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

## Cross-references

- [`references/pitfalls.md`](pitfalls.md) "Fluent Forms shortcode renders empty if the form has 0 fields"
- [`references/stack.md`](stack.md) "Form & Lead" — Fluent Forms vs Elementor Pro Form decision
- [`workflows/smtp-relay-setup.md`](../workflows/smtp-relay-setup.md) — Brevo SMTP for email delivery
- [Fluent Forms docs](https://fluentforms.com/docs/) — official reference
