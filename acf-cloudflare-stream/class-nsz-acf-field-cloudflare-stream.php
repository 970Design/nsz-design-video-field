<?php
/**
 * Defines the custom field type class.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * nsz_acf_field_cloudflare_stream class.
 */
class nsz_design_video_field_acf_field_cloudflare_stream extends \acf_field {
    /**
     * Controls field type visibilty in REST requests.
     *
     * @var bool
     */
    public $show_in_rest = true;

    /**
     * Environment values relating to the theme or plugin.
     *
     * @var array $env Plugin or theme context such as 'url' and 'version'.
     */
    private $env;

    /**
     * Constructor.
     */
    public function __construct() {
        /**
         * Field type reference used in PHP and JS code.
         *
         * No spaces. Underscores allowed.
         */
        $this->name = 'cloudflare_stream';

        /**
         * Field type label.
         *
         * For public-facing UI. May contain spaces.
         */
        $this->label = __( 'Cloudflare Stream', 'cloudflare-stream' );

        /**
         * The category the field appears within in the field type picker.
         */
        $this->category = 'content'; // basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME

        /**
         * Field type Description.
         *
         * For field descriptions. May contain spaces.
         */
        $this->description = __( 'This field uploads videos to Cloudflare Stream and provides manifest URLs, thumbnails and video options.', 'cloudflare-stream' );

        /**
         * Field type Doc URL.
         *
         * For linking to a documentation page. Displayed in the field picker modal.
         */
        $this->doc_url = '';

        /**
         * Field type Tutorial URL.
         *
         * For linking to a tutorial resource. Displayed in the field picker modal.
         */
        $this->tutorial_url = '';

	    /**
	     * Defaults for your custom user-facing settings for this field type.
	     */
	    $this->defaults = array(
		    'default_autoplay' => 0,
		    'default_muted' => 0,
		    'default_controls' => 0,
		    'default_loop' => 0,
				'default_play_scrolled_into_view' => 0,
	    );

        /**
         * Strings used in JavaScript code.
         *
         * Allows JS strings to be translated in PHP and loaded in JS via:
         *
         * ```js
         * const errorMessage = acf._e("cloudflare_stream", "error");
         * ```
         */
        $this->l10n = array(
                'error'	=> __( 'Error! Please enter a higher value', 'cloudflare-stream' ),
        );

        $plugin_dir = WP_PLUGIN_DIR . '/nsz-design-video-field/nsz-design-video-field.php';
        $plugin_data = get_plugin_data($plugin_dir);
        $plugin_version = $plugin_data['Version'] ?? '1.0';

        $this->env = array(
                'url'     => plugin_dir_url( __FILE__ ),
                'version' => $plugin_version,
        );

        /**
         * Field type preview image.
         *
         * A preview image for the field type in the picker modal.
         */
        $this->preview_image = $this->env['url'] . '/assets/images/field-preview-custom.png';

        // Register server-side AJAX proxies for Cloudflare API calls.
        add_action( 'wp_ajax_nsz_cfstream_list_videos',      array( $this, 'ajax_list_videos' ) );
        add_action( 'wp_ajax_nsz_cfstream_get_video',        array( $this, 'ajax_get_video' ) );
        add_action( 'wp_ajax_nsz_cfstream_delete_video',     array( $this, 'ajax_delete_video' ) );
        add_action( 'wp_ajax_nsz_cfstream_create_upload_url', array( $this, 'ajax_create_upload_url' ) );

        $this->input_admin_enqueue_scripts();

        parent::__construct();
    }

    /**
     * Returns the decrypted Cloudflare account ID, validated as a 32-char hex string.
     *
     * Sends a JSON error and exits if the value is missing or malformed.
     *
     * @return string
     */
    private function get_cf_account_id() {
        $account_id = nsz_decrypt_value( get_option( 'nsz_cfstream_account_id', '' ) );
        if ( empty( $account_id ) || ! preg_match( '/^[a-f0-9]{32}$/', $account_id ) ) {
            wp_send_json_error( 'Cloudflare Account ID is not configured or is invalid.', 400 );
        }
        return $account_id;
    }

    /**
     * Returns auth headers array suitable for wp_remote_* calls.
     *
     * @return array
     */
    private function get_cf_auth_headers() {
        $auth_type = get_option( 'nsz_cfstream_auth_type', 'global_api_key' );
        if ( $auth_type === 'api_token' ) {
            return array(
                'Authorization' => 'Bearer ' . nsz_decrypt_value( get_option( 'nsz_cfstream_api_key', '' ) ),
            );
        }
        return array(
            'X-Auth-Email' => nsz_decrypt_value( get_option( 'nsz_cfstream_account_email', '' ) ),
            'X-Auth-Key'   => nsz_decrypt_value( get_option( 'nsz_cfstream_global_api_key', '' ) ),
        );
    }

    /**
     * AJAX proxy: list all videos in the account.
     */
    public function ajax_list_videos() {
        check_ajax_referer( 'nsz_cfstream_ajax', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $account_id = $this->get_cf_account_id();
        $response   = wp_remote_get(
            "https://api.cloudflare.com/client/v4/accounts/{$account_id}/stream",
            array( 'headers' => $this->get_cf_auth_headers(), 'timeout' => 30 )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Failed to connect to Cloudflare API.' );
        }

        $this->send_validated_json( $response );
    }

    /**
     * AJAX proxy: fetch a single video's details.
     */
    public function ajax_get_video() {
        check_ajax_referer( 'nsz_cfstream_ajax', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $video_id = sanitize_text_field( $_POST['video_id'] ?? '' );
        if ( empty( $video_id ) || ! preg_match( '/^[a-f0-9]{32}$/', $video_id ) ) {
            wp_send_json_error( 'Invalid video_id', 400 );
        }

        $account_id = $this->get_cf_account_id();
        $response   = wp_remote_get(
            "https://api.cloudflare.com/client/v4/accounts/{$account_id}/stream/{$video_id}",
            array( 'headers' => $this->get_cf_auth_headers(), 'timeout' => 30 )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Failed to connect to Cloudflare API.' );
        }

        $this->send_validated_json( $response );
    }

    /**
     * AJAX proxy: delete a video.
     */
    public function ajax_delete_video() {
        check_ajax_referer( 'nsz_cfstream_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $video_id = sanitize_text_field( $_POST['video_id'] ?? '' );
        if ( empty( $video_id ) || ! preg_match( '/^[a-f0-9]{32}$/', $video_id ) ) {
            wp_send_json_error( 'Invalid video_id', 400 );
        }

        $account_id = $this->get_cf_account_id();
        $response   = wp_remote_request(
            "https://api.cloudflare.com/client/v4/accounts/{$account_id}/stream/{$video_id}",
            array( 'method' => 'DELETE', 'headers' => $this->get_cf_auth_headers(), 'timeout' => 30 )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Failed to connect to Cloudflare API.' );
        }

        $this->send_validated_json( $response );
    }

    /**
     * AJAX proxy: create a pre-authorized direct upload URL.
     *
     * Returns a short-lived Cloudflare-issued upload URL so the browser can
     * upload directly without ever sending credentials client-side.
     */
    public function ajax_create_upload_url() {
        check_ajax_referer( 'nsz_cfstream_ajax', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $account_id = $this->get_cf_account_id();

        $body = array( 'maxDurationSeconds' => 21600 );
        $file_name = sanitize_text_field( $_POST['file_name'] ?? '' );
        if ( ! empty( $file_name ) ) {
            $body['meta'] = array( 'name' => $file_name );
        }

        $response   = wp_remote_post(
            "https://api.cloudflare.com/client/v4/accounts/{$account_id}/stream/direct_upload",
            array(
                'headers' => array_merge(
                    $this->get_cf_auth_headers(),
                    array( 'Content-Type' => 'application/json' )
                ),
                'body'    => json_encode( $body ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Failed to connect to Cloudflare API.' );
        }

        $this->send_validated_json( $response );
    }

    /**
     * Validate that a wp_remote response contains valid JSON and send it.
     *
     * @param array $response A wp_remote_* response array.
     */
    private function send_validated_json( $response ) {
        $body    = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body );

        if ( $decoded === null && json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( 'Invalid response from Cloudflare API.' );
        }

        wp_send_json( $decoded );
    }

    /**
     * Settings to display when users configure a field of this type.
     *
     * These settings appear on the ACF "Edit Field Group" admin page when
     * setting up the field.
     *
     * @param array $field
     * @return void
     */
    public function render_field_settings( $field ) {
        /*
         * Repeat for each setting you wish to display for this field type.
         */

	    acf_render_field_setting(
		    $field,
		    array(
			    'label'			=> __( 'Hide File Info','cloudflare-stream' ),
			    'instructions'	=> __( 'Hide the detailed file information.','cloudflare-stream' ),
			    'type'			=> 'true_false',
			    'name'			=> 'hide_file_info',
		    )
	    );

        acf_render_field_setting(
                $field,
                array(
                        'label'			=> __( 'Hide Options','cloudflare-stream' ),
                        'instructions'	=> __( 'Hide the video player options.','cloudflare-stream' ),
                        'type'			=> 'true_false',
                        'name'			=> 'hide_options',
                )
        );

	    // Default video player options (only visible when hide_options is not checked)
	    acf_render_field_setting(
		    $field,
		    array(
			    'label'			=> __( 'Default: Autoplay','cloudflare-stream' ),
			    'instructions'	=> __( 'Set autoplay as default for new videos. Note: Autoplay will automatically enable muted.','cloudflare-stream' ),
			    'type'			=> 'true_false',
			    'name'			=> 'default_autoplay',
			    'ui'            => 1,
			    'conditional_logic' => array(
				    array(
					    array(
						    'field' => 'hide_options',
						    'operator' => '!=',
						    'value' => '1',
					    ),
				    ),
			    ),
		    )
	    );

	    acf_render_field_setting(
		    $field,
		    array(
			    'label'			=> __( 'Default: Muted','cloudflare-stream' ),
			    'instructions'	=> __( 'Set muted as default for new videos.','cloudflare-stream' ),
			    'type'			=> 'true_false',
			    'name'			=> 'default_muted',
			    'ui'            => 1,
			    'conditional_logic' => array(
				    array(
					    array(
						    'field' => 'hide_options',
						    'operator' => '!=',
						    'value' => '1',
					    ),
				    ),
			    ),
		    )
	    );

	    acf_render_field_setting(
		    $field,
		    array(
			    'label'			=> __( 'Default: Controls','cloudflare-stream' ),
			    'instructions'	=> __( 'Set controls as default for new videos.','cloudflare-stream' ),
			    'type'			=> 'true_false',
			    'name'			=> 'default_controls',
			    'ui'            => 1,
			    'conditional_logic' => array(
				    array(
					    array(
						    'field' => 'hide_options',
						    'operator' => '!=',
						    'value' => '1',
					    ),
				    ),
			    ),
		    )
	    );

	    acf_render_field_setting(
		    $field,
		    array(
			    'label'			=> __( 'Default: Loop','cloudflare-stream' ),
			    'instructions'	=> __( 'Set loop as default for new videos.','cloudflare-stream' ),
			    'type'			=> 'true_false',
			    'name'			=> 'default_loop',
			    'ui'            => 1,
			    'conditional_logic' => array(
				    array(
					    array(
						    'field' => 'hide_options',
						    'operator' => '!=',
						    'value' => '1',
					    ),
				    ),
			    ),
		    )
	    );

        // To render field settings on other tabs in ACF 6.0+:
        // https://www.advancedcustomfields.com/resources/adding-custom-settings-fields/#moving-field-setting
    }

    /**
     * HTML content to show when a publisher edits the field on the edit screen.
     *
     * @param array $field The field settings and values.
     * @return void
     */
	public function render_field( $field ) {
		$auth_type  = get_option('nsz_cfstream_auth_type', 'global_api_key');
		$account_id = nsz_decrypt_value(get_option('nsz_cfstream_account_id', ''));

		if ($auth_type === 'api_token') {
			$credentials_valid = nsz_decrypt_value(get_option('nsz_cfstream_api_key', '')) && $account_id;
		} else {
			$credentials_valid = nsz_decrypt_value(get_option('nsz_cfstream_global_api_key', ''))
				&& $account_id
				&& nsz_decrypt_value(get_option('nsz_cfstream_account_email', ''));
		}

		if ($credentials_valid) {
			$hls = $field['value']['hls'] ?? '';
			$dash = $field['value']['dash'] ?? '';
			$thumbnail = $field['value']['thumbnail'] ?? '';
			$preview = $field['value']['preview'] ?? '';
			$filename = $field['value']['filename'] ?? '';

			// Use default values if no value is set yet
			$is_new_video = empty($field['value']['hls']) && empty($field['value']['dash']);

			if ($is_new_video) {
				// Apply defaults for new videos
				$default_autoplay = $field['default_autoplay'] ?? 0;
				$default_muted = $field['default_muted'] ?? 0;
				$default_controls = $field['default_controls'] ?? 1;
				$default_loop = $field['default_loop'] ?? 0;
				$default_play_scrolled_into_view = $field['default_play_scrolled_into_view'] ?? 0;

				// If autoplay is default, force muted to be true
				if ($default_autoplay) {
					$default_muted = 1;
				}

				$muted = $default_muted;
				$autoplay = $default_autoplay;
				$controls = $default_controls;
				$loop = $default_loop;
				$play_scrolled_into_view = $default_play_scrolled_into_view;
			} else {
				// Use saved values for existing videos
				$autoplay = $field['value']['autoplay'] ?? false;
				$controls = $field['value']['controls'] ?? false;
				$play_scrolled_into_view = $field['value']['play_scrolled_into_view'] ?? false;

				if($play_scrolled_into_view) {
					$loop = true;
					$muted = true;
				} else {
					$loop = $field['value']['loop'] ?? false;
					$muted = $field['value']['muted'] ?? false;
				}
			}

			$hide_options = $field['hide_options'] ?? false;
			$hide_file_info = $field['hide_file_info'] ?? false;

			$is_video_uploaded = false;
			if ($hls || $dash || $thumbnail || $preview) {
				$is_video_uploaded = true;
			}
			?>

			<div class="cloudflare-stream-wrapper">

				<div class="wrap-upper-nav">
					<div class="wrap-browse-field">
						<button class="nsz-cloudflare-stream-browse-modal button-primary">Browse Existing Videos</button>
						<dialog class="nsz-cloudflare-stream-modal">
							<div class="nsz-cloudflare-stream-modal-listing">

							</div>
							<form method="dialog">
								<button class="nsz-cloudflare-stream-close-modal button-primary">Close</button>
							</form>
						</dialog>
					</div>

					<?php if ($is_video_uploaded) : ?>
						<div class="wrap-item cloudflare-video-clear-wrapper">
							<button class="nsz-cloudflare-stream-clear-video button-primary" type="button">Clear Video</button>
						</div>
					<?php endif; ?>
				</div>

				<div class="wrap-upload-field <?php if (!$is_video_uploaded) : ?> active <?php endif; ?>">
					<div class="acf-label">
						<label for="nsz-cloudflare-stream-file">Choose a video to upload:</label>
					</div>
					<div class="acf-input">
						<input
								type="file"
								class="nsz-cloudflare-stream-file"
								id="nsz-cloudflare-stream-file"
								name="<?php echo esc_attr($field['name']) ?>[filename]"
								value=""
						/>
					</div>
				</div>

				<div class="cloudflare-stream-progress-wrap">
					<span>Uploading to Cloudflare... </span>
					<progress class="cloudflare-stream-progress-bar" value="0" max="100"></progress>
				</div>

				<div class="cloudflare-stream-success"> </div>

				<div class="cloudflare-stream-error"> </div>

				<div class="cloudflare-video-details <?php if ($is_video_uploaded) : ?> active <?php endif; ?>">

					<h4><span class="data-filename-display"><?php echo esc_html($filename); ?></span> Details:</h4>

					<div class="cloudflare-video-details-info">
						<div class="wrap-item wrap-thumbnail">
							<img class="cloudflare-video-thumbnail-preview" src="<?php echo esc_url($thumbnail); ?>" />
						</div>

						<input class="data-filename" type="hidden" name="<?php echo esc_attr($field['name']) ?>[filename]" value="<?php echo esc_attr($filename); ?>" />

						<div class="cloudflare-video-details-options">

							<?php if (!$hide_options) : ?>
								<div class="cloudflare-video-details-option-holder">
									<div class="wrap-item">
										<div class="acf-label"><label>Muted</label></div>
										<input type="hidden" name="<?php echo esc_attr($field['name']) ?>[muted]" value="0" />
										<input type="checkbox" class="data-muted" name="<?php echo esc_attr($field['name']) ?>[muted]" value="1" <?php if ($muted) : ?> checked <?php endif; ?> />
									</div>
									<div class="wrap-item">
										<div class="acf-label"><label>Autoplay</label></div>
										<input type="hidden" name="<?php echo esc_attr($field['name']) ?>[autoplay]" value="0" />
										<input type="checkbox" class="data-autoplay" name="<?php echo esc_attr($field['name']) ?>[autoplay]" value="1" <?php if ($autoplay) : ?> checked <?php endif; ?> />
									</div>
									<div class="wrap-item">
										<div class="acf-label"><label>Loop</label></div>
										<input type="hidden" name="<?php echo esc_attr($field['name']) ?>[loop]" value="0" />
										<input type="checkbox" class="data-loop" name="<?php echo esc_attr($field['name']) ?>[loop]" value="1" <?php if ($loop) : ?> checked <?php endif; ?> />
									</div>
									<div class="wrap-item">
										<div class="acf-label"><label>Controls</label></div>
										<input type="hidden" name="<?php echo esc_attr($field['name']) ?>[controls]" value="0" />
										<input type="checkbox" class="data-controls" name="<?php echo esc_attr($field['name']) ?>[controls]" value="1" <?php if ($controls) : ?> checked <?php endif; ?> />
									</div>
									<div class="wrap-item">
										<div class="acf-label"><label>Play when scrolled into view</label></div>
										<input type="hidden" name="<?php echo esc_attr($field['name']) ?>[play_scrolled_into_view]" value="0" />
										<input type="checkbox" class="data-play-scrolled-into-view" name="<?php echo esc_attr($field['name']) ?>[play_scrolled_into_view]" value="1" <?php if ($play_scrolled_into_view) : ?> checked <?php endif; ?> />
									</div>

								</div>
							<?php endif; ?>

							<?php if (!$hide_file_info) : ?>
								<div class="cloudflare-video-details-item-holder">
									<div class="wrap-item wrap-hls">
										<div class="acf-label"><label>HLS Manifest</label></div>
										<input  class="data-hls" type="text" name="<?php echo esc_attr($field['name']) ?>[hls]" value="<?php echo esc_attr($hls); ?>"/>
									</div>

									<div class="wrap-item wrap-dash">
										<div class="acf-label"><label>Dash Manifest</label></div>
										<input  class="data-dash" type="text" name="<?php echo esc_attr($field['name']) ?>[dash]" value="<?php echo esc_attr($dash); ?>" />
									</div>

									<div class="wrap-item wrap-thumbnail">
										<div class="acf-label"><label>Thumbnail</label></div>
										<input class="data-thumbnail" type="text" name="<?php echo esc_attr($field['name']) ?>[thumbnail]" value="<?php echo esc_attr($thumbnail); ?>" />
									</div>

									<div class="wrap-item wrap-preview">
										<div class="acf-label"> <label>Preview</label></div>
										<input class="data-preview" type="text" name="<?php echo esc_attr($field['name']) ?>[preview]" value="<?php echo esc_attr($preview); ?>" />
									</div>
								</div>
							<?php endif; ?>
						</div>
					</div>

				</div>

			</div>

			<?php

		} else {
			echo '<p>Cloudflare Stream API Key, Account ID, and Account Email must be set in the 970 Design Video Field settings page.</p>';
		}


	}

    /**
     * Sanitize field values before they are saved to the database.
     *
     * @param mixed $value The field value.
     * @param int $post_id The post ID.
     * @param array $field The field array.
     * @return mixed
     */
    public function update_value( $value, $post_id, $field ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }

        // Sanitize URLs.
        $value['hls']       = isset( $value['hls'] )       ? esc_url_raw( $value['hls'] )       : '';
        $value['dash']      = isset( $value['dash'] )      ? esc_url_raw( $value['dash'] )      : '';
        $value['thumbnail'] = isset( $value['thumbnail'] ) ? esc_url_raw( $value['thumbnail'] ) : '';
        $value['preview']   = isset( $value['preview'] )   ? esc_url_raw( $value['preview'] )   : '';

        // Sanitize text.
        $value['filename'] = isset( $value['filename'] ) ? sanitize_text_field( $value['filename'] ) : '';

        // Normalize boolean options to '0' or '1'.
        foreach ( array( 'autoplay', 'muted', 'controls', 'loop', 'play_scrolled_into_view' ) as $opt ) {
            $value[ $opt ] = empty( $value[ $opt ] ) ? '0' : '1';
        }

        // Force loop and muted when play_scrolled_into_view is enabled.
        if ( $value['play_scrolled_into_view'] === '1' ) {
            $value['loop']  = '1';
            $value['muted'] = '1';
        }

        return $value;
    }

    /**
     * Enqueues CSS and JavaScript needed by HTML in the render_field() method.
     *
     * Callback for admin_enqueue_script.
     *
     * @return void
     */
    public function input_admin_enqueue_scripts() {
        $url     = plugin_dir_url( __FILE__ );
        $version = $this->env['version'];

        wp_register_script(
                'nsz-cloudflare-stream',
                "{$url}assets/js/field.js",
                array( 'acf-input' ),
                $version
        );

        wp_register_style(
                'nsz-cloudflare-stream',
                "{$url}assets/css/field.css",
                array( 'acf-input' ),
                $version
        );

        wp_enqueue_script( 'nsz-cloudflare-stream' );
        wp_enqueue_style( 'nsz-cloudflare-stream' );

        // No credentials are passed to the browser — all Cloudflare API calls are
        // proxied through WP AJAX. Uploads use a server-issued direct upload URL.
        $params = array(
                'nonce' => wp_create_nonce( 'nsz_cfstream_ajax' ),
        );

        wp_localize_script( 'nsz-cloudflare-stream', 'nsz_cloudflare_stream', $params );
    }
}
