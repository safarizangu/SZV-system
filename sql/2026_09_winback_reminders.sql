-- Loyalty/repeat-sales automation — the audit confirmed "repeat-customer
-- count is visible; no automated win-back/loyalty flow." Adds a 5th
-- reminder type reusing the existing booking_reminders/Mailer
-- infrastructure (deposit/balance/departure/post_trip already work this
-- way) rather than building a second, parallel scheduling system.

ALTER TABLE booking_reminders MODIFY COLUMN type ENUM('deposit_due','balance_due','departure','post_trip','win_back','custom') NOT NULL;
