-- Admin users setup — run in phpMyAdmin → safari_zangu → SQL

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id`            int NOT NULL AUTO_INCREMENT,
  `username`      varchar(80)  NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `full_name`     varchar(150) DEFAULT NULL,
  `email`         varchar(200) DEFAULT NULL,
  `role`          enum('superadmin','admin','staff') DEFAULT 'admin',
  `is_active`     tinyint(1) DEFAULT 1,
  `last_login`    timestamp NULL DEFAULT NULL,
  `created_at`    timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin user
-- Username: admin
-- Password: SZVAdmin2026! (change this immediately after first login)
INSERT IGNORE INTO `admin_users`
  (`username`, `password_hash`, `full_name`, `email`, `role`)
VALUES (
  'admin',
  '$2y$12$Yn45.AM0UtpR3DVDn2OiHu2T8emOpbkpKOqgvPeX77u7WYK7QxG1.',
  'Safari Zangu Admin',
  'admin@safarizanguven.com',
  'superadmin'
);

-- To create a new user with a different password, run this PHP snippet:
-- php -r "echo password_hash('YourNewPassword!', PASSWORD_BCRYPT, ['cost'=>12]);"
-- Then INSERT with that hash.

-- To update the admin password:
-- UPDATE admin_users SET password_hash = '<new_hash>' WHERE username = 'admin';
