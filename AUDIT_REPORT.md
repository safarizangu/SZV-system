# Safari Zangu Ventures — System Audit (Phase 1)

Date: 2026-10-04
Scope: current PHP admin/booking system in this repository, as verified during this session.
Ratings are judgment scores (1–100) for this codebase. Competitor comparisons reflect general market knowledge, not a live feature review.

## 1. Existing functionality (verified)

- Public site: packages, custom safari request form, contact form, direct booking with pricing from the itinerary engine, booking tracking page.
- Pricing: `ItineraryQuoteBuilder` (single live engine), per-person-sharing room occupancy, season multipliers, VAT, deposit split.
- Quotations: create/edit with line items, status changes, PDF (quotation, itinerary, invoice-mode), email send, convert to booking.
- Bookings: list, status (payment) changes, detail modal, PDF/itinerary links, invoice email auto-send on fully paid, reminders scheduled.
- Leads pipeline: stages, manual and automatic advance from quotation/booking, commission creation on booked, agent notification email.
- Custom requests: intake, status (viewed → in review, quoted/converted via quotation linkage).
- Commissions: approve/paid workflow, rate setting (admin only), agent summary.
- Invoices list (agent-scoped), vouchers (print + email), reviews submission and admin notification.
- Operations: guides/drivers roster, room availability, incidents with emergency contacts, suppliers table, park fees, seasonal rates.
- Communications: email templates, email queue with cron processing and immediate send, booking reminder cron.
- Admin: dashboard (business and agent views), notification bell, profile page, users, activity log, password change.
- Security controls: CSRF on admin forms, role checks (`isAgentRole`/`isAdminRole`), server-side ownership scoping for leads, quotations, commissions, invoices, bookings, custom requests, activity log for denied attempts.

## 2. Missing functionality

- Customer portal (quote/itinerary view, document upload, invoices, payments).
- Agent (B2B) portal with its own login and custom agent pricing.
- Online payments (Stripe, PayPal, Flutterwave, Pesapal, M-Pesa). Only bank transfer and manual payment status exist.
- WhatsApp Business API automation (only click-to-chat links exist).
- Financial module: P&L, cash flow, expense tracking, supplier payments, refunds.
- Business intelligence dashboards with trends, forecasts, CLV, repeat customer metrics.
- Fleet management: plate numbers, insurance/road licence expiry, maintenance schedule, fuel tracking, availability.
- Guide management: certifications, ratings, leave, availability calendar, performance.
- Supplier contracts, rate cards, contract expiry alerts, supplier invoice tracking.
- Availability engine that prevents double booking across rooms, vehicles and guides.
- Document vault with versioning and permissions.
- Quote analytics (view tracking, open and acceptance rates).
- Multi-currency quotations (USD, EUR, GBP, TZS, KES). A `currency_rates` table and a `currency` column exist, but quotations are priced in USD.
- Google and TripAdvisor review integration.
- Itinerary builder with drag-and-drop, route optimisation, distance and time estimates.
- Operations command centre (today's arrivals and departures, guests in safari, emergency cases).

## 3. Technical debt

- Several parallel implementations were found and removed this session (`api/leads.php`, a duplicate custom-requests schema). Other duplicated logic remains: `invoices.php` derives booking ownership with its own join, separate from `OwnershipScope`.
- Mixed patterns: some pages scope by `isAgentRole()` inline, others use the shared helpers.
- Large monolithic admin pages mixing HTML, SQL and inline JavaScript (for example `admin/bookings.php`, `admin/package_edit.php`).
- Pricing is computed in parallel in PHP and in JavaScript preview code (`calcTotals()`), kept in sync by tests rather than by a single source.
- Dead or stale files remain in some areas; a full dead-code sweep has not been done.
- No automated end-to-end test suite; 78 unit/structural tests cover pricing, season logic and rate limiting.

## 4. Security gaps

- Bookings, custom requests, currencies and package link/toggle were open to agents. Fixed this session; a full route-by-route sweep of every POST/GET action in the app has not been done.
- Contact messages have no owner model, so agents see all of them.
- Customers are unscoped by design; this needs review if agents should only see their own customers.
- Staff can still edit package itineraries through the save form (product decision pending).
- Configuration secrets: production credentials live in `.env`; confirm hardcoded fallbacks were removed from `config/app.php` before deployment.
- No device or IP tracking, no encrypted-at-rest document storage (none exists yet).
- Session hardening (regeneration on login, idle timeout, secure cookie flags) not verified in this audit.

## 5. UX issues

- Admin shell improved this session (grouped collapsible sidebar, notification bell, profile page, spacing), but several pages still use inline styles and inconsistent button sizes.
- Quotation and booking modals have many actions; the booking modal was reduced to two buttons per request.
- Customer-facing experience is limited to the public site; there is no self-service area.
- No mobile-optimised operations views beyond basic responsive layout.

## 6. Database issues

- Migrations are tracked by `schema_migrations`; several earlier schema gaps were corrected (missing foreign keys and indexes, commission uniqueness per lead).
- Some legacy columns and mixed naming remain (for example `adults` vs `party_size` across tables, `package_name` vs `package_interest` history).
- No vendor, contract, fleet, guide-availability, payment, ledger or document tables exist yet.
- Quotation versioning uses a `version` column but no version history table.

## 7. Performance bottlenecks

- The shared layout runs several count and list queries on every admin page load (notification bell, nav badges). Acceptable at current volume; should be cached or consolidated as data grows.
- Dashboard runs many separate scalar queries rather than one aggregated query.
- Immediate email send spawns a background PHP process per email; a proper queue worker would be more reliable.
- No caching layer (Redis or otherwise).

## 8. Scalability issues

- Single MySQL database, no read replicas, no queue service.
- Background processing relies on system cron jobs that must be configured manually on hosting.
- Deployment is manual (FTP/SSH and phpMyAdmin); there is no CI/CD pipeline.
- No Docker configuration.
- Session storage and file uploads are local to one server.

## 9. Revenue opportunities

- Online deposit and balance collection (card and mobile money) to shorten payment cycles.
- Agent self-service with custom pricing to scale channel sales.
- Quote analytics to focus sales effort on quotes most likely to convert.
- Automated departure and post-trip upsells (extensions, transfers, activities).
- Supplier rate management to improve margin visibility per package.

## 10. Competitor comparison (general market knowledge)

Platforms: SafariPortal, TourPlan, TrekkSoft, TravelWorks, Tourwriter, Wetu, TourRadar, Viator, GetYourGuide, Airbnb Experiences.

- Operations and supplier depth (TourPlan, Wetu, Tourwriter): this system has far less supplier, fleet, guide and availability management.
- Consumer booking (Viator, GetYourGuide, TourRadar): this system has direct booking but no integrated payments, reviews sync or inventory-aware checkout.
- Agent channel (TourPlan, TrekkSoft): no B2B agent portal yet.
- Strengths relative to the category: a strong, tested itinerary-driven pricing engine, consistent PDF documents, and quotation-to-booking-to-invoice automation.

## 11. Module ratings (1–100, judgment)

| Module | Score | Notes |
|---|---|---|
| Public booking and pricing | 72 | Solid engine; no online payment |
| Itinerary and packages | 65 | Good data model; no drag-and-drop builder |
| Quotations | 70 | Strong PDFs and conversion; no versions UI, single currency |
| Bookings | 66 | Ownership now enforced; no payment gateway |
| Leads and CRM | 62 | Pipeline and commission work; no campaign or source analytics |
| Custom requests | 60 | Intake and linkage; no assignee field |
| Commissions and agents | 58 | Approval workflow; no agent portal |
| Invoices and payments | 45 | Invoice PDF and email; no ledger, no gateways |
| Operations (guides, drivers, rooms, incidents) | 40 | Roster and incidents only; no assignment engine |
| Fleet | 25 | Vehicle rates only |
| Suppliers and contracts | 25 | Table exists; no contracts or payables |
| Financial reporting | 15 | Not built |
| Business intelligence | 25 | Basic dashboard counts |
| Customer portal | 5 | Not built |
| Agent portal | 5 | Not built |
| Documents vault | 5 | Not built |
| Reviews | 40 | Submission and admin notice; no external sync |
| Security (RBAC, CSRF, audit) | 60 | Major gaps closed this session; sweep incomplete |
| DevOps and deployment | 25 | Manual deploy, no CI/CD or containers |
| Overall | 45 | Strong core workflow; large feature surface still missing |

## 12. Recommended order (systematic path)

1. Finish security sweep of every action (route by route).
2. Quote status and view analytics (Phase 4 subset).
3. Profitability per package (Phase 5) and operations command centre (Phase 6), read-only first.
4. Fleet and guide registries with availability (Phases 7, 8, 10).
5. Supplier contracts and payables (Phase 9).
6. Financial reporting (Phase 15) and BI (Phase 16).
7. Payments and WhatsApp (Phases 13, 14) — blocked on merchant accounts and Meta business verification.
8. Customer and agent portals (Phases 11, 12).
9. Document vault (Phase 18) and reviews sync (Phase 19).
10. UI rebuild and DevOps (Phases 20, 22, 23) — requires a decision on the stack (see below).

Blocking decisions: (a) keep the PHP admin and improve it, or rebuild in Next.js; (b) which payment providers you hold accounts with; (c) whether you have WhatsApp Business API access.
