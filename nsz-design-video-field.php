<?php

/**
 * NSZ Design Video Field
 *
 * Plugin Name: 970 Design Video Field
 * Description: An Advanced Custom Fields (ACF) Field for Cloudflare Stream.
 * Version:     1.42
 * Author:      970Design
 * Author URI:  https://970design.com/
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: nsz-video-field
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

/*
require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/970Design/nsz-vercel-dashboard',
    __FILE__, //Full path to the main plugin file or functions.php.
    'nsz-vercel-dashboard'
);
*/