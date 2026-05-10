# content_reference.md — `<Project Name>`

> Single source of truth for brand facts, copy patterns, and product specifics. AI reads this at the start of every copy-writing session.
>
> **Last verified**: YYYY-MM-DD by `<person>`
> **Workflow**: see [`~/.claude/skills/wp-stack/workflows/content-reference.md`](../../.claude/skills/wp-stack/workflows/content-reference.md)

---

## §1 Brand identity quick facts

Locked numbers — verified, do not invent. Each fact uses one of three flags:
- ✅ Verified (use this)
- ⚠️ Conflict (some pages still show V0 — see §10)
- ❓ Unverified (do NOT use)

| Fact | Value | Status | Source |
|---|---|---|---|
| Brand name (legal) | `<Brand Inc.>` | ✅ | Business registration |
| Brand name (display) | `<BrandX>` | ✅ | Logo style guide |
| Founding year | YYYY | ✅ | Registration paperwork |
| Founder | `<Name>` | ✅ | Founder bio |
| Team size | N (full-time) + M (contractors) | ✅ | HR roster |
| Headquarters | `<City, Country>` | ✅ | Lease agreement |
| Service area | `<Country / Region>` | ✅ | — |
| Tax ID / VAT | `<XXXXX-XXX>` | ✅ | — |
| Phone | `+<country> <number>` | ✅ | Verified hotline |
| Email (sales) | `sales@<domain>` | ✅ | — |
| Email (support) | `support@<domain>` | ✅ | — |
| Website | `https://<domain>` | ✅ | — |

---

## §2 Service / product catalog

For every service / product, capture:
- Name (canonical)
- Tagline (short value prop)
- Price (range or starting from)
- Tech specs (model, capability, capacity)
- Use cases (when this service fits)
- Add-on options

Sample:
```markdown
### Service A — `<canonical name>`

- **Tagline**: <one sentence value prop>
- **Price**: $X – $Y / unit (or "Custom quote")
- **Specs**: 
  - Capacity: ...
  - Coverage: ...
  - Output: ...
- **Use cases**:
  - <case 1>
  - <case 2>
- **Add-ons**:
  - <add-on 1> +$Z
  - <add-on 2> +$W
- **NOT for**: <anti-use-case so AI doesn't oversell>
```

Repeat per service.

---

## §3 Portfolio / case studies database

When AI writes "we worked with X / Y / Z" copy, it must pull from this list. Otherwise it invents.

| Project name | Date | Partner / Client | Outcome / Headline | Visible publicly? |
|---|---|---|---|---|
| `<Project A>` | YYYY-MM | `<Partner>` | `<headline-grade outcome>` | ✅ / ❌ |
| `<Project B>` | YYYY-MM | `<Partner>` | `...` | ✅ / ❌ |

Mark "Visible publicly" — some projects are NDA-restricted; AI must NOT mention these on the public site.

---

## §4 Equipment / asset inventory

Specifics matter — "high-end equipment" is weak; "RED Komodo 6K + Aputure 600d Pro lighting" builds trust.

| Equipment | Model / Spec | Quantity | Use |
|---|---|---|---|
| `<Equipment 1>` | `<exact model>` | N units | `<typical use>` |

---

## §5 Partner / vendor database

Logos / agencies / brands the project has worked with. Mark which can be displayed publicly:

| Partner | Type | Public display OK? | Logo URL |
|---|---|---|---|
| `<Brand A>` | Client | ✅ | `<logo url>` |
| `<Agency B>` | Vendor | ❌ NDA | — |

---

## §6 Blog catalog + topic gaps

Track what's published + what's missing. Helps content planning.

```markdown
### Published

| Post title | Slug | Date | Topic | Performance (organic) |
|---|---|---|---|---|
| `<post 1>` | `/post-1/` | YYYY-MM | `<topic>` | low / mid / high |

### Topic gaps (to write next)

- `<gap 1>` — keyword opportunity, target query: "..."
- `<gap 2>` — competitor content gap
```

---

## §7 Tone & voice patterns

For brand voice consistency.

```markdown
### Do

- Direct, specific, factual ("3 fields", "free 30-min consultation", "delivered in 4 hours")
- Acknowledge the customer's pain before the solution
- Use industry terms correctly (no fake jargon)
- Vietnamese: friendly-formal (em / mình / quý anh chị) — not stiff (chúng tôi tự hào)

### Don't

- Generic claims ("high-quality", "leading", "world-class")
- Empty intensifiers ("the most amazing", "literally the best")
- Aspirational facts not yet true ("serving 1000+ clients" when count is 47)
- English ad-speak transplanted into Vietnamese (DON'T: "Discover the difference")
```

Sample do / don't:
| Bad ❌ | Good ✅ |
|---|---|
| "We deliver world-class consulting" | "We deliver consulting in 4 working hours, no SLA penalty if late" |
| "Leading provider of X services" | "47 clients in 2026, 92% repeat in year 2" |

---

## §8 Trust signals

What can the brand legally claim?

| Signal | Detail | Evidence | Public OK? |
|---|---|---|---|
| License | License # `<XXX>`, issued YYYY | Photo of certificate | ✅ |
| Insurance | Liability $<amount> | Policy number | ✅ |
| Certification | ISO / GDPR / etc. | Audit report | ✅ |
| Awards | `<award>` YYYY | Press release link | ✅ |

⚠️ Never invent trust signals. If empty, leave empty.

---

## §9 SEO meta references

Title + description templates so every page is consistent.

```markdown
### Title format templates

- **Service page**: `<Service Name> — <Key Benefit> | <Brand>` (≤60 ch)
- **Pillar page**: `<Topic Pillar>: <Audience> | <Brand>` (≤60 ch)
- **Blog post**: `<Headline-question> | <Brand>` (≤60 ch)
- **Homepage**: `<Brand> — <Tagline>` (≤60 ch)

### Description templates

- **Service page**: `<value prop in 1 sentence>. <USP detail>. <CTA — phone or quote>.` (150–160 ch)
- **Blog post**: `<question or thesis>. <key insight>. <reader benefit>.` (150–160 ch)

### Schema reference

- LocalBusiness Schema: see Rank Math → Local SEO config (canonical fields here)
- FAQPage: 1 per page max (see [`references/seo-checklist.md`](../../.claude/skills/wp-stack/references/seo-checklist.md))
```

---

## §10 Conflicts table

Facts that have changed — track for cleanup.

| Fact | V0 (old) | V1 (current) | Why changed | Pages still showing V0 | Status |
|---|---|---|---|---|---|
| Team size | "50+" | "10 full-time + 8 contract" | V0 was aspirational from 2022 launch | counter-widget id `XXX` on `/`; about-us heading id `YYY` | ⚠️ pending update |
| Founding year | 2022 | 2023 | V0 = domain reg, V1 = business license | footer; about-us | ⚠️ pending update |

When V0 is fully migrated, change Status to "✅ migrated".

---

## §11 Usage guide

### When to read this file
- Start of every copy-writing session
- Before quoting brand facts to user
- Before responding to "is this number right?" questions

### How to update this file
- New fact discovered → add to §1 with status flag
- Fact changes (V0 → V1) → add row to §10 conflicts table + audit pages still showing V0
- Voice example caught → add to §7 do/don't
- Update the "Last verified" timestamp at the top

### When NOT to invent
If a fact isn't in this file → ask the user. Do NOT guess (e.g., "founded around 2020-ish") — that's worse than admitting unknown.

### Cross-references
- [`CLAUDE.md`](CLAUDE.md) — project infrastructure facts (host, paths, credentials)
- [`~/.claude/skills/wp-stack/`](../../.claude/skills/wp-stack/) — universal WP-stack patterns (this file is the project-specific layer)
