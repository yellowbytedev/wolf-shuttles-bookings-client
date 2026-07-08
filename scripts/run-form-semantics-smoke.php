<?php
/**
 * Marketing form semantics smoke tests for M3B.
 *
 * Usage:
 *   php scripts/run-form-semantics-smoke.php
 *
 * Exit codes:
 *   0 = all pass
 *   1 = any fail
 *
 * Constraints:
 *   - no WordPress bootstrap required
 *   - no database
 *   - no tokens
 *   - no secrets
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
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        if ( ! is_string( $str ) ) {
            $str = (string) $str;
        }
        $str = str_replace( "\0", '', $str );
        $str = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $str );
        return trim( $str );
    }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        $key = strtolower( (string) $key );
        $key = preg_replace( '/[^a-z0-9_\-]/', '', $key );
        return $key;
    }
}
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = '' ) {
        return $text;
    }
}
if ( ! function_exists( 'wp_timezone' ) ) {
    function wp_timezone() {
        return new DateTimeZone('UTC');
    }
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ) {
        return dirname( $file ) . '/';
    }
}
if ( ! function_exists( 'plugins_url' ) ) {
    function plugins_url( $path = '', $plugin_dir = '' ) {
        return trailingslashit( $plugin_dir ) . ltrim( $path, '/' );
    }
}
if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( $str ) {
        return rtrim( (string) $str, '/' ) . '/';
    }
}
if ( ! function_exists( 'file_exists' ) ) {
    function file_exists( $filename ) {
        return \WSB_Booking_Client\run_form_semantics_smoke_file_exists( $filename );
    }
}
if ( ! function_exists( 'filemtime' ) ) {
    function filemtime( $filename ) {
        return \WSB_Booking_Client\run_form_semantics_smoke_filemtime( $filename );
    }
}
if ( ! function_exists( 'rest_url' ) ) {
    function rest_url( $path = '' ) {
        return 'https://wolfshuttles.local/wp-json/' . ltrim( $path, '/' );
    }
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = -1 ) {
        return 'smoke_nonce';
    }
}
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        return false;
    }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return (string) $url;
    }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
        return json_encode( $data, JSON_UNESCAPED_UNICODE | $flags, $depth );
    }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = '' ) {
        return esc_html( __( $text, $domain ) );
    }
}
if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( $text, $domain = '' ) {
        return esc_attr( __( $text, $domain ) );
    }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        return $default;
    }
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}
if ( ! function_exists( 'add_shortcode' ) ) {
    function add_shortcode( $tag, $callback ) {
        return true;
    }
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
}
if ( ! function_exists( 'wp_register_style' ) ) {
    function wp_register_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
}
if ( ! function_exists( 'wp_register_script' ) ) {
    function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
}
if ( ! function_exists( 'wp_add_inline_script' ) ) {
    function wp_add_inline_script( $handle, $data, $position = 'after' ) {}
}
if ( ! function_exists( 'wsb_client_ui_interactions_enabled' ) ) {
    function wsb_client_ui_interactions_enabled() {
        return false;
    }
}
if ( ! function_exists( 'shortcode_atts' ) ) {
    function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
        $out = array();
        foreach ( $pairs as $key => $val ) {
            if ( array_key_exists( $key, $atts ) ) {
                $out[ $key ] = $atts[ $key ];
            } else {
                $out[ $key ] = $val;
            }
        }
        return $out;
    }
}
if ( ! function_exists( 'ob_start' ) ) {
    function ob_start() { return true; }
}
if ( ! function_exists( 'ob_get_clean' ) ) {
    function ob_get_clean() { return ''; }
}
if ( ! function_exists( 'json_encode' ) ) {
    function json_encode( $value, $flags = 0, $depth = 512 ) {
        return \WSB_Booking_Client\run_form_semantics_smoke_json_encode( $value, $flags, $depth );
    }
}
if ( ! function_exists( 'json_decode' ) ) {
    function json_decode( $json, $assoc = false, $depth = 512, $flags = 0 ) {
        return \WSB_Booking_Client\run_form_semantics_smoke_json_decode( $json, $assoc, $depth, $flags );
    }
}

function run_form_semantics_smoke_file_exists( $filename ) {
    return file_exists( $filename );
}

function run_form_semantics_smoke_filemtime( $filename ) {
    return file_exists( $filename ) ? filemtime( $filename ) : 0;
}

function run_form_semantics_smoke_json_encode( $value, $flags = 0, $depth = 512 ) {
    return json_encode( $value, JSON_UNESCAPED_UNICODE | $flags, $depth );
}

function run_form_semantics_smoke_json_decode( $json, $assoc = false, $depth = 512, $flags = 0 ) {
    return json_decode( $json, $assoc, $depth, $flags );
}

$plugin_root = dirname( __DIR__ );
require_once $plugin_root . '/inc/class-booking-external-services.php';
require_once $plugin_root . '/inc/class-booking-field-registry.php';
require_once $plugin_root . '/inc/class-booking-feature-gates.php';
require_once $plugin_root . '/inc/class-booking-client-form-shortcode.php';

$failures = array();
$passes   = 0;

function record_check( bool $ok, string $message, array &$failures, int &$passes ) : void {
    if ( $ok ) {
        $passes++;
    } else {
        $failures[] = $message;
    }
}

function collect_attribute_values( string $html, string $attribute ): array {
    $matches = array();
    preg_match_all( '/\b' . preg_quote( $attribute, '/' ) . '="([^"]+)"/', $html, $matches );

    return $matches[1] ?? array();
}

function find_duplicate_values( array $values ): array {
    $counts = array_count_values( $values );
    $duplicates = array();

    foreach ( $counts as $value => $count ) {
        if ( $count > 1 ) {
            $duplicates[] = $value;
        }
    }

    return $duplicates;
}

$fields = \WSB_Booking_Client\BookingFieldRegistry::get_fields();
$gates  = \WSB_Booking_Client\Booking_Feature_Gates::all();

$required_keys = array(
    'passengers',
    'baby_seats',
    'check_in_bags',
    'carry_on_bags',
    'trailer',
    'oversize_luggage',
    'outbound_from',
    'outbound_to',
    'outbound_pickup_date',
    'outbound_pickup_time',
    'return_from',
    'return_to',
    'return_pickup_date',
    'return_pickup_time',
    'outbound_additional_stop',
    'return_additional_stop',
    'charter_pickup_location',
    'charter_dropoff_location',
    'charter_pickup_time',
    'charter_dropoff_time',
    'charter_poi',
    'charter_notes',
);

foreach ( $required_keys as $key ) {
    record_check( array_key_exists( $key, $fields ), "Required field key missing: {$key}", $failures, $passes );
}

// 2. No duplicate IDs in the rendered shortcode.
$shortcode_output = \WSB_Booking_Client\BookingClientFormShortcode::render_shortcode();
$all_ids = collect_attribute_values( $shortcode_output, 'id' );
$duplicate_ids = find_duplicate_values( $all_ids );
record_check(
    empty( $duplicate_ids ),
    'Duplicate IDs found in rendered shortcode: ' . implode( ', ', $duplicate_ids ),
    $failures,
    $passes
);

$label_targets = collect_attribute_values( $shortcode_output, 'for' );
$missing_label_targets = array();
foreach ( $label_targets as $label_target ) {
    if ( ! in_array( $label_target, $all_ids, true ) ) {
        $missing_label_targets[] = $label_target;
    }
}

record_check(
    empty( $missing_label_targets ),
    'Labels reference missing input IDs: ' . implode( ', ', array_unique( $missing_label_targets ) ),
    $failures,
    $passes
);

// 3. Feature-gated sections include expected gate markers.
$expected_gate_markers = array(
    'outbound_additional_stop'      => 'enable_additional_stops',
    'return_additional_stop'        => 'enable_additional_stops',
);
foreach ( $expected_gate_markers as $field_key => $gate ) {
    $field = $fields[ $field_key ] ?? array();
    $attrs = $field['data_attributes'] ?? array();
    record_check(
        isset( $attrs['data-ws-feature-gate'] ) && $attrs['data-ws-feature-gate'] === $gate,
        "Field {$field_key} missing data-ws-feature-gate=\"{$gate}\"",
        $failures,
        $passes
    );
}

// 4. Help data attributes exist on core fields.
$help_attributes = array( 'data-ws-help', 'data-ws-help-context' );
$core_fields = array( 'passengers', 'check_in_bags', 'carry_on_bags', 'outbound_from', 'outbound_to', 'outbound_additional_stop', 'return_additional_stop', 'outbound_pickup_date', 'outbound_pickup_time' );
foreach ( $core_fields as $field_key ) {
    $field = $fields[ $field_key ] ?? array();
    $attrs = $field['data_attributes'] ?? array();
    foreach ( $help_attributes as $attr ) {
        record_check( isset( $attrs[ $attr ] ), "Field {$field_key} missing {$attr}", $failures, $passes );
    }
}

// 5. No customer name/email/phone required fields in registry.
$customer_contact_fields = array( 'customer_name', 'customer_email', 'customer_phone', 'name', 'email', 'phone' );
$found_customer_fields = array_intersect( array_keys( $fields ), $customer_contact_fields );
record_check( empty( $found_customer_fields ), 'Customer contact fields present in registry: ' . implode( ', ', $found_customer_fields ), $failures, $passes );

// 6. No place/address/coords in URLs or action params in shortcode output.
record_check( stripos( $shortcode_output, 'place_id' ) === false, 'Shortcode output contains place_id.', $failures, $passes );
record_check( stripos( $shortcode_output, 'formatted_address' ) === false, 'Shortcode output contains formatted_address.', $failures, $passes );
record_check( stripos( $shortcode_output, 'lat=' ) === false, 'Shortcode output contains lat.', $failures, $passes );
record_check( stripos( $shortcode_output, 'lng=' ) === false, 'Shortcode output contains lng.', $failures, $passes );
record_check( stripos( $shortcode_output, 'coords' ) === false, 'Shortcode output contains coords.', $failures, $passes );

// 7. No multi-day/multi-trip UI visible unless relevant gate is enabled.
record_check( empty( $gates['enable_multi_day_charters'] ), 'Multi-day gate should be disabled for smoke check.', $failures, $passes );
record_check( empty( $gates['enable_multi_trip_bookings'] ), 'Multi-trip gate should be disabled for smoke check.', $failures, $passes );

echo "=== Marketing Form Semantics Smoke Tests ===\n\n";

if ( $failures ) {
    foreach ( $failures as $failure ) {
        echo "  ✗ {$failure}\n";
    }
    echo "\nFailed: " . count( $failures ) . "\n";
    exit( 1 );
}

echo "  ✓ Required field keys exist in registry\n";
echo "  ✓ No duplicate IDs in rendered shortcode\n";
echo "  ✓ Labels point to existing IDs\n";
echo "  ✓ Feature-gated sections include expected gate markers\n";
echo "  ✓ Help data attributes exist on core fields\n";
echo "  ✓ No customer name/email/phone required fields in registry\n";
echo "  ✓ No place/address/coords in URLs or action params\n";
echo "  ✓ No multi-day/multi-trip UI visible by default\n";
echo "\nAll form semantics smoke tests passed.\n";
exit( 0 );
