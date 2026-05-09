# Workflow: Session distillation — upgrade skill từ insights mỗi chat

Cuối mỗi session làm việc trên project WP, distill các pattern mới phát hiện thành file trong skill này. Đây là cách skill **tự nâng cấp** chứ không phải static reference.

## Khi nào chạy distillation

✅ Sau khi giải quyết 1 bug khó (>1h debug) — root cause + fix là pattern reusable
✅ Khám phá kiến trúc/quirk mới của plugin/theme/host
✅ Tìm ra workaround khi tool/MCP fail
✅ Áp dụng workflow N lần và thấy bước nào lặp lại
❌ Sửa typo / config nhỏ — không đáng distill
❌ Insight chỉ áp dụng 1 site cụ thể (đó là project memory, không phải skill)

## Phân tách: skill vs project memory vs CLAUDE.md

Trước khi distill, quyết định insight thuộc layer nào:

| Layer | Nội dung | Path |
|---|---|---|
| **Skill** (`~/.claude/skills/wp-stack/`) | WHAT — universal pattern, reusable đa project | `references/*.md`, `workflows/*.md` |
| **Project memory** (`~/.claude/projects/<project>/memory/`) | WHO/WHERE — fact riêng project, persist sang session sau | `MEMORY.md`, `<topic>.md` |
| **Project CLAUDE.md** (`<project-root>/CLAUDE.md`) | Static config — host, brand, color, path, commit | Trong git repo |

Test phân biệt nhanh: "Pattern này áp dụng cho site khác cũng không?"
- Có → skill
- Không, nhưng cần nhớ cho session sau → project memory
- Không, cố định + cả team đọc được → project CLAUDE.md

## 6-step distillation process

### 1. Liệt kê insight raw

Cuối session, ghi nhanh 5-15 thứ học được. Không quan tâm format. Ví dụ:
```
- App password phải có space khi base64
- claude mcp add bị parse fail nếu --header trước name
- Astra entry-title duplicate H1 nếu page_template không phải canvas
- 13/20 PKM page thiếu H1 vì... (project-specific, skip)
- Build dashboard skill output đẹp với data:build-dashboard
- WebFetch không parse JSON-LD đầy đủ → đừng tin
```

### 2. Phân loại theo layer

Mỗi insight → đánh dấu skill / project / CLAUDE.md:
```
- App password có space: SKILL (universal)
- claude mcp add header order: SKILL (universal)
- Astra entry-title H1 dup: SKILL (đã có ở pitfalls.md, có thể có case mới?)
- 13/20 PKM page thiếu H1: PROJECT MEMORY (specific)
- data:build-dashboard tốt cho audit: SKILL workflow update
- WebFetch fail SEO parse: SKILL pitfall mới
```

### 3. Check duplicate

Trước khi tạo file mới, search trong skill xem đã có chưa:
```bash
cd ~/.claude/skills/wp-stack
grep -ri "application password" references/ workflows/
grep -ri "header order" references/ workflows/
```

Nếu đã có:
- Insight giống y → skip
- Insight thêm chi tiết / case mới → APPEND vào file cũ (đừng tạo file mới)
- Insight phản bác / sửa file cũ → UPDATE (mark deprecated nếu cần)

### 4. Quyết định format

| Loại insight | Đặt ở đâu |
|---|---|
| Universal architecture / kiến trúc plugin | `references/<topic>.md` (file mới hoặc section) |
| Quirk / gotcha / trap | `references/pitfalls.md` (append section) |
| Step-by-step process | `workflows/<task>.md` (file mới) |
| Setting / config cheatsheet | `references/<plugin>.md` |
| Code snippet / template | `templates/snippets/<name>.<ext>` |

### 5. Quality bar — insight ăn được

Mỗi insight đáng distill phải có ≥3 trong 4:

1. **Root cause**: vì sao chuyện đó xảy ra (không chỉ "làm gì khi gặp")
2. **Reproduction**: trigger condition cụ thể, kèm command/data input
3. **Fix**: cách giải quyết, kèm code/config/command verify được
4. **Reusability**: pattern dùng được cho site khác / case khác — không hardcode 1 project

❌ Anti-pattern (insight kém):
- "PKM 13 page thiếu H1" — quá specific, project memory chứ không skill
- "Đôi khi MCP fail, restart" — không root cause, không reproduction
- "Dùng Rank Math thay Yoast" — opinion không có data backing

✅ Pattern tốt:
- "Astra entry-title H1 duplicate khi `_wp_page_template != 'elementor_canvas'`. Reproduce: tạo page qua REST không set template → check `<h1 class=entry-title>` xuất hiện. Fix: `update_post_meta(_wp_page_template, 'elementor_canvas')`. Reusable cho mọi site Astra + Elementor."

### 6. Update CHANGELOG.md

Mỗi đợt distill = 1 entry version (semver):
```markdown
## [0.2.0] — 2026-05-10

### Added

- `references/mcp-architecture.md` — multi-plugin MCP endpoint architecture
- `references/wp-abilities.md` — direct REST ability call pattern
- `workflows/claude-mcp-connector-setup.md` — `claude mcp add` CLI workflow
- `workflows/session-distillation.md` — meta workflow for skill upgrades

### Changed

- `references/pitfalls.md` — added section "MCP bridge connector vs endpoint mismatch"
- `workflows/seo-audit.md` — added Python-stdlib Tier 2 template

### Lessons applied from

- PKM Mai Thanh project session 2026-05-10 — 4h audit + MCP bridge debugging
```

Bump version theo semver:
- **PATCH** (0.1.0 → 0.1.1): typo, formatting, link fix
- **MINOR** (0.1.0 → 0.2.0): file mới, section mới, pattern mới
- **MAJOR** (0.x.x → 1.0.0): breaking change ở structure (đổi tên file lớn, refactor)

## Rút insight từ project memory ngược về skill

Memory là nơi insight "ấp" — sau N session áp dụng pattern, nếu pattern lặp lại trên ≥2 project, **promote** lên skill:

```
PKM project memory:
  "MCP route /mcp/elementor-mcp-server cần connector riêng"
livesfx project memory:
  "Cùng pattern — connector riêng cho elementor-mcp-server"
chacavungtau project memory:
  "Cùng pattern"

→ promote thành skill references/mcp-architecture.md
```

Mặc định không promote sớm — insight trên 1 project có thể là quirk của site đó. Đợi xác nhận pattern qua nhiều project.

## Insight phản bác — sửa skill cũ

Skill có thể sai. Khi gặp insight phản bác:

1. **Verify bằng test thực**: reproduce condition trên 2 site khác xem có nhất quán không
2. **Không xóa text cũ ngay**: mark `~~deprecated~~` + ghi rõ vì sao
3. **CHANGELOG ghi rõ "Changed: X. Reason: Y. Replaced by Z"**
4. Nếu insight cũ chỉ sai trong context cụ thể → giữ + thêm "When NOT to apply"

## Output template — final report cuối session

```markdown
## Session distillation 2026-05-10

### Skill files affected

- ✅ Created: references/mcp-architecture.md (250 lines)
- ✅ Created: references/wp-abilities.md (180 lines)
- ✅ Created: workflows/claude-mcp-connector-setup.md (200 lines)
- ✅ Updated: references/pitfalls.md (+1 section, ~40 lines)
- ✅ Updated: workflows/seo-audit.md (+Python template, +WebFetch warning)
- ✅ Updated: CHANGELOG.md (v0.2.0 entry)

### Project memory updates

- project_pkmaithanh.md — stack details (Astra Pro, Elementor 4.0.7, 24 plugins)
- reference_mcp_access.md — connector config + REST workaround pattern

### Insights NOT distilled (rationale)

- "13/20 PKM page thiếu H1" — project-specific, kept in project memory only
- "WPForms vs FluentForms duplicate" — case-by-case audit finding, not universal pattern

### Time invested

Distillation: ~30 phút. Coverage: 6 reusable patterns + 2 project facts captured.
```

## Liên quan

- [`SKILL.md`](../SKILL.md) — separation of concerns (skill = WHAT, project = WHERE/WHO)
- [`CHANGELOG.md`](../CHANGELOG.md) — semver history
- [`templates/project-claude-md-template.md`](../templates/project-claude-md-template.md) — project CLAUDE.md template
