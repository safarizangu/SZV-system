-- Backfills the (already-existing, previously unused) team_members.member_type
-- column so the About page can split sales agents/partners out of "Our Team"
-- into their own "Our Partners" section. Anything with "Agent" in its role
-- (e.g. "Sales Agent — UK") is flagged 'agent'; everyone else defaults to
-- 'team', matching how public/about.php now reads this column.
-- INSERT IGNORE-equivalent safety: only touches rows where member_type is
-- still unset, so it's safe to re-run and won't clobber a manual edit made
-- via the admin panel afterward.
--
-- Run via: php sql/migrate.php

UPDATE team_members
SET member_type = 'agent'
WHERE (member_type IS NULL OR member_type = '')
  AND role LIKE '%Agent%';

UPDATE team_members
SET member_type = 'team'
WHERE member_type IS NULL OR member_type = '';
