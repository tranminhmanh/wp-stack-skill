# Google Business Profile (GBP) — setup + content policy

Google Business Profile is where local search results pull from. Two recurring gotchas hit WordPress site owners:

1. **Description field has a strict content policy** — fields like phone number, pricing, promotional language, URLs, HTML tags get auto-rejected. The whole description block is hidden until you fix it.
2. **Vietnamese category autocomplete dropdown** has multiple visually-similar entries with very different intent (retail vs wholesale vs distributor vs manufacturer). Picking the wrong one routes Google's local-search matching to the wrong audience.

Both issues are silent — Google rejects/mismatches without flagging the cause clearly. This file is the cheat-sheet for both.

## When to use this reference

✅ Setting up a brand-new GBP profile (especially for a B2B / wholesale / specialty business)
✅ Editing an existing GBP description and getting "Nội dung chỉnh sửa của bạn không được phê duyệt" (your edit was not approved)
✅ Auditing why local-search rankings dropped after a category change

❌ Setting up Google My Business legacy app (deprecated, redirects to GBP)
❌ Multi-location franchise rollout — needs a different reference (we don't cover that)

## 5 description content-policy rules (auto-reject triggers)

Google's description field rejects content that belongs in dedicated fields. Source: [support.google.com/business/answer/3038177](https://support.google.com/business/answer/3038177).

| ❌ Cấm | Belongs in | Why |
|---|---|---|
| Số điện thoại | Contact → Phone | Phone is a structured field with click-to-call |
| Giá cả / pricing tiers | Products section | Pricing is a structured field, validated against currency |
| Promotional content (miễn phí, cam kết hoàn 100%, đổi trả, giảm giá, ưu đãi, khuyến mãi) | Posts (Offer type) hoặc Services | Promos are time-bound + need disclaimer fields |
| URLs | Contact → Website + Booking link | Links are structured |
| HTML tags, special unicode, all-caps spam (e.g. "3 KHÔNG", `−18°C`, `•` bullet bombing) | — (just don't) | Description is plain text only |

### Detection — grep before submit

```bash
DESC=$(cat /tmp/gbp-description.txt)

# Test for the 5 rule violations
echo "$DESC" | grep -E '\d{3,4}[\.\s]?\d{3,4}'         && echo "❌ phone-like pattern"
echo "$DESC" | grep -E '\d{2,3}[\.,]?\d{3}đ|\d+VND|\$\d' && echo "❌ pricing"
echo "$DESC" | grep -iE 'miễn phí|cam kết|hoàn 100%|đổi trả|khuyến mãi|giảm giá|ưu đãi' && echo "❌ promo language"
echo "$DESC" | grep -E 'https?://|www\.'              && echo "❌ URL"
echo "$DESC" | grep -E '<[^>]+>|−|•|✓|✗'              && echo "❌ HTML / special unicode"
```

All five must return empty (no match) before submitting.

## Compliant template — B2B Vietnamese (food wholesaler example)

Description should describe **what the business IS**, not **what it SELLS**. Skeleton:

```
[Brand] là [type of business] [làm gì] [cho ai] [tại đâu].

Sản phẩm [mô tả tính chất, công thức, quy trình, không/có gì]. [Đóng gói, bảo quản].

Phục vụ [audience type] tại [service areas]. Có dịch vụ [generic service types].

[Background / heritage / location context].
```

### Concrete example (compliant)

```
Acme Foods là nhà sản xuất + bán buôn chả cá tươi truyền thống tại Vũng Tàu.

Sản phẩm chế biến hàng ngày từ cá biển đánh bắt tươi, không chất bảo quản, không hàn the. Đóng gói chân không, bảo quản đông lạnh.

Phục vụ nhà hàng, quán ăn, đại lý phân phối tại TP.HCM, Bình Dương, Đồng Nai. Có giao hàng tận nơi cho đơn hàng từ 5kg.

Cơ sở được kiểm định ATTP định kỳ. Đặt tại Vũng Tàu — vùng nguyên liệu chính của Việt Nam.
```

Note: no phone, no price, no promo words, no URL, no HTML. Reads as a description of the business identity.

### Concrete example (rejected — anti-pattern)

```
🐟 Acme Foods — CHẢ CÁ NGON NHẤT VŨNG TÀU 🐟
Liên hệ ngay 0938 123 456 để được tư vấn MIỄN PHÍ!
Giá chỉ từ 50.000đ/kg • Cam kết HOÀN TIỀN 100% nếu không hài lòng
Website: https://example.com
👉 ƯU ĐÃI 20% cho đơn hàng đầu tiên!
```

Hits all 5 rules. Google rejects the entire block.

## Re-submit timeline + cadence

- After fixing the description → Save → Google re-reviews in **1–3 business days**.
- Don't spam multiple edits in 24h — the profile gets flagged for review-throttling.
- Re-review status is invisible — just check periodically (every 24h) to see if the description renders on the public profile URL.

## Category dropdown — Vietnamese autocomplete gotcha

The GBP category picker has Vietnamese localization with multiple entries that look similar but route to entirely different Google taxonomy nodes:

| Vietnamese label | English (real category) | Intent |
|---|---|---|
| Cửa tiệm bán lẻ trực tiếp | Direct Retail Store | **B2C** — walk-in retail |
| Cửa hàng bán buôn thực phẩm | Wholesale food store | **B2B** — sells in bulk to other businesses |
| Nhà phân phối thực phẩm | Food distributor | **B2B** middleman / logistics |
| Nhà sản xuất thực phẩm | Food manufacturer | Production facility |
| Nhà máy chế biến cá | Fish processing company | Industrial-scale fish processing |

Picking the wrong one → Google matches the wrong local search queries:
- "Wholesale food store" → matches "bán buôn chả cá", "mua sỉ thực phẩm", "nhà cung cấp B2B" ✓
- "Direct retail store" → matches "chả cá lẻ", "mua chả cá tại Vũng Tàu" ✗ (B2C intent — wrong audience for a wholesaler)

### Workaround — type ENGLISH keys in the autocomplete

When the Vietnamese autocomplete dropdown shows ambiguous near-matches:

1. Switch the search term to English: type `wholesale` or `manufacturer` or `fish processing` instead of the Vietnamese term
2. The dropdown will show the official category name + the Vietnamese translation in parentheses
3. Pick the one that explicitly matches your business model — the EN name removes ambiguity

```
Search "wholesale" → dropdown shows:
  - Wholesale food store (Cửa hàng bán buôn thực phẩm)  ← B2B
  - Wholesaler (Nhà bán buôn)
  - ...

Search "retail" → dropdown shows:
  - Retail store (Cửa hàng bán lẻ)  ← B2C
  - ...
```

### Primary vs secondary category

**Primary** determines which Google search queries Google considers a match for your business. Pick the one that matches your **dominant revenue source**. Add secondary categories for other revenue streams.

For a fish wholesaler that occasionally sells direct:
- Primary: **Wholesale food store** (95% revenue)
- Secondary: **Fish processing company**, **Food manufacturer** (if applicable)
- Do NOT add: Retail store (5% revenue, wrong intent for main audience)

### Fixing a wrong primary category

GBP → Edit profile → Categories → drag the correct one to primary → save. Google re-evaluates local-search routing within 1–3 days. Existing reviews + photos preserved. Existing ranking signals partially preserved (Google needs ~2 weeks to fully reroute matching).

## Setup checklist for a new GBP profile

```
[ ] Verify business identity (postcard / phone / email)
[ ] Pick correct primary category (use ENGLISH search if VN dropdown ambiguous)
[ ] Add secondary categories matching other revenue streams
[ ] Write description following the 5-rule policy (grep before submit)
[ ] Fill structured fields: Phone, Website, Hours, Service Areas, Products
[ ] Add Posts (Offers) for promotional content — do NOT bake into description
[ ] Add photos (logo, exterior, products, team) — Google ranks profiles with 10+ photos higher
[ ] Set up Q&A — seed 3-5 common questions to pre-empt customer questions
[ ] Enable messaging if you have capacity to reply within ~1 day
[ ] After publish: wait 1-3 days, search business name in incognito to verify rendering
```

## When to escalate to Google support

- Profile suspended (rare but happens for legitimate reasons too — duplicate listing, suspected fake address)
- Description edits rejected 3+ times despite passing the grep checklist (may need to contact Google support)
- Category permission denied (some niche categories require human review)

Path: GBP wp-admin → Support → Contact us. Response time: usually 24–72h.

## Anti-patterns

❌ **Cram everything into description** — Google's structured fields exist for a reason. Use them.

❌ **Pick the most-specific-sounding category** — "Bán lẻ thực phẩm tươi sống" sounds specific but may route to wrong intent. Use English autocomplete to confirm taxonomy.

❌ **Set up GBP after the website launches** — set up GBP first, link Google to the website URL during onboarding. Google indexes the business 30–60 days faster this way.

❌ **Ignore the Posts feature** — Posts surface promotional content WITHOUT violating description policy. Use Posts for "free shipping over X kg" / "20% off first order" / etc.

## Cross-references

- [`references/seo-checklist.md`](seo-checklist.md) — Schema LocalBusiness markup ties to GBP profile fields
- [`references/schema-jsonld.md`](schema-jsonld.md) — JSON-LD `Organization` / `LocalBusiness` `sameAs` should link to the public GBP profile URL
- [`references/vietnamese.md`](vietnamese.md) — Vietnamese-locale content writing patterns
