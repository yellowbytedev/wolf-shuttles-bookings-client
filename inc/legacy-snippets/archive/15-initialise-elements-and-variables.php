<?php
// <Internal Doc Start>
/*
*
* @description: Old version
* @tags: 
* @group: 
* @name: [OLD] Initialise elements and variables on booking form
* @type: js
* @status: draft
* @created_by: 
* @created_at: 
* @updated_at: 2025-01-24 11:20:33
* @is_valid: 
* @updated_by: 
* @priority: 10
* @run_at: wp_footer
* @load_as_file: 
* @condition: {"status":"no","run_if":"assertive","items":[[]]}
*/
?>
<?php if (!defined("ABSPATH")) { return;} // <Internal Doc End> ?>
// Centralized State Management
const state = {
    tripType: 'one-way', // Default trip type
    oneWay: {
        originPlaceId: null,
        destinationPlaceId: null,
    },
    roundTrip: {
        originPlaceId: null,
        destinationPlaceId: null,
    },
};

// Utility function to update the state
function updateState(field, placeId) {
    if (state.tripType === 'one-way') {
        state.oneWay[field] = placeId;
    } else if (state.tripType === 'roundtrip') {
        state.roundTrip[field] = placeId;
    }
    console.log('State updated:', state);
}

// Attach Google Autocomplete to Input Fields
function attachAutoComplete(inputField, fieldType) {
    const autocomplete = new google.maps.places.Autocomplete(inputField, {
        types: ['establishment', 'geocode'],
        componentRestrictions: { country: 'ZA' },
        fields: ['address_components', 'geometry', 'name', 'place_id'],
    });

    autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        if (!place.geometry) {
            console.error('No geometry for place:', place.name);
            return;
        }
        updateState(fieldType, place.place_id);
        triggerDistanceCalculation();
    });
}
// Utility function to calculate distance between two locations
async function calculateDistanceBetweenLocations(originPlaceId, destinationPlaceId) {
    try {
        const result = await getDistanceFromAPI(originPlaceId, destinationPlaceId);
        const parsedDistance = parseDistance(result.distance);
        return parsedDistance; // Return distance in km
    } catch (error) {
        console.error('Error calculating distance:', error);
        return null;
    }
}

// Validate Trip Logic
async function validateTripDistances() {
    const { tripType, oneWay, roundTrip } = state;
    const HQ_LAT = -33.9696; // Latitude of Cape Town International Airport
    const HQ_LNG = 18.5978; // Longitude of Cape Town International Airport
    const HQ_PLACE_ID = "ChIJvQtA90JFzB0RkF7P43l1SEA"; // Replace with your HQ place ID if available
    const maxRadius = 200; // Maximum allowable distance in km
    const zone10MaxRadius = 80; // Zone 10 distance in km

    let validationResults = {
        withinZone10: false, // Currently set to Zone 10 which is 80km max
        outsideMaxRadius: false,
        ineligibleTrip: false,
        distances: [],
    };

    try {
        if (tripType === 'one-way') {
            // One-way trip: Calculate distances for origin and destination
            const originDistance = await calculateDistanceBetweenLocations(HQ_PLACE_ID, oneWay.originPlaceId);
            const destinationDistance = await calculateDistanceBetweenLocations(HQ_PLACE_ID, oneWay.destinationPlaceId);
          validationResults.distances = [originDistance, destinationDistance];
        } else if (tripType === 'roundtrip') {
            // Roundtrip: Calculate distances for all four locations
            const originDistanceOutbound = await calculateDistanceBetweenLocations(HQ_PLACE_ID, roundTrip.originPlaceId);
            const destinationDistanceOutbound = await calculateDistanceBetweenLocations(HQ_PLACE_ID, roundTrip.destinationPlaceId);
            const originDistanceReturn = await calculateDistanceBetweenLocations(HQ_PLACE_ID, oneWay.originPlaceId);
            const destinationDistanceReturn = await calculateDistanceBetweenLocations(HQ_PLACE_ID, oneWay.destinationPlaceId);

             validationResults.distances = [
                originDistanceOutbound,
                destinationDistanceOutbound,
                originDistanceReturn,
                destinationDistanceReturn,
            ];
        }

        // Check if any location is within Zone 10
        validationResults.withinZone10 = validationResults.distances.some((distance) => distance <= zone10MaxRadius);

        // Check if any location exceeds the maximum allowable radius
        validationResults.outsideMaxRadius = validationResults.distances.some((distance) => distance > maxRadius);

         // Calculate ineligibleTrip based on validation logic
        validationResults.ineligibleTrip = !validationResults.withinZone10 || validationResults.outsideMaxRadius;

        // Log validation results for debugging
        console.log('Validation Results:', validationResults);

        // Return the results for further use
        return validationResults;
    } catch (error) {
        console.error('Error in validateTripDistances:', error);
        throw error;
    }
}

// Updated triggerDistanceCalculation to include validation without overwriting distance variables
function triggerDistanceCalculation() {
    const { tripType, oneWay, roundTrip } = state;
    const withinZoneThresholdInputField = document.querySelector("input[name='within_zone_threshold']");
    const outsideMaxRadiusInputField = document.querySelector("input[name='outside_max_radius']");
    const ineligibleTripInputField = document.querySelector("input[name='ineligible_trip']");
    
    if (tripType === 'one-way' && oneWay.originPlaceId && oneWay.destinationPlaceId) {
        startApiCall(); // Increment API call counter
        // disableButton(true, true); // Disable button before API calls
        calculateDistance(tripType, oneWay.originPlaceId, oneWay.destinationPlaceId).then(() => {
        validateTripDistances().then((validationResults) => {
            withinZoneThresholdInputField.value = validationResults.withinZone10;
            outsideMaxRadiusInputField.value = validationResults.outsideMaxRadius;
            ineligibleTripInputField.value = validationResults.ineligibleTrip;
            // disableButton(false);
        }).catch((error) => {
                console.error('Validation failed:', error);
                // disableButton(false); // Ensure the button is enabled in case of error
            })
             .finally(() => {
                endApiCall(); // Decrement API call counter
            });
        });
    } else if (
        tripType === 'roundtrip' &&
        roundTrip.originPlaceId &&
        roundTrip.destinationPlaceId &&
        oneWay.originPlaceId &&
        oneWay.destinationPlaceId
    ) {
            startApiCall(); // Increment API call counter        
        calculateDistance(
            tripType,
            oneWay.originPlaceId,
            oneWay.destinationPlaceId,
            roundTrip.originPlaceId,
            roundTrip.destinationPlaceId
        ).then(() => {
            validateTripDistances().then((validationResults) => {
                withinZoneThresholdInputField.value = validationResults.withinZone10;
                outsideMaxRadiusInputField.value = validationResults.outsideMaxRadius;
                ineligibleTripInputField.value = validationResults.ineligibleTrip;
                // disableButton(false); // Enable button after validation
             }).catch((error) => {
                console.error('Validation failed:', error);
                // disableButton(false); // Ensure the button is enabled in case of error
            })
                .finally(() => {
                endApiCall(); // Decrement API call counter
            });

        });
    } else {
        console.log('Required place IDs are not ready for calculation.');
    }
}


// Debounce Utility to Prevent Excessive API Calls
let debounceTimer;
function debounce(func, delay) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(func, delay);
}

// API Call for Distance Calculation
async function getDistanceFromAPI(originPlaceId, destinationPlaceId) {
    try {
        const response = await fetch('/wp-admin/admin-ajax.php?action=calculate_distance', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'calculate_distance',
                origin: originPlaceId,
                destination: destinationPlaceId,
            }),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
             console.error('distance data from getDistanceFromAPI:', result);
            return result.data;
        } else {
            throw new Error(result.error_message || 'Unknown API error.');
        }
    } catch (error) {
        console.error('Error in getDistanceFromAPI:', error.message);
        throw error;
    }
}

// Utility function to convert distance string to a number (in km)
function parseDistance(distanceString) {
    // Remove commas and the unit (e.g., " km" or " m")
    const cleanedDistance = distanceString.replace(/,/g, '').replace(/\s*km|\sm/g, '');
    
    // Convert to a number
    return parseFloat(cleanedDistance);
}

async function calculateDistance(tripType, originPlaceId, destinationPlaceId, returnOriginPlaceId, returnDestinationPlaceId) {
    // disableButton(true, true);    
    debounce(async () => {
        try {
            let travelData;
            let placeIdsString;
            
            if (tripType === 'one-way') {
                placeIdsString = `${originPlaceId},${destinationPlaceId}`;
                travelData = await getDistanceFromAPI(originPlaceId, destinationPlaceId);
            } else if (tripType === 'roundtrip') {
                const outboundData = await getDistanceFromAPI(originPlaceId, destinationPlaceId);
                const returnData = await getDistanceFromAPI(returnOriginPlaceId, returnDestinationPlaceId);

                placeIdsString = `${originPlaceId},${destinationPlaceId},${returnOriginPlaceId},${returnDestinationPlaceId}`;
                travelData = {
                    distance: parseFloat(parseDistance(outboundData.distance)) + parseFloat(parseDistance(returnData.distance)),
                    duration: `${outboundData.duration} + ${returnData.duration}`,
                };
            }

            displayTravelData(travelData, placeIdsString);
            // disableButton(false); // Re-enable button on success
        } catch (error) {
            // disableButton(false); // Re-enable button on error
        }
    }, 300); // Debounce delay
}


// Update Distance in Input Field
function displayTravelData(travelData, placeIdsString) {
    const distanceInputField = document.querySelector("input[name='distance']");
    const durationInputField = document.querySelector("input[name='duration']");
  
     const placeIdElement = document.querySelector("input[name='place_ids']");
    
    if (placeIdElement) {
        placeIdElement.value = placeIdsString;
    } else {
        console.error("Input element with name 'place_ids' not found.");
    }
    
    if (!distanceInputField) {
        console.warn('Distance input field not found. Skipping update.');
        return;
    }
    const parsedDistance = parseDistance(travelData.distance);
    distanceInputField.value = parsedDistance;
    console.log('Distance updated:', parsedDistance);

     if (!durationInputField) {
        console.warn('Distance input field not found. Skipping update.');
        return;
    }
    durationInputField.value = travelData.duration;
    console.log('Duration updated:', travelData.duration);
}

// Event Listener for Trip Type Change
const radioGroup = document.querySelector("input[name='trip_type']");
if (radioGroup) {
    radioGroup.addEventListener('change', (event) => {
        state.tripType = event.target.value;
        console.log('Trip type updated to:', state.tripType);
        triggerDistanceCalculation();
    });
} else {
    console.warn('Radio group for trip type not found.');
}

// Initialize Autocomplete for All Fields Using forEach
document.addEventListener('DOMContentLoaded', () => {
    // monitorGravityForms(); // Handle Gravity Forms events
    
    const inputFields = [
       { selector: 'input[name="location_origin"]', fieldType: 'originPlaceId' },
        { selector: 'input[name="location_destination"]', fieldType: 'destinationPlaceId' },
        // { selector: '#input_1_22_1', fieldType: 'originPlaceId' },
        // { selector: '#input_1_23_1', fieldType: 'destinationPlaceId' },
    ];

    inputFields.forEach(({ selector, fieldType }) => {
        const inputField = document.querySelector(selector);
        console.log('inputField: ' + inputField);
        if (inputField) {
            attachAutoComplete(inputField, fieldType);
        } else {
            console.warn(`Input field not found for selector: ${selector}`);
        }
    });
});


let apiCallsInProgress = 0;

function disableButton(isDisabled, isProcessing = false) {
  const submitButton = document.querySelector("button.bricks-button[type='submit']");
     let buttonTextContent = submitButton.querySelector("span.text").textContent;
    let buttonText = submitButton.querySelector("span.text").innerText;
       console.log('button innerText: ' + buttonText);
    console.log('button TextContent: ' + buttonTextContent);
    console.log('isDisabled: ' + isDisabled);
  if (submitButton) {
    submitButton.disabled = isDisabled;

    console.log('it is disabled now');
    if (isProcessing) {
        console.log('processing is happening now....');
      submitButton.querySelector("span.text").innerText = 'Processing...'; // Change button text
        // buttonText.setInnerText = 'Processing...'; // Change button text
    } else {
      // Restore original button text (if available)
       submitButton.querySelector("span.text").innerText = 'View Pricing'; 
    }
  } else {
    console.warn('Button element not found.');
  }
}

// Manage API call lifecycle and button state
function startApiCall() {
    apiCallsInProgress++;
    updateButtonState();
}

function endApiCall() {
    apiCallsInProgress--;
    updateButtonState();
}

function updateButtonState() {
    const isDisabled = apiCallsInProgress > 0; // Disable if there are ongoing API calls
    disableButton(isDisabled, isDisabled); // Pass `isProcessing` as true if the button should show "Processing..."
}
// Attach event listeners to Gravity Forms AJAX events
function monitorGravityForms() {
    // Reapply button state after Gravity Forms re-renders
    jQuery(document).on('gform_post_render', function () {
        console.log('monitorGravityForms() -> gform_post_render' );
        enforceButtonDisabledState();
        initialiseDatePickerOnForm();
        // initializeCustomScripts();
    });

    // Reapply button state after any field value changes
    jQuery(document).on('change', 'input, select, textarea', function () {
        enforceButtonDisabledState();
    });
}

// Ensure the button remains disabled even after user interactions
function enforceButtonDisabledState() {
    const button = document.querySelector('.bricks-button')[0];
    if (button && apiCallsInProgress > 0) {
        button.disabled = true;
    }
}

// Attach event listeners to form elements to enforce the button's state
function preventUnintentionalButtonEnabling() {
    const inputs = document.querySelectorAll('input, select, textarea, button');
    inputs.forEach(input => {
        input.addEventListener('change', enforceButtonDisabledState);
        input.addEventListener('focus', enforceButtonDisabledState);
        input.addEventListener('blur', enforceButtonDisabledState);
    });
}
