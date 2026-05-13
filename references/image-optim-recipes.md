# Image optimization recipes

Production-tested patterns for compressing the WordPress media library after the fact (e.g. images uploaded by the previous developer at default quality, or before image optimization plugins were added). All examples use Pillow (PIL) stdlib — no SaaS dependency.

## When to use this vs ShortPixel / Imagify / Smush plugins

| Approach | When |
|---|---|
| **ShortPixel / Imagify (plugin)** | New uploads going forward + bulk-optimize on first install (use the plugin) |
| **This recipe (Pillow scripts)** | Site already optimized once, but specific large images need a deeper pass; or no plugin license; or one-off audit-driven optimization where Lighthouse flags specific URLs |

The two are complementary. A plugin is the right baseline; this is for the residual top-N offenders after the plugin's first pass.

## PNG quantization — choose the method by mode

PNG with many colors (gradient, photo-style logo, screenshot of UI with shadows) compresses dramatically when reduced to a 256-color palette + dither. Pillow's `quantize()` has 4 methods — pick by image mode:

| Method constant | Method # | Works for | Notes |
|---|---|---|---|
| `Image.MEDIANCUT` | 0 | RGB only | Highest visual quality, slower |
| `Image.MAXCOVERAGE` | 1 | RGB only | Faster, slightly worse quality |
| `Image.FASTOCTREE` | 2 | RGB **or** RGBA | Default fallback — works for everything |
| `Image.LIBIMAGEQUANT` | 3 | RGBA | Best quality but requires libimagequant-dev system package |

```python
from PIL import Image

def quantize_png(input_path, output_path, colors=256):
    im = Image.open(input_path)

    # Pick method by mode
    if im.mode == "RGBA":
        method = Image.FASTOCTREE   # MEDIANCUT errors on RGBA
    elif im.mode == "RGB":
        method = Image.MEDIANCUT    # higher quality for opaque
    else:
        im = im.convert("RGBA")
        method = Image.FASTOCTREE

    im_q = im.quantize(colors=colors, method=method, dither=Image.FLOYDSTEINBERG)
    im_q.save(output_path, format="PNG", optimize=True)
```

⚠️ **`MEDIANCUT` on RGBA raises `ValueError`**: `"Fast Octree (method == 2) and libimagequant (method == 3) are the only valid methods for quantizing RGBA images"`. Always branch by mode.

**Real-world reduction**:
- 1254×1254 RGB logo with gradient (117K unique colors): 1.4MB → 209KB (**86% reduction**)
- 100×100 / 150×150 / 300×300 thumbnail variants: 70–80% reduction each
- Flat icon (already <16 colors): negligible — skip these

**When to skip PNG quantization**:
- Image is already a palette PNG (mode `P`)
- Image has fewer than ~16 unique colors (flat icon, simple logo) — quantize gains <5%
- Image is the target of pixel-perfect comparison (legal logo, brand asset locked by guidelines)

## JPEG re-encode — q82 progressive

WordPress core saves JPEG at quality ~90–95 by default. Re-encoding at q82 progressive gives 30–40% reduction on the original with no perceptible visual loss. For variants that are already smaller (downscaled by WP), the gain is 5–10% — diminishing returns.

```python
def reencode_jpeg(input_path, output_path, quality=82):
    im = Image.open(input_path)
    if im.mode in ("RGBA", "P"):
        im = im.convert("RGB")  # JPEG has no alpha
    im.save(output_path, format="JPEG", quality=quality,
            optimize=True, progressive=True)
```

**Why progressive**: progressive JPEG renders in passes (low-res first, then refines). On slow connections, the user sees a blurry preview immediately rather than top-down stripes. No file-size penalty.

**Quality sweet spot**:
- q82 = balance for body / hero images (DEFAULT)
- q70 = aggressive, OK for blog post thumbnails / lightbox preview
- q90 = print-quality, only for portfolio / gallery hero
- < q60 = visible artifacts on faces and gradients — avoid

**Real-world reduction**:
- 1888×1072 hero JPEG q90 → q82: 1MB → 350KB (~65% reduction)
- 768×436 srcset variant q90 → q82: 120KB → 95KB (~20% reduction)
- 100×100 thumbnail: 8KB → 7KB (~10%)

## Optimize the entire WordPress srcset family — not just the original

WordPress auto-generates responsive variants on upload: `-100x100`, `-150x150`, `-300x300`, `-768x768`, `-1024x1024`, `-1536x872` (depending on theme), plus the original. The browser picks ONE variant per image based on the viewport / DPR — usually NOT the original.

**Why this matters**: optimizing only the original (1888×1072) gives zero gain on mobile, where the browser loads `-768x436`.

**Find which variant the browser actually loads**:
```bash
# From a Lighthouse JSON report — total-byte-weight audit
jq '.audits["total-byte-weight"].details.items[] | select(.url | test("uploads")) | .url' lh.json
```

This lists URLs Lighthouse measured being fetched. That's the priority list — optimize THOSE files, not the entire `/uploads/` tree.

**Bulk script — re-encode all srcset variants of a single image**:
```python
import os, glob
from PIL import Image

def reencode_family(base_path):
    """
    base_path = /path/to/uploads/2026/05/hero.jpg
    Re-encodes hero.jpg + hero-100x100.jpg + hero-300x300.jpg + ... in place.
    """
    base, ext = os.path.splitext(base_path)
    pattern = base + "*" + ext
    for f in glob.glob(pattern):
        if ext.lower() in (".jpg", ".jpeg"):
            reencode_jpeg(f, f, quality=82)
        elif ext.lower() == ".png":
            quantize_png(f, f, colors=256)
        size = os.path.getsize(f)
        print(f"{f}: {size:,} bytes")
```

⚠️ **Backup before in-place re-encode**: `cp -r 2026/05 /tmp/backup-2026-05/` first. Optimization is one-way; if the result is too aggressive, restore from backup.

## Avoid the "global PIL re-encode of every image" trap

Tempting: walk all of `/wp-content/uploads/`, re-encode everything. Don't.

Reasons:
1. Most images are never loaded (pages deleted, posts unpublished, drafts in trash, plugin orphans).
2. Some files are intentional originals (raw photography for a designer to crop, source assets for posters).
3. Round-trip JPEG re-encode (decode → encode) compounds artifacts even at the same quality. Doing it 3× in 3 sessions visibly degrades.

**Correct discipline**: Lighthouse-driven optimization. Identify the ~10–30 files Lighthouse flags per page, optimize those, move on. See [`workflows/lighthouse-driven-optim.md`](../workflows/lighthouse-driven-optim.md).

## WebP conversion — when worth it

WebP at q85 typically beats JPEG q82 by 30–50% on the same image. Smaller AVIF possible (q70 ~30% smaller than WebP) but limited browser support outside modern Chrome/Edge/Firefox.

**WebP trade-off**:
- ✅ 30–50% size reduction over JPEG
- ❌ Different file extension → invalidates existing `srcset` HTML, `<img src=>` references, cached pages
- ❌ Older Safari versions (<14) lack support — fallback chain needed

**Recommendation**: do NOT convert existing JPEGs to WebP via direct re-save. Use a plugin (LiteSpeed Image Optimization, ShortPixel, Imagify) that maintains JPEG fallback + serves WebP via content-negotiation `<picture>` tags. The plugin handles browser sniffing + URL rewriting; doing it manually breaks every `<img src=>` reference scattered across `_elementor_data` JSON, post content HTML, and theme templates.

## Verifying optimization actually shrunk the file

A 0% reduction or +X% increase means the source was already compressed at or below the target quality.

```python
import os
def report(path_before, path_after):
    a = os.path.getsize(path_before)
    b = os.path.getsize(path_after)
    pct = 100 * (1 - b / a)
    print(f"{path_after}: {a:,} → {b:,} bytes  ({pct:+.1f}%)")
```

If a JPEG re-encode at q82 gives `+5%` instead of `-30%`, the source is already heavily compressed (probably q60–70 from a previous pass) → leave it alone. Re-encoding at the same low quality compounds artifacts without saving bytes.

## WordPress `srcset` variants must be optimized as a set

⚠️ **Trap**: Optimizing only the "original" upload file → browser still picks a `srcset` variant URL → Lighthouse flags both URLs as oversized. Each srcset variant is a separate file; each must be optimized.

### Symptom

```html
<!-- WP auto-generated srcset (5 variants) -->
<img src="https://site/hero-1920.webp"
     srcset="https://site/hero-300w.webp 300w,
             https://site/hero-768w.webp 768w,
             https://site/hero-1024w.webp 1024w,
             https://site/hero-1536w.webp 1536w,
             https://site/hero-1920.webp 1920w">
```

Optimizing `hero-1920.webp` only → other 4 variants stay unoptimized → Lighthouse "Properly size images" audit lists `hero-768w.webp` because browser picks that for current viewport.

### Browser picks 1 variant by DPR × viewport

```
Mobile (375px viewport, 2x DPR) → picks 768w variant (375 × 2 = 750, nearest)
Tablet (768px, 1x) → 1024w
Desktop (1280px, 1x) → 1536w
Desktop retina (1440px, 2x) → 1920w
```

So Lighthouse audit URL depends on test device. Optimizing only the largest file leaves common mobile variants oversized.

### Fix: optimize all variants together

```python
import os, glob
from PIL import Image

basename = "hero-stage-effects"
src_dir = "/path/to/uploads/2024/05/"

# Find all WP-generated variants of same basename
variants = glob.glob(f"{src_dir}{basename}-*.webp") + glob.glob(f"{src_dir}{basename}.webp")

for v in variants:
    # Re-optimize each variant
    im = Image.open(v)
    im.save(v, "WEBP", quality=85, optimize=True)
    print(f"Re-optimized: {v} ({os.path.getsize(v):,} bytes)")
```

### Pattern: pre-optimize before upload

Trước khi upload, optimize **source file** at correct quality. WP auto-generates srcset variants from source → inherit optimization level. Saves the "after-the-fact" pain.

### Cross-link với Lighthouse audit

Lighthouse `total-byte-weight` audit lists URLs **actually fetched by browser** (current device + viewport). Use Largest Payloads từ audit → optimize specific URLs first → re-test on multiple device emulations để confirm.

## Replicate API rate limit — >2 parallel = 429

Launch 4 parallel calls với `&` + `wait` → 1 succeeds, 3 fail HTTP 429 immediately (NOT after N requests/min). Rate limit applies per-IP per-second window.

```bash
# WRONG — parallel
for prompt in p1 p2 p3 p4; do
    curl -X POST -d "{\"prompt\":\"$prompt\"}" "$REPLICATE_API" &
done
wait
# → 1 success, 3 429 fails
```

### Fix: Sequential với sleep

```bash
# RIGHT — sequential với gap
for prompt in p1 p2 p3 p4; do
    curl -X POST -d "{\"prompt\":\"$prompt\"}" "$REPLICATE_API"
    sleep 10  # 5-10s gap minimum
done
```

### Cost note

Failed 429 calls KHÔNG bị charge (request never reaches prediction creation). So retry is safe but wastes time.

### Batch 2-by-2 max

Nếu cần concurrency for speed:
```bash
# Batch of 2 parallel, then sleep 10s
batch_size=2
for batch in $(seq 0 $batch_size $total); do
    for i in $(seq 0 1); do
        idx=$((batch + i))
        if [ $idx -lt $total ]; then
            curl -X POST "$REPLICATE_API" &
        fi
    done
    wait
    sleep 10
done
```

### Reusability

Same rate-limit applies cho most AI image generation APIs (Replicate, DALL-E, Midjourney, Stable Diffusion Cloud). Pattern: max 2 parallel, sleep 10s between batches.

## Flux 2 Pro aspect_ratio — 11 ratios only, no 21:9

Flux 2 Pro model supports exactly 11 `aspect_ratio` values. Cannot pass arbitrary ratio (vd 21:9, 32:9 ultrawide):

| Aspect ratio | Use case |
|---|---|
| 1:1 | Square (Instagram post, profile pic) |
| 16:9 | Widescreen video / OG image |
| 9:16 | Vertical video / Story / Reels |
| 4:3 | Classic photo / TV legacy |
| 3:4 | Vertical photo / portrait |
| 3:2 | DSLR landscape |
| 2:3 | DSLR portrait |
| 4:5 | Instagram portrait |
| 5:4 | Slight landscape |
| 21:9 | ❌ NOT supported |
| 32:9 | ❌ NOT supported |

### Workaround cho 21:9 ultrawide

```python
# Generate at 16:9 (close enough) + crop to 21:9 via CSS object-position
# OR
# Use Flux Schnell / Dev (different aspect ratio support — verify per model)
# OR
# Generate at 16:9 + Photoshop / GIMP / `convert` crop center
```

CSS crop pattern:
```css
.hero-ultrawide {
    aspect-ratio: 21 / 9;
    overflow: hidden;
}
.hero-ultrawide img {
    width: 100%;
    height: auto;
    object-fit: cover;
    object-position: center 30%;  /* shift up for portrait subjects */
}
```

### Cross-reference

OG image generation typically uses 1.91:1 ratio (Facebook/Twitter spec). Flux's nearest is 16:9 (1.78:1) — visually close enough. See [`workflows/og-image-generation.md`](../workflows/og-image-generation.md) for OG-specific generation patterns.

## Cross-references

- [`workflows/lighthouse-driven-optim.md`](../workflows/lighthouse-driven-optim.md) — pick targets via the audit, not by walking the tree
- [`references/deployment.md`](deployment.md) "cPanel Fileman/upload_files" — push optimized files back to the host
- [`references/pitfalls.md`](pitfalls.md) "LiteSpeed lazy-load rewrites `src=""` runtime" — Lighthouse "missing src" red herring on optimized images
- [`references/performance.md`](performance.md) "LiteSpeed default static-asset TTL" — long cache TTL recipe so optimized files stay cached
