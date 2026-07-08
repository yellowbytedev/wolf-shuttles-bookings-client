# Marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/m3x-contextual-help-guided-assistance-plan.md

---

## 1. Purpose and Scope

This document outlines a **contextual help/guided assistance plan** for the Wolf Shuttles V3 marketing booking forms. It does **not**:
- Alter payloads, pricing, booking-side logic, or customer data collection
- Require static text balloons or intrusive UI elements
- Change core form functionality or validation rules

The plan focuses on:
- Feature-gated, user-driven assistance
- Context-aware tooltips and tours
- Accessible, non-blocking guidance
- Configurable content for future adaptability

---

## 2. Current Form Observations

Based on screenshots and repo inspection:

### Form Structure
- **Book a Ride tab**:
  - Passengers count, baggage, children
  - Pickup/drop-off locations, dates/times
  - Route preferences (optional)
- **Shuttle Hire tab**:
  - Additional stops (manual input)
  - Multi-day charter builder (scaffold)
- **Universal fields**:
  - Google Places selection (mandatory for quote readiness)
  - Trailer required and oversize luggage are simple luggage/add-on toggles

### User Pain Points
- Google Places selection required but unclear how it ties to route pricing
- Multi-day charter logic (add/duplicate/delete days)
- Drag/drop itinerary support but no visual guidance
- Time field formatting (AM/PM suffix uncertainty)
- Tabs switching (inconsistent active state indicators)

---

## 3. Assistance Principles

Key design rules:
- **Contextual**: Help appears only when relevant (e.g., location field vs. unrelated fields)
- **Non-intrusive**: No wall-of-text explanations
- **Accessible**: Works via keyboard (`Tab`/`Enter`), touch, and screen readers
- **Dismissible**: No page locking during help interactions
- **Validation-separated**: Hints don't override server checks
- **Payload-safe**: No sensitive data in help text or state

---

## 4. Hover/Focus/Touch Behaviour Rules

- No help may be hover-only.
- Desktop may use delayed hover plus focus/click.
- Mobile must use tap/click.
- Keyboard users must get focus-triggered help.
- Escape must close popovers/tours.

---

## 5. Feature Gate Proposal

Proposed gates (configurable in code):

| Gate ID                          | Local Default | Staging Default | Prod Default | UI Control | Fallback | Privacy Risks |
|----------------------------------|---------------|------------------|--------------|------------|----------|---------------|
| `enable_contextual_help`         | true          | false (test-only) | false        | Plugin config | None | Safe |
| `enable_field_tooltips`          | true          | false (test-only) | false        | Feature gate | None | Safe |
| `enable_guided_form_tour`        | true          | false            | false        | Plugin admin | None | Safe |
| `enable_multi_day_builder_tour`  | true          | false            | false        | Feature gate | Manual | Safe |
| `enable_delayed_hover_help`      | true          | false            | false        | JS config | Disable | Safe |
| `enable_first_time_use_hints`    | true          | false            | false        | User session | Show once | Safe |
| `enable_help_analytics_local_only` | true        | false            | false        | JS | LocalStorage | Safe |

**Note**: All gates fail closed by default (false) in production/staging environments. They can only be enabled locally for testing.

---

## 6. Tooltip/Help Content Model

Config-driven system example:

```js
helpItems = {
  passengers: {
    selector: "[data-ws-help='passengers']",
    title: "Passengers",
    body: "Specify how many travelers to match the right vehicle.",
    triggers: ["focus", "click"],
    placement: "top",
    contexts: ["book_a_ride"],
    featureGate: "enable_contextual_help",
    mobile: { position: "right" },
    dismiss: "click-outside"
  }
}
```

Includes:
- Stable selectors (data attributes)
- Localizable content
- Feature gate dependencies
- Mobile-specific behavior
- Dismiss behavior

---

## 7. Recommended Data Attributes (Future-Proofing)

Proposed data attributes for implementation:

### Core Attributes
- `data-ws-help`: Primary help identifier (maps to help content)
- `data-ws-help-context`: Defines scope/context of help (e.g., "book_a_ride")
- `data-ws-help-tour-step`: Links element to specific tour step
- `data-ws-field-key`: Maps to predefined field configurations
- `data-ws-form-section`: Identifies form section (e.g., "passengers")
- `data-ws-feature-gate`: Required gate ID(s) for activation

### Field-Specific Mappings
- passengers: `data-ws-field-key="passengers"`
- baby_seats: `data-ws-field-key="baby_seats"`
- check_in_bags: `data-ws-field-key="check_in_bags"`
- carry_on_bags: `data-ws-field-key="carry_on_bags"`
- trailer: `data-ws-field-key="trailer"`
- oversize_luggage: `data-ws-field-key="oversize_luggage"`
- pickup_location: `data-ws-field-key="pickup_location"`
- drop_off_location: `data-ws-field-key="drop_off_location"`
- additional_stop: `data-ws-field-key="additional_stop"`
- pickup_date: `data-ws-field-key="pickup_date"`
- pickup_time: `data-ws-field-key="pickup_time"`
- drop_off_time: `data-ws-field-key="drop_off_time"`
- submit_button: `data-ws-field-key="submit_button"`
- future_day_card: `data-ws-field-key="future_day_card"`
- future_duplicate_day: `data-ws-field-key="future_duplicate_day"`
- future_delete_day: `data-ws-field-key="future_delete_day"`
- future_drag_handle: `data-ws-field-key="future_drag_handle"`

---

## 8. Help by Form Area

### Tabs
- **Book a Ride**: Passengers, baggage, times
- **Shuttle Hire**: Additional stops, charter builder

### Location Fields
- Google Places guidance:
  - "Select a location to calculate your route and pricing"
  - Stale edit warning: "You edited this address. Reconfirm from dropdown for accuracy"

### Multi-Day Charter
- Guide users to:
  - Add days via `+ Add` button
  - Mirror pickup/dropoff for each day
  - Validate end time > start time

### Drag/Drop Itinerary
- "Drag stops to reorder or delete with ❌"
- Fallback to keyboard arrows for mobile

---

## 9. Guided Tour Plan

### A. First-Time Book a Ride Tour
- **Trigger**: Tour opt-in checkbox visible on initial load
- **Steps**:
  1. Passengers: "Choose number of travelers"
  2. Pickup Location: "Select pickup point via Google Places"
  3. Drop-off Location: "Select destination via Google Places"
  4. Pickup Date/Time: "Pick your preferred date and time"
- **Selectors**:
  - `data-ws-help="passengers"`
  - `data-ws-help-context="book_a_ride"`
  - `data-ws-help-tour-step="step_1"`
- **Dismiss**: Click outside or press Esc
- **Mobile Fallback**: Tap anywhere outside popover
- **Accessibility**: Focus-triggered help, Esc closes
- **Persistence Rule**: Complete tour once per user session

### B. First-Time Shuttle Hire Tour
- **Trigger**: Tour opt-in checkbox on Shuttle Hire tab activation
- **Steps**:
  1. Additional Stops: "Add stops as needed"
  2. Trailer/Luggage: "Toggle trailer or oversize luggage if required"
  3. Pickup/Drop-off Times: "Set times for each stop"
  4. Multi-day Builder: "Add days using + Add button"
- **Selectors**:
  - `data-ws-help="additional_stops"`
  - `data-ws-help="trailer"`
  - `data-ws-help="oversize_luggage"`
- **Selectors/Data Attributes**:
  - `data-ws-field-key="additional_stops"`
  - `data-ws-feature-gate="enable_guided_form_tour"`
- **Dismiss**: Click outside or press Esc
- **Mobile Fallback**: Tap outside interaction area
- **Accessibility**: Focus order follows natural tab sequence
- **Persistence Rule**: Complete tour once per user session

### C. Multi-Day Charter Builder Tour
- **Trigger**: Tour opt-in appears when charter enabled
- **Steps**:
  1. Add Day: "+ Add" button creates new day card
  2. Edit Day: Complete pickup/dropoff fields
  3. Validate: Ensure end time > start time
  4. Save: Confirm day creation
- **Selectors**:
  - `data-ws-help="future_day_card"`
  - `data-ws-help="future_duplicate_day"`
  - `data-ws-help="future_delete_day"`
  - `data-ws-help="future_drag_handle"`
- **Persistence**: Complete tour once per session
- **Mobile Fallback**: Scrollable day cards on mobile
- **Accessibility**: Keyboard navigation through day controls
- **Dependency**: Multi-day builder must exist before this tour is implemented

### D. Additional Stops Tour
- **Trigger**: First-time visitor to Shuttle Hire tab
- **Steps**:
  1. Enable Stops: Toggle additional stops
  2. Add Stop: Use + Add button
  3. Configure Stop: Fill location and details
  4. Drag/Drop: Reorder stops as needed
- **Selectors**:
  - `data-ws-help="additional_stop"`
  - `data-ws-help-tour-step="step_3"`
- **Dismiss Behavior**: Click outside or press Esc
- **Mobile Fallback**: Tap outside interaction area
- **Accessibility**: Focus management for dynamic stop elements
- **Persistence Rule**: Complete tour once per user session

### E. Google Places Address Selection Tour
- **Trigger**: First interaction with location fields
- **Steps**:
  1. Select Location: Choose from dropdown
  2. Confirm Selection: Verify place_id loads
  3. Edit Warning: Understand stale edit behavior
  4. Proceed: Continue when ready
- **Selectors**:
  - `data-ws-help="pickup_location"`
  - `data-ws-help="drop_off_location"`
  - `data-ws-help-context="google_places"`
- **Persistence Rule**: Complete only once per user session
- **Accessibility**: Screenreader announces selection status

---

## 10. Validation Assistance Plan

Contextual hints:
- "📍 Select Google Places location for accurate routing" (if no place_id)
- "⏳ Return trip missing return leg details"
- "🗓️ Multi-day day incomplete"
- "⚠️ Stale address detected - please reselect"

---

## 11. Library/Plugin Recommendation

**Recommended Path**:
- Driver.js for guided tours/highlights (high guidance needs)
- Tippy.js or Floating UI for field-level popovers if needed (lightweight)
- Avoid heavy WordPress plugin dependency for now
- Be cautious with Intro.js licensing before commercial use

---

## 12. Implementation Backlog

- HLP-A: Finalize plan/docs (completed)
- HLP-B: Implement feature gates in PHP/JS
- HLP-C: Add `data-ws-help` attributes to form fields
- HLP-D: Create static help JSON config
- HLP-E: Field-level tooltip prototype, local only
- HLP-F: Guided tour prototype, local only
- HLP-G: Multi-day tour after builder exists
- HLP-H: Accessibility testing
- HLP-I: Staging rollout
- HLP-J: Production rollout

---

## 13. Accessibility & UX Gates

Must pass:
- Keyboard focus order preserves natural tab sequence
- Escape key closes all help popovers/tours
- No fixed positioning blocks content
- Screen reader announces help purpose
- Focus-triggered help always available
- Mobile tap behavior matches desktop interactions

---

## 14. Security/Privacy Constraints

Re-confirmed:
- no customer name/email/phone required in initial marketing handoff
- no addresses/place IDs/coordinates in URLs
- no payload/tokens/secrets in localStorage/cookies/help analytics
- help state may store only dismissed help IDs/tour versions
- route details remain sensitive input/context only
- marketing is not pricing authority

---

## 15. Current UI Issues to Log

- excessive top whitespace in forms
- tab alignment and active state inconsistency
- inconsistent input widths across form controls
- pickup/drop-off time layout issue (misaligned labels)
- AM/PM suffix layout issue (misplaced meridiem indicators)
- label/input spacing inconsistencies
- Shuttle Hire date/time grouping issue (poor visual association)
- mobile layout still unknown (needs responsive testing)
- visual polish deferred until functionality is stable

---

## 16. Implementation Dependency Note

Contextual help implementation **must wait until**:
- M3A feature gates exist and are properly implemented
- stable field keys/data attributes are available for mapping
- multi-day builder exists before multi-day tour is implemented
- all tours respect the staged gate activation sequence

---

## 17. Files Reviewed

- `phase-2x-form-feature-gates-multitrip-multiday-plan.md`
- `google-places-quote-ready-handoff.md`
- `booking-payload-v2-contract.md`
- All marketing-site form documentation
- Screenshots of current booking interface

## Final Report

1. **Files Reviewed**: All relevant markdown documentation and form screenshots
2. **Files Changed**: `m3x-contextual-help-guided-assistance-plan.md` (updated with corrections)
3. **Corrections Made**:
   - Fixed feature-gate defaults to fail closed in production/staging
   - Updated wording about trailer/oversized luggage toggles
   - Completed guided-tour section with all intended tours
   - Strengthened data-attribute plan with comprehensive mappings
   - Clarified hover/focus/touch behavior rules
   - Expanded current UI issues from screenshots
   - Clarified library recommendation approach
   - Added implementation dependency note
   - Reconfirmed security/privacy constraints
4. **Final Gate Defaults**:
   - Production: All gates false (fail closed)
   - Staging: All gates false unless specifically testing
   - Local: Gates can be enabled for development/testing
5. **All Tours Documented**: Complete tour specifications with triggers, steps, selectors, and persistence rules
6. **Screenshot UI Issues Logged**: All identified issues from visual inspection
7. **Confirmation**: No runtime files changed, only documentation updated

(End of file - total 380 lines)
