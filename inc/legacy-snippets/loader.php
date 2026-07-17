<?php
/**
 * Legacy Fluent Snippets loader.
 *
 * This file is intentionally loaded only when WSB_CLIENT_LOAD_LEGACY_SNIPPETS
 * is enabled in the main plugin file.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WSB_CLIENT_LOAD_LEGACY_SNIPPETS') || !WSB_CLIENT_LOAD_LEGACY_SNIPPETS) {
    return;
}

function wsb_client_legacy_snippet_path(string $relative_path): string {
    return __DIR__ . '/' . ltrim($relative_path, '/');
}

function wsb_client_legacy_require_snippet(string $relative_path): void {
    $path = wsb_client_legacy_snippet_path($relative_path);

    if (!is_readable($path)) {
        error_log('[WSB Client] Legacy snippet not readable: ' . $relative_path);
        return;
    }

    ob_start();
    require_once $path;
    $output = ob_get_clean();

    if (is_string($output) && trim($output) !== '') {
        echo $output;
    }
}

function wsb_client_legacy_print_asset(string $relative_path, string $type, string $id): void {
    $path = wsb_client_legacy_snippet_path($relative_path);

    if (!is_readable($path)) {
        error_log('[WSB Client] Legacy asset not readable: ' . $relative_path);
        return;
    }

    $contents = file_get_contents($path);

    if ($contents === false || trim($contents) === '') {
        return;
    }

    if ($type === 'css') {
        printf(
            "\n<style id=\"%s\">\n%s\n</style>\n",
            esc_attr($id),
            $contents
        );
        return;
    }

    printf(
        "\n<script id=\"%s\">\n%s\n</script>\n",
        esc_attr($id),
        $contents
    );
}

function wsb_client_legacy_matches_context(array $page_ids = [], array $post_types = []): bool {
    if ($page_ids && is_page($page_ids)) {
        return true;
    }

    if ($post_types && is_singular($post_types)) {
        return true;
    }

    return false;
}

foreach ([
    'php/10-helper-functions.php',
    'php/11-register-api-endpoint-for.php',
    'php/26-legacy-bricks-v2-handover-adapter.php',
    'php/15-submit-booking-form-and.php',
    'php/16-create-localised-variable-for.php',
    'php/19-bricks-builder-custom.php',
    'php/24-create-rest-endpoint-for.php',
    'php/25-register-bricks-helper-functions.php',
    'php/7-api-call-to-google.php',
] as $wsb_client_legacy_php_snippet) {
    wsb_client_legacy_require_snippet($wsb_client_legacy_php_snippet);
}
unset($wsb_client_legacy_php_snippet);

add_action('wp', function (): void {
    $conditional_snippets = [
        [
            'file'       => 'php/8-add-jquery.php',
            'page_ids'   => [6, 1958],
            'post_types' => ['post', 'location_service', 'bricks_template', 'landing-pages'],
        ],
        [
            'file'       => 'php/2-test-2.php',
            'page_ids'   => [6, 1958],
            'post_types' => ['post', 'location_service', 'bricks_template', 'landing-pages', 'location-service'],
        ],
        [
            'file'       => 'php/6-enqueue-google-maps-api.php',
            'page_ids'   => [6, 1958],
            'post_types' => ['post', 'location_service', 'bricks_template', 'landing-pages', 'location', 'location-service'],
        ],
        [
            'file'       => 'php/21-redirect-book-online.php',
            'page_ids'   => [6],
            'post_types' => [],
        ],
    ];

    foreach ($conditional_snippets as $snippet) {
        if (wsb_client_legacy_matches_context($snippet['page_ids'], $snippet['post_types'])) {
            wsb_client_legacy_require_snippet($snippet['file']);
        }
    }
}, 0);

add_action('wp_head', function (): void {
    wsb_client_legacy_print_asset(
        'css/29-form-tooltip-styling.css',
        'css',
        'wsb-legacy-29-form-tooltip-styling'
    );
}, 10);

add_action('wp_footer', function (): void {
    wsb_client_legacy_print_asset(
        'js/5-calculate-distance-v2.js',
        'js',
        'wsb-legacy-5-calculate-distance-v2'
    );
}, 1);

add_action('wp_footer', function (): void {
    wsb_client_legacy_print_asset(
        'js/28-add-tooltip-to-additional.js',
        'js',
        'wsb-legacy-28-add-tooltip-to-additional'
    );
}, 10);

add_action('wp_footer', function (): void {
    wsb_client_legacy_print_asset(
        'js/30-create-debugger.js',
        'js',
        'wsb-legacy-30-create-debugger'
    );
}, 10);

// Local test-only: bypass Google Places geocoding for browser testing
// ONLY loads in local environment with WSB_TEST_GEOCODING_BYPASS enabled
if (defined('WSB_TEST_GEOCODING_BYPASS') && WSB_TEST_GEOCODING_BYPASS) {
    add_action('wp_footer', function (): void {
        // Inject config to indicate test mode
        echo '<script>window.WSB_BOOKING_CLIENT_FORM = Object.assign({}, window.WSB_BOOKING_CLIENT_FORM || {}, { testGeocodingBypass: true });</script>';
        wsb_client_legacy_print_asset(
            'js/test-geocoding-bypass.js',
            'js',
            'wsb-test-geocoding-bypass'
        );
    }, 5);
}
