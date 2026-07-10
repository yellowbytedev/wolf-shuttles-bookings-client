(function () {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    var DEBUG = window.location.search.indexOf('debug=1') !== -1;
    var CONFIG = window.WSB_BOOKING_CLIENT_FORM || {};
    var PREVIEW_URL = typeof CONFIG.previewUrl === 'string' ? CONFIG.previewUrl : '';
    var PREVIEW_NONCE = typeof CONFIG.nonce === 'string' ? CONFIG.nonce : '';
    var STRINGS = CONFIG.strings || {
        serverValidationPending: 'Checking your details...',
        serverValidationSuccess: 'Your details look good.',
        serverValidationWarnings: 'Your details look good, with a few notes.',
        serverValidationFailed: 'We could not verify your details.',
        serverPreviewUnavailable: 'The review panel is unavailable.',
        serverPreviewError: 'We could not complete the review.',
    };
    var BOOKING_SITE_CONFIG = CONFIG.bookingSiteConfig || {};
var GOOGLE_PLACES = (CONFIG.googlePlaces || {
    enabled: false,
    available: false,
    requiredForQuoteReady: false
});

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

    function isElementVisible(node) {
        if (!node || !node.ownerDocument) {
            return false;
        }

        var style = window.getComputedStyle(node);
        if (!style || style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity || '1') === 0) {
            return false;
        }

        var rect = node.getBoundingClientRect ? node.getBoundingClientRect() : null;
        return !!rect && rect.width > 0 && rect.height > 0;
    }

    function getField(form, selector) {
        if (!form) {
            return null;
        }

        var matches = form.querySelectorAll(selector);
        for (var i = 0; i < matches.length; i += 1) {
            if (!matches[i].disabled && isElementVisible(matches[i])) {
                return matches[i];
            }
        }

        return matches.length ? matches[0] : null;
    }

    function getFieldValue(form, selector, fallback) {
        if (!form) {
            return fallback;
        }

        var matches = form.querySelectorAll(selector);
        if (!matches.length) {
            return fallback;
        }

        for (var i = 0; i < matches.length; i += 1) {
            var input = matches[i];
            if (!input || input.disabled) {
                continue;
            }

            if (input.type === 'checkbox') {
                if (input.checked) {
                    return true;
                }
                continue;
            }

            if (input.type === 'radio') {
                if (selector.indexOf(':checked') !== -1) {
                    if (input.checked) {
                        return input.value;
                    }
                    continue;
                }
                if (input.checked) {
                    return input.value;
                }
                continue;
            }

            var value = trimValue(input.value);
            if (value) {
                return value;
            }
        }

        var first = matches[0];
        if (first && first.type === 'checkbox') {
            return Boolean(first.checked);
        }
        if (first && first.type === 'radio') {
            return first.checked ? first.value : fallback;
        }
        return first ? (trimValue(first.value) || fallback) : fallback;
    }

    function getNumberValue(form, selector, fallback) {
        var parsed = parseInt(getFieldValue(form, selector, ''), 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function getBooleanValue(form, selector) {
        return Boolean(getFieldValue(form, selector, false));
    }

    function getActiveSharedScope(form, serviceGroup) {
        if (!form) {
            return null;
        }
        if (serviceGroup === 'charter') {
            return form.querySelector('[data-wsb-charter-section]');
        }
        return form.querySelector('[data-wsb-transfer-fields]') || form;
    }

    function getSharedField(form, serviceGroup, name) {
        var scope = getActiveSharedScope(form, serviceGroup);
        var selector = '[name="' + name + '"]';
        return (scope && scope.querySelector(selector)) || (form ? form.querySelector(selector) : null);
    }

    function getSharedNumberValue(form, serviceGroup, name, fallback) {
        var input = getSharedField(form, serviceGroup, name);
        if (!input) {
            return fallback;
        }
        var parsed = parseInt(input.value, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function getSharedBooleanValue(form, serviceGroup, name) {
        var input = getSharedField(form, serviceGroup, name);
        return Boolean(input && input.checked);
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
        return getField(form, '[name="' + name + '"]');
    }

    function setInputValue(form, name, value) {
        if (!form) {
            return;
        }

        var inputs = form.querySelectorAll('[name="' + name + '"]');
        forEachNode(inputs, function (input) {
            if (!input) {
                return;
            }
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function setCheckboxValue(form, name, value) {
        if (!form) {
            return;
        }

        var inputs = form.querySelectorAll('[name="' + name + '"]');
        forEachNode(inputs, function (input) {
            if (!input) {
                return;
            }
            input.checked = Boolean(value);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
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

    function getCharterModeValue(form) {
        var modeInput = form.querySelector('input[name="charter_mode"]:checked');
        return modeInput ? trimValue(modeInput.value) || 'same_day' : 'same_day';
    }

    function getCharterDayCards(root) {
        return Array.prototype.slice.call(root.querySelectorAll('[data-wsb-charter-day-card]'));
    }

    function getVisibleCharterDayCards(root) {
        return getCharterDayCards(root).filter(function (card) {
            return card.getAttribute('data-wsb-charter-day-visible') === 'true' && !card.classList.contains('wsb-booking-client-hidden');
        });
    }

    function getCharterDayField(card, fieldKey) {
        if (!card) {
            return null;
        }

        var container = card.querySelector('[data-wsb-charter-day-field="' + fieldKey + '"]');
        if (!container) {
            return null;
        }

        return container.querySelector('input, textarea, select') || container;
    }

    function getCharterDaySnapshotKey(card, fieldKey) {
        var dayId = card && card.getAttribute ? trimValue(card.getAttribute('data-wsb-charter-day-id')) : '';
        return dayId ? dayId + ':' + fieldKey : fieldKey;
    }

    function getCharterDaySnapshot(card, fieldKey) {
        var key = getCharterDaySnapshotKey(card, fieldKey);
        return placeSnapshots[key] || clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
    }

    function setCharterDaySnapshot(card, fieldKey, snapshot) {
        var key = getCharterDaySnapshotKey(card, fieldKey);
        placeSnapshots[key] = snapshot ? clonePlaceSnapshot(snapshot) : clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
    }

    function buildLocationPayload(label, snapshot) {
        var snap = snapshot || clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
        return {
            label: trimValue(label),
            name: snap.label || '',
            town: '',
            neighbourhood: '',
            place_id: snap.place_id || '',
            coords: {
                lat: snap.lat != null ? snap.lat : null,
                lng: snap.lng != null ? snap.lng : null
            },
            formatted_address: snap.formatted_address || trimValue(label)
        };
    }

    function buildCharterDayPayload(card, index) {
        var dayId = card && card.getAttribute ? trimValue(card.getAttribute('data-wsb-charter-day-id')) : '';
        var pickupField = getCharterDayField(card, 'pickup_location');
        var dropoffField = getCharterDayField(card, 'dropoff_location');
        var dateField = getCharterDayField(card, 'date');
        var startTimeField = getCharterDayField(card, 'start_time');
        var endTimeField = getCharterDayField(card, 'end_time');
        var poiField = getCharterDayField(card, 'poi_intent');
        var notesField = getCharterDayField(card, 'notes');
        var pickupLabel = trimValue(pickupField && pickupField.value ? pickupField.value : '');
        var dropoffLabel = trimValue(dropoffField && dropoffField.value ? dropoffField.value : '');
        var pickupSnapshot = getCharterDaySnapshot(card, 'pickup_location');
        var dropoffSnapshot = getCharterDaySnapshot(card, 'dropoff_location');

        return {
            day_id: dayId || 'day_' + index,
            day_index: index,
            sort_order: (index + 1) * 10,
            date: trimValue(dateField && dateField.value ? dateField.value : ''),
            start_time: trimValue(startTimeField && startTimeField.value ? startTimeField.value : ''),
            end_time: trimValue(endTimeField && endTimeField.value ? endTimeField.value : ''),
            pickup_location: buildLocationPayload(pickupLabel, pickupSnapshot),
            dropoff_location: buildLocationPayload(dropoffLabel, dropoffSnapshot),
            poi_intent: trimValue(poiField && poiField.value ? poiField.value : ''),
            notes: trimValue(notesField && notesField.value ? notesField.value : ''),
            stops: [],
            place_snapshots: {
                from: pickupSnapshot,
                to: dropoffSnapshot,
                stops: []
            }
        };
    }

    function buildCharterLegFromDay(day) {
        var charterDay = day || {};
        var fromLocation = charterDay.pickup_location || {};
        var toLocation = charterDay.dropoff_location || {};
        var fromSnapshot = charterDay.place_snapshots && charterDay.place_snapshots.from ? charterDay.place_snapshots.from : clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
        var toSnapshot = charterDay.place_snapshots && charterDay.place_snapshots.to ? charterDay.place_snapshots.to : clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);

        return {
            type: 'charter',
            from: buildLocationPayload(fromLocation.label || '', fromSnapshot),
            to: buildLocationPayload(toLocation.label || '', toSnapshot),
            pickup_date: trimValue(charterDay.date || ''),
            pickup_time: trimValue(charterDay.start_time || ''),
            dropoff_time: trimValue(charterDay.end_time || ''),
            stops: [],
            route: {},
            place_snapshots: {
                from: fromSnapshot,
                to: toSnapshot,
                stops: []
            }
        };
    }

    function setCharterDayCardVisible(card, visible) {
        if (!card) {
            return;
        }

        card.setAttribute('data-wsb-charter-day-visible', visible ? 'true' : 'false');
        card.classList.toggle('wsb-booking-client-hidden', !visible);
    }

    function setCharterDayCardCollapsed(card, collapsed) {
        if (!card) {
            return;
        }

        card.setAttribute('data-wsb-charter-day-collapsed', collapsed ? 'true' : 'false');
        card.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        card.classList.toggle('wsb-booking-client-charter-day-card--collapsed', collapsed);

        forEachNode(card.querySelectorAll('[data-wsb-charter-day-toggle]'), function (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (toggle.classList.contains('wsb-icon-action--toggle')) {
                toggle.setAttribute('aria-label', collapsed ? 'Open day details' : 'Close day details');
            }
        });

        var body = card.querySelector('[data-wsb-charter-day-body]');
        if (body) {
            body.classList.toggle('wsb-booking-client-hidden', collapsed);
        }
    }

    function updateCharterDayButtons(root) {
        var cards = getCharterDayCards(root);
        var visibleCards = getVisibleCharterDayCards(root);
        var visibleCount = visibleCards.length;
        var addButtons = root.querySelectorAll('[data-wsb-charter-add-day]');
        var collapseAll = root.querySelector('[data-wsb-charter-collapse-all]');
        var expandAll = root.querySelector('[data-wsb-charter-expand-all]');

        forEachNode(addButtons, function (addButton) {
            addButton.disabled = visibleCount >= cards.length;
        });
        if (collapseAll) {
            collapseAll.disabled = visibleCount === 0;
        }
        if (expandAll) {
            expandAll.disabled = visibleCount === 0;
        }

        cards.forEach(function (card) {
            var cardVisible = card.getAttribute('data-wsb-charter-day-visible') === 'true' && !card.classList.contains('wsb-booking-client-hidden');
            var duplicateButton = card.querySelector('[data-wsb-charter-day-duplicate]');
            var deleteButton = card.querySelector('[data-wsb-charter-day-delete]');
            var toggleButton = card.querySelector('[data-wsb-charter-day-toggle]');
            var cardIndex = cards.indexOf(card);
            var hasNextHidden = false;
            for (var i = cardIndex + 1; i < cards.length; i += 1) {
                if (cards[i].getAttribute('data-wsb-charter-day-visible') !== 'true' || cards[i].classList.contains('wsb-booking-client-hidden')) {
                    hasNextHidden = true;
                    break;
                }
            }

            if (duplicateButton) {
                duplicateButton.disabled = !cardVisible || !hasNextHidden;
            }
            if (deleteButton) {
                deleteButton.disabled = !cardVisible || visibleCount <= 1;
            }
            if (toggleButton) {
                toggleButton.disabled = !cardVisible;
            }
        });
    }

    function findNextHiddenCharterDayCard(root, startIndex) {
        var cards = getCharterDayCards(root);
        for (var i = startIndex; i < cards.length; i += 1) {
            var card = cards[i];
            if (card.getAttribute('data-wsb-charter-day-visible') !== 'true' || card.classList.contains('wsb-booking-client-hidden')) {
                return card;
            }
        }
        return null;
    }

    function copyCharterDayCardValues(sourceCard, targetCard) {
        if (!sourceCard || !targetCard) {
            return;
        }

        var fields = ['date', 'start_time', 'end_time', 'pickup_location', 'dropoff_location', 'poi_intent', 'notes'];
        fields.forEach(function (fieldKey) {
            var sourceField = getCharterDayField(sourceCard, fieldKey);
            var targetField = getCharterDayField(targetCard, fieldKey);
            if (sourceField && targetField) {
                targetField.value = sourceField.value;
            }
        });

        var sourceDayId = trimValue(sourceCard.getAttribute('data-wsb-charter-day-id'));
        var targetDayId = trimValue(targetCard.getAttribute('data-wsb-charter-day-id'));
        ['pickup_location', 'dropoff_location'].forEach(function (fieldKey) {
            var sourceSnapshot = placeSnapshots[getCharterDaySnapshotKey(sourceCard, fieldKey)] || clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
            placeSnapshots[getCharterDaySnapshotKey(targetCard, fieldKey)] = clonePlaceSnapshot(sourceSnapshot);
        });

        if (sourceDayId && targetDayId) {
            targetCard.setAttribute('data-wsb-charter-day-source', sourceDayId);
        }
    }

    function clearCharterDayCardValues(card) {
        if (!card) {
            return;
        }

        ['date', 'start_time', 'end_time', 'pickup_location', 'dropoff_location', 'poi_intent', 'notes'].forEach(function (fieldKey) {
            var field = getCharterDayField(card, fieldKey);
            if (field) {
                field.value = '';
            }
        });

        ['pickup_location', 'dropoff_location'].forEach(function (fieldKey) {
            placeSnapshots[getCharterDaySnapshotKey(card, fieldKey)] = clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
        });
    }

    function collectMultiDayCharterDays(root) {
        var cards = getCharterDayCards(root);
        var days = [];

        cards.forEach(function (card) {
            var isVisible = card.getAttribute('data-wsb-charter-day-visible') === 'true' && !card.classList.contains('wsb-booking-client-hidden');
            if (!isVisible) {
                return;
            }

            days.push(buildCharterDayPayload(card, days.length));
        });

        return days;
    }

    function hydrateMultiDayCharterFromPayload(form, root, payload) {
        var charter = payload && payload.charter && Array.isArray(payload.charter.days) ? payload.charter.days : [];
        var cards = getCharterDayCards(root);

        cards.forEach(function (card, index) {
            var day = charter[index] || null;
            if (!day) {
                clearCharterDayCardValues(card);
                setCharterDayCardVisible(card, index === 0);
                setCharterDayCardCollapsed(card, index !== 0);
                return;
            }

            setCharterDayCardVisible(card, true);
            setCharterDayCardCollapsed(card, false);
            setInputValue(card, 'charter_day_date', day.date || '');
            setInputValue(card, 'charter_day_start_time', day.start_time || '');
            setInputValue(card, 'charter_day_end_time', day.end_time || '');
            setInputValue(card, 'charter_day_pickup_location', day.pickup_location && day.pickup_location.label ? day.pickup_location.label : '');
            setInputValue(card, 'charter_day_dropoff_location', day.dropoff_location && day.dropoff_location.label ? day.dropoff_location.label : '');
            setInputValue(card, 'charter_day_poi', day.poi_intent || '');
            setInputValue(card, 'charter_day_notes', day.notes || '');

            if (day.place_snapshots && day.place_snapshots.from) {
                placeSnapshots[getCharterDaySnapshotKey(card, 'pickup_location')] = clonePlaceSnapshot(day.place_snapshots.from);
            }
            if (day.place_snapshots && day.place_snapshots.to) {
                placeSnapshots[getCharterDaySnapshotKey(card, 'dropoff_location')] = clonePlaceSnapshot(day.place_snapshots.to);
            }
        });
    }

    var placeSnapshots = {};

    function buildLeg(form, type) {
        var prefix = type === 'return' ? 'return_' : 'outbound_';
        var fromLabel = getFieldValue(form, 'input[name$="-from"], input[name$="_from"]', '');
        var toLabel = getFieldValue(form, 'input[name$="-to"], input[name$="_to"]', '');
        var fromSnapshot = placeSnapshots[prefix + 'from'] || clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
        var toSnapshot = placeSnapshots[prefix + 'to'] || clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);

        // Merge label from text input if snapshot label is missing
        if (!fromSnapshot.label && fromLabel) {
            fromSnapshot.label = fromLabel;
            fromSnapshot.formatted_address = fromLabel;
        }
        if (!toSnapshot.label && toLabel) {
            toSnapshot.label = toLabel;
            toSnapshot.formatted_address = toLabel;
        }

        var leg = {
            type: type,
            from: {
                label: fromLabel,
                name: fromSnapshot.label || '',
                town: '',
                neighbourhood: '',
                place_id: fromSnapshot.place_id || '',
                coords: {
                    lat: fromSnapshot.lat != null ? fromSnapshot.lat : null,
                    lng: fromSnapshot.lng != null ? fromSnapshot.lng : null
                },
                formatted_address: fromSnapshot.formatted_address || fromLabel
            },
            to: {
                label: toLabel,
                name: toSnapshot.label || '',
                town: '',
                neighbourhood: '',
                place_id: toSnapshot.place_id || '',
                coords: {
                    lat: toSnapshot.lat != null ? toSnapshot.lat : null,
                    lng: toSnapshot.lng != null ? toSnapshot.lng : null
                },
                formatted_address: toSnapshot.formatted_address || toLabel
            },
            pickup_date: type === 'return'
                ? getFieldValue(form, 'input[name="return_pickup_date"]', '')
                : getFieldValue(form, 'input[name="outbound_pickup_date"]', ''),
            pickup_time: type === 'return'
                ? getFieldValue(form, 'input[name="return_pickup_time"]', '')
                : getFieldValue(form, 'input[name="outbound_pickup_time"]', ''),
            pickup_datetime: trimValue(
                (type === 'return'
                    ? getFieldValue(form, 'input[name="return_pickup_date"]', '')
                    : getFieldValue(form, 'input[name="outbound_pickup_date"]', '')
                ) + ' ' + (
                    type === 'return'
                        ? getFieldValue(form, 'input[name="return_pickup_time"]', '')
                        : getFieldValue(form, 'input[name="outbound_pickup_time"]', '')
                )
            ),
            stops: [],
            route: {},
            place_snapshots: {
                from: fromSnapshot,
                to: toSnapshot,
                stops: []
            }
        };

var additionalStopEnabled = getBooleanValue(form, 'input[name$="-additional-stop-enabled"], input[name$="_additional_stop_enabled"]');
         var additionalStop = trimValue(getFieldValue(form, 'input[name$="-additional-stop"], input[name$="_additional_stop"]', ''));
         if (additionalStopEnabled && additionalStop) {
             var stopSnapshot = placeSnapshots[prefix + 'additional_stop'] || clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
             leg.stops.push({
                 type: 'additional_stop',
                 location: {
                     label: additionalStop,
                     place_id: stopSnapshot.place_id || '',
                     coords: {
                         lat: stopSnapshot.lat != null ? stopSnapshot.lat : null,
                         lng: stopSnapshot.lng != null ? stopSnapshot.lng : null
                     },
                     formatted_address: stopSnapshot.formatted_address || additionalStop
                 }
             });
             leg.place_snapshots.stops.push(stopSnapshot);
         }

         return leg;
     }

     function buildCharterLeg(form, state) {
        var fromLabel = getFieldValue(form, 'input[name$="-pickup-location"], input[name$="_pickup_location"]', '');
        var toLabel = getFieldValue(form, 'input[name$="-dropoff-location"], input[name$="_dropoff_location"]', '');
        var fromSnapshot = placeSnapshots['charter_pickup_location'] || clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
        var toSnapshot = placeSnapshots['charter_dropoff_location'] || clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);

        if (!fromSnapshot.label && fromLabel) {
            fromSnapshot.label = fromLabel;
            fromSnapshot.formatted_address = fromLabel;
        }
        if (!toSnapshot.label && toLabel) {
            toSnapshot.label = toLabel;
            toSnapshot.formatted_address = toLabel;
        }

        var leg = {
            type: 'charter',
            from: {
                label: fromLabel,
                name: fromSnapshot.label || '',
                town: '',
                neighbourhood: '',
                place_id: fromSnapshot.place_id || '',
                coords: {
                    lat: fromSnapshot.lat != null ? fromSnapshot.lat : null,
                    lng: fromSnapshot.lng != null ? fromSnapshot.lng : null
                },
                formatted_address: fromSnapshot.formatted_address || fromLabel
            },
            to: {
                label: toLabel,
                name: toSnapshot.label || '',
                town: '',
                neighbourhood: '',
                place_id: toSnapshot.place_id || '',
                coords: {
                    lat: toSnapshot.lat != null ? toSnapshot.lat : null,
                    lng: toSnapshot.lng != null ? toSnapshot.lng : null
                },
                formatted_address: toSnapshot.formatted_address || toLabel
            },
            pickup_date: getFieldValue(form, 'input[name="outbound_pickup_date"]', ''),
            pickup_time: getFieldValue(form, 'input[name="charter_pickup_time"]', ''),
            dropoff_time: getFieldValue(form, 'input[name="charter_dropoff_time"]', ''),
            poi_intent: trimValue(getFieldValue(form, 'input[name="charter_poi"]', '')),
            notes: trimValue(getFieldValue(form, 'textarea[name="charter_notes"], input[name="charter_notes"]', '')),
            stops: [],
            route: {},
            place_snapshots: {
                from: fromSnapshot,
                to: toSnapshot,
                stops: []
            }
        };

        return leg;
    }

    function buildPayload(form, state) {
        var currentState = state || {};
        var tripTypeFromForm = getFieldValue(form, 'input[name="trip_type"]:checked', 'one_way');
        var serviceGroup = trimValue(currentState.serviceGroup || (form.closest('[data-wsb-booking-builder]') ? form.closest('[data-wsb-booking-builder]').dataset.wsbServiceGroup : '')) || 'transfer';
        var serviceType = trimValue(currentState.serviceType || (form.closest('[data-wsb-booking-builder]') ? form.closest('[data-wsb-booking-builder]').dataset.wsbServiceType : '')) || 'city_transfer';
        var charterMode = trimValue(currentState.charterMode || getCharterModeValue(form) || 'same_day') || 'same_day';

        var tripType = serviceGroup === 'charter' ? 'charter' : tripTypeFromForm;
        if (serviceGroup === 'charter') {
            serviceType = 'charter_hire';
        }

        var passengers = getSharedNumberValue(form, serviceGroup, 'passengers', 1);
        var legs = [];

        if (passengers < 1) {
            passengers = 1;
        }

        var charterBlock = {
            enabled: false,
            type: null,
            days: []
        };

        if (serviceGroup === 'charter') {
            var charterLeg = null;
            if (charterMode === 'multi_day') {
                var charterDays = collectMultiDayCharterDays(form);
                charterLeg = charterDays.length ? buildCharterLegFromDay(charterDays[0]) : buildCharterLeg(form, state);
                charterBlock = {
                    enabled: true,
                    type: 'reserved',
                    days: charterDays
                };
            } else {
                charterLeg = buildCharterLeg(form, state);
                charterBlock = {
                    enabled: true,
                    type: 'same_day',
                    days: [
                        {
                            day_index: 0,
                            date: charterLeg.pickup_date,
                            start_time: charterLeg.pickup_time,
                            end_time: charterLeg.dropoff_time,
                            pickup_location: charterLeg.from,
                            dropoff_location: charterLeg.to,
                            poi_intent: charterLeg.poi_intent || '',
                            notes: charterLeg.notes || '',
                            stops: []
                        }
                    ]
                };
            }

            legs.push(charterLeg);
        } else {
            legs.push(buildLeg(form, 'outbound'));
            if (tripType === 'return') {
                legs.push(buildLeg(form, 'return'));
            }
        }

        var quoteReady = true;
          var requiredPlaceIds = [];
          legs.forEach(function (leg) {
              if (leg.place_snapshots && leg.place_snapshots.from) {
                  if (!leg.place_snapshots.from.place_id || !leg.place_snapshots.from.lat || !leg.place_snapshots.from.lng || leg.place_snapshots.from.stale) {
                      quoteReady = false;
                      requiredPlaceIds.push(leg.type + '.from');
                  }
              }
              if (leg.place_snapshots && leg.place_snapshots.to) {
                  if (!leg.place_snapshots.to.place_id || !leg.place_snapshots.to.lat || !leg.place_snapshots.to.lng || leg.place_snapshots.to.stale) {
                      quoteReady = false;
                      requiredPlaceIds.push(leg.type + '.to');
                  }
              }
              if (leg.place_snapshots && leg.place_snapshots.stops && leg.stops && leg.stops.length > 0) {
                  leg.stops.forEach(function (stop, stopIndex) {
                      if (stop.location && stop.location.label) {
                          var stopSnap = leg.place_snapshots.stops[stopIndex];
                          if (!stopSnap || !stopSnap.place_id || stopSnap.stale) {
                              quoteReady = false;
                              requiredPlaceIds.push(leg.type + '.stops[' + stopIndex + ']');
                          }
                      }
                  });
              }
          });

        if (serviceGroup === 'charter' && charterBlock.type === 'reserved') {
            charterBlock.days.forEach(function (day, dayIndex) {
                var daySnapshots = day.place_snapshots || {};
                var dayPrefix = 'charter.days.' + dayIndex;

                if (day.pickup_location && day.pickup_location.label) {
                    if (!daySnapshots.from || !daySnapshots.from.place_id || !daySnapshots.from.lat || !daySnapshots.from.lng || daySnapshots.from.stale) {
                        quoteReady = false;
                        requiredPlaceIds.push(dayPrefix + '.pickup_location');
                    }
                }

                if (day.dropoff_location && day.dropoff_location.label) {
                    if (!daySnapshots.to || !daySnapshots.to.place_id || !daySnapshots.to.lat || !daySnapshots.to.lng || daySnapshots.to.stale) {
                        quoteReady = false;
                        requiredPlaceIds.push(dayPrefix + '.dropoff_location');
                    }
                }
            });
        }

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
             baby_seats: getSharedNumberValue(form, serviceGroup, 'baby_seats', 0),
             check_in_bags: getSharedNumberValue(form, serviceGroup, 'check_in_bags', 0),
             carry_on_bags: getSharedNumberValue(form, serviceGroup, 'carry_on_bags', 0),
             add_ons: {
                 trailer: getSharedBooleanValue(form, serviceGroup, 'trailer'),
                 oversize_luggage: getSharedBooleanValue(form, serviceGroup, 'oversize_luggage')
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
validation_flags: {
                  google_place_snapshots_ready: quoteReady
              },
              meta: {
                  preview_only: true,
                  handover_mode: 'preview',
                  created_at: new Date().toISOString()
              }
          };
      }

    function getMissingPlaceIds(payload) {
        var missing = [];
        if (!payload || !payload.legs) {
            return missing;
        }
         payload.legs.forEach(function (leg) {
             if (leg.place_snapshots) {
                 if (leg.place_snapshots.from && !leg.place_snapshots.from.place_id) {
                     missing.push(leg.type + '.from');
                 }
                 if (leg.place_snapshots.to && !leg.place_snapshots.to.place_id) {
                     missing.push(leg.type + '.to');
                 }
                 if (leg.place_snapshots.stops && leg.stops && leg.stops.length > 0) {
                     leg.stops.forEach(function (stop, idx) {
                         if (stop.location && stop.location.label && !leg.place_snapshots.stops[idx].place_id) {
                             missing.push(leg.type + '.stop[' + idx + ']');
                         }
                     });
                }
            }
        });

        if (payload.charter && payload.charter.type === 'reserved' && Array.isArray(payload.charter.days)) {
            payload.charter.days.forEach(function (day, dayIndex) {
                var daySnapshots = day && day.place_snapshots ? day.place_snapshots : {};
                var dayPrefix = 'charter.days.' + dayIndex;

                if (day && day.pickup_location && day.pickup_location.label && (!daySnapshots.from || !daySnapshots.from.place_id)) {
                    missing.push(dayPrefix + '.pickup_location');
                }
                if (day && day.dropoff_location && day.dropoff_location.label && (!daySnapshots.to || !daySnapshots.to.place_id)) {
                    missing.push(dayPrefix + '.dropoff_location');
                }
            });
        }
        return missing;
    }

    function renderPreviewSummary(statusElement, payload, state) {
        if (!statusElement) {
            return;
        }

        var legCount = payload.legs ? payload.legs.length : 0;
        var outboundStops = payload.legs && payload.legs[0] && payload.legs[0].stops && payload.legs[0].stops.length > 0;
        var returnStops = payload.legs && payload.legs[1] && payload.legs[1].stops && payload.legs[1].stops.length > 0;
        var stopLabel = outboundStops ? 'Additional stop: included' : (returnStops ? 'Additional stop: included' : 'Additional stop: not included');
        var charterLabel = '';
        if (payload.charter && payload.charter.enabled) {
            charterLabel = ((payload.charter.days || []).length > 1 ? ('Multi-day hire · ' + (payload.charter.days || []).length + ' days') : 'Single-day hire');
        }
        var summary = [
            'Booking summary ready',
            'Service: ' + (payload.service_group === 'charter' ? 'Shuttle hire' : 'Book a ride'),
            'Trip: ' + (payload.trip_type === 'return' ? 'Return' : 'One-way'),
            legCount + ' leg' + (legCount === 1 ? '' : 's'),
            stopLabel,
            'updated: ' + new Date().toLocaleTimeString()
        ];

        if (charterLabel) {
            summary.splice(4, 0, charterLabel);
        }

        if (state && state.fixtureId) {
            summary.splice(2, 0, 'Fixture: ' + state.fixtureId);
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

    /**
     * Submit payload to handover endpoint for real handoff.
     * Returns Promise resolving to response data with redirect_url on success.
     */
    function requestHandoverPreview(payload) {
        var handoverUrl = typeof CONFIG.handoverPreviewUrl === 'string' ? CONFIG.handoverPreviewUrl : '';
        if (!handoverUrl) {
            return Promise.reject(new Error('Handover endpoint not available'));
        }

        var headers = {
            'Content-Type': 'application/json'
        };

        if (PREVIEW_NONCE) {
            headers['X-WP-Nonce'] = PREVIEW_NONCE;
        }

        return fetch(handoverUrl, {
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
        updateFixtureDrawerStatus(statusElement, (CONFIG.strings && CONFIG.strings.fixtureDrawerOpen) ? CONFIG.strings.fixtureDrawerOpen : 'Sample drawer opened.', 'info');
    }

    function closeFixtureDrawer(drawer, toggle, statusElement) {
        if (!drawer) {
            return;
        }

        drawer.classList.add('wsb-booking-client-hidden');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
        updateFixtureDrawerStatus(statusElement, (CONFIG.strings && CONFIG.strings.fixtureDrawerClosed) ? CONFIG.strings.fixtureDrawerClosed : 'Sample drawer closed.', 'muted');
    }

    function updateServiceMode(root, serviceGroup, charterMode) {
        var transferFields = root.querySelector('[data-wsb-transfer-fields]');
        var charterSection = root.querySelector('[data-wsb-charter-section]');
        var planSection = root.querySelector('[data-wsb-plan-section]');
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

        if (planSection) {
            planSection.classList.toggle('wsb-booking-client-hidden', serviceGroup !== 'plan');
        }

        if (serviceGroup === 'plan') {
            if (transferFields) {
                transferFields.classList.add('wsb-booking-client-hidden');
            }
            if (charterSection) {
                charterSection.classList.add('wsb-booking-client-hidden');
            }
            if (outboundSection) {
                outboundSection.classList.add('wsb-booking-client-hidden');
            }
            if (returnSection) {
                returnSection.classList.add('wsb-booking-client-hidden');
            }
            updateCharterMode(root, 'same_day');
        } else if (serviceGroup === 'charter') {
            if (transferFields) {
                transferFields.classList.add('wsb-booking-client-hidden');
            }
            if (charterSection) {
                charterSection.classList.remove('wsb-booking-client-hidden');
            }
            updateCharterMode(root, charterMode || getCharterModeValue(root.querySelector('[data-wsb-booking-form]') || root));
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
            updateCharterMode(root, 'same_day');
        }
    }

    function updateCharterMode(root, charterMode) {
        var form = root.querySelector('[data-wsb-booking-form]');
        var sameDayPanel = root.querySelector('[data-wsb-charter-same-day-panel]');
        var shell = root.querySelector('[data-wsb-charter-multiday-shell]');
        var dayToolbar = root.querySelector('[data-wsb-charter-day-toolbar]');
        var dayList = root.querySelector('[data-wsb-charter-day-list]');
        var addDayRow = root.querySelector('[data-wsb-charter-add-day-row]');
        var isMultiDay = trimValue(charterMode) === 'multi_day';
        var activeMode = isMultiDay ? 'multi_day' : 'same_day';

        if (root) {
            root.dataset.wsbCharterMode = activeMode;
        }

        if (form) {
            var modeInputs = form.querySelectorAll('input[name="charter_mode"]');
            forEachNode(modeInputs, function (input) {
                input.checked = input.value === activeMode;
            });
        }

        if (sameDayPanel) {
            sameDayPanel.classList.toggle('wsb-booking-client-hidden', isMultiDay);
        }

        if (shell) {
            shell.classList.toggle('wsb-booking-client-hidden', !isMultiDay);
        }

        if (dayList) {
            dayList.classList.toggle('wsb-booking-client-hidden', !isMultiDay);
            if (isMultiDay) {
                getCharterDayCards(root).forEach(function (card) {
                    if (card.getAttribute('data-wsb-charter-day-visible') === 'true') {
                        card.classList.remove('wsb-booking-client-hidden');
                    }
                });
            }
        }

        if (dayToolbar) {
            dayToolbar.classList.toggle('wsb-booking-client-hidden', !isMultiDay);
        }
        if (addDayRow) {
            addDayRow.classList.toggle('wsb-booking-client-hidden', !isMultiDay);
        }

        updateCharterDayButtons(root);
        document.dispatchEvent(new CustomEvent('wsb:charter-days-updated'));
        if (typeof root.__wsbSyncCharterDayDragDrop === 'function') {
            root.__wsbSyncCharterDayDragDrop();
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
        currentState.charterMode = payload && payload.charter && payload.charter.type === 'reserved' ? 'multi_day' : 'same_day';

        if (root) {
            root.dataset.wsbServiceType = currentState.serviceType;
            root.dataset.wsbServiceGroup = currentState.serviceGroup;
            root.dataset.wsbCharterMode = currentState.charterMode;
        }

        updateServiceMode(root, currentState.serviceGroup, currentState.charterMode);

        setRadioValue(form, 'trip_type', payload.trip_type || 'one_way');
        setRadioValue(form, 'charter_mode', currentState.charterMode);
        setInputValue(form, 'passengers', payload.passengers != null ? payload.passengers : 1);
        setInputValue(form, 'baby_seats', payload.baby_seats != null ? payload.baby_seats : 0);
        setInputValue(form, 'check_in_bags', payload.check_in_bags != null ? payload.check_in_bags : 0);
        setInputValue(form, 'carry_on_bags', payload.carry_on_bags != null ? payload.carry_on_bags : 0);
        setCheckboxValue(form, 'trailer', Boolean(payload.add_ons && payload.add_ons.trailer));
        setCheckboxValue(form, 'oversize_luggage', Boolean(payload.add_ons && payload.add_ons.oversize_luggage));

        if (currentState.serviceGroup === 'charter') {
            setInputValue(form, 'charter_pickup_location', outboundLeg.from && outboundLeg.from.label ? outboundLeg.from.label : '');
            setInputValue(form, 'charter_dropoff_location', outboundLeg.to && outboundLeg.to.label ? outboundLeg.to.label : '');
            var charterDay = payload.charter && payload.charter.days && payload.charter.days.length ? payload.charter.days[0] : null;
            setInputValue(form, 'outbound_pickup_date', outboundLeg.pickup_date || (charterDay ? charterDay.date : ''));
            setInputValue(form, 'charter_pickup_time', outboundLeg.pickup_time || (charterDay ? charterDay.start_time : ''));
            setInputValue(form, 'charter_dropoff_time', outboundLeg.dropoff_time || (charterDay ? charterDay.end_time : ''));
            if (payload.charter && payload.charter.type === 'reserved') {
                hydrateMultiDayCharterFromPayload(form, root, payload);
            }

            placeSnapshots.charter_pickup_location = clonePlaceSnapshot((outboundLeg.place_snapshots || {}).from || PLACE_SNAPSHOT_EMPTY);
            placeSnapshots.charter_dropoff_location = clonePlaceSnapshot((outboundLeg.place_snapshots || {}).to || PLACE_SNAPSHOT_EMPTY);
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

            placeSnapshots.outbound_from = clonePlaceSnapshot((outboundLeg.place_snapshots || {}).from || PLACE_SNAPSHOT_EMPTY);
            placeSnapshots.outbound_to = clonePlaceSnapshot((outboundLeg.place_snapshots || {}).to || PLACE_SNAPSHOT_EMPTY);
            if (outboundLeg.place_snapshots && outboundLeg.place_snapshots.stops && outboundLeg.place_snapshots.stops.length) {
                placeSnapshots.outbound_additional_stop = clonePlaceSnapshot(outboundLeg.place_snapshots.stops[0]);
            }
            if (returnLeg) {
                placeSnapshots.return_from = clonePlaceSnapshot((returnLeg.place_snapshots || {}).from || PLACE_SNAPSHOT_EMPTY);
                placeSnapshots.return_to = clonePlaceSnapshot((returnLeg.place_snapshots || {}).to || PLACE_SNAPSHOT_EMPTY);
                if (returnLeg.place_snapshots && returnLeg.place_snapshots.stops && returnLeg.place_snapshots.stops.length) {
                    placeSnapshots.return_additional_stop = clonePlaceSnapshot(returnLeg.place_snapshots.stops[0]);
                }
            }
        }

        setDateDefaults(root);
        setCharterTimeDefaults(root);
        updateAmPmLabels(root);
        refreshPickerStatusMessages(root);

        return null;
    }

    function runFixturePreviewChecks(payload, fixture, validationElement, messageElement, statusElement, state) {
        var expectedOk = Boolean(fixture && fixture.expected_ok);
        var sampleId = trimValue(fixture && fixture.id ? fixture.id : 'sample');
        var sampleLabel = sampleId + ' — Expected: ' + (expectedOk ? 'valid' : 'invalid');
        var strings = CONFIG.strings || {};

        updateFixtureDrawerStatus(statusElement, (strings.fixtureDrawerLoaded || 'Loaded sample:') + ' ' + sampleLabel, 'info');

        return postPayloadPreview(payload, validationElement, messageElement).then(function (serverData) {
            var serverOk = Boolean(serverData && serverData.validation && serverData.validation.valid);
            var serverMatched = serverOk === expectedOk;
            var serverMessage = strings.fixtureDrawerServerMatched || 'Server validation matched expected result.';
            if (!serverMatched) {
                serverMessage = strings.fixtureDrawerServerMismatch || 'Server validation did not match expected result.';
            }

            var followUp = [sampleLabel, serverMessage];
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
                    [sampleLabel, serverMessage, handoverMessage].join(' · '),
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
            returnSection.classList.add('wsb-booking-client-return--visible');
            if (!returnSection.hasAttribute('data-wsb-return-accordion-ready')) {
                returnSection.setAttribute('data-wsb-return-accordion-ready', 'true');
                setReturnAccordionOpen(returnSection, true);
            }
        } else {
            returnSection.classList.add('wsb-booking-client-hidden');
            returnSection.classList.remove('wsb-booking-client-return--visible');
        }
    }

    function setReturnAccordionOpen(returnSection, open) {
        if (!returnSection) {
            return;
        }
        var body = returnSection.querySelector('[data-wsb-return-body]');
        var toggle = returnSection.querySelector('[data-wsb-return-accordion-toggle]');
        returnSection.classList.toggle('wsb-booking-client-return--collapsed', !open);
        if (body) {
            body.classList.toggle('wsb-booking-client-hidden', !open);
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function updateAdditionalStop(toggle, section) {
        if (!section || !toggle) {
            return;
        }

        var toggleLabel = toggle.closest('.wsb-booking-client-additional-toggle-label');
        var path = section.closest('[data-wsb-location-path]');
        var input = section.querySelector('input');

        if (toggle.checked) {
            section.classList.remove('wsb-booking-client-hidden');
            section.setAttribute('data-wsb-additional-stop-open', 'true');
            if (toggleLabel) {
                toggleLabel.classList.add('wsb-booking-client-hidden');
            }
            if (path) {
                path.classList.add('wsb-location-path--has-stop');
            }
            if (input) {
                input.disabled = false;
                updateLocationFieldState(input, !!input.value);
                if (!input.value) {
                    window.setTimeout(function () { input.focus(); }, 30);
                }
            }
        } else {
            section.classList.add('wsb-booking-client-hidden');
            section.removeAttribute('data-wsb-additional-stop-open');
            if (toggleLabel) {
                toggleLabel.classList.remove('wsb-booking-client-hidden');
            }
            if (path) {
                path.classList.remove('wsb-location-path--has-stop');
            }
            if (input) {
                clearLocationField(input);
                input.disabled = true;
            }
        }
    }

    function resetAdditionalStopClosed(toggle, section) {
        if (!toggle || !section || toggle.checked) {
            return;
        }
        section.classList.add('wsb-booking-client-hidden');
        section.removeAttribute('data-wsb-additional-stop-open');
        var input = section.querySelector('input');
        if (input) {
            input.disabled = true;
            updateLocationFieldState(input, false);
        }
        var toggleLabel = toggle.closest('.wsb-booking-client-additional-toggle-label');
        if (toggleLabel && !toggle.disabled) {
            toggleLabel.classList.remove('wsb-booking-client-hidden');
        }
        var path = section.closest('[data-wsb-location-path]');
        if (path) {
            path.classList.remove('wsb-location-path--has-stop');
        }
    }

    function isFeatureGateEnabled(gate) {
        if (typeof CONFIG.featureGates !== 'object' || CONFIG.featureGates === null) {
            return false;
        }
        return Boolean(CONFIG.featureGates[gate]);
    }

    function applyFeatureGateVisibility(root) {
        var additionalStopToggles = root.querySelectorAll('[data-ws-feature-gate="enable_additional_stops"]');
        additionalStopToggles.forEach(function (el) {
            if (el.type === 'checkbox') {
                el.disabled = !isFeatureGateEnabled('enable_additional_stops');
                if (!isFeatureGateEnabled('enable_additional_stops')) {
                    el.checked = false;
                    el.closest('.wsb-booking-client-additional-toggle-label')?.classList.add('wsb-booking-client-hidden');
                } else {
                    el.closest('.wsb-booking-client-additional-toggle-label')?.classList.remove('wsb-booking-client-hidden');
                }
            }
        });

        var additionalStopSections = root.querySelectorAll('[data-wsb-outbound-additional-stop-section], [data-wsb-return-additional-stop-section]');
        additionalStopSections.forEach(function (section) {
            if (!isFeatureGateEnabled('enable_additional_stops')) {
                section.classList.add('wsb-booking-client-hidden');
                var input = section.querySelector('input');
                if (input) {
                    input.disabled = true;
                }
            }
        });

        var multiDayEnabled = isFeatureGateEnabled('enable_multi_day_charters');
        var multiDayShells = root.querySelectorAll('[data-wsb-charter-multiday-shell]');
        multiDayShells.forEach(function (shell) {
            shell.classList.toggle('wsb-booking-client-hidden', !multiDayEnabled);
        });

        var multiDayOptions = root.querySelectorAll('[data-wsb-charter-mode-option="multi_day"]');
        multiDayOptions.forEach(function (input) {
            input.disabled = !multiDayEnabled;
            var label = input.closest('.wsb-booking-client-pill');
            if (label) {
                label.classList.toggle('wsb-booking-client-hidden', !multiDayEnabled);
            }
        });

        if (!multiDayEnabled) {
            updateCharterMode(root, 'same_day');
        }
    }

    function constrainTimeByDate(dateInput, timeInput, constraints) {
        if (!dateInput || !timeInput || !constraints) {
            return;
        }

        var dateValue = dateInput.value;
        if (!dateValue) {
            return;
        }

        var minDateFromConfig = dateInput.getAttribute('min');
        if (!minDateFromConfig) {
            return;
        }

        var transferMinNotice = constraints.transferMinNoticeMinutes || 300;

        var now = new Date();
        var minDateTime = new Date(now.getTime() + transferMinNotice * 60000);
        var minTimeStr = ('0' + minDateTime.getHours()).slice(-2) + ':' + ('0' + minDateTime.getMinutes()).slice(-2);

        if (dateValue === minDateFromConfig) {
            timeInput.min = minTimeStr;
        } else {
            timeInput.removeAttribute('min');
        }
    }

    /* ---------- Date/Time Picker Parity Helpers ---------- */

    function formatTodayPlusDays(days) {
        var d = new Date();
        d.setDate(d.getDate() + days);
        var yyyy = d.getFullYear();
        var mm = ('0' + (d.getMonth() + 1)).slice(-2);
        var dd = ('0' + d.getDate()).slice(-2);
        return yyyy + '-' + mm + '-' + dd;
    }

    function getTomorrowDateString() {
        return formatTodayPlusDays(1);
    }

    function setDateDefaults(root) {
        var outboundDate = getField(root, 'input[name$="-pickup-date"], input[name$="_pickup_date"]');
        var charterDate = getField(root, 'input[name$="-pickup-date"], input[name$="_pickup_date"]');
        var returnDate = getField(root, 'input[name$="-return-pickup-date"], input[name$="_return_pickup_date"]');

        if (outboundDate && !outboundDate.value) {
            outboundDate.value = getTomorrowDateString();
        }
        if (charterDate && !charterDate.value) {
            charterDate.value = getTomorrowDateString();
        }
        if (returnDate && !returnDate.value) {
            returnDate.value = getTomorrowDateString();
        }
    }

    function setCharterTimeDefaults(root) {
        var pickupTime = getField(root, 'input[name$="-pickup-time"], input[name$="_pickup_time"]');
        var dropoffTime = getField(root, 'input[name$="-dropoff-time"], input[name$="_dropoff_time"]');

        if (pickupTime && !pickupTime.value) {
            pickupTime.value = '08:00';
        }
        if (dropoffTime && !dropoffTime.value) {
            dropoffTime.value = '17:00';
        }
    }

    function clearCharterTimeDefaults(root) {
        var pickupTime = getField(root, 'input[name$="-pickup-time"], input[name$="_pickup_time"]');
        var dropoffTime = getField(root, 'input[name$="-dropoff-time"], input[name$="_dropoff_time"]');

        if (pickupTime && pickupTime.value === '08:00') {
            pickupTime.value = '';
        }
        if (dropoffTime && dropoffTime.value === '17:00') {
            dropoffTime.value = '';
        }
    }

    function deriveAmPmLabel(timeValue) {
        if (!timeValue) {
            return '';
        }
        var parts = timeValue.split(':');
        if (parts.length < 2) {
            return '';
        }
        var h = parseInt(parts[0], 10);
        if (isNaN(h)) {
            return '';
        }
        if (h >= 12) {
            return 'PM';
        }
        return 'AM';
    }

    function updateAmPmLabels(root) {
        var timeInputs = root.querySelectorAll('input[name$="-pickup-time"], input[name$="-dropoff-time"], input[name$="_pickup_time"], input[name$="_dropoff_time"], input[name$="_start_time"], input[name$="_end_time"], input[data-wsb-charter-day-field="start_time"], input[data-wsb-charter-day-field="end_time"]');
        forEachNode(timeInputs, function (input) {
            var wrapper = input.closest('.wsb-booking-client-field');
            if (!wrapper) {
                return;
            }
            var existingBadge = wrapper.querySelector('.wsb-time-ampm-badge, .wsb-booking-client-ampm');
            var label = deriveAmPmLabel(input.value);
            if (label) {
                if (!existingBadge) {
                    existingBadge = document.createElement('span');
                    existingBadge.className = 'wsb-time-ampm-badge';
                    input.parentNode.insertBefore(existingBadge, input.nextSibling);
                    wrapper.classList.add('wsb-booking-client-field--time');
                }
                existingBadge.textContent = label;
                wrapper.classList.add('wsb-booking-client-field--time');
            } else if (existingBadge) {
                existingBadge.remove();
            }
            // Clean up class if no badge remains
            if (!wrapper.querySelector('.wsb-time-ampm-badge, .wsb-booking-client-ampm')) {
                wrapper.classList.remove('wsb-booking-client-field--time');
            }
        });
    }

    function getBlockedDatesFromConfig() {
        var config = BOOKING_SITE_CONFIG || {};
        var blockouts = (config.blockouts || {});
        // Scaffold only: live fetch not implemented yet.
        // blockouts may contain a static blocked_dates map for future global blockouts.
        return blockouts.blocked_dates || [];
    }

    function validateDateAgainstBlockouts(dateInput) {
        if (!dateInput) {
            return true;
        }
        var value = dateInput.value;
        if (!value) {
            return true;
        }
        var blocked = getBlockedDatesFromConfig();
        if (!blocked.length) {
            return true;
        }
        return blocked.indexOf(value) === -1;
    }

    function markBlockedDateState(dateInput, isBlocked) {
        if (!dateInput) {
            return;
        }
        if (isBlocked) {
            dateInput.classList.add('wsb-date-blocked');
        } else {
            dateInput.classList.remove('wsb-date-blocked');
        }
    }

    /* ---------- Google Places Autocomplete ---------- */

    var PLACE_SNAPSHOT_EMPTY = {
        provider: null,
        place_id: null,
        label: null,
        formatted_address: null,
        lat: null,
        lng: null
    };

function clonePlaceSnapshot(snapshot) {
         return {
             provider: snapshot.provider || null,
             place_id: snapshot.place_id || null,
             label: snapshot.label || null,
             formatted_address: snapshot.formatted_address || null,
             lat: snapshot.lat != null ? snapshot.lat : null,
             lng: snapshot.lng != null ? snapshot.lng : null,
             stale: snapshot.stale === true
         };
     }

    function stripTrailingCountry(value) {
      if (typeof value !== 'string') {
        return '';
      }
      var trimmed = value.trim();
      var suffixes = [', South Africa', ' South Africa', ', SA', ' SA'];
      for (var i = 0; i < suffixes.length; i += 1) {
        if (trimmed.length > suffixes[i].length && trimmed.slice(-suffixes[i].length) === suffixes[i]) {
          trimmed = trimmed.slice(0, -suffixes[i].length).replace(/,\s*$/, '');
          break;
        }
      }
      return trimmed;
    }

    function formatAddressForDisplay(place, inputValue) {
      if (!place || typeof place !== 'object') {
        return stripTrailingCountry(inputValue);
      }

      var rawDisplay = inputValue || place.formatted_address || place.name || '';
      var cleaned = stripTrailingCountry(rawDisplay);
      var placeName = (typeof place.name === 'string' ? place.name : '').trim();

      if (placeName && cleaned.indexOf(placeName) === -1) {
        cleaned = placeName + (cleaned ? ', ' + cleaned : '');
      }

      return cleaned || placeName || stripTrailingCountry(place.formatted_address) || '';
    }

    function extractPlaceDetails(place, inputValue) {
      var snapshot = clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
      if (!place) {
        return snapshot;
      }

      snapshot.provider = 'google_places';
      snapshot.place_id = typeof place.place_id === 'string' ? place.place_id : null;
      snapshot.label = typeof place.name === 'string' ? place.name : null;
      snapshot.formatted_address = typeof place.formatted_address === 'string' ? place.formatted_address : null;

      var display = formatAddressForDisplay(place, inputValue);
      if (display) {
        snapshot.label = display;
        if (!snapshot.formatted_address) {
          snapshot.formatted_address = display;
        }
      }

      if (place.geometry && place.geometry.location) {
        var loc = place.geometry.location;
        if (typeof loc.lat === 'function') {
          snapshot.lat = loc.lat();
        } else if (typeof loc.lat === 'number') {
          snapshot.lat = loc.lat;
        }
        if (typeof loc.lng === 'function') {
          snapshot.lng = loc.lng();
        } else if (typeof loc.lng === 'number') {
          snapshot.lng = loc.lng;
        }
      }

      return snapshot;
    }

    function markPlaceFieldSelected(input) {
        if (!input) {
            return;
        }
        var wrapper = input.closest('.wsb-booking-client-field');
        if (!wrapper) {
            return;
        }
        wrapper.classList.add('wsb-booking-client-field--place-selected');
        wrapper.classList.remove('wsb-booking-client-field--place-stale');
        wrapper.classList.add('wsb-booking-client-field--place-confirmed');
        wrapper.classList.add('wsb-booking-client-field--has-value');
    }

    function markPlaceFieldStale(input) {
        if (!input) {
            return;
        }
        var wrapper = input.closest('.wsb-booking-client-field');
        if (!wrapper) {
            return;
        }
        wrapper.classList.remove('wsb-booking-client-field--place-selected', 'wsb-booking-client-field--place-confirmed');
        wrapper.classList.add('wsb-booking-client-field--place-stale');
        wrapper.classList.add('wsb-booking-client-field--has-value');
    }

    function clearPlaceFieldState(input) {
        if (!input) {
            return;
        }
        var wrapper = input.closest('.wsb-booking-client-field');
        if (!wrapper) {
            return;
        }
        wrapper.classList.remove('wsb-booking-client-field--place-selected', 'wsb-booking-client-field--place-confirmed', 'wsb-booking-client-field--place-stale', 'wsb-booking-client-field--has-value');
    }

    function updateLocationFieldState(input, hasValue) {
        var wrapper = input.closest('.wsb-booking-client-field');
        if (!wrapper) {
            return;
        }
        if (hasValue && input.value) {
            wrapper.classList.add('wsb-booking-client-field--has-value');
        } else {
            wrapper.classList.remove('wsb-booking-client-field--has-value');
        }
    }

    function isFieldReadyForPlaces(input) {
        if (!input || input.disabled) {
            return false;
        }

        var hiddenContainer = input.closest('.wsb-booking-client-hidden, [hidden]');
        if (hiddenContainer) {
            return false;
        }

        return isElementVisible(input);
    }

    function getPlaceFieldConfig(input) {
        if (!input || !input.matches) {
            return null;
        }

        var fieldMap = [
            { selector: 'input[name="outbound_from"]', snapshotKey: 'outbound_from', routeRole: 'origin', placeRole: 'origin' },
            { selector: 'input[name="outbound_to"]', snapshotKey: 'outbound_to', routeRole: 'destination', placeRole: 'destination' },
            { selector: 'input[name="outbound_additional_stop"]', snapshotKey: 'outbound_additional_stop', routeRole: 'stop', placeRole: 'outbound_stop' },
            { selector: 'input[name="return_from"]', snapshotKey: 'return_from', routeRole: 'return_origin', placeRole: 'return_origin' },
            { selector: 'input[name="return_to"]', snapshotKey: 'return_to', routeRole: 'return_destination', placeRole: 'return_destination' },
            { selector: 'input[name="return_additional_stop"]', snapshotKey: 'return_additional_stop', routeRole: 'stop', placeRole: 'return_stop' },
            { selector: 'input[name="charter_pickup_location"]', snapshotKey: 'charter_pickup_location', routeRole: 'charter_origin', placeRole: 'charter_origin' },
            { selector: 'input[name="charter_dropoff_location"]', snapshotKey: 'charter_dropoff_location', routeRole: 'charter_destination', placeRole: 'charter_destination' },
            { selector: 'input[data-wsb-charter-day-field="pickup_location"]', snapshotKey: function (target) { return getCharterDaySnapshotKey(target.closest('[data-wsb-charter-day-card]'), 'pickup_location'); }, routeRole: 'charter_day_origin', placeRole: 'charter_day_origin' },
            { selector: 'input[data-wsb-charter-day-field="dropoff_location"]', snapshotKey: function (target) { return getCharterDaySnapshotKey(target.closest('[data-wsb-charter-day-card]'), 'dropoff_location'); }, routeRole: 'charter_day_destination', placeRole: 'charter_day_destination' },
        ];

        for (var i = 0; i < fieldMap.length; i += 1) {
            if (input.matches(fieldMap[i].selector)) {
                return fieldMap[i];
            }
        }

        return null;
    }

    function clearLocationField(input) {
        if (!input) {
            return;
        }
        input.value = '';
        clearPlaceFieldState(input);
        updateLocationFieldState(input, false);
    }

    function getLocationSnapshotKey(input) {
        if (!input) {
            return '';
        }

        if (input.hasAttribute('data-wsb-charter-day-field')) {
            var card = input.closest('[data-wsb-charter-day-card]');
            var fieldAttr = input.getAttribute('data-wsb-charter-day-field');
            return card && fieldAttr ? getCharterDaySnapshotKey(card, fieldAttr) : (input.getAttribute('name') || '');
        }

        return input.getAttribute('name') || '';
    }

    function isCurrentLocationSupported() {
        return Boolean(window.navigator && navigator.geolocation && typeof google !== 'undefined' && google.maps && typeof google.maps.Geocoder === 'function');
    }

    function initCurrentLocationButtons(root, refreshCallback) {
        forEachNode(root.querySelectorAll('[data-wsb-place-current]'), function (btn) {
            if (btn.dataset.wsbCurrentLocationBound === 'true') {
                return;
            }

            btn.dataset.wsbCurrentLocationBound = 'true';

            var wrapper = btn.closest('.wsb-booking-client-field');
            var input = wrapper ? wrapper.querySelector('input[type="text"]') : null;

            if (!input) {
                btn.disabled = true;
                return;
            }

            if (!isCurrentLocationSupported()) {
                btn.disabled = true;
                btn.setAttribute('aria-disabled', 'true');
                btn.title = 'Current location unavailable in this browser';
                return;
            }

            btn.addEventListener('click', function () {
                if (!navigator.geolocation || typeof google === 'undefined' || !google.maps || typeof google.maps.Geocoder !== 'function') {
                    return;
                }

                var originalLabel = btn.getAttribute('aria-label') || 'Use current location';
                var snapshotKey = getLocationSnapshotKey(input);

                btn.disabled = true;
                btn.classList.add('wsb-booking-client-place-current--loading');
                btn.setAttribute('aria-label', 'Fetching current location');
                btn.title = 'Fetching current location';

                navigator.geolocation.getCurrentPosition(function (position) {
                    var geocoder = new google.maps.Geocoder();
                    var location = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };

                    geocoder.geocode({ location: location }, function (results) {
                        var result = results && results.length ? results[0] : null;
                        var snapshot = result ? extractPlaceDetails(result, result.formatted_address || result.name || input.value) : clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);

                        if (!snapshot.label) {
                            snapshot.label = (result && result.formatted_address) ? result.formatted_address : 'Current location';
                        }
                        if (!snapshot.formatted_address) {
                            snapshot.formatted_address = snapshot.label;
                        }
                        if (snapshot.place_id == null) {
                            snapshot.place_id = result && result.place_id ? result.place_id : null;
                        }
                        if (!Number.isFinite(snapshot.lat) && result && result.geometry && result.geometry.location && typeof result.geometry.location.lat === 'function') {
                            snapshot.lat = result.geometry.location.lat();
                        }
                        if (!Number.isFinite(snapshot.lng) && result && result.geometry && result.geometry.location && typeof result.geometry.location.lng === 'function') {
                            snapshot.lng = result.geometry.location.lng();
                        }
                        snapshot.provider = snapshot.provider || 'browser_geolocation';

                        if (snapshotKey) {
                            placeSnapshots[snapshotKey] = snapshot;
                        }

                        input.value = snapshot.label || snapshot.formatted_address || 'Current location';
                        markPlaceFieldSelected(input);
                        updateLocationFieldState(input, true);
                        btn.disabled = false;
                        btn.classList.remove('wsb-booking-client-place-current--loading');
                        btn.setAttribute('aria-label', originalLabel);
                        btn.title = originalLabel;

                        if (typeof refreshCallback === 'function') {
                            refreshCallback('');
                        }
                    });
                }, function () {
                    btn.disabled = false;
                    btn.classList.remove('wsb-booking-client-place-current--loading');
                    btn.setAttribute('aria-label', originalLabel);
                    btn.title = originalLabel;
                }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
            });
        });
    }

    function initClearButtons(root, refreshCallback) {
        forEachNode(root.querySelectorAll('[data-wsb-place-clear]'), function (btn) {
            if (btn.dataset.wsbClearBound === 'true') {
                return;
            }
            btn.dataset.wsbClearBound = 'true';
            btn.addEventListener('click', function () {
                var wrapper = btn.closest('.wsb-booking-client-field');
                if (!wrapper) {
                    return;
                }
                var input = wrapper.querySelector('input[type="text"]');
                if (!input) {
                    return;
                }
                var fieldKey = input.name;
                var snapshotKey = null;
                if (fieldKey) {
                    if (input.hasAttribute('data-wsb-charter-day-field')) {
                        var card = input.closest('[data-wsb-charter-day-card]');
                        var fieldAttr = input.getAttribute('data-wsb-charter-day-field');
                        snapshotKey = card && fieldAttr ? getCharterDaySnapshotKey(card, fieldAttr) : fieldKey;
                    } else {
                        snapshotKey = fieldKey;
                    }
                }
                clearLocationField(input);
                if (snapshotKey && snapshotKey in placeSnapshots) {
                    placeSnapshots[snapshotKey] = clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
                }
                if (typeof refreshCallback === 'function') {
                    refreshCallback('');
                }
            });
        });
    }

    function initGooglePlacesAutocomplete(root, refreshCallback) {
        if (!GOOGLE_PLACES.enabled || typeof google === 'undefined' || typeof google.maps === 'undefined' || typeof google.maps.places === 'undefined') {
            if (root && root.dataset && root.dataset.wsbPlacesDelegated !== 'true') {
                root.dataset.wsbPlacesDelegated = 'true';
                initCurrentLocationButtons(root, refreshCallback);
                initClearButtons(root, refreshCallback);
            }
            return;
        }

        if (root && root.dataset && root.dataset.wsbPlacesDelegated !== 'true') {
            root.dataset.wsbPlacesDelegated = 'true';
            initCurrentLocationButtons(root, refreshCallback);
            initClearButtons(root, refreshCallback);

            root.addEventListener('focusin', function (event) {
                var target = event.target;
                if (!target || !target.matches) {
                    return;
                }
                if (target.dataset.wsbPlaceInitialized === 'true' || !isFieldReadyForPlaces(target)) {
                    return;
                }
                primePlaceField(target, refreshCallback);
            });
        }

        primeVisiblePlaceFields(root, refreshCallback);
    }

    function primePlaceField(input, refreshCallback) {
        if (!input) {
            return;
        }

        var field = getPlaceFieldConfig(input);
        if (!field || input.dataset.wsbPlaceInitialized === 'true' || !isFieldReadyForPlaces(input)) {
            return;
        }

        input.dataset.wsbPlaceInitialized = 'true';

        if (field.routeRole) {
            input.setAttribute('data-ws-route-role', field.routeRole);
        }
        if (field.placeRole) {
            input.setAttribute('data-ws-place-role', field.placeRole);
        }
        if (!input.hasAttribute('data-ws-field-key')) {
            input.setAttribute('data-ws-field-key', input.getAttribute('name') || '');
        }

        var autocomplete = new google.maps.places.Autocomplete(input, {
            types: ['establishment', 'geocode'],
            componentRestrictions: { country: 'ZA' },
            fields: ['address_components', 'geometry', 'name', 'place_id', 'formatted_address']
        });

        autocomplete.addListener('place_changed', function () {
            var place = autocomplete.getPlace();
            var currentInputValue = input.value;
            var snapshot = extractPlaceDetails(place, currentInputValue);
            var snapshotKey = typeof field.snapshotKey === 'function' ? field.snapshotKey(input) : field.snapshotKey;

            if (!snapshot.place_id) {
                placeSnapshots[snapshotKey] = clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
                markPlaceFieldSelected(input);
                input.value = '';
                updateLocationFieldState(input, false);
                if (typeof refreshCallback === 'function') {
                    refreshCallback('');
                }
                return;
            }

            placeSnapshots[snapshotKey] = snapshot;
            if (snapshot.label) {
                input.value = snapshot.label;
            }
            markPlaceFieldSelected(input);
            updateLocationFieldState(input, true);
            if (typeof refreshCallback === 'function') {
                refreshCallback('');
            }
        });

        input.addEventListener('click', function () {
            if (input.hasAttribute('readonly')) {
                input.removeAttribute('readonly');
            }
        });

        input.addEventListener('focus', function () {
            if (input.hasAttribute('readonly')) {
                input.removeAttribute('readonly');
            }
        });

        input.addEventListener('input', function () {
            var wrapper = input.closest('.wsb-booking-client-field');
            if (!wrapper) {
                return;
            }
            if (wrapper.classList.contains('wsb-booking-client-field--place-selected')) {
                markPlaceFieldStale(input);
            }
            updateLocationFieldState(input, !!input.value);
        });

        input.addEventListener('focus', function () {
            var wrapper = input.closest('.wsb-booking-client-field');
            if (wrapper && wrapper.classList.contains('wsb-booking-client-field--place-stale')) {
                clearPlaceFieldState(input);
            }
            updateLocationFieldState(input, !!input.value);
        });
    }

    function primeVisiblePlaceFields(root, refreshCallback) {
        var selector = 'input[name="outbound_from"], input[name="outbound_to"], input[name="outbound_additional_stop"], input[name="return_from"], input[name="return_to"], input[name="return_additional_stop"], input[name="charter_pickup_location"], input[name="charter_dropoff_location"], input[data-wsb-charter-day-field="pickup_location"], input[data-wsb-charter-day-field="dropoff_location"]';
        forEachNode(root.querySelectorAll(selector), function (input) {
            if (!input || input.dataset.wsbPlaceInitialized === 'true' || !isElementVisible(input)) {
                return;
            }
            primePlaceField(input, refreshCallback);
        });
    }

    function refreshPickerStatusMessages(root) {
        var outboundDate = root.querySelector('input[name="outbound_pickup_date"]');
        var outboundTime = root.querySelector('input[name="outbound_pickup_time"]');
        var returnDate = root.querySelector('input[name="return_pickup_date"]');
        var returnTime = root.querySelector('input[name="return_pickup_time"]');

        function updateStatus(dateInput, timeInput, statusSelector) {
            if (!dateInput || !timeInput) {
                return;
            }
            var wrapper = dateInput.closest('.wsb-booking-client-picker-group') || dateInput.closest('.wsb-booking-client-grid');
            if (!wrapper) {
                return;
            }
            var statusEl = wrapper.querySelector(statusSelector);
            if (!statusEl) {
                return;
            }
            if (!dateInput.value || !timeInput.value) {
                statusEl.textContent = '';
                statusEl.className = 'wsb-picker-status';
                return;
            }
            var dateValue = dateInput.value;
            var minDate = dateInput.getAttribute('min');
            var maxDate = dateInput.getAttribute('max');
            var timeValue = timeInput.value;
            var minTime = timeInput.getAttribute('min');

            if (minDate && dateValue < minDate) {
                statusEl.textContent = (STRINGS.pickerDateBeforeMin || STRINGS.serverValidationFailed || 'Date is before the earliest allowed date.');
                statusEl.className = 'wsb-picker-status';
                dateInput.classList.add('wsb-date-blocked');
                return;
            }
            if (maxDate && dateValue > maxDate) {
                statusEl.textContent = (STRINGS.pickerDateAfterMax || STRINGS.serverValidationFailed || 'Date exceeds the maximum advance booking window.');
                statusEl.className = 'wsb-picker-status';
                dateInput.classList.add('wsb-date-blocked');
                return;
            }
            if (minTime && timeValue < minTime) {
                statusEl.textContent = (STRINGS.pickerTimeBeforeMin || STRINGS.serverValidationFailed || 'Time is inside the lead-time window.');
                statusEl.className = 'wsb-picker-status';
                return;
            }
            if (!validateDateAgainstBlockouts(dateInput)) {
                statusEl.textContent = (STRINGS.pickerDateBlocked || STRINGS.serverValidationFailed || 'Selected date is blocked for bookings.');
                statusEl.className = 'wsb-picker-status';
                return;
            }
            statusEl.textContent = '';
            statusEl.className = 'wsb-picker-status wsb-picker-status--ok';
            dateInput.classList.remove('wsb-date-blocked');
        }

        updateStatus(outboundDate, outboundTime, '[data-wsb-outbound-picker-status]');
        updateStatus(returnDate, returnTime, '[data-wsb-return-picker-status]');
    }

    function initBookingBuilder(root) {
        var form = root.querySelector('[data-wsb-booking-form]');
        var returnSection = root.querySelector('[data-wsb-return-section]');
        var outboundAdditionalStopToggle = root.querySelector('[data-wsb-outbound-additional-stop-toggle]');
        var outboundAdditionalStopField = root.querySelector('[data-wsb-outbound-additional-stop-section]');
        var returnAdditionalStopToggle = root.querySelector('[data-wsb-return-additional-stop-toggle]');
        var returnAdditionalStopField = root.querySelector('[data-wsb-return-additional-stop-section]');
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
            charterMode: trimValue(root.dataset.wsbCharterMode || 'same_day') || 'same_day',
            fixtureId: '',
            fixtureExpected: ''
        };
        placeSnapshots = {};
        var constraints = {
            transferMinNoticeMinutes: parseInt((BOOKING_SITE_CONFIG.lead_times || {}).transfer_min_notice_minutes || 300, 10),
            charterMinNoticeMinutes: parseInt((BOOKING_SITE_CONFIG.lead_times || {}).charter_min_notice_minutes || 2880, 10),
            maxAdvanceBookingDays: parseInt((BOOKING_SITE_CONFIG.lead_times || {}).max_advance_booking_days || 365, 10),
            timeStepMinutes: parseInt((BOOKING_SITE_CONFIG.picker || {}).time_step_minutes || 5, 10)
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

        function handleCharterDayAction(event) {
            var target = event.target;
            if (!target || !target.closest) {
                return;
            }

            var actionButton = target.closest('[data-wsb-charter-add-day], [data-wsb-charter-collapse-all], [data-wsb-charter-expand-all], [data-wsb-charter-day-toggle], [data-wsb-charter-day-duplicate], [data-wsb-charter-day-delete]');
            if (!actionButton) {
                return;
            }

            if (actionButton.hasAttribute('data-wsb-charter-add-day')) {
                var visibleCards = getVisibleCharterDayCards(root);
                var nextIndex = 0;
                if (visibleCards.length) {
                    nextIndex = getCharterDayCards(root).indexOf(visibleCards[visibleCards.length - 1]) + 1;
                }
                var nextCard = findNextHiddenCharterDayCard(root, nextIndex);
                if (nextCard) {
                    setCharterDayCardVisible(nextCard, true);
                    setCharterDayCardCollapsed(nextCard, false);
                    clearCharterDayCardValues(nextCard);
                    updateCharterDayButtons(root);
                    refreshPreview('');
                }
                event.preventDefault();
                return;
            }

            if (actionButton.hasAttribute('data-wsb-charter-collapse-all')) {
                getVisibleCharterDayCards(root).forEach(function (card) {
                    setCharterDayCardCollapsed(card, true);
                });
                updateCharterDayButtons(root);
                refreshPreview('');
                event.preventDefault();
                return;
            }

            if (actionButton.hasAttribute('data-wsb-charter-expand-all')) {
                getVisibleCharterDayCards(root).forEach(function (card) {
                    setCharterDayCardCollapsed(card, false);
                });
                updateCharterDayButtons(root);
                refreshPreview('');
                event.preventDefault();
                return;
            }

            var card = actionButton.closest('[data-wsb-charter-day-card]');
            if (!card) {
                return;
            }

            if (actionButton.hasAttribute('data-wsb-charter-day-toggle')) {
                setCharterDayCardCollapsed(card, card.getAttribute('data-wsb-charter-day-collapsed') !== 'true');
                updateCharterDayButtons(root);
                refreshPreview('');
                event.preventDefault();
                return;
            }

            if (actionButton.hasAttribute('data-wsb-charter-day-duplicate')) {
                var cards = getCharterDayCards(root);
                var sourceIndex = cards.indexOf(card);
                var targetCard = findNextHiddenCharterDayCard(root, sourceIndex + 1);
                if (targetCard) {
                    copyCharterDayCardValues(card, targetCard);
                    setCharterDayCardVisible(targetCard, true);
                    setCharterDayCardCollapsed(targetCard, false);
                    updateCharterDayButtons(root);
                    refreshPreview('');
                }
                event.preventDefault();
                return;
            }

            if (actionButton.hasAttribute('data-wsb-charter-day-delete')) {
                if (getVisibleCharterDayCards(root).length <= 1) {
                    event.preventDefault();
                    return;
                }
                clearCharterDayCardValues(card);
                setCharterDayCardCollapsed(card, true);
                setCharterDayCardVisible(card, false);
                updateCharterDayButtons(root);
                refreshPreview('');
                event.preventDefault();
            }
        }


        function handleAdditionalStopRemove(event) {
            var button = event.target && event.target.closest ? event.target.closest('[data-wsb-additional-stop-remove]') : null;
            if (!button) {
                return false;
            }
            var section = button.closest('[data-wsb-outbound-additional-stop-section], [data-wsb-return-additional-stop-section]');
            var toggle = null;
            if (section && section.hasAttribute('data-wsb-outbound-additional-stop-section')) {
                toggle = outboundAdditionalStopToggle;
            } else if (section && section.hasAttribute('data-wsb-return-additional-stop-section')) {
                toggle = returnAdditionalStopToggle;
            }
            if (toggle) {
                toggle.checked = false;
                updateAdditionalStop(toggle, section);
                refreshPreview('');
            }
            event.preventDefault();
            return true;
        }

        function handleReturnAccordionClick(event) {
            var button = event.target && event.target.closest ? event.target.closest('[data-wsb-return-accordion-toggle]') : null;
            if (!button || !returnSection) {
                return false;
            }
            var isOpen = button.getAttribute('aria-expanded') !== 'false';
            setReturnAccordionOpen(returnSection, !isOpen);
            event.preventDefault();
            return true;
        }

        function refreshCharterDayOrderLabels() {
            getVisibleCharterDayCards(root).forEach(function (card, index) {
                card.setAttribute('data-wsb-charter-day-slot', String(index + 1));
                var label = card.querySelector('.wsb-booking-client-eyebrow');
                if (label) {
                    label.textContent = 'Day ' + (index + 1);
                }
                var title = card.querySelector('.wsb-charter-day-title strong');
                var route = card.querySelector('[data-wsb-day-route-label]');
                var summary = card.querySelector('[data-wsb-day-summary]');
                var pickup = getCharterDayField(card, 'pickup_location');
                var dropoff = getCharterDayField(card, 'dropoff_location');
                var date = getCharterDayField(card, 'date');
                var start = getCharterDayField(card, 'start_time');
                var end = getCharterDayField(card, 'end_time');
                if (title) {
                    title.firstChild.nodeValue = 'Day ' + (index + 1) + ' — ';
                }
                if (route) {
                    var p = pickup && pickup.value ? pickup.value.split(',')[0] : 'Start location';
                    var d = dropoff && dropoff.value ? dropoff.value.split(',')[0] : 'End location';
                    route.textContent = p + ' to ' + d;
                }
                if (summary) {
                    summary.textContent = (date && date.value ? date.value : 'Date') + ' · ' + (start && start.value ? start.value : 'start time') + ' – ' + (end && end.value ? end.value : 'end time');
                }
            });
        }

        var charterDaySortable = null;
        var charterDayDragEnabled = false;
        var charterDayDragListenersBound = false;

        function destroyNativeCharterDayDragDrop() {
            charterDayDragEnabled = false;
            if (charterDaySortable && typeof charterDaySortable.destroy === 'function') {
                try {
                    charterDaySortable.destroy();
                } catch (error) {
                    if (DEBUG) {
                        logDebug('Sortable destroy failed', error);
                    }
                }
            }
            charterDaySortable = null;
        }

        function syncNativeCharterDayDragDrop() {
            var shell = root.querySelector('[data-wsb-charter-multiday-shell]');
            var isActive = Boolean(shell && !shell.classList.contains('wsb-booking-client-hidden'));
            if (isActive) {
                initNativeCharterDayDragDrop();
            } else {
                destroyNativeCharterDayDragDrop();
            }
        }

        function initNativeCharterDayDragDrop() {
            var shell = root.querySelector('[data-wsb-charter-multiday-shell]');
            var list = root.querySelector('[data-wsb-sortable-list="charter-day-list"]');
            var isActive = Boolean(shell && !shell.classList.contains('wsb-booking-client-hidden') && list);

            if (!list) {
                destroyNativeCharterDayDragDrop();
                return;
            }

            if (!isActive) {
                destroyNativeCharterDayDragDrop();
                return;
            }

            if (typeof window.Sortable === 'function') {
                if (charterDaySortable) {
                    charterDayDragEnabled = true;
                    return;
                }
                try {
                    charterDaySortable = new window.Sortable(list, {
                        animation: 120,
                        draggable: '.wsb-charter-day-card:not(.wsb-booking-client-hidden)',
                        handle: '[data-wsb-drag-handle]',
                        ghostClass: 'wsb-sortable-placeholder',
                        chosenClass: 'wsb-sortable-chosen',
                        onStart: function () {
                            document.documentElement.classList.add('wsb-sortable-is-dragging');
                        },
                        onEnd: function () {
                            document.documentElement.classList.remove('wsb-sortable-is-dragging');
                            refreshCharterDayOrderLabels();
                            document.dispatchEvent(new CustomEvent('wsb:charter-days-updated'));
                            refreshPreview('');
                        }
                    });
                    charterDayDragEnabled = true;
                    return;
                } catch (error) {
                    charterDaySortable = null;
                    if (DEBUG) {
                        logDebug('Sortable initialisation failed, falling back to simple drag', error);
                    }
                }
            }

            charterDayDragEnabled = true;

            if (charterDayDragListenersBound) {
                return;
            }
            charterDayDragListenersBound = true;

            // Minimal fallback if Sortable is unavailable.
            forEachNode(list.querySelectorAll('[data-wsb-drag-handle]'), function (handle) {
                handle.setAttribute('draggable', 'true');
            });

            var draggedCard = null;

            list.addEventListener('dragstart', function (event) {
                if (!charterDayDragEnabled) {
                    event.preventDefault();
                    return;
                }
                var handle = event.target && event.target.closest ? event.target.closest('[data-wsb-drag-handle]') : null;
                if (!handle) {
                    event.preventDefault();
                    return;
                }

                draggedCard = handle.closest('[data-wsb-charter-day-card]');
                if (!draggedCard || draggedCard.classList.contains('wsb-booking-client-hidden')) {
                    event.preventDefault();
                    draggedCard = null;
                    return;
                }

                draggedCard.classList.add('wsb-sortable-chosen');
                document.documentElement.classList.add('wsb-sortable-is-dragging');
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', draggedCard.getAttribute('data-wsb-charter-day-id') || '');
                }
            });

            list.addEventListener('dragover', function (event) {
                if (!draggedCard || !charterDayDragEnabled) {
                    return;
                }

                var targetCard = event.target && event.target.closest ? event.target.closest('[data-wsb-charter-day-card]') : null;
                if (!targetCard || targetCard === draggedCard || targetCard.classList.contains('wsb-booking-client-hidden')) {
                    return;
                }

                event.preventDefault();
                var rect = targetCard.getBoundingClientRect();
                var insertAfter = event.clientY > rect.top + (rect.height / 2);
                list.insertBefore(draggedCard, insertAfter ? targetCard.nextSibling : targetCard);
            });

            list.addEventListener('dragend', function () {
                if (!draggedCard) {
                    return;
                }

                draggedCard.classList.remove('wsb-sortable-chosen');
                draggedCard = null;
                document.documentElement.classList.remove('wsb-sortable-is-dragging');
                refreshCharterDayOrderLabels();
                updateCharterDayButtons(root);
                document.dispatchEvent(new CustomEvent('wsb:charter-days-updated'));
                refreshPreview('');
            });
        }

        var debouncedRefresh = debounce(function () {
            refreshPreview('');
        }, 150);

        var tripTypeInputs = form.querySelectorAll('input[name="trip_type"]');
        forEachNode(tripTypeInputs, function (radio) {
            radio.addEventListener('change', function () {
                updateReturnVisibility(returnSection, tripTypeInputs);
                initGooglePlacesAutocomplete(root, refreshPreview);
                refreshPreview('');
            });
        });

        var serviceTabs = root.querySelectorAll('[data-wsb-service-tab]');
        forEachNode(serviceTabs, function (tab) {
            tab.addEventListener('click', function () {
                var serviceGroup = trimValue(tab.getAttribute('data-service'));
                if (serviceGroup === 'charter' || serviceGroup === 'transfer' || serviceGroup === 'plan') {
                    state.serviceGroup = serviceGroup;
                    root.dataset.wsbServiceGroup = serviceGroup;
                    state.serviceType = serviceGroup === 'charter' ? 'charter_hire' : (serviceGroup === 'plan' ? 'multi_trip_plan' : 'city_transfer');
                    root.dataset.wsbServiceType = state.serviceType;
                    if (serviceGroup === 'charter') {
                        setCharterTimeDefaults(root);
                    } else {
                        clearCharterTimeDefaults(root);
                    }
                    updateServiceMode(root, serviceGroup, state.charterMode);
                    updateReturnVisibility(returnSection, tripTypeInputs);
                    initClockTimePicker(root);
                    initGooglePlacesAutocomplete(root, refreshPreview);
                    refreshPreview('');
                }
            });
        });

        root.addEventListener('click', function (event) {
            if (handleAdditionalStopRemove(event) || handleReturnAccordionClick(event)) {
                return;
            }
            handleCharterDayAction(event);
        });

        if (outboundAdditionalStopToggle) {
            outboundAdditionalStopToggle.addEventListener('change', function () {
                updateAdditionalStop(outboundAdditionalStopToggle, outboundAdditionalStopField);
                initGooglePlacesAutocomplete(root, refreshPreview);
                refreshPreview('');
            });
        }

        if (returnAdditionalStopToggle) {
            returnAdditionalStopToggle.addEventListener('change', function () {
                updateAdditionalStop(returnAdditionalStopToggle, returnAdditionalStopField);
                initGooglePlacesAutocomplete(root, refreshPreview);
                refreshPreview('');
            });
        }

        var outboundDateInput = form.querySelector('input[name$="-pickup-date"], input[name$="_pickup_date"]');
        var outboundTimeInput = form.querySelector('input[name$="-pickup-time"], input[name$="_pickup_time"]');
        if (outboundDateInput && outboundTimeInput) {
            outboundDateInput.addEventListener('change', function () {
                constrainTimeByDate(outboundDateInput, outboundTimeInput, constraints);
                updateAmPmLabels(root);
                refreshPickerStatusMessages(root);
                refreshPreview('');
            });
        }

        var charterDateInput = form.querySelector('input[name$="-pickup-date"], input[name$="_pickup_date"]');
        var charterTimeInput = form.querySelector('input[name$="-pickup-time"], input[name$="_pickup_time"]');
        if (charterDateInput && charterTimeInput) {
            charterDateInput.addEventListener('change', function () {
                constrainTimeByDate(charterDateInput, charterTimeInput, constraints);
                updateAmPmLabels(root);
                refreshPickerStatusMessages(root);
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
                        updateFixtureDrawerStatus(fixtureStatus, 'Sample not found: ' + trimValue(chip.getAttribute('data-wsb-fixture-id')), 'error');
                        return;
                    }

                    applyFixtureToForm(form, root, fixture, state);
                    var payload = refreshPreview((CONFIG.strings && CONFIG.strings.fixtureDrawerLoaded ? CONFIG.strings.fixtureDrawerLoaded : 'Loaded sample:') + ' ' + fixture.id + ' — Expected: ' + (fixture.expected_ok ? 'valid' : 'invalid'));
                    runFixturePreviewChecks(payload, fixture, validationElement, messageElement, fixtureStatus, state);
                });
            });
        }

        form.addEventListener('input', debouncedRefresh);
        forEachNode(root.querySelectorAll('.wsb-booking-client-field--location input[type="text"]'), function (input) {
            if (input.dataset.wsbInputStateBound === 'true') {
                return;
            }
            input.dataset.wsbInputStateBound = 'true';
            input.addEventListener('input', function () {
                updateLocationFieldState(input, !!input.value);
            });
            input.addEventListener('change', function () {
                updateLocationFieldState(input, !!input.value);
            });
        });
        form.addEventListener('change', function (event) {
            var target = event && event.target ? event.target : null;
            if (target && target.name === 'charter_mode') {
                state.charterMode = getCharterModeValue(form);
                root.dataset.wsbCharterMode = state.charterMode;
                updateCharterMode(root, state.charterMode);
                initGooglePlacesAutocomplete(root, refreshPreview);
            }
            refreshPreview('');
        });
        form.addEventListener('blur', function () {
            refreshPreview('');
}, true);
form.addEventListener('submit', function (event) {
             event.preventDefault();
             var payload = buildPayload(form, state);
             var missingSnapshots = getMissingPlaceIds(payload);

             if (missingSnapshots.length > 0 && GOOGLE_PLACES.requiredForQuoteReady) {
                 renderValidationError(validationElement, (CONFIG.strings && CONFIG.strings.placeSnapshotRequired ? CONFIG.strings.placeSnapshotRequired : 'Please choose the address from the suggestions.'));
                 if (messageElement) {
                     messageElement.textContent = 'Please choose each address from the suggestions.';
                 }
                 return;
             }

             // Submit directly to handover endpoint
             refreshPreview('Checking availability...');
             if (messageElement) {
                 messageElement.textContent = 'Checking availability...';
             }

             return requestHandoverPreview(payload)
                 .then(function (data) {
                     if (data && data.success && data.redirect_url) {
                         // Real handoff succeeded - redirect to booking site
                         window.location.href = data.redirect_url;
                         return;
                     }

                     if (data && data.ok) {
                         // Preview mode - just show validation success
                         renderPayload(previewElement, statusElement, messageElement, payload, 'Your details have been checked.', state);
                         renderValidationOutput(validationElement, data.validation);
                         if (messageElement) {
                             messageElement.textContent = STRINGS.serverValidationSuccess;
                         }
                         return;
                     }

                     // Error case
                     renderValidationError(validationElement, data && data.error ? data.error : STRINGS.serverPreviewError);
                     if (messageElement) {
                         messageElement.textContent = data && data.error ? data.error : STRINGS.serverPreviewError;
                     }
                 })
                 .catch(function (error) {
                     renderValidationError(validationElement, STRINGS.serverPreviewError);
                     if (messageElement) {
                         messageElement.textContent = STRINGS.serverPreviewError;
                     }
                     logDebug('Handover submit failed', error);
                 });
      });

        updateServiceMode(root, state.serviceGroup, state.charterMode);
        updateReturnVisibility(returnSection, tripTypeInputs);
        applyFeatureGateVisibility(root);
        resetAdditionalStopClosed(outboundAdditionalStopToggle, outboundAdditionalStopField);
        resetAdditionalStopClosed(returnAdditionalStopToggle, returnAdditionalStopField);
        updateAdditionalStop(outboundAdditionalStopToggle, outboundAdditionalStopField);
        updateAdditionalStop(returnAdditionalStopToggle, returnAdditionalStopField);
        updateCharterMode(root, state.charterMode);
        initNativeCharterDayDragDrop();
        refreshCharterDayOrderLabels();
        setDateDefaults(root);
        updateAmPmLabels(root);
        refreshPickerStatusMessages(root);
        root.__wsbSyncCharterDayDragDrop = syncNativeCharterDayDragDrop;
        syncNativeCharterDayDragDrop();
        initClockTimePicker(root);
        initGooglePlacesAutocomplete(root, refreshPreview);
        forEachNode(root.querySelectorAll('.wsb-booking-client-field--location input[type="text"]'), function (input) {
            updateLocationFieldState(input, !!input.value);
        });
        refreshPreview('Booking summary initialised');
        if (fixtureStatus && fixtures.length) {
            updateFixtureDrawerStatus(
                fixtureStatus,
                (CONFIG.strings && CONFIG.strings.fixtureDrawerDefault) ? CONFIG.strings.fixtureDrawerDefault : 'Choose a sample to load booking details.',
                'muted'
            );
        }
    }

    // UI Interaction Scaffold (sortable adapter)
    // No-op fallback when no sortable library is present.
    // Future: integrate SortableJS-style adapter for ordered stops/day rows.
    var WSB_BOOKING_UI_INTERACTIONS = {
        _sortableInstances: {},

        /**
         * Check if a sortable library is available.
         * Currently returns false until a library is explicitly approved.
         *
         * @return bool
         */
        isSortableAvailable: function () {
            return !!(typeof window.Sortable !== 'undefined' && window.Sortable);
        },

        /**
         * Initialize a sortable list on the given root element.
         * No-op if sortable library is not available or disabled.
         *
         * @param HTMLElement root - Container with data-wsb-sortable-list
         * @param object options - Optional SortableJS options
         * @return void
         */
        initSortableList: function (root, options) {
            if (!this.isSortableAvailable()) {
                if (DEBUG) {
                    logDebug('Sortable not available, initSortableList is no-op');
                }
                return;
            }

            var list = root.querySelector('[data-wsb-sortable-list]');
            if (!list) {
                return;
            }

            try {
                var instance = new window.Sortable(list, Object.assign({
                    animation: 150,
                    handle: '.wsb-drag-handle',
                    ghostClass: 'wsb-sortable-placeholder',
                    chosenClass: 'wsb-sortable-chosen'
                }, options || {}));
                this._sortableInstances[list.id] = instance;
            } catch (e) {
                if (DEBUG) {
                    logDebug('Sortable init failed', e);
                }
            }
        },

        /**
         * Destroy a sortable list instance.
         * No-op if sortable library is not available.
         *
         * @param HTMLElement root - Container with data-wsb-sortable-list
         * @return void
         */
        destroySortableList: function (root) {
            if (!this.isSortableAvailable()) {
                return;
            }

            var list = root.querySelector('[data-wsb-sortable-list]');
            if (list && list.id && this._sortableInstances[list.id]) {
                this._sortableInstances[list.id].destroy();
                delete this._sortableInstances[list.id];
            }
        }
    };

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

    var activeClockInput = null;
    var clockPositionTimer = null;

    function getClockPopupCandidates() {
        var selectors = [
            '.clock-timepicker-popup',
            '.clock-timepicker-popover',
            '.ui-clockpicker',
            '.clockpicker-popover'
        ];
        var nodes = [];
        selectors.forEach(function (selector) {
            nodes = nodes.concat(Array.prototype.slice.call(document.querySelectorAll(selector)));
        });
        return nodes.filter(function (node) {
            if (!node || !node.getBoundingClientRect) {
                return false;
            }
            var style = window.getComputedStyle(node);
            if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity || '1') === 0) {
                return false;
            }
            var rect = node.getBoundingClientRect();
            return rect.width > 20 && rect.height > 20;
        });
    }

    function getVisibleClockPopup() {
        var candidates = getClockPopupCandidates();
        return candidates.length ? candidates[candidates.length - 1] : null;
    }

    function positionClockPopup(input) {
        if (!input || !input.getBoundingClientRect) {
            return;
        }
        var popup = getVisibleClockPopup();
        if (!popup) {
            return;
        }

        var rect = input.getBoundingClientRect();
        var popupRect = popup.getBoundingClientRect();
        var popupWidth = Math.max(popupRect.width || 280, 260);
        var popupHeight = Math.max(popupRect.height || 260, 240);
        var gap = 12;
        var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 1024;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 768;
        var left = rect.left;
        var top;

        // Prefer above the field. This avoids the CTA/footer collision at the bottom of the form.
        if (rect.top > popupHeight + gap + 8) {
            top = rect.top - popupHeight - gap;
            popup.setAttribute('data-wsb-placement', 'top');
        } else if (viewportHeight - rect.bottom > popupHeight + gap + 8) {
            top = rect.bottom + gap;
            popup.setAttribute('data-wsb-placement', 'bottom');
        } else {
            top = Math.max(16, rect.top - popupHeight - gap);
            popup.setAttribute('data-wsb-placement', 'top-clamped');
        }

        if (left + popupWidth > viewportWidth - 16) {
            left = viewportWidth - popupWidth - 16;
        }
        left = Math.max(16, left);

        popup.style.position = 'fixed';
        popup.style.top = Math.round(top) + 'px';
        popup.style.left = Math.round(left) + 'px';
        popup.style.zIndex = '2147483000';
        popup.style.maxWidth = 'calc(100vw - 32px)';
        popup.classList.add('wsb-clock-popup-positioned');
    }

    function scheduleClockPosition(root, input) {
        activeClockInput = input || activeClockInput;
        if (clockPositionTimer) {
            window.clearTimeout(clockPositionTimer);
        }
        clockPositionTimer = window.setTimeout(function () {
            clockPositionTimer = null;
            if (!activeClockInput || !getVisibleClockPopup()) {
                activeClockInput = null;
                return;
            }
            if (activeClockInput) {
                positionClockPopup(activeClockInput);
            }
            updateAmPmLabels(root);
        }, 16);
    }

    function bindClockPositioning(root, input) {
        if (input.dataset.wsbClockPositionReady === 'true') {
            return;
        }
        input.dataset.wsbClockPositionReady = 'true';
        ['focus', 'click', 'mousedown', 'touchstart', 'input', 'change'].forEach(function (eventName) {
            input.addEventListener(eventName, function () {
                [0, 40, 120].forEach(function (delay) {
                    window.setTimeout(function () {
                        scheduleClockPosition(root, input);
                    }, delay);
                });
            }, { passive: eventName === 'touchstart' });
        });
    }

    window.addEventListener('resize', function () {
        if (activeClockInput && getVisibleClockPopup()) {
            scheduleClockPosition(document, activeClockInput);
        } else if (activeClockInput && !getVisibleClockPopup()) {
            activeClockInput = null;
        }
    });

    window.addEventListener('scroll', function () {
        if (activeClockInput && getVisibleClockPopup()) {
            scheduleClockPosition(document, activeClockInput);
        } else if (activeClockInput && !getVisibleClockPopup()) {
            activeClockInput = null;
        }
    }, { capture: true, passive: true });

    function initClockTimePicker(root) {
        if (!window.jQuery || !jQuery.fn.clockTimePicker) {
            return;
        }
        var clockSelector = 'input[type="text"][name$="-pickup-time"], input[type="text"][name$="-dropoff-time"], input[type="text"][name$="_pickup_time"], input[type="text"][name$="_dropoff_time"], input[type="text"][name$="_start_time"], input[type="text"][name$="_end_time"], input[type="text"][data-wsb-charter-day-field="start_time"], input[type="text"][data-wsb-charter-day-field="end_time"]';
        var clockTheme = {
            alwaysSelectHoursFirst: true,
            duration: false,
            precision: 5,
            onlyShowClockOnMobile: false,
            popupWidthOnDesktop: 280,
            i18n: { cancelButton: 'Cancel', okButton: 'Done' },
            colors: {
                buttonTextColor: '#d92d20',
                clockFaceColor: '#ffffff',
                clockInnerCircleTextColor: '#6b7280',
                clockInnerCircleUnselectableTextColor: '#d1d5db',
                clockOuterCircleTextColor: '#111827',
                clockOuterCircleUnselectableTextColor: '#d1d5db',
                hoverCircleColor: 'rgba(217, 45, 32, 0.12)',
                popupBackgroundColor: '#ffffff',
                popupHeaderBackgroundColor: '#d92d20',
                popupHeaderTextColor: '#ffffff',
                selectorColor: '#d92d20',
                selectorNumberColor: '#ffffff',
                signButtonColor: '#ffffff',
                signButtonBackgroundColor: '#d92d20'
            }
        };
        function primeClockInput(input) {
            if (!input || input.dataset.wsbClockReady === 'true' || input.closest('.clock-timepicker') || !isElementVisible(input)) {
                return;
            }

            bindClockPositioning(root, input);
            input.dataset.wsbClockReady = 'true';
            try {
                jQuery(input).clockTimePicker(clockTheme);
            } catch (e) {
                input.dataset.wsbClockReady = 'false';
                if (DEBUG) {
                    logDebug('Clock timepicker init failed', e);
                }
            }
        }

        if (root && root.dataset && root.dataset.wsbClockDelegated !== 'true') {
            root.dataset.wsbClockDelegated = 'true';
            root.addEventListener('focusin', function (event) {
                var input = event.target;
                if (!input || !input.matches || !input.matches(clockSelector)) {
                    return;
                }
                primeClockInput(input);
            });
        }

        forEachNode(root.querySelectorAll(clockSelector), function (input) {
            primeClockInput(input);
        });

        root.addEventListener('focusout', function () {
            window.setTimeout(function () {
                if (!getVisibleClockPopup()) {
                    activeClockInput = null;
                }
            }, 0);
        });

        updateAmPmLabels(root);
     }
})();
