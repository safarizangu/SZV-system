<?php
/**
 * One-off data-repair script — strips a leaked "/safari-zangu" prefix
 * (this local dev environment's BASE_PATH) out of stored image URLs.
 *
 * Root cause: api/gallery/list.php, api/gallery/upload.php, and
 * api/set_hero.php used to bake BASE_PATH into the URL they returned/
 * stored, so any image picked or hero image set while running locally
 * got a "/safari-zangu/..." path saved into packages.gallery_images or
 * settings.hero_image. That path 404s wherever BASE_PATH differs —
 * production, most importantly. All three endpoints are now fixed to
 * store root-relative paths only; this script cleans up rows that were
 * already saved before that fix.
 *
 * Safe to run more than once (no-ops once nothing matches). Safe to
 * run against the production database too — it only touches values
 * that literally start with "/safari-zangu".
 *
 * Usage: php sql/fix_basepath_leaked_urls.php
 */
require_once __DIR__ . '/../app/bootstrap.php';

$db = \App\Models\Database::getInstance();
$prefix = '/safari-zangu';

function stripPrefix($value, string $prefix) {
    if (is_string($value) && str_starts_with($value, $prefix)) {
        return substr($value, strlen($prefix));
    }
    return $value;
}

$totalChanged = 0;

// ── packages.gallery_images (JSON array of URLs) ────────────────
$rows = $db->query("SELECT id, gallery_images FROM packages WHERE gallery_images LIKE '%/safari-zangu/%'")->fetchAll();
foreach ($rows as $row) {
    $arr = json_decode($row['gallery_images'] ?? '[]', true) ?: [];
    $fixed = array_map(fn($u) => stripPrefix($u, $prefix), $arr);
    $db->prepare("UPDATE packages SET gallery_images=? WHERE id=?")
       ->execute([json_encode($fixed), $row['id']]);
    echo "packages.gallery_images fixed for package id={$row['id']}\n";
    $totalChanged++;
}

// ── settings.value (single scalar, keyed 'hero_image' etc.) ─────
$rows = $db->query("SELECT `key`, `value` FROM settings WHERE `value` LIKE '%/safari-zangu/%'")->fetchAll();
foreach ($rows as $row) {
    $fixed = stripPrefix($row['value'], $prefix);
    $db->prepare("UPDATE settings SET `value`=? WHERE `key`=?")
       ->execute([$fixed, $row['key']]);
    echo "settings.{$row['key']} fixed: {$row['value']} -> {$fixed}\n";
    $totalChanged++;
}

echo "\nDone — {$totalChanged} row(s) updated.\n";
