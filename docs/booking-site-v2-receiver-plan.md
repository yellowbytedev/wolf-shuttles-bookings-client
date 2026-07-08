# Booking-Site v2 Receiver — Implementation Plan

## Purpose

Document the proposed receiver for `BookingPayload v2` in the booking-site plugin (`ws-bookings`). This is a planning document for the v2 intake endpoint.

## Current Marketing Handover Envelope

The marketing plugin (`ws-bookings-client`) sends a handover envelope via:

- **REST endpoint:** `POST /wp-json/ws-bookings-client/v1/handover-preview`
- **Envelope shape:**

```json
{
  "handover_version": "2.0",
  "schema_version": "2.0",
  "action": "submit",
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
    "handover_mode": "live"
  }
}
```

## Proposed Booking-Site v2 Receiver Endpoint

**Path:** `POST /wp-json/ws-bookings/v2/intake`

**Purpose:** Accept a marketing handover envelope, verify its integrity and expiry, validate the payload, and return a response. During early phases, the response may indicate the validated data without yet creating database records, WooCommerce cart items, or orders.

### Early Phase Behavior

- Validates envelope signature and expiry
- Validates payload structure and business rules
- Returns validated preview data with status flags
- No database writes, cart items, or orders until later phases

## Receiver Flow

```text
Marketing sends POST /wp-json/ws-bookings/v2/intake
  with { handover_envelope: { ... } }
    ↓
Booking site receives
1. Check Content-Type is JSON
2. Extract envelope
3. Verify HMAC/signature
4. Verify expiry (expires_at > now)
5. Verify schema_version == "2.0"
6. Validate BookingPayload v2 structure
7. Adapt payload to trip data (via V2_Payload_Adapter)
8. Return validated response
    ↓
Response: validated payload with status flags (early phase) or booking token/redirect (later phase)
```

## Phase Implementation Flow

### Phase 1: Validation-Only Endpoint
- Validates envelope signature and expiry
- Validates payload structure and business rules
- Returns validated preview data with status flags
- No database writes, cart items, or orders until Phase 2

### Phase 2: Booking Token + Draft Itinerary
- Wire `WSB_Bookings_Booking_Token_Service` to real token generation
- Create itinerary rows in `ws_bookings_itineraries`
- Store payload JSON + trip JSON
- Implement expiry/cleanup

### Phase 3: Route / Toll / Classification / Availability
- Call Google Distance Matrix for `distance_meters`, `duration_seconds`
- Call HERE Maps for toll detection
- Run direction classification
- Check vehicle availability against WooCommerce products + blockouts

### Phase 4: WooCommerce Cart / Session Integration
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

1. Should the booking site re-validate the payload or trust marketing's validation? Consider defense-in-depth.
2. Should the booking site own the 1-hour expiry window, or should marketing negotiate a longer TTL?
3. How should the booking site handle `blockouts` from marketing — verify, re-evaluate, or ignore?
4. Should `validation_flags.google_place_snapshots_ready` block the intake receiver, or just warn?
5. What is the correct WooCommerce product linkage for charter vs. transfer vehicles?
6. Should the v2 endpoint coexist with the legacy `/wp-json/booking-api/v1/receive-booking` or replace it?
7. How does the booking-token flow interact with existing `ws_trip_id` + `ws_trip_sig` cookie system?

## Shared Contract: BookingPayload v2

The canonical data contract between marketing and booking sites prevents drift. Required fields:

```json
{
  "schema_version": "2.0",
  "source": "marketing_booking_builder",
  "service_group": "transfer",
  "service_type": "city_transfer",
  "trip_type": "one_way",
  "customer": {
    "name": "string",
    "email": "string",
    "phone": "string"
  },
  "passengers": "integer",
  "baby_seats": "integer",
  "check_in_bags": "integer",
  "carry_on_bags": "integer",
  "add_ons": {
    "trailer": "boolean",
    "oversize_luggage": "boolean"
  },
  "route": {
    "provider": "null|google_places",
    "distance_meters": "number|null",
    "duration_seconds": "number|null"
  },
  "charter": {
    "enabled": "boolean",
    "type": "string|null",
    "days": "array"
  },
  "validation_flags": {
    "google_place_snapshots_ready": "boolean"
  },
  "blockouts": {
    "authority": "booking_site"
  },
  "legs": [
    {
      "type": "outbound|return|charter",
      "from": { "label": "string", "place_id": "string|null", "lat": "number|null", "lng": "number|null" },
      "to": { "label": "string", "place_id": "string|null", "lat": "number|null", "lng": "number|null" },
      "pickup_date": "Y-m-d",
      "pickup_time": "H:i",
      "dropoff_time": "H:i|null",
      "stops": "array"
    }
  ]
}
```

### Handover Envelope Requirements

- Signed with HMAC-SHA256 using shared secret `WSB_CLIENT_V2_HANDOVER_SECRET`
- 1-hour expiry from `created_at`
- `action` field indicates intent: `"preview"`, `"fixture"`, `"submit"`
- `meta.handover_mode`: `"preview"` for testing, `"live"` for production handover

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
