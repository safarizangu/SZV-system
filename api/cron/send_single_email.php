<?php
/**
 * Background single-email sender — dispatched via exec() from
 * App\Mail\Mailer::queue() so a guest's HTTP request can return
 * immediately instead of waiting on an SMTP round-trip. Not meant to be
 * hit directly: CLI-only (or with the cron secret, matching
 * process_email_queue.php's own guard), one email_queue id per run.
 *
 * Usage: php send_single_email.php <queue_id>
 */
require_once __DIR__ . '/../../app/bootstrap.php';

$isCli      = php_sapi_name() === 'cli';
$cronSecret = defined('CRON_SECRET') ? CRON_SECRET : '';
$hasKey     = $cronSecret !== '' && isset($_GET['key']) && hash_equals($cronSecret, $_GET['key']);
if (!$isCli && !$hasKey) {
    http_response_code(403); exit('Forbidden');
}

$queueId = (int)($argv[1] ?? $_GET['id'] ?? 0);
if ($queueId <= 0) {
    fwrite(STDERR, "Usage: php send_single_email.php <queue_id>\n");
    exit(1);
}

use App\Mail\Mailer;

$db  = \App\Models\Database::getInstance();
$row = $db->query("SELECT * FROM email_queue WHERE id={$queueId} LIMIT 1")->fetch();

if (!$row || $row['status'] !== 'pending') {
    // Already sent (or failed permanently) — nothing to do. Not an
    // error: the synchronous fallback in Mailer may have already handled
    // it, or a previous dispatch of this same id already ran.
    exit(0);
}

$db->prepare("UPDATE email_queue SET attempts=attempts+1 WHERE id=?")->execute([$queueId]);
$row['attempts'] = (int)$row['attempts'] + 1;

Mailer::sendRow($row, 20);
