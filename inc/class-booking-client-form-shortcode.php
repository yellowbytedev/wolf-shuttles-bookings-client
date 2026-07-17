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
        $title = __('Book a Ride', 'wsb');

        $atts = shortcode_atts([
            'title' => $title,
        ], $atts, 'ws_booking_client_form');

        $show_dev_drawer = self::should_show_dev_drawer();
        $fixtures = $show_dev_drawer ? self::get_dev_fixtures() : array();
        $gates = \WSB_Booking_Client\Booking_Feature_Gates::all();
        $multi_day_enabled = !empty($gates['enable_multi_day_charters']);

        ob_start();
        ?>
        <div class="wsb-booking-client-shell<?php echo $show_dev_drawer ? ' wb-debug' : ''; ?>" data-wsb-booking-builder data-wsb-service-group="transfer" data-wsb-service-type="city_transfer" data-wsb-fixtures="<?php echo esc_attr(wp_json_encode($fixtures)); ?>">
            <?php if ($show_dev_drawer): ?>
            <div class="wsb-booking-client-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <p><?php echo esc_html__('Plan your booking details below.', 'wsb'); ?></p>
            </div>
            <?php endif; ?>

            <div class="wsb-tabs wsb-booking-client-service-tabs" role="tablist" aria-label="<?php echo esc_attr__('Booking type', 'wsb'); ?>">
                <button type="button" class="wsb-booking-client-service-tab wsb-booking-client-service-tab--active" data-service="transfer" data-wsb-service-tab="transfer"><?php echo self::render_ui_icon('car'); ?><span><?php echo esc_html__('Book a Ride', 'wsb'); ?></span></button>
                <button type="button" class="wsb-booking-client-service-tab" data-service="charter" data-wsb-service-tab="charter"><?php echo self::render_ui_icon('users'); ?><span><?php echo esc_html__('Shuttle Hire', 'wsb'); ?></span></button>
                <button type="button" class="wsb-booking-client-service-tab" data-service="plan" data-wsb-service-tab="plan" data-ws-feature-gate="enable_multi_trip_bookings"><?php echo self::render_ui_icon('route'); ?><span><?php echo esc_html__('Plan Full Itinerary', 'wsb'); ?></span></button>
            </div>

            <form class="wsb-booking-client-form" data-wsb-booking-form method="post" action="#" novalidate>
                <div class="wsb-booking-client-grid wsb-form-layout">
                    <div class="wsb-booking-client-main-column">
                        <section class="wsb-booking-client-card wsb-form-shell wsb-booking-client-transfer-only" data-wsb-transfer-fields>

                            <div class="wsb-booking-client-mode-row wsb-top-controls">
                                <div class="wsb-segmented-control wsb-segmented-control--two wsb-segmented-switch wsb-booking-client-pill-group" role="radiogroup" aria-label="<?php echo esc_attr__('Trip type', 'wsb'); ?>">
                                    <label class="wsb-booking-client-pill wsb-booking-client-pill--radio">
                                        <input type="radio" name="trip_type" value="one_way" checked>
                                        <span><?php echo esc_html__('One-way', 'wsb'); ?></span>
                                    </label>
                                    <label class="wsb-booking-client-pill wsb-booking-client-pill--radio">
                                        <input type="radio" name="trip_type" value="return">
                                        <span><?php echo esc_html__('Return', 'wsb'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--options">
                                <?php echo self::render_number_field($fields['passengers'], 'book-a-ride'); ?>
                                <?php echo self::render_number_field($fields['check_in_bags'], 'book-a-ride'); ?>
                                <?php echo self::render_number_field($fields['carry_on_bags'], 'book-a-ride'); ?>
                                 <?php echo self::render_number_field($fields['baby_seats'], 'book-a-ride'); ?>
                            </div>

                            <div class="wsb-booking-client-addons-row wsb-booking-client-addons-row--below">
                                <?php echo self::render_checkbox_option($fields['trailer'] ?? array('key' => 'trailer', 'label' => __('Trailer required', 'wsb')), 'trailer'); ?>
                                <?php echo self::render_checkbox_option($fields['oversize_luggage'] ?? array('key' => 'oversize_luggage', 'label' => __('Oversize luggage', 'wsb')), 'oversize_luggage'); ?>
                            </div>

                            <div class="wsb-route-stack wsb-location-path" data-wsb-location-path="outbound" data-wsb-outbound-section>
                                <?php echo self::render_text_field($fields['outbound_from'], 'book-a-ride'); ?>
                                <?php echo self::render_additional_stop_toggle('outbound_additional_stop_enabled', 'data-wsb-outbound-additional-stop-toggle', __('Add additional stop', 'wsb')); ?>
                                <?php echo self::render_additional_stop_field($fields['outbound_additional_stop'], 'book-a-ride', 'data-wsb-outbound-additional-stop-section'); ?>
                                <?php echo self::render_text_field($fields['outbound_to'], 'book-a-ride'); ?>
                            </div>

                            <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--datetime">
                                <?php echo self::render_date_field($fields['outbound_pickup_date'], 'book-a-ride'); ?>
                                <?php echo self::render_time_field($fields['outbound_pickup_time'], 'book-a-ride'); ?>
                            </div>

                            <section class="wsb-return-accordion wsb-booking-client-return wsb-booking-client-return--accordion wsb-booking-client-hidden" data-wsb-return-section data-wsb-return-accordion>
                                <button type="button" class="wsb-booking-client-return-toggle" data-wsb-return-accordion-toggle aria-expanded="true">
                                    <span><?php echo esc_html__('Return details', 'wsb'); ?></span>
                                    <span class="wsb-booking-client-return-toggle-icon" aria-hidden="true"></span>
                                </button>
                                <div class="wsb-booking-client-return-body" data-wsb-return-body>
                                    <div class="wsb-route-stack wsb-location-path" data-wsb-location-path="return">
                                        <?php echo self::render_text_field($fields['return_from'], 'book-a-ride', false); ?>
                                        <?php echo self::render_additional_stop_toggle('return_additional_stop_enabled', 'data-wsb-return-additional-stop-toggle', __('Add additional stop', 'wsb')); ?>
                                        <?php echo self::render_additional_stop_field($fields['return_additional_stop'], 'book-a-ride', 'data-wsb-return-additional-stop-section'); ?>
                                        <?php echo self::render_text_field($fields['return_to'], 'book-a-ride', false); ?>
                                    </div>
                                    <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--datetime">
                                        <?php echo self::render_date_field($fields['return_pickup_date'], 'book-a-ride', false); ?>
                                        <?php echo self::render_time_field($fields['return_pickup_time'], 'book-a-ride', false); ?>
                                    </div>
                                </div>
                            </section>
                        </section>

                        <section class="wsb-booking-client-card wsb-form-shell wsb-booking-client-charter wsb-booking-client-hidden" data-wsb-charter-section>

                            <?php if ($multi_day_enabled): ?>
                            <div class="wsb-segmented-control wsb-segmented-control--two wsb-charter-mode-switch wsb-booking-client-charter-mode-bar" role="radiogroup" aria-label="<?php echo esc_attr__('Hire length', 'wsb'); ?>">
                                <label class="wsb-booking-client-pill wsb-booking-client-pill--switch">
                                    <input type="radio" name="charter_mode" value="same_day" checked data-wsb-charter-mode-option="same_day">
                                    <span><?php echo esc_html__('Single-day hire', 'wsb'); ?></span>
                                </label>
                                <label class="wsb-booking-client-pill wsb-booking-client-pill--switch">
                                    <input type="radio" name="charter_mode" value="multi_day" data-wsb-charter-mode-option="multi_day">
                                    <span><?php echo esc_html__('Multi-day hire', 'wsb'); ?></span>
                                </label>
                            </div>
                            <?php endif; ?>

                            <div class="wsb-charter-trip-details">


                                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--options">
                                    <?php echo self::render_number_field($fields['passengers'], 'shuttle-hire'); ?>
                                    <?php echo self::render_number_field($fields['check_in_bags'], 'shuttle-hire'); ?>
                                    <?php echo self::render_number_field($fields['carry_on_bags'], 'shuttle-hire'); ?>
                                     <?php echo self::render_number_field($fields['baby_seats'], 'shuttle-hire'); ?>
                                </div>

                                <div class="wsb-booking-client-addons-row wsb-booking-client-addons-row--below">
                                    <?php echo self::render_checkbox_option($fields['trailer'] ?? array('key' => 'trailer', 'label' => __('Trailer required', 'wsb')), 'trailer'); ?>
                                    <?php echo self::render_checkbox_option($fields['oversize_luggage'] ?? array('key' => 'oversize_luggage', 'label' => __('Oversize luggage', 'wsb')), 'oversize_luggage'); ?>
                                </div>
                            </div>

                            <div class="wsb-charter-single-day-panel wsb-booking-client-charter-same-day-panel wsb-charter-panel" data-wsb-charter-same-day-panel>
                                 <div class="wsb-booking-client-card-header">
                                    <div>
                                        <h4><?php echo esc_html__('Plan your day', 'wsb'); ?></h4>
                                    </div>
                                </div>

                                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--datetime wsb-grid--three">
                                    <?php echo self::render_date_field(array_replace($fields['outbound_pickup_date'], array('label' => __('Date', 'wsb'), 'placeholder' => __('Select date', 'wsb'), 'date_min_attr' => ($fields['outbound_pickup_date']['charter_date_min_attr'] ?? $fields['outbound_pickup_date']['date_min_attr'] ?? ''))), 'shuttle-hire', false); ?>
                                    <?php echo self::render_time_field(array_replace($fields['charter_pickup_time'], array('label' => __('Start time', 'wsb'))), 'shuttle-hire', false); ?>
                                    <?php echo self::render_time_field(array_replace($fields['charter_dropoff_time'], array('label' => __('End time', 'wsb'))), 'shuttle-hire', false); ?>
                                </div>

                                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--locations">
                                    <?php echo self::render_text_field($fields['charter_pickup_location'], 'shuttle-hire'); ?>
                                    <?php echo self::render_text_field($fields['charter_dropoff_location'], 'shuttle-hire'); ?>
                                </div>

                                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--notes">
                                    <?php echo self::render_text_field(array_replace($fields['charter_poi'], array('label' => __('Places or stops you’d like to include', 'wsb'), 'placeholder' => __('Wine estates, viewpoints, attractions', 'wsb'))), 'shuttle-hire', false); ?>
                                    <?php echo self::render_textarea_field($fields['charter_notes'], 'shuttle-hire', false); ?>
                                </div>
                            </div>

                            <?php if ($multi_day_enabled): ?>
                                <?php echo self::render_multiday_charter_shell($fields); ?>
                            <?php endif; ?>
                        </section>

                        <section class="wsb-booking-client-card wsb-form-shell wsb-booking-client-plan wsb-booking-client-hidden" data-wsb-plan-section>
                            <?php if (!empty($gates['enable_multi_trip_bookings'])): ?>
                                <?php echo self::render_multi_trip_shell($fields); ?>
                            <?php else: ?>
                                <div class="wsb-booking-client-plan-shell" aria-label="Plan Full Booking preview">
                                    <article class="wsb-booking-client-plan-card">
                                        <span class="wsb-booking-client-plan-card__badge"><?php echo esc_html__('Next phase', 'wsb'); ?></span>
                                        <h4><?php echo esc_html__('Multi-trip planning shell', 'wsb'); ?></h4>
                                        <p><?php echo esc_html__('Customers will be able to combine transfers, return trips and shuttle hire in a single flow.', 'wsb'); ?></p>
                                    </article>
                                    <button type="button" class="wsb-booking-client-charter-action" disabled><?php echo esc_html__('Add another trip', 'wsb'); ?></button>
                                </div>
                            <?php endif; ?>
                        </section>

                        <div class="wsb-booking-client-actions">
                            <button type="submit" class="wsb-primary-cta wsb-booking-client-submit" data-wsb-preview-submit><?php echo esc_html__('Check Pricing & Availability', 'wsb'); ?><span aria-hidden="true"></span></button>
                            <div class="wsb-booking-client-submit-message" aria-live="polite" data-wsb-submit-message></div>
                            <p class="wsb-secure-note wsb-booking-client-note"><?php echo esc_html__('Secure booking. No payment required yet.', 'wsb'); ?></p>
                        </div>
                    </div>

                    <aside class="wsb-booking-client-preview-column" aria-live="polite">
                        <section class="wsb-booking-client-card wsb-booking-client-card--preview">
                            <div class="wsb-booking-client-card-header">
                                <h3><?php echo esc_html__('Booking summary', 'wsb'); ?></h3>
                            </div>
                            <div class="wsb-booking-client-preview-help"><?php echo esc_html__('Pricing is calculated on the next step based on your selections and availability.', 'wsb'); ?></div>
                            <div class="wsb-booking-client-preview-status" data-wsb-preview-status aria-live="polite"></div>
                            <div class="wsb-booking-client-validation" data-wsb-validation-output aria-live="polite"></div>
                            <pre class="wsb-booking-client-preview-json" data-wsb-payload-preview aria-live="polite" tabindex="0"><?php echo esc_html__('No booking summary generated yet.', 'wsb'); ?></pre>
                        </section>
                    </aside>
                </div>

                <?php if ($show_dev_drawer && !empty($fixtures)): ?>
                <button
                    type="button"
                    class="wsb-booking-client-fixture-toggle"
                    data-wsb-fixture-toggle
                    aria-expanded="false"
                    aria-controls="wsb-booking-client-fixture-drawer"
                >
                    <?php echo esc_html__('Sample data', 'wsb'); ?>
                </button>
                <aside
                    id="wsb-booking-client-fixture-drawer"
                    class="wsb-booking-client-fixture-drawer wsb-booking-client-hidden"
                    data-wsb-fixture-drawer
                    aria-label="<?php echo esc_attr__('Sample booking data', 'wsb'); ?>"
                >
                    <div class="wsb-booking-client-fixture-drawer-inner">
                        <div class="wsb-booking-client-fixture-drawer-header">
                            <div>
                                <p class="wsb-booking-client-eyebrow"><?php echo esc_html__('QA samples', 'wsb'); ?></p>
                                <h3><?php echo esc_html__('Load a booking fixture', 'wsb'); ?></h3>
                            </div>
                            <button type="button" class="wsb-booking-client-fixture-close" data-wsb-fixture-close aria-label="<?php echo esc_attr__('Close sample drawer', 'wsb'); ?>">×</button>
                        </div>
                        <p class="wsb-booking-client-fixture-status" data-wsb-fixture-status><?php echo esc_html__('Choose a sample to load booking details.', 'wsb'); ?></p>
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
            </form>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_number_field(array $field, string $dom_context): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $required = !empty($field['required']) ? 'required' : '';
        $data_attrs = self::render_data_attributes($field);
        $options = $field['options'] ?? [];

        if (empty($options)) {
            $options = [0];
        }

        $default_value = ! empty($field['required']) ? 1 : 0;
        $options_html = '';
        foreach ($options as $value) {
            $selected = ((int) $value === (int) $default_value) ? ' selected' : '';
            $options_html .= sprintf(
                '<option value="%d"%s>%d</option>',
                (int) $value,
                $selected,
                (int) $value
            );
        }

        return sprintf(
            '<div class="wsb-select-field wsb-booking-client-field wsb-booking-client-field--select wsb-booking-client-field--key-%7$s"%5$s>%8$s<div class="wsb-field-control wsb-field-control--select wsb-field-control--key-%7$s"><span class="wsb-field-control__icon" aria-hidden="true"></span><select class="wsb-form__input" id="%1$s" name="%3$s"%6$s>%4$s</select></div></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['key']),
            $options_html,
            $data_attrs,
            $required ? ' required' : '',
            esc_attr(str_replace('_', '-', $field['key'])),
            self::render_label_with_help($field, $dom_id)
        );
    }

    private static function render_text_field(array $field, string $dom_context, bool $required = true): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $data_attrs = self::render_data_attributes($field);
        $is_location_field = !empty($field['data_attributes']['data-ws-place-role']) || in_array($field['key'], [
            'outbound_from', 'outbound_to', 'outbound_additional_stop',
            'return_from', 'return_to', 'return_additional_stop',
            'one_way_from', 'one_way_to',
            'return_outbound_from', 'return_outbound_to',
            'return_return_from', 'return_return_to',
            'charter_pickup_location', 'charter_dropoff_location',
            'charter_day_pickup_location', 'charter_day_dropoff_location'
        ], true);

        if ($is_location_field) {
            $clear_label = esc_attr__('Clear location', 'wsb');
            $current_label = esc_attr__('Use my current location', 'wsb');
            $stale_message = esc_html__('Location was edited after selection. Please select a place again.', 'wsb');
            $confirmed_message = esc_html__('Location confirmed.', 'wsb');
            $name_attr = esc_attr($field['key']);
            $placeholder_attr = esc_attr($field['placeholder'] ?? '');
            $required_attr = $required ? 'required' : '';
            $icon_class = in_array($field['key'], array('outbound_additional_stop', 'return_additional_stop'), true) ? 'wsb-booking-client-field-icon--stop' : 'wsb-booking-client-field-icon--location';
            $current_button = '<button type="button" class="wsb-booking-client-place-current" data-wsb-place-current data-wsb-action-tooltip="' . $current_label . '" aria-label="' . $current_label . '"></button>';
            return '<div class="wsb-location-field wsb-booking-client-field wsb-booking-client-field--location wsb-booking-client-field--key-' . esc_attr(str_replace('_', '-', $field['key'])) . '"' . $data_attrs . '>' . self::render_label_with_help($field, $dom_id) . '<div class="wsb-booking-client-location-row"><span class="wsb-booking-client-field-icon ' . esc_attr($icon_class) . '" aria-hidden="true"></span><input class="wsb-form__input" type="text" id="' . $dom_id . '" name="' . $name_attr . '" placeholder="' . $placeholder_attr . '" ' . $required_attr . ' /><div class="wsb-booking-client-location-actions"><button type="button" class="wsb-booking-client-place-clear" data-wsb-place-clear data-wsb-action-tooltip="' . $clear_label . '" aria-label="' . $clear_label . '"></button>' . $current_button . '</div></div><span class="wsb-booking-client-place-confirmed-message" aria-live="polite">' . $confirmed_message . '</span><span class="wsb-booking-client-place-stale-message" aria-live="polite">' . $stale_message . '</span></div>';
        }

        if (in_array($field['key'], array('charter_poi', 'charter_day_poi'), true)) {
            $list_id = self::build_dom_id($dom_context, $field['key'] . '-list');
            $clear_label = esc_attr__('Clear selected places to visit', 'wsb');
            return sprintf(
                '<div class="wsb-booking-client-field wsb-booking-client-field--poi wsb-poi-field"%6$s>%9$s<div class="wsb-poi-control"><input class="wsb-form__input" type="text" id="%1$s" name="%7$s" list="%8$s" placeholder="%3$s" autocomplete="off" %4$s /><button type="button" class="wsb-poi-clear" data-wsb-poi-clear data-wsb-action-tooltip="%10$s" aria-label="%10$s"></button></div><datalist id="%8$s">%5$s</datalist></div>',
                esc_attr($dom_id),
                esc_html($field['label']),
                esc_attr($field['placeholder'] ?? ''),
                $required ? 'required' : '',
                self::render_poi_options(),
                $data_attrs,
                esc_attr($field['key']),
                esc_attr($list_id),
                self::render_label_with_help($field, $dom_id),
                $clear_label
            );
        }

        return sprintf(
            '<div class="wsb-booking-client-field"%5$s>%7$s<input class="wsb-form__input" type="text" id="%1$s" name="%6$s" placeholder="%3$s" %4$s /></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : '',
            $data_attrs,
            esc_attr($field['key']),
            self::render_label_with_help($field, $dom_id)
        );
    }

    private static function render_date_field(array $field, string $dom_context, bool $required = true): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $field_key = esc_attr($field['key']);
        $date_min = !empty($field['date_min_attr']) ? ' min="' . esc_attr($field['date_min_attr']) . '"' : '';
        $date_max = !empty($field['date_max_attr']) ? ' max="' . esc_attr($field['date_max_attr']) . '"' : '';
        $status_attr = '';
        if (false !== strpos($field['key'], 'outbound') || false !== strpos($field['key'], 'charter')) {
            $status_attr = ' data-wsb-outbound-picker-status';
        } elseif (false !== strpos($field['key'], 'return')) {
            $status_attr = ' data-wsb-return-picker-status';
        }
        $data_attrs = self::render_data_attributes($field);

        return sprintf(
            '<div class="wsb-date-field wsb-booking-client-picker-group wsb-booking-client-field--date"%6$s><div class="wsb-booking-client-picker-wrapper">%10$s<div class="wsb-booking-client-date-row"><span class="wsb-booking-client-date-icon" aria-hidden="true"></span><input class="wsb-form__input" type="text" inputmode="numeric" autocomplete="off" data-wsb-datepicker id="%1$s" name="%9$s" placeholder="%3$s" %4$s%5$s%7$s /></div></div></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : '',
            $date_min,
            $data_attrs,
            $date_max,
            $status_attr,
            $field_key,
            self::render_label_with_help($field, $dom_id)
        );
    }

    private static function render_time_field(array $field, string $dom_context, bool $required = true): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $step = isset($field['step_attr']) && $field['step_attr'] ? ' step="' . (int) $field['step_attr'] . '"' : '';
        $data_attrs = self::render_data_attributes($field);
        return sprintf(
            '<div class="wsb-time-field wsb-booking-client-field wsb-booking-client-field--time"%6$s>%8$s<div class="wsb-booking-client-time-row"><span class="wsb-booking-client-time-icon" aria-hidden="true"></span><input class="wsb-form__input"%4$s type="text" id="%1$s" name="%7$s" placeholder="%3$s" autocomplete="off" %5$s /><span class="wsb-booking-client-ampm" data-wsb-ampm-badge>AM</span></div></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $step,
            $required ? 'required' : '',
            $data_attrs,
            esc_attr($field['key']),
            self::render_label_with_help($field, $dom_id)
        );
    }

    private static function render_textarea_field(array $field, string $dom_context, bool $required = false): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $data_attrs = self::render_data_attributes($field);

        return sprintf(
            '<div class="wsb-booking-client-field wsb-booking-client-field--textarea wsb-notes-field"%5$s>%7$s<textarea class="wsb-form__input" rows="3" id="%1$s" name="%6$s" placeholder="%3$s" %4$s></textarea></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : '',
            $data_attrs,
            esc_attr($field['key']),
            self::render_label_with_help($field, $dom_id)
        );
    }

    private static function render_checkbox_option(array $field, string $name): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $help_icon = self::render_help_icon($field);

        return '<label class="wsb-checkbox-option wsb-booking-client-checkbox-label"><input type="checkbox" name="' . esc_attr($name) . '"><span class="wsb-booking-client-checkbox-label__inner"><span class="wsb-booking-client-checkbox-label__text">' . esc_html($field['label']) . '</span>' . $help_icon . '</span></label>';
    }

    private static function render_label_with_help(array $field, string $dom_id): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $help_icon = self::render_help_icon($field);
        $label = esc_html($field['label']);

        if ('' === $help_icon) {
            return '<label class="wsb-form__label" for="' . esc_attr($dom_id) . '">' . $label . '</label>';
        }

        return '<label class="wsb-form__label wsb-form__label--with-help" for="' . esc_attr($dom_id) . '"><span class="wsb-form__label-text">' . $label . '</span>' . $help_icon . '</label>';
    }

    private static function render_help_icon(array $field): string {
        $help_text = self::get_field_help_text($field);
        if ('' === $help_text) {
            return '';
        }

        return '<button type="button" class="wsb-tip-icon" data-wsb-help-icon aria-label="' . esc_attr($help_text) . '" aria-expanded="false"><span class="wsb-tip-icon__glyph" aria-hidden="true">i</span><span class="wsb-tip-icon__bubble" role="tooltip">' . esc_html($help_text) . '</span></button>';
    }

    private static function get_field_help_text(array $field): string {
        $help_key = '';
        $help_context = '';
        if (!empty($field['data_attributes']) && is_array($field['data_attributes'])) {
            $help_key = trim((string) ($field['data_attributes']['data-ws-help'] ?? ''));
            $help_context = trim((string) ($field['data_attributes']['data-ws-help-context'] ?? ''));
        }

        $help_map = array(
            'passengers' => 'How many people are travelling.',
            'baby_seats' => 'Add one seat for each baby seat you need.',
            'check_in_bags' => 'Bags that will go in the hold.',
            'carry_on_bags' => 'Smaller bags that stay with you.',
            'trailer' => 'Select this if you need a trailer for extra luggage or gear.',
            'oversize_luggage' => 'Select this for surfboards, sports gear, bike boxes, or other bulky items.',
            'pickup_location' => 'Select an address from the suggestions.',
            'dropoff_location' => 'Select the place you want to arrive at.',
            'pickup_date' => 'Select the travel date.',
            'pickup_time' => 'Select the pickup time.',
            'dropoff_time' => 'Select the drop-off time.',
            'additional_stop' => 'Add one extra stop between pickup and drop-off.',
            'charter_poi' => 'Add places you would like to include on the route.',
            'charter_notes' => 'Add any route or timing notes for the hire.',
            'day_date' => 'Choose the date for this day.',
            'day_start_time' => 'At what time will we pick you up at your home, hotel, etc?',
            'day_end_time' => 'At what time will we drop you off at your home, hotel, etc?',
            'day_pickup_location' => 'Where do we pick you up to start your day?',
            'day_dropoff_location' => 'Where do we drop you off after your day ends?',
            'day_poi' => 'Add the places you want to visit on this day.',
            'day_notes' => 'Add any timing or itinerary notes for this day.'
        );

        if ('' !== $help_key && isset($help_map[$help_key])) {
            return (string) $help_map[$help_key];
        }

        if ('' !== $help_key && in_array($help_key, array('pickup_location', 'dropoff_location', 'pickup_date', 'pickup_time', 'dropoff_time', 'additional_stop'), true) && '' !== $help_context) {
            if (false !== strpos($help_context, 'shuttle_hire')) {
                $context_map = array(
                    'pickup_location' => 'Choose the charter pickup point from the suggestions.',
                    'dropoff_location' => 'Choose the charter destination from the suggestions.',
                    'pickup_date' => 'Choose the charter date.',
                    'pickup_time' => 'Choose the charter start time.',
                    'dropoff_time' => 'Choose the charter end time.',
                    'additional_stop' => 'Add one extra stop to the itinerary.',
                );

                if (isset($context_map[$help_key])) {
                    return (string) $context_map[$help_key];
                }
            }

            if (false !== strpos($help_context, 'book_a_ride')) {
                $context_map = array(
                    'pickup_location' => 'Choose the pickup address from the suggestions.',
                    'dropoff_location' => 'Choose the drop-off address from the suggestions.',
                    'pickup_date' => 'Choose the pickup date.',
                    'pickup_time' => 'Choose the pickup time.',
                    'dropoff_time' => 'Choose the return time.',
                    'additional_stop' => 'Add one extra stop between pickup and drop-off.',
                );

                if (isset($context_map[$help_key])) {
                    return (string) $context_map[$help_key];
                }
            }
        }

        return '';
    }

    private static function render_charter_day_card(array $fields, int $slot_index, bool $visible = false, bool $collapsed = false): string {
        $day_number = $slot_index + 1;
        $day_id = 'day_' . $slot_index;
        $dom_context = 'shuttle-hire-day-' . $day_number;
        $card_classes = array('wsb-charter-day-card', 'wsb-booking-client-charter-day-card');
        if (! $visible) {
            $card_classes[] = 'wsb-booking-client-hidden';
        }
        if ($collapsed) {
            $card_classes[] = 'wsb-booking-client-charter-day-card--collapsed';
        }

        $body_classes = array('wsb-charter-day-body', 'wsb-booking-client-charter-day-body');
        if ($collapsed) {
            $body_classes[] = 'wsb-booking-client-hidden';
        }

        $header_toggle_label = $collapsed ? __('Expand day details', 'wsb') : __('Collapse day details', 'wsb');
        $expanded = $collapsed ? 'false' : 'true';

        ob_start();
        ?>
        <section
            class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"
            data-wsb-charter-day-card
            data-wsb-charter-day-id="<?php echo esc_attr($day_id); ?>"
            data-wsb-charter-day-slot="<?php echo esc_attr((string) $day_number); ?>"
            data-wsb-charter-day-visible="<?php echo esc_attr($visible ? 'true' : 'false'); ?>"
            data-wsb-charter-day-collapsed="<?php echo esc_attr($collapsed ? 'true' : 'false'); ?>"
            aria-expanded="<?php echo esc_attr($expanded); ?>"
        >
            <div class="wsb-charter-day-header wsb-booking-client-card-header wsb-booking-client-charter-day-header">
                <button type="button" class="wsb-drag-handle wsb-icon-action" data-wsb-drag-handle data-wsb-action-tooltip="<?php echo esc_attr__('Drag to reorder', 'wsb'); ?>" aria-label="<?php echo esc_attr__('Drag to reorder day', 'wsb'); ?>" draggable="true"></button>
                <span class="wsb-charter-day-icon" aria-hidden="true"></span>
                <button type="button" class="wsb-charter-day-title" data-wsb-charter-day-toggle aria-expanded="<?php echo esc_attr($expanded); ?>" aria-label="<?php echo esc_attr($header_toggle_label); ?>">
                    <strong><?php echo esc_html(sprintf(__('Day %d', 'wsb'), $day_number)); ?> — <span data-wsb-day-route-label><?php echo esc_html__('Add route', 'wsb'); ?></span></strong>
                    <small data-wsb-day-summary><?php echo esc_html__('Date · start time – end time', 'wsb'); ?></small>
                </button>
                <div class="wsb-booking-client-charter-day-actions">
                    <button type="button" class="wsb-icon-action wsb-icon-action--copy" data-wsb-charter-day-duplicate data-wsb-action-tooltip="<?php echo esc_attr__('Duplicate this day', 'wsb'); ?>" aria-label="<?php echo esc_attr__('Duplicate this day', 'wsb'); ?>"></button>
                    <button type="button" class="wsb-icon-action wsb-icon-action--delete" data-wsb-charter-day-delete data-wsb-action-tooltip="<?php echo esc_attr__('Remove this day', 'wsb'); ?>" aria-label="<?php echo esc_attr__('Remove this day', 'wsb'); ?>"></button>
                    <button type="button" class="wsb-icon-action wsb-icon-action--toggle" data-wsb-charter-day-toggle data-wsb-action-tooltip="<?php echo esc_attr($header_toggle_label); ?>" aria-expanded="<?php echo esc_attr($expanded); ?>" aria-label="<?php echo esc_attr($header_toggle_label); ?>"></button>
                </div>
            </div>

            <div class="<?php echo esc_attr(implode(' ', $body_classes)); ?>" data-wsb-charter-day-body>
                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--datetime wsb-booking-client-grid--day-card">
                    <?php echo self::render_date_field($fields['charter_day_date'], $dom_context); ?>
                    <?php echo self::render_time_field($fields['charter_day_start_time'], $dom_context); ?>
                    <?php echo self::render_time_field($fields['charter_day_end_time'], $dom_context); ?>
                </div>

                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--locations">
                    <?php echo self::render_text_field($fields['charter_day_pickup_location'], $dom_context); ?>
                    <?php echo self::render_text_field($fields['charter_day_dropoff_location'], $dom_context); ?>
                </div>

                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--notes">
                    <?php echo self::render_text_field($fields['charter_day_poi'], $dom_context, false); ?>
                    <?php echo self::render_textarea_field($fields['charter_day_notes'], $dom_context, false); ?>
                </div>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_multiday_charter_shell(array $fields): string {
        ob_start();
        ?>
        <section class="wsb-booking-client-charter-multiday-shell" data-wsb-charter-multiday-shell data-ws-feature-gate="enable_multi_day_charters">
            <div class="wsb-booking-client-card-header">
                <div>
                    <h4><?php echo esc_html__('Plan each day', 'wsb'); ?></h4>
                </div>
            </div>

            <div class="wsb-charter-day-list wsb-booking-client-charter-day-list wsb-booking-client-hidden" data-wsb-charter-day-list data-wsb-sortable-list="charter-day-list">
                <?php echo self::render_charter_day_card($fields, 0, true, false); ?>
                <?php echo self::render_charter_day_card($fields, 1, false, true); ?>
                <?php echo self::render_charter_day_card($fields, 2, false, true); ?>
                <?php echo self::render_charter_day_card($fields, 3, false, true); ?>
                <?php echo self::render_charter_day_card($fields, 4, false, true); ?>
            </div>
            <button type="button" class="wsb-booking-client-add-day-row wsb-booking-client-hidden" data-wsb-charter-add-day data-wsb-charter-add-day-row>
                <span aria-hidden="true">+</span><?php echo esc_html__('Add another day', 'wsb'); ?>
            </button>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_multi_trip_shell(array $fields): string {
        ob_start();
        ?>
        <div class="wsb-booking-client-plan-shell" data-wsb-multi-trip-shell data-ws-feature-gate="enable_multi_trip_bookings">
            <div class="wsb-booking-client-plan-layout">
                <div class="wsb-booking-client-plan-stage">
                    <div class="wsb-booking-client-plan-stage__inner">
                        <div class="wsb-booking-client-plan-shared" data-wsb-plan-fields>
                            <div class="wsb-booking-client-trip-section__header">
                                <h4><?php echo esc_html__('Build your full itinerary', 'wsb'); ?></h4>
                                <p><?php echo esc_html__('The options below apply to the entire itinerary.', 'wsb'); ?></p>
                            </div>
                            <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--options">
                                <?php echo self::render_number_field($fields['passengers'], 'plan-full-booking'); ?>
                                <?php echo self::render_number_field($fields['check_in_bags'], 'plan-full-booking'); ?>
                                <?php echo self::render_number_field($fields['carry_on_bags'], 'plan-full-booking'); ?>
                                <?php echo self::render_number_field($fields['baby_seats'], 'plan-full-booking'); ?>
                            </div>
                            <div class="wsb-booking-client-addons-row wsb-booking-client-addons-row--below">
                                <label class="wsb-checkbox-option wsb-booking-client-checkbox-label">
                                    <input type="checkbox" name="trailer">
                                    <span><?php echo esc_html($fields['trailer']['label'] ?? __('Trailer required', 'wsb')); ?></span>
                                </label>
                                <label class="wsb-checkbox-option wsb-booking-client-checkbox-label">
                                    <input type="checkbox" name="oversize_luggage">
                                    <span><?php echo esc_html($fields['oversize_luggage']['label'] ?? __('Oversize luggage', 'wsb')); ?></span>
                                </label>
                            </div>
                        </div>

                        <div class="wsb-booking-client-multi-trip-list" data-wsb-multi-trip-list data-wsb-sortable-list="multi-trip-list">
                            <?php echo self::render_multi_trip_card($fields, 0, true); ?>
                            <?php echo self::render_multi_trip_card($fields, 1, false, true); ?>
                        </div>

                        <div class="wsb-booking-client-plan-actions">
                            <button type="button" class="wsb-booking-client-add-day-row" data-wsb-multi-trip-add>
                                <span aria-hidden="true">+</span><?php echo esc_html__('Add another trip', 'wsb'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <aside class="wsb-booking-client-plan-summary" data-wsb-plan-summary>
                    <div class="wsb-booking-client-plan-summary__header">
                        <h4><?php echo esc_html__('Booking Summary', 'wsb'); ?></h4>
                        <p class="wsb-booking-client-plan-summary__count" data-wsb-plan-summary-count><?php echo esc_html__('1 item', 'wsb'); ?></p>
                    </div>
                    <div class="wsb-booking-client-plan-summary__list" data-wsb-plan-summary-list></div>
                    <div class="wsb-booking-client-plan-summary__note" data-wsb-plan-summary-note></div>
                </aside>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_multi_trip_card(array $fields, int $trip_index, bool $expanded = true, bool $template = false): string {
        $trip_number = $trip_index + 1;
        $trip_id = 'trip_' . $trip_number;
        $dom_context = 'multi-trip-' . $trip_number;
        $card_classes = array('wsb-booking-client-charter-day-card', 'wsb-booking-client-multi-trip-card');
        if (!$expanded) {
            $card_classes[] = 'wsb-booking-client-charter-day-card--collapsed';
        }
        if ($template) {
            $card_classes[] = 'wsb-booking-client-multi-trip-card--template';
        }
        $body_classes = array('wsb-charter-day-body', 'wsb-booking-client-charter-day-body');
        if (!$expanded) {
            $body_classes[] = 'wsb-booking-client-hidden';
        }
        if ($template) {
            $card_classes[] = 'wsb-booking-client-hidden';
            $body_classes[] = 'wsb-booking-client-hidden';
        }

        ob_start();
        ?>
        <section
            class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"
            data-wsb-multi-trip-card
            data-wsb-multi-trip-id="<?php echo esc_attr($trip_id); ?>"
            data-wsb-multi-trip-index="<?php echo esc_attr((string) $trip_index); ?>"
            data-wsb-multi-trip-visible="true"
            <?php echo $template ? 'data-wsb-multi-trip-template="true"' : ''; ?>
            data-wsb-multi-trip-trip-type="one_way"
            aria-expanded="<?php echo esc_attr($expanded ? 'true' : 'false'); ?>"
        >
            <div class="wsb-charter-day-header wsb-booking-client-card-header wsb-booking-client-charter-day-header">
                <button type="button" class="wsb-drag-handle wsb-icon-action" data-wsb-drag-handle data-wsb-action-tooltip="<?php echo esc_attr__('Drag to reorder trip', 'wsb'); ?>" aria-label="<?php echo esc_attr__('Drag to reorder trip', 'wsb'); ?>" draggable="true"></button>
                <span class="wsb-charter-day-icon wsb-charter-day-icon--plan" aria-hidden="true" data-wsb-multi-trip-icon></span>
                <button type="button" class="wsb-charter-day-title" data-wsb-multi-trip-toggle aria-expanded="<?php echo esc_attr($expanded ? 'true' : 'false'); ?>">
                    <strong><span data-wsb-multi-trip-number><?php echo esc_html(sprintf(__('Trip %d', 'wsb'), $trip_number)); ?></span> — <span data-wsb-multi-trip-type-label><?php echo esc_html__('One-way', 'wsb'); ?></span></strong>
                    <span class="wsb-charter-day-title__route" data-wsb-multi-trip-route-label><?php echo esc_html__('Add route', 'wsb'); ?></span>
                    <small data-wsb-multi-trip-summary><?php echo esc_html__('Date · time', 'wsb'); ?></small>
                </button>
                <div class="wsb-booking-client-charter-day-actions">
                    <button type="button" class="wsb-icon-action wsb-icon-action--copy" data-wsb-multi-trip-copy data-wsb-action-tooltip="<?php echo esc_attr__('Duplicate this trip', 'wsb'); ?>" aria-label="<?php echo esc_attr__('Duplicate this trip', 'wsb'); ?>"></button>
                    <button type="button" class="wsb-icon-action wsb-icon-action--delete" data-wsb-multi-trip-remove data-wsb-action-tooltip="<?php echo esc_attr__('Remove this trip', 'wsb'); ?>" aria-label="<?php echo esc_attr__('Remove this trip', 'wsb'); ?>"></button>
                    <button type="button" class="wsb-icon-action wsb-icon-action--toggle" data-wsb-multi-trip-toggle data-wsb-action-tooltip="<?php echo esc_attr($expanded ? __('Collapse trip details', 'wsb') : __('Expand trip details', 'wsb')); ?>" aria-expanded="<?php echo esc_attr($expanded ? 'true' : 'false'); ?>" aria-label="<?php echo esc_attr($expanded ? __('Collapse trip details', 'wsb') : __('Expand trip details', 'wsb')); ?>"></button>
                </div>
            </div>

            <div class="<?php echo esc_attr(implode(' ', $body_classes)); ?>" data-wsb-multi-trip-body>
                <div class="wsb-segmented-control wsb-segmented-control--three wsb-booking-client-trip-type-switch" role="radiogroup" aria-label="<?php echo esc_attr__('Trip type', 'wsb'); ?>">
                    <label class="wsb-booking-client-pill wsb-booking-client-pill--radio">
                        <input type="radio" name="<?php echo esc_attr($trip_id . '_trip_type'); ?>" value="one_way" checked data-wsb-multi-trip-trip-type-option>
                        <span><?php echo esc_html__('One-way', 'wsb'); ?></span>
                    </label>
                    <label class="wsb-booking-client-pill wsb-booking-client-pill--radio">
                        <input type="radio" name="<?php echo esc_attr($trip_id . '_trip_type'); ?>" value="return" data-wsb-multi-trip-trip-type-option>
                        <span><?php echo esc_html__('Return', 'wsb'); ?></span>
                    </label>
                    <label class="wsb-booking-client-pill wsb-booking-client-pill--radio">
                        <input type="radio" name="<?php echo esc_attr($trip_id . '_trip_type'); ?>" value="charter" data-wsb-multi-trip-trip-type-option>
                        <span><?php echo esc_html__('Charter', 'wsb'); ?></span>
                    </label>
                </div>

                <div class="wsb-booking-client-trip-section wsb-booking-client-trip-section--outbound" data-wsb-multi-trip-section="one_way">
                    <div class="wsb-booking-client-trip-section__header wsb-booking-client-hidden" data-wsb-multi-trip-outbound-heading>
                        <h4><?php echo esc_html__('Outbound details', 'wsb'); ?></h4>
                        <p><?php echo esc_html__('Your original one-way trip becomes the outbound leg.', 'wsb'); ?></p>
                    </div>

                    <div class="wsb-route-stack wsb-location-path wsb-booking-client-grid--locations" data-wsb-location-path="plan-one-way">
                        <?php echo self::render_text_field(self::prepare_multi_trip_field($fields['outbound_from'], 'from', array('label' => __('From', 'wsb'), 'placeholder' => __('Enter pickup address', 'wsb'), 'data_attributes' => array('data-wsb-multi-trip-field' => 'from', 'data-ws-route-role' => 'origin', 'data-ws-place-role' => 'origin'))), $dom_context); ?>
                        <?php echo self::render_additional_stop_toggle('one_way_additional_stop_enabled', 'data-wsb-multi-trip-field="one_way_additional_stop_enabled" data-wsb-one-way-additional-stop-toggle', __('Add additional stop', 'wsb')); ?>
                        <?php echo self::render_additional_stop_field(self::prepare_multi_trip_field($fields['outbound_additional_stop'], 'one_way_additional_stop', array('data_attributes' => array('data-wsb-multi-trip-field' => 'one_way_additional_stop'))), $dom_context, 'data-wsb-one-way-additional-stop-section'); ?>
                        <?php echo self::render_text_field(self::prepare_multi_trip_field($fields['outbound_to'], 'to', array('label' => __('To', 'wsb'), 'placeholder' => __('Enter drop-off address', 'wsb'), 'data_attributes' => array('data-wsb-multi-trip-field' => 'to', 'data-ws-route-role' => 'destination', 'data-ws-place-role' => 'destination'))), $dom_context); ?>
                    </div>

                    <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--datetime">
                        <?php echo self::render_date_field(self::prepare_multi_trip_field($fields['outbound_pickup_date'], 'pickup_date', array('label' => __('Pickup date', 'wsb'), 'data_attributes' => array('data-wsb-multi-trip-field' => 'pickup_date'))), $dom_context); ?>
                        <?php echo self::render_time_field(self::prepare_multi_trip_field($fields['outbound_pickup_time'], 'pickup_time', array('label' => __('Pickup time', 'wsb'), 'data_attributes' => array('data-wsb-multi-trip-field' => 'pickup_time'))), $dom_context); ?>
                    </div>

                    <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--notes">
                        <?php echo self::render_textarea_field(self::prepare_multi_trip_field(array('key' => 'notes', 'label' => __('Notes', 'wsb'), 'placeholder' => __('Trip notes, timing or access notes', 'wsb')), 'notes', array('data_attributes' => array('data-wsb-multi-trip-field' => 'notes'))), $dom_context, false); ?>
                    </div>
                </div>

                <div class="wsb-booking-client-trip-section wsb-booking-client-hidden" data-wsb-multi-trip-section="return">
                    <button type="button" class="wsb-booking-client-return-toggle wsb-booking-client-multi-trip-return-toggle" data-wsb-multi-trip-return-toggle aria-expanded="true">
                        <span><?php echo esc_html__('Return details', 'wsb'); ?></span>
                        <span class="wsb-booking-client-return-toggle-icon" aria-hidden="true"></span>
                    </button>
                    <div class="wsb-booking-client-multi-trip-return-body" data-wsb-multi-trip-return-body>
                        <div class="wsb-booking-client-trip-subsection">
                            <div class="wsb-route-stack wsb-location-path" data-wsb-location-path="return-return">
                                <?php echo self::render_text_field(self::prepare_multi_trip_field($fields['return_from'], 'return_return_from', array('data_attributes' => array('data-wsb-multi-trip-field' => 'return_return_from', 'data-ws-route-role' => 'return_origin', 'data-ws-place-role' => 'return_origin'))), $dom_context, false); ?>
                                <?php echo self::render_additional_stop_toggle('return_return_additional_stop_enabled', 'data-wsb-multi-trip-field="return_return_additional_stop_enabled" data-wsb-return-return-additional-stop-toggle', __('Add additional stop', 'wsb')); ?>
                                <?php echo self::render_additional_stop_field(self::prepare_multi_trip_field($fields['return_additional_stop'], 'return_return_additional_stop', array('data_attributes' => array('data-wsb-multi-trip-field' => 'return_return_additional_stop'))), $dom_context, 'data-wsb-return-return-additional-stop-section'); ?>
                                <?php echo self::render_text_field(self::prepare_multi_trip_field($fields['return_to'], 'return_return_to', array('data_attributes' => array('data-wsb-multi-trip-field' => 'return_return_to', 'data-ws-route-role' => 'return_destination', 'data-ws-place-role' => 'return_destination'))), $dom_context, false); ?>
                            </div>
                            <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--datetime wsb-booking-client-grid--day-card">
                                <?php echo self::render_date_field(self::prepare_multi_trip_field($fields['return_pickup_date'], 'return_return_pickup_date', array('label' => __('Return pickup date', 'wsb'), 'data_attributes' => array('data-wsb-multi-trip-field' => 'return_return_pickup_date'))), $dom_context, false); ?>
                                <?php echo self::render_time_field(self::prepare_multi_trip_field($fields['return_pickup_time'], 'return_return_pickup_time', array('label' => __('Return pickup time', 'wsb'), 'data_attributes' => array('data-wsb-multi-trip-field' => 'return_return_pickup_time'))), $dom_context, false); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wsb-booking-client-trip-section wsb-booking-client-hidden" data-wsb-multi-trip-section="charter">
                    <div class="wsb-booking-client-trip-section__header">
                        <h4><?php echo esc_html__('Charter details', 'wsb'); ?></h4>
                        <p><?php echo esc_html__('Set the hire window and the places you want to visit.', 'wsb'); ?></p>
                    </div>
                    <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--datetime wsb-booking-client-grid--day-card">
                        <?php echo self::render_date_field(self::prepare_multi_trip_field($fields['outbound_pickup_date'], 'charter_pickup_date', array('label' => __('Date', 'wsb'), 'placeholder' => __('Select date', 'wsb'), 'date_min_attr' => ($fields['outbound_pickup_date']['charter_date_min_attr'] ?? $fields['outbound_pickup_date']['date_min_attr'] ?? ''), 'data_attributes' => array('data-wsb-multi-trip-field' => 'charter_pickup_date'))), $dom_context); ?>
                        <?php echo self::render_time_field(self::prepare_multi_trip_field($fields['charter_pickup_time'], 'charter_pickup_time', array('label' => __('Start time', 'wsb'), 'data_attributes' => array('data-wsb-multi-trip-field' => 'charter_pickup_time'))), $dom_context); ?>
                        <?php echo self::render_time_field(self::prepare_multi_trip_field($fields['charter_dropoff_time'], 'charter_dropoff_time', array('label' => __('End time', 'wsb'), 'data_attributes' => array('data-wsb-multi-trip-field' => 'charter_dropoff_time'))), $dom_context); ?>
                    </div>
                    <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--locations">
                        <?php echo self::render_text_field(self::prepare_multi_trip_field($fields['charter_pickup_location'], 'charter_pickup_location', array('data_attributes' => array('data-wsb-multi-trip-field' => 'charter_pickup_location', 'data-ws-route-role' => 'charter_origin', 'data-ws-place-role' => 'charter_origin'))), $dom_context); ?>
                        <?php echo self::render_text_field(self::prepare_multi_trip_field($fields['charter_dropoff_location'], 'charter_dropoff_location', array('data_attributes' => array('data-wsb-multi-trip-field' => 'charter_dropoff_location', 'data-ws-route-role' => 'charter_destination', 'data-ws-place-role' => 'charter_destination'))), $dom_context); ?>
                    </div>
                    <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--notes">
                        <?php echo self::render_text_field(self::prepare_multi_trip_field($fields['charter_poi'], 'charter_poi', array('label' => __('Places or stops you’d like to include', 'wsb'), 'placeholder' => __('Wine estates, viewpoints, attractions', 'wsb'), 'data_attributes' => array('data-wsb-multi-trip-field' => 'charter_poi'))), $dom_context, false); ?>
                        <?php echo self::render_textarea_field(self::prepare_multi_trip_field($fields['charter_notes'], 'charter_notes', array('label' => __('Notes for this hire', 'wsb'), 'data_attributes' => array('data-wsb-multi-trip-field' => 'charter_notes'))), $dom_context, false); ?>
                    </div>
                </div>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function prepare_multi_trip_field(array $field, string $key, array $overrides = array()): array {
        $field['key'] = $key;
        $field['data_attributes'] = isset($field['data_attributes']) && is_array($field['data_attributes']) ? $field['data_attributes'] : array();

        if (isset($overrides['data_attributes']) && is_array($overrides['data_attributes'])) {
            $field['data_attributes'] = array_merge($field['data_attributes'], $overrides['data_attributes']);
            unset($overrides['data_attributes']);
        }

        foreach ($overrides as $override_key => $override_value) {
            $field[$override_key] = $override_value;
        }

        $field['data_attributes']['data-wsb-multi-trip-field'] = $key;

        return $field;
    }


    private static function get_poi_choices(): array {
        // Exact fallback order from the legacy Bricks charter form qlwoyv / charter_poi field.
        $defaults = array(
            'Cape Peninsula',
            'Cape Winelands',
            'City Bowl',
            'Atlantis Sand Dunes',
            'Langebaan',
            'Hermanus - Whale Watching',
            'Hermanus - Hemel en Aarde',
            'West Coast Nature Reserve',
            'Gansbaai - Shark Cage Diving',
            'Aquila Safari',
            'Ceres Nature Reserve',
            'Cape Agulhas',
            'Garden Route',
            'Wedding Charter',
            'Other',
        );

        $config = wsb_client_external_services()->get_cached_booking_site_config();
        $from_config = array();
        foreach (array('points_of_interest', 'poi_options', 'charter_poi_options') as $key) {
            if (!empty($config[$key]) && is_array($config[$key])) {
                $from_config = $config[$key];
                break;
            }
        }

        $choices = $from_config ? $from_config : $defaults;
        $choices = array_map('sanitize_text_field', $choices);
        $choices = array_values(array_unique(array_filter($choices)));

        return (array) apply_filters('wsb_booking_client_poi_choices', $choices);
    }

    private static function render_poi_options(): string {
        $html = '';
        foreach (self::get_poi_choices() as $choice) {
            $html .= '<option value="' . esc_attr($choice) . '"></option>';
        }
        return $html;
    }

    private static function render_additional_stop_toggle(string $name, string $data_attr, string $label): string {
        $id = self::build_dom_id('toggle', $name);

        return '<label class="wsb-add-stop-control wsb-booking-client-add-stop-button wsb-booking-client-additional-toggle-label" for="' . esc_attr($id) . '" data-ws-feature-gate="enable_additional_stops"><input id="' . esc_attr($id) . '" type="checkbox" name="' . esc_attr($name) . '" class="wsb-booking-client-additional-toggle" ' . $data_attr . ' data-ws-feature-gate="enable_additional_stops"><span class="wsb-booking-client-add-stop-button__icon" aria-hidden="true">+</span><span>' . esc_html($label) . '</span></label>';
    }

    private static function render_additional_stop_field(array $field, string $dom_context, string $section_marker): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $clear_label = esc_attr__('Clear location', 'wsb');
        $current_label = esc_attr__('Use my current location', 'wsb');
        $stale_message = esc_html__('Location was edited after selection. Please select a place again.', 'wsb');
        $confirmed_message = esc_html__('Location confirmed.', 'wsb');
        $name_attr = esc_attr($field['key']);
        $placeholder_attr = esc_attr($field['placeholder'] ?? '');
        $section_attr = esc_attr($section_marker);
        $data_attrs = self::render_data_attributes($field);

        return '<div class="wsb-location-field wsb-booking-client-field wsb-booking-client-field--location wsb-booking-client-additional-stop-field wsb-booking-client-field--key-' . esc_attr(str_replace('_', '-', $field['key'])) . ' wsb-booking-client-hidden" data-wsb-additional-stop-section ' . $section_attr . $data_attrs . ' data-ws-feature-gate="enable_additional_stops"><label class="wsb-form__label" for="' . $dom_id . '">' . esc_html($field['label']) . '</label><div class="wsb-booking-client-location-row"><span class="wsb-booking-client-field-icon wsb-booking-client-field-icon--stop" aria-hidden="true"></span><input class="wsb-form__input" type="text" id="' . $dom_id . '" name="' . $name_attr . '" placeholder="' . $placeholder_attr . '" disabled /><div class="wsb-booking-client-location-actions"><button type="button" class="wsb-booking-client-place-clear" data-wsb-place-clear data-wsb-action-tooltip="' . $clear_label . '" aria-label="' . $clear_label . '"></button><button type="button" class="wsb-booking-client-place-current" data-wsb-place-current data-wsb-action-tooltip="' . $current_label . '" aria-label="' . $current_label . '"></button></div><button type="button" class="wsb-booking-client-remove-stop-button" data-wsb-additional-stop-remove data-wsb-action-tooltip="' . esc_attr__('Remove additional stop', 'wsb') . '" aria-label="' . esc_attr__('Remove additional stop', 'wsb') . '">×</button></div><span class="wsb-booking-client-place-confirmed-message" aria-live="polite">' . $confirmed_message . '</span><span class="wsb-booking-client-place-stale-message" aria-live="polite">' . $stale_message . '</span></div>';
    }


    private static function render_ui_icon(string $name): string {
        $icons = array(
            'car' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11l1.4-4.1A3 3 0 0 1 9.2 5h5.6a3 3 0 0 1 2.8 1.9L19 11m-14 0h14m-14 0a2 2 0 0 0-2 2v3h2m0 0a2 2 0 1 0 4 0m-4 0h10m0 0a2 2 0 1 0 4 0m-4 0h4v-3a2 2 0 0 0-2-2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 19v-1.5a3.5 3.5 0 0 0-7 0V19m3.5-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.5 8v-1a3 3 0 0 0-2.2-2.9M17.5 5.7a2.5 2.5 0 0 1 0 4.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 11V8a5 5 0 0 1 10 0v3m-11 0h12v9H6v-9Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'route' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6h.01M18 18h.01M7 6h4a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="6" cy="6" r="2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="18" cy="18" r="2" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
        );

        return '<span class="wsb-booking-client-tab-icon">' . ($icons[$name] ?? '') . '</span>';
    }

    private static function build_dom_id(string $dom_context, string $field_key): string {
        $parts = array();
        foreach (array($dom_context, $field_key) as $part) {
            $slug = strtolower(trim((string) $part));
            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
            $slug = trim((string) $slug, '-');
            if ('' !== $slug) {
                $parts[] = $slug;
            }
        }

        return $parts ? 'wsb-' . implode('-', $parts) : 'wsb';
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
            ['jquery', 'wsb-sortable-lite'],
            file_exists($js_path) ? filemtime($js_path) : WSB_CLIENT_VERSION,
            true
        );

        wp_register_script(
            'wsb-clock-timepicker',
            plugins_url('assets/js/jquery-clock-timepicker.min.js', __DIR__ . '/../ws-bookings-client.php'),
            ['jquery'],
            null,
            true
        );

        wp_register_script(
            'wsb-sortable-lite',
            plugins_url('assets/vendor/sortable.min.js', __DIR__ . '/../ws-bookings-client.php'),
            [],
            file_exists(plugin_dir_path(__DIR__) . 'assets/vendor/sortable.min.js') ? filemtime(plugin_dir_path(__DIR__) . 'assets/vendor/sortable.min.js') : WSB_CLIENT_VERSION,
            true
        );

        $google_places_enabled = false;
        $google_places_available = false;
        if (defined('GOOGLE_API_KEY') && GOOGLE_API_KEY !== '') {
            $google_places_enabled = true;
            $google_places_available = true; // Actual availability checked in JS

            wp_register_script(
                'wsb-google-places-api',
                sprintf(
                    'https://maps.googleapis.com/maps/api/js?key=%s&libraries=places',
                    rawurlencode(GOOGLE_API_KEY)
                ),
                [],
                null,
                true
            );
        }

        $preview_url = rest_url('ws-bookings-client/v1/payload-preview');
        $gates = \WSB_Booking_Client\Booking_Feature_Gates::frontend_config();
        $config = array(
            'previewUrl' => esc_url_raw($preview_url),
            'handoverPreviewUrl' => esc_url_raw($handover_preview_url),
            'nonce' => wp_create_nonce('wp_rest'),
            'debug' => (bool) current_user_can('manage_options'),
            'fixtureDrawerEnabled' => (bool) ( isset($_GET['debug']) && '1' === (string) $_GET['debug'] ),
            'uiInteractionsEnabled' => wsb_client_ui_interactions_enabled(),
            'featureGates' => is_array($gates['feature_gates'] ?? null) ? $gates['feature_gates'] : array(),
            'environment' => sanitize_key($gates['environment'] ?? 'production'),
            'bookingSiteConfig' => wsb_client_external_services()->get_cached_booking_site_config(),
            'strings' => array(
                'serverValidationPending' => __('Checking your details...', 'wsb'),
                'serverValidationSuccess' => __('Your details look good.', 'wsb'),
                'serverValidationWarnings' => __('Your details look good, with a few notes.', 'wsb'),
                'serverValidationFailed' => __('We could not verify your details.', 'wsb'),
                'serverPreviewUnavailable' => __('The review panel is unavailable.', 'wsb'),
                'serverPreviewError' => __('We could not complete the review.', 'wsb'),
                'fixtureDrawerDefault' => __('Choose a sample to load booking details.', 'wsb'),
                'fixtureDrawerLoaded' => __('Loaded sample:', 'wsb'),
                'fixtureDrawerExpected' => __('Expected:', 'wsb'),
                'fixtureDrawerServerMatched' => __('Server validation matched expected result.', 'wsb'),
                'fixtureDrawerServerMismatch' => __('Server validation did not match expected result.', 'wsb'),
                'fixtureDrawerHandoverMatched' => __('Handover preview matched expected result.', 'wsb'),
                'fixtureDrawerHandoverMismatch' => __('Handover preview did not match expected result.', 'wsb'),
                'fixtureDrawerHandoverUnavailable' => __('Handover preview endpoint unavailable.', 'wsb'),
                'fixtureDrawerOpen' => __('Sample drawer opened.', 'wsb'),
                'fixtureDrawerClosed' => __('Sample drawer closed.', 'wsb'),
                'pickerDateBeforeMin' => __('Date is before the earliest allowed date.', 'wsb'),
                'pickerDateAfterMax' => __('Date exceeds the maximum advance booking window.', 'wsb'),
                'pickerTimeBeforeMin' => __('Time is inside the lead-time window.', 'wsb'),
                'pickerDateBlocked' => __('Selected date is blocked for bookings.', 'wsb'),
                'placeSnapshotStale' => __('Location was edited after selection. Please select a place again.', 'wsb'),
                'placeSnapshotRequired' => __('Please choose the address from the suggestions.', 'wsb'),
            ),
            'googlePlaces' => array(
                'enabled' => $google_places_enabled,
                'available' => $google_places_available,
                'requiredForQuoteReady' => true,
            ),
        );

        wp_add_inline_script('wsb-booking-client-form-script', 'window.WSB_BOOKING_CLIENT_FORM = ' . wp_json_encode($config) . ';', 'before');
    }

    private static function render_data_attributes(array $field): string {
        $attrs = $field['data_attributes'] ?? array();
        if ( empty( $attrs ) ) {
            return '';
        }

        $parts = array();
        foreach ( $attrs as $key => $value ) {
            if ( '' === $value ) {
                $parts[] = esc_attr( $key );
            } else {
                $parts[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
            }
        }

        return ' ' . implode( ' ', $parts );
    }

    public static function enqueue_assets(): void {
        self::register_assets();
        wp_enqueue_style('wsb-booking-client-form-style');
        wp_enqueue_script('wsb-sortable-lite');
        wp_enqueue_script('wsb-booking-client-form-script');
        wp_enqueue_script('wsb-clock-timepicker');
        if (defined('GOOGLE_API_KEY') && GOOGLE_API_KEY !== '') {
            wp_enqueue_script('wsb-google-places-api');
        }
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
}
