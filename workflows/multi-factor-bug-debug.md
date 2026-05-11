# Workflow: Multi-factor "cocktail" bug debug

When a bug appears on **one site** but doesn't reproduce on other sites with the same theme + plugins, the cause is usually NOT a single root cause — it's a combination of 4–6 stack factors that individually are harmless but combine into a state desync. This workflow inverts the standard "find the root cause" pattern: enumerate the unique factors, then identify which combination triggers the bug.

## When to use this workflow

✅ Bug shows up on production but not on staging (and staging is "close enough" to prod)
✅ Bug shows up on one client's site but not on a similar client's site with the same plugins
✅ A "standard fix" from documentation / Stack Overflow doesn't work on this specific site
✅ You've spent >1 hour searching for "the cause" and nothing fits

❌ Brand-new site with no stack history — single-factor debugging is fine
❌ Bug reproduces consistently across multiple environments — that's a regular bug, find the root cause

## The mental flip

Standard debug mindset:
> Something is broken. There must be a single root cause. Find it, fix it, done.

Multi-factor cocktail mindset:
> Multiple components are each behaving within spec, but their **interaction** produces an unexpected state. The fix is rarely "remove component X" — it's "introduce a defensive layer that handles the interaction explicitly."

## Step 1 — Confirm the bug is site-specific

Before applying this workflow, rule out:
- Browser / OS / device specific (test on multiple)
- User-account specific (test as anonymous)
- Cache / state specific (test in incognito after full cache clear)

If the bug reproduces only on one specific site under standard conditions on multiple devices → site-specific, proceed to Step 2.

## Step 2 — Enumerate stack factors

List every stack factor that's unique to this site. Compare against the typical setup for the same theme + plugins on other working sites. Sources for the factor list:
- WordPress version + active plugin list + active theme (`wp plugin list --status=active` or `/wp/v2/plugins`)
- Active page template (`_wp_page_template` post meta)
- Server stack (LiteSpeed / WP Rocket / Cloudflare / nginx config)
- WordPress core features that ship enabled (Speculation Rules API since 6.4+, BlockEditor, REST surface)
- Browser-side enhancements (bfcache, service workers, prefetch hints)
- Theme settings (mobile menu mode, header style, conditional load)

A typical Astra + Elementor + LiteSpeed site has ~15 toggleable factors. Most sites overlap on 12 — the 3 that differ are usually the cocktail ingredients.

## Step 3 — Identify the candidate factor set

For each unique factor, ask: "does this factor change DOM, state, or timing in any way?" If yes, it's a candidate.

| Factor type | Examples | Effect |
|---|---|---|
| **DOM injection / takeover** | Elementor Theme Builder template, mu-plugin DOM rewriters, custom theme hooks | Changes HTML structure, breaks selectors that expect the default theme DOM |
| **State persistence** | WP Speculation Rules API prefetch, browser bfcache, service workers | Restores stale state from before navigation |
| **Render mode** | Astra mobile menu mode (dropdown vs off-canvas), Elementor atomic mode vs classic | Different DOM + JS handlers per mode; cross-mode fixes silently fail |
| **Cache layer** | LiteSpeed CCSS, WP Rocket optimization, server-side full-page cache, Cloudflare | Serves stale content; obscures recent changes; introduces timing bugs |
| **JS timing** | Combine + minify (LiteSpeed JS combine), defer / async modes, late-bound CDN | DOMContentLoaded fires at different times relative to inline scripts |
| **Plugin add-on** | Element Pack, UAEL, Essential Addons, Crocoblock | Inject CSS / JS that mutates other plugins' DOM |

Real example — the [`astra-mobile-menu.md`](../references/astra-mobile-menu.md) "iOS Safari bfcache state-desync" bug had 5 cocktail factors:
1. Page template `elementor_header_footer` (Elementor takeover changes Astra header DOM ordering)
2. WP Speculation Rules API prefetch (caches stale HTML)
3. Astra mobile menu mode = dropdown (more fragile state than off-canvas; menu is in main DOM tree, not separate subtree)
4. iOS Safari bfcache (aggressive restoration without re-running init scripts)
5. LiteSpeed JS combine + CCSS (timing changes when JS becomes available vs when DOM ready fires)

Sites with 1–2 of these factors didn't have the bug. The site with all 5 had it deterministically. Looking for "the cause" would have wasted hours.

## Step 4 — Mental model: state desync

The shape of most cocktail bugs is **state desync between components that each assume they're operating on a fresh page load**:

```
                       NORMAL PAGE LOAD
                  ┌──────────────────────────┐
   DOMContentLoaded ─► Plugin A: init state │
                  │   Plugin B: init state  │
                  │   Plugin C: init state  │
                  │   ──────────            │
                  │   All in sync (= 0)     │
                  └──────────────────────────┘

                       BFCACHE / PREFETCH RESTORE
                  ┌──────────────────────────────────┐
   pageshow event ──► (DOMContentLoaded NOT fired)  │
                  │   Plugin A: state restored (= 1)│
                  │   Plugin B: re-init (= 0)        │ ◄── DESYNC
                  │   Plugin C: state restored (= 1) │
                  │   ──────────                    │
                  │   B's "I'm closed" disagrees with│
                  │   A's "you're open"             │
                  └──────────────────────────────────┘
```

Fix isn't "remove A or B" — it's "add a `pageshow` listener that resets ALL components to a known state before user interaction."

## Step 5 — Iterative add-layer fix

Once you have the factor list and the state-desync mental model, the fix is rarely one change. It's a stack of defensive layers, each addressing one branch of the state machine:

```
LAYER 1: CSS handles the visual (don't let JS set inline styles)
LAYER 2: JS handles state classes (not visual properties)
LAYER 3: Capture-phase fallback for when the default handler is dead
LAYER 4: pageshow reset (covers bfcache + normal nav)
LAYER 5: Mobile-width / breakpoint-change reload safety net
LAYER 6: Server-side cache invalidation (kit CSS, CCSS, page cache)
```

Each layer is independently verifiable + independently failable. If layer 4 doesn't fire (browser doesn't support `pageshow`), layer 5 catches it. If layer 5 doesn't fire (user doesn't resize), layer 1+2 already handle the standard case.

**Iterate v1 → vN**: start with the simplest layer that should address the largest factor (usually layer 4 — pageshow reset). Test → adds new symptom → next layer. Don't try to write all 6 layers up-front; you don't know the order they fail in.

Sample iteration log from the [`astra-mobile-menu.md`](../references/astra-mobile-menu.md) reference:
- **v1**: `pageshow` handler with `e.persisted === true` only → missed normal navigation. Lesson: don't filter on bfcache only.
- **v2**: Broader reset, but used wrong class name → no effect. Lesson: detect actual class names from live JS, don't assume.
- **v3**: Correct class assumed but off-canvas mode → wrong class for dropdown mode. Lesson: detect mode (`data-type`) first.
- **v4**: Correct class + inline `display:block` → menu shows but snaps. Lesson: don't let JS set visual properties; let CSS animate.
- **v5**: CSS animation + JS class toggle + capture-phase fallback + pageshow reset → ✓ smooth across all factor combinations.

## Step 6 — Document the cocktail for future

When you find the factor set, write it down (insights doc, CLAUDE.md, project memory). Otherwise the next person investigating "why is this only broken here?" repeats your hours.

```markdown
### Bug: <one-line symptom>
**Site-specific cocktail (5 factors)**:
1. Factor A — <why it contributes>
2. Factor B — <why it contributes>
...

**Fix**: <reference to the layered solution>
**Lesson**: <what was non-obvious>
```

Promote to skill (via [`workflows/session-distillation.md`](session-distillation.md)) when the cocktail recurs on a second project. One sighting = project-specific. Two sightings = candidate. Three = promote to skill reference.

## Anti-patterns

❌ **"There must be a single root cause"** — wastes hours looking for one bullet when the bug is a multi-factor interaction.

❌ **"Just remove component X"** — components A and B are each fine. Removing one fixes the symptom but breaks the feature each provides. Add a layer; don't remove a component.

❌ **"It works on my machine"** — your machine has 4 of the 5 factors. The user's has all 5. Reproduce on the actual stack (same browser, same network conditions, same cache state).

❌ **"Copy the fix from the sibling site"** — sibling site has 3 of 5 factors. The fix that works there is incomplete here. Re-derive the layer stack for the actual factor set.

❌ **"Disable cache while debugging"** — disabling LiteSpeed makes the bug disappear (eliminates a factor), but production has the cache on. Always reproduce + fix on the production stack.

## When to escalate to "switch a component instead of layering"

After 5–6 iterations and the layered fix is still fragile, consider whether one of the cocktail factors can be changed. Example: if Astra dropdown mode is more fragile than off-canvas, **switch the site to off-canvas mode** instead of stacking more defensive layers. The mode is a single setting; changing it eliminates Factor C from the cocktail. Trade-off: visible UX changes — confirm with the user.

This is a last resort because changing a stack factor is a bigger commitment than adding a JS layer, but sometimes it's the cleaner fix.

## Cross-references

- [`references/astra-mobile-menu.md`](../references/astra-mobile-menu.md) — concrete case study of this workflow applied (iOS Safari + 5 cocktail factors → 6-layer defense)
- [`workflows/ui-verification.md`](ui-verification.md) — verify each layer's effect with screenshots + measurements, don't trust mental model
- [`workflows/session-distillation.md`](session-distillation.md) — promote the cocktail pattern to skill once seen on a second project
- [`references/pitfalls.md`](../references/pitfalls.md) — individual factor entries (LiteSpeed CCSS staleness, Element Pack subscriber filter, etc.)
