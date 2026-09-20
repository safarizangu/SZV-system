<?php
/**
 * Reminder Processor
 * Cron: 0 * * * * /usr/bin/php /var/www/html/safari-zangu/php/process_reminders.php
 * (runs once per hour)
 */
require_once __DIR__ . '/../../app/bootstrap.php';
use App\Mail\Mailer;

$isCli   = php_sapi_name() === 'cli';
$hasKey  = isset($_GET['key']) && hash_equals($_GET['key'], 'szv-cron-2026');
if (!$isCli && !$hasKey) { http_response_code(403); exit('Forbidden'); }

$db = \App\Models\Database::getInstance();

// Only these 5 reminder types have a matching EmailTemplate method —
// booking_reminders.type also allows 'custom', which has none.
$templateForType = [
    'deposit_due' => 'booking_reminder_deposit',
    'balance_due' => 'booking_reminder_balance',
    'departure'   => 'booking_reminder_departure',
    'post_trip'   => 'booking_reminder_posttrip',
    'win_back'    => 'booking_reminder_winback',
];

// Get reminders due in the next hour that haven't been sent
try {
    $due = $db->query(
        "SELECT r.*, b.email, b.full_name, b.travel_date, b.total_amount, b.deposit_amount, b.package_name, b.created_at AS booking_created_at
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
    $template = $templateForType[$r['type']] ?? null;
    if (!$template) {
        // Unknown/custom type — nothing to render; leave it pending rather
        // than silently dropping it, and move on.
        echo '[' . date('H:i:s') . '] Skipped reminder id ' . $r['id'] . ' — no template for type "' . $r['type'] . '"' . "\n";
        continue;
    }

    // Win-back specifically: skip it if this guest has already booked
    // again since — checked here at send time (not when it was
    // scheduled, 6 months earlier) since that's the only point where the
    // answer is actually current. This is what makes it a real win-back
    // flow rather than a blanket "come back" blast to everyone.
    if ($r['type'] === 'win_back') {
        $rebooked = $db->prepare("
            SELECT 1 FROM bookings
            WHERE email = ? AND booking_ref != ? AND payment_status != 'cancelled'
              AND created_at > ? LIMIT 1
        ");
        $rebooked->execute([$r['email'], $r['booking_ref'], $r['booking_created_at']]);
        if ($rebooked->fetchColumn()) {
            $db->prepare("UPDATE booking_reminders SET status='cancelled' WHERE id=?")->execute([$r['id']]);
            echo '[' . date('H:i:s') . '] Skipped win_back for ' . $r['booking_ref'] . ' — guest already rebooked' . "\n";
            continue;
        }
    }

    try {
        $queued = Mailer::queue($template, [
            'to_email'       => $r['email'],
            'to_name'        => $r['full_name'],
            'client_name'    => $r['full_name'],
            'booking_ref'    => $r['booking_ref'],
            'package_name'   => $r['package_name'],
            'travel_date'    => $r['travel_date'],
            'total_amount'   => (float)$r['total_amount'],
            'deposit_amount' => (float)$r['deposit_amount'],
            'balance_due'    => (float)$r['total_amount'] - (float)$r['deposit_amount'],
        ]);

        if (!$queued) {
            throw new \Exception('Mailer::queue returned false — check the recipient email address.');
        }

        $db->prepare("UPDATE booking_reminders SET status='sent', sent_at=NOW() WHERE id=?")
           ->execute([$r['id']]);

        echo '[' . date('H:i:s') . '] Queued reminder: ' . $r['type'] . ' for ' . $r['booking_ref'] . "\n";

    } catch (\Exception $e) {
        $db->prepare("UPDATE booking_reminders SET status='failed' WHERE id=?")->execute([$r['id']]);
        echo 'Failed: ' . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
