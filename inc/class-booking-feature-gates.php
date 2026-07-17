<?php

declare( strict_types=1 );

namespace WSB_Booking_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( '\WSB_Booking_Client\Booking_Feature_Gates' ) ) {
    final class Booking_Feature_Gates {
        private const KNOWN_GATES = [
            'enable_multi_day_charters',
            'enable_multi_trip_bookings',
            'enable_additional_stops',
            'enable_route_options_payload',
            'enable_route_alternatives_on_shuttles_page',
            'enable_google_places_required',
            'enable_drag_drop_itinerary_ordering',
            'enable_day_duplicate_delete',
            'enable_charter_poi_fields',
            'enable_debug_free_text_locations_local_only',
            'enable_real_handover',
        ];

        private const DEFAULTS = [
            'local' => [
                'enable_multi_day_charters'                => true,
                'enable_multi_trip_bookings'               => true,
                'enable_additional_stops'                  => true,
                'enable_route_options_payload'             => true,
                'enable_route_alternatives_on_shuttles_page' => false,
                'enable_google_places_required'            => true,
                'enable_drag_drop_itinerary_ordering'      => true,
                'enable_day_duplicate_delete'              => true,
                'enable_charter_poi_fields'                => true,
                'enable_debug_free_text_locations_local_only' => true,
                'enable_real_handover'                     => true,
            ],
            'staging' => [
                'enable_multi_day_charters'                => false,
                'enable_multi_trip_bookings'               => false,
                'enable_additional_stops'                  => true,
                'enable_route_options_payload'             => true,
                'enable_route_alternatives_on_shuttles_page' => false,
                'enable_google_places_required'            => true,
                'enable_drag_drop_itinerary_ordering'      => false,
                'enable_day_duplicate_delete'              => false,
                'enable_charter_poi_fields'                => false,
                'enable_debug_free_text_locations_local_only' => false,
                'enable_real_handover'                     => false,
            ],
            'production' => [
                'enable_multi_day_charters'                => false,
                'enable_multi_trip_bookings'               => false,
                'enable_additional_stops'                  => false,
                'enable_route_options_payload'             => true,
                'enable_route_alternatives_on_shuttles_page' => false,
                'enable_google_places_required'            => true,
                'enable_drag_drop_itinerary_ordering'      => false,
                'enable_day_duplicate_delete'              => false,
                'enable_charter_poi_fields'                => false,
                'enable_debug_free_text_locations_local_only' => false,
                'enable_real_handover'                     => false,
            ],
        ];

        /**
         * Return all sanitized feature gates for the current environment.
         *
         * @return array<string,bool>
         */
        public static function all(): array {
            $environment = self::resolve_environment();
            $gates       = self::defaults_for_environment( $environment );

            $sanitized = [];
            foreach ( $gates as $gate => $value ) {
                $sanitized[ $gate ] = self::to_bool( $value );
            }

            $filtered = apply_filters( 'ws_bookings_client_feature_gates', $sanitized, $environment );

            if ( ! is_array( $filtered ) ) {
                $filtered = $sanitized;
            }

            $final = [];
            foreach ( self::KNOWN_GATES as $gate ) {
                if ( array_key_exists( $gate, $filtered ) ) {
                    $final[ $gate ] = self::to_bool( $filtered[ $gate ] );
                }
            }

            return $final;
        }

        /**
         * Get a single gate value.
         *
         * @param string $gate
         * @param mixed  $default
         * @return bool
         */
        public static function get( string $gate, $default = false ): bool {
            if ( ! self::is_known_gate( $gate ) ) {
                return self::to_bool( $default );
            }

            $gates = self::all();

            return $gates[ $gate ] ?? self::to_bool( $default );
        }

        /**
         * Check whether a gate is enabled.
         *
         * @param string $gate
         * @return bool
         */
        public static function is_enabled( string $gate ): bool {
            return self::get( $gate, false );
        }

        /**
         * Return default gate values for the requested environment.
         *
         * @param string $environment
         * @return array<string,bool>
         */
        public static function defaults_for_environment( string $environment ): array {
            $environment = strtolower( $environment );

            if ( isset( self::DEFAULTS[ $environment ] ) ) {
                return self::DEFAULTS[ $environment ];
            }

            if ( in_array( $environment, [ 'local', 'development' ], true ) ) {
                return self::DEFAULTS['local'];
            }

            return self::DEFAULTS['production'];
        }

        /**
         * Return a sanitized frontend-safe config fragment.
         *
         * @return array<string,mixed>
         */
        public static function frontend_config(): array {
            $gates     = self::all();
            $env       = self::resolve_environment();
            $is_prod   = 'production' === $env;

            $config = [
                'feature_gates'    => $gates,
                'environment'      => $is_prod ? 'production' : $env,
                'google_places_required' => $is_prod ? self::get( 'enable_google_places_required', true ) : self::get( 'enable_google_places_required', false ),
            ];

            return $config;
        }

        /**
         * Assert that a gate key is known.
         *
         * @param string $gate
         * @return bool
         */
        public static function is_known_gate( string $gate ): bool {
            return in_array( $gate, self::KNOWN_GATES, true );
        }

        /**
         * Resolve the current WordPress environment label.
         *
         * @return string
         */
        private static function resolve_environment(): string {
            if ( defined( 'WP_ENVIRONMENT_TYPE' ) && is_string( WP_ENVIRONMENT_TYPE ) && WP_ENVIRONMENT_TYPE !== '' ) {
                return strtolower( WP_ENVIRONMENT_TYPE );
            }

            if ( function_exists( 'wp_get_environment_type' ) ) {
                $env = wp_get_environment_type();

                if ( is_string( $env ) && $env !== '' ) {
                    return strtolower( $env );
                }
            }

            return 'production';
        }

        /**
         * Coerce a value to bool.
         *
         * @param mixed $value
         * @return bool
         */
        private static function to_bool( $value ): bool {
            if ( is_bool( $value ) ) {
                return $value;
            }

            if ( is_string( $value ) ) {
                return in_array( strtolower( $value ), [ '1', 'true', 'yes', 'on' ], true );
            }

            return (bool) $value;
        }
    }
}
