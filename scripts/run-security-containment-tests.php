<?php
/** Standalone Phase 0.95 security tests. */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WP_DEBUG_LOG', false );
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function apply_filters( $hook, $value ) { return $value; }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $key ) ); }

require_once dirname( __DIR__ ) . '/inc/class-booking-security.php';
require_once dirname( __DIR__ ) . '/inc/class-booking-payload-v2-handover-service.php';

$failed = array();
$assert = static function ( bool $condition, string $message ) use ( &$failed ): void {
	if ( ! $condition ) $failed[] = $message;
};

$secret = 'synthetic-marketing-secret-never-deploy';
$url = 'https://router.example/v8/routes?apikey=' . $secret . '&origin=-33.9,18.4';
$safe_url = WSB_Client_Security::redact_url( $url );
$assert( false === strpos( $safe_url, $secret ), 'provider URL secret was not redacted' );
$assert( false !== strpos( $safe_url, 'router.example/v8/routes' ), 'safe provider context was lost' );

$safe = WSB_Client_Security::redact_value( array(
	'Authorization' => 'Bearer synthetic-bearer',
	'email' => 'synthetic@example.test',
	'phone' => '+27000000000',
	'booking_token' => 'synthetic-booking-token',
	'operation' => 'handover',
) );
$encoded = wp_json_encode( $safe );
foreach ( array( 'synthetic-bearer', 'synthetic@example.test', '+27000000000', 'synthetic-booking-token' ) as $needle ) {
	$assert( false === strpos( $encoded, $needle ), 'sensitive payload value survived redaction' );
}
$assert( 'handover' === $safe['operation'], 'safe operational context was removed' );

$payload = array( 'schema_version' => '2.0', 'trip_type' => 'one_way', 'legs' => array( array( 'from' => array( 'label' => 'Synthetic A' ), 'to' => array( 'label' => 'Synthetic B' ) ) ) );
$service = new WSB_Client_Booking_Payload_V2_Handover_Service( $secret );
$envelope = $service->build_envelope( $payload, 'submit', true );
$canonicalise = static function ( $value ) use ( &$canonicalise ) {
	if ( is_array( $value ) ) {
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) ksort( $value, SORT_STRING );
		foreach ( $value as $key => $child ) $value[ $key ] = $canonicalise( $child );
	}
	return $value;
};
$assert( hash( 'sha256', wp_json_encode( $canonicalise( $payload ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) === $envelope['body_hash'], 'payload body hash mismatch' );
$assert( in_array( 'body_hash', $envelope['integrity']['signed_fields'], true ), 'body hash is not signed' );
$assert( in_array( 'target_site', $envelope['integrity']['signed_fields'], true ), 'receiver is not signed' );
$assert( ! empty( $envelope['integrity']['signature'] ), 'valid handover is unsigned' );

if ( $failed ) {
	fwrite( STDERR, "Security containment failures:\n- " . implode( "\n- ", $failed ) . "\n" );
	exit( 1 );
}
echo "PASS: marketing security containment (redaction, PII, signed body/audience)\n";
