<?php
use App\Services\ItineraryQuoteBuilder;

test('July is peak season', function () {
    assertSame('peak', ItineraryQuoteBuilder::detectSeason('2026-07-15'));
});

test('late December is peak season', function () {
    assertSame('peak', ItineraryQuoteBuilder::detectSeason('2026-12-24'));
});

test('early January is peak season', function () {
    assertSame('peak', ItineraryQuoteBuilder::detectSeason('2026-01-03'));
});

test('mid-January is not peak season', function () {
    assertFalse(ItineraryQuoteBuilder::detectSeason('2026-01-15') === 'peak');
});

test('April is shoulder season', function () {
    assertSame('shoulder', ItineraryQuoteBuilder::detectSeason('2026-04-10'));
});

test('February is low season', function () {
    assertSame('low', ItineraryQuoteBuilder::detectSeason('2026-02-14'));
});
