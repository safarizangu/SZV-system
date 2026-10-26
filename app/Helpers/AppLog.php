<?php
namespace App\Helpers;

/**
 * DEVOPS-2: admin/email_queue.php and admin/reminders.php each have a
 * "Live Log" panel reading /var/log/szv_email.log / szv_reminders.log —
 * paths that require root to create and that nothing in the codebase
 * ever wrote to, so those panels were permanently empty. Writes instead
 * to storage/logs/ (already exists, already denied by storage/.htaccess,
 * already writable by the web server — just unused until now).
 */
class AppLog
{
    public static function write(string $channel, string $line): void
    {
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $safeChannel = preg_replace('/[^a-z0-9_-]/i', '', $channel) ?: 'app';
        @file_put_contents(
            $dir . '/' . $safeChannel . '.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
