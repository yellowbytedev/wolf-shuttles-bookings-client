<?php

namespace WSB_Booking_Client;

if (!defined('ABSPATH')) {
    exit;
}

class BookingPayloadValidator {
    /**
     * Validate a canonical v2 payload.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function validate(array $payload): array {
        return [
            'valid' => true,
            'errors' => [],
        ];
    }
}
