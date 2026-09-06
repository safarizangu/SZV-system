-- Adds Includes/Excludes columns to quotations, mirroring packages.includes/
-- packages.excludes. Used when a quotation is NOT linked to a shared
-- itineraries-table template (itinerary_id) — same fallback pattern as
-- quotations.itinerary (see sql/quotation_itinerary_column.sql).
ALTER TABLE `quotations`
  ADD COLUMN `includes` JSON DEFAULT NULL AFTER `itinerary`,
  ADD COLUMN `excludes` JSON DEFAULT NULL AFTER `includes`;
