<?php
/**
 * Reminder Processor
 * Cron: 0 * * * * /usr/bin/php /var/www/html/safari-zangu/php/process_reminders.php
 * (runs once per hour)
 */
require_once __DIR__ . '/../app/bootstrap.php';

$isCli   = php_sapi_name() === 'cli';
$hasKey  = isset($_GET['key']) && hash_equals($_GET['key'], 'szv-cron-2026');
if (!$isCli && !$hasKey) { http_response_code(403); exit('Forbidden'); }

$db = \App\Models\Database::getInstance();

// Get reminders due in the next hour that haven't been sent
try {
    $due = $db->query(
        "SELECT r.*, b.email, b.full_name, b.travel_date, b.total_amount, b.deposit_amount
         FROM booking_reminders r
         JOIN bookings b ON b.booking_ref = r.booking_ref COLLATE utf8mb4_unicode_ci
         WHERE r.status = 'pending'
           AND r.send_at <= DATE_ADD(NOW(), INTERVAL 1 HOUR)
         ORDER BY r.send_at ASC LIMIT 30"
    )->fetchAll();
} catch (\Exception $e) {
    echo 'DB error: ' . $e->getMessage() . "\n"; exit(1);
}

foreach ($due as $r) {
    try {
        // Queue to email_queue instead of sending directly
        $db->prepare("
            INSERT INTO email_queue (to_email, to_name, subject, body, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE id=id
        ")->execute([
            $r['email'], $r['full_name'],
            $r['subject'] ?? 'Safari Reminder — ' . $r['booking_ref'],
            $r['body']    ?? buildReminderBody($r),
        ]);

        $db->prepare("UPDATE booking_reminders SET status='sent', sent_at=NOW() WHERE id=?")
           ->execute([$r['id']]);

        echo '[' . date('H:i:s') . '] Queued reminder: ' . $r['type'] . ' for ' . $r['booking_ref'] . "\n";

    } catch (\Exception $e) {
        $db->prepare("UPDATE booking_reminders SET status='failed' WHERE id=?")->execute([$r['id']]);
        echo 'Failed: ' . $e->getMessage() . "\n";
    }
}

function buildReminderBody(array $r): string {
    $name = htmlspecialchars($r['full_name']);
    $ref  = htmlspecialchars($r['booking_ref']);
    $date = date('d F Y', strtotime($r['travel_date']));
    return match($r['type']) {
        'deposit_due' => "<p>Dear {$name},</p><p>This is a reminder that your deposit of USD $" . number_format($r['deposit_amount'],0) . " for booking <strong>{$ref}</strong> (travel date: {$date}) is due within 48 hours.</p><p>Please send payment to confirm your safari dates.</p>",
        'balance_due' => "<p>Dear {$name},</p><p>Your safari balance of USD $" . number_format($r['total_amount']-$r['deposit_amount'],0) . " for <strong>{$ref}</strong> is due. Your travel date is {$date}.</p>",
        'departure'   => "<p>Dear {$name},</p><p>Your Tanzania safari departs in 7 days on {$date}! Your guide and vehicle details will be shared tomorrow. Is there anything you need before you travel?</p>",
        'post_trip'   => "<p>Dear {$name},</p><p>We hope you had an incredible safari experience! We would love to hear your feedback. Please take 2 minutes to share your thoughts — it helps us and future travelers.</p>",
        default       => "<p>Dear {$name},</p><p>A reminder regarding your Safari Zangu booking <strong>{$ref}</strong>.</p>",
    };
}

echo "Done.\n";
