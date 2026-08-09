<?php require_once __DIR__ . '/app/bootstrap.php'; ?>
<?php

$pageTitle = 'Track Booking';

$pageDescription = 'Track your Safari Zangu Ventures booking.';

$pageCanonical = 'https://safarizanguven.com/track.php';

ob_start();

include __DIR__ . '/views/track.php';

$content = ob_get_clean();

include __DIR__ . '/layouts/app.php';
