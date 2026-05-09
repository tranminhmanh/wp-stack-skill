# Standard Stack

The tested combination, deployed in production. Do not change on a whim — any proposal to add plugins outside this stack must have a clear reason and user approval.

## Core (every site)

| Component | Tool | Version | Note |
|---|---|---|---|
| WordPress core | WordPress | 6.8+ | Auto-update minor versions |
| Theme | Astra | Latest free | NOT Pro |
| Page builder | Elementor | 3.20+ | Flexbox Containers ON |
| Page builder Pro | Elementor Pro | 3.20+ | License $59–99/year |
| MCP server | msrbuilds/elementor-mcp | v1.4+ | GitHub release |
| MCP adapter | WordPress MCP Adapter | Latest | Required for MCP |
| Custom fields | ACF Free | Latest | Simple |
| Custom fields advanced | JetEngine | Latest | When relationships / dynamic content needed |

## Marketing / SEO

| Tool | When to use |
|---|---|
| Rank Math Free | Every site (NOT Yoast) |
| Schema Pro | Sites needing rich snippets (review, FAQ) |
| Redirection | 301 management |
| Site Kit by Google | GA4 + Search Console integration |

## Performance

| Tool | Tier |
|---|---|
| WP Rocket | Premium ($59/year) — important sites |
| LiteSpeed Cache | Free — if hosting runs LiteSpeed |
| ShortPixel | Image optimization |
| Cloudflare Free | CDN + DDoS protection |
| Asset CleanUp | Disable unused scripts per-page |

## Security

| Tool | Purpose |
|---|---|
| Wordfence Free | Firewall + malware scan |
| WPS Hide Login | Change `/wp-admin` URL |
| Two Factor | 2FA for admin |
| Limit Login Attempts | Brute-force protection |

## Backup

| Tool | Frequency |
|---|---|
| UpdraftPlus | Daily DB + weekly files |
| Provider snapshot | Before every major edit (CloudPanel / Hostinger / SiteGround all have this) |
| WP Migrate Pro | When migrating staging → prod |

## Email

| Tool | Note |
|---|---|
| WP Mail SMTP | Required, do not use default `wp_mail()` |
| SendGrid / Brevo / Mailgun | Provider |

## Form & Lead

| Tool | When to use |
|---|---|
| Elementor Form Pro | Simple forms (already in Pro) |
| Fluent Forms | Complex, multi-step, conditional logic |
| WP Webhooks | Bridge form → CRM / n8n |

## Multilingual

| Tool | When to use |
|---|---|
| Polylang Free | Small site, 2 languages |
| Meep AI Translator | Elementor-heavy site (reads Elementor JSON without breaking layout) |
| WPML | Avoid — heavy on Elementor |

## Page Speed budget

- Lighthouse mobile: ≥85
- Lighthouse desktop: ≥95
- LCP: <2.5s
- CLS: <0.1
- INP: <200ms
- Total page weight: <2MB (target <1MB)

## Plugins NOT to install

- Jetpack (overkill, slow)
- Elementor addon packs (Essential Addons, Premium Addons, Crocoblock JetElements) — bloat. Use native widgets + ACF / JetEngine.
- WPBakery, Divi, Bricks, Beaver Builder — not in the stack
- Themes other than Astra — Astra Free only
- Astra Pro — overlaps with Elementor Pro (see "Astra Pro vs Elementor Pro feature overlap" below)

## Astra Pro vs Elementor Pro feature overlap

When a site already has Astra Pro (legacy license, paid before) AND Elementor Pro, many features duplicate. Pick one tool per feature to avoid double-firing markup, conflicting CSS, and wasted licenses.

| Feature | Astra Pro | Elementor Pro Theme Builder | Recommended |
|---|---|---|---|
| Header builder | ✅ | ✅ | **Elementor** — flex layout + responsive control is stronger |
| Footer builder | ✅ | ✅ | **Elementor** — Theme Builder template more powerful |
| Mega menu | ✅ | ✅ | **Elementor** — better mobile responsive |
| Sticky / transparent header | ✅ | ✅ | Either — pick the one matching your header builder choice |
| Custom layouts (hooks) | ✅ Hooks API | ✅ Theme Builder locations | **Elementor** — more flexible, easier display conditions |
| Schema markup (LocalBusiness) | ✅ | ❌ (use Rank Math) | **Disable Astra schema, use Rank Math** — see `pitfalls.md` "Schema duplicate" |
| Mobile breakpoint control | ✅ | ✅ | Either |
| Page-level header/footer disable | ✅ | ✅ | Whichever builder you picked |
| Performance impact | Lighter | Heavier | Astra for non-builder pages, Elementor where you need the builder |

**Recommendation when both are licensed**: keep Astra Pro for theme system fonts / colors / global typography integration. Use Elementor Pro Theme Builder for everything visible (header / footer / loops / single post / archive / 404). Disable Astra schema (Rank Math handles it). Disable Astra header / footer when an Elementor Theme Builder template covers the location (Astra free 4.13.x does NOT auto-suppress — see [`workflows/new-site-setup.md`](../workflows/new-site-setup.md) "Decision tree: header / footer rendering" + Astra-Elementor bridge mu-plugin).

**Recommendation for new sites**: Astra Free + Elementor Pro is enough. The Pro features in Astra duplicate Elementor Pro, so Astra Pro is rarely worth $59+/year on top of Elementor Pro.

## CSS architecture: mu-plugin master CSS preferred over Code Snippets

The stack recommends using **a single mu-plugin master CSS file** instead of multiple Code Snippets for production CSS rules:

| Aspect | Code Snippets plugin | mu-plugin master CSS |
|---|---|---|
| Cascade priority | Default `wp_head` priority | `wp_head` priority 100 → loads later, wins cascade |
| Version control | DB option (snippet content) | File in repo, cPanel upload, git tracked |
| Specificity fight | Multiple snippets compete | One file = one cascade order |
| Crash isolation | Snippet fatal = site 500 (see [pitfalls "Code Snippets safety"](pitfalls.md)) | mu-plugin fatal also recoverable (rename file) |
| Recovery | Have to enter DB to disable | SSH / Fileman rename `.php` → `.php.off` |

**When Code Snippets is still fine**:
- Small JS hooks (analytics events, scroll trackers)
- Occasional WP filters / actions (e.g. tweak excerpt length)
- Admin-only single-use logic (`scope=admin`, `active=-1`)

**When mu-plugin is REQUIRED**:
- Production CSS overrides
- Elementor API calls (`files_manager`, `frontend->get_settings`, …)
- Code that runs at `priority 1` or before Elementor init
- Anything that, if it breaks, would force you to use [`templates/snippets/wp-fix.php`](../templates/snippets/wp-fix.php) to recover

## Compatibility: WAE plugin (`wordpress-wae`) needs mu-plugin show-in-rest fix

If a site uses the `wordpress-wae` plugin (89 abilities for posts / pages / products / media), abilities default to `meta['show_in_rest'] = false` → the REST controller `WP_REST_Abilities_V1_List_Controller::get_items()` filters by this meta → only 2 core abilities are visible via the API.

**Fix**: deploy `wp-content/mu-plugins/abilities-show-in-rest.php`:

```php
<?php
/**
 * WAE compatibility — flip show_in_rest=true for mcp-wp/* and core/* abilities.
 * Runs at hook wp_abilities_api_init priority 999 (after WAE registers).
 */
add_action('wp_abilities_api_init', function () {
    if (!class_exists('\\WP\\Abilities\\Abilities_Registry')) return;
    $registry = \WP\Abilities\Abilities_Registry::get_instance();
    $reflection = new ReflectionClass($registry);
    $abilities_prop = $reflection->getProperty('abilities');
    $abilities_prop->setAccessible(true);
    $abilities = $abilities_prop->getValue($registry);

    foreach ($abilities as $name => $ability) {
        if (str_starts_with($name, 'mcp-wp/') || str_starts_with($name, 'core/')) {
            $meta_prop = (new ReflectionClass($ability))->getProperty('meta');
            $meta_prop->setAccessible(true);
            $meta = $meta_prop->getValue($ability) ?: [];
            $meta['show_in_rest'] = true;
            $meta_prop->setValue($ability, $meta);
        }
    }
}, 999);
```

Verify: `curl /wp-json/wp/v2/abilities | jq '.abilities | length'` — expect 80+ instead of 2.
