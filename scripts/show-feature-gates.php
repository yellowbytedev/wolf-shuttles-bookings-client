<?php
/**
 * Marketing feature gate debug report.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/ws-bookings-client/scripts/show-feature-gates.php
 *
 * Outputs:
 *   - environment
 *   - gate key/value list
 *   - no secrets
 *   - no payloads
 *   - no customer/location data
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook_name, $value, ...$args ) {
        return $value;
    }
}
if ( ! function_exists( 'wp_get_environment_type' ) ) {
    function wp_get_environment_type() {
        return defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'production';
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        if ( ! is_string( $str ) ) { $str = (string) $str; }
        $str = str_replace( "\0", '', $str );
        $str = preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', $str );
        return trim( $str );
    }
}

require_once dirname( __DIR__ ) . '/inc/class-booking-feature-gates.php';

if ( ! class_exists( '\WSB_Booking_Client\Booking_Feature_Gates' ) ) {
    fwrite( STDERR, "ERROR: Booking_Feature_Gates not loaded.\n" );
    exit( 1 );
}

$gates      = \WSB_Booking_Client\Booking_Feature_Gates::frontend_config();
$environment = sanitize_text_field( (string) ( $gates['environment'] ?? 'production' ) );
$gates      = is_array( $gates['feature_gates'] ?? null ) ? $gates['feature_gates'] : array();

echo "=== Marketing Feature Gates ===\n";
echo "Environment: " . sanitize_text_field( (string) $environment ) . "\n";
echo "\n";

foreach ( $gates as $key => $value ) {
    echo str_pad( $key, 45 ) . '=> ' . ( $value ? 'true' : 'false' ) . "\n";
}

echo "\nNote: No payloads, secrets, customer data, or location data are displayed by this script.\n";
exit( 0 );
