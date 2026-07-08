<?php
/**
 * BookingPayload v2 Fixture Runner
 *
 * Terminal-only script that loads BookingPayload v2 fixtures,
 * normalises each, validates through the actual normalizer/validator,
 * and reports pass/fail against expected_ok.
 *
 * Usage: php scripts/run-booking-payload-fixtures.php
 *
 * Exit codes:
 *   0 — all fixtures pass (match expected_ok)
 *   1 — one or more fixtures fail
 *
 * Note: This runner does NOT bootstrap WordPress. It includes the
 * normalizer/validator classes directly after providing minimal
 * polyfills for the sanitization functions they need. This avoids
 * the database connection requirement from the CLI.
 */

// ---- Polyfills for WordPress functions used by normalizer/validator ----
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        if ( ! is_string( $str ) ) {
            $str = (string) $str;
        }
        // Strip null bytes, strip tags, trim
        $str = str_replace( "\0", '', $str );
        $str = wp_strip_all_tags( $str );
        return trim( $str );
    }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $string, $remove_breaks = false ) {
        $string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
        $string = strip_tags( $string );
        if ( $remove_breaks ) {
            $string = preg_replace( '/[\r\n\t ]+/', ' ', $string );
        }
        return trim( $string );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        $key          = strtolower( $key );
        $key          = preg_replace( '/[^a-z0-9_\-]/', '', $key );
        return $key;
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        if ( ! is_string( $email ) ) {
            return '';
        }
        // Simple sanitize: remove chars not valid in email
        $email = preg_replace( '/[^a-zA-Z0-9@_.+\-]/', '', $email );
        return trim( $email );
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function wsb_client_fixture_gate_mode(): string {
        $mode = getenv( 'WSB_CLIENT_FIXTURE_GATE_MODE' );

        if ( false === $mode || '' === trim( (string) $mode ) ) {
            $mode = 'enforced';
        }

        return strtolower( trim( (string) $mode ) );
    }

    function wsb_client_fixture_gate_overrides(): array {
        $mode = wsb_client_fixture_gate_mode();

        if ( in_array( $mode, array( 'enforced', 'production' ), true ) ) {
            return array(
                'enable_google_places_required'              => true,
                'enable_debug_free_text_locations_local_only' => false,
            );
        }

        if ( in_array( $mode, array( 'local', 'development', 'dev' ), true ) ) {
            return array(
                'enable_google_places_required'              => false,
                'enable_debug_free_text_locations_local_only' => true,
            );
        }

        return array();
    }

    function apply_filters( $hook_name, $value, ...$args ) {
        if ( 'ws_bookings_client_feature_gates' === $hook_name ) {
            $overrides = wsb_client_fixture_gate_overrides();

            if ( is_array( $value ) && $overrides ) {
                foreach ( $overrides as $gate => $gate_value ) {
                    if ( array_key_exists( $gate, $value ) ) {
                        $value[ $gate ] = (bool) $gate_value;
                    }
                }
            }
        }

        return $value;
    }
}

if ( ! function_exists( 'gmdate' ) ) {
    function gmdate( $format, $timestamp = null ) {
        return date( $format, $timestamp ?? time() );
    }
}

if ( ! function_exists( 'wp_timezone' ) ) {
    function wp_timezone() {
        // Return UTC timezone as fallback
        return new DateTimeZone('UTC');
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = '' ) {
        return $text;
    }
}

// ---- Load external services (with config scaffold) ----
require_once dirname( __DIR__ ) . '/inc/class-booking-external-services.php';
require_once dirname( __DIR__ ) . '/inc/class-booking-feature-gates.php';

// ---- Load plugin classes ----
$plugin_root = dirname( __DIR__ );
require_once $plugin_root . '/inc/class-booking-payload-v2-normalizer.php';
require_once $plugin_root . '/inc/class-booking-payload-v2-validator.php';

// ---- Load fixtures ----
$fixture_file = $plugin_root . '/tests/fixtures/booking-payload-v2-fixtures.json';
if ( ! file_exists( $fixture_file ) ) {
    fprintf( STDERR, "ERROR: Fixture file not found at %s\n", $fixture_file );
    exit( 1 );
}

$fixtures_json = file_get_contents( $fixture_file );
if ( false === $fixtures_json ) {
    fprintf( STDERR, "ERROR: Could not read fixture file.\n" );
    exit( 1 );
}

$fixtures = json_decode( $fixtures_json, true );
if ( ! is_array( $fixtures ) ) {
    fprintf( STDERR, "ERROR: Fixture file is not valid JSON.\n" );
    exit( 1 );
}

// ---- Instantiate normalizer & validator ----
$normalizer = new WSB_Client_Booking_Payload_V2_Normalizer();
$validator  = new WSB_Client_Booking_Payload_V2_Validator();

// ---- Run fixtures ----
$total     = count( $fixtures );
$valid_pass = 0;
$invalid_expected_fail = 0;
$skipped_unsupported = 0;
$failures  = array();
$unexpected_fails = 0;
$unexpected_passes = 0;

$header = sprintf( "\n=== BookingPayload v2 Fixture Runner ===\nTotal fixtures: %d\n\n", $total );
echo $header;
echo 'Gate override mode: ' . wsb_client_fixture_gate_mode() . "\n";
echo "Forced gates: enable_google_places_required=true, enable_debug_free_text_locations_local_only=false\n\n";

foreach ( $fixtures as $i => $fixture ) {
    $id          = $fixture['id'] ?? "fixture-{$i}";
    $description = $fixture['description'] ?? '';
    $expected_ok = (bool) ( $fixture['expected_ok'] ?? false );
    $skip        = (bool) ( $fixture['skip'] ?? false );
    $raw_payload = $fixture['payload'] ?? array();

    // Skip unsupported fixtures
    if ( $skip ) {
        $skipped_unsupported++;
        echo "  [SKIP] {$id}\n";
        if ( $description ) {
            printf( "    Description: %s\n", $description );
        }
        if ( ! empty( $fixture['skip_reason'] ) ) {
            printf( "    Skip reason: %s\n", $fixture['skip_reason'] );
        }
        echo "\n";
        continue;
    }

    // Normalize
    $normalized = $normalizer->normalize( $raw_payload );

    // Validate
    $validation = $validator->validate( $normalized );
    $actual_ok  = ! empty( $validation['valid'] );

    if ( $expected_ok && $actual_ok ) {
        $valid_pass++;
        $status = 'PASS';
        $symbol = '✓';
    } elseif ( ! $expected_ok && ! $actual_ok ) {
        $invalid_expected_fail++;
        $status = 'EXPECTED_FAIL';
        $symbol = '✓';
    } elseif ( $expected_ok && ! $actual_ok ) {
        $unexpected_fails++;
        $status = 'UNEXPECTED_FAIL';
        $symbol = '✗';
        $failures[] = array(
            'id'          => $id,
            'expected_ok' => $expected_ok,
            'actual_ok'   => $actual_ok,
            'errors'      => $validation['errors'] ?? array(),
        );
    } else {
        $unexpected_passes++;
        $status = 'UNEXPECTED_PASS';
        $symbol = '✗';
        $failures[] = array(
            'id'          => $id,
            'expected_ok' => $expected_ok,
            'actual_ok'   => $actual_ok,
            'errors'      => $validation['errors'] ?? array(),
        );
    }

    printf( "  %s [%s] %s\n", $symbol, $status, $id );
    if ( $description ) {
        printf( "    Description: %s\n", $description );
    }

    $error_count = count( $validation['errors'] ?? array() );
    $warn_count  = count( $validation['warnings'] ?? array() );

    printf(
        "    Expected OK: %-5s  Actual OK: %-5s  Errors: %d  Warnings: %d\n",
        $expected_ok ? 'true' : 'false',
        $actual_ok ? 'true' : 'false',
        $error_count,
        $warn_count
    );

    if ( ( $expected_ok && ! $actual_ok ) || ( ! $expected_ok && $actual_ok ) ) {
        $error_label = $actual_ok ? 'unexpected pass' : 'unexpected fail';
        printf( "    Result: %s\n", $error_label );
    }

    if ( ( $expected_ok && ! $actual_ok ) || ( ! $expected_ok && $actual_ok ) ) {
        foreach ( $validation['errors'] ?? array() as $err ) {
            printf( "      - [%s] %s: %s\n", $err['field'] ?? '?', $err['code'] ?? '?', $err['message'] ?? '?' );
        }
    }

    echo "\n";
}

// ---- Summary ----
echo "=== Results ===\n";
printf( "  total: %d\n", $total );
printf( "  valid_pass: %d\n", $valid_pass );
printf( "  invalid_expected_fail: %d\n", $invalid_expected_fail );
printf( "  skipped_unsupported: %d\n", $skipped_unsupported );
printf( "  unexpected_fail: %d\n", $unexpected_fails );
printf( "  unexpected_pass: %d\n", $unexpected_passes );

if ( $unexpected_fails > 0 || $unexpected_passes > 0 ) {
    echo "\n=== Unexpected fixtures ===\n";
    foreach ( $failures as $f ) {
        printf( "  %s (expected_ok=%s, actual_ok=%s)\n", $f['id'], $f['expected_ok'] ? 'true' : 'false', $f['actual_ok'] ? 'true' : 'false' );
    }
}

echo "\n";
exit( ( $unexpected_fails > 0 || $unexpected_passes > 0 ) ? 1 : 0 );
