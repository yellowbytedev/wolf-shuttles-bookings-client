<?php
// <Internal Doc Start>
/*
*
* @description: 
* @tags: 
* @group: 
* @name: Create hash code & travel data on ride booking form submission
* @type: PHP
* @status: draft
* @created_by: 
* @created_at: 
* @updated_at: 2025-01-24 12:33:11
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
add_filter('gform_confirmation_1', 'submit_booking_form', 10, 4);

function send_booking_data($data, $secret_key) {
    $hash = hash_hmac('sha256', json_encode($data), $secret_key);

    // API URL of the booking system
    $api_url = get_booking_url('/wp-json/booking-api/v1/receive-booking');
    error_log('$api_url: ' . $api_url);

    // Send the data and hash to the booking system
    $response = wp_remote_post($api_url, [
        'body' => json_encode([
            'data' => $data,
            'hash' => $hash,
        ]),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ]);

    // Check the response
    if (is_wp_error($response)) {
        error_log('Error sending booking data: ' . $response->get_error_message());
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code === 200) {
        return $hash; // Return the hash for redirect
    } 

    error_log('Failed to send booking data: ' . wp_remote_retrieve_body($response));
    return false;
}




function submit_booking_form($confirmation, $form, $entry, $is_ajax) {
    // Get the data from the form submission
    $data = [
        'tripType'       => rgar($entry, '4'), 
        'locationFrom'   => rgar($entry, '1.1'),
        'locationTo'     => rgar($entry, '14.1'),
        'pickupDate'     => rgar($entry, '5'),
        'pickupTime'     => rgar($entry, '34'),
        'returnFrom'     => rgar($entry, '22.1'),
        'returnTo'       => rgar($entry, '23.1'),
        'returnDate'     => rgar($entry, '17'),
        'returnTime'     => rgar($entry, '35'),
        'passengers'     => rgar($entry, '32'),
        'distance'       => rgar($entry, '24'),
        'duration'       => rgar($entry, '25'),
        'placeIds'       => rgar($entry, '30'),
        'largeBags'      => rgar($entry, '33'),
        'carryOnBags'    => rgar($entry, '36'),
        'babySeatRequired'  => !empty(rgar($entry, '31.1')) ? 'true' : 'false',
        'trailerRequired'   => !empty(rgar($entry, '31.2')) ? 'true' : 'false',
        'withinZoneThreshold' => rgar($entry, '38'),
        'outsideMaxRadius' => rgar($entry, '39'),
        'ineligibleTrip' => rgar($entry, '40'),
    ];

    // Generate a hash for the booking data
    $secret_key = BOOKING_HASH_SECRET;
    $hash = send_booking_data($data, $secret_key);
    error_log('Generated hash: ' . $hash);

    if ($hash) {
        $redirect_url = get_booking_url('?hash=' .  urlencode($hash));
        error_log('$redirect_url: ' . $redirect_url);
        wp_redirect($redirect_url);
        exit;
    } else {
        wp_die('Failed to process your booking. Please try again later.');
    }
}
