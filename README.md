# wp-stack — WordPress Stack Skill

Skill universal cho mọi WordPress project, theo stack: **Astra Free + Elementor Pro + ACF + msrbuilds/elementor-mcp**.

## Triết lý

```
Skill = WHAT (kiến thức universal)
CLAUDE.md project = WHERE (host, path, brand cụ thể)
```

Skill KHÔNG chứa hardcode host/SSH/path. Mọi thông tin specific đọc từ:
- `~/.claude/CLAUDE.md` — global về user
- `<project-root>/CLAUDE.md` — project hiện tại

## Cấu trúc

```
wp-stack/
├── SKILL.md                          ← Entry point (Claude đọc đầu tiên)
├── README.md                         ← File này
├── references/                       ← Knowledge base
│   ├── stack.md                      ← Stack chuẩn
│   ├── design-tokens.md              ← Spacing/typography universal
│   ├── elementor-mcp.md              ← MCP cheatsheet
│   ├── widget-mapping.md             ← HTML → widget
│   ├── responsive.md                 ← Breakpoint rules
│   ├── astra-customizer.md           ← Astra settings
│   ├── seo-checklist.md              ← Rank Math setup
│   ├── performance.md                ← Speed optimization
│   ├── security.md                   ← Hardening checklist
│   ├── deployment.md                 ← Deploy workflow generic
│   ├── vietnamese.md                 ← VN-specific concerns
│   └── pitfalls.md                   ← Bẫy thường gặp
├── workflows/                        ← Step-by-step procedures
│   ├── new-site-setup.md             ← Setup site mới A-Z
│   ├── add-cpt.md                    ← Tạo Custom Post Type
│   ├── theme-builder-loop.md         ← Build template loop
│   └── migrate-staging-prod.md       ← Migrate workflow
└── templates/                        ← Reusable assets
    ├── project-claude-md-template.md ← Template cho project CLAUDE.md
    ├── elementor/                    ← Elementor JSON templates (build sau)
    ├── acf/                          ← ACF field group exports (build sau)
    └── snippets/                     ← Code snippets (build sau)
```

## Cài đặt

### Trên máy local (Mac/Windows/Linux)

```bash
# 1. Clone hoặc copy folder vào ~/.claude/skills/
mkdir -p ~/.claude/skills
cp -r wp-stack ~/.claude/skills/

# 2. Verify Claude Code load skill
claude
> /skills list
```

Phải thấy `wp-stack` trong list.

### Cách dùng cho project mới

```bash
# 1. Tạo CLAUDE.md cho project
cd ~/projects/<project-name>
cp ~/.claude/skills/wp-stack/templates/project-claude-md-template.md CLAUDE.md

# 2. Edit CLAUDE.md, điền đầy đủ thông tin brand + hosting

# 3. Mở Claude Code trong project
claude

# 4. Trigger skill
> Build landing page hero theo skill wp-stack
```

Claude sẽ:
1. Đọc `CLAUDE.md` project lấy brand/host
2. Load `SKILL.md` của wp-stack
3. Load reference files cần thiết
4. Build theo chuẩn

## Maintenance

### Mỗi quý
Review `references/stack.md` — update version plugin/theme.

### Mỗi project xong
Phát hiện pattern mới hữu ích → add vào `pitfalls.md` hoặc tạo workflow mới.

### Khi Anthropic/Elementor update lớn
Review `elementor-mcp.md`, `design-tokens.md`.

### Build template library

Sau khi build trang đầu thành công:

```bash
# Trong Claude Code session
> Export hero section page ID 42 thành JSON,
> save vào ~/.claude/skills/wp-stack/templates/elementor/hero-section.json
```

Lần sau build hero mới cho project khác → Claude reference template → build nhanh gấp 5 lần.

## Anti-patterns (bị Claude từ chối)

- Đề xuất Divi/WPBakery/Bricks
- Đề xuất theme khác Astra
- Cài plugin ngoài stack mà không hỏi
- Đoán SSH alias/path khi CLAUDE.md không có
- Run command production không confirm
- Generate Vietnamese copy không nhắc native review

## License

Internal use. Không public — chứa workflow nội bộ.
