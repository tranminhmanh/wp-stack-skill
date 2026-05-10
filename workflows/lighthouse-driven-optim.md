# Workflow: Lighthouse-driven optimization

Use Lighthouse audit data to pick what to optimize, instead of "optimize everything in `/uploads/`". Catches the 5–10% of files that account for 80%+ of page weight on real load paths, skips the 95% no one ever requests.

## When to use

✅ Site has been live for ≥2 weeks (browser-history-based audit data is meaningful).
✅ You are post-launch and tuning for Core Web Vitals score.
✅ User reports "site feels slow" but generic optimization didn't move the needle.
✅ You want to avoid blanket image optimization that risks breaking originals.

❌ Brand-new site with no real traffic — bake-in-defaults via plugin (LiteSpeed Image Optimization auto-pick) instead.

## Anti-pattern: blanket optimize everything

Tempting:
```bash
# DON'T DO THIS
find /var/www/.../wp-content/uploads -name "*.jpg" -exec convert {} -quality 82 {} \;
```

Reasons not to:
1. Most files are never requested (drafts, deleted post media, plugin orphans, year-old galleries).
2. Round-trip JPEG re-encode at the same quality compounds artifacts. Three sessions visibly degrade.
3. Originals stay original — designers may need them for crops / re-uploads.
4. You can't tell what actually changed page weight.

## Anchor-URL gotcha — `#section` inflates measured page weight

**Symptom**: Lighthouse on `https://example.com/` reports 2.5MB. Lighthouse on `https://example.com/#contact` reports 8.0MB on the same page. Same HTML, very different numbers.

**Root cause**: when the URL has an anchor fragment, the browser scrolls to that section on load. Scrolling triggers `IntersectionObserver`-based lazy loaders to fire on every section above the anchor → ALL above-fold images load synchronously → page weight balloons.

**Lesson**: when comparing audits before/after optimization, **always use the same URL without anchor**. If you ran `?#section` once, run it again the same way. Otherwise the delta is noise.

```bash
# Audit homepage cleanly
npx lighthouse https://example.com/ --output=json --output-path=before.json --preset=perf

# Optimize stuff...

# Re-audit the EXACT SAME URL
npx lighthouse https://example.com/ --output=json --output-path=after.json --preset=perf
```

Document the URL in the audit report file name: `before-homepage-no-anchor.json` not just `before.json`.

## Step 1 — Run Lighthouse with full JSON output

```bash
# CLI version (Node.js)
npm install -g lighthouse  # one time
npx lighthouse https://example.com/ \
  --output=json,html \
  --output-path=./audit-$(date +%Y%m%d) \
  --preset=perf \
  --chrome-flags="--headless --no-sandbox"
```

Saves both `audit-YYYYMMDD.report.json` (machine-readable) and `audit-YYYYMMDD.report.html` (human-readable).

For 5 representative pages:
```bash
for path in / /pillar/ /service/ /blog/post-1/ /contact/; do
  slug=$(echo "$path" | tr '/' '-' | sed 's/^-//;s/-$//')
  [ -z "$slug" ] && slug=home
  npx lighthouse "https://example.com$path" \
    --output=json --output-path="audit-${slug}.json" \
    --preset=perf --chrome-flags="--headless --no-sandbox"
done
```

## Step 2 — Extract `total-byte-weight` priority list

The `total-byte-weight` audit lists the URLs the browser **actually fetched** for that page render — sorted by transferred bytes. THIS is the optimization priority list.

```python
import json

with open('audit-home.json') as f:
    audit = json.load(f)

items = audit['audits']['total-byte-weight']['details']['items']
# Top 10 by size
for it in items[:10]:
    print(f"{it['totalBytes']:>10,} bytes  {it['url']}")
```

Sample output:
```
   654,321 bytes  https://example.com/wp-content/uploads/2026/05/hero.jpg
   421,000 bytes  https://example.com/wp-content/uploads/2026/05/team-photo.jpg
   389,000 bytes  https://example.com/wp-content/themes/.../style.css
   ...
```

The hero JPEG at 654KB is target #1. Optimize THAT file (not every JPEG in the folder).

## Step 3 — Cross-reference with `properly-sized-images` audit

This audit identifies images that are larger than their actual rendered display dimensions:

```python
oversized = audit['audits']['uses-responsive-images']['details']['items']
for it in oversized:
    print(f"  {it['url']}")
    print(f"    served:   {it.get('totalBytes', 0):,} bytes")
    print(f"    wasted:   {it.get('wastedBytes', 0):,} bytes")
```

If a 1008×1008 image is rendered at 56×56 (avatar slot), the audit flags `wastedBytes ≈ 380000`. Fix by referencing the `-150x150` WordPress responsive variant (~7KB).

## Step 4 — Optimize the priority list

For each top-N URL from step 2:

1. **Confirm it's actually used** — view the live page, find the element. Sometimes the audit catches transient resources (plugin diagnostic image, error tracker pixel).
2. **Check the sibling responsive variants** — if it's an image, are the WP variants (`-768x768`, `-1024x1024`) also large? The browser may pick a different variant on different viewports.
3. **Apply the right recipe** — see [`references/image-optim-recipes.md`](../references/image-optim-recipes.md) for PNG quantize / JPEG re-encode / WebP conversion.
4. **Replace in place** (after backup) via cPanel UAPI Fileman or FTP. See [`references/deployment.md`](../references/deployment.md) "cPanel Fileman/upload_files".
5. **Clear cache** — see [`references/performance.md`](../references/performance.md) "Cache invalidation playbook".
6. **Re-audit the same URL** — diff `total-byte-weight` before / after.

## Step 5 — Set long cache TTL on optimized files

Optimized images shrink page weight on FIRST load. Long cache TTL keeps the gain on repeat visits and improves Core Web Vitals (`uses-long-cache-ttl` audit). See [`references/performance.md`](../references/performance.md) "LiteSpeed default static-asset TTL fails Lighthouse" for the `.htaccess` long-TTL recipe.

```apache
<FilesMatch "\.(jpe?g|png|gif|webp|avif|svg|ico|woff2?)$">
  Header always set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>
```

## Step 6 — Lighthouse-CLI watch mode for iterative work

When tuning a specific page over multiple optimization rounds:
```bash
# Re-audit + diff in one line
npx lighthouse https://example.com/ --output=json --output-path=current.json --preset=perf --chrome-flags="--headless"
python3 -c "
import json
prev = json.load(open('previous.json'))
curr = json.load(open('current.json'))
prev_w = prev['audits']['total-byte-weight']['numericValue']
curr_w = curr['audits']['total-byte-weight']['numericValue']
prev_s = prev['categories']['performance']['score'] * 100
curr_s = curr['categories']['performance']['score'] * 100
print(f'Page weight: {prev_w/1024:,.0f}KB → {curr_w/1024:,.0f}KB ({(curr_w-prev_w)/1024:+,.0f}KB)')
print(f'Perf score:  {prev_s:.0f}    → {curr_s:.0f}    ({curr_s-prev_s:+.0f})')
"
mv current.json previous.json
```

## Step 7 — When `total-byte-weight` is dominated by render-blocking CSS / JS

Sometimes the top entry is `style.css` at 280KB (Elementor inline + theme CSS combined). Image optimization won't help.

Path forward:
- LiteSpeed Cache → "Critical CSS" feature: extracts only above-fold CSS, defers the rest
- Elementor → Settings → Advanced → "Improved CSS Loading" + "Inline Font Icons"
- Asset CleanUp plugin: disable Elementor scripts on pages that don't use Elementor (e.g. WP Login, custom landing pages built in another tool)

These are addressed in [`references/performance.md`](../references/performance.md). The critical thing is that Lighthouse-driven prioritization **also catches CSS / JS** as priority list items — not just images.

## Sample multi-round optimization log

```
Round 1 (homepage): hero.jpg 654KB → 280KB (q82 progressive). Score: 72 → 81.
Round 2 (homepage): team-photo.jpg 421KB → 180KB. Score: 81 → 86.
Round 3 (homepage): inline CSS too large — switched on Elementor "Improved CSS Loading". Score: 86 → 91.
Round 4: cache TTL applied (1 year). Repeat-visit score: 91 → 96.
```

Each round = 30 minutes of focused work, measurable delta. Beats blind walk-the-tree.

## Cross-references

- [`references/image-optim-recipes.md`](../references/image-optim-recipes.md) — Pillow recipes for PNG / JPEG re-encode
- [`references/performance.md`](../references/performance.md) — cache invalidation, LiteSpeed long-TTL `.htaccess`
- [`references/pitfalls.md`](../references/pitfalls.md) "LiteSpeed lazy-load rewrites `src=""` runtime" — Lighthouse "missing src" red herring
- [`references/a11y-debugging.md`](../references/a11y-debugging.md) — when Lighthouse a11y is also failing on the same page
- [`references/deployment.md`](../references/deployment.md) "cPanel Fileman/upload_files" — push optimized files back to host
