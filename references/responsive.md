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
