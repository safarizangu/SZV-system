<?php
/**
 * Reminder Processor
 * Cron (Hostinger Control Panel → Cron Jobs): run hourly —
 *   0 * * * * php8.3 /home/u662187129/domains/safarizanguven.com/public_html/api/cron/process_reminders.php
 */
require_once __DIR__ . '/../../app/bootstrap.php';
use App\Mail\Mailer;
use App\Helpers\AppLog;

// DEVOPS-2: this used to check against a hardcoded literal ('szv-cron-2026')
// instead of the CRON_SECRET every other cron/*.php script reads from
// .env — not configurable per-environment, and a fixed secret baked into
// source control. Brought in line with the others.
$isCli      = php_sapi_name() === 'cli';
$cronSecret = defined('CRON_SECRET') ? CRON_SECRET : '';
$hasKey     = $cronSecret !== '' && isset($_GET['key']) && hash_equals($cronSecret, $_GET['key']);
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
        "SELECT r.*, b.email, b.full_name, b.travel_date, b.total_amount, b.deposit_amount, b.package_name, b.quotation_id, b.created_at AS booking_created_at
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
        $line = 'Skipped reminder id ' . $r['id'] . ' — no template for type "' . $r['type'] . '"';
        echo '[' . date('H:i:s') . '] ' . $line . "\n";
        AppLog::write('reminders', $line);
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
            $line = 'Skipped win_back for ' . $r['booking_ref'] . ' — guest already rebooked';
            echo '[' . date('H:i:s') . '] ' . $line . "\n";
            AppLog::write('reminders', $line);
            continue;
        }
    }

    try {
        // Departure (T-7 days) is the one reminder where re-attaching a
        // document makes sense — the standard "here are your final travel
        // documents" moment. The other four are payment/post-trip notices
        // with nothing new to attach.
        $attachments = null;
        if ($r['type'] === 'departure') {
            $itinFile = !empty($r['quotation_id'])
                ? \App\Services\PdfRenderer::renderQuotationItinerary((int)$r['quotation_id'], 'Itinerary-' . $r['booking_ref'])
                : \App\Services\PdfRenderer::renderBookingItinerary($r['booking_ref'], 'Itinerary-' . $r['booking_ref']);
            if ($itinFile) $attachments = [$itinFile];
        }

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
        ], null, null, $attachments);

        if (!$queued) {
            throw new \Exception('Mailer::queue returned false — check the recipient email address.');
        }

        $db->prepare("UPDATE booking_reminders SET status='sent', sent_at=NOW() WHERE id=?")
           ->execute([$r['id']]);

        $line = 'Queued reminder: ' . $r['type'] . ' for ' . $r['booking_ref'];
        echo '[' . date('H:i:s') . '] ' . $line . "\n";
        AppLog::write('reminders', $line);

    } catch (\Exception $e) {
        $db->prepare("UPDATE booking_reminders SET status='failed' WHERE id=?")->execute([$r['id']]);
        echo 'Failed: ' . $e->getMessage() . "\n";
        AppLog::write('reminders', 'Failed: ' . $e->getMessage());
    }
}

echo "Done.\n";
AppLog::write('reminders', 'Done.');
