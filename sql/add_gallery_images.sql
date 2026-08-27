-- Add gallery_images column if it doesn't exist
-- MySQL 5.7: run each ALTER separately, skip #1060 Duplicate column errors

ALTER TABLE `packages` ADD COLUMN `gallery_images` JSON DEFAULT NULL AFTER `hero_image`;
ALTER TABLE `packages` ADD COLUMN `price_notes`    text DEFAULT NULL AFTER `price_3pax`;
ALTER TABLE `packages` ADD COLUMN `cost_breakdown` JSON DEFAULT NULL AFTER `excludes`;
