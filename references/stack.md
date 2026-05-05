# Stack chuẩn

Combo đã test, deploy production. KHÔNG thay đổi tùy hứng — đề xuất plugin ngoài stack phải có lý do chính đáng và hỏi user.

## Core (mọi site đều có)

| Component | Tool | Version | Note |
|---|---|---|---|
| WordPress core | WordPress | 6.8+ | Auto-update minor |
| Theme | Astra | Latest free | KHÔNG Pro |
| Page builder | Elementor | 3.20+ | + Flexbox Containers ON |
| Page builder pro | Elementor Pro | 3.20+ | License $59-99/year |
| MCP server | msrbuilds/elementor-mcp | v1.4+ | GitHub release |
| MCP adapter | WordPress MCP Adapter | Latest | Required cho MCP |
| Custom fields | ACF Free | Latest | Đơn giản |
| Custom fields advanced | JetEngine | Latest | Khi cần relationship/dynamic |

## Marketing/SEO

| Tool | Khi dùng |
|---|---|
| Rank Math Free | Mọi site (KHÔNG Yoast) |
| Schema Pro | Site có rich snippet (review, FAQ) |
| Redirection | Quản lý 301 |
| Site Kit by Google | GA4 + Search Console integration |

## Performance

| Tool | Tier |
|---|---|
| WP Rocket | Premium ($59/year) — site quan trọng |
| LiteSpeed Cache | Free — nếu hosting có LiteSpeed |
| ShortPixel | Image optimization |
| Cloudflare Free | CDN + DDoS protection |
| Asset CleanUp | Disable script không cần per-page |

## Security

| Tool | Mục đích |
|---|---|
| Wordfence Free | Firewall + malware scan |
| WPS Hide Login | Đổi /wp-admin URL |
| Two Factor | 2FA admin |
| Limit Login Attempts | Brute force protection |

## Backup

| Tool | Tần suất |
|---|---|
| UpdraftPlus | Daily DB + weekly files |
| Provider snapshot | Trước mỗi major edit (CloudPanel/Hostinger/SiteGround đều có) |
| WP Migrate Pro | Khi migrate staging→prod |

## Email

| Tool | Note |
|---|---|
| WP Mail SMTP | Bắt buộc, không dùng wp_mail() default |
| SendGrid / Brevo / Mailgun | Provider |

## Form & Lead

| Tool | Khi dùng |
|---|---|
| Elementor Form Pro | Form đơn giản (đã có sẵn trong Pro) |
| Fluent Forms | Form phức tạp, multi-step, conditional logic |
| WP Webhooks | Bridge form → CRM/n8n |

## Multilingual

| Tool | Khi dùng |
|---|---|
| Polylang Free | Site nhỏ 2 ngôn ngữ |
| Meep AI Translator | Site Elementor-heavy (đọc JSON Elementor) |
| WPML | Tránh — nặng cho Elementor |

## Page Speed budget

- Lighthouse mobile: ≥85
- Lighthouse desktop: ≥95
- LCP: <2.5s
- CLS: <0.1
- INP: <200ms
- Total page weight: <2MB (target <1MB)

## Plugin nào KHÔNG cài

- Jetpack (overkill, chậm)
- Elementor addon packs (Essential Addons, Premium Addons, Crocoblock JetElements) — bloat, dùng widget native + ACF/JetEngine
- WPBakery, Divi, Bricks, Beaver Builder — không trong stack
- Theme khác Astra — chỉ Astra Free
- Astra Pro — chồng feature với Elementor Pro

## CSS architecture: mu-plugin master CSS preferred over Code Snippets

Stack đề xuất dùng **1 mu-plugin master CSS** thay vì nhiều Code Snippets cho production CSS rules:

| Aspect | Code Snippets plugin | Mu-plugin master CSS |
|---|---|---|
| Cascade priority | Default `wp_head` priority | `wp_head` priority 100 → load SAU, thắng cascade |
| Version control | DB option (snippet content) | File trong repo, cPanel upload, git track |
| Specificity fight | Nhiều snippets compete với nhau | 1 file = 1 cascade order |
| Crash isolation | Snippet fatal = site 500 (xem [pitfalls "Code Snippets safety"](pitfalls.md)) | Mu-plugin nếu fatal cũng dễ rollback (rename file) |
| Recovery | Phải vào DB disable | SSH/Fileman rename `.php` → `.php.off` |

**Khi nào Code Snippets vẫn OK**:
- JS hooks 1-vài dòng (analytics event, scroll tracker)
- WP filter/action lẻ tẻ (vd: tweak excerpt length)
- Logic admin-only single-use (`scope=admin`, `active=-1`)

**Khi nào BẮT BUỘC mu-plugin**:
- CSS overrides cho production layout
- Elementor API calls (`files_manager`, `frontend->get_settings`, …)
- Code chạy ở `priority 1` hoặc trước Elementor init
- Anything mà nếu hỏng phải khôi phục site qua [`templates/snippets/wp-fix.php`](../templates/snippets/wp-fix.php)

## Compatibility: WAE plugin (`wordpress-wae`) cần mu-plugin show-in-rest fix

Nếu site dùng `wordpress-wae` plugin (89 abilities cho posts/pages/products/media), abilities mặc định `meta['show_in_rest'] = false` → REST controller `WP_REST_Abilities_V1_List_Controller::get_items()` filter theo meta này → chỉ 2 core abilities visible qua API.

**Fix**: deploy mu-plugin `wp-content/mu-plugins/abilities-show-in-rest.php`:

```php
<?php
/**
 * WAE compatibility — flip show_in_rest=true cho mcp-wp/* và core/* abilities.
 * Chạy ở hook wp_abilities_api_init priority 999 (sau khi WAE register).
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

Verify: `curl /wp-json/wp/v2/abilities | jq '.abilities | length'` — expect 80+ thay vì 2.
