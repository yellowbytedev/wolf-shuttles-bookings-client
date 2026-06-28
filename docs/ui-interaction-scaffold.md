# UI Interaction Scaffold

## Purpose

This document describes the scaffolded UI interaction support added to the Booking Builder for future draggable/sortable interfaces.

## Current Status

- **disabled by default** — No UI interaction features are active.
- **no third-party library loaded** — SortableJS or interact.js are not included.
- **no-op adapter only** — The scaffold provides safe fallback functions.

## Future Use Cases

Ordered-list sorting is the primary anticipated first use case:

1. **ordered additional stops** — Stops within a leg (outbound/return/charter) may need reordering.
2. **charter day segments** — Multi-day charter rows can be reordered.
3. **future itinerary/trip ordering** — Admin interfaces may need to reorder trips in an itinerary.
4. **future admin-style ordering interfaces** — General drag-to-sort UX patterns.

## Library Decision Points

### SortableJS-style Adapter (Likely First)

- Designed for **ordered lists** with drag handles.
- Minimal setup: attach to a container, specify handle, get sort events.
- Used by: WordPress core, Gutenberg, many admin interfaces.
- **Recommendation:** Use for stops/day rows when needed.

### interact.js-style Adapter (Possible Later)

- Designed for **freeform drag/resizing** interactions.
- More complex: inertia, snapping, grid, resize handles.
- Useful for: calendar drag, spatial positioning, advanced admin UX.
- **Recommendation:** Consider when freeform interactions are needed.

## Scaffold Shape

### PHP Configuration

```php
// In inc/booking-client-config.php
WSB_CLIENT_UI_INTERACTIONS_ENABLED // bool, false by default
wsb_client_ui_interactions_enabled() // returns bool, respects constant + filter
```

### JS Adapter

```js
// In assets/js/booking-client-form.js
WSB_BOOKING_UI_INTERACTIONS = {
    isSortableAvailable()   // returns false until SortableJS is loaded
    initSortableList(root)  // no-op if library missing
    destroySortableList(root) // no-op if library missing
}
```

### CSS Hooks (Future Use)

```css
.wsb-sortable-list      /* container for sortable items */
.wsb-sortable-item      /* each draggable item */
.wsb-drag-handle        /* drag handle element */
.wsb-sortable-placeholder /* ghost element during drag */
.wsb-sortable-chosen    /* active drag state */
```

### Data Attributes (Future Use)

```html
<div data-wsb-sortable-list>...</div>
<div data-wsb-sortable-item>...</div>
<div data-wsb-drag-handle>...</div>
```

## No-Op Fallback Requirements

The adapter must:

1. Never throw errors when no sortable library is present.
2. Not affect existing Booking Builder functionality.
3. Not show any visual drag UI unless explicitly enabled.
4. Be activatable via config flag `uiInteractionsEnabled`.

## Browser MCP Visual QA

Any future UI interaction task must run browser/Playwright MCP visual QA to verify:

- No console errors are introduced.
- No visual regressions in existing UI.
- Drag interactions work as expected when enabled.

## Activation Path

To enable sortable interactions in future:

1. Add `define('WSB_CLIENT_UI_INTERACTIONS_ENABLED', true);` to config or environment.
2. Enqueue and load SortableJS library (approval required).
3. Add `data-wsb-sortable-list` to target containers.
4. Call `WSB_BOOKING_UI_INTERACTIONS.initSortableList(root)` in JS.

## History

- Scaffold added in Phase 2K+ as preparation for future drag/sort features.
- No third-party library installed; no functional changes to existing UI.