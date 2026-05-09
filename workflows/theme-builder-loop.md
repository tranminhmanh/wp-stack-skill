# Workflow: Theme Builder + Loop Template

For when a CPT needs to be rendered in bulk (e.g. 1000 branches, product list, blog post grid).

## When to use Loop Grid (Pro)

- Render a list of items from a CPT or Posts
- Each item uses the same template
- Need pagination / filter / sort

Do NOT use Loop Grid when:
- Only 3–5 fixed items → build a container by hand
- Items have completely different layouts

## Procedure

### 1. Create a Loop Item template

```
Templates → Theme Builder → Loop Item → Add New
- Type: Loop Item
- Source: pick the CPT (e.g. Branch)
- Conditions: All [post type]
```

### 2. Design the Loop Item

In the template editor:
- Use post-related widgets: Featured Image, Post Title, Post Excerpt, Post Info
- Use Dynamic Tags to bind ACF fields:
  - Heading widget → Dynamic Tag → ACF Field → pick field
- Style as a normal card: container + image + heading + meta + button

### 3. Create an Archive template (if you need a list page)

```
Templates → Theme Builder → Archive → Add New
- Type: Archive
- Conditions: [CPT] Archive
```

In the archive template:
- Page title heading
- Loop Grid widget
- Source: pick the CPT
- Loop template: pick the Loop Item you just made
- Columns: 3 desktop / 2 tablet / 1 mobile
- Posts per page: 12
- Pagination: Numbers

### 4. Single template

```
Templates → Theme Builder → Single → Add New
- Type: Single Post
- Conditions: All Singular [CPT]
```

Design the single page: hero, content, ACF fields, related items.

### 5. Test with Dynamic Preview

In the template editor, the top toolbar has "Preview Settings":
- Choose a specific post → preview with real data
- Make sure the data renders correctly before publishing

### 6. Add filters (advanced, if needed)

Use **JetSmartFilters** or **FacetWP**:
- Add filter widgets to the archive page (province / type / etc.)
- Bind the filter to the Loop Grid query
- Test that AJAX filtering works

## Example mapping for a 1000-branch CPT

```
CPT: branch
ACF fields:
  - province (select: 63 provinces)
  - district (text)
  - address (text)
  - phone (text)
  - hours (textarea)
  - google_maps_url (url)
  - featured_image (built-in)

Loop Item template:
  - Container card padding 24
  - Image (featured)
  - Heading H3 (title — branch name)
  - Meta (district, province) via Dynamic Tag
  - Address text via Dynamic Tag
  - "View details" button linked to single

Archive template (/branches/):
  - Hero "Our Branches"
  - Filter: province dropdown + district
  - Loop Grid: Source=branch, columns 3/2/1, 12/page
  - Pagination

Single template (/branches/<slug>/):
  - Hero with featured image + title
  - Address, phone, hours
  - Google Map embed (iframe from google_maps_url)
  - Related branches in the same province
```

## Theme Builder pitfalls

### 1. Loop Item shows no data
- Check the Source CPT is correct
- Check the ACF field binding via Dynamic Tag
- Reload the editor (close + reopen)

### 2. Conditions not applying
- Settings → Display Conditions → Include: All [CPT]
- Save → publish the template
- Clear cache

### 3. Multiple Loop Item templates conflict
A single CPT should have only one active Loop Item template. If you have several, set specific conditions (e.g. by category) so they don't conflict.

### 4. Pagination not working
- Posts per page > 0
- Pagination type: Numbers / Load More / Infinite Scroll
- Permalink flush after creating a new CPT

### 5. Loop Grid renders slowly with many items
- Limit posts per page ≤ 12
- Disable Elementor lazy-loaded CSS for Loop Grid (improves LCP)
- Use object cache (Redis / Memcached) on the hosting tier
- Cache the page with WP Rocket

## Pitfall: `set-template-conditions` MCP doesn't trigger conditions cache

The MCP `set-template-conditions` writes the `_elementor_conditions` post meta correctly (`include/general` or per-CPT) BUT does NOT update the option `elementor_pro_theme_builder_conditions` (the cache aggregating conditions across all templates). Symptom: `elementor_theme_do_location('header')` returns `false` → header location does not render even though the template has the right conditions.

**Root cause**: MCP only writes the post meta, NOT triggering the `save_post_elementor_library` action hooks (where Elementor Pro registers the cache regen).

**Permanent fix**: mu-plugin auto-regenerating the cache:
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

Or trigger manually after MCP `set-template-conditions`:
```bash
docker exec <c> php -r "
require_once '/var/www/html/wp-load.php';
\\ElementorPro\\Modules\\ThemeBuilder\\Module::instance()->get_conditions_manager()->get_cache()->regenerate();
"
```

## Verify-iterate-fix cycle (REQUIRED)

After every MCP batch (template build, set conditions, update settings):
1. Clear caches (see [`references/performance.md` "Cache invalidation playbook"](../references/performance.md))
2. `curl -sI <preview URL>` → expect 200
3. Visit the page in a browser or take a Chrome MCP screenshot → verify visually
4. If wrong → debug rendered CSS + post meta:
   ```bash
   wp post meta get <template_id> _elementor_conditions
   wp option get elementor_pro_theme_builder_conditions | head -20
   ```
5. Adjust → re-run → re-verify

Average 3–4 iterations for complex Theme Builder layouts. Do NOT batch many template builds and verify at the end — verify after each template so rollback is clean.
