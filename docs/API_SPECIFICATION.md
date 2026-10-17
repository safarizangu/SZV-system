# API Specification

**Status:** Active
**Owner:** Project Team
**Version:** 1.0.0
**Last Updated:** 2026-10-16

## Purpose

Every endpoint under `api/`, as they actually exist in code today — method,
auth, and what each one does. Verified against the live files, not
reconstructed from memory. All endpoints return JSON unless noted
otherwise; PDF endpoints stream HTML (viewable directly, or converted to a
PDF file internally by `App\Services\PdfRenderer` when attached to an
email — see
[SYSTEM_ARCHITECTURE.md §5](SYSTEM_ARCHITECTURE.md#5-pdf-generation)).

Auth column key: **None** = no session required · **Admin** = any admin
panel session (`$_SESSION['admin_authenticated'] === true`), further
scoped by role where noted · **Rate-limited** = `App\Middleware\RateLimiter`
enforces a per-IP cap regardless of auth.

---

## Public form submission

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `book.php` | POST | None, rate-limited | Submit the booking form. Validates via `BookingValidator`, generates `booking_ref` (`SZV` + `bin2hex(random_bytes(5))`), inserts the booking, schedules 4 `booking_reminders`, queues `booking_confirmation` with Confirmation + Itinerary PDFs attached. |
| `contact.php` | POST | None, rate-limited | Contact form. Strips newlines from header-bound values (anti header-injection), queues `contact_acknowledgement` to the guest and `contact_admin_notification` to admin via `Mailer::queueBoth()`. |
| `custom_safari.php` | POST | None, rate-limited | Bespoke-safari enquiry form. Creates/updates the `customers` row, inserts into `custom_safari_requests`, queues `custom_request_ack` / `custom_request_admin`. |
| `review_submit.php` | POST | None, rate-limited | Guest review submission keyed by `booking_ref`. Inserts into `reviews` (`is_published=0` until an admin approves), queues an admin notification via `Mailer::queueRaw()` (not template-editable — see note in [SYSTEM_ARCHITECTURE.md §6](SYSTEM_ARCHITECTURE.md#6-email-system)). |

## Booking lookup / tracking (public)

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `track_lookup.php` | POST | None | JSON booking lookup by `ref` + `date` for the public Track Booking page — the pair is the visitor's only proof of ownership. |
| `check_booking.php` | GET | None, rate-limited | Reverse-calculates the VAT breakdown (`subtotal`/`vat_amount`/`vat_rate`) of a booking's authoritative total, for display without exposing the full record. |
| `track_booking_pdf.php` | GET | None, rate-limited | Streams the same HTML `booking_pdf.php` gives admins, gated by the same `ref`+`date` proof and sharing `track_lookup.php`'s per-IP rate-limit counter so the 3-attempt lockout can't be dodged by switching endpoints. |

## Payments

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `paypal_create_order.php` | POST | None, rate-limited | Creates a PayPal order server-side for the amount the server itself computes — the client never supplies the amount being charged. |
| `record_payment.php` | POST | None, rate-limited | Verifies a PayPal order via `App\Services\PayPalClient` (OAuth2 + live `/v2/checkout/orders/{id}` lookup) before marking a booking `deposit_paid`/`fully_paid`. The client-reported status/amount is never trusted — only the server-verified PayPal response is. |

## Quotation lifecycle (admin)

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `itinerary.php` | GET/POST | Admin | Feeds `admin/quotation_new.php`'s line-item builder — resolves an itinerary's days into priced components via `ItineraryQuoteBuilder`. |
| `quotation_email.php` | POST | Admin, ownership-checked for agents | Sends the quotation to the client. Generates `public_token` on first send, builds the view link, attaches Quotation + Itinerary PDFs. Builds its own email body directly (`Mailer::queueRaw()`) rather than going through the `quotation_sent` template — editing that template in `admin/email_templates.php` has no effect on this send. |
| `quotation_pdf.php` | GET | Admin, ownership-checked for agents | Streams the Quotation document. `?invoice=1` relabels it INVOICE, honored only after re-querying the linked booking's `payment_status === 'fully_paid'` — the URL flag alone can't trigger it. |
| `quotation_itinerary_pdf.php` | GET | Admin, ownership-checked for agents | Streams the quotation's day-by-day itinerary companion, preserving the snapshot-first resolution priority (the quotation's own `itinerary`/`includes`/`excludes` columns, then `itinerary_id`, then a package-name fallback). |
| `convert_quotation.php` | POST | Admin, ownership-checked for agents | Converts an accepted quotation into a `bookings` row, carrying over its deposit percentage and line items; prevents an agent from converting (and thereby triggering a commission) on a quotation they don't own. |
| `quote_view.php` | GET | None, rate-limited (token-only) | The client-facing view link from the quotation email. Accepts **only** `?t=<48-hex-char public_token>` — never a numeric id. Increments `view_count`, sets `first_viewed_at`/`last_viewed_at`, advances `status` `sent`→`viewed` (never backward), then internally includes `quotation_pdf.php` with `SZV_PDF_INTERNAL_RENDER` defined. |

## Booking documents & resend (admin)

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `booking_pdf.php` | GET | Admin, ownership-checked for agents | Streams Booking Confirmation / Invoice. Rebuilds its itemized breakdown live via `ItineraryQuoteBuilder` for bookings with no linked quotation (most of them), but the Grand Total always comes from the frozen `bookings.total_amount`, never a live recompute. |
| `booking_itinerary_pdf.php` | GET | Admin, ownership-checked for agents | Streams the booking's day-by-day itinerary companion. |
| `booking_email.php` | POST | Admin, ownership-checked for agents | Manually resends the booking confirmation. Builds its own HTML inline (`Mailer::queueRaw()`) — a separate code path from the automatic send in `book.php`/`convert_quotation.php`, so the two are not guaranteed to produce byte-identical output. |
| `voucher_pdf.php` | GET | Admin, ownership-checked for agents | Streams the Travel Voucher (logistics only — no pricing). |
| `voucher_email.php` | POST | Admin, ownership-checked for agents | Emails the Travel Voucher to the guest. |
| `manifest_pdf.php` | GET | Admin | Streams the Daily Departure Manifest for a given date — every confirmed departure that day, for the ops team. |

## Operational / misc (admin)

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `generate_caption.php` | POST | Admin, agent-blocked, rate-limited | Calls the Anthropic API to draft a social-media poster caption for `admin/poster_maker.php`. Degrades to a clear "not configured" message if `ANTHROPIC_API_KEY` is unset. |
| `set_hero.php` | POST | Admin, agent-blocked | Sets the homepage hero image. |
| `vehicle_manage.php` | POST | Admin, agent-blocked | Fleet CRUD for `vehicles`. |
| `upload_team_image.php` | POST | Admin, agent-blocked | Uploads a guide/staff profile photo. |
| `gallery/upload.php` / `gallery/delete.php` / `gallery/list.php` | POST/POST/GET | Admin | Gallery image management; `delete.php` enforces a `realpath()` boundary check so a crafted path can't escape `assets/images/`. |
| `dashboard_stats.php` | GET | Admin | Dashboard summary tiles. |
| `analytics.php` | POST | None, rate-limited | Records a public-site analytics event (page view / interaction) into `analytics_events`. |
| `get_setting.php` | GET | None | Reads one key from the `settings` table. No auth — scoped by a strict `^[a-z0-9_]+$` key allowlist and read-only, so this is intentionally public rather than an oversight; still worth re-checking if a sensitive key is ever added to `settings`. |

## Cron (CLI-only, 403 from any web request)

| Endpoint | Purpose |
|---|---|
| `cron/process_email_queue.php` | Retry net for `email_queue` — picks up anything still `pending` or under 3 failed attempts. |
| `cron/process_reminders.php` | Fires due `booking_reminders` (deposit/balance/departure/post-trip/win-back) and the departure reminder's PDF attachment. |
| `cron/send_single_email.php` | Background single-send dispatched by `Mailer::queue()` right after insert, so most emails leave within seconds rather than waiting for the next cron tick. |

## Response shape

Every JSON endpoint follows the same minimal contract:

```json
// success
{ "success": true, "...": "endpoint-specific fields" }
// failure
{ "success": false, "message": "human-readable reason" }
```

There is no API versioning and no shared router — each file is a
self-contained handler, so new endpoints are added by creating a new file
under `api/`, not by registering a route.
