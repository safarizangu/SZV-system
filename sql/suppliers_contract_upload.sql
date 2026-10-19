-- Contract PDF on file for a supplier, kept outside the public web root
-- (storage/uploads/contracts/ — already blocked from direct access by
-- .htaccess, same as storage/uploads/quotations) and served only through
-- api/supplier_contract.php's own admin-auth check.

ALTER TABLE suppliers
    ADD COLUMN contract_filename      VARCHAR(255) NULL AFTER payment_terms,
    ADD COLUMN contract_original_name VARCHAR(255) NULL AFTER contract_filename,
    ADD COLUMN contract_uploaded_at   TIMESTAMP NULL AFTER contract_original_name;
