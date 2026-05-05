# MCP Cheatsheet — msrbuilds/elementor-mcp

## Connection

```json
{
  "mcpServers": {
    "elementor-mcp": {
      "type": "http",
      "url": "https://<site>/wp-json/mcp/elementor-mcp-server",
      "headers": {
        "Authorization": "Basic <base64 của username:app-password>"
      }
    }
  }
}
```

Sinh base64: `echo -n "admin:xxxx xxxx xxxx xxxx xxxx xxxx" | base64`

⚠️ Username là **login slug thực** (admin/email-slug), KHÔNG phải label của Application Password.
⚠️ App password phải GIỮ NGUYÊN khoảng trắng.

## Quy tắc params (BẪY THƯỜNG GẶP)

1. `add-container` lấy `settings: {}` object
2. `add-*` widget shortcuts (add-heading, add-button) lấy **flat params**, KHÔNG nested settings
3. `update-widget` / `update-element` dùng `settings: {}` object
4. Typography keys phải có `typography_typography: "custom"` mới active
5. Background phải `background_background: "classic"` trước khi set color
6. Flexbox keys prefix `flex_*`: flex_direction, flex_justify_content, flex_align_items, flex_gap, flex_wrap

## Container chuẩn (section)

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

## Container 3-column grid

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
  title: "Tiêu đề",
  header_size: "h1",
  align: "center",
  title_color: "#FFFFFF",
  typography_typography: "custom",
  typography_font_family: "Be Vietnam Pro",
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
  text: "Yêu cầu báo giá",
  link: {url: "/lien-he", is_external: false},
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
  form_name: "Báo giá",
  form_fields: [
    {field_type: "text", field_label: "Họ tên", required: true},
    {field_type: "tel", field_label: "Số điện thoại", required: true},
    {field_type: "email", field_label: "Email", required: true},
    {field_type: "date", field_label: "Ngày sự kiện", required: true},
    {field_type: "select", field_label: "Loại dịch vụ",
     field_options: "Option 1\nOption 2\nOption 3"},
    {field_type: "textarea", field_label: "Mô tả thêm", required: false}
  ],
  email_to: "info@<domain>",
  button_text: "Gửi yêu cầu"
)
```

## Element ID format

Element ID Elementor trả về: 7 ký tự hex (vd: `f8d1545`).
LƯU lại sau mỗi add-* call để dùng cho update/move/delete sau.

## Verify pattern (BẮT BUỘC)

Sau mỗi section quan trọng:
```
get-page-structure(page_id: 123)
```

Sau loạt edit, clear cache:
```
clear_elementor_cache(page_id: 123)
```

## Backup trước edit production

```
backup_elementor_data(page_id: 123)
```

Plugin lưu vào meta riêng, restore được nếu hỏng.

## Pin npm version trong .mcp.json

`npx elementor-mcp` resolve khác version tùy npm cache state. Có lúc pull v1.0.0 (cũ, thiếu tools), lúc pull v1.4.x (đủ tools).

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

Hoặc lock cụ thể: `"elementor-mcp@1.4.2"`. Sau update `.mcp.json`, **reload Claude Code session** mới load được.

## File format conventions (`update_page_from_file`, `download_page_to_file`)

`update_page_from_file` chấp nhận 2 format, từ chối 1:

| Format | Accepted | Note |
|---|---|---|
| Plain JSON array `[{...},{...}]` | ✅ | `json.dump(elements_array, f)` |
| Full WP REST response wrapper (output của `download_page_to_file`) | ✅ | `{"id":N, "meta":{"_elementor_data":[...]}, ...}` |
| Object wrapper `{"_elementor_data": [...]}` | ❌ | MCP trả `true`, REST trả 200, postmeta saved as string → render 500 fatal `Undefined array key "elType"` |

**Recipe push payload từ Python**:
```python
import json
elements_array = build_sections()
with open('/tmp/page-43.json', 'w') as f:
    json.dump(elements_array, f, ensure_ascii=False)  # plain array, NOT wrapped
```

## Verify pattern (BẮT BUỘC mỗi write op)

MCP `return true` ≠ render OK. Sau mỗi `update_page_*`, `update-widget`, plugin toggle, option set:

```bash
URL="$WP_SITE/<path>?cb=$(date +%s)"
curl -sI "$URL" | head -1                                         # expect HTTP 200
curl -s "$URL" | grep -c '<title>WordPress.*Lỗi\|wp-die-message'  # expect 0
```

Nếu fatal → rollback ngay (`backup_elementor_data` trước; hoặc dùng `wp-fix.php` recovery cho site-wide crash).

KHÔNG batch nhiều update rồi mới verify.

## Sau MCP create page → regen post CSS

Page tạo qua API/MCP có thể thiếu `--flex-basis` CSS variables vì Elementor chỉ generate CSS khi user set column width trong Editor UI. Symptom: 4-col layout không có width, render ngẫu nhiên.

**Fix**: chạy CSS regeneration:
```php
\Elementor\Core\Files\CSS\Post::create($id)->update();
// hoặc
delete_post_meta($id, '_elementor_css');
\Elementor\Plugin::$instance->files_manager->clear_cache();
```

Hoặc visit page trong Elementor Editor rồi save (trigger CSS gen).

## Widget schema gotchas

Schema không consistent giữa các widget — phải `get-widget-schema` mỗi lần:

| Widget / setting | Format đúng | Trap |
|---|---|---|
| Counter `typography_number_typography` | `"yes"` | Không phải `"custom"` (heading dùng `"custom"`) |
| Heading `typography_typography` | `"custom"` | Không phải `"yes"` |
| Background `background_background` | `"classic"` | Phải set trước khi đặt color |
| Testimonial Carousel pagination | `pagination: "bullets"` + `loop: "yes"` | Không phải `navigation: "dots"` + `infinite: "yes"` |
| Testimonial Carousel `image_border_radius` | `{size, unit}` simple | Không phải `{top,right,bottom,left}` như image widget |
| nav-menu trong header flex row | `_flex_size: "grow"` | Counter-intuitive — `"shrink"` làm `<ul>` items wrap dòng |
| Pro Form `email_subject` field ref | `[field id="field_4"]` | Không phải `{{field_4}}` hay `[field_label]`. IDs auto từ 0 |
| Pro Form field `required` | `"yes"` | Không phải `"true"` (schema enum chỉ accept `["yes"]`) |
| Counter `ending_number` | integer only | `26.5` reject. Round trước khi gửi |
| Image responsive width | `width`, `width_tablet`, `width_mobile` (3 fields) | Không phải 1 field với responsive object |
| Counter icon | emoji 📅⚓⚡ trực tiếp | FA unicode `\\f5d2` không render reliably trong Elementor |

**`add-price-list` schema rejected**: `price_list` array of objects không pass validation. Workaround: HTML widget với custom CSS class `.sa-price-table` + `.sa-price-row`.

## Container & structure quirks

### `add-container` cells append at INDEX 0 (FILO)

Sequential adds → DOM order ngược. Add 5 cells 1→5 → DOM order 5→4→3→2→1.

**Fix**: dùng `reorder-elements` với `container_id` + `element_ids` array đúng order sau khi add xong.

⚠️ `reorder-elements` schema: dùng `container_id` (không phải `parent_id`) + `element_ids` (không phải `ordered_ids`). Schema trap.

### `update-page-settings` works on Elementor kit post

Kit chỉ là regular post type `elementor_library` với `_elementor_template_type: kit`. Có thể edit `container_width`, `custom_css`, `space_between_widgets` qua MCP:
```
update-page-settings(
  post_id: <option elementor_active_kit>,
  settings: {
    container_width: {size: 1280, unit: "px"},
    custom_css: ".e-con-full > .elementor-widget { max-width: 1280px; ... }"
  }
)
```
→ áp dụng global cho mọi page. Không cần thông qua Customizer UI.

### `update-page-settings` KHÔNG update post fields

Returns `success: true` nhưng `post_status`, `post_parent`, `post_name` KHÔNG apply. Phải dùng `wp_update_post()` PHP trực tiếp qua docker exec / SSH.

### `grid_gaps` vs `gap` naming inconsistency

| `container_type` | Property | Format |
|---|---|---|
| `grid` | `grid_gaps` | `{column, row, unit, size, isLinked}` |
| `flex` | `gap` | `{column, row, unit, size}` |

Check `container_type` trước khi set.

### Shape divider built-in V4 native

Container settings `shape_divider_bottom`, `shape_divider_bottom_color`, `shape_divider_bottom_height`, `shape_divider_bottom_flip`. Shapes: `waves`, `mountains`, `clouds`, `tilt`, `triangle`, `arrow`. Apply per section bottom edge → smooth transition giữa sections khác bg color.

5 phút setup, big visual impact. Không cần SVG embed thủ công.

### Counter widget swap pattern

Khi clone page và cần thay counter values, KHÔNG str_replace `ending_number` (số không unique). Walk JSON, match by `widgetType === 'counter'` + original `settings.title`. Helper `update_counter_by_title()` trong [`templates/snippets/elementor-data-update.php`](../templates/snippets/elementor-data-update.php).
