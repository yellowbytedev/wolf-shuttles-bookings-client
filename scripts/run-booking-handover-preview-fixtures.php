<?php
/**
 * BookingPayload v2 Handover Preview Fixture Runner
 *
 * DEPRECATED: Use run-booking-handover-fixtures.php instead.
 * This file is kept for backward compatibility.
 *
 * @deprecated 1.1.0
 * @see run-booking-handover-fixtures.php
 */
if ( file_exists( dirname( __DIR__ ) . '/scripts/run-booking-handover-fixtures.php' ) ) {
    require_once dirname( __DIR__ ) . '/scripts/run-booking-handover-fixtures.php';
} else {
    // Fallback stubs for standalone execution
    if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', dirname( __DIR__ ) . '/' );
    }

    // Stub functions
    if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $str ) { return $str; } }
    if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $key ) { return $key; } }
    if ( ! function_exists( 'sanitize_email' ) ) { function sanitize_email( $email ) { return $email; } }
    if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $hook_name, $value, ...$args ) { return $value; } }
    if ( ! function_exists( 'gmdate' ) ) { function gmdate( $format, $ts = null ) { return date( $format, $ts ?? time() ); } }
    if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $val, $opts = 0 ) { return json_encode( $val, $opts | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); } }
    if ( ! function_exists( 'random_bytes' ) ) { function random_bytes( $len ) { $b = ''; for ( $i = 0; $i < $len; $i++ ) { $b .= chr( random_int( 0, 255 ) ); } return $b; } }

    // Stub classes
    if ( ! class_exists( 'WP_REST_Response' ) ) {
        class WP_REST_Response { public $data; protected $status; protected $headers; public function __construct( $data = null, $status = 200, $headers = [] ) { $this->data = $data ?? []; $this->status = $status; $this->headers = $headers; } public function to_array() { return $this->data; } }
    }

    echo "\n[DEPRECATED] Use run-booking-handover-fixtures.php instead.\n";
    exit( 1 );
}