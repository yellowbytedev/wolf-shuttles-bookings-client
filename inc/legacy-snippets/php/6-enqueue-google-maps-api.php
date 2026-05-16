<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Enqueue Google Maps Api for autocomplete functionality for forms
* @type: PHP
* @status: published
* @created_by: 
* @created_at: 
* @updated_at: 2026-02-25 19:44:11
* @is_valid: 
* @updated_by: 
* @priority: 10
* @run_at: all
* @load_as_file: 
* @load_in_block_editor: 
* @condition: {"status":"yes","run_if":"assertive","items":[[{"source":["page","page_ids"],"operator":"in","value":["6","1958"]}],[{"source":["page","post_type"],"operator":"in","value":["post","location_service","bricks_template","landing-pages"]}],[{"source":["page","post_type"],"operator":"in","value":["location","location-service"]}]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
/**
 * Enqueue Google Maps API for autocomplete functionality on specific pages.
 */

add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if ($handle === 'google-places-api') {
        return '<script src="' . esc_url($src) . '" async defer></script>';
    }
    return $tag;
}, 10, 3);


add_action('wp_enqueue_scripts', function () {
    // Define the Google Maps API key in a variable for easier management
    $google_maps_api_key = GOOGLE_API_KEY;

    $google_maps_api_url = sprintf(
    'https://maps.googleapis.com/maps/api/js?key=%s&libraries=places&callback=initGoogleAutocomplete',
    $google_maps_api_key
);
    
    // Enqueue the Google Maps API script
    wp_enqueue_script(
        'google-places-api',
        $google_maps_api_url,
        null,
        null,
        true
    );
});
