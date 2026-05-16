<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: create a location_service post for EVERY location
* @type: PHP
* @status: draft
* @created_by: 1
* @created_at: 2026-01-12 15:24:17
* @updated_at: 2026-01-12 15:26:11
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
/**
 * One-time seeder: create a location_service post for EVERY location,
 * linked to ONE service: "Airport Transfers".
 *
 * Safe to re-run: it checks by deterministic slug and skips if it exists.
 * Remove after seeding.
 */

add_action('admin_notices', function () {
  if (!current_user_can('manage_options')) return;

  $base = admin_url('/');
  $service_slug = 'airport-transfers'; // <-- confirm this is your service post_name

  $dry_url = add_query_arg([
    'wolf_seed_location_services' => 1,
    'service' => $service_slug,
    'dry_run' => 1,
  ], $base);
  $dry_url = wp_nonce_url($dry_url, 'wolf_seed_location_services');

  $run_url = add_query_arg([
    'wolf_seed_location_services' => 1,
    'service' => $service_slug,
  ], $base);
  $run_url = wp_nonce_url($run_url, 'wolf_seed_location_services');

  echo '<div class="notice notice-info"><p><strong>Wolf: Seed Location Services</strong><br>';
  echo 'Creates <code>location_service</code> posts for all <code>location</code> posts, for service <code>' . esc_html($service_slug) . '</code>.</p><p>';
  echo '<a class="button" href="' . esc_url($dry_url) . '">Dry run (no changes)</a> ';
  echo '<a class="button button-primary" href="' . esc_url($run_url) . '">Run seeder</a>';
  echo '</p><p style="margin:0;">After it runs successfully, deactivate/remove this snippet.</p></div>';
});

add_action('admin_init', function () {

  if (empty($_GET['wolf_seed_location_services'])) return;
  if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');

  $nonce = $_GET['_wpnonce'] ?? '';
  if (!$nonce || !wp_verify_nonce($nonce, 'wolf_seed_location_services')) {
    wp_die('Invalid nonce.');
  }

  $dryRun = !empty($_GET['dry_run']);

  // CPT slugs (change ONLY if your CPT slugs differ)
  $pt_location        = 'location';
  $pt_service         = 'service';
  $pt_location_service = 'location_service';

  // Which service are we seeding for?
  $service_slug = sanitize_title($_GET['service'] ?? 'airport-transfers');

  $service = get_page_by_path($service_slug, OBJECT, $pt_service);
  if (!$service) {
    // fallback: try by title
    $service = get_page_by_title('Airport Transfers', OBJECT, $pt_service);
  }
  if (!$service || empty($service->ID)) {
    wp_die('Could not find the service post. Expected service slug: ' . esc_html($service_slug));
  }
  $service_id = (int) $service->ID;

  // Get all locations
  $locations = get_posts([
    'post_type'      => $pt_location,
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'all',
    'orderby'        => 'title',
    'order'          => 'ASC',
  ]);

  $created = 0;
  $skipped = 0;
  $errors  = 0;

  foreach ($locations as $loc) {
    $loc_id   = (int) $loc->ID;
    $loc_slug = $loc->post_name ?: sanitize_title($loc->post_title);

    // Deterministic slug so we can safely re-run:
    // {location-slug}-airport-transfers
    $ls_slug  = $loc_slug . '-' . $service_slug;

    // If already exists, skip
    $existing = get_page_by_path($ls_slug, OBJECT, $pt_location_service);
    if ($existing && !empty($existing->ID)) {
      $skipped++;
      continue;
    }

    if ($dryRun) {
      $created++;
      continue;
    }

    $postarr = [
      'post_type'   => $pt_location_service,
      'post_status' => 'publish',
      'post_title'  => $loc->post_title . ' — ' . $service->post_title,
      'post_name'   => $ls_slug,
    ];

    $ls_id = wp_insert_post($postarr, true);
    if (is_wp_error($ls_id) || !$ls_id) {
      $errors++;
      continue;
    }
    $ls_id = (int) $ls_id;

    // Set SCF relationship fields on the location_service post.
    // Your field names (from your screenshot): location, service
    if (class_exists('SCF')) {
      // Relationship fields typically store arrays of IDs
      \SCF::update($ls_id, 'location', [$loc_id]);
      \SCF::update($ls_id, 'service',  [$service_id]);
    } else {
      // Fallback: plain post meta
      update_post_meta($ls_id, 'location', [$loc_id]);
      update_post_meta($ls_id, 'service',  [$service_id]);
    }

    $created++;
  }

  if ($dryRun) {
    wp_die(
      'Dry run complete.<br>' .
      'Service: ' . esc_html($service_slug) . ' (ID ' . (int)$service_id . ')<br>' .
      'Locations found: ' . (int)count($locations) . '<br>' .
      'Would create: ' . (int)$created . '<br>' .
      'Would skip (already exists): ' . (int)$skipped
    );
  }

  wp_die(
    'Seeder complete.<br>' .
    'Service: ' . esc_html($service_slug) . ' (ID ' . (int)$service_id . ')<br>' .
    'Locations found: ' . (int)count($locations) . '<br>' .
    'Created: ' . (int)$created . '<br>' .
    'Skipped (already exists): ' . (int)$skipped . '<br>' .
    'Errors: ' . (int)$errors . '<br><br>' .
    'Now deactivate/remove this snippet.'
  );
});
