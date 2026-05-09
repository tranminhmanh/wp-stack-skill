# HTML → Elementor Widget Mapping

For converting HTML from Claude Design / Figma / hand-coded markup into Elementor.

## Mapping table

| HTML element | Elementor widget | Notes |
|---|---|---|
| `<section>` | Container (root level) | Set section padding |
| `<div class="grid">` | Container flex row wrap | Set `flex_gap` |
| `<div class="card">` | Container nested | Set background, border-radius |
| `<h1>...<h6>` | Heading widget | Map `header_size` |
| `<p>` | Text Editor widget | For long text with formatting |
| `<span>` short text | Heading H6 or Text Editor | Depends on context |
| `<button>` | Button widget | Do NOT use HTML |
| `<a class="btn">` | Button widget | Set `link.url` |
| `<img>` | Image widget | Upload via `sideload-image` first |
| `<svg>` icon | Icon widget | Use `eicon-*` or Font Awesome |
| `<form>` | Form widget (Pro) | Map fields array |
| `<ul>` / `<ol>` list | Icon List widget | Each `<li>` is one item |
| `<blockquote>` | Blockquote widget (Pro) | Or Testimonial |
| `<video>` self-hosted | Video widget | Source: self-hosted |
| YouTube / Vimeo embed | Video widget | Source: youtube / vimeo |
| `<iframe>` Calendly | HTML widget | Exception — third-party embed |
| Tabs UI | Nested Tabs widget (Pro) | Do NOT build by hand |
| Accordion | Nested Accordion widget (Pro) | |
| Carousel / slider | Media Carousel or Slides (Pro) | |
| Repeating posts | Loop Grid widget (Pro) | Requires CPT setup first |
| Counter / stats | Counter widget | Animate on scroll |
| Pricing table | Price Table widget (Pro) | |
| CTA banner | Call to Action widget (Pro) | Or Container + Heading + Button |
| Testimonial | Testimonial widget or Reviews (Pro) | |
| Social icons | Social Icons widget | |
| Progress bar | Progress widget | |
| Countdown | Countdown widget (Pro) | |
| Maps embed | Google Maps widget | |

## NEVER use the HTML widget for

- Text / heading / button → marketing team loses the ability to edit
- Tabs / accordion / carousel → Pro widgets exist
- Inline `style` attributes → break responsive
- Simple forms → use Form widget Pro

## When the HTML widget is OK

- Calendly / Typeform booking embeds
- Custom Google Maps with marker animation
- A/B testing snippets
- Custom interactive component without an equivalent widget
- Third-party scripts running on the frontend (chat widgets, schema.org JSON)

## Order of operations when converting

1. Identify section boundaries (`<section>` or large divs)
2. Identify the layout type of each section (grid? row? center?)
3. Map each leaf element to a widget
4. Build top-down: section → row container → card container → leaf widgets
5. Verify with `get-page-structure` after each section
6. Apply responsive settings last

## Link storage location per widget

When you need to bulk-rewrite links (e.g. hash anchors `#x` → root-relative `/#x` when copying a section to another page), each widget stores URLs in a different place:

| Widget | Link path inside `settings` | Recipe for updating |
|---|---|---|
| Button | `settings.link.url` | Direct field update |
| Icon List | `settings.icon_list[].link.url` | Walk the `icon_list` array |
| Text Editor | `settings.editor` (HTML string) | Regex `href="#x"` → `href="/#x"` |
| Heading (with link) | `settings.title` (HTML string if it contains inline `<a>`) | Same regex |
| HTML widget | `settings.html` (HTML string) | Same regex |
| Posts Grid / Loop Grid | dynamic — link is `permalink` from post object | Do NOT hard-code |
| Image (linked) | `settings.link.url` | Direct |
| Nav Menu | uses WP menu items (`wp-admin → Menus`) — NOT in `_elementor_data` | Edit the menu instead of the page |

Helper `absolutize_hash_links()` in [`templates/snippets/elementor-data-update.php`](../templates/snippets/elementor-data-update.php) handles the four main widgets (button, icon-list, text-editor, html).

## HTML widget exception for data tables / complex content

The "100% native widget" rule has exceptions for:
- **Data tables** with header rows, hover states, pill labels — Elementor has no proper Table widget. HTML widget + a CSS class in the kit `custom_css` is the cleanest solution.
- **Schema JSON-LD** (`<script type="application/ld+json">`)
- **Custom interactive markup** (pricing comparison tables, feature matrices, multi-state cards with CSS animations)

Pattern:
```html
<!-- HTML widget content -->
<table class="x-transit-table">
  <thead><tr><th>Port</th><th>Transit</th><th>Frequency</th></tr></thead>
  <tbody>
    <tr><td>HCM-Busan</td><td>5–7 days</td>
        <td><span class="x-tt-pill x-tt-pill--hot">2/week</span></td></tr>
  </tbody>
</table>
```

CSS in the kit `custom_css`:
```css
.x-transit-table { width: 100%; border-collapse: collapse; }
.x-transit-table th { background: #0A2540; color: white; ... }
.x-tt-pill--hot { background: #E87722; color: white; ... }
```

Min-width 640px + scroll-x wrapper for mobile.

## Refactor strategy: preserve class names

When converting a raw HTML widget (`<div class="commit-card">...</div>`) to native widgets (heading + text-editor + ...), the biggest risk is **losing styling** because mu-plugin / kit CSS targets specific classes (`.commit-card`, `.pain-card`, `.price-card`, `.icon-circle`, ...). Rebuilding with widget defaults means rewriting all the CSS.

### Pattern preservation: KEEP class names, map to Elementor element type

| Legacy HTML class | Elementor element to attach to | Setting field |
|---|---|---|
| `<section class="commit-grid">` (outer container) | Inner section / container | `settings.css_classes` |
| `<div class="commit-card">` (column) | Column / nested container | `settings.css_classes` |
| `<span class="icon-circle">` (widget wrapper) | Widget wrapper | `_css_classes` (widget-level) |
| `<span>78<small>k</small></span>` (special markup) | Text-editor widget with raw HTML | `settings.editor` + `_css_classes` |

### Order of operations when refactoring

1. **Identify the CSS class hierarchy** in the legacy HTML (read the mu-plugin CSS to see which class styles what)
2. **Map class → Elementor element type** per the table above
3. **Build top-down**: outer container with class → column with class → widget with `_css_classes`
4. **Do NOT rename classes** — that forces a full CSS rewrite

### Special case: complex inline markup

`<span>78<small>k</small></span>` cannot go through the heading widget (HTML gets escaped). Use:
- **Text-editor widget** with raw HTML
- `_css_classes` for the wrapper class (e.g. `'price'`)
- Existing CSS `.price small { font-size: 0.5em; }` keeps working

Trade-off: text-editor allows raw HTML, heading widget escapes it — pick the widget based on whether you need inline markup.
