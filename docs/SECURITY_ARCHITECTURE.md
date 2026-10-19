# Security Architecture

**Status:** Active
**Owner:** Project Team
**Version:** 1.1.0
**Last Updated:** 2026-10-19

## Purpose

How access control actually works — the RBAC model, the ownership-scoping
pattern, and the hardening already in place. For a running checklist used
during deploys, see [SECURITY_CHECKLIST.md](SECURITY_CHECKLIST.md).

---

## 1. Roles

Four roles, stored in `admin_users.role`:

| Role | Scope |
|---|---|
| `superadmin` | Full access. **Currently identical to `admin`** in every permission check in the codebase — a deliberate "not yet differentiated" state, not a bug. |
| `admin` | Full access to all admin pages and all records. |
| `staff` | Full page access (no dedicated restriction method exists for this role) minus Settings-page visibility in the nav. Can still edit a package's itinerary via the save form — flagged as an open question, not yet resolved either way. |
| `agent` | Restricted to their own data only — see §2. Cannot set their own `agent_id` or `commission_rate` in any request; both are forced server-side regardless of what's posted. |

`App\Middleware\AuthMiddleware::isAgentRole()` is true only for `agent`.
`isAdminRole()` is true for `admin`/`superadmin`. There is no
`isStaffRole()` — staff is handled as "whatever's left" rather than a
positive check.

**An `agent` account has two separate front doors into the same session
model.** `admin/*.php` pages still work for an agent (several of them
narrow their own data to `OwnershipScope`), but `agent-portal/` is now
the dedicated surface: its own `login.php`/`logout.php`, its own
`AuthMiddleware::requireAgent()` (same session shape and inactivity
timeout as `requireAdmin()`, but redirects to `agent-portal/login.php`
and additionally requires `isAgentRole()`), and `admin/login.php` itself
redirects a successful agent login straight into `agent-portal/index.php`
rather than the admin dashboard. The portal's "New Client" flow
(`agent-portal/leads.php`) can create a real `bookings` row directly
(gated by the `agent_direct_booking_enabled` setting) or a
`custom_safari_requests` row — both covered by the ownership rules below.

## 2. Ownership model

Two of the record types an agent must be scoped to —
**bookings** and **custom_safari_requests** — have **no owner column**.
Ownership is derived by joining through the one relationship that does
carry it:

```
bookings          → quotations.created_by   (if quotation_id is set)
custom_safari_requests → quotations.created_by (if a linked quotation exists)
leads             → leads.agent_id          (this one does have a real column)
commissions       → commissions.agent_id
```

This means a booking or custom request with **no linked quotation at all**
is invisible to every agent (correct — an agent shouldn't see a walk-in
booking they had no hand in) but fully visible to every admin-role user.

**One deliberate exception**: `custom_safari_requests.submitted_by_agent_id`.
When an agent submits a custom request directly (agent portal's "New
Client → Custom Request"), there's no quotation yet at creation time to
derive ownership from — this column is stamped instead, specifically so
the submitting agent keeps visibility of their own request even after an
admin later builds the quotation under their own `created_by`.
`OwnershipScope::customRequestsListSql()`/`ownsCustomRequest()` check
both this column and the derived-via-quotation path; everything else
(bookings included) stays purely derived, no stored owner column.

`App\Services\OwnershipScope` is the single place this logic lives:

- `isScoped()` — true only when the current session role is `agent`.
- `bookingsListSql()` / `customRequestsListSql()` — the `WHERE`/`JOIN`
  fragment a list query adds when scoped.
- `ownsBooking()` / `ownsCustomRequest()` / `ownsQuotation()` — single-record
  checks used by every PDF/email endpoint and by `convert_quotation.php`
  before letting an agent act on a specific record.

Every place that reads or acts on a booking, custom request, or quotation
— admin list pages, the PDF endpoints, the email-send endpoints, and the
quote-to-booking conversion — goes through this same class, so there's one
definition of "owns this record," not a different ad-hoc check per file.

## 3. Denial logging

`App\Services\AccessGuard` is the single place an unauthorized request is
rejected: `denyJson()`, `denyRedirect()`, `denyPlain()`. Each one writes to
`activity_log` with `action='access_denied'` (including the attempted
target as a string, e.g. a booking ref, since those aren't always
numeric) **before** denying — so an attempted cross-tenant access attempt
is always auditable, not silently dropped.

## 4. CSRF

`App\Middleware\CsrfMiddleware::validateOrDie()` / `::field()`. Every
state-changing form includes a token; `admin/itineraries.php`'s delete
action was a real gap found and fixed this cycle — it was previously a
bare `?delete=ID` GET link with no CSRF check at all, converted to a
POST + token-validated form submission.

## 5. Rate limiting

`App\Middleware\RateLimiter` — file-based, keyed by `REMOTE_ADDR` only
(never `X-Forwarded-For`, which a client can set to anything). Used on:
login (5/15min), public form submissions (5/hour), booking tracking
(default 3 failed lookups/15min — configurable from Settings → General →
Security as `TRACK_LOCKOUT_ATTEMPTS`/`TRACK_LOCKOUT_MINUTES`, shared
across all four Track Booking endpoints — `track_lookup.php`,
`track_booking_pdf.php`, `track_quotation_pdf.php`,
`track_itinerary_pdf.php` — via one lock file per IP, so switching which
one is hit doesn't reset the counter), and the public quotation view link
(120/hour).

## 5a. Track Booking authentication

A guest's proof of owning a booking used to be "knows the booking ref and
the travel date" — both plausibly visible to someone else (a shared
screen, a forwarded email) and not a real secret. It's now a genuine
one-time password: `App\Services\BookingTracking::issue()` generates an
8-character unambiguous-alphabet string, bcrypt-hashes it into
`bookings.tracking_password_hash`, and the plaintext exists only for the
remainder of the request that creates it — emailed once in the
confirmation email, flashed once on `confirmation.php` via a session
variable that's read-and-unset (never re-shown on reload, never put in a
URL), then gone. `BookingTracking::verify($db, $ref, $password)` is the
one place a ref+password pair is checked, behind the same rate-limited
lockout as above. This same credential now also gates PayPal payment
creation (`api/paypal_create_order.php`, `api/record_payment.php`) as an
alternative to the travel-date check `confirmation.php`'s own
same-session payment flow still uses — a *different* credential for a
*different* trust context (just-completed booking in this browser, vs. a
guest returning later), not two checks of the same thing.

## 6. Session hardening

```php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => true,       // HTTPS only on production
    'httponly' => true,
    'samesite' => 'Strict',
]);
```

Login lockout is file-based (`/tmp/szv_ratelimit/`), not session-based —
can't be bypassed by opening a fresh session after failing.

## 7. Data-boundary enforcement by surface

| Surface | Enforcement |
|---|---|
| Admin pages agents should never see at all (fleet, suppliers, staff, financial dashboards, settings, etc.) | `AuthMiddleware::isAgentRole()` → `AccessGuard::denyRedirect()` right after the page's `requireAdmin()` call — 22 pages hardened this way. |
| Admin-write APIs agents should never call (`set_hero.php`, `vehicle_manage.php`, `upload_team_image.php`, `generate_caption.php`) | Same pattern via `AccessGuard::denyJson()`. |
| Bookings / custom requests / quotations an agent didn't originate | `OwnershipScope`, as above — list queries are scoped, and every single-record action (PDF view, email send, conversion) re-checks ownership independently rather than trusting that the list was filtered correctly upstream. |
| Settings pages | `isAdminRole()` required — `admin/currency_rates.php` was missing this check and has been fixed; it's now consistent across every Settings page. |
| Package publish/visibility toggle | `admin/packages.php`'s `toggle_id` action and `admin/package_edit.php`'s `link`/`unlink` itinerary actions now both require `isAdminRole()` — previously callable by any authenticated role. |
| Supplier Contract PDFs | Stored in `storage/uploads/contracts/`, outside the web root and already `.htaccess`-blocked (`RewriteRule ^storage/ - [F,L]`) same as every other upload directory. The only path to a file is `api/supplier_contract.php`, which requires `requireAdmin()` and denies agents the same way the Suppliers page itself does. Upload is validated by real content-type sniffing (`finfo`), not the filename/extension. |

## 8. Known gaps / deliberate non-decisions

Documented so they aren't mistaken for oversights on the next read-through:

- **Superadmin vs. Admin**: identical today. If differentiated later,
  every `isAdminRole()` check needs a decision about which side of the
  split gets which permission — not something to infer from this doc.
- **Staff + itinerary editing**: staff can currently save changes to a
  package's itinerary via `admin/package_edit.php`'s save form. Whether
  that should be admin-only is an open product decision.
- ~~**`custom_safari_requests` has no `assigned_to` column**~~ — **resolved.**
  `submitted_by_agent_id` now covers the one case this gap described (an
  agent submitting a request directly, before any quotation exists); see
  §2. It's deliberately narrower than a general `assigned_to` — it only
  ever reflects who *submitted* the request, not a later reassignment, so
  "assign this walk-in request to an agent" is still not representable.
