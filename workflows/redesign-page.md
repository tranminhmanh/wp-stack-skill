# Workflow: Redesigning a live page — 5-state marking system

When restyling / restructuring an existing page that already has user content (intro paragraphs, service descriptions, testimonials, FAQ, founder bios), preserve the original voice. The fastest path — "remove old container, build fresh" — destroys content the user wrote, costs trust, and is hard to undo.

This workflow forces an explicit decision per existing widget BEFORE any write op.

## When to use

✅ Page has been live + has hand-written content (intro, FAQ, case studies, bios)
✅ User asks for a "design refresh" / "modernize" / "make it more conversion-focused"
✅ Pre-redesign content quality is uneven (some good, some placeholder, some generic)
❌ Page is a brand-new draft → no need for marking, just build
❌ Page is auto-generated content (search archive, blog hub) → restructure freely

## Why 5 states (not 2)

A naive workflow has only KEEP vs REPLACE. That's too coarse — it forces an all-or-nothing choice on each widget. The 5-state system maps to the natural decisions you'd make if reviewing the content with the user:

| State | Definition | When to apply |
|---|---|---|
| **KEEP** | Widget stays at current position with current style | Already meets brand standards, no improvement obvious |
| **MOVE** | Widget moves to a new section, restyled per design tokens | Content is good, layout / visual style is dated |
| **ENHANCE** | Widget moves + content is upgraded (preserves voice + adds detail) | Content is good but thin / incomplete; user voice is valuable |
| **REPLACE** | Widget is recreated from scratch (new content) | ONLY when content is vague + has HTML errors + <30 words. Always confirm with user before replacing |
| **REMOVE** | Widget is deleted | Empty container, placeholder, demo image, broken embed |

## The 3-phase process

### Phase 1 — Audit existing widgets

List EVERY widget on the page that has content (text-editor, heading, icon-list, testimonial, accordion, image, button). For each, record:
- Element ID
- Widget type
- First 30 words of content (to identify the widget at a glance)
- Visual quality assessment (good / dated / broken)

```python
# Pseudocode — call get-page-structure, walk for content widgets
structure = mcp.get_page_structure(post_id=N)
audit = []
def walk(elements, path=""):
    for i, el in enumerate(elements):
        wt = el.get('widgetType')
        if wt in ('text-editor', 'heading', 'icon-list', 'testimonial', 'image', 'button', 'accordion', 'tabs', 'toggle'):
            settings = el.get('settings', {})
            preview = (settings.get('title') or settings.get('editor') or settings.get('text') or '')[:60]
            audit.append({
                'id': el['id'],
                'type': wt,
                'path': f"{path}/[{i}]",
                'preview': preview,
            })
        walk(el.get('elements', []), f"{path}/[{i}]")
walk(structure['elements'])
```

Output: a list like
```
- 4a8f29c heading "Dịch vụ khám phụ khoa toàn diện"
- 7c1d83b text-editor "Phòng khám X cung cấp các dịch vụ khám phụ khoa định kỳ..."
- 8e5f12a icon-list "5 lý do chọn chúng tôi: bác sĩ giỏi, thiết bị hiện đại..."
- ...
```

### Phase 2 — Mark every widget (KEEP/MOVE/ENHANCE/REPLACE/REMOVE)

For each widget from Phase 1, write the state + reason. This is the **only** phase where decisions happen — Phase 3 is just execution.

| Widget ID | Type | Preview | State | Reason |
|---|---|---|---|---|
| 4a8f29c | heading | "Dịch vụ khám phụ khoa toàn diện" | KEEP | Strong brand-aligned heading |
| 7c1d83b | text-editor | "Phòng khám X cung cấp..." | ENHANCE | Solid intro paragraph, but 50 words — pad to 120 with one anchor stat + reassurance line |
| 8e5f12a | icon-list | "5 lý do chọn chúng tôi..." | MOVE | Content good, but list-with-bullet style is dated → move into a 3×2 card grid section |
| f4d12bc | image | (placeholder demo image) | REMOVE | Demo content from page builder template, not real |
| 91a8d3e | text-editor | "Lorem ipsum dolor sit amet..." | REPLACE | Lorem ipsum, no real content → recreate (confirm with user first) |
| 2b7c5a9 | accordion | "What is..." (English placeholder) | REPLACE | English placeholder text on a Vietnamese site → user-confirm + recreate |

⚠️ **Save this table to a file** — `redesign-plan-page-N.md` — so you can resume after a session break and so the user can review.

⚠️ **REPLACE always requires user confirmation** when the original is non-empty + non-placeholder. Surface every REPLACE in a single message: "I plan to replace these N widgets with new content. Their current content is X. Approve / push back per item?"

### Phase 3 — Execute

Apply the marking decisions:
- KEEP → skip
- MOVE → `move-element` to the new section's element ID + `update-element` with new style settings
- ENHANCE → `move-element` + `update-element` content (preserve original phrases + extend)
- REPLACE → only after user approval. Then `remove-element` + `add-*` fresh widget. Or update settings in place.
- REMOVE → `remove-element`

⚠️ **Forbidden**: do NOT call `remove-element` on a top-level container that contains text-editor / heading children that have NOT been moved out first. Order of operations:
1. For each child in the to-be-removed container: if it's marked KEEP / MOVE / ENHANCE → move it OUT first
2. Only after the container is empty (or only has REMOVE-marked children): remove the container

## Why this matters — real-world cost of skipping

Without 5-state marking, the fastest path is:
1. Remove the old container
2. Build the new design from scratch
3. Move on

The user sees the result and discovers content they wrote (custom intro, hand-typed FAQ answers, founder bio with specific anecdote) is gone. They lose trust. They ask "did you preserve the original?" — which means they have to manually compare the old DB backup vs the new state to find what's missing. This is hours of work + anxiety.

With 5-state marking, every original widget has an explicit decision recorded. If something is missing later, you can grep the marking file for its ID and see the rationale. Either it was correctly REMOVE/REPLACE → user approved → no surprise; or there's a bug → easy to spot.

## Anti-patterns

❌ **Implicit REMOVE**: building the new structure and "letting the old container fall off" via Elementor's flex layout. The container may still exist in `_elementor_data` but never render — confusing later.

❌ **REPLACE without confirmation** on widgets with >50 words of original content. User wrote it. Even if it's awkward English, it's THEIR voice.

❌ **MOVE without restyle**: moving a widget to a new section but keeping the original visual properties (padding, color, typography) → looks out of place. Always combine MOVE with `update-element` to apply the new design tokens.

❌ **Verbal marking instead of file**: saying "I'll keep that one" in chat without writing it down. Lose the chat → lose the rationale. Always commit the marking table to a file.

## Resumable redesign — handling session breaks

If the redesign takes >1 session, the file-based marking table makes resumption clean:
1. Read the file at session start.
2. List which widget IDs have already been moved (compare the original markings against the current `get-page-structure`).
3. Resume from the first un-executed marking.

Mark each row as you execute:
```markdown
| Widget ID | Type | State | Reason | Status |
|---|---|---|---|---|
| 4a8f29c | heading | KEEP | ... | — |
| 7c1d83b | text-editor | ENHANCE | ... | ✅ done 2026-05-10 |
| 8e5f12a | icon-list | MOVE | ... | ⏳ in progress |
```

## Cross-references

- [`references/elementor-mcp.md`](../references/elementor-mcp.md) — `move-element`, `update-element`, `update-widget` schemas
- [`references/pitfalls.md`](../references/pitfalls.md) — `_elementor_edit_mode`, container hierarchy traps
- [`workflows/clone-transform-pattern.md`](clone-transform-pattern.md) — bulk-build alternative when N similar pages need redesign
- [`workflows/ui-verification.md`](ui-verification.md) — verify-don't-assume after redesign
