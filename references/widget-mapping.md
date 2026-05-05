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
