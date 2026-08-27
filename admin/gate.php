<?php
/**
 * gate.php — legacy entry point.
 * Redirects to the new login page.
 */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;

if (AuthMiddleware::isAuthenticated()) {
    header('Location: index.php'); exit;
}

// Preserve any reason parameter
$reason = $_GET['reason'] ?? '';
$qs     = $reason ? '?reason=' . urlencode($reason) : '';
header('Location: login.php' . $qs);
exit;
