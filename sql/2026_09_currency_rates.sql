-- Multi-currency (display) — replaces the single hardcoded GBP_RATE
-- constant in config/app.php with a real, admin-managed rate table
-- covering the currencies SZV's actual guest nationalities use.
--
-- Deliberately display-only: USD stays the one currency bookings,
-- quotations and payments are actually recorded in. Converting real
-- charges to a foreign currency touches payment-gateway settlement and
-- FX-timing risk that's a separate decision, not something to fold into
-- a display feature.
--
-- Seed rates: GBP carries over the existing GBP_RATE=0.75 the site
-- already used. EUR/KES/TZS are new — reasonable ballpark figures, not
-- a live feed, so is_active defaults off for them until an admin
-- verifies and turns them on via admin/currency_rates.php.

CREATE TABLE IF NOT EXISTS currency_rates (
    code         VARCHAR(3) PRIMARY KEY,
    name         VARCHAR(50) NOT NULL,
    symbol       VARCHAR(5) NOT NULL,
    rate_to_usd  DECIMAL(12,6) NOT NULL COMMENT 'Units of this currency per 1 USD',
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO currency_rates (code, name, symbol, rate_to_usd, is_active) VALUES
    ('USD', 'US Dollar',        '$',   1.000000, 1),
    ('GBP', 'British Pound',    '£',   0.750000, 1),
    ('EUR', 'Euro',             '€',   0.920000, 0),
    ('KES', 'Kenyan Shilling',  'KSh', 129.000000, 0),
    ('TZS', 'Tanzanian Shilling','TSh', 2600.000000, 0)
ON DUPLICATE KEY UPDATE code = code;
