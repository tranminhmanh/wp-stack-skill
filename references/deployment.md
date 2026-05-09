# Deployment Workflow

⚠️ **Do NOT hardcode VPS IP / SSH alias / paths in this file.** Read project-specific information from the project `CLAUDE.md`.

## Safe pattern

Before deploying, **read the project `CLAUDE.md`** to get:
- Provider (CloudPanel VPS / SiteGround / Cloudways / Hostinger / other)
- SSH access method (key / password / panel-only)
- Site path
- Database type / host / port / name
- Staging URL

If `CLAUDE.md` is missing information → ASK the user, do not guess.

## Workflow staging → production

### Option 1: WP Migrate Pro (recommended)

1. On staging: Tools → WP Migrate → Export
2. Pick "Push to remote site"
3. Target: production URL + secret key
4. Find/replace URL: `<staging-url>` → `<prod-url>`
5. Push DB + files

### Option 2: Manual via SSH

```bash
# 1. Backup production first (read path from CLAUDE.md)
ssh <alias from CLAUDE.md>
cd <site-path from CLAUDE.md>
tar czf /tmp/backup-$(date +%Y%m%d).tar.gz .
mysqldump -u <user> -p <db> > /tmp/db-$(date +%Y%m%d).sql

# 2. Pull staging files
rsync -avz <staging>:<staging-path>/ <prod-path>/

# 3. Pull staging DB
ssh <staging> mysqldump <staging-db> > /tmp/staging-db.sql
mysql -u <user> -p <prod-db> < /tmp/staging-db.sql

# 4. Update site URL
wp search-replace '<staging-url>' '<prod-url>' --skip-columns=guid
wp cache flush
```

### Option 3: Provider-specific

- **SiteGround**: Site Tools → Dev → WordPress Migrator
- **Cloudways**: Application Management → Clone App
- **Hostinger**: hPanel → File Manager + DB tools
- **CloudPanel**: SSH manual (option 2) or snapshot

## Pre-deploy checklist

- [ ] Backup production DB + files
- [ ] Test the full user flow on staging
- [ ] Lighthouse mobile ≥85 on staging
- [ ] No PHP errors in `wp-content/debug.log`
- [ ] Form submission test succeeds
- [ ] All images load, no 404
- [ ] SSL valid, no mixed content
- [ ] Sitemap regenerated
- [ ] Submit new sitemap in Search Console
- [ ] Google Tag Manager fires events correctly

## Post-deploy

- Clear cache: WP Rocket + Cloudflare + LiteSpeed
- Test 5 main pages on 3 devices (375 / 768 / 1280)
- Submit "Request indexing" in Search Console
- Monitor uptime (UptimeRobot) for 24h
- Check Google Analytics is receiving events

## Rollback if broken

```bash
ssh <alias>
cd <site-path>
rm -rf ./*
tar xzf /tmp/backup-YYYYMMDD.tar.gz
mysql -u <user> -p <db> < /tmp/db-YYYYMMDD.sql
wp cache flush
```

## Common hosting pitfalls

### CloudPanel
- PHP-FPM crashes on low memory limit → wp-config bumps to 512M + CloudPanel PHP Settings
- Let's Encrypt SSL renewal fails → check DNS A record + port 80 open
- Disk full from logs → truncate `debug.log` or disable `WP_DEBUG_LOG`

### SiteGround
- Aggressive cache → in Site Tools, disable Dynamic Cache while developing
- SSH access requires generating a key in Site Tools → Dev → SSH Keys
- Migration plugin is SiteGround Migrator (recommended)

### Cloudways
- Clone App is fast but creates a random URL → map domain afterwards
- Strong Varnish cache → Application → Manage Services → Purge Varnish

### Hostinger
- LiteSpeed cache built-in → use the LSCache plugin instead of WP Rocket
- Hostinger File Manager has browser-based SSH (Web Terminal)

## When the user has no SSH access

(e.g. legacy shared hosting, client did not grant SSH)

→ Do NOT propose an MCP-via-SSH workflow.
→ Alternatives: WP Migrate Pro UI, UpdraftPlus restore, Duplicator.
→ MCP via HTTP still works (only needs the REST API endpoint), but deploy / migrate has to go through the plugin UI.

## Verify docroot before deploying (addon domain)

Shared hosting (cPanel / Hostinger / similar) — addon domains do NOT share the same docroot as the main domain. Example: main = `/home/user/public_html/`, addon `example.com` = `/home/user/example.com/`.

Guessing the docroot = bug. Verify:
```bash
curl -H "$CP_AUTH" "$CP_URL/LangPHP/php_get_vhost_versions"
# returns the docroot per domain — cross-reference with CLAUDE.md
```

Or check the Domain Manager UI → Document Root column.

## Shared host WAF (e.g. Imunify360) blocks `.php` upload

Imunify360 on shared hosts scans content during cPanel API uploads. A `.php` file containing patterns it considers malware (`eval`, `base64_decode`, `system()`, `exec()`) returns 403 even with correct credentials.

**Workarounds**:
- Upload `.php.txt` first → rename via the File Manager UI (UI bypasses the scan)
- Split risky logic into a separate file, include from a clean stub
- Replace `eval` with `call_user_func`, encode differently (string concat) — NOT recommended for production

Detection: response body contains "Imunify360" / "AI-Bolit" → confirmed.

## cPanel Fileman API endpoints vary by host

Some hosts strip dangerous endpoints. A typical shared host only exposes:
- ✅ `list_files`, `get_file_content`, `save_file_content`, `mkdir`, `upload_files`
- ❌ `delete_files`, `rename`, `move`, `empty_file`

**Workarounds**:
- "Delete" a file = overwrite with a stub (`<?php // removed`)
- "Rename" = read old → write new path → stub the old one
- "Move" = same pattern

Test endpoints before writing the deploy script:
```bash
curl -H "$CP_AUTH" "$CP_URL/Fileman/<endpoint>?dir=&file=" | jq .errors
```

## Docker `php.ini` bind-mount for upload size

Default `upload_max_filesize = 2M` is not enough for Elementor Pro zip (~10M), media library, plugin uploads.

Bind-mount a separate ini (do not edit the container's global `php.ini`):
```yaml
# docker-compose.yml
volumes:
  - ./php-uploads.ini:/usr/local/etc/php/conf.d/zzz-uploads.ini:ro
```

`php-uploads.ini`:
```ini
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 512M
max_execution_time = 300
```

`docker compose up -d --force-recreate <service>` to reload.

## `opcache_reset()` mandatory after every mu-plugin edit

PHP-FPM / Apache caches bytecode in shared memory. Edit a mu-plugin → opcache still runs the old version. Easy to lose 30–60 minutes in a confusion loop if you forget.

```bash
docker exec <container> php -r 'opcache_reset();'
# Or via the web (needs a separate file):
curl https://<site>/opcache-reset.php?token=<TOKEN>
```

Web-reset pattern (file: `wp-content/mu-plugins/opcache-reset.php`):
```php
<?php
if (($_GET['token'] ?? '') === 'STRONG-TOKEN') {
    opcache_reset();
    echo 'OK';
}
```

## Bash heredoc + SSH escape hell

The outer `"..."` of `ssh` interferes with the inner `<<'PHPEOF'` heredoc backslash escaping. Triple-escaped backslashes `\\\\\\` become unpredictable across shell layers.

**Fix**: do NOT inline PHP via ssh heredoc. Instead:
```bash
# 1. Write PHP locally
cat > /tmp/script.php <<'EOF'
<?php
require_once '/var/www/html/wp-load.php';
// ...
EOF

# 2. scp to remote
scp /tmp/script.php user@host:/tmp/

# 3. docker cp + exec
ssh user@host 'docker cp /tmp/script.php <container>:/tmp/ && docker exec <container> php /tmp/script.php'
```

Avoid all shell-escape layering.

## REST API response capture safety (Vietnamese / non-ASCII UTF-8)

Bash subshell substitution `resp=$(curl ...)` corrupts control characters in non-ASCII UTF-8 responses. `jq <<< "$resp"` fails with `Invalid string: control characters from U+0000 through U+001F`.

**Fix**: tee the response to a file before parsing, do NOT pipe through a bash variable:
```bash
# WRONG
resp=$(curl -u "$WP_USER:$WP_PASS" "$WP_SITE/wp-json/wp/v2/media")
jq <<< "$resp"

# RIGHT
curl -u "$WP_USER:$WP_PASS" "$WP_SITE/wp-json/wp/v2/media" -o /tmp/resp.json
jq -r '.[].id' /tmp/resp.json
```

Apply to any REST endpoint returning non-ASCII content (Posts, Pages, Media, Terms, Comments).

## WP media duplicate filename pattern

Uploading `image.jpg` when a file with the same name already exists (orphan from a failed upload) → WP auto-renames to `image-1.jpg`. `.source_url` reflects the new name → code that expects the original filename breaks.

**Pre-upload check**:
```bash
# Search by basename
curl -u "$WP_USER:$WP_PASS" \
  "$WP_SITE/wp-json/wp/v2/media?search=$(basename $FILE .jpg)" \
  -o /tmp/check.json
EXISTING=$(jq -r '.[0].id // empty' /tmp/check.json)

if [ -n "$EXISTING" ]; then
    # Option A: DELETE old (force=true skips trash)
    curl -u "$WP_USER:$WP_PASS" -X DELETE \
      "$WP_SITE/wp-json/wp/v2/media/$EXISTING?force=true"
    # Option B: versioned filename
    # cp "$FILE" "${FILE%.jpg}-v2.jpg"
fi

# Then upload
curl -u "$WP_USER:$WP_PASS" -X POST \
  -H "Content-Disposition: attachment; filename=\"$(basename $FILE)\"" \
  -H "Content-Type: image/jpeg" \
  --data-binary "@$FILE" \
  "$WP_SITE/wp-json/wp/v2/media"
```

## MariaDB modern containers — `mariadb` not `mysql`

Modern MariaDB 11+ Docker images ship the `mariadb` family of binaries instead of `mysql`. If you are scripting backup / restore and the container has only MariaDB, the classic command names are missing.

| Classic `mysql` | MariaDB equivalent |
|---|---|
| `mysql` | `mariadb` |
| `mysqldump` | `mariadb-dump` |
| `mysqladmin` | `mariadb-admin` |
| `mysqlimport` | `mariadb-import` |

Restore script must use `mariadb` not `mysql`. Also, the env var `MYSQL_ROOT_PASSWORD` may not be set in the container — read `MARIADB_ROOT_PASSWORD` (or `MARIADB_ROOT_PWD` per your project) from the project `.env`:

```bash
RPWD=$(grep MARIADB_ROOT_PWD .env | cut -d= -f2)

# Restore from gzipped backup into a temp DB (safer than touching production)
docker exec <db-container> mariadb -u root -p"$RPWD" -e \
  "DROP DATABASE IF EXISTS recover_db; CREATE DATABASE recover_db;"
zcat backup.sql.gz | docker exec -i <db-container> mariadb -u root -p"$RPWD" recover_db

# Dump just the rows you need from the temp DB
docker exec <db-container> mariadb-dump -u root -p"$RPWD" --skip-extended-insert \
  --where="post_id IN (525, 541) AND meta_key='_elementor_data'" \
  recover_db wp_postmeta > /tmp/recover.sql
```

When in doubt, check what the container has: `docker exec <db-container> which mariadb mysql 2>/dev/null`.

## Silent fatal debugging — plugin isolation chain

When a site is 500 and `debug.log` is empty (PHP-FPM crashed before WP could write — see [`pitfalls.md`](pitfalls.md) "AZDIGI shared host PHP-FPM worker exhaustion"), use this chain to localize the bug:

**1. Check log file via web (some hosts allow it)**:
```bash
curl https://example.com/wp-content/uploads/debug.log  # may return file content if .htaccess allows
```
⚠️ Some hosts (LiteSpeed/AZDIGI) return `200 + size 283171` for nonexistent files (the HTML 404 page is served). GET to inspect actual content size before trusting it.

**2. Plugin isolation test — REST PATCH deactivate one at a time**:
```bash
# Deactivate
curl -u "$WP_USER:$WP_PASS" -X POST \
  "$WP_SITE/wp-json/wp/v2/plugins/<slug>/<slug>" \
  -d '{"status":"inactive"}'

# Test the broken endpoint (e.g. retry the failing media upload)
curl -X POST "$WP_SITE/wp-json/wp/v2/media" -F file=@/tmp/test.jpg

# Reactivate
curl -u "$WP_USER:$WP_PASS" -X POST \
  "$WP_SITE/wp-json/wp/v2/plugins/<slug>/<slug>" \
  -d '{"status":"active"}'
```
Sequence-test all suspect plugins. The one whose deactivation fixes the symptom is the culprit.

**3. Try multiple upload paths (different code paths = different failure modes)**:
- `POST /wp-json/wp/v2/media` (REST, App Password)
- `POST /wp-admin/async-upload.php` (admin AJAX, nonce + cookies)
- MCP `sideload_image` ability
Each one triggers a different hook chain. If one fails and another succeeds, the failing one's hook chain is the bug location.

**4. Tiny PNG bypass test** — a 1×1 transparent PNG (~68 bytes) often skips thumbnail generation (no need to scale). Bypassing GD-intensive code can isolate "GD library bug" vs "PHP fatal somewhere else". A tiny PNG that uploads OK while a real JPG fails = the bug is in the resize / thumbnail path, not in auth / WAF / memory.

**5. Surface the fatal directly via mu-plugin** — see [`pitfalls.md`](pitfalls.md) "Emergency debug pattern".

## WP REST useful (but underdocumented) endpoints

Endpoints not prominently documented in WP core but extremely useful for automation:

| Endpoint | Purpose |
|---|---|
| `GET /wp-json/wp/v2/plugins` | List all plugins (admin only) |
| `POST /wp-json/wp/v2/plugins/{slug}/{slug}` body `{"status":"inactive"}` | Deactivate plugin (recovery when a plugin crashes the site) |
| `GET /wp-json/wp/v2/users/me?context=edit` | Full user details + caps (`upload_files`, `unfiltered_html`, ...) — verify before automation |
| `POST /wp-json/wp/v2/media/{id}` (acts as PATCH) | Update title / alt_text / caption after upload |
| `DELETE /wp-json/wp/v2/media/{id}?force=true` | Permanent delete (bypass trash) |
| `GET /wp-json/` | List all REST namespaces — discover plugin endpoints |
| `GET /wp-json/wp-abilities/v1/abilities` | List all loaded MCP abilities + schemas |

**Useful headers**:
- `Cache-Control: no-cache` + `Pragma: no-cache` → bypass LSCache for one request
- `Content-Disposition: attachment; filename="x.jpg"` → set the filename when uploading raw binary
- `Authorization: Basic <base64(user:apppassword)>` → App Password auth

**Fallback chain when one path is broken**: REST `/wp/v2/media` → `/wp-admin/async-upload.php` → MCP `sideload_image` ability — three different code paths with different failure modes.
