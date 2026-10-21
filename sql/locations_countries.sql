-- Smart Location system: auto-growing master lists that power autocomplete
-- for every location/country field in the app (accommodations, park fees,
-- itinerary day locations, booking/quotation client country). Nothing is
-- entered here by an admin directly — a value is upserted into these
-- tables automatically whenever it's saved anywhere else (see
-- App\Services\LocationRegistry), so the lists grow themselves from real
-- usage instead of needing a separate management page.

CREATE TABLE IF NOT EXISTS locations (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    country    VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_locations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS countries (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_countries_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- park_fees never had a location column (only `country`) — accommodations
-- already has one. Adding it here so a park's own area/region can be
-- matched against an itinerary day's location the same way an
-- accommodation's location is.
ALTER TABLE park_fees ADD COLUMN location VARCHAR(150) NULL AFTER name;
