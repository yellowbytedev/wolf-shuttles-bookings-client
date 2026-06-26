# Booking Intake and Multi-Trip Roadmap

## End goal

Build a modular Wolf Shuttles booking system where a customer or admin can create one itinerary containing multiple trips, then pay through one WooCommerce order.

Example future state:

```text
Itinerary #1001
├── One-way transfer: Stellenbosch → Somerset West
├── Return transfer: Cape Town Airport ↔ Camps Bay
└── Charter hire: 5-day wedding itinerary
```

## Phase 1 — Completed

Loose Fluent snippets were moved into plugin control for both:

```text
ws-bookings-client = marketing site
ws-bookings        = booking system site
```

## Phase 2 — Booking intake foundation

Build the new marketing-side intake layer beside the legacy flow.

Milestones:

1. Add documentation and agent instructions.
2. Define BookingPayload v2.
3. Build PHP payload builder, normaliser, validator, and booking-site client classes.
4. Build a shortcode shell: `[ws_booking_client_form]`.
5. Create a new official local page, e.g. `/booking-builder/`.
6. Support one-way and return transfers.
7. Keep charter scaffolded in the schema but not necessarily in the first UI.
8. Support both `legacy_hash` and `v2_token` handover modes during development.
9. Keep existing Bricks form untouched.
10. Add a minimal fixture-driven test runner.

## Phase 3 — Booking-site v2 intake

Add booking-system support for v2 payloads.

The booking site should branch like this:

```text
no schema_version / legacy payload → current processing
schema_version = 2.0              → new v2 processing
```

Initial v2 booking-site behaviour can still create one trip only.

## Phase 4 — Itinerary parent model

Add an itinerary parent table, likely:

```text
ws_bookings_itineraries
```

Then link existing trips to itineraries:

```text
ws_bookings_trips.itinerary_id
```

At first:

```text
one itinerary → one trip
```

This preserves current behaviour while preparing multi-trip support.

## Phase 5 — Trip-to-cart line item model

Change cart behaviour from:

```text
one active booking replaces/clears cart
```

to:

```text
one trip = one WooCommerce cart line item
```

Each cart item should store:

```text
itinerary_id
trip_id
selected_vehicle_id
quote_snapshot
```

## Phase 6 — Add another booking

Allow the user to add another trip under the same itinerary from the booking or checkout page.

Flow:

```text
Add another booking
→ open booking builder form/modal/page
→ create new trip under same itinerary
→ return to shuttle selection for that trip
→ add/update that trip as its own cart line item
```

## Phase 7 — Checkout/cart UX

Render each trip as a collapsible line item:

```text
One-way: Stellenbosch → Somerset West      R697
[Edit] [Remove] [Expand]
```

Expanded details should show route, passengers, luggage, add-ons, selected vehicle, and quote breakdown.

## Phase 8 — Admin itinerary builder

Allow operations/admin staff to create and edit itineraries manually.

Admin-created itineraries should support:

```text
non-expiring drafts
payment links
manual edits
future deposit/balance support
```

## Phase 9 — Modular pricing features

Once the data model is clean, add modular pricing features:

- return saver discount
- airport-only multiplier
- peak/holiday multiplier
- route choice pricing
- manual admin adjustment
- deposit/balance payments
- additional stops

Each feature should plug into a pricing pipeline rather than being hardcoded into form snippets.
