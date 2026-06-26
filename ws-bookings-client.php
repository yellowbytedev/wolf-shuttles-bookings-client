<?php
/**
 * Plugin Name: WSB Blockouts Client
 * Description: Marketing-site client for booking blockouts (fetch/cache JSON, expose helpers, enqueue frontend).
 * Version:     1.0.0
 * Author:      Wolf Shuttles
 */

if (!defined('ABSPATH')) exit;

define('WSB_CLIENT_VERSION', '1.0.0');

if (!defined('WSB_CLIENT_LOAD_LEGACY_SNIPPETS')) {
  define('WSB_CLIENT_LOAD_LEGACY_SNIPPETS', false);
}

if (WSB_CLIENT_LOAD_LEGACY_SNIPPETS) {
  require_once __DIR__ . '/inc/legacy-snippets/loader.php';
}

require_once __DIR__ . '/inc/booking-client.php';

/**
 * SOURCE URL:
 * - Default is the booking site's JSON cache (adjust domain if needed).
 * - You can override via filter 'wsb_client_source_url' or by defining constant earlier.
 */
if (!defined('WSB_CLIENT_SOURCE_URL')) {
  define('WSB_CLIENT_SOURCE_URL', 'https://bookings.wolfshuttles.co.za/wp-json/wsb/v1/blockouts');
}

/**
 * Return the current cached store (version, days, updated_at).
 */
function wsb_client_blockouts_store(): array {
  $opt = get_option('wsb_client_blockouts_store');
  $opt = is_array($opt) ? $opt : [];
  // defaults
  $opt += ['version'=>0,'days'=>[], 'updated_at'=>null, 'checked_at'=>0];
  return $opt;
}


/**
 * Fetch blockouts JSON from booking site and update the cache (option).
 * @param bool $force If true, bypasses existing cache and forces a network fetch.
 * @return array The latest store (version, days, updated_at)
 */
function wsb_client_fetch_blockouts(bool $force = false): array {
  $store = wsb_client_blockouts_store();

  $ttl = (int) apply_filters('wsb_client_ttl', 120); // 2 minutes
  $now = time();

  // fresh enough? return cache
  if (!$force && !empty($store['days']) && ($now - (int)$store['checked_at']) < $ttl) {
    return $store;
  }

  // fetch with cache-busting + no-cache headers
  $base = apply_filters('wsb_client_source_url', WSB_CLIENT_SOURCE_URL);
  $url  = add_query_arg('v', (string)$now, $base);
  $res  = wp_remote_get($url, [
    'timeout' => 10,
    'headers' => ['Cache-Control' => 'no-cache, max-age=0', 'Pragma' => 'no-cache'],
    'user-agent' => 'WSB-Client/'.WSB_CLIENT_VERSION,
  ]);

  if (is_wp_error($res)) {
    error_log('[WSB Client] fetch error: '.$res->get_error_message());
    // still stamp checked_at so we’ll try again after TTL
    $store['checked_at'] = $now;
    update_option('wsb_client_blockouts_store', $store, false);
    return $store;
  }

  $code = wp_remote_retrieve_response_code($res);
  if ((int)$code !== 200) {
    error_log('[WSB Client] fetch HTTP '.$code.' from '.$url);
    $store['checked_at'] = $now;
    update_option('wsb_client_blockouts_store', $store, false);
    return $store;
  }

  $json = json_decode(wp_remote_retrieve_body($res), true);
  if (!is_array($json) || !isset($json['days']) || !is_array($json['days'])) {
    error_log('[WSB Client] invalid JSON payload');
    $store['checked_at'] = $now;
    update_option('wsb_client_blockouts_store', $store, false);
    return $store;
  }

  $new = [
    'version'    => (int)($json['version'] ?? 0),
    'days'       => $json['days'],
    'updated_at' => $json['updated_at'] ?? current_time('c'),
    'checked_at' => $now,
  ];

  update_option('wsb_client_blockouts_store', $new, false);
  return $new;
}


/**
 * Convert "H:i" to minutes since midnight.
 */
function wsb_client_time_to_minutes(?string $t): ?int {
  $t = trim(strtolower((string)$t));
  if ($t === '') return null;
  if ($t === '24:00') return 24*60;
  if (!preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) return null;
  $h = (int)$m[1]; $mi = (int)$m[2];
  if ($h<0 || $h>24 || $mi<0 || $mi>59) return null;
  return $h*60 + $mi;
}

/**
 * Server-side helper to check if a specific ISO date + time is blocked.
 */
function wsb_client_is_blocked(string $ymd, string $time): bool {
  $store  = wsb_client_blockouts_store();
  $ranges = $store['days'][$ymd] ?? null;
  if (!$ranges || !is_array($ranges)) return false;

  $t = wsb_client_time_to_minutes($time);
  if ($t === null) return false;

  foreach ($ranges as $pair) {
    $s = wsb_client_time_to_minutes($pair[0] ?? '');
    $e = wsb_client_time_to_minutes($pair[1] ?? '');
    if ($s === null || $e === null) continue;
    if ($t >= $s && $t < $e) return true; // [start, end)
  }
  return false;
}

/**
 * Activation: schedule hourly refresh and prime the cache.
 */
register_activation_hook(__FILE__, function () {
  if (!wp_next_scheduled('wsb_client_refresh_blockouts')) {
    wp_schedule_event(time() + 30, 'hourly', 'wsb_client_refresh_blockouts');
  }
  wsb_client_fetch_blockouts(true);
});

/**
 * Deactivation: unschedule refresh.
 */
register_deactivation_hook(__FILE__, function () {
  $ts = wp_next_scheduled('wsb_client_refresh_blockouts');
  if ($ts) wp_unschedule_event($ts, 'wsb_client_refresh_blockouts');
});

/**
 * Cron hook to refresh the cache.
 */
add_action('wsb_client_refresh_blockouts', function () {
  wsb_client_fetch_blockouts(true);
});

/**
 * Enqueue and localize frontend JS (date + time behavior).
 * This plugin does NOT do server-side form validation; keep that in your Bricks snippet.
 */
add_action('wp_enqueue_scripts', function () {
  // Ensure we have some data in-memory
  $store = wsb_client_fetch_blockouts(false);

  // 1) Provide config object
  wp_register_script(
    'wsb-client-frontend',
    plugins_url('assets/js/blockouts-frontend.js', __FILE__), // your shared blocking logic JS
    [],
    WSB_CLIENT_VERSION,
    true
  );

  $selectors = apply_filters('wsb_client_selectors', [
    // Dates present on marketing site (adjust if needed)
    'date'       => 'input[name="pickup_date"], input[name="return_date"], input[name="charter_pickup_date"]',
    // Times (cover text/clock, native, select)
    'timeText'   => 'input[name$="_time"]',
    'timeInput'  => 'input[type="time"][name$="_time"]',
    'timeSelect' => 'select[name$="_time"]',
  ]);

  $cfg = [
    'days'      => $store['days'],
    'debug'     => (bool)apply_filters('wsb_client_debug', current_user_can('manage_options')),
    'selectors' => $selectors,
    'i18n'      => [
      'fullDay' => __('Unavailable (fully booked)','wsb'),
      'partial' => __('Partially unavailable','wsb'),
    ],
  ];

  wp_add_inline_script('wsb-client-frontend', 'window.WSB_BLOCKOUTS = '.wp_json_encode($cfg).';', 'before');
  wp_enqueue_script('wsb-client-frontend');

  // 2) Datepicker (jQuery UI) + your datepicker initializer for marketing site
  wp_enqueue_script('jquery-ui-datepicker');
  wp_enqueue_style('jquery-ui-theme', 'https://code.jquery.com/ui/1.13.3/themes/base/jquery-ui.css', [], '1.13.3');

  wp_enqueue_script(
    'wsb-client-datepickers',
    plugins_url('assets/js/datepickers.js', __FILE__), // your marketing-site datepicker JS
    ['jquery', 'jquery-ui-datepicker', 'wsb-client-frontend'],
    WSB_CLIENT_VERSION,
    true
  );
});
