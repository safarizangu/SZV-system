# Safari Zangu Ventures — System Documentation

**Version:** 1.1 — October 2026  
**Stack:** PHP 8.3 · MySQL 5.7 · Apache · Hostinger  
**Live site:** [safarizanguven.com](https://safarizanguven.com)  
**Admin panel:** [safarizanguven.com/admin](https://safarizanguven.com/admin)

---

## What This System Is

Safari Zangu Ventures is a full-stack Tanzania safari booking and operations platform built from scratch — no framework, no page builder, no off-the-shelf booking software. Every line is custom PHP 8.3 with a PSR-4 MVC architecture running on a single Apache/MySQL Hostinger instance.

The system covers everything an Arusha-based private safari operator needs to run the business: the public website guests see, the admin panel the team uses daily, the agent and commission pipeline, the email automation engine, and the marketing tools.

---

## Full Documentation

This README is a tour; the detailed, continuously-maintained reference
docs live in [`docs/`](docs/):

| Document | Covers |
|---|---|
| [docs/SYSTEM_ARCHITECTURE.md](docs/SYSTEM_ARCHITECTURE.md) | Request lifecycle, service layer, PDF/email pipelines, known gaps |
| [docs/DATABASE_DESIGN.md](docs/DATABASE_DESIGN.md) | All 32 tables, as introspected from the live schema |
| [docs/API_SPECIFICATION.md](docs/API_SPECIFICATION.md) | Every `api/` endpoint — method, auth, purpose |
| [docs/SECURITY_ARCHITECTURE.md](docs/SECURITY_ARCHITECTURE.md) | RBAC, the ownership-scoping model, CSRF, rate limiting |
| [docs/DEV_SETUP.md](docs/DEV_SETUP.md) | Local environment setup (this machine's quirks included) |
| [docs/DEPLOYMENT_GUIDE.md](docs/DEPLOYMENT_GUIDE.md) | Hostinger production deploy checklist |
| [docs/SECURITY_CHECKLIST.md](docs/SECURITY_CHECKLIST.md) | Pre-deploy security checklist |
| [ADMIN_GUIDE.md](ADMIN_GUIDE.md) | End-user guide, served in-app at `admin/guide.php` |
| [docs/reports/](docs/reports/) | Historical audit reports and specs from past review cycles |

---

## Table of Contents

1. [Folder Structure](#1-folder-structure)
2. [Technology Stack](#2-technology-stack)
3. [Public Website](#3-public-website)
4. [Admin Panel](#4-admin-panel)
5. [Database Schema](#5-database-schema)
6. [Email System](#6-email-system)
7. [Agent & Commission System](#7-agent--commission-system)
8. [Security Architecture](#8-security-architecture)
9. [API Endpoints](#9-api-endpoints)
10. [Cron Jobs](#10-cron-jobs)
11. [Poster Maker](#11-poster-maker)
12. [Configuration & Environment](#12-configuration--environment)
13. [Local Development Setup](#13-local-development-setup)
14. [Production Deployment](#14-production-deployment)
15. [How a Booking Flows End-to-End](#15-how-a-booking-flows-end-to-end)
16. [How a Quotation Flows End-to-End](#16-how-a-quotation-flows-end-to-end)
17. [How the Agent Commission System Works](#17-how-the-agent-commission-system-works)
18. [Maintenance & Monitoring](#18-maintenance--monitoring)
19. [Testing](#19-testing)

---

## 1. Folder Structure

```
/                               ← Apache document root — stays minimal
├── .env                        ← secrets (DB, SMTP, PayPal) — never committed
├── .env.example                ← secrets template — committed to git
├── .htaccess                   ← Apache routing, security headers, rewrites
├── .gitignore
├── robots.txt                  ← search engine directives
├── sitemap.xml                 ← SEO sitemap
├── README.md                   ← this file
├── ADMIN_GUIDE.md              ← served in-app at admin/guide.php
│
├── docs/                       ← full reference docs — see "Full Documentation" above
│   └── reports/                ← historical audit reports/specs
│
├── public/                     ← all public-facing pages (URL entry points)
│   ├── index.php / about.php / packages.php / book.php / contact.php
│   ├── track.php / confirmation.php / terms.php / privacy-policy.php
│   ├── sustainability.php / review.php
│   └── 404.php / 500.php
│
├── admin/                      ← ~37 pages, admin panel (self-contained, auth-gated)
│   ├── login.php / logout.php / change_password.php / profile.php
│   ├── index.php               ← dashboard
│   ├── bookings.php / quotations.php / quotation_new.php / invoices.php
│   ├── leads.php / commissions.php / customers.php / customer_view.php
│   ├── packages.php / package_edit.php / itineraries.php
│   ├── vehicles.php / staff.php / suppliers.php / park_fees.php
│   ├── accommodations.php / seasonal_rates.php / activities.php
│   ├── operations_calendar.php / room_availability.php
│   ├── emergency_contacts.php / incidents.php
│   ├── dashboard_sales.php / dashboard_operations.php / dashboard_fleet.php
│   ├── dashboard_profitability.php / dashboard_customer_experience.php
│   ├── dashboard_agents.php / analytics.php
│   ├── email_queue.php / email_templates.php / reminders.php
│   ├── contact_messages.php / custom_requests.php
│   ├── gallery.php / poster_maker.php / team_members.php / guide.php
│   ├── users.php / currency_rates.php / activity_log.php / reviews.php
│   ├── css/admin.css           ← admin design system
│   └── js/                     ← admin JavaScript
│
├── app/                        ← PHP core, PSR-4 autoloaded — see docs/SYSTEM_ARCHITECTURE.md
│   ├── bootstrap.php           ← autoloader, session, DB init
│   ├── Models/                 ← Database, Booking, Customer, CustomSafariRequest, Package
│   ├── Mail/                   ← EmailTemplate, Mailer
│   ├── Middleware/              ← AuthMiddleware, CsrfMiddleware, RateLimiter, Honeypot
│   ├── Helpers/                 ← Formatter, AdminIcon, Env, ImageOptimizer, ItineraryDayCards, TripExtensions
│   ├── Services/                ← ItineraryQuoteBuilder, PdfRenderer, OwnershipScope, AccessGuard,
│   │                               PipelineSync, InvoiceMailer, PayPalClient, AccommodationAvailability,
│   │                               BookingReminderScheduler, ActivityLog
│   └── Validators/             ← BookingValidator
│
├── api/                        ← ~28 endpoints — full reference in docs/API_SPECIFICATION.md
│   ├── book.php / contact.php / custom_safari.php / review_submit.php
│   ├── track_lookup.php / check_booking.php / track_booking_pdf.php
│   ├── paypal_create_order.php / record_payment.php
│   ├── itinerary.php / convert_quotation.php
│   ├── quotation_email.php / quotation_pdf.php / quotation_itinerary_pdf.php / quote_view.php
│   ├── booking_email.php / booking_pdf.php / booking_itinerary_pdf.php
│   ├── voucher_pdf.php / voucher_email.php / manifest_pdf.php
│   ├── generate_caption.php / set_hero.php / vehicle_manage.php / upload_team_image.php
│   ├── dashboard_stats.php / analytics.php / get_setting.php
│   ├── gallery/upload.php / gallery/delete.php / gallery/list.php
│   └── cron/                   ← CLI-only, blocked from web (403)
│       ├── process_email_queue.php / process_reminders.php / send_single_email.php
│
├── views/                      ← HTML templates (included by public/)
│   └── home.php / about.php / packages.php / book.php / contact.php / track.php / confirmation.php / review.php
│
├── layouts/                    ← shared partials
│   └── app.php / admin.php / head.php / navbar.php / footer.php
│
├── assets/                     ← static files served directly (css/, js/, images/)
│
├── config/
│   └── app.php                 ← all constants, env loader, business rules
│
├── cron/
│   └── backup.sh               ← daily mysqldump, 30-day retention
│
├── sql/                        ← dated migration files (web-blocked), tracked via schema_migrations
│
├── tests/                      ← dependency-free test suite (no PHPUnit needed)
│   ├── run.php                 ← test runner — `php tests/run.php`
│   └── suites/                 ← one file per subject
│
└── storage/                    ← runtime writable storage (web-blocked)
    ├── cache/
    ├── logs/
    └── uploads/
```

---

## 2. Technology Stack

| Layer | Technology | Notes |
|---|---|---|
| Language | PHP 8.3 | Typed properties, match expressions, nullsafe operator |
| Database | MySQL 5.7 | No `IF NOT EXISTS` on ALTER — run each migration separately |
| Web server | Apache 2.4 | mod_rewrite, mod_headers, mod_expires |
| Hosting | Hostinger | Shared hosting, Ubuntu, cPanel |
| Email | PHPMailer + Hostinger SMTP | Queue-based via `email_queue` table |
| PDF | dompdf (Composer) | Every `api/*_pdf.php` file's own HTML *is* the document — `App\Services\PdfRenderer` captures it and runs it through dompdf for email attachments |
| Payments | PayPal (sandbox → live) | Stripe/Flutterwave planned Phase 1 |
| AI | Anthropic Claude API | Poster caption generation (claude-sonnet-4-6) |
| Frontend | Vanilla JS + Tailwind-free custom CSS | No framework, no build step |
| Fonts | Playfair Display + Jost | Via Google Fonts |
| Architecture | Custom PSR-4 MVC | No Laravel, no Symfony |
| Session | PHP native sessions | SameSite=Strict, file-based lockout |
| Security | Custom middleware stack | 22 hardening fixes applied |

---

## 3. Public Website

### How pages render

Every public page follows the same pattern:

```
public/about.php
  → require_once '../app/bootstrap.php'   (loads config, session, autoloader, DB)
  → sets $pageTitle, $pageDescription     (for <head>)
  → ob_start()
  → include '../views/about.php'          (the actual HTML content)
  → $content = ob_get_clean()
  → include '../layouts/app.php'          (wraps content in nav + footer)
```

The `.htaccess` rewrite rule makes `safarizanguven.com/about.php` transparently serve `public/about.php` — the `/public/` directory is never visible in the URL.

### Pages

| URL | File | Purpose |
|---|---|---|
| `/` | `public/index.php` | Hero, package cards, trust strip, testimonials, CTA |
| `/about.php` | `public/about.php` | Team bios, guide profiles, story, licensing |
| `/packages.php` | `public/packages.php` | All 5 packages with itinerary, gallery, pricing |
| `/book.php` | `public/book.php` | 3-step booking form with CSRF protection |
| `/contact.php` | `public/contact.php` | Contact form, map, WhatsApp, response times |
| `/track.php` | `public/track.php` | Booking lookup by ref + date (rate-limited) |
| `/confirmation.php` | `public/confirmation.php` | Post-booking success page |
| `/terms.php` | `public/terms.php` | Full booking terms |
| `/privacy-policy.php` | `public/privacy-policy.php` | GDPR data policy |

### Design system

The design is editorial luxury — black/white with warm gold accent (`#c8963e`). Fonts are Playfair Display (display/headings) and Jost (body/UI). No framework — everything is custom CSS in `assets/css/main.css`.

Gold appears consistently on: section labels, divider lines, active states, form focus rings, navigation CTA, package card numbers, team role labels, itinerary day numbers, and hover states. Black/white handles everything structural.

---

## 4. Admin Panel

### Authentication

Login at `/admin/login.php`. Protected by:
- bcrypt password hashing (cost 12)
- Server-side file-based lockout — 5 failed attempts locks the IP for 15 minutes, stored in `/tmp/szv_ratelimit/login_{hash}.json`. Cannot be bypassed by opening a new session.
- `$_SESSION['admin_authenticated'] = true` on success
- `$_SESSION['admin_role']` stores the user's role for permission checks

### Roles

| Role | Permissions |
|---|---|
| `superadmin` | Full access. Currently identical to `admin` in every permission check — deliberate, not yet differentiated. |
| `admin` | Full access to all admin pages and all records. |
| `agent` | Only sees their own leads, bookings, quotations, and custom requests. Bookings/custom-requests have no owner column — ownership is derived through a linked quotation's `created_by`. Cannot set their own `agent_id` or `commission_rate`. |
| `staff` | Full page access minus Settings; see [docs/SECURITY_ARCHITECTURE.md](docs/SECURITY_ARCHITECTURE.md) for the one open question around itinerary editing. |

Full detail, including the ownership-derivation pattern and every
hardening fix, is in
[docs/SECURITY_ARCHITECTURE.md](docs/SECURITY_ARCHITECTURE.md).

### Admin pages

**Dashboard** (`admin/index.php`) — live stats: total bookings, monthly revenue, active leads, reminders due today, recent booking feed.

**Bookings** (`admin/bookings.php`) — full table with search/filter, status dropdown (pending → deposit → paid → confirmed → cancelled), modal detail view.

**Quotations** (`admin/quotations.php`) — create/edit quotations with line items, send by email, generate PDF, convert to booking. When status changes to `accepted` → emails the client automatically. When status changes to `expired` → emails the client automatically.

**Quotation Builder** (`admin/quotation_new.php`) — select an itinerary, dates, pax count, accommodation level → the `api/itinerary.php` endpoint auto-generates a full line-item breakdown (accommodation per lodge, vehicle per day, park fees per park per guest, activities, meals, service fee). Each line item is editable before sending.

**Packages** (`admin/packages.php` / `admin/package_edit.php`) — manage the 5 packages, edit per-day itineraries, upload gallery images, set pricing tiers (1/2/3+ pax), includes/excludes lists.

**Leads Pipeline** (`admin/leads.php`) — 8-stage kanban board (Lead → Qualified → Quoted → Negotiation → Booked → Travelled → Completed → Lost). Agents see only their own leads. Admins see all, can assign to any agent. Quick stage-move buttons on each card. List view with search and stage filter.

**Commissions** (`admin/commissions.php`) — agent performance cards showing total leads, conversions, pending commission, paid commission. Commission rate editor per agent. Status workflow: pending → approved → paid. Each status change emails the agent.

**Users** (`admin/users.php`) — create/edit team members and agents. Set role, commission rate, phone, bio, monthly target.

**Gallery** (`admin/gallery.php`) — folder tree by package, drag-drop image upload, delete with path traversal protection, lightbox preview.

**Email Queue** (`admin/email_queue.php`) — live view of all outbound emails, status (pending/sent/failed), retry failed emails, purge old records.

**Email Templates** (`admin/email_templates.php`) — visual preview of all 15 email templates. Mobile/desktop toggle, full-screen modal, copy PHP `Mailer::queue()` code snippet.

**Reminders** (`admin/reminders.php`) — upcoming and sent booking reminders, type badges, retry option.

**Contact Messages** (`admin/contact_messages.php`) — all contact form submissions with full message body, WhatsApp quick-reply, email link, mark as read, delete.

**Analytics** (`admin/analytics.php`) — revenue by month, booking count trends, package popularity, lead source breakdown.

**Poster Maker** (`admin/poster_maker.php`) — full social media poster studio. 9 templates, 8 colour themes, 4 canvas sizes (1:1/4:5/9:16/16:9), AI caption generation via Anthropic API, hashtag chips, PNG/JPG download.

---

## 5. Database Schema

32 tables plus 1 view. The core transactional ones:

- **`customers`** — the unified identity behind bookings, quotations,
  leads, and custom safari requests, deduplicated on a lowercased/trimmed
  `email` via `App\Models\Customer::findOrCreateByEmail()`.
- **`bookings`** — the source of truth for confirmed bookings.
  `total_amount` is the authoritative, frozen charged figure.
- **`quotations`** — pre-booking proposals; snapshots its own itinerary
  and line items as JSON so it can regenerate its PDF without depending
  on catalog data that might change later. No owner column — an agent's
  ownership is derived through `created_by`.
- **`leads`** — the agent sales pipeline (`agent_id` *is* a real column
  here, unlike bookings/quotations).
- **`commissions`** — one row per lead (unique constraint on `lead_id`),
  created by `App\Services\PipelineSync`.
- **`custom_safari_requests`** — bespoke enquiries; also has no owner
  column, scoped the same way quotations are.
- Catalog: `packages`, `itineraries`, `accommodations` (+
  `accommodation_seasonal_rates`), `vehicles`, `park_fees`, `activities`,
  `seasonal_rates`, `suppliers`.
- Operations: `staff`, `booking_assignments`, `incidents`.
- System: `email_queue`, `email_templates`, `booking_reminders`,
  `contact_messages`, `activity_log`, `analytics_events`, `settings`,
  `currency_rates`, `schema_migrations`, `reviews`.
- People: `admin_users`, `agent_targets`.

Full column-by-column reference, generated by introspecting the live
schema rather than written from memory:
**[docs/DATABASE_DESIGN.md](docs/DATABASE_DESIGN.md)**.

---

## 6. Email System

### Architecture

Emails never send synchronously during a web request. Every email is inserted into the `email_queue` table and sent by a cron job within 2 minutes.

```
Web request
  → Mailer::queue('template_name', $vars)
      → EmailTemplate::render('template_name', $vars)
          → returns ['html', 'text', 'subject']
      → INSERT INTO email_queue (to_email, subject, body, template, status='pending')

Cron (every 2 min)
  → process_email_queue.php
      → SELECT pending emails
      → PHPMailer::send() via Hostinger SMTP
      → UPDATE status = 'sent' / 'failed'
      → retry up to 3 times on failure
```

### The 15 email templates

| Template | Sent to | Triggered by |
|---|---|---|
| `booking_confirmation` | Guest | `api/book.php` on new booking |
| `booking_reminder_deposit` | Guest | Cron — 48h after booking |
| `booking_reminder_balance` | Guest | Cron — 45 days before travel |
| `booking_reminder_departure` | Guest | Cron — 7 days before travel |
| `booking_reminder_posttrip` | Guest | Cron — 3 days after return |
| `quotation_sent` | Guest | listed here for completeness, but `api/quotation_email.php` actually builds its own body inline and never calls this template — editing it in `admin/email_templates.php` has no effect; see [docs/SYSTEM_ARCHITECTURE.md §6](docs/SYSTEM_ARCHITECTURE.md#6-email-system) |
| `quotation_accepted` | Guest | `admin/quotations.php` on accept |
| `quotation_expired` | Guest | `admin/quotations.php` on expire |
| `contact_acknowledgement` | Guest | `api/contact.php` auto-reply |
| `contact_admin_notification` | Admin | `api/contact.php` notify admin |
| `custom_request_ack` | Guest | `api/custom_safari.php` auto-reply |
| `custom_request_admin` | Admin | `api/custom_safari.php` notify admin |
| `lead_converted` | Agent | `App\Services\PipelineSync` on stage → Booked |
| `commission_approved` | Agent | `admin/commissions.php` on approve |
| `commission_paid` | Agent | `admin/commissions.php` on paid |

### Sending an email from PHP

```php
use App\Mail\Mailer;

// Single email
Mailer::queue('booking_confirmation', [
    'to_email'       => $booking['email'],
    'to_name'        => $booking['full_name'],
    'client_name'    => $booking['full_name'],
    'booking_ref'    => $ref,
    'package_name'   => $booking['package_name'],
    'travel_date'    => $booking['travel_date'],
    'total_amount'   => $booking['total_amount'],
    'deposit_amount' => $booking['deposit_amount'],
]);

// Both guest and admin in one call
Mailer::queueBoth(
    'contact_acknowledgement', $guestVars,
    'contact_admin_notification', $adminVars
);

// Admin only
Mailer::queueAdmin('custom_request_admin', $vars);
```

---

## 7. Agent & Commission System

### How roles work in practice

When an agent logs in, `$_SESSION['admin_role']` is set to `'agent'`. `admin/leads.php` applies a `WHERE agent_id = $myId` filter to every query, and `App\Services\OwnershipScope` applies the equivalent scoping to bookings, quotations, and custom requests (which have no `agent_id` column of their own — ownership there is derived through a linked quotation). All of this is enforced server-side; the client cannot override it. Agents cannot set their own `agent_id` or `commission_rate` in POST requests (both are ignored and set server-side). Full detail: [docs/SECURITY_ARCHITECTURE.md](docs/SECURITY_ARCHITECTURE.md).

### Commission lifecycle

```
1. Admin creates agent account in admin/users.php
   → sets role='agent', commission_rate=10 (%)

2. Agent logs in → sees only their leads
   → adds a new lead with client details, package interest, budget

3. Lead moves through stages: Lead → Qualified → Quoted → Negotiation

4. Agent moves lead to 'Booked':
   → admin sets actual booking_value via "$ Set Value" button
   → commission_amount = booking_value × commission_rate / 100
   → Commission record auto-created in commissions table (status='pending')
   → Email 'lead_converted' fires to agent with commission amount

5. Admin reviews → changes commission status to 'approved'
   → Email 'commission_approved' fires to agent

6. Admin pays the agent → changes status to 'paid'
   → paid_at timestamp recorded
   → Email 'commission_paid' fires to agent
```

### Agent performance dashboard

`admin/commissions.php` shows a card per agent with:
- Total leads in pipeline
- Converted bookings count
- Pending commission (not yet paid)
- Total commission earned
- Paid commission total
- Rate editor (admin can change per-agent rate)

---

## 8. Security Architecture

> The RBAC model, the ownership-scoping pattern for agents, and denial
> logging are documented in full in
> [docs/SECURITY_ARCHITECTURE.md](docs/SECURITY_ARCHITECTURE.md) — this
> section covers the lower-level hardening fixes.

### What was hardened

| Vulnerability | Fix |
|---|---|
| IP spoofing via X-Forwarded-For | `REMOTE_ADDR` only in `RateLimiter.php` |
| Session-based login lockout (bypassable) | File-based lockout in `/tmp/szv_ratelimit/` |
| ADMIN_PIN plaintext fallback | `bin2hex(random_bytes(32))` — effectively disabled |
| Open redirect on login `?redirect=` | Strict allowlist of known admin pages |
| Path traversal in gallery upload | `realpath()` check — path must be inside `assets/images/` |
| Gallery delete trusts client path | Extension whitelist + boundary check |
| Agent ID forgery via POST | `agent_id` forced to `$myId` for agents server-side |
| Agent self-assigning commission rate | Rate only settable by admin |
| Email header injection in contact.php | Newlines stripped from all header values |
| SQL directory web-accessible | `.htaccess` blocks `^sql/`, `^app/`, `^config/` |
| Quotation PDF unauthenticated | Admin session required |
| Session SameSite=Lax | Changed to `Strict` |
| PHP errors in production | `display_errors=0` when `APP_ENV=production` |
| Predictable booking refs | `random_bytes(5)` via `Formatter::generateRef()` |
| Weak password policy | 10 chars min + uppercase + number |
| Username enumeration on login | No prefill on failure, generic error message |
| Missing security headers | X-Frame-Options, X-Content-Type-Options, Permissions-Policy, COEP, CORP |
| No CSRF on public forms | CsrfMiddleware token validation |
| cron endpoints web-accessible | `api/cron/.htaccess` blocks all HTTP access |
| Sensitive directories web-accessible | `app/`, `config/`, `storage/`, `sql/` all return 403 |
| `.git` directory web-accessible | Blocked via `.htaccess` redirect |
| PayPal payment status/amount trusted from client | `App\Services\PayPalClient` verifies the order server-side (OAuth2 + `/v2/checkout/orders/{id}`) before any booking is marked paid — see [9. API Endpoints](#9-api-endpoints) |
| `.bak` / dev script source disclosure | `.htaccess` blocks `.bak`, `.py`, `.sh`, `.sql`, `.log` extensions; stray backup files also removed from the repo |

### Rate limiting

`App\Middleware\RateLimiter` — file-based, stored in `/tmp/szv_ratelimit/`. Used on:
- Login: 5 attempts per 15 minutes per IP
- Track booking: 3 failed lookups per 15 minutes per IP
- Public forms: 5 requests per hour per IP

### Session hardening in `app/bootstrap.php`

```php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => true,       // HTTPS only on production
    'httponly' => true,       // no JavaScript access
    'samesite' => 'Strict',   // no cross-origin requests
]);
```

---

## 9. API Endpoints

~28 endpoints live under `api/`. Full method/auth/purpose reference for
every one of them, kept in sync with the code rather than hand-copied
here: **[docs/API_SPECIFICATION.md](docs/API_SPECIFICATION.md)**.

---

## 10. Cron Jobs

Installed under `www-data` user (`sudo crontab -u www-data -e`):

```cron
# Send pending emails (every 2 minutes)
*/2 * * * * /usr/bin/php8.3 /var/www/html/safari-zangu/api/cron/process_email_queue.php >> /var/log/szv_email.log 2>&1

# Fire booking reminders (every hour)
0 * * * * /usr/bin/php8.3 /var/www/html/safari-zangu/api/cron/process_reminders.php >> /var/log/szv_reminders.log 2>&1

# Daily database backup at 2am (30-day retention)
0 2 * * * /var/www/html/safari-zangu/cron/backup.sh >> /var/log/szv_backup.log 2>&1
```

### How reminders are scheduled

When a booking is created, `api/book.php` inserts 4 rows into `booking_reminders`:

| Type | Scheduled at |
|---|---|
| `deposit_due` | `created_at + 48 hours` |
| `balance_due` | `travel_date - 45 days` |
| `departure` | `travel_date - 7 days` |
| `post_trip` | `travel_date + 3 days` |

The hourly cron selects reminders where `scheduled_at <= NOW()` and `status = 'pending'`, renders the appropriate email template, queues it, and marks the reminder as sent.

---

## 11. Poster Maker

`admin/poster_maker.php` is a full social media poster studio powered by the HTML5 Canvas API and the Anthropic Claude API.

### Templates (9)
Hero · Split · Mosaic · Minimal · Itinerary · Pricing · Story · Feature · Dark Full

### Colour themes (8)
Midnight · Forest · Savannah · Night Sky · Dust Red · Sand · Violet Night · White

Each theme has a background, text colour, sub-text colour, and overlay colour. A separate accent colour (default: gold `#c8963e`) can be changed via colour picker or presets.

### Canvas sizes (4)
1:1 Square (1080×1080) · 4:5 Portrait (1080×1350) · 9:16 Story (1080×1920) · 16:9 Landscape (1920×1080)

### AI caption generation

Clicking "✦ AI Caption" calls the Anthropic API with a prompt built from the selected package's name, duration, price, accommodation level, day-by-day itinerary, and inclusions. Four tone presets:

- **Premium** — aspirational, sensory, 160–220 words
- **Urgent** — FOMO, limited availability, 100–140 words
- **Story** — personal narrative, first-person, 150–200 words
- **Minimal** — sparse, sophisticated, 40–70 words

Output is editable before copying.

### Export
PNG download · JPG download · Copy to Clipboard (where browser supports it)

---

## 12. Configuration & Environment

### `config/app.php`

All constants are defined here and loaded from `.env` via the `loadEnv()` and `env()` helpers. The file itself contains no live secrets — only local dev fallbacks.

Key constants:

```php
APP_ENV          // 'local' or 'production'
BASE_PATH        // '' on production; '/safari-zangu' on local (this machine has no
                 // Apache Alias for the project, so it's served as a real subfolder —
                 // see docs/DEV_SETUP.md)
DB_HOST/USER/PASS/NAME
SMTP_HOST/PORT/SECURE/USERNAME/PASSWORD
PAYPAL_MODE      // 'sandbox' or 'live'
PAYPAL_CLIENT_ID
PAYPAL_CLIENT_SECRET  // required for server-side order verification — see PayPalClient
BANK_NAME / BANK_ACCOUNT_NAME / BANK_ACCOUNT_NO / BANK_SWIFT
GA4_ID           // Google Analytics 4 Measurement ID
ADMIN_PIN        // bin2hex(random_bytes(32)) — effectively disabled
DEPOSIT_PERCENT  // 25
BALANCE_DAYS     // 45
```

### `.env` file (create from `.env.example`)

```bash
cp .env.example .env
nano .env
```

Fill in: `DB_PASS`, `SMTP_PASSWORD`, `PAYPAL_MODE=live`, `PAYPAL_CLIENT_ID`, `BANK_ACCOUNT_NO`, `BANK_SWIFT`, `GA4_ID`.

---

## 13. Local Development Setup

Prerequisites: PHP 8.3, MySQL/MariaDB, Apache with `mod_rewrite`, on
Ubuntu. Both Apache and the database service are independent and neither
auto-starts on this machine — the most common "it doesn't load" cause is
one of the two simply not running.

Full step-by-step (clone, `.env`, schema load, permissions, this
machine's specific `BASE_PATH` quirk, and starting both services):
**[docs/DEV_SETUP.md](docs/DEV_SETUP.md)**.

---

## 14. Production Deployment (Hostinger)

Full step-by-step checklist (production `.env`, file upload, SQL
migrations, permissions, Composer, cron, HTTPS/HSTS, SPF/DKIM/DMARC):
**[docs/DEPLOYMENT_GUIDE.md](docs/DEPLOYMENT_GUIDE.md)**.

---

## 15. How a Booking Flows End-to-End

```
Guest visits safarizanguven.com/book.php
  → Fills in: name, email, phone, package, dates, travelers, accommodation level
  → CSRF token validated
  → Rate limiter checked (5/hour per IP)
  → Form submitted to api/book.php

api/book.php
  → BookingValidator validates all fields
  → Booking ref generated: SZV + bin2hex(random_bytes(5))
  → INSERT into bookings (status='pending')
  → INSERT 4 booking_reminders (deposit/balance/departure/posttrip)
  → Mailer::queue('booking_confirmation', $vars)  ← guest email queued
  → Returns JSON { success: true, booking_ref: 'SZVABC123' }

Browser
  → Redirects to /confirmation.php?ref=SZVABC123

Cron (within 2 minutes)
  → process_email_queue.php sends 'booking_confirmation' email to guest

Admin sees
  → New booking in admin/bookings.php
  → Manually confirms dates with lodges/vehicles
  → Updates payment_status to 'deposit' when transfer received
  → Updates to 'confirmed' when fully paid

Cron (automated)
  → 48h after booking: sends 'booking_reminder_deposit'
  → 45 days before travel: sends 'booking_reminder_balance'
  → 7 days before travel: sends 'booking_reminder_departure' (with packing checklist)
  → 3 days after travel: sends 'booking_reminder_posttrip' (with review request)
```

---

## 16. How a Quotation Flows End-to-End

```
Admin receives enquiry (from contact form, WhatsApp, or leads pipeline)
  → Opens admin/quotation_new.php
  → Selects itinerary, dates, adults, children, accommodation level
  → api/itinerary.php generates line items:
      - Accommodation: per lodge, per night, per guest
      - Vehicle: per type, per day
      - Park fees: per park, per adult, per child
      - Activities: paid activities as line items; free ones in notes
      - Meals: consolidated total
      - Service fee: $100 per guest if not already included
  → Admin reviews, edits individual lines, applies discount if needed
  → Saves quotation (status='draft')
  → Clicks 'Send by Email'

api/quotation_email.php
  → Renders 'quotation_sent' template with full line items table
  → Queues to email_queue
  → Updates quotation status to 'sent'

Guest receives email
  → Full cost breakdown in branded HTML email
  → WhatsApp button + email reply button to accept
  → Quotation valid for 14 days

Guest accepts
  → Admin opens quotation in admin/quotations.php
  → Changes status to 'accepted'
  → System queues 'quotation_accepted' email to guest (next steps + deposit info)
  → Admin manually converts to booking via 'Convert to Booking' action
  → System creates booking record, queues booking_confirmation email

If guest doesn't respond
  → Admin changes status to 'expired'
  → System queues 'quotation_expired' email inviting re-enquiry
```

---

## 17. How the Agent Commission System Works

```
Setup:
  Admin creates agent in admin/users.php
    → role = 'agent', commission_rate = 10 (meaning 10%)
    → Agent receives login credentials

Agent workflow:
  Agent logs in → lands on admin/leads.php (sees only own leads)
  → Clicks '+ Add Lead' → fills in client details, package interest, budget
  → Moves lead through kanban stages as conversations progress

Conversion:
  Agent moves lead to 'Booked' stage in kanban or dropdown
  → System records stage change in leads.pipeline_stage
  → Admin enters actual booking_value (e.g. $3,500)
  → commission_amount = 3500 × 10 / 100 = $350
  → Commission record created in commissions table (status='pending')
  → Agent receives 'lead_converted' email: "Your lead John Smith converted.
     Commission: $350 (10% of $3,500 booking)"

Admin approval:
  → admin/commissions.php shows pending commissions
  → Admin changes status to 'approved'
  → Agent receives 'commission_approved' email

Payment:
  → Admin pays agent (bank transfer)
  → Changes commission status to 'paid' in admin panel
  → paid_at timestamp recorded
  → Agent receives 'commission_paid' email with amount and date
```

---

## 18. Maintenance & Monitoring

### Daily checks

```bash
# Check email queue is processing
sudo tail -20 /var/log/szv_email.log

# Check reminders are firing
sudo tail -20 /var/log/szv_reminders.log

# Check backup ran
sudo tail -5 /var/log/szv_backup.log

# Check for PHP errors
sudo tail -20 /var/log/apache2/error.log
```

### Database backup

The `cron/backup.sh` script runs nightly at 2am, creates a compressed `.sql.gz` backup in `/var/backups/szv_db/`, and deletes backups older than 30 days automatically.

To restore a backup:

```bash
gunzip -c /var/backups/szv_db/safari_zangu_2026-08-30_0200.sql.gz | \
  mysql -u root -p safari_zangu
```

### Adding a new package

1. Insert into `packages` table via phpMyAdmin or `admin/packages.php`
2. Create matching itinerary in `admin/itineraries.php`
3. Link itinerary: `UPDATE packages SET itinerary_id=X WHERE slug='new-package'`
4. Upload images to `assets/images/packages/new-package/`
5. Add package-specific hashtags to `admin/poster_maker.php` hashtag array

### Adding a new agent

1. `admin/users.php` → Create User → set `role=agent`, `commission_rate=10`
2. Give them their login credentials
3. Log out and back in to refresh your own session if you updated your own role

### Updating email templates

All 15 templates are in `app/Mail/EmailTemplate.php`. Each template is a private method `tpl_templatename(array $v): array`. The method returns `['subject', 'title', 'preview', 'html', 'text']`. Preview all templates at `admin/email_templates.php`.

---

## 19. Testing

There's no PHPUnit dependency — the Hostinger deploy target has no Composer dev-install step, so `tests/run.php` is a small self-contained runner with its own `test()`/`assertSame()`/`assertTrue()`/`assertFalse()` helpers.

```bash
php tests/run.php
```

Each file in `tests/suites/` covers one class and needs no database: `FormatterTest.php`, `BookingValidatorTest.php`, `SeasonDetectionTest.php` (the pure `ItineraryQuoteBuilder::detectSeason()` logic), and `RateLimiterTest.php` (against the real `/tmp` file store, using a random per-run action name so runs don't collide). `ItineraryQuoteBuilder::build()` itself isn't covered here because it requires a live DB connection for its rate lookups (season multiplier, resident/child park fees, single supplement) — exercise it via `admin/quotation_new.php` or `api/book.php` locally instead.

To add a suite: drop a new `tests/suites/YourThing.php` file that calls `test('description', fn() => ...)` — `tests/run.php` picks it up automatically, no registration needed.

---

## Licence & Ownership

This system is proprietary software belonging to **Safari Zangu Ventures Ltd**, Arusha, Tanzania. All rights reserved.

- Registration No. 179228971
- TALA Licence No. 037129

---

*Last updated: 16 October 2026 — folder structure, stack, database schema, security model, and API reference sections corrected against the live codebase; full technical reference split out into [docs/](docs/).*