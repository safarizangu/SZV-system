-- Add template column to email_queue for tracking which template was used
ALTER TABLE `email_queue` ADD COLUMN `template` varchar(60) DEFAULT NULL AFTER `subject`;
