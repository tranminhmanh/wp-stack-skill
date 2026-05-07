# Workflow: SMTP Relay Setup (budget VPS)

End-to-end transactional email cho WordPress trên VPS rẻ (BNIX, OVH bare, Hetzner cloud, DigitalOcean) bị block port 25 outbound. Pattern proven: **15 phút Mạnh setup Brevo + DNS** + **20 phút install WP Mail SMTP + config + test** = end-to-end working, $0/tháng.

## Khi nào dùng

✅ VPS rẻ chạy WordPress → form submission cần gửi email reliable.
✅ Self-host SMTP server không hoạt động (port 25 outbound blocked 95% providers).
✅ Free tier transactional email đủ (≤300 emails/day).
✅ Budget B2B startup, không có sysadmin riêng.

❌ High-volume (>5000/day) → upgrade paid tier hoặc dedicated SMTP infrastructure.

## Lesson core: Don't run own mail server unless you really really really need to

3 lý do mạnh để KHÔNG host mail server riêng:
1. **Port 25 outbound bị block 95%** VPS providers. BNIX confirmed BLOCKED, OVH/Hetzner/DO mặc định cũng block (cần ticket support unlock).
2. **IP reputation building** mất 3–6 tháng warmup. Blacklist ban tự nhiên cho IP shared range của budget VPS.
3. **Maintenance** ~30–60 phút/tuần (Postfix + Dovecot + rspamd + cert + logs + DNSBL).

→ Free tier transactional service cover N tuần đầu đến 1 năm trước khi cần upgrade.

## Bước 1: Verify VPS outbound port availability

TRƯỚC khi commit provider, test ports:

```bash
docker exec wp-container bash -c '
for hp in "smtp-relay.brevo.com:587" "smtp.gmail.com:587" \
         "smtp-relay.brevo.com:465" "smtp-relay.brevo.com:25"; do
    H=$(echo $hp | cut -d: -f1); P=$(echo $hp | cut -d: -f2)
    timeout 5 bash -c "</dev/tcp/$H/$P" 2>/dev/null \
        && echo "OPEN  $hp" || echo "BLOCK $hp"
done
'
```

Expected pattern (ShipAsia trên BNIX):
```
OPEN  smtp-relay.brevo.com:587
OPEN  smtp.gmail.com:587
OPEN  smtp-relay.brevo.com:465
BLOCK smtp-relay.brevo.com:25
```

Port 25 blocked = không thể self-host outbound SMTP. Port 587/465 OK = relay được.

## Bước 2: Pick provider

| Provider | Free tier | Setup time | Auth method | Notes |
|---|---|---|---|---|
| **Brevo** (Sendinblue) | 300 emails/day | 15 phút | Domain DKIM CNAME + SMTP key | Recommended cho B2B 1-người. Free không hết hạn, không cần CC. |
| Gmail App Password | 500/day personal, 2000 Workspace | 5 phút | Gmail App Password | Đơn giản nhất nhưng có "via gmail.com" header trong client |
| SendGrid | 100/day | 10 phút | API key + SMTP relay | Stricter onboarding |
| Resend | 3000/month | 10 phút | API key (HTTPS preferred) | Modern API, dev-friendly |
| Mailgun | 5000 first 3 months | 15 phút | API key + SMTP relay | Strong deliverability nhưng free cap thấp dần |

ShipAsia chọn **Brevo** — tradeoff tốt nhất: free đủ (3–5 mail/ngày × 100 = vẫn xa 300 quota), không hết hạn, deliverability cao (whitelisted IPs), domain DKIM đã đủ Gmail trust ngay mail đầu.

## Bước 3: DNS records (Brevo example)

Brevo show ra 4 records bắt buộc + 1 manual:

| # | Type | Name | Value | Purpose |
|---|---|---|---|---|
| 1 | TXT | `@` | `brevo-code:<32 hex>` | Verify domain ownership |
| 2 | CNAME | `brevo1._domainkey` | `b1.<domain>.dkim.brevo.com` | DKIM key 1 |
| 3 | CNAME | `brevo2._domainkey` | `b2.<domain>.dkim.brevo.com` | DKIM key 2 |
| 4 | TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:rua@dmarc.brevo.com` | DMARC reporting |
| 5 | TXT (manual) | `@` | `v=spf1 include:spf.brevo.com mx ~all` | SPF (Brevo không show — manually add cho deliverability tối đa) |

⚠️ **DKIM trap**: Brevo dùng **CNAME** (không TXT). Brevo tự rotate DKIM key mà không yêu cầu update DNS. Đừng nhầm với DKIM Mailgun/SendGrid (TXT trực tiếp với public key inline).

DNS propagate ~5–15 phút sau khi save. Verify:
```bash
dig +short brevo1._domainkey.<domain>.com   # expect b1.<domain>.dkim.brevo.com
dig +short txt _dmarc.<domain>.com          # expect v=DMARC1...
```

## Bước 4: WP Mail SMTP plugin install via PHP (no WP-CLI)

Container Docker WP thường không có WP-CLI. Cài plugin bằng PHP:

```php
<?php
require_once '/var/www/html/wp-load.php';

// 1. Download zip
shell_exec('curl -sL https://downloads.wordpress.org/plugin/wp-mail-smtp.latest-stable.zip -o /tmp/p.zip');

// 2. Install unzip if missing (Debian/Ubuntu container)
shell_exec('apt-get update -qq && apt-get install -y unzip');

// 3. Extract + chown
shell_exec('cd /var/www/html/wp-content/plugins && unzip -q /tmp/p.zip && chown -R www-data:www-data wp-mail-smtp/');

// 4. Activate
require_once ABSPATH . 'wp-admin/includes/plugin.php';
activate_plugin('wp-mail-smtp/wp_mail_smtp.php');

// 5. Configure (Brevo example)
update_option('wp_mail_smtp', [
    'mail' => [
        'from_email'        => 'sales@example.com',
        'from_email_force'  => true,
        'from_name'         => 'Brand',
        'from_name_force'   => true,
        'mailer'            => 'smtp',
        'reply_to_email'    => 'support@example.com',
    ],
    'smtp' => [
        'host'              => 'smtp-relay.brevo.com',
        'port'              => 587,
        'encryption'        => 'tls',  // STARTTLS on port 587
        'autotls'           => true,
        'auth'              => true,
        'user'              => 'XXXX@smtp-brevo.com',
        'pass'              => 'xsmtpsib-...',
    ],
]);
```

5 phút end-to-end. Không cần admin GUI.

## Bước 5: Pro Form `submit_actions` order

```php
$el['settings']['submit_actions'] = ['save-to-database', 'email'];
//                                   ^^^^^^^^^^^^^^^^ FIRST = guaranteed lead capture
//                                                       'email' SECOND = if SMTP fails, lead still in DB
```

Pro Form action chain runs sequentially. Action 1 fail → action 2 vẫn chạy (không stop chain). NHƯNG response trả `success: true` chỉ khi ALL succeed.

**Rule**: đặt `save-to-database` FIRST. Nếu SMTP/Brevo down hoặc DNS DKIM hỏng → form vẫn xong (user không thấy lỗi UI) + lead vẫn vào admin Submissions.

```
✅ submit_actions: ["save-to-database", "email", "webhook"]
❌ submit_actions: ["email", "webhook", "save-to-database"]   ← email fail làm webhook + save không chạy
```

## Bước 6: `email_to` phasing (khi inbound mail server chưa ready)

Brevo gửi email đi tốt nhưng email phải tới được người nhận. Nếu `email_to: sales@<domain>` mà domain chưa có MX record (chưa setup Zoho/Workspace inbound) → mail bounce silently.

**Pattern phase tạm**: dùng Gmail cá nhân cho đến khi inbound mail server (Zoho free / Google Workspace) ready.

```php
// Phase 1 (Brevo only, no inbound MX):
'email_to' => 'manh-personal@gmail.com',
'reply_to_email' => 'manh-personal@gmail.com',

// Phase 2 (after Zoho/Workspace setup):
'email_to' => 'sales@example.com',
'reply_to_email' => 'sales@example.com',
```

## Bước 7: End-to-end smoke test

```bash
# Trigger form submission with curl (replace nonce + form_id)
NONCE=$(curl -s "https://example.com/?n=$(date +%s)" | grep -oE '"nonce":"[a-z0-9]+"' | head -1 | cut -d'"' -f4)
curl -s -X POST "https://example.com/wp-admin/admin-ajax.php" \
    -F "post_id=35" -F "form_id=bddad86" -F "_nonce=$NONCE" \
    -F "action=elementor_pro_forms_send_form" \
    -F "form_fields[name]=TEST_$(date +%s)" \
    -F "form_fields[phone]=0000000000"
```

Expected:
- Response: `{"success":true, "data":{"message":"...","data":{"submission_id":...}}}`
- DB row: `wp_e_submissions.actions_succeeded_count = 1/1`
- Email arrives in target inbox <1 phút (check INBOX, không Spam)
- From header: `Brand <sales@<domain>>` (đúng brand)

## Health check cron (recommended pre-launch)

Catch silent fails trong 24h thay vì N tuần:

```bash
#!/usr/bin/env bash
# /opt/<project>/scripts/form_health_check.sh — chạy daily

NONCE=$(curl -s "https://example.com/?n=$(date +%s)" \
    | grep -oE '"nonce":"[a-z0-9]+"' | head -1 | cut -d'"' -f4)

RESULT=$(curl -s -X POST "https://example.com/wp-admin/admin-ajax.php" \
    -F "post_id=35" -F "form_id=bddad86" -F "_nonce=$NONCE" \
    -F "action=elementor_pro_forms_send_form" \
    -F "form_fields[name]=HEALTH_CHECK_$(date +%s)" \
    -F "form_fields[phone]=0000000000")

echo "$RESULT" | grep -q '"success":true' \
    || telegram_alert "FORM BROKEN: $RESULT"
```

Cron: `0 9 * * * /opt/<project>/scripts/form_health_check.sh`

## Cost & resource savings

| Approach | Cost | Resource (RAM) | Setup time | Maintenance |
|---|---|---|---|---|
| Self-host Postfix + Dovecot + rspamd | $0 | +300–500MB | 4–8 giờ | 30–60 phút/tuần |
| **Brevo free relay + WP Mail SMTP** | **$0/tháng** | **+0MB** | **35 phút end-to-end** | **0 phút/tuần** |
| Brevo paid (5K/day) | $25/tháng | +0MB | Same | 0 phút/tuần |

VPS budget thường có 2–4GB RAM, 4 apps đã chạy → tiết kiệm 300–500MB là đáng kể.

## Liên quan

- [`references/pitfalls.md`](../references/pitfalls.md) — Pro Form silent fail (custom_id missing) — check ngay sau setup SMTP
- [`references/elementor-mcp.md`](../references/elementor-mcp.md) — `add-form` không set custom_id → manual patch
