# Legacy External Services Audit

## Overview

Audited legacy Fluent snippets for external service/API-style behavior to inform future BookingPayload v2 integration. Source: `inc/legacy-snippets/` directory.

## Files Inspected

| File | Type | Status |
|------|------|--------|
| `php/7-api-call-to-google.php` | PHP | Active (via loader) |
| `php/6-enqueue-google-maps-api.php` | PHP | Active (conditional) |
| `js/5-calculate-distance-v2.js` | JS | Active (via loader) |
| `php/24-create-rest-endpoint-for.php` | PHP | Active (via loader) |
| `php/15-submit-booking-form-and.php` | PHP | Active (via loader) |
| `php/10-helper-functions.php` | PHP | Active (via loader) |
| `php/11-register-api-endpoint-for.php` | PHP | Active (via loader) |
| `php/16-create-localised-variable-for.php` | PHP | Active (via loader) |

## Services/Integrations Found

### 1. Google Maps / Google Places API

**File:** `php/7-api-call-to-google.php`

**Purpose:** Route, distance, duration, geocoding, and place details lookups.

**Endpoints:**
- `https://maps.googleapis.com/maps/api/distancematrix/json` — Distance Matrix for route distance/duration
- `https://maps.googleapis.com/maps/api/place/findplacefromtext/json` — Place search by name
- `https://maps.googleapis.com/maps/api/place/details/json` — Place details by place_id
- `https://maps.googleapis.com/maps/api/geocode/json` — Reverse geocoding lat/lng to place_id

**AJAX Actions Registered:**
- `wp_ajax_calculate_distance` / `wp_ajax_nopriv_calculate_distance`
- `wp_ajax_get_place_details` / `wp_ajax_nopriv_get_place_details`
- `wp_ajax_get_place_geocode` / `wp_ajax_nopriv_get_place_geocode`
- `wp_ajax_fetch_place_id_by_name` / `wp_ajax_nopriv_fetch_place_id_by_name`

**Data Read/Written:**
- Reads: `GOOGLE_API_KEY` constant, origin/destination place_ids, lat/lng coordinates
- Writes: JSON responses with distance (text, meters), duration (text, seconds), place_id, town, neighborhood

**Future v2 Integration:**
- **Should migrate:** Distance/duration data for route scaffold
- **Should migrate:** Place details for location normalization
- **Not in scope for Phase 2:** Real-time API calls; keep as no-op scaffold

### 2. HERE Maps Routing API (Toll Lookup)

**File:** `php/7-api-call-to-google.php` (function `calculate_tolls`)

**Purpose:** Detect toll roads on routes using HERE's route comparison feature.

**Endpoint:** `https://router.hereapi.com/v8/routes`

**Data Read/Written:**
- Reads: `HERE_MAPS_API_KEY` constant, origin/destination coordinates
- Writes: `has_tolls` boolean, `toll_name`, `toll_names` array, `total` amount/currency, `fees` array, debug metadata

**Future v2 Integration:**
- **Should migrate:** Toll metadata for route options
- **Not in scope for Phase 2:** Real-time API calls; keep as no-op scaffold

### 3. Google Maps JavaScript (Autocomplete)

**File:** `php/6-enqueue-google-maps-api.php`, `js/5-calculate-distance-v2.js`

**Purpose:** Client-side place autocomplete for form inputs.

**Behavior:**
- Enqueues `https://maps.googleapis.com/maps/api/js?key={GOOGLE_API_KEY}&libraries=places&callback=initGoogleAutocomplete`
- Attaches `google.maps.places.Autocomplete` to origin/destination inputs
- Calls backend proxies (`fetch_place_id_by_name`, `fetch_google_geocode`, `get_place_details`) as fallback

**Data Read/Written:**
- Reads: Place selections from Google Maps, address components (locality, neighborhood)
- Writes: Hidden form fields with place_id, town, neighborhood, coordinates

**Future v2 Integration:**
- **Should migrate:** Autocomplete for location inputs
- **Not in scope for Phase 2:** Real-time API calls; keep as no-op scaffold

### 4. Booking-site Handover (Remote POST)

**File:** `php/15-submit-booking-form-and.php`

**Purpose:** Submit booking data to booking-system site via HMAC-secured POST.

**Endpoint:** `get_booking_url('/wp-json/booking-api/v1/receive-booking')` (booking-site)

**Behavior:**
- Builds flat data structure from form fields
- HMAC signs with `BOOKING_HASH_SECRET`
- `wp_remote_post` to booking-site endpoint
- Redirects to booking-site with hash parameter

**Future v2 Integration:**
- **Should replace with:** v2 handover envelope (`WSB_Client_Booking_Payload_V2_Handover_Service`)
- **Current status:** Legacy hash mode active; v2_token mode scaffolded

### 5. WordPress REST Endpoints (Local)

**File:** `php/24-create-rest-endpoint-for.php`

**Purpose:** Internal tracking/traveler count updates.

**Endpoints:**
- `POST /wp-json/ws/v1/traveler-count` — HMAC-secured traveler total updates
- `GET /wp-json/ws/v1/travelers` — Public traveler info read

**Future v2 Integration:**
- **Remain legacy-only:** Not part of new Booking Builder flow

### 6. CTIA Distance Logic (Geo Calculations)

**File:** `js/5-calculate-distance-v2.js`, `php/15-submit-booking-form-and.php`

**Purpose:** Calculate distance from/to Cape Town International Airport (CTIA) for dispatch/return fee decisions.

**Reference Point:**
- CTIA: lat=-33.9696, lng=18.5978, place_id=ChIJvQtA90JFzB0RkF7P43l1SEA

**Thresholds:**
- `pickup_far_threshold_km: 80` — If pickup >80km from CTIA, add dispatch fee
- `dropoff_near_threshold_km: 30` — If dropoff <30km from CTIA, add return fee

**Future v2 Integration:**
- **Should migrate:** Distance-to-HQ logic into PHP normalizer
- **Current status:** JS calculates; PHP should own this for v2

### 7. Direction Classification (Bearings)

**File:** `js/5-calculate-distance-v2.js`

**Purpose:** Classify trip direction relative to CTIA (`toward`, `away`, `lateral`).

**Future v2 Integration:**
- **Consider migrating:** Direction classification logic to PHP
- **Current status:** Client-side only; not critical for Phase 2

## Legacy-Only (No Migration Planned)

| Integration | Reason |
|-------------|--------|
| Traveler count API (`ws/v1/traveler-count`) | Separate analytics/tracking system |
| Timepicker initialization (`2-test-2.php`) | Bricks-specific UI enhancement |
| Redirect logic (`21-redirect-book-online.php`) | Temporary redirect for legacy flow |
| Tooltip styling (`29-form-tooltip-styling.css`) | Visual-only CSS |
| Debugger script (`30-create-debugger.js`) | Legacy debug UI |

## Recommended Adapter Scaffold

Create `inc/class-booking-external-services.php` with:

```php
// No-op placeholders returning payload-compatible arrays
get_route_scaffold(): array
get_toll_scaffold(): array
get_place_scaffold(): array
get_handover_scaffold(): array

// Feature flags (disabled by default)
is_google_enabled(): bool
is_here_enabled(): bool
is_handover_live(): bool
```

All methods should return safe empty structures without calling external APIs.
Feature flags control activation when the time comes.