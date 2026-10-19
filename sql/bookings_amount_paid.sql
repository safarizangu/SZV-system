-- There's no bank/payment-gateway integration for manual bank transfers —
-- an admin marking a booking "Deposit Paid" is attesting to a real-world
-- transfer that already happened off-platform. Until now the system had
-- nowhere to record the actual amount that came in, only the suggested
-- deposit_amount computed at quote time — which a guest can plausibly
-- over- or under-pay in practice. This column is the real, admin-entered
-- figure that the Quotation/Invoice PDF and Track Booking now display.

ALTER TABLE bookings
    ADD COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER deposit_amount;
