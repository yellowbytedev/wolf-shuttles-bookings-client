# MARKETING-PAYLOAD-HANDOFF-AUDIT-001

**Date:** 2026-07-09  
**Auditor:** Kilo Agent  
**Scope:** Marketing plugin (`/booking-builder/`) handoff capability to booking system

---

## Executive Summary

| Question | Answer |
|----------|--------|
| Can current marketing form submit to booking system successfully? | **Partial** |
| Which form paths work? | Payload validation / preview only; no live handoff |
| Which form paths fail? | All paths fail to hand off to booking site (dry_run mode) |
| Primary blocker | Missing customer contact fields + dry_run handover mode |

The marketing forms produce a structurally valid v2 payload for transfers and charters, but:
1. **Customer contact fields (name, email, phone) are NOT collected in the UI**
2. **Handover service operates in dry_run mode only** - no actual HTTP call to booking site
3. **The form is intentionally in preview/testing phase** per Phase 2 development status

---

## Field Mapping Table

| Form Path | Visible UI Field | DOM/Input Name | JS State Key | Handoff Payload Key | Booking Receiver Expected Key | Status | Notes |
|-----------|---------------|----------------|--------------|---------------------|----------------------------|--------|-------|
| **All Paths** | Passengers | `passengers` | `passengers` | `payload.passengers` | `passengers` | OK | Lines 64-79 registry |
| **All Paths** | Baby seats | `baby_seats` | `baby_seats` | `payload.baby_seats` | `baby_seats` | OK | Lines 80-95 registry |
| **All Paths** | Check-in bags | `check_in_bags` | `check_in_bags` | `payload.check_in_bags` | `check_in_bags` | OK | Lines 96-111 registry |
| **All Paths** | Carry-on bags | `carry_on_bags` | `carry_on_bags` | `payload.carry_on_bags` | `carry_on_bags` | OK | Lines 112-127 registry |
| **All Paths** | Trailer | `trailer` | `trailer` | `payload.add_ons.trailer` | `add_ons.trailer` | OK | Lines 128-142 registry |
| **All Paths** | Oversize luggage | `oversize_luggage` | `oversize_luggage` | `payload.add_ons.oversize_luggage` | `add_ons.oversize_luggage` | OK | Lines 143-157 registry |
| **Book a Ride** | From | `outbound_from` | `outbound_from` | `legs[0].from.label` | `legs[0].from.label` | OK | Lines 158-174 registry |
| **Book a Ride** | To | `outbound_to` | `outbound_to` | `legs[0].to.label` | `legs[0].to.label` | OK | Lines 175-191 registry |
| **Book a Ride** | Pickup date | `outbound_pickup_date` | `outbound_pickup_date` | `legs[0].pickup_date` | `legs[0].pickup_date` | OK | Lines 192-209 registry |
| **Book a Ride** | Pickup time | `outbound_pickup_time` | `outbound_pickup_time` | `legs[0].pickup_time` | `legs[0].pickup_time` | OK | Lines 210-225 registry |
| **Return Trip** | Return from | `return_from` | `return_from` | `legs[1].from.label` | `legs[1].from.label` | OK | Lines 226-242 registry |
| **Return Trip** | Return to | `return_to` | `return_to` | `legs[1].to.label` | `legs[1].to.label` | OK | Lines 243-259 registry |
| **Return Trip** | Return date | `return_pickup_date` | `return_pickup_date` | `legs[1].pickup_date` | `legs[1].pickup_date` | OK | Lines 260-276 registry |
| **Return Trip** | Return time | `return_pickup_time` | `return_pickup_time` | `legs[1].pickup_time` | `legs[1].pickup_time` | OK | Lines 277-292 registry |
| **With Stop** | Additional stop | `outbound_additional_stop` | `outbound_additional_stop` | `legs[0].stops[].location` | `legs[0].stops[].location` | OK | Lines 310-327 registry |
| **Shuttle Hire** | Pickup location | `charter_pickup_location` | `charter_pickup_location` | `legs[0].from.label` | `legs[0].from.label` | OK | Lines 346-362 registry |
| **Shuttle Hire** | Drop-off location | `charter_dropoff_location` | `charter_dropoff_location` | `legs[0].to.label` | `legs[0].to.label` | OK | Lines 363-379 registry |
| **Shuttle Hire** | Pickup time | `charter_pickup_time` | `charter_pickup_time` | `legs[0].pickup_time` | `legs[0].pickup_time` | OK | Lines 380-395 registry |
| **Shuttle Hire** | Drop-off time | `charter_dropoff_time` | `charter_dropoff_time` | `legs[0].dropoff_time` | `legs[0].dropoff_time` | OK | Lines 396-411 registry |
| **Shuttle Hire** | POI | `charter_poi` | `charter_poi` | `legs[0].poi_intent` | `legs[0].poi_intent` | OK | Lines 430-444 registry |
| **Shuttle Hire** | Notes | `charter_notes` | `charter_notes` | `legs[0].notes` | `legs[0].notes` | OK | Lines 445-459 registry |
| **All Paths** | **Customer name** | *(NOT COLLECTED)* | *(NOT COLLECTED)* | `payload.customer.name` | `customer.name` | **MISSING** | Required by booking-site v2 intake |
| **All Paths** | **Customer email** | *(NOT COLLECTED)* | *(NOT COLLECTED)* | `payload.customer.email` | `customer.email` | **MISSING** | Required by booking-site v2 intake |
| **All Paths** | **Customer phone** | *(NOT COLLECTED)* | *(NOT COLLECTED)* | `payload.customer.phone` | `customer.phone` | **MISSING** | Required by booking-site v2 intake |
| **Multi-day** | Day date | `charter_day_date` | `charter_day_date` | `charter.days[].date` | `charter.days[].date` | OK | Lines 460-478 registry |
| **Multi-day** | Day start time | `charter_day_start_time` | `charter_day_start_time` | `charter.days[].start_time` | `charter.days[].start_time` | OK | Lines 479-496 registry |
| **Multi-day** | Day end time | `charter_day_end_time` | `charter_day_end_time` | `charter.days[].end_time` | `charter.days[].end_time` | OK | Lines 497-514 registry |
| **Multi-day** | Day pickup | `charter_day_pickup_location` | `charter_day_pickup_location` | `charter.days[].pickup_location` | `charter.days[].pickup_location` | OK | Lines 515-531 registry |
| **Multi-day** | Day dropoff | `charter_day_dropoff_location` | `charter_day_dropoff_location` | `charter.days[].dropoff_location` | `charter.days[].dropoff_location` | OK | Lines 532-548 registry |

---

## Test Cases

### 1. Book a Ride — One-way Transfer

**Form Path:** `/booking-builder/` → "Book a Ride" tab → one-way radio selected

**Payload Generated (from JS buildPayload):**
```json
{
  "schema_version": "2.0",
  "source": "marketing_booking_builder",
  "service_group": "transfer",
  "service_type": "city_transfer",
  "trip_type": "one_way",
  "customer": { "name": "", "email": "", "phone": "" },  // STALE EMPTY VALUES
  "passengers": 1,
  "add_ons": { "trailer": false, "oversize_luggage": false },
  "legs": [
    {
      "type": "outbound",
      "from": { "label": "<user-entered>", "place_id": "<from Google Places if selected>" },
      "to": { "label": "<user-entered>", "place_id": "<from Google Places if selected>" },
      "pickup_date": "tomorrow",
      "pickup_time": "08:00",
      "stops": [],
      "place_snapshots": { "from": {...}, "to": {...}, "stops": [] }
    }
  ],
  "validation_flags": { "google_place_snapshots_ready": false }  // FALSE without Google Places
}
```

**Submit Result:**
- URL: `/wp-json/ws-bookings-client/v1/handover-preview` (preview endpoint, NOT booking site)
- Response: Contains `ok`, `normalised_payload`, `handover_envelope`, `meta.preview_only: true`
- **No redirect to booking site occurs**

### 2. Book a Ride — Return Transfer

**Form Path:** `/booking-builder/` → "Book a Ride" tab → return radio selected

**Payload Generated:**
- Two legs: `outbound` and `return`
- `legs[1].from.label` = return_from
- `legs[1].to.label` = return_to
- `legs[1].pickup_date` = return_pickup_date

**Submit Result:** Same as one-way - preview only, no handoff

### 3. Book a Ride — Additional Stop

**Form Path:** `/booking-builder/` → "Book a Ride" tab → one-way + "Add additional stop"

**Payload Generated:**
- `legs[0].stops[]` populated when `additional_stop_enabled` is true
- `outbound_additional_stop_enabled` checkbox controls stop visibility

**Submit Result:** Same as one-way - preview only, no handoff

### 4. Shuttle Hire — Single-day Charter

**Form Path:** `/booking-builder/` → "Shuttle Hire" tab → same-day mode

**Payload Generated:**
```json
{
  "service_group": "charter",
  "service_type": "charter_hire",
  "trip_type": "charter",
  "legs": [{
    "type": "charter",
    "from": { "label": "charter_pickup_location" },
    "to": { "label": "charter_dropoff_location" },
    "pickup_date": "outbound_pickup_date",
    "pickup_time": "charter_pickup_time",
    "dropoff_time": "charter_dropoff_time"
  }],
  "charter": {
    "enabled": true,
    "type": "same_day",
    "days": [{
      "date": "...",
      "start_time": "...",
      "end_time": "...",
      "pickup_location": { "label": "..." },
      "dropoff_location": { "label": "..." }
    }]
  }
}
```

**Submit Result:** Preview only, no handoff

### 5. Shuttle Hire — Multi-day Charter

**Form Path:** `/booking-builder/` → "Shuttle Hire" tab → multi-day mode → "Add another day"

**Payload Generated:**
- Multiple day cards with date/start/end times
- `charter.days[]` array populated
- Each day has independent location fields

**Submit Result:** Preview only, no handoff

### 6. Plan Full Booking / Multi-trip

**Form Path:** `/booking-builder/` → "Plan Full Booking" tab

**Status:** Scaffold only - shows "Next phase" badge, "Add another trip" button disabled  
**Submit Result:** No functionality - preview placeholder only

---

## Submit Result Analysis

### Current Form Submit Flow

1. **Form Action:** `#` (no action) - `method="post"` but `event.preventDefault()` in JS
2. **Submit Handler:** `booking-client-form.js:2655-2670`
   - Calls `buildPayload(form, state)` - lines 656-810
   - Validates place snapshots exist - lines 720-746
   - Calls `refreshPreview()` and `refreshServerPreview()` - lines 2668-2669
   - **No redirect, no handoff call to booking site**

3. **Submit Target:** 
   - Marketing REST: `/wp-json/ws-bookings-client/v1/handover-preview`
   - Endpoint: `class-booking-payload-handover-preview-controller.php`
   - Response includes `meta.preview_only: true`, `real_handover_enabled: false`

### Why No Real Handoff?

| File | Line | Block |
|------|------|-------|
| `class-booking-payload-v2-handover-service.php` | 25 | `public const MODE = 'dry_run';` |
| `class-booking-payload-v2-handover-service.php` | 86-88 | `'preview_only' => true`, `'real_handover_enabled' => false` |
| `class-booking-payload-handover-preview-controller.php` | 110-113 | Response meta always sets `real_handover_enabled: false` |

---

## Blockers

### Critical Blockers (Prevent Working Handoff)

| # | Blockers | Files Involved | Resolution Required |
|---|----------|----------------|---------------------|
| 1 | Customer contact fields missing from UI | `class-booking-client-form-shortcode.php:46-278` - form HTML has no customer fields | Add name/email/phone inputs to form |
| 2 | Handover mode locked to dry_run | `class-booking-payload-v2-handover-service.php:25` | Change to `real` mode for production handoff |
| 3 | No HTTP call to booking site | `class-booking-payload-handover-preview-controller.php` - only validates, never POSTs to booking site | Implement actual handoff request |
| 4 | HMAC verification required | `class-handover-verifier.php:141-152` - rejects unsigned/untimed envelopes | Need matching `WSB_CLIENT_V2_HANDOVER_SECRET` on both sites |

### Field-Level Mismatches

| Missing Key | Source | Required By | Status |
|-------------|--------|-------------|--------|
| `customer.name` | Not collected | `class-v2-payload-adapter.php:52` | CRITICAL |
| `customer.email` | Not collected | `class-v2-payload-adapter.php:53` | CRITICAL |
| `customer.phone` | Not collected | `class-v2-payload-adapter.php:54` | CRITICAL |

### Charter Constraints (Intentional)

| Constraint | Source | Status |
|------------|--------|--------|
| Charter legs cannot have stops | `class-v2-intake-controller.php:304-306` | Business rule - stops cleared for charter type |
| Charter requires `dropoff_time` | `class-v2-intake-controller.php:297-303` | Validation enforced |
| Charter end time must be > start time | `class-v2-intake-controller.php:300-303` | Validation enforced |

---

## Recommended Implementation Sequence

### Minimal Changes for Working Handoff

1. **Add Customer Contact Fields (HIGH PRIORITY)**
   - File: `inc/class-booking-field-registry.php`
   - Add fields: `customer_name`, `customer_email`, `customer_phone`
   - Add to form shortcode in `inc/class-booking-client-form-shortcode.php`
   - Map to `buildPayload.customer` in `assets/js/booking-client-form.js:775-779`

2. **Enable Real Handover Mode**
   - File: `inc/class-booking-payload-v2-handover-service.php:25`
   - Change: `'dry_run'` → `'real'` (or add configuration flag)
   - File: `inc/class-booking-payload-handover-preview-controller.php:110-113`
   - Add logic to forward to `/wp-json/ws-bookings/v2/intake` when `real_handover_enabled: true`

3. **Configure Shared HMAC Secret**
   - Both sites need matching `WSB_CLIENT_V2_HANDOVER_SECRET` constant
   - Production: must be configured via environment/constant

### Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Customer data exposure in UI | Medium | Never display raw email/phone in client-side; use server validation |
| Place ID exposure in dev tools | Low | Place snapshots already visible in preview panel for QA purposes |
| Missing Google Places = no pricing | High | Validation warning already implemented; form blocks submit without selections |
| Charter stops silently dropped | Medium | Document in UI that charter does not support additional stops |

### Test Plan

1. **Unit Tests:** Run fixture tests against modified normalizer
   ```
   wp wsb-client test-payloads --file=tests/fixtures/booking-payload-v2-fixtures.json
   ```

2. **Browser Tests:**
   - Navigate to `/booking-builder/`
   - Fill forms with test data (fake names/emails)
   - Verify preview JSON includes customer fields
   - Submit and check for redirect to `/book-online/?booking_token=...`

3. **Booking-Site Tests:**
   - Verify intake endpoint accepts payload
   - Confirm booking intent created in database
   - Confirm vehicle-selection page reachable

---

## Architectural Notes

### Current Architecture (Preview Only)

```
Marketing Form (browser)
  ↓
booking-client-form.js::buildPayload()
  ↓
{ customer: { name:'', email:'', phone:'' } } ← STUB VALUES (blocker #1)
  ↓
/handover-preview (marketing REST)
  ↓
handover_service::build_envelope()
  ↓
Mode: 'dry_run'
real_handover_enabled: false (blocker #2)
  ↓
Response: ok, normalised_payload, meta.preview_only=true
```

### Required Architecture for Production

```
Marketing Form (browser)
  ↓
booking-client-form.js::buildPayload()
  ↓
{ customer: { name:'...', email:'...', phone:'...' } }
  ↓
POST /wp-json/ws-bookings/v2/intake (booking-site endpoint)
  ↓
handover_verifier::verify() → HMAC signature check
  ↓
v2_intake_controller::validate_payload() → Required fields check
  ↓
Booking_Intent_Repository::create()
  ↓
Redirect: /book-online/?booking_token=...
```

---

## Conclusion

The marketing forms are structurally ready to produce valid v2 payloads for transfers and charters, but **cannot successfully hand off to the booking system** in their current state due to:

1. **Missing customer contact information** - the single largest blocker
2. **Intentional dry_run mode** - prevents actual handoff
3. **No redirect implementation** - form submit is preview-only

**Recommendation:** Implement customer fields first, then enable real handover mode. The fixture corpus already demonstrates valid payload shapes for all form paths.