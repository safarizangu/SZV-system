<?php
namespace App\Services;

/**
 * Rejects an unauthorized request and records it in the activity log.
 * Used at the top of every protected action, before any side effect runs.
 */
class AccessGuard
{
    public static function denyJson(string $entity, string $target, string $attempted): never
    {
        self::log($entity, $target, $attempted);
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    public static function denyPlain(string $entity, string $target, string $attempted): never
    {
        self::log($entity, $target, $attempted);
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Permission denied.';
        exit;
    }

    public static function denyRedirect(string $location, string $entity, string $target, string $attempted): never
    {
        self::log($entity, $target, $attempted);
        header('Location: ' . $location);
        exit;
    }

    private static function log(string $entity, string $target, string $attempted): void
    {
        ActivityLog::record('access_denied', $entity, null, [
            'target'    => $target,
            'attempted' => $attempted,
            'role'      => $_SESSION['admin_role'] ?? null,
            'uri'       => $_SERVER['REQUEST_URI'] ?? null,
        ]);
    }
}
