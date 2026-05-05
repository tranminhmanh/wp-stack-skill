# Responsive Rules

## Breakpoints (Elementor default)

```
Mobile:        < 768px
Tablet:        768 - 1024px
Desktop:       1024 - 1440px
Wide:          > 1440px
```

## Quy tắc layout responsive

### Container direction

- Desktop: `flex_direction: row` cho grid 2-4 cols
- Mobile: `flex_direction_mobile: column` (auto stack)

Hoặc set `flex_wrap: wrap` + width children → tự xuống dòng.

### Width children trong flex row

| Cols desktop | Width child desktop | Width child tablet | Width child mobile |
|---|---|---|---|
| 2 cols | calc(50% - gap) | 50% | 100% |
| 3 cols | 33.33% | 50% | 100% |
| 4 cols | 25% | 50% | 100% |

Set qua `width: {size: 33.33, unit: "%"}` + breakpoint variants.

### Spacing reduce theo breakpoint

Mọi padding/margin/gap phải có 3 giá trị: desktop / tablet / mobile.
Quy tắc: tablet = 70-80% desktop, mobile = 50-66% desktop.

### Typography responsive

Heading luôn set 3 size:
- H1: 56/40/32
- H2: 40/32/28
- H3: 28/24/22
- Body: 18/16/16

### Image responsive

- `width: 100%` desktop nếu nằm trong column
- `max-width: 100%` mobile
- Set `image_size: "large"` để Elementor auto serve responsive srcset
- Hero image: lazy load OFF (LCP element)
- Below-fold image: lazy load ON

### Hide on breakpoint

Chỉ hide khi cực cần thiết (vd: decorative element trên mobile):
```
settings: {
  hide_desktop: false,
  hide_tablet: false,
  hide_mobile: true
}
```

## Test responsive sau build

Bắt buộc check 3 breakpoint:
- 375px (iPhone SE/13 mini)
- 768px (iPad portrait)
- 1280px (laptop)

## Common bugs và fix

| Bug | Fix |
|---|---|
| Text overflow | Giảm font-size mobile |
| Image squish | Set min-height hoặc aspect-ratio |
| Button text wrap | Giảm padding hoặc text ngắn lại |
| Gap quá lớn mobile | Giảm flex_gap_mobile |
| Hero text dán đỉnh | Tăng padding-top mobile |
| Sticky header che content | margin-top section đầu = header height |
| Card height không đều | Set align-items: stretch trên parent |
| Image distort aspect | object-fit: cover + aspect-ratio |

## Image responsive width — 3 fields riêng

Image widget responsive sizing dùng 3 fields độc lập (KHÔNG 1 field với responsive object):

```
width:        {size: 100, unit: "%"}     // desktop
width_tablet: {size: 80, unit: "%"}      // tablet
width_mobile: {size: 100, unit: "%"}     // mobile
```

Tương tự cho `height`, `max_width`. Schema khác với typography (1 field `typography_font_size` + responsive variants).

## Custom CSS scope theo media query (KHÔNG override Elementor responsive default)

Khi style một element có default Elementor hide/show theo breakpoint (vd: hamburger toggle, dropdown menu), LUÔN scope custom CSS theo media query để không phá responsive default:

```css
@media (max-width: 1024px) {
  .menu-toggle { display: inline-flex; padding: 12px; ... }
  .nav-menu--main { display: none !important; }
}

@media (min-width: 1025px) {
  .menu-toggle { display: none !important; }
  .nav-menu--main { display: block !important; }
}
```

Bug pattern: custom rule unscoped → mobile toggle hiển thị cả desktop, hoặc desktop nav hiển thị cả mobile.

## Mobile drawer state-scoped via `[aria-hidden]` attribute

Elementor V4 hide dropdown qua `max-height: 0; opacity: 0` (KHÔNG `display: none`). Custom rule `max-height: calc(100vh - 60px)` override → drawer render visible với 41px height ngay khi `aria-hidden=true`.

**Fix**: scope drawer styles theo `[aria-hidden]` state attribute:

```css
.dropdown[aria-hidden="true"] {
  display: none !important;
  max-height: 0 !important;
  pointer-events: none !important;
}
.dropdown[aria-hidden="false"] {
  display: block !important;
  max-height: calc(100vh - var(--header-h, 60px)) !important;
  pointer-events: auto !important;
  opacity: 1 !important;
}
```

Detection trong DevTools:
```js
const drop = document.querySelector('.elementor-nav-menu--dropdown');
drop.getBoundingClientRect().height  // > 0 even when aria-hidden=true = bug
drop.getAttribute('aria-hidden')     // 'true' | 'false'
```

## iframe-based responsive testing pattern

Khi `resize_window` (Chrome MCP, browser tools) không actually constrain viewport (browser fullscreen Retina), screenshot trả wrong dimensions. Workaround: inject iframe fixed-width:

```js
const iframe = document.createElement('iframe');
iframe.src = 'https://example.com/?cb=v1';
iframe.style.cssText = 'position:fixed;top:50px;right:20px;width:375px;height:600px;border:2px solid red;z-index:99999';
document.body.appendChild(iframe);

// Wait for load, then read iframe.contentDocument
iframe.contentWindow.innerWidth  // = 371 (correct mobile viewport)
```

Ưu điểm: chính xác viewport, click hamburger và verify drawer interaction trong iframe context.
