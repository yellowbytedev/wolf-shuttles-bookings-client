(function () {
    'use strict';

    // ONLY for local/test environment: bypass Google Places geocoding requirement
    // This file MUST NOT be loaded in production/staging
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    // Only activate in local environment with bypass flag
    var isLocalEnv = (window.WSB_BOOKING_CLIENT_FORM && 
                     window.WSB_BOOKING_CLIENT_FORM.testGeocodingBypass === true);

    if (!isLocalEnv) {
        return;
    }

    var DEBUG = window.location.search.indexOf('debug=1') !== -1;

    function logDebug() {
        if (!DEBUG || !window.console || !window.console.log) {
            return;
        }
        window.console.log('[WSB:TestGeocodingBypass]', arguments);
    }

    // Test location data (fake/test only - does NOT correspond to real places)
    var FAKE_LOCATION_DATA = {
        place_id: 'fake_test_place_id_ctpw',
        lat: -33.9249,
        lng: 18.4241,
        label: 'Cape Town (Test Location)',
        formatted_address: 'Cape Town, Western Cape, South Africa',
        name: 'Cape Town',
        town: 'Cape Town',
        neighborhood: ''
    };

    function populateHiddenFieldsFromFakeSelection() {
        var forms = document.querySelectorAll('form.bricks-form');
        forms.forEach(function (form) {
            // Check if this is the Cape Town point-to-point form
            var formIdField = form.querySelector('input[name="formId"]');
            var formId = formIdField ? formIdField.value : '';

            // Only apply to legacy forms (ifkszj = point-to-point, qlwoyv = charter)
            if (formId !== 'ifkszj' && formId !== 'qlwoyv') {
                return;
            }

            logDebug('Populating hidden fields for legacy form:', formId);

            // Populate outbound (one-way) fields
            var townOrigin = form.querySelector('input[name="town_origin"]');
            var neighborhoodOrigin = form.querySelector('input[name="neighborhood_origin"]');
            var originCoords = form.querySelector('input[name="origin_coords"]');
            var townDestination = form.querySelector('input[name="town_destination"]');
            var neighborhoodDestination = form.querySelector('input[name="neighborhood_destination"]');
            var destinationCoords = form.querySelector('input[name="destination_coords"]');
            var placeIds = form.querySelector('input[name="place_ids"]');
            var tripDistance = form.querySelector('input[name="trip_distance"]');

            // Populate with fake test data
            if (townOrigin) { townOrigin.value = FAKE_LOCATION_DATA.town || 'Cape Town'; }
            if (neighborhoodOrigin) { neighborhoodOrigin.value = FAKE_LOCATION_DATA.neighborhood || ''; }
            if (originCoords) { originCoords.value = FAKE_LOCATION_DATA.lat + ',' + FAKE_LOCATION_DATA.lng; }
            if (townDestination) { townDestination.value = FAKE_LOCATION_DATA.town || 'Cape Town'; }
            if (neighborhoodDestination) { neighborhoodDestination.value = FAKE_LOCATION_DATA.neighborhood || ''; }
            if (destinationCoords) { destinationCoords.value = FAKE_LOCATION_DATA.lat + ',' + FAKE_LOCATION_DATA.lng; }

            // Place IDs: "origin_place_id,destination_place_id"
            if (placeIds) { 
                placeIds.value = FAKE_LOCATION_DATA.place_id + ',' + FAKE_LOCATION_DATA.place_id; 
            }

            // Trip distance: a reasonable test value (e.g., 25km)
            if (tripDistance) { 
                tripDistance.value = '25.0'; 
            }

            // Dispatch change events to trigger any listeners
            [townOrigin, neighborhoodOrigin, originCoords, townDestination, 
             neighborhoodDestination, destinationCoords, placeIds, tripDistance]
                .forEach(function (field) {
                    if (field && field.value) {
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });

            logDebug('Hidden fields populated successfully');
        });
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', populateHiddenFieldsFromFakeSelection);
    } else {
        populateHiddenFieldsFromFakeSelection();
    }

    // Also expose for manual triggering
    window.WSB_test_populate_geocoding = populateHiddenFieldsFromFakeSelection;

})();