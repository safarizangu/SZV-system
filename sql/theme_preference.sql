-- Per-account dark/light theme preference for the admin panel, set from
-- admin/settings.php and applied via data-theme="" on <html> in
-- layouts/admin.php. Defaults to 'light' so existing accounts keep the
-- current (white sidebar / light content) look until they opt into dark.

ALTER TABLE `admin_users` ADD COLUMN `theme_preference` ENUM('light','dark') NOT NULL DEFAULT 'light' AFTER `can_delete_records`;
