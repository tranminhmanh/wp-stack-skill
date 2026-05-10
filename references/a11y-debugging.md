# Accessibility debugging recipes

Production-tested patches for common a11y audit failures (Lighthouse / axe-core / WAVE) on Astra + Elementor + Fluent Forms / CF7. Each entry: symptom → root cause → fix you can deploy via JS injection or theme code.

## 1. Lighthouse `color-contrast` reports a color you cannot find in CSS — blended rgba

**Symptom**: Lighthouse color-contrast audit fails with `foreground color: #6d8b9d`. You search the entire stylesheet for `#6d8b9d` — no match. The selector points to an element whose CSS only uses brand variables.

**Root cause**: the rule applies `color: rgba(11,61,92,0.6)` (the brand navy with 60% alpha) on a white parent. The browser blends to RGB at render time → Lighthouse reports the **blended** color, not the source rgba.

**Math** — given source RGB `(R,G,B,a)` on white parent:
```
blended_R = R * a + 255 * (1 - a)
blended_G = G * a + 255 * (1 - a)
blended_B = B * a + 255 * (1 - a)
```

To compute the alpha needed to blend FROM source X TO target T on white:
```
alpha = (255 - T) / (255 - X)
```

**Example reverse-engineering**:
- Source `#0B3D5C` (R=11)
- Target blended `#6D8B9D` (R=109)
- `alpha = (255 - 109) / (255 - 11) = 146 / 244 ≈ 0.598` → confirms `rgba(11,61,92,0.6)`

**Fix — pick one**:
1. **Best**: replace `rgba(*, low alpha)` with a solid color from the design palette (`var(--brand-slate)`) that has sufficient contrast on its own.
2. Increase alpha to ≥0.7 (typically passes 4.5:1 against white for darker base colors).
3. Darken the parent background (white → `#F9FAFD`) which raises the blended contrast.

**Tools**:
- Chrome DevTools → Computed → "color" shows the resolved RGB (no need to compute by hand)
- WebAIM Contrast Checker → drop the blended hex into "Foreground" field
- Pillow / Python `from PIL import ImageColor` for batch-reverse-engineering rgba values across the brand system

**Reusability**: universal for any site using rgba shorthand for muted text on light backgrounds.

## 2. axe-core respects `aria-level` — keep DOM, fix heading order via attribute

**Symptom**: `heading-order` audit fails. An `<h4>` appears after `<h2>` (skipping h3). Renaming the tag breaks CSS that targets `h4` for styling.

**Root cause**: Lighthouse / axe-core enforces sequential heading levels. h2 → h4 = 1 skipped level = fail. Most documentation suggests "rewrite the DOM" — costly when CSS depends on the tag name.

**Fix without DOM change** — promote the `aria-level` attribute:
```javascript
// JS injection at wp_footer (priority 99 or higher)
document.querySelectorAll('h4.target-class').forEach(h4 => {
  h4.setAttribute('role', 'heading');
  h4.setAttribute('aria-level', '3');
});
```

axe-core resolves the **effective heading level** as `aria-level` if present, regardless of the actual tag. The element stays an `<h4>` for CSS, but accessibility tooling (and screen readers) treat it as h3.

**Caveat**: keep the actual visual order matching the aria-level. Don't override an h4 to aria-level=2 if it visually looks like a sub-section — that confuses screen-reader users navigating by heading level.

**Also valid**: `<div role="heading" aria-level="3">` for fully custom heading widgets. Use this when the element is not a real heading tag (e.g. an Elementor "heading" widget that renders a `<div>`).

**Reusability**: universal a11y patch for any site where DOM rewrite is expensive.

## 3. Elementor accordion `aria-selected` invalid attribute

**Symptom**: Lighthouse `aria-allowed-attr` audit fails (weight 10) on `<h3 role="button" aria-selected="true">` rendered by the Elementor accordion widget.

**Root cause**: `aria-selected` is only valid on roles `tab`, `option`, `treeitem`, `row`, `gridcell`, `columnheader`. The Elementor accordion uses `role="button"` — incompatible. The widget code emits `aria-selected` regardless, and re-emits it after every toggle click.

**Fix** — JS strip + watch:
```javascript
function fixAccordionAria() {
  document.querySelectorAll('h3.elementor-tab-title[role="button"][aria-selected]')
    .forEach(h => h.removeAttribute('aria-selected'));
}

// Initial pass
fixAccordionAria();

// Re-run after every toggle (Elementor re-adds the attribute on click)
document.addEventListener('click', e => {
  if (e.target.closest('.elementor-tab-title')) {
    setTimeout(fixAccordionAria, 50);  // wait for Elementor's handler to finish
  }
}, true);  // capture phase, runs before Elementor's bubble handlers
```

**Alternative**: replace `aria-selected` with `aria-expanded` (which IS valid on `role="button"`):
```javascript
document.querySelectorAll('h3.elementor-tab-title[role="button"]').forEach(h => {
  if (h.hasAttribute('aria-selected')) {
    h.setAttribute('aria-expanded', h.getAttribute('aria-selected'));
    h.removeAttribute('aria-selected');
  }
});
```

Same watch pattern for click events.

**Reusability**: universal for any Elementor site using the accordion widget.

## 4. Contact Form 7 honeypot field — `aria-hidden` + off-screen, not a label

**Symptom**: `<input name="honeypot">` (a CF7 / Akismet honeypot trap) fails `label` audit. Adding a visible `<label>` defeats the trap by exposing the field to the user.

**Root cause**: honeypots work by being invisible to humans but visible to bots that fill every field. A visible label makes humans fill it too, breaking the spam filter.

**Fix** — invisible label + screen-reader-skipped field:
```html
<input
  name="honeypot"
  type="text"
  aria-hidden="true"
  tabindex="-1"
  aria-label="Skip this field"
  autocomplete="off"
  style="position:absolute; left:-9999px; width:1px; height:1px; opacity:0; pointer-events:none">
```

What each attribute does:
- `aria-hidden="true"` → screen readers skip the field entirely
- `tabindex="-1"` → keyboard navigation cannot tab into it
- `aria-label="Skip this field"` → fallback for tools that ignore aria-hidden (some axe versions still want a label)
- `position: absolute; left: -9999px; width:1px; height:1px; opacity:0` → invisible to sighted users, off-screen
- `pointer-events: none` → click cannot focus it
- `autocomplete="off"` → password managers / browser autofill skip it

**JS injection version** (for sites where you can't edit CF7 form markup):
```javascript
document.querySelectorAll('input[name="honeypot"], input[name="_wpcf7_honeypot"]').forEach(hp => {
  hp.setAttribute('aria-hidden', 'true');
  hp.setAttribute('tabindex', '-1');
  hp.setAttribute('aria-label', 'Skip this field');
  hp.setAttribute('autocomplete', 'off');
  hp.style.cssText += 'position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;';
});
```

**Reusability**: universal for CF7, Fluent Forms, Akismet, custom honeypot implementations.

## 5. Detecting which a11y check actually fired

When Lighthouse reports an a11y failure on a complex page, find the actual element via the report's "Failing elements" detail. The CSS selector path is one click away — copy it into DevTools console:

```javascript
$$('selector-from-lighthouse')   // jQuery-like, returns array of matching elements
$$('selector-from-lighthouse')[0].outerHTML   // see the markup
```

For axe-core directly (more granular than Lighthouse's a11y category):
```bash
# Install axe CLI once
npm install -g @axe-core/cli

# Audit a URL
axe https://<site>/page/ --tags wcag2a,wcag2aa --reporter v2 --save report.json
```

axe-core reports often catch issues Lighthouse glosses over (Lighthouse category samples, axe enumerates).

## Workflow — bulk-fix a11y across a site

1. Run Lighthouse on the 5 representative pages (homepage, pillar, blog single, contact, archive). Save HTML reports.
2. Identify recurring patterns — usually 3–5 patterns explain >80% of failures (e.g. "all accordions have aria-selected", "all form honeypots lack labels", "all muted text uses rgba 0.6").
3. Write JS / CSS injection patches in a single mu-plugin (`wp-content/mu-plugins/a11y-fixes.php`). Hook on `wp_footer` priority 110.
4. Re-run Lighthouse — verify the failures dropped.
5. Iterate on the long tail.

**Sample mu-plugin scaffold**:
```php
// wp-content/mu-plugins/a11y-fixes.php
<?php
add_action('wp_footer', function () { ?>
<script>
(function () {
  // Pattern 1: accordion aria-selected
  function fixAccordionAria() { /* ... */ }
  fixAccordionAria();
  document.addEventListener('click', e => {
    if (e.target.closest('.elementor-tab-title')) setTimeout(fixAccordionAria, 50);
  }, true);

  // Pattern 2: heading order via aria-level
  document.querySelectorAll('h4.callout-heading').forEach(h => h.setAttribute('aria-level', '3'));

  // Pattern 3: honeypot a11y
  document.querySelectorAll('input[name="honeypot"]').forEach(hp => {
    hp.setAttribute('aria-hidden', 'true');
    hp.setAttribute('tabindex', '-1');
  });
})();
</script>
<?php }, 110);
```

## Cross-references

- [`references/pitfalls.md`](pitfalls.md) "LiteSpeed lazy-load rewrites `src=""` runtime" — common false positive in Lighthouse a11y / image audits
- [`workflows/lighthouse-driven-optim.md`](../workflows/lighthouse-driven-optim.md) — overall workflow for picking what to fix
- [WCAG 2.1 quickref](https://www.w3.org/WAI/WCAG21/quickref/) — official spec
- [axe-core rules reference](https://dequeuniversity.com/rules/axe/) — every rule axe enforces, with fixes
