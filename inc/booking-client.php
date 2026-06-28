<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/booking-client-config.php';
require_once __DIR__ . '/class-booking-payload-builder.php';
require_once __DIR__ . '/class-booking-payload-normalizer.php';
require_once __DIR__ . '/class-booking-payload-validator.php';
require_once __DIR__ . '/class-booking-payload-v2-normalizer.php';
require_once __DIR__ . '/class-booking-payload-v2-validator.php';
require_once __DIR__ . '/class-booking-payload-preview-controller.php';
require_once __DIR__ . '/class-booking-site-client.php';
require_once __DIR__ . '/class-booking-payload-v2-handover-service.php';
require_once __DIR__ . '/class-booking-payload-handover-preview-controller.php';
require_once __DIR__ . '/class-booking-field-registry.php';
require_once __DIR__ . '/class-booking-client-form-shortcode.php';
require_once __DIR__ . '/class-booking-intake-fixture-loader.php';
require_once __DIR__ . '/class-booking-external-services.php';

add_action('init', function (): void {
    \WSB_Booking_Client\BookingClientFormShortcode::init();
    \WSB_Booking_Client\BookingIntakeFixtureLoader::init();

    $preview_controller = new \WSB_Client_Booking_Payload_V2_Preview_Controller(
        new \WSB_Client_Booking_Payload_V2_Normalizer(),
        new \WSB_Client_Booking_Payload_V2_Validator()
    );
    $preview_controller->register();

    $handover_service = new \WSB_Client_Booking_Payload_V2_Handover_Service(
        wsb_client_v2_handover_secret()
    );
    $handover_controller = new \WSB_Client_Booking_Payload_V2_Handover_Preview_Controller(
        new \WSB_Client_Booking_Payload_V2_Normalizer(),
        new \WSB_Client_Booking_Payload_V2_Validator(),
        $handover_service
    );
    $handover_controller->register();
});
