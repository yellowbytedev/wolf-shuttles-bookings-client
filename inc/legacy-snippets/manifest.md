# Legacy Fluent Snippets Manifest

Source: `wp-content/fluent-snippet-storage` staging Fluent Snippets export.

The loader is gated by `WSB_CLIENT_LOAD_LEGACY_SNIPPETS` and is disabled by default. Archived, draft, and skipped snippets are not loaded.

## Active Migrated Snippets

| Source snippet | Migrated path | Type | Load notes |
| --- | --- | --- | --- |
| `10-helper-functions.php` | `php/10-helper-functions.php` | PHP | Loaded with `require_once`. |
| `11-register-api-endpoint-for.php` | `php/11-register-api-endpoint-for.php` | PHP | Loaded with `require_once`; keeps `booking/v1/data`. |
| `15-submit-booking-form-and.php` | `php/15-submit-booking-form-and.php` | PHP | Loaded with `require_once`; booking payload, HMAC, form IDs, field names, and redirect logic left unchanged. |
| `16-create-localised-variable-for.php` | `php/16-create-localised-variable-for.php` | PHP / inline JS | Loaded with `require_once`; keeps the `myAjax.ajaxurl` inline script behaviour. |
| `19-bricks-builder-custom.php` | `php/19-bricks-builder-custom.php` | PHP | Loaded with `require_once`; copied because it was explicitly listed for this booking migration. |
| `2-test-2.php` | `php/2-test-2.php` | Mixed PHP / JS / CSS | Conditional load only on original page IDs `6`, `1958`, and original listed post types. Keeps timepicker enqueue and inline footer output intact. |
| `21-redirect-book-online.php` | `php/21-redirect-book-online.php` | PHP | Conditional load only on original page ID `6`; redirect behaviour unchanged. |
| `24-create-rest-endpoint-for.php` | `php/24-create-rest-endpoint-for.php` | PHP | Loaded with `require_once`; keeps `ws/v1/traveler-count` and `ws/v1/travelers`. |
| `25-register-bricks-helper-functions.php` | `php/25-register-bricks-helper-functions.php` | PHP | Loaded with `require_once`. |
| `28-add-tooltip-to-additional.php` | `js/28-add-tooltip-to-additional.js` | JS | Fluent PHP metadata stripped; printed inline on `wp_footer` at priority `10`. |
| `29-form-tooltip-styling.php` | `css/29-form-tooltip-styling.css` | CSS | Fluent PHP metadata stripped; printed inline on `wp_head` at priority `10`. |
| `30-create-debugger.php` | `js/30-create-debugger.js` | JS | Fluent PHP metadata stripped; printed inline on `wp_footer` at priority `10`. |
| `5-calculate-distance-v2.php` | `js/5-calculate-distance-v2.js` | JS | Fluent PHP metadata stripped; printed inline on `wp_footer` at priority `1`. |
| `6-enqueue-google-maps-api.php` | `php/6-enqueue-google-maps-api.php` | PHP / external JS enqueue | Conditional load only on original page IDs `6`, `1958`, and original listed post types. Keeps Google Maps handle and callback unchanged. |
| `7-api-call-to-google.php` | `php/7-api-call-to-google.php` | PHP | Loaded with `require_once`; AJAX action names unchanged. |
| `8-add-jquery.php` | `php/8-add-jquery.php` | PHP / enqueue | Conditional load only on original page IDs `6`, `1958`, and original listed post types. Keeps jQuery and jQuery UI enqueue behaviour unchanged. |

## Archived Only

These snippets were copied to `archive/` and are not referenced by `loader.php`.

| Source snippet | Type / status from export | Notes |
| --- | --- | --- |
| `3-custom-date-picker-on.php` | JS / draft | Archived only. |
| `4-initialise-booking-form.php` | PHP / draft | Archived only. |
| `9-create-hash-code-on.php` | PHP / draft | Archived only. |
| `14-bricks-custom-filters.php` | PHP / draft | Archived only. |
| `15-initialise-elements-and-variables.php` | JS / draft | Archived only. |
| `31-create-a-location_service-post.php` | PHP / draft | Archived only. |
| `31-resave-temp.php` | PHP / draft | Archived only. |
| `33-force-display-style-on.php` | JS / draft | Archived only. |
| `26-test.php` | PHP / published | Archived only per migration request; not loaded. |

## Skipped Snippets

Skipped because they are analytics, schema, global styling, CPC tracking, general site/content snippets, or Fluent Snippets internals:

- `12-override-default-styles-for.php`
- `17-google-tag-manager-body.php`
- `18-google-tag-manager-head.php`
- `20-add-clarity-code.php`
- `21-add-clarity-user.php`
- `22-global-styling.php`
- `27-send-cpc-data-to.php`
- `32-custom-output-query-for.php`
- `34-ws-custom-options-page.php`
- `35-home-page-schema.php`
- `36-about-us-schema.php`
- `37-our-services-schema.php`
- `38-contact-schema.php`
- `39-airport-shuttles-schema.php`
- `40-point-to-point-service.php`
- `41-charter-service-schema.php`
- `42-corporate-transfers-service-schema.php`
- `43-transport-management-schema.php`
- `44-luxury-transfers-service-schema.php`
- `45-chauffeur-services-schema.php`
- `46-bricks-custom-condition-for.php`
- `index.php`
- `cached/index.php`
- `wp-content/fluent-snippet-storage.zip`

## Assumptions And TODOs

- `2-test-2.php` is treated as active because it was explicitly listed and its export metadata name is "Implement JQuery Timepicker on booking form"; the similarly named `26-test.php` is archived only.
- PHP snippets were copied without business-logic refactors. This intentionally preserves existing function names, endpoint names, HMAC logic, form field names, hidden field names, AJAX actions, and redirects.
- PHP snippets are loaded with `require_once`; the loader discards incidental whitespace from Fluent's PHP wrapper to avoid premature output before redirects or REST responses.
- The Google Maps, jQuery, timepicker, and redirect snippets are required on `wp` only when their original Fluent page/post conditions match, so their internal WordPress hooks are registered before `wp_enqueue_scripts`, `wp_head`, `wp_footer`, or `template_redirect` run.
- Before enabling `WSB_CLIENT_LOAD_LEGACY_SNIPPETS`, deactivate matching Fluent Snippets on local/staging to avoid duplicate function definitions and duplicate frontend output.
