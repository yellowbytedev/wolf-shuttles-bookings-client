<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoint for BookingPayload v2 handover.
 *
 * - POST /wp-json/ws-bookings-client/v1/handover-preview
 * - Requires X-WP-Nonce header (wp_rest)
 * - On real handover: forwards to booking-site v2 intake endpoint
 * - On preview mode: returns envelope for inspection
 *
 * Response (valid payload, real handover):
 *   success: true
 *   redirect_url: <booking site URL with token>
 *
 * Response (valid payload, preview mode):
 *   ok: true
 *   normalised_payload, handover_envelope, meta
 *
 * Response (invalid payload):
 *   ok: false
 *   validation errors
 */
if ( ! class_exists( 'WSB_Client_Booking_Payload_V2_Handover_Preview_Controller' ) ) {
    final class WSB_Client_Booking_Payload_V2_Handover_Preview_Controller {

        private WSB_Client_Booking_Payload_V2_Normalizer $normalizer;
        private WSB_Client_Booking_Payload_V2_Validator $validator;
        private WSB_Client_Booking_Payload_V2_Handover_Service $handover_service;

        /**
         * @param WSB_Client_Booking_Payload_V2_Normalizer      $normalizer
         * @param WSB_Client_Booking_Payload_V2_Validator       $validator
         * @param WSB_Client_Booking_Payload_V2_Handover_Service $handover_service
         */
        public function __construct(
            WSB_Client_Booking_Payload_V2_Normalizer $normalizer,
            WSB_Client_Booking_Payload_V2_Validator $validator,
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
                    'permission_callback'   => array( $this, 'verify_nonce' ),
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
         * @return WP_REST_Response
         */
        public function handle_request( $request ) {
            $raw = $request->get_json_params();
            if ( ! is_array( $raw ) ) {
                $raw = array();
            }

            $normalized = $this->normalizer->normalize( $raw );
            $validation = $this->validator->validate( $normalized );
            $ok = ! empty( $validation['valid'] );

            $is_real_handover = WSB_Client_Booking_Intake_Client::is_real_handover_enabled();

            $response_data = array(
                'ok'         => $ok,
                'payload'    => $normalized,
                'validation' => $validation,
                'meta'       => array(
                    'preview_only'          => ! $is_real_handover,
                    'real_handover_enabled' => $is_real_handover,
                    'generated_at'          => gmdate( 'c' ),
                ),
            );

            if ( $ok ) {
                $response_data['normalised_payload']  = $normalized;
                $response_data['handover_envelope']   = $this->handover_service->build_envelope( $normalized, 'handover_preview', $is_real_handover );

                if ( $is_real_handover ) {
                    $envelope = $response_data['handover_envelope'];
                    $intake_result = wsb_client_booking_intake()->send( $envelope );

                    if ( $intake_result['success'] ) {
                        $response_data['success']     = true;
                        $response_data['redirect_url'] = $intake_result['redirect_url'];
                        $response_data['booking_data'] = $intake_result['data'] ?? array();
                    } else {
                        $response_data['success'] = false;
                        $response_data['error']   = $intake_result['error'] ?? 'Handover to booking site failed';
                    }
                }
            }

            $status = $response_data['ok'] ? 200 : 400;

            return new WP_REST_Response( $response_data, $status );
        }
    }
}