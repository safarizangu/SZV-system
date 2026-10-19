# API Specification

**Status:** Active
**Owner:** Project Team
**Version:** 1.1.0
**Last Updated:** 2026-10-19

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
| `track_lookup.php` | POST | None, rate-limited | JSON booking lookup by `ref` + `password` for the public Track Booking page — a real one-time password (`App\Services\BookingTracking::verify()`), not the weaker ref+travel-date pairing this used before. Returns the full cost breakdown (real `amount_paid`/`balance_due`, not just the suggested deposit), special requests, and an itinerary summary, since a real password now gates it. |
| `track_booking_pdf.php` | GET | None, rate-limited | Streams the same HTML `booking_pdf.php` gives admins, gated by `ref`+`password` and sharing `track_lookup.php`'s per-IP rate-limit counter (configurable — `track_lockout_attempts`/`track_lockout_minutes` settings) so the lockout can't be dodged by switching endpoints. |
| `track_quotation_pdf.php` | GET | None, rate-limited | Streams the booking's Quotation/Invoice PDF (`quotation_pdf.php`, which self-labels Quotation/partial Invoice/full Invoice from the live `payment_status`) — same `ref`+`password` gate and shared rate limit. |
| `track_itinerary_pdf.php` | GET | None, rate-limited | Streams the booking's itinerary PDF (`quotation_itinerary_pdf.php`) — same gate. |
| ~~`check_booking.php`~~ | GET | None, rate-limited | **Dead code** — no caller anywhere in the current UI (`views/track.php` uses `track_lookup.php` instead). Still reachable by direct URL and still uses the old percentage-based service-charge estimate rather than a real `amount_paid`; left unfixed since nothing links to it, but worth removing outright in a future cleanup rather than leaving it to confuse the next read-through. |

## Payments

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `paypal_create_order.php` | POST | None, rate-limited | Creates a PayPal order server-side for the amount the server itself computes — the client never supplies the amount being charged. Accepts **either** `travel_date` (same-session trust, used by `confirmation.php` right after booking) or the Track Booking `password` (used by `track.php`, a separate later visit) as proof of ownership. |
| `record_payment.php` | POST | None, rate-limited | Verifies a PayPal order via `App\Services\PayPalClient` (OAuth2 + live `/v2/checkout/orders/{id}` lookup) before marking a booking `deposit_paid`/`fully_paid`. The client-reported status/amount is never trusted — only the server-verified PayPal response is. Accumulates the verified amount into the real `bookings.amount_paid` (not the merely suggested `deposit_amount`, which it previously and incorrectly read), then sends the same branded Invoice/Deposit-Receipt email `admin/bookings.php`'s manual status change does — gated by the `auto_send_payment_emails` setting. |

## Quotation lifecycle (admin)

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `itinerary.php` | GET/POST | Admin | Feeds `admin/quotation_new.php`'s line-item builder — resolves an itinerary's days into priced components via `ItineraryQuoteBuilder`. |
| `quotation_email.php` | POST | Admin, ownership-checked for agents | Sends the quotation to the client. Generates `public_token` on first send, builds the view link, attaches Quotation + Itinerary PDFs. Builds its own email body directly (`Mailer::queueRaw()`) rather than going through the `quotation_sent` template — editing that template in `admin/email_templates.php` has no effect on this send. |
| `quotation_pdf.php` | GET | Admin, ownership-checked for agents | Streams the Quotation document. Self-determines Quotation / partial Invoice (Deposit Paid, real amount/balance) / full Invoice (Paid in Full) purely from the linked booking's live `payment_status`/`amount_paid`, re-queried fresh on every view — no query flag involved any more. |
| `quotation_itinerary_pdf.php` | GET | Admin, ownership-checked for agents | Streams the quotation's day-by-day itinerary companion, preserving the snapshot-first resolution priority (the quotation's own `itinerary`/`includes`/`excludes` columns, then `itinerary_id`, then a package-name fallback). |
| `convert_quotation.php` | POST | Admin, ownership-checked for agents | Converts an accepted quotation into a `bookings` row, carrying over its deposit percentage and line items; prevents an agent from converting (and thereby triggering a commission) on a quotation they don't own. |
| `quote_view.php` | GET | None, rate-limited (token-only) | The client-facing view link from the quotation email. Accepts **only** `?t=<48-hex-char public_token>` — never a numeric id. Increments `view_count`, sets `first_viewed_at`/`last_viewed_at`, advances `status` `sent`→`viewed` (never backward), then internally includes `quotation_pdf.php` with `SZV_PDF_INTERNAL_RENDER` defined. |

## Booking documents & resend (admin)

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `booking_pdf.php` | GET | Admin, ownership-checked for agents | Streams Booking Confirmation / Invoice — same self-determined Quotation/partial/full state as `quotation_pdf.php`. Rebuilds its itemized breakdown live via `ItineraryQuoteBuilder` for bookings with no linked quotation (most of them), but the Grand Total always comes from the frozen `bookings.total_amount`, never a live recompute. |
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
| `supplier_contract.php` | GET | Admin, agent-blocked | Streams a supplier's uploaded Contract PDF from `storage/uploads/contracts/` (outside the web root, `.htaccess`-blocked from direct access — this is the only path to it). Upload (via `admin/suppliers.php`'s own save form) is validated by real content-type sniffing, not the filename/extension. |
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
