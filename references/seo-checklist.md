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
