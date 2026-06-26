<?php
/**
 * BookingPayload v2 normalizer scaffold for the Wolf Shuttles marketing plugin.
 *
 * Merge into: wp-content/plugins/ws-bookings-client/inc/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WSB_Client_Booking_Payload_V2_Normalizer' ) ) {
    final class WSB_Client_Booking_Payload_V2_Normalizer {
        /**
         * Normalize raw form/REST data into the canonical BookingPayload v2 shape.
         *
         * @param array<string,mixed> $raw Raw input.
         * @return array<string,mixed>
         */
        public function normalize( array $raw ) : array {
            $trip_type = $this->sanitize_enum( $raw['trip_type'] ?? 'one_way', array( 'one_way', 'return', 'charter' ), 'one_way' );
            $service_type = $this->sanitize_enum( $raw['service_type'] ?? 'city_transfer', array( 'city_transfer', 'airport_pickup', 'airport_dropoff', 'charter' ), 'city_transfer' );

            $payload = array(
                'schema_version' => '2.0',
                'source'         => sanitize_key( $raw['source'] ?? 'marketing_booking_builder' ),
                'service_type'   => $service_type,
                'trip_type'      => $trip_type,
                'customer'       => $this->normalize_customer( $raw['customer'] ?? array() ),
                'passengers'     => $this->positive_int( $raw['passengers'] ?? 1, 1 ),
                'baby_seats'     => $this->non_negative_int( $raw['baby_seats'] ?? 0 ),
                'check_in_bags'  => $this->non_negative_int( $raw['check_in_bags'] ?? $raw['luggage']['check_in_bags'] ?? 0 ),
                'carry_on_bags'  => $this->non_negative_int( $raw['carry_on_bags'] ?? $raw['luggage']['carry_on_bags'] ?? 0 ),
                'add_ons'        => array(
                    'trailer'          => $this->to_bool( $raw['trailer'] ?? ( $raw['add_ons']['trailer'] ?? false ) ),
                    'oversize_luggage' => $this->to_bool( $raw['oversize_luggage'] ?? ( $raw['add_ons']['oversize_luggage'] ?? false ) ),
                ),
                'legs'           => $this->normalize_legs( $raw, $trip_type ),
                'tracking'       => is_array( $raw['tracking'] ?? null ) ? $raw['tracking'] : array(),
                'meta'           => array(
                    'handover_mode' => sanitize_key( $raw['handover_mode'] ?? 'preview_only' ),
                    'created_at'    => gmdate( 'c' ),
                ),
            );

            /**
             * Filter normalized BookingPayload v2 before validation/handover.
             *
             * @param array<string,mixed> $payload
             * @param array<string,mixed> $raw
             */
            return apply_filters( 'wsb_client_booking_payload_v2_normalized', $payload, $raw );
        }

        /** @param mixed $value */
        private function to_bool( $value ) : bool {
            if ( is_bool( $value ) ) {
                return $value;
            }

            if ( is_string( $value ) ) {
                return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
            }

            return (bool) $value;
        }

        /** @param mixed $value */
        private function non_negative_int( $value ) : int {
            return max( 0, (int) $value );
        }

        /** @param mixed $value */
        private function positive_int( $value, int $fallback ) : int {
            $value = (int) $value;
            return $value > 0 ? $value : $fallback;
        }

        /**
         * @param mixed $value
         * @param array<int,string> $allowed
         */
        private function sanitize_enum( $value, array $allowed, string $fallback ) : string {
            $value = sanitize_key( (string) $value );
            return in_array( $value, $allowed, true ) ? $value : $fallback;
        }

        /** @param mixed $customer */
        private function normalize_customer( $customer ) : array {
            if ( ! is_array( $customer ) ) {
                $customer = array();
            }

            return array(
                'name'  => sanitize_text_field( $customer['name'] ?? '' ),
                'email' => sanitize_email( $customer['email'] ?? '' ),
                'phone' => sanitize_text_field( $customer['phone'] ?? '' ),
            );
        }

        /**
         * @param array<string,mixed> $raw
         * @return array<int,array<string,mixed>>
         */
        private function normalize_legs( array $raw, string $trip_type ) : array {
            if ( is_array( $raw['legs'] ?? null ) && ! empty( $raw['legs'] ) ) {
                return $this->normalize_legs_from_payload( $raw['legs'] );
            }

            $legs = array();
            $legs[] = $this->normalize_leg_from_flat_fields( 'outbound', $raw );

            if ( 'return' === $trip_type ) {
                $legs[] = $this->normalize_leg_from_flat_fields( 'return', $raw );
            }

            return $legs;
        }

        /**
         * @param array<int,mixed> $legs
         * @return array<int,array<string,mixed>>
         */
        private function normalize_legs_from_payload( array $legs ) : array {
            $normalized = array();

            foreach ( $legs as $raw_leg ) {
                if ( ! is_array( $raw_leg ) ) {
                    continue;
                }

                $type = $this->sanitize_enum( $raw_leg['type'] ?? 'outbound', array( 'outbound', 'return' ), 'outbound' );
                $from = $this->normalize_location( $raw_leg['from'] ?? array() );
                $to   = $this->normalize_location( $raw_leg['to'] ?? array() );

                $date = sanitize_text_field( $raw_leg['pickup_date'] ?? '' );
                $time = sanitize_text_field( $raw_leg['pickup_time'] ?? '' );
                $pickup_datetime = trim( $date . ' ' . $time );

                $stops = array();
                if ( is_array( $raw_leg['stops'] ?? null ) ) {
                    foreach ( $raw_leg['stops'] as $raw_stop ) {
                        if ( ! is_array( $raw_stop ) ) {
                            continue;
                        }

                        $stop_type = sanitize_key( $raw_stop['type'] ?? 'additional_stop' );
                        $location = $this->normalize_location( $raw_stop['location'] ?? $raw_stop );
                        if ( empty( $location['label'] ) ) {
                            continue;
                        }

                        $stops[] = array(
                            'type'     => $stop_type,
                            'location' => $location,
                        );
                    }
                }

                $normalized[] = array(
                    'type'            => $type,
                    'from'            => $from,
                    'to'              => $to,
                    'pickup_date'     => $date,
                    'pickup_time'     => $time,
                    'pickup_datetime' => $pickup_datetime,
                    'stops'           => $stops,
                    'route'           => is_array( $raw_leg['route'] ?? null ) ? $raw_leg['route'] : array(),
                );
            }

            return $normalized;
        }

        /**
         * @param array<string,mixed> $raw
         * @return array<string,mixed>
         */
        private function normalize_leg_from_flat_fields( string $type, array $raw ) : array {
            $prefix = 'return' === $type ? 'return_' : 'outbound_';

            $from = $this->normalize_location( $raw[ $prefix . 'from' ] ?? array() );
            $to   = $this->normalize_location( $raw[ $prefix . 'to' ] ?? array() );

            $date = sanitize_text_field( $raw[ $prefix . 'pickup_date' ] ?? '' );
            $time = sanitize_text_field( $raw[ $prefix . 'pickup_time' ] ?? '' );

            $stops = array();
            if ( 'outbound' === $type && $this->to_bool( $raw['additional_stop_enabled'] ?? false ) ) {
                $stop = $this->normalize_location( $raw['additional_stop'] ?? array() );
                if ( ! empty( $stop['label'] ) ) {
                    $stops[] = array(
                        'type'     => 'additional_stop',
                        'location' => $stop,
                    );
                }
            }

            return array(
                'type'            => $type,
                'from'            => $from,
                'to'              => $to,
                'pickup_date'     => $date,
                'pickup_time'     => $time,
                'pickup_datetime' => trim( $date . ' ' . $time ),
                'stops'           => $stops,
                'route'           => array(),
            );
        }

        /** @param mixed $location */
        private function normalize_location( $location ) : array {
            if ( is_string( $location ) ) {
                $location = array( 'label' => $location );
            }

            if ( ! is_array( $location ) ) {
                $location = array();
            }

            return array(
                'label'             => sanitize_text_field( $location['label'] ?? $location['value'] ?? '' ),
                'place_id'          => sanitize_text_field( $location['place_id'] ?? '' ),
                'lat'               => isset( $location['lat'] ) && '' !== $location['lat'] ? (float) $location['lat'] : null,
                'lng'               => isset( $location['lng'] ) && '' !== $location['lng'] ? (float) $location['lng'] : null,
                'formatted_address' => sanitize_text_field( $location['formatted_address'] ?? '' ),
            );
        }
    }
}
