<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: resave temp
* @type: PHP
* @status: draft
* @created_by: 1
* @created_at: 2026-01-16 08:51:42
* @updated_at: 2026-01-17 16:46:01
* @is_valid: 1
* @updated_by: 1
* @priority: 10
* @run_at: backend
* @load_as_file: 
* @condition: {"status":"no","run_if":"assertive","items":[[]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php

if ( ! defined('ABSPATH') ) exit;

/**
 * WS Bulk Normalize + Resave (Fluent Snippets)
 * - Normalizes 'location' and 'service' meta from serialized arrays -> single IDs
 * - Touches each post via wp_update_post
 * - Runs in batches through admin-post.php to avoid "Cannot load ..." menu-page issues
 */

define('WS_LS_BULK_PT', 'location_service'); // your CPT slug
define('WS_LS_BULK_BATCH_DEFAULT', 25);

// Meta keys to normalize (now both are Post Object single)
define('WS_LS_BULK_KEYS', ['location', 'service']);

add_action('admin_menu', function () {
  add_management_page(
    'Bulk Resave Location Services',
    'Bulk Resave Location Services',
    'manage_options',
    'ws-bulk-resave-location-services',
    'ws_ls_bulk_tools_page'
  );
});

function ws_ls_bulk_tools_page() {
  if ( ! current_user_can('manage_options') ) wp_die('Insufficient permissions.');

  $pt    = WS_LS_BULK_PT;
  $batch = isset($_GET['batch']) ? max(1, (int) $_GET['batch']) : WS_LS_BULK_BATCH_DEFAULT;

  $counts = wp_count_posts($pt);
  $total  = 0;
  foreach ((array)$counts as $v) $total += (int)$v;

  $run_url = admin_url('admin-post.php?action=ws_ls_bulk_normalize&pt=' . urlencode($pt) . '&batch=' . $batch . '&offset=0');

  echo '<div class="wrap">';
  echo '<h1>Bulk Resave Location Services</h1>';
  echo '<p><strong>Post type:</strong> <code>' . esc_html($pt) . '</code></p>';
  echo '<p><strong>Meta keys to normalize:</strong> <code>' . esc_html(implode(', ', WS_LS_BULK_KEYS)) . '</code></p>';
  echo '<p><strong>Total posts (approx):</strong> ' . esc_html($total) . '</p>';
  echo '<p><strong>Batch size:</strong> ' . esc_html($batch) . '</p>';
  echo '<p>This converts serialized arrays into a single Post Object ID for each key above, then resaves posts.</p>';
  echo '<p><a class="button button-primary" href="' . esc_url($run_url) . '">Run bulk normalize + resave</a></p>';
  echo '</div>';
}

function ws_ls_normalize_to_single_id($value) {
  // WP auto-unserializes post meta, so serialized arrays come through as PHP arrays.
  if (is_array($value)) {
    $value = reset($value);
  }

  // If it's a WP_Post object (rare in meta), convert to ID.
  if (is_object($value) && isset($value->ID)) {
    $value = $value->ID;
  }

  // Strings like "2169" should become int 2169.
  if (is_string($value) && is_numeric($value)) {
    $value = (int) $value;
  }

  // Ensure ints for IDs.
  if (is_numeric($value)) {
    return (int) $value;
  }

  return null;
}

add_action('admin_post_ws_ls_bulk_normalize', function () {
  if ( ! current_user_can('manage_options') ) wp_die('Insufficient permissions.');

  $pt     = isset($_GET['pt']) ? sanitize_key($_GET['pt']) : WS_LS_BULK_PT;
  $batch  = isset($_GET['batch']) ? max(1, (int) $_GET['batch']) : WS_LS_BULK_BATCH_DEFAULT;
  $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

  if ( ! post_type_exists($pt) ) wp_die('Post type not found: ' . esc_html($pt));

  $ids = get_posts([
    'post_type'        => $pt,
    'post_status'      => 'any',
    'posts_per_page'   => $batch,
    'offset'           => $offset,
    'orderby'          => 'ID',
    'order'            => 'ASC',
    'fields'           => 'ids',
    'suppress_filters' => true,
  ]);

  if ( empty($ids) ) {
    echo '<div class="wrap"><h1>Bulk Resave Location Services</h1>';
    echo '<p><strong>Done.</strong> No more posts to process.</p>';
    echo '<p>You can now disable this Fluent Snippet.</p>';
    echo '</div>';
    exit;
  }

  $stats = [
    'processed' => 0,
    'location_converted' => 0,
    'service_converted'  => 0,
    'resaved'   => 0,
    'errors'    => 0,
  ];

  foreach ($ids as $post_id) {
    $stats['processed']++;

    foreach (WS_LS_BULK_KEYS as $key) {
      $current = get_post_meta($post_id, $key, true);
      $normalized = ws_ls_normalize_to_single_id($current);

      if ($normalized !== null) {
        // Only update if it actually changes (prevents unnecessary writes)
        if ($current !== $normalized) {
          update_post_meta($post_id, $key, $normalized);

          if ($key === 'location') $stats['location_converted']++;
          if ($key === 'service')  $stats['service_converted']++;
        }
      }
    }

    $r = wp_update_post(['ID' => $post_id], true);
    if (is_wp_error($r)) {
      $stats['errors']++;
      continue;
    }
    $stats['resaved']++;
  }

  $next_offset = $offset + $batch;
  $next_url = admin_url('admin-post.php?action=ws_ls_bulk_normalize&pt=' . urlencode($pt) . '&batch=' . $batch . '&offset=' . $next_offset);

  echo '<div class="wrap"><h1>Batch complete</h1>';
  echo '<ul>';
  echo '<li>Processed: ' . esc_html($stats['processed']) . '</li>';
  echo '<li>Location converted: ' . esc_html($stats['location_converted']) . '</li>';
  echo '<li>Service converted: ' . esc_html($stats['service_converted']) . '</li>';
  echo '<li>Resaved: ' . esc_html($stats['resaved']) . '</li>';
  echo '<li>Errors: ' . esc_html($stats['errors']) . '</li>';
  echo '</ul>';
  echo '<p>Continuing to next batch…</p>';
  echo '<p><a class="button button-primary" href="' . esc_url($next_url) . '">Continue</a></p>';
  echo '<script>setTimeout(function(){ window.location.href = ' . json_encode($next_url) . '; }, 700);</script>';
  echo '</div>';
  exit;
});
