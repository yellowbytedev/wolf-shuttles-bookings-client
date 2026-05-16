<?php
// <Internal Doc Start>
/*
*
* @description: This snippet will contain all echo custom functions for custom data display on the website
* @tags: 
* @group: 
* @name: Bricks Builder - custom filters
* @type: PHP
* @status: published
* @created_by: 
* @created_at: 
* @updated_at: 2026-04-20 09:53:31
* @is_valid: 
* @updated_by: 
* @priority: 10
* @run_at: all
* @load_as_file: 
* @load_in_block_editor: 
* @condition: {"status":"no","run_if":"assertive","items":[[]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
if (!defined('ABSPATH')) exit;

// Register Bricks functions for custom filters
add_filter('bricks/code/echo_function_names', function() {
    return [
        'display_dynamic_kw_from_google_ads',
        'display_current_year',
        'wolf_get_service_usp',
        'ws_passenger_options',
        'ws_luggage_options',
        'ws_max_passengers',
    ];
});

function display_dynamic_kw_from_google_ads() {
    $utm_content = isset($_GET['utm_content']) ? urldecode($_GET['utm_content']) : '';

    if (empty($utm_content)) {
        $utm_content = 'Cape Town Shuttle Services';
    } elseif (stripos($utm_content, 'shuttle services') === false) {
        $utm_content = 'Cape Town Shuttle Services';
    } else {
        $utm_content = ucwords($utm_content);
    }

    return $utm_content;
}

function display_current_year() {
    $ts = function_exists('current_time') ? current_time('timestamp') : time();
    return date_i18n('Y', $ts);
}

/**
 * Read the max passenger count from SCF Options Page.
 *
 * IMPORTANT:
 * - 'company-profile' must match the SCF options page menu slug.
 * - 'max_passengers' must match the SCF field name.
 * - Falls back to 13 if nothing valid is stored.
 */
function ws_get_max_passengers_value() {
    $max = null;

    // Smart Custom Fields options page value
    if (class_exists('SCF') && method_exists('SCF', 'get_option_meta')) {
        $max = SCF::get_option_meta('company-profile', 'max_passengers');
    }

    // Optional fallback for ACF / SCF compatibility layers
    if (($max === null || $max === '' || $max === false) && function_exists('get_field')) {
        $max = get_field('max_passengers', 'option');
    }

    // Normalize arrays just in case
    if (is_array($max)) {
        $max = reset($max);
    }

    // Hard fallback
    if (!is_numeric($max)) {
        $max = 13;
    }

    $max = (int) $max;

    if ($max < 1) {
        $max = 1;
    }

    return $max;
}

function ws_build_number_options($min, $max) {
    $min = (int) $min;
    $max = (int) $max;

    if ($max < $min) {
        $max = $min;
    }

    $lines = [];

    for ($i = $min; $i <= $max; $i++) {
        $lines[] = $i . ':' . $i;
    }

    return implode("\n", $lines);
}

/**
 * Passengers: minimum 1
 */
function ws_passenger_options() {
    return ws_build_number_options(1, ws_get_max_passengers_value());
}

/**
 * Luggage: minimum 0
 */
function ws_luggage_options() {
    return ws_build_number_options(0, ws_get_max_passengers_value());
}

/**
 * Optional helper for output elsewhere in Bricks
 */
function ws_max_passengers() {
    return ws_get_max_passengers_value();
}