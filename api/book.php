<?php
/**
 * Safari Zangu Ventures — Booking Handler
 * ob_start() captures ALL output (warnings, notices, errors)
 * so only clean JSON ever reaches the browser.
 */
ob_start();                          // capture everything from this point
header('Content-Type: application/json');

// php/config.php removed — functions now in app/bootstrap.php

/**
 * Auto-schedule all standard reminders for a new booking.
 * Called immediately after INSERT into bookings table.
 */
function scheduleBookingReminders(\PDO $pdo, string $ref, string $travelDate): void {
    try {
        $travel    = new \DateTime($travelDate);
        $now       = new \DateTime();
        $daysAway  = (int)$now->diff($travel)->days;
        $reminders = [];

        // Deposit due — 48 hours from now (if not already past)
        $depositDue = (clone $now)->modify('+48 hours');
        $reminders[] = [
            'type'    => 'deposit_due',
            'send_at' => $depositDue->format('Y-m-d H:i:s'),
            'subject' => "Deposit Due — Booking $ref | Safari Zangu Ventures",
        ];

        // Balance due — 45 days before travel (only if > 50 days away)
        if ($daysAway > 50) {
            $balanceDue = (clone $travel)->modify('-45 days');
            $reminders[] = [
                'type'    => 'balance_due',
                'send_at' => $balanceDue->format('Y-m-d 09:00:00'),
                'subject' => "Balance Due — Booking $ref | Safari Zangu Ventures",
            ];
        }

        // Departure reminder — 7 days before travel
        if ($daysAway > 10) {
            $departure = (clone $travel)->modify('-7 days');
            $reminders[] = [
                'type'    => 'departure',
                'send_at' => $departure->format('Y-m-d 09:00:00'),
                'subject' => "Your Safari Departs in 7 Days — $ref",
            ];
        }

        // Post-trip feedback — 3 days after travel
        $postTrip = (clone $travel)->modify('+3 days');
        $reminders[] = [
            'type'    => 'post_trip',
            'send_at' => $postTrip->format('Y-m-d 10:00:00'),
            'subject' => "How Was Your Safari? — $ref",
        ];

        // Insert all reminders
        $stmt = $pdo->prepare("
            INSERT INTO booking_reminders (booking_ref, type, send_at, subject)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($reminders as $r) {
            $stmt->execute([$ref, $r['type'], $r['send_at'], $r['subject']]);
        }
    } catch (\Exception $e) {
        error_log('[SZV Reminders] Failed to schedule for '.$ref.': '.$e->getMessage());
        // Non-fatal — booking still created
    }
}


use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimiter;
use App\Validators\BookingValidator;
use App\Helpers\Formatter;

// ── Helper: send JSON and exit cleanly ────────────────────
function respond(array $payload): void {
    ob_end_clean();                  // discard any warnings/notices
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// ── Method check ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed.']);
}

// ── Security ──────────────────────────────────────────────
try {
    RateLimiter::enforceOrDie('booking', 5, 3600);
} catch (Exception $e) {
    respond(['success' => false, 'message' => 'Too many requests. Please wait and try again.']);
}

if (($_POST['payment_method'] ?? '') !== 'paypal_callback') {
    try {
        CsrfMiddleware::validateOrDie();
    } catch (Exception $e) {
        respond(['success' => false, 'message' => 'Security token expired. Please refresh the page and try again.']);
    }
}

// ── Collect & sanitize ────────────────────────────────────
$adults   = max(1, (int)($_POST['adults']   ?? $_POST['travelers'] ?? 1));
$children = max(0, (int)($_POST['children'] ?? 0));
$travelers = $adults + $children;

$data = [
    'full_name'           => Formatter::sanitize($_POST['full_name']           ?? ''),
    'email'               => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
    'phone'               => Formatter::sanitize($_POST['phone']                ?? ''),
    'adults'              => $adults,
    'children'            => $children,
    'travelers'           => $travelers,
    'package_id'          => (int)($_POST['package_id']                        ?? 0),
    'accommodation_level' => Formatter::sanitize($_POST['accommodation_level']  ?? 'midrange'),
    'travel_date'         => Formatter::sanitize($_POST['travel_date']          ?? ''),
    'special_request'     => Formatter::sanitize($_POST['special_request']      ?? ''),
    'payment_method'      => Formatter::sanitize($_POST['payment_method']       ?? 'bank_transfer'),
    'paypal_order_id'     => Formatter::sanitize($_POST['paypal_order_id']      ?? ''),
];

// ── Validate ──────────────────────────────────────────────
if (empty(trim($data['full_name']))) {
    respond(['success' => false, 'message' => 'Full name is required.']);
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    respond(['success' => false, 'message' => 'A valid email address is required.']);
}
if (empty(trim($data['phone']))) {
    respond(['success' => false, 'message' => 'Phone number is required.']);
}
if ($data['package_id'] < 1) {
    respond(['success' => false, 'message' => 'Please select a package.']);
}
if (empty($data['travel_date']) || strtotime($data['travel_date']) < strtotime('tomorrow')) {
    respond(['success' => false, 'message' => 'Travel date must be at least tomorrow.']);
}

// ── Load package ──────────────────────────────────────────
$package = getPackageById($data['package_id']);
if (!$package) {
    respond(['success' => false, 'message' => 'Selected package not found.']);
}

// ── Calculate price ───────────────────────────────────────
$tier  = $travelers === 1 ? '1pax' : ($travelers === 2 ? '2pax' : '3pax');
$price = (float)($package['price_' . $tier] ?? $package['price_2pax'] ?? 0);
$total   = round($price * $travelers, 2);
$deposit = round($total * (DEPOSIT_PERCENT / 100), 2);

$data['package_name']   = $package['name'];
$data['total_amount']   = $total;
$data['deposit_amount'] = $deposit;

// ── Generate booking reference ────────────────────────────
$ref = generateBookingRef();

// ── Save to DB ────────────────────────────────────────────
try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("
        INSERT INTO bookings
            (booking_ref, full_name, email, phone, travelers,
             package_id, package_name, accommodation_level,
             travel_date, special_request,
             total_amount, deposit_amount,
             payment_status, payment_method, created_at)
        VALUES
            (:ref, :name, :email, :phone, :travelers,
             :pkg_id, :pkg_name, :acc_level,
             :travel_date, :special_request,
             :total, :deposit,
             'pending', :method, NOW())
    ");
    $stmt->execute([
        'ref'            => $ref,
        'name'           => $data['full_name'],
        'email'          => $data['email'],
        'phone'          => $data['phone'],
        'travelers'      => $data['travelers'],
        'pkg_id'         => $data['package_id'],
        'pkg_name'       => $data['package_name'],
        'acc_level'      => $data['accommodation_level'],
        'travel_date'    => $data['travel_date'],
        'special_request'=> $data['special_request'],
        'total'          => $data['total_amount'],
        'deposit'        => $data['deposit_amount'],
        'method'         => $data['payment_method'],
    ]);
} catch (Exception $e) {
    error_log('[SZV Booking DB] ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Booking could not be saved. Please try again or WhatsApp us.']);
}

// ── Send confirmation email (silent — never crash the response) ──
try {
    if (function_exists('sendBookingConfirmationEmail')) {
        sendBookingConfirmationEmail(
            $data['email'],
            $data['full_name'],
            $ref,
            $data['package_name'],
            $data['travel_date'],
            $data['total_amount'],
            $data['deposit_amount'],
            $data['accommodation_level']
        );
    }
} catch (Exception $e) {
    error_log('[SZV Booking Email] ' . $e->getMessage());
    // Never let email failure affect the booking response
}

// ── Success response ──────────────────────────────────────
respond([
    'success'       => true,
    'booking_ref'   => $ref,
    'total_amount'  => $data['total_amount'],
    'deposit_amount'=> $data['deposit_amount'],
    'message'       => 'Booking confirmed! Your reference is ' . $ref,
    'redirect'      => SITE_URL . '/confirmation.php?ref=' . urlencode($ref)
                     . '&date=' . urlencode($data['travel_date']),
]);
