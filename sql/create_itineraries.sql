-- ============================================================
-- Itineraries Table
-- Run in phpMyAdmin → safari_zangu → SQL
-- ============================================================

CREATE TABLE IF NOT EXISTS `itineraries` (
  `id`                int NOT NULL AUTO_INCREMENT,
  `name`              varchar(200) NOT NULL COMMENT 'e.g. Classic Safari – 5 Days',
  `slug`              varchar(100) NOT NULL UNIQUE,
  `duration_days`     int NOT NULL DEFAULT 5,
  `overview`          text DEFAULT NULL,
  `accommodation_level` varchar(50) DEFAULT 'midrange',
  `days`              JSON DEFAULT NULL COMMENT 'Array of day objects with all details',
  `includes`          JSON DEFAULT NULL,
  `excludes`          JSON DEFAULT NULL,
  `base_price_pax`    decimal(10,2) DEFAULT 0.00 COMMENT 'Computed base price per person',
  `is_active`         tinyint(1) DEFAULT 1,
  `sort_order`        int DEFAULT 0,
  `created_at`        timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add itinerary_id column to packages and quotations
ALTER TABLE `packages`   ADD COLUMN `itinerary_id` int DEFAULT NULL AFTER `itinerary`;
ALTER TABLE `quotations` ADD COLUMN `itinerary_id` int DEFAULT NULL AFTER `line_items`;

-- ============================================================
-- Day JSON structure (stored in itineraries.days array):
-- [{
--   "day": 1,
--   "title": "Arusha to Tarangire",
--   "description": "Morning departure...",
--   "location": "Tarangire National Park",
--   "accommodation_id": 12,
--   "accommodation_name": "Tarangire River Camp",
--   "accommodation_rate": 180,
--   "accommodation_rooms": 1,
--   "vehicle_id": 2,
--   "vehicle_name": "Toyota Land Cruiser 4WD",
--   "vehicle_rate": 350,
--   "park_fee_id": 3,
--   "park_fee_name": "Tarangire National Park",
--   "park_adult_fee": 59,
--   "activities": [
--     {"name": "Morning Game Drive", "price_pp": 0, "type": "included"},
--     {"name": "Sundowner", "price_pp": 0, "type": "included"}
--   ],
--   "meal_plan": "fb",
--   "meals_cost_pp": 20,
--   "other_items": [
--     {"name": "Bottled Water", "amount": 5}
--   ]
-- }]
-- ============================================================
