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

## Output artifacts

Save to project's `audit/` folder:
- `audit/bulk_plan_{date}.md` — pre-run plan
- `audit/bulk_targets_{date}.json` — post ID list từ Stage 2
- `audit/bulk_log_{date}.json` — Stage 3 execution log
- `audit/bulk_verify_{date}.md` — Stage 4 audit results

## Related skills

- [`wp-abilities.md`](../references/wp-abilities.md) — REST API patterns
- [`litespeed-cache-mgmt.md`](litespeed-cache-mgmt.md) — cache after bulk updates
- [`seo-audit.md`](seo-audit.md) — when bulk fix SEO issues
- [`pitfalls.md`](../references/pitfalls.md) — PHP-FPM exhaustion on rapid REST
- Insight source: weekly distillation 2026-05-13 (idempotent marker pattern, ~70-post real test)
