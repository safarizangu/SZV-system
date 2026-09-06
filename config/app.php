<?php
/**
 * Safari Zangu Ventures — Application Configuration
 *
 * SECRETS: Never hardcode live credentials here.
 * Load from .env (via loadEnv() below) or server environment variables.
 * This file is safe to commit — it contains no real credentials.
 */

// ── Environment loader ────────────────────────────────────────
function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key); $val = trim($val, " \t\n\r\0\x0B\"'");
        if ($key && !isset($_ENV[$key])) {
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
}
loadEnv(__DIR__ . '/../.env');

function env(string $key, mixed $default = null): mixed {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ── Host detection ────────────────────────────────────────────
$_host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_isLocal = str_contains($_host, 'localhost') || str_contains($_host, '127.0.0.1');

if (!defined('BASE_PATH')) {
    // Local dev on this machine serves the project as a plain subfolder of a
    // shared multi-project Apache docroot (/var/www/html), with NO Alias
    // mapping /safari-zangu -> this project's public/ — confirmed live:
    // requesting /assets/css/main.css 404s, while /safari-zangu/assets/css/main.css
    // returns 200. Every root-relative link (nav, forms, images, CSS) needs
    // the /safari-zangu prefix here, or the whole site 404s on every asset
    // and internal link, not just stylesheets.
    // If an Apache Alias for /safari-zangu -> public/ is ever added (see
    // LOCAL-DEV-SETUP.md), switch this back to '' for local too.
    // Production serves from public_html root, so BASE_PATH stays ''.
    define('BASE_PATH', $_isLocal ? '/safari-zangu' : '');
}

// ── App environment ───────────────────────────────────────────
if (!defined('APP_ENV'))   define('APP_ENV',   env('APP_ENV', $_isLocal ? 'local' : 'production'));
if (!defined('APP_DEBUG')) define('APP_DEBUG', APP_ENV === 'local');
if (!defined('APP_NAME'))  define('APP_NAME',  'Safari Zangu Ventures');
if (!defined('APP_URL'))   define('APP_URL',
    env('APP_URL', $_isLocal ? 'http://'.$_host.BASE_PATH : 'https://safarizanguven.com'));

ini_set('display_errors',         APP_DEBUG ? 1 : 0);
ini_set('display_startup_errors', APP_DEBUG ? 1 : 0);
error_reporting(APP_DEBUG ? E_ALL : 0);

// ── Database ──────────────────────────────────────────────────
if (!defined('DB_HOST'))    define('DB_HOST',    env('DB_HOST',    'localhost'));
if (!defined('DB_USER'))    define('DB_USER',    env('DB_USER',    $_isLocal ? 'root' : 'u662187129_admin'));
if (!defined('DB_PASS'))    define('DB_PASS',    env('DB_PASS',    $_isLocal ? 'Sdg@201819' : ''));
if (!defined('DB_NAME'))    define('DB_NAME',    env('DB_NAME',    $_isLocal ? 'safari_zangu' : 'u662187129_safari_zangu'));
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ── Site identity ─────────────────────────────────────────────
if (!defined('SITE_NAME'))        define('SITE_NAME',        'Safari Zangu Ventures');
if (!defined('SITE_URL'))         define('SITE_URL',         APP_URL);
if (!defined('SITE_EMAIL'))       define('SITE_EMAIL',       'info@safarizanguven.com');
if (!defined('ADMIN_EMAIL'))      define('ADMIN_EMAIL',      env('ADMIN_EMAIL', 'admin@safarizanguven.com'));
if (!defined('WHATSAPP_NUMBER'))  define('WHATSAPP_NUMBER',  '255663430934');

// ── Mail / SMTP ───────────────────────────────────────────────
if (!defined('SMTP_HOST'))       define('SMTP_HOST',       env('SMTP_HOST',     'smtp.hostinger.com'));
if (!defined('SMTP_PORT'))       define('SMTP_PORT',       (int)env('SMTP_PORT', 465));
if (!defined('SMTP_SECURE'))     define('SMTP_SECURE',     env('SMTP_SECURE',   'ssl'));
if (!defined('SMTP_USERNAME'))   define('SMTP_USERNAME',   env('SMTP_USERNAME', 'admin@safarizanguven.com'));
if (!defined('SMTP_PASSWORD'))   define('SMTP_PASSWORD',   env('SMTP_PASSWORD', ''));
if (!defined('MAIL_FROM_NAME'))  define('MAIL_FROM_NAME',  'Safari Zangu Ventures');
if (!defined('MAIL_FROM_EMAIL')) define('MAIL_FROM_EMAIL', env('SMTP_USERNAME', 'admin@safarizanguven.com'));

// ── PayPal ────────────────────────────────────────────────────
// Set PAYPAL_MODE=live and real PAYPAL_CLIENT_ID/PAYPAL_CLIENT_SECRET in .env before go-live
if (!defined('PAYPAL_MODE'))        define('PAYPAL_MODE',        env('PAYPAL_MODE',        'sandbox'));
if (!defined('PAYPAL_CLIENT_ID'))   define('PAYPAL_CLIENT_ID',   env('PAYPAL_CLIENT_ID',   'sb-your-sandbox-client-id'));
if (!defined('PAYPAL_CLIENT_SECRET')) define('PAYPAL_CLIENT_SECRET', env('PAYPAL_CLIENT_SECRET', ''));
if (!defined('PAYPAL_CURRENCY'))    define('PAYPAL_CURRENCY',    'USD');
if (!defined('PAYPAL_API_BASE'))    define('PAYPAL_API_BASE',    PAYPAL_MODE === 'live'
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com');

// ── Bank details ──────────────────────────────────────────────
// Set real account details in .env — never hardcode live banking here
if (!defined('BANK_NAME'))         define('BANK_NAME',         env('BANK_NAME',         'ABSA Bank Tanzania'));
if (!defined('BANK_ACCOUNT_NAME')) define('BANK_ACCOUNT_NAME', env('BANK_ACCOUNT_NAME', 'Safari Zangu Ventures Ltd'));
if (!defined('BANK_ACCOUNT_NO'))   define('BANK_ACCOUNT_NO',   env('BANK_ACCOUNT_NO',   'XXXXXXXXXX'));
if (!defined('BANK_SWIFT'))        define('BANK_SWIFT',        env('BANK_SWIFT',        'BARCTZTZXXX'));
if (!defined('WISE_EMAIL'))        define('WISE_EMAIL',        env('WISE_EMAIL',        'payments@safarizanguven.com'));

// ── Analytics ─────────────────────────────────────────────────
// Set GA4_ID=G-YOURCODE in .env
if (!defined('GA4_ID')) define('GA4_ID', env('GA4_ID', ''));

// ── ADMIN_PIN — DISABLED ─────────────────────────────────────
// PIN login is permanently disabled. A random unpredictable value is used
// so even if the code path is somehow reached, it cannot authenticate.
// DO NOT replace this with a real PIN.
if (!defined('ADMIN_PIN')) define('ADMIN_PIN', bin2hex(random_bytes(32)));

// ── Cron ─────────────────────────────────────────────────────
if (!defined('CRON_SECRET')) define('CRON_SECRET', env('CRON_SECRET', 'szv-cron-2026'));

// ── Business rules ────────────────────────────────────────────
if (!defined('DEPOSIT_PERCENT'))     define('DEPOSIT_PERCENT',     25);   // 25% deposit at booking
if (!defined('BALANCE_DAYS'))        define('BALANCE_DAYS',        45);   // balance due 45 days before travel
if (!defined('VAT_RATE'))            define('VAT_RATE',            0.18);
if (!defined('SERVICE_CHARGE_RATE')) define('SERVICE_CHARGE_RATE', 0.05);
if (!defined('CONSERVATION_FEE'))    define('CONSERVATION_FEE',    20);

// ── Park fees ─────────────────────────────────────────────────
if (!defined('PARK_FEES')) define('PARK_FEES', [
    'serengeti'    => ['adult'=>70,  'child'=>20, 'name'=>'Serengeti National Park'],
    'ngorongoro'   => ['adult'=>70,  'child'=>20, 'name'=>'Ngorongoro Conservation Area'],
    'tarangire'    => ['adult'=>53,  'child'=>18, 'name'=>'Tarangire National Park'],
    'lake_manyara' => ['adult'=>53,  'child'=>18, 'name'=>'Lake Manyara National Park'],
    'arusha_np'    => ['adult'=>53,  'child'=>18, 'name'=>'Arusha National Park'],
    'ruaha'        => ['adult'=>53,  'child'=>18, 'name'=>'Ruaha National Park'],
    'nyerere'      => ['adult'=>50,  'child'=>15, 'name'=>'Nyerere National Park'],
    'zanzibar'     => ['adult'=>0,   'child'=>0,  'name'=>'Zanzibar Island'],
]);

// ── Vehicles ─────────────────────────────────────────────────
if (!defined('VEHICLE_TYPES')) define('VEHICLE_TYPES', [
    'land_cruiser'     => ['name'=>'Toyota Land Cruiser',          'capacity'=>6,  'daily_rate'=>250],
    'extended_cruiser' => ['name'=>'Extended Toyota Land Cruiser', 'capacity'=>7,  'daily_rate'=>300],
    'luxury_cruiser'   => ['name'=>'Luxury Land Cruiser (A/C)',    'capacity'=>6,  'daily_rate'=>380],
    'minivan'          => ['name'=>'Safari Minivan',               'capacity'=>12, 'daily_rate'=>220],
]);

// ── Accommodation levels ──────────────────────────────────────
if (!defined('ACCOMMODATION_LEVELS')) define('ACCOMMODATION_LEVELS', [
    'budget'       => 'Budget-Friendly',
    'midrange'     => 'Mid-Range',
    'highend'      => 'High-End / Luxury',
    'ultra_luxury' => 'Ultra Luxury',
]);

// ── Seasonal rates ────────────────────────────────────────────
if (!defined('SEASONAL_RATES')) define('SEASONAL_RATES', [
    'peak'=>1.0, 'shoulder'=>0.85, 'low'=>0.75,
]);

// ── Activity prices ───────────────────────────────────────────
if (!defined('ACTIVITY_PRICES')) define('ACTIVITY_PRICES', [
    'balloon_safari'  => ['name'=>'Hot Air Balloon Safari',     'price'=>599, 'unit'=>'per_person'],
    'walking_safari'  => ['name'=>'Walking Safari (2h)',        'price'=>75,  'unit'=>'per_person'],
    'night_drive'     => ['name'=>'Night Game Drive',           'price'=>80,  'unit'=>'per_person'],
    'dhow_cruise'     => ['name'=>'Zanzibar Dhow Cruise',       'price'=>65,  'unit'=>'per_person'],
    'maasai_visit'    => ['name'=>'Maasai Village Visit',       'price'=>45,  'unit'=>'per_person'],
    'stone_town_tour' => ['name'=>'Stone Town Walking Tour',    'price'=>55,  'unit'=>'per_person'],
    'spice_tour'      => ['name'=>'Zanzibar Spice Farm Tour',   'price'=>40,  'unit'=>'per_person'],
    'snorkeling'      => ['name'=>'Snorkeling at Mnemba Atoll', 'price'=>85,  'unit'=>'per_person'],
    'coffee_tour'     => ['name'=>'Coffee Farm & Culture Tour', 'price'=>35,  'unit'=>'per_person'],
    'crater_hike'     => ['name'=>'Ngorongoro Crater Rim Hike', 'price'=>50,  'unit'=>'per_person'],
]);

// ── Guide languages ───────────────────────────────────────────
if (!defined('GUIDE_LANGUAGES')) define('GUIDE_LANGUAGES', [
    'english'=>['name'=>'English',          'daily_rate'=>120],
    'french' =>['name'=>'French',           'daily_rate'=>150],
    'german' =>['name'=>'German',           'daily_rate'=>150],
    'italian'=>['name'=>'Italian',          'daily_rate'=>150],
    'spanish'=>['name'=>'Spanish',          'daily_rate'=>150],
    'chinese'=>['name'=>'Mandarin Chinese', 'daily_rate'=>180],
    'swahili'=>['name'=>'Swahili',          'daily_rate'=>100],
]);

// ── Meal plans ────────────────────────────────────────────────
if (!defined('MEAL_PLAN_RATES')) define('MEAL_PLAN_RATES', [
    'fb'=>['name'=>'Full Board (B+L+D)', 'rate'=>95],
    'hb'=>['name'=>'Half Board (B+D)',   'rate'=>65],
    'bb'=>['name'=>'Bed & Breakfast',    'rate'=>30],
    'ai'=>['name'=>'All Inclusive',      'rate'=>140],
]);
