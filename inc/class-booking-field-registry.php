<?php

namespace WSB_Booking_Client;

if (!defined('ABSPATH')) {
    exit;
}

class BookingFieldRegistry {
    /**
     * Return canonical field definitions for the booking builder form.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function get_fields(): array {
        $config = wsb_client_external_services()->get_cached_booking_site_config();
        $capacity = $config['capacity'] ?? [];
        $picker = $config['picker'] ?? [];
        $lead_times = $config['lead_times'] ?? [];
        $time_step = (int) ($picker['time_step_minutes'] ?? 5);
        $max_passengers = (int) ($capacity['max_passengers'] ?? 13);
        $max_bags = (int) ($capacity['max_check_in_bags'] ?? 13);

        $tz = wp_timezone();
        $now = new \DateTime('now', $tz);

        // Calculate min date (transfer lead time = 5 hours minimum)
        $transfer_min_notice = (int) ($lead_times['transfer_min_notice_minutes'] ?? 300);
        $charter_min_notice = (int) ($lead_times['charter_min_notice_minutes'] ?? 2880);
        $max_advance = (int) ($lead_times['max_advance_booking_days'] ?? 365);

        // Min date: earliest selectable date based on transfer lead time
        $min_date = clone $now;
        $min_date->modify('+' . $transfer_min_notice . ' minutes');
        $min_date->setTime((int)$min_date->format('H'), 0, 0); // Align to hour start
        $min_date_str = $min_date->format('Y-m-d');

        $charter_min_date = clone $now;
        $charter_min_date->modify('+' . $charter_min_notice . ' minutes');
        $charter_min_date->setTime((int) $charter_min_date->format('H'), 0, 0);
        $charter_min_date_str = $charter_min_date->format('Y-m-d');

        // Max date: latest selectable date
        $max_date = clone $now;
        $max_date->modify('+' . $max_advance . ' days');
        $max_date_str = $max_date->format('Y-m-d');

        $fields = [
            'trip_type' => [
                'key' => 'trip_type',
                'label' => __('Trip type', 'wsb'),
                'placeholder' => __('One-way or return', 'wsb'),
                'type' => 'select',
                'required' => true,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'trip_type',
                    'data-ws-form-section' => 'trip_type',
                    'data-ws-help' => 'trip_type',
                    'data-ws-help-context' => 'book_a_ride',
                ],
            ],
            'passengers' => [
                'key' => 'passengers',
                'label' => __('Passengers', 'wsb'),
                'placeholder' => __('Number of passengers', 'wsb'),
                'type' => 'select',
                'required' => true,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'options' => range(1, $max_passengers),
                'data_attributes' => [
                    'data-ws-field-key' => 'passengers',
                    'data-ws-form-section' => 'passengers',
                    'data-ws-help' => 'passengers',
                    'data-ws-help-context' => 'book_a_ride,shuttle_hire',
                ],
            ],
            'baby_seats' => [
                'key' => 'baby_seats',
                'label' => __('Baby seats', 'wsb'),
                'placeholder' => __('Number of baby seats', 'wsb'),
                'type' => 'select',
                'required' => false,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'options' => range(0, 3),
                'data_attributes' => [
                    'data-ws-field-key' => 'baby_seats',
                    'data-ws-form-section' => 'passengers',
                    'data-ws-help' => 'baby_seats',
                    'data-ws-help-context' => 'book_a_ride,shuttle_hire',
                ],
            ],
            'check_in_bags' => [
                'key' => 'check_in_bags',
                'label' => __('Check-in bags', 'wsb'),
                'placeholder' => __('Number of check-in bags', 'wsb'),
                'type' => 'select',
                'required' => false,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'options' => range(0, $max_bags),
                'data_attributes' => [
                    'data-ws-field-key' => 'check_in_bags',
                    'data-ws-form-section' => 'luggage',
                    'data-ws-help' => 'check_in_bags',
                    'data-ws-help-context' => 'book_a_ride,shuttle_hire',
                ],
            ],
            'carry_on_bags' => [
                'key' => 'carry_on_bags',
                'label' => __('Carry-on bags', 'wsb'),
                'placeholder' => __('Number of carry-on bags', 'wsb'),
                'type' => 'select',
                'required' => false,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'options' => range(0, $max_bags),
                'data_attributes' => [
                    'data-ws-field-key' => 'carry_on_bags',
                    'data-ws-form-section' => 'luggage',
                    'data-ws-help' => 'carry_on_bags',
                    'data-ws-help-context' => 'book_a_ride,shuttle_hire',
                ],
            ],
            'trailer' => [
                'key' => 'trailer',
                'label' => __('Trailer required', 'wsb'),
                'placeholder' => __('Need a trailer?', 'wsb'),
                'type' => 'checkbox',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'trailer',
                    'data-ws-form-section' => 'addons',
                    'data-ws-help' => 'trailer',
                    'data-ws-help-context' => 'book_a_ride,shuttle_hire',
                ],
            ],
            'oversize_luggage' => [
                'key' => 'oversize_luggage',
                'label' => __('Oversize luggage', 'wsb'),
                'placeholder' => __('Oversize luggage', 'wsb'),
                'type' => 'checkbox',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'oversize_luggage',
                    'data-ws-form-section' => 'addons',
                    'data-ws-help' => 'oversize_luggage',
                    'data-ws-help-context' => 'book_a_ride,shuttle_hire',
                ],
            ],
            'outbound_from' => [
                'key' => 'outbound_from',
                'label' => __('From', 'wsb'),
                'placeholder' => __('Enter pickup address, airport or area', 'wsb'),
                'type' => 'text',
                'required' => true,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'outbound_from',
                    'data-ws-form-section' => 'outbound_locations',
                    'data-ws-help' => 'pickup_location',
                    'data-ws-help-context' => 'book_a_ride',
                    'data-ws-route-role' => 'origin',
                    'data-ws-place-role' => 'origin',
                ],
            ],
            'outbound_to' => [
                'key' => 'outbound_to',
                'label' => __('To', 'wsb'),
                'placeholder' => __('Enter drop-off address, airport or area', 'wsb'),
                'type' => 'text',
                'required' => true,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'outbound_to',
                    'data-ws-form-section' => 'outbound_locations',
                    'data-ws-help' => 'dropoff_location',
                    'data-ws-help-context' => 'book_a_ride',
                    'data-ws-route-role' => 'destination',
                    'data-ws-place-role' => 'destination',
                ],
            ],
            'outbound_pickup_date' => [
                'key' => 'outbound_pickup_date',
                'label' => __('Pickup date', 'wsb'),
                'placeholder' => __('Select pickup date', 'wsb'),
                'type' => 'date',
                'required' => true,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'date_min_attr' => $min_date_str,
                'charter_date_min_attr' => $charter_min_date_str,
                'date_max_attr' => $max_date_str,
                'data_attributes' => [
                    'data-ws-field-key' => 'outbound_pickup_date',
                    'data-ws-form-section' => 'outbound_datetime',
                    'data-ws-help' => 'pickup_date',
                    'data-ws-help-context' => 'book_a_ride',
                ],
            ],
            'outbound_pickup_time' => [
                'key' => 'outbound_pickup_time',
                'label' => __('Pickup time', 'wsb'),
                'placeholder' => __('Select pickup time', 'wsb'),
                'type' => 'time',
                'required' => true,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'step_attr' => $time_step * 60, // Convert to seconds
                'data_attributes' => [
                    'data-ws-field-key' => 'outbound_pickup_time',
                    'data-ws-form-section' => 'outbound_datetime',
                    'data-ws-help' => 'pickup_time',
                    'data-ws-help-context' => 'book_a_ride',
                ],
            ],
            'return_from' => [
                'key' => 'return_from',
                'label' => __('Return from', 'wsb'),
                'placeholder' => __('Enter return pickup address, airport or area', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'return_from',
                    'data-ws-form-section' => 'return_locations',
                    'data-ws-help' => 'pickup_location',
                    'data-ws-help-context' => 'book_a_ride',
                    'data-ws-route-role' => 'return_origin',
                    'data-ws-place-role' => 'return_origin',
                ],
            ],
            'return_to' => [
                'key' => 'return_to',
                'label' => __('Return to', 'wsb'),
                'placeholder' => __('Enter return drop-off address, airport or area', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'return_to',
                    'data-ws-form-section' => 'return_locations',
                    'data-ws-help' => 'dropoff_location',
                    'data-ws-help-context' => 'book_a_ride',
                    'data-ws-route-role' => 'return_destination',
                    'data-ws-place-role' => 'return_destination',
                ],
            ],
            'return_pickup_date' => [
                'key' => 'return_pickup_date',
                'label' => __('Return date', 'wsb'),
                'placeholder' => __('Select return date', 'wsb'),
                'type' => 'date',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
                'date_min_attr' => $min_date_str,
                'date_max_attr' => $max_date_str,
                'data_attributes' => [
                    'data-ws-field-key' => 'return_pickup_date',
                    'data-ws-form-section' => 'return_datetime',
                    'data-ws-help' => 'pickup_date',
                    'data-ws-help-context' => 'book_a_ride',
                ],
            ],
            'return_pickup_time' => [
                'key' => 'return_pickup_time',
                'label' => __('Return time', 'wsb'),
                'placeholder' => __('Select return time', 'wsb'),
                'type' => 'time',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
                'step_attr' => $time_step * 60, // Convert to seconds
                'data_attributes' => [
                    'data-ws-field-key' => 'return_pickup_time',
                    'data-ws-form-section' => 'return_datetime',
                    'data-ws-help' => 'pickup_time',
                    'data-ws-help-context' => 'book_a_ride',
                ],
            ],
            'additional_stop' => [
                'key' => 'additional_stop',
                'label' => __('Additional stop', 'wsb'),
                'placeholder' => __('Enter additional stop address or area', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'additional_stop',
                    'data-ws-form-section' => 'additional_stop',
                    'data-ws-help' => 'additional_stop',
                    'data-ws-help-context' => 'book_a_ride',
                    'data-ws-route-role' => 'stop',
                    'data-ws-place-role' => 'stop',
                ],
            ],
            'outbound_additional_stop' => [
                'key' => 'outbound_additional_stop',
                'label' => __('Additional stop', 'wsb'),
                'placeholder' => __('Enter additional stop address or area', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'outbound_additional_stop',
                    'data-ws-form-section' => 'outbound_additional_stop',
                    'data-ws-help' => 'additional_stop',
                    'data-ws-help-context' => 'book_a_ride',
                    'data-ws-route-role' => 'stop',
                    'data-ws-place-role' => 'stop',
                    'data-ws-feature-gate' => 'enable_additional_stops',
                ],
            ],
            'return_additional_stop' => [
                'key' => 'return_additional_stop',
                'label' => __('Additional stop', 'wsb'),
                'placeholder' => __('Enter additional stop address or area', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'return_additional_stop',
                    'data-ws-form-section' => 'return_additional_stop',
                    'data-ws-help' => 'additional_stop',
                    'data-ws-help-context' => 'book_a_ride',
                    'data-ws-route-role' => 'stop',
                    'data-ws-place-role' => 'stop',
                    'data-ws-feature-gate' => 'enable_additional_stops',
                ],
            ],
            'charter_pickup_location' => [
                'key' => 'charter_pickup_location',
                'label' => __('Pickup location', 'wsb'),
                'placeholder' => __('Enter pickup address or area', 'wsb'),
                'type' => 'text',
                'required' => true,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_pickup_location',
                    'data-ws-form-section' => 'charter_locations',
                    'data-ws-help' => 'pickup_location',
                    'data-ws-help-context' => 'shuttle_hire',
                    'data-ws-route-role' => 'charter_origin',
                    'data-ws-place-role' => 'charter_origin',
                ],
            ],
            'charter_dropoff_location' => [
                'key' => 'charter_dropoff_location',
                'label' => __('Drop-off location', 'wsb'),
                'placeholder' => __('Enter drop-off address or area', 'wsb'),
                'type' => 'text',
                'required' => true,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_dropoff_location',
                    'data-ws-form-section' => 'charter_locations',
                    'data-ws-help' => 'dropoff_location',
                    'data-ws-help-context' => 'shuttle_hire',
                    'data-ws-route-role' => 'charter_destination',
                    'data-ws-place-role' => 'charter_destination',
                ],
            ],
            'charter_pickup_time' => [
                'key' => 'charter_pickup_time',
                'label' => __('Pickup time', 'wsb'),
                'placeholder' => __('Start time (HH:MM)', 'wsb'),
                'type' => 'time',
                'required' => true,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'step_attr' => $time_step * 60, // Convert to seconds
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_pickup_time',
                    'data-ws-form-section' => 'charter_datetime',
                    'data-ws-help' => 'pickup_time',
                    'data-ws-help-context' => 'shuttle_hire',
                ],
            ],
            'charter_dropoff_time' => [
                'key' => 'charter_dropoff_time',
                'label' => __('Drop-off time', 'wsb'),
                'placeholder' => __('End time (HH:MM)', 'wsb'),
                'type' => 'time',
                'required' => true,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'step_attr' => $time_step * 60, // Convert to seconds
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_dropoff_time',
                    'data-ws-form-section' => 'charter_datetime',
                    'data-ws-help' => 'dropoff_time',
                    'data-ws-help-context' => 'shuttle_hire',
                ],
            ],
            'charter_additional_stop' => [
                'key' => 'charter_additional_stop',
                'label' => __('Additional stop', 'wsb'),
                'placeholder' => __('Enter additional stop address or area', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_additional_stop',
                    'data-ws-form-section' => 'additional_stop',
                    'data-ws-help' => 'additional_stop',
                    'data-ws-help-context' => 'shuttle_hire',
                    'data-ws-route-role' => 'stop',
                    'data-ws-place-role' => 'stop',
                    'data-ws-feature-gate' => 'enable_additional_stops',
                ],
            ],
            'charter_poi' => [
                'key' => 'charter_poi',
                'label' => __('Point of interest', 'wsb'),
                'placeholder' => __('Search or select a point of interest', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_poi',
                    'data-ws-form-section' => 'charter_poi',
                    'data-ws-help' => 'charter_poi',
                    'data-ws-help-context' => 'shuttle_hire',
                ],
            ],
            'charter_notes' => [
                'key' => 'charter_notes',
                'label' => __('Notes for this hire', 'wsb'),
                'placeholder' => __('Any timing, route or itinerary notes', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_notes',
                    'data-ws-form-section' => 'charter_notes',
                    'data-ws-help' => 'charter_notes',
                    'data-ws-help-context' => 'shuttle_hire',
                ],
            ],
            'charter_day_date' => [
                'key' => 'charter_day_date',
                'label' => __('Date', 'wsb'),
                'placeholder' => __('Select date', 'wsb'),
                'type' => 'date',
                'required' => true,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'date_min_attr' => $charter_min_date_str,
                'date_max_attr' => $max_date_str,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_day_date',
                    'data-ws-form-section' => 'charter_day_slot',
                    'data-ws-help' => 'day_date',
                    'data-ws-help-context' => 'shuttle_hire',
                    'data-wsb-context' => 'shuttle_hire',
                    'data-wsb-charter-day-field' => 'date',
                ],
            ],
            'charter_day_start_time' => [
                'key' => 'charter_day_start_time',
                'label' => __('Start time', 'wsb'),
                'placeholder' => __('Start time (HH:MM)', 'wsb'),
                'type' => 'time',
                'required' => true,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'step_attr' => $time_step * 60,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_day_start_time',
                    'data-ws-form-section' => 'charter_day_slot',
                    'data-ws-help' => 'day_start_time',
                    'data-ws-help-context' => 'shuttle_hire',
                    'data-wsb-context' => 'shuttle_hire',
                    'data-wsb-charter-day-field' => 'start_time',
                ],
            ],
            'charter_day_end_time' => [
                'key' => 'charter_day_end_time',
                'label' => __('End time', 'wsb'),
                'placeholder' => __('End time (HH:MM)', 'wsb'),
                'type' => 'time',
                'required' => true,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'step_attr' => $time_step * 60,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_day_end_time',
                    'data-ws-form-section' => 'charter_day_slot',
                    'data-ws-help' => 'day_end_time',
                    'data-ws-help-context' => 'shuttle_hire',
                    'data-wsb-context' => 'shuttle_hire',
                    'data-wsb-charter-day-field' => 'end_time',
                ],
            ],
            'charter_day_pickup_location' => [
                'key' => 'charter_day_pickup_location',
                'label' => __('Pickup location', 'wsb'),
                'placeholder' => __('Enter pickup address or area', 'wsb'),
                'type' => 'text',
                'required' => true,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_day_pickup_location',
                    'data-ws-form-section' => 'charter_day_plan',
                    'data-ws-help' => 'day_pickup_location',
                    'data-ws-help-context' => 'shuttle_hire',
                    'data-wsb-context' => 'shuttle_hire',
                    'data-wsb-charter-day-field' => 'pickup_location',
                ],
            ],
            'charter_day_dropoff_location' => [
                'key' => 'charter_day_dropoff_location',
                'label' => __('Drop-off location', 'wsb'),
                'placeholder' => __('Enter drop-off address or area', 'wsb'),
                'type' => 'text',
                'required' => true,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_day_dropoff_location',
                    'data-ws-form-section' => 'charter_day_plan',
                    'data-ws-help' => 'day_dropoff_location',
                    'data-ws-help-context' => 'shuttle_hire',
                    'data-wsb-context' => 'shuttle_hire',
                    'data-wsb-charter-day-field' => 'dropoff_location',
                ],
            ],
            'charter_day_poi' => [
                'key' => 'charter_day_poi',
                'label' => __('Places or stops you\'d like to include', 'wsb'),
                'placeholder' => __('Wine estates, viewpoints, attractions', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_day_poi',
                    'data-ws-form-section' => 'charter_day_plan',
                    'data-ws-help' => 'day_poi',
                    'data-ws-help-context' => 'shuttle_hire',
                    'data-wsb-context' => 'shuttle_hire',
                    'data-wsb-charter-day-field' => 'poi_intent',
                ],
            ],
            'charter_day_notes' => [
                'key' => 'charter_day_notes',
                'label' => __('Notes for this day', 'wsb'),
                'placeholder' => __('Any timing or itinerary notes', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['charter'],
                'admin_editable' => true,
                'data_attributes' => [
                    'data-ws-field-key' => 'charter_day_notes',
                    'data-ws-form-section' => 'charter_day_plan',
                    'data-ws-help' => 'day_notes',
                    'data-ws-help-context' => 'shuttle_hire',
                    'data-wsb-context' => 'shuttle_hire',
                    'data-wsb-charter-day-field' => 'notes',
                ],
            ],
        ];

        return (array) apply_filters('wsb_booking_field_registry', $fields);
    }
}
