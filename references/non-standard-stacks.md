# Non-standard stacks — when site doesn't use Astra + Elementor

> wp-stack default = Astra + Elementor Pro + Rank Math. Real-world inherited sites often diverge. This reference covers fallback patterns + what to AVOID when proposing.

## Detection: identify stack first

Trước khi propose bất cứ MCP write nào, check actual stack:

```bash
# 1. Active theme
curl -s -u user:app_pw 'https://site/wp-json/wp/v2/themes?status=active' | jq '.[] | {name: .name.raw, version: .version}'

# 2. Active plugins (look for builder)
curl -s -u user:app_pw 'https://site/wp-json/wp/v2/plugins' | jq '.[] | select(.status=="active") | .name' | grep -i -E 'elementor|flatsome|wpbakery|bricks|divi|gutenberg|kadence|breakdance'

# 3. Page builder fingerprint (curl frontend HTML)
curl -s 'https://site/' | head -50 | grep -i -E 'elementor|ux-builder|vc_row|brxe|et_pb|wp-block-' | head -5
```

→ **Document trong project's CLAUDE.md** ngay khi detect, không assume Astra+Elementor.

## Stack matrix — what MCP tools work where

| Stack detected | Native MCP support | Workaround |
|---|---|---|
| **Astra + Elementor** | ✅ Full — Elementor MCP + Astra MCP + Rank Math MCP | Standard wp-stack |
| **Flatsome + UX Builder** | ⚠️ Partial — Rank Math MCP works, NO UX Builder MCP | Direct REST `update_post_meta` + custom CSS via theme options |
| **WPBakery (Visual Composer)** | ❌ No MCP — shortcode-based content | Edit content via WP REST `posts/{id}` content field directly (raw shortcode) |
| **Bricks Builder** | ❌ No MCP (yet) — JSON-based content | REST `bricks/v1/render` endpoint nếu plugin expose; otherwise content via JSON in postmeta |
| **Divi Builder** | ❌ No MCP — shortcode-based | Like WPBakery |
| **Gutenberg only** (no builder) | ✅ Full — `core/*` block API qua REST | Standard WP REST blocks-based |
| **Kadence Blocks** | ✅ Mostly — Gutenberg-based, REST blocks work | Block attributes manipulation via REST |
| **Breakdance** | ❌ No MCP — custom JSON storage | Theme builder UI only |

## Flatsome + UX Builder

**Site example**: any Flatsome theme + UX Builder install (common VN WooCommerce setup)

**Theme info**:
- Premium theme by UX-Themes (~$59 ThemeForest)
- WooCommerce-focused, popular cho VN e-commerce sites
- UX Builder = built-in page builder (NOT separate plugin)
- Storage: `_ux_builder_meta` post meta (proprietary JSON-like format)

**MCP capability**:
- ❌ No UX Builder MCP plugin exists (chưa có 3rd party wrapper)
- ✅ Rank Math MCP works (SEO meta same as any site)
- ✅ Core mcp-wp/* abilities work (CRUD posts, terms, options)
- ❌ Page structure editing requires UX Builder UI manually

**What to AVOID**:

```
❌ DON'T propose: elementor-mcp/build-page, elementor-mcp/add-heading, etc.
   → Plugin not installed, tools won't be in connector

❌ DON'T inject Elementor JSON into UX Builder pages
   → Different schema, will break rendering

❌ DON'T propose adding Elementor "alongside" UX Builder
   → Double-builder = 2x bloat, conflicting CSS, owner backlash
```

**What WORKS**:

| Task | Approach |
|---|---|
| Update SEO title/desc | `rankmath-mcp/update-meta` (works on any stack) |
| Bulk meta read | `rankmath-mcp/bulk-get-meta` |
| Edit post content (text/HTML) | `mcp-wp/update-post` với raw HTML in content field (UX Builder ignores, render as fallback) |
| Add custom CSS | Flatsome → Customize → Advanced → Custom CSS (manual) |
| Theme settings | Flatsome → Theme Options (manual, no MCP) |
| Insert shortcode-based block | Add to content via REST post update; UX Builder parses |

**Fallback pattern — edit content programmatically**:

```bash
# Update post content raw HTML (UX Builder will render as raw HTML block)
curl -u user:app_pw -X POST \
  'https://<site>.example.com/wp-json/wp/v2/posts/123' \
  -H 'Content-Type: application/json' \
  -d '{"content": "<h2>New section</h2><p>Content here</p>"}'
```

**Acceptable**: SEO meta updates, content text replacement, redirect setup, schema injection via theme footer.

**Boundary**: layout edits, page builder structure — direct user/owner via wp-admin UI.

## WPBakery (Visual Composer) — shortcode-based

**Site example**: (older sites, ~2018-2020 era)

**Storage**: content field has shortcodes như `[vc_row][vc_column][vc_column_text]content[/vc_column_text][/vc_column][/vc_row]`

**MCP capability**:
- ✅ REST POST `wp/v2/posts/{id}` với raw shortcode trong content — WPBakery parses on render
- ✅ Rank Math MCP works
- ❌ No structured editing (must construct shortcodes manually)

**Approach**:

```php
// Generate shortcode programmatically
$content = '[vc_row][vc_column width="1/1"][vc_column_text]'
         . '<h2>Heading</h2><p>Paragraph content</p>'
         . '[/vc_column_text][/vc_column][/vc_row]';

// POST via REST
```

**Limitation**: phải biết WPBakery shortcode schema (60+ shortcodes, version-dependent).

## Bricks Builder — JSON-based, no MCP (2026)

**Storage**: postmeta `_bricks_page_content_2` = JSON array of element objects.

**Theoretical MCP path**: build wrapper plugin exposing Bricks element CRUD as abilities (no upstream MCP yet).

**Current approach**: edit via wp-admin Bricks UI manually; agent provides SEO/content/schema only.

## Divi Builder — shortcode-based (similar WPBakery)

**Storage**: shortcodes `[et_pb_section][et_pb_row][et_pb_column type="4_4"][et_pb_text]content[/et_pb_text]...`

**Same pattern as WPBakery**: edit via REST content with shortcodes; no structured MCP support.

## Gutenberg only (no builder)

**Best case** ✅ — WP core supports block REST API out-of-box.

```bash
# Read blocks
curl -u user:app_pw 'https://site/wp-json/wp/v2/posts/123' | jq .content.raw

# Update blocks (via parse_blocks-friendly HTML)
curl -u user:app_pw -X POST 'https://site/wp-json/wp/v2/posts/123' \
  -H 'Content-Type: application/json' \
  -d '{"content": "<!-- wp:heading --><h2>New heading</h2><!-- /wp:heading -->"}'
```

`mcp-wp/*` abilities work fully here.

## Kadence Blocks (Gutenberg-extension)

✅ Same as Gutenberg + extra Kadence blocks parse via block comment markers:

```
<!-- wp:kadence/rowlayout {"uniqueID":"..."} -->
<div class="...">...</div>
<!-- /wp:kadence/rowlayout -->
```

Edit via standard WP REST blocks API. Block attributes (in `{...}`) preserved.

## Decision tree — when to install Elementor on inherited site?

User asks: "Có nên cài Elementor lên site đang dùng [X]?"

| Inherited builder | Migrate to Elementor? | Why |
|---|---|---|
| **No builder** (raw HTML / Classic Editor) | ✅ Recommend | Clean slate, Elementor adds visual editing |
| **Gutenberg** | ⚠️ Maybe | Gutenberg is fine for content sites; Elementor only if visual landing pages needed |
| **WPBakery / Divi** | ⚠️ Heavy migration | Shortcodes everywhere; migration = rewrite all content. Don't unless owner committed |
| **Flatsome + UX Builder** | ❌ Don't | UX Builder integrated with theme; replacing = lose theme features |
| **Bricks** | ❌ Don't | Bricks is similar-tier; switching = political not technical |
| **Already Elementor** | ✅ Already there | Apply standard wp-stack |

→ **Rule of thumb**: don't migrate builders unless owner explicitly wants. Each builder = 1-2 weeks of content rewrite + QA + risk.

## Documenting non-standard stack trong project CLAUDE.md

Template section để paste vào project CLAUDE.md khi detect non-Astra/Elementor:

```markdown
## Stack (NON-STANDARD)

⚠️ **Important**: Site dùng [Flatsome + UX Builder / WPBakery / Bricks / ...], KHÔNG phải Astra + Elementor.

→ wp-stack reference `non-standard-stacks.md` cho fallback patterns.

| Component | Active | Standard wp-stack | Workaround |
|---|---|---|---|
| Theme | [name] | Astra | (theme settings via wp-admin manual) |
| Page builder | [name] | Elementor | [REST content / shortcode / no-MCP] |
| MCP edit | ❌ no UI MCP | ✅ Elementor MCP | Direct REST POST content |
| SEO | ✅ Rank Math MCP | ✅ Same | works fully |

### What Claude CAN do
- SEO meta updates (Rank Math MCP)
- Content text updates (REST POST content with builder-specific syntax)
- Redirect setup
- Schema injection (theme footer manual OR Rank Math)

### What Claude CANNOT do
- Page structure editing (must user via builder UI)
- Theme settings changes (must user via wp-admin)
- Builder-specific widget adding
```

## Cross-references

| Topic | See |
|---|---|
| Standard Astra + Elementor stack | [`stack.md`](stack.md) |
| Rank Math MCP (works any stack) | [`rankmath.md`](rankmath.md) |
| WP REST core abilities | [`wp-abilities.md`](wp-abilities.md) |
| Direct REST bypass MCP | [`wp-abilities.md`](wp-abilities.md) §When direct REST > MCP bridge |
| Detect stack at audit time | [`../workflows/comprehensive-audit.md`](../workflows/comprehensive-audit.md) |
