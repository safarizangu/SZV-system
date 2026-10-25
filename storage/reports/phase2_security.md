# Phase 2 — Security Audit (OWASP Top 10)
Safari Zangu Ventures admin system — PHP 8.3 procedural codebase at `/var/www/html/safari-zangu`.

**Audit type:** Read-only. Static code review of `admin/*.php`, `agent-portal/*.php`, `api/*.php`, `views/*.php`, `public/*.php`, `app/**`, plus non-mutating local `curl` probes against `http://localhost/safari-zangu` (GET requests and reads of fake/nonexistent references only). No application file was modified. No booking, customer, lead, payment, or settings record was created, changed, or deleted. No destructive SQL was run against the live `safari_zangu` database — DB credentials in `.env` were deliberately not read (blocked by the environment's own permission policy; not needed for this review, since every finding below is demonstrated either by source inspection or by a safe, unauthenticated read).

**Cross-reference note:** `reports/phase4_api.md` (an earlier, API-focused pass) already documents several of the same root causes found independently here from the OWASP-category angle (`manifest_pdf.php` IDOR, `get_setting.php` disclosure, `book.php` CSRF bypass, `check_booking.php` weak credential). Where this report reaches the same endpoint, it says so explicitly and adds only what phase4 didn't cover, rather than repeating the full writeup. Everything else below — the admin/agent-portal CSRF sweep, the `public/book.php?quote_id=` IDOR, the `admin/users.php` privilege escalation, session/auth mechanics, file upload validation, security headers, and the SQL-injection sweep — is new in this pass.

---

## Summary

| # | Finding | Severity | Status |
|---|---|---|---|
| 1 | `api/check_booking.php` — unauthenticated PII + financial disclosure via ref+date (no password) | **High** | Confirmed live |
| 2 | `public/book.php?quote_id=` — sequential-ID IDOR discloses other clients' quotation PII | **High** | Confirmed by code + routing test |
| 3 | `admin/users.php` — "admin" role can self-escalate to "superadmin" | **Medium** | Confirmed by code |
| 4 | `api/get_setting.php` — unauthenticated settings oracle (bank account disclosure) | **High** | Confirmed live (corroborates phase4 Finding 2) |
| 5 | `api/manifest_pdf.php` — no agent ownership scoping, cross-agent PII leak | **High** | Confirmed by code (corroborates phase4 Finding 1) |
| 6 | `api/book.php` — CSRF bypass via attacker-controlled `payment_method=paypal_callback` | **Medium** | Confirmed by code (corroborates phase4 Finding 3) |
| 7 | Weaker "ref + date/ID" ownership proof used in parallel with the password model | **Medium** | Confirmed by code (`confirmation.php`, `paypal_create_order.php`, `record_payment.php`) |
| 8 | Login brute-force lockout is IP-based, not account-based | **Low** | Confirmed by code |
| 9 | `dashboard_stats.php` / `itinerary.php` / `set_hero.php` bypass the 30-min inactivity timeout | **Low** | Confirmed by code |
| 10 | Track Booking password transmitted via GET query string | **Low** | Confirmed by code |
| 11 | CSP allows `'unsafe-inline'` for scripts and styles | **Low / Informational** | Confirmed live |
| — | SQL injection | **None found** | Verified safe |
| — | CSRF coverage (admin/agent-portal) | **No gaps found** (api/book.php gap already counted as #6) | Verified |
| — | File upload validation | **No issues found** | Verified safe |
| — | Session fixation | **Not present** | Verified safe (`session_regenerate_id(true)` on login) |
| — | Open redirect | **Not present** | Verified safe (whitelisted) |
| — | Hardcoded secrets | **None found** | Verified safe |
| — | `.env` exposure | **Not exposed** | Verified (403 live, gitignored) |

---

## Finding 1 — `api/check_booking.php`: full customer PII + financial data disclosed with no password, contradicting the app's own password-gated design

**Severity:** High
**Status:** Confirmed live (safe probe, fabricated ref, no real data retrieved):
```
$ curl -s "http://localhost/safari-zangu/api/check_booking.php?ref=SZV0000000000&date=2026-01-01"
{"success":false,"message":"No booking found. Please check your reference and date."}
HTTP 200, reachable with zero authentication.
```

**Impact:** For any valid `booking_ref` + `travel_date` pair, this unauthenticated public endpoint returns the guest's full name, email, phone number, package, accommodation level, exact payment status, full VAT/service-charge cost breakdown, and literal bank-transfer payment instructions (bank name, account number, SWIFT) — see `api/check_booking.php:70-109`. No password is required.

This directly contradicts the security model the application itself documents elsewhere. `app/Services/BookingTracking.php`'s class doc explicitly frames the Track Booking password as the real proof of ownership ("Hashed at rest like every other credential in this app"), and `api/track_booking_pdf.php:82-85` states in its own comment: *"Safe here because we've already independently verified ref+date ownership above, matching the exact same proof-of-ownership track_lookup.php requires"* — i.e. the newer flow (`track_lookup.php` / `track.php`) was deliberately built to require ref **+ password**, specifically because ref+date alone was considered insufficient. `check_booking.php` was never migrated to this model and still uses the older, weaker one.

**Risk:** `booking_ref` itself is high-entropy (`'SZV' . bin2hex(random_bytes(5))`, ~40 bits — not practically brute-forceable). The real risk is that `check_booking.php` removes the *second* factor (the password) that the rest of the app relies on to protect this exact data. Any channel through which the ref alone leaks — a forwarded confirmation email, a screenshot shared on social media/WhatsApp, a browser-history sync on a shared device, a ref pasted into a public support forum, server/proxy access logs — is enough to pull a stranger's full PII and payment status, where every *other* document-serving endpoint in the app (`track_booking_pdf.php`, `track_quotation_pdf.php`, `track_itinerary_pdf.php`) would have additionally required the one-time password.
Rate limiting is also shared with `track_lookup.php`'s bucket (`RateLimiter::enforceOrDie('track', 15, 3600)` — 15/hour per IP) rather than the newer flow's dedicated 3-attempt/15-minute per-IP lockout (`api/track_lookup.php` / `api/track_booking_pdf.php`), so this endpoint gets 5x more guesses per hour than the hardened flow intends.

**Root cause:** `api/check_booking.php:27-28` calls `Booking::findByRefAndDate($ref, $date)` (`app/Models/Booking.php:52-59`, a plain `WHERE booking_ref = ? AND travel_date = ?`) instead of `App\Services\BookingTracking::verify($db, $ref, $password)`. No code anywhere else in the codebase calls `check_booking.php` (confirmed by `grep` — the only reference found is a one-line comment in `api/booking_pdf.php:44`), suggesting it is orphaned/legacy code left live rather than an intentionally maintained second entry point.

**Recommended fix:** Retire this endpoint (delete it, since nothing calls it), or if it must stay, require the same `BookingTracking::verify()` password check every sibling tracking endpoint uses, and move it onto the dedicated 3-attempt lockout bucket instead of the shared 15/hour one.

**File Location:** `api/check_booking.php:1-109` (the vulnerable query is `api/check_booking.php:27-28`; data exposure is `api/check_booking.php:70-109`); compare `app/Services/BookingTracking.php:36-45` and `api/track_booking_pdf.php:61-68` for the correct pattern.

---

## Finding 2 — `public/book.php?quote_id=` (routed as `/book.php?quote_id=`): sequential integer ID IDOR discloses other clients' quotation PII and pricing

**Severity:** High
**Status:** Confirmed by source review; endpoint reachability confirmed live (`curl -o /dev/null -w "%{http_code}" "http://localhost/safari-zangu/book.php?quote_id=1"` → `200`, routed via root `.htaccess`'s `^([a-zA-Z0-9_-]+\.php)$ → public/$1` rule). No real quotation data was retrieved or disclosed during this test — the local database currently has no quotation in `('accepted','sent')` status at the low IDs tried, so the probe returned the normal empty booking form; the vulnerability is demonstrated by the unguarded query itself, not by exfiltrating real customer data.

**Impact:** `public/book.php` accepts a raw, client-supplied, auto-increment integer (`$_GET['quote_id']`) and uses it directly to fetch and display a quotation record with **no ownership, token, or session check whatsoever** — only a status filter:

```php
// public/book.php:12-18
if ($qId = (int)($_GET['quote_id'] ?? 0)) {
    $qStmt = $qPdo->prepare("SELECT * FROM quotations WHERE id=? AND status IN ('accepted','sent') LIMIT 1");
    $qStmt->execute([$qId]);
    $quoteData = $qStmt->fetch() ?: null;
}
```

The result is echoed straight into the public booking form (`views/book.php:29-196`), pre-filling (and thus disclosing, via rendered `value="..."` attributes any visitor can view-source) the client's **full name, email address, phone number, country, travel dates, party size, and the quotation's grand total price** — for *any* quotation in `sent` or `accepted` status, system-wide, not just the one the link was meant for.

**Risk:** `id` is a plain auto-increment primary key. An attacker needs no credential at all — just iterate `?quote_id=1`, `2`, `3`, … to enumerate every client who has ever had a quotation emailed to them with status `sent` or `accepted` (which, per `admin/quotation_new.php`, is the normal lifecycle state for "quotation has been sent to the client and is awaiting their decision" — i.e. most real quotations spend meaningful time in this state). This is a direct PII harvesting vector (names, emails, phone numbers, nationalities, trip values) with no rate limiting on this parameter and no logging/alerting that would catch sequential scraping.

**Root cause:** The link is intended to be followed only from a one-time context (e.g. a "Complete your booking" email/link sent to the client, or `admin/quotation_new.php?id=` for staff), but the public endpoint that consumes it trusts the bare numeric ID as if it were a capability token. There is no secondary secret (e.g. a random token column, or requiring the request to also match `client_email`) binding the link to the one person it was sent to.

**Recommended fix:** Either (a) stop passing the raw `id` and instead generate/require a random, unguessable, single-use token per quotation (the same pattern already used correctly by `api/quote_view.php`'s "48-hex token" design, per `reports/phase4_api.md`'s endpoint inventory), or (b) require the request to also supply and match `client_email` before returning any quotation fields, or (c) at minimum apply `RateLimiter` to this parameter to slow down enumeration while a real fix is built.

**File Location:** `public/book.php:12-18`; consumed/rendered at `views/book.php:29-196` (notably lines 31-34, 89, 101, 117, 127, 168, 179, 196).

---

## Finding 3 — `admin/users.php`: any "admin" role account can self-promote (or promote anyone) to "superadmin", bypassing per-account restrictions

**Severity:** Medium
**Status:** Confirmed by source review (not exploited against the live system — doing so would create/modify a real `admin_users` row, which this audit's rules of engagement exclude).

**Impact:** `admin/users.php` gates the whole page on `AuthMiddleware::isAdminRole()`, which is true for **both** `admin` and `superadmin` (`app/Middleware/AuthMiddleware.php:58-61`):
```php
public static function isAdminRole(): bool {
    return in_array($_SESSION['admin_role'] ?? 'agent', ['admin', 'superadmin'], true);
}
```
But the save handler itself places no additional restriction on which `role` value a plain `admin` session is allowed to write:
```php
// admin/users.php:23 (role read straight from POST, no further guard)
$role = $_POST['role'] ?? 'admin';
...
// admin/users.php:46-49 — written for ANY id, including $_SESSION['admin_user_id'] itself
$db->prepare("UPDATE admin_users SET username=?,full_name=?,email=?,role=?, ... WHERE id=?")
   ->execute([$username,$fullName,$email,$role, ..., $id]);
```
The role `<select>` on the edit form (`admin/users.php:211-216`) unconditionally lists `superadmin` as a choosable option regardless of the acting user's own role. A logged-in `admin` (not `superadmin`) can therefore submit the edit form for their own account (or anyone else's) with `role=superadmin` and immediately have it applied.

**Risk:** This is a genuine vertical privilege-escalation path, not merely cosmetic, because `superadmin` is treated specially elsewhere and bypasses per-account restrictions that a true superadmin may have deliberately placed on that user:
```php
// app/Middleware/AuthMiddleware.php:86-97
public static function canChangeStatus(): bool {
    if (($_SESSION['admin_role'] ?? '') === 'superadmin') return true;   // bypasses the stored flag entirely
    return ($_SESSION['can_change_status'] ?? 1) == 1;
}
public static function canDeleteRecords(): bool {
    if (($_SESSION['admin_role'] ?? '') === 'superadmin') return true;
    return ($_SESSION['can_delete_records'] ?? 1) == 1;
}
```
A restricted `admin` account (e.g. one a superadmin has deliberately set `can_delete_records=0` on) can use this exact page to set their own `role` to `superadmin` and thereby erase that restriction unilaterally, without ever needing the original superadmin's cooperation or knowledge.

**Root cause:** No check comparing the acting session's own role against the `role` value being written — the page's single `isAdminRole()` gate treats `admin` and `superadmin` as equally trusted to manage accounts/roles, but the role hierarchy itself (via `canChangeStatus`/`canDeleteRecords`'s special-casing) assumes `superadmin` is strictly more trusted than `admin`.

**Recommended fix:** Require `($_SESSION['admin_role'] ?? '') === 'superadmin'` specifically before allowing `role=superadmin` to be written (either when editing self or any other row), and/or explicitly forbid a non-superadmin from editing their own `role`/`can_change_status`/`can_delete_records` fields at all — only a different, higher-privileged account should be able to change those.

**File Location:** `admin/users.php:9` (page gate), `:16-61` (save handler, no role-escalation guard), `:211-216` (form exposes `superadmin` option unconditionally); the privilege model being bypassed is `app/Middleware/AuthMiddleware.php:86-97`.

---

## Finding 4 — `api/get_setting.php`: unauthenticated settings-table oracle, confirmed to disclose the company's real bank account number

**Severity:** High
**Status:** Confirmed live independently in this pass (corroborates `reports/phase4_api.md` Finding 2, found via the same method):
```
$ curl -s "http://localhost/safari-zangu/api/get_setting.php?key=bank_account_no"
{"success":true,"key":"bank_account_no","value":"0026017459"}
```

**Impact:** No `AuthMiddleware` call exists anywhere in the file. The only check is a format regex on the key name, not a permission check:
```php
// api/get_setting.php:5-9
$key = trim($_GET['key'] ?? '');
if (!$key || !preg_match('/^[a-z0-9_]+$/', $key)) { ... }
...
$stmt = $db->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
```
`admin/settings.php`'s `saveSettings()` helper writes an open-ended set of keys to this same generic `settings` table — currently including `bank_name`, `bank_account_name`, `bank_account_no`, `bank_swift`, `wise_email`, `site_email`, `admin_email`, `whatsapp_number`, plus VAT/deposit/currency business-rule constants, and `emergency_contacts` (JSON). Any one of them is readable by an anonymous visitor with no rate limiting on this endpoint specifically.

**Risk:** Direct disclosure of real company banking details that the system itself tells guests, elsewhere (`api/check_booking.php`'s `payment_instructions`), to wire real deposit money to — i.e. this is exactly the kind of detail invoice-fraud/BEC social-engineering attacks against the company's own clients would want, since it lets an attacker independently confirm the real account number a phishing email claims to be. It's also an open-ended future risk: because the allow-list is "any snake_case key," any more sensitive value added to `settings` later (an API credential someone mistakenly stores there, a webhook URL, an internal distribution list) is exposed by the exact same request with no code change needed on the attacker's side.

**Root cause:** No auth check; no allow-list of genuinely-public key names; no caller anywhere else in the codebase was found referencing this endpoint (`grep` across `views/`, `admin/`, JS — no hits), consistent with phase4's conclusion that this is dead/unused code left reachable.

**Recommended fix:** Delete the endpoint if truly unused, or gate it behind `AuthMiddleware::requireAdmin()`, or replace the open regex with an explicit allow-list of non-sensitive keys (e.g. nothing containing `bank`, `account`, `swift`, `email`, `contact`) and deny by default.

**File Location:** `api/get_setting.php:1-23`.

---

## Finding 5 — `api/manifest_pdf.php`: missing agent-ownership scoping, cross-agent customer PII leak

**Severity:** High
**Status:** Confirmed by source review in this pass (corroborates `reports/phase4_api.md` Finding 1).

**Impact:** Every sibling document-serving endpoint that can be reached by an `agent`-role session enforces `OwnershipScope` before returning data:
```php
// e.g. api/booking_pdf.php, api/voucher_pdf.php, api/quotation_pdf.php (pattern repeated in each)
if (!defined('SZV_PDF_INTERNAL_RENDER') && \App\Services\OwnershipScope::isScoped()) {
    $__ref = strtoupper(trim($_GET['ref'] ?? $_POST['ref'] ?? ''));
    if (!\App\Services\OwnershipScope::ownsBooking($db, $__ref, OwnershipScope::currentAgentId())) {
        \App\Services\AccessGuard::denyPlain(...);
    }
}
```
`api/manifest_pdf.php:11-13` only has:
```php
if (!defined('SZV_PDF_INTERNAL_RENDER')) {
    AuthMiddleware::requireAdmin();
}
```
— `requireAdmin()` accepts any authenticated session, including `agent`; there is no `isAgentRole()` / `OwnershipScope` check anywhere else in the file. The query (`api/manifest_pdf.php:21-26`) pulls every booking for a given date with no agent filter, returning full name, phone, email, special requests, and assigned staff/vehicle for every guest departing that day — not just the requesting agent's own clients.

**Risk:** Directly breaks the application's own documented ownership model (`App\Services\OwnershipScope`'s class doc: *"Staff, admin and superadmin are never scoped here... only agent gets per-record ownership filtering"*). An agent account — explicitly described in that same doc as "the one role restricted to its own records" — can harvest every other agent's customers' PII via this one endpoint, trivially, by enumerating `?date=YYYY-MM-DD`. The linking page (`admin/operations_calendar.php`) does deny agents, but that UI-level denial was never mirrored onto the API endpoint itself, so an agent who already knows (or guesses) the URL pattern reaches it directly.

**Root cause:** The `OwnershipScope` gate present on every comparable endpoint was simply not added to this one.

**Recommended fix:** Add the same `isAgentRole()`/`OwnershipScope` check used by the sibling endpoints — either deny agents outright (consistent with `operations_calendar.php`), or filter the manifest's booking list to the agent's own bookings via `OwnershipScope::bookingsListSql()`.

**File Location:** `api/manifest_pdf.php:9-13` (missing check), `:21-26` (unscoped query); compare `api/booking_pdf.php:12-19`, `api/voucher_pdf.php:14-22`.

---

## Finding 6 — `api/book.php`: CSRF check is bypassed by a client-controlled field, on the one state-changing public endpoint that sends real email to an attacker-chosen address

**Severity:** Medium
**Status:** Confirmed by source review in this pass (corroborates `reports/phase4_api.md` Finding 3). Not exploited live — doing so would create a real booking/customer row and send a real email, which this audit's scope excludes.

**Impact:**
```php
// api/book.php:48-53
if (($_POST['payment_method'] ?? '') !== 'paypal_callback') {
    try {
        CsrfMiddleware::validateOrDie();
    } catch (Exception $e) { ... }
}
```
`payment_method` is read later into `$data['payment_method']` via only `Formatter::sanitize()` — no allow-list constrains it before this check runs. A bare scripted `POST` that simply includes `payment_method=paypal_callback` (with no real PayPal transaction behind it; `PayPalClient` verification only happens later and separately, inside `record_payment.php`) skips the CSRF check entirely.

**Risk:** Lower than a typical CSRF bypass in one sense (the endpoint is public and needs no session to reach directly anyway), but it removes the one piece of friction — proof the request originated from a same-origin page load — standing between an anonymous scripted request and a fully processed booking: a real row in `bookings`, a real `customers` record, and a real "Booking Confirmation" email (with a genuine Track Booking password inside it) sent to any address the attacker supplies. That makes this endpoint usable as a mail-relay/phishing primitive (sending a legitimate-looking, system-generated email from the real domain to a third party), independent of any CSRF-victim scenario.

**Root cause:** `grep` across the codebase shows the literal string `'paypal_callback'` appears in exactly one place — this `if` condition itself (`api/book.php:48`). No view, JS file, or other caller sends it; it is vestigial logic for a flow that does not exist in the current code, left live as an attacker-reachable bypass condition.

**Recommended fix:** Remove the bypass, or if a legitimate pre-CSRF-token PayPal callback path is genuinely needed, gate it on server-side verification of a real `paypal_order_id` via `PayPalClient` *before* skipping the CSRF check, not on a bare client-asserted string.

**File Location:** `api/book.php:48-53`; the field is sanitized (not allow-listed) at `api/book.php:82`.

---

## Finding 7 — The "ref + date" ownership proof is used in three more places beyond Finding 1, as a lower-assurance alternative running in parallel with the password model

**Severity:** Medium
**Status:** Confirmed by source review.

**Impact:** Beyond `check_booking.php` (Finding 1), the same weaker pattern — proving booking ownership with `booking_ref` + `travel_date` only, no password — is accepted as a valid alternative to the password in:
- `views/confirmation.php:3-23` — the immediate post-booking success page. Fetches and displays the full booking (name, email, cost breakdown, payment status) by `ref`+`date` alone, no password, no expiry, permanently revisitable by anyone who has the URL.
- `api/paypal_create_order.php:39-59` and `api/record_payment.php:38-62` — both explicitly accept *either* the password *or* `ref`+`travel_date` (`if ($password !== '') { ... BookingTracking::verify ... } else { ... WHERE booking_ref = ? AND travel_date = ? ... }`), by design, per their own doc comments ("confirmation.php ... still proves ownership with travel_date; track.php ... now proves it with the Track Booking password instead").

**Risk:** Materially lower than Finding 1 for these specific three, because:
- `confirmation.php` is a conventional "thank you" page pattern most booking systems use this way, and the amount a PayPal order can be created for is always server-computed from the booking row (`api/paypal_create_order.php:71-75`), so a stranger who reaches this page via a known ref+date cannot inflate/discount what they'd be paying — at worst they could pay off *someone else's* balance (low-harm) or create harmless duplicate PayPal order attempts (rate-limited to 10/hour).
- `record_payment.php` independently re-verifies the PayPal capture against PayPal's own API before trusting any amount (`$amountPaid = PayPalClient::verifyCompletedOrder($orderId)`), so this path cannot be used to falsify a payment.

The residual risk is PII disclosure on `confirmation.php` specifically (full name, email, cost breakdown, payment status — visible to anyone with the ref+date, same leakage channels as Finding 1) and the general inconsistency of having two ownership models live side-by-side, which widens the overall attack surface and is easy to forget when auditing or extending the code later.

**Root cause:** `travel_date` was the original (pre-`BookingTracking`) ownership proof across the whole guest-facing booking flow; when the password model was introduced for `track.php`, it was added everywhere reachable from a *second* visit, but the *first*-visit page (`confirmation.php`) and the two payment endpoints kept the old method as a same-session convenience ("the guest just typed this exact date into the form a moment ago") that was never revisited for a later, different-session visitor who merely has the URL.

**Recommended fix:** For `confirmation.php`: treat the flashed one-time session value (`$_SESSION['_track_pwd_'.$ref]`, already present for showing the password itself) as the authority for *this* visit rather than re-deriving ownership from the URL every time; for a reload or different-session access, fall back to requiring the password like `track.php` does. Lower priority than Findings 1, 2, 4, 5.

**File Location:** `views/confirmation.php:3-23`; `api/paypal_create_order.php:39-59`; `api/record_payment.php:38-62`.

---

## Finding 8 — Login lockout is IP-based, not account-based, on both `admin/login.php` and `agent-portal/login.php`

**Severity:** Low
**Status:** Confirmed by code review.

**Impact:** Both login pages lock out after 5 failed attempts per **source IP** within 15 minutes (`admin/login.php:19-37`, `agent-portal/login.php:19-37`), keyed by `hash('sha256', $ip)`. There is no secondary, account-keyed counter.

**Risk:** A distributed attacker (e.g. a small botnet, or any attacker rotating through a handful of proxies/VPN exits) can bypass the lockout entirely and brute-force a single known username's password at low-and-slow volume — 5 attempts from each of N source IPs, with no cross-IP correlation at all. This is mitigated significantly by bcrypt cost-12 hashing (`admin/users.php:45`, `agent-portal/profile.php:52`, `admin/change_password.php:42` — each guess is computationally expensive) and by this being a small, non-SaaS internal tool (limited realistic attacker incentive), but it remains a real gap relative to a fully account-aware rate limit. (The flip side — IP-based lockout — correctly prevents one attacker from DoS-locking a *specific* known victim account from behind a single IP, which an account-only lockout would be vulnerable to; this is a genuine trade-off, not simply a bug, and is worth noting both ways per the audit brief.)

**Root cause:** `RateLimiter`/the login pages' own file-based lockout is deliberately keyed on `REMOTE_ADDR` only (by design — see `app/Middleware/RateLimiter.php`'s doc comment on never trusting `X-Forwarded-For`), with no complementary per-`username` counter.

**Recommended fix:** Add a second, account-keyed counter (e.g. failed-attempt count stored on the `admin_users` row itself, or a separate `hash('sha256', 'login|'.$username)` lock file) alongside the existing IP-based one, so a distributed attacker can't bypass lockout by rotating IPs against one known username.

**File Location:** `admin/login.php:18-37`; `agent-portal/login.php:18-37`.

---

## Finding 9 — Three admin-session-gated endpoints check `$_SESSION` directly, bypassing the 30-minute inactivity timeout

**Severity:** Low
**Status:** Confirmed by code review and live header check.

**Impact:** `AuthMiddleware::requireAdmin()` is the only place that checks and refreshes `$_SESSION['admin_last_active']` against `SESSION_TIMEOUT_MINUTES` (`app/Middleware/AuthMiddleware.php:6-21`). Three endpoints bypass this helper and check the raw session flag directly instead:
- `api/dashboard_stats.php:11` — `if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) { ... http_response_code(403) ... }`
- `api/itinerary.php:6` — same pattern, but with **no `http_response_code()` call at all**, so an unauthenticated request gets `HTTP 200` with body `{"success":false,"message":"Unauthorized"}` (confirmed live: `curl -i .../api/itinerary.php?action=list` → `200 OK`) — a minor REST-correctness bug, not itself exploitable, but inconsistent with every other endpoint in the app, which correctly returns 401/403.
- `api/set_hero.php:10` — same direct-session pattern (though this one does correctly return `401`).

**Risk:** None of these three ever read or write `admin_last_active`, so a session that `requireAdmin()` would have expired after 30 minutes of inactivity remains fully valid against these three specific endpoints for as long as the PHP session itself lives (until browser close / cookie expiry, since the cookie's own `lifetime` is `0` — session-only — per `app/bootstrap.php:118-124`). A stolen/left-open session cookie that is *only* ever replayed against `dashboard_stats.php`, `itinerary.php`, or `set_hero.php` would never hit the 30-minute cutoff the rest of the app enforces.

**Root cause:** These three files were written (or last touched) before/without reusing `AuthMiddleware::requireAdmin()`, checking the boolean flag directly instead.

**Recommended fix:** Replace the raw `$_SESSION['admin_authenticated']` checks with `AuthMiddleware::requireAdmin()` (adjusted to return JSON instead of redirecting, same as the pattern already used in `api/booking_pdf.php` etc. for JSON-vs-redirect context), so the timeout is enforced uniformly.

**File Location:** `api/dashboard_stats.php:10-16`; `api/itinerary.php:5-11`; `api/set_hero.php:9-14`.

---

## Finding 10 — Track Booking password transmitted via GET query string

**Severity:** Low
**Status:** Confirmed by code review.

**Impact:** The one-time Track Booking password is passed as a URL query parameter in several places, e.g. `views/track.php:156-162`:
```js
'api/track_booking_pdf.php?ref=' + encodeURIComponent(d.booking_ref) + '&password=' + encodeURIComponent(pwd)
```
and consumed the same way server-side in `api/track_booking_pdf.php:51-52`, `api/track_quotation_pdf.php`, `api/track_itinerary_pdf.php`.

**Risk:** Credentials in URLs (even over HTTPS in production) are more likely to end up in server/proxy access logs, browser history, and `Referer` headers of any subsequent cross-origin request from that page, than credentials sent only in a POST body. The actual password itself is already low-value (single-use in spirit, bcrypt-hashed at rest, rate-limited), so this is a defense-in-depth nit rather than a standalone exploitable vulnerability, but it's an easy fix.

**Root cause:** These three are plain file downloads (`<a href>` / `target="_blank"`), which is the reason GET was used — a straightforward download link is simpler to implement than a POST-triggered download — but it puts the credential in the URL.

**Recommended fix:** Low priority. If retained as GET, at minimum ensure production web/proxy server access-log configuration doesn't retain full query strings for these paths; a more complete fix would issue a short-lived signed download token from an authenticated POST step instead of passing the password itself in the URL.

**File Location:** `views/track.php:156-162`; `api/track_booking_pdf.php:51-52`; `api/track_itinerary_pdf.php`; `api/track_quotation_pdf.php`.

---

## Finding 11 — CSP allows `'unsafe-inline'` for `script-src` and `style-src`

**Severity:** Low / Informational
**Status:** Confirmed live and in source.

**Impact:** The Content-Security-Policy header (set redundantly at both the `.htaccess` layer and the PHP layer — see "Verified Safe" section below) includes `'unsafe-inline'` for both `script-src` and `style-src`:
```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.paypal.com ...; style-src 'self' 'unsafe-inline' ...
```
(`.htaccess` and `app/bootstrap.php:58`, identical policy in both places.)

**Risk:** `'unsafe-inline'` on `script-src` means that *if* a reflected or stored XSS were ever introduced (none was found in this audit — see "Verified Safe" below), the CSP would not actually stop the injected `<script>` from executing, since inline scripts are explicitly allowed. CSP is therefore currently providing much weaker XSS defense-in-depth than its presence suggests at a glance.

**Root cause:** The codebase makes heavy, direct use of inline `<script>` blocks for page-specific logic throughout `admin/*.php`, `views/*.php`, etc. (confirmed throughout this review — e.g. `views/confirmation.php:320-378`), which would all break under a strict CSP without a nonce/hash scheme.

**Recommended fix:** Longer-term improvement, not urgent given no XSS was found: migrate toward nonce-based or hash-based CSP (`script-src 'self' 'nonce-<random-per-request>' ...`) to retain real protection against any future injection bug, rather than relying solely on `htmlspecialchars()` discipline everywhere. Not a priority fix given the rest of the app's consistently correct output-encoding (see below).

**File Location:** `.htaccess` (CSP header block); `app/bootstrap.php:58`.

---

## Verified Safe (reviewed, no issue found — documented per audit instructions to avoid false positives)

- **SQL Injection:** Every user-input-bearing query reviewed across `admin/*.php`, `agent-portal/*.php`, `api/*.php` uses PDO prepared statements with bound parameters. The handful of raw `->query()` calls that interpolate a PHP variable directly (`admin/activities.php:59`, `admin/accommodations.php:64`, `admin/users.php:93`, `admin/currency_rates.php:55`, `admin/seasonal_rates.php:47`, `admin/park_fees.php:46`, `admin/staff.php:66`, `admin/vehicles.php:58`, `admin/team_members.php:63`, `admin/packages.php:27`) only interpolate `$perPage`/`$offset` pagination values that are always derived via `(int)` casts (`$page = max(1,(int)($_GET['page'] ?? 1)); $perPage = 50; $offset = ($page-1)*$perPage;` — identical pattern in every file, confirmed individually), or (`admin/reviews.php:41`, `admin/contact_messages.php:42`) a `$where` string built from a ternary over a small fixed set of hardcoded strings, never from raw user input. `admin/customers.php`'s `$orderBy` is a `match()` over a fixed whitelist with a safe default. `admin/team_members.php:52`'s odd double-query line casts `(int)$_GET['edit']` before interpolating it, so no injection is possible there either. No instance of raw, unbound user input reaching a SQL string was found anywhere in the reviewed surface.

- **Reflected/Stored XSS:** No instance of `$_GET`/`$_POST`/`$_REQUEST` being echoed directly without encoding was found. All six admin search/filter boxes identified (`admin/customers.php`, `admin/custom_requests.php`, `admin/invoices.php`, `admin/bookings.php`, `admin/quotations.php`, `admin/email_queue.php`) correctly wrap the reflected value in `htmlspecialchars()` both in the `value="..."` attribute and in the "no results for ..." message. `views/confirmation.php` and `views/track.php` consistently escape every DB-sourced and GET-sourced value rendered into HTML; the one place a booking value is embedded in a `<script>` block, it is passed through `json_encode()` (`views/confirmation.php:326-328, 359-360`), which is the correct encoding for that context.

- **CSRF:** Every state-changing POST handler found across the full `admin/*.php` and `agent-portal/*.php` surface calls `CsrfMiddleware::validateOrDie()` (verified exhaustively: zero files had a `POST` branch without a matching `validateOrDie()` call). `app/Middleware/CsrfMiddleware.php` uses a session-bound synchronizer token compared with `hash_equals()` (timing-safe), generated with `random_bytes(32)`. Within `api/*.php`, the only gap found was `api/book.php`'s attacker-controlled bypass condition (Finding 6 above, which corroborates a pre-existing phase4 finding) and `api/analytics.php`, which is an intentionally public, anonymous telemetry beacon with no session-bound state or privileged action to protect (it only ever inserts a non-attributable analytics row) and is rate-limited instead (120/hour) — CSRF protection would add no real value there, so this is not counted as a gap.

- **File Uploads:** All three upload handlers found (`api/gallery/upload.php`, `api/upload_team_image.php`, and the contract upload in `admin/suppliers.php`) validate the actual file content via `finfo::file()` MIME sniffing (never trusting the client-supplied filename/extension), derive the stored extension from the validated MIME type (not from user input), generate the on-disk filename server-side (`uniqid()`/timestamp-suffixed, with user-derived text restricted to `[a-zA-Z0-9_-]`), enforce a size cap, and verify via `realpath()` that the final write path stays inside the intended directory before writing. The one PDF-accepting handler (`admin/suppliers.php`'s supplier-contract upload) correctly restricts to `application/pdf` by content sniffing and stores it under `storage/uploads/contracts/`, which is blocked from direct web access by both the root `.htaccess` (`RewriteRule ^storage/ - [F,L]`) and `storage/.htaccess` (`Deny from all`) — confirmed the serving endpoint (`api/supplier_contract.php`) requires `AuthMiddleware::requireAdmin()`, denies the `agent` role, and re-validates the resolved path stays under the contracts directory before `readfile()`.

- **IDOR via `OwnershipScope`:** Every booking/quotation/custom-request-fetching admin and agent-portal endpoint checked (`admin/bookings.php`, `admin/custom_requests.php`, `agent-portal/leads.php`, `agent-portal/commissions.php`, and the PDF/email endpoints listed in Finding 5's comparison) correctly calls `OwnershipScope::isScoped()` + the relevant `owns*()`/`*ListSql()` method before returning or acting on agent-reachable data — **except** `api/manifest_pdf.php` (Finding 5). Agent-portal list queries additionally filter at the SQL level with `WHERE agent_id=?`/`created_by=?` using the session's own `admin_user_id`, never a client-supplied ID.

- **Session Management / Session Fixation:** `session_regenerate_id(true)` is called immediately after successful authentication on both `admin/login.php:102` and `agent-portal/login.php:90`, preventing session fixation. Session cookies are configured with `httponly: true`, `samesite: 'Strict'`, and `secure` conditional on an active HTTPS connection (`app/bootstrap.php:117-126`) — appropriate for this local-HTTP dev environment, and correctly becomes `true` automatically once served over HTTPS in production (no separate prod/dev code path needed). The PIN-based admin-login fallback (`admin/login.php:85-92`) is deliberately neutralized: `ADMIN_PIN` is defined as `bin2hex(random_bytes(32))`, re-generated fresh on every request (`config/app.php:166-170`, with an explicit "DO NOT replace this with a real PIN" comment), so this code path can never actually authenticate anyone — confirmed this is intentional, not a disabled-but-exploitable backdoor.

- **Password Storage:** All real password hashing (`admin/users.php`, `agent-portal/profile.php`, `admin/change_password.php`) uses `password_hash(..., PASSWORD_BCRYPT, ['cost' => 12])`. The separate, lower-cost (`cost: 10`) hash in `app/Services/BookingTracking.php:22` is appropriately scoped lower — it protects a single-use, 8-character-from-32-char-alphabet (≈40-bit), short-lived guest tracking code, not a long-lived account credential, so cost 10 there is a reasonable, deliberate choice rather than an oversight.

- **Open Redirect:** The only user-influenceable `Location:` redirect found (`admin/login.php:112-121`) is explicitly constrained to a hardcoded whitelist (`$allowedRedirects`) before use; any other value falls back to `index.php`. `admin/reviews.php:18` and `admin/incidents.php:59` append a URL-encoded query value onto a fixed, hardcoded relative path — not a redirect destination — so neither is exploitable.

- **Hardcoded Secrets:** No API keys, passwords, or tokens were found hardcoded in PHP/JS source (`grep` swept for common patterns: `api_key=`, `secret=`, `password=` literals, Anthropic/AWS/Slack key-shaped strings — all clean). `ANTHROPIC_API_KEY`, `PAYPAL_CLIENT_SECRET`, and `CRON_SECRET` are all sourced exclusively from `.env` via `env()`, with `CRON_SECRET` explicitly failing closed (empty string, never a guessable default) when unset (`config/app.php:172-175`).

- **`.env` Exposure:** `.env` is listed in `.gitignore` (`.env` + `*.env.local`) and is not tracked in git (`git ls-files` shows only `.env.example`). Direct web access is blocked by two independent layers: root `.htaccess`'s `<FilesMatch "\.(env|...)$"> Deny from all </FilesMatch>` and the hidden-file block `RewriteRule ^\.(?!well-known) - [F,L]`. Confirmed live: `curl -o /dev/null -w "%{http_code}" http://localhost/safari-zangu/.env` → `403`. `.git/config`, `app/bootstrap.php`, and `sql/baseline_schema.sql` were also probed and confirmed `403`.

- **Security Headers:** Contrary to the common assumption that a small operator site would have none of these, this application sets a solid set of security headers **in two independent layers** (`.htaccess`'s `<IfModule mod_headers.c>` block, and redundantly again in PHP at `app/bootstrap.php:50-59` specifically because, per its own comment, `mod_headers` was found disabled in this environment and can't be assumed enabled in production either): `X-Frame-Options: SAMEORIGIN` (`DENY` specifically under `/admin/`, via `admin/.htaccess`), `X-Content-Type-Options: nosniff`, `X-XSS-Protection`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (camera/mic/geolocation all denied), `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Resource-Policy: same-origin`, and a real `Content-Security-Policy` (with the `'unsafe-inline'` caveat noted in Finding 11). All confirmed present live via `curl -i`. The admin cookie is additionally served with `Cache-Control: no-store, no-cache, must-revalidate, private` and `X-Powered-By` is unset. The only gap is HSTS, which is explicitly commented-out-pending-production-HTTPS-confirmation in `.htaccess` rather than missing by oversight — correct for an HTTP-only local dev environment.

- **SSRF:** The one outbound server-side HTTP call to a third party with request-influenced content (`api/generate_caption.php`'s call to `api.anthropic.com`) uses a hardcoded destination URL; the only "variable" part of the request is a prompt string built entirely from trusted DB data (`$pkg` fields fetched by a server-controlled `package_id` lookup, never client-supplied free text), so there is no attacker-controlled destination or SSRF vector.

- **Mail Header Injection:** No raw `mail()` calls with manually concatenated headers exist anywhere in the codebase; all outbound email goes through PHPMailer (`app/Mail/Mailer.php`), which handles header encoding/sanitization internally.

---

## Methodology Note

This review combined manual source reading of every `admin/*.php` (49 files), `agent-portal/*.php` (6 files), and `api/*.php` (32 files) entry point for auth-ordering and CSRF coverage; targeted `grep` sweeps for known-risky patterns (raw `->query()` with interpolated variables, `echo $_GET/$_POST`, `move_uploaded_file`, `header('Location: '.$_GET...)`, hardcoded secret-shaped strings, `mail(`); and a small number of safe, read-only `curl` probes against the local dev server to confirm live behavior (reachability, HTTP status codes, and — where already independently reported in `reports/phase4_api.md` — the `get_setting.php` bank-account disclosure). No probe submitted a real payment, created a real booking/lead/quotation/customer row, or attempted any write against `admin_users`/`settings`/any other table. DB credentials were not accessed.
