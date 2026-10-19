# Safari Zangu Ventures — System Audit (Phase 2)

Date: 2026-10-19
Scope: full booking lifecycle (all three creation paths — public, agent portal, converted request), verified end-to-end against the session's own [Booking Workflow Map](Booking_Workflow_Map.pdf), with live disposable-data tests, not a read-through.

## 1. Bugs found and fixed

**`api/record_payment.php` never updated the real amount paid.**
A PayPal payment computed its "already paid" base from `bookings.deposit_amount` — the merely *suggested* deposit figure — and never wrote to `bookings.amount_paid` at all. Once `amount_paid` became the real source of truth for the Quotation/Invoice PDF and Track Booking page (this session's own Track Booking + Invoice work), a guest who paid online via PayPal would see their own Invoice PDF report **$0.00 paid**, balance still the full total, despite the payment having gone through and `payment_status` correctly flipping to `deposit_paid`/`fully_paid`. Fixed: reads and accumulates the real `amount_paid`, and now sends the same branded Invoice/Deposit-Receipt email (with the correct PDFs attached) that the manual admin-entry path already sent, instead of a separate bare-text notification.

**`api/booking_pdf.php` never actually became an invoice.**
This file (behind Track Booking's "Download PDF Confirmation" link) kept the old all-or-nothing invoice flag — `?invoice=1` required *and* `payment_status === 'fully_paid'`. Track Booking's own download link never passed that flag, so this document never relabeled itself as an Invoice for any booking, fully paid or not, and had no concept of a partial/deposit-paid state at all (no real amount paid, no real balance, no "Balance Still Due" section). Fixed to self-determine Quotation / partial Invoice / full Invoice purely from the booking's live `payment_status`/`amount_paid`, the same model `api/quotation_pdf.php` already used — so this document and the Quotation/Invoice PDF now always agree.

**`admin/bookings.php` showed stale figures once a real payment existed.**
Both the compact bookings-table row ("dep: $X") and the full booking detail modal continued to display the *suggested* `deposit_amount` and a balance computed from it, even after an admin had recorded a different real amount via the new "Deposit Paid" prompt. An admin viewing their own booking list could see a different paid/balance figure than the client's Invoice PDF and Track Booking page. Fixed — both now switch to the real `amount_paid`/real balance once `payment_status` is `deposit_paid` or `fully_paid`.

**Pre-existing: unbalanced `<div>` in `api/quotation_pdf.php`'s fully-paid Invoice.**
Found while restructuring the same invoice-state conditional for the fix above. The `.last-page` wrapper's opening `<div>` was inside an `if (!$isInvoice)` block, but its closing `</div>` was unconditional — every "Paid in Full" invoice ever generated had one stray closing tag with no matching open. Fixed as part of the same change (now both the open and close are conditional).

## 2. Confirmed dead code (not fixed — flagged instead)

`api/check_booking.php` has no caller anywhere in the current codebase (`views/track.php` uses `api/track_lookup.php`). It still reverse-derives a percentage-based service charge estimate rather than a real `amount_paid`, same category of issue as bug #2 above, but since nothing reaches it, it has no live effect. Left as-is; documented in [API_SPECIFICATION.md](../API_SPECIFICATION.md) as a candidate for outright removal rather than a fix, since fixing dead code just as plausibly bit-rots again unnoticed.

## 3. Verified working end-to-end (live disposable-data tests, not read-through)

- All three booking-creation doors (`api/book.php`, `agent-portal/leads.php` direct booking, `api/convert_quotation.php`) — correct pricing, linked quotation with correct `created_by`, Track Booking password issuance + email, lead→booked→commission chain where applicable.
- Service Charge Adults/Children split, including a package with distinct per-type rates (not just the shared flat default) — correct in the line items, the Quotation PDF, and `admin/package_edit.php`'s/`admin/quotation_new.php`'s own save + edit-mode prefill (both previously untested end-to-end through their real POST handlers).
- Deposit Paid → Fully Paid status progression via `admin/bookings.php`, including the mandatory amount-received prompt, the resulting Invoice PDF states, and both branded emails.
- Track Booking lookup, Quotation/Invoice PDF download, and Itinerary PDF download, each with correct-password success and wrong-password rejection against the shared rate limiter.
- Supplier Contract PDF upload, replace, remove, and admin-gated download, including real content-type validation and direct-URL access being blocked.
- New Settings — all nine new keys save correctly and take effect immediately (verified via direct constant inspection after each save), with every default matching the previously-hardcoded value exactly, so nothing changed behavior for an installation that never touches Settings.
- A full sweep for leftover test data and stray settings overrides from this session and the one before it — none found remaining in the live database by the time this report was written.

## 4. Scope note

This pass audited the booking lifecycle specifically — the surface this session's own changes touched, and what the Booking Workflow Map describes. It did not re-audit unrelated admin pages (fleet, gallery, analytics dashboards, etc.) that saw no changes this session or the one before it.
