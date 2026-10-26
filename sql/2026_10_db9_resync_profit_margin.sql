-- DB-9 remediation (audit finding): packages.profit_margin_percent (a
-- stored, write-only column — nothing reads it directly; the dashboard
-- always reads vw_package_profitability's live computation instead) had
-- gone stale for 5 of 6 packages (0.00% stored vs. real margins per the
-- view). admin/package_edit.php's own save handler already computes this
-- with the exact same formula the view uses — these rows just predate
-- that being saved consistently, or selling_price/operational_cost
-- changed afterward through a path that doesn't re-save this column.
-- One-time resync to the view's own live formula; package_edit.php's
-- existing save handler keeps it in sync from here on.
--
-- Run via: php sql/migrate.php

UPDATE packages
SET profit_margin_percent = ROUND(((selling_price - operational_cost) / NULLIF(selling_price, 0)) * 100, 2)
WHERE selling_price IS NOT NULL;
