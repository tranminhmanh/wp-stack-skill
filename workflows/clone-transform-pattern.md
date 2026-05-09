# Workflow: Clone + Transform — Bulk-build Elementor pages

When you need to build N pages with ~95% identical structure (e.g. 8 country pillar pages, 50+ port-pair subpages, 3-language legal child pages), the manual MCP pattern takes ~45–60 min per page. This pattern clones `_elementor_data` from a template + walk-replace targeted strings → ~2–10 min per page.

## When to apply

✅ Apply when:
- You already have one fully-built template page from MCP (golden master)
- N new pages share ≥90% of the structure, only text / numbers / links differ
- Per-page differences: heading text, counter values, table rows, accordion FAQs, schema JSON-LD

❌ Do NOT apply when:
- Layout differs significantly (rebuild from scratch)
- < 3 pages (manual MCP is faster)

## Procedure

### 1. Build the golden master via MCP (~45–60 min the first time)

Build the first page completely. SAVE element IDs (7-char hex) for the blocks that will change:
- Hero H1, subtitle, CTA
- Counter widgets (4–6)
- Industry icon-boxes
- Pain-case headings + HTML widgets
- Tip / FAQ accordion tabs
- Transit / pricing table HTML
- Schema JSON-LD HTML widget

Read post structure: `mcp__elementor__get_page(page_id)` or `wp post meta get $id _elementor_data`.

### 2. Write the transform PHP script

Use helpers from [`../templates/snippets/elementor-data-update.php`](../templates/snippets/elementor-data-update.php). Skeleton:

```php
<?php
require_once '/var/www/html/wp-load.php';
require_once __DIR__ . '/elementor-data-update.php';

$source_id = 260;
$source_data = json_decode(get_post_meta($source_id, '_elementor_data', true), true);

// 1. Generic text replacements (50–80 pairs)
walk_recursive_replace($source_data, [
    'Country A' => 'Country B',
    'Port A'    => 'Port B',
    'Carrier-A' => 'Carrier-B',
    'route-country-a' => 'route-country-b',
    // ... ~50 pairs
]);

// 2. Targeted updates by element ID
update_element_by_id($source_data, 'df5f3f6', function (&$el) {
    $el['settings']['title'] = 'Container shipping to Country B';
});

// 3. Counter swap by current title (ending_number is not unique)
update_counter_by_title($source_data, 'Annual exports', [
    'ending_number' => 25,
    'title' => 'Annual exports (USD billion)',
]);

// 4. Hash anchor absolutize (when copying header/footer sections)
absolutize_hash_links($source_data);

// 5. Create the new page with required meta
$new_id = create_elementor_page([
    'title'  => 'Container shipping to Country B',
    'slug'   => 'route-country-b',
    'parent' => get_post($source_id)->post_parent,
    'data'   => $source_data,
]);

echo "Created $new_id\n";
```

### 3. Run + verify immediately after each page

```bash
docker exec <container> php /tmp/transform_pillar.php
# Output: Created post 459

# Verify HTTP 200 + no fatal
URL="https://example.com/route-country-b?cb=$(date +%s)"
curl -sI "$URL" | head -1
curl -s "$URL" | grep -c '<title>WordPress.*Error\|wp-die-message'
# Must be 0 — if > 0 → roll back immediately
```

Do NOT batch many transforms then verify at the end. Verify after each page so rollback is clean.

### 4. Manual touch-ups (~5 min)

Visit the page in a browser, screenshot:
- Check Hero H1 + counter values
- Check pain-case content
- Check schema JSON-LD in DevTools
- Override anything still incorrect

## Time savings (case study)

| Iteration | Source → Target | Time |
|---|---|---|
| Page #1 (golden master) | Manual MCP | ~60 min |
| Page #2 | Page #1 → Page #2 (pattern emerging) | ~45 min |
| Page #3 | Page #2 → Page #3 (stable) | ~32 min |
| Page #4+ | mature | ~25–30 min |
| 5 subpages of one pillar | template subpage → 5 routes | ~75 min (vs ~5h manual) |

Time saved: ~73–95% once the pattern is stable.

## Common pitfalls

### 1. Plain str_replace doesn't match non-ASCII text
`_elementor_data` stores non-ASCII (Vietnamese, Chinese, etc.) as `\uXXXX` JSON escapes. PHP `str_replace('Hải Phòng', ...)` literal does NOT match.

**Fix**: decode JSON → walk recursive and replace plain strings → re-encode (`wp_json_encode` auto re-escapes Unicode → matches the stored format).

### 2. `update_post_meta` strips backslash escapes
WP calls `wp_unslash()` internally → corrupts the JSON when re-stored.

**Fix**: call `wp_slash($encoded)` before passing to `update_post_meta()`. Helper `update_elementor_data()` wraps this.

### 3. Counter swap not unique by `ending_number`
Multiple counters may have the same `ending_number: 5` → str_replace cannot distinguish.

**Fix**: walk the JSON, match by `widgetType === 'counter'` + the original `settings.title`. Helper `update_counter_by_title()`.

### 4. Hash anchor links broken when copying a section across pages
A header / footer section using `#san-pham` only scrolls correctly on the homepage. On a child page that does not have that section → no scroll.

**Fix**: regex transform `href="#xxx"` → `href="/#xxx"` (root-relative). Helper `absolutize_hash_links()` covers 4 places (button link.url, icon-list items, text-editor / HTML inline href).

### 5. Empty `_elementor_edit_mode` → page renders broken
If you skip the meta `_elementor_edit_mode = 'builder'`, WP fallback rendering kicks in with wpautop + wp_kses_post → strips HTML widget classes, divs, spans. The page renders as plain text instead of the Elementor layout.

See [`pitfalls.md`](../references/pitfalls.md) "CRITICAL: edit_mode empty → wpautop". Helper `create_elementor_page()` always sets it correctly.

### 6. Schema JSON-LD price update — escape regex
HTML widget content is stored escaped inside `_elementor_data`. Match `"lowPrice":\s*"\d+"` → swap. Plain `str_replace` does not work because the format has whitespace variations.

### 7. Bash heredoc + SSH escape hell
The outer `"..."` of `ssh` interferes with the inner `<<'PHPEOF'` heredoc backslash escaping. Triple-escaped backslashes `\\\\\\` become unpredictable.

**Fix**: `Write` the PHP file locally → `scp` to remote → `docker cp` into the container → `docker exec php /tmp/...`. Avoid all shell-escape layering.

### 8. Walk-replace HTML widget trap (multiple items in one widget)

When N items are encoded inside **a single HTML widget** (grid layout, list inline) — e.g. 5 cards inline in `<div class="grid">5 cards</div>` — naive `stripos` first-match replacement REPLACES the ENTIRE widget content → loses N-1 items.

**Detection**: compare `strlen($elementor_data)` before/after — a sudden drop (e.g. 17KB→13KB for 5→1 cards) is the smoking gun.

**Fix**: detect the target widget by a marker class + REBUILD the whole widget with N items:
```php
function walk_replace_grid(&$elements, &$found, $new_full_grid_html) {
    foreach ($elements as &$el) {
        if (($el['widgetType'] ?? '') === 'html'
            && strpos($el['settings']['html'] ?? '', 'x-blog-coming-grid') !== false) {
            $el['settings']['html'] = $new_full_grid_html;
            $found++;
            return;
        }
    }
}
```

See [`pitfalls.md` "Walk-replace HTML widget trap"](../references/pitfalls.md) for the full lesson.

## Verify-iterate-fix cycle

After each transform-script run:
1. `curl -sI` page URL → expect 200
2. `curl -s | grep` fatal patterns → expect 0
3. Screenshot via Chrome MCP / browser
4. If wrong → adjust the script → re-run
5. Average 3–4 iterations for complex layouts

When building / editing via MCP and immediately screenshotting → old CSS still cached. Force-fresh pattern:
```
URL?fresh=$(date +%s%N)  + Cmd+Shift+R
rm -rf wp-content/cache/* uploads/elementor/css/*
docker exec <c> php -r 'opcache_reset();'
```

## Pattern matures only after 2–3 iterations

Build #1 (golden master) usually has 3–5 micro-bugs that surface when iterating to Build #2-3:
- Missing `_wp_page_template = 'elementor_canvas'` → Astra entry-title H1 duplicate
- HTML escaping inconsistency for non-ASCII chars
- Schema JSON-LD missing FAQPage in v1
- Counter ending_number wrong format (float vs int)
- Hash anchor links not absolutized

Plan **time for audit + sync back** to the pattern. Tracking:
- Build #1: ~60 min manual + audit + fix bugs
- Build #2: inherits the cleaned pattern + 3–5 new findings
- Build #3: pattern stable (~80% of bugs fixed)
- Build #4+: marginal cost ~25 min/page

After the audit, **update helpers in `templates/snippets/elementor-data-update.php`** with fixes — the pattern only matures when the feedback loop closes.

## Alternative: build from scratch with generic helpers

When the content structure differs significantly from the template (e.g. blog long-form vs pillar landing), clone+transform is inefficient. Building from scratch with section-helper functions is more efficient:

```php
function gen_id(): string { return substr(bin2hex(random_bytes(4)), 0, 7); }

function build_hero_section(array $cfg): array { /* return Elementor section structure */ }
function build_counter_strip(array $counters): array { /* 4 counter cards */ }
function build_section_heading(string $title, string $subtitle): array { ... }
function build_cta_section(array $cfg): array { /* gradient navy + buttons */ }
function build_html_section(string $html, int $padding_top, int $padding_bottom, string $bg): array { ... }
function build_schema_section(array $schema): array { /* JSON-LD HTML widget */ }

function build_blog_data(array $cfg): array {
    return [
        build_hero_section($cfg),
        build_counter_strip($cfg['stats']),
        build_section_heading($cfg['intro_title'], $cfg['intro_subtitle']),
        build_html_section($cfg['intro_html'], 64, 64, 'white'),
        // ...
        build_cta_section($cfg),
        build_schema_section($cfg['schema']),
    ];
}

// Loop N configs → N pages built in seconds
foreach ($configs as $cfg) {
    $data = build_blog_data($cfg);
    create_elementor_page([
        'title' => $cfg['title'],
        'slug' => $cfg['slug'],
        'data' => $data,
    ]);
}
```

**Performance**: build 5 blog posts in ~3 seconds vs ~7.5h manual MCP — 99% saved.

**When to prefer over clone+transform**:
- Content structure differs significantly (blog vs pillar)
- Section count / order varies per page
- The work decomposes into reusable section helpers (≥80% page = helpers, <20% per-page custom)

## Cross-page internal linking — Add NEW DOM > regex existing DOM

When you need to inject internal links from a pillar → N subpages (e.g. 26 port pairs), there are 2 approaches:

### Approach 1 (fragile): regex match existing content

Walk the pillar's transit-table HTML, regex-match `<strong>HCM → Busan</strong>` → wrap with an `<a>` tag. Coverage is low because the format is inconsistent across pillars (built by different scripts):
- `HCM → Tokyo` (matches ✓)
- `HCM → Osaka/Kobe` (slash → fail)
- `HCM → Tanjung Priok` but the subpage is `hcm-jakarta` (port name vs city name mismatch)
- `HCM (Cát Lái) → Nhava Sheva (direct)` (extra suffix → fail)

Result: **~42% coverage** across 5/8 pillars. Not acceptable for SEO internal linking.

### Approach 2 (winner): inject an explicit cards section

Build one NEW container section per pillar, with a card grid linking to ALL subpages by `post_parent`:

```php
$subs = get_posts([
    'post_parent' => $pillar_id,
    'post_type' => 'page',
    'numberposts' => -1,
]);

$cards = '';
foreach ($subs as $s) {
    $url = get_permalink($s->ID);
    $label = port_pair_label($s->post_name);  // 'hcm-busan' → 'HCM → Busan'
    $cards .= "<a href='{$url}' class='x-subpage-card'>...</a>";
}

$new_section = [
    'id' => substr(bin2hex(random_bytes(4)), 0, 7),
    'elType' => 'container',
    'settings' => [
        'content_width' => 'boxed',
        'background_color' => '#F8FAFC',
    ],
    'elements' => [[
        'id' => substr(bin2hex(random_bytes(4)), 0, 7),
        'elType' => 'widget',
        'widgetType' => 'html',
        'settings' => ['html' => $cards],
    ]],
];

// Insert before the final CTA
array_splice($data, count($data) - 1, 0, [$new_section]);
```

Result: **100% coverage** across 8/8 pillars + cleaner card UX than inline links + visible at any scroll depth.

### Marker class pattern (idempotent re-runs)

Re-running the script does not double-inject:
```php
$exists = false;
array_walk_recursive($data, function ($v) use (&$exists) {
    if (is_string($v) && stripos($v, 'x-pillar-subpages') !== false) $exists = true;
});
if ($exists) return;  // skip — already there
```

### Universal lesson: Add NEW DOM > regex existing DOM

When you need to inject internal links / elements / schema into existing content:
- **Regex existing**: depends on text format → fragile, low coverage, nightmare if format varies
- **Add a new dedicated section** with a marker class: 100% coverage, explicit positioning, easy to update / remove via the marker, idempotent re-runs

Reusable pattern for any cross-page linking work (related posts, breadcrumbs, child page directories, schema injection).

### Bonus SEO impact

Each pillar gets N inbound `<a href>` to subpages → strengthens taxonomy signals + cards visible above-fold at the pillar bottom → user CTR climbs. Expected +5–10% organic CTR for subpages within 4–6 weeks (Google rebuilds the internal link graph).

## Related

- [`templates/snippets/elementor-data-update.php`](../templates/snippets/elementor-data-update.php) — PHP recipe foundation
- [`references/elementor-mcp.md`](../references/elementor-mcp.md) — file format + verify pattern
- [`references/pitfalls.md`](../references/pitfalls.md) — `_elementor_edit_mode` CRITICAL section + MCP write safety
