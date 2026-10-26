-- Adds "number of rooms" and "airport pickup / Day 0" fields to direct
-- bookings and custom safari requests, so a guest can state both when
-- submitting either form.
--
-- rooms: a single total-rooms-needed preference for the whole trip (not
-- per-night — the itinerary builder's own day_acc_rooms[] already covers
-- per-night room counts once an admin builds the actual quotation; this
-- is just the guest's own stated preference to work from).
--
-- airport_pickup: whether the guest wants pickup from the airport/point
-- of collection with an overnight before the tour itself starts. The
-- itinerary builder (admin/package_edit.php, admin/quotation_new.php)
-- already supports flagging a day as this "Day 0" arrival day — this
-- column just carries the guest's request through to wherever an admin
-- reviews it, so they know to use that existing toggle.

ALTER TABLE bookings
  ADD COLUMN rooms INT NULL AFTER children,
  ADD COLUMN airport_pickup TINYINT(1) NOT NULL DEFAULT 0 AFTER rooms;

ALTER TABLE custom_safari_requests
  ADD COLUMN rooms INT NULL AFTER children,
  ADD COLUMN airport_pickup TINYINT(1) NOT NULL DEFAULT 0 AFTER rooms;
