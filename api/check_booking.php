<?php
/**
 * Legacy booking-check endpoint. Previously accepted booking_ref +
 * travel_date only (no password) and returned full PII + financial
 * breakdown + bank transfer details to anyone who knew those two values —
 * travel_date has very low entropy and ref+date leaks easily (a forwarded
 * email, a screenshot, a shared-device browser history). SEC-1: now
 * requires the same Track Booking password every other tracking endpoint
 * (api/track_lookup.php, api/track_booking_pdf.php, etc.) requires, via
 * App\Services\BookingTracking::verify() — ref+date alone is no longer
 * sufficient. Response shape is unchanged for any existing caller; only
 * the required input and lockout bucket changed.
 */
ob_start();
header('Content-Type: application/json');

function ck_respond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\BookingTracking;
use App\Helpers\Formatter;

// ── Own dedicated lockout bucket — same strict 3-attempts/15-minutes
//    config as api/track_lookup.php, kept separate from track_'s own
//    bucket (not shared) so this endpoint can't be used to burn through
//    attempts against a ref under a different counter, but with no looser
//    allowance than the hardened flow it mirrors.
$ip       = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
$lockDir  = '/tmp/szv_ratelimit/';
$lockFile = $lockDir . 'checkbooking_' . hash('sha256', $ip) . '.json';
if (!is_dir($lockDir)) mkdir($lockDir, 0700, true);

$lockData = ['attempts' => 0, 'last_attempt' => 0];
if (file_exists($lockFile)) {
    $lockData = json_decode(file_get_contents($lockFile), true) ?? $lockData;
}
$maxAttempts = defined('TRACK_LOCKOUT_ATTEMPTS') ? TRACK_LOCKOUT_ATTEMPTS : 3;
$lockSeconds = (defined('TRACK_LOCKOUT_MINUTES') ? TRACK_LOCKOUT_MINUTES : 15) * 60;

if ((time() - $lockData['last_attempt']) >= $lockSeconds) {
    $lockData['attempts'] = 0;
}
if ($lockData['attempts'] >= $maxAttempts) {
    $remaining = $lockSeconds - (time() - $lockData['last_attempt']);
    ck_respond(['success'=>false,'message'=>'Too many failed attempts. Please try again in ' . ceil($remaining / 60) . ' minutes, or contact us on WhatsApp.']);
}

$ref      = strtoupper(Formatter::sanitize($_REQUEST['ref']      ?? ''));
$password = trim($_REQUEST['password'] ?? '');

if (empty($ref) || empty($password)) {
    ck_respond(['success'=>false,'message'=>'Booking reference and Track Booking password are required.']);
}

$db      = \App\Models\Database::getInstance();
$booking = BookingTracking::verify($db, $ref, $password);

if (!$booking) {
    $lockData['attempts']++;
    $lockData['last_attempt'] = time();
    file_put_contents($lockFile, json_encode($lockData), LOCK_EX);
    ck_respond(['success'=>false,'message'=>'No booking found with those details. Please check your reference and Track Booking password.']);
}

// Success — reset the attempt counter.
$lockData['attempts'] = 0;
file_put_contents($lockFile, json_encode($lockData), LOCK_EX);

$statusMap = [
    'pending'      => ['label'=>'Pending Confirmation','color'=>'orange'],
    'deposit_paid' => ['label'=>'Deposit Received',    'color'=>'blue'],
    'fully_paid'   => ['label'=>'Fully Paid',          'color'=>'green'],
    'cancelled'    => ['label'=>'Cancelled',            'color'=>'red'],
];
$status = $statusMap[$booking['payment_status'] ?? 'pending'];

$instructions = '';
if (in_array($booking['payment_status'], ['pending','deposit_paid'])) {
    $remaining = (float)$booking['total_amount'] - (float)($booking['payment_status'] === 'deposit_paid' ? $booking['deposit_amount'] : 0);
    $instructions = "Please transfer " . Formatter::currency($remaining) . " to:\n"
        . "Bank: " . BANK_NAME . "\nAccount: " . BANK_ACCOUNT_NO . "\nSWIFT: " . BANK_SWIFT . "\n"
        . "Or via PayPal/Wise to: " . WISE_EMAIL . "\n"
        . "Reference: {$ref}";
}

// ── Cost breakdown with VAT ───────────────────────────────
$rawTotal   = (float)$booking['total_amount'];
$rawDeposit = (float)$booking['deposit_amount'];

// Reverse-calculate: total includes VAT (18%)
// subtotal = total / (1 + VAT_RATE)
$vatRate    = defined('VAT_RATE') ? (float)VAT_RATE : 0.18;
$subtotal   = round($rawTotal / (1 + $vatRate), 2);
$vatAmount  = round($rawTotal - $subtotal, 2);

// Service charge (5%) is part of subtotal
$svcRate    = defined('SERVICE_CHARGE_RATE') ? (float)SERVICE_CHARGE_RATE : 0.05;
$baseAmount = round($subtotal / (1 + $svcRate), 2);
$svcAmount  = round($subtotal - $baseAmount, 2);

$balanceDue = round($rawTotal - $rawDeposit, 2);
$travelers  = (int)$booking['travelers'];
$perPerson  = $travelers > 0 ? round($rawTotal / $travelers, 2) : $rawTotal;

ob_end_clean();
echo json_encode([
    'success' => true,
    'booking'              => [
        'ref'              => $booking['booking_ref'],
        'name'             => $booking['full_name'],
        'email'            => $booking['email'],
        'phone'            => $booking['phone'],
        'package'          => $booking['package_name'],
        'accommodation_level' => Formatter::accommodationLabel($booking['accommodation_level'] ?? 'midrange'),
        'travelers'        => $travelers,
        'travel_date'      => date('d F Y', strtotime($booking['travel_date'])),
        'status'           => $booking['payment_status'],
        // Breakdown
        'base_amount'      => Formatter::currency($baseAmount),
        'service_charge'   => Formatter::currency($svcAmount),
        'subtotal'         => Formatter::currency($subtotal),
        'vat_rate'         => ($vatRate * 100) . '%',
        'vat_amount'       => Formatter::currency($vatAmount),
        'total'            => Formatter::currency($rawTotal),
        'per_person'       => Formatter::currency($perPerson),
        'deposit'          => Formatter::currency($rawDeposit),
        'balance_due'      => Formatter::currency($balanceDue),
        'created_at'       => date('d M Y, H:i', strtotime($booking['created_at'])),
        'payment_instructions' => $instructions,
    ],
    // Keep legacy keys for backward compat
    'booking_ref'          => $booking['booking_ref'],
    'full_name'            => $booking['full_name'],
    'package_name'         => $booking['package_name'],
    'accommodation_level'  => Formatter::accommodationLabel($booking['accommodation_level'] ?? 'midrange'),
    'travelers'            => $travelers,
    'travel_date'          => $booking['travel_date'],
    'total_amount'         => Formatter::currency($rawTotal),
    'deposit_amount'       => Formatter::currency($rawDeposit),
    'payment_status'       => $booking['payment_status'],
    'payment_status_label' => $status['label'],
    'payment_instructions' => $instructions,
    'whatsapp'             => 'https://wa.me/'.WHATSAPP_NUMBER.'?text='.urlencode("Hi, I'd like to check booking {$ref}"),
]);
