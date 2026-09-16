<?php
namespace App\Middleware;

class AuthMiddleware
{
    public static function requireAdmin(): void
    {
        if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
            header('Location: ' . APP_URL . '/admin/login.php?reason=required');
            exit;
        }
        // Session timeout: 30 minutes of inactivity
        if (isset($_SESSION['admin_last_active'])
            && (time() - $_SESSION['admin_last_active']) > 1800) {
            session_destroy();
            session_start();
            header('Location: ' . APP_URL . '/admin/login.php?reason=timeout');
            exit;
        }
        $_SESSION['admin_last_active'] = time();
    }

    public static function requirePin(): void
    {
        if (!isset($_SESSION['pin_verified']) || $_SESSION['pin_verified'] !== true) {
            header('Location: ' . APP_URL . '/admin/login.php?reason=required');
            exit;
        }
    }

    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
    }

    /** True for admin/superadmin — reserved for genuinely admin-only actions
     *  (Settings pages, package pricing, deleting other people's records) —
     *  not the same thing as "sees everything," see isAgentRole() below. */
    public static function isAdminRole(): bool
    {
        return in_array($_SESSION['admin_role'] ?? 'agent', ['admin', 'superadmin'], true);
    }

    /**
     * True only for the 'agent' role — the one role restricted to its own
     * records (leads, quotations, commissions, invoices) and its own
     * dashboard. admin/superadmin/staff are all treated as "sees
     * everything within the sections available to them"; only agent gets
     * per-record ownership filtering. Kept as its own check (rather than
     * reusing !isAdminRole()) specifically so staff doesn't inherit an
     * agent's restrictions just for not being admin/superadmin.
     */
    public static function isAgentRole(): bool
    {
        return ($_SESSION['admin_role'] ?? 'agent') === 'agent';
    }
}
