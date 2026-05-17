# MCP Cheatsheet — msrbuilds/elementor-mcp

## Connection

```json
{
  "mcpServers": {
    "elementor-mcp": {
      "type": "http",
      "url": "https://<site>/wp-json/mcp/elementor-mcp-server",
      "headers": {
        "Authorization": "Basic <base64 of username:app-password>"
      }
    }
  }
}
```

Generate the base64: `echo -n "admin:xxxx xxxx xxxx xxxx xxxx xxxx" | base64`

⚠️ The username is the **actual login slug** (admin / email-slug), NOT the Application Password label.
⚠️ The app password must KEEP the spaces.

## Param rules (common traps)

1. `add-container` takes `settings: {}` object
2. `add-*` widget shortcuts (add-heading, add-button) take **flat params**, NOT nested settings
3. `update-widget` / `update-element` use `settings: {}` object
4. Typography keys require `typography_typography: "custom"` to activate
5. Background requires `background_background: "classic"` before setting color
6. Flexbox keys are prefixed `flex_*`: `flex_direction`, `flex_justify_content`, `flex_align_items`, `flex_gap`, `flex_wrap`

## Standard container (section)

```
add-container(
  page_id: 123,
  parent_id: null,
  settings: {
    content_width: "boxed",
    boxed_width: {size: 1280, unit: "px"},
    padding: {top: "96", right: "32", bottom: "96", left: "32", unit: "px", isLinked: false},
    padding_tablet: {top: "64", right: "24", bottom: "64", left: "24", unit: "px"},
    padding_mobile: {top: "48", right: "16", bottom: "48", left: "16", unit: "px"},
    flex_direction: "column",
    background_background: "classic",
    background_color: "#0A0A0A"
  }
)
```

## 3-column grid container

```
add-container(
  page_id: 123,
  parent_id: <section_id>,
  settings: {
    content_width: "full",
    flex_direction: "row",
    flex_gap: {size: 32, unit: "px"},
    flex_gap_tablet: {size: 24, unit: "px"},
    flex_gap_mobile: {size: 16, unit: "px"},
    flex_wrap: "wrap"
  }
)
```

## Heading widget

```
add-heading(
  page_id: 123,
  parent_id: <container_id>,
  title: "Title",
  header_size: "h1",
  align: "center",
  title_color: "#FFFFFF",
  typography_typography: "custom",
  typography_font_family: "Inter",
  typography_font_size: {size: 56, unit: "px"},
  typography_font_size_tablet: {size: 40, unit: "px"},
  typography_font_size_mobile: {size: 32, unit: "px"},
  typography_font_weight: "700"
)
```

## Button widget

```
add-button(
  page_id: 123,
  parent_id: <container_id>,
  text: "Get a quote",
  link: {url: "/contact", is_external: false},
  size: "lg",
  background_color: "#FF4500",
  hover_color: "#FFFFFF",
  border_radius: {size: 8, unit: "px"}
)
```

## Image widget

```
add-image(
  page_id: 123,
  parent_id: <container_id>,
  image: {id: <media_id>, url: "https://..."},
  image_size: "large",
  align: "center",
  width: {size: 100, unit: "%"}
)
```

## Form widget (Pro)

```
add-form(
  page_id: 123,
  parent_id: <container_id>,
  form_name: "Quote",
  form_fields: [
    {field_type: "text",     field_label: "Name",        required: true},
    {field_type: "tel",      field_label: "Phone",       required: true},
    {field_type: "email",    field_label: "Email",       required: true},
    {field_type: "date",     field_label: "Event date",  required: true},
    {field_type: "select",   field_label: "Service type",
     field_options: "Option 1\nOption 2\nOption 3"},
    {field_type: "textarea", field_label: "Notes",       required: false}
  ],
  email_to: "info@<domain>",
  button_text: "Send"
)
```

## Element ID format

The element ID returned by Elementor is 7 hex characters (e.g. `f8d1545`).
SAVE it after every `add-*` call so you can use it for later update / move / delete.

## Verify pattern (REQUIRED)

After each major section:
```
get-page-structure(page_id: 123)
```

After a batch of edits, clear cache:
```
clear_elementor_cache(page_id: 123)
```

## Backup before editing production

```
backup_elementor_data(page_id: 123)
```

The plugin saves to a separate meta key, so you can restore if something breaks.

## Pin npm version in `.mcp.json`

`npx elementor-mcp` resolves to a different version depending on npm cache state. Sometimes it pulls v1.0.0 (old, missing tools), sometimes v1.4.x (full toolset).

```json
{
  "mcpServers": {
    "elementor-mcp": {
      "command": "npx",
      "args": ["-y", "elementor-mcp@latest"]
    }
  }
}
```

Or lock a specific version: `"elementor-mcp@1.4.2"`. After editing `.mcp.json`, **reload the Claude Code session** to pick up the change.

## File format conventions (`update_page_from_file`, `download_page_to_file`)

`update_page_from_file` accepts 2 formats and rejects 1:

| Format | Accepted | Note |
|---|---|---|
| Plain JSON array `[{...},{...}]` | ✅ | `json.dump(elements_array, f)` |
| Full WP REST response wrapper (output of `download_page_to_file`) | ✅ | `{"id":N, "meta":{"_elementor_data":[...]}, ...}` |
| Object wrapper `{"_elementor_data": [...]}` | ❌ | MCP returns `true`, REST returns 200, postmeta saved as a string → render 500 fatal `Undefined array key "elType"` |

**Recipe for pushing payload from Python**:
```python
import json
elements_array = build_sections()
with open('/tmp/page-43.json', 'w') as f:
    json.dump(elements_array, f, ensure_ascii=False)  # plain array, NOT wrapped
```

## Verify pattern (REQUIRED after every write op)

MCP `return true` ≠ render OK. After every `update_page_*`, `update-widget`, plugin toggle, or option set:

```bash
URL="$WP_SITE/<path>?cb=$(date +%s)"
curl -sI "$URL" | head -1                                         # expect HTTP 200
curl -s "$URL" | grep -c '<title>WordPress.*Error\|wp-die-message' # expect 0
```

If fatal → roll back immediately (`backup_elementor_data` first; or use `wp-fix.php` for a site-wide crash).

DO NOT batch many updates and then verify at the end.

## After MCP create page → regenerate post CSS

A page created via API / MCP may be missing `--flex-basis` CSS variables because Elementor only generates that CSS when the user sets column width in the Editor UI. Symptom: 4-column layout has no widths and renders randomly.

**Fix**: trigger CSS regeneration:
```php
\Elementor\Core\Files\CSS\Post::create($post_id)->update();
// or
delete_post_meta($post_id, '_elementor_css');
\Elementor\Plugin::$instance->files_manager->clear_cache();
```

Or open the page in the Elementor Editor and save (which triggers CSS regen).

## Widget schema gotchas

The schema is not consistent across widgets — always `get-widget-schema` first:

| Widget / setting | Correct format | Trap |
|---|---|---|
| Counter `typography_number_typography` | `"yes"` | Not `"custom"` (heading uses `"custom"`) |
| Heading `typography_typography` | `"custom"` | Not `"yes"` |
| Background `background_background` | `"classic"` | Required before setting color |
| Testimonial Carousel pagination | `pagination: "bullets"` + `loop: "yes"` | Not `navigation: "dots"` + `infinite: "yes"` |
| Testimonial Carousel `image_border_radius` | `{size, unit}` simple form | Not `{top,right,bottom,left}` like image widget |
| nav-menu in a flex-row header | `_flex_size: "grow"` | Counter-intuitive — `"shrink"` makes the `<ul>` items wrap onto a new line |
| Pro Form `email_subject` field ref | `[field id="field_4"]` | Not `{{field_4}}` or `[field_label]`. IDs auto-generated from 0 |
| Pro Form field `required` | `"yes"` | Not `"true"` (schema enum only accepts `["yes"]`) |
| Counter `ending_number` | integer only | `26.5` rejected. Round before sending |
| Image responsive width | `width`, `width_tablet`, `width_mobile` (3 fields) | Not one field with a responsive object |
| Counter icon | emoji (📅⚓⚡) directly | FA Unicode `\\f5d2` does not render reliably in Elementor |

**`add-price-list` schema rejected**: a `price_list` array of objects fails validation. Workaround: HTML widget with custom CSS classes `.x-price-table` + `.x-price-row`.

### `add-price-table` `currency_format` — required for non-decimal prices

**Symptom**: setting `price: "8500000"` on a price-table widget renders as `"8"` on the frontend (the rest is silently truncated).

**Root cause**: default `currency_format = "."` makes Elementor parse `8500000` as a decimal — period (`.`) is the delimiter, the value before the first delimiter is `8`. Without an explicit format, big integer prices break.

**Fix**: explicitly set `currency_format: ","` so Elementor treats the value as a plain integer:
```python
add-price-table(
  price="8500000",
  currency_format=",",          # ← REQUIRED for VND / IDR / large integer currencies
  currency_symbol_position="after",
  currency_symbol_custom=" đ"   # space + symbol
)
```

Render: `8,500,000 đ`.

Applies to any locale that uses comma as the thousands separator (most non-English locales). For US-style `$8,500.00`, the default `.` format is correct.

### `show_ribbon` carries over from cloned price-table cards

**Symptom**: setting `show_ribbon: "yes"` on one price-table card → after cloning the card 3 times, ALL 3 clones show the ribbon. Only one was supposed to be highlighted.

**Root cause**: `show_ribbon` is a regular setting; clone / duplicate operations carry it over by default. There is no "default empty" — once set on a card, every duplicate inherits it.

**Fix**: explicitly clear it on cards that should NOT have a ribbon:
```python
update-widget(
  widget_id="card-without-ribbon-id",
  settings={"show_ribbon": ""}   # empty string, NOT False / null / "no"
)
```

⚠️ The schema requires `""` (empty string) — `False`, `null`, or `"no"` may render as truthy depending on Elementor version. Verify by re-reading the structure after update.

**Reusability**: same pattern applies to other "presence" settings on Elementor widgets (`show_*`, `_animation`, `_css_classes`) — clone carries the value, explicit empty-string is the way to clear.

### `css_classes` field name — different for widget vs container

The custom-CSS-class field name differs by element type and using the wrong one is a silent save-no-render:

| Element type | Correct field |
|---|---|
| **Widget** (heading, button, text-editor, image, …) | `_css_classes` (WITH underscore prefix) |
| **Container** (Flexbox `e-con`) | `css_classes` (NO underscore) |

```python
# Widget
update_widget(post_id=N, widget_id=W, settings={"_css_classes": "my-title"})

# Container
update_element(post_id=N, element_id=C, settings={"css_classes": "my-card"})
```

Wrong field for the element type = value persists in `_elementor_data` but no `class="..."` rendered. Schema-confirm via `get-container-schema` / `get-widget-schema` if unsure. Full diagnosis: [`pitfalls.md`](pitfalls.md) "`css_classes` field name — widget vs container difference".

## Container & structure quirks

### `add-container` cells append at INDEX 0 (FILO)

Sequential adds → DOM order is reversed. Adding 5 cells 1→5 → DOM order 5→4→3→2→1.

**Fix**: use `reorder-elements` with `container_id` + `element_ids` array in the right order after all adds.

⚠️ `reorder-elements` schema: use `container_id` (not `parent_id`) + `element_ids` (not `ordered_ids`). Schema trap.

### `update-page-settings` works on the Elementor kit post

The kit is a regular post type `elementor_library` with `_elementor_template_type: kit`. You can edit `container_width`, `custom_css`, `space_between_widgets` via MCP:
```
update-page-settings(
  post_id: <option elementor_active_kit>,
  settings: {
    container_width: {size: 1280, unit: "px"},
    custom_css: ".e-con-full > .elementor-widget { max-width: 1280px; ... }"
  }
)
```
→ applies globally to every page. No need to go through the Customizer UI.

### `update-page-settings` does NOT update post fields

Returns `success: true` but `post_status`, `post_parent`, `post_name` do NOT actually apply. You have to call `wp_update_post()` directly via docker exec / SSH.

### `add-form` schema enums

- `required`: `"yes"` (NOT `"true"`)
- `field_type`: `"text"`, `"tel"`, `"email"`, `"date"`, `"select"`, `"textarea"`, ...

### `grid_gaps` vs `gap` naming inconsistency

| `container_type` | Property | Format |
|---|---|---|
| `grid` | `grid_gaps` | `{column, row, unit, size, isLinked}` |
| `flex` | `gap` | `{column, row, unit, size}` |

Check `container_type` before setting.

### Shape divider built-in (V4 native)

Container settings `shape_divider_bottom`, `shape_divider_bottom_color`, `shape_divider_bottom_height`, `shape_divider_bottom_flip`. Shapes: `waves`, `mountains`, `clouds`, `tilt`, `triangle`, `arrow`. Apply per section bottom edge → smooth transition between sections of different bg colors.

5-minute setup, big visual impact. No need for SVG embed by hand.

### Counter widget swap pattern

When cloning a page and swapping counter values, do NOT `str_replace` `ending_number` (the number is not unique). Walk the JSON, match by `widgetType === 'counter'` + the original `settings.title`. Helper `update_counter_by_title()` in [`templates/snippets/elementor-data-update.php`](../templates/snippets/elementor-data-update.php).

## Settings that need post-CSS regen to apply

Set via MCP / REST → DB stores it correctly, but the live render has no inline style or CSS rule for it. A page A with the same setting renders correctly (because it was created via the Editor → CSS regen happens automatically). Page B, pushed through MCP later, does not.

**Affected settings** (list updated as encountered):
- `title_color` on heading widgets
- `typography_*` properties (`font_size`, `font_weight`, `letter_spacing`, ...)
- `_padding`, `_margin` with responsive units
- Custom column width (`_inline_size`)

**Workarounds (pick one)**:
1. **mu-plugin CSS rule** targeting the widget class wrapper (`.cta-heading .elementor-heading-title { color: ... !important; }`) — bypasses post-CSS entirely, works immediately. **Reliable for automation.**
2. **Force Elementor post-CSS regen** via PHP one-shot:
   ```php
   \Elementor\Core\Files\CSS\Post::create($post_id)->update();
   ```
3. Open the page in the Editor and save manually (does not scale).

## Column width: `_column_size` is NOT enforced

Setting `_column_size: 63` via MCP / REST → the DB stores it correctly. But the rendered DOM shows column width ~51% (`flex: 0 1 auto`, `flexBasis: auto`, `width: auto`). Computed width follows content, not 63%.

**Root cause**: Elementor only generates a `width: X%` rule when `_inline_size` is set. `_column_size` is just a fallback for the old non-flex column layout.

**Fix (pick one)**:
1. Also set `_inline_size: 63` (numeric) → Elementor emits inline `width: 63%`.
2. **Recommended**: CSS flex override:
   ```css
   .header-nav { flex: 1 1 0 !important; }              /* grow to fill */
   .header-logo, .header-cta { flex: 0 0 auto !important; }  /* shrink to content */
   ```
   More robust because of content-driven sizing — logo / CTA shrink to text, nav grows to fill space.

When building a new layout via MCP, **verify the actual width in the browser** — do not trust the DB value.

## Class propagation across nested sections

When styles rely on a parent class (e.g. `.dark` for dark-bg sections), apply the class to ALL nested sections, because `:not()` chains check the closest ancestor section, NOT an outer ancestor.

A typical Elementor CTA structure:
```
outer-section.dark
  → column
    → inner-section (no class)
      → column
        → widget
```

The heading's `.closest('section')` is the inner-section without `.dark` → `:not([class~="dark"])` still matches → the rule still applies.

**Fix**: walk the tree and propagate `.dark` to every section inside an outer-dark:
```php
function propagate_class_to_nested_sections(array &$elements, string $cls): void {
    foreach ($elements as &$el) {
        if (($el['elType'] ?? '') === 'section') {
            $el['settings']['css_classes'] = trim(($el['settings']['css_classes'] ?? '') . ' ' . $cls);
        }
        if (!empty($el['elements'])) {
            propagate_class_to_nested_sections($el['elements'], $cls);
        }
    }
}
```

## `update_page_from_file` does NOT regen `post_content`

Pushing an edited JSON file via `mcp__elementor__update_page_from_file` → REST `/wp/v2/pages/N?context=edit` shows the new `_elementor_data`. BUT `.content.rendered` (the `post_content` column) still has the old HTML → frontend renders from `post_content`, so the user does not see the change.

**Root cause**: `update_page_*` MCP only updates the `_elementor_data` meta via REST. It does not trigger the Elementor save handler → no HTML regen → no LiteSpeed page-cache invalidation.

**Standard workflow for image bulk swap / data update**:
1. Edit JSON locally
2. `mcp__elementor__update_page_from_file` to push the data
3. **`mcp_batch_update`** (or `update_widget` / `update_element`) with the same settings just pushed → goes through the Elementor save pipeline → re-renders `post_content` + invalidates LiteSpeed cache

→ The 2-step pattern is required for any bulk update via MCP.

## Image widget `update-widget` needs the full object

When updating an image widget, setting only `id` is NOT enough:

```json
// WRONG — Elementor caches the URL inside _elementor_data, does not re-resolve from the ID
{
  "image": { "id": 3872 }
}

// RIGHT — full object
{
  "image": {
    "id": 3872,
    "url": "https://example.com/wp-content/uploads/2026/05/hero.jpg",
    "alt": "Descriptive alt text for SEO",
    "source": "library",
    "size": ""
  }
}
```

After the update, the Elementor frontend auto-generates a thumb at `wp-content/uploads/elementor/thumbs/[basename]-rn[hash].jpg` (the hash bypasses old CDN cache).

## `add-form` does NOT set `custom_id` — manual patch after build

MCP `add-form` builds form fields but does NOT enforce `custom_id` → silent submission failures. See [`pitfalls.md` "CRITICAL: Pro Form silent fail"](pitfalls.md) for full detection + fix.

**Rule**: after every `add-form`, manually patch fields with semantic `custom_id` (`name`, `email`, `phone`, `route`, ...). Do NOT use the default `field_1` / `field_2`.

## WP MCP Abilities API — input wrapper format

All MCP servers (mcp-wp, elementor-mcp, custom WAE abilities) share the same Abilities API REST endpoint pattern:

### Read-only abilities (GET method)

URL syntax: `?input[key]=value` (PHP-style nested array, URL-encoded):
```
GET /wp-json/wp-abilities/v1/abilities/elementor-mcp/get-page-structure/run?input%5Bpost_id%5D=206
```

Wrong:
- ❌ POST with body — server rejects with "Read-only abilities require GET method"
- ❌ Direct query param `?post_id=206` — "input is not of type object"
- ❌ JSON-encoded query `?input={...}` — does not decode correctly

### Write abilities (POST method)

Body wrapper required:
```json
{
  "input": {
    "post_id": 206,
    "element_id": "92f9b3b",
    "settings": {...}
  }
}
```

### Discover loaded abilities

```
GET /wp-json/wp-abilities/v1/abilities
```

→ Lists all loaded abilities + their schemas. When the MCP client tool returns `-32603: Failed to get ability details: 404`, fall back to direct REST calls via the endpoint above.

## Elementor Pro Custom Code Snippets — built-in CPT for site-wide JS / CSS / HTML

Elementor Pro ships a built-in **Custom Code** feature (CPT `elementor_snippet`) that injects site-wide JS / CSS / HTML without needing a separate plugin (no Code Snippets plugin, no `functions.php` edit, no mu-plugin file). Useful for:
- Mobile menu fix snippets (see [`astra-mobile-menu.md`](astra-mobile-menu.md))
- 3rd-party analytics tags (GA4, Tag Manager, Hotjar, Microsoft Clarity)
- A11y JS patches (see [`a11y-debugging.md`](a11y-debugging.md))
- Brand-specific CSS overrides that don't fit in the kit `custom_css`

⚠️ This only handles JS / CSS / HTML — **no PHP**. For PHP snippets you still need the Code Snippets plugin or a mu-plugin file.

### Access via wp-admin

`wp-admin → Templates → Custom Code → Add New Custom Code`

### Snippet settings

| Setting | Values | Note |
|---|---|---|
| **Location** | `<head>` / `Body - Start` / `Body - End` / `wp_footer` | Determines where the snippet is injected. Default `<head>` for most JS/CSS. Use `Body - End` for scripts that need DOM ready without a `DOMContentLoaded` wrapper. |
| **Priority** | 1–999 (default 10) | Lower = earlier. Multiple snippets at the same location are output by priority order. Set 5 for "before everything else", 100 for "after everything else". |
| **Frequency** | Every Page / Every Page Once | "Once" loads the snippet once per session (good for trackers). "Every Page" re-injects on every load (good for fixes that re-init). |
| **Conditions** | Include / Exclude rules per page / template / language | Same condition system as Elementor Theme Builder. Apply globally with "Include: Entire Site". |

### Manage via MCP

Elementor MCP exposes the snippet CPT with tools (versions vary by plugin release):
- `list_code_snippets()` — enumerate all snippets with id, title, status, location, priority
- `add_code_snippet(...)` — create a new snippet with the same settings as the wp-admin form
- (Some MCP server versions also support `update_code_snippet` and `delete_code_snippet`. Check `list_widgets` / tool inventory on your site.)

### Direct REST fallback (when MCP tool unavailable)

```bash
# List snippets
curl -u "$U:$APP_PW" \
  "$SITE/wp-json/wp/v2/elementor_snippet?per_page=50&_fields=id,title,status,meta"

# Create a snippet (CPT REST allowed because elementor_snippet has show_in_rest=true)
curl -u "$U:$APP_PW" -X POST "$SITE/wp-json/wp/v2/elementor_snippet" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Mobile menu iOS bfcache fix",
    "status": "publish",
    "content": "<script>/* JS here */</script>",
    "meta": {
      "_elementor_snippet_location": "wp_footer",
      "_elementor_snippet_priority": 5,
      "_elementor_snippet_frequency": "every_page"
    }
  }'
```

### When to use Custom Code Snippet vs alternatives

| Tool | When |
|---|---|
| **`elementor_snippet`** (Elementor Pro built-in) | JS / CSS / HTML site-wide, no separate plugin, condition-based scope |
| **Kit `custom_css`** (via `update-page-settings` on kit post) | Site-wide CSS only, applies to Elementor-rendered pages |
| **Code Snippets plugin** | PHP snippets (Elementor's `elementor_snippet` does NOT support PHP) |
| **mu-plugin file** | Always-on PHP, can't be deactivated via wp-admin |

### Pitfalls

- ⚠️ Default frequency is **"Every Page"** — heavy snippets at high traffic re-execute on every load. For analytics / tracking, switch to "Once".
- ⚠️ Conditions inherit from Elementor Theme Builder's UI — be aware of the same display-condition gotchas (see [`pitfalls.md`](pitfalls.md) "Element Pack Pro legacy `display_condition_list: subscriber`").
- ⚠️ Multiple snippets at the same location + same priority → execution order is non-deterministic. Use distinct priorities to guarantee order.

## Elementor 4.0 — `update-page-settings custom_css` field does NOT load on frontend

**Symptom**: Set CSS via MCP `update-page-settings` field `custom_css`:
```python
update-page-settings(post_id=2926, settings={"custom_css": ".my-class { color: red; }"})
```
→ HTTP 200, save thành công vào `_elementor_page_settings` meta. **BUT** front-end KHÔNG load CSS này. View source → no `<style>` tag.

**Root cause (suspected)**: Elementor 4.0 quirk — field `custom_css` saved to DB nhưng renderer KHÔNG output. Có thể migration 3.x → 4.x mất rule, hoặc field này yêu cầu Elementor Pro license verification fail silently.

**Workaround — HTML widget với `<style>` block**:

Inject CSS via `add-html` widget ở vị trí `position=0` của container đầu tiên (Hero):
```python
add-html(
  parent_id="<hero-container-id>",
  html_content='<style id="brand-design-system">/* CSS rules here */</style>'
)
```

Browser xử lý `<style>` trong body như scoped CSS toàn page. Verified work cho:
- Section padding rhythm
- Card aspect-ratio constraints
- Fluent Forms button override
- FAQ accordion styling

**Lesson**:
- KHÔNG TIN field name "custom_css" trong page settings → verify front-end view-source sau mỗi set
- HTML widget injection là workaround tin cậy hơn cho Elementor 4.0 hiện tại
- Đặt `<style id="...">` để dễ track + debug trong DevTools

**Alternative**: edit kit `custom_css` via `update-page-settings(kit_id, {custom_css: "..."})` — kit-level CSS DOES load (verified). Use kit for global tokens, HTML widget for page-specific overrides.

## FontAwesome Pro-only icons render EMPTY box

**Symptom**: Icon `champagne-glasses`, `cake-candles`, `champagne-glass`, etc. set trong Elementor → render empty box trên frontend.

**Root cause**: Đây là icons Pro của Elementor (FA Pro license). Elementor render free version → không tìm thấy → silent empty.

**Fix**: Dùng free alternatives:

| Pro (broken) | Free (works) | Use case |
|---|---|---|
| `champagne-glasses` | `glass-cheers` | Cheers / celebration |
| `cake-candles` | `birthday-cake` | Birthday / celebration |
| `champagne-glass` | `glass-martini-alt` | Single drink |
| `face-smile-beam` | `smile` | Happy face |
| `gem` (some weights) | `diamond` | Premium / gift |

**Verify**: Search [fontawesome.com/search?o=r&m=free](https://fontawesome.com/search?o=r&m=free) trước khi pick icon — confirm "Free" tag, not "Pro". (FontAwesome restructured the legacy `/v5/free` URL to a search-based one; see [`pitfalls.md`](pitfalls.md) "Pro FontAwesome icons render empty on Free Elementor" — fixed in v0.5.0, regression reintroduced + corrected in v0.7.2.)

**Alternative**: Use FontAwesome CDN with Pro license URL — but requires kit setup + license sync to site. Free FA tier covers 1500+ icons, usually enough.

## Elementor section `background_image` lưu trong post-X.css, KHÔNG inline HTML

**Symptom**: Sau khi `batch_update` set `background_image` cho hero section, `grep` HTML không tìm thấy URL ảnh. Tưởng là chưa apply.

**Reality**: Elementor render bg-image ra file `wp-content/uploads/elementor/css/post-{ID}.css` (external stylesheet), NOT inline `<style>` trong HTML.

**Verify đúng cách**:
```bash
# Fetch post CSS file riêng
curl -sL "https://site/wp-content/uploads/elementor/css/post-43.css" | grep <url-pattern>

# Hoặc DevTools Computed styles trên element
```

**Lesson**: Khi audit Elementor visual settings (background, padding via responsive units, animation), đừng grep HTML — fetch post-X.css file riêng. Inline HTML chỉ chứa CSS classes, không actual styles.

**Architecture summary**:
- `_elementor_data` (DB meta) → JSON với widget settings
- Elementor renderer → generate `wp-content/uploads/elementor/css/post-{ID}.css` (external stylesheet)
- Frontend HTML → reference post-X.css via `<link>` + classes only

## Diagnostic technique: demote `header_size` to find H1 duplication source

**Use case**: Frontend renders 2+ H1 tags on same page. Em phải xác định source:
- (A) Cùng widget render 2 lần (recursion / cache bug)
- (B) 2 different widget instances
- (C) Widget + theme/template overlay

**Pattern**: tạm thời update 1 widget với `header_size: "h2"`. Sau đó re-fetch frontend, count H1 vs H2.

```python
# Step 1: Identify candidate widget rendering H1
find-element(post_id=8004, widget_type="theme-post-title")  # returns widget id 2f0a7929

# Step 2: Demote temporarily
update-widget(post_id=8004, element_id="2f0a7929", settings={"header_size": "h2"})

# Step 3: Re-fetch + count
# curl -s site/page?cb=$(date +%s) | grep -c '<h1'  → check H1 count
# curl -s site/page?cb=$(date +%s) | grep -c '<h2.*"Title text"'  → check H2 count
```

**Interpretation**:

| Result | Conclusion |
|---|---|
| H1=0, H2=2 với same text | Same widget renders twice (recursion / cache) — investigate `theme-post-content` self-recursion or render layer |
| H1=1, H2=1 với same text | 2 widget instances exist — find second via `find-element` widget_type="heading" |
| H1=1, no H2 với text | 1 H1 from updated widget, other H1 from elsewhere (template overlay, plugin inject, Astra builtin) |

**Step 4**: Revert widget back to `header_size: "h1"` after diagnosis. Test takes ~30 seconds.

**Reusability**: Universal cho any heading-tag-flexible widget (Elementor heading, theme-post-title, Astra Customizer site-title). Apply when seeing render multiplicity that data doesn't explain.

## Elementor heading widget strips SVG / HTML from `title` setting (`wp_kses_post`)

**Symptom**: trying to inject an SVG icon inline with heading text via `update-widget`:

```python
mcp_call("update-widget", {
    "post_id": 2932,
    "element_id": "360307a3",
    "settings": {"title": '<svg><use href="#icon-co2-jet"/></svg>CO₂ Jets — X-1'}
})
# Response: {"success": true, "element_id": "360307a3"}
```

Frontend renders:
```html
<h2 class="elementor-heading-title">CO₂ Jets — X-1</h2>
<!-- SVG stripped silently — no error -->
```

**Root cause**: Elementor heading widget runs `wp_kses_post()` on the `title` field. SVG tags + `<use>` element are NOT in the default `allowed_html` list → stripped without error.

### 3 workarounds (pick by use case)

| Workaround | Pros | Cons |
|---|---|---|
| **1. Separate HTML widget BEFORE the heading** | Native widgets preserved, clean separation | Layout assumes 2 widgets in flow — flex/grid needs adjusting |
| **2. Replace heading widget entirely with HTML widget** containing `<h2>` + SVG | Full HTML control | Loses Elementor heading widget styling presets (typography panel etc.) |
| **3. JS runtime injection** after page load | Keep native widgets | Breaks SSR / SEO — Google scrapes the H2 WITHOUT the icon |

### Recommendation

Use option 1 (separate HTML widget) for SVG icons before headings. Add via `elementor-mcp-add-html` with the same `parent_id` as the heading widget:

```python
# Add HTML widget BEFORE the heading
mcp_call("elementor-mcp-add-html", {
    "post_id": 2932,
    "parent_id": "<container_id>",
    "position": 0,  # before heading
    "html": '<svg width="32" height="32"><use href="#icon-co2-jet"/></svg>',
})
```

For pages with many heading + icon pairs (>5), consider option 2 — convert the entire section to HTML widgets to avoid widget-count bloat.

### Other widgets affected

Same `wp_kses_post` filter applies to:
- `text-editor` widget `editor` field (less strict — allows more tags)
- `button` widget `text` field (strict — no HTML)
- `icon-box` widget `title_text` field (strict)

When in doubt: test with an SVG sample BEFORE building the full pipeline.

## SVG upload blocked by host WAF — CSS `mask-image` data URI workaround

**Symptom**: `POST /wp/v2/media` with an SVG file (MIME `image/svg+xml`):

```bash
curl -u "$U:$APP_PW" -X POST "$SITE/wp-json/wp/v2/media" \
  -H "Content-Disposition: attachment; filename=\"icon.svg\"" \
  -H "Content-Type: image/svg+xml" \
  --data-binary "@./icon.svg"

# Response: HTTP 500
# {"code":"rest_upload_unknown_error","message":"Rất tiếc, bạn không được phép tải lên định dạng tệp tin này"}
```

**Root cause**: Most shared hosts (AZDIGI, similar) + Imunify360 WAF block SVG uploads by default — prevents XSS via SVG `<script>` payloads. The block is at the host / WAF level, not WordPress itself. You can't store SVG as standalone Media Library attachments.

### Workaround — CSS `mask-image` with data URI

For native Elementor icon-box widgets that need custom SVG icons: inline the SVG as a CSS `mask-image` data URI. The browser uses the SVG to MASK a CSS background color (mono-color result, but supports hover transitions).

```css
/* Hide the default Font Awesome icon */
#post-224 .elementor-element-<wid> .elementor-icon i {
  display: none !important;
}

/* Show the custom SVG via mask */
#post-224 .elementor-element-<wid> .elementor-icon::before {
  content: '';
  display: block;
  width: 60px;
  height: 60px;
  background-color: #0A1F44;                    /* the icon "color" */
  -webkit-mask-image: url("data:image/svg+xml;utf8,<svg ...>...</svg>");
  mask-image: url("data:image/svg+xml;utf8,<svg ...>...</svg>");
  mask-repeat: no-repeat;
  mask-position: center;
  mask-size: contain;
}
```

### URL-encoding the SVG for data URI

```python
import urllib.parse

svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="..."/></svg>'

# Preserve common SVG chars → human-readable, smaller output
encoded = urllib.parse.quote(svg, safe="*'()/=,:;!?[]{}")
data_uri = f'data:image/svg+xml;utf8,{encoded}'

# Use in CSS: mask-image: url("<data_uri>")
```

`safe=` keyword controls which characters NOT to encode — preserving brackets / spaces / quotes makes the data URI readable AND smaller.

### Trade-offs

- ✓ No host upload needed → bypasses WAF restriction
- ✓ Hover transitions work (`background-color` + `transform`)
- ✓ No CORS, no XHR, no Media Library entry
- ✗ Mono-color only (mask cuts shape, background-color fills)
- ✗ ~500-800 chars per data URI → ~6 KB CSS overhead for 3 icons
- ✗ Editable only in CSS source, not via wp-admin UI

### When to use SVG-upload-allowed alternatives

If the site host doesn't block SVG (some VPS hosts, Cloudflare R2, S3 + media offload), upload the SVG normally. Even if some hosts allow it, the `mask-image` pattern is still useful when:
- You want a theme-able icon (color shifts via CSS, not file re-export)
- You're injecting via Code Snippets / mu-plugin and don't want a Media Library dependency

## Site-wide conversion tracking via Custom Code Snippet — multi-platform pattern

**Extending** the "Elementor Pro Custom Code Snippets" section above with a real-world conversion-tracking recipe — fires events into GA4 + Meta Pixel + TikTok concurrently, all wrapped in `try/catch` so a missing platform doesn't break the others.

### Usage

```python
mcp_call("elementor-mcp-add-code-snippet", {
    "title":    "Conversion Tracking — Site-Wide",
    "code":     TRACKING_JS,         # full JS shown below
    "location": "body_end",
    "priority": 5,
    "status":   "publish",
})
```

`location: body_end` ensures DOM is ready when the script runs — no need for `DOMContentLoaded` wrapper.

### Multi-platform fire pattern

```javascript
function fire(eventName, params) {
  // GA4 — gtag from Site Kit / Tag Manager
  try { if (typeof gtag === 'function') gtag('event', eventName, params); } catch(e) {}
  // Meta Pixel — fbq
  try { if (typeof fbq === 'function') fbq('trackCustom', eventName, params); } catch(e) {}
  // TikTok Pixel — ttq
  try { if (typeof ttq === 'object' && ttq.track) ttq.track(eventName, params); } catch(e) {}
}
```

Each platform's global is checked + wrapped — a missing global (pixel not installed yet) silently no-ops.

### Event delegation for dynamic / late-bound elements

Use capture-phase delegation on `document` to catch clicks on elements that may be injected after page load (Elementor lazy widgets, AJAX-loaded content, etc.):

```javascript
document.addEventListener('click', function(e) {
  // Click-to-call
  var phone = e.target.closest('a[href^="tel:"]');
  if (phone) fire('click_to_call', { phone: phone.getAttribute('href').replace('tel:', '') });

  // Email link click
  var email = e.target.closest('a[href^="mailto:"]');
  if (email) fire('click_to_email', { email: email.getAttribute('href').replace('mailto:', '') });

  // Form submit button (delegate, not direct)
  var submit = e.target.closest('.elementor-button[type="submit"], .wpcf7-submit, .ff-btn-submit');
  if (submit) fire('form_submit_click', { form: submit.closest('form')?.getAttribute('id') });
}, true);  // capture: true = runs before bubble handlers
```

`closest()` matches the click target OR its ancestors → catches clicks on icon children inside a wrapping anchor (e.g. a phone icon inside `<a href="tel:...">`).

### Future-proof

`fire()` calls `fbq` / `ttq` even when the pixel isn't installed yet — the `try/catch` silently skips. When you install Meta Pixel later, all your existing events automatically start flowing. No need to redeploy the snippet.

### Anti-patterns

❌ **Inline tracking JS on individual buttons** — fragile (button changes break tracking), inconsistent (forgot one button)
❌ **Skip the `try/catch`** — one missing platform breaks the others' fire
❌ **Use `addEventListener` without capture phase** — late-bound elements miss events
❌ **Fire same event into multiple platforms via separate code blocks** — DRY: one `fire()` helper, three platform calls inside

## WP Admin upload (`async-upload.php`) vs REST `/wp/v2/media`

The two upload endpoints take **different code paths**:

| Endpoint | Auth | Hooks triggered | Failure modes |
|---|---|---|---|
| `/wp-admin/async-upload.php` | nonce + cookies | `wp_handle_upload`, `wp_generate_attachment_metadata` | UI-friendly errors |
| `/wp/v2/media` (REST) | Basic Auth (App Password) | Same hooks BUT through different wrapper code | 500 fatal silent if a hook crashes |

**Workaround when REST is broken**: drag-drop via WP Admin → still works. Useful for bulk upload if the REST script fails. Also use the MCP `sideload_image` ability → a third code path — fallback if both above fail.
