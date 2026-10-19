-- Track Booking moves from the weak "booking ref + travel date" proof of
-- ownership (both of which a third party could plausibly guess or see on
-- a shared screen) to a real one-time password, generated server-side and
-- emailed once at booking-creation time, same as every other credential
-- in this app is handled: hashed at rest, never stored or logged in
-- plaintext. See app/Services/BookingTracking.php.

ALTER TABLE bookings
    ADD COLUMN tracking_password_hash VARCHAR(255) NULL AFTER booking_ref;
