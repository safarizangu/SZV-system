<?php
/**
 * Minimal dependency-free test runner.
 * No PHPUnit — keeps `php tests/run.php` working with nothing but the PHP CLI.
 * Usage: php tests/run.php
 */

require_once __DIR__ . '/../app/bootstrap.php';

$passed = 0;
$failed = 0;
$failures = [];

/** Register and immediately run one assertion. */
function test(string $name, callable $fn): void
{
    global $passed, $failed, $failures;
    try {
        $fn();
        $passed++;
        echo "  \033[32m✓\033[0m $name\n";
    } catch (\Throwable $e) {
        $failed++;
        $failures[] = "$name: {$e->getMessage()}";
        echo "  \033[31m✗\033[0m $name — {$e->getMessage()}\n";
    }
}

function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        $e = var_export($expected, true);
        $a = var_export($actual, true);
        throw new \RuntimeException($msg ?: "Expected $e, got $a");
    }
}

function assertTrue(bool $cond, string $msg = 'Expected true'): void
{
    if (!$cond) throw new \RuntimeException($msg);
}

function assertFalse(bool $cond, string $msg = 'Expected false'): void
{
    if ($cond) throw new \RuntimeException($msg);
}

$suites = glob(__DIR__ . '/suites/*.php');
sort($suites);
foreach ($suites as $suite) {
    echo basename($suite) . "\n";
    require $suite;
    echo "\n";
}

echo "----------------------------------------\n";
echo "$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
