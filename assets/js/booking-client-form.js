(function () {
    if (typeof window === 'undefined') {
        return;
    }

    function isDebugMode() {
        return window.location.search.indexOf('debug=1') !== -1;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.wsb-booking-client-form');
        if (!form) {
            return;
        }

        var returnSection = document.querySelector('.wsb-booking-client-return');
        var additionalStopToggle = document.querySelector('.wsb-booking-client-additional-toggle');
        var additionalStopInput = document.querySelector('input[name="additional_stop"]');
        var tripTypeRadios = form.querySelectorAll('input[name="trip_type"]');

        function updateReturnVisibility() {
            var isReturn = Array.prototype.slice.call(tripTypeRadios).some(function (radio) {
                return radio.checked && radio.value === 'return';
            });

            if (!returnSection) {
                return;
            }

            if (isReturn) {
                returnSection.classList.remove('wsb-booking-client-hidden');
            } else {
                returnSection.classList.add('wsb-booking-client-hidden');
            }
        }

        function updateAdditionalStop() {
            if (!additionalStopToggle || !additionalStopInput) {
                return;
            }

            if (additionalStopToggle.checked) {
                additionalStopInput.disabled = false;
                additionalStopInput.classList.remove('wsb-booking-client-hidden');
            } else {
                additionalStopInput.disabled = true;
                additionalStopInput.classList.add('wsb-booking-client-hidden');
            }
        }

        tripTypeRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                updateReturnVisibility();
            });
        });

        if (additionalStopToggle) {
            additionalStopToggle.addEventListener('change', function () {
                updateAdditionalStop();
            });
        }

        updateReturnVisibility();
        updateAdditionalStop();

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var message = 'Booking submission is not enabled yet. This is a Phase 2 layout preview.';
            var messageEl = document.querySelector('.wsb-booking-client-submit-message');

            if (!messageEl) {
                messageEl = document.createElement('div');
                messageEl.className = 'wsb-booking-client-submit-message';
                form.insertBefore(messageEl, form.querySelector('.wsb-booking-client-submit'));
            }

            messageEl.textContent = message;
            messageEl.style.display = 'block';

            if (isDebugMode()) {
                console.debug('[WSB Booking Builder] submit intercepted:', {
                    trip_type: form.querySelector('input[name="trip_type"]:checked')?.value,
                    additional_stop_enabled: additionalStopToggle?.checked,
                });
            }
        });
    });
})();
