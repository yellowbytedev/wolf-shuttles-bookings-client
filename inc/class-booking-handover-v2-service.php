<?php
/**
 * Booking handover v2 service scaffold.
 *
 * This is intentionally not fully wired yet. It prepares the marketing plugin for
 * future `legacy_hash` and `v2_token` handover modes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WSB_Client_Booking_Handover_V2_Service' ) ) {
    final class WSB_Client_Booking_Handover_V2_Service {
        private string $booking_site_base_url;

        public function __construct( string $booking_site_base_url ) {
            $this->booking_site_base_url = untrailingslashit( $booking_site_base_url );
        }

        /**
         * @param array<string,mixed> $payload Normalized/validated BookingPayload v2.
         * @return array{ok:bool,mode:string,redirect_url:string,token:string,error:string}
         */
        public function create_v2_token_handover( array $payload ) : array {
            // Placeholder until booking-site v2 receiver exists.
            $token = $this->generate_booking_token();

            return array(
                'ok'           => true,
                'mode'         => 'v2_token',
                'redirect_url' => add_query_arg(
                    array(
                        'booking_token' => $token,
                        'trip_id'       => 1,
                    ),
                    $this->booking_site_base_url . '/'
                ),
                'token'        => $token,
                'error'        => '',
            );
        }

        private function generate_booking_token() : string {
            return strtolower( wp_generate_password( 12, false, false ) );
        }
    }
}
