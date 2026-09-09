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
     * A package's single "starting from" rate. The real total for an
     * actual booking/quotation is computed fresh from the traveler's
     * exact adults/children count via ItineraryQuoteBuilder, not from
     * this — $travelers is accepted for backward-compatible call sites
     * but no longer changes which column is read.
     */
    public function getPrice(array $package, int $travelers = 1): float
    {
        return (float)($package['price_1pax'] ?? 0);
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
