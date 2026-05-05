# Workflow: Setup WordPress site mới từ A-Z

## Pre-flight

- [ ] Domain mua, DNS point về hosting
- [ ] Hosting provisioned (CloudPanel/SiteGround/Hostinger/khác)
- [ ] SSL Let's Encrypt active
- [ ] WordPress core install
- [ ] Admin user: KHÔNG "admin", strong password
- [ ] Email admin đúng

## Day 1 — Foundation

### 1. Core settings (15 phút)

```
Settings → General:
  - Site title, tagline (từ CLAUDE.md project)
  - Timezone: UTC+7 (cho VN)
  - Date format: d/m/Y
  - Week starts: Monday

Settings → Reading:
  - Front page: Static page (tạo Home draft)
  - Posts page: Blog (tạo Blog draft)

Settings → Permalinks:
  - Post name (/%postname%/)

Settings → Discussion:
  - Disable comments default (nếu không cần)
```

⚠️ **Language pack** (cho non-English locale): `update_option('WPLANG', 'vi')` chỉ set DB value. Phải có file `wp-content/languages/vi.mo` thì admin UI mới switch. Trigger qua admin: Settings → Site Language → Vietnamese → Save (WP auto-download 72 lang files ~2MB), hoặc WP-CLI:
```bash
wp language core install vi --activate
```
Verify: `ls wp-content/languages/vi.mo` có tồn tại.

### 2. Theme + plugins (30 phút)

Cài theo thứ tự:
1. Astra (theme)
2. Elementor + Elementor Pro
3. WordPress MCP Adapter
4. msrbuilds/elementor-mcp (zip từ GitHub)
5. ACF Free
6. Rank Math
7. Wordfence
8. WPS Hide Login
9. Two Factor
10. UpdraftPlus
11. WP Mail SMTP

⚠️ Khi upload zip msrbuilds/elementor-mcp từ GitHub source: rename folder thành `elementor-mcp/` trước khi zip lại. GitHub source zipball có hash-suffixed folder làm WP activation lỗi.

⚠️ **mcp-adapter dùng release zip, KHÔNG `trunk` branch**: trunk thiếu `vendor/` (composer autoload broken) → fatal `\WP\MCP\Core\McpAdapter class missing`. Download release zip v0.5.0+ từ GitHub Releases (vendor pre-built).

### 3. Astra Customizer (30 phút)

Đọc `references/astra-customizer.md`. Brand-specific lấy từ CLAUDE.md project.

### 4. Elementor settings (15 phút)

```
Elementor → Settings → Features:
  - Flexbox Container: Active
  - Container Grid: Active (nếu cần)
  - Nested Tabs: Active
  - Nested Accordion: Active

Elementor → Settings → Advanced:
  - Enable Unfiltered File Uploads: Yes
  - Switch Editor Loader Method: ON nếu chậm

Elementor → Settings → Performance:
  - CSS Print Method: External File
  - Optimize CSS Loading: Active
  - Optimize DOM Output: Active
```

### 5. Application Password + MCP setup (15 phút)

Đọc `references/elementor-mcp.md`.
Verify Claude Code `list-pages` thành công.

### 6. Security hardening (15 phút)

Đọc `references/security.md`.

## Day 2 — Build pages

### 1. Tạo CPT nếu cần
Đọc `workflows/add-cpt.md`.

### 2. Tạo ACF fields nếu cần
Field group export → save vào templates/acf/<project>.json.

### 3. Build Theme Builder templates
Đọc `workflows/theme-builder-loop.md`:
- Header global
- Footer global
- Single post
- Archive
- 404
- Search results

### 4. Build landing pages qua MCP

Per page:
1. Backup
2. Plan section-by-section
3. add-container + add-widget loop
4. Verify get-page-structure
5. Test 3 breakpoint trên browser thật

## Day 3 — SEO + Performance

1. Rank Math setup → `references/seo-checklist.md`
2. Performance optimization → `references/performance.md`
3. Cloudflare config → `references/performance.md` Cloudflare section

## Day 4 — Pre-launch QA

- [ ] All forms test send + receive email
- [ ] All CTA link đúng
- [ ] No 404 internal link (Broken Link Checker plugin)
- [ ] Lighthouse mobile ≥85
- [ ] Cross-browser: Chrome, Safari, Firefox, Edge
- [ ] Cross-device: 375, 768, 1280, 1920px
- [ ] Search Console verified + sitemap submitted
- [ ] Google Analytics 4 receive event
- [ ] Backup snapshot trước launch

## Launch Day

1. DNS final check
2. Cloudflare proxied
3. Submit sitemap GSC
4. Monitor 24h: error log, uptime, GA event
5. Announce

## Decision tree: header/footer rendering strategy

Sites dùng `elementor_canvas` template (strip toàn bộ Astra header/footer) cần build header/footer riêng. 2 hướng:

```
Site dùng theme default header/footer (Astra header builder)?
├─ YES → KHÔNG cần làm gì. Astra header/footer auto render qua wp_head + astra_footer hooks.
│
└─ NO (canvas template strip Astra):
   ├─ Có Elementor Pro?
   │  └─ YES → Theme Builder template với display conditions "Entire Site"
   │           Header location → Pro Theme Builder
   │           Footer location → Pro Theme Builder
   │           1 lần build, propagate auto cho mọi page
   │
   └─ NO Elementor Pro:
      ├─ Inject section 0 (header strip) + section cuối (footer) vào MỖI page
      ├─ Helper script đồng bộ: khi update header, walk tất cả canvas pages, replace section 0
      └─ Hash anchor `#xxx` trong header → transform thành `/#xxx` (root-relative)
         (Xem references/pitfalls.md "Shared section across pages with hash anchors")
```

Astra free 4.13.x KHÔNG tự suppress header khi Theme Builder template active → cần mu-plugin bridge (xem references/astra-customizer.md "Astra free + Theme Builder bridge").
