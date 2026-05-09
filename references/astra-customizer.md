# Astra Customizer — Common Settings

Astra Free has a deep Customizer. Brand-specific values (exact fonts, exact colors) live in the project `CLAUDE.md`. This file only points to the paths you'll use most.

## Global

`Customize → Global → Typography`
- Body font: from `CLAUDE.md`, weight 400, size 16/16/16
- Heading font: from `CLAUDE.md`, weight 700
- Line height body: 1.6
- Line height heading: 1.2

`Customize → Global → Colors`
- Theme color (link): primary brand color
- Link hover: darker shade
- Heading color: text-primary
- Body text: text-primary
- Background: background

`Customize → Global → Container`
- Container width: 1280px
- Container layout: Boxed (default)

`Customize → Global → Buttons`
- Button radius: 8px
- Button padding: 16/32px
- Button typography: brand font, weight 600

## Header

`Customize → Header Builder`
- Layout: logo left, menu right, CTA button on the right
- Sticky on scroll: ON
- Transparent on hero: per project
- Mobile breakpoint: 1024px (Astra default 921 is a bit early)

## Footer

`Customize → Footer Builder`
- Layout: 4 columns desktop, 2 columns tablet, 1 column mobile
- Background: dark mode per brand

## Performance

`Customize → Performance`
- Load Google Fonts Locally: ON (cuts one external request)
- Preload Local Fonts: ON for the primary font
- Disable Block Editor styles: ON if not using Gutenberg

## Layout

`Customize → Layout → Sidebar`
- Default Layout: No Sidebar (for any marketing site)
- Sidebar Style: Unboxed (if you do need a blog sidebar)

`Customize → Layout → Blog`
- Blog Layout: Grid or Classic
- Posts per page: 9 (divisible by 3 columns)
- Excerpt length: 25 words

## Astra MCP (since v4.13)

If you connect Astra MCP, Claude can drive everything above through natural language. Two endpoints:
- `/wp-json/astra/v1/mcp` — Astra theme only
- WordPress.com global MCP — entire site

Setup: Plugins → Astra → MCP tab → Generate config.

⚠️ Astra MCP **cannot build landing-page sections** — it only adjusts global theme settings. Page content still uses msrbuilds/elementor-mcp.

## Common Astra pitfalls

### Mobile breakpoint too early (921px)

By default Astra treats <922px as tablet → `Customize → Layout → Container → Mobile breakpoint: 768`.

### Local font cache missing Vietnamese subset

After enabling Load Google Fonts Locally, the Vietnamese subset can be missing.
Fix: Astra → Performance → Flush local font cache → reload → check font-family.

### Transparent header + Elementor hero conflict

Transparent-on-hero does not auto-detect the hero section. Set per page: Page Settings → Header Style → Transparent.

### Astra schema vs Rank Math schema duplicate

Disable Astra schema, keep Rank Math.

## Astra Free + Elementor Theme Builder bridge

**Astra Pro** ships with Elementor Theme Builder integration (auto-suppresses the Astra header / footer when a Theme Builder template has matching conditions). **Astra Free 4.13.x does NOT auto-suppress** → Theme Builder header is active but Astra's header still renders above it = double header.

**Fix**: mu-plugin bridge (`wp-content/mu-plugins/astra-elementor-bridge.php`):

```php
<?php
/**
 * Astra Free → Elementor Theme Builder bridge.
 * Suppresses Astra header / footer when a Theme Builder template is active.
 */

// 1. Suppress Astra main header
add_filter('astra_main_header_display', '__return_false');

// 2. Inject Elementor header location at body open
add_action('wp_body_open', function () {
    if (function_exists('elementor_theme_do_location')) {
        elementor_theme_do_location('header');
    }
}, 1);

// 3. Replace Astra footer with Elementor footer location
add_action('init', function () {
    remove_all_actions('astra_footer');
    add_action('astra_footer', function () {
        if (function_exists('elementor_theme_do_location')) {
            elementor_theme_do_location('footer');
        }
    });
});
```

Conditional version (only suppress when a Theme Builder template is actually active):
```php
add_filter('astra_main_header_display', function ($display) {
    if (function_exists('elementor_theme_do_location')
        && \ElementorPro\Modules\ThemeBuilder\Module::instance()
            ->get_locations_manager()
            ->is_location_filled('header')) {
        return false;
    }
    return $display;
});
```

⚠️ Before pushing the mu-plugin, grep the Elementor Pro source for the specific version to verify the method names exist — see [`security.md` "mu-plugin API check"](security.md).

## Astra Pro vs Elementor Pro — feature overlap matrix

The standard stack (see [`stack.md`](stack.md)) is **Astra Free + Elementor Pro**, NOT Astra Pro (overlapping features = redundant). But if you already have an Astra Pro license (sunk cost), keep Astra Pro for non-builder pages.

| Feature | Astra Pro | Elementor Pro | Recommendation |
|---|---|---|---|
| Header builder | ✅ | ✅ Theme Builder | Pick **Elementor** (more powerful, better responsive) |
| Footer builder | ✅ | ✅ Theme Builder | Pick **Elementor** |
| Mega menu | ✅ | ✅ Mega Menu Pro | Pick **Elementor** (better responsive) |
| Custom layouts (hooks) | ✅ Astra Hooks | ✅ Theme Builder | Pick **Elementor** |
| Schema markup | ✅ Local Business | ❌ (use Rank Math) | **Disable Astra schema**, use Rank Math (avoid duplicate) |
| Mobile breakpoint | ✅ Customizer | ✅ Editor settings | Set in Astra Customizer (global) |
| Site Identity | ✅ | ❌ | Astra (theme-level) |
| Performance | Lighter | Heavier | **Astra** for non-builder pages (blog single, archive) |

**Decision flow**:
- New site (no license commitment): **Astra Free + Elementor Pro** (standard stack)
- Existing Astra Pro license: keep it → **disable Astra schema** + **use Elementor Theme Builder** for header / footer / mega menu / custom layouts. Astra Pro continues to handle non-builder default templates.

**Anti-pattern**: building the same header in BOTH Astra Header Builder AND Elementor Theme Builder → conflict, browser loads both → double header.
