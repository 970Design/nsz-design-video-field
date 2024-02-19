<?php

function nsz_design_video_field_settings_page() {
    // Must check that the user has the required capability
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Define variables for the field and option names
    $nsz_cfstream_api_field = 'nsz_cfstream_api_key';
    $nsz_cfstream_account_id_field = 'nsz_cfstream_account_id';
    $nsz_cfstream_account_email_field = 'nsz_cfstream_account_email';

    // Check if the form has been submitted
    if (isset($_POST['submitted']) && $_POST['submitted'] == 'Y') {
        // Sanitize and save the API Key
        $nsz_cfstream_api_value = filter_var(($_POST[$nsz_cfstream_api_field] ?? ''), FILTER_SANITIZE_STRING);
        update_option($nsz_cfstream_api_field, $nsz_cfstream_api_value);

        // Sanitize and save the Account ID
        $nsz_cfstream_account_id_value = filter_var(($_POST[$nsz_cfstream_account_id_field] ?? ''), FILTER_SANITIZE_STRING);
        update_option($nsz_cfstream_account_id_field, $nsz_cfstream_account_id_value);

        // Sanitize and save the Account Email
        $nsz_cfstream_account_email_value = filter_var(($_POST[$nsz_cfstream_account_email_field] ?? ''), FILTER_SANITIZE_EMAIL);
        update_option($nsz_cfstream_account_email_field, $nsz_cfstream_account_email_value);

        // Display a success message
        ?>
        <div class="updated"><p><strong>Settings Updated</strong></p></div>
        <?php
    }

    // Retrieve the current values to display in the form
    $nsz_cfstream_api_value = get_option($nsz_cfstream_api_field, '');
    $nsz_cfstream_account_id_value = get_option($nsz_cfstream_account_id_field, '');
    $nsz_cfstream_account_email_value = get_option($nsz_cfstream_account_email_field, '');

    // Assets
    $wordmark_url = plugins_url( 'assets/wordmark.svg', __FILE__ );

    ?>
    <section class="nsz-design-video-admin">

        <header class="nsz-design-video-header">
            <div class="nsz-design-video-container">
                <h1 class="nsz-design-video-header-title">
                    <img src="<?php echo esc_url( $wordmark_url ); ?>" alt="970 Design Wordmark" class="nsz-design-video-wordmark"> Video Field Settings
                </h1>
            </div>
        </header>

        <section class="nsz-design-video-container">
            <div class="nsz-design-video-card">
                <form method="post" action="">
                    <h2 class="nsz-design-video-title">Cloudflare Settings</h2>

                    <div class="nsz-design-video-row">
                        <div>
                            <label for="<?php echo $nsz_cfstream_api_field; ?>">API Token: <span class="required">*</span></label>
                            <input required type="text" id="<?php echo $nsz_cfstream_api_field; ?>" name="<?php echo $nsz_cfstream_api_field; ?>" value="<?php echo esc_attr($nsz_cfstream_api_value); ?>" size="35">
                            <br><br>
                            <span class="small"><a href="https://developers.cloudflare.com/fundamentals/api/get-started/create-token/" target="_blank">How to create an API Token</a></span>
                        </div>
                    </div>
                    <div class="nsz-design-video-row">
                        <div>
                            <label for="<?php echo $nsz_cfstream_account_id_field; ?>">Account ID: <span class="required">*</span></label>
                            <input required type="text" id="<?php echo $nsz_cfstream_account_id_field; ?>" name="<?php echo $nsz_cfstream_account_id_field; ?>" value="<?php echo esc_attr($nsz_cfstream_account_id_value); ?>" size="35">
                            <br><br>
                            <span class="small">
                                <a href="https://developers.cloudflare.com/fundamentals/setup/find-account-and-zone-ids/ " target="_blank">How to find your Account ID</a>
                            </span>
                        </div>
                    </div>
                    <div class="nsz-design-video-row">
                        <div>
                            <label for="<?php echo $nsz_cfstream_account_email_field; ?>">Account Email: <span class="required">*</span></label>
                            <input required type="email" id="<?php echo $nsz_cfstream_account_email_field; ?>" name="<?php echo $nsz_cfstream_account_email_field; ?>" value="<?php echo esc_attr($nsz_cfstream_account_email_value); ?>" size="35">
                        </div>
                    </div>
                    <footer class="nsz-design-video-footer">
                        <input type="hidden" name="submitted" value="Y">
                        <div class="submit">
                            <input type="submit" name="Submit" class="button-primary" value="Save Changes" />
                        </div>
                    </footer>
                </form>
            </div>
        </section>
    </section>
    <?php
}

function nsz_design_video_field_menu_item() {
    add_options_page("Cloudflare Stream ACF Field Settings", "Cloudflare Stream ACF Field Settings", "manage_options", "nsz_design_video_field_settings", "nsz_design_video_field_settings_page");
}

add_action("admin_menu", "nsz_design_video_field_menu_item");