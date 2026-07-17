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
            if ( ! in_array( $trip_type, array( 'one_way', 'return', 'charter', 'multi_trip' ), true ) ) {
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

// Google Places enforcement authority: Server-side feature gates control whether snapshots are required.
		// Payload validation_flags.google_place_snapshots_ready is diagnostic only, not enforcement authority.
		$validation_flags = is_array( $payload['validation_flags'] ?? null ) ? $payload['validation_flags'] : array();
		$is_diagnostic_ready = ! empty( $validation_flags['google_place_snapshots_ready'] );
		$debug_free_text_enabled = class_exists( 'WSB_Booking_Client\Booking_Feature_Gates' )
			&& WSB_Booking_Client\Booking_Feature_Gates::is_enabled( 'enable_debug_free_text_locations_local_only' );
		$enforce_place_snapshots = class_exists( 'WSB_Booking_Client\Booking_Feature_Gates' )
			&& WSB_Booking_Client\Booking_Feature_Gates::is_enabled( 'enable_google_places_required' )
			&& ! $debug_free_text_enabled;
		$google_places_ready = true;
		$missing_place_ids = array();

            foreach ( $legs as $leg_index => $leg ) {
                if ( ! is_array( $leg ) ) {
                    continue;
                }

                $leg_prefix = 'legs.' . $leg_index;
                $place_snapshots = is_array( $leg['place_snapshots'] ?? null ) ? $leg['place_snapshots'] : array();

                // Validate from location snapshot
                $from_snapshot = $place_snapshots['from'] ?? array();
                if ( empty( $leg['from']['label'] ?? '' ) ) {
                    // Already covered by required label validation
                } elseif ( ! $this->has_valid_place_snapshot( $from_snapshot ) ) {
                    $google_places_ready = false;
                    $missing_place_ids[] = $leg_prefix . '.from';
                }

                // Validate to location snapshot
                $to_snapshot = $place_snapshots['to'] ?? array();
                if ( empty( $leg['to']['label'] ?? '' ) ) {
                    // Already covered by required label validation
                } elseif ( ! $this->has_valid_place_snapshot( $to_snapshot ) ) {
                    $google_places_ready = false;
                    $missing_place_ids[] = $leg_prefix . '.to';
                }

                // Validate additional stop snapshots (if present)
                $stops = is_array( $leg['stops'] ?? null ) ? $leg['stops'] : array();
                foreach ( $stops as $stop_index => $stop ) {
                    if ( ! is_array( $stop ) ) {
                        continue;
                    }
                    $stop_location = $stop['location'] ?? array();
                    if ( empty( $stop_location['label'] ?? '' ) ) {
                        continue;
                    }
                    $stop_snapshot = $place_snapshots['stops'][$stop_index] ?? array();
                    if ( ! $this->has_valid_place_snapshot( $stop_snapshot ) ) {
                        $google_places_ready = false;
                        $missing_place_ids[] = $leg_prefix . '.stops[' . $stop_index . ']';
                    }
                }
            }

            $route = is_array( $payload['route'] ?? null ) ? $payload['route'] : array();
            $route_authority_messages = array(
                'distance_meters' => 'Route distance is advisory only and must not be authoritative.',
                'duration_seconds' => 'Route duration is advisory only and must not be authoritative.',
                'price_quoted'    => 'Route price is advisory only and must not be authoritative.',
                'polyline'        => 'Route polyline is advisory only and must not be authoritative.',
            );
            foreach ( $route_authority_messages as $field => $message ) {
                if ( array_key_exists( $field, $route ) && null !== $route[ $field ] && '' !== $route[ $field ] ) {
                    $errors[] = $this->error( 'route.' . $field, 'authoritative_not_allowed', $message );
                }
            }

            $charter = is_array( $payload['charter'] ?? null ) ? $payload['charter'] : array();
            if ( 'reserved' === (string) ( $charter['type'] ?? '' ) ) {
                $charter_days = is_array( $charter['days'] ?? null ) ? $charter['days'] : array();
                foreach ( $charter_days as $day_index => $day ) {
                    if ( ! is_array( $day ) ) {
                        continue;
                    }

                    $day_prefix = 'charter.days.' . $day_index;
                    $day_place_snapshots = is_array( $day['place_snapshots'] ?? null ) ? $day['place_snapshots'] : array();
                    $pickup_location = is_array( $day['pickup_location'] ?? null ) ? $day['pickup_location'] : array();
                    $dropoff_location = is_array( $day['dropoff_location'] ?? null ) ? $day['dropoff_location'] : array();

                    if ( ! empty( $pickup_location['label'] ?? '' ) ) {
                        $pickup_snapshot = $day_place_snapshots['from'] ?? array();
                        if ( ! $this->has_valid_place_snapshot( $pickup_snapshot ) ) {
                            $google_places_ready = false;
                            $missing_place_ids[] = $day_prefix . '.pickup_location';
                        }
                    }

                    if ( ! empty( $dropoff_location['label'] ?? '' ) ) {
                        $dropoff_snapshot = $day_place_snapshots['to'] ?? array();
                        if ( ! $this->has_valid_place_snapshot( $dropoff_snapshot ) ) {
                            $google_places_ready = false;
                            $missing_place_ids[] = $day_prefix . '.dropoff_location';
                        }
                    }
                }
            }

            $itinerary = is_array( $payload['itinerary'] ?? null ) ? $payload['itinerary'] : array();
            $trips = is_array( $itinerary['trips'] ?? null ) ? $itinerary['trips'] : array();
            foreach ( $trips as $trip_index => $trip ) {
                if ( ! is_array( $trip ) ) {
                    continue;
                }

                $trip_legs = is_array( $trip['legs'] ?? null ) ? $trip['legs'] : array();
                foreach ( $trip_legs as $trip_leg_index => $trip_leg ) {
                    if ( ! is_array( $trip_leg ) ) {
                        continue;
                    }

                    $trip_leg_prefix = 'itinerary.trips.' . $trip_index . '.legs.' . $trip_leg_index;
                    $trip_place_snapshots = is_array( $trip_leg['place_snapshots'] ?? null ) ? $trip_leg['place_snapshots'] : array();

                    $trip_from_snapshot = $trip_place_snapshots['from'] ?? array();
                    if ( empty( $trip_leg['from']['label'] ?? '' ) ) {
                        // Already covered by endpoint validation.
                    } elseif ( ! $this->has_valid_place_snapshot( $trip_from_snapshot ) ) {
                        $google_places_ready = false;
                        $missing_place_ids[] = $trip_leg_prefix . '.from';
                    }

                    $trip_to_snapshot = $trip_place_snapshots['to'] ?? array();
                    if ( empty( $trip_leg['to']['label'] ?? '' ) ) {
                        // Already covered by endpoint validation.
                    } elseif ( ! $this->has_valid_place_snapshot( $trip_to_snapshot ) ) {
                        $google_places_ready = false;
                        $missing_place_ids[] = $trip_leg_prefix . '.to';
                    }

                    $trip_stops = is_array( $trip_leg['stops'] ?? null ) ? $trip_leg['stops'] : array();
                    foreach ( $trip_stops as $trip_stop_index => $trip_stop ) {
                        if ( ! is_array( $trip_stop ) ) {
                            continue;
                        }

                        $trip_stop_location = $trip_stop['location'] ?? array();
                        if ( empty( $trip_stop_location['label'] ?? '' ) ) {
                            continue;
                        }

                        $trip_stop_snapshot = $trip_place_snapshots['stops'][ $trip_stop_index ] ?? array();
                        if ( ! $this->has_valid_place_snapshot( $trip_stop_snapshot ) ) {
                            $google_places_ready = false;
                            $missing_place_ids[] = $trip_leg_prefix . '.stops[' . $trip_stop_index . ']';
                        }
                    }
                }
            }

            // Only add error when enforcement is active AND snapshots are missing
            if ( $enforce_place_snapshots && ! $google_places_ready ) {
                $errors[] = $this->error( 'place_snapshots', 'missing_required', 'Google place snapshots are required for production quote-ready handoff. Origin and destination must be selected from the address dropdown.' );
            } elseif ( ! $google_places_ready ) {
                // Diagnostic warning when not enforcing (payload may have diagnostic flag set)
                $warnings[] = $this->warning( 'place_snapshots', 'not_quote_ready', 'Google place snapshots are not quote-ready. Origin/destination place IDs are required for pricing.' );
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

        /**
         * Check if a place snapshot has valid required fields for quote-ready handoff.
         * A valid snapshot must have: place_id, lat, lng, and provider = google_places.
         * Stale snapshots are considered invalid.
         *
         * @param mixed $snapshot
         * @return bool
         */
        private function has_valid_place_snapshot( $snapshot ) : bool {
            if ( ! is_array( $snapshot ) ) {
                return false;
            }

            $has_place_id = ! empty( $snapshot['place_id'] );
            $has_coords   = isset( $snapshot['lat'] ) && isset( $snapshot['lng'] ) && $snapshot['lat'] !== null && $snapshot['lng'] !== null;
            $is_google    = isset( $snapshot['provider'] ) && $snapshot['provider'] === 'google_places';
            $is_stale     = ! empty( $snapshot['stale'] );

            return $has_place_id && $has_coords && $is_google && ! $is_stale;
        }
    }
}
