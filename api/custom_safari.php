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
use App\Middleware\Honeypot;
use App\Helpers\Formatter;
use App\Mail\Mailer;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cs_respond(['success' => false, 'message' => 'Method not allowed.']);
}

// Platform Controls → Maintenance Mode (admin/settings.php)
if (MAINTENANCE_MODE) {
    cs_respond(['success' => false, 'message' => "We're temporarily updating our booking system. Please WhatsApp us directly, or try again shortly."]);
}

try {
    RateLimiter::enforceOrDie('custom_safari', 5, 3600);
} catch (\Exception $e) {
    cs_respond(['success' => false, 'message' => 'Too many requests. Please try again later.']);
}

CsrfMiddleware::validateOrDie();

// Honeypot — silently pretend success so a spam script has no signal to
// adapt against. Nothing is saved or emailed.
if (Honeypot::caught()) {
    cs_respond(['success' => true, 'message' => "Thank you! We've received your request and will send a personalised proposal within 24 hours."]);
}

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
    'rooms'                  => isset($_POST['rooms']) && $_POST['rooms'] !== '' ? max(1, (int)$_POST['rooms']) : null,
    'airport_pickup'         => !empty($_POST['airport_pickup']) ? 1 : 0,
    // Pre-existing bug, found while testing: 'Preferred Start Date' has
    // always been optional on the form (no required attribute), but an
    // empty string here fails to save at all — MySQL's strict mode
    // rejects '' for a DATE column. NULL is what "not specified" means.
    'start_date'             => Formatter::sanitize($_POST['start_date'] ?? '') ?: null,
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

// Auto-queue acknowledgement + admin notification — through the branded
// template system (EmailTemplate::tpl_custom_request_ack/_admin), which
// already existed and was documented as used here, but this handler was
// building raw unstyled HTML strings directly into email_queue instead.
try {
    Mailer::queue('custom_request_ack', array_merge($data, [
        'to_email' => $data['email'],
        'to_name'  => $data['full_name'],
    ]));
    Mailer::queueAdmin('custom_request_admin', $data);
} catch (\Exception $e) {
    error_log('[SZV CustomEmail] ' . $e->getMessage());
    // Non-fatal
}

// Respond immediately — email sends via cron
cs_respond([
    'success' => true,
    'message' => "Thank you, {$data['full_name']}! We've received your request and will send a personalised proposal within 24 hours.",
]);
