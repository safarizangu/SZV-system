-- Quote status expansion and view tracking.
--  - 'viewed' is set automatically when a client opens their quote link.
--  - 'negotiating' is a manual stage between sent/viewed and accepted.
--  - The prompt's "rejected" is the existing 'declined' value (not duplicated).
--  - public_token is the only key the client-facing view link uses; it is
--    random, unique, and never reveals the quotation id.
ALTER TABLE quotations
  MODIFY COLUMN status ENUM('draft','sent','viewed','negotiating','accepted','declined','expired','converted') NOT NULL DEFAULT 'draft',
  ADD COLUMN public_token VARCHAR(64) NULL UNIQUE,
  ADD COLUMN view_count INT NOT NULL DEFAULT 0,
  ADD COLUMN first_viewed_at DATETIME NULL,
  ADD COLUMN last_viewed_at DATETIME NULL;
