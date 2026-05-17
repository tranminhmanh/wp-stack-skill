# Fluent Forms — styling + integration cheatsheet

Fluent Forms (free + Pro) is the recommended form plugin in this stack when Elementor Pro Form is too limited (multi-step, conditional logic, file upload, integration hub). This file covers the gotchas specific to styling and integration that aren't obvious from the plugin docs.

## Install + activate

```bash
# Plugin slug: fluentform (free) — installable via WP REST or WP Admin
curl -u "$WP_USER:$WP_PASS" -X POST \
  "$WP_SITE/wp-json/wp/v2/plugins" \
  -d '{"slug":"fluentform","status":"active"}'
```

Or WP Admin → Plugins → Add New → search "Fluent Forms" → Install → Activate.

## Submit-button styling — high-specificity selector + `!important`

**Symptom**: brand button color set in `.ff-btn-submit { background: #0A1F44 }` does NOT apply. Submit button keeps the plugin's default blue (`#1976d2`).

**Root cause**: Fluent Forms ships a CSS variable `--fluentform-primary` set as **inline style on the `<form>` element**. Specificity is high enough that a plain class selector (`.ff-btn-submit`) loses the cascade battle.

**Fix — high-specificity chain + `!important`** to beat the inline variable:
```css
body .fluentform .ff-btn-submit,
body .ff-btn.ff-btn-submit,
body button.ff-btn.ff_btn_style {
  background: #0A1F44 !important;
  border-color: #0A1F44 !important;
  color: #FFFFFF !important;
}

body .fluentform .ff-btn-submit:hover {
  background: #0d2858 !important;
  border-color: #0d2858 !important;
}
```

3 selector chains for coverage:
1. `body .fluentform .ff-btn-submit` — wrapper class chain
2. `body .ff-btn.ff-btn-submit` — class composition
3. `body button.ff-btn.ff_btn_style` — element type + brand class

`body` prefix raises specificity to (0, 2, 1) — enough to beat the inline `--fluentform-primary` variable cascade.

## Input field styling

```css
body .fluentform input[type="text"],
body .fluentform input[type="email"],
body .fluentform input[type="tel"],
body .fluentform input[type="number"],
body .fluentform select,
body .fluentform textarea {
  font-family: 'Inter', sans-serif !important;
  font-size: 16px !important;       /* iOS Safari: <16px = unwanted zoom on focus */
  border: 1px solid #D1D5DB !important;
  border-radius: 6px !important;
  padding: 10px 14px !important;
  transition: border-color 0.2s, box-shadow 0.2s !important;
}

body .fluentform input:focus,
body .fluentform select:focus,
body .fluentform textarea:focus {
  border-color: #0A1F44 !important;
  box-shadow: 0 0 0 3px rgba(10, 31, 68, 0.1) !important;
  outline: none !important;
}
```

⚠️ Mobile-specific: input `font-size: 16px` is the magic threshold to prevent iOS Safari from zooming on focus. Anything <16px triggers the auto-zoom UX bug.

## Checkbox + radio button alignment

Fluent Forms checkboxes / radios sometimes render with awkward vertical alignment because the input + label use different line-heights:
```css
body .fluentform .ff-el-form-check {
  display: flex !important;
  align-items: flex-start !important;
  gap: 10px !important;
}
body .fluentform .ff-el-form-check input[type="checkbox"],
body .fluentform .ff-el-form-check input[type="radio"] {
  margin-top: 4px !important;       /* visually centers with first line of label */
  flex-shrink: 0 !important;
}
```

## Where to inject the CSS

Two options, in priority order:

1. **mu-plugin master CSS** (`wp-content/mu-plugins/master-css.php`) — preferred. Outlives plugin updates, doesn't depend on Fluent Forms internals.
   ```php
   add_action('wp_head', function () { ?>
   <style id="brand-fluentform">/* CSS above */</style>
   <?php }, 100);
   ```

2. **Fluent Forms Settings → Custom CSS field** — also works, but coupled to the plugin's lifecycle. If you uninstall + reinstall, the rule is lost.

DO NOT use Code Snippets for this — see [`references/stack.md`](stack.md) "CSS architecture" for why mu-plugin is preferred.

## Form rendering pitfalls

See [`references/pitfalls.md`](pitfalls.md) "Fluent Forms shortcode renders empty if the form has 0 fields" — common debug spiral when the shortcode shows submit-button-only.

## Email notification setup

After creating a form, **submissions are saved to DB but no email is sent by default**. To enable:

1. WP Admin → Fluent Forms → All Forms → click form ID
2. **Settings & Integrations** tab (top right)
3. **Email Notifications** → Add Notification:
   - Send to: `sales@<domain>` (or `{inputs.email}` for reply-to-sender pattern)
   - Reply-to: `{inputs.email}` to make replies hit the sender
   - Subject: `New lead: {inputs.first_name}` — interpolate field IDs in `{}` braces
   - Body: HTML-formatted, can use any `{inputs.<field>}` token
4. **Test**: submit the form once → check the inbox

⚠️ Without `WP Mail SMTP` configured, `wp_mail()` falls back to PHP `mail()` which on most VPS hosts hits port 25 outbound block → silent fail. See [`workflows/smtp-relay-setup.md`](../workflows/smtp-relay-setup.md) for Brevo SMTP relay.

## Free vs Pro — what each tier ships

| Feature | Free | Pro |
|---|---|---|
| Form builder | ✅ | ✅ |
| Multi-step | ❌ | ✅ |
| Conditional logic | Limited | ✅ |
| File upload | ❌ | ✅ |
| Phone field (intl) | ❌ Use Text + regex | ✅ |
| Payment integration | ❌ | ✅ Stripe / PayPal / Razorpay |
| CRM integrations | ❌ | ✅ HubSpot / Salesforce / etc. |
| Submission DB | ✅ | ✅ |
| Email notifications | ✅ | ✅ |
| Webhooks | ❌ | ✅ |

For B2B lead capture: Free is enough for 95% of cases (Name + Email + Phone via Text + Notes). Pro is worth it when you need conditional logic that hides fields based on prior input, or webhook → CRM.

## Workaround — Free version Phone field via Text + regex

```javascript
// Add a regex pattern to a Text field via the visual builder OR via post-save JS injection
document.querySelectorAll('input[data-name="phone"]').forEach(input => {
  input.setAttribute('pattern', '[0-9+\\-\\s\\(\\)]{8,15}');
  input.setAttribute('inputmode', 'tel');
  input.setAttribute('autocomplete', 'tel');
});
```

`pattern` triggers HTML5 native validation. `inputmode="tel"` brings up the phone keyboard on mobile. `autocomplete="tel"` lets browsers autofill from saved profiles.

## Submission test via `admin-ajax.php` — anonymous frontend simulation

When you need to verify a Fluent Form actually submits (vs just renders correctly) — without opening a browser — POST to `admin-ajax.php` directly. Useful for CI smoke tests, post-deploy verification, regression suites.

### Endpoint

```
POST https://<site>/wp-admin/admin-ajax.php
Content-Type: application/x-www-form-urlencoded
Referer: https://<site>/<page-with-form>/

action=fluentform_submit
&data=<urlencoded inner form data>
&form_id=<N>
```

### Inner `data=` is DOUBLE-ENCODED

The `data` parameter is itself URL-encoded form-data. Easiest from Python:

```python
import urllib.parse

inner = urllib.parse.urlencode({
    "names[first_name]": "Test",
    "names[last_name]":  "Lead",
    "email":             "test@example.com",
    "input_text":        "+84 762 279 292",       # phone
    "dropdown":          "Concert / Liveshow",     # MUST be exact-match against form schema
    "datetime":          "2026-05-20",
})

# Outer wrapper
post_body = urllib.parse.urlencode({
    "action":  "fluentform_submit",
    "data":    inner,                              # inner already URL-encoded
    "form_id": "3",
})

# Send
import urllib.request
req = urllib.request.Request(
    f"{SITE}/wp-admin/admin-ajax.php",
    data=post_body.encode("utf-8"),
    headers={
        "Content-Type": "application/x-www-form-urlencoded",
        "Referer": f"{SITE}/<page-with-form>/",
    },
)
resp = urllib.request.urlopen(req)
print(resp.read().decode())
```

### Success response

```json
{
  "success": true,
  "data": {
    "insert_id": 1,
    "result": {"message": "Cảm ơn bạn đã nhắn tin...", "action": "hide_form"},
    "error": ""
  }
}
```

Submission saved to DB, visible at `GET /wp-json/fluentform/v1/submissions?form_id=3` (App Password auth required).

### Validation error response

```json
HTTP 423 {
  "errors": {
    "input_text": {"required": "Trường này là bắt buộc"},
    "dropdown":   ["Dữ liệu được cung cấp không hợp lệ"]
  }
}
```

HTTP 423 means schema validation failed — the request reached Fluent Forms but didn't pass field rules.

### Dropdown / select fields are EXACT-MATCH

For `dropdown`, `radio`, `checkbox` fields, the submitted value must exactly match one of the configured options — including case, spaces, and Vietnamese diacritics:

```
✅ "Concert / Liveshow"
❌ "concert/liveshow"       (case + space differ)
❌ "Concert/Liveshow"       (missing space around `/`)
❌ "Concert/Liveshow "       (trailing space)
```

**Pre-fetch valid options** from the form schema before testing:

```bash
curl -u "$U:$APP_PW" \
  "$SITE/wp-json/fluentform/v1/forms/3" \
  | jq -r '.form_fields[] | select(.attributes.name=="dropdown") | .settings.advanced_options[].value'
```

### When to use this vs browser testing

| Use case | Tool |
|---|---|
| Post-deploy smoke test | `admin-ajax.php` POST (fast, scriptable) |
| Regression in CI | `admin-ajax.php` POST |
| Cross-browser layout / JS issue | Real browser |
| Final user-acceptance test before launch | Real browser |
| Anti-bot / honeypot debug | Real browser (POSTs don't trigger client-side captcha) |

### Anti-patterns

❌ **Skip the `Referer` header** — Fluent Forms may reject submissions without it (treats as direct API hit)

❌ **Use `data` parameter as a JSON object** — Fluent Forms expects URL-encoded form data inside, NOT JSON

❌ **Send the request to `/wp-json/fluentform/v1/submit`** — that endpoint exists but is admin-only (requires App Password) and has different validation. `admin-ajax.php` is the anonymous-user endpoint Fluent Forms actually uses for frontend.

❌ **Forget to clean up test submissions** — your test data sits in the production DB. Delete via:
```bash
curl -u "$U:$APP_PW" -X DELETE \
  "$SITE/wp-json/fluentform/v1/submissions/<insert_id>"
```

## Cross-references

- [`references/pitfalls.md`](pitfalls.md) "Fluent Forms shortcode renders empty if the form has 0 fields"
- [`references/stack.md`](stack.md) "Form & Lead" — Fluent Forms vs Elementor Pro Form decision
- [`workflows/smtp-relay-setup.md`](../workflows/smtp-relay-setup.md) — Brevo SMTP for email delivery
- [Fluent Forms docs](https://fluentforms.com/docs/) — official reference
