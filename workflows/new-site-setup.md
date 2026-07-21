# Workflow: Set up a new WordPress site A → Z

## Pre-flight

- [ ] Domain purchased, DNS pointed to hosting
- [ ] Hosting provisioned (CloudPanel / SiteGround / Hostinger / other)
- [ ] Let's Encrypt SSL active
- [ ] WordPress core installed
- [ ] Admin user: NOT "admin", strong password
- [ ] Admin email correct

## Day 1 — Foundation

### 1. Core settings (15 min)

```
Settings → General:
  - Site title, tagline (from project CLAUDE.md)
  - Timezone: per project
  - Date format: per locale (e.g. d/m/Y for VN, m/d/Y for US)
  - Week starts: per locale

Settings → Reading:
  - Front page: Static page (create Home draft)
  - Posts page: Blog (create Blog draft)

Settings → Permalinks:
  - Post name (/%postname%/)

Settings → Discussion:
  - Disable comments by default (unless needed)
```

⚠️ **Language pack** (for non-English locales): `update_option('WPLANG', 'vi')` only sets the DB value. You also need the `wp-content/languages/vi.mo` file for the admin UI to switch. Trigger via admin: Settings → Site Language → choose locale → Save (WP auto-downloads ~72 language files, ~2MB), or via WP-CLI:
```bash
wp language core install vi --activate
```
Verify: `ls wp-content/languages/vi.mo` exists.

### 2. Theme + plugins (30 min)

Install in order:
1. Astra (theme)
2. Elementor + Elementor Pro
3. WordPress MCP Adapter
4. msrbuilds/elementor-mcp (zip from GitHub)
5. ACF Free
6. Rank Math
7. Wordfence
8. WPS Hide Login
9. Two Factor
10. UpdraftPlus
11. WP Mail SMTP

⚠️ When uploading the msrbuilds/elementor-mcp zip from GitHub source: rename the folder to `elementor-mcp/` before zipping. The GitHub source zipball has a hash-suffixed folder name that fails WP plugin activation.

⚠️ **mcp-adapter requires the release zip, NOT the `trunk` branch**: trunk is missing `vendor/` (composer autoload broken) → fatal `\WP\MCP\Core\McpAdapter class missing`. Download the release zip v0.5.0+ from GitHub Releases (vendor pre-built).

### 3. Astra Customizer (30 min)

See `references/astra-customizer.md`. Brand-specific values come from the project `CLAUDE.md`.

### 4. Elementor settings (15 min)

```
Elementor → Settings → Features:
  - Flexbox Container: Active
  - Container Grid: Active (if needed)
  - Nested Tabs: Active
  - Nested Accordion: Active

Elementor → Settings → Advanced:
  - Enable Unfiltered File Uploads: Yes
  - Switch Editor Loader Method: ON if slow

Elementor → Settings → Performance:
  - CSS Print Method: External File
  - Optimize CSS Loading: Active
  - Optimize DOM Output: Active
```

### 5. Application Password + MCP setup (15 min)

See `references/elementor-mcp.md`.
Verify Claude Code `list-pages` succeeds.

### 6. Security hardening (15 min)

See `references/security.md`.

## Day 2 — Build pages

### 1. Create CPT if needed
See `workflows/add-cpt.md`.

### 2. Create ACF fields if needed
Field group export → save into `templates/acf/<project>.json`.

### 3. Build Theme Builder templates
See `workflows/theme-builder-loop.md`:
- Header global
- Footer global
- Single post
- Archive
- 404
- Search results

### 4. Build landing pages via MCP

Per page:
1. Backup
2. Plan section by section
3. `add-container` + `add-widget` loop
4. Verify with `get-page-structure`
5. Test 3 breakpoints in a real browser

## Day 3 — SEO + Performance

1. Rank Math setup → `references/seo-checklist.md`
2. Performance optimization → `references/performance.md`
3. Cloudflare config → `references/performance.md` Cloudflare section

## Day 4 — Pre-launch QA

- [ ] All forms tested (send + receive email)
- [ ] All CTAs link correctly
- [ ] No 404 internal links (Broken Link Checker plugin)
- [ ] Lighthouse mobile ≥85
- [ ] Cross-browser: Chrome, Safari, Firefox, Edge
- [ ] Cross-device: 375, 768, 1280, 1920px
- [ ] Search Console verified + sitemap submitted
- [ ] Google Analytics 4 receiving events
- [ ] Backup snapshot before launch

## Launch Day

1. DNS final check
2. Cloudflare proxied
3. Submit sitemap to GSC
4. Monitor for 24h: error log, uptime, GA events
5. Announce

## Decision tree: header / footer rendering strategy

Sites using the `elementor_canvas` template (which strips Astra header/footer) need their own header / footer. Two approaches:

```
Site uses default theme header/footer (Astra header builder)?
├─ YES → Nothing to do. Astra header/footer auto-renders via wp_head + astra_footer hooks.
│
└─ NO (canvas template strips Astra):
   ├─ Have Elementor Pro?
   │  └─ YES → Theme Builder template with display conditions "Entire Site"
   │           Header location → Pro Theme Builder
   │           Footer location → Pro Theme Builder
   │           Build once, propagates automatically to every page
   │
   └─ NO Elementor Pro:
      ├─ Inject section 0 (header strip) + last section (footer) into EVERY page
      ├─ Sync helper script: when updating the header, walk all canvas pages and replace section 0
      └─ Hash anchors `#xxx` in the header → transform to `/#xxx` (root-relative)
         (See references/pitfalls.md "Shared section across pages with hash anchors")
```

Astra Free 4.13.x does NOT auto-suppress the header when a Theme Builder template is active → you need a mu-plugin bridge (see `references/astra-customizer.md` "Astra Free + Theme Builder bridge").

## After adding blog / CPT — nav menu assignment check (`wp_page_menu()` fallback trap)

**Symptom**: You publish blog posts (or a new CPT) and everything looks fine on the homepage. Then you click a blog post link and the header on the post page is **broken** — it lists every page on the site (including Cart / Checkout / My account / legal pages) with each page's full SEO title, header wraps to multiple lines, layout shattered.

**Root cause**: Astra falls back to `wp_page_menu()` when no menu is assigned to `primary` location. `wp_page_menu()` outputs a `<ul>` of ALL top-level pages, using each page's title verbatim. Homepage / Canvas templates don't hit this because they build their own header inside Elementor data → don't render the theme's default header at all. Theme-default templates (blog post single, legal pages, WooCommerce cart / checkout / account) DO render the theme header → catch the fallback.

**Detection**:

```bash
# 1. Are ANY menus registered?
curl -s -u $U:$P "$SITE/wp-json/wp/v2/menus" | jq 'length'
# → 0 = no menu exists → fallback active on every theme-default page

# 2. Which menu is assigned to each location?
curl -s -u $U:$P "$SITE/wp-json/wp/v2/menu-locations" | jq '{primary,mobile_menu,footer_menu} | map_values(.menu)'
# → { "primary": 0, "mobile_menu": 0, "footer_menu": 0 } = all unassigned → fallback active

# 3. Fetch a blog post page and count top-level nav items in the header
curl -s "$SITE/hello-world/" | grep -oE '<a[^>]+class="[^"]*menu-item[^"]*"' | wc -l
# → 15+ = fallback listing every page = broken
```

### Fix — create menu + assign to all locations via REST

```bash
# 1. Create menu
MENU_ID=$(curl -s -u $U:$P -X POST "$SITE/wp-json/wp/v2/menus" \
    -H "Content-Type: application/json" \
    -d '{"name": "Main Nav"}' | jq -r .id)

# 2. Add menu items — top-level pages
for PAGE_ID in <home-id> <about-id> <blog-id> <contact-id>; do
    curl -s -u $U:$P -X POST "$SITE/wp-json/wp/v2/menu-items" \
        -H "Content-Type: application/json" \
        -d "{\"menus\": $MENU_ID, \"type\": \"post_type\", \"object\": \"page\", \"object_id\": $PAGE_ID}"
done

# 3. Add menu items — categories (submenu under blog)
BLOG_ITEM_ID=<the-menu-item-id-created-for-blog-in-step-2>
for CAT_ID in <cat-1> <cat-2>; do
    curl -s -u $U:$P -X POST "$SITE/wp-json/wp/v2/menu-items" \
        -H "Content-Type: application/json" \
        -d "{\"menus\": $MENU_ID, \"type\": \"taxonomy\", \"object\": \"category\", \"object_id\": $CAT_ID, \"parent\": $BLOG_ITEM_ID}"
done

# 4. Assign menu to locations
curl -s -u $U:$P -X PUT "$SITE/wp-json/wp/v2/menus/$MENU_ID" \
    -H "Content-Type: application/json" \
    -d '{"locations": ["primary", "mobile_menu", "footer_menu"]}'
```

### Post-add checklist for blog / CPT

Whenever adding a blog or CPT to a site:

- [ ] `page_for_posts` option points at a real page (Settings → Reading → "Posts page")
- [ ] Menu exists and is assigned to at least `primary` location
- [ ] Blog page (`page_for_posts`) appears in the menu OR menu has a "Blog" link pointing at it
- [ ] Fetch a blog post URL directly and confirm header renders correctly (not `wp_page_menu` fallback)
- [ ] If CPT: `has_archive => true` in CPT registration, and archive URL added to menu if visible in nav
- [ ] Menu-locations survey after any theme change (theme swap resets location assignments)

Failing any of these → theme-default templates for that content type render broken UI. Homepage looks fine because Canvas / Elementor build their own header; the fallback only surfaces where the theme controls the layout.
