# Workflow: SEO Audit (3-tier pattern)

Bulk audit N WordPress pages cho SEO health: title/meta/H1 hierarchy/schema/canonical/internal links/og:image. Pattern proven trên ShipAsia 52 pages, ~30 phút audit (vs ~1 tuần manual GUI Rank Math).

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

## Liên quan

- [`references/seo-checklist.md`](../references/seo-checklist.md) — Rank Math meta keys, Schema 3 types, OfferCatalog
- [`references/pitfalls.md`](../references/pitfalls.md) — Astra entry-title H1 duplicate, slug freeze, dead pillar links
- [`workflows/clone-transform-pattern.md`](clone-transform-pattern.md) — bulk build → audit cycle
