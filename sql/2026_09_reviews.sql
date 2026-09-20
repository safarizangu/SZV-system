-- Customer reviews / NPS capture — the post-trip email already links to
-- external Google/TripAdvisor review pages, but nothing internal existed
-- to capture structured feedback SZV can act on or (once moderated)
-- display as vetted testimonials.
--
-- One review per booking (UNIQUE booking_ref) — prevents repeat
-- submissions inflating scores, and matches "how was YOUR trip" as a
-- one-time ask, not an ongoing thread.

CREATE TABLE IF NOT EXISTS reviews (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref    VARCHAR(20) NOT NULL,
    customer_id    INT NULL,
    guest_name     VARCHAR(150) NOT NULL,
    guest_country  VARCHAR(100),
    rating         TINYINT UNSIGNED NOT NULL,
    nps_score      TINYINT UNSIGNED NULL,
    comment        TEXT,
    is_published   TINYINT(1) NOT NULL DEFAULT 0,
    submitted_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_booking_review (booking_ref),
    CONSTRAINT fk_reviews_booking  FOREIGN KEY (booking_ref) REFERENCES bookings(booking_ref) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT chk_reviews_rating  CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT chk_reviews_nps     CHECK (nps_score IS NULL OR nps_score BETWEEN 0 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
