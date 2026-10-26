# Phase 4 — API Endpoint Security Audit
Safari Zangu Ventures booking system — `api/*.php` and `api/cron/*.php`
Audit type: read-only, static code review + non-mutating local curl checks against `http://localhost/safari-zangu`.
No application file was modified. No booking/customer/lead/payment record was created during this audit.

---

## 1. Endpoint Inventory

| Endpoint | Method | Auth | Description |
|---|---|---|---|
| `api/book.php` | POST | Public | Creates a direct booking, customer record, auto-quotation, PDFs, confirmation email |
| `api/contact.php` | POST | Public | Contact form → `contact_messages` + ack/admin emails |
| `api/custom_safari.php` | POST | Public | Custom safari request → `custom_safari_requests` + ack/admin emails |
| `api/convert_quotation.php` | POST | Admin (+ agent ownership check) | Converts an accepted quotation into a booking |
| `api/dashboard_stats.php` | GET | Admin session (raw `$_SESSION` check, not `AuthMiddleware`) | Live dashboard counters |
| `api/generate_caption.php` | POST | Admin (agent explicitly denied) | Calls Anthropic API server-side for Instagram captions |
| `api/check_booking.php` | GET/POST | Public, ref+travel_date | Legacy booking lookup, returns name/email/phone/financials |
| `api/record_payment.php` | POST | Public, ref+(date\|password) | Records a verified PayPal payment against a booking |
| `api/paypal_create_order.php` | POST | Public, ref+(date\|password) | Creates a PayPal order for the amount actually due |
| `api/track_lookup.php` | GET/POST | Public, ref+password | Track-Booking JSON lookup (password-gated, lockout) |
| `api/track_booking_pdf.php` | GET | Public, ref+password | Guest PDF download (booking confirmation) |
| `api/track_itinerary_pdf.php` | GET | Public, ref+password | Guest PDF download (itinerary) |
| `api/track_quotation_pdf.php` | GET | Public, ref+password | Guest PDF download (quotation/invoice) |
| `api/quote_view.php` | GET | Public, 48-hex token | Client-facing quotation view by opaque token |
| `api/booking_pdf.php` | GET | Admin (+ agent ownership) | Booking confirmation/invoice PDF |
| `api/booking_itinerary_pdf.php` | GET | Admin (+ agent ownership) | Booking itinerary PDF |
| `api/quotation_pdf.php` | GET | Admin (+ agent ownership) | Quotation/invoice PDF |
| `api/quotation_itinerary_pdf.php` | GET | Admin (+ agent ownership) | Quotation itinerary PDF |
| `api/itinerary_pdf.php` | GET | Admin | Itinerary template PDF |
| `api/manifest_pdf.php` | GET | Admin **(no agent-ownership check — see Finding 1)** | Daily departure manifest, all bookings for a date |
| `api/voucher_pdf.php` | GET | Admin (+ agent ownership) | Supplier-facing service voucher PDF |
| `api/booking_email.php` | POST | Admin (+ agent ownership) | Resends booking confirmation email |
| `api/voucher_email.php` | POST | Admin (+ agent ownership) | Emails service voucher to guest |
| `api/quotation_email.php` | POST | Admin (+ agent ownership) | Emails quotation to client |
| `api/review_submit.php` | POST | Public | Guest review submission |
| `api/set_hero.php` | POST | Admin (agent denied) | Sets homepage hero image |
| `api/upload_team_image.php` | POST | Admin (agent denied) | Uploads team/fleet/accommodation image |
| `api/vehicle_manage.php` | POST | Admin (agent denied) | Adds a fleet vehicle |
| `api/supplier_contract.php` | GET | Admin (agent denied) | Serves a supplier's uploaded contract PDF |
| `api/get_setting.php` | GET | **None — see Finding 2** | Returns any `settings` table value by key |
| `api/itinerary.php` | GET/POST | Admin session (raw check) | Itinerary list/get/quote-line-item builder |
| `api/analytics.php` | POST | Public, rate-limited | Front-end event tracking → `analytics_events` |
| `api/gallery/upload.php` | POST | Admin | Image gallery upload |
| `api/gallery/delete.php` | POST | Admin | Image gallery delete |
| `api/gallery/list.php` | GET | Admin | Image gallery listing |
| `api/cron/process_email_queue.php` | CLI/key | `CRON_SECRET` | Email queue retry processor |
| `api/cron/process_quotation_expiry.php` | CLI/key | `CRON_SECRET` | Expires stale quotations |
| `api/cron/process_reminders.php` | CLI/key | **Hardcoded string — see Finding 5** | Sends scheduled reminder emails |
| `api/cron/send_single_email.php` | CLI/key | `CRON_SECRET` | Fire-and-forget single email dispatch |

### Public, zero-authentication attack surface
`book.php`, `contact.php`, `custom_safari.php`, `check_booking.php`, `record_payment.php`, `paypal_create_order.php`, `track_lookup.php`, `track_booking_pdf.php`, `track_itinerary_pdf.php`, `track_quotation_pdf.php`, `quote_view.php`, `review_submit.php`, `analytics.php`, **`get_setting.php`** (unintentionally — see Finding 2). All of these are reasonable to be public by design *except* `get_setting.php`, which has no documented public use case and is not referenced anywhere else in the codebase.

---

## 2. Findings

### Finding 1 — `manifest_pdf.php` has no per-agent ownership scoping (bulk IDOR for the "agent" role)
**Severity:** High
**Impact:** An authenticated user with the restricted `agent` role (who is meant to see only their own clients' bookings, enforced on every other booking/quotation/voucher endpoint) can retrieve the **full daily departure manifest** — every guest booked on a given date, regardless of which agent sold it — including full name, phone, email, special requests, package name, accommodation level, and any assigned guide/vehicle. Trivial to enumerate across all dates.
**Risk:** Agents (who in this system's own threat model are commission-earning third parties restricted to their own book of business — see `App\Services\OwnershipScope`'s doc comment) can harvest every other agent's customer PII and itinerary data. This directly breaks the system's documented ownership model.
**Root cause:** Every sibling admin-facing document endpoint (`booking_pdf.php`, `booking_itinerary_pdf.php`, `quotation_pdf.php`, `quotation_itinerary_pdf.php`, `voucher_pdf.php`, `booking_email.php`, `voucher_email.php`, `quotation_email.php`) enforces:
```php
if (!defined('SZV_PDF_INTERNAL_RENDER') && \App\Services\OwnershipScope::isScoped()) {
    $__ref = strtoupper(trim($_GET['ref'] ?? $_POST['ref'] ?? ''));
    if (!\App\Services\OwnershipScope::ownsBooking($db, $__ref, OwnershipScope::currentAgentId())) {
        \App\Services\AccessGuard::denyPlain(...);
    }
}
```
`api/manifest_pdf.php` (lines 9–13) only has:
```php
if (!defined('SZV_PDF_INTERNAL_RENDER')) {
    AuthMiddleware::requireAdmin();
}
```
— no `isAgentRole()` / `OwnershipScope` check anywhere in the file. The only place that defends against an agent reaching this data is the *linking page*, `admin/operations_calendar.php`, which explicitly denies the whole page to agents (lines 5–9: *"Agents have no access to this section ... the nav hiding alone isn't enforcement, so block here too"*) — the same comment that was applied to the calendar page was never applied to the API endpoint it links to.
**Verified:** Confirmed via static review of `api/manifest_pdf.php` against every sibling PDF endpoint, and confirmed live that the endpoint only requires *some* admin session (`curl` unauthenticated → `302` redirect to login, same as every other admin endpoint — an agent session would pass straight through).
**Recommended fix:** Add the same `OwnershipScope::isScoped()` gate used elsewhere — either deny agents outright (consistent with `operations_calendar.php`), or filter the manifest query to only bookings the agent owns via `OwnershipScope::bookingsListSql()`.
**File Location:** `api/manifest_pdf.php:9-13` (missing check); compare `api/booking_pdf.php:12-19`, `api/voucher_pdf.php:14-22`, `api/quotation_pdf.php:15-23`.

---

### Finding 2 — `get_setting.php` is a public, unauthenticated settings-table oracle (confirmed bank account disclosure)
**Severity:** High
**Impact:** Confirmed live: an anonymous request discloses the company's real bank account number.
```
$ curl -s "http://localhost/safari-zangu/api/get_setting.php?key=bank_account_no"
{"success":true,"key":"bank_account_no","value":"0026017459"}
```
Any key present in the `settings` table is readable by anyone, with no authentication, no CSRF requirement (it's a GET), and no rate limiting. `admin/settings.php` writes (at minimum) `bank_name`, `bank_account_name`, `bank_account_no`, `bank_swift`, `wise_email`, `site_email`, `admin_email`, `whatsapp_number`, plus all business-rule constants, to this same table via a generic key/value `saveSettings()` helper — so *any* setting added to that table in the future (e.g. a notification webhook URL, an internal email list) is exposed by the same mechanism with zero code changes needed on the attacker's side.
**Risk:** Direct disclosure of business banking details (usable for invoice-fraud / BEC-style social engineering against the company's own clients, who are told elsewhere in the system — e.g. `check_booking.php` — to wire deposits to this exact account) plus an open-ended future risk: this endpoint has no allow-list, so it will silently expose any future sensitive setting without anyone noticing.
**Root cause:** `api/get_setting.php` has no `AuthMiddleware` call at all, and validates only that the `key` parameter matches `^[a-z0-9_]+$` — a format check, not a permission check:
```php
$key = trim($_GET['key'] ?? '');
if (!$key || !preg_match('/^[a-z0-9_]+$/', $key)) { ... }
...
$stmt = $db->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
```
Grep across the entire codebase (views, JS, admin) shows **no caller anywhere else references `get_setting.php`** — it is unused dead code left reachable on the live site.
**Recommended fix:** Either delete the endpoint (nothing in the codebase calls it), or require admin auth, or move to an explicit allow-list of genuinely public, non-sensitive keys (e.g. nothing financial) and deny everything else by default.
**File Location:** `api/get_setting.php:1-23`.

---

### Finding 3 — `book.php` has an unused, unauthenticated CSRF bypass on its only state-changing public write
**Severity:** Medium
**Impact:** `api/book.php`, which creates a real booking + customer + quotation + emails real confirmation documents to a client-supplied address, skips CSRF validation entirely when the client sends `payment_method=paypal_callback`:
```php
if (($_POST['payment_method'] ?? '') !== 'paypal_callback') {
    try {
        CsrfMiddleware::validateOrDie();
    } catch (Exception $e) { ... }
}
```
This string is attacker-controlled (`payment_method` is a plain POST field read later into `$data['payment_method']` with only `Formatter::sanitize()`, no allow-list). A scripted request that simply sets this one field skips the CSRF check with no corresponding verification that a PayPal transaction actually happened at this stage (`PayPalClient` verification only happens later, inside `record_payment.php`, a completely separate request/endpoint).
**Risk:** Lower than a typical CSRF bypass because the endpoint is already public and requires no authenticated session to exploit directly — but it removes the one friction mechanism (token possession, implying a same-origin page load) standing between a bare scripted POST and a fully processed booking, including a real email sent to any address the attacker supplies (phishing/spam vector: attacker controls `email`, `full_name`, and can trigger the system's own "Booking Confirmation" email, with a real tracking password, to a third party's inbox).
**Root cause:** Grep of the entire codebase shows **`'paypal_callback'` is referenced in exactly one place — this `if` condition itself** (`api/book.php:50`); no view, JS file, or other caller ever sends it. It is vestigial logic for a flow that does not exist in the current code, left live as a bypass condition.
**Recommended fix:** Remove the bypass entirely, or if a legitimate PayPal-approved booking path needs it, gate it on a verified PayPal order (e.g. require and validate `paypal_order_id` server-side via `PayPalClient` *before* skipping CSRF) rather than a bare client-asserted string.
**File Location:** `api/book.php:50-56`.

---

### Finding 4 — `record_payment.php` has no transactional/row-locking protection against concurrent duplicate-credit
**Severity:** Medium
**Impact:** Two near-simultaneous requests referencing the same booking (e.g. a double-submitted PayPal capture, or a replayed/duplicated client callback fired twice in quick succession) can both pass the "already recorded" idempotency check before either writes, because the check-then-act sequence is not inside a transaction or row lock:
```php
$alreadyPaid  = (float)($booking['amount_paid'] ?? 0);   // both requests read this BEFORE either writes
$newTotalPaid = round($alreadyPaid + $amountPaid, 2);
...
$pdo->prepare("UPDATE bookings SET payment_status=?, amount_paid=?, ... WHERE booking_ref=?")
    ->execute([$newStatus, $newTotalPaid, $orderId, $ref]);
```
The existing idempotency guards (`fully_paid` short-circuit, and the `paypal_order_id === $orderId` replay check a few lines above) only protect against a *second, later* request after the first has already committed — they do nothing for two requests racing in the same narrow window, since both read `amount_paid` before either commits its update.
**Risk:** Double-crediting `amount_paid` for one real PayPal payment, which can push a booking to `fully_paid` prematurely or inflate the recorded balance, and can also double-send the "fully paid" / "deposit receipt" invoice email (`InvoiceMailer`) for a single real payment.
**Root cause:** No `$pdo->beginTransaction()` / `SELECT ... FOR UPDATE` / unique-constraint-based guard around the read-modify-write sequence. (Contrast with `app/Services/PipelineSync.php`, which does use `beginTransaction()`/`rollBack()` elsewhere in the codebase — the pattern exists, it's just not applied here.)
**Recommended fix:** Wrap the booking read + amount_paid update in a transaction with `SELECT ... FOR UPDATE` on the booking row, or add a separate `payments` table with a unique constraint on `paypal_order_id` and derive `amount_paid` as a `SUM()` rather than an incrementally-mutated column.
**File Location:** `api/record_payment.php:53-119`.

---

### Finding 5 — Inconsistent cron secret: `process_reminders.php` uses a hardcoded string instead of `CRON_SECRET`
**Severity:** Medium (mitigated in this deployment by directory-level `.htaccess`, but a latent defense-in-depth gap)
**Impact:** Every other cron script follows the same pattern — compare `CRON_SECRET` from config via `hash_equals()`:
```php
// process_email_queue.php, process_quotation_expiry.php, send_single_email.php
$cronSecret = defined('CRON_SECRET') ? CRON_SECRET : '';
$hasKey = $cronSecret !== '' && isset($_GET['key']) && hash_equals($cronSecret, $_GET['key']);
```
`api/cron/process_reminders.php` instead hardcodes a literal secret directly in the PHP source, committed to version control:
```php
$hasKey = isset($_GET['key']) && hash_equals($_GET['key'], 'szv-cron-2026');
```
**Risk:** A secret committed to source control is not a secret — anyone with repository access (current or former contractors, a leaked backup/zip, a public fork) has it permanently and it cannot be rotated without a code change (whereas `CRON_SECRET` can be rotated via `.env`/config alone). It is also a weak, guessable value (company name + year).
**Mitigating factor — verified live:** `api/cron/.htaccess` currently blocks all direct web access to the entire `api/cron/` directory (`Order allow,deny` / `Deny from all`), and this was confirmed effective on this host:
```
$ curl -s -o /dev/null -w "HTTP:%{http_code}\n" http://localhost/safari-zangu/api/cron/process_reminders.php
HTTP:403
```
So today, the web vector is closed regardless of the key's strength — but this is a single point of failure (one misconfigured `AllowOverride`, Apache-to-Nginx migration, or a reverse proxy added in front that doesn't forward `.htaccess` semantics, and this becomes live-exploitable with a publicly-known key). This endpoint sends real customer reminder emails (deposit due, balance due, departure, post-trip, win-back) if ever reached.
**Root cause:** `api/cron/process_reminders.php:11` doesn't use the `CRON_SECRET` constant like its three siblings.
**Recommended fix:** Replace the hardcoded string with the same `CRON_SECRET`-based check used everywhere else, so the directory-level `.htaccess` is defense-in-depth rather than the only control.
**File Location:** `api/cron/process_reminders.php:10-12` vs. `api/cron/process_email_queue.php:18-21`, `api/cron/process_quotation_expiry.php:19-22`, `api/cron/send_single_email.php:13-16`.

---

### Finding 6 — `check_booking.php` exposes full PII on a weaker, unrated credential than the newer Track Booking flow
**Severity:** Medium
**Impact:** `api/check_booking.php` authorizes solely on `booking_ref` (format `SZV` + 6–20 alphanumeric, generated via `bin2hex(random_bytes(5))` — reasonably high entropy, 40 bits) **plus `travel_date`**, a value with very low entropy (effectively a few hundred realistic values per year, and often knowable or guessable by anyone with a rough idea of the guest's trip). On a match it returns full name, email, phone, package, and complete financial breakdown. This is the same booking-ownership proof the system explicitly moved away from for the newer `track_lookup.php`/`track.php` flow, which the code comments in that newer file describe as "a real one-time password... not just 'knows the ref and the travel date'" — implying this was recognized internally as the weaker, legacy model. `check_booking.php` is rate-limited only generically (`RateLimiter::enforceOrDie('track', 15, 3600)` — 15/hour per IP), shared with `track_lookup.php`'s key, so an IP has 15 attempts/hour against *either* credential, not a per-target lockout like the newer flow's dedicated `/tmp/szv_ratelimit/track_<ip-hash>.json` 3-attempt lockout.
**Risk:** Given the booking ref's randomness, this isn't trivially brute-forceable in isolation — but if a ref is ever disclosed in a less-protected channel (e.g. forwarded in a non-HTTPS email, pasted in a support chat, or simply observed by a second person on a shared device that received the confirmation email, which also contains the travel date), the combination requires no password at all, unlike every newer flow in this codebase.
**Root cause:** `api/check_booking.php` was not migrated to `App\Services\BookingTracking::verify()` when the rest of the Track Booking surface was hardened (see the doc comments in `track_lookup.php` itself, which describe exactly this upgrade).
**Recommended fix:** Either retire `check_booking.php` in favor of `track_lookup.php` (if nothing still calls it), or require the same `tracking_password_hash` check.
**File Location:** `api/check_booking.php:12-32`.

---

### Finding 7 — Inconsistent error handling: raw exception messages echoed to admin-authenticated clients
**Severity:** Low
**Impact:** Several admin-gated endpoints echo `$e->getMessage()` directly into the JSON/redirect response instead of a generic message, leaking internal details (DB schema fragments, third-party API error text) to any authenticated admin/agent/staff session:
```php
// api/vehicle_manage.php:37
vrespond(['success'=>false,'message'=>$e->getMessage()]);

// api/convert_quotation.php:199
qrespond(['success'=>false,'message'=>'Error: '.$e->getMessage()]);

// api/generate_caption.php:144
caption_respond(['success' => false, 'message' => 'Could not generate caption: ' . $e->getMessage()]);
```
Compare the hardened pattern used almost everywhere else in this same codebase (e.g. `api/book.php:206-209`, `api/contact.php:85-89`, `api/record_payment.php:124-127`), which logs `$e->getMessage()` via `error_log()` and returns a generic client-facing message.
**Risk:** Low — all three are behind `AuthMiddleware::requireAdmin()`, so this is internal information disclosure to already-trusted (if possibly lower-privileged, e.g. agent-adjacent) sessions, not to the public. Still a useful signal for a would-be attacker who has obtained lower-tier admin credentials and is probing for a path to privilege escalation or a SQL injection confirmation oracle.
**Recommended fix:** Standardize on `error_log()` + generic client message, as already done on the majority of endpoints in this codebase.
**File Location:** `api/vehicle_manage.php:37`, `api/convert_quotation.php:199`, `api/generate_caption.php:144`.

---

### Finding 8 — Response shape is inconsistent across the API surface
**Severity:** Low
**Impact:** Most JSON endpoints use `{success: bool, message: string, ...}`, but several deviate:
- `api/dashboard_stats.php` returns a flat data object with no `success` key at all on success, and `{error: "..."}` (not `message`) on the auth failure path.
- `api/itinerary.php` wraps successful payloads as `{success, data}` while every booking/quotation endpoint uses top-level fields instead of a `data` envelope.
- `api/analytics.php` returns bare `{success: true}` / `{success: false}` with no `message` at all, while virtually everything else always includes one.
- Several PDF/HTML-rendering endpoints (`booking_pdf.php`, `voucher_pdf.php`, `itinerary_pdf.php`, `manifest_pdf.php`) return plain `die('...')` text on error with no structure at all — correct for an HTML-rendering endpoint, but worth noting as a second, unversioned "error contract" that any JS caller treating all `api/*.php` failures uniformly would mishandle.
**Risk:** Purely a maintainability/robustness concern — a frontend change that assumes `{success, message}` universally will silently mishandle `dashboard_stats.php`'s or `analytics.php`'s shape. Not independently exploitable.
**Recommended fix:** Converge on one envelope (`{success, message, data?}`) for all JSON endpoints.
**File Location:** `api/dashboard_stats.php:43-44`, `api/itinerary.php` (multiple `irespond()` calls), `api/analytics.php:43,48`.

---

### Finding 9 — No API versioning strategy
**Severity:** Low (noted only because it is a near-term real risk here, not boilerplate)
**Impact:** Every `api/*.php` file is a flat, unversioned script directly exposed at a stable URL, and is called from multiple places by absolute path (`admin/*.php`, `views/*.php`, other `api/*.php` files via `SZV_PDF_INTERNAL_RENDER` self-includes, and presumably front-end JS). Several files already show in-place behavioral evolution via comments ("previously sent synchronously... now queues", "previously this read deposit_amount... now writes amount_paid") — i.e., the response shape and side effects of a given endpoint have already changed multiple times in place, under the same URL, with no caller-visible signal.
**Risk:** This specifically matters here because: (a) `api/book.php`/`api/convert_quotation.php` are called from more than one surface (public form + admin "convert quotation" modal share overlapping logic paths) that must stay in lock-step with any payload change, and (b) the `SZV_PDF_INTERNAL_RENDER` self-include pattern (`track_booking_pdf.php` → `booking_pdf.php`, `quote_view.php`/`track_quotation_pdf.php` → `quotation_pdf.php`) means a change to one endpoint's expected `$_GET` shape silently changes behavior for every internal caller too, with no explicit contract enforced anywhere.
**Recommended fix:** Not urgent enough to block anything, but worth a lightweight convention going forward (e.g. a `CHANGELOG` comment block per endpoint, already informally present in some files) rather than a true `/v1/` versioning scheme, given this is a single-consumer internal system rather than a public API product.
**File Location:** N/A (systemic).

---

## 3. What was verified as correctly implemented

To avoid over-flagging, the following were specifically checked and found sound:

- **PayPal amount integrity**: `paypal_create_order.php` computes the amount server-side from the booking row only (never from client input); `record_payment.php` independently re-verifies the completed order against PayPal's own API (`PayPalClient::verifyCompletedOrder`) rather than trusting any client-submitted amount or status.
- **PayPal replay protection**: a single-field `paypal_order_id === $orderId` idempotency check correctly treats a resubmitted *same* order as a no-op (race condition aside — Finding 4).
- **CSRF**: enforced via `hash_equals()` against a per-session random token on essentially every state-changing endpoint except the one bypass in Finding 3.
- **Rate limiting**: present and IP-keyed (via `REMOTE_ADDR` only, explicitly never `X-Forwarded-For`) on every public write endpoint (`book`, `contact`, `custom_safari`, `review_submit`, `record_payment`, `paypal_create_order`, `analytics`, `quote_view`) and on the password-bearing `track_*` family (separate, stricter lockout mechanism with per-IP persistent attempt counters).
- **File upload hardening**: `gallery/upload.php` and `upload_team_image.php` both sniff real MIME type via `finfo` (not filename/extension), cap size, generate server-side filenames, and verify the resolved `realpath()` stays inside the intended asset root before writing.
- **Admin-only PDF/email endpoints**: all correctly gate on `AuthMiddleware::requireAdmin()`, and the "internal render" bypass (`SZV_PDF_INTERNAL_RENDER`) is only ever `define()`'d by trusted server-side code (the `track_*_pdf.php` and `quote_view.php` files), never from request input — verified by grep across the codebase.
- **Agent ownership scoping**: correctly enforced on `booking_pdf.php`, `booking_itinerary_pdf.php`, `quotation_pdf.php`, `quotation_itinerary_pdf.php`, `voucher_pdf.php`, `booking_email.php`, `voucher_email.php`, `quotation_email.php`, `convert_quotation.php`, and outright denied (rather than scoped) on `generate_caption.php`, `set_hero.php`, `upload_team_image.php`, `vehicle_manage.php`, `supplier_contract.php` — the one documented exception is `manifest_pdf.php` (Finding 1).
- **Quotation public view token**: `quote_view.php` uses a 48-hex-char (192-bit) random token, never the numeric id, with `preg_match` format validation and its own rate limit.
- **SQL injection**: all endpoints reviewed use parameterized PDO queries; the one interpolated value found (`manifest_pdf.php`'s `$db->quote($date)`) is itself validated against a strict `^\d{4}-\d{2}-\d{2}$` regex before use, and is passed through the driver's own `quote()` rather than raw concatenation.
- **Output escaping**: despite `api/analytics.php` storing `event_name`/`page_url`/`referrer` with no sanitization at write time, `admin/analytics.php` consistently `htmlspecialchars()`s every one of these fields at render time, so this does not present a stored-XSS path today (though input-side validation would still be good defense-in-depth).

---

## 4. API Score: 64 / 100

**Justification:**

The transactional core — booking creation, PayPal payment verification, CSRF, and rate limiting on every public write — is implemented with real care, including several comments showing deliberate, security-conscious fixes to past bugs (VAT/booking math, missing attachments, the Track Booking password upgrade). That raises the floor significantly above a typical ad-hoc PHP booking system.

What pulls the score down to the mid-60s is a cluster of findings that are small individually but point to the same systemic gap: **security controls applied inconsistently across near-identical sibling endpoints**, rather than missing entirely:
- One of eight nearly-identical admin PDF/email endpoints (`manifest_pdf.php`) is missing the ownership check every sibling has (Finding 1) — the exact "forgot one file" failure mode the codebase's own comments elsewhere warn against.
- One of four cron scripts uses a hardcoded secret instead of the shared `CRON_SECRET` pattern used by the other three (Finding 5).
- One of the system's two public endpoints that mutate real data has a silent, apparently-dead CSRF bypass condition that nothing else in the codebase exercises (Finding 3).
- One genuinely unauthenticated endpoint with no callers anywhere in the codebase (`get_setting.php`) sits live on the public internet and was confirmed, live, to disclose the company's real bank account number (Finding 2) — the single highest-impact, lowest-effort-to-fix finding in this audit.

None of these are novel attack classes requiring deep exploitation — every one is a straightforward "this file didn't get the same treatment as its siblings" gap, which is good news for remediation cost (each fix is a small, mechanical change copying an existing, already-correct pattern from elsewhere in the same codebase) but still represents real, currently-live exposure (confirmed bank detail disclosure, confirmed bulk-PII access path for the agent role) rather than theoretical risk.
