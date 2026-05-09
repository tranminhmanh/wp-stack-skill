<?php
/**
 * Elementor data update — safe PHP recipe.
 *
 * Use case: bulk-update _elementor_data via PHP script (clone+transform pattern,
 * post-build cleanup, automated content sync). Avoids 6+ pitfalls discovered
 * across multiple production projects.
 *
 * Reference: workflows/clone-transform-pattern.md, references/pitfalls.md
 */

// ---- 1. Create new Elementor page with all required postmeta ----

/**
 * wp_insert_post() does NOT auto-set Elementor meta. Skipping any one = silent broken render.
 *
 * CRITICAL meta: _elementor_edit_mode = 'builder'
 *   - Empty/missing → WP fallback `the_content` filter applies wpautop + wp_kses_post
 *   - HTML widget classes stripped, <div> and <span> removed, <br> inserted
 *   - Page renders broken plain text instead of Elementor layout
 *
 * CRITICAL: post_author must have the `unfiltered_html` cap. Otherwise wp_kses_post
 *   strips class attributes from <a> tags even when edit_mode=builder.
 *   The default admin user (id=1) has it; subscriber/contributor does NOT.
 */
function create_elementor_page(array $args): int {
    // Pick an author with the unfiltered_html cap (default to admin)
    $admin_id = $args['author'] ?? 1;
    if (!user_can($admin_id, 'unfiltered_html')) {
        throw new RuntimeException("Author $admin_id lacks the unfiltered_html cap");
    }

    $post_id = wp_insert_post([
        'post_title'  => $args['title'],
        'post_status' => $args['status'] ?? 'publish',
        'post_type'   => 'page',
        'post_name'   => $args['slug'],
        'post_parent' => $args['parent'] ?? 0,
        'post_author' => $admin_id,
    ], true);

    if (is_wp_error($post_id)) {
        throw new RuntimeException($post_id->get_error_message());
    }

    // Required meta — skipping any one = silent broken render
    update_post_meta($post_id, '_elementor_data', wp_slash(wp_json_encode($args['data'])));
    update_post_meta($post_id, '_elementor_page_settings', $args['page_settings'] ?? []);
    update_post_meta($post_id, '_elementor_edit_mode', 'builder');           // CRITICAL
    update_post_meta($post_id, '_elementor_template_type', 'wp-page');
    update_post_meta($post_id, '_elementor_version', $args['version'] ?? '4.0.5');
    update_post_meta($post_id, '_wp_page_template', $args['template'] ?? 'elementor_header_footer');
    delete_post_meta($post_id, '_elementor_css');  // clear stale CSS

    return $post_id;
}

// ---- 2. Update _elementor_data safely ----

/**
 * `update_post_meta($id, '_elementor_data', $json)` strips backslashes via
 * wp_unslash() internally. Without wp_slash(), JSON escape sequences corrupt.
 *
 * Symptom: page renders empty, JSON syntax error in the browser console.
 */
function update_elementor_data(int $post_id, array $data): void {
    $encoded = wp_json_encode($data);
    update_post_meta($post_id, '_elementor_data', wp_slash($encoded));  // wp_slash CRITICAL

    // Clear post CSS cache so Elementor regenerates
    delete_post_meta($post_id, '_elementor_css');

    if (class_exists('\\Elementor\\Plugin')) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
}

// ---- 3. Walk-replace text in _elementor_data (non-ASCII safe) ----

/**
 * `_elementor_data` JSON encodes non-ASCII (Vietnamese, Chinese, Japanese, ...) as
 * `\uXXXX` escapes by default. Plain str_replace on the raw JSON string does NOT
 * match non-ASCII text.
 *
 * Pipeline: decode → walk recursive (PHP strings are UTF-8) → re-encode
 * (wp_json_encode auto re-escapes Unicode → matches the stored format).
 */
function walk_recursive_replace(array &$data, array $pairs): void {
    foreach ($data as &$val) {
        if (is_array($val)) {
            walk_recursive_replace($val, $pairs);
        } elseif (is_string($val)) {
            $val = strtr($val, $pairs);
        }
    }
}

/**
 * Find an element by 7-char hex ID anywhere in the nested elements tree, run a callback.
 */
function update_element_by_id(array &$elements, string $id, callable $callback): bool {
    foreach ($elements as &$el) {
        if (isset($el['id']) && $el['id'] === $id) {
            $callback($el);
            return true;
        }
        if (isset($el['elements']) && is_array($el['elements'])) {
            if (update_element_by_id($el['elements'], $id, $callback)) return true;
        }
    }
    return false;
}

/**
 * Counter widget swap — match by widgetType + the current title.
 * `ending_number` alone is not unique (multiple counters may share a value).
 */
function update_counter_by_title(array &$elements, string $current_title, array $new_settings): bool {
    foreach ($elements as &$el) {
        if (
            ($el['widgetType'] ?? '') === 'counter'
            && ($el['settings']['title'] ?? '') === $current_title
        ) {
            foreach ($new_settings as $k => $v) {
                $el['settings'][$k] = $v;
            }
            return true;
        }
        if (isset($el['elements']) && is_array($el['elements'])) {
            if (update_counter_by_title($el['elements'], $current_title, $new_settings)) return true;
        }
    }
    return false;
}

// ---- 4. Hash anchor link absolutize when copying sections cross-page ----

/**
 * A section copied from the homepage to a child page: hash links `#san-pham`
 * stop scrolling because the child page does not have that section.
 *
 * Transform `#xxx` → `/#xxx` so the browser navigates to root + scrolls.
 *
 * Apply to: button settings.link.url, icon-list settings.icon_list[].link.url,
 * text-editor settings.editor HTML, heading/html widget HTML.
 */
function absolutize_hash_links(array &$elements): void {
    foreach ($elements as &$el) {
        if (isset($el['settings']) && is_array($el['settings'])) {
            $s = &$el['settings'];

            // Button widget
            if (isset($s['link']['url']) && is_string($s['link']['url']) && str_starts_with($s['link']['url'], '#')) {
                $s['link']['url'] = '/' . $s['link']['url'];
            }

            // Icon list items
            if (isset($s['icon_list']) && is_array($s['icon_list'])) {
                foreach ($s['icon_list'] as &$item) {
                    if (isset($item['link']['url']) && is_string($item['link']['url']) && str_starts_with($item['link']['url'], '#')) {
                        $item['link']['url'] = '/' . $item['link']['url'];
                    }
                }
            }

            // HTML / text-editor / heading inline href in HTML strings
            foreach (['editor', 'html', 'title'] as $field) {
                if (isset($s[$field]) && is_string($s[$field])) {
                    $s[$field] = preg_replace('/href="#([^"]+)"/', 'href="/#$1"', $s[$field]);
                }
            }
        }

        if (isset($el['elements']) && is_array($el['elements'])) {
            absolutize_hash_links($el['elements']);
        }
    }
}

// ---- 5. Slug clash guard — reuse existing or pick alternative ----

/**
 * WordPress auto-appends `-2` when a slug collides. Symptom: parent slug becomes
 * `-2`, the URL hierarchy `/parent/child/` breaks, SEO + internal links suffer.
 *
 * Use this BEFORE wp_insert_post to detect a collision and reuse the existing post id.
 */
function find_existing_page_by_slug(string $slug, ?int $parent_id = null): ?int {
    $args = [
        'name'        => $slug,
        'post_type'   => 'page',
        'post_status' => 'any',
        'numberposts' => 1,
    ];
    if ($parent_id !== null) {
        $args['post_parent'] = $parent_id;
    }
    $posts = get_posts($args);
    return $posts ? (int) $posts[0]->ID : null;
}

// ---- Example usage ----

/*
require_once '/var/www/html/wp-load.php';
require_once __DIR__ . '/elementor-data-update.php';

$source_id = 260;
$source_data = json_decode(get_post_meta($source_id, '_elementor_data', true), true);

// Generic replacements
walk_recursive_replace($source_data, [
    'Country A' => 'Country B',
    'Port A'    => 'Port B',
    'route-country-a' => 'route-country-b',
]);

// Targeted update by element ID
update_element_by_id($source_data, 'df5f3f6', function (&$el) {
    $el['settings']['title'] = 'Container shipping to Country B';
});

// Counter swap by current title
update_counter_by_title($source_data, 'Annual exports', [
    'ending_number' => 25,
    'title'         => 'Annual exports (USD billion)',
]);

// Slug clash guard
$slug = 'route-country-b';
$existing = find_existing_page_by_slug($slug);
if ($existing) {
    update_elementor_data($existing, $source_data);
    echo "Updated existing $existing\n";
} else {
    $new_id = create_elementor_page([
        'title'  => 'Container shipping to Country B',
        'slug'   => $slug,
        'parent' => get_post($source_id)->post_parent,
        'data'   => $source_data,
    ]);
    echo "Created $new_id\n";
}
*/
