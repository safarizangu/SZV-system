<?php
use App\Services\ItineraryQuoteBuilder;

function sampleItinerary(): array
{
    return [
        'days' => json_encode([
            [
                'day' => 1, 'location' => 'Tarangire National Park',
                'accommodation_name' => 'Tarangire River Camp', 'accommodation_rate' => 180, 'accommodation_rooms' => 1,
                'vehicle_name' => 'Toyota Land Cruiser', 'vehicle_rate' => 250,
                'park_fee_name' => 'Tarangire National Park', 'park_adult_fee' => 53,
                'activities' => [['name' => 'Morning Game Drive', 'price_pp' => 0, 'type' => 'included']],
                'meals_cost_pp' => 20,
            ],
            [
                'day' => 2, 'location' => 'Serengeti National Park',
                'accommodation_name' => 'Serengeti Camp', 'accommodation_rate' => 220, 'accommodation_rooms' => 1,
                'vehicle_name' => 'Toyota Land Cruiser', 'vehicle_rate' => 250,
                'park_fee_name' => 'Serengeti National Park', 'park_adult_fee' => 70,
                'activities' => [['name' => 'Hot Air Balloon Safari', 'price_pp' => 599, 'type' => 'included']],
                'meals_cost_pp' => 20,
            ],
        ]),
        'includes' => json_encode(['All park fees', 'Full board']),
        'excludes' => json_encode(['International flights']),
    ];
}

test('line items scale with adults+children, not a flat per-booking rate', function () {
    $itin = sampleItinerary();

    $couple = ItineraryQuoteBuilder::build($itin, 2, 0);
    $family = ItineraryQuoteBuilder::build($itin, 2, 2);

    $sum = fn($items) => array_sum(array_column($items, 'amount'));

    assertTrue($sum($family['line_items']) > $sum($couple['line_items']),
        'A 2 adult + 2 child party must cost more than a 2 adult party for the same itinerary');
});

test('park fees charge the child rate for children, not the adult rate', function () {
    $withChildren = ItineraryQuoteBuilder::build(sampleItinerary(), 2, 2);
    $parkLines = array_filter($withChildren['line_items'], fn($i) => $i['category'] === 'park_fee');
    assertTrue(count($parkLines) === 2, 'Expected one line item per park (Tarangire, Serengeti)');
    // Tarangire: (53 adult + 18 child) x 2 each x 1 entry = 106 + 36 = 142.
    // Serengeti: (70 adult + 20 child) x 2 each x 1 entry = 140 + 40 = 180.
    // Child fees come from the park_fees table (matched by name), since the
    // day itinerary snapshot only stores the adult fee.
    $total = array_sum(array_column($parkLines, 'amount'));
    assertSame(322.0, $total);
});

test('the balloon safari activity scales by full pax (adults+children)', function () {
    $built = ItineraryQuoteBuilder::build(sampleItinerary(), 2, 1);
    $balloon = array_values(array_filter($built['line_items'], fn($i) => str_contains($i['description'], 'Balloon')))[0] ?? null;
    assertTrue($balloon !== null, 'Expected the balloon safari line item');
    assertSame(599.0 * 3, (float)$balloon['amount']); // 3 pax total
});

test('a solo traveler still gets a valid, non-empty quote', function () {
    $built = ItineraryQuoteBuilder::build(sampleItinerary(), 1, 0);
    assertTrue(count($built['line_items']) > 0);
    assertSame(1, $built['pax']);
});

test('totals(): subtotal -> 18% VAT -> grand total -> DEPOSIT_PERCENT deposit', function () {
    $lineItems = [['amount' => 1000, 'category' => 'other']];
    $totals = ItineraryQuoteBuilder::totals($lineItems);
    $depositPct = defined('DEPOSIT_PERCENT') ? DEPOSIT_PERCENT : 25;

    assertSame(1000.0, $totals['subtotal']);
    assertSame(180.0, $totals['vat']);
    assertSame(1180.0, $totals['grand_total']);
    assertSame(round(1180.0 * ($depositPct / 100), 2), $totals['deposit']);
    assertSame(round(1180.0 - $totals['deposit'], 2), $totals['balance']);
});

test('totals(): a discount percentage reduces VAT and grand total accordingly', function () {
    $lineItems = [['amount' => 1000, 'category' => 'other']];
    $totals = ItineraryQuoteBuilder::totals($lineItems, 10); // 10% off

    assertSame(1000.0, $totals['subtotal']);
    assertSame(100.0, $totals['discount']);
    assertSame(162.0, $totals['vat']);          // 900 * 0.18
    assertSame(1062.0, $totals['grand_total']); // 900 + 162
});

test('totals(): markup defaults to 0 — no behavior change unless explicitly requested', function () {
    $lineItems = [['amount' => 1000, 'category' => 'other']];
    $totals = ItineraryQuoteBuilder::totals($lineItems);
    assertSame(0.0, $totals['markup']);
    assertSame(1180.0, $totals['grand_total']);
});

test('totals(): a markup percentage increases the subtotal before VAT', function () {
    $lineItems = [['amount' => 1000, 'category' => 'other']];
    $totals = ItineraryQuoteBuilder::totals($lineItems, 0, 20); // 20% markup, no discount

    assertSame(200.0, $totals['markup']);
    assertSame(216.0, $totals['vat']);          // (1000+200) * 0.18
    assertSame(1416.0, $totals['grand_total']); // 1200 + 216
});

test('totals(): markup applies before discount, not after', function () {
    $lineItems = [['amount' => 1000, 'category' => 'other']];
    $totals = ItineraryQuoteBuilder::totals($lineItems, 10, 20); // 20% markup, then 10% off

    // 1000 -> +20% = 1200 -> -10% = 1080 -> +18% VAT = 1274.40
    assertSame(200.0, $totals['markup']);
    assertSame(120.0, $totals['discount']);
    assertSame(194.4, $totals['vat']);
    assertSame(1274.4, $totals['grand_total']);
});

// ── Room-occupancy engine ───────────────────────────────────────
// Tied to real seed data (Tarangire River Camp: rate_per_person=55,
// single_supplement=10, child_rate=25 — a genuine per-person-sharing
// property; Arusha Planet Lodge: rate_per_room=250, rate_per_person=0 —
// a genuine flat-per-room property), same tradeoff the existing park-fee
// tests above already make. If these seed values change, update the
// expected totals here to match, don't just delete the test.

function perPersonItinerary(int $nights = 1): array
{
    $days = [];
    for ($i = 1; $i <= $nights; $i++) {
        $days[] = ['day' => $i, 'accommodation_name' => 'Tarangire River Camp', 'accommodation_rate' => 55, 'accommodation_rooms' => 1];
    }
    return ['days' => json_encode($days)];
}

test('occupancy engine: an even adult count pairs into rooms with no single supplement', function () {
    $built = ItineraryQuoteBuilder::build(perPersonItinerary(1), 4, 0, false);
    $accLine = array_values(array_filter($built['line_items'], fn($i) => str_contains($i['description'], 'Per Person Sharing')))[0] ?? null;
    assertTrue($accLine !== null, 'Expected a per-person-sharing accommodation line');
    assertSame(220.0, (float)$accLine['amount']); // 55 * 4 adults * 1 night
    assertTrue(str_contains($accLine['quantity_str'], '2 Rooms'), 'Expected 4 adults to allocate into 2 rooms');
    $suppLine = array_filter($built['line_items'], fn($i) => str_contains($i['description'], 'Single Supplement'));
    assertTrue(count($suppLine) === 0, 'An even adult count should never trigger a single supplement');
});

test('occupancy engine: an odd adult count leaves exactly one paying the single supplement', function () {
    $built = ItineraryQuoteBuilder::build(perPersonItinerary(2), 3, 0, false);
    $accLine  = array_values(array_filter($built['line_items'], fn($i) => str_contains($i['description'], 'Per Person Sharing')))[0] ?? null;
    $suppLine = array_values(array_filter($built['line_items'], fn($i) => str_contains($i['description'], 'Single Supplement')))[0] ?? null;
    assertSame(330.0, (float)$accLine['amount']);  // 55 * 3 adults * 2 nights
    assertTrue(str_contains($accLine['quantity_str'], '2 Rooms'), 'Expected 3 adults to allocate into 2 rooms (one shared, one solo)');
    assertTrue($suppLine !== null, 'Expected exactly one single-supplement line for the odd adult out');
    assertSame(20.0, (float)$suppLine['amount']); // 10 * 2 nights — charged once, not per adult
});

test('occupancy engine: children are charged the child rate, not the adult per-person rate', function () {
    $built = ItineraryQuoteBuilder::build(perPersonItinerary(1), 2, 2, false);
    $childLine = array_values(array_filter($built['line_items'], fn($i) => str_contains($i['description'], 'Child Rate')))[0] ?? null;
    assertTrue($childLine !== null, 'Expected a child-rate line');
    assertSame(50.0, (float)$childLine['amount']); // 25 * 2 children * 1 night
});

test('occupancy engine: a flat-per-room property is unaffected (no rate_per_person on file)', function () {
    $itin = ['days' => json_encode([
        ['day' => 1, 'accommodation_name' => 'Arusha Planet Lodge', 'accommodation_rate' => 250, 'accommodation_rooms' => 1],
        ['day' => 2, 'accommodation_name' => 'Arusha Planet Lodge', 'accommodation_rate' => 250, 'accommodation_rooms' => 1],
    ])];
    $built = ItineraryQuoteBuilder::build($itin, 2, 0, false);
    $accLine = array_values(array_filter($built['line_items'], fn($i) => str_contains($i['description'], 'Arusha Planet Lodge')))[0] ?? null;
    assertTrue($accLine !== null);
    assertTrue(!str_contains($accLine['description'], 'Per Person Sharing'), 'A room-rate-only property must not switch to per-person pricing');
    assertSame(500.0, (float)$accLine['amount']); // 250 * 1 room * 2 nights — occupancy-independent, unchanged
});
