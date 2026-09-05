<?php
use App\Helpers\Formatter;

test('currency formats with thousands separator and no decimals', function () {
    assertSame('USD 3,500', Formatter::currency(3500));
});

test('currency respects a custom currency code', function () {
    assertSame('TZS 1,000', Formatter::currency(1000, 'TZS'));
});

test('sanitize strips tags and leaves inner text', function () {
    assertSame('alert(1)', Formatter::sanitize('<script>alert(1)</script>'));
});

test('sanitize escapes quotes left after tag stripping', function () {
    assertSame('&quot; onclick=&quot;evil()', Formatter::sanitize('" onclick="evil()'));
});

test('sanitize trims surrounding whitespace', function () {
    assertSame('hello', Formatter::sanitize('  hello  '));
});

test('generateRef produces an SZV-prefixed 13-char reference', function () {
    $ref = Formatter::generateRef();
    assertTrue(str_starts_with($ref, 'SZV'), "Expected ref to start with SZV, got $ref");
    assertSame(13, strlen($ref), "Expected 13 chars (SZV + 10 hex), got " . strlen($ref));
});

test('generateRef is not deterministic across calls', function () {
    assertFalse(Formatter::generateRef() === Formatter::generateRef());
});
