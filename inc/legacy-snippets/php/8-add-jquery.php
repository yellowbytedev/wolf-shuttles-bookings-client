<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Add Jquery
* @type: PHP
* @status: published
* @created_by: 
* @created_at: 
* @updated_at: 2026-01-26 10:17:42
* @is_valid: 
* @updated_by: 
* @priority: 1
* @run_at: all
* @load_as_file: 
* @load_in_block_editor: 
* @condition: {"status":"yes","run_if":"assertive","items":[[{"source":["page","page_ids"],"operator":"in","value":["6","1958"]}],[{"source":["page","post_type"],"operator":"in","value":["post","location_service","bricks_template","landing-pages"]}]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
function enqueue_jquery_script() {
   // Enqueue jQuery (WordPress default)
    wp_enqueue_script('jquery');

    // Enqueue jQuery UI (for Datepicker)
    wp_enqueue_script('jquery-ui-datepicker');
        
    // Optional: Enqueue jQuery UI CSS
   wp_enqueue_style(
        'jquery-ui-css',
        'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css',
        [],
        '1.13.2'
    );

}
add_action('wp_enqueue_scripts', 'enqueue_jquery_script');
