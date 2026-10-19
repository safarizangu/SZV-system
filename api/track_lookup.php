<?php
/**
 * Track Booking API — hardened with server-side rate limiting.
 * Called by views/track.php via fetch().
 * Rate limit: 3 failed attempts per IP → 15 minute lockout.
 *
 * Auth is now a real one-time password (see app/Services/BookingTracking.php),
 * not just "knows the ref and the travel date" — so once verified, this can
 * safely return the full cost breakdown, special requests and itinerary
 * summary too. Still never exposes: passport details, personal addresses,
 * or anything about a DIFFERENT booking.
 */
ob_start();
require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\BookingTracking;

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

$maxAttempts = TRACK_LOCKOUT_ATTEMPTS;
$lockSeconds = TRACK_LOCKOUT_MINUTES * 60;

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
$ref      = strtoupper(trim($_POST['ref'] ?? $_GET['ref'] ?? ''));
$password = trim($_POST['password'] ?? $_GET['password'] ?? '');

if (!$ref || !$password) {
    jout(['success' => false, 'message' => 'Please enter both your Booking ID and Track Booking password.']);
}

// Validate booking ref format: starts with SZV, 6–20 alphanumeric chars
if (!preg_match('/^SZV[A-Z0-9]{4,17}$/i', $ref)) {
    jout(['success' => false, 'message' => 'Invalid Booking ID format. It should start with SZV.']);
}

// ── DB lookup ─────────────────────────────────────────────────
try {
    $db      = \App\Models\Database::getInstance();
    $booking = BookingTracking::verify($db, $ref, $password);

    if (!$booking) {
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

    // ── Build response — a real password now gates this, so the full
    //    cost breakdown and special requests are safe to return ──
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

    $ps         = $booking['payment_status'] ?? 'pending';
    $total      = (float)$booking['total_amount'];
    $dep        = (float)$booking['deposit_amount'];
    $amountPaid = (float)($booking['amount_paid'] ?? 0);
    // Below pending, balance_due is against the suggested deposit (nothing
    // paid yet to be real about); once a payment is actually on file —
    // deposit_paid or fully_paid — it's the real amount_paid an admin
    // entered by hand (no bank integration), same figures the
    // Quotation/Invoice PDF shows.
    $balance = $ps === 'pending' ? round($total - $dep, 2) : max(0, round($total - $amountPaid, 2));

    $paymentNote = '';
    if ($ps === 'pending') {
        $paymentNote = 'Deposit of $' . number_format($dep, 0) . ' USD is due to confirm your dates.';
    } elseif ($ps === 'deposit_paid') {
        $paymentNote = '$' . number_format($amountPaid, 0) . ' USD received. Balance of $' . number_format($balance, 0) . ' USD due 45 days before departure.';
    } elseif ($ps === 'fully_paid') {
        $paymentNote = 'All payments received. You are fully confirmed.';
    }

    // Itinerary summary — day titles + includes/excludes only, never the
    // full per-day pricing internals.
    $itinerary = null;
    if (!empty($booking['itinerary_id'])) {
        try {
            $is = $db->prepare("SELECT name, duration_days, days, includes, excludes FROM itineraries WHERE id=? LIMIT 1");
            $is->execute([(int)$booking['itinerary_id']]);
            $it = $is->fetch();
            if ($it) {
                $days = json_decode($it['days'] ?? '[]', true) ?: [];
                $itinerary = [
                    'name'          => $it['name'],
                    'duration_days' => (int)$it['duration_days'],
                    'day_titles'    => array_values(array_map(
                        fn($d, $i) => 'Day ' . ($i + 1) . ($d['title'] ?? $d['location'] ?? '' ? ': ' . ($d['title'] ?? $d['location'] ?? '') : ''),
                        $days, array_keys($days)
                    )),
                    'includes' => json_decode($it['includes'] ?? '[]', true) ?: [],
                    'excludes' => json_decode($it['excludes'] ?? '[]', true) ?: [],
                ];
            }
        } catch (\Exception $e) {}
    }

    jout([
        'success'         => true,
        'booking_ref'     => $booking['booking_ref'],
        'client_name'     => $booking['full_name'],
        'email'           => $booking['email'],
        'phone'           => $booking['phone'],
        'package_name'    => $booking['package_name'],
        'accommodation'   => ucwords(str_replace(['_','-'], ' ', $booking['accommodation_level'] ?? '')),
        'travel_date'     => date('d F Y', strtotime($booking['travel_date'])),
        'travel_date_iso' => $booking['travel_date'],
        'travelers'       => (int)$booking['travelers'],
        'adults'          => (int)($booking['adults'] ?? 0),
        'children'        => (int)($booking['children'] ?? 0),
        'special_request' => $booking['special_request'] ?? '',
        'payment_status'  => $ps,
        'status_label'    => $statusLabels[$ps] ?? ucfirst($ps),
        'status_color'    => $statusColors[$ps] ?? '#374151',
        'payment_note'    => $paymentNote,
        'total_amount'    => $total,
        'deposit_amount'  => $dep,
        'amount_paid'     => $amountPaid,
        'balance_due'     => $balance,
        'has_quotation'   => !empty($booking['quotation_id']),
        'booked_on'       => date('d F Y', strtotime($booking['created_at'])),
        'itinerary'       => $itinerary,
    ]);

} catch (\Exception $e) {
    error_log('[Track Lookup] ' . $e->getMessage());
    jout(['success' => false, 'message' => 'An error occurred. Please try again or contact us on WhatsApp.']);
}
