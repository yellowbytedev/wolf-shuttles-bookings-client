(function () {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    var DEBUG = window.location.search.indexOf('debug=1') !== -1;

    function logDebug() {
        if (!DEBUG || typeof window.console === 'undefined' || typeof window.console.log !== 'function') {
            return;
        }

        window.console.log.apply(window.console, arguments);
    }

    function debounce(fn, delay) {
        var timer = null;
        return function debounced() {
            var context = this;
            var args = arguments;
            clearTimeout(timer);
            timer = window.setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    function forEachNode(list, callback) {
        Array.prototype.forEach.call(list || [], callback);
    }

    function trimValue(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    function getField(form, selector) {
        return form.querySelector(selector);
    }

    function getFieldValue(form, selector, fallback) {
        var input = getField(form, selector);
        if (!input) {
            return fallback;
        }

        if (input.type === 'checkbox') {
            return Boolean(input.checked);
        }

        if (input.type === 'radio') {
            if (selector.indexOf(':checked') !== -1) {
                return input.checked ? input.value : fallback;
            }

            var checked = getField(form, selector + ':checked');
            return checked ? checked.value : fallback;
        }

        return trimValue(input.value) || fallback;
    }

    function getNumberValue(form, selector, fallback) {
        var parsed = parseInt(getFieldValue(form, selector, ''), 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function getBooleanValue(form, selector) {
        return Boolean(getFieldValue(form, selector, false));
    }

    function textLocation(label) {
        var text = trimValue(label);
        return {
            label: text,
            name: '',
            town: '',
            neighbourhood: '',
            place_id: '',
            coords: {
                lat: null,
                lng: null
            },
            formatted_address: text
        };
    }

    function buildLeg(form, type) {
        var prefix = type === 'return' ? 'return_' : 'outbound_';
        var leg = {
            type: type,
            from: textLocation(getFieldValue(form, 'input[name="' + prefix + 'from"]', '')),
            to: textLocation(getFieldValue(form, 'input[name="' + prefix + 'to"]', '')),
            pickup_date: getFieldValue(form, 'input[name="' + prefix + 'pickup_date"]', ''),
            pickup_time: getFieldValue(form, 'input[name="' + prefix + 'pickup_time"]', ''),
            pickup_datetime: trimValue(getFieldValue(form, 'input[name="' + prefix + 'pickup_date"]', '') + ' ' + getFieldValue(form, 'input[name="' + prefix + 'pickup_time"]', '')),
            stops: [],
            route: {}
        };
        return leg;
    }

    function buildPayload(form) {
        var tripType = getFieldValue(form, 'input[name="trip_type"]:checked', 'one_way');
        var additionalStopEnabled = getBooleanValue(form, 'input[name="additional_stop_enabled"]');
        var additionalStop = trimValue(getFieldValue(form, 'input[name="additional_stop"]', ''));
        var outboundLeg = buildLeg(form, 'outbound');
        var legs = [outboundLeg];
        var passengers = getNumberValue(form, 'input[name="passengers"]', 1);

        if (passengers < 1) {
            passengers = 1;
        }

        if (additionalStopEnabled && additionalStop) {
            outboundLeg.stops.push({
                type: 'additional_stop',
                location: textLocation(additionalStop)
            });
        }

        if (tripType === 'return') {
            legs.push(buildLeg(form, 'return'));
        }

        return {
            schema_version: '2.0',
            source: 'marketing_booking_builder',
            service_group: 'transfer',
            service_type: 'city_transfer',
            trip_type: tripType,
            passengers: passengers,
            baby_seats: getNumberValue(form, 'input[name="baby_seats"]', 0),
            luggage: {
                check_in_bags: getNumberValue(form, 'input[name="check_in_bags"]', 0),
                carry_on_bags: getNumberValue(form, 'input[name="carry_on_bags"]', 0)
            },
            add_ons: {
                trailer: getBooleanValue(form, 'input[name="trailer"]'),
                oversize_luggage: getBooleanValue(form, 'input[name="oversize_luggage"]')
            },
            legs: legs,
            meta: {
                preview_only: true,
                created_at: new Date().toISOString()
            }
        };
    }

    function renderPreviewSummary(statusElement, payload) {
        if (!statusElement) {
            return;
        }

        var legCount = payload.legs ? payload.legs.length : 0;
        var hasAdditional = payload.legs && payload.legs[0] && payload.legs[0].stops && payload.legs[0].stops.length > 0;
        var summary = [
            'Live payload preview active',
            'trip: ' + payload.trip_type,
            legCount + ' leg' + (legCount === 1 ? '' : 's'),
            'additional stop: ' + (hasAdditional ? 'enabled' : 'disabled'),
            'updated: ' + new Date().toLocaleTimeString()
        ];

        statusElement.textContent = summary.join(' · ');
    }

    function renderPayload(previewElement, statusElement, messageElement, payload, message) {
        if (previewElement) {
            previewElement.textContent = JSON.stringify(payload, null, 2);
        }

        renderPreviewSummary(statusElement, payload);

        if (messageElement) {
            messageElement.textContent = message || '';
        }

        logDebug('Booking Builder preview updated', payload);
    }

    function initBookingBuilder(root) {
        var form = root.querySelector('[data-wsb-booking-form]');
        var returnSection = root.querySelector('[data-wsb-return-section]');
        var additionalStopToggle = root.querySelector('[data-wsb-additional-stop-toggle]');
        var additionalStopField = root.querySelector('[data-wsb-additional-stop-section]');
        var previewElement = root.querySelector('[data-wsb-payload-preview]');
        var statusElement = root.querySelector('[data-wsb-preview-status]');
        var messageElement = root.querySelector('[data-wsb-submit-message]');

        if (!form) {
            if (DEBUG) {
                logDebug('Missing booking form in wrapper', root);
            }
            return;
        }

        if (!previewElement) {
            if (DEBUG) {
                logDebug('Missing payload preview element in booking builder', root);
            }
            return;
        }

        function refreshPreview(message) {
            var payload = buildPayload(form);
            renderPayload(previewElement, statusElement, messageElement, payload, message);
            return payload;
        }

        var debouncedRefresh = debounce(function () {
            refreshPreview('');
        }, 150);

        var tripTypeInputs = form.querySelectorAll('input[name="trip_type"]');
        forEachNode(tripTypeInputs, function (radio) {
            radio.addEventListener('change', function () {
                updateReturnVisibility(returnSection, tripTypeInputs);
                refreshPreview('');
            });
        });

        if (additionalStopToggle) {
            additionalStopToggle.addEventListener('change', function () {
                updateAdditionalStop(additionalStopToggle, additionalStopField);
                refreshPreview('');
            });
        }

        form.addEventListener('input', debouncedRefresh);
        form.addEventListener('change', function () {
            refreshPreview('');
        });
        form.addEventListener('blur', function () {
            refreshPreview('');
        }, true);
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            refreshPreview('Preview updated. Real booking submission is not enabled yet.');
        });

        updateReturnVisibility(returnSection, tripTypeInputs);
        updateAdditionalStop(additionalStopToggle, additionalStopField);
        refreshPreview('Live payload preview initialised');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var wrappers = document.querySelectorAll('[data-wsb-booking-builder]');

        if (!wrappers.length) {
            if (DEBUG) {
                logDebug('No booking builder wrapper found on page');
            }
            return;
        }

        logDebug('Booking Builder preview initialised', 'wrapper count:', wrappers.length);
        forEachNode(wrappers, initBookingBuilder);
    });
})();
