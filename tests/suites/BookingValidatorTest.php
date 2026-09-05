<?php
use App\Validators\BookingValidator;

function validBookingData(array $overrides = []): array
{
    return array_merge([
        'full_name'   => 'Jane Doe',
        'email'       => 'jane@example.com',
        'phone'       => '+255700000000',
        'adults'      => 2,
        'children'    => 0,
        'travel_date' => date('Y-m-d', strtotime('+30 days')),
        'package_id'  => 1,
    ], $overrides);
}

test('accepts a fully valid booking', function () {
    $v = new BookingValidator();
    assertTrue($v->validate(validBookingData()));
});

test('rejects a missing email', function () {
    $v = new BookingValidator();
    assertFalse($v->validate(validBookingData(['email' => ''])));
    assertTrue(isset($v->errors()['email']));
});

test('rejects a malformed email', function () {
    $v = new BookingValidator();
    assertFalse($v->validate(validBookingData(['email' => 'not-an-email'])));
});

test('rejects a travel date in the past', function () {
    $v = new BookingValidator();
    assertFalse($v->validate(validBookingData(['travel_date' => '2020-01-01'])));
    assertTrue(isset($v->errors()['travel_date']));
});

test('rejects zero adults', function () {
    $v = new BookingValidator();
    assertFalse($v->validate(validBookingData(['adults' => 0])));
});

test('rejects more than 50 adults', function () {
    $v = new BookingValidator();
    assertFalse($v->validate(validBookingData(['adults' => 51])));
});

test('rejects a missing package selection', function () {
    $v = new BookingValidator();
    assertFalse($v->validate(validBookingData(['package_id' => ''])));
});

test('firstError returns the first validation message', function () {
    $v = new BookingValidator();
    $v->validate(validBookingData(['full_name' => '']));
    assertSame('Full name is required.', $v->firstError());
});
