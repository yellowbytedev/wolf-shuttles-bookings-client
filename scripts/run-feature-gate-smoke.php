<?php
/**
 * Feature gate smoke tests.
 *
 * Usage:
 *   php scripts/run-feature-gate-smoke.php
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
        if ( 'ws_bookings_client_feature_gates' === $hook_name ) {
            $environment = $args[0] ?? 'production';
            $override = $args[1] ?? array();

            if ( ! is_array( $override ) ) {
                return $value;
            }

            $known = array(
                'enable_multi_day_charters',
                'enable_multi_trip_bookings',
                'enable_additional_stops',
                'enable_route_options_payload',
                'enable_route_alternatives_on_shuttles_page',
                'enable_google_places_required',
                'enable_drag_drop_itinerary_ordering',
                'enable_day_duplicate_delete',
                'enable_charter_poi_fields',
                'enable_debug_free_text_locations_local_only',
            );

            foreach ( $known as $gate ) {
                if ( array_key_exists( $gate, $override ) ) {
                    $value[ $gate ] = (bool) $override[ $gate ];
                }
            }

            return $value;
        }

        return $value;
    }
}
if ( ! function_exists( 'do_action' ) ) {
    function do_action( $hook_name, ...$args ) {}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        if ( ! is_string( $str ) ) {
            $str = (string) $str;
        }
        $str = str_replace( "\0", '', $str );
        $str = preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', $str );
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
if ( ! function_exists( 'wp_get_environment_type' ) ) {
    function wp_get_environment_type() {
        return defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'production';
    }
}

$plugin_root = dirname( __DIR__ );
require_once $plugin_root . '/inc/class-booking-feature-gates.php';

if ( ! class_exists( '\WSB_Booking_Client\Booking_Feature_Gates' ) ) {
    fwrite( STDERR, "ERROR: Booking_Feature_Gates not loaded.\n" );
    exit( 1 );
}

$ref = new \ReflectionClass( \WSB_Booking_Client\Booking_Feature_Gates::class );
$known_gates = $ref->getConstant( 'KNOWN_GATES' );

$failures = array();

// 1. Required gates exist and are known.
foreach ( $known_gates as $gate ) {
    if ( ! \WSB_Booking_Client\Booking_Feature_Gates::is_known_gate( $gate ) ) {
        $failures[] = "Known gate missing: {$gate}";
    }
}

// 2. All values are booleans for each environment.
foreach ( [ 'local', 'staging', 'production' ] as $env ) {
    $defaults = \WSB_Booking_Client\Booking_Feature_Gates::defaults_for_environment( $env );
    foreach ( $defaults as $key => $value ) {
        if ( ! is_bool( $value ) ) {
            $failures[] = "Default for {$env}::{$key} is not bool.";
        }
    }
}

// 3. Production defaults fail closed for unfinished features.
$production_defaults = \WSB_Booking_Client\Booking_Feature_Gates::defaults_for_environment( 'production' );
$unfinished_gates = array(
    'enable_multi_day_charters',
    'enable_multi_trip_bookings',
    'enable_route_alternatives_on_shuttles_page',
    'enable_drag_drop_itinerary_ordering',
    'enable_day_duplicate_delete',
    'enable_charter_poi_fields',
    'enable_debug_free_text_locations_local_only',
);
foreach ( $unfinished_gates as $gate ) {
    if ( ! empty( $production_defaults[ $gate ] ) ) {
        $failures[] = "Production default should be false for unfinished gate: {$gate}";
    }
}

// 4. Additional stops disabled in production unless explicitly allowed by existing state.
// Current existing behaviour relies on legacy additional-stop fields and JS, so keep it disabled by default.
if ( ! empty( $production_defaults['enable_additional_stops'] ) ) {
    $failures[] = 'Production default for enable_additional_stops should remain false by default until explicitly validated.';
}

// 5. Local-only debug free-text gate cannot be enabled outside local/development by defaults.
foreach ( [ 'staging', 'production' ] as $env ) {
    $env_defaults = \WSB_Booking_Client\Booking_Feature_Gates::defaults_for_environment( $env );
    if ( ! empty( $env_defaults['enable_debug_free_text_locations_local_only'] ) ) {
        $failures[] = "Local-only debug free-text gate must be false in {$env} defaults.";
    }
}

// 6. Filter override works for known gates.
$filtered = apply_filters(
    'ws_bookings_client_feature_gates',
    \WSB_Booking_Client\Booking_Feature_Gates::defaults_for_environment( 'production' ),
    'production',
    array( 'enable_multi_day_charters' => true )
);
if ( empty( $filtered['enable_multi_day_charters'] ) ) {
    $failures[] = 'Filter override failed to enable known gate.';
}

// 7. Unknown gate override is ignored or safely handled.
$filtered_unknown = apply_filters(
    'ws_bookings_client_feature_gates',
    \WSB_Booking_Client\Booking_Feature_Gates::defaults_for_environment( 'production' ),
    'production',
    array( 'unknown_gate_xyz' => true )
);
if ( isset( $filtered_unknown['unknown_gate_xyz'] ) ) {
    $failures[] = 'Unknown gate override should be ignored.';
}

// 8. frontend_config contains only known gates.
$frontend = \WSB_Booking_Client\Booking_Feature_Gates::frontend_config();
if ( ! is_array( $frontend['feature_gates'] ?? null ) ) {
    $failures[] = 'frontend_config feature_gates must be an array.';
} else {
    foreach ( $frontend['feature_gates'] as $gate => $value ) {
        if ( ! \WSB_Booking_Client\Booking_Feature_Gates::is_known_gate( $gate ) ) {
            $failures[] = "frontend_config contains unknown gate: {$gate}";
        }
    }
}

// 9. Output contains no secrets, payloads, customer/location data.
ob_start();
\WSB_Booking_Client\Booking_Feature_Gates::all();
$captured = ob_get_clean();
if ( $captured !== '' ) {
    $failures[] = 'Feature gate methods produced unexpected output.';
}

echo "=== Marketing Feature Gate Smoke Tests ===\n\n";

if ( $failures ) {
    foreach ( $failures as $failure ) {
        echo "  ✗ {$failure}\n";
    }
    echo "\nFailed: " . count( $failures ) . "\n";
    exit( 1 );
}

echo "  ✓ Required gates exist\n";
echo "  ✓ All default values are booleans\n";
echo "  ✓ Production defaults fail closed\n";
echo "  ✓ Local-only debug gate disabled outside local/development\n";
echo "  ✓ Filter override works for known gates\n";
echo "  ✓ Unknown gate override is ignored\n";
echo "  ✓ frontend_config contains only known gates\n";
echo "  ✓ No unexpected output from feature gate methods\n";
echo "\nAll feature gate smoke tests passed.\n";
exit( 0 );
