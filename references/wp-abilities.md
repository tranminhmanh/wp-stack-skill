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

## Liên quan

- [`mcp-architecture.md`](mcp-architecture.md) — vì sao endpoint MCP server tách biệt khỏi abilities registry
- [`workflows/claude-mcp-connector-setup.md`](../workflows/claude-mcp-connector-setup.md) — setup MCP bridge để dùng tool ergonomic hơn
- [`pitfalls.md`](pitfalls.md) "Application Password label ≠ username" — auth gotcha
