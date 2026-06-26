<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/booking-client-config.php';
require_once __DIR__ . '/class-booking-payload-builder.php';
require_once __DIR__ . '/class-booking-payload-normalizer.php';
require_once __DIR__ . '/class-booking-payload-validator.php';
require_once __DIR__ . '/class-booking-site-client.php';
require_once __DIR__ . '/class-booking-field-registry.php';
require_once __DIR__ . '/class-booking-client-form-shortcode.php';
require_once __DIR__ . '/class-booking-intake-fixture-loader.php';

add_action('init', function (): void {
    \WSB_Booking_Client\BookingClientFormShortcode::init();
    \WSB_Booking_Client\BookingIntakeFixtureLoader::init();
});
