# Workflow: Theme Builder + Loop Template

Áp dụng khi có CPT cần render hàng loạt (vd: 1000 chi nhánh, danh sách sản phẩm, blog post grid).

## Khi nào dùng Loop Grid (Pro)

- Render danh sách items từ CPT/Posts
- Mỗi item dùng cùng template
- Cần pagination/filter/sort

KHÔNG dùng Loop Grid khi:
- Chỉ có 3-5 items cố định → build tay container
- Items khác nhau hoàn toàn về layout

## Quy trình

### 1. Tạo Loop Item template

```
Templates → Theme Builder → Loop Item → Add New
- Type: Loop Item
- Source: chọn CPT (vd: Branch)
- Conditions: All [post type]
```

### 2. Design Loop Item

Trong template editor:
- Dùng widget post-related: Featured Image, Post Title, Post Excerpt, Post Info
- Dùng Dynamic Tags để bind ACF fields:
  - Heading widget → Dynamic Tag → ACF Field → chọn field
- Style như card thường: container + image + heading + meta + button

### 3. Tạo Archive template (nếu cần page list)

```
Templates → Theme Builder → Archive → Add New
- Type: Archive  
- Conditions: [CPT] Archive
```

Trong archive template:
- Page title heading
- Loop Grid widget
- Source: chọn CPT
- Loop template: chọn Loop Item vừa tạo
- Columns: 3 desktop / 2 tablet / 1 mobile
- Posts per page: 12
- Pagination: Numbers

### 4. Single template

```
Templates → Theme Builder → Single → Add New
- Type: Single Post
- Conditions: All Singular [CPT]
```

Design single page: hero, content, ACF fields, related items.

### 5. Test với Dynamic Preview

Trong template editor, top toolbar có "Preview Settings":
- Choose specific post → preview với data thật
- Đảm bảo data render đúng trước khi publish

### 6. Add filter (advanced, nếu cần)

Dùng **JetSmartFilters** hoặc **FacetWP**:
- Tạo filter widget trên archive page (Tỉnh/Thành, Loại, etc.)
- Bind filter với Loop Grid query
- Test AJAX filter hoạt động

## Mapping cho BMMH 1000 chi nhánh (ví dụ)

```
CPT: branch
ACF fields:
  - province (select: 63 tỉnh thành)
  - district (text)
  - address (text)
  - phone (text)
  - hours (textarea)
  - google_maps_url (url)
  - featured_image (built-in)

Loop Item template:
  - Container card padding 24
  - Image (featured)
  - Heading H3 (title - tên chi nhánh)
  - Meta (district, province) qua Dynamic Tag
  - Address text qua Dynamic Tag
  - Button "Xem chi tiết" link to single

Archive template (chi-nhanh/):
  - Hero "Hệ thống chi nhánh"
  - Filter: dropdown 63 tỉnh + district
  - Loop Grid: Source=branch, columns 3/2/1, 12/page
  - Pagination

Single template (chi-nhanh/<slug>/):
  - Hero với featured image + title
  - Address, phone, hours
  - Google Map embed (iframe từ google_maps_url)
  - Related branches cùng tỉnh
```

## Bẫy Theme Builder

### 1. Loop Item không hiển thị data
- Check Source CPT đúng chưa
- Check ACF field binding qua Dynamic Tag
- Reload editor (close + reopen)

### 2. Conditions không apply
- Settings → Display Conditions → Include: All [CPT]
- Save → publish template
- Clear cache

### 3. Multiple Loop Item template conflict
1 CPT chỉ nên có 1 Loop Item template active. Nếu có nhiều, set conditions cụ thể (vd: by category) để không conflict.

### 4. Pagination không hoạt động
- Posts per page > 0
- Pagination type: Numbers / Load More / Infinite Scroll
- Permalink flush sau tạo CPT mới

### 5. Loop Grid render chậm khi nhiều items
- Limit posts per page ≤ 12
- Disable lazy load CSS Elementor cho Loop Grid (tăng LCP)
- Use object cache (Redis/Memcached) tier hosting
- Cache page với WP Rocket

## Bẫy: `set-template-conditions` MCP không trigger conditions cache

MCP `set-template-conditions` ghi `_elementor_conditions` post meta đúng (`include/general` hoặc per-CPT) NHƯNG KHÔNG update option `elementor_pro_theme_builder_conditions` (cache aggregated cho tất cả templates). Symptom: `elementor_theme_do_location('header')` trả `false` → header location không render dù template có conditions đúng.

**Root cause**: MCP chỉ ghi post meta, KHÔNG trigger `save_post_elementor_library` action hooks (Elementor Pro register cache regen ở đó).

**Fix permanent**: mu-plugin auto-regenerate cache:
```php
<?php
// wp-content/mu-plugins/elementor-conditions-cache-fix.php
add_action('save_post_elementor_library', function ($post_id) {
    if (function_exists('elementor_pro_load_plugin')) {
        $manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
        if (method_exists($manager, 'get_cache')) {
            $manager->get_cache()->regenerate();
        }
    }
}, 99);
```

Hoặc trigger manual sau MCP set-template-conditions:
```bash
docker exec <c> php -r "
require_once '/var/www/html/wp-load.php';
\\ElementorPro\\Modules\\ThemeBuilder\\Module::instance()->get_conditions_manager()->get_cache()->regenerate();
"
```

## Verify-iterate-fix cycle (BẮT BUỘC)

Sau mỗi MCP batch (build template, set conditions, update settings):
1. Clear caches (xem [`references/performance.md` "Cache invalidation playbook"](../references/performance.md))
2. `curl -sI <preview URL>` → expect 200
3. Visit page trong browser hoặc Chrome MCP screenshot → verify visual
4. Nếu sai → debug rendered CSS + post meta:
   ```bash
   wp post meta get <template_id> _elementor_conditions
   wp option get elementor_pro_theme_builder_conditions | head -20
   ```
5. Adjust → re-run → re-verify

Average 3–4 iterations cho complex Theme Builder layouts. KHÔNG batch nhiều template build rồi mới verify — verify ngay sau mỗi template để rollback gọn.
