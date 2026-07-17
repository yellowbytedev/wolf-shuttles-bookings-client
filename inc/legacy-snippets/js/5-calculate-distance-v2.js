
// Centralized State Management

const state = {
    tripType: 'one-way', // Default trip type
    serviceType: '',
    oneWay: {
        originPlaceId: null,
        destinationPlaceId: null,
        originLat: '',
        originLng: '',
        destinationLat: '',
        destinationLng: '',
        originName: '',
        destinationName: '',
        originTown : '',
        destinationTown : '',
        originNeighborhood : '',
        destinationNeighborhood : '',
        duration: null,      // excludes the empty dispatch, and used on-trip duration
        outboundDuration: null,
        emptyLegsDuration: null, // all empty leg's summed duration
    

        // NEW
        serviceType: '',
        direction : '',
        pickupDistanceFromCtiaKm : '',
        dropoffDistanceFromCtiaKm : '',
        pickupDuration : null, // empty leg duration for the pickup 
        dropoffDuration : null, // empty leg duration for the dropoff 
        pickupIsFar : '',
        dropoffIsNear : '', 
        applyDispatchFee : '',
        dispatchFeeKm   : '',   
        applyReturnFee : '',
        returnFeeKm : '',
    },
    roundTrip: {
        returnOriginPlaceId: null,
        returnDestinationPlaceId: null,
        returnOriginLat: '',
        returnOriginLng: '',
        returnDestinationLat: '',
        returnDestinationLng: '',
        returnOriginName: '',
        returnDestinationName: '',
        returnOriginTown : '',
        returnDestinationTown : '',
        returnOriginNeighborhood : '',
        returnDestinationNeighborhood : '',
        returnDuration: null,      // excludes the empty dispatch
        returnEmptyLegsDuration: null, // empty dispatch duration
        returnPickupDuration : null, // empty leg duration for the pickup 
        returnDropoffDuration : null, // empty leg duration for the dropoff 

          // NEW
        returnServiceType: '',
        returnDirection : '',
        returnPickupDistanceFromCtiaKm : '',
        returnDropoffDistanceFromCtiaKm : '',
        returnPickupIsFar : '',
        returnDropoffIsNear : '', 
        returnApplyDispatchFee : '',
        returnDispatchFeeKm   : '',   
        returnApplyReturnFee : '',
        returnReturnFeeKm : '',
    },
    charter: {
        charterOriginPlaceId: null,
        charterDestinationPlaceId: null,
        charterOriginLat: '',
        charterOriginLng: '',
        charterOriginName: '',
        charterDestinationName: '',
        charterDestinationLat: '',
        charterDestinationLng: '',
        charterOriginName : '',
        charterDestinationName : '',
        charterOriginTown : '',
        charterDestinationTown : '',
        charterOriginNeighborhood : '',
        charterDestinationNeighborhood : '',
        // NEW
        pickupDistanceFromCtiaKm: '',
        dropoffDistanceFromCtiaKm: '',
    },
    withinLocalRadius: [true, true],
    tripDistance: [],
    tollGates: [],
};

// ---- Pricing/dispatch rules config (easy to tweak later or load from server) ----
const PRICING_RULES = {
  hq: {                   // Cape Town International Airport (CTIA)
    lat: -33.9696,
    lng: 18.5978,
    placeId: "ChIJvQtA90JFzB0RkF7P43l1SEA"
  },
  thresholds: {
    // if pickup is this far (or more) from CTIA, we charge a dispatch leg to pickup
    pickup_far_threshold_km: 80,
    // if drop-off is this close (or closer) to CTIA, we charge a return leg after drop-off
    dropoff_near_threshold_km: 30,
    // direction classifier:
    direction: {
      toward_max_deg: 60,     // ≤60° of the CTIA bearing counts as “toward”
      away_min_deg: 120,      // ≥120° counts as “away”
      radial_min_km: 10,      // need ≥10 km meaningful closer/farther
      angular_eps_deg: 0.25   // tiny angle treated as equal
    }
  }
};

const SCHEMA_VERSION = 1;

function pick(obj, keys){ 
    const o={}; keys.forEach(k=>o[k]=obj?.[k]??null); return o; 
}

function buildBookingPayload() {
  return {
    v: SCHEMA_VERSION,
    ts: Date.now(),
    state: safeCloneForJSON(state) // <-- entire state snapshot
  };
}

function safeCloneForJSON(root) {
  const seen = new WeakSet();
  const isDOM = v => v && typeof v === 'object' && ('nodeType' in v || v === window || v === document);

  return JSON.parse(JSON.stringify(root, (key, val) => {
    // Strip functions/symbols/bigints/DOM/window/document
    if (typeof val === 'function' || typeof val === 'symbol' || typeof val === 'bigint' || isDOM(val)) return undefined;
    if (val && typeof val === 'object') {
      if (seen.has(val)) return undefined; // drop circular
      seen.add(val);
    }
    return val;
  }));
}

// Find the real <form> for a Bricks element id or selector
function resolveActualForm(elOrSelector) {
  const el = (typeof elOrSelector === 'string')
    ? document.querySelector(elOrSelector)
    : elOrSelector;
  if (!el) return null;
  if (el.tagName === 'FORM') return el;
  return el.querySelector('form') || el.closest('form');
}

function attachPayloadToFormData(){
  const containers = document.querySelectorAll('[data-element-id="ifkszj"], [data-element-id="qlwoyv"]');
  const forms = [];
  containers.forEach(c => {
    const f = resolveActualForm(c);
    if (f && !forms.includes(f)) forms.push(f);
  });

  // Also fall back to any Bricks forms if the element-id lookup fails
  if (!forms.length) {
    document.querySelectorAll('form.bricks-form').forEach(f => forms.push(f));
  }

  // 1) Standards path: hook formdata on the actual <form>
  forms.forEach(form => {
    form.addEventListener('formdata', (ev) => {
      const payload = buildBookingPayload();
      ev.formData.set('booking_payload_json', JSON.stringify(payload));
      ev.formData.set('booking_payload_v', String(payload.v ?? 1));

      if (window.WSB_DEBUG) {
        const dbg = (window.WSB?.makeLogger?.('WSB:Network')) || console;
        dbg.group?.('formdata hook');
        dbg.log?.('attached to form', form);
        dbg.log?.('payload', payload);
        dbg.groupEnd?.();
      }
    });
  });

  // 2) Safety net: intercept fetch + XHR to ensure the field is present
  (function(){
    // fetch
    const _fetch = window.fetch;
    window.fetch = function(input, init) {
      try {
        if (init && init.body instanceof FormData) {
          if (!init.body.has('booking_payload_json')) {
            init.body.set('booking_payload_json', JSON.stringify(buildBookingPayload()));
            init.body.set('booking_payload_v', '1');
          }
            
          if (window.WSB_DEBUG) {
            const dbg = (window.WSB?.makeLogger?.('WSB:Network')) || console;
            dbg.group?.('fetch interceptor');
            dbg.log?.('FormData keys', [...init.body.keys()]);
            dbg.groupEnd?.();
          }
        }
      } catch(e) {}
      return _fetch.apply(this, arguments);
    };

    // XHR
    const _send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function(body) {
      try {
        if (body instanceof FormData && !body.has('booking_payload_json')) {
          body.set('booking_payload_json', JSON.stringify(buildBookingPayload()));
          body.set('booking_payload_v', '1');
          if (window.WSB_DEBUG) {
            const dbg = (window.WSB?.makeLogger?.('WSB:Network')) || console;
            dbg.group?.('xhr interceptor');
            dbg.log?.('FormData keys', [...body.keys()]);
            dbg.groupEnd?.();
          }
        }
      } catch(e) {}
      return _send.call(this, body);
    };
  })();

  if (window.WSB_DEBUG) {
    const dbg = (window.WSB?.makeLogger?.('WSB:Network')) || console;
    dbg.log?.('attachPayloadToFormData wired for', forms.length, 'form(s)');
  }
}

// Support LEGACY browsers for submitting form data
(function(){
  if ('FormDataEvent' in window) return;
  const _FormData = window.FormData;
  window.FormData = function(form){
    const fd = form ? new _FormData(form) : new _FormData();
    try{
      if (form) {
        const evt = document.createEvent('Event');
        evt.initEvent('formdata', true, false);
        evt.formData = fd;
        form.dispatchEvent(evt);
      }
    }catch(e){}
    return fd;
  };
  window.FormData.prototype = _FormData.prototype;
})();

// ---------- tiny geo helpers (bearings + haversine) ----------
function toRad(d){ return d * Math.PI / 180; }
function toDeg(r){ return r * 180 / Math.PI; }

function initialBearing(lat1, lon1, lat2, lon2){
  const φ1 = toRad(lat1), φ2 = toRad(lat2), Δλ = toRad(lon2 - lon1);
  const y = Math.sin(Δλ) * Math.cos(φ2);
  const x = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(Δλ);
  return (toDeg(Math.atan2(y, x)) + 360) % 360;
}
function smallestAngleDiff(a,b){
  const d = ((a - b + 180) % 360 + 360) % 360 - 180;
  return Math.abs(d);
}
function haversineKm(lat1, lon1, lat2, lon2){
  const R = 6371, φ1 = toRad(lat1), φ2 = toRad(lat2), dφ = toRad(lat2 - lat1), dλ = toRad(lon2 - lon1);
  const a = Math.sin(dφ/2)**2 + Math.cos(φ1) * Math.cos(φ2) * Math.sin(dλ/2)**2;
  return 2 * R * Math.asin(Math.sqrt(a));
}

/**
 * Classify the leg heading vs CTIA using both angular alignment and radial change.
 * Returns: 'toward' | 'away' | 'lateral' | 'neutral'
 */
function classifyDirectionRelativeToHQ(origin, destination){
  if (!origin || !destination) return 'neutral';

  const HQ = PRICING_RULES.hq;
  const T = PRICING_RULES.thresholds.direction;

  const o2d = initialBearing(origin.lat, origin.lng, destination.lat, destination.lng);
  const o2h = initialBearing(origin.lat, origin.lng, HQ.lat, HQ.lng);
  const angle = smallestAngleDiff(o2d, o2h);

  const O = haversineKm(HQ.lat, HQ.lng, origin.lat, origin.lng);
  const D = haversineKm(HQ.lat, HQ.lng, destination.lat, destination.lng);
  const radialDelta = D - O; // negative = ended closer to CTIA

  if (Math.abs(radialDelta) < T.radial_min_km && angle <= T.angular_eps_deg) return 'neutral';
  if (angle <= T.toward_max_deg && (O - D) >= T.radial_min_km) return 'toward';
  if (angle >= T.away_min_deg   && (D - O) >= T.radial_min_km) return 'away';
  return 'lateral';
}

// Derive a single booking-level service type (for now we do this per one-way request)
// airport_pickup | airport_dropoff | city | charter
function deriveServiceType(tripType, { isAirportPickup, isAirportDropoff }) {
  if (tripType === 'charter') return 'charter'; // future: per-leg when you refactor legs
  if (isAirportPickup)  return 'airport_pickup';
  if (isAirportDropoff) return 'airport_dropoff';
  return 'city';
}

/**
 * Decide empty-leg fees in natural language.
 * originDistance / destinationDistance are **driving** km CTIA→origin/destination 
 */
function decideEmptyLegs({
  isAirportPickup,
  isAirportDropoff,
  originDistance,
  destinationDistance,
  pickupIsFar,
  dropoffIsNear,
  direction
}){

  // Dispatch BEFORE pickup (CTIA -> pickup)?
  const applyDispatchFee =
    (isAirportDropoff && !isAirportPickup) ||
    (!isAirportPickup && !isAirportDropoff && pickupIsFar);
   
  const dispatchFeeKm = applyDispatchFee ? originDistance : 0;

  // Return AFTER drop-off (drop-off -> CTIA)?
  const applyReturnFee =
    (isAirportPickup && !isAirportDropoff) ||
    (!isAirportPickup && !isAirportDropoff && (
      dropoffIsNear || (destinationDistance > PRICING_RULES.thresholds.dropoff_near_threshold_km && direction === 'away')
    ));

  const returnFeeKm = applyReturnFee ? destinationDistance : 0;

  return { applyDispatchFee, dispatchFeeKm, applyReturnFee, returnFeeKm };
}

// Attach Google Autocomplete to Input Fields
// inputField = html element
// fieldType = originPlacedId, destinationPlaceId, returnOriginPlacedId, returnDestinationPlaceId
// tripType = one-way, roundtrip, charter
function attachAutoComplete(inputField, fieldType, tripType) {
    const autocomplete = new google.maps.places.Autocomplete(inputField, {
        types: ['establishment', 'geocode'],
        componentRestrictions: { country: 'ZA' },
        fields: ['address_components', 'geometry', 'name', 'place_id'],
    });

    autocomplete.addListener('place_changed', async () => {
        const place = autocomplete.getPlace();
        const logPlaces = WSB.makeLogger('WSB:Places');
        logPlaces.log('Place details from place_changed listener', place);

         if (place.place_id) {
            // console.log('Using place_id directly:', place.place_id);
            // console.log('town found: ' +  getLocationData(place).town);
            // console.log('neighborhood found: ' +  getLocationData(place).neighborhood);
            handleInputChange(fieldType, place.place_id, tripType, getLocationData(place));
            triggerDistanceCalculation();
        } else if (place.name) {
            console.warn('No Place ID found. Searching by name:', place.name);
            const fallbackData = await fetchPlaceIDByName(place.name);
    
            if (fallbackData && fallbackData.place_id) {
                handleInputChange(fieldType, fallbackData.place_id, tripType, fallbackData);
                triggerDistanceCalculation();
            } else {
                console.error('Failed to fetch Place ID by name.');
            }
        } else {
            console.error('No Place ID or Name found. Cannot proceed.');
        }
        
        // // Extract town/locality from address components
        // let town = '';
        // for (let i = 0; i < place.address_components.length; i++) {
        //     let component = place.address_components[i];
        //     if (component.types.includes('locality')) {
        //         town = component.long_name;
        //         break;
        //     }
        // }
        // console.log(' Valid Place ID:', place.place_id);
        // console.log('town: ' + town);
                 
        // handleInputChange(fieldType, place.place_id, tripType, town);
        // triggerDistanceCalculation();
    });
}

// function getTownFromAddress(place) {
//     if (!place || !place.address_components) {
//         return '';
//     }

//     let town = '';
//     // Check for more granular components first
//     for (let component of place.address_components) {
//         // Look for sublocality types, which often hold the suburb name
//         if (component.types.includes('sublocality') || component.types.includes('sublocality_level_2') || component.types.includes('neighborhood')) {
//             town = component.long_name;
//             break;
//         }
//     }

//     // Fallback to locality if no sublocality/neighborhood is found
//     if (!town) {
//         for (let component of place.address_components) {
//             if (component.types.includes('locality')) {
//                 town = component.long_name;
//                 break;
//             }
//         }
//     }
//     return town;
// }

function getLocationData(place) {
    const locationData = { name: '', town: '', neighborhood: '' };

    if (!place || !place.address_components) {
        return locationData;
    }

    if (place.name) {
        locationData.name = place.name;
    } 

    // Loop through all address components
    place.address_components.forEach(component => {
        if (component.types.includes('locality')) {
            locationData.town = component.long_name;
        }
        // Check for sublocality, sublocality_level_2, or neighborhood
        if (
            component.types.includes('sublocality') ||
            component.types.includes('sublocality_level_2') ||
            component.types.includes('neighborhood')
        ) {
            locationData.neighborhood = component.long_name;
        }
    });

    if (place.geometry && place.geometry.location) {
        locationData.lat = place.geometry.location.lat();
        locationData.lng = place.geometry.location.lng();
    }

    return locationData;
}


// function getTownFromAddress(place) {
//     if (!place || !place.address_components) {
//         return '';
//     }

//     for (let component of place.address_components) {
//         if (component.types.includes('locality')) {
//             return component.long_name; // Return town/city name
//         }
//     }

//     return ''; // Return empty string if no locality is found
// }

async function fetchPlaceIDByName(placeName) {    
    try {        
        const response = await fetch(`${myAjax.ajaxurl}?action=fetch_place_id_by_name&place_name=${encodeURIComponent(placeName)}&_wsb_nonce=${encodeURIComponent(myAjax.providerNonce)}`, {
            method: 'GET'
        });
                
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();  

        if (result.success === true) {
            return { 
                place_id: result.data.place_id, 
                town: result.data.town,
                neighborhood: result.data.neighborhood
            };
        } else {
            console.error('Error fetching Place ID by name:', result.error);
            return null;
        }
    } 
    catch (error) {
        console.error('Error fetching Place ID:', error);
        return null;
    }
}


/**
 *  Fallback: Get Place ID using Lat/Lng
 */
async function getPlaceIDFromLatLng(lat, lng) {
    try {
         const response = await fetch(`${myAjax.ajaxurl}?action=fetch_google_geocode&lat=${lat}&lng=${lng}&_wsb_nonce=${encodeURIComponent(myAjax.providerNonce)}`);
         const data = response.json();

        if (data.success && data.place_id) {
            return { place_id: data.place_id, town: data.town };
        } else {
            console.error('Google Geocoding API Error:', data.error || 'Unknown error');
            return null;
        }
    } catch (error) {
        console.error('Error fetching Place ID from backend:', error);
        return null;
    }
}

/**
 * Function to check if a Place ID is valid and refresh if needed
 */
async function validateAndRefreshPlaceID(placeId) {
    try {
         const response = await fetch(`${myAjax.ajaxurl}?action=get_place_details&place_id=${encodeURIComponent(placeId)}&_wsb_nonce=${encodeURIComponent(myAjax.providerNonce)}`);
         const data = await response.json();

        if (data.success && data.data.result) {
            return data.data.result; // Place details from WordPress proxy
        } else {
            console.warn('Invalid Place ID:', data);
            return null;
        }
    } catch (error) {
        console.error('Error validating Place ID:', error);
        return null;
    }
}

// Utility function to calculate distance between two locations
async function calculateDistanceBetweenLocations(originPlaceId, destinationPlaceId, opts = null) {
    try {
        // If origin and destination are the same
        const wantDetails = !!opts?.details;

        if (originPlaceId === destinationPlaceId) {
          return wantDetails
            ? { km: 0, distanceText: "0 km", durationText: "0 mins", distance_m: 0, duration_s: 0 }
            : 0;
        }
        
        const result = await getDistanceFromAPI(originPlaceId, destinationPlaceId);

        // distanceText is what you currently return from PHP (e.g. "12.3 km")
        const km = parseDistance(result.distance);

        if (!wantDetails) return km;

        // duration is currently a text field in your payload (e.g. "1 hour 5 mins")
        return {
          km,
          distanceText: result.distance ?? null,
          durationText: result.duration ?? null,
          // If you add numeric fields in PHP later, these will populate automatically:
          distance_m: (typeof result.distance_m === "number") ? result.distance_m : null,
          duration_s: (typeof result.duration_s === "number") ? result.duration_s : null,
        };
    } catch (error) {
        console.error('Error calculating distance:', error);
        return null;
    }
}

function pickNonZeroDurationSeconds(originDistance, destinationDistance, originInfo, destInfo) {
  const oS = Number(originInfo?.duration_s) || 0;
  const dS = Number(destInfo?.duration_s) || 0;

    console.log('oS: ' + oS);
      console.log('dS: ' + dS);

  // Both legs have a real dispatch/return component
  if (originDistance > 0 && destinationDistance > 0) return oS + dS;

  // Only one side counts: pick the one with distance > 0
  if (originDistance > 0) return oS;
  if (destinationDistance > 0) return dS;

  // Both are 0 (CTIA -> CTIA scenario); return 0
  return 0;
}

function formatSeconds(secs) {
  secs = Math.max(0, Math.round(secs));
  const days = Math.floor(secs / 86400); secs %= 86400;
  const hrs  = Math.floor(secs / 3600);  secs %= 3600;
  const mins = Math.floor(secs / 60);

  const parts = [];
  if (days) parts.push(`${days} day${days === 1 ? '' : 's'}`);
  if (hrs)  parts.push(`${hrs} hour${hrs === 1 ? '' : 's'}`);
  if (mins || !parts.length) parts.push(`${mins} min${mins === 1 ? '' : 's'}`);
  return parts.join(' ');
}

// Validate Trip Logic
async function validateTripDistances() {
    const { tripType, oneWay, roundTrip, charter } = state;
    const HQ_LAT = -33.9696; // Latitude of Cape Town International Airport
    const HQ_LNG = 18.5978; // Longitude of Cape Town International Airport
    const HQ_PLACE_ID = "ChIJvQtA90JFzB0RkF7P43l1SEA"; // Cape Town International Airport
    const maxRadius = 350; // Maximum allowable distance in km
    const localZoneMaxRadius = 50; // Zone 7 distance in km

    let validationResults = {
        withinLocalZoneRadius: false, // Currently set to Zone 7 which is 50km max
        outsideMaxRadius: false,
        ineligibleTrip: false,
        distances: [],
    };

    try {
        if (tripType === 'one-way') {
            // One-way trip: Calculate distances for origin and destination
            const originInfo = await calculateDistanceBetweenLocations(HQ_PLACE_ID, oneWay.originPlaceId, { details: true });
            const destInfo = await calculateDistanceBetweenLocations(HQ_PLACE_ID, oneWay.destinationPlaceId, { details: true });

            const originDistance = originInfo.km;          
            const destinationDistance = destInfo.km;  
            
            validationResults.distances = [originDistance, destinationDistance];

            const durSecs = pickNonZeroDurationSeconds(originDistance, destinationDistance, originInfo, destInfo);
            state.oneWay.emptyLegsDuration = formatSeconds(durSecs);

            state.oneWay.pickupDuration = originInfo.durationText;
            state.oneWay.dropoffDuration = destInfo.durationText;
            
             // ---- NEW: classify & decide fees (natural language) ----
              const isAirportPickup  = originDistance === 0;
              const isAirportDropoff = destinationDistance === 0;

            const serviceType = deriveServiceType(tripType, { isAirportPickup, isAirportDropoff });
            state.oneWay.serviceType = serviceType;
            
              // general (non-hardcoded) proximity flags
              const pickupIsFar   = !isAirportPickup  && originDistance >= PRICING_RULES.thresholds.pickup_far_threshold_km;
              const dropoffIsNear = !isAirportDropoff && destinationDistance <= PRICING_RULES.thresholds.dropoff_near_threshold_km;
            
              // direction based on bearings + radial change 
              const originPoint = { lat: Number(state.oneWay.originLat), lng: Number(state.oneWay.originLng) };
              const destPoint   = { lat: Number(state.oneWay.destinationLat), lng: Number(state.oneWay.destinationLng) };
              const direction   = (!isAirportPickup && !isAirportDropoff)
                ? classifyDirectionRelativeToHQ(originPoint, destPoint)
                : (isAirportPickup && !isAirportDropoff ? 'away'
                   : (!isAirportPickup && isAirportDropoff ? 'toward' : 'neutral'));
            
              const fees = decideEmptyLegs({
                isAirportPickup,
                isAirportDropoff,
                originDistance,
                destinationDistance,
                pickupIsFar,
                dropoffIsNear,
                direction
              });
            
              // Persist on state (for submission later)
              state.oneWay.direction = direction;

              state.oneWay.pickupDistanceFromCtiaKm  = originDistance;
              state.oneWay.dropoffDistanceFromCtiaKm = destinationDistance;
            
               

              // state.oneWay.totalDuration  = originDistance;
           
              state.oneWay.pickupIsFar   = pickupIsFar;
              state.oneWay.dropoffIsNear = dropoffIsNear;
            
              state.oneWay.applyDispatchFee = fees.applyDispatchFee;
              state.oneWay.dispatchFeeKm    = fees.dispatchFeeKm;
            
              state.oneWay.applyReturnFee   = fees.applyReturnFee;
              state.oneWay.returnFeeKm      = fees.returnFeeKm;

              state.oneWay.serviceType = serviceType;

            const legAnalysis = WSB.makeLogger('WSB:Leg Analyis');
            legAnalysis.log('One-way leg', {
                direction,
                pickupIsFar,
                dropoffIsNear,
                applyDispatchFee: fees.applyDispatchFee,
                dispatchFeeKm:    fees.dispatchFeeKm,
                applyReturnFee:   fees.applyReturnFee,
                returnFeeKm:      fees.returnFeeKm
              });
            

             // Check if any location is within Zone 7
            validationResults.withinLocalZoneRadius = validationResults.distances.some((distance) => distance <= localZoneMaxRadius);

        } 
        else if (tripType === 'roundtrip') {
            const originInfoReturn = await calculateDistanceBetweenLocations(HQ_PLACE_ID, roundTrip.returnOriginPlaceId, { details: true });
            const destinationInfoReturn = await calculateDistanceBetweenLocations(HQ_PLACE_ID, roundTrip.returnDestinationPlaceId, { details: true });

               // Roundtrip: Calculate distances for all four locations
            const originDistanceReturn = originInfoReturn.km;
            const destinationDistanceReturn = destinationInfoReturn.km;
            
            const originInfoOutbound = await calculateDistanceBetweenLocations(HQ_PLACE_ID, oneWay.originPlaceId, { details: true });
            const destInfoOutBound = await calculateDistanceBetweenLocations(HQ_PLACE_ID, oneWay.destinationPlaceId, { details: true });

            const originDistanceOutbound = originInfoOutbound.km;          
            const destinationDistanceOutbound = destInfoOutBound.km;  
            
             validationResults.distances = [
                originDistanceOutbound,
                destinationDistanceOutbound,
                originDistanceReturn,
                destinationDistanceReturn,
            ];

              const durSecsReturn = pickNonZeroDurationSeconds(originDistanceReturn, destinationDistanceReturn, originInfoReturn, destinationInfoReturn);

            state.roundTrip.returnEmptyLegsDuration = formatSeconds(durSecsReturn);

            state.roundTrip.returnPickupDuration = originInfoReturn.durationText;
            state.roundTrip.returnDropoffDuration = destinationInfoReturn.durationText;

            const durSecsOutbound = pickNonZeroDurationSeconds(originDistanceOutbound, destinationDistanceOutbound, originInfoOutbound, destInfoOutBound);
            state.oneWay.emptyLegsDuration = formatSeconds(durSecsOutbound);

            state.oneWay.pickupDuration = originInfoOutbound.durationText;
            state.oneWay.dropoffDuration = destInfoOutBound.durationText;

          
            
            // ---- NEW: OUTBOUND ----
           const isAirportPickup  = originDistanceOutbound === 0;
           const isAirportDropoff = destinationDistanceOutbound === 0;
           const isAirportPickupReturn  = originDistanceReturn === 0;
           const isAirportDropoffReturn = destinationDistanceReturn === 0;

            const serviceType = deriveServiceType(tripType, { isAirportPickup, isAirportDropoff });
            const serviceTypeReturn = deriveServiceType(tripType, {
              isAirportPickup:  isAirportPickupReturn,
              isAirportDropoff: isAirportDropoffReturn
            });
            
              // general (non-hardcoded) proximity flags
              const pickupIsFar   = !isAirportPickup  && originDistanceOutbound >= PRICING_RULES.thresholds.pickup_far_threshold_km;
              const dropoffIsNear = !isAirportDropoff && destinationDistanceOutbound <= PRICING_RULES.thresholds.dropoff_near_threshold_km;
              const pickupIsFarReturn   = !isAirportPickupReturn  && originDistanceReturn >= PRICING_RULES.thresholds.pickup_far_threshold_km;
              const dropoffIsNearReturn = !isAirportDropoffReturn && destinationDistanceReturn <= PRICING_RULES.thresholds.dropoff_near_threshold_km;
            
              // direction based on bearings + radial change 
              const originPoint = { lat: Number(state.oneWay.originLat), lng: Number(state.oneWay.originLng) };
              const destPoint   = { lat: Number(state.oneWay.destinationLat), lng: Number(state.oneWay.destinationLng)};
             const originPointReturn = { lat: Number(state.roundTrip.returnOriginLat), lng: Number(state.roundTrip.returnOriginLng) };
              const destPointReturn   = { lat: Number(state.roundTrip.returnDestinationLat), lng: Number(state.roundTrip.returnDestinationLng)};
                  
              const direction   = (!isAirportPickup && !isAirportDropoff)
                ? classifyDirectionRelativeToHQ(originPoint, destPoint)
                : (isAirportPickup && !isAirportDropoff ? 'away'
                   : (!isAirportPickup && isAirportDropoff ? 'toward' : 'neutral'));

             const directionReturn   = (!isAirportPickupReturn && !isAirportDropoffReturn)
                ? classifyDirectionRelativeToHQ(originPointReturn, destPointReturn)
                : (isAirportPickupReturn && !isAirportDropoffReturn ? 'away'
                   : (!isAirportPickupReturn && isAirportDropoffReturn ? 'toward' : 'neutral'));

            const fees = decideEmptyLegs({
              isAirportPickup,
              isAirportDropoff,
              originDistance:      originDistanceOutbound,
              destinationDistance: destinationDistanceOutbound,
              pickupIsFar,
              dropoffIsNear,
              direction
            });
            
            const feesReturn = decideEmptyLegs({
              isAirportPickup:      isAirportPickupReturn,
              isAirportDropoff:     isAirportDropoffReturn,
              originDistance:       originDistanceReturn,
              destinationDistance:  destinationDistanceReturn,
              pickupIsFar:          pickupIsFarReturn,
              dropoffIsNear:        dropoffIsNearReturn,
              direction:            directionReturn
            });
            
              // Persist on state (for submission later)
              state.oneWay.direction = direction;
              state.roundTrip.returnDirection = directionReturn;
            
              state.oneWay.pickupDistanceFromCtiaKm  = originDistanceOutbound;
              state.oneWay.dropoffDistanceFromCtiaKm = destinationDistanceOutbound;

              state.roundTrip.returnPickupDistanceFromCtiaKm  = originDistanceReturn;
              state.roundTrip.returnDropoffDistanceFromCtiaKm = destinationDistanceReturn;
            
              state.oneWay.pickupIsFar   = pickupIsFar;
              state.oneWay.dropoffIsNear = dropoffIsNear;

              state.roundTrip.returnPickupIsFar   = pickupIsFarReturn;
              state.roundTrip.returnDropoffIsNear = dropoffIsNearReturn;
            
              state.oneWay.applyDispatchFee = fees.applyDispatchFee;
              state.oneWay.dispatchFeeKm    = fees.dispatchFeeKm;
            
              state.roundTrip.returnApplyDispatchFee = feesReturn.applyDispatchFee;
              state.roundTrip.returnDispatchFeeKm    = feesReturn.dispatchFeeKm;
            
              state.oneWay.applyReturnFee   = fees.applyReturnFee;
              state.oneWay.returnFeeKm      = fees.returnFeeKm;

              state.roundTrip.returnApplyReturnFee   = feesReturn.applyReturnFee;
              state.roundTrip.returnReturnFeeKm      = feesReturn.returnFeeKm;

             state.oneWay.serviceType = serviceType;
             state.roundTrip.returnServiceType = serviceTypeReturn;

            const legAnalysis = WSB.makeLogger('WSB:Leg Analyis');
            legAnalysis.log('Outbound leg', {
                direction,
                pickupIsFar,
                dropoffIsNear,
                applyDispatchFee: fees.applyDispatchFee,
                dispatchFeeKm:    fees.dispatchFeeKm,
                applyReturnFee:   fees.applyReturnFee,
                returnFeeKm:      fees.returnFeeKm
              });

            legAnalysis.log('Roundtrip leg', {
                directionReturn,
                pickupIsFarReturn,
                dropoffIsNearReturn,
                applyDispatchFeeReturn: feesReturn.applyDispatchFee,
                dispatchFeeKmReturn:    feesReturn.dispatchFeeKm,
                applyReturnFeeReturn:   feesReturn.applyReturnFee,
                returnFeeKmReturn:      feesReturn.returnFeeKm
              });
            
            const outboundWithin = (
            originDistanceOutbound    <= localZoneMaxRadius ||
            destinationDistanceOutbound <= localZoneMaxRadius
            );

            const returnWithin = (
              originDistanceReturn    <= localZoneMaxRadius ||
              destinationDistanceReturn <= localZoneMaxRadius
            );
            
            // 2) Store both in state.roundTrip.withinLocalRadius
            state.withinLocalRadius = [ outboundWithin, returnWithin ];
            
            // 3) (Optional) If you still need a single “both‐legs” flag, re‐compute validationResults:
            validationResults.withinLocalZoneRadius = outboundWithin && returnWithin;

        }
            else if (tripType === 'charter') {
                // charter trip:
                const charterOriginDistance      = await calculateDistanceBetweenLocations(HQ_PLACE_ID, charter.charterOriginPlaceId);
                const charterDestinationDistance = await calculateDistanceBetweenLocations(HQ_PLACE_ID, charter.charterDestinationPlaceId);
        
                validationResults.distances = [charterOriginDistance, charterDestinationDistance];
        
                // Persist these on state so they go into booking_payload_json
                // (charter-specific)
                state.charter.pickupDistanceFromCtiaKm  = charterOriginDistance;
                state.charter.dropoffDistanceFromCtiaKm = charterDestinationDistance;
        
                // Optional: mirror onto oneWay as a “global” primary leg,
                // so any existing PHP that reads state.oneWay.* keeps working.
                state.oneWay.pickupDistanceFromCtiaKm  = charterOriginDistance;
                state.oneWay.dropoffDistanceFromCtiaKm = charterDestinationDistance;
        
                // Check if any location is within Zone 7
                validationResults.withinLocalZoneRadius =
                    validationResults.distances.some((distance) => distance <= localZoneMaxRadius);
            }


        // Check if any location exceeds the maximum allowable radius
        validationResults.outsideMaxRadius = validationResults.distances.some((distance) => distance > maxRadius);

         // Calculate ineligibleTrip based on validation logic
        validationResults.ineligibleTrip = validationResults.outsideMaxRadius;

        // Return the results for further use
        return validationResults;
    } catch (error) {
        console.error('Error in validateTripDistances:', error);
        throw error;
    }
}

// Updated ajax contract for HERE Api call
async function checkTollGates(origin, destination) {
  try {
    // quick guard against bad inputs
    if (!origin || !destination || origin.includes('undefined') || destination.includes('undefined')) {
      console.warn('checkTollGates: bad origin/destination', { origin, destination });
      return null;
    }

    const formData = new URLSearchParams();
    formData.append('action', 'calculate_tolls'); // you can put it in body as well
    formData.append('origin', origin);
    formData.append('destination', destination);
    formData.append('_wsb_nonce', myAjax.providerNonce);

    const response = await fetch(myAjax.ajaxurl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
      // If you use a nonce: headers: { 'X-WP-Nonce': myAjax.nonce }
    });

    const result = await response.json();
    const log = WSB.makeLogger('WSB: Tolls API');

    if (!response.ok) {
      log.error('HTTP error', response.status, result);
      return null;
    }

    log.log('HERE Tolls API result:', result);

    if (result.success && result.data) {
      return result.data.toll_name || null; // a string like "huguenot toll" or null
    } else {
      // surface server-provided error to console to help you debug
      if (result?.data?.error) log.warn('Tolls error:', result.data.error, result.data.here || '');
      return null;
    }
  } catch (err) {
    console.error('Error checking toll gates:', err);
    return null;
  }
}

// Updated triggerDistanceCalculation to include validation without overwriting distance variables
async function triggerDistanceCalculation() {
    const stateLogger = WSB.makeLogger('WSB: State');

    const { tripType, oneWay, roundTrip, charter } = state;
    
    const prefix = tripType === 'charter' ? 'charter_' : '';

    const tollGatesInputField = document.querySelector(`input[name='${prefix}toll_gates']`);
    const withinZoneThresholdInputField = document.querySelector(`input[name='${prefix}within_zone_threshold']`);
    const outsideMaxRadiusInputField = document.querySelector(`input[name='${prefix}outside_max_radius']`);
    const ineligibleTripInputField = document.querySelector(`input[name='${prefix}ineligible_trip']`);
    // const tripDistancesInputField = document.querySelector(`input[name='${prefix}distances']`);
    
    const isCharter = tripType === 'charter';

    try {
         // Helpers
            const getKm = (arr, i) => {
              const v = Number(arr?.[i]);
              return Number.isFinite(v) ? v : 0;
            };
            
            const setAirportDistances = (
                  leg,
                  serviceType,
                  onTripKm,
                  keys = { pickup: "pickupDistanceFromCtiaKm", dropoff: "dropoffDistanceFromCtiaKm" }
                ) => {
                  const { pickup, dropoff } =  keys;
                  
                  if (serviceType === "airport_pickup") {
                      
                    leg[pickup]  = 0;        // pickup is CTIA
                    leg[dropoff] = onTripKm; // remote side
                  } else if (serviceType === "airport_dropoff") {
                      
                    leg[dropoff] = 0;        // dropoff is CTIA
                    leg[pickup]  = onTripKm; // remote side
                  }
                };
        
        if (tripType === 'one-way' && oneWay.originPlaceId && oneWay.destinationPlaceId) {
            startApiCall(isCharter);
            const originCoords = `${oneWay.originLat},${oneWay.originLng}`;
            const destinationCoords = `${oneWay.destinationLat},${oneWay.destinationLng}`;

            // Run toll check, distance calculation, and validation concurrently.
            const [tollExists, distanceResult, validationResults] = await Promise.all([
                checkTollGates(originCoords, destinationCoords),
                calculateDistance(tripType, oneWay.originPlaceId, oneWay.destinationPlaceId),
                validateTripDistances()
            ]);

            let travelData = distanceResult;

            // Update toll gate result.
            state.tollGates = [tollExists];
            if (tollGatesInputField) {
                tollGatesInputField.value = JSON.stringify(state.tollGates);
            }

            state.roundTrip.withinLocalRadius

            // if (!validationResults.withinLocalZoneRadius)  {
            //     const routeKm = parseDistance(distanceResult.distance);  // e.g. 47
            
            //     let totalKm = routeKm;
            //     totalKm += validationResults.distances[0];         // e.g. +101 = 148
                
            //     state.tripDistance = [ totalKm ];
            //     const updatedTravelData = {
            //       distance: distanceResult.distance,      // <-- override with our total
            //       duration: distanceResult.duration  // keep the same duration
            //     };

            //     travelData = updatedTravelData;
            // }

            setAirportDistances(
              state.oneWay,
              state.oneWay.serviceType,
              getKm(state.tripDistance, 0)
            );
        
            stateLogger.log('Trip state updated', state);

            displayTravelData(
                travelData,
                `${oneWay.originPlaceId},${oneWay.destinationPlaceId}`,
                false
            );
            
            // Update validation result fields.
            withinZoneThresholdInputField.value = validationResults.withinLocalZoneRadius;
            outsideMaxRadiusInputField.value = validationResults.outsideMaxRadius;
            ineligibleTripInputField.value = validationResults.ineligibleTrip;
            // tripDistancesInputField.value = validationResults.distances;
        } 
        
        else if (
            tripType === 'roundtrip' &&
            roundTrip.returnOriginPlaceId &&
            roundTrip.returnDestinationPlaceId &&
            oneWay.originPlaceId &&
            oneWay.destinationPlaceId
        ) {
            startApiCall(isCharter);

            const outboundCoords = {
                origin: `${oneWay.originLat},${oneWay.originLng}`,
                destination: `${oneWay.destinationLat},${oneWay.destinationLng}`
            };
            const returnCoords = {
                origin: `${roundTrip.returnOriginLat},${roundTrip.returnOriginLng}`,
                destination: `${roundTrip.returnDestinationLat},${roundTrip.returnDestinationLng}`
            };

            // Run toll checks for both legs concurrently.
            const [outboundToll, returnToll] = await Promise.all([
                checkTollGates(outboundCoords.origin, outboundCoords.destination),
                checkTollGates(returnCoords.origin, returnCoords.destination)
            ]);
            state.tollGates = [outboundToll, returnToll];
            if (tollGatesInputField) {
                tollGatesInputField.value = JSON.stringify(state.tollGates);
            }
            
            // Run distance calculation and validation concurrently.
            const [distanceResult, validationResults] = await Promise.all([
                calculateDistance(
                    tripType,
                    oneWay.originPlaceId,
                    oneWay.destinationPlaceId,
                    roundTrip.returnOriginPlaceId,
                    roundTrip.returnDestinationPlaceId
                ),
                validateTripDistances()
            ]);

            let travelData = distanceResult;
            
            // if (!validationResults.withinLocalZoneRadius)  {
            //       // 1) Get the “within 50 km” flags for each leg:
            //    const withinArr = state.withinLocalRadius;
    
            //     // 2) Get the raw leg distances
            //     const legKms = state.tripDistance; 
                
            //     // 3) Build a new array where we add the dispatch‐leg only if that leg is out‐of‐zone:
            //     const adjustedLegKms = legKms.map((legKm, idx) => {
            //       // if that leg is outside 50 km, then add the HQ→pickup distance:
            //       if (!withinArr[idx]) {
            //         const dispatchKm = validationResults.distances[idx * 2];
            //         return legKm + dispatchKm;
            //       }
            //       // otherwise, just keep the original legKm
            //       return legKm;
            //     });
                
            //     // 4) Persist the adjusted legs back into state:
            //     state.tripDistance = adjustedLegKms;
                
            //     // 5) If you want a single “total km” value for your form, sum them:
            //     // const totalKm = adjustedLegKms.reduce((sum, k) => sum + k, 0);
                
            //     // 6) Build a final travelData object (so displayTravelData can pick it up):
            //     //    (You can keep the same duration string you got from your API.)
            //     travelData = {
            //       distance: distanceResult.distance,
            //       duration: distanceResult.duration
            //     };
                
            // }

        setAirportDistances(
          state.oneWay,
          state.oneWay.serviceType,
          getKm(state.tripDistance, 0)
        );
        
        // Return leg uses roundTrip.returnServiceType
        setAirportDistances(
          state.roundTrip,
          state.roundTrip.returnServiceType,
          getKm(state.tripDistance, 1),
          { pickup: "returnPickupDistanceFromCtiaKm", dropoff: "returnDropoffDistanceFromCtiaKm" }
        );

         stateLogger.log('Trip state updated', state);

            
            displayTravelData(
              travelData,
`${oneWay.originPlaceId},${oneWay.destinationPlaceId},${roundTrip.returnOriginPlaceId},${roundTrip.returnDestinationPlaceId}`,
              false  // or `true` if this is a charter
            );
            
            withinZoneThresholdInputField.value = validationResults.withinLocalZoneRadius;
            outsideMaxRadiusInputField.value = validationResults.outsideMaxRadius;
            ineligibleTripInputField.value = validationResults.ineligibleTrip;
            // tripDistancesInputField.value = validationResults.distances;
        } 
        else if (tripType === 'charter' && charter.charterOriginPlaceId && charter.charterDestinationPlaceId) {
            startApiCall(isCharter);

            const charterOriginCoords = `${charter.charterOriginLat},${charter.charterOriginLng}`;
            const charterDestinationCoords = `${charter.charterDestinationLat},${charter.charterDestinationLng}`;

             // Run toll check, distance calculation, and validation concurrently.
            const [tollExists, distanceResult, validationResults] = await Promise.all([
                checkTollGates(charterOriginCoords, charterDestinationCoords),
                calculateDistance(tripType, charter.charterOriginPlaceId, charter.charterDestinationPlaceId),
                validateTripDistances()
            ]);
            
             // Update toll gate result.
            state.tollGates = [tollExists];
            if (tollGatesInputField) {
                tollGatesInputField.value = JSON.stringify(state.tollGates);
            }
            
            // Update validation result fields.
            withinZoneThresholdInputField.value = validationResults.withinLocalZoneRadius;
            outsideMaxRadiusInputField.value = validationResults.outsideMaxRadius;
            ineligibleTripInputField.value = validationResults.ineligibleTrip;
            // tripDistancesInputField.value = validationResults.distances;
        }
        else {
            WSB.makeLogger('Place IDs').warn('All required place IDs not ready for calculation.')
        }
    } catch (error) {
        console.error('Error in triggerDistanceCalculation:', error);
    } finally {
        endApiCall(isCharter); // Decrement API call counter
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
        const response = await fetch(`${myAjax.ajaxurl}?action=calculate_distance`, {
            method: 'POST',
            body: new URLSearchParams({
                action: 'calculate_distance',
                origin: originPlaceId,
                destination: destinationPlaceId,
                _wsb_nonce: myAjax.providerNonce,
            }),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
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
    return parseFloat(parseFloat(cleanedDistance).toFixed(2));
}

async function calculateDistance(
  tripType,
  originPlaceId,
  destinationPlaceId,
  returnOriginPlaceId,
  returnDestinationPlaceId
) {
  try {
    let isCharter = false;
    let travelData;
    let placeIdsString;

    const stateLogger = WSB.makeLogger('WSB: State');

        // const isAirportPickup  = 0;
        // const isAirportDropoff = 0;
        // const isAirportPickupReturn  = 0;
        // const isAirportDropoffReturn = 0;

        //  const isAirportPickup  = originDistanceOutbound === 0;
        // const isAirportDropoff = destinationDistanceOutbound === 0;
        // const isAirportPickupReturn  = originDistanceReturn === 0;
        // const isAirportDropoffReturn = destinationDistanceReturn === 0;

         
            // const serviceTypeReturn = deriveServiceType(tripType, {
            //   isAirportPickup:  isAirportPickupReturn,
            //   isAirportDropoff: isAirportDropoffReturn
            // });

    if (tripType === 'one-way') {
      // ← use backticks here
      placeIdsString = `${originPlaceId},${destinationPlaceId}`;
      travelData = await getDistanceFromAPI(originPlaceId, destinationPlaceId);

      // console.log('travelData: ' + JSON.stringify(travelData));

      state.tripDistance = [parseDistance(travelData.distance)];
      state.oneWay.duration = travelData.duration;
      state.oneWay.outboundDuration = travelData.duration;

      stateLogger.log('Trip state updated', state);
    } 
    else if (tripType === 'roundtrip') {
      const outboundData = await getDistanceFromAPI(originPlaceId, destinationPlaceId);
      const returnData   = await getDistanceFromAPI(returnOriginPlaceId, returnDestinationPlaceId);

      state.tripDistance = [
        parseDistance(outboundData.distance),
        parseDistance(returnData.distance)
      ];

       state.oneWay.duration = outboundData.duration;
       state.oneWay.outboundDuration = outboundData.duration;
       state.roundTrip.returnDuration = returnData.duration;

       stateLogger.log('Trip state updated', state);

      // add the two legs and format with backticks
      let distance = parseFloat(parseDistance(outboundData.distance)) 
                   + parseFloat(parseDistance(returnData.distance));
      const formattedDistance = `${distance} km`;

      // another template literal
      placeIdsString = `${originPlaceId},${destinationPlaceId},${returnOriginPlaceId},${returnDestinationPlaceId}`;
      travelData = {
        distance: formattedDistance,
        // wrap the durations in backticks if you want them concatenated as a string
        duration: `${outboundData.duration} + ${returnData.duration}`,
      };
    } 
    else if (tripType === 'charter') {
      const outboundData = await getDistanceFromAPI(originPlaceId, destinationPlaceId);

      state.tripDistance = [parseDistance(outboundData.distance)];
      let distance = parseFloat(state.tripDistance);
      let duration = outboundData.duration;

      // both need backticks to become proper strings
      const formattedDistance = `${distance} km`;
      const formattedDuration = `${duration} hours`;

      travelData = {
        trueDistance: formattedDistance,
        distance: formattedDistance,
        duration: formattedDuration,
      };
      placeIdsString = `${originPlaceId},${destinationPlaceId}`;
      isCharter = true;
    }

    // now that travelData is built, display it
    displayTravelData(travelData, placeIdsString, isCharter);
    return travelData;
  } 
  catch (error) {
    console.error('Error in calculateDistance:', error);
    return null;
  }
}


// Update Distance in Input Field
function displayTravelData(travelData, placeIdsString, isCharter = false) {

    const elementId = isCharter ? "qlwoyv" : "ifkszj";

    // "qlwoyv" -> shuttle hire form 
    // "ifkszj" -> ride form 
    
    // Use querySelector to find the element by data-script-id
    const form = document.querySelector(`[data-element-id="${elementId}"]`);

    if (!form) {
        console.warn(`Element with data-element-id="${elementId}" not found.`);
        return;
    }
    const prefix = isCharter ? 'charter_' : '';

    const tripDistanceInputField = form.querySelector(`input[name='${prefix}trip_distance']`);
    const distanceInputField = form.querySelector(`input[name='${prefix}distance']`);
    const durationInputField = form.querySelector(`input[name='${prefix}duration']`);
    const placeIdElement = form.querySelector(`input[name='${prefix}place_ids']`);
    
    if (tripDistanceInputField) {
        tripDistanceInputField.value = state.tripDistance;
    }

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
    
     if (!durationInputField) {
        console.warn('Distance input field not found. Skipping update.');
        return;
    }
    durationInputField.value = travelData.duration;
}

function initialiseTripTypeRadio() {
    // Event Listener for Trip Type Change
    const tripTypeRadioGroup = document.querySelectorAll('input[name="trip_type[]"]');
    console.log('hey we are here in initialiseTripTypeRadio() ');
    if (tripTypeRadioGroup) {
            console.log('we have a tripttype radio group initialiseTripTypeRadio() ');
        tripTypeRadioGroup.forEach(radio => {
          radio.addEventListener('change', (event) => {
            if (radio.checked) {
                state.tripType = event.target.value;

                triggerDistanceCalculation();

                if (event.target.value === "roundtrip") {
                    toggleReturnFields(true); // Show return fields for roundtrip
                } else {
                    toggleReturnFields(false); // Hide return fields for one-way
                }
                  
            }
          });
        });
    } else {
        console.warn('Radio group for trip type not found.');
    }
}

// Handle the state object
function handleInputChange(fieldType, placeId, tripType, locationData) {
    let tripTypeSelected = tripType;

    const stateLogger = WSB.makeLogger('WSB: State');
    
    if (tripType !== "charter") {
        const tripTypeRadioGroup = document.querySelectorAll('input[name="trip_type[]"]');

        let selectedValue;
        tripTypeRadioGroup.forEach(radio => {
          if (radio.checked) {
            tripTypeSelected = radio.value;
          }
        });
    }

    state.tripType = tripTypeSelected;
    // Update booking details based on the fieldType
    if (tripType === "one-way") {
        if (fieldType === "originPlaceId") {
            state.oneWay.originPlaceId = placeId;
            state.oneWay.originName = locationData.name;
            state.oneWay.originTown = locationData.town;
            state.oneWay.originNeighborhood = locationData.neighborhood;

            let townOriginInputField = document.querySelector("input[name='town_origin']");
            let neighborhoodOriginInputField = document.querySelector("input[name='neighborhood_origin']");
            let originCoordsInputField = document.querySelector("input[name='origin_coords']");
            
            // Save coordinates:
            state.oneWay.originLat = locationData.lat;
            state.oneWay.originLng = locationData.lng;

            if (townOriginInputField) {
                townOriginInputField.value = state.oneWay.originTown;
            }
            if (neighborhoodOriginInputField) {
                neighborhoodOriginInputField.value = state.oneWay.originNeighborhood;
            }
            if (originCoordsInputField) {
                originCoordsInputField.value = JSON.stringify({
                lat: state.oneWay.originLat,
                lng:  state.oneWay.originLng,
                });
            }
        } 
        else if (fieldType === "destinationPlaceId") {
            state.oneWay.destinationPlaceId = placeId;
            state.oneWay.destinationName = locationData.name;
            state.oneWay.destinationTown = locationData.town;
            state.oneWay.destinationNeighborhood = locationData.neighborhood;

            let townDestinationInputField = document.querySelector("input[name='town_destination']");
            let neighborhoodDestinationInputField = document.querySelector("input[name='neighborhood_destination']");
            let destinationCoordsInputField = document.querySelector("input[name='destination_coords']");
            
            state.oneWay.destinationLat = locationData.lat;
            state.oneWay.destinationLng = locationData.lng;

            if (townDestinationInputField) {
                townDestinationInputField.value = state.oneWay.destinationTown;
            }
            if (neighborhoodDestinationInputField) {
                neighborhoodDestinationInputField.value = state.oneWay.destinationNeighborhood;
            }
            if (destinationCoordsInputField) {
                destinationCoordsInputField.value = JSON.stringify({
                lat: state.oneWay.destinationLat,
                lng:  state.oneWay.destinationLng,
                });
            }
        }
    } 
    else if (tripType === "roundtrip") {
        if (fieldType === "returnOriginPlaceId") {
            state.roundTrip.returnOriginPlaceId = placeId;
            state.roundTrip.returnOriginName = locationData.name;
            state.roundTrip.returnOriginTown = locationData.town;
            state.roundTrip.returnOriginNeighborhood = locationData.neighborhood;

            let returnTownOriginInputField = document.querySelector("input[name='return_town_origin']");
            let returnNeighborhoodOriginInputField = document.querySelector("input[name='return_neighborhood_origin']");
            let returnOriginCoordsInputField = document.querySelector("input[name='return_origin_coords']");
            
            state.roundTrip.returnOriginLat = locationData.lat;
            state.roundTrip.returnOriginLng = locationData.lng;

            if (returnTownOriginInputField) {
                returnTownOriginInputField.value = state.roundTrip.returnOriginTown;
            }
            if (returnNeighborhoodOriginInputField) {
                returnNeighborhoodOriginInputField.value = state.roundTrip.returnOriginNeighborhood;
            }
            if (returnOriginCoordsInputField) {
                returnOriginCoordsInputField.value = JSON.stringify({
                lat: state.roundTrip.returnOriginLat,
                lng: state.roundTrip.returnOriginLng
                });
            }
            
        } 
        else if (fieldType === "returnDestinationPlaceId") {
            state.roundTrip.returnDestinationPlaceId = placeId;
            state.roundTrip.returnDestinationName = locationData.name;
            state.roundTrip.returnDestinationTown = locationData.town;
            state.roundTrip.returnDestinationNeighborhood = locationData.neighborhood;
            
            let returnTownDestinationInputField = document.querySelector("input[name='return_town_destination']");
            let returnNeighborhoodDestinationInputField = document.querySelector("input[name='return_neighborhood_destination']");
            let returnDestinationCoordsInputField = document.querySelector("input[name='return_destination_coords']");
            
            state.roundTrip.returnDestinationLat = locationData.lat;
            state.roundTrip.returnDestinationLng = locationData.lng;

            if (returnTownDestinationInputField) {
                returnTownDestinationInputField.value = state.roundTrip.returnDestinationTown;
            }
            if (returnNeighborhoodDestinationInputField) {
                returnNeighborhoodDestinationInputField.value = state.roundTrip.returnDestinationNeighborhood;
            }
            if (returnDestinationCoordsInputField) {
                returnDestinationCoordsInputField.value = JSON.stringify({
                lat: state.roundTrip.returnDestinationLat,
                lng: state.roundTrip.returnDestinationLng
                });
            }
    }
    }
    else if (tripType === "charter") {
         if (fieldType === "charterOriginPlaceId") {
            state.charter.charterOriginPlaceId = placeId;
            state.charter.charterOriginName = locationData.name;
            state.charter.charterOriginTown = locationData.town;
            state.charter.charterOriginNeighborhood = locationData.neighborhood;
            
            let charterTownOriginInputField = document.querySelector("input[name='charter_town_origin']");
            let charterNeighborhoodOriginInputField = document.querySelector("input[name='charter_neighborhood_origin']");
            let charterOriginCoordsInputField = document.querySelector("input[name='charter_origin_coords']");
            
            // Save coordinates:
            state.charter.charterOriginLat = locationData.lat;
            state.charter.charterOriginLng = locationData.lng;
            
            if (charterTownOriginInputField) {
                charterTownOriginInputField.value = state.charter.charterOriginTown;
            }
            if (charterNeighborhoodOriginInputField) {
                charterNeighborhoodOriginInputField.value = state.charter.charterOriginNeighborhood;
            }
            if (charterOriginCoordsInputField) {
                charterOriginCoordsInputField.value = JSON.stringify({
                lat: state.charter.charterOriginLat,
                lng:  state.charter.charterOriginLng,
                });
            }
         }
         else if (fieldType === "charterDestinationPlaceId") {
            state.charter.charterDestinationPlaceId = placeId;
            state.charter.charterDestinationName = locationData.name;
            state.charter.charterDestinationTown = locationData.town;
            state.charter.charterDestinationNeighborhood = locationData.neighborhood;
            
            let charterTownDestinationInputField = document.querySelector("input[name='charter_town_destination']");
            let charterNeighborhoodDestinationInputField = document.querySelector("input[name='charter_neighborhood_destination']");
            let charterDestinationCoordsInputField = document.querySelector("input[name='charter_destination_coords']");
            
            // Save coordinates:
            state.charter.charterDestinationLat = locationData.lat;
            state.charter.charterDestinationLng = locationData.lng;
            
            if (charterTownDestinationInputField) {
                charterTownDestinationInputField.value = state.charter.charterDestinationTown;
            }
            if (charterNeighborhoodDestinationInputField) {
                charterNeighborhoodDestinationInputField.value = state.charter.charterDestinationNeighborhood;
            }
            if (charterDestinationCoordsInputField) {
                charterDestinationCoordsInputField.value = JSON.stringify({
                lat: state.charter.charterDestinationLat,
                lng:  state.charter.charterDestinationLng,
                });
            }
         }
        
    } 
    
    stateLogger.log('Trip state updated', state);
}

function toggleReturnFields(show) {
    // Define the input field names/IDs and additional elements to target the groups
    const fields = [
        "return_location_destination",
        "return_location_origin",    
        "return_date",      
        "return_time",      
        "Return Trip Heading"      
    ];

    // Loop through the fields
    fields.forEach((fieldName) => {
        let element;

        // Locate the input element by name or id
        if (fieldName === "Return Trip Heading") {
            // Locate the specific h3 element by checking all h3 elements and matching their text content
            const headings = Array.from(document.querySelectorAll("h3"));
            element = headings.find((h3) => h3.textContent.trim() === "Return Trip");
        } else {
            element = document.querySelector(`[name="${fieldName}"], #${fieldName}`);
        }

        if (element) {

             // Toggle the 'required' attribute
            if (show) {
                element.setAttribute("required", "required");
            } else {
                element.removeAttribute("required");
            }
            
            // Find the parent group (e.g., div with class="form-group")
            const group = element.closest(".form-group");
            if (group) {
                // Show or hide the group based on the `show` parameter
                group.style.display = show ? "block" : "none";
            }
        } else {
            console.warn(`Field with name or id "${fieldName}" not found.`);
        }
    });
}

let apiCallsInProgress = 0;

function disableButton(isDisabled, isProcessing = false, isCharter = false) {
    const elementId = isCharter ? "qlwoyv" : "ifkszj";

    // "qlwoyv" -> shuttle hire form 
    // "ifkszj" -> ride form 
    
    // Use querySelector to find the element by data-script-id
    const form = document.querySelector(`[data-element-id="${elementId}"]`);

    if (!form) {
        console.warn(`Element with data-element-id="${elementId}" not found.`);
        return;
    }

    const submitButton = form.querySelector("button.bricks-button[type='submit']");
    let buttonTextContent = submitButton.querySelector("span.text").textContent;
    let buttonText = submitButton.querySelector("span.text").innerText;
  
    if (submitButton) {
        submitButton.disabled = isDisabled;

        if (isProcessing) {

          submitButton.querySelector("span.text").innerText = 'Processing...'; // Change button text
            // buttonText.setInnerText = 'Processing...'; // Change button text
        } else {
      // Restore original button text (if available)
       submitButton.querySelector("span.text").innerText = 'Check Pricing & Availability'; 
    }
  } else {
    console.warn('Button element not found.');
  }
}

// Manage API call lifecycle and button state
function startApiCall(isCharter = false) {
    apiCallsInProgress++;
    updateButtonState(isCharter);
}

function endApiCall(isCharter = false) {
    if (apiCallsInProgress !== 0) {
        apiCallsInProgress--;
        
    }
    updateButtonState(isCharter);
   
}

function updateButtonState(isCharter = false) {

    const isDisabled = apiCallsInProgress > 0; // Disable if there are ongoing API calls

    disableButton(isDisabled, isDisabled, isCharter); // Pass `isProcessing` as true if the button should show "Processing..."
}

function initialisePointsOfInterestSelect() {
    const poiSelect = document.querySelector("select[name='charter_poi']");
    const poiOtherInput = document.querySelector("input[name='charter_poi_other']");
    const poiOtherGroup = poiOtherInput.closest('.form-group');
    
    // Hide the "Other" input by default
    if (poiOtherGroup) {
        poiOtherGroup.style.display = "none";
    }

    // Show/hide the "Other" input based on the dropdown selection
    poiSelect.addEventListener("change", function() {
        const isOther = this.value === "Other";
        poiOtherGroup.style.display = isOther ? "" : "none";
        isOther
          ? poiOtherInput.setAttribute("required", "required")
          : poiOtherInput.removeAttribute("required");
        
    });
}

function initialiseTabListener() {
    // Get the tab container and the target element
    // const tabContainer = document.getElementById('brxe-6cbc22'); //.tab-menu -> this is the tab menu class
    const tabContainer = document.querySelector('.tab-menu');
    const targetElement = document.querySelector('.expandable'); // Selecting by class

    if (!tabContainer || !targetElement) return; // Ensure elements exist before proceeding
    
    // Query all tab elements inside the container
    const tabs = tabContainer.querySelectorAll('.tab-title');
    tabs.forEach(tab => {
    tab.addEventListener('click', function() {
      // Optionally remove your custom class from the target element first
      targetElement.classList.remove('active-padding');
    
      // Check if the clicked tab is the one we care about ("brxe-ifdvis")
      if (this.classList.contains('brxe-ifdvis') || this.querySelector('.brxe-ifdvis')) {
          
      // if (this.id === 'brxe-ifdvis' || this.querySelector('#brxe-ifdvis')) {
            // Add a class to the target element (e.g., a class that adds padding)
            targetElement.classList.add('active-padding');
          }
        });
    });
}

function initialiseErrorListener() {
      // Locate each form
      const charterForm = document.querySelector('[data-element-id="qlwoyv"]');
      const rideForm = document.querySelector('[data-element-id="ifkszj"]');

      // Locate each tab
      const charterTab = document.querySelector('.brxe-tgqfor');
      const rideTab = document.querySelector('.brxe-fjtytr');
    
      // Locate each support block
      const charterBlock = charterTab.querySelector('.support-block');
      const rideBlock = rideTab.querySelector('.support-block');
    
      // Attach the listeners
      attachErrorListener(rideForm, rideBlock);
      attachErrorListener(charterForm, charterBlock);
}

// Helper function to attach a listener that shows the block on error
function attachErrorListener(form, block) {
    if (!form || !block) return;
    
    form.addEventListener('submit', () => {
        setTimeout(() => {
          // Bricks sets .bricks-form-error on invalid fields
          const errors = form.querySelectorAll('.error');
          if (errors.length > 0) {
            block.style.display = 'flex';
          }
        }, 2000);
    });
}

// Initialize Autocomplete for All Fields Using forEach
function initGoogleAutocomplete() {

    const inputFields = [
        { selector: 'input[name="location_origin"]', fieldType: 'originPlaceId', tripType: 'one-way'},
        { selector: 'input[name="location_destination"]', fieldType: 'destinationPlaceId', tripType: 'one-way' },
        { selector: 'input[name="return_location_origin"]', fieldType: 'returnOriginPlaceId', tripType: 'roundtrip'},
        { selector: 'input[name="return_location_destination"]', fieldType: 'returnDestinationPlaceId', tripType: 'roundtrip'},
        { selector: 'input[name="charter_location_origin"]', fieldType: 'charterOriginPlaceId', tripType: 'charter'},
        { selector: 'input[name="charter_location_destination"]', fieldType: 'charterDestinationPlaceId', tripType: 'charter'},
    ];

    toggleReturnFields(false);

    initialiseTripTypeRadio();
    
    inputFields.forEach(({ selector, fieldType, tripType }) => {
        const elements = document.querySelectorAll(selector);
        const inputField = document.querySelector(selector);
        elements.forEach(element => {
            if (element) {
                attachAutoComplete(element, fieldType, tripType);
            } else {
                console.warn(`Input field not found for selector: ${selector}`);
            }
        });
    });

    initialisePointsOfInterestSelect();

    initialiseTabListener();

    initialiseErrorListener();

    attachPayloadToFormData();
};

// Global for Google's callback
window.initGoogleAutocomplete = initGoogleAutocomplete;

// IMPORTANT: ensure we call this **even if** Google callback fails
document.addEventListener('DOMContentLoaded', attachPayloadToFormData);


window.dumpPayload = () =>
    WSB.makeLogger('WSB: Payload').log(JSON.stringify(buildBookingPayload(), null, 2));
