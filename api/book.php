<?php
/**
 * Safari Zangu Ventures — Booking Handler
 * ob_start() captures ALL output (warnings, notices, errors)
 * so only clean JSON ever reaches the browser.
 */
ob_start();                          // capture everything from this point
header('Content-Type: application/json');

require_once __DIR__ . '/../app/bootstrap.php';

use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimiter;
use App\Middleware\Honeypot;
use App\Validators\BookingValidator;
use App\Helpers\Formatter;
use App\Mail\Mailer;
use App\Services\BookingReminderScheduler;
use App\Models\Customer;
use App\Services\ItineraryQuoteBuilder;
use App\Services\PdfRenderer;

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

// Platform Controls → Maintenance Mode (admin/settings.php) — checked
// before the rate limiter so a maintenance window doesn't also burn
// through guests' request quota.
if (MAINTENANCE_MODE) {
    respond(['success' => false, 'message' => "We're temporarily updating our booking system. Please WhatsApp us directly and we'll confirm your booking by hand, or try again shortly."]);
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

// Honeypot — silently pretend success (no redirect field, so the
// frontend just shows the message) rather than tip off a spam script.
// Nothing is saved or emailed.
if (Honeypot::caught()) {
    respond(['success' => true, 'message' => 'Thanks! We’ll be in touch shortly to confirm your dates.']);
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
    'nationality'          => Formatter::sanitize($_POST['nationality']          ?? ''),
    'payment_method'      => Formatter::sanitize($_POST['payment_method']       ?? 'bank_transfer'),
    'paypal_order_id'     => Formatter::sanitize($_POST['paypal_order_id']      ?? ''),
];

// ── Validate ──────────────────────────────────────────────
$validator = new BookingValidator();
if (!$validator->validate($data)) {
    respond(['success' => false, 'message' => $validator->firstError()]);
}

// ── Load package ──────────────────────────────────────────
// getPackageById() was called here but never existed anywhere in the
// codebase — every direct booking submission fatally errored on this
// line (after also fatally erroring one line earlier for the same
// reason: this whole file never required app/bootstrap.php either).
$package = (new \App\Models\Package())->findById($data['package_id']);
if (!$package) {
    respond(['success' => false, 'message' => 'Selected package not found.']);
}

// Trip extension — only trust a code that's actually one of THIS
// package's offered options (matches views/book.php's own dropdown,
// built from the same TripExtensions source). A stray/tampered value
// is dropped rather than saved as free text.
$extCode = Formatter::sanitize($_POST['trip_extension'] ?? '');
$data['trip_extension'] = $extCode !== ''
    ? \App\Helpers\TripExtensions::labelFor($package['slug'] ?? '', $extCode)
    : null;

// ── Calculate price ───────────────────────────────────────
// Packages linked to a real itinerary get a proper itemised quotation,
// scaled to the exact adults/children count, instead of a flat rate
// that doesn't distinguish a 2-adult party from a 1-adult+1-child one.
// A package without a linked itinerary falls back to its single
// price_1pax "starting from" rate × traveler count.
$itinerary   = null;
$builtQuote  = null;
$quoteTotals = null;

if (!empty($package['itinerary_id'])) {
    try {
        $itinStmt = Database::getInstance()->prepare("SELECT * FROM itineraries WHERE id=? AND is_active=1 LIMIT 1");
        $itinStmt->execute([(int)$package['itinerary_id']]);
        $itinerary = $itinStmt->fetch() ?: null;
        if ($itinerary) {
            // This package's own Adults/Children Service Charge rates
            // (admin/package_edit.php) — falls back to the builder's own
            // $100/person default for a package saved before that split.
            $pkgCostBreakdown = json_decode($package['cost_breakdown'] ?? '{}', true) ?: [];
            $svcFeeRates = [
                'adult' => (float)($pkgCostBreakdown['service_charge_adult'] ?? $pkgCostBreakdown['service_charge_pax'] ?? 100),
                'child' => (float)($pkgCostBreakdown['service_charge_child'] ?? $pkgCostBreakdown['service_charge_pax'] ?? 100),
            ];
            $builtQuote  = ItineraryQuoteBuilder::build($itinerary, $adults, $children, true, $data['travel_date'] ?: null, $data['nationality'] ?: null, $svcFeeRates);
            $quoteTotals = ItineraryQuoteBuilder::totals($builtQuote['line_items']);
        }
    } catch (\Exception $e) {
        error_log('[SZV Booking Itinerary] ' . $e->getMessage());
        $itinerary = null; // fall through to flat-tier pricing below
    }
}

if ($quoteTotals) {
    $total   = $quoteTotals['grand_total'];
    $deposit = $quoteTotals['deposit'];
} else {
    $price   = (float)($package['price_1pax'] ?? 0);
    $total   = round($price * $travelers, 2);
    $deposit = round($total * (DEPOSIT_PERCENT / 100), 2);
}

$data['package_name']   = $package['name'];
$data['total_amount']   = $total;
$data['deposit_amount'] = $deposit;

// ── Generate booking reference ────────────────────────────
$ref = generateBookingRef();

// ── Resolve (or create) the unified customer record first, so the
//    booking can be inserted with the link already in place ─────
$pdo = Database::getInstance();
$customerId = null;
try {
    $customerId = (new Customer())->findOrCreateByEmail($data['email'], $data['full_name'], $data['phone'], $data['nationality'] ?: null);
} catch (Exception $e) {
    error_log('[SZV Booking Customer] ' . $e->getMessage());
}

// ── Save to DB ────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        INSERT INTO bookings
            (booking_ref, customer_id, full_name, email, phone, country, travelers, adults, children,
             package_id, package_name, accommodation_level,
             travel_date, special_request, trip_extension,
             total_amount, deposit_amount,
             payment_status, payment_method, created_at)
        VALUES
            (:ref, :customer_id, :name, :email, :phone, :country, :travelers, :adults, :children,
             :pkg_id, :pkg_name, :acc_level,
             :travel_date, :special_request, :trip_extension,
             :total, :deposit,
             'pending', :method, NOW())
    ");
    $stmt->execute([
        'ref'            => $ref,
        'customer_id'    => $customerId,
        'name'           => $data['full_name'],
        'email'          => $data['email'],
        'phone'          => $data['phone'],
        'country'        => $data['nationality'] ?: null,
        'travelers'      => $data['travelers'],
        'adults'         => $data['adults'],
        'children'       => $data['children'],
        'pkg_id'         => $data['package_id'],
        'pkg_name'       => $data['package_name'],
        'acc_level'      => $data['accommodation_level'],
        'travel_date'    => $data['travel_date'],
        'special_request'=> $data['special_request'],
        'trip_extension' => $data['trip_extension'],
        'total'          => $data['total_amount'],
        'deposit'        => $data['deposit_amount'],
        'method'         => $data['payment_method'],
    ]);
} catch (Exception $e) {
    error_log('[SZV Booking DB] ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Booking could not be saved. Please try again or WhatsApp us.']);
}

// ── Schedule the 4 standard reminder emails ─────────────────
BookingReminderScheduler::schedule($pdo, $ref, $data['travel_date']);

// ── Issue the Track Booking password — plaintext only exists for the
//    rest of this request, to go out in the confirmation email below, plus
//    a one-time session flash so confirmation.php (the very next page,
//    same browser) can show it once too, in case the email is delayed or
//    caught by spam filtering ──
$trackingPassword = \App\Services\BookingTracking::issue($pdo, $ref);
$_SESSION['_track_pwd_' . $ref] = $trackingPassword;

// ── Auto-generate a real quotation record for this booking ──
// When the package has a linked itinerary, $builtQuote/$quoteTotals were
// already computed above to price the booking itself — reuse them here
// so the quotation the client receives shows exactly the numbers they
// were actually charged, broken down by line item for their exact party.
$quotationId = null;
if ($builtQuote && $quoteTotals) {
    try {
        $destinations = array_values(array_unique(array_filter(array_map(
            fn($d) => $d['location'] ?? '', $builtQuote['day_titles']
        ))));
        $quoteRef = 'SZV-Q' . strtoupper(bin2hex(random_bytes(4)));
        $pdo->prepare("
            INSERT INTO quotations
                (quote_ref, customer_id, package_id, package_name, itinerary_id,
                 client_name, client_email, client_phone, client_country,
                 adults, children, duration_days, start_date, destinations,
                 accommodation_level, line_items, subtotal, vat, grand_total,
                 per_person, deposit_amount, balance_due, status, booking_ref,
                 valid_until, sent_at, accepted_at, created_at)
            VALUES
                (?,?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?,?,?, 'converted', ?,
                 CURDATE(), NOW(), NOW(), NOW())
        ")->execute([
            $quoteRef, $customerId, $data['package_id'], $data['package_name'], $package['itinerary_id'],
            $data['full_name'], $data['email'], $data['phone'], $data['nationality'] ?: null,
            $adults, $children, count($builtQuote['day_titles']) ?: 5,
            $data['travel_date'], json_encode($destinations),
            $data['accommodation_level'], json_encode($builtQuote['line_items']),
            $quoteTotals['subtotal'], $quoteTotals['vat'], $quoteTotals['grand_total'],
            round($quoteTotals['grand_total'] / max(1, $travelers), 2),
            $quoteTotals['deposit'], $quoteTotals['balance'], $ref,
        ]);
        $quotationId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE bookings SET quotation_id=?, itinerary_id=? WHERE booking_ref=?")
            ->execute([$quotationId, $package['itinerary_id'], $ref]);
    } catch (Exception $e) {
        error_log('[SZV Booking Quotation] ' . $e->getMessage());
        $quotationId = null; // booking still stands without the attached PDF
    }
}

// ── Render two separate PDFs — Quotation (pricing) and Itinerary
//    (day-by-day) — to attach to the confirmation email, instead of one
//    long combined document ──
$attachments = [];
$pdfFile = $quotationId
    ? PdfRenderer::renderQuotation($quotationId, 'Quotation-' . $ref)
    : PdfRenderer::renderBooking($ref, 'Booking-' . $ref);
if ($pdfFile) {
    $attachments[] = $pdfFile;
}
$itinFile = $quotationId
    ? PdfRenderer::renderQuotationItinerary($quotationId, 'Itinerary-' . $ref)
    : PdfRenderer::renderBookingItinerary($ref, 'Itinerary-' . $ref);
if ($itinFile) {
    $attachments[] = $itinFile;
}

// ── Send confirmation email via the queue (silent — never crash the response) ──
try {
    $queued = Mailer::queue('booking_confirmation', [
        'to_email'            => $data['email'],
        'to_name'             => $data['full_name'],
        'client_name'         => $data['full_name'],
        'booking_ref'         => $ref,
        'tracking_password'   => $trackingPassword,
        'package_name'        => $data['package_name'],
        'travel_date'         => $data['travel_date'],
        'travelers'           => $data['travelers'],
        'accommodation_level' => $data['accommodation_level'],
        'total_amount'        => $data['total_amount'],
        'deposit_amount'      => $data['deposit_amount'],
    ], null, null, $attachments ?: null);
    if ($queued) {
        $pdo->prepare("UPDATE bookings SET confirmation_email_sent=1 WHERE booking_ref=?")->execute([$ref]);
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
