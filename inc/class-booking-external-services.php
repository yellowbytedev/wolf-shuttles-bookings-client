<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WSB_Client_Booking_External_Services' ) ) {
    final class WSB_Client_Booking_External_Services {
        /**
         * Get route scaffold for BookingPayload v2.
         * Returns empty structure compatible with route schema.
         *
         * @return array<string,mixed>
         */
        public function get_route_scaffold(): array {
            return array(
                'provider'           => null,
                'selected_route_id'  => null,
                'selected_route_label' => null,
                'distance_meters'    => null,
                'duration_seconds'   => null,
                'polyline'           => null,
                'route_options'      => array(),
            );
        }

        /**
         * Get toll scaffold for BookingPayload v2.
         * Returns empty structure for future toll metadata.
         *
         * @return array<string,mixed>
         */
        public function get_toll_scaffold(): array {
            return array(
                'has_tolls'  => false,
                'toll_name'  => null,
                'toll_names' => array(),
                'total'      => array(
                    'amount'   => 0.0,
                    'currency' => 'ZAR',
                ),
                'fees'       => array(),
            );
        }

        /**
         * Get place scaffold for BookingPayload v2.
         * Returns empty structure for place details.
         *
         * @return array<string,mixed>
         */
        public function get_place_scaffold(): array {
            return array(
                'place_id'     => null,
                'name'         => null,
                'town'         => null,
                'neighborhood' => null,
                'lat'          => null,
                'lng'          => null,
                'formatted_address' => null,
            );
        }

        /**
         * Get handover scaffold for BookingPayload v2.
         * Returns empty structure for handover metadata.
         *
         * @return array<string,mixed>
         */
        public function get_handover_scaffold(): array {
            return array(
                'mode'               => 'dry_run',
                'handover_version'   => '2.0',
                'target_site'        => 'booking',
                'source_site'        => 'marketing',
                'request_id'         => null,
                'created_at'         => null,
                'expires_at'         => null,
                'signature'          => '',
            );
        }

        /**
         * Check if Google Maps API is enabled.
         *
         * @return bool
         */
        public function is_google_enabled(): bool {
            return (bool) apply_filters( 'wsb_client_external_google_enabled', false );
        }

        /**
         * Check if HERE Maps API is enabled.
         *
         * @return bool
         */
        public function is_here_enabled(): bool {
            return (bool) apply_filters( 'wsb_client_external_here_enabled', false );
        }

        /**
         * Check if live handover to booking-site is enabled.
         *
         * @return bool
         */
        public function is_handover_live(): bool {
            return (bool) apply_filters( 'wsb_client_handover_live', false );
        }

        /**
         * Get booking site config scaffold for Booking Builder constraints.
         * Returns safe defaults; no live fetch yet.
         *
         * @return array<string,mixed>
         */
        public function get_booking_site_config_scaffold(): array {
            return $this->get_default_booking_site_config();
        }

        /**
         * Get cached booking site config.
         * Currently returns defaults; caching to be implemented when endpoint is ready.
         *
         * @return array<string,mixed>
         */
        public function get_cached_booking_site_config(): array {
            // Future: check cache, return cached config, fallback to defaults
            return $this->get_default_booking_site_config();
        }

        /**
         * Get default booking site config values.
         * Safe defaults matching booking-site-config-contract.md.
         *
         * @return array<string,mixed>
         */
        public function get_default_booking_site_config(): array {
            return array(
                'version' => 1,
                'updated_at' => null,
                'source' => 'marketing_default',
                'lead_times' => array(
                    'transfer_min_notice_minutes' => 300,
                    'charter_min_notice_minutes' => 2880,
                    'max_advance_booking_days' => 365,
                ),
                'capacity' => array(
                    'max_passengers' => 13,
                    'max_baby_seats' => 13,
                    'max_check_in_bags' => 13,
                    'max_carry_on_bags' => 13,
                ),
                'picker' => array(
                    'time_step_minutes' => 5,
                    'date_format' => 'Y-m-d',
                ),
                'blockouts' => array(
                    'global_blockouts_supported' => true,
                    'vehicle_scoped_blockouts_supported' => true,
                    'vehicle_scoped_blockouts_affect_marketing_picker' => false,
                ),
                'cache' => array(
                    'status' => 'default',
                    'recommended_ttl_seconds' => 14400,
                    'fetched_at' => null,
                    'expires_at' => null,
                ),
            );
        }

        /**
         * Check if booking site config fetch is enabled.
         * Disabled by default; no live HTTP calls until endpoint is ready.
         *
         * @return bool
         */
        public function is_booking_site_config_fetch_enabled(): bool {
            return (bool) apply_filters( 'wsb_client_booking_site_config_fetch_enabled', false );
        }
    }
}

function wsb_client_external_services(): WSB_Client_Booking_External_Services {
    static $instance = null;
    if ( $instance === null ) {
        $instance = new WSB_Client_Booking_External_Services();
    }
    return $instance;
}
