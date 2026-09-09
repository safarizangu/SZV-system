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

test('totals(): subtotal -> 18% VAT -> grand total -> 30% deposit', function () {
    $lineItems = [['amount' => 1000, 'category' => 'other']];
    $totals = ItineraryQuoteBuilder::totals($lineItems);

    assertSame(1000.0, $totals['subtotal']);
    assertSame(180.0, $totals['vat']);
    assertSame(1180.0, $totals['grand_total']);
    assertSame(354.0, $totals['deposit']);
    assertSame(826.0, $totals['balance']);
});

test('totals(): a discount percentage reduces VAT and grand total accordingly', function () {
    $lineItems = [['amount' => 1000, 'category' => 'other']];
    $totals = ItineraryQuoteBuilder::totals($lineItems, 10); // 10% off

    assertSame(1000.0, $totals['subtotal']);
    assertSame(100.0, $totals['discount']);
    assertSame(162.0, $totals['vat']);          // 900 * 0.18
    assertSame(1062.0, $totals['grand_total']); // 900 + 162
});
