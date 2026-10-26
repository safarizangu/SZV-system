-- DB-4/DB-5/DB-6 remediation (audit findings): missing indexes behind
-- the admin dashboard's "recent activity" widgets and the agent
-- dashboard's commission lookups — confirmed via EXPLAIN (type:ALL +
-- filesort). Harmless today at current row counts, but each of these
-- columns is filtered/sorted on every dashboard load, so it matters
-- as soon as the data grows past a trivial size.
--
-- Run via: php sql/migrate.php

ALTER TABLE bookings
  ADD INDEX idx_bookings_created_at (created_at),
  ADD INDEX idx_bookings_payment_status (payment_status);

ALTER TABLE quotations
  ADD INDEX idx_quotations_created_at (created_at);

ALTER TABLE leads
  ADD INDEX idx_leads_created_at (created_at);

ALTER TABLE custom_safari_requests
  ADD INDEX idx_custom_requests_created_at (created_at);

ALTER TABLE commissions
  ADD INDEX idx_commissions_booking_ref (booking_ref);
