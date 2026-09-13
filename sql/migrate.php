<?php
/**
 * Minimal migration runner — answers "has this environment run migration
 * X" with one query instead of a manual SHOW CREATE TABLE diff, which is
 * what every schema change up to this point required.
 *
 * Usage: php sql/migrate.php
 *
 * Runs every sql/*.sql file not yet recorded in schema_migrations, in
 * filename order, inside its own transaction where possible. Not a
 * framework — no down-migrations, no dependency graph — just a record of
 * what's been applied where, which this project had none of before.
 */
require_once __DIR__ . '/../app/bootstrap.php';

$pdo = \App\Models\Database::getInstance();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        filename    VARCHAR(255) NOT NULL UNIQUE,
        applied_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$applied = $pdo->query("SELECT filename FROM schema_migrations")->fetchAll(\PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$files = glob(__DIR__ . '/*.sql');
sort($files);

foreach ($files as $path) {
    $name = basename($path);
    if ($name === basename(__FILE__)) continue;
    if (isset($applied[$name])) continue;

    echo "Running $name ... ";
    $sql = file_get_contents($path);
    try {
        // Split on statement-terminating semicolons; adequate for this
        // project's migrations (no stored procedures / triggers with
        // embedded semicolons).
        $statements = array_filter(array_map('trim', explode(";\n", $sql)));
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) continue;
            $pdo->exec($stmt);
        }
        $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)")->execute([$name]);
        echo "OK\n";
    } catch (\Throwable $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        echo "Stopping — fix this migration before continuing.\n";
        exit(1);
    }
}

echo "Done.\n";
