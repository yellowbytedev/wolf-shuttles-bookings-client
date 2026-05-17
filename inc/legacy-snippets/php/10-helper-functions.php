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
* @updated_at: 2026-05-17 11:07:18
* @is_valid: 
* @updated_by: 
* @priority: 5
* @run_at: all
* @load_as_file: 
* @load_in_block_editor: 
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

function wsb_get_booking_base_url(): string {
    if (defined('WSB_BOOKING_BASE_URL') && WSB_BOOKING_BASE_URL) {
        return untrailingslashit(WSB_BOOKING_BASE_URL);
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';

    if (
        $host === 'wolfshuttles.local' ||
        $host === 'staging.wolfshuttles.local' ||
        str_ends_with($host, '.local')
    ) {
        return 'https://bookings.wolfshuttles.local';
    }

    if (strpos($host, 'staging.') !== false) {
        return 'https://staging.bookings.wolfshuttles.co.za';
    }

    return 'https://bookings.wolfshuttles.co.za';
}

function get_booking_url($path = '') {
    return trailingslashit(wsb_get_booking_base_url()) . ltrim((string) $path, '/');
}

function get_charter_url($path = '') {
    return get_booking_url($path);
}