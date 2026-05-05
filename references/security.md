# Security Hardening Checklist

## wp-config.php

```php
// Disable file editor trong admin
define('DISALLOW_FILE_EDIT', true);

// Disable plugin/theme update qua admin
define('DISALLOW_FILE_MODS', false); // false để update được

// Force SSL admin
define('FORCE_SSL_ADMIN', true);

// Limit post revisions
define('WP_POST_REVISIONS', 5);

// Auto-save interval
define('AUTOSAVE_INTERVAL', 300);

// Disable debug trên production
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

// Memory limit
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Salt keys: regenerate qua https://api.wordpress.org/secret-key/1.1/salt/
```

## File permissions

```bash
# Folders 755, files 644
find /<site-path>/ -type d -exec chmod 755 {} \;
find /<site-path>/ -type f -exec chmod 644 {} \;

# wp-config.php restrictive
chmod 600 wp-config.php

# uploads writable bởi PHP user
chown -R <php-user>:<php-user> wp-content/uploads
```

(Thay `<site-path>` và `<php-user>` từ CLAUDE.md project)

## Plugin security

- Wordfence Free: Firewall ON, scan weekly
- WPS Hide Login: đổi /wp-admin → /<random-slug>
- Limit Login Attempts: max 5 attempts, 60 min lockout
- Two Factor: bật cho mọi admin user

## User management

- Admin user: KHÔNG dùng "admin" làm username
- Password: 16+ ký tự, manager (1Password/Bitwarden)
- Application Password: dùng cho MCP, KHÔNG cho hàng ngày
- Disable XML-RPC: nếu không dùng Jetpack/IFTTT
- Disable REST API public: nếu không cần (cẩn thận: MCP dùng REST)

## Backup strategy

- UpdraftPlus: Daily DB + Weekly files → Google Drive remote
- Provider snapshot: Trước mỗi MCP session lớn
- Off-site: WP Migrate Pro export → S3 bucket riêng

## Recovery plan nếu bị hack

1. Snapshot ngay (preserve evidence)
2. Restore backup gần nhất sạch
3. Wordfence full scan
4. Đổi MỌI password (DB, admin, app password, FTP)
5. Update WP core + theme + plugin lên latest
6. Audit user list, xóa user lạ
7. Audit installed plugin, xóa plugin lạ
8. Check .htaccess, wp-config có code lạ không
