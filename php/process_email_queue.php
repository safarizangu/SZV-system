<?php
/**
 * Email Queue Processor
 * Run via cron every 2 minutes:
 * crontab -e
 * Cron schedule: every 2 minutes
 */
require_once __DIR__ . '/../app/bootstrap.php';

// Only run from CLI or with a secret key
$isCli    = php_sapi_name() === 'cli';
$hasKey   = isset($_GET['key']) && hash_equals($_GET['key'], defined('CRON_SECRET') ? CRON_SECRET : 'szv-cron-2026');
if (!$isCli && !$hasKey) {
    http_response_code(403); exit('Forbidden');
}

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

$db     = \App\Models\Database::getInstance();
$maxPer = 20; // max emails per run
$sent   = 0;
$failed = 0;

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

foreach ($rows as $row) {
    // Mark as in-progress
    try {
        $db->prepare("UPDATE email_queue SET attempts=attempts+1 WHERE id=?")
           ->execute([$row['id']]);
    } catch (\Exception $e) { continue; }

    // Send
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        if ($row['to_name']) {
            $mail->addAddress($row['to_email'], $row['to_name']);
        } else {
            $mail->addAddress($row['to_email']);
        }
        $mail->addCC(ADMIN_EMAIL, 'Safari Zangu Admin');
        $mail->Subject = $row['subject'];
        $mail->isHTML(true);
        $mail->Body    = $row['body'];
        $mail->AltBody = strip_tags($row['body']);
        $mail->send();

        $db->prepare("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=?")
           ->execute([$row['id']]);
        $sent++;
        echo '[' . date('Y-m-d H:i:s') . '] SENT: ' . $row['to_email'] . ' — ' . $row['subject'] . "\n";

    } catch (MailerException $e) {
        $err = $e->getMessage();
        $db->prepare("UPDATE email_queue SET status='failed', last_error=? WHERE id=? AND attempts >= 3")
           ->execute([$err, $row['id']]);
        $db->prepare("UPDATE email_queue SET last_error=? WHERE id=? AND attempts < 3")
           ->execute([$err, $row['id']]);
        $failed++;
        echo '[' . date('Y-m-d H:i:s') . '] FAILED: ' . $row['to_email'] . ' — ' . $err . "\n";
    }
}

echo '[' . date('Y-m-d H:i:s') . "] Done. Sent: {$sent} Failed: {$failed}\n";
