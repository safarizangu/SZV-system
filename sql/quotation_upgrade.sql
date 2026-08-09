-- Quotation table upgrade — MySQL 5.7 compatible
-- Run in phpMyAdmin → safari_zangu → SQL

ALTER TABLE `quotations`
  ADD COLUMN `package_id`          int           DEFAULT NULL       AFTER `booking_id`,
  ADD COLUMN `package_name`        varchar(150)  DEFAULT NULL       AFTER `package_id`,
  ADD COLUMN `vehicle_id`          int           DEFAULT NULL       AFTER `package_name`,
  ADD COLUMN `vehicle_name`        varchar(100)  DEFAULT NULL       AFTER `vehicle_id`,
  ADD COLUMN `park_fees_total`     decimal(12,2) DEFAULT '0.00'     AFTER `vehicle_name`,
  ADD COLUMN `vehicle_total`       decimal(12,2) DEFAULT '0.00'     AFTER `park_fees_total`,
  ADD COLUMN `activities_total`    decimal(12,2) DEFAULT '0.00'     AFTER `vehicle_total`,
  ADD COLUMN `accommodation_total` decimal(12,2) DEFAULT '0.00'     AFTER `activities_total`,
  ADD COLUMN `flight_cost`         decimal(12,2) DEFAULT '0.00'     AFTER `accommodation_total`,
  ADD COLUMN `discount_pct`        decimal(5,2)  DEFAULT '0.00'     AFTER `flight_cost`,
  ADD COLUMN `discount_amount`     decimal(12,2) DEFAULT '0.00'     AFTER `discount_pct`,
  ADD COLUMN `version`             int           DEFAULT '1'        AFTER `discount_amount`,
  ADD COLUMN `pdf_path`            varchar(255)  DEFAULT NULL       AFTER `version`,
  ADD COLUMN `client_country`      varchar(100)  DEFAULT NULL       AFTER `client_phone`,
  ADD COLUMN `expedition_type`     varchar(50)   DEFAULT 'safari'   AFTER `client_country`;