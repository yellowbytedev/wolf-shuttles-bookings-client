<?php

namespace WSB_Booking_Client;

if (!defined('ABSPATH')) {
    exit;
}

class BookingClientFormShortcode {
    public static function init(): void {
        add_shortcode('ws_booking_client_form', [self::class, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
    }

    public static function render_shortcode(array $atts = []): string {
        self::enqueue_assets();

        $fields = BookingFieldRegistry::get_fields();
        $title = __('Booking Builder', 'wsb');

        $atts = shortcode_atts([
            'title' => $title,
        ], $atts, 'ws_booking_client_form');

        $show_dev_drawer = self::should_show_dev_drawer();
        $fixtures = $show_dev_drawer ? self::get_dev_fixtures() : array();
        $fixtures_json = $show_dev_drawer ? (string) wp_json_encode($fixtures) : '[]';

        ob_start();
        ?>
        <div
            class="wsb-booking-client-shell"
            data-wsb-booking-builder
            data-wsb-service-group="transfer"
            data-wsb-service-type="city_transfer"
            <?php echo $show_dev_drawer && $fixtures_json ? 'data-wsb-fixtures="' . esc_attr($fixtures_json) . '"' : ''; ?>
        >
            <div class="wsb-booking-client-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <p><?php echo esc_html__('Build a new booking request. No real booking submission is enabled yet.', 'wsb'); ?></p>
            </div>

            <div class="wsb-booking-client-service-tabs" role="tablist">
                <button type="button" class="wsb-booking-client-service-tab wsb-booking-client-service-tab--active" data-service="transfer"><?php echo esc_html__('City Transfers', 'wsb'); ?></button>
                <button type="button" class="wsb-booking-client-service-tab" disabled><?php echo esc_html__('Shuttle Hire (coming soon)', 'wsb'); ?></button>
            </div>

            <form class="wsb-booking-client-form" data-wsb-booking-form method="post" action="#" novalidate>
                <div class="wsb-booking-client-grid">
                    <div class="wsb-booking-client-main-column">
                        <section class="wsb-booking-client-card wsb-booking-client-card--hero">
                            <div class="wsb-booking-client-card-header">
                                <div>
                                    <p class="wsb-booking-client-eyebrow"><?php echo esc_html__('Booking Builder', 'wsb'); ?></p>
                                    <h3><?php echo esc_html__('Create a local transfer preview', 'wsb'); ?></h3>
                                </div>
                                <span class="wsb-booking-client-badge"><?php echo esc_html__('Preview only', 'wsb'); ?></span>
                            </div>
                            <p class="wsb-booking-client-card-copy"><?php echo esc_html__('This form builds a local BookingPayload v2 preview. Real booking submission is not enabled yet.', 'wsb'); ?></p>
                            <div class="wsb-booking-client-preview-summary" data-wsb-payload-summary>
                                <p class="wsb-booking-client-preview-summary-text"><?php echo esc_html__('Live payload preview active', 'wsb'); ?></p>
                                <p class="wsb-booking-client-preview-status" data-wsb-preview-status><?php echo esc_html__('Waiting for updates...', 'wsb'); ?></p>
                            </div>
                        </section>

                        <section class="wsb-booking-client-card">
                            <div class="wsb-booking-client-card-header">
                                <h3><?php echo esc_html__('Trip settings', 'wsb'); ?></h3>
                            </div>
                            <div class="wsb-booking-client-pill-group" role="group" aria-label="<?php echo esc_attr__('Trip type', 'wsb'); ?>">
                                <label class="wsb-booking-client-pill">
                                    <input type="radio" name="trip_type" value="one_way" checked>
                                    <span><?php echo esc_html__('One-way', 'wsb'); ?></span>
                                </label>
                                <label class="wsb-booking-client-pill">
                                    <input type="radio" name="trip_type" value="return">
                                    <span><?php echo esc_html__('Return', 'wsb'); ?></span>
                                </label>
                            </div>

                            <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
                                <?php echo self::render_number_field($fields['passengers']); ?>
                                <?php echo self::render_number_field($fields['baby_seats']); ?>
                                <?php echo self::render_number_field($fields['check_in_bags']); ?>
                                <?php echo self::render_number_field($fields['carry_on_bags']); ?>
                            </div>

                            <div class="wsb-booking-client-addons-row">
                                <label class="wsb-booking-client-checkbox-label">
                                    <input type="checkbox" name="trailer">
                                    <?php echo esc_html($fields['trailer']['label'] ?? __('Trailer required', 'wsb')); ?>
                                </label>
                                <label class="wsb-booking-client-checkbox-label">
                                    <input type="checkbox" name="oversize_luggage">
                                    <?php echo esc_html($fields['oversize_luggage']['label'] ?? __('Oversize luggage', 'wsb')); ?>
                                </label>
                            </div>
                        </section>

<section class="wsb-booking-client-card">
                             <div class="wsb-booking-client-card-header">
                                 <h3><?php echo esc_html__('Outbound leg', 'wsb'); ?></h3>
                             </div>
                             <?php echo self::render_text_field($fields['outbound_from']); ?>
                             <label class="wsb-booking-client-checkbox-label wsb-booking-client-additional-toggle-label">
                                 <input type="checkbox" name="additional_stop_enabled" class="wsb-booking-client-additional-toggle" data-wsb-additional-stop-toggle>
                                 <?php echo esc_html__('Enable additional stop', 'wsb'); ?>
                             </label>
                             <div class="wsb-booking-client-field wsb-booking-client-additional-stop-field wsb-booking-client-hidden" data-wsb-additional-stop-section>
                                 <label class="wsb-form__label" for="<?php echo esc_attr($fields['additional_stop']['key']); ?>"><?php echo esc_html($fields['additional_stop']['label']); ?></label>
                                 <input class="wsb-form__input" type="text" id="<?php echo esc_attr($fields['additional_stop']['key']); ?>" name="<?php echo esc_attr($fields['additional_stop']['key']); ?>" placeholder="<?php echo esc_attr($fields['additional_stop']['placeholder']); ?>" disabled />
                             </div>
                             <?php echo self::render_text_field($fields['outbound_to']); ?>
                             <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
                                 <?php echo self::render_date_field($fields['outbound_pickup_date']); ?>
                                 <?php echo self::render_time_field($fields['outbound_pickup_time']); ?>
                             </div>
                         </section>

                        <section class="wsb-booking-client-card wsb-booking-client-return wsb-booking-client-hidden" data-wsb-return-section>
                            <div class="wsb-booking-client-card-header">
                                <h3><?php echo esc_html__('Return leg', 'wsb'); ?></h3>
                            </div>
                            <?php echo self::render_text_field($fields['return_from'], false); ?>
                            <?php echo self::render_text_field($fields['return_to'], false); ?>
                            <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
                                <?php echo self::render_date_field($fields['return_pickup_date'], false); ?>
                                <?php echo self::render_time_field($fields['return_pickup_time'], false); ?>
                            </div>
                        </section>

                        <section class="wsb-booking-client-card wsb-booking-client-card--secondary">
                            <div class="wsb-booking-client-card-header">
                                <h3><?php echo esc_html__('Additional stop', 'wsb'); ?></h3>
                            </div>
                            <label class="wsb-booking-client-checkbox-label wsb-booking-client-additional-toggle-label">
                                <input type="checkbox" name="additional_stop_enabled" class="wsb-booking-client-additional-toggle" data-wsb-additional-stop-toggle>
                                <?php echo esc_html__('Enable additional stop', 'wsb'); ?>
                            </label>
                            <div class="wsb-booking-client-field wsb-booking-client-additional-stop-field wsb-booking-client-hidden" data-wsb-additional-stop-section>
                                <label class="wsb-form__label" for="<?php echo esc_attr($fields['additional_stop']['key']); ?>"><?php echo esc_html($fields['additional_stop']['label']); ?></label>
                                <input class="wsb-form__input" type="text" id="<?php echo esc_attr($fields['additional_stop']['key']); ?>" name="<?php echo esc_attr($fields['additional_stop']['key']); ?>" placeholder="<?php echo esc_attr($fields['additional_stop']['placeholder']); ?>" disabled />
                            </div>
                        </section>

                        <div class="wsb-booking-client-actions">
                            <button type="submit" class="wsb-booking-client-submit" data-wsb-preview-submit><?php echo esc_html__('Preview booking payload', 'wsb'); ?></button>
                            <div class="wsb-booking-client-submit-message" aria-live="polite" data-wsb-submit-message></div>
                            <p class="wsb-booking-client-note"><?php echo esc_html__('This preview is local only; no real booking is submitted.', 'wsb'); ?></p>
                        </div>
                    </div>

                    <aside class="wsb-booking-client-preview-column">
                        <section class="wsb-booking-client-card wsb-booking-client-card--preview">
                            <div class="wsb-booking-client-card-header">
                                <h3><?php echo esc_html__('Payload preview', 'wsb'); ?></h3>
                            </div>
                            <div class="wsb-booking-client-preview-help"><?php echo esc_html__('Submit the form to render a BookingPayload v2 preview and server-side validation response below.', 'wsb'); ?></div>
                            <div class="wsb-booking-client-validation" data-wsb-validation-output aria-live="polite"></div>
                            <pre class="wsb-booking-client-preview-json" data-wsb-payload-preview aria-live="polite" tabindex="0"><?php echo esc_html__('No payload generated yet.', 'wsb'); ?></pre>
                        </section>
                    </aside>
                </div>
            </form>
            <?php if ($show_dev_drawer && !empty($fixtures)): ?>
                <button
                    type="button"
                    class="wsb-booking-client-fixture-toggle"
                    data-wsb-fixture-toggle
                    aria-expanded="false"
                    aria-controls="wsb-booking-client-fixture-drawer"
                >
                    <?php echo esc_html__('Test payloads', 'wsb'); ?>
                </button>
                <aside
                    class="wsb-booking-client-fixture-drawer wsb-booking-client-hidden"
                    data-wsb-fixture-drawer
                    id="wsb-booking-client-fixture-drawer"
                >
                    <div class="wsb-booking-client-fixture-drawer-inner">
                        <div class="wsb-booking-client-fixture-header">
                            <div>
                                <strong><?php echo esc_html__('Developer Fixture Drawer', 'wsb'); ?></strong>
                                <p><?php echo esc_html__('Click a fixture to populate the form and run preview checks locally.', 'wsb'); ?></p>
                            </div>
                            <button type="button" class="wsb-booking-client-fixture-close" data-wsb-fixture-close>
                                <?php echo esc_html__('Close', 'wsb'); ?>
                            </button>
                        </div>
                        <div class="wsb-booking-client-fixture-status" data-wsb-fixture-status aria-live="polite">
                            <?php echo esc_html__('Choose a fixture to load sample payload data.', 'wsb'); ?>
                        </div>
                        <div class="wsb-booking-client-fixture-list" data-wsb-fixture-list>
                            <?php foreach ($fixtures as $fixture): ?>
                                <button
                                    type="button"
                                    class="wsb-booking-client-fixture-chip"
                                    data-wsb-fixture-chip
                                    data-wsb-fixture-id="<?php echo esc_attr($fixture['id']); ?>"
                                    data-wsb-fixture-expected="<?php echo esc_attr(!empty($fixture['expected_ok']) ? 'valid' : 'invalid'); ?>"
                                >
                                    <span class="wsb-booking-client-fixture-chip-id"><?php echo esc_html($fixture['id']); ?></span>
                                    <span class="wsb-booking-client-fixture-chip-desc"><?php echo esc_html($fixture['description']); ?></span>
                                    <span class="wsb-booking-client-fixture-chip-badge wsb-booking-client-fixture-chip-badge--<?php echo esc_attr(!empty($fixture['expected_ok']) ? 'valid' : 'invalid'); ?>">
                                        <?php echo esc_html(!empty($fixture['expected_ok']) ? 'valid' : 'invalid'); ?>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_number_field(array $field): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $required = !empty($field['required']) ? 'required' : '';
        return sprintf(
            '<div class="wsb-booking-client-field"><label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="number" id="%1$s" name="%1$s" min="0" value="0" placeholder="%3$s" %4$s /></div>',
            esc_attr($field['key']),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required
        );
    }

    private static function render_text_field(array $field, bool $required = true): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        return sprintf(
            '<div class="wsb-booking-client-field"><label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="text" id="%1$s" name="%1$s" placeholder="%3$s" %4$s /></div>',
            esc_attr($field['key']),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : ''
        );
    }

    private static function render_date_field(array $field, bool $required = true): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        return sprintf(
            '<div class="wsb-booking-client-field"><label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="date" id="%1$s" name="%1$s" placeholder="%3$s" %4$s /></div>',
            esc_attr($field['key']),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : ''
        );
    }

    private static function render_time_field(array $field, bool $required = true): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        return sprintf(
            '<div class="wsb-booking-client-field"><label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="time" id="%1$s" name="%1$s" placeholder="%3$s" %4$s /></div>',
            esc_attr($field['key']),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : ''
        );
    }

    public static function register_assets(): void {
        $css_path = plugin_dir_path(__DIR__) . 'assets/css/booking-client-form.css';
        $js_path = plugin_dir_path(__DIR__) . 'assets/js/booking-client-form.js';
        $handover_preview_url = rest_url('ws-bookings-client/v1/handover-preview');

        wp_register_style(
            'wsb-booking-client-form-style',
            plugins_url('assets/css/booking-client-form.css', __DIR__ . '/../ws-bookings-client.php'),
            [],
            file_exists($css_path) ? filemtime($css_path) : WSB_CLIENT_VERSION
        );

        wp_register_script(
            'wsb-booking-client-form-script',
            plugins_url('assets/js/booking-client-form.js', __DIR__ . '/../ws-bookings-client.php'),
            [],
            file_exists($js_path) ? filemtime($js_path) : WSB_CLIENT_VERSION,
            true
        );

        $preview_url = rest_url('ws-bookings-client/v1/payload-preview');
        $config = array(
            'previewUrl' => esc_url_raw($preview_url),
            'handoverPreviewUrl' => esc_url_raw($handover_preview_url),
            'nonce' => wp_create_nonce('wp_rest'),
            'debug' => (bool) current_user_can('manage_options'),
            'fixtureDrawerEnabled' => (bool) ( isset($_GET['debug']) && '1' === (string) $_GET['debug'] ),
            'strings' => array(
                'serverValidationPending' => __('Validating payload on server...', 'wsb'),
                'serverValidationSuccess' => __('Server validation passed.', 'wsb'),
                'serverValidationWarnings' => __('Server validation passed with warnings.', 'wsb'),
                'serverValidationFailed' => __('Server validation failed.', 'wsb'),
                'serverPreviewUnavailable' => __('Server-side preview endpoint is unavailable.', 'wsb'),
                'serverPreviewError' => __('Server preview could not be completed.', 'wsb'),
                'fixtureDrawerDefault' => __('Choose a fixture to load sample payload data.', 'wsb'),
                'fixtureDrawerLoaded' => __('Loaded fixture:', 'wsb'),
                'fixtureDrawerExpected' => __('Expected:', 'wsb'),
                'fixtureDrawerServerMatched' => __('Server validation matched expected result.', 'wsb'),
                'fixtureDrawerServerMismatch' => __('Server validation did not match expected result.', 'wsb'),
                'fixtureDrawerHandoverMatched' => __('Handover preview matched expected result.', 'wsb'),
                'fixtureDrawerHandoverMismatch' => __('Handover preview did not match expected result.', 'wsb'),
                'fixtureDrawerHandoverUnavailable' => __('Handover preview endpoint unavailable.', 'wsb'),
                'fixtureDrawerOpen' => __('Fixture drawer opened.', 'wsb'),
                'fixtureDrawerClosed' => __('Fixture drawer closed.', 'wsb'),
            ),
        );

        wp_add_inline_script('wsb-booking-client-form-script', 'window.WSB_BOOKING_CLIENT_FORM = ' . wp_json_encode($config) . ';', 'before');
    }

    public static function enqueue_assets(): void {
        self::register_assets();
        wp_enqueue_style('wsb-booking-client-form-style');
        wp_enqueue_script('wsb-booking-client-form-script');
    }

    private static function should_show_dev_drawer(): bool {
        return isset($_GET['debug']) && '1' === (string) $_GET['debug'];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_dev_fixtures(): array {
        $fixture_file = plugin_dir_path(__DIR__) . 'tests/fixtures/booking-payload-v2-fixtures.json';
        if (!file_exists($fixture_file)) {
            return array();
        }

        $fixtures_json = file_get_contents($fixture_file);
        if (false === $fixtures_json) {
            return array();
        }

        $fixtures = json_decode($fixtures_json, true);
        if (!is_array($fixtures)) {
            return array();
        }

        $safe = array();
        foreach ($fixtures as $fixture) {
            $safe[] = array(
                'id' => sanitize_key($fixture['id'] ?? ''),
                'description' => sanitize_text_field($fixture['description'] ?? ''),
                'expected_ok' => !empty($fixture['expected_ok']),
                'payload' => is_array($fixture['payload'] ?? null) ? $fixture['payload'] : array(),
            );
        }

        return $safe;
    }

    private static function get_dev_fixtures_json(): string {
        return (string) wp_json_encode(self::get_dev_fixtures());
    }

    private static function get_screen_with_shortcode() {
        global $post;

        if (!isset($post) || !is_singular()) {
            return null;
        }

        if (has_shortcode($post->post_content ?? '', 'ws_booking_client_form')) {
            return $post;
        }

        return null;
    }
}
