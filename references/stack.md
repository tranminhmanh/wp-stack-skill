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
