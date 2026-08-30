-- ============================================================
-- Agent & Commission System
-- Run each block separately in phpMyAdmin, skip #1060 errors
-- ============================================================

-- 1. Upgrade admin_users to support agents
ALTER TABLE `admin_users` ADD COLUMN `role` enum('superadmin','admin','agent','staff') DEFAULT 'admin' AFTER `email`;
ALTER TABLE `admin_users` ADD COLUMN `is_active` tinyint(1) DEFAULT 1 AFTER `role`;
ALTER TABLE `admin_users` ADD COLUMN `commission_rate` decimal(5,2) DEFAULT 10.00 COMMENT 'Default commission % on bookings' AFTER `is_active`;
ALTER TABLE `admin_users` ADD COLUMN `phone` varchar(50) DEFAULT NULL AFTER `commission_rate`;
ALTER TABLE `admin_users` ADD COLUMN `bio` text DEFAULT NULL AFTER `phone`;
ALTER TABLE `admin_users` ADD COLUMN `target_monthly` decimal(10,2) DEFAULT 0 COMMENT 'Monthly booking value target in USD' AFTER `bio`;

-- 2. Upgrade leads table
ALTER TABLE `leads` ADD COLUMN `agent_id` int DEFAULT NULL COMMENT 'admin_users.id of the agent who owns this lead' AFTER `assigned_to`;
ALTER TABLE `leads` ADD COLUMN `package_name` varchar(150) DEFAULT NULL AFTER `destinations`;
ALTER TABLE `leads` ADD COLUMN `booking_value` decimal(10,2) DEFAULT 0 COMMENT 'Actual booking value when converted' AFTER `budget_range`;
ALTER TABLE `leads` ADD COLUMN `commission_rate` decimal(5,2) DEFAULT NULL COMMENT 'Override rate for this lead' AFTER `booking_value`;
ALTER TABLE `leads` ADD COLUMN `commission_amount` decimal(10,2) DEFAULT 0 AFTER `commission_rate`;
ALTER TABLE `leads` ADD COLUMN `commission_status` enum('pending','approved','paid','cancelled') DEFAULT 'pending' AFTER `commission_amount`;
ALTER TABLE `leads` ADD COLUMN `booking_ref` varchar(50) DEFAULT NULL AFTER `commission_status`;
ALTER TABLE `leads` ADD COLUMN `converted_at` timestamp NULL DEFAULT NULL AFTER `booking_ref`;

-- 3. Commissions table — full audit trail
CREATE TABLE IF NOT EXISTS `commissions` (
  `id`                int NOT NULL AUTO_INCREMENT,
  `agent_id`          int NOT NULL,
  `lead_id`           int DEFAULT NULL,
  `booking_ref`       varchar(50) DEFAULT NULL,
  `client_name`       varchar(150) DEFAULT NULL,
  `package_name`      varchar(150) DEFAULT NULL,
  `booking_value`     decimal(10,2) DEFAULT 0.00,
  `commission_rate`   decimal(5,2) DEFAULT 10.00,
  `commission_amount` decimal(10,2) DEFAULT 0.00,
  `status`            enum('pending','approved','paid','cancelled') DEFAULT 'pending',
  `travel_date`       date DEFAULT NULL,
  `notes`             text DEFAULT NULL,
  `approved_by`       int DEFAULT NULL,
  `approved_at`       timestamp NULL DEFAULT NULL,
  `paid_at`           timestamp NULL DEFAULT NULL,
  `created_at`        timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agent` (`agent_id`),
  KEY `idx_status` (`status`),
  KEY `idx_booking` (`booking_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Agent monthly targets
CREATE TABLE IF NOT EXISTS `agent_targets` (
  `id`          int NOT NULL AUTO_INCREMENT,
  `agent_id`    int NOT NULL,
  `month`       date NOT NULL COMMENT 'First day of target month',
  `target_leads`    int DEFAULT 0,
  `target_bookings` int DEFAULT 0,
  `target_value`    decimal(10,2) DEFAULT 0,
  `actual_leads`    int DEFAULT 0,
  `actual_bookings` int DEFAULT 0,
  `actual_value`    decimal(10,2) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `agent_month` (`agent_id`,`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
