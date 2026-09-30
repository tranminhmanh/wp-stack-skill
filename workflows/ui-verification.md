# Workflow: UI verification — counter to anchoring + confirmation bias

When AI builds or modifies a page layout, the AI does NOT actually see the result. It infers from the CSS / HTML / Elementor settings it just wrote. Two cognitive biases consistently break this inference:

- **Anchoring bias**: setting `text-align: center` → assuming the element renders centered, without measuring.
- **Confirmation bias**: when the user complains the layout is broken, scanning the screenshot for evidence that supports the original assumption ("the heading IS centered, so the buttons must be too") instead of looking for evidence that contradicts it.

The cost is concrete: user feedback like *"CTA is left-aligned but you said it's centered — what's wrong with your analysis?"* — trust damaged in a single exchange.

This workflow forces explicit verification before any layout claim.

## When to apply

✅ Right after building or modifying a hero / CTA / form / grid layout via MCP
✅ Before responding to a user with "centered / aligned / cân đối / OK"
✅ Whenever you want to claim "the layout works on mobile/tablet/desktop"
✅ When the user pushes back on a layout claim — re-verify, do NOT defend the original

❌ Pure backend changes (DB updates, plugin toggles, schema injection) — no visual to verify
❌ Tiny copy edits to existing layouts — verification overhead not worth it for a one-character fix

## The verify-don't-assume checklist

Before claiming a layout is correct, run through this checklist EVERY TIME:

```
[ ] 1. Take a screenshot of the live frontend (NOT the Elementor editor view)
[ ] 2. Measure pixel position of the element vs viewport center
[ ] 3. Test 3 viewports: 375 (mobile) / 768 (tablet) / 1280 (desktop)
[ ] 4. Inspect Computed style in DevTools — see ACTUAL margin / flex-align / text-align applied
[ ] 5. Compare against the brief / mockup / user expectation if any
```

All 5 boxes ticked → claim is grounded. Any unticked box → claim is an assumption, not a fact.

## Why screenshots from the Elementor editor are NOT enough

The Elementor editor renders the page inside a chrome-wrapped iframe. Layout in the editor differs from the live frontend in subtle but important ways:
- Cache plugins (LiteSpeed, WP Rocket) don't run in the editor → real CSS may be different
- Theme Builder header / footer templates apply ONLY on live, not in the editor
- Custom CSS injected via `wp_head` priority 110+ from mu-plugins runs ONLY on live
- JS injection from Code Snippets only runs on live
- LiteSpeed lazy-load image rewriting (`src=""` swap) only happens on live

→ The editor lies to you. Always screenshot the live URL.

## Tooling — pick one available in the session

### Option A: Chrome MCP / Claude in Chrome

```
mcp__Claude_in_Chrome__navigate(url: "https://example.com/page/?cb=$(date +%s)")
mcp__Claude_in_Chrome__resize_window({width: 375, height: 800})
mcp__Claude_in_Chrome__take_screenshot()
mcp__Claude_in_Chrome__read_page()  # also returns DOM + computed styles
```

⚠️ `resize_window` is unreliable on Retina / high-DPI displays — see [`references/responsive.md`](../references/responsive.md) "iframe-based responsive testing pattern" for the workaround (inject a fixed-width iframe for accurate viewport).

### Option B: WebFetch + jsdom-style parsing (when Chrome MCP unavailable)

Lower fidelity — no real layout measurement, but you can detect HTML structure / class presence / inline styles:
```python
import urllib.request, re
html = urllib.request.urlopen(url).read().decode('utf-8')

# Verify the element exists
assert '<h1 class="hero-title"' in html, "H1 missing"

# Verify the class chain (suggests CSS will apply)
assert re.search(r'class="[^"]*centered[^"]*"', html), "centered class missing"
```

⚠️ This catches structural bugs but NOT layout bugs. A class can be present but the CSS rule that uses it might not load. Always prefer Option A for layout claims.

### Option C: User-supplied screenshot

When the user provides a screenshot, treat it as ground truth — but read it carefully. The bias trap is reading the screenshot looking for confirmation of your earlier claim. Read it looking for evidence of the user's complaint:
- User says "CTA is left-aligned" → your job is to find pixel evidence of left-alignment in the screenshot, not "see, the heading above it is centered, so the CTA must be too".

## Centering anti-patterns inside flex containers

Most "centered claim turned out wrong" bugs are inside Elementor flex containers. The trap: developers learn `text-align: center` in CSS 101, but `text-align` only centers **inline content within a block**. Flex children align via different properties.

**Wrong** — `text-align: center` on a flex item only centers TEXT inside the item, not the item itself:
```css
.cta-button {
  text-align: center;  /* centers the button text, not the button */
}
```

**Right** — for an element inside a flex-row container:
```css
.cta-button {
  align-self: center !important;       /* center in cross-axis */
  width: fit-content !important;        /* don't stretch full width */
  margin: 0 auto !important;            /* center in main-axis */
}
```

All 3 properties needed simultaneously. Missing any one → off-center.

For a flex-column container, swap roles: `align-self: center` for the cross-axis, `margin: 0 auto` not needed (the column already centers vertically with `justify-content: center` on parent).

## Computed-style inspection for "but I set text-align: center"

In DevTools → Elements → Computed:
- Look for `text-align` actual value (may be overridden by parent flex layout)
- Look for `display` of the element — if it's `flex` or `block`, `text-align` only affects inner inline content
- Look for `margin-left` / `margin-right` — `auto` on both = block-level horizontal centering, but the element must have `width: fit-content` or specific `width` for it to take effect

A common surprise: `display: flex; align-items: center` only centers in the **cross-axis**. If the parent flex direction is `row`, that's vertical centering, not horizontal. To horizontally center in row direction, use `justify-content: center` on parent OR `margin: 0 auto` on the child (with `width: fit-content`).

## Meta-pattern — "AI cannot trust its own visual reasoning"

The AI does not have vision. Even multimodal models, when reading their own DOM output, are susceptible to the same anchoring/confirmation pattern as text-only models reading their CSS.

**Discipline**: before any visual claim, use a tool to ground the claim in observed pixels. The 5-step checklist above is the minimal-overhead version.

When the user pushes back on a visual claim:
1. Don't defend the original — re-verify FIRST
2. Take a fresh screenshot
3. Measure
4. Report what the measurement shows
5. THEN explain the gap: "You're right — the CTA is at x=20, not x=viewport_center. The cause is X. The fix is Y."

This pattern restores trust. Defending an unverified claim damages it permanently.

## Bug pattern — "looks centered in editor, off-center on live"

A typical sequence:
1. Build the page in Elementor editor → editor shows centered
2. Save → frontend renders → user reports off-center
3. AI: "but it's centered, see the editor screenshot"
4. User: "I'm looking at the live URL, not the editor"
5. Trust damaged

**Prevention**: from the start of a layout build, work against the LIVE URL with cache-busting query string, not the editor preview. Iterate live → eliminate the editor-vs-live class of bugs entirely.

```
URL = https://example.com/page/?cb=$(date +%s%N)
```

Cache-bust forces fresh render. Combined with `Cmd+Shift+R` browser hard-refresh after each MCP write op, you see what the user sees.

## Verification ladder — HTTP 200 → markup present → rendered and behaving

Each rung proves less than people assume. Across 4 sites the same near-miss happened: a check on a lower rung passed and "done" was almost reported.

| Rung | What it proves | What it does NOT prove |
|---|---|---|
| 1. HTTP 200 | No fatal error | That anything you changed is there |
| 2. Markup grep (`curl … \| grep`) | The server emits your HTML | That it renders correctly, that JS ran, that nothing later rewrote it, that content loaded by AJAX exists |
| 3. Real browser: screenshot + console + counts | What a visitor actually gets | — (this is the bar for UI claims) |

Real cases where rung 2 passed and rung 3 failed:

| Change | Rung 2 said | Rung 3 showed |
|---|---|---|
| 5 sub-menu items added to a theme header builder menu | 5/5 links inside `<nav>` on 5/5 pages, correct `sub-menu` class | Parent item thrown above the header plus an empty white box — header broken; reverted |
| Schedule widget embedded via script tag | The widget's container `<div>` present | Table empty until counted: `tbody tr` = 0 (asset path routed to the wrong upstream) |
| One JS block removed from an injection MU-plugin | Page 200, target block gone | A different section vanished: `ReferenceError` in console — a later block used a variable the removed one declared |
| Lead-form audit by crawling HTML | Only the inline forms found | A 700×800 popup form loaded via AJAX on desktop — invisible to curl, visible in the browser |

**Rung-3 checklist** (in addition to the layout checklist above):

```text
[ ] Screenshot desktop + mobile width of the changed area
[ ] Console: 0 new errors — in a FRESH tab (the console keeps errors from the previous load; stack traces still show the old ?cb= URL)
[ ] Count what should exist: rows, cards, sections, links — compare with the source data, not with "looks fine"
[ ] Diff raw HTML vs rendered text for the values you changed: `curl` shows the source, `document.body.innerText` shows what JS left — a mismatch means a runtime rewriter (see pitfalls §"Self-reverting change")
[ ] Clean URL, not only ?cb= (see litespeed-cache-mgmt §"Verify render changes on the CLEAN URL")
```

### Fixed bottom bars cover footer-injected content on mobile

Theme/plugin sticky bars (call / Zalo / navigation strips pinned to the bottom of the viewport) often exist only below a breakpoint and leave `body` without bottom padding. Anything you inject at the end of the page (`wp_footer` links, a copyright row) sits underneath on phones. Measure instead of guessing:

```js
// Gap between the last injected element and the fixed bar
const bar  = document.querySelector('<fixed-bar-selector>');
const last = document.querySelector('<your-block> :last-child');
({ barHeight: bar?.getBoundingClientRect().height,
   gapBelowLast: document.documentElement.scrollHeight - (last.getBoundingClientRect().bottom + scrollY) });

// Read the bar's real breakpoint from the stylesheets instead of assuming 767px
[...document.styleSheets].flatMap(ss => { try { return [...ss.cssRules]; } catch { return []; } })
  .filter(r => r.type === CSSRule.MEDIA_RULE && r.cssText.includes('<fixed-bar-class>'))
  .map(r => r.conditionText);
```

Then add bottom padding ≥ the bar height, scoped to the same media query the bar uses.

## Cross-references

- [`references/responsive.md`](../references/responsive.md) — `[aria-hidden]` state-scoped CSS, iframe-based responsive testing pattern
- [`references/pitfalls.md`](../references/pitfalls.md) — `_elementor_edit_mode` empty makes editor-vs-live diverge dangerously
- [`references/elementor-mcp.md`](../references/elementor-mcp.md) "Verify pattern (REQUIRED after every write op)" — HTTP-level verify (no fatal)
- [`workflows/redesign-page.md`](redesign-page.md) — verify after each phase 3 execution
- [`litespeed-cache-mgmt.md`](litespeed-cache-mgmt.md) §"Verify render changes on the CLEAN URL" — cache layer of the same discipline
- Insight source: weekly distillation 2026-09-30 (verification-ladder near-misses on 4 sites: header menu, embedded widget, JS block removal, AJAX popup; fixed-bar overlap)
