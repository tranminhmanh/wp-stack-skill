---
name: wp-stack
description: Build, sửa, tối ưu, debug WordPress sites theo stack chuẩn (Astra Free + Elementor Pro + ACF + msrbuilds/elementor-mcp). Kích hoạt khi user yêu cầu việc liên quan WordPress, Elementor, page builder, landing page, CPT, custom field, theme settings, plugin config, deploy WP, migrate, performance, security, SEO trên WP, hoặc nhắc tên tool/plugin trong stack (Astra, Elementor, ACF, JetEngine, Rank Math, Yoast, WP Rocket, Cloudflare, CloudPanel, Wordfence). Cũng kích hoạt khi convert design (Figma/Claude Design/HTML) sang WordPress + Elementor structure qua MCP.
---

# WordPress Stack Skill — Universal

Skill này áp dụng cho MỌI WordPress site, không phụ thuộc project cụ thể.

## Nguyên tắc phân tách (QUAN TRỌNG)

Skill này chứa **WHAT** — kiến thức universal về stack, design tokens, MCP, conventions.
Project CLAUDE.md chứa **WHERE/WHO** — host nào, path nào, brand gì, màu gì.

**KHÔNG hardcode** thông tin host/SSH/path/database vào skill. Mọi thông tin specific đọc từ:
1. `~/.claude/CLAUDE.md` — global về user
2. `<project-root>/CLAUDE.md` — project hiện tại

Nếu CLAUDE.md không có thông tin cần thiết → **HỎI user**, KHÔNG đoán.

## Stack chuẩn (BẮT BUỘC tuân thủ)

Đọc `references/stack.md` để biết plugin nào cài, version nào.

Tóm tắt:
- **Theme**: Astra Free (KHÔNG Pro, đã có Elementor Pro chồng tính năng)
- **Page builder**: Elementor Pro 3.20+ với Flexbox Containers
- **Custom fields**: ACF Free → JetEngine khi cần relationship
- **MCP**: msrbuilds/elementor-mcp v1.4+
- **SEO**: Rank Math (KHÔNG Yoast)
- **Cache**: WP Rocket hoặc LiteSpeed Cache
- **Backup**: UpdraftPlus + provider-level snapshot
- **Security**: Wordfence + 2FA admin
- **Email**: WP Mail SMTP + SendGrid/Brevo

## Nguyên tắc tối thượng

1. **Native widget first** — KHÔNG HTML widget trừ embed bên thứ 3
2. **Flexbox Container** — KHÔNG Section/Column cũ
3. **Design tokens** — đọc `references/design-tokens.md`, không bịa số
4. **Verify sau write** — `get-page-structure` sau mỗi MCP write
5. **Backup trước edit production** — luôn luôn
6. **Staging first** — KHÔNG MCP edit thẳng production
7. **Vietnamese-aware** — UTF-8, font có VN subset, copy do native review
8. **Mobile-first responsive** — 3 breakpoint bắt buộc (375/768/1280)
9. **Performance budget** — Lighthouse mobile ≥85, LCP <2.5s
10. **Security default** — wp-config hardened, file perm chuẩn

## Workflow chuẩn cho mọi task

1. **Hỏi context**: project nào? staging hay prod? đã backup chưa?
2. **Đọc CLAUDE.md project** để lấy brand/host/path
3. **Verify stack** thực tế match với `references/stack.md` không
4. **Plan steps**, show user duyệt
5. **Execute** với verify mỗi bước
6. **Report**: đã làm gì, link verify, next step gợi ý

## Khi nào load reference nào

| Task | Load files |
|---|---|
| Build/sửa landing page | design-tokens + elementor-mcp + widget-mapping + responsive |
| Theme settings, header, footer | astra-customizer + elementor-mcp |
| Tạo CPT, custom field | workflows/add-cpt.md |
| Loop template, archive | workflows/theme-builder-loop.md |
| Setup site mới từ đầu | workflows/new-site-setup.md |
| SEO setup | seo-checklist + vietnamese |
| Site chậm | performance |
| Bị hack/malware | security |
| Deploy/migrate | deployment + workflows/migrate-staging-prod.md |
| Lỗi MCP | pitfalls |
| User nhắc tiếng Việt | vietnamese |

## Anti-patterns — TUYỆT ĐỐI tránh

- Đề xuất Divi/WPBakery/Bricks (stack chỉ Elementor)
- Đề xuất Hello/GeneratePress/OceanWP theme (stack chỉ Astra)
- Edit thẳng production không backup
- Cài plugin ngoài stack mà không hỏi
- Suggest "rebuild từ đầu" khi có thể fix incremental
- Generate copy tiếng Việt mà không nhắc native review
- Bỏ qua mobile breakpoint
- Inline CSS thay vì widget settings
- HTML widget cho text/button/heading
- **Đoán SSH alias, path, database name** khi CLAUDE.md không có
- **Run command production** mà không confirm với user

## Pattern an toàn cho deploy/SSH

```
User: Deploy LIVESFX lên production
Claude: Đọc CLAUDE.md project livesfx...
        Tôi sẽ SSH `<alias từ CLAUDE.md>`, path `<từ CLAUDE.md>`,
        pull staging → production. Đúng không?
User: Đúng
Claude: [chạy commands]
```

Nếu CLAUDE.md thiếu thông tin: HỎI trước, không chạy.
