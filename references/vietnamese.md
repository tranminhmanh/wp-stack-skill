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

## Tooling: PowerShell scripts với Vietnamese content cần UTF-8 BOM

Khi viết PowerShell `.ps1` script chứa Vietnamese characters (comments, string literals, output messages), PS 5.1 (Windows default) đọc file theo **system codepage** (Windows-1252 cho VN locale). Bytes UTF-8 của diacritic chars → decoded thành garbage → parser error.

### Symptom

```powershell
# script.ps1 written by VS Code "UTF-8 no BOM" or Claude Code Write tool
# Content: "# Tạo backup ảnh — copy to /backup/"
# em-dash `—` = 3 bytes UTF-8: E2 80 94

PS> .\script.ps1
At ...\script.ps1:1 char:23
+ # Tạo backup ảnh â€" copy to /backup/
+                       ~~~
Unexpected token in expression or statement.
ParserError ...
```

3 bytes `E2 80 94` (em-dash UTF-8) → Win-1252 decoder thấy `â€"` (3 garbage chars) → PS parser sees broken token.

### Root cause

PS 5.1 `Get-Content` / script loader detect file encoding **bằng BOM signature**:
- File có UTF-8 BOM (3 bytes `EF BB BF` đầu file) → parse correctly
- File no BOM → assume system codepage (Win-1252, CP-936, ...)

PS 7+ default UTF-8 (no BOM needed). Cross-version-safe: always BOM cho PS 5.1.

### Fix

```powershell
$f = "path\to\script.ps1"

# Read content (UTF-8 decoder explicit)
$content = [IO.File]::ReadAllText($f, [Text.UTF8Encoding]::new($false))

# Re-write with BOM ($true = include BOM)
$utf8Bom = New-Object Text.UTF8Encoding($true)
[IO.File]::WriteAllText($f, $content, $utf8Bom)

# Verify BOM (first 3 bytes should be EF BB BF)
$bytes = [IO.File]::ReadAllBytes($f)[0..2]
"BOM: $('{0:X2} {1:X2} {2:X2}' -f $bytes[0], $bytes[1], $bytes[2])"
# Output: BOM: EF BB BF
```

### Gotcha

Edit tools (vd Claude Code Edit, VS Code save) có thể strip BOM khi modify file. Re-add BOM sau mỗi major edit:
```powershell
# Run after every major .ps1 edit
$f = "path\to\script.ps1"
$content = [IO.File]::ReadAllText($f, [Text.UTF8Encoding]::new($false))
[IO.File]::WriteAllText($f, $content, [Text.UTF8Encoding]::new($true))
```

### Alternative: ASCII-only comments

Strip Vietnamese từ `.ps1` comments + string literals — write code in English-only. Trade-off: less readable cho VN devs.

### Reusability

Universal cho any non-ASCII content in PS 5.1 scripts:
- Vietnamese (đ, ă, ê, ơ, ư, ô, à, á, è, é, ...)
- Em-dash `—` and en-dash `–` (used in markdown-style headers)
- Chinese, Japanese, Korean, Cyrillic
- Smart quotes `"…"`, `'…'`

PowerShell 7+ default UTF-8 — không cần BOM nếu chạy PS 7+.
