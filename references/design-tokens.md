# Design Tokens — Universal

Brand-specific values (exact colors, exact fonts) live in the project `CLAUDE.md`. This file only contains the numeric system.

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

Every spacing value is a multiple of 8 (or 4 for fine-tuning).

## Responsive scale reduction

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

Rule: tablet = 70–80% desktop, mobile = 50–66% desktop.

## Container max-widths

- Standard: 1280px (boxed) — default
- Narrow text: 720px (blog post body)
- Full bleed: 100% (hero video, full-width image)

Side padding: 32px desktop / 24px tablet / 16px mobile.

## Section padding-top / padding-bottom

| Type | Desktop | Tablet | Mobile |
|---|---|---|---|
| Hero | 120 | 80 | 64 |
| Standard | 96 | 64 | 48 |
| Compact | 64 | 48 | 32 |
| Tight | 40 | 32 | 24 |

Adjacent sections do NOT stack margins — only padding.

## Grid gaps (`flex_gap`)

| Layout | Desktop | Tablet | Mobile |
|---|---|---|---|
| 2 cols | 40 | 32 | 24 |
| 3 cols | 32 | 24 | 16 |
| 4 cols | 24 | 20 | 16 |
| 6 cols (logo cloud) | 16 | 16 | 16 |

Rule: more columns → smaller gap.

## Card / component padding

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
- New H2 (after content) margin-top: 64px

## Border radius scale

```
none = 0
sm   = 4px      (input, small button)
md   = 8px      (button, regular card)
lg   = 16px     (large card, modal)
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
Tablet:        768 – 1024px
Desktop:       1024 – 1440px
Desktop wide:  > 1440px
```

Matches Elementor defaults — do not override.

## Required rules

1. Do NOT use spacing values outside the scale
2. Do NOT margin against the viewport edge — always go through container padding
3. Adjacent sections do NOT stack margins (use padding only)
4. More grid columns → smaller gap
5. Mobile spacing = 50–66% of desktop spacing
6. Every heading sets 3 sizes for 3 breakpoints
7. Every padding / margin sets 3 values for 3 breakpoints

## Global section centering pattern (Elementor kit `custom_css`)

Apply once in the kit → every future section auto-centers without wrapping in an inner container:

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

## Header / footer location override (full-bleed exception)

Header and footer locations need to ESCAPE the global centering rule above. The override must reset all 3 properties (see [pitfalls "CSS cascade"](pitfalls.md)):

```css
/* Header — flex layout, not boxed */
.elementor-location-header .e-con-full > .elementor-widget,
.elementor-location-header .e-con-full > .e-con {
  max-width: none;
  width: auto;
  margin-inline: 0;
}

/* Footer — outer column full-bleed for dark bg, inner grid already boxed */
.elementor-location-footer > .e-con-full {
  max-width: none;
  width: 100%;
  margin-inline: 0;
}
```

`!important` is usually needed when fighting Elementor's `--container-max-width` CSS variable.

## B2B header sizing tokens

Professional pattern (Stripe / Linear / Vercel / Notion / Figma):

| Property | Value |
|---|---|
| Header height | 64–72px (sticky shrunk) / 88–96px (top hero) |
| Logo height | 32–40px |
| Padding Y | 16px |
| Padding X | 32 desktop / 24 tablet / 16 mobile (per design-tokens) |
| Background | white / light tint |
| Border-bottom | `1px solid rgba(navy, 0.08)` (subtle, more professional than shadow) |
| Topbar (optional) | hotline + email + chat link, 32–36px, font-size 13–14px, hide on mobile |

**Logo SVG aspect-ratio formula**: `target_height × aspect_ratio = width`. SVG 360×80 (4.5:1) at height 40px → set `width: 180px`. Image widget `height: auto` follows aspect.

## Card design tokens (B2B)

```
white bg
border-radius: 12px
padding: 28-32px (24px mobile)
box-shadow: 0 1px 3px rgba(navy, 0.08)
border: 1px solid rgba(navy, 0.06)

hover:
  transform: translateY(-4px)
  box-shadow: 0 16px 40px rgba(navy, 0.12)
  border-color: rgba(teal, 0.3)
  transition: 0.2s ease
```

Apply consistently across all card variants (service, testimonial, feature, route).
