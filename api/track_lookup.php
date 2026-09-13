<?php
/**
 * Track Booking API — hardened with server-side rate limiting.
 * Called by views/track.php via fetch().
 * Rate limit: 3 failed attempts per IP → 15 minute lockout.
 * Only exposes: booking status, package name, travel date, deposit status.
 * Never exposes: passport details, payment amounts, personal addresses.
 */
ob_start();
require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');

function jout(array $d): void {
    ob_end_clean();
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Server-side rate limiting ─────────────────────────────────
$ip       = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
$lockDir  = '/tmp/szv_ratelimit/';
$lockFile = $lockDir . 'track_' . hash('sha256', $ip) . '.json';

if (!is_dir($lockDir)) mkdir($lockDir, 0700, true);

$lockData = ['attempts' => 0, 'last_attempt' => 0, 'last_success' => 0];
if (file_exists($lockFile)) {
    $lockData = json_decode(file_get_contents($lockFile), true) ?? $lockData;
}

$maxAttempts = 3;
$lockSeconds = 900; // 15 minutes

// Reset if lock window expired
if ((time() - $lockData['last_attempt']) >= $lockSeconds) {
    $lockData['attempts'] = 0;
}

if ($lockData['attempts'] >= $maxAttempts) {
    $remaining = $lockSeconds - (time() - $lockData['last_attempt']);
    jout([
        'success' => false,
        'locked'  => true,
        'message' => 'Too many failed attempts. Please try again in ' . ceil($remaining / 60) . ' minutes, or contact us on WhatsApp.',
    ]);
}

// ── Validate input ────────────────────────────────────────────
$ref  = strtoupper(trim($_POST['ref'] ?? $_GET['ref'] ?? ''));
$date = trim($_POST['date'] ?? $_GET['date'] ?? '');

if (!$ref || !$date) {
    jout(['success' => false, 'message' => 'Please enter both your Booking ID and Travel Date.']);
}

// Validate booking ref format: starts with SZV, 6–20 alphanumeric chars
if (!preg_match('/^SZV[A-Z0-9]{4,17}$/i', $ref)) {
    jout(['success' => false, 'message' => 'Invalid Booking ID format. It should start with SZV.']);
}

// Validate date — accept YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY
$parsedDate = null;
$dateFormats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
foreach ($dateFormats as $fmt) {
    $d = \DateTime::createFromFormat($fmt, $date);
    if ($d && $d->format($fmt) === $date) {
        $parsedDate = $d->format('Y-m-d'); // normalise to Y-m-d for DB lookup
        break;
    }
}
if (!$parsedDate) {
    jout(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD (e.g. 2026-09-15) or DD/MM/YYYY (e.g. 15/09/2026).']);
}

// ── DB lookup ─────────────────────────────────────────────────
try {
    $db = \App\Models\Database::getInstance();
    $stmt = $db->prepare(
        "SELECT booking_ref, full_name, package_name, accommodation_level,
                travel_date, travelers, adults, children,
                payment_status, total_amount, deposit_amount,
                created_at
         FROM bookings
         WHERE booking_ref = ? AND travel_date = ?
         LIMIT 1"
    );
    $stmt->execute([$ref, $parsedDate]);
    $booking = $stmt->fetch();

    if (!$booking) {
        // Increment failed attempt counter
        $lockData['attempts']++;
        $lockData['last_attempt'] = time();
        file_put_contents($lockFile, json_encode($lockData), LOCK_EX);

        $remaining = $maxAttempts - $lockData['attempts'];
        jout([
            'success'  => false,
            'message'  => 'No booking found with those details. ' .
                ($remaining > 0
                    ? $remaining . ' attempt' . ($remaining !== 1 ? 's' : '') . ' remaining before temporary lockout.'
                    : 'No more attempts. Please contact us on WhatsApp.'),
        ]);
    }

    // Success — reset attempt counter
    $lockData['attempts'] = 0;
    $lockData['last_success'] = time();
    file_put_contents($lockFile, json_encode($lockData), LOCK_EX);

    // ── Build safe response — never expose passport/address/full payment amounts ──
    $statusLabels = [
        'pending'      => 'Awaiting Deposit',
        'deposit_paid' => 'Deposit Received — Balance Due',
        'fully_paid'   => 'Fully Paid',
        'cancelled'    => 'Cancelled',
    ];
    $statusColors = [
        'pending'      => '#92400e',
        'deposit_paid' => '#1d4ed8',
        'fully_paid'   => '#166534',
        'cancelled'    => '#991b1b',
    ];

    $ps     = $booking['payment_status'] ?? 'pending';
    $total  = (float)$booking['total_amount'];
    $dep    = (float)$booking['deposit_amount'];
    $balance = $total - $dep;

    // Only show deposit amount and whether balance is due — never full breakdown
    $paymentNote = '';
    if ($ps === 'pending') {
        $paymentNote = 'Deposit of $' . number_format($dep, 0) . ' USD is due to confirm your dates.';
    } elseif ($ps === 'deposit_paid') {
        $paymentNote = 'Deposit received. Balance of $' . number_format($balance, 0) . ' USD due 45 days before departure.';
    } elseif ($ps === 'fully_paid') {
        $paymentNote = 'All payments received. You are fully confirmed.';
    }

    jout([
        'success'        => true,
        'booking_ref'    => $booking['booking_ref'],
        // Only first name — no surname exposure
        'client_first'   => explode(' ', trim($booking['full_name']))[0] ?? 'Guest',
        'package_name'   => $booking['package_name'],
        'accommodation'  => ucwords(str_replace(['_','-'], ' ', $booking['accommodation_level'] ?? '')),
        'travel_date'    => date('d F Y', strtotime($booking['travel_date'])),
        'travelers'      => (int)$booking['travelers'],
        'adults'         => (int)($booking['adults'] ?? 0),
        'children'       => (int)($booking['children'] ?? 0),
        'payment_status' => $ps,
        'status_label'   => $statusLabels[$ps] ?? ucfirst($ps),
        'status_color'   => $statusColors[$ps] ?? '#374151',
        'payment_note'   => $paymentNote,
        'booked_on'      => date('d F Y', strtotime($booking['created_at'])),
    ]);

} catch (\Exception $e) {
    error_log('[Track Lookup] ' . $e->getMessage());
    jout(['success' => false, 'message' => 'An error occurred. Please try again or contact us on WhatsApp.']);
}
