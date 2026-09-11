<?php
namespace App\Services;

use App\Models\Database;

/**
 * Minimal "who did what" trail for admin mutations. Not a generic
 * event-sourcing system — just enough to answer "who changed this"
 * for the handful of actions worth auditing.
 */
class ActivityLog
{
    public static function record(string $action, string $entityType, ?int $entityId, array $details = []): void
    {
        try {
            $db = Database::getInstance();
            $actorId   = $_SESSION['admin_user_id'] ?? null;
            $actorName = $_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? 'Unknown';
            $db->prepare(
                "INSERT INTO activity_log (actor_id, actor_name, action, entity_type, entity_id, details)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([
                $actorId,
                $actorName,
                $action,
                $entityType,
                $entityId ?: null,
                $details ? json_encode($details) : null,
            ]);
        } catch (\Exception $e) {
            error_log('[ActivityLog] ' . $e->getMessage());
        }
    }
}
