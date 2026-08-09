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

    public function all(bool $activeOnly = true): array
    {
        $cols   = array_column($this->db->query("SHOW COLUMNS FROM packages")->fetchAll(), 'Field');
        $where  = ($activeOnly && in_array('is_active', $cols)) ? ' WHERE is_active = 1' : '';
        $order  = in_array('sort_order', $cols) ? ' ORDER BY sort_order ASC, id ASC' : ' ORDER BY id ASC';
        return $this->db->query("SELECT * FROM packages{$where}{$order}")->fetchAll();
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM packages WHERE slug = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM packages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Calculate price for a package given pax tier.
     * paxTier: 1 = solo, 2 = couple, 3+ = group
     */
    public function getPrice(array $package, int $travelers): float
    {
        $tier = match(true) {
            $travelers >= 3 => '3',
            $travelers === 2 => '2',
            default         => '1',
        };
        // Support both old schema (price_1pax) and new tiered schema
        $col = "price_{$tier}pax";
        return (float)($package[$col] ?? $package['price_2pax'] ?? 0);
    }

    public function decode(array $package): array
    {
        foreach (['itinerary', 'includes', 'excludes', 'gallery_images'] as $field) {
            if (!empty($package[$field]) && is_string($package[$field])) {
                $decoded = json_decode($package[$field], true);
                $package[$field] = is_array($decoded) ? $decoded : [];
            }
        }
        return $package;
    }
}
