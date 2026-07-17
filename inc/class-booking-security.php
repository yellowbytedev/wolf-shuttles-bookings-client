<?php
/**
 * Shared security helpers for public legacy surfaces and operational logging.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WSB_Client_Security' ) ) {
	final class WSB_Client_Security {
		private const SENSITIVE_KEYS = array(
			'api_key', 'apikey', 'key', 'token', 'access_token', 'secret',
			'signature', 'authorization', 'hmac', 'password', 'booking_token',
			'email', 'phone', 'address', 'formatted_address', 'lat', 'lng',
			'hash', 'sig', 'ws_trip_sig', 'origin', 'destination', 'location',
			'coords', 'customer', 'tracking', 'name', 'request_uri',
			'request_id',
		);

		public static function redact_url( string $url ): string {
			$parts = wp_parse_url( $url );
			if ( ! is_array( $parts ) ) {
				return '[invalid_url]';
			}

			$query = array();
			if ( isset( $parts['query'] ) ) {
				parse_str( (string) $parts['query'], $query );
				$query = self::redact_value( $query );
			}

			$safe = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '[host]' );
			$safe .= isset( $parts['path'] ) ? $parts['path'] : '';
			return $query ? $safe . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : $safe;
		}

		public static function redact_value( $value, string $key = '' ) {
			if ( self::is_sensitive_key( $key ) ) {
				return '[REDACTED]';
			}
			if ( is_array( $value ) ) {
				$safe = array();
				foreach ( $value as $child_key => $child ) {
					$safe[ $child_key ] = self::redact_value( $child, (string) $child_key );
				}
				return $safe;
			}
			if ( is_string( $value ) ) {
				if ( preg_match( '#^https?://#i', $value ) ) {
					$value = self::redact_url( $value );
				}
				$value = preg_replace( '/\bBearer\s+[^\s,;]+/i', 'Bearer [REDACTED]', $value );
				return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 240 ) : substr( $value, 0, 240 );
			}
			return is_scalar( $value ) || null === $value ? $value : '[REDACTED]';
		}

		public static function log( string $event, array $context = array() ): void {
			if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
				return;
			}
			$event = preg_replace( '/[^a-z0-9_.-]/i', '_', $event );
			$context['event_id'] = substr( hash( 'sha256', $event . '|' . wp_json_encode( $context ) ), 0, 12 );
			error_log( '[WSB Client Security] ' . wp_json_encode( self::redact_value( $context ) + array( 'event' => $event ) ) );
		}

		public static function guard_provider_request( string $scope, int $limit = 40, int $window = 300 ) {
			$nonce = isset( $_REQUEST['_wsb_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wsb_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'wsb_provider_proxy' ) ) {
				return new WP_Error( 'wsb_provider_unauthorised', 'Request could not be authorised.', array( 'status' => 403 ) );
			}

			$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
			if ( $origin && 0 !== strpos( home_url(), $origin ) && 0 !== strpos( $origin, home_url() ) ) {
				return new WP_Error( 'wsb_provider_origin', 'Request origin is not allowed.', array( 'status' => 403 ) );
			}

			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
			$key = 'wsb_pr_' . substr( hash_hmac( 'sha256', $scope . '|' . $ip, wp_salt( 'nonce' ) ), 0, 32 );
			$count = (int) get_transient( $key );
			if ( $count >= (int) apply_filters( 'wsb_client_provider_rate_limit', $limit, $scope ) ) {
				return new WP_Error( 'wsb_provider_rate_limited', 'Too many requests. Please try again shortly.', array( 'status' => 429, 'retry_after' => $window ) );
			}
			set_transient( $key, $count + 1, $window );
			return true;
		}

		private static function is_sensitive_key( string $key ): bool {
			$key = strtolower( trim( $key ) );
			foreach ( self::SENSITIVE_KEYS as $sensitive ) {
				if ( $key === $sensitive || str_ends_with( $key, '_' . $sensitive ) ) {
					return true;
				}
			}
			return false;
		}
	}
}
