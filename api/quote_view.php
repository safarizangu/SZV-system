<?php
/**
 * Client-facing quotation view. Reached from the link in the quotation email.
 * URL: api/quote_view.php?t=<public_token>
 *
 * Access is by token only — no admin session, and the numeric quotation id is
 * never accepted from the request. Each view is recorded, and a quotation that
 * was just sent moves to 'viewed' automatically. Status never moves backward.
 */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\RateLimiter;

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$token = $_GET['t'] ?? '';
if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Quotation not found.');
}

try {
    RateLimiter::enforceOrDie('quote_view', 120, 3600);
} catch (\Exception $e) {
    http_response_code(429);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Too many requests. Please try again later.');
}

$db = \App\Models\Database::getInstance();
$s  = $db->prepare("SELECT id, status FROM quotations WHERE public_token = ? LIMIT 1");
$s->execute([$token]);
$quote = $s->fetch();
if (!$quote) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Quotation not found.');
}

$db->prepare("UPDATE quotations
              SET view_count = view_count + 1,
                  first_viewed_at = COALESCE(first_viewed_at, NOW()),
                  last_viewed_at = NOW(),
                  status = IF(status = 'sent', 'viewed', status)
              WHERE id = ?")
   ->execute([(int)$quote['id']]);

// Render the same document the client receives by email, via the trusted
// internal path (bypasses the admin-session check only for this include).
define('SZV_PDF_INTERNAL_RENDER', true);
$_GET['id'] = (string)(int)$quote['id'];
header('Content-Type: text/html; charset=UTF-8');
include __DIR__ . '/quotation_pdf.php';
