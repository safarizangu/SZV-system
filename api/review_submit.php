<?php
ob_start();
header('Content-Type: application/json');

function review_respond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimiter;
use App\Middleware\Honeypot;
use App\Helpers\Formatter;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    review_respond(['success' => false, 'message' => 'Method not allowed.']);
}

try {
    RateLimiter::enforceOrDie('review', 5, 3600);
} catch (Exception $e) {
    review_respond(['success' => false, 'message' => 'Too many requests. Please wait a moment.']);
}

CsrfMiddleware::validateOrDie();

if (Honeypot::caught()) {
    review_respond(['success' => true, 'message' => 'Thank you for your feedback!']);
}

$ref     = strtoupper(trim($_POST['booking_ref'] ?? ''));
$name    = Formatter::sanitize($_POST['guest_name']    ?? '');
$country = Formatter::sanitize($_POST['guest_country'] ?? '');
$rating  = (int)($_POST['rating'] ?? 0);
$nps     = $_POST['nps_score'] !== '' ? (int)($_POST['nps_score'] ?? -1) : null;
$comment = Formatter::sanitize($_POST['comment'] ?? '');

if (!$ref) {
    review_respond(['success' => false, 'message' => 'Missing booking reference.']);
}
if (empty($name)) {
    review_respond(['success' => false, 'message' => 'Please enter your name.']);
}
if ($rating < 1 || $rating > 5) {
    review_respond(['success' => false, 'message' => 'Please select a rating.']);
}
if ($nps !== null && ($nps < 0 || $nps > 10)) {
    $nps = null;
}

$db = \App\Models\Database::getInstance();

try {
    $bs = $db->prepare("SELECT id, customer_id FROM bookings WHERE booking_ref=? LIMIT 1");
    $bs->execute([$ref]);
    $booking = $bs->fetch();
    if (!$booking) {
        review_respond(['success' => false, 'message' => 'Booking not found.']);
    }
} catch (\Exception $e) {
    error_log('[SZV Review] booking lookup: ' . $e->getMessage());
    review_respond(['success' => false, 'message' => 'Could not verify booking. Please try again.']);
}

try {
    $db->prepare("
        INSERT INTO reviews (booking_ref, customer_id, guest_name, guest_country, rating, nps_score, comment, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([$ref, $booking['customer_id'] ?: null, $name, $country ?: null, $rating, $nps, $comment ?: null]);
} catch (\Exception $e) {
    // UNIQUE(booking_ref) — a repeat submission for the same booking.
    if (str_contains($e->getMessage(), 'uniq_booking_review')) {
        review_respond(['success' => true, 'message' => 'You\'ve already reviewed this trip — thank you!']);
    }
    error_log('[SZV Review] insert: ' . $e->getMessage());
    review_respond(['success' => false, 'message' => 'Could not save your review. Please try again or contact us directly.']);
}

// Best-effort admin notification — non-blocking, never fails the submission.
try {
    \App\Mail\Mailer::queueRaw(
        defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@safarizanguven.com',
        'Safari Zangu Admin',
        "New Review ({$rating}★) — {$ref}",
        '<p><strong>' . htmlspecialchars($name) . '</strong> (' . htmlspecialchars($ref) . ') left a ' . $rating . '-star review'
            . ($nps !== null ? ", NPS {$nps}/10" : '') . '.</p>'
            . ($comment ? '<p>' . nl2br(htmlspecialchars($comment)) . '</p>' : '')
            . '<p><a href="' . (defined('SITE_URL') ? SITE_URL : '') . '/admin/reviews.php">View in admin →</a></p>',
        null,
        'review_admin_notification'
    );
} catch (\Throwable $e) {}

review_respond(['success' => true, 'message' => 'Thank you! Your review means a lot to us.']);
