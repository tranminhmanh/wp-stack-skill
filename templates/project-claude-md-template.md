# Project: [Site name]

> Paste this template into `<project-root>/CLAUDE.md` for every WordPress project. Fill it in fully before asking Claude to do any deploy / SSH work.

## Brand
- **Domain**:
- **Industry**:
- **Target audience**: B2B / B2C / both
- **Tone**:
- **Primary color**:
- **Secondary color**:
- **Background color**:
- **Font primary**: Inter / Roboto / Be Vietnam Pro / other
- **Font heading**: (if different from primary)

## Hosting (REQUIRED if there's any deploy / SSH work)

- **Provider**: CloudPanel VPS / SiteGround / Cloudways / Hostinger / other
- **IP / Hostname**:
- **SSH access**:
  - Method: SSH key / password / panel-only / NO access
  - Alias (in `~/.ssh/config`):
  - User:
  - Port: 22 / custom
- **Site path**: (e.g. `/home/user/htdocs/site.com` or `/var/www/site.com`)
- **Database**:
  - Type: MySQL / MariaDB / PostgreSQL
  - Host: localhost / remote
  - Port: 3306 / 5432 / custom
  - Name:
- **PHP version**:
- **Production URL**:
- **Staging URL**: (if any)

## MCP setup

- **MCP endpoint**: `https://<staging or dev>/wp-json/mcp/elementor-mcp-server`
- **Application Password label**: (e.g. "Claude MCP")
- **App password**: (KEEP IN A PASSWORD MANAGER, do NOT paste here)

## Site-specific stack

(List any plugin / tool outside the standard stack. For example:)
- WooCommerce + a regional payment plugin
- LearnDash for courses
- BuddyBoss for community
- JetEngine for relationship CPTs
- Polylang / Meep AI Translator for bilingual

## CPT & ACF (if any)

- CPT: branch, product, project, ...
- Field group export: `templates/acf/<project>.json`

## Skills to apply

- wp-stack
- (other skills if needed)

## Constraints / notes

(Anything Claude should know:)
- Client preferences
- Deadline
- Progress so far
- Plugins explicitly forbidden
- Special style guide
- Commit / branch conventions if using git

## Workflow choices

- Vibe coding via Claude Code: Yes / No
- Theme Builder loop: Yes / No (if there's a CPT)
- Multilingual: No / Polylang / Meep / WPML
- E-commerce: No / WooCommerce / other
- Membership: No / MemberPress / other

---

## Example fully filled in

```markdown
# Project: ACME Corp

## Brand
- Domain: acme.com
- Industry: B2B SaaS for logistics
- Target: enterprise logistics managers
- Tone: professional, data-driven, trustworthy
- Primary: #0A2540
- Secondary: #00A3B5
- Background: #F8FAFC
- Font primary: Inter
- Font heading: Inter (weight 700)

## Hosting
- Provider: VPS Debian 12 + CloudPanel
- IP: [from global CLAUDE.md]
- SSH alias: acme-prod
- Site path: /home/acme/htdocs/acme.com
- Database: MySQL standard (CloudPanel managed)
- PHP version: 8.2
- Production URL: https://acme.com
- Staging URL: https://staging.acme.com

## MCP setup
- Endpoint: https://staging.acme.com/wp-json/mcp/elementor-mcp-server
- App password label: Claude Code

## Site-specific stack
- (none — standard stack is enough)

## Skills
- wp-stack

## Workflow
- Vibe coding via Claude Code: Yes
- Theme Builder loop: Yes (for case-study CPT)
- Multilingual: No (English only)
- E-commerce: No
```
