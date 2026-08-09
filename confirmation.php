<?php require_once __DIR__ . '/app/bootstrap.php'; ?>
<?php

$pageTitle = 'Booking Confirmation';

$pageDescription = 'Booking confirmation details.';

$pageCanonical = 'https://safarizanguven.com/confirmation.php';

ob_start();

include __DIR__ . '/views/confirmation.php';

$content = ob_get_clean();

include __DIR__ . '/layouts/app.php';
