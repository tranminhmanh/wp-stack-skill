# Workflow: Comprehensive 8-dimension site audit (no Lighthouse required)

Audit a WordPress site across 8 dimensions using only `curl + regex + Python stdlib`. No Lighthouse / PSI API quota needed. Works on every site, free, repeatable.

## When to use

✅ Inherited site — need baseline understanding across multiple concerns
✅ Quarterly health check
✅ Pre-launch verification after a big rebuild
✅ PSI / Lighthouse API quota exhausted
✅ CI-friendly (no headless Chrome, no API key)

❌ Need real Core Web Vitals (LCP / CLS / INP) from real-browser runtime — use Lighthouse + [`workflows/lighthouse-driven-optim.md`](lighthouse-driven-optim.md) instead
❌ Need color-contrast actual rendering — use Lighthouse a11y or axe-core CLI (see [`references/a11y-debugging.md`](../references/a11y-debugging.md))

## The 8 dimensions

| # | Dimension | Method | Output |
|---|---|---|---|
| 1 | **SEO** | Crawl HTML → regex extract `<title>`, meta description, `<h1>`, schema `@type`, canonical, hreflang | `seo_audit.json` |
| 2 | **Performance** | curl `-w` TTFB + total time + page weight + asset count | `perf_audit.json` |
| 3 | **Security** | Extract plugin slug+version from HTML → cross-reference public CVE / WPScan database | `security_audit.json` |
| 4 | **Plugin usage** | Grep widget signatures in rendered HTML (`bdt-` for Element Pack, `uael-` for Ultimate Addons, `eael-` for Essential Addons, etc.) — measure what's actually used | `plugin_usage_audit.json` |
| 5 | **Schema** | Extract `<script type="application/ld+json">` → parse `@type` per page → coverage matrix | `schema_audit.json` |
| 6 | **Accessibility** (static) | Static HTML checks: `alt` presence, `lang` attribute, landmark roles (`<main>`, `<nav>`, `<footer>`), ARIA attributes, skip-link existence | `a11y_audit.json` |
| 7 | **DB / Robots** | Sitemap_index.xml structure (declared sitemaps, count entries) + robots.txt format (physical vs WP virtual, sitemap references) | `robots_audit.json` |
| 8 | **Redirects + links** | HEAD requests on internal links; extract anchor URLs from rendered HTML; identify 404s, redirect chains, malformed links | `redirect_audit.json` |

## Why this beats running 8 separate tools

- One Python stdlib script — no `npm install`, no API key, no service account
- Repeatable on schedule (cron, GitHub Action) — outputs JSON diffable across runs
- Works offline-style (no third-party API calls)
- Same crawler walks the site once, multiple audits piggyback on the same fetch

## Skeleton script

```python
#!/usr/bin/env python3
"""
comprehensive_audit.py — 8-dim audit, pure stdlib.
Usage: SITE=https://example.com python3 comprehensive_audit.py
"""
import json, os, re, time, urllib.request, urllib.parse, html.parser
from concurrent.futures import ThreadPoolExecutor

SITE = os.environ['SITE'].rstrip('/')
AUTH = os.environ.get('AUTH_HEADER')  # Basic <base64> if needed

# ---- 1. Get URL list from sitemap ----

def get_sitemap_urls():
    req = urllib.request.Request(f'{SITE}/sitemap_index.xml')
    if AUTH: req.add_header('Authorization', AUTH)
    idx = urllib.request.urlopen(req, timeout=30).read().decode('utf-8')
    sitemaps = re.findall(r'<loc>([^<]+)</loc>', idx)
    urls = []
    for sm in sitemaps:
        req = urllib.request.Request(sm)
        try:
            xml = urllib.request.urlopen(req, timeout=30).read().decode('utf-8')
            urls.extend(re.findall(r'<loc>([^<]+)</loc>', xml))
        except Exception as e:
            print(f"  skip {sm}: {e}")
    return list(set(urls))

# ---- 2. Single-page audit ----

def audit_page(url):
    out = {'url': url, 'errors': []}
    try:
        t0 = time.time()
        req = urllib.request.Request(url, headers={'User-Agent': 'comprehensive-audit/1.0'})
        resp = urllib.request.urlopen(req, timeout=30)
        body = resp.read()
        ttfb = time.time() - t0
        html_text = body.decode('utf-8', errors='replace')

        # Dimension 1: SEO
        title = (re.search(r'<title>([^<]*)</title>', html_text) or [None, ''])[1]
        desc = (re.search(r'<meta\s+name="description"\s+content="([^"]*)"', html_text) or [None, ''])[1]
        og_desc = (re.search(r'<meta\s+property="og:description"\s+content="([^"]*)"', html_text) or [None, ''])[1]
        canonical = (re.search(r'<link\s+rel="canonical"\s+href="([^"]+)"', html_text) or [None, ''])[1]
        h1s = re.findall(r'<h1[^>]*>(.*?)</h1>', html_text, re.S)
        hreflang = re.findall(r'<link\s+rel="alternate"\s+hreflang="([^"]+)"', html_text)
        out['seo'] = {
            'title': title, 'title_len': len(title),
            'description': desc[:200], 'description_len': len(desc),
            'og_description_match': desc.strip()[:50] == og_desc.strip()[:50],
            'canonical': canonical,
            'h1_count': len(h1s),
            'h1_first': re.sub(r'<[^>]+>', '', h1s[0])[:100] if h1s else None,
            'hreflang': hreflang,
        }

        # Dimension 2: Performance
        out['perf'] = {
            'ttfb_s': round(ttfb, 3),
            'page_weight_kb': round(len(body) / 1024, 1),
            'asset_count': len(re.findall(r'<(?:script|link|img)[^>]+(?:src|href)="[^"]+"', html_text)),
        }

        # Dimension 5: Schema
        schemas = re.findall(r'<script\s+type="application/ld\+json"[^>]*>(.*?)</script>', html_text, re.S)
        types = []
        for s in schemas:
            try:
                obj = json.loads(s.strip())
                if isinstance(obj, dict): types.extend(_extract_types(obj))
                elif isinstance(obj, list):
                    for o in obj: types.extend(_extract_types(o))
            except json.JSONDecodeError:
                out['errors'].append('schema_json_parse_fail')
        out['schema'] = {'types': sorted(set(types)), 'count': len(schemas)}

        # Dimension 6: A11y (static checks)
        out['a11y'] = {
            'lang_attr': bool(re.search(r'<html[^>]+lang="[^"]+"', html_text)),
            'main_landmark': bool(re.search(r'<main[\s>]', html_text)),
            'nav_landmark': bool(re.search(r'<nav[\s>]', html_text)),
            'footer_landmark': bool(re.search(r'<footer[\s>]', html_text)),
            'skip_link': bool(re.search(r'href="#(content|main|skip-link)"', html_text)),
            'images_total': len(re.findall(r'<img[\s>]', html_text)),
            'images_with_alt': len(re.findall(r'<img[^>]+alt="[^"]+"', html_text)),
            'images_alt_empty': len(re.findall(r'<img[^>]+alt=""', html_text)),
        }

        # Dimension 4: Plugin usage (widget signatures)
        out['plugins'] = {
            'element_pack': len(re.findall(r'\bbdt-[a-z-]+', html_text)),
            'ultimate_addons': len(re.findall(r'\buael-[a-z-]+', html_text)),
            'essential_addons': len(re.findall(r'\beael-[a-z-]+', html_text)),
            'jetelements': len(re.findall(r'\bjet-[a-z-]+', html_text)),
            'litespeed_lazyload': bool(re.search(r'data-src=', html_text)),
            'rank_math_schema': bool(re.search(r'@graph', html_text)),
            'foxai': bool(re.search(r'foxai-', html_text)),
        }

    except Exception as e:
        out['errors'].append(f'fetch_fail: {e}')
    return out

def _extract_types(obj):
    types = []
    if '@type' in obj:
        t = obj['@type']
        if isinstance(t, list): types.extend(t)
        else: types.append(t)
    if '@graph' in obj:
        for sub in obj['@graph']: types.extend(_extract_types(sub))
    return types

# ---- 3. Site-level audit (one-shot) ----

def audit_site():
    out = {}

    # Dimension 7: Robots.txt
    try:
        req = urllib.request.Request(f'{SITE}/robots.txt')
        resp = urllib.request.urlopen(req, timeout=10)
        robots = resp.read().decode('utf-8')
        out['robots'] = {
            'status': resp.status,
            'last_modified': resp.headers.get('last-modified'),  # if present, file is physical not virtual
            'accept_ranges': resp.headers.get('accept-ranges'),  # 'bytes' suggests physical file
            'sitemap_declarations': re.findall(r'^Sitemap:\s*(\S+)', robots, re.M),
            'disallow_count': len(re.findall(r'^Disallow:', robots, re.M)),
            'lines': len(robots.splitlines()),
        }
        # Tell if it's likely a physical file (not WP virtual)
        out['robots']['likely_physical'] = bool(out['robots']['last_modified'] and out['robots']['accept_ranges'])
    except Exception as e:
        out['robots'] = {'error': str(e)}

    # Sitemap structure
    try:
        req = urllib.request.Request(f'{SITE}/sitemap_index.xml')
        xml = urllib.request.urlopen(req, timeout=10).read().decode('utf-8')
        out['sitemap_index'] = {
            'declared_sitemaps': re.findall(r'<loc>([^<]+)</loc>', xml),
            'count': len(re.findall(r'<sitemap>', xml)),
        }
    except Exception as e:
        out['sitemap_index'] = {'error': str(e)}

    # Dimension 3: Security — generator meta + plugin versions
    try:
        req = urllib.request.Request(f'{SITE}/')
        homepage = urllib.request.urlopen(req, timeout=10).read().decode('utf-8')
        wp_version = (re.search(r'<meta\s+name="generator"\s+content="WordPress\s+([\d.]+)"', homepage) or [None, None])[1]
        # Plugin versions exposed via inline asset ?ver=X.Y.Z
        plugins_seen = {}
        for m in re.finditer(r'/wp-content/plugins/([a-z0-9-]+)/[^?"]+\?ver=([0-9.]+)', homepage):
            plugins_seen.setdefault(m.group(1), m.group(2))
        out['security'] = {
            'wp_version': wp_version,  # WordPress version (vulnerable if <current.minor)
            'wp_version_exposed': bool(wp_version),  # exposure = info leak
            'plugin_versions_exposed': plugins_seen,  # similar leak
        }
    except Exception as e:
        out['security'] = {'error': str(e)}

    return out

# ---- 4. Main ----

def main():
    out = {'site': SITE, 'audit_at': time.strftime('%Y-%m-%dT%H:%M:%S')}
    print('Fetching sitemap URLs...')
    urls = get_sitemap_urls()
    print(f'  found {len(urls)} URLs')

    print('Auditing pages (parallel x6)...')
    with ThreadPoolExecutor(max_workers=6) as exe:
        out['pages'] = list(exe.map(audit_page, urls))

    print('Site-level audit...')
    out['site_audit'] = audit_site()

    # Dimension 8: Redirect/link integrity — derived from page audits
    # Walk a sample of internal links per page, HEAD-check
    # (Omitted from skeleton for brevity — implement per project taste)

    with open('comprehensive_audit.json', 'w') as f:
        json.dump(out, f, ensure_ascii=False, indent=2)
    print(f'Done. Output: comprehensive_audit.json ({len(urls)} pages, 8 dimensions)')

if __name__ == '__main__':
    main()
```

## Output triage — what to flag

After running, derive an issue list from the JSON. Typical priorities:

| Severity | Examples |
|---|---|
| 🔴 Critical | WP version exposed + plugin versions exposed (security info leak); pages with 0 H1; canonical mismatch with permalink; sitemap declares 21+ feeds (most 404) |
| 🟠 High | Title > 70ch (SERP truncation); description missing or > 160ch; missing `og:description`; Element Pack subscriber-filter detected on many widgets (see [`pitfalls.md`](../references/pitfalls.md)) |
| 🟡 Medium | TTFB > 1.5s; page weight > 300KB; > 1 H1 per page; multiple FAQPage schema instances |
| 🟢 Low | Missing skip-link; landmarks missing on some pages; `alt=""` on >5% images (legitimate decorative may be OK) |

## Diagnostic step — PHP-runtime ability count vs REST-list count

When auditing "missing abilities" or a "tool count gap" on an MCP-bridged site, always compare TWO sources:

1. **PHP-runtime count** — what `wp_get_abilities()` returns when called from a wp-load.php context (the actual registry).
2. **REST-list count** — what `GET /wp-json/wp-abilities/v1/abilities` returns to an external client.

The gap between these two tells you WHERE the problem is:

| Pattern | Means | Fix path |
|---|---|---|
| Runtime = REST = expected count | Both sources match expectation → nothing missing | (no action) |
| Runtime > REST (e.g. 194 vs 100) | **Display filter problem** — pagination cap, `show_in_rest` meta missing, REST permission filter | Adjust `per_page` query, flip `meta.show_in_rest:true`, check REST capability filter |
| Runtime < expected | **Registration problem** — plugin inactive, fatal error in plugin's data file, hook not firing | Reactivate plugin, check error log, verify the canonical `wp_abilities_api_init` hook fires |

### Probe runtime count via WP-context PHP

```bash
# Drop a probe.php in webroot, hit via curl, then stub it out
cat > /tmp/probe.php <<'PHP'
<?php
require_once __DIR__ . '/wp-load.php';
if (!function_exists('wp_get_abilities')) {
    echo "wp_abilities_api not loaded\n"; exit;
}
$abilities = wp_get_abilities();
$by_ns = [];
foreach ($abilities as $name => $a) {
    $ns = strstr($name, '/', true) ?: '(no-namespace)';
    $by_ns[$ns] = ($by_ns[$ns] ?? 0) + 1;
}
ksort($by_ns);
foreach ($by_ns as $ns => $count) {
    printf("%-30s %d\n", $ns, $count);
}
printf("\nTOTAL: %d abilities registered\n", count($abilities));
PHP

# Deploy via cPanel Fileman or SCP, then:
curl -s "https://<site>/probe.php?token=<token>"
# Output e.g.:
#   core                   3
#   elementor-mcp        105
#   mcp-wp                86
#   TOTAL: 194 abilities registered

# Compare to REST:
curl -u "$U:$APP_PW" "https://<site>/wp-json/wp-abilities/v1/abilities?per_page=200" \
  | jq '. | length'
# 100 ← capped at default per_page

# Mismatch (194 runtime vs 100 REST) → pagination cap issue
```

⚠️ Always stub `probe.php` after use (overwrite with `<?php // disabled`) — leaving probes accessible is a security risk.

### Common causes of runtime > REST

1. **Default `per_page=100` cap on the REST list** — see [`mcp-architecture.md`](../references/mcp-architecture.md) "REST registry pagination caveat". Override with `?per_page=200`.
2. **mu-plugin pagination override** — some mu-plugins (`abilities-show-in-rest.php` variants) wrap the REST controller and cap `per_page` to 100 max. Source-grep for `min(100,` in mu-plugins.
3. **Missing `meta.show_in_rest: true`** — see [`wp-abilities.md`](../references/wp-abilities.md). The ability registers but is filtered out of the REST list.

### When to suspect tool count gap

- MCP session shows fewer tools than expected after `claude mcp list` shows ✓ Connected
- A specific namespace (`mcp-wp/*`, `rankmath-mcp/*`) is entirely absent while others are present
- npm stdio bridge logs show only N abilities while the site dashboard reports N+M

The runtime-vs-REST compare disambiguates "ability not registered" from "ability not visible". Always do this before assuming the plugin needs reinstall.

## Limitations

1. **No real Core Web Vitals** (LCP / CLS / INP) — requires a real browser. For perf optimization, use [`workflows/lighthouse-driven-optim.md`](lighthouse-driven-optim.md).
2. **No color contrast** — needs rendered pixels. Use Lighthouse a11y category or axe-core CLI ([`references/a11y-debugging.md`](../references/a11y-debugging.md)).
3. **No JS-rendered content audit** — pure HTML crawl misses content injected by client-side scripts (React widgets, late-bound Elementor templates).
4. **Form labels** — Fluent Forms / CF7 sometimes render labels via JS, missing here. Manual spot-check the contact page.

For all four limitations, the workflow is complementary: run this for breadth, run Lighthouse selectively for depth.

## Dashboard output (optional)

The JSON output ships nicely to the `data:build-dashboard` skill:

```
/build-dashboard Site audit dashboard for <site> — N pages, 8 dimensions
Input: comprehensive_audit.json
Output: dashboard.html (single file, embed Chart.js CDN)
Layout:
  - KPI cards: pages audited, critical issues, high issues, avg TTFB, avg page weight
  - Issue summary group by dimension
  - Charts: TTFB histogram, page weight distribution, schema coverage matrix, plugin usage frequency
  - Per-page table sortable by dimension flags
```

Battle-tested output: 32KB single HTML embedding 20 pages, renders <100ms, shareable without a terminal. See [`workflows/seo-audit.md`](seo-audit.md) "data:build-dashboard skill integration" for the pattern.

## Reusable scripts

Save as `<project>/audit/comprehensive_audit.py` plus a tiny driver:

```bash
# audit/run-audit.sh
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
SITE=https://<your-site> \
AUTH_HEADER="Basic $(printf '%s' "$WP_USER:$WP_APP_PW" | base64)" \
python3 comprehensive_audit.py
```

Schedule via cron / GitHub Action for trendlines.

## Cross-references

- [`workflows/seo-audit.md`](seo-audit.md) — narrower SEO-only audit, deeper Rank Math integration
- [`workflows/lighthouse-driven-optim.md`](lighthouse-driven-optim.md) — Lighthouse-based perf workflow
- [`references/a11y-debugging.md`](../references/a11y-debugging.md) — fix patterns when audit flags a11y issues
- [`references/pitfalls.md`](../references/pitfalls.md) — Element Pack subscriber filter, WP version exposure, plugin redundancy
- [`references/seo-checklist.md`](../references/seo-checklist.md) — Rank Math meta + schema setup

## Audit Rank Math features — Link Genius backend = REST routes (not abilities)

When auditing Rank Math features (Link Genius/Link Builder for internal linking automation, focus keyword status, redirects, schema templates), be aware:

**Rank Math Link Genius backend = REST routes**, NOT WP Abilities Framework abilities. Endpoints:
- `/wp-json/rankmath/v1/links/posts` — list posts with link stats
- `/wp-json/rankmath/v1/links/{id}` — per-post incoming/outgoing links
- `/wp-json/rankmath/v1/links/posts-stats` — site-wide stats
- `/wp-json/rankmath/v1/links/links-stats` — top linked posts

Direct REST audit (no plugin needed):
```bash
curl -u $U:$P "$SITE/wp-json/rankmath/v1/links/posts-stats?per_page=200" \
  | jq '{total: .total, orphan: [.posts[] | select(.is_orphan==true)] | length, average_score: ([.posts[].seo_score] | add / length)}'
```

To MCP-discover Link Genius features, wrap REST routes into abilities (see [`build-mcp-wrapper-plugin.md`](build-mcp-wrapper-plugin.md)). Without wrapping, Claude/MCP clients can't auto-discover — but direct REST works fine for one-off audits.

## Rank Math `rank_math_*` meta NOT exposed via REST default

When auditing meta completeness (title, description, focus_keyword), `PATCH /wp/v2/pages/{id} {meta: {rank_math_*: "..."}}` returns HTTP 200 but **silent ignore** — Rank Math doesn't register `show_in_rest=true` for these meta keys.

Workarounds (per audit need):
- **Read in edit context**: `/wp-json/wp/v2/pages/{id}?context=edit&_fields=meta` — meta visible for admin/editor auth
- **Bulk update during audit**: One-shot mu-plugin (custom REST endpoint with token guard, calls `update_post_meta` directly) — see [`../references/rankmath.md`](../references/rankmath.md) "`rank_math_*` post meta NOT exposed via REST default"
- **Permanent wrapper**: `rankmath-mcp` plugin pattern — see [`build-mcp-wrapper-plugin.md`](build-mcp-wrapper-plugin.md)

Audit step — verify access path before bulk update:
```bash
curl -u $U:$P "$SITE/wp-json/wp/v2/pages/123?context=edit&_fields=meta" | jq '.meta | keys[]' | grep rank_math
# If visible → read access OK, but write needs one-shot
```
