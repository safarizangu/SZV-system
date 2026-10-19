<?php
/**
 * One-off data-repair script — recalculates packages.price_1pax from each
 * package's linked itinerary, using the exact same formula
 * admin/package_edit.php already applies on every save: grand_total
 * (itinerary + service charge, 1 pax, 0 children) via ItineraryQuoteBuilder.
 *
 * price_1pax is a "starting from" marketing figure only — the real price a
 * guest pays is always recomputed fresh from their actual adults/children
 * count at quotation/booking time, never read from this column.
 *
 * Also syncs each linked itinerary's base_price_pax to the new price_1pax,
 * since that field is meant to track it and can go stale.
 *
 * Prints old -> new for every package before writing, so the change is
 * auditable rather than a silent overwrite. Safe to re-run (idempotent —
 * recomputes the same way every time from the current itinerary data).
 *
 * Usage: php sql/recalc_package_prices.php
 */
require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\ItineraryQuoteBuilder;

$db = \App\Models\Database::getInstance();

$packages = $db->query("SELECT id, name, itinerary_id, itinerary, cost_breakdown, price_1pax FROM packages ORDER BY id")->fetchAll();

foreach ($packages as $pkg) {
    $days = [];
    if (!empty($pkg['itinerary_id'])) {
        $stmt = $db->prepare("SELECT days FROM itineraries WHERE id=? LIMIT 1");
        $stmt->execute([$pkg['itinerary_id']]);
        $row  = $stmt->fetch();
        $days = $row ? (json_decode($row['days'] ?? '[]', true) ?: []) : [];
    } else {
        $days = json_decode($pkg['itinerary'] ?? '[]', true) ?: [];
    }

    if (!$days) {
        echo "SKIP #{$pkg['id']} {$pkg['name']} — no itinerary days found\n";
        continue;
    }

    // service_charge_adult/service_charge_child replaced the old flat
    // service_charge_pax (one rate for every traveler regardless of age)
    // — a package saved before that split still has only the old key, so
    // it falls back to applying that same flat rate as the Adults rate
    // here, same as everywhere else that reads this column.
    $costBreakdown = json_decode($pkg['cost_breakdown'] ?? '{}', true) ?: [];
    $svcAdultPax   = (float)($costBreakdown['service_charge_adult'] ?? $costBreakdown['service_charge_pax'] ?? 0);

    $quote     = ItineraryQuoteBuilder::build(['days' => $days], 1, 0, false);
    $lineItems = $quote['line_items'];
    if ($svcAdultPax > 0) {
        $lineItems[] = [
            'description' => 'Service Charge (Adults)', 'unit_price' => $svcAdultPax,
            'quantity_str'=> '1 Adult',
            'amount'      => round($svcAdultPax, 2), 'category' => 'service_fee',
        ];
    }
    $totals   = ItineraryQuoteBuilder::totals($lineItems);
    $computed = round($totals['grand_total'], 2);

    printf("#%d %-20s  1pax: %8s -> %8s\n", $pkg['id'], $pkg['name'], $pkg['price_1pax'], $computed);

    $db->prepare("UPDATE packages SET price_1pax=? WHERE id=?")->execute([$computed, $pkg['id']]);

    if (!empty($pkg['itinerary_id'])) {
        $db->prepare("UPDATE itineraries SET base_price_pax=? WHERE id=?")
           ->execute([$computed, $pkg['itinerary_id']]);
    }
}

echo "\nDone.\n";
