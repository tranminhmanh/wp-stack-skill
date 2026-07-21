# Structured Data (JSON-LD / Schema.org) — Patterns + Pitfalls

Reference cho mọi site cần rich schema markup (LocalBusiness, Physician, Article, FAQ, etc.) — đặc biệt YMYL (Your Money/Your Life) sites như medical, legal, financial.

> **Khi nào đọc**: lần đầu thêm JSON-LD vào page, có conflict giữa plugin schema (Rank Math/Yoast) và custom schema, hoặc YMYL site cần E-E-A-T signals cho Google.

## 1. Multi-source `@id` conflict — rename + cross-link pattern

⚠️ **Trap phổ biến**: Rank Math/Yoast auto-emit `@id: https://site.com/#organization` (sparse, ~3 props). Custom rich schema cần đầy đủ (~12 props) → reuse cùng `@id` → Google MERGE entities → signals mơ hồ (2 names, 2 descriptions cho cùng entity).

### Evidence — conflict

```jsonld
// BEFORE — same @id, 2 fragments
{"@id": "https://site.com/#organization", "@type": "Organization", "name": "Site Name"}  // Rank Math
{"@id": "https://site.com/#organization", "@type": "MedicalClinic", "name": "<Clinic Name>",
 "address": {...}, "founder": [...], ...}  // Custom

// → Google merge: 2 names, address from #2, founder from #2, plain description from #1
// → Knowledge Graph confused: which is the canonical name?
```

### Fix — rename + parentOrganization cross-link

```jsonld
// Plugin schema (UNTOUCHED — don't fight Rank Math)
{"@id": "https://site.com/#organization", "@type": "Organization", "name": "Site Name"}

// Custom rich schema (DIFFERENT @id)
{
  "@id": "https://site.com/#localbusiness",
  "@type": ["MedicalClinic", "LocalBusiness"],
  "parentOrganization": {"@id": "https://site.com/#organization"},  // ← cross-link UP
  "name": "<Full Clinic Name>",
  "address": {...},
  "founder": [...],
  // 12 props rich
}
```

Google sees: 2 distinct nodes — `Organization` (brand entity) + `LocalBusiness` (physical location, child of brand). Clean hierarchy, no merge.

### Root cause

Schema.org `@id` semantics = **"same node identity"**. 2 nodes cùng `@id` = MERGE properties. Different `@id` = 2 separate entities. For multi-facet branding (brand Organization + physical LocalBusiness), use 2 different `@id` với explicit relationship via:
- `parentOrganization` (Organization → LocalBusiness hierarchy)
- `branchOf` (LocalBusiness chain)
- `isPartOf` (entity belongs to bigger)
- `mainEntity` (PageType primary entity)

### Application across plugins

Same pattern works với:
- Rank Math (default Organization schema auto-emit)
- Yoast SEO (similar Organization + WebSite blocks)
- Schema Pro plugin
- ACF Schema (custom field-based)

Pattern: **keep plugin-auto schema as-is, custom schema uses different `@id`, cross-link via explicit semantic relationship**.

### Verification

```bash
# Extract all @id values from page JSON-LD
curl -sL "https://site.com/" \
  | grep -oP '<script type="application/ld\+json">[\s\S]*?</script>' \
  | grep -oP '"@id"\s*:\s*"[^"]+"' \
  | sort | uniq -c

# Expect: 0 duplicates across all @id values
```

## 2. YMYL Physician schema — 18-field design cho E-E-A-T

Generic Physician schema (8 fields: name, url, medicalSpecialty) miss E-E-A-T signals Google cần cho YMYL medical content. Comprehensive 18-field design provides **Experience + Expertise + Authority + Trust** machine-readable signals — supplements (không thay thế) on-page content.

### Full template

```jsonld
{
  "@type": "Physician",
  "@id": "https://site.com/doctor-slug/#physician",
  "name": "BS CK1 <Doctor Full Name>",
  "alternateName": ["Bác sĩ <Last Name>", "<Doctor Full Name>"],
  "honorificPrefix": "BS CK1",
  "honorificSuffix": "<Honorific Award/Recognition>",
  "description": "<N> năm kinh nghiệm <specialty> tại <Top Hospital Name>.",
  "image": "https://site.com/wp-content/uploads/doctor-photo.jpg",
  "url": "https://site.com/doctor-slug/",
  "jobTitle": "Bác sĩ Chuyên khoa I — Sản Phụ Khoa",
  "gender": "Female",
  "knowsLanguage": ["vi", "en"],
  "medicalSpecialty": ["Obstetrics", "Gynecology", "ReproductiveMedicine"],
  "knowsAbout": [
    "Khám thai định kỳ",
    "Sàng lọc dị tật bẩm sinh",
    "NIPT",
    "Siêu âm 4D thai",
    "Khám phụ khoa định kỳ",
    "Pap smear",
    "HPV screening",
    "Tư vấn IVF/ICSI",
    "Điều trị hiếm muộn",
    "Theo dõi thai kỳ nguy cơ cao"
  ],
  "worksFor": {"@id": "https://site.com/#localbusiness"},
  "hospitalAffiliation": {
    "@type": "Hospital",
    "name": "<Top Hospital Name>",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "<Street Address>",
      "addressLocality": "<City District>",
      "addressRegion": "<City Region>",
      "addressCountry": "VN"
    }
  },
  "memberOf": [
    {"@type": "MedicalOrganization", "name": "<Professional Association 1>"},
    {"@type": "MedicalOrganization", "name": "<Professional Association 2>"}
  ],
  "alumniOf": {
    "@type": "CollegeOrUniversity",
    "name": "<Medical University Name>",
    "url": "https://<university-domain>/"
  }
}
```

### Why each field matters cho E-E-A-T

| Field group | E-E-A-T signal |
|---|---|
| `description` + `knowsAbout` 10 procedures | **Experience** (hands-on, specific procedures) |
| `medicalSpecialty` + `jobTitle` + `honorificPrefix` | **Expertise** (formal qualification) |
| `hospitalAffiliation` (top hospital structured) + `memberOf` (associations) + `alumniOf` | **Authority** (institutional backing) |
| `image` + `gender` + `knowsLanguage` + `alternateName` | **Identifiability / Trust** (real person, contactable) |

### Reference: Google E-E-A-T docs

[developers.google.com/search/docs/fundamentals/creating-helpful-content](https://developers.google.com/search/docs/fundamentals/creating-helpful-content) emphasize cho YMYL: "Who is the author? What's their qualification? Where do they work?". Structured Physician schema provides machine-readable answers — supplements visible bio.

### Reusability

Same 18-field design applies (with @type swap) cho mọi YMYL professional:

| Profession | @type | Key diffs |
|---|---|---|
| Physician/Dentist/Dermatologist | `Physician` | Use as-is |
| Mental health professional | `Psychiatrist` hoặc `MedicalBusiness` | Same fields |
| Lawyer | `Attorney` | Replace `medicalSpecialty` → `lawyerSpecialty`; `hospitalAffiliation` → `worksFor` |
| Financial advisor | `FinancialProduct` provider | `knowsAbout` financial products; `memberOf` industry associations |
| Dietitian / nutritionist | `Dietitian` (Schema.org pending) → use `Person` + jobTitle | Same E-E-A-T signals |

## 3. JSON-LD injection methods — pros/cons

### Method 1: Plugin (Rank Math / Schema Pro)

✅ Setup once in admin, auto-applies per post type
✅ Update sync với post data
❌ Limited template — không cover edge cases (vd Physician needs custom procedure list)
❌ Hard to debug — output buried in plugin code

### Method 2: Elementor HTML widget với `<script>` tag

✅ Per-page customization, visual editor
✅ Easy preview
❌ Per-page maintenance (no DRY)
❌ Not parsed by Elementor preview iframe (paste into final position)

```html
<!-- In Elementor HTML widget -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Physician",
  "@id": "https://site.com/doctor/#physician",
  ...
}
</script>
```

### Method 3: WP filter `wp_head` action

✅ Fully programmatic, DRY
✅ Conditional per post type / post ID
❌ Requires mu-plugin or theme functions.php

```php
add_action( 'wp_head', function () {
    if ( ! is_singular( 'doctor_cpt' ) ) return;
    $pid = get_the_ID();
    $schema = build_physician_schema( $pid );  // your function
    echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';
}, 50 );
```

### Recommendation

- **Single page** (homepage LocalBusiness): Elementor HTML widget — visible to editor, easy review
- **Per-CPT** (doctor profile, service, FAQ): mu-plugin via `wp_head` — DRY, scalable
- **Mixed**: plugin handles common types (Article, BreadcrumbList), custom mu-plugin overrides specifics

## 4. Validation tools

| Tool | Purpose |
|---|---|
| [Schema.org Validator](https://validator.schema.org/) | Syntactic + semantic validation |
| [Google Rich Results Test](https://search.google.com/test/rich-results) | Eligibility cho rich snippets (FAQ, Product, Recipe, etc.) |
| [Schema Markup Validator](https://json-ld.org/playground/) | Interactive JSON-LD parser |
| Browser DevTools → Sources → search `application/ld+json` | Quick "what's emitted" check |

⚠️ **Always validate trên LIVE URL** (not staging) sau khi setup — Google indexer fetches production.

## 5. Common types reference

| Type | Required props | E-E-A-T relevance |
|---|---|---|
| `Organization` | name, url | Brand entity |
| `LocalBusiness` | name, address, telephone | Physical location |
| `MedicalClinic` | LocalBusiness + medicalSpecialty | Medical service |
| `Physician` | name, jobTitle | YMYL critical (see §2) |
| `Article` | headline, datePublished, author | Content E-E-A-T |
| `FAQPage` | mainEntity (Q&A list) | Rich snippet eligible |
| `BreadcrumbList` | itemListElement | Navigation rich snippet |
| `Product` | name, image, offers | E-commerce |
| `Service` | name, provider, areaServed | Service business |

## JSON-LD graph consolidation via `rank_math/json_ld` filter — additive enrich pattern

**Problem**: Site has 2+ sources emitting `@graph` entities that conflict — typically Rank Math auto-emitted `@graph` + a custom Elementor HTML widget `@graph` (or a Local SEO plugin's parallel graph). Duplicate `@id` values + dangling `@id` refs + 2 Organization / 2 LocalBusiness node instances → validators error, Google may drop one graph entirely.

**Solution**: 1 MU-plugin filter on `rank_math/json_ld` that ADDS to Rank Math's base graph rather than emitting a parallel graph. Then delete the custom widget graphs entirely.

### Filter pattern — additive enrich by @id-SUFFIX match

```php
<?php
// wp-content/mu-plugins/<site>-entity-graph.php
add_filter( 'rank_math/json_ld', function ( $data, $jsonld ) {
    // Defensive — never fatal even if RM returns non-array in edge case
    if ( ! is_array( $data ) ) return $data;

    // Iterate top-level pieces — key-agnostic (RM key naming is not stable across versions)
    foreach ( $data as $key => $piece ) {
        if ( ! is_array( $piece ) || empty( $piece['@id'] ) ) continue;
        $id = $piece['@id'];

        // Match by @id SUFFIX — resilient to RM array key changes
        if ( '#organization' === substr( $id, -13 ) ) {
            $data[ $key ] = array_merge( $piece, [
                // Additive enrich — keep RM's name/url/logo, add missing fields
                'address'          => [ '@type' => 'PostalAddress', /* inline value, NO @id */ ],
                'telephone'        => '+xx-xxx-xxx-xxxx',
                'contactPoint'     => [ /* inline value, NO @id — see anti-pattern below */ ],
                'founder'          => [ /* inline Person */ ],
                'medicalSpecialty' => 'Obstetrics',
                'sameAs'           => [ /* social/profile URLs */ ],
            ] );
        }

        // Enrich author byline nodes for E-E-A-T
        if ( isset( $piece['@type'] ) && 'Person' === $piece['@type']
             && str_ends_with( $id, '/author/<slug>/' ) ) {
            $data[ $key ] = array_merge( $piece, [
                'jobTitle'    => 'Physician',
                'description' => '<credentials>',
                'sameAs'      => [ '#physician' ], // ref to canonical Physician node
            ] );
        }
    }

    // Inject net-new nodes (branch, department, whatever RM doesn't emit)
    $data['<site>_branch_1'] = [ '@type' => 'MedicalClinic', '@id' => '/#branch1', /* ... */ ];

    return $data;
}, 99, 2 );
```

**Why @id-suffix match instead of array key**: Rank Math's internal `$data` array keys aren't stable across plugin versions (`$data['organization']` vs `$data[0]` vs numeric index). Matching by `@id` suffix (`#organization`, `/#localbusiness`) is version-safe.

**Why `array_merge` additive**: keeps RM's automatically-computed fields (name, url, logo, image, description, openingHours from Local SEO settings) — you only ADD missing structured fields. Overwriting with a full replacement loses RM's built-in intelligence.

**PHP compatibility**: `str_ends_with()` is PHP 8.0+. `substr($id, -13) === '#organization'` works on older. Pick per site PHP version.

**Deploy**: via `rankmath-mcp/write-mu-plugin {file_name, content_base64, overwrite:true}` — see [`mu-plugin-patterns.md`](mu-plugin-patterns.md) §"Safe deploy pattern".

### Anti-pattern — sub-object with `@id` inside a property creates duplicate `@id`

**Regression trap** (real 2026-06-17 miss). When enriching `#organization.contactPoint` via the filter with a sub-object that carries `@id`:

```php
// ❌ WRONG — sub-object with @id becomes a node definition → collision
'contactPoint' => [
    '@type'     => 'ContactPoint',
    '@id'       => '/lien-he/#contactpoint-q11',   // ← this makes it a node
    'telephone' => '+xx-xxx-xxx-xxxx',
],
```

The sub-object with `@id` is treated by the JSON-LD spec (and Google's parser) as a **node definition** in the graph. If a page's other widget (or a custom emit on `/lien-he/`) also defines `#contactpoint-q11`, you get **2 node definitions with same @id** on that page — duplicate error.

**Rule**: sub-object embedded in a property (`contactPoint` / `address` / `geo` / `hoursAvailable`) should carry `@id` **ONLY** when it is a canonical node defined ONCE and referenced from elsewhere via `{"@id": "..."}`. If it's just a value of the property → inline value object WITHOUT `@id`:

```php
// ✅ RIGHT — inline value, no @id → no node definition, no collision
'contactPoint' => [
    '@type'     => 'ContactPoint',
    'telephone' => '+xx-xxx-xxx-xxxx',
    'contactType' => 'customer service',
],
```

**Verify obligation**: after deploying a filter that injects sub-objects, fetch EVERY page that shares the entity (especially the page that originally defines the node via a widget), walk the `@graph`, count nodes with `(@type AND @id)` — none should appear >1. Catch this during incremental verify (`curl "$SITE/page/?cb=$(date +%s)" | jq .graph`), not at end-of-project audit.

### Safe removal of a JSON-LD central node — repoint inbound refs FIRST

**Problem**: you decide to remove a central node (e.g. `#localbusiness` in favor of `#organization`). Naive delete leaves **dangling refs**: `#physician.worksFor`, `/lien-he/#contactpage-detail.mainEntity`, `/lien-he/#contactpage-detail.about`, `#branch.branchOf` — all pointing at a node that no longer exists.

**Safe sequence** (order matters):

1. **Find all inbound refs first** — `elementor-mcp/find-element query="application/ld+json"` on each page, extract widget IDs that emit JSON-LD containing `{"@id":"<node-to-remove>"}` as a reference value.
2. **Repoint each inbound ref to the replacement node** — update every widget that references the doomed node to point at the replacement (`#organization` in this example).
3. **Empty / delete the widget(s) that DEFINE the doomed node** — usually 1 primary source (e.g. homepage HTML widget `99df6b5`).
4. **Deploy MU-plugin filter that INJECTS the replacement node site-wide** — so refs from pages that used to resolve to the doomed node now find the replacement.

**Order caveat**:
- Deploy injector BEFORE removing widget → temporary duplicate node (both sources active with same `@id`) — bad but recoverable
- Remove widget BEFORE injector deploy → short window with missing node → dangling refs (usually acceptable on low-traffic clean-up window)

**Verify checklist**:

```bash
# Before removal: count references TO the node
for page in home about contact team; do
    curl -s "$SITE/$page/" | grep -c '"@id":"[^"]*#localbusiness"'
done

# After cleanup: expect 0 defs + 0 refs to old node
for page in home about contact team; do
    defs=$(curl -s "$SITE/$page/" | jq -r '.["@graph"][] | select(."@id" | endswith("#localbusiness")) | ."@id"' | wc -l)
    refs=$(curl -s "$SITE/$page/" | grep -c '"@id":"[^"]*#localbusiness"')
    echo "$page: defs=$defs refs=$refs"
done
# Expect: defs=0, refs=0

# Replacement node present + rich
curl -s "$SITE/" | jq '.["@graph"][] | select(."@id" | endswith("#organization"))'
# Expect all enriched fields present
```

Cross-references: [`elementor-mcp.md`](elementor-mcp.md) `find-element` — locate widgets emitting JSON-LD; [`rankmath.md`](rankmath.md) §"Wrapper plugin response conventions" — `write-mu-plugin` deploy path.

## Liên quan

- [`rankmath.md`](rankmath.md) — Rank Math auto-emits Organization + WebSite — coexistence pattern
- [`seo-checklist.md`](seo-checklist.md) — schema integration trong full SEO checklist
- [`mu-plugin-patterns.md`](mu-plugin-patterns.md) — `write-mu-plugin` safe deploy + `rank_math/json_ld` filter as bridge pattern
- Insight sources: weekly distillation 2026-05-13 (@id conflict resolution + Physician YMYL pattern); 2026-06-17 (graph consolidation methodology + sub-object @id collision + safe removal sequence)
