-- Reminders table — run in phpMyAdmin
-- Automated booking reminders scheduled on booking creation

CREATE TABLE IF NOT EXISTS `booking_reminders` (
  `id`          int NOT NULL AUTO_INCREMENT,
  `booking_id`  int NOT NULL,
  `booking_ref` varchar(20) NOT NULL,
  `type`        enum('deposit_due','balance_due','departure','post_trip','custom') DEFAULT 'custom',
  `send_at`     datetime NOT NULL,
  `sent_at`     datetime DEFAULT NULL,
  `status`      enum('pending','sent','failed','cancelled') DEFAULT 'pending',
  `subject`     varchar(500) DEFAULT NULL,
  `body`        longtext DEFAULT NULL,
  `created_at`  timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_send_at` (`send_at`),
  KEY `idx_status`  (`status`),
  KEY `idx_booking` (`booking_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
