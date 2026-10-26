<?php
/**
 * Activity Log + Email Queue Retention — DB-3 remediation.
 * Cron (Hostinger Control Panel → Cron Jobs): run weekly —
 *   0 4 * * 0 php8.3 <public_html>/api/cron/prune_old_records.php
 *
 * activity_log had no retention at all — rows accumulated forever.
 * Archives rows older than the retention window into
 * activity_log_archive (same pattern as api/cron/prune_analytics_events.php
 * — an audit trail is exactly the kind of data that shouldn't just be
 * silently destroyed) before removing them from the live table. 365 days
 * is generous headroom over anything an admin would realistically need
 * to look back on by hand.
 *
 * email_queue's only cleanup was admin/email_queue.php's "Purge sent >30
 * days" button — a real action, but one that only ran when a human
 * remembered to click it. Runs that same exact query here, automatically.
 * Not archived: it's a transient send-status table, not an audit record,
 * and the manual button never archived either.
 */
require_once __DIR__ . '/../../app/bootstrap.php';

$isCli      = php_sapi_name() === 'cli';
$cronSecret = defined('CRON_SECRET') ? CRON_SECRET : '';
$hasKey     = $cronSecret !== '' && isset($_GET['key']) && hash_equals($cronSecret, $_GET['key']);
if (!$isCli && !$hasKey) { http_response_code(403); exit('Forbidden'); }

$db = \App\Models\Database::getInstance();
$retentionDays = defined('ACTIVITY_LOG_RETENTION_DAYS') ? (int)ACTIVITY_LOG_RETENTION_DAYS : 365;

$messages = [];

try {
    $db->exec("CREATE TABLE IF NOT EXISTS activity_log_archive LIKE activity_log");

    $cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

    $countStmt = $db->prepare("SELECT COUNT(*) FROM activity_log WHERE created_at < ?");
    $countStmt->execute([$cutoff]);
    $toArchive = (int)$countStmt->fetchColumn();

    if ($toArchive > 0) {
        $db->beginTransaction();
        $db->prepare("
            INSERT INTO activity_log_archive
                (id, actor_id, actor_name, action, entity_type, entity_id, details, created_at)
            SELECT id, actor_id, actor_name, action, entity_type, entity_id, details, created_at
            FROM activity_log WHERE created_at < ?
        ")->execute([$cutoff]);
        $db->prepare("DELETE FROM activity_log WHERE created_at < ?")->execute([$cutoff]);
        $db->commit();
    }

    $messages[] = "archived {$toArchive} activity_log row(s) older than {$retentionDays} days (cutoff {$cutoff})";
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[prune_old_records] activity_log: ' . $e->getMessage());
    $messages[] = 'activity_log: ERROR — see error log';
}

try {
    // Same query admin/email_queue.php's "Purge sent" button already runs by hand.
    $purged = $db->exec("DELETE FROM email_queue WHERE status='sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $messages[] = "purged {$purged} sent email_queue row(s) older than 30 days";
} catch (\Throwable $e) {
    error_log('[prune_old_records] email_queue: ' . $e->getMessage());
    $messages[] = 'email_queue: ERROR — see error log';
}

$msg = '[prune_old_records] ' . implode('; ', $messages);
error_log($msg);
if (!$isCli) { header('Content-Type: text/plain'); echo $msg; }
