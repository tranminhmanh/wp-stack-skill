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

## VPS-level: fail2ban whitelist override

Whitelist IP qua `jail.local` (KHÔNG `jail.d/whitelist.conf`) — jail-level overrides DEFAULT, ngược lại không.

```ini
# /etc/fail2ban/jail.local
[DEFAULT]
ignoreip = 127.0.0.1/8 ::1 <YOUR-OFFICE-IP>

[sshd]
enabled = true
ignoreip = 127.0.0.1/8 ::1 <YOUR-OFFICE-IP>
```

`systemctl restart fail2ban`. Verify: `fail2ban-client status sshd` → `Currently banned` không có IP whitelisted.

## VPS-level: iptables `-F` flush risk khi default DROP

`iptables -F INPUT` KHÔNG actually flush nếu default policy là DROP — server bị lockout vĩnh viễn (không có console access = mất server).

**Safe order**:
```bash
iptables -P INPUT ACCEPT     # 1. switch policy ACCEPT trước
iptables -F INPUT             # 2. flush rules
# 3. add rules mới
iptables -P INPUT DROP        # 4. switch policy DROP cuối
```

Tự kiểm: `iptables -L INPUT -v` xem default policy trước khi flush.

## Mu-plugin API check trước khi gọi method

Elementor Pro API thay đổi giữa versions. Vd `\ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager::is_location_filled()` không tồn tại trong v4.0.1 → fatal error 500 site-wide khi mu-plugin load.

**Pattern an toàn**:
```bash
# 1. Grep source code trước khi gọi
grep -n "function is_location_filled" /path/to/elementor-pro/modules/.../classes/locations-manager.php
# Empty result = method KHÔNG tồn tại trong version này

# 2. Hoặc reflection check trong PHP
if (method_exists('\\ElementorPro\\Modules\\ThemeBuilder\\Classes\\Locations_Manager', 'is_location_filled')) {
    // safe to call
}
```

Mu-plugin load tự động → deploy với API call sai = site die ngay. Phải có local PHP unit test hoặc grep source trước khi push.
