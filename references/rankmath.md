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
- Reference implementation: PKMT `audit/rankmath-mcp/` (16 abilities cho Link Genius + meta CRUD + redirects)

**Option C — Direct DB write** (last resort, không recommend):
```sql
UPDATE wp_postmeta SET meta_value='New text' WHERE post_id=36 AND meta_key='rank_math_description';
```
Bypass mọi hooks → cần manually clear LiteSpeed cache after.

## 4. rankmath-mcp wrapper plugin — response key conventions

Nếu dùng wrapper plugin pattern (vd `audit/rankmath-mcp/` ở PKMT), response keys KHÔNG follow `items` standard — preserve semantic keys per resource:

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

## Liên quan

- [`wp-abilities.md`](wp-abilities.md) — REST direct execution pattern
- [`schema-jsonld.md`](schema-jsonld.md) — Rank Math auto-emits Organization `@id` schema, multi-source coexistence
- [`workflows/litespeed-cache-mgmt.md`](../workflows/litespeed-cache-mgmt.md) — WP-Abilities REST cache bust
- [`workflows/build-mcp-wrapper-plugin.md`](../workflows/build-mcp-wrapper-plugin.md) — wrap Rank Math REST as MCP abilities
- Insights references: PKM-2026-05-13-074 (lazy compute), -077 (redirect override), -078 (response keys)
