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

    // Developer-only local fallback — do NOT use in production.
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        return 'local_v2_handover_preview_secret';
    }

    return '';
}
