<?php
/**
 * Quotation Expiry Processor
 * Cron: 0 3 * * * /usr/bin/php /var/www/html/safari-zangu/api/cron/process_quotation_expiry.php
 * (runs once a day)
 *
 * 'expired' has been a selectable quotations.status value — with a real
 * quotation_expired email template already built (app/Mail/EmailTemplate.php)
 * — since before this script existed, but nothing ever actually set it: a
 * 'sent' quotation whose valid_until date passed just sat there, still
 * marked 'sent', until an admin noticed and changed it by hand (or never
 * did). This finishes that half-built feature: a 'sent' quotation past its
 * own valid_until is moved to 'expired' and the client gets the existing
 * re-enquiry email, once per quotation, here rather than anywhere else.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
use App\Mail\Mailer;

$isCli      = php_sapi_name() === 'cli';
$cronSecret = defined('CRON_SECRET') ? CRON_SECRET : '';
$hasKey     = $cronSecret !== '' && isset($_GET['key']) && hash_equals($cronSecret, $_GET['key']);
if (!$isCli && !$hasKey) { http_response_code(403); exit('Forbidden'); }

$db = \App\Models\Database::getInstance();

try {
    $due = $db->query("
        SELECT id, quote_ref, client_name, client_email
        FROM quotations
        WHERE status = 'sent' AND valid_until IS NOT NULL AND valid_until < CURDATE()
        ORDER BY valid_until ASC LIMIT 100
    ")->fetchAll();
} catch (\Exception $e) {
    error_log('[process_quotation_expiry] ' . $e->getMessage());
    echo "DB error — see error log.\n"; exit(1);
}

foreach ($due as $q) {
    try {
        $db->prepare("UPDATE quotations SET status='expired', updated_at=NOW() WHERE id=? AND status='sent'")
           ->execute([$q['id']]);

        $queued = Mailer::queue('quotation_expired', [
            'to_email'    => $q['client_email'],
            'to_name'     => $q['client_name'],
            'client_name' => $q['client_name'],
            'quote_ref'   => $q['quote_ref'],
        ]);

        echo '[' . date('H:i:s') . '] Expired ' . $q['quote_ref']
            . ($queued ? ' — email queued' : ' — email NOT queued (invalid address?)') . "\n";
    } catch (\Exception $e) {
        error_log('[process_quotation_expiry] quote ' . $q['quote_ref'] . ': ' . $e->getMessage());
        echo 'Failed: ' . $q['quote_ref'] . ' — ' . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
