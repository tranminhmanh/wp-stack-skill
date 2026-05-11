# Workflow: Design system rollout (Astra + Elementor)

Apply a brand design system (colors, typography, shadows, utility classes) to a WordPress site running Astra + Elementor. Order-dependent, cascade-aware, with a post-rollout widget audit because widget-level hardcoded settings can silently override the global brand.

## When to use

✅ New brand guidelines arrive (palette + typography + components) and need to land on a live site.
✅ Rebrand of an inherited site — current site uses an old palette / font baked into widget settings.
✅ Migration from one design system to another (vN.0 → vN+1.0).
❌ Single-page tweak — too much overhead. Just update the page.
❌ Pure Gutenberg site — Astra-Elementor cascade rules don't apply.

## The 3-layer architecture

Brand rollout on Astra + Elementor lives in three layers. Each layer overrides the one above when it sets a value; each layer falls through to the one above when it does not.

```
LAYER 1 — Astra theme (foundation)
├─ astra-update-theme-color     → 6 base colors (text / heading / link / border / accent / link-hover)
├─ astra-update-global-palette  → 9 palette slots (--ast-global-color-0..8)
├─ astra-update-font-heading    → heading font + weight + line-height
└─ astra-update-font-body       → body font + weight + size + line-height (with desktop/tablet/mobile)
    │
    ▼ (fall through when Elementor kit does not set the property)

LAYER 2 — Elementor kit (semantic global)
├─ update-global-colors         → 4 system colors (primary / secondary / text / accent)
├─ update-global-typography     → 4 system fonts (heading / sub-heading / body / CTA)
└─ update-page-settings(kit_id) → kit-level `custom_css` field (design tokens, utility classes)
    │
    ▼ (fall through when widget setting does not override)

LAYER 3 — Widget settings (instance-level)
└─ widget.settings.title_color = "#467FF7" ← 🚨 hardcoded HEX beats Astra + kit
   widget.settings.typography_font_family = "Poppins" ← hardcoded font beats kit typography
```

**Implication for the rollout sequence**: setting layers 1 and 2 correctly is necessary but **not sufficient**. Widgets created on old templates carry hardcoded values from the previous brand and will override the new globals. Step 3 below is the widget-audit phase that catches these overrides.

## Phase 1 — Layer 1: Astra theme (foundation)

Order within Phase 1 matters because Astra's font-rendering depends on the palette being set first:

```
1. astra-update-global-palette   (9 color slots first — Astra typography rules reference these)
2. astra-update-theme-color      (text / heading / link / border bind to palette via var(--ast-global-color-N))
3. astra-update-font-heading     (heading font; ⚠️ font_weight clamped ≤ 700, see "Gotchas" below)
4. astra-update-font-body        (body font + responsive sizes; set desktop/tablet/mobile separately)
5. astra-update-global-buttons   (primary button; call again separately for secondary — schema accepts 1 type per call)
6. astra-flush-font-local        (force-clear local-font cache so Astra re-detects new fonts)
```

After step 6, verify the frontend:
```bash
curl -s "https://<site>/?cb=$(date +%s)" \
  | grep -oE -- '--ast-global-color-0:[^;]*'
# Expect: --ast-global-color-0:#<your-primary>

curl -s "https://<site>/?cb=$(date +%s)" \
  | grep -oE "font-family:'?[A-Z][a-zA-Z ]*" | sort -u
# Expect: only brand fonts. No leftover Roboto / Open Sans / system stack.
```

## Phase 2 — Layer 2: Elementor kit (semantic global)

```
7. elementor-mcp-update-global-colors      (4 system colors override Astra for Elementor widgets)
8. elementor-mcp-update-global-typography  (4 typography presets — heading/sub/body/CTA)
9. update-page-settings(kit_id, {custom_css: "..."}) 
                                            (kit custom_css injects design tokens + utility classes site-wide)
```

**Finding kit_id**:
```bash
curl -u "$U:$APP_PW" "https://<site>/wp-json/wp/v2/elementor_library?_fields=id,title,modified&per_page=20" \
  | jq '.[] | select(.title.rendered=="Default Kit")'
# The kit you want is the one whose `modified` timestamp updates when you change Elementor global colors via the editor.
```

**Kit `custom_css` is the right place for design tokens**:
```php
// Use update-page-settings on the kit post (NOT add-custom-css — that adds inline per page).
update-page-settings(post_id=<kit_id>, settings={
  "custom_css": ":root{--p-500:#FF4E88;--p-600:#E63970;--t-500:#78DEC7;--n-900:#1A1A1A;...} .utility-class{...}"
})
```

The kit `custom_css` is rendered as `<style id="elementor-...-css-id">` on every Elementor-rendered page — true site-wide.

Verify:
```bash
curl -s "https://<site>/?cb=$(date +%s)" | grep -c '<style id="elementor-' 
# > 0 = kit custom_css is rendering
curl -s "https://<site>/?cb=$(date +%s)" | grep -oE -- '--p-500:\s*#[A-Fa-f0-9]+'
# Expect: --p-500: #<your-primary>
```

## Phase 3 — Layer 3: Widget audit + bulk fix (the step most people skip)

Setting layers 1 and 2 doesn't update widgets that hardcoded the old brand into their `settings`. You have to find them and rewrite them.

### 3a. Bulk-scan for off-brand markers via JSON walk

Don't try per-field checks — there are too many field names across widget types. Just JSON-dump the widget's `settings` and grep for known off-brand color hex / font family:

```python
import json

def find_off_brand(elements, results, off_brand_colors, off_brand_fonts):
    for el in elements:
        eid = el.get('id', '?')
        wt = el.get('widgetType', '') or el.get('elType', '')
        s_str = json.dumps(el.get('settings', {}), ensure_ascii=False)

        bad = []
        for color in off_brand_colors:
            if color.lower() in s_str.lower():
                bad.append(color)
        for font in off_brand_fonts:
            if (f'"font_family":"{font}"' in s_str
                or f'"typography_font_family":"{font}"' in s_str):
                bad.append(font)

        if bad:
            results.append({'id': eid, 'widget': wt, 'bad': bad})
        if 'elements' in el:
            find_off_brand(el['elements'], results, off_brand_colors, off_brand_fonts)

# Example call
OFF_BRAND_COLORS = ['#467FF7', '#1C244B', '#324A6D']    # the OLD palette to find
OFF_BRAND_FONTS  = ['Poppins', 'Roboto', 'Roboto Slab'] # the OLD typography
results = []
for post_id in pages_to_audit:
    data = json.loads(get_post_meta(post_id, '_elementor_data', True))
    find_off_brand(data, results, OFF_BRAND_COLORS, OFF_BRAND_FONTS)
```

Why JSON-string grep beats per-field check:
- No need to know all field names per widget type (each widget has 50–100 settings)
- Catches inline HTML colors inside `editor` / `html` fields
- Catches nested settings (button `hover_color`, accordion `icon_active_color`, etc.)
- One pass, fully recursive

### 3b. Refine with `get-element-settings` for hits

For each off-brand widget found, call `get-element-settings(element_id)` to see exactly which fields hold the off-brand value, then plan the update.

### 3c. Bulk batch-update via `elementor-mcp-batch-update`

Single call updates N widgets at once. Significantly faster than N sequential `update-widget` calls + cache invalidates auto:

```python
operations = []
for hit in results:
    # Plan per hit — map off-brand value to brand value
    new_settings = {}
    if '#467FF7' in hit['bad']:
        new_settings['title_color'] = '#FF4E88'  # or use __globals__: ...
    if 'Poppins' in hit['bad']:
        new_settings['typography_font_family'] = 'Be Vietnam Pro'
    if new_settings:
        operations.append({'element_id': hit['id'], 'settings': new_settings})

batch_update(post_id=N, operations=operations)
# 60+ widgets updated in one call
```

### 3d. Better: bind to globals instead of hardcoding

Future-proof brand rollouts by binding widget settings to globals references, not hardcoded hex:

```python
# Instead of:
{"title_color": "#FF4E88"}

# Use:
{"__globals__": {"title_color": "globals/colors?id=primary"}}
```

Next brand update only requires changing `update-global-colors` — every widget that bound via `__globals__` follows automatically.

### 3e. Element Pack subscriber-filter sweep (if Element Pack Pro is active)

Element Pack Pro old versions leave `display_condition_list: [{"display_condition_login_status": "subscriber"}]` on widgets created via templates. Catch them in the same walk:

```python
def scan_subscriber_filter(elements, results):
    for el in elements:
        dcl = el.get('settings', {}).get('display_condition_list', [])
        if any(c.get('display_condition_login_status') == 'subscriber'
               for c in dcl if isinstance(c, dict)):
            results.append(el['id'])
        if 'elements' in el:
            scan_subscriber_filter(el['elements'], results)

# Then bulk-clear:
ops = [{'element_id': eid, 'settings': {'display_condition_list': []}} for eid in results]
batch_update(post_id=N, operations=ops)
```

Site-wide infection scale on one real audit: 88 widgets on the homepage + 63 on the contact page = 151+ widgets in two pages alone. See [`references/pitfalls.md`](../references/pitfalls.md) "Element Pack Pro legacy `display_condition_list`".

## Phase 4 — Verify + cache clear

After Phase 1–3:

```bash
# 1. Visual smoke check on 5 representative pages
for path in / /about/ /service-X/ /contact/ /blog/post-1/; do
  curl -sI "https://<site>$path?cb=$(date +%s)" | head -1
done

# 2. Off-brand frequency count — should be 0
for path in /pages-list...; do
  curl -s "https://<site>$path?cb=$(date +%s)" | grep -c "#467FF7\|Poppins"
done

# 3. LiteSpeed CCSS may still cache OLD design — see pitfalls.md "LiteSpeed CCSS staleness"
# Some pages may need manual purge via wp-admin or mass page-edit to trigger CCSS regen
```

## Gotchas observed in production

### Astra heading font_weight clamped ≤ 700

`astra-update-font-heading font_weight=800` returns `font_weight: 700` in response. Astra UI dropdown only accepts up to 700, schema silently ignores 800/900. Workarounds:
1. Use 700 for Astra base
2. Override per-element in Elementor (Elementor does not clamp)
3. Inject CSS in kit `custom_css`: `h1, h2, h3 { font-weight: 800 !important; }`

### `astra-update-global-buttons` accepts only ONE button_type per call

Don't try to set primary + secondary in one payload — the schema rejects. Two separate calls: one with `button_type: "primary"`, one with `button_type: "secondary"`.

### Astra MCP response can be stale (returns old value but write applied)

`astra-update-global-palette` may return an OUTPUT schema error, but the underlying DB UPDATE actually succeeded. Always verify via frontend curl, never trust the response object.

### Roboto preload list does not auto-clear after font swap

After `astra-flush-font-local`, Astra's preload list may still include the OLD font's woff2 for a few requests. Disable + re-enable the "Local Fonts" feature in Astra Customizer to force a regenerate.

### Widget cascade override is the most common failure mode

Layers 1+2 set correctly, but widgets render in the OLD brand because their settings hardcode the old hex / font. Phase 3 audit + bulk-fix is mandatory on any inherited site. On a fresh build with disciplined `__globals__` binding, you can skip Phase 3.

## Sample rollout timing (one real session)

| Phase | Work | Time |
|---|---|---|
| Phase 1 | Astra colors + palette + heading font + body font + buttons + flush | ~20 min |
| Phase 2 | Elementor 4 colors + 4 typography presets + kit `custom_css` (~5KB) | ~25 min |
| Phase 3 | Site-wide audit (88+63 widgets subscriber filter; 50+ hardcoded color hits; 30+ font overrides) + batch-update | ~75 min |
| Phase 4 | Verify + cache clear | ~10 min |
| **Total** | | **~130 min** |

For a green-field site without Phase 3 widget hardcoding to clean up, total drops to ~55 min.

## Cross-references

- [`references/astra-customizer.md`](../references/astra-customizer.md) — Astra MCP tool reference (which setting key maps to what)
- [`references/elementor-mcp.md`](../references/elementor-mcp.md) — `update-page-settings` on kit post, `__globals__` binding pattern
- [`references/pitfalls.md`](../references/pitfalls.md) — Element Pack subscriber filter, Astra font_weight clamp, widget hardcode cascade
- [`references/stack.md`](../references/stack.md) — Astra Pro vs Elementor Pro feature overlap
- [`workflows/ui-verification.md`](ui-verification.md) — verify-don't-assume after each phase
- [`workflows/comprehensive-audit.md`](comprehensive-audit.md) — 8-dimension audit for post-rollout health check
