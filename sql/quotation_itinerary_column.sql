-- Adds a per-quotation day-by-day itinerary column, mirroring packages.itinerary.
-- Used when a quotation is NOT linked to a shared itineraries-table template
-- (itinerary_id) — the admin can still build a custom day-by-day itinerary
-- directly on the quotation, and the Quotation Live Cost Summary + the PDF/
-- email day-by-day breakdown both read it as a fallback.
ALTER TABLE `quotations`
  ADD COLUMN `itinerary` JSON DEFAULT NULL AFTER `itinerary_id`;
