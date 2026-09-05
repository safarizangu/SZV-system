<?php
/**
 * Record PayPal payment against an existing booking.
 * Called from confirmation.php and track.php PayPal buttons.
 * The PayPal order is verified server-side (status + amount + currency)
 * before any payment status is trusted — see App\Services\PayPalClient.
 */
ob_start();
header('Content-Type: application/json');

function pay_respond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

use App\Middleware\RateLimiter;
use App\Helpers\Formatter;
use App\Services\PayPalClient;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pay_respond(['success' => false, 'message' => 'Invalid request.']);
}

// Rate-limit this endpoint (max 10 payment attempts per hour per IP)
try {
    RateLimiter::enforceOrDie('record_payment', 10, 3600);
} catch (\Exception $e) {
    pay_respond(['success' => false, 'message' => 'Too many requests.']);
}

$ref        = strtoupper(Formatter::sanitize($_POST['ref']              ?? ''));
$travelDate = Formatter::sanitize($_POST['travel_date']                  ?? '');
$orderId    = Formatter::sanitize($_POST['paypal_order_id']              ?? '');

if (!$ref || !$travelDate || !$orderId) {
    pay_respond(['success' => false, 'message' => 'Missing booking reference, date, or PayPal order ID.']);
}

// Verify the order directly with PayPal — never trust client-submitted amounts/status.
$amountPaid = PayPalClient::verifyCompletedOrder($orderId);
if ($amountPaid === null) {
    pay_respond(['success' => false, 'message' => 'Payment could not be verified. Please contact us.']);
}

try {
    $pdo  = \App\Models\Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_ref = ? AND travel_date = ? LIMIT 1");
    $stmt->execute([$ref, $travelDate]);
    $booking = $stmt->fetch();

    if (!$booking) {
        pay_respond(['success' => false, 'message' => 'Booking not found.']);
    }

    // Don't double-process a completed payment
    if ($booking['payment_status'] === 'fully_paid') {
        pay_respond(['success' => true, 'status' => 'fully_paid', 'message' => 'Already fully paid.']);
    }

    // This exact PayPal order was already applied to this booking — idempotent replay, not a new payment.
    if (!empty($booking['paypal_order_id']) && $booking['paypal_order_id'] === $orderId) {
        pay_respond(['success' => true, 'status' => $booking['payment_status'],
                     'message' => 'Payment already recorded.']);
    }

    // Calculate new status
    $alreadyPaid  = $booking['payment_status'] === 'deposit_paid'
                    ? (float)$booking['deposit_amount'] : 0;
    $newTotalPaid = $alreadyPaid + $amountPaid;
    $newStatus    = ($newTotalPaid >= (float)$booking['total_amount'])
                    ? 'fully_paid' : 'deposit_paid';

    $pdo->prepare("
        UPDATE bookings
        SET payment_status=?, payment_method='paypal', paypal_order_id=?, updated_at=NOW()
        WHERE booking_ref=?
    ")->execute([$newStatus, $orderId, $ref]);

    // Queue a payment confirmation email
    try {
        $pdo->prepare("
            INSERT INTO email_queue (to_email, to_name, subject, body, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([
            $booking['email'],
            $booking['full_name'],
            'Payment Received — ' . $ref . ' | Safari Zangu Ventures',
            '<p>Dear ' . htmlspecialchars($booking['full_name']) . ',</p>'
            . '<p>We have received your payment of USD $' . number_format($amountPaid, 0) . '.</p>'
            . '<p>Your booking status is now: <strong>' . strtoupper(str_replace('_',' ',$newStatus)) . '</strong></p>'
            . '<p>Reference: <strong>' . $ref . '</strong></p>'
            . '<p>Thank you — Safari Zangu Ventures</p>',
        ]);
    } catch (\Exception $e) {
        error_log('[SZV PayEmail] ' . $e->getMessage());
        // Non-fatal — payment still recorded
    }

    pay_respond(['success' => true, 'status' => $newStatus,
                 'message' => 'Payment recorded. Status: ' . $newStatus]);

} catch (\Exception $e) {
    error_log('[SZV RecordPayment] ' . $e->getMessage());
    pay_respond(['success' => false, 'message' => 'Could not record payment. Please contact us.']);
}
