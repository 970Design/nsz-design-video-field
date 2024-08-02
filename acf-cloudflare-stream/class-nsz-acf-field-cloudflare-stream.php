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
        $this->description = __( 'This field uploads videos to Cloudflare stream and provide a video URL.', 'cloudflare-stream' );

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
        /*
		$this->defaults = array(
			'font_size'	=> 14,
		);
        */

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

        $protocol = is_ssl() ? 'https://' : 'http://';
        $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $this->env = array(
            'url'     => $protocol.$domainName.'/app/plugins/nsz-design-video-field/acf-cloudflare-stream/', // URL to the acf-cloudflare-stream directory.
            'version' => '1.0', // Replace this with your theme or plugin version constant.
        );

        /**
         * Field type preview image.
         *
         * A preview image for the field type in the picker modal.
         */
        $this->preview_image = $this->env['url'] . '/assets/images/field-preview-custom.png';

        $this->input_admin_enqueue_scripts();

        parent::__construct();
    }

    /**
     * Settings to display when users configure a field of this type.
     *
     * These settings appear on the ACF “Edit Field Group” admin page when
     * setting up the field.
     *
     * @param array $field
     * @return void
     */
    public function render_field_settings( $field ) {
        /*
         * Repeat for each setting you wish to display for this field type.
         */
        /*
		acf_render_field_setting(
			$field,
			array(
				'label'			=> __( 'Font Size','cloudflare-stream' ),
				'instructions'	=> __( 'Customise the input font size','cloudflare-stream' ),
				'type'			=> 'number',
				'name'			=> 'font_size',
				'append'		=> 'px',
			)
		);
        */

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
        // Debug output to show what field data is available.
        //echo '<pre>';
        //print_r( $field );
        //echo '</pre>';

        $api_token = get_option('nsz_cfstream_api_key');
        $account_id = get_option('nsz_cfstream_account_id');
        $account_email = get_option('nsz_cfstream_account_email');

        if ($api_token && $account_id && $account_email) {
            //loop through $field['value'] and create variables based on key names
            //(unsure why they are sometimes undefined when referenced by key)
            if (is_array($field['value']) && !empty($field['value'])) {
                foreach ($field['value'] as $key => $value) {
                    $$key = $value;
                }
            }

            if (!isset($hls)) { $hls = ''; }
            if (!isset($dash)) { $dash = ''; }
            if (!isset($thumbnail)) { $thumbnail = ''; }
            if (!isset($preview)) { $preview = ''; }
            if (!isset($filename)) { $filename = ''; }

            $is_video_uploaded = false;
            if ($hls || $dash || $thumbnail || $preview) {
                $is_video_uploaded = true;
            }

            ?>

            <div class="cloudflare-stream-wrapper">
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

                    <h4><span class="data-filename"><?php echo $filename; ?></span> Details:</h4>

                    <div class="wrap-item wrap-thumbnail">
                        <img class="cloudflare-video-thumbnail-preview" src="<?php echo $thumbnail ?>" />
                    </div>

                    <div class="cloudflare-video-details-item-holder">
                        <div class="wrap-item wrap-hls">
                            <div class="acf-label"><label>HLS Manifest</label></div>
                            <input  class="data-hls" type="text" name="<?php echo esc_attr($field['name']) ?>[hls]" value="<?php echo $hls ?>"/>
                        </div>

                        <div class="wrap-item wrap-dash">
                            <div class="acf-label"><label>Dash Manifest</label></div>
                            <input  class="data-dash" type="text" name="<?php echo esc_attr($field['name']) ?>[dash]" value="<?php echo $dash ?>" />
                        </div>

                        <div class="wrap-item wrap-thumbnail">
                            <div class="acf-label"><label>Thumbnail</label></div>
                            <input class="data-thumbnail" type="text" name="<?php echo esc_attr($field['name']) ?>[thumbnail]" value="<?php echo $thumbnail ?>" />
                        </div>

                        <div class="wrap-item wrap-preview">
                            <div class="acf-label"> <label>Preview</label></div>
                            <input class="data-preview" type="text" name="<?php echo esc_attr($field['name']) ?>[preview]" value="<?php echo $preview ?>" />
                        </div>
                    </div>

                    <div class="wrap-item cloudflare-video-clear-wrapper">
                        <button class="nsz-cloudflare-stream-clear-video button-primary" type="button">Clear Video</button>
                    </div>
                </div>

            </div>

            <?php

        } else {
            echo '<p>Cloudflare Stream API Key, Account ID, and Account Email must be set in the 970 Design Video Field settings page.</p>';
        }


    }

    /**
     * Enqueues CSS and JavaScript needed by HTML in the render_field() method.
     *
     * Callback for admin_enqueue_script.
     *
     * @return void
     */
    public function input_admin_enqueue_scripts() {
        $url     = trailingslashit( $this->env['url'] );
        $version = $this->env['version'];

        wp_register_script(
            'nsz-cloudflare-stream-tus',
            "{$url}assets/js/tus.min.js",
            array( 'acf-input' ),
            $version
        );

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

        wp_enqueue_script( 'nsz-cloudflare-stream-tus' );
        wp_enqueue_script( 'nsz-cloudflare-stream' );
        wp_enqueue_style( 'nsz-cloudflare-stream' );

        $api_token = get_option('nsz_cfstream_api_key');
        $account_id = get_option('nsz_cfstream_account_id');
        $account_email = get_option('nsz_cfstream_account_email');

        $params = array(
            'api_token' => $api_token,
            'account_id' => $account_id,
            'account_email' => $account_email,
        );

        wp_localize_script( 'nsz-cloudflare-stream', 'nsz_cloudflare_stream', $params );
    }
}
