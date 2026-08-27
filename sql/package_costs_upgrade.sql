-- Package Cost Breakdown Upgrade
-- Run once in phpMyAdmin → safari_zangu → SQL

ALTER TABLE `packages`
  ADD COLUMN `cost_breakdown` JSON DEFAULT NULL
    COMMENT 'Detailed cost breakdown: accommodations[], transport, park_fees[], activities[], other[]'
    AFTER `excludes`,
  ADD COLUMN `price_notes` text DEFAULT NULL
    COMMENT 'Notes about pricing assumptions shown to guests'
    AFTER `cost_breakdown`;
