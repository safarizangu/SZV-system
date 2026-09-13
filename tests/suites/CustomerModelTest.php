<?php
// App\Models\Customer — the dedup point shared by bookings, quotations,
// leads and custom safari requests. Previously untested despite being on
// every one of those write paths. Runs against the real local database,
// cleaning up its own row.

use App\Models\Customer;
use App\Models\Database;

function cleanupCustomerModelTestRows(): void
{
    Database::getInstance()
        ->prepare("DELETE FROM customers WHERE email = ?")
        ->execute(['test-suite-customer@example.invalid']);
}

test('findOrCreateByEmail() creates a new customer on first contact', function () {
    cleanupCustomerModelTestRows();
    $model = new Customer();
    $id    = $model->findOrCreateByEmail('Test-Suite-Customer@Example.INVALID', 'Test Customer', '+255700000001', 'Kenya');

    assertTrue($id !== null && $id > 0);

    $row = Database::getInstance()
        ->query("SELECT * FROM customers WHERE id = " . (int)$id)
        ->fetch();
    // Email is normalized to lowercase/trimmed regardless of input casing.
    assertSame('test-suite-customer@example.invalid', $row['email']);
    assertSame('Test Customer', $row['full_name']);
    assertSame('Kenya', $row['country']);

    cleanupCustomerModelTestRows();
});

test('findOrCreateByEmail() returns the same id for a repeat contact', function () {
    cleanupCustomerModelTestRows();
    $model = new Customer();
    $id1   = $model->findOrCreateByEmail('test-suite-customer@example.invalid', 'Test Customer', '+255700000001');
    $id2   = $model->findOrCreateByEmail('test-suite-customer@example.invalid', 'Test Customer', '+255700000001');

    assertSame($id1, $id2);

    cleanupCustomerModelTestRows();
});

test('findOrCreateByEmail() fills in a missing phone but never overwrites an existing one', function () {
    cleanupCustomerModelTestRows();
    $model = new Customer();
    // First contact: no phone given.
    $id = $model->findOrCreateByEmail('test-suite-customer@example.invalid', 'Test Customer');
    // Second contact: phone now provided — should be filled in.
    $model->findOrCreateByEmail('test-suite-customer@example.invalid', 'Test Customer', '+255700000002');
    $row = Database::getInstance()->query("SELECT phone FROM customers WHERE id = " . (int)$id)->fetch();
    assertSame('+255700000002', $row['phone']);

    // Third contact: a different phone — must NOT overwrite the one already on file.
    $model->findOrCreateByEmail('test-suite-customer@example.invalid', 'Test Customer', '+255799999999');
    $row = Database::getInstance()->query("SELECT phone FROM customers WHERE id = " . (int)$id)->fetch();
    assertSame('+255700000002', $row['phone']);

    cleanupCustomerModelTestRows();
});

test('findOrCreateByEmail() rejects an invalid email', function () {
    $model = new Customer();
    $id    = $model->findOrCreateByEmail('not-an-email', 'Test Customer');
    assertTrue($id === null);
});
