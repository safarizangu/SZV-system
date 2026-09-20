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
