<?php
/**
 * Application bootstrap.
 * Include this at the top of every entry point.
 * Handles: autoload, config, session, helpers.
 */

// ── Composer-style autoloader ─────────────────────────────────
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