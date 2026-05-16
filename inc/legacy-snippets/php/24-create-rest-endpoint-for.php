<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Register rest endpoint for receiving traveler totals
* @type: PHP
* @status: published
* @created_by: 1
* @created_at: 2025-08-21 13:10:39
* @updated_at: 2025-08-21 13:32:13
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
 * WS Receiver – R1: Secure POST to update traveler totals
 * Endpoint: POST /wp-json/ws/v1/traveler-count
 * Headers:  X-WS-Timestamp, X-WS-Signature
 * Body:     {"total": <int>, "as_of": "YYYY-mm-dd HH:MM:SS"}  // as_of optional
 */

/** 1) Shared secret (move to wp-config.php when convenient) */
if (!defined('WS_TRAVELER_SHARED_SECRET')) {
    define('WS_TRAVELER_SHARED_SECRET', WS_TRAVELER_SHARED_SECRET_PRODUCTION);
}

/** 2) Baseline helper (re-uses what we set earlier) */
function ws_get_traveler_baseline(): int {
    $v = get_option('ws_traveler_baseline', null);
    if ($v === null || $v === '') {
        $v = 35000;
        add_option('ws_traveler_baseline', (int)$v, '', 'no');
    }
    return max(0, (int)$v);
}

/** 3) Signature verification */
function ws_verify_signed_request( WP_REST_Request $req ): bool {
    $ts  = $req->get_header('x-ws-timestamp');
    $sig = $req->get_header('x-ws-signature');
    if (!$ts || !$sig) return false;

    // Freshness: 5 minutes window
    if (abs(time() - (int)$ts) > 300) return false;

    $body = $req->get_body();
    $calc = hash_hmac('sha256', $ts . '.' . $body, WS_TRAVELER_SHARED_SECRET);

    // Use hash_equals to avoid timing attacks
    return hash_equals($calc, (string)$sig);
}

/** Register secure POST endpoint */
add_action('rest_api_init', function () {
    register_rest_route('ws/v1', '/traveler-count', [
        'methods'  => \WP_REST_Server::CREATABLE, // i.e., POST
        // 'methods'  => 'POST',
        'permission_callback' => 'ws_verify_signed_request',
        'callback' => function ( WP_REST_Request $req ) {

            // Parse & validate payload
            $data = $req->get_json_params();
            if (!is_array($data) || !isset($data['total'])) {
                return new WP_Error('bad_request', 'Missing "total" in JSON body', ['status' => 400]);
            }

            $shop_total = max(0, (int)$data['total']); // clamp negative to 0
            $as_of      = isset($data['as_of']) ? sanitize_text_field($data['as_of']) : current_time('mysql');

            // Store raw values (not autoloaded)
            update_option('ws_traveler_last_shop_total', $shop_total, false);
            update_option('ws_traveler_as_of', $as_of, false);

            // Compute display total (baseline + shop total) that Bricks reads
            $display = ws_get_traveler_baseline() + $shop_total;
            update_option('ws_traveler_display_total', (int)$display, false);

            // Respond with what we stored
            return rest_ensure_response([
                'ok'            => true,
                'baseline'      => ws_get_traveler_baseline(),
                'shop_total'    => $shop_total,
                'display_total' => (int)$display,
                'as_of'         => $as_of,
            ]);
        },
    ]);
});

/** Register secure GET endpoint for testing */
add_action('rest_api_init', function () {
  register_rest_route('ws/v1', '/travelers', [
    'methods'  => 'GET',
    'permission_callback' => '__return_true',
    'callback' => function () {
      return [
        'baseline'      => ws_get_traveler_baseline(),
        'display_total' => (int) get_option('ws_traveler_display_total', 0),
        'last_shop_total'=> (int) get_option('ws_traveler_last_shop_total', 0),
        'as_of'         => get_option('ws_traveler_as_of', ''),
      ];
    },
  ]);
});
