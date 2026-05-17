# Workflow: Setup `claude mcp add` cho WordPress MCP server

End-to-end thiết lập connector từ Claude Code CLI tới 1 endpoint MCP của site WP. Dùng cho mỗi plugin MCP riêng (xem [`references/mcp-architecture.md`](../references/mcp-architecture.md) — 1 plugin = 1 endpoint = 1 connector).

## Khi nào dùng workflow này

✅ Site mới, chưa connect MCP từ phía Claude
✅ Đang debug "tool count gap" — site có nhiều plugin MCP nhưng chỉ 1 connector
✅ Muốn add Elementor MCP riêng song song với MCP Adapter mặc định
❌ Connector đã chạy OK — chỉ restart session là đủ

## Pre-requisites

| Kiểm tra | Cách verify |
|---|---|
| Plugin MCP active trên WP | `curl -u $U:$P https://<site>/wp-json/wp/v2/plugins \| jq '.[] \| select(.status=="active") \| select(.plugin \| contains("mcp"))'` |
| Endpoint route register | `curl https://<site>/wp-json/mcp \| jq '.routes \| keys'` — tìm `/mcp/<plugin>-server` |
| Application Password đã tạo | wp-admin → Users → Profile → Application Passwords (label rõ purpose) |
| `claude` CLI cài đặt | `claude --version` |

## Steps

### 1. Compute Basic auth header

```bash
# Username = WP login slug (KHÔNG phải App Password label!)
# App Password phải GIỮ space "xxxx xxxx ..." — đó là format gốc WP issue
B64=$(printf 'admin:xxxx xxxx xxxx xxxx xxxx xxxx' | base64)
echo "Authorization: Basic $B64"   # length should be ~52 chars
```

### 2. Add connector — argument order CRITICAL

```bash
# ✅ CORRECT — name + URL ở positional, --header CUỐI CÙNG
claude mcp add \
  -t http \
  -s user \
  acme-elementor \
  "https://example.com/wp-json/mcp/elementor-mcp-server" \
  -H "Authorization: Basic $B64"

# Output: "Added HTTP MCP server ... to user config / File modified: ~/.claude.json"
```

❌ **Anti-pattern**: `--header` đặt TRƯỚC positional args → CLI parser lỗi `missing required argument 'name'`. Lý do: header value chứa space + colon, parser nhầm tách positional khỏi header → mất `name`.

```bash
# ❌ WRONG — name không parse được
claude mcp add -t http -s user -H "Authorization: Basic $B64" name "URL"
# Error: missing required argument 'name'
```

### 3. Flag `--scope` chọn `local` / `user` / `project`

| Scope | Storage | Visible in |
|---|---|---|
| `local` (default) | Per-project trong `~/.claude.json` | Chỉ project hiện tại |
| `user` | Global trong `~/.claude.json` user config | Mọi project |
| `project` | `<project>/.mcp.json` (commit được) | Mọi clone của repo, cần workspace trust |

Khuyến nghị:
- **`-s user`** cho site WP cá nhân/team riêng — dùng được mọi nơi, không leak vào git
- **`-s project`** chỉ khi cả team cùng dùng cùng creds (filter qua git-secret hay env-substitution)
- Tránh `-s local` cho site dài hạn — phải re-add cho mỗi project mới

### 4. Verify connector connected

```bash
# Health check tất cả MCP server
claude mcp list
# Tìm dòng: "acme-elementor: <url> (HTTP) - ✓ Connected"

# Inspect 1 server
claude mcp get acme-elementor
# Output: scope, status, type, url, headers (Basic auth visible base64)
```

❌ Nếu `! Needs authentication` → check:
1. Username là login slug (admin/email-prefix), không phải display name hay App Password label
2. App Password copy đầy đủ với space
3. Site URL đúng `https://` (không `http`)
4. App Password chưa bị revoke

### 5. Restart session để load tool schemas

⚠️ **Tool schemas chỉ load lúc session init**. Sau khi add connector mới giữa session:
- `claude mcp list` báo Connected ✓
- Nhưng `mcp__<name>__*` tools KHÔNG xuất hiện trong tool list của session hiện tại

→ **Phải restart Claude Code session** để pickup tools mới.

Workaround tạm thời (nếu không restart được): dùng [`references/wp-abilities.md`](../references/wp-abilities.md) gọi REST trực tiếp.

### 6. Verify tools loaded sau restart

Trong session mới:
```
ToolSearch query: "+acme-elementor list-pages"
→ Should return mcp__acme-elementor__elementor-mcp-list-pages schema
```

Hoặc gọi thử ngay tool ergonomic:
```
mcp__<name>__elementor-mcp-detect-elementor-version()
→ {"elementor_version":"4.0.7","supports_atomic":true,...}
```

## Multi-plugin setup pattern

Site có nhiều plugin MCP → add nhiều connector cùng lúc:

```bash
B64=$(printf "$USER:$APP_PW" | base64)
SITE="https://example.com"

# 1 connector cho mỗi plugin endpoint
for endpoint in mcp-adapter-default-server elementor-mcp-server custom-plugin-server; do
  name="example-com-${endpoint%-server}"
  claude mcp add -t http -s user "$name" \
    "$SITE/wp-json/mcp/$endpoint" \
    -H "Authorization: Basic $B64"
done

claude mcp list
```

Naming convention: `<site-slug>-<plugin-shortname>` — ví dụ pattern: `acme-global` cho MCP Adapter core + `acme-elementor` cho elementor-mcp endpoint.

## Removing a connector

```bash
claude mcp remove "acme-elementor" -s user
```

Sau khi user revoke App Password phía WP, connector vẫn còn entry trong `~/.claude.json` nhưng auth fail. Remove cleanup luôn.

## Naming convention table

| Project type | Connector pattern |
|---|---|
| Single-site MCP Adapter only | `<site>-global` |
| Site có Elementor MCP riêng | `<site>-global` + `<site>-elementor` |
| Multi-site cùng team | `<site1>-elementor`, `<site2>-elementor`, ... |
| Test/dev | `<site>-staging` (riêng connector cho staging URL) |

## Troubleshooting

| Triệu chứng | Khả năng cao | Fix |
|---|---|---|
| `missing required argument 'name'` | `-H` đặt trước positional | Đặt `-H` cuối |
| `! Needs authentication` | Wrong username / wrong App Pw / revoked | Re-create App Pw, đảm bảo username = login slug |
| Connected ✓ nhưng tool không thấy | Session chưa restart | Exit + reopen Claude Code |
| Connected ✓, tool xuất hiện, gọi 404 | Connector trỏ sai endpoint | Verify URL trong `claude mcp get`, so với `/wp-json/mcp` route list |
| Random `connection closed` mid-session | Network blip / WP timeout | Restart session, kiểm tra LiteSpeed throttle, php-fpm worker |

## HTTP MCP vs stdio bridge — decision matrix (long-term standard: HTTP)

Two architectures for connecting Claude Code / Claude Desktop to WordPress MCP:

### Architecture A — stdio bridge (`mcp-wp-abilities` npm package)

The stdio bridge runs as a local Node.js process. It fetches the site's REST list of abilities at startup, then exposes each ability as its own MCP tool (e.g. 210 tools for a site with 210 abilities). Claude Code's tool registry sees them all as discrete `mcp__<site>__<ability>` tools.

```
Claude Code session
       │
       ▼  stdio
   Node.js process
       │
       ▼  HTTPS + Basic auth
   /wp-json/wp-abilities/v1/abilities (registry)
   /wp-json/wp-abilities/v1/abilities/<name>/run (execute)
```

### Architecture B — HTTP MCP (`mcp-adapter` plugin)

The site's `mcp-adapter` plugin self-hosts an MCP server at `/wp-json/mcp/<plugin>-server`. Claude Code's connector talks MCP protocol over HTTPS directly to that endpoint. The connector exposes only 3 META tools per server: `discover-abilities`, `get-ability-info`, `execute-ability`. To run a specific ability, Claude calls `execute-ability(name: "...", parameters: {...})`.

```
Claude Code session
       │
       ▼  HTTPS + MCP protocol + Basic auth
   /wp-json/mcp/<plugin>-server (MCP server)
       │
       ▼  in-process
   ability callback (registered via wp_register_ability)
```

### Comparison at scale (10+ sites)

| Criteria | stdio | HTTP |
|---|---|---|
| Setup time per site | 1–2 hours (mu-plugin patches, npm install, config) | **5 minutes** (`claude mcp add -t http -s user ...`) |
| Maintenance on plugin update | mu-plugin may break (whitelist + pagination + fuzzy match logic) | **Auto-detects** new abilities |
| Context tokens for 10 sites | ~2,100 tool schemas loaded | **30 schemas** (~70× lighter) |
| Failure surface | npm + Node + opcache + LSCache + MSYS path + Imunify quarantine + fuzzy regex | HTTP up/down + App Password expiry |
| Chat ergonomic | ✓ Autocomplete 210 tools by name | ✗ Need `discover_abilities` first to see options |
| Token cost of one tool call | Lower (direct tool dispatch) | Slightly higher (META tool overhead per call) |

### Decision rule (long-term standard going forward)

**Default: HTTP MCP for all new sites.** The 5-minute setup + auto-detect + 70× context savings + lower failure surface outweighs the autocomplete loss.

**Exception — choose stdio when**:
- Site is in heavy build-phase (rebuilding 50+ pages) and autocomplete saves real time
- You want to do tool-name-driven discovery in chat without an explicit `discover` step
- Pre-existing stdio investment + stable mu-plugin already deployed

**Migrate from stdio → HTTP when**:
- Site shifts from build phase to maintenance mode
- mu-plugin patches become a maintenance burden
- Adding multi-site (3+) — the context-token math tips heavily toward HTTP

### Compensate for HTTP's lost autocomplete — auto-dump ability catalog

The biggest UX cost of HTTP is "you can't see what abilities exist until you call `discover`". Compensate by dumping the ability list to `.ability-catalog.md` in the project root, ONCE after setup:

```bash
# Run after each new connector setup
mcp__<site>__mcp-adapter-discover-abilities | jq -r '.[] | "- `\(.name)` — \(.description)"' \
  > .ability-catalog.md
```

Reference `.ability-catalog.md` from the project's `CLAUDE.md`:
```markdown
## Available abilities

See `.ability-catalog.md` for the full list (auto-dumped after MCP setup).
```

Claude reads `CLAUDE.md` at session start → loads `.ability-catalog.md` once → knows every ability name without consuming a tool-list-load cycle. Best of both worlds: HTTP's efficiency + stdio's discoverability.

## Windows — edit `~/.claude.json` manually when no `claude` CLI on PATH

On macOS / Linux, `claude mcp add ...` is the standard install path. On Windows, the `claude` CLI may not be on PATH yet (especially fresh installs of Claude Desktop / Claude Code).

### Manual edit fallback — 3 scope locations

`~/.claude.json` is a single JSON file. Edit it directly. There are 3 places to add an MCP server config; pick by scope:

```jsonc
{
  // SCOPE 1: USER (global, applies to every project)
  "mcpServers": {
    "<site>-elementor": {
      "type": "http",
      "url": "https://<site>/wp-json/mcp/elementor-mcp-server",
      "headers": {
        "Authorization": "Basic <base64-of-user:app-pw>"
      }
    }
  },

  // SCOPE 2: PROJECT-LOCAL (only when you open a specific project dir)
  "projects": {
    "C:\\Users\\<user>\\path\\to\\project": {
      "mcpServers": {
        "<site>-elementor": {
          "type": "http",
          "url": "...",
          "headers": { ... }
        }
      }
    }
  }
}
```

```jsonc
// SCOPE 3: PROJECT-CHECKED-IN (commit to repo for team)
// File: <project>/.mcp.json
{
  "mcpServers": {
    "<site>-elementor": {
      "type": "http",
      "url": "...",
      "headers": { "Authorization": "Basic <base64>" }
    }
  }
}
```

### Putting the same config in all 3 → guaranteed pickup

If you want belt-and-suspenders certainty: set the same config in all 3 locations. Pickup priority is `project/.mcp.json` → `~/.claude.json` projects key → `~/.claude.json` global `mcpServers`. Having it in all three means at least one always fires.

### Verify after manual edit

```bash
# Windows PowerShell
Get-Content $env:USERPROFILE\.claude.json | ConvertFrom-Json | Select-Object -ExpandProperty mcpServers

# Or open Claude Code → check Settings → MCP Servers list
```

Then restart Claude Code session to load tool schemas (see "Restart session" step above).

## HTTP MCP transport requires `initialize` handshake (POST `tools/list` directly = 400)

When debugging or scripting against an HTTP MCP server directly (curl / Python urllib), you cannot just POST `tools/list` or `tools/call` and expect a response. The MCP protocol requires an initial handshake.

### Symptom

```bash
curl -X POST "https://<site>/wp-json/mcp/<plugin>-server" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
# → HTTP 400 Bad Request: missing session
```

### Correct flow — 2-step

**Step 1**: POST `initialize` to establish session:

```bash
curl -i -X POST "https://<site>/wp-json/mcp/<plugin>-server" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "Authorization: Basic <base64>" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {
      "protocolVersion": "2024-11-05",
      "capabilities": {},
      "clientInfo": {"name": "test-client", "version": "1.0.0"}
    }
  }'

# Response includes header:
#   Mcp-Session-Id: <session-uuid>
```

**Step 2**: Subsequent requests include the session header + `MCP-Protocol-Version` header:

```bash
curl -X POST "https://<site>/wp-json/mcp/<plugin>-server" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "Authorization: Basic <base64>" \
  -H "Mcp-Session-Id: <session-uuid>" \
  -H "MCP-Protocol-Version: 2024-11-05" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
```

### Required headers per request

| Header | Why |
|---|---|
| `Content-Type: application/json` | Standard JSON body |
| `Accept: application/json, text/event-stream` | MCP may return SSE stream for streaming responses |
| `Authorization: Basic <base64>` | App Password auth |
| `Mcp-Session-Id: <uuid>` | After initialize — links request to session |
| `MCP-Protocol-Version: 2024-11-05` | Pin protocol version (current as of writing) |

### When you might hit this

- Writing a custom MCP client (not Claude Code) against the WP MCP endpoint
- CI/CD smoke test that pings the MCP server to verify it's alive
- Debugging "why isn't Claude Code seeing my tools?" — run the handshake manually to isolate transport vs registration issues

### When you don't need this

When using `claude mcp add ... -t http ...` via the CLI, Claude Code handles the handshake internally. You only see this if you bypass the CLI.

## Liên quan

- [`references/mcp-architecture.md`](../references/mcp-architecture.md) — kiến trúc 1 plugin = 1 endpoint
- [`references/wp-abilities.md`](../references/wp-abilities.md) — fallback REST direct khi không restart được
- [`references/elementor-mcp.md`](../references/elementor-mcp.md) — connector config snippet
- [`references/pitfalls.md`](../references/pitfalls.md) "Application Password label ≠ username" — auth pitfall
