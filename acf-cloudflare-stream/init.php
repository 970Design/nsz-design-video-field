<?php
/**
 * Registration logic for the new ACF field type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'nsz_include_acf_field_cloudflare_stream' );
/**
 * Registers the ACF field type.
 */
function nsz_include_acf_field_cloudflare_stream() {
	if ( ! function_exists( 'acf_register_field_type' ) ) {
		return;
	}

	require_once __DIR__ . '/class-nsz-acf-field-cloudflare-stream.php';

	acf_register_field_type( 'nsz_acf_field_cloudflare_stream' );
}
