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

## Cross-references

- [`workflows/lighthouse-driven-optim.md`](../workflows/lighthouse-driven-optim.md) — pick targets via the audit, not by walking the tree
- [`references/deployment.md`](deployment.md) "cPanel Fileman/upload_files" — push optimized files back to the host
- [`references/pitfalls.md`](pitfalls.md) "LiteSpeed lazy-load rewrites `src=""` runtime" — Lighthouse "missing src" red herring on optimized images
- [`references/performance.md`](performance.md) "LiteSpeed default static-asset TTL" — long cache TTL recipe so optimized files stay cached
