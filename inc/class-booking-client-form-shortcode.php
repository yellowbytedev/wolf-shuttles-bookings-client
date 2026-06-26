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
                <fieldset class="wsb-booking-client-fieldset">
                    <legend class="wsb-booking-client-legend"><?php echo esc_html($fields['trip_type']['label'] ?? __('Trip type', 'wsb')); ?></legend>
                    <label class="wsb-booking-client-radio-label">
                        <input type="radio" name="trip_type" value="one_way" checked>
                        <?php echo esc_html__('One-way', 'wsb'); ?>
                    </label>
                    <label class="wsb-booking-client-radio-label">
                        <input type="radio" name="trip_type" value="return">
                        <?php echo esc_html__('Return', 'wsb'); ?>
                    </label>
                </fieldset>

                <div class="wsb-booking-client-columns">
                    <div class="wsb-booking-client-column">
                        <?php echo self::render_number_field($fields['passengers']); ?>
                        <?php echo self::render_number_field($fields['baby_seats']); ?>
                        <?php echo self::render_number_field($fields['check_in_bags']); ?>
                        <?php echo self::render_number_field($fields['carry_on_bags']); ?>
                    </div>
                    <div class="wsb-booking-client-column">
                        <fieldset class="wsb-booking-client-fieldset wsb-booking-client-addons">
                            <legend class="wsb-booking-client-legend"><?php echo esc_html__('Add-ons', 'wsb'); ?></legend>
                            <label class="wsb-booking-client-checkbox-label">
                                <input type="checkbox" name="trailer">
                                <?php echo esc_html($fields['trailer']['label'] ?? __('Trailer required', 'wsb')); ?>
                            </label>
                            <label class="wsb-booking-client-checkbox-label">
                                <input type="checkbox" name="oversize_luggage">
                                <?php echo esc_html($fields['oversize_luggage']['label'] ?? __('Oversize luggage', 'wsb')); ?>
                            </label>
                        </fieldset>
                    </div>
                </div>

                <div class="wsb-booking-client-section">
                    <h3><?php echo esc_html__('Outbound leg', 'wsb'); ?></h3>
                    <?php echo self::render_text_field($fields['outbound_from']); ?>
                    <?php echo self::render_text_field($fields['outbound_to']); ?>
                    <?php echo self::render_date_field($fields['outbound_pickup_date']); ?>
                    <?php echo self::render_time_field($fields['outbound_pickup_time']); ?>
                </div>

                <div class="wsb-booking-client-section wsb-booking-client-return wsb-booking-client-hidden">
                    <h3><?php echo esc_html__('Return leg', 'wsb'); ?></h3>
                    <?php echo self::render_text_field($fields['return_from'], false); ?>
                    <?php echo self::render_text_field($fields['return_to'], false); ?>
                    <?php echo self::render_date_field($fields['return_pickup_date'], false); ?>
                    <?php echo self::render_time_field($fields['return_pickup_time'], false); ?>
                </div>

                <fieldset class="wsb-booking-client-fieldset wsb-booking-client-additional-stop-fieldset">
                    <legend class="wsb-booking-client-legend"><?php echo esc_html__('Additional stop', 'wsb'); ?></legend>
                    <label class="wsb-booking-client-checkbox-label">
                        <input type="checkbox" name="additional_stop_enabled" class="wsb-booking-client-additional-toggle">
                        <?php echo esc_html__('Enable additional stop', 'wsb'); ?>
                    </label>
                    <input class="wsb-form__input wsb-booking-client-hidden" type="text" name="additional_stop" placeholder="<?php echo esc_attr($fields['additional_stop']['placeholder'] ?? __('Add an optional stop', 'wsb')); ?>" disabled />
                </fieldset>

                <p class="wsb-booking-client-note"><?php echo esc_html__('An early Booking Builder preview only. No real submission is enabled yet.', 'wsb'); ?></p>
                <button type="submit" class="wsb-booking-client-submit"><?php echo esc_html__('Check Pricing & Availability', 'wsb'); ?></button>
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
            '<label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="number" id="%1$s" name="%1$s" min="0" value="0" placeholder="%3$s" %4$s />',
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
            '<label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="text" id="%1$s" name="%1$s" placeholder="%3$s" %4$s />',
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
            '<label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="date" id="%1$s" name="%1$s" placeholder="%3$s" %4$s />',
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
            '<label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="time" id="%1$s" name="%1$s" placeholder="%3$s" %4$s />',
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
