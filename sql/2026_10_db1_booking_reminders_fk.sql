-- DB-1 remediation (audit finding): booking_reminders had zero FK
-- constraints, and 34 of 52 rows (65%) referenced a booking_ref that no
-- longer existed in bookings — evidence of a booking_ref format
-- migration that never cleaned up its dependents. Archives the orphaned
-- rows (not a bare delete — preserved for the record), removes them from
-- the live table, then adds the FK constraint that should have existed
-- from the start so this can't silently recur.
--
-- Run via: php sql/migrate.php

CREATE TABLE IF NOT EXISTS booking_reminders_orphaned_archive LIKE booking_reminders;
ALTER TABLE booking_reminders_orphaned_archive
  ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS archive_reason VARCHAR(255) DEFAULT NULL;

INSERT INTO booking_reminders_orphaned_archive
  (id, booking_id, booking_ref, scheduled_at, type, send_at, sent_at, status, subject, body, created_at, archive_reason)
SELECT br.id, br.booking_id, br.booking_ref, br.scheduled_at, br.type, br.send_at, br.sent_at, br.status, br.subject, br.body, br.created_at,
       'DB-1 remediation: booking_ref no longer exists in bookings (pre-existing booking_ref format migration never cleaned up dependents)'
FROM booking_reminders br
LEFT JOIN bookings b ON b.booking_ref = br.booking_ref
WHERE b.booking_ref IS NULL
  AND br.id NOT IN (SELECT id FROM booking_reminders_orphaned_archive);

DELETE br FROM booking_reminders br
LEFT JOIN bookings b ON b.booking_ref = br.booking_ref
WHERE b.booking_ref IS NULL;

ALTER TABLE booking_reminders
  ADD CONSTRAINT fk_booking_reminders_booking_ref
  FOREIGN KEY (booking_ref) REFERENCES bookings(booking_ref)
  ON DELETE CASCADE ON UPDATE CASCADE;
