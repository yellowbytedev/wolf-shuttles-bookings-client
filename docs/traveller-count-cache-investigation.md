# Traveller Count Cache Investigation

## Symptom

- Production traveller/passenger count increases correctly after booking site sync.
- Around/after midday it appears to reset to the baseline/default number (~35,000).
- Later it may recover.

## Bricks Usage

Bricks displays the count using:

```
{echo:ws_travelers_total()}
```

This function is defined in `inc/legacy-snippets/php/25-register-bricks-helper-functions.php` and reads `ws_traveler_display_total` directly from the options table.

## Investigation Result

No second writer was found. The only code path writing traveler count options is:

- **POST `/wp-json/ws/v1/traveler-count`** — updates:
  - `ws_traveler_display_total` = baseline + shop_total
  - `ws_traveler_last_shop_total` = shop_total
  - `ws_traveler_as_of` = timestamp

The likely cause is **LiteSpeed/page-cache serving stale HTML**, not duplicate traveler logic.

### Evidence

1. LiteSpeed Cache is active with object-cache.php drop-in installed.
2. The 12-hour ESI nonce TTL (`43200` seconds) matches the observed reset window.
3. The crawler runs approximately every 10 minutes — could serve stale renders.
4. No traveler-specific cache invalidation hooks were found in the codebase.

## Implemented Cache-Safe Display Approach

### 1. Cache purge after successful POST update

Added `ws_travelers_purge_count_cache()` in `inc/legacy-snippets/php/24-create-rest-endpoint-for.php`:

- Defensive check using `class_exists('\\LiteSpeed\\Purge')` and `method_exists()`.
- Uses `\LiteSpeed\Purge::purge_url()` to purge specific URLs.
- Default URL list: `['/']` (homepage) — filterable via `apply_filters('ws_travelers_count_purge_urls', ['/'])`.
- Logs success/failure via `error_log()` for debugging.
- Only runs after successful traveler count POST update.

### 2. No-cache headers on GET endpoint

Added `Cache-Control` and `Pragma` headers to `GET /wp-json/ws/v1/travelers`:

```
Cache-Control: no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
```

This prevents caching of the current traveler count values via the API.

## Production File to Copy

The following file was modified:

- `inc/legacy-snippets/php/24-create-rest-endpoint-for.php`

Changes:
1. Added `ws_travelers_purge_count_cache()` function (lines 62-84)
2. Added cache purge call after successful update (line 112)
3. Added no-cache headers to GET endpoint (lines 132-135)

## Local Test Steps

1. Verify endpoint accessibility:
   ```bash
   curl -k -s -i https://wolfshuttles.local/wp-json/ws/v1/travelers
   ```

2. Check no-cache headers are present in response:
   ```
   cache-control: no-store, no-cache, must-revalidate, max-age=0
   pragma: no-cache
   ```

3. Check PHP syntax is valid (if PHP available):
   ```bash
   php -l inc/legacy-snippets/php/24-create-rest-endpoint-for.php
   ```

4. Verify no PHP fatal in debug log:
   ```bash
   tail -n 200 wp-content/debug.log | grep -i "ws_travelers\|fatal"
   ```

## Production Verification Steps

1. **After deployment**, verify no-cache headers on GET endpoint:
   ```bash
   curl -k -s -i https://wolfshuttles.co.za/wp-json/ws/v1/travelers
   ```

2. **Verify cache purge triggers**:
   - Check debug.log for `[WS Travelers] Cache purge triggered` message after next traveler sync.
   - If LiteSpeed is not available, you should see `[WS Travelers] LiteSpeed Purge class not available` (expected in some environments).

3. **Monitor the reset window**:
   - Check the visible count around midday on days with bookings.
   - Should stay at `baseline + shop_total` instead of reverting to baseline.

## Rollback Steps

If issues arise:

1. Revert `inc/legacy-snippets/php/24-create-rest-endpoint-for.php` to the previous version.
2. Clear LiteSpeed cache from the admin interface.
3. The original file without the purge function is still in git history.

## Why Purge Belongs After POST, Not Inside the Display Helper

The purge is triggered only after the POST `/wp-json/ws/v1/traveler-count` endpoint successfully updates the options. This is because:

1. The display helper `ws_travelers_total()` runs on every page load — purging on every render would be wasteful and could cause cache stampedes.
2. The cache should only be invalidated when the underlying data changes.
3. The POST endpoint is the single source of truth for traveler count updates from the booking site.
4. Running purge inside the helper would execute even when no update occurred.