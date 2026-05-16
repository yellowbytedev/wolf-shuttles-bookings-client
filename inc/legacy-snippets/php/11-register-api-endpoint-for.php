<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Register API Endpoint for hash
* @type: PHP
* @status: published
* @created_by: 
* @created_at: 
* @updated_at: 2025-01-17 12:50:21
* @is_valid: 
* @updated_by: 
* @priority: 10
* @run_at: all
* @load_as_file: 
* @condition: {"status":"no","run_if":"assertive","items":[[]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
add_action('rest_api_init', function() {
    register_rest_route('booking/v1', '/data', [
        'methods' => 'POST',
        'callback' => function(WP_REST_Request $request) {
            $hash = $request->get_param('hash');
            $secret_key = BOOKING_HASH_SECRET;

            // Validate the hash and fetch the data
            $transient_key = 'booking_' . $hash;
            $data = get_transient($transient_key);

            if (!$data) {
                return new WP_Error('invalid_hash', 'Invalid or expired hash.', ['status' => 400]);
            }

            $expected_hash = hash_hmac('sha256', json_encode($data), $secret_key);

            if (!hash_equals($expected_hash, $hash)) {
                return new WP_Error('hash_mismatch', 'Hash mismatch.', ['status' => 400]);
            }

            // Return the data
            return rest_ensure_response($data);
        },
        'permission_callback' => '__return_true',
    ]);
});
