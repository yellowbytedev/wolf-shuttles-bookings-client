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
    function apply_filters( $hook_name, $value, ...$args ) {
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
$passed    = 0;
$failed    = 0;
$failures  = array();

$header = sprintf( "\n=== BookingPayload v2 Fixture Runner ===\nTotal fixtures: %d\n\n", $total );
echo $header;

foreach ( $fixtures as $i => $fixture ) {
    $id          = $fixture['id'] ?? "fixture-{$i}";
    $description = $fixture['description'] ?? '';
    $expected_ok = (bool) ( $fixture['expected_ok'] ?? false );
    $raw_payload = $fixture['payload'] ?? array();

    // Normalize
    $normalized = $normalizer->normalize( $raw_payload );

    // Validate
    $validation = $validator->validate( $normalized );
    $actual_ok  = ! empty( $validation['valid'] );
    $match      = ( $actual_ok === $expected_ok );

    if ( $match ) {
        $passed++;
        $status = 'PASS';
    } else {
        $failed++;
        $status = 'FAIL';
        $failures[] = array(
            'id'          => $id,
            'expected_ok' => $expected_ok,
            'actual_ok'   => $actual_ok,
            'errors'      => $validation['errors'] ?? array(),
        );
    }

    $symbol = $match ? '✓' : '✗';
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

    if ( ! $match && $error_count > 0 ) {
        foreach ( $validation['errors'] as $err ) {
            printf( "      - [%s] %s: %s\n", $err['field'] ?? '?', $err['code'] ?? '?', $err['message'] ?? '?' );
        }
    }

    echo "\n";
}

// ---- Summary ----
echo "=== Results ===\n";
printf( "  Total:  %d\n", $total );
printf( "  Passed: %d\n", $passed );
printf( "  Failed: %d\n", $failed );

if ( $failed > 0 ) {
    echo "\n=== Failed fixtures ===\n";
    foreach ( $failures as $f ) {
        printf( "  %s (expected_ok=%s, actual_ok=%s)\n", $f['id'], $f['expected_ok'] ? 'true' : 'false', $f['actual_ok'] ? 'true' : 'false' );
    }
}

echo "\n";
exit( $failed > 0 ? 1 : 0 );
