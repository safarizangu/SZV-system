-- commissions and agent_targets were both created with no indexes beyond
-- their primary key and no foreign keys at all, despite being queried by
-- agent_id/lead_id/status. Both tables are empty today, so this is zero
-- risk now and expensive to retrofit once real data exists.

-- agent_id is NOT NULL on both tables — no ON DELETE clause (defaults to
-- RESTRICT) so an agent with commission/target history can't be deleted
-- out from under their own financial records; reassign or deactivate
-- (admin_users.is_active) instead of deleting.
ALTER TABLE commissions
    ADD INDEX idx_commissions_agent (agent_id),
    ADD INDEX idx_commissions_lead (lead_id),
    ADD INDEX idx_commissions_status (status),
    ADD CONSTRAINT fk_commissions_agent FOREIGN KEY (agent_id) REFERENCES admin_users(id),
    ADD CONSTRAINT fk_commissions_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_commissions_approved_by FOREIGN KEY (approved_by) REFERENCES admin_users(id) ON DELETE SET NULL;

ALTER TABLE agent_targets
    ADD CONSTRAINT fk_agent_targets_agent FOREIGN KEY (agent_id) REFERENCES admin_users(id);

ALTER TABLE analytics_events
    ADD INDEX idx_analytics_event_created (event_name, created_at);
