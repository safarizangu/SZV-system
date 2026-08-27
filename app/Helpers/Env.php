<?php
namespace App\Helpers;

/**
 * Simple .env file loader.
 * Call Env::load() once in bootstrap before config/app.php.
 */
class Env
{
    private static bool $loaded = false;

    public static function load(string $path = null): void
    {
        if (self::$loaded) return;
        $file = $path ?? dirname(__DIR__, 2) . '/.env';
        if (!file_exists($file)) return;

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, '"\'');
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}
