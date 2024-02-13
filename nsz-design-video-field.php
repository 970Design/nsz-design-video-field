<?php

/**
 * NSZ Design Video Field
 *
 * Plugin Name: 970 Design Video Field
 * Description: An Advanced Custom Fields (ACF) Field for Cloudflare Stream.
 * Version:     1.2
 * Author:      970 Design
 * Author URI:  https://970design.com/
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: nsz-events
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2, as published by the Free Software Foundation. You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

global $nsz_cloudflare_stream_field_db_version;
$nsz_cloudflare_stream_field_db_version = '1.0';

require_once 'nsz-design-video-field-admin.php';

include_once __DIR__.'/acf-cloudflare-stream/init.php';


add_filter( 'plugin_action_links_nsz-design-video-field/nsz-design-video-field.php', 'nsz_design_video_field_settings_link' );
function nsz_design_video_field_settings_link( $links ) {
    $url = esc_url( add_query_arg(
        'page',
        'nsz_design_video_field_settings',
        get_admin_url() . 'options-general.php'
    ) );

    $settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';

    array_push(
        $links,
        $settings_link
    );
    return $links;
}

//create an ajax route to acquire a cloudflare upload url without exposing API
//currently doesn't work; will keep returning new URLs to upload when called in recursion by tus, so it can never start
add_action('rest_api_init', 'nsz_cloudflare_stream_url_route');
function nsz_cloudflare_stream_url_route()
{
    register_rest_route('nsz-cloudflare-stream', 'url', array(
        'methods' => 'POST',
        'callback' => 'nsz_cloudflare_stream_url_ajax',
        'permission_callback' => '__return_true'
    ));
}
function nsz_cloudflare_stream_url_ajax()
{
    $result = false;
    $headers = array();
    $api_token = get_option('nsz_cfstream_api_key');
    $account_id = get_option('nsz_cfstream_account_id');
    $account_email = get_option('nsz_cfstream_account_email');
    $size = filter_var(($_GET['size'] ?? 0), FILTER_VALIDATE_INT);
    $url = 'https://api.cloudflare.com/client/v4/accounts/'.$account_id.'/stream?direct_user=true';

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    //parse headers into array to get location for redirect
    curl_setopt($curl, CURLOPT_HEADERFUNCTION,
        function ($curl, $header) use (&$headers) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) // ignore invalid headers
                return $len;

            $headers[strtolower(trim($header[0]))][] = trim($header[1]);

            return $len;
        }
    );
    curl_setopt($curl, CURLOPT_HEADER, 1);

    //set cloudflare required headers for api call
    $headers = array(
        "X-Auth-Email: ".$account_email,
        "X-Auth-Key: ".$api_token,
        "Tus-Resumable: 1.0.0",
        "Upload-Length: ".$size,
    );
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    $data = '{"maxDurationSeconds": 10}';

    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

    $result = curl_exec($curl);
    curl_close($curl);

    header('Access-Control-Expose-Headers: *');
    header('Access-Control-Allow-Headers: *');
    header('Access-Control-Allow-Origin: *');
    header("X-Auth-Email: ".$account_email);
    header("X-Auth-Key: ".$api_token);
    header('Location: '.$headers['location'][0] ?? null);

    //$headers_array = array();
    //$headers_array['Access-Control-Expose-Headers'] = '*';
    //$headers_array['Access-Control-Allow-Headers'] = '*';
    //$headers_array['Access-Control-Allow-Origin'] = '*';
    //$headers_array['Location'] = $headers['location'][0] ?? null;
    //$headers_array['X-Auth-Email'] = $account_email;
    //$headers_array['X-Auth-Key'] = $api_token;
    //$headers_array['Authorization'] = 'bearer '.$api_token;

    //return new \WP_REST_Response(null, 200, $headers_array);

    //rest_ensure_response();

    //return $headers['location'][0] ?? null;

    exit();
}