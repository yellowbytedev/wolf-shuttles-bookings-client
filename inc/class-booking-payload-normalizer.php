<?php

namespace WSB_Booking_Client;

if (!defined('ABSPATH')) {
    exit;
}

class BookingPayloadNormalizer {
    /**
     * Normalize a raw v2 intake payload into canonical schema shape.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function normalize(array $payload): array {
        return $payload;
    }
}
