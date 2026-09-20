-- Room/inventory availability tracking — confirmed MISSING entirely in
-- the enterprise audit ("no availability/capacity tracking found
-- anywhere; accommodations table has rates but no room-count or
-- booking-conflict logic"). available_rooms is nullable and defaults to
-- NULL ("not tracked / no known limit") rather than 0, so adding this
-- column never silently blocks or flags any existing accommodation until
-- an admin actively enters a real allotment figure.

ALTER TABLE accommodations ADD COLUMN available_rooms INT NULL AFTER rate_high;
