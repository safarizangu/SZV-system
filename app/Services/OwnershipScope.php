<?php
namespace App\Services;

use App\Middleware\AuthMiddleware;

/**
 * Single source of truth for "which records does this agent own".
 *
 * Ownership is derived, not stored, because bookings and custom safari
 * requests have no owner column of their own:
 *  - a booking belongs to the agent who created its quotation, or the
 *    agent holding a commission on it;
 *  - a custom request belongs to the agent who created a quotation from it.
 *
 * Staff, admin and superadmin are never scoped here — callers should use
 * isScoped() first and only apply these checks for agents.
 */
class OwnershipScope
{
    public static function isScoped(): bool
    {
        return AuthMiddleware::isAgentRole();
    }

    public static function currentAgentId(): int
    {
        return (int)($_SESSION['admin_user_id'] ?? 0);
    }

    /** SQL fragment for list queries over `bookings` (alias-free table name). Returns [sql, params]. */
    public static function bookingsListSql(int $agentId): array
    {
        return [
            "(bookings.quotation_id IN (SELECT id FROM quotations WHERE created_by = ?)
              OR bookings.booking_ref IN (SELECT booking_ref FROM commissions WHERE agent_id = ? AND booking_ref IS NOT NULL))",
            [$agentId, $agentId],
        ];
    }

    /** True if the given booking_ref belongs to the agent. */
    public static function ownsBooking(\PDO $db, string $bookingRef, int $agentId): bool
    {
        [$sql, $params] = self::bookingsListSql($agentId);
        $stmt = $db->prepare("SELECT 1 FROM bookings WHERE booking_ref = ? AND $sql LIMIT 1");
        $stmt->execute(array_merge([$bookingRef], $params));
        return (bool)$stmt->fetchColumn();
    }

    /** SQL fragment for list queries over `custom_safari_requests`. Returns [sql, params]. */
    public static function customRequestsListSql(int $agentId): array
    {
        return [
            "custom_safari_requests.id IN (SELECT custom_request_id FROM quotations WHERE created_by = ? AND custom_request_id IS NOT NULL)",
            [$agentId],
        ];
    }

    /** True if the given custom request id belongs to the agent. */
    public static function ownsCustomRequest(\PDO $db, int $requestId, int $agentId): bool
    {
        $stmt = $db->prepare("SELECT 1 FROM quotations WHERE custom_request_id = ? AND created_by = ? LIMIT 1");
        $stmt->execute([$requestId, $agentId]);
        return (bool)$stmt->fetchColumn();
    }

    /** True if the quotation was created by the agent. */
    public static function ownsQuotation(\PDO $db, int $quotationId, int $agentId): bool
    {
        $stmt = $db->prepare("SELECT 1 FROM quotations WHERE id = ? AND created_by = ? LIMIT 1");
        $stmt->execute([$quotationId, $agentId]);
        return (bool)$stmt->fetchColumn();
    }
}
