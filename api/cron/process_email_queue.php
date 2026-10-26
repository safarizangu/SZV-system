<?php
/**
 * Email Queue Processor — the retry/fallback path.
 * Run via cron every 2 minutes:
 * crontab -e
 * Cron schedule: every 2 minutes
 *
 * Most emails no longer wait for this: App\Mail\Mailer::queue() makes an
 * immediate send attempt in the same request that creates them. This
 * script exists to catch whatever that attempt didn't manage — a slow
 * SMTP handshake, a transient network blip, or a request that errored
 * out before the immediate attempt ran — using the exact same send logic
 * (Mailer::sendRow()) so success/failure is handled identically either way.
 */
require_once __DIR__ . '/../../app/bootstrap.php';

// Only run from CLI or with a secret key
$isCli    = php_sapi_name() === 'cli';
$cronSecret = defined('CRON_SECRET') ? CRON_SECRET : '';
$hasKey   = $cronSecret !== '' && isset($_GET['key']) && hash_equals($cronSecret, $_GET['key']);
if (!$isCli && !$hasKey) {
    http_response_code(403); exit('Forbidden');
}

use App\Mail\Mailer;
use App\Helpers\AppLog;

$db     = \App\Models\Database::getInstance();
$maxPer = 20; // max emails per run

// Get pending emails (max 3 attempts)
try {
    $rows = $db->query(
        "SELECT * FROM email_queue
         WHERE status='pending' AND attempts < 3
         ORDER BY created_at ASC LIMIT {$maxPer}"
    )->fetchAll();
} catch (\Exception $e) {
    echo '[' . date('Y-m-d H:i:s') . '] DB error: ' . $e->getMessage() . "\n";
    exit(1);
}

$sent = 0;
$failed = 0;

foreach ($rows as $row) {
    try {
        $db->prepare("UPDATE email_queue SET attempts=attempts+1 WHERE id=?")
           ->execute([$row['id']]);
    } catch (\Exception $e) { continue; }
    $row['attempts'] = (int)$row['attempts'] + 1;

    if (Mailer::sendRow($row, 20)) {
        $sent++;
        $line = 'SENT: ' . $row['to_email'] . ' — ' . $row['subject'];
        echo '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n";
        AppLog::write('email', $line);
    } else {
        $failed++;
        $line = 'FAILED: ' . $row['to_email'];
        echo '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n";
        AppLog::write('email', $line);
    }
}

echo '[' . date('Y-m-d H:i:s') . "] Done. Sent: {$sent} Failed: {$failed}\n";
AppLog::write('email', "Done. Sent: {$sent} Failed: {$failed}");
