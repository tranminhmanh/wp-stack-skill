# wp-stack-skill

> **Claude Code skill** for building, fixing, optimizing, and debugging WordPress sites with the standard stack: **Astra Free + Elementor Pro + ACF + msrbuilds/elementor-mcp**.

Battle-tested across 3 production WordPress sites. 24+ universal patterns, 4 end-to-end workflows, 3 PHP recipes — all extracted from real production debugging sessions, not theory.

---

## Why this skill

Building a WordPress site with Claude Code goes from "AI tries random things" → "AI follows conventions" the moment a skill is loaded. This skill encodes:

- **What works** — stack choices that play well together
- **What breaks** — pitfalls discovered the hard way (with fixes)
- **How to scale** — clone-transform pattern for bulk-building 8 similar pillar pages in 30 minutes instead of 6 hours
- **How to verify** — every write op has a paired verify command, because MCP returning `true` does not mean the page renders

Sample patterns inside:

- ⚠️ **CRITICAL bugs**: `_elementor_edit_mode` empty → wpautop strips classes; Elementor kit `_elementor_page_settings` PHP-serialized vs JSON; Pro Form `add-form` does not set `custom_id` (silent fail for weeks); `page_for_posts` overrides Elementor render
- 🎨 **CSS / V4 layout traps**: applying CSS grid to `<section>` (squeeze), `width` setting persisting across container_type change, `_css_classes` MCP unreliable → target by element ID
- 🚀 **Bulk-build workflows**: clone + transform PHP pattern (95% time saved), 4-tier OG image coverage strategy ($0.175 for 52 pages), 3-tier SEO audit (PHP backend + Bash frontend + Python analyze)
- 📨 **End-to-end recipes**: SMTP relay via Brevo on budget VPS where port 25 is blocked, SEO Schema 3 types (BreadcrumbList + Service + FAQPage), Astra Free + Elementor Theme Builder bridge mu-plugin

---

## Quick start

### Install

```bash
mkdir -p ~/.claude/skills
git clone https://github.com/tranminhmanh/wp-stack-skill.git ~/.claude/skills/wp-stack
```

Verify Claude Code loads it:

```
claude
> /skills list
```

You should see `wp-stack` in the list.

### Use

Open Claude Code in a WordPress project directory, then trigger naturally:

```
> Build a landing page hero with 3 CTAs using wp-stack.
> Migrate this site from staging to production.
> Run an SEO audit on all 52 pages and report critical issues.
> Bulk-create 8 country pillar pages from this template.
> Why is my Pro Form silently failing?
```

The skill auto-loads the relevant reference files based on the task.

---

## What's inside

```
wp-stack/
├── SKILL.md                          ← Entry point (Claude reads first)
├── README.md                         ← This file
│
├── references/                       ← Knowledge base (12 files)
│   ├── stack.md                      ← Standard stack and versions
│   ├── design-tokens.md              ← Spacing / typography / shadows
│   ├── elementor-mcp.md              ← MCP cheatsheet + widget gotchas
│   ├── widget-mapping.md             ← HTML element → Elementor widget
│   ├── responsive.md                 ← Breakpoint rules + container budgets
│   ├── astra-customizer.md           ← Astra theme settings
│   ├── seo-checklist.md              ← Rank Math + Schema + OG image
│   ├── performance.md                ← Speed optimization + cache invalidation
│   ├── security.md                   ← Hardening checklist
│   ├── deployment.md                 ← Deploy workflow generic
│   ├── vietnamese.md                 ← Vietnamese-locale concerns (fonts, slugs, schema)
│   └── pitfalls.md                   ← 30+ pitfalls with detection + fix
│
├── workflows/                        ← Step-by-step procedures (8 files)
│   ├── new-site-setup.md             ← Set up a new site A → Z
│   ├── add-cpt.md                    ← Create a Custom Post Type
│   ├── theme-builder-loop.md         ← Build a Loop template
│   ├── migrate-staging-prod.md       ← Migrate staging → production
│   ├── clone-transform-pattern.md    ← Bulk-build N similar pages via PHP transform
│   ├── og-image-generation.md        ← 4-tier OG image coverage strategy
│   ├── seo-audit.md                  ← 3-tier SEO audit (PHP + Bash + Python)
│   └── smtp-relay-setup.md           ← Brevo SMTP relay for budget VPS
│
└── templates/                        ← Reusable assets
    ├── project-claude-md-template.md ← Template for project CLAUDE.md
    └── snippets/                     ← PHP recipes
        ├── elementor-data-update.php ← Safe _elementor_data update (Vietnamese-safe)
        ├── wp-fix.php                ← Token-guarded recovery script (read warnings!)
        └── og-image-generator.php    ← PHP GD OG image generator + WP attachment integration
```

---

## Stack supported

| Component | Tool | Version | Note |
|---|---|---|---|
| WordPress core | WordPress | 6.8+ | Auto-update minor versions |
| Theme | Astra | Latest free | NOT Pro |
| Page builder | Elementor | 3.20+ | Flexbox Containers ON |
| Page builder Pro | Elementor Pro | 3.20+ | License required |
| MCP server | msrbuilds/elementor-mcp | v1.4+ | GitHub release |
| Custom fields | ACF Free | Latest | JetEngine when relationships needed |
| SEO | Rank Math Free | Latest | NOT Yoast |
| Cache | WP Rocket / LiteSpeed | Latest | Pick one |
| Security | Wordfence Free | Latest | + 2FA admin |
| Backup | UpdraftPlus | Latest | + provider snapshots |
| Email | WP Mail SMTP | Latest | + Brevo / SendGrid / Mailgun |

Full list with rationale: [`references/stack.md`](references/stack.md).

---

## When NOT to use this skill

- You are using a different page builder (Divi, WPBakery, Bricks, Beaver Builder)
- You are using a different theme (Hello, GeneratePress, OceanWP, Kadence)
- Your site is mostly Gutenberg blocks — this skill assumes Elementor as the primary builder
- You need WooCommerce-specific guidance (covered minimally; consider a dedicated WooCommerce skill)

---

## How to file project-specific info

Every project keeps its own `CLAUDE.md` with brand / host / path / DB info. Template:

```bash
cp ~/.claude/skills/wp-stack/templates/project-claude-md-template.md \
   ~/projects/<project-name>/CLAUDE.md
# Then edit and fill in.
```

The skill **reads** project `CLAUDE.md` for brand and hosting, then applies universal patterns. It never hardcodes project-specific info inside itself.

---

## Versioning

This project follows [Semantic Versioning](https://semver.org/). Current version: see [`CHANGELOG.md`](CHANGELOG.md).

- **Major bump**: skill structure changes that break existing project `CLAUDE.md` references
- **Minor bump**: new workflows, references, or major pattern additions
- **Patch bump**: corrections, clarifications, single-pattern additions

---

## Contributing

Contributions welcome — especially new patterns from production debugging. See [`CONTRIBUTING.md`](CONTRIBUTING.md) for the workflow.

If you find a security issue (especially in the `wp-fix.php` recovery template), please follow [`SECURITY.md`](SECURITY.md) instead of opening a public issue.

---

## License

MIT — see [`LICENSE`](LICENSE).

---

## Disclaimer

WordPress, Elementor, Astra, ACF, Rank Math, WP Rocket, LiteSpeed, Cloudflare, Wordfence, and other product names are trademarks of their respective owners. This project is not affiliated with, endorsed by, or sponsored by any of these projects. All patterns are extracted from public documentation and our own production debugging.

---

## Acknowledgments

Patterns extracted from production debugging on 3 WordPress sites (B2B logistics, food retail, event SFX). Special thanks to the upstream projects whose documentation and source code informed many of these patterns:

- [Elementor](https://github.com/elementor/elementor)
- [Astra Theme](https://wpastra.com/)
- [Advanced Custom Fields](https://www.advancedcustomfields.com/)
- [msrbuilds/elementor-mcp](https://github.com/msrbuilds/elementor-mcp)
- [Rank Math](https://rankmath.com/)
- [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/)
