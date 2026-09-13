-- Normalize the 7 tables created after the original schema (which all
-- inherited the MySQL 8.0 server default, utf8mb4_0900_ai_ci) onto the
-- same collation as the other 16 tables, utf8mb4_unicode_ci. Without this,
-- any join comparing a string column across the two groups (e.g.
-- booking_reminders.booking_ref <-> bookings.booking_ref) throws
-- "Illegal mix of collations" unless every such query remembers to add
-- an explicit COLLATE override.
ALTER TABLE activity_log      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE agent_targets     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE booking_reminders CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE commissions       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE email_queue       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE email_templates   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE settings          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
