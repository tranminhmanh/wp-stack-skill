# MCP architecture trên WordPress — 1 plugin = 1 endpoint = 1 connector

**Why this matters**: nếu hiểu sai kiến trúc, debug MCP 404 sẽ đi sai hướng (đoán plugin chưa cài, đoán protocol mismatch, v.v.) trong khi thực tế chỉ là client connect sai endpoint.

## Sự thật về cách plugin MCP đăng ký endpoint

Mỗi plugin WP có khả năng MCP **đăng ký endpoint MCP server riêng** — chúng KHÔNG share namespace qua một endpoint chung.

Khi cài `mcp-adapter` + `elementor-mcp` + `mcp-wp-capabilities` cùng lúc, WP route `/wp-json/mcp/` sẽ liệt kê **N endpoint độc lập**, mỗi endpoint chỉ expose abilities của plugin sở hữu nó:

```
/wp-json/mcp                           ← discovery (list các MCP server endpoints)
/wp-json/mcp/mcp-adapter-default-server ← chỉ expose core/* abilities
/wp-json/mcp/elementor-mcp-server      ← chỉ expose elementor-mcp/* abilities
/wp-json/mcp/<other-plugin-server>     ← v.v.
```

Khi MCP client (Claude.ai connector hoặc `claude mcp add`) connect vào **một** endpoint cụ thể, nó chỉ thấy abilities của endpoint đó. Không có "merge" tự động ở phía server.

## WP Abilities Framework — central registry, KHÔNG phải transport

Endpoint `/wp-json/wp-abilities/v1/abilities` **liệt kê toàn bộ ability đã register** của tất cả plugin (tổng `core/* + elementor-mcp/* + ...`) — đây là nơi để discover/introspect schema.

Tuy nhiên endpoint này chỉ là **registry**, KHÔNG phải MCP transport. MCP client không kết nối vào đây — họ connect vào `/mcp/<server-name>` của từng plugin riêng.

→ Có thể dùng abilities endpoint để **trực tiếp invoke ability qua REST** (bypass MCP), xem [`wp-abilities.md`](wp-abilities.md).

## Hệ quả thiết kế

### 1 connector = 1 endpoint

Nếu site có **N plugin MCP**, cần **N connector** từ phía Claude:

```
acme-global    → /mcp/mcp-adapter-default-server   (2 core tools)
acme-elementor → /mcp/elementor-mcp-server         (~110 elementor tools)
acme-<other>   → /mcp/<other-server>               (...)
```

❌ **Anti-pattern**: chỉ tạo 1 connector global "all-in-one" → mất N-1 bộ tool.

### Triệu chứng "tool count gap"

Nếu MCP đếm < số ability mong đợi (so với site khác đang chạy đủ), khả năng cao là **client thiếu connector**, không phải plugin chưa cài:

```
Site B: 48 elementor-mcp/* abilities REGISTERED + 0 tools VISIBLE → 1 connector thiếu
Site A: 48 elementor-mcp/* abilities REGISTERED + 110 tools VISIBLE → 2 connector OK
```

→ Khi gặp error `MCP error -32603: Failed to get ability details: 404`, thứ tự debug:
1. **Curl `/wp-json/wp-abilities/v1/abilities`** với App Password → confirm ability có register không
2. **Curl `/wp-json/mcp`** → list các MCP server endpoint hiện có
3. **`claude mcp list`** → xem connector đang trỏ endpoint nào
4. Nếu thiếu → add connector mới (xem [`workflows/claude-mcp-connector-setup.md`](../workflows/claude-mcp-connector-setup.md))

## Diagnosis matrix

| Triệu chứng | Lỗi thực sự ở đâu | Fix |
|---|---|---|
| Ability không trong `/wp-abilities/v1/abilities` list | Plugin chưa active hoặc không register | wp-admin → Plugins, hoặc curl `/wp-json/wp/v2/plugins` (cần auth) |
| Ability có trong list nhưng MCP tool 404 khi gọi | Connector đang trỏ sai endpoint | Add connector trỏ đúng endpoint của plugin sở hữu ability |
| Ability có, MCP tool xuất hiện, nhưng invoke trả error | Auth fail hoặc input format sai | Test direct REST: `GET /wp-abilities/v1/abilities/{name}/run?input[k]=v` |
| `claude mcp list` báo Connected nhưng tool không thấy trong session | Tool schemas chỉ load lúc session init | Restart session |

## Plugin → endpoint mapping observed

Quy ước đặt tên: `{plugin-slug}-server`. Tested 2026-05-10:

| Plugin | Endpoint | Abilities namespace |
|---|---|---|
| `mcp-adapter` v0.5.0 | `/mcp/mcp-adapter-default-server` | `core/*` |
| `elementor-mcp` v1.4.3 | `/mcp/elementor-mcp-server` | `elementor-mcp/*` |
| `mcp-wp-capabilities` v1.0.0 | (chưa verify) | (chưa verify) |

Khi user cài plugin MCP mới, kiểm tra `/wp-json/mcp` để biết tên endpoint mới register.

## MCP Adapter filters abilities by `meta.mcp.public:true`

A subtle but critical filtering rule: MCP Adapter v0.5.0 only exposes (via `discover-abilities` + `execute-ability`) abilities whose registration meta contains `'mcp' => ['public' => true]`. This is **independent** of `meta.show_in_rest:true` (which only controls REST registry visibility).

Symptom when the flag is missing:
```
mcp-adapter-execute-ability(name: "<plugin>/<ability>", parameters: {...})
→ Error: Ability "<plugin>/<ability>" is not exposed via MCP (mcp.public!=true)
```

The ability still works via direct REST `/wp-abilities/v1/abilities/<name>/run` — it's only blocked at the MCP transport layer. See [`wp-abilities.md`](wp-abilities.md) section "`meta.mcp.public:true` REQUIRED for MCP Adapter dispatch" for the helper pattern + fallback REST direct.

## Verifying endpoint mapping

```bash
# List tất cả MCP server endpoint trên site (cần admin auth)
curl -u "$USER:$APP_PW" "https://<site>/wp-json/mcp" | jq '.routes | keys'

# List abilities của 1 endpoint cụ thể (initialize MCP session, returns tool list)
# (phức tạp hơn vì cần MCP protocol handshake — dùng wp-abilities/v1/abilities thay)

# List toàn bộ abilities đã register, group theo namespace
curl -u "$USER:$APP_PW" "https://<site>/wp-json/wp-abilities/v1/abilities" \
  | jq -r '.[].name' | awk -F'/' '{print $1}' | sort | uniq -c
```

## REST registry pagination caveat — `per_page` cap on abilities list

WP-Abilities REST endpoint `/wp-json/wp-abilities/v1/abilities` default returns **100 abilities per page**. Sites với 100+ abilities registered (vd Astra + mcp-wp + plugin mcp + custom abilities = often 200+) → page 2 missing data when client doesn't paginate.

### Symptom

```bash
# Client list abilities, expects all
curl -u $U:$P "https://site/wp-json/wp-abilities/v1/abilities" | jq '. | length'
# 100 ← capped at default

# Reality: site has 194 abilities total
# Page 2 missing 94 abilities
```

### Common consequences

- npm stdio bridge (`mcp-wp-abilities`) hardcodes `per_page=100` → silent miss
- Custom MCP clients querying registry as setup-once not seeing full toolset
- Documentation generators dump incomplete ability catalog

### Fix

```bash
# Explicit per_page override (most servers allow up to 200-300)
curl -u $U:$P "https://site/wp-json/wp-abilities/v1/abilities?per_page=200"

# Or paginate
for page in 1 2 3; do
    curl -u $U:$P "https://site/wp-json/wp-abilities/v1/abilities?per_page=100&page=$page"
done | jq -s 'add | unique_by(.name) | sort_by(.name)'
```

### Verify total via different endpoint

MCP Adapter `discover-abilities` qua MCP protocol bypass REST pagination — returns all `mcp.public:true` abilities trong 1 call:

```javascript
mcp__site-global__mcp-adapter-discover-abilities()
// → all abilities với mcp.public:true (subset of registry, but reliable count)
```

Compare cả 2 sources để get full picture:
- REST `/wp-abilities/v1/abilities?per_page=200` = `show_in_rest:true` abilities (full schema)
- MCP `discover-abilities` = `mcp.public:true` abilities (basic info)
- Union = total registered

Em script `audit/cache_abilities.ps1` (xem [`workflows/litespeed-cache-mgmt.md`](../workflows/litespeed-cache-mgmt.md) cross-link OR `references/wp-abilities.md` cross-link) implements 2-pass fetch.

## stdio bridge vs HTTP MCP — architectural distinction

Beyond the "1 plugin = 1 endpoint = 1 connector" rule above, there's a separate architectural choice: HOW does the connector talk to the plugin? Two patterns coexist in the ecosystem:

### Pattern 1 — stdio bridge (npm package — local Node.js process)

```
Claude Code session
     │
     ▼  stdio (line-delimited JSON-RPC)
  Node.js process (e.g. mcp-wp-abilities)
     │
     ▼  HTTPS + Basic auth
  /wp-json/wp-abilities/v1/abilities (registry — fetches at startup)
  /wp-json/wp-abilities/v1/abilities/<name>/run (executes per call)
```

The Node.js process bridges stdio → HTTP. It fetches the WP abilities REST list once at startup, exposes EACH ability as its own MCP tool to Claude (so a site with 210 abilities → 210 MCP tools registered).

### Pattern 2 — HTTP MCP (plugin self-hosts MCP server)

```
Claude Code session
     │
     ▼  HTTPS + MCP protocol + Basic auth
  /wp-json/mcp/<plugin>-server (real MCP server inside WordPress)
     │
     ▼  in-process
  ability callback (registered via wp_register_ability)
```

The MCP adapter plugin (e.g. `mcp-adapter`) self-hosts a real MCP server at `/wp-json/mcp/<plugin>-server`. Claude Code's connector talks the MCP protocol DIRECTLY over HTTPS to that endpoint. No Node.js intermediary.

The connector exposes only **3 META tools** per server (`discover-abilities`, `get-ability-info`, `execute-ability`) regardless of how many abilities are registered. Running a specific ability is a call to `execute-ability(name: "...", parameters: {...})`.

### Why the distinction matters for context tokens

| Site count | stdio context cost | HTTP context cost |
|---|---|---|
| 1 site, 200 abilities | 200 tool schemas in tool registry | 3 tool schemas (regardless of ability count) |
| 10 sites, 200 abilities each | 2,000 tool schemas | 30 tool schemas |
| 50 sites | 10,000 tool schemas (impractical) | 150 tool schemas (fine) |

stdio gives autocomplete ergonomics but consumes context proportional to ability count. HTTP gives constant-context but requires `discover` step to learn the abilities.

For long-term standard: **HTTP scales, stdio doesn't**.

### Why this is independent of the "1 plugin = 1 endpoint" rule

Both patterns honor 1-plugin-1-endpoint:
- stdio bridge: each plugin's abilities REST surface fetched separately → separate stdio process per plugin (or per site).
- HTTP MCP: each plugin self-hosts its own `/mcp/<plugin>-server` endpoint → separate connector per plugin.

The choice between stdio/HTTP is orthogonal to which endpoint a connector targets.

### When stdio is the right choice

- Heavy build phase (rebuilding many pages) where autocomplete saves real time
- Single site, no plan to scale to multi-site
- Already-deployed stdio investment + working mu-plugin
- Want tool-name-driven discovery in chat without explicit `discover_abilities` step

### When HTTP is the right choice (default going forward)

- Multi-site automation (3+ sites)
- Fresh site setup with no prior MCP investment
- Want auto-detect of new abilities when plugin updates
- Maintenance mode (autocomplete value < context-cost trade-off)

### Migrating from stdio → HTTP

When a site shifts from build → maintenance, migration is straightforward:

1. Install `mcp-adapter` plugin on the WP site (if not already present)
2. `claude mcp add -t http -s user <site>-elementor https://<site>/wp-json/mcp/elementor-mcp-server -H "Authorization: Basic <b64>"`
3. Restart Claude Code session
4. Optionally remove the stdio connector + uninstall the npm bridge
5. (Optional) Auto-dump `.ability-catalog.md` to project root for discoverability (see [`workflows/claude-mcp-connector-setup.md`](../workflows/claude-mcp-connector-setup.md) "Compensate for HTTP's lost autocomplete")

The transition is non-destructive — both can coexist while you verify.

## Liên quan

- [`wp-abilities.md`](wp-abilities.md) — gọi ability trực tiếp qua REST (bypass MCP bridge)
- [`workflows/claude-mcp-connector-setup.md`](../workflows/claude-mcp-connector-setup.md) — `claude mcp add` CLI cho connector
- [`elementor-mcp.md`](elementor-mcp.md) — connector config cho elementor-mcp endpoint
