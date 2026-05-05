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
