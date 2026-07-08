<?php
/**
 * BookingPayload v2 Handover Fixture Runner
 *
 * Terminal-only script. Loads v2 fixtures, normalises each, validates,
 * builds a dry-run handover envelope, and asserts envelope structure.
 *
 * Usage: php scripts/run-booking-handover-fixtures.php
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
if ( ! function_exists( 'wsb_client_fixture_gate_mode' ) ) {
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
                'enable_google_places_required'               => true,
                'enable_debug_free_text_locations_local_only' => false,
            );
        }

        if ( in_array( $mode, array( 'local', 'development', 'dev' ), true ) ) {
            return array(
                'enable_google_places_required'               => false,
                'enable_debug_free_text_locations_local_only' => true,
            );
        }

        return array();
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
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
if ( ! function_exists( 'wp_timezone' ) ) {
    function wp_timezone() {
        return new DateTimeZone('UTC');
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
require_once $plugin_root . '/inc/class-booking-external-services.php';
require_once $plugin_root . '/inc/class-booking-feature-gates.php';
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

$normalizer       = new \WSB_Client_Booking_Payload_V2_Normalizer();
$validator        = new \WSB_Client_Booking_Payload_V2_Validator();
// Terminal/dev runner uses hardcoded local development secret.
// DO NOT use this value outside of local development.
// Note: wp_get_environment_type() is not available outside WordPress bootstrap,
// so the fixture runner must explicitly provide the secret.
$handover_service = new \WSB_Client_Booking_Payload_V2_Handover_Service( 'local_v2_handover_secret' );

function wsb_handover_value_at_path( $payload, array $path, bool &$found = null ) {
    $current = $payload;

    foreach ( $path as $segment ) {
        if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
            $found = false;
            return null;
        }

        $current = $current[ $segment ];
    }

    $found = true;
    return $current;
}

function wsb_handover_assert_place_snapshot( array $snapshot, string $fixture_id, string $path ) : array {
    $failures = array();
    $required_keys = array( 'provider', 'place_id', 'label', 'formatted_address', 'lat', 'lng', 'captured_at', 'stale' );

    foreach ( $required_keys as $key ) {
        if ( ! array_key_exists( $key, $snapshot ) ) {
            $failures[] = sprintf( '[%s] %s missing key: %s', $fixture_id, $path, $key );
        }
    }

    if ( isset( $snapshot['provider'] ) && 'google_places' !== $snapshot['provider'] ) {
        $failures[] = sprintf( '[%s] %s provider must remain google_places', $fixture_id, $path );
    }

    if ( empty( $snapshot['place_id'] ) ) {
        $failures[] = sprintf( '[%s] %s place_id is empty', $fixture_id, $path );
    }

    if ( empty( $snapshot['label'] ) ) {
        $failures[] = sprintf( '[%s] %s label is empty', $fixture_id, $path );
    }

    if ( ! array_key_exists( 'lat', $snapshot ) || ! array_key_exists( 'lng', $snapshot ) || null === $snapshot['lat'] || null === $snapshot['lng'] ) {
        $failures[] = sprintf( '[%s] %s lat/lng are missing', $fixture_id, $path );
    }

    if ( ! isset( $snapshot['captured_at'] ) || ! is_string( $snapshot['captured_at'] ) || '' === trim( $snapshot['captured_at'] ) ) {
        $failures[] = sprintf( '[%s] %s captured_at must be preserved', $fixture_id, $path );
    }

    if ( ! array_key_exists( 'stale', $snapshot ) || ! is_bool( $snapshot['stale'] ) ) {
        $failures[] = sprintf( '[%s] %s stale must be a boolean flag', $fixture_id, $path );
    }

    return $failures;
}

function wsb_handover_assert_payload_shape( array $payload, array $raw_payload, string $fixture_id ) : array {
    $failures = array();

    $route = is_array( $payload['route'] ?? null ) ? $payload['route'] : array();
    if ( is_array( $route['route_options'] ?? null ) ) {
        foreach ( $route['route_options'] as $index => $option ) {
            if ( ! is_array( $option ) ) {
                $failures[] = sprintf( '[%s] route.route_options[%d] must remain an object', $fixture_id, $index );
                continue;
            }

            foreach ( array( 'id', 'label' ) as $key ) {
                if ( empty( $option[ $key ] ) ) {
                    $failures[] = sprintf( '[%s] route.route_options[%d].%s is empty', $fixture_id, $index, $key );
                }
            }

            if ( isset( $option['preferences'] ) && ! is_array( $option['preferences'] ) ) {
                $failures[] = sprintf( '[%s] route.route_options[%d].preferences must remain an object', $fixture_id, $index );
            }

            if ( isset( $option['details'] ) && ! is_array( $option['details'] ) ) {
                $failures[] = sprintf( '[%s] route.route_options[%d].details must remain an object', $fixture_id, $index );
            }
        }
    }

    $charter = is_array( $payload['charter'] ?? null ) ? $payload['charter'] : array();
    if ( array_key_exists( 'additional_stop', $charter ) && is_array( $charter['additional_stop'] ) ) {
        foreach ( array( 'label', 'place_id', 'formatted_address' ) as $key ) {
            if ( ! array_key_exists( $key, $charter['additional_stop'] ) ) {
                $failures[] = sprintf( '[%s] charter.additional_stop missing key: %s', $fixture_id, $key );
            }
        }
    }

    if ( array_key_exists( 'poi', $charter ) && null !== $charter['poi'] ) {
        if ( ! is_string( $charter['poi'] ) || '' === trim( $charter['poi'] ) ) {
            $failures[] = sprintf( '[%s] charter.poi must preserve the POI text', $fixture_id );
        }
    }

    if ( array_key_exists( 'notes', $charter ) && null !== $charter['notes'] ) {
        if ( ! is_string( $charter['notes'] ) || '' === trim( $charter['notes'] ) ) {
            $failures[] = sprintf( '[%s] charter.notes must preserve the notes text', $fixture_id );
        }
    }

    if ( is_array( $charter['days'] ?? null ) ) {
        foreach ( $charter['days'] as $day_index => $day ) {
            if ( ! is_array( $day ) ) {
                $failures[] = sprintf( '[%s] charter.days[%d] must remain an object', $fixture_id, $day_index );
                continue;
            }

            foreach ( array( 'day_index', 'date', 'start_time', 'end_time', 'pickup_location', 'dropoff_location', 'stops' ) as $key ) {
                if ( ! array_key_exists( $key, $day ) ) {
                    $failures[] = sprintf( '[%s] charter.days[%d] missing key: %s', $fixture_id, $day_index, $key );
                }
            }
        }
    }

    $legs = is_array( $payload['legs'] ?? null ) ? $payload['legs'] : array();
    foreach ( $legs as $leg_index => $leg ) {
        if ( ! is_array( $leg ) ) {
            $failures[] = sprintf( '[%s] legs[%d] must remain an object', $fixture_id, $leg_index );
            continue;
        }

        $place_snapshots = is_array( $leg['place_snapshots'] ?? null ) ? $leg['place_snapshots'] : array();
        foreach ( array( 'from', 'to' ) as $endpoint ) {
            if ( ! isset( $place_snapshots[ $endpoint ] ) || ! is_array( $place_snapshots[ $endpoint ] ) ) {
                $failures[] = sprintf( '[%s] legs[%d].place_snapshots.%s must remain an object', $fixture_id, $leg_index, $endpoint );
                continue;
            }

            $failures = array_merge( $failures, wsb_handover_assert_place_snapshot( $place_snapshots[ $endpoint ], $fixture_id, sprintf( 'legs[%d].place_snapshots.%s', $leg_index, $endpoint ) ) );
        }

        if ( is_array( $place_snapshots['stops'] ?? null ) ) {
            foreach ( $place_snapshots['stops'] as $stop_index => $stop_snapshot ) {
                if ( ! is_array( $stop_snapshot ) ) {
                    $failures[] = sprintf( '[%s] legs[%d].place_snapshots.stops[%d] must remain an object', $fixture_id, $leg_index, $stop_index );
                    continue;
                }

                $failures = array_merge( $failures, wsb_handover_assert_place_snapshot( $stop_snapshot, $fixture_id, sprintf( 'legs[%d].place_snapshots.stops[%d]', $leg_index, $stop_index ) ) );
            }
        }
    }

    $itinerary = is_array( $payload['itinerary'] ?? null ) ? $payload['itinerary'] : array();
    if ( is_array( $itinerary['trips'] ?? null ) ) {
        foreach ( $itinerary['trips'] as $trip_index => $trip ) {
            if ( ! is_array( $trip ) ) {
                $failures[] = sprintf( '[%s] itinerary.trips[%d] must remain an object', $fixture_id, $trip_index );
                continue;
            }

            if ( ! array_key_exists( 'legs', $trip ) || ! is_array( $trip['legs'] ) ) {
                $failures[] = sprintf( '[%s] itinerary.trips[%d].legs must remain an array', $fixture_id, $trip_index );
            }
        }
    }

    if ( is_array( $route['route_options'] ?? null ) && is_array( $raw_payload['route']['route_options'] ?? null ) && $route['route_options'] !== $raw_payload['route']['route_options'] ) {
        $failures[] = sprintf( '[%s] route.route_options did not survive normalization unchanged', $fixture_id );
    }

    if ( array_key_exists( 'route_preferences', $raw_payload['route'] ?? array() ) && ( $route['route_preferences'] ?? null ) !== $raw_payload['route']['route_preferences'] ) {
        $failures[] = sprintf( '[%s] route.route_preferences did not survive normalization unchanged', $fixture_id );
    }

    if ( array_key_exists( 'route_details', $raw_payload['route'] ?? array() ) && ( $route['route_details'] ?? null ) !== $raw_payload['route']['route_details'] ) {
        $failures[] = sprintf( '[%s] route.route_details did not survive normalization unchanged', $fixture_id );
    }

    if ( array_key_exists( 'charter_additional_stop', $raw_payload ) ) {
        $expected = $raw_payload['charter_additional_stop'];
        $actual = $charter['additional_stop'] ?? null;
        if ( $actual !== $expected ) {
            $failures[] = sprintf( '[%s] charter.additional_stop did not survive normalization unchanged', $fixture_id );
        }
    }

    if ( array_key_exists( 'charter_notes', $raw_payload ) ) {
        $expected = $raw_payload['charter_notes'];
        $actual = $charter['notes'] ?? null;
        if ( $actual !== $expected ) {
            $failures[] = sprintf( '[%s] charter.notes did not survive normalization unchanged', $fixture_id );
        }
    }

    if ( array_key_exists( 'charter_poi', $raw_payload ) ) {
        $expected = $raw_payload['charter_poi'];
        $actual = $charter['poi'] ?? null;
        if ( $actual !== $expected ) {
            $failures[] = sprintf( '[%s] charter.poi did not survive normalization unchanged', $fixture_id );
        }
    }

    if ( is_array( $raw_payload['charter']['days'] ?? null ) && is_array( $charter['days'] ?? null ) && $charter['days'] !== $raw_payload['charter']['days'] ) {
        $failures[] = sprintf( '[%s] charter.days did not survive normalization unchanged', $fixture_id );
    }

    if ( is_array( $raw_payload['itinerary']['trips'] ?? null ) && is_array( $itinerary['trips'] ?? null ) && $itinerary['trips'] !== $raw_payload['itinerary']['trips'] ) {
        $failures[] = sprintf( '[%s] itinerary.trips did not survive normalization unchanged', $fixture_id );
    }

    return $failures;
}

function wsb_handover_assert_envelope( array $envelope, array $normalized_payload, array $raw_payload, string $fixture_id ) : array {
    $failures = array();

    if ( empty( $envelope ) ) {
        $failures[] = "[{$fixture_id}] envelope is empty";
        return $failures;
    }

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
        $found = false;
        $actual = wsb_handover_value_at_path( $envelope, explode( '.', $path ), $found );
        if ( ! $found || $actual !== $expected ) {
            $failures[] = sprintf( '[%s] %s: expected %s, got %s', $fixture_id, $path, var_export( $expected, true ), var_export( $actual, true ) );
        }
    }

    if ( empty( $envelope['integrity']['signature'] ?? null ) ) {
        $failures[] = "[{$fixture_id}] integrity.signature is empty";
    }

    if ( empty( $envelope['payload'] ?? null ) ) {
        $failures[] = "[{$fixture_id}] payload is missing or empty";
    }

    if ( ! in_array( 'hash_hmac_sha256', (array) ( $envelope['integrity']['algorithm'] ?? '' ), true ) ) {
        $failures[] = "[{$fixture_id}] integrity.algorithm is not hash_hmac_sha256";
    }

    $required_fields = array( 'handover_version', 'schema_version', 'action', 'request_id', 'created_at', 'expires_at', 'payload' );
    $signed = (array) ( $envelope['integrity']['signed_fields'] ?? array() );
    foreach ( $required_fields as $field ) {
        if ( ! in_array( $field, $signed, true ) ) {
            $failures[] = "[{$fixture_id}] signed_fields missing: {$field}";
        }
    }

    if ( $envelope['payload'] !== $normalized_payload ) {
        $failures[] = "[{$fixture_id}] envelope payload was mutated during handover envelope creation";
    }

    $failures = array_merge( $failures, wsb_handover_assert_payload_shape( $envelope['payload'], $raw_payload, $fixture_id ) );

    return $failures;
}

$total = count( $fixtures );
$valid_pass = 0;
$invalid_expected_fail = 0;
$skipped_unsupported = 0;
$unexpected_fail = 0;
$unexpected_pass = 0;
$failures_list = array();

echo "\n=== BookingPayload v2 Handover Fixture Runner ===\n";
echo "Total fixtures: {$total}\n";
echo 'Gate override mode: ' . wsb_client_fixture_gate_mode() . "\n";
echo "Forced gates: enable_google_places_required=true, enable_debug_free_text_locations_local_only=false\n\n";

foreach ( $fixtures as $i => $fixture ) {
    $id = $fixture['id'] ?? "fixture-{$i}";
    $description = $fixture['description'] ?? '';
    $expected_ok = (bool) ( $fixture['expected_ok'] ?? false );
    $skip = (bool) ( $fixture['skip'] ?? false );
    $raw_payload = $fixture['payload'] ?? array();

    if ( $skip ) {
        $skipped_unsupported++;
        echo "  - [SKIP] {$id}\n";
        if ( $description ) {
            echo "    Description: {$description}\n";
        }
        if ( ! empty( $fixture['skip_reason'] ) ) {
            printf( "    Skip reason: %s\n", $fixture['skip_reason'] );
        }
        echo "\n";
        continue;
    }

    $normalized = $normalizer->normalize( $raw_payload );
    $validation = $validator->validate( $normalized );
    $actual_ok = ! empty( $validation['valid'] );
    $assert_fails = array();

    if ( $expected_ok && $actual_ok ) {
        $envelope = $handover_service->build_envelope( $normalized, 'fixture' );
        $assert_fails = wsb_handover_assert_envelope( $envelope, $normalized, $raw_payload, $id );

        if ( empty( $assert_fails ) ) {
            $valid_pass++;
            $status = 'PASS';
            $symbol = '✓';
        } else {
            $unexpected_fail++;
            $status = 'UNEXPECTED_FAIL';
            $symbol = '✗';
            $failures_list[] = array(
                'id' => $id,
                'error' => 'handover envelope assertion failed',
                'failures' => $assert_fails,
            );
        }
    } elseif ( ! $expected_ok && ! $actual_ok ) {
        $invalid_expected_fail++;
        $status = 'EXPECTED_FAIL';
        $symbol = '✓';
    } elseif ( $expected_ok && ! $actual_ok ) {
        $unexpected_fail++;
        $status = 'UNEXPECTED_FAIL';
        $symbol = '✗';
        $failures_list[] = array(
            'id' => $id,
            'error' => 'payload failed validation before envelope build',
            'errors' => $validation['errors'] ?? array(),
        );
    } else {
        $unexpected_pass++;
        $status = 'UNEXPECTED_PASS';
        $symbol = '✗';
        $failures_list[] = array(
            'id' => $id,
            'error' => 'invalid fixture unexpectedly passed validation',
            'errors' => $validation['errors'] ?? array(),
        );
    }

    printf( "  %s [%s] %s\n", $symbol, $status, $id );
    if ( $description ) {
        printf( "    Description: %s\n", $description );
    }

    printf(
        "    Expected OK: %-5s  Actual OK: %-5s  Errors: %d  Warnings: %d\n",
        $expected_ok ? 'true' : 'false',
        $actual_ok ? 'true' : 'false',
        count( $validation['errors'] ?? array() ),
        count( $validation['warnings'] ?? array() )
    );

    if ( ( $expected_ok && ! $actual_ok ) || ( ! $expected_ok && $actual_ok ) ) {
        $error_label = $actual_ok ? 'unexpected pass' : 'unexpected fail';
        printf( "    Result: %s\n", $error_label );
    }

    if ( ! empty( $validation['errors'] ) ) {
        foreach ( $validation['errors'] as $err ) {
            printf( "      - [%s] %s: %s\n", $err['field'] ?? '?', $err['code'] ?? '?', $err['message'] ?? '?' );
        }
    }

    if ( ! empty( $assert_fails ) ) {
        foreach ( $assert_fails as $msg ) {
            printf( "      - %s\n", $msg );
        }
    }

    echo "\n";
}

echo "=== Results ===\n";
printf( "  total: %d\n", $total );
printf( "  valid_pass: %d\n", $valid_pass );
printf( "  invalid_expected_fail: %d\n", $invalid_expected_fail );
printf( "  skipped_unsupported: %d\n", $skipped_unsupported );
printf( "  unexpected_fail: %d\n", $unexpected_fail );
printf( "  unexpected_pass: %d\n", $unexpected_pass );

if ( 0 === $unexpected_fail && 0 === $unexpected_pass ) {
    echo "\nAll supported fixtures produced valid dry-run handover envelopes.\n";
    exit( 0 );
}

echo "\n=== Failed fixtures ===\n";
foreach ( $failures_list as $f ) {
    printf( "  %s\n", $f['id'] );
    if ( ! empty( $f['error'] ) ) {
        echo "    Error: {$f['error']}\n";
    }
    foreach ( $f['failures'] ?? array() as $msg ) {
        echo "    {$msg}\n";
    }
    if ( ! empty( $f['errors'] ) ) {
        echo "    Validation errors:\n";
        foreach ( $f['errors'] as $err ) {
            printf( "      - [%s] %s: %s\n", $err['field'] ?? '?', $err['code'] ?? '?', $err['message'] ?? '?' );
        }
    }
    echo "\n";
}

exit( 1 );
