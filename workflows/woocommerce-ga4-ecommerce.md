# Workflow: WooCommerce × GA4 ecommerce tracking — audit sources, fix `purchase`, server-side Measurement Protocol

A WooCommerce site usually has **three or four independent things firing GA4 events**, and nobody wrote down which one owns which event. The result is double counts, events that never reach GA4, and revenue that is off by orders of magnitude. This workflow audits the sources, makes each event single-owner, and moves `purchase` to the server so it is counted even when the customer never sees the thank-you page.

## When to use

✅ GA4 revenue / purchase count does not match WooCommerce orders (any gap > ~10%)
✅ `itemsViewed = 0` or ecommerce funnel reports empty even though "the plugin fires view_item"
✅ `form_submit` is high but real lead events are 0
✅ Orders arrive through channels that skip the browser thank-you page (bank-transfer / QR payment where the customer doesn't return, POS or ERP sync, admin-created orders)
✅ Conversion reports feel inflated (staff entering offline leads through the website form)

❌ Pure configuration writes (custom dimensions, key events) — see [`ga4-admin-api.md`](ga4-admin-api.md)
❌ Events not firing at all because scripts are delayed — fix [`litespeed-cache-mgmt.md`](litespeed-cache-mgmt.md) §"JS Delay" first

## Step 1 — Map who fires what (source audit)

Open a product page, the cart, checkout and the thank-you page with DevTools and dump `window.dataLayer`. Group every entry by event name and by `send_to`.

| Source (typical) | Events | Where they go | How to recognise |
|---|---|---|---|
| Site Kit — WooCommerce events provider (`googlesitekit-events-provider-woocommerce-*.js`) | `add_to_cart`, `purchase` (with `items[]`) | GA4 | Script src contains `events-provider-woocommerce` |
| Google for WooCommerce (plugin slug `google-listings-and-ads`, `gtag-events.js`) | `view_item`, `add_to_cart`, `page_view` in remarketing format (`ecomm_pagetype`, `id: "gla_<id>"`) | **Google Ads only** (`send_to: "GLA"` / `AW-…`) | `send_to` present and not your `G-` ID |
| GA4 Enhanced Measurement (automatic, no code) | `form_start`, `form_submit`, scroll, outbound `click`, file download… | GA4 | No dataLayer entry — happens inside gtag.js |
| Your own MU-plugin / snippet | Whatever you add | Whatever `send_to` you set | You wrote it |

The single most common misread: **"the product page fires `view_item`, so GA4 has it"**. If the only `view_item` carries `send_to: "GLA"`, it never reaches GA4 — `itemsViewed` stays 0.

**Rule: one event name → exactly one owner per destination.** Before adding an event, check nobody else already sends it to GA4. When you add one, set `send_to: 'G-XXXXXXXXXX'` explicitly so it doesn't leak into the Ads tag, and don't re-send `add_to_cart` / `purchase` if Site Kit already owns them (until Step 3 says otherwise).

## Step 2 — Fill the missing ecommerce events

Site Kit covers `add_to_cart` + `purchase` only. The GA4 ecommerce funnel also needs `view_item`, `view_item_list`, `select_item`, `view_cart`, `remove_from_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`. One MU-plugin on `wp_footer` (late priority, e.g. 99) renders the data server-side and pushes it:

```php
add_action( 'wp_footer', function () {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
    $p = wc_get_product( get_the_ID() );
    if ( ! $p ) return;
    $cats = wp_get_post_terms( $p->get_id(), 'product_cat', [ 'fields' => 'names' ] );
    $item = [
        'item_id'       => $p->get_sku() ?: (string) $p->get_id(),   // prefer SKU
        'item_name'     => $p->get_name(),
        'item_category' => $cats[0] ?? '',
        'price'         => (float) wc_get_price_to_display( $p ),
        'index'         => 0,
    ];
    $params = [
        'send_to'  => 'G-XXXXXXXXXX',            // GA4 only — keep it out of the Ads tag
        'currency' => get_woocommerce_currency(),
        'value'    => $item['price'],
        'items'    => [ $item ],
    ];
    ?>
    <script data-no-optimize="1" data-cfasync="false">
    (function () {
      // Same signature as the official gtag() stub: push the Arguments object
      function acmeTrack() { (window.dataLayer = window.dataLayer || []).push(arguments); }
      acmeTrack('event', 'view_item', <?php echo wp_json_encode( $params ); ?>);
    })();
    </script>
    <?php
}, 99 );
```

Pushing the `Arguments` object is exactly what the official `gtag()` stub does, so the call is queued safely even when gtag.js loads later (see the JS Delay traps in [`litespeed-cache-mgmt.md`](litespeed-cache-mgmt.md)). Two look-alikes are **not** equivalent on a gtag-only setup: a GTM-style object `push({event: 'view_item', …})` only feeds GTM triggers and sends nothing to GA4, and an array `push(['event', …])` is not guaranteed to be read as a gtag command. Also don't declare a global named `gtag` — you would overwrite the one Site Kit installs. Verify in GA4 **Realtime**: the new events appear, and the counts of events owned by other sources did not change (no double count).

Test the right pages: find the real cart / checkout page IDs in WooCommerce → Settings → Advanced. Sites migrated from another shop plugin often keep a legacy "cart" page that is NOT the WooCommerce cart.

## Step 3 — `purchase` revenue ÷100 on zero-decimal currencies

**Symptom**: GA4 purchase revenue is ~1–2% of WooCommerce revenue. Match orders by `transaction_id` and the ratio is exactly 100: a 150,000 VND order shows as 1,500 in GA4.

**Root cause (observed on Site Kit's client-side WooCommerce provider, 2026-07)**: the provider sends `value` as if the amount were in minor units (cents) and divides by 100. That is wrong for zero-decimal currencies such as VND (JPY / KRW are at risk of the same bug — verify on your version). The same order sent server-side (Step 4) records the correct value, so the bug is in the client-side path, not in GA4.

**Detection** — compare per order, never on totals only:

```text
# WooCommerce side: orders in sale statuses for the window
GET /wp-json/wc/v3/orders?after=2026-07-01T00:00:00&status=processing,completed&per_page=100
# GA4 side: Data API runReport, dimension transactionId, metric purchaseRevenue
POST https://analyticsdata.googleapis.com/v1beta/properties/<property-id>:runReport
{"dateRanges":[{"startDate":"2026-07-01","endDate":"today"}],
 "dimensions":[{"name":"transactionId"}],"metrics":[{"name":"purchaseRevenue"}]}
# Join on order number = transactionId → ratio WC/GA4 per order. ~100 everywhere = this bug.
```

**Fix — make server-side the single `purchase` source and silence the client-side one only where it fires `purchase`** (the thank-you / order-received endpoint). Other pages keep the provider, so `add_to_cart` is untouched:

```php
// Belt: dequeue the provider on the order-received endpoint only
add_action( 'wp_enqueue_scripts', function () {
    if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) return;
    foreach ( wp_scripts()->registered as $handle => $dep ) {
        if ( is_string( $dep->src ) && false !== strpos( $dep->src, 'events-provider-woocommerce' ) ) {
            wp_dequeue_script( $handle );
            wp_deregister_script( $handle );
        }
    }
}, 9999 );

// Suspenders: drop the tag if something re-enqueues it later
add_filter( 'script_loader_tag', function ( $tag, $handle, $src ) {
    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' )
         && false !== strpos( (string) $src, 'events-provider-woocommerce' ) ) {
        return '';
    }
    return $tag;
}, 10, 3 );
```

Match the script by `src` substring rather than a hard-coded handle — handles change between plugin versions.

❌ Don't try to "fix" the value by wrapping `gtag()` in JS. GA4 de-duplicates `purchase` by `transaction_id` and **keeps the first event it receives** — which would be the wrong one.

Historic orders cannot be corrected: GA4 keeps the first (wrong) event per transaction, and Measurement Protocol only accepts timestamps up to 72 hours back. Record the fix date as a data break.

## Step 4 — Server-side `purchase` via Measurement Protocol

Client-side `purchase` only fires when a browser renders the thank-you page. Bank-transfer / QR customers often don't come back, and orders created by a POS or ERP sync never have a browser at all. Measurement Protocol (MP) sends the event from PHP when the order reaches a paid status.

### 4a. Create the MP secret (Admin API, same service account as [`ga4-admin-api.md`](ga4-admin-api.md))

```bash
curl -X POST "https://analyticsadmin.googleapis.com/v1beta/properties/$PROPERTY_ID/dataStreams/$STREAM_ID/measurementProtocolSecrets" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"displayName":"server-purchase"}'
# → {"name": ".../measurementProtocolSecrets/<id>", "secretValue": "<secret>"}
```

Store `secretValue` in a WordPress option through a one-time admin-only REST route — **never hard-code it in the MU-plugin file** (files get copied, backed up, pasted into chats).

### 4b. The MU-plugin — three triggers, one sender

```php
<?php
/**
 * Plugin Name: acme-ga4-mp-purchase
 * Description: Server-side GA4 `purchase` via Measurement Protocol — single source of truth for revenue.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const ACME_MP_MEASUREMENT_ID = 'G-XXXXXXXXXX';
const ACME_MP_DELAY          = 120;                  // seconds after the order reaches a sale status
const ACME_MP_SWEEP_WINDOW   = 2 * DAY_IN_SECONDS;   // must stay inside MP's 72h backdating limit

function acme_mp_sale_statuses() {
    // Decide deliberately. Leave out statuses that are often cancelled later (e.g. unpaid on-hold).
    return apply_filters( 'acme_mp_sale_statuses', [ 'processing', 'completed' ] );
}

// Capture the GA client_id at checkout. Cookie _ga = "GA1.1.<random>.<timestamp>" → client_id = "<random>.<timestamp>"
add_action( 'woocommerce_checkout_create_order', function ( $order ) {
    if ( empty( $_COOKIE['_ga'] ) ) return;
    $parts = explode( '.', sanitize_text_field( wp_unslash( $_COOKIE['_ga'] ) ) );
    if ( count( $parts ) >= 4 ) {
        $order->update_meta_data( '_acme_ga_cid', $parts[2] . '.' . $parts[3] );
    }
} );

function acme_mp_schedule_if_sale( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_meta( '_acme_mp_sent' ) ) return;
    if ( ! in_array( $order->get_status(), acme_mp_sale_statuses(), true ) ) return;
    if ( ! wp_next_scheduled( 'acme_mp_send', [ (int) $order_id ] ) ) {
        wp_schedule_single_event( time() + ACME_MP_DELAY, 'acme_mp_send', [ (int) $order_id ] );
    }
}
// Trigger 1 — normal flow: status changes into a sale status
add_action( 'woocommerce_order_status_changed', function ( $order_id ) { acme_mp_schedule_if_sale( $order_id ); }, 10, 1 );
// Trigger 2 — orders BORN in a sale status (POS / ERP sync) never fire status_changed
add_action( 'woocommerce_new_order', function ( $order_id ) { acme_mp_schedule_if_sale( $order_id ); }, 20, 1 );
// Trigger 3 — hourly sweep: the real safety net for anything the hooks missed
add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'acme_mp_sweep' ) ) wp_schedule_event( time() + 300, 'hourly', 'acme_mp_sweep' );
} );
add_action( 'acme_mp_sweep', function () {
    $ids = wc_get_orders( [
        'status'       => acme_mp_sale_statuses(),
        'date_created' => '>' . ( time() - ACME_MP_SWEEP_WINDOW ),
        'limit'        => 200,
        'return'       => 'ids',
    ] );
    foreach ( $ids as $id ) acme_mp_send( $id );
} );

add_action( 'acme_mp_send', 'acme_mp_send' );
function acme_mp_send( $order_id ) {
    $order  = wc_get_order( $order_id );
    $secret = get_option( 'acme_mp_secret' );
    if ( ! $order || ! $secret || $order->get_meta( '_acme_mp_sent' ) ) return;       // idempotent
    if ( ! in_array( $order->get_status(), acme_mp_sale_statuses(), true ) ) return;

    $created = $order->get_date_created()->getTimestamp();
    // No cookie (synced / admin orders) → stable synthetic id: revenue is right, source shows as (direct)
    $cid = $order->get_meta( '_acme_ga_cid' ) ?: abs( crc32( 'order-' . $order_id ) ) . '.' . $created;

    $items = [];
    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        $items[] = [
            'item_id'   => ( $product && $product->get_sku() ) ? $product->get_sku() : (string) $item->get_product_id(),
            'item_name' => $item->get_name(),
            'quantity'  => (int) $item->get_quantity(),
            'price'     => (float) $order->get_item_subtotal( $item, false, false ),
        ];
    }

    $payload = [
        'client_id'        => $cid,
        // Order date, clamped inside the 72h window MP accepts — otherwise the event is dropped silently
        'timestamp_micros' => max( $created, time() - 71 * HOUR_IN_SECONDS ) * 1000000,
        'events'           => [ [
            'name'   => 'purchase',
            'params' => [
                'transaction_id' => (string) $order->get_order_number(),
                'currency'       => $order->get_currency(),
                'value'          => (float) $order->get_total(),   // full currency units — no /100 for VND
                'shipping'       => (float) $order->get_shipping_total(),
                'tax'            => (float) $order->get_total_tax(),
                'items'          => $items,
            ],
        ] ],
    ];

    $res = wp_remote_post(
        'https://www.google-analytics.com/mp/collect?measurement_id=' . ACME_MP_MEASUREMENT_ID . '&api_secret=' . rawurlencode( $secret ),
        [ 'body' => wp_json_encode( $payload ), 'headers' => [ 'Content-Type' => 'application/json' ], 'timeout' => 10 ]
    );
    if ( ! is_wp_error( $res ) && 2 === intdiv( (int) wp_remote_retrieve_response_code( $res ), 100 ) ) {
        $order->update_meta_data( '_acme_mp_sent', gmdate( 'c' ) );
        $order->save();
    }
}
```

Add three admin-only REST routes for operations (gate on `manage_woocommerce`): `status` (dry list of orders that would be sent), `send {order_id, force}`, `sweep`. They make the plugin debuggable without wp-admin.

### 4c. Validate before trusting it

```bash
# MP never returns errors on /mp/collect (always 2xx). Validate the payload shape on the debug endpoint:
curl -s -X POST "https://www.google-analytics.com/debug/mp/collect?measurement_id=G-XXXXXXXXXX&api_secret=$SECRET" \
  -H "Content-Type: application/json" -d @payload.json
# → {"validationMessages": []}   ← empty = valid
```

Then send one real order, confirm GA4 **Realtime** shows `purchase = 1` with the correct value, and send it again → the plugin must refuse (`already sent`).

### Design flaw to avoid

An earlier version skipped MP whenever the thank-you page had rendered ("client-side will handle it"). Rendering ≠ a valid event: the order is often still `pending` at that moment and the client-side event may never fire. Once Step 3 silences client-side `purchase`, send MP for **every** order that reaches a sale status — no client-side dedup flag.

## Step 5 — Enhanced Measurement pollutes `form_submit`

**Symptom**: `form_submit` counts are high on cart, product and checkout pages, while the real lead form shows 0.

**Root cause**: Enhanced Measurement's *form interactions* auto-captures every `<form>` element — including WooCommerce quantity / coupon / cart forms — under the same `form_start` / `form_submit` names you wanted for leads.

**Fix**: turn off just that toggle, keep the other enhanced-measurement features:

```bash
curl -X PATCH "https://analyticsadmin.googleapis.com/v1alpha/properties/$PROPERTY_ID/dataStreams/$STREAM_ID/enhancedMeasurementSettings?updateMask=formInteractionsEnabled" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"formInteractionsEnabled": false}'
```

- `enhancedMeasurementSettings` is exposed on the `v1alpha` surface (not `v1beta` at the time of writing) — if you get 404, check the version segment first.
- The response **omits fields whose value is `false`**. Verify by checking that `formInteractionsEnabled` is *absent* from the response, not by reading it and getting `None`.

Then emit your own `form_start` (first `focusin` inside the real lead form) and a lead event from the form's real success signal (Step 6).

## Step 6 — Lead-form events: bind to the form plugin's real AJAX contract

Guessing the contract is how listeners end up never firing. Capture one real submission in DevTools → Network and write down three things:

1. **Action name** of the submit request (exact spelling — hyphen vs underscore matters)
2. **A payload field that only exists on a real submit**, not on the impression / init / validation calls the same plugin also makes
3. **A response field that only exists when the record was saved** (a record ID) — this is both your success condition and your dedupe key

Example — a popup/form-builder plugin whose old listener never fired had three independent mistakes: it matched `action=lepopup_submit` (the real action is `lepopup-front-<x>`, hyphenated), checked `status === 'ok'` (the plugin returns `"OK"`), and did not separate submits from impression calls (only submits carry `form-data`). The fixed binding:

```js
function acmeTrack() { (window.dataLayer = window.dataLayer || []).push(arguments); } // gtag() signature
jQuery(document).ajaxComplete(function (e, xhr, settings) {
  var body = String(settings.data || '');
  if (body.indexOf('action=lepopup-front-') === -1 || body.indexOf('form-data=') === -1) return; // submit calls only
  var r; try { r = JSON.parse(xhr.responseText); } catch (err) { return; }
  if (!r || r.status !== 'OK' || !r['record-id']) return;               // saved records only
  if (window.__leadSeen && window.__leadSeen[r['record-id']]) return;   // once per record
  (window.__leadSeen = window.__leadSeen || {})[r['record-id']] = 1;
  acmeTrack('event', 'generate_lead', {
    send_to: 'G-XXXXXXXXXX', form_id: (body.match(/form-id=(\d+)/) || [])[1]
  });
});
```

If the plugin also exposes an official "after submit" JS hook, chain it as a fallback — and make both paths go through the same once-per-record lock so a submit counts exactly once. Remember the jQuery-poll trap when jQuery is delayed ([`litespeed-cache-mgmt.md`](litespeed-cache-mgmt.md) §"JS Delay").

**Test with a spy, not with GA4**: temporarily replace the push target with a recorder, replay a fake *submit* payload (must count 1) and a fake *impression* payload (must count 0), call the hook twice (still 1), then restore. No test data ever reaches the property.

## Step 7 — Separate internal data entry from website leads

A common pattern on sales-driven sites: staff type leads they received by phone / chat / walk-in **into the website form**, because it feeds the CRM. Every one of those becomes a "website conversion". On one site this inflated website conversions ~18×.

**Detect it from the form plugin's own table** (not from GA4):

| Signal | Internal data entry | Real website leads |
|---|---|---|
| Distinct IPs vs records | A handful of IPs, one IP ≈ 90%+ of rows | ~1 IP per record |
| Hour-of-day histogram | Office hours only, dip at lunch, ~0 at night | Spread across 24h, including late night |
| Field completeness | Only the minimum fields | Fuller |
| Rendered on the site? | Often the form is not even embedded publicly (check the rendered HTML for the form's CSS class) | Embedded on public pages |

Some form plugins store `created` as a UNIX **integer** — use `FROM_UNIXTIME(created)`; `DATE(created)` silently returns zero rows and looks like "no leads".

**Fix**:

- Route internal form IDs to a separate event (`internal_lead_entry`) instead of `generate_lead` — the KPI stays clean even when the office IP changes.
- Add a GA4 internal-traffic rule for the office IP and set the **Internal Traffic** data filter to *Active*: Admin → Data streams → Configure tag settings → Define internal traffic, then Admin → Data filters. This is **UI-only** — the Admin API has no data-filter resource in the versions tried (404).
- Log the activation date as a data break. Numbers after it are lower because junk is gone, not because performance dropped.
- **Verify by divergence**: the form table's records/day stay flat while GA4 thank-you views and desktop share drop. Two sources moving differently is stronger proof than one GA4 number.

**Reading trap**: `sessionSourceMedium` describes the **session**, not the page. "google / organic" on a noindex thank-you page means the visitor started the session from Google, not that the thank-you page ranks.

## Step 8 — Weekly reconciliation job (GA4 vs WooCommerce)

Automate the comparison so regressions surface in a week, not a quarter:

- WooCommerce: orders created via checkout, sale statuses, excluding test orders (tiny totals, test emails), last 7 days.
- GA4 Data API: `eventCount` + `purchaseRevenue` for `eventName = purchase`, same window.
- Flag when the gap exceeds ~15%; exit non-zero so the scheduler can alert.
- Built-in self-diagnosis:
  - GA4 revenue < 50% of WooCommerce → MP sender not running (check the `status` route, WP-Cron health)
  - WooCommerce / GA4 ≈ 100 → the ÷100 client-side `purchase` is back (plugin update re-enqueued the provider?)
  - Missing orders all share one status → a born-in-status gap the hooks don't cover

Run it where the credentials live (local `launchd` / `cron`). Cloud-scheduled agents usually don't have the service-account key or WooCommerce API keys.

## Anti-patterns

❌ Comparing only totals — per-order matching by `transaction_id` is what exposes ÷100 and missing-order patterns
❌ Adding your own `add_to_cart` / `purchase` while Site Kit still sends them → double count
❌ Trusting `view_item` in dataLayer without reading `send_to`
❌ Wrapping `gtag` to patch values (first-received event wins the dedupe)
❌ Deduping MP on "thank-you page rendered"
❌ Hard-coding the MP secret in a file
❌ Reporting form submissions as website leads without checking for internal data entry

## Cross-references

- [`ga4-admin-api.md`](ga4-admin-api.md) — service-account auth, key events, custom dimensions, Admin API gaps
- [`litespeed-cache-mgmt.md`](litespeed-cache-mgmt.md) — JS Delay: why listeners attach late and how to opt scripts out
- [`../references/pitfalls.md`](../references/pitfalls.md) §"Google for WooCommerce — `google/connected` is not a health check"
- [Measurement Protocol (GA4)](https://developers.google.com/analytics/devguides/collection/protocol/ga4) — payload reference, validation endpoint, 72h timestamp limit
- Insight source: weekly distillation 2026-09-30 (WooCommerce e-commerce site: 3-source audit, ÷100 VND, MP born-in-status + sweep, Enhanced Measurement form junk, internal lead entry)
