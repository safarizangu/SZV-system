-- ============================================================
-- Safari Zangu Ventures — baseline schema
-- Generated 2026-09-05 via `mysqldump --no-data` against a live,
-- verified-working copy of the database (includes the customers
-- table and every audit fix as of this date).
--
-- WHY THIS FILE EXISTS: no combination of the older sql/*.sql files
-- can reproduce the schema this application actually runs on —
-- `custom_safari_requests`, `contact_messages`, `analytics_events`,
-- and `team_members` are all tables the code actively reads and
-- writes (App\Models\CustomSafariRequest, api/contact.php,
-- assets/js/analytics.js -> api/analytics.php, admin/team_members.php)
-- with no CREATE TABLE for any of them anywhere in this repo's
-- history — they were created directly in phpMyAdmin at some point
-- and never captured. A fresh clone following the old file list in
-- section 13 of README.md would be missing all four.
--
-- Run this FIRST on a fresh database, before any other file in
-- sql/ or database/migrations/ — it supersedes and includes
-- everything those files build up to as of this date, using
-- CREATE TABLE IF NOT EXISTS so it's also safe to run on an
-- existing database that already has some of these tables.
-- ============================================================

-- Tables reference each other (bookings -> packages/itineraries/customers,
-- quotations -> itineraries/customers, etc.) in an order that doesn't
-- match plain alphabetical CREATE TABLE order below — disable FK
-- checks for the duration of this file rather than hand-reordering
-- 22 tables.
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `accommodations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` enum('budget','midrange','highend','ultra_luxury') COLLATE utf8mb4_unicode_ci DEFAULT 'midrange',
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `park_key` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rate_per_room` decimal(10,2) DEFAULT '0.00',
  `rate_per_person` decimal(10,2) DEFAULT '0.00',
  `rate_low` decimal(10,2) DEFAULT NULL,
  `rate_high` decimal(10,2) DEFAULT NULL,
  `single_supplement` decimal(10,2) DEFAULT '0.00',
  `child_rate` decimal(10,2) DEFAULT '0.00',
  `meal_plan` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'fb',
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `images_json` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `activities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `activity_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(8,2) DEFAULT '0.00',
  `price_unit` enum('per_person','per_group','flat') COLLATE utf8mb4_unicode_ci DEFAULT 'per_person',
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `activity_key` (`activity_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('superadmin','admin','agent','staff') COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT '1',
  `commission_rate` decimal(5,2) DEFAULT '10.00',
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `target_monthly` decimal(10,2) DEFAULT '0.00',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `agent_targets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agent_id` int NOT NULL,
  `month` date NOT NULL,
  `target_leads` int DEFAULT '0',
  `target_bookings` int DEFAULT '0',
  `target_value` decimal(10,2) DEFAULT '0.00',
  `actual_leads` int DEFAULT '0',
  `actual_bookings` int DEFAULT '0',
  `actual_value` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `agent_month` (`agent_id`,`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE IF NOT EXISTS `analytics_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_slug` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `referrer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `booking_reminders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int DEFAULT NULL,
  `booking_ref` varchar(20) NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `type` enum('deposit_due','balance_due','departure','post_trip','custom') DEFAULT 'custom',
  `send_at` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','failed','cancelled') DEFAULT 'pending',
  `subject` varchar(500) DEFAULT NULL,
  `body` longtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_send_at` (`send_at`),
  KEY `idx_status` (`status`),
  KEY `idx_booking` (`booking_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_ref` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quotation_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travelers` int NOT NULL DEFAULT '1',
  `adults` int DEFAULT '1',
  `children` int DEFAULT '0',
  `package_id` int DEFAULT NULL,
  `itinerary_id` int DEFAULT NULL,
  `package_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accommodation_level` enum('budget','midrange','highend') COLLATE utf8mb4_unicode_ci DEFAULT 'midrange',
  `travel_date` date NOT NULL,
  `special_request` text COLLATE utf8mb4_unicode_ci,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `deposit_amount` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('pending','deposit_paid','fully_paid','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_method` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paypal_order_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmation_email_sent` tinyint(1) DEFAULT '0',
  `payment_notes` text COLLATE utf8mb4_unicode_ci,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_ref` (`booking_ref`),
  KEY `idx_ref_travel_date` (`booking_ref`,`travel_date`),
  KEY `idx_itinerary_id` (`itinerary_id`),
  KEY `idx_quotation_id` (`quotation_id`),
  KEY `fk_bookings_package` (`package_id`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bookings_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bookings_itinerary` FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bookings_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `commissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agent_id` int NOT NULL,
  `lead_id` int DEFAULT NULL,
  `booking_ref` varchar(50) DEFAULT NULL,
  `client_name` varchar(150) DEFAULT NULL,
  `package_name` varchar(150) DEFAULT NULL,
  `booking_value` decimal(10,2) DEFAULT '0.00',
  `commission_rate` decimal(5,2) DEFAULT '10.00',
  `commission_amount` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','approved','paid','cancelled') DEFAULT 'pending',
  `travel_date` date DEFAULT NULL,
  `notes` text,
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `custom_safari_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int DEFAULT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `travelers` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adults` int DEFAULT NULL,
  `children` int DEFAULT NULL,
  `infants` int DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `duration_days` int DEFAULT NULL,
  `destinations` text COLLATE utf8mb4_unicode_ci,
  `message` text COLLATE utf8mb4_unicode_ci,
  `status` enum('new','in_review','quoted','converted','closed') COLLATE utf8mb4_unicode_ci DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `flexible_dates` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guide_language` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mainland_accommodation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `beach_accommodation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meal_plan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_layout` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accommodation_standard` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_budget` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `experiences` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `fk_custom_requests_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'stored lowercase+trimmed — the dedup key',
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_seen_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL,
  `to_name` varchar(150) DEFAULT NULL,
  `subject` varchar(500) NOT NULL,
  `template` varchar(60) DEFAULT NULL,
  `booking_ref` varchar(50) DEFAULT NULL,
  `attachments` json DEFAULT NULL,
  `is_plain_text` tinyint(1) DEFAULT '0',
  `body` longtext NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` int DEFAULT '0',
  `last_error` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_key` varchar(100) NOT NULL,
  `subject` varchar(300) NOT NULL,
  `body_html` longtext NOT NULL,
  `is_custom` tinyint(1) DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_key` (`template_key`),
  UNIQUE KEY `uk_key` (`template_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE IF NOT EXISTS `itineraries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. Classic Safari – 5 Days',
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_days` int NOT NULL DEFAULT '5',
  `overview` text COLLATE utf8mb4_unicode_ci,
  `accommodation_level` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'midrange',
  `days` json DEFAULT NULL COMMENT 'Array of day objects with all details',
  `includes` json DEFAULT NULL,
  `excludes` json DEFAULT NULL,
  `base_price_pax` decimal(10,2) DEFAULT '0.00' COMMENT 'Computed base price per person',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `leads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'website',
  `pipeline_stage` enum('lead','qualified','quoted','negotiation','booked','travelled','completed','lost') COLLATE utf8mb4_unicode_ci DEFAULT 'lead',
  `budget_range` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `travel_date` date DEFAULT NULL,
  `booking_value` decimal(10,2) DEFAULT '0.00',
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `commission_amount` decimal(10,2) DEFAULT '0.00',
  `commission_status` enum('pending','approved','paid','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `booking_ref` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `travel_month` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `party_size` int DEFAULT NULL,
  `destinations` text COLLATE utf8mb4_unicode_ci,
  `package_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `score` int DEFAULT '0',
  `assigned_to` int DEFAULT NULL,
  `agent_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `last_contact` timestamp NULL DEFAULT NULL,
  `next_follow_up` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stage` (`pipeline_stage`),
  KEY `idx_email` (`email`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `fk_leads_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `packages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_days` int NOT NULL,
  `short_description` text COLLATE utf8mb4_unicode_ci,
  `overview` text COLLATE utf8mb4_unicode_ci,
  `itinerary` json DEFAULT NULL,
  `itinerary_id` int DEFAULT NULL,
  `accommodation` text COLLATE utf8mb4_unicode_ci,
  `accommodation_level` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `includes` json DEFAULT NULL,
  `excludes` json DEFAULT NULL,
  `cost_breakdown` json DEFAULT NULL COMMENT 'Detailed cost breakdown: accommodations[], transport, park_fees[], activities[], other[]',
  `price_notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Notes about pricing assumptions shown to guests',
  `price_1pax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_2pax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_3pax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `hero_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `gallery_images` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `park_fees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `park_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Tanzania',
  `adult_fee` decimal(8,2) DEFAULT '0.00',
  `child_fee` decimal(8,2) DEFAULT '0.00',
  `resident_adult` decimal(8,2) DEFAULT '0.00',
  `resident_child` decimal(8,2) DEFAULT '0.00',
  `fee_unit` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'per_person_per_entry',
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `park_key` (`park_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `quotations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quote_ref` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `custom_request_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `booking_id` int DEFAULT NULL,
  `package_id` int DEFAULT NULL,
  `package_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_id` int DEFAULT NULL,
  `vehicle_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `park_fees_total` decimal(12,2) DEFAULT '0.00',
  `vehicle_total` decimal(12,2) DEFAULT '0.00',
  `activities_total` decimal(12,2) DEFAULT '0.00',
  `accommodation_total` decimal(12,2) DEFAULT '0.00',
  `flight_cost` decimal(12,2) DEFAULT '0.00',
  `discount_pct` decimal(5,2) DEFAULT '0.00',
  `discount_amount` decimal(12,2) DEFAULT '0.00',
  `version` int DEFAULT '1',
  `pdf_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_email` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expedition_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'safari',
  `adults` int DEFAULT '2',
  `children` int DEFAULT '0',
  `infants` int DEFAULT '0',
  `rooms` int DEFAULT '1',
  `duration_days` int NOT NULL DEFAULT '5',
  `start_date` date DEFAULT NULL,
  `destinations` json DEFAULT NULL,
  `accommodation_level` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'midrange',
  `meal_plan` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'fb',
  `guide_language` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'english',
  `activities` json DEFAULT NULL,
  `internal_flight` tinyint(1) DEFAULT '0',
  `season` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'peak',
  `line_items` json DEFAULT NULL,
  `itinerary_id` int DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT '0.00',
  `service_charge` decimal(12,2) DEFAULT '0.00',
  `vat` decimal(12,2) DEFAULT '0.00',
  `grand_total` decimal(12,2) DEFAULT '0.00',
  `per_person` decimal(12,2) DEFAULT '0.00',
  `deposit_amount` decimal(12,2) DEFAULT '0.00',
  `balance_due` decimal(12,2) DEFAULT '0.00',
  `currency` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT 'USD',
  `status` enum('draft','sent','accepted','declined','expired','converted') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `valid_until` date DEFAULT NULL,
  `booking_ref` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_ref` (`quote_ref`),
  KEY `idx_quote_ref` (`quote_ref`),
  KEY `idx_client_email` (`client_email`),
  KEY `idx_status` (`status`),
  KEY `fk_quotations_itinerary` (`itinerary_id`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `fk_quotations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_quotations_itinerary` FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `seasonal_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `season_key` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `multiplier` decimal(4,2) DEFAULT '1.00',
  `start_month` int DEFAULT NULL,
  `end_month` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `season_key` (`season_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `settings` (
  `key` varchar(100) NOT NULL,
  `value` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE IF NOT EXISTS `team_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `member_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `linkedin_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vehicle_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity` int DEFAULT '6',
  `daily_rate` decimal(8,2) DEFAULT '0.00',
  `fuel_cost_day` decimal(8,2) DEFAULT '0.00',
  `driver_cost_day` decimal(8,2) DEFAULT '0.00',
  `level` enum('standard','comfort','luxury') COLLATE utf8mb4_unicode_ci DEFAULT 'standard',
  `is_active` tinyint(1) DEFAULT '1',
  `images_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_key` (`vehicle_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
