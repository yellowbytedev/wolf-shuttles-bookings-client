<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Register bricks helper functions for displaying traveler data
* @type: PHP
* @status: published
* @created_by: 1
* @created_at: 2025-08-21 13:12:45
* @updated_at: 2025-08-21 13:14:53
* @is_valid: 1
* @updated_by: 1
* @priority: 10
* @run_at: all
* @load_as_file: 
* @condition: {"status":"no","run_if":"assertive","items":[[]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
add_filter('bricks/code/echo_function_names', function($allowed = []) {
  // Only allow the specific helpers we just declared
  $allowed[] = 'ws_travelers_total';
  $allowed[] = 'ws_travelers_total_raw';
  $allowed[] = 'ws_travelers_as_of';
  return $allowed;
});

/**
 * Bricks helper function for reading traveler data
 */

function ws_travelers_total($formatted = true) {
    // pull the stored total that the site should display
    $n = (int) get_option('ws_traveler_display_total', 0);
    return $formatted ? number_format_i18n($n) : (string) $n;
}

// Returns raw digits (e.g., 35000)
function ws_travelers_total_raw() {
  return (string) (int) get_option('ws_traveler_display_total', 0);
}


/**
 * Optional: show the "as of" timestamp (site timezone)
 * Usage in Bricks: {echo:ws_travelers_as_of('M j, Y')}
 */
function ws_travelers_as_of($format = 'M j, Y') {
    $as_of = get_option('ws_traveler_as_of', '');
    if (!$as_of) return '';
    // convert stored MySQL datetime to timestamp in site TZ, then format
    $ts = strtotime($as_of);
    return $ts ? date_i18n($format, $ts) : '';
}
