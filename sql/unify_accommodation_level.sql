-- Unify accommodation_level into one canonical 5-value ENUM shared across
-- every table that stores it. Before this, the same concept had 3 column
-- types (a 4-value ENUM, a 3-value ENUM missing luxury/ultra_luxury
-- entirely, and three separate free-text varchar(50) columns), and
-- packages.accommodation_level already had inconsistent casing ("Luxury",
-- "Mid-Range") in production data.

-- Clean existing bad casing/spacing first — ALTER ... MODIFY to ENUM
-- would otherwise reject these rows outright under strict SQL mode.
UPDATE packages SET accommodation_level = 'luxury'   WHERE accommodation_level = 'Luxury';
UPDATE packages SET accommodation_level = 'midrange' WHERE accommodation_level = 'Mid-Range';

ALTER TABLE accommodations
    MODIFY level ENUM('budget','midrange','highend','luxury','ultra_luxury') DEFAULT 'midrange';

ALTER TABLE bookings
    MODIFY accommodation_level ENUM('budget','midrange','highend','luxury','ultra_luxury') DEFAULT 'midrange';

ALTER TABLE itineraries
    MODIFY accommodation_level ENUM('budget','midrange','highend','luxury','ultra_luxury') DEFAULT 'midrange';

ALTER TABLE quotations
    MODIFY accommodation_level ENUM('budget','midrange','highend','luxury','ultra_luxury') DEFAULT 'midrange';

ALTER TABLE packages
    MODIFY accommodation_level ENUM('budget','midrange','highend','luxury','ultra_luxury') DEFAULT NULL;
