<?php
/**
 * Safari Zangu Ventures — Application Configuration
 *
 * SECRETS: Never hardcode live credentials here.
 * Load from .env (via loadEnv() below) or server environment variables.
 * Every secret below must come from env() with NO literal fallback —
 * a hardcoded fallback here is a hardcoded credential, committed to git.
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
// Authoritative signal for a real web request: the Host header of the
// request that is actually hitting this server right now. This must NOT
// be overridable by a .env value — a stale .env (e.g. production's copy
// still carrying APP_URL=http://localhost/safari-zangu from local setup)
// previously won regardless of what domain the visitor was really on,
// silently sending every booking-confirmation redirect, admin login
// redirect, tracking-link email, and PDF logo URL to localhost in
// production. The live Host header can't go stale like that.
//
// CLI runs (cron, sql/migrate.php, tests) have no Host header at all, so
// there they fall back to .env's APP_ENV — the only signal available in
// that context — defaulting to production if even that's unset, since a
// misconfigured cron job should fail safe toward the real domain, not
// toward localhost.
$_isCli       = php_sapi_name() === 'cli';
$_host        = $_SERVER['HTTP_HOST'] ?? '';
$_hostIsLocal = str_contains($_host, 'localhost') || str_contains($_host, '127.0.0.1');
$_isLocal     = $_isCli ? (strtolower((string)env('APP_ENV', 'production')) === 'local') : $_hostIsLocal;

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
// Derived only — no .env override for APP_ENV/APP_URL on a real web
// request, for the same reason BASE_PATH above takes none. A CLI run
// still respects .env's APP_ENV (set above, via $_isLocal) since that's
// the only signal it has.
if (!defined('APP_ENV'))   define('APP_ENV',   $_isLocal ? 'local' : 'production');
if (!defined('APP_DEBUG')) define('APP_DEBUG', APP_ENV === 'local');
if (!defined('APP_NAME'))  define('APP_NAME',  'Safari Zangu Ventures');
if (!defined('APP_URL'))   define('APP_URL',
    $_isLocal ? 'http://' . ($_host ?: 'localhost') . BASE_PATH : 'https://safarizanguven.com');

ini_set('display_errors',         APP_DEBUG ? 1 : 0);
ini_set('display_startup_errors', APP_DEBUG ? 1 : 0);
error_reporting(APP_DEBUG ? E_ALL : 0);

// ── Database ──────────────────────────────────────────────────
if (!defined('DB_HOST'))    define('DB_HOST',    env('DB_HOST',    'localhost'));
if (!defined('DB_USER'))    define('DB_USER',    env('DB_USER',    $_isLocal ? 'root' : 'u662187129_admin'));
if (!defined('DB_PASS'))    define('DB_PASS',    env('DB_PASS', ''));
if (!defined('DB_NAME'))    define('DB_NAME',    env('DB_NAME',    $_isLocal ? 'safari_zangu' : 'u662187129_safari_zangu'));
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ── DB-backed setting overrides ─────────────────────────────────
// admin/settings.php lets an admin tune business rules (VAT, deposit %,
// bank details, WhatsApp number, etc.) from the UI, stored in the
// existing `settings` key/value table (sql/create_settings.sql — already
// live, previously only holding hero_image). Reading it back here, once,
// means every one of the ~40 call sites across the app that reference
// the constants below needs no change at all — they just see whichever
// value wins: DB setting, then .env, then the hardcoded default.
//
// Deliberately NOT App\Models\Database::getInstance() — that die()s the
// whole request on a connection failure, correct for a page that
// actually needs the DB but wrong here: config/app.php loads on every
// request, including ones that never touch the DB, so a blip here must
// fall through to the hardcoded defaults silently, never take the site
// down. Short connection timeout for the same reason.
$_settingsOverride = [];
try {
    $_setPdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
        DB_USER, DB_PASS,
        [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $_settingsOverride = $_setPdo->query("SELECT `key`,`value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    unset($_setPdo);
} catch (\Throwable $e) {
    // Fresh install (table doesn't exist yet) or DB briefly unreachable —
    // either way, every _setting() call below just returns its default.
}
function _setting(string $key, mixed $default): mixed {
    global $_settingsOverride;
    return (array_key_exists($key, $_settingsOverride) && $_settingsOverride[$key] !== '')
        ? $_settingsOverride[$key]
        : $default;
}

// ── Site identity ─────────────────────────────────────────────
if (!defined('SITE_NAME'))        define('SITE_NAME',        'Safari Zangu Ventures');
if (!defined('SITE_URL'))         define('SITE_URL',         APP_URL);
if (!defined('SITE_EMAIL'))       define('SITE_EMAIL',       _setting('site_email', 'info@safarizanguven.com'));
if (!defined('ADMIN_EMAIL'))      define('ADMIN_EMAIL',      _setting('admin_email', env('ADMIN_EMAIL', 'admin@safarizanguven.com')));
if (!defined('WHATSAPP_NUMBER'))  define('WHATSAPP_NUMBER',  _setting('whatsapp_number', '255663430934'));

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

// ── Anthropic (admin/poster_maker.php's AI Caption feature) ────
// Set ANTHROPIC_API_KEY in .env to enable — the feature degrades to a
// clear "not configured" message rather than failing silently when unset.
if (!defined('ANTHROPIC_API_KEY')) define('ANTHROPIC_API_KEY', env('ANTHROPIC_API_KEY', ''));

// ── Bank details ──────────────────────────────────────────────
// Editable from admin/settings.php — falls back to .env, then these
// hardcoded placeholders. Never hardcode real live banking here.
if (!defined('BANK_NAME'))         define('BANK_NAME',         _setting('bank_name', env('BANK_NAME', 'ABSA Bank Tanzania')));
if (!defined('BANK_ACCOUNT_NAME')) define('BANK_ACCOUNT_NAME', _setting('bank_account_name', env('BANK_ACCOUNT_NAME', 'Safari Zangu Ventures Ltd')));
if (!defined('BANK_ACCOUNT_NO'))   define('BANK_ACCOUNT_NO',   _setting('bank_account_no', env('BANK_ACCOUNT_NO', 'XXXXXXXXXX')));
if (!defined('BANK_SWIFT'))        define('BANK_SWIFT',        _setting('bank_swift', env('BANK_SWIFT', 'BARCTZTZXXX')));
if (!defined('WISE_EMAIL'))        define('WISE_EMAIL',        _setting('wise_email', env('WISE_EMAIL', 'payments@safarizanguven.com')));

// ── Analytics ─────────────────────────────────────────────────
// Set GA4_ID=G-YOURCODE in .env
if (!defined('GA4_ID')) define('GA4_ID', env('GA4_ID', ''));

// ── ADMIN_PIN — DISABLED ─────────────────────────────────────
// PIN login is permanently disabled. A random unpredictable value is used
// so even if the code path is somehow reached, it cannot authenticate.
// DO NOT replace this with a real PIN.
if (!defined('ADMIN_PIN')) define('ADMIN_PIN', bin2hex(random_bytes(32)));

// ── Cron ─────────────────────────────────────────────────────
// Must be set in .env — no default. If unset, process_email_queue.php's
// bearer check always fails closed rather than accepting a guessable value.
if (!defined('CRON_SECRET')) define('CRON_SECRET', env('CRON_SECRET', ''));

// ── Business rules ────────────────────────────────────────────
// All editable from admin/settings.php — see the DB-backed override
// block above. Each still has its original hardcoded value as the
// final fallback if no override is set.
if (!defined('DEPOSIT_PERCENT'))     define('DEPOSIT_PERCENT',     (int)_setting('deposit_percent', 25));   // % deposit at booking
if (!defined('BALANCE_DAYS'))        define('BALANCE_DAYS',        (int)_setting('balance_days', 45));      // balance due N days before travel
if (!defined('VAT_RATE'))            define('VAT_RATE',            (float)_setting('vat_rate', 0.18));
if (!defined('SERVICE_CHARGE_RATE')) define('SERVICE_CHARGE_RATE', (float)_setting('service_charge_rate', 0.05));

// Display-only USD→GBP rate for showing "~£X" next to package/homepage
// prices, for UK visitors who convert in their heads. All actual charging,
// invoicing and quotation math stays in USD — this never touches payment
// amounts. Rounded rather than fetched live, and needs a manual update
// occasionally (checked against openexchangerates 20 Sep 2026: ~0.748).
if (!defined('GBP_RATE')) define('GBP_RATE', (float)_setting('gbp_rate', 0.75));

// ── Lead, quotation & party-size rules ──────────────────────────
// Also editable from admin/settings.php.
if (!defined('DEFAULT_COMMISSION_RATE')) define('DEFAULT_COMMISSION_RATE', (float)_setting('default_commission_rate', 10));   // % used when an agent has no rate of their own set — see PipelineSync::createCommission()
if (!defined('QUOTATION_VALIDITY_DAYS')) define('QUOTATION_VALIDITY_DAYS', (int)_setting('quotation_validity_days', 14));     // default "valid until" window on a new quotation
if (!defined('MAX_PARTY_SIZE'))          define('MAX_PARTY_SIZE',          (int)_setting('max_party_size', 60));              // upper bound on adults+children for a single public booking — see BookingValidator

// ── Platform controls ────────────────────────────────────────────
// Feature toggles, also editable from admin/settings.php.
if (!defined('MAINTENANCE_MODE'))               define('MAINTENANCE_MODE',               (bool)(int)_setting('maintenance_mode', 0));                 // when on, public booking + custom-request submission politely decline — see api/book.php, api/custom_safari.php
if (!defined('AGENT_DIRECT_BOOKING_ENABLED'))   define('AGENT_DIRECT_BOOKING_ENABLED',   (bool)(int)_setting('agent_direct_booking_enabled', 1));    // when off, agents can still submit custom requests but not book packages directly — see agent-portal/leads.php
if (!defined('AUTO_SEND_PAYMENT_EMAILS'))       define('AUTO_SEND_PAYMENT_EMAILS',       (bool)(int)_setting('auto_send_payment_emails', 1));        // when off, a Deposit Paid/Fully Paid status change still saves normally but skips the branded Invoice/Receipt email — see admin/bookings.php, api/record_payment.php

// ── Security ──────────────────────────────────────────────────────
if (!defined('SESSION_TIMEOUT_MINUTES')) define('SESSION_TIMEOUT_MINUTES', max(5, (int)_setting('session_timeout_minutes', 30)));  // inactivity timeout for admin/agent sessions — see AuthMiddleware
if (!defined('TRACK_LOCKOUT_ATTEMPTS'))  define('TRACK_LOCKOUT_ATTEMPTS',  max(1, (int)_setting('track_lockout_attempts', 3)));    // failed Track Booking lookups before a temporary lockout — see api/track_*.php
if (!defined('TRACK_LOCKOUT_MINUTES'))   define('TRACK_LOCKOUT_MINUTES',   max(1, (int)_setting('track_lockout_minutes', 15)));    // how long that lockout lasts

// ── Documents ──────────────────────────────────────────────────────
if (!defined('SUPPLIER_CONTRACT_MAX_MB')) define('SUPPLIER_CONTRACT_MAX_MB', max(1, (int)_setting('supplier_contract_max_mb', 15)));  // upload size cap for a supplier's Contract PDF — see admin/suppliers.php

// ── Accommodation levels ──────────────────────────────────────
if (!defined('ACCOMMODATION_LEVELS')) define('ACCOMMODATION_LEVELS', [
    'budget'       => 'Budget-Friendly',
    'midrange'     => 'Mid-Range',
    'highend'      => 'High-End',
    'luxury'       => 'Luxury',
    'ultra_luxury' => 'Ultra Luxury',
]);

// PARK_FEES, VEHICLE_TYPES, SEASONAL_RATES, ACTIVITY_PRICES, GUIDE_LANGUAGES,
// MEAL_PLAN_RATES, and CONSERVATION_FEE were removed here — they existed
// solely as a DB-unavailable fallback inside the now-deleted
// App\Services\QuotationEngine (see git history), which had zero real
// callers and was superseded by App\Services\ItineraryQuoteBuilder, the
// live pricing engine. ItineraryQuoteBuilder reads park_fees, vehicles,
// seasonal_rates, and activities directly from the database with no
// config-array fallback, so these had been dead weight — and a real
// drift risk (the DB park_fees table had moved on to different rates
// than this file still had hardcoded) — since that deletion.
