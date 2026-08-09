-- Performance indexes — run once in phpMyAdmin → safari_zangu → SQL
-- Safe to run even if some already exist (will error on duplicate, just skip those)

ALTER TABLE `bookings`
  ADD INDEX `idx_travel_date`    (`travel_date`),
  ADD INDEX `idx_payment_status` (`payment_status`),
  ADD INDEX `idx_created_at`     (`created_at`);

ALTER TABLE `analytics_events`
  ADD INDEX `idx_ae_created_at`  (`created_at`),
  ADD INDEX `idx_ae_event_name`  (`event_name`),
  ADD INDEX `idx_ae_package`     (`package_slug`);

ALTER TABLE `quotations`
  ADD INDEX `idx_q_status`       (`status`),
  ADD INDEX `idx_q_valid_until`  (`valid_until`),
  ADD INDEX `idx_q_created_at`   (`created_at`);

ALTER TABLE `leads`
  ADD INDEX `idx_l_stage`        (`pipeline_stage`),
  ADD INDEX `idx_l_created_at`   (`created_at`);

ALTER TABLE `contact_messages`
  ADD INDEX `idx_cm_is_read`     (`is_read`),
  ADD INDEX `idx_cm_created_at`  (`created_at`);

ALTER TABLE `custom_safari_requests`
  ADD INDEX `idx_csr_status`     (`status`),
  ADD INDEX `idx_csr_created_at` (`created_at`);

ALTER TABLE `packages`
  ADD INDEX `idx_pkg_active`     (`is_active`),
  ADD INDEX `idx_pkg_sort`       (`sort_order`);
