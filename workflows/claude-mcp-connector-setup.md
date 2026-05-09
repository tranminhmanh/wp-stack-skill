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

## Liên quan

- [`references/mcp-architecture.md`](../references/mcp-architecture.md) — kiến trúc 1 plugin = 1 endpoint
- [`references/wp-abilities.md`](../references/wp-abilities.md) — fallback REST direct khi không restart được
- [`references/elementor-mcp.md`](../references/elementor-mcp.md) — connector config snippet
- [`references/pitfalls.md`](../references/pitfalls.md) "Application Password label ≠ username" — auth pitfall
