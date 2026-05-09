# SEO Checklist — Rank Math setup

## Initial install

1. Install Rank Math Free
2. Setup Wizard:
   - Site type: Business / Personal blog / Community blog
   - Logo & default social image
   - Connect Google Search Console
   - Connect Google Analytics 4

## Critical settings

`Rank Math → General Settings → Sitemap`
- XML Sitemap: ON
- Include images: ON
- Items per sitemap: 200
- Exclude: tag pages, author pages (unless blog-heavy)

`Rank Math → General Settings → Open Graph`
- Default OG image: 1200×630 brand image
- Twitter card: `summary_large_image`

`Rank Math → Titles & Meta`
- Homepage: `[Brand] — [Tagline]`
- Posts: `%title% %sep% %sitename%`
- Archive: noindex (unless this category matters)
- Author: noindex (unless blog-heavy)
- Search: noindex
- 404: noindex

`Rank Math → Local SEO` (for sites with a physical location)
- Person / Organization: Organization
- Address, phone, email, opening hours
- Knowledge graph type: LocalBusiness
- Maps API key (optional)

## Per-page SEO when building with Elementor

For each landing page built via MCP:
1. Set focus keyword
2. Title tag: 50–60 chars, keyword first
3. Meta description: 150–160 chars, with a CTA
4. URL slug: short, with keyword, NO non-Latin chars
5. H1 unique, with keyword
6. Alt text on every image
7. Schema: LocalBusiness / Service / Product per the page

## Vietnamese SEO

See [`vietnamese.md`](vietnamese.md) for full details (slug rules, title length 40–55 chars, hreflang `vi-VN`, etc.).

## Common SEO issues

| Issue | Fix |
|---|---|
| Duplicate title tags | Disable noindex on archive pages |
| Missing alt text | Bulk fix via Rank Math image SEO |
| Slow LCP | See `performance.md` |
| Thin content | Min 600 words per page |
| No internal links | Each page links to 3–5 others |
| No schema | Rank Math Schema tab per page |
| Sitemap not updating | Tools → Database Tools → Update Sitemap |
| Schema duplicate (Astra + Rank Math) | Disable Astra schema |

## Pillar / landing page Schema 3-types pattern

Maximize SERP coverage for long-tail keywords + commercial intent. Inject 3 schema types via an HTML widget with `<script type="application/ld+json">`:

### 1. BreadcrumbList
```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type":"ListItem", "position":1, "name":"Home", "item":"https://example.com/"},
    {"@type":"ListItem", "position":2, "name":"Routes", "item":"https://example.com/routes/"},
    {"@type":"ListItem", "position":3, "name":"Route A", "item":"https://example.com/routes/route-a/"}
  ]
}
```

### 2. Service / AggregateOffer
```json
{
  "@context": "https://schema.org",
  "@type": "Service",
  "name": "Service name",
  "provider": {"@type":"Organization", "name":"Brand", "url":"https://example.com/"},
  "areaServed": [{"@type":"Country","name":"Country A"}, {"@type":"Country","name":"Country B"}],
  "offers": {
    "@type": "AggregateOffer",
    "priceCurrency": "USD",
    "lowPrice": "750",
    "highPrice": "950",
    "offerCount": "12"
  }
}
```

### 3. FAQPage

Auto-generated from the Elementor Accordion widget when `faq_schema: "yes"` is set. Each tab → `Question` + `Answer`. No manual injection needed if the accordion already has it.

### Hide the visual of the schema HTML widget
```css
.x-schema-only { display: none; }
```
Schema is crawled in the DOM by Google, no need to render it visibly.

## Bulk Schema price update via regex

When prices change quarterly, HTML widget content is stored escaped inside `_elementor_data`. Plain `str_replace` does not work because of whitespace variations:

```php
// Match `"lowPrice": "750"`, `"lowPrice":"750"`, `"lowPrice" : "750"`
$updated = preg_replace_callback(
    '/"lowPrice"\s*:\s*"(\d+)"/',
    fn($m) => '"lowPrice": "' . $new_low . '"',
    $html_widget_content
);
$updated = preg_replace_callback(
    '/"highPrice"\s*:\s*"(\d+)"/',
    fn($m) => '"highPrice": "' . $new_high . '"',
    $updated
);
```

Apply the same pattern across subpages of the same pillar (5–10 routes) within the same script.

## Bulk Rank Math meta via post_meta (no GUI)

Rank Math stores all SEO meta as `post_meta` keys. Bulk-set via PHP is instant — no need to navigate the GUI 52 times:

| Meta key | Purpose | Override |
|---|---|---|
| `rank_math_title` | `<title>` tag | Overrides post_title |
| `rank_math_description` | meta description | — |
| `rank_math_focus_keyword` | primary keyword for on-page analysis | — |
| `rank_math_canonical_url` | canonical URL | Default = self |
| `rank_math_robots` | array `['index', 'follow', 'noarchive', ...]` | — |
| `rank_math_facebook_image_id` | OG attachment ID | Required for og:image to render |
| `rank_math_facebook_image` | OG image URL | Set together with `_id` |
| `rank_math_twitter_image_id` | Twitter card image ID | — |
| `rank_math_twitter_image` | Twitter card image URL | — |

```php
// Bulk-set across N pages
foreach ($pages as $page) {
    update_post_meta($page->ID, 'rank_math_title', $page->seo_title);
    update_post_meta($page->ID, 'rank_math_description', $page->seo_desc);
    update_post_meta($page->ID, 'rank_math_focus_keyword', $page->keyword);
}
```

## Rank Math meta NOT exposed via REST — one-shot mu-plugin pattern

`PATCH /wp/v2/pages/{id}` with `meta: {rank_math_description: "..."}` returns HTTP 200 but the meta does NOT change. Rank Math does not register `show_in_rest=true` on its meta keys, so the REST endpoint silently ignores the update.

The same pattern applies to any third-party meta with `show_in_rest=false` (ACF private fields, custom plugin meta, hidden post meta).

**Workaround**: token-guarded one-shot mu-plugin that calls `update_post_meta()` directly, runs once, then stubs itself.

```php
// wp-content/mu-plugins/_oneshot.php (deploy → hit URL → stub)
<?php
add_action('init', function () {
    if (($_GET['_oneshot_token'] ?? '') !== 'STRONG-TOKEN-HERE') return;

    // Apply the targeted meta updates
    update_post_meta(36, 'rank_math_description', 'Clean meta description without artifacts.');
    update_post_meta(37, 'rank_math_focus_keyword', 'target keyword');
    // ... add more updates here

    echo "OK\n";
    exit;
});
```

Trigger:
```bash
curl "https://example.com/?_oneshot_token=STRONG-TOKEN-HERE"
# Output: OK
```

Stub the file immediately after (Fileman API on shared hosts has no delete → overwrite with empty stub):
```bash
echo '<?php // disabled' > wp-content/mu-plugins/_oneshot.php
```

**Reusable for**:
- Rank Math meta (`rank_math_*`)
- ACF private fields (when `show_in_rest=false` on the field group)
- Third-party plugin meta hidden from REST
- WP options that aren't in the registered allowlist

When you need direct DB access without SSH, this pattern bridges the gap.

## Inject Schema markup into `_elementor_data` via PHP

When you need to add schema (Service, BlogPosting, FAQPage) to pages already built via MCP:

```php
$schema_html = '<script type="application/ld+json">'
             . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
             . '</script>';

$new_widget = [
    'id' => substr(md5(uniqid('', true)), 0, 7),
    'elType' => 'container',
    'settings' => ['content_width' => 'boxed'],
    'elements' => [[
        'id' => substr(md5(uniqid('', true)), 0, 7),
        'elType' => 'widget',
        'widgetType' => 'html',
        'settings' => ['html' => $schema_html],
    ]],
];

$data = json_decode(get_post_meta($id, '_elementor_data', true), true);
$data[] = $new_widget;  // Append at end
update_post_meta($id, '_elementor_data', wp_slash(json_encode($data, JSON_UNESCAPED_UNICODE)));
```

Hide via CSS: `.x-schema-only { display: none; }` on the widget container — Google crawls the DOM, no need to render visibly.

## Schema OfferCatalog for grouped services

When a hub page contains multiple "items" belonging to the same main service (e.g. 8 routes belonging to one shipping service, 5 sub-services of one hub), a flat list is worse than `OfferCatalog`:

**Flat (worse)**:
```json
[
  {"@type":"Service", "name":"Service A", ...},
  {"@type":"Service", "name":"Service B", ...}
]
```

**OfferCatalog (better)** — Google understands the structure "N items of one main service":
```json
{
  "@type": "Service",
  "name": "Main Service",
  "areaServed": ["Country1", "Country2"],
  "hasOfferCatalog": {
    "@type": "OfferCatalog",
    "name": "N sub-services",
    "itemListElement": [
      {
        "@type": "Offer",
        "itemOffered": {"@type": "Service", "name": "Sub A", "url": "..."}
      }
    ]
  }
}
```

Eligible for **Sitelinks Search Box** + structured product / service rich result in SERP.

## OG image attachment integration (Rank Math)

⚠️ Rank Math **REQUIRES the attachment ID** (not just the URL) to render `og:image` properly.

```php
// Wrong: only URL → og:image meta does not render
update_post_meta($post_id, 'rank_math_facebook_image', 'https://example.com/og.png');

// Right: attachment ID + URL together
$attach_id = wp_insert_attachment([...], $file_path);
wp_generate_attachment_metadata($attach_id, $file_path);
update_post_meta($attach_id, '_wp_attachment_image_alt', 'Descriptive alt');

update_post_meta($post_id, 'rank_math_facebook_image_id', $attach_id);
update_post_meta($post_id, 'rank_math_facebook_image', wp_get_attachment_url($attach_id));
update_post_meta($post_id, 'rank_math_twitter_image_id', $attach_id);
update_post_meta($post_id, 'rank_math_twitter_image', wp_get_attachment_url($attach_id));
set_post_thumbnail($post_id, $attach_id);  // Double-coverage fallback
```

Frontend result: `og:image` URL + `og:image:secure_url` (HTTPS) + `og:image:width/height` (Rank Math auto-detects from attachment metadata) + `og:image:alt` from `_wp_attachment_image_alt` + `og:image:type` + `twitter:card summary_large_image`.

Full workflow: [`workflows/og-image-generation.md`](../workflows/og-image-generation.md). PHP recipe: [`templates/snippets/og-image-generator.php`](../templates/snippets/og-image-generator.php).
