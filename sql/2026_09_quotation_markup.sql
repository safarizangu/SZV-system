-- Adds an explicit markup step to the quotation calculation
-- (ItineraryQuoteBuilder::totals()), mirroring the existing discount_pct
-- column exactly — same table, same UI pattern, default 0 so nothing
-- currently priced changes until an admin actively sets one.

ALTER TABLE quotations ADD COLUMN markup_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER discount_pct;
