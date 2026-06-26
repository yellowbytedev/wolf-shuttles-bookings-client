<?php

namespace WSB_Booking_Client;

if (!defined('ABSPATH')) {
    exit;
}

class BookingSiteClient {
    /**
     * Return the configured booking site endpoint for handover.
     *
     * @return string
     */
    public function get_handover_endpoint(): string {
        return 'https://bookings.wolfshuttles.local/';
    }

    /**
     * Build the handover URL for the given payload and mode.
     *
     * @param array<string,mixed> $payload
     * @param string $mode
     * @return string
     */
    public function build_handover_url(array $payload, string $mode): string {
        $endpoint = untrailingslashit($this->get_handover_endpoint()) . '/';
        if ($mode === 'v2_token') {
            return add_query_arg([
                'booking_token' => 'placeholder',
                'trip_id' => 'placeholder',
            ], $endpoint);
        }
        return add_query_arg('hash', 'placeholder', $endpoint);
    }
}
