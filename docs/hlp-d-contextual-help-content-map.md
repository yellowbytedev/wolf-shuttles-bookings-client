# Contextual Help Content Map (HLP-D)

## 1. Purpose and Scope

This document is a **content/config map only**. It does not implement Driver.js, Tippy.js, Floating UI, or any runtime help engine. It does not modify PHP, JavaScript, forms, fixtures, DB schema, REST endpoints, WooCommerce/session/cart/order logic, pricing, blockouts, or vehicle availability. It does not build multi-day UI or multi-trip UI.

The goal of HLP-D is to provide a single, stable, config-ready map of help content for the existing booking forms and future multi-day / multi-trip areas, using the stable data attributes and field keys defined in M3B.

## 2. Content Principles

Help content must follow these rules:

- **short, useful, non-salesy** — every sentence should help the user complete the form faster; no promotional language.
- **help explains what to do next** — not how the product works globally.
- **no wall-of-text** — body copy should be one or two short sentences maximum.
- **no hover-only dependency** — desktop may use focus or click; mobile must use tap.
- **mobile/touch friendly** — copy and layout must work on narrow viewports without horizontal scrolling.
- **validation messages remain separate** — help is proactive guidance; validation is reactive error state. Do not merge them.
- **no customer/location/token data stored in help state** — help system must never persist names, emails, phones, addresses, place IDs, coordinates, or tokens. It may persist only dismissed help IDs or tour versions.

## 3. Help Item Schema Proposal

A future help engine should consume objects shaped like this:

```yaml
id: string
selector: string
field_key: string
form_section: string
context: string[]
title: string
body: string
trigger: string[]
placement: string
gate: string
tour_step: string | null
mobile_behaviour: string
dismiss_behaviour: string
accessibility_note: string
version: string
```

| Property | Purpose | Example |
|---|---|---|
| `id` | Unstable help item identifier for analytics/dismissal | `help-passengers` |
| `selector` | CSS or data-attribute selector to attach to | `input[data-ws-help="passengers"]` |
| `field_key` | Stable M3B field key | `passengers` |
| `form_section` | Logical form area | `passengers` |
| `context` | Where the item applies | `["book_a_ride", "shuttle_hire"]` |
| `title` | Short headline shown in the tooltip/tour bubble | `Passengers` |
| `body` | One or two sentences of guidance | `Enter the number of people travelling so we can show the right vehicle.` |
| `trigger` | How help opens | `["focus", "click"]` |
| `placement` | Preferred popover position | `top` |
| `gate` | M3A feature gate that must be enabled | `enable_contextual_help` |
| `tour_step` | Linked tour step if any | `book_a_ride_step_1` |
| `mobile_behaviour` | Mobile-specific override | `tap` |
| `dismiss_behaviour` | How help closes | `click_outside` |
| `accessibility_note` | A11y guidance for this item | `Esc closes bubble; focus moves to trigger.` |
| `version` | Content schema version | `"hlp-d-1"` |

## 4. Field-Level Help Map

All selectors use the stable data attributes from M3B. Gate dependencies reference M3A gates only.

### 4.1 Passengers

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="passengers"]` |
| `field_key` | `passengers` |
| `form_section` | `passengers` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Passengers |
| `body` | Enter the number of people travelling so we can show the right vehicle. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | `book_a_ride_step_1` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Esc closes bubble; focus returns to field. |

### 4.2 Baby Seats

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="baby_seats"]` |
| `field_key` | `baby_seats` |
| `form_section` | `passengers` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Baby seats |
| `body` | Add any baby seats or infant carriers needed for the trip. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Esc closes bubble; focus returns to field. |

### 4.3 Check-in Bags

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="check_in_bags"]` |
| `field_key` | `check_in_bags` |
| `form_section` | `luggage` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Check-in bags |
| `body` | Count large luggage that goes into the cargo hold. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Esc closes bubble; focus returns to field. |

### 4.4 Carry-on Bags

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="carry_on_bags"]` |
| `field_key` | `carry_on_bags` |
| `form_section` | `luggage` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Carry-on bags |
| `body` | Count small bags you want to keep with you in the cabin. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Esc closes bubble; focus returns to field. |

### 4.5 Trailer

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="trailer"]` |
| `field_key` | `trailer` |
| `form_section` | `addons` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Trailer |
| `body` | Enable if you need a trailer for extra cargo or equipment. |
| `trigger` | `focus`, `click` |
| `placement` | `right` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Esc closes bubble; checkbox state is unchanged on dismiss. |

### 4.6 Oversize Luggage

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="oversize_luggage"]` |
| `field_key` | `oversize_luggage` |
| `form_section` | `addons` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Oversize luggage |
| `body` | Enable for items like surfboards, bicycles, or large musical instruments. |
| `trigger` | `focus`, `click` |
| `placement` | `right` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Esc closes bubble; checkbox state is unchanged on dismiss. |

### 4.7 Outbound From (Pickup Location)

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="pickup_location"]:not([data-wsb-context="shuttle_hire"])` |
| `field_key` | `outbound_from` |
| `form_section` | `outbound_locations` |
| `context` | `book_a_ride` |
| `title` | Pickup location |
| `body` | Start typing and choose the exact address from the dropdown. This lets us calculate your route accurately. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | `book_a_ride_step_2` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Screen reader should announce: pick-up location, choose from dropdown. |

### 4.8 Outbound To (Drop-off Location)

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="dropoff_location"]:not([data-wsb-context="shuttle_hire"])` |
| `field_key` | `outbound_to` |
| `form_section` | `outbound_locations` |
| `context` | `book_a_ride` |
| `title` | Drop-off location |
| `body` | Start typing and choose the exact destination from the dropdown so we can compute distance and pricing. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | `book_a_ride_step_3` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Screen reader should announce: drop-off location, choose from dropdown. |

### 4.9 Outbound Pickup Date

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="pickup_date"]:not([data-wsb-context="shuttle_hire"])` |
| `field_key` | `outbound_pickup_date` |
| `form_section` | `outbound_datetime` |
| `context` | `book_a_ride` |
| `title` | Pickup date |
| `body` | Choose the date you want to be collected. The earliest allowed date matches the transfer lead time. |
| `trigger` | `focus`, `click` |
| `placement` | `bottom` |
| `gate` | `enable_contextual_help` |
| `tour_step` | `book_a_ride_step_4` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Esc closes bubble; date picker remains open until closed explicitly. |

### 4.10 Outbound Pickup Time

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="pickup_time"]:not([data-wsb-context="shuttle_hire"])` |
| `field_key` | `outbound_pickup_time` |
| `form_section` | `outbound_datetime` |
| `context` | `book_a_ride` |
| `title` | Pickup time |
| `body` | Pick the time you want the shuttle to arrive. Times are in 5-minute steps. |
| `trigger` | `focus`, `click` |
| `placement` | `bottom` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Esc closes bubble; time picker remains open until closed explicitly. |

### 4.11 Return From

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="pickup_location"][data-wsb-context="book_a_ride"]` |
| `field_key` | `return_from` |
| `form_section` | `return_locations` |
| `context` | `book_a_ride` |
| `title` | Return pickup location |
| `body` | Where should we collect you for the return leg? Choose it from the dropdown. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Only visible when trip type is Return. |

### 4.12 Return To

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="dropoff_location"][data-wsb-context="book_a_ride"]` |
| `field_key` | `return_to` |
| `form_section` | `return_locations` |
| `context` | `book_a_ride` |
| `title` | Return drop-off location |
| `body` | Where should the return leg end? Choose the exact address from the dropdown. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Only visible when trip type is Return. |

### 4.13 Return Pickup Date

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="pickup_date"][data-wsb-context="book_a_ride"]` |
| `field_key` | `return_pickup_date` |
| `form_section` | `return_datetime` |
| `context` | `book_a_ride` |
| `title` | Return date |
| `body` | Pick the date for your return transfer. Blocked dates cannot be selected. |
| `trigger` | `focus`, `click` |
| `placement` | `bottom` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Only visible when trip type is Return. |

### 4.14 Return Pickup Time

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="pickup_time"][data-wsb-context="book_a_ride"]` |
| `field_key` | `return_pickup_time` |
| `form_section` | `return_datetime` |
| `context` | `book_a_ride` |
| `title` | Return time |
| `body` | Set the pickup time for your return leg. Return trips use the same 5-hour lead time. |
| `trigger` | `focus`, `click` |
| `placement` | `bottom` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Only visible when trip type is Return. |

### 4.15 Outbound Additional Stop

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="additional_stop"]:not([data-wsb-context="shuttle_hire"])` |
| `field_key` | `outbound_additional_stop` |
| `form_section` | `outbound_additional_stop` |
| `context` | `book_a_ride` |
| `title` | Additional stop |
| `body` | Add an optional stop between pickup and drop-off. Enable the toggle first, then choose the address from the dropdown. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help`, `enable_additional_stops` |
| `tour_step` | `additional_stops_step_2` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Help is inert when the additional stop toggle is disabled. |

### 4.16 Return Additional Stop

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="additional_stop"][data-wsb-context="book_a_ride"]` |
| `field_key` | `return_additional_stop` |
| `form_section` | `return_additional_stop` |
| `context` | `book_a_ride` |
| `title` | Return additional stop |
| `body` | Add an optional stop on the return leg. Enable the toggle, then choose the address from the dropdown. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help`, `enable_additional_stops` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Help is inert when the additional stop toggle is disabled. |

### 4.17 Charter Pickup Location

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="pickup_location"][data-wsb-context="shuttle_hire"]` |
| `field_key` | `charter_pickup_location` |
| `form_section` | `charter_locations` |
| `context` | `shuttle_hire` |
| `title` | Pickup location |
| `body` | Where should the charter start? Choose the address from the dropdown so routing is accurate. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | `shuttle_hire_step_1` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Charter tab has its own help context; does not duplicate Book a Ride tooltips. |

### 4.18 Charter Drop-off Location

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="dropoff_location"][data-wsb-context="shuttle_hire"]` |
| `field_key` | `charter_dropoff_location` |
| `form_section` | `charter_locations` |
| `context` | `shuttle_hire` |
| `title` | Drop-off location |
| `body` | Where should the charter end? Pick the exact place from the dropdown. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Charter tab has its own help context. |

### 4.19 Charter Pickup Time

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="pickup_time"][data-wsb-context="shuttle_hire"]` |
| `field_key` | `charter_pickup_time` |
| `form_section` | `charter_datetime` |
| `context` | `shuttle_hire` |
| `title` | Start time |
| `body` | The time the chauffeur should be ready at pickup. |
| `trigger` | `focus`, `click` |
| `placement` | `bottom` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Charter tab has its own help context. |

### 4.20 Charter Drop-off Time

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="dropoff_time"][data-wsb-context="shuttle_hire"]` |
| `field_key` | `charter_dropoff_time` |
| `form_section` | `charter_datetime` |
| `context` | `shuttle_hire` |
| `title` | End time |
| `body` | The latest time you expect to need the vehicle before return. |
| `trigger` | `focus`, `click` |
| `placement` | `bottom` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Charter tab has its own help context. |

### 4.21 Charter Additional Stop

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="additional_stop"][data-wsb-context="shuttle_hire"]` |
| `field_key` | `charter_additional_stop` |
| `form_section` | `additional_stop` |
| `context` | `shuttle_hire` |
| `title` | Additional stop |
| `body` | Add an optional stop during your charter. Enable the toggle, then choose the address from the dropdown. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help`, `enable_additional_stops` |
| `tour_step` | `additional_stops_step_3` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Help is inert when the additional stop toggle is disabled. |

### 4.22 Charter Points of Interest

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="charter_poi"]` |
| `field_key` | `charter_poi` |
| `form_section` | `charter_poi` |
| `context` | `shuttle_hire` |
| `title` | Points of interest |
| `body` | List landmarks, estates, or stops you want included in the itinerary. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help`, `enable_charter_poi_fields` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Plain text field; no address selection here. |

### 4.23 Charter Notes

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="charter_notes"]` |
| `field_key` | `charter_notes` |
| `form_section` | `charter_notes` |
| `context` | `shuttle_hire` |
| `title` | Charter notes |
| `body` | Add any special requests or timing constraints we should know about. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Plain text field; no address selection here. |

### 4.24 Submit Button

| Property | Value |
|---|---|
| `selector` | `button[data-wsb-preview-submit]` |
| `field_key` | `submit_button` |
| `form_section` | `actions` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Submit |
| `body` | Submit to preview the local payload and validation result. This does not create a real booking yet. |
| `trigger` | `focus`, `click` |
| `placement` | `bottom` |
| `gate` | `enable_contextual_help` |
| `tour_step` | `book_a_ride_step_5` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Native button; Esc does not submit, only closes help. |

## 5. Google Places Help Copy

These items apply wherever a location field uses Google Places autocomplete.

### 5.1 Selecting from Dropdown

| Property | Value |
|---|---|
| `selector` | `input[data-ws-place-role]` |
| `field_key` | (dynamic by location) |
| `form_section` | (matches location section) |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Choose address |
| `body` | Please choose the address from the dropdown. This gives us the exact place needed to calculate your route accurately. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | `google_places_step_2` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Screen reader should announce dropdown results and selection result. |

### 5.2 Stale Address After Editing

| Property | Value |
|---|---|
| `selector` | `.wsb-booking-client-field--place-stale input[data-ws-place-role]` |
| `field_key` | (dynamic by location) |
| `form_section` | (matches location section) |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Address changed |
| `body` | You edited this address after selecting it. Please select it again from the dropdown so we have an accurate place. |
| `trigger` | `focus` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | `google_places_step_3` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Combined with stale class and field-level validation; do not block keyboard users. |

### 5.3 Why Free Text Is Not Enough

| Property | Value |
|---|---|
| `selector` | `input[data-ws-place-role]:not(.wsb-booking-client-field--place-selected)` |
| `field_key` | (dynamic by location) |
| `form_section` | (matches location section) |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Why dropdown? |
| `body` | Free text is not enough for routing. A dropdown selection gives us the place ID, coordinates, and exact address snapshot the booking side needs. |
| `trigger` | `focus` |
| `placement` | `top` |
| `gate` | `enable_contextual_help`, `enable_google_places_required` |
| `tour_step` | `google_places_step_1` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Do not show this help when field already has a valid selection. |

### 5.4 Missing Place Selection

| Property | Value |
|---|---|
| `selector` | `input[data-ws-place-role]:not(.wsb-booking-client-field--place-selected)` |
| `field_key` | (dynamic by location) |
| `form_section` | (matches location section) |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Place not selected |
| `body` | This location is still missing a selection. Open the dropdown and pick the exact place before continuing. |
| `trigger` | `blur`, `submit` |
| `placement` | `top` |
| `gate` | `enable_contextual_help`, `enable_google_places_required` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Do not duplicate server-side validation error styling. |

### 5.5 Additional Stop Place Selection

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="additional_stop"][data-ws-place-role="stop"]` |
| `field_key` | `outbound_additional_stop`, `return_additional_stop`, `charter_additional_stop` |
| `form_section` | `additional_stop`, `outbound_additional_stop`, `return_additional_stop` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Additional stop address |
| `body` | Please choose the additional stop from the dropdown. That gives us the exact place needed for this leg of the route. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help`, `enable_additional_stops` |
| `tour_step` | `additional_stops_step_3` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Inert when the additional stop toggle is disabled. |

## 6. Additional Stops Help Copy

### 6.1 When to Use an Additional Stop

| Property | Value |
|---|---|
| `selector` | `.wsb-booking-client-additional-toggle-label input[data-ws-feature-gate="enable_additional_stops"]` |
| `field_key` | `outbound_additional_stop`, `return_additional_stop`, `charter_additional_stop` |
| `form_section` | `additional_stop` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | When to use additional stops |
| `body` | Use an additional stop if you need to pick up or drop off between the main pickup and destination. |
| `trigger` | `focus` |
| `placement` | `right` |
| `gate` | `enable_contextual_help`, `enable_additional_stops` |
| `tour_step` | `additional_stops_step_1` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Focus is on the checkbox; do not trap focus. |

### 6.2 How It Affects the Route

| Property | Value |
|---|---|
| `selector` | `.wsb-booking-client-additional-stop-field:not(.wsb-booking-client-hidden)` |
| `field_key` | (dynamic by additional stop) |
| `form_section` | `outbound_additional_stop`, `return_additional_stop` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Route impact |
| `body` | Adding a stop updates the route sequence. The final route and pricing are confirmed on the booking site. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help`, `enable_additional_stops` |
| `tour_step` | `additional_stops_step_4` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Section is hidden when the feature gate is off; help must not expose hidden fields. |

### 6.3 Why Each Stop Needs a Dropdown Selection

| Property | Value |
|---|---|
| `selector` | `input[data-ws-place-role="stop"]` |
| `field_key` | `outbound_additional_stop`, `return_additional_stop`, `charter_additional_stop` |
| `form_section` | `outbound_additional_stop`, `return_additional_stop`, `additional_stop` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Dropdown for stops |
| `body` | Every additional stop needs a place ID and coordinates so the route can be calculated correctly. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help`, `enable_additional_stops` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Screen reader should announce stop role and selection status. |

### 6.4 Final Route and Pricing Confirmation

| Property | Value |
|---|---|
| `selector` | `.wsb-booking-client-additional-stop-field:not(.wsb-booking-client-hidden)` |
| `field_key` | (dynamic by additional stop) |
| `form_section` | `outbound_additional_stop`, `return_additional_stop` |
| `context` | `book_a_ride`, `shuttle_hire` |
| `title` | Pricing confirmation |
| `body` | The stop adds distance and time. The final route and pricing are confirmed on the booking side, not here. |
| `trigger` | `focus` |
| `placement` | `bottom` |
| `gate` | `enable_contextual_help`, `enable_additional_stops` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Help must not claim pricing authority. |

## 7. Shuttle Hire / Charter Help Copy

### 7.1 Pickup and Drop-off Meaning

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="pickup_location"][data-wsb-context="shuttle_hire"]` |
| `field_key` | `charter_pickup_location`, `charter_dropoff_location` |
| `form_section` | `charter_locations` |
| `context` | `shuttle_hire` |
| `title` | Pickup and drop-off |
| `body` | Pickup is where the service starts; drop-off is where it ends. Both need addresses chosen from the dropdown. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help` |
| `tour_step` | `shuttle_hire_step_1` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Charter tab has its own help context. |

### 7.2 Start and End Time Meaning

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="pickup_time"][data-wsb-context="shuttle_hire"], input[data-ws-help="dropoff_time"][data-wsb-context="shuttle_hire"]` |
| `field_key` | `charter_pickup_time`, `charter_dropoff_time` |
| `form_section` | `charter_datetime` |
| `context` | `shuttle_hire` |
| `title` | Start and end time |
| `body` | Start time is when the chauffeur should be ready. End time is the latest service finish time. End time must be after start time. |
| `trigger` | `focus`, `click` |
| `placement` | `bottom` |
| `gate` | `enable_contextual_help` |
| `tour_step` | `shuttle_hire_step_2` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Charter tab has its own help context. |

### 7.3 Trailer and Oversize Luggage Meaning

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="trailer"], input[data-ws-help="oversize_luggage"]` |
| `field_key` | `trailer`, `oversize_luggage` |
| `form_section` | `addons` |
| `context` | `shuttle_hire` |
| `title` | Trailer and oversize luggage |
| `body` | Use these toggles if you need extra cargo space or to transport items like bikes, surfboards, or large equipment. |
| `trigger` | `focus`, `click` |
| `placement` | `right` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Checkbox state is unchanged on dismiss. |

### 7.4 POI and Notes Meaning

| Property | Value |
|---|---|
| `selector` | `input[data-ws-help="charter_poi"], input[data-ws-help="charter_notes"]` |
| `field_key` | `charter_poi`, `charter_notes` |
| `form_section` | `charter_poi`, `charter_notes` |
| `context` | `shuttle_hire` |
| `title` | Points of interest and notes |
| `body` | Use POI to suggest landmarks or stops. Use notes for special requests or timing constraints. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_contextual_help`, `enable_charter_poi_fields` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Plain text fields; no address selection here. |

### 7.5 Same-day Charter vs Future Multi-day Charter

| Property | Value |
|---|---|
| `selector` | `[data-wsb-charter-section]` |
| `field_key` | `charter_pickup_time`, `charter_dropoff_time` |
| `form_section` | `charter_datetime` |
| `context` | `shuttle_hire` |
| `title` | Same-day charter |
| `body` | This form currently supports same-day charters with a single start and end time. Multi-day charters are planned for a future build. |
| `trigger` | `focus`, `click` |
| `placement` | `bottom` |
| `gate` | `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Does not mention unbuilt multi-day UI as interactive. |

## 8. Future Multi-Day Help Placeholders

These items are **future / disabled** until the multi-day builder exists. Do not wire them into any runtime config yet.

### 8.1 Day Card

| Property | Value |
|---|---|
| `selector` | `[data-ws-help="day_card"]` |
| `field_key` | `charter_day_id` |
| `form_section` | `charter_days` |
| `context` | `shuttle_hire` |
| `title` | Day card |
| `body` | Each day in a multi-day charter is shown as a card. Complete all fields before saving. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_multi_day_charters`, `enable_contextual_help` |
| `tour_step` | `future_multi_day_step_1` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Placeholder only; element does not exist yet. |
| `status` | `future` |

### 8.2 Add Day

| Property | Value |
|---|---|
| `selector` | `button[data-ws-help="add_day"]` |
| `field_key` | `charter_days` |
| `form_section` | `charter_days` |
| `context` | `shuttle_hire` |
| `title` | Add day |
| `body` | Adds a new day card to your multi-day charter itinerary. |
| `trigger` | `click` |
| `placement` | `top` |
| `gate` | `enable_multi_day_charters`, `enable_contextual_help` |
| `tour_step` | `future_multi_day_step_2` |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Placeholder only; element does not exist yet. |
| `status` | `future` |

### 8.3 Duplicate Day

| Property | Value |
|---|---|
| `selector` | `button[data-ws-help="duplicate_day"]` |
| `field_key` | `charter_days` |
| `form_section` | `charter_days` |
| `context` | `shuttle_hire` |
| `title` | Duplicate day |
| `body` | Copies the current day so you can reuse its pickup, drop-off, and timing. |
| `trigger` | `click` |
| `placement` | `top` |
| `gate` | `enable_multi_day_charters`, `enable_day_duplicate_delete`, `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Placeholder only; element does not exist yet. |
| `status` | `future` |

### 8.4 Delete Day

| Property | Value |
|---|---|
| `selector` | `button[data-ws-help="delete_day"]` |
| `field_key` | `charter_days` |
| `form_section` | `charter_days` |
| `context` | `shuttle_hire` |
| `title` | Delete day |
| `body` | Removes this day from the itinerary. You cannot undo this action. |
| `trigger` | `click` |
| `placement` | `top` |
| `gate` | `enable_multi_day_charters`, `enable_day_duplicate_delete`, `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Placeholder only; element does not exist yet. |
| `status` | `future` |

### 8.5 Drag Handle

| Property | Value |
|---|---|
| `selector` | `[data-ws-help="drag_handle"]` |
| `field_key` | `charter_days` |
| `form_section` | `charter_days` |
| `context` | `shuttle_hire` |
| `title` | Reorder days |
| `body` | Drag the handle to reorder days in your itinerary. |
| `trigger` | `click` |
| `placement` | `top` |
| `gate` | `enable_multi_day_charters`, `enable_drag_drop_itinerary_ordering`, `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Placeholder only; element does not exist yet. |
| `status` | `future` |

### 8.6 Move Up/Down

| Property | Value |
|---|---|
| `selector` | `[data-ws-help="move_day"]` |
| `field_key` | `charter_days` |
| `form_section` | `charter_days` |
| `context` | `shuttle_hire` |
| `title` | Move day |
| `body` | Use the up/down arrows to move a day earlier or later in the itinerary. |
| `trigger` | `click` |
| `placement` | `top` |
| `gate` | `enable_multi_day_charters`, `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Placeholder only; element does not exist yet. |
| `status` | `future` |

### 8.7 Day Pickup Location

| Property | Value |
|---|---|
| `selector` | `[data-ws-help="day_pickup_location"]` |
| `field_key` | `charter_day_pickup_location` |
| `form_section` | `charter_days` |
| `context` | `shuttle_hire` |
| `title` | Day pickup location |
| `body` | Where should the chauffeur collect for this day? Choose from the dropdown. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_multi_day_charters`, `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Placeholder only; element does not exist yet. |
| `status` | `future` |

### 8.8 Day Drop-off Location

| Property | Value |
|---|---|
| `selector` | `[data-ws-help="day_dropoff_location"]` |
| `field_key` | `charter_day_dropoff_location` |
| `form_section` | `charter_days` |
| `context` | `shuttle_hire` |
| `title` | Day drop-off location |
| `body` | Where should this day's service end? Choose from the dropdown. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_multi_day_charters`, `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Placeholder only; element does not exist yet. |
| `status` | `future` |

### 8.9 Day Start/End Time

| Property | Value |
|---|---|
| `selector` | `[data-ws-help="day_times"]` |
| `field_key` | `charter_day_start_time`, `charter_day_end_time` |
| `form_section` | `charter_days` |
| `context` | `shuttle_hire` |
| `title` | Day times |
| `body` | Set when the chauffeur should start and finish for this day. End time must be after start time. |
| `trigger` | `focus`, `click` |
| `placement` | `bottom` |
| `gate` | `enable_multi_day_charters`, `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Placeholder only; element does not exist yet. |
| `status` | `future` |

### 8.10 Day Notes

| Property | Value |
|---|---|
| `selector` | `[data-ws-help="day_notes"]` |
| `field_key` | `charter_day_notes` |
| `form_section` | `charter_days` |
| `context` | `shuttle_hire` |
| `title` | Day notes |
| `body` | Add any special requests or timing constraints for this day. |
| `trigger` | `focus`, `click` |
| `placement` | `top` |
| `gate` | `enable_multi_day_charters`, `enable_contextual_help` |
| `tour_step` | null |
| `mobile_behaviour` | `tap` |
| `dismiss_behaviour` | `click_outside` |
| `accessibility_note` | Placeholder only; element does not exist yet. |
| `status` | `future` |

## 9. Guided Tour Content Outline

Each tour is a sequential series of help items. Steps are numbered and may be skipped if a gate is disabled.

### 9.1 Book a Ride Tour

| Step | Field Key | Title | Body |
|---|---|---|---|
| 1 | `passengers` | How many people? | Enter the number of travellers so we can match the right vehicle size. |
| 2 | `outbound_from` | Pickup location | Type and choose the exact address from the dropdown. |
| 3 | `outbound_to` | Drop-off location | Type and choose the destination from the dropdown. |
| 4 | `outbound_pickup_date` | Pickup date | Select when you need to be collected. |
| 5 | `outbound_pickup_time` | Pickup time | Select the preferred time of day. |
| 6 | `submit_button` | Check pricing | Click to preview the payload and validation. |

**Trigger**: First visit to Book a Ride tab, or explicit "Tour me" button.

**Gate**: `enable_guided_form_tour`

### 9.2 Shuttle Hire Tour

| Step | Field Key | Title | Body |
|---|---|---|---|
| 1 | `charter_pickup_location` | Pickup location | Enter where the charter should start. Choose from the dropdown. |
| 2 | `charter_dropoff_location` | Drop-off location | Enter where the charter should end. Choose from the dropdown. |
| 3 | `charter_pickup_time` | Start time | Set when the chauffeur should be ready. |
| 4 | `charter_dropoff_time` | End time | Set the latest finish time. Must be after start time. |
| 5 | `trailer` | Need a trailer? | Enable if you need extra cargo space. |
| 6 | `oversize_luggage` | Oversize luggage? | Enable for large items like bikes or surfboards. |
| 7 | `charter_poi` | Points of interest | List any landmarks or stops you want included. |
| 8 | `charter_notes` | Special requests | Add any notes about timing constraints or preferences. |

**Trigger**: First visit to Shuttle Hire tab, or explicit "Tour me" button.

**Gate**: `enable_guided_form_tour`

### 9.3 Google Places Selection Tour

| Step | Field Key | Title | Body |
|---|---|---|---|
| 1 | `outbound_from` | Location selection | Start typing and pick an address from the dropdown. |
| 2 | `outbound_to` | Destination selection | Same for the drop-off location. |
| 3 | - | Why dropdown? | The dropdown gives us the place ID and coordinates needed for accurate pricing. |
| 4 | - | Editing warning | If you edit after selecting, pick it again from the dropdown. |
| 5 | - | Continue | The form is ready when all location fields have selected addresses. |

**Trigger**: Focus on first location field when no place is selected.

**Gate**: `enable_guided_form_tour`, `enable_google_places_required`

### 9.4 Additional Stops Tour

| Step | Field Key | Title | Body |
|---|---|---|---|
| 1 | `outbound_additional_stop` | Enable stops | Toggle on if you need an extra stop between pickup and drop-off. |
| 2 | `outbound_additional_stop` | Add stop | Choose the stop address from the dropdown. |
| 3 | - | Route impact | Adding a stop changes the route. Pricing is confirmed on the booking side. |
| 4 | - | Return stop | You can also add stops on the return leg. |
| 5 | - | Continue | Continue with your booking when done. |

**Trigger**: First focus on additional stop toggle when gate is enabled.

**Gate**: `enable_guided_form_tour`, `enable_additional_stops`

### 9.5 Future Multi-Day Builder Tour

| Step | Field Key | Title | Body |
|---|---|---|---|
| 1 | `add_day` | Add a day | Click + Add to create a new day card. |
| 2 | `day_pickup_location` | Day pickup | Enter where the chauffeur should collect for this day. |
| 3 | `day_dropoff_location` | Day drop-off | Enter where this day's service should end. |
| 4 | `day_times` | Day times | Set start and end time for the day. |
| 5 | `duplicate_day` | Duplicate | Use Duplicate to copy a day's settings to a new day. |
| 6 | `delete_day` | Remove | Use Delete to remove a day from the itinerary. |
| 7 | `drag_handle` | Reorder | Drag to change the order of days. |

**Trigger**: First visit to multi-day builder (when implemented).

**Gate**: `enable_multi_day_charters`, `enable_guided_form_tour`

## 10. Privacy/Security Notes

Confirm these constraints are enforced before any help implementation:

- **no payloads/tokens/secrets in help content** — help copy must never contain booking tokens, signed envelopes, HMAC signatures, or any payload fragments.
- **no addresses/place IDs/coordinates stored in help state** — localStorage, sessionStorage, or cookies may store only dismissed help IDs or tour version numbers.
- **localStorage/cookies may store only dismissed help IDs/tour versions later** — no personal data should be persisted.
- **no customer name/email/phone required in initial handoff** — marketing form does not collect these as required fields.
- **marketing remains non-authoritative for pricing/routing** — help must not claim the marketing form calculates final prices or routes.

## 11. Implementation Handoff Notes

Future implementation should:

- **use M3A gates** — read `enable_contextual_help`, `enable_additional_stops`, `enable_multi_day_charters`, etc. from `window.WSB_BOOKING_CLIENT_FORM.featureGates`.
- **use M3B data attributes** — selectors like `input[data-ws-help]`, `button[data-ws-help]`, etc. are stable and guaranteed to exist in production.
- **stay disabled in production by default** — all help features must default to disabled (false) in production/staging environments per M3A.
- **start local only** — help should be opt-in or auto-enabled only in local/development environments.
- **validate accessibility before staging** — ensure keyboard navigation, screen reader announcements, and ESC dismissal work correctly.

## 12. Final Report

1. **Files reviewed**:
   - `docs/m3x-contextual-help-guided-assistance-plan.md`
   - `docs/m3b-form-field-semantics-and-gated-scaffolding.md`
   - `docs/m3a-feature-gate-config-foundation.md`
   - `docs/google-places-quote-ready-handoff.md`
   - `docs/booking-payload-v2-contract.md`
   - `inc/class-booking-field-registry.php`
   - `inc/class-booking-client-form-shortcode.php`
   - `assets/js/booking-client-form.js`
   - `booking-site/app/public/wp-content/plugins/ws-bookings/docs/phase-2x-security-privacy-logging-audit.md`

2. **Files changed**: None. This is documentation only.

3. **Help map created**: `hlp-d-contextual-help-content-map.md` (this file).

4. **Field help entries included**: 24 field-level entries covering passengers, baby_seats, check_in_bags, carry_on_bags, trailer, oversize_luggage, outbound_from, outbound_to, outbound_pickup_date, outbound_pickup_time, return_from, return_to, return_pickup_date, return_pickup_time, outbound_additional_stop, return_additional_stop, charter_pickup_location, charter_dropoff_location, charter_pickup_time, charter_dropoff_time, charter_additional_stop, charter_poi, charter_notes, submit_button.

5. **Guided tour outlines included**: Book a Ride tour, Shuttle Hire tour, Google Places selection tour, Additional Stops tour, Future Multi-Day Builder tour.

6. **Future multi-day placeholders included**: Day card, Add day, Duplicate day, Delete day, Drag handle, Move up/down, Day pickup location, Day drop-off location, Day start/end time, Day notes. All marked as `status: future`.

7. **Privacy/security constraints confirmed**:
   - No payloads, tokens, secrets in help content
   - No addresses/place IDs/coordinates stored in help state
   - localStorage may store only dismissed help IDs
   - No customer name/email/phone required initially
   - Marketing is non-authoritative for pricing/routing

8. **Confirmation no runtime changes**: All content is configuration-ready JSON/YAML. No PHP, JavaScript, forms, fixtures, DB schema, REST endpoints, WooCommerce/session/cart/order logic, pricing, blockouts, or vehicle availability are modified. No multi-day UI or multi-trip UI is built.

---

*Document version: HLP-D v1.0 | Last updated: 2026-07-02*