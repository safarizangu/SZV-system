<?php
namespace App\Models;

class TeamMember
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(): array
    {
        try {
            $tables = $this->db->query("SHOW TABLES LIKE 'team_members'")->fetchAll();
            if (empty($tables)) return [];
            return $this->db->query(
                "SELECT * FROM team_members WHERE is_active = 1 ORDER BY sort_order ASC, id ASC"
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }
}
