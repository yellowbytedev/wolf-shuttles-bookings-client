(function () {
    if (typeof window === 'undefined') {
        return;
    }

    function isDebugMode() {
        return window.location.search.indexOf('debug=1') !== -1;
    }

    function getInputValue(form, selector, fallback) {
        var input = form.querySelector(selector);
        return input ? input.value.trim() : fallback;
    }

    function getNumberValue(form, selector, fallback) {
        var value = parseInt(getInputValue(form, selector, ''), 10);
        return Number.isFinite(value) ? value : fallback;
    }

    function getCheckedValue(form, selector) {
        var input = form.querySelector(selector);
        return Boolean(input && input.checked);
    }

    function buildPayload(form) {
        var tripType = getInputValue(form, 'input[name="trip_type"]:checked', 'one_way');
        var stops = [];

        if (getCheckedValue(form, 'input[name="additional_stop_enabled"]')) {
            var additionalStop = getInputValue(form, 'input[name="additional_stop"]', '');
            if (additionalStop) {
                stops.push(additionalStop);
            }
        }

        var payload = {
            schema_version: '2.0',
            source: 'marketing_booking_builder',
            service_type: 'city_transfer',
            trip_type: tripType,
            passengers: getNumberValue(form, 'input[name="passengers"]', 1),
            baby_seats: getNumberValue(form, 'input[name="baby_seats"]', 0),
            check_in_bags: getNumberValue(form, 'input[name="check_in_bags"]', 0),
            carry_on_bags: getNumberValue(form, 'input[name="carry_on_bags"]', 0),
            add_ons: {
                trailer: getCheckedValue(form, 'input[name="trailer"]'),
                oversize_luggage: getCheckedValue(form, 'input[name="oversize_luggage"]')
            },
            legs: [
                {
                    type: 'outbound',
                    from: getInputValue(form, 'input[name="outbound_from"]', ''),
                    to: getInputValue(form, 'input[name="outbound_to"]', ''),
                    pickup_date: getInputValue(form, 'input[name="outbound_pickup_date"]', ''),
                    pickup_time: getInputValue(form, 'input[name="outbound_pickup_time"]', ''),
                    stops: stops
                }
            ]
        };

        if (tripType === 'return') {
            payload.legs.push({
                type: 'return',
                from: getInputValue(form, 'input[name="return_from"]', ''),
                to: getInputValue(form, 'input[name="return_to"]', ''),
                pickup_date: getInputValue(form, 'input[name="return_pickup_date"]', ''),
                pickup_time: getInputValue(form, 'input[name="return_pickup_time"]', ''),
                stops: []
            });
        }

        return payload;
    }

    function updateReturnVisibility(returnSection, tripTypeRadios) {
        if (!returnSection) {
            return;
        }

        var isReturn = Array.prototype.slice.call(tripTypeRadios).some(function (radio) {
            return radio.checked && radio.value === 'return';
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

        if (additionalStopToggle.checked) {
            additionalStopField.classList.remove('wsb-booking-client-hidden');
            var additionalInput = additionalStopField.querySelector('input');
            if (additionalInput) {
                additionalInput.removeAttribute('disabled');
            }
        } else {
            additionalStopField.classList.add('wsb-booking-client-hidden');
            var additionalInput = additionalStopField.querySelector('input');
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

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.wsb-booking-client-form');
        if (!form) {
            return;
        }

        var returnSection = form.querySelector('.wsb-booking-client-return');
        var additionalStopToggle = form.querySelector('.wsb-booking-client-additional-toggle');
        var additionalStopField = form.querySelector('.wsb-booking-client-additional-stop-field');
        var tripTypeRadios = form.querySelectorAll('input[name="trip_type"]');
        var previewElement = form.querySelector('.wsb-booking-client-preview-json');
        var messageElement = form.querySelector('.wsb-booking-client-submit-message');

        tripTypeRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                updateReturnVisibility(returnSection, tripTypeRadios);
            });
        });

        if (additionalStopToggle) {
            additionalStopToggle.addEventListener('change', function () {
                updateAdditionalStop(additionalStopToggle, additionalStopField);
            });
        }

        updateReturnVisibility(returnSection, tripTypeRadios);
        updateAdditionalStop(additionalStopToggle, additionalStopField);

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var payload = buildPayload(form);
            renderPayloadPreview(previewElement, payload);

            if (messageElement) {
                messageElement.textContent = 'Real booking submission is disabled. This is a local BookingPayload v2 preview only.';
            }

            if (isDebugMode()) {
                console.debug('[WSB Booking Builder] Payload preview:', payload);
            }
        });
    });
})();
