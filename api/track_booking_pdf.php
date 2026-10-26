<?php
/**
 * Public "Download PDF Confirmation" for the Track Booking page.
 * URL: api/track_booking_pdf.php?ref=SZVXXX&token=XXXXXXXX
 *
 * No admin session — gated the same way api/track_lookup.php already is,
 * except via a short-lived download token instead of the real tracking
 * password (SEC-10: the real password reused in a GET link would sit in
 * browser history, server access logs, and any Referer header along the
 * way, indefinitely — issued once per successful lookup by
 * BookingTracking::issueDownloadToken(), 15-minute expiry, useless for
 * anything but re-downloading this one booking's documents). Both
 * endpoints share the SAME per-IP rate-limit counter (identical lock
 * file), so a guest can't dodge the 3-attempt lockout by hitting this
 * endpoint instead of the JSON lookup. On success it renders the exact
 * same browser-print page api/booking_pdf.php already gives admins —
 * same template, no second copy of the PDF markup to maintain.
 */
require_once __DIR__ . '/../app/bootstrap.php';

function track_pdf_fail(string $message, int $code = 400): void {
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
    track_pdf_fail('Too many failed attempts. Please try again in ' . ceil($remaining / 60) . ' minutes, or contact us on WhatsApp.', 429);
}

// ── Validate input — identical rules to track_lookup.php ──────────────
$ref      = strtoupper(trim($_GET['ref'] ?? ''));
$token = trim($_GET['token'] ?? '');

if (!$ref || !$token) {
    track_pdf_fail('Booking ID and download token are both required.');
}
if (!preg_match('/^SZV[A-Z0-9]{4,17}$/i', $ref)) {
    track_pdf_fail('Invalid Booking ID format.');
}

// ── Confirm ownership (ref + download token must match) ──
$found = \App\Services\BookingTracking::verifyDownloadToken($ref, $token);

if (!$found) {
    $lockData['attempts']++;
    $lockData['last_attempt'] = time();
    file_put_contents($lockFile, json_encode($lockData), LOCK_EX);
    track_pdf_fail('This download link has expired. Please look up your booking again.', 404);
}

// Success — reset the shared attempt counter, same as track_lookup.php.
$lockData['attempts']     = 0;
$lockData['last_success'] = time();
file_put_contents($lockFile, json_encode($lockData), LOCK_EX);

// Render the same admin-facing template — SZV_PDF_INTERNAL_RENDER skips
// its normal AuthMiddleware::requireAdmin() gate. Safe here because we've
// already independently verified ref+date ownership above, matching the
// exact same proof-of-ownership track_lookup.php requires.
define('SZV_PDF_INTERNAL_RENDER', true);
$_GET['ref'] = $ref;
require __DIR__ . '/booking_pdf.php';
