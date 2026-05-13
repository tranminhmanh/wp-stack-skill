# Performance Optimization Workflow

## Diagnosing a slow site

1. Run Lighthouse mobile + desktop
2. Run GTmetrix
3. Run PageSpeed Insights
4. Identify the bottleneck: LCP / FCP / CLS / TBT

## Fixes by bottleneck

### LCP slow (>2.5s)

- Hero image: convert to WebP/AVIF, lazy load OFF for the hero
- Preload hero image: `<link rel="preload" as="image">`
- Preload primary font: woff2 with `crossorigin`
- Server-side cache: WP Rocket page cache ON
- CDN: Cloudflare proxied
- Database: optimize tables (Rank Math has a tool)

### CLS high (>0.1)

- Set width/height on every `<img>` (Elementor does this automatically if `image_size` is set)
- `font-display: swap` can cause CLS — use `optional` for non-critical fonts
- Reserve space for ads / embeds
- Do NOT inject content above existing content

### TBT high (>200ms)

- Asset CleanUp: disable scripts not needed per page
- Defer JavaScript: WP Rocket has a tool
- Combine CSS / JS: WP Rocket
- Remove unused CSS: WP Rocket Pro

## WP Rocket standard settings

- File Optimization: minify CSS, minify JS, combine OFF (HTTP/2)
- Media: lazy load images, lazy load iframes/videos, replace YouTube with preview
- Cache: enable cache for mobile, separate mobile cache, lifespan 10 hours
- Database: cleanup post revisions weekly, cleanup transients daily
- CDN: Cloudflare addon ON if using

## ShortPixel settings

- Compression: Glossy (balance quality / size)
- Image format: WebP + AVIF fallback
- Resize on upload: max 2560px width
- Bulk-optimize at night

## Cloudflare Free settings

- SSL: Full (strict)
- Always Use HTTPS: ON
- Auto Minify: HTML / CSS / JS
- Brotli: ON
- Browser Cache TTL: 1 year (static assets)

## Test after optimizing

```bash
npx lighthouse https://<site> --view --preset=desktop
npx lighthouse https://<site> --view --preset=perf
```

Targets after optimization:
- Mobile: 85+
- Desktop: 95+
- LCP: <2.5s
- CLS: <0.1
- INP: <200ms

## Common WP Rocket pitfalls

### Combine CSS breaks layout
Disable Combine CSS if Elementor renders broken. HTTP/2 is good enough; combining is unnecessary.

### Lazy-loaded hero image causes high LCP
Hero image: add CSS class `no-lazy` or disable lazy load for `.elementor-section:first-of-type img`.

### Cache not clearing after MCP edit
WP Rocket caches at the page level. After a Claude Code MCP edit:
- Settings → Cache → Clear Cache → All
- Or call MCP `clear_elementor_cache` (only clears Elementor; you still need to manually clear WP Rocket)

## Common Cloudflare pitfalls

### Always Online caches an old version forever
Disable if not needed. Or Purge Everything after deploy.

### Mixed content after HTTP→HTTPS migration
SSL: Full (strict) + Auto HTTPS Rewrites ON + Always Use HTTPS ON.
DB still has http:// URLs: use `wp search-replace` (see `deployment.md`).

## Cache invalidation playbook by host

After an MCP / PHP write op, the page may still serve the old version due to server-side cache. Workaround varies by host:

| Host / cache | Working method | NOT working |
|---|---|---|
| WP Rocket | Settings → Cache → Clear Cache → All; or REST `/wp-json/wp-rocket/v1/clear-all` | — |
| LiteSpeed Cache (LSCWP self-hosted) | `\LiteSpeed\Purge::purge_all()` PHP; REST `X-LiteSpeed-Purge: *` | — |
| **LSCWP on shared host with LSWS server-level cache** | **Plugin deactivate → 1s → reactivate** | REST purge headers, `purge_all()`, WP-CLI cache purge — server-level cache ignores WP-level signals |
| Cloudflare | API `purge_cache` endpoint; UI Purge Everything | — |
| Object cache (Redis) | `wp cache flush`; `redis-cli FLUSHDB` | — |

**LSCWP shared-host workaround** (only the plugin toggle works):
```bash
URL="$WP_SITE/wp-json/wp/v2/plugins/litespeed-cache/litespeed-cache"
curl -u "$WP_USER:$WP_PASS" -X POST "$URL" -d '{"status":"inactive"}'
sleep 1
curl -u "$WP_USER:$WP_PASS" -X POST "$URL" -d '{"status":"active"}'
```

Suspected root cause: server-level LSWS cache does not listen to WordPress-level purge signals — only the plugin lifecycle hook (deactivate) triggers the host's cache invalidation.

### LiteSpeed: 2 invalidation paths to keep distinct

LiteSpeed cache has two separate paths — confusion causes mis-debugging:

| Path | Trigger | Status |
|---|---|---|
| **Auto-purge per-post** | `save_post` hook fires (any post update through the Elementor save handler) | ✅ Works automatically — page cache invalidated as soon as the save fires |
| **Manual purge tool / API** | `\LiteSpeed\Purge::purge_all()`, REST `X-LiteSpeed-Purge`, plugin "Purge All" button | ❌ Broken on shared hosts — only the plugin toggle workaround works |

**Practical implication**:
- MCP writes that trigger `save_post` (e.g. `mcp_batch_update`, `update_widget`, `update_element`) → cache clears automatically → frontend hits fresh data immediately.
- MCP writes that update meta directly (e.g. `update_page_from_file`) → do NOT trigger `save_post` → cache still serves the old version. Follow up with `batch_update` (see [`elementor-mcp.md` "`update_page_from_file` does not regen post_content"](elementor-mcp.md)).

**Verify cache state**:
```bash
curl -sI "https://site.com/page/" | grep -i x-litespeed
# x-litespeed-cache: hit       → serving old cache
# x-litespeed-cache: miss      → server generated fresh, will cache next
```

### LiteSpeed default static-asset TTL fails Lighthouse `uses-long-cache-ttl`

**Symptom**: Lighthouse audit `uses-long-cache-ttl` fails. The static assets (JPEG, PNG, WebP, woff2, CSS, JS) return `cache-control: public, max-age=604800` (7 days) — LSCWP plugin default. Lighthouse expects ≥30 days for typical assets and ≥365 days for versioned assets.

**Root cause**: LSCWP sets `max-age=604800` for static assets out of the box. The plugin assumes cache may need to be invalidated within a week. Lighthouse benchmarks against modern CDN best practices where versioned assets get 1-year TTL with `immutable`.

**Fix**: prepend an `<IfModule mod_headers.c>` block in `.htaccess` BEFORE the `# BEGIN LSCACHE` block. `Header always set` runs late in the response chain and overrides LSCWP's defaults:

```apache
# Long-TTL override — must come BEFORE # BEGIN LSCACHE
<IfModule mod_headers.c>
  <FilesMatch "\.(jpe?g|png|gif|webp|avif|svg|ico|woff2?|ttf|eot|otf)$">
    Header always set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
  <FilesMatch "\.(css|js)$">
    Header always set Cache-Control "public, max-age=31536000"
  </FilesMatch>
</IfModule>

# BEGIN LSCACHE
# ... existing LSCWP block, untouched ...
# END LSCACHE
```

- 31536000 seconds = 1 year
- `immutable` flag on images/fonts: tells the browser the asset will never change — skip revalidation entirely. Safe because WordPress versions images via filename (`-1024x576`, `-rn[hash]`).
- CSS/JS `immutable` only safe if assets carry `?ver=` query strings — WP core does this by default. If a plugin emits unversioned `<link>` / `<script>` tags, drop `immutable` for CSS/JS.

**Verify**:
```bash
curl -sI "https://<site>/wp-content/uploads/2026/05/hero.jpg" | grep -i cache-control
# expect: cache-control: public, max-age=31536000, immutable
```

**When NOT to apply 1-year TTL**:
- Asset URLs without versioning (raw `/uploads/foo.png` referenced from a CDN that doesn't bust on file change) → use 30 days (`max-age=2592000`) instead.
- Frequently-rotating sprite sheets or theme assets shipped via `enqueue_script` without `?ver=` → drop the rule for those file patterns.

**Reusability**: universal for any LiteSpeed shared hosting where you have `.htaccess` write access. Same pattern works for Apache without LSCWP — just prepend the `<IfModule>` block at the top of `.htaccess`.

## Pre-deploy / pre-iteration cache-clear ritual

When building / editing via MCP and immediately screenshotting / testing:
```bash
# 1. Server-side
rm -rf wp-content/cache/* wp-content/uploads/elementor/css/*
docker exec <container> php -r 'opcache_reset();'

# 2. Plugin cache (pick by host)
# WP Rocket: WP-CLI rocket clean --confirm
# LSCWP shared host: plugin toggle (see above)

# 3. Browser
# URL?fresh=$(date +%s%N) + Cmd+Shift+R
```

## LiteSpeed Cache lifecycle — 2 invalidation paths

LSC cache invalidates qua 2 different paths, behave differently:

| Path | Trigger | Reliability |
|---|---|---|
| **Per-post auto-purge** | `save_post` hook fires (post update/REST PATCH/MCP edit) | ✓ Reliable |
| **Manual "Purge All"** | Admin clicks LSC Toolbox button | ⚠️ Sometimes flaky via REST/CLI; works via UI |
| **Plugin toggle** (deactivate→reactivate) | Last-resort nuke | ✓ Reliable but heavy |

### Recommended workflow

```bash
# Want fresh content for ONE page → use update path, not purge
curl -X POST -u $U:$P \
  "https://site/wp-json/wp/v2/pages/4540" \
  -d '{"content": "updated"}'
# → save_post fires → LSC auto-purges page 4540 → frontend fresh

# Want full site fresh → use UI not REST API
# wp-admin → LiteSpeed Cache → Toolbox → Purge All
# (LSC REST endpoint /litespeed-cache/v1/purge — known flaky)
```

### When REST GET stale-read

Sau write thành công, GET ngay sau có thể trả stale value. Đặc biệt với WP-Abilities REST `/wp-json/wp-abilities/v1/abilities/*/run`. Fix: add `rest_post_dispatch` filter emit no-cache headers — xem [`workflows/litespeed-cache-mgmt.md`](../workflows/litespeed-cache-mgmt.md) section 1 cho code recipe.

## LiteSpeed default `max-age=604800` (7 days) → Lighthouse cache policy FAIL

LSC default sets `public, max-age=604800` (7 days) cho mọi cached resource. Lighthouse "Serve static assets with an efficient cache policy" audit FAILS với 7 days — Lighthouse wants ≥30 days, ideally 1 year + `immutable` cho versioned/hashed assets.

### Fix via .htaccess override

```apache
# .htaccess in WP root, BELOW LiteSpeed rules
<IfModule mod_expires.c>
    ExpiresActive On

    # Versioned/hashed static assets — 1 year + immutable
    <FilesMatch "\.(woff2|woff|ttf|eot|otf)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>

    # Versioned JS/CSS (WP appends ?ver= to enqueued URLs)
    <FilesMatch "\.(js|css)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>

    # Images (rare update — 6 months)
    <FilesMatch "\.(jpg|jpeg|png|webp|gif|svg|ico)$">
        Header set Cache-Control "public, max-age=15552000"
    </FilesMatch>
</IfModule>
```

⚠️ `immutable` chỉ áp dụng cho versioned URLs (`script.js?ver=2.5`). Without version param, browser sẽ NOT revalidate even khi file changes server-side. WP automatically appends `?ver=` cho enqueued scripts/styles → safe cho most theme/plugin assets.

### Verify

```bash
curl -sI "https://site/wp-content/uploads/2024/05/hero.webp" | grep -i cache-control
# Expected: max-age=15552000 (after override)

# Re-run Lighthouse "Serve static assets..." audit → should pass
```

## Avatar `src=""` is LiteSpeed lazy-load, NOT broken image

**Symptom**: View source/grep `<img src="">` returns empty src on `<img>` tags. Looks like broken images.

**Reality**: LiteSpeed Cache plugin's image lazy-load feature rewrites `<img>` tags before serving HTML:
- `src=""` (empty)
- `data-lazyloaded="1"` data attribute set
- Actual URL moved to `data-src` (or `data-srcset` for responsive)
- JS executes onload → swap data-src → src when visible

This is **expected behavior** — không phải broken.

### Verify

```bash
curl -s "https://site/" | grep -oE '<img[^>]+>' | head -5
# Look for data-lazyloaded="1" attribute → confirmed lazy-load rewrite
```

### Real issue: oversized image, NOT empty src

The actual perf issue:
- Avatar image 1024×1024 displayed at 50×50 thumbnail size
- No `srcset` variant generated → browser downloads full 1MB image cho 50px display
- Lighthouse flags via "Properly size images" audit

### Fix

WordPress automatically generates srcset variants if media uploaded > certain dimensions. Verify:
```bash
curl -s "https://site/" | grep -oE 'srcset="[^"]+"' | head -3
# Expected: multiple URLs with widths (vd avatar-50x50.webp 50w, avatar-100x100.webp 100w, ...)
```

If no `srcset`:
- Re-upload image via wp-admin Media (triggers thumbnail generation)
- Or use Regenerate Thumbnails plugin
- Or use ShortPixel / Imagify bulk to re-generate
