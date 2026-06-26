<?php

namespace WSB_Booking_Client;

if (!defined('ABSPATH')) {
    exit;
}

class BookingClientFormShortcode {
    public static function init(): void {
        add_shortcode('ws_booking_client_form', [self::class, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function render_shortcode(array $atts = []): string {
        $fields = BookingFieldRegistry::get_fields();
        $title = __('Booking Builder', 'wsb');

        $atts = shortcode_atts([
            'title' => $title,
        ], $atts, 'ws_booking_client_form');

        ob_start();
        ?>
        <div class="wsb-booking-client-shell">
            <div class="wsb-booking-client-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <p><?php echo esc_html__('Build a new booking request. No real booking submission is enabled yet.', 'wsb'); ?></p>
            </div>

            <div class="wsb-booking-client-service-tabs" role="tablist">
                <button type="button" class="wsb-booking-client-service-tab wsb-booking-client-service-tab--active" data-service="transfer"><?php echo esc_html__('City Transfers', 'wsb'); ?></button>
                <button type="button" class="wsb-booking-client-service-tab" disabled><?php echo esc_html__('Shuttle Hire (coming soon)', 'wsb'); ?></button>
            </div>

            <form class="wsb-booking-client-form" method="post" action="#" novalidate>
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
                            <?php echo self::render_text_field($fields['outbound_to']); ?>
                            <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
                                <?php echo self::render_date_field($fields['outbound_pickup_date']); ?>
                                <?php echo self::render_time_field($fields['outbound_pickup_time']); ?>
                            </div>
                        </section>

                        <section class="wsb-booking-client-card wsb-booking-client-return wsb-booking-client-hidden">
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
                                <input type="checkbox" name="additional_stop_enabled" class="wsb-booking-client-additional-toggle">
                                <?php echo esc_html__('Enable additional stop', 'wsb'); ?>
                            </label>
                            <div class="wsb-booking-client-field wsb-booking-client-additional-stop-field wsb-booking-client-hidden">
                                <label class="wsb-form__label" for="<?php echo esc_attr($fields['additional_stop']['key']); ?>"><?php echo esc_html($fields['additional_stop']['label']); ?></label>
                                <input class="wsb-form__input" type="text" id="<?php echo esc_attr($fields['additional_stop']['key']); ?>" name="<?php echo esc_attr($fields['additional_stop']['key']); ?>" placeholder="<?php echo esc_attr($fields['additional_stop']['placeholder']); ?>" disabled />
                            </div>
                        </section>

                        <div class="wsb-booking-client-actions">
                            <button type="submit" class="wsb-booking-client-submit"><?php echo esc_html__('Preview booking payload', 'wsb'); ?></button>
                            <div class="wsb-booking-client-submit-message" aria-live="polite"></div>
                            <p class="wsb-booking-client-note"><?php echo esc_html__('This preview is local only; no real booking is submitted.', 'wsb'); ?></p>
                        </div>
                    </div>

                    <aside class="wsb-booking-client-preview-column">
                        <section class="wsb-booking-client-card wsb-booking-client-card--preview">
                            <div class="wsb-booking-client-card-header">
                                <h3><?php echo esc_html__('Payload preview', 'wsb'); ?></h3>
                            </div>
                            <div class="wsb-booking-client-preview-help"><?php echo esc_html__('Submit the form to render a BookingPayload v2 preview below.', 'wsb'); ?></div>
                            <pre class="wsb-booking-client-preview-json" aria-live="polite" tabindex="0"><?php echo esc_html__('No payload generated yet.', 'wsb'); ?></pre>
                        </section>
                    </aside>
                </div>
            </form>
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

    public static function enqueue_assets(): void {
        if (!is_a(self::get_screen_with_shortcode(), 'WP_Post')) {
            return;
        }

        wp_register_style(
            'wsb-booking-client-form-style',
            plugins_url('assets/css/booking-client-form.css', __DIR__ . '/../ws-bookings-client.php'),
            [],
            WSB_CLIENT_VERSION
        );

        wp_register_script(
            'wsb-booking-client-form-script',
            plugins_url('assets/js/booking-client-form.js', __DIR__ . '/../ws-bookings-client.php'),
            [],
            WSB_CLIENT_VERSION,
            true
        );

        wp_enqueue_style('wsb-booking-client-form-style');
        wp_enqueue_script('wsb-booking-client-form-script');
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
