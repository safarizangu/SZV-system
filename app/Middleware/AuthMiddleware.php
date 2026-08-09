<?php
namespace App\Middleware;

class AuthMiddleware
{
    public static function requireAdmin(): void
    {
        if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
            header('Location: ' . APP_URL . '/admin/gate.php');
            exit;
        }
        // Session timeout: 30 minutes of inactivity
        if (isset($_SESSION['admin_last_active'])
            && (time() - $_SESSION['admin_last_active']) > 1800) {
            session_destroy();
            session_start();
            header('Location: ' . APP_URL . '/admin/gate.php?reason=timeout');
            exit;
        }
        $_SESSION['admin_last_active'] = time();
    }

    public static function requirePin(): void
    {
        if (!isset($_SESSION['pin_verified']) || $_SESSION['pin_verified'] !== true) {
            header('Location: ' . APP_URL . '/admin/gate.php');
            exit;
        }
    }

    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
    }
}
