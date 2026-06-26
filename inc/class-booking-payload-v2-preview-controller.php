<?php
/**
 * REST preview endpoint scaffold for BookingPayload v2.
 *
 * Merge into: wp-content/plugins/ws-bookings-client/inc/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WSB_Client_Booking_Payload_V2_Preview_Controller' ) ) {
    final class WSB_Client_Booking_Payload_V2_Preview_Controller {
        private WSB_Client_Booking_Payload_V2_Normalizer $normalizer;
        private WSB_Client_Booking_Payload_V2_Validator $validator;

        public function __construct(
            WSB_Client_Booking_Payload_V2_Normalizer $normalizer,
            WSB_Client_Booking_Payload_V2_Validator $validator
        ) {
            $this->normalizer = $normalizer;
            $this->validator  = $validator;
        }

        public function register() : void {
            add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        }

        public function register_routes() : void {
            register_rest_route(
                'ws-bookings-client/v1',
                '/payload-preview',
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'preview' ),
                    'permission_callback' => '__return_true',
                )
            );
        }

        /**
         * @param WP_REST_Request $request
         * @return WP_REST_Response
         */
        public function preview( WP_REST_Request $request ) : WP_REST_Response {
            $raw = $request->get_json_params();
            if ( ! is_array( $raw ) ) {
                $raw = array();
            }

            $normalized = $this->normalizer->normalize( $raw );
            $validation = $this->validator->validate( $normalized );

            return new WP_REST_Response(
                array(
                    'ok'         => true,
                    'payload'    => $normalized,
                    'validation' => $validation,
                    'meta'       => array(
                        'preview_only' => true,
                        'generated_at' => gmdate( 'c' ),
                    ),
                ),
                200
            );
        }
    }
}
