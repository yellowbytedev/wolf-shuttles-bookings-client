<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Create localised variable for AJAX calls
* @type: PHP
* @status: published
* @created_by: 
* @created_at: 
* @updated_at: 2025-03-14 09:07:39
* @is_valid: 
* @updated_by: 
* @priority: 2
* @run_at: all
* @load_as_file: 
* @condition: {"status":"no","run_if":"assertive","items":[[]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
function my_add_ajax_url_inline_script() {
    // We can use an already registered script handle, for example 'jquery'
    $ajax_url = admin_url('admin-ajax.php');
    $inline_js = 'var myAjax = ' . wp_json_encode([
        'ajaxurl'       => esc_url($ajax_url),
        'providerNonce' => wp_create_nonce('wsb_provider_proxy'),
    ]) . ';';
    wp_add_inline_script('jquery', $inline_js);
}
add_action('wp_enqueue_scripts', 'my_add_ajax_url_inline_script');
