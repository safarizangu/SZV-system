# Phase 5 — Performance Audit

**Scope:** Safari Zangu Ventures booking system (PHP 8.3, MySQL 8.0, Apache 2.4/mod_php), local dev instance at `http://localhost/safari-zangu`, DB `safari_zangu`.
**Method:** Read-only. Source review (grep/read of admin, api, app, views), real HTTP timing via `curl -w` against a disposable `qa_perf_audit_tmp` admin account (created in `admin_users`, used for authenticated timing, then deleted — confirmed `DELETE ... rowCount=1` at the end of this audit, no rows or files left behind), and direct CLI invocation of `App\Services\PdfRenderer` to time dompdf rendering in isolation (no files were persisted — the CLI user lacks write permission on `storage/uploads/quotations/`, confirmed by the `Permission denied` warning, so cleanup there was a no-op by inspection). No application file or data was modified. Current data volumes (measured): bookings 17, quotations 18, leads 3, custom_safari_requests 8, commissions 1, itineraries 13, packages 6, accommodations 146, vehicles 4, activities 46, email_queue 42, analytics_events 1182. This is a lightly-loaded local dev box with dev-scale data — all timings below should be read as "cost per operation today," not as evidence the app is slow; several findings are about what happens as these tables grow, not about current symptoms.

---

## Finding 1 — Two synchronous dompdf renders block every booking/quotation email, before the email is even queued

**Severity:** High
**Impact:** Every guest-facing booking submission and every admin "email quotation" / "convert to booking" action pays the full cost of generating two PDFs synchronously, in-request, before `Mailer::queue()` is ever called (which is where the existing background-dispatch optimization lives).
**Risk:** On a lightly loaded box this is already ~0.7–0.8s of added latency on checkout. Under concurrent load (several bookings at once), dompdf's rendering is CPU-bound PHP+GD work inside the Apache worker handling the guest's request — this will scale worse than the DB-bound parts of the app and is the single biggest latency contributor identified in this audit.
**Root Cause:** `App\Services\PdfRenderer::render()` (`app/Services/PdfRenderer.php:78-127`) is called twice (quotation + itinerary, or booking + itinerary) directly in the controller, and only once both calls return does the code call `Mailer::queue()`/`queueRaw()`, which is the point where `Mailer::attemptImmediateSend()` tries to hand off to a detached background process via `exec()` (`app/Mail/Mailer.php:142-187`). The background-dispatch optimization in Mailer only covers the SMTP send — it never covers the PDF generation that happens before it.
  - `api/book.php:270-284` — `PdfRenderer::renderQuotation()`/`renderBooking()` then `renderQuotationItinerary()`/`renderBookingItinerary()`, then `Mailer::queue()`.
  - `api/convert_quotation.php:161-168` — same pattern.
  - `api/quotation_email.php:283-299` — same pattern (admin-facing, so less urgent, but still synchronous).
**Measured:** Using the CLI harness described above (direct call to `PdfRenderer::renderQuotation(18, ...)` / `renderQuotationItinerary(18, ...)`, record id=18 is a real `quotations` row, `QUO-CLASSIC-01`):
  - `renderQuotation()`: **0.3705s**
  - `renderQuotationItinerary()`: **0.3868s**
  - Combined: **~0.757s** of synchronous dompdf work per email-triggering request, on top of whatever the rest of `book.php`/`convert_quotation.php` does.
**Recommended Fix:** Move PDF generation itself off the request path — e.g. queue a lightweight "render + attach + send" job for `send_single_email.php` to pick up (it already runs as a detached background process via `exec()`), passing the quotation/booking id rather than a pre-rendered file path, and have the background script call `PdfRenderer` itself. This keeps the existing architecture (detached PHP process, same `sendRow()` logic) but moves the 700ms+ CPU cost out of the guest's response time entirely, not just the SMTP round-trip.
**File Location:** `app/Services/PdfRenderer.php:78-127`, `api/book.php:270-284`, `api/convert_quotation.php:161-168`, `api/quotation_email.php:283-299`.

---

## Finding 2 — Hero image setting queried from `settings` table twice per page load, on every public page

**Severity:** Low
**Impact:** Every single public page render (home, packages, about, book, confirmation, track, review, contact — anything built on `layouts/app.php`) executes the identical `SELECT value FROM settings WHERE key='hero_image'` query twice: once in `layouts/head.php` (for the CSS `background-image`) and once more in `layouts/app.php` (for the `<link rel=preload>` hint), each with its own try/catch and its own BASE_PATH-normalization logic.
**Risk:** Low at today's traffic (single-row lookup on a tiny table), but it is pure waste on every request and the pattern risks drifting out of sync (two independent copies of the same normalization logic already exist — a fix to one has already needed, per the inline comments, to be mirrored in the other).
**Root Cause:** Duplicated, independently-written lookups instead of computing the value once and sharing it.
  - `layouts/head.php:88-108` (first lookup, drives `.hero-bg` CSS).
  - `layouts/app.php:44-64` (second, identical, lookup, drives `<link rel="preload">`).
**Recommended Fix:** Compute `$_heroImg` once (in `head.php`, which already runs first via `app.php:11`) and have `app.php` reuse that same variable for the preload tag instead of re-querying and re-normalizing.
**File Location:** `layouts/head.php:88-108`, `layouts/app.php:44-64`.

---

## Finding 3 — `mod_expires` is not loaded; the "cache images 1 year / CSS+JS 1 month" directives in `.htaccess` are silent no-ops

**Severity:** Medium
**Impact:** Every static asset (package photos, `main.css`, JS bundles) is re-requested fresh (with only `ETag`/`Last-Modified` for a 304 revalidation, not a skip-the-request-entirely `Cache-Control: max-age=...`) on every repeat visit, undermining the intended caching strategy and adding avoidable round trips for every returning visitor.
**Risk:** Medium — this is a configuration gap, not a code bug, but it means the `.htaccess` author's stated intent ("cache images for 1 year") is not actually happening in this environment, and nothing in CI/deploy would catch that regression.
**Root Cause:** `.htaccess:95-102` wraps the `ExpiresByType` directives in `<IfModule mod_expires.c>` (correctly, so it fails safe rather than erroring), but `apache2ctl -M` on this host lists only `deflate_module`, `rewrite_module`, `alias_module`, etc. — **no `expires_module` and no `headers_module`** are loaded.
**Measured:** `curl -s -D - -o /dev/null http://localhost/safari-zangu/assets/css/main.css` and `.../assets/images/logo.jpeg` both return `Last-Modified` + `ETag` + `Vary: Accept-Encoding` but **no `Expires` and no `Cache-Control` header at all**. (Separately, every HTML response *does* carry the full custom security-header set including CSP — but that is because `app/bootstrap.php:51-58` sets those headers directly in PHP via `header()`, not via `mod_headers` as `.htaccess:11-21,110-112` assumes; those `<IfModule mod_headers.c>` blocks are also silently skipped on this host for the same reason.)
**Recommended Fix:** Enable `a2enmod expires` (and `a2enmod headers`, to remove the current reliance on PHP-level header-setting matching the `.htaccess` comments) in whatever environment this is deployed to, or — since behavior must not depend on which modules happen to be enabled — set `Cache-Control`/`Expires` explicitly from PHP for the handful of asset-serving paths that go through PHP, and verify the production host (Hostinger, per `.env`'s commented-out production block) actually has `mod_expires` enabled before relying on it there.
**File Location:** `.htaccess:11-21, 95-102, 110-112`; confirmed via `apache2ctl -M` (modules loaded) and live `curl -D -` headers on `/assets/css/main.css` and `/assets/images/logo.jpeg`.

---

## Finding 4 — PHP's default session cache limiter forces `no-store` on every page, including fully public marketing pages

**Severity:** Low
**Impact:** Every response — including the homepage and packages page, which have no per-visitor state and are safe to cache briefly — is sent with `Expires: Thu, 19 Nov 1981...`, `Cache-Control: no-store, no-cache, must-revalidate`, `Pragma: no-cache`. This defeats any HTTP/CDN-level caching for public pages, on top of Finding 3.
**Risk:** Low functionally (nothing breaks), but it's a missed, easy win: these headers are appropriate for `admin/*` (session-gated, must never be cached) but are currently applied uniformly to public pages too, as a side effect of calling `session_start()` on every request rather than a deliberate choice.
**Root Cause:** `app/bootstrap.php:118-125` calls `session_start()` unconditionally for every request. PHP's default `session.cache_limiter` is `nocache`, which emits exactly the headers observed. Grepping the codebase for explicit `Cache-Control` / `no-store` turns up only `api/quote_view.php:14` and `api/dashboard_stats.php:4` — the no-cache headers on public HTML pages are not a deliberate choice anywhere in the code, they're a PHP default nobody has overridden.
**Measured:** `curl -s -D - -o /dev/null http://localhost/safari-zangu/packages.php` — response headers include `Cache-Control: no-store, no-cache, must-revalidate`.
**Recommended Fix:** Call `session_cache_limiter('')` (or set an explicit short `public, max-age=60`-style `Cache-Control` after `session_start()`) for public-facing controllers specifically, leaving the stricter no-cache behavior for `admin/*`.
**File Location:** `app/bootstrap.php:118-125`.

---

## Finding 5 — `admin/analytics.php` runs ~20 sequential full-scan aggregate queries per load, several using leading-wildcard `LIKE`

**Severity:** Medium (today), trending toward High as `analytics_events` grows
**Impact:** This is measurably the slowest admin page audited (below), and the gap will widen as the table grows — it is an append-only event log fed by every public page view/click.
**Risk:** `analytics_events` currently has 1182 rows and the page is already the slowest in the admin panel. At realistic traffic (one row per page view/interaction) this table can reach six figures within a year; none of these 20 queries are currently indexed to avoid a full scan under that growth, and several explicitly can't use the existing index.
**Root Cause:** `admin/analytics.php` issues ~20 independent `aq()`/`aqv()` calls (lines 39-162), each scanning `analytics_events` from scratch for the selected date range, with zero result caching between them even though most share the same `WHERE created_at >= $since` predicate. The existing composite index `idx_analytics_event_created (event_name, created_at)` cannot be used for the `event_name LIKE '%page_view%'` / `NOT LIKE '%page_view%'` predicates at `admin/analytics.php:41-42,60,122` — a leading wildcard forces a full index/table scan regardless.
**Measured:** `curl` timing against the `qa_perf_audit_tmp` session (3 runs): **0.091s, 0.083s, 0.055s** — roughly 2–4x the next-slowest admin page measured (`accommodations.php`: 0.045/0.037/0.060s; everything else measured under 0.045s, several under 0.02s). At 1182 rows this is not a user-facing problem yet, but it's the clearest leading indicator in this audit of a page that will degrade with data growth rather than stay flat.
**Recommended Fix:** Either (a) store `event_name` normalized so page-view detection is an equality/`IN` check instead of `LIKE '%...%'`, letting the composite index actually be used, or (b) pre-aggregate `analytics_events` into a daily rollup table (written once per day via cron) that this dashboard reads from instead of re-scanning raw events on every admin page load.
**File Location:** `admin/analytics.php:39-162` (20 query block), index defined via `idx_analytics_event_created` on `analytics_events` (confirmed via `SHOW INDEX`).

---

## Finding 6 — Static/seed package photography bypasses the image-optimization pipeline and has no responsive variants

**Severity:** Medium
**Impact:** Public-facing pages (`packages.php`, `home.php`) serve several 1920×1280 JPEGs at 400–760KB each to every visitor regardless of device/viewport.
**Risk:** Directly affects LCP/page-weight on mobile connections, which is where most safari-inquiry traffic for a tourism site like this originates.
**Root Cause:** `App\Helpers\ImageOptimizer` (resizes to a 1920px max edge, re-encodes at quality 82 — `app/Helpers/ImageOptimizer.php:23-70`) is correctly wired into the *admin upload* pipeline (`api/upload_team_image.php:70`, `api/gallery/upload.php:65`), but the static seed images under `assets/images/packages/*` and `assets/images/downloaded/*` were placed directly on disk and never passed through it. Measured on disk: `assets/images/packages/luxury-safari/3.jpg` — 1920×1280, 761KB; `assets/images/packages/short-safari/3.jpg` — 1920×1280, 502KB; `assets/images/downloaded/safari_28.jpg` — 1600×1000, 562KB. `grep -c srcset views/*.php` returns 0 across every public view — no `<picture>`/`srcset` responsive variants exist anywhere, so a phone on a slow connection downloads the same 500–760KB file a 4K desktop monitor would. `loading="lazy"` *is* used correctly on gallery images (`views/packages.php:110,115` and 13 occurrences across `views/home.php`), so this is specifically a missing-resize/missing-responsive-variant issue, not a missing-lazy-load issue.
**Recommended Fix:** Run the existing `ImageOptimizer` (or an offline batch pass using the same settings) over the static seed images once, and consider adding `srcset`/`<picture>` with a smaller mobile variant for the package gallery images specifically, since those are the heaviest assets on the highest-traffic public pages.
**File Location:** `app/Helpers/ImageOptimizer.php:23-70` (pipeline, correct); `assets/images/packages/**`, `assets/images/downloaded/**` (unoptimized files, measured above); `views/packages.php`, `views/home.php` (no `srcset` usage, grep-confirmed).

---

## Finding 7 (informational, not a bug) — Confirmed: previously-flagged N+1 patterns remain fixed

Per the brief, these were checked specifically to confirm no regression — **no new issue found, no fix needed**:
- **`admin/dashboard_agents.php:28-65`** — still exactly 3 grouped/aggregate queries total (`leads` by agent, `commissions` MTD by agent, `commissions` status totals by agent, each `GROUP BY agent_id`), merged into the `$agents` array in PHP, regardless of agent count. The inline comment at lines 12-17/34-36 correctly describes the fix and it is intact.
- **`admin/accommodations.php:77-94`**, **`admin/vehicles.php:66-75`**, **`admin/activities.php:71-80`** — all three fetch the same 3 full-table JSON blobs (`itineraries.days`, `quotations.itinerary`, `packages.itinerary`) exactly once per page load, outside the row-rendering loop, and do string-matching against those blobs in PHP per row rather than issuing a DB query per row. Confirmed by direct read of all three files — the pattern is identical and intentional (comment at `accommodations.php:70-76` explains the design rationale), and none of them query inside the `foreach ($rows as $r)` loop that renders the table.
- **`app/Services/ItineraryQuoteBuilder.php:534-557, 567-589, 597-...`** (`lookupParkRates`, `lookupAccommodationExtras`, `lookupVehicleComponents`) — each uses a `static $cache = []` keyed by name, so a single quote calculation that references the same park/accommodation/vehicle multiple times across itinerary days hits the DB once per unique name, not once per occurrence. This is a per-request cache only (no cross-request persistence) — noted as a genuine, if minor, opportunity below, but it is not an N+1 bug.

---

## Finding 8 — No cross-request cache for park-fee/accommodation/vehicle rate lookups (informational / Low)

**Severity:** Low
**Impact:** Every new quotation calculation re-queries `park_fees`, `accommodations`, and `vehicles` by name for each unique park/property/vehicle referenced in that itinerary (deduplicated *within* the request via the static caches noted in Finding 7, but not *across* requests).
**Risk:** Low today — these are small, indexed, single-row lookups (`WHERE name = ? LIMIT 1`) against tables with 146/4/≤~20 rows respectively — but worth flagging per the brief's explicit callout, since this is exactly the kind of lookup ("park-fee/pricing lookups hit on every quote calculation") that would benefit from an application-level cache (e.g. APCu or a static in-memory registry loaded once per deploy) if quote-calculation volume grows significantly (e.g. a public-facing instant-quote feature).
**Root Cause:** No application-level cache layer exists anywhere in the codebase (confirmed — no APCu/Redis/Memcached usage found in any `app/`, `admin/`, or `api/` file); every cacheable lookup falls back to a fresh prepared statement each request.
**Recommended Fix:** Not recommended as a priority now given current data volumes and request rates — revisit if/when `park_fees`/`accommodations`/`vehicles` lookups show up as a measurable cost under real traffic.
**File Location:** `app/Services/ItineraryQuoteBuilder.php:534-620` (representative lookups).

---

## Email queue — reviewed, not flagged as a finding (by design, and reasonably so)

`app/Mail/Mailer.php` and `api/cron/process_email_queue.php` were read in full.
- **Primary path:** `Mailer::queue()`/`queueRaw()` insert into `email_queue` then call `attemptImmediateSend()`, which first tries `dispatchBackground()` — a detached `exec()`'d PHP process (`app/Mail/Mailer.php:172-187`) running `send_single_email.php`, specifically because this server runs mod_php (no `fastcgi_finish_request()`), and the code's own comments (`Mailer.php:128-141`) record that the previous flush()-based approach was directly measured at ~9s end-to-end and didn't actually detach. The current `exec()`-based dispatch is a legitimate, previously-verified fix for that; it was not re-tested here since doing so would send a real email through live SMTP credentials, which is out of scope for a read-only audit.
- **Fallback path:** if `exec()` is unavailable, falls back to a synchronous 5s-capped send (`Mailer.php:142-162`) — bounded and non-blocking-by-design (fails fast, cron retries).
- **Cron retry path:** `api/cron/process_email_queue.php` processes up to 20 pending rows per run (`$maxPer = 20`, line 28), sequentially, each a synchronous `Mailer::sendRow($row, 20)` call (20s timeout) inside a plain `foreach` loop (lines 45-59) — genuinely synchronous-in-a-loop, but this is the intentional *retry* path for whatever the immediate attempt didn't catch, not the primary delivery mechanism, and is documented as such in the file's own header comment. At a 2-minute cron interval and max 20/run, throughput tops out around 600 emails/hour if every row needed the full 20s timeout (worst case); in practice only failed/slow sends from the immediate-attempt path land here, so this is a reasonable, intentional design trade-off rather than a performance defect. No code change recommended.

---

## Backend/DB timing — measured

Using the `qa_perf_audit_tmp` authenticated session, 3 consecutive `curl -w '%{time_total}'` runs per admin page (local dev box, single-user, no concurrent load — treat as relative ordering, not absolute production numbers):

| Page | Run 1 | Run 2 | Run 3 |
|---|---|---|---|
| `admin/index.php` | 0.048s | 0.018s | 0.018s |
| `admin/dashboard_agents.php` | 0.029s | 0.029s | 0.017s |
| `admin/accommodations.php` | 0.045s | 0.037s | 0.060s |
| `admin/vehicles.php` | 0.023s | 0.018s | 0.019s |
| `admin/activities.php` | 0.031s | 0.041s | 0.046s |
| `admin/bookings.php` | 0.042s | 0.044s | 0.029s |
| `admin/quotations.php` | 0.029s | 0.028s | 0.025s |
| `admin/analytics.php` | **0.091s** | **0.083s** | **0.055s** |
| `admin/dashboard_operations.php` | 0.017s | 0.020s | 0.022s |
| `admin/dashboard_sales.php` | 0.014s | 0.013s | 0.018s |
| `admin/leads.php` | 0.016s | 0.014s | 0.008s |

`admin/index.php`'s main dashboard query set (`admin/index.php:179-193`) is 9 fixed scalar/aggregate queries (not row-scaled, not in a loop) plus 3 "recent N rows" queries — consistent with the fast, flat timings above; no N+1 found there. `analytics.php` is the clear outlier — see Finding 5.

## PDF generation timing — measured

Direct `App\Services\PdfRenderer` calls via CLI (quotation id=18, real data, `QUO-CLASSIC-01`):
- `renderQuotation()`: 0.3705s
- `renderQuotationItinerary()`: 0.3868s

No redundant DB queries were found inside the PDF templates themselves — `api/quotation_pdf.php` does exactly 2 queries (the quotation row, then the linked booking's payment status if any — lines 30-58) and one `json_decode` each for `line_items`/`destinations` (lines 36-37), no re-decode/re-encode cycles. `api/quotation_itinerary_pdf.php` and `api/booking_pdf.php` follow the same single-query-plus-single-decode shape. The cost is dompdf's own HTML→PDF rendering, not wasted DB/JSON work — see Finding 1 for where that cost actually hurts (the synchronous call site, not the template itself).

## Frontend — reviewed

- **Script loading:** `country-codes.js`, `form-validator.js`, `main.js`, `analytics.js` (`layouts/app.php:83-86`) are placed at the end of `<body>`, after all content — **not render-blocking**, no `defer`/`async` needed there since placement already achieves the same effect. No issue.
- **Google Fonts:** loaded via a blocking `<link rel="stylesheet">` (`layouts/head.php:74-81`) with `rel="preconnect"` already in place for both font origins — a reasonable mitigation already present; a font-display swap/preload-with-print-media trick would shave a little more but this is a minor, not-flagged-as-a-finding optimization opportunity.
- **Cache-busting (`?v=`):** `admin/css/admin.css`, `admin/js/admin-validator.js`, `admin/js/admin.js`, `agent-portal/css/agent.css` all use `filemtime()`-based `?v=` cache-busting (`layouts/admin.php:8-9,270-271`, `layouts/agent.php:8-10,68-70`). **`assets/css/main.css` and `assets/css/forms.css` (the public-site stylesheets, `layouts/head.php:83-84`) have no cache-busting at all** — an inconsistency worth noting: if these files are ever edited without a corresponding file rename, returning visitors with a cached copy (to the extent Finding 3 is fixed and caching actually starts working) would not see the update until their cache naturally expires.
- **Image optimization:** see Finding 6.

## Caching — summary

- **Opcache:** enabled under Apache (`opcache.enable=1`), confirmed via a live `phpinfo()`-style check against the running web server (not just CLI): `opcache.memory_consumption=128`, `opcache.validate_timestamps=1`, `opcache.revalidate_freq=2`, `opcache.max_accelerated_files=10000`, `opcache_get_status()['opcache_enabled']=true`. This is a reasonable dev configuration (`validate_timestamps=1` means edited files are picked up without a restart); production should set `validate_timestamps=0` with a deploy-time `opcache_reset()` for maximum effect, but that's a deploy-process note, not a current defect.
- **HTTP caching headers:** intended but not functioning for static assets — Finding 3. Public HTML pages are explicitly `no-store` as a side effect of session handling — Finding 4.
- **Application-level caching:** none exists anywhere in the codebase (confirmed by inspection — no APCu/Redis/Memcached). The one place the brief specifically asks about (pricing/park-fee lookups during quote calculation) already has a *per-request* static cache (Finding 7) and is low-volume enough today that a persistent cache isn't yet justified (Finding 8).

---

## Performance Score: 74 / 100

**Justification:** The core transactional paths (admin dashboards, list pages, PDF templates, the DB layer) are well-built — the previously-fixed N+1 patterns are confirmed intact, the per-row "usage count" lookups are confirmed to be page-load-once (not per-row), the quote-calculation lookups are already memoized per-request, the mailer already solves its own documented synchronous-SMTP problem via a detached process, and opcache is correctly enabled. Nothing in this audit found an actual N+1 query loop, a missing index on a hot path, or a runaway query. The deductions are for: a real, measured ~0.75s of synchronous CPU-bound PDF work sitting in front of every booking/quotation email send (Finding 1, the most consequential single item here); a caching strategy that's specified in `.htaccess` but not actually active on this host because the required Apache modules aren't loaded (Finding 3); a PHP session default silently defeating cacheability on fully public pages (Finding 4); one admin page (`analytics.php`) whose current ~20-query, full-scan, leading-wildcard-`LIKE` design is fine at 1,182 rows but is the one clear "will get worse, not stay flat" item identified (Finding 5); and unoptimized static hero/package imagery on the highest-traffic public pages (Finding 6). None of these are urgent at current (dev-scale) data volumes, which is why the score sits in the "solid, with specific known gaps" band rather than lower — but Finding 1 and Finding 3 are both cheap to fix and would materially improve real-world responsiveness once this goes to production traffic.
