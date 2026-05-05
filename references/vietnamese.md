# Vietnamese-Specific Concerns

## Database

- MUST use `utf8mb4_unicode_ci` collation (KHÔNG phải `utf8`)
- Check: 
```sql
SHOW VARIABLES LIKE 'character_set%';
SHOW VARIABLES LIKE 'collation%';
```

## Fonts với Vietnamese subset

Đã test OK:
- **Be Vietnam Pro** (recommend cho mọi project)
- Inter (vietnamese subset)
- Roboto
- IBM Plex Sans Vietnamese
- Noto Sans Vietnamese
- Manrope

NOT OK (thiếu dấu):
- Custom font upload không có Vietnamese subset
- Một số Google Fonts older (Lora, Merriweather) — check trước

## Astra font load locally

`Customize → Performance → Load Google Fonts Locally: ON`

⚠️ Astra cache local có thể thiếu Vietnamese subset. Fix:
1. Astra → Performance → Flush local font cache
2. Re-load page
3. Inspect → font-family Be Vietnam Pro
4. Network tab → font file load đủ Vietnamese chars

Nếu vẫn lỗi → Disable local fonts, dùng Google Fonts CDN.

## Slug URL

QUY TẮC: Slug KHÔNG dấu, dùng kebab-case.
- ✅ `/dich-vu-phao-hoa/`
- ✅ `/banh-mi-cha/`
- ❌ `/dịch-vụ-pháo-hoa/`
- ❌ `/bánh-mì-chả/`

WordPress auto-convert nếu set permalink đúng. Nếu không, fix:
- Tools → Convert non-Latin chars in URL

## Meta description Vietnamese

OK dùng dấu, KHÔNG cần unicode escape.
Length: 150-160 ký tự (Vietnamese ~30% dài hơn English vì dấu).

## Schema markup tiếng Việt

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

OK dấu trong JSON, đảm bảo file UTF-8 BOM-less.

## Translation strategy

- Polylang Free: 2 ngôn ngữ, site đơn giản
- **Meep AI Translator**: Elementor-heavy (đọc JSON Elementor không vỡ layout)
- WPML: AVOID (nặng, chậm với Elementor)

## Copy generation

⚠️ AI generate Vietnamese copy thường:
- Văn phong gượng, máy móc
- Dùng từ Hán-Việt không cần thiết
- Sai sắc thái B2B vs B2C
- Sai địa phương (Sài Gòn vs Hà Nội voice)

→ AI làm DRAFT, copywriter Việt rewrite hero/CTA bắt buộc.

## Phone, address, currency format

- Phone: `0901 234 567` hoặc `+84 901 234 567`
- Date: `dd/mm/yyyy` (KHÔNG mm/dd/yyyy)
- Currency: `1.000.000 ₫` (dấu chấm phân cách, ₫ phía sau)
- Time: 24h format `14:30` thay vì `2:30 PM`

## Vietnamese SEO

- Google VN crawl mobile UA
- LocalBusiness schema có `addressCountry: "VN"`
- hreflang: `vi-VN` cho Vietnamese, `en-US` cho English version
- Search Console: target country Vietnam
- Submit sitemap qua Google Search Console version vi.google.com
