-- ============================================================
-- Audit fix migration — 2026-09-05
-- Run once in phpMyAdmin → safari_zangu → SQL (or via CLI).
-- Every change here was verified against a live copy of the
-- schema, not just the older sql/*.sql history.
--
-- Only for a database created BEFORE 2026-09-05. A fresh install
-- should run sql/baseline_schema.sql instead (not in addition to
-- this file) — it already includes every change below. If your
-- database already has a foreign key on bookings.package_id from
-- an earlier ad-hoc change, drop it first or the ADD CONSTRAINT
-- below will just add a redundant second one on the same column.
-- ============================================================

-- FIX (critical, reproduced live): admin_users.role was missing
-- 'agent' from its ENUM in production. sql/build_commission_system.sql
-- intended to add it but a later/parallel ALTER re-narrowed the column.
-- Confirmed live: `INSERT ... role='agent'` throws
-- "ERROR 1265: Data truncated for column 'role'" — no agent account
-- can currently be created through admin/users.php.
ALTER TABLE `admin_users`
  MODIFY COLUMN `role` enum('superadmin','admin','agent','staff') DEFAULT 'admin';

-- FIX (critical): bookings had no way to reference the itinerary a
-- client actually agreed to — the link was dropped the moment a
-- quotation became a booking. quotation_id already existed on
-- bookings but was never populated by api/convert_quotation.php;
-- add the missing itinerary_id alongside it and index both.
ALTER TABLE `bookings`
  ADD COLUMN `itinerary_id` int DEFAULT NULL AFTER `package_id`;
ALTER TABLE `bookings` ADD INDEX `idx_itinerary_id` (`itinerary_id`);
ALTER TABLE `bookings` ADD INDEX `idx_quotation_id` (`quotation_id`);

-- NOTE: email_queue already has `attachments` (JSON) and `is_plain_text`
-- columns in production, added ahead of any code that used them. No
-- schema change needed here — the fix is wiring api/quotation_email.php
-- and api/cron/process_email_queue.php to actually read/write them,
-- which is a code change, not a migration (see git history for this date).

-- ── Safe, low-risk foreign keys ────────────────────────────────
-- Scoped to relationships where the referenced table is a stable
-- lookup/reference table (packages, itineraries) rather than
-- attempting a full FK pass across the schema in one migration.
-- ON DELETE SET NULL: deleting a package/itinerary must never
-- delete or block deleting the booking/quotation that used it.
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_package`
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bookings_itinerary`
    FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries`(`id`) ON DELETE SET NULL;

ALTER TABLE `quotations`
  ADD CONSTRAINT `fk_quotations_itinerary`
    FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries`(`id`) ON DELETE SET NULL;

-- ── Orphaned tables (verified 0 rows, 0 code references) ───────
-- custom_requests: superseded by custom_safari_requests, which is
--   the table app/Models/CustomSafariRequest.php actually uses.
-- fleet: superseded by vehicles, which is the table QuotationEngine
--   and admin/vehicles.php (the page actually linked in the sidebar
--   nav) both use. admin/fleet.php, the only code that touched
--   `fleet`, has been removed from the codebase (dead, unlinked page).
-- Confirmed 0 rows in both, then dropped, on the local dev database
-- this migration was written against — sql/baseline_schema.sql was
-- generated after that drop, so neither table appears there either.
-- VERIFY row counts are also 0 in production before running these
-- two lines there — uncomment once confirmed:
-- DROP TABLE IF EXISTS `custom_requests`;
-- DROP TABLE IF EXISTS `fleet`;
