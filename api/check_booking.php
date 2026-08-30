<?php
ob_start();
header('Content-Type: application/json');

function ck_respond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\Booking;
use App\Middleware\RateLimiter;
use App\Helpers\Formatter;

RateLimiter::enforceOrDie('track', 15, 3600);

$ref  = strtoupper(Formatter::sanitize($_REQUEST['ref']  ?? ''));
$date = Formatter::sanitize($_REQUEST['date'] ?? '');

if (empty($ref) || empty($date)) {
    ck_respond(['success'=>false,'message'=>'Booking reference and travel date are required.']);
}

$model   = new Booking();
$booking = $model->findByRefAndDate($ref, $date);

if (!$booking) {
    ck_respond(['success'=>false,'message'=>'No booking found. Please check your reference and date.']);
}

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