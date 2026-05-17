# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.8.0] — 2026-05-17

Round 10 weekly distillation. ~2,400 lines of new insights this week across 4 production sites. 14 patterns promoted into 11 file updates + 3 new reference files. Brand-leak scan + commit-message scan run pre-push per the established governance.

### Added — 3 new reference files

- **`references/code-snippets.md`** — Code Snippets plugin REST API workflow. `GET/POST /wp-json/code-snippets/v1/snippets` endpoints, `code_error: null` validation, control-char JSON parse gotcha, surgical-edit workflow (list → fetch → edit → POST update → cache purge). Battle-tested cleanup pattern for duplicate `og:image` emissions across legacy snippet sites.
- **`references/gbp-setup.md`** — Google Business Profile content-policy reference. 5 description rules (no phone / pricing / promo / URL / HTML — auto-reject triggers), compliant Vietnamese template for B2B businesses, category Vietnamese-autocomplete gotcha (type English to disambiguate B2B vs B2C intent), re-submit timeline + escalation path.
- **`references/mu-plugin-patterns.md`** — MU-plugin patterns for surviving upstream-plugin misbehavior. Suppress anonymous Closure via `ReflectionFunction::getFileName()` + `$wp_filter` unset (since `remove_action()` can't reference Closures), conditional override, bridge between two plugins, polyfill, force-option override. File-organization conventions.

### Changed

- **`references/rankmath.md`** — major update (6 insights bundled):
  - Schema Builder 2.x `rank_math_schema_{Type}` meta format unlocks rich snippets WITHOUT Schema Pro plugin. Verified types: Service, LocalBusiness, Article, Festival, BusinessEvent, SocialEvent, Product.
  - FAQPage NOT supported via the schema-meta path — inline JSON-LD via HTML widget is the workaround.
  - WC Shop page title precedence — per-page meta wins over template; `pt_product_archive_title` is NOT in the title chain.
  - OG image resolution chain: `rank_math_facebook_image` (raster only) → `featured_media` → site default; SVG silently skipped.
  - WooCommerce product OG image via `/wc/v3/products` `images[]` array (REPLACE semantic — fetch + append + PUT for additive).
  - Wrapper-plugin response/input key conventions: semantic keys (`posts[]`, `redirections[]`, `links[]`, `rows[]`, `destination`) instead of generic ones.
- **`references/deployment.md`** — 4 hosting traps added:
  - cPanel `save_file_content` UPDATEs only, doesn't CREATE — use `upload_files` (multipart) for new files; combo "probe + restore stub" pattern avoids creating new files entirely.
  - Imunify360 quarantines Vietnamese strings in PHP body — base64-encode in GET param, decode in PHP (source stays 100% ASCII).
  - PHP `error_log` location on CloudLinux LVE / cPanel = vhost root (`/home/<user>/<domain>/error_log`), NOT `wp-content/debug.log`. Detection probe + matrix across 7+ shared hosts.
  - Server-side substring filter for huge `error_log` triage (29MB+ logs → `tail -100` is 100% spam noise; filter by `Fatal` / time-window via ability).
- **`references/wp-abilities.md`** — 3 REST gotchas added:
  - WP REST trash post requires `DELETE /posts/{id}` (POST `status: trash` is rejected because the `status` enum excludes `trash`).
  - REST `content.rendered` pagination URLs use REST endpoint URI (`/wp-json/wp/v2/pages/123/page/2/`) NOT canonical (`/about/page/2/`). For audit, fetch via frontend GET with browser User-Agent.
  - App Password auth REST → 200 (works), wp-admin → 302 (redirect to login, NOT fatal). Distinguish: real fatal = 500. Healthy-site post-fix verification checklist included.
- **`references/elementor-mcp.md`** — 3 widget gotchas added:
  - Heading widget strips SVG / HTML from `title` field via `wp_kses_post`. 3 workarounds documented; separate-HTML-widget-BEFORE-heading is the recommended path.
  - SVG upload blocked by host WAF (AZDIGI etc.) → CSS `mask-image` data URI workaround for native icon-box widgets. URL-encoding pattern + mono-color trade-offs.
  - Site-wide conversion tracking via Custom Code Snippet — multi-platform fire pattern (GA4 + Meta + TikTok, each `try/catch`-wrapped), event delegation with `closest()` + capture phase for late-bound elements.
- **`references/pitfalls.md`** — 4 critical pitfalls added:
  - **CRITICAL** WooCommerce 9.x Coming Soon Mode silent SEO killer — shop + product show placeholder VN text regardless of content; Google indexes the placeholder; 30+ min typical misdiagnosis time.
  - **CRITICAL** LiteSpeed `object-cache.php` drop-in version mismatch — blocks ALL plugin activate hooks with `litespeed_oc_disable_ext_cache()` undefined fatal; misdiagnosed as "the plugin being activated has a bug" when the LSC drop-in is the actual cause.
  - XML comment regex trap when injecting SVG sprite — non-greedy `.*?` doesn't enforce uniqueness; explicit `count=0` for replace-all in `re.sub()`.
  - WP shared hosting SVG-upload block → CSS `mask-image` data URI workaround (cross-ref to `elementor-mcp.md` full recipe).
- **`references/seo-checklist.md`** — added "Duplicate `og:image` / meta tag detection — 3-layer audit": SEO plugin (layer 1, source of truth) / Code Snippets legacy hardcoded (layer 2) / theme hooks + `wp_site_icon()` (layer 3). Triage workflow + grep commands.
- **`references/mcp-architecture.md`** — added "stdio bridge vs HTTP MCP" architectural distinction. Comparison at scale (10+ sites): context-token cost (210 tools per site vs 3 META tools), maintenance burden, failure surface. When-to-use decision matrix for each. Migration path stdio → HTTP.
- **`workflows/claude-mcp-connector-setup.md`** — 3 sections added:
  - HTTP MCP vs stdio bridge decision matrix (long-term standard: HTTP for new sites). Compensate for HTTP's lost autocomplete with auto-dumped `.ability-catalog.md`.
  - Windows manual `~/.claude.json` edit when no `claude` CLI on PATH. 3 scope locations (user / project-local / project-checked-in) with belt-and-suspenders option.
  - HTTP MCP transport `initialize` handshake required (POST `tools/list` direct = 400). 2-step flow + required headers (Mcp-Session-Id, MCP-Protocol-Version, Accept: application/json,text/event-stream).
- **`workflows/comprehensive-audit.md`** — added "Diagnostic step — PHP-runtime ability count vs REST-list count". Compare `wp_get_abilities()` returns vs `/wp-abilities/v1/abilities` list to distinguish "filter problem" (pagination, show_in_rest, REST permission) from "registration problem" (plugin inactive, fatal in data files, hook not firing).
- **`references/fluent-forms.md`** — added "Submission test via `admin-ajax.php`" — anonymous frontend simulation, double-encoded inner `data=` param, dropdown EXACT-match validation, 423 validation-error format, anti-patterns.
- **`workflows/bulk-content-automation.md`** — added "Master dataset pattern" — Python module as single source of truth (slug + facts + SEO + schema centralized), import-and-iterate, version-controlled diff. Real result: 25 portfolio × 14 fields = 350 data points centralized; Python dict scales to ~200 items, then move to SQLite.
- **`workflows/og-image-generation.md`** — added Flux 2 Pro img-to-img recipe ($0.06/image, reference image preserves brand color/lighting, 1344×752 output, prompt tips, upload + featured_media set workflow).
- **`SKILL.md`** — 3 new task routes added to the load-which-file matrix.
- **`README.md`** — 24 references + 21 workflows (was 18 + 17). Refreshed inventory tree.

### Sources — patterns extracted from

4 production sites this week: bulk SEO automation + Rank Math wrapper plugin iterations + JSON-LD schema rollout across many post types + Schema Builder 2.x format discovery + LSC object-cache drop-in fatal + WooCommerce Coming Soon SEO trap + CloudLinux LVE error_log location + HTTP MCP vs stdio architectural decision + Windows ~/.claude.json manual edit + MU-plugin Closure suppression + SVG WAF workaround + Vietnamese in PHP base64 GET workaround + Code Snippets REST API + GBP description content policy.

[0.8.0]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.8.0

## [0.7.2] — 2026-05-13 (CI fix + brand-leak scrub)

### Fixed

- **CI lychee link-check** — 3 errors traced + fixed:
  - `[0.7.0]` + `[0.7.1]` orphan release-tag link references in CHANGELOG → resolved by retroactively tagging the existing commits + creating GitHub release pages (commit `30d6cf2` → v0.7.0; commit `535705d` → v0.7.1)
  - `fontawesome.com/v5/free` URL in `references/elementor-mcp.md` → updated to `/search?o=r&m=free` (regression of the v0.5.0 fix that originally landed in `pitfalls.md` but slipped into a new occurrence in `elementor-mcp.md` during v0.7.x)

### Changed — brand-leak scrub across v0.7.x content

The v0.7.x content shipped with project-specific identifiers (client acronyms, insight-ID prefixes that revealed project names, real-site references) that the pre-push scan added in commit `b3885d0` was meant to prevent. Caught + scrubbed in this patch:

- Insight ID prefixes like `<acronym>-2026-05-XX-NNN` rewritten to `weekly distillation 2026-05-XX #NNN` (no project-name reveal)
- Project-name-revealing phrasing rewritten with neutral placeholders (`the audited site`, `inherited B2B site`, `project A` / `project B`)
- Brand-prefix CSS marker examples rewritten to generic `.<project-slug>-` syntax (the literal scrubbed strings are not quoted here to avoid re-leaking via the changelog itself)
- GitHub release notes for v0.7.0 + v0.7.1 also edited via `gh release edit` for consistency

### Files touched

12 files, 66 lines re-worded. No semantic content change — patterns and references identical, only the source-attribution / example markers neutralized.

[0.7.2]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.7.2

## [0.7.1] — 2026-05-13 (night — Group C UPDATE completions)

18 sections appended to 8 existing skill files. Continues Group D (v0.7.0) work — promotes UPDATE-tier insights from weekly insights.md cross-project. Insights that were already covered by skill v0.6.0 were skipped during execution (saved ~12 entries from initial 33-entry plan).

### Changed — sections added

- **`references/elementor-mcp.md`** — 4 new sections:
  - "Elementor 4.0 — `update-page-settings custom_css` field does NOT load on frontend" (insight #13: workaround via HTML widget `<style>` injection; kit `custom_css` alternative)
  - "FontAwesome Pro-only icons render EMPTY box" (insight #15.2: free alternatives table)
  - "Elementor section `background_image` lưu trong post-X.css, KHÔNG inline HTML" (insight #30: verify via post-CSS file fetch, not HTML grep)
  - "Diagnostic technique: demote `header_size` to find H1 duplication source" (insight #085: same-widget vs different-widgets distinguish methodology)

- **`references/pitfalls.md`** — 3 new sections:
  - "`theme-post-content` widget trên page hub = self-recursion render" (insight #084: critical SEO bug — data clean, render multiplicity 2x via `the_content()` self-evaluation; fix = remove widget; detection + verification + reference fix from the audited site)
  - "Brand-css generic class collisions với Elementor" (insight #32: `.container`, `.row`, etc. Elementor uses; namespace via project prefix `.<project-slug>-`)
  - "Default theme dark/light variant invisible on light parent" (insight #33: brand-css designed cho dark theme rendering invisible on light bg; defensive mu-plugin CSS via `:not()`)

- **`references/performance.md`** — 3 new sections:
  - "LiteSpeed Cache lifecycle — 2 invalidation paths" (insight #27: save_post auto-purge reliable vs manual Purge All flaky via REST)
  - "LiteSpeed default `max-age=604800` (7 days) → Lighthouse cache policy FAIL" (insight #49: .htaccess override to 1 year + immutable for versioned assets)
  - "Avatar `src=""` is LiteSpeed lazy-load, NOT broken image" (insight #50: misdiagnosis red herring; real issue is oversized image without srcset)

- **`references/vietnamese.md`** — 1 new section:
  - "Tooling: PowerShell scripts với Vietnamese content cần UTF-8 BOM" (insight #087: PS 5.1 system codepage mojibake on diacritics; BOM fix recipe + verification)

- **`references/mcp-architecture.md`** — 1 new section:
  - "REST registry pagination caveat — `per_page` cap on abilities list" (insight #61: default 100/page; npm bridge hardcoded miss; per_page=200 override; MCP discover bypass; em script `cache_abilities.ps1` reference)

- **`references/image-optim-recipes.md`** — 3 new sections:
  - "WordPress `srcset` variants must be optimized as a set" (insight #48: browser picks 1 by DPR × viewport; optimize all variants of basename together)
  - "Replicate API rate limit — >2 parallel = 429" (insight #28: sequential với sleep 10s, batch 2-by-2 max; failed calls not charged)
  - "Flux 2 Pro aspect_ratio — 11 ratios only, no 21:9" (insight #38: workarounds — switch model OR CSS object-position crop)

- **`workflows/comprehensive-audit.md`** — 2 new sections:
  - "Audit Rank Math features — Link Genius backend = REST routes" (insight #066 equivalent: backend is REST routes `/rankmath/v1/links/*`, not abilities; direct audit pattern + how to wrap for MCP)
  - "Rank Math `rank_math_*` meta NOT exposed via REST default" (insight #29: silent ignore on PATCH; 3 workaround paths)

- **`workflows/build-mcp-wrapper-plugin.md`** — 1 new section:
  - "Deploy wrapper plugin — local zip workflow" (insight #090: `mcp-wp/install-plugin` URL-only constraint; 4 deploy paths — manual wp-admin / public URL / SCP / WordPress.org; verify + rollback procedures)

### Skipped — already covered by skill v0.6.0+

12 insights from initial Group C plan were already covered:
- insight #14 (Fluent Forms button styling) → `fluent-forms.md` § Submit-button styling
- insight #15.1 (parallel widget reverse order) → `elementor-mcp.md` § `add-container` cells append at INDEX 0
- insight #15.3 (currency_format) → `elementor-mcp.md` § `add-price-table` currency_format
- insight #15.4 (show_ribbon inherit) → `elementor-mcp.md` § show_ribbon carries over from cloned cards
- insight #17 (empty Fluent Form) → `fluent-forms.md` references `pitfalls.md` shortcode-empty entry
- insight #24 (update_page_from_file skip post_content) → `elementor-mcp.md` § `update_page_from_file` does NOT regen `post_content`
- insight #25 (REST binary capture corruption) → `deployment.md` § REST API response capture safety
- insight #26 (media duplicate filename suffix) → `deployment.md` § WP media duplicate filename pattern
- insight #34 (css_classes snake_case) → `elementor-mcp.md` § css_classes field name — widget vs container
- insight #51 + insight #60 (Lighthouse URL anchor + total-byte-weight) → `lighthouse-driven-optim.md` existing coverage
- insight #017 (Astra site-post-title bulk) → already PROMOTED-TO-SKILL v0.2.0
- insight #018 + #071 (Rank Math updateMeta REST + tag-based focus_keyword) → `workflows/seo-audit.md` v0.6.0

### Insights promoted CANDIDATE → PROMOTED-TO-SKILL via this round

Insights: #084 (theme-post-content recursion), #085 (demote-h2 diagnostic), #087 (PS UTF-8 BOM), #090 (install-plugin URL constraint).
Project-A insights: #13 (Elementor 4 custom_css broken), #15.2 (FA Pro icons empty).
Project-B insights: #27 (LiteSpeed save_post), #28 (Replicate rate limit), #29 (Rank Math meta REST), #30 (background_image in post-X.css), #32 (brand-css class collision), #33 (dark/light variant invisible), #38 (Flux aspect ratio), #48 (srcset variants), #49 (LSC max-age Lighthouse), #50 (avatar src empty lazy-load), #61 (per_page cap).

### Total — Round 9 + Round 9.1 cumulative

- 5 new files (Group D): `rankmath.md`, `schema-jsonld.md`, `native-html-patterns.md`, `litespeed-cache-mgmt.md`, `bulk-content-automation.md`
- 6 sections added (Group D)
- 18 sections added (Group C — this round)
- = **5 new files + 24 new sections** total this distillation cycle

[0.7.1]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.7.1

## [0.7.0] — 2026-05-13 (evening — Round 9 weekly distillation)

20 insights promoted from weekly insights.md cross-project (inherited B2B site, project-B, project-A — Group D in plan `insights-skill-classification-2026-05-13.md`). 5 new reference/workflow files + 6 new sections appended to existing files. Routing matrix in SKILL.md gains 5 rows.

### Added — 5 new files

- **`references/rankmath.md`** — Rank Math SEO behaviors, quirks, automation patterns. 6 sections: (1) SEO score recompute is LAZY — REST updates don't trigger compute; (2) redirect `comparison: exact` overrides published source URL (allows "setup redirect → trash source" reverse workflow); (3) `rank_math_*` post meta NOT exposed via REST default (3 workaround options); (4) wrapper plugin response key conventions; (5) LiteSpeed cache + meta stale-read trap; (6) sitemap regen triggers. Insights sources: weekly distillation 2026-05-13 #074, -077, -078.
- **`references/schema-jsonld.md`** — Structured Data (JSON-LD / Schema.org) patterns + pitfalls. 5 sections: (1) Multi-source `@id` conflict — rename + `parentOrganization` cross-link pattern; (2) YMYL Physician schema 18-field design for E-E-A-T (template + per-profession reuse); (3) JSON-LD injection methods comparison (plugin vs HTML widget vs WP filter); (4) validation tools; (5) common types reference. Insights sources: weekly distillation 2026-05-13 #079, -080.
- **`references/native-html-patterns.md`** — Zero-JS, browser-native, A11y-by-default UI patterns. 5 sections: FAQ accordion `<details>`/`<summary>`, Google Maps `<iframe>` no API key, `<dialog>` modal, image gallery (dialog + picture), HTML5 form validation. Comparison table vs plugin alternatives. Insights sources: weekly distillation 2026-05-07 #041, -042.
- **`workflows/litespeed-cache-mgmt.md`** — LiteSpeed Cache management for REST API + MCP automation. 5 traps documented: (1) WP-Abilities REST stale-read after write — fix via `rest_post_dispatch` filter + `litespeed_control_set_nocache`; (2) page cache auto-invalidates via `save_post`, not manual purge; (3) Cache-Control vs Lighthouse cache policy audit; (4) `X-LiteSpeed-Cache: hit/miss` indicators; (5) PHP-FPM exhaustion under concurrency. Diagnostic flowchart included. Insights source: weekly distillation 2026-05-13 #075.
- **`workflows/bulk-content-automation.md`** — Idempotent bulk content modification via WP REST. 4-stage workflow (Plan → Dry-run → Execute with marker → Verify). Marker class pattern (`{project}-{purpose}-{slug}`) provides idempotency, audit, rollback, recovery. Real-world test: 69 cluster posts, 0 duplicates, 0 failures. Reusable patterns: pillar up-links, author bylines, trust callouts, affiliate disclosures, review notices. Insights source: weekly distillation 2026-05-13 #081.

### Changed — 6 new sections in existing files

- **`references/deployment.md`** — added "Plugin zip build cross-platform — forward-slash separator MUST" section (insight #089: Compress-Archive default backslash separator silent-fails on Linux WordPress host; fix via `.NET ZipArchive` explicit `/`) + "Windows + Git Bash path quirks — MSYS_NO_PATHCONV" section (insight #63: bash subshell path mangling when crafting URLs/JSON with `/` literals).
- **`references/pitfalls.md`** — added "CSS specificity battles — `body.page-id-X` selector wins over plain `body`" section (insight #39). Specificity ladder table + 4 fix strategies (edit at source, match exact chain, scope-NOT, inline style). Documents why mu-plugin page-id rules override page-builder custom CSS.
- **`workflows/design-system-rollout.md`** — added Phase 5 "Visual rhythm standardization (custom-injected sections)" (insight #44: unified h2 typography with gold underline + Oswald font for injected HTML sections matching brand sec-head) + Phase 6 "Canonical IA reorder via JS `insertBefore`" (insight #43: 2-stage inject-then-reorder pattern for funnel-canonical section order across custom sections).
- **`workflows/content-reference.md`** — added "Brand fact audit cross-page when update" section (insight #18). 5-step audit workflow when updating shared brand facts (year, team size, address, etc.) to catch drift across counter widgets, headings, schema, meta tags, footer. Real-world example: the audited site founding year drift. Cadence: 3-month audit.

### Changed — `SKILL.md` routing matrix +5 rows

New task routes added to the "When to load which reference" table:
- Rank Math automation → `references/rankmath.md`
- JSON-LD / Schema.org → `references/schema-jsonld.md`
- Native HTML patterns → `references/native-html-patterns.md`
- LiteSpeed cache stale-read → `workflows/litespeed-cache-mgmt.md`
- Bulk content automation → `workflows/bulk-content-automation.md`

### Insights promoted (status CANDIDATE → PROMOTED-TO-SKILL)

Insights: #074, #075, #077, #079, #080, #081, #089. Project-B insights: #39, #41, #42, #43, #44, #63. Project-A insights: #18.

### Sources — patterns extracted from

- **inherited B2B site week** — 5 sessions across 2026-05-10 → 2026-05-13: SEO automation 85 posts, Rank Math wrapper plugin v2.0.0 → v2.0.5 (mcp.public flag fix in evening session), JSON-LD LocalBusiness + Physician schema rollout, cluster pillar wiring 69 posts.
- **project-B week** — 2026-05-07 (CRO injection, mobile menu, layout standard), 2026-05-09 (image opt + accessibility sprint), 2026-05-13 (MCP architecture diagnostics).
- **project-A week** — 2026-05-10 session: Elementor MCP gotchas, Fluent Forms styling, brand fact drift audit, content_reference.md pattern crystallized.

[0.7.0]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.7.0

## [0.6.0] — 2026-05-13

Round 8 weekly insights distillation — 7 patterns from one production session (Rank Math MCP wrapper plugin build + SEO automation across 86 posts). One major new workflow: end-to-end recipe for wrapping any existing WP plugin's REST routes into MCP-discoverable abilities.

### Added — 1 new workflow

- **`workflows/build-mcp-wrapper-plugin.md`** — end-to-end recipe for wrapping a WP plugin's REST routes into MCP abilities so AI agents can discover and call them. Bundles 5 framework gotchas: REST routes vs WP-Abilities Framework distinction, canonical hook `wp_abilities_api_init`, `ArrayObject` (not `stdClass`) for empty `input_schema.properties`, `meta.show_in_rest: true` required for REST visibility, GET/POST/dummy-input call-pattern matrix. Real proof: one wrapper plugin, 4 REST routes → 16 abilities, v1.0.0 → v2.0.2 over 12 iterations in 5 hours.

### Changed

- **`references/wp-abilities.md`** — added "Building a wrapper plugin (REST routes → abilities) — 4 gotchas" section as lookup-friendly reference companion to the new workflow. Each gotcha (canonical hook, ArrayObject empty schema, show_in_rest visibility, call-pattern matrix) documented with wrong / right code + symptoms + cross-reference to the workflow.
- **`workflows/seo-audit.md`** — added "Focus-keyword automation — tags beat title-slice heuristic" section. Documents the 19.9 → 64.6 avg Rank Math SEO score lift (86 posts, +225%) when switching from title-slice keyword inference to tag-based scoring. Includes Python scoring algorithm (tag-in-title +10, tag-in-slug +5, word-overlap ×3, 2-4 words +2, cluster authority +N, penalize sentence-style or no-diacritics), bulk-set via Rank Math `updateMeta` REST, verification pattern.
- **`references/pitfalls.md`** — added "CloudLinux LVE + Elementor Pro `posts` widget concurrent renders trigger HTTP 500" pitfall. Root cause: per-account memory/I/O quota on shared hosting + heavy widget (DB query + 9 thumbnails) × multiple pillar pages × concurrent crawl. Workaround: pre-built static HTML list in `text-editor` widget. Trade-off matrix + when-safe-when-not.
- **`SKILL.md`** — 1 new row in task → files-to-load matrix.
- **`README.md`** — 17 workflows (was 16).

### Sources — patterns extracted from

- **Inherited B2B site Rank Math automation session** — 5 hours building `rankmath-mcp` wrapper plugin (v1.0.0 → v2.0.2, 12 install cycles); bulk-setting focus keywords across 86 posts with V1 (title slice) → V2 (tag scoring) iteration; 3 pillar pages broken by `posts` widget cocktail on CloudLinux LVE.

[0.6.0]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.6.0

## [0.5.1] — 2026-05-11

### Fixed

- **CI lychee exclusion** — v0.5.0 attempted to exclude `templates/content-reference-template.md` via `--exclude-path`, but lychee continued to flag 3 `file://` errors in that file. Switched to `--exclude '^file://'` which matches the URL pattern lychee actually emits for relative paths that resolve outside the repo. CI now green.
- Comment in `.github/workflows/ci.yml` rewritten to explain the design rationale (templates are deployed into user projects; paths like `../../.claude/skills/...` are intentional and resolve at deployment time, not CI time).

[0.5.1]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.5.1

## [0.5.0] — 2026-05-11

Round 7 weekly insights distillation — 4 patterns promoted, all from one production debugging session on an inherited B2B site (iOS Safari mobile menu state-desync). One major mental-model addition: multi-factor "cocktail" bug debugging methodology, captured for sites where the bug exists only on one specific stack combination.

### Added — 1 new reference + 1 new workflow

- **`references/astra-mobile-menu.md`** — complete Astra mobile menu debug reference bundling 3 related insights:
  - Mode comparison (`dropdown` / `off-canvas` / `fullscreen`) — different DOM, body class names, JS click handlers per mode. Cross-mode fixes silently fail. Detection step + comparison table.
  - iOS Safari bfcache state-desync bug — `pageshow` handler fix + reason it doesn't reproduce on every Astra site.
  - 6-layer defense architecture (production-tested v5): CSS transitions + stagger animation + JS class manager + capture-phase fallback + `pageshow` reset + width-reload safety net.
  - Iterative debug journey v1 → v5 with lessons per version.
  - Cross-references to `astra-customizer.md` (MCP coverage gaps), `elementor-mcp.md` (Custom Code Snippets for deployment), `workflows/multi-factor-bug-debug.md` (methodology).

- **`workflows/multi-factor-bug-debug.md`** — methodology for "bug only on one site" cases. Inverts the standard single-root-cause assumption. 6-step process: confirm site-specific → enumerate stack factors → identify candidate factor set → state-desync mental model → iterative add-layer fix → document the cocktail for future. Includes the v1 → v5 iteration log from the Astra mobile menu case as a concrete example.

### Changed

- **`references/elementor-mcp.md`** — added "Elementor Pro Custom Code Snippets" section. Documents the built-in CPT `elementor_snippet` for site-wide JS / CSS / HTML injection — no separate plugin, no `functions.php` edit, no mu-plugin file. 4 location hooks (`<head>`, `Body - Start`, `Body - End`, `wp_footer`), priority + frequency settings, MCP + REST tool reference, decision matrix vs Code Snippets plugin / kit `custom_css` / mu-plugin.

- **`references/astra-customizer.md`** — added "Astra MCP coverage gaps" section. Documents that settings stored in `theme_mod('astra-settings')` serialized array (e.g. `mobile-menu-style`, body typography overrides) are NOT exposed via Astra MCP tools. 4 workaround paths (wp-admin Customizer manual, PHP `set_theme_mod` snippet, WP-CLI, custom REST endpoint plugin). PHP recipe included.

- **`SKILL.md`** — 2 new rows in task → files-to-load matrix.
- **`README.md`** — 18 references (was 17) + 16 workflows (was 15).

### Fixed — CI link-check was failing since v0.2.0

The `Markdown link check` job (lychee) has been failing on every release since v0.2.0. 6 errors traced + fixed in this release:

- **Orphan `## [0.2.2]` section in CHANGELOG.md** — referenced a release that was never tagged → 404 on the link. Section removed (its content was already incorporated into v0.3.0+).
- **`fontawesome.com/v5/free`** in `pitfalls.md` → 404. FontAwesome restructured to `fontawesome.com/search?o=r&m=free`. Link updated.
- **`linkedin.com`** in `README.md` Author section → HTTP 999 (LinkedIn returns 999 to all bots / link checkers — universal anti-scrape). Added to lychee `--exclude` list.
- **3 relative paths in `templates/content-reference-template.md`** — paths like `../../.claude/skills/wp-stack/...` are intended to resolve from the user's project location, not from the skill repo root. Added `--exclude-path` for the template file (templates are deployment-time, not link-check-time).

CI workflow `.github/workflows/ci.yml` updated with documented `--exclude` rules so future contributors know which exclusions are intentional.

### Sources — patterns extracted from

- **Inherited B2B site mobile-menu debug session** — 5 hours iOS Safari debug, v1 → v5 iteration, 5-factor stack cocktail identification, Elementor Custom Code Snippet deployment, Astra Customizer manual mode switch.

[0.5.0]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.5.0

## [0.4.0] — 2026-05-11

Round 6 weekly insights distillation — 13 patterns promoted, including 2 corrections to v0.1.0/v0.2.0 entries that were incomplete or wrong about the root cause.

### Added — 2 new workflow files

- **`workflows/design-system-rollout.md`** — bundles 5 related patterns into one workflow: 3-layer architecture (Astra → Elementor kit → custom CSS), 9-step apply sequence, cascade-priority rules (Astra → Elementor → widget), widget-hardcode audit via JSON-walk, bulk batch-update fix, plus Element Pack subscriber-filter sweep step. Real timing: ~130 min for an inherited site, ~55 min for greenfield.
- **`workflows/comprehensive-audit.md`** — 8-dimension site audit using only curl + regex + Python stdlib (no Lighthouse / PSI API quota needed). Covers SEO, performance, security, plugin usage, schema, accessibility (static), DB/robots, redirects. Output JSON ships to `data:build-dashboard` for interactive HTML.

### Changed

- **`references/wp-abilities.md`** — appended 2 sections:
  - Extract auth credentials from `~/.claude.json` MCP server config (base64-decode `Authorization: Basic` for direct REST) + security caveats (`chmod 600`, no public sync, rotation discipline)
  - WP REST endpoint paths use plural `rest_base` (not singular `post_type`), discoverable via `/wp/v2/types`. Hyphen-vs-underscore gotcha for vendor CPTs (e.g. `rank-math-locations` REST vs `rank_math_locations` post_type)
- **`references/seo-checklist.md`** — appended 2 sections:
  - Robots.txt physical file at docroot overrides WP virtual + Rank Math hooks (Apache serves before PHP). Detection signals (last-modified header, accept-ranges) + 3 fix paths + audit checklist
  - Schema graph `@id` linking best practice — link entities via `@id` URL fragments instead of duplicating Organization data on every page. Naming convention + pattern across CollectionPage / Service / Person / WebPage
- **`references/pitfalls.md`** — appended 4 new pitfalls + 1 correction:
  - Rank Math `updateSchemas` REST silent fail (HTTP 200, body=`[]`, schema NOT saved). Same family as `updateRedirection`. Workaround: HTML widget JSON-LD injection
  - LiteSpeed CCSS staleness — 10 REST endpoints tried, all return 200 silent. CCSS frozen with old plugin CSS even after deactivation. 4 workarounds (wp-admin GUI, manual file delete, mass page edit, disable/re-enable feature)
  - Astra `font_weight` clamped ≤ 700 (silently ignores 800/900). 3 workarounds
  - **Element Pack Pro `display_condition_list: subscriber` site-wide infection at scale** (extends v0.3.0 entry) — real observation 88+63=151 widgets across 2 pages. Bulk-fix via `batch_update`
  - **CORRECTION**: v0.1.0 entry "Elementor V4 doesn't always add `_css_classes` to DOM" was wrong root cause. Real cause: widget uses `_css_classes` (WITH underscore); container uses `css_classes` (NO underscore). Wrong field name = silent save-no-render. Original `.elementor-element-{ID}` selector workaround remains valid as a fallback
- **`references/elementor-mcp.md`** — added "`css_classes` field name — different for widget vs container" entry cross-referencing the corrected pitfall
- **`references/stack.md`** — added "Elementor classic widgets vs atomic widgets — decision matrix" entry. Default recommendation: classic. Use atomic only on greenfield, pure-Elementor sites
- **`workflows/multilingual-polylang.md`** — **MAJOR REVISION**: replaced the "Polylang Free + Rank Math sitemap incomplete — custom `/sitemap-en.xml` workaround" section with the 2 REAL root causes discovered: (1) `rank_math_canonical_url` mismatch with `get_permalink()` due to Polylang timing — must defer canonical to a Pass 2 after `pll_set_post_language` + `flush_rewrite_rules`. (2) Hidden disk cache at `/uploads/rank-math/*.xml` — 3rd cache layer most invalidation scripts miss. Custom mu-plugin sitemap demoted to "defensive backup, optional"
- **`SKILL.md`** — 2 new rows in task → files-to-load matrix
- **`README.md`** — 15 workflows (was 13), refreshed structure

### Sources — patterns extracted from

- **Inherited B2B site** (Astra Pro + Elementor 4.x + 24 plugins) — brand application 9-step sequence, cascade priority discovery, widget hardcode audit pattern, Element Pack subscriber-filter site-wide infection, LiteSpeed CCSS REST silent fail, Rank Math `updateSchemas` silent fail, robots.txt physical override, comprehensive 8-dim audit framework, REST plural rest_base, auth from `~/.claude.json`, Schema `@id` graph linking, Astra font_weight clamp, `_css_classes` vs `css_classes` field correction, atomic widgets decision matrix
- **B2B logistics site** (Polylang Free + Rank Math) — Rank Math sitemap deep-debug uncovering 2 real root causes (canonical timing + disk cache)

[0.4.0]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.4.0

## [0.3.0] — 2026-05-10

Round 5 weekly insights distillation — 4 production sites this week (B2B logistics, food retail, event SFX, inherited B2B clinic), ~6,100 lines of `insights.md` parsed, 22 patterns promoted.

### Added — 4 new reference files

- **`references/image-optim-recipes.md`** — Pillow PNG quantize (`FASTOCTREE` for RGBA, `MEDIANCUT` for RGB), JPEG q82 progressive re-encode, srcset variant strategy, "don't blanket-optimize the uploads tree" anti-pattern.
- **`references/a11y-debugging.md`** — Lighthouse / axe-core fix recipes: blended-rgba contrast math, `aria-level` attr workaround for heading-order, Elementor accordion `aria-selected` invalid attr fix, CF7 honeypot a11y pattern, mu-plugin scaffold for bulk-fix.
- **`references/fluent-forms.md`** — high-specificity selector pattern to beat `--fluentform-primary` inline variable, input/checkbox styling, install + email setup, Free vs Pro decision matrix, Phone-field workaround on Free.

### Added — 4 new workflow files

- **`workflows/lighthouse-driven-optim.md`** — anchor-URL gotcha (`#section` inflates measured page weight), `total-byte-weight` audit as the priority list, sample multi-round optimization log.
- **`workflows/redesign-page.md`** — 5-state Phase 2 marking system (KEEP / MOVE / ENHANCE / REPLACE / REMOVE) with phase 1 audit + phase 3 execution discipline. Counter to "remove old container, build fresh" content-loss anti-pattern.
- **`workflows/ui-verification.md`** — verify-don't-assume checklist (screenshot live URL, measure pixels, test 3 viewports, inspect computed styles), counter to anchoring + confirmation bias. Includes flex-centering gotchas and "AI cannot trust its own visual reasoning" meta-pattern.
- **`workflows/content-reference.md`** — single-source-of-truth pattern for brand facts. Plus `templates/content-reference-template.md` — starter file with 11 sections (brand identity, service catalog, portfolio DB, equipment, partners, blog catalog, tone, trust signals, SEO meta, conflicts table, usage guide).

### Changed

- **`references/pitfalls.md`** — appended 7 new pitfalls (~270 lines):
  - Rank Math `updateRedirection` REST silent fail (returns 200, rule never kicks in via App Pw context)
  - Astra `site-post-title=disabled` per-post toggle for blog H1 duplicate
  - **CRITICAL**: Element Pack Pro legacy `display_condition_list: subscriber` halts container rendering for non-logged-in users
  - Elementor 4.0 `update-page-settings custom_css` saves but does not load on frontend (HTML-widget `<style>` workaround)
  - Pro FontAwesome icons render empty on Free Elementor (free-alternative table)
  - Fluent Forms shortcode renders empty if form has 0 fields
  - LiteSpeed lazy-load rewrites `src=""` runtime — Lighthouse "missing src" red herring
- **`references/elementor-mcp.md`** — appended 2 entries:
  - `add-price-table currency_format: ","` required for non-decimal-thousands locales (VND, IDR, big integers)
  - `show_ribbon: ""` (empty string) required to clear ribbon on cloned price-table cards
- **`references/seo-checklist.md`** — 3 new sections:
  - Rank Math `updateMeta` silent fail when meta key is non-Rank-Math-managed (returns `{slug:true}` but meta unchanged)
  - 1 FAQPage per page principle (multiple `faq_schema=yes` widgets emit invalid Schema)
  - Eyebrow first-text trap — Rank Math meta-description fallback hijack
- **`references/wp-abilities.md`** — CPT not exposing REST → `/wp/v2/search?subtype=...` workaround pattern
- **`references/performance.md`** — LiteSpeed default 7-day TTL fails Lighthouse `uses-long-cache-ttl`; `.htaccess` long-TTL recipe (`max-age=31536000, immutable`)
- **`references/deployment.md`** — cPanel Fileman/upload_files: `overwrite=1` flag + 5–9 file batch limit per multipart request
- **`SKILL.md`** — 6 new rows in the task → files-to-load matrix
- **`README.md`** — refreshed structure: 17 references + 13 workflows + 3 PHP recipes + 2 templates

### Sources — patterns extracted from

- B2B logistics site — image optimization sprint (PNG quantize, srcset variants, LiteSpeed long-TTL)
- Food retail e-commerce site — accessibility sprint (blended rgba, axe-core aria-level, accordion aria-selected, CF7 honeypot)
- Event SFX premium B2B site — Fluent Forms styling, UI verification anti-pattern (anchoring + confirmation bias), content_reference.md SoT pattern
- Inherited B2B site — Element Pack Pro legacy filter, Rank Math updateMeta vs updateRedirection, FAQ Schema consolidation, eyebrow Rank Math hijack, CPT REST workaround, 5-state redesign marking system

[0.3.0]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.3.0

## [0.2.1] — 2026-05-10

### Fixed

- **Brand-leak scrub** across v0.2.0 files (`CHANGELOG.md`, `references/mcp-architecture.md`, `references/pitfalls.md`, `workflows/claude-mcp-connector-setup.md`, `workflows/seo-audit.md`, `workflows/session-distillation.md`) — replaced real domain names, brand acronyms, customer-identifying tech combos, and one v0.1.0-carryover maintainer first name with neutral placeholders (`example.com` / `acme-*` / `Site A` / `Site B`). Pure documentation scrub, no logic changes. Author section in `README.md` kept intentionally (standard OSS author credit).

### Added

- **`workflows/session-distillation.md` — quality bar criterion #5 "Brand-neutral"**: a distilled insight must be expressible without revealing real domain / brand acronym / customer name / identifying stack combo. Re-write with `example.com` / `acme-*` / `Site A`/`B` placeholders if the real-world origin is sensitive. Required for public-skill governance.
- **`workflows/session-distillation.md` — step 5b "Pre-publish brand-leak scan"**: 4 grep patterns to run before every `git push` (real domain, brand acronym, connector slug, maintainer first name) + recovery procedure when a leak ships. Catches the leak class that shipped in v0.2.0 before it ships again.

[0.2.1]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.2.1

## [0.2.0] — 2026-05-10

### Added

- **`references/mcp-architecture.md`** — Multi-plugin MCP endpoint architecture. Each MCP plugin registers its own endpoint (`/mcp/<plugin>-server`); they do NOT share a namespace. The WP Abilities Framework is a central registry, NOT a transport. Diagnosis matrix for the 4 common 404 patterns + endpoint mappings observed for mcp-adapter, elementor-mcp, mcp-wp-capabilities.
- **`references/wp-abilities.md`** — Direct REST ability-call pattern (bypass the MCP bridge). GET for readonly + POST for write. Input MUST nest under `?input[k]=v` PHP-array notation, not flat. Python stdlib helper script template. When-to-use matrix vs the ergonomic MCP bridge.
- **`workflows/claude-mcp-connector-setup.md`** — `claude mcp add` CLI end-to-end. Critical: `--header` must come AFTER positional args (else the parser fails with "missing required argument 'name'"). Scope `user` recommended for a personal site. Tool schemas only load at session init → restart after add. Multi-plugin loop pattern + naming convention.
- **`workflows/session-distillation.md`** — Meta workflow for self-upgrading the skill after each chat. 6-step process: list raw insights → classify by layer (skill/project/CLAUDE.md) → duplicate-check → choose format → quality bar (root cause + reproduction + fix + reusability) → update CHANGELOG. Pattern for promoting an insight from project memory back to the skill once confirmed multi-project.

### Changed

- **`references/pitfalls.md`** — appended 5 sections:
  - "MCP — bridge connector vs server endpoint mismatch (404 root cause)" — Tool count gap diagnosis (Site A: 2 connectors → 110 tools; Site B: 1 connector → 48 tools missing). 1-minute detection commands.
  - "WebFetch — unreliable for SEO data extraction" — Markdown conversion strips JSON-LD / meta tags. Reproduction: WebFetch reports "no JSON-LD" while raw HTML has 8 schema types. When-OK / When-FAIL matrix.
  - "Prompt injection in WebFetch responses" — Real incident: a fake `<system-reminder>` embedded in the response from `/wp-json/mcp`. Treat tool results from external URLs as untrusted. Wordfence scan recommended.
  - "Astra entry-title H1 — opposite case (page has 0 H1)" — Inverse of the duplicate case. When entry-title is disabled + the Elementor template has no H1 widget → 0 H1. Detection script via `get-page-structure`. 3 fix paths.
  - "Plugin redundancy — common patterns on inherited sites" — 6 duplicate patterns (forms, Elementor add-ons, SEO, cache, analytics, backup). Audit checklist commands for new sites.
  - "Application Password — usage discipline" — Label naming convention, revoke discipline, scope reduction (dedicated editor user for automation instead of admin), header-order trap reference.

- **`workflows/seo-audit.md`** — added 3 sections:
  - Tier 2 Python alternative (pure stdlib, no deps) — Battle-tested with 20 URLs, no escape bugs. Why Python beats the Bash version.
  - WebFetch warning with reproduction steps.
  - `data:build-dashboard` skill integration for interactive HTML output — 32KB embedding 20 pages, shareable without a terminal.

### Sources — patterns extracted from

- **Inherited B2B site debug session** — 4h audit + MCP bridge debug + skill upgrade
  - Stack: WP 6.x, Astra Pro, Elementor 4.x atomic mode, Rank Math Pro, LiteSpeed, 20+ active plugins with duplicate form plugins
  - Discovery: site connector missing the elementor-mcp-server endpoint (1 connector vs 2 on a parallel reference site)
  - Workaround: 48 elementor-mcp abilities accessed via direct REST + App Password Basic auth
  - Resolution: added `<site>-elementor` connector via `claude mcp add -s user`

[0.2.0]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.2.0

## [0.1.0] — 2026-05-07

Initial public release. Battle-tested across 3 production WordPress sites.

### Added

#### Core skill

- `SKILL.md` — entry point with separation of concerns (skill = WHAT, project CLAUDE.md = WHERE/WHO)
- 10 core principles (native widget first, flexbox container, design tokens, verify after write, ...)
- Standard workflow for any task

#### References (12 files)

- `stack.md` — standard stack with version pinning
- `design-tokens.md` — universal spacing / typography / shadow scale + B2B header sizing
- `elementor-mcp.md` — MCP cheatsheet, widget schema gotchas, file format conventions, settings that need post-CSS regen, container & structure quirks, Abilities API input wrapper format, `update_page_from_file` no `post_content` regen
- `widget-mapping.md` — HTML element → Elementor widget mapping, link storage location per widget, refactor strategy preserving class names
- `responsive.md` — 3 breakpoint rules, custom CSS scoping per breakpoint, container budget for nav decorations, scroll height optimization ROI, iframe-based responsive testing
- `astra-customizer.md` — Astra settings, Astra Free + Elementor Theme Builder bridge mu-plugin, Astra Pro vs Elementor Pro feature overlap matrix
- `seo-checklist.md` — Rank Math setup, Schema 3 types pattern (BreadcrumbList + Service + FAQPage), bulk Rank Math meta via post_meta, Schema injection via PHP, OfferCatalog for grouped services, OG image attachment integration
- `performance.md` — speed optimization, cache invalidation playbook by host, LiteSpeed 2 invalidation paths (auto-purge per save_post vs manual purge API broken)
- `security.md` — hardening checklist, fail2ban override, iptables flush risk, mu-plugin API check before deploy
- `deployment.md` — deploy workflow generic, shared-host WAF behavior, addon domain docroot verification, Fileman API limitations, REST response capture safety (Vietnamese UTF-8), WP REST hidden useful endpoints
- `vietnamese.md` — Vietnamese locale concerns (collation, fonts, slugs, schema)
- `pitfalls.md` — 30+ pitfalls organized into clusters:
  - **CRITICAL**: `_elementor_edit_mode` empty → wpautop, `page_for_posts` overrides render, Pro Form `custom_id` missing, kit `_elementor_page_settings` storage format trap
  - Elementor V4 layout / CSS pitfalls
  - CSS cascade / specificity pitfalls
  - MCP write safety
  - PHP-FPM worker exhaustion
  - PHP bulk-update pitfalls
  - WP nav menu pitfalls
  - Astra theme pitfalls

#### Workflows (8 files)

- `new-site-setup.md` — Day 1 → Launch Day end-to-end, language pack install, mcp-adapter release zip vs trunk, decision tree for `elementor_canvas` template header/footer
- `add-cpt.md` — create a Custom Post Type via ACF / JetEngine / code
- `theme-builder-loop.md` — Loop Item + Archive + Single template, `set-template-conditions` cache regen, verify-iterate-fix cycle
- `migrate-staging-prod.md` — WP Migrate Pro / Duplicator / manual SSH / provider-specific
- **`clone-transform-pattern.md`** (NEW) — bulk-build N similar pages via PHP transform script. Time saved: ~73–95% after pattern stable. Includes generic builder helpers alternative for differing structure, walk-replace HTML widget trap, cross-page internal linking ("Add NEW DOM > regex existing DOM" lesson)
- **`og-image-generation.md`** (NEW) — 4-tier OG image coverage strategy ($0.175 for 52 pages proven). Style A (PHP GD) vs Style B (AI photo + overlay) decision matrix, Replicate API integration, Schnell vs Pro decision matrix, parallel call rate limits
- **`seo-audit.md`** (NEW) — 3-tier audit pattern: PHP backend dump + Bash frontend curl + Python analyze. Per-page deep audit, false positive types, HTTP code verify all internal links, audit script design lessons
- **`smtp-relay-setup.md`** (NEW) — Brevo SMTP relay end-to-end: port verify, DNS records, WP Mail SMTP plugin install via PHP, `submit_actions` order, `email_to` phasing, health check cron, "don't run own mail server" lesson

#### Templates

- `project-claude-md-template.md` — template for project `CLAUDE.md`
- **`snippets/elementor-data-update.php`** (NEW) — safe PHP recipe for `_elementor_data` updates: `wp_slash` + JSON walk recursive (Vietnamese `\uXXXX`-safe) + minimum 7 meta for `wp_insert_post`, hash anchor absolutize, slug clash guard
- **`snippets/wp-fix.php`** (NEW) — token-guarded recovery script for site 500 (parses `wp-config.php` with regex, mysqli direct ops on options table). **Self-stub after use. READ THE WARNINGS.**
- **`snippets/og-image-generator.php`** (NEW) — PHP GD OG image generator + WP attachment integration with Rank Math (Tier 1/2/3 helpers)

### Sources

Patterns extracted from 3 production WordPress sites debugging across 2026-05-02 to 2026-05-07:
- B2B logistics site (8 country pillars + 26 subpages + 5 blog posts)
- Food retail e-commerce site
- Event SFX premium B2B site

Total: 3,730+ lines of production-tested knowledge across 24 unique patterns.

[0.1.0]: https://github.com/tranminhmanh/wp-stack-skill/releases/tag/v0.1.0
