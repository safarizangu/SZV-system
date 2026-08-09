<?php
/**
 * Custom Safari Request Handler v3
 * All exits via cs_respond() — no raw echo+exit
 */
ob_start();
header('Content-Type: application/json');

function cs_respond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\CustomSafariRequest;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimiter;
use App\Helpers\Formatter;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cs_respond(['success' => false, 'message' => 'Method not allowed.']);
}

try {
    RateLimiter::enforceOrDie('custom_safari', 5, 3600);
} catch (\Exception $e) {
    cs_respond(['success' => false, 'message' => 'Too many requests. Please try again later.']);
}

CsrfMiddleware::validateOrDie();

// Collect & sanitize destinations (array of strings)
$rawDests = $_POST['destinations'] ?? [];
if (is_string($rawDests)) {
    $rawDests = array_filter(explode(',', $rawDests));
}
$destinations = array_map([Formatter::class, 'sanitize'], (array)$rawDests);

// Collect experiences
$rawExp = $_POST['experiences'] ?? [];
$experiences = array_map([Formatter::class, 'sanitize'], (array)$rawExp);

$data = [
    'full_name'              => Formatter::sanitize($_POST['full_name']              ?? ''),
    'email'                  => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
    'phone'                  => Formatter::sanitize($_POST['phone']                  ?? ''),
    'country'                => Formatter::sanitize($_POST['country']                ?? ''),
    'adults'                 => max(1, (int)($_POST['adults']                        ?? 1)),
    'children'               => max(0, (int)($_POST['children']                      ?? 0)),
    'infants'                => max(0, (int)($_POST['infants']                       ?? 0)),
    'start_date'             => Formatter::sanitize($_POST['start_date']             ?? ''),
    'duration_days'          => max(1, (int)($_POST['duration_days']                 ?? 5)),
    'flexible_dates'         => Formatter::sanitize($_POST['flexible_dates']         ?? 'Yes'),
    'destinations'           => $destinations,
    'accommodation_standard' => Formatter::sanitize($_POST['accommodation_standard'] ?? ''),
    'meal_plan'              => Formatter::sanitize($_POST['meal_plan']              ?? ''),
    'room_layout'            => Formatter::sanitize($_POST['room_layout']            ?? ''),
    'guide_language'         => Formatter::sanitize($_POST['guide_language']         ?? 'english'),
    'experiences'            => $experiences,
    'total_budget'           => Formatter::sanitize($_POST['total_budget']           ?? ''),
    'message'                => Formatter::sanitize($_POST['message']                ?? ''),
];

// Validate
if (empty($data['full_name'])) {
    cs_respond(['success' => false, 'message' => 'Please enter your full name.']);
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    cs_respond(['success' => false, 'message' => 'Please enter a valid email address.']);
}
if (empty($destinations)) {
    cs_respond(['success' => false, 'message' => 'Please select at least one destination.']);
}

// Save to DB
try {
    $model = new CustomSafariRequest();
    $id    = $model->create($data);
} catch (\Exception $e) {
    error_log('[SZV Custom] ' . $e->getMessage());
    cs_respond(['success' => false, 'message' => 'Could not save your request. Please email us at info@safarizanguven.com.']);
}

// Auto-queue acknowledgement email
try {
    $name  = $data['full_name'];
    $dests = implode(', ', $destinations ?: ['Tanzania']);
    $body  = "<p>Dear " . htmlspecialchars($name) . ",</p>"
           . "<p>Thank you for your custom safari enquiry! We've received your request for a private safari to <strong>" . htmlspecialchars($dests) . "</strong>.</p>"
           . "<p>Our team will send you a fully personalised proposal — complete with itinerary, accommodation options, and transparent pricing — within <strong>24 hours</strong>.</p>"
           . "<p>For faster response: <strong>WhatsApp +255 663 430 934</strong></p>"
           . "<p>Safari Zangu Ventures · Arusha, Tanzania</p>";

    $pdo = \App\Models\Database::getInstance();
    $pdo->prepare("INSERT INTO email_queue (to_email, to_name, subject, body, created_at) VALUES (?,?,?,?,NOW())")
        ->execute([$data['email'], $name, "Your Custom Safari Request — Safari Zangu Ventures", $body]);

    // Notify admin
    $adminBody = "New custom safari request from {$name} ({$data['email']})\n"
               . "Destinations: " . implode(', ', $destinations) . "\n"
               . "Duration: {$data['duration_days']} days · Adults: {$data['adults']} · Children: {$data['children']}\n"
               . "Budget: {$data['total_budget']}\n"
               . "Message: {$data['message']}";
    $pdo->prepare("INSERT INTO email_queue (to_email, to_name, subject, body, created_at) VALUES (?,?,?,?,NOW())")
        ->execute([ADMIN_EMAIL, 'Admin', "New Custom Safari Request — {$name}", nl2br($adminBody)]);

} catch (\Exception $e) {
    error_log('[SZV CustomEmail] ' . $e->getMessage());
    // Non-fatal
}

// Respond immediately — email sends via cron
cs_respond([
    'success' => true,
    'message' => "Thank you, {$data['full_name']}! We've received your request and will send a personalised proposal within 24 hours.",
]);
