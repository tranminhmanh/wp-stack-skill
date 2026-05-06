# SEO Checklist — Rank Math setup

## Cài đặt ban đầu

1. Install Rank Math Free
2. Setup Wizard:
   - Site type: Business / Personal blog / Community blog
   - Logo & default social image
   - Connect Google Search Console
   - Connect Google Analytics 4

## Settings critical

`Rank Math → General Settings → Sitemap`
- XML Sitemap: ON
- Include images: ON
- Items per sitemap: 200
- Exclude: tags pages, author pages (trừ blog-heavy site)

`Rank Math → General Settings → Open Graph`
- Default OG image: 1200x630 brand image
- Twitter card: summary_large_image

`Rank Math → Titles & Meta`
- Homepage: "[Brand] — [Tagline]"
- Posts: "%title% %sep% %sitename%"
- Archive: noindex (trừ category quan trọng)
- Author: noindex (trừ blog-heavy)
- Search: noindex
- 404: noindex

`Rank Math → Local SEO` (cho site có địa điểm vật lý)
- Person/Organization: Organization
- Address, phone, email, opening hours
- Knowledge graph type: LocalBusiness
- Maps API key (optional)

## Per-page SEO khi build với Elementor

Mỗi landing page build qua MCP:
1. Set focus keyword
2. Title tag: 50-60 ký tự, có keyword đầu
3. Meta description: 150-160 ký tự, có CTA
4. URL slug: ngắn, có keyword, KHÔNG tiếng Việt có dấu
5. H1 unique, có keyword
6. Alt text mọi image
7. Schema: LocalBusiness/Service/Product tùy page

## Vietnamese SEO

- Slug: dùng tiếng Việt không dấu (`/dich-vu-phao-hoa/`, không `/dịch-vụ/`)
- Meta description: tiếng Việt có dấu OK
- Schema name: tiếng Việt có dấu OK
- hreflang: nếu có song ngữ
- Mobile-first: Google VN crawl mobile UA

## Common SEO issues

| Issue | Fix |
|---|---|
| Duplicate title tags | Disable archive pages noindex |
| Missing alt text | Bulk fix qua Rank Math image SEO |
| Slow LCP | Check performance.md |
| Thin content | Min 600 words/page |
| No internal links | Mỗi page link tới 3-5 page khác |
| No schema | Rank Math Schema tab per page |
| Sitemap không update | Tools → Database Tools → Update Sitemap |
| Schema duplicate (Astra + Rank Math) | Disable Astra schema |

## Pillar / landing page Schema 3 types pattern

Maximize SERP coverage cho long-tail keywords + commercial intent. Inject 3 schema types qua HTML widget với `<script type="application/ld+json">`:

### 1. BreadcrumbList
```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type":"ListItem", "position":1, "name":"Trang chủ", "item":"https://example.com/"},
    {"@type":"ListItem", "position":2, "name":"Tuyến vận chuyển", "item":"https://example.com/tuyen-van-chuyen/"},
    {"@type":"ListItem", "position":3, "name":"VN-Hàn Quốc", "item":"https://example.com/tuyen-van-chuyen/vn-han-quoc/"}
  ]
}
```

### 2. Service / AggregateOffer
```json
{
  "@context": "https://schema.org",
  "@type": "Service",
  "name": "Vận chuyển container Việt Nam đi Hàn Quốc",
  "provider": {"@type":"Organization", "name":"ShipAsia", "url":"https://shipasia.vn/"},
  "areaServed": [{"@type":"Country","name":"Vietnam"}, {"@type":"Country","name":"South Korea"}],
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
Auto-generate từ Elementor Accordion widget với `faq_schema: "yes"` setting. Mỗi tab → `Question` + `Answer`. Không cần inject manual nếu accordion đã set.

### Hide visual của HTML widget chứa schema
```css
.sa-schema-only { display: none; }
```
Schema được Google bot crawl trong DOM, không cần render visible.

## Bulk Schema price update via regex

Khi bulk update giá pillar/subpage (cước thay đổi quý), HTML widget content stored escaped trong `_elementor_data`. KHÔNG plain str_replace vì format có space variations:

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

Áp pattern tương tự cho subpages cùng pillar (5–10 cặp cảng) trong cùng script.

## Bulk Rank Math meta via post_meta (no GUI)

Rank Math stores all SEO meta as `post_meta` keys. Bulk-set qua PHP instant, không navigate GUI 52 lần:

| Meta key | Purpose | Override |
|---|---|---|
| `rank_math_title` | `<title>` tag | Override post_title |
| `rank_math_description` | meta description | — |
| `rank_math_focus_keyword` | primary keyword cho on-page analysis | — |
| `rank_math_canonical_url` | canonical URL | Default = self |
| `rank_math_robots` | array `['index', 'follow', 'noarchive', ...]` | — |
| `rank_math_facebook_image_id` | OG attachment ID | Required cho og:image render |
| `rank_math_facebook_image` | OG image URL | Set cùng `_id` |
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

## Inject Schema markup vào `_elementor_data` via PHP

Khi cần add schema (Service, BlogPosting, FAQPage) cho pages đã build qua MCP:

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

CSS hide: `.sa-schema-only { display: none; }` cho widget container — Google bot crawl trong DOM, không cần render visible.

## Vietnamese title length: 40-55 chars

Tiếng Việt UTF-8 takes 1.5-2 bytes per char (đ, ă, ê, ơ...), nhưng Google SERP đếm by **char visual width**. Title `Vận chuyển container Việt Nam → Trung Đông (UAE/Saudi/Qatar) - ShipAsia` = 70+ chars → SERP cắt giữa.

**Target**: 40-55 chars Vietnamese (vs 50-60 English).

Format chuẩn: `[Keyword chính] — [USP highlight] | Brand`

Vd:
- ✅ "Vận chuyển VN-Hàn Quốc — Cước cạnh tranh | ShipAsia" (49 chars)
- ❌ "Vận chuyển container Việt Nam đi Hàn Quốc — Báo giá miễn phí trong 4 giờ" (74 chars, SERP cắt)

## Schema OfferCatalog cho structured services

Khi page hub có nhiều "items" thuộc cùng main service (vd 8 routes của 1 vận chuyển service, 5 dịch vụ sub của hub), schema flat list kém hơn `OfferCatalog`:

**Flat (kém)**:
```json
[
  {"@type":"Service", "name":"Service A", ...},
  {"@type":"Service", "name":"Service B", ...}
]
```

**OfferCatalog (tốt)** — Google hiểu structure "N items của 1 main service":
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

Eligible cho **Sitelinks Search Box** + structured product/service rich result trong SERP.

## OG image attachment integration (Rank Math)

⚠️ Rank Math **REQUIRE attachment ID** (không chỉ URL) để render `og:image` properly.

```php
// Wrong: only URL → og:image meta không render
update_post_meta($post_id, 'rank_math_facebook_image', 'https://example.com/og.png');

// Right: attachment ID + URL both
$attach_id = wp_insert_attachment([...], $file_path);
wp_generate_attachment_metadata($attach_id, $file_path);
update_post_meta($attach_id, '_wp_attachment_image_alt', 'Descriptive alt');

update_post_meta($post_id, 'rank_math_facebook_image_id', $attach_id);
update_post_meta($post_id, 'rank_math_facebook_image', wp_get_attachment_url($attach_id));
update_post_meta($post_id, 'rank_math_twitter_image_id', $attach_id);
update_post_meta($post_id, 'rank_math_twitter_image', wp_get_attachment_url($attach_id));
set_post_thumbnail($post_id, $attach_id);  // Double coverage fallback
```

Result frontend: `og:image` URL + `og:image:secure_url` HTTPS + `og:image:width/height` (Rank Math auto-detect from attachment metadata) + `og:image:alt` từ `_wp_attachment_image_alt` + `og:image:type` + `twitter:card summary_large_image`.

Full workflow: [`workflows/og-image-generation.md`](../workflows/og-image-generation.md). PHP recipe: [`templates/snippets/og-image-generator.php`](../templates/snippets/og-image-generator.php).
