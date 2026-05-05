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
