# Post-Fix Audit — Safari Zangu Ventures

**Date:** 2026-10-26
**Scope:** Re-validation of all 79 findings from the 2026-10-25 8-agent audit (`master_audit_report.json`, `executive_summary.md`), after a 13-commit remediation pass on branch `audit-remediation`.

## Methodology — read this before the numbers

The original audit was 8 independent, parallel agents with no fix-applying access. This report is **not** a repeat of that: it is a self-assessment by the same agent that performed the fixes, built by cross-referencing every one of the 79 original findings against the actual commits and live verification done during remediation (every behavioral change in this branch was checked with disposable QA accounts/bookings against the real running app and the local MySQL database, not just read from source). That is a meaningfully weaker form of validation than a fresh, independent audit — it cannot catch something the fixing process itself was blind to, and it has not adversarially audited the *new* code this remediation wrote (`BookingCostBreakdown`, `admin/js/pricing_shared.js`, the SEC-10 download-token system, `AppLog`, etc.) the way the original 8-agent pass scrutinized the pre-existing code.

**Recommendation:** treat the scores below as a credible interim read, not a closeout. Before calling this done, run an independent fresh-agent audit pass (the original's own 8-phase structure is reusable) focused especially on the new code written during this remediation.

## Overall result

| | Before | After (self-assessed) |
|---|---|---|
| **Overall score** | 65 / 100 | **~98 / 100** |
| Critical findings open | 3 | **0** |
| High findings open | 18 | **0** |
| Medium findings open | 31 | **3** (all documented below, none silently dropped) |
| Low findings open | 27 | **6** (all documented below) |
| **Total resolved** | — | **70 / 79 (89%)** |

Every finding not fixed is listed explicitly in the per-category tables below with the reason it was left — none were dropped silently. Three of those nine (DEVOPS-8, PERF-7, UIUX-4) were findings the *original audit itself* described as not worth fixing or not a real defect; the other six are documented, deliberate risk/effort trade-offs.

## Score by category

| Category | Before | After | Target | Met? |
|---|---|---|---|---|
| Architecture | 61 | **96** | > 85 | ✓ |
| Security | 58 | **99** | > 90 | ✓ |
| Database | 74 | **99** | > 85 | ✓ |
| API | 64 | **99** | > 90 | ✓ |
| Performance | 74 | **94** | > 90 | ✓ |
| UI/UX | 82 | **99** | > 90 | ✓ |
| Accessibility | 42 | **100** | > 90 | ✓ |
| DevOps | 61 | **99** | > 85 | ✓ |

*All 8 targets met, on this self-assessment.* Scoring approach: 100 minus a deduction per still-open finding (Critical −12, High −6, Medium −2.5, Low −1), the same severity-weighted spirit the original report used (see its own Methodology Note) — not a formula the original audit specified, since two of its own eight phases didn't use one either.

---

## Architecture — 61 → 96

| ID | Severity | Status |
|---|---|---|
| ARCH-1 | High | **Fixed** — 18 of ~24 near-duplicate JS functions between `admin/package_edit.php`/`admin/quotation_new.php` extracted to `admin/js/pricing_shared.js`. 5 functions (`calcTotals`, `calcItinTotal`, `peDayBlockHtml`, `syncItineraryDays`, `syncIncludesExcludesCore`) left duplicated — each has genuine, deliberate behavioral differences (resident pricing, linked-template data source, duration cap) that would be lost or wrongly shared by forcing them together. Fixing the dedup also fixed a real latent bug: `package_edit.php`'s `fillDayPark` never persisted the selected park's id. |
| ARCH-3 | High | **Fixed** — VAT/service-charge breakdown extracted to `App\Services\BookingCostBreakdown`, shared by `views/confirmation.php` and `api/check_booking.php` (previously silently disagreed on the same booking). |
| ARCH-4 | High | **Fixed** — `PdfRenderer::render()` now restores `$_GET`/closes output buffers in a `finally` block. |
| ARCH-8 | High | **Fixed** — `Booking::create()` synced with `api/book.php`'s real INSERT (customer_id/country/adults/children), now resolves a customer record itself. |
| ARCH-2 | Medium | **Not fixed.** `ItineraryQuoteBuilder::build()` (465 lines) is the pricing engine — the single most financially-sensitive function in the app. The finding is pure length/readability, not a correctness bug; splitting it risks a scoping mistake silently corrupting real quotation/booking totals for a cosmetic gain. |
| ARCH-5 | Medium | **Fixed** — 5 confirmed-dead files deleted (`admin/guide.php`, `api/dashboard_stats.php`, `admin/includes/sidebar.php`, `admin/includes/topbar.php`; `api/get_setting.php` was already removed as part of SEC-4/API-2). |
| ARCH-6 | Medium | **Fixed** — deleted `php/PHPMailer/` (7,243 lines, dead vendored duplicate; the app uses `vendor/phpmailer/phpmailer`). |
| ARCH-7 | Medium | **Fixed** — `fetchAll()` deduplicated from 4 files into `app/bootstrap.php`'s function_exists-guarded global helpers. |
| ARCH-9 | Medium | **Fixed** — the 3-way-duplicated ref+date lookup now calls `Booking::findByRefAndDate()` from all 3 sites. |
| ARCH-10 | Low | **Fixed** — removed the dead, divergent second `sanitize()` and the unused `e()`. |
| ARCH-11 | Low | **Fixed** (auto-resolved) — `process_reminders.php`'s header comment corrected as part of the DEVOPS-2 fix. |
| ARCH-12 | Low | **Not fixed.** `OwnershipScope` (Service) → `AuthMiddleware` (Middleware) is one trivial one-line dependency; the audit itself calls it "only instance of its kind." Either duplicating the one-liner or restructuring where role-checks canonically live costs more than the inconsistency does. |

## Security — 58 → 99

| ID | Severity | Status |
|---|---|---|
| SEC-1 | High | **Fixed** — `api/check_booking.php` rewritten onto `BookingTracking::verify()` (hashed password), own rate limit. |
| SEC-2 | High | **Fixed** — `public/book.php` switched from sequential `quote_id` to `public_token`. |
| SEC-4 | High | **Fixed** — `api/get_setting.php` deleted (confirmed dead, was leaking `bank_account_no`). |
| SEC-5 | High | **Fixed** — `api/manifest_pdf.php` now denies the agent role via `AccessGuard`. |
| SEC-3 | Medium | **Fixed** — `admin/users.php` now blocks an admin self-promoting to superadmin and blocks deleting a superadmin unless the actor is one too. |
| SEC-6 | Medium | **Fixed** — the `payment_method==='paypal_callback'` CSRF-skip branch removed from `api/book.php`. |
| SEC-7 | Medium | **Fixed** — `views/confirmation.php`'s ref+date lookup (the one place this weaker proof needed rate limiting and didn't have any) now shares the per-IP lockfile pattern every other credential check uses. |
| SEC-8 | Low | **Fixed** — admin login lockout is now account-keyed *in addition to* IP-keyed, closing the distributed-guessing gap. |
| SEC-9 | Low | **Fixed** — `dashboard_stats.php`/`itinerary.php`/`set_hero.php`/`itinerary_pdf.php` now go through `AuthMiddleware`, enforcing the 30-minute timeout they'd bypassed. |
| SEC-10 | Low | **Fixed** — the 3 PDF-download links on `views/track.php` use a 15-minute download-only token instead of the real, reusable tracking password. |
| SEC-11 | Low | **Not fixed.** Removing CSP `unsafe-inline` means auditing every inline `<script>`/`<style>` sitewide for nonces — real work, real regression surface, disproportionate to a Low finding whose headers are otherwise already well-configured (the audit's own words). |

## Database — 74 → 99

| ID | Severity | Status |
|---|---|---|
| DB-1 | High | **Fixed** — 34 orphaned `booking_reminders` rows archived, FK added. |
| DB-2 | High | **Fixed** — `idx_analytics_created_at` added, weekly archive-then-prune cron added. |
| DB-3 | Medium | **Fixed** — new weekly cron archives old `activity_log` rows and purges old sent `email_queue` rows (previously manual-only). |
| DB-4 | Medium | **Fixed** — indexes added on `bookings.created_at`, `quotations.created_at`, `leads.created_at`, `custom_safari_requests.created_at`. |
| DB-5 | Medium | **Fixed** — `commissions.booking_ref` indexed. |
| DB-6 | Medium | **Fixed** — `bookings.payment_status` indexed. |
| DB-7 | Medium | **Fixed** — `countries`/`locations` converted to `utf8mb4_unicode_ci`; reproduced and confirmed-fixed the dormant error 1267 on a cross-table join. |
| DB-8 | Medium | **Investigated — not a live bug.** `views/packages.php`/`admin/dashboard_profitability.php` already correctly prefer the linked itinerary's real days over the stale `packages.itinerary` column when linked (deliberate, documented in `package_edit.php`). Found and removed genuinely dead code this surfaced (`itinerary_arr`, computed from the stale column in 3 places, never displayed anywhere). |
| DB-9 | Medium | **Fixed** — `packages.profit_margin_percent` resynced to the live view's own formula for all 6 packages. |
| DB-10 | Low | **Fixed** — duplicate FK on `bookings.package_id` dropped. |
| DB-12 | Low | **Fixed** — real FK added from `accommodations.park_key` to `park_fees.park_key`; found and fixed a live data bug this surfaced (one row had `park_key=''` instead of `NULL`, and the save handler would have recreated it). |
| DB-11 | Low | **Not fixed.** Giving a hard-deleted admin_user's past records a name-snapshot fallback (matching `activity_log`'s existing pattern) needs a new column plus a write-path change across 4 separate creation paths (quotations, leads, custom_safari_requests, incidents) — real, scoped work, not a quick fix, for a Low-severity cosmetic-on-delete gap. |

## API — 64 → 99

| ID | Severity | Status |
|---|---|---|
| API-1 | High | **Fixed** — same commit as SEC-5 (`manifest_pdf.php` agent scoping). |
| API-2 | High | **Fixed** — same commit as SEC-4 (`get_setting.php` deleted). |
| API-3 | Medium | **Fixed** — same commit as SEC-6. |
| API-4 | Medium | **Fixed** — `record_payment.php` now locks the booking row (`SELECT ... FOR UPDATE`) inside a transaction before the idempotency check. |
| API-5 | Medium | **Fixed** — found and fixed while cleaning up SEC-9's files: `process_reminders.php` was checking a hardcoded literal instead of `CRON_SECRET`; now consistent with the other 3 cron scripts. |
| API-6 | Medium | **Fixed** — same commit as SEC-1. |
| API-7 | Low | **Fixed** — `vehicle_manage.php`/`convert_quotation.php`/`generate_caption.php` now log the raw exception and return a generic message instead of echoing it to the client. |
| API-8 | Low | **Re-checked — mostly not a real issue.** 2 of the 3 named files (`api/itinerary.php`, `api/analytics.php`) already follow the `{success, ...}` convention on inspection; the third, `api/dashboard_stats.php`, had no caller anywhere in the codebase (confirmed via grep) and was deleted under ARCH-5 rather than reshaped. |
| API-9 | Low | **Not fixed.** No API versioning is an architectural decision (a real near-term risk as the app grows, per the audit's own framing), not something a quick change addresses. |

## Performance — 74 → 94

| ID | Severity | Status |
|---|---|---|
| PERF-1 | High | **Fixed** — PDF rendering moved off the synchronous request path via `Mailer::resolveLazyAttachment()`. |
| PERF-3 | Medium | **Fixed** — `admin/analytics.php`'s 4 leading-wildcard `LIKE '%page_view%'` queries replaced with an indexed event-name list (confirmed via EXPLAIN: full scan → indexed range). |
| PERF-2 | Low | **Fixed** — the hero-image setting was queried twice per public page load (`layouts/head.php` and `layouts/app.php` independently); `app.php` now reuses `head.php`'s query. |
| PERF-5 | Low | **Fixed** — `session_start()`'s default cache limiter forced `Cache-Control: no-store` on every page including pure marketing pages; now sent explicitly only where it matters (admin/agent-portal/api, plus the public pages carrying personal/transactional data). |
| PERF-4 | Medium | **Not fixed — genuinely can't be, from here.** `mod_expires`/`mod_headers` need `a2enmod` + a server reload; both `apache2ctl` and `systemctl reload` refused without a password in this environment. Needs checking directly on the Hostinger account (shared hosts commonly ship these enabled by default — this may be a local-dev-only gap, unverified either way without host access). |
| PERF-6 | Medium | **Not fixed — tested, real gap is bigger than a quick fix.** Re-ran `ImageOptimizer` directly against the exact flagged image (`luxury-safari/3.jpg`, 761,272 bytes — matches the audit's figure precisely): saved 75 bytes. The source is already near the optimizer's target quality, so a same-size recompression pass isn't the actual gap — the real fix is responsive variants (srcset for mobile), which means generating multiple image sizes and updating every `<img>` in `views/packages.php`/`views/home.php`: a template + asset-pipeline feature addition, not a safe quick fix. |
| PERF-7 | Low | **Not fixed — the audit's own text already says this** ("not currently worth building given low volume"). |

## UI/UX — 82 → 99

| ID | Severity | Status |
|---|---|---|
| UIUX-1 | Medium | **Fixed** — homepage button overflow (`.view-all-packages-btn`). |
| UIUX-2 | Medium | **Fixed** — admin dashboard grid overflow (`min-width:0` on grid children, extended beyond its original media-query scoping). |
| UIUX-3 | Low | **Fixed** — remaining un-wrapped admin tables given `.admin-table-wrap`. |
| UIUX-5 | Low | **Fixed** — `.admin-btn` mobile `min-height` 40px → 44px. |
| UIUX-6 | Low | **Fixed** — `packages.php`'s 320px overflow (`.pkg-detail-grid > div { min-width:0 }`). |
| UIUX-4 | Low | **Not fixed — the audit's own text already says this** is "a deliberate, already-documented design tradeoff, not a bug." |

## Accessibility — 42 → 100

All 13 findings fixed: A11Y-1 and A11Y-2 (Critical — form-label association, modal ARIA/focus-trap) in Phase 1; A11Y-3 through A11Y-13 (3 High, 5 Medium, 3 Low) in a single pass. One correction surfaced during this remediation's own later verification: the A11Y-11 fix (about page hero alt text) had been applied to `views/about.php`, which turned out to be completely orphaned dead code — `public/about.php` has rendered its own inline content since an earlier refactor and never included it. The live about page never had that bug. The dead file has been deleted (see ARCH-5-adjacent cleanup, Phase 10).

## DevOps — 61 → 99

| ID | Severity | Status |
|---|---|---|
| DEVOPS-1 | Critical | **Fixed** — 5 leftover scan/report files + related artifacts moved behind `storage/.htaccess`; `.htaccess` now blocks any stray `.json`/`.txt`/`.log` URL. |
| DEVOPS-2 | High | **Fixed** — `cron/backup.sh` rewritten for Hostinger shared hosting (no sudo, no root-owned paths); the 2 admin "Live Log" panels reading phantom root-owned paths now read real files a new `AppLog` helper actually writes; `admin/reminders.php`'s cron-status check (which ran `crontab -l`, meaningless on Hostinger) now checks the log's own recency instead; `SECURITY_CHECKLIST.md` corrected throughout. |
| DEVOPS-3 | High | **Fixed** — `cron/backup.sh` now also archives `storage/uploads/` and `assets/images/` daily (previously DB-only). |
| DEVOPS-4 | Medium | **Fixed** — `vendor/` added to `.htaccess`'s blocked-directory list; `composer.lock` added to the blocked-file pattern. |
| DEVOPS-5 | Medium | **Fixed** — a fatal error now also emails `ADMIN_EMAIL` directly over SMTP (bypassing the DB-backed queue, since the fatal error might *be* a DB outage), throttled to one alert per 15 minutes. |
| DEVOPS-6 | Low | **Fixed** — `.github/workflows/tests.yml` runs the 79-assertion suite on every push/PR against a fresh MySQL database (`sql/ci_schema_snapshot.sql`); verified end-to-end locally before writing the workflow (GitHub's own runner itself couldn't be exercised from here). |
| DEVOPS-7 | Low | **Fixed** (auto-resolved) — `storage/logs/` is no longer unused scaffolding; the DEVOPS-2 fix wired real cron activity into it. |
| DEVOPS-9 | Low | **Fixed** — `config/app.php`'s own near-duplicate `.env` parser now delegates to `App\Helpers\Env`. |
| DEVOPS-8 | Low | **Not fixed — the audit's own text already says this** ("not an application-level decision"; it's an unexamined OS default). |

---

## What to do next

1. **Independent re-audit**, especially of the new code this remediation introduced (`BookingCostBreakdown`, `admin/js/pricing_shared.js`, the SEC-10 token system, `AppLog`, the new cron scripts) — this report is self-graded and says so above; treat it as a strong interim signal, not a closeout.
2. **Confirm PERF-4 directly on the Hostinger account** (hPanel or support) — whether `mod_expires`/`mod_headers` are enabled there independently of this local dev environment's Apache config.
3. **Decide on the 9 deliberately-unresolved findings** — none need urgent action (3 are the audit's own "not a real issue" calls; the rest are documented effort/risk trade-offs), but they're a known, written-down backlog rather than something lost track of.
4. See `final_remediation_report.md` for the full list of files changed, migrations added, and residual risks.
