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
use App\Helpers\Formatter;

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

if (!empty($_POST['_csrf'])) {
    try {
        CsrfMiddleware::validateOrDie();
    } catch (Exception $e) {
        contact_respond(['success' => false, 'message' => 'Security token expired. Please refresh the page.']);
        exit;
    }
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

// ── SUCCESS: send response to browser NOW ──────────────────────
// Browser gets the response immediately. Email sends after.
contact_respond(['success' => true, 'message' => "Thank you, {$name}! We\u2019ll respond within 24 hours."]);

// ── Email: runs AFTER browser has already received the response ──
// If this times out or fails, the user never sees it
ignore_user_abort(true);
set_time_limit(30);

try {
    $headers  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Safari Zangu Ventures <admin@safarizanguven.com>\r\n";

    $autoReply = "<p>Dear " . htmlspecialchars($name) . ",</p>"
               . "<p>Thank you for contacting <strong>Safari Zangu Ventures</strong>. "
               . "We have received your message and will respond within 24 hours.</p>"
               . "<p>For faster response, WhatsApp us: <strong>+255 663 430 934</strong></p>"
               . "<p>Best regards,<br>Safari Zangu Ventures &middot; Arusha, Tanzania</p>";

    @mail($email, "We received your message — Safari Zangu Ventures", $autoReply, $headers);

    $adminBody = "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\nSubject: {$subject}\n\n{$message}";
    @mail(ADMIN_EMAIL, "New Contact Message: {$subject}", $adminBody, "From: {$email}");

} catch (\Exception $e) {
    error_log('[SZV Contact Mail] ' . $e->getMessage());
}
