# Security Hardening Checklist

## `wp-config.php`

```php
// Disable file editor in admin
define('DISALLOW_FILE_EDIT', true);

// Disable plugin / theme update via admin
define('DISALLOW_FILE_MODS', false); // false = updates allowed

// Force SSL admin
define('FORCE_SSL_ADMIN', true);

// Limit post revisions
define('WP_POST_REVISIONS', 5);

// Auto-save interval
define('AUTOSAVE_INTERVAL', 300);

// Disable debug in production
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

// Memory limit
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Salt keys: regenerate via https://api.wordpress.org/secret-key/1.1/salt/
```

## File permissions

```bash
# Folders 755, files 644
find /<site-path>/ -type d -exec chmod 755 {} \;
find /<site-path>/ -type f -exec chmod 644 {} \;

# wp-config.php restrictive
chmod 600 wp-config.php

# uploads writable by the PHP user
chown -R <php-user>:<php-user> wp-content/uploads
```

(Replace `<site-path>` and `<php-user>` from the project `CLAUDE.md`.)

## Plugin security

- Wordfence Free: Firewall ON, scan weekly
- WPS Hide Login: change `/wp-admin` → `/<random-slug>`
- Limit Login Attempts: max 5 attempts, 60 min lockout
- Two Factor: enable for every admin user

## User management

- Admin user: do NOT use `admin` as the username
- Password: 16+ chars, password manager (1Password / Bitwarden)
- Application Password: for MCP only, NOT for daily use
- Disable XML-RPC if not using Jetpack / IFTTT
- Disable public REST API if not needed (be careful: MCP uses REST)

## Backup strategy

- UpdraftPlus: daily DB + weekly files → Google Drive remote
- Provider snapshot: before every large MCP session
- Off-site: WP Migrate Pro export → dedicated S3 bucket

## Recovery plan if hacked

1. Snapshot immediately (preserve evidence)
2. Restore the most recent clean backup
3. Wordfence full scan
4. Rotate ALL passwords (DB, admin, app password, FTP)
5. Update WP core + theme + plugins to latest
6. Audit user list, delete unknown users — compare `SELECT COUNT(*) FROM wp_users` with the admin UI / REST count; malware can hide accounts (see forensic #2 below)
7. Audit installed plugins **and `mu-plugins/`**, delete unknown ones — if a plugin refuses to deactivate, use the two-phase `muplugins_loaded` removal (forensic #2)
8. Check `.htaccess`, `wp-config` for injected code

## VPS-level: fail2ban whitelist override

Whitelist an IP via `jail.local` (NOT `jail.d/whitelist.conf`) — jail-level overrides DEFAULT, the reverse does not.

```ini
# /etc/fail2ban/jail.local
[DEFAULT]
ignoreip = 127.0.0.1/8 ::1 <YOUR-OFFICE-IP>

[sshd]
enabled = true
ignoreip = 127.0.0.1/8 ::1 <YOUR-OFFICE-IP>
```

`systemctl restart fail2ban`. Verify: `fail2ban-client status sshd` → `Currently banned` does not include the whitelisted IP.

## VPS-level: `iptables -F` flush risk when default policy is DROP

`iptables -F INPUT` does NOT actually flush if the default policy is DROP — the server gets locked out permanently (no console access = lost server).

**Safe order**:
```bash
iptables -P INPUT ACCEPT     # 1. switch to ACCEPT first
iptables -F INPUT             # 2. flush rules
# 3. add new rules
iptables -P INPUT DROP        # 4. switch back to DROP at the end
```

Self-check: `iptables -L INPUT -v` to see the default policy before flushing.

## mu-plugin API check before calling a method

Elementor Pro APIs change between versions. For example, `\ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager::is_location_filled()` does not exist in v4.0.1 → fatal error 500 site-wide when the mu-plugin loads.

**Safe pattern**:
```bash
# 1. Grep the source code before calling
grep -n "function is_location_filled" \
  /path/to/elementor-pro/modules/.../classes/locations-manager.php
# Empty result = method does NOT exist in this version

# 2. Or reflection check in PHP
if (method_exists('\\ElementorPro\\Modules\\ThemeBuilder\\Classes\\Locations_Manager', 'is_location_filled')) {
    // safe to call
}
```

Mu-plugins load automatically → deploying with a wrong API call kills the site instantly. Run a local PHP unit test or grep the source before pushing.

## WordPress hack forensic — rogue sitemap via `.htaccess` injection

**Symptom**: Google Search Console shows a sudden spike of `Not found (404)` — tens of thousands of URLs matching patterns like `/[a-z]\d{14}.html`, `/shop/pg/\d{8}-\d`, `/cate-\d+-\d+` on a site that isn't e-commerce. Site owner sees ranking impact from spam-URL crawl budget waste.

**Root cause**: Attacker injects a block at the top of `.htaccess` (root):

```apache
<Files "sxallsitemap.xml">
    RewriteRule ^sxallsitemap\.xml$ index.php [L]
</Files>
```

Combined with a backdoor (hooked via a rogue plugin / mu-plugin / theme edit) that generates the rogue sitemap listing spam URLs. Google fetches `/sxallsitemap.xml` → follows every `<loc>` → indexes the spam URLs → search results poisoned. Colloquially "sx spam" after the sitemap filename.

### Forensic ladder — determine if still active or already cleaned

Run each in order; a clean result narrows the diagnosis.

| Step | Test | Read |
|---|---|---|
| a | Probe a spam URL with normal User-Agent vs `Googlebot/2.1` UA | Same response = no cloaking (likely cleaned); different = cloaking active |
| b | Probe a URL that FITS the spam pattern but doesn't exist (e.g. `/abc12345678901234.html`) | 404 = generator gỡ; 200 with spam page = still generating |
| c | `curl -I "$SITE/sxallsitemap.xml"` | 404 = generator removed; 200 XML with `<loc>` spam = still active |
| d | cPanel scan `.htaccess` root — grep for spam patterns like `<Files.*sxall`, `RewriteRule.*sxall`, unfamiliar `RewriteRule` at top of file | Injection block present = still compromised |
| e | cPanel scan root + wp-content for `x.php` / random-named PHP with recent `mtime` matching spam window (`.QUARANTINE` folders from Imunify360 also flag) | Any `.php` in `wp-content/uploads/` = **RED FLAG** — never legitimate |
| f | grep `wp-config.php` + `wp-load.php` + `wp-settings.php` for injection signatures: `eval(`, `base64_decode(`, `gzinflate(`, `str_rot13(` | Any hit = injected loader |

### Cleanup sequence (order matters)

```bash
# 1. BACKUP first (in case rollback needed)
cp .htaccess .htaccess.pre-cleanup-$(date +%Y%m%d)

# 2. Remove the injection block from .htaccess
# (surgical edit — do NOT rewrite .htaccess from template, may lose legitimate rules)

# 3. Add [G,L] 410 Gone rules per spam pattern (BEFORE the WordPress rules block)
# Google removes 410'd URLs faster than 404'd URLs
#
# CRITICAL: pattern regex must be SPECIFIC — must not match legitimate URLs
```

Example `.htaccess` addition:

```apache
# 410 rogue spam URLs — do not match legit sitemaps or Vietnamese slugs
RewriteEngine On
RewriteRule ^[a-z][0-9]{14}\.html$ - [G,L]
RewriteRule ^shop/pg/[0-9]{8}-[0-9]+$ - [G,L]
RewriteRule ^cate-[0-9]+-[0-9]+$ - [G,L]
```

**Pattern safety rules**:

- `^cate-[0-9]` matches `/cate-123-456/` but NOT `/category-sitemap.xml` — anchor + `-` + digits guards against category slugs
- `^[a-z][0-9]{14}\.html$` matches `/abc12345678901234.html` but NOT Vietnamese-locale slugs (which contain diacritics + `-` separators)
- **Test each pattern** against a known legit URL before deploying: `curl -I "$SITE/known-legit-page/"` — expect 200

```bash
# 4. Remove any rogue PHP files
# (only after verifying they're not legit — cross-check filename against fresh WP install)

# 5. If Imunify360 quarantined files: check .QUARANTINE folders — attacker artifacts stay quarantined
```

### Interpreting the 404 backlog

`27,790 URL 404` in GSC is **benign backlog** after cleanup — Google removes 404'd URLs on natural re-crawl cycle (~4-8 weeks). Does NOT penalize ranking. Do NOT try to "speed up removal" via URL removal tool for every spam URL — 410 rule accelerates naturally, URL removal tool has quota limits and doesn't scale to 27k+.

Track backlog reduction: GSC → Pages → Not indexed → Not found (404) → sort by URL pattern → verify the count drops week over week after 410 rules deploy.

### Reusability

UNIVERSAL — pattern applies to any hacked WP site emitting rogue sitemaps or shell-shell backdoors. Adapt the URL patterns to what the specific site's spam looks like. The forensic ladder (probe → GB UA test → sitemap fetch → cPanel scan) doesn't change.

Cross-references: [`deployment.md`](deployment.md) §"Shared host WAF (e.g. Imunify360)" — WAF quarantine behavior; [`mu-plugin-patterns.md`](mu-plugin-patterns.md) — how injected backdoors load.

## WordPress hack forensic #2 — blockchain-hosted JS loader + hidden admin via fake plugins

**Symptom**: a large obfuscated inline script (javascript-obfuscator style: `_0x…` identifiers, RC4-encoded string arrays) appears on frontend pages — sometimes twice per page, adding ~100KB — and can disappear an hour later without anyone touching the site. `wp-content/plugins/` contains plugins you never installed, named like real ones with a numeric suffix (`<legit-plugin-name>-177/`, `<plausible-name>-196/`). There may be an administrator you cannot see in *Users*.

### How the pieces fit (observed on a WooCommerce site, 2026-08)

| Component | Behaviour |
|---|---|
| **Loader plugin** (fake "link checker") | Enqueues a small JS on every frontend page. The JS POSTs to `admin-ajax.php` with a `wp_ajax_nopriv_*` action. PHP performs an `eth_call` JSON-RPC against public **BSC testnet** RPC endpoints, ABI-decodes the returned string (= JavaScript) and sends it back; the loader injects it with `createElement('script')`. The command-and-control lives in a smart contract ("EtherHiding"): there is no domain to block or take down, and the attacker swaps the payload by writing to the contract — which is why the injection appears and disappears remotely. |
| **Persistence plugin** (fake "integrity/security" plugin) | Creates an admin account (observed login `sys_maint`, `@localhost.local` email; credentials stored as hex constants so grep for the plain strings misses them). Hides it from the Users screen (injects into `pre_user_query` / `query_where`) and from REST user listings. Blocks its own removal: `wp_die()` on deactivate/delete, strips the row action links, shows a "Protected" label. |
| **Decoy directories** | Plugin folders whose main file is 0 bytes — noise to slow down review. |

### Detection checklist

```sql
-- 1. Hidden users: DB count vs what WordPress shows
SELECT COUNT(*) FROM wp_users;          -- compare with /wp-json/wp/v2/users?context=edit&per_page=100 (admin auth)
SELECT ID, user_login, user_registered FROM wp_users ORDER BY user_registered DESC LIMIT 10;
-- A mismatch means a filter is hiding users. user_registered of the hidden admin = earliest known compromise date.
```

- **`active_plugins` vs the plugins directory** — names with random numeric suffixes, folders whose files share one recent mtime, 0-byte main files.
- **Anonymous frontend fetch** (logged out, cache-busted) — grep for `eth_call`, the loader filename, `_0x` density, unusually large inline `<script>`.
- **The AJAX action itself** — `curl -d 'action=<suspect>' $SITE/wp-admin/admin-ajax.php` while logged out: returning JavaScript = live loader.
- **Attribute hook callbacks to files** — iterate `$wp_filter['init']->callbacks` (and `wp_head`, `wp_footer`, `pre_user_query`), resolve each with `ReflectionFunction` / `ReflectionMethod` → `getFileName()`. The fastest way to prove which file does what (same reflection technique as [`mu-plugin-patterns.md`](mu-plugin-patterns.md) Pattern 1).
- **Full filesystem scan without truncation** — sites with 100k+ files: make sure the scanner reports how many files it read; a silently truncated scan reports "clean".

### Removal — two-phase MU-plugin

The UI route is blocked by the malware itself, and regular plugins cannot be trusted to remove each other. MU-plugins load **before** regular plugins, which gives you a window the malware can't defend:

1. **Phase 1 — hook `muplugins_loaded`**: remove the malicious entries from the `active_plugins` option. On the next request WordPress never includes them, so their self-protection never runs.
2. **Phase 2 — hook `init`, on a later request**: delete the plugin directories; delete the hidden user(s) (reassign their content); delete the malware's options and usermeta; reset `default_role` to `subscriber` and check `users_can_register`; destroy every session (`WP_Session_Tokens::destroy_all_for_all_users()`); purge page cache.

**Same-request resurrection trap**: in the request where you delete things, the malware may already be loaded (it was included before your option change could matter). Its `init` callback at a later priority then **re-creates the hidden admin with a new ID**. That is one leftover execution, not a second backdoor. Deploy a temporary watchdog MU-plugin that deletes the account if it reappears and logs each kill; expect **exactly one** kill, then silence across many requests. Remove the watchdog after credentials are rotated.

Keep every forensic / cleanup route read-only by default (`apply=0`), gated on `manage_options`, and delete these MU-plugins when finished — they are powerful endpoints.

### After cleanup — the part automation cannot do

- Rotate **all** credentials: admin passwords, **Application Passwords** (then update every MCP connector config that embeds them, or the connectors break), DB password, hosting / SFTP, and regenerate the salts in `wp-config.php`.
- Check the workstation of whoever administers the site (infostealer / "paste this into Run" ClickFix lures are a common entry point).
- An attacker who held admin for days could read everything: treat the database as exfiltrated — customer PII, order data, password hashes — and assess notification obligations for your jurisdiction.
- Preserve evidence before cleaning: save one page HTML with the injected blob and the raw loader JS; note the timeline (hidden admin's `user_registered`, file mtimes).

### Reusability

UNIVERSAL — any WordPress site. The loader's C2 channel will vary (blockchain RPC today, something else tomorrow); the detection ladder (DB vs REST user count → `active_plugins` vs disk → anonymous frontend fetch → hook-to-file attribution) and the two-phase `muplugins_loaded` removal do not. Different vector from the rogue-sitemap case above — run both ladders on any hacked site.
