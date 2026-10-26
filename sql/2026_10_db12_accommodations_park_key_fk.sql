-- DB-12 remediation (audit finding): accommodations.park_key was an
-- unenforced soft reference to park_fees.park_key — nothing in the
-- schema stopped a typo'd or stale key from silently never matching any
-- park. One row ("Arusha Planet Lodge", a town lodge with no park) had
-- park_key='' instead of NULL (admin/accommodations.php's save handler
-- always wrote '' for a blank field, never NULL) — normalized first so
-- the new FK can be added cleanly, since '' doesn't match any real
-- park_fees.park_key.
--
-- ON DELETE SET NULL mirrors fk_accommodations_supplier's existing
-- pattern on this same table. ON UPDATE CASCADE because park_key is
-- editable on an existing park_fees row (admin/park_fees.php) — a rename
-- should carry every accommodation referencing it along, not orphan them.
--
-- Run via: php sql/migrate.php

UPDATE accommodations SET park_key = NULL WHERE park_key = '';

ALTER TABLE accommodations
  ADD CONSTRAINT fk_accommodations_park_key
    FOREIGN KEY (park_key) REFERENCES park_fees (park_key)
    ON DELETE SET NULL ON UPDATE CASCADE;
