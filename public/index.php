<?php require_once __DIR__ . '/../app/bootstrap.php'; ?>
<?php
$pageTitle = 'Safari Zangu Ventures - Private Tanzania Safari Experiences';
$pageDescription = 'Private Tanzania Safari Experiences. Serengeti, Ngorongoro, Zanzibar and custom safari adventures.';
$pageCanonical = 'https://safarizanguven.com/';

ob_start();
include __DIR__ . '/../views/home.php';
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
