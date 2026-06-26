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

        $showReturn = false;
        $returnHiddenClass = $showReturn ? '' : 'wsb-booking-client-hidden';
        $additionalStopHiddenClass = 'wsb-booking-client-hidden';

        return sprintf(
            '<div class="wsb-booking-client-shell">
                <div class="wsb-booking-client-header">
                    <h2>%s</h2>
                    <p>%s</p>
                </div>
                <div class="wsb-booking-client-service-tabs" role="tablist">
                    <button type="button" class="wsb-booking-client-service-tab wsb-booking-client-service-tab--active" data-service="transfer">%s</button>
                    <button type="button" class="wsb-booking-client-service-tab" disabled>%s</button>
                </div>
                <form class="wsb-booking-client-form" method="post" action="#" novalidate>
                    <fieldset class="wsb-booking-client-fieldset">
                        <legend class="wsb-booking-client-legend">%s</legend>
                        <label class="wsb-booking-client-radio-label"><input type="radio" name="trip_type" value="one_way" checked>%s</label>
                        <label class="wsb-booking-client-radio-label"><input type="radio" name="trip_type" value="return">%s</label>
                    </fieldset>

                    <div class="wsb-booking-client-columns">
                        <div class="wsb-booking-client-column">
                            %s
                            %s
                            %s
                            %s
                        </div>
                        <div class="wsb-booking-client-column">
                            %s
                            %s
                        </div>
                    </div>

                    <fieldset class="wsb-booking-client-fieldset wsb-booking-client-addons">
                        <legend class="wsb-booking-client-legend">%s</legend>
                        <label class="wsb-booking-client-checkbox-label"><input type="checkbox" name="trailer"> %s</label>
                        <label class="wsb-booking-client-checkbox-label"><input type="checkbox" name="oversize_luggage"> %s</label>
                    </fieldset>

                    <div class="wsb-booking-client-section">
                        <h3>%s</h3>
                        %s
                        %s
                        %s
                        %s
                    </div>

                    <div class="wsb-booking-client-section wsb-booking-client-return %s">
                        <h3>%s</h3>
                        %s
                        %s
                        %s
                        %s
                    </div>

                    <fieldset class="wsb-booking-client-fieldset wsb-booking-client-additional-stop-fieldset">
                        <legend class="wsb-booking-client-legend">%s</legend>
                        <label class="wsb-booking-client-checkbox-label">
                            <input type="checkbox" name="additional_stop_enabled" class="wsb-booking-client-additional-toggle"> %s
                        </label>
                        <input class="wsb-form__input %s" type="text" name="additional_stop" placeholder="%s" disabled />
                    </fieldset>

                    <p class="wsb-booking-client-note">%s</p>
                    <button type="submit" class="wsb-booking-client-submit">%s</button>
                </form>
            </div>',
            esc_html($atts['title']),
            esc_html__('Build a new booking request. No real booking submission is enabled yet.', 'wsb'),
            esc_html__('City Transfers', 'wsb'),
            esc_html__('Shuttle Hire (coming soon)', 'wsb'),
            esc_html($fields['trip_type']['label'] ?? __('Trip type', 'wsb')),
            esc_html__('One-way', 'wsb'),
            esc_html__('Return', 'wsb'),
            $fields['passengers']['label'] ? sprintf('<label class="wsb-form__label">%s</label><input class="wsb-form__input" type="number" name="passengers" min="1" value="1" placeholder="%s" required />', esc_html($fields['passengers']['label']), esc_attr($fields['passengers']['placeholder'])) : '',
            $fields['baby_seats']['label'] ? sprintf('<label class="wsb-form__label">%s</label><input class="wsb-form__input" type="number" name="baby_seats" min="0" value="0" placeholder="%s" />', esc_html($fields['baby_seats']['label']), esc_attr($fields['baby_seats']['placeholder'])) : '',
            $fields['check_in_bags']['label'] ? sprintf('<label class="wsb-form__label">%s</label><input class="wsb-form__input" type="number" name="check_in_bags" min="0" value="0" placeholder="%s" />', esc_html($fields['check_in_bags']['label']), esc_attr($fields['check_in_bags']['placeholder'])) : '',
            $fields['carry_on_bags']['label'] ? sprintf('<label class="wsb-form__label">%s</label><input class="wsb-form__input" type="number" name="carry_on_bags" min="0" value="0" placeholder="%s" />', esc_html($fields['carry_on_bags']['label']), esc_attr($fields['carry_on_bags']['placeholder'])) : '',
            esc_html($fields['outbound_from']['label'] ?? __('From', 'wsb')),
            sprintf('<input class="wsb-form__input" type="text" name="outbound_from" placeholder="%s" required />', esc_attr($fields['outbound_from']['placeholder'] ?? __('Pick up from', 'wsb'))),
            sprintf('<label class="wsb-form__label">%s</label><input class="wsb-form__input" type="text" name="outbound_to" placeholder="%s" required />', esc_html($fields['outbound_to']['label'] ?? __('To', 'wsb')), esc_attr($fields['outbound_to']['placeholder'] ?? __('Drop off at', 'wsb'))),
            sprintf('<label class="wsb-form__label">%s</label><input class="wsb-form__input" type="date" name="outbound_pickup_date" placeholder="%s" required />', esc_html($fields['outbound_pickup_date']['label'] ?? __('Pickup date', 'wsb')), esc_attr($fields['outbound_pickup_date']['placeholder'] ?? __('Select pickup date', 'wsb'))),
            sprintf('<label class="wsb-form__label">%s</label><input class="wsb-form__input" type="time" name="outbound_pickup_time" placeholder="%s" required />', esc_html($fields['outbound_pickup_time']['label'] ?? __('Pickup time', 'wsb')), esc_attr($fields['outbound_pickup_time']['placeholder'] ?? __('Select pickup time', 'wsb'))),
            $returnHiddenClass,
            esc_html__('Return leg', 'wsb'),
            sprintf('<label class="wsb-form__label">%s</label><input class="wsb-form__input" type="text" name="return_from" placeholder="%s" />', esc_html($fields['return_from']['label'] ?? __('Return from', 'wsb')), esc_attr($fields['return_from']['placeholder'] ?? __('Return pickup from', 'wsb'))),
            sprintf('<label class="wsb-form__label">%s</label><input class="wsb-form__input" type="text" name="return_to" placeholder="%s" />', esc_html($fields['return_to']['label'] ?? __('Return to', 'wsb')), esc_attr($fields['return_to']['placeholder'] ?? __('Return drop off at', 'wsb'))),
            sprintf('<label class="wsb-form__label">%s</label><input class="wsb-form__input" type="date" name="return_pickup_date" placeholder="%s" />', esc_html($fields['return_pickup_date']['label'] ?? __('Return date', 'wsb')), esc_attr($fields['return_pickup_date']['placeholder'] ?? __('Select return date', 'wsb'))),
            sprintf('<label class="wsb-form__label">%s</label><input class="wsb-form__input" type="time" name="return_pickup_time" placeholder="%s" />', esc_html($fields['return_pickup_time']['label'] ?? __('Return time', 'wsb')), esc_attr($fields['return_pickup_time']['placeholder'] ?? __('Select return time', 'wsb'))),
            esc_html__('Additional stop', 'wsb'),
            esc_html__('Enable additional stop', 'wsb'),
            $additionalStopHiddenClass,
            esc_attr($fields['additional_stop']['placeholder'] ?? __('Add an optional stop', 'wsb')),
            esc_html__('An early Booking Builder preview only. No real submission is enabled yet.', 'wsb'),
            esc_html__('Check Pricing & Availability', 'wsb')
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
