<?php
namespace App\Models;

/**
 * Unified customer identity — the dedup point for bookings, quotations,
 * leads, and custom safari requests, which each previously stored
 * name/email/phone independently with no shared record.
 */
class Customer
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Look up a customer by email (case/whitespace-insensitive), creating
     * one if none exists. Fills in phone/country if the existing record
     * is missing them, but never overwrites a value that's already set —
     * the record represents the best-known info across every touchpoint,
     * not just the most recent one.
     */
    public function findOrCreateByEmail(string $email, string $fullName, ?string $phone = null, ?string $country = null): ?int
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $fullName = trim($fullName) ?: 'Guest';
        $phone    = $phone   !== null ? trim($phone)   : null;
        $country  = $country !== null ? trim($country) : null;

        $stmt = $this->db->prepare("SELECT id, full_name, phone, country FROM customers WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $updates = [];
            $params  = [];
            if (empty($existing['phone']) && !empty($phone)) {
                $updates[] = 'phone = ?';
                $params[]  = $phone;
            }
            if (empty($existing['country']) && !empty($country)) {
                $updates[] = 'country = ?';
                $params[]  = $country;
            }
            if ($updates) {
                $params[] = $existing['id'];
                $this->db->prepare("UPDATE customers SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?")
                    ->execute($params);
            }
            return (int)$existing['id'];
        }

        $this->db->prepare("
            INSERT INTO customers (full_name, email, phone, country, first_seen_at)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([$fullName, $email, $phone, $country]);

        return (int)$this->db->lastInsertId();
    }
}
