-- admin/bookings.php's Edit Booking dialog (and api/booking_pdf.php's own
-- "Country" field, which already read $b['country'] but had no column to
-- read it from — it silently always showed "—") both need somewhere to
-- store this. Populated going forward by all three booking-creation paths
-- (api/book.php's "nationality" field, the agent portal's direct-booking
-- form, and api/convert_quotation.php carrying over the quotation's own
-- client_country); existing bookings are simply NULL until edited.

ALTER TABLE bookings
    ADD COLUMN country VARCHAR(100) NULL AFTER phone;
