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
