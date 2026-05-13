# Structured Data (JSON-LD / Schema.org) — Patterns + Pitfalls

Reference cho mọi site cần rich schema markup (LocalBusiness, Physician, Article, FAQ, etc.) — đặc biệt YMYL (Your Money/Your Life) sites như medical, legal, financial.

> **Khi nào đọc**: lần đầu thêm JSON-LD vào page, có conflict giữa plugin schema (Rank Math/Yoast) và custom schema, hoặc YMYL site cần E-E-A-T signals cho Google.

## 1. Multi-source `@id` conflict — rename + cross-link pattern

⚠️ **Trap phổ biến**: Rank Math/Yoast auto-emit `@id: https://site.com/#organization` (sparse, ~3 props). Custom rich schema cần đầy đủ (~12 props) → reuse cùng `@id` → Google MERGE entities → signals mơ hồ (2 names, 2 descriptions cho cùng entity).

### Evidence — conflict

```jsonld
// BEFORE — same @id, 2 fragments
{"@id": "https://site.com/#organization", "@type": "Organization", "name": "Site Name"}  // Rank Math
{"@id": "https://site.com/#organization", "@type": "MedicalClinic", "name": "Phòng khám Mai Thanh",
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
  "name": "Phòng khám Sản Phụ Khoa Mai Thanh",
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
  "name": "BS CK1 Huỳnh Thị Mai Thanh",
  "alternateName": ["Bác sĩ Mai Thanh", "Huỳnh Thị Mai Thanh"],
  "honorificPrefix": "BS CK1",
  "honorificSuffix": "Bác sĩ trẻ tiêu biểu TP.HCM",
  "description": "12 năm kinh nghiệm khám thai và sản phụ khoa tại Bệnh viện Từ Dũ.",
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
    "name": "Bệnh viện Từ Dũ",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "284 Cống Quỳnh, Phường Phạm Ngũ Lão",
      "addressLocality": "Quận 1",
      "addressRegion": "TP.HCM",
      "addressCountry": "VN"
    }
  },
  "memberOf": [
    {"@type": "MedicalOrganization", "name": "Hội Sản Phụ khoa Việt Nam"},
    {"@type": "MedicalOrganization", "name": "Hội Y học Sinh sản TP.HCM"}
  ],
  "alumniOf": {
    "@type": "CollegeOrUniversity",
    "name": "Đại học Y Phạm Ngọc Thạch",
    "url": "https://pnt.edu.vn"
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

## Liên quan

- [`rankmath.md`](rankmath.md) — Rank Math auto-emits Organization + WebSite — coexistence pattern
- [`seo-checklist.md`](seo-checklist.md) — schema integration trong full SEO checklist
- Insights references: PKM-2026-05-13-079 (@id conflict resolution), -080 (Physician 18-field YMYL)
