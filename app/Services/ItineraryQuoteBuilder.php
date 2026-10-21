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
 *
 * This is also the single pricing engine for the whole app — the
 * previously-separate App\Services\QuotationEngine (season multiplier,
 * single-supplement, resident park-fee logic, none of it ever wired to
 * a live caller) was retired in favor of merging its useful pieces in
 * here, so there is one calculation pathway, not two that can drift.
 */
class ItineraryQuoteBuilder
{
    /**
     * Countries treated as East African Community residents for park-fee
     * pricing. Deliberately the 5 original EAC members rather than every
     * later-admitted state — narrow the list here if that changes.
     */
    private const EAC_RESIDENT_COUNTRIES = ['tanzania', 'kenya', 'uganda', 'rwanda', 'burundi'];

    /**
     * @param array $itinerary Row from the `itineraries` table (days/includes/excludes as JSON strings or already-decoded arrays)
     * @param string|null $travelDate  YYYY-MM-DD. Accepted for callers that already pass it, but no longer used to scale pricing — a manually entered or selected accommodation/vehicle rate is charged exactly as given, never silently adjusted by season (the admin's typed price must always equal what the printed quotation shows).
     * @param string|null $nationality Guest nationality/country. When it matches an EAC country, resident park-fee rates are used wherever the park has one on file (a $0.00 resident rate on file is treated as "not yet configured" and falls back to the standard rate, never charged as free).
     * @param array{adult?:float,child?:float} $serviceFeeRates Per-person Service Charge, normally the booked package's own cost_breakdown.service_charge_adult/service_charge_child (see admin/package_edit.php). Falls back to the old flat $100/person rate for both when a caller doesn't have a package to read it from. Infants are never charged — they're excluded from $adults/$children entirely, not just this fee.
     */
    public static function build(array $itinerary, int $adults, int $children, bool $includeDefaultServiceFee = true, ?string $travelDate = null, ?string $nationality = null, array $serviceFeeRates = []): array
    {
        $adults   = max(1, $adults);
        $children = max(0, $children);
        $pax      = $adults + $children;

        $days     = self::decode($itinerary['days']     ?? []);
        $includes = self::decode($itinerary['includes'] ?? []);
        $excludes = self::decode($itinerary['excludes'] ?? []);

        $isResident = $nationality ? in_array(strtolower(trim($nationality)), self::EAC_RESIDENT_COUNTRIES, true) : false;

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
                    $rates           = self::lookupParkRates($parkName);
                    $residentApplied = $isResident && $rates['resident_adult'] > 0;
                    $parkGroups[$key] = [
                        'name'       => $parkName,
                        'fee'        => $residentApplied ? $rates['resident_adult'] : $parkFee,
                        'childFee'   => $residentApplied && $rates['resident_child'] > 0 ? $rates['resident_child'] : $rates['child_fee'],
                        'vehicleFee' => $rates['vehicle_fee'],
                        'entries'    => 0, 'adults' => $adults, 'children' => $children,
                        'resident'   => $residentApplied,
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

                // How many travelers this specific day's booking of the
                // activity covers — an explicit Qty field on each
                // Activity row (admin/quotation_new.php,
                // admin/package_edit.php) now drives this, so a party
                // where only some travelers join an activity isn't
                // silently charged for everyone. Days saved before that
                // field existed have no qty key at all, so they fall
                // back to full pax — the only value the old, qty-less
                // behavior could ever have meant.
                $qty = isset($act['qty']) ? max(1, (int)$act['qty']) : $pax;

                if ($price > 0) {
                    $actPaid[] = ['name' => $actName, 'price' => $price, 'qty' => $qty, 'day' => $dayNum];
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

                // An explicit "/traveler" checkbox on each Other Cost row
                // now drives this — admin/quotation_new.php and
                // admin/package_edit.php both save it. Older saved
                // itinerary days from before that checkbox existed have
                // no per_pax key at all, so they fall back to the old
                // name-substring guess rather than silently losing the
                // per-traveler scaling they were relying on.
                $perPax = array_key_exists('per_pax', $ot)
                    ? (bool)$ot['per_pax']
                    : (bool)preg_match('/transfer|water|contribution/i', $otName);

                // An explicit Qty field on each Other Cost row — e.g. 3
                // firewood bundles at $5 each — instead of the row's
                // dollar amount always meaning "one unit". Days saved
                // before that field existed have no qty key, so they
                // fall back to 1 (the only value the old behavior could
                // ever have meant).
                $otQty  = isset($ot['qty']) ? max(1, (int)$ot['qty']) : 1;
                // What a unit is called (e.g. "Box", "Trip", "Vehicle") —
                // a free-text label the admin fills in themselves, purely
                // for the printed quantity string; it never multiplies
                // anything and falls back to the generic "Unit(s)" label
                // when left blank.
                $otUnit = trim($ot['unit'] ?? '');

                $found = false;
                foreach ($otherItems as &$existing) {
                    if ($existing['name'] === $otName) {
                        $existing['qty'] += $otQty;
                        $found = true;
                        break;
                    }
                }
                unset($existing);
                if (!$found) {
                    $otherItems[] = ['name' => $otName, 'unit_price' => $otAmt, 'qty' => $otQty, 'unit_name' => $otUnit, 'per_pax' => $perPax];
                }
            }
        }

        // ── Step 2: Build line items ──────────────────────────
        $lineItems = [];

        foreach ($accGroups as $acc) {
            $n = $acc['nights'];
            $accExtras = self::lookupAccommodationExtras($acc['name']);

            if ($accExtras['rate_per_person'] > 0) {
                // ── Per-person-sharing model — the real convention at
                // 35 of the 60 accommodations currently on file (verified
                // directly against the table before building this).
                // A genuine room allocation: adults pair into twin/double
                // rooms, one leftover adult (an odd count) pays the
                // single supplement instead of a guessed flat rate,
                // children are charged the lodge's own child rate rather
                // than the adult per-person rate.
                $personRate = $accExtras['rate_per_person'];
                $pairs      = intdiv($adults, 2);
                $soloAdult  = $adults % 2;
                $roomsUsed  = $pairs + $soloAdult;

                // unit_price is exactly the rate on file/entered for the
                // day — no hidden seasonal adjustment. The admin's typed
                // or selected rate is the final charged rate; Unit Price
                // × Qty always equals Amount, on screen and on the PDF.
                $baseAmount = $personRate * $adults * $n;
                $qtyStr = $adults . ' Adult' . ($adults !== 1 ? 's' : '') . ' × ' . $n . ' Night' . ($n !== 1 ? 's' : '')
                    . ' (' . $roomsUsed . ' Room' . ($roomsUsed !== 1 ? 's' : '') . ')';
                $lineItems[] = [
                    'description' => 'Accommodation – ' . $acc['name'] . ' (Per Person Sharing)',
                    'unit_price'  => $personRate,
                    'quantity_str'=> $qtyStr,
                    'amount'      => round($baseAmount, 2),
                    'category'    => 'accommodation',
                ];

                if ($soloAdult === 1 && $accExtras['single_supplement'] > 0) {
                    $suppAmount = $accExtras['single_supplement'] * $n;
                    $lineItems[] = [
                        'description' => 'Single Supplement – ' . $acc['name'],
                        'unit_price'  => $accExtras['single_supplement'],
                        'quantity_str'=> '1 Guest × ' . $n . ' Night' . ($n !== 1 ? 's' : ''),
                        'amount'      => round($suppAmount, 2),
                        'category'    => 'accommodation',
                    ];
                }

                if ($children > 0 && $accExtras['child_rate'] > 0) {
                    $childAmount = $accExtras['child_rate'] * $children * $n;
                    $lineItems[] = [
                        'description' => 'Child Rate – ' . $acc['name'],
                        'unit_price'  => $accExtras['child_rate'],
                        'quantity_str'=> $children . ' Child' . ($children !== 1 ? 'ren' : '') . ' × ' . $n . ' Night' . ($n !== 1 ? 's' : ''),
                        'amount'      => round($childAmount, 2),
                        'category'    => 'accommodation',
                    ];
                }
            } else {
                // ── Flat per-room model — unchanged from before. 23 of
                // 60 accommodations only have rate_per_room populated;
                // a flat room rate has no single "correct" occupancy to
                // derive automatically the way a per-person rate does,
                // so the admin's own room count (set per day) still
                // drives this, same as it always has.
                $r = $acc['rooms'];
                $amount = $acc['rate'] * $n * $r;
                $qtyStr = $r > 1 ? $r . ' Rooms × ' . $n . ' Night' . ($n !== 1 ? 's' : '') : $n . ' Night' . ($n !== 1 ? 's' : '');
                $lineItems[] = [
                    'description' => 'Accommodation – ' . $acc['name'],
                    'unit_price'  => $acc['rate'],
                    'quantity_str'=> $qtyStr,
                    'amount'      => round($amount, 2),
                    'category'    => 'accommodation',
                ];

                if ($adults === 1 && $accExtras['single_supplement'] > 0) {
                    $suppAmount = $accExtras['single_supplement'] * $n * $r;
                    $lineItems[] = [
                        'description' => 'Single Supplement – ' . $acc['name'],
                        'unit_price'  => $accExtras['single_supplement'],
                        'quantity_str'=> $qtyStr,
                        'amount'      => round($suppAmount, 2),
                        'category'    => 'accommodation',
                    ];
                }

                if ($children > 0 && $accExtras['child_rate'] > 0) {
                    $childAmount = $accExtras['child_rate'] * $children * $n;
                    $lineItems[] = [
                        'description' => 'Child Rate – ' . $acc['name'],
                        'unit_price'  => $accExtras['child_rate'],
                        'quantity_str'=> $children . ' Child' . ($children !== 1 ? 'ren' : '') . ' × ' . $n . ' Night' . ($n !== 1 ? 's' : ''),
                        'amount'      => round($childAmount, 2),
                        'category'    => 'accommodation',
                    ];
                }
            }
        }

        foreach ($vehGroups as $veh) {
            // There's no multi-vehicle-per-day concept in this schema (one
            // vehicle_name/vehicle_rate per day, grouped across days by
            // name) — always exactly 1 vehicle, stated explicitly so the
            // client sees the same "quantity × days" multiplication the
            // accommodation lines already show, not just a bare day count.
            $dayStr = '1 Vehicle × ' . $veh['days'] . ' Day' . ($veh['days'] !== 1 ? 's' : '');

            // Itemize into daily-rate/fuel/driver only when the day's
            // entered rate matches this vehicle's current on-file total
            // (within a cent) — if an admin typed a custom negotiated
            // rate that doesn't match, splitting it by the DB's component
            // ratios would present a breakdown that doesn't add up to
            // what was actually agreed, so it stays one bundled line.
            $components = self::lookupVehicleComponents($veh['name']);
            $dbTotal    = $components['daily_rate'] + $components['fuel_cost_day'] + $components['driver_cost_day'];

            if ($dbTotal > 0 && abs($dbTotal - $veh['rate']) < 0.01) {
                foreach ([
                    ['daily_rate', $veh['name']],
                    ['fuel_cost_day', 'Fuel — ' . $veh['name']],
                    ['driver_cost_day', 'Driver Allowance — ' . $veh['name']],
                ] as [$field, $desc]) {
                    if ($components[$field] <= 0) continue;
                    $lineItems[] = [
                        'description' => $desc,
                        'unit_price'  => $components[$field],
                        'quantity_str'=> $dayStr,
                        'amount'      => round($components[$field] * $veh['days'], 2),
                        'category'    => 'transport',
                    ];
                }
            } else {
                $amount = $veh['rate'] * $veh['days'];
                $lineItems[] = [
                    'description' => $veh['name'] . ' & Fuel',
                    'unit_price'  => $veh['rate'],
                    'quantity_str'=> $dayStr,
                    'amount'      => round($amount, 2),
                    'category'    => 'transport',
                ];
            }
        }

        foreach ($parkGroups as $park) {
            $amount   = ($park['fee'] * $park['adults'] + $park['childFee'] * $park['children']) * $park['entries'];
            $guestStr = $park['adults'] . ' Adult' . ($park['adults'] !== 1 ? 's' : '')
                . ($park['children'] > 0 ? ' + ' . $park['children'] . ' Child' . ($park['children'] !== 1 ? 'ren' : '') : '');
            $qtyStr   = $park['entries'] > 1 ? $guestStr . ' × ' . $park['entries'] . ' Days' : $guestStr;
            $lineItems[] = [
                'description' => $park['name'] . ' Entry Fees' . ($park['resident'] ? ' (Resident Rate)' : ''),
                'unit_price'  => $park['fee'],
                'quantity_str'=> $qtyStr,
                'amount'      => round($amount, 2),
                'category'    => 'park_fee',
            ];

            // Vehicle entry fee — per vehicle per entry, not per traveler
            // (TANAPA and similar Tanzania park fee schedules charge this
            // separately from the per-person gate fee).
            if ($park['vehicleFee'] > 0) {
                $vehAmount = $park['vehicleFee'] * $park['entries'];
                $lineItems[] = [
                    'description' => $park['name'] . ' Vehicle Entry Fee',
                    'unit_price'  => $park['vehicleFee'],
                    'quantity_str'=> $park['entries'] > 1 ? $park['entries'] . ' Days' : '1 Vehicle',
                    'amount'      => round($vehAmount, 2),
                    'category'    => 'park_fee',
                ];
            }
        }

        $actConsolidated = [];
        foreach ($actPaid as $act) {
            $key = $act['name'];
            if (!isset($actConsolidated[$key])) {
                $actConsolidated[$key] = ['name' => $act['name'], 'price' => $act['price'], 'totalQty' => 0, 'days' => 0];
            }
            $actConsolidated[$key]['totalQty'] += $act['qty'];
            $actConsolidated[$key]['days']++;
        }
        foreach ($actConsolidated as $act) {
            $totalQty = $act['totalQty'];
            $actDays  = $act['days'];
            $amount   = $act['price'] * $totalQty;
            $lineItems[] = [
                'description' => $act['name'],
                'unit_price'  => $act['price'],
                'quantity_str'=> $totalQty . ' Guest' . ($totalQty !== 1 ? 's' : '') . ($actDays > 1 ? ' × ' . $actDays . ' Days' : ''),
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
            $isPerPax  = $ot['per_pax'];
            $totalQty  = $isPerPax ? $ot['qty'] * $pax : $ot['qty'];
            $amount    = $ot['unit_price'] * $totalQty;
            $unitLabel = $ot['unit_name'] !== '' ? $ot['unit_name'] : ('Unit' . ($ot['qty'] !== 1 ? 's' : ''));

            if ($isPerPax) {
                $qtyStr = $pax . ' Guest' . ($pax !== 1 ? 's' : '');
                if ($ot['qty'] > 1) $qtyStr .= ' × ' . $ot['qty'] . ' ' . $unitLabel;
            } else {
                $qtyStr = $ot['qty'] . ' ' . $unitLabel;
            }

            $lineItems[] = [
                'description' => $ot['name'],
                'unit_price'  => $ot['unit_price'],
                'quantity_str'=> $qtyStr,
                'amount'      => round($amount, 2),
                'category'    => 'other',
            ];
        }

        $hasServiceFee = false;
        foreach ($lineItems as $li) {
            if (($li['category'] ?? '') === 'service_fee'
                || stripos($li['description'], 'service charge') !== false
                || stripos($li['description'], 'service fee') !== false
                || stripos($li['description'], 'guide fee') !== false) {
                $hasServiceFee = true; break;
            }
        }
        if (!$hasServiceFee && $includeDefaultServiceFee) {
            // Infants are excluded from $adults/$children entirely, so
            // they're already never charged here — nothing extra needed
            // to keep them free.
            $adultRate = (float)($serviceFeeRates['adult'] ?? 100);
            $childRate = (float)($serviceFeeRates['child'] ?? 100);
            if ($adults > 0) {
                $lineItems[] = [
                    'description' => 'Service Charge (Adults)',
                    'unit_price'  => $adultRate,
                    'quantity_str'=> $adults . ' Adult' . ($adults !== 1 ? 's' : ''),
                    'amount'      => round($adultRate * $adults, 2),
                    'category'    => 'service_fee',
                ];
            }
            if ($children > 0) {
                $lineItems[] = [
                    'description' => 'Service Charge (Children)',
                    'unit_price'  => $childRate,
                    'quantity_str'=> $children . ' Child' . ($children !== 1 ? 'ren' : ''),
                    'amount'      => round($childRate * $children, 2),
                    'category'    => 'service_fee',
                ];
            }
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
     * Given a set of line items, compute subtotal -> markup -> discount ->
     * VAT -> grand total -> deposit, used throughout the quotation system
     * (admin/quotation_new.php's JS, api/quotation_pdf.php). Deposit
     * percentage comes from the single shared DEPOSIT_PERCENT constant
     * (config/app.php), and VAT from VAT_RATE — both single sources of
     * truth rather than each caller hardcoding its own copy.
     *
     * $markupPct defaults to 0 — a package/quotation prices at exactly
     * raw itemized cost + VAT unless a margin is explicitly requested,
     * same behavior as before this parameter existed. Deciding what
     * markup to actually charge is a business call, not something to
     * apply silently.
     */
    public static function totals(array $lineItems, float $discountPct = 0, float $markupPct = 0): array
    {
        $subtotal   = array_sum(array_column($lineItems, 'amount'));
        $markupAmt  = $subtotal * ($markupPct / 100);
        $marked     = $subtotal + $markupAmt;
        $discAmt    = $marked * ($discountPct / 100);
        $afterDisc  = $marked - $discAmt;
        $vatRate    = defined('VAT_RATE') ? VAT_RATE : 0.18;
        $vat        = round($afterDisc * $vatRate, 2);
        $grandTotal = round($afterDisc + $vat, 2);
        $depositPct = defined('DEPOSIT_PERCENT') ? DEPOSIT_PERCENT : 25;
        $deposit    = round($grandTotal * ($depositPct / 100), 2);

        return [
            'subtotal'    => round($subtotal, 2),
            'markup'      => round($markupAmt, 2),
            'discount'    => round($discAmt, 2),
            'vat'         => $vat,
            'grand_total' => $grandTotal,
            'deposit'     => $deposit,
            'balance'     => round($grandTotal - $deposit, 2),
        ];
    }

    /**
     * The day-by-day itinerary only snapshots a park's adult fee
     * (park_fee_name/park_adult_fee) — child and resident fees are
     * looked up here from the park_fees table instead, so that pricing
     * always reflects the current rate without needing to change the
     * itinerary editor's day-JSON shape or backfill existing itinerary
     * rows. Falls back to all-zero for a free-typed park name with no
     * matching row.
     */
    private static function lookupParkRates(string $parkName): array
    {
        static $cache = [];
        if (array_key_exists($parkName, $cache)) return $cache[$parkName];

        $rates = ['child_fee' => 0.0, 'resident_adult' => 0.0, 'resident_child' => 0.0, 'vehicle_fee' => 0.0];
        try {
            $db   = \App\Models\Database::getInstance();
            $stmt = $db->prepare("SELECT child_fee, resident_adult, resident_child, vehicle_fee FROM park_fees WHERE name = ? LIMIT 1");
            $stmt->execute([$parkName]);
            $row  = $stmt->fetch();
            if ($row) {
                $rates = [
                    'child_fee'      => (float)$row['child_fee'],
                    'resident_adult' => (float)$row['resident_adult'],
                    'resident_child' => (float)$row['resident_child'],
                    'vehicle_fee'    => (float)$row['vehicle_fee'],
                ];
            }
        } catch (\Throwable $e) {
            // No DB context (e.g. a future non-DB caller) — treat as no child/resident/vehicle fee on file.
        }
        return $cache[$parkName] = $rates;
    }

    /**
     * Same snapshot-gap pattern as lookupParkRates() — single_supplement,
     * child_rate, and rate_per_person aren't part of the itinerary day
     * JSON (it only snapshots the one resolved flat rate), so they're
     * looked up by name from accommodations here. rate_per_person is
     * what decides which occupancy model applies in the caller — see
     * the per-person-sharing branch above.
     */
    private static function lookupAccommodationExtras(string $accName): array
    {
        static $cache = [];
        if (array_key_exists($accName, $cache)) return $cache[$accName];

        $extras = ['single_supplement' => 0.0, 'child_rate' => 0.0, 'rate_per_person' => 0.0];
        try {
            $db   = \App\Models\Database::getInstance();
            $stmt = $db->prepare("SELECT single_supplement, child_rate, rate_per_person FROM accommodations WHERE name = ? LIMIT 1");
            $stmt->execute([$accName]);
            $row  = $stmt->fetch();
            if ($row) {
                $extras = [
                    'single_supplement' => (float)$row['single_supplement'],
                    'child_rate'        => (float)$row['child_rate'],
                    'rate_per_person'   => (float)$row['rate_per_person'],
                ];
            }
        } catch (\Throwable $e) {
            // No DB context — no supplement/child rate charged, falls back to flat per-room.
        }
        return $cache[$accName] = $extras;
    }

    /**
     * Same snapshot-gap pattern again — the itinerary day only stores
     * one pre-bundled vehicle rate, not its daily/fuel/driver components,
     * so they're looked up by name from vehicles here to itemize the
     * quote line (see the dbTotal-match check at the call site).
     */
    private static function lookupVehicleComponents(string $vehName): array
    {
        static $cache = [];
        if (array_key_exists($vehName, $cache)) return $cache[$vehName];

        $components = ['daily_rate' => 0.0, 'fuel_cost_day' => 0.0, 'driver_cost_day' => 0.0];
        try {
            $db   = \App\Models\Database::getInstance();
            $stmt = $db->prepare("SELECT daily_rate, fuel_cost_day, driver_cost_day FROM vehicles WHERE name = ? LIMIT 1");
            $stmt->execute([$vehName]);
            $row  = $stmt->fetch();
            if ($row) {
                $components = [
                    'daily_rate'       => (float)$row['daily_rate'],
                    'fuel_cost_day'    => (float)$row['fuel_cost_day'],
                    'driver_cost_day'  => (float)$row['driver_cost_day'],
                ];
            }
        } catch (\Throwable $e) {
            // No DB context — fall back to the single bundled line at the call site.
        }
        return $cache[$vehName] = $components;
    }

    /**
     * Ported from the now-retired App\Services\QuotationEngine, which
     * had this logic tested and correct but zero real callers. Pure
     * date math — no DB, safe as a static helper.
     */
    public static function detectSeason(string $date): string
    {
        $month = (int)date('n', strtotime($date));
        $day   = (int)date('j', strtotime($date));
        if (in_array($month, [6, 7, 8, 9, 10], true)) return 'peak';
        if ($month === 12 && $day >= 20) return 'peak';
        if ($month === 1  && $day <= 5)  return 'peak';
        if (in_array($month, [3, 4, 5], true)) return 'shoulder';
        return 'low';
    }

    private static function decode($value): array
    {
        if (is_array($value)) return $value;
        if (empty($value)) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
