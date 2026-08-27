<?php
require_once __DIR__ . '/../app/bootstrap.php';

// Clear all session data
$_SESSION = [];

// Destroy session cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-3600,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();
header('Location: login.php?reason=logout'); exit;
