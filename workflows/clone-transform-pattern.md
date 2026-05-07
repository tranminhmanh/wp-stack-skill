# Workflow: Clone + Transform — Bulk-build Elementor pages

Khi cần build N trang có structure ~95% giống nhau (vd: pillar landing 8 country, subpage 50+ cặp cảng, child page legal 3 ngôn ngữ), pattern manual MCP tốn ~45–60 phút/trang. Pattern này clone `_elementor_data` từ template + walk-replace targeted → ~2–10 phút/trang.

## Khi nào áp dụng

✅ Áp dụng khi:
- Đã có 1 trang template build hoàn chỉnh qua MCP (golden master)
- N trang mới có structure giống nhau (≥90%), chỉ khác text/numbers/links
- Per-trang differs: heading text, counter values, table rows, accordion FAQs, schema JSON-LD

❌ KHÔNG áp dụng khi:
- Layout khác hẳn (re-build từ đầu)
- < 3 trang (manual MCP nhanh hơn)

## Quy trình

### 1. Build golden master qua MCP (~45–60 phút lần đầu)

Build trang đầu tiên hoàn chỉnh. LƯU element IDs (7-char hex) của các block sẽ thay đổi:
- Hero H1, subtitle, CTA
- Counter widgets (4–6)
- Industry icon-boxes
- Pain case headings + HTML widgets
- Tip / FAQ accordion tabs
- Transit / pricing table HTML
- Schema JSON-LD HTML widget

Đọc post structure: `mcp__elementor__get_page(page_id)` hoặc `wp post meta get $id _elementor_data`.

### 2. Viết transform PHP script

Dùng helpers từ [`../templates/snippets/elementor-data-update.php`](../templates/snippets/elementor-data-update.php). Skeleton:

```php
<?php
require_once '/var/www/html/wp-load.php';
require_once __DIR__ . '/elementor-data-update.php';

$source_id = 260;
$source_data = json_decode(get_post_meta($source_id, '_elementor_data', true), true);

// 1. Generic text replacements (50–80 pairs)
walk_recursive_replace($source_data, [
    'Hàn Quốc' => 'Nhật Bản',
    'Busan'    => 'Tokyo',
    'KMTC'     => 'ONE Express',
    'tuyen-van-chuyen-vn-han-quoc' => 'tuyen-van-chuyen-vn-nhat-ban',
    // ... ~50 pairs
]);

// 2. Targeted updates by element ID
update_element_by_id($source_data, 'df5f3f6', function (&$el) {
    $el['settings']['title'] = 'Vận chuyển container Việt Nam đi Nhật Bản';
});

// 3. Counter swap by current title (ending_number không unique)
update_counter_by_title($source_data, 'Kim ngạch XK', [
    'ending_number' => 25,
    'title' => 'Kim ngạch XK (tỷ USD)',
]);

// 4. Hash anchor absolutize (nếu copy header/footer section)
absolutize_hash_links($source_data);

// 5. Create new page với required meta
$new_id = create_elementor_page([
    'title'  => 'Vận chuyển container Việt Nam đi Nhật Bản',
    'slug'   => 'tuyen-van-chuyen-vn-nhat-ban',
    'parent' => get_post($source_id)->post_parent,
    'data'   => $source_data,
]);

echo "Created $new_id\n";
```

### 3. Run + verify ngay sau mỗi page

```bash
docker exec <container> php /tmp/transform_pillar.php
# Output: Created post 459

# Verify HTTP 200 + no fatal
URL="https://example.com/tuyen-van-chuyen-vn-nhat-ban?cb=$(date +%s)"
curl -sI "$URL" | head -1
curl -s "$URL" | grep -c '<title>WordPress.*Lỗi\|wp-die-message'
# Phải trả 0 — nếu > 0 → rollback ngay
```

KHÔNG batch nhiều transform rồi mới verify. Verify ngay sau mỗi page để rollback gọn.

### 4. Manual touch-ups (~5 phút)

Visit page trên browser, screenshot:
- Check Hero H1 + counter values
- Check pain case content
- Check schema JSON-LD trong DevTools
- Override gì còn sót

## Time saving (case study)

| Iteration | Source → Target | Time |
|---|---|---|
| Pillar #1 (golden master) | Manual MCP | ~60 phút |
| Pillar #2 | Pillar #1 → Pillar #2 (pattern emerging) | ~45 phút |
| Pillar #3 | Pillar #2 → Pillar #3 (stable) | ~32 phút |
| Pillar #4+ | mature | ~25–30 phút |
| 5 subpages cùng pillar | template subpage → 5 routes | ~75 phút (vs ~5h manual) |

Time saved: ~73–95% sau khi pattern stable.

## Pattern matures only after 2-3 iterations

Build #1 (golden master) thường có 3-5 micro-bugs detect khi iterate sang Build #2-3:
- Missing `_wp_page_template = 'elementor_canvas'` → Astra entry-title H1 duplicate
- HTML escaping inconsistency cho Vietnamese chars
- Schema JSON-LD chưa có FAQPage trong v1
- Counter ending_number wrong format (float vs int)
- Hash anchor links không absolutize

Plan **time cho audit + sync ngược** vào pattern. Tracking:
- Build #1: ~60 phút manual + audit + fix bugs
- Build #2: inherit clean pattern + 3-5 mới phát hiện
- Build #3: pattern stable (~80% bugs fixed)
- Build #4+: marginal cost ~25 phút/page

Sau audit, **update helpers trong `templates/snippets/elementor-data-update.php`** với fixes — pattern chỉ matures khi feedback loop closed.

## Alternative: build-from-scratch with generic helpers

Khi content structure khác hẳn template (vd: blog long-form vs pillar landing page), clone+transform không hiệu quả. Build từ scratch với section helper functions hiệu quả hơn:

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

// Loop N configs → N pages built trong vài giây
foreach ($configs as $cfg) {
    $data = build_blog_data($cfg);
    create_elementor_page([
        'title' => $cfg['title'],
        'slug' => $cfg['slug'],
        'data' => $data,
    ]);
}
```

**Performance**: Build 5 blogs trong ~3 giây vs ~7.5 giờ manual MCP — saving 99%.

**Khi nào prefer over clone+transform**:
- Content structure khác hẳn (blog vs pillar)
- Section count/order varies per page
- Có thể decompose thành reusable section helpers (≥80% page = helpers, <20% per-page custom)

## Bẫy thường gặp

### 1. Vietnamese không match khi str_replace plain string
`_elementor_data` lưu Vietnamese như `\uXXXX` JSON escapes. PHP `str_replace('Hải Phòng', ...)` literal KHÔNG match.

**Fix**: decode JSON → walk recursive replace plain strings → re-encode (`wp_json_encode` auto re-escape Unicode → matches stored format).

### 2. `update_post_meta` strip backslash escapes
WP gọi `wp_unslash()` internally → corrupt JSON khi re-store.

**Fix**: gọi `wp_slash($encoded)` trước khi pass vào `update_post_meta()`. Helper `update_elementor_data()` đã wrap.

### 3. Counter swap không unique by `ending_number`
Nhiều counter có cùng `ending_number: 5` → str_replace không phân biệt.

**Fix**: walk JSON, match by `widgetType === 'counter'` + original `settings.title` text. Helper `update_counter_by_title()`.

### 4. Hash anchor links broken khi copy section sang page khác
Section header/footer dùng `#san-pham` chỉ scroll OK ở homepage. Page con không có section đó → no scroll.

**Fix**: regex transform `href="#xxx"` → `href="/#xxx"` (root-relative). Helper `absolutize_hash_links()` apply 4 vị trí (button link.url, icon-list items, text-editor/HTML inline href).

### 5. Empty `_elementor_edit_mode` → page render broken
Nếu skip meta `_elementor_edit_mode = 'builder'`, WP fallback render với wpautop + wp_kses_post → strip HTML widget classes, divs, spans. Page render plain text thay vì layout Elementor.

Xem [`pitfalls.md`](../references/pitfalls.md) "CRITICAL: edit_mode empty → wpautop". Helper `create_elementor_page()` luôn set đầy đủ.

### 6. Schema JSON-LD price update — escape regex
HTML widget content stored escaped trong `_elementor_data`. Match `"lowPrice":\s*"\d+"` → swap. KHÔNG plain str_replace vì format có space variations.

### 7. Bash heredoc + SSH escape hell
Outer `"..."` của ssh interferes với inner `<<'PHPEOF'` heredoc backslash escaping. Triple-escaped backslashes `\\\\\\` become unpredictable.

**Fix**: `Write` PHP file local → `scp` to remote → `docker cp` into container → `docker exec php /tmp/...`. Avoid all shell escape layering.

### 8. Walk-replace HTML widget trap (multi items in 1 widget)

Khi N items được encode trong **1 HTML widget duy nhất** (grid layout, list inline) — vd 5 cards inline trong 1 `<div class="grid">5 cards</div>` — naive `stripos` first-match replace ENTIRE widget content → mất N-1 items.

**Detection technique**: So sánh `strlen($elementor_data)` before/after — drop đột ngột (vd 17KB→13KB cho 5→1 cards) = bug này.

**Fix**: Detect target widget bằng marker class + REBUILD whole widget với N items:
```php
function walk_replace_grid(&$elements, &$found, $new_full_grid_html) {
    foreach ($elements as &$el) {
        if (($el['widgetType'] ?? '') === 'html'
            && strpos($el['settings']['html'] ?? '', 'sa-blog-coming-grid') !== false) {
            $el['settings']['html'] = $new_full_grid_html;
            $found++;
            return;
        }
    }
}
```

Xem [`pitfalls.md` "Walk-replace HTML widget trap"](../references/pitfalls.md) cho lessons đầy đủ.

## Cross-page internal linking — Add NEW DOM > regex existing DOM

Khi cần inject internal links từ pillar → N subpages (vd 26 cặp cảng), 2 approach:

### Approach 1 (fragile): Regex match existing content

Walk pillar's transit table HTML, regex match `<strong>HCM → Busan</strong>` → wrap với `<a>` tag. Coverage không đủ vì format inconsistent giữa các pillars (build qua khác script):
- `HCM → Tokyo` (matched ✓)
- `HCM → Osaka/Kobe` (slash → fail)
- `HCM → Tanjung Priok` nhưng subpage là `hcm-jakarta` (port name vs city name mismatch)
- `HCM (Cát Lái) → Nhava Sheva (direct)` (extra suffix → fail)

Result: **~42% coverage** trên 5/8 pillars. Không acceptable cho SEO internal linking.

### Approach 2 (winner): Inject explicit cards section

Build 1 NEW container section per pillar, card grid links đến TẤT CẢ subpages by `post_parent`:

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
    $cards .= "<a href='{$url}' class='sa-subpage-card'>...</a>";
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

// Insert before CTA cuối (last section)
array_splice($data, count($data) - 1, 0, [$new_section]);
```

Result: **100% coverage** trên 8/8 pillars + Card UX cleaner than inline links + visible từ any scroll depth.

### Marker class pattern (idempotent re-run)

Re-run script không double-inject:
```php
$exists = false;
array_walk_recursive($data, function ($v) use (&$exists) {
    if (is_string($v) && stripos($v, 'sa-pillar-subpages') !== false) $exists = true;
});
if ($exists) return;  // skip — đã có
```

### Universal lesson: Add NEW DOM > regex existing DOM

Khi cần inject internal links / elements / schema vào content đã có:
- **Regex existing**: phụ thuộc format text → fragile, low coverage, nightmare nếu format vary
- **Add new dedicated section** với marker class: 100% coverage, explicit positioning, easy to update/remove via marker, idempotent re-run

Reusable pattern cho mọi cross-page linking work (related posts, breadcrumbs, child page directories, schema injection).

### Bonus SEO impact

Mỗi pillar inject N inbound `<a href>` đến subpages → strengthen taxonomy crawler signal + cards visible trên-fold của pillar bottom → user CTR tăng. Expected +5–10% organic CTR cho subpages trong 4–6 tuần (Google rebuild internal link graph).

## Verify-iterate-fix cycle

Sau mỗi transform script run:
1. `curl -sI` page URL → expect 200
2. `curl -s | grep` fatal patterns → expect 0
3. Screenshot via Chrome MCP / browser
4. Nếu sai → adjust script → re-run
5. Average 3–4 iterations cho complex layout

Khi build/edit qua MCP rồi screenshot ngay → CSS cũ vẫn cached. Pattern force fresh:
```
URL?fresh=$(date +%s%N)  + Cmd+Shift+R
rm -rf wp-content/cache/* uploads/elementor/css/*
docker exec <c> php -r 'opcache_reset();'
```

## Liên quan

- [`templates/snippets/elementor-data-update.php`](../templates/snippets/elementor-data-update.php) — PHP recipe foundation
- [`references/elementor-mcp.md`](../references/elementor-mcp.md) — file format + verify pattern
- [`references/pitfalls.md`](../references/pitfalls.md) — `_elementor_edit_mode` CRITICAL section + MCP write safety
