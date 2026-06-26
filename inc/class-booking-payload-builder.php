<?php

namespace WSB_Booking_Client;

if (!defined('ABSPATH')) {
    exit;
}

class BookingPayloadBuilder {
    /**
     * Build the canonical booking payload from normalized intake data.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function build(array $data): array {
        return [
            'schema_version' => '2.0',
            'source' => [
                'site' => 'marketing',
                'channel' => 'shortcode_form',
                'page_url' => $data['source']['page_url'] ?? '',
                'referrer' => $data['source']['referrer'] ?? '',
            ],
        ];
    }

    /**
     * Build the final handover payload. This is a phase 2 stub.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function buildHandoverPayload(array $payload): array {
        return $payload;
    }
}
