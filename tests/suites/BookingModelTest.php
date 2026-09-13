<?php
// App\Models\Booking — previously 0% covered despite being the model
// behind every booking read/write in the admin panel and public tracker.
// Runs against the real local database (same connection app code uses),
// creating and cleaning up its own rows rather than touching real data.

use App\Models\Booking;
use App\Models\Database;

function bookingModelTestData(): array
{
    return [
        'full_name'           => 'Test Suite Booking',
        'email'                => 'test-suite-booking@example.invalid',
        'phone'                => '+255700000000',
        'travelers'            => 2,
        'package_id'           => null,
        'package_name'         => 'Test Package',
        'accommodation_level'  => 'midrange',
        'travel_date'          => '2027-01-15',
        'special_request'      => 'A test special request',
        'total_amount'         => 1000.00,
        'deposit_amount'       => 250.00,
        'payment_method'       => 'bank_transfer',
    ];
}

function cleanupBookingModelTestRows(): void
{
    Database::getInstance()
        ->prepare("DELETE FROM bookings WHERE email = ?")
        ->execute(['test-suite-booking@example.invalid']);
}

test('Booking::create() inserts a row and returns a generated ref', function () {
    cleanupBookingModelTestRows();
    $model = new Booking();
    $ref   = $model->create(bookingModelTestData());

    assertTrue(is_string($ref) && strlen($ref) > 0);
    assertTrue(str_starts_with($ref, 'SZV'));

    cleanupBookingModelTestRows();
});

test('Booking::findByRefAndDate() finds what create() just inserted', function () {
    cleanupBookingModelTestRows();
    $model = new Booking();
    $ref   = $model->create(bookingModelTestData());

    $found = $model->findByRefAndDate($ref, '2027-01-15');
    assertTrue($found !== null);
    assertSame('Test Suite Booking', $found['full_name']);
    assertSame('A test special request', $found['special_request']);
    assertSame('pending', $found['payment_status']);

    cleanupBookingModelTestRows();
});

test('Booking::findByRefAndDate() returns null for a non-existent booking', function () {
    $model = new Booking();
    $found = $model->findByRefAndDate('SZVNOTREAL00', '2027-01-15');
    assertTrue($found === null);
});

test('Booking::updatePaymentStatus() changes status and records the PayPal order id', function () {
    cleanupBookingModelTestRows();
    $model = new Booking();
    $ref   = $model->create(bookingModelTestData());

    $model->updatePaymentStatus($ref, 'deposit_paid', 'TEST-PAYPAL-ORDER-1');
    $found = $model->findByRefAndDate($ref, '2027-01-15');

    assertSame('deposit_paid', $found['payment_status']);
    assertSame('TEST-PAYPAL-ORDER-1', $found['paypal_order_id']);

    cleanupBookingModelTestRows();
});

test('Booking::count() reflects an inserted row', function () {
    cleanupBookingModelTestRows();
    $model  = new Booking();
    $before = $model->count();
    $model->create(bookingModelTestData());
    $after  = $model->count();

    assertSame($before + 1, $after);

    cleanupBookingModelTestRows();
});
