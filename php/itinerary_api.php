<?php
ob_start();
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
header('Content-Type: application/json');

function irespond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

$db     = \App\Models\Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── LIST ──────────────────────────────────────────────────────
if ($action === 'list') {
    try {
        $rows = $db->query("SELECT id,name,slug,duration_days,accommodation_level,
                            base_price_pax,is_active FROM itineraries
                            WHERE is_active=1 ORDER BY sort_order,name")->fetchAll();
        irespond(['success'=>true,'data'=>$rows]);
    } catch(\Exception $e) { irespond(['success'=>false,'message'=>$e->getMessage()]); }
}

// ── GET SINGLE ────────────────────────────────────────────────
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    try {
        $s = $db->prepare("SELECT * FROM itineraries WHERE id=?");
        $s->execute([$id]);
        $itin = $s->fetch();
        if (!$itin) irespond(['success'=>false,'message'=>'Not found']);
        $itin['days']     = json_decode($itin['days']     ?? '[]', true) ?: [];
        $itin['includes'] = json_decode($itin['includes'] ?? '[]', true) ?: [];
        $itin['excludes'] = json_decode($itin['excludes'] ?? '[]', true) ?: [];
        irespond(['success'=>true,'data'=>$itin]);
    } catch(\Exception $e) { irespond(['success'=>false,'message'=>$e->getMessage()]); }
}

// ── GENERATE QUOTATION LINE ITEMS ─────────────────────────────
if ($action === 'quote_items') {
    $id       = (int)($_GET['id']       ?? 0);
    $adults   = max(1,(int)($_GET['adults']   ?? 2));
    $children = max(0,(int)($_GET['children'] ?? 0));
    $pax      = $adults + $children;

    try {
        $s = $db->prepare("SELECT * FROM itineraries WHERE id=?");
        $s->execute([$id]);
        $itin = $s->fetch();
        if (!$itin) irespond(['success'=>false,'message'=>'Itinerary not found']);

        $days     = json_decode($itin['days']     ?? '[]', true) ?: [];
        $includes = json_decode($itin['includes'] ?? '[]', true) ?: [];
        $excludes = json_decode($itin['excludes'] ?? '[]', true) ?: [];
        $dur      = count($days);

        // ── Step 1: Aggregate resources across all days ───────
        $accGroups  = []; // accommodation_name → {rate, rooms, nights, locations}
        $vehGroups  = []; // vehicle_name → {rate, days}
        $parkGroups = []; // park_name → {fee, entries, adults}
        $actPaid    = []; // paid activities (price_pp > 0)
        $actFree    = []; // included activities (price_pp = 0, shown as $0)
        $mealsTotal = 0;  // total meals cost across all days (per person)
        $mealsDays  = 0;  // days with meals
        $otherItems = []; // misc other_items

        foreach ($days as $day) {
            $dayNum = $day['day'] ?? '?';

            // Accommodation
            $accName = trim($day['accommodation_name'] ?? '');
            $accRate = (float)($day['accommodation_rate'] ?? 0);
            if ($accName && $accRate > 0) {
                $key = $accName;
                if (!isset($accGroups[$key])) {
                    $accGroups[$key] = [
                        'name'  => $accName,
                        'rate'  => $accRate,
                        'rooms' => (int)($day['accommodation_rooms'] ?? 1),
                        'nights'=> 0,
                        'days'  => [],
                    ];
                }
                $accGroups[$key]['nights']++;
                $accGroups[$key]['days'][] = $dayNum;
            }

            // Vehicle
            $vehName = trim($day['vehicle_name'] ?? '');
            $vehRate = (float)($day['vehicle_rate'] ?? 0);
            if ($vehName && $vehRate > 0) {
                $key = $vehName;
                if (!isset($vehGroups[$key])) {
                    $vehGroups[$key] = ['name'=>$vehName,'rate'=>$vehRate,'days'=>0];
                }
                $vehGroups[$key]['days']++;
            }

            // Park fees
            $parkName = trim($day['park_fee_name'] ?? '');
            $parkFee  = (float)($day['park_adult_fee'] ?? 0);
            if ($parkName && $parkFee > 0) {
                $key = $parkName;
                if (!isset($parkGroups[$key])) {
                    $parkGroups[$key] = ['name'=>$parkName,'fee'=>$parkFee,'entries'=>0,'adults'=>$adults];
                }
                $parkGroups[$key]['entries']++;
            }

            // Activities — separate paid vs free
            foreach ($day['activities'] ?? [] as $act) {
                $actName  = trim($act['name'] ?? '');
                if (!$actName) continue;
                $price    = (float)($act['price_pp'] ?? 0);
                $type     = $act['type'] ?? 'included';

                if ($type === 'optional') continue; // Skip optional — don't add to quote

                if ($price > 0) {
                    $actPaid[] = [
                        'name'  => $actName,
                        'price' => $price,
                        'day'   => $dayNum,
                    ];
                } else {
                    // Included activity at no extra charge
                    $actFree[] = [
                        'name' => $actName,
                        'day'  => $dayNum,
                    ];
                }
            }

            // Meals
            $meals = (float)($day['meals_cost_pp'] ?? 0);
            if ($meals > 0) {
                $mealsTotal += $meals;
                $mealsDays++;
            }

            // Other items
            foreach ($day['other_items'] ?? [] as $ot) {
                $otName  = trim($ot['name'] ?? '');
                $otAmt   = (float)($ot['amount'] ?? 0);
                if (!$otName || $otAmt <= 0) continue;

                // Check if already exists (consolidate duplicates e.g. crater service fee)
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
                    $otherItems[] = ['name'=>$otName,'amount'=>$otAmt,'count'=>1];
                }
            }
        }

        // ── Step 2: Build line items ──────────────────────────
        $lineItems = [];

        // A. ACCOMMODATIONS — one row per unique property
        foreach ($accGroups as $acc) {
            $n      = $acc['nights'];
            $r      = $acc['rooms'];
            $amount = $acc['rate'] * $n * $r;

            if ($r > 1) {
                $qtyStr = $r.' Rooms × '.$n.' Night'.($n!==1?'s':'');
            } else {
                $qtyStr = $n.' Night'.($n!==1?'s':'');
            }

            $lineItems[] = [
                'description'  => 'Accommodation – '.$acc['name'],
                'unit_price'   => $acc['rate'],
                'quantity_str' => $qtyStr,
                'amount'       => round($amount, 2),
                'category'     => 'accommodation',
            ];
        }

        // B. VEHICLES — one row per unique vehicle
        foreach ($vehGroups as $veh) {
            $amount = $veh['rate'] * $veh['days'];
            $lineItems[] = [
                'description'  => $veh['name'].' & Fuel',
                'unit_price'   => $veh['rate'],
                'quantity_str' => $veh['days'].' Day'.($veh['days']!==1?'s':''),
                'amount'       => round($amount, 2),
                'category'     => 'transport',
            ];
        }

        // C. PARK FEES — one row per park
        foreach ($parkGroups as $park) {
            $amount = $park['fee'] * $park['adults'] * $park['entries'];
            // If multiple entries in same park, show entries count
            if ($park['entries'] > 1) {
                $qtyStr = $park['adults'].' Guests × '.$park['entries'].' Days';
            } else {
                $qtyStr = $park['adults'].' Guest'.($park['adults']!==1?'s':'');
            }
            $lineItems[] = [
                'description'  => $park['name'].' Entry Fees',
                'unit_price'   => $park['fee'],
                'quantity_str' => $qtyStr,
                'amount'       => round($amount, 2),
                'category'     => 'park_fee',
            ];
        }

        // D. PAID ACTIVITIES (balloon, tours, etc.)
        // Consolidate same activity if it appears on multiple days
        $actConsolidated = [];
        foreach ($actPaid as $act) {
            $key = $act['name'];
            if (!isset($actConsolidated[$key])) {
                $actConsolidated[$key] = ['name'=>$act['name'],'price'=>$act['price'],'count'=>0];
            }
            $actConsolidated[$key]['count']++;
        }
        foreach ($actConsolidated as $act) {
            $count  = $act['count'];
            $amount = $act['price'] * $pax * $count;
            $lineItems[] = [
                'description'  => $act['name'],
                'unit_price'   => $act['price'],
                'quantity_str' => $pax.' Guest'.($pax!==1?'s':'').($count>1?' × '.$count.' Days':''),
                'amount'       => round($amount, 2),
                'category'     => 'activity',
            ];
        }

        // E. MEALS — consolidated, full trip
        if ($mealsTotal > 0) {
            $avgMealPP = round($mealsTotal / max(1,$mealsDays), 2);
            $totalMealsAmt = round($mealsTotal * $pax, 2);
            $lineItems[] = [
                'description'  => 'Meals (Full Board — '.$mealsDays.' day'.($mealsDays!==1?'s':'').')',
                'unit_price'   => $avgMealPP,
                'quantity_str' => $pax.' Guests × '.$mealsDays.' Days',
                'amount'       => $totalMealsAmt,
                'category'     => 'activity',
            ];
        }

        // F. OTHER ITEMS (crater fees, transfers, etc.)
        foreach ($otherItems as $ot) {
            $amount = $ot['amount'];
            // Detect per-person items (transfers, water) vs flat fees
            $isPerPax = stripos($ot['name'],'transfer') !== false ||
                        stripos($ot['name'],'water') !== false ||
                        stripos($ot['name'],'contribution') !== false;

            if ($isPerPax && $pax > 1) {
                $amount = $ot['amount'] * $pax;
                $qtyStr = $pax.' Guests';
                if ($ot['count'] > 1) $qtyStr .= ' × '.$ot['count'].' Days';
            } else {
                $qtyStr = $ot['count'] > 1 ? $ot['count'].' Times' : '1';
            }

            $lineItems[] = [
                'description'  => $ot['name'],
                'unit_price'   => $ot['amount'],
                'quantity_str' => $qtyStr,
                'amount'       => round($amount, 2),
                'category'     => 'other',
            ];
        }

        // G. PROFESSIONAL GUIDE & SERVICE FEE
        // Check if already in other_items (some itineraries include it)
        $hasServiceFee = false;
        foreach ($lineItems as $li) {
            if (stripos($li['description'],'service fee') !== false ||
                stripos($li['description'],'guide fee') !== false) {
                $hasServiceFee = true; break;
            }
        }
        if (!$hasServiceFee) {
            $svcRate = 100; // USD per guest — industry standard
            $lineItems[] = [
                'description'  => 'Professional Driver Guide & Service Fee',
                'unit_price'   => $svcRate,
                'quantity_str' => $pax.' Guest'.($pax!==1?'s':''),
                'amount'       => round($svcRate * $pax, 2),
                'category'     => 'service_fee',
            ];
        }

        // ── Step 3: Build included activities note ────────────
        $includedActivitiesNote = '';
        if (!empty($actFree)) {
            $unique = array_unique(array_column($actFree, 'name'));
            $includedActivitiesNote = 'Included activities: '.implode(', ', $unique);
        }

        // ── Step 4: Build day titles for preview ──────────────
        $dayTitles = array_map(function($d) {
            return [
                'day'         => $d['day'],
                'title'       => $d['title']       ?? '',
                'description' => $d['description'] ?? '',
                'location'    => $d['location']    ?? '',
                'accommodation' => $d['accommodation_name'] ?? '',
                'vehicle'       => $d['vehicle_name']       ?? '',
                'park'          => $d['park_fee_name']       ?? '',
            ];
        }, $days);

        // ── Step 5: Build optional activities list ────────────
        $optionalActivities = [];
        foreach ($days as $day) {
            foreach ($day['activities'] ?? [] as $act) {
                if (($act['type']??'included') === 'optional' && !empty($act['name'])) {
                    $price = (float)($act['price_pp'] ?? 0);
                    $optionalActivities[] = [
                        'name'  => $act['name'],
                        'price' => $price,
                        'day'   => $day['day'],
                    ];
                }
            }
        }

        irespond([
            'success'     => true,
            'itinerary'   => [
                'name'                 => $itin['name'],
                'duration_days'        => $itin['duration_days'],
                'overview'             => $itin['overview'],
                'accommodation_level'  => $itin['accommodation_level'],
                'base_price_pax'       => (float)$itin['base_price_pax'],
            ],
            'line_items'           => $lineItems,
            'includes'             => $includes,
            'excludes'             => $excludes,
            'day_titles'           => $dayTitles,
            'optional_activities'  => $optionalActivities,
            'included_activities'  => array_values(array_unique(array_column($actFree,'name'))),
            'notes_suggestion'     => $includedActivitiesNote,
            'pax'                  => $pax,
            'adults'               => $adults,
            'children'             => $children,
        ]);

    } catch(\Exception $e) {
        irespond(['success'=>false,'message'=>$e->getMessage()]);
    }
}

irespond(['success'=>false,'message'=>'Unknown action: '.$action]);
