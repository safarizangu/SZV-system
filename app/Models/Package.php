<?php
namespace App\Models;

/**
 * Package model — wraps the packages table.
 * All raw DB access lives here. Zero HTML.
 */
class Package
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM packages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
