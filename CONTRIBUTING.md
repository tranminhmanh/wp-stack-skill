# Contributing to wp-stack-skill

Thanks for considering a contribution. The most valuable contributions are **new patterns extracted from production debugging** — not theoretical advice.

## What we accept

- ✅ **New pitfalls** with detection + fix (especially Elementor V4 quirks, MCP gotchas, CSS cascade traps)
- ✅ **New workflows** that save substantial time on a recurring task (≥30 minutes / use)
- ✅ **PHP recipes** for repeated operations (must be tested on a real site)
- ✅ **Stack updates** when plugins/themes release breaking changes
- ✅ **Translations** to other languages (currently English-primary; Vietnamese reference retained)
- ✅ **Corrections** — typos, broken links, outdated version pins

## What we don't accept

- ❌ Patterns from theory or untested code — must be production-verified
- ❌ Recommendations to switch the standard stack (Astra Free + Elementor Pro + ACF + msrbuilds/elementor-mcp). Suggest a fork instead.
- ❌ Marketing material, brand-specific content, or industry-specific patterns (B2B logistics, real estate, etc.) — keep universal
- ❌ Code that requires a paid plugin not already in the stack
- ❌ AI-generated content without production verification

## Workflow

### 1. Open an issue first (for non-trivial changes)

Discussion before code saves time. Open an issue describing:
- The pattern you observed
- The fix
- A code snippet that triggers the bug (if a pitfall)
- Which file(s) you'd update

For typo / broken-link fixes, skip straight to a PR.

### 2. Branch + commit

```bash
git checkout -b feat/my-pattern
# ... edit files ...
git add references/pitfalls.md
git commit -m "Add pitfall: <short title>"
```

**Commit message convention** (loose, but preferred):

| Prefix | When |
|---|---|
| `Add ...` | New file or new section |
| `Fix ...` | Correction to existing content |
| `Update ...` | Refresh existing content (version bump, expanded explanation) |
| `Remove ...` | Deletion |
| `Refactor ...` | Reorganize without changing meaning |

### 3. Open a PR

Use the PR template. The CI will check:
- Markdown linting (`markdownlint-cli2`)
- Internal link validity (`lychee`)
- PHP syntax (`php -l`) on snippets
- Shell linting (`shellcheck`) on bash examples

All checks must pass before merge.

### 4. Review

A maintainer will review within 72 hours (best effort). Expect questions about:
- **Production verification**: which site, which version of which plugin, which OS / hosting
- **Universality**: is this specific to one project or applicable to most WordPress sites?
- **Conflict**: does this contradict an existing pattern? Reconcile or delete the older one.

## Style guide

### Markdown

- One H1 per file (the title)
- H2 = major sections, H3 = subsections
- Code blocks tagged with language (` ```bash`, ` ```php`, ` ```css`)
- Use tables for matrices (decision matrix, gotcha lookup)
- Link cross-files with relative paths: `[text](../references/pitfalls.md)`

### Code snippets

- PHP: PSR-12-ish, but readability first
- Bash: `set -euo pipefail` for non-trivial scripts
- All code must include a comment header explaining the use case + reference to the workflow that uses it

### Pitfall entries

Use this template:

```markdown
### Title (concise, action-oriented)

**Symptom**: What the user sees (browser error, console output, missing class, etc.)

**Root cause**: One paragraph explaining why.

**Detection**: How to verify it's this bug (curl command, PHP snippet, DevTools check).

**Fix**: One or more options. Recommend one as primary.

**Lesson**: Optional one-line takeaway.
```

### Workflow files

Use this structure:

```markdown
# Workflow: <Title>

Brief intro explaining what this workflow does and the time it saves.

## When to apply

✅ Apply when: ...
❌ Don't apply when: ...

## Steps

### 1. ...
### 2. ...

## Common pitfalls

## Time saved (case study)

## Related
```

## Security

If your contribution involves a security-sensitive recipe (recovery scripts, credential handling, file permissions), please follow [`SECURITY.md`](SECURITY.md) before opening a public PR.

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
