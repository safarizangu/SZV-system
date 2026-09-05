<?php
/**
 * One-time backfill: populate the new `customers` table from existing
 * bookings, quotations, leads, and custom_safari_requests rows, and
 * set each source row's new customer_id.
 *
 * Run once, after sql/2026_09_customers_table.sql:
 *   php database/migrations/2026_09_backfill_customers.php
 *
 * Idempotent — safe to re-run. Rows that already have a customer_id
 * are skipped; App\Models\Customer::findOrCreateByEmail() re-uses an
 * existing customers row for an email it's already seen.
 *
 * Processes every source row newest-first (globally, across all 4
 * tables) so that when an email is seen for the first time in this
 * run, the customer record is created using that person's most
 * recent full_name/phone/country — typically the most accurate and
 * complete version of their details — while older rows for the same
 * email only fill in gaps (see Customer::findOrCreateByEmail), never
 * overwrite it.
 */
require_once __DIR__ . '/../../app/bootstrap.php';

use App\Models\Database;
use App\Models\Customer;

$pdo      = Database::getInstance();
$customer = new Customer();

$sources = [
    'bookings' => [
        'select' => "SELECT id, full_name, email, phone, NULL AS country, created_at FROM bookings WHERE customer_id IS NULL",
        'update' => "UPDATE bookings SET customer_id = ? WHERE id = ?",
    ],
    'quotations' => [
        'select' => "SELECT id, client_name AS full_name, client_email AS email, client_phone AS phone, client_country AS country, created_at FROM quotations WHERE customer_id IS NULL",
        'update' => "UPDATE quotations SET customer_id = ? WHERE id = ?",
    ],
    'leads' => [
        'select' => "SELECT id, full_name, email, phone, country, created_at FROM leads WHERE customer_id IS NULL",
        'update' => "UPDATE leads SET customer_id = ? WHERE id = ?",
    ],
    'custom_safari_requests' => [
        'select' => "SELECT id, full_name, email, phone, country, created_at FROM custom_safari_requests WHERE customer_id IS NULL",
        'update' => "UPDATE custom_safari_requests SET customer_id = ? WHERE id = ?",
    ],
];

// Gather every source row into one list, tagged with which table/update
// statement it came from, so they can be sorted globally by created_at.
$rows = [];
foreach ($sources as $table => $cfg) {
    foreach ($pdo->query($cfg['select'])->fetchAll() as $row) {
        $row['_table']  = $table;
        $row['_update'] = $cfg['update'];
        $rows[] = $row;
    }
}

usort($rows, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? '')); // newest first

$linked  = 0;
$skipped = 0;

foreach ($rows as $row) {
    if (empty($row['email'])) {
        $skipped++;
        continue;
    }

    $customerId = $customer->findOrCreateByEmail(
        $row['email'],
        $row['full_name'] ?? 'Guest',
        $row['phone']   ?? null,
        $row['country'] ?? null
    );

    if ($customerId === null) {
        $skipped++;
        continue;
    }

    $pdo->prepare($row['_update'])->execute([$customerId, $row['id']]);
    $linked++;
}

$totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();

echo "Linked {$linked} row(s) across bookings/quotations/leads/custom_safari_requests.\n";
echo "Skipped {$skipped} row(s) with no valid email.\n";
echo "customers table now has {$totalCustomers} row(s).\n";
