<?php require_once __DIR__ . '/../app/bootstrap.php'; ?>
<?php

$pageTitle = 'Safari Packages - Safari Zangu Ventures';

$pageDescription = 'Browse all Safari Zangu safari packages, itineraries, accommodations and pricing.';

$pageCanonical = 'https://safarizanguven.com/packages.php';
$bodyClass = 'packages-page';

ob_start();

include __DIR__ . '/../views/packages.php';

$content = ob_get_clean();

include __DIR__ . '/../layouts/app.php';
