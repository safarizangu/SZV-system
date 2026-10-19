-- Agent-submitted custom safari requests have no quotation yet at creation
-- time, so OwnershipScope's usual "derive ownership from the linked
-- quotation" trick (see app/Services/OwnershipScope.php) can't apply —
-- there's nothing to derive from. This column is the one deliberate
-- exception: it records which agent personally submitted the request, so
-- they keep visibility of it even after an admin builds the quotation
-- under their own created_by.

ALTER TABLE custom_safari_requests
    ADD COLUMN submitted_by_agent_id INT NULL AFTER customer_id;

ALTER TABLE custom_safari_requests
    ADD CONSTRAINT fk_custom_requests_agent FOREIGN KEY (submitted_by_agent_id)
        REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE custom_safari_requests
    ADD INDEX idx_submitted_by_agent (submitted_by_agent_id);
