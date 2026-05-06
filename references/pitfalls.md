# Pitfalls — Bẫy thường gặp toàn stack

## CRITICAL: `_elementor_edit_mode` empty → wpautop strip HTML widget classes

Page có `_elementor_data` đầy đủ + `_wp_page_template = elementor_header_footer` + `_elementor_template_type = wp-page` nhưng nếu **`_elementor_edit_mode` empty/không set**, Elementor KHÔNG fully bootstrap. WordPress fallback rendering kicks in:
- `the_content` filter applied with **wpautop** → adds `<br />` for newlines, wraps content trong `<p>`
- **`wp_kses_post`** applied (nếu post_author không có `unfiltered_html` cap) → strips class attributes from `<a>`, removes `<div>` and `<span>` entirely
- HTML widget content rendered as broken plain text instead of styled markup

**Symptoms cheatsheet** — khi HTML widget render hỏng, check:
- `<p>` wraps content + `<br />` between newlines → wpautop active
- Class attributes stripped from `<a>` tags → wp_kses_post active
- `<div>` và `<span>` tags removed entirely → wp_kses_post stripping disallowed tags
- Custom CSS rules không apply vì target classes không có trong DOM
- Page renders looking like raw HTML5 default styling

**Detection**:
```php
echo get_post_meta($id, '_elementor_edit_mode', true);
// empty string = BROKEN
// 'builder' = correct
```
So sánh với các Elementor page khác — page nào có empty edit_mode là page hỏng.

**Root cause**: page created qua raw `wp_insert_post` (script bulk-create, MCP tools cũ, REST API direct) mà skip `_elementor_edit_mode` meta.

**Fix priority order**:
1. Set `update_post_meta($id, '_elementor_edit_mode', 'builder')` (root cause 90% trường hợp)
2. Check `post_author` có cap `unfiltered_html` (default admin id=1 có)
3. `delete_post_meta($id, '_elementor_css')` để regen CSS
4. mu-plugin override kses cho Elementor pages (backup)

Recipe đầy đủ: [`templates/snippets/elementor-data-update.php`](../templates/snippets/elementor-data-update.php) helper `create_elementor_page()`.

## Elementor MCP (msrbuilds)

### 1. Settings format sai

❌ SAI: `add-heading(settings: {title: "Hello"})`
✅ ĐÚNG (flat): `add-heading(title: "Hello", header_size: "h1")`

Chỉ `add-container`, `update-element`, `update-widget` dùng `settings: {}`.

### 2. Typography không apply

Set `typography_font_size` mà không thấy đổi → thiếu:
```
typography_typography: "custom"
```

### 3. Background color không hiện

Thiếu:
```
background_background: "classic"
```

### 4. Element ID disappear sau edit

Element ID là 7 ký tự hex (vd `f8d1545`). LƯU lại sau mỗi add-*. Nếu mất, gọi `get-page-structure`.

### 5. Cache CSS cũ

Sau loạt edit, gọi `clear_elementor_cache(page_id: 123)`. Không clear → user thấy CSS cũ, tưởng MCP fail.

### 6. Pro widget trên Free

22 widget Pro chỉ chạy nếu Elementor Pro active. Trên Free → lỗi `widget_type_not_found`.

### 7. Elementor 4.0 Atomic Elements

MCP plugin v1.4 chưa support (issue #29). Nếu user dùng Elementor 4.0 → disable Atomic Elements ở Settings → Features hoặc downgrade 3.27.

### 8. Application Password label ≠ username

WordPress hiển thị "label" (vd "Claude MCP"). Nhưng auth dùng **login slug thực** (admin/email-slug). Sai → 401.

### 9. Concurrent edit

User edit page trong Elementor editor cùng lúc Claude Code MCP edit → conflict. User phải đóng Elementor editor trước khi MCP session bắt đầu.

### 10. Connection closed errors

Issue #27. Workaround:
- Restart `claude` session
- Verify endpoint: `curl https://<site>/wp-json/mcp/elementor-mcp-server`
- Check WordPress error log

## Astra

### 1. Cache local font thiếu Vietnamese
Xem vietnamese.md.

### 2. Mobile breakpoint sớm (921px)
Customize → Layout → Container → Mobile breakpoint: 768.

### 3. Header transparent + Elementor hero conflict
Set per-page: Page Settings → Header Style → Transparent.

## ACF / JetEngine

### 1. ACF field bind vào Elementor không update
Sau khi add ACF field mới:
- Save field group
- Reload Elementor editor (close + reopen, không phải refresh)
- Dynamic Tags dropdown mới hiện field

### 2. JetEngine Listing override Theme Builder
Cùng dùng JetEngine Listing và Elementor Theme Builder cho 1 CPT → conflict, JetEngine wins. Pick một.

## Rank Math

### 1. Sitemap không update sau publish
Tools → Database Tools → Update Schema → Update Sitemap.

### 2. Schema duplicate
Astra Local Business schema + Rank Math Local Business → duplicate. Disable Astra schema.

## WP Rocket

### 1. Combine CSS vỡ layout
Disable Combine CSS. HTTP/2 không cần.

### 2. Lazy load hero image gây LCP cao
Hero image: class `no-lazy` hoặc disable lazy load cho `.elementor-section:first-of-type img`.

### 3. Cache không clear sau MCP edit
WP Rocket cache page-level. Sau Claude Code edit:
- Settings → Cache → Clear Cache → All
- MCP `clear_elementor_cache` không đủ

## Cloudflare

### 1. Always Online cache phiên bản cũ
Disable nếu không cần. Hoặc Purge Everything sau deploy.

### 2. Mixed content sau migrate HTTP→HTTPS
SSL: Full (strict) + Auto HTTPS Rewrites ON + Always Use HTTPS ON.
DB còn URL http://: 
```bash
wp search-replace 'http://<site>' 'https://<site>' --skip-columns=guid
```

## Hosting (generic — chi tiết per provider trong deployment.md)

### Disk full do log
```bash
du -sh wp-content/debug.log
```
Nếu > 1GB: truncate hoặc disable WP_DEBUG_LOG production.

### SSL Let's Encrypt renew fail
Check DNS A record point đúng + port 80 mở.

### PHP memory limit thấp
wp-config: `define('WP_MEMORY_LIMIT', '512M');` + provider PHP settings tăng.

## Elementor V4 Layout / CSS pitfalls

### 1. CSS grid trên Elementor `<section>` → squeeze

Brand-CSS (legacy HTML mockup) `display: grid` apply trực tiếp lên `<section>` Elementor → bị squeeze vì section có 1 child duy nhất là `.elementor-container`.

**Fix**: KHÔNG apply `display: grid` lên `<section>`. Apply lên `.elementor-container` con:
```css
section.elementor-section.sec-head { display: block !important; }
section.sec-head > .elementor-container {
  display: grid !important;
  grid-template-columns: minmax(80px,110px) 1fr !important;
}
```

### 2. `width` setting persist khi đổi `container_type: flex` → `grid`

Convert flex 4-col → grid 4-col, cells render cực narrow (~76px) vì leftover `--width: 25%` đè 1fr.

**Fix permanent**: per-cell `update-container(element_id, {"width": ""})`. CSS `!important` KHÔNG thắng inline `--width` đã render. Phải clear field, không chỉ `_flex_size`.

### 3. Container `content_width: "full"` đè flex-grow/shrink children

CSS `width: 100%` thắng flex sizing. Symptom: 4-col layout stack thành 1 col, child container kéo dài full viewport.

**Fix**:
- Để shrink: `content_width: "boxed"` + `_flex_size: "none"` + `_element_width: "initial"`
- Để equal-width grid: dùng `container_type: "grid"` + `grid_columns_grid: {size: 4, unit: "fr"}` (CSS Grid immune to width:100% override)

### 4. `.e-con-boxed.e-flex` hardcoded `flex-direction: column`

Elementor V4 base CSS rule `.e-con-boxed.e-flex { flex-direction: column }` overrides element `--flex-direction: row`. Symptom: flex row container render column.

**Fix**: chuyển `content_width: "full"` + add kit CSS exception cho cells/widgets bên trong:
```css
.elementor-element-XXX > .e-con { max-width: none !important; margin-inline: 0 !important; }
.elementor-element-XXX > .elementor-widget { max-width: none !important; width: auto !important; }
```

### 5. Container `align_items` setting key không apply trên e-flex V4

`align_items: 'center'` lưu OK trong `_elementor_data` nhưng `getComputedStyle` trả `'normal'` (= stretch). Children stretch to row height thay vì shrink to content.

**Fix dứt điểm**: KHÔNG dựa setting key — override CSS với element ID + `!important`:
```css
header .elementor-element-{ID} {
  align-items: center !important;
  justify-content: space-between !important;
  flex-wrap: nowrap !important;
}
header .elementor-element-{ID} > .elementor-element { align-self: center !important; }
```

### 6. `_animation: "fadeInUp"` section stuck invisible

Elementor's intersection observer animation đôi khi không trigger (scroll quickly, JS conflict, theme JS interference). Symptom: scroll xuống section, content không xuất hiện (opacity:0 stuck).

**Workaround**: KHÔNG dùng `_animation` trên section-level. Hoặc CSS `@keyframes` self-trigger khi page load.

### 7. Grid `1fr` cells với explicit `width: 25%` shrinks below 1fr

CSS Grid: nếu grid item có explicit `width`, item respects width within grid cell area. `1fr` cell = 304px, item width:25% = 76px → renders 76px.

**Fix**: clear `width` property trên grid items, để `grid-template-columns` control sizing.

## CSS Cascade / Specificity pitfalls

### 1. `_css_classes` MCP setting save OK nhưng không luôn render to DOM

MCP `update-container` với `_css_classes: "sa-hero-row"` saved correctly trong `_elementor_data` nhưng Elementor V4 render KHÔNG luôn add class to DOM. Chỉ default classes (`elementor-element elementor-element-{ID} e-flex e-con-full`) hiện.

**Workaround**: Target via `.elementor-element-{ID}` selector — class này LUÔN render bởi Elementor:
```css
.elementor-element-XXX { max-width: 1280px !important; ... }
.elementor-element-XXX > .elementor-element-YYY { flex: 1 1 60% !important; ... }
```

100% reliable, tránh debug class-not-rendering.

### 2. `custom_css` không override per-element CSS variables

Elementor render per-element CSS với specificity `.elementor-element-{id}` (0,1,0). Custom CSS với `!important` thắng đa số. NHƯNG inline `--width: 25%` từ Elementor render is processed BEFORE custom_css cascade → CSS variable đã set, override `!important` không reset CSS variable.

**Lesson**: Khi muốn clear leftover settings, edit per-element thay vì rely on global CSS override.

### 3. Override phải reset ĐỦ TẤT CẢ properties đã set

Single property reset không đủ. Vd: nếu global rule set `{max-width: 1280; width: 100%; margin-inline: auto}`, thì override chỉ set `max-width: none` còn `width:100%` + `margin:auto` vẫn áp dụng → flex layout vỡ.

**Fix**: reset cả 3 properties:
```css
.elementor-location-header .e-con-full > .elementor-widget {
  max-width: none;
  width: auto;
  margin-inline: 0;
}
```

Khi viết override, nghĩ "tôi đang override CÁI GÌ" thay vì "tôi muốn cái nào khác".

### 4. `!important` cần thiết khi override Elementor kit's auto-generated CSS

Elementor in-line CSS có higher specificity than custom_css. Vd:
```css
.elementor-location-header > .e-con-full {
  max-width: 1280px !important;  /* cần !important vì --container-max-width var thắng */
}
```

### 5. Specificity war với legacy snippet (chain 8+ classes)

Snippet cũ force `color: var(--navy) !important` trên `.elementor-heading-title` bằng 7-class `:not()` chain — specificity (0,8,1)+`!important`.

**Beat pattern**: Chain 8+ REAL classes (không `:not()`):
```css
body .elementor section.site-header-bar.elementor-section.elementor-top-section
.elementor-element.elementor-widget-heading.hdr-logo-text .elementor-heading-title
```
→ (0,8,2)+`!important`, win.

**Better solution**: Deactivate legacy snippet entirely, merge needed rules vào mu-plugin master CSS.

## MCP write safety

### 11. MCP `return true` ≠ render OK — luôn verify live

`update-page-from-file` trả `true` nhanh, REST 200, modified timestamp đúng — nhưng page có thể render 500 fatal vì payload format sai. Tin vào MCP success tuyệt đối → có thể hỏng nhiều page.

**Fix bắt buộc**: Sau mỗi MCP write op (page update, plugin toggle, option set), verify ngay:
```bash
curl -s "$WP_SITE/<path>?cb=$(date +%s)" -o /tmp/check.html
grep -c '<title>WordPress.*Lỗi\|wp-die-message' /tmp/check.html  # > 0 = fatal, rollback
```
KHÔNG batch nhiều update rồi mới check.

### 12. Sequential MCP, NEVER parallel

PATCH `_elementor_data` ghi đè full page. Parallel MCP write = data race = mất content (last-writer-wins).

**Rule**: MCP write ops phải sequential. Trade-off: chậm nhưng predictable.

### 13. WP slug clash auto-rename `-2`

Khi tạo page với slug đã tồn tại, WP đổi thành `slug-2`. Symptom: `/parent/child/` URL hierarchy broken, parent slug bị `-2`.

**Fix**: query existing trước khi tạo:
```sql
SELECT ID, post_name FROM $wpdb->posts WHERE post_name LIKE 'slug%'
```
REUSE existing post nếu có. Helper `find_existing_page_by_slug()` trong [`elementor-data-update.php`](../templates/snippets/elementor-data-update.php).

### 14. Browser cache trong rapid MCP iteration

Khi build/edit qua MCP rồi screenshot ngay → CSS cũ vẫn cached. Both browser AND server-side.

**Pattern force fresh**:
```bash
# Server-side
rm -rf wp-content/cache/* uploads/elementor/css/*
docker exec <c> php -r 'opcache_reset();'

# Browser
URL?fresh=$(date +%s%N)  + Cmd+Shift+R
```

### 15. Code Snippets plugin: `scope=global` + Elementor API call = site 500

Snippet chạy `\Elementor\Plugin::$instance->files_manager->clear_cache()` ở `priority 1, scope=global, active=1`. Khi `files_manager` là null (chưa init) → fatal trên mọi page load.

**Rules**:
- Luôn wrap dangerous code trong `isset()` / `!empty()` guard
- Set `scope=admin` hoặc single-use (`active=-1`) cho Elementor API calls
- KHÔNG bao giờ chạy Elementor API ở `scope=global` + priority sớm
- Recovery khi snippet crash site: dùng [`templates/snippets/wp-fix.php`](../templates/snippets/wp-fix.php) `?op=disable_all`

### 16. Shared section across pages với hash anchor links

Section header/footer dùng `#san-pham`, `#bang-gia` — anchor tới section trên homepage. Copy section sang child page → `#xxx` không scroll vì child page không có section đó.

**Fix**: transform `#xxx` → `/#xxx` (root-relative). Apply 4 nơi: button `settings.link.url`, icon-list items, text-editor/HTML inline `href` (regex `href="#x"` → `href="/#x"`).

Helper `absolutize_hash_links()` trong [`elementor-data-update.php`](../templates/snippets/elementor-data-update.php).

## CRITICAL: WP option overrides Elementor render

### `page_for_posts` overrides Elementor render

Khi WP có `page_for_posts` set (Settings → Reading → Posts page = This Page), WP **override** Elementor render: page render bằng "Posts archive template" (`home.php` hoặc `index.php`) thay vì `_elementor_data`.

**Triệu chứng**: Build /blog/ Elementor page (post 11), data 17KB stored OK, edit_mode=builder, post_status=publish. Frontend không render Elementor classes — `.sa-blog-categories` returns null trong DOM.

**Detection**:
```php
$page_for_posts = get_option('page_for_posts');
$show_on_front = get_option('show_on_front');
if ($show_on_front === 'page' && $page_for_posts == $post_id) {
    // Page bị override
}
```

**Fix**:
```php
update_option('page_for_posts', 0);  // Unset
// Hoặc move /blog/ slug ra chỗ khác
```

**Pattern preventive**: Khi build "blog hub", "static-front", hoặc trang tên đặc biệt, check WP Settings → Reading TRƯỚC. Các option có thể ẩn override page render mà không có lỗi rõ ràng:
- `page_for_posts` (Posts page)
- `page_on_front` (Static homepage)
- `default_category` (default post category override)

## WordPress nav menu pitfalls

### `nav_menu_item.post_title` SEPARATE từ linked page title

WP nav menu items (`post_type=nav_menu_item`) có **`post_title` RIÊNG** (gọi là "Navigation Label") tách biệt với `post_title` của page được link.

**Triệu chứng**: Update `post_title` của 5 trang menu cho SEO-friendly long titles ("Liên hệ ShipAsia — Tư vấn miễn phí trong 4 giờ" v.v.) → header navigation render full long titles → nav row tràn dòng.

**Root cause**: Khi menu item tạo lần đầu, WP copy page title làm default Navigation Label. Sau đó page title đổi → nav menu KHÔNG tự sync. Nhưng nếu menu item bị "auto-update" (reset menu, plugin migration, save quirk) → bị overwrite lại.

**Fix**: Update `wp_posts.post_title` cho menu items trực tiếp:
```php
$menu_items = wp_get_nav_menu_items('menu-chinh');
$labels = ['lien-he' => 'Liên hệ', 've-chung-toi' => 'Về chúng tôi'];

foreach ($menu_items as $item) {
    $linked_id = get_post_meta($item->ID, '_menu_item_object_id', true);
    $linked_post = get_post($linked_id);
    if (isset($labels[$linked_post->post_name])) {
        wp_update_post([
            'ID' => $item->ID,
            'post_title' => $labels[$linked_post->post_name],
        ]);
    }
}
```

**Best practice**: SEO long title cho `<title>` tag + breadcrumb, short clean label cho nav UI. Khi update SEO long titles, LUÔN check + update nav menu labels riêng.

**Menu item postmeta keys** (reference):
- `_menu_item_object_id` — ID của post/page/term được link
- `_menu_item_object` — type: `'page'`, `'post'`, `'category'`, `'custom'`
- `_menu_item_type` — `'post_type'` / `'taxonomy'` / `'custom'`
- `_menu_item_url` — URL nếu type='custom'
- `_menu_item_target` — `'_blank'` hoặc empty
- `_menu_item_classes` — array of CSS classes
- `_menu_item_menu_item_parent` — parent menu item ID (cho submenu)

## More CSS Cascade pitfalls

### CSS attribute selector: `[class*=""]` substring vs `[class~=""]` word match

`[class*="-dark"]` substring match có thể match cả `not-dark` hoặc `xx-dark-yy` — false positive.

**Triệu chứng**: Snippet legacy có rule `:not([class*="-dark"])` để exclude dark sections — class chuẩn của design system là `dark` (không dash). Khi inject section `class="dark"`, legacy rule vẫn match → heading navy invisible trên bg navy.

**Fix**: Dùng `[class~="dark"]` word match (exact word, space-separated) cho exact class:
```css
/* WRONG: matches "not-dark", "darkness", etc. */
:not([class*="dark"]) { color: navy !important; }

/* RIGHT: matches only standalone class "dark" */
:not([class~="dark"]) { color: navy !important; }
```

**Bonus pitfall**: Class propagation across nested sections. Elementor structure thường: `outer-section.dark → column → inner-section (no class) → column → widget`. Heading's `.closest('section')` là inner-section KHÔNG có `.dark` → `:not([class~="dark"])` vẫn match → vẫn ăn navy.

**Fix**: walk tree, propagate `.dark` class xuống mọi nested section bên trong outer-dark.

## Astra theme pitfalls

### Astra `entry-title` H1 duplicate Elementor H1

**Triệu chứng**: Pages có 2 H1 trên rendered HTML:
```html
<h1 class="entry-title" itemprop="headline">[page title]</h1>      ← Astra theme inject
<h1 class="elementor-heading-title">[heading widget]</h1>           ← Elementor render
```

**Root cause**: Pages built mà không set `_wp_page_template = 'elementor_canvas'` → fallback to Astra `single.php` template → render entry-title H1 trước Elementor data.

**Fix**: `update_post_meta($id, '_wp_page_template', 'elementor_canvas')` → Astra skip render. Trong mọi PHP build script, MUST include:
```php
update_post_meta($post_id, '_wp_page_template', 'elementor_canvas');
update_post_meta($post_id, '_elementor_edit_mode', 'builder');
update_post_meta($post_id, '_elementor_template_type', 'wp-page');
```

Helper `create_elementor_page()` trong [`templates/snippets/elementor-data-update.php`](../templates/snippets/elementor-data-update.php) đã set đầy đủ.

## Internal link integrity pitfalls

### Slug freeze early + post-build CI verify all internal links

**Triệu chứng**: Build homepage Tuần 2 dùng URL placeholder `viet-nam-X` (descriptive). Tuần 3+ build pillars với slug simplified `vn-X`. Homepage URLs KHÔNG được update đồng bộ → 8 dead pillar links 404 → 6 tháng leak link equity.

**Impact**: Trang chủ là page có link equity cao nhất site. Leak toàn bộ power qua URLs chết → crawler waste budget on 404s, link juice bay vào hư không.

**Fix**: Walk Elementor data str_replace per-link cho all dead URL pairs:
```php
foreach ($el['settings'] as &$setting) {
    foreach (['html', 'editor', 'title'] as $field) { /* str_replace */ }
}
if (isset($settings['link']['url']))    { /* str_replace */ }
if (isset($settings['link_to']['url'])) { /* str_replace */ }
```

**Lessons CRITICAL**:
1. **CI check post-build**: viết script verify ALL internal links return 200, run sau mỗi build. Xem [`workflows/seo-audit.md`](../workflows/seo-audit.md) "Always verify HTTP code".
2. **Slug freeze early**: quyết định slug convention TRƯỚC khi build trang nào → pages khác inherit cùng pattern.
3. **Audit script phải verify HTTP code của internal links**, không chỉ count.

## PHP bulk-update pitfalls

### Walk-replace HTML widget trap (multi items in 1 widget)

**Triệu chứng**: Blog hub `_elementor_data` 17KB → drop 13KB (mất 4KB) sau khi chạy update script → kết quả chỉ còn 1 card hiển thị thay vì 5.

**Root cause**: 5 cards encoded trong **1 HTML widget duy nhất** (Elementor build chỉ tạo 1 widget chứa full grid `<div class="grid">5 cards inline</div>`). Function walk_replace dùng `stripos` first-match:
```php
foreach ($replacements as $key => $rep) {
    if (stripos($h, $key) !== false) {
        $el['settings']['html'] = $new_card;  // ← REPLACES WHOLE WIDGET với 1 card
        break;
    }
}
```
Match đầu tiên replace ENTIRE widget content → mất 4 cards còn lại.

**Fix**: Detect target widget bằng marker class + REBUILD whole widget với N items:
```php
function walk_replace_grid(&$elements, &$found, $new_full_grid_html) {
    foreach ($elements as &$el) {
        if (($el['widgetType'] ?? '') === 'html'
            && strpos($el['settings']['html'] ?? '', 'sa-blog-coming-grid') !== false) {
            $el['settings']['html'] = $new_full_grid_html;  // Full new grid
            $found++;
            return;
        }
    }
}
```

**Detection technique**: So sánh `strlen($elementor_data)` before/after — drop đột ngột (17KB→13KB cho 5→1 cards) là dấu hiệu bug này.

**Lesson**: Khi multiple items được encode trong 1 HTML widget (grid layout, list inline...), MUST:
- Rebuild whole widget thay vì targeted replace per item
- Detect target widget bằng marker class (existing class) tồn tại + missing new marker
- Always check Elementor data size before/after để detect data loss bugs
