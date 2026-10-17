# Security Architecture

**Status:** Active
**Owner:** Project Team
**Version:** 1.0.0
**Last Updated:** 2026-10-16

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
(3 failed lookups/15min, shared between `track_lookup.php` and
`track_booking_pdf.php` so switching endpoints doesn't reset the
counter), and the public quotation view link (120/hour).

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

## 8. Known gaps / deliberate non-decisions

Documented so they aren't mistaken for oversights on the next read-through:

- **Superadmin vs. Admin**: identical today. If differentiated later,
  every `isAdminRole()` check needs a decision about which side of the
  split gets which permission — not something to infer from this doc.
- **Staff + itinerary editing**: staff can currently save changes to a
  package's itinerary via `admin/package_edit.php`'s save form. Whether
  that should be admin-only is an open product decision.
- **`custom_safari_requests` has no `assigned_to` column** — if the
  business wants a request to show up for a specific agent before any
  quotation exists, this table needs a real ownership column; right now
  there is no way to represent that.
