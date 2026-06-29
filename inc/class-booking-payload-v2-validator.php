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
        private function get_booking_config(): array {
            if ( function_exists( 'wsb_client_external_services' ) ) {
                return wsb_client_external_services()->get_cached_booking_site_config();
            }
            return array(
                'lead_times' => array(
                    'transfer_min_notice_minutes' => 300,
                    'charter_min_notice_minutes' => 2880,
                    'max_advance_booking_days' => 365,
                ),
                'capacity' => array(
                    'max_passengers' => 13,
                ),
            );
        }

        private function parse_time_to_minutes( string $time ): ?int {
            $time = trim( strtolower( $time ) );
            if ( $time === '' ) {
                return null;
            }
            if ( ! preg_match( '/^(\d{1,2})(?::?(\d{2}))?\s*(am|pm)?$/', $time, $m ) ) {
                return null;
            }
            $h = (int) $m[1];
            $mi = isset( $m[2] ) ? (int) $m[2] : 0;
            $ap = $m[3] ?? '';
            if ( $ap === 'pm' && $h < 12 ) {
                $h += 12;
            }
            if ( $ap === 'am' && $h === 12 ) {
                $h = 0;
            }
            return $h * 60 + $mi;
        }

        private function format_validation_error( array $error ): string {
            return sprintf( '%s: %s', $error['field'], $error['message'] );
        }

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

            $config = $this->get_booking_config();
            $lead_times = $config['lead_times'] ?? array();
            $tz = wp_timezone();

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

                    // Charter lead time validation
                    if ( ! empty( $leg['pickup_date'] ) && ! empty( $leg['pickup_time'] ) ) {
                        $pickup_date = $leg['pickup_date'];
                        $pickup_time_val = $leg['pickup_time'];
                        $charter_min = (int) ( $lead_times['charter_min_notice_minutes'] ?? 2880 );
                        $max_advance = (int) ( $lead_times['max_advance_booking_days'] ?? 365 );

                        try {
                            $pickup_dt = new DateTime( $pickup_date . ' ' . $pickup_time_val, $tz );
                            $now = new DateTime( 'now', $tz );

                            $diff_minutes = ( (int) $pickup_dt->format( 'U' ) - (int) $now->format( 'U' ) ) / 60;

                            if ( $diff_minutes < $charter_min ) {
                                $errors[] = $this->error(
                                    $prefix . '.pickup_time',
                                    'lead_time_violation',
                                    sprintf( 'Charter pickup must be at least %d hours in advance.', $charter_min / 60 )
                                );
                            }

                            $max_date = new DateTime( 'now', $tz );
                            $max_date->modify( '+' . $max_advance . ' days' );
                            $max_date->setTime( 23, 59, 59 );

                            if ( $pickup_dt > $max_date ) {
                                $errors[] = $this->error(
                                    $prefix . '.pickup_date',
                                    'max_advance_violation',
                                    sprintf( 'Charter pickup date cannot be more than %d days in advance.', $max_advance )
                                );
                            }
                        } catch ( Exception $e ) {
                            // Date parsing failed, skip lead time validation
                        }
                    }
                } else {
                    if ( empty( $leg['pickup_date'] ?? '' ) ) {
                        $errors[] = $this->error( $prefix . '.pickup_date', 'required', 'Pickup date is required.' );
                    }

                    if ( empty( $leg['pickup_time'] ?? '' ) ) {
                        $errors[] = $this->error( $prefix . '.pickup_time', 'required', 'Pickup time is required.' );
                    }

                    // Transfer lead time validation
                    if ( ! empty( $leg['pickup_date'] ) && ! empty( $leg['pickup_time'] ) ) {
                        $pickup_date = $leg['pickup_date'];
                        $pickup_time_val = $leg['pickup_time'];
                        $transfer_min = (int) ( $lead_times['transfer_min_notice_minutes'] ?? 300 );
                        $max_advance = (int) ( $lead_times['max_advance_booking_days'] ?? 365 );

                        try {
                            $pickup_dt = new DateTime( $pickup_date . ' ' . $pickup_time_val, $tz );
                            $now = new DateTime( 'now', $tz );

                            $diff_minutes = ( (int) $pickup_dt->format( 'U' ) - (int) $now->format( 'U' ) ) / 60;

                            if ( $diff_minutes < $transfer_min ) {
                                $errors[] = $this->error(
                                    $prefix . '.pickup_time',
                                    'lead_time_violation',
                                    sprintf( 'Pickup must be at least %d minutes in advance.', $transfer_min )
                                );
                            }

                            $max_date = new DateTime( 'now', $tz );
                            $max_date->modify( '+' . $max_advance . ' days' );
                            $max_date->setTime( 23, 59, 59 );

                            if ( $pickup_dt > $max_date ) {
                                $errors[] = $this->error(
                                    $prefix . '.pickup_date',
                                    'max_advance_violation',
                                    sprintf( 'Pickup date cannot be more than %d days in advance.', $max_advance )
                                );
                            }
                        } catch ( Exception $e ) {
                            // Date parsing failed, skip lead time validation
                        }
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
