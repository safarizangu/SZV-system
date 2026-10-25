<?php
/**
 * Analytics Events Retention — DB-2 remediation.
 * Cron: 0 4 * * 0 /usr/bin/php /var/www/html/safari-zangu/api/cron/prune_analytics_events.php
 * (runs once a week)
 *
 * analytics_events previously had no retention policy at all — raw
 * page-view/click events accumulated forever with nothing pruning them.
 * Harmless at this site's current volume (~1,200 rows total at the time
 * this was written) but a genuine unbounded-growth risk long-term, and
 * one no other part of the codebase was addressing.
 *
 * Archives rows older than the retention window into
 * analytics_events_archive (same shape, kept — not a bare delete, in
 * case historical analysis is ever wanted) before removing them from the
 * live table. 365 days is deliberately generous: this table backs
 * admin/analytics.php's trend widgets, which only ever look at the last
 * 7/30/90 days, so a year of headroom costs nothing in query performance
 * while comfortably covering any longer look-back an admin might want to
 * do by hand before this ever runs.
 */
require_once __DIR__ . '/../../app/bootstrap.php';

$isCli      = php_sapi_name() === 'cli';
$cronSecret = defined('CRON_SECRET') ? CRON_SECRET : '';
$hasKey     = $cronSecret !== '' && isset($_GET['key']) && hash_equals($cronSecret, $_GET['key']);
if (!$isCli && !$hasKey) { http_response_code(403); exit('Forbidden'); }

$db = \App\Models\Database::getInstance();
$retentionDays = defined('ANALYTICS_RETENTION_DAYS') ? (int)ANALYTICS_RETENTION_DAYS : 365;

try {
    $db->exec("CREATE TABLE IF NOT EXISTS analytics_events_archive LIKE analytics_events");

    $cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

    $countStmt = $db->prepare("SELECT COUNT(*) FROM analytics_events WHERE created_at < ?");
    $countStmt->execute([$cutoff]);
    $toArchive = (int)$countStmt->fetchColumn();

    if ($toArchive > 0) {
        $db->beginTransaction();
        $db->prepare("
            INSERT INTO analytics_events_archive
                (id, event_name, page_url, package_slug, ip_address, user_agent, created_at, referrer)
            SELECT id, event_name, page_url, package_slug, ip_address, user_agent, created_at, referrer
            FROM analytics_events WHERE created_at < ?
        ")->execute([$cutoff]);
        $db->prepare("DELETE FROM analytics_events WHERE created_at < ?")->execute([$cutoff]);
        $db->commit();
    }

    $msg = "[prune_analytics_events] archived {$toArchive} rows older than {$retentionDays} days (cutoff {$cutoff})";
    error_log($msg);
    if (!$isCli) { header('Content-Type: text/plain'); echo $msg; }
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[prune_analytics_events] ' . $e->getMessage());
    if (!$isCli) { http_response_code(500); echo 'Error — see error log.'; }
}
