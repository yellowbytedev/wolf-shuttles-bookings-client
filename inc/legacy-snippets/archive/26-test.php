<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Add Traveler Count API endpoint
* @type: PHP
* @status: published
* @created_by: 1
* @created_at: 2025-08-21 13:16:22
* @updated_at: 2025-10-07 20:39:05
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
add_action('rest_api_init', function () {
  register_rest_route('ws/v1', '/routes', [
    'methods' => 'GET',
    'permission_callback' => '__return_true',
    'callback' => function () {
      $routes = rest_get_server()->get_routes();
      $target = '/ws/v1/traveler-count';
      $out = ['path' => $target, 'methods' => []];

      if (!empty($routes[$target])) {
        $names = [];
        foreach ($routes[$target] as $def) {
          $m = $def['methods'] ?? 0;

          // If WP gave us a bitmask integer, decode it:
          if (is_int($m)) {
            if (defined('\WP_REST_Server::METHOD_GET')    && ($m & \WP_REST_Server::METHOD_GET))    $names[] = 'GET';
            if (defined('\WP_REST_Server::METHOD_POST')   && ($m & \WP_REST_Server::METHOD_POST))   $names[] = 'POST';
            if (defined('\WP_REST_Server::METHOD_PUT')    && ($m & \WP_REST_Server::METHOD_PUT))    $names[] = 'PUT';
            if (defined('\WP_REST_Server::METHOD_PATCH')  && ($m & \WP_REST_Server::METHOD_PATCH))  $names[] = 'PATCH';
            if (defined('\WP_REST_Server::METHOD_DELETE') && ($m & \WP_REST_Server::METHOD_DELETE)) $names[] = 'DELETE';
          }
          // If it's a string or array, normalize that too:
          elseif (is_string($m)) {
            $names = array_merge($names, array_map('trim', explode(',', $m)));
          } elseif (is_array($m)) {
            $names = array_merge($names, $m);
          }
        }
        $out['methods'] = array_values(array_unique($names));
      }

      return $out;
    },
  ]);
});
