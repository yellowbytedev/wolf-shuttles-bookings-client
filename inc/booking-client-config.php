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
