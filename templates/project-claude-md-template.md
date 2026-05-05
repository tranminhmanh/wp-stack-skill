# Project: [Tên site]

> Template này paste vào `<project-root>/CLAUDE.md` cho mỗi WordPress project. Điền đầy đủ trước khi nhờ Claude làm việc gì liên quan deploy/SSH.

## Brand
- **Domain**: 
- **Industry**: 
- **Target audience**: B2B / B2C / both
- **Tone**: 
- **Primary color**: 
- **Secondary color**: 
- **Background color**: 
- **Font primary**: Be Vietnam Pro / Inter / Roboto / khác
- **Font heading**: (nếu khác primary)

## Hosting (BẮT BUỘC điền nếu có deploy/SSH)

- **Provider**: CloudPanel VPS / SiteGround / Cloudways / Hostinger / Other
- **IP/Hostname**: 
- **SSH access**:
  - Method: SSH key / password / panel only / KHÔNG có
  - Alias (trong ~/.ssh/config): 
  - User: 
  - Port: 22 / custom
- **Site path**: (vd: `/home/user/htdocs/site.com` hoặc `/var/www/site.com`)
- **Database**:
  - Type: MySQL / MariaDB / PostgreSQL
  - Host: localhost / remote
  - Port: 3306 / 5432 / custom
  - Name: 
- **PHP version**: 
- **Production URL**: 
- **Staging URL**: (nếu có)

## MCP setup

- **MCP endpoint**: `https://<staging hoặc dev>/wp-json/mcp/elementor-mcp-server`
- **Application Password label**: (vd "Claude MCP")
- **App password**: (LƯU TRONG PASSWORD MANAGER, không paste vào đây)

## Stack đặc biệt site này

(Liệt kê plugin/tool ngoài stack chuẩn nếu có. Ví dụ:)
- WooCommerce + plugin payment Việt
- LearnDash cho course
- BuddyBoss cho community
- JetEngine cho relationship CPT
- Polylang / Meep AI Translator cho song ngữ

## CPT & ACF (nếu có)

- CPT: branch, product, project, ...
- Field group export: `templates/acf/<project>.json`

## Skill apply

- wp-stack
- (skill khác nếu cần)

## Constraints / Notes

(Bất cứ gì Claude cần biết:)
- Client preferences
- Deadline
- Đã làm tới đâu
- Plugin nào cấm cài
- Style guide đặc biệt
- Quy ước commit/branch nếu có git

## Workflow đã chọn

- Vibe coding via Claude Code: Yes/No
- Theme Builder loop: Yes/No (nếu có CPT)
- Multilingual: No / Polylang / Meep / WPML
- E-commerce: No / WooCommerce / khác
- Membership: No / MemberPress / khác

---

## Ví dụ điền đầy đủ (LIVESFX)

```markdown
# Project: LIVESFX

## Brand
- Domain: livesfx.vn
- Industry: SFX cho event (B2B premium)
- Target: event organizer, concert promoter, wedding planner cao cấp
- Tone: dark cinematic, asymmetric, motion-friendly
- Primary: #FF4500
- Secondary: #FFD700
- Background: #0A0A0A
- Font primary: Be Vietnam Pro

## Hosting
- Provider: VPS Debian 12 + CloudPanel
- IP: [từ CLAUDE.md global]
- SSH alias: manhkyi2
- Site path: /home/livesfx/htdocs/livesfx.vn
- Database: MySQL standard (CloudPanel managed)
- PHP version: 8.2
- Production URL: https://livesfx.vn
- Staging URL: https://staging.livesfx.vn

## MCP setup
- Endpoint: https://staging.livesfx.vn/wp-json/mcp/elementor-mcp-server
- App password label: Claude Code

## Stack đặc biệt
- (none — stack chuẩn đủ)

## Skill apply
- wp-stack

## Workflow
- Vibe coding via Claude Code: Yes
- Theme Builder loop: Yes (cho portfolio dự án)
- Multilingual: No (chỉ Vietnamese)
- E-commerce: No
```
