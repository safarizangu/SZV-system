-- Per-user permission overrides for admin/staff accounts — lets an admin
-- restrict a specific user's ability to change record status or delete
-- records, independent of their role. Both default to 1 (allowed) so
-- existing accounts keep behaving exactly as before until an admin
-- explicitly unchecks one for a given user. superadmin always bypasses
-- both (see AuthMiddleware::canChangeStatus()/canDeleteRecords()) so an
-- admin can never accidentally lock out every superadmin account.

ALTER TABLE `admin_users` ADD COLUMN `can_change_status` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`;
ALTER TABLE `admin_users` ADD COLUMN `can_delete_records` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_change_status`;
