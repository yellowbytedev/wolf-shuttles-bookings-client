<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: API call to Google Distance Matrix API
* @type: PHP
* @status: published
* @created_by: 
* @created_at: 
* @updated_at: 2026-02-04 12:23:53
* @is_valid: 
* @updated_by: 
* @priority: 10
* @run_at: frontend
* @load_as_file: 
* @load_in_block_editor: 
* @condition: {"status":"no","run_if":"assertive","items":[[]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
add_action('wp_ajax_calculate_distance', 'calculate_distance_callback'); // Logged-in users
add_action('wp_ajax_nopriv_calculate_distance', 'calculate_distance_callback'); // Guests 

add_action('wp_ajax_get_place_details', 'proxy_google_places_request'); // Logged-in users
add_action('wp_ajax_nopriv_get_place_details', 'proxy_google_places_request'); // Guests 

add_action('wp_ajax_get_place_geocode', 'fetch_google_geocode'); // Logged-in users
add_action('wp_ajax_nopriv_get_place_geocode', 'fetch_google_geocode'); // Guests 

add_action('wp_ajax_fetch_place_id_by_name', 'fetch_place_id_by_name');
add_action('wp_ajax_nopriv_fetch_place_id_by_name', 'fetch_place_id_by_name');

// Register AJAX callbacks for toll calculation.
add_action('wp_ajax_calculate_tolls', 'calculate_tolls');
add_action('wp_ajax_nopriv_calculate_tolls', 'calculate_tolls');

// function fetch_place_id_by_name() {
//     if (!isset($_GET['place_name'])) {
//         wp_send_json_error(['error' => 'Missing place_name parameter']);
//     }

//     $place_name = sanitize_text_field($_GET['place_name']);
//     $api_key = GOOGLE_API_KEY;

//     // Build Google Places Search API URL
//     $url = "https://maps.googleapis.com/maps/api/place/findplacefromtext/json?input={$place_name}&inputtype=textquery&fields=place_id,formatted_address&key={$api_key}";

//     // Fetch data from Google API
//     $response = wp_remote_get($url);

//     if (is_wp_error($response)) {
//         wp_send_json_error(['error' => 'Failed to fetch data from Google']);
//     }

//     $body = json_decode(wp_remote_retrieve_body($response), true);

//     if ($body['status'] !== 'OK' || empty($body['candidates'])) {
//         wp_send_json_error(['error' => 'Invalid response from Google', 'status' => $body['status']]);
//     }

//     $place = $body['candidates'][0];
//     $place_id = $place['place_id'];
//     $town = '';

//     // Extract town/locality if available
//     if (!empty($place['formatted_address'])) {
//         $town_parts = explode(',', $place['formatted_address']);
//         $town = trim($town_parts[0]); // Get the first part of the address as town
//     }

//     wp_send_json_success(['place_id' => $place_id, 'town' => $town]);
// }

function fetch_place_id_by_name() {
    if (!isset($_GET['place_name'])) {
        wp_send_json_error(['error' => 'Missing place_name parameter']);
    }

    $place_name = sanitize_text_field($_GET['place_name']);
    $api_key = GOOGLE_API_KEY;

    // Include address_components so we can extract both town and neighborhood
    $url = "https://maps.googleapis.com/maps/api/place/findplacefromtext/json?input={$place_name}&inputtype=textquery&fields=place_id,formatted_address,address_components&key={$api_key}";

    // Fetch data from Google API
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_send_json_error(['error' => 'Failed to fetch data from Google']);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($body['status'] !== 'OK' || empty($body['candidates'])) {
        wp_send_json_error(['error' => 'Invalid response from Google', 'status' => $body['status']]);
    }

    $place = $body['candidates'][0];
    $place_id = $place['place_id'];
    $town = '';
    $neighborhood = '';

    if (isset($place['address_components']) && is_array($place['address_components'])) {
        foreach ($place['address_components'] as $component) {
            // Extract town from the component with type "locality"
            if (in_array('locality', $component['types'])) {
                $town = $component['long_name'];
            }
            // Extract neighborhood from components with types "sublocality", "sublocality_level_2", or "neighborhood"
            if (
                in_array('sublocality', $component['types']) ||
                in_array('sublocality_level_2', $component['types']) ||
                in_array('neighborhood', $component['types'])
            ) {
                $neighborhood = $component['long_name'];
            }
        }
    }

    // Fallback: if town is still empty, use the first part of the formatted address.
    if (empty($town) && !empty($place['formatted_address'])) {
         $parts = explode(',', $place['formatted_address']);
         $town = trim($parts[0]);
    }

    wp_send_json_success([
        'place_id'    => $place_id,
        'town'        => $town,
        'neighborhood'=> $neighborhood
    ]);
}

function proxy_google_places_request() {
    $place_id = $_GET['place_id'] ?? null;

    if (!$place_id) {
        wp_send_json_error(['message' => 'Missing Place ID']);
    }

    $api_key = GOOGLE_API_KEY;
    $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id=$place_id&key=$api_key";

    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error fetching data']);
    }

    $body = wp_remote_retrieve_body($response);
    wp_send_json_success(json_decode($body));
}

function fetch_google_geocode() {
    if (!isset($_GET['lat']) || !isset($_GET['lng'])) {
        wp_send_json_error(['error' => 'Missing lat/lng parameters']);
    }

    $lat = sanitize_text_field($_GET['lat']);
    $lng = sanitize_text_field($_GET['lng']);

    $api_key = GOOGLE_API_KEY;
    $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$api_key}";

    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_send_json_error(['error' => 'Failed to fetch data from Google']);
    }

    if ($body['status'] !== 'OK' || empty($body['results'])) {
        wp_send_json_error(['error' => 'Invalid response from Google', 'status' => $body['status']]);
    }

    $place = $body['results'][0];
    $place_id = $place['place_id'];
    $town = '';

    // Extract town/locality
    foreach ($place['address_components'] as $component) {
        if (in_array('locality', $component['types'])) {
            $town = $component['long_name'];
            break;
        }
    }

     wp_send_json_success(['place_id' => $place_id, 'town' => $town]);
}

function calculate_distance_callback() {
    // // Log raw POST data for debugging
    // error_log('Raw POST data: ' . file_get_contents('php://input'));
    // error_log(print_r($_POST, true));
    
    // Check if origin and destination are set
    if (!isset($_POST['origin']) || !isset($_POST['destination'])) {
        wp_send_json_error(['error' => 'Invalid request. Missing origin or destination.']);
        return;
    }

    // Get the origin and destination from the POST request
    $origin = sanitize_text_field($_POST['origin']);
    $destination = sanitize_text_field($_POST['destination']);

    // Build the Google Distance Matrix API URL
    $api_key = GOOGLE_API_KEY; // Replace with your actual API key
    $endpoint = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    $params = [
        'origins' => 'place_id:' . $origin,
        'destinations' => 'place_id:' . $destination,
        'key' => $api_key,
    ];

    $url = $endpoint . '?' . http_build_query($params);

    // Make the API request using wp_remote_get
    $response = wp_remote_get($url);

    // Handle errors in the API request
    if (is_wp_error($response)) {
        wp_send_json_error(['error' => 'Failed to connect to Google Distance Matrix API.']);
        return;
    }

    // Parse the API response
    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($data['rows'][0]['elements'][0]['status']) && $data['rows'][0]['elements'][0]['status'] === 'OK') {
         // error_log($data);
        
        // $distance = $data['rows'][0]['elements'][0]['distance']['text'];
        // $duration = $data['rows'][0]['elements'][0]['duration']['text'];

        // Send the data back to the client
        // wp_send_json_success([
        //     'distance' => $distance,
        //     'duration' => $duration,
        // ]);

        $el = $data['rows'][0]['elements'][0];

        wp_send_json_success([
          'distance'    => $el['distance']['text'],
          'duration'    => $el['duration']['text'],
          'distance_m'  => (int) $el['distance']['value'],
          'duration_s'  => (int) $el['duration']['value'],
        ]);
    } else {
        wp_send_json_error(['error' => 'Google API response error: ' . $data['rows'][0]['elements'][0]['status']]);
    }

    // Always exit after sending the response
    wp_die();
}

// v8 version for HERE routing 
function calculate_tolls() {
  // ── 0) Inputs ──────────────────────────────────────────────────────────────
  if (empty($_POST['origin']) || empty($_POST['destination'])) {
    wp_send_json_error(['error' => 'Missing origin or destination parameters']); return;
  }
  $origin      = trim(sanitize_text_field($_POST['origin']));        // "lat,lng"
  $destination = trim(sanitize_text_field($_POST['destination']));

  if (!defined('HERE_MAPS_API_KEY') || !HERE_MAPS_API_KEY) {
    wp_send_json_error(['error' => 'HERE API key is missing']); return;
  }

  // ── 1) HERE Routing setup ──────────────────────────────────────────────────
  $endpoint = 'https://router.hereapi.com/v8/routes';
  $key      = HERE_MAPS_API_KEY;

  $base = [
    'transportMode' => 'car',
    'routingMode'   => 'fast',
    'units'         => 'metric',
    'origin'        => $origin,
    'destination'   => $destination,
    'return'        => 'summary,tolls,polyline', // ask for toll meta when it exists
    'currency'      => 'ZAR',
    'apikey'        => $key,
  ];

  $call = function(array $params) use ($endpoint){
    $url = $endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $res = wp_remote_get($url, ['timeout' => 20]);
    if (is_wp_error($res)) return null;
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
  };

  // ── 2) Normal route (may include toll metadata) ────────────────────────────
  $normal = $call($base);
  if (!$normal || empty($normal['routes'][0]['sections'][0]['summary'])) {
    wp_send_json_error(['error' => 'No routes found in HERE response']); return;
  }

  $sections = $normal['routes'][0]['sections'];
  $normSum  = $sections[0]['summary'];
  $normLen  = (int)($normSum['length'] ?? 0);   // meters
  $normDur  = (int)($normSum['duration'] ?? 0); // seconds

  // Parse any HERE-returned toll metadata (names/fees if available)
  $fees = [];
  $names = [];
  $total = 0.0;
  $currency = 'ZAR';

  foreach ($sections as $sec) {
    if (!empty($sec['tolls']['fees'])) {
      foreach ($sec['tolls']['fees'] as $fee) {
        $amount   = isset($fee['price']['amount']) ? (float)$fee['price']['amount'] : 0;
        $currency = $fee['price']['currency'] ?? $currency;
        $name     = $fee['name'] ?? null;
        if ($name && !in_array($name, $names, true)) $names[] = $name;
        if ($amount > 0) {
          $fees[] = [
            'name'     => $name,
            'amount'   => $amount,
            'currency' => $currency,
            'method'   => $fee['paymentMethod'] ?? null,
          ];
          $total += $amount;
        }
      }
    }
  }

  // ── 3) Infer "has tolls" by comparing with avoid[tollRoad] ────────────────
  $avoid = $call(array_merge($base, [
    'return' => 'summary,tolls,polyline,actions',  // smaller response
    'avoid[features]' => 'tollRoad'
  ]));
  $avoLen = (int)($avoid['routes'][0]['sections'][0]['summary']['length'] ?? 0);
  $avoDur = (int)($avoid['routes'][0]['sections'][0]['summary']['duration'] ?? 0);

  $hasTolls = false;
  if ($avoLen && $normLen) {
    $lenDelta = abs($avoLen - $normLen);  // meters
    $durDelta = abs($avoDur - $normDur);  // seconds
    $hasTolls = ($lenDelta > 500 || $durDelta > 60); // tweak if needed
  }
  if (!$hasTolls && !empty($names)) $hasTolls = true; // HERE provided names/fees → definitely tolls

  // ── 4)  NEW: probe acceptance helper (exact scope you asked for) ─────────
  // Place this helper *inside* calculate_tolls(), BEFORE the probe loop.
  $within_limit = function(int $probeLen, int $normLen, int $probeDur = 0, int $normDur = 0): bool {
      if (!$probeLen || !$normLen) return false;
    
      $lenDelta = $probeLen - $normLen; // meters
      $durDelta = $probeDur - $normDur; // seconds
    
      // Tighter: allow small noise, reject real detours
      $lenOk = $lenDelta <= max(2500, (int) round(0.03 * $normLen)); // 2.5km or 3%
      $durOk = $durDelta <= max(180,  (int) round(0.03 * $normDur)); // 3 min or 3%
    
      return ($lenOk && $durOk);
    };

  // ── 5) Fallback naming with plaza probes (uses the helper above) ──────────
  $probedNames = [];
  if ($hasTolls && empty($names)) {
    $probes = [
      [ 'name' => 'huguenot',      'via' => '-33.7427595,19.0196853' ], // N1 Huguenot Toll Plaza
      [ 'name' => 'chapmans_peak', 'via' => '-34.0631,18.37168'      ], // M6 Chapman’s Peak Toll
      [ 'name' => 'tsitsikamma',   'via' => '-33.9503839,23.6232311' ],
    ];
    foreach ($probes as $p) {
      $probe = $call(array_merge($base, [
        'return' => 'summary',
        'via'    => $p['via'] . '!passThrough=true'
      ]));
      $probeSum = $probe['routes'][0]['sections'][0]['summary'] ?? null;
      $pLen     = (int)($probeSum['length'] ?? 0);
      $pDur     = (int)($probeSum['duration'] ?? 0);
      if ($within_limit($pLen, $normLen, $pDur, $normDur)) {
        $probedNames[] = $p['name'];
      }
    }
  }

  // ── 6) Finalise and return ────────────────────────────────────────────────
  $finalNames = !empty($names) ? $names : $probedNames;

  wp_send_json_success([
    'has_tolls'  => $hasTolls,
    'toll_name'  => $finalNames[0] ?? null,   // keeps your JS happy
    'toll_names' => $finalNames,              // multi-name future-proofing
    'total'      => ['amount' => round($total, 2), 'currency' => $currency],
    'fees'       => $fees,
    'debug'      => [
      'origin'        => $origin,
      'destination'   => $destination,
      'normal_len_m'  => $normLen,
      'avoid_len_m'   => $avoLen,
      'here_names'    => $names,
      'probe_names'   => $probedNames,
      'road_debug' => array_slice(($normal['routes'][0]['sections'][0]['actions'] ?? []), 0, 15),
    ],
  ]);
}



