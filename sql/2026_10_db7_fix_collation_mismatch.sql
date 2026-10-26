-- DB-7 remediation (audit finding): countries and locations were created
-- with utf8mb4_0900_ai_ci (MySQL 8's newer default) while every other
-- table in this schema uses utf8mb4_unicode_ci — confirmed this throws
-- "Illegal mix of collations" (error 1267) the moment anything joins
-- either table against a column on another table (e.g.
-- bookings.package_name = locations.name). Dormant only because nothing
-- currently does that join; checked both tables' UNIQUE KEY(name) for
-- collisions under the target collation first — none found (3 and 53
-- rows respectively).
--
-- Run via: php sql/migrate.php

ALTER TABLE countries CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE locations CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
