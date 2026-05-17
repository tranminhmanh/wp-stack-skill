# Deploy rankmath-mcp wrapper plugin — canonical recipe + 4 distribution paths

> **Pin**: v2.0.9 là known-good (20 abilities, includes `write-mu-plugin` + `truncate-log` + `update-titles-option` + `read-debug-log` + Link Genius wrap + Meta CRUD + Redirections). Source: a private wrapper-plugin audit folder (`rankmath-mcp-2.0.9.zip`). Cross-link [`build-mcp-wrapper-plugin.md`](build-mcp-wrapper-plugin.md) cho generic wrapper concepts.

## When to deploy

Site cần wrap Rank Math abilities thành MCP-discoverable tools khi:

- Rank Math Link Genius backend (orphan posts, SEO score, link map) — KHÔNG có sẵn ability core
- Bulk meta read/write cho nhiều posts trong 1 call
- Direct SQL cho "missing focus keyword" — không recursive REST
- Redirections CRUD qua MCP (create/list/delete)
- Debug PHP error_log từ remote (read-debug-log)
- Direct option write bypass Rank Math broken endpoints (`update-titles-option`)
- File ops cho MU-plugin deploy + log truncate (v2.0.9+)

→ Nếu không cần các tính năng này, skip — site chạy Rank Math core đủ.

## 4 distribution paths

Mỗi project chọn path phù hợp với operational constraint. Cùng codebase, khác cách ship.

### Path A — Plugin zip + wp-admin upload (DEFAULT — easiest)

**When**: project có wp-admin access full + agent có thể guide user upload manual.

**Reference sites**: 2 inherited production sites (B2B medical + B2B retail).

**Steps**:

```bash
# 1. Build zip locally với forward-slash separator (WP plugin upload rejects backslashes on cross-platform zips)
python3 -c "
import zipfile, os
with zipfile.ZipFile('rankmath-mcp-2.0.9.zip', 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
    for root, _, files in os.walk('rankmath-mcp'):
        for file in files:
            if file.startswith('.'): continue
            full = os.path.join(root, file)
            arc = os.path.relpath(full, '.').replace(os.sep, '/')
            zf.write(full, arc)
"

# 2. User upload qua wp-admin
# Plugins → Add New → Upload Plugin → choose .zip → Install Now → Replace current → Activate

# 3. Agent verify
curl -u user:app_pw https://site/wp-json/rankmath-mcp/v1/debug
# → plugin_version: "2.0.9", 20 abilities registered
```

**Pros**:
- Zero hosting requirements (chỉ cần wp-admin)
- Standard plugin lifecycle (deactivate/activate normal)
- Visible trong Plugins list, user control
- Plugin update protected từ overwrite (folder owned bởi Plugins)

**Cons**:
- Manual upload step (5 phút overhead lần đầu)
- Phải re-upload mỗi version update
- Tạo plugin folder mới (vd `<site-slug>-1/rankmath-mcp`) nếu WP detect existing → cần "Replace current"

### Path B — MU-plugin bridge (existing core plugin, expose qua custom server)

**When**: site đã có Rank Math + WordPress Abilities API plugin (mcp-adapter v0.5+) installed but **chưa expose abilities qua MCP server** — agent muốn add MCP layer mà không touch plugin core hoặc install new plugin.

**Reference sites**: a shipping-focused production site (MU-plugin path `wp-content/mu-plugins/<site>-rankmath-mcp-server.php`).

**Steps**:

```php
<?php
// wp-content/mu-plugins/<site>-rankmath-mcp-server.php
/**
 * Plugin Name: <Site> Rank Math MCP Server
 * Description: Expose rankmath-mcp/* abilities (or rank-math/* if upstream provides) as dedicated MCP server endpoint
 * Version: 1.0
 */
if (!defined('ABSPATH')) exit;

add_action('mcp_adapter_init', function ($mcp_adapter) {
    // Pattern A — Dynamic discovery (RECOMMENDED — auto-pick new abilities)
    $abilities = wp_get_abilities();
    $rm_abilities = [];
    foreach ($abilities as $ability) {
        $name = is_object($ability) ? $ability->get_name() : ($ability['name'] ?? '');
        if (strpos($name, 'rankmath-mcp/') === 0) {
            $rm_abilities[] = $name;
        }
    }

    if (empty($rm_abilities)) {
        error_log('[<site> RM-MCP] No rankmath-mcp/* abilities registered yet');
        return;
    }

    $result = $mcp_adapter->create_server(
        '<site>-rankmath-mcp-server',                  // server_id
        'mcp',                                          // route_namespace
        '<site>-rankmath-mcp-server',                  // route
        '<Site> Rank Math MCP Server',                 // server_name
        'Expose Rank Math SEO data + ops via MCP',     // description
        '1.0',                                          // version
        array(\WP\MCP\Transport\HttpTransport::class), // transports
        null, null,
        $rm_abilities,                                  // tools (dynamic!)
        array(), array(), null
    );
}, 20);
```

**Pros**:
- KHÔNG cần install rankmath-mcp plugin separately (if upstream `rank-math` itself registers abilities) — chỉ build MCP server bridge
- MU-plugin survives plugin updates (luôn auto-load)
- Có thể custom server name (vd `<site>-rankmath-mcp-server` instead of generic)

**Cons**:
- KHÔNG có abilities mới — chỉ bridge existing. Cần Path A nếu muốn `write-mu-plugin` + `truncate-log` v2.0.9.
- MU-plugin không hiện trong Plugins UI → user khó manage
- Manual deploy MU-plugin file (cPanel File Manager hoặc Path D zero-touch)

**Pattern verification**:

```bash
# After deploy, MU-plugin tự load — no activation needed
curl -u user:app_pw https://site/wp-json/mcp/<site>-rankmath-mcp-server
# → MCP server response
```

### Path C — Custom-namespace fork (avoid conflict với existing plugin)

**When**: site đã có rankmath-mcp v2.0.x từ project khác installed, agent muốn deploy version riêng KHÔNG conflict (different abilities, different schema). Hoặc client muốn brand riêng (vd `<client>-rankmath-mcp`).

**Reference sites**: an events-focused production site (`<client>-rankmath-mcp` namespace `<client>-rankmath-mcp/v1`).

**Steps**:

1. Fork rankmath-mcp source from your private wrapper-plugin audit folder.
2. Rename:
   - Plugin folder `rankmath-mcp/` → `<client>-rankmath-mcp/`
   - Main file `rankmath-mcp.php` → `<client>-rankmath-mcp.php`
   - Plugin header: `Plugin Name` + `Plugin URI` + `Text Domain`
   - Ability prefix `rankmath-mcp/` → `<client>-rankmath-mcp/` (vd `<client>-rankmath-mcp/list-posts`)
   - REST namespace `rankmath-mcp/v1` → `<client>-rankmath-mcp/v1`
   - Helper functions `rmcp_*` → `<prefix>_*` (avoid collision)
3. Build zip + Path A upload flow.

**Pros**:
- KHÔNG conflict với rankmath-mcp default version
- Client-specific branding/scope
- Independent versioning

**Cons**:
- Maintenance burden: phải re-fork mỗi version upgrade từ canonical
- 2 plugins running cùng lúc = +N abilities trùng functionality
- Schema/behavior drift theo thời gian

### Path D — Merged single-endpoint (advanced, post-pain)

**When**: site đã có multi-MCP-endpoint topology + đang chịu stdio bridge disconnect / connector slot scarcity. Migrate sang 1 endpoint duy nhất.

**Reference sites**: a B2B wholesale site (`<site>-all` endpoint với 212 tools).

**Steps**: Xem [`mcp-architecture.md`](../references/mcp-architecture.md) §"Merged single-endpoint (ALTERNATIVE pattern)".

→ rankmath-mcp abilities là 1 subset trong merged endpoint, không cần ship riêng.

**Trade-off**: details trong `mcp-architecture.md` table. Recommend ONLY khi project đã rõ pain với default multi-endpoint.

---

## Decision tree — which path?

```
Bắt đầu: Project cần rankmath-mcp abilities?
├─ Không → Skip, dùng Rank Math core qua wp-admin
└─ Có
   ├─ Project có wp-admin upload access bình thường?
   │  ├─ Có
   │  │  ├─ Site đã có rankmath-mcp version khác?
   │  │  │  ├─ Có → Path C (custom namespace fork)
   │  │  │  └─ Không → ✅ Path A (plugin zip — DEFAULT)
   │  │  └─ (continue)
   │  └─ Không (chỉ có SFTP/cPanel) → Path B (MU-plugin) OR Path A via cPanel File Manager
   ├─ Site đã có multi-MCP pain (stdio disconnect frequent)?
   │  └─ Có → Path D (merged endpoint)
   └─ Site muốn brand riêng abilities?
      └─ Path C (custom namespace fork)
```

## Path A details — zero-touch upgrade post-v2.0.9

Sau khi user upload v2.0.9 lần đầu, future upgrades zero-touch qua abilities mới:

```bash
# 1. Build v2.0.10+ zip locally
# 2. Encode plugin source file base64
python3 -c "import base64; print(base64.b64encode(open('v2.0.10/rankmath-mcp.php','rb').read()).decode())" > /tmp/payload.b64

# 3. Em không có upload abilities cho plugins/ folder (security by design)
# → Vẫn cần Path A manual upload cho version upgrade
# → BUT em CÓ THỂ deploy MU-plugin patches + truncate logs zero-touch
```

→ **Conclusion**: v2.0.9 unlock zero-touch MU-plugin deploy + log management, KHÔNG unlock plugin folder write. Plugin update vẫn manual wp-admin.

## Verify after deploy (4 paths đều dùng)

```bash
# 1. Plugin loaded?
curl -u user:app_pw https://site/wp-json/rankmath-mcp/v1/debug
→ plugin_version: "2.0.9", abilities count

# 2. Abilities discoverable?
curl -u user:app_pw https://site/wp-json/wp-abilities/v1/abilities | jq '[.[] | select(.name | startswith("rankmath-mcp/"))] | length'
→ 20 (or namespace count nếu Path C)

# 3. Abilities executable qua MCP Adapter?
# (Requires meta.mcp.public:true — present in v2.0.5+, xem `references/mcp-architecture.md`)
curl -u user:app_pw https://site/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}' | jq '.result.tools[] | .name' | grep rankmath-mcp
→ 20 tool names listed

# 4. End-to-end ability call
curl -u user:app_pw "https://site/wp-json/wp-abilities/v1/abilities/rankmath-mcp/ping/run?input[dummy]=1"
→ {"success":true,"pong":true,"version":"2.0.9"}
```

## Common gotchas

| Gotcha | Symptom | Fix |
|---|---|---|
| Plugin upload "Replace current" missed | 2 folders: `rankmath-mcp/` + `<site-slug>-1/rankmath-mcp/` | Deactivate old, delete folder, only new active |
| MCP Adapter cache stale | New abilities not visible qua `mcp-adapter-execute-ability` | Wait 60s (opcache TTL) hoặc deactivate/reactivate MCP Adapter plugin |
| PHP opcache transient fatal | HTTP 500 `is_numeric() 1 arg vs 3 given` ngay sau upload | Wait 60-90s + retest (xem `troubleshooting.md` §3) |
| LSC drop-in fatal blocks activate | "Critical error" trong wp-admin Plugins | Disable LSC Object Cache (xem `pitfalls.md` LiteSpeed section) |
| Application Password auth REST OK, wp-admin 302 | Curl wp-admin trả 302 → tưởng broken | Expected behavior (xem `wp-abilities.md` §App Password scope) |

## Cross-references

| Topic | See |
|---|---|
| Generic wrapper plugin design | [`build-mcp-wrapper-plugin.md`](build-mcp-wrapper-plugin.md) |
| MCP architecture (1 vs N endpoints) | [`../references/mcp-architecture.md`](../references/mcp-architecture.md) |
| MU-plugin patterns | [`../references/mu-plugin-patterns.md`](../references/mu-plugin-patterns.md) |
| Rank Math REST endpoints | [`../references/rankmath.md`](../references/rankmath.md) |
| WP Abilities API + App Password | [`../references/wp-abilities.md`](../references/wp-abilities.md) |
| PHP opcache transient window | [`../references/troubleshooting.md`](../references/troubleshooting.md) §3 |
| Cross-site naming convention | (this file §"Project naming") |

## Project naming convention (recommendation — not yet enforced)

Khi setup connector trong Claude Code, naming pattern recommended:

```
<site-slug>-<purpose>
  vd <site-A>-com-elementor
     <site-A>-com-global
     <site-B>-rankmath
     <site-C>-vn-elementor
```

Rule:
- Lowercase, hyphen-separator
- TLD đầu cho disambiguation (`.com`, `.vn`)
- Purpose: `global` (default mcp-adapter-default-server) | `elementor` | `rankmath` | `astra` | `all` (merged endpoint)
- Tránh project-specific noise (vd `<long-business-name>-elementor` quá long)

Skip nếu inherited connector name từ user (don't rename their setup).
