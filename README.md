# Safari Zangu Ventures — System Documentation

**Version:** 1.0 — August 2026  
**Stack:** PHP 8.3 · MySQL 5.7 · Apache · Hostinger  
**Live site:** [safarizanguven.com](https://safarizanguven.com)  
**Admin panel:** [safarizanguven.com/admin](https://safarizanguven.com/admin)

---

## What This System Is

Safari Zangu Ventures is a full-stack Tanzania safari booking and operations platform built from scratch — no framework, no page builder, no off-the-shelf booking software. Every line is custom PHP 8.3 with a PSR-4 MVC architecture running on a single Apache/MySQL Hostinger instance.

The system covers everything an Arusha-based private safari operator needs to run the business: the public website guests see, the admin panel the team uses daily, the agent and commission pipeline, the email automation engine, and the marketing tools.

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
│
├── public/                     ← all public-facing pages (URL entry points)
│   ├── index.php               ← home page
│   ├── about.php               ← team, story, guides, licensing
│   ├── packages.php            ← all 5 safari packages
│   ├── book.php                ← booking form
│   ├── contact.php             ← contact form + office map
│   ├── track.php               ← booking tracker (no login)
│   ├── confirmation.php        ← post-booking confirmation page
│   ├── terms.php               ← booking terms & conditions
│   ├── privacy-policy.php      ← GDPR privacy policy
│   ├── sustainability.php      ← environmental commitment
│   ├── 404.php                 ← custom error page
│   └── 500.php                 ← custom server error page
│
├── admin/                      ← admin panel (self-contained, auth-gated)
│   ├── login.php               ← bcrypt login, server-side lockout
│   ├── logout.php / change_password.php
│   ├── index.php               ← dashboard with live stats
│   ├── bookings.php            ← all bookings, status management
│   ├── quotations.php          ← quotation list, email, PDF
│   ├── quotation_new.php       ← new quotation builder
│   ├── packages.php            ← package management
│   ├── package_edit.php        ← per-day itinerary builder
│   ├── itineraries.php         ← itinerary templates
│   ├── leads.php               ← 8-stage kanban lead pipeline
│   ├── commissions.php         ← agent commission tracking
│   ├── users.php               ← team and agent management
│   ├── gallery.php             ← image gallery manager
│   ├── email_queue.php         ← outbound email monitor
│   ├── email_templates.php     ← 15 template previews
│   ├── reminders.php           ← booking reminder monitor
│   ├── contact_messages.php    ← contact form inbox
│   ├── custom_requests.php     ← custom safari enquiries
│   ├── analytics.php           ← revenue and booking charts
│   ├── poster_maker.php        ← IG/social poster studio
│   ├── team_members.php        ← guide/staff bios and photos
│   ├── guide.php                ← per-guide profile editor
│   ├── vehicles.php             ← fleet inventory + quotation rates
│   ├── accommodations.php      ← lodge/camp rates by level
│   ├── park_fees.php           ← per-park adult/child entry fees
│   ├── seasonal_rates.php      ← peak/shoulder/low season multipliers
│   ├── activities.php          ← optional paid activity pricing
│   ├── css/admin.css           ← admin design system
│   └── js/                     ← admin JavaScript
│
├── app/                        ← PHP MVC core, PSR-4 autoloaded
│   ├── bootstrap.php           ← autoloader, session, DB init
│   ├── Models/
│   │   ├── Database.php        ← PDO singleton
│   │   ├── Booking.php
│   │   ├── Package.php
│   │   └── TeamMember.php
│   ├── Mail/
│   │   ├── EmailTemplate.php   ← 15-template engine
│   │   └── Mailer.php          ← queue-based sending
│   ├── Middleware/
│   │   ├── AuthMiddleware.php  ← session auth for admin
│   │   ├── RateLimiter.php     ← file-based IP rate limiting
│   │   └── CsrfMiddleware.php  ← CSRF token validation
│   ├── Helpers/
│   │   └── Formatter.php       ← booking refs, currency, dates
│   ├── Services/
│   │   ├── QuotationEngine.php ← itinerary → line items pricing (DB-backed rates)
│   │   └── PayPalClient.php    ← server-side PayPal order verification
│   └── Validators/
│       └── BookingValidator.php
│
├── api/                        ← AJAX endpoints and form handlers
│   ├── book.php                ← booking form submission
│   ├── contact.php             ← contact form (POST only)
│   ├── custom_safari.php       ← custom safari request handler
│   ├── convert_quotation.php   ← quotation → booking conversion
│   ├── itinerary.php           ← itinerary pricing API
│   ├── quote.php               ← ad-hoc QuotationEngine pricing API
│   ├── leads.php               ← leads CRUD (agent-scoped)
│   ├── track_lookup.php        ← booking lookup (rate-limited)
│   ├── check_booking.php       ← booking existence/status check
│   ├── record_payment.php      ← verifies + records a PayPal payment (auth: none, PayPal-verified)
│   ├── quotation_email.php     ← send quotation by email
│   ├── quotation_pdf.php       ← generate quotation PDF (auth)
│   ├── booking_email.php       ← resend a booking confirmation email
│   ├── booking_pdf.php         ← generate booking confirmation PDF
│   ├── itinerary_pdf.php       ← generate itinerary PDF
│   ├── analytics.php           ← dashboard stats endpoint
│   ├── dashboard_stats.php     ← admin dashboard summary tiles
│   ├── mailer.php              ← shared mail-send helper for API endpoints
│   ├── get_setting.php         ← read a single key from `settings` table
│   ├── set_hero.php            ← set homepage hero image (auth)
│   ├── vehicle_manage.php      ← fleet CRUD (auth)
│   ├── upload_team_image.php   ← team/guide photo upload (auth)
│   ├── gallery/
│   │   ├── upload.php          ← image upload (auth)
│   │   ├── delete.php          ← image delete (auth)
│   │   └── list.php            ← image list (auth)
│   └── cron/                   ← CLI-only, blocked from web (403)
│       ├── process_email_queue.php
│       └── process_reminders.php
│
├── views/                      ← HTML templates (included by public/)
│   ├── home.php
│   ├── about.php
│   ├── packages.php
│   ├── book.php
│   ├── contact.php
│   ├── track.php
│   └── confirmation.php
│
├── layouts/                    ← shared partials
│   ├── app.php                 ← public page wrapper (head + nav + footer)
│   ├── admin.php               ← admin page wrapper + sidebar
│   ├── head.php                ← <head> meta, fonts, CSS
│   ├── navbar.php              ← responsive navigation
│   └── footer.php              ← 4-column footer with credentials
│
├── assets/                     ← static files served directly
│   ├── css/
│   │   ├── main.css            ← full design system (1700+ lines)
│   │   └── forms.css           ← form-specific styles
│   ├── js/
│   │   ├── main.js             ← nav, animations, form behaviour
│   │   ├── analytics.js        ← GA4 event tracking
│   │   ├── form-validator.js   ← client-side validation
│   │   └── country-codes.js    ← phone flag selector
│   └── images/
│       └── packages/           ← per-package photography
│           ├── classic-safari/
│           ├── luxury-safari/
│           ├── comfort-safari/
│           ├── safari-zanzibar/
│           └── cultural-safari/
│
├── config/
│   └── app.php                 ← all constants, env loader, business rules
│
├── cron/
│   └── backup.sh               ← daily mysqldump, 30-day retention
│
├── database/
│   ├── migrations/             ← schema evolution scripts
│   └── seeders/                ← initial data (itineraries, packages)
│
├── sql/                        ← SQL migration files (web-blocked)
│
├── tests/                      ← dependency-free test suite (no PHPUnit needed)
│   ├── run.php                 ← test runner — `php tests/run.php`
│   └── suites/                 ← one file per subject (Formatter, BookingValidator, …)
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
| PDF | mPDF | Quotation PDF generation |
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
| `superadmin` | Full access to everything including user management |
| `admin` | Full access except creating other superadmins |
| `agent` | Only sees own leads, cannot see other agents' data |
| `staff` | Read-only access to bookings and packages |

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

### Core tables

**`customers`** — the unified identity behind bookings, quotations, leads, and custom safari requests. Before this table existed, the same guest's name/email/phone was copied independently into each of those four tables with no shared record — a repeat guest looked like several unrelated people. `App\Models\Customer::findOrCreateByEmail()` is called from every place a new record in those four tables is created; it matches on a lowercased, trimmed email, fills in a missing `phone`/`country` on the existing record but never overwrites a value that's already set, and creates a new row only when the email hasn't been seen before.

| Column | Purpose |
|---|---|
| `email` | the dedup key — always stored lowercase/trimmed |
| `full_name`, `phone`, `country` | best-known values across every touchpoint |
| `first_seen_at` | when this person first contacted us, across any channel |

**`bookings`** — the source of truth for all confirmed bookings.

| Column | Type | Purpose |
|---|---|---|
| `id` | int PK | |
| `booking_ref` | varchar(20) | `SZV` + `bin2hex(random_bytes(5))` — cryptographically random |
| `customer_id` | int | FK → customers, nullable |
| `quotation_id` | int | FK → the quotation this booking was converted from, if any |
| `full_name` | varchar(150) | |
| `email` | varchar(200) | |
| `phone` | varchar(50) | |
| `travelers` | int | total guests |
| `adults` | int | |
| `children` | int | |
| `package_id` | int | FK → packages, nullable |
| `itinerary_id` | int | FK → itineraries, nullable |
| `package_name` | varchar(150) | denormalised for email rendering |
| `accommodation_level` | enum | budget / midrange / highend |
| `travel_date` | date | |
| `total_amount` | decimal(10,2) | USD |
| `deposit_amount` | decimal(10,2) | direct booking: `DEPOSIT_PERCENT` config (25%); quotation conversion: carries over the 30% already quoted to the client — previously this was silently recomputed to 50% at conversion, overriding what the client had agreed to |
| `payment_status` | enum | pending / deposit_paid / fully_paid / cancelled |
| `confirmation_email_sent` | tinyint(1) | set once `booking_confirmation` is successfully queued |
| `created_at` | timestamp | |

**`quotations`** — pre-booking cost proposals sent to potential clients.

| Column | Purpose |
|---|---|
| `quote_ref` | e.g. `SZV-Q001` |
| `client_name`, `client_email` | |
| `itinerary_id` | FK → itineraries |
| `start_date` | proposed departure |
| `adults`, `children` | |
| `accommodation_level` | |
| `line_items` | JSON — full cost breakdown |
| `grand_total`, `per_person`, `deposit_amount`, `balance_due` | |
| `status` | draft / sent / accepted / expired / converted |
| `discount_pct` | |
| `booking_ref` | populated when converted to booking |

**`packages`** — the 5 safari products.

| Column | Purpose |
|---|---|
| `slug` | URL key e.g. `classic-safari` |
| `name` | display name |
| `duration_days` | |
| `itinerary_id` | FK → itineraries |
| `price_1pax`, `price_2pax`, `price_3pax` | USD per person by group size |
| `accommodation_level` | default level |
| `includes` | JSON array |
| `excludes` | JSON array |
| `gallery_images` | JSON array of image paths |
| `cost_breakdown` | JSON — internal costing |
| `is_active`, `is_featured`, `sort_order` | |

**`itineraries`** — day-by-day journey templates.

| Column | Purpose |
|---|---|
| `slug` | e.g. `classic-safari-5n` |
| `duration_days` | |
| `days` | JSON array: `[{day, title, location, description, accommodation, vehicle, parks, activities, meals}]` |
| `includes` | JSON array |
| `base_price_pax` | starting price for quotation engine |

**`leads`** — the agent sales pipeline.

| Column | Purpose |
|---|---|
| `full_name`, `email`, `phone`, `country` | |
| `agent_id` | FK → admin_users |
| `package_name` | what they're interested in |
| `budget_range` | e.g. `$2,000–$3,000` |
| `pipeline_stage` | lead / qualified / quoted / negotiation / booked / travelled / completed / lost |
| `booking_value` | actual value when converted |
| `commission_rate` | override rate for this lead |
| `commission_amount` | calculated: `booking_value × rate / 100` |
| `commission_status` | pending / approved / paid / cancelled |
| `booking_ref` | populated when converted |
| `next_follow_up` | date for follow-up reminder |

**`commissions`** — audit trail of every commission record.

| Column | Purpose |
|---|---|
| `agent_id` | FK → admin_users |
| `lead_id` | FK → leads |
| `booking_ref` | |
| `client_name`, `package_name` | |
| `booking_value`, `commission_rate`, `commission_amount` | |
| `status` | pending / approved / paid / cancelled |
| `approved_by` | FK → admin_users |
| `approved_at`, `paid_at` | timestamps |

**`admin_users`** — all admin panel users including agents.

| Column | Purpose |
|---|---|
| `username`, `password_hash` | bcrypt hashed |
| `full_name`, `email`, `phone` | |
| `role` | superadmin / admin / agent / staff |
| `is_active` | toggle without deleting |
| `commission_rate` | default % for this agent |
| `target_monthly` | monthly booking value target |

**`email_queue`** — all outbound emails.

| Column | Purpose |
|---|---|
| `to_email`, `to_name` | |
| `subject`, `body` | full HTML body |
| `template` | which template was used |
| `status` | pending / sent / failed |
| `retry_count` | max 3 attempts |
| `created_at`, `sent_at` | |

**`booking_reminders`** — scheduled email triggers.

| Column | Purpose |
|---|---|
| `booking_id` | FK → bookings |
| `type` | deposit_due / balance_due / departure / post_trip |
| `scheduled_at` | when to send |
| `sent_at` | populated when sent |
| `status` | pending / sent / skipped |

**`agent_targets`** — monthly targets per agent.

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
| `quotation_sent` | Guest | `api/quotation_email.php` |
| `quotation_accepted` | Guest | `admin/quotations.php` on accept |
| `quotation_expired` | Guest | `admin/quotations.php` on expire |
| `contact_acknowledgement` | Guest | `api/contact.php` auto-reply |
| `contact_admin_notification` | Admin | `api/contact.php` notify admin |
| `custom_request_ack` | Guest | `api/custom_safari.php` auto-reply |
| `custom_request_admin` | Admin | `api/custom_safari.php` notify admin |
| `lead_converted` | Agent | `api/leads.php` on stage → Booked |
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

When an agent logs in, `$_SESSION['admin_role']` is set to `'agent'`. The `api/leads.php` handler checks this and applies a `WHERE agent_id = $myId` filter to every query. This is enforced server-side — the client cannot override it. Agents cannot set their own `agent_id` or `commission_rate` in POST requests (both are ignored and set server-side).

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

All endpoints live in `api/`. They return JSON (except `quotation_pdf.php` which streams a PDF).

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `api/book.php` | POST | None | Submit booking form |
| `api/contact.php` | POST | None | Submit contact form |
| `api/custom_safari.php` | POST | None | Submit custom safari request |
| `api/track_lookup.php` | POST | None (rate-limited) | Look up booking by ref + date |
| `api/record_payment.php` | POST | None (rate-limited, PayPal-verified) | Verify a PayPal order server-side and mark booking deposit/fully paid |
| `api/itinerary.php` | GET/POST | Admin session | Get itinerary data + quote items |
| `api/leads.php` | POST | Admin session | Leads CRUD (agent-scoped) |
| `api/convert_quotation.php` | POST | Admin session | Convert quote to booking |
| `api/quotation_email.php` | POST | Admin session | Send quotation by email |
| `api/quotation_pdf.php` | GET | Admin session | Stream quotation PDF |
| `api/analytics.php` | GET | Admin session | Dashboard stats JSON |
| `api/gallery/upload.php` | POST | Admin session | Upload image |
| `api/gallery/delete.php` | POST | Admin session | Delete image |
| `api/gallery/list.php` | GET | Admin session | List images in folder |
| `api/cron/process_email_queue.php` | CLI only | 403 from web | Send pending emails |
| `api/cron/process_reminders.php` | CLI only | 403 from web | Fire scheduled reminders |

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
                 // see LOCAL-DEV-SETUP.md)
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

### Prerequisites
- PHP 8.3 (`/usr/bin/php8.3`)
- MySQL 5.7
- Apache with `mod_rewrite`
- Ubuntu (tested on Ubuntu 22.04 / 24.04)

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/your-org/safari-zangu.git /var/www/html/safari-zangu

# 2. Create local environment file
cd /var/www/html/safari-zangu
cp .env.example .env
# Edit .env: set DB_PASS=your_local_password, APP_ENV=local

# 3. Create the database
mysql -u root -p -e "CREATE DATABASE safari_zangu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Load the schema
# baseline_schema.sql is a verified, complete snapshot of every table the
# app actually uses as of 2026-09-05 — including the `customers` table —
# (generated via mysqldump against a working install, already including
# the audit fixes below) — it replaces the old practice of hand-picking
# which sql/*.sql files to run, which had drifted out of sync with
# production and was missing custom_safari_requests, contact_messages,
# analytics_events, and team_members entirely.
# On a FRESH database, this is the only schema file you need:
mysql -u root -p safari_zangu < sql/baseline_schema.sql
mysql -u root -p safari_zangu < sql/admin_users_setup.sql   # seeds the default admin login

# On an EXISTING database created before 2026-09-05, run these instead of
# (not in addition to) baseline_schema.sql — in this order:
# mysql -u root -p safari_zangu < sql/2026_09_audit_fixes.sql
# mysql -u root -p safari_zangu < sql/2026_09_customers_table.sql
# php database/migrations/2026_09_backfill_customers.php   # one-time, idempotent

# 5. Enable Apache rewrite
sudo a2enmod rewrite headers expires deflate
sudo systemctl restart apache2

# 6. Set permissions
sudo chown -R www-data:www-data assets/images/ storage/
sudo chmod -R 775 assets/images/ storage/

# 7. Set up local cron (optional for dev)
sudo crontab -u www-data -e
# Add the 3 cron lines from section 10

# 8. Visit the site
open http://localhost/safari-zangu/
open http://localhost/safari-zangu/admin/
```

### Default admin credentials (local)
Set via `sql/admin_users_setup.sql` — change immediately after first login.

---

## 14. Production Deployment (Hostinger)

### Pre-launch checklist

```bash
# 1. Upload files via FTP or git pull
# 2. Create .env with production values
APP_ENV=production
DB_PASS=<hostinger_db_password>
SMTP_PASSWORD=<smtp_password>
PAYPAL_MODE=live
PAYPAL_CLIENT_ID=<live_client_id>
GA4_ID=G-YOURCODE
BANK_ACCOUNT_NO=<real_account>
BANK_SWIFT=<real_swift>

# 3. Set file permissions
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 assets/images/ storage/

# 4. Remove the local git history if shipping a plain file copy
rm -rf .git

# 5. Run SQL migrations in Hostinger phpMyAdmin

# 6. Install cron jobs in Hostinger cPanel → Cron Jobs

# 7. Verify HTTPS is working, then uncomment HSTS in .htaccess

# 8. Test SPF/DKIM/DMARC email authentication
# Send a test email to Gmail → Open original → All three should show PASS

# 9. Set admin role
# phpMyAdmin: UPDATE admin_users SET role='superadmin' WHERE username='admin';
```

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

Each file in `tests/suites/` covers one class and needs no database: `FormatterTest.php`, `BookingValidatorTest.php`, `QuotationEngineTest.php` (the pure `detectSeason()` logic), and `RateLimiterTest.php` (against the real `/tmp` file store, using a random per-run action name so runs don't collide). `App\Services\QuotationEngine::calculate()` itself isn't covered here because it requires a live DB connection for its rate lookups — exercise it via `admin/quotation_new.php` or `api/quote.php` locally instead.

To add a suite: drop a new `tests/suites/YourThing.php` file that calls `test('description', fn() => ...)` — `tests/run.php` picks it up automatically, no registration needed.

---

## Licence & Ownership

This system is proprietary software belonging to **Safari Zangu Ventures Ltd**, Arusha, Tanzania. All rights reserved.

- Registration No. 179228971
- TALA Licence No. 037129

---

*Last updated: August 2026*