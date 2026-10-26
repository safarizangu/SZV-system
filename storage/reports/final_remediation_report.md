# Final Remediation Report — Safari Zangu Ventures

**Branch:** `audit-remediation` (13 commits over `main`)
**Date:** 2026-10-26
**Source audit:** `storage/reports/master_audit_report.json` / `executive_summary.md` (2026-10-25, 8 parallel agents, 79 findings)
**Validation:** `storage/reports/post_fix_audit.md` (self-assessed re-check of all 79 findings — read its Methodology section before quoting the scores below)

---

## Summary

70 of 79 findings resolved (100% of Critical, 100% of High, 90% of Medium, 78% of Low). The 9 left open are each documented with a specific reason — three are findings the original audit itself already called non-issues (DEVOPS-8, PERF-7, UIUX-4); the rest are deliberate effort/risk trade-offs, not oversights. See `post_fix_audit.md` for the full per-finding ledger.

| Category | Before | After (self-assessed) | Target | Met |
|---|---|---|---|---|
| Architecture | 61 | 96 | >85 | ✓ |
| Security | 58 | 99 | >90 | ✓ |
| Database | 74 | 99 | >85 | ✓ |
| API | 64 | 99 | >90 | ✓ |
| Performance | 74 | 94 | >90 | ✓ |
| UI/UX | 82 | 99 | >90 | ✓ |
| Accessibility | 42 | 100 | >90 | ✓ |
| DevOps | 61 | 99 | >85 | ✓ |
| **Overall** | **65** | **~98** | — | — |

110 files changed (5,640 insertions, 1,639 deletions) across 13 commits. 6 files deleted as confirmed-dead code. 7 real schema migrations applied (plus one CI-only schema snapshot, deliberately not a real migration — see below).

---

## Vulnerabilities fixed

- **PII/financial disclosure via weak credentials** (SEC-1/API-6): `api/check_booking.php` upgraded from ref+travel_date to the hashed Track Booking password.
- **IDOR**: `public/book.php?quote_id=` (sequential integer) replaced with `?quote=<token>` (192-bit `public_token`) (SEC-2).
- **Unauthenticated settings oracle**: `api/get_setting.php` deleted — was leaking `bank_account_no` to anyone (SEC-4/API-2).
- **Missing ownership scoping**: `api/manifest_pdf.php` let an agent pull every other agent's manifests; now denies the agent role (SEC-5/API-1).
- **Privilege escalation**: a plain `admin` could self-promote to `superadmin`; `admin/users.php` now blocks it (SEC-3).
- **CSRF bypass**: a vestigial `payment_method==='paypal_callback'` branch skipped CSRF validation in `api/book.php`; removed (SEC-6/API-3).
- **Payment race condition**: `api/record_payment.php` could double-credit a payment on a near-simultaneous retry; now locks the row in a transaction before the idempotency check (API-4).
- **Brute-force gaps**: admin login lockout was IP-only (added account-keyed lockout, SEC-8); 4 endpoints bypassed the 30-minute session timeout by checking `$_SESSION` directly instead of `AuthMiddleware` (SEC-9); `views/confirmation.php`'s ref+date lookup had no rate limiting at all, unlike every other credential check in the app (SEC-7).
- **Credential exposure in URLs**: the Track Booking password sat in 3 PDF-download links' query strings indefinitely (browser history, server logs, Referer headers); replaced with a 15-minute, download-only token (SEC-10).
- **Live information disclosure**: 5 leftover audit-artifact files (including one listing the site's own prior security findings) were publicly fetchable; moved/blocked, plus a new `.htaccess` rule blocking any stray `.json`/`.txt`/`.log` URL (DEVOPS-1).
- **Unprotected `vendor/`**: `vendor/autoload.php`, `composer.lock`, and other dependency files were directly web-fetchable, disclosing exact package versions; blocked (DEVOPS-4).
- **Data-integrity gaps now enforced**: a real FK added for `accommodations.park_key → park_fees.park_key` (previously unenforced — DB-12), which surfaced and fixed a live bug (`admin/accommodations.php` saving `''` instead of `NULL` for "no park").

## Accessibility fixed (13 of 13 findings)

- 2 Critical: 280 unlabeled form inputs across admin/agent-portal now have proper `for`/`id` association (A11Y-1); all 21 modal dialogs now have ARIA (`role="dialog"`, `aria-modal`, `aria-labelledby`) and real focus-trapping, via a MutationObserver-based system rather than touching each page's own open/close logic (A11Y-2).
- 3 High: missing `:focus-visible` outlines restored on admin inputs and public form inputs (a CSS cascade-order bug) (A11Y-3/4); `aria-live`/`role="alert"` added to login and form-submission error messages (A11Y-5).
- 5 Medium + 3 Low: WCAG AA contrast fixes (a new `--gold-text` token, 6.37:1 vs the original 2.66:1), kebab-menu keyboard navigation, filename-as-alt-text fixed, missing `alt` attributes added, a fabricated hero-image alt text corrected (on a page later found to be dead code — see below), a heading-hierarchy skip fixed, skip-links added to every layout (plus a genuine pre-existing bug found while verifying: literal stray backslashes had silently broken the public skip-link's own JS the whole time).

## Performance gains

- PDF rendering moved off the synchronous request path (`api/book.php` previously blocked ~0.76s per booking on 2 dompdf renders before the user got a response) — now a lazy-attachment spec resolved inside `Mailer::sendRow()` (PERF-1).
- `admin/analytics.php`'s 4 leading-wildcard `LIKE '%page_view%'` queries (full table scans, confirmed via EXPLAIN) replaced with an indexed event-name list — `type:range` instead of a full scan (PERF-3).
- The hero-image setting was queried from the database twice on every single public page load; now once (PERF-2).
- `session_start()`'s default cache limiter forced `Cache-Control: no-store` on every page including pure marketing pages that have nothing session-specific to protect; now sent explicitly only where it matters, leaving marketing pages cacheable (PERF-5).
- Not fixed, and why: `mod_expires`/`mod_headers` are disabled in this local environment and enabling them needs root (`a2enmod` + reload), which this session doesn't have access to — needs checking directly on the Hostinger account (PERF-4). The static-image optimization gap (PERF-6) was tested directly and found to need a real responsive-image feature (multiple sizes + `srcset`), not a quick recompression pass — see `post_fix_audit.md` for the measured evidence.

## Files changed

**New files (22):**
- `.github/workflows/tests.yml`, `sql/ci_schema_snapshot.sql` — CI (DEVOPS-6)
- `admin/js/pricing_shared.js` — deduplicated pricing/itinerary JS (ARCH-1)
- `app/Services/BookingCostBreakdown.php` — deduplicated VAT/service-charge logic (ARCH-3)
- `app/Helpers/AppLog.php` — real file-based logging for the two admin "Live Log" panels (DEVOPS-2)
- `api/cron/prune_old_records.php`, `api/cron/prune_analytics_events.php` — new retention crons (DB-2, DB-3)
- 7 new SQL migrations (listed below)
- `storage/reports/*` — the original audit's own output, relocated here (not new work product) as part of closing DEVOPS-1's live-exposure finding

**Deleted (6, all confirmed dead via repo-wide grep):**
`admin/guide.php`, `admin/includes/sidebar.php`, `admin/includes/topbar.php`, `api/dashboard_stats.php`, `api/get_setting.php`, `views/about.php` (orphaned — `public/about.php` has rendered its own inline content since an earlier refactor and never included it; found while verifying an earlier accessibility fix that had been applied to this dead file).

**Also deleted:** `php/PHPMailer/` (7,243 lines, untracked/gitignored — a dead vendored duplicate of the Composer package actually in use).

**Modified:** 82 files across admin/, agent-portal/, api/, app/, assets/, layouts/, public/, views/, config/, docs/, cron/ — see `git log --stat audit-remediation` for the full list; the 13 commit messages (below) describe each phase's changes and verification in detail.

## Migrations added

Run via `php sql/migrate.php` (or apply directly + `INSERT IGNORE INTO schema_migrations`, as these were):

| File | Purpose |
|---|---|
| `2026_10_db1_booking_reminders_fk.sql` | Archives 34 orphaned rows, adds FK on `booking_reminders.booking_ref` |
| `2026_10_db2_analytics_events_index.sql` | Adds `idx_analytics_created_at` |
| `2026_10_db10_drop_duplicate_booking_package_fk.sql` | Drops the duplicate FK on `bookings.package_id` |
| `2026_10_db12_accommodations_park_key_fk.sql` | Normalizes `''`→`NULL`, adds FK on `accommodations.park_key` |
| `2026_10_db456_dashboard_indexes.sql` | 6 indexes for dashboard widget queries |
| `2026_10_db7_fix_collation_mismatch.sql` | Converts `countries`/`locations` to `utf8mb4_unicode_ci` |
| `2026_10_db9_resync_profit_margin.sql` | One-time resync of `packages.profit_margin_percent` |

`sql/ci_schema_snapshot.sql` is **not** a migration — see its own header comment for why (replaying the real migration history against an empty database fails; this is a straight schema dump used only to bootstrap CI).

## Residual risks (the 9 open findings, by severity)

**Medium (3):**
- **ARCH-2** — `ItineraryQuoteBuilder::build()` is a 465-line pricing-engine function. Left untouched: splitting it is pure readability work with real regression risk on the app's most financially-sensitive code.
- **PERF-4** — `mod_expires`/`mod_headers` disabled; needs root access this session doesn't have. Check directly on the Hostinger account.
- **PERF-6** — static package images need responsive variants (`srcset`), not just recompression (tested and confirmed negligible gain from the latter). A template + asset-pipeline feature addition.

**Low (6):**
- **ARCH-12** — trivial Service→Middleware dependency, audit calls it isolated.
- **DB-11** — hard-deleting an admin_user nulls attribution on 4 tables' records; needs a name-snapshot column + write-path change across all 4.
- **SEC-11** — CSP `unsafe-inline`; removing it needs a sitewide inline-script/style audit for nonces.
- **API-9** — no API versioning; architectural.
- **DEVOPS-8** — log rotation is an OS default; audit's own text says "not an application-level decision."
- **PERF-7** — no cross-request cache for park/accommodation/vehicle lookups; audit's own text says "not currently worth building."

None of these are urgent. They're a written-down backlog, not something lost track of.

## Recommended future improvements

1. **Independent re-audit** of this remediation's own new code (`BookingCostBreakdown`, `pricing_shared.js`, the SEC-10 token system, `AppLog`, the new cron scripts) — this report self-graded its own work; a fresh audit pass is the real closeout, not this document.
2. **External uptime monitoring** (e.g., UptimeRobot free tier) — flagged under DEVOPS-5 as something that needs an external account and can't be set up from code.
3. **Confirm PERF-4 on the real Hostinger account directly** rather than assuming this local dev environment's Apache config matches production.
4. If/when there's appetite for it: the responsive-image pipeline (PERF-6) and a name-snapshot column for deleted-user attribution (DB-11) are the two residual items with the clearest concrete path to "done," if revisited.
5. Consider scheduling the independent re-audit (#1) before the next production deploy, given the volume of change in this branch (110 files, 13 commits) relative to the size of the original codebase.

## Commit log

```
c9d1525 Phase 10b: close the Medium-severity findings the phase plan skipped
177cd57 Phase 10: remaining Low-severity findings
b769362 Phase 9: DevOps fixes (DEVOPS-2, DEVOPS-3, DEVOPS-4, DEVOPS-5)
19d63de Phase 8: architecture fixes (ARCH-1, ARCH-3, ARCH-4, ARCH-8)
5371744 Phase 7: fix 5 of 6 UI/UX findings (UIUX-1,2,3,5,6); UIUX-4 left as-is
ffed0df Phase 6: close all 11 remaining accessibility findings (A11Y-3 through A11Y-13)
843b157 Phase 5: defer PDF generation off the request path (PERF-1), fix analytics full scans (PERF-3)
48ba348 Phase 4: fix payment race condition (API-4) and remove CSRF bypass (API-3)
ba7fd68 Phase 3: database fixes (DB-1 orphans + FK, DB-2 index + retention)
26e6cdc Phase 2: fix 5 High-severity security findings (SEC-1,2,3 / API-1,2)
581aac2 A11Y-2 (Critical): add ARIA + focus management to every modal dialog
afc4399 A11Y-1 (Critical): associate every admin/agent-portal form label with its input
2a6d506 Phase 0 + DEVOPS-1: safety net, and close live audit-artifact exposure
```

Every commit in this list was followed by a full `php tests/run.php` run (79/79 passing at every stage) and, for every behavioral change, live verification against the running app with disposable QA accounts/bookings — never source-reading alone. See each commit's own message for its specific verification notes.
