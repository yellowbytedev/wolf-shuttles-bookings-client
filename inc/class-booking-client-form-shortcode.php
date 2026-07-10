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
                <button type="button" class="wsb-booking-client-service-tab" data-service="plan" data-wsb-service-tab="plan"><?php echo self::render_ui_icon('route'); ?><span><?php echo esc_html__('Plan Full Booking', 'wsb'); ?></span></button>
            </div>

            <form class="wsb-booking-client-form" data-wsb-booking-form method="post" action="#" novalidate>
                <div class="wsb-booking-client-grid wsb-form-layout">
                    <div class="wsb-booking-client-main-column">
                        <section class="wsb-booking-client-card wsb-form-shell wsb-booking-client-transfer-only" data-wsb-transfer-fields>
                            <div class="wsb-form-header wsb-booking-client-card-header">
                                <div>
                                    <p class="wsb-booking-client-eyebrow"><?php echo esc_html__('Book a Ride', 'wsb'); ?></p>
                                    <h3><?php echo esc_html__('Trip details', 'wsb'); ?></h3>
                                </div>
                            </div>

                            <div class="wsb-booking-client-mode-row wsb-top-controls">
                                <div class="wsb-segmented-switch wsb-booking-client-pill-group" role="radiogroup" aria-label="<?php echo esc_attr__('Trip type', 'wsb'); ?>">
                                    <label class="wsb-booking-client-pill wsb-booking-client-pill--radio">
                                        <input type="radio" name="trip_type" value="one_way" checked>
                                        <span><?php echo esc_html__('One-way', 'wsb'); ?></span>
                                    </label>
                                    <label class="wsb-booking-client-pill wsb-booking-client-pill--radio">
                                        <input type="radio" name="trip_type" value="return">
                                        <span><?php echo esc_html__('Return', 'wsb'); ?></span>
                                    </label>
                                </div>

                                <div class="wsb-booking-client-addons-row wsb-booking-client-addons-row--inline">
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

                            <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--options">
                                <?php echo self::render_number_field($fields['passengers'], 'book-a-ride'); ?>
                                <?php echo self::render_number_field($fields['baby_seats'], 'book-a-ride'); ?>
                                <?php echo self::render_number_field($fields['check_in_bags'], 'book-a-ride'); ?>
                                <?php echo self::render_number_field($fields['carry_on_bags'], 'book-a-ride'); ?>
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
                            <div class="wsb-form-header wsb-booking-client-card-header">
                                <div>
                                    <p class="wsb-booking-client-eyebrow"><?php echo esc_html__('Shuttle Hire', 'wsb'); ?></p>
                                    <h3><?php echo esc_html__('Plan your shuttle hire', 'wsb'); ?></h3>
                                    <p class="wsb-booking-client-card-copy"><?php echo esc_html__('Create your charter by adding your trip details below. You’ll see pricing and availability next.', 'wsb'); ?></p>
                                </div>
                            </div>

                            <?php if ($multi_day_enabled): ?>
                            <div class="wsb-charter-mode-switch wsb-booking-client-charter-mode-bar" role="radiogroup" aria-label="<?php echo esc_attr__('Hire length', 'wsb'); ?>">
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
                                <div class="wsb-charter-trip-details__header">
                                    <h4><?php echo esc_html__('Your trip details', 'wsb'); ?></h4>
                                    <p><?php echo esc_html__('Let us know the details of your group and luggage.', 'wsb'); ?></p>
                                </div>

                                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--options">
                                    <?php echo self::render_number_field($fields['passengers'], 'shuttle-hire'); ?>
                                    <?php echo self::render_number_field($fields['baby_seats'], 'shuttle-hire'); ?>
                                    <?php echo self::render_number_field($fields['check_in_bags'], 'shuttle-hire'); ?>
                                    <?php echo self::render_number_field($fields['carry_on_bags'], 'shuttle-hire'); ?>
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

                            <div class="wsb-charter-single-day-panel wsb-booking-client-charter-same-day-panel wsb-charter-panel" data-wsb-charter-same-day-panel>
                                <div class="wsb-charter-panel__header">
                                    <h4><?php echo esc_html__('Single-day charter details', 'wsb'); ?></h4>
                                    <p><?php echo esc_html__('Tell us when and where you need to go.', 'wsb'); ?></p>
                                </div>

                                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--datetime wsb-grid--three">
                                    <?php echo self::render_date_field($fields['outbound_pickup_date'], 'shuttle-hire', false); ?>
                                    <?php echo self::render_time_field($fields['charter_pickup_time'], 'shuttle-hire', false); ?>
                                    <?php echo self::render_time_field($fields['charter_dropoff_time'], 'shuttle-hire', false); ?>
                                </div>

                                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--locations">
                                    <?php echo self::render_text_field($fields['charter_pickup_location'], 'shuttle-hire'); ?>
                                    <?php echo self::render_text_field($fields['charter_dropoff_location'], 'shuttle-hire'); ?>
                                </div>

                                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact wsb-booking-client-grid--notes">
                                    <?php echo self::render_text_field($fields['charter_poi'], 'shuttle-hire', false); ?>
                                    <?php echo self::render_textarea_field($fields['charter_notes'], 'shuttle-hire', false); ?>
                                </div>
                            </div>

                            <?php if ($multi_day_enabled): ?>
                                <?php echo self::render_multiday_charter_shell($fields); ?>
                            <?php endif; ?>
                        </section>

                        <section class="wsb-booking-client-card wsb-form-shell wsb-booking-client-plan wsb-booking-client-hidden" data-wsb-plan-section>
                            <div class="wsb-form-header wsb-booking-client-card-header">
                                <div>
                                    <p class="wsb-booking-client-eyebrow"><?php echo esc_html__('Plan Full Booking', 'wsb'); ?></p>
                                    <h3><?php echo esc_html__('Build your itinerary', 'wsb'); ?></h3>
                                    <p class="wsb-booking-client-card-copy"><?php echo esc_html__('Add transfers and shuttle-hire days in one booking plan. This tab is ready for the next build phase.', 'wsb'); ?></p>
                                </div>
                            </div>
                            <div class="wsb-booking-client-plan-shell" aria-label="Plan Full Booking preview">
                                <article class="wsb-booking-client-plan-card">
                                    <span class="wsb-booking-client-plan-card__badge"><?php echo esc_html__('Next phase', 'wsb'); ?></span>
                                    <h4><?php echo esc_html__('Multi-trip planning shell', 'wsb'); ?></h4>
                                    <p><?php echo esc_html__('Customers will be able to combine transfers, return trips and shuttle hire in a single flow.', 'wsb'); ?></p>
                                </article>
                                <button type="button" class="wsb-booking-client-charter-action" disabled><?php echo esc_html__('Add another trip', 'wsb'); ?></button>
                            </div>
                        </section>

                        <div class="wsb-booking-client-actions">
                            <button type="submit" class="wsb-primary-cta wsb-booking-client-submit" data-wsb-preview-submit><?php echo esc_html__('Check Pricing & Availability', 'wsb'); ?><span aria-hidden="true"></span></button>
                            <div class="wsb-booking-client-submit-message" aria-live="polite" data-wsb-submit-message></div>
                            <p class="wsb-secure-note wsb-booking-client-note"><?php echo self::render_ui_icon('lock'); ?><?php echo esc_html__('Secure booking. No payment required yet.', 'wsb'); ?></p>
                        </div>
                    </div>

                    <aside class="wsb-booking-client-preview-column" aria-live="polite">
                        <section class="wsb-booking-client-card wsb-booking-client-card--preview">
                            <div class="wsb-booking-client-card-header">
                                <h3><?php echo esc_html__('Booking summary', 'wsb'); ?></h3>
                            </div>
                            <div class="wsb-booking-client-preview-help"><?php echo esc_html__('Pricing is calculated on the next step based on your selections and availability.', 'wsb'); ?></div>
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
            '<div class="wsb-select-field wsb-booking-client-field wsb-booking-client-field--select wsb-booking-client-field--key-%7$s"%5$s><label class="wsb-form__label" for="%1$s">%2$s</label><div class="wsb-field-control wsb-field-control--select wsb-field-control--key-%7$s"><span class="wsb-field-control__icon" aria-hidden="true"></span><select class="wsb-form__input" id="%1$s" name="%3$s"%6$s>%4$s</select></div></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['key']),
            $options_html,
            $data_attrs,
            $required ? ' required' : '',
            esc_attr(str_replace('_', '-', $field['key']))
        );
    }

    private static function render_text_field(array $field, string $dom_context, bool $required = true): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $data_attrs = self::render_data_attributes($field);
        $is_location_field = in_array($field['key'], [
            'outbound_from', 'outbound_to', 'outbound_additional_stop',
            'return_from', 'return_to', 'return_additional_stop',
            'charter_pickup_location', 'charter_dropoff_location',
            'charter_day_pickup_location', 'charter_day_dropoff_location'
        ], true);

        if ($is_location_field) {
            $clear_label = esc_attr__('Clear location', 'wsb');
            $current_label = esc_attr__('Use current location', 'wsb');
            $stale_message = esc_html__('Location was edited after selection. Please select a place again.', 'wsb');
            $confirmed_message = esc_html__('Location confirmed.', 'wsb');
            $name_attr = esc_attr($field['key']);
            $placeholder_attr = esc_attr($field['placeholder'] ?? '');
            $required_attr = $required ? 'required' : '';
            $icon_class = in_array($field['key'], array('outbound_additional_stop', 'return_additional_stop'), true) ? 'wsb-booking-client-field-icon--stop' : 'wsb-booking-client-field-icon--location';
            $current_button = '<button type="button" class="wsb-booking-client-place-current" data-wsb-place-current aria-label="' . $current_label . '" title="' . $current_label . '"></button>';
            return '<div class="wsb-location-field wsb-booking-client-field wsb-booking-client-field--location wsb-booking-client-field--key-' . esc_attr(str_replace('_', '-', $field['key'])) . '"' . $data_attrs . '><label class="wsb-form__label" for="' . $dom_id . '">' . esc_html($field['label']) . '</label><div class="wsb-booking-client-location-row"><span class="wsb-booking-client-field-icon ' . esc_attr($icon_class) . '" aria-hidden="true"></span><input class="wsb-form__input" type="text" id="' . $dom_id . '" name="' . $name_attr . '" placeholder="' . $placeholder_attr . '" ' . $required_attr . ' /><div class="wsb-booking-client-location-actions"><button type="button" class="wsb-booking-client-place-clear" data-wsb-place-clear aria-label="' . $clear_label . '"></button>' . $current_button . '</div></div><span class="wsb-booking-client-place-confirmed-message" aria-live="polite">' . $confirmed_message . '</span><span class="wsb-booking-client-place-stale-message" aria-live="polite">' . $stale_message . '</span></div>';
        }

        if (in_array($field['key'], array('charter_poi', 'charter_day_poi'), true)) {
            $list_id = self::build_dom_id($dom_context, $field['key'] . '-list');
            return sprintf(
                '<div class="wsb-booking-client-field wsb-booking-client-field--poi wsb-poi-field"%6$s><label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="text" id="%1$s" name="%7$s" list="%8$s" placeholder="%3$s" %4$s /><datalist id="%8$s">%5$s</datalist></div>',
                esc_attr($dom_id),
                esc_html($field['label']),
                esc_attr($field['placeholder'] ?? ''),
                $required ? 'required' : '',
                self::render_poi_options(),
                $data_attrs,
                esc_attr($field['key']),
                esc_attr($list_id)
            );
        }

        return sprintf(
            '<div class="wsb-booking-client-field"%5$s><label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="text" id="%1$s" name="%6$s" placeholder="%3$s" %4$s /></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : '',
            $data_attrs,
            esc_attr($field['key'])
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
            '<div class="wsb-date-field wsb-booking-client-picker-group wsb-booking-client-field--date"%6$s><div class="wsb-booking-client-picker-wrapper"><label class="wsb-form__label" for="%1$s">%2$s</label><div class="wsb-booking-client-date-row"><span class="wsb-booking-client-date-icon" aria-hidden="true"></span><input class="wsb-form__input" type="text" inputmode="numeric" autocomplete="off" data-wsb-datepicker id="%1$s" name="%9$s" placeholder="%3$s" %4$s%5$s%7$s /></div><div class="wsb-picker-status"%8$s></div></div></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : '',
            $date_min,
            $data_attrs,
            $date_max,
            $status_attr,
            $field_key
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
            '<div class="wsb-time-field wsb-booking-client-field wsb-booking-client-field--time"%6$s><label class="wsb-form__label" for="%1$s">%2$s</label><div class="wsb-booking-client-time-row"><span class="wsb-booking-client-time-icon" aria-hidden="true"></span><input class="wsb-form__input"%4$s type="text" id="%1$s" name="%7$s" placeholder="%3$s" autocomplete="off" %5$s /><span class="wsb-booking-client-ampm" data-wsb-ampm-badge>AM</span></div></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $step,
            $required ? 'required' : '',
            $data_attrs,
            esc_attr($field['key'])
        );
    }

    private static function render_textarea_field(array $field, string $dom_context, bool $required = false): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $data_attrs = self::render_data_attributes($field);

        return sprintf(
            '<div class="wsb-booking-client-field wsb-booking-client-field--textarea wsb-notes-field"%5$s><label class="wsb-form__label" for="%1$s">%2$s</label><textarea class="wsb-form__input" rows="3" id="%1$s" name="%6$s" placeholder="%3$s" %4$s></textarea></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : '',
            $data_attrs,
            esc_attr($field['key'])
        );
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

        $header_toggle_label = $collapsed ? __('Open', 'wsb') : __('Close', 'wsb');
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
                <button type="button" class="wsb-drag-handle wsb-icon-action" data-wsb-drag-handle aria-label="<?php echo esc_attr__('Drag to reorder this day', 'wsb'); ?>" draggable="true"></button>
                <span class="wsb-charter-day-icon" aria-hidden="true"></span>
                <button type="button" class="wsb-charter-day-title" data-wsb-charter-day-toggle aria-expanded="<?php echo esc_attr($expanded); ?>">
                    <strong><?php echo esc_html(sprintf(__('Day %d', 'wsb'), $day_number)); ?> — <span data-wsb-day-route-label><?php echo esc_html__('Add route', 'wsb'); ?></span></strong>
                    <small data-wsb-day-summary><?php echo esc_html__('Date · start time – end time', 'wsb'); ?></small>
                </button>
                <div class="wsb-booking-client-charter-day-actions">
                    <button type="button" class="wsb-icon-action wsb-icon-action--copy" data-wsb-charter-day-duplicate aria-label="<?php echo esc_attr__('Copy this day', 'wsb'); ?>"></button>
                    <button type="button" class="wsb-icon-action wsb-icon-action--delete" data-wsb-charter-day-delete aria-label="<?php echo esc_attr__('Remove day', 'wsb'); ?>"></button>
                    <button type="button" class="wsb-icon-action wsb-icon-action--toggle" data-wsb-charter-day-toggle aria-expanded="<?php echo esc_attr($expanded); ?>" aria-label="<?php echo esc_attr($header_toggle_label); ?>"></button>
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
                    <p class="wsb-booking-client-eyebrow"><?php echo esc_html__('Multi-day hire', 'wsb'); ?></p>
                    <h4><?php echo esc_html__('Plan each day', 'wsb'); ?></h4>
                </div>
                <p class="wsb-booking-client-card-copy"><?php echo esc_html__('Add the places and notes for each day.', 'wsb'); ?></p>
            </div>

            <div class="wsb-booking-client-charter-multiday-toolbar wsb-booking-client-hidden" data-wsb-charter-day-toolbar>
                <button type="button" class="wsb-booking-client-charter-action" data-wsb-charter-add-day>
                    <?php echo esc_html__('Add another day', 'wsb'); ?>
                </button>
                <button type="button" class="wsb-booking-client-charter-action" data-wsb-charter-collapse-all>
                    <?php echo esc_html__('Close all', 'wsb'); ?>
                </button>
                <button type="button" class="wsb-booking-client-charter-action" data-wsb-charter-expand-all>
                    <?php echo esc_html__('Open all', 'wsb'); ?>
                </button>
            </div>

            <div class="wsb-charter-day-list wsb-booking-client-charter-day-list wsb-booking-client-hidden" data-wsb-charter-day-list data-wsb-sortable-list="charter-day-list">
                <?php echo self::render_charter_day_card($fields, 0, true, false); ?>
                <?php echo self::render_charter_day_card($fields, 1, false, true); ?>
                <?php echo self::render_charter_day_card($fields, 2, false, true); ?>
            </div>
            <button type="button" class="wsb-booking-client-add-day-row wsb-booking-client-hidden" data-wsb-charter-add-day data-wsb-charter-add-day-row>
                <span aria-hidden="true">+</span><?php echo esc_html__('Add another day', 'wsb'); ?>
            </button>
        </section>
        <?php

        return (string) ob_get_clean();
    }


    private static function get_poi_choices(): array {
        $defaults = array(
            'Cape Town City Centre',
            'Cape Winelands',
            'Stellenbosch',
            'Franschhoek',
            'Paarl',
            'Constantia Wine Route',
            'Hermanus',
            'Betty\'s Bay',
            'Gansbaai',
            'West Coast',
            'Garden Route',
            'Knysna',
            'Plettenberg Bay',
            'Tsitsikamma',
            'Safari / Game Reserve',
            'Wedding venue',
            'School or sports event',
            'Corporate event',
            'Other / custom itinerary',
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
        $current_label = esc_attr__('Use current location', 'wsb');
        $stale_message = esc_html__('Location was edited after selection. Please select a place again.', 'wsb');
        $confirmed_message = esc_html__('Location confirmed.', 'wsb');
        $name_attr = esc_attr($field['key']);
        $placeholder_attr = esc_attr($field['placeholder'] ?? '');
        $section_attr = esc_attr($section_marker);
        $data_attrs = self::render_data_attributes($field);

        return '<div class="wsb-location-field wsb-booking-client-field wsb-booking-client-field--location wsb-booking-client-additional-stop-field wsb-booking-client-field--key-' . esc_attr(str_replace('_', '-', $field['key'])) . ' wsb-booking-client-hidden" ' . $section_attr . $data_attrs . ' data-ws-feature-gate="enable_additional_stops"><label class="wsb-form__label" for="' . $dom_id . '">' . esc_html($field['label']) . '</label><div class="wsb-booking-client-location-row"><span class="wsb-booking-client-field-icon wsb-booking-client-field-icon--stop" aria-hidden="true"></span><input class="wsb-form__input" type="text" id="' . $dom_id . '" name="' . $name_attr . '" placeholder="' . $placeholder_attr . '" disabled /><div class="wsb-booking-client-location-actions"><button type="button" class="wsb-booking-client-place-clear" data-wsb-place-clear aria-label="' . $clear_label . '"></button><button type="button" class="wsb-booking-client-place-current" data-wsb-place-current aria-label="' . $current_label . '" title="' . $current_label . '"></button></div><button type="button" class="wsb-booking-client-remove-stop-button" data-wsb-additional-stop-remove aria-label="' . esc_attr__('Remove additional stop', 'wsb') . '">×</button></div><span class="wsb-booking-client-place-confirmed-message" aria-live="polite">' . $confirmed_message . '</span><span class="wsb-booking-client-place-stale-message" aria-live="polite">' . $stale_message . '</span></div>';
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
