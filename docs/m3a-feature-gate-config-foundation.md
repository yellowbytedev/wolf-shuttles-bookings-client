# Marketing Feature Gate Config Foundation (M3A)

## 1. Purpose

This document defines the marketing-side feature-gate foundation for the Wolf Shuttles Booking Builder.

The goal of M3A is to provide a PHP-authoritative, environment-aware feature-gate system with a JS-localized mirror. This lets future form features be safely enabled/disabled by environment without building the full multi-day/multi-trip UI first.

M3A is a foundation task only. It does not enable new UI, change active form layout, or modify booking-side runtime code.

## 2. What M3A Implements

- A PHP feature-gate service: `WSB_Booking_Client\Booking_Feature_Gates`.
- Environment-aware defaults using `wp_get_environment_type()`.
- A WordPress filter (`ws_bookings_client_feature_gates`) for project-level overrides.
- A sanitized frontend config fragment exposed to JS.
- Two helper scripts:
  - `scripts/show-feature-gates.php`
  - `scripts/run-feature-gate-smoke.php`
- Documentation of gate intent, safety rules, and next phases.

## 3. Feature Gates

| Gate | Local | Staging | Production | Purpose |
|---|---|---|---|---|
| `enable_multi_day_charters` | true | false | false | Future multi-day charter UI and payload schema. |
| `enable_multi_trip_bookings` | false | false | false | Future multi-trip booking cart. |
| `enable_additional_stops` | true | true | false | Additional stop fields, behaviour, and validation. |
| `enable_route_options_payload` | true | true | true | Preserve `route.route_options[]` in payloads. |
| `enable_route_alternatives_on_shuttles_page` | false | false | false | Route alternatives UI on shuttles page. |
| `enable_google_places_required` | true | true | true | Google Places snapshots required for quote-ready. |
| `enable_drag_drop_itinerary_ordering` | true | false | false | Sortable/drag-drop itinerary ordering. |
| `enable_day_duplicate_delete` | true | false | false | Duplicate/delete day rows in charter builder. |
| `enable_charter_poi_fields` | true | false | false | Charter POI/waypoint intent fields. |
| `enable_debug_free_text_locations_local_only` | true | false | false | Debug free-text fallback when Google Places is unavailable. |

## 4. Environment Detection

- Uses `wp_get_environment_type()` when available.
- Falls back to `WP_ENVIRONMENT_TYPE` constant if defined.
- Defaults to `production` for unknown or missing environment declarations.

Environment mapping:
- `local` / `development` -> local/dev defaults
- `staging` -> staging defaults
- `production` -> production defaults
- Unknown -> fail closed to production defaults

## 5. PHP Source of Truth

File: `inc/class-booking-feature-gates.php`

Key methods:
- `all()` — returns sanitized `[gate => bool]` for current environment.
- `get($gate, $default = false)` — single gate lookup.
- `is_enabled($gate)` — boolean shorthand.
- `defaults_for_environment($environment)` — raw defaults for an environment.
- `frontend_config()` — JS-safe fragment.
- `is_known_gate($gate)` — guard against unknown keys.

Filter:
- `ws_bookings_client_feature_gates` receives `($gates, $environment)`.
- Filter may override known gates only.
- Final values are coerced to booleans.

## 6. JS-Localized Mirror

The plugin localizes gates into the existing `window.WSB_BOOKING_CLIENT_FORM` object.

Shape:
```js
window.WSB_BOOKING_CLIENT_FORM.featureGates = {
  enable_multi_day_charters: false,
  // ...
};
window.WSB_BOOKING_CLIENT_FORM.environment = "production";
window.WSB_BOOKING_CLIENT_FORM.googlePlaces.requiredForQuoteReady = true;
```

JS should read gates but must not activate unbuilt functionality. M3A does not add multi-day UI, multi-trip booking UI, drag-and-drop UI, or any visible layout changes.

## 7. Override / Filter Mechanism

Project-level overrides are applied via the WordPress filter:

```php
add_filter(
    'ws_bookings_client_feature_gates',
    function ( array $gates, string $environment ): array {
        $gates['enable_additional_stops'] = true;
        return $gates;
    },
    10,
    2
);
```

Rules:
- Override only known gates.
- Unknown gate keys are ignored.
- Values are force-cast to booleans.

No admin UI or options screens are added in M3A.

## 8. Validator / Normalizer Integration

M3A does not change validation behaviour broadly.

The feature gate service is available to future validator/normalizer/handover logic via static helper methods:
- `Booking_Feature_Gates::all()`
- `Booking_Feature_Gates::get($gate)`
- `Booking_Feature_Gates::is_enabled($gate)`

Existing normalizer, validator, and handover service constructors are left unchanged so no existing instantiation points need updating. Future phases may inject the feature gate service when they need gate-aware validation, normalization, or envelope metadata.

## 9. Smoke Test / Verification

Run:
```bash
php scripts/run-feature-gate-smoke.php
```

Expected result: all checks pass.

Run feature-gate inspector:
```bash
wp eval-file wp-content/plugins/ws-bookings-client/scripts/show-feature-gates.php
```

Expected result: environment + gate list printed. No payloads, secrets, or customer data.

## 10. Security / Privacy Constraints

- Marketing captures structured booking intent only.
- Marketing may preserve route_options, route_preferences, route_details, stops, and POI/waypoint intent.
- Marketing must not calculate authoritative route, distance, tolls, classification, availability, or price.
- Booking side remains authority for route calculation, tolls, distance, duration, classification, vehicle availability, pricing, WooCommerce session/cart/order.
- Initial marketing handoff does not require customer name, email, or phone.
- No raw payloads, addresses, coordinates, place IDs, booking tokens, HMAC signatures, secrets, or customer/contact data are logged or echoed by feature-gate code.
- Local-only debug free-text locations must never be enabled outside `local`/`development` environments.

## 11. What Gates Control Now

Currently, gates are config-only. They do not drive visible UI changes in M3A.

Future phases may use gates to:
- Show/hide multi-day charter rows and day duplicate/delete controls.
- Show/hide multi-trip booking UI.
- Show/hide additional stop inputs.
- Include or omit route_options from normalized payload.
- Show/hide route alternatives on the shuttles page.
- Toggle Google Places required validation behaviour.
- Enable/disable drag-and-drop stop/day ordering.
- Enable/disable charter POI fields.
- Allow local-only free-text fallback when Google Places is unavailable.

## 12. Recommended Next Phase

M3A establishes the config foundation. The next recommended marketing-side work is:

- **M3B** — Marketing form field semantics, stable keys, and gated scaffolding foundations. M3B uses these gates to standardise form data attributes, additional-stop scaffolding, Google Places hooks, and contextual-help selectors without building new visible UI. See `docs/m3b-form-field-semantics-and-gated-scaffolding.md`.
- Or proceed with the next planned marketing feature that needs environment-safe toggles.

## 13. Status

- M3A implementation complete.
- UI still not enabled.
- Multi-day charter UI still pending.
- Multi-trip booking UI still pending.
- Drag-and-drop UI still pending.
