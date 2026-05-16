<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Redirect book-online
* @type: PHP
* @status: published
* @created_by: 1
* @created_at: 2025-07-10 07:44:05
* @updated_at: 2025-07-10 07:46:14
* @is_valid: 1
* @updated_by: 1
* @priority: 10
* @run_at: frontend
* @load_as_file: 
* @condition: {"status":"yes","run_if":"assertive","items":[[{"source":["page","page_ids"],"operator":"in","value":["6"]}]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
// Redirect book-online slug to its Cape Town subpage
add_action('template_redirect', function() {

    $host = $_SERVER['HTTP_HOST'];
    // If "staging" is not found in the host, output the GTM code
    if (strpos($host, 'staging') === false) {
        // send a 301 redirect
        wp_redirect( home_url('/cape-town-shuttles/'), 301 );
        exit;
    }
});
