-- Trip-length flexibility: lets a guest flag interest in extending their
-- booked package (Ruaha / Selous / Zanzibar / etc., matching the
-- per-package suggestions on the Packages page and booking form) without
-- inventing a new "extension request" table. Nullable — most bookings
-- won't use it.
ALTER TABLE bookings
  ADD COLUMN trip_extension VARCHAR(255) NULL AFTER special_request;
