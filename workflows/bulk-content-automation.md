# Workflow: Bulk Content Automation via WP REST (Idempotent + Recoverable)

Khi cần prepend/append/modify HTML content trên 50-500+ posts trong 1 batch (vd cluster up-link callout, author byline injection, trust signal banner), workflow này đảm bảo **idempotent** (re-run safe) + **recoverable** (rollback dễ) + **diff-friendly** (audit changes).

> **Khi nào dùng**: bulk SEO automation, pillar cluster wiring, affiliate disclosure boilerplate inject, "Last reviewed: ..." update notice rollout, mass post_meta sync.

## Critical pattern: marker class for idempotency

⚠️ **Trap**: WP REST `POST /wp/v2/posts/{id} {content: ...}` always OVERRIDES content (no patch semantics). Re-run script → content doubles. Without dedup check, "did I run this already?" becomes unknown.

### Fix — unique marker class

Every bulk-injected HTML block phải chứa **unique CSS class marker**: `{project}-{purpose}-{slug}` pattern.

```python
MARKER = 'acme-pillar-uplink-topic-slug'  # unique per pillar, project-prefixed

CALLOUT = f'''<div class="{MARKER}" style="background:#fff5f8;border-left:4px solid #FF4E88;padding:16px;margin-bottom:24px;">
<p>📌 <strong>Đây là bài thuộc chuyên đề Phụ khoa.</strong>
<a href="https://site.com/dich-vu-kham-phu-khoa/" style="color:#FF4E88;font-weight:600;">
Tìm hiểu đầy đủ về dịch vụ Khám phụ khoa →</a></p>
</div>

'''  # Note: trailing \n\n preserves WP paragraph break

for pid in cluster_post_ids:
    # GET current content (raw, không rendered)
    code, post = req('GET', f'/wp/v2/posts/{pid}?context=edit&_fields=content')
    raw = post['content']['raw']

    # IDEMPOTENT CHECK — skip if already added
    if MARKER in raw:
        skip_count += 1
        continue

    # Prepend callout
    new_content = CALLOUT + raw

    # POST update
    req('POST', f'/wp/v2/posts/{pid}', {'content': new_content})
    success_count += 1
    time.sleep(0.5)  # rate limit, polite

print(f"Updated: {success_count}, Skipped (already done): {skip_count}")
```

### Benefits

| Property | Without marker | With marker |
|---|---|---|
| **Idempotent** | ❌ Re-run doubles content | ✓ Re-run skips done posts |
| **Audit "did this run?"** | ❌ Need separate log file | ✓ `grep MARKER db_dump.sql` |
| **Rollback** | ❌ Manual diff per post | ✓ `regex replace <div class="MARKER".*?</div>` |
| **Per-batch trackable** | ❌ Mass changes blur together | ✓ Different marker per batch (`pillar-uplink-phu-khoa` vs `-hiem-muon`) |
| **Partial run recovery** | ❌ Re-run causes data loss | ✓ Resume from where it stopped |

## 4-stage workflow

### Stage 1: Plan + define marker

Specify:
1. **What to add** — exact HTML block với styled CSS (no class outside the marker)
2. **Marker name** — `{project}-{purpose}-{variant}` lowercase-kebab format
3. **Target posts** — query criteria (category, tag, status, exclude IDs)
4. **Position** — prepend (top of content) vs append (bottom) vs after-Nth-paragraph

Example plan markdown:
```markdown
## Cluster Up-link Phụ khoa

- Marker: `acme-pillar-uplink-topic-slug`
- Target: posts trong category `phu-khoa` (id=446) + tag `kham-phu-khoa` (id=89)
- Excluded: pillar page itself (4630), draft/trash status
- Position: prepend
- Estimated count: ~4-5 posts
- Pillar URL: https://<site>/<pillar-slug>/
```

### Stage 2: Dry-run query (no writes)

```python
import requests
from requests.auth import HTTPBasicAuth

auth = HTTPBasicAuth('user', 'app_password')
site = 'https://site.com'

# Query target posts
params = {
    'categories': 446,
    'tags': 89,
    'status': 'publish',
    'per_page': 100,
    '_fields': 'id,title,slug',  # minimal fields for dry-run
    'exclude': '4630',  # exclude pillar itself
}

r = requests.get(f'{site}/wp-json/wp/v2/posts', params=params, auth=auth)
posts = r.json()

print(f"Found {len(posts)} target posts:")
for p in posts:
    print(f"  {p['id']}: {p['title']['rendered']} (/{p['slug']}/)")

# Manual review — confirm target list correct before stage 3
```

### Stage 3: Execute bulk with marker check

```python
MARKER = 'acme-pillar-uplink-topic-slug'
CALLOUT = '''<div class="{marker}" style="...">
<p>📌 ... <a href="{pillar_url}">{cta_text}</a></p>
</div>

'''.format(marker=MARKER, pillar_url='https://...', cta_text='Tìm hiểu →')

stats = {'updated': 0, 'skipped': 0, 'failed': 0}
log = []

for post in posts:
    pid = post['id']

    # Get raw content
    r = requests.get(f'{site}/wp-json/wp/v2/posts/{pid}?context=edit&_fields=content',
                     auth=auth)
    if r.status_code != 200:
        stats['failed'] += 1
        log.append({'pid': pid, 'status': 'GET failed', 'http': r.status_code})
        continue

    raw = r.json()['content']['raw']

    # IDEMPOTENT CHECK
    if MARKER in raw:
        stats['skipped'] += 1
        log.append({'pid': pid, 'status': 'already-done'})
        continue

    # Update
    new_content = CALLOUT + raw
    r = requests.post(f'{site}/wp-json/wp/v2/posts/{pid}',
                      json={'content': new_content},
                      auth=auth)
    if r.status_code in (200, 201):
        stats['updated'] += 1
        log.append({'pid': pid, 'status': 'updated'})
    else:
        stats['failed'] += 1
        log.append({'pid': pid, 'status': 'POST failed', 'http': r.status_code,
                    'body': r.text[:200]})

    time.sleep(0.5)  # rate limit, courteous

# Output
print(f"Updated: {stats['updated']}, Skipped: {stats['skipped']}, Failed: {stats['failed']}")
import json
print(json.dumps(log, indent=2))  # save to audit/bulk_run_{date}.json
```

### Stage 4: Verify + audit

```python
# Verify N random samples
import random
sample_ids = random.sample([p['id'] for p in posts], min(5, len(posts)))

for pid in sample_ids:
    r = requests.get(f'{site}/wp-json/wp/v2/posts/{pid}?context=edit&_fields=content',
                     auth=auth)
    raw = r.json()['content']['raw']
    found = MARKER in raw
    print(f"Post {pid}: marker present? {found}")

# Frontend visual check
for pid in sample_ids[:2]:
    print(f"Visit: {site}/?p={pid}&cb={int(time.time())}  (cache-bust)")
```

## Rollback strategy

If something goes wrong (vd wrong CTA URL inserted):

```python
import re

MARKER_PATTERN = re.compile(
    r'<div class="' + re.escape(MARKER) + r'"[^>]*>[\s\S]*?</div>\s*\n*'
)

for post in posts:
    pid = post['id']
    r = requests.get(f'{site}/wp-json/wp/v2/posts/{pid}?context=edit&_fields=content',
                     auth=auth)
    raw = r.json()['content']['raw']

    if MARKER not in raw:
        continue

    # Remove marker block
    new_content = MARKER_PATTERN.sub('', raw)

    requests.post(f'{site}/wp-json/wp/v2/posts/{pid}',
                  json={'content': new_content}, auth=auth)
    print(f"Rolled back {pid}")
```

The marker class makes regex match easy + reliable (no positional guessing).

## Real-world results

Real-world test (2026-05-13, a B2B services site): ~70 cluster posts processed across 3 topic pillars. Different marker per pillar. **0 duplicates, 0 failures.** Re-run skipped all (idempotency verified). Authority gain: pillar incoming links +N (verified via Rank Math `get-incoming-links`).

## Patterns reusable

The marker class pattern applies cho:

| Use case | Marker example | Notes |
|---|---|---|
| Pillar up-links | `{site}-pillar-uplink-{slug}` | Different marker per pillar |
| Author byline injection | `{site}-author-byline-{slug}` | Per author or single marker |
| Trust signal callouts | `{site}-trust-{type}` | vd `-trust-medical`, `-trust-license` |
| Affiliate disclosure | `{site}-affiliate-disclosure` | Single marker site-wide |
| Update notice ("Reviewed: 2026") | `{site}-review-notice-{quarter}` | New marker each quarter, audit old quarters |
| ACF field values (vs `content`) | Use `_meta_key` value as natural marker | Read existing value first |
| Post meta updates | Compare existing value before update | Idempotency built-in |
| Comment thread modifications | Comment ID + content marker | Combined with comment update REST |

## Edge cases

### Edit conflicts (concurrent editor)

WP REST update doesn't lock posts — if user edits post in wp-admin simultaneously, last write wins. For long-running bulk operations:
- Run during low-traffic hours (3-5 AM site time zone)
- Use ETag check via `_envelope` headers (advanced)
- Or use trash status temporarily during bulk to block editor

### Cache invalidation

After bulk update, cache may serve old content. See [`litespeed-cache-mgmt.md`](litespeed-cache-mgmt.md):
- `save_post` hook auto-fires from REST POST → per-post LSC purge automatic
- CDN edge cache may need separate purge

### Permission scope

Bulk update requires `edit_others_posts` capability. Use App Password from admin/editor account, NOT contributor.

## Master dataset pattern — Python module as single source of truth

When bulk-updating N items that share the same field structure (portfolio items, products, team members, courses, articles), **extract the data into a Python module** and have the bulk script import it. Don't hardcode dataset entries in the script itself.

### Why

| Concern | Hardcoded in script | External dataset module |
|---|---|---|
| Edit a fact (e.g. event date) | Find + replace in script | Edit one line in dataset, no script change |
| Re-run after edit | Re-read script logic | Re-import dataset, logic untouched |
| Code review / diff | Mixed: data + logic | Separated: data diff is clear, script unchanged |
| Type-safety / IDE autocomplete | None (strings everywhere) | Python dict fields → IDE shows keys |
| Reusable across multiple scripts | Copy-paste | Import in N scripts |

### Structure

```
scripts/
├── portfolio_data.py        # the dataset (data only, no logic)
├── update_seo.py            # imports dataset, updates SEO meta
├── update_schema.py         # imports same dataset, adds JSON-LD schema
└── verify.py                # imports same dataset, smoke-checks each item live
```

### `portfolio_data.py` shape

```python
PORTFOLIO_ITEMS = [
    {
        "id":         3437,                                       # WP post ID
        "slug":       "event-x-tour-stop-1",
        "artist":     "Performer Name",
        "event_name": "Event Name — Tour Stop 1",
        "type":       "concert",                                  # categorical, drives schema choice
        "year":       2024,
        "date":       "2024-04-13",
        "city":       "TP.HCM",
        "city_en":    "Ho Chi Minh City",
        "capacity":   "15000",
        "tech":       "48 cue pyro + Cold Spark + Laser + CO2",
        "fkw":        "<focus keyword string for SEO>",
        "seo_title":  "<formatted SEO title>",
        "seo_desc":   "<full 150-160 char description>",
    },
    # ... 24 more items, same shape
]

# Type-to-schema dispatch — adding a new event type = one dict entry
TYPE_TO_SCHEMA = {
    "concert":   "Festival",
    "corporate": "BusinessEvent",
    "wedding":   "SocialEvent",
}
```

### Bulk script imports + iterates

```python
from portfolio_data import PORTFOLIO_ITEMS, TYPE_TO_SCHEMA

for it in PORTFOLIO_ITEMS:
    # Update SEO meta via Rank Math
    call_post("rankmath-mcp/update-meta", {
        "id":              it["id"],
        "focus_keyword":   it["fkw"],
        "seo_title":       it["seo_title"],
        "seo_description": it["seo_desc"],
        "canonical_url":   f"https://<site>/portfolio/{it['slug']}/",
    })

    # Add Event schema (subtype dispatched by type)
    schema_type = TYPE_TO_SCHEMA.get(it["type"], "Event")
    call_post("rankmath-mcp/update-meta", {
        "id":   it["id"],
        "meta": {f"rank_math_schema_{schema_type}": build_event_schema(it, schema_type)},
    })
```

### Benefits compounding

- **Single source of truth**: fix the date once, both SEO + Schema update next run
- **Re-runnable**: scripts idempotent — rerun after edit is safe
- **Extensible**: add a field → one dict update + one accessor in script
- **Cross-script reuse**: same `PORTFOLIO_ITEMS` powers SEO update, schema injection, content-reference audit, dashboard generation

### Real-world result

One bulk operation: 25 portfolio items × 14 fields = **350 data points centralized** in one Python module. SEO update + schema injection + verify across 25 items: 0 errors, 100% match. Adding a 26th item: append one dict, rerun — no script change.

### When to escalate from Python dict to a database

| Item count | Recommendation |
|---|---|
| < 50 | Python dict (`portfolio_data.py`) — easiest |
| 50–200 | Still Python dict, but consider splitting by type into multiple modules |
| 200–1000 | SQLite — keep dataset query-able, support partial-update scripts |
| > 1000 | PostgreSQL or other DB; treat the WP site as a view-layer |

The skill's default recommendation is **Python dict module** for almost everything — you rarely cross 200 items per bulk-update concern.

### Anti-patterns

❌ **Mixing data + logic in same file** — code review noise; data changes look like logic changes in git diff

❌ **Maintaining the same dataset in multiple scripts** — duplicate-source drift; one script ships an updated fact, others lag

❌ **CSV instead of Python dict** — loses type info, comments, nested structures; harder to merge-resolve in git

❌ **Pulling dataset from the live WP site** — circular: you're trying to update the site, but you're also reading from the site for the input → race conditions, partial reads. Snapshot to a `.py` file once, then iterate

## Output artifacts

Save to project's `audit/` folder:
- `audit/bulk_plan_{date}.md` — pre-run plan
- `audit/bulk_targets_{date}.json` — post ID list từ Stage 2
- `audit/bulk_log_{date}.json` — Stage 3 execution log
- `audit/bulk_verify_{date}.md` — Stage 4 audit results

## Content injection via mu-plugin `the_content` filter — DRY alternative to editing N posts

**Problem shape**: you need to add the same content block (CTA button + "Related posts" grid + newsletter opt-in) to every existing blog post AND to every future blog post automatically. Naive approach = edit each of 24 posts via REST + remember to edit any new posts manually. Doesn't scale, easy to drift.

**Better pattern**: 1 mu-plugin filter on `the_content` that appends the block at render time. Zero post edits. Auto-applies to new posts. Removable by deleting the mu-plugin file.

### The filter — guarded to `is_singular('post')` only

```php
<?php
// wp-content/mu-plugins/<site>-blog-enhance.php
add_filter( 'the_content', function ( $content ) {
    // Guard chain — only enhance single blog posts in main query
    if ( ! is_singular( 'post' ) ) return $content;
    if ( ! in_the_loop() ) return $content;
    if ( ! is_main_query() ) return $content;

    // CTA block
    $cta = '<div class="blog-cta"><a href="/contact/" class="btn">Get a quote</a></div>';

    // Related posts — 3 from same category, excluding current
    $post_id = get_the_ID();
    $cats    = wp_get_post_categories( $post_id );
    $related = new WP_Query( [
        'category__in'         => $cats,
        'post__not_in'         => [ $post_id ],
        'posts_per_page'       => 3,
        'ignore_sticky_posts'  => true,
        'no_found_rows'        => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ] );

    if ( $related->have_posts() ) {
        $rel_html = '<div class="blog-related"><h3>Related</h3><ul>';
        while ( $related->have_posts() ) {
            $related->the_post();
            $rel_html .= '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
        }
        $rel_html .= '</ul></div>';
        wp_reset_postdata();
    } else {
        $rel_html = '';
    }

    return $content . $cta . $rel_html;
}, 20 );
```

**Guard chain rationale**:
- `is_singular('post')` — only blog posts, not pages / CPTs / archives
- `in_the_loop()` — not in sidebar `WP_Query`, not in RSS feed loop
- `is_main_query()` — not inside a Related Posts widget query calling `apply_filters('the_content', ...)` on an excerpt

Skipping any of these → CTA appears in unexpected places (sidebar widgets, RSS emails, category description outputs, everywhere `the_content` fires).

### Homepage blog section — `wp_footer` JS injection for Canvas pages

Homepage on Canvas templates doesn't render posts. Inject a "Latest from blog" section via footer JS:

```php
add_action( 'wp_footer', function () {
    if ( ! is_front_page() ) return;

    $latest = new WP_Query( [
        'post_type'      => 'post',
        'posts_per_page' => 4,
        'no_found_rows'  => true,
    ] );
    $items = [];
    while ( $latest->have_posts() ) {
        $latest->the_post();
        $items[] = [
            'title' => get_the_title(),
            'url'   => get_permalink(),
            'excerpt' => wp_trim_words( get_the_excerpt(), 20 ),
            'thumb' => get_the_post_thumbnail_url( get_the_ID(), 'medium' ),
        ];
    }
    wp_reset_postdata();

    $json = wp_json_encode( $items );
    ?>
    <script>
    (function () {
        var items = <?php echo $json; ?>;
        var anchor = document.querySelector('#cau-hoi');  // known section on homepage
        if ( ! anchor || ! items.length ) return;

        var html = '<section class="home-blog"><h2>Latest</h2><div class="grid">' +
            items.map(function (i) {
                return '<a href="' + i.url + '"><img src="' + i.thumb + '"><h4>' +
                    i.title + '</h4><p>' + i.excerpt + '</p></a>';
            }).join('') +
            '</div></section>';

        anchor.insertAdjacentHTML('afterend', html);
    })();
    </script>
    <?php
} );
```

Pattern: PHP builds data → JSON-encodes → JS inserts DOM after a known anchor selector (`#cau-hoi` here — replace with a stable anchor from the site's homepage Elementor data). Consistent with the "JS injection" style used by Canvas-template sites — see [`references/non-standard-stacks.md`](../references/non-standard-stacks.md) §"Editing `_elementor_data` when no Elementor MCP" for context on why Canvas pages often prefer JS injection over data mutation.

### Why this beats editing N posts

| Approach | 24 posts | Adding post #25 | Removing the CTA |
|---|---|---|---|
| Edit each post | 24 REST calls, ~5 min each | Remember to edit manually | 24 REST calls again |
| `the_content` filter | 1 mu-plugin deploy | Auto-applies, zero effort | Delete 1 file |

Add + remove is O(1) instead of O(N). Content drift impossible because there's ONE source (the filter). Reviewable via a single file diff.

### Anti-patterns

❌ **Skipping `is_main_query()` guard** → CTA appears in sidebar widget queries, category descriptions, RSS excerpts. The filter fires everywhere `the_content` is applied.

❌ **Hardcoding related-post IDs** → new posts don't get auto-related; you're back to O(N) maintenance. Always query at render time.

❌ **Using `the_content` for markup-heavy blocks with slow queries** → filter fires on every request; heavy `WP_Query` inside = TTFB regression. Cache the related-posts HTML with a transient (5-min TTL) if the site is high-traffic.

❌ **Forgetting `wp_reset_postdata()` after `WP_Query`** → the outer loop's `get_the_ID()` / `get_permalink()` break for the rest of the page.

## Related skills

- [`wp-abilities.md`](../references/wp-abilities.md) — REST API patterns
- [`litespeed-cache-mgmt.md`](litespeed-cache-mgmt.md) — cache after bulk updates
- [`seo-audit.md`](seo-audit.md) — when bulk fix SEO issues
- [`pitfalls.md`](../references/pitfalls.md) — PHP-FPM exhaustion on rapid REST
- [`../references/mu-plugin-patterns.md`](../references/mu-plugin-patterns.md) — safe mu-plugin deploy + verification
- Insight source: weekly distillation 2026-05-13 (idempotent marker pattern, ~70-post real test); 2026-07-07 (the_content filter beats N-post edit)
