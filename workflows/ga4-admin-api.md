# GA4 Admin API — write config via service account (escape from Site Kit's readonly cage)

Google Site Kit connects a WP site to GA4 for reporting, but its REST endpoints run at `analytics.readonly` scope in the WordPress App-Password context. You cannot use Site Kit REST to WRITE GA4 configuration (custom dimensions, key events, event modifications, audiences). The clean escape hatch is the **Analytics Admin API v1beta** authenticated via a Google Cloud service account, JWT-signed with `openssl` (no PyJWT needed).

## When to use this

✅ Setting up custom dimensions (e.g. `page_variant`, `logged_in`) BEFORE event data collects — dimensions don't retroact
✅ Marking events as key events / conversions (`click_zalo`, `contact_form_submit`)
✅ Auditing + fixing polluted key event lists (`first_visit`, `purchase` on non-e-commerce sites)
✅ Renaming events, creating audiences, managing property access programmatically
✅ Any WRITE operation the GA4 UI supports

❌ Reading reports / dashboards (Site Kit already does this well via `analytics.readonly`)
❌ Real-time streaming (Measurement Protocol, not Admin API)
❌ One-off manual changes (GA4 web UI is simpler if you only touch once)

## Why Site Kit's REST doesn't work for writes

Site Kit exposes `/wp-json/site-kit/v1/modules/analytics-4/data/<datapoint>` and accepts POST, and can even auto-create `googlesitekit_*`-prefixed custom dimensions from the wp-admin UI. But when called via App-Password REST context, Site Kit's `core/user/data/authentication` returns:

```json
{
  "authenticated": false,
  "grantedScopes": []
}
```

The App Password authenticates you as a WP admin but doesn't grant OAuth scopes. Site Kit's backend requires `analytics.edit` scope for write ops, but the fallback-scope granted in REST context is only `analytics.readonly`. Result: `missing_required_scopes` on any write attempt. Dead end unless you rebuild the OAuth flow (which requires a browser session — not scriptable).

## Setup — one-time per GCP project

### 1. Enable APIs in your GCP project

```bash
# Via gcloud CLI (or via GCP Console → APIs & Services → Library)
gcloud services enable analyticsadmin.googleapis.com --project=<gcp-project-id>
gcloud services enable analyticsdata.googleapis.com --project=<gcp-project-id>  # for reads too
```

### 2. Create service account + download key

```bash
gcloud iam service-accounts create ga4-admin-sa \
    --display-name="GA4 Admin API SA" \
    --project=<gcp-project-id>

gcloud iam service-accounts keys create ga4-sa-key.json \
    --iam-account=ga4-admin-sa@<gcp-project-id>.iam.gserviceaccount.com
```

`ga4-sa-key.json` contains `private_key` (RSA), `client_email`, `token_uri`. Keep it out of git — treat like a password.

### 3. Grant SA access to the GA4 property

**This is a separate permission layer** from GCP IAM. Even with the SA created and API enabled, calls will 403 until you add the SA to the specific GA4 property:

1. GA4 UI → Admin → Property access management → Add user
2. Email: `ga4-admin-sa@<gcp-project-id>.iam.gserviceaccount.com`
3. Role: **Editor** (needed for writes) or **Marketer** (for key events only)

Failure to do this step returns `403 PERMISSION_DENIED "The caller does not have permission"` — different error from "API not enabled" (`SERVICE_DISABLED`). Diagnose the two layers separately.

## Authenticate — JWT signed via openssl (no PyJWT)

Same pattern as Google Indexing API. Standard bash + openssl only:

```bash
#!/bin/bash
# get_ga4_token.sh — exchange service account JWT for OAuth access token

SA_KEY_FILE="ga4-sa-key.json"
SCOPE="https://www.googleapis.com/auth/analytics.edit"

# Extract SA fields
SA_EMAIL=$(jq -r .client_email "$SA_KEY_FILE")
PRIVATE_KEY=$(jq -r .private_key "$SA_KEY_FILE")
TOKEN_URI=$(jq -r .token_uri "$SA_KEY_FILE")

# JWT header + claim
IAT=$(date +%s)
EXP=$((IAT + 3600))

HEADER='{"alg":"RS256","typ":"JWT"}'
CLAIM="{\"iss\":\"$SA_EMAIL\",\"scope\":\"$SCOPE\",\"aud\":\"$TOKEN_URI\",\"iat\":$IAT,\"exp\":$EXP}"

# base64url encode (no padding, - and _ instead of + and /)
b64url() {
    openssl base64 -A | tr -- '+/' '-_' | tr -d '='
}

HEADER_B64=$(printf '%s' "$HEADER" | b64url)
CLAIM_B64=$(printf '%s' "$CLAIM" | b64url)
SIGNING_INPUT="${HEADER_B64}.${CLAIM_B64}"

# Sign with RS256
SIGNATURE=$(printf '%s' "$SIGNING_INPUT" | \
    openssl dgst -sha256 -sign <(printf '%s' "$PRIVATE_KEY") | b64url)

JWT="${SIGNING_INPUT}.${SIGNATURE}"

# Exchange JWT for access token
ACCESS_TOKEN=$(curl -s -X POST "$TOKEN_URI" \
    -d "grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=$JWT" \
    | jq -r .access_token)

echo "$ACCESS_TOKEN"
```

Token TTL is 1 hour. Cache in a var for the duration of your script; re-run when it expires.

**No PyJWT required** — this is pure bash + `openssl dgst -sha256 -sign` + `openssl base64 -A`. Same pattern works for Indexing API, Search Console API, any Google API that accepts JWT bearer auth. (Auth pattern only — read §"Indexing API — scope limitation" before using the Indexing API for anything.)

## Ops — custom dimensions + key events

Base URL: `https://analyticsadmin.googleapis.com/v1beta/properties/{property_id}`

Property ID is the numeric ID from GA4 (e.g. `123456789`), not the measurement ID (`G-XXXXX...`). Find it: GA4 → Admin → Property settings → "PROPERTY ID".

### Create a custom dimension

```bash
TOKEN=$(./get_ga4_token.sh)
PROPERTY_ID=123456789

curl -X POST "https://analyticsadmin.googleapis.com/v1beta/properties/$PROPERTY_ID/customDimensions" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "parameterName": "page_variant",
        "displayName": "Page Variant (A/B test)",
        "scope": "EVENT",
        "description": "Which variant the user saw (a|b|control)"
    }'
```

**Not retroactive**: only events collected AFTER the dimension is created will populate it. Create dimensions BEFORE launching the A/B test / event tracking that will use them.

### Mark an event as a key event (formerly "conversion")

```bash
curl -X POST "https://analyticsadmin.googleapis.com/v1beta/properties/$PROPERTY_ID/keyEvents" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "eventName": "click_zalo",
        "countingMethod": "ONCE_PER_SESSION"
    }'
```

**Two important properties**:

- `keyEvents.create` accepts an `eventName` **not yet collected** — mark it as a key event BEFORE the tracking fires. GA4 will count it as a conversion from the first fire.
- `countingMethod: ONCE_PER_SESSION` — 1 user clicking `tel:` link 3 times in a session = 1 conversion (typical for contact intent). Default `ONCE_PER_EVENT` = 3 conversions (better for e-commerce purchase events).

### Delete a key event (un-mark as conversion)

```bash
# Find its ID first
KE_ID=$(curl -s "https://analyticsadmin.googleapis.com/v1beta/properties/$PROPERTY_ID/keyEvents" \
    -H "Authorization: Bearer $TOKEN" | jq -r '.keyEvents[] | select(.eventName=="first_visit") | .name' | awk -F/ '{print $NF}')

curl -X DELETE "https://analyticsadmin.googleapis.com/v1beta/properties/$PROPERTY_ID/keyEvents/$KE_ID" \
    -H "Authorization: Bearer $TOKEN"
# → HTTP 200 with EMPTY body
```

**Return body caveat**: DELETE returns empty body. Do NOT `json.loads(response)` — you'll get `json.JSONDecodeError` on empty string. Check response `status_code == 200` only.

**Not retroactive**: deleting a key event stops counting future fires as conversions, but historical data keeps the conversion attribution. The conversion column in reports drops going forward.

## Audit existing key events BEFORE adding new ones

Before adding a new key event, always list what's already marked:

```bash
curl -s "https://analyticsadmin.googleapis.com/v1beta/properties/$PROPERTY_ID/keyEvents" \
    -H "Authorization: Bearer $TOKEN" | jq '.keyEvents[] | {eventName, countingMethod}'
```

### Common pollution patterns

Non-e-commerce sites frequently arrive with 3 default-marked events that turn "conversions" into meaningless noise:

| Event name | Why it's junk on a non-e-commerce site |
|---|---|
| `first_visit` | Fires for EVERY new user → conversion count ≈ new-user count. Kills the ability to see actual business conversions. |
| `click` | GA4 auto-collects outbound clicks as `click` events. If you also add `click_zalo` / `click_call` custom events → **double count**. Every Zalo click = 1 `click` (auto) + 1 `click_zalo` (custom) = 2 conversions. Nonsense. |
| `purchase` | Site doesn't sell anything → this event never fires or fires from stray GTM installs. Column shows 0 forever. |

Delete these three (via the DELETE op above) before adding contact-intent key events (`click_zalo`, `click_call_button`, `contact_form_submit`). The conversion column then reflects business reality.

### Correct counting method per intent

| Intent | `countingMethod` | Why |
|---|---|---|
| Contact intent (call / Zalo / form) | `ONCE_PER_SESSION` | 1 user clicking 3 times in session = 1 lead |
| Purchase / transaction | `ONCE_PER_EVENT` | Each purchase is a distinct conversion, even in same session |
| Video / content engagement | `ONCE_PER_EVENT` | Multiple video plays = multiple engagements |
| Newsletter signup | `ONCE_PER_SESSION` | Duplicate submissions in same session = 1 signup (spam-safe) |

## Diagnosing permission errors — 2-layer check

When calls fail, distinguish:

| Error | Layer | Fix |
|---|---|---|
| `SERVICE_DISABLED` | GCP project | Enable `analyticsadmin.googleapis.com` in GCP Console → APIs & Services → Library |
| Token exchange 401 / 400 | GCP IAM | Service account key file wrong / expired; regenerate key |
| `403 PERMISSION_DENIED "The caller does not have permission"` | GA4 property access | Add SA email to GA4 → Admin → Property access → Editor role |
| `403 "missing_required_scopes"` | Wrong scope in JWT | Ensure `scope` in JWT claim = `https://www.googleapis.com/auth/analytics.edit` |
| `404 "Requested entity was not found"` | Wrong property ID | Use numeric property ID (Admin → Property settings), not measurement ID (`G-...`) |

Getting a token successfully = layer 1 OK (API enabled + SA valid). Getting 403 on property call = layer 2 fail (SA not added to GA4 property).

## Same service account → Search Console API (index diagnosis)

The JWT flow above works for Search Console with scope `https://www.googleapis.com/auth/webmasters.readonly` (or `webmasters` for sitemap submit). Add the service-account email as a user of the GSC property (Settings → Users and permissions) — *Full* is enough for these read APIs; only the Indexing API needs *Owner*. Property IDs are `sc-domain:example.com` (domain property) or `https://example.com/` (URL-prefix) — URL-encode them in paths.

### URL Inspection — the fastest way to tell *why* a URL is not indexed

```bash
curl -s -X POST "https://searchconsole.googleapis.com/v1/urlInspection/index:inspect" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"inspectionUrl":"https://example.com/some-post/","siteUrl":"sc-domain:example.com"}' \
  | jq '.inspectionResult.indexStatusResult | {coverageState, verdict, lastCrawlTime, googleCanonical, sitemap, referringUrls}'
```

Read `coverageState` as a diagnosis, not just a status:

| `coverageState` | Meaning | Where to look |
|---|---|---|
| `Submitted and indexed` | Fine | — |
| `URL is unknown to Google` | Never discovered / never crawled — a **discovery or crawl-demand** problem, not a quality verdict | Sitemap freshness, crawl budget sinks (spam 404/410 backlog after a hack, faceted URLs), link paths |
| `Discovered - currently not indexed` | Known but not yet crawled — crawl budget / demand | Same as above, plus server speed |
| `Crawled - currently not indexed` | Google fetched it and chose not to index — **quality / duplication / priority** | Content depth, duplicates, canonical signals |

Sample 10–20 URLs **per content type** (money pages, blog posts, archives) before concluding anything — one site that "had only 23 indexed pages" turned out to have 7 of 8 key service pages indexed, with the gap concentrated in blog posts that Google had never seen. That changes the fix completely (discovery, not trust or quality). The API is quota-limited per property per day — sample, don't sweep. The "Request indexing" button has **no API**; it stays a manual GSC UI action.

### Search Analytics + Sitemaps

```bash
SITE_ENC=$(python3 -c 'import urllib.parse,sys; print(urllib.parse.quote(sys.argv[1], safe=""))' "sc-domain:example.com")
curl -s -X POST "https://searchconsole.googleapis.com/webmasters/v3/sites/$SITE_ENC/searchAnalytics/query" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"startDate":"2026-06-01","endDate":"2026-06-28","dimensions":["page"],"rowLimit":1000}'

curl -s "https://searchconsole.googleapis.com/webmasters/v3/sites/$SITE_ENC/sitemaps" -H "Authorization: Bearer $TOKEN"
```

⚠️ In the Sitemaps response, `contents[].indexed` is a **deprecated field** — it comes back 0 or missing no matter how many URLs are indexed. Do not read it as "0 pages indexed". Use `submitted`, `lastDownloaded`, `errors`, `warnings` there, and URL Inspection / the Pages report for index state.

## Indexing API — scope limitation (read before using it)

Google's Indexing API is supported **only** for pages carrying `JobPosting` or `BroadcastEvent` (livestream `VideoObject`) structured data. For any other URL it still answers `200 OK` and consumes quota — which makes it look like it worked — but it is not a supported way to get normal pages (blog posts, service pages, products) crawled or indexed. Two sites in this portfolio pushed batches of ordinary URLs through it after a cleanup: one saw no attributable effect, the other saw crawls happen but no indexing it could credit to the API. It may nudge a crawl in practice; it guarantees nothing, and Google can stop honoring out-of-scope calls at any time.

For normal pages: fresh sitemap + internal links from indexed pages + URL Inspection → *Request indexing* in the GSC UI for the few URLs that matter most. Where the Indexing API *is* appropriate, the caller must be a verified **Owner** of the property (a delegated owner is fine) — *Full user* is rejected.

## OAuth user flow — when a service account can't be added

Some owners won't add a service-account email to their properties. Alternative: one installed-app OAuth flow as a person who already has access, requesting all scopes at once (`analytics.edit`, `analytics.readonly`, `webmasters`, `indexing` as needed), caching the refresh token locally (never in git), and calling the REST endpoints with that token. Gotchas that cost time:

- **API usage is attributed to the OAuth client's project *number*** — the numeric prefix of the `client_id`. Enable each API on *that* project. A project has both an ID string and a number; they are the same project, so notes that pair a number with a different-looking ID cause needless confusion. `SERVICE_DISABLED` errors name the project number — trust that.
- **Consent screen in "Testing" mode** → the authorizing Google account must be listed as a *test user*, or the flow fails before any scope is granted.
- **Owner-only APIs** (Indexing) → the authorizing account must be an Owner / delegated owner of the GSC property, not a Full user.

## Admin API gaps — do these in the UI

| Task | API status | Where |
|---|---|---|
| Internal-traffic rule + Data filter activation | No data-filter resource in the Admin API versions tried (404) | GA4 Admin → Data streams → Configure tag settings → Define internal traffic; Admin → Data filters → set *Active* |
| Enhanced Measurement toggles | `enhancedMeasurementSettings` on the `v1alpha` surface | See [`woocommerce-ga4-ecommerce.md`](woocommerce-ga4-ecommerce.md) Step 5 |
| Measurement Protocol secrets | `v1beta` `…/dataStreams/{id}/measurementProtocolSecrets` | See [`woocommerce-ga4-ecommerce.md`](woocommerce-ga4-ecommerce.md) Step 4a |

## Cross-references

- [`litespeed-cache-mgmt.md`](litespeed-cache-mgmt.md) — JS Delay traps that prevent events from firing at all (fix client-side FIRST, before adding GA4 config)
- [`../references/pitfalls.md`](../references/pitfalls.md) — Site Kit REST scope limitations
- [Google Analytics Admin API v1beta docs](https://developers.google.com/analytics/devguides/config/admin/v1) — canonical reference
- Insight source: weekly distillation 2026-07-21 (GA4 Admin API via SA + openssl JWT, Site Kit readonly cage, key event pollution audit)
- Insight source: weekly distillation 2026-09-30 (Search Console URL Inspection triage, Sitemaps `indexed` deprecated, Indexing API scope limitation confirmed on 2 sites, OAuth project-number + test-user + Owner gotchas, Admin API gaps)
- [`woocommerce-ga4-ecommerce.md`](woocommerce-ga4-ecommerce.md) — ecommerce events, server-side `purchase`, Enhanced Measurement form junk
