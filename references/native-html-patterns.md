# Native HTML Patterns — Zero JS, Browser-Native, A11y by Default

Khi nào dùng native HTML elements thay vì JS framework / plugin widget. Each pattern: **zero JS, browser-handled, a11y automatic, fewer kilobytes**.

> **Khi nào đọc**: cần FAQ accordion, map embed, modal, image gallery, hoặc bất cứ widget UI nào mà plugin tốn 50KB+. Native alternative thường đủ.

## 1. FAQ accordion — `<details>` + `<summary>`

HTML5 native — zero JavaScript, accessibility built-in.

### Markup

```html
<details class="faq-item">
  <summary>Khám phụ khoa định kỳ bao lâu/lần?</summary>
  <p>Phụ nữ trên 21 tuổi nên khám phụ khoa định kỳ <strong>mỗi 6-12 tháng</strong>...</p>
</details>

<details class="faq-item">
  <summary>Chi phí khám phụ khoa khoảng bao nhiêu?</summary>
  <p>Khám phụ khoa cơ bản tại phòng khám từ <strong>300.000-500.000đ</strong>...</p>
</details>
```

### CSS pattern cho rotate icon `+` → `×`

```css
details summary {
    cursor: pointer;
    padding: 16px 48px 16px 16px;
    position: relative;
    list-style: none; /* hide default marker (Firefox) */
}

details summary::-webkit-details-marker {
    display: none; /* hide default arrow (Chrome/Safari) */
}

details summary::after {
    content: '+';
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    transition: transform .2s ease;
    font-size: 1.5em;
    line-height: 1;
}

details[open] summary::after {
    transform: translateY(-50%) rotate(45deg); /* + → × */
}

details[open] summary {
    border-bottom: 1px solid var(--border, #e5e5e5);
}

details > *:not(summary) {
    padding: 16px;
}
```

### A11y bonuses (free)

| Behavior | Native handling |
|---|---|
| Keyboard navigation (Tab focus, Enter/Space toggle) | ✓ |
| Screen reader announce "expanded/collapsed" | ✓ (VoiceOver, NVDA, JAWS) |
| `aria-expanded` state | Auto-managed |
| Focus indicator | Browser default (style via `:focus-visible`) |
| Print friendly (auto-open on print) | ✓ via `@media print { details { open: true } }` |

### When NOT to use

- Multiple items toggle simultaneously với "open one closes others" (single-open accordion) — need JS:
  ```javascript
  document.querySelectorAll('.faq-item').forEach(item => {
      item.addEventListener('toggle', () => {
          if (item.open) {
              document.querySelectorAll('.faq-item').forEach(other => {
                  if (other !== item) other.open = false;
              });
          }
      });
  });
  ```
- Custom animation timing (CSS transitions on `details` content height don't work without JS height calc)

### Comparison

| Approach | Bundle size | A11y | Setup |
|---|---|---|---|
| `<details>` + `<summary>` | 0 KB | ✓ Built-in | 5 min |
| Elementor Toggle widget | ~50 KB (Elementor frontend) | ⚠️ Partial | Click-build |
| jQuery accordion | ~85 KB (jQuery + plugin) | ⚠️ Manual ARIA | Setup overhead |
| Vue/React accordion | ~30 KB+ (framework cost) | ⚠️ Custom impl | Build pipeline |

## 2. Google Maps embed — `<iframe>` no API key

Embed Google Maps location WITHOUT requesting/storing Google Maps API key. Public embed URL = no auth, no quota, no JS.

### Get embed URL

1. Open Google Maps → find location
2. Click **Share** → **Embed a map** → copy iframe HTML
3. Or build manually: `https://www.google.com/maps/embed/v1/place?q=<encoded_address>&key=YOUR_API_KEY`
4. **No API key needed** cho the public "Share → Embed" URL (different endpoint)

### Markup

```html
<div class="map-embed">
  <iframe
    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.7..."
    width="100%"
    height="450"
    style="border:0;"
    allowfullscreen=""
    loading="lazy"
    referrerpolicy="no-referrer-when-downgrade">
  </iframe>
</div>
```

### Performance attributes

- `loading="lazy"` — iframe chỉ load khi user scroll tới (saves ~500KB initial page weight)
- `referrerpolicy="no-referrer-when-downgrade"` — không leak full URL với https→http, but allow https→https
- `allowfullscreen=""` — user click fullscreen → expanded view

### CSS responsive wrapper

```css
.map-embed {
  position: relative;
  width: 100%;
  padding-bottom: 56.25%; /* 16:9 ratio */
  height: 0;
}
.map-embed iframe {
  position: absolute;
  top: 0; left: 0;
  width: 100%;
  height: 100%;
}
```

### Privacy considerations

- Google Maps embed sets cookies (NID, CONSENT, etc.) khi iframe load
- For GDPR-sensitive sites: lazy-load với consent gate:
  ```html
  <div class="map-placeholder" data-map-src="https://www.google.com/maps/embed?...">
    <button class="load-map">Load map (uses Google cookies)</button>
  </div>
  <script>
    document.querySelector('.load-map')?.addEventListener('click', e => {
      const placeholder = e.target.closest('.map-placeholder');
      const iframe = document.createElement('iframe');
      iframe.src = placeholder.dataset.mapSrc;
      iframe.loading = 'lazy';
      iframe.style.cssText = 'width:100%;height:450px;border:0;';
      placeholder.replaceWith(iframe);
    });
  </script>
  ```

### When NOT to use Google Maps

- Need full programmatic control (custom markers, click events, routing) → use [Leaflet](https://leafletjs.com/) + OpenStreetMap (free, no API key) hoặc Mapbox GL JS
- Need offline-first map → host tiles locally
- Privacy-strict EU site without consent banner → use [OpenLayers](https://openlayers.org/) với OSM tiles

## 3. Modal/dialog — `<dialog>` element

HTML5 `<dialog>` element provides modal dialog với keyboard handling + a11y free. Supports: `<button>` form submission, escape-to-close, focus trap.

### Markup

```html
<button type="button" onclick="document.getElementById('contact-dialog').showModal()">
  Đặt lịch khám
</button>

<dialog id="contact-dialog">
  <form method="dialog">
    <h2>Đặt lịch khám</h2>
    <input type="text" name="name" placeholder="Tên" required>
    <input type="tel" name="phone" placeholder="Số điện thoại" required>
    <textarea name="message" placeholder="Triệu chứng..."></textarea>
    <menu>
      <button value="cancel">Hủy</button>
      <button value="submit" type="submit">Gửi</button>
    </menu>
  </form>
</dialog>
```

### Native a11y bonuses

- `Esc` key closes (no JS needed)
- Background scroll lock automatic
- Focus trap inside modal
- `aria-modal="true"` auto-set
- Form submission with `method="dialog"` returns value via `returnValue` property

### Browser support

Modern browsers (Chrome 37+, Firefox 98+, Safari 15.4+). Polyfill: [dialog-polyfill](https://github.com/GoogleChrome/dialog-polyfill) cho legacy IE.

## 4. Image gallery — `<dialog>` + `<picture>` (no lightbox plugin)

Combine `<dialog>` với responsive `<picture>` for lightbox WITHOUT 50KB+ plugin like FancyBox.

```html
<a href="#" onclick="event.preventDefault(); document.getElementById('img-1').showModal()">
  <img src="thumb.jpg" alt="...">
</a>

<dialog id="img-1" class="lightbox">
  <picture>
    <source srcset="full.webp" type="image/webp">
    <img src="full.jpg" alt="Full size image">
  </picture>
  <button onclick="this.closest('dialog').close()" aria-label="Close">×</button>
</dialog>

<style>
  .lightbox { padding: 0; border: none; max-width: 90vw; max-height: 90vh; }
  .lightbox img { width: 100%; height: auto; }
  .lightbox::backdrop { background: rgba(0,0,0,0.8); }
</style>
```

## 5. Form validation — HTML5 native attributes

Skip JS form validation library — HTML5 attributes provide accessible inline error messages.

```html
<form>
  <input
    type="email"
    name="email"
    required
    placeholder="email@example.com"
    aria-describedby="email-error"
  >
  <span id="email-error" class="error-message"></span>

  <input
    type="tel"
    name="phone"
    pattern="0[0-9]{9}"
    title="Số điện thoại 10 chữ số bắt đầu bằng 0"
    required
  >

  <button type="submit">Submit</button>
</form>
```

### Attributes

- `type="email"` — built-in email validation
- `type="tel"` — phone field (mobile keyboard)
- `pattern="regex"` — custom regex validation
- `required` — non-empty enforced
- `min`, `max`, `minlength`, `maxlength` — numeric/string bounds
- `title="message"` — tooltip + error message
- `aria-describedby="error-id"` — link error message để screen reader announce

### Customizing error messages

```javascript
// One-line per input — message khi invalid
input.addEventListener('invalid', e => {
  const target = e.target;
  if (target.validity.valueMissing) target.setCustomValidity('Trường này bắt buộc');
  else if (target.validity.typeMismatch) target.setCustomValidity('Định dạng không đúng');
  else target.setCustomValidity('');
});
input.addEventListener('input', e => e.target.setCustomValidity(''));
```

## When to break the "zero JS" rule

Use plugin/JS framework only when:
- Need state synchronization across multiple components (vd cart, multi-step form)
- Native API insufficient (canvas drawing, WebGL, real-time updates)
- A11y requires custom announcements (aria-live regions for dynamic content)
- Browser support < target (Safari 14 needs polyfill for `<dialog>`)

Otherwise: **native first** — performance + a11y win.

## Liên quan

- [`responsive.md`](responsive.md) — viewport-based image sizing
- [`a11y-debugging.md`](a11y-debugging.md) — accessibility audit when going custom
- [`widget-mapping.md`](widget-mapping.md) — when to use HTML widget vs native Elementor widget
- Insight sources: weekly distillation 2026-05-07 (FAQ accordion + Google Maps embed patterns)
