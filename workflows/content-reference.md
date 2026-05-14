# Workflow: `content_reference.md` — single source of truth for brand facts

When AI generates copy for a website, every session is a fresh start — the AI does not remember last week's facts unless they're in context. Result: drift. Homepage counter says "founded 2022, 50+ team", an /about/ page hand-edited later says "founded 2023, 10 team", a footer copyright auto-updated 2024 says "© 2024 BrandX". Three numbers, all inconsistent.

`content_reference.md` is a project-level markdown file that locks every fact, list, copy pattern the AI needs. The AI reads it at the start of every copy-writing session. One source of truth, edited once per fact.

## When to use

✅ Site has multiple pages with the same brand facts (counters, team size, founding year, awards, partner logos)
✅ Content is partially AI-generated (drift risk is high)
✅ Brand voice / tone needs to be consistent across pages
✅ User wants AI to reference real numbers, not invent them

❌ Single-page site with one source of all facts (the page itself is the SoT)
❌ Pure technical docs site with no brand voice

## File location

`<project-root>/content_reference.md` — same directory as `CLAUDE.md`. Both files are project-specific (do NOT commit secrets to a public repo).

## Recommended sections

A working file is ~500–1500 lines. Larger gets unwieldy; smaller risks omission. Structure:

```
1. Brand identity quick facts (locked numbers — year, team size, address, license)
2. Service / product catalog (with tech specs, prices)
3. Portfolio / case studies database (project name, date, partner, outcome)
4. Equipment / asset inventory (specifics: model, version, capability)
5. Partner / vendor database (logos, agencies, brands worked with)
6. Blog catalog + topic gaps (what's published + what's missing)
7. Tone & voice patterns (do / don't examples for the brand)
8. Trust signals (license, insurance, certifications — qualitative wording)
9. SEO meta references (title format templates, description templates)
10. Conflicts table (fact V0 vs fact V1 with rationale for the change)
11. Usage guide (when to read this, how to update it)
```

Sections 1–8 are the canonical facts. Sections 9–11 are workflow / governance.

A starter template lives at [`templates/content-reference-template.md`](../templates/content-reference-template.md).

## Build process — 4 steps

### 1. Source — scrape `llms.txt` if the site has one

Modern WordPress sites with Rank Math / Yoast / FOXAI auto-generate an `llms.txt` at root: `https://example.com/llms.txt`. It's an AI-friendly summary of the site's content. If present, this is your starting set:
```bash
curl -s https://example.com/llms.txt > content-reference-source.txt
```

If not present, scrape key pages by hand:
```bash
curl -s https://example.com/about/ | <html-to-text-tool>
curl -s https://example.com/services/ | <html-to-text-tool>
# etc.
```

### 2. Augment — pull from non-public sources

- Brand guidelines PDF (Drive, Notion, Figma)
- `CLAUDE.md` project facts
- Old sales kit / brochures (PDF or Docs)
- Customer persona docs
- Founder interview notes
- Any "frequently corrected" feedback from the user (these are golden — they reveal facts the user keeps having to repeat)

### 3. Structure — organize by concept, NOT by page

Counter-intuitive: do NOT mirror the website's page structure. Pages change; concepts persist. A "Pricing" section in the file collects all price-related facts (per-service prices, discount structure, package tiers, regional variants), regardless of which page they appear on.

When a fact appears on multiple pages, list ALL pages where it appears in the section. This becomes the audit list when the fact changes.

### 4. Lock numbers + flag conflicts

For every fact:
- ✅ Verified — confirmed against canonical source (founder, paperwork, internal system). Use this.
- ⚠️ Conflict — fact has changed, old value still present somewhere. Action item to update.
- ❓ Unverified — claimed but not confirmed. Don't use until verified.

Sample:
```markdown
## Brand identity facts (verified 2026-05-10)

- ✅ **Founding year**: 2023 (verified, business registration certificate)
- ⚠️ **Team size**: 10 KTV chính thức (verified) — but homepage counter still shows "50+", needs update (see Conflicts table §10)
- ❓ **Annual revenue**: claimed "5B VND" in marketing brochure — not verified, do NOT use until founder confirms
```

## Conflicts table — track facts that change over time

```markdown
## §10 Conflicts table

| Fact | V0 (old) | V1 (current) | Why changed | Pages still showing V0 |
|---|---|---|---|---|
| Team size | 50+ KTV | 10 KTV chính thức | V0 was aspirational claim from 2022 marketing — V1 is verified count | homepage counter widget b72d1b2; about-us heading b04cdb6 |
| Founding year | 2022 | 2023 | V0 was when domain was registered — V1 is when business was actually licensed | homepage; footer copyright |
| Service count | "10+ services" | "6 core services + 3 add-ons" | V0 was vague — V1 is specific catalog | homepage; service hub |
```

When a fact changes (V0 → V1), every page in the "still showing V0" column becomes an action item.

## Cross-page audit — when updating a fact

Before edits, find every place the fact lives. With the site live, walk the sitemap:

```bash
# Get all URLs
curl -s https://example.com/sitemap_index.xml | grep -oE 'https://[^<]+' > sitemap-urls.txt

# Grep for the old fact across the entire site
while read url; do
  curl -s "$url" | grep -l "50+\|founded 2022" && echo "$url"
done < sitemap-urls.txt
```

That gives the audit list. For each hit, plan an update via MCP / REST.

After updates, re-grep to confirm zero hits. Update the conflicts table to mark V0 as "fully migrated".

## Workflow per copy-writing session

```
1. AI reads content_reference.md sections relevant to the task
2. AI picks correct facts/numbers from the locked list (sections 1–8)
3. AI uses tone patterns from §7 for voice consistency
4. AI cross-checks final draft against conflicts table (§10)
5. If a NEW fact is discovered, AI updates content_reference.md AND alerts the user
```

The 5th step is the system-improving feedback loop. Without it, the file goes stale.

## Storage discipline

- ✅ `content_reference.md` lives in the project repo (or Synology Drive / Dropbox)
- ✅ Update timestamp at the top: `Last verified: YYYY-MM-DD by <person>`
- ✅ Treat the file like code — `git diff` it before committing
- ❌ Do NOT commit to a PUBLIC repo if it contains private business data (revenue, internal pricing, customer names)
- ❌ Do NOT version the file via filename (`content-reference-v2.md`, `content-reference-final-v3.md`) — keep ONE file, use git history

## Reusable across projects

Pattern works for every brand-heavy WordPress project:
- B2B services (consulting, agencies, logistics, healthcare)
- E-commerce with brand voice (DTC fashion, food, beauty)
- Personal brand (consultants, coaches, freelancers)
- Multi-author blogs (need shared style guide)

Each project gets its own `content_reference.md`. The pattern is the same; the contents are project-specific.

## Anti-patterns

❌ **Scattered facts**: leaving brand facts in `CLAUDE.md`, `MEMORY.md`, ad-hoc chat messages, Google Docs, Slack pins. The AI can't read all of these in every session.

❌ **CMS-style "single source of truth"**: trying to make WordPress itself the SoT. The CMS has display logic + design state mixed in; extracting the facts requires curl + parsing every session. Markdown SoT is faster.

❌ **Auto-generation without verification**: pulling SoT from an LLM-summarized version of the website (the lazy "scrape llms.txt and call it done"). The site already has the drift problem; sucking it back in doesn't fix it.

❌ **One-shot file**: writing content_reference.md once and never updating. Brand facts change. The file MUST be a living document, with edits per fact change.

## Brand fact audit cross-page khi update

Khi update 1 brand fact (founding year, team size, address, phone, awards), audit toàn site trước commit để bắt drift. Brand facts sống ở nhiều nơi:

- Counter widget homepage (có thể có nhiều widget IDs khác nhau)
- About-us / Giới thiệu page heading + body
- Footer copyright auto-update
- Schema markup (LocalBusiness.foundingDate, Organization.foundingDate)
- Meta tags description (Rank Math / Yoast custom)
- Social profile bio (sync separately)
- Email signature template (sync separately)
- llms.txt (auto-gen, may lag behind manual updates)

Update 1 nơi không trigger update các nơi khác → drift accumulates.

### Story (anonymized real example)

CLAUDE.md / content_reference.md ghi "founded 2014, 12 năm kinh nghiệm" (verified). Nhưng:
- Homepage counter vẫn show "2013 — 10 năm" (cũ 1 year)
- About-us page heading vẫn show "Thành lập 2013"
- Schema LocalBusiness `foundingDate: "2013-01-01"` (cũ)

User chỉ phát hiện sau khi visit page → ask "tại sao mâu thuẫn?".

### Audit workflow

**Step 1: Grep toàn site cho fact cũ + variants**

```bash
# Get full URL list từ sitemap
curl -s https://example.com/sitemap_index.xml | grep -oP 'https://[^<]+\.xml' | \
  xargs -I {} curl -s {} | grep -oP 'https://[^<]+' > /tmp/urls.txt

# Search each URL for old fact pattern
while read url; do
    content=$(curl -s "$url")
    if echo "$content" | grep -qE '(2013|2022|50\+|cũ-fact)'; then
        echo "$url"
        echo "$content" | grep -oE '(2013|2022|50\+|cũ-fact)' | sort -u | sed 's/^/  /'
    fi
done < /tmp/urls.txt
```

**Step 2: List all locations chứa fact cần update**

Group findings by source type:

| Location type | URLs / IDs | Old value | Action |
|---|---|---|---|
| Counter widgets | Homepage widget b72d1b2, footer 6d1f7f4 | "2013", "10 năm" | Update via MCP |
| Heading text | /about-us, /gioi-thieu | "Thành lập 2013" | Edit Elementor heading widget |
| Schema markup | Homepage LocalBusiness JSON-LD | `foundingDate: "2013-01-01"` | Edit HTML widget với `<script>` tag |
| Meta description | Rank Math meta `_rank_math_description` | "Phòng khám 10 năm..." | REST update |
| Footer copyright | Astra footer builder copyright element | "© 2014-2023" | Astra Customizer or footer builder |

**Step 3: Update ALL in 1 batch — không từng cái**

```bash
# Use MCP / REST API to update all detected locations in single session
# Don't update homepage today, schema tomorrow — drift period exposes inconsistency to users

# Track in audit log
echo "$(date): updated 2013→2014 in 5 locations" >> audit/brand_fact_updates.log
```

**Step 4: Verify post-update**

```bash
# Re-grep to confirm 0 remaining occurrences
while read url; do
    content=$(curl -s "$url?cb=$(date +%s)")  # cache-bust
    if echo "$content" | grep -qE '(2013|10 năm)'; then
        echo "STILL FOUND: $url"
    fi
done < /tmp/urls.txt
# Expect: no output (all updated)
```

**Step 5: Update content_reference.md với fact mới + mark cũ là OUTDATED**

```markdown
## Conflicts table

| Fact | V0 (old) | V1 (current) | Updated date | Reason |
|---|---|---|---|---|
| Founding year | 2013 | 2014 | 2026-05-13 | Source: business license cert |
| Team size | 10 | 12 chuyên môn + 8 hỗ trợ | 2026-05-13 | Q1 2026 hire batch |
```

### Cadence

Audit cross-page mỗi 3 tháng để catch drift. Common drift sources:
- WordPress auto-update plugin pushes new default schema
- Theme update overwrites Customizer settings
- AI-generated content (FOXAI, GPT writers) introduces incorrect facts
- Manual edit to 1 page without updating others

### Reusability

Same workflow applies cho any "fact that lives in multiple places":
- Pricing changes (homepage banner + product page + service page + footer + email signature)
- Contact info update (3 phone numbers? 2 addresses? Hours?)
- Award / certification addition
- Partner logo additions

The core principle: **update everywhere in 1 batch, verify cross-page, log to canonical SoT (content_reference.md)**.

## Cross-references

- [`templates/content-reference-template.md`](../templates/content-reference-template.md) — starter template (copy + fill in per project)
- [`templates/project-claude-md-template.md`](../templates/project-claude-md-template.md) — project CLAUDE.md template (different focus: WHERE/WHO of the project)
- [`workflows/session-distillation.md`](session-distillation.md) — when a project pattern recurs, promote to skill
- [`references/seo-checklist.md`](../references/seo-checklist.md) — Rank Math meta + Schema markup that should also reference the canonical facts
