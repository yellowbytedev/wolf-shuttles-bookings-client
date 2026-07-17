<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * BookingPayload v2 handover service.
 *
 * Produces a deterministic, HMAC-signed handover envelope for dry-run
 * or (future) real handover to the booking-site endpoint.
 *
 * This service never:
 * - calls the booking site
 * - creates a booking
 * - creates database records
 * - exposes secrets to JavaScript
 */
if ( ! class_exists( 'WSB_Client_Booking_Payload_V2_Handover_Service' ) ) {
    final class WSB_Client_Booking_Payload_V2_Handover_Service {

	/**
	 * Envelope mode — dry-run only at this stage.
	 */
	public const MODE = 'dry_run';

	/**
	 * Handover schema version.
	 */
	private const HANDOVER_VERSION = '2.0';

	/**
	 * Hours of validity for a generated envelope.
	 */
	private const EXPIRY_HOURS = 1;

	/**
	 * @var string
	 */
	private $secret;

	/**
	 * @param string $secret The HMAC secret used to sign envelopes.
	 */
	public function __construct( string $secret = '' ) {
		$this->secret = $secret;
	}

	/**
	 * Build a complete signed envelope around a normalised BookingPayload v2.
	 *
	 * @param array<string,mixed> $payload Normalised BookingPayload v2.
	 * @param string              $action Envelope action label, e.g. 'preview'.
	 * @return array<string,mixed>
	 */
	public function build_envelope( array $payload, string $action = 'preview', bool $real_handover = false ) : array {
		$request_id = $this->generate_request_id();
		$created_at = gmdate( 'c' );
		$expires_at = gmdate( 'c', strtotime( '+' . self::EXPIRY_HOURS . ' hour' ) );

		$envelope = array(
			'handover_version'   => self::HANDOVER_VERSION,
			'schema_version'     => '2.0',
			'mode'               => $real_handover ? 'real' : self::MODE,
			'action'             => sanitize_key( $action ),
			'request_id'         => $request_id,
			'created_at'         => $created_at,
			'expires_at'         => $expires_at,
			'source_site'         => 'marketing',
			'target_site'         => 'booking',
			'payload'            => $payload,
			'integrity'          => array(
				'algorithm'    => 'hash_hmac_sha256',
				'signature'    => '',
				'signed_fields' => array(
					'handover_version',
					'schema_version',
					'action',
					'request_id',
					'created_at',
					'expires_at',
					'payload',
				),
			),
			'meta'               => array(
				'preview_only'           => ! $real_handover,
				'real_handover_enabled'  => $real_handover,
			),
		);

		$message = $this->build_signature_message( $envelope );
		$envelope['integrity']['signature'] = $this->compute_hmac_sha256( $message );

		/**
		 * Filter the handover envelope before it is returned.
		 *
		 * @param array<string,mixed> $envelope
		 * @param array<string,mixed> $payload
		 * @param string              $action
		 */
		return apply_filters( 'wsb_client_booking_payload_v2_handover_envelope', $envelope, $payload, $action );
	}

	/**
	 * Canonicalise an array for signing by recursively sorting keys.
	 * Numeric-indexed arrays are not reordered — only associative keys.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function canonicalise( $value ) {
		if ( is_array( $value ) ) {
			if ( $this->is_associative( $value ) ) {
				ksort( $value, SORT_STRING );
				$result = array();
				foreach ( $value as $k => $v ) {
					$result[ $k ] = $this->canonicalise( $v );
				}
				return $result;
			}

			return array_map( array( $this, 'canonicalise' ), $value );
		}

		return $value;
	}

	/**
	 * Produce a stable JSON string for the envelope fields that are signed.
	 *
	 * @param array<string,mixed> $envelope
	 * @return string
	 */
	private function build_signature_message( array $envelope ) : string {
		$fields = $envelope['integrity']['signed_fields'] ?? array();

		$subset = array();
		foreach ( $fields as $field ) {
			$subset[ $field ] = $envelope[ $field ] ?? null;
		}

		$canonical = $this->canonicalise( $subset );

		// Stable encoding: no extra whitespace, plain ASCII output.
		return wp_json_encode( $canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Compute HMAC-SHA256, returning an empty string if no secret is set.
	 *
	 * @param string $message
	 * @return string
	 */
	private function compute_hmac_sha256( string $message ) : string {
		$secret = $this->resolve_secret();
		if ( empty( $secret ) ) {
			return '';
		}

		return hash_hmac( 'sha256', $message, $secret );
	}

	/**
	 * Resolve the signing secret: instance value, then config constant, then dev fallback.
	 *
	 * @return string
	 */
	private function resolve_secret() : string {
		if ( ! empty( $this->secret ) ) {
			return $this->secret;
		}

		if ( defined( 'WSB_CLIENT_V2_HANDOVER_SECRET' ) && WSB_CLIENT_V2_HANDOVER_SECRET !== '' ) {
			return WSB_CLIENT_V2_HANDOVER_SECRET;
		}

		// Local/development environment fallback only.
		// Prefer the explicit WP_ENVIRONMENT_TYPE constant, then the WordPress runtime helper.
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && is_string( WP_ENVIRONMENT_TYPE ) && strtolower( WP_ENVIRONMENT_TYPE ) === 'local' ) {
			return 'local_v2_handover_secret';
		}

		// Uses wp_get_environment_type() to ensure staging/production never fall back.
		if ( function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'local' ) {
			return 'local_v2_handover_secret';
		}

		return '';
	}

	/**
	 * Generate a unique request identifier (UUID v4 style).
	 *
	 * @return string
	 */
	private function generate_request_id() : string {
		$data  = random_bytes( 16 );
		$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
		$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );

		return vsprintf(
			'%s%s-%s-%s-%s-%s%s%s',
			str_split( bin2hex( $data ), 4 )
		);
	}

	/**
	 * Determine whether an array is associative (string-keyed).
	 *
	 * @param array $array
	 * @return bool
	 */
	private function is_associative( array $array ) : bool {
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}
    }
}
