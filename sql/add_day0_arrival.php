<?php
/**
 * One-off content update — adds a real "Day 0" (airport pickup + Arusha
 * overnight before the tour itself starts) to the 6 itineraries actually
 * linked to the 6 active packages. The itinerary/package editors
 * (admin/package_edit.php, admin/quotation_new.php) have supported
 * flagging a day as this arrival day since it was added, but none of
 * the real itinerary data ever actually used it — every package's
 * day-by-day plan started straight at "Day 1" with no arrival day built
 * in at all, despite several of the package names already promising one
 * more night than the itinerary's own day-count supports (e.g. "Classic
 * Safari — 5 Nights" with only 5 *days* in the itinerary, i.e. 4 nights
 * by the duration_days-1 formula — the name was written assuming a
 * Day 0 that was never actually in the data; adding it now makes the
 * existing names accurate instead of changing them).
 *
 * Day 0 carries a real cost (one more hotel night, the same vehicle
 * continuing from the airport, one dinner) — accommodation chosen to
 * match each package's own accommodation_level, all real Arusha
 * properties already in the accommodations table, not invented. This
 * is a genuine price increase, not a free addition: see
 * docs/DEPLOYMENT_GUIDE.md or ask before re-running against production
 * data if prices have since diverged from what's below.
 *
 * Idempotent: skips an itinerary that already has an is_arrival day at
 * index 0, so safe to re-run.
 *
 * Usage: php sql/add_day0_arrival.php
 * Follow with: php sql/recalc_package_prices.php
 *   (that script recalculates packages.price_1pax / itineraries.base_price_pax
 *   from the new day-by-day data; this script only adds the day and bumps
 *   duration_days, it doesn't touch pricing fields directly)
 */
require_once __DIR__ . '/../app/bootstrap.php';

$db = \App\Models\Database::getInstance();

// itinerary_id => Day 0 definition. Vehicle continues from each
// itinerary's own Day 1 (same car for the whole trip); accommodation
// matches the linked package's own accommodation_level with a real
// Arusha property at that level; meals_cost_pp is dinner only (~40% of
// that itinerary's own full-day rate), not a full day's three meals.
$DAY0 = [
    8 => [ // Classic Safari — midrange
        'title' => 'Arrival in Arusha — Airport Pickup',
        'description' => 'Arrive at Kilimanjaro International Airport, where your driver-guide is waiting to meet you. Transfer into Arusha and settle in for the night — tomorrow the safari begins with the drive south to Tarangire.',
        'vehicle_name' => 'Toyota Land Cruiser (4WD pop-up roof)', 'vehicle_rate' => 250,
        'meals_cost_pp' => 20,
        'accommodation_name' => 'Arusha Coffee Lodge', 'accommodation_rate' => 250,
        'accommodation_alternatives' => ['Arusha Planet Lodge', 'Ngare Sero Mountain Lodge'],
    ],
    9 => [ // Luxury Safari — luxury
        'title' => 'Arrival in Arusha — Private Airport Transfer',
        'description' => 'A private transfer meets you at Kilimanjaro International Airport for the drive into Arusha. Unwind with dinner at the lodge before an early departure for the Serengeti tomorrow.',
        'vehicle_name' => 'Private Land Cruiser (luxury spec)', 'vehicle_rate' => 320,
        'meals_cost_pp' => 45,
        'accommodation_name' => 'Moivaro Coffee Lodge', 'accommodation_rate' => 580,
        'accommodation_alternatives' => ['Hatari Lodge', 'Ngurdoto Mountain Lodge'],
    ],
    10 => [ // Comfort Safari — midrange
        'title' => 'Arrival in Arusha — Airport Pickup',
        'description' => 'Arrive at Kilimanjaro International Airport and transfer into Arusha with your driver-guide. An early night here sets you up for the drive to Lake Manyara in the morning.',
        'vehicle_name' => 'Toyota Land Cruiser 4WD', 'vehicle_rate' => 250,
        'meals_cost_pp' => 20,
        'accommodation_name' => 'Arusha Coffee Lodge', 'accommodation_rate' => 250,
        'accommodation_alternatives' => ['Arusha Planet Lodge', 'Ngare Sero Mountain Lodge'],
    ],
    11 => [ // Safari + Zanzibar — luxury
        'title' => 'Arrival in Arusha — Airport Pickup',
        'description' => 'Arrive at Kilimanjaro International Airport, where your driver-guide welcomes you for the transfer into Arusha. Rest and settle in before tomorrow\'s departure for Tarangire — the first stop on the mainland leg, ahead of the Zanzibar extension at the end of the trip.',
        'vehicle_name' => 'Toyota Land Cruiser 4WD', 'vehicle_rate' => 250,
        'meals_cost_pp' => 20,
        'accommodation_name' => 'Moivaro Coffee Lodge', 'accommodation_rate' => 580,
        'accommodation_alternatives' => ['Hatari Lodge', 'Ngurdoto Mountain Lodge'],
    ],
    12 => [ // Cultural Safari — budget
        'title' => 'Arrival in Arusha — Airport Pickup',
        'description' => 'Arrive at Kilimanjaro International Airport and transfer into Arusha. A relaxed first night in town before the cultural immersion itinerary begins in the morning.',
        'vehicle_name' => 'Toyota Land Cruiser 4WD', 'vehicle_rate' => 200,
        'meals_cost_pp' => 20,
        'accommodation_name' => 'Arusha Backpackers Lodge', 'accommodation_rate' => 50,
        'accommodation_alternatives' => ['Arusha Vision Guesthouse', 'Outpost Lodge Arusha'],
    ],
    13 => [ // Short Safari — budget
        'title' => 'Arrival in Arusha — Airport Pickup',
        'description' => 'Arrive at Kilimanjaro International Airport and transfer into Arusha with your driver-guide. An early night here ahead of the drive to Tarangire in the morning.',
        'vehicle_name' => 'Toyota Land Cruiser 4WD', 'vehicle_rate' => 250,
        'meals_cost_pp' => 20,
        'accommodation_name' => 'Arusha Backpackers Lodge', 'accommodation_rate' => 50,
        'accommodation_alternatives' => ['Arusha Vision Guesthouse', 'Outpost Lodge Arusha'],
    ],
];

foreach ($DAY0 as $itinId => $def) {
    $stmt = $db->prepare("SELECT days, duration_days FROM itineraries WHERE id=? LIMIT 1");
    $stmt->execute([$itinId]);
    $row = $stmt->fetch();
    if (!$row) { echo "SKIP itinerary #{$itinId} — not found\n"; continue; }

    $days = json_decode($row['days'] ?? '[]', true) ?: [];
    if (!empty($days[0]['is_arrival'])) {
        echo "SKIP itinerary #{$itinId} — already has a Day 0 arrival day\n";
        continue;
    }

    $day0 = [
        'day' => 0,
        'title' => $def['title'],
        'location' => 'Arusha',
        'activities' => [],
        'description' => $def['description'],
        'other_items' => [],
        'vehicle_name' => $def['vehicle_name'],
        'vehicle_rate' => $def['vehicle_rate'],
        'meals_cost_pp' => $def['meals_cost_pp'],
        'park_fee_name' => '',
        'park_adult_fee' => 0,
        'accommodation_name' => $def['accommodation_name'],
        'accommodation_rate' => $def['accommodation_rate'],
        'accommodation_rooms' => 1,
        'accommodation_alternatives' => $def['accommodation_alternatives'],
        'is_arrival' => true,
    ];

    array_unshift($days, $day0);
    $newDuration = count($days);

    $db->prepare("UPDATE itineraries SET days=?, duration_days=? WHERE id=?")
       ->execute([json_encode($days, JSON_UNESCAPED_UNICODE), $newDuration, $itinId]);

    $pkgStmt = $db->prepare("UPDATE packages SET duration_days=? WHERE itinerary_id=?");
    $pkgStmt->execute([$newDuration, $itinId]);
    $pkgAffected = $pkgStmt->rowCount();

    echo "OK itinerary #{$itinId}: duration_days -> {$newDuration}, {$pkgAffected} package(s) synced\n";
}

echo "\nDone. Now run: php sql/recalc_package_prices.php\n";
