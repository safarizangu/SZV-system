-- DB-2 remediation (audit finding): analytics_events had no pruning or
-- archival anywhere in the codebase, and its only index
-- (event_name, created_at) doesn't serve the many admin/analytics.php
-- queries that filter on created_at alone (confirmed via EXPLAIN: full
-- index scan) — a composite index only helps a leftmost-prefix query,
-- and filtering on the second column without the first gets no benefit
-- from it. Adds a standalone index on created_at for those; the existing
-- composite index is untouched since the event_name-filtered queries
-- still benefit from it.
--
-- Run via: php sql/migrate.php

ALTER TABLE analytics_events
  ADD INDEX idx_analytics_created_at (created_at);
