<?php
/**
 * Public "Download Itinerary PDF" for the Track Booking page.
 * URL: api/track_itinerary_pdf.php?ref=SZVXXX&token=XXXXXXXX
 *
 * SEC-10: gated by a short-lived download token (BookingTracking::
 * issueDownloadToken(), 15-minute expiry) rather than the real tracking
 * password, which used to sit in this GET link indefinitely — in
 * browser history, server access logs, and any Referer header along the
 * way. Same proof-of-ownership and shared rate-limit lock file as
 * api/track_booking_pdf.php / api/track_quotation_pdf.php / api/track_lookup.php.
 * Renders api/quotation_itinerary_pdf.php for the booking's linked quotation.
 */
require_once __DIR__ . '/../app/bootstrap.php';

function track_ipdf_fail(string $message, int $code = 400): void {
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
    track_ipdf_fail('Too many failed attempts. Please try again in ' . ceil($remaining / 60) . ' minutes, or contact us on WhatsApp.', 429);
}

// ── Validate input — identical rules to track_lookup.php ──────────────
$ref      = strtoupper(trim($_GET['ref'] ?? ''));
$token = trim($_GET['token'] ?? '');

if (!$ref || !$token) {
    track_ipdf_fail('Booking ID and download token are both required.');
}
if (!preg_match('/^SZV[A-Z0-9]{4,17}$/i', $ref)) {
    track_ipdf_fail('Invalid Booking ID format.');
}

// ── Confirm ownership (ref + download token must match) ──
if (!\App\Services\BookingTracking::verifyDownloadToken($ref, $token)) {
    $lockData['attempts']++;
    $lockData['last_attempt'] = time();
    file_put_contents($lockFile, json_encode($lockData), LOCK_EX);
    track_ipdf_fail('This download link has expired. Please look up your booking again.', 404);
}

try {
    $db    = \App\Models\Database::getInstance();
    $stmt  = $db->prepare("SELECT quotation_id FROM bookings WHERE booking_ref = ? LIMIT 1");
    $stmt->execute([$ref]);
    $found = $stmt->fetch();
} catch (\Exception $e) {
    error_log('[Track Itinerary PDF] ' . $e->getMessage());
    track_ipdf_fail('An error occurred. Please try again or contact us on WhatsApp.', 500);
}

if (empty($found['quotation_id'])) {
    track_ipdf_fail('No itinerary is attached to this booking yet. Please contact us on WhatsApp.', 404);
}

// Success — reset the shared attempt counter, same as track_lookup.php.
$lockData['attempts']     = 0;
$lockData['last_success'] = time();
file_put_contents($lockFile, json_encode($lockData), LOCK_EX);

define('SZV_PDF_INTERNAL_RENDER', true);
$_GET['id'] = (int)$found['quotation_id'];
require __DIR__ . '/quotation_itinerary_pdf.php';
