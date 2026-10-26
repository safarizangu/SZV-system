-- DB-10 remediation (audit finding): bookings.package_id carries two FK
-- constraints pointing at the same column (packages.id) with the same
-- ON UPDATE/ON DELETE rules (NO ACTION / SET NULL) — bookings_ibfk_1 (an
-- auto-generated name, likely from the original table creation) and
-- fk_bookings_package (explicitly named, added later without noticing
-- the FK already existed). Pure migration leftover, no behavioral
-- difference between them — drops the auto-generated one, keeps the
-- explicitly-named one.
--
-- Run via: php sql/migrate.php

ALTER TABLE bookings
  DROP FOREIGN KEY bookings_ibfk_1;
