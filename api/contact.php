<?php
ob_start();
header('Content-Type: application/json');

function contact_respond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d);

    // Flush response to browser IMMEDIATELY — don't wait for email
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();   // PHP-FPM: sends response, keeps script running
    } else {
        // Apache mod_php fallback: flush + close connection
        $size = ob_get_length();
        header('Content-Length: ' . strlen(json_encode($d)));
        header('Connection: close');
        ob_end_flush();
        flush();
    }
}

require_once __DIR__ . '/../app/bootstrap.php';

use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimiter;
use App\Middleware\Honeypot;
use App\Helpers\Formatter;
use App\Mail\Mailer;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    RateLimiter::enforceOrDie('contact', 5, 3600);
} catch (Exception $e) {
    contact_respond(['success' => false, 'message' => 'Too many requests. Please wait a moment.']);
    exit;
}

// Unconditional — a forged cross-site submission that simply omits the
// _csrf field must fail, not skip the check entirely (the previous
// `if (!empty($_POST['_csrf']))` guard let exactly that through).
CsrfMiddleware::validateOrDie();

// Honeypot — silently pretend success so a spam script has no signal to
// adapt against. Nothing is saved or emailed.
if (Honeypot::caught()) {
    contact_respond(['success' => true, 'message' => "Thanks! We’ll be in touch."]);
    exit;
}

$name    = Formatter::sanitize($_POST['full_name'] ?? '');
$email   = filter_var($_POST['email']   ?? '', FILTER_SANITIZE_EMAIL);
$phone   = Formatter::sanitize($_POST['phone']     ?? '');
$subject = Formatter::sanitize($_POST['subject']   ?? 'General Enquiry');
$message = Formatter::sanitize($_POST['message']   ?? '');

if (empty($name)) {
    contact_respond(['success' => false, 'message' => 'Please enter your full name.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    contact_respond(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}
if (empty($message)) {
    contact_respond(['success' => false, 'message' => 'Please enter your message.']);
    exit;
}

// Save to DB
try {
    $pdo  = \App\Models\Database::getInstance();
    $stmt = $pdo->prepare(
        "INSERT INTO contact_messages (full_name, email, phone, subject, message, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$name, $email, $phone, $subject, $message]);
} catch (\Exception $e) {
    error_log('[SZV Contact DB] ' . $e->getMessage());
    contact_respond(['success' => false, 'message' => 'Could not save your message. Please email us at info@safarizanguven.com.']);
    exit;
}

// ── Queue guest auto-reply + admin notification ─────────────
// Queuing is a fast DB insert (unlike a live SMTP send), so this can
// happen before responding — the cron sends both within 2 minutes,
// with retries, and a visible record in admin/email_queue.php.
try {
    Mailer::queueBoth(
        'contact_acknowledgement', [
            'to_email'    => $email,
            'to_name'     => $name,
            'client_name' => $name,
            'subject'     => $subject,
        ],
        'contact_admin_notification', [
            'client_name'  => $name,
            'client_email' => $email,
            'phone'        => $phone,
            'subject'      => $subject,
            'message'      => $message,
        ]
    );
} catch (\Exception $e) {
    error_log('[SZV Contact Mail] ' . $e->getMessage());
    // Non-fatal — the message itself is already saved above
}

// ── SUCCESS ────────────────────────────────────────
contact_respond(['success' => true, 'message' => "Thank you, {$name}! We\u2019ll respond within 24 hours."]);
