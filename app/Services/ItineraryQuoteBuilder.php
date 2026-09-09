<?php
namespace App\Services;

/**
 * Turns an itinerary's day-by-day plan into priced quotation line items,
 * scaled to a party's exact adults/children count.
 *
 * Extracted from api/itinerary.php's `quote_items` action so the same
 * logic can be called directly in PHP (no HTTP round-trip, no admin-session
 * requirement) from api/book.php — the public direct-booking path needs
 * the identical adults/children-aware pricing the admin quote builder
 * already used, not a separate, potentially-inconsistent calculation.
 */
class ItineraryQuoteBuilder
{
    /**
     * @param array $itinerary Row from the `itineraries` table (days/includes/excludes as JSON strings or already-decoded arrays)
     */
    public static function build(array $itinerary, int $adults, int $children, bool $includeDefaultServiceFee = true): array
    {
        $adults   = max(1, $adults);
        $children = max(0, $children);
        $pax      = $adults + $children;

        $days     = self::decode($itinerary['days']     ?? []);
        $includes = self::decode($itinerary['includes'] ?? []);
        $excludes = self::decode($itinerary['excludes'] ?? []);

        // ── Step 1: Aggregate resources across all days ───────
        $accGroups  = [];
        $vehGroups  = [];
        $parkGroups = [];
        $actPaid    = [];
        $actFree    = [];
        $mealsTotal = 0;
        $mealsDays  = 0;
        $otherItems = [];

        foreach ($days as $day) {
            $dayNum = $day['day'] ?? '?';

            $accName = trim($day['accommodation_name'] ?? '');
            $accRate = (float)($day['accommodation_rate'] ?? 0);
            if ($accName && $accRate > 0) {
                $key = $accName;
                if (!isset($accGroups[$key])) {
                    $accGroups[$key] = [
                        'name' => $accName, 'rate' => $accRate,
                        'rooms' => (int)($day['accommodation_rooms'] ?? 1),
                        'nights' => 0, 'days' => [],
                    ];
                }
                $accGroups[$key]['nights']++;
                $accGroups[$key]['days'][] = $dayNum;
            }

            $vehName = trim($day['vehicle_name'] ?? '');
            $vehRate = (float)($day['vehicle_rate'] ?? 0);
            if ($vehName && $vehRate > 0) {
                $key = $vehName;
                if (!isset($vehGroups[$key])) {
                    $vehGroups[$key] = ['name' => $vehName, 'rate' => $vehRate, 'days' => 0];
                }
                $vehGroups[$key]['days']++;
            }

            $parkName = trim($day['park_fee_name'] ?? '');
            $parkFee  = (float)($day['park_adult_fee'] ?? 0);
            if ($parkName && $parkFee > 0) {
                $key = $parkName;
                if (!isset($parkGroups[$key])) {
                    $parkGroups[$key] = [
                        'name' => $parkName, 'fee' => $parkFee, 'childFee' => self::lookupParkChildFee($parkName),
                        'entries' => 0, 'adults' => $adults, 'children' => $children,
                    ];
                }
                $parkGroups[$key]['entries']++;
            }

            foreach ($day['activities'] ?? [] as $act) {
                $actName = trim($act['name'] ?? '');
                if (!$actName) continue;
                $price = (float)($act['price_pp'] ?? 0);
                $type  = $act['type'] ?? 'included';
                if ($type === 'optional') continue;

                if ($price > 0) {
                    $actPaid[] = ['name' => $actName, 'price' => $price, 'day' => $dayNum];
                } else {
                    $actFree[] = ['name' => $actName, 'day' => $dayNum];
                }
            }

            $meals = (float)($day['meals_cost_pp'] ?? 0);
            if ($meals > 0) {
                $mealsTotal += $meals;
                $mealsDays++;
            }

            foreach ($day['other_items'] ?? [] as $ot) {
                $otName = trim($ot['name'] ?? '');
                $otAmt  = (float)($ot['amount'] ?? 0);
                if (!$otName || $otAmt <= 0) continue;

                $found = false;
                foreach ($otherItems as &$existing) {
                    if ($existing['name'] === $otName) {
                        $existing['amount'] += $otAmt;
                        $existing['count']++;
                        $found = true;
                        break;
                    }
                }
                unset($existing);
                if (!$found) {
                    $otherItems[] = ['name' => $otName, 'amount' => $otAmt, 'count' => 1];
                }
            }
        }

        // ── Step 2: Build line items ──────────────────────────
        $lineItems = [];

        foreach ($accGroups as $acc) {
            $n = $acc['nights']; $r = $acc['rooms'];
            $amount = $acc['rate'] * $n * $r;
            $qtyStr = $r > 1 ? $r . ' Rooms × ' . $n . ' Night' . ($n !== 1 ? 's' : '') : $n . ' Night' . ($n !== 1 ? 's' : '');
            $lineItems[] = [
                'description' => 'Accommodation – ' . $acc['name'],
                'unit_price'  => $acc['rate'],
                'quantity_str'=> $qtyStr,
                'amount'      => round($amount, 2),
                'category'    => 'accommodation',
            ];
        }

        foreach ($vehGroups as $veh) {
            $amount = $veh['rate'] * $veh['days'];
            $lineItems[] = [
                'description' => $veh['name'] . ' & Fuel',
                'unit_price'  => $veh['rate'],
                'quantity_str'=> $veh['days'] . ' Day' . ($veh['days'] !== 1 ? 's' : ''),
                'amount'      => round($amount, 2),
                'category'    => 'transport',
            ];
        }

        foreach ($parkGroups as $park) {
            $amount   = ($park['fee'] * $park['adults'] + $park['childFee'] * $park['children']) * $park['entries'];
            $guestStr = $park['adults'] . ' Adult' . ($park['adults'] !== 1 ? 's' : '')
                . ($park['children'] > 0 ? ' + ' . $park['children'] . ' Child' . ($park['children'] !== 1 ? 'ren' : '') : '');
            $qtyStr   = $park['entries'] > 1 ? $guestStr . ' × ' . $park['entries'] . ' Days' : $guestStr;
            $lineItems[] = [
                'description' => $park['name'] . ' Entry Fees',
                'unit_price'  => $park['fee'],
                'quantity_str'=> $qtyStr,
                'amount'      => round($amount, 2),
                'category'    => 'park_fee',
            ];
        }

        $actConsolidated = [];
        foreach ($actPaid as $act) {
            $key = $act['name'];
            if (!isset($actConsolidated[$key])) {
                $actConsolidated[$key] = ['name' => $act['name'], 'price' => $act['price'], 'count' => 0];
            }
            $actConsolidated[$key]['count']++;
        }
        foreach ($actConsolidated as $act) {
            $count  = $act['count'];
            $amount = $act['price'] * $pax * $count;
            $lineItems[] = [
                'description' => $act['name'],
                'unit_price'  => $act['price'],
                'quantity_str'=> $pax . ' Guest' . ($pax !== 1 ? 's' : '') . ($count > 1 ? ' × ' . $count . ' Days' : ''),
                'amount'      => round($amount, 2),
                'category'    => 'activity',
            ];
        }

        if ($mealsTotal > 0) {
            $avgMealPP     = round($mealsTotal / max(1, $mealsDays), 2);
            $totalMealsAmt = round($mealsTotal * $pax, 2);
            $lineItems[] = [
                'description' => 'Meals (Full Board — ' . $mealsDays . ' day' . ($mealsDays !== 1 ? 's' : '') . ')',
                'unit_price'  => $avgMealPP,
                'quantity_str'=> $pax . ' Guests × ' . $mealsDays . ' Days',
                'amount'      => $totalMealsAmt,
                'category'    => 'activity',
            ];
        }

        foreach ($otherItems as $ot) {
            $amount   = $ot['amount'];
            $isPerPax = stripos($ot['name'], 'transfer') !== false
                || stripos($ot['name'], 'water') !== false
                || stripos($ot['name'], 'contribution') !== false;

            if ($isPerPax && $pax > 1) {
                $amount = $ot['amount'] * $pax;
                $qtyStr = $pax . ' Guests';
                if ($ot['count'] > 1) $qtyStr .= ' × ' . $ot['count'] . ' Days';
            } else {
                $qtyStr = $ot['count'] > 1 ? $ot['count'] . ' Times' : '1';
            }

            $lineItems[] = [
                'description' => $ot['name'],
                'unit_price'  => $ot['amount'],
                'quantity_str'=> $qtyStr,
                'amount'      => round($amount, 2),
                'category'    => 'other',
            ];
        }

        $hasServiceFee = false;
        foreach ($lineItems as $li) {
            if (stripos($li['description'], 'service fee') !== false || stripos($li['description'], 'guide fee') !== false) {
                $hasServiceFee = true; break;
            }
        }
        if (!$hasServiceFee && $includeDefaultServiceFee) {
            $svcRate = 100;
            $lineItems[] = [
                'description' => 'Professional Driver Guide & Service Fee',
                'unit_price'  => $svcRate,
                'quantity_str'=> $pax . ' Guest' . ($pax !== 1 ? 's' : ''),
                'amount'      => round($svcRate * $pax, 2),
                'category'    => 'service_fee',
            ];
        }

        $includedActivitiesNote = '';
        if (!empty($actFree)) {
            $unique = array_unique(array_column($actFree, 'name'));
            $includedActivitiesNote = 'Included activities: ' . implode(', ', $unique);
        }

        $dayTitles = array_map(function ($d) {
            return [
                'day'           => $d['day'] ?? '',
                'title'         => $d['title'] ?? '',
                'description'   => $d['description'] ?? '',
                'location'      => $d['location'] ?? '',
                'accommodation' => $d['accommodation_name'] ?? '',
                'vehicle'       => $d['vehicle_name'] ?? '',
                'park'          => $d['park_fee_name'] ?? '',
            ];
        }, $days);

        $optionalActivities = [];
        foreach ($days as $day) {
            foreach ($day['activities'] ?? [] as $act) {
                if (($act['type'] ?? 'included') === 'optional' && !empty($act['name'])) {
                    $optionalActivities[] = [
                        'name'  => $act['name'],
                        'price' => (float)($act['price_pp'] ?? 0),
                        'day'   => $day['day'] ?? '',
                    ];
                }
            }
        }

        return [
            'line_items'          => $lineItems,
            'includes'            => $includes,
            'excludes'            => $excludes,
            'day_titles'          => $dayTitles,
            'optional_activities' => $optionalActivities,
            'included_activities' => array_values(array_unique(array_column($actFree, 'name'))),
            'notes_suggestion'    => $includedActivitiesNote,
            'pax'                 => $pax,
            'adults'              => $adults,
            'children'            => $children,
        ];
    }

    /**
     * Given a set of line items, compute the same subtotal -> VAT(18%) ->
     * grand total -> deposit(30%) totals used throughout the quotation
     * system (admin/quotation_new.php's JS, api/quotation_pdf.php).
     */
    public static function totals(array $lineItems, float $discountPct = 0): array
    {
        $subtotal   = array_sum(array_column($lineItems, 'amount'));
        $discAmt    = $subtotal * ($discountPct / 100);
        $afterDisc  = $subtotal - $discAmt;
        $vat        = round($afterDisc * 0.18, 2);
        $grandTotal = round($afterDisc + $vat, 2);
        $deposit    = round($grandTotal * 0.30, 2);

        return [
            'subtotal'    => round($subtotal, 2),
            'discount'    => round($discAmt, 2),
            'vat'         => $vat,
            'grand_total' => $grandTotal,
            'deposit'     => $deposit,
            'balance'     => round($grandTotal - $deposit, 2),
        ];
    }

    /**
     * The day-by-day itinerary only snapshots a park's adult fee
     * (park_fee_name/park_adult_fee), not a child fee — looked up here
     * from the park_fees table instead, so child pricing always reflects
     * the current rate without needing to change the itinerary editor's
     * day-JSON shape or backfill existing itinerary rows. Falls back to
     * 0 for a free-typed park name with no matching row.
     */
    private static function lookupParkChildFee(string $parkName): float
    {
        static $cache = [];
        if (array_key_exists($parkName, $cache)) return $cache[$parkName];

        $fee = 0.0;
        try {
            $db   = \App\Models\Database::getInstance();
            $stmt = $db->prepare("SELECT child_fee FROM park_fees WHERE name = ? LIMIT 1");
            $stmt->execute([$parkName]);
            $fee  = (float)($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            // No DB context (e.g. a future non-DB caller) — treat as no child fee.
        }
        return $cache[$parkName] = $fee;
    }

    private static function decode($value): array
    {
        if (is_array($value)) return $value;
        if (empty($value)) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
