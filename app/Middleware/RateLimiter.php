<?php
namespace App\Middleware;

/**
 * Simple file-based rate limiter.
 * No Redis required — works on shared hosting.
 */
class RateLimiter
{
    private static string $storageDir = __DIR__ . '/../../storage/cache/';

    /**
     * Check if IP is rate-limited for a given action.
     * @param string $action  e.g. 'booking', 'contact', 'custom_safari'
     * @param int    $maxHits max hits allowed
     * @param int    $window  time window in seconds
     */
    public static function check(string $action, int $maxHits = 5, int $window = 3600): bool
    {
        $ip   = self::getIp();
        $key  = md5($action . '_' . $ip);
        $file = self::$storageDir . $key . '.json';

        $data = ['hits' => [], 'ip' => $ip];
        if (file_exists($file)) {
            $raw  = file_get_contents($file);
            $data = json_decode($raw, true) ?? $data;
        }

        $now  = time();
        $data['hits'] = array_filter($data['hits'], fn($t) => ($now - $t) < $window);

        if (count($data['hits']) >= $maxHits) {
            return false; // rate limited
        }

        $data['hits'][] = $now;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return true; // allowed
    }

    public static function enforceOrDie(string $action, int $max = 5, int $window = 3600): void
    {
        if (!self::check($action, $max, $window)) {
            http_response_code(429);
            die(json_encode([
                'success' => false,
                'message' => 'Too many requests. Please wait a moment and try again.',
            ]));
        }
    }

    private static function getIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}
