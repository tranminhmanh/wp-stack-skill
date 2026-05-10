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

⚠️ **CRITICAL: 1 FAQPage per page principle**. If a page has multiple accordions / toggles all with `faq_schema=yes`, Elementor emits multiple `<script type="application/ld+json">` blocks each declaring `@type: FAQPage`. Schema.org best practice (and Google's rich-result eligibility) is **ONE FAQPage per page** with ALL questions consolidated under `mainEntity`. Multiple FAQPage instances = invalid Schema → Google may ignore the lot.

**Detection**:
```bash
curl -s "https://<site>/page/" | grep -c '"@type":"FAQPage"'
# > 1 = problem
```

**Fix**:
1. **Audit**: walk `_elementor_data` for widgets with `faq_schema="yes"`.
2. **Consolidate**: merge all Q&A into ONE accordion (preferred — single rendered control) or ONE toggle. Apply `faq_schema: "yes"` only on that consolidated widget.
3. **Disable schema** on the other widgets: set `faq_schema: ""` (empty string). DO NOT set `"no"` — Elementor versions vary on truthy interpretation; empty string is the safe clear.
4. **Verify**: `grep -c '"@type":"FAQPage"' rendered.html` should equal 1.

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

## Rank Math meta — bulk update qua REST

### Method 1 (PREFERRED): Rank Math `updateMeta` REST endpoint

Rank Math Pro (verified v3.0.84) expose endpoint `/wp-json/rankmath/v1/updateMeta` cho **bulk per-post meta update qua REST với App Password Basic auth**. Battle-tested 85 posts, 8 giây, 0 errors:

```bash
POST /wp-json/rankmath/v1/updateMeta
Authorization: Basic <base64 user:app-pw>
Content-Type: application/json

body: {
  "objectID": 123,
  "objectType": "post",        // "post" | "page" | "term"
  "meta": {
    "rank_math_title": "Custom title | Brand",
    "rank_math_description": "Custom desc 150-160ch",
    "rank_math_focus_keyword": "primary keyword",
    "rank_math_canonical_url": "https://canonical/"
  }
}

→ HTTP 200 {"slug":true,"schemas":[]}
```

Python helper (parallel-safe, different post IDs không conflict):
```python
import json, base64, urllib.request
from concurrent.futures import ThreadPoolExecutor

auth = "Basic " + base64.b64encode(f"{USER}:{APP_PW}".encode()).decode()

def update_meta(post_id, meta):
    payload = {"objectID": post_id, "objectType": "post", "meta": meta}
    req = urllib.request.Request(
        "https://<site>/wp-json/rankmath/v1/updateMeta",
        data=json.dumps(payload, ensure_ascii=False).encode("utf-8"),
        headers={"Authorization": auth, "Content-Type": "application/json"},
        method="POST"
    )
    return urllib.request.urlopen(req, timeout=20).getcode()

# Bulk parallel
with ThreadPoolExecutor(max_workers=6) as exe:
    futs = [exe.submit(update_meta, p['id'], {"rank_math_title": p['new_title']}) for p in plan]
```

⚠️ **NOT to confuse with**:
- `updateSettings` (global, returns 403 với App Password — admin GUI session required)
- `updateRedirection` (returns 200 nhưng rule không kick in trên frontend — xem `pitfalls.md`)

⚠️ **`updateMeta` silent fail when the meta key is NOT Rank-Math-managed**:

The `updateMeta` endpoint only accepts keys whitelisted by Rank Math (anything starting with `rank_math_*` plus a few other plugin-registered keys). If you pass a key that's NOT in the whitelist (Astra theme meta like `site-post-title`, ACF private fields, custom plugin meta), the response is STILL `HTTP 200 {"slug":true,"schemas":[]}` — looks like success — but the meta is silently ignored, never saved.

```python
# ❌ Silent fail — Astra theme meta is not Rank-Math-managed
body = {"objectID": 4992, "objectType": "post",
        "meta": {"site-post-title": "disabled"}}    # Astra meta
# Response: 200 {"slug":true}    ← looks fine
# But: frontend still has 2 H1, the meta was NOT saved
```

`slug:true` in the response only confirms that the URL slug is valid — it does NOT confirm the meta itself was saved. To verify the save, fetch the post meta back via WP REST or render the page and grep the expected output.

**Decision matrix**:
| Meta key prefix | Use | Why |
|---|---|---|
| `rank_math_*` (title, description, focus_keyword, canonical_url, schemas) | ✅ Rank Math `updateMeta` REST | Native handler, App Pw OK |
| Astra theme meta (`site-post-title`, etc.) | WP REST `POST /wp/v2/{type}/{id}` with `meta` (if registered REST-exposed) — or wp-admin GUI | NOT in Rank Math whitelist |
| ACF private / hidden meta | Method 2 mu-plugin one-shot below | `show_in_rest=false` blocks REST |
| Third-party plugin meta (custom CPT, hidden options) | Method 2 mu-plugin one-shot | Not registered for REST |

When in doubt, always re-fetch and verify after the call.

### Method 2 (LEGACY fallback): one-shot mu-plugin

Vẫn còn applicable khi:
- Rank Math version cũ chưa có `updateMeta` endpoint
- Cần update meta của 3rd-party plugin khác (KHÔNG phải Rank Math)
- ACF private fields (`show_in_rest=false`)
- WP options không trong registered allowlist

`PATCH /wp/v2/pages/{id}` với `meta: {rank_math_description: "..."}` returns HTTP 200 but the meta does NOT change qua route default — đó là vì Rank Math không register `show_in_rest=true` trên direct meta keys. NHƯNG endpoint riêng `updateMeta` của Rank Math có handler hooks ride trên permission khác, work qua App Password.

**Workaround mu-plugin** (cho non-Rank-Math meta hoặc Rank Math version cũ): token-guarded one-shot mu-plugin that calls `update_post_meta()` directly, runs once, then stubs itself.

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

## Eyebrow first-text trap — Rank Math meta-description fallback hijack

**Symptom**: rendered `<meta name="description" content="EYEBROW LABEL · SHORT TAG">` shows ~30 chars of the eyebrow text instead of the intended 150-160-char description. Same for `og:description` and `twitter:description`. SERP snippet looks broken / uninformative.

**Root cause**: Rank Math auto-generates meta description as a fallback when `rank_math_description` is not set explicitly, by picking the first text content on the page. The "eyebrow" design pattern (small uppercase label above the H1) is usually the first text node → Rank Math grabs it instead of the body paragraph.

**Reproduction**:
```html
<!-- Hero with eyebrow first, then H1, then body paragraph -->
<div class="elementor-heading-title">SERVICE LABEL · USP TAG</div>   <!-- eyebrow -->
<h1>The actual page heading</h1>
<p>The body sentence that should be the meta description...</p>

<!-- Frontend rendered meta (Rank Math auto-fallback) -->
<meta name="description" content="SERVICE LABEL · USP TAG" />
<meta property="og:description" content="SERVICE LABEL · USP TAG" />
<!-- ⚠️ truncated, not informative -->
```

**Fix — always set explicit Rank Math meta description after design changes**:
```bash
POST /wp-json/rankmath/v1/updateMeta
Content-Type: application/json
Authorization: Basic <base64>

{
  "objectID": N,
  "objectType": "post",
  "meta": {
    "rank_math_description": "Full sentence 150–160 chars covering the page's value proposition and primary keyword.",
    "rank_math_facebook_description": "Full sentence",
    "rank_math_twitter_description": "Full sentence"
  }
}
```

**Verify after every design change** that touches the hero / first text block:
```bash
curl -s "https://<site>/page/" | grep -oE '<meta name="description"[^>]+>'
```

If the description matches an eyebrow / short tag, set it explicitly.

**When this matters most**:
- Eyebrow design pattern (uppercase label + middot separator)
- Pages cloned from a template that had `rank_math_description` set on the original — the clone inherits empty
- Pages built via MCP without an explicit description set

**Reusability**: universal for any Elementor + Rank Math site that uses an eyebrow / kicker pattern in the hero.

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
