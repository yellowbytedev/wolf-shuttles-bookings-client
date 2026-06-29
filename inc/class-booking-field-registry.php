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
            ],
            'passengers' => [
                'key' => 'passengers',
                'label' => __('Passengers', 'wsb'),
                'placeholder' => __('Number of passengers', 'wsb'),
                'type' => 'number',
                'required' => true,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'max_attr' => $max_passengers,
            ],
            'baby_seats' => [
                'key' => 'baby_seats',
                'label' => __('Baby seats', 'wsb'),
                'placeholder' => __('Number of baby seats', 'wsb'),
                'type' => 'number',
                'required' => false,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'max_attr' => $max_passengers,
            ],
            'check_in_bags' => [
                'key' => 'check_in_bags',
                'label' => __('Check-in bags', 'wsb'),
                'placeholder' => __('Number of check-in bags', 'wsb'),
                'type' => 'number',
                'required' => false,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'max_attr' => $max_bags,
            ],
            'carry_on_bags' => [
                'key' => 'carry_on_bags',
                'label' => __('Carry-on bags', 'wsb'),
                'placeholder' => __('Number of carry-on bags', 'wsb'),
                'type' => 'number',
                'required' => false,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
                'max_attr' => $max_bags,
            ],
            'trailer' => [
                'key' => 'trailer',
                'label' => __('Trailer required', 'wsb'),
                'placeholder' => __('Need a trailer?', 'wsb'),
                'type' => 'checkbox',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
            ],
            'oversize_luggage' => [
                'key' => 'oversize_luggage',
                'label' => __('Oversize luggage', 'wsb'),
                'placeholder' => __('Oversize luggage', 'wsb'),
                'type' => 'checkbox',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
            ],
            'outbound_from' => [
                'key' => 'outbound_from',
                'label' => __('From', 'wsb'),
                'placeholder' => __('Pick up from', 'wsb'),
                'type' => 'text',
                'required' => true,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
            ],
            'outbound_to' => [
                'key' => 'outbound_to',
                'label' => __('To', 'wsb'),
                'placeholder' => __('Drop off at', 'wsb'),
                'type' => 'text',
                'required' => true,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
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
                'date_max_attr' => $max_date_str,
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
            ],
            'return_from' => [
                'key' => 'return_from',
                'label' => __('Return from', 'wsb'),
                'placeholder' => __('Return pickup from', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
            ],
            'return_to' => [
                'key' => 'return_to',
                'label' => __('Return to', 'wsb'),
                'placeholder' => __('Return drop off at', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
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
            ],
            'additional_stop' => [
                'key' => 'additional_stop',
                'label' => __('Additional stop', 'wsb'),
                'placeholder' => __('Add an optional stop', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
            ],
            'outbound_additional_stop' => [
                'key' => 'outbound_additional_stop',
                'label' => __('Additional stop', 'wsb'),
                'placeholder' => __('Add an optional stop', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
            ],
            'return_additional_stop' => [
                'key' => 'return_additional_stop',
                'label' => __('Additional stop', 'wsb'),
                'placeholder' => __('Add an optional stop', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer', 'charter'],
                'admin_editable' => true,
            ],
            'charter_pickup_location' => [
                'key' => 'charter_pickup_location',
                'label' => __('Pickup location', 'wsb'),
                'placeholder' => __('Pickup address or area', 'wsb'),
                'type' => 'text',
                'required' => true,
                'applies_to' => ['charter'],
                'admin_editable' => true,
            ],
            'charter_dropoff_location' => [
                'key' => 'charter_dropoff_location',
                'label' => __('Drop-off location', 'wsb'),
                'placeholder' => __('Drop-off address or area', 'wsb'),
                'type' => 'text',
                'required' => true,
                'applies_to' => ['charter'],
                'admin_editable' => true,
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
            ],
            'charter_additional_stop' => [
                'key' => 'charter_additional_stop',
                'label' => __('Additional stop', 'wsb'),
                'placeholder' => __('Add an optional stop', 'wsb'),
                'type' => 'text',
                'required' => false,
                'applies_to' => ['transfer'],
                'admin_editable' => true,
            ],
        ];

        return (array) apply_filters('wsb_booking_field_registry', $fields);
    }
}