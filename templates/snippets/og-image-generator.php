<?php
/**
 * og-image-generator.php — PHP GD OG image generator + WP attachment integration.
 *
 * Use case: programmatic Open Graph image generation 1200×630 với Vietnamese
 * diacritics-safe text overlay. Branded consistent across N pages without
 * Photoshop/Canva manual work.
 *
 * Reference: workflows/og-image-generation.md
 *
 * Requires: PHP GD with FreeType (TTF support). Verify: `php -m | grep gd`.
 */

// ---- 1. Image generation primitives ----

/**
 * PHP GD KHÔNG có native rounded rectangle. Helper compose từ rect + 4 ellipse corners.
 */
function imagefilledroundedrectangle($im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void {
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
}

/**
 * Vertical gradient between 2 RGB colors.
 */
function gradient_fill($im, int $width, int $height, array $rgb_top, array $rgb_bottom): void {
    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = (int) ($rgb_top[0] + ($rgb_bottom[0] - $rgb_top[0]) * $ratio);
        $g = (int) ($rgb_top[1] + ($rgb_bottom[1] - $rgb_top[1]) * $ratio);
        $b = (int) ($rgb_top[2] + ($rgb_bottom[2] - $rgb_top[2]) * $ratio);
        $color = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $width, $y, $color);
    }
}

// ---- 2. Style A: PHP GD vector-only ----

/**
 * Generate OG image from PHP GD primitives (no AI).
 * Cheap, fast, but limited visual richness.
 */
function generate_og_php_only(string $output_path, array $config): void {
    $width = 1200;
    $height = 630;
    $im = imagecreatetruecolor($width, $height);
    imagesavealpha($im, true);

    // Layer 1: Vertical gradient navy_dark → navy_med
    gradient_fill($im, $width, $height, [10, 37, 64], [30, 58, 92]);

    // Layer 2: Diagonal accent lines (alpha)
    $accent = imagecolorallocatealpha($im, 0, 163, 181, 110);
    imagesetthickness($im, 2);
    for ($i = 0; $i < 8; $i++) {
        $x_start = $i * 200 - 400;
        imageline($im, $x_start, 0, $x_start + $height, $height, $accent);
    }

    // Layer 3: Container ship silhouette
    $silhouette = imagecolorallocate($im, 255, 255, 255);
    $hull = [800, 420, 1180, 420, 1160, 480, 820, 480];
    imagefilledpolygon($im, $hull, $silhouette);
    // 18 stacked containers
    for ($row = 0; $row < 3; $row++) {
        for ($col = 0; $col < 6; $col++) {
            imagefilledrectangle($im,
                830 + $col * 55, 360 + $row * 20,
                880 + $col * 55, 380 + $row * 20,
                $silhouette);
        }
    }

    // Layer 4: TTF text overlay
    $ttf = $config['ttf_path'] ?? '/var/www/html/wp-content/themes/twentytwentythree/assets/fonts/inter/Inter-VariableFont_slnt,wght.ttf';
    if (!is_readable($ttf)) {
        throw new RuntimeException("TTF font not readable: $ttf");
    }

    $white = imagecolorallocate($im, 255, 255, 255);
    $accent_solid = imagecolorallocate($im, 0, 163, 181);

    imagettftext($im, 90, 0, 80, 200, $white, $ttf, $config['brand'] ?? 'Brand');
    imagettftext($im, 32, 0, 80, 270, $accent_solid, $ttf, $config['tagline'] ?? '');
    imagettftext($im, 24, 0, 80, 540, $white, $ttf, $config['cta'] ?? '');
    imagettftext($im, 18, 0, 80, 580, $accent_solid, $ttf, $config['url'] ?? 'example.com');

    imagepng($im, $output_path, 6);
    imagedestroy($im);
}

// ---- 3. Style B: AI background + PHP overlay ----

/**
 * Overlay branded text onto AI-generated background photo.
 * Background photo must be 1200×630 (or larger, will crop center).
 */
function overlay_text_on_bg(string $bg_path, string $output_path, array $config): void {
    $width = 1200;
    $height = 630;

    if (!is_readable($bg_path)) {
        throw new RuntimeException("Background not readable: $bg_path");
    }

    $bg = imagecreatefromjpeg($bg_path) ?: imagecreatefrompng($bg_path);
    if (!$bg) throw new RuntimeException("Failed to decode bg image");

    // Resize/crop to 1200×630 (center crop)
    $bg_w = imagesx($bg);
    $bg_h = imagesy($bg);
    $im = imagecreatetruecolor($width, $height);
    $scale = max($width / $bg_w, $height / $bg_h);
    $new_w = (int) ($bg_w * $scale);
    $new_h = (int) ($bg_h * $scale);
    $crop_x = (int) (($new_w - $width) / 2);
    $crop_y = (int) (($new_h - $height) / 2);
    imagecopyresampled($im, $bg, -$crop_x, -$crop_y, 0, 0, $new_w, $new_h, $bg_w, $bg_h);
    imagedestroy($bg);

    // Layer: navy gradient overlay LEFT 65% (text legibility)
    $overlay = imagecreatetruecolor($width, $height);
    imagealphablending($overlay, false);
    imagesavealpha($overlay, true);
    $transparent = imagecolorallocatealpha($overlay, 0, 0, 0, 127);
    imagefill($overlay, 0, 0, $transparent);
    imagealphablending($overlay, true);

    for ($x = 0; $x < $width * 0.65; $x++) {
        $alpha = (int) (110 * (1 - $x / ($width * 0.65)));  // 110 → 0 fade
        $color = imagecolorallocatealpha($overlay, 10, 37, 64, $alpha);
        imageline($overlay, $x, 0, $x, $height, $color);
    }

    // Bottom subtle dark fade
    for ($y = (int) ($height * 0.7); $y < $height; $y++) {
        $alpha = 127 - (int) (60 * ($y - $height * 0.7) / ($height * 0.3));
        $color = imagecolorallocatealpha($overlay, 0, 0, 0, $alpha);
        imageline($overlay, 0, $y, $width, $y, $color);
    }

    imagecopy($im, $overlay, 0, 0, 0, 0, $width, $height);
    imagedestroy($overlay);

    // Text overlay
    $ttf = $config['ttf_path'] ?? '/var/www/html/wp-content/themes/twentytwentythree/assets/fonts/inter/Inter-VariableFont_slnt,wght.ttf';
    $white = imagecolorallocate($im, 255, 255, 255);
    $accent = imagecolorallocate($im, 0, 163, 181);

    // Badge (rounded rect with category text)
    if (!empty($config['badge'])) {
        $badge_bg = imagecolorallocate($im, 0, 163, 181);
        imagefilledroundedrectangle($im, 60, 80, 280, 130, 6, $badge_bg);
        imagettftext($im, 18, 0, 80, 115, $white, $ttf, $config['badge']);
    }

    // H1 title
    imagettftext($im, 56, 0, 60, 220, $white, $ttf, $config['h1'] ?? '');

    // H2 subtitle
    if (!empty($config['h2'])) {
        imagettftext($im, 32, 0, 60, 290, $white, $ttf, $config['h2']);
    }

    // Tagline
    if (!empty($config['tagline'])) {
        imagettftext($im, 24, 0, 60, 480, $accent, $ttf, $config['tagline']);
    }

    // CTA + URL
    imagettftext($im, 22, 0, 60, 560, $white, $ttf, $config['cta'] ?? '');
    imagettftext($im, 18, 0, 60, 595, $accent, $ttf, $config['url'] ?? '');

    imagepng($im, $output_path, 6);
    imagedestroy($im);
}

// ---- 4. WP attachment integration cho Rank Math og:image ----

/**
 * Register PNG file as WordPress attachment + bind Rank Math og:image meta.
 *
 * CRITICAL: Rank Math REQUIRE attachment ID (không chỉ URL) để render og:image
 * properly. Phải set CẢ `*_image_id` + `*_image` URL.
 */
function register_og_image_for_post(int $post_id, string $file_path, string $alt_text): int {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    // Move file vào wp-content/uploads if not already there
    $upload_dir = wp_upload_dir();
    if (!str_starts_with($file_path, $upload_dir['basedir'])) {
        $filename = basename($file_path);
        $dest = $upload_dir['path'] . '/' . $filename;
        copy($file_path, $dest);
        $file_path = $dest;
    }

    $attach_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => $alt_text,
        'post_content' => '',
        'post_status' => 'inherit',
    ], $file_path, $post_id);

    if (is_wp_error($attach_id)) {
        throw new RuntimeException($attach_id->get_error_message());
    }

    // Generate metadata (sizes, dimensions for og:image:width/height auto-detect)
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);

    // Alt text (for og:image:alt)
    update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);

    // Rank Math meta — set BOTH facebook + twitter
    $url = wp_get_attachment_url($attach_id);
    update_post_meta($post_id, 'rank_math_facebook_image_id', $attach_id);
    update_post_meta($post_id, 'rank_math_facebook_image', $url);
    update_post_meta($post_id, 'rank_math_twitter_image_id', $attach_id);
    update_post_meta($post_id, 'rank_math_twitter_image', $url);

    // Featured image (double coverage — fallback if og meta missed)
    set_post_thumbnail($post_id, $attach_id);

    return $attach_id;
}

/**
 * Tier 2: Inherit parent OG cho subpages.
 */
function inherit_parent_og(array $parent_to_attach_map): int {
    $count = 0;
    $subpages = get_posts([
        'post_type' => 'page',
        'post_parent__in' => array_keys($parent_to_attach_map),
        'numberposts' => -1,
    ]);

    foreach ($subpages as $sub) {
        $attach_id = $parent_to_attach_map[$sub->post_parent] ?? 0;
        if (!$attach_id) continue;

        $url = wp_get_attachment_url($attach_id);
        update_post_meta($sub->ID, 'rank_math_facebook_image_id', $attach_id);
        update_post_meta($sub->ID, 'rank_math_facebook_image', $url);
        update_post_meta($sub->ID, 'rank_math_twitter_image_id', $attach_id);
        update_post_meta($sub->ID, 'rank_math_twitter_image', $url);
        set_post_thumbnail($sub->ID, $attach_id);
        $count++;
    }

    return $count;
}

/**
 * Tier 3: Default site OG fallback (Rank Math global option).
 */
function set_default_site_og(int $homepage_attach_id): void {
    $rm_titles = get_option('rank-math-options-titles', []);
    $url = wp_get_attachment_url($homepage_attach_id);
    $rm_titles['open_graph_image_id'] = $homepage_attach_id;
    $rm_titles['open_graph_image'] = $url;
    $rm_titles['twitter_card_type'] = 'summary_large_image';
    $rm_titles['homepage_facebook_image_id'] = $homepage_attach_id;
    $rm_titles['homepage_facebook_image'] = $url;
    update_option('rank-math-options-titles', $rm_titles);
}

// ---- Example usage ----

/*
require_once '/var/www/html/wp-load.php';
require_once __DIR__ . '/og-image-generator.php';

// Tier 1: Generate unique OG cho pillar
overlay_text_on_bg(
    '/tmp/og-raw/pillar-A-bg.jpg',     // AI-generated background
    '/tmp/og-output/og-pillar-A.png',
    [
        'badge' => 'TUYẾN A',
        'h1' => 'Vận chuyển A',
        'h2' => 'Cước cạnh tranh, transit nhanh',
        'tagline' => 'B2B logistics specialist',
        'cta' => 'Báo giá miễn phí',
        'url' => 'example.com/tuyen-a/',
    ]
);

// Register + bind
$pillar_a_id = 260;
$attach_a = register_og_image_for_post($pillar_a_id, '/tmp/og-output/og-pillar-A.png', 'Vận chuyển tuyến A — Brand');

// Tier 2: Inherit cho subpages
inherit_parent_og([
    260 => $attach_a,
    459 => $attach_b,  // ...
]);

// Tier 3: Default fallback
set_default_site_og($homepage_attach_id);
*/
