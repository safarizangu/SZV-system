-- Add image columns to team_members, accommodations, vehicles
-- Run each ALTER separately in phpMyAdmin. Skip #1060 errors.

ALTER TABLE `team_members`   ADD COLUMN `photo_url`    varchar(500) DEFAULT NULL AFTER `linkedin_url`;
ALTER TABLE `accommodations` ADD COLUMN `images_json`  JSON DEFAULT NULL AFTER `notes`;
ALTER TABLE `vehicles`       ADD COLUMN `images_json`  JSON DEFAULT NULL AFTER `is_active`;
ALTER TABLE `admin_users`    ADD COLUMN `role`         enum('superadmin','admin','staff') DEFAULT 'admin' AFTER `email`;
ALTER TABLE `admin_users`    ADD COLUMN `is_active`    tinyint(1) DEFAULT 1 AFTER `role`;
