# Safari Zangu Ventures — Admin System Guide

> **Version:** 2026 · **Platform:** safarizanguven.com · **Stack:** PHP 8.3 / MySQL / Apache

---

## Table of Contents

1. [Accessing the Admin Panel](#1-accessing-the-admin-panel)
2. [Dashboard](#2-dashboard)
3. [Bookings](#3-bookings)
4. [Quotations](#4-quotations)
5. [Safari Requests](#5-safari-requests)
6. [Messages](#6-messages)
7. [Leads Pipeline](#7-leads-pipeline)
8. [Email Queue](#8-email-queue)
9. [Reminders](#9-reminders)
10. [Gallery](#10-gallery)
11. [Itineraries](#11-itineraries)
12. [Packages](#12-packages)
13. [Accommodations](#13-accommodations)
14. [Fleet / Vehicles](#14-fleet--vehicles)
15. [Team Members](#15-team-members)
16. [Activities](#16-activities)
17. [Park Fees](#17-park-fees)
18. [Seasonal Rates](#18-seasonal-rates)
19. [Users](#19-users)
20. [Analytics](#20-analytics)
21. [Cron Jobs & Automation](#21-cron-jobs--automation)
22. [New Since September 2026](#22-new-since-september-2026)
23. [Common Workflows](#23-common-workflows)
24. [Security Notes](#24-security-notes)
25. [Troubleshooting](#25-troubleshooting)

---

## 1. Accessing the Admin Panel

**URL:** `https://safarizanguven.com/admin/login.php`

| Field | Value |
|-------|-------|
| Username | `admin` (or your assigned username) |
| Password | Set during setup — change immediately at `/admin/change_password.php` |

**Security features:**
- 5 failed attempts → 15-minute lockout
- Sessions expire after 30 minutes of inactivity
- All sessions require re-authentication after timeout

**First-time setup:**
1. Log in with default credentials
2. Go to **Settings → Users** → edit the admin user → set your real name and email
3. Go to **Change Password** (bottom of sidebar) → set a strong password

---

## 2. Dashboard

**URL:** `/admin/index.php`

The dashboard shows a real-time overview of the business:

**Top row — Key Metrics:**
| Card | What it shows |
|------|---------------|
| Total Revenue | Sum of all confirmed bookings |
| Bookings | Total booking count |
| Quotations | Total quotations created |
| New Requests | Unprocessed safari enquiries (amber when > 0) |
| Unread Messages | Contact form messages awaiting reply |
| Conversion Rate | % of requests that become bookings |

**Comms Health row:**
| Card | Action needed when... |
|------|----------------------|
| Emails Queued | Normal — cron processes every 2 min |
| Emails Failed | Red → go to Email Queue → Retry All |
| Reminders Scheduled | Informational |
| Due Next 24h | Blue → reminders firing today |

**Bottom grid:**
- Recent Bookings — click any row to see full detail
- New Requests — latest safari enquiries
- Recent Quotations — click to edit
- Quick Actions — one-click shortcuts

---

## 3. Bookings

**URL:** `/admin/bookings.php`

**Workflow:**
1. Bookings are created automatically when a guest submits the booking form
2. Or manually converted from a quotation (Quotations → Convert to Booking)

**Status flow:**
```
pending → deposit_paid → fully_paid
                      ↘ cancelled
```

**Actions per booking:**
- **View** — opens detail modal with full guest info, safari details, payment status
- **Update Status** — change payment status inline in the modal
- **WA** — opens WhatsApp chat with the guest's number pre-filled
- **Email** — opens email client with guest's address
- **Export CSV** — download all bookings or current month as spreadsheet

**Filter pills:**
- All / Pending / Confirmed / Deposit Paid / Fully Paid / Cancelled

**Search:**
- Searches booking reference, guest name, and email simultaneously

---

## 4. Quotations

**URL:** `/admin/quotations.php` | **New:** `/admin/quotation_new.php`

### Creating a quotation

1. Click **+ New Quotation**
2. Fill in **Client & Trip Details** (name, email, adults, duration, start date)
3. **Optional: Load an Itinerary Template** (black bar at top)
   - Select itinerary → enter adult count → click **↓ Load Itinerary**
   - All line items auto-populate: accommodation, vehicle, park fees, meals, service fee
   - Line items are fully editable after loading
4. **Build the Cost Breakdown table** manually if not using a template:
   - Each row: Description | Unit Price | Quantity string | Amount
   - Quantity parser: `"6 Guests × 3 Days"` auto-calculates `6×3=18`
   - Click **+ Accommodation**, **+ Vehicle**, **+ Park Fee**, etc. for quick-add rows
   - Use the **Quick Add sidebar** — click any vehicle/accommodation/park to add pre-filled rows
5. **Service fee** is a line item (not a percentage) — e.g. `$100 × 6 Guests = $600`
6. Totals update live: TOTAL → VAT 18% → GRAND TOTAL → Per Person ÷ guests
7. Set **Valid Until** date, **Status** (draft/sent/accepted)
8. Click **💾 Save Quotation**

### After saving:
- **PDF ↗** — opens print-ready PDF matching the SZV Quotation format
- **✉ Send Email** — sends branded HTML quotation email to the client
- **Edit** — return to the builder to adjust line items

### Status flow:
```
draft → sent → accepted → (convert to booking)
            ↘ declined
```

---

## 5. Safari Requests

**URL:** `/admin/custom_requests.php`

These come from the "Design My Safari" form on the homepage.

**Workflow:**
1. New request arrives → shows in sidebar badge and dashboard
2. Click **View** to see full request details (package interest, dates, budget, message)
3. Options:
   - **Reply by Email** — opens email client
   - **WhatsApp** — opens WhatsApp with the guest
   - **Create Quotation** — links to quotation builder pre-filled with client details
   - **Update Status** — mark as `new` / `contacted` / `quoted` / `booked` / `closed`

---

## 6. Messages

**URL:** `/admin/contact_messages.php`

Contact form submissions. Each message shows:
- Guest name, email, phone, country
- Full message body
- **Reply by Email** / **WhatsApp** / **Mark Read** / **Delete** buttons

Unread count shows in the sidebar badge.

---

## 7. Leads Pipeline

**URL:** `/admin/leads.php`

Kanban board with 8 stages:

| Stage | Meaning |
|-------|---------|
| Lead | Initial contact made |
| Qualified | Confirmed they're a real potential buyer |
| Quoted | Quotation sent |
| Negotiation | Discussing price/itinerary |
| Booked | Booking confirmed |
| Travelled | Safari completed |
| Completed | Post-trip follow-up done |
| Lost | Did not book |

Move leads between stages using the dropdown on each card.

---

## 8. Email Queue

**URL:** `/admin/email_queue.php`

All system emails (booking confirmations, quotations, reminders) pass through this queue.

**Status meanings:**
- **Pending** — waiting for cron to process (runs every 2 minutes)
- **Sent** — successfully delivered
- **Failed** — SMTP error, up to 3 auto-retries

**Actions:**
- **View** — preview the HTML email in a modal
- **↺ Retry** — reset a failed email to pending
- **↺ Retry All Failed** — bulk retry
- **🗑 Purge Old Sent** — delete sent emails older than 30 days
- **Live Log** — shows last 20 lines of `/var/log/szv_email.log`, auto-refreshes every 30s

**Cron schedule:** `*/2 * * * *` — runs every 2 minutes as `www-data`

---

## 9. Reminders

**URL:** `/admin/reminders.php`

Automated reminders scheduled for every booking. Four types are auto-created:

| Type | Sent When |
|------|-----------|
| Deposit Due | 48 hours after booking created |
| Balance Due | 45 days before travel date |
| Departure | 7 days before travel date |
| Post-Trip | 3 days after return date |

**Cron status badge** (top right) shows `✓ Active` or `✗ Not Set`.

**Actions:**
- **Schedule a Reminder** — add a custom reminder for any booking reference
- **Cancel** — cancel a pending reminder
- **↺ Retry** — re-queue a failed reminder
- **Live Log** — shows `/var/log/szv_reminders.log`

**Cron schedule:** `0 * * * *` — runs every hour as `www-data`

---

## 10. Gallery

**URL:** `/admin/gallery.php`

Manages all images in `assets/images/`.

**Left panel — Folder tree:**
- Click any folder to browse its images
- Shows image count per folder

**Upload:**
1. Select destination folder from dropdown
2. Click the upload area or drag-and-drop images
3. Accepts JPG, PNG, WebP up to 8MB
4. Multiple files upload simultaneously with progress bar

**Per-image actions:**
- **Copy URL** — copies the image path to clipboard
- **✕ Delete** — removes the file (with confirmation)
- **Click thumbnail** — opens full-size preview

**Used in:** Package editor (gallery picker), team photos, accommodation images, vehicle images

---

## 11. Itineraries

**URL:** `/admin/itineraries.php`

Create reusable day-by-day itinerary templates that populate packages and quotations.

### Creating an itinerary:

1. Click **+ New** in the left sidebar
2. Set **Name**, **Slug**, **Duration (nights)**, **Accommodation Level**
3. For each day, fill in:
   - **🏕 Accommodation** — search by name (auto-fills rate from DB), set rooms
   - **🚙 Vehicle** — search by name (auto-fills rate), one vehicle per day
   - **🏕 Park & Meals** — select park (auto-fills adult fee), set meals cost per person
   - **Activities** — add as many as needed with price and Included/Optional type
   - **Other Costs** — any miscellaneous items
4. **Live cost bar** at top updates as you type — shows total and per-person estimate
5. Each day card shows its own estimated cost badge
6. Fill **Includes** and **Excludes** (one item per line)
7. Click **💾 Save Itinerary**

### Using an itinerary:

**In Quotations** (black bar): Select → enter adult count → **↓ Load Itinerary**
- Populates all line items, groups same accommodation across multiple nights
- Groups same vehicle across all days
- Consolidates park fees by park
- Adds service fee line automatically
- All items editable after loading

**In Package Editor** (purple bar): Select → **↓ Load Template**
- Fills day titles and descriptions
- Sets duration and accommodation level

---

## 12. Packages

**URL:** `/admin/packages.php` | **Edit:** `/admin/package_edit.php`

### Package list (card view):
- Shows hero image, name, active/featured badges, accommodation level, price, image count
- **Edit** / **View Live** / **Activate/Deactivate** buttons per card

### Editing a package:

**Basic Information:**
- Name, URL Slug, Duration (nights), Sort Order
- Short Description (shown on cards), Full Overview

**Gallery Images:**
- Click **+ Select Images** → Gallery Picker modal opens
- Browse by folder, filter by name, click to select (checkmark appears)
- First selected image = hero image on the card

**Cost Breakdown Builder:**
- Add accommodation rows (auto-fills rate from DB)
- Add multiple vehicle rows — each vehicle is a separate line
- **+ New Vehicle to DB** → modal to add a new vehicle to the database
- Add park fees (auto-fills from DB)
- Add activities (Included / Optional toggle)
- **Service Charge ($/person)** → multiplied by traveler count → shown in cost summary
- Live Cost Summary sidebar updates as you type:
  - Accommodation / Transport / Park Fees / Activities / Other / **Service Charge** / **Grand Total / Per Person ÷ travelers**

**Listed Prices:** Solo / Couple / Group (3+) — the selling price shown publicly

**Itinerary:** Day-by-day titles and descriptions (auto-syncs with Duration field)

**Load Itinerary Template** (purple bar): fills day titles from an itinerary template

**Includes & Excludes:** One item per line

---

## 13. Accommodations

**URL:** `/admin/accommodations.php`

Database of lodges, camps, and hotels used in itinerary and cost building.

**Fields:** Name, Level (Budget/Mid-Range/High-End/Ultra Luxury), Location, Park, Rate/Room/Night, Rate/Person, Single Supplement, Child Rate, Meal Plan, Images

**Images section:** Select from Gallery → saved as JSON array

---

## 14. Fleet / Vehicles

**URL:** `/admin/vehicles.php`

Safari vehicles used in cost building.

**Fields:** Vehicle Key, Name, Capacity (seats), Daily Rate, Fuel/Day, Driver/Day, Level (Standard/Luxury), Images

**Note:** Rates entered here auto-fill the vehicle fields in the package cost builder and itinerary builder.

---

## 15. Team Members

**URL:** `/admin/team_members.php`

**Fields:** Name, Role, Bio, Email, Phone, LinkedIn URL, Photo URL, Sort Order, Active

**Adding a photo:**
1. Click **📷 Gallery** button next to Photo URL
2. Gallery modal opens — click any image to select
3. Photo preview appears below the field

---

## 16. Activities

**URL:** `/admin/activities.php`

Activity database used in itineraries and cost builders.

**Fields:** Name, Description, Price, Price Unit (per_person/per_group/per_vehicle), Active

---

## 17. Park Fees

**URL:** `/admin/park_fees.php`

**Fields:** Name, Park Key, Adult Fee, Child Fee, Vehicle Fee, Active

These auto-fill in the itinerary builder and package cost builder when you select a park.

---

## 18. Seasonal Rates

**URL:** `/admin/seasonal_rates.php`

Define seasonal pricing multipliers.

**Fields:** Name (Peak/Shoulder/Low), Start Month, End Month, Multiplier (e.g. 1.0 = full price, 0.85 = 15% discount)

---

## 19. Users

**URL:** `/admin/users.php`

Manage admin panel access.

**Roles:**
| Role | Access |
|------|--------|
| Superadmin | Full access to all modules |
| Admin | Full access |
| Staff | Standard access (limited by future role restrictions) |

**Adding a user:**
1. Fill in Username, Full Name, Email, Role, Password (min 8 chars)
2. Enter **your current password** to authorise (required for all changes)
3. Click **+ Create User**

**Important:** You cannot delete your own account.

---

## 20. Analytics

**URL:** `/admin/analytics.php`

Overview of site traffic and booking performance. Connect Google Analytics 4 by replacing `G-XXXXXXXXXX` in `layouts/head.php` with your real GA4 Measurement ID.

---

## 21. Cron Jobs & Automation

### Installed cron jobs (www-data):
```
*/2 * * * *  /usr/bin/php8.3 .../php/process_email_queue.php >> /var/log/szv_email.log
0   * * * *  /usr/bin/php8.3 .../php/process_reminders.php   >> /var/log/szv_reminders.log
```

### Monitor cron status:
```bash
sudo bash /var/www/html/safari-zangu/check_cron_status.sh
```

### Log files:
- Email: `/var/log/szv_email.log`
- Reminders: `/var/log/szv_reminders.log`

### Manual trigger:
```bash
sudo -u www-data /usr/bin/php8.3 /var/www/html/safari-zangu/php/process_email_queue.php
sudo -u www-data /usr/bin/php8.3 /var/www/html/safari-zangu/php/process_reminders.php
```

### Auto-reminder schedule:
Every time a booking is created or a quotation is converted to a booking, 4 reminders are automatically scheduled:

| Reminder | When |
|----------|------|
| Deposit Due | 48 hours after booking |
| Balance Due | 45 days before travel |
| Departure | 7 days before travel |
| Post-Trip | 3 days after return |

---

## 22. New Since September 2026

A large operations/quotation upgrade landed in September 2026. The sections above still describe every page that existed before it; this section covers what's new.

**Pricing engine.** There is now only one — `App\Services\ItineraryQuoteBuilder`. It applies seasonal pricing, resident/EAC park-fee rates, single-supplement and child accommodation rates, itemized vehicle fuel/driver costs, and an optional markup step, all sourced from the same DB tables the forms already write to. Nothing here needs separate configuration beyond the fields already on Accommodations / Park Fees / Vehicles / Seasons.

| Page | Purpose |
|------|---------|
| Operations Calendar | Month view of confirmed departures and reminders due; assign a guide/driver + vehicle per booking directly from a day's detail panel, with same-day conflict-blocking |
| Guides & Drivers | Roster of guide-drivers (the business treats these as one combined role) |
| Suppliers | Contracted lodges/operators, linkable to individual Accommodations records |
| Room Availability | Set an "Available Rooms" allotment on any Accommodation to see 60-day utilization and get flagged if a property is over-committed |
| Reviews | Guest-submitted ratings/NPS (public link sent in the post-trip email), with publish/unpublish moderation |
| Incidents | Log and resolve trip issues (vehicle breakdown, medical, weather, etc.), optionally linked to a booking |
| Emergency Contacts | Editable reference info (24h line, hospital, evacuation provider) that prints on every Service Voucher |
| Currency Rates | Manages the "~£X" style approximate-price hints shown next to USD prices — display only, all real charges stay in USD |

**New documents**, both generated from a booking/date the same way Booking PDFs already are:
- **Service Voucher** (`api/voucher_pdf.php?ref=...`) — supplier-facing, states the trip is prepaid, lists the day-by-day itinerary and assigned guide/vehicle
- **Departure Manifest** (`api/manifest_pdf.php?date=...`) — every departure on one date in a single printable list, linked from the Operations Calendar

**6 new dashboards** (sidebar → Dashboards): Sales & Pipeline, Operations, Profitability, Customer Experience, Agent Performance, Suppliers & Fleet. Profitability and Agent Performance are admin/superadmin only.

**Automated win-back email.** Every booking now also schedules a 6-months-after-travel "we'd love to have you back" email, alongside the existing deposit/balance/departure/post-trip reminders — it's automatically skipped if the guest has already rebooked by the time it's due to send.

---

## 23. Common Workflows

### Workflow A: New Enquiry → Quotation → Booking

```
1. Guest submits "Design My Safari" form
   → Appears in: Requests (sidebar badge)

2. Admin opens Requests → View → reads details
   → Click "Create Quotation" or go to Quotations → New

3. In Quotation Builder:
   a. Client details auto-filled from request
   b. Load an Itinerary Template (optional) → all costs populate
   c. Adjust line items → review TOTAL / VAT / GRAND TOTAL / Per Person
   d. Save → PDF Preview → Send Email to client

4. Client accepts → update Quotation status to "Accepted"
   → In Quotations list → convert to booking (or client books directly)

5. Booking created → reminders auto-scheduled
   → Deposit reminder fires 48h later
   → Balance reminder fires 45 days before travel
```

### Workflow B: Direct Booking

```
1. Guest submits booking form on website
   → Booking created automatically with reference (SZV...)
   → Confirmation email queued automatically
   → 4 reminders scheduled automatically

2. Admin sees new booking in Dashboard and Bookings
   → Click "View" → verify details
   → Update status as payment received: pending → deposit_paid → fully_paid
```

### Workflow C: Creating a New Package

```
1. Gallery → upload photos for the package folder
   (e.g. assets/images/packages/my-new-package/)

2. Itineraries → create day-by-day itinerary with accommodation, vehicle, parks

3. Packages → New Package:
   a. Fill basic info (name, slug, duration)
   b. Select Images → pick from gallery
   c. Load Itinerary Template → fills day titles
   d. Set cost breakdown (or load from itinerary)
   e. Set listed prices (1pax / 2pax / 3+pax)
   f. Fill Includes & Excludes
   g. Save → View Live to check

4. Package appears on public site immediately if Active + Featured
```

### Workflow D: Managing Email Issues

```
If emails stop sending:
1. Email Queue → check Failed count
2. Click "↺ Retry All Failed"
3. Check Live Log for SMTP errors
4. If persistent: verify SMTP credentials in config/app.php
5. Check cron is running: sudo crontab -u www-data -l
```

---

## 24. Security Notes

| Item | Detail |
|------|--------|
| Session timeout | 30 minutes inactivity → auto logout |
| Login lockout | 5 failed attempts → 15 min lock |
| CSRF protection | All forms have CSRF tokens |
| Rate limiting | Public forms limited by IP (file-based) |
| Password hashing | bcrypt cost 12 |
| .env file | DB/SMTP secrets — never commit to git |
| Admin URL | `/admin/login.php` — not publicly linked |
| .git folder | Blocked by .htaccess |
| Encryption at rest | **Not verified from inside this codebase — it depends on Hostinger's storage layer, which application code can't inspect or control.** Confirm directly with Hostinger support whether the MySQL data directory sits on encrypted storage; if self-managing in the future, enable disk/volume encryption at the OS level. |
| API rate limiting | Every public POST endpoint (contact, book, custom-safari, track, review) is individually rate-limited by IP. There's no single blanket gateway throttle in front of all of `api/` — that's normally a reverse-proxy/WAF concern rather than application code, and isn't something to bolt on speculatively without a specific abuse pattern to defend against. |

**Before going live:**
1. Change default admin password
2. Create `.env` from `.env.example` with real credentials
3. Set real GA4 tracking ID in `layouts/head.php`
4. Switch PayPal to `live` mode
5. Set real bank account details
6. Verify SPF/DKIM/DMARC on domain DNS
7. Remove `.git` folder from production server

---

## 25. Troubleshooting

| Problem | Solution |
|---------|----------|
| Emails not sending | Email Queue → Retry All Failed; check SMTP password |
| Reminders not firing | `sudo crontab -u www-data -l` — verify cron entries exist |
| Images not loading | Check file exists in `assets/images/`; verify permissions (755 dirs, 644 files) |
| Login failing | Check `admin_users` table in DB; verify bcrypt hash with `php -r "echo password_verify('pw', 'hash');"` |
| DB connection error | Check `config/app.php` or `.env` credentials match Hostinger DB |
| 500 error on pages | Set `APP_DEBUG=true` temporarily; check Apache error log |
| Cron not running | `sudo systemctl status cron`; check `/var/log/szv_email.log` |
| Package images 404 | Verify folder name matches slug; upload images to correct folder |
| Quotation PDF blank | Check line_items JSON in quotations table; ensure items have amounts |

---

*Documentation generated: 2026 · Safari Zangu Ventures Admin System*
