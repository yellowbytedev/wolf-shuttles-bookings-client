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

            <div class="wsb-booking-client-service-tabs" role="tablist">
                <button type="button" class="wsb-booking-client-service-tab wsb-booking-client-service-tab--active" data-service="transfer" data-wsb-service-tab="transfer"><?php echo esc_html__('Book a Ride', 'wsb'); ?></button>
                <button type="button" class="wsb-booking-client-service-tab" data-service="charter" data-wsb-service-tab="charter"><?php echo esc_html__('Shuttle Hire', 'wsb'); ?></button>
            </div>

            <form class="wsb-booking-client-form" data-wsb-booking-form method="post" action="#" novalidate>
                <div class="wsb-booking-client-grid">
                    <div class="wsb-booking-client-main-column">
                        <section class="wsb-booking-client-card wsb-booking-client-transfer-only" data-wsb-transfer-fields>
                            <div class="wsb-booking-client-card-header">
                                <div>
                                    <p class="wsb-booking-client-eyebrow"><?php echo esc_html__('Book a Ride', 'wsb'); ?></p>
                                    <h3><?php echo esc_html__('Trip details', 'wsb'); ?></h3>
                                </div>
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
                                <?php echo self::render_number_field($fields['passengers'], 'book-a-ride'); ?>
                                <?php echo self::render_number_field($fields['baby_seats'], 'book-a-ride'); ?>
                                <?php echo self::render_number_field($fields['check_in_bags'], 'book-a-ride'); ?>
                                <?php echo self::render_number_field($fields['carry_on_bags'], 'book-a-ride'); ?>
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

                        <section class="wsb-booking-client-card wsb-booking-client-charter wsb-booking-client-hidden" data-wsb-charter-section>
                            <div class="wsb-booking-client-card-header">
                                <div>
                                    <p class="wsb-booking-client-eyebrow"><?php echo esc_html__('Shuttle Hire', 'wsb'); ?></p>
                                    <h3><?php echo esc_html__('Plan your hire', 'wsb'); ?></h3>
                                </div>
                            </div>
                            <p class="wsb-booking-client-card-copy"><?php echo esc_html__('Choose same-day hire or multi-day hire and add the details below.', 'wsb'); ?></p>
                            <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
                                <?php echo self::render_number_field($fields['passengers'], 'shuttle-hire'); ?>
                                <?php echo self::render_number_field($fields['baby_seats'], 'shuttle-hire'); ?>
                                <?php echo self::render_number_field($fields['check_in_bags'], 'shuttle-hire'); ?>
                                <?php echo self::render_number_field($fields['carry_on_bags'], 'shuttle-hire'); ?>
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

                            <div class="wsb-booking-client-charter-same-day-panel" data-wsb-charter-same-day-panel>
                                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
                                    <?php echo self::render_text_field($fields['charter_pickup_location'], 'shuttle-hire'); ?>
                                    <?php echo self::render_text_field($fields['charter_dropoff_location'], 'shuttle-hire'); ?>
                                </div>

                                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
                                    <?php echo self::render_date_field($fields['outbound_pickup_date'], 'shuttle-hire', false); ?>
                                    <?php echo self::render_time_field($fields['charter_pickup_time'], 'shuttle-hire', false); ?>
                                    <?php echo self::render_time_field($fields['charter_dropoff_time'], 'shuttle-hire', false); ?>
                                </div>
                            </div>

                            <?php if ($multi_day_enabled): ?>
                                <?php echo self::render_multiday_charter_shell($fields); ?>
                            <?php endif; ?>
                        </section>

                        <section class="wsb-booking-client-card wsb-booking-client-outbound" data-wsb-outbound-section>
                            <?php echo self::render_text_field($fields['outbound_from'], 'book-a-ride'); ?>
                            <label class="wsb-booking-client-checkbox-label wsb-booking-client-additional-toggle-label">
                                <input type="checkbox" name="outbound_additional_stop_enabled" class="wsb-booking-client-additional-toggle" data-wsb-outbound-additional-stop-toggle data-ws-feature-gate="enable_additional_stops">
                                <?php echo esc_html(__('Enable additional stop', 'wsb')); ?>
                            </label>
                            <?php echo self::render_additional_stop_field($fields['outbound_additional_stop'], 'book-a-ride', 'data-wsb-outbound-additional-stop-section'); ?>
                            <?php echo self::render_text_field($fields['outbound_to'], 'book-a-ride'); ?>
                            <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
                                <?php echo self::render_date_field($fields['outbound_pickup_date'], 'book-a-ride'); ?>
                                <?php echo self::render_time_field($fields['outbound_pickup_time'], 'book-a-ride'); ?>
                            </div>
                            <p class="wsb-booking-client-picker-legend">
                                <?php echo esc_html__('Blocked dates are shown and cannot be selected.', 'wsb'); ?>
                            </p>
                        </section>

                        <section class="wsb-booking-client-card wsb-booking-client-return wsb-booking-client-hidden" data-wsb-return-section>
                            <div class="wsb-booking-client-card-header">
                                <h3><?php echo esc_html__('Return transfer', 'wsb'); ?></h3>
                            </div>
                            <?php echo self::render_text_field($fields['return_from'], 'book-a-ride', false); ?>
                            <label class="wsb-booking-client-checkbox-label wsb-booking-client-additional-toggle-label">
                                <input type="checkbox" name="return_additional_stop_enabled" class="wsb-booking-client-additional-toggle" data-wsb-return-additional-stop-toggle data-ws-feature-gate="enable_additional_stops">
                                <?php echo esc_html(__('Enable additional stop', 'wsb')); ?>
                            </label>
                            <?php echo self::render_additional_stop_field($fields['return_additional_stop'], 'book-a-ride', 'data-wsb-return-additional-stop-section'); ?>
                            <?php echo self::render_text_field($fields['return_to'], 'book-a-ride', false); ?>
                            <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
                                <?php echo self::render_date_field($fields['return_pickup_date'], 'book-a-ride', false); ?>
                                <?php echo self::render_time_field($fields['return_pickup_time'], 'book-a-ride', false); ?>
                            </div>
                            <p class="wsb-booking-client-picker-legend wsb-booking-client-picker-legend--time">
                                <?php echo esc_html__('Return transfer uses the same 5-hour lead time.', 'wsb'); ?>
                            </p>
                        </section>

                        <div class="wsb-booking-client-actions">
                            <button type="submit" class="wsb-booking-client-submit" data-wsb-preview-submit><?php echo esc_html__('Check Pricing & Availability', 'wsb'); ?></button>
                            <div class="wsb-booking-client-submit-message" aria-live="polite" data-wsb-submit-message></div>
                            <p class="wsb-booking-client-note"><?php echo esc_html__("We'll use these details to review your request.", 'wsb'); ?></p>
                        </div>
                    </div>

                    <aside class="wsb-booking-client-preview-column">
                        <section class="wsb-booking-client-card wsb-booking-client-card--preview">
                            <div class="wsb-booking-client-card-header">
                                <h3><?php echo esc_html__('Booking summary', 'wsb'); ?></h3>
                            </div>
                            <div class="wsb-booking-client-preview-help"><?php echo esc_html__('Review your trip details and validation response below.', 'wsb'); ?></div>
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
                    class="wsb-booking-client-fixture-drawer wsb-booking-client-hidden"
                    data-wsb-fixture-drawer
                    id="wsb-booking-client-fixture-drawer"
                >
                    <div class="wsb-booking-client-fixture-drawer-inner">
                        <div class="wsb-booking-client-fixture-header">
                            <div>
                                <strong><?php echo esc_html__('Sample Data Drawer', 'wsb'); ?></strong>
                                <p><?php echo esc_html__('Choose a sample to populate the form and review the checks locally.', 'wsb'); ?></p>
                            </div>
                            <button type="button" class="wsb-booking-client-fixture-close" data-wsb-fixture-close>
                                <?php echo esc_html__('Close', 'wsb'); ?>
                            </button>
                        </div>
                        <div class="wsb-booking-client-fixture-status" data-wsb-fixture-status aria-live="polite">
                            <?php echo esc_html__('Choose a sample to load booking details.', 'wsb'); ?>
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
        $max = isset($field['max_attr']) ? ' max="' . (int) $field['max_attr'] . '"' : '';
        $step = isset($field['step_attr']) && $field['step_attr'] ? ' step="' . (int) $field['step_attr'] . '"' : '';
        $data_attrs = self::render_data_attributes($field);
        return sprintf(
            '<div class="wsb-booking-client-field"%5$s><label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input"%6$s%7$s type="number" id="%1$s" name="%1$s" min="0" value="0" placeholder="%3$s" %4$s /></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required,
            $data_attrs,
            $max,
            $step
        );
    }

    private static function render_text_field(array $field, string $dom_context, bool $required = true): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $data_attrs = self::render_data_attributes($field);
        return sprintf(
            '<div class="wsb-booking-client-field"%5$s><label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="text" id="%1$s" name="%1$s" placeholder="%3$s" %4$s /></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : '',
            $data_attrs
        );
    }

    private static function render_date_field(array $field, string $dom_context, bool $required = true): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $date_min = !empty($field['date_min_attr']) ? ' min="' . esc_attr($field['date_min_attr']) . '"' : '';
        $date_max = !empty($field['date_max_attr']) ? ' max="' . esc_attr($field['date_max_attr']) . '"' : '';
        $status_attr = '';
        if (false !== strpos($field['key'], 'outbound')) {
            $status_attr = ' data-wsb-outbound-picker-status';
        } elseif (false !== strpos($field['key'], 'return')) {
            $status_attr = ' data-wsb-return-picker-status';
        }
        $data_attrs = self::render_data_attributes($field);

        return sprintf(
            '<div class="wsb-booking-client-picker-group"%6$s><div class="wsb-booking-client-picker-wrapper"><label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="date" id="%1$s" name="%1$s" placeholder="%3$s" %4$s%5$s%7$s /><div class="wsb-picker-status"%8$s></div></div></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : '',
            $date_min,
            $data_attrs,
            $date_max,
            $status_attr
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
            '<div class="wsb-booking-client-field"%6$s><label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input"%4$s type="text" id="%1$s" name="%1$s" placeholder="%3$s" %5$s /></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $step,
            $required ? 'required' : '',
            $data_attrs
        );
    }

    private static function render_textarea_field(array $field, string $dom_context, bool $required = false): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $data_attrs = self::render_data_attributes($field);

        return sprintf(
            '<div class="wsb-booking-client-field wsb-booking-client-field--textarea"%5$s><label class="wsb-form__label" for="%1$s">%2$s</label><textarea class="wsb-form__input" rows="3" id="%1$s" name="%1$s" placeholder="%3$s" %4$s></textarea></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['placeholder'] ?? ''),
            $required ? 'required' : '',
            $data_attrs
        );
    }

    private static function render_charter_day_card(array $fields, int $slot_index, bool $visible = false, bool $collapsed = false): string {
        $day_number = $slot_index + 1;
        $day_id = 'day_' . $slot_index;
        $dom_context = 'shuttle-hire-day-' . $day_number;
        $card_classes = array('wsb-booking-client-charter-day-card', 'wsb-booking-client-card');
        if (! $visible) {
            $card_classes[] = 'wsb-booking-client-hidden';
        }
        if ($collapsed) {
            $card_classes[] = 'wsb-booking-client-charter-day-card--collapsed';
        }

        $body_classes = array('wsb-booking-client-charter-day-body');
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
            <div class="wsb-booking-client-card-header wsb-booking-client-charter-day-header">
                <div>
                    <p class="wsb-booking-client-eyebrow"><?php echo esc_html(sprintf(__('Day %d', 'wsb'), $day_number)); ?></p>
                    <h4><?php echo esc_html__('Day details', 'wsb'); ?></h4>
                    <p class="wsb-booking-client-card-copy"><?php echo esc_html__('Add the places and notes for this day.', 'wsb'); ?></p>
                </div>
                <div class="wsb-booking-client-charter-day-actions">
                    <button
                        type="button"
                        class="wsb-booking-client-charter-day-toggle"
                        data-wsb-charter-day-toggle
                        aria-expanded="<?php echo esc_attr($expanded); ?>"
                    >
                        <?php echo esc_html($header_toggle_label); ?>
                    </button>
                    <button
                        type="button"
                        class="wsb-booking-client-charter-day-duplicate"
                        data-wsb-charter-day-duplicate
                    >
                        <?php echo esc_html__('Copy this day', 'wsb'); ?>
                    </button>
                    <button
                        type="button"
                        class="wsb-booking-client-charter-day-delete"
                        data-wsb-charter-day-delete
                    >
                        <?php echo esc_html__('Remove day', 'wsb'); ?>
                    </button>
                </div>
            </div>

            <div class="<?php echo esc_attr(implode(' ', $body_classes)); ?>" data-wsb-charter-day-body>
                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
                    <?php echo self::render_date_field($fields['charter_day_date'], $dom_context); ?>
                    <?php echo self::render_time_field($fields['charter_day_start_time'], $dom_context); ?>
                    <?php echo self::render_time_field($fields['charter_day_end_time'], $dom_context); ?>
                </div>

                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
                    <?php echo self::render_text_field($fields['charter_day_pickup_location'], $dom_context); ?>
                    <?php echo self::render_text_field($fields['charter_day_dropoff_location'], $dom_context); ?>
                </div>

                <div class="wsb-booking-client-grid wsb-booking-client-grid--compact">
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
        <section class="wsb-booking-client-card wsb-booking-client-charter-multiday-shell" data-wsb-charter-multiday-shell data-ws-feature-gate="enable_multi_day_charters">
            <div class="wsb-booking-client-card-header">
                <div>
                    <p class="wsb-booking-client-eyebrow"><?php echo esc_html__('Multi-day hire', 'wsb'); ?></p>
                    <h4><?php echo esc_html__('Plan each day', 'wsb'); ?></h4>
                </div>
                <p class="wsb-booking-client-card-copy"><?php echo esc_html__('Add the places and notes for each day.', 'wsb'); ?></p>
            </div>

            <div class="wsb-booking-client-pill-group" role="radiogroup" aria-label="<?php echo esc_attr__('Charter mode', 'wsb'); ?>">
                <label class="wsb-booking-client-pill">
                    <input type="radio" name="charter_mode" value="same_day" checked data-wsb-charter-mode-option="same_day">
                    <span><?php echo esc_html__('Same-day hire', 'wsb'); ?></span>
                </label>
                <label class="wsb-booking-client-pill">
                    <input type="radio" name="charter_mode" value="multi_day" data-wsb-charter-mode-option="multi_day">
                    <span><?php echo esc_html__('Multi-day hire', 'wsb'); ?></span>
                </label>
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

            <div class="wsb-booking-client-charter-day-list wsb-booking-client-hidden" data-wsb-charter-day-list data-wsb-sortable-list="charter-day-list">
                <?php echo self::render_charter_day_card($fields, 0, true, false); ?>
                <?php echo self::render_charter_day_card($fields, 1, false, true); ?>
                <?php echo self::render_charter_day_card($fields, 2, false, true); ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private static function render_additional_stop_field(array $field, string $dom_context, string $section_marker): string {
        if (empty($field['key']) || empty($field['label'])) {
            return '';
        }

        $dom_id = self::build_dom_id($dom_context, $field['key']);
        $data_attrs = self::render_data_attributes($field);

        return sprintf(
            '<div class="wsb-booking-client-field wsb-booking-client-additional-stop-field wsb-booking-client-hidden" %6$s data-ws-feature-gate="enable_additional_stops"><label class="wsb-form__label" for="%1$s">%2$s</label><input class="wsb-form__input" type="text" id="%1$s" name="%3$s" placeholder="%4$s" disabled%5$s /></div>',
            esc_attr($dom_id),
            esc_html($field['label']),
            esc_attr($field['key']),
            esc_attr($field['placeholder'] ?? ''),
            $data_attrs,
            esc_attr($section_marker)
        );
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
            ['jquery'],
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
            'googlePlaces' => array(
                'enabled' => $google_places_enabled,
                'available' => $google_places_available,
                'requiredForQuoteReady' => ! empty($gates['google_places_required']) ? true : false,
            ),
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
