# Booking-Site v2 Receiver — Dry-Run Plan

## Purpose

Document the proposed dry-run receiver for `BookingPayload v2` in the booking-site plugin (`ws-bookings`). This is a planning document only. Do not implement the receiver until this plan is reviewed and approved.

## Current Marketing Dry-Run Handover Envelope

The marketing plugin (`ws-bookings-client`) sends a dry-run envelope via:

- **REST endpoint:** `POST /wp-json/ws-bookings-client/v1/handover-preview`
- **Envelope shape:**

```json
{
  "handover_version": "2.0",
  "schema_version": "2.0",
  "mode": "dry_run",
  "action": "preview",
  "request_id": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "created_at": "2026-07-01T12:00:00+00:00",
  "expires_at": "2026-07-01T13:00:00+00:00",
  "source_site": "marketing",
  "target_site": "booking",
  "payload": { /* BookingPayload v2 */ },
  "integrity": {
    "algorithm": "hash_hmac_sha256",
    "signature": "<HMAC-SHA256 hex>",
    "signed_fields": [
      "handover_version", "schema_version", "action",
      "request_id", "created_at", "expires_at", "payload"
    ]
  },
  "meta": {
    "preview_only": true,
    "real_handover_enabled": false
  }
}
```

## Proposed Booking-Site Dry-Run Receiver Endpoint

**Path:** `POST /wp-json/ws-bookings/v2/intake-dry-run`

**Purpose:** Accept a marketing handover envelope, verify its integrity and expiry, validate the payload, and return a dry-run response — **without creating any database records, tokens, cart items, or orders.**

### Why a separate endpoint?

- Keeps the live `/intake` endpoint clean and fast
- Allows marketing to test the full handshake end-to-end before enabling real submissions
- Provides a staging/proving ground for HMAC verification, expiry validation, and payload adaptation
- Aligns with the existing scaffold in `inc/v2/class-v2-intake-controller.php`

## Dry-Run Receiver Flow

```text
Marketing sends POST /wp-json/ws-bookings/v2/intake-dry-run
  with { handover_envelope: { ... } }
    ↓
Booking site receives
1. Check Content-Type is JSON
2. Extract envelope
3. Verify HMAC/signature
4. Verify expiry (expires_at > now)
5. Verify schema_version == "2.0"
6. Normalise/validate BookingPayload v2 (or trust marketing validation)
7. Adapt payload to trip data (via V2_Payload_Adapter)
8. Return dry-run response
    ↓
Response: dry-run acknowledgement (no DB writes)
```

## Step-by-Step Processing

### 1. Request Parsing
- Expect `Content-Type: application/json`
- Extract JSON body; require top-level `handover_envelope` object
- Return `400` if body is empty or not JSON

### 2. HMAC / Signature Verification
- Receive `integrity.signature` and `integrity.signed_fields` from envelope
- Reconstruct the signature message:
  1. Extract the listed `signed_fields` from the envelope in key-sorted order
  2. Canonicalise: recursively sort associative keys, stable JSON encode (`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`)
  3. Compute `hash_hmac('sha256', canonical_json, shared_secret)`
  4. Compare with `integrity.signature` using `hash_equals()`
- **Shared secret resolution:** booking-site constant (`WSB_BOOKINGS_V2_HANDOVER_SECRET`) or environment variable
- **Production:** reject if verification fails (return `401`)
- **Dry-run / local dev:** allow empty secret (skip verification) only if `WP_DEBUG` is true and a config flag permits it
- **Never** expose the secret or signature computation details in responses

### 3. Expiry Validation
- Parse `expires_at` as ISO 8601
- If `expires_at` is in the past, return `400` with `error: "envelope_expired"`
- Envelope is valid for **1 hour** after `created_at` (matching marketing's `EXPIRY_HOURS`)

### 4. Schema Version Validation
- Require `schema_version: "2.0"` at the envelope level
- Require `schema_version: "2.0"` inside the nested `payload`
- Return `400` with `error: "unsupported_schema_version"` if mismatch

### 5. BookingPayload v2 Normalisation / Validation
- **Option A (trust marketing):** Assume payload is already validated by marketing's normaliser/validator. Still perform defensive checks:
  - Required top-level fields: `source`, `service_group`, `service_type`, `trip_type`, `passengers`, `legs`
  - Each leg: `type`, `from.label`, `to.label`, `pickup_date`, `pickup_time`
  - Charter legs: `dropoff_time` required, end time must be after start time
  - Return trips: at least 2 legs
- **Option B (re-validate):** Port the marketing validator logic to booking site for defense-in-depth
- **Recommended:** Option A for dry-run, Option B for live intake

### 6. Payload Adaptation
- Use `WSB_Bookings_V2_Payload_Adapter::to_trip_data()` to convert `BookingPayload v2` into a trip data array
- Map `legs[]` to `legs_json` (encoded)
- Store the full raw payload as `payload_json`
- Extract top-level fields: `trip_type`, `service_type`, pickup/dropoff locations, passengers, date/time

### 7. Dry-Run Response (No DB Writes)
Return a JSON response confirming what *would* happen:

```json
{
  "ok": true,
  "mode": "dry_run",
  "envelope": {
    "handover_version": "2.0",
    "schema_version": "2.0",
    "action": "preview",
    "request_id": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
    "source_site": "marketing",
    "target_site": "booking"
  },
  "payload": { /* received BookingPayload v2 */ },
  "trip_preview": {
    "trip_type": "one_way",
    "service_type": "city_transfer",
    "pickup_location": "Cape Town International Airport",
    "dropoff_location": "Stellenbosch Central",
    "pickup_date": "2026-07-15",
    "pickup_time": "14:00",
    "passengers": 2
  },
  "validation": {
    "schema_version_ok": true,
    "hmac_ok": true,
    "expiry_ok": true,
    "payload_ok": true
  },
  "meta": {
    "dry_run": true,
    "no_db_writes": true,
    "no_token_created": true,
    "no_cart_created": true,
    "processed_at": "2026-07-01T12:05:00+00:00"
  }
}
```

## Failure Responses

### HMAC Verification Failed
```json
{
  "ok": false,
  "error": "invalid_signature",
  "message": "HMAC signature verification failed.",
  "meta": { "dry_run": true }
}
```

### Envelope Expired
```json
{
  "ok": false,
  "error": "envelope_expired",
  "message": "The handover envelope has expired.",
  "meta": { "dry_run": true }
}
```

### Unsupported Schema Version
```json
{
  "ok": false,
  "error": "unsupported_schema_version",
  "message": "Only schema_version 2.0 is supported.",
  "meta": { "dry_run": true }
}
```

### Invalid Payload
```json
{
  "ok": false,
  "error": "invalid_payload",
  "message": "Payload validation failed.",
  "validation_errors": [
    { "field": "legs.0.from", "code": "required", "message": "Origin is required." }
  ],
  "meta": { "dry_run": true }
}
```

## No DB / Token / Cart / Order Creation

During the dry-run phase:
- **No `ws_bookings_itineraries` inserts**
- **No `ws_bookings_trips` inserts**
- **No booking tokens created**
- **No WooCommerce cart items created**
- **No orders created**
- **No WooCommerce session modifications**

The dry-run receiver is a **read-only validation + adaptation exercise**.

## Future Phases

### Phase A: Dry-Run Receiver (current task)
- Implement the endpoint above in `ws-bookings`
- Write PHP unit tests for HMAC verification, expiry checks, payload adaptation
- Wire to existing `V2_Intake_Controller` scaffold

### Phase B: Receiver Fixture Runner
- Port marketing's fixture runner to booking-site side
- Test that every marketing fixture produces the expected dry-run response
- Assert envelope fields, trip_preview shape, HMAC verification results

### Phase C: Booking Token + Draft Itinerary Storage
- Wire `WSB_Bookings_Booking_Token_Service` to real token generation
- Create itinerary rows in `ws_bookings_itineraries`
- Store payload JSON + trip JSON
- Implement expiry/cleanup

### Phase D: Route / Toll / Classification / Availability Integration
- Call Google Distance Matrix for `distance_meters`, `duration_seconds`
- Call HERE Maps for toll detection
- Run direction classification (toward/away/lateral/neutral)
- Check vehicle availability against WooCommerce products + blockouts

### Phase E: WooCommerce Cart / Session Integration
- One trip = one WooCommerce cart line item
- Link cart items to itinerary/trip IDs
- Support "Add another booking" flow
- Render collapsible trip line items in checkout

## Security Rules

1. **HMAC verification is mandatory for live/production use.** Never accept unsigned or incorrectly signed envelopes in non-debug environments.
2. **Shared secrets must never be logged or exposed in API responses.**
3. **Expiry enforcement is mandatory.** Never accept stale envelopes.
4. **Rate limit the endpoint** to prevent brute-force HMAC attempts and DoS.
5. **Use `hash_equals()`** for constant-time signature comparison.
6. **Log verification failures** (without exposing secrets) for security monitoring.
7. **CORS:** Restrict to the marketing site origin(s) in production.
8. **Nonce / additional auth:** Consider adding a shared API key or IP allowlist alongside HMAC for defense-in-depth.

## Open Questions

1. Should the booking site re-validate the payload (Option B) or trust marketing's validation (Option A)?
2. Should the booking site own the 1-hour expiry window, or should marketing negotiate a longer TTL?
3. How should the booking site handle `blockouts` from marketing — verify, re-evaluate, or ignore (current scaffold ignores)?
4. Should `validation_flags.google_place_snapshots_ready` block the dry-run receiver, or just warn?
5. What is the correct WooCommerce product linkage for charter vs. transfer vehicles?
6. Should the v2 endpoint coexist with the legacy `/wp-json/booking-api/v1/receive-booking` or replace it?
7. How does the booking-token flow interact with existing `ws_trip_id` + `ws_trip_sig` cookie system?

## Mapping: Marketing Payload → Booking-Site Concepts

| Marketing `BookingPayload v2` | Booking-Site Concept | Notes |
|------------------------------|---------------------|-------|
| `schema_version: "2.0"` | Accept only v2 | Routing gate: unknown schema → legacy |
| `trip_type` | `ws_bookings_trips.trip_type` | `one_way`, `return`, `charter` |
| `service_type` | `ws_bookings_trips.service_type` | `airport_pickup`, `airport_dropoff`, `city_transfer`, `charter_hire` |
| `legs[0]` | First trip leg | `pickup_date`, `pickup_time`, `from`, `to` |
| `legs[1]` (return) | Second trip leg | `return_date`, `return_time`, `from`, `to` |
| `legs[].dropoff_time` | Charter end time | Only for charter legs |
| `legs[].stops[]` | Future multi-stop support | Currently stored in `legs_json` |
| `passengers` | Trip passenger count | Also used for vehicle filtering |
| `baby_seats`, `check_in_bags`, `carry_on_bags` | Add-ons on trip | Mapped to WooCommerce cart item meta |
| `add_ons.trailer`, `add_ons.oversize_luggage` | Trip add-ons | Mapped to cart item meta / pricing modifiers |
| `customer.name/email/phone` | `ws_bookings_itineraries.*` | Stored on itinerary parent |
| `route` | Populated by booking-side API | Marketing sends scaffold; booking fills with Google data |
| `charter` | Charter days / type | `enabled`, `type`, `days[]` mapped to trip meta |
| `blockouts` | Booking-site blockouts store | Marketing scaffold → booking-site evaluation |
| `place_snapshots` | Route/toll/classification input | `provider`, `place_id`, `lat`, `lng` used for Google/HERE calls |
| `validation_flags` | Diagnostic | Quote-ready flag, route verification flags |
| `meta.preview_only` | Dry-run vs live | `true` = no DB writes |
| `meta.handover_mode` | Flow control | `"preview"` vs `"live"` |
| `handover_envelope` | HMAC-authenticated request | Verified before processing |

## Booking-Site Files Involved

| File | Role |
|------|------|
| `inc/v2/class-v2-intake-controller.php` | REST route scaffolding (`/ws-bookings/v2/intake`) |
| `inc/v2/class-v2-payload-adapter.php` | Converts `BookingPayload v2` → trip data array |
| `inc/v2/class-booking-token-service.php` | Generates booking tokens |
| `inc/v2/class-itinerary-repository.php` | CRUD for `ws_bookings_itineraries` table |
| `inc/pricing/price-router.php` | Zone / Tariffs v2 / Charter pricing entry point |
| `inc/calendar/blockouts-validation.php` | Global + time-range blockout checks |
| `inc/calendar/blockouts-store.php` | Blockouts JSON file management |
| `inc/trips/session.php` | WooCommerce session + cookie-based trip state |
| `inc/trips/model.php` | Trip model helpers (`wsb_trip_is_return`, `wsb_trip_update`) |
| `ws-bookings.php` | Plugin bootstrap |
