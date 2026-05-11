# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
