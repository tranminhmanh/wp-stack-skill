# Code Snippets plugin — REST API + surgical edit workflow

The Code Snippets plugin (free, by Code Snippets Pro) exposes a clean REST API by default. Lets you list, fetch, edit, and update snippets via HTTP without touching wp-admin — useful for bulk audits, cross-site cleanup, and version-control workflows outside git.

## Endpoints

```
GET  /wp-json/code-snippets/v1/snippets             # list all snippets (id, name, type, scope, status)
GET  /wp-json/code-snippets/v1/snippets/<id>        # fetch full code + metadata
POST /wp-json/code-snippets/v1/snippets/<id>        # update existing snippet (PATCH-style, partial fields OK)
POST /wp-json/code-snippets/v1/snippets             # create new snippet
DELETE /wp-json/code-snippets/v1/snippets/<id>      # delete
```

Auth: Application Password Basic auth. The same App Password used for `/wp/v2/*` works here.

## Workflow — surgical edit (battle-tested)

Real use case: clean up a duplicate `og:image` emission across a site that had ~30 legacy snippets, some of which still hardcoded OG / favicon tags from before Rank Math was installed.

```bash
B64=$(printf 'admin:xxxx xxxx xxxx xxxx xxxx xxxx' | base64)
SITE=https://example.com

# 1. List all snippets — find candidates
curl -H "Authorization: Basic $B64" \
  "$SITE/wp-json/code-snippets/v1/snippets" \
  | jq '.[] | {id, name, scope, active}' \
  | head -30

# 2. Filter for snippets emitting og:image / favicon — patterns to clean up
curl -H "Authorization: Basic $B64" \
  "$SITE/wp-json/code-snippets/v1/snippets" \
  | jq '.[] | select(.code | test("og:image|twitter:image|favicon|logo-3fish"; "i")) | {id, name}'

# 3. Fetch full code of target snippet
curl -H "Authorization: Basic $B64" \
  "$SITE/wp-json/code-snippets/v1/snippets/42" \
  | jq -r '.code' > /tmp/snippet-42.php

# 4. Surgical edit (delete the offending block, keep the rest)
python3 /tmp/scrub-og.py /tmp/snippet-42.php > /tmp/snippet-42-fixed.php

# 5. POST update — re-eval immediately
NEW_CODE=$(jq -Rs . < /tmp/snippet-42-fixed.php)
curl -H "Authorization: Basic $B64" \
  -H "Content-Type: application/json" \
  -X POST "$SITE/wp-json/code-snippets/v1/snippets/42" \
  -d "{\"code\": $NEW_CODE}"
# → {"code_error": null, ...} on success

# 6. Verify frontend + purge cache
curl -s "$SITE/?cb=$(date +%s)" | grep -c 'og:image'
```

## `code_error: null` validation

The POST response includes `code_error`:
- `null` → PHP parsed successfully, snippet active immediately
- `"Parse error: ..."` → syntax error, snippet auto-deactivated to prevent fatal

Always check this field after a POST. Treat non-null as a failed update.

## Control-char JSON parse gotcha

Snippet `code` can contain literal newlines, tabs, NUL bytes (rare). Python's `json.loads()` in strict mode rejects these:

```python
import json
raw = open('response.json').read()

# ❌ WRONG — fails on multi-line PHP code with embedded control chars
data = json.loads(raw)

# ✅ RIGHT — strict=False allows raw control chars
data = json.loads(raw, strict=False)
```

`jq` handles this transparently — only matters when parsing in Python.

## Update body — minimum fields

```json
{
  "code": "<?php\n// ... your code ..."
}
```

Other fields (`name`, `desc`, `scope`, `tags`, `active`) preserve existing values if omitted. To toggle activation:

```bash
curl -H "Authorization: Basic $B64" \
  -X POST "$SITE/wp-json/code-snippets/v1/snippets/42" \
  -d '{"active": false}'
```

## When this REST API beats wp-admin

- **Bulk audit** across N snippets — grep code field for known anti-patterns (legacy hardcoded SEO tags, deprecated plugin function calls, broken short-codes)
- **Cross-site cleanup** — same patch across 5 sites via shell loop
- **Version control outside git** — export → modify → import flow
- **Surgical edit when wp-admin UI is too slow** — direct PHP edit + POST is faster than wp-admin's monaco-editor + save round-trip

## When wp-admin UI is better

- **Single-snippet creation** — clicking through the form is easier than crafting a POST body
- **Browser debug** — wp-admin's "Run Once" button lets you test scoped runs interactively
- **First-time install on a site** — get the lay of the land in the UI before automating

## Anti-patterns

❌ **Skipping `code_error` check** — POST returns 200 even when the code fails to parse. Always inspect the response field.

❌ **Re-uploading the entire snippet just to flip `active`** — sending only `{"active": false}` is sufficient and avoids accidentally clobbering recent in-UI edits.

❌ **Editing snippets that are part of a managed plugin** — if the user has Code Snippets Pro with sync from a private repo, your REST edits get overwritten on next sync. Coordinate with the user before bulk-editing.

❌ **Storing the snippet code in your project repo** — the code lives in the WP DB; your repo holds the patcher script, not the snippet source. Treat snippets as project memory in the WP DB, scripts as the version-controlled patcher.

## zsh command-substitution + UTF-8 response trap

When snippet `code` contains UTF-8 multibyte characters (Vietnamese diacritics, emojis, smart quotes, em-dashes), zsh command substitution `$(curl …)` may corrupt the response. Two reasons:

- zsh's word-splitting + globbing on `$(...)` output can mangle byte sequences interpreted as locale-mismatched characters
- Some shells (or terminal locale settings) re-interpret bytes through the active locale before storing in the variable, garbling multi-byte sequences

Result: parse it via `jq` from the variable → silently truncated or `"unicode escape"` errors.

```bash
# ❌ WRONG — works on ASCII-only responses, breaks on UTF-8 snippet code
RESPONSE=$(curl -s -H "Authorization: Basic $B64" "$SITE/wp-json/code-snippets/v1/snippets/13")
echo "$RESPONSE" | jq -r '.code'  # may be truncated, may show \uXXXX glitches
```

### Workaround — write to file, parse via Python

```bash
# ✅ RIGHT — bypass shell's variable handling entirely
curl -s -H "Authorization: Basic $B64" \
  "$SITE/wp-json/code-snippets/v1/snippets/13" \
  -o /tmp/snippet-13.json

python3 -c "
import json
data = json.loads(open('/tmp/snippet-13.json', encoding='utf-8').read())
print(data['code'])
" > /tmp/snippet-13.php
```

Python `open(..., encoding='utf-8')` + `json.loads()` (with `strict=False` if control chars are present) preserves bytes exactly.

### Alternative — bash with UTF-8 locale

If you're locked into bash + shell variables:

```bash
LC_ALL=en_US.UTF-8 \
RESPONSE=$(curl -s -H "Authorization: Basic $B64" "$SITE/wp-json/code-snippets/v1/snippets/13")
```

Less reliable than the file-route — locale variations across shells / terminal / SSH sessions add fragility. Default to the file-route for CRUD on snippet `code` containing non-ASCII.

### When this matters

- Snippet contains Vietnamese / Chinese / Korean / Japanese strings in PHP literals
- Snippet contains JavaScript with emoji or curly quotes
- Snippet contains author display name, brand slogan, or marketing copy in non-Latin script
- Pretty much any production snippet on a non-English locale site

When in doubt: file-route. The 1 extra command is cheap; debugging silent truncation later is not.

## Cross-references

- [`references/seo-checklist.md`](seo-checklist.md) "Duplicate OG/meta detection" — what to scan for in legacy snippets
- [`references/wp-abilities.md`](wp-abilities.md) — Application Password auth pattern
- [`workflows/bulk-content-automation.md`](../workflows/bulk-content-automation.md) — marker-based idempotency for cross-site cleanup
