-- Removes the 7 orphaned itinerary rows (ids 1-7) that predate the
-- current 6 (ids 8-13, one per active package) — confirmed via
-- cross-check against packages.itinerary_id, bookings.itinerary_id and
-- quotations.itinerary_id that nothing references any of them. ids 1-5
-- even carry a broken, double-escaped name ("Classic Safari — 5
-- Nights" literally, not an em-dash) from whatever import produced them.
--
-- Archived (not just deleted) before removal, consistent with every
-- other orphan-cleanup migration in this project (e.g.
-- 2026_10_db1_booking_reminders_fk.sql).
--
-- Run via: php sql/migrate.php

CREATE TABLE IF NOT EXISTS itineraries_orphaned_archive LIKE itineraries;

INSERT INTO itineraries_orphaned_archive
SELECT * FROM itineraries WHERE id IN (1,2,3,4,5,6,7);

DELETE FROM itineraries WHERE id IN (1,2,3,4,5,6,7);
