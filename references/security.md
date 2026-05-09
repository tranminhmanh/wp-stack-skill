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
6. Audit user list, delete unknown users
7. Audit installed plugins, delete unknown ones
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
