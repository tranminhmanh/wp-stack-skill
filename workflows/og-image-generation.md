# Workflow: OG Image Generation at Scale

100% Open Graph image coverage cho N pages WordPress site, $0–0.20 total cost. Pattern proven trên ShipAsia 52 pages.

## Khi nào áp dụng

✅ Site có ≥10 pages cần social share preview (homepage, pillars, services, blogs).
✅ Cần Vietnamese diacritics-safe text (đ ạ ọ ấ...) — AI image generators kém với text Việt.
✅ Brand consistency cross N images — text overlay programmatic > manual Photoshop/Canva.

❌ Site < 10 pages → manual Canva nhanh hơn.
❌ Site có brand designer in-house → để họ làm.

## 4-tier coverage strategy

| Tier | Target | Cost/image | When |
|---|---|---|---|
| 1 — Unique OG | Critical pages (homepage, pillars, services, blogs) | $0.003–$0.04 (AI) hoặc $0 (PHP-only) | Brand-critical, social share |
| 2 — Inherit parent | Subpages của pillar/hub | $0 | Reinforces parent-child relationship |
| 3 — Default site fallback | Menu/system pages (about, contact, hubs) | $0 | Low social share priority |
| 4 — Verification | All pages smoke test | $0 | Pre-launch QA |

ShipAsia case: 19 unique × $0.04 + 26 inherit × $0 + 7 fallback × $0 = **$0.175 cho 52 pages**.

## Tier 1: Unique OG generation

### Decision matrix: PHP GD only vs AI background + PHP overlay

| Aspect | Style A: 100% PHP GD vector | Style B: AI photo + PHP overlay |
|---|---|---|
| Cost | $0 | $0.003–$0.04/img (Flux schnell/dev/pro) |
| Speed | ~1s/image | ~10–60s/image (gen) + 1s overlay |
| Visual richness | Flat colors, vector ship silhouette | Photorealistic cinematic, brand cohesion |
| Trust signal B2B | Lower | Higher |
| Vietnamese text | OK (TTF font) | OK (TTF overlay) |

**Rule**: Pick ONE style cho toàn site. Don't mix. Mixed style across N OGs looks broken (homepage flat vector + pillars photorealistic = brand cohesion vỡ).

Recommended: **Style B** cho B2B brand sites — photorealism = trust signal.

### Style B workflow

```
1. AI gen BACKGROUND ONLY (no text in prompt — AI kém với text)
2. PHP GD overlay branded text consistently
3. Register WP attachment + bind Rank Math og:image meta
4. Verify og:image render trên frontend
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

**Negative prompts** (paste vào tất cả):
```
no text, no letters, no logos, no watermark, no people faces,
no cartoon style, no neon colors, no specific brand logos,
no painted letters on subjects, no signage
```

⚠️ Schnell vẫn render fake text trên subjects dù có "no text" — explicit "no painted letters, no signage" + Pro tier respect tốt hơn.

### Composition rule (consistent qua N images)

- **Left 40%**: Empty atmospheric space cho text overlay (gradient navy fade làm text legible)
- **Right 60%**: Subject (port, ship, landmark, product)

Nếu AI sinh subject ở giữa hoặc bên trái → khó overlay text. Prompt MUST specify "empty space on LEFT, subject on RIGHT".

### PHP overlay recipe

Xem [`templates/snippets/og-image-generator.php`](../templates/snippets/og-image-generator.php) — full code: gradient navy_dark→navy_med, diagonal accent lines (alpha), branded text với Inter Bold/Variable TTF, rounded rectangle helper (PHP GD không có native), badge/H1/H2/tagline/CTA/URL layered.

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

Visual benefit: subpages share pillar OG → reinforces parent-child relationship trong eyes of crawlers + social share viewers.

## Tier 3: Default site OG fallback

```php
$rm_titles = get_option('rank-math-options-titles', []);
$rm_titles['open_graph_image_id'] = $homepage_attach_id;
$rm_titles['open_graph_image'] = wp_get_attachment_url($homepage_attach_id);
$rm_titles['twitter_card_type'] = 'summary_large_image';
$rm_titles['homepage_facebook_image_id'] = $homepage_attach_id;
update_option('rank-math-options-titles', $rm_titles);
```

Single config → N pages auto-inherit khi Rank Math không tìm thấy per-page OG.

## Tier 4: 100% coverage verification

```bash
for url in $ALL_URLS; do
    og=$(curl -s "$url" | grep -oE '<meta property="og:image" content="[^"]+"')
    [ -n "$og" ] && echo "OK $url" || echo "MISSING $url"
done
```

Target: 100% pages có `og:image` meta render. Missing = bug, fix trước launch.

## AI image generation tooling (optional, for scale)

### Replicate API (recommended cho indie/small-team)

Pay-as-you-go, không prepay. Setup: link card → get token → ready.

Vs Google Imagen yêu cầu prepay credit model — project bị "Prepay tier" với $0 balance → toàn bộ APIs blocked. Bypass khó cho cá nhân.

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

# Async fallback poll loop nếu wait timeout
# Auto-load .env from project root
# HTTP error mapping (401/402/404/422/429) per-code guidance
# macOS/Linux base64 -D vs --decode compat
```

### Schnell vs Pro decision matrix

| Use case | Model | Reason |
|---|---|---|
| Drafts / concepts / atmospheric scenes | schnell ($0.003) | Cheap, fast, good enough |
| Batch mass-produce simple subjects | schnell | Cost-effective |
| Critical pages (homepage, pillar hero) | pro ($0.04) | Photorealistic textures, composition adherence |
| Realism-critical (industrial machinery, accurate landmarks) | pro | Schnell render cartoonish/inaccurate |
| Final/production batch (no time to regen) | pro | Higher first-pass success rate |

**Visual review loop pattern** (cost-effective):
1. Generate N với schnell ($0.003/img)
2. Visual review → grade ⭐ 1-5
3. Identify weak ones (⭐ ≤3 hoặc có quirks: cartoonish, fake text, shape inaccuracy)
4. Regen weak ones với Pro ($0.04/img)

ShipAsia 5 blogs: 2/5 weak schnell + 1 borderline → 3 Pro regens. Final cost $0.135 (vs all-Pro $0.20) → save 33%.

### Replicate rate limits (gotcha)

**Per-minute limit (free tier, credit balance < $5)**:
- 6 requests/minute (1 burst)
- Batch generate: `sleep 11` giữa mỗi call

**Parallel limit (any tier)**: > 2 calls đồng thời → 1 succeed, others fail HTTP 429 IMMEDIATE (không phải sau N requests/min). Test 4 calls với `&` + `wait` → 3/4 fail 429 lập tức.

**Cost note**: Failed 429 calls KHÔNG bị charge — request không tạo prediction. Safe to retry.

**Workaround**:
- Max 2 parallel cho urgent batch (vẫn sleep 5–10s giữa các pair)
- Sequential với `sleep 5–10` cho batch >3 (recommended)
- Nạp credit > $5 unlock burst limits per-minute (nhưng parallel limit vẫn áp dụng)

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

Khi user bảo "tôi đã save rồi" mà không nói path:

```bash
# macOS Downloads dir
find ~/Downloads -type f \( -iname "*.jpg" -o -iname "*.png" \) -mmin -60 \
  -not -path "*/Library/Caches/*"

# Bulk rename theo slug convention
for f in "$SRC"/*.png; do
    case "$f" in
        *Country1*) cp "$f" "$DST/og-country1.png" ;;
        *Country2*) cp "$f" "$DST/og-country2.png" ;;
    esac
done
```

**Lesson**: KHÔNG yêu cầu user provide path ngay → thử tự find trong Downloads/Desktop/recent files trước, tiết kiệm thời gian cho user.

## v1 → v2 migration (style consistency)

Nếu site có legacy OG style (vd PHP GD vector) và muốn migrate sang AI photo:

```
1. Generate AI background only (Flux Pro $0.04 per critical page)
2. Run same overlay script (chỉ thay file path input)
3. Overwrite same WP attachment ID file
4. wp_generate_attachment_metadata() refresh size/dimensions
5. og:image cache flush (xem references/performance.md "Cache invalidation")
```

Migration cost: ~$0.04 + 5 phút work per page. ROI: visual cohesion 100%, brand consistency, social share quality.

## Liên quan

- [`templates/snippets/og-image-generator.php`](../templates/snippets/og-image-generator.php) — PHP GD recipe + WP attachment integration
- [`references/seo-checklist.md`](../references/seo-checklist.md) — WP attachment integration cho og:image (Rank Math meta keys)
- [`references/performance.md`](../references/performance.md) — cache flush sau update OG
