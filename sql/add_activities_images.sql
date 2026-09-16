-- Activities had no image storage at all, unlike accommodations/vehicles
-- (both already have images_json). Adding the same column so all three
-- can share the same gallery-picker widget as packages.
ALTER TABLE activities ADD COLUMN images_json JSON NULL AFTER category;
