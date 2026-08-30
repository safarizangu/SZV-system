<?php
namespace App\Middleware;

/**
 * Server-side file-based rate limiter.
 * Uses REMOTE_ADDR only (no X-Forwarded-For spoofing).
 * Stores state in /tmp (outside webroot).
 */
class RateLimiter
{
    // Store outside webroot — not accessible via HTTP
    private static string $storageDir = '/tmp/szv_ratelimit/';

    public static function check(string $action, int $maxHits = 5, int $window = 3600): bool
    {
        self::ensureDir();
        // NEVER trust X-Forwarded-For — use only real server-provided IP
        $ip   = self::getRealIp();
        $key  = hash('sha256', $action . '|' . $ip);
        $file = self::$storageDir . $key . '.json';

        $now  = time();
        $data = ['hits' => []];

        if (file_exists($file)) {
            $raw  = file_get_contents($file);
            $data = json_decode($raw, true) ?? $data;
        }

        // Prune old hits outside window
        $data['hits'] = array_values(array_filter(
            $data['hits'] ?? [],
            fn($t) => ($now - $t) < $window
        ));

        if (count($data['hits']) >= $maxHits) {
            return false; // rate limited
        }

        $data['hits'][] = $now;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }

    public static function enforceOrDie(string $action, int $max = 5, int $window = 3600): void
    {
        if (!self::check($action, $max, $window)) {
            http_response_code(429);
            // Drain output buffer before dying
            if (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Too many requests. Please wait a moment and try again.',
            ]);
            exit;
        }
    }

    public static function clearForIp(string $action): void
    {
        $ip   = self::getRealIp();
        $key  = hash('sha256', $action . '|' . $ip);
        $file = self::$storageDir . $key . '.json';
        if (file_exists($file)) unlink($file);
    }

    /**
     * Returns only REMOTE_ADDR — never X-Forwarded-For.
     * X-Forwarded-For is client-controlled and trivially spoofed.
     */
    private static function getRealIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Validate it's actually an IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        return '0.0.0.0';
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0700, true);
        }
    }
}
