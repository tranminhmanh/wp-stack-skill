# Astra mobile menu — complete debug reference

Astra Free + Pro have a mobile menu system with **3 distinct render modes**, each with its own DOM structure, body class names, and JavaScript click handlers. Cross-mode debugging is a recurring pain point — a fix that works on a `dropdown`-mode site silently fails on an `off-canvas`-mode site (and vice versa). This file documents the mode differences + the recurring iOS Safari bfcache state-desync bug + the production-tested 6-layer defense pattern.

## When to use this file

✅ Mobile menu doesn't open on iOS Safari after browser back-button navigation
✅ Hamburger button changes to X but the menu visually doesn't appear
✅ Copy-pasted "fix" from a sibling Astra site mysteriously doesn't work
✅ Need to detect which mode the site is on before applying a fix
✅ Building a robust mobile menu for an Astra site that uses Elementor Theme Builder + LiteSpeed + WP Speculation Rules

❌ Not running Astra — different theme, different mobile-menu system
❌ The site only uses the Elementor `nav-menu` widget without Astra header involvement

## Mode detection — the foundation step

Astra Pro has 3 mobile menu modes. **Detect first, fix second.** Skipping detection = wasting hours applying the wrong fix.

```bash
# Get the mode value from the rendered HTML
curl -s "https://<site>/" | grep -oE 'id="ast-mobile-header"[^>]*data-type="[^"]*"'
# Output examples:
#   data-type="dropdown"    → dropdown mode (Astra default)
#   data-type="off-canvas"  → off-canvas mode (Pro feature, also called "flyout")
#   data-type="fullscreen"  → fullscreen mode (Pro feature)
```

If `data-type` is missing, the site is on Astra Free with the default dropdown.

## 3 modes — full comparison

| Property | Dropdown | Off-canvas (Flyout) | Fullscreen |
|---|---|---|---|
| `data-type` value | `dropdown` | `off-canvas` | `fullscreen` |
| Body class when open | `ast-main-header-nav-open` | `ast-popup-nav-open` + `<html>` has `ast-off-canvas-active` | `ast-popup-nav-open` |
| Menu DOM location | Inline inside `#ast-mobile-header` | Separate `#ast-mobile-popup` element appended to body | Same as off-canvas |
| Click handler (Free) | `astraNavMenuToggle()` | n/a (Pro feature) | n/a (Pro feature) |
| Click handler (Pro) | Same as Free | `astraNavMenuTogglePro()` | Same as off-canvas |
| Transition style | `max-height` + `opacity` slide-down | `transform: translateX()` slide-in | `opacity` fade + scale |
| Typical pitfall | Class-name fixes from off-canvas sites apply wrong class → menu invisible | Targeting `#ast-mobile-header` instead of `#ast-mobile-popup` | Same as off-canvas |

⚠️ **Most common debug trap**: a fix written for `off-canvas` mode (using `ast-popup-nav-open`) gets copy-pasted onto a `dropdown`-mode site. The class is added to `<body>` but Astra's CSS doesn't trigger the dropdown reveal → button changes to X, menu stays invisible. Always detect mode first.

## The iOS Safari bfcache state-desync bug

**Symptom**: on an iPhone (any model running Safari), navigate Home → another page → tap browser back / logo → tap hamburger → menu does NOT open. The X icon may appear (toggle handler fired) but the menu DOM stays hidden. Force-refresh fixes it once, but the bug returns on the next back-navigation.

**Root cause**: iOS Safari aggressively restores pages from its back/forward cache (bfcache). Astra's JS init runs on `DOMContentLoaded`, which does **not** fire on bfcache restore — the JS keeps a stale internal state from the previous page (toggle thinks it's still in the "menu closed" state from BEFORE the navigation away, even though `<body>` has the `ast-main-header-nav-open` class lingering from the original visit).

**Why other Astra sites don't always hit this**: the bug needs a stack-factor cocktail to reliably trigger. See [`workflows/multi-factor-bug-debug.md`](../workflows/multi-factor-bug-debug.md) "Astra mobile menu iOS bfcache" — the 5 factors that combine to make this bug appear:
1. Page template `elementor_header_footer` (Elementor takes over the template; Astra header injects via hook with different DOM ordering)
2. WP Speculation Rules API (WP 6.4+) — background prefetch caches stale HTML
3. Dropdown mode (more fragile state than off-canvas, which has its menu in a separate DOM tree)
4. iOS Safari bfcache (aggressive restoration without re-running init scripts)
5. LiteSpeed JS combine + CCSS (timing of when JS becomes available vs when DOM ready fires)

A site with 1–2 of these factors might work fine. A site with all 5 has the bug deterministically.

## The 6-layer defense pattern (production-tested)

After iterating from v1 → v5 on a real production site, the architecture that holds up under the cocktail is:

```
LAYER 1 — CSS transitions on the visible class
  .ast-mobile-header-content {
    opacity: 0; max-height: 0; transform: translateY(-8px);
    transition: opacity .25s, max-height .35s, transform .25s;
  }
  body.ast-main-header-nav-open .ast-mobile-header-content {
    opacity: 1; max-height: 80vh; transform: translateY(0);
  }
  /* Do NOT use display:none — kills transitions. Use max-height/opacity. */

LAYER 2 — Stagger animation for menu items
  body.ast-main-header-nav-open .menu li {
    animation: slidein .3s ease forwards;
  }
  body.ast-main-header-nav-open .menu li:nth-child(1) { animation-delay: 0.05s; }
  /* ... 2..6 */

LAYER 3 — JS class manager (DO NOT use inline display:block — kills CSS transitions)
  toggle.addEventListener('click', () => {
    document.body.classList.toggle('ast-main-header-nav-open');
    toggle.setAttribute('aria-expanded',
      document.body.classList.contains('ast-main-header-nav-open'));
  });

LAYER 4 — Capture-phase fallback (in case Astra's handler is dead post-bfcache)
  document.addEventListener('click', e => {
    if (e.target.closest('.ast-button-wrap .menu-toggle')) {
      e.stopPropagation();  // beat Astra's bubble handler if it's broken
      // Manually toggle the class
      document.body.classList.toggle('ast-main-header-nav-open');
    }
  }, true);  // capture: true = runs BEFORE bubble handlers

LAYER 5 — pageshow handler (covers BOTH bfcache AND normal navigation)
  window.addEventListener('pageshow', e => {
    // Reset state: close any stuck menu, reset aria
    document.body.classList.remove('ast-main-header-nav-open');
    document.querySelectorAll('.menu-toggle').forEach(t =>
      t.setAttribute('aria-expanded', 'false'));
    // e.persisted = true means bfcache restore — most aggressive case
  });

LAYER 6 — Mobile-width reload safety net (last resort, mobile only)
  let lastWidth = window.innerWidth;
  window.addEventListener('resize', () => {
    if (window.innerWidth <= 768 && lastWidth > 768) {
      // Coming back to mobile width — full reload to re-init everything
      location.reload();
    }
    lastWidth = window.innerWidth;
  });
```

Deploy via Elementor Pro Custom Code Snippet (CPT `elementor_snippet`) with **Location: `wp_footer`, Priority: 5** so it runs early in the footer order. See [`elementor-mcp.md`](elementor-mcp.md) "Elementor Pro Custom Code Snippets".

## Iterative debug journey — v1 → v5 (lessons per version)

When the bug appeared, the fix went through 5 versions. Each iteration taught something:

| Version | What was tried | Why it failed | Lesson |
|---|---|---|---|
| **v1** | `pageshow` handler that resets state ONLY when `e.persisted === true` | Misses bug on normal back-navigation (not just bfcache restore) | bfcache is not the only trigger — handle every navigation |
| **v2** | Broader reset on `pageshow` regardless of `persisted` | Used class `ast-mobile-menu-active` — but that class doesn't exist in Astra | Don't assume class names; dump live Astra JS to see what classes it actually uses |
| **v3** | Manual toggle with class `ast-popup-nav-open` | That's the off-canvas class — site was dropdown mode → CSS doesn't reveal | Detect mode FIRST (`data-type`). Class names differ by mode |
| **v4** | Manual toggle with correct class `ast-main-header-nav-open` + inline `display: block` | Menu shows but snaps in without animation | Inline `display: block` kills CSS transitions. Let CSS handle the visual; JS only toggles the class |
| **v5** | CSS-driven slide animation + stagger items, JS only manages the class. Plus capture-phase fallback + pageshow reset + width-reload safety net | ✓ Works smooth across bfcache, normal nav, prefetch | Animation = CSS, state = JS class. Add multiple defensive layers — bfcache is too unreliable to trust one fix |

This pattern (iterative add-layer debug) is captured generically in [`workflows/multi-factor-bug-debug.md`](../workflows/multi-factor-bug-debug.md).

## Astra MCP can't set `mobile-menu-style` — manual fix path

The mode choice (`dropdown` / `off-canvas` / `fullscreen`) lives in `theme_mod('astra-settings')['mobile-menu-style']` — a serialized array value with no dedicated MCP tool exposure (see [`astra-customizer.md`](astra-customizer.md) "Astra MCP coverage gaps").

To switch modes:
- **Easiest**: wp-admin → Customize → Header Builder → Mobile Menu → Mobile Menu Style → pick one → publish.
- **Scripted (PHP snippet)**:
  ```php
  $astra = get_theme_mod('astra-settings', []);
  $astra['mobile-menu-style'] = 'off-canvas';   // switch to off-canvas (Pro)
  set_theme_mod('astra-settings', $astra);
  delete_transient('astra_theme_dynamic_css_cached');
  ```

**Mode switch as a fix**: when the dropdown-mode cocktail bug is too tangled to fix in-mode, switching the site to off-canvas mode often resolves the bug at the architecture level (off-canvas keeps its menu in a separate DOM subtree → bfcache state desync is less likely to cause invisible-menu). The trade-off is a different UX feel — confirm with the user before switching the visible mode.

## Common debug commands

```bash
# 1. Confirm mode
curl -s "https://<site>/" | grep -oE 'data-type="[^"]*"' | head -1

# 2. Verify body class is being added on toggle (run in DevTools console after tapping hamburger)
document.body.className

# 3. Verify pageshow handler is registered
getEventListeners(window)['pageshow']   // Chrome DevTools

# 4. Test bfcache simulation in Safari
# Reload → navigate away → navigate back → tap hamburger
# Compare body class state before/after back-nav

# 5. Verify Speculation Rules API is on (WP 6.4+)
curl -s "https://<site>/" | grep -oE '<script[^>]*type="speculationrules"' | head -1
# If present, the site is prefetching — adds to the cocktail
```

## Cross-references

- [`workflows/multi-factor-bug-debug.md`](../workflows/multi-factor-bug-debug.md) — methodology for "only this site has this bug" cases
- [`references/astra-customizer.md`](astra-customizer.md) "Astra MCP coverage gaps" — why `mobile-menu-style` isn't reachable via Astra MCP
- [`references/elementor-mcp.md`](elementor-mcp.md) "Elementor Pro Custom Code Snippets" — where to deploy the 6-layer defense
- [`references/pitfalls.md`](pitfalls.md) "LiteSpeed CCSS staleness" — companion gotcha when LiteSpeed CCSS doesn't update after a snippet change
- [`workflows/ui-verification.md`](../workflows/ui-verification.md) — verify-don't-assume discipline; always test on the real device after each layer
