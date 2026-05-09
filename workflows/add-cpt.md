# Workflow: Create a Custom Post Type

## When you need a CPT

- Recurring content with a similar structure (branches, products, projects, team members, testimonials, FAQ, etc.)
- You need a separate list / filter / search for this group
- You need a separate display template (single + archive)

You do NOT need a CPT if:
- Only 5–10 items without a repeating pattern
- One-off page

## How to create a CPT

### Option 1: ACF (recommended for simple cases)

1. Plugins → ACF → Post Types → Add New
2. Plural label, singular label, slug
3. Supports: title, editor, thumbnail, custom-fields
4. Public: Yes
5. Has archive: Yes
6. Menu position, icon
7. Save

### Option 2: JetEngine (when relationships needed)

1. JetEngine → Post Types → Add New
2. Setup similar to ACF
3. Bonus: relationships with other CPTs (e.g. Product belongs to Category)

### Option 3: Code snippet (advanced)

```php
function register_my_cpt() {
    register_post_type('branch', [
        'labels' => [
            'name' => 'Branches',
            'singular_name' => 'Branch',
        ],
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-location',
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        'rewrite' => ['slug' => 'branches'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'register_my_cpt');
```

## After creating the CPT

### 1. Create an ACF field group for the CPT

Common fields for a location / branch CPT:
- Address (text)
- Phone (text)
- Email (email)
- Opening hours (textarea or repeater)
- Google Maps coordinates (text or Map field if available)
- Featured image (already supported)

Common fields for a product / service CPT:
- Price (number)
- SKU (text)
- Gallery (gallery)
- Specifications (repeater)
- Related products (relationship)

### 2. Build a single template in Elementor Theme Builder

See `workflows/theme-builder-loop.md`.

### 3. Build an archive template

Also in Theme Builder:
- Loop Grid widget
- Pagination
- Filter (if needed) via FacetWP or JetSmartFilters

### 4. Permalink flush

After registering the new CPT: Settings → Permalinks → Save (no changes) → flush rewrite rules.

### 5. Import data

If you have a CSV (e.g. 1000 branches):
- WP All Import (recommended)
- Import from CSV → map fields → ACF fields → run import
- Verify in the admin list

## CPT pitfalls

### 1. Slug conflict
The CPT slug cannot collide with:
- An existing page slug
- A tag / category slug
- Another post type

### 2. Permalink 404
After registering a CPT, you must flush rewrite rules. If still 404: check `has_archive: true` and `rewrite: ['slug' => '...']`.

### 3. Show in REST API
Set `show_in_rest: true` so Elementor MCP and Gutenberg can read it.

### 4. Capabilities
By default only admins can edit a CPT. To let editors edit too: set `capability_type` properly.

### 5. CPT not showing in Elementor Loop Grid
- Make sure `public: true`
- Make sure `has_archive: true`
- Reload the Elementor editor
- In the Loop Grid query: pick Source = your new CPT
