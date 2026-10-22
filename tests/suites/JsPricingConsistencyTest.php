<?php
use App\Services\ItineraryQuoteBuilder;

/**
 * admin/quotation_new.php and admin/package_edit.php each carry a
 * client-side JS re-implementation of ItineraryQuoteBuilder's season
 * detection (peDetectSeason), for an instant live-preview without a
 * server round-trip — a deliberate, audited UX tradeoff, but one where
 * the two implementations can silently drift apart if either side
 * changes without the other.
 *
 * This test extracts the REAL peDetectSeason() function body straight
 * out of the live admin page source (not a hand-retyped copy that could
 * itself drift from what's actually deployed) and runs it under Node,
 * asserting its output matches App\Services\ItineraryQuoteBuilder::
 * detectSeason() for the same dates. If Node isn't available in this
 * environment, the check is skipped rather than failed — it verifies
 * consistency when it can run, it doesn't require Node to exist.
 */

function extractJsFunction(string $file, string $functionName): ?string
{
    $src = file_get_contents($file);
    if ($src === false) return null;
    // Match "function name(...) { ... }" up to its balancing closing
    // brace by counting depth, since a naive regex can't do that.
    $start = strpos($src, "function {$functionName}(");
    if ($start === false) return null;
    $openBrace = strpos($src, '{', $start);
    if ($openBrace === false) return null;

    $depth = 0;
    for ($i = $openBrace; $i < strlen($src); $i++) {
        if ($src[$i] === '{') $depth++;
        if ($src[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($src, $start, $i - $start + 1);
            }
        }
    }
    return null;
}

function runNodeSeasonCheck(string $jsFunctionSrc, array $dates): ?array
{
    $script = $jsFunctionSrc . "\n"
        . "var dates = " . json_encode($dates) . ";\n"
        . "var out = {};\n"
        . "dates.forEach(function(d){ out[d] = peDetectSeason(d); });\n"
        . "console.log(JSON.stringify(out));\n";

    $tmpFile = tempnam(sys_get_temp_dir(), 'szv_js_test_') . '.js';
    file_put_contents($tmpFile, $script);

    $output = shell_exec('node ' . escapeshellarg($tmpFile) . ' 2>&1');
    @unlink($tmpFile);

    $decoded = json_decode(trim((string)$output), true);
    return is_array($decoded) ? $decoded : null;
}

$nodeAvailable = trim((string)shell_exec('which node 2>/dev/null')) !== '';

if (!$nodeAvailable) {
    test('JS/PHP season detection consistency (skipped — node not available in this environment)', function () {
        assertTrue(true);
    });
} else {
    $jsFn = extractJsFunction(__DIR__ . '/../../admin/quotation_new.php', 'peDetectSeason');

    test('peDetectSeason() is present in admin/quotation_new.php', function () use ($jsFn) {
        assertTrue($jsFn !== null, 'Could not find function peDetectSeason() in admin/quotation_new.php — has it been renamed or removed?');
    });

    if ($jsFn !== null) {
        $testDates = [
            '2026-08-15', // peak (mid-year range)
            '2026-02-14', // low
            '2026-04-10', // shoulder
            '2026-12-24', // peak (late Dec)
            '2026-01-03', // peak (early Jan)
            '2026-01-15', // low (mid Jan, not peak)
            '2026-03-01', // shoulder boundary
            '2026-10-31', // peak boundary
            '2026-11-01', // low boundary (first day after peak range)
        ];

        $jsResults = runNodeSeasonCheck($jsFn, $testDates);

        test('JS peDetectSeason() ran successfully under node', function () use ($jsResults) {
            assertTrue($jsResults !== null, 'node execution failed or returned non-JSON output');
        });

        if ($jsResults !== null) {
            foreach ($testDates as $date) {
                test("JS and PHP agree on season for {$date}", function () use ($date, $jsResults) {
                    $phpResult = ItineraryQuoteBuilder::detectSeason($date);
                    assertSame($phpResult, $jsResults[$date] ?? null,
                        "PHP says '{$phpResult}' but JS (admin/quotation_new.php's peDetectSeason) says '" . ($jsResults[$date] ?? 'MISSING') . "' for {$date} — the two implementations have drifted apart.");
                });
            }
        }
    }
}

/**
 * The room-occupancy engine's JS mirror lives inside calcTotals() rather
 * than a standalone named function, so it can't be extracted and run
 * under Node the way peDetectSeason() is above. This is a lighter,
 * structural check instead: both admin pages' JS must contain the same
 * pairs/solo-adult formula ItineraryQuoteBuilder::build() actually uses
 * (Math.floor(adults/2) rooms, adults%2 determining the single
 * supplement) — catches the realistic failure mode of the PHP formula
 * changing without the JS mirror being updated to match, even without a
 * live cross-execution.
 */
foreach (['admin/quotation_new.php', 'admin/package_edit.php'] as $file) {
    test("{$file}: JS still mirrors the pairs/solo-adult occupancy formula", function () use ($file) {
        $src = file_get_contents(__DIR__ . '/../../' . $file);
        assertTrue($src !== false, "Could not read {$file}");
        assertTrue(str_contains($src, 'Math.floor(adults / 2)'), "{$file} is missing the room-pairing formula (Math.floor(adults / 2)) — has the occupancy engine's JS mirror been removed or rewritten?");
        assertTrue(str_contains($src, 'adults % 2'), "{$file} is missing the solo-adult detection (adults % 2) — single supplement logic may have drifted from the server.");
        assertTrue(str_contains($src, 'ratePerPerson'), "{$file}'s PE_ACCS no longer carries ratePerPerson — the JS preview would silently fall back to flat per-room pricing for every accommodation.");
    });
}

/**
 * The EAC-resident-country list that drives resident park-fee pricing is
 * hand-duplicated as a literal array in both PHP
 * (ItineraryQuoteBuilder::EAC_RESIDENT_COUNTRIES, private) and JS
 * (admin/quotation_new.php's PE_EAC_COUNTRIES) — unlike detectSeason()
 * above, nothing previously caught the two drifting apart if either side
 * were edited alone. Read via ReflectionClassConstant rather than making
 * the PHP constant public just for this test.
 */
test('PHP EAC_RESIDENT_COUNTRIES and JS PE_EAC_COUNTRIES stay in sync', function () {
    $ref = new ReflectionClassConstant(ItineraryQuoteBuilder::class, 'EAC_RESIDENT_COUNTRIES');
    $phpList = $ref->getValue();
    assertTrue(is_array($phpList) && count($phpList) > 0, 'Could not read EAC_RESIDENT_COUNTRIES via reflection — has it been renamed?');

    $src = file_get_contents(__DIR__ . '/../../admin/quotation_new.php');
    assertTrue($src !== false, 'Could not read admin/quotation_new.php');
    assertTrue(
        (bool)preg_match("/var PE_EAC_COUNTRIES\s*=\s*\[(.*?)\]/", $src, $m),
        'Could not find "var PE_EAC_COUNTRIES = [...]" in admin/quotation_new.php — has it been renamed or removed?'
    );

    preg_match_all("/'([a-z]+)'/", $m[1], $jsMatches);
    $jsList = $jsMatches[1];

    sort($phpList);
    sort($jsList);
    assertSame($phpList, $jsList, 'PHP EAC_RESIDENT_COUNTRIES (' . implode(',', $phpList) . ') and JS PE_EAC_COUNTRIES (' . implode(',', $jsList) . ') have drifted apart — a country added or removed on one side needs the same change on the other.');
});
