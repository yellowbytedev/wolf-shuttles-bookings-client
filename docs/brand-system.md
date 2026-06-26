# Brand system

The plugin should use existing CSS variables where possible rather than hard-coded colours.

- Primary colour should use `var(--primary)`.
- Secondary colour should use `var(--secondary)`.
- Tertiary colour should use `var(--tertiary)`.
- Base/dark text should use `var(--base)` and `var(--base-ultra-dark)` where appropriate.
- White backgrounds and text should use `var(--white)`.
- Spacing should use `var(--space-*)` variables.
- Border radius should use `var(--radius*)` variables.
- Form/button styling should be based on existing class patterns such as `ws-form`, `btn-cta`, and `btn-cta--bg-primary`.
- Avoid hard-coded hex values unless no variable exists.

Where the new Booking Builder form is built, the field registry should keep canonical field keys stable and let labels/placeholders be configured separately.
