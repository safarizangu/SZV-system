-- ═══════════════════════════════════════════════════════════════
-- Safari Zangu Ventures — Combined Production Deployment Migration
-- Generated 2026-09-20 21:45 — everything from this session, in the
-- exact order it was applied locally (per the schema_migrations table).
--
-- HOW TO RUN: paste this whole file into phpMyAdmin's SQL tab and
-- execute. If any single ALTER TABLE ... ADD COLUMN statement fails
-- with 'Duplicate column name' or a CREATE TABLE fails with 'table
-- already exists', that specific piece is already on production —
-- delete just that one statement and re-run the rest. CREATE TABLE
-- statements already use IF NOT EXISTS so those are always safe to
-- re-run as-is.
--
-- After this succeeds, run the schema_migrations INSERT block at
-- the very end so production's migration history matches local.
-- ═══════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────
-- FILE: sql/2026_09_operations_guides_drivers.sql
-- ─────────────────────────────────────────────────────────────
-- Operations Management: Guide/Driver roster + per-booking assignment.
--
-- Safari Zangu's own pricing already treats guide and driver as one role
-- (ItineraryQuoteBuilder's default line item is literally "Professional
-- Driver Guide & Service Fee") — this models that same reality instead
-- of inventing a separate-roles structure the business doesn't use.
--
-- booking_assignments is intentionally one row per booking (UNIQUE on
-- booking_ref): "who is on this trip" is a single current answer, not a
-- history log. staff_id/vehicle_id are nullable so a booking can be
-- assigned a vehicle before a guide is confirmed, or vice versa.

CREATE TABLE IF NOT EXISTS staff (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(150) NOT NULL,
    role            ENUM('guide_driver','guide','driver') NOT NULL DEFAULT 'guide_driver',
    phone           VARCHAR(50),
    email           VARCHAR(200),
    languages       VARCHAR(255),
    license_number  VARCHAR(100),
    daily_rate      DECIMAL(10,2) NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    notes           TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_assignments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref  VARCHAR(20) NOT NULL,
    staff_id     INT NULL,
    vehicle_id   INT NULL,
    notes        TEXT,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_booking_ref (booking_ref),
    CONSTRAINT fk_assignments_booking FOREIGN KEY (booking_ref) REFERENCES bookings(booking_ref) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_staff   FOREIGN KEY (staff_id)    REFERENCES staff(id)    ON DELETE SET NULL,
    CONSTRAINT fk_assignments_vehicle FOREIGN KEY (vehicle_id)  REFERENCES vehicles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- FILE: sql/2026_09_currency_rates.sql
-- ─────────────────────────────────────────────────────────────
-- Multi-currency (display) — replaces the single hardcoded GBP_RATE
-- constant in config/app.php with a real, admin-managed rate table
-- covering the currencies SZV's actual guest nationalities use.
--
-- Deliberately display-only: USD stays the one currency bookings,
-- quotations and payments are actually recorded in. Converting real
-- charges to a foreign currency touches payment-gateway settlement and
-- FX-timing risk that's a separate decision, not something to fold into
-- a display feature.
--
-- Seed rates: GBP carries over the existing GBP_RATE=0.75 the site
-- already used. EUR/KES/TZS are new — reasonable ballpark figures, not
-- a live feed, so is_active defaults off for them until an admin
-- verifies and turns them on via admin/currency_rates.php.

CREATE TABLE IF NOT EXISTS currency_rates (
    code         VARCHAR(3) PRIMARY KEY,
    name         VARCHAR(50) NOT NULL,
    symbol       VARCHAR(5) NOT NULL,
    rate_to_usd  DECIMAL(12,6) NOT NULL COMMENT 'Units of this currency per 1 USD',
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO currency_rates (code, name, symbol, rate_to_usd, is_active) VALUES
    ('USD', 'US Dollar',        '$',   1.000000, 1),
    ('GBP', 'British Pound',    '£',   0.750000, 1),
    ('EUR', 'Euro',             '€',   0.920000, 0),
    ('KES', 'Kenyan Shilling',  'KSh', 129.000000, 0),
    ('TZS', 'Tanzanian Shilling','TSh', 2600.000000, 0)
ON DUPLICATE KEY UPDATE code = code;

-- ─────────────────────────────────────────────────────────────
-- FILE: sql/2026_09_reviews.sql
-- ─────────────────────────────────────────────────────────────
-- Customer reviews / NPS capture — the post-trip email already links to
-- external Google/TripAdvisor review pages, but nothing internal existed
-- to capture structured feedback SZV can act on or (once moderated)
-- display as vetted testimonials.
--
-- One review per booking (UNIQUE booking_ref) — prevents repeat
-- submissions inflating scores, and matches "how was YOUR trip" as a
-- one-time ask, not an ongoing thread.

CREATE TABLE IF NOT EXISTS reviews (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref    VARCHAR(20) NOT NULL,
    customer_id    INT NULL,
    guest_name     VARCHAR(150) NOT NULL,
    guest_country  VARCHAR(100),
    rating         TINYINT UNSIGNED NOT NULL,
    nps_score      TINYINT UNSIGNED NULL,
    comment        TEXT,
    is_published   TINYINT(1) NOT NULL DEFAULT 0,
    submitted_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_booking_review (booking_ref),
    CONSTRAINT fk_reviews_booking  FOREIGN KEY (booking_ref) REFERENCES bookings(booking_ref) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT chk_reviews_rating  CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT chk_reviews_nps     CHECK (nps_score IS NULL OR nps_score BETWEEN 0 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- FILE: sql/2026_09_suppliers.sql
-- ─────────────────────────────────────────────────────────────
-- Supplier management — connects existing accommodations to the real
-- business entity SZV contracts/pays, instead of a disconnected
-- standalone directory. Park/activity operators and transport partners
-- go in the same table with a category, since none of those have their
-- own dedicated table to link from yet.

CREATE TABLE IF NOT EXISTS suppliers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    category        ENUM('accommodation','park_operator','activity_operator','transport','other') NOT NULL DEFAULT 'accommodation',
    contact_person  VARCHAR(150),
    phone           VARCHAR(50),
    email           VARCHAR(200),
    payment_terms   VARCHAR(255),
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    notes           TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE accommodations ADD COLUMN supplier_id INT NULL AFTER park_key;
ALTER TABLE accommodations ADD CONSTRAINT fk_accommodations_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;

-- ─────────────────────────────────────────────────────────────
-- FILE: sql/2026_09_quotation_markup.sql
-- ─────────────────────────────────────────────────────────────
-- Adds an explicit markup step to the quotation calculation
-- (ItineraryQuoteBuilder::totals()), mirroring the existing discount_pct
-- column exactly — same table, same UI pattern, default 0 so nothing
-- currently priced changes until an admin actively sets one.

ALTER TABLE quotations ADD COLUMN markup_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER discount_pct;

-- ─────────────────────────────────────────────────────────────
-- FILE: sql/2026_09_park_vehicle_fee.sql
-- ─────────────────────────────────────────────────────────────
-- Tanzania national parks charge a separate per-vehicle entry fee on top
-- of per-person entrance fees (TANAPA fee schedules). admin/quotation_new.php
-- already tried to read this as park_fees.vehicle_fee — a column that never
-- existed, so that SELECT silently failed and the park-fee autocomplete on
-- that page has been returning empty ever since. Adding the real column
-- fixes both the bug and the missing pricing category at once.

ALTER TABLE park_fees ADD COLUMN vehicle_fee DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER child_fee;

-- ─────────────────────────────────────────────────────────────
-- FILE: sql/2026_09_room_availability.sql
-- ─────────────────────────────────────────────────────────────
-- Room/inventory availability tracking — confirmed MISSING entirely in
-- the enterprise audit ("no availability/capacity tracking found
-- anywhere; accommodations table has rates but no room-count or
-- booking-conflict logic"). available_rooms is nullable and defaults to
-- NULL ("not tracked / no known limit") rather than 0, so adding this
-- column never silently blocks or flags any existing accommodation until
-- an admin actively enters a real allotment figure.

ALTER TABLE accommodations ADD COLUMN available_rooms INT NULL AFTER rate_high;

-- ─────────────────────────────────────────────────────────────
-- FILE: sql/2026_09_incidents.sql
-- ─────────────────────────────────────────────────────────────
-- Incident/emergency logging — the audit confirmed zero matches for
-- "emergency" or "incident" anywhere in the codebase. Emergency Contacts
-- (settings key, added earlier this session) covers reference info; this
-- is the actual event log — what happened, on which trip, who's handling
-- it, and whether it's resolved.
--
-- booking_ref is nullable: not every incident is trip-specific (a
-- vehicle issue discovered at the office, a supplier dispute) — forcing
-- a booking link on those would mean either picking an unrelated
-- booking or not logging the incident at all.

CREATE TABLE IF NOT EXISTS incidents (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref       VARCHAR(20) NULL,
    incident_type     ENUM('vehicle_breakdown','medical','weather_delay','accommodation_issue','guide_driver_issue','safety','other') NOT NULL DEFAULT 'other',
    severity          ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    description       TEXT NOT NULL,
    reported_by       INT NULL,
    status            ENUM('open','resolved') NOT NULL DEFAULT 'open',
    resolution_notes  TEXT,
    occurred_at       DATETIME NOT NULL,
    resolved_at       DATETIME NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_incidents_booking  FOREIGN KEY (booking_ref) REFERENCES bookings(booking_ref) ON DELETE SET NULL,
    CONSTRAINT fk_incidents_reporter FOREIGN KEY (reported_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- FILE: sql/2026_09_winback_reminders.sql
-- ─────────────────────────────────────────────────────────────
-- Loyalty/repeat-sales automation — the audit confirmed "repeat-customer
-- count is visible; no automated win-back/loyalty flow." Adds a 5th
-- reminder type reusing the existing booking_reminders/Mailer
-- infrastructure (deposit/balance/departure/post_trip already work this
-- way) rather than building a second, parallel scheduling system.

ALTER TABLE booking_reminders MODIFY COLUMN type ENUM('deposit_due','balance_due','departure','post_trip','win_back','custom') NOT NULL;

-- ─────────────────────────────────────────────────────────────
-- FILE: sql/2026_09_package_tiers_and_costing.sql
-- ─────────────────────────────────────────────────────────────
-- Package tiers, vehicle costing detail, profitability scaffolding, and a
-- real duplicate-accommodation fix. Rewritten against the actual schema
-- (packages.name not package_name, price_1pax not base_price, etc.) from
-- a migration draft that was written against a different, imagined
-- schema. accommodation_seasonal_rates already existed identically to
-- what was proposed, so it's not repeated here.
--
-- Deliberately NOT included, with reasons — see conversation:
--   - a duplicated "Ultra Luxury Safari" package and 10 new packages:
--     no real itinerary/images/cost data, would go live broken.
--   - operational_cost population: two packages' cost_breakdown isn't
--     fully structured yet, so a blind copy would show a false 100%
--     margin. Only selling_price (= existing real price_1pax) is set.
--   - a BEFORE INSERT trigger blocking grand_total<=0 on quotations:
--     no corresponding PHP error handling exists for it yet.

-- ── Vehicle costing: only the 2 columns that don't already exist ──────
ALTER TABLE vehicles
  ADD COLUMN maintenance_cost_day DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN insurance_cost_day   DECIMAL(10,2) DEFAULT 0;

-- ── Package tier ────────────────────────────────────────────────────
ALTER TABLE packages
  ADD COLUMN package_tier ENUM('BUDGET','MIDRANGE','COMFORT','LUXURY','ULTRA_LUXURY')
  DEFAULT 'MIDRANGE';

UPDATE packages SET package_tier='MIDRANGE' WHERE slug='classic-safari';
UPDATE packages SET package_tier='LUXURY'   WHERE slug='luxury-safari';
UPDATE packages SET package_tier='COMFORT'  WHERE slug='comfort-safari';
UPDATE packages SET package_tier='LUXURY'   WHERE slug='safari-zanzibar';
UPDATE packages SET package_tier='BUDGET'   WHERE slug='cultural-safari';
UPDATE packages SET package_tier='BUDGET'   WHERE slug='short-safari';

-- ── Package profitability scaffolding ──────────────────────────────
ALTER TABLE packages
  ADD COLUMN operational_cost      DECIMAL(12,2) DEFAULT 0,
  ADD COLUMN selling_price         DECIMAL(12,2) DEFAULT 0,
  ADD COLUMN profit_margin_percent DECIMAL(5,2)  DEFAULT 0;

UPDATE packages SET selling_price = price_1pax WHERE selling_price = 0;

-- ── Fix a real duplicate accommodation name ────────────────────────
-- Two distinct properties both named "Arusha Planet Lodge" (id 1 =
-- midrange, id 45 = highend) — same name, different tier, genuinely
-- ambiguous in any list that shows the name alone.
UPDATE accommodations
  SET name = 'Arusha Planet Lodge Executive Suites'
  WHERE name = 'Arusha Planet Lodge' AND category = 'highend';

-- ── Package variants (structure only — no data proposed) ───────────
CREATE TABLE IF NOT EXISTS package_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    variant_name VARCHAR(100),
    selling_price DECIMAL(10,2),
    accommodation_level ENUM('BUDGET','MIDRANGE','COMFORT','LUXURY','ULTRA_LUXURY'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(package_id)
);

-- ── Quotation validity flag ─────────────────────────────────────────
ALTER TABLE quotations
  ADD COLUMN is_valid BOOLEAN DEFAULT TRUE;

UPDATE quotations SET is_valid = FALSE WHERE grand_total <= 0;

-- ── Profitability view (correct column names: id, name) ────────────
CREATE OR REPLACE VIEW vw_package_profitability AS
SELECT
    id AS package_id,
    name AS package_name,
    price_1pax,
    operational_cost,
    selling_price,
    (selling_price - operational_cost) AS profit_amount,
    ROUND(((selling_price - operational_cost) / NULLIF(selling_price,0)) * 100, 2) AS profit_margin_percent
FROM packages;

-- ─────────────────────────────────────────────────────────────
-- FILE: sql/2026_09_drop_package_variants.sql
-- ─────────────────────────────────────────────────────────────
-- package_variants was scaffolded in sql/2026_09_package_tiers_and_costing.sql
-- for a "multiple pricing tiers per itinerary" feature that was never
-- built — packages already represent tiers as separate catalog rows
-- (package_tier column, added in the same migration), so a sub-table of
-- variants per package never had a clear purpose. Confirmed 0 rows and
-- zero code references anywhere before dropping.

DROP TABLE IF EXISTS package_variants;

-- ─────────────────────────────────────────────────────────────
-- Register all of the above in schema_migrations, matching local
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO schema_migrations (filename, applied_at) VALUES
  ('2026_09_operations_guides_drivers.sql', NOW()),
  ('2026_09_currency_rates.sql', NOW()),
  ('2026_09_reviews.sql', NOW()),
  ('2026_09_suppliers.sql', NOW()),
  ('2026_09_quotation_markup.sql', NOW()),
  ('2026_09_park_vehicle_fee.sql', NOW()),
  ('2026_09_room_availability.sql', NOW()),
  ('2026_09_incidents.sql', NOW()),
  ('2026_09_winback_reminders.sql', NOW()),
  ('2026_09_package_tiers_and_costing.sql', NOW()),
  ('2026_09_drop_package_variants.sql', NOW());
