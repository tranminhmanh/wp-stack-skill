# Vietnamese-Locale Concerns

Concerns specific to Vietnamese-language WordPress sites. The same patterns apply to other diacritic-heavy languages (Polish, Czech, Turkish, etc.) — adjust the locale-specific bits.

## Database

- MUST use `utf8mb4_unicode_ci` collation (NOT `utf8`)
- Check:
```sql
SHOW VARIABLES LIKE 'character_set%';
SHOW VARIABLES LIKE 'collation%';
```

## Fonts with Vietnamese subset

Tested OK:
- **Be Vietnam Pro** (recommended for any Vietnamese project)
- Inter (Vietnamese subset)
- Roboto
- IBM Plex Sans Vietnamese
- Noto Sans Vietnamese
- Manrope

NOT OK (missing diacritics):
- Custom font upload without a Vietnamese subset
- Some older Google Fonts (Lora, Merriweather) — verify before use

## Astra local font cache

`Customize → Performance → Load Google Fonts Locally: ON`

⚠️ Astra's local cache may miss the Vietnamese subset. Fix:
1. Astra → Performance → Flush local font cache
2. Reload page
3. Inspect → font-family Be Vietnam Pro
4. Network tab → font file loads with all Vietnamese chars

If still broken → disable local fonts, use Google Fonts CDN.

## Slug URLs

RULE: slugs are diacritic-free, kebab-case.
- ✅ `/dich-vu-phao-hoa/`
- ✅ `/banh-mi-cha/`
- ❌ `/dịch-vụ-pháo-hoa/`
- ❌ `/bánh-mì-chả/`

WordPress auto-converts if permalinks are set correctly. If not:
- Tools → Convert non-Latin chars in URL

## Vietnamese meta description

Diacritics OK in meta description, no Unicode escape needed.
Length: 150–160 chars (Vietnamese is ~30% longer than English due to diacritics).

## Vietnamese in schema markup

```json
{
  "@type": "LocalBusiness",
  "name": "Bánh Mì Má Hải",
  "address": {
    "streetAddress": "123 Lê Lợi",
    "addressLocality": "Quận 1",
    "addressRegion": "TP. Hồ Chí Minh",
    "addressCountry": "VN"
  }
}
```

Diacritics OK in JSON. Make sure the file is UTF-8 BOM-less.

## Translation strategy

- **Polylang Free**: 2 languages, simple sites
- **Meep AI Translator**: Elementor-heavy sites (reads Elementor JSON without breaking layout)
- **WPML**: AVOID (heavy and slow with Elementor)

## Copy generation

⚠️ AI-generated Vietnamese copy often has:
- Stiff, machine-like tone
- Unnecessary Sino-Vietnamese vocabulary
- Wrong B2B vs B2C register
- Wrong regional voice (Saigon vs Hanoi)

→ Use AI for DRAFT only. A native Vietnamese copywriter MUST rewrite hero / CTA.

## Phone, address, currency formats

- Phone: `0xxx xxx xxx` or `+84 xxx xxx xxx`
- Date: `dd/mm/yyyy` (NOT mm/dd/yyyy)
- Currency: `1.000.000 ₫` (dots as thousand separators, ₫ after the number)
- Time: 24h format `14:30` instead of `2:30 PM`

## Vietnamese SEO

- Google VN crawls with the mobile UA
- LocalBusiness schema needs `addressCountry: "VN"`
- hreflang: `vi-VN` for Vietnamese, `en-US` for the English version
- Search Console: target country Vietnam
- Submit sitemap via the Google Search Console version at `vi.google.com`

## Vietnamese title length: 40–55 chars

Vietnamese in UTF-8 takes 1.5–2 bytes per char (đ, ă, ê, ơ...), but Google SERP counts by **visual character width**. A title of 70+ chars gets cut mid-word. Target: 40–55 chars Vietnamese (vs 50–60 English).

Format: `[Main keyword] — [USP highlight] | Brand`

Examples:
- ✅ "Vận chuyển VN-Hàn Quốc — Cước cạnh tranh | Brand" (49 chars)
- ❌ "Vận chuyển container Việt Nam đi Hàn Quốc — Báo giá miễn phí trong 4 giờ" (74 chars, SERP truncates)
