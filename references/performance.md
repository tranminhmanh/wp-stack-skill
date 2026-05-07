# Performance Optimization Workflow

## Quy trình debug site chậm

1. Run Lighthouse mobile + desktop
2. Run GTmetrix
3. Run PageSpeed Insights
4. Identify bottleneck: LCP / FCP / CLS / TBT

## Fix theo bottleneck

### LCP chậm (>2.5s)

- Hero image: convert WebP/AVIF, lazy load OFF cho hero
- Preload hero image: `<link rel="preload" as="image">`
- Preload font chính: woff2 với crossorigin
- Server-side cache: WP Rocket page cache ON
- CDN: Cloudflare proxied
- Database: optimize tables (Rank Math có tool)

### CLS cao (>0.1)

- Set width/height cho mọi `<img>` (Elementor tự làm nếu image_size set)
- Font-display: swap → có thể gây CLS, dùng `optional` cho non-critical
- Reserve space cho ads/embed
- KHÔNG inject content above existing content

### TBT cao (>200ms)

- Asset CleanUp: disable script không cần per page
- Defer JavaScript: WP Rocket có tool
- Combine CSS/JS: WP Rocket
- Remove unused CSS: WP Rocket Pro

## WP Rocket settings chuẩn

- File Optimization: minify CSS, minify JS, combine off (HTTP/2)
- Media: lazy load images, lazy load iframes/videos, replace YouTube với preview
- Cache: enable cache for mobile, separate cache mobile, cache lifespan 10 hours
- Database: cleanup post revisions weekly, cleanup transients daily
- CDN: Cloudflare addon ON nếu dùng

## ShortPixel settings

- Compression: Glossy (balance quality/size)
- Image format: WebP + AVIF fallback
- Resize on upload: max 2560px width
- Bulk optimize lúc đêm

## Cloudflare Free settings

- SSL: Full (strict)
- Always Use HTTPS: ON
- Auto Minify: HTML/CSS/JS
- Brotli: ON
- Browser Cache TTL: 1 year (static assets)

## Test sau optimize

```bash
npx lighthouse https://<site> --view --preset=desktop
npx lighthouse https://<site> --view --preset=perf
```

Target sau optimize:
- Mobile: 85+
- Desktop: 95+
- LCP: <2.5s
- CLS: <0.1
- INP: <200ms

## Bẫy WP Rocket hay gặp

### Combine CSS vỡ layout
Disable Combine CSS nếu Elementor render lỗi. HTTP/2 đã đủ tốt không cần combine.

### Lazy load hero image gây LCP cao
Hero image: add CSS class `no-lazy` hoặc disable lazy load cho `.elementor-section:first-of-type img`.

### Cache không clear sau MCP edit
WP Rocket cache page-level. Sau Claude Code edit qua MCP:
- Settings → Cache → Clear Cache → All
- Hoặc gọi MCP `clear_elementor_cache` (chỉ clear Elementor, vẫn cần clear WP Rocket manual)

## Bẫy Cloudflare hay gặp

### Always Online cache phiên bản cũ vô thời hạn
Disable nếu không cần. Hoặc Purge Everything sau deploy.

### Mixed content sau migrate HTTP→HTTPS
SSL: Full (strict) + Auto HTTPS Rewrites ON + Always Use HTTPS ON.
DB còn URL http://: dùng `wp search-replace` (xem deployment.md).

## Cache invalidation playbook by host

Sau MCP/PHP write op, page có thể vẫn render version cũ vì cache server-side. Workaround vary by host:

| Host / cache | Working method | NOT working |
|---|---|---|
| WP Rocket | Settings → Cache → Clear Cache → All; hoặc REST `/wp-json/wp-rocket/v1/clear-all` | — |
| LiteSpeed Cache (LSCWP self-hosted) | `\LiteSpeed\Purge::purge_all()` PHP; REST `X-LiteSpeed-Purge: *` | — |
| **LSCWP trên AZDIGI shared host (LSWS server-level)** | **Plugin deactivate → 1s → reactivate** | REST purge headers, `purge_all()`, WP-CLI cache purge — server-level cache ignore WP-level signal |
| Cloudflare | API `purge_cache` endpoint; UI Purge Everything | — |
| Object cache (Redis) | `wp cache flush`; `redis-cli FLUSHDB` | — |

**LSCWP shared host workaround** (chỉ plugin toggle hoạt động):
```bash
URL="$WP_SITE/wp-json/wp/v2/plugins/litespeed-cache/litespeed-cache"
curl -u "$WP_USER:$WP_PASS" -X POST "$URL" -d '{"status":"inactive"}'
sleep 1
curl -u "$WP_USER:$WP_PASS" -X POST "$URL" -d '{"status":"active"}'
```

Suspected root cause: server-level LSWS cache không nghe WordPress-level purge signal — chỉ plugin lifecycle hook (deactivate) trigger được host's cache invalidation.

### LiteSpeed: 2 invalidation paths phải phân biệt rõ

LiteSpeed cache có 2 path riêng — confusion gây debug nhầm:

| Path | Trigger | Status |
|---|---|---|
| **Auto-purge per-post** | `save_post` hook fires (any post update through Elementor save handler) | ✅ Works automatically — page cache invalidated ngay khi save |
| **Manual purge tool/API** | `\LiteSpeed\Purge::purge_all()`, REST `X-LiteSpeed-Purge`, plugin "Purge All" button | ❌ Broken trên AZDIGI shared host — chỉ plugin toggle workaround |

**Practical implication**:
- MCP write trigger `save_post` (vd `mcp_batch_update`, `update_widget`, `update_element`) → cache tự xoá → frontend hit fresh data ngay.
- MCP write CHỈ update meta thẳng (vd `update_page_from_file`) → KHÔNG trigger save_post → cache cũ vẫn serve. Phải follow-up bằng `batch_update` (xem [`elementor-mcp.md` "`update_page_from_file` không regen post_content"](elementor-mcp.md)).

**Verify cache state**:
```bash
curl -sI "https://site.com/page/" | grep -i x-litespeed
# x-litespeed-cache: hit       → đang serve cache cũ
# x-litespeed-cache: miss      → server generated fresh, sẽ cache lại
```

## Pre-deploy / pre-iteration cache clear ritual

Khi build/edit qua MCP rồi screenshot/test ngay:
```bash
# 1. Server-side
rm -rf wp-content/cache/* wp-content/uploads/elementor/css/*
docker exec <container> php -r 'opcache_reset();'

# 2. Plugin cache (chọn theo host)
# WP Rocket: WP-CLI rocket clean --confirm
# LSCWP shared host: plugin toggle (xem trên)

# 3. Browser
# URL?fresh=$(date +%s%N) + Cmd+Shift+R
```
