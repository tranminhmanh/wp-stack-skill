# Workflow: Migrate Staging → Production

⚠️ Read the project `CLAUDE.md` for hosting info first. Do NOT guess paths or SSH.

## Pre-migrate checklist

- [ ] Production has been backed up (DB + files)
- [ ] Staging has passed full QA
- [ ] Lighthouse on staging ≥85 mobile
- [ ] Forms tested successfully on staging
- [ ] No PHP errors on staging
- [ ] Schedule a downtime window (if the site is live)

## Option 1: WP Migrate Pro (recommended, $49+)

1. Install WP Migrate Pro on both staging + production
2. On staging: Tools → WP Migrate → Push
3. Target: production URL + secret key
4. Find/replace URL: automatic
5. Push DB + files
6. Verify production after push

Pros: easy, safe, supports rollback.
Cons: paid.

## Option 2: Duplicator Free

1. Install Duplicator on staging
2. Create Package → Build → Download (.zip + installer.php)
3. Upload both files to the production root
4. Visit `<prod-url>/installer.php`
5. Configure the production DB
6. Run installer
7. Login to admin, finalize, delete installer files

Pros: free.
Cons: production must be clean (delete the existing WP files first).

## Option 3: Manual SSH (read CLAUDE.md for paths)

```bash
# 1. Backup production
ssh <alias from CLAUDE.md>
cd <prod-path>
tar czf /tmp/prod-backup-$(date +%Y%m%d).tar.gz .
mysqldump -u <db-user> -p <prod-db> > /tmp/prod-db-$(date +%Y%m%d).sql

# 2. Stop accepting traffic (optional, maintenance mode)
# .htaccess redirect, or WP Maintenance Mode plugin

# 3. Pull staging files (rsync from staging server or local pull)
rsync -avz --exclude='wp-config.php' \
  <staging>:<staging-path>/ \
  <prod-path>/

# 4. Pull staging DB
ssh <staging> mysqldump <staging-db> > /tmp/staging-db.sql
mysql -u <db-user> -p <prod-db> < /tmp/staging-db.sql

# 5. Update site URL
cd <prod-path>
wp search-replace '<staging-url>' '<prod-url>' --skip-columns=guid

# 6. Update specific configs if needed (uploads URL, etc.)
wp option update siteurl '<prod-url>'
wp option update home '<prod-url>'

# 7. Flush
wp cache flush
wp rewrite flush

# 8. Re-set permissions
chown -R <php-user>:<php-user> <prod-path>
find <prod-path> -type d -exec chmod 755 {} \;
find <prod-path> -type f -exec chmod 644 {} \;
chmod 600 <prod-path>/wp-config.php
```

## Option 4: Provider-specific

### SiteGround
Site Tools → Dev → WordPress Migrator → Source URL + Token.

### Cloudways
Application Management → Clone App → adjust URL.

### Hostinger
hPanel → File Manager + phpMyAdmin (more manual).

### CloudPanel
SSH manual (option 3) or snapshot restore via the provider VPS dashboard.

## Post-migrate verification

- [ ] Homepage loads OK
- [ ] 5 main pages load OK
- [ ] Admin login works
- [ ] Form submit + email received
- [ ] Search Console reports no 404 spike
- [ ] SSL valid (no mixed content)
- [ ] Cloudflare Purge Everything (if used)
- [ ] WP Rocket Clear Cache
- [ ] LiteSpeed Cache Purge All (if used)
- [ ] Google Analytics receiving events
- [ ] Sitemap regenerated + submitted to GSC

## Common bugs after migration

### 1. Mixed content (http:// in DB)
```bash
wp search-replace 'http://<site>' 'https://<site>' --skip-columns=guid
```

### 2. Image 404 after migration
- Check uploads path is correct
- Permission on `wp-content/uploads`
- WordPress.com style: if staging used S3, update the path

### 3. White screen of death
- Enable `WP_DEBUG_LOG` temporarily
- Check the error log
- Plugin compatibility (deactivate all → reactivate one by one)

### 4. Admin login redirect loop
- Clear browser cookies for the domain
- wp-config: `define('COOKIE_DOMAIN', '.<site>');`

### 5. Permalinks 404
- `wp rewrite flush`
- Settings → Permalinks → Save (no changes)

## Rollback

```bash
ssh <alias>
cd <prod-path>
rm -rf ./*
tar xzf /tmp/prod-backup-YYYYMMDD.tar.gz
mysql -u <db-user> -p <prod-db> < /tmp/prod-db-YYYYMMDD.sql
wp cache flush
```
