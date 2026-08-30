<?php
/**
 * Convert an accepted quotation into a confirmed booking.
 * Called via POST from admin/quotations.php modal.
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
use App\Middleware\AuthMiddleware;


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

function qrespond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d);
    exit;
}

AuthMiddleware::requireAdmin();
use App\Middleware\CsrfMiddleware;
CsrfMiddleware::validateOrDie();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') qrespond(['success'=>false,'message'=>'Invalid method.']);

$id = (int)($_POST['quotation_id'] ?? 0);
if (!$id) qrespond(['success'=>false,'message'=>'Missing quotation ID.']);

try {
    $db = \App\Models\Database::getInstance();

    // Load quotation
    $s = $db->prepare("SELECT * FROM quotations WHERE id=? AND status IN ('accepted','sent') LIMIT 1");
    $s->execute([$id]);
    $q = $s->fetch();
    if (!$q) qrespond(['success'=>false,'message'=>'Quotation not found or not in accepted/sent status.']);

    // Check not already converted
    if ($q['status'] === 'converted') qrespond(['success'=>false,'message'=>'Already converted to a booking.']);

    // Determine travel date
    $travelDate = $_POST['travel_date'] ?? $q['start_date'] ?? null;
    if (!$travelDate) qrespond(['success'=>false,'message'=>'Travel date is required to create a booking.']);

    // Generate booking ref
    $ref = 'SZV' . date('Y') . strtoupper(substr(md5(uniqid()),0,6));

    // Adults/children
    $adults   = max(1,(int)($q['adults']   ?? 2));
    $children = max(0,(int)($q['children'] ?? 0));
    $travelers = $adults + $children;

    // Find package by name
    $pkgId = 0;
    try {
        $ps = $db->prepare("SELECT id FROM packages WHERE name=? LIMIT 1");
        $ps->execute([$q['package_name'] ?? '']);
        $pkgRow = $ps->fetch();
        if ($pkgRow) $pkgId = (int)$pkgRow['id'];
    } catch(\Exception $e) {}

    // Special requests
    $special = trim($_POST['special_requests'] ?? '');

    // Payment method
    $method = trim($_POST['payment_method'] ?? 'bank_transfer');

    // Insert booking
    $db->prepare("
        INSERT INTO bookings
            (booking_ref, full_name, email, phone, travelers, adults, children,
             package_id, package_name, accommodation_level,
             travel_date, special_request,
             total_amount, deposit_amount,
             payment_status, payment_method, created_at)
        VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',?,NOW())
    ")->execute([
        $ref,
        $q['client_name'],
        $q['client_email'],
        $q['client_phone'] ?? '',
        $travelers, $adults, $children,
        $pkgId ?: null,
        $q['package_name'] ?? '',
        $q['accommodation_level'] ?? 'midrange',
        $travelDate,
        $special,
        (float)$q['grand_total'],
        round((float)$q['grand_total'] * 0.50, 2),
        $method,
    ]);

    $bookingId = $db->lastInsertId();

    // Auto-schedule all standard reminders
    scheduleBookingReminders($db, $ref, $travelDate);

    // Mark quotation as converted
    $db->prepare("
        UPDATE quotations
        SET status='converted', booking_ref=?, updated_at=NOW()
        WHERE id=?
    ")->execute([$ref, $id]);

    // Queue confirmation email
    try {
        $emailBody = '<p>Dear '.htmlspecialchars($q['client_name']).',</p>'
            .'<p>Your booking has been confirmed! Here are your details:</p>'
            .'<table style="border-collapse:collapse;width:100%;max-width:500px">'
            .'<tr><td style="padding:.5rem;border-bottom:1px solid #eee;color:#888;font-size:.85rem">Booking Reference</td>'
            .'<td style="padding:.5rem;border-bottom:1px solid #eee;font-weight:700">'.$ref.'</td></tr>'
            .'<tr><td style="padding:.5rem;border-bottom:1px solid #eee;color:#888;font-size:.85rem">Safari Package</td>'
            .'<td style="padding:.5rem;border-bottom:1px solid #eee">'.htmlspecialchars($q['package_name']??'').'</td></tr>'
            .'<tr><td style="padding:.5rem;border-bottom:1px solid #eee;color:#888;font-size:.85rem">Travel Date</td>'
            .'<td style="padding:.5rem;border-bottom:1px solid #eee">'.date('d F Y',strtotime($travelDate)).'</td></tr>'
            .'<tr><td style="padding:.5rem;border-bottom:1px solid #eee;color:#888;font-size:.85rem">Total Amount</td>'
            .'<td style="padding:.5rem;border-bottom:1px solid #eee;font-weight:700">USD $'.number_format((float)$q['grand_total'],0).'</td></tr>'
            .'<tr><td style="padding:.5rem;border-bottom:1px solid #eee;color:#888;font-size:.85rem">Deposit Due (50%)</td>'
            .'<td style="padding:.5rem;border-bottom:1px solid #eee;color:#1d4ed8;font-weight:700">USD $'.number_format(round((float)$q['grand_total']*0.5,0),0).'</td></tr>'
            .'</table>'
            .'<p style="margin-top:1.25rem">Please send your deposit within <strong>48 hours</strong> to secure your safari dates.</p>'
            .'<p>Your Booking ID and Travel Date together are your access credentials for our <a href="'.APP_URL.'/track.php">booking tracker</a>.</p>'
            .'<p>Thank you — Safari Zangu Ventures</p>';

        $db->prepare("
            INSERT INTO email_queue (to_email,to_name,subject,body,created_at)
            VALUES (?,?,?,?,NOW())
        ")->execute([
            $q['client_email'],
            $q['client_name'],
            'Booking Confirmed — '.$ref.' | Safari Zangu Ventures',
            $emailBody,
        ]);
    } catch(\Exception $e) {
        error_log('[SZV ConvertEmail] '.$e->getMessage());
    }

    qrespond([
        'success'     => true,
        'booking_ref' => $ref,
        'booking_id'  => $bookingId,
        'message'     => 'Booking '.$ref.' created successfully.',
        'track_url'   => BASE_PATH.'/track.php?ref='.urlencode($ref).'&date='.urlencode($travelDate),
        'admin_url'   => BASE_PATH.'/admin/bookings.php?view='.urlencode($ref),
    ]);

} catch(\Exception $e) {
    error_log('[SZV Convert] '.$e->getMessage());
    qrespond(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}
