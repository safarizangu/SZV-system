-- Package tiers, vehicle costing detail, profitability scaffolding, and a
-- real duplicate-accommodation fix. Rewritten against the actual schema
-- (packages.name not package_name, price_1pax not base_price, etc.) from
-- a migration draft that was written against a different, imagined
-- schema. accommodation_seasonal_rates already existed identically to
-- what was proposed, so it's not repeated here.
--
-- Deliberately NOT included, with reasons — see conversation:
--   - a duplicated "Ultra Luxury Safari" package and 10 new packages:
--     no real itinerary/images/cost data, would go live broken.
--   - operational_cost population: two packages' cost_breakdown isn't
--     fully structured yet, so a blind copy would show a false 100%
--     margin. Only selling_price (= existing real price_1pax) is set.
--   - a BEFORE INSERT trigger blocking grand_total<=0 on quotations:
--     no corresponding PHP error handling exists for it yet.

-- ── Vehicle costing: only the 2 columns that don't already exist ──────
ALTER TABLE vehicles
  ADD COLUMN maintenance_cost_day DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN insurance_cost_day   DECIMAL(10,2) DEFAULT 0;

-- ── Package tier ────────────────────────────────────────────────────
ALTER TABLE packages
  ADD COLUMN package_tier ENUM('BUDGET','MIDRANGE','COMFORT','LUXURY','ULTRA_LUXURY')
  DEFAULT 'MIDRANGE';

UPDATE packages SET package_tier='MIDRANGE' WHERE slug='classic-safari';
UPDATE packages SET package_tier='LUXURY'   WHERE slug='luxury-safari';
UPDATE packages SET package_tier='COMFORT'  WHERE slug='comfort-safari';
UPDATE packages SET package_tier='LUXURY'   WHERE slug='safari-zanzibar';
UPDATE packages SET package_tier='BUDGET'   WHERE slug='cultural-safari';
UPDATE packages SET package_tier='BUDGET'   WHERE slug='short-safari';

-- ── Package profitability scaffolding ──────────────────────────────
ALTER TABLE packages
  ADD COLUMN operational_cost      DECIMAL(12,2) DEFAULT 0,
  ADD COLUMN selling_price         DECIMAL(12,2) DEFAULT 0,
  ADD COLUMN profit_margin_percent DECIMAL(5,2)  DEFAULT 0;

UPDATE packages SET selling_price = price_1pax WHERE selling_price = 0;

-- ── Fix a real duplicate accommodation name ────────────────────────
-- Two distinct properties both named "Arusha Planet Lodge" (id 1 =
-- midrange, id 45 = highend) — same name, different tier, genuinely
-- ambiguous in any list that shows the name alone.
UPDATE accommodations
  SET name = 'Arusha Planet Lodge Executive Suites'
  WHERE name = 'Arusha Planet Lodge' AND category = 'highend';

-- ── Package variants (structure only — no data proposed) ───────────
CREATE TABLE IF NOT EXISTS package_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    variant_name VARCHAR(100),
    selling_price DECIMAL(10,2),
    accommodation_level ENUM('BUDGET','MIDRANGE','COMFORT','LUXURY','ULTRA_LUXURY'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(package_id)
);

-- ── Quotation validity flag ─────────────────────────────────────────
ALTER TABLE quotations
  ADD COLUMN is_valid BOOLEAN DEFAULT TRUE;

UPDATE quotations SET is_valid = FALSE WHERE grand_total <= 0;

-- ── Profitability view (correct column names: id, name) ────────────
CREATE OR REPLACE VIEW vw_package_profitability AS
SELECT
    id AS package_id,
    name AS package_name,
    price_1pax,
    operational_cost,
    selling_price,
    (selling_price - operational_cost) AS profit_amount,
    ROUND(((selling_price - operational_cost) / NULLIF(selling_price,0)) * 100, 2) AS profit_margin_percent
FROM packages;
