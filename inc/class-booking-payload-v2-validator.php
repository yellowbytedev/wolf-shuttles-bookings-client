<?php
/**
 * BookingPayload v2 validator scaffold for the Wolf Shuttles marketing plugin.
 *
 * Merge into: wp-content/plugins/ws-bookings-client/inc/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WSB_Client_Booking_Payload_V2_Validator' ) ) {
    final class WSB_Client_Booking_Payload_V2_Validator {
        /**
         * @param array<string,mixed> $payload Normalized BookingPayload v2.
         * @return array{valid:bool,errors:array<int,array<string,string>>,warnings:array<int,array<string,string>>}
         */
        public function validate( array $payload ) : array {
            $errors = array();
            $warnings = array();

            if ( '2.0' !== (string) ( $payload['schema_version'] ?? '' ) ) {
                $errors[] = $this->error( 'schema_version', 'invalid_schema_version', 'Payload schema_version must be 2.0.' );
            }

            $trip_type = (string) ( $payload['trip_type'] ?? '' );
            if ( ! in_array( $trip_type, array( 'one_way', 'return', 'charter' ), true ) ) {
                $errors[] = $this->error( 'trip_type', 'invalid_trip_type', 'Trip type is invalid.' );
            }

            if ( (int) ( $payload['passengers'] ?? 0 ) < 1 ) {
                $errors[] = $this->error( 'passengers', 'required', 'At least one passenger is required.' );
            }

            $legs = is_array( $payload['legs'] ?? null ) ? $payload['legs'] : array();
            if ( empty( $legs ) ) {
                $errors[] = $this->error( 'legs', 'required', 'At least one trip leg is required.' );
            }

            foreach ( $legs as $index => $leg ) {
                if ( ! is_array( $leg ) ) {
                    $errors[] = $this->error( 'legs.' . $index, 'invalid_leg', 'Leg must be an object/array.' );
                    continue;
                }

                $prefix = 'legs.' . $index;
                $type = (string) ( $leg['type'] ?? '' );

                if ( ! in_array( $type, array( 'outbound', 'return', 'charter' ), true ) ) {
                    $errors[] = $this->error( $prefix . '.type', 'invalid_leg_type', 'Leg type is invalid.' );
                }

                if ( empty( $leg['from']['label'] ?? '' ) ) {
                    $errors[] = $this->error( $prefix . '.from', 'required', 'Origin is required.' );
                }

                if ( empty( $leg['to']['label'] ?? '' ) ) {
                    $errors[] = $this->error( $prefix . '.to', 'required', 'Destination is required.' );
                }

                if ( $type === 'charter' ) {
                    if ( empty( $leg['pickup_date'] ?? '' ) ) {
                        $errors[] = $this->error( $prefix . '.pickup_date', 'required', 'Charter date is required.' );
                    }
                    if ( empty( $leg['pickup_time'] ?? '' ) ) {
                        $errors[] = $this->error( $prefix . '.pickup_time', 'required', 'Charter start time is required.' );
                    }
                    if ( empty( $leg['dropoff_time'] ?? '' ) ) {
                        $errors[] = $this->error( $prefix . '.dropoff_time', 'required', 'Charter end time is required.' );
                    } else {
                        $pickup_time = $leg['pickup_time'] ?? '';
                        $dropoff_time = $leg['dropoff_time'] ?? '';
                        if ( $pickup_time && $dropoff_time && $dropoff_time <= $pickup_time ) {
                            $errors[] = $this->error( $prefix . '.dropoff_time', 'invalid_time_order', 'Charter end time must be after start time.' );
                        }
                    }
                } else {
                    if ( empty( $leg['pickup_date'] ?? '' ) ) {
                        $errors[] = $this->error( $prefix . '.pickup_date', 'required', 'Pickup date is required.' );
                    }

                    if ( empty( $leg['pickup_time'] ?? '' ) ) {
                        $errors[] = $this->error( $prefix . '.pickup_time', 'required', 'Pickup time is required.' );
                    }
                }
            }

            if ( 'return' === $trip_type && count( $legs ) < 2 ) {
                $errors[] = $this->error( 'legs', 'return_leg_required', 'Return trips require outbound and return legs.' );
            }

            if ( 'one_way' === $trip_type && count( $legs ) > 1 ) {
                $warnings[] = $this->warning( 'legs', 'extra_legs_ignored', 'One-way trips should only have one leg.' );
            }

            if ( 'charter' === $trip_type ) {
                $charter = $payload['charter'] ?? array();
                if ( ! is_array( $charter ) || empty( $charter['enabled'] ) ) {
                    $warnings[] = $this->warning( 'charter', 'disabled', 'Charter payload has charter.enabled as false or missing.' );
                }
            }

            /**
             * Filter v2 validation result.
             *
             * @param array $result
             * @param array $payload
             */
            return apply_filters(
                'wsb_client_booking_payload_v2_validation_result',
                array(
                    'valid'    => empty( $errors ),
                    'errors'   => $errors,
                    'warnings' => $warnings,
                ),
                $payload
            );
        }

        private function error( string $field, string $code, string $message ) : array {
            return array(
                'level'   => 'error',
                'field'   => $field,
                'code'    => $code,
                'message' => $message,
            );
        }

        private function warning( string $field, string $code, string $message ) : array {
            return array(
                'level'   => 'warning',
                'field'   => $field,
                'code'    => $code,
                'message' => $message,
            );
        }
    }
}