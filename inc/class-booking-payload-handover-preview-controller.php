<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST endpoint for dry-run BookingPayload v2 handover preview.
 *
 * - POST /wp-json/ws-bookings-client/v1/handover-preview
 * - Requires X-WP-Nonce header (wp_rest)
 * - Never calls the booking site
 * - Never creates a booking token or database record
 *
 * Response (valid payload):
 *   ok: true
 *   normalised_payload, validation, handover_envelope, meta
 *
 * Response (invalid payload):
 *   ok: false
 *   validation errors
 *   no handover_envelope
 */
if ( ! class_exists( 'WSB_Client_Booking_Payload_V2_Handover_Preview_Controller' ) ) {
    final class WSB_Client_Booking_Payload_V2_Handover_Preview_Controller {

	private BookingPayloadV2Normalizer $normalizer;
	private BookingPayloadV2Validator $validator;
	private WSB_Client_Booking_Payload_V2_Handover_Service $handover_service;

	/**
	 * @param BookingPayloadV2Normalizer      $normalizer
	 * @param BookingPayloadV2Validator       $validator
	 * @param WSB_Client_Booking_Payload_V2_Handover_Service $handover_service
	 */
	public function __construct(
		BookingPayloadV2Normalizer $normalizer,
		BookingPayloadV2Validator $validator,
		WSB_Client_Booking_Payload_V2_Handover_Service $handover_service
	) {
		$this->normalizer      = $normalizer;
		$this->validator       = $validator;
		$this->handover_service = $handover_service;
	}

	/**
	 * Register the REST route.
	 */
	public function register() : void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register /handover-preview route.
	 */
	public function register_routes() : void {
		register_rest_route(
			'ws-bookings-client/v1',
			'/handover-preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
				'args'                => array(
					'schema_version' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Verify the WP REST nonce.
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function verify_nonce( $request ) : bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || $nonce === '' ) {
			return false;
		}

		return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Handle the handover-preview request.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_REST_Error
	 */
	public function handle_request( $request ) {
		$raw = $request->get_json_params();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$normalized = $this->normalizer->normalize( $raw );
		$validation = $this->validator->validate( $normalized );
		$ok = ! empty( $validation['valid'] );

		$response_data = array(
			'ok'          => $ok,
			'payload'     => $normalized,
			'validation'  => $validation,
			'meta'        => array(
				'preview_only'          => true,
				'real_handover_enabled' => false,
				'generated_at'          => gmdate( 'c' ),
			),
		);

		if ( $ok ) {
			$response_data['normalised_payload'] = $normalized;
			$response_data['handover_envelope']  = $this->handover_service->build_envelope( $normalized, 'handover_preview' );
		}

		return new WP_REST_Response( $response_data, 200 );
	}
}
}
