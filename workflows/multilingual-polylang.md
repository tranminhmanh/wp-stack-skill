# Multilingual workflow — Polylang Free + Elementor

Production patterns for building a bilingual (or trilingual) WordPress site with Polylang Free + Elementor + Rank Math. Captured from a 5-week, 104-page bilingual deployment (52 vi + 52 en).

> **Why Polylang Free over alternatives**: cleanest URL routing (`/en/` subfolder), no JSON corruption when used with Elementor, free, 2-language sites work without Pro. WPML conflicts heavily with Elementor JSON. Polylang Pro adds the `pll_check_canonical_url` filter that fixes the same-slug bug below — worth $99 if you have many slug collisions.

---

## Architecture overview

```
WordPress + Polylang Free
├─ Default lang = vi (no /vi/ prefix — clean URL)
├─ Secondary lang = en (with /en/ prefix)
├─ Each post has a "translation linkage" (Polylang term)
├─ Each language is a custom term in the `language` taxonomy
└─ /en/<slug>/ resolves to the EN translation of vi <slug>
```

For each VI page, you create a parallel EN page with same structure, English content, and a translation link binding the two together. URL pattern: `/<vi-slug>/` ↔ `/en/<en-slug>/`.

---

## Phase 1 — Day 1 setup (~95 min)

### 1. Install + activate Polylang Free

```bash
docker exec <wp-container> bash -c '
  curl -sL https://downloads.wordpress.org/plugin/polylang.latest-stable.zip -o /tmp/p.zip
  cd /var/www/html/wp-content/plugins && unzip -q /tmp/p.zip
  chown -R www-data:www-data polylang/
'

# Activate via PHP (no WP-CLI needed)
docker exec <wp-container> php -r '
  require_once "/var/www/html/wp-load.php";
  require_once ABSPATH . "wp-admin/includes/plugin.php";
  activate_plugin("polylang/polylang.php");
'
```

### 2. Configure 2 languages + URL structure

```php
// wp-content/mu-plugins/polylang-bootstrap.php (one-shot, stub after run)
$pll_options = [
    'default_lang'  => 'vi',
    'force_lang'    => 1,   // /en/ subfolder for non-default
    'hide_default'  => 1,   // vi (default) without /vi/ prefix — clean
    'redirect_lang' => 1,   // /en redirect → /en/ (canonical)
    'browser'       => 0,   // OFF — do NOT auto-redirect via Accept-Language
    'media_support' => 1,
    'sync'          => [],
    'post_types'    => ['page', 'post', 'product'],
    'taxonomies'    => ['category', 'post_tag', 'product_cat'],
];
update_option('polylang', $pll_options);

// Create vi + en languages (run once)
if (function_exists('pll_default_languages_list')) {
    $defaults = pll_default_languages_list();
    PLL()->model->add_language(['slug' => 'vi', 'name' => 'Tiếng Việt', 'locale' => 'vi', 'rtl' => 0, 'flag' => 'vn', 'term_group' => 0]);
    PLL()->model->add_language(['slug' => 'en', 'name' => 'English',    'locale' => 'en_US', 'rtl' => 0, 'flag' => 'us', 'term_group' => 1]);
}
```

⚠️ Without `redirect_lang=1`, `/en` (no slash) returns 404. Set ALL FOUR options.

### 3. Bulk-assign existing VI posts to language=vi

Polylang's `get_terms()` is filtered by language. After enabling Polylang, terms without a language assignment return 0 from `get_terms()`. To bulk-assign existing content:

```php
global $wpdb;
$pids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts}
     WHERE post_status='publish' AND post_type IN ('page','post','product')"
);
foreach ($pids as $pid) {
    pll_set_post_language($pid, 'vi');
}

// Same for terms (categories, tags)
$tids = $wpdb->get_col(
    "SELECT t.term_id FROM {$wpdb->terms} t
     INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id
     WHERE tt.taxonomy IN ('category','post_tag','product_cat')"
);
foreach ($tids as $tid) {
    pll_set_term_language($tid, 'vi');
}
```

### 4. Verify routing — 4 checks

```bash
# 1. Homepage HTTP 200
curl -sI https://example.com/                          # 200

# 2. /en/ HTTP 200
curl -sI https://example.com/en/                       # 200 (NOT 404)

# 3. hreflang emission on homepage
curl -s https://example.com/ | grep -c 'hreflang'      # ≥ 2 (vi + en)

# 4. /en/ resolves correct post (NOT the vi-blog index)
curl -s https://example.com/en/ | grep -oE 'page-id-[0-9]+'  # match EN homepage post ID
```

If `/en/` body has `class="blog ..."` instead of `class="home page-id-NNN"` → routing fail, post translation linkage is missing — see "Translation linkage requires API call" below.

---

## CRITICAL: Translation linkage requires API call (not just SQL)

Polylang stores language assignments via `term_relationships`, but it caches resolved translations in a runtime `PLL_Cache`. Inserting directly into `wp_term_relationships` does NOT register the translation linkage — `pll_get_post(35, 'en')` still returns null → `/en/` falls back to the blog index.

```php
// ❌ WRONG — direct DB insert misses runtime cache
$wpdb->insert($wpdb->term_relationships, [
    'object_id' => 35, 'term_taxonomy_id' => $en_tt_id
]);

// ✅ RIGHT — use the API
pll_set_post_language($post_id, $lang_slug);  // assign language
pll_save_post_translations(['vi' => 35, 'en' => 629]);  // link translations
```

Direct SQL is OK for BULK assigning vi to existing posts (53 pages × API call = slow). After bulk SQL, call `pll_set_post_language()` once per post that needs translation linkage active right away.

---

## Phase 2 — Translation walk-replace pipeline

For each VI page, clone the `_elementor_data` and walk-replace VI strings with EN equivalents.

### Build script structure

```php
// /tmp/build_en_pillars_batch.php
require_once '/var/www/html/wp-load.php';

$shared_map = [
    // Long-phrase keys ONLY — see substring trap rules below
    'Vận chuyển container'              => 'Container shipping',
    'Cước biển'                          => 'Ocean freight',
    'Giải pháp <Brand>'                  => '<Brand> solution',
    // ... 100-150 phrases for the shared common content
];

$pillars = [
    [
        'vi_id' => 260, 'en_slug' => 'vn-korea',
        'en_title' => 'Container shipping Vietnam to Korea',
        'extras' => [
            'Hàn Quốc'   => 'Korea',
            'cảng Busan' => 'Busan port',
        ],
    ],
    // ... 7 more pillars
];

foreach ($pillars as $cfg) {
    $vi_data = get_post_meta($cfg['vi_id'], '_elementor_data', true);

    // Apply shared + per-page maps
    $en_data = strtr($vi_data, $shared_map);
    $en_data = strtr($en_data, $cfg['extras']);

    // Pre-save corruption check (see "Substring trap" below)
    foreach (CORRUPTION_PATTERNS as $bad) {
        if (strpos($en_data, $bad) !== false) {
            fwrite(STDERR, "✗ CORRUPTION: $bad in {$cfg['en_slug']}\n");
            continue 2;
        }
    }

    // JSON validation
    if (!json_decode($en_data, true)) {
        fwrite(STDERR, "✗ JSON broken for {$cfg['en_slug']}\n");
        continue;
    }

    // Find existing EN post (filtered by language — see CRITICAL below)
    $existing = find_existing_en_post($cfg['en_slug']);
    if ($existing) {
        // Update
        update_post_meta($existing, '_elementor_data', wp_slash($en_data));
        $en_id = $existing;
    } else {
        // Create
        $en_id = wp_insert_post([
            'post_title'    => $cfg['en_title'],
            'post_name'     => $cfg['en_slug'],
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_author'   => 1,  // admin with unfiltered_html
        ]);
        update_post_meta($en_id, '_elementor_data', wp_slash($en_data));
        update_post_meta($en_id, '_elementor_edit_mode', 'builder');
        update_post_meta($en_id, '_elementor_template_type', 'wp-page');
        update_post_meta($en_id, '_wp_page_template', 'elementor_canvas');
    }

    // Polylang linkage
    pll_set_post_language($en_id, 'en');
    pll_save_post_translations(['vi' => $cfg['vi_id'], 'en' => $en_id]);

    // CSS regen
    delete_post_meta($en_id, '_elementor_css');
}

flush_rewrite_rules(true);
```

---

## CRITICAL: Filter existing-detection by language (avoid VI overwrite disaster)

```php
// ❌ WRONG — matches VI post with same slug → overwrites VI on UPDATE
$existing = $wpdb->get_var($wpdb->prepare(
    "SELECT ID FROM {$wpdb->posts}
     WHERE post_type='page' AND post_name=%s AND post_status='publish'",
    $slug
));

// ✅ RIGHT — JOIN language taxonomy, filter to en only
function find_existing_en_post($slug) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
         INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
         INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
         WHERE p.post_type = 'page'
           AND p.post_name = %s
           AND p.post_status = 'publish'
           AND tt.taxonomy = 'language'
           AND t.slug = 'en'",
        $slug
    ));
}
```

**Real-world damage from missing the filter**: 2 VI pillar pages overwritten with EN content during a batch build. Recovery procedure → see "VI overwrite recovery" below.

---

## CRITICAL: Translation map substring traps

`strtr($source, $map)` does **substring replacement**. Short keys (1–4 chars) that are substrings of legitimate text trigger corruption.

| Bad key | Damage | Detection |
|---|---|---|
| `'cont' => 'container'` | `elType:"container"` → `containerainer` | Page renders empty Elementor wrapper |
| `'đi' => 'to'` | `điền` → `tofilln` | VN words mid-sentence broken |
| `'pin' => 'battery'` | `Shipping` → `Shipbatteryg` | Visible heading corruption |
| `'do' => 'due to'` | `documentation` → `due tocumentation` | Visible FAQ heading corruption |
| `'đóng cont' => 'load container'` | `đóng container` → `load containerainer` | Same as 'cont' |

### Hard rules

1. ❌ **Never 1–3 char Latin keys**
2. ❌ **Never keys that are common English substrings**: `cont` ⊂ `container`, `do` ⊂ `documentation`, `pin` ⊂ `Shipping`
3. ❌ **Never keys ending mid-word followed by space + EN word**: `'đóng cont'` ⊂ `'đóng container'`
4. ✅ **Full phrase keys (5+ chars VN word boundaries)** with clear word boundaries
5. ✅ **Pre-save validation** — search for known corruption strings:
   ```php
   const CORRUPTION_PATTERNS = [
       'containerainer', 'containerent_width', 'wpaainer',
       'Shipbatteryg', 'shipbatteryg',
       'due tocumentation',
       // Add new ones as you hit them
   ];
   foreach (CORRUPTION_PATTERNS as $bad) {
       if (strpos($json, $bad) !== false) {
           exit("CORRUPTION: $bad\n");
       }
   }
   ```
6. ✅ **Post-save validation**: `json_decode($json, true)` should not error
7. ✅ **When in doubt**, use specific full-context phrases:
   - ❌ `'cont' => 'container'`
   - ✅ `'đóng cont nguyên' => 'load full container'`
   - ❌ `'do' => 'due to'`
   - ✅ `'do việc' => 'business'` (full word + word boundary)

### Recovery when a trap fires post-save

```php
$reverts = [
    'due tocumentation' => 'documentation',
    'Shipbatteryg' => 'Shipping',
    'containerainer' => 'container',
];
foreach ($post_ids as $id) {
    $json = get_post_meta($id, '_elementor_data', true);
    $json = strtr($json, $reverts);
    update_post_meta($id, '_elementor_data', wp_slash($json));
    delete_post_meta($id, '_elementor_css');
}
```

### Em-dash (`—`) vs en-dash (`–`) vs hyphen (`-`) byte-level mismatch

PHP `strtr` is byte-level. Em-dash (U+2014) does NOT match en-dash (U+2013) or hyphen-minus (`-`). If the VI source uses em-dash and your map has hyphen, no match.

**Fix**: split phrases around the dash so neither side depends on the dash type:
```php
// Risky — dash type varies
'không phụ phí ẩn — không trễ sailings' => 'no hidden fees — no missed sailings',

// Safe — no dash in keys
'không phụ phí ẩn'   => 'no hidden fees',
'không trễ sailings' => 'no missed sailings',
```

### Pro Form `custom_id` MUST be preserved (don't translate)

Translation map only changes `field_label` + `placeholder`, NEVER `custom_id`. The form-handler webhook (n8n / CRM) expects specific keys (`name`, `phone`, `route`):

```php
// SAFE — visual labels only
'Họ tên'        => 'Full Name',
'Số điện thoại' => 'Phone Number',

// KEEP — custom_id preserved (n8n payload key)
// custom_id: 'name'  ← unchanged
// custom_id: 'phone' ← unchanged
```

---

## Same-slug bug across languages (Polylang Free limitation)

`url_to_postid('/en/blog/')` returns the VI post 11 (`name='blog'`) NOT the EN post 715 (also `name='blog'`). Polylang Free does NOT override WP core canonical resolution. WP then `redirect_canonical()` builds `canonical = get_permalink(11) = /blog/` and 301-redirects.

### Tested fixes that DO NOT work in Polylang Free

- ❌ `add_filter('redirect_canonical', ...)` priority 1 — too late, WP already resolved wrong post
- ❌ `template_redirect` action with manual `WP_Query` override — too late in lifecycle
- ❌ Setting `redirect_lang=0` — does not affect WP core canonical
- ❌ Multiple `pll_save_post_translations()` + cache flush — translation is correct, but WP rewrite finds VI first

**Polylang Pro feature**: `pll_check_canonical_url` filter handles same-slug + language detection natively.

### Workaround: rename EN slug to UNIQUE word

| VI slug | EN slug | Why |
|---|---|---|
| `/blog/` | `/en/articles/` | "blog" is English in both → collision |
| `/dich-vu/` | `/en/services/` | already different |
| `/lien-he/` | `/en/contact/` | already different |
| `/ve-chung-toi/` | `/en/about/` | already different |
| `/bao-gia/` | `/en/get-quote/` | already different |
| `/lich-tau/` | `/en/schedules/` | already different |

Plan EN slugs upfront — check the VI `post_name` list FIRST. If the VI slug is an English word that EN would also use, choose an alternative.

### Force /en/ prefix after batch creates

After bulk-creating EN posts, the URL may show without /en/ prefix initially. Run:
```php
pll_save_post_translations(['vi' => $vi_id, 'en' => $en_id]);  // re-save linkage
flush_rewrite_rules(true);
```

---

## Polylang Free + Rank Math sitemap incomplete — custom `/sitemap-en.xml`

Polylang Free + Rank Math integration generates `page-sitemap.xml` with only PARTIAL EN URL coverage (sometimes only 12 / 52 pages). Polylang Pro has filters that fix this, Free does not.

### Workaround mu-plugin

```php
// wp-content/mu-plugins/sitemap-en.php
<?php
add_action('init', function () {
    add_rewrite_tag('%en_sitemap%', '1');
    add_rewrite_rule('^en-sitemap/?$', 'index.php?en_sitemap=1', 'top');
    add_rewrite_rule('^sitemap-en\.xml$', 'index.php?en_sitemap=1', 'top');
});

add_action('template_redirect', function () {
    if (get_query_var('en_sitemap') !== '1') return;

    $en_term_id = get_term_by('slug', 'en', 'language')->term_id ?? null;
    if (!$en_term_id) { http_response_code(404); exit; }

    $args = [
        'post_type'   => ['page', 'post'],
        'post_status' => 'publish',
        'numberposts' => -1,
        'tax_query'   => [[
            'taxonomy' => 'language',
            'field'    => 'term_id',
            'terms'    => $en_term_id,
        ]],
    ];
    $posts = get_posts($args);

    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    echo '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    foreach ($posts as $p) {
        $en_url = get_permalink($p->ID);
        $vi_id  = pll_get_post($p->ID, 'vi');
        $vi_url = $vi_id ? get_permalink($vi_id) : null;

        echo "<url>\n";
        echo "  <loc>" . esc_url($en_url) . "</loc>\n";
        echo "  <lastmod>" . get_the_modified_date('c', $p) . "</lastmod>\n";
        if ($vi_url) {
            echo '  <xhtml:link rel="alternate" hreflang="vi" href="' . esc_url($vi_url) . "\"/>\n";
        }
        echo '  <xhtml:link rel="alternate" hreflang="en" href="' . esc_url($en_url) . "\"/>\n";
        echo "</url>\n";
    }
    echo "</urlset>\n";
    exit;
}, 0);
```

Submit `/sitemap-en.xml` to GSC alongside Rank Math's `/sitemap_index.xml`.

⚠️ **nginx static file fall-through gotcha**: default nginx config for WordPress checks if `/sitemap-en.xml` exists as a static file FIRST (`try_files $uri $uri/ /index.php`). If the file doesn't exist, nginx may return 404 (cached) without falling through to WP. If you can't edit nginx, prefer URL paths without `.xml` extension (`/en-sitemap/` works WP-only, no nginx interception).

---

## VI overwrite recovery procedure

When the existing-detection bug overwrites a VI post with EN content, restore from backup using a temp database. Do NOT restore the entire production DB — too disruptive.

```bash
# 1. Find latest backup BEFORE the overwrite
ls -lah /opt/<site>/backups/
# db-20260507-030001.sql.gz (3 AM today, before damage)

# 2. Restore to a temp DB
RPWD=$(grep MARIADB_ROOT_PWD /opt/<site>/.env | cut -d= -f2)
docker exec <db-container> mariadb -u root -p"$RPWD" -e \
  "DROP DATABASE IF EXISTS site_recover; CREATE DATABASE site_recover;"
zcat /opt/<site>/backups/db-20260507-030001.sql.gz | \
  docker exec -i <db-container> mariadb -u root -p"$RPWD" site_recover

# 3. Dump just the rows you need
docker exec <db-container> mariadb-dump -u root -p"$RPWD" --skip-extended-insert \
  --where="post_id IN (525, 541) AND meta_key='_elementor_data'" \
  site_recover wp_postmeta > /tmp/recover_inserts.sql

# 4. Apply via JOIN UPDATE from temp to production (in mariadb)
mariadb -u root -p"$RPWD" -e "
UPDATE site_prod.wp_postmeta p
INNER JOIN site_recover.wp_postmeta r
  ON r.post_id = p.post_id AND r.meta_key = p.meta_key
SET p.meta_value = r.meta_value
WHERE p.post_id IN (525, 541) AND p.meta_key = '_elementor_data';
"

# 5. Drop temp DB
docker exec <db-container> mariadb -u root -p"$RPWD" -e "DROP DATABASE site_recover;"
```

```php
// 6. Reset Polylang language assignments (script may have flipped to 'en' during overwrite)
pll_set_post_language(525, 'vi');
pll_set_post_language(541, 'vi');
flush_rewrite_rules(true);
```

```bash
# 7. Verify
curl -sI https://example.com/vn-russia/         # 200, VI content
curl -s  https://example.com/vn-russia/ | grep '<h1'   # VI text (not EN)
```

See [`deployment.md`](../references/deployment.md) "MariaDB modern containers" if your container has `mariadb` not `mysql`.

---

## 7-test pre-launch QA framework (bilingual)

Reusable for any Polylang bilingual site:

### Test 1 — hreflang verification
```bash
for url in $(cat all_vi_urls.txt); do
    en_url=$(echo "$url" | sed 's|//|//en/|')
    HREFLANG=$(curl -s "$url" | grep -oE 'hreflang="(vi|en)"' | sort -u)
    [ "$(echo "$HREFLANG" | wc -l)" = 2 ] && echo "✓ $url" || echo "✗ $url"
done
```
Pass: 52/52 pairs have correct vi↔en cross-references.

### Test 2 — Schema markup audit
Extract `<script type="application/ld+json">` from each rendered page, parse `@type`. Pass: WebPage 100%, Organization 100%, BreadcrumbList ≥95%.

### Test 3 — Sitemap coverage
Fetch `sitemap_index.xml` + `/sitemap-en.xml` (custom), count URLs, compare with DB count. Pass: 52 EN URLs in sitemap = 52 EN pages in DB.

### Test 4 — Internal link integrity
Scan `_elementor_data` for VI URL patterns leaking into EN pages. Pass: 0 occurrences of `/<vi-slug>/` in EN page data. Polylang's `get_permalink()` auto-routes correctly → 0 leaks expected.

### Test 5 — Form integration
Find form widgets, check `custom_id`, `submit_actions`, labels. Pass: form_fields[] has `custom_id` (n8n payload key), email action active, labels in EN. Phone normalized to international format (e.g. `+84 935 042 919`).

### Test 6 — Performance audit
`curl -w` for TTFB + total time. Pass: TTFB <1.5s, Total <2s, size <300KB per page (B2B WP standards). Sample 5 representative pages (homepage + pillar + subpage + service + article).

### Test 7 — Translation consistency
Count remaining VI diacritics in rendered body, find unique terms. Pass: avg <50 VN chars per page, key terms consistent (`Container Shipping`, `NVOCC`, `CO form`). Distribution target: >40% of pages 100% EN, <10% with major leaks.

```bash
# Quick check
curl -s https://example.com/en/<slug>/ | \
  grep -oE '[ăâđêôơưĂÂĐÊÔƠƯ]' | wc -l
# > 100 chars = needs polish
```

**Total QA budget**: ~1.5–2h for full QA on a 52-page bilingual site. Output → comprehensive QA report file with the maintainer's review checklist.

---

## Translation polish 3-round methodology

For pages cloned from a VI source, translation quality goes through 3 rounds:

| Round | Phrase count | Coverage | Focus |
|---|---|---|---|
| 1 — Initial map | 50–80 phrases | 60–70% | H1, section H2, common CTA, brand voice |
| 2 — Common patterns | 30–50 phrases | 80–85% | FAQ Q starters, pain case adjectives, service bullets |
| 3 — Targeted contextual | 20–30 phrases | 90–95% | Deep FAQ answers, pain case body, mixed VN/EN tech terms |

Final ~5% requires contextual rewriting — manual edit, not pattern substitution.

### Pre-extract phrases for targeted polish

Before applying Round 2 / 3, run:

```php
$worst_ids = [...];  // pages with most VN chars
$all_phrases = [];

foreach ($worst_ids as $id) {
    $data = json_decode(get_post_meta($id, '_elementor_data', true), true);
    array_walk_recursive($data, function ($v) use (&$all_phrases) {
        if (!is_string($v) || !preg_match('/[ăâđêôơưĂÂĐÊÔƠƯ]/u', $v)) return;
        // Split into clauses
        $parts = preg_split('/[.!?\n,]/', $v);
        foreach ($parts as $p) {
            $p = trim($p);
            if (strlen($p) > 3 && preg_match('/[ăâđêôơưĂÂĐÊÔƠƯ]/u', $p)) {
                $all_phrases[$p] = ($all_phrases[$p] ?? 0) + 1;
            }
        }
    });
}
arsort($all_phrases);
print_r(array_slice($all_phrases, 0, 80));
// → Top 60–80 most frequent phrases for the next round map
```

Grounds the next round in actual remaining content, not guesswork.

### When to stop polishing

Stop when:
1. Diminishing returns (<5% reduction per round)
2. Remaining VN content is in deep FAQ answers / pain cases needing contextual rewrite
3. Substring traps appearing (signal that you're getting too aggressive with short keys)

Defer the last 5–10% to:
1. Human reviewer spot-check (~3h for ~17 key pages)
2. Post-launch iteration based on user feedback + GSC data
3. Industry expert rewrite for technical FAQ content

---

## Time savings

| Phase | Manual (per page) | Batch script |
|---|---|---|
| Day 1 setup (Polylang infrastructure) | — | ~95 min |
| Pillar build (8 EN pillars) | ~45 min × 8 = 6h | ~3 sec batch + 15 min config |
| Subpage build (26 EN subpages) | ~30 min × 26 = 13h | ~3 sec batch + 30 min config |
| Service / blog / utility (16 pages) | ~25 min × 16 = 6.5h | ~5 sec batch + 30 min config |
| QA + polish (52 pages) | — | ~2h |
| **Total** | **~25h+ manual** | **~5h scripted** |

Saving ~80% on bilingual site rollout when the batch + map pattern is in place.

---

## Reusable scripts

Suggested file naming:
- `/tmp/build_en_homepage.php` — primary build
- `/tmp/build_en_pillars_batch.php` — pillar template
- `/tmp/build_en_subpages_batch.php` — subpages (N configs)
- `/tmp/build_en_services_batch.php` — services / utility
- `/tmp/polish_round_2.php`, `polish_round_3.php` — refinement
- `/tmp/recover_via_dump.sh` — VI recovery
- `/tmp/rename_en_slug_to_unique.php` — same-slug conflict resolution
- `wp-content/mu-plugins/sitemap-en.php` — custom EN sitemap (permanent)

Stub one-shot scripts after running so they cannot be re-triggered.

---

## Cross-references

- [`references/stack.md`](../references/stack.md) "Multilingual" — Polylang Free vs alternatives
- [`references/pitfalls.md`](../references/pitfalls.md) "CRITICAL: `_elementor_edit_mode` empty" — required when creating EN posts via PHP
- [`workflows/clone-transform-pattern.md`](clone-transform-pattern.md) — base pattern for cloning VI → EN, then transform
- [`references/deployment.md`](../references/deployment.md) "MariaDB modern containers" — recovery commands
- [`references/seo-checklist.md`](../references/seo-checklist.md) "Bulk Rank Math meta" — set EN-specific titles / descriptions
