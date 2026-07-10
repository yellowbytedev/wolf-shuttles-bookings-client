<?php
/**
 * Legacy Bricks form V2 handover adapter.
 *
 * Posts legacy flat-field payload to V2 intake endpoint and returns booking_token.
 * Used when wsb_client_handover_mode() returns 'v2_token'.
 *
 * This file intentionally does NOT touch existing legacy flow when mode is 'legacy_hash'.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if V2 handover is enabled.
 *
 * @return bool
 */
function wsb_legacy_adapter_is_v2_enabled(): bool {
    if ( ! function_exists( 'wsb_client_handover_mode' ) ) {
        return false;
    }

    return 'v2_token' === wsb_client_handover_mode();
}

/**
 * Check if V2 handover is in strict mode (no fallback to legacy).
 *
 * @return bool
 */
function wsb_legacy_adapter_is_v2_strict_mode(): bool {
    return ( defined( 'WSB_CLIENT_V2_STRICT_MODE' ) && WSB_CLIENT_V2_STRICT_MODE );
}

/**
 * Normalize service_type from legacy values to V2 canonical.
 *
 * Legacy may have various values; V2 requires: airport_pickup, airport_dropoff, city_transfer, charter_hire.
 */
function wsb_legacy_adapter_normalize_service_type( array $data ): string {
    $legacy_service = strtolower( trim( (string) ( $data['serviceType'] ?? $data['tripType'] ?? '' ) ) );

    $map = [
        'airport pickup' => 'airport_pickup',
        'airport_pickup' => 'airport_pickup',
        'airport dropoff' => 'airport_dropoff',
        'airport_dropoff' => 'airport_dropoff',
        'charter' => 'charter_hire',
        'city transfer' => 'city_transfer',
        'city_transfer' => 'city_transfer',
    ];

    return $map[ $legacy_service ] ?? 'city_transfer';
}

/**
 * Normalize trip_type from legacy values to V2 canonical.
 *
 * Legacy uses: 'charter' or 'point_to_point_transfer'.
 * V2 uses: 'charter', 'one_way', 'return'.
 */
function wsb_legacy_adapter_normalize_trip_type( array $data ): string {
    $legacy_trip = (string) ( $data['tripType'] ?? '' );

    if ( 'charter' === $legacy_trip ) {
        return 'charter';
    }

    // point_to_point_transfer: check if return trip exists.
    if ( ! empty( $data['returnFrom'] ) || ! empty( $data['return_from'] ) || ! empty( $data['returnDate'] ) ) {
        return 'return';
    }

    return 'one_way';
}

/**
 * Map legacy camelCase keys to V2 snake_case keys.
 *
 * Legacy forms use: locationFrom, locationTo, pickupDate, pickupTime, dropOffTime, etc.
 * Normalizer expects: location_from, location_to, pickup_date, pickup_time, dropoff_time, etc.
 *
 * @param array<string,mixed> $data Legacy camelCase data.
 * @return array<string,mixed> Normalized snake_case data.
 */
function wsb_legacy_adapter_normalize_keys( array $data ): array {
    $mapping = [
        'locationFrom' => 'location_from',
        'locationTo' => 'location_to',
        'nameFrom' => 'name_from',
        'nameTo' => 'name_to',
        'pickupDate' => 'pickup_date',
        'pickupTime' => 'pickup_time',
        'dropOffTime' => 'drop_off_time',
        'returnFrom' => 'return_from',
        'returnTo' => 'return_to',
        'returnDate' => 'return_date',
        'returnTime' => 'return_time',
        'tripDistance' => 'trip_distance',
        'largeBags' => 'large_bags',
        'carryOnBags' => 'carry_on_bags',
        'trailerRequired' => 'trailer_required',
        'oversizeLuggage' => 'oversize_luggage',
        'charterInfo' => 'charter',
    ];

    $normalized = [];

    foreach ( $data as $key => $value ) {
        $new_key = $mapping[ $key ] ?? $key;
        $normalized[ $new_key ] = $value;
    }

    // Normalize trip_type and service_type.
    $normalized['trip_type'] = wsb_legacy_adapter_normalize_trip_type( $data );
    $normalized['service_type'] = wsb_legacy_adapter_normalize_service_type( $data );
    $normalized['service_group'] = ( 'charter' === $normalized['trip_type'] ) ? 'charter' : 'transfer';

    // Convert baby_seats: use babySeatCount numeric value, default to 0.
    $normalized['baby_seats'] = isset( $data['babySeatCount'] ) && is_numeric( $data['babySeatCount'] )
        ? (int) $data['babySeatCount']
        : 0;

    // Set schema version for V2 intake.
    if ( ! isset( $normalized['schema_version'] ) ) {
        $normalized['schema_version'] = '2.0';
    }

    // Ensure passengers exists.
    if ( ! isset( $normalized['passengers'] ) && isset( $data['passengers'] ) ) {
        $normalized['passengers'] = (int) $data['passengers'];
    }

    return $normalized;
}

/**
 * Send legacy flat-field payload to V2 intake endpoint.
 *
 * @param array<string,mixed> $legacy_data The legacy flat-field data structure.
 * @return array{ok:bool,booking_token?:string,redirect_url?:string,error?:string}
 */
function wsb_legacy_adapter_send_to_v2_intake( array $legacy_data ): array {
    // Resolve booking site URL.
    $booking_site_url = '';
    if ( defined( 'WSB_CLIENT_BOOKING_SITE_URL' ) && WSB_CLIENT_BOOKING_SITE_URL ) {
        $booking_site_url = WSB_CLIENT_BOOKING_SITE_URL;
    } elseif ( defined( 'WSB_BOOKING_BASE_URL' ) && WSB_BOOKING_BASE_URL ) {
        $booking_site_url = WSB_BOOKING_BASE_URL;
    } elseif ( function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'local' ) {
        $booking_site_url = 'https://bookings.wolfshuttles.local';
    }

    if ( empty( $booking_site_url ) ) {
        return [
            'ok'    => false,
            'error' => 'Booking site URL not configured',
        ];
    }

    // Normalize legacy keys to V2 format.
    $normalized_data = wsb_legacy_adapter_normalize_keys( $legacy_data );

    $endpoint = rtrim( $booking_site_url, '/' ) . '/wp-json/ws-bookings/v2/intake';

    $response = wp_remote_post(
        $endpoint,
        [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode( $normalized_data ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        return [
            'ok'    => false,
            'error' => 'HTTP error: ' . $response->get_error_message(),
        ];
    }

    $status_code = (int) wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( ! is_array( $data ) ) {
        return [
            'ok'    => false,
            'error' => 'Invalid response format',
        ];
    }

    if ( $status_code >= 200 && $status_code < 300 && ! empty( $data['success'] ) ) {
        return [
            'ok'           => true,
            'booking_token'  => $data['booking_token'] ?? '',
            'redirect_url'   => $data['redirect_url'] ?? '',
            'error'        => '',
        ];
    }

    return [
        'ok'    => false,
        'error' => $data['message'] ?? $data['error'] ?? 'V2 intake returned status ' . $status_code,
    ];
}

/**
 * Wrapper to replace send_booking_data() hash generation with V2 flow.
 * Returns either a hash (legacy) or redirect URL (V2).
 *
 * @param array<string,mixed> $data The legacy flat-field data.
 * @param string $secret_key The legacy HMAC secret.
 * @return array{ok:bool,redirect_url?:string,hash?:string,booking_token?:string,error?:string}
 */
function wsb_legacy_send_booking_data_adapter( array $data, string $secret_key ): array {
    // V2 flow: POST directly to intake endpoint
    if ( wsb_legacy_adapter_is_v2_enabled() ) {
        $result = wsb_legacy_adapter_send_to_v2_intake( $data );

        if ( $result['ok'] ) {
            return [
                'ok'           => true,
                'redirect_url'   => $result['redirect_url'] ?? '',
                'booking_token'  => $result['booking_token'] ?? '',
            ];
        }

        // Fall back to legacy hash on V2 failure
        error_log( '[WSB Legacy Adapter] V2 intake failed, falling back: ' . $result['error'] );
    }

    // Legacy flow (unchanged)
    $hash = hash_hmac( 'sha256', json_encode( $data ), $secret_key );

    return [
        'ok'   => true,
        'hash' => $hash,
        'redirect_url' => '', // Will be constructed by caller
    ];
}