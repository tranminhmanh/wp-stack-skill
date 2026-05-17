# Rank Math SEO — Behaviors, Quirks, Automation

Reference cho mọi WP site dùng Rank Math (Free hoặc PRO). Tập trung vào **gotchas khi automate qua REST/MCP** — không cover Customizer basic.

> **Khi nào đọc file này**: bulk update SEO meta, setup redirect, integrate Rank Math vào MCP wrapper, debug "SEO score không tăng dù update keyword đầy đủ".

## 1. SEO score recompute là LAZY — không tự trigger từ REST update

⚠️ **Trap phổ biến**: bulk set `rank_math_focus_keyword` qua REST → expect SEO score tăng → score stays unchanged.

### Root cause

Rank Math compute SEO score **chỉ khi**:
1. User mở post/page trong wp-admin → meta box tự fire `rank_math_compute_score` JS
2. Hoặc Rank Math → Tools → **Reanalyze Posts** chạy explicit
3. **Frontend page view does NOT trigger** compute

REST update `rank_math_*` meta keys không hook vào compute pipeline. Score stays at last computed value.

### Evidence

```
# Before update:
Page 565 (Home): score=25, kw=""

# Update via update-meta-bulk REST:
# → updated keys: rank_math_title, rank_math_description, rank_math_focus_keyword
# → HTTP 200, success=true

# Wait 10s + visit frontend (expecting trigger compute):
Page 565: score=25  ← UNCHANGED

# Same for pages 2918, 3280, 7047, 2919, 1614, 3712, 8004, 7722 — ALL stuck
```

### Fix options

| Option | When | Effort |
|---|---|---|
| Manual: open each page wp-admin, hit Save | 1-10 pages | 10s/page |
| Bulk: Rank Math → Tools → Reanalyze Posts | 10+ pages | ~30s total |
| Programmatic: trigger `\RankMath\SEO_Analysis\Analyzer::analyze( $post_id )` via custom REST endpoint | CI/CD automation | wrap into MCP ability |

### Future enhancement

Rank Math không expose `Analyzer::analyze()` qua REST mặc định. Wrap into custom plugin ability:
```php
add_action( 'wp_abilities_api_init', function () {
    wp_register_ability( 'rankmath-mcp/reanalyze-post', [
        'label'       => 'Reanalyze Post SEO Score',
        'input_schema' => [
            'type' => 'object',
            'properties' => ['post_id' => ['type' => 'integer']],
            'required' => ['post_id'],
        ],
        'execute_callback' => function ( $input ) {
            if ( ! class_exists( '\\RankMath\\SEO_Analysis\\Analyzer' ) ) {
                return ['success' => false, 'error' => 'Rank Math SEO_Analysis class missing'];
            }
            $analyzer = new \RankMath\SEO_Analysis\Analyzer();
            $result = $analyzer->analyze( (int) $input['post_id'] );
            return ['success' => true, 'data' => $result];
        },
        'meta' => [
            'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => true],
            'show_in_rest' => true,
            'mcp'          => ['public' => true, 'type' => 'tool'],
        ],
    ] );
});
```

## 2. Redirect `comparison: exact` override source URL even when post is published

Khi setup 301 redirect với `comparison: "exact"` cho source URL của post đang `status=publish`, Rank Math redirect **VẪN FIRE** (HTTP 301) thay vì serve post. Cho phép pattern "setup redirect → verify → trash source" (KHÔNG cần reverse order).

### Evidence

```
Post 5903 status=publish, slug="example-slug"

+ Rank Math redirect:
    pattern="example-slug"
    comparison="exact"
    destination="/new-target/"
    header_code=301

→ GET https://site/example-slug/
→ HTTP 301 Location: https://site/new-target/
   (post 5903 KHÔNG render)
```

### Root cause

`\RankMath\Redirections\Redirector` hooks early on `template_redirect` (priority 11) — trước khi WP load post template. Match `exact` URL pattern → emit 301 + exit.

### Useful workflow

Reverse traditional order (trash first, redirect second) — em có thể:
1. **Setup redirect FIRST** (test trên source URL không cache-bust → expect 301 immediate)
2. **Trash source post AFTER** (insurance: post out of sitemap + not indexed)

Safer khi:
- Đang transition cluster content giữa pillar
- Anh muốn rollback dễ (untrash redirect → restore old URL nếu rollback)

### Comparison với plugin khác

| Plugin | Same behavior? |
|---|---|
| Yoast Redirection Pro | ✓ Similar (hook early on `template_redirect`) |
| Redirection (John Godley) | ✓ Similar |
| LiteSpeed Cache redirect rules | ✗ Khác — runs after WP route resolver |
| Wordfence Live Traffic redirect | ✗ Khác — runs as security middleware |

Verify per plugin trước khi rely on pattern.

## 3. `rank_math_*` post meta KHÔNG expose qua REST mặc định

Rank Math không register `show_in_rest=true` cho meta keys:
- `rank_math_title`
- `rank_math_description`
- `rank_math_focus_keyword`
- `rank_math_canonical_url`
- `rank_math_robots`

### Symptom

```
PATCH /wp/v2/pages/36 {"meta": {"rank_math_description": "New text"}}
→ HTTP 200, BUT meta unchanged trên DB (silent ignore)
```

### Fix options

**Option A — Custom REST endpoint via one-shot mu-plugin** (no plugin dependency):
```php
// mu-plugins/rankmath-meta-oneshot.php
add_action( 'rest_api_init', function () {
    register_rest_route( 'rmo/v1', '/update', [
        'methods'  => 'POST',
        'callback' => function ( WP_REST_Request $req ) {
            $token = $req->get_param( 'token' );
            if ( $token !== '<SECRET_TOKEN>' ) return new WP_Error( 'forbidden', 'Bad token', ['status'=>403] );
            $pid   = (int) $req->get_param( 'post_id' );
            $key   = sanitize_text_field( $req->get_param( 'key' ) );
            $val   = $req->get_param( 'value' );
            if ( ! str_starts_with( $key, 'rank_math_' ) ) return new WP_Error( 'bad_key', 'Key must start with rank_math_', ['status'=>400] );
            update_post_meta( $pid, $key, $val );
            return ['success' => true, 'pid' => $pid, 'key' => $key];
        },
        'permission_callback' => '__return_true',
    ] );
} );
```
Call: `POST /wp-json/rmo/v1/update {token, post_id, key, value}` → updated. Remove mu-plugin sau khi xong.

**Option B — rankmath-mcp wrapper plugin** ([`workflows/build-mcp-wrapper-plugin.md`](../workflows/build-mcp-wrapper-plugin.md)):
- Plugin wraps `update_post_meta('rank_math_*')` via WP-Abilities Framework
- Permanent solution, MCP-discoverable
- Reference implementation: a wrapper plugin (16 abilities for Link Genius + meta CRUD + redirects) — see [`workflows/build-mcp-wrapper-plugin.md`](../workflows/build-mcp-wrapper-plugin.md)

**Option C — Direct DB write** (last resort, không recommend):
```sql
UPDATE wp_postmeta SET meta_value='New text' WHERE post_id=36 AND meta_key='rank_math_description';
```
Bypass mọi hooks → cần manually clear LiteSpeed cache after.

## 4. rankmath-mcp wrapper plugin — response key conventions

Khi dùng wrapper plugin pattern (xem `workflows/build-mcp-wrapper-plugin.md`), response keys KHÔNG follow `items` standard — preserve semantic keys per resource:

| Ability | Response key | Input key |
|---|---|---|
| `bulk-get-meta` | `posts[]` | `ids` |
| `list-redirections` | `redirections[]` | `status` |
| `get-incoming-links` | `links[]` | `target_post_id` |
| `list-posts`, `list-orphan-posts`, `list-no-focus-keyword` | `posts[]` | various |
| `update-meta-bulk` | `results[]` | **`rows`** (NOT `items`) |
| `create-redirection` | `{id, message}` | **`destination`** (NOT `url_to`) |

⚠️ Wrong: `items = r.get('items', [])` → always `[]`.
✅ Right: `posts = r.get('posts', [])` (per ability).

When building NEW wrapper plugin, prefer semantic keys cho clarity nhưng document thoroughly in readme.

## 5. LiteSpeed cache + Rank Math meta — stale-read trap

Sau `update-meta` write thành công, subsequent GET `get-meta` có thể trả STALE value. Xem [`workflows/litespeed-cache-mgmt.md`](../workflows/litespeed-cache-mgmt.md) section "WP-Abilities REST stale-read fix" — wrapper plugin phải emit no-cache headers qua `rest_post_dispatch` filter + `litespeed_control_set_nocache` action.

## 6. Sitemap regen trigger

Rank Math sitemap (XML) cached at file system level. Update post → sitemap không auto-regen ngay. Trigger:
- wp-admin → Rank Math → Sitemap Settings → Save (forces regen)
- Programmatic: `do_action( 'rank_math/sitemap/hit_index' );` hoặc delete cache transient `rank_math_sitemap_*`

## 7. Schema Builder 2.x — `rank_math_schema_{Type}` format (no Schema Pro needed)

**Key insight**: Rank Math Pro v3.0.112+ ships a complete Schema Builder. You do **NOT need a separate Schema Pro plugin** to emit rich-snippet structured data. The format is one post-meta key per schema type, namespaced as `rank_math_schema_{Type}`.

### Meta-key naming

One key per schema TYPE, per post. Value is a JSON object with a `metadata` wrapper:

```
rank_math_schema_Service          # Service
rank_math_schema_LocalBusiness    # LocalBusiness
rank_math_schema_Article          # Article (legacy 1.x uses different key — see below)
rank_math_schema_Event            # Event (with subtypes: Festival, BusinessEvent, SocialEvent)
rank_math_schema_Product          # Product
```

### Value shape

```jsonc
{
  "@type": "Service",
  "metadata": {
    "title":     "Service",
    "type":      "template",
    "shortcode": "s-{post_id}-service",
    "isPrimary": true
  },
  "@id":         "https://<site>/<page>/#service",
  "name":        "...",
  "description": "...",
  "provider":    { "@type": "Organization", "@id": "https://<site>/#organization" }
}
```

The `metadata` wrapper is mandatory — without it, Rank Math doesn't recognize the schema and silently doesn't emit it on the frontend.

### Setting via REST (one ability call)

```
POST /wp-abilities/v1/abilities/rankmath-mcp/update-meta/run
Body: {"input": {"id": <post_id>, "meta": {"rank_math_schema_Service": { ...full JSON object... }}}}
```

WordPress auto-serializes the JSON object → PHP array when `update_post_meta()` runs. Rank Math frontend filter picks it up and emits in `@graph`.

### Verified supported types

Service, LocalBusiness, Article, Festival, BusinessEvent, SocialEvent, Product, Recipe. Tested on real deployments — 100% render frontend (anonymous user).

### What NOT to use

❌ **`rankmath-mcp/update-schemas` ability** — save returns success but schemas DO NOT render on frontend. Different code path that doesn't validate properly. Use `update-meta` with `rank_math_schema_{Type}` key instead.

❌ **Legacy 1.x meta `rank_math_rich_snippet` + `rank_math_snippet_*`** — will BREAK the 2.x Schema Builder. After legacy values are present, Schema Builder shows only Breadcrumb + broken Article (`@type: ""`). Delete legacy keys before migrating to 2.x.

❌ **Hardcoded `@id`** — Rank Math overrides at runtime with pattern `#schema-{post_id}`. Either use the runtime-generated `@id` OR follow a custom `@id` convention site-wide (see [`schema-jsonld.md`](schema-jsonld.md) "@id linking").

### FAQPage NOT supported via this path

Tested all variations: `rank_math_schema_FAQPage`, `rank_math_schema_FAQ`, lowercase, numbered — **all save OK, none render** on frontend. Rank Math only recognizes FAQPage via:

1. Gutenberg "Rank Math FAQ Block" (manual config in editor)
2. Schema Templates UI manual config (per-page UI flow)

**Workaround**: inject `<script type="application/ld+json">{"@type":"FAQPage","mainEntity":[...]}</script>` directly into an HTML widget. Google parses JSON-LD anywhere in the document body. See [`schema-jsonld.md`](schema-jsonld.md) for the injection pattern.

## 8. WooCommerce Shop page — title precedence resolution (dual-context gotcha)

WooCommerce Shop page (page id from `wc_get_page_id('shop')`) is **dual-context**: it's both a Page (CPT `page`) and a Product Archive. Rank Math resolves title vs description via DIFFERENT chains for these two contexts:

| Chain | Resolution order |
|---|---|
| **Title** | per-page `rank_math_title` postmeta → `pt_page_title` template → default |
| **Description** | per-page `rank_math_description` postmeta → `pt_product_archive_description` template → `pt_page_description` → default |

⚠️ **`pt_product_archive_title` is NOT in the title chain for `/shop/`**. Updating that template-level option changes only descriptions on product archives, not the Shop page title.

**Symptom**: update `rank-math-options-titles[pt_product_archive_title]` via REST → meta description changes correctly, but `<title>` tag stays `"Shop · Sitename"` (rendered from `pt_page_title` template `%title% %sep% %sitename%`). og:title also stays old.

**Fix — always use per-page meta override for the Shop page**:

```php
$shop_id = wc_get_page_id('shop');
update_post_meta($shop_id, 'rank_math_title',       '<custom title>');
update_post_meta($shop_id, 'rank_math_description', '<custom description 150-160ch>');
do_action('litespeed_purge_all');  // clear cached HTML
```

Or via the wrapper-plugin REST endpoint:

```bash
POST /wp-abilities/v1/abilities/rankmath-mcp/update-meta/run
Body: {"input": {
  "id": <shop_page_id>,
  "meta": {
    "rank_math_title":       "<custom>",
    "rank_math_description": "<custom>"
  }
}}
```

**Identify shop page ID dynamically**:
```php
$shop_id = wc_get_page_id('shop');  // handles non-default shop page IDs
```

Same pattern applies to other dual-context WC pages: Cart, Checkout, My Account, Product Tag archives.

## 9. OG image resolution chain — `featured_media` beats SVG `rank_math_facebook_image`

**Symptom**: set `rank_math_facebook_image` = SVG URL → output `og:image` is a different image (the page's `featured_media` JPG, not the SVG).

**Root cause**: Rank Math validates the OG image MIME type. SVG (`image/svg+xml`) is **skipped** because Facebook / Twitter / LinkedIn do not render SVG in social previews → fallback chain goes to `featured_media`.

### Resolution chain (highest to lowest priority)

1. `rank_math_facebook_image` post meta — only if URL is **raster** (JPG, PNG, WebP)
2. `featured_media` of the post — raster fallback
3. Site-wide default OG image (`rank-math-options-titles[open_graph_image_id]`)
4. No OG image (Rank Math omits the meta tag entirely)

### Implications

- **Fastest path to set OG image** for a page = `update featured_media` via REST. No need to touch `rank_math_facebook_image` at all for 90% of cases.
- **Per-page Rank Math meta override still wins** when the override URL is a raster image.
- **SVG can be uploaded** (as a hero / inline brand asset) without affecting OG — Rank Math just skips it for OG purposes.

### REST recipe

```bash
# Set featured image (Rank Math auto-picks for og:image when raster)
curl -u "$U:$APP_PW" -X POST "$SITE/wp-json/wp/v2/pages/<page_id>" \
  -H "Content-Type: application/json" \
  -d "{\"featured_media\": <attachment_id>}"

# Verify
curl -s "$SITE/<page-path>/?cb=$(date +%s)" | grep -oE '<meta property="og:image"[^>]+>'
```

## 10. WooCommerce product OG image — `/wc/v3/products` `images[]` array (REPLACE semantic)

Products use the WooCommerce REST endpoint, NOT the WP Page endpoint. The `featured_media` field doesn't apply directly — use the `images[]` array.

### Endpoint differences

| Post type | Endpoint | Featured image field |
|---|---|---|
| Page | `/wp-json/wp/v2/pages/<id>` | `featured_media` (integer attachment ID) |
| Post | `/wp-json/wp/v2/posts/<id>` | `featured_media` |
| **Product** | `/wp-json/wc/v3/products/<id>` | **`images[]` array** |

### Update workflow

```bash
curl -u "$WP_USER:$WP_PASS" -X PUT "$WP_SITE/wp-json/wc/v3/products/<ID>" \
  -H "Content-Type: application/json" \
  -d '{"images": [{"id": <attachment_id>, "alt": "descriptive alt text"}]}'
```

### ⚠️ PUT with `images` REPLACES the entire array

This is the most common gotcha. `images` is treated as the full set, not a patch:

- Send `{"images": [{"id": 5}]}` → product now has 1 image (5). All existing images are unlinked.
- To APPEND, fetch the product first, append to the existing array, then PUT the merged array back.

```bash
# Fetch existing images
EXISTING=$(curl -u "$U:$P" "$SITE/wp-json/wc/v3/products/<ID>" | jq -r '.images')

# Append new image
NEW_IMAGES=$(echo "$EXISTING" | jq ". + [{\"id\": $NEW_ATTACH_ID, \"alt\": \"...\"}]")

# PUT merged array
curl -u "$U:$P" -X PUT "$SITE/wp-json/wc/v3/products/<ID>" \
  -H "Content-Type: application/json" \
  -d "{\"images\": $NEW_IMAGES}"
```

### Rank Math behavior for products

First image in `images[]` = featured = `og:image` source. Rank Math auto-skips SVG (same as page logic above).

### Cache invalidation

PUT on `/wc/v3/products` triggers the `save_post` hook → LiteSpeed auto-purges the product page. No separate touch needed.

### Verification

```bash
curl -s "$SITE/product/<slug>/?cb=$(date +%s)" | grep -oE '<meta property="og:image"[^>]+>'
```

## 11. Wrapper plugin response/input key conventions (rankmath-mcp pattern)

When wrapping Rank Math REST routes into MCP abilities (see [`workflows/build-mcp-wrapper-plugin.md`](../workflows/build-mcp-wrapper-plugin.md)), the response shape doesn't follow the generic `{items: [...]}` standard. Each resource preserves its semantic key:

| Wrapped ability | Response key |
|---|---|
| `bulk-get-meta` | `posts[]` |
| `list-redirections` | `redirections[]` |
| `get-incoming-links` | `links[]` |
| `get-meta` | (object — not array) |

Similarly, write abilities take semantic input keys, not generic ones:

| Write ability | Input key |
|---|---|
| `update-meta-bulk` | `rows[]` (NOT `items` or `entries`) |
| `create-redirection` | `destination` (NOT `url_to` or `target`) |

This is a conscious design choice — preserves the wrapped REST API's vocabulary so users who know Rank Math docs can transfer their knowledge. When designing your own wrapper plugin, follow the same convention: keep the upstream's semantic naming, don't force a generic shape.

### `get-meta` returns `post_title` in `title` field

Specifically: the `get-meta` ability returns the WordPress `post_title` in the `title` response key — NOT the `rank_math_title` meta. This is intentional (the post's official title) but it confuses callers who expect the SEO title. To get the SEO title, read `rank_math_title` from the `meta` field of the response.

### `update-meta` accepts both canonical + alias parameter names

To ease migration from older Rank Math API versions, `update-meta` accepts the canonical key AND common aliases:

| Canonical | Aliases |
|---|---|
| `seo_title` | `rank_math_title`, `title` |
| `seo_description` | `rank_math_description`, `description` |
| `focus_keyword` | `rank_math_focus_keyword`, `keyword` |
| `canonical_url` | `rank_math_canonical_url`, `canonical` |

Pick one convention per script and stick with it. Don't mix aliases within a single call — schema validation may catch the inconsistency.

## Liên quan

- [`wp-abilities.md`](wp-abilities.md) — REST direct execution pattern
- [`schema-jsonld.md`](schema-jsonld.md) — Rank Math auto-emits Organization `@id` schema, multi-source coexistence
- [`workflows/litespeed-cache-mgmt.md`](../workflows/litespeed-cache-mgmt.md) — WP-Abilities REST cache bust
- [`workflows/build-mcp-wrapper-plugin.md`](../workflows/build-mcp-wrapper-plugin.md) — wrap Rank Math REST as MCP abilities
- Insight sources: weekly distillation 2026-05-13 (lazy compute behavior, redirect override semantics, response key conventions)
