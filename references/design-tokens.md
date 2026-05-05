# Design Tokens — Universal

Brand-specific (màu, font cụ thể) đọc từ CLAUDE.md project. File này chỉ chứa hệ thống số.

## Spacing scale (8-point grid)

```
xs   = 8px
sm   = 16px
md   = 24px
lg   = 40px
xl   = 64px
2xl  = 96px
3xl  = 120px
4xl  = 160px
```

Mọi spacing là bội số 8 (hoặc 4 cho fine-tuning).

## Responsive scale reduce

| Token | Desktop | Tablet | Mobile |
|---|---|---|---|
| xs | 8 | 8 | 4 |
| sm | 16 | 12 | 8 |
| md | 24 | 20 | 16 |
| lg | 40 | 32 | 24 |
| xl | 64 | 48 | 32 |
| 2xl | 96 | 72 | 48 |
| 3xl | 120 | 96 | 64 |
| 4xl | 160 | 120 | 80 |

Quy tắc: tablet = 70-80% desktop, mobile = 50-66% desktop.

## Container max-widths

- Standard: 1280px (boxed) — mặc định
- Narrow text: 720px (blog post body)
- Full bleed: 100% (hero video, full-width image)

Side padding: 32px desktop / 24px tablet / 16px mobile.

## Section padding-top/bottom

| Loại | Desktop | Tablet | Mobile |
|---|---|---|---|
| Hero | 120 | 80 | 64 |
| Standard | 96 | 64 | 48 |
| Compact | 64 | 48 | 32 |
| Tight | 40 | 32 | 24 |

Section liền kề KHÔNG cộng dồn margin — chỉ dùng padding.

## Grid gaps (flex_gap)

| Layout | Desktop | Tablet | Mobile |
|---|---|---|---|
| 2 cols | 40 | 32 | 24 |
| 3 cols | 32 | 24 | 16 |
| 4 cols | 24 | 20 | 16 |
| 6 cols (logo cloud) | 16 | 16 | 16 |

Quy tắc: càng nhiều cột → gap càng nhỏ.

## Card / Component padding

| Component | Desktop | Mobile |
|---|---|---|
| Service card | 32 | 24 |
| Testimonial card | 40 | 24 |
| Pricing card | 48 | 32 |
| Feature box | 24 | 16 |
| Form container | 40 | 24 |

## Typography scale

| Element | Desktop | Tablet | Mobile | Weight |
|---|---|---|---|---|
| H1 | 56 | 40 | 32 | 700 |
| H2 | 40 | 32 | 28 | 700 |
| H3 | 28 | 24 | 22 | 600 |
| H4 | 22 | 20 | 18 | 600 |
| Body | 18 | 16 | 16 | 400 |
| Caption | 14 | 14 | 13 | 400 |

Line-height: heading 1.2, body 1.6.

## Typography rhythm (margins)

- H1 margin-bottom: 24px
- H2 margin-bottom: 20px
- H3 margin-bottom: 16px
- H4 margin-bottom: 12px
- Paragraph margin-bottom: 16px
- H2 mới (sau content) margin-top: 64px

## Border radius scale

```
none = 0
sm   = 4px      (input, small button)
md   = 8px      (button, card thường)
lg   = 16px     (card lớn, modal)
xl   = 24px     (hero card, feature card)
full = 9999px   (pill, avatar)
```

## Shadow scale

```
sm  = 0 1px 2px rgba(0,0,0,0.05)
md  = 0 4px 6px rgba(0,0,0,0.1)
lg  = 0 10px 15px rgba(0,0,0,0.1)
xl  = 0 20px 25px rgba(0,0,0,0.15)
2xl = 0 25px 50px rgba(0,0,0,0.25)
```

## Breakpoints

```
Mobile:        < 768px
Tablet:        768 - 1024px
Desktop:       1024 - 1440px
Desktop wide:  > 1440px
```

Trùng Elementor default — không override.

## Quy tắc bắt buộc

1. KHÔNG dùng spacing không nằm trong scale
2. KHÔNG margin sát mép viewport — luôn qua container padding
3. Section liền kề KHÔNG cộng dồn margin (chỉ dùng padding)
4. Grid càng nhiều cột → gap càng nhỏ
5. Mobile spacing = 50-66% desktop spacing
6. Mọi heading set 3 size cho 3 breakpoint
7. Mọi padding/margin set 3 giá trị cho 3 breakpoint

## Global section centering pattern (Elementor kit `custom_css`)

Apply 1 lần trong kit → tất cả sections future tự center mà không cần wrap inner container:

```css
.e-con-full > .elementor-widget,
.e-con-full > .e-con-boxed,
.e-con-full > .e-con.e-flex,
.e-con-full > .e-con.e-grid {
  max-width: 1280px;
  width: 100%;
  margin-inline: auto;
}
```

## Header / Footer location override (full-bleed exception)

Header/footer location cần ESCAPE global centering rule trên. Override phải reset ĐỦ 3 properties (xem [pitfalls.md "CSS cascade"](pitfalls.md)):

```css
/* Header — flex layout, không boxed */
.elementor-location-header .e-con-full > .elementor-widget,
.elementor-location-header .e-con-full > .e-con {
  max-width: none;
  width: auto;
  margin-inline: 0;
}

/* Footer — outer column full-bleed cho dark bg, inner grid đã boxed sẵn */
.elementor-location-footer > .e-con-full {
  max-width: none;
  width: 100%;
  margin-inline: 0;
}
```

`!important` thường cần khi đối đầu Elementor's `--container-max-width` CSS variable.

## B2B header sizing tokens

Pattern professional (Stripe / Linear / Vercel / Notion / Figma):

| Property | Value |
|---|---|
| Header height | 64–72px (sticky shrunk) / 88–96px (top hero) |
| Logo height | 32–40px |
| Padding Y | 16px |
| Padding X | 32 desktop / 24 tablet / 16 mobile (theo design-tokens) |
| Background | white / light navy tint |
| Border-bottom | `1px solid rgba(navy, 0.08)` (subtle, chuyên nghiệp hơn shadow) |
| Topbar (optional) | hotline + email + Zalo, 32–36px, `font-size: 13–14px`, hide mobile |

**Logo SVG aspect ratio formula**: `target_height × aspect_ratio = width`. SVG 360×80 (= 4.5:1) + height 40px → set `width: 180px`. Image widget `height: auto` theo aspect.

## Card design tokens (B2B)

```
white bg
border-radius: 12px
padding: 28-32px (24px mobile)
box-shadow: 0 1px 3px rgba(navy,0.08)
border: 1px solid rgba(navy,0.06)

hover:
  transform: translateY(-4px)
  box-shadow: 0 16px 40px rgba(navy,0.12)
  border-color: rgba(teal,0.3)
  transition: 0.2s ease
```

Apply consistent cho tất cả card variants (service, testimonial, feature, route).
