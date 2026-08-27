-- Add booking_ref column to quotations (links converted quotes to bookings)
-- Run once in phpMyAdmin → safari_zangu → SQL

ALTER TABLE `quotations`
  ADD COLUMN IF NOT EXISTS `booking_ref` varchar(20) DEFAULT NULL
    COMMENT 'Set when quotation is converted to a booking'
    AFTER `valid_until`,
  ADD COLUMN IF NOT EXISTS `accepted_at` datetime DEFAULT NULL
    AFTER `booking_ref`,
  ADD COLUMN IF NOT EXISTS `sent_at` datetime DEFAULT NULL
    AFTER `accepted_at`;

-- MySQL 5.7 fallback (no IF NOT EXISTS for ADD COLUMN):
-- ALTER TABLE `quotations` ADD COLUMN `booking_ref` varchar(20) DEFAULT NULL;
-- ALTER TABLE `quotations` ADD COLUMN `accepted_at` datetime DEFAULT NULL;
-- ALTER TABLE `quotations` ADD COLUMN `sent_at` datetime DEFAULT NULL;

-- Add adults/children to bookings table if missing
ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `adults`   int DEFAULT 1 AFTER `travelers`,
  ADD COLUMN IF NOT EXISTS `children` int DEFAULT 0 AFTER `adults`;
