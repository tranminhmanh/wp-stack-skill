<?php
/**
 * ============================================================================
 *  ⚠️  WARNING: SECURITY-SENSITIVE RECOVERY SCRIPT — READ BEFORE DEPLOYING  ⚠️
 * ============================================================================
 *
 * wp-fix.php — Token-guarded recovery script for a WordPress site that has
 * crashed (fatal PHP error, plugin conflict, theme break) and cannot be fixed
 * through the normal WP admin.
 *
 * It bypasses normal WordPress access control by parsing wp-config.php and
 * connecting to the database directly via mysqli. This is by design — but it
 * also means a misconfigured deployment is a backdoor.
 *
 * BEFORE YOU UPLOAD THIS FILE TO A LIVE SERVER:
 *
 *   1. CHANGE the WP_FIX_TOKEN constant below to a 32-char random string
 *      generated freshly per-deployment. Example:
 *          openssl rand -hex 32
 *      The script REFUSES to run with the placeholder value, but if you
 *      change it to something weak (e.g. "password"), it becomes a backdoor.
 *
 *   2. Use the script ONLY as long as needed for recovery. As soon as the
 *      fatal is fixed, hit ?op=stub to overwrite this file with a 404 stub.
 *
 *   3. After the recovery window, DELETE the file from the server entirely.
 *      Do not leave it sitting in the docroot.
 *
 *   4. Do NOT commit a copy of this file with a real token to any repo
 *      (public or private). Treat the token like a password.
 *
 *   5. If the site is on shared hosting and uses a Web Application Firewall
 *      (e.g. Imunify360), the upload itself may be blocked. See
 *      references/deployment.md "Shared host WAF blocks .php upload" for
 *      workarounds.
 *
 *   6. After running any destructive op (set_active, disable_all,
 *      switch_theme), VERIFY the site renders before stubbing the file.
 *
 * If you are not comfortable with the security implications, do not deploy
 * this script. Use a hosting-provider snapshot or backup restore instead.
 *
 * ============================================================================
 *
 * Deploy: upload to docroot via cPanel Fileman / SSH. Hit:
 *   https://example.com/wp-fix.php?token=YOURTOKEN&op=diag
 *
 * Operations:
 *   ?op=diag                                          → list active plugins, theme, db_version
 *   ?op=set_active&plugins=foo/foo.php,bar/bar.php    → replace active_plugins (backs up current)
 *   ?op=disable_all                                   → set active_plugins to [] (backs up current)
 *   ?op=enable_one&plugin=foo/foo.php                 → add plugin to active_plugins
 *   ?op=switch_theme&theme=astra                      → switch active theme
 *   ?op=restore                                       → restore from backup_active_plugins
 *   ?op=stub                                          → self-overwrite to a 404 stub
 */

const WP_FIX_TOKEN = 'CHANGE-ME-LONG-RANDOM-STRING-32-CHARS';

if (($_GET['token'] ?? '') !== WP_FIX_TOKEN || WP_FIX_TOKEN === 'CHANGE-ME-LONG-RANDOM-STRING-32-CHARS') {
    http_response_code(404);
    exit('Not found');
}

// Parse wp-config.php WITHOUT requiring it (avoids the fatal cascade)
$wp_config_path = __DIR__ . '/wp-config.php';
if (!is_readable($wp_config_path)) {
    exit('wp-config.php not readable');
}
$cfg = file_get_contents($wp_config_path);

function cfg_const(string $cfg, string $name): ?string {
    if (preg_match("/define\(\s*['\"]" . preg_quote($name, '/') . "['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $cfg, $m)) {
        return $m[1];
    }
    return null;
}
function cfg_var(string $cfg, string $name): ?string {
    if (preg_match("/\\\${$name}\s*=\s*['\"]([^'\"]*)['\"]/", $cfg, $m)) {
        return $m[1];
    }
    return null;
}

$db_name = cfg_const($cfg, 'DB_NAME');
$db_user = cfg_const($cfg, 'DB_USER');
$db_pass = cfg_const($cfg, 'DB_PASSWORD');
$db_host = cfg_const($cfg, 'DB_HOST') ?? 'localhost';
$prefix  = cfg_var($cfg, 'table_prefix') ?? 'wp_';

if (!$db_name || !$db_user) {
    exit('Failed to parse DB credentials from wp-config.php');
}

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    exit("DB connect failed: " . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

function get_option_raw(mysqli $db, string $prefix, string $name): ?string {
    $stmt = $db->prepare("SELECT option_value FROM {$prefix}options WHERE option_name = ?");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $stmt->bind_result($val);
    $stmt->fetch();
    $stmt->close();
    return $val;
}

function set_option_raw(mysqli $db, string $prefix, string $name, string $val): void {
    $exists = get_option_raw($db, $prefix, $name);
    if ($exists === null) {
        $stmt = $db->prepare("INSERT INTO {$prefix}options (option_name, option_value, autoload) VALUES (?, ?, 'yes')");
        $stmt->bind_param('ss', $name, $val);
    } else {
        $stmt = $db->prepare("UPDATE {$prefix}options SET option_value = ? WHERE option_name = ?");
        $stmt->bind_param('ss', $val, $name);
    }
    $stmt->execute();
    $stmt->close();
}

$op = $_GET['op'] ?? 'diag';
header('Content-Type: text/plain; charset=utf-8');

switch ($op) {
    case 'diag':
        $active = get_option_raw($mysqli, $prefix, 'active_plugins');
        $theme  = get_option_raw($mysqli, $prefix, 'stylesheet');
        echo "Active plugins (raw): $active\n\n";
        echo "Active theme: $theme\n";
        echo "DB version: " . (get_option_raw($mysqli, $prefix, 'db_version') ?? '?') . "\n";
        echo "Site URL: " . (get_option_raw($mysqli, $prefix, 'siteurl') ?? '?') . "\n";
        break;

    case 'set_active':
        $current = get_option_raw($mysqli, $prefix, 'active_plugins');
        set_option_raw($mysqli, $prefix, 'backup_active_plugins', $current ?? '');
        $list = explode(',', $_GET['plugins'] ?? '');
        $list = array_values(array_filter(array_map('trim', $list)));
        set_option_raw($mysqli, $prefix, 'active_plugins', serialize($list));
        echo "Set active_plugins to: " . implode(', ', $list) . "\nBackup saved to backup_active_plugins.\n";
        break;

    case 'disable_all':
        $current = get_option_raw($mysqli, $prefix, 'active_plugins');
        set_option_raw($mysqli, $prefix, 'backup_active_plugins', $current ?? '');
        set_option_raw($mysqli, $prefix, 'active_plugins', serialize([]));
        echo "Disabled all plugins. Backup at backup_active_plugins.\n";
        break;

    case 'enable_one':
        $plugin  = $_GET['plugin'] ?? '';
        $current = unserialize(get_option_raw($mysqli, $prefix, 'active_plugins') ?: 'a:0:{}');
        if (!is_array($current)) $current = [];
        if ($plugin && !in_array($plugin, $current, true)) {
            $current[] = $plugin;
        }
        set_option_raw($mysqli, $prefix, 'active_plugins', serialize($current));
        echo "Enabled $plugin. Active: " . implode(', ', $current) . "\n";
        break;

    case 'switch_theme':
        $theme = $_GET['theme'] ?? 'astra';
        set_option_raw($mysqli, $prefix, 'stylesheet', $theme);
        set_option_raw($mysqli, $prefix, 'template', $theme);
        echo "Switched theme to $theme\n";
        break;

    case 'restore':
        $backup = get_option_raw($mysqli, $prefix, 'backup_active_plugins');
        if ($backup) {
            set_option_raw($mysqli, $prefix, 'active_plugins', $backup);
            echo "Restored active_plugins from backup.\n";
        } else {
            echo "No backup found.\n";
        }
        break;

    case 'stub':
        $stub = "<?php\nhttp_response_code(404);\nexit('Not found');\n";
        file_put_contents(__FILE__, $stub);
        echo "wp-fix.php stubbed to 404. Recommended next step: delete the file entirely.\n";
        break;

    default:
        echo "Unknown op: $op\nUse: diag, set_active, disable_all, enable_one, switch_theme, restore, stub\n";
}

$mysqli->close();
