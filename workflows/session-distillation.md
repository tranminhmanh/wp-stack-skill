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
- 13/20 page của Site X thiếu H1 vì... (project-specific, skip)
- Build dashboard skill output đẹp với data:build-dashboard
- WebFetch không parse JSON-LD đầy đủ → đừng tin
```

### 2. Phân loại theo layer

Mỗi insight → đánh dấu skill / project / CLAUDE.md:
```
- App password có space: SKILL (universal)
- claude mcp add header order: SKILL (universal)
- Astra entry-title H1 dup: SKILL (đã có ở pitfalls.md, có thể có case mới?)
- 13/20 page của Site X thiếu H1: PROJECT MEMORY (specific)
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

Mỗi insight đáng distill phải có ≥4 trong 5:

1. **Root cause**: vì sao chuyện đó xảy ra (không chỉ "làm gì khi gặp")
2. **Reproduction**: trigger condition cụ thể, kèm command/data input
3. **Fix**: cách giải quyết, kèm code/config/command verify được
4. **Reusability**: pattern dùng được cho site khác / case khác — không hardcode 1 project
5. **Brand-neutral**: pattern phải express được mà không cần real domain / brand acronym / customer name / identifying stack combo. Nếu giữ ví dụ thật, phải scrub thành `example.com` / `acme-*` / `Site A` / `Site B`. Đây là điều kiện BẮT BUỘC khi skill public — leaked customer info = breach trust + có thể vi phạm NDA.

❌ Anti-pattern (insight kém):
- "13 page của Site X thiếu H1" — quá specific, project memory chứ không skill
- "Đôi khi MCP fail, restart" — không root cause, không reproduction
- "Dùng Rank Math thay Yoast" — opinion không có data backing
- "Site `<real-domain>.com` connector `<acronym>` thiếu endpoint" — leak customer / project name → re-write với generic placeholder

✅ Pattern tốt:
- "Astra entry-title H1 duplicate khi `_wp_page_template != 'elementor_canvas'`. Reproduce: tạo page qua REST không set template → check `<h1 class=entry-title>` xuất hiện. Fix: `update_post_meta(_wp_page_template, 'elementor_canvas')`. Reusable cho mọi site Astra + Elementor."

### 5b. Pre-publish brand-leak scan (BẮT BUỘC trước khi commit lên repo public)

Trước khi `git push`, grep toàn bộ skill files cho identifier-class strings có thể leak:

```bash
cd ~/.claude/skills/wp-stack

# Known-safe domains: placeholders + 3rd-party docs + standards URIs
ALLOW='example\.com|example\.org|site\.com|acme\.com|<site>'   # placeholders
ALLOW="$ALLOW"'|github\.com|schema\.org|wordpress\.org|w3\.org|sitemaps\.org'
ALLOW="$ALLOW"'|wpastra\.com|elementor\.com|advancedcustomfields\.com|rankmath\.com'
ALLOW="$ALLOW"'|claude\.com|anthropic\.com|keepachangelog\.com|semver\.org'
ALLOW="$ALLOW"'|brevo\.com|sendgrid\.com|mailgun\.com|cloudflare\.com'
ALLOW="$ALLOW"'|api\.replicate\.com|googleapis\.com|gstatic\.com|cdnjs\.cloudflare\.com'
ALLOW="$ALLOW"'|fontawesome\.com|fluentforms\.com|dequeuniversity\.com|webaim\.org'

# 1. Any real domain in skill content (only placeholders + 3rd-party allowed)
grep -nrE "https?://[a-z0-9.-]+\.(com|vn|net|org|id\.vn|io)" \
  references/ workflows/ templates/ SKILL.md CHANGELOG.md \
  | grep -vE "$ALLOW"

# 2. Brand acronym / customer name — populate from your real project list,
#    then run before each push. Example pattern (replace with your slugs):
LEAK='\b(acmecorp|client-a|client-b)\b'   # ← edit per your portfolio
grep -nriE "$LEAK" references/ workflows/ CHANGELOG.md SKILL.md
# Append new acronym/slug whenever you distill from a new project.

# 3. Connector / project slug must be neutral
grep -nrE "[a-z]+-(vn|com|net)-(global|elementor)" references/ workflows/ \
  | grep -vE "acme-(global|elementor)|<site>-(global|elementor)|<site-slug>"

# 4. Maintainer / team first names should NOT appear in workflow text;
#    they belong only in README Author section (intentional OSS credit).
#    Replace the regex with your team's name list.
grep -nrE "<list-of-team-first-names>" workflows/ references/

# Expect: empty output for all 4 commands.
# Self-reference exception: the grep pattern itself (this section) will
# match its own regex strings — that is OK, scrub does not need to
# rewrite the workflow that documents the scrub.
```

Nếu có hit → re-edit + re-commit trước khi push. Đây là step 5b vì brand-scrub thường bị quên cho tới khi user phát hiện.

**Recovery khi đã publish leak**:
1. Edit files local + commit "Scrub brand leaks"
2. Push commit mới (KHÔNG force-push lên main vì destructive)
3. Edit GitHub release notes via `gh release edit <tag> --notes "<scrubbed>"`
4. Note rằng git history vẫn có leak — chỉ scrub được nếu force-push, mà force-push public main là worse risk
5. Lessons-learn: thêm pattern vào step 5b grep cho session sau

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

- Inherited B2B site debug session 2026-05-10 — 4h audit + MCP bridge debugging
```

Bump version theo semver:
- **PATCH** (0.1.0 → 0.1.1): typo, formatting, link fix
- **MINOR** (0.1.0 → 0.2.0): file mới, section mới, pattern mới
- **MAJOR** (0.x.x → 1.0.0): breaking change ở structure (đổi tên file lớn, refactor)

## Rút insight từ project memory ngược về skill

Memory là nơi insight "ấp" — sau N session áp dụng pattern, nếu pattern lặp lại trên ≥2 project, **promote** lên skill:

```
Site A project memory:
  "MCP route /mcp/elementor-mcp-server cần connector riêng"
Site B project memory:
  "Cùng pattern — connector riêng cho elementor-mcp-server"
Site C project memory:
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

- project_<slug>.md — stack details (theme, builder version, plugin count)
- reference_mcp_access.md — connector config + REST workaround pattern

### Insights NOT distilled (rationale)

- "13/20 page X thiếu H1" — project-specific, kept in project memory only
- "Form plugin A vs Form plugin B duplicate" — case-by-case audit finding, not universal pattern

### Time invested

Distillation: ~30 phút. Coverage: 6 reusable patterns + 2 project facts captured.
```

## Pre-push brand-leak scan — MUST cover BOTH file content AND commit message

⚠️ **Critical rule cho mọi push to public skill repo**: brand-leak scan phải kiểm BOTH:
1. File content (modified + new files)
2. **Commit message text itself** (often forgotten — leak lives in git log forever)

### Real-world miss (anonymized 2026-05-13)

Distillation session scrubbed 12 brand instances từ 6 skill files, commit + push to main. Commit message body **liệt kê toàn bộ leak gốc plaintext** trong before→after substitution table — leak xuất hiện trên public GitHub commit page (forever indexed). Required amend + `--force-with-lease` push để rewrite history. Same pitfall struck the documentation commit itself initially — `<placeholder>` syntax in body table avoids it.

### Pre-push checklist (5 commands)

```bash
cd ~/.claude/skills/wp-stack

# 1. Scan staged/changed file CONTENT for project-specific terms
git diff --cached | grep -iE "your-site\.com|client-name|doctor-name|hospital-name|<other brand terms>" && echo "LEAK IN CONTENT" || echo "CONTENT CLEAN"

# 2. Scan working tree files (catch new files not yet staged)
for term in "site-domain" "client-name" "doctor-name" "hospital-name"; do
    count=$(grep -rli --include="*.md" "$term" references/ workflows/ SKILL.md 2>/dev/null | wc -l)
    [ "$count" -gt 0 ] && echo "LEAK: $term in $count files"
done

# 3. PREPARE commit message in /tmp/msg.txt FIRST (don't inline -m)
$EDITOR /tmp/commit-msg.txt
# Write structural description without quoting actual brand strings.

# 4. SCAN the commit message itself BEFORE committing
for term in "site-domain" "client-name" "doctor-name" "hospital-name"; do
    grep -i "$term" /tmp/commit-msg.txt && echo "LEAK IN MESSAGE"
done

# 5. Only after both scans clean — commit + push
git commit -F /tmp/commit-msg.txt
git push origin HEAD
rm /tmp/commit-msg.txt  # cleanup
```

### Commit message style — describe structure, not content

| ❌ Wrong (leaks brand in message) | ✅ Right (structural description) |
|---|---|
| `"<ActualSiteDomain>" → "site.com"` | `site URL placeholder` |
| `"<RealClientName>" → "<Clinic Name>"` | `clinic name placeholder` |
| `"<RealDoctorName>" → "<Doctor>"` | `doctor name + alternate names` |
| `"<RealHospitalName>" → "<Hospital>"` | `hospital affiliation example` |

Note: the ❌ column above uses meta-placeholders `<ActualXxx>` to describe the anti-pattern without itself leaking. The pattern is: never quote the actual brand string in commit messages — describe field type only.

The git log entry stays informative ("8 replacements in schema-jsonld.md Physician template covering doctor name, hospital, university, professional associations") without exposing the originals.

### Why this matters

- GitHub commits are **forever public** even after file scrub (visible via commit page, GitHub search, code mirrors, Wayback Machine archives, JSON API)
- `git log -p` retrieves full message history → bypass file scrub entirely
- Code search engines (Sourcegraph, grep.app) index commit messages
- AI training corpora often include commit messages → leak baked into future models

### Brand-term blocklist per project

Each project's `CLAUDE.md` should declare its brand-leak blocklist for skill push scans. Example structure in [`templates/project-claude-md-template.md`](../templates/project-claude-md-template.md):

```markdown
## Brand-leak blocklist (for skill push scans)

Terms that must NEVER appear in public skill repo (`references/`, `workflows/`, SKILL.md, commit messages):

- Site domain: `site-domain.com`
- Brand display name: `Brand Display Name`, `Brand Vietnamese Spelling`
- Person names: `Person 1 Full Name`, `Person 2 Full Name`, common shortenings
- Institution affiliations: `Top Hospital Name`, `University Name`
- Specific addresses, phone numbers, license numbers
- Project codename / shorthand if site-identifying (e.g. abbreviation appears nowhere else online)
```

### Force-push amendment recovery

If brand leak shipped in commit message (post-push discovery):

```bash
# 1. Rewrite last commit with sanitized message
git commit --amend -F /tmp/sanitized-msg.txt

# 2. Force push with --force-with-lease (safer than --force)
git push origin main --force-with-lease

# Warn before doing: any collaborator already pulled the bad commit has
# divergent local history. Solo repos low impact; team repos require notice.
```

⚠️ Force-push to main always requires user explicit "yes". Per safety rules, never force push without prior confirmation.

## Periodic skill drift audit — Step 8 every 10-20 promote events

Distillation rounds add insights but rarely **rewrite existing claims**. Sau ~10-20 PROMOTE-TO-SKILL events across multiple projects, skill phát triển 3 dạng drift:

1. **Prescriptive rules conflict với deployed reality** — vd "NOT Astra Pro" trong stack.md trong khi 3+ inherited sites run Astra Pro 4.13.x
2. **Anti-patterns marked wrongly** — pattern ban từ rule v1 nay được dùng intentionally cho operational reason mới (vd merged single-endpoint MCP)
3. **Coverage gaps cho stack variations** — non-standard builders (Flatsome, WPBakery, Bricks) chưa documented

Without periodic audit, agent tự tin propose wrong solutions từ stale rules.

### Trigger

Run reflection audit **every 10-20 PROMOTE-TO-SKILL events** — tune frequency theo project velocity. Tracking: count PROMOTED-TO-SKILL markers across project insights.md files since last audit; once ≥10, schedule next.

### Agent prompt template

Paste vào Agent tool (Explore or Plan agent type):

```
Audit reflection task — cross-project consistency của skill <skill-name> at <skill-path>.

INVESTIGATE 4 questions, looking at ALL recent project insights.md files + the skill files:

1. Coverage gaps — patterns đang dùng trong production projects nhưng skill chưa document?
   (vd: builder X, plugin Y, deploy path Z khi nào appear ≥2 sites)

2. Cross-project inconsistency — same task làm khác cách ở mỗi project?
   (vd: project-A dùng path A, project-B dùng path B for same goal — neither in skill)

3. Skill conflicts — rules contradicting deployed reality?
   (vd: skill says "NEVER X" but ≥2 projects actively use X without issue)

4. Stale content — files >2 weeks chưa touched, possibly outdated?
   (check git log + cross-reference với current insight content)

For each finding: cite specific files, line numbers, project examples.
Be BLUNT — không sugar-coat. User wants reality check.
Output: table với columns | Finding | Severity | Recommended action | Files affected |
```

### Resolution categories per finding

After audit, classify each finding into one of 4 buckets:

| Category | When | Example action |
|---|---|---|
| **Reconcile** | Hard contradiction — rule wrong | Soften absolute rule → conditional rule, add exception case, update prescription |
| **Add file** | Net new coverage needed | Create new reference (vd `non-standard-stacks.md` cho Flatsome) |
| **Add workflow** | Multiple valid paths exist, no canonical recipe | Create new workflow with decision tree (vd `deploy-rankmath-mcp-wrapper.md` cho 4 distribution paths) |
| **Mark stale** | File old but still relevant — needs refresh | Flag file in CHANGELOG todo list for next distillation round |

### Real-world outcome (wp-stack v0.9.0)

Audit ran ~10 days after v0.8.0 distillation. Findings:
- 3 hard contradictions (Astra Pro anti-pattern, MCP merged endpoint, native widget first)
- 1 coverage gap (Flatsome + UX Builder) — new file
- 1 missing canonical recipe (4 deploy paths for rankmath-mcp wrapper) — new workflow
- 4 stale files flagged for next round

Net: 3 file edits + 2 new files + 0 deletions. Skill stayed prescriptive but acknowledged reality alternatives.

### When NOT to run drift audit

- After every distillation round (overkill — drift takes time to accumulate)
- Project velocity low (<5 promote events trong 2-3 weeks)
- Major skill refactor just shipped (audit redundant immediately after)

## Liên quan

- [`SKILL.md`](../SKILL.md) — separation of concerns (skill = WHAT, project = WHERE/WHO)
- [`CHANGELOG.md`](../CHANGELOG.md) — semver history
- [`templates/project-claude-md-template.md`](../templates/project-claude-md-template.md) — project CLAUDE.md template
