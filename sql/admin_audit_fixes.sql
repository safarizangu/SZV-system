-- Admin System Audit — schema fixes
-- Run once against the live database. Safe to re-run (guards included).
--
-- 1. quotations.lead_id      — links a quotation back to the lead it was created from
-- 2. quotations.created_by   — the admin_users row that created the quotation (agent ownership)
-- 3. Missing FKs/indexes flagged by the audit (0 orphans on 6 of 7; leads.agent_id had 1
--    placeholder row of 0, nulled below before the FK is added)

-- ── quotations.lead_id ──────────────────────────────────────────
ALTER TABLE quotations
    ADD COLUMN lead_id INT NULL AFTER custom_request_id;

ALTER TABLE quotations
    ADD CONSTRAINT fk_quotations_lead FOREIGN KEY (lead_id)
        REFERENCES leads(id) ON DELETE SET NULL;

-- ── quotations.created_by ───────────────────────────────────────
ALTER TABLE quotations
    ADD COLUMN created_by INT NULL AFTER lead_id;

ALTER TABLE quotations
    ADD CONSTRAINT fk_quotations_created_by FOREIGN KEY (created_by)
        REFERENCES admin_users(id) ON DELETE SET NULL;

-- ── leads.agent_id — clear the one placeholder (0) row before adding the FK ─
UPDATE leads SET agent_id = NULL WHERE agent_id = 0;

-- ── Missing foreign keys (audit item: no referential integrity) ────────────
ALTER TABLE quotations
    ADD CONSTRAINT fk_quotations_package FOREIGN KEY (package_id)
        REFERENCES packages(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_quotations_vehicle FOREIGN KEY (vehicle_id)
        REFERENCES vehicles(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_quotations_booking FOREIGN KEY (booking_id)
        REFERENCES bookings(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_quotations_custom_request FOREIGN KEY (custom_request_id)
        REFERENCES custom_safari_requests(id) ON DELETE SET NULL;

ALTER TABLE packages
    ADD CONSTRAINT fk_packages_itinerary FOREIGN KEY (itinerary_id)
        REFERENCES itineraries(id) ON DELETE SET NULL;

ALTER TABLE leads
    ADD CONSTRAINT fk_leads_assigned_to FOREIGN KEY (assigned_to)
        REFERENCES admin_users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_leads_agent FOREIGN KEY (agent_id)
        REFERENCES admin_users(id) ON DELETE SET NULL;

-- ── Lightweight audit log ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    actor_id    INT NULL,
    actor_name  VARCHAR(150) NOT NULL,
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50)  NOT NULL,
    entity_id   INT NULL,
    details     JSON NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_entity (entity_type, entity_id),
    INDEX idx_activity_actor (actor_id),
    INDEX idx_activity_created (created_at),
    CONSTRAINT fk_activity_actor FOREIGN KEY (actor_id)
        REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
