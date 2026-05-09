---
name: wp-stack
description: Build, fix, optimize, and debug WordPress sites with the standard stack (Astra Free + Elementor Pro + ACF + msrbuilds/elementor-mcp). Activates when the user asks for WordPress, Elementor, page-builder, landing-page, CPT, custom-field, theme-settings, plugin-config, deploy, migrate, performance, security, or SEO work, or mentions any tool in the stack (Astra, Elementor, ACF, JetEngine, Rank Math, Yoast, WP Rocket, LiteSpeed, Cloudflare, CloudPanel, Wordfence). Also activates when converting a design (Figma / Claude Design / HTML) into a WordPress + Elementor structure via MCP.
---

# WordPress Stack Skill — Universal

This skill applies to any WordPress site, regardless of the specific project.

## Separation of concerns (IMPORTANT)

This skill contains **WHAT** — universal knowledge about the stack, design tokens, MCP, and conventions.
Project `CLAUDE.md` files contain **WHERE/WHO** — which host, which path, which brand, which colors.

**Do NOT hardcode** host/SSH/path/database information in this skill. Read project-specific information from:
1. `~/.claude/CLAUDE.md` — global user preferences
2. `<project-root>/CLAUDE.md` — current project

If a `CLAUDE.md` does not have the information you need → **ask the user**, do not guess.

## Standard stack (REQUIRED)

Read `references/stack.md` for the full list of plugins and versions.

Summary:
- **Theme**: Astra Free (NOT Pro — Elementor Pro already covers the overlapping features)
- **Page builder**: Elementor Pro 3.20+ with Flexbox Containers
- **Custom fields**: ACF Free → JetEngine when relationships are needed
- **MCP**: msrbuilds/elementor-mcp v1.4+
- **SEO**: Rank Math (NOT Yoast)
- **Cache**: WP Rocket or LiteSpeed Cache
- **Backup**: UpdraftPlus + provider-level snapshots
- **Security**: Wordfence + 2FA admin
- **Email**: WP Mail SMTP + SendGrid / Brevo / Mailgun

## Core principles

1. **Native widget first** — do not use HTML widgets except for third-party embeds
2. **Flexbox Container** — do not use the legacy Section/Column system
3. **Design tokens** — read `references/design-tokens.md`, do not invent numbers
4. **Verify after write** — call `get-page-structure` after every MCP write
5. **Backup before editing production** — always
6. **Staging first** — do not edit production directly via MCP
7. **Locale-aware** — UTF-8, fonts with the correct subset, copy reviewed by a native speaker
8. **Mobile-first responsive** — three required breakpoints (375 / 768 / 1280)
9. **Performance budget** — Lighthouse mobile ≥85, LCP <2.5s
10. **Security defaults** — `wp-config` hardened, file permissions correct

## Standard workflow for any task

1. **Ask for context**: which project? staging or prod? backup taken?
2. **Read the project `CLAUDE.md`** to get brand / host / path
3. **Verify the actual stack** matches `references/stack.md`
4. **Plan the steps** and let the user approve
5. **Execute** with verification at each step
6. **Report**: what was done, verification link, suggested next step

## When to load which reference

| Task | Files to load |
|---|---|
| Build/edit a landing page | `design-tokens` + `elementor-mcp` + `widget-mapping` + `responsive` |
| Theme settings, header, footer | `astra-customizer` + `elementor-mcp` |
| Create a CPT or custom field | `workflows/add-cpt.md` |
| Loop template, archive | `workflows/theme-builder-loop.md` |
| Set up a new site from scratch | `workflows/new-site-setup.md` |
| SEO setup | `seo-checklist` + `vietnamese` (for Vietnamese sites) |
| Slow site | `performance` |
| Hacked / malware | `security` |
| Deploy / migrate | `deployment` + `workflows/migrate-staging-prod.md` |
| MCP errors | `pitfalls` + `mcp-architecture` (1 plugin = 1 endpoint = 1 connector) |
| MCP bridge 404 / tool count gap | `mcp-architecture` + `wp-abilities` (REST fallback) |
| Setup MCP connector mới | `workflows/claude-mcp-connector-setup.md` |
| Distill insights cuối session | `workflows/session-distillation.md` |
| Bulk-build N similar pages | `workflows/clone-transform-pattern.md` |
| OG image generation at scale | `workflows/og-image-generation.md` |
| SEO audit on N pages | `workflows/seo-audit.md` |
| SMTP relay setup (form email) | `workflows/smtp-relay-setup.md` |
| Bilingual / multilingual site (Polylang) | `workflows/multilingual-polylang.md` |

## Anti-patterns — STRICTLY avoid

- Suggesting Divi / WPBakery / Bricks (the stack is Elementor only)
- Suggesting Hello / GeneratePress / OceanWP themes (the stack is Astra only)
- Editing production without a backup
- Installing a plugin outside the stack without asking
- Suggesting "rebuild from scratch" when an incremental fix is possible
- Generating Vietnamese (or other non-English) copy without flagging it for native review
- Skipping the mobile breakpoint
- Inline CSS instead of widget settings
- HTML widgets for text / button / heading
- **Guessing SSH alias, path, or database name** when `CLAUDE.md` does not have them
- **Running production commands** without confirming with the user

## Safe pattern for deploy / SSH work

```
User:   Deploy ACME to production.
Claude: Reading the ACME project CLAUDE.md...
        I will SSH to <alias from CLAUDE.md>, path <from CLAUDE.md>,
        pull staging → production. Confirm?
User:   Confirm.
Claude: [runs commands]
```

If `CLAUDE.md` is missing information: **ask first**, do not run.
