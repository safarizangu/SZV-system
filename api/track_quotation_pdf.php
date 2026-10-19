<?php
/**
 * Public "Download Quotation / Invoice PDF" for the Track Booking page.
 * URL: api/track_quotation_pdf.php?ref=SZVXXX&password=XXXXXXXX
 *
 * Same proof-of-ownership and shared rate-limit lock file as
 * api/track_booking_pdf.php / api/track_lookup.php. Renders
 * api/quotation_pdf.php for the booking's linked quotation — that file
 * already relabels itself Quotation/partial Invoice/full Invoice from the
 * booking's own live payment_status, so nothing extra is needed here to
 * pick the right document; it's always whatever's currently true.
 */
require_once __DIR__ . '/../app/bootstrap.php';

function track_qpdf_fail(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;padding:3rem;text-align:center;color:#374151">'
        . '<p>' . htmlspecialchars($message) . '</p>'
        . '<p><a href="' . htmlspecialchars(BASE_PATH) . '/track.php">&larr; Back to Track Booking</a></p>'
        . '</body>';
    exit;
}

// ── Server-side rate limiting — same lock file as track_lookup.php ────
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

if ((time() - $lockData['last_attempt']) >= $lockSeconds) {
    $lockData['attempts'] = 0;
}

if ($lockData['attempts'] >= $maxAttempts) {
    $remaining = $lockSeconds - (time() - $lockData['last_attempt']);
    track_qpdf_fail('Too many failed attempts. Please try again in ' . ceil($remaining / 60) . ' minutes, or contact us on WhatsApp.', 429);
}

// ── Validate input — identical rules to track_lookup.php ──────────────
$ref      = strtoupper(trim($_GET['ref'] ?? ''));
$password = trim($_GET['password'] ?? '');

if (!$ref || !$password) {
    track_qpdf_fail('Booking ID and Track Booking password are both required.');
}
if (!preg_match('/^SZV[A-Z0-9]{4,17}$/i', $ref)) {
    track_qpdf_fail('Invalid Booking ID format.');
}

// ── Confirm ownership (ref + password must match, same as lookup) ──
try {
    $db    = \App\Models\Database::getInstance();
    $found = \App\Services\BookingTracking::verify($db, $ref, $password);
} catch (\Exception $e) {
    error_log('[Track Quotation PDF] ' . $e->getMessage());
    track_qpdf_fail('An error occurred. Please try again or contact us on WhatsApp.', 500);
}

if (!$found) {
    $lockData['attempts']++;
    $lockData['last_attempt'] = time();
    file_put_contents($lockFile, json_encode($lockData), LOCK_EX);
    track_qpdf_fail('No booking found with those details.', 404);
}

if (empty($found['quotation_id'])) {
    track_qpdf_fail('No quotation is attached to this booking yet. Please contact us on WhatsApp.', 404);
}

// Success — reset the shared attempt counter, same as track_lookup.php.
$lockData['attempts']     = 0;
$lockData['last_success'] = time();
file_put_contents($lockFile, json_encode($lockData), LOCK_EX);

// Render the same admin-facing template — SZV_PDF_INTERNAL_RENDER skips
// its normal AuthMiddleware::requireAdmin() gate. Safe here because we've
// already independently verified ref+password ownership above.
define('SZV_PDF_INTERNAL_RENDER', true);
$_GET['id'] = (int)$found['quotation_id'];
require __DIR__ . '/quotation_pdf.php';
