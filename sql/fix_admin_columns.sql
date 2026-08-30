-- Fix admin_users table — add missing columns
-- Run each line separately in phpMyAdmin, skip #1060 errors

ALTER TABLE `admin_users` ADD COLUMN `role` enum('superadmin','admin','staff') DEFAULT 'admin' AFTER `email`;
ALTER TABLE `admin_users` ADD COLUMN `is_active` tinyint(1) DEFAULT 1 AFTER `role`;
ALTER TABLE `admin_users` ADD COLUMN `full_name` varchar(100) DEFAULT NULL AFTER `username`;
ALTER TABLE `admin_users` ADD COLUMN `last_login` datetime DEFAULT NULL AFTER `is_active`;

-- Fix bookings table — ensure adults/children columns exist
ALTER TABLE `bookings` ADD COLUMN `adults` int DEFAULT 1 AFTER `travelers`;
ALTER TABLE `bookings` ADD COLUMN `children` int DEFAULT 0 AFTER `adults`;

-- Fix quotations — ensure all expected columns exist
ALTER TABLE `quotations` ADD COLUMN `start_date` date DEFAULT NULL AFTER `duration_days`;
ALTER TABLE `quotations` ADD COLUMN `itinerary_id` int DEFAULT NULL AFTER `package_name`;
ALTER TABLE `quotations` ADD COLUMN `discount_pct` decimal(5,2) DEFAULT 0 AFTER `service_charge`;
ALTER TABLE `quotations` ADD COLUMN `discount_amount` decimal(10,2) DEFAULT 0 AFTER `discount_pct`;
ALTER TABLE `quotations` ADD COLUMN `balance_due` decimal(10,2) DEFAULT 0 AFTER `deposit_amount`;
ALTER TABLE `quotations` ADD COLUMN `booking_ref` varchar(50) DEFAULT NULL AFTER `itinerary_id`;
ALTER TABLE `quotations` ADD COLUMN `infants` int DEFAULT 0 AFTER `children`;
ALTER TABLE `quotations` ADD COLUMN `guide_language` varchar(50) DEFAULT 'english' AFTER `meal_plan`;

-- Fix packages — ensure itinerary_id exists
ALTER TABLE `packages` ADD COLUMN `itinerary_id` int DEFAULT NULL AFTER `itinerary`;
ALTER TABLE `packages` ADD COLUMN `price_notes` varchar(500) DEFAULT NULL AFTER `price_3pax`;
ALTER TABLE `packages` ADD COLUMN `cost_breakdown` JSON DEFAULT NULL AFTER `excludes`;
ALTER TABLE `packages` ADD COLUMN `gallery_images` JSON DEFAULT NULL AFTER `hero_image`;
