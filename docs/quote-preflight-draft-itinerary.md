# Quote Preflight / Draft Itinerary Warm-Up

## Purpose

Document the Quote Preflight / Draft Itinerary Warm-Up roadmap concept. This is a future performance optimisation where the marketing site sends a quote-ready draft payload to the booking site before final submit, allowing expensive booking-side work to begin early.

## Preflight Goal

### Problem Statement

When a user submits a booking request, the booking site must perform several expensive operations:

1. Route distance/duration calculation via Google Distance Matrix
2. Toll detection via HERE Maps
3. Direction classification (toward/away/lateral) for dispatch fees
4. Vehicle availability checks against WooCommerce product variations
5. Pricing calculation with multipliers and add-ons

These operations can introduce latency that impacts user experience, especially on mobile connections.

### Solution

The marketing Booking Builder becomes **quote-ready**, meaning it sends a **preflight payload** to the booking site after a debounce period. The booking site:

1. Creates or updates a **draft itinerary** in a non-committed state
2. Starts expensive route/toll/classification work early
3. Returns a **draft token** for referencing this work

When the user finalises their booking, the final payload includes the draft token. The booking site either:

- Reuses the preflight work if route-critical fields haven't changed
- Updates/recalculates if route-critical fields have changed

### Data Flow

```
User types in form → Debounced payload → Booking site draft creation
       ↓
User selects vehicle → Final submit with draft token → Booking site validates
       ↓
Booking site reuses preflight work or recalculates
```

---

## Quote-Ready Conditions

### Transfers (One-Way / Return)

A preflight payload is quote-ready when:

| Field | Required | Reason |
|-------|----------|--------|
| `service_group` | Yes | Determines vehicle pool |
| `service_type` | Yes | Airport vs. city transfer affects pricing |
| `trip_type` | Yes | One-way vs. return affects number of legs |
| `passengers` | Yes | Vehicle capacity filtering |
| `pickup_date` | Yes | Date picker value |
| `pickup_time` | Yes | Time picker value |
| `outbound.from.place_id` | Yes | Required for route, toll, classification |
| `outbound.to.place_id` | Yes | Required for route, toll, classification |
| `return.from.place_id` | Conditional | Required if `trip_type: "return"` |
| `return.to.place_id` | Conditional | Required if `trip_type: "return"` |
| `outbound.stops[].location.place_id` | Optional | Stops included in route fingerprint |

### Charters

A preflight payload is quote-ready when:

| Field | Required | Reason |
|-------|----------|--------|
| `service_group` | Yes | Must be `"charter"` |
| `service_type` | Yes | Must be `"charter_hire"` |
| `trip_type` | Yes | Must be `"charter"` |
| `passengers` | Yes | Vehicle capacity filtering |
| `charter.from.place_id` | Yes | Required for route, toll |
| `charter.to.place_id` | Yes | Required for route, toll |
| `charter.pickup_date` | Yes | Charter date |
| `charter.pickup_time` | Yes | Charter start time |
| `charter.dropoff_time` | Yes | Charter end time (affects duration pricing) |

---

## Autocomplete as Mandatory

### Production Requirement

The final production marketing form **must require Google Places autocomplete selection** for origin and destination fields.

### Reasons

1. **Place IDs:** Required by booking site for accurate distance/toll calculations
2. **Coordinates:** Required for direction/classification logic
3. **Reliability:** Free text addresses cannot be reliably resolved without API calls
4. **Consistency:** Place snapshots provide canonical location references

### Implementation Rules

- `place_snapshots` must be populated with:
  - `provider`: `"google_places"`
  - `place_id`: Non-null value
  - `lat`, `lng`: Non-null coordinates
  - `label`: Display label for humans
  - `formatted_address`: Full address string
- Free text input may exist only as:
  - Debug/troubleshooting mode
  - Fallback for edge cases
  - Not part of final quote-ready payload
- Payload validation should warn/error if place IDs are missing in production

---

## Draft Payload Shape

```json
{
  "preflight_version": "1.0",
  "mode": "draft_itinerary",
  "draft_token": null,
  "payload_hash": null,
  "route_fingerprint": null,
  "quote_ready": false,
  "payload": {
    "schema_version": "2.0",
    "source": "marketing_booking_builder",
    "service_group": "transfer",
    "service_type": "city_transfer",
    "trip_type": "one_way",
    "passengers": 1,
    "legs": [
      {
        "type": "outbound",
        "from": {
          "label": "Origin name",
          "place_id": "ChIJxxx",
          "lat": -33.9249,
          "lng": 18.4241
        },
        "to": {
          "label": "Destination name",
          "place_id": "ChIJyyy",
          "lat": -33.9333,
          "lng": 18.8467
        },
        "pickup_date": "2026-07-15",
        "pickup_time": "14:00"
      }
    ]
  }
}
```

### Field Definitions

| Field | Type | Description |
|-------|------|-------------|
| `preflight_version` | string | Preflight schema version |
| `mode` | string | Always `"draft_itinerary"` |
| `draft_token` | string\|null | Existing draft token for updates |
| `payload_hash` | string\|null | Hash of payload for change detection |
| `route_fingerprint` | string\|null | Key for route work reuse |
| `quote_ready` | boolean | Whether all quote-ready conditions met |
| `payload` | object | The BookingPayload v2 payload |

---

## Booking-Site Draft Response Shape

```json
{
  "ok": true,
  "draft_token": "draft_a1b2c3d4e5f",
  "status": "preflight_ready",
  "expires_at": "2026-06-29T13:00:00+02:00",
  "payload_hash": "sha256_hash_of_payload",
  "route_fingerprint": "origin_dest_return_origin_return_dest_date_time",
  "precomputed": {
    "route": true,
    "tolls": true,
    "classification": true,
    "pricing": true,
    "vehicle_filters": false
  },
  "estimated_duration": "1800s"
}
```

### Field Definitions

| Field | Type | Description |
|-------|------|-------------|
| `ok` | boolean | Request succeeded |
| `draft_token` | string | Token for later final submit |
| `status` | string | Current preflight status |
| `expires_at` | string | ISO timestamp when draft expires |
| `payload_hash` | string | Hash for validating unchanged payload |
| `route_fingerprint` | string | Key for route cache reuse |
| `precomputed` | object | What work has been completed |
| `estimated_duration` | string | Estimated time for remaining work |

---

## What Invalidates Preflight Work

### Route-Critical Fields (Triggers Full Recalculation)

| Field | Reason |
|-------|--------|
| `outbound.from.place_id` | Route origin changed |
| `outbound.to.place_id` | Route destination changed |
| `return.from.place_id` | Return leg origin changed |
| `return.to.place_id` | Return leg destination changed |
| `outbound.stops[].location.place_id` | Route waypoints changed |
| `pickup_date` / `return_pickup_date` | Date affects traffic patterns |
| `pickup_time` / `return_pickup_time` | Time affects pricing if traffic-sensitive |
| `service_group` | Different vehicle pools |
| `service_type` | Airport vs. city pricing rules |
| `charter.pickup_time` | Charter duration changes |
| `charter.dropoff_time` | Charter duration changes |

### Non-Route-Critical Fields (Affects Vehicle Filtering Only)

| Field | Reason |
|-------|--------|
| `passengers` | Vehicle capacity requirement |
| `baby_seats` | Baby seat availability per vehicle |
| `check_in_bags` | Large luggage capacity per vehicle |
| `carry_on_bags` | Carry-on capacity per vehicle |
| `trailer` | Trailer hitch requirement |
| `oversize_luggage` | Oversize luggage capability |

These fields should update vehicle filtering without forcing route/toll recalculation.

---

## No Route Cache Policy

### Cache Policy Rationale

Do **not** rely on broad route caches (e.g., "Airport to Stellenbosch") because:

1. Small distance differences significantly affect pricing
2. Exact place boundaries matter for dispatch/return fee calculations
3. Approximate caches introduce pricing uncertainty

### If Cache Is Implemented Later

Any route cache must be keyed by:

- Exact origin place ID
- Exact destination place ID
- Return leg place IDs (if applicable)
- All stop place IDs
- Date/time for traffic patterns
- Service type for pricing rules

Cache hits must fall back to recalculation if any route-critical field changes.

---

## Safety Rules

### No WooCommerce Cart/Orders During Preflight

- Draft itineraries **must not** create WooCommerce products
- Draft itineraries **must not** create cart items
- Draft itineraries **must not** create orders

### No Payment State During Preflight

- Draft itineraries **must not** involve payment
- No tokens or authorisations issued
- No payment intents created

### Draft Lifecycle

| Rule | Description |
|------|-------------|
| Auto-expiry | Drafts expire after configured TTL (e.g., 4 hours) |
| Final validation | Always runs before checkout, ignoring draft work |
| Cleanup | Old drafts purged via cron or lazy cleanup |
| Rate limiting | Debounced submission only (e.g., 500ms after last input) |
| Bot protection | Nonce required, rate limit per IP, spam detection |

### Performance vs. Correctness

> **Draft work is a performance optimisation, not a source of truth.**

The booking site must always be prepared to recalculate all values if:

- Draft expired
- Route-critical fields changed
- Preflight failed
- Recalculation requested

---

## Implementation Roadmap

### Prerequisites

1. Booking-site v2 intake endpoint must exist
2. Place Snapshots must be required for quote-ready payloads
3. Google Places autocomplete must be production-ready

### Phases

| Phase | Task |
|-------|------|
| **Phase 2P** | Define preflight endpoint contract on booking site |
| **Phase 2P+** | Add `get_preflight_scaffold()` to `WSB_Client_Booking_External_Services` |
| **Phase 2Q** | Add debounced preflight submission in Booking Builder JS |
| **Phase 2Q+** | Add `draft_token` to final submit flow |
| **Phase 2R** | Add vehicle card loading states for preflight in progress |
| **Phase 2S** | Add preflight validation fixtures |
| **Phase 2S+** | Browser MCP QA for draft token lifecycle |

### Current Status

- **Not implemented yet**
- **No live endpoint exists**
- Next implementation: Booking-site endpoint design or marketing consumer scaffold

---

## Relationship to Other Concepts

| Concept | Relationship |
|---------|--------------|
| Google Places autocomplete | **Mandatory** for quote-ready payloads |
| Place snapshots | **Required** for route fingerprinting |
| Route scaffold | Preflight returns actual route data, not scaffold |
| Vehicle blockouts | Preflight draft includes diagnostics only |
| Real-time pricing | Preflight returns estimated pricing, final submit confirms |
| Multi-trip itineraries | Preflight works per-trip; parent model in Phase 4 |