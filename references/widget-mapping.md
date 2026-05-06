# HTML → Elementor Widget Mapping

Khi convert HTML từ Claude Design / Figma / hand-coded sang Elementor.

## Mapping table

| HTML element | Elementor widget | Notes |
|---|---|---|
| `<section>` | Container (root level) | Set padding section |
| `<div class="grid">` | Container flex row wrap | Set flex_gap |
| `<div class="card">` | Container nested | Set background, border-radius |
| `<h1>...<h6>` | Heading widget | Map header_size |
| `<p>` | Text Editor widget | Cho text dài có format |
| `<span>` text ngắn | Heading H6 hoặc Text Editor | Tùy context |
| `<button>` | Button widget | KHÔNG dùng HTML |
| `<a class="btn">` | Button widget | Set link.url |
| `<img>` | Image widget | Upload qua sideload-image trước |
| `<svg>` icon | Icon widget | Dùng eicon-* hoặc Font Awesome |
| `<form>` | Form widget (Pro) | Map fields array |
| `<ul>/<ol>` list | Icon List widget | Mỗi <li> là 1 item |
| `<blockquote>` | Blockquote widget (Pro) | hoặc Testimonial |
| `<video>` self-host | Video widget | Source: self-hosted |
| YouTube/Vimeo embed | Video widget | Source: youtube/vimeo |
| `<iframe>` Calendly | HTML widget | Ngoại lệ — embed bên thứ 3 |
| Tabs UI | Nested Tabs widget (Pro) | KHÔNG build tay |
| Accordion | Nested Accordion widget (Pro) | |
| Carousel/slider | Media Carousel hoặc Slides (Pro) | |
| Repeating posts | Loop Grid widget (Pro) | Cần CPT setup trước |
| Counter/stats | Counter widget | Animate on scroll |
| Pricing table | Price Table widget (Pro) | |
| CTA banner | Call to Action widget (Pro) | hoặc Container + Heading + Button |
| Testimonial | Testimonial widget hoặc Reviews (Pro) | |
| Social icons | Social Icons widget | |
| Progress bar | Progress widget | |
| Countdown | Countdown widget (Pro) | |
| Maps embed | Google Maps widget | |

## KHÔNG BAO GIỜ dùng HTML widget cho

- Text/heading/button → mất khả năng team marketing edit
- Tabs/accordion/carousel → có widget Pro rồi
- Inline style trong HTML → break responsive
- Form đơn giản → dùng Form widget Pro

## Khi nào HTML widget OK

- Embed Calendly, Typeform booking
- Google Maps custom với marker animation
- A/B testing snippet
- Custom interactive component không có widget tương đương
- Third-party script chạy frontend (chat widget, schema-org JSON)

## Order of operations khi convert

1. Identify section boundaries (`<section>` hoặc div lớn)
2. Identify layout type của mỗi section (grid? row? center?)
3. Map từng leaf element sang widget
4. Build top-down: section → row container → card container → leaf widgets
5. Verify get-page-structure sau mỗi section
6. Apply responsive settings cuối cùng

## Link storage location per widget

Khi cần bulk-rewrite links (vd: hash anchor `#x` → root-relative `/#x` khi copy section sang page khác), mỗi widget lưu URL ở chỗ khác:

| Widget | Link path trong settings | Recipe khi update |
|---|---|---|
| Button | `settings.link.url` | direct field update |
| Icon List | `settings.icon_list[].link.url` | walk `icon_list` array |
| Text Editor | `settings.editor` (HTML string) | regex `href="#x"` → `href="/#x"` |
| Heading (with link) | `settings.title` (HTML string nếu có inline `<a>`) | regex same |
| HTML widget | `settings.html` (HTML string) | regex same |
| Posts Grid / Loop Grid | dynamic — link là `permalink` từ post object | KHÔNG hard-code |
| Image (linked) | `settings.link.url` | direct |
| Nav Menu | dùng WP menu items (`wp-admin → Menus`) — KHÔNG trong `_elementor_data` | edit menu thay vì page |

Helper `absolutize_hash_links()` trong [`templates/snippets/elementor-data-update.php`](../templates/snippets/elementor-data-update.php) handle 4 widget chính (button, icon-list, text-editor, html).

## HTML widget exception cho data tables / complex content

Quy tắc "100% native widget" có ngoại lệ cho:
- **Data tables** với header rows, hover states, pill labels — Elementor không có Table widget native chuẩn. HTML widget với CSS class trong kit `custom_css` là giải pháp gọn nhất.
- **Schema JSON-LD** (`<script type="application/ld+json">`)
- **Custom interactive markup** (pricing comparison tables, feature matrices, multi-state cards với CSS animations)

Pattern:
```html
<!-- HTML widget content -->
<table class="sa-transit-table">
  <thead><tr><th>Cảng</th><th>Transit</th><th>Tần suất</th></tr></thead>
  <tbody>
    <tr><td>HCM-Busan</td><td>5–7 ngày</td>
        <td><span class="sa-tt-pill sa-tt-pill--hot">2/tuần</span></td></tr>
  </tbody>
</table>
```

CSS trong kit `custom_css`:
```css
.sa-transit-table { width: 100%; border-collapse: collapse; }
.sa-transit-table th { background: #0A2540; color: white; ... }
.sa-tt-pill--hot { background: #E87722; color: white; ... }
```

Min-width 640px + scroll-x wrapper cho mobile.

## Refactor strategy: preserve class names

Khi convert raw HTML widget (`<div class="commit-card">...</div>`) sang native widgets (heading + text-editor + ...), risk lớn nhất là **mất styling** vì mu-plugin/kit CSS targets class cụ thể (`.commit-card`, `.pain-card`, `.price-card`, `.icon-circle`, ...). Rebuild với widget defaults = phải viết lại toàn bộ CSS.

### Pattern preservation: KEEP class names, map to Elementor element type

| Legacy HTML class | Elementor element to attach | Setting field |
|---|---|---|
| `<section class="commit-grid">` (outer container) | Inner section / container | `settings.css_classes` |
| `<div class="commit-card">` (column) | Column / nested container | `settings.css_classes` |
| `<span class="icon-circle">` (widget wrapper) | Widget wrapper | `_css_classes` (widget-level) |
| `<span>78<small>k</small></span>` (special markup) | Text-editor widget với raw HTML | `settings.editor` + `_css_classes` |

### Order of operations khi refactor

1. **Identify CSS class hierarchy** trong legacy HTML (đọc mu-plugin CSS xem class nào style cái gì)
2. **Map class → Elementor element type** theo bảng trên
3. **Build top-down**: outer container với class → column với class → widget với `_css_classes`
4. **DO NOT rename classes** — sẽ phải viết lại toàn bộ CSS

### Special case: complex inline markup

`<span>78<small>k</small></span>` không thể qua heading widget (escape HTML). Dùng:
- **text-editor widget** với raw HTML
- `_css_classes` cho wrapper class (vd `'price'`)
- Existing CSS `.price small { font-size: 0.5em; }` continues working

Tradeoff: text-editor cho phép raw HTML, heading widget escape — pick widget theo nhu cầu inline markup.
