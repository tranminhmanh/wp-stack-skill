# Workflow: SEO Audit (3-tier pattern)

Bulk audit N WordPress pages cho SEO health: title/meta/H1 hierarchy/schema/canonical/internal links/og:image. Pattern proven on a 52-page site, ~30 phút audit (vs ~1 tuần manual GUI Rank Math).

## Khi nào dùng

✅ Pre-launch QA cho site mới build (≥20 pages).
✅ Quarterly SEO health check.
✅ Sau mass-build via MCP/PHP (validate consistency).
✅ Inherited site, cần baseline understanding.

❌ Single page audit → dùng Rank Math GUI nhanh hơn.

## 3-tier audit pattern

### Tier 1: PHP backend dump (`/tmp/seo_audit.php`)

Đọc post_meta + walk `_elementor_data` → JSON output.

```php
<?php
require_once '/var/www/html/wp-load.php';

$pages = get_posts(['post_type' => 'page', 'numberposts' => -1, 'post_status' => 'publish']);
$results = [];

foreach ($pages as $p) {
    $row = [
        'id' => $p->ID,
        'slug' => $p->post_name,
        'url' => get_permalink($p->ID),
        'rm_title' => get_post_meta($p->ID, 'rank_math_title', true),
        'rm_desc' => get_post_meta($p->ID, 'rank_math_description', true),
        'rm_canonical' => get_post_meta($p->ID, 'rank_math_canonical_url', true),
        'rm_focus' => get_post_meta($p->ID, 'rank_math_focus_keyword', true),
        'rm_og_image_id' => get_post_meta($p->ID, 'rank_math_facebook_image_id', true),
        'page_template' => get_post_meta($p->ID, '_wp_page_template', true),
        'edit_mode' => get_post_meta($p->ID, '_elementor_edit_mode', true),
    ];

    $data = json_decode(get_post_meta($p->ID, '_elementor_data', true), true) ?: [];
    $row['counts'] = walk_count($data);  // h1/h2/h3, internal_links, images, schema_scripts, ...
    $results[] = $row;
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

function walk_count(array $els, array &$counts = ['h1'=>0,'h2'=>0,'h3'=>0,'internal_links'=>0,'images_with_alt'=>0,'images_no_alt'=>0,'schema_scripts'=>0]): array {
    foreach ($els as $el) {
        $type = $el['widgetType'] ?? '';
        $s = $el['settings'] ?? [];

        if ($type === 'heading') {
            $size = strtolower($s['header_size'] ?? 'h2');
            if (isset($counts[$size])) $counts[$size]++;
        }

        // Walk button/icon-box/image link.url
        foreach (['link', 'link_to'] as $linkfield) {
            if (isset($s[$linkfield]['url']) && str_starts_with($s[$linkfield]['url'], '/')) {
                $counts['internal_links']++;
            }
        }

        // Walk HTML widget href
        foreach (['html', 'editor', 'title'] as $field) {
            if (!empty($s[$field]) && is_string($s[$field])) {
                if (preg_match_all('/href="(\/[^"]+)"/i', $s[$field], $m)) {
                    $counts['internal_links'] += count($m[1]);
                }
                if (str_contains($s[$field], 'application/ld+json')) {
                    $counts['schema_scripts']++;
                }
            }
        }

        if ($type === 'image') {
            empty($s['image']['alt']) ? $counts['images_no_alt']++ : $counts['images_with_alt']++;
        }

        if (isset($el['elements']) && is_array($el['elements'])) {
            walk_count($el['elements'], $counts);
        }
    }
    return $counts;
}
```

### Tier 2: Bash frontend (`/tmp/seo_audit_live.sh`)

Curl all URLs → grep rendered HTML cho `<title>`, `<meta>`, `<h1>`, schema, og:image, canonical.

```bash
#!/usr/bin/env bash
URLS=(
    "https://example.com/"
    "https://example.com/page-1/"
    # ...
)

echo '['
first=1
for url in "${URLS[@]}"; do
    [ $first -eq 0 ] && echo ','
    first=0

    html=$(curl -s "$url")
    title=$(echo "$html" | grep -oE '<title>[^<]+</title>' | sed 's|<[^>]*>||g' | head -1)
    desc=$(echo "$html" | grep -oE '<meta name="description" content="[^"]+"' | sed 's|.*content="||;s|"$||')
    h1=$(echo "$html" | grep -oE '<h1[^>]*>[^<]+</h1>' | sed 's|<[^>]*>||g' | head -1)
    h1_count=$(echo "$html" | grep -cE '<h1[^>]*>')
    canonical=$(echo "$html" | grep -oE '<link rel="canonical" href="[^"]+"' | sed 's|.*href="||;s|"$||')
    og_image=$(echo "$html" | grep -oE '<meta property="og:image" content="[^"]+"' | sed 's|.*content="||;s|"$||')
    schema_types=$(echo "$html" | grep -oE '"@type"[^"]*"[A-Za-z]+"' | sed 's|.*"||;s|"$||' | sort -u | paste -sd ',' -)

    cat <<EOF
{
  "url": "$url",
  "title": $(printf '%s' "$title" | jq -Rs .),
  "title_len": ${#title},
  "desc": $(printf '%s' "$desc" | jq -Rs .),
  "desc_len": ${#desc},
  "h1": $(printf '%s' "$h1" | jq -Rs .),
  "h1_count": $h1_count,
  "canonical": "$canonical",
  "og_image": "$og_image",
  "schema_types": "$schema_types"
}
EOF
done
echo ']'
```

### Tier 3: Python analyze (`/tmp/seo_analyze.py`)

Merge backend + frontend by URL → apply rules per page category → group by severity → markdown report.

```python
import json
from pathlib import Path

backend = json.loads(Path('/tmp/seo_audit_backend.json').read_text())
frontend = json.loads(Path('/tmp/seo_audit_live.json').read_text())

# Index by URL
b_by_url = {row['url']: row for row in backend}
f_by_url = {row['url']: row for row in frontend}

# Category rules — adjust per project
CATEGORIES = {
    'system': lambda url: url.endswith(('/', '/contact/', '/about/')),
    'pillar': lambda url: '/topic/' in url and url.count('/') == 4,
    'subpage': lambda url: '/topic/' in url and url.count('/') == 5,
    'blog': lambda url: '/blog/' in url and url.count('/') >= 4,
}

SCHEMA_REQUIREMENTS = {
    'system': ['Organization', 'WebSite'],
    'pillar': ['BreadcrumbList', 'Service', 'FAQPage'],
    'subpage': ['BreadcrumbList', 'Service', 'FAQPage'],
    'blog': ['BreadcrumbList', 'BlogPosting', 'FAQPage'],
}

issues = {'critical': [], 'high': [], 'medium': [], 'low': []}

for url in sorted(b_by_url.keys() | f_by_url.keys()):
    b = b_by_url.get(url, {})
    f = f_by_url.get(url, {})
    cat = next((c for c, fn in CATEGORIES.items() if fn(url)), 'other')

    # Critical: H1 duplicate
    if f.get('h1_count', 0) > 1:
        issues['critical'].append(f"[{cat}] {url}: {f['h1_count']} H1 tags (Astra entry-title duplicate?)")

    # High: missing meta
    if not f.get('title') or f.get('title_len', 0) > 60:
        issues['high'].append(f"[{cat}] {url}: title len={f.get('title_len',0)} (target 40-55 cho VN, 50-60 EN)")
    if not f.get('desc'):
        issues['high'].append(f"[{cat}] {url}: meta description missing")

    # High: schema missing
    required_schemas = set(SCHEMA_REQUIREMENTS.get(cat, []))
    found_schemas = set((f.get('schema_types') or '').split(','))
    missing_schemas = required_schemas - found_schemas
    if missing_schemas:
        issues['high'].append(f"[{cat}] {url}: missing schemas {missing_schemas}")

    # Medium: og:image
    if not f.get('og_image'):
        issues['medium'].append(f"[{cat}] {url}: og:image missing")

    # Medium: 0 internal links (pillars should have 3+)
    if cat in ('pillar', 'subpage') and b.get('counts', {}).get('internal_links', 0) == 0:
        issues['medium'].append(f"[{cat}] {url}: 0 internal links detected (false positive cho button/icon-box?)")

    # Low: canonical empty
    if not f.get('canonical'):
        issues['low'].append(f"[{cat}] {url}: canonical missing")

# Generate markdown report
print("# SEO Audit Report\n")
for severity in ('critical', 'high', 'medium', 'low'):
    if issues[severity]:
        print(f"## {severity.upper()} ({len(issues[severity])})\n")
        for i in issues[severity]:
            print(f"- {i}")
        print()
```

## Per-page deep audit (more thorough)

Khi bulk audit phát hiện outlier, deep audit 1 page:

```php
$walk = function($els) use (&$walk, &$widget_types, &$headings, &$internal_links, &$images_with_alt, &$images_no_alt) {
    foreach ($els as $el) {
        $type = $el['widgetType'] ?? '';
        $s = $el['settings'] ?? [];
        $widget_types[$type] = ($widget_types[$type] ?? 0) + 1;

        // Heading hierarchy WITH content (not just count)
        if ($type === 'heading') {
            $size = strtolower($s['header_size'] ?? 'h2');
            $headings[$size][] = $s['title'] ?? '';
        }

        // Link map by source
        if ($type === 'button' && !empty($s['link']['url'])) {
            $internal_links[] = ['type' => 'button', 'url' => $s['link']['url'], 'text' => $s['text'] ?? ''];
        }
        if (in_array($type, ['icon-box', 'image-box']) && !empty($s['link']['url'])) {
            $internal_links[] = ['type' => $type, 'url' => $s['link']['url']];
        }
        foreach (['html', 'editor', 'title'] as $field) {
            if (!empty($s[$field]) && preg_match_all('/href="(\/[^"]+)"/i', $s[$field], $m)) {
                foreach ($m[1] as $href) {
                    $internal_links[] = ['type' => "html-$field", 'url' => $href];
                }
            }
        }

        // Image alt status
        if ($type === 'image') {
            !empty($s['image']['alt']) ? $images_with_alt[] = $s['image']['url'] ?? ''
                                       : $images_no_alt[] = $s['image']['url'] ?? '';
        }

        if (!empty($el['elements'])) $walk($el['elements']);
    }
};
$walk($data);
```

Output: widget inventory table + heading hierarchy with content + link map by source type + image alt status + schema scripts inline.

## Always verify HTTP code cho internal links

Bulk audit count internal links nhưng KHÔNG check links work. Critical bug pattern: build pillar pages với slug A, homepage built earlier có URL slug B → 8 dead links 404, leak link equity.

```bash
# After bulk-build, verify all internal links:
declare -A seen
for url in $(jq -r '.[].counts.internal_link_urls[]' backend.json | sort -u); do
    [ -n "${seen[$url]}" ] && continue
    seen[$url]=1
    code=$(curl -s -o /dev/null -w "%{http_code}" "https://example.com$url")
    [ "$code" = "200" ] || echo "DEAD: $url ($code)"
done
```

CI integrate: chạy script này sau mỗi build, fail PR nếu có dead link.

## False positive types (improve detector)

3 issue types có high false positive rate:

### 1. CTA-missing
Script chỉ count `widgetType=button`. Pages dùng `<a class="cta-primary">` trong HTML widget → không detect.

**Fix detector**:
```python
if widget_type == 'html' and html_content:
    cta_count += len(re.findall(r'class="[^"]*cta[^"]*"|href="/contact/"', html_content))
```

### 2. Internal-links 0
Script chỉ count `href` trong HTML/text widgets. Miss button/icon-box/image `link.url`.

**Fix detector**:
```python
def count_links(settings):
    count = 0
    for key, val in settings.items():
        if isinstance(val, dict) and 'url' in val:
            url = val['url']
            if url.startswith('/') or 'mysite.com' in url:
                count += 1
    return count
```

### 3. BreadcrumbList missing on `/`
Homepage depth=0, không cần breadcrumb. Skip rule cho homepage.

## Audit script design lessons

1. **Validate detector against actual rendered HTML** — không trust pure backend data.
2. **Acceptance criteria phải account for design patterns** — Elementor có nhiều cách thể hiện 1 thứ (button widget vs HTML widget vs icon-box link).
3. **Categorize false positives ngay từ đầu** trong report — đừng để stakeholder waste time fix non-issues.
4. **Phân biệt rõ "false positive" vs "minor" vs "real issue"** — không gộp hết vào High/Medium/Low.
5. **Per-page deep audit khi bulk thiếu detail** — bulk gives breadth, deep gives root-cause.

## Reusable cho project khác

Adjust 3 chỗ:
1. `CATEGORIES` rules in Python analyze (per URL pattern).
2. `SCHEMA_REQUIREMENTS` per category.
3. `RULES` thresholds (title length 40-55 VN vs 50-60 EN).
4. URL list trong `seo_audit_live.sh` (mass-export từ `wp post list --post_type=page --field=url`).

Save 3 scripts vào project's `tools/` folder. Re-run quarterly.

## Tier 2 Python alternative — pure stdlib, không deps (preferred)

Bash heredoc + jq nhanh nhưng fragile với UTF-8 và escape. Python stdlib version sạch hơn, chạy trên macOS/Linux không cần install gì:

```python
#!/usr/bin/env python3
"""SEO Audit Tier 2 — frontend crawl pure stdlib."""
import json, re, time, urllib.request
from pathlib import Path

URLS = [
    "https://example.com/",
    "https://example.com/page-1/",
    # ...
]
UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/124.0 Safari/537.36"

def fetch(url):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    t0 = time.time()
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return resp.getcode(), resp.read().decode("utf-8", errors="replace"), time.time()-t0
    except Exception as e:
        return 0, f"ERROR: {e}", time.time()-t0

def extract(pattern, html, group=1):
    m = re.search(pattern, html, re.IGNORECASE | re.DOTALL)
    return (m.group(group) or "").strip() if m else ""

def analyze(url, status, html, elapsed):
    title = extract(r"<title[^>]*>([^<]*)</title>", html)
    desc = extract(r'<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)', html)
    canonical = extract(r'<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']*)', html)
    og_image = extract(r'<meta[^>]+property=["\']og:image["\'][^>]*content=["\']([^"\']*)', html)
    h1_matches = re.findall(r"<h1[^>]*>(.*?)</h1>", html, re.IGNORECASE | re.DOTALL)
    schema_types = sorted(set(re.findall(r'"@type"\s*:\s*"([A-Za-z]+)"', html)))
    img_tags = re.findall(r"<img\b[^>]*>", html, re.IGNORECASE)
    return {
        "url": url, "status": status, "time_sec": round(elapsed, 3),
        "size_bytes": len(html.encode("utf-8")),
        "title": title, "title_len": len(title),
        "desc": desc, "desc_len": len(desc),
        "canonical": canonical, "og_image": og_image,
        "h1_first": re.sub(r"<[^>]+>", "", h1_matches[0]).strip() if h1_matches else "",
        "h1_count": len(h1_matches),
        "h2_count": len(re.findall(r"<h2[\s>]", html, re.IGNORECASE)),
        "schema_types": schema_types,
        "img_total": len(img_tags),
        "img_no_alt": sum(1 for t in img_tags if not re.search(r'\balt\s*=', t, re.IGNORECASE)),
        "links_internal": len(re.findall(r'href=["\'](/[^"\']+|https://[^"\']*example\.com[^"\']*)["\']', html)),
        "lang": extract(r'<html[^>]+lang=["\']([^"\']*)["\']', html),
        "generator": extract(r'<meta[^>]+name=["\']generator["\'][^>]*content=["\']([^"\']*)', html),
        "inline_css_kb": sum(len(m) for m in re.findall(r"<style[^>]*>.*?</style>", html, re.IGNORECASE | re.DOTALL)) // 1024,
    }

def main():
    out = []
    for i, url in enumerate(URLS, 1):
        print(f"[{i}/{len(URLS)}] {url}", flush=True)
        status, html, elapsed = fetch(url)
        if status >= 400 or not html:
            out.append({"url": url, "status": status, "error": True})
            continue
        out.append(analyze(url, status, html, elapsed))
    Path("seo_audit_live.json").write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")

if __name__ == "__main__":
    main()
```

**Vì sao Python > Bash version:**
- ✅ UTF-8 native — không lo Vietnamese diacritics bị mangled qua bash subshell substitution (xem `pitfalls.md` "Bash `$(curl ...)` corrupts non-ASCII UTF-8")
- ✅ Stdlib only — chạy ngay trên macOS/Linux/Docker, không cần `jq` / `python3-pip`
- ✅ Regex compose dễ hơn — không phải escape `"` trong heredoc
- ✅ JSON encode safe — không cần `printf '%s' | jq -Rs .` workaround
- ✅ Add column mới = sửa 1 dòng dict, không phải sửa 5 chỗ

Battle-tested 2026-05-10: 20 URL, 3 phút runtime, 0 escape bug.

## ⚠️ KHÔNG dùng WebFetch cho SEO data extraction

WebFetch convert HTML → markdown rồi parse → mất nhiều structured data critical cho audit:
- JSON-LD `<script type="application/ld+json">` thường strip
- `<meta property="og:*">` summary lược
- HTML comments (Yoast/Rank Math hint) bỏ
- Multi-H1 đếm sai

**Reproduce thực tế**: WebFetch a home page (WP + Rank Math + Schema enabled) hỏi "extract JSON-LD types" → output "No JSON-LD detected". Curl raw + grep `'"@type"'` → tìm thấy 8 types (Article, BreadcrumbList, ImageObject, Organization, SearchAction, WebPage, WebSite, ...).

**Rule**: SEO audit phải dùng raw HTML parse. WebFetch chỉ cho user-facing content (article body, FAQ).

Đầy đủ: [`references/pitfalls.md`](../references/pitfalls.md) "WebFetch — KHÔNG đáng tin cho SEO data extraction".

## Output dashboard interactive với `data:build-dashboard` skill

Sau khi có `seo_audit_live.json`, build dashboard self-contained HTML với skill `data:build-dashboard`:

```
/build-dashboard SEO Audit Dashboard cho <site> — N page chính.
Input: seo_audit_live.json
Output: dashboard.html (single file, embed data + Chart.js CDN)
Layout:
  - 5 KPI cards (total, healthy, critical, high, medium)
  - Issue summary group theo type
  - Charts: H1 doughnut, title length histogram, page weight bar, TTFB bar
  - Per-page table sortable
  - Recommendations ranked theo priority
```

Dashboard mở được trong browser, send qua Slack/email cho stakeholder không có terminal.

Battle-tested 2026-05-10: dashboard 32KB embed 20 page, render < 100ms, share được link via cloud storage.

## Focus-keyword automation — tags beat title-slice heuristic

After auditing finds posts with missing `rank_math_focus_keyword`, the bulk-fix temptation is to **slice the post title** (first 3–5 words). It produces too many truncated / nonsensical keywords (cụt-câu in Vietnamese: "điều cần lưu ý trước"). The better source: **user-curated WP tags**.

### Why tags beat title heuristics

| Source | Quality signal | Failure mode |
|---|---|---|
| Title slice (first N words) | None — purely positional | Cuts mid-phrase; "Top 20...", "Hướng dẫn..." filler dominates |
| WP tags | Human-curated; tag = topic the author chose | Some tags are too generic ("blog"), some too specific ("thai 12 tuần 3 ngày") — needs scoring |

Real measurement on one site (86 posts, before / after):

| Metric | V1 (title slice) | V2 (tags) |
|---|---:|---:|
| Focus keyword set | 84/86 (97%) | 86/86 (100%) |
| Cụt-câu (truncated meaning) | ~10 cases | 1 case |
| Avg Rank Math SEO score | ~30 | **64.6** |
| Posts scoring ≥70 | unmeasured | 38/86 (44%) |

The score jump (+225% over baseline) is from the keyword now actually matching the content's topical theme, so Rank Math's other on-page checks (keyword in title, in URL, in first paragraph, density) start passing automatically.

### Scoring algorithm

Pick the best tag for each post via a weighted score:

```python
def normalize(s: str) -> str:
    """Lowercase + strip Vietnamese diacritics for comparison."""
    import unicodedata
    return unicodedata.normalize('NFD', s.lower()) \
                      .encode('ascii', 'ignore').decode('ascii')

def has_vn_diacritics(s: str) -> bool:
    return any(0x0300 <= ord(c) <= 0x036F or 0x1EA0 <= ord(c) <= 0x1EF9 for c in s)

def score_tag(tag_name: str, title: str, slug: str, cluster_count: int) -> int:
    """Higher score = better candidate for focus_keyword. Returns -inf if disqualified."""
    score = 0
    tag_norm = normalize(tag_name)
    title_norm = normalize(title)
    tag_words = tag_norm.split()
    title_words = set(title_norm.split())

    # Strong positives
    if tag_norm in title_norm:          score += 10   # tag appears in title (exact-ish)
    if tag_norm in slug:                score += 5    # tag appears in URL slug
    score += 3 * len(set(tag_words) & title_words)    # word overlap

    # Length preference: 2-4 words is the SEO sweet spot
    if 2 <= len(tag_words) <= 4:        score += 2

    # Cluster authority: tags used on many posts are "topic authorities"
    score += min(cluster_count - 1, 5)

    # Penalties — disqualify weak tags
    if len(tag_words) > 6:              score -= 15   # sentence-style tag, not a keyword
    if not has_vn_diacritics(tag_name): score -= 5    # slug-style tag like "3 thang cuoi"

    return score

def pick_best_tag(post_tags: list, title: str, slug: str, tag_post_counts: dict) -> str | None:
    """Return the highest-scoring tag, or None if all are disqualified."""
    if not post_tags:
        return None
    scored = [(score_tag(t, title, slug, tag_post_counts.get(t, 1)), t) for t in post_tags]
    scored.sort(reverse=True)
    best_score, best_tag = scored[0]
    return best_tag if best_score > 0 else None
```

### Bulk-set focus keyword via Rank Math `updateMeta` REST

```python
import json, base64, urllib.request

AUTH = "Basic " + base64.b64encode(f"{USER}:{APP_PW}".encode()).decode()

def set_focus_keyword(post_id: int, keyword: str) -> int:
    payload = {"objectID": post_id, "objectType": "post",
               "meta": {"rank_math_focus_keyword": keyword}}
    req = urllib.request.Request(
        f"{SITE}/wp-json/rankmath/v1/updateMeta",
        data=json.dumps(payload, ensure_ascii=False).encode("utf-8"),
        headers={"Authorization": AUTH, "Content-Type": "application/json"},
        method="POST",
    )
    return urllib.request.urlopen(req, timeout=20).getcode()

# Bulk loop
for post in posts:
    best = pick_best_tag(post['tag_names'], post['title'], post['slug'], tag_counts)
    if best:
        set_focus_keyword(post['id'], best)
        print(f"✓ post {post['id']}: {best}")
    else:
        print(f"⚠ post {post['id']}: no tag passed scoring — manual review")
```

### When this won't work

- **Site has no tags** (or tags are too generic — "blog", "news"). Fall back to title-slice but accept lower quality.
- **English-only site** — drop the `has_vn_diacritics` penalty; the rest of the algorithm works.
- **Heavy taxonomy site** — categories may be a better source than tags. Adjust the loop to read `post.category_names` instead.
- **Manual editorial control** — if the user prefers to pick focus keywords by hand, this automation is wrong. Run it once for the backlog + leave new posts to the editor.

### Verifying the lift

After bulk-set, re-audit Rank Math scores:
```bash
# Use Rank Math's bulk-edit endpoint or per-post fetch
for post_id in $POST_IDS; do
  score=$(curl -u "$U:$P" "$SITE/wp-json/wp/v2/posts/$post_id?_fields=meta&context=edit" \
          | jq -r '.meta.rank_math_seo_score // 0')
  echo "$post_id: $score"
done
```

Compare distribution before / after — the lift comes from the keyword now matching what Rank Math's on-page rules can detect in the rendered HTML.

## Liên quan

- [`references/seo-checklist.md`](../references/seo-checklist.md) — Rank Math meta keys, Schema 3 types, OfferCatalog
- [`references/pitfalls.md`](../references/pitfalls.md) — Astra entry-title H1 (cả duplicate + missing), WebFetch unreliable, plugin redundancy
- [`references/wp-abilities.md`](../references/wp-abilities.md) — direct REST ability cho Tier 1 thay PHP backend dump qua SSH
- [`workflows/clone-transform-pattern.md`](clone-transform-pattern.md) — bulk build → audit cycle
- [`workflows/session-distillation.md`](session-distillation.md) — distill audit insights ngược về skill
