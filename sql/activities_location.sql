-- Adds a `location` column to `activities` so the Day-by-Day Itinerary's
-- Activities picker can be filtered by region, the same way it already
-- filters Accommodations and Park/Area Fees. Seeded via
-- scratchpad seed_activities.php (one-off, not re-runnable from here) —
-- see admin/activities.php for the ongoing admin UI that maintains it.
ALTER TABLE activities ADD COLUMN location VARCHAR(120) NULL AFTER category;
