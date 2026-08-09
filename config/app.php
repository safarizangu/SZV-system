<?php
/**
 * Safari Zangu Ventures — Application Configuration
 */

$_host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_isLocal = (str_contains($_host, 'localhost') || str_contains($_host, '127.0.0.1'));

// BASE_PATH: '/safari-zangu' on local, '' on Hostinger root
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $_isLocal ? '/safari-zangu' : '');
}

if (!defined('APP_ENV'))   define('APP_ENV',   $_isLocal ? 'local' : 'production');
if (!defined('APP_DEBUG')) define('APP_DEBUG', APP_ENV === 'local');
if (!defined('APP_NAME'))  define('APP_NAME',  'Safari Zangu Ventures');
if (!defined('APP_URL'))   define('APP_URL',
    $_isLocal ? 'http://'.$_host.BASE_PATH : 'https://safarizanguven.com');

ini_set('display_errors',         APP_DEBUG ? 1 : 0);
ini_set('display_startup_errors', APP_DEBUG ? 1 : 0);
error_reporting(APP_DEBUG ? E_ALL : 0);

if (!defined('DB_HOST'))    define('DB_HOST',    'localhost');
if (!defined('DB_USER'))    define('DB_USER',    APP_ENV === 'local' ? 'root'         : 'u662187129_admin');
if (!defined('DB_PASS'))    define('DB_PASS',    APP_ENV === 'local' ? 'Sdg@201819'   : '$5TJebl55mL');
if (!defined('DB_NAME'))    define('DB_NAME',    APP_ENV === 'local' ? 'safari_zangu' : 'u662187129_safari_zangu');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

if (!defined('SITE_NAME'))        define('SITE_NAME',        'Safari Zangu Ventures');
if (!defined('SITE_URL'))         define('SITE_URL',         APP_URL);
if (!defined('SITE_EMAIL'))       define('SITE_EMAIL',       'info@safarizanguven.com');
if (!defined('ADMIN_EMAIL'))      define('ADMIN_EMAIL',      'admin@safarizanguven.com');
if (!defined('WHATSAPP_NUMBER'))  define('WHATSAPP_NUMBER',  '255663430934');

if (!defined('SMTP_HOST'))       define('SMTP_HOST',       'smtp.hostinger.com');
if (!defined('SMTP_PORT'))       define('SMTP_PORT',       465);
if (!defined('SMTP_SECURE'))     define('SMTP_SECURE',     'ssl');
if (!defined('SMTP_USERNAME'))   define('SMTP_USERNAME',   'admin@safarizanguven.com');
if (!defined('SMTP_PASSWORD'))   define('SMTP_PASSWORD',   '0xwm-lhjc-uwlm-rcu5');
if (!defined('MAIL_FROM_NAME'))  define('MAIL_FROM_NAME',  'Safari Zangu Ventures');
if (!defined('MAIL_FROM_EMAIL')) define('MAIL_FROM_EMAIL', 'admin@safarizanguven.com');

if (!defined('PAYPAL_MODE'))      define('PAYPAL_MODE',      'sandbox');
if (!defined('PAYPAL_CLIENT_ID')) define('PAYPAL_CLIENT_ID', 'sb-your-sandbox-client-id');
if (!defined('PAYPAL_CURRENCY'))  define('PAYPAL_CURRENCY',  'USD');

if (!defined('BANK_NAME'))         define('BANK_NAME',         'ABSA Bank Tanzania');
if (!defined('BANK_ACCOUNT_NAME')) define('BANK_ACCOUNT_NAME', 'Safari Zangu Ventures Ltd');
if (!defined('BANK_ACCOUNT_NO'))   define('BANK_ACCOUNT_NO',   '1234567890');
if (!defined('BANK_SWIFT'))        define('BANK_SWIFT',        'BARCTZTZXXX');
if (!defined('WISE_EMAIL'))        define('WISE_EMAIL',        'payments@safarizanguven.com');

if (!defined('ADMIN_PIN'))    define('ADMIN_PIN',    '2024SZV!');
if (!defined('CRON_SECRET'))  define('CRON_SECRET',  'szv-cron-2026');

if (!defined('DEPOSIT_PERCENT'))     define('DEPOSIT_PERCENT',     50);
if (!defined('BALANCE_DAYS'))        define('BALANCE_DAYS',        14);
if (!defined('VAT_RATE'))            define('VAT_RATE',            0.18);
if (!defined('SERVICE_CHARGE_RATE')) define('SERVICE_CHARGE_RATE', 0.05);
if (!defined('CONSERVATION_FEE'))    define('CONSERVATION_FEE',    20);

if (!defined('PARK_FEES')) define('PARK_FEES', [
    'serengeti'    => ['adult'=>70,'child'=>20,'name'=>'Serengeti National Park'],
    'ngorongoro'   => ['adult'=>70,'child'=>20,'name'=>'Ngorongoro Conservation Area'],
    'tarangire'    => ['adult'=>53,'child'=>18,'name'=>'Tarangire National Park'],
    'lake_manyara' => ['adult'=>53,'child'=>18,'name'=>'Lake Manyara National Park'],
    'arusha_np'    => ['adult'=>53,'child'=>18,'name'=>'Arusha National Park'],
    'ruaha'        => ['adult'=>53,'child'=>18,'name'=>'Ruaha National Park'],
    'nyerere'      => ['adult'=>50,'child'=>15,'name'=>'Nyerere National Park'],
    'zanzibar'     => ['adult'=>0, 'child'=>0, 'name'=>'Zanzibar Island'],
]);

if (!defined('VEHICLE_TYPES')) define('VEHICLE_TYPES', [
    'land_cruiser'     => ['name'=>'Toyota Land Cruiser',          'capacity'=>6,  'daily_rate'=>250],
    'extended_cruiser' => ['name'=>'Extended Toyota Land Cruiser', 'capacity'=>7,  'daily_rate'=>300],
    'luxury_cruiser'   => ['name'=>'Luxury Land Cruiser (A/C)',    'capacity'=>6,  'daily_rate'=>380],
    'minivan'          => ['name'=>'Safari Minivan',               'capacity'=>12, 'daily_rate'=>220],
]);

if (!defined('ACCOMMODATION_LEVELS')) define('ACCOMMODATION_LEVELS', [
    'budget'       => 'Budget-Friendly',
    'midrange'     => 'Mid-Range',
    'highend'      => 'High-End / Luxury',
    'ultra_luxury' => 'Ultra Luxury',
]);

if (!defined('SEASONAL_RATES')) define('SEASONAL_RATES', [
    'peak'=>1.0, 'shoulder'=>0.85, 'low'=>0.75,
]);

if (!defined('ACTIVITY_PRICES')) define('ACTIVITY_PRICES', [
    'balloon_safari'  => ['name'=>'Hot Air Balloon Safari',     'price'=>599,'unit'=>'per_person'],
    'walking_safari'  => ['name'=>'Walking Safari (2h)',        'price'=>75, 'unit'=>'per_person'],
    'night_drive'     => ['name'=>'Night Game Drive',           'price'=>80, 'unit'=>'per_person'],
    'dhow_cruise'     => ['name'=>'Zanzibar Dhow Cruise',       'price'=>65, 'unit'=>'per_person'],
    'maasai_visit'    => ['name'=>'Maasai Village Visit',       'price'=>45, 'unit'=>'per_person'],
    'stone_town_tour' => ['name'=>'Stone Town Walking Tour',    'price'=>55, 'unit'=>'per_person'],
    'spice_tour'      => ['name'=>'Zanzibar Spice Farm Tour',   'price'=>40, 'unit'=>'per_person'],
    'snorkeling'      => ['name'=>'Snorkeling at Mnemba Atoll', 'price'=>85, 'unit'=>'per_person'],
    'coffee_tour'     => ['name'=>'Coffee Farm & Culture Tour', 'price'=>35, 'unit'=>'per_person'],
    'crater_hike'     => ['name'=>'Ngorongoro Crater Rim Hike', 'price'=>50, 'unit'=>'per_person'],
]);

if (!defined('GUIDE_LANGUAGES')) define('GUIDE_LANGUAGES', [
    'english'=>['name'=>'English',         'daily_rate'=>120],
    'french' =>['name'=>'French',          'daily_rate'=>150],
    'german' =>['name'=>'German',          'daily_rate'=>150],
    'italian'=>['name'=>'Italian',         'daily_rate'=>150],
    'spanish'=>['name'=>'Spanish',         'daily_rate'=>150],
    'chinese'=>['name'=>'Mandarin Chinese','daily_rate'=>180],
    'swahili'=>['name'=>'Swahili',         'daily_rate'=>100],
]);

if (!defined('MEAL_PLAN_RATES')) define('MEAL_PLAN_RATES', [
    'fb'=>['name'=>'Full Board (B+L+D)', 'rate'=>95],
    'hb'=>['name'=>'Half Board (B+D)',   'rate'=>65],
    'bb'=>['name'=>'Bed & Breakfast',    'rate'=>30],
    'ai'=>['name'=>'All Inclusive',      'rate'=>140],
]);
