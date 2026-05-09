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

## Author

<a href="https://github.com/tranminhmanh">
  <img src="https://github.com/tranminhmanh.png?size=160" align="right" width="160" alt="Trần Minh Mạnh">
</a>

**Trần Minh Mạnh** — independent WordPress + Elementor developer based in Vietnam, focused on B2B sites where the form actually has to convert.

- Website: [tranminhmanh.id.vn](https://tranminhmanh.id.vn/)
- LinkedIn: [linkedin.com/in/tranminhmanh](https://www.linkedin.com/in/tranminhmanh/)
- GitHub: [@tranminhmanh](https://github.com/tranminhmanh)
- Email: [tranminhmanh.official@gmail.com](mailto:tranminhmanh.official@gmail.com)

The patterns in this skill come from production debugging on three sites I built and maintain over the past three months:

- **B2B logistics** — 8 country pillar pages + 26 port-pair subpages + 5 long-tail blog posts. Source of the 9-week silent Pro Form `custom_id` bug, the slug-mismatch dead-link cleanup pattern, and most of the bulk-build clone-transform work.
- **Regional food brand** — Astra Free + Elementor `elementor_canvas` template on shared hosting. Source of most WAF + shared-host pitfalls (Imunify360, PHP-FPM worker exhaustion, LiteSpeed two-path cache invalidation, addon-domain docroot verification).
- **Event SFX premium B2B** — VPS + Docker + msrbuilds/elementor-mcp on a fresh stack. Source of the MCP-specific gotchas (image widget full object, MCP Abilities API input wrapper format, async-upload vs REST media fallback chain).

Open to consulting on WordPress + Elementor + Claude Code projects — reach out via email or LinkedIn.

For contributions, bug reports, and questions about the patterns themselves, please use [Issues](https://github.com/tranminhmanh/wp-stack-skill/issues) or [Discussions](https://github.com/tranminhmanh/wp-stack-skill/discussions) — much more useful than email since others can search them later.

<details>
<summary><strong>Tiếng Việt</strong></summary>

<br>

**Trần Minh Mạnh** — WordPress + Elementor developer độc lập, làm việc tại Việt Nam, tập trung vào site B2B mà form thực sự phải convert.

- Website: [tranminhmanh.id.vn](https://tranminhmanh.id.vn/)
- LinkedIn: [linkedin.com/in/tranminhmanh](https://www.linkedin.com/in/tranminhmanh/)
- GitHub: [@tranminhmanh](https://github.com/tranminhmanh)
- Email: [tranminhmanh.official@gmail.com](mailto:tranminhmanh.official@gmail.com)

Các pattern trong skill này được trích xuất từ quá trình debug production trên 3 site tôi tự build và maintain trong 3 tháng vừa qua:

- **Site B2B logistics** — 8 trang pillar quốc gia + 26 subpage cặp cảng + 5 blog post long-tail. Nguồn của bug Pro Form `custom_id` silent fail 9 tuần, pattern dọn dead-link do slug mismatch, và phần lớn workflow clone-transform bulk-build.
- **Site thực phẩm vùng miền** — Astra Free + Elementor `elementor_canvas` template trên shared hosting. Nguồn các pitfall liên quan WAF + shared host (Imunify360, PHP-FPM worker exhaustion, LiteSpeed 2-path cache invalidation, verify addon-domain docroot).
- **Site event SFX B2B premium** — VPS + Docker + msrbuilds/elementor-mcp stack mới. Nguồn các gotcha MCP-specific (image widget cần full object, MCP Abilities API input wrapper format, async-upload vs REST media fallback chain).

Open consulting cho dự án WordPress + Elementor + Claude Code — liên hệ qua email hoặc LinkedIn.

Contribution, bug report, và câu hỏi về pattern → vui lòng dùng [Issues](https://github.com/tranminhmanh/wp-stack-skill/issues) hoặc [Discussions](https://github.com/tranminhmanh/wp-stack-skill/discussions) thay email — để người khác search được sau này.

</details>

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
