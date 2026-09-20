-- Supplier management — connects existing accommodations to the real
-- business entity SZV contracts/pays, instead of a disconnected
-- standalone directory. Park/activity operators and transport partners
-- go in the same table with a category, since none of those have their
-- own dedicated table to link from yet.

CREATE TABLE IF NOT EXISTS suppliers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    category        ENUM('accommodation','park_operator','activity_operator','transport','other') NOT NULL DEFAULT 'accommodation',
    contact_person  VARCHAR(150),
    phone           VARCHAR(50),
    email           VARCHAR(200),
    payment_terms   VARCHAR(255),
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    notes           TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE accommodations ADD COLUMN supplier_id INT NULL AFTER park_key;
ALTER TABLE accommodations ADD CONSTRAINT fk_accommodations_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;
