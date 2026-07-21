# Workflow: LiteSpeed Cache Management cho Site có MCP + REST API

LiteSpeed Cache (LSC) trên shared hosting với LSWS có 2 cache layers + invalidation patterns rất khác từ WP Rocket/W3 Total Cache. Workflow này tập trung vào **REST API cache surprises** (stale-read sau write) — thường gặp khi automate qua WP-Abilities/MCP.

> **Khi nào dùng**: setup site mới với LiteSpeed Cache, debug "write success nhưng read return old value", wrap REST plugin thành MCP abilities, hoặc bulk content automation.

## Pre-requisites

- LiteSpeed Cache plugin active (free OK)
- LSWS host hoặc LSCache CDN (auto-detect via header `X-LiteSpeed-Cache: hit|miss`)
- Optional: Cloudflare/other CDN trên top — chain cache

## LSC architecture overview

```
[Browser] ─→ [CDN (optional)] ─→ [LSWS server cache] ─→ [WordPress PHP]
              │                    │                     │
              cache 1 (edge)       cache 2 (origin)      Origin response
              CDN-Cache-Control    X-LiteSpeed-Cache-*   nocache_headers()
```

LSC plugin manages **server-side cache (cache 2)** and emits CDN headers (cache 1). Both layers can serve stale → 2 invalidation steps needed for full freshness.

## Trap 1: WP-Abilities REST stale-read sau write

⚠️ **Symptom**: `POST .../update-meta {keyword: NEW}` → HTTP 200 success. Subsequent `GET .../get-meta?id=X` → returns OLD keyword. Cache-bust với `&_t=<timestamp>` → returns NEW. Easy to debug nhầm thành "write silent-fail".

### Evidence

```
POST /wp-json/wp-abilities/v1/abilities/<plugin>/update-meta/run
Body: {"input": {"id": 4540, "focus_keyword": "_NEW_VALUE_"}}
→ HTTP 200, success=true

GET /wp-json/wp-abilities/v1/abilities/<plugin>/get-meta/run?input[id]=4540
→ "old_keyword"   ← STALE

GET .../get-meta?input[id]=4540   (same URL)
→ "old_keyword"   ← STALE (cached for 7 days TTL)

GET .../get-meta?input[id]=4540&_t=1778656910   (cache-bust)
→ "_NEW_VALUE_"   ← FRESH
```

### Root cause

LSC caches WP-Abilities REST **GET** responses với `public, max-age=604800` (7 days default). WP core REST routes usually emit `Cache-Control: no-cache, no-store` from `nocache_headers()` — BUT WP-Abilities Framework REST controller does NOT emit no-cache headers automatically.

### Fix — wrap REST namespace với no-cache filter

Add vào plugin (mu-plugin hoặc functions.php nếu solo project):

```php
add_action( 'rest_api_init', function () {
    add_filter( 'rest_post_dispatch', function ( $response, $server, $request ) {
        $route = (string) $request->get_route();

        // Match WP-Abilities REST namespace
        if ( str_starts_with( $route, '/wp-abilities/v1/abilities/' ) && str_ends_with( $route, '/run' ) ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true );
            $response->header( 'X-LiteSpeed-Cache-Control', 'no-cache', true );
            $response->header( 'CDN-Cache-Control', 'no-store', true );

            // Trigger LSC plugin's nocache control action
            if ( class_exists( '\\LiteSpeed\\Core' ) ) {
                do_action( 'litespeed_control_set_nocache', 'WP-Abilities REST response' );
            }
        }

        // Also apply to your plugin's own REST namespace if any (vd /rankmath-mcp/v1/*)
        if ( str_starts_with( $route, '/your-plugin-namespace/v1/' ) ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true );
            $response->header( 'X-LiteSpeed-Cache-Control', 'no-cache', true );
            if ( class_exists( '\\LiteSpeed\\Core' ) ) {
                do_action( 'litespeed_control_set_nocache', 'plugin REST response' );
            }
        }

        return $response;
    }, 10, 3 );
});
```

### Verify after fix

```bash
# Check response headers via curl
curl -sI -u $USER:$APP_PW \
  "https://site.com/wp-json/wp-abilities/v1/abilities/<plugin>/get-meta/run?input[id]=4540"

# Expect:
# Cache-Control: no-store, no-cache, must-revalidate, max-age=0
# X-LiteSpeed-Cache-Control: no-cache
# X-LiteSpeed-Cache: miss   (first hit) or absent if no-cache honored
```

### Reusability

Universal pattern — bất kỳ WP plugin nào expose abilities qua WP-Abilities REST trên site có LiteSpeed/CDN page cache đều hit bug này. Same pattern applies cho mọi public REST namespace cần "always fresh" semantics (REST APIs serving dynamic data, webhooks, real-time stats).

## Trap 2: Page cache auto-invalidates qua `save_post`, NOT manual purge tool

LSC plugin's "Purge All" admin button is reliable BUT can fail silently via REST API. However, **per-post cache** auto-invalidates correctly when WordPress fires `save_post` hook.

### Symptom

```
# Admin → LiteSpeed Cache → Toolbox → Purge All → looks like works
# But subsequent page load → still serves cache 1 from CDN (LSWS purged, CDN not)

# Reality: per-post purge works via save_post; full-purge sometimes flaky
```

### Insight

**2 different invalidation paths**:

| Path | Trigger | Reliability |
|---|---|---|
| Per-post auto-purge | `save_post` hook fires (post update/REST PATCH/MCP edit) | ✓ Reliable |
| Manual "Purge All" button | Admin clicks LSC toolbox | ⚠️ Sometimes flaky via REST/CLI; works via UI |
| Plugin toggle deactivate→reactivate | Last-resort nuke | ✓ Reliable but heavy |

### Recommended workflow

```bash
# Want fresh content for ONE page → use update path, not purge
curl -X POST -u $U:$P \
  "https://site/wp-json/wp/v2/pages/4540" \
  -d '{"content": "updated content"}'
# → save_post fires → LSC auto-purges page 4540 → frontend fresh

# Want full site fresh (rare) → use UI not API
# wp-admin → LiteSpeed Cache → Toolbox → Purge All → Submit
# (NOT via REST endpoint /litespeed-cache/v1/purge — known flaky)
```

## Trap 3: Cache-Control headers vs Asset CleanUp lifetime

LSC default sets `public, max-age=604800` (7 days) cho mọi cached resource. Lighthouse "Serve static assets with an efficient cache policy" audit FAILS với 7 days (Lighthouse wants ≥30 days for static assets, ideally 1 year + `immutable`).

### Fix .htaccess override

```apache
# .htaccess in WP root, BELOW LiteSpeed rules
<IfModule mod_expires.c>
    ExpiresActive On

    # Versioned/hashed static assets — 1 year + immutable
    <FilesMatch "\.(woff2|woff|ttf|eot|otf)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>

    # Versioned JS/CSS (if you append ?ver= to URLs)
    <FilesMatch "\.(js|css)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>

    # Images (rare update but not 1 year — 6 months)
    <FilesMatch "\.(jpg|jpeg|png|webp|gif|svg|ico)$">
        Header set Cache-Control "public, max-age=15552000"
    </FilesMatch>
</IfModule>
```

⚠️ `immutable` chỉ áp dụng cho versioned URLs (vd `script.js?ver=2.5`). Without version param, browser sẽ NOT revalidate even khi file changes server-side. Risk: stuck on old version.

WP automatically appends `?ver=` to enqueued scripts/styles → safe cho most theme/plugin assets. Verify trong page source: `<link href="...style.css?ver=4.13.1">`.

## Trap 4: `X-LiteSpeed-Cache: hit` vs `miss` indicators

Check cache state qua response headers:

```bash
curl -sI "https://site/" | grep -i litespeed

# Possible values:
X-LiteSpeed-Cache: hit         ← cache served, OK
X-LiteSpeed-Cache: miss        ← origin generated, will cache next time
X-LiteSpeed-Cache: hit,user    ← logged-in user cache
X-LiteSpeed-Cache: control    ← no-cache directive honored (good)
# Absent header                ← LSC bypass (logged-in admin, REST, etc.)
```

```bash
curl -sI "https://site/wp-admin/" | grep -i litespeed
# Usually no header — admin bypassed
```

## Trap 5: LSCache plugin trên container PHP-FPM exhaust

Trên CloudLinux LVE shared hosting (vd AZDIGI, iNet), rapid REST batch upload có thể trigger PHP-FPM worker exhaustion. LSC plugin itself không gây — nhưng bulk operations chui qua plugin's cache build/purge có thể overload.

Workaround: insert `sleep 2-5s` giữa rapid REST calls. Xem [`pitfalls.md`](../references/pitfalls.md) "AZDIGI shared hosting — PHP-FPM exhaustion".

## Diagnostic flowchart

```
Symptom: "REST write succeeded but read returns stale"
├─ Check: GET response includes X-LiteSpeed-Cache: hit?
│   ├─ YES → cache stale, add no-cache filter (Trap 1)
│   └─ NO → check Cache-Control header
│       ├─ has max-age > 0 → CDN cached (Trap 1 fix applies)
│       └─ no-cache present → check upstream CDN layer
└─ Verify: cache-bust with ?_t=<timestamp>
    ├─ Returns fresh → confirms cache (apply Trap 1 fix)
    └─ Returns same stale → write actually failed (DB or hook), not cache

Symptom: "Manual Purge All button doesn't free cache"
├─ Try: wp-admin LSC Toolbox UI (not REST API)
├─ Fallback: deactivate → reactivate plugin
└─ Last resort: cPanel File Manager delete /wp-content/cache/litespeed/

Symptom: "Lighthouse cache policy audit fails (7 days)"
└─ Add .htaccess Header directive (Trap 3) — override LSC default
```

## Programmatic purge via `do_action('litespeed_purge_all')` — works from a mu-plugin

**Updates prior note "REST purge fails, only deactivate→reactivate plugin works"** — that note applied specifically to LSC's own REST endpoint. The PHP action hook `do_action('litespeed_purge_all')` DOES work when fired from a mu-plugin (or any PHP context loaded after LSC init). This is the cleanest programmatic purge — no plugin toggle, no cache directory rm.

### Pattern — 1-time purge on version bump

```php
<?php
// wp-content/mu-plugins/<site>-purge-on-deploy.php
add_action( 'init', function () {
    $current_version = '2026-07-07-a';  // bump this string to re-fire on next deploy
    $done_key        = '<site>_purge_marker';

    if ( get_option( $done_key ) === $current_version ) return;  // already fired this version

    if ( function_exists( 'do_action' ) ) {
        do_action( 'litespeed_purge_all' );
    }

    update_option( $done_key, $current_version );
}, 20 );  // priority 20 — after LSC hooks itself into init
```

**How it fires**:
- First request after deploy → hook runs → `do_action` triggers LSC purge → option set to prevent re-fire
- Subsequent requests → option matches → early return, no-op
- To re-fire on next deploy → bump the version string in the constant → matches change → purge runs once more

**Verify**:

```bash
# Fetch a known-cached URL WITHOUT cache-bust query
curl -sI "$SITE/some-page/" | grep -i 'x-litespeed-cache'
# → x-litespeed-cache: miss (was hit before, purge cleared it) — confirms purge worked

# Confirm your intended change is visible
curl -s "$SITE/some-page/" | grep -c 'new-cta-marker-string'
# → 1 (or however many) — expected value present
```

### When to use each purge method

| Method | Speed | Scope | Use when |
|---|---|---|---|
| `do_action('litespeed_purge_all')` from mu-plugin | Instant | Site-wide | Deploying a render-changing mu-plugin |
| Wp-admin → LSC → Toolbox → Purge All | Instant | Site-wide | Human-driven, one-off |
| `LiteSpeed_Cache_API::purge_post( $post_id )` in hook | Instant | Single post | Programmatic per-post after data write |
| Deactivate → reactivate plugin | Slow | Full reset | Nuclear option when hooks not firing |
| Delete `/wp-content/cache/litespeed/` via cPanel | Slow | Full reset | Last resort when plugin itself broken |

The wp-admin UI + `do_action('litespeed_purge_all')` are equivalent — same action hook. Wrapper plugin-toggle recovery is only needed when LSC is in a broken state where its action hooks aren't firing.

## JS Delay (NOT Defer) — analytics + form-tracking traps

LiteSpeed's "JS Delay" optimization (LSC → Page Optimization → JS Settings → **Load JS Deferred: `Delayed`**) does something more aggressive than `defer`: it converts `<script type="text/javascript">` to `<script type="litespeed/javascript">` (browser ignores this type) — so the script does NOT execute until the FIRST USER INTERACTION (mousemove / scroll / touch / keypress with `isTrusted:true`).

This breaks analytics + form-event tracking in three specific ways.

### Trap 1 — inline listener scripts must opt out via `data-no-optimize="1"`

Symptom: page fully loads, but `typeof gtag === 'undefined'` and `window.jQuery === undefined` for many seconds. Custom inline `<script>` that attaches `click` listeners for tracking → the listener isn't registered until AFTER the first user interaction. So the first click on a `tel:` link (usually the highest-value conversion) fires WITHOUT any listener attached → event lost forever.

Fix: any inline `<script>` that registers analytics listeners must have `data-no-optimize="1"` (LSC skip flag). If Cloudflare Rocket Loader is also active, add `data-cfasync="false"` — Rocket Loader has its own delay behavior.

```html
<!-- ✅ RIGHT — script attaches listener at page parse time -->
<script data-no-optimize="1" data-cfasync="false">
(function() {
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a[href^="tel:"]');
        if (link) { /* fire analytics event */ }
    }, true);  // capture phase — catch before any nested handlers
})();
</script>
```

Verify after deploy: view page source, confirm the script tag still has `type="text/javascript"` (not `type="litespeed/javascript"`). If LSC still delayed it → the opt-out flag isn't recognized → check LSC version / setting name.

### Trap 2 — `dataLayer.push`, not `gtag()` direct

When gtag.js is loaded async / delayed, calling `gtag(...)` before gtag.js finishes loading throws `gtag is not defined`. Correct pattern is to push to `window.dataLayer` — gtag.js reads the queue when it eventually initializes:

```javascript
// ❌ WRONG — throws if gtag.js not loaded yet
gtag('event', 'click_zalo', { link_url: url });

// ✅ RIGHT — always safe, queue processed on gtag.js init
(window.dataLayer = window.dataLayer || []).push({
    event: 'click_zalo',
    link_url: url,
});
```

Verified pattern: 3 events pushed to dataLayer BEFORE gtag.js loaded → all 3 sent to `/g/collect` with full parameters after gtag.js initialized. The queue survives.

### Trap 3 — jQuery poll trap for form-submit events

Elementor forms emit `submit_success`, Fluent Forms emits `fluentform_submission_success` — both are jQuery custom events. If your tracking script polls for `window.jQuery` availability with a short interval (e.g. 60 × 300ms = 18 seconds max), the poll can time out BEFORE jQuery loads (jQuery is JS-Delayed too, so it doesn't load until first user interaction).

Real failure: user opens the form, sits still on page for 20+ seconds reading, then fills form and submits → jQuery only just loaded from the interaction → your poll timed out already → event listener never registered → form submission untracked.

Fix: extend poll to ~10 minutes total, exit early when jQuery appears:

```javascript
var attempts = 0;
var poll = setInterval(function () {
    attempts++;
    if (typeof window.jQuery !== 'undefined') {
        clearInterval(poll);
        window.jQuery(document).on('submit_success fluentform_submission_success', handler);
        return;
    }
    if (attempts > 1200) {  // 1200 × 500ms = 10 minutes
        clearInterval(poll);
    }
}, 500);
```

### Verifying analytics end-to-end without GA4 UI

You don't need to log into GA4 to check tracking events are firing. Hook `window.fetch` + `navigator.sendBeacon` client-side, filter for GA4's `/g/collect` endpoint, and parse the body:

```javascript
// Paste into browser console AFTER page loads
(function () {
    var orig = window.fetch;
    window.fetch = function (url, opts) {
        if (typeof url === 'string' && url.includes('/g/collect')) {
            console.log('[GA4 fetch]', url, opts && opts.body);
        }
        return orig.apply(this, arguments);
    };
    var origBeacon = navigator.sendBeacon.bind(navigator);
    navigator.sendBeacon = function (url, data) {
        if (url.includes('/g/collect')) {
            console.log('[GA4 beacon]', url, data);
        }
        return origBeacon(url, data);
    };
})();
```

Then trigger events (click, form submit). Note: GA4 batches multiple events into a single request body separated by `\r\n` — parse each `\r\n`-separated line for `en=<event_name>` + `ep.*=<param>`. When gtm/gtag batches, only the first event's params appear in the URL — the rest are in the body.

Test with a `dispatchEvent(new Event('click'))` from console — that event has `isTrusted:false`, LSC's JS Delay does NOT trigger on it. To fully test JS Delay-gated scripts, do a REAL mouse click / real scroll (`isTrusted:true` events only).

## Related skills

- [`pitfalls.md`](../references/pitfalls.md) — PHP-FPM exhaustion, LSC behavior quirks
- [`performance.md`](../references/performance.md) — Lighthouse cache policy + CWV
- [`wp-abilities.md`](../references/wp-abilities.md) — REST namespace patterns
- [`rankmath.md`](../references/rankmath.md) — Rank Math meta + LSC interaction
- [`ga4-admin-api.md`](ga4-admin-api.md) — GA4 write via service account (needed for custom dimensions when Site Kit blocked)
- Insight source: weekly distillation 2026-05-13 (stale-read fix via `rest_post_dispatch` filter); 2026-07-07 (programmatic purge via do_action); 2026-07-21 (JS Delay traps)
