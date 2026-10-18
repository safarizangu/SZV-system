<?php
/**
 * Convert an accepted quotation into a confirmed booking.
 * Called via POST from admin/quotations.php modal.
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Helpers\Formatter;
use App\Mail\Mailer;
use App\Services\BookingReminderScheduler;
use App\Models\Customer;
use App\Services\PdfRenderer;

function qrespond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d);
    exit;
}

AuthMiddleware::requireAdmin();
if (\App\Services\OwnershipScope::isScoped() && !\App\Services\OwnershipScope::ownsQuotation(\App\Models\Database::getInstance(), (int)($_POST['quotation_id'] ?? 0), \App\Services\OwnershipScope::currentAgentId())) {
    \App\Services\AccessGuard::denyJson('quotation', (string)($_POST['quotation_id'] ?? ''), 'convert_to_booking');
}
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
    // Covers "already converted" too — the SELECT above only matches
    // status IN ('accepted','sent'), so a 'converted' quotation always
    // lands here with this same message rather than a separate check.
    if (!$q) qrespond(['success'=>false,'message'=>'Quotation not found, already converted, or not in accepted/sent status.']);

    // Determine travel date
    $travelDate = $_POST['travel_date'] ?? $q['start_date'] ?? null;
    if (!$travelDate) qrespond(['success'=>false,'message'=>'Travel date is required to create a booking.']);

    // Generate booking ref — same generator used by the direct-booking path
    // so both booking paths produce refs with the same shape/entropy.
    $ref = Formatter::generateRef();

    // Adults/children
    $adults   = max(1,(int)($q['adults']   ?? 2));
    $children = max(0,(int)($q['children'] ?? 0));
    $travelers = $adults + $children;

    // Prefer the quotation's own package_id (set when the quote was built
    // from a real package); fall back to a name match for older quotations
    // that predate that column being populated.
    $pkgId = (int)($q['package_id'] ?? 0);
    if (!$pkgId) {
        try {
            $ps = $db->prepare("SELECT id FROM packages WHERE name=? LIMIT 1");
            $ps->execute([$q['package_name'] ?? '']);
            $pkgRow = $ps->fetch();
            if ($pkgRow) $pkgId = (int)$pkgRow['id'];
        } catch(\Exception $e) {}
    }
    $itineraryId = (int)($q['itinerary_id'] ?? 0) ?: null;

    // Reuse the quotation's own customer link if it has one; otherwise
    // resolve/create it now from the quotation's client details.
    $customerId = (int)($q['customer_id'] ?? 0) ?: null;
    if (!$customerId) {
        try {
            $customerId = (new \App\Models\Customer())->findOrCreateByEmail(
                $q['client_email'], $q['client_name'], $q['client_phone'] ?? null, $q['client_country'] ?? null
            );
        } catch (\Exception $e) { error_log('[SZV Convert Customer] '.$e->getMessage()); }
    }

    // Special requests
    $special = trim($_POST['special_requests'] ?? '');

    // Payment method
    $method = trim($_POST['payment_method'] ?? 'bank_transfer');

    // Carry over the deposit the client was actually quoted (30% of grand
    // total, computed and shown to them in admin/quotation_new.php) rather
    // than recomputing a different rate here — a booking silently asking
    // for 50% down after a 30% quotation is a real money surprise for a
    // client who already saw and accepted a lower number in writing.
    $depositAmount = isset($q['deposit_amount']) && (float)$q['deposit_amount'] > 0
        ? (float)$q['deposit_amount']
        : round((float)$q['grand_total'] * 0.30, 2); // fallback for older quotations saved before this column existed

    // Insert booking
    $db->prepare("
        INSERT INTO bookings
            (booking_ref, quotation_id, customer_id, full_name, email, phone, travelers, adults, children,
             package_id, itinerary_id, package_name, accommodation_level,
             travel_date, special_request,
             total_amount, deposit_amount,
             payment_status, payment_method, created_at)
        VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',?,NOW())
    ")->execute([
        $ref,
        $id,
        $customerId,
        $q['client_name'],
        $q['client_email'],
        $q['client_phone'] ?? '',
        $travelers, $adults, $children,
        $pkgId ?: null,
        $itineraryId,
        $q['package_name'] ?? '',
        $q['accommodation_level'] ?? 'midrange',
        $travelDate,
        $special,
        (float)$q['grand_total'],
        $depositAmount,
        $method,
    ]);

    $bookingId = $db->lastInsertId();

    // Auto-schedule all standard reminders — same scheduler the direct-booking path uses
    BookingReminderScheduler::schedule($db, $ref, $travelDate);

    // If this quotation originated from a lead or a custom request, advance
    // that record's status now that it has genuinely become a booking —
    // same linkage used when the quotation itself was created, just read
    // back instead of requiring a human to update it separately.
    if (!empty($q['lead_id'])) \App\Services\PipelineSync::advanceLead($db, (int)$q['lead_id'], 'booked');
    if (!empty($q['custom_request_id'])) \App\Services\PipelineSync::advanceCustomRequest($db, (int)$q['custom_request_id'], 'converted');

    // Mark quotation as converted
    $db->prepare("
        UPDATE quotations
        SET status='converted', booking_ref=?, updated_at=NOW()
        WHERE id=?
    ")->execute([$ref, $id]);

    // Render the quotation the client already saw, plus its itinerary as
    // a separate PDF, to attach to the confirmation email — same numbers,
    // no surprises between what they were quoted and what they're now
    // being sent as their booking's cost breakdown.
    $attachments = [];
    $pdfFile = PdfRenderer::renderQuotation($id, 'Quotation-' . $ref);
    if ($pdfFile) $attachments[] = $pdfFile;
    $itinFile = PdfRenderer::renderQuotationItinerary($id, 'Itinerary-' . $ref);
    if ($itinFile) $attachments[] = $itinFile;

    // Queue confirmation email — same branded template the direct-booking path uses
    try {
        $queued = Mailer::queue('booking_confirmation', [
            'to_email'            => $q['client_email'],
            'to_name'             => $q['client_name'],
            'client_name'         => $q['client_name'],
            'booking_ref'         => $ref,
            'package_name'        => $q['package_name'] ?? '',
            'travel_date'         => $travelDate,
            'travelers'           => $travelers,
            'accommodation_level' => $q['accommodation_level'] ?? 'midrange',
            'total_amount'        => (float)$q['grand_total'],
            'deposit_amount'      => $depositAmount,
        ], null, null, $attachments ?: null);
        if ($queued) {
            $db->prepare("UPDATE bookings SET confirmation_email_sent=1 WHERE booking_ref=?")->execute([$ref]);
        }
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
