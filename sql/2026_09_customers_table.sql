-- ============================================================
-- Customers entity — 2026-09-05
-- Run once, after sql/2026_09_audit_fixes.sql (or after
-- sql/baseline_schema.sql on a fresh install, which does not yet
-- include this — this file is newer than that snapshot).
--
-- WHY: bookings, quotations, leads, and custom_safari_requests each
-- store full_name/email/phone independently with no shared identity.
-- The same guest who submits a custom safari enquiry, gets a
-- quotation, and later books directly produces 3+ disconnected rows
-- with no query able to answer "how many times has this person
-- contacted us" or "what is this person's full history with us."
-- ============================================================

CREATE TABLE IF NOT EXISTS `customers` (
  `id`         int NOT NULL AUTO_INCREMENT,
  `full_name`  varchar(150) NOT NULL,
  `email`      varchar(200) NOT NULL COMMENT 'stored lowercase+trimmed — the dedup key',
  `phone`      varchar(50) DEFAULT NULL,
  `country`    varchar(100) DEFAULT NULL,
  `first_seen_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link the 4 tables that currently duplicate identity independently.
-- Nullable + ON DELETE SET NULL: a customer record is a convenience
-- index, never a required parent — deleting one must never touch the
-- business records (bookings, quotations, leads, enquiries) that
-- reference it.
ALTER TABLE `bookings`               ADD COLUMN `customer_id` int DEFAULT NULL AFTER `quotation_id`;
ALTER TABLE `quotations`             ADD COLUMN `customer_id` int DEFAULT NULL AFTER `custom_request_id`;
ALTER TABLE `leads`                  ADD COLUMN `customer_id` int DEFAULT NULL AFTER `agent_id`;
ALTER TABLE `custom_safari_requests` ADD COLUMN `customer_id` int DEFAULT NULL AFTER `id`;

ALTER TABLE `bookings`               ADD INDEX `idx_customer_id` (`customer_id`);
ALTER TABLE `quotations`             ADD INDEX `idx_customer_id` (`customer_id`);
ALTER TABLE `leads`                  ADD INDEX `idx_customer_id` (`customer_id`);
ALTER TABLE `custom_safari_requests` ADD INDEX `idx_customer_id` (`customer_id`);

ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_customer`   FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL;
ALTER TABLE `quotations`
  ADD CONSTRAINT `fk_quotations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL;
ALTER TABLE `leads`
  ADD CONSTRAINT `fk_leads_customer`      FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL;
ALTER TABLE `custom_safari_requests`
  ADD CONSTRAINT `fk_custom_requests_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL;

-- After this file, run the one-time backfill for EXISTING rows:
--   php database/migrations/2026_09_backfill_customers.php
-- New rows going forward are linked automatically by
-- App\Models\Customer::findOrCreateByEmail(), called from
-- api/book.php, api/convert_quotation.php, admin/quotation_new.php,
-- api/leads.php, and App\Models\CustomSafariRequest::create().
