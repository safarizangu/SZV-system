<?php

if (!defined('APP_URL')) {
    require_once __DIR__ . '/../app/bootstrap.php';
}

// ============================================================
// Safari Zangu Ventures – Configuration
// ============================================================

// --- DEBUG: Show errors on local, hide on production ---
// Set SAFARI_ENV=production on your live server to hide errors
$isProduction = (getenv('SAFARI_ENV') === 'production');
ini_set('display_errors', $isProduction ? 0 : 1);
ini_set('display_startup_errors', $isProduction ? 0 : 1);
error_reporting($isProduction ? 0 : E_ALL);

// --- Database credentials ---
if (!defined('DB_HOST')) define('DB_HOST',    'localhost');
if (!defined('DB_USER')) define('DB_USER',    'root');
if (!defined('DB_PASS')) define('DB_PASS',    'Sdg@201819');
if (!defined('DB_NAME')) define('DB_NAME',    'safari_zangu');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// --- Hostinger credentials ---
// if (!defined('DB_HOST')) define('DB_HOST',    'localhost');
// if (!defined('DB_USER')) define('DB_USER',    'u662187129_admin');
// if (!defined('DB_PASS')) define('DB_PASS',    '$5TJebl55mL');
// if (!defined('DB_NAME')) define('DB_NAME',    'u662187129_safari_zangu');
// if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// --- Site config ---
if (!defined('SITE_NAME')) define('SITE_NAME',       'Safari Zangu Ventures');

// Auto-detect: local dev vs production
$_szHost    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_szIsLocal = (strpos($_szHost, 'localhost') !== false ||
               strpos($_szHost, '127.0.0.1') !== false);
if (!defined('SITE_URL')) define('SITE_URL', $_szIsLocal ? 'http://' . $_szHost . '/safari-zangu' : 'https://safarizanguven.com');

if (!defined('SITE_EMAIL')) define('SITE_EMAIL',      'info@safarizanguven.com');
if (!defined('WHATSAPP_NUMBER')) define('WHATSAPP_NUMBER', '255663430934');

// --- Payment details ---
if (!defined('BANK_NAME')) define('BANK_NAME',         'ABSA Bank Tanzania');
if (!defined('BANK_ACCOUNT_NAME')) define('BANK_ACCOUNT_NAME', 'Safari Zangu Ventures Ltd');
if (!defined('BANK_ACCOUNT_NO')) define('BANK_ACCOUNT_NO',   '1234567890');
if (!defined('BANK_SWIFT')) define('BANK_SWIFT',        'BARCTZTZXXX');
if (!defined('WISE_EMAIL')) define('WISE_EMAIL',        'payments@safarizanguven.com');
if (!defined('PAYPAL_EMAIL')) define('PAYPAL_EMAIL',      'payments@safarizanguven.com');

// --- PayPal (Sandbox by default — switch CLIENT_ID + MODE for live) ---
if (!defined('PAYPAL_MODE')) define('PAYPAL_MODE',      'sandbox'); // 'sandbox' or 'live'
if (!defined('PAYPAL_CLIENT_ID')) define('PAYPAL_CLIENT_ID', 'sb-your-sandbox-client-id'); // Replace with your real Client ID
if (!defined('PAYPAL_CURRENCY')) define('PAYPAL_CURRENCY',  'USD');

// --- SMTP / PHPMailer (used by includes/mailer.php) ---
if (!defined('SMTP_HOST')) define('SMTP_HOST',       'smtp.hostinger.com');
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME',   'admin@safarizanguven.com');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD',   '0xwm-lhjc-uwlm-rcu5');
if (!defined('SMTP_PORT')) define('SMTP_PORT',       465);
if (!defined('SMTP_SECURE')) define('SMTP_SECURE',     'ssl'); // 'ssl' or 'tls'
if (!defined('MAIL_FROM_EMAIL')) define('MAIL_FROM_EMAIL', 'admin@safarizanguven.com');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME',  'Safari Zangu Ventures');

// --- Admin access PIN gate (required before reaching /admin) ---
if (!defined('ADMIN_PIN')) define('ADMIN_PIN', '2024SZV!');

// --- Accommodation tiers ---
if (!defined('ACCOMMODATION_LEVELS')) define('ACCOMMODATION_LEVELS', [
    'budget'   => 'Budget-Friendly',
    'midrange' => 'Mid-Range',
    'highend'      => 'High-End',
    'ultra_luxury' => 'Ultra Luxury',
]);

// --- Booking rules ---
if (!defined('DEPOSIT_PERCENT')) define('DEPOSIT_PERCENT', 50);
if (!defined('BALANCE_DAYS')) define('BALANCE_DAYS',    14);

// --- Database singleton ---
if (!class_exists('Database')) {
class Database {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode=(SELECT REPLACE(@@SESSION.sql_mode,'ONLY_FULL_GROUP_BY',''))",
                PDO::ATTR_EMULATE_PREPARES   => false,
            );
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (\PDOException $e) {
                http_response_code(500);
                die(
                    '<h2 style="color:red">Database connection failed</h2>' .
                    '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>' .
                    '<p>Check DB_HOST, DB_NAME, DB_USER, DB_PASS in <code>php/config.php</code></p>'
                );
            }
        }
        return self::$instance;
    }
}
} // end class_exists Database

// --- Session ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Helpers ---
if (!function_exists('generateBookingRef')) {
function generateBookingRef() {
    return 'SZV' . date('Y') . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
}
}


if (!function_exists('sanitize')) {
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}
}


if (!function_exists('formatCurrency')) {
function formatCurrency($amount, $symbol = 'USD') {
    return $symbol . ' ' . number_format((float)$amount, 0, '.', ',');
}
}


if (!function_exists('generateCsrfToken')) {
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
}


if (!function_exists('verifyCsrfToken')) {
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
}


if (!function_exists('isAdminLoggedIn')) {
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}
}


if (!function_exists('requireAdmin')) {
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
}


// --- Admin PIN gate (checked before reaching the login page) ---
function isAdminPinVerified() {
    return isset($_SESSION['admin_pin_ok']) && $_SESSION['admin_pin_ok'] === true;
}

if (!function_exists('requireAdminPin')) {
function requireAdminPin() {
    if (!isAdminPinVerified()) {
        // Not authorized to even see the admin area — bounce to home
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}
}


// --- Accommodation level helpers ---
if (!function_exists('normalizeAccommodationLevel')) {
function normalizeAccommodationLevel($level) {
    $level = strtolower(trim((string)$level));
    $map = ['budget' => 'budget', 'mid-range' => 'midrange', 'midrange' => 'midrange',
            'mid_range' => 'midrange', 'high-end' => 'highend', 'highend' => 'highend',
            'high_end' => 'highend'];
    return $map[$level] ?? 'midrange';
}
}


if (!function_exists('accommodationLabel')) {
function accommodationLabel($level) {
    $levels = ACCOMMODATION_LEVELS;
    return $levels[normalizeAccommodationLevel($level)] ?? 'Mid-Range';
}
}


/**
 * Get the correct per-person price for a package given accommodation
 * level and traveler count (1 / 2 / 3+ pax tiers).
 */
if (!function_exists('getPackagePrice')) {
function getPackagePrice($package, $accommodationLevel, $travelers) {
    // DB only has price_1pax / price_2pax / price_3pax (not level-tiered)
    // Use traveler count tier only; accommodation level is separate concept
    $travelers = max(1, (int)$travelers);
    if ($travelers == 1)     $col = 'price_1pax';
    elseif ($travelers == 2) $col = 'price_2pax';
    else                      $col = 'price_3pax';
    return isset($package[$col]) ? (float)$package[$col] : 0.0;
}
}


if (!function_exists('getAllPackages')) {
function getAllPackages($activeOnly = true) {
    $pdo = Database::getInstance();
    try {
        // Ensure table exists and check which columns exist to avoid fatal errors on older DB schemas
        $tables = $pdo->query("SHOW TABLES LIKE 'packages'")->fetchAll();
        if (empty($tables)) return [];
        $cols = $pdo->query("SHOW COLUMNS FROM packages")->fetchAll(PDO::FETCH_COLUMN);
        $hasActive    = in_array('is_active',  $cols);
        $hasSortOrder = in_array('sort_order', $cols);
        $where  = ($activeOnly && $hasActive) ? " WHERE is_active = 1" : "";
        $order  = $hasSortOrder ? " ORDER BY sort_order ASC, id ASC" : " ORDER BY id ASC";
        return $pdo->query("SELECT * FROM packages" . $where . $order)->fetchAll();
    } catch (\Exception $e) {
        return [];
    }
}
}



if (!function_exists('getTeamMembers')) {
function getTeamMembers() {
    $pdo = Database::getInstance();
    try {
        // Check table exists first
        $tables = $pdo->query("SHOW TABLES LIKE 'team_members'")->fetchAll();
        if (empty($tables)) return [];
        $stmt = $pdo->query("SELECT * FROM team_members WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        return $stmt->fetchAll();
    } catch (\Exception $e) {
        return [];
    }
}
}


if (!function_exists('getPackageBySlug')) {
function getPackageBySlug($slug) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM packages WHERE slug = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}
}


if (!function_exists('getPackageById')) {
function getPackageById($id) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch();
}
}


if (!function_exists('decodeJsonField')) {
function decodeJsonField($value) {
    if (empty($value)) return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}
}