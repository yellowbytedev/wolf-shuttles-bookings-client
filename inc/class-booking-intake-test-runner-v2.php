<?php
/**
 * BookingPayload v2 fixture test runner scaffold.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WSB_Client_Booking_Intake_Test_Runner_V2' ) ) {
    final class WSB_Client_Booking_Intake_Test_Runner_V2 {
        private WSB_Client_Booking_Payload_V2_Normalizer $normalizer;
        private WSB_Client_Booking_Payload_V2_Validator $validator;

        public function __construct(
            WSB_Client_Booking_Payload_V2_Normalizer $normalizer,
            WSB_Client_Booking_Payload_V2_Validator $validator
        ) {
            $this->normalizer = $normalizer;
            $this->validator  = $validator;
        }

        public function register_cli() : void {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::add_command( 'wsb-client test-payloads', array( $this, 'run_cli' ) );
            }
        }

        /**
         * @param array<int,string> $args
         * @param array<string,string> $assoc_args
         */
        public function run_cli( array $args, array $assoc_args ) : void {
            $file = $assoc_args['file'] ?? plugin_dir_path( __DIR__ ) . 'tests/fixtures/booking-payload-v2-core.json';

            if ( ! file_exists( $file ) ) {
                WP_CLI::error( 'Fixture file not found: ' . $file );
            }

            $fixtures = json_decode( (string) file_get_contents( $file ), true );
            if ( ! is_array( $fixtures ) ) {
                WP_CLI::error( 'Fixture file is not valid JSON array.' );
            }

            foreach ( $fixtures as $fixture ) {
                $id = $fixture['id'] ?? 'unknown';
                $input = is_array( $fixture['input'] ?? null ) ? $fixture['input'] : array();
                $payload = $this->normalizer->normalize( $input );
                $validation = $this->validator->validate( $payload );

                WP_CLI::line( sprintf( '[%s] %s / %s => %s', $id, $payload['trip_type'], $payload['service_type'], $validation['valid'] ? 'VALID' : 'INVALID' ) );

                foreach ( $validation['errors'] as $error ) {
                    WP_CLI::warning( sprintf( '  %s: %s', $error['field'], $error['message'] ) );
                }
            }
        }
    }
}
