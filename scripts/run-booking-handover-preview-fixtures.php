<?php
/**
 * BookingPayload v2 Handover Preview Fixture Runner
 *
 * Terminal-only script. Loads v2 fixtures, normalises each, validates,
 * builds a dry-run handover envelope, and asserts envelope structure.
 *
 * Usage: php scripts/run-booking-handover-preview-fixtures.php
 * Exit codes: 0 = all pass, 1 = any fail
 *
 * Constraints: no WordPress bootstrap, no database, no tokens, no booking-site calls.
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        if ( ! is_string( $str ) ) { $str = (string) $str; }
        $str = str_replace( "\0", '', $str );
        $str = wp_strip_all_tags( $str );
        return trim( $str );
    }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $string, $remove_breaks = false ) {
        $string = preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', $string );
        $string = strip_tags( $string );
        if ( $remove_breaks ) { $string = preg_replace( '/[\r\n\t ]+/', ' ', $string ); }
        return trim( $string );
    }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        $key = strtolower( $key );
        $key = preg_replace( '/[^a-z0-9_\-]/', '', $key );
        return $key;
    }
}
if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        if ( ! is_string( $email ) ) { return ''; }
        $email = preg_replace( '/[^a-zA-Z0-9@_.+\-]/', '', $email );
        return trim( $email );
    }
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook_name, $value, ...$args ) { return $value; }
}
if ( ! function_exists( 'do_action' ) ) {
    function do_action( $hook_name, ...$args ) {}
}
if ( ! function_exists( 'gmdate' ) ) {
    function gmdate( $format, $ts = null ) { return date( $format, $ts ?? time() ); }
}
if ( ! function_exists( 'strtotime' ) ) {
    function strtotime( $time, $now = null ) { return strtotime( $time, $now ?? time() ); }
}
if ( ! function_exists( 'random_bytes' ) ) {
    function random_bytes( $len ) {
        $b = '';
        for ( $i = 0; $i < $len; $i++ ) { $b .= chr( random_int( 0, 255 ) ); }
        return $b;
    }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $val, $opts = 0, $depth = 512 ) {
        return json_encode( $val, $opts | JSON_UNESCAPED_UNICODE, $depth );
    }
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( $nonce, $action = -1 ) {
        return ( $nonce !== null && $nonce !== '' ) ? 1 : 0;
    }
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public $data = array();
        protected $status = 200;
        protected $headers = array();
        public function __construct( $data = null, $status = 200, $headers = array() ) {
            $this->data = is_array( $data ) ? $data : array();
            $this->status = (int) $status;
            $this->headers = (array) $headers;
        }
        public function to_array() : array { return $this->data; }
    }
}

$plugin_root = dirname( __DIR__ );
require_once $plugin_root . '/inc/class-booking-payload-v2-normalizer.php';
require_once $plugin_root . '/inc/class-booking-payload-v2-validator.php';
require_once $plugin_root . '/inc/class-booking-payload-v2-handover-service.php';
require_once $plugin_root . '/inc/booking-client-config.php';

$fixture_file = $plugin_root . '/tests/fixtures/booking-payload-v2-fixtures.json';
if ( ! file_exists( $fixture_file ) ) {
    fwrite( STDERR, "ERROR: Fixture file not found at {$fixture_file}\n" );
    exit( 1 );
}
$fixtures_json = file_get_contents( $fixture_file );
if ( false === $fixtures_json ) {
    fwrite( STDERR, "ERROR: Could not read fixture file.\n" );
    exit( 1 );
}
$fixtures = json_decode( $fixtures_json, true );
if ( ! is_array( $fixtures ) ) {
    fwrite( STDERR, "ERROR: Fixture file is not valid JSON.\n" );
    exit( 1 );
}

$normalizer      = new \WSB_Client_Booking_Payload_V2_Normalizer();
$validator       = new \WSB_Client_Booking_Payload_V2_Validator();
// Terminal/dev runner always uses the local development fallback secret.
// DO NOT use this value outside of local development.
$handover_secret = defined( 'WP_DEBUG' ) && WP_DEBUG
    ? 'local_v2_handover_preview_secret'
    : ( wsb_client_v2_handover_secret() ?: 'local_v2_handover_preview_secret' );
$handover_service = new \WSB_Client_Booking_Payload_V2_Handover_Service( $handover_secret );

function canonical_sort_keys( $value ) {
    if ( is_array( $value ) ) {
        if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
            ksort( $value, SORT_STRING );
            return array_map( 'canonical_sort_keys', $value );
        }
        return array_map( 'canonical_sort_keys', $value );
    }
    return $value;
}

function assert_handover_envelope( array $envelope, string $fixture_id ) : array {
    $failures = array();
    if ( empty( $envelope ) ) {
        $failures[] = "[{$fixture_id}] envelope is empty";
        return $failures;
    }
    $sorted = canonical_sort_keys( $envelope );

    $checks = array(
        'handover_version'           => '2.0',
        'schema_version'             => '2.0',
        'mode'                       => 'dry_run',
        'source_site'                => 'marketing',
        'target_site'                => 'booking',
        'meta.preview_only'          => true,
        'meta.real_handover_enabled' => false,
    );

    foreach ( $checks as $path => $expected ) {
        $keys   = explode( '.', $path );
        $actual = $sorted;
        foreach ( $keys as $k ) {
            if ( ! is_array( $actual ) || ! array_key_exists( $k, $actual ) ) { $actual = null; break; }
            $actual = $actual[ $k ];
        }
        if ( $actual !== $expected ) {
            $failures[] = sprintf( '[%s] %s: expected %s, got %s', $fixture_id, $path, var_export( $expected, true ), var_export( $actual, true ) );
        }
    }

    if ( empty( $sorted['integrity']['signature'] ?? null ) ) {
        $failures[] = "[{$fixture_id}] integrity.signature is empty";
    }
    if ( empty( $sorted['payload'] ?? null ) ) {
        $failures[] = "[{$fixture_id}] payload is missing or empty";
    }
    if ( ! in_array( 'hash_hmac_sha256', (array) ( $sorted['integrity']['algorithm'] ?? '' ), true ) ) {
        $failures[] = "[{$fixture_id}] integrity.algorithm is not hash_hmac_sha256";
    }
    $required_fields = array( 'handover_version', 'schema_version', 'action', 'request_id', 'created_at', 'expires_at', 'payload' );
    $signed = (array) ( $sorted['integrity']['signed_fields'] ?? array() );
    foreach ( $required_fields as $field ) {
        if ( ! in_array( $field, $signed, true ) ) {
            $failures[] = "[{$fixture_id}] signed_fields missing: {$field}";
        }
    }
    return $failures;
}

$total    = count( $fixtures );
$passed   = 0; $failed = 0; $skipped = 0; $failures_list = array();

echo "\n=== BookingPayload v2 Handover Preview Fixture Runner ===\n";
echo "Valid fixtures only: expected_ok === true\n";
echo "Total fixtures: {$total}\n\n";

foreach ( $fixtures as $i => $fixture ) {
    $id          = $fixture['id'] ?? "fixture-{$i}";
    $description = $fixture['description'] ?? '';
    $expected_ok = (bool) ( $fixture['expected_ok'] ?? false );
    $raw_payload = $fixture['payload'] ?? array();

    if ( ! $expected_ok ) {
        $skipped++;
        echo "  - [SKIP] {$id} (expected_ok=false, intentionally invalid)\n";
        if ( $description ) { echo "    Description: {$description}\n"; }
        echo "\n";
        continue;
    }

    $normalized = $normalizer->normalize( $raw_payload );
    $validation = $validator->validate( $normalized );
    $actual_ok  = ! empty( $validation['valid'] );

    if ( empty( $actual_ok ) ) {
        $failed++;
        $failures_list[] = array( 'id' => $id, 'error' => 'payload failed validation before envelope build', 'errors' => $validation['errors'] ?? array() );
        echo "  ✗ [FAIL] {$id}\n    Payload failed normalization validation.\n";
        foreach ( $validation['errors'] ?? array() as $err ) {
            printf( "      - [%s] %s: %s\n", $err['field'] ?? '?', $err['code'] ?? '?', $err['message'] ?? '?' );
        }
        echo "\n";
        continue;
    }

    $envelope = $handover_service->build_envelope( $normalized, 'fixture' );
    $assert_fails = assert_handover_envelope( $envelope, $id );

    if ( empty( $assert_fails ) ) {
        $passed++; $symbol = '✓'; $st = 'PASS';
    } else {
        $failed++;
        $failures_list[] = array( 'id' => $id, 'error' => 'handover envelope assertion failed', 'failures' => $assert_fails );
        $symbol = '✗'; $st = 'FAIL';
    }

    echo "  {$symbol} [{$st}] {$id}\n";
    if ( $description ) { echo "    Description: {$description}\n"; }
    foreach ( $assert_fails ?? array() as $msg ) { echo "    {$msg}\n"; }
    echo "\n";
}

echo "=== Results ===\n";
printf( "  Total fixtures : %d\n", $total );
printf( "  Passed         : %d\n", $passed );
printf( "  Failed         : %d\n", $failed );
printf( "  Skipped        : %d\n\n", $skipped );

if ( $failed > 0 ) {
    echo "=== Failed fixtures ===\n";
    foreach ( $failures_list as $f ) {
        printf( "  %s\n", $f['id'] );
        if ( ! empty( $f['error'] ) ) { echo "    Error: {$f['error']}\n"; }
        foreach ( $f['failures'] ?? array() as $msg ) { echo "    {$msg}\n"; }
        if ( ! empty( $f['errors'] ) ) {
            echo "    Validation errors:\n";
            foreach ( $f['errors'] as $err ) {
                printf( "      - [%s] %s: %s\n", $err['field'] ?? '?', $err['code'] ?? '?', $err['message'] ?? '?' );
            }
        }
        echo "\n";
    }
    exit( 1 );
}

echo "All expected-valid fixtures produced valid dry-run handover envelopes.\n";
exit( 0 );
