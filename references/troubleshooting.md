# Troubleshooting WordPress on shared hosting (no SSH)

Universal patterns cho debug WP stack qua REST + log analysis only, không cần SSH/SFTP access. Áp dụng đặc biệt cho CloudLinux LVE / cPanel / shared hosting providers (AZDIGI, Vietnix, iNet, Hawk Host, NameCheap, GoDaddy cPanel, A2 Hosting, SiteGround pre-2023, etc).

## Finding PHP error_log on shared hosting

**The trap**: Standard WP debugging assumes `wp-content/debug.log` (khi `WP_DEBUG_LOG=true` trong wp-config.php). Trên CloudLinux LVE / cPanel shared hosting WITHOUT `WP_DEBUG_LOG` defined, PHP writes errors to **vhost-root sibling** của `public_html/` — `/home/<cpanel_user>/<domain>/error_log`. Agent searching `wp-content/debug.log` → file không tồn tại → **false negative "no errors logged"**.

**Why**:
- cPanel default Apache config sets `php_value error_log <vhost_root>/error_log` per virtual host (`<vhost_root>` = `/home/<user>/<domain>/`).
- CloudLinux LVE enforces per-user resource limits — each vhost has isolated error_log routing.
- WordPress `WP_DEBUG_LOG=true` would override → write to `wp-content/debug.log`. Without it, PHP runtime config wins → vhost-root path.
- `ini_get('error_log')` returns `"error_log"` (relative string) — PHP resolves relative paths against **current working directory** of request handler, which varies (front controller cwd, REST handler cwd, etc.) → unreliable.

**Detection strategy** — probe 7 candidate paths:

| # | Path candidate | When it wins |
|---|---|---|
| 1 | `WP_CONTENT_DIR . '/debug.log'` | Standard WP_DEBUG_LOG=true |
| 2 | `WP_CONTENT_DIR . '/error_log'` | Alt naming convention |
| 3 | `dirname(ABSPATH) . '/error_log'` | **vhost-root cPanel default** ← most common |
| 4 | `dirname(ABSPATH) . '/debug.log'` | Alt vhost-root naming |
| 5 | `dirname(dirname(ABSPATH)) . '/error_log'` | Parent of WP install (multisite) |
| 6 | `ini_get('error_log')` | PHP runtime config (may be relative) |
| 7 | `WP_DEBUG_LOG` constant value | If defined as string path |

Per path, check `file_exists()` + `filesize()` + `filemtime()` + `is_readable()`. **Largest file growing actively = active log**.

**Implementation reference** (rankmath-mcp v2.0.8+ `read-debug-log` ability):
```php
$candidates = [
    'wp_content_debug'     => WP_CONTENT_DIR . '/debug.log',
    'wp_content_error_log' => WP_CONTENT_DIR . '/error_log',
    'abspath_error_log'    => dirname(ABSPATH) . '/error_log',
    'abspath_debug'        => dirname(ABSPATH) . '/debug.log',
    'vhost_error_log'      => dirname(dirname(ABSPATH)) . '/error_log',
    'php_error_log_ini'    => ini_get('error_log') ?: '(empty)',
    'wp_debug_log_const'   => defined('WP_DEBUG_LOG') ? (is_string(WP_DEBUG_LOG) ? WP_DEBUG_LOG : WP_CONTENT_DIR.'/debug.log') : '(undefined)',
];
foreach ($candidates as $key => $path) {
    $exists = file_exists($path);
    $results[$key] = [
        'path'     => $path,
        'exists'   => $exists,
        'size'     => $exists ? filesize($path) : 0,
        'mtime'    => $exists ? gmdate('Y-m-d\TH:i:s\Z', filemtime($path)) : null,
        'readable' => $exists ? is_readable($path) : false,
    ];
}
```

**Diagnostic example** (real production site, 29MB active error_log):
```json
{
  "wp_content_debug":     { "exists": false },
  "wp_content_error_log": { "exists": false },
  "abspath_error_log":    { "exists": true, "size": 30749076, "mtime": "2026-05-16T15:44:32Z" },  ← ACTIVE
  "vhost_error_log":      { "exists": true, "size": 294 },
  "php_error_log_ini":    { "exists": false }
}
```
→ `abspath_error_log` (29.32MB, growing) là active log. cPanel `php_value error_log` routes to vhost root.

---

## Filtering huge logs efficiently — server-side substring filter

**The trap**: Khi error_log file lớn (29MB+) + có verbose spam (vd unconditional `error_log()` từ plugin), `tail -100` thường trả về 100 dòng spam — miss real fatals xảy ra trước window. `grep` qua SSH thì OK nhưng shared hosting không có SSH. Transferring full 29MB qua HTTPS để client-side filter = lãng phí bandwidth + slow.

**Pattern**: Server-side substring filter in the log-reading ability — server reads tail của file, filters lines containing keyword, returns ONLY matching lines.

**Implementation reference** (rankmath-mcp v2.0.7+):
```php
function read_log_tail_filtered($path, $lines = 5000, $filter = '') {
    // Efficient tail: read 8KB chunks from EOF
    $tail = read_tail_lines($path, $lines);
    if ($filter !== '') {
        $tail = array_filter($tail, fn($l) => stripos($l, $filter) !== false);
    }
    return ['lines' => array_values($tail), 'matches' => count($tail)];
}
```

**Filter strategy** (recommended substrings):

| Filter | Use case |
|---|---|
| `Fatal` | PHP Fatal errors (typed errors PHP 8 strict) |
| `Warning` | Non-fatal but worth review |
| `Deprecated` | Pre-PHP-9 migration prep |
| `<plugin-slug>` | Isolate plugin-specific errors (vd `seo-audit-google`, `litespeed-cache`) |
| `<function-name>` | Chase function-specific (vd `is_numeric`, `in_array`, `litespeed_oc_disable_ext_cache`) |
| Timestamp prefix (`16-May-2026 14:`) | Time-window filter (hour granularity) |
| Class name (`SAG_DB`, `RankMath\`) | Namespace-specific |

**Anti-pattern vs right approach**:
```bash
# WRONG — tail wastes bandwidth on spam, miss real fatals
curl read-debug-log?lines=100
→ 100% spam lines (vd `MCP Server: Registering ...`), real fatals từ 3 ngày trước → missed

# RIGHT — server-side filter, signal only
curl read-debug-log?lines=5000&filter=Fatal
→ Only Fatal-matching lines, complete fatal history (no transfer of spam)
```

**Pattern composition** (multi-pass diagnosis):

| Pass | Filter | Goal |
|---|---|---|
| 1 | `filter=Fatal` | List all fatal categories trong log |
| 2 | `filter=<top_fatal_substring>` | Drill into specific fatal (e.g. `filter=Call to undefined function`) |
| 3 | `filter=<timestamp>` | Verify NO new fatal post-fix (e.g. `filter=16-May-2026 14:`) |
| 4 | `filter=<plugin_name>` | Confirm specific plugin clean (e.g. `filter=seo-audit-google`) |

**Reusability**: Pattern applies cho any debug log auditing where verbose noise drowns signal:
- Apache access_log: `filter=" 500 "`, `filter=<IP>`
- MySQL slow_log: `filter="Query_time: 1[0-9]"`
- Nginx error_log: `filter=upstream timed out`

---

## PHP opcache transient window during plugin update

**The trap**: Plugin upgrade qua wp-admin Upload (Replace current) ghi đè file `.php` filesystem. PHP opcache giữ bytecode cũ ~60s sau (default `opcache.revalidate_freq=60`). Trong window đó: **WP REST validation reads NEW schema (loaded lazy from new file) vs OLD callback code (cached bytecode)** → signature mismatch fatals tại WP core (e.g. `ArgumentCountError: is_numeric() expects 1 argument, 3 given in class-wp-rest-request.php:930`). Easy false-positive: agent kết luận "plugin v2.0.9 bug" trong khi thực ra chỉ là transient opcache lag.

**Diagnostic timeline** (real v2.0.9 deploy on production):
```
T+0:00   wp-admin Upload → Replace current → file rankmath-mcp.php ghi đè
T+0:01   curl /wp-json/rankmath-mcp/v1/debug → HTTP 500 (Fatal at class-wp-rest-request.php:930)
T+0:05   curl ping ability → HTTP 200, version: 2.0.9 (different REST handler path, doesn't hit opcache mismatch)
T+0:30   curl /debug → still HTTP 500
T+60:00  curl /debug → HTTP 200 ✓ (opcache TTL expired, bytecode refresh)
T+60:01-65:00  5/5 sequential calls → all HTTP 200 stable

Total fatals across window: 17 entries
Pattern: errors stop abruptly after opcache TTL boundary
```

**Root cause** (PHP runtime + WP REST):
1. PHP opcache stores compiled bytecode in shared memory keyed by file `realpath()` + mtime.
2. When plugin file overwritten, opcache **may or may not** detect change immediately (depends on `opcache.validate_timestamps` + `opcache.revalidate_freq` settings; default freq=60s).
3. WP REST API reads schema from plugin's `register_rest_route()` call freshly each request (lazy load via reflection on attached callback's docblock).
4. If new schema declares new validation arg (vd `pattern`, `default`, new property) but old cached callback doesn't handle it → WP core validation tries to invoke arg-specific validators with new signature → fails.
5. Most fatals manifest tại WP core file (not plugin file) because validator wrapper lives ở `wp-includes/`.

**Misdiagnosis trap**: Fatal stack trace points to `wp-includes/rest-api/class-wp-rest-request.php:930` (WP core) → seems like WP bug or plugin schema bug. Reality: just bytecode-data mismatch during transition.

**Diagnostic checklist** trước khi conclude "plugin bug":

| Check | If YES → likely opcache window |
|---|---|
| Thời gian giữa plugin upload + first error < 90s? | ✅ |
| Stack trace points to WP core file (not plugin file)? | ✅ |
| Some endpoints work, others fail intermittently? | ✅ |
| Errors stop abruptly at clean time boundary (60s TTL)? | ✅ |
| Frontend / abilities other than affected endpoint work? | ✅ |

**Fix** (3 options):

| Option | When | Command |
|---|---|---|
| A. Wait 60-90s + retest | **DEFAULT** (most cases) | n/a — opcache TTL auto-expire |
| B. Force opcache reset | Have WP-CLI / SSH | `wp eval 'opcache_reset();'` |
| C. Host-specific opcache purge | Have admin GUI | wp-admin → LiteSpeed → Toolbox → Purge OPcache (LSC), hoặc cPanel → PHP Selector → reset opcache |

**Reusability**: UNIVERSAL — affects bất kỳ plugin update workflow trên PHP với opcache enabled (default trên 99% production hosting):
- Plugin update via wp-admin Upload (most common)
- Plugin update via WP-CLI (`wp plugin update`)
- Plugin code change via Plugin File Editor
- Composer-based plugin updates
- Direct file edit via SFTP/cPanel File Manager

**Prevention** (if you control hosting):
- Set `opcache.revalidate_freq=2` (2s) — fast detection of file changes, minor perf cost
- Or set `opcache.validate_timestamps=0` + explicit `opcache_reset()` post-deploy (most efficient)

---

## Cross-references

| Pattern | See |
|---|---|
| Build MCP-discoverable abilities cho log/file access | [`wp-abilities.md`](wp-abilities.md) |
| Suppress upstream plugin's verbose error_log spam | [`mu-plugin-patterns.md`](mu-plugin-patterns.md) Pattern 1 |
| LiteSpeed Cache + REST stale-read fix | [`pitfalls.md`](pitfalls.md) (LiteSpeed section) |
| PHP 8 strict-type compat (in_array null, ArgumentCountError) | [`pitfalls.md`](pitfalls.md) (PHP version section) |
