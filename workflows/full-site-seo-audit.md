# Full-site SEO audit — crawl-once + multi-agent /60 template

A structured audit that scores a WordPress site out of 60 (6 Google SEO categories × 10) using a **crawl-once → deterministic aggregate → multi-agent deep-read → adversarial verify → synthesize** pipeline. Runs in ~13 minutes for a mid-size site (100-300 URLs) at the cost of ~19 agents.

## When to use

✅ Onboarding a new client site — establish objective baseline
✅ Pre-launch verification after content migration
✅ Periodic quarterly review — trend the /60 score over time
✅ Diagnosing "why did organic traffic drop" — compare pre / post score

❌ Single-URL SEO check — use manual crawl + Rank Math built-in SEO score
❌ Content strategy / keyword research — this audits execution, not planning
❌ Backlink audit — external tool (Ahrefs / SEMrush) needed for that

## Prerequisites

- App Password on the site (super_admin or Editor role) for authed REST calls
- Optional: Google Search Console API access (for Core Web Vitals field data — fallback when PSI quota blocked)
- Site's `robots.txt` allows Googlebot (audit assumes production-facing state, not staging behind auth)

## Phase 1 — crawl-once with a single Python stdlib script

Fetch every URL from `sitemap_index.xml` in parallel (threadpool max=5 — LVE-safe on shared hosting). Extract per-URL structured signals into one JSON file. No agent involvement here — this is deterministic mechanical work that must produce identical output on every run.

```python
#!/usr/bin/env python3
"""
seo_crawl.py — single-pass crawl, extract per-URL SEO signals.
Usage: SITE=https://example.com AUTH=Basic\ <b64> python3 seo_crawl.py > seo_crawl_data.json
"""
import json, os, re, urllib.request, urllib.parse
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser

SITE = os.environ['SITE'].rstrip('/')
AUTH = os.environ.get('AUTH')  # optional — Basic <b64>

def fetch(url, follow=True):
    req = urllib.request.Request(url, headers={'User-Agent': 'wp-stack-audit/1.0'})
    if AUTH:
        req.add_header('Authorization', AUTH)
    try:
        with urllib.request.urlopen(req, timeout=15) as r:
            return r.status, dict(r.headers), r.read().decode('utf-8', errors='replace')
    except urllib.error.HTTPError as e:
        return e.code, {}, ''
    except Exception:
        return 0, {}, ''

def extract_signals(html):
    """Extract structured SEO signals from rendered HTML."""
    signals = {
        'title': None,
        'meta_description': None,
        'robots': None,
        'canonical': None,
        'h1_list': [],
        'h2_count': 0,
        'jsonld_types': [],
        'word_count': 0,
        'img_missing_alt': 0,
        'img_total': 0,
        'internal_links': 0,
        'external_links': 0,
    }
    # Title
    m = re.search(r'<title>(.*?)</title>', html, re.I | re.S)
    if m: signals['title'] = m.group(1).strip()
    # Meta description
    m = re.search(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)', html, re.I)
    if m: signals['meta_description'] = m.group(1)
    # Robots
    m = re.search(r'<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']+)', html, re.I)
    if m: signals['robots'] = m.group(1)
    # Canonical
    m = re.search(r'<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)', html, re.I)
    if m: signals['canonical'] = m.group(1)
    # H1 list (may be 0 or >1 — both are audit findings)
    signals['h1_list'] = re.findall(r'<h1[^>]*>(.*?)</h1>', html, re.I | re.S)
    signals['h2_count'] = len(re.findall(r'<h2[^>]*>', html, re.I))
    # JSON-LD types
    for block in re.findall(r'<script[^>]+application/ld\+json[^>]*>(.*?)</script>', html, re.I | re.S):
        try:
            data = json.loads(block)
            if isinstance(data, dict):
                if '@graph' in data:
                    signals['jsonld_types'].extend([n.get('@type') for n in data['@graph'] if isinstance(n, dict) and '@type' in n])
                elif '@type' in data:
                    signals['jsonld_types'].append(data['@type'])
        except Exception:
            pass
    # Word count (rough — strip tags)
    text = re.sub(r'<[^>]+>', ' ', html)
    signals['word_count'] = len(text.split())
    # Images
    imgs = re.findall(r'<img[^>]*>', html, re.I)
    signals['img_total'] = len(imgs)
    signals['img_missing_alt'] = sum(1 for i in imgs if not re.search(r'\balt=["\']', i, re.I))
    # Links (very rough)
    signals['internal_links'] = len(re.findall(r'href=["\'](?:' + re.escape(SITE) + r'|/)', html))
    signals['external_links'] = len(re.findall(r'href=["\']https?://(?!' + re.escape(SITE.split('//')[1]) + r')', html))
    return signals

def get_urls_from_sitemap():
    _, _, xml = fetch(f'{SITE}/sitemap_index.xml')
    sitemaps = re.findall(r'<loc>([^<]+)</loc>', xml)
    urls = set()
    for sm in sitemaps:
        _, _, sub = fetch(sm)
        urls.update(re.findall(r'<loc>([^<]+)</loc>', sub))
    return sorted(urls)

def audit_url(url):
    status, headers, html = fetch(url)
    row = {
        'url': url,
        'status': status,
        'redirect_to': headers.get('Location'),
        'content_type': headers.get('Content-Type', '').split(';')[0],
    }
    if status == 200 and html:
        row.update(extract_signals(html))
    return row

if __name__ == '__main__':
    urls = get_urls_from_sitemap()
    with ThreadPoolExecutor(max_workers=5) as ex:
        rows = list(ex.map(audit_url, urls))
    print(json.dumps(rows, ensure_ascii=False, indent=2))
```

Output: `seo_crawl_data.json` — one row per URL with the signals above. This file is now the **single source of truth** for the rest of the audit — every agent reads it, no agent re-crawls (avoids inconsistency across parallel agents + saves quota).

**No-follow-redirect note**: the crawler doesn't follow 3xx by default so you catch redirect chains + 410 Gone responses in the data. Post-process to identify chains.

**LVE-safe**: `max_workers=5` avoids saturating PHP-FPM worker pool on shared hosting (see [`../references/pitfalls.md`](../references/pitfalls.md) "PHP-FPM exhaustion"). Bump higher on VPS with more capacity.

## Phase 2 — mechanical aggregate (inline, no agent)

Once `seo_crawl_data.json` exists, compute deterministic findings that don't need model judgment:

```python
import json
from collections import Counter

data = json.load(open('seo_crawl_data.json'))

# Duplicate titles
titles = Counter(r['title'] for r in data if r.get('title'))
dupes = {t: c for t, c in titles.items() if c > 1}

# Noindex pages
noindex = [r['url'] for r in data if r.get('robots') and 'noindex' in r['robots']]

# Thin content
thin = [r['url'] for r in data if r.get('word_count', 0) < 300 and r.get('status') == 200]

# Missing meta description
no_desc = [r['url'] for r in data if r.get('status') == 200 and not r.get('meta_description')]

# 410 Gone (spam cleanup rules → catch collateral hits)
gone = [r['url'] for r in data if r.get('status') == 410]

# H1 hygiene
no_h1 = [r['url'] for r in data if r.get('status') == 200 and len(r.get('h1_list', [])) == 0]
multi_h1 = [r['url'] for r in data if len(r.get('h1_list', [])) > 1]

# Alt text coverage
alt_gap_urls = [r['url'] for r in data if r.get('img_missing_alt', 0) > 0]
```

These are the "mechanical scorecard" — number-count findings that go directly into the report without needing an agent to interpret.

## Phase 3 — 8-agent workflow, scoring /10 per Google category

Spawn agents in a `Workflow` script — 6 category agents + 2 special-purpose gate agents. Each reads `seo_crawl_data.json` + can fetch live pages for deep-read.

```javascript
export const meta = {
  name: 'seo-audit-60',
  description: 'Score site /60 across 6 Google SEO categories + 2 gates',
  phases: [
    { title: 'Score', detail: '8 agents in parallel — 6 categories + 2 gates' },
    { title: 'Verify', detail: 'Adversarially verify each critical/high finding' },
    { title: 'Synthesize', detail: '1 agent writes final /60 report' },
  ],
}

const CATEGORIES = [
  { key: 'tech',        prompt: 'Score technical SEO: crawlability (robots.txt, sitemap), canonical hygiene, redirect chains, HTTPS coverage. Cite developers.google.com/search/docs.' },
  { key: 'cwv',         prompt: 'Score Core Web Vitals + performance signals. Use GSC field data via /wp-json/site-kit/v1/modules/analytics-4/data/pagespeed-insights if available; else document that CWV needs field data and score /10 conservatively.' },
  { key: 'content_eeat', prompt: 'Score content quality + E-E-A-T signals: author bylines, expertise credentials, dates, sources, thin content. Reference the Helpful Content system + E-E-A-T guidelines.' },
  { key: 'on_page',     prompt: 'Score on-page: title tags (uniqueness, length), meta descriptions, H1 hygiene, keyword-in-title alignment, internal linking depth.' },
  { key: 'schema',      prompt: 'Score structured data: Organization / LocalBusiness / Article / Product coverage, @id consistency, sd-policy compliance. Cite sd-policies for any YMYL concerns.' },
  { key: 'local',       prompt: 'Score Local SEO: NAP consistency across site + GBP + schema, Local Business fields, service-area markup.' },
]

const GATES = [
  { key: 'spam_gate',      prompt: 'GATE: scan for spam-hack indicators (rogue sitemap URLs, .htaccess injection artifacts, uploads/ .php files). Flag CRITICAL if any found. Return { blocked: true|false, findings: [...] }.' },
  { key: 'index_hygiene',  prompt: 'GATE: score index hygiene — noindex coverage on thin/archive pages, canonical alignment, sitemap accuracy vs indexable pages. Flag if archive bloat detected.' },
]

phase('Score')

const scores = await parallel([...CATEGORIES, ...GATES].map(c => () =>
  agent(
    `Read seo_crawl_data.json. ${c.prompt} Score /10. Return { category: "${c.key}", score: 0-10, findings: [{ severity: "critical|high|med|low", title, evidence, recommendation, google_doc_url }] }`,
    { label: `score:${c.key}`, phase: 'Score', schema: SCORE_SCHEMA }
  )
))

// Adversarial verify each critical/high finding on live pages
phase('Verify')

const criticalFindings = scores.filter(Boolean)
  .flatMap(s => (s.findings || []).map(f => ({ ...f, category: s.category })))
  .filter(f => f.severity === 'critical' || f.severity === 'high')

const verified = await parallel(criticalFindings.map(f => () =>
  agent(
    `Adversarially verify this finding on live site: ${JSON.stringify(f)}. Fetch the specific URL cited, confirm the issue actually reproduces. Return { confirmed: true|false, reason: "..." }`,
    { label: `verify:${f.title.slice(0,30)}`, phase: 'Verify', schema: VERIFY_SCHEMA }
  ).then(v => ({ ...f, verify: v }))
))

const survivors = verified.filter(Boolean).filter(f => f.verify?.confirmed)

// Synthesize final report
phase('Synthesize')

const report = await agent(
  `Write /60 SEO audit report. Category scores: ${JSON.stringify(scores)}.
   Verified critical/high findings: ${JSON.stringify(survivors)}.
   Format: template from anthropic-skills/seo-google skill.
   Include: executive summary, category-by-category score table (X/10), findings prioritized by severity, quick-win recommendations, long-term recommendations.`,
  { label: 'synthesize:report', schema: REPORT_SCHEMA }
)

return { report, scores, verified: survivors }
```

**Why parallel between phases**: Score and Verify are staged intentionally — Verify needs the collected findings from Score to know what to verify. Within each phase, agents run concurrently.

**Why the adversarial verify**: score agents optimize for coverage → tend to over-flag. Verify agents optimize for confirmed problems → cull false positives before they hit the report. Common cull rate: 20-40% of critical/high findings turn out to be model over-reads that don't reproduce on live fetch.

**Time budget**: ~19 agents at ~40s each with 8-way parallelism = ~13 minutes wall-clock for a mid-size site.

## Phase 4 — CWV field data fallback (PSI quota blocked)

Google PageSpeed Insights API quota-blocks aggressively when many pages are audited (429 / 403 after ~25 requests/hour in free tier). Don't fabricate CWV data — use the fallback:

1. **GSC field data via Site Kit** — `/wp-json/site-kit/v1/modules/search-console/data/searchanalytics` gives real Core Web Vitals rollup from CrUX. Site Kit must be connected + `analytics.readonly` scope granted.
2. **If Site Kit not configured** — document explicitly in the report that CWV was NOT measured (score 5/10 as "unmeasured, unknown" rather than fabricating).

Never make up LCP / CLS / INP numbers. False confidence in CWV data misleads the client + costs credibility when they verify with real tools.

## Deliverables

Save all three to `<project>/audit/`:

- `seo_crawl_data.json` — raw crawl output (Phase 1)
- `seo_audit_findings.json` — scored + verified findings (Phase 3)
- `seo_audit_report.md` — human-readable /60 report (Phase 3 output)

Report format follows the `anthropic-skills/seo-google` skill template:
- Executive summary (2 paragraphs — score + top 3 findings + quick wins)
- 6-category score table (X/10 with 1-line rationale each)
- Critical / High findings with cited Google docs
- Prioritized action list (quick wins first, structural fixes second)

## Cross-references

- [`comprehensive-audit.md`](comprehensive-audit.md) — 8-dimension audit (broader, less SEO-deep)
- [`seo-audit.md`](seo-audit.md) — narrower per-page SEO tools (Rank Math integration)
- [`../references/seo-checklist.md`](../references/seo-checklist.md) — the checklist this audit scores against
- [`../references/schema-jsonld.md`](../references/schema-jsonld.md) — schema scoring reference
- [`ga4-admin-api.md`](ga4-admin-api.md) — set up event tracking to enable "conversions per organic session" scoring in future audits
- Insight source: weekly distillation 2026-06-18 (crawl-once + multi-agent + adversarial verify)
