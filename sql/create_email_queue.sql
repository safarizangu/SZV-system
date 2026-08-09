-- Email queue table — run once in phpMyAdmin
-- Eliminates all SMTP blocking; emails sent by cron job

CREATE TABLE IF NOT EXISTS `email_queue` (
  `id`         int NOT NULL AUTO_INCREMENT,
  `to_email`   varchar(255) NOT NULL,
  `to_name`    varchar(150) DEFAULT NULL,
  `subject`    varchar(500) NOT NULL,
  `body`       longtext NOT NULL,
  `status`     enum('pending','sent','failed') DEFAULT 'pending',
  `attempts`   int DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `sent_at`    timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
