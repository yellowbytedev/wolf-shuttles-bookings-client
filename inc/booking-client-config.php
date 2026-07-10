<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WSB_CLIENT_HANDOVER_MODE')) {
    define('WSB_CLIENT_HANDOVER_MODE', 'legacy_hash');
}

/**
 * Return the allowed handover mode values for this plugin.
 *
 * @return array<string>
 */
function wsb_client_handover_mode_allowed(): array {
    return (array) apply_filters('wsb_client_handover_mode_allowed', [
        'legacy_hash',
        'v2_token',
    ]);
}

/**
 * Return the active handover mode.
 *
 * @return string
 */
function wsb_client_handover_mode(): string {
    $mode = defined('WSB_CLIENT_HANDOVER_MODE') ? (string) WSB_CLIENT_HANDOVER_MODE : 'legacy_hash';
    $allowed = wsb_client_handover_mode_allowed();

    if (!in_array($mode, $allowed, true)) {
        $mode = 'legacy_hash';
    }

    return (string) apply_filters('wsb_client_handover_mode', $mode);
}

if ( ! defined( 'WSB_CLIENT_V2_HANDOVER_SECRET' ) ) {
    define( 'WSB_CLIENT_V2_HANDOVER_SECRET', '' );
}

/**
 * Return the v2 handover signing secret.
 *
 * In production this MUST be set to a real HMAC secret (not left empty).
 * For local development an empty value is accepted — the handover
 * envelope will be generated without a signature (integrity.signature
 * will be ""), allowing fixture tests and preview validation to run
 * without any real secret material.
 *
 * @return string
 */
function wsb_client_v2_handover_secret(): string {
	if ( defined( 'WSB_CLIENT_V2_HANDOVER_SECRET' ) && WSB_CLIENT_V2_HANDOVER_SECRET !== '' ) {
		return WSB_CLIENT_V2_HANDOVER_SECRET;
	}

	// Local/development environment fallback only.
	// Uses wp_get_environment_type() to ensure staging/production never fall back.
	if ( function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'local' ) {
		return 'local_v2_handover_secret';
	}

	return '';
}

/**
 * Return whether UI interaction features are enabled.
 *
 * This is currently disabled by default. When enabled, it allows
 * sortable/draggable adapters for future features like ordered stops,
 * charter day rows, and itinerary ordering.
 *
 * @return bool
 */
function wsb_client_ui_interactions_enabled(): bool {
    if ( ! defined( 'WSB_CLIENT_UI_INTERACTIONS_ENABLED' ) ) {
        return false;
    }

    return (bool) WSB_CLIENT_UI_INTERACTIONS_ENABLED;
}

if ( ! defined( 'WSB_CLIENT_BOOKING_SITE_URL' ) ) {
    define( 'WSB_CLIENT_BOOKING_SITE_URL', '' );
}
