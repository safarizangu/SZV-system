-- package_variants was scaffolded in sql/2026_09_package_tiers_and_costing.sql
-- for a "multiple pricing tiers per itinerary" feature that was never
-- built — packages already represent tiers as separate catalog rows
-- (package_tier column, added in the same migration), so a sub-table of
-- variants per package never had a clear purpose. Confirmed 0 rows and
-- zero code references anywhere before dropping.

DROP TABLE IF EXISTS package_variants;
