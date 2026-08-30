<?php
require_once __DIR__ . '/../app/bootstrap.php';

$pageTitle       = 'Contact Us — Safari Zangu Ventures';
$pageDescription = 'Get in touch with Safari Zangu Ventures. WhatsApp, email, or our contact form — we respond within 24 hours from Arusha, Tanzania.';
$pageCanonical   = 'https://safarizanguven.com/contact.php';

ob_start();
include __DIR__ . '/../views/contact.php';
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';