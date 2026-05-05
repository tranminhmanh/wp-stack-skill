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
