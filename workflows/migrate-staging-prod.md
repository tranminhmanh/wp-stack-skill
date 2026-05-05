# Workflow: Migrate Staging → Production

⚠️ Đọc CLAUDE.md project lấy hosting info trước. KHÔNG đoán path/SSH.

## Pre-migrate checklist

- [ ] Production đã backup (DB + files)
- [ ] Staging đã pass full QA
- [ ] Lighthouse staging ≥85 mobile
- [ ] Forms test thành công trên staging
- [ ] No PHP errors trên staging
- [ ] Schedule downtime window (nếu site live)

## Cách 1: WP Migrate Pro (recommended, $49+)

1. Cài WP Migrate Pro trên cả staging + production
2. Trên staging: Tools → WP Migrate → Push
3. Target: production URL + secret key
4. Find/replace URL: tự động
5. Push DB + files
6. Verify production sau push

Pros: dễ, an toàn, có rollback.
Cons: $$.

## Cách 2: Duplicator Free

1. Cài Duplicator trên staging
2. Create Package → Build → Download (.zip + installer.php)
3. Upload 2 files lên production root
4. Truy cập `<prod-url>/installer.php`
5. Configure DB production
6. Run installer
7. Login admin, finalize, delete installer files

Pros: free.
Cons: prod phải clean (xóa hết WP files cũ).

## Cách 3: Manual SSH (đọc CLAUDE.md cho path)

```bash
# 1. Backup production
ssh <alias từ CLAUDE.md>
cd <prod-path>
tar czf /tmp/prod-backup-$(date +%Y%m%d).tar.gz .
mysqldump -u <db-user> -p <prod-db> > /tmp/prod-db-$(date +%Y%m%d).sql

# 2. Stop accept traffic (optional, maintenance mode)
# Có thể dùng .htaccess redirect, hoặc plugin WP Maintenance Mode

# 3. Pull staging files (rsync from staging server hoặc local pull)
rsync -avz --exclude='wp-config.php' \
  <staging>:<staging-path>/ \
  <prod-path>/

# 4. Pull staging DB
ssh <staging> mysqldump <staging-db> > /tmp/staging-db.sql
mysql -u <db-user> -p <prod-db> < /tmp/staging-db.sql

# 5. Update site URL
cd <prod-path>
wp search-replace '<staging-url>' '<prod-url>' --skip-columns=guid

# 6. Update specific configs nếu cần (uploads URL, etc.)
wp option update siteurl '<prod-url>'
wp option update home '<prod-url>'

# 7. Flush
wp cache flush
wp rewrite flush

# 8. Permission re-set
chown -R <php-user>:<php-user> <prod-path>
find <prod-path> -type d -exec chmod 755 {} \;
find <prod-path> -type f -exec chmod 644 {} \;
chmod 600 <prod-path>/wp-config.php
```

## Cách 4: Provider-specific

### SiteGround
Site Tools → Dev → WordPress Migrator → Source URL + Token.

### Cloudways
Application Management → Clone App → adjust URL.

### Hostinger
hPanel → File Manager + phpMyAdmin (manual hơn).

### CloudPanel
SSH manual (cách 3) hoặc snapshot restore qua provider VPS dashboard.

## Post-migrate verify

- [ ] Homepage load OK
- [ ] 5 page chính load OK
- [ ] Login admin được
- [ ] Form submit + email nhận
- [ ] Search Console không report 404 spike
- [ ] SSL valid (no mixed content)
- [ ] Cloudflare Purge Everything (nếu dùng)
- [ ] WP Rocket Clear Cache
- [ ] LiteSpeed Cache Purge All (nếu dùng)
- [ ] Google Analytics receive event
- [ ] Sitemap regenerate + submit GSC

## Common bugs sau migrate

### 1. Mixed content (http:// trong DB)
```bash
wp search-replace 'http://<site>' 'https://<site>' --skip-columns=guid
```

### 2. Image 404 sau migrate
- Check uploads path đúng
- Permission wp-content/uploads
- WordPress.com style: nếu staging dùng s3 thì update đường dẫn

### 3. White screen of death
- WP_DEBUG_LOG ON tạm thời
- Check error_log
- Plugin compatibility (deactive all → reactive từng cái)

### 4. Login admin loop redirect
- Clear browser cookies cho domain
- wp-config: define('COOKIE_DOMAIN', '.<site>');

### 5. Permalinks 404
- wp rewrite flush
- Settings → Permalinks → Save (không đổi gì)

## Rollback

```bash
ssh <alias>
cd <prod-path>
rm -rf ./*
tar xzf /tmp/prod-backup-YYYYMMDD.tar.gz
mysql -u <db-user> -p <prod-db> < /tmp/prod-db-YYYYMMDD.sql
wp cache flush
```
