<?php
/**
 * Record PayPal payment against an existing booking.
 * Called from confirmation.php and track.php PayPal buttons.
 * IMPORTANT: In production, verify the PayPal order server-side
 * before updating payment status. See comment below.
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
$amountPaid = (float)($_POST['amount_paid']                              ?? 0);

if (!$ref || !$travelDate || !$orderId) {
    pay_respond(['success' => false, 'message' => 'Missing booking reference, date, or PayPal order ID.']);
}

/*
 * ── PRODUCTION TODO ────────────────────────────────────────────
 * Verify the PayPal order server-side before trusting client data:
 *
 * $ch = curl_init("https://api-m.paypal.com/v2/checkout/orders/{$orderId}");
 * curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . getPaypalToken()]);
 * curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 * $response = json_decode(curl_exec($ch), true);
 * if ($response['status'] !== 'COMPLETED') {
 *     pay_respond(['success' => false, 'message' => 'Payment not completed.']);
 * }
 * $amountPaid = (float)$response['purchase_units'][0]['amount']['value'];
 * ──────────────────────────────────────────────────────────────
 */

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
