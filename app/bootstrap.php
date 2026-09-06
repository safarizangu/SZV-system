<?php
/**
 * Application bootstrap.
 * Include this at the top of every entry point.
 * Handles: autoload, config, session, helpers.
 */

// ── Composer autoloader (PHPMailer, dompdf, etc.) ──────────────
// Without this, `class_exists('\Dompdf\Dompdf')` is always false outside
// the cron scripts that happen to require this directly for PHPMailer —
// PDF attachments on quotation emails have been silently skipping
// themselves for exactly this reason, composer install notwithstanding.
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// ── App-specific autoloader ─────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $map = [
        'App\\Models\\'      => __DIR__ . '/Models/',
        'App\\Services\\'    => __DIR__ . '/Services/',
        'App\\Middleware\\'  => __DIR__ . '/Middleware/',
        'App\\Helpers\\'     => __DIR__ . '/Helpers/',
        'App\\Mail\\'        => __DIR__ . '/Mail/',
        'App\\Validators\\'  => __DIR__ . '/Validators/',
        'App\\Controllers\\' => __DIR__ . '/Controllers/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

// ── Config ────────────────────────────────────────────────────
// Load .env before config
if (file_exists(__DIR__.'/../.env')) {
    require_once __DIR__ . '/Helpers/Env.php';
    \App\Helpers\Env::load();
}
require_once __DIR__ . '/../config/app.php';

// Suppress errors in production — never expose paths/variables to browser
if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// ── Session ───────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',  // Strict prevents CSRF via cross-site requests
    ]);
    session_start();
}

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Africa/Dar_es_Salaam');

// ── Global helpers (backwards compat with legacy php/ files) ──
if (!function_exists('sanitize')) {
    function sanitize(string $s): string {
        return \App\Helpers\Formatter::sanitize($s);
    }
}
if (!function_exists('formatCurrency')) {
    function formatCurrency(float $amount, string $currency = 'USD'): string {
        return \App\Helpers\Formatter::currency($amount, $currency);
    }
}
if (!function_exists('generateBookingRef')) {
    function generateBookingRef(): string {
        return \App\Helpers\Formatter::generateRef();
    }
}
if (!function_exists('accommodationLabel')) {
    function accommodationLabel(string $level): string {
        return \App\Helpers\Formatter::accommodationLabel($level);
    }
}
// Legacy Database class alias
if (!class_exists('Database')) {
    class Database extends \App\Models\Database {}
}


// ── Helper functions (moved from php/config.php) ──────────────

if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(string $token): bool {
        return isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('decodeJsonField')) {
    function decodeJsonField(mixed $val, mixed $default = []): mixed {
        if (is_array($val)) return $val;
        if (empty($val))   return $default;
        $decoded = json_decode($val, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $default;
    }
}

if (!function_exists('getAllPackages')) {
    function getAllPackages(): array {
        try {
            $db = App\Models\Database::getInstance();
            return $db->query(
                "SELECT * FROM packages WHERE is_active=1 ORDER BY sort_order ASC, id ASC"
            )->fetchAll();
        } catch (\Exception $e) {
            error_log('[getAllPackages] ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getPackageBySlug')) {
    function getPackageBySlug(string $slug): ?array {
        try {
            $db   = App\Models\Database::getInstance();
            $stmt = $db->prepare(
                "SELECT * FROM packages WHERE slug=? AND is_active=1 LIMIT 1"
            );
            $stmt->execute([$slug]);
            return $stmt->fetch() ?: null;
        } catch (\Exception $e) {
            error_log('[getPackageBySlug] ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('sanitize')) {
    function sanitize(mixed $val): string {
        return htmlspecialchars(trim((string)$val), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e')) {
    function e(mixed $val): string {
        return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
    }
}
