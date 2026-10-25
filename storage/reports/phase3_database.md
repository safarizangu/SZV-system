# Phase 3 — Database Audit: Safari Zangu Ventures (MySQL `safari_zangu`)

**Scope:** Read-only audit of the live local dev MySQL instance (`safari_zangu`, InnoDB, 33 base tables + 1 view). All findings below are evidence-based — every claim was verified against the running database (`SHOW CREATE TABLE`, `SHOW INDEX`, `SHOW TABLE STATUS`, `EXPLAIN`, and orphan-detection `LEFT JOIN` queries) and cross-checked against the actual SQL in `admin/*.php`, `app/Services/*.php`, and `api/cron/*.php`. No write/DDL/DML statements were executed against the database.

**Date of audit:** 2026-10-25
**Current data volume (dev DB):** everything is small — largest tables are `analytics_events` (967 rows), `bookings` (17 rows), `email_queue` (28 rows), `quotations` (18 rows). This matters: several findings below describe problems that are **currently invisible** (sub-millisecond full table scans on 17 rows) but will become real bottlenecks once this goes to production traffic. That distinction is called out explicitly in each finding's Impact.

---

## Summary table

| # | Finding | Severity | Table(s) |
|---|---|---|---|
| 1 | `booking_reminders` has zero FK constraints; 34/52 rows (65%) are confirmed orphans today | **High** | `booking_reminders` |
| 2 | `analytics_events` has no pruning/archival anywhere in the code; its only index doesn't serve the dashboard's actual query shape | **High** | `analytics_events` |
| 3 | No automated retention for `activity_log`; `email_queue` retention exists but is manual-button-only, not scheduled | **Medium** | `activity_log`, `email_queue` |
| 4 | Missing `created_at`/status indexes behind every "recent activity" dashboard widget | **Medium** | `bookings`, `quotations`, `leads`, `custom_safari_requests` |
| 5 | `commissions.booking_ref` unindexed but used in a dashboard JOIN | **Medium** | `commissions` |
| 6 | `bookings.payment_status` unindexed despite being filtered on every admin dashboard load | **Medium** | `bookings` |
| 7 | Collation mismatch: `countries`/`locations` use `utf8mb4_0900_ai_ci` vs. `utf8mb4_unicode_ci` everywhere else — confirmed to throw error 1267 on join | **Medium** | `countries`, `locations` |
| 8 | `packages.itinerary` JSON duplicates `itineraries.days` and has verifiably drifted (one package has an empty array where the real data has 3 days) | **Medium** | `packages`, `itineraries` |
| 9 | `packages.profit_margin_percent` stored column disagrees with the live value `vw_package_profitability` computes from the same base columns | **Medium** | `packages` |
| 10 | Duplicate FK constraint on `bookings.package_id` (migration leftover) | **Low** | `bookings` |
| 11 | Hard-delete of `admin_users` silently erases attribution on `quotations`/`leads`/`custom_safari_requests`/`incidents` (no name-snapshot fallback, unlike `activity_log`) | **Low** | `admin_users` and dependents |
| 12 | `accommodations.park_key` is a soft/unenforced reference to `park_fees.park_key` | **Low** | `accommodations`, `park_fees` |

**Positive findings worth stating up front:** this schema is **better than the audit brief implied**. Nearly every real relationship (`bookings→customers`, `bookings→packages`, `quotations→leads/customers/packages/vehicles/itineraries/custom_safari_requests`, `leads→admin_users/customers`, `commissions→admin_users/leads`, `booking_assignments→bookings/staff/vehicles`, `incidents→bookings/admin_users`, `reviews→bookings/customers`) **is enforced with real FK constraints**, not just application-level convention. Ten of eleven orphan-detection queries run against the live data came back at **0 orphans**. `activity_log` correctly denormalizes `actor_name` as a text snapshot specifically so the audit trail survives actor deletion — that's good defensive design, not a flaw, and is called out as a positive contrast in finding #11.

---

## Finding 1 — `booking_reminders` has no FK constraints and 65% of its rows are confirmed orphans

**Severity:** High

**Impact:** `booking_reminders` drives the deposit/balance/departure/post-trip email reminder pipeline (`api/cron/process_reminders.php`, `admin/index.php` "overdue reminders" widget). Verified live:

```
SELECT COUNT(*) FROM booking_reminders br LEFT JOIN bookings b
  ON br.booking_ref = b.booking_ref WHERE b.booking_ref IS NULL;
-- => 34  (out of 52 rows total)
```

Sample orphaned refs: `SZV4CBD795A1A`, `SZV630BAFDFCE`, `SZV857AAC7F97`, `SZV8AC55143E1`, `SZV8D8A1DE900`, `SZV8ED8AB18BB`, `SZVC921B5F373`, `SZVDF5E7F324F`. Current `bookings.booking_ref` values all follow a different, year-embedding format (`SZV2026216710`, `SZV20263FB5E0`, …). This strongly indicates the `booking_ref` generation scheme was changed at some point and the old bookings behind these reminders were deleted/reseeded without their dependent reminders being cleaned up — exactly the failure mode that FK constraints exist to prevent.

**Risk:** `process_reminders.php` and any report joining `booking_reminders` to `bookings` will silently skip/mishandle these 34 rows forever (no booking to attach to, no way to resolve the guest's email/trip details). They are permanent dead rows unless manually cleaned.

**Root cause:** `SHOW CREATE TABLE booking_reminders` has **zero `CONSTRAINT` clauses** — only secondary indexes (`idx_send_at`, `idx_status`, `idx_booking` on `booking_ref`). Sibling tables that reference `bookings.booking_ref` (`booking_assignments`, `incidents`, `reviews`) all declare a real FK; `booking_reminders` is the one table in this family that doesn't.

**Recommended fix:**
```sql
-- 1. Clean up the confirmed orphans (after manual review — this DELETEs data,
--    run only once stakeholders confirm these reminders are truly dead):
-- DELETE FROM booking_reminders WHERE booking_ref NOT IN (SELECT booking_ref FROM bookings);

-- 2. Add the missing FK so this can't recur (mirrors booking_assignments' pattern):
ALTER TABLE booking_reminders
  ADD CONSTRAINT fk_booking_reminders_booking
  FOREIGN KEY (booking_ref) REFERENCES bookings(booking_ref) ON DELETE CASCADE;
```

**Location:** `booking_reminders` table; consumed by `api/cron/process_reminders.php`, `admin/index.php:31`.

---

## Finding 2 — `analytics_events` has no pruning strategy, and its one index doesn't match the dashboard's query shape

**Severity:** High

**Impact:** `analytics_events` logs every public-site `page_view`/`book_now_click`/`whatsapp_click`/etc. (`api/analytics.php:32`, `INSERT`). It already has 967 live rows / AUTO_INCREMENT at 1174 after a few weeks of dev traffic — on real production traffic (every page load from every visitor) this is the single fastest-growing table in the system, and:

- A repo-wide grep of `api/cron/*.php` and all admin files for `DELETE`, `archive`, `purge`, `prune`, `retention` turned up **no code that ever deletes from `analytics_events`**. It grows forever.
- The only secondary index is `idx_analytics_event_created (event_name, created_at)`. `admin/analytics.php` runs several date-range-only queries that don't filter on `event_name` first:
  ```sql
  SELECT COUNT(*) FROM analytics_events WHERE created_at >= $since;          -- line 39
  SELECT COUNT(DISTINCT ip_address) FROM analytics_events WHERE created_at >= $since;  -- line 40
  ```
  `EXPLAIN` on the live data confirms MySQL can't use a leading-range scan on `created_at` through a `(event_name, created_at)` index — it falls back to a full index scan reading **every row** (`type: index`, `rows: 967`, `Extra: Using where; Using index`). Today that's free; at production scale with millions of rows it becomes a full-table-equivalent scan on every single admin analytics dashboard load.

**Risk:** Unbounded disk growth with no operational lever to control it, plus a dashboard that gets linearly slower with total event history instead of with the selected date window.

**Root cause:** No retention policy was designed for this table, and the index was built for the event-breakdown queries (which do filter on `event_name`) without covering the plain date-range queries that run first on the same page.

**Recommended fix:**
```sql
-- Add a standalone index that serves date-only range scans:
CREATE INDEX idx_analytics_created ON analytics_events (created_at);

-- Add a scheduled cron (new file, e.g. api/cron/purge_analytics.php, mirroring
-- the pattern already used for email_queue's manual purge in admin/email_queue.php):
-- DELETE FROM analytics_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY);
-- (Or archive to a summarized rollup table before deleting, if historical totals matter.)
```

**Location:** `analytics_events` table; written by `api/analytics.php:32`; read by `admin/analytics.php` (lines 39-160).

---

## Finding 3 — No automated retention for `activity_log`; `email_queue` retention is manual-only

**Severity:** Medium

**Impact:** `activity_log` (the admin audit trail, `INSERT`ed on nearly every admin mutation) has no delete/archive path anywhere in the codebase — same unbounded-growth pattern as Finding 2, just slower (55 rows so far). `email_queue` is better off: `admin/email_queue.php` has a real `purge_sent` action —
```php
// admin/email_queue.php:37-41
if ($action === 'purge_sent') {
    $db->exec("DELETE FROM email_queue WHERE status='sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
}
```
— but this only runs when an admin clicks a button in the UI. There is no cron entry invoking it. `api/cron/` contains `process_email_queue.php` (sends pending mail), `process_quotation_expiry.php`, `process_reminders.php`, `send_single_email.php` — none of them purge anything.

**Risk:** `email_queue.body` stores full HTML email bodies (`longtext`) plus JSON attachment metadata — `SHOW TABLE STATUS` already shows this table's average row length (15KB) dwarfs every other table's. Left to grow without an admin remembering to click "purge sent" periodically, this becomes the largest table on disk well before `analytics_events` does, in storage-per-row terms.

**Root cause:** Retention logic exists for `email_queue` but was wired to a manual admin action instead of a cron job; `activity_log` has no retention logic at all.

**Recommended fix:**
```sql
-- Same statement admin/email_queue.php already runs, just scheduled (e.g. daily cron):
DELETE FROM email_queue WHERE status='sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- New policy for activity_log — keep longer since it's an audit trail, but still bound it:
DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);
```
Add both to the same cron mechanism that drives `api/cron/process_reminders.php` etc.

**Location:** `email_queue` (purge logic in `admin/email_queue.php:37-41`, no cron caller); `activity_log` (no purge logic anywhere).

---

## Finding 4 — Missing `created_at`/status indexes behind the dashboard "recent activity" widgets

**Severity:** Medium (currently free due to tiny table sizes; will degrade as tables grow)

**Impact:** Every admin landing page loads several "most recent N" or "active status" lists sorted by `created_at`, and none of the underlying tables have an index that supports it:

```php
// admin/index.php
$recentBookings = sa($db,"SELECT * FROM bookings ORDER BY created_at DESC LIMIT 8");      // line 191
$recentQuotes   = sa($db,"SELECT * FROM quotations ORDER BY created_at DESC LIMIT 5");    // line 192
$newLeadRows    = sa($db,"SELECT * FROM custom_safari_requests WHERE status='new' ORDER BY created_at DESC LIMIT 5"); // line 193

// admin/leads.php:124
$stmt = $db->prepare("SELECT * FROM leads $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
```

Verified with `EXPLAIN` on the live data:

| Query | `type` | `Extra` |
|---|---|---|
| `bookings ORDER BY created_at DESC LIMIT 8` | **ALL** (17 rows) | `Using filesort` |
| `custom_safari_requests WHERE status='new' ORDER BY created_at DESC LIMIT 5` | **ALL** (8 rows) | `Using where; Using filesort` |
| `quotations ORDER BY created_at DESC LIMIT 5` | **ALL** (18 rows) | `Using filesort` |

All three are full table scans with an explicit sort pass. `custom_safari_requests` additionally has **no secondary index at all** besides its two FK columns (`idx_customer_id`, `idx_submitted_by_agent`) — `status` isn't indexed even though it's filtered on this dashboard and in `admin/custom_requests.php`.

**Risk:** None today (these scans cost microseconds on <20 rows). At real booking volume this is the admin dashboard's home page, loaded on every staff login — a filesort-per-load pattern that degrades linearly with total historical row count rather than with the 5-8 rows actually displayed.

**Root cause:** Indexes were built around FK lookups and status filters needed elsewhere in the app, but the specific "most recent" access pattern used by the dashboards was never covered.

**Recommended fix:**
```sql
ALTER TABLE bookings  ADD INDEX idx_bookings_created_at (created_at);
ALTER TABLE quotations ADD INDEX idx_quotations_created_at (created_at);
ALTER TABLE leads ADD INDEX idx_leads_created_at (created_at);
ALTER TABLE custom_safari_requests ADD INDEX idx_csr_status_created (status, created_at);
```

**Location:** `bookings`, `quotations`, `leads`, `custom_safari_requests`; consumed by `admin/index.php:191-193`, `admin/leads.php:124`.

---

## Finding 5 — `commissions.booking_ref` is unindexed but used in a dashboard JOIN

**Severity:** Medium

**Impact:** The agent dashboard widget on `admin/index.php` computes "bookings I'm credited with" via:
```php
// admin/index.php:29-32
SELECT COUNT(DISTINCT b.id) FROM bookings b
LEFT JOIN quotations oq ON oq.id = b.quotation_id
LEFT JOIN commissions oc ON oc.booking_ref = b.booking_ref
WHERE oq.created_by = ? OR oc.agent_id = ?
```
`SHOW INDEX FROM commissions` confirms the table has indexes on `lead_id`, `agent_id`, `status`, and `approved_by` — but **not on `booking_ref`**, the column this join actually keys on. `EXPLAIN` on the live data shows the `commissions` side of the join as `type: ALL` with `Using join buffer (hash join)`.

**Risk:** Harmless at 1 commission row today; becomes a per-row full scan of the entire `commissions` table for every agent dashboard page load once commission volume grows.

**Recommended fix:**
```sql
ALTER TABLE commissions ADD INDEX idx_commissions_booking_ref (booking_ref);
```

**Location:** `commissions` table; consumed by `admin/index.php:29-37`.

---

## Finding 6 — `bookings.payment_status` unindexed despite being filtered on every dashboard load

**Severity:** Medium

**Impact:** Both the main dashboard and operations dashboard filter on `payment_status`:
```php
// admin/index.php:189
SELECT COUNT(*) FROM bookings WHERE payment_status NOT IN ('fully_paid','cancelled')
  AND travel_date <= DATE_ADD(CURDATE(), INTERVAL 45 DAY)

// admin/dashboard_operations.php:21, 54, 76
WHERE b.payment_status != 'cancelled' ORDER BY b.travel_date ...
```
`SHOW INDEX FROM bookings` has no index covering `payment_status` at all (its only composite index, `idx_ref_travel_date`, is keyed on `booking_ref` first, which is useless for a status-only or status+date filter). `EXPLAIN` confirms `type: ALL` on the overdue-balance query.

**Recommended fix:**
```sql
ALTER TABLE bookings ADD INDEX idx_bookings_status_travel (payment_status, travel_date);
```

**Location:** `bookings` table; consumed by `admin/index.php:189`, `admin/dashboard_operations.php`.

---

## Finding 7 — Collation mismatch between `countries`/`locations` and the rest of the schema

**Severity:** Medium (latent — not currently triggered by application code, but a confirmed landmine)

**Impact:** Verified directly:
```sql
mysql> SELECT b.id FROM bookings b JOIN countries c ON b.country = c.name LIMIT 1;
ERROR 1267 (HY000): Illegal mix of collations (utf8mb4_unicode_ci,IMPLICIT) and (utf8mb4_0900_ai_ci,IMPLICIT) for operation '='
```
`countries` and `locations` were created with `utf8mb4_0900_ai_ci` (MySQL 8.0's newer default collation); every other table in the schema — including every string column that logically corresponds to a country or location name (`bookings.country`, `leads.country`, `quotations.client_country`, etc.) — uses `utf8mb4_unicode_ci`. Any query that directly compares or joins a string from these two tables to a string from any other table in the schema will throw this error.

Currently `app/Services/LocationRegistry.php` only issues standalone `SELECT name FROM countries` / `SELECT name, country FROM locations` queries (confirmed via grep — no `JOIN countries`/`JOIN locations` exists anywhere in the codebase today), so this is dormant. But it is exactly the kind of bug that surfaces the day someone writes a "bookings by country" report that joins `bookings.country` to `countries.name` for a canonical list, or adds a `country_id` FK later.

**Root cause:** `countries` and `locations` were evidently created in a later migration against a MySQL 8.0 instance using its new default collation, without matching the `utf8mb4_unicode_ci` convention established by every earlier table.

**Recommended fix:**
```sql
ALTER TABLE countries  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE locations  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Location:** `countries`, `locations` tables.

---

## Finding 8 — `packages.itinerary` duplicates `itineraries.days` and has already drifted

**Severity:** Medium

**Impact:** `packages` has both `itinerary_id` (a real FK to `itineraries`) **and** its own `itinerary` JSON column holding a second, independent copy of day-by-day itinerary content. Verified directly against live data:
```sql
SELECT p.id, p.name, p.itinerary_id,
  JSON_LENGTH(p.itinerary) AS pkg_itin_len,
  JSON_LENGTH(i.days) AS itin_days_len
FROM packages p JOIN itineraries i ON p.itinerary_id = i.id;
```
Result: **every one of the 6 packages has content that differs between the two columns**, and package #6 ("Short Safari") has `pkg_itin_len = 0` (an empty itinerary array) while the linked `itineraries.days` row has 3 real day entries — i.e. the duplicate copy isn't just stylistically different, it's missing data entirely for at least one package.

The public-facing page has already been patched around this: `views/packages.php` contains an explicit code comment (lines 10-15) saying it deliberately reads `itineraries.days` "rather than `packages.itinerary` (a much thinner title+description-only…)" column. So customer-facing display is **not** currently affected — but the stale `packages.itinerary` column remains in the table, live and queryable, as a trap for any future report, export, or API consumer that doesn't know about that workaround.

**Root cause:** `itinerary_id`/`itinerary_days`-based itinerary management (the `itineraries` table) was added after `packages.itinerary` already existed, and the old column was never backfilled, deprecated, or dropped — it was simply stopped being read by the one page that mattered, leaving the duplicate column to silently rot everywhere else.

**Recommended fix:**
```sql
-- Confirm no other consumer reads packages.itinerary (grep found none besides the
-- dead column itself), then either drop it:
ALTER TABLE packages DROP COLUMN itinerary;
-- ...or, if it must be kept for backward compatibility, keep it in sync on write:
-- UPDATE packages p JOIN itineraries i ON p.itinerary_id = i.id SET p.itinerary = i.days;
```

**Location:** `packages.itinerary` vs. `itineraries.days`; workaround already in `views/packages.php:10-25`.

---

## Finding 9 — `packages.profit_margin_percent` disagrees with the value the profitability view computes live

**Severity:** Medium

**Impact:** `packages` stores `operational_cost`, `selling_price`, and a derived `profit_margin_percent` column. The `vw_package_profitability` view recomputes the same margin live from the same two base columns: `ROUND(((selling_price - operational_cost) / NULLIF(selling_price,0)) * 100, 2)`. Verified against live data:

| id | name | operational_cost | selling_price | **stored** `profit_margin_percent` | **live-computed** value |
|---|---|---|---|---|---|
| 1-5 | Classic/Luxury/Comfort/Zanzibar/Cultural Safari | 0.00 | >0 | **0.00** | **100.00** |
| 6 | Short Safari | 0.00 | 2751.76 | 100.00 | 100.00 |

5 of 6 packages show a stored value of `0.00%` that **contradicts** what the same formula produces live from the same inputs (`100.00%`, since cost is recorded as zero). `admin/dashboard_profitability.php` is not fooled by this — it correctly reads `profit_margin_percent` from `vw_package_profitability` (the live-computed value), not from the raw `packages` column. But `admin/package_edit.php:177-204` writes `profit_margin_percent` back onto `packages` using the identical formula every time a package is saved through the edit screen — meaning the 5 affected rows have simply never been saved through that screen since `operational_cost` was last touched, and the column default (`'0.00'`) was never overwritten.

**Risk:** Any future report, export, or API that queries `packages.profit_margin_percent` directly (bypassing the view) will report every package except one as having a **0% margin**, when the business intent (per the view and the dashboard) is that these packages simply haven't been costed yet and should show ~100%/"Not set" — a materially misleading number for financial reporting.

**Root cause:** A derived value is persisted in two places with two different update triggers — recomputed on every view query, but only recomputed on `packages` when an admin explicitly re-saves the edit form — with no constraint, trigger, or backfill job keeping them in sync.

**Recommended fix:**
```sql
-- Backfill the stale column to match the view's logic right now:
UPDATE packages
SET profit_margin_percent = ROUND(((selling_price - operational_cost) / NULLIF(selling_price,0)) * 100, 2)
WHERE selling_price > 0;

-- Longer-term: drop the redundant stored column and have admin/package_edit.php
-- and admin/dashboard_profitability.php both read from vw_package_profitability,
-- which is already the single source of truth in practice.
```

**Location:** `packages.profit_margin_percent` vs. `vw_package_profitability`; write path `admin/package_edit.php:177-204`; read path `admin/dashboard_profitability.php:13-18`.

---

## Finding 10 — Duplicate FK constraint on `bookings.package_id`

**Severity:** Low

**Impact:** `SHOW CREATE TABLE bookings` lists **two** foreign keys on the same column to the same target with the same action:
```sql
CONSTRAINT `bookings_ibfk_1`   FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
CONSTRAINT `fk_bookings_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
```
This is harmless functionally (MySQL will just check the same constraint twice on every `INSERT`/`UPDATE`/`DELETE` touching `package_id`) but it's dead weight and a sign of migration hygiene debt — an auto-generated FK name (`bookings_ibfk_1`) was never dropped when a properly-named replacement (`fk_bookings_package`) was added.

**Recommended fix:**
```sql
ALTER TABLE bookings DROP FOREIGN KEY bookings_ibfk_1;
```

**Location:** `bookings` table.

---

## Finding 11 — Hard-deleting an admin user silently erases attribution on dependent records

**Severity:** Low

**Impact:** `admin/users.php:73` performs a genuine hard delete: `DELETE FROM admin_users WHERE id=?` (wrapped in try/catch, not a soft-delete toggle — `admin_users.is_active` exists and is used elsewhere for deactivation, but the delete action bypasses it entirely). The following FKs reference `admin_users` with `ON DELETE SET NULL` and have **no denormalized name column to fall back on**:

- `quotations.created_by` → `fk_quotations_created_by`
- `leads.agent_id`, `leads.assigned_to` → `fk_leads_agent`, `fk_leads_assigned_to`
- `custom_safari_requests.submitted_by_agent_id` → `fk_custom_requests_agent`
- `incidents.reported_by` → `fk_incidents_reporter`

Deleting a staff/agent account permanently nulls out "who created this quote" / "who owns this lead" / "who reported this incident" on every historical record they touched, with no way to recover it. By contrast, `activity_log.actor_id` is **also** `ON DELETE SET NULL`, but that table additionally stores `actor_name` as a plain text snapshot specifically so its audit trail survives account deletion — a pattern that was applied there but not to the tables above. `commissions.agent_id` goes the other direction entirely: it has **no `ON DELETE` clause** (default `RESTRICT`), so deleting an agent who has any commission history fails outright with a raw PDO exception message surfaced to the admin via `admin/users.php:74`.

**Risk:** Low likelihood (deleting a staff account that has historical quotes/leads is presumably rare), but when it happens the data loss is silent and permanent, and the inconsistent policy (RESTRICT here, SET NULL there, name-snapshot somewhere else) makes the actual behavior unpredictable without reading each FK definition individually.

**Recommended fix:** Either (a) change these FKs to `RESTRICT` to match the `commissions` pattern and force an explicit reassignment before deletion, or (b) add `created_by_name` / `agent_name` snapshot columns matching the `activity_log.actor_name` pattern. Simplest operational fix: stop exposing hard delete in `admin/users.php` and route it through the existing `is_active` soft-delete toggle instead.

**Location:** `admin_users` and its dependents; delete path `admin/users.php:67-77`.

---

## Finding 12 — `accommodations.park_key` is an unenforced soft reference to `park_fees.park_key`

**Severity:** Low

**Impact:** `accommodations.park_key varchar(50)` has no FK to `park_fees.park_key`. A live orphan check found **0 orphans today** (`SELECT COUNT(*) FROM accommodations a WHERE a.park_key NOT IN (SELECT park_key FROM park_fees)` → 0), so this isn't an active problem, but nothing in the schema prevents one `accommodations` row from referencing a `park_key` that doesn't exist in `park_fees`, which would silently break any cost calculation that joins the two on this key.

**Recommended fix:** Optional. If `park_key` is meant as a strict reference (not just a loose semantic tag like `vehicle_key`/`activity_key` elsewhere in the schema), add:
```sql
ALTER TABLE accommodations ADD CONSTRAINT fk_accommodations_park
  FOREIGN KEY (park_key) REFERENCES park_fees(park_key) ON DELETE SET NULL;
```

**Location:** `accommodations.park_key`, `park_fees.park_key`.

---

## Normalization notes (no action needed)

Per the audit scope, JSON-blob columns (`itineraries.days`, `itineraries.includes/excludes`, `packages.includes/excludes/cost_breakdown/gallery_images`, `quotations.itinerary/line_items/destinations/activities/includes/excludes`, `accommodations.images_json`, `vehicles.images_json`, `activity_log.details`, `email_queue.attachments`) are a deliberate design choice for this system and are **not** flagged as normalization violations in themselves.

`package_name` is duplicated as a free-text column across `leads`, `quotations`, `commissions`, and `bookings` — verified via live query that **0 mismatches** currently exist between `quotations.package_name`/`bookings.package_name` and their linked `packages.name`. This is consistent with an intentional "snapshot the name at time of quote/booking" pattern (so a later package rename doesn't retroactively rewrite historical documents), which is a legitimate and common design for this kind of transactional data — not flagged as a bug. The one place this pattern does go wrong is `packages.itinerary` (Finding 8), where the duplicate isn't a deliberate point-in-time snapshot of someone else's data — it's a second live copy of the same package's own data that was supposed to track its source and didn't.

---

## Database Score: 74 / 100

**Justification:** This schema is meaningfully better-built than a typical "single-developer travel booking system" — the vast majority of real relationships (`bookings↔customers/packages/itineraries/quotations`, `quotations↔leads/customers/packages/vehicles/custom_safari_requests`, `leads↔admin_users/customers`, `commissions↔admin_users/leads`, `booking_assignments↔bookings/staff/vehicles`, `incidents↔bookings/admin_users`, `reviews↔bookings/customers`) are enforced with genuine FK constraints with sensible `ON DELETE` actions, and live orphan checks came back clean on 10 of 11 relationships tested. `activity_log`'s actor-name snapshot is a genuinely good defensive pattern.

Points were deducted for:
- **One confirmed, currently-live data integrity failure** (Finding 1: 65% of `booking_reminders` rows are real orphans today, not theoretical) caused by a missing FK that every sibling table has.
- **Two confirmed data-drift bugs** in `packages` (Findings 8 and 9) that are evidence of derived/duplicated columns without a mechanism keeping them consistent — one is already worked around in the one place it mattered (customer-facing page), the other isn't read anywhere dangerous yet, but both represent real, verifiable inconsistency in the live data right now, not just a theoretical risk.
- **A confirmed collation landmine** (Finding 7) that will throw a hard SQL error the day anyone writes the obvious "join bookings to countries" report.
- **No growth/retention story at all** for the system's two genuinely unbounded, highest-write tables (`analytics_events`, `activity_log`), and only a manual (non-automated) one for `email_queue` — this is the biggest forward-looking risk for a production safari-booking site that will log a `page_view`/`click` event on every public visitor interaction.
- **A cluster of missing indexes** (Findings 4, 5, 6) that are currently free (tables are 10-50 rows) but sit directly behind every admin dashboard's "recent activity" and "overdue" widgets — these will be the first thing that visibly slows down as real booking volume accumulates, and fixing them now (while the tables are empty, so the `ALTER TABLE` is instant) is cheap insurance.

None of the findings are "data is actively corrupting production right now at scale" — the system is still at dev/demo data volumes — which is why the score sits in the "solid foundation, fix before scaling" range rather than lower.

---

## Appendix A — Schema reference (condensed)

33 base tables, 1 view (`vw_package_profitability`), all `InnoDB`, all `utf8mb4` except `countries`/`locations` (`utf8mb4_0900_ai_ci` — see Finding 7). Full `SHOW CREATE TABLE` output for every table was captured during this audit; key structural facts are summarized here.

| Table | Rows (live) | AUTO_INCREMENT | PK | Notable FKs | Notable indexes |
|---|---|---|---|---|---|
| `admin_users` | 6 | 95 | `id` | — | `username` unique |
| `leads` | 3 | 26 | `id` | `agent_id`, `assigned_to`, `customer_id` → SET NULL | `idx_stage`, `idx_email`, `idx_customer_id` |
| `customers` | 7 | 562 | `id` | — | `uk_email` unique |
| `quotations` | 18 | 60 | `id` | 8 FKs (lead, customer, booking, package, vehicle, itinerary, created_by, custom_request) → SET NULL | `idx_quote_ref`, `idx_client_email`, `idx_status`, `idx_customer_id` + 1 per FK |
| `bookings` | 17 | 760 | `id` | `customer_id`, `package_id` (×2, duplicate — Finding 10), `itinerary_id`, `quotation_id` → SET NULL | `idx_ref_travel_date`, `idx_itinerary_id`, `idx_quotation_id`, `idx_customer_id` |
| `commissions` | 1 | 13 | `id` | `agent_id` → RESTRICT (no ON DELETE), `lead_id` → SET NULL, `approved_by` → SET NULL | `uniq_commissions_lead`, `idx_commissions_agent/lead/status`; **no index on `booking_ref`** (Finding 5) |
| `booking_assignments` | 0 | 2 | `id` | `booking_ref` → CASCADE, `staff_id`/`vehicle_id` → SET NULL | `uniq_booking_ref` |
| `booking_reminders` | 52 | 99 | `id` | **none** (Finding 1) | `idx_send_at`, `idx_status`, `idx_booking` |
| `incidents` | 0 | 2 | `id` | `booking_ref`, `reported_by` → SET NULL | — |
| `reviews` | 0 | 2 | `id` | `booking_ref` → CASCADE, `customer_id` → SET NULL | `uniq_booking_review`; CHECK constraints on `rating`/`nps_score` |
| `custom_safari_requests` | 8 | 17 | `id` | `customer_id`, `submitted_by_agent_id` → SET NULL | `idx_customer_id`, `idx_submitted_by_agent`; **no index on `status`/`created_at`** (Finding 4) |
| `email_queue` | 28 | 86 | `id` | — | `idx_status`, `idx_created_at` |
| `activity_log` | 55 | 89 | `id` | `actor_id` → SET NULL (name snapshot via `actor_name`) | `idx_activity_entity`, `idx_activity_actor`, `idx_activity_created` |
| `analytics_events` | 967 | 1174 | `id` | — | `idx_analytics_event_created (event_name, created_at)` — doesn't serve date-only scans (Finding 2) |
| `packages` | 7 | 14 | `id` | `itinerary_id` → SET NULL | `slug` unique |
| `itineraries` | 13 | 17 | `id` | — | `slug` unique, `idx_active` |
| `accommodations` | 146 | 153 | `id` | `supplier_id` → SET NULL | — (no FK on `park_key`, Finding 12) |
| `countries` | 3 | 36 | `id` | — | `uq_countries_name`; **collation `utf8mb4_0900_ai_ci`** (Finding 7) |
| `locations` | 51 | 174 | `id` | — | `uq_locations_name`; **collation `utf8mb4_0900_ai_ci`** (Finding 7) |

Other tables (`park_fees`, `vehicles`, `activities`, `staff`, `suppliers`, `team_members`, `seasonal_rates`, `accommodation_seasonal_rates`, `agent_targets`, `currency_rates`, `email_templates`, `settings`, `contact_messages`, `schema_migrations`) are small reference/config tables with no issues found beyond `contact_messages` having no secondary index at all (low priority given its size and simple filter pattern).

## Appendix B — Orphan-detection queries run (all against live data)

```sql
-- All returned 0 except booking_reminders:
leads.agent_id → admin_users           : 0 orphans
leads.assigned_to → admin_users        : 0 orphans
leads.customer_id → customers          : 0 orphans
bookings.customer_id → customers       : 0 orphans
bookings.package_id → packages         : 0 orphans
bookings.quotation_id → quotations     : 0 orphans
commissions.agent_id → admin_users     : 0 orphans
commissions.lead_id → leads            : 0 orphans
commissions.booking_ref → bookings     : 0 orphans
quotations.lead_id/customer_id/package_id/created_by → * : 0 orphans each
booking_assignments.booking_ref → bookings : 0 orphans
booking_reminders.booking_id → bookings    : 0 orphans (booking_id is mostly NULL)
booking_reminders.booking_ref → bookings   : 34 orphans / 52 rows  ← Finding 1
custom_safari_requests.customer_id → customers : 0 orphans
accommodations.park_key → park_fees.park_key   : 0 orphans (unenforced, Finding 12)
```

## Appendix C — `EXPLAIN` results referenced above

```
SELECT COUNT(*) FROM analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);
  -> type: index, key: idx_analytics_event_created, rows: 967, Extra: Using where; Using index

SELECT * FROM bookings ORDER BY created_at DESC LIMIT 8;
  -> type: ALL, rows: 17, Extra: Using filesort

SELECT * FROM custom_safari_requests WHERE status='new' ORDER BY created_at DESC LIMIT 5;
  -> type: ALL, rows: 8, Extra: Using where; Using filesort

SELECT COUNT(*) FROM bookings WHERE payment_status NOT IN ('fully_paid','cancelled')
  AND travel_date <= DATE_ADD(CURDATE(), INTERVAL 45 DAY);
  -> type: ALL, rows: 17, Extra: Using where

SELECT COUNT(DISTINCT b.id) FROM bookings b
  LEFT JOIN quotations oq ON oq.id=b.quotation_id
  LEFT JOIN commissions oc ON oc.booking_ref=b.booking_ref
  WHERE oq.created_by=1 OR oc.agent_id=1;
  -> commissions: type: ALL, Extra: Using where; Using join buffer (hash join)

SELECT * FROM quotations ORDER BY created_at DESC LIMIT 5;
  -> type: ALL, rows: 18, Extra: Using filesort
```
