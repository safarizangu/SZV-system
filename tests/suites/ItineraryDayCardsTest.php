<?php
use App\Helpers\ItineraryDayCards;

function sampleDays(): array
{
    return [
        [
            'day' => 1, 'title' => 'Arusha & Tarangire', 'location' => 'Tarangire National Park',
            'description' => "Line one.\nLine two.",
            'accommodation_name' => 'Tarangire River Camp',
            'vehicle_name' => 'Toyota Land Cruiser',
            'park_fee_name' => 'Tarangire National Park',
            'activities' => [
                ['name' => 'Morning Game Drive', 'type' => 'included'],
                ['name' => 'Sundowner', 'type' => 'included'],
                ['name' => 'Optional Balloon', 'type' => 'optional'],
            ],
        ],
        [
            // A day with only a vehicle — no accommodation change, no park.
            'day' => 2, 'title' => 'Transit Day', 'location' => '',
            'description' => '', 'vehicle_name' => 'Toyota Land Cruiser',
            'activities' => [],
        ],
    ];
}

test('render() joins multiple activities with a real middle dot, not literal "&middot;" text', function () {
    $html = ItineraryDayCards::render(sampleDays());
    assertTrue(str_contains($html, 'Morning Game Drive &middot; Sundowner'),
        'Expected the HTML entity to survive as markup');
    assertFalse(str_contains($html, '&amp;middot;'),
        'The whole joined string must not be re-escaped after the entity was inserted — that turns &middot; into visible "&middot;" text');
});

test('render() lists every named activity regardless of included/optional (display only — pricing lives in ItineraryQuoteBuilder)', function () {
    $html = ItineraryDayCards::render(sampleDays());
    assertTrue(str_contains($html, 'Optional Balloon'));
});

test('render() only emits a box for categories that have data', function () {
    $html = ItineraryDayCards::render(sampleDays());
    // Day 2 has only a vehicle — must not render empty Accommodation/Park boxes.
    $day2Start = strpos($html, 'Day 2');
    $day2Html  = substr($html, $day2Start);
    assertFalse(str_contains($day2Html, 'box-acc'));
    assertFalse(str_contains($day2Html, 'box-park'));
    assertTrue(str_contains($day2Html, 'box-veh'));
});

test('render() omits emoji by default (dompdf renders emoji as literal "?")', function () {
    $html = ItineraryDayCards::render(sampleDays());
    assertFalse(str_contains($html, '🏕'));
    assertTrue(str_contains($html, '>Accommodation<') || str_contains($html, 'Accommodation</p>'));
});

test('render() includes emoji when explicitly requested for real-browser contexts', function () {
    $html = ItineraryDayCards::render(sampleDays(), true);
    assertTrue(str_contains($html, '🏕'));
});

test('render() never uses font-weight 500/600 — dompdf silently breaks glyph rendering at those weights', function () {
    $css = ItineraryDayCards::css();
    assertFalse(str_contains($css, 'font-weight:500'));
    assertFalse(str_contains($css, 'font-weight:600'));
});

test('renderEmailSafe() produces table-based markup with no CSS grid/flexbox', function () {
    $html = ItineraryDayCards::renderEmailSafe(sampleDays());
    assertTrue(str_contains($html, '<table'));
    assertFalse(str_contains($html, 'display:grid'));
    assertFalse(str_contains($html, 'display:flex'));
});

test('renderEmailSafe() also avoids the middot double-escaping bug', function () {
    $html = ItineraryDayCards::renderEmailSafe(sampleDays());
    assertFalse(str_contains($html, '&amp;middot;'));
});

test('render() escapes user-controlled text to prevent HTML injection via itinerary content', function () {
    $days = [[
        'day' => 1, 'title' => '<script>alert(1)</script>', 'location' => '',
        'description' => '', 'accommodation_name' => '<b>bold</b>',
    ]];
    $html = ItineraryDayCards::render($days);
    assertFalse(str_contains($html, '<script>alert(1)</script>'));
    assertFalse(str_contains($html, '<b>bold</b>'));
});
