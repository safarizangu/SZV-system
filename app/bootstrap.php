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

// ── Security headers (PHP-level, independent of Apache config) ─
// .htaccess also defines these, but only takes effect if mod_headers is
// enabled on the host — confirmed disabled on this environment (and
// unverifiable on the Hostinger production account without host access),
// which silently drops every header below site-wide. Setting them here
// means they apply regardless of server module state.
if (!headers_sent() && php_sapi_name() !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.paypal.com https://www.googletagmanager.com https://www.google-analytics.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://www.paypal.com https://www.google-analytics.com https://analytics.google.com; frame-src https://www.paypal.com https://www.google.com;");
}

// Suppress errors in production — never expose paths/variables to browser
if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// ── Global exception/fatal-error handler ────────────────────────
// Previously there was none — any exception thrown outside a local
// try/catch was genuinely uncaught. An HTML page request happened to be
// covered by .htaccess's ErrorDocument 500 (only when mod_headers-style
// output hadn't already started), but every api/*.php endpoint has no
// fallback at all: a fatal error there returns a truncated or empty body,
// which the frontend's `.then(r => r.json())` sees as a JSON parse error
// instead of a clean {"success":false} response.
if (php_sapi_name() !== 'cli') {
    $__szvIsApi = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
    $__szvDebug = defined('APP_DEBUG') && APP_DEBUG;

    // DEVOPS-5: previously nothing alerted anyone to a fatal error — the
    // only way to notice one was tailing a log by hand (see
    // docs/SECURITY_CHECKLIST.md's Monitoring section), on a site taking
    // live PayPal payments. Sends a direct SMTP email (bypassing the
    // email_queue/DB entirely — if the fatal error IS a DB outage, this
    // still has to work) to ADMIN_EMAIL, throttled to at most one every 15
    // minutes via a lock file's mtime, so one bad request can't flood the
    // inbox. Best-effort only: every failure here is swallowed so the
    // alert mechanism itself can never cause a second fatal error.
    $__szvAlertAdmin = function (string $message, string $file, int $line): void {
        try {
            if (!defined('ADMIN_EMAIL') || !ADMIN_EMAIL || !class_exists('\PHPMailer\PHPMailer\PHPMailer')) return;
            $throttleFile = __DIR__ . '/../storage/logs/.fatal_alert_throttle';
            if (is_file($throttleFile) && (time() - filemtime($throttleFile)) < 900) return;
            @touch($throttleFile);

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port    = SMTP_PORT;
            $mail->Timeout = 8;
            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress(ADMIN_EMAIL);
            $mail->Subject = '[Safari Zangu] Server error — ' . ($_SERVER['HTTP_HOST'] ?? 'unknown host');
            $mail->Body    = "A server error occurred:\n\n{$message}\nin {$file}:{$line}\n\n"
                . 'URL: ' . ($_SERVER['REQUEST_URI'] ?? 'CLI') . "\n"
                . 'Time: ' . date('Y-m-d H:i:s') . "\n\n"
                . "(Further alerts are suppressed for 15 minutes.)";
            $mail->send();
        } catch (\Throwable $e) {
            error_log('[SZV AlertAdmin] failed to send fatal-error alert: ' . $e->getMessage());
        }
    };

    $__szvHandleFatal = function (string $message, string $file, int $line) use (&$__szvIsApi, &$__szvDebug, &$__szvAlertAdmin): void {
        error_log("[SZV Uncaught] $message in $file:$line");
        $__szvAlertAdmin($message, $file, $line);
        if (headers_sent()) return;
        while (ob_get_level()) { ob_end_clean(); }
        http_response_code(500);
        if ($__szvIsApi) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $__szvDebug ? $message : 'An unexpected error occurred. Please try again or contact us.',
            ]);
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!doctype html><html><head><meta charset="UTF-8"><title>Server Error</title></head><body style="font-family:sans-serif;text-align:center;padding:4rem 1rem">'
               . '<h1 style="font-size:1.5rem">Something went wrong</h1>'
               . '<p style="color:#6b7280">Our team has been notified. Please try again in a moment, or WhatsApp us at +255 663 430 934.</p>'
               . ($__szvDebug ? '<pre style="text-align:left;max-width:800px;margin:2rem auto;background:#f3f4f6;padding:1rem;overflow:auto">' . htmlspecialchars("$message in $file:$line") . '</pre>' : '')
               . '</body></html>';
        }
    };

    set_exception_handler(function (\Throwable $e) use ($__szvHandleFatal): void {
        $__szvHandleFatal($e->getMessage(), $e->getFile(), $e->getLine());
    });

    register_shutdown_function(function () use ($__szvHandleFatal): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $__szvHandleFatal($error['message'], $error['file'], $error['line']);
        }
    });
}

// ── Session ───────────────────────────────────────────────────
// PERF-5: PHP's default session cache limiter ('nocache') sends
// Cache-Control: no-store on literally every response once session_start()
// runs, including the fully static marketing pages below that have
// nothing session-specific to protect and would otherwise be cacheable.
// Disabling the limiter and sending that header ourselves, only for
// paths that actually carry session-specific or personal data (the
// admin panel, agent portal, api/, and every other public/*.php page —
// book.php/confirmation.php/track.php/review.php/contact.php each show
// or submit something tied to one specific visitor or booking), keeps
// the original protection exactly where it matters.
session_cache_limiter('');
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
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    $__szvScript = $_SERVER['SCRIPT_NAME'] ?? '';
    $__szvCacheablePages = ['/index.php', '/about.php', '/packages.php', '/privacy-policy.php', '/sustainability.php', '/terms.php'];
    $__szvIsCacheable = false;
    foreach ($__szvCacheablePages as $__szvPage) {
        if (str_ends_with($__szvScript, '/public' . $__szvPage)) { $__szvIsCacheable = true; break; }
    }
    if (!$__szvIsCacheable) {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
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
if (!function_exists('gbpApprox')) {
    function gbpApprox(float $usdAmount): string {
        return \App\Helpers\Formatter::gbpApprox($usdAmount);
    }
}
if (!function_exists('approxAll')) {
    function approxAll(float $usdAmount): string {
        return \App\Helpers\Formatter::approxAll($usdAmount);
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

// ARCH-7: was redefined identically as a bare global function in 4
// separate admin/*.php files (itineraries.php, itinerary_edit.php,
// package_edit.php, quotation_new.php) — a "Cannot redeclare" fatal
// risk if any two of them were ever included in the same request.
if (!function_exists('fetchAll')) {
    function fetchAll(\PDO $db, string $sql): array {
        try { return $db->query($sql)->fetchAll(); }
        catch (\Exception $e) { return []; }
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


