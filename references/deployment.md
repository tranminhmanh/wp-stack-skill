# Deployment Workflow

⚠️ **KHÔNG hardcode VPS IP/SSH alias/path trong file này.** Mọi thông tin specific đọc từ CLAUDE.md project.

## Pattern an toàn

Trước khi deploy, **đọc CLAUDE.md project** lấy:
- Provider (CloudPanel VPS / SiteGround / Cloudways / Hostinger / khác)
- SSH access method (key/password/panel-only)
- Site path
- Database type/host/port/name
- Staging URL

Nếu CLAUDE.md thiếu thông tin → HỎI user, KHÔNG đoán.

## Workflow staging → production

### Cách 1: WP Migrate Pro (recommended)

1. Trên staging: Tools → WP Migrate → Export
2. Chọn Push to remote site
3. Target: production URL + secret key
4. Find/replace URL: `<staging-url>` → `<prod-url>`
5. Push DB + files

### Cách 2: Manual qua SSH

```bash
# 1. Backup production trước (đọc path từ CLAUDE.md)
ssh <alias từ CLAUDE.md>
cd <site-path từ CLAUDE.md>
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

### Cách 3: Provider-specific

- **SiteGround**: Site Tools → Dev → WordPress Migrator
- **Cloudways**: Application Management → Clone App
- **Hostinger**: hPanel → File Manager + DB tools
- **CloudPanel**: SSH manual (cách 2) hoặc snapshot

## Pre-deploy checklist

- [ ] Backup production DB + files
- [ ] Test trên staging full user flow
- [ ] Lighthouse score staging ≥85 mobile
- [ ] No PHP errors trong wp-content/debug.log
- [ ] Form submission test thành công
- [ ] All images load, no 404
- [ ] SSL valid, mixed content cleared
- [ ] Sitemap regenerate
- [ ] Search Console submit sitemap mới
- [ ] Google Tag Manager track event đúng

## Post-deploy

- Clear cache: WP Rocket + Cloudflare + LiteSpeed
- Test 5 page chính trên 3 device (375/768/1280)
- Submit Search Console "Request indexing"
- Monitor uptime (UptimeRobot) 24h sau deploy
- Check Google Analytics có receive event không

## Rollback nếu hỏng

```bash
ssh <alias>
cd <site-path>
rm -rf ./*
tar xzf /tmp/backup-YYYYMMDD.tar.gz
mysql -u <user> -p <db> < /tmp/db-YYYYMMDD.sql
wp cache flush
```

## Bẫy hosting thường gặp

### CloudPanel
- PHP-FPM crash khi memory limit thấp → wp-config tăng 512M + CloudPanel PHP Settings
- SSL Let's Encrypt renew fail → check DNS A record + port 80 mở
- Disk full do log → truncate debug.log hoặc disable WP_DEBUG_LOG

### SiteGround
- Cache aggressive → vào Site Tools tắt Dynamic Cache khi develop
- SSH access cần generate key qua Site Tools → Dev → SSH Keys
- Migration plugin có SiteGround Migrator riêng (recommended)

### Cloudways
- Clone App nhanh nhưng tạo URL random → cần map domain sau
- Varnish cache mạnh → Application → Manage Services → Purge Varnish

### Hostinger
- LiteSpeed cache built-in → dùng LSCache plugin thay WP Rocket
- File Manager Hostinger có browser-based SSH (Web Terminal)

## Khi user dùng host KHÔNG có SSH

(Vd: shared hosting cũ, client không cấp SSH access)

→ KHÔNG đề xuất MCP qua SSH workflow.
→ Workflow thay thế: WP Migrate Pro UI, UpdraftPlus restore, Duplicator.
→ MCP qua HTTP vẫn chạy được (chỉ cần REST API endpoint), nhưng deploy migrate phải qua plugin UI.
