<?php require_once __DIR__ . '/../app/bootstrap.php'; ?>
<?php

$pageTitle = 'Share Your Experience';
$pageDescription = 'Tell us about your Tanzania safari with Safari Zangu Ventures.';
$pageCanonical = 'https://safarizanguven.com/review.php';

$ref = strtoupper(trim($_GET['ref'] ?? ''));
$booking = null;
$alreadyReviewed = false;
if ($ref) {
    try {
        $db = \App\Models\Database::getInstance();
        $s = $db->prepare("SELECT booking_ref, full_name FROM bookings WHERE booking_ref=? LIMIT 1");
        $s->execute([$ref]);
        $booking = $s->fetch() ?: null;
        if ($booking) {
            $rs = $db->prepare("SELECT id FROM reviews WHERE booking_ref=? LIMIT 1");
            $rs->execute([$ref]);
            $alreadyReviewed = (bool)$rs->fetch();
        }
    } catch (\Exception $e) { error_log('[review.php] ' . $e->getMessage()); }
}

ob_start();
include __DIR__ . '/../views/review.php';
$content = ob_get_clean();

include __DIR__ . '/../layouts/app.php';
