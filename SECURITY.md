# Security Policy

## Reporting a vulnerability

**Do not open a public GitHub issue for security-sensitive findings.**

Instead, open a [GitHub Security Advisory draft](https://github.com/tranminhmanh/wp-stack-skill/security/advisories/new) — this lets us discuss privately and coordinate disclosure.

If you cannot use Security Advisories, send a brief description to the repository owner via GitHub's contact form.

You will receive an acknowledgment within 72 hours (best effort).

## Scope

This skill is **documentation and PHP recipes**. It does not run on a live system except when:

1. A user copies a snippet from `templates/snippets/*.php` to their own WordPress install, or
2. A maintainer's CI executes `php -l` on the snippets

Security findings we want to hear about:

- A snippet in `templates/snippets/` has a vulnerability that could compromise a site if deployed as documented
- A pattern in `references/` recommends an insecure default that is not flagged
- A recipe leaks credentials, PII, or other sensitive data
- The `wp-fix.php` recovery script's token check can be bypassed
- Any prompt-injection vector in `SKILL.md` that could cause Claude Code to take harmful actions

Out of scope:

- Vulnerabilities in upstream WordPress, Elementor, Astra, or any third-party plugin (report to the upstream project)
- General "WordPress is insecure" findings without a specific link to this skill's content

## `wp-fix.php` warning

The `templates/snippets/wp-fix.php` template is a **token-guarded recovery script** for sites that have crashed (fatal PHP error, plugin conflict, theme break). It bypasses normal WordPress access control by reading `wp-config.php` and connecting to the database directly via `mysqli`.

If deployed:

1. **Change the default `WP_FIX_TOKEN`** to a 32-character random string. The script refuses to run with the placeholder value, but if you change it to something weak (e.g. `password`), it becomes a backdoor.
2. **Self-stub immediately after use** via `?op=stub` — this overwrites the file with a 404 stub.
3. **Better: delete the file from the server** after the recovery is complete.

Treat this script the same way you would treat a `.env` file: never commit secrets to the public repo, never leave it on a live host longer than the recovery window.

## Disclosure timeline

- Day 0: report received
- Day 0–3: acknowledgment + initial triage
- Day 3–14: investigation, fix drafted, coordinated disclosure plan
- Day 14–30: public advisory + patched release

We follow [coordinated disclosure](https://en.wikipedia.org/wiki/Coordinated_vulnerability_disclosure) practices and credit reporters in the advisory unless they request anonymity.
