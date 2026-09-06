<?php
namespace App\Mail;

use App\Models\Database;

/**
 * Safari Zangu Ventures — Unified Mailer
 *
 * Queues emails for sending via the email_queue table.
 * Processed by php/process_email_queue.php (cron every 2 min).
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

            return (int)$db->lastInsertId();

        } catch (\Exception $e) {
            error_log("[SZV Mailer] queue() failed for {$template}: " . $e->getMessage());
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
