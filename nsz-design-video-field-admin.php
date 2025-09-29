<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (!function_exists('nsz_encrypt_value')) {
    function nsz_encrypt_value($value) {
        if (empty($value)) {
            return '';
        }

        $key = hash('sha256', AUTH_SALT . SECURE_AUTH_SALT, true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt(
                $value,
                'AES-256-CBC',
                $key,
                0,
                $iv
        );

        if ($encrypted === false) {
            return '';
        }

        return base64_encode($iv . $encrypted);
    }
}

if (!function_exists('nsz_decrypt_value')) {
    function nsz_decrypt_value($encrypted_data) {
        if (empty($encrypted_data)) {
            return '';
        }

        $decoded = base64_decode($encrypted_data);
        if ($decoded === false) {
            return '';
        }

        // Verify we have enough data for IV (16 bytes) plus at least 1 byte of encrypted data
        if (strlen($decoded) < 17) {
            return '';
        }

        $iv = substr($decoded, 0, 16);
        // Verify IV length is exactly 16 bytes
        if (strlen($iv) !== 16) {
            return '';
        }

        $encrypted = substr($decoded, 16);
        $key = hash('sha256', AUTH_SALT . SECURE_AUTH_SALT, true);
        $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $key,
                0,
                $iv
        );

        return $decrypted === false ? '' : $decrypted;
    }
}

if (!function_exists('nsz_obfuscate_string')) {
    function nsz_obfuscate_string($string, $show_start = 4, $show_end = 4) {
        if (empty($string)) {
            return '';
        }

        $length = strlen($string);
        if ($length <= ($show_start + $show_end)) {
            return str_repeat('*', $length);
        }

        $start = substr($string, 0, $show_start);
        $end = substr($string, -$show_end);
        $middle_length = $length - ($show_start + $show_end);

        return $start . str_repeat('*', $middle_length) . $end;
    }
}

function nsz_design_video_field_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'), 403);
    }

    $nsz_cfstream_api_field = 'nsz_cfstream_api_key';
    $nsz_cfstream_account_id_field = 'nsz_cfstream_account_id';
    $nsz_cfstream_account_email_field = 'nsz_cfstream_account_email';

    if (isset($_POST['submitted']) && $_POST['submitted'] === 'Y') {
        if (!isset($_POST['nsz_video_settings_nonce']) ||
                !wp_verify_nonce($_POST['nsz_video_settings_nonce'], 'nsz_video_settings_action')) {
            wp_die(__('Security check failed.'), 403);
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'), 403);
        }

        $last_update = get_option('nsz_settings_last_update', 0);
        if (time() - $last_update < 5) {
            wp_die(__('Please wait a few seconds before submitting again.'), 403);
        }

        $nsz_cfstream_api_value = get_option($nsz_cfstream_api_field, null);
        if (isset($_POST[$nsz_cfstream_api_field]) && !str_contains($_POST[$nsz_cfstream_api_field], '*')) {
            $nsz_cfstream_api_value = nsz_encrypt_value(sanitize_text_field($_POST[$nsz_cfstream_api_field]));
        }
        update_option($nsz_cfstream_api_field, $nsz_cfstream_api_value);

        $nsz_cfstream_account_id_value = isset($_POST[$nsz_cfstream_account_id_field])
                ? nsz_encrypt_value(sanitize_text_field($_POST[$nsz_cfstream_account_id_field]))
                : '';
        update_option($nsz_cfstream_account_id_field, $nsz_cfstream_account_id_value);

        $nsz_cfstream_account_email_value = isset($_POST[$nsz_cfstream_account_email_field])
                ? nsz_encrypt_value(sanitize_email($_POST[$nsz_cfstream_account_email_field]))
                : '';
        update_option($nsz_cfstream_account_email_field, $nsz_cfstream_account_email_value);

        update_option('nsz_settings_last_update', time());

        add_settings_error(
                'nsz_video_settings',
                'nsz_video_settings_updated',
                __('Settings updated successfully.'),
                'updated'
        );
    }

    $nsz_cfstream_api_value = nsz_decrypt_value(get_option($nsz_cfstream_api_field, ''));
    $nsz_cfstream_account_id_value = nsz_decrypt_value(get_option($nsz_cfstream_account_id_field, ''));
    $nsz_cfstream_account_email_value = nsz_decrypt_value(get_option($nsz_cfstream_account_email_field, ''));

    $wordmark_url = esc_url(plugins_url('assets/wordmark.svg', __FILE__));

    settings_errors('nsz_video_settings');
    ?>
    <section class="nsz-design-video-admin">
        <header class="nsz-design-video-header">
            <div class="nsz-design-video-container">
                <h1 class="nsz-design-video-header-title">
                    <img src="<?php echo $wordmark_url; ?>" alt="970 Design Wordmark" class="nsz-design-video-wordmark">
                    <?php esc_html_e('Video Field Settings'); ?>
                </h1>
            </div>
        </header>

        <section class="nsz-design-video-container">
            <div class="nsz-design-video-card">
                <form method="post" action="">
                    <?php wp_nonce_field('nsz_video_settings_action', 'nsz_video_settings_nonce'); ?>

                    <h2 class="nsz-design-video-title"><?php esc_html_e('Cloudflare Settings'); ?></h2>

                    <div class="nsz-design-video-row">
                        <div>
                            <label for="<?php echo esc_attr($nsz_cfstream_api_field); ?>">
                                <?php esc_html_e('API Token:'); ?> <span class="required">*</span>
                            </label>
                            <input required
                                   type="text"
                                   id="<?php echo esc_attr($nsz_cfstream_api_field); ?>"
                                   name="<?php echo esc_attr($nsz_cfstream_api_field); ?>"
                                   value="<?php echo esc_attr(nsz_obfuscate_string($nsz_cfstream_api_value)); ?>"
                                   size="35">
                            <br><br>
                            <span class="small">
                                <a href="https://developers.cloudflare.com/fundamentals/api/get-started/create-token/"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    <?php esc_html_e('How to create an API Token'); ?>
                                </a>
                            </span>
                        </div>
                    </div>

                    <div class="nsz-design-video-row">
                        <div>
                            <label for="<?php echo esc_attr($nsz_cfstream_account_id_field); ?>">
                                <?php esc_html_e('Account ID:'); ?> <span class="required">*</span>
                            </label>
                            <input required
                                   type="text"
                                   id="<?php echo esc_attr($nsz_cfstream_account_id_field); ?>"
                                   name="<?php echo esc_attr($nsz_cfstream_account_id_field); ?>"
                                   value="<?php echo esc_attr($nsz_cfstream_account_id_value); ?>"
                                   size="35">
                            <br><br>
                            <span class="small">
                                <a href="https://developers.cloudflare.com/fundamentals/setup/find-account-and-zone-ids/"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    <?php esc_html_e('How to find your Account ID'); ?>
                                </a>
                            </span>
                        </div>
                    </div>

                    <div class="nsz-design-video-row">
                        <div>
                            <label for="<?php echo esc_attr($nsz_cfstream_account_email_field); ?>">
                                <?php esc_html_e('Account Email:'); ?> <span class="required">*</span>
                            </label>
                            <input required
                                   type="email"
                                   id="<?php echo esc_attr($nsz_cfstream_account_email_field); ?>"
                                   name="<?php echo esc_attr($nsz_cfstream_account_email_field); ?>"
                                   value="<?php echo esc_attr($nsz_cfstream_account_email_value); ?>"
                                   size="35">
                        </div>
                    </div>

                    <footer class="nsz-design-video-footer">
                        <input type="hidden" name="submitted" value="Y">
                        <div class="submit">
                            <input type="submit"
                                   name="Submit"
                                   class="button-primary"
                                   value="<?php esc_attr_e('Save Changes'); ?>" />
                        </div>
                    </footer>
                </form>
            </div>
        </section>
    </section>
    <?php
}

function nsz_design_video_field_menu_item() {
    add_options_page(
            __('Cloudflare Stream ACF Field Settings'),
            __('Cloudflare Stream ACF Field Settings'),
            'manage_options',
            'nsz_design_video_field_settings',
            'nsz_design_video_field_settings_page'
    );
}

add_action('admin_menu', 'nsz_design_video_field_menu_item');