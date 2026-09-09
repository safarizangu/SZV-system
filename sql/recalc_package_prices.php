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

    $costBreakdown = json_decode($pkg['cost_breakdown'] ?? '{}', true) ?: [];
    $svcPax        = (float)($costBreakdown['service_charge_pax'] ?? 0);

    $quote     = ItineraryQuoteBuilder::build(['days' => $days], 1, 0, false);
    $lineItems = $quote['line_items'];
    if ($svcPax > 0) {
        $lineItems[] = [
            'description' => 'Service Charge', 'unit_price' => $svcPax,
            'quantity_str'=> '1 Guest',
            'amount'      => round($svcPax, 2), 'category' => 'service_charge',
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
