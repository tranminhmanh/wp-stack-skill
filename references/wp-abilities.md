# WP Abilities Framework — gọi ability trực tiếp qua REST

**When to use**: khi MCP bridge fail (404, missing connector, session không restart được, hoặc trong CI script không có MCP runtime).

WP Abilities Framework expose mọi ability đã register qua endpoint REST chuẩn, có thể invoke bằng `curl` + Application Password Basic auth — **không cần MCP protocol**.

## Endpoints

```
GET  /wp-json/wp-abilities/v1/abilities                    # list all abilities (registry)
GET  /wp-json/wp-abilities/v1/abilities/{namespace}/{name} # get schema cho 1 ability
GET  /wp-json/wp-abilities/v1/abilities/{namespace}/{name}/run?input[k]=v   # invoke (readonly)
POST /wp-json/wp-abilities/v1/abilities/{namespace}/{name}/run             # invoke (write)
     body: {"input": {...}}
```

## Method = function of `meta.annotations.readonly`

Ability metadata có flag `readonly`:
```json
{
  "name": "elementor-mcp/list-pages",
  "meta": {
    "annotations": {
      "readonly": true,    // → GET method, args qua query string
      "destructive": false,
      "idempotent": true
    }
  }
}
```

| `readonly` | HTTP method | Args | Sai method trả |
|---|---|---|---|
| `true` | GET | `?input[k]=v` query string | `405 rest_ability_invalid_method` |
| `false` | POST | body `{"input": {...}}` JSON | (write op chỉ chấp nhận POST) |

**Lưu ý**: ability mặc định bị reject GET nếu chưa explicit `readonly: true` — vì server không biết có safe để cache hay không.

## Input format trap — phải nest dưới `input`

Sai format common:
```bash
# ❌ args ở top-level → "input không phải là loại của object"
curl ".../list-pages/run?post_type=page"

# ❌ JSON-encoded args qua query → server không decode
curl ".../list-pages/run?input=%7B%22post_type%22%3A%22page%22%7D"

# ✅ PHP-style nested array notation
curl ".../list-pages/run?input%5Bpost_type%5D=page&input%5Bstatus%5D=publish"
# = ?input[post_type]=page&input[status]=publish
```

PHP `parse_str` decode `input[k]=v` thành `$_GET['input'] = ['k' => 'v']` — đó là cách WP REST nhận input cho ability.

## Authentication

Application Password (xem [`pitfalls.md`](pitfalls.md) "Application Password label ≠ username"):

```bash
# Username = login slug (NOT App Password label)
# App Password chuẩn dạng "xxxx xxxx xxxx xxxx xxxx xxxx" (24 chars, có space)
B64=$(printf 'admin:xxxx xxxx xxxx xxxx xxxx xxxx' | base64)

curl -H "Authorization: Basic $B64" \
  "https://<site>/wp-json/wp-abilities/v1/abilities/{ability}/run?input[k]=v"
```

Hoặc inline `-u user:pass` (curl tự encode):
```bash
curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  "https://<site>/wp-json/wp-abilities/v1/abilities/elementor-mcp/list-pages/run?input[post_type]=page"
```

## Helper script (Python stdlib only)

Ready-to-copy template. Đặt ở `<project>/scripts/wp_ability.py` hoặc dùng template từ `templates/snippets/wp-ability-helper.py` (chưa tạo, làm khi cần).

```python
import base64, json, os, urllib.parse, urllib.request

SITE = "https://example.com"
USERNAME = os.environ["WP_USER"]
APP_PW = os.environ["WP_APP_PW"]   # NEVER hardcode in source

def call(ability: str, input_data: dict | None = None, *, write: bool = False):
    url = f"{SITE}/wp-json/wp-abilities/v1/abilities/{ability}/run"
    headers = {
        "Authorization": "Basic " + base64.b64encode(f"{USERNAME}:{APP_PW}".encode()).decode(),
        "Accept": "application/json",
    }
    if write:
        body = json.dumps({"input": input_data or {}}, ensure_ascii=False).encode()
        headers["Content-Type"] = "application/json"
        req = urllib.request.Request(url, data=body, headers=headers, method="POST")
    else:
        if input_data:
            qs = urllib.parse.urlencode({f"input[{k}]": v for k, v in input_data.items()})
            url = f"{url}?{qs}"
        req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            return json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        return {"_error": e.code, "_body": json.loads(e.read().decode("utf-8"))}

# Usage
pages = call("elementor-mcp/list-pages", {"post_type": "page", "status": "publish"})
structure = call("elementor-mcp/get-page-structure", {"post_id": 1234})
# Write op (auto-detect via schema or pass write=True)
result = call("elementor-mcp/update-widget", {
    "post_id": 1234,
    "element_id": "abc1234",
    "settings": {"header_size": "h1"}
}, write=True)
```

## Discovery commands

```bash
# List tất cả ability nhóm theo namespace
curl -u "$U:$P" "$SITE/wp-json/wp-abilities/v1/abilities" \
  | jq -r '.[].name' | awk -F'/' '{print $1}' | sort | uniq -c

# List ability của 1 namespace
curl -u "$U:$P" "$SITE/wp-json/wp-abilities/v1/abilities" \
  | jq -r '.[] | select(.name | startswith("elementor-mcp/")) | .name'

# Schema 1 ability
curl -u "$U:$P" "$SITE/wp-json/wp-abilities/v1/abilities/elementor-mcp/list-pages" \
  | jq '{name, input_schema, output_schema, meta: .meta.annotations}'
```

## When direct REST > MCP bridge

| Tình huống | Khuyến nghị |
|---|---|
| Bridge connector chưa setup, không restart được session | Direct REST |
| CI / cron / non-Claude environment | Direct REST |
| One-off bulk script (e.g. update 89 post meta) | Direct REST + parallel curl |
| Interactive Claude conversation, có MCP bridge OK | MCP tool (ergonomic hơn) |
| Cần feedback realtime từng bước | MCP tool |
| Muốn pattern reproducible, persistable | Direct REST script |

## Security guardrails

- **Không hardcode App Password trong file commit**: dùng env var
- **Revoke App Password sau khi xong**: wp-admin → Profile → Application Passwords → Revoke
- **Set short label rõ purpose**: `claude-audit-2026-05-10` không phải `password1` — để dễ track + revoke đúng cái
- **Đối với production**: có thể tạo riêng user `wp-claude-readonly` với role `editor` thay vì admin → giới hạn scope abilities có thể call

## Gotchas

### `?input[]=` vs `?input[k]=v`

Một số ability có `input_schema` không phải `type: object` mà là `type: array`. Khi đó:
```bash
?input[]=value1&input[]=value2   # for array input
?input[k]=v                       # for object input
```
Đọc schema trước khi gọi để biết structure.

### Output stream lớn

Một số ability (e.g. `get-page-structure`) trả JSON 50-500KB. Pipe qua `jq` hoặc save file:
```bash
curl ... > /tmp/structure.json
jq '.structure[].elType' /tmp/structure.json
```

### Idempotency

`meta.annotations.idempotent: true` → an toàn retry. `false` → cần track request id để tránh duplicate (ví dụ `create-page`).

## CPT không expose REST → `/wp/v2/search` subtype workaround

**Symptom**: một số Custom Post Types (vd `rank_math_locations` từ Rank Math Local SEO, hoặc CPT của plugin tự register) **không expose REST namespace** mặc định:

```bash
GET /wp-json/wp/v2/locations/4992
# → 404 "rest_no_route"
```

→ Không thể read content, update meta, audit programmatically qua WP REST direct.

**Workaround**: dùng generic endpoint `/wp/v2/search` với `subtype` parameter — endpoint này hoạt động cho **mọi** post type registered, kể cả những CPT không có route REST riêng.

```bash
# Find post_id của bất kỳ post type nào theo keyword
GET /wp-json/wp/v2/search?search=quan%2011&_fields=id,title,subtype,url

# Response includes ALL matching post types:
[
  {"id": 4992, "subtype": "rank_math_locations", "url": ".../q11/...", "title": "..."},
  {"id": 5012, "subtype": "page",                "url": ".../about/",   "title": "..."}
]
```

`subtype` field cho biết post type thực sự — filter sau theo CPT cần.

```python
import urllib.request, urllib.parse, json

def find_by_keyword(site, keyword, subtype=None, auth_header=None):
    qs = urllib.parse.urlencode({"search": keyword, "_fields": "id,title,subtype,url", "per_page": 100})
    url = f"{site}/wp-json/wp/v2/search?{qs}"
    req = urllib.request.Request(url)
    if auth_header:
        req.add_header("Authorization", auth_header)
    results = json.loads(urllib.request.urlopen(req).read())
    if subtype:
        results = [r for r in results if r["subtype"] == subtype]
    return results

# Usage
locations = find_by_keyword("https://<site>", "quan", subtype="rank_math_locations")
# [{"id": 4992, ...}, {"id": 4989, ...}]
```

**Limitations**:
- Read-only via search endpoint — finds the post_id but can't update meta directly through `/wp/v2/search`
- For meta UPDATE on a CPT without REST: use Rank Math `updateMeta` for `rank_math_*` keys (works regardless of CPT REST exposure), OR the one-shot mu-plugin pattern (see [`seo-checklist.md`](seo-checklist.md) "Method 2 mu-plugin"), OR wp-admin GUI for one-off fixes
- For full CRUD: register the CPT REST exposure via mu-plugin (`register_post_type` filter to set `show_in_rest=true`)

**Why CPT vendors often skip REST exposure**:
- Privacy / security (don't want public API on internal data)
- Plugin author oversight (REST registration is opt-in)
- Backwards compat (REST added in WP 4.7; older plugins may not register)

**Fix path when you control the CPT registration**:
```php
// mu-plugin to flip show_in_rest=true on a vendor CPT
add_action('init', function () {
    global $wp_post_types;
    if (isset($wp_post_types['rank_math_locations'])) {
        $wp_post_types['rank_math_locations']->show_in_rest = true;
        $wp_post_types['rank_math_locations']->rest_base = 'locations';
    }
}, 999);
```

⚠️ Verify the vendor's CPT design: some CPTs intentionally hide from REST for security reasons. Check the plugin docs before flipping the flag.

**Reusability**: universal for any WP site needing programmatic access to vendor-registered CPTs (Rank Math Local SEO, WooCommerce extensions, custom CRM plugins).

## Extract auth credentials from `~/.claude.json` MCP server config

When the user has already wired up the site's MCP connector via `claude mcp add`, the Application Password is stored in `~/.claude.json` under the connector's `headers.Authorization` as `Basic <base64>`. You can decode this to get the user/password pair for direct REST calls — no need to ask the user to re-share credentials.

```python
import json, base64, os

config_path = os.path.expanduser('~/.claude.json')
with open(config_path) as f:
    config = json.load(f)

# MCP servers can live at top-level "mcpServers" or under project-scoped keys.
# Check both shapes.
servers = config.get('mcpServers', {})

# Find the relevant connector — name varies per site
target = next((name for name in servers if 'example' in name and 'elementor' in name), None)
if target:
    auth_header = servers[target]['headers']['Authorization']
    # auth_header = "Basic bWFpdGhhbmg6M0xoMC..."
    encoded = auth_header.split(' ', 1)[1]
    decoded = base64.b64decode(encoded).decode('utf-8')
    user, pw = decoded.split(':', 1)
    # user, pw now usable for direct REST calls
```

**Security caveats**:
- `~/.claude.json` is plain-text on disk. Any process running as your user can read it.
- Make sure `chmod 600 ~/.claude.json` so only the owner reads it.
- Do NOT sync `~/.claude.json` to a public cloud / git repo / shared drive.
- The Application Password is rotation-able from `wp-admin → Profile → Application Passwords` — revoke any password that may have leaked.

**When to use this pattern**:
- You're scripting direct REST calls and the MCP bridge is already configured
- The user previously shared the App Password during `claude mcp add` setup — re-using it avoids asking again
- A CI runner needs the same credentials but `claude mcp add` is the source of truth

**When NOT to use this pattern**:
- Cross-user / cross-machine scripts (the file is local to one user/machine)
- Sharing the script with someone else (they don't have your `~/.claude.json`)
- Long-running cron — App Passwords get rotated; prefer env-var-driven config that you control

**Reusability**: universal for any user who has already configured MCP for a WP site via `claude mcp add`.

## WP REST endpoint paths use plural `rest_base`, NOT singular `post_type`

When calling `/wp/v2/{type}` endpoints, the URL path is the **plural `rest_base`** of the post type, not the singular `post_type` slug. For most built-in types it's just `s`-pluralized (`post` → `posts`, `page` → `pages`), but CPTs can have custom `rest_base` that differs substantially.

```bash
# ❌ WRONG — singular post_type name
curl "$SITE/wp-json/wp/v2/post?slug=hello-world"
# → returns empty [] (NOT 404!) because no such endpoint exists

# ✅ RIGHT — plural rest_base
curl "$SITE/wp-json/wp/v2/posts?slug=hello-world"
# → returns matching posts
```

**Discover `rest_base` for every type on the site**:
```bash
curl -u "$U:$APP_PW" "$SITE/wp-json/wp/v2/types" \
  | jq 'to_entries[] | {type: .key, rest_base: .value.rest_base, rest_namespace: .value.rest_namespace}'
```

Sample output:
```json
{"type": "post",                  "rest_base": "posts",                 "rest_namespace": "wp/v2"}
{"type": "page",                  "rest_base": "pages",                 "rest_namespace": "wp/v2"}
{"type": "product",               "rest_base": "products",              "rest_namespace": "wc/v3"}
{"type": "rank_math_locations",   "rest_base": "rank-math-locations",   "rest_namespace": "wp/v2"}
                                                  ↑ hyphen-separated, NOT underscore
```

⚠️ **CPT gotcha**: vendor CPTs often use a **hyphen-separated `rest_base`** even when the `post_type` slug uses underscores. `rank_math_locations` (post_type) → `rank-math-locations` (rest_base). Always check `/wp/v2/types` first.

**Helper script** to build the correct URL:
```python
import json, urllib.request

def get_rest_base(site, post_type, auth_header=None):
    req = urllib.request.Request(f"{site}/wp-json/wp/v2/types")
    if auth_header: req.add_header("Authorization", auth_header)
    types = json.loads(urllib.request.urlopen(req).read())
    return types[post_type]['rest_base']

base = get_rest_base("https://example.com", "rank_math_locations")
url = f"https://example.com/wp-json/wp/v2/{base}?slug=my-location"
```

**Reusability**: universal for ALL WP REST API consumers.

## Building a wrapper plugin (REST routes → abilities) — 4 gotchas

When wrapping an existing plugin's REST routes into MCP abilities (full workflow: [`workflows/build-mcp-wrapper-plugin.md`](../workflows/build-mcp-wrapper-plugin.md)), these 4 quirks of the WP Abilities Framework are the most frequent iteration costs.

### Canonical registration hook — `wp_abilities_api_init`

`wp_register_ability()` only persists registrations called from the `wp_abilities_api_init` hook. Calling it from `plugins_loaded`, `init`, `rest_api_init`, or anywhere else returns success **but the ability silently does not enter the registry**.

```php
// ✅ CORRECT
add_action( 'wp_abilities_api_categories_init', $register_categories_callback );
add_action( 'wp_abilities_api_init',            $register_abilities_callback );

// ❌ WRONG — returns success but ability NOT registered
add_action( 'plugins_loaded', $register_abilities_callback, 20 );
add_action( 'init',           $register_abilities_callback );
add_action( 'rest_api_init',  $register_abilities_callback );
```

Verified pattern from upstream `elementor-mcp` plugin (48 abilities, working). Iterating wrong hooks costs ~15 min per cycle (plugin reinstall + smoke test).

### Empty `input_schema.properties` — use `ArrayObject`, NOT `stdClass`

When an ability has no input parameters, `input_schema.properties` must serialize to a JSON empty object `{}`. Two PHP types can serialize that way, but only one survives the WP 6.9.4+ schema validator.

```php
// ❌ WRONG — fatal on WP 6.9.4+:
// Cannot use object of type stdClass as array in rest-api.php:2397
'input_schema' => [
    'type'                 => 'object',
    'properties'           => new \stdClass(),
    'additionalProperties' => false,
],

// ✅ CORRECT — ArrayObject implements both ArrayAccess (PHP bracket syntax)
//             and JsonSerializable (JSON `{}` output)
'input_schema' => [
    'type'                 => 'object',
    'properties'           => new \ArrayObject(),
    'additionalProperties' => true,
    'default'              => new \ArrayObject(),
],
```

WP core's `rest_validate_object_value_from_schema()` accesses `$schema['properties'][$key]` via PHP bracket syntax. `stdClass` doesn't implement `ArrayAccess` → fatal. `ArrayObject` does both interfaces → works.

### `meta.show_in_rest: true` REQUIRED for REST visibility

Without `show_in_rest: true`, an ability is registered internally (visible to `wp_get_abilities()`) but **invisible** via the REST endpoint `/wp-abilities/v1/abilities` — the controller filters by this exact flag. MCP clients can't discover it. Symptom: ability count gap (e.g. internal registry has 266, REST list has 50).

```php
// ❌ WRONG — registered but NOT REST-visible
'meta' => [
    'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
],

// ✅ CORRECT
'meta' => [
    'annotations'  => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
    'show_in_rest' => true,
],
```

Bake into helper functions to enforce consistency across every ability:
```php
function rmcp_meta_read(): array {
    return [
        'annotations'  => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
        'show_in_rest' => true,
    ];
}
function rmcp_meta_write(): array {
    return [
        'annotations'  => [ 'readonly' => false, 'destructive' => true, 'idempotent' => false ],
        'show_in_rest' => true,
    ];
}
```

### Call-pattern matrix — GET / POST / dummy input

The framework infers the HTTP method from `meta.annotations.readonly`. Wrong method → 405; wrong body shape → 400. The dummy-input quirk is the least-documented:

| Ability shape | HTTP method | Args / body | Error when wrong |
|---|---|---|---|
| Read (`readonly: true`) | GET | `?input[k]=v` (PHP nested array URL syntax) | `405 rest_ability_invalid_method` if POST; `400` if direct `?k=v` |
| Write (`readonly: false`) | POST | Body `{"input": {...}}` JSON wrapper | `405` if GET; `400` if raw body without `input` wrapper |
| Zero-input | GET | `?input[_]=1` (dummy property) | `400 "input không phải là loại của object"` if `?input` omitted entirely |

```bash
# Read
curl ".../wrapper-mcp/get-something/run?input[id]=8124"

# Write
curl -X POST ".../wrapper-mcp/update-something/run" \
  -d '{"input": {"id": 8124, "data": {"key": "value"}}}'

# Zero-input — the dummy is required because the validator runs BEFORE schema defaults
curl ".../wrapper-mcp/get-stats/run?input[_]=1"
```

The validator runs **before** schema `default` is applied → even `default = new ArrayObject()` doesn't satisfy the "input must be present" check. The `?input[_]=1` dummy is accepted only when `additionalProperties: true` on the schema (so the unknown `_` key is allowed).

**Recommendation**: avoid zero-input abilities entirely. Give every ability at least one optional property (`since`, `limit`, etc.) so users never hit the dummy-input footgun.

### `meta.mcp.public:true` REQUIRED for MCP Adapter dispatch (separate from `show_in_rest`)

The framework has **two independent visibility flags** that both must be set for end-to-end MCP usage. Setting one does NOT imply the other:

| Flag | Effect when `true` | REST endpoint affected |
|---|---|---|
| `meta.show_in_rest` | Ability listed in `/wp-abilities/v1/abilities` (registry) | Legacy REST `/wp-abilities/v1/...` |
| `meta.mcp.public` | Ability exposed via MCP Adapter `discover-abilities` + executable via `execute-ability` | MCP Adapter `/wp-json/mcp/<server>` |

**Symptom of missing `mcp.public:true`** — ability registered fine, visible via REST registry, but MCP Adapter `execute-ability` rejects with:
```
Ability "rankmath-mcp/list-redirections" is not exposed via MCP (mcp.public!=true)
```

**Why both flags exist separately**: MCP Adapter v0.5.0 filters abilities by an opt-in flag so plugin authors can register internal abilities (used by other PHP code) without exposing them as MCP tools to external clients. The asymmetric design is intentional but undocumented in the MCP Adapter README.

**Correct helper pattern** — bake BOTH flags into every meta function:

```php
// ✅ CORRECT
function rmcp_meta_read(): array {
    return [
        'annotations'  => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
        'show_in_rest' => true,
        'mcp'          => [ 'public' => true, 'type' => 'tool' ],
    ];
}

function rmcp_meta_write( bool $destructive = false ): array {
    return [
        'annotations'  => [ 'readonly' => false, 'destructive' => $destructive, 'idempotent' => true ],
        'show_in_rest' => true,
        'mcp'          => [ 'public' => true, 'type' => 'tool' ],
    ];
}
```

`mcp.type` accepts `"tool"` (default — exposes as callable MCP tool), `"resource"`, or `"prompt"` per MCP spec. Most write/read abilities want `"tool"`.

**Fallback REST direct** — when you can't patch the plugin (third-party code, immediate need), bypass MCP Adapter entirely:

```bash
# Readonly: GET, dùng input[k]=v nested array syntax
curl -u $U:$P "https://site/wp-json/wp-abilities/v1/abilities/<name>/run?input[limit]=200"

# Write: POST với body wrapper {"input": {...}}
curl -u $U:$P -X POST -H 'Content-Type: application/json' \
  "https://site/wp-json/wp-abilities/v1/abilities/<name>/run" \
  -d '{"input": {"sources":[...], "destination":"..."}}'
```

REST direct path always works regardless of `mcp.public` — it goes through the WP Abilities REST controller, not the MCP Adapter filter.

**Plugin design choice**: some plugins intentionally omit `mcp.public:true` (admin-only ops, internal abilities). When wrapping a third-party plugin's REST API into your own MCP-discoverable abilities (eg. rankmath-mcp wrapping Rank Math Link Genius REST), explicitly set both flags so end-users get autocomplete + dispatch via Claude/MCP clients.

Discovered: weekly distillation 2026-05-13 (a Rank Math wrapper plugin missed the flag in v2.0.4 → patched v2.0.5 after the silent-fail symptom).

## Liên quan

- [`mcp-architecture.md`](mcp-architecture.md) — vì sao endpoint MCP server tách biệt khỏi abilities registry
- [`workflows/claude-mcp-connector-setup.md`](../workflows/claude-mcp-connector-setup.md) — setup MCP bridge để dùng tool ergonomic hơn
- [`pitfalls.md`](pitfalls.md) "Application Password label ≠ username" — auth gotcha
