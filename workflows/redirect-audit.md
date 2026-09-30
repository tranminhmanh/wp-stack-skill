# Workflow: Redirect audit — find rules that mask live content

Old sites accumulate redirect rules: a permalink-base change years ago, a plugin migration, a bulk import that created `/old-prefix/slug` → `/slug` pairs, a cleanup that pointed deleted posts at a hub. Most rules are harmless. The dangerous ones are rules whose **source is the permalink of something that is live today** — they hijack that page, and Google drops it from the index. On one site, 28 recovered posts had **0 impressions over 4 weeks** while such rules were active; nothing in the site's own dashboards flagged it.

## When to use

✅ Inherited site with a long redirect list (Rank Math / Redirection plugin / custom map / `.htaccess`)
✅ Posts that exist, are published, are in the sitemap — and still get no impressions
✅ GSC shows "Redirect error" / "Page with redirect" for URLs you believe are live
✅ After changing permalink structure or migrating from another CMS / page builder

❌ A handful of rules you created yourself last week — just test them directly

## The one criterion that matters

Don't classify rules by where the chain ends. Ask, for **every** rule:

> **Is the source path the permalink of a published post / page / product?**

If yes, the rule masks live content no matter what happens next:

| Chain outcome | What the visitor / crawler sees | Easy to spot? |
|---|---|---|
| Loop (source → old prefix → canonical back to source) | Browser error, crawler gives up | Yes — first-pass audits catch these |
| Ends 404 / 410 (target was deleted) | Dead page instead of the live post | Medium |
| **Ends 200 at a hub / homepage** | A different page — looks "healthy" | **No — this is the group first-pass audits miss** |

A first pass that only flagged self-loops found 34 loops and kept everything that ended in 200. A second pass using the criterion above found 9 more masked live URLs — 8 posts behind rules that "resolved fine" at a hub, and 1 product URL bounced to a dead legacy slug.

## Step 1 — Export every redirect source

Collect all layers, not just the plugin UI list:

- **Rank Math**: table `{prefix}rank_math_redirections` — `id`, `sources` (serialized array of `{pattern, comparison}`), `url_to`, `header_code`, `status`. If the plugin's REST route / wrapper ability fails under Application Password context (see [`../references/pitfalls.md`](../references/pitfalls.md) §"Rank Math `updateRedirection` REST silent fail"), expose a **read-only**, `manage_options`-gated MU-plugin route that returns the rows with `sources` unserialized.
- **Redirection plugin** (John Godley): its own tables / REST API.
- **Custom MU-plugin maps** (`init` / `template_redirect` hooks with a `$map = [...]`).
- **`.htaccess`** `Redirect` / `RewriteRule … [R=301]` lines.

## Step 2 — Build the set of live paths

```bash
# Published posts / pages / products → path set (paginate until empty)
for type in posts pages product; do
  for page in $(seq 1 50); do
    out=$(curl -s -u "$U:$P" "$SITE/wp-json/wp/v2/$type?status=publish&per_page=100&page=$page&_fields=link")
    case "$out" in ''|'[]'|*'"code"'*) break ;; esac   # empty page or rest_post_invalid_page_number
    echo "$out" | jq -r '.[].link' | sed -E "s#^https?://[^/]+##"
  done
done | sort -u > live-paths.txt
```

Or check per source server-side with `get_page_by_path( $path, OBJECT, [ 'post', 'page', 'product' ] )`.

## Step 3 — Classify every rule

Follow hops manually (no `curl -L`), cap at ~10, record each `Location`, detect revisits:

```bash
hop() { u="$1"; for i in $(seq 1 10); do
  read code loc < <(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$u")
  echo "$code $u"; [ -z "$loc" ] && break; u="$loc"; done; }
```

| Class | Test | Action |
|---|---|---|
| **Masks live content** | source path ∈ live-paths | Deactivate |
| **Loop** | a URL repeats in the hop list | Deactivate the rule that creates it; rules that only loop *because they funnel into* a masking rule usually heal once that rule is off — re-test before touching them |
| **Dead target** | chain ends 404 / 410 and source is not live | Repoint to the closest **topical** hub (not the homepage), or let it 410 |
| **Healthy** | chain ends 200 at the intended target, source not live | Keep |

## Step 4 — Apply (reversible, dry-run first)

When the plugin UI/API can't be scripted, a token-gated MU-plugin route that edits the table directly works. Make `apply=0` (dry run) the default and return the list of rows it *would* change:

```php
// Deactivate — never DELETE (keeps history, one UPDATE to roll back)
$wpdb->query( "UPDATE {$wpdb->prefix}rank_math_redirections
               SET status = 'inactive' WHERE id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ")" );
// Repoint — keep 301, change target
$wpdb->update( "{$wpdb->prefix}rank_math_redirections", [ 'url_to' => $new_url ], [ 'id' => (int) $id ] );
// Rank Math keeps a resolved-redirect cache — clear it or old decisions keep being served
$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}rank_math_redirections_cache" );
if ( defined( 'LSCWP_V' ) ) do_action( 'litespeed_purge_all' );
```

Check table names first (`SHOW TABLES LIKE '%redirection%'`) — prefixes and plugin versions differ.

## Step 5 — Guard custom redirect maps permanently

Any redirect MU-plugin you maintain should refuse to redirect away from a live permalink, so the problem can't come back when someone publishes a post whose slug matches an old rule:

```php
add_action( 'template_redirect', function () {
    $path = trim( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    $map  = acme_redirect_map();                 // [ 'old-path' => '/new-path/' ]
    if ( ! isset( $map[ $path ] ) ) return;
    $live = get_page_by_path( $path, OBJECT, [ 'post', 'page', 'product' ] );
    if ( $live && 'publish' === $live->post_status ) return;   // never mask live content
    wp_safe_redirect( home_url( $map[ $path ] ), 301 );
    exit;
}, 1 );
```

## Step 6 — Verify and measure recovery

- Every previously masked URL: **200, 0 hops, self-referencing canonical**
- Every former loop: gone (or a clean 404 when nothing lives behind it)
- Spot-check a sample of kept rules — still 301 → 200 at the intended target
- Snapshot GSC impressions for the recovered URLs **before** the fix (typically 0) — any impressions afterwards are clean recovery signal

Then nudge re-crawl: resubmit the sitemap and use URL Inspection → *Request indexing* (GSC UI) for the most important URLs. The Indexing API is **not** a supported shortcut for normal pages — see [`ga4-admin-api.md`](ga4-admin-api.md) §"Indexing API — scope limitation".

## Widen the net with GSC data

The redirect table only shows rules. To find other hidden damage, cross three lists: sitemap URLs × URLs with GSC data (Search Analytics, 90 days) × live HTTP status. Sitemap URLs with zero GSC data and a non-200 status are the next candidates to inspect.

## Anti-patterns

❌ Judging rules by final status ("ends in 200 → fine")
❌ Deleting rules instead of deactivating them — you lose the audit trail and the easy rollback
❌ Repointing everything to the homepage — Google may treat mass homepage redirects as soft 404s
❌ Auditing only the plugin's UI list — custom maps and `.htaccess` redirect too
❌ Forgetting the plugin's redirect cache table after a direct DB edit

## Related

- [`../references/rankmath.md`](../references/rankmath.md) §2 — an `exact` redirect overrides a published post. That is useful **on purpose** (consolidating a post into a pillar); this workflow is the audit for the accidental version of the same behaviour
- [`full-site-seo-audit.md`](full-site-seo-audit.md) — crawl-once data to feed Step 2 / Step 3
- [`litespeed-cache-mgmt.md`](litespeed-cache-mgmt.md) — verify redirects on clean URLs, not cache-busted ones
- Insight source: weekly distillation 2026-09-30 (e-commerce site: 176-rule audit, 29 deactivated + 54 repointed, second pass found 9 masked URLs the first pass kept)
