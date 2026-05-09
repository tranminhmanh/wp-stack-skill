# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
