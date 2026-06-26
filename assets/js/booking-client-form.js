(function () {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    function isDebugMode() {
        return window.location.search.indexOf('debug=1') !== -1;
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

    function buildLocation(label) {
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

    function buildSource() {
        return {
            site: 'marketing',
            channel: 'shortcode_form',
            page_url: window.location.href,
            referrer: document.referrer || ''
        };
    }

    function buildTracking() {
        var params = {};
        var search = window.location.search.replace(/^\?/, '');

        if (!search) {
            return params;
        }

        search.split('&').forEach(function (pair) {
            if (!pair) {
                return;
            }

            var parts = pair.split('=');
            var key = decodeURIComponent((parts[0] || '').replace(/\+/g, '%20')).trim();
            var value = decodeURIComponent((parts[1] || '').replace(/\+/g, '%20')).trim();
            var allowed = key.indexOf('utm_') === 0 || 'gclid' === key || 'fbclid' === key;

            if (allowed && key) {
                params[key] = value;
            }
        });

        return params;
    }

    function buildLeg(form, type) {
        var prefix = 'return' === type ? 'return_' : 'outbound_';
        var leg = {
            sequence: 'return' === type ? 2 : 1,
            leg_group: 'return' === type ? 'return' : 'outbound',
            leg_type: 'direct',
            service_type: 'city_transfer',
            from: buildLocation(getFieldValue(form, 'input[name="' + prefix + 'from"]', '')),
            to: buildLocation(getFieldValue(form, 'input[name="' + prefix + 'to"]', '')),
            pickup_date: getFieldValue(form, 'input[name="' + prefix + 'pickup_date"]', ''),
            pickup_time: getFieldValue(form, 'input[name="' + prefix + 'pickup_time"]', ''),
            pickup_datetime: '',
            stops: [],
            route: {}
        };

        leg.pickup_datetime = trimValue(leg.pickup_date + ' ' + leg.pickup_time);

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
                location: buildLocation(additionalStop)
            });
        }

        if ('return' === tripType) {
            legs.push(buildLeg(form, 'return'));
        }

        return {
            schema_version: '2.0',
            source: buildSource(),
            service_group: 'transfer',
            service_type: 'city_transfer',
            trip_type: tripType,
            customer: {
                name: '',
                email: '',
                phone: ''
            },
            passengers: passengers,
            luggage: {
                check_in_bags: getNumberValue(form, 'input[name="check_in_bags"]', 0),
                carry_on_bags: getNumberValue(form, 'input[name="carry_on_bags"]', 0)
            },
            baby_seats: getNumberValue(form, 'input[name="baby_seats"]', 0),
            add_ons: {
                baby_seats: getNumberValue(form, 'input[name="baby_seats"]', 0),
                trailer: getBooleanValue(form, 'input[name="trailer"]'),
                oversize_luggage: getBooleanValue(form, 'input[name="oversize_luggage"]')
            },
            legs: legs,
            route: {
                place_ids: [],
                toll_gates: [],
                route_options: []
            },
            tracking: buildTracking(),
            validation_flags: {},
            meta: {
                handover_mode: 'preview_only',
                created_at: new Date().toISOString()
            },
            charter: null
        };
    }

    function updateReturnVisibility(returnSection, tripTypeRadios) {
        if (!returnSection) {
            return;
        }

        var isReturn = false;

        forEachNode(tripTypeRadios, function (radio) {
            if (radio.checked && 'return' === radio.value) {
                isReturn = true;
            }
        });

        if (isReturn) {
            returnSection.classList.remove('wsb-booking-client-hidden');
        } else {
            returnSection.classList.add('wsb-booking-client-hidden');
        }
    }

    function updateAdditionalStop(additionalStopToggle, additionalStopField) {
        if (!additionalStopToggle || !additionalStopField) {
            return;
        }

        var additionalInput = additionalStopField.querySelector('input');

        if (additionalStopToggle.checked) {
            additionalStopField.classList.remove('wsb-booking-client-hidden');
            if (additionalInput) {
                additionalInput.removeAttribute('disabled');
            }
        } else {
            additionalStopField.classList.add('wsb-booking-client-hidden');
            if (additionalInput) {
                additionalInput.setAttribute('disabled', 'disabled');
            }
        }
    }

    function renderPayloadPreview(previewElement, payload) {
        if (!previewElement) {
            return;
        }

        previewElement.textContent = JSON.stringify(payload, null, 2);
    }

    function initBookingBuilder(shell) {
        var form = shell.querySelector('.wsb-booking-client-form') || shell;
        var returnSection = form.querySelector('.wsb-booking-client-return');
        var additionalStopToggle = form.querySelector('.wsb-booking-client-additional-toggle');
        var additionalStopField = form.querySelector('.wsb-booking-client-additional-stop-field');
        var tripTypeRadios = form.querySelectorAll('input[name="trip_type"]');
        var previewElement = form.querySelector('.wsb-booking-client-preview-json');
        var messageElement = form.querySelector('.wsb-booking-client-submit-message');

        if (!form || !previewElement) {
            return;
        }

        function renderPreview(message) {
            var payload = buildPayload(form);

            renderPayloadPreview(previewElement, payload);

            if (messageElement) {
                messageElement.textContent = message || '';
            }

            if (isDebugMode() && window.console && typeof window.console.log === 'function') {
                window.console.log('[WSB BookingPayload v2]', payload);
            }

            return payload;
        }

        var debouncedRenderPreview = debounce(function () {
            renderPreview('');
        }, 150);

        forEachNode(tripTypeRadios, function (radio) {
            radio.addEventListener('change', function () {
                updateReturnVisibility(returnSection, tripTypeRadios);
                renderPreview('');
            });
        });

        if (additionalStopToggle) {
            additionalStopToggle.addEventListener('change', function () {
                updateAdditionalStop(additionalStopToggle, additionalStopField);
                renderPreview('');
            });
        }

        form.addEventListener('input', debouncedRenderPreview);
        form.addEventListener('change', function () {
            renderPreview('');
        });
        form.addEventListener('blur', function () {
            renderPreview('');
        }, true);
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            renderPreview('Real booking submission is disabled. This is a local BookingPayload v2 preview only.');
        });

        updateReturnVisibility(returnSection, tripTypeRadios);
        updateAdditionalStop(additionalStopToggle, additionalStopField);
        renderPreview('');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var shells = document.querySelectorAll('.wsb-booking-client-shell');

        if (shells.length) {
            forEachNode(shells, initBookingBuilder);
            return;
        }

        var form = document.querySelector('.wsb-booking-client-form');
        if (form) {
            initBookingBuilder(form);
        }
    });
})();
