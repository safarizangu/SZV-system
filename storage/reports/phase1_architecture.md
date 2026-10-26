# Phase 1 — Architecture & Design Audit
Safari Zangu Ventures admin system — read-only audit, PHP 8.3, procedural architecture.

Scope note: this codebase is deliberately procedural (one file per admin page, no router/MVC), and is judged on those terms — "not using MVC" is not itself a finding. The known, audited tradeoff of mirroring `ItineraryQuoteBuilder`'s pricing logic in client-side JS (guarded by `tests/suites/JsPricingConsistencyTest.php`) is likewise not re-flagged here except where the actual duplication footprint turns out to be far larger than that single guarded function.

---

## Finding 1 — Client-side JS duplication across admin pages is ~20x larger than the one function the drift test actually covers

**Severity:** High
**Impact:** Maintainability / correctness. Pricing, day-builder, and itinerary-sync logic can silently diverge between `admin/package_edit.php` and `admin/quotation_new.php` with zero test coverage, and in several cases already has.
**Risk:** A quotation and a package built from logically the same itinerary data can compute different totals, margins, or day layouts depending on which admin screen was used, with no CI signal when this happens.
**Root Cause:** `tests/suites/JsPricingConsistencyTest.php` extracts and checks exactly one function, `peDetectSeason`, out of `admin/quotation_new.php`. In reality, both files carry ~20 near-duplicate JS functions covering pricing totals, park/accommodation/vehicle lookups, day-block rendering, and itinerary-day sync — none of which are checked for drift. A byte-level diff of the functions shared between the two files shows 9 are still byte-identical, but 12 have already diverged in length/content, meaning drift is not hypothetical:
  - `calcTotals`: 11,223 bytes (package_edit) vs 12,341 bytes (quotation_new) — different
  - `calcItinTotal`: 2,215 vs 1,381 bytes — different
  - `fillDayPark`: 362 vs 464 bytes — different
  - `syncItineraryDays`: 839 vs 884 bytes — different
  - `renumberDays`: 1,045 vs 1,040 bytes — different
  - `peDayBlockHtml`: 7,559 vs 7,716 bytes — different
  - `syncIncludesExcludesCore`: 3,717 vs 4,061 bytes — different
  - `addDayActivity`: 1,823 vs 1,825 bytes — different
  - Still byte-identical (for now): `parkChildFee`, `peParkLookup`, `peAccLookup`, `filterDayOptions`, `filterAllDayOptions`, `saveDayActs`, `addDayOther`, `saveDayOther`, `locMatch`, `isArrivalDayFlagged`, `scheduleAutoSync`
**Recommended Fix:** Extract this JS into one shared file (e.g. `admin/js/itinerary-builder.js`) consumed by both pages via `<script src>`, or at minimum extend `JsPricingConsistencyTest.php`'s extraction/diff approach to assert the two files' copies of each of these functions are byte-identical (cheap, catches drift immediately) until a real extraction happens.
**File Location:** `admin/package_edit.php` (functions at lines 909–1826) and `admin/quotation_new.php` (functions at lines 989–1759); test gap at `tests/suites/JsPricingConsistencyTest.php:1-100` (only covers `peDetectSeason`).

---

## Finding 2 — `ItineraryQuoteBuilder::build()` is a 465-line single function (god function)

**Severity:** Medium
**Impact:** Testability and readability. The method aggregates accommodation/vehicle/park-fee groups, resident-rate logic, activities (paid/free), meals, "other" items, and line-item assembly all inline, in one static method.
**Risk:** Any future pricing rule change requires understanding the whole 465-line function's local state (7+ accumulator arrays built incrementally) since there's no sub-function boundary to reason about in isolation; a change to, say, resident park-fee logic can't be tested independently of the whole build.
**Root Cause:** The method grew by accretion (step comments at lines 47 "Step 1: Aggregate resources" and 180 "Step 2: Build line items" show the author's own awareness of the two phases) but was never split into private helper methods per phase/resource-type.
**Recommended Fix:** Split along the already-marked internal steps: `aggregateDayResources()`, `buildAccommodationLineItems()`, `buildActivityLineItems()`, etc., each independently unit-testable. `tests/suites/ItineraryQuoteBuilderTest.php` already exists and exercises `build()`, so this is a safe, test-covered refactor target — flagging for awareness, not urgency.
**File Location:** `app/Services/ItineraryQuoteBuilder.php:35-500` (single method `build()`), phase markers at lines 47 and 180.

---

## Finding 3 — Pricing/VAT/service-charge business logic duplicated into a public view, untested and unguarded

**Severity:** High
**Impact:** A core financial computation (VAT back-calculation, 3-tier service-charge derivation: quotation line items → package cost_breakdown → percentage estimate) lives directly in a public-facing view file rather than in a service, with no test coverage at all.
**Risk:** If `VAT_RATE`, `SERVICE_CHARGE_RATE`, or the real service-fee derivation in `ItineraryQuoteBuilder`/the quotation pipeline changes, this page's displayed cost breakdown can silently show a wrong split between "base" and "service charge" to a customer who just paid — with no test anywhere to catch it, unlike the admin-side JS mirrors which at least have one guarded function.
**Root Cause:** No shared "invoice/cost breakdown presenter" abstraction exists; the one place this logic is needed outside the admin area was written inline instead of being factored into `app/Services/` (e.g. alongside `ItineraryQuoteBuilder::totals()`, which already centralizes VAT/deposit/markup math for the admin side per its own docblock at `app/Services/ItineraryQuoteBuilder.php:501-511`).
**Recommended Fix:** Extract a `CostBreakdown::forBooking(array $booking): array` service method covering the same subtotal/VAT/service-charge/balance logic, call it from `views/confirmation.php`, and add it to the test suite.
**File Location:** `views/confirmation.php:29-81`.

---

## Finding 4 — `app/Services/PdfRenderer.php` inverts the service/controller dependency direction via `include` + superglobal mutation, with an unsafe exception path

**Severity:** High
**Impact:** The service layer (`PdfRenderer`, meant to be the reusable, low-level layer) reaches *upward* into six `api/*_pdf.php` endpoint scripts and `include`s them to scrape their rendered HTML, swapping `$_GET` beforehand so the included script "thinks" it received a normal request (`self::render()` at lines 90-93: `$prevGet = $_GET; $_GET = $getParams; ... include $file;`). This is the opposite of normal layering (controller calls service), and couples the reusable renderer to presentation-layer files that also carry their own auth/business rules (payment-status checks, `OwnershipScope` checks — see `api/quotation_pdf.php:17`, `api/booking_pdf.php:14`).
**Risk:** Concrete bug, not just style: the `$_GET` restore at line 95 and the `ob_get_clean()` at line 94 only run if the `include` on line 93 completes without throwing. The whole block is wrapped in a single `try`/`catch` (lines 89-126), so if the included `api/*_pdf.php` script throws (e.g. a DB error while fetching the quotation), control jumps straight to the `catch` at line 123 — **skipping both the `ob_get_clean()` and the `$_GET = $prevGet` restore.** The output buffer stays open for the rest of the request, and `$_GET` is left permanently overwritten with the fake PDF params (`['id' => ...]` or `['ref' => ...]`) instead of the real request's query string, for every line of code that runs afterward in that request.
**Root Cause:** PDF rendering logic (HTML markup) was written once in the `api/*_pdf.php` browser-view endpoints and never factored out into a template the service could render directly; reusing it via `include` + global-state puppetry was the shortcut instead of extracting a shared render function that takes parameters and returns HTML.
**Recommended Fix:** Minimum: wrap the restore in a `finally` block so `$_GET` and the output buffer are always restored regardless of exception. Better: extract each `api/*_pdf.php`'s HTML-building logic into a plain function/method that both the browser endpoint and `PdfRenderer` call directly with explicit parameters, removing the need to fake a request at all.
**File Location:** `app/Services/PdfRenderer.php:78-127` (method `render()`), especially lines 89-95 and the `catch` at 123-126. Consumers that would be affected by the latent bug: `app/Services/InvoiceMailer.php`, `api/quotation_email.php`, `api/book.php`, `api/convert_quotation.php` (per the class's own docblock, lines 4-21).

---

## Finding 5 — Five files are dead code, never referenced anywhere in the codebase

**Severity:** Medium
**Impact:** Dead admin pages and includes that still pass `AuthMiddleware::requireAdmin()` and query the live database, but are unreachable through any link, nav item, or `fetch()` call — wasted attack surface and maintenance confusion for anyone grepping for "what uses this."
**Risk:** Low direct risk (they require admin auth), but they are stale surface area: `admin/guide.php` renders a full admin guide page that no nav entry links to; `api/dashboard_stats.php` and `api/get_setting.php` are endpoints no JS calls; `admin/includes/sidebar.php` and `admin/includes/topbar.php` are a superseded nav/header implementation — the real nav markup was inlined directly into `layouts/admin.php:27-130` instead, leaving the `includes/` pair orphaned.
**Root Cause:** Refactor-and-abandon: `layouts/admin.php` grew its own inline sidebar/topbar markup (confirmed by reading it — it builds `$cur`, badge counts, and the nav list directly at lines 27+) and nothing was updated to still use `admin/includes/{sidebar,topbar}.php`.
**Recommended Fix:** Delete the five files (confirmed via repo-wide grep for each filename, and via `git log` showing them untouched since Sept/Oct but with zero inbound references), or if `admin/guide.php` is intended to be live documentation, add a nav link to it.
**File Location:** `admin/guide.php`, `api/dashboard_stats.php`, `api/get_setting.php`, `admin/includes/sidebar.php`, `admin/includes/topbar.php`.

---

## Finding 6 — A full vendored copy of PHPMailer (7,243 lines) is dead weight, superseded by Composer

**Severity:** Medium
**Impact:** Supply-chain / maintainability risk: two copies of the same mail library ship in the repo, and the unused one won't receive security patches via `composer update`.
**Risk:** If a future change ever referenced `php/PHPMailer/src/PHPMailer.php` directly (e.g. a copy-pasted `require`), it would silently run a different, unpatched version of PHPMailer than the one actually in use elsewhere.
**Root Cause:** `composer.json` declares `phpmailer/phpmailer: ^7.1` and `vendor/phpmailer/phpmailer` is the copy actually `use`d (`app/Mail/Mailer.php:5-6`, `use PHPMailer\PHPMailer\PHPMailer`). The `php/PHPMailer/` tree (`Exception.php` 40 lines, `SMTP.php` 1,617 lines, `PHPMailer.php` 5,586 lines) has zero inbound references anywhere in the codebase — confirmed via repo-wide grep for `php/PHPMailer` and `PHPMailer/src`.
**Recommended Fix:** Delete `php/PHPMailer/` entirely; it is pure legacy carry-over from before Composer autoloading was wired up (Composer autoload appears purpose-built for this — see `app/bootstrap.php:8-15`'s comment about PDF attachments "silently skipping themselves" before the Composer autoloader was added).
**File Location:** `php/PHPMailer/src/Exception.php`, `php/PHPMailer/src/SMTP.php`, `php/PHPMailer/src/PHPMailer.php`.

---

## Finding 7 — No shared DB query helper; the same `fetchAll($db,$sql)` wrapper is hand-redefined in 4 admin files

**Severity:** Medium
**Impact:** Missing abstraction. `app/Models/Database.php` is purely a PDO connection factory (`getInstance()` only, lines 14-32) with no query-helper methods at all, so every admin page that wants "run this query, return `[]` on failure instead of throwing" has re-implemented the exact same 1-line try/catch wrapper independently.
**Risk:** All four copies happen to be semantically identical today, but because each is a bare global-scope `function fetchAll($db,$sql){...}` (no namespace, no class), the moment any two of these files are ever combined into the same PHP process/include chain (e.g. a future router, or one file including another for a shared partial), it is an instant fatal `Cannot redeclare fetchAll()` error. This is also evidence the team already recognizes the need for this helper but has no single place to put it.
**Root Cause:** `Database` was designed as a connection singleton only; no `Database::fetchAll()` or similar was ever added, so callers improvised locally instead of extending the model.
**Recommended Fix:** Add `Database::fetchAll(string $sql, array $params = []): array` (and similar) to `app/Models/Database.php`, and replace the four local redefinitions.
**File Location:** `admin/itineraries.php:15`, `admin/quotation_new.php:63`, `admin/itinerary_edit.php:122`, `admin/package_edit.php:55` (all `function fetchAll($db,$sql){try{return $db->query($sql)->fetchAll();}catch(\Exception $e){return[];}}`); related near-duplicate `fetchAllSafe($db,$sql,$params)` at `admin/customer_view.php:21`. Root cause location: `app/Models/Database.php:10-36`.

---

## Finding 8 — `App\Models\Booking` is out of sync with the actual production schema usage; its one write path is effectively dead code tested in isolation

**Severity:** High
**Impact:** False confidence from a passing test. `tests/suites/BookingModelTest.php` exercises `Booking::create()` and reports green, but the real booking-creation endpoint never calls it — so the test is validating a code path production traffic never executes.
**Risk:** `Booking::create()` (`app/Models/Booking.php:16-50`) builds an `INSERT INTO bookings` covering `booking_ref, full_name, email, phone, travelers, package_id, package_name, accommodation_level, travel_date, special_request, trip_extension, total_amount, deposit_amount, payment_status, payment_method`. The actual production write, in `api/book.php:172-201`, inserts a materially different column set — `customer_id, country, adults, children` are present in production's INSERT but entirely absent from the model's. The model has silently drifted behind the real schema/flow and nobody would notice from the test suite, since the test only checks the model's own round-trip, not that the model matches what `api/book.php` needs.
**Root Cause:** `api/book.php` was extended (customer linking, adults/children split, country) without updating the corresponding model method; the model was left as a vestigial, untouched alternate path.
**Recommended Fix:** Either delete `Booking::create()` (and its test) since it's unused and misleading, or make `api/book.php` actually call it after bringing it up to date with the current column set — the latter also fixes Finding 9 below.
**File Location:** `app/Models/Booking.php:16-50` vs. actual usage at `api/book.php:172-201`; test at `tests/suites/BookingModelTest.php:35-45`.

---

## Finding 9 — The same "find booking by ref+date" SQL is copy-pasted in 4 places instead of reusing the one model method that exists for it

**Severity:** Medium
**Impact:** `App\Models\Booking::findByRefAndDate()` already exists and is correct, but three other call sites reimplement the identical raw query instead of calling it, so any future change (e.g. adding a `deleted_at IS NULL` guard, or changing case-sensitivity handling — the model lowercases/uppercases the ref via `strtoupper($ref)` at line 57, the duplicates do not consistently) has to be remembered and applied in four places.
**Risk:** Behavioral drift between call sites (the model does `strtoupper($ref)` before querying; `views/confirmation.php` uses `$ref` already uppercased by its own caller at line 3, while `api/paypal_create_order.php` and `api/record_payment.php` were not checked for the same normalization, which is itself a latent correctness risk if any caller ever passes a lowercase ref to one of the duplicate sites but not the model).
**Root Cause:** No habit of reaching for the existing `Booking` model from procedural endpoint files; raw PDO is used ad hoc instead.
**Recommended Fix:** Replace the three duplicate query sites with `(new \App\Models\Booking())->findByRefAndDate($ref, $date)`.
**File Location:** Canonical: `app/Models/Booking.php:52-59`. Duplicates: `views/confirmation.php:19-23`, `api/paypal_create_order.php:56`, `api/record_payment.php:59`.

---

## Finding 10 — Duplicate, drift-prone global function definitions in `bootstrap.php`

**Severity:** Low
**Impact:** `bootstrap.php` defines `sanitize()` twice and `e()` once more on top of `Formatter::e()`/`Formatter::sanitize()`, all guarded by `function_exists()` so only the first definition of each ever actually runs — but the dead second definition has different behavior than the live one, which is a trap for the next person reading the file top-to-bottom.
**Risk:** The first `sanitize()` (lines 132-136) delegates to `Formatter::sanitize()`, which does `htmlspecialchars(strip_tags(trim($input)), ...)` (`app/Helpers/Formatter.php:99-102`). The second, unreachable `sanitize()` (lines 225-229) is a literal reimplementation that is missing the `strip_tags()` step. If anyone ever reorders the file, removes the first block thinking it's redundant with the "real" Formatter-based one below, or copies the pattern elsewhere, the live sanitizer silently loses tag-stripping. Likewise `e()` (lines 231-235) duplicates `Formatter::e()` (`app/Helpers/Formatter.php:104-107`) exactly, for no structural reason.
**Root Cause:** The second "Helper functions (moved from php/config.php)" block (`bootstrap.php:168-235`) was merged in afterward without checking it against the first block already defined above it.
**Recommended Fix:** Delete the dead second `sanitize()` definition and the redundant `e()` global wrapper; keep call sites going through `Formatter::` directly, or keep exactly one global wrapper per helper.
**File Location:** `app/bootstrap.php:132-136` (live `sanitize()`), `app/bootstrap.php:225-229` (dead, divergent `sanitize()`), `app/bootstrap.php:231-235` (redundant `e()`).

---

## Finding 11 — Stale cron path in a code comment references a file that doesn't exist

**Severity:** Low
**Impact:** Operational/documentation drift — a maintainer reading the file header to (re)install the crontab would install a cron entry pointing at a nonexistent script.
**Risk:** `api/cron/process_reminders.php:4` documents its own crontab line as `0 * * * * /usr/bin/php /var/www/html/safari-zangu/php/process_reminders.php` — but there is no `php/process_reminders.php`; the file lives at `api/cron/process_reminders.php`. `find php -maxdepth 1 -type f` confirms the `php/` directory contains no top-level `.php` files at all (only the dead `php/PHPMailer/` tree from Finding 6).
**Root Cause:** The script was moved from an older `php/` location into `api/cron/` and the self-documenting comment was never updated to match.
**Recommended Fix:** Update the comment to the real path.
**File Location:** `api/cron/process_reminders.php:4`.

---

## Finding 12 — Service layer depends on Middleware layer (minor layering inversion)

**Severity:** Low
**Impact:** `App\Services\OwnershipScope` (business/data-scoping logic) imports and calls `App\Middleware\AuthMiddleware::isAgentRole()`. Conventionally middleware sits above services in the request-handling pipeline (middleware consults services, not the reverse), so this is a layering inversion, though a mild one since `AuthMiddleware::isAgentRole()` is just a session-flag read with no HTTP-specific behavior.
**Risk:** Low in practice today; worth flagging because it's the one place in `app/Services/` that reaches into `app/Middleware/` (confirmed — no other Service does this), so it's an inconsistency rather than an established pattern.
**Root Cause:** `isAgentRole()`/role-checking logic lives in `AuthMiddleware` rather than in a role/session-reading helper that both middleware and services could depend on equally.
**Recommended Fix:** Low priority; if ever touched, move the underlying session-role read into a small, dependency-free helper (e.g. `App\Helpers\Session` or similar) that both `AuthMiddleware` and `OwnershipScope` can depend on without either depending on the other.
**File Location:** `app/Services/OwnershipScope.php:4, 25`.

---

## Findings judged NOT to be issues (noted for completeness)

- **File size of `admin/quotation_new.php` (1,899 lines) and `admin/package_edit.php` (1,954 lines):** both split cleanly into three distinct, separable sections — PHP request/data logic (~280-420 lines), HTML template (~450-500 lines), inline JS (~950-1,070 lines) — per the project's established one-file-per-page pattern. The size itself is a consequence of Finding 1 (unextracted, duplicated JS) rather than a separate defect; extracting the JS per Finding 1's recommendation would cut both files to roughly half their current size without any other structural change.
- **No circular dependencies found** among `app/Services/*.php`, `app/Models/*.php`, and `app/Middleware/*.php` via namespace `use` statements — dependency graph is a clean DAG except for the single Service→Middleware edge noted in Finding 12.
- **No PHP ↔ PHP circular includes found** between `admin/`, `api/`, and `app/` — the one upward-reaching dependency (`app/Services/PdfRenderer.php` including `api/*_pdf.php`) is directional (Service → API, never the reverse) and is Finding 4, not a cycle.

---

## Architecture Score: 61 / 100

**Justification:** The codebase is a coherent, intentionally procedural system with a real (if thin) service layer, a working autoloader, consistent middleware for auth/CSRF, and — notably — an actual test suite with 79 passing tests including at least one duplication-drift guard, which is more rigor than most codebases at this scale attempt. That earns real credit over a "typical" unstructured procedural PHP app. The deductions are concrete and specific, not stylistic: a documented drift-detection strategy (Finding 1's test) covers roughly 5% of the duplicated surface it implies it covers, which is the kind of gap that erodes trust in the whole safety net; a core service inverts the dependency direction into the controller layer with a genuine unhandled-exception bug as a direct consequence (Finding 4); a model class has silently drifted out of sync with the production write path it's supposed to represent while its own unit test still reports green (Finding 8); and there are five entirely dead, still-authenticated admin/API endpoints and a 7,000-line dead vendored library sitting in the tree. None of these are exotic or speculative — each was confirmed by reading the actual code and tracing actual call sites. The system is maintainable by someone who already knows where the bodies are buried, but is not yet safe for a newcomer to extend by trusting the tests and the apparent service boundaries at face value.
