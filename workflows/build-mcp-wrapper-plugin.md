# Workflow: Build a wrapper MCP plugin (REST routes → MCP abilities)

Wrap an existing WordPress plugin's REST routes into **MCP-discoverable abilities** via the WP Abilities Framework. Lets an AI agent (Claude Code, Claude Desktop) call plugin features that the original author never exposed as MCP tools.

## When to use

✅ A WP plugin you use heavily exposes REST routes (Rank Math, Yoast, WP Rocket, etc.) but no MCP abilities → AI agent can't see them
✅ You want the AI to drive plugin features in chat without you running curl yourself
✅ The plugin's REST routes are stable + documented (or you can read its `register_rest_route()` source)

❌ Plugin has NO REST routes — wrapper has nothing to wrap (different problem: ask the vendor for REST)
❌ Plugin already ships MCP abilities (e.g. msrbuilds/elementor-mcp) — don't duplicate
❌ One-off automation script — direct REST is enough (see [`references/wp-abilities.md`](../references/wp-abilities.md) "Direct REST ability call pattern")

## Architecture — REST routes vs WP-Abilities Framework

Two **independent** registries on a WordPress site. The distinction is critical for understanding why a wrapper plugin is needed.

| Aspect | `register_rest_route()` | `wp_register_ability()` (WP-Abilities) |
|---|---|---|
| Discovery URL | `/wp-json/<namespace>/<version>` | `/wp-json/wp-abilities/v1/abilities` |
| Who registers | Any plugin via the REST API hook chain | Any plugin via `wp_abilities_api_init` |
| Schema metadata | Optional `args` param per route | Required `input_schema` + `output_schema` |
| MCP discovery | ❌ NOT auto-bridged to MCP | ✅ Auto-discovered when MCP client connects |
| AI-agent visibility | Hidden unless wrapper exists | Visible immediately |

**The wrapper plugin's job**: register an ability for each REST route, with the ability's callback dispatching to the existing REST route via `rest_do_request()` in-process.

```
AI Agent ─── MCP ──> /wp-json/wp-abilities/v1/abilities/<wrapper>/<ability>/run
                              │
                              ▼
                     ability callback fires
                              │
                              ▼  rest_do_request('/<plugin>/v1/<route>', $input)
                              │
                              ▼
                     plugin's existing REST handler
                              │
                              ▼
                     returns to ability → returns to MCP → returns to AI
```

Same business logic, two front doors. The wrapper is purely a discoverability + schema layer.

## Step 1 — Inventory the plugin's REST routes

```bash
# List all REST namespaces on the site
curl -u "$U:$APP_PW" "$SITE/wp-json/" | jq '.namespaces'

# List routes for the plugin you want to wrap
curl -u "$U:$APP_PW" "$SITE/wp-json/<plugin>/v1" | jq '.routes | keys'
```

Sample output for Rank Math:
```json
[
  "/rankmath/v1/updateMeta",
  "/rankmath/v1/updateRedirection",
  "/rankmath/v1/links/links",
  "/rankmath/v1/links/posts",
  ...
]
```

For each route, decide:
- Will the AI agent use this often enough to be worth wrapping?
- Does the schema (input/output) make sense for AI use?
- Is the route stable across plugin versions, or might it change soon?

A typical wrapper plugin handles 5–20 routes — pick the high-value ones, not every route.

## Step 2 — Scaffold the wrapper plugin

```
your-wrapper-mcp/
├── your-wrapper-mcp.php          (plugin header + boot)
├── includes/
│   ├── class-abilities.php       (ability registration)
│   ├── class-dispatch.php        (rest_do_request wrappers)
│   └── helpers.php               (rmcp_meta_read / rmcp_meta_write helpers)
└── readme.txt
```

Minimum boot file:

```php
<?php
/**
 * Plugin Name: <Plugin> MCP Wrapper
 * Description: Wraps <Plugin>'s REST routes into MCP-discoverable abilities.
 * Version: 1.0.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/class-dispatch.php';
require_once __DIR__ . '/includes/class-abilities.php';

add_action( 'wp_abilities_api_categories_init', [ \WrapperMCP\Abilities::class, 'register_category' ] );
add_action( 'wp_abilities_api_init',            [ \WrapperMCP\Abilities::class, 'register_abilities' ] );
```

⚠️ **CRITICAL — use `wp_abilities_api_init` hook**. Not `plugins_loaded`, not `init`, not `rest_api_init`. The WP-Abilities Framework only accepts registrations during this specific hook. Calling `wp_register_ability()` outside this hook returns success **but the registration does not persist** — the ability is silently dropped.

Reference: [`references/wp-abilities.md`](../references/wp-abilities.md) "Canonical registration hook `wp_abilities_api_init`".

## Step 3 — Helper functions for meta consistency

Every ability needs a `meta` block declaring readonly/destructive/idempotent flags + REST visibility. Define helpers so every ability declares them consistently — avoid the next pitfall.

```php
// includes/helpers.php

/**
 * Standard meta block for READONLY abilities (GET-able).
 * @return array
 */
function rmcp_meta_read(): array {
    return [
        'annotations'  => [
            'readonly'    => true,
            'destructive' => false,
            'idempotent'  => true,
        ],
        'show_in_rest' => true,   // CRITICAL — without this, /wp-abilities/v1/abilities filters out the ability
    ];
}

/**
 * Standard meta block for WRITE abilities (POST-only).
 * @return array
 */
function rmcp_meta_write(): array {
    return [
        'annotations'  => [
            'readonly'    => false,
            'destructive' => true,    // assume destructive unless idempotent
            'idempotent'  => false,
        ],
        'show_in_rest' => true,
    ];
}
```

⚠️ **`meta.show_in_rest: true` is REQUIRED**. The REST controller at `/wp-abilities/v1/abilities` filters the registry by `show_in_rest=true`. Default is `false`, so an ability without this flag is registered but invisible to MCP clients. Symptom: `wp_get_abilities()` returns the ability internally, but `GET /wp-abilities/v1/abilities` does not list it → MCP tool count gap → 404 on direct call.

## Step 4 — Register categories + abilities

```php
// includes/class-abilities.php
namespace WrapperMCP;

class Abilities {

    public static function register_category(): void {
        wp_register_ability_category( 'wrapper-mcp', [
            'label'       => 'Wrapper Plugin',
            'description' => 'Wrapped REST routes from <plugin>',
        ] );
    }

    public static function register_abilities(): void {
        // Read ability — GET method
        wp_register_ability( 'wrapper-mcp/get-something', [
            'label'        => 'Get <something>',
            'description'  => 'Reads <something> from <plugin>',
            'category'     => 'wrapper-mcp',
            'input_schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'id' => [ 'type' => 'integer', 'description' => 'Post ID' ],
                ],
                'required'             => [ 'id' ],
                'additionalProperties' => false,
            ],
            'output_schema' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
            'execute_callback' => [ Dispatch::class, 'get_something' ],
            'meta' => rmcp_meta_read(),   // readonly + show_in_rest
        ] );

        // Write ability — POST method
        wp_register_ability( 'wrapper-mcp/update-something', [
            'label'        => 'Update <something>',
            'description'  => 'Updates <something> via <plugin>',
            'category'     => 'wrapper-mcp',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'id'   => [ 'type' => 'integer' ],
                    'data' => [ 'type' => 'object', 'additionalProperties' => true ],
                ],
                'required'   => [ 'id', 'data' ],
                'additionalProperties' => false,
            ],
            'output_schema' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
            'execute_callback' => [ Dispatch::class, 'update_something' ],
            'meta' => rmcp_meta_write(),
        ] );
    }
}
```

### ⚠️ Empty `input_schema.properties` trap — use `ArrayObject`, NOT `stdClass`

When an ability has NO input parameters (zero-input call), `input_schema.properties` must serialize to a JSON empty object `{}`. Two PHP objects can do that, but only ONE works at runtime:

```php
// ❌ WRONG — Devenia reference plugin pattern. Fails on WP 6.9.4+ with PHP fatal:
// "Cannot use object of type stdClass as array in rest-api.php:2397"
'input_schema' => [
    'type'                 => 'object',
    'properties'           => new \stdClass(),
    'additionalProperties' => false,
],

// ✅ CORRECT — ArrayObject implements ArrayAccess (PHP brackets work)
//             AND JsonSerializable (serializes to JSON {})
'input_schema' => [
    'type'                 => 'object',
    'properties'           => new \ArrayObject(),
    'additionalProperties' => true,     // allow dummy input — see Step 6 dummy-input quirk
    'default'              => new \ArrayObject(),
],
```

WP core's `rest_validate_object_value_from_schema()` accesses `$schema['properties'][$key]` via PHP bracket syntax. `stdClass` doesn't implement `ArrayAccess` → fatal. `ArrayObject` does both. See [`references/wp-abilities.md`](../references/wp-abilities.md) "Empty input_schema trap".

## Step 5 — Implement the dispatch class

The dispatch class translates ability input → `WP_REST_Request` → calls the plugin's existing REST handler via `rest_do_request()`.

```php
// includes/class-dispatch.php
namespace WrapperMCP;

class Dispatch {

    /**
     * @param array $input  Validated input from the ability's input_schema.
     * @return array|WP_Error  Plugin's REST response.
     */
    public static function get_something( array $input ): array {
        $req = new \WP_REST_Request( 'GET', '/wrapped-plugin/v1/something/' . $input['id'] );

        // Forward auth context (current user permissions inherit automatically)
        $resp = rest_do_request( $req );

        if ( $resp->is_error() ) {
            return [ 'error' => $resp->as_error()->get_error_message() ];
        }
        return $resp->get_data();
    }

    public static function update_something( array $input ): array {
        $req = new \WP_REST_Request( 'POST', '/wrapped-plugin/v1/something/' . $input['id'] );
        $req->set_body_params( $input['data'] );
        $req->set_header( 'Content-Type', 'application/json' );

        $resp = rest_do_request( $req );
        if ( $resp->is_error() ) {
            return [ 'error' => $resp->as_error()->get_error_message() ];
        }
        return $resp->get_data();
    }
}
```

Key idea: `rest_do_request()` runs the request **in-process** within the current PHP execution. No HTTP round-trip, full WordPress context (current user, capabilities, hooks).

## Step 6 — Test via direct REST + understand the call patterns

Three call patterns the framework enforces. Document them in your plugin's readme so users know.

```bash
B64=$(printf 'admin:xxxx xxxx xxxx xxxx xxxx xxxx' | base64)

# Pattern 1: READ ability (readonly: true) — GET, params via ?input[k]=v
curl -H "Authorization: Basic $B64" \
  "$SITE/wp-json/wp-abilities/v1/abilities/wrapper-mcp/get-something/run?input[id]=8124"
# → 200 OK

# Pattern 2: WRITE ability (readonly: false) — POST, body wrapped in {"input": {...}}
curl -H "Authorization: Basic $B64" \
  -H "Content-Type: application/json" \
  -X POST "$SITE/wp-json/wp-abilities/v1/abilities/wrapper-mcp/update-something/run" \
  -d '{"input": {"id": 8124, "data": {"key": "value"}}}'
# → 200 OK

# Pattern 3: ZERO-INPUT ability — GET with dummy input to satisfy the validator
curl -H "Authorization: Basic $B64" \
  "$SITE/wp-json/wp-abilities/v1/abilities/wrapper-mcp/get-stats/run?input[_]=1"
# → 200 OK

# Without the dummy: 400 "input không phải là loại của object"
```

⚠️ The framework validator runs BEFORE applying the schema's `default` value. So even when `default = new ArrayObject()`, the validator demands the `input` field be present in the request. The `?input[_]=1` dummy satisfies the "input must be object" check (since `additionalProperties: true` accepts any extra keys).

**Recommendation**: avoid zero-input abilities entirely — give every ability at least one optional property to remove the dummy-input footgun for end users. Example: `get-stats` accepts an optional `since` date param, even if the plugin ignores it.

## Step 7 — Verify discovery + MCP visibility

```bash
# Count abilities listed via REST (the MCP-visible set)
curl -u "$U:$APP_PW" "$SITE/wp-json/wp-abilities/v1/abilities" \
  | jq '. | length'
# → previous count + N abilities you registered

# Count abilities registered internally (any with show_in_rest=true OR false)
curl -u "$U:$APP_PW" "$SITE/wp-json/wp-abilities/v1/abilities" \
  | jq -r '.[] | .name' | grep -c '^wrapper-mcp/'
# → N (your wrapper's count)
```

If the REST list shows fewer than expected, the most likely cause is missing `show_in_rest: true` in the `meta` block — see Step 3.

After REST verification, restart your MCP client (Claude Code / Claude Desktop) to load the new abilities into the tool registry. Tool schemas only load at session init.

## Step 8 — Iterate + version

Real wrapper plugins iterate. Plan for at least 5–10 install cycles before the abilities are stable. Common iteration causes:

| Iteration trigger | Solution |
|---|---|
| Hook timing wrong (registration silently dropped) | Use `wp_abilities_api_init` — see Step 2 |
| Empty input_schema crash on WP 6.9.4 | Switch `new stdClass()` → `new ArrayObject()` — see Step 4 |
| Ability registered but not in REST list | Add `show_in_rest: true` to meta — see Step 3 |
| `rest_do_request()` returns 401 / 403 | Permission callback — the wrapped route may require admin caps; ensure the user calling the ability has them |
| Schema validation rejects valid input | The wrapped route's args are looser than your ability's `input_schema` — tighten the ability schema to match what the route accepts |
| Output too large / breaks JSON | The wrapped route returns a 100KB+ payload — paginate via the ability input, OR truncate in dispatch |

## Real-world reference

Build cycles observed on one real wrapper plugin (`rankmath-mcp` wrapping Rank Math's REST routes):
- **v1.0.0 → v1.0.3**: hook iteration (3 wrong hooks before landing on `wp_abilities_api_init`)
- **v1.0.4**: `ArrayObject` swap (empty schema fatal on WP 6.9.4)
- **v1.0.5**: helper refactor (every ability needed `show_in_rest`)
- **v2.0.0 → v2.0.1**: visibility fix (16 abilities registered, only 0 in REST list — missing `show_in_rest`)
- **v2.0.2**: stable. 4 Rank Math REST routes → 16 MCP abilities. AI agent can now drive Rank Math features in chat.

Total: 12 install cycles over a single 5-hour session. Each cycle was ~15 minutes (deploy → smoke-test → diagnose → patch).

## Anti-patterns

❌ **Wrapping every REST route blindly**. Pick the ones the AI will actually use; over-wrapping bloats the MCP tool list + adds maintenance burden.

❌ **Wrapping routes you don't control**. If the upstream plugin author renames a route, your wrapper breaks silently. Subscribe to their changelog or pin the wrapped plugin version.

❌ **Skipping smoke-test on every iteration**. Twelve 15-minute iterations beats one 4-hour debugging marathon.

❌ **Mixing helper patterns**. Pick `rmcp_meta_read()` / `rmcp_meta_write()` once and use everywhere. Inconsistent `show_in_rest` is the most common bug after the third refactor.

## Cross-references

- [`references/wp-abilities.md`](../references/wp-abilities.md) — Framework-level reference: call patterns, hook, ArrayObject empty schema, show_in_rest visibility
- [`references/mcp-architecture.md`](../references/mcp-architecture.md) — Why MCP-bridge connectors need to point at a plugin's MCP endpoint, not at the abilities-registry endpoint
- [`workflows/claude-mcp-connector-setup.md`](claude-mcp-connector-setup.md) — Add the wrapper plugin's endpoint as an MCP connector after deployment
- [`references/seo-checklist.md`](../references/seo-checklist.md) — Rank Math `updateMeta` / `updateRedirection` REST direct-call patterns (alternative to wrapping when one-off automation suffices)

## Deploy wrapper plugin — local zip workflow

After building wrapper plugin source, deploy onto live WordPress site. MCP abilities `mcp-wp/install-plugin` and `mcp-wp/update-plugin` only accept WordPress.org slug or public download URL — they do **NOT** accept local file content/base64. Custom plugins must deploy via alternative paths.

### Deploy paths (in order of preference)

| Path | Effort | Automation | When |
|---|---|---|---|
| **Manual wp-admin upload** | ~2 min user action | ❌ Required user click | Single deploy, no CI/CD setup |
| **Host zip at public URL → `mcp-wp/install-plugin`** | ~5 min setup once | ✓ Automated after URL set | Multi-site rollout, CI/CD |
| **SCP/SFTP to `/wp-content/plugins/`** | ~3 min if SSH access | ✓ Scriptable | Dev with SSH access |
| **`mcp-wp/install-plugin` với WordPress.org URL** | ✓ Fully automated | ✓ | Only for public plugins (not custom) |

### Path A: Manual wp-admin upload (recommended for solo dev)

1. Build zip với forward-slash separator (xem [`../references/deployment.md`](../references/deployment.md) "Plugin zip build cross-platform")
2. `wp-admin → Plugins → Add New → Upload Plugin`
3. Select zip → Install Now
4. If "Plugin already installed" → click **Replace current with uploaded** → confirm
5. Activate Plugin (usually pre-active if v→ overwrites)

User must do step 2-5. Takes ~2 minutes.

### Path B: Public URL + `mcp-wp/install-plugin` (CI/CD automation)

```bash
# 1. Build zip với forward-slash
# 2. Upload zip lên public URL (S3, GitHub release, Synology Drive public share, etc.)

# 3. Call MCP ability to install
mcp__site-global__mcp-adapter-execute-ability(
    ability_name="mcp-wp/install-plugin",
    parameters={
        "download_url": "https://your-cdn/path/wrapper-plugin-1.0.5.zip",
        "activate": true
    }
)
# → installs from URL, no manual upload needed
```

⚠️ Public URL exposes plugin source code globally — verify no secrets baked in (App Passwords, API keys, customer data).

### Path C: SCP / SFTP (dev with SSH)

```bash
unzip wrapper-plugin-1.0.5.zip -d /tmp/wrapper-plugin/

rsync -avz /tmp/wrapper-plugin/ user@host:/path/to/wordpress/wp-content/plugins/wrapper-plugin/

# Activate via WP-CLI OR REST
curl -u $U:$P -X POST "$SITE/wp-json/wp/v2/plugins/wrapper-plugin/wrapper-plugin" -d '{"status":"active"}'
```

### Verify deploy

```bash
# Check plugin version via debug endpoint (if wrapper exposes one)
curl -u $U:$P "$SITE/wp-json/wrapper-mcp/v1/debug"
# Expected: plugin_version matches your build

# Check ability count
curl -u $U:$P "$SITE/wp-json/wp-abilities/v1/abilities" | jq '[.[] | select(.name | startswith("wrapper-mcp/"))] | length'
# Expected: N (your wrapper's ability count)

# Test 1 ability via MCP (if mcp.public:true set)
mcp__site-global__mcp-adapter-execute-ability(name: "wrapper-mcp/sample-ability", parameters: {...})
# Expected: HTTP 200 success
```

### Rollback nếu deploy fails

```bash
# Path A/C: re-upload OLD version zip via same path
# Path B: switch download_url back to previous version URL

# Worst case (plugin causes 500 fatal):
# wp-admin → Plugins → Deactivate wrapper-plugin
# Or via REST: 
curl -u $U:$P -X POST "$SITE/wp-json/wp/v2/plugins/wrapper-plugin/wrapper-plugin" -d '{"status":"inactive"}'
```

### Reusability

Same pattern applies cho deploying ANY custom WP plugin (not just MCP wrappers). MCP install-plugin ability constraint = WordPress.org slug OR public URL → custom plugins forever require alternative deploy paths.
