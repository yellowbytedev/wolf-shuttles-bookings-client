# LEGACY-BRICKS-V2-ADAPTER-ENABLE-VERIFY-002

## Summary
Enable and verify Cape Town Bricks legacy form through V2 token handoff.

## Configuration Changes

### Marketing Site (`wp-config.php`)
```php
define('WSB_CLIENT_HANDOVER_MODE', 'v2_token');
define('WSB_CLIENT_V2_STRICT_MODE', true);
```

### Files Modified
1. `inc/legacy-snippets/php/26-legacy-bricks-v2-handover-adapter.php` (created)
2. `inc/legacy-snippets/php/15-submit-booking-form-and.php` (modified)
3. `inc/legacy-snippets/loader.php` (modified)

## Test Results

### V2 Intake Endpoint Test
```bash
curl -X POST https://bookings.wolfshuttles.local/wp-json/ws-bookings/v2/intake \
  -H "Content-Type: application/json" \
  -d '{"schema_version":"2.0","trip_type":"one_way","service_type":"city_transfer","service_group":"transfer","passengers":2,"location_from":"V&A Waterfront","location_to":"Cape Town Airport","pickup_date":"2026-07-15","pickup_time":"08:00","drop_off_time":"09:30"}'
```

Response:
```json
{
  "success": true,
  "status": "prepared",
  "booking_token": "fcehvc6pd-8bybnmaswywg",
  "redirect_url": "https://bookings.wolfshuttles.local?booking_token=fcehvc6pd-8bybnmaswywg",
  "next_step": "resume_booking",
  "schema_version": "2.0",
  "trip_type": "one_way",
  "service_type": "city_transfer",
  "service_group": "transfer",
  "warnings": ["Google place snapshots are not quote-ready. Place IDs are required for pricing."]
}
```

### PHP Lint Results
All modified files pass syntax check:
- `inc/legacy-snippets/php/26-legacy-bricks-v2-handover-adapter.php` - OK
- `inc/legacy-snippets/php/15-submit-booking-form-and.php` - OK
- `inc/legacy-snippets/loader.php` - OK

## Implementation Details

### Flow
1. Legacy Bricks form submits to `custom_booking_form_action()`
2. `send_booking_data()` checks `wsb_legacy_adapter_is_v2_enabled()`
3. If V2 enabled, calls `wsb_legacy_adapter_send_to_v2_intake()`
4. Legacy camelCase keys are normalized to snake_case via `wsb_legacy_adapter_normalize_keys()`
5. Payload POSTed to `/wp-json/ws-bookings/v2/intake`
6. V2 intake normalizer converts flat fields to legs format
7. Booking intent created, token generated, redirect URL returned
8. Browser redirected to `https://bookings.wolfshuttles.local/?booking_token=...`

### Key Functions
- `wsb_legacy_adapter_is_v2_enabled()` - Returns true when `WSB_CLIENT_HANDOVER_MODE === 'v2_token'`
- `wsb_legacy_adapter_is_v2_strict_mode()` - Prevents fallback to legacy on V2 failure
- `wsb_legacy_adapter_normalize_keys()` - Transforms legacy field names
- `wsb_legacy_adapter_normalize_trip_type()` - Converts `point_to_point_transfer` → `one_way`/`return`
- `wsb_legacy_adapter_normalize_service_type()` - Maps service types to canonical values

## Verification Checklist
- [x] V2 intake endpoint tested with flat-field format
- [x] Booking token generated
- [x] Redirect URL correct (`bookings.wolfshuttles.local/?booking_token=` NOT `/book-online/`)
- [x] Strict mode prevents silent fallback
- [x] PHP lint passes on all modified files
- [ ] Browser form submission test (requires Local running)
- [ ] V2 UI renders on booking site
- [ ] Old `?hash=` compatibility preserved (when mode is `legacy_hash`)

## Remaining Actions
1. Start Local (Flywheel/Local) and verify the form submission works in browser
2. Check `wp-content/debug.log` on both sites for errors
3. Verify V2 booking page renders correctly on `bookings.wolfshuttles.local`
4. Test legacy hash compatibility by removing V2 mode constants

## Constraints Enforced
- No `/book-online/` path in redirect URL (booking site root used instead)
- No checkout/cart/order/payment mutations in booking intent creation
- No customer details exposed in error responses
- Strict mode logs V2 failure instead of silent fallback