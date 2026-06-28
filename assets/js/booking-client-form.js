(function () {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    var DEBUG = window.location.search.indexOf('debug=1') !== -1;
    var CONFIG = window.WSB_BOOKING_CLIENT_FORM || {};
    var PREVIEW_URL = typeof CONFIG.previewUrl === 'string' ? CONFIG.previewUrl : '';
    var PREVIEW_NONCE = typeof CONFIG.nonce === 'string' ? CONFIG.nonce : '';
    var STRINGS = CONFIG.strings || {
        serverValidationPending: 'Validating payload on server...',
        serverValidationSuccess: 'Server validation passed.',
        serverValidationWarnings: 'Server validation passed with warnings.',
        serverValidationFailed: 'Server validation failed.',
        serverPreviewUnavailable: 'Server-side preview endpoint is unavailable.',
        serverPreviewError: 'Server preview could not be completed.',
    };

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

    function getInput(form, name) {
        return form.querySelector('[name="' + name + '"]');
    }

    function setInputValue(form, name, value) {
        var input = getInput(form, name);
        if (!input) {
            return;
        }

        input.value = value;
    }

    function setCheckboxValue(form, name, value) {
        var input = getInput(form, name);
        if (!input) {
            return;
        }

        input.checked = Boolean(value);
    }

    function setRadioValue(form, name, value) {
        var inputs = form.querySelectorAll('input[name="' + name + '"]');
        forEachNode(inputs, function (input) {
            input.checked = input.value === value;
        });
    }

    function setFieldGroupDisabled(form, selector, disabled) {
        var container = form.querySelector(selector);
        if (!container) {
            return;
        }

        var input = container.querySelector('input');
        if (input) {
            input.disabled = disabled;
        }
    }

    function parseFixtures(rawFixtures) {
        if (!rawFixtures) {
            return [];
        }

        if (Array.isArray(rawFixtures)) {
            return rawFixtures;
        }

        if (typeof rawFixtures === 'string') {
            try {
                var parsed = JSON.parse(rawFixtures);
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                logDebug('Could not parse fixture data', error);
            }
        }

        return [];
    }

    function readFixtureCollection(root) {
        return parseFixtures(root && root.dataset ? root.dataset.wsbFixtures : []);
    }

    function findFixtureById(fixtures, fixtureId) {
        var id = trimValue(fixtureId);
        for (var i = 0; i < fixtures.length; i += 1) {
            if (trimValue(fixtures[i].id) === id) {
                return fixtures[i];
            }
        }
        return null;
    }

    function inferServiceGroup(serviceType) {
        var type = trimValue(serviceType);
        if (type === 'charter_hire' || type === 'charter') {
            return 'charter';
        }
        return 'transfer';
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

        var additionalStopEnabled = getBooleanValue(form, 'input[name="' + prefix + 'additional_stop_enabled"]');
        var additionalStop = trimValue(getFieldValue(form, 'input[name="' + prefix + 'additional_stop"]', ''));
        if (additionalStopEnabled && additionalStop) {
            leg.stops.push({
                type: 'additional_stop',
                location: textLocation(additionalStop)
            });
        }

        return leg;
    }

    function buildCharterLeg(form, state) {
        var leg = {
            type: 'charter',
            from: textLocation(getFieldValue(form, 'input[name="charter_pickup_location"]', '')),
            to: textLocation(getFieldValue(form, 'input[name="charter_dropoff_location"]', '')),
            pickup_date: getFieldValue(form, 'input[name="outbound_pickup_date"]', ''),
            pickup_time: getFieldValue(form, 'input[name="charter_pickup_time"]', ''),
            dropoff_time: getFieldValue(form, 'input[name="charter_dropoff_time"]', ''),
            stops: [],
            route: {}
        };

        var additionalStopEnabled = getBooleanValue(form, 'input[name="charter_additional_stop_enabled"]');
        var additionalStop = trimValue(getFieldValue(form, 'input[name="charter_additional_stop"]', ''));
        if (additionalStopEnabled && additionalStop) {
            leg.stops.push({
                type: 'additional_stop',
                location: textLocation(additionalStop)
            });
        }

        return leg;
    }

    function buildPayload(form, state) {
        var currentState = state || {};
        var tripType = getFieldValue(form, 'input[name="trip_type"]:checked', 'one_way');
        var serviceType = trimValue(currentState.serviceType || (form.closest('[data-wsb-booking-builder]') ? form.closest('[data-wsb-booking-builder]').dataset.wsbServiceType : '')) || 'city_transfer';
        var serviceGroup = trimValue(currentState.serviceGroup || (form.closest('[data-wsb-booking-builder]') ? form.closest('[data-wsb-booking-builder]').dataset.wsbServiceGroup : '')) || inferServiceGroup(serviceType);
        var passengers = getNumberValue(form, 'input[name="passengers"]', 1);
        var legs = [];

        if (passengers < 1) {
            passengers = 1;
        }

        if (serviceGroup === 'charter') {
            legs.push(buildCharterLeg(form, state));
        } else {
            legs.push(buildLeg(form, 'outbound'));
            if (tripType === 'return') {
                legs.push(buildLeg(form, 'return'));
            }
        }

        var charterBlock = serviceGroup === 'charter' ? {
            enabled: true,
            type: 'same_day',
            days: [
                {
                    day_index: 0,
                    date: legs[0].pickup_date,
                    start_time: legs[0].pickup_time,
                    end_time: legs[0].dropoff_time,
                    pickup_location: legs[0].from,
                    dropoff_location: legs[0].to,
                    stops: legs[0].stops
                }
            ]
        } : {
            enabled: false,
            type: null,
            days: []
        };

        return {
            schema_version: '2.0',
            source: 'marketing_booking_builder',
            service_group: serviceGroup,
            service_type: serviceType,
            trip_type: tripType,
            customer: {
                name: '',
                email: '',
                phone: ''
            },
            passengers: passengers,
            baby_seats: getNumberValue(form, 'input[name="baby_seats"]', 0),
            check_in_bags: getNumberValue(form, 'input[name="check_in_bags"]', 0),
            carry_on_bags: getNumberValue(form, 'input[name="carry_on_bags"]', 0),
            add_ons: {
                trailer: getBooleanValue(form, 'input[name="trailer"]'),
                oversize_luggage: getBooleanValue(form, 'input[name="oversize_luggage"]')
            },
            legs: legs,
            charter: charterBlock,
            blockouts: {
                version: 2,
                authority: 'booking_site',
                marketing_evaluates_vehicle_availability: false,
                vehicle_scoped_blockouts_supported: true,
                global_picker_blockouts_supported: true,
                config_hash: null,
                marketing_evaluated_at: null,
                notes: []
            },
            tracking: {},
            validation_flags: {},
            meta: {
                preview_only: true,
                handover_mode: 'preview',
                created_at: new Date().toISOString()
            }
        };
    }

    function renderPreviewSummary(statusElement, payload, state) {
        if (!statusElement) {
            return;
        }

        var legCount = payload.legs ? payload.legs.length : 0;
        var outboundStops = payload.legs && payload.legs[0] && payload.legs[0].stops && payload.legs[0].stops.length > 0;
        var returnStops = payload.legs && payload.legs[1] && payload.legs[1].stops && payload.legs[1].stops.length > 0;
        var stopLabel = outboundStops ? 'outbound stop: enabled' : (returnStops ? 'return stop: enabled' : 'stops: disabled');
        var summary = [
            'Live payload preview active',
            'service: ' + payload.service_type,
            'trip: ' + payload.trip_type,
            legCount + ' leg' + (legCount === 1 ? '' : 's'),
            stopLabel,
            'updated: ' + new Date().toLocaleTimeString()
        ];

        if (state && state.fixtureId) {
            summary.splice(2, 0, 'fixture: ' + state.fixtureId);
        }

        statusElement.textContent = summary.join(' · ');
    }

    function renderValidationOutput(outputElement, validation) {
        if (!outputElement) {
            return;
        }

        var status = validation.valid ? 'success' : 'error';
        if (validation.valid && validation.warnings && validation.warnings.length) {
            status = 'warning';
        }

        var summaryText = validation.valid ? (validation.warnings && validation.warnings.length ? STRINGS.serverValidationWarnings : STRINGS.serverValidationSuccess) : STRINGS.serverValidationFailed;
        var summaryClass = 'wsb-booking-client-validation-summary--' + status;

        var html = '<div class="wsb-booking-client-validation-summary ' + summaryClass + '">' + summaryText + '</div>';

        if (validation.errors && validation.errors.length) {
            html += '<ul class="wsb-booking-client-validation-list">';
            validation.errors.forEach(function (error) {
                html += '<li><strong>' + escapeHtml(error.field) + '</strong>: ' + escapeHtml(error.message) + '</li>';
            });
            html += '</ul>';
        }

        if (validation.warnings && validation.warnings.length) {
            html += '<ul class="wsb-booking-client-validation-list">';
            validation.warnings.forEach(function (warning) {
                html += '<li><strong>' + escapeHtml(warning.field) + '</strong>: ' + escapeHtml(warning.message) + '</li>';
            });
            html += '</ul>';
        }

        outputElement.innerHTML = html;
    }

    function escapeHtml(text) {
        var str = String(text || '');
        return str.replace(/[&"'<>]/g, function (match) {
            var replacements = {
                '&': '&amp;',
                '"': '&quot;',
                "'": '&#39;',
                '<': '&lt;',
                '>': '&gt;'
            };
            return replacements[match] || match;
        });
    }

    function renderValidationError(outputElement, message) {
        if (!outputElement) {
            return;
        }

        outputElement.innerHTML = '<div class="wsb-booking-client-validation-summary wsb-booking-client-validation-summary--error">' + escapeHtml(message) + '</div>';
    }

    function requestJsonPreview(endpointUrl, payload) {
        if (!endpointUrl) {
            return Promise.resolve(null);
        }

        var headers = {
            'Content-Type': 'application/json'
        };

        if (PREVIEW_NONCE) {
            headers['X-WP-Nonce'] = PREVIEW_NONCE;
        }

        return fetch(endpointUrl, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(payload)
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            return response.json();
        });
    }

    function renderPayload(previewElement, statusElement, messageElement, payload, message, state) {
        if (previewElement) {
            previewElement.textContent = JSON.stringify(payload, null, 2);
        }

        renderPreviewSummary(statusElement, payload, state);

        if (messageElement) {
            messageElement.textContent = message || '';
        }

        logDebug('Booking Builder preview updated', payload);
    }

    function postPayloadPreview(payload, validationElement, messageElement) {
        if (!validationElement) {
            return Promise.resolve(null);
        }

        if (!PREVIEW_URL) {
            renderValidationError(validationElement, STRINGS.serverPreviewUnavailable);
            if (messageElement) {
                messageElement.textContent = STRINGS.serverPreviewUnavailable;
            }
            return Promise.resolve(null);
        }

        if (messageElement) {
            messageElement.textContent = STRINGS.serverValidationPending;
        }

        return requestJsonPreview(PREVIEW_URL, payload)
            .then(function (data) {
                if (!data || !data.validation) {
                    throw new Error('Invalid preview response');
                }

                renderValidationOutput(validationElement, data.validation);
                if (messageElement) {
                    messageElement.textContent = data.validation.valid ? (data.validation.warnings && data.validation.warnings.length ? STRINGS.serverValidationWarnings : STRINGS.serverValidationSuccess) : STRINGS.serverValidationFailed;
                }
                logDebug('Server preview response', data);
                return data;
            })
            .catch(function (error) {
                renderValidationError(validationElement, STRINGS.serverPreviewError);
                if (messageElement) {
                    messageElement.textContent = STRINGS.serverPreviewError;
                }
                logDebug('Server preview failed', error);
                return null;
            });
    }

    function postHandoverPreview(payload, messageElement) {
        var handoverUrl = typeof CONFIG.handoverPreviewUrl === 'string' ? CONFIG.handoverPreviewUrl : '';
        if (!handoverUrl) {
            if (messageElement && CONFIG.strings && CONFIG.strings.fixtureDrawerHandoverUnavailable) {
                messageElement.textContent = CONFIG.strings.fixtureDrawerHandoverUnavailable;
            }
            return Promise.resolve(null);
        }

        return requestJsonPreview(handoverUrl, payload)
            .then(function (data) {
                logDebug('Handover preview response', data);
                return data;
            })
            .catch(function (error) {
                logDebug('Handover preview failed', error);
                return null;
            });
    }

    function updateFixtureDrawerStatus(statusElement, message, variant) {
        if (!statusElement) {
            return;
        }

        statusElement.className = 'wsb-booking-client-fixture-status';
        if (variant) {
            statusElement.classList.add('wsb-booking-client-fixture-status--' + variant);
        }
        statusElement.textContent = message;
    }

    function openFixtureDrawer(drawer, toggle, statusElement) {
        if (!drawer) {
            return;
        }

        drawer.classList.remove('wsb-booking-client-hidden');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
        updateFixtureDrawerStatus(statusElement, (CONFIG.strings && CONFIG.strings.fixtureDrawerOpen) ? CONFIG.strings.fixtureDrawerOpen : 'Fixture drawer opened.', 'info');
    }

    function closeFixtureDrawer(drawer, toggle, statusElement) {
        if (!drawer) {
            return;
        }

        drawer.classList.add('wsb-booking-client-hidden');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
        updateFixtureDrawerStatus(statusElement, (CONFIG.strings && CONFIG.strings.fixtureDrawerClosed) ? CONFIG.strings.fixtureDrawerClosed : 'Fixture drawer closed.', 'muted');
    }

    function updateServiceMode(root, serviceGroup) {
        var transferFields = root.querySelector('[data-wsb-transfer-fields]');
        var charterSection = root.querySelector('[data-wsb-charter-section]');
        var outboundSection = root.querySelector('[data-wsb-outbound-section]');
        var returnSection = root.querySelector('[data-wsb-return-section]');
        var serviceTabs = root.querySelectorAll('[data-wsb-service-tab]');

        forEachNode(serviceTabs, function (tab) {
            tab.classList.remove('wsb-booking-client-service-tab--active');
        });

        var activeTab = root.querySelector('[data-wsb-service-tab="' + serviceGroup + '"]');
        if (activeTab) {
            activeTab.classList.add('wsb-booking-client-service-tab--active');
        }

        if (serviceGroup === 'charter') {
            if (transferFields) {
                transferFields.classList.add('wsb-booking-client-hidden');
            }
            if (charterSection) {
                charterSection.classList.remove('wsb-booking-client-hidden');
            }
            if (outboundSection) {
                outboundSection.classList.add('wsb-booking-client-hidden');
            }
            if (returnSection) {
                returnSection.classList.add('wsb-booking-client-hidden');
            }
        } else {
            if (transferFields) {
                transferFields.classList.remove('wsb-booking-client-hidden');
            }
            if (charterSection) {
                charterSection.classList.add('wsb-booking-client-hidden');
            }
            if (outboundSection) {
                outboundSection.classList.remove('wsb-booking-client-hidden');
            }
        }
    }

    function applyFixtureToForm(form, root, fixture, state) {
        var payload = fixture && fixture.payload ? fixture.payload : {};
        var currentState = state || {};
        var outboundLeg = payload.legs && payload.legs.length ? payload.legs[0] : {};
        var returnLeg = null;

        if (payload.trip_type === 'return' && payload.legs && payload.legs.length > 1) {
            returnLeg = payload.legs[1];
        }

        currentState.fixtureId = trimValue(fixture.id || '');
        currentState.fixtureExpected = payload ? (fixture.expected_ok ? 'valid' : 'invalid') : '';
        currentState.serviceType = trimValue(payload.service_type || currentState.serviceType || 'city_transfer');
        currentState.serviceGroup = trimValue(payload.service_group || inferServiceGroup(currentState.serviceType));

        if (root) {
            root.dataset.wsbServiceType = currentState.serviceType;
            root.dataset.wsbServiceGroup = currentState.serviceGroup;
        }

        updateServiceMode(root, currentState.serviceGroup);

        setRadioValue(form, 'trip_type', payload.trip_type || 'one_way');
        setInputValue(form, 'passengers', payload.passengers != null ? payload.passengers : 1);
        setInputValue(form, 'baby_seats', payload.baby_seats != null ? payload.baby_seats : 0);
        setInputValue(form, 'check_in_bags', payload.check_in_bags != null ? payload.check_in_bags : 0);
        setInputValue(form, 'carry_on_bags', payload.carry_on_bags != null ? payload.carry_on_bags : 0);
        setCheckboxValue(form, 'trailer', Boolean(payload.add_ons && payload.add_ons.trailer));
        setCheckboxValue(form, 'oversize_luggage', Boolean(payload.add_ons && payload.add_ons.oversize_luggage));

        if (currentState.serviceGroup === 'charter') {
            setInputValue(form, 'charter_pickup_location', outboundLeg.from && outboundLeg.from.label ? outboundLeg.from.label : '');
            setInputValue(form, 'charter_dropoff_location', outboundLeg.to && outboundLeg.to.label ? outboundLeg.to.label : '');
            setInputValue(form, 'outbound_pickup_date', outboundLeg.pickup_date || payload.charter && payload.charter.days && payload.charter.days[0] && payload.charter.days[0].date ? payload.charter.days[0].date : '');
            setInputValue(form, 'charter_pickup_time', outboundLeg.pickup_time || payload.charter && payload.charter.days && payload.charter.days[0] && payload.charter.days[0].start_time ? payload.charter.days[0].start_time : '');
            setInputValue(form, 'charter_dropoff_time', outboundLeg.dropoff_time || payload.charter && payload.charter.days && payload.charter.days[0] && payload.charter.days[0].end_time ? payload.charter.days[0].end_time : '');

            var charterStop = outboundLeg.stops && outboundLeg.stops.length ? outboundLeg.stops[0] : (payload.charter && payload.charter.days && payload.charter.days[0] && payload.charter.days[0].stops && payload.charter.days[0].stops.length ? payload.charter.days[0].stops[0] : null);
            var charterStopEnabled = Boolean(charterStop && charterStop.location && charterStop.location.label);
            setCheckboxValue(form, 'charter_additional_stop_enabled', charterStopEnabled);
            setInputValue(form, 'charter_additional_stop', charterStopEnabled ? charterStop.location.label : '');
            setFieldGroupDisabled(form, '[data-wsb-charter-additional-stop-section]', !charterStopEnabled);
            updateAdditionalStop(form.querySelector('[data-wsb-charter-additional-stop-toggle]'), root.querySelector('[data-wsb-charter-additional-stop-section]'));
        } else {
            setInputValue(form, 'outbound_from', outboundLeg.from && outboundLeg.from.label ? outboundLeg.from.label : '');
            setInputValue(form, 'outbound_to', outboundLeg.to && outboundLeg.to.label ? outboundLeg.to.label : '');
            setInputValue(form, 'outbound_pickup_date', outboundLeg.pickup_date || '');
            setInputValue(form, 'outbound_pickup_time', outboundLeg.pickup_time || '');

            var outboundStop = outboundLeg.stops && outboundLeg.stops.length ? outboundLeg.stops[0] : null;
            var outboundStopEnabled = Boolean(outboundStop && outboundStop.location && outboundStop.location.label);
            setCheckboxValue(form, 'outbound_additional_stop_enabled', outboundStopEnabled);
            setInputValue(form, 'outbound_additional_stop', outboundStopEnabled ? outboundStop.location.label : '');
            setFieldGroupDisabled(form, '[data-wsb-outbound-additional-stop-section]', !outboundStopEnabled);

            if (returnLeg) {
                setInputValue(form, 'return_from', returnLeg.from && returnLeg.from.label ? returnLeg.from.label : '');
                setInputValue(form, 'return_to', returnLeg.to && returnLeg.to.label ? returnLeg.to.label : '');
                setInputValue(form, 'return_pickup_date', returnLeg.pickup_date || '');
                setInputValue(form, 'return_pickup_time', returnLeg.pickup_time || '');

                var returnStop = returnLeg.stops && returnLeg.stops.length ? returnLeg.stops[0] : null;
                var returnStopEnabled = Boolean(returnStop && returnStop.location && returnStop.location.label);
                setCheckboxValue(form, 'return_additional_stop_enabled', returnStopEnabled);
                setInputValue(form, 'return_additional_stop', returnStopEnabled ? returnStop.location.label : '');
                setFieldGroupDisabled(form, '[data-wsb-return-additional-stop-section]', !returnStopEnabled);
            } else {
                setInputValue(form, 'return_from', '');
                setInputValue(form, 'return_to', '');
                setInputValue(form, 'return_pickup_date', '');
                setInputValue(form, 'return_pickup_time', '');
                setCheckboxValue(form, 'return_additional_stop_enabled', false);
                setInputValue(form, 'return_additional_stop', '');
                setFieldGroupDisabled(form, '[data-wsb-return-additional-stop-section]', true);
            }
        }

        return null;
    }

    function runFixturePreviewChecks(payload, fixture, validationElement, messageElement, statusElement, state) {
        var expectedOk = Boolean(fixture && fixture.expected_ok);
        var fixtureId = trimValue(fixture && fixture.id ? fixture.id : 'fixture');
        var fixtureLabel = fixtureId + ' — Expected: ' + (expectedOk ? 'valid' : 'invalid');
        var strings = CONFIG.strings || {};

        updateFixtureDrawerStatus(statusElement, (strings.fixtureDrawerLoaded || 'Loaded fixture:') + ' ' + fixtureLabel, 'info');

        return postPayloadPreview(payload, validationElement, messageElement).then(function (serverData) {
            var serverOk = Boolean(serverData && serverData.validation && serverData.validation.valid);
            var serverMatched = serverOk === expectedOk;
            var serverMessage = strings.fixtureDrawerServerMatched || 'Server validation matched expected result.';
            if (!serverMatched) {
                serverMessage = strings.fixtureDrawerServerMismatch || 'Server validation did not match expected result.';
            }

            var followUp = [fixtureLabel, serverMessage];
            updateFixtureDrawerStatus(statusElement, followUp.join(' · '), serverMatched ? 'success' : 'warning');

            return postHandoverPreview(payload, messageElement).then(function (handoverData) {
                var handoverOk = Boolean(handoverData && handoverData.ok);
                var handoverMatched = handoverOk === expectedOk;
                var handoverMessage = strings.fixtureDrawerHandoverMatched || 'Handover preview matched expected result.';
                if (!handoverMatched) {
                    handoverMessage = strings.fixtureDrawerHandoverMismatch || 'Handover preview did not match expected result.';
                }

                updateFixtureDrawerStatus(
                    statusElement,
                    [fixtureLabel, serverMessage, handoverMessage].join(' · '),
                    serverMatched && handoverMatched ? 'success' : 'warning'
                );

                return {
                    server: serverData,
                    handover: handoverData
                };
            });
        });
    }

    function updateReturnVisibility(returnSection, tripTypeInputs) {
        if (!returnSection || !tripTypeInputs) {
            return;
        }

        var hasReturn = false;
        Array.prototype.forEach.call(tripTypeInputs, function (input) {
            if (input.checked && input.value === 'return') {
                hasReturn = true;
            }
        });

        if (hasReturn) {
            returnSection.classList.remove('wsb-booking-client-hidden');
        } else {
            returnSection.classList.add('wsb-booking-client-hidden');
        }
    }

    function updateAdditionalStop(toggle, section) {
        if (!section || !toggle) {
            return;
        }

        if (toggle.checked) {
            section.classList.remove('wsb-booking-client-hidden');
            var input = section.querySelector('input');
            if (input) {
                input.disabled = false;
            }
        } else {
            section.classList.add('wsb-booking-client-hidden');
            var input = section.querySelector('input');
            if (input) {
                input.disabled = true;
            }
        }
    }

    function initBookingBuilder(root) {
        var form = root.querySelector('[data-wsb-booking-form]');
        var returnSection = root.querySelector('[data-wsb-return-section]');
        var outboundAdditionalStopToggle = root.querySelector('[data-wsb-outbound-additional-stop-toggle]');
        var outboundAdditionalStopField = root.querySelector('[data-wsb-outbound-additional-stop-section]');
        var returnAdditionalStopToggle = root.querySelector('[data-wsb-return-additional-stop-toggle]');
        var returnAdditionalStopField = root.querySelector('[data-wsb-return-additional-stop-section]');
        var charterAdditionalStopToggle = root.querySelector('[data-wsb-charter-additional-stop-toggle]');
        var charterAdditionalStopField = root.querySelector('[data-wsb-charter-additional-stop-section]');
        var previewElement = root.querySelector('[data-wsb-payload-preview]');
        var validationElement = root.querySelector('[data-wsb-validation-output]');
        var statusElement = root.querySelector('[data-wsb-preview-status]');
        var messageElement = root.querySelector('[data-wsb-submit-message]');
        var fixtureToggle = root.querySelector('[data-wsb-fixture-toggle]');
        var fixtureDrawer = root.querySelector('[data-wsb-fixture-drawer]');
        var fixtureClose = root.querySelector('[data-wsb-fixture-close]');
        var fixtureList = root.querySelector('[data-wsb-fixture-list]');
        var fixtureStatus = root.querySelector('[data-wsb-fixture-status]');
        var fixtures = readFixtureCollection(root);
        var state = {
            serviceGroup: trimValue(root.dataset.wsbServiceGroup || 'transfer') || 'transfer',
            serviceType: trimValue(root.dataset.wsbServiceType || 'city_transfer') || 'city_transfer',
            fixtureId: '',
            fixtureExpected: ''
        };

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
            var payload = buildPayload(form, state);
            renderPayload(previewElement, statusElement, messageElement, payload, message, state);
            return payload;
        }

        function refreshServerPreview(message) {
            var payload = buildPayload(form, state);
            postPayloadPreview(payload, validationElement, messageElement);
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

        var serviceTabs = root.querySelectorAll('[data-wsb-service-tab]');
        forEachNode(serviceTabs, function (tab) {
            tab.addEventListener('click', function () {
                var serviceGroup = trimValue(tab.getAttribute('data-service'));
                if (serviceGroup === 'charter' || serviceGroup === 'transfer') {
                    state.serviceGroup = serviceGroup;
                    root.dataset.wsbServiceGroup = serviceGroup;
                    updateServiceMode(root, serviceGroup);
                    refreshPreview('');
                }
            });
        });

        if (outboundAdditionalStopToggle) {
            outboundAdditionalStopToggle.addEventListener('change', function () {
                updateAdditionalStop(outboundAdditionalStopToggle, outboundAdditionalStopField);
                refreshPreview('');
            });
        }

        if (returnAdditionalStopToggle) {
            returnAdditionalStopToggle.addEventListener('change', function () {
                updateAdditionalStop(returnAdditionalStopToggle, returnAdditionalStopField);
                refreshPreview('');
            });
        }

        if (charterAdditionalStopToggle) {
            charterAdditionalStopToggle.addEventListener('change', function () {
                updateAdditionalStop(charterAdditionalStopToggle, charterAdditionalStopField);
                refreshPreview('');
            });
        }

        if (fixtureToggle && fixtureDrawer && fixtureList && fixtures.length) {
            fixtureToggle.addEventListener('click', function () {
                var isOpen = !fixtureDrawer.classList.contains('wsb-booking-client-hidden');
                if (isOpen) {
                    closeFixtureDrawer(fixtureDrawer, fixtureToggle, fixtureStatus);
                } else {
                    openFixtureDrawer(fixtureDrawer, fixtureToggle, fixtureStatus);
                }
            });
        }

        if (fixtureClose && fixtureDrawer && fixtureToggle) {
            fixtureClose.addEventListener('click', function () {
                closeFixtureDrawer(fixtureDrawer, fixtureToggle, fixtureStatus);
            });
        }

        if (fixtureList && fixtures.length) {
            forEachNode(fixtureList.querySelectorAll('[data-wsb-fixture-chip]'), function (chip) {
                chip.addEventListener('click', function () {
                    var fixture = findFixtureById(fixtures, chip.getAttribute('data-wsb-fixture-id'));
                    if (!fixture) {
                        updateFixtureDrawerStatus(fixtureStatus, 'Fixture not found: ' + trimValue(chip.getAttribute('data-wsb-fixture-id')), 'error');
                        return;
                    }

                    applyFixtureToForm(form, root, fixture, state);
                    var payload = refreshPreview((CONFIG.strings && CONFIG.strings.fixtureDrawerLoaded ? CONFIG.strings.fixtureDrawerLoaded : 'Loaded fixture:') + ' ' + fixture.id + ' — Expected: ' + (fixture.expected_ok ? 'valid' : 'invalid'));
                    runFixturePreviewChecks(payload, fixture, validationElement, messageElement, fixtureStatus, state);
                });
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
            refreshServerPreview();
        });

        updateServiceMode(root, state.serviceGroup);
        updateReturnVisibility(returnSection, tripTypeInputs);
        updateAdditionalStop(outboundAdditionalStopToggle, outboundAdditionalStopField);
        updateAdditionalStop(returnAdditionalStopToggle, returnAdditionalStopField);
        updateAdditionalStop(charterAdditionalStopToggle, charterAdditionalStopField);
        refreshPreview('Live payload preview initialised');
        if (fixtureStatus && fixtures.length) {
            updateFixtureDrawerStatus(
                fixtureStatus,
                (CONFIG.strings && CONFIG.strings.fixtureDrawerDefault) ? CONFIG.strings.fixtureDrawerDefault : 'Choose a fixture to load sample payload data.',
                'muted'
            );
        }
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