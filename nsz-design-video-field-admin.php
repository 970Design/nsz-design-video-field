<?php

function nsz_cloudflare_stream_field_settings_page()
{

    //must check that the user has the required capability
    if (!current_user_can('manage_options'))
    {
        wp_die( __('You do not have sufficient permissions to access this page.') );
    }

    //variables for the field and option names
    $nsz_cfstream_api_hidden = 'nsz_cfstream_api_hidden';
    $nsz_cfstream_api_field = 'nsz_cfstream_api_key';
    $nsz_cfstream_api_value = filter_var(($_POST[$nsz_cfstream_api_field] ?? null), FILTER_SANITIZE_STRING);
    $nsz_cfstream_account_id_hidden = 'nsz_cfstream_account_id_hidden';
    $nsz_cfstream_account_id_field = 'nsz_cfstream_account_id';
    $nsz_cfstream_account_id_value = filter_var(($_POST[$nsz_cfstream_account_id_field] ?? null), FILTER_SANITIZE_STRING);
    $nsz_cfstream_account_email_hidden = 'nsz_cfstream_account_email_hidden';
    $nsz_cfstream_account_email_field = 'nsz_cfstream_account_email';
    $nsz_cfstream_account_email_value = filter_var(($_POST[$nsz_cfstream_account_email_field] ?? null), FILTER_SANITIZE_EMAIL);

    //save api key
    if (isset($_POST[$nsz_cfstream_api_hidden]) && $_POST[$nsz_cfstream_api_hidden] == 'Y') {
        if ($nsz_cfstream_api_value) {
            update_option($nsz_cfstream_api_field, $nsz_cfstream_api_value);
            echo $nsz_cfstream_api_value;
        }
        ?>
        <div class="updated"><p><strong>API Token Updated</strong></p></div>
        <?php
    }

    //save account id
    if (isset($_POST[$nsz_cfstream_account_id_hidden]) && $_POST[$nsz_cfstream_account_id_hidden] == 'Y') {
        if ($nsz_cfstream_account_id_value) {
            update_option($nsz_cfstream_account_id_field, $nsz_cfstream_account_id_value);
        }
        ?>
        <div class="updated"><p><strong>Account ID Updated</strong></p></div>
        <?php
    }

    //save account email
    if (isset($_POST[$nsz_cfstream_account_email_hidden]) && $_POST[$nsz_cfstream_account_email_hidden] == 'Y') {
        if ($nsz_cfstream_account_email_value) {
            update_option($nsz_cfstream_account_email_field, $nsz_cfstream_account_email_value);
        }
        ?>
        <div class="updated"><p><strong>Account Email Updated</strong></p></div>
        <?php
    }

    $nsz_cfstream_api_value = get_option($nsz_cfstream_api_field) ?: $nsz_cfstream_api_value;
    $nsz_cfstream_account_id_value = get_option($nsz_cfstream_account_id_field) ?: $nsz_cfstream_account_id_value;
    $nsz_cfstream_account_email_value = get_option($nsz_cfstream_account_email_field) ?: $nsz_cfstream_account_email_value;


    ?>
    <div class="wrap">
        <h1>Cloudflare Stream ACF Field Settings</h1>

        <form name="form-nsz-cloudflare-stream-api-token" method="post" action="">
            <h2>API Token</h2>
            <p><label>API Token:</label>
                <input type="text" name="<?php echo $nsz_cfstream_api_field; ?>" value="<?php echo $nsz_cfstream_api_value ?? ''; ?>" size="35">
            </p>
            <input type="hidden" name="<?php echo $nsz_cfstream_api_hidden; ?>" value="Y">
            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="Update API Token" />
            </p>
        </form>

        <hr />

        <form name="form-nsz-cloudflare-stream-account-id" method="post" action="">
            <h2>Account ID</h2>
            <p><label>Account ID:</label>
                <input type="text" name="<?php echo $nsz_cfstream_account_id_field; ?>" value="<?php echo $nsz_cfstream_account_id_value ?? ''; ?>" size="35">
            </p>
            <input type="hidden" name="<?php echo $nsz_cfstream_account_id_hidden; ?>" value="Y">
            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="Update Account ID" />
            </p>
        </form>

        <hr />

        <form name="form-nsz-cloudflare-stream-account-email" method="post" action="">
            <h2>Account Email</h2>
            <p><label>Account Email:</label>
                <input type="email" name="<?php echo $nsz_cfstream_account_email_field; ?>" value="<?php echo $nsz_cfstream_account_email_value ?? ''; ?>" size="35">
            </p>
            <input type="hidden" name="<?php echo $nsz_cfstream_account_email_hidden; ?>" value="Y">
            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="Update Account Email" />
            </p>
        </form>
    </div>

    <?php
}

function nsz_cloudflare_stream_field_menu_item()
{
    add_options_page("Cloudflare Stream ACF Field Settings", "Cloudflare Stream ACF Field Settings", "manage_options", "nsz_cloudflare_stream_field_settings", "nsz_cloudflare_stream_field_settings_page");
}

add_action("admin_menu", "nsz_cloudflare_stream_field_menu_item");