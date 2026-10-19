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

use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimiter;
use App\Helpers\Formatter;
use App\Services\PayPalClient;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pay_respond(['success' => false, 'message' => 'Invalid request.']);
}

CsrfMiddleware::validateOrDie();

// Rate-limit this endpoint (max 10 payment attempts per hour per IP)
try {
    RateLimiter::enforceOrDie('record_payment', 10, 3600);
} catch (\Exception $e) {
    pay_respond(['success' => false, 'message' => 'Too many requests.']);
}

$ref        = strtoupper(Formatter::sanitize($_POST['ref']              ?? ''));
$travelDate = Formatter::sanitize($_POST['travel_date']                  ?? '');
$password   = $_POST['password'] ?? '';
$orderId    = Formatter::sanitize($_POST['paypal_order_id']              ?? '');

if (!$ref || (!$travelDate && !$password) || !$orderId) {
    pay_respond(['success' => false, 'message' => 'Missing booking reference or PayPal order ID.']);
}

// Verify the order directly with PayPal — never trust client-submitted amounts/status.
$amountPaid = PayPalClient::verifyCompletedOrder($orderId);
if ($amountPaid === null) {
    pay_respond(['success' => false, 'message' => 'Payment could not be verified. Please contact us.']);
}

try {
    $pdo = \App\Models\Database::getInstance();
    // Same dual-credential rule as api/paypal_create_order.php.
    if ($password !== '') {
        $booking = \App\Services\BookingTracking::verify($pdo, $ref, $password);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_ref = ? AND travel_date = ? LIMIT 1");
        $stmt->execute([$ref, $travelDate]);
        $booking = $stmt->fetch();
    }

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

    // Calculate new status — against the real cumulative amount_paid
    // (same column admin/bookings.php's manual "Deposit Paid" entry
    // writes to), not the merely-suggested deposit_amount. Previously
    // this read deposit_amount and never wrote amount_paid at all, so a
    // PayPal payment left the Quotation/Invoice PDF and Track Booking
    // page showing $0.00 paid even though real money had come in.
    $alreadyPaid  = (float)($booking['amount_paid'] ?? 0);
    $newTotalPaid = round($alreadyPaid + $amountPaid, 2);
    $newStatus    = ($newTotalPaid >= (float)$booking['total_amount'])
                    ? 'fully_paid' : 'deposit_paid';

    $pdo->prepare("
        UPDATE bookings
        SET payment_status=?, amount_paid=?, payment_method='paypal', paypal_order_id=?, updated_at=NOW()
        WHERE booking_ref=?
    ")->execute([$newStatus, $newTotalPaid, $orderId, $ref]);

    // Branded Invoice/Deposit-Receipt email with the real Quotation and
    // Itinerary PDFs attached — the same documents admin/bookings.php's
    // manual status change sends, so a PayPal payment and a manually
    // recorded bank transfer produce identical paperwork. Every call
    // reaching this point is already a genuinely new payment (the
    // fully_paid short-circuit and paypal_order_id replay check above
    // rule out a duplicate), so this always fires, not just once.
    if (AUTO_SEND_PAYMENT_EMAILS) {
        try {
            $bs = $pdo->prepare("SELECT * FROM bookings WHERE booking_ref=? LIMIT 1");
            $bs->execute([$ref]);
            $freshBooking = $bs->fetch();
            if ($freshBooking) {
                if ($newStatus === 'fully_paid') {
                    \App\Services\InvoiceMailer::sendForBooking($freshBooking);
                } else {
                    \App\Services\InvoiceMailer::sendDepositReceipt($freshBooking);
                }
            }
        } catch (\Throwable $e) {
            error_log('[SZV PayEmail] ' . $e->getMessage());
            // Non-fatal — payment still recorded
        }
    }

    pay_respond(['success' => true, 'status' => $newStatus,
                 'message' => 'Payment recorded. Status: ' . $newStatus]);

} catch (\Exception $e) {
    error_log('[SZV RecordPayment] ' . $e->getMessage());
    pay_respond(['success' => false, 'message' => 'Could not record payment. Please contact us.']);
}
