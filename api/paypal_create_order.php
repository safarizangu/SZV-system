<?php
/**
 * Create a PayPal order for the outstanding amount on an existing booking.
 * Called by the PayPal Buttons' createOrder() on confirmation.php and
 * track.php — the amount is always computed here from the booking record,
 * never accepted from the client, so there's nothing for a tampered
 * request to inflate or discount. api/record_payment.php independently
 * re-verifies the captured order against PayPal's own API afterward.
 */
ob_start();
header('Content-Type: application/json');

function ppc_respond(array $d): void {
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
    ppc_respond(['success' => false, 'message' => 'Invalid request.']);
}

CsrfMiddleware::validateOrDie();

try {
    RateLimiter::enforceOrDie('paypal_create_order', 10, 3600);
} catch (\Exception $e) {
    ppc_respond(['success' => false, 'message' => 'Too many requests.']);
}

$ref        = strtoupper(Formatter::sanitize($_POST['ref']         ?? ''));
$travelDate = Formatter::sanitize($_POST['travel_date']             ?? '');
$password   = $_POST['password'] ?? '';

if (!$ref || (!$travelDate && !$password)) {
    ppc_respond(['success' => false, 'message' => 'Missing booking reference.']);
}

try {
    $pdo = \App\Models\Database::getInstance();
    // confirmation.php (right after booking, same browser session) still
    // proves ownership with travel_date; track.php (a separate, later
    // visit) now proves it with the Track Booking password instead — see
    // app/Services/BookingTracking.php.
    if ($password !== '') {
        $booking = \App\Services\BookingTracking::verify($pdo, $ref, $password);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_ref = ? AND travel_date = ? LIMIT 1");
        $stmt->execute([$ref, $travelDate]);
        $booking = $stmt->fetch();
    }

    if (!$booking) {
        ppc_respond(['success' => false, 'message' => 'Booking not found.']);
    }
    if ($booking['payment_status'] === 'cancelled') {
        ppc_respond(['success' => false, 'message' => 'This booking has been cancelled.']);
    }
    if ($booking['payment_status'] === 'fully_paid') {
        ppc_respond(['success' => false, 'message' => 'This booking is already fully paid.']);
    }

    $total   = (float)$booking['total_amount'];
    $deposit = (float)$booking['deposit_amount'];
    // Pending: deposit is due. Deposit already paid: the remaining balance is due.
    $amount  = $booking['payment_status'] === 'deposit_paid' ? ($total - $deposit) : $deposit;
    $amount  = round($amount, 2);

    if ($amount <= 0) {
        ppc_respond(['success' => false, 'message' => 'Nothing due on this booking.']);
    }

    $orderId = PayPalClient::createOrder($amount, PAYPAL_CURRENCY, $ref);
    if (!$orderId) {
        ppc_respond(['success' => false, 'message' => 'Could not start PayPal checkout. Please try again or contact us.']);
    }

    ppc_respond(['success' => true, 'order_id' => $orderId, 'amount' => $amount, 'currency' => PAYPAL_CURRENCY]);

} catch (\Exception $e) {
    error_log('[SZV PayPalCreateOrder] ' . $e->getMessage());
    ppc_respond(['success' => false, 'message' => 'Could not start PayPal checkout. Please try again or contact us.']);
}
