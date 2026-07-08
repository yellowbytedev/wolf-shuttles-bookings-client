<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Submit booking form and redirect to booking system
* @type: PHP
* @status: published
* @created_by: 
* @created_at: 
* @updated_at: 2026-05-12 12:47:40
* @is_valid: 
* @updated_by: 
* @priority: 10
* @run_at: all
* @load_as_file: 
* @load_in_block_editor: 
* @condition: {"status":"no","run_if":"assertive","items":[[]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
<?php
add_action( 'bricks/form/custom_action', 'custom_booking_form_action', 10, 1 );

add_filter( 'bricks/form/validate', 'validate_custom_form', 10, 2 );

function wsb_ms_ensure_blockouts_loaded(): void {
  if (!function_exists('wsb_client_blockouts_store')) return;
  $store = wsb_client_blockouts_store();
  if (empty($store['days']) && function_exists('wsb_client_fetch_blockouts')) {
    // force a fresh pull from the booking site
    wsb_client_fetch_blockouts(true);
  }
}

// Validate an array of [label,date,time] triplets against the cached blockouts.
// Returns an array of error strings (empty if OK).
function wsb_ms_check_blockouts(array $pairs): array {
  if (!function_exists('wsb_client_is_blocked')) {
    // Plugin not active → fail open (or return a warning if you prefer)
    return [];
  }
  $errs = [];
  foreach ($pairs as $p) {
    $label = $p['label'] ?? 'Time';
    $date  = wsb_ms_to_iso($p['date'] ?? '');
    $time  = trim((string)($p['time'] ?? ''));
    if ($date === '' || $time === '') continue; // skip empty fields
    if (wsb_client_is_blocked($date, $time)) {
      $errs[] = sprintf('%s is unavailable on %s at %s. Please choose a different time.', $label, $date, $time);
    }
  }
  return $errs;
}

// dd/mm/YYYY → YYYY-mm-dd
function wsb_ms_to_iso(?string $d): string {
  $d = trim((string)$d);
  if ($d === '') return '';
  if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $d, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
  return $d;
}

// Minutes since midnight (supports "13:30", "1:30 pm", "1330", etc.)
function wsb_ms_time_to_minutes(?string $t): ?int {
  $t = trim(strtolower((string)$t));
  if ($t === '') return null;
  if (!preg_match('/^(\d{1,2})(?::?(\d{2}))?\s*(am|pm)?$/', $t, $m)) return null;
  $h=(int)$m[1]; $mi=isset($m[2])?(int)$m[2]:0; $ap=$m[3]??'';
  if ($ap==='pm' && $h<12) $h+=12; if ($ap==='am' && $h===12) $h=0;
  return $h*60 + $mi;
}

/**
 * Get booking payload from POST (JSON).
 */
function wsb_get_booking_payload_from_post() : array {
    if ( empty($_POST['booking_payload_json']) ) {
        error_log('[WSB] No booking_payload_json in POST');
        return [];
    }
    $raw = wp_unslash( $_POST['booking_payload_json'] );
    $data = json_decode( $raw, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        error_log('[WSB] booking_payload_json decode error: ' . json_last_error_msg());
        return [];
    }
    return is_array($data) ? $data : [];
}

/**
 * POI → canonical reference point (lat,lng).
 * IMPORTANT: Replace placeholders with real coords.
 */
function wsb_ms_poi_reference_map(): array {
  return [
       'City'                      => ['type' => 'latlng', 'value' => '-33.9249,18.4241', 'label' => 'Waterfront/Bo-Kaap/Table Mountain'],
    'Cape Point'                => ['type' => 'latlng', 'value' => '-34.3568,18.4953', 'label' => 'Cape Point/Boulders Beach/Chapmans Peak'],
    'Winelands'                 => ['type' => 'latlng', 'value' => '-33.9321,18.8602', 'label' => 'Stellenbosch/Somerset West - Winelands)'],
    'Franschhoek/Paarl - Winelands'                 => ['type' => 'latlng', 'value' => '-33.8241,18.9274', 'label' => 'Franschhoek/Paarl - Winelands'],
  'Constantia Winelands'                 => ['type' => 'latlng', 'value' => '-34.0314,18.4183', 'label' => 'Constantia - Winelands'],
    'Durbanville Winelands'                 => ['type' => 'latlng', 'value' => '-33.8262,18.6418', 'label' => 'Durbanville - Winelands'],
    'Riebeek Kasteel - Winelands'                 => ['type' => 'latlng', 'value' => '-33.3845,18.8995', 'label' => 'Riebeek Kasteel - Winelands'],
  'Wellington - Winelands'                 => ['type' => 'latlng', 'value' => '-33.6398,19.0112', 'label' => 'Wellington - Winelands'],
   'Robertson - Winelands'                 => ['type' => 'latlng', 'value' => '-33.8000,19.8833', 'label' => 'Robertson - Winelands'],
    'Atlantis Sand Dunes'       => ['type' => 'latlng', 'value' => '-33.5650,18.4620', 'label' => 'Atlantis'],
    'Langebaan'                 => ['type' => 'latlng', 'value' => '-33.0926,18.0345', 'label' => 'Langebaan'],
    'Hermanus - Whale Watching' => ['type' => 'latlng', 'value' => '-34.4187,19.2345', 'label' => 'Hermanus'],
    'Hermanus - Hemel en Aarde' => ['type' => 'latlng', 'value' => '-34.3990,19.2440', 'label' => 'Hemel-en-Aarde'],
    'West Coast Nature Reserve' => ['type' => 'latlng', 'value' => '-33.2040,18.1110', 'label' => 'West Coast Nature Reserve'],
    'Gansbaai'                  => ['type' => 'latlng', 'value' => '-34.5809,19.3517', 'label' => 'Gansbaai'],
    'Aquila Safari'             => ['type' => 'latlng', 'value' => '-33.3522,19.9362', 'label' => 'Aquila Safari'],
    'Ceres Nature Reserve'      => ['type' => 'latlng', 'value' => '-33.3689,19.3180', 'label' => 'Ceres'],
    'Cape Agulhas'              => ['type' => 'latlng', 'value' => '-34.8299,20.0006', 'label' => 'Cape Agulhas'],
  ];
}

/**
 * Normalize coordinates into "lat,lng".
 * Accepts:
 *  - array(['lat'=>..,'lng'=>..]) or ['latitude','longitude']
 *  - JSON string {"lat":...,"lng":...}
 *  - string "-33.9,18.4"
 */
function wsb_ms_normalize_latlng($val): string {
  if (is_array($val)) {
    $lat = $val['lat'] ?? $val['latitude'] ?? null;
    $lng = $val['lng'] ?? $val['longitude'] ?? null;
    if ($lat !== null && $lng !== null) {
      return sprintf('%.7f,%.7f', (float)$lat, (float)$lng);
    }
    return '';
  }

  $s = trim((string)$val);
  if ($s === '') return '';

  // JSON?
  if ($s[0] === '{') {
    $j = json_decode($s, true);
    if (is_array($j)) {
      $lat = $j['lat'] ?? $j['latitude'] ?? null;
      $lng = $j['lng'] ?? $j['longitude'] ?? null;
      if ($lat !== null && $lng !== null) {
        return sprintf('%.7f,%.7f', (float)$lat, (float)$lng);
      }
    }
    return '';
  }

  // "lat,lng"
  if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $s, $m)) {
    return sprintf('%.7f,%.7f', (float)$m[1], (float)$m[2]);
  }

  return '';
}

/**
 * For "Other", fall back to destination coords from the form.
 */
function wsb_ms_get_poi_reference(string $selected_poi, array $form_fields): array {
  $selected_poi = trim($selected_poi);

  error_log('[WSB] poi_selected=1');
  $map = apply_filters('wsb_ms_poi_reference_map', wsb_ms_poi_reference_map());

  if ($selected_poi === 'Other') {
    $dest_coords = wsb_ms_normalize_latlng($form_fields['charter_destination_coords'] ?? '');
    if ($dest_coords !== '') {
      return ['type' => 'latlng', 'value' => $dest_coords, 'label' => 'Other (Destination)'];
    }
    return [];
  }

  $ref = $map[$selected_poi] ?? [];
  return is_array($ref) ? $ref : [];
}

/**
 * Distance Matrix accepts "lat,lng" directly.
 */
function wsb_ms_dm_loc(array $ref): string {
  $type = $ref['type'] ?? '';
  $val  = trim((string)($ref['value'] ?? ''));
  if ($val === '') return '';

  if ($type === 'latlng') return $val;

  // keep place_id support as optional/backward compatible
  if ($type === 'place_id') return 'place_id:' . $val;

  return '';
}

/**
 * Call Google Distance Matrix and return structured result.
 * Cached by (origin|dest) for 12 hours.
 */
function wsb_ms_distance_matrix(array $origin_ref, array $dest_ref): ?array {
  if (!defined('GOOGLE_API_KEY') || !GOOGLE_API_KEY) {
    error_log('[WSB][dm] GOOGLE_API_KEY not defined');
    return null;
  }

  $origin = wsb_ms_dm_loc($origin_ref);
  $dest   = wsb_ms_dm_loc($dest_ref);

  if ($origin === '' || $dest === '') return null;

  $cache_key = 'wsb_dm_' . md5($origin . '|' . $dest);
  $cached = get_transient($cache_key);
  if ($cached !== false) return is_array($cached) ? $cached : null;

  $endpoint = 'https://maps.googleapis.com/maps/api/distancematrix/json';
  $params = [
    'origins'      => $origin,
    'destinations' => $dest,
    'key'          => GOOGLE_API_KEY,
    'units'        => 'metric',
  ];

  $url = $endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

  $res = wp_remote_get($url, ['timeout' => 15]);
  if (is_wp_error($res)) {
    error_log('[WSB][dm] wp_remote_get error: ' . $res->get_error_message());
    return null;
  }

  $json = json_decode(wp_remote_retrieve_body($res), true);
  if (!is_array($json)) {
    error_log('[WSB][dm] Invalid JSON response');
    return null;
  }

  $el = $json['rows'][0]['elements'][0] ?? null;
  $st = is_array($el) ? ($el['status'] ?? '') : '';

  if ($st !== 'OK') {
    error_log('[WSB][dm] element status not OK: ' . $st);
    return null;
  }

  $meters  = (int) ($el['distance']['value'] ?? 0);
  $seconds = (int) ($el['duration']['value'] ?? 0);

  $out = [
    'meters'        => $meters,
    'km'            => $meters > 0 ? round($meters / 1000, 1) : 0.0,
    'duration_sec'  => $seconds,
    'duration_min'  => $seconds > 0 ? (int) round($seconds / 60) : 0,
    'distance_text' => (string) ($el['distance']['text'] ?? ''),
    'duration_text' => (string) ($el['duration']['text'] ?? ''),
  ];

  set_transient($cache_key, $out, 12 * HOUR_IN_SECONDS);
  return $out;
}

/**
 * Convert Bricks select values into a safe non-negative integer.
 *
 * Handles:
 * - "0", "1", "2"
 * - integer values
 * - arrays from Bricks
 * - empty/null values
 */
function wsb_ms_non_negative_int($value): int {
    if (is_array($value)) {
        $value = reset($value);
    }

    $value = trim((string) $value);

    if ($value === '') {
        return 0;
    }

    if (!is_numeric($value)) {
        return 0;
    }

    return max(0, (int) $value);
}

/**
 * Baby seat count for the Ride / point-to-point form.
 *
 * Confirmed from debug:
 * - semantic key: baby-seats
 * - Bricks field ID: uhztrm
 */
function wsb_ms_get_ride_baby_seat_count($form, array $form_fields = []): int {
    $value = null;

    if (isset($form_fields['baby-seats'])) {
        $value = $form_fields['baby-seats'];
    } elseif (isset($form_fields['form-field-uhztrm'])) {
        $value = $form_fields['form-field-uhztrm'];
    } elseif (method_exists($form, 'get_field_value')) {
        $value = $form->get_field_value('uhztrm');
    }

    return wsb_ms_non_negative_int($value);
}

/**
 * Baby seat count for the Charter form.
 *
 */
function wsb_ms_get_charter_baby_seat_count($form, array $form_fields = []): int {
    $value = null;

    $possible_keys = [
        'charter_baby_seats',
        'charter_baby-seats',
        'baby-seats',
        'baby_seats',
    ];

    foreach ($possible_keys as $key) {
        if (isset($form_fields[$key])) {
            $value = $form_fields[$key];
            break;
        }
    }

    return wsb_ms_non_negative_int($value);
}


function process_charter_data($form, $form_fields, array $raw = [] ) {

    $wedding_transfer_distance_threshold = 70;
    $charter_distances = $form_fields['charter_distances']; 
    $distance_array = explode(',', $charter_distances);
    $dispatch_distance = $distance_array[0];

    $ineligible_trip = $form_fields['charter_ineligible_trip'];

    $additional = (array) $form->get_field_value('yejphj'); // Force the value to be an array

    $baby_seat_count = wsb_ms_get_charter_baby_seat_count($form, $form_fields);
    
    $pickup_date = $form_fields['charter_pickup_date'];
    $pickup_time = $form_fields['charter_pickup_time'];
    $drop_off_time = $form_fields['charter_drop-off_time'];
    
    // Calculate trip duration.
    $duration = calculate_duration($pickup_date, $pickup_time, $drop_off_time);
    $formatted_duration = $duration . ' hours';

     // POI distance mapping.
    $poi_distance_mapping = [
        // 'Dinner Transfer'            => 100,
        'Wedding'                    => 150,  // Wedding Parties
        'Cape Point'                 => 200,  // Cape Point/Boulders Beach
        'Winelands'                  => 200,  // Winelands - Stellenbosch/Franschhoek/Paarl
        'City'                       => 200,  // City - Table Mountain/Bo-Kaap/Waterfront
        'Atlantis Sand Dunes'        => 200,  // Atlantis Sand Dunes/Quad Biking
        'Langebaan'                  => 450,
        'Hermanus - Whale Watching'  => 450,  // Hermanus - Whale Watching
        'Hermanus - Hemel en Aarde'  => 450,  // Hermanus - Hemel en Aarde
        'West Coast Nature Reserve'  => 450,
        'Gansbaai'                   => 450,  // Gansbaai - Shark Cage Diving
        'Aquila Safari'              => 450,  // Aquila Safari & Spa Game Reserve
        'Ceres Nature Reserve'       => 450,
        'Cape Agulhas'               => 450,  // Cape Agulhas/La' Agulhas
        'Garden Route'               => 0,    //Garden Route - Knysna/Plettenberg Bay/Tsitsikamma
        'Oudtshoorn'                 => 0,    // Oudtshoorn - Cango Caves/Ostrich Farm
        'Other'                      => 0,
    ];
    
    $selected_poi = $form_fields['charter_poi'] ?? '';
    $poi_distance = $poi_distance_mapping[$selected_poi] ?? 0;

    // NEW: Compute pickup -> POI (lat/lng based)
    $pickup_coords = wsb_ms_normalize_latlng($form_fields['charter_origin_coords'] ?? '');
    $poi_ref       = wsb_ms_get_poi_reference($selected_poi, $form_fields);
    
    $poi_distance_from_pickup_km   = null;
    $poi_distance_from_pickup_text = '';
    
    if ($pickup_coords !== '' && !empty($poi_ref)) {
      $dm = wsb_ms_distance_matrix(
        ['type' => 'latlng', 'value' => $pickup_coords],
        $poi_ref
      );
      if (is_array($dm)) {
        $poi_distance_from_pickup_km   = $dm['km'] ?? null;
        $poi_distance_from_pickup_text = $dm['distance_text'] ?? '';
      }
    }


    $charter_codes = determine_charter_codes($selected_poi, $poi_distance, $pickup_time, $drop_off_time, $duration);
    
    if ($charter_codes === ['wedding']) {

        $dispatch_distance = isset($dispatch_distance) && is_numeric($dispatch_distance)
            ? (float) $dispatch_distance
            : 0.0;
    
        if ($dispatch_distance <= 70) {
            $charter_codes = ['wedding'];
            $poi_distance = 150;
            $ineligible_trip = false;
        } elseif ($dispatch_distance > 70 && $dispatch_distance <= 150) {
            $charter_codes = ['wedding_plus'];
            $poi_distance = 300;
            $ineligible_trip = false;
        } elseif ($dispatch_distance > 150 && $dispatch_distance <= 250) {
            $charter_codes = ['wedding_plus_plus'];
            $poi_distance = 500;
            $ineligible_trip = false;
        } else {
            $charter_codes = ['null'];
            $poi_distance = 0;
            $ineligible_trip = true;
        }
    
        error_log('[WSB][marketing][wedding_tier] dispatch_km=' . $dispatch_distance . ' code=' . implode(',', $charter_codes) . ' poi_distance=' . $poi_distance);
    }
    
    if ($charter_codes === ['dinner']) {
        $poi_distance = 100; // reassign the new poi distance 
    } elseif ($charter_codes === ['full_day_local_plus']) {
        $poi_distance = 250; // reassign the new poi distance 
    } elseif ($charter_codes === ['full_day_local_plus_dinner']) {
        $poi_distance = 350; // reassign the new poi distance 
    } elseif ($charter_codes === ['full_day_overland_plus_dinner']) {
        $poi_distance = 500;
    }

    // Determine charter duration type.
    if ($duration > 6) {
        $charter_duration = "full_day";
    } 
    else {
        $pickup_hour = (int) date('H', strtotime($pickup_time));
        $dropoff_hour = (int) date('H', strtotime($drop_off_time));
    
        if ($pickup_hour < 13 && $dropoff_hour < 13) {
            $charter_duration = "half_day_morning";
        } elseif ($pickup_hour >= 13 && $dropoff_hour >= 13) {
            $charter_duration = "half_day_afternoon";
        } else {
            $charter_duration = "full_day";
        }
    }
            
    $data = [
        'tripType'          => 'charter',
        'charterInfo' => [
            'codes'       => null, // UPDATED
            'poi'         => $form_fields['charter_poi'] ?? '',
            'poiOther'    => $form_fields['charter_poi_other'] ?? '',
            'duration'    => null, 
            'poiReference'              => $poi_ref ?: null,
            'poiDistanceFromPickupKm'   => $poi_distance_from_pickup_km,
            'poiDistanceFromPickupText' => $poi_distance_from_pickup_text,
        ],
        'locationFrom'      => $form_fields['charter_location_origin'] ?? '', 
        'locationTo'        => $form_fields['charter_location_destination'] ?? '',
        'locationFromCoords'=> $form_fields['charter_origin_coords'] ?? '',
        'locationToCoords'  => $form_fields['charter_destination_coords'] ?? '',
        'nameFrom'          => sanitize_text_field($raw['charter']['charterOriginName'] ?? ''),
        'nameTo'            => sanitize_text_field($raw['charter']['charterDestinationName'] ?? ''),
        'townFrom'          => $form_fields['charter_town_origin'] ?? '',
        'townTo'            => $form_fields['charter_town_destination'] ?? '',
        'neighborhoodFrom'  => $form_fields['charter_neighborhood_origin'] ?? '',
        'neighborhoodTo'    => $form_fields['charter_neighborhood_destination'] ?? '',
        'pickupDate'        => $pickup_date ?? '',
        'pickupTime'        => $pickup_time ?? '',
        'dropOffTime'       => $drop_off_time ?? '',
        'origin_hq_km'             => isset($raw['charter']['pickupDistanceFromCtiaKm'])        ? (float)$raw['charter']['pickupDistanceFromCtiaKm']        : null,
        'destination_hq_km'        => isset($raw['charter']['dropoffDistanceFromCtiaKm'])       ? (float)$raw['charter']['dropoffDistanceFromCtiaKm']       : null,
        'returnFrom'        => '', 
        'returnTo'          => '', 
        'returnTownFrom'    => '', 
        'returnTownTo'      => '', 
        'returnFromCoords'  => '', 
        'returnToCoords'    => '', 
        'returnNeighborhoodFrom'  => '', 
        'returnNeighborhoodTo'    => '', 
        'returnDate'        => '',  
        'returnTime'        => '',  
        'passengers'        => $form_fields['charter_passengers'] ?? '',
        'tripDistance'      => $form_fields['charter_distances'] ?? '',
        'distance'          => $poi_distance,
        'duration'          => $formatted_duration,
        'placeIds'          => $form_fields['charter_place_ids'] ?? '',
        'largeBags'         => $form_fields['charter_large_bags'] ?? '',
        'carryOnBags'       => $form_fields['charter_carry-on_bags'] ?? '',
       'babySeatRequired'  => $baby_seat_count > 0 ? 'true' : 'false',
        'babySeatCount'     => $baby_seat_count,
        'trailerRequired'   => in_array('trailer', $additional) ? 'true' : 'false',
        'oversizeLuggage'   => in_array('oversize', $additional) ? 'true' : 'false',
        'withinZoneThreshold' => $form_fields['charter_within_zone_threshold'] ?? '',
        'outsideMaxRadius'  => $form_fields['charter_outside_max_radius'] ?? '',
        'ineligibleTrip'    => $ineligible_trip ?? '',
        'tollGates'         => $form_fields['charter_toll_gates'] ?? '',
        ];

error_log('[WSB] data_structure_processed=1');

    return $data;
}

/**
 * Determine which charter codes apply, based on user’s POI selection and the mapped distance.
 *
 * @param string $selected_poi   The POI label from the form (e.g., "Wedding Charter", "Dinner Transfer", etc.)
 * @param int    $poi_distance   The distance in km, from $poi_distance_mapping.
 *
 * @return array An array of charter codes (e.g., ["wedding", "wedding_plus"]).
 */
function determine_charter_codes( $selected_poi, $poi_distance, $pickup_time, $drop_off_time, $duration ) {
    // 1. Handle special POI cases first:
    if ( $selected_poi === 'Wedding' ) {
        return ['wedding']; // add check for "dispatch > 70km then = wedding_plus"
    }

    $pickup_hour = (int) date('H', strtotime($pickup_time));
    $dropoff_hour = (int) date('H', strtotime($drop_off_time)); 
    // 2. Check half-day eligibility second (applies regardless of selected POI).
    //    If duration is 6 hours or less, we consider it a half-day booking.

    if ( $duration <= 6 && ! ( $pickup_hour < 13 && $dropoff_hour > 13 ) ) {
        // If pickup is 17:00 or later and distance is within 100km, classify as dinner transfer only.
        if ($pickup_hour >= 17) {
            return ['dinner'];
        }
        
        // Check morning half-day: pickup between 07:00 and 13:00.
        if ( $pickup_hour >= 7 && $pickup_hour < 13 ) {
            return ['half_day_am'];
        }
        // Check afternoon half-day: pickup between 13:00 and 19:00.
        if ( $pickup_hour >= 13 && $pickup_hour < 19 ) {
            return ['half_day_pm'];
        }
        // For ambiguous cases, use the midpoint of pickup and drop-off times.
        $pickup_timestamp  = strtotime($pickup_time);
        $dropoff_timestamp = strtotime($drop_off_time);
        $midpoint_timestamp = ($pickup_timestamp + $dropoff_timestamp) / 2;
        $midpoint_hour = (int) date('H', $midpoint_timestamp);
        return ($midpoint_hour < 13) ? ['half_day_am'] : ['half_day_pm'];
    }
    
    // 3.  If the selected POI is "Other" and it's not a half-day booking,
    // we do not know where they want to go or what they want to do for the full day booking
    // then we assign null to the charter code as we want to show a "GET QUOTE" button.
    if ( $selected_poi === 'Other' || $selected_poi === 'Garden Route' || $selected_poi === 'Oudtshoorn'  ) {
        return ['null'];
    }

    // 4. Full-Day Bookings (duration > 6 hours)
    // Determine base package solely based on distance.
    // Note: We assume that if the booking reaches here, duration is >6.
    if ($poi_distance <= 200) {
        // Base package: FULL DAY LOCAL (8-10 hours) for distances ≤200 km.
        // Upgrade logic:
        //   If duration is between 8 and 10 (or below 10): remain as full_day_local.
        //   If duration is between 10 and 12: upgrade to full_day_local_plus.
        //   If duration is above 12: upgrade to full_day_local_plus_dinner.
        if ($duration < 8) {
            $final_package = 'full_day_local';  // don't downgrade
        } elseif ($duration <= 10) {
            $final_package = 'full_day_local';
        } elseif ($duration <= 12) {
            $final_package = 'full_day_local_plus';
        } else {
            $final_package = 'full_day_local_plus_dinner';
        }
        return [$final_package];
    } 
    elseif ($poi_distance <= 250) {
        // Base package: FULL DAY LOCAL + (10-12 hours) for distances ≤250 km.
        if ($duration < 10) {
            $final_package = 'full_day_local_plus';  // no downgrade
        } elseif ($duration <= 12) {
            $final_package = 'full_day_local_plus';
        } else {
            $final_package = 'full_day_local_plus_dinner';
        }
        return [$final_package];
    } 
    elseif ($poi_distance <= 450) {
        // Base package: FULL DAY OVERLAND (12 hours fixed) for distances ≤450 km.
        if ($duration <= 12) {
            $final_package = 'full_day_overland';
        } else {
            $final_package = 'full_day_overland_plus_dinner';
        }
        return [$final_package];
    } 
    else {
        return ['null'];
    }
}

function process_general_transfer_data($form, array $raw = [], array $form_fields = []) {
    // Map Bricks form fields to data structure
    $additional = (array) $form->get_field_value('kkztqf'); // Trailer / oversize etc.

    $baby_seat_count = wsb_ms_get_ride_baby_seat_count($form, $form_fields);

    $get_form_value = function (string $semantic_key, array $fallback_ids = [], $default = '') use ($form, $form_fields) {
    if (array_key_exists($semantic_key, $form_fields)) {
        $value = $form_fields[$semantic_key];

        if (is_array($value)) {
            $value = reset($value);
        }

        if ($value !== '' && $value !== null) {
            return $value;
        }
    }

    foreach ($fallback_ids as $field_id) {
        if (method_exists($form, 'get_field_value')) {
            $value = $form->get_field_value($field_id);

            if (is_array($value)) {
                $value = reset($value);
            }

            if ($value !== '' && $value !== null) {
                return $value;
            }
        }
    }

    return $default;
};
    
    $serviceType = sanitize_text_field($raw['oneWay']['serviceType'] ?? '');

    $data = [
        'tripType'          => isset($form->get_field_value('qattnw')[0]) ? (string) $form->get_field_value('qattnw')[0] : '',
        'charterInfo'       => null,
        'serviceType'       => sanitize_text_field($raw['oneWay']['serviceType'] ?? ''),
        'locationFrom'      => $form->get_field_value('88be8e') ?? '',
        'locationTo'        => $form->get_field_value('4c8111') ?? '',
        'locationFromCoords'=> $form->get_field_value('ikddon') ?? '',
        'locationToCoords'  => $form->get_field_value('khfppi') ?? '',
        'nameFrom'          => sanitize_text_field($raw['oneWay']['originName'] ?? ''),
        'nameTo'            => sanitize_text_field($raw['oneWay']['destinationName'] ?? ''),
        'townFrom'          => $form->get_field_value('rxgxdd') ?? '', 
        'townTo'            => $form->get_field_value('hmwdjo') ?? '',
        'neighborhoodFrom'  => $form->get_field_value('lzxeqb') ?? '', 
        'neighborhoodTo'    => $form->get_field_value('gvswfz') ?? '',  
        'pickupDate'        => $form->get_field_value('3c8aa9') ?? '',
        'pickupTime'        => $form->get_field_value('yzwoxy') ?? '',
        'dropOffTime'        => '',
        'duration'          => isset($raw['oneWay']['duration']) ? (float)$raw['oneWay']['duration'] : null,
        'outboundDuration'  => sanitize_text_field($raw['oneWay']['outboundDuration'] ?? '' ),
        'emptyLegsDuration' => sanitize_text_field($raw['oneWay']['emptyLegsDuration'] ?? ''),
        'pickupDuration' => sanitize_text_field($raw['oneWay']['pickupDuration'] ?? ''),
        'dropoffDuration' => sanitize_text_field($raw['oneWay']['dropoffDuration'] ?? ''),
        'return_service_type'    => sanitize_text_field($raw['roundTrip']['returnServiceType'] ?? ''),
        'returnFrom'        => $form->get_field_value('hdpxkg') ?? '', 
        'returnTo'          => $form->get_field_value('gyuzci') ?? '',
        'returnNameFrom'    => sanitize_text_field($raw['roundTrip']['returnOriginName'] ?? ''),
        'returnNameTo'      => sanitize_text_field($raw['roundTrip']['returnDestinationName'] ?? ''),
        'returnTownFrom'    => $form->get_field_value('vevvtu') ?? '', 
        'returnTownTo'      => $form->get_field_value('makkbx') ?? '',
        'returnFromCoords'  => $form->get_field_value('wkfeng') ?? '',
        'returnToCoords'    => $form->get_field_value('azdnpc') ?? '',
        'returnNeighborhoodFrom'  => sanitize_text_field($raw['roundTrip']['returnOriginNeighborhood'] ?? ''),
        'returnNeighborhoodTo'    => sanitize_text_field($raw['roundTrip']['returnDestinationNeighborhood'] ?? ''),
        'returnDate'        => $form->get_field_value('ldmuex') ?? '', 
        'returnTime'        => $form->get_field_value('jhfygx') ?? '', 
        'returnDuration'          => sanitize_text_field($raw['roundTrip']['returnDuration'] ?? ''), 
        'returnEmptyLegsDuration' => sanitize_text_field($raw['roundTrip']['returnEmptyLegsDuration'] ?? ''),
        'returnPickupDuration' => sanitize_text_field($raw['roundTrip']['returnPickupDuration'] ?? ''),
        'returnDropoffDuration' => sanitize_text_field($raw['roundTrip']['returnDropoffDuration'] ?? ''),
        'passengers'        => max(0, (int) $get_form_value('passengers', ['rliwwi', 'zbrayu'], 0)),
        'largeBags'         => max(0, (int) $get_form_value('large_bags', ['bcsxgw'], 0)),
        'carryOnBags'       => max(0, (int) $get_form_value('carry-on_bags', ['henfgr'], 0)),
        'tripDistance'      => $form->get_field_value('jeswey') ?? '',  // used for price calculation
        'distance'          => $form->get_field_value('miweni') ?? '',  // used for display purposes
        'origin_hq_km'             => isset($raw['oneWay']['pickupDistanceFromCtiaKm'])        ? (float)$raw['oneWay']['pickupDistanceFromCtiaKm']        : null,
        'destination_hq_km'        => isset($raw['oneWay']['dropoffDistanceFromCtiaKm'])       ? (float)$raw['oneWay']['dropoffDistanceFromCtiaKm']       : null,
         'return_origin_hq_km'      => isset($raw['roundTrip']['returnPickupDistanceFromCtiaKm'])  ? (float)$raw['roundTrip']['returnPickupDistanceFromCtiaKm']  : null,
        'return_destination_hq_km' => isset($raw['roundTrip']['returnDropoffDistanceFromCtiaKm']) ? (float)$raw['roundTrip']['returnDropoffDistanceFromCtiaKm'] : null,
        'duration'          => $form->get_field_value('muexss') ?? '',
        'placeIds'          => $form->get_field_value('mrhvcn') ?? '',
        'babySeatRequired'  => $baby_seat_count > 0 ? 'true' : 'false',
        'babySeatCount'     => $baby_seat_count,
        'trailerRequired'   => in_array('trailer', $additional) ? 'true' : 'false',
        'oversizeLuggage'   => in_array('oversize', $additional) ? 'true' : 'false',
        'withinZoneThreshold' => $form->get_field_value('gtjusi') ?? '',
        'outsideMaxRadius'  => $form->get_field_value('ctyzbw') ?? '',
        'ineligibleTrip'    => $form->get_field_value('vjbnkw') ?? '',
        'tollGates'         => $form->get_field_value('fzbfrk') ?? '',
    ];

    return $data;
}



// ===== TRACKING (marketing site) =====
if ( ! function_exists('ws_get_tracking_params') ) {
    /**
     * Collect UTMs/click IDs from the page that submitted the form (HTTP_REFERER),
     * and record the exact landing page path + query (ws_lp / ws_ref).
     */
    function ws_get_tracking_params(): array {
        // Prefer the page that fired the AJAX request
        $source_url = '';
        if ( ! empty($_SERVER['HTTP_REFERER']) ) {
            $source_url = (string) $_SERVER['HTTP_REFERER'];
        } else {
            // Fallback (rare): current request (may be admin-ajax)
            $scheme = is_ssl() ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? '';
            $uri    = $_SERVER['REQUEST_URI'] ?? '/';
            $source_url = $scheme . '://' . $host . $uri;
        }

        // Parse the referrer URL
        $u  = wp_parse_url($source_url);
        $qs = [];
        if ( ! empty($u['query']) ) {
            parse_str($u['query'], $qs);
        }

        // Keys we care about
        $keys = [
            'utm_source','utm_medium','utm_campaign','utm_content','utm_term',
            'matchtype','device','gclid','fbclid','ttclid'
        ];
        $out = [];

        foreach ($keys as $k) {
            $v = $qs[$k] ?? ($_GET[$k] ?? '');
            if ($v !== '') {
                $out[$k] = substr(sanitize_text_field(wp_unslash($v)), 0, 256);
            }
        }

        // Exact page that contained the form (path + query), not admin-ajax
        $path  = $u['path']  ?? '/';
        $query = ! empty($u['query']) ? ('?' . $u['query']) : '';

        // Guard: if something still fed us an admin URL, strip it
        if ( strpos($path, '/wp-admin/') !== false ) {
            $path  = '/';
            $query = '';
        }

        $out['ws_lp']  = $path . $query; // e.g. /airport-shuttle/?utm_...
        $out['ws_ref'] = $source_url;    // full URL on the marketing site

        return $out;
    }
}

function custom_booking_form_action( $form ) {
    // Get form fields
    $form_fields = $form->get_fields();
    $form_id = $form_fields['formId'];

    $payload = wsb_get_booking_payload_from_post();
    $booking_data_raw   = isset($payload['state']) && is_array($payload['state']) ? $payload['state'] : [];
    $schema  = isset($payload['v']) ? intval($payload['v']) : 0;
    
    error_log('$payload present: ' . (empty($payload) ? 'no' : 'yes'));
    error_log('$state[tripType]: ' . ( $booking_data_raw['tripType'] ?? '' ));
    error_log('$state[oneWay][serviceType]: ' . ( $booking_data_raw['oneWay']['serviceType'] ?? '' ));
    
    // If you need a dynamic key like $oneWayName:
    $oneWayName = 'oneWay'; // or derive it if you change naming later
    error_log('$booking_data_raw[' . $oneWayName . ']: ' . ( empty($booking_data_raw[$oneWayName]) ? 'empty' : 'set' ));


    $settings = $form->get_settings();

     error_log('$form_id: ' . $form_id);

    // Ensure this is the correct form
    // form_id ifkszj -> point to point transfer form (Ride)
    // form_id qlwoyv -> charter services form 
    // Ensure we're working with the expected form IDs
    if ( ! in_array( $form_id, ['ifkszj', 'qlwoyv'], true ) ) {
        return;
    }

    // Assign a custom booking type based on the formId
    $booking_type = $form_id === 'qlwoyv' ? 'charter' : 'point_to_point_transfer';
    // error_log('$bookingType: ' . $booking_type);

     error_log('[WSB] form_fields_received=1');

    // Now you can simply check against $bookingType in your logic
    if ( 'charter' === $booking_type ) {
        $data = process_charter_data($form, $form_fields, $booking_data_raw);
    } 
    else {
        $data = process_general_transfer_data($form, $booking_data_raw, $form_fields);

    }
    
    // override the system rules if admin is logged in 
    if ( is_user_logged_in() && current_user_can( 'administrator' ) ) {
        $data['withinZoneThreshold'] = true;
        $data['outsideMaxRadius']   = false;
        $data['ineligibleTrip']     = false;
    }

    // error_log('$data: '. print_r($data, true));
    // Gather tracking from current request (marketing site)
    $tracking = ws_get_tracking_params();
    
    // Attach to the payload you HMAC
    $data['tracking'] = $tracking;
    
    // Generate a hash for the booking data
    $secret_key = BOOKING_HASH_SECRET;
    error_log('[WSB] secret_configured=1');
    $hash = send_booking_data( $data, $secret_key );

    if ( $hash ) {
        // Redirect to the booking URL
        $redirect_url = get_booking_url( '?hash=' . urlencode( $hash ) );
        $form->set_result([
            'action' => 'custom_redirect',
            'type' => 'redirect',
            'redirectTo' => $redirect_url,
            'redirectTimeout' => 0,
        ]);
    } 
    else {
        wp_die( 'Failed to process your booking. Please try again later.' );
    }
}

function send_booking_data( $data, $secret_key ) {
    $hash = hash_hmac( 'sha256', json_encode( $data ), $secret_key );

    error_log('[WSB] handover_hash_generated=1');

    // API URL of the booking system
    $api_url = get_booking_url( '/wp-json/booking-api/v1/receive-booking' );
     
    error_log('[WSB] handover_endpoint_configured=1');

    // Send data and hash to the booking system
    $response = wp_remote_post( $api_url, [
        'timeout' => 15,
        'body' => json_encode([
            'data' => $data,
            'hash' => $hash,
        ]),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ]);

    // Check the response
    if ( is_wp_error( $response ) ) {
        error_log( 'Error sending booking data: ' . $response->get_error_message() );
        return false;
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    if ( $response_code === 200 ) {
        return $hash; // Return the hash for redirect
    }

    error_log( 'Failed to send booking data: ' . wp_remote_retrieve_body( $response ) );
    return false;
}

function validate_trip_distances($trip_distance, $type) {
    // error_log('$trip_distance: ' . print_r($trip_distance));
      error_log('[WSB] trip_distance_validated=1');
    // Check if $trip_distance is actually an array
    if (!is_array($trip_distance)) {
        return null;
    }
    
    if ($type === 'return') {
         // Then, check the array length for return trips 
        if (count($trip_distance) !== 2) {
            return 'Return journey: Please select a location from the address autocomplete';
        }
    } elseif ($type === 'one-way') {
         // Then, check the array length for one-way trips
        if (count($trip_distance) !== 1 || empty($trip_distance[0])) {
            return 'Please select a location from the address autocomplete';
        }
    } 
   
    return null;
}

/**
 * Charter validation: uses pickup date for both pickup + drop-off checks.
 * Expects $form_fields with keys:
 *   - charter_pickup_date
 *   - charter_pickup_time
 *   - charter_drop-off_time   (NOTE the hyphen in the key!)
 */
function wsb_validate_charter_blockouts(array $errors, $form, array $form_fields): array {
  // 1) Read fields (array first, then Bricks getter as fallback)
  $pickup_date   = $form_fields['charter_pickup_date']
                   ?? (method_exists($form,'get_field_value') ? $form->get_field_value('charter_pickup_date') : '');
  $pickup_time   = $form_fields['charter_pickup_time']
                   ?? (method_exists($form,'get_field_value') ? $form->get_field_value('charter_pickup_time') : '');
  $drop_off_time = $form_fields['charter_drop-off_time'] // <-- hyphen
                   ?? (method_exists($form,'get_field_value') ? $form->get_field_value('charter_drop-off_time') : '');

  // 2) Normalize date
  $iso = wsb_ms_to_iso($pickup_date);

  // 3) Server-side blockouts (from the marketing-site client plugin cache)
  if (function_exists('wsb_client_is_blocked')) {
    if ($iso && $pickup_time && wsb_client_is_blocked($iso, $pickup_time)) {
      $errors[] = sprintf('Charter pickup time is unavailable on %s at %s. Please choose a different time.', $iso, $pickup_time);
    }
    if ($iso && $drop_off_time && wsb_client_is_blocked($iso, $drop_off_time)) {
      $errors[] = sprintf('Charter drop-off time is unavailable on %s at %s. Please choose a different time.', $iso, $drop_off_time);
    }
  }
  if (!empty($errors)) return $errors; // stop early if blocked

  // 4) Chronology (same-day assumption): drop-off must be after pickup
  // $p = wsb_ms_time_to_minutes($pickup_time);
  // $d = wsb_ms_time_to_minutes($drop_off_time);
  // if ($p !== null && $d !== null && $d <= $p) {
  //   $errors[] = 'For charter bookings, the drop-off time must be after the pickup time.';
  // }

  return $errors;
}

function validate_charter_booking($errors, $form) {

    $form_fields = method_exists($form,'get_fields') ? (array)$form->get_fields() : [];

    $errors = wsb_validate_charter_blockouts($errors, $form, $form_fields);
    if (!empty($errors)) return $errors;

    // Retrieve pickup date and time from form fields.
    $pickup_date = $form_fields['charter_pickup_date'];
    $pickup_time = $form_fields['charter_pickup_time'];
    $drop_off_time = $form_fields['charter_drop-off_time'];
    
    // Define required charter duration 
    $required_duration = 48 * HOUR_IN_SECONDS; 

    // validate time gap if not admin 
    if ( !current_user_can('administrator') ) {
         // Validate the time gap using our helper.
        $error_message = validate_time_gap($pickup_date, $pickup_time, $required_duration, 'charter');
        if ($error_message !== null) {
            $errors[] = esc_html__($error_message, 'bricks');
        }
    }

    // Validate that drop off time is after pickup time.
    // Currently, we can allow a client to book into the morning... So disabling for now. 
    
    // $error_message = validate_dropoff_after_pickup($pickup_date, $pickup_time, $drop_off_time);
    if ($error_message !== null) {
        $errors[] = esc_html__($error_message, 'bricks');
    }
    
    return $errors; 
}

/**
 * Calculates the duration (in hours) between the pickup and drop off times,
 * rounding to the nearest hour (rounding half hours up).
 *
 * @param string $pickup_date   The pickup date string (e.g., "28/02/2025").
 * @param string $pickup_time   The pickup time string (e.g., "08:10").
 * @param string $drop_off_time The drop off time string (e.g., "10:30").
 * @param string $format        The date/time format (default "d/m/Y H:i").
 *
 * @return int|false The duration in hours (rounded) or false if parsing fails.
 */
function calculate_duration($pickup_date, $pickup_time, $drop_off_time, $format = 'd/m/Y H:i') {
    // Use WordPress's timezone for consistency.
    $timezone = wp_timezone();
    
    // Build DateTime objects.
    $pickup_datetime = DateTime::createFromFormat($format, $pickup_date . ' ' . $pickup_time, $timezone);
    $dropoff_datetime = DateTime::createFromFormat($format, $pickup_date . ' ' . $drop_off_time, $timezone);
    
    if (!$pickup_datetime || !$dropoff_datetime) {
        error_log('Failed to parse pickup or drop off date/time.');
        return false;
    }
    
    // Calculate the difference in seconds.
    $diff_seconds = $dropoff_datetime->getTimestamp() - $pickup_datetime->getTimestamp();
    
    // Convert to hours (as a float).
    $hours = $diff_seconds / 3600;
    
    // Round to the nearest whole hour, rounding half hours up.
    $rounded_hours = round($hours, 0, PHP_ROUND_HALF_UP);
    
    return $rounded_hours;
}

/**
 * Validates that the given date and time is at least the required gap ahead of the current time.
 *
 * @param string $date         The date string (e.g., "28/02/2025").
 * @param string $time         The time string (e.g., "08:00").
 * @param int    $duration     The minimum required gap in seconds.
 *
 * @return string|null Returns an error message if invalid, or null if the gap is valid.
 */
function validate_time_gap($date, $time, $duration, $vehicle_type) {
    // error_log('duration in validate_time_gap: ' . $duration);
    $format = 'd/m/Y H:i';
    $datetime_str = $date . ' ' . $time;
    
    // Use WordPress's timezone for consistency.
    $timezone = wp_timezone();
    $datetime_obj = DateTime::createFromFormat($format, $datetime_str, $timezone);
    
    // If parsing fails, return an error message.
    if (!$datetime_obj) {
        return 'Failed to parse date/time: ' . $datetime_str;
    }
    
    $pickup_timestamp = $datetime_obj->getTimestamp();
    $current_datetime = new DateTime( 'now', $timezone );
    $current_timestamp = $current_datetime->getTimestamp();
    // $current_timestamp = current_time('timestamp');
    
    // Check if the gap is less than the required duration.
    if (($pickup_timestamp - $current_timestamp) < $duration) {
        return 'Please book your ' . $vehicle_type . ' at least ' . ($duration / 3600) . ' hours in advance.';
    }
    
    return null;
}

/**
 * Validates that the drop off time occurs after the pickup time.
 *
 * @param string $pickup_date    The pickup date string (e.g., "28/02/2025").
 * @param string $pickup_time    The pickup time string (e.g., "08:10").
 * @param string $drop_off_time  The drop off time string (e.g., "10:00").
 * @param string $format         The format for parsing the date/time (default "d/m/Y H:i").
 *
 * @return string|null Returns an error message if invalid; otherwise, null.
 */
function validate_dropoff_after_pickup($pickup_date, $pickup_time, $drop_off_time, $format = 'd/m/Y H:i') {
    // Use WordPress's timezone for consistency.
    $timezone = wp_timezone();
    
    // Build pickup datetime
    $pickup_datetime_str = $pickup_date . ' ' . $pickup_time;
    $pickup_dt = DateTime::createFromFormat($format, $pickup_datetime_str, $timezone);
    if (!$pickup_dt) {
        return 'Failed to parse pickup date/time: ' . $pickup_datetime_str;
    }
    
    // Build drop off datetime
    $dropoff_datetime_str = $pickup_date . ' ' . $drop_off_time;
    $dropoff_dt = DateTime::createFromFormat($format, $dropoff_datetime_str, $timezone);
    if (!$dropoff_dt) {
        return 'Failed to parse drop off date/time: ' . $dropoff_datetime_str;
    }
    
    // Check that drop off time is strictly after pickup time.
    if ($dropoff_dt <= $pickup_dt) {
        return 'Drop off time must be after pickup time.';
    }
    
    return null;
}

function validate_general_transfer_booking($errors, $form) {

    // Use the hashed field names you already use further down:
    $pairs = [
    ['label' => 'Pickup time', 'date' => $form->get_field_value('3c8aa9'), 'time' => $form->get_field_value('yzwoxy')],
    ['label' => 'Return time',   'date' => $form->get_field_value('ldmuex'), 'time' => $form->get_field_value('jhfygx')],
    ];
    
    // Fallback to WC session if outbound is missing
    if (empty($pairs[0]['date']) || empty($pairs[0]['time'])) {
    $trip = function_exists('WC') ? WC()->session->get('trip_details') : null;
    if (is_array($trip)) {
          if (empty($pairs[0]['date']) && !empty($trip['pickup_date'])) $pairs[0]['date'] = $trip['pickup_date'];
          if (empty($pairs[0]['time']) && !empty($trip['pickup_time'])) $pairs[0]['time'] = $trip['pickup_time'];
        }
    }
    
    // Run server-side blockout check FIRST
    $calendar_availability = wsb_ms_check_blockouts($pairs);
    foreach ($calendar_availability as $msg) { $errors[] = $msg; }
    if (!empty($calendar_availability)) return $errors;
    
    // Retrieve pickup date and time from form fields.
    $pickup_date = $form->get_field_value('3c8aa9'); // e.g., "28/02/2025"
    $pickup_time = $form->get_field_value('yzwoxy');  // e.g., "08:00"

    // Determine the “now” in the site’s timezone (GMT+2).
    $tz   = wp_timezone(); 
    $now  = new DateTime( 'now', $tz );
    $hour = (int) $now->format( 'H' );

    $required_duration = 12 * HOUR_IN_SECONDS;

    if ( $hour >= 4 && $hour < 21 ) {
        $required_duration = 5 * HOUR_IN_SECONDS;  
    } else {
        $required_duration = 12 * HOUR_IN_SECONDS;  
    }
    
    // validate time gap if not admin 
    if ( !current_user_can('administrator') ) {
       // Validate the time gap using our helper.
        $error_message = validate_time_gap($pickup_date, $pickup_time, $required_duration, 'shuttle');
        if ($error_message !== null) {
            $errors[] = esc_html__($error_message, 'bricks');
        }
    }

    $form_fields = $form->get_fields();
    $trip_type = $form_fields['trip_type'];

    // validate trip distances 
    if (is_array($trip_type) && !empty($trip_type)) {
        $trip_type_value = $trip_type[0];
        $trip_distance_str = $form_fields['trip_distance']; // e.g. "20.5,77.1"

        // Convert the string to an array
        $trip_distance = explode(',', $trip_distance_str);

        if ($trip_type_value === 'roundtrip') {
            $error_message = validate_trip_distances($trip_distance, 'return');
        } else {
            $error_message = validate_trip_distances($trip_distance, 'one-way');
        }
        if ($error_message !== null) {
            $errors[] = esc_html__($error_message, 'bricks');
        }
    }
    
    return $errors;
}

function validate_custom_form( $errors, $form ) {
    wsb_ms_ensure_blockouts_loaded();

    $form_fields = $form->get_fields();
    $form_id = $form_fields['formId'];

    $payload = wsb_get_booking_payload_from_post();
    
    // Validate that the form id is from either of our forms
    if ( ! in_array( $form_id, ['ifkszj', 'qlwoyv'], true ) ) {
        return $errors;
    }

    // Map the form id to a booking type
    $booking_type = $form_id === 'qlwoyv' ? 'charter' : 'point_to_point_transfer';

    // Return the result from the appropriate validation function
    return ( 'charter' === $booking_type )
        ? validate_charter_booking( $errors, $form )
        : validate_general_transfer_booking( $errors, $form );
}
