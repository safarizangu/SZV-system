-- Tanzania national parks charge a separate per-vehicle entry fee on top
-- of per-person entrance fees (TANAPA fee schedules). admin/quotation_new.php
-- already tried to read this as park_fees.vehicle_fee — a column that never
-- existed, so that SELECT silently failed and the park-fee autocomplete on
-- that page has been returning empty ever since. Adding the real column
-- fixes both the bug and the missing pricing category at once.

ALTER TABLE park_fees ADD COLUMN vehicle_fee DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER child_fee;
