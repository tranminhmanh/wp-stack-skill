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

## WP Admin upload (`async-upload.php`) vs REST `/wp/v2/media`

The two upload endpoints take **different code paths**:

| Endpoint | Auth | Hooks triggered | Failure modes |
|---|---|---|---|
| `/wp-admin/async-upload.php` | nonce + cookies | `wp_handle_upload`, `wp_generate_attachment_metadata` | UI-friendly errors |
| `/wp/v2/media` (REST) | Basic Auth (App Password) | Same hooks BUT through different wrapper code | 500 fatal silent if a hook crashes |

**Workaround when REST is broken**: drag-drop via WP Admin → still works. Useful for bulk upload if the REST script fails. Also use the MCP `sideload_image` ability → a third code path — fallback if both above fail.
