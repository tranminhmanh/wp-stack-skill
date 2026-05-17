# Workflow: OG Image Generation at Scale

100% Open Graph image coverage for N pages of a WordPress site, $0–0.20 total cost. Pattern proven across 52 pages.

## When to apply

✅ The site has ≥10 pages that need a social-share preview (homepage, pillars, services, blog posts).
✅ You need diacritic-safe text overlay (đ ạ ọ ấ, ş, ż, etc.) — AI image generators are bad at non-Latin text.
✅ You need brand consistency across N images — programmatic text overlay beats manual Photoshop / Canva.

❌ Site < 10 pages → manual Canva is faster.
❌ The site has an in-house brand designer → let them handle it.

## 4-tier coverage strategy

| Tier | Target | Cost / image | When |
|---|---|---|---|
| 1 — Unique OG | Critical pages (homepage, pillars, services, blogs) | $0.003–$0.04 (AI) or $0 (PHP-only) | Brand-critical, social share |
| 2 — Inherit parent | Subpages of a pillar / hub | $0 | Reinforces parent-child relationship |
| 3 — Default site fallback | Menu / system pages (about, contact, hubs) | $0 | Low social-share priority |
| 4 — Verification | All pages smoke test | $0 | Pre-launch QA |

Real example: 19 unique × $0.04 + 26 inherit × $0 + 7 fallback × $0 = **$0.175 for 52 pages**.

## Tier 1: Unique OG generation

### Decision matrix: PHP GD only vs AI background + PHP overlay

| Aspect | Style A: 100% PHP GD vector | Style B: AI photo + PHP overlay |
|---|---|---|
| Cost | $0 | $0.003–$0.04 / img (Flux schnell / dev / pro) |
| Speed | ~1s / image | ~10–60s / image (gen) + 1s overlay |
| Visual richness | Flat colors, vector ship silhouette | Photorealistic cinematic, brand cohesion |
| Trust signal for B2B | Lower | Higher |
| Diacritic-safe text | OK (TTF font) | OK (TTF overlay) |

**Rule**: pick ONE style for the whole site. Do NOT mix. A mixed style across N OGs looks broken (homepage flat-vector + pillars photorealistic = brand cohesion shattered).

Recommended: **Style B** for B2B brand sites — photorealism = trust signal.

### Style B workflow

```
1. AI gen BACKGROUND ONLY (no text in the prompt — AI is bad at text)
2. PHP GD overlays branded text consistently
3. Register WP attachment + bind Rank Math og:image meta
4. Verify og:image renders on the frontend
```

### AI prompt template (B2B brand-safe)

```
Cinematic [aerial|drone] photograph of [SUBJECT] at [TIME_OF_DAY],
[LANDMARK] visible in distance, [INDUSTRY_CONTEXT],
[WEATHER_MOOD], deep navy blue [PALETTE_HINT], modern infrastructure,
ultra-detailed photorealistic, professional [INDUSTRY] photography,
empty atmospheric space on LEFT 40%, [SUBJECT] on RIGHT 60%,
[BRAND_AESTHETIC]. 16:9 aspect ratio, 1200x630
```

**Negative prompts** (paste into all):
```
no text, no letters, no logos, no watermark, no people faces,
no cartoon style, no neon colors, no specific brand logos,
no painted letters on subjects, no signage
```

⚠️ Schnell still renders fake text on subjects despite "no text" — explicit "no painted letters, no signage" + the Pro tier respects negatives better.

### Composition rule (consistent across N images)

- **Left 40%**: empty atmospheric space for text overlay (gradient navy fade keeps text readable)
- **Right 60%**: subject (port, ship, landmark, product)

If the AI generates a subject in the middle or on the left → hard to overlay text. The prompt MUST specify "empty space on LEFT, subject on RIGHT".

### PHP overlay recipe

See [`templates/snippets/og-image-generator.php`](../templates/snippets/og-image-generator.php) — full code: gradient `navy_dark`→`navy_med`, diagonal accent lines (alpha), branded text with Inter Bold / Variable TTF, rounded-rectangle helper (PHP GD has no native), badge / H1 / H2 / tagline / CTA / URL layered.

## Tier 2: Inherit parent OG

```php
$pillars = [
    260 => ['attach' => 593],  // Pillar A → og-A.png
    459 => ['attach' => 594],  // Pillar B → og-B.png
];

$subpages = get_posts([
    'post_type' => 'page',
    'post_parent__in' => array_keys($pillars),
    'numberposts' => -1,
]);

foreach ($subpages as $sub) {
    $attach_id = $pillars[$sub->post_parent]['attach'];
    update_post_meta($sub->ID, 'rank_math_facebook_image_id', $attach_id);
    update_post_meta($sub->ID, 'rank_math_facebook_image', wp_get_attachment_url($attach_id));
    update_post_meta($sub->ID, 'rank_math_twitter_image_id', $attach_id);
    update_post_meta($sub->ID, 'rank_math_twitter_image', wp_get_attachment_url($attach_id));
    set_post_thumbnail($sub->ID, $attach_id);
}
```

Visual benefit: subpages share the pillar OG → reinforces the parent-child relationship for crawlers + social-share viewers.

## Tier 3: Default site OG fallback

```php
$rm_titles = get_option('rank-math-options-titles', []);
$rm_titles['open_graph_image_id'] = $homepage_attach_id;
$rm_titles['open_graph_image'] = wp_get_attachment_url($homepage_attach_id);
$rm_titles['twitter_card_type'] = 'summary_large_image';
$rm_titles['homepage_facebook_image_id'] = $homepage_attach_id;
update_option('rank-math-options-titles', $rm_titles);
```

A single config → N pages auto-inherit when Rank Math finds no per-page OG.

## Tier 4: 100% coverage verification

```bash
for url in $ALL_URLS; do
    og=$(curl -s "$url" | grep -oE '<meta property="og:image" content="[^"]+"')
    [ -n "$og" ] && echo "OK $url" || echo "MISSING $url"
done
```

Target: 100% of pages have `og:image` meta rendering. Missing = bug, fix before launch.

## AI image generation tooling (optional, for scale)

### Replicate API (recommended for indie / small team)

Pay-as-you-go, no prepayment. Setup: link a card → get a token → ready.

vs Google Imagen, which requires a prepaid credit model — projects with $0 balance get all APIs blocked. Hard to bypass for an individual.

### Reusable `genimg.sh` script (key features)

```bash
# 3-tier model selector via env var
case "${MODEL:-schnell}" in
    schnell) MODEL_PATH="black-forest-labs/flux-schnell"  ; PRICE="\$0.003" ;;
    dev)     MODEL_PATH="black-forest-labs/flux-dev"      ; PRICE="\$0.025" ;;
    pro)     MODEL_PATH="black-forest-labs/flux-1.1-pro"  ; PRICE="\$0.04"  ;;
esac

# Sync mode via Prefer: wait header
curl -sS -X POST "https://api.replicate.com/v1/models/${MODEL_PATH}/predictions" \
    -H "Authorization: Bearer $REPLICATE_API_TOKEN" \
    -H "Prefer: wait=60" \
    -d "$REQUEST_JSON"

# Async fallback poll loop if wait times out
# Auto-load .env from project root
# HTTP error mapping (401/402/404/422/429) per code
# macOS / Linux base64 -D vs --decode compat
```

### Schnell vs Pro decision matrix

| Use case | Model | Reason |
|---|---|---|
| Drafts / concepts / atmospheric scenes | schnell ($0.003) | Cheap, fast, good enough |
| Batch mass-produce simple subjects | schnell | Cost-effective |
| Critical pages (homepage, pillar hero) | pro ($0.04) | Photorealistic textures, composition adherence |
| Realism-critical (industrial machinery, accurate landmarks) | pro | Schnell renders cartoonish / inaccurate |
| Final / production batch (no time to regen) | pro | Higher first-pass success rate |

**Visual review loop pattern** (cost-effective):
1. Generate N with schnell ($0.003 / img)
2. Visual review → grade ⭐ 1-5
3. Identify weak ones (⭐ ≤3 or with quirks: cartoonish, fake text, shape inaccuracy)
4. Regen the weak ones with Pro ($0.04 / img)

Real example, 5 blog posts: 2/5 weak schnell + 1 borderline → 3 Pro regens. Final cost $0.135 (vs all-Pro $0.20) → 33% saved.

### Replicate rate limits (gotchas)

**Per-minute limit (free tier, credit balance < $5)**:
- 6 requests / minute (1 burst)
- Batch generate: `sleep 11` between calls

**Parallel limit (any tier)**: > 2 calls in parallel → 1 succeeds, others fail HTTP 429 IMMEDIATELY (not after N requests/min). Tested 4 calls with `&` + `wait` → 3/4 fail 429 immediately.

**Cost note**: failed 429 calls are NOT charged — the request did not create a prediction. Safe to retry.

**Workarounds**:
- Max 2 parallel for an urgent batch (still sleep 5–10s between pairs)
- Sequential with `sleep 5–10` for batches > 3 (recommended)
- Top up credit > $5 to unlock per-minute burst (parallel limit still applies)

```bash
# Sequential batch (safest)
for prompt in "${PROMPTS[@]}"; do
    sleep 11  # per-minute rate limit guard
    ./scripts/genimg.sh "$prompt" ...
done

# Pair-parallel (if urgent)
for ((i=0; i<${#PROMPTS[@]}; i+=2)); do
    ./scripts/genimg.sh "${PROMPTS[i]}" ... &
    [ -n "${PROMPTS[i+1]}" ] && ./scripts/genimg.sh "${PROMPTS[i+1]}" ... &
    wait
    sleep 10  # cooldown before next pair
done
```

## Auto-find user attachments

When the user says "I already saved them" without giving a path:

```bash
# macOS Downloads dir
find ~/Downloads -type f \( -iname "*.jpg" -o -iname "*.png" \) -mmin -60 \
  -not -path "*/Library/Caches/*"

# Bulk-rename per slug convention
for f in "$SRC"/*.png; do
    case "$f" in
        *Country1*) cp "$f" "$DST/og-country1.png" ;;
        *Country2*) cp "$f" "$DST/og-country2.png" ;;
    esac
done
```

**Lesson**: don't ask the user for a path immediately → first try to auto-find in Downloads / Desktop / recent files, save the user time.

## v1 → v2 migration (style consistency)

If the site has a legacy OG style (e.g. PHP GD vector) and you want to migrate to AI photos:

```
1. Generate AI background only (Flux Pro $0.04 per critical page)
2. Run the same overlay script (just change the input file path)
3. Overwrite the same WP attachment ID file
4. wp_generate_attachment_metadata() refreshes size / dimensions
5. og:image cache flush (see references/performance.md "Cache invalidation")
```

Migration cost: ~$0.04 + 5 min of work per page. ROI: 100% visual cohesion, brand consistency, social-share quality.

## Flux 2 Pro img-to-img — brand-consistent OG image recipe

When the site already has hero photography on it (real product shots, real venue photos, real team portraits), use **Flux 2 Pro with `INPUT_IMAGES=`** (img-to-img mode) instead of text-to-image. The reference image preserves color palette + lighting + style; the prompt adds new composition.

### When this beats plain Pro text-to-image

| Mode | Cost | When |
|---|---|---|
| Flux schnell text-to-image | $0.003 | Drafts, atmospheric scenes, no brand reference yet |
| Flux 2 Pro text-to-image | $0.04 | Brand hero, premium, weak schnell regens |
| **Flux 2 Pro img-to-img** (`INPUT_IMAGES=...`) | **$0.06** | Match existing brand photography on a live site |

The +$0.02 over plain Pro is worth it when brand consistency matters more than novelty. For OG image that needs to look like "the same brand" as the live hero, this is the right tier.

### Recipe

```bash
MODEL=pro2 \
ASPECT=16:9 \
RESOLUTION="1 MP" \
QUALITY=92 \
INPUT_IMAGES="https://<site>/wp-content/uploads/2026/05/real-product-hero.jpg" \
./scripts/genimg.sh \
  "[hero subject], [composition style], [lighting], [color palette], [aesthetic], no text no logo, clean 16:9 banner composition" \
  ./out/og-home og-hero
```

Output: 1344×752 px (16:9 of 1 MP), ~250–300 KB JPG q92.

### Aspect ratio note

- Target OG spec: 1200×630 (= 1.91:1)
- Flux 2 Pro `aspect=16:9`: 1344×752 (= 1.78:1)
- Facebook / LinkedIn crop ~5% top + bottom → still looks fine

Don't fight the aspect ratio — accept the ~5% crop. If you need exact 1.91:1: Pillow post-crop to 1200×630 or switch to `pro2-ultra` with `21:9` (cost: $0.10).

### Two critical prompt tips

1. **`"no text no logo"` is mandatory** — Flux models hallucinate text/logos at random. ~30% of outputs without this token contain garbled fake text.
2. **Context descriptors matter more than nouns** — "premium B2B wholesale aesthetic" / "cinematic event aesthetic" dial Flux toward commercial photography style instead of generic stock-photo look.

### What the reference image preserves vs not

The input image (`INPUT_IMAGES=...`) provides:
- ✓ Color palette (dominant hues)
- ✓ Lighting direction + intensity
- ✓ Composition style (close-up vs wide)
- ✓ Texture quality (polished vs raw)

It does NOT preserve:
- ✗ Subject identity (a person's face won't match)
- ✗ Specific product details
- ✗ Exact background

Pick a reference photo that has the brand's mood/lighting; use the prompt to specify the subject content.

### Upload + attach workflow

```bash
# 1. Upload to WP Media via REST
ATTACH_RESP=$(curl -u "$WP_USER:$WP_PASS" -X POST "$WP_SITE/wp-json/wp/v2/media" \
  -H "Content-Disposition: attachment; filename=\"og-home.jpg\"" \
  -H "Content-Type: image/jpeg" \
  --data-binary @out/og-home/og-hero-001.jpg)
ATTACH_ID=$(echo "$ATTACH_RESP" | jq -r '.id')

# 2. Set as featured image on the target page
#    Rank Math uses featured_media as og:image fallback when raster-only
#    (see references/rankmath.md "OG image resolution chain")
curl -u "$WP_USER:$WP_PASS" -X POST "$WP_SITE/wp-json/wp/v2/pages/<PAGE_ID>" \
  -H "Content-Type: application/json" \
  -d "{\"featured_media\": $ATTACH_ID}"

# 3. Verify
curl -s "$WP_SITE/<page-path>/?cb=$(date +%s)" | grep -oE '<meta property="og:image"[^>]+>'
```

### Anti-patterns

❌ Forget `no text no logo` → wasted generations
❌ Low-quality / blurry / off-brand reference → output inherits the flaws
❌ Skip the verification step → social shares break silently
❌ Stash generated images outside `/uploads/` → Rank Math + LiteSpeed can't reference them as attachments

## Related

- [`templates/snippets/og-image-generator.php`](../templates/snippets/og-image-generator.php) — PHP GD recipe + WP attachment integration
- [`references/seo-checklist.md`](../references/seo-checklist.md) — WP attachment integration for og:image (Rank Math meta keys)
- [`references/performance.md`](../references/performance.md) — cache flush after updating OG
