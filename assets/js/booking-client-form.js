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
        if (serviceGroup === 'plan') {
            return form.querySelector('[data-wsb-plan-fields]') || form;
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

    function getMultiTripSharedValues(context) {
        var root = context && context.querySelector ? context : null;
        return {
            passengers: getSharedNumberValue(root || context, 'plan', 'passengers', 1),
            baby_seats: getSharedNumberValue(root || context, 'plan', 'baby_seats', 0),
            check_in_bags: getSharedNumberValue(root || context, 'plan', 'check_in_bags', 0),
            carry_on_bags: getSharedNumberValue(root || context, 'plan', 'carry_on_bags', 0),
            add_ons: {
                trailer: getSharedBooleanValue(root || context, 'plan', 'trailer'),
                oversize_luggage: getSharedBooleanValue(root || context, 'plan', 'oversize_luggage')
            }
        };
    }

    function formatPlanDate(value) {
        var text = trimValue(value);
        if (!text) {
            return '';
        }

        var parsed = new Date(text + 'T00:00:00');
        if (!Number.isNaN(parsed.getTime())) {
            var day = String(parsed.getDate()).padStart(2, '0');
            var month = String(parsed.getMonth() + 1).padStart(2, '0');
            return day + '/' + month + '/' + parsed.getFullYear();
        }

        return text;
    }

    function shortLocationLabel(location, fallback) {
        var value = '';
        if (location && typeof location === 'object') {
            value = trimValue(location.label || location.formatted_address || location.name || '');
        } else {
            value = trimValue(location || '');
        }

        if (!value) {
            value = trimValue(fallback || '');
        }

        if (!value) {
            return '';
        }

        value = value.split(',')[0].trim();
        if (value.length > 32) {
            value = value.slice(0, 29).trim() + '…';
        }

        return value;
    }

    function ensureMultiTripTemplateLast(root) {
        if (!root) {
            return null;
        }

        var template = root.querySelector('[data-wsb-multi-trip-template="true"]');
        var list = root.querySelector('[data-wsb-multi-trip-list]');
        if (template && list && template.parentNode === list && list.lastElementChild !== template) {
            list.appendChild(template);
        }

        return template;
    }

    function revealMultiTripTemplate(root) {
        var template = root ? root.querySelector('[data-wsb-multi-trip-template="true"]') : null;
        if (!template) {
            return null;
        }

        template.setAttribute('data-wsb-multi-trip-visible', 'true');
        template.setAttribute('aria-expanded', 'true');
        template.classList.remove('wsb-booking-client-hidden');
        template.classList.remove('wsb-booking-client-charter-day-card--collapsed');
        var body = template.querySelector('[data-wsb-multi-trip-body]');
        if (body) {
            body.classList.remove('wsb-booking-client-hidden');
        }
        clearMultiTripCardValues(template);
        ensureMultiTripTemplateLast(root);
        return template;
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
            toggle.setAttribute('aria-label', collapsed ? 'Expand day details' : 'Collapse day details');
            if (toggle.classList.contains('wsb-icon-action--toggle')) {
                toggle.setAttribute('data-wsb-action-tooltip', collapsed ? 'Expand day details' : 'Collapse day details');
            }
        });

        var body = card.querySelector('[data-wsb-charter-day-body]');
        if (body) {
            body.classList.toggle('wsb-booking-client-hidden', collapsed);
        }
    }

    function setCharterDayDefaults(card) {
        if (!card) {
            return;
        }

        var today = getTomorrowDateString();
        var dateField = getCharterDayField(card, 'date');
        var startTimeField = getCharterDayField(card, 'start_time');
        var endTimeField = getCharterDayField(card, 'end_time');

        if (dateField && !trimValue(dateField.value)) {
            dateField.value = today;
        }
        if (startTimeField && !trimValue(startTimeField.value)) {
            startTimeField.value = '08:00';
        }
        if (endTimeField && !trimValue(endTimeField.value)) {
            endTimeField.value = '17:00';
        }
    }

    function collapseOtherCharterDayCards(root, activeCard) {
        getVisibleCharterDayCards(root).forEach(function (card) {
            if (activeCard && card === activeCard) {
                return;
            }
            setCharterDayCardCollapsed(card, true);
        });
    }

    function updateCharterDayButtons(root) {
        var cards = getCharterDayCards(root);
        var visibleCards = getVisibleCharterDayCards(root);
        var visibleCount = visibleCards.length;
        var addButtons = root.querySelectorAll('[data-wsb-charter-add-day]');

        forEachNode(addButtons, function (addButton) {
            addButton.disabled = visibleCount >= cards.length;
        });

        cards.forEach(function (card) {
            var cardVisible = card.getAttribute('data-wsb-charter-day-visible') === 'true' && !card.classList.contains('wsb-booking-client-hidden');
            var duplicateButton = card.querySelector('[data-wsb-charter-day-duplicate]');
            var deleteButton = card.querySelector('[data-wsb-charter-day-delete]');
            var toggleButton = card.querySelector('[data-wsb-charter-day-toggle]');
            var hasHiddenCard = visibleCount < cards.length;

            if (duplicateButton) {
                duplicateButton.disabled = !cardVisible || !hasHiddenCard;
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
        if (!cards.length) {
            return null;
        }
        var safeStart = Math.max(0, parseInt(startIndex || 0, 10)) % cards.length;
        for (var offset = 0; offset < cards.length; offset += 1) {
            var card = cards[(safeStart + offset) % cards.length];
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
            var targetField = getCharterDayField(targetCard, fieldKey);
            if (targetField && sourceSnapshot.place_id) {
                markPlaceFieldSelected(targetField);
            } else if (targetField && trimValue(targetField.value)) {
                markPlaceFieldStale(targetField);
            } else {
                clearPlaceFieldState(targetField);
            }
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

        if (root) {
            root.dataset.wsbCharterMultiDaySeeded = 'true';
        }

        cards.forEach(function (card, index) {
            var day = charter[index] || null;
            if (!day) {
                clearCharterDayCardValues(card);
                setCharterDayCardVisible(card, index === 0);
                setCharterDayCardCollapsed(card, index !== 0);
                setCharterDayDefaults(card);
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

    function getMultiTripCards(root) {
        if (!root) {
            return [];
        }

        return Array.prototype.slice.call(root.querySelectorAll('[data-wsb-multi-trip-card]'));
    }

    function getMultiTripContentCards(root) {
        return getMultiTripCards(root).filter(function (card) {
            return card.getAttribute('data-wsb-multi-trip-template') !== 'true';
        });
    }

    function getVisibleMultiTripCards(root) {
        return getMultiTripContentCards(root).filter(function (card) {
            return card.getAttribute('data-wsb-multi-trip-visible') === 'true' && !card.classList.contains('wsb-booking-client-hidden');
        });
    }

    function getMultiTripField(card, fieldKey) {
        if (!card) {
            return null;
        }

        var field = card.querySelector('[data-wsb-multi-trip-field="' + fieldKey + '"]');
        if (!field) {
            return null;
        }
        if (field.matches('input, textarea, select')) {
            return field;
        }
        return field.querySelector('input, textarea, select');
    }

    function getMultiTripSnapshotKey(card, fieldKey) {
        var tripId = card && card.getAttribute ? trimValue(card.getAttribute('data-wsb-multi-trip-id')) : '';
        return tripId ? tripId + ':' + fieldKey : fieldKey;
    }

    function getMultiTripSnapshot(card, fieldKey) {
        return placeSnapshots[getMultiTripSnapshotKey(card, fieldKey)] || clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
    }

    function setMultiTripSnapshot(card, fieldKey, snapshot) {
        placeSnapshots[getMultiTripSnapshotKey(card, fieldKey)] = snapshot ? clonePlaceSnapshot(snapshot) : clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
    }

    function getNextMultiTripId(root) {
        var existing = {};
        forEachNode(root ? root.querySelectorAll('[data-wsb-multi-trip-card]') : [], function (card) {
            var id = trimValue(card.getAttribute('data-wsb-multi-trip-id') || '');
            if (id) {
                existing[id] = true;
            }
        });

        var stamp = Date.now();
        var attempt = 0;
        var candidate = '';
        do {
            attempt += 1;
            candidate = 'trip_' + stamp + '_' + attempt;
        } while (existing[candidate]);

        return candidate;
    }

    function resetMultiTripCardRuntimeState(card) {
        if (!card) {
            return;
        }

        forEachNode(card.querySelectorAll('input, textarea, select, button'), function (node) {
            if (!node || !node.dataset) {
                return;
            }
            delete node.dataset.wsbPlaceInitialized;
            delete node.dataset.wsbCurrentLocationBound;
            delete node.dataset.wsbClearBound;
            delete node.dataset.wsbInputStateBound;
            delete node.dataset.wsbClockReady;
        });

        // cloneNode() copies jQuery UI's marker class/ARIA attributes but not its
        // instance data. Leaving the marker behind makes later-trip date inputs
        // look initialised while they have no working datepicker instance.
        forEachNode(card.querySelectorAll('[data-wsb-datepicker], input[type="date"]'), function (input) {
            input.classList.remove('hasDatepicker');
            input.removeAttribute('aria-haspopup');
            input.removeAttribute('aria-owns');
            input.removeAttribute('aria-expanded');
            if (window.jQuery) {
                window.jQuery(input).removeData('wsbDp').removeData('datepicker');
            }
        });

        forEachNode(card.querySelectorAll('.wsb-poi-field'), function (field) {
            delete field.dataset.wsbPoiBound;
            var input = field.querySelector('input[type="text"]');
            var datalist = field.querySelector('datalist');
            var listbox = field.querySelector('.wsb-poi-suggestions');
            if (listbox && listbox.parentNode) {
                listbox.parentNode.removeChild(listbox);
            }
            if (input && datalist && datalist.id) {
                input.setAttribute('list', datalist.id);
                input.removeAttribute('role');
                input.removeAttribute('aria-autocomplete');
                input.removeAttribute('aria-expanded');
                input.removeAttribute('aria-controls');
            }
            field.classList.remove('is-poi-open');
        });

        forEachNode(card.querySelectorAll('.clock-timepicker'), function (wrapper) {
            var input = wrapper.querySelector('input');
            if (input && wrapper.parentNode) {
                wrapper.parentNode.insertBefore(input, wrapper);
            }
            if (wrapper.parentNode) {
                wrapper.parentNode.removeChild(wrapper);
            }
        });

        card.querySelectorAll('.clock-timepicker-popup, .clock-timepicker-popover').forEach(function (popup) {
            if (popup && popup.parentNode) {
                popup.parentNode.removeChild(popup);
            }
        });
    }

    function prepareMultiTripClonedCard(card, root) {
        if (!card) {
            return null;
        }

        var clonedTripId = getNextMultiTripId(root);
        card.setAttribute('data-wsb-multi-trip-id', clonedTripId);
        card.setAttribute('data-wsb-multi-trip-visible', 'true');
        card.setAttribute('aria-expanded', 'true');
        card.classList.remove('wsb-booking-client-hidden', 'wsb-booking-client-charter-day-card--collapsed', 'wsb-booking-client-multi-trip-card--template');
        card.removeAttribute('data-wsb-multi-trip-template');

        var body = card.querySelector('[data-wsb-multi-trip-body]');
        if (body) {
            body.classList.remove('wsb-booking-client-hidden');
        }

        // Give the detached clone its own radio group before insertion. If a
        // checked clone enters the DOM with the source name, the browser
        // unchecks the source trip type before reindexing can repair the names.
        forEachNode(card.querySelectorAll('[data-wsb-multi-trip-trip-type-option]'), function (input) {
            input.name = clonedTripId + '_trip_type';
        });

        resetMultiTripCardRuntimeState(card);
        return card;
    }

    function copyMultiTripSnapshots(sourceCard, targetCard) {
        if (!sourceCard || !targetCard) {
            return;
        }
        var copied = {};
        forEachNode(sourceCard.querySelectorAll('[data-wsb-multi-trip-field]'), function (holder) {
            var fieldKey = trimValue(holder.getAttribute('data-wsb-multi-trip-field') || '');
            if (!fieldKey || copied[fieldKey]) {
                return;
            }
            copied[fieldKey] = true;
            setMultiTripSnapshot(targetCard, fieldKey, getMultiTripSnapshot(sourceCard, fieldKey));
        });
    }

    function copyMultiTripCardValues(sourceCard, targetCard) {
        if (!sourceCard || !targetCard) {
            return;
        }

        var copiedFields = {};
        forEachNode(sourceCard.querySelectorAll('[data-wsb-multi-trip-field]'), function (holder) {
            var fieldKey = trimValue(holder.getAttribute('data-wsb-multi-trip-field') || '');
            if (!fieldKey || copiedFields[fieldKey]) {
                return;
            }

            var sourceField = getMultiTripField(sourceCard, fieldKey);
            var targetField = getMultiTripField(targetCard, fieldKey);
            if (!sourceField || !targetField) {
                return;
            }

            copiedFields[fieldKey] = true;
            if (sourceField.type === 'checkbox' || sourceField.type === 'radio') {
                targetField.checked = sourceField.checked;
            } else if (sourceField.tagName === 'SELECT') {
                targetField.selectedIndex = sourceField.selectedIndex;
                targetField.value = sourceField.value;
            } else {
                // Copy the live property explicitly. cloneNode() is not a safe
                // contract for user-edited form state across enhanced controls.
                targetField.value = sourceField.value;
            }
        });

        var tripType = getMultiTripTripType(sourceCard);
        setMultiTripTripType(targetCard, tripType);

        [
            ['one_way_additional_stop_enabled', 'one_way_additional_stop'],
            ['return_return_additional_stop_enabled', 'return_return_additional_stop']
        ].forEach(function (stopConfig) {
            var toggle = getMultiTripField(targetCard, stopConfig[0]);
            var stopField = getMultiTripField(targetCard, stopConfig[1]);
            var section = stopField ? stopField.closest('[data-wsb-additional-stop-section]') : null;
            if (toggle && section) {
                updateAdditionalStop(toggle, section);
            }
        });

        var sourceReturnToggle = sourceCard.querySelector('[data-wsb-multi-trip-return-toggle]');
        var targetReturnToggle = targetCard.querySelector('[data-wsb-multi-trip-return-toggle]');
        var targetReturnBody = targetCard.querySelector('[data-wsb-multi-trip-return-body]');
        if (tripType === 'return' && sourceReturnToggle && targetReturnToggle && targetReturnBody) {
            var returnExpanded = sourceReturnToggle.getAttribute('aria-expanded') === 'true';
            targetReturnToggle.setAttribute('aria-expanded', returnExpanded ? 'true' : 'false');
            targetReturnBody.classList.toggle('wsb-booking-client-hidden', !returnExpanded);
        }

        copyMultiTripSnapshots(sourceCard, targetCard);
    }

    function duplicateMultiTripCard(root, sourceCard, refreshCallback, syncDragCallback) {
        if (!root || !sourceCard || !sourceCard.parentNode) {
            return null;
        }

        var duplicatedCard = sourceCard.cloneNode(true);
        prepareMultiTripClonedCard(duplicatedCard, root);
        copyMultiTripCardValues(sourceCard, duplicatedCard);
        sourceCard.parentNode.insertBefore(duplicatedCard, sourceCard.nextSibling);
        collapseOtherMultiTripCards(root, duplicatedCard);
        setMultiTripCardCollapsed(duplicatedCard, false);
        syncMultiTripCardIndices(root);
        refreshMultiTripCardSummary(duplicatedCard);
        initGooglePlacesAutocomplete(root, refreshCallback);
        initClockTimePicker(root);
        initPoiFields(root, refreshCallback);
        document.dispatchEvent(new CustomEvent('wsb:booking-builder:fields-added'));
        if (typeof syncDragCallback === 'function') {
            syncDragCallback();
        }
        if (typeof refreshCallback === 'function') {
            refreshCallback('');
        }
        return duplicatedCard;
    }

    function updateMultiTripButtons(root) {
        var cards = getMultiTripContentCards(root);
        var visibleCount = getVisibleMultiTripCards(root).length;

        cards.forEach(function (card) {
            var deleteButton = card.querySelector('[data-wsb-multi-trip-remove]');
            var toggleButton = card.querySelector('[data-wsb-multi-trip-toggle]');
            var copyButton = card.querySelector('[data-wsb-multi-trip-copy]');
            var cardVisible = card.getAttribute('data-wsb-multi-trip-visible') === 'true' && !card.classList.contains('wsb-booking-client-hidden');

            if (deleteButton) {
                deleteButton.disabled = !cardVisible || visibleCount <= 1;
            }
            if (toggleButton) {
                toggleButton.disabled = !cardVisible;
            }
            if (copyButton) {
                copyButton.disabled = !cardVisible;
            }
        });
    }

    function setMultiTripCardCollapsed(card, collapsed) {
        if (!card) {
            return;
        }
        card.classList.toggle('wsb-booking-client-charter-day-card--collapsed', collapsed);
        card.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        var body = card.querySelector('[data-wsb-multi-trip-body]');
        if (body) {
            body.classList.toggle('wsb-booking-client-hidden', collapsed);
        }
        forEachNode(card.querySelectorAll('[data-wsb-multi-trip-toggle]'), function (toggle) {
            var label = collapsed ? 'Expand trip details' : 'Collapse trip details';
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (toggle.classList.contains('wsb-icon-action--toggle')) {
                toggle.setAttribute('aria-label', label);
                toggle.setAttribute('data-wsb-action-tooltip', label);
            }
        });
    }

    function collapseOtherMultiTripCards(root, activeCard) {
        getVisibleMultiTripCards(root).forEach(function (card) {
            if (card !== activeCard) {
                setMultiTripCardCollapsed(card, true);
            }
        });
    }

    function getMultiTripTripType(card) {
        if (!card) {
            return 'one_way';
        }

        var checked = card.querySelector('[data-wsb-multi-trip-trip-type-option]:checked');
        return checked && checked.value ? trimValue(checked.value) || 'one_way' : 'one_way';
    }

    function setMultiTripDefaults(card, tripType) {
        if (!card) {
            return;
        }

        function setDateDefault(key, daysAhead) {
            var input = getMultiTripField(card, key);
            if (input && !trimValue(input.value || '')) {
                var desiredDate = formatTodayPlusDays(daysAhead);
                var minimumDate = trimValue(input.getAttribute('min') || '');
                input.value = minimumDate && minimumDate > desiredDate ? minimumDate : desiredDate;
            }
        }

        function setTimeDefault(key, fallback) {
            var input = getMultiTripField(card, key);
            if (input && !trimValue(input.value || '')) {
                input.value = fallback;
            }
        }

        setDateDefault('pickup_date', 1);
        setTimeDefault('pickup_time', getCurrentTimeString());

        if (tripType === 'return') {
            setDateDefault('return_return_pickup_date', 2);
            setTimeDefault('return_return_pickup_time', getCurrentTimeString());
        }

        if (tripType === 'charter') {
            setDateDefault('charter_pickup_date', 2);
            setTimeDefault('charter_pickup_time', '08:00');
            setTimeDefault('charter_dropoff_time', '17:00');
        }
    }

    function setMultiTripTripType(card, tripType) {
        if (!card) {
            return;
        }

        var resolved = tripType === 'return' || tripType === 'charter' ? tripType : 'one_way';
        card.setAttribute('data-wsb-multi-trip-trip-type', resolved);

        forEachNode(card.querySelectorAll('[data-wsb-multi-trip-trip-type-option]'), function (input) {
            input.checked = trimValue(input.value) === resolved;
        });

        forEachNode(card.querySelectorAll('[data-wsb-multi-trip-section]'), function (section) {
            var sectionType = section.getAttribute('data-wsb-multi-trip-section');
            var hideSection = sectionType === 'one_way' ? resolved === 'charter' : (sectionType === 'return' ? resolved !== 'return' : sectionType === 'charter' ? resolved !== 'charter' : sectionType !== resolved);
            section.classList.toggle('wsb-booking-client-hidden', hideSection);
        });

        var outboundHeading = card.querySelector('[data-wsb-multi-trip-outbound-heading]');
        if (outboundHeading) {
            outboundHeading.classList.toggle('wsb-booking-client-hidden', resolved !== 'return');
        }

        var returnBody = card.querySelector('[data-wsb-multi-trip-return-body]');
        var returnToggle = card.querySelector('[data-wsb-multi-trip-return-toggle]');
        if (resolved === 'return' && returnBody && returnToggle) {
            returnBody.classList.remove('wsb-booking-client-hidden');
            returnToggle.setAttribute('aria-expanded', 'true');
        }

        var titleLabel = card.querySelector('[data-wsb-multi-trip-type-label]');
        if (titleLabel) {
            titleLabel.textContent = resolved === 'return' ? 'Return' : (resolved === 'charter' ? 'Charter' : 'One-way');
        }

        var icon = card.querySelector('[data-wsb-multi-trip-icon]');
        if (icon) {
            icon.classList.remove('wsb-charter-day-icon--plan-one-way', 'wsb-charter-day-icon--plan-return', 'wsb-charter-day-icon--plan-charter');
            icon.classList.add(resolved === 'return' ? 'wsb-charter-day-icon--plan-return' : (resolved === 'charter' ? 'wsb-charter-day-icon--plan-charter' : 'wsb-charter-day-icon--plan-one-way'));
        }

        setMultiTripDefaults(card, resolved);
        updateAmPmLabels(card);

    }

    function getMultiTripTripSectionField(card, sectionType, fieldKey) {
        if (!card) {
            return null;
        }

        var section = card.querySelector('[data-wsb-multi-trip-section="' + sectionType + '"]');
        return section ? section.querySelector('[data-wsb-multi-trip-field="' + fieldKey + '"]') : null;
    }

    function clearMultiTripCardValues(card) {
        if (!card) {
            return;
        }

        forEachNode(card.querySelectorAll('[data-wsb-multi-trip-field]'), function (field) {
            if (!field) {
                return;
            }

            if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = false;
            } else if (field.tagName === 'SELECT') {
                field.selectedIndex = 0;
            } else {
                field.value = '';
            }
        });

        [
            'pickup_date', 'pickup_time', 'from', 'to', 'passengers', 'bags', 'notes',
            'one_way_from', 'one_way_to', 'one_way_pickup_date', 'one_way_pickup_time', 'one_way_additional_stop',
            'return_outbound_from', 'return_outbound_to', 'return_outbound_pickup_date', 'return_outbound_pickup_time', 'return_outbound_additional_stop',
            'return_return_from', 'return_return_to', 'return_return_pickup_date', 'return_return_pickup_time', 'return_return_additional_stop',
            'charter_pickup_date', 'charter_pickup_time', 'charter_dropoff_time', 'charter_pickup_location', 'charter_dropoff_location', 'charter_poi', 'charter_notes'
        ].forEach(function (fieldKey) {
            setMultiTripSnapshot(card, fieldKey, PLACE_SNAPSHOT_EMPTY);
        });

        setMultiTripTripType(card, 'one_way');
    }

    function buildMultiTripLocationPayload(label, snapshot) {
        return buildLocationPayload(label, snapshot);
    }

    function buildMultiTripLegFromFields(card, config) {
        var fromField = getMultiTripField(card, config.fromField || '');
        var toField = getMultiTripField(card, config.toField || '');
        var dateField = getMultiTripField(card, config.dateField || '');
        var timeField = getMultiTripField(card, config.timeField || '');
        var additionalStopField = config.additionalStopField ? getMultiTripField(card, config.additionalStopField) : null;
        var additionalStopToggle = config.additionalStopToggle ? getMultiTripField(card, config.additionalStopToggle) : null;
        var fromLabel = trimValue(fromField && fromField.value ? fromField.value : '');
        var toLabel = trimValue(toField && toField.value ? toField.value : '');
        var fromSnapshot = getMultiTripSnapshot(card, config.fromField || '');
        var toSnapshot = getMultiTripSnapshot(card, config.toField || '');

        if (!fromSnapshot.label && fromLabel) {
            fromSnapshot.label = fromLabel;
            fromSnapshot.formatted_address = fromLabel;
        }
        if (!toSnapshot.label && toLabel) {
            toSnapshot.label = toLabel;
            toSnapshot.formatted_address = toLabel;
        }

        var leg = {
            type: config.type || 'outbound',
            from: {
                label: fromLabel,
                name: fromSnapshot.label || '',
                town: '',
                neighbourhood: '',
                place_id: fromSnapshot.place_id || '',
                coords: { lat: fromSnapshot.lat != null ? fromSnapshot.lat : null, lng: fromSnapshot.lng != null ? fromSnapshot.lng : null },
                formatted_address: fromSnapshot.formatted_address || fromLabel
            },
            to: {
                label: toLabel,
                name: toSnapshot.label || '',
                town: '',
                neighbourhood: '',
                place_id: toSnapshot.place_id || '',
                coords: { lat: toSnapshot.lat != null ? toSnapshot.lat : null, lng: toSnapshot.lng != null ? toSnapshot.lng : null },
                formatted_address: toSnapshot.formatted_address || toLabel
            },
            pickup_date: trimValue(dateField && dateField.value ? dateField.value : ''),
            pickup_time: trimValue(timeField && timeField.value ? timeField.value : ''),
            dropoff_time: config.dropoffField ? trimValue((getMultiTripField(card, config.dropoffField) || {}).value || '') : '',
            stops: [],
            route: {},
            place_snapshots: {
                from: fromSnapshot,
                to: toSnapshot,
                stops: []
            }
        };

        if (additionalStopField) {
            var stopLabel = trimValue(additionalStopField.value || '');
            var stopEnabled = Boolean(additionalStopToggle ? additionalStopToggle.checked : stopLabel);
            var stopSnapshot = getMultiTripSnapshot(card, config.additionalStopField);

            if (!stopSnapshot.label && stopLabel) {
                stopSnapshot.label = stopLabel;
                stopSnapshot.formatted_address = stopLabel;
            }

            if (stopEnabled && stopLabel) {
                leg.stops.push({
                    type: 'additional_stop',
                    location: {
                        label: stopLabel,
                        place_id: stopSnapshot.place_id || '',
                        coords: {
                            lat: stopSnapshot.lat != null ? stopSnapshot.lat : null,
                            lng: stopSnapshot.lng != null ? stopSnapshot.lng : null
                        },
                        formatted_address: stopSnapshot.formatted_address || stopLabel
                    }
                });
                leg.place_snapshots.stops.push(stopSnapshot);
            }
        }

        return leg;
    }

    function buildMultiTripTripPayload(card, index, sharedValues) {
        var tripType = getMultiTripTripType(card);
        var shared = sharedValues || getMultiTripSharedValues(card ? card.closest('[data-wsb-booking-builder]') : null);
        var notesField = getMultiTripField(card, tripType === 'charter' ? 'charter_notes' : 'notes');

        if (tripType === 'return') {
            var outboundLeg = buildMultiTripLegFromFields(card, {
                type: 'outbound',
                fromField: 'from',
                toField: 'to',
                dateField: 'pickup_date',
                timeField: 'pickup_time',
                additionalStopField: 'one_way_additional_stop',
                additionalStopToggle: 'one_way_additional_stop_enabled'
            });
            var returnLeg = buildMultiTripLegFromFields(card, {
                type: 'return',
                fromField: 'return_return_from',
                toField: 'return_return_to',
                dateField: 'return_return_pickup_date',
                timeField: 'return_return_pickup_time',
                additionalStopField: 'return_return_additional_stop',
                additionalStopToggle: 'return_return_additional_stop_enabled'
            });
            var returnNotes = trimValue(notesField && notesField.value ? notesField.value : '');
            var pickupSnapshot = outboundLeg.place_snapshots && outboundLeg.place_snapshots.from ? outboundLeg.place_snapshots.from : clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
            var dropoffSnapshot = returnLeg.place_snapshots && returnLeg.place_snapshots.to ? returnLeg.place_snapshots.to : clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);

            return {
                trip_index: index + 1,
                label: 'Trip ' + (index + 1),
                title: 'Trip ' + (index + 1),
                trip_label: 'Trip ' + (index + 1),
                trip_type: 'return',
                service_type: 'city_transfer',
                date: outboundLeg.pickup_date || '',
                pickup_time: outboundLeg.pickup_time || '',
                pickup: { address: outboundLeg.from && outboundLeg.from.label ? outboundLeg.from.label : '', place_snapshot: pickupSnapshot },
                dropoff: { address: returnLeg.to && returnLeg.to.label ? returnLeg.to.label : '', place_snapshot: dropoffSnapshot },
                passengers: shared.passengers,
                baby_seats: shared.baby_seats,
                check_in_bags: shared.check_in_bags,
                carry_on_bags: shared.carry_on_bags,
                add_ons: { trailer: shared.add_ons.trailer, oversize_luggage: shared.add_ons.oversize_luggage },
                notes: returnNotes,
                stops: outboundLeg.stops.concat(returnLeg.stops || []),
                legs: [outboundLeg, returnLeg]
            };
        }

        if (tripType === 'charter') {
            var charterLeg = buildMultiTripLegFromFields(card, {
                type: 'charter',
                fromField: 'charter_pickup_location',
                toField: 'charter_dropoff_location',
                dateField: 'charter_pickup_date',
                timeField: 'charter_pickup_time',
                dropoffField: 'charter_dropoff_time'
            });
            var poiField = getMultiTripField(card, 'charter_poi');
            var charterNotesField = getMultiTripField(card, 'charter_notes');
            var pickupSnapshotCharter = charterLeg.place_snapshots && charterLeg.place_snapshots.from ? charterLeg.place_snapshots.from : clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
            var dropoffSnapshotCharter = charterLeg.place_snapshots && charterLeg.place_snapshots.to ? charterLeg.place_snapshots.to : clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);

            return {
                trip_index: index + 1,
                label: 'Trip ' + (index + 1),
                title: 'Trip ' + (index + 1),
                trip_label: 'Trip ' + (index + 1),
                trip_type: 'charter',
                service_type: 'charter_hire',
                date: charterLeg.pickup_date || '',
                pickup_time: charterLeg.pickup_time || '',
                pickup: { address: charterLeg.from && charterLeg.from.label ? charterLeg.from.label : '', place_snapshot: pickupSnapshotCharter },
                dropoff: { address: charterLeg.to && charterLeg.to.label ? charterLeg.to.label : '', place_snapshot: dropoffSnapshotCharter },
                passengers: shared.passengers,
                baby_seats: shared.baby_seats,
                check_in_bags: shared.check_in_bags,
                carry_on_bags: shared.carry_on_bags,
                add_ons: { trailer: shared.add_ons.trailer, oversize_luggage: shared.add_ons.oversize_luggage },
                notes: trimValue(charterNotesField && charterNotesField.value ? charterNotesField.value : ''),
                poi: trimValue(poiField && poiField.value ? poiField.value : ''),
                stops: [],
                legs: [charterLeg]
            };
        }

        var oneWayLeg = buildMultiTripLegFromFields(card, {
            type: 'outbound',
            fromField: 'from',
            toField: 'to',
            dateField: 'pickup_date',
            timeField: 'pickup_time',
            additionalStopField: 'one_way_additional_stop',
            additionalStopToggle: 'one_way_additional_stop_enabled'
        });
        var oneWayNotes = trimValue(notesField && notesField.value ? notesField.value : '');
        var pickupSnapshotOneWay = oneWayLeg.place_snapshots && oneWayLeg.place_snapshots.from ? oneWayLeg.place_snapshots.from : clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);
        var dropoffSnapshotOneWay = oneWayLeg.place_snapshots && oneWayLeg.place_snapshots.to ? oneWayLeg.place_snapshots.to : clonePlaceSnapshot(PLACE_SNAPSHOT_EMPTY);

        return {
            trip_index: index + 1,
            label: 'Trip ' + (index + 1),
            title: 'Trip ' + (index + 1),
            trip_label: 'Trip ' + (index + 1),
            trip_type: 'one_way',
            service_type: 'city_transfer',
            date: oneWayLeg.pickup_date || '',
            pickup_time: oneWayLeg.pickup_time || '',
            pickup: { address: oneWayLeg.from && oneWayLeg.from.label ? oneWayLeg.from.label : '', place_snapshot: pickupSnapshotOneWay },
            dropoff: { address: oneWayLeg.to && oneWayLeg.to.label ? oneWayLeg.to.label : '', place_snapshot: dropoffSnapshotOneWay },
            passengers: shared.passengers,
            baby_seats: shared.baby_seats,
            check_in_bags: shared.check_in_bags,
            carry_on_bags: shared.carry_on_bags,
            add_ons: { trailer: shared.add_ons.trailer, oversize_luggage: shared.add_ons.oversize_luggage },
            notes: oneWayNotes,
            stops: oneWayLeg.stops || [],
            legs: [oneWayLeg]
        };
    }

    function collectMultiTripTrips(root, sharedValues) {
        var cards = getVisibleMultiTripCards(root);
        var trips = [];
        var shared = sharedValues || getMultiTripSharedValues(root);

        cards.forEach(function (card) {
            trips.push(buildMultiTripTripPayload(card, trips.length, shared));
        });

        return trips;
    }

    function collectMultiTripLegs(root) {
        var trips = collectMultiTripTrips(root);
        var legs = [];

        trips.forEach(function (trip) {
            if (Array.isArray(trip.legs)) {
                trip.legs.forEach(function (leg) {
                    legs.push(leg);
                });
            }
        });

        return legs;
    }

    function refreshMultiTripCardSummary(card) {
        if (!card) {
            return;
        }

        var root = card.closest ? card.closest('[data-wsb-booking-builder]') : null;
        var summary = card.querySelector('[data-wsb-multi-trip-summary]');
        var routeLabel = card.querySelector('[data-wsb-multi-trip-route-label]');
        var tripType = getMultiTripTripType(card);
        var payload = buildMultiTripTripPayload(card, 0, getMultiTripSharedValues(root));
        var routeParts = [];
        var metaParts = [];
        var stopCount = Array.isArray(payload.stops) ? payload.stops.length : 0;

        if (tripType === 'return') {
            routeParts.push(shortLocationLabel(payload.legs && payload.legs[0] && payload.legs[0].from, payload.pickup && payload.pickup.address));
            routeParts.push(shortLocationLabel(payload.legs && payload.legs[0] && payload.legs[0].to, payload.dropoff && payload.dropoff.address));
            routeParts.push(shortLocationLabel(payload.legs && payload.legs[1] && payload.legs[1].from, payload.pickup && payload.pickup.address));
            routeParts.push(shortLocationLabel(payload.legs && payload.legs[1] && payload.legs[1].to, payload.dropoff && payload.dropoff.address));
        } else {
            routeParts.push(shortLocationLabel(payload.pickup && payload.pickup.place_snapshot ? payload.pickup.place_snapshot : null, payload.pickup && payload.pickup.address ? payload.pickup.address : ''));
            routeParts.push(shortLocationLabel(payload.dropoff && payload.dropoff.place_snapshot ? payload.dropoff.place_snapshot : null, payload.dropoff && payload.dropoff.address ? payload.dropoff.address : ''));
        }

        routeParts = routeParts.filter(Boolean);

        if (tripType === 'charter') {
            if (payload.date) {
                metaParts.push(formatPlanDate(payload.date));
            }
            if (payload.pickup_time && payload.legs && payload.legs[0] && payload.legs[0].dropoff_time) {
                metaParts.push(payload.pickup_time + ' – ' + payload.legs[0].dropoff_time);
            } else if (payload.pickup_time) {
                metaParts.push(payload.pickup_time);
            }
            if (payload.poi) {
                metaParts.push(shortLocationLabel(payload.poi, payload.poi));
            }
        } else {
            if (payload.date) {
                metaParts.push(formatPlanDate(payload.date));
            }
            if (payload.pickup_time) {
                metaParts.push(payload.pickup_time);
            }
            if (tripType === 'return') {
                var outboundDate = payload.legs && payload.legs[0] ? formatPlanDate(payload.legs[0].pickup_date) : '';
                var outboundTime = payload.legs && payload.legs[0] ? trimValue(payload.legs[0].pickup_time || '') : '';
                var returnDate = payload.legs && payload.legs[1] ? formatPlanDate(payload.legs[1].pickup_date) : '';
                var returnTime = payload.legs && payload.legs[1] ? trimValue(payload.legs[1].pickup_time || '') : '';
                var outboundMeta = [outboundDate, outboundTime].filter(Boolean).join(' · ');
                var returnMeta = [returnDate, returnTime].filter(Boolean).join(' · ');
                metaParts = [];
                if (outboundMeta) {
                    metaParts.push('Outbound ' + outboundMeta);
                }
                if (returnMeta) {
                    metaParts.push('Return ' + returnMeta);
                }
            }
            if (stopCount > 0) {
                metaParts.push(stopCount + ' stop' + (stopCount === 1 ? '' : 's'));
            }
        }

        if (routeLabel) {
            if (tripType === 'return' && routeParts.length >= 4) {
                routeLabel.textContent = routeParts[0] + ' → ' + routeParts[routeParts.length - 1];
            } else {
                routeLabel.textContent = routeParts.length >= 2 ? (routeParts[0] + ' → ' + routeParts[routeParts.length - 1]) : 'Add route';
            }
        }

        if (summary) {
            summary.textContent = metaParts.length ? metaParts.join(' · ') : 'Date · time';
        }

        var titleType = card.querySelector('[data-wsb-multi-trip-type-label]');
        if (titleType) {
            titleType.textContent = tripType === 'return' ? 'Return' : (tripType === 'charter' ? 'Charter' : 'One-way');
        }

        var header = card.querySelector('[data-wsb-multi-trip-icon]');
        if (header) {
            header.classList.toggle('wsb-charter-day-icon--plan-return', tripType === 'return');
            header.classList.toggle('wsb-charter-day-icon--plan-charter', tripType === 'charter');
            header.classList.toggle('wsb-charter-day-icon--plan-one-way', tripType === 'one_way');
        }
    }

    function syncMultiTripCardIndices(root) {
        var cards = getMultiTripContentCards(root);
        var selectedTripTypes = cards.map(function (card) {
            return getMultiTripTripType(card);
        });

        cards.forEach(function (card, index) {
            forEachNode(card.querySelectorAll('[data-wsb-multi-trip-trip-type-option]'), function (input) {
                input.name = 'wsb_multi_trip_reindex_' + index;
            });
        });

        cards.forEach(function (card, index) {
            var tripNumber = index + 1;
            var isCollapsed = card.classList.contains('wsb-booking-client-charter-day-card--collapsed');
            card.setAttribute('data-wsb-multi-trip-index', String(index));
            card.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');

            forEachNode(card.querySelectorAll('[data-wsb-multi-trip-toggle]'), function (toggle) {
                var actionLabel = isCollapsed ? 'Expand trip details' : 'Collapse trip details';
                toggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                if (toggle.classList.contains('wsb-icon-action--toggle')) {
                    toggle.setAttribute('aria-label', actionLabel);
                    toggle.setAttribute('data-wsb-action-tooltip', actionLabel);
                }
            });

            var numberLabel = card.querySelector('[data-wsb-multi-trip-number]');
            if (numberLabel) {
                numberLabel.textContent = 'Trip ' + tripNumber;
            }

            var indexedFields = {};
            forEachNode(card.querySelectorAll('[data-wsb-multi-trip-field]'), function (holder) {
                var fieldKey = trimValue(holder.getAttribute('data-wsb-multi-trip-field') || '');
                if (!fieldKey || indexedFields[fieldKey]) {
                    return;
                }
                var field = holder.matches('input, textarea, select') ? holder : holder.querySelector('input, textarea, select');
                if (!field) {
                    return;
                }
                indexedFields[fieldKey] = true;
                var previousControlId = field.id;
                var controlId = 'wsb-multi-trip-' + tripNumber + '-' + fieldKey.replace(/_/g, '-');
                field.id = controlId;
                field.name = 'trip_' + tripNumber + '_' + fieldKey;
                var label = holder.matches('input, textarea, select') && previousControlId ? card.querySelector('label[for="' + previousControlId + '"]') : holder.querySelector('label');
                if (label) {
                    label.setAttribute('for', controlId);
                }
                var datalist = holder.matches('.wsb-poi-field') ? holder.querySelector('datalist') : null;
                if (datalist) {
                    datalist.id = controlId + '-list';
                    // The POI lifecycle marker belongs to the wrapper. Testing
                    // the input here always looked unbound and restored the
                    // native datalist alongside the custom listbox.
                    if (holder.dataset.wsbPoiBound !== 'true') {
                        field.setAttribute('list', datalist.id);
                    } else {
                        field.removeAttribute('list');
                    }
                }
            });

            forEachNode(card.querySelectorAll('[data-wsb-multi-trip-trip-type-option]'), function (input) {
                input.name = 'trip_' + tripNumber + '_trip_type';
                input.checked = trimValue(input.value) === selectedTripTypes[index];
            });

            setMultiTripTripType(card, selectedTripTypes[index]);
        });

        ensureMultiTripTemplateLast(root);
        updateMultiTripButtons(root);
    }

    function addMultiTripCard(root) {
        var list = root.querySelector('[data-wsb-multi-trip-list]');
        if (!list) {
            return null;
        }

        var cards = getMultiTripContentCards(root);
        var templateSource = root.querySelector('[data-wsb-multi-trip-template="true"]') || (cards.length ? cards[cards.length - 1] : null);
        if (!templateSource) {
            return null;
        }

        var template = templateSource.cloneNode(true);
        prepareMultiTripClonedCard(template, root);

        if (templateSource && templateSource.getAttribute && templateSource.getAttribute('data-wsb-multi-trip-template') === 'true' && templateSource.parentNode === list) {
            list.insertBefore(template, templateSource);
        } else {
            list.appendChild(template);
        }
        ensureMultiTripTemplateLast(root);
        syncMultiTripCardIndices(root);
        clearMultiTripCardValues(template);
        refreshMultiTripCardSummary(template);
        initClockTimePicker(root);
        document.dispatchEvent(new CustomEvent('wsb:booking-builder:fields-added'));
        return template;
    }

    function hydrateMultiTripStop(card, toggleKey, fieldKey, leg) {
        var toggle = getMultiTripField(card, toggleKey);
        var field = getMultiTripField(card, fieldKey);
        var stop = leg && Array.isArray(leg.stops) && leg.stops.length ? leg.stops[0] : null;
        var enabled = Boolean(stop && stop.location && stop.location.label);
        if (toggle) {
            toggle.checked = enabled;
        }
        if (field) {
            field.value = enabled ? stop.location.label : '';
        }
        var section = field ? field.closest('[data-wsb-additional-stop-section]') : null;
        if (toggle && section) {
            updateAdditionalStop(toggle, section);
        }
        if (enabled && leg && leg.place_snapshots && Array.isArray(leg.place_snapshots.stops) && leg.place_snapshots.stops.length) {
            setMultiTripSnapshot(card, fieldKey, leg.place_snapshots.stops[0]);
        }
    }

    function hydrateMultiTripFromPayload(form, root, payload) {
        var itineraryTrips = payload && payload.itinerary && Array.isArray(payload.itinerary.trips) ? payload.itinerary.trips : [];
        var list = root.querySelector('[data-wsb-multi-trip-list]');
        if (!list) {
            return;
        }

        while (getMultiTripContentCards(root).length < itineraryTrips.length) {
            addMultiTripCard(root);
        }

        var cards = getMultiTripContentCards(root);
        cards.forEach(function (card, index) {
            var trip = itineraryTrips[index] || null;
            if (!trip) {
                clearMultiTripCardValues(card);
                card.setAttribute('data-wsb-multi-trip-visible', index === 0 ? 'true' : 'false');
                return;
            }

            card.setAttribute('data-wsb-multi-trip-visible', 'true');
            var tripType = trimValue(trip.trip_type || 'one_way') || 'one_way';
            setMultiTripTripType(card, tripType);
            var body = card.querySelector('[data-wsb-multi-trip-body]');
            if (body) {
                body.classList.remove('wsb-booking-client-hidden');
            }

            var leg = trip.legs && trip.legs.length ? trip.legs[0] : null;
            var fromLabel = trip.pickup && trip.pickup.address ? trip.pickup.address : (leg && leg.from && leg.from.label ? leg.from.label : '');
            var toLabel = trip.dropoff && trip.dropoff.address ? trip.dropoff.address : (leg && leg.to && leg.to.label ? leg.to.label : '');

            var dateField = getMultiTripField(card, 'pickup_date');
            var timeField = getMultiTripField(card, 'pickup_time');
            var fromField = getMultiTripField(card, 'from');
            var toField = getMultiTripField(card, 'to');
            var passengersField = getMultiTripField(card, 'passengers');
            var bagsField = getMultiTripField(card, 'bags');
            var notesField = getMultiTripField(card, 'notes');

            if (dateField) {
                dateField.value = trip.date || (leg && leg.pickup_date ? leg.pickup_date : '');
            }
            if (timeField) {
                timeField.value = trip.pickup_time || (leg && leg.pickup_time ? leg.pickup_time : '');
            }
            if (fromField) {
                fromField.value = fromLabel;
            }
            if (toField) {
                toField.value = toLabel;
            }
            if (passengersField) {
                passengersField.value = String(trip.passengers != null ? trip.passengers : 1);
            }
            if (bagsField) {
                bagsField.value = String(trip.bags != null ? trip.bags : 0);
            }
            if (notesField) {
                notesField.value = trip.notes || '';
            }

            if (leg && leg.place_snapshots) {
                if (leg.place_snapshots.from) {
                    setMultiTripSnapshot(card, 'from', leg.place_snapshots.from);
                }
                if (leg.place_snapshots.to) {
                    setMultiTripSnapshot(card, 'to', leg.place_snapshots.to);
                }
            }

            if (tripType === 'one_way' || tripType === 'return') {
                hydrateMultiTripStop(card, 'one_way_additional_stop_enabled', 'one_way_additional_stop', leg);
            }

            if (tripType === 'return') {
                var returnLeg = trip.legs && trip.legs.length > 1 ? trip.legs[1] : null;
                var returnFieldMap = {
                    return_return_from: returnLeg && returnLeg.from ? returnLeg.from.label || '' : '',
                    return_return_to: returnLeg && returnLeg.to ? returnLeg.to.label || '' : '',
                    return_return_pickup_date: returnLeg ? returnLeg.pickup_date || '' : '',
                    return_return_pickup_time: returnLeg ? returnLeg.pickup_time || '' : ''
                };
                Object.keys(returnFieldMap).forEach(function (fieldKey) {
                    var target = getMultiTripField(card, fieldKey);
                    if (target) {
                        target.value = returnFieldMap[fieldKey];
                    }
                });
                if (returnLeg && returnLeg.place_snapshots) {
                    setMultiTripSnapshot(card, 'return_return_from', returnLeg.place_snapshots.from);
                    setMultiTripSnapshot(card, 'return_return_to', returnLeg.place_snapshots.to);
                }
                hydrateMultiTripStop(card, 'return_return_additional_stop_enabled', 'return_return_additional_stop', returnLeg);
            } else if (tripType === 'charter') {
                var charterFieldMap = {
                    charter_pickup_date: trip.date || (leg ? leg.pickup_date || '' : ''),
                    charter_pickup_time: trip.pickup_time || (leg ? leg.pickup_time || '' : ''),
                    charter_dropoff_time: leg ? leg.dropoff_time || '' : '',
                    charter_pickup_location: fromLabel,
                    charter_dropoff_location: toLabel,
                    charter_poi: trip.poi || '',
                    charter_notes: trip.notes || ''
                };
                Object.keys(charterFieldMap).forEach(function (fieldKey) {
                    var target = getMultiTripField(card, fieldKey);
                    if (target) {
                        target.value = charterFieldMap[fieldKey];
                    }
                });
                if (leg && leg.place_snapshots) {
                    setMultiTripSnapshot(card, 'charter_pickup_location', leg.place_snapshots.from);
                    setMultiTripSnapshot(card, 'charter_dropoff_location', leg.place_snapshots.to);
                }
            }

            refreshMultiTripCardSummary(card);
        });

        syncMultiTripCardIndices(root);
        initClockTimePicker(root);
        initPoiFields(root, null);
    }

    function buildPayload(form, state) {
        var currentState = state || {};
        var root = form.closest('[data-wsb-booking-builder]') || null;
        var tripTypeFromForm = getFieldValue(form, 'input[name="trip_type"]:checked', 'one_way');
        var serviceGroup = trimValue(currentState.serviceGroup || (root ? root.dataset.wsbServiceGroup : '')) || 'transfer';
        var serviceType = trimValue(currentState.serviceType || (root ? root.dataset.wsbServiceType : '')) || 'city_transfer';
        var charterMode = trimValue(currentState.charterMode || getCharterModeValue(form) || 'same_day') || 'same_day';
        var itinerary = null;
        var sharedOptions = null;

        var tripType = serviceGroup === 'charter' ? 'charter' : tripTypeFromForm;
        var multiTripTrips = [];
        if (serviceGroup === 'charter') {
            serviceType = 'charter_hire';
        } else if (serviceGroup === 'plan') {
            sharedOptions = getMultiTripSharedValues(form);
            serviceGroup = 'transfer';
            serviceType = 'city_transfer';
            tripType = 'multi_trip';
            multiTripTrips = collectMultiTripTrips(root || form, sharedOptions);
            itinerary = {
                trips: multiTripTrips,
                shared_options: sharedOptions
            };
        }

        var passengers = tripType === 'multi_trip'
            ? (sharedOptions ? sharedOptions.passengers : 1)
            : getSharedNumberValue(form, serviceGroup, 'passengers', 1);
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
        } else if (tripType === 'multi_trip') {
            legs = collectMultiTripLegs(root || form);
        } else {
            legs.push(buildLeg(form, 'outbound'));
            if (tripType === 'return') {
                legs.push(buildLeg(form, 'return'));
            }
        }

        var quoteReady = true;
        legs.forEach(function (leg) {
            if (leg.place_snapshots && leg.place_snapshots.from) {
                if (!leg.place_snapshots.from.place_id || !leg.place_snapshots.from.lat || !leg.place_snapshots.from.lng || leg.place_snapshots.from.stale) {
                    quoteReady = false;
                }
            }
            if (leg.place_snapshots && leg.place_snapshots.to) {
                if (!leg.place_snapshots.to.place_id || !leg.place_snapshots.to.lat || !leg.place_snapshots.to.lng || leg.place_snapshots.to.stale) {
                    quoteReady = false;
                }
            }
            if (leg.place_snapshots && leg.place_snapshots.stops && leg.stops && leg.stops.length > 0) {
                leg.stops.forEach(function (stop, stopIndex) {
                    if (stop.location && stop.location.label) {
                        var stopSnap = leg.place_snapshots.stops[stopIndex];
                        if (!stopSnap || !stopSnap.place_id || stopSnap.stale) {
                            quoteReady = false;
                        }
                    }
                });
            }
        });

        if (serviceGroup === 'charter' && charterBlock.type === 'reserved') {
            charterBlock.days.forEach(function (day) {
                var daySnapshots = day.place_snapshots || {};

                if (day.pickup_location && day.pickup_location.label) {
                    if (!daySnapshots.from || !daySnapshots.from.place_id || !daySnapshots.from.lat || !daySnapshots.from.lng || daySnapshots.from.stale) {
                        quoteReady = false;
                    }
                }

                if (day.dropoff_location && day.dropoff_location.label) {
                    if (!daySnapshots.to || !daySnapshots.to.place_id || !daySnapshots.to.lat || !daySnapshots.to.lng || daySnapshots.to.stale) {
                        quoteReady = false;
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
            baby_seats: tripType === 'multi_trip' ? (sharedOptions ? sharedOptions.baby_seats : 0) : getSharedNumberValue(form, serviceGroup, 'baby_seats', 0),
            check_in_bags: tripType === 'multi_trip' ? (sharedOptions ? sharedOptions.check_in_bags : 0) : getSharedNumberValue(form, serviceGroup, 'check_in_bags', 0),
            carry_on_bags: tripType === 'multi_trip' ? (sharedOptions ? sharedOptions.carry_on_bags : 0) : getSharedNumberValue(form, serviceGroup, 'carry_on_bags', 0),
            add_ons: {
                trailer: tripType === 'multi_trip' ? Boolean(sharedOptions && sharedOptions.add_ons && sharedOptions.add_ons.trailer) : getSharedBooleanValue(form, serviceGroup, 'trailer'),
                oversize_luggage: tripType === 'multi_trip' ? Boolean(sharedOptions && sharedOptions.add_ons && sharedOptions.add_ons.oversize_luggage) : getSharedBooleanValue(form, serviceGroup, 'oversize_luggage')
            },
            legs: legs,
            itinerary: itinerary || undefined,
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
        statusElement.classList.remove('wsb-booking-client-preview-status--charter-days');

        var legCount = payload.legs ? payload.legs.length : 0;
        var itineraryTrips = payload.itinerary && Array.isArray(payload.itinerary.trips) ? payload.itinerary.trips.length : 0;
        var outboundStops = payload.legs && payload.legs[0] && payload.legs[0].stops && payload.legs[0].stops.length > 0;
        var returnStops = payload.legs && payload.legs[1] && payload.legs[1].stops && payload.legs[1].stops.length > 0;
        var stopLabel = outboundStops ? 'Additional stop: included' : (returnStops ? 'Additional stop: included' : 'Additional stop: not included');
        var charterLabel = '';
        if (payload.charter && payload.charter.enabled) {
            var charterDayCount = (payload.charter.days || []).length;
            charterLabel = payload.charter.type === 'reserved'
                ? ('Multi-day hire · ' + charterDayCount + ' day' + (charterDayCount === 1 ? '' : 's'))
                : 'Single-day hire';
        }
        var multiTripLabel = itineraryTrips > 0 ? ('Multi-trip itinerary · ' + itineraryTrips + ' trip' + (itineraryTrips === 1 ? '' : 's')) : '';
        var summary = [
            'Booking summary ready',
            'Service: ' + (payload.trip_type === 'multi_trip' ? 'Book full itinerary' : (payload.service_group === 'charter' ? 'Shuttle hire' : 'Book a ride')),
            'Trip: ' + (payload.trip_type === 'return' ? 'Return' : (payload.trip_type === 'multi_trip' ? 'Multi-trip' : 'One-way')),
            legCount + ' leg' + (legCount === 1 ? '' : 's'),
            stopLabel,
            'updated: ' + new Date().toLocaleTimeString()
        ];

        if (charterLabel) {
            summary.splice(4, 0, charterLabel);
        }
        if (multiTripLabel) {
            summary.splice(4, 0, multiTripLabel);
        }

        if (state && state.fixtureId) {
            summary.splice(2, 0, 'Fixture: ' + state.fixtureId);
        }

        var charterDays = payload.charter && payload.charter.type === 'reserved' && Array.isArray(payload.charter.days)
            ? payload.charter.days
            : [];
        if (!charterDays.length) {
            statusElement.textContent = summary.join(' · ');
            return;
        }

        statusElement.textContent = '';
        statusElement.classList.add('wsb-booking-client-preview-status--charter-days');
        var heading = document.createElement('strong');
        heading.className = 'wsb-charter-summary-heading';
        heading.textContent = 'Multi-day hire · ' + charterDays.length + ' day' + (charterDays.length === 1 ? '' : 's');
        statusElement.appendChild(heading);

        var list = document.createElement('ol');
        list.className = 'wsb-charter-summary-days';
        charterDays.forEach(function (day, index) {
            var item = document.createElement('li');
            item.className = 'wsb-charter-summary-day';
            var dayLabel = document.createElement('span');
            dayLabel.className = 'wsb-charter-summary-day__label';
            dayLabel.textContent = 'Day ' + (index + 1);
            var pickup = day && day.pickup_location ? trimValue(day.pickup_location.label || '') : '';
            var dropoff = day && day.dropoff_location ? trimValue(day.dropoff_location.label || '') : '';
            var poi = trimValue(day.poi_intent || '');
            var title = document.createElement('strong');
            title.className = 'wsb-charter-summary-day__destination';
            title.textContent = poi || shortLocationLabel(dropoff, '') || shortLocationLabel(pickup, '') || 'Day plan';

            var pickupRow = document.createElement('span');
            pickupRow.className = 'wsb-charter-summary-day__route';
            pickupRow.textContent = 'Pickup: ' + (shortLocationLabel(pickup, '') || 'Pending');
            var dropoffRow = document.createElement('span');
            dropoffRow.className = 'wsb-charter-summary-day__route';
            dropoffRow.textContent = 'Drop-off: ' + (shortLocationLabel(dropoff, '') || 'Pending');
            var schedule = document.createElement('span');
            schedule.className = 'wsb-charter-summary-day__schedule';
            schedule.textContent = [formatPlanDate(day.date || ''), [trimValue(day.start_time || ''), trimValue(day.end_time || '')].filter(Boolean).join('–')].filter(Boolean).join(' · ') || 'Date and time pending';

            item.appendChild(dayLabel);
            item.appendChild(title);
            item.appendChild(pickupRow);
            item.appendChild(dropoffRow);
            item.appendChild(schedule);
            list.appendChild(item);
        });
        statusElement.appendChild(list);
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

        var root = previewElement && previewElement.closest ? previewElement.closest('[data-wsb-booking-builder]') : null;
        if (root) {
            renderPlanSummaryPanel(root, payload);
        }

        renderPreviewSummary(statusElement, payload, state);

        if (messageElement) {
            messageElement.textContent = message || '';
        }

        logDebug('Booking Builder preview updated', payload);
    }

    function renderPlanSummaryPanel(root, payload) {
        if (!root) {
            return;
        }

        var summaryRoot = root.querySelector('[data-wsb-plan-summary]');
        var summaryList = root.querySelector('[data-wsb-plan-summary-list]');
        var summaryCount = root.querySelector('[data-wsb-plan-summary-count]');
        var summaryNote = root.querySelector('[data-wsb-plan-summary-note]');
        var trips = payload && payload.itinerary && Array.isArray(payload.itinerary.trips) ? payload.itinerary.trips : [];

        if (!summaryRoot || !summaryList || !summaryCount || !summaryNote) {
            return;
        }

        summaryCount.textContent = trips.length + ' item' + (trips.length === 1 ? '' : 's');
        summaryList.innerHTML = trips.map(function (trip, index) {
            var tripType = trimValue(trip.trip_type || 'one_way');
            var typeLabel = tripType === 'return' ? 'Return' : (tripType === 'charter' ? 'Charter' : 'One-way');
            var meta = [];
            var routes = [];
            var stopCount = Array.isArray(trip.stops) ? trip.stops.length : 0;

            if (tripType === 'charter') {
                if (trip.date) {
                    meta.push(formatPlanDate(trip.date));
                }
                if (trip.pickup_time && trip.legs && trip.legs[0] && trip.legs[0].dropoff_time) {
                    meta.push(trip.pickup_time + ' – ' + trip.legs[0].dropoff_time);
                } else if (trip.pickup_time) {
                    meta.push(trip.pickup_time);
                }
                routes.push(shortLocationLabel(trip.pickup && trip.pickup.place_snapshot ? trip.pickup.place_snapshot : null, trip.pickup && trip.pickup.address ? trip.pickup.address : ''));
                routes.push(shortLocationLabel(trip.dropoff && trip.dropoff.place_snapshot ? trip.dropoff.place_snapshot : null, trip.dropoff && trip.dropoff.address ? trip.dropoff.address : ''));
                if (trip.poi) {
                    meta.push(shortLocationLabel(trip.poi, trip.poi));
                }
            } else if (tripType === 'return') {
                var outbound = trip.legs && trip.legs[0] ? trip.legs[0] : null;
                var inbound = trip.legs && trip.legs[1] ? trip.legs[1] : null;
                routes.push(shortLocationLabel(outbound && outbound.from ? outbound.from : null, trip.pickup && trip.pickup.address ? trip.pickup.address : ''));
                routes.push(shortLocationLabel(outbound && outbound.to ? outbound.to : null, trip.dropoff && trip.dropoff.address ? trip.dropoff.address : ''));
                routes.push(shortLocationLabel(inbound && inbound.from ? inbound.from : null, trip.pickup && trip.pickup.address ? trip.pickup.address : ''));
                routes.push(shortLocationLabel(inbound && inbound.to ? inbound.to : null, trip.dropoff && trip.dropoff.address ? trip.dropoff.address : ''));
                var outboundMeta = [outbound && outbound.pickup_date ? formatPlanDate(outbound.pickup_date) : '', outbound && outbound.pickup_time ? outbound.pickup_time : ''].filter(Boolean).join(' · ');
                var inboundMeta = [inbound && inbound.pickup_date ? formatPlanDate(inbound.pickup_date) : '', inbound && inbound.pickup_time ? inbound.pickup_time : ''].filter(Boolean).join(' · ');
                if (outboundMeta) {
                    meta.push('Outbound ' + outboundMeta);
                }
                if (inboundMeta) {
                    meta.push('Return ' + inboundMeta);
                }
            } else {
                if (trip.date) {
                    meta.push(formatPlanDate(trip.date));
                }
                if (trip.pickup_time) {
                    meta.push(trip.pickup_time);
                }
                routes.push(shortLocationLabel(trip.pickup && trip.pickup.place_snapshot ? trip.pickup.place_snapshot : null, trip.pickup && trip.pickup.address ? trip.pickup.address : ''));
                routes.push(shortLocationLabel(trip.dropoff && trip.dropoff.place_snapshot ? trip.dropoff.place_snapshot : null, trip.dropoff && trip.dropoff.address ? trip.dropoff.address : ''));
            }

            routes = routes.filter(Boolean);
            if (stopCount > 0) {
                meta.push(stopCount + ' stop' + (stopCount === 1 ? '' : 's'));
            }

            return '' +
                '<article class="wsb-booking-client-plan-summary-card" data-wsb-plan-summary-card data-wsb-trip-type="' + escapeHtml(tripType) + '">' +
                    '<div class="wsb-booking-client-plan-summary-card__header">' +
                        '<span class="wsb-booking-client-plan-summary-card__icon" aria-hidden="true"></span>' +
                        '<div>' +
                            '<h5>' + escapeHtml('Trip ' + (index + 1) + ' — ' + typeLabel) + '</h5>' +
                            (meta.length ? '<p>' + escapeHtml(meta.join(' • ')) + '</p>' : '') +
                        '</div>' +
                        '<span class="wsb-booking-client-plan-summary-card__badge">' + escapeHtml(typeLabel) + '</span>' +
                    '</div>' +
                    '<div class="wsb-booking-client-plan-summary-card__route">' +
                        routes.map(function (route) {
                            return '<span>' + escapeHtml(route) + '</span>';
                        }).join('') +
                    '</div>' +
                '</article>';
        }).join('');

        // summaryNote.textContent = trips.length > 1 ? 'You can reorder trips by dragging them up or down.' : 'Add another trip to complete your itinerary.';
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
            planSection.classList.toggle('wsb-booking-client-hidden', serviceGroup !== 'plan' || !isFeatureGateEnabled('enable_multi_trip_bookings'));
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
        var currentState = root.__wsbBookingState || null;
        var sameDayPanel = root.querySelector('[data-wsb-charter-same-day-panel]');
        var shell = root.querySelector('[data-wsb-charter-multiday-shell]');
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
                seedMultiDayCharterFromSingleDay(form, root, currentState);
                var openedFirstVisibleCard = false;
                getCharterDayCards(root).forEach(function (card) {
                    if (card.getAttribute('data-wsb-charter-day-visible') === 'true') {
                        card.classList.remove('wsb-booking-client-hidden');
                    }
                    if (card.getAttribute('data-wsb-charter-day-visible') === 'true') {
                        if (!openedFirstVisibleCard) {
                            setCharterDayCardCollapsed(card, false);
                            openedFirstVisibleCard = true;
                        } else {
                            setCharterDayCardCollapsed(card, true);
                        }
                    } else {
                        setCharterDayCardCollapsed(card, true);
                    }
                    setCharterDayDefaults(card);
                });
                getCharterDayCards(root).forEach(function (card, index) {
                    refreshCharterDayCardTitle(card, index);
                });
            }
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
        currentState.tripType = trimValue(payload.trip_type || 'one_way');

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

        if (currentState.tripType === 'multi_trip' || (payload.itinerary && Array.isArray(payload.itinerary.trips))) {
            currentState.serviceGroup = 'plan';
            currentState.serviceType = 'multi_trip_plan';
            if (root) {
                root.dataset.wsbServiceGroup = currentState.serviceGroup;
                root.dataset.wsbServiceType = currentState.serviceType;
            }
            updateServiceMode(root, currentState.serviceGroup, currentState.charterMode);
            setRadioValue(form, 'trip_type', 'one_way');
            hydrateMultiTripFromPayload(form, root, payload);
        } else if (currentState.serviceGroup === 'charter') {
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

        var multiTripGated = root.querySelectorAll('[data-ws-feature-gate="enable_multi_trip_bookings"]');
        multiTripGated.forEach(function (el) {
            var enabled = isFeatureGateEnabled('enable_multi_trip_bookings');
            el.classList.toggle('wsb-booking-client-hidden', !enabled);
            if ('disabled' in el) {
                el.disabled = !enabled;
            }
        });

        var additionalStopSections = root.querySelectorAll('[data-wsb-additional-stop-section]');
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

    function getCurrentTimeString() {
        var now = new Date();
        var hours = String(now.getHours()).padStart(2, '0');
        var minutes = String(now.getMinutes()).padStart(2, '0');
        return hours + ':' + minutes;
    }

    function setDateDefaults(root) {
        var dateInputs = root.querySelectorAll('input[name$="_pickup_date"], input[name$="-pickup-date"]');
        forEachNode(dateInputs, function (input) {
            if (input && !input.value) {
                input.value = getTomorrowDateString();
            }
        });
    }

    function setCharterTimeDefaults(root) {
        var panel = root.querySelector('[data-wsb-charter-same-day-panel]') || root;
        var pickupTime = getField(panel, 'input[name="charter_pickup_time"]');
        var dropoffTime = getField(panel, 'input[name="charter_dropoff_time"]');

        if (pickupTime && !pickupTime.value) {
            pickupTime.value = '08:00';
        }
        if (dropoffTime && !dropoffTime.value) {
            dropoffTime.value = '17:00';
        }
    }

    function setTransferTimeDefaults(root) {
        var defaultTime = getCurrentTimeString();
        var outboundPickupTime = getField(root, 'input[name="outbound_pickup_time"]');
        var returnPickupTime = getField(root, 'input[name="return_pickup_time"]');

        if (outboundPickupTime && !trimValue(outboundPickupTime.value)) {
            outboundPickupTime.value = defaultTime;
        }
        if (returnPickupTime && !trimValue(returnPickupTime.value)) {
            returnPickupTime.value = defaultTime;
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

    function getCharterDayDisplayLabel(card) {
        if (!card) {
            return '';
        }

        var poi = getCharterDayField(card, 'poi_intent');
        var pickup = getCharterDayField(card, 'pickup_location');
        var dropoff = getCharterDayField(card, 'dropoff_location');
        var preferred = shortLocationLabel(poi && poi.value ? poi.value : '', '');

        if (!preferred) {
            preferred = shortLocationLabel(dropoff && dropoff.value ? dropoff.value : '', '');
        }

        if (!preferred) {
            preferred = shortLocationLabel(pickup && pickup.value ? pickup.value : '', '');
        }

        return preferred;
    }

    function refreshCharterDayCardTitle(card, index) {
        if (!card) {
            return;
        }

        var label = card.querySelector('.wsb-booking-client-eyebrow');
        var title = card.querySelector('.wsb-charter-day-title strong');
        var route = card.querySelector('[data-wsb-day-route-label]');
        var summary = card.querySelector('[data-wsb-day-summary]');
        var pickup = getCharterDayField(card, 'pickup_location');
        var dropoff = getCharterDayField(card, 'dropoff_location');
        var date = getCharterDayField(card, 'date');
        var start = getCharterDayField(card, 'start_time');
        var end = getCharterDayField(card, 'end_time');
        var routeLabel = getCharterDayDisplayLabel(card);

        if (label) {
            label.textContent = 'Day ' + (index + 1);
        }

        if (route) {
            route.textContent = routeLabel || 'Add route';
        }

        if (title) {
            var titleRoute = routeLabel || 'Add route';
            if (title.firstChild && title.firstChild.nodeType === Node.TEXT_NODE) {
                title.firstChild.nodeValue = 'Day ' + (index + 1) + ' — ';
            } else if (!route) {
                title.textContent = 'Day ' + (index + 1) + ' — ' + titleRoute;
            }
        }

        if (summary) {
            var summaryDate = date && date.value ? formatPlanDate(date.value) : 'Date';
            var summaryStart = start && start.value ? start.value : 'start time';
            var summaryEnd = end && end.value ? end.value : 'end time';
            summary.textContent = summaryDate + ' • ' + summaryStart + ' – ' + summaryEnd;
        }
    }

    function seedMultiDayCharterFromSingleDay(form, root, state) {
        if (!form || !root || root.dataset.wsbCharterMultiDaySeeded === 'true' || (state && state.charterMultiDaySeeded)) {
            return;
        }

        var cards = getCharterDayCards(root);
        var firstCard = cards.length ? cards[0] : null;
        var singleDayPanel = root.querySelector('[data-wsb-charter-same-day-panel]') || form;
        if (!firstCard) {
            if (state) {
                state.charterMultiDaySeeded = true;
            }
            root.dataset.wsbCharterMultiDaySeeded = 'true';
            return;
        }

        setInputValue(firstCard, 'charter_day_date', getFieldValue(singleDayPanel, 'input[name="outbound_pickup_date"]', ''));
        setInputValue(firstCard, 'charter_day_start_time', getFieldValue(form, 'input[name="charter_pickup_time"]', ''));
        setInputValue(firstCard, 'charter_day_end_time', getFieldValue(form, 'input[name="charter_dropoff_time"]', ''));
        setInputValue(firstCard, 'charter_day_pickup_location', getFieldValue(form, 'input[name="charter_pickup_location"]', ''));
        setInputValue(firstCard, 'charter_day_dropoff_location', getFieldValue(form, 'input[name="charter_dropoff_location"]', ''));
        setInputValue(firstCard, 'charter_day_poi', getFieldValue(form, 'input[name="charter_poi"]', ''));
        setInputValue(firstCard, 'charter_day_notes', getFieldValue(form, 'textarea[name="charter_notes"]', ''));
        setCharterDaySnapshot(firstCard, 'pickup_location', placeSnapshots.charter_pickup_location || PLACE_SNAPSHOT_EMPTY);
        setCharterDaySnapshot(firstCard, 'dropoff_location', placeSnapshots.charter_dropoff_location || PLACE_SNAPSHOT_EMPTY);
        setCharterDayDefaults(firstCard);

        if (state) {
            state.charterMultiDaySeeded = true;
        }
        root.dataset.wsbCharterMultiDaySeeded = 'true';
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

    function initHelpIcons(root) {
        if (!root || root.dataset.wsbHelpIconsBound === 'true') {
            return;
        }

        root.dataset.wsbHelpIconsBound = 'true';
        root.addEventListener('click', function (event) {
            var button = event.target && event.target.closest ? event.target.closest('[data-wsb-help-icon]') : null;
            if (!button || !root.contains(button)) {
                return;
            }

            var shouldOpen = button.getAttribute('aria-expanded') !== 'true';
            forEachNode(root.querySelectorAll('[data-wsb-help-icon].is-open'), function (openButton) {
                if (openButton !== button) {
                    openButton.classList.remove('is-open');
                    openButton.setAttribute('aria-expanded', 'false');
                }
            });
            button.classList.toggle('is-open', shouldOpen);
            button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            event.preventDefault();
        });

        root.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            forEachNode(root.querySelectorAll('[data-wsb-help-icon].is-open'), function (button) {
                button.classList.remove('is-open');
                button.setAttribute('aria-expanded', 'false');
            });
        });
    }

    function initPoiFields(root, onChange) {
        forEachNode(root.querySelectorAll('.wsb-poi-field'), function (field) {
            if (field.dataset.wsbPoiBound === 'true') {
                return;
            }
            field.dataset.wsbPoiBound = 'true';
            var input = field.querySelector('input[type="text"]');
            var datalist = field.querySelector('datalist');
            var clearButton = field.querySelector('[data-wsb-poi-clear]');
            if (!input || !datalist) {
                return;
            }

            var choices = Array.prototype.map.call(datalist.querySelectorAll('option'), function (option) {
                return trimValue(option.value);
            }).filter(Boolean);
            input.removeAttribute('list');
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-autocomplete', 'list');
            input.setAttribute('aria-expanded', 'false');

            var listbox = document.createElement('div');
            listbox.className = 'wsb-poi-suggestions';
            listbox.setAttribute('role', 'listbox');
            listbox.id = input.id + '-suggestions';
            input.setAttribute('aria-controls', listbox.id);
            (input.closest('.wsb-poi-control') || field).appendChild(listbox);

            function renderChoices() {
                var query = trimValue(input.value).toLowerCase();
                listbox.textContent = '';
                choices.filter(function (choice) {
                    return !query || choice.toLowerCase().indexOf(query) !== -1;
                }).forEach(function (choice) {
                    var option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'wsb-poi-suggestion';
                    option.setAttribute('role', 'option');
                    option.textContent = choice;
                    option.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                    });
                    option.addEventListener('click', function () {
                        input.value = choice;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        closeChoices();
                    });
                    listbox.appendChild(option);
                });
            }

            function openChoices() {
                renderChoices();
                field.classList.add('is-poi-open');
                input.setAttribute('aria-expanded', 'true');
            }

            function closeChoices() {
                field.classList.remove('is-poi-open');
                input.setAttribute('aria-expanded', 'false');
            }

            listbox.addEventListener('wheel', function (event) {
                if (!field.classList.contains('is-poi-open') || listbox.scrollHeight <= listbox.clientHeight) {
                    return;
                }
                listbox.scrollTop += event.deltaY;
                event.preventDefault();
                event.stopPropagation();
            }, { passive: false });

            input.addEventListener('focus', openChoices);
            input.addEventListener('click', openChoices);
            input.addEventListener('input', function () {
                openChoices();
                if (typeof onChange === 'function') {
                    onChange('');
                }
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeChoices();
                }
            });
            input.addEventListener('blur', function () {
                window.setTimeout(function () {
                    if (!field.contains(document.activeElement)) {
                        closeChoices();
                    }
                }, 100);
            });
            document.addEventListener('pointerdown', function (event) {
                if (!field.contains(event.target)) {
                    closeChoices();
                }
            });
            if (clearButton) {
                clearButton.addEventListener('click', function () {
                    input.value = '';
                    input.focus();
                    openChoices();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
        });
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

        var multiTripHolder = input.closest('[data-wsb-multi-trip-field]');
        var multiTripCard = input.closest('[data-wsb-multi-trip-card]');
        if (multiTripHolder && multiTripCard && input.closest('.wsb-booking-client-field--location')) {
            var multiTripFieldKey = trimValue(multiTripHolder.getAttribute('data-wsb-multi-trip-field') || '');
            return {
                selector: '',
                snapshotKey: function (target) {
                    return getMultiTripSnapshotKey(target.closest('[data-wsb-multi-trip-card]'), multiTripFieldKey);
                },
                routeRole: trimValue(input.getAttribute('data-ws-route-role') || multiTripHolder.getAttribute('data-ws-route-role') || ''),
                placeRole: trimValue(input.getAttribute('data-ws-place-role') || multiTripHolder.getAttribute('data-ws-place-role') || '')
            };
        }

        var fieldMap = [
            { selector: 'input[name="outbound_from"]', snapshotKey: 'outbound_from', routeRole: 'origin', placeRole: 'origin' },
            { selector: 'input[name="outbound_to"]', snapshotKey: 'outbound_to', routeRole: 'destination', placeRole: 'destination' },
            { selector: 'input[name="outbound_additional_stop"]', snapshotKey: 'outbound_additional_stop', routeRole: 'stop', placeRole: 'outbound_stop' },
            { selector: 'input[name="return_from"]', snapshotKey: 'return_from', routeRole: 'return_origin', placeRole: 'return_origin' },
            { selector: 'input[name="return_to"]', snapshotKey: 'return_to', routeRole: 'return_destination', placeRole: 'return_destination' },
            { selector: 'input[name="return_additional_stop"]', snapshotKey: 'return_additional_stop', routeRole: 'stop', placeRole: 'return_stop' },
            { selector: 'input[name="one_way_from"]', snapshotKey: 'one_way_from', routeRole: 'origin', placeRole: 'origin' },
            { selector: 'input[name="one_way_to"]', snapshotKey: 'one_way_to', routeRole: 'destination', placeRole: 'destination' },
            { selector: 'input[name="one_way_additional_stop"]', snapshotKey: 'one_way_additional_stop', routeRole: 'stop', placeRole: 'one_way_stop' },
            { selector: 'input[name="return_outbound_from"]', snapshotKey: 'return_outbound_from', routeRole: 'return_origin', placeRole: 'return_origin' },
            { selector: 'input[name="return_outbound_to"]', snapshotKey: 'return_outbound_to', routeRole: 'return_destination', placeRole: 'return_destination' },
            { selector: 'input[name="return_outbound_additional_stop"]', snapshotKey: 'return_outbound_additional_stop', routeRole: 'stop', placeRole: 'return_outbound_stop' },
            { selector: 'input[name="return_return_from"]', snapshotKey: 'return_return_from', routeRole: 'return_origin', placeRole: 'return_origin' },
            { selector: 'input[name="return_return_to"]', snapshotKey: 'return_return_to', routeRole: 'return_destination', placeRole: 'return_destination' },
            { selector: 'input[name="return_return_additional_stop"]', snapshotKey: 'return_return_additional_stop', routeRole: 'stop', placeRole: 'return_return_stop' },
            { selector: 'input[name="charter_pickup_location"]', snapshotKey: 'charter_pickup_location', routeRole: 'charter_origin', placeRole: 'charter_origin' },
            { selector: 'input[name="charter_dropoff_location"]', snapshotKey: 'charter_dropoff_location', routeRole: 'charter_destination', placeRole: 'charter_destination' },
            { selector: 'input[name="charter_day_pickup_location"]', snapshotKey: function (target) { return getCharterDaySnapshotKey(target.closest('[data-wsb-charter-day-card]'), 'pickup_location'); }, routeRole: 'charter_day_origin', placeRole: 'charter_day_origin' },
            { selector: 'input[name="charter_day_dropoff_location"]', snapshotKey: function (target) { return getCharterDaySnapshotKey(target.closest('[data-wsb-charter-day-card]'), 'dropoff_location'); }, routeRole: 'charter_day_destination', placeRole: 'charter_day_destination' },
            { selector: 'input[data-wsb-multi-trip-field="from"]', snapshotKey: function (target) { return getMultiTripSnapshotKey(target.closest('[data-wsb-multi-trip-card]'), 'from'); }, routeRole: 'multi_trip_origin', placeRole: 'multi_trip_origin' },
            { selector: 'input[data-wsb-multi-trip-field="to"]', snapshotKey: function (target) { return getMultiTripSnapshotKey(target.closest('[data-wsb-multi-trip-card]'), 'to'); }, routeRole: 'multi_trip_destination', placeRole: 'multi_trip_destination' },
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

        if (input.name === 'charter_day_pickup_location' || input.name === 'charter_day_dropoff_location') {
            var card = input.closest('[data-wsb-charter-day-card]');
            var fieldAttr = input.name === 'charter_day_pickup_location' ? 'pickup_location' : 'dropoff_location';
            return card && fieldAttr ? getCharterDaySnapshotKey(card, fieldAttr) : (input.getAttribute('name') || '');
        }

        var multiTripHolder = input.closest('[data-wsb-multi-trip-field]');
        if (multiTripHolder) {
            var tripCard = input.closest('[data-wsb-multi-trip-card]');
            var tripFieldAttr = multiTripHolder.getAttribute('data-wsb-multi-trip-field');
            return tripCard && tripFieldAttr ? getMultiTripSnapshotKey(tripCard, tripFieldAttr) : (input.getAttribute('name') || '');
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
                btn.setAttribute('data-wsb-action-tooltip', 'Current location unavailable in this browser');
                return;
            }

            btn.addEventListener('click', function () {
                if (!navigator.geolocation || typeof google === 'undefined' || !google.maps || typeof google.maps.Geocoder !== 'function') {
                    return;
                }

                var originalLabel = btn.getAttribute('aria-label') || 'Use my current location';
                var snapshotKey = getLocationSnapshotKey(input);

                btn.disabled = true;
                btn.classList.add('wsb-booking-client-place-current--loading');
                btn.setAttribute('aria-label', 'Fetching current location');
                btn.setAttribute('data-wsb-action-tooltip', 'Fetching current location');

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
                        btn.setAttribute('data-wsb-action-tooltip', originalLabel);

                        if (typeof refreshCallback === 'function') {
                            refreshCallback('');
                        }
                    });
                }, function () {
                    btn.disabled = false;
                    btn.classList.remove('wsb-booking-client-place-current--loading');
                    btn.setAttribute('aria-label', originalLabel);
                    btn.setAttribute('data-wsb-action-tooltip', originalLabel);
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
                var snapshotKey = getLocationSnapshotKey(input);
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
        // Added/duplicated cards contain new buttons even when delegated Places
        // focus handling is already active on the root. These binders are
        // idempotent per button, so always let them discover new controls.
        initCurrentLocationButtons(root, refreshCallback);
        initClearButtons(root, refreshCallback);

        if (!GOOGLE_PLACES.enabled || typeof google === 'undefined' || typeof google.maps === 'undefined' || typeof google.maps.places === 'undefined') {
            if (root && root.dataset && root.dataset.wsbPlacesDelegated !== 'true') {
                root.dataset.wsbPlacesDelegated = 'true';
            }
            return;
        }

        if (root && root.dataset && root.dataset.wsbPlacesDelegated !== 'true') {
            root.dataset.wsbPlacesDelegated = 'true';

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
        var selector = 'input[name="outbound_from"], input[name="outbound_to"], input[name="outbound_additional_stop"], input[name="return_from"], input[name="return_to"], input[name="return_additional_stop"], input[name="charter_pickup_location"], input[name="charter_dropoff_location"], input[name="charter_day_pickup_location"], input[name="charter_day_dropoff_location"], input[data-wsb-multi-trip-field], [data-wsb-multi-trip-field] input[type="text"]';
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
            charterMultiDaySeeded: root.dataset.wsbCharterMultiDaySeeded === 'true',
            fixtureId: '',
            fixtureExpected: ''
        };
        root.__wsbBookingState = state;
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
            refreshCharterDayOrderLabels();
            getMultiTripCards(root).forEach(function (card) {
                refreshMultiTripCardSummary(card);
            });
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

            var actionButton = target.closest('[data-wsb-charter-add-day], [data-wsb-charter-day-toggle], [data-wsb-charter-day-duplicate], [data-wsb-charter-day-delete]');
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
                    var dayList = nextCard.closest('[data-wsb-charter-day-list]');
                    if (dayList) {
                        dayList.appendChild(nextCard);
                    }
                    setCharterDayCardVisible(nextCard, true);
                    clearCharterDayCardValues(nextCard);
                    setCharterDayDefaults(nextCard);
                    collapseOtherCharterDayCards(root, nextCard);
                    setCharterDayCardCollapsed(nextCard, false);
                    updateCharterDayButtons(root);
                    initGooglePlacesAutocomplete(root, refreshPreview);
                    initClockTimePicker(root);
                    refreshCharterDayOrderLabels();
                    refreshPreview('');
                }
                event.preventDefault();
                return;
            }

            var card = actionButton.closest('[data-wsb-charter-day-card]');
            if (!card) {
                return;
            }

            if (actionButton.hasAttribute('data-wsb-charter-day-toggle')) {
                var shouldOpen = card.getAttribute('data-wsb-charter-day-collapsed') === 'true';
                if (shouldOpen) {
                    collapseOtherCharterDayCards(root, card);
                }
                setCharterDayCardCollapsed(card, !shouldOpen ? true : false);
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
                    card.parentNode.insertBefore(targetCard, card.nextSibling);
                    copyCharterDayCardValues(card, targetCard);
                    setCharterDayCardVisible(targetCard, true);
                    setCharterDayCardCollapsed(targetCard, false);
                    collapseOtherCharterDayCards(root, targetCard);
                    updateCharterDayButtons(root);
                    initGooglePlacesAutocomplete(root, refreshPreview);
                    initClockTimePicker(root);
                    refreshCharterDayOrderLabels();
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
                var remainingCards = getVisibleCharterDayCards(root);
                if (remainingCards.length) {
                    collapseOtherCharterDayCards(root, remainingCards[0]);
                    setCharterDayCardCollapsed(remainingCards[0], false);
                }
                updateCharterDayButtons(root);
                initGooglePlacesAutocomplete(root, refreshPreview);
                initClockTimePicker(root);
                refreshCharterDayOrderLabels();
                refreshPreview('');
                event.preventDefault();
            }
        }

        function handleMultiTripAction(event) {
            var target = event.target;
            if (!target || !target.closest) {
                return false;
            }

            var actionButton = target.closest('[data-wsb-multi-trip-add], [data-wsb-multi-trip-copy], [data-wsb-multi-trip-remove], [data-wsb-multi-trip-toggle], [data-wsb-multi-trip-return-toggle]');
            if (!actionButton) {
                return false;
            }

            if (actionButton.hasAttribute('data-wsb-multi-trip-add')) {
                var newCard = addMultiTripCard(root);
                if (newCard) {
                    collapseOtherMultiTripCards(root, newCard);
                    setMultiTripCardCollapsed(newCard, false);
                    refreshPreview('');
                    initGooglePlacesAutocomplete(root, refreshPreview);
                    initClockTimePicker(root);
                    initPoiFields(root, refreshPreview);
                    syncNativeMultiTripDragDrop();
                }
                event.preventDefault();
                return true;
            }

            var card = actionButton.closest('[data-wsb-multi-trip-card]');
            if (!card) {
                return false;
            }

            if (actionButton.hasAttribute('data-wsb-multi-trip-return-toggle')) {
                var returnBody = card.querySelector('[data-wsb-multi-trip-return-body]');
                var returnOpen = actionButton.getAttribute('aria-expanded') !== 'true';
                actionButton.setAttribute('aria-expanded', returnOpen ? 'true' : 'false');
                if (returnBody) {
                    returnBody.classList.toggle('wsb-booking-client-hidden', !returnOpen);
                }
                event.preventDefault();
                return true;
            }

            if (actionButton.hasAttribute('data-wsb-multi-trip-copy')) {
                duplicateMultiTripCard(root, card, refreshPreview, syncNativeMultiTripDragDrop);
                event.preventDefault();
                return true;
            }

            if (actionButton.hasAttribute('data-wsb-multi-trip-toggle')) {
                var shouldOpen = card.classList.contains('wsb-booking-client-charter-day-card--collapsed');
                if (shouldOpen) {
                    collapseOtherMultiTripCards(root, card);
                }
                setMultiTripCardCollapsed(card, !shouldOpen);
                syncMultiTripCardIndices(root);
                refreshPreview('');
                event.preventDefault();
                return true;
            }

            if (actionButton.hasAttribute('data-wsb-multi-trip-remove')) {
                var visibleCards = getVisibleMultiTripCards(root);
                if (visibleCards.length <= 1) {
                    updateMultiTripButtons(root);
                    event.preventDefault();
                    return true;
                }

                card.remove();
                var remainingCards = getVisibleMultiTripCards(root);
                if (remainingCards.length && remainingCards.every(function (remaining) { return remaining.classList.contains('wsb-booking-client-charter-day-card--collapsed'); })) {
                    setMultiTripCardCollapsed(remainingCards[0], false);
                }
                syncMultiTripCardIndices(root);
                refreshPreview('');
                initGooglePlacesAutocomplete(root, refreshPreview);
                initClockTimePicker(root);
                syncNativeMultiTripDragDrop();
                event.preventDefault();
                return true;
            }

            return false;
        }


        function handleAdditionalStopRemove(event) {
            var button = event.target && event.target.closest ? event.target.closest('[data-wsb-additional-stop-remove]') : null;
            if (!button) {
                return false;
            }
            var section = button.closest('[data-wsb-additional-stop-section]');
            var toggleLabel = section ? section.previousElementSibling : null;
            var toggle = toggleLabel ? toggleLabel.querySelector('.wsb-booking-client-additional-toggle') : null;
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
            getCharterDayCards(root).forEach(function (card) {
                if (card.getAttribute('data-wsb-charter-day-visible') !== 'true' || card.classList.contains('wsb-booking-client-hidden')) {
                    card.setAttribute('data-wsb-charter-day-slot', 'hidden-' + trimValue(card.getAttribute('data-wsb-charter-day-id') || 'day'));
                }
            });
            getVisibleCharterDayCards(root).forEach(function (card, index) {
                card.setAttribute('data-wsb-charter-day-slot', String(index + 1));
                refreshCharterDayCardTitle(card, index);
            });
        }

        var charterDaySortable = null;
        var charterDayDragEnabled = false;
        var charterDayDragListenersBound = false;
        var charterDaySlotTimes = [];

        function captureCharterDaySlotTimes() {
            charterDaySlotTimes = getVisibleCharterDayCards(root).slice().sort(function (left, right) {
                return parseInt(left.getAttribute('data-wsb-charter-day-slot') || '0', 10)
                    - parseInt(right.getAttribute('data-wsb-charter-day-slot') || '0', 10);
            }).map(function (card) {
                return {
                    date: getCharterDayField(card, 'date').value,
                    start: getCharterDayField(card, 'start_time').value,
                    end: getCharterDayField(card, 'end_time').value
                };
            });
        }

        function restoreCharterDaySlotTimes() {
            var visibleCards = getVisibleCharterDayCards(root);
            var scheduleBySlot = {};
            visibleCards.forEach(function (card) {
                var slotNumber = parseInt(card.getAttribute('data-wsb-charter-day-slot') || '0', 10);
                if (!slotNumber) {
                    return;
                }
                scheduleBySlot[slotNumber] = {
                    date: getCharterDayField(card, 'date').value,
                    start: getCharterDayField(card, 'start_time').value,
                    end: getCharterDayField(card, 'end_time').value
                };
            });
            visibleCards.forEach(function (card, index) {
                var slot = charterDaySlotTimes[index] || scheduleBySlot[index + 1];
                if (!slot) {
                    return;
                }
                getCharterDayField(card, 'date').value = slot.date;
                getCharterDayField(card, 'start_time').value = slot.start;
                getCharterDayField(card, 'end_time').value = slot.end;
            });
        }

        function releaseCharterDaySlotTimes() {
            window.setTimeout(function () {
                charterDaySlotTimes = [];
            }, 0);
        }

        function clearCharterDropIndicators(list) {
            forEachNode(list.querySelectorAll('.wsb-sortable-drop-before, .wsb-sortable-drop-after'), function (card) {
                card.classList.remove('wsb-sortable-drop-before', 'wsb-sortable-drop-after');
            });
        }

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
                    if (list.dataset.wsbCharterPointerSnapshotBound !== 'true') {
                        list.dataset.wsbCharterPointerSnapshotBound = 'true';
                        list.addEventListener('pointerdown', function (event) {
                            var handle = event.target && event.target.closest ? event.target.closest('[data-wsb-drag-handle]') : null;
                            if (handle && list.contains(handle)) {
                                captureCharterDaySlotTimes();
                            }
                        }, true);
                    }
                    forEachNode(list.querySelectorAll('[data-wsb-drag-handle]'), function (handle) {
                        handle.removeAttribute('draggable');
                    });
                    charterDaySortable = new window.Sortable(list, {
                        animation: 120,
                        draggable: '.wsb-charter-day-card:not(.wsb-booking-client-hidden)',
                        handle: '[data-wsb-drag-handle]',
                        ghostClass: 'wsb-sortable-placeholder',
                        chosenClass: 'wsb-sortable-chosen',
                        onStart: function () {
                            captureCharterDaySlotTimes();
                            document.documentElement.classList.add('wsb-sortable-is-dragging');
                        },
                        onMove: function (event) {
                            clearCharterDropIndicators(list);
                            if (event.related) {
                                event.related.classList.add(event.willInsertAfter ? 'wsb-sortable-drop-after' : 'wsb-sortable-drop-before');
                            }
                        },
                        onEnd: function () {
                            document.documentElement.classList.remove('wsb-sortable-is-dragging');
                            clearCharterDropIndicators(list);
                            restoreCharterDaySlotTimes();
                            refreshCharterDayOrderLabels();
                            document.dispatchEvent(new CustomEvent('wsb:charter-days-updated'));
                            refreshPreview('');
                            releaseCharterDaySlotTimes();
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
                if (charterDaySortable) {
                    draggedCard = null;
                    return;
                }
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
                captureCharterDaySlotTimes();
                document.documentElement.classList.add('wsb-sortable-is-dragging');
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', draggedCard.getAttribute('data-wsb-charter-day-id') || '');
                }
            });

            list.addEventListener('dragover', function (event) {
                if (charterDaySortable) {
                    return;
                }
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
                clearCharterDropIndicators(list);
                targetCard.classList.add(insertAfter ? 'wsb-sortable-drop-after' : 'wsb-sortable-drop-before');
                list.insertBefore(draggedCard, insertAfter ? targetCard.nextSibling : targetCard);
            });

            list.addEventListener('dragend', function () {
                if (charterDaySortable) {
                    draggedCard = null;
                    return;
                }
                if (!draggedCard) {
                    return;
                }

                draggedCard.classList.remove('wsb-sortable-chosen');
                draggedCard = null;
                document.documentElement.classList.remove('wsb-sortable-is-dragging');
                clearCharterDropIndicators(list);
                restoreCharterDaySlotTimes();
                refreshCharterDayOrderLabels();
                updateCharterDayButtons(root);
                document.dispatchEvent(new CustomEvent('wsb:charter-days-updated'));
                refreshPreview('');
                releaseCharterDaySlotTimes();
            });
        }

        var multiTripSortable = null;
        var multiTripDragListenersBound = false;

        function destroyNativeMultiTripDragDrop() {
            if (multiTripSortable && typeof multiTripSortable.destroy === 'function') {
                try {
                    multiTripSortable.destroy();
                } catch (error) {
                    if (DEBUG) {
                        logDebug('Multi-trip sortable destroy failed', error);
                    }
                }
            }
            multiTripSortable = null;
        }

        function syncNativeMultiTripDragDrop() {
            var shell = root.querySelector('[data-wsb-multi-trip-shell]');
            var list = root.querySelector('[data-wsb-sortable-list="multi-trip-list"]');
            var isActive = Boolean(shell && !shell.classList.contains('wsb-booking-client-hidden') && list);

            if (!list || !isActive) {
                destroyNativeMultiTripDragDrop();
                return;
            }

            if (typeof window.Sortable === 'function') {
                if (multiTripSortable) {
                    return;
                }

                try {
                    multiTripSortable = new window.Sortable(list, {
                        animation: 120,
                        draggable: '.wsb-booking-client-multi-trip-card:not(.wsb-booking-client-hidden)',
                        handle: '[data-wsb-drag-handle]',
                        ghostClass: 'wsb-sortable-placeholder',
                        chosenClass: 'wsb-sortable-chosen',
                        onStart: function () {
                            document.documentElement.classList.add('wsb-sortable-is-dragging');
                        },
                        onEnd: function () {
                            document.documentElement.classList.remove('wsb-sortable-is-dragging');
                            syncMultiTripCardIndices(root);
                            refreshPreview('');
                        }
                    });
                    return;
                } catch (error) {
                    multiTripSortable = null;
                    if (DEBUG) {
                        logDebug('Multi-trip sortable initialisation failed, using keyboard fallback', error);
                    }
                }
            }

            if (multiTripDragListenersBound) {
                return;
            }
            multiTripDragListenersBound = true;

            list.addEventListener('keydown', function (event) {
                var handle = event.target && event.target.closest ? event.target.closest('[data-wsb-drag-handle]') : null;
                if (!handle) {
                    return;
                }

                if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
                    return;
                }

                var card = handle.closest('[data-wsb-multi-trip-card]');
                if (!card) {
                    return;
                }

                var sibling = event.key === 'ArrowUp' ? card.previousElementSibling : card.nextElementSibling;
                while (sibling && sibling.classList.contains('wsb-booking-client-hidden')) {
                    sibling = event.key === 'ArrowUp' ? sibling.previousElementSibling : sibling.nextElementSibling;
                }

                if (!sibling) {
                    return;
                }

                if (event.key === 'ArrowUp') {
                    list.insertBefore(card, sibling);
                } else {
                    list.insertBefore(sibling, card);
                }

                syncMultiTripCardIndices(root);
                refreshPreview('');
                event.preventDefault();
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
                    if (serviceGroup === 'plan' && !isFeatureGateEnabled('enable_multi_trip_bookings')) {
                        return;
                    }
                    state.serviceGroup = serviceGroup;
                    root.dataset.wsbServiceGroup = serviceGroup;
                    state.serviceType = serviceGroup === 'charter' ? 'charter_hire' : (serviceGroup === 'plan' ? 'multi_trip_plan' : 'city_transfer');
                    root.dataset.wsbServiceType = state.serviceType;
                    if (serviceGroup === 'charter') {
                        setCharterTimeDefaults(root);
                    } else {
                        clearCharterTimeDefaults(root);
                        setTransferTimeDefaults(root);
                    }
                    updateServiceMode(root, serviceGroup, state.charterMode);
                    syncNativeMultiTripDragDrop();
                    updateReturnVisibility(returnSection, tripTypeInputs);
                    initClockTimePicker(root);
                    initGooglePlacesAutocomplete(root, refreshPreview);
                    refreshPreview('');
                }
            });
        });

        root.addEventListener('click', function (event) {
            if (handleMultiTripAction(event) || handleAdditionalStopRemove(event) || handleReturnAccordionClick(event)) {
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
            if (target && target.classList && target.classList.contains('wsb-booking-client-additional-toggle')) {
                var toggleLabel = target.closest('.wsb-booking-client-additional-toggle-label');
                var stopSection = toggleLabel ? toggleLabel.nextElementSibling : null;
                if (stopSection && stopSection.hasAttribute('data-wsb-additional-stop-section')) {
                    updateAdditionalStop(target, stopSection);
                    initGooglePlacesAutocomplete(root, refreshPreview);
                }
            }
            if (target && target.name === 'charter_mode') {
                state.charterMode = getCharterModeValue(form);
                root.dataset.wsbCharterMode = state.charterMode;
                updateCharterMode(root, state.charterMode);
                initGooglePlacesAutocomplete(root, refreshPreview);
                initClockTimePicker(root);
            }
            if (target && target.hasAttribute && target.hasAttribute('data-wsb-multi-trip-trip-type-option')) {
                var tripCard = target.closest('[data-wsb-multi-trip-card]');
                if (tripCard) {
                    setMultiTripTripType(tripCard, trimValue(target.value) || 'one_way');
                }
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
        setTransferTimeDefaults(root);
        initNativeCharterDayDragDrop();
        syncNativeMultiTripDragDrop();
        refreshCharterDayOrderLabels();
        setDateDefaults(root);
        getCharterDayCards(root).forEach(function (card) {
            setCharterDayDefaults(card);
        });
        updateAmPmLabels(root);
        refreshPickerStatusMessages(root);
        root.__wsbSyncCharterDayDragDrop = syncNativeCharterDayDragDrop;
        root.__wsbSyncMultiTripDragDrop = syncNativeMultiTripDragDrop;
        syncNativeCharterDayDragDrop();
        syncNativeMultiTripDragDrop();
        initClockTimePicker(root);
        initHelpIcons(root);
        initPoiFields(root, refreshPreview);
        initGooglePlacesAutocomplete(root, refreshPreview);
        forEachNode(root.querySelectorAll('.wsb-booking-client-field--location input[type="text"]'), function (input) {
            updateLocationFieldState(input, !!input.value);
        });
        syncMultiTripCardIndices(root);
        updateMultiTripButtons(root);
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

    function getClockPopupForInput(input) {
        var wrapper = input && input.closest ? input.closest('.clock-timepicker') : null;
        var popup = wrapper ? wrapper.querySelector('.clock-timepicker-popup, .clock-timepicker-popover, .ui-clockpicker, .clockpicker-popover') : null;
        if (!popup && input && input.id) {
            forEachNode(document.querySelectorAll('[data-wsb-clock-owner]'), function (candidate) {
                if (!popup && candidate.getAttribute('data-wsb-clock-owner') === input.id) {
                    popup = candidate;
                }
            });
        }
        return popup && isElementVisible(popup) ? popup : null;
    }

    function clearClockOverlayState(root) {
        var context = root && root.querySelectorAll ? root : document;
        forEachNode(context.querySelectorAll('.wsb-clock-overlay-open'), function (node) {
            node.classList.remove('wsb-clock-overlay-open');
        });
        forEachNode(context.querySelectorAll('.wsb-timepicker-owner'), function (node) {
            node.classList.remove('wsb-timepicker-owner');
        });
        forEachNode(document.querySelectorAll('[data-wsb-clock-owner]'), function (popup) {
            if (!isElementVisible(popup) && popup._wsbClockOriginalParent) {
                popup._wsbClockOriginalParent.appendChild(popup);
                popup.removeAttribute('data-wsb-clock-owner');
                popup.classList.remove('wsb-clock-popup-positioned');
                popup.removeAttribute('data-wsb-placement');
                popup.style.removeProperty('top');
                popup.style.removeProperty('left');
                popup.style.removeProperty('max-width');
            }
        });
    }

    function reconcileClockOverlayState() {
        window.setTimeout(function () {
            if (activeClockInput && getClockPopupForInput(activeClockInput)) {
                positionClockPopup(activeClockInput);
                return;
            }
            activeClockInput = null;
            clearClockOverlayState(document);
        }, 0);
    }

    function activateClockOverlayState(input, popup) {
        clearClockOverlayState(document);
        var shell = input.closest('.wsb-booking-client-shell');
        var owner = input.closest('.wsb-booking-client-field');
        if (shell) {
            shell.classList.add('wsb-clock-overlay-open');
        }
        if (owner) {
            owner.classList.add('wsb-timepicker-owner');
        }
        popup.classList.add('wsb-clock-popup-positioned');
    }

    function positionClockPopup(input) {
        if (!input || !input.getBoundingClientRect) {
            return;
        }
        var popup = getClockPopupForInput(input);
        if (!popup) {
            clearClockOverlayState(document);
            return;
        }

        if (popup.parentNode !== document.body) {
            popup._wsbClockOriginalParent = popup.parentNode;
            popup.setAttribute('data-wsb-clock-owner', input.id || input.name || 'active-clock');
            if (popup.dataset.wsbClockPortalBound !== 'true') {
                popup.dataset.wsbClockPortalBound = 'true';
                popup.addEventListener('click', function (event) {
                    event.stopPropagation();
                    reconcileClockOverlayState();
                });
                popup.addEventListener('mousedown', function (event) {
                    event.stopPropagation();
                });
            }
            document.body.appendChild(popup);
        }

        activateClockOverlayState(input, popup);

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
            if (!activeClockInput || !getClockPopupForInput(activeClockInput)) {
                activeClockInput = null;
                clearClockOverlayState(root);
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
        if (activeClockInput && getClockPopupForInput(activeClockInput)) {
            scheduleClockPosition(document, activeClockInput);
        } else if (activeClockInput && !getClockPopupForInput(activeClockInput)) {
            activeClockInput = null;
            clearClockOverlayState(document);
        }
    });

    window.addEventListener('scroll', function () {
        if (activeClockInput && getClockPopupForInput(activeClockInput)) {
            scheduleClockPosition(document, activeClockInput);
        } else if (activeClockInput && !getClockPopupForInput(activeClockInput)) {
            activeClockInput = null;
            clearClockOverlayState(document);
        }
    }, { capture: true, passive: true });

    function initClockTimePicker(root) {
        if (!window.jQuery || !jQuery.fn.clockTimePicker) {
            return;
        }
        if (document.documentElement.dataset.wsbClockOverlayCleanupBound !== 'true') {
            document.documentElement.dataset.wsbClockOverlayCleanupBound = 'true';
            document.addEventListener('click', reconcileClockOverlayState);
            document.addEventListener('keyup', reconcileClockOverlayState);
            document.addEventListener('focusin', reconcileClockOverlayState);
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
