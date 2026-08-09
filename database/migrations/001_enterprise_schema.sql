-- ============================================================
-- SAFARI ZANGU VENTURES v2 — Enterprise Schema Migration
-- Run in phpMyAdmin → SQL tab (MySQL 8.0+)
-- Safe to run on existing database — uses IF NOT EXISTS
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+03:00'; -- Africa/Dar_es_Salaam

-- ══════════════════════════════════════════════════════════════
-- 1. PACKAGES — add tiered pricing support
-- ══════════════════════════════════════════════════════════════
ALTER TABLE packages
    MODIFY COLUMN price_1pax DECIMAL(10,2) NOT NULL DEFAULT 0,
    MODIFY COLUMN price_2pax DECIMAL(10,2) NOT NULL DEFAULT 0,
    MODIFY COLUMN price_3pax DECIMAL(10,2) NOT NULL DEFAULT 0;

-- ══════════════════════════════════════════════════════════════
-- 2. BOOKINGS — enterprise columns
-- ══════════════════════════════════════════════════════════════
ALTER TABLE bookings
    MODIFY COLUMN accommodation_level ENUM('budget','midrange','highend') DEFAULT 'midrange';

-- ══════════════════════════════════════════════════════════════
-- 3. QUOTATIONS — new table for itemised quotes
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS quotations (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    quote_ref           VARCHAR(20)         NOT NULL UNIQUE,
    custom_request_id   INT                 DEFAULT NULL,
    booking_id          INT                 DEFAULT NULL,
    client_name         VARCHAR(150)        NOT NULL,
    client_email        VARCHAR(200)        NOT NULL,
    client_phone        VARCHAR(50)         DEFAULT NULL,
    adults              INT                 DEFAULT 2,
    children            INT                 DEFAULT 0,
    infants             INT                 DEFAULT 0,
    rooms               INT                 DEFAULT 1,
    duration_days       INT                 NOT NULL DEFAULT 5,
    start_date          DATE                DEFAULT NULL,
    destinations        JSON                DEFAULT NULL,
    accommodation_level VARCHAR(50)         DEFAULT 'midrange',
    meal_plan           VARCHAR(10)         DEFAULT 'fb',
    guide_language      VARCHAR(50)         DEFAULT 'english',
    activities          JSON                DEFAULT NULL,
    internal_flight     TINYINT(1)          DEFAULT 0,
    season              VARCHAR(20)         DEFAULT 'peak',
    line_items          JSON                DEFAULT NULL, -- full itemised breakdown
    subtotal            DECIMAL(12,2)       DEFAULT 0,
    service_charge      DECIMAL(12,2)       DEFAULT 0,
    vat                 DECIMAL(12,2)       DEFAULT 0,
    grand_total         DECIMAL(12,2)       DEFAULT 0,
    per_person          DECIMAL(12,2)       DEFAULT 0,
    deposit_amount      DECIMAL(12,2)       DEFAULT 0,
    balance_due         DECIMAL(12,2)       DEFAULT 0,
    currency            VARCHAR(5)          DEFAULT 'USD',
    status              ENUM('draft','sent','accepted','declined','expired','converted') DEFAULT 'draft',
    valid_until         DATE                DEFAULT NULL,
    notes               TEXT                DEFAULT NULL,
    admin_notes         TEXT                DEFAULT NULL,
    sent_at             TIMESTAMP           NULL DEFAULT NULL,
    accepted_at         TIMESTAMP           NULL DEFAULT NULL,
    created_at          TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quote_ref (quote_ref),
    INDEX idx_client_email (client_email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════
-- 4. PARK FEES — database-driven, editable by admin
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS park_fees (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    park_key        VARCHAR(50)     NOT NULL UNIQUE,
    name            VARCHAR(150)    NOT NULL,
    country         VARCHAR(50)     DEFAULT 'Tanzania',
    adult_fee       DECIMAL(8,2)    DEFAULT 0,
    child_fee       DECIMAL(8,2)    DEFAULT 0,
    resident_adult  DECIMAL(8,2)    DEFAULT 0,
    resident_child  DECIMAL(8,2)    DEFAULT 0,
    fee_unit        VARCHAR(30)     DEFAULT 'per_person_per_entry',
    is_active       TINYINT(1)      DEFAULT 1,
    notes           TEXT            DEFAULT NULL,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO park_fees (park_key, name, adult_fee, child_fee) VALUES
('serengeti',    'Serengeti National Park',           70, 20),
('ngorongoro',   'Ngorongoro Conservation Area',      70, 20),
('tarangire',    'Tarangire National Park',           53, 18),
('lake_manyara', 'Lake Manyara National Park',        53, 18),
('arusha_np',    'Arusha National Park',              53, 18),
('ruaha',        'Ruaha National Park',               53, 18),
('nyerere',      'Nyerere National Park',             50, 15),
('zanzibar',     'Zanzibar Island',                    0,  0);

-- ══════════════════════════════════════════════════════════════
-- 5. ACCOMMODATIONS — catalogue for the cost engine
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS accommodations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200)    NOT NULL,
    level           ENUM('budget','midrange','highend','ultra_luxury') DEFAULT 'midrange',
    location        VARCHAR(150)    DEFAULT NULL,
    park_key        VARCHAR(50)     DEFAULT NULL,
    rate_per_room   DECIMAL(10,2)   DEFAULT 0,
    rate_per_person DECIMAL(10,2)   DEFAULT 0,
    single_supplement DECIMAL(10,2) DEFAULT 0,
    child_rate      DECIMAL(10,2)   DEFAULT 0,
    meal_plan       VARCHAR(10)     DEFAULT 'fb',
    is_active       TINYINT(1)      DEFAULT 1,
    notes           TEXT            DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════
-- 6. VEHICLES — fleet management
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS vehicles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_key     VARCHAR(50)     NOT NULL UNIQUE,
    name            VARCHAR(100)    NOT NULL,
    capacity        INT             DEFAULT 6,
    daily_rate      DECIMAL(8,2)    DEFAULT 0,
    fuel_cost_day   DECIMAL(8,2)    DEFAULT 0,
    driver_cost_day DECIMAL(8,2)    DEFAULT 0,
    level           ENUM('standard','comfort','luxury') DEFAULT 'standard',
    is_active       TINYINT(1)      DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO vehicles (vehicle_key, name, capacity, daily_rate, level) VALUES
('land_cruiser',     'Toyota Land Cruiser',            6,  250, 'standard'),
('extended_cruiser', 'Extended Toyota Land Cruiser',   7,  300, 'comfort'),
('luxury_cruiser',   'Luxury Land Cruiser (A/C)',      6,  380, 'luxury'),
('minivan',          'Safari Minivan',                 12, 220, 'standard');

-- ══════════════════════════════════════════════════════════════
-- 7. ACTIVITIES — priceable activities catalogue
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS activities (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    activity_key    VARCHAR(50)     NOT NULL UNIQUE,
    name            VARCHAR(150)    NOT NULL,
    description     TEXT            DEFAULT NULL,
    price           DECIMAL(8,2)    DEFAULT 0,
    price_unit      ENUM('per_person','per_group','flat') DEFAULT 'per_person',
    category        VARCHAR(50)     DEFAULT NULL,
    is_active       TINYINT(1)      DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO activities (activity_key, name, price, price_unit, category) VALUES
('balloon_safari',  'Hot Air Balloon Safari',       599, 'per_person', 'wildlife'),
('walking_safari',  'Walking Safari (2h)',            75, 'per_person', 'wildlife'),
('night_drive',     'Night Game Drive',               80, 'per_person', 'wildlife'),
('dhow_cruise',     'Zanzibar Dhow Cruise',           65, 'per_person', 'zanzibar'),
('maasai_visit',    'Maasai Village Visit',           45, 'per_person', 'culture'),
('stone_town_tour', 'Stone Town Walking Tour',        55, 'per_person', 'culture'),
('spice_tour',      'Zanzibar Spice Farm Tour',       40, 'per_person', 'zanzibar'),
('snorkeling',      'Snorkeling at Mnemba Atoll',     85, 'per_person', 'zanzibar'),
('coffee_tour',     'Coffee Farm & Culture Tour',     35, 'per_person', 'culture'),
('crater_hike',     'Ngorongoro Crater Rim Hike',     50, 'per_person', 'wildlife');

-- ══════════════════════════════════════════════════════════════
-- 8. LEADS / CRM PIPELINE
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS leads (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(150)    NOT NULL,
    email           VARCHAR(200)    NOT NULL,
    phone           VARCHAR(50)     DEFAULT NULL,
    country         VARCHAR(100)    DEFAULT NULL,
    source          VARCHAR(50)     DEFAULT 'website', -- website|referral|social|email|whatsapp
    pipeline_stage  ENUM('lead','qualified','quoted','negotiation','booked','travelled','completed','lost') DEFAULT 'lead',
    budget_range    VARCHAR(100)    DEFAULT NULL,
    travel_month    VARCHAR(30)     DEFAULT NULL,
    party_size      INT             DEFAULT NULL,
    destinations    TEXT            DEFAULT NULL,
    notes           TEXT            DEFAULT NULL,
    score           INT             DEFAULT 0,  -- lead scoring 0-100
    assigned_to     INT             DEFAULT NULL,
    last_contact    TIMESTAMP       NULL DEFAULT NULL,
    next_follow_up  DATE            DEFAULT NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_stage (pipeline_stage),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════
-- 9. ANALYTICS EVENTS (extend existing)
-- ══════════════════════════════════════════════════════════════
-- Table already exists. Add missing columns if needed.
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='analytics_events' AND COLUMN_NAME='referrer');
SET @sql = IF(@col=0, 'ALTER TABLE analytics_events ADD COLUMN referrer VARCHAR(255) DEFAULT NULL',
              'SELECT "referrer exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ══════════════════════════════════════════════════════════════
-- 10. SEASONAL RATES TABLE
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS seasonal_rates (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50)     NOT NULL,
    season_key      VARCHAR(20)     NOT NULL UNIQUE,
    multiplier      DECIMAL(4,2)    DEFAULT 1.00,
    start_month     INT             DEFAULT NULL,
    end_month       INT             DEFAULT NULL,
    description     TEXT            DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO seasonal_rates (name, season_key, multiplier, start_month, end_month, description) VALUES
('Peak Season',     'peak',     1.00, 6,  10, 'June–October + Christmas/New Year. Full rates apply.'),
('Shoulder Season', 'shoulder', 0.85, 3,  5,  'March–May (Green Season). 15% discount.'),
('Low Season',      'low',      0.75, 11, 2,  'November–February (excluding Christmas). 25% discount.');

-- ══════════════════════════════════════════════════════════════
-- FIX: team_members — ensure correct data
-- ══════════════════════════════════════════════════════════════
-- Remove duplicate Penina entry (id=4, old seed)
DELETE FROM team_members WHERE id = 4 AND (email IS NULL OR email = '');

-- Ensure correct bios are set
UPDATE team_members SET bio = 'Disciplined and confident software engineer with deep knowledge of technology and strategic thinking, enhancing customer experiences and driving innovative solutions for Safari Zangu Ventures.'
WHERE name = 'Samson Gabba' AND (bio IS NULL OR bio = 'Over 15 years guiding experience across East Africa.');

