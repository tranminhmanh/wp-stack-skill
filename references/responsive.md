# Responsive Rules

## Breakpoints (Elementor default)

```
Mobile:        < 768px
Tablet:        768 – 1024px
Desktop:       1024 – 1440px
Wide:          > 1440px
```

## Responsive layout rules

### Container direction

- Desktop: `flex_direction: row` for a 2–4 column grid
- Mobile: `flex_direction_mobile: column` (auto-stacks)

Or use `flex_wrap: wrap` + child widths → wraps to a new line automatically.

### Child widths inside a flex row

| Cols desktop | Width child desktop | Width child tablet | Width child mobile |
|---|---|---|---|
| 2 cols | calc(50% - gap) | 50% | 100% |
| 3 cols | 33.33% | 50% | 100% |
| 4 cols | 25% | 50% | 100% |

Set via `width: {size: 33.33, unit: "%"}` + breakpoint variants.

### Spacing reduction across breakpoints

Every padding / margin / gap needs three values: desktop / tablet / mobile.
Rule: tablet = 70–80% of desktop, mobile = 50–66% of desktop.

### Responsive typography

Headings always set 3 sizes:
- H1: 56 / 40 / 32
- H2: 40 / 32 / 28
- H3: 28 / 24 / 22
- Body: 18 / 16 / 16

### Responsive images

- `width: 100%` on desktop if inside a column
- `max-width: 100%` on mobile
- Set `image_size: "large"` so Elementor auto-serves a responsive `srcset`
- Hero image: lazy load OFF (LCP element)
- Below-fold images: lazy load ON

### Hide on breakpoint

Only hide when truly necessary (e.g. decorative element on mobile):
```
settings: {
  hide_desktop: false,
  hide_tablet: false,
  hide_mobile: true
}
```

## Test responsive after building

Always check 3 breakpoints:
- 375px (iPhone SE / 13 mini)
- 768px (iPad portrait)
- 1280px (laptop)

## Common bugs and fixes

| Bug | Fix |
|---|---|
| Text overflow | Reduce font-size on mobile |
| Image squish | Set `min-height` or `aspect-ratio` |
| Button text wrap | Reduce padding or shorten the text |
| Gap too large on mobile | Reduce `flex_gap_mobile` |
| Hero text glued to top | Increase padding-top on mobile |
| Sticky header overlaps content | margin-top of first section = header height |
| Card heights uneven | Set `align-items: stretch` on the parent |
| Image aspect distorted | `object-fit: cover` + `aspect-ratio` |

## Image responsive width — three separate fields

Image widget responsive sizing uses three independent fields (NOT one field with a responsive object):

```
width:        {size: 100, unit: "%"}     // desktop
width_tablet: {size: 80,  unit: "%"}     // tablet
width_mobile: {size: 100, unit: "%"}     // mobile
```

Same for `height`, `max_width`. Schema differs from typography (one field `typography_font_size` + responsive variants).

## Custom CSS scoped to media queries (do NOT override Elementor responsive defaults)

When styling an element that has Elementor default hide/show behavior at breakpoints (e.g. hamburger toggle, dropdown menu), ALWAYS scope the custom CSS to a media query so you do not break the responsive default:

```css
@media (max-width: 1024px) {
  .menu-toggle { display: inline-flex; padding: 12px; ... }
  .nav-menu--main { display: none !important; }
}

@media (min-width: 1025px) {
  .menu-toggle { display: none !important; }
  .nav-menu--main { display: block !important; }
}
```

Bug pattern: an unscoped custom rule → mobile toggle showing on desktop, or desktop nav showing on mobile.

## Mobile drawer state-scoped via `[aria-hidden]` attribute

Elementor V4 hides dropdowns via `max-height: 0; opacity: 0` (NOT `display: none`). A custom rule like `max-height: calc(100vh - 60px)` overrides this → the drawer renders visible at 41px height even when `aria-hidden="true"`.

**Fix**: scope drawer styles by the `[aria-hidden]` state attribute:

```css
.dropdown[aria-hidden="true"] {
  display: none !important;
  max-height: 0 !important;
  pointer-events: none !important;
}
.dropdown[aria-hidden="false"] {
  display: block !important;
  max-height: calc(100vh - var(--header-h, 60px)) !important;
  pointer-events: auto !important;
  opacity: 1 !important;
}
```

Detection in DevTools:
```js
const drop = document.querySelector('.elementor-nav-menu--dropdown');
drop.getBoundingClientRect().height  // > 0 even when aria-hidden=true = bug
drop.getAttribute('aria-hidden')     // 'true' | 'false'
```

## iframe-based responsive testing pattern

When `resize_window` (Chrome MCP, browser tools) does not actually constrain the viewport (browser fullscreen on Retina), screenshots return the wrong dimensions. Workaround: inject a fixed-width iframe:

```js
const iframe = document.createElement('iframe');
iframe.src = 'https://example.com/?cb=v1';
iframe.style.cssText = 'position:fixed;top:50px;right:20px;width:375px;height:600px;border:2px solid red;z-index:99999';
document.body.appendChild(iframe);

// Wait for load, then read iframe.contentDocument
iframe.contentWindow.innerWidth  // = 371 (correct mobile viewport)
```

Advantage: accurate viewport, plus you can click the hamburger and verify drawer interaction inside the iframe context.

## Container budget for nav decorations

When adding icons / badges / decorations to a nav (mask-image SVG, status pills, count badges), calculate the TOTAL width budget BEFORE adding:

```
container_width = sum(item_widths) + sum(gaps) + decorations
nav_total_width = N × (text_width + icon_width + gap) - last_gap
```

**Bug pattern**: Round-4 polish adds SVG line icons (12–14px) before nav text via `mask-image` data URI. Each link gains ~22px (icon + gap). 5 links × 22px = 110px overflow → nav wraps to 2 rows. Fixed-width container does not grow.

**Fix (pick one)**:
1. **Drop decorations** (simplest if not essential)
2. **Make container flexible**: `flex: 1 1 0` so it grows with content. See [`elementor-mcp.md` "Column width: `_column_size` is not enforced"](elementor-mcp.md).
3. **Hover-only icons**: show the icon ONLY on `:hover` — avoids normal-state overflow:
   ```css
   .nav-item .icon { width: 0; opacity: 0; transition: 0.2s; }
   .nav-item:hover .icon { width: 14px; opacity: 1; margin-right: 6px; }
   ```

Rule: a fixed-width container caps the number of chars + decorations. Test all breakpoints after adding decorations.

## Scroll height optimization ROI

When auditing a homepage at ~9800px scroll height and wanting to shrink it, avoid the false-economy "compress section padding".

**Measured ROI** (homepage 9800px audit):
- Section outer padding 80→64px × 3 sections = expected 96px savings
- **Actual savings: ~80px = 0.8% of page height**
- Visually the section feels "tighter" but this is NOT a dramatic scroll reduction

**Effective optimizations** (target 20%+ shrink):
1. **Compress INNER content** (bigger ROI):
   - Reduce text-editor `margin-bottom`
   - Tighten heading `line-height` (1.2 → 1.1)
   - Clamp image `max-height`
2. **Convert vertical → horizontal** layout:
   - 5 stacked icon-boxes → 5-col grid (saves ~400–600px)
   - Vertical timeline → horizontal scroll-x
3. **Defer / lazy-load below-fold sections** (intersection observer)
4. **Audit content essential vs decorative** — remove sections that don't pull weight

**Rule**: outer padding tweaks max out at ~5–10% page reduction. To shrink 20%+ you need INNER content + layout strategy. **Measure before & after** to validate ROI — don't ship "feels tighter" without numbers.
