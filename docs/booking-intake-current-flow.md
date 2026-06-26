# Booking Intake Current Flow Audit — Marketing Site

## Scope

This audit covers the marketing-site legacy snippets imported into the `ws-bookings-client` plugin. The current live/local form is on:

```text
https://wolfshuttles.local/cape-town-shuttles/
```

The booking system currently receives handover URLs shaped like:

```text
https://bookings.wolfshuttles.local/?hash=<hmac-hash>
```

## Plugin and folder context

Marketing plugin:

```text
wp-content/plugins/ws-bookings-client
```

Booking-system plugin:

```text
wp-content/plugins/ws-bookings
```

Legacy snippets currently live under a plugin-controlled snippets folder, expected to be somewhere like:

```text
ws-bookings-client/inc/fluent-snippets/
```

or:

```text
ws-bookings-client/inc/snippets-legacy/
```

## Important legacy files

### `js/5-calculate-distance-v2.js`

This is the main frontend intake file. It currently handles:

- one-way / return / charter state
- Google Places autocomplete
- place IDs, names, towns, neighbourhoods, and coordinates
- distance and duration calls
- HQ / CTIA distance logic
- airport pickup, airport drop-off, and city transfer detection
- empty-leg / dispatch / return-fee flags
- toll detection
- button disabled/processing state
- hidden field population
- `booking_payload_json` injection into Bricks form submissions

### `php/15-submit-booking-form-and.php`

This is the main PHP submit, validation, mapping, and handover file. It currently handles:

- Bricks custom form action
- Bricks validation filter
- form ID detection
- one-way / return payload mapping
- charter payload mapping
- minimum lead-time validation
- blockout validation
- distance validation
- tracking / UTM capture
- HMAC hash creation
- posting the booking payload to the booking system
- redirecting the user to the booking site with `?hash=...`

Known Bricks form IDs from the legacy flow:

```text
ifkszj = transfer / point-to-point form
qlwoyv = charter form
```

These IDs must not become the new data contract.

### `php/7-api-call-to-google.php`

Provides AJAX proxy endpoints for:

- Google Distance Matrix / distance calculation
- Google Places details
- Google geocoding
- place ID lookup by name
- HERE toll lookup

These proxy concepts are worth keeping, but the new intake layer should wrap them behind cleaner names/services.

### `php/6-enqueue-google-maps-api.php`

Loads Google Places and calls:

```js
window.initGoogleAutocomplete
```

### `php/10-helper-functions.php`

Provides environment-aware booking URLs, including local/staging/live booking site base URLs.

### `php/16-create-localised-variable-for.php`

Creates the legacy AJAX variable:

```js
myAjax.ajaxurl
```

### `php/2-test-2.php`

Handles timepicker behaviour and AM/PM labels.

### `js/30-create-debugger.js`

Provides localStorage/query-string debug logging helpers.

### `js/28-add-tooltip-to-additional.js` and `css/29-form-tooltip-styling.css`

Adds tooltip behaviour/styling for additional options such as trailer and oversize luggage.

## Current data flow

The current flow is:

```text
User opens marketing form
→ JS initialises Google autocomplete and timepickers
→ User selects origin/destination/return/charter fields
→ JS stores place IDs, labels, coords, town, neighbourhood
→ JS calls distance/duration endpoints
→ JS derives service type and distance flags
→ JS checks tolls
→ JS writes multiple hidden Bricks fields
→ JS injects `booking_payload_json` into Bricks AJAX submission
→ PHP reads Bricks fields plus `booking_payload_json`
→ PHP validates blockouts, lead time, and distances
→ PHP maps the form data into a legacy flat booking array
→ PHP signs the payload with HMAC hash
→ PHP posts data to the booking system
→ Booking system stores/retrieves the hash payload
→ User redirects to bookings site with `?hash=...`
```

## Overlapping data sources

The current system has two overlapping sources of booking state:

```text
1. Bricks form fields and hidden fields
2. JS-generated `booking_payload_json`
```

This is one of the main areas to simplify.

## What to keep

Keep these behaviours/concepts:

- Google Places autocomplete
- Google/HERE proxy endpoint pattern
- distance and duration collection
- HQ/CTIA distance calculations
- airport pickup / airport drop-off / city transfer detection
- toll detection
- minimum lead-time validation
- blockout validation
- tracking and UTM capture
- secure handover to booking site
- debug logger concept
- one-way and return support
- charter support as the next service type

## What not to keep as future architecture

Do not keep these as the new architecture:

- one massive JS file controlling the entire booking intake
- Bricks hashed field IDs as the data contract
- hidden fields as the source of truth
- full frontend JS state snapshot as the payload contract
- mixed transfer, charter, validation, and hash logic in one PHP file
- frontend-derived pricing flags being fully trusted by the backend

## Architecture split recommended

Split the system into these concerns:

```text
UI/component layer
- renders fields
- toggles one-way/return/charter states
- shows dynamic UX

Client-side assist layer
- Google autocomplete
- route/distance helper calls
- lightweight state needed for the current form interaction

Server-side intake layer
- schema ownership
- normalisation
- validation
- payload building
- handover to booking site

Booking-site intake layer
- accepts legacy v1 and new v2 payloads
- creates itinerary/trip records later
```

## Backwards compatibility decision

The old flow should continue to work.

The new flow should create `schema_version: "2.0"` payloads. The booking site should eventually branch on payload version:

```text
legacy/no schema version → existing legacy processing
schema_version = 2.0 → new v2 intake processing
```

For the immediate marketing-side milestone, the new form may support both handover modes:

```text
legacy_hash = current `?hash=...` flow
v2_token = future `/bookings/?booking_token=...&trip_id=...` flow
```
