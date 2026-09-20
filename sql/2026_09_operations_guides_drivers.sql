-- Operations Management: Guide/Driver roster + per-booking assignment.
--
-- Safari Zangu's own pricing already treats guide and driver as one role
-- (ItineraryQuoteBuilder's default line item is literally "Professional
-- Driver Guide & Service Fee") — this models that same reality instead
-- of inventing a separate-roles structure the business doesn't use.
--
-- booking_assignments is intentionally one row per booking (UNIQUE on
-- booking_ref): "who is on this trip" is a single current answer, not a
-- history log. staff_id/vehicle_id are nullable so a booking can be
-- assigned a vehicle before a guide is confirmed, or vice versa.

CREATE TABLE IF NOT EXISTS staff (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(150) NOT NULL,
    role            ENUM('guide_driver','guide','driver') NOT NULL DEFAULT 'guide_driver',
    phone           VARCHAR(50),
    email           VARCHAR(200),
    languages       VARCHAR(255),
    license_number  VARCHAR(100),
    daily_rate      DECIMAL(10,2) NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    notes           TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_assignments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref  VARCHAR(20) NOT NULL,
    staff_id     INT NULL,
    vehicle_id   INT NULL,
    notes        TEXT,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_booking_ref (booking_ref),
    CONSTRAINT fk_assignments_booking FOREIGN KEY (booking_ref) REFERENCES bookings(booking_ref) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_staff   FOREIGN KEY (staff_id)    REFERENCES staff(id)    ON DELETE SET NULL,
    CONSTRAINT fk_assignments_vehicle FOREIGN KEY (vehicle_id)  REFERENCES vehicles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
