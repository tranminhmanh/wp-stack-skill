# Workflow: Tạo Custom Post Type

## Khi nào cần CPT

- Có nhóm content lặp lại với cấu trúc giống nhau (chi nhánh, sản phẩm, dự án, team member, testimonial, FAQ, etc.)
- Cần list/filter/search riêng nhóm này
- Cần template hiển thị riêng (single + archive)

KHÔNG cần CPT nếu:
- Chỉ có 5-10 items không lặp pattern
- Dùng 1 lần (one-off page)

## Cách tạo CPT

### Cách 1: ACF (recommended cho đơn giản)

1. Plugins → ACF → Post Types → Add New
2. Plural label, singular label, slug
3. Supports: title, editor, thumbnail, custom-fields
4. Public: Yes
5. Has archive: Yes
6. Menu position, icon
7. Save

### Cách 2: JetEngine (khi cần relationship)

1. JetEngine → Post Types → Add New
2. Setup tương tự ACF
3. Bonus: relationship với CPT khác (vd: Product belongs to Category)

### Cách 3: Code snippet (advanced)

```php
function register_my_cpt() {
    register_post_type('branch', [
        'labels' => [
            'name' => 'Chi nhánh',
            'singular_name' => 'Chi nhánh',
        ],
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-location',
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        'rewrite' => ['slug' => 'chi-nhanh'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'register_my_cpt');
```

## Sau khi tạo CPT

### 1. Tạo ACF field group cho CPT

Common fields cho location/branch CPT:
- Address (text)
- Phone (text)
- Email (email)
- Opening hours (textarea hoặc repeater)
- Google Maps coordinates (text hoặc Map field nếu có)
- Featured image (đã có support)

Common fields cho product/service CPT:
- Price (number)
- SKU (text)
- Gallery (gallery)
- Specifications (repeater)
- Related products (relationship)

### 2. Build single template trong Elementor Theme Builder

Đọc `workflows/theme-builder-loop.md`.

### 3. Build archive template

Cũng trong Theme Builder:
- Loop Grid widget
- Pagination
- Filter (nếu cần) qua FacetWP hoặc JetSmartFilters

### 4. Permalink flush

Sau khi tạo CPT mới: Settings → Permalinks → Save (không đổi gì) → flush rewrite rules.

### 5. Import data

Nếu có CSV (vd: 1000 chi nhánh):
- WP All Import (recommended)
- Import từ CSV → map fields → ACF fields → run import
- Verify trên admin list

## Bẫy CPT

### 1. Slug conflict
CPT slug không được trùng với:
- Page slug đã có
- Tag/category slug
- Post type khác

### 2. Permalink 404
Sau register CPT phải flush rewrite rules. Nếu vẫn 404: kiểm tra `has_archive: true` và `rewrite: ['slug' => '...']`.

### 3. Show in REST API
Set `show_in_rest: true` để Elementor MCP và Gutenberg đọc được.

### 4. Capabilities
Default CPT chỉ admin sửa được. Để editor sửa được: set capability_type properly.

### 5. CPT không hiện trong Elementor Loop Grid
- Đảm bảo `public: true`
- Đảm bảo có `has_archive: true`
- Reload Elementor editor
- Trong Loop Grid query: chọn Source = CPT vừa tạo
