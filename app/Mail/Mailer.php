<?php
namespace App\Mail;

use App\Models\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Safari Zangu Ventures — Unified Mailer
 *
 * Queues emails via the email_queue table, then makes one immediate send
 * attempt in the same request — so the common case goes out in seconds,
 * not on the next 2-minute cron tick. api/cron/process_email_queue.php
 * still runs on schedule as the retry path for whatever that immediate
 * attempt didn't manage (a slow SMTP handshake, a transient network
 * blip) — it shares this exact same send logic via sendRow(), so success
 * or failure is handled identically either way.
 *
 * Usage:
 *   Mailer::queue('booking_confirmation', [
 *       'to_email'    => 'guest@email.com',
 *       'to_name'     => 'John Smith',
 *       'client_name' => 'John Smith',
 *       'booking_ref' => 'SZVABC123',
 *       'package_name'=> 'Classic Safari',
 *       'travel_date' => '2026-08-15',
 *       'total_amount'=> 3300.00,
 *       ...
 *   ]);
 *
 *   // Also notify admin:
 *   Mailer::queueAdmin('custom_request_admin', [...]);
 */
class Mailer
{
    /**
     * Queue an email using a named template.
     * Returns the email_queue id or false on failure.
     */
    /**
     * @param array<int,array{path:string,name:string}>|null $attachments
     *        Files to attach — e.g. from App\Services\PdfRenderer.
     */
    public static function queue(
        string $template,
        array  $vars,
        ?string $toEmail = null,
        ?string $toName  = null,
        ?array  $attachments = null
    ): int|false
    {
        try {
            $rendered = EmailTemplate::render($template, $vars);
            $email    = $toEmail ?? $vars['to_email'] ?? $vars['client_email'] ?? $vars['email'] ?? null;
            $name     = $toName  ?? $vars['to_name']  ?? $vars['client_name']  ?? $vars['full_name'] ?? '';

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error_log("[SZV Mailer] Invalid email for template {$template}: " . var_export($email, true));
                return false;
            }

            $db = Database::getInstance();
            $db->prepare("INSERT INTO email_queue (to_email, to_name, subject, body, template, attachments, status, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())")
               ->execute([$email, $name, $rendered['subject'], $rendered['html'], $template,
                          $attachments ? json_encode($attachments) : null]);

            $id = (int)$db->lastInsertId();

            // Best-effort immediate send — if this fails or times out for
            // any reason, the row is untouched (still 'pending', attempts
            // still 0) and the next cron tick picks it up exactly as
            // before. A slow guest-facing request is never held up by a
            // slow mail server: this is capped short and swallows its own
            // errors.
            self::attemptImmediateSend($id);

            return $id;

        } catch (\Exception $e) {
            error_log("[SZV Mailer] queue() failed for {$template}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue a pre-rendered HTML email — for the admin "resend" actions
     * (booking confirmation, quotation) that build their own HTML rather
     * than going through EmailTemplate::render(). Same queue-and-attempt-
     * immediate-send path as queue(), just skipping the template lookup,
     * so these get the same near-instant delivery instead of waiting on
     * the 2-minute cron or duplicating their own PHPMailer setup.
     *
     * @param array<int,array{path:string,name:string}>|null $attachments
     */
    public static function queueRaw(
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        ?array $attachments = null,
        string $templateLabel = 'manual',
        bool $isPlainText = false
    ): int|false
    {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('[SZV Mailer] queueRaw(): invalid email ' . var_export($toEmail, true));
            return false;
        }

        try {
            $db = Database::getInstance();
            $db->prepare("INSERT INTO email_queue (to_email, to_name, subject, body, is_plain_text, template, attachments, status, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())")
               ->execute([$toEmail, $toName, $subject, $body, $isPlainText ? 1 : 0, $templateLabel,
                          $attachments ? json_encode($attachments) : null]);

            $id = (int)$db->lastInsertId();
            self::attemptImmediateSend($id);
            return $id;

        } catch (\Exception $e) {
            error_log('[SZV Mailer] queueRaw() failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get this email out the door without making the guest's own request
     * wait for an SMTP round-trip. Prefers a detached background process
     * (api/cron/send_single_email.php via exec) — this server runs
     * mod_php, which has no fastcgi_finish_request(), and the
     * flush()-then-keep-working trick these endpoints used to rely on
     * doesn't actually detach the connection here (confirmed directly: a
     * request measured at ~9s end to end with it). A real child process
     * is what actually returns control to Apache immediately.
     *
     * Falls back to the previous inline synchronous send (5s cap) if
     * exec() is unavailable or disabled — slower, but never silently
     * drops the send.
     */
    private static function attemptImmediateSend(int $queueId): void
    {
        if (self::dispatchBackground($queueId)) {
            return;
        }

        try {
            $db  = Database::getInstance();
            $row = $db->query("SELECT * FROM email_queue WHERE id={$queueId} LIMIT 1")->fetch();
            if (!$row || $row['status'] !== 'pending') return;

            $db->prepare("UPDATE email_queue SET attempts=attempts+1 WHERE id=?")->execute([$queueId]);
            $row['attempts'] = (int)$row['attempts'] + 1;

            self::sendRow($row, 5); // 5s timeout — fast fail, cron retries
        } catch (\Throwable $e) {
            error_log('[SZV Mailer] Immediate send attempt failed for queue id ' . $queueId . ': ' . $e->getMessage());
        }
    }

    /**
     * Launches api/cron/send_single_email.php as a detached background
     * process. Returns true only once the process is genuinely dispatched
     * (never on a guess) — the queue id is the only input, cast to int,
     * so there's no user-controlled string anywhere near the shell
     * command. Returns false (never throws) if exec() isn't available,
     * so the caller can fall back to the synchronous path.
     */
    private static function dispatchBackground(int $queueId): bool
    {
        if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
            return false;
        }
        $php    = PHP_BINARY && is_executable(PHP_BINARY) ? PHP_BINARY : '/usr/bin/php';
        $script = escapeshellarg(__DIR__ . '/../../api/cron/send_single_email.php');
        $id     = (int)$queueId; // never interpolated as anything but an int
        $cmd    = escapeshellcmd($php) . ' ' . $script . ' ' . $id . ' > /dev/null 2>&1 &';
        try {
            exec($cmd);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The actual PHPMailer send + status update for one email_queue row.
     * Shared by the immediate-send path above and
     * api/cron/process_email_queue.php, so both handle success/failure
     * identically rather than carrying two copies of this logic.
     *
     * @param array $row An email_queue row (already has attempts incremented).
     * @param int   $timeoutSeconds SMTP timeout — short for the immediate
     *        inline attempt, longer for the cron's own dedicated run.
     */
    public static function sendRow(array $row, int $timeoutSeconds = 20): bool
    {
        $db   = Database::getInstance();
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port    = SMTP_PORT;
            $mail->Timeout = $timeoutSeconds;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            if ($row['to_name']) {
                $mail->addAddress($row['to_email'], $row['to_name']);
            } else {
                $mail->addAddress($row['to_email']);
            }
            // No blanket admin CC — every transactional email (reminders,
            // invoices, acceptance notices) used to copy ADMIN_EMAIL
            // unconditionally, which doesn't scale past a handful of
            // bookings a month. Internal awareness of what actually needs
            // attention (new lead, new booking, new request, unread
            // message) now runs through the admin panel's notification
            // bell (layouts/admin.php) instead. Emails that are genuinely
            // addressed to admin (custom_request_admin, review notification,
            // etc.) already go there directly via Mailer::queueAdmin(), so
            // nothing admin-relevant is lost.
            $mail->Subject = $row['subject'];

            if (!empty($row['is_plain_text'])) {
                $mail->isHTML(false);
                $mail->Body = $row['body'];
            } else {
                $mail->isHTML(true);
                $mail->Body    = $row['body'];
                $mail->AltBody = strip_tags($row['body']);
            }

            if (!empty($row['attachments'])) {
                $files = json_decode($row['attachments'], true) ?: [];
                foreach ($files as $file) {
                    if (!empty($file['path']) && is_file($file['path'])) {
                        $mail->addAttachment($file['path'], $file['name'] ?? basename($file['path']));
                    } else {
                        error_log('[SZV Mailer] Missing attachment for email_queue id ' . $row['id'] . ': ' . ($file['path'] ?? '?'));
                    }
                }
            }

            $mail->send();

            $db->prepare("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=?")
               ->execute([$row['id']]);
            return true;

        } catch (PHPMailerException $e) {
            $err = $e->getMessage();
            $db->prepare("UPDATE email_queue SET status=IF(attempts>=3,'failed',status), last_error=? WHERE id=?")
               ->execute([$err, $row['id']]);
            return false;
        }
    }

    /**
     * Queue an email to the admin using a named template.
     */
    public static function queueAdmin(string $template, array $vars): int|false
    {
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@safarizanguven.com';
        $adminName  = 'Safari Zangu Admin';
        return self::queue($template, $vars, $adminEmail, $adminName);
    }

    /**
     * Queue emails to both guest and admin in one call.
     * $guestTemplate  — sent to the client
     * $adminTemplate  — sent to admin (or null to skip)
     */
    public static function queueBoth(
        string  $guestTemplate,
        array   $guestVars,
        ?string $adminTemplate = null,
        ?array  $adminVars     = null
    ): array
    {
        $guestId = self::queue($guestTemplate, $guestVars);
        $adminId = $adminTemplate
            ? self::queueAdmin($adminTemplate, $adminVars ?? $guestVars)
            : null;
        return ['guest' => $guestId, 'admin' => $adminId];
    }
}
