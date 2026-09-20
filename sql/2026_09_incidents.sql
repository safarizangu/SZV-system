-- Incident/emergency logging — the audit confirmed zero matches for
-- "emergency" or "incident" anywhere in the codebase. Emergency Contacts
-- (settings key, added earlier this session) covers reference info; this
-- is the actual event log — what happened, on which trip, who's handling
-- it, and whether it's resolved.
--
-- booking_ref is nullable: not every incident is trip-specific (a
-- vehicle issue discovered at the office, a supplier dispute) — forcing
-- a booking link on those would mean either picking an unrelated
-- booking or not logging the incident at all.

CREATE TABLE IF NOT EXISTS incidents (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref       VARCHAR(20) NULL,
    incident_type     ENUM('vehicle_breakdown','medical','weather_delay','accommodation_issue','guide_driver_issue','safety','other') NOT NULL DEFAULT 'other',
    severity          ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    description       TEXT NOT NULL,
    reported_by       INT NULL,
    status            ENUM('open','resolved') NOT NULL DEFAULT 'open',
    resolution_notes  TEXT,
    occurred_at       DATETIME NOT NULL,
    resolved_at       DATETIME NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_incidents_booking  FOREIGN KEY (booking_ref) REFERENCES bookings(booking_ref) ON DELETE SET NULL,
    CONSTRAINT fk_incidents_reporter FOREIGN KEY (reported_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
