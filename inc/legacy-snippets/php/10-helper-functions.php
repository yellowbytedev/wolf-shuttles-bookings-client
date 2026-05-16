<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Helper functions
* @type: PHP
* @status: published
* @created_by: 
* @created_at: 
* @updated_at: 2025-02-22 21:23:25
* @is_valid: 
* @updated_by: 
* @priority: 5
* @run_at: all
* @load_as_file: 
* @condition: {"status":"no","run_if":"assertive","items":[[]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
function get_environment_url($path = '') {
    // Define staging and production URLs
    $base_url = (strpos($_SERVER['HTTP_HOST'], 'staging.') !== false) 
        ? 'https://staging.wolfshuttles.co.za' 
        : 'https://wolfshuttles.co.za/book-online';
    
    return trailingslashit($base_url) . ltrim($path, '/');
}

function get_booking_url($path = '') {
    // Define staging and production URLs
    $base_url = (strpos($_SERVER['HTTP_HOST'], 'staging.') !== false) 
        ? 'https://staging.bookings.wolfshuttles.co.za' 
        : 'https://bookings.wolfshuttles.co.za';
    
    return trailingslashit($base_url) . ltrim($path, '/');
}

function get_charter_url($path = '') {
    // Define staging and production URLs
    $base_url = (strpos($_SERVER['HTTP_HOST'], 'staging.') !== false) 
        ? 'https://staging.bookings.wolfshuttles.co.za' 
        : 'https://bookings.wolfshuttles.co.za';
    
    return trailingslashit($base_url) . ltrim($path, '/');
}
