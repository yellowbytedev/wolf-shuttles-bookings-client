<?php
/**
 * Booking-site intake client for real handover from marketing plugin.
 *
 * Sends signed envelopes to the booking-site v2 intake endpoint.
 * Does not create bookings or pricing - only hands off validated payloads.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WSB_Client_Booking_Intake_Client' ) ) {
    final class WSB_Client_Booking_Intake_Client {

        /**
         * @var string Booking site base URL.
         */
        private string $booking_site_url;

        /**
         * @var int HTTP timeout in seconds.
         */
        private int $timeout;

        /**
         * @param string $booking_site_url Base URL for the booking site.
         * @param int    $timeout          Request timeout in seconds.
         */
        public function __construct( string $booking_site_url = '', int $timeout = 15 ) {
            $this->booking_site_url = $this->resolve_booking_site_url( $booking_site_url );
            $this->timeout = $timeout;
        }

        /**
         * Resolve the booking site URL: explicit value, then filter, then default.
         *
         * @param string $url Explicit URL or empty for auto-resolution.
         * @return string
         */
        private function resolve_booking_site_url( string $url ): string {
            if ( $url !== '' ) {
                return rtrim( $url, '/' );
            }

            /**
             * Filter the booking site URL.
             *
             * @param string $url
             */
            $url = apply_filters( 'wsb_client_booking_site_url', '' );
            if ( $url !== '' ) {
                return rtrim( $url, '/' );
            }

            // Check for configured booking site URL constant.
            if ( defined( 'WSB_CLIENT_BOOKING_SITE_URL' ) && WSB_CLIENT_BOOKING_SITE_URL !== '' ) {
                return rtrim( WSB_CLIENT_BOOKING_SITE_URL, '/' );
            }

            // Local development fallback.
            if ( function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'local' ) {
                return 'https://bookings.wolfshuttles.local';
            }

            return '';
        }

        /**
         * Send a signed envelope to the booking site intake endpoint.
         *
         * @param array<string,mixed> $envelope Signed handover envelope.
         * @return array{success:bool,redirect_url?:string,error?:string,data?:array<string,mixed>}
         */
        public function send( array $envelope ): array {
            if ( $this->booking_site_url === '' ) {
                return array(
                    'success' => false,
                    'error'   => 'Booking site URL not configured',
                );
            }

            $endpoint = $this->booking_site_url . '/wp-json/ws-bookings/v2/intake';

            $args = array(
                'method'      => 'POST',
                'timeout'     => $this->timeout,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking'    => true,
                'headers'     => array(
                    'Content-Type' => 'application/json',
                ),
                'body'        => wp_json_encode( array( 'handover_envelope' => $envelope ) ),
                'data_format' => 'body',
            );

            $response = wp_remote_post( $endpoint, $args );

            if ( is_wp_error( $response ) ) {
                return array(
                    'success' => false,
                    'error'   => 'HTTP error: ' . $response->get_error_message(),
                );
            }

            $status_code = (int) wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );

            $data = json_decode( $body, true );

            if ( ! is_array( $data ) ) {
                return array(
                    'success' => false,
                    'error'   => 'Invalid response format',
                );
            }

            if ( $status_code >= 200 && $status_code < 300 ) {
                return array(
                    'success'      => true,
                    'redirect_url' => $data['redirect_url'] ?? '',
                    'data'         => $data,
                );
            }

            return array(
                'success' => false,
                'error'   => $data['message'] ?? 'Intake endpoint returned ' . $status_code,
                'data'    => $data,
            );
        }

        /**
         * Check if real handover is enabled via feature gate.
         *
         * @return bool
         */
        public static function is_real_handover_enabled(): bool {
            if ( class_exists( \WSB_Booking_Client\Booking_Feature_Gates::class ) ) {
                return \WSB_Booking_Client\Booking_Feature_Gates::is_enabled( 'enable_real_handover' );
            }
            return (bool) apply_filters( 'wsb_client_real_handover_enabled', false );
        }
    }
}

/**
 * Get the booking intake client instance.
 *
 * @return WSB_Client_Booking_Intake_Client
 */
function wsb_client_booking_intake(): WSB_Client_Booking_Intake_Client {
    static $instance = null;
    if ( $instance === null ) {
        $instance = new WSB_Client_Booking_Intake_Client();
    }
    return $instance;
}