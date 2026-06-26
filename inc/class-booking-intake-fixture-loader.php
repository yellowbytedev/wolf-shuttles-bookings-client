<?php

namespace WSB_Booking_Client;

if (!defined('ABSPATH')) {
    exit;
}

class BookingIntakeFixtureLoader {
    public static function init(): void {
        add_action('admin_init', [self::class, 'register_fixture_test_page']);
    }

    public static function register_fixture_test_page(): void {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        if (!class_exists('\WP_CLI')) {
            return;
        }

        \WP_CLI::add_command('wsb-intake fixture', [self::class, 'run_fixture']);
    }

    public static function run_fixture(array $args, array $assoc_args): void {
        $file = plugin_dir_path(__DIR__) . 'tests/fixtures/booking-intake-fixtures.v2.seed.json';
        if (!is_readable($file)) {
            \WP_CLI::error('Fixture file not found: ' . $file);
            return;
        }

        $data = json_decode(file_get_contents($file) ?: '', true);
        if (!is_array($data) || empty($data['fixtures'])) {
            \WP_CLI::error('Invalid fixture file.');
            return;
        }

        foreach ($data['fixtures'] as $fixture) {
            \WP_CLI::line(sprintf('Fixture: %s', $fixture['id'] ?? 'unknown'));
        }
    }
}
