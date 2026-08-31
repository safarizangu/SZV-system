-- Settings table for site-wide configuration
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        varchar(100) NOT NULL,
  `value`      text         DEFAULT NULL,
  `updated_at` timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (`key`,`value`)
  VALUES ('hero_image','/safari-zangu/assets/images/downloaded/safari_1.jpg');
