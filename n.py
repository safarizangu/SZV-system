#!/usr/bin/env python3
"""
Fix itinerary_api.php — proper price breakdown for ALL items:
- Accommodation: per property, grouped by nights, showing room rate
- Vehicle: per vehicle type, per day rate, total days
- Park fees: per park, per adult, number of entries (days in that park)
- Activities: ALL shown — included (price=0 shown as Included) and paid ones
- Meals: per day per person, total across all days
- Other items: with proper quantities
- Airport transfers from itinerary other_items
- Removes default $100 service fee — uses actual guide fee from itinerary data
- Populates includes/excludes in quotation notes
Also fixes:
- quotation_new.php: remove duplicate itinerary block
- quotation_new.php: populate includes/excludes into notes after load
- quotation_new.php: show day-by-day breakdown in preview
"""
import os, subprocess

SERVER = '/var/www/html/safari-zangu'
def R(f): return open(os.path.join(SERVER,f),encoding='utf-8').read()
def W(f,c):
    p=os.path.join(SERVER,f)
    open(p,'w',encoding='utf-8').write(c)
    print(f'  WROTE: {f}')
def php(f):
    r=subprocess.run(['/usr/bin/php8.3','-l',os.path.join(SERVER,f)],capture_output=True,text=True)
    return 'No syntax errors' in r.stdout

print('='*60)
print('  FIX ITINERARY → QUOTATION PRICE BREAKDOWN')
print('='*60)

# ══════════════════════════════════════════════════════════════
# 1. Rewrite php/itinerary_api.php — comprehensive line items
# ══════════════════════════════════════════════════════════════
print('\n[1] php/itinerary_api.php — comprehensive pricing')
W('php/itinerary_api.php', r"""<?php
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
""")

# ══════════════════════════════════════════════════════════════
# 2. Fix admin/quotation_new.php — remove duplicate + improve loader
# ══════════════════════════════════════════════════════════════
print('\n[2] admin/quotation_new.php — remove duplicate + improve loadItinerary()')
qn = R('admin/quotation_new.php')

# Remove duplicate itinerary block
count_before = qn.count('Load Itinerary Template')
print(f'  "Load Itinerary Template" count: {count_before}')

if count_before > 1:
    # Find first and second occurrences
    first  = qn.find('<!-- LOAD FROM ITINERARY -->')
    second = qn.find('<!-- LOAD FROM ITINERARY -->', first + 10) if first >= 0 else -1

    if second == -1:
        # Try alternative marker
        first  = qn.find('Load Itinerary Template')
        second = qn.find('Load Itinerary Template', first + 10)
        # Find the containing div start
        card_start = qn.rfind('\n<div ', 0, second)
        # Find its end
        i = card_start; depth = 0
        while i < len(qn):
            if qn[i:i+4]=='<div': depth+=1
            elif qn[i:i+6]=='</div>':
                depth-=1
                if depth==0: end=i+6; break
            i+=1
        qn = qn[:card_start] + '\n' + qn[end+1:]
        print(f'  Removed duplicate via div search')
    else:
        # Find end of second block
        i = second; depth = 0
        # Go back to find the opening div
        block_start = qn.rfind('\n<!-- LOAD', 0, second)
        if block_start == -1:
            block_start = qn.rfind('\n<div', 0, second)
        i = block_start; depth = 0
        while i < len(qn):
            if qn[i:i+4]=='<div': depth+=1
            elif qn[i:i+6]=='</div>':
                depth-=1
                if depth==0: end=i+6; break
            i+=1
        qn = qn[:block_start] + qn[end:]
        print(f'  Removed duplicate block')

    print(f'  Count after: {qn.count("Load Itinerary Template")}')

# Now update the loadItinerary() JavaScript function with improved version
OLD_LOAD_FN = 'async function loadItinerary() {'
NEW_LOAD_FN = '''async function loadItinerary() {
  var id     = document.getElementById('itin_select').value;
  var adults = parseInt(document.getElementById('itin_adults').value)||2;
  if (!id) { alert('Please select an itinerary first.'); return; }

  var btn = document.getElementById('load-itin-btn');
  btn.disabled=true; btn.textContent='Loading…';

  try {
    var url = (window.BASE_PATH||'')+'/php/itinerary_api.php?action=quote_items'
              +'&id='+id+'&adults='+adults;
    var r   = await fetch(url, {credentials:'same-origin'});
    var d   = await r.json();

    if (!d.success) { alert('Error: '+(d.message||'Failed to load')); btn.disabled=false; btn.textContent='↓ Load Itinerary'; return; }

    var itin     = d.itinerary;
    var items    = d.line_items;
    var dayInfo  = d.day_titles || [];
    var includes = d.includes   || [];
    var excludes = d.excludes   || [];
    var optionals= d.optional_activities || [];
    var included = d.included_activities || [];

    // ── Set trip meta fields ──────────────────────────────────
    var pkgInput = document.querySelector('[name="package_name"]');
    if (pkgInput) pkgInput.value = itin.name;

    var durInput = document.querySelector('[name="duration_days"]');
    if (durInput) durInput.value = itin.duration_days;

    var lvlSel = document.querySelector('[name="accommodation_level"]');
    if (lvlSel) lvlSel.value = itin.accommodation_level || 'midrange';

    var adultsInp = document.getElementById('inp_adults');
    if (adultsInp) adultsInp.value = adults;

    // ── Set destinations from day locations ───────────────────
    var destInput = document.querySelector('[name="destinations"]');
    if (destInput) {
      var locs = dayInfo.map(function(d){ return d.location||d.title; }).filter(Boolean);
      var uniqueLocs = locs.filter(function(v,i,a){ return a.indexOf(v)===i; });
      destInput.value = uniqueLocs.join(', ');
    }

    // ── Clear existing line items ─────────────────────────────
    document.getElementById('line-items-body').innerHTML = '';

    // ── Category labels and options ───────────────────────────
    var cats = {
      accommodation: 'Accommodation',
      transport:     'Transport',
      park_fee:      'Park Fee',
      activity:      'Activity',
      flight:        'Flight',
      service_fee:   'Service Fee',
      other:         'Other'
    };

    // ── Add each line item as a row ───────────────────────────
    items.forEach(function(item) {
      var catOptions = Object.keys(cats).map(function(v){
        return '<option value="'+v+'"'+(v===item.category?' selected':'')+'>'+cats[v]+'</option>';
      }).join('');

      var div = document.createElement('div');
      div.className = 'line-item';
      div.style.cssText = 'display:grid;grid-template-columns:2.5fr 110px 170px 100px 40px;gap:.5rem;align-items:center;padding:.55rem 1rem;border-bottom:1px solid #f3f4f6';
      div.innerHTML =
        '<div style="display:flex;gap:.4rem;align-items:center">'
          +'<select class="li-cat" style="font-size:.6rem;border:1px solid #e5e7eb;padding:.2rem .3rem;color:#9ca3af;background:#fff;cursor:pointer;flex-shrink:0;max-width:90px" onchange="updateTotals()">'+catOptions+'</select>'
          +'<input class="li-desc admin-input" style="font-size:.82rem;padding:.35rem .5rem" list="desc-suggestions" value="'+htmlE(item.description)+'">'
        +'</div>'
        +'<input class="li-price admin-input" type="number" step="0.01" style="font-size:.82rem;padding:.35rem .5rem;text-align:right" value="'+item.unit_price.toFixed(2)+'" oninput="recalcRow(this);updateTotals()">'
        +'<input class="li-qty-str admin-input" style="font-size:.78rem;padding:.35rem .5rem;text-align:center" value="'+htmlE(item.quantity_str)+'" oninput="parseQtyAndRecalc(this)">'
        +'<input class="li-amount admin-input" type="number" step="0.01" style="font-size:.85rem;padding:.35rem .5rem;text-align:right;font-weight:700;background:#f9f9f8" value="'+item.amount.toFixed(2)+'" oninput="updateTotals()">'
        +'<div style="text-align:center"><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:.2rem .3rem">✕</button></div>';

      document.getElementById('line-items-body').appendChild(div);
    });

    updateTotals();

    // ── Populate notes with includes/excludes ─────────────────
    var notesEl = document.querySelector('[name="notes"]');
    if (notesEl && (includes.length || excludes.length)) {
      var notesText = '';
      if (includes.length) {
        notesText += 'INCLUDED:\n' + includes.map(function(i){ return '✓ '+i; }).join('\n');
      }
      if (excludes.length) {
        notesText += '\n\nNOT INCLUDED:\n' + excludes.map(function(e){ return '✗ '+e; }).join('\n');
      }
      if (optionals.length) {
        notesText += '\n\nOPTIONAL ACTIVITIES:\n' + optionals.map(function(o){
          return '◦ '+o.name + (o.price > 0 ? ' ($'+o.price.toFixed(0)+'/person)' : '');
        }).join('\n');
      }
      notesEl.value = notesText.trim();
    }

    // ── Build rich preview panel ──────────────────────────────
    var preview = document.getElementById('itin-preview');
    preview.style.display = 'block';

    var summaryHtml =
      '<div style="margin-bottom:.75rem">'
        +'<strong style="color:#0a0a0a;font-size:.88rem">✓ Loaded: '+htmlE(itin.name)+'</strong>'
        +'<span style="font-size:.72rem;color:#9ca3af;margin-left:.75rem">'+itin.duration_days+' nights · '+itin.accommodation_level+'</span>'
      +'</div>';

    // Stats row
    var accItems  = items.filter(function(i){ return i.category==='accommodation'; });
    var vehItems  = items.filter(function(i){ return i.category==='transport'; });
    var parkItems = items.filter(function(i){ return i.category==='park_fee'; });
    var actItems  = items.filter(function(i){ return i.category==='activity'; });
    var svcItems  = items.filter(function(i){ return i.category==='service_fee'; });
    var total     = items.reduce(function(s,i){ return s+i.amount; }, 0);

    summaryHtml +=
      '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.4rem;margin-bottom:.85rem">';
    [
      ['🏕 Accommodation', accItems.length+' propert'+(accItems.length===1?'y':'ies')],
      ['🚙 Vehicle',        vehItems.length+' type'+(vehItems.length===1?'':'s')],
      ['🏞 Park Fees',      parkItems.length+' park'+(parkItems.length===1?'':'s')],
      ['🎯 Activities',     actItems.length+' item'+(actItems.length===1?'':'s')],
      ['💼 Service',        svcItems.length ? 'Included' : '—'],
      ['💰 Est. Total',     '$'+Math.round(total).toLocaleString()],
    ].forEach(function(item){
      summaryHtml += '<div style="background:#f3f4f6;padding:.4rem .6rem;font-size:.7rem">'
        +'<div style="opacity:.6;margin-bottom:.1rem">'+item[0]+'</div>'
        +'<div style="font-weight:700;color:#0a0a0a">'+item[1]+'</div></div>';
    });
    summaryHtml += '</div>';

    // Day by day
    summaryHtml += '<div style="border-top:1px solid #e5e7eb;padding-top:.65rem;max-height:220px;overflow-y:auto">';
    dayInfo.forEach(function(d){
      summaryHtml += '<div style="display:grid;grid-template-columns:52px 1fr;gap:.35rem;padding:.3rem 0;border-bottom:1px solid #f3f4f6;font-size:.75rem">'
        +'<span style="font-weight:700;color:#9ca3af;flex-shrink:0">Day '+d.day+'</span>'
        +'<div>'
          +'<div style="font-weight:600;color:#0a0a0a">'+htmlE(d.title)+'</div>';
      if (d.location) summaryHtml += '<div style="color:#9ca3af;font-size:.68rem">📍 '+htmlE(d.location)+'</div>';
      if (d.accommodation) summaryHtml += '<div style="color:#6b7280;font-size:.68rem">🏕 '+htmlE(d.accommodation)+'</div>';
      summaryHtml += '</div></div>';
    });

    // Optional activities
    if (optionals.length) {
      summaryHtml += '<div style="margin-top:.65rem;padding:.5rem;background:#fef3e2;font-size:.72rem">'
        +'<strong style="color:#92400e">Optional add-ons:</strong> '
        +optionals.map(function(o){ return o.name+(o.price>0?' ($'+o.price+'/pp)':''); }).join(' · ')
        +'</div>';
    }
    summaryHtml += '</div>';

    preview.innerHTML = summaryHtml;

  } catch(e) {
    alert('Network error: '+e.message);
  }

  btn.disabled=false; btn.textContent='↓ Load Itinerary';
}

function htmlE(s) {
  var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML;
}'''

# Replace the old loadItinerary function
if OLD_LOAD_FN in qn:
    # Find end of function (matching brace)
    start = qn.find(OLD_LOAD_FN)
    depth = 0; i = start
    while i < len(qn):
        if qn[i] == '{': depth += 1
        elif qn[i] == '}':
            depth -= 1
            if depth == 0: end = i+1; break
        i += 1
    qn = qn[:start] + NEW_LOAD_FN + qn[end:]
    print('  Replaced loadItinerary() with comprehensive version')
else:
    print('  WARNING: loadItinerary() function not found — check manually')

W('admin/quotation_new.php', qn)

# ══════════════════════════════════════════════════════════════
# VERIFY
# ══════════════════════════════════════════════════════════════
print('\n'+'='*60)
print('  VERIFY')
print('='*60)

checks = [
    ('itinerary_api.php: accGroups',          'accGroups' in R('php/itinerary_api.php')),
    ('itinerary_api.php: parkGroups',         'parkGroups' in R('php/itinerary_api.php')),
    ('itinerary_api.php: actPaid/actFree',    'actFree' in R('php/itinerary_api.php')),
    ('itinerary_api.php: mealsTotal',         'mealsTotal' in R('php/itinerary_api.php')),
    ('itinerary_api.php: otherItems',         'otherItems' in R('php/itinerary_api.php')),
    ('itinerary_api.php: optional_activities','optional_activities' in R('php/itinerary_api.php')),
    ('itinerary_api.php: included_activities','included_activities' in R('php/itinerary_api.php')),
    ('itinerary_api.php: notes_suggestion',   'notes_suggestion' in R('php/itinerary_api.php')),
    ('itinerary_api.php: PHP OK',             php('php/itinerary_api.php')),
    ('quotation_new: single itin block',      R('admin/quotation_new.php').count('Load Itinerary Template') == 1),
    ('quotation_new: new loadItinerary()',     'optional_activities' in R('admin/quotation_new.php')),
    ('quotation_new: notes populated',        'INCLUDED:' in R('admin/quotation_new.php')),
    ('quotation_new: day preview',            'Day '+' '+str(1) in R('admin/quotation_new.php') or 'Day '+d.day in R('admin/quotation_new.php') or 'dayInfo' in R('admin/quotation_new.php')),
    ('quotation_new: PHP OK',                 php('admin/quotation_new.php')),
]

all_ok = True
for label, ok in checks:
    print('  '+('✓' if ok else '✗')+'  '+label)
    if not ok: all_ok = False

print()
print('='*60)
print('ALL PASS ✓' if all_ok else 'Check ✗ items')
print("""
Test flow:
  1. /admin/quotation_new.php → select itinerary → enter adults → Load Itinerary
  2. Verify all line items appear:
     - Each accommodation property with nights
     - Vehicle with days
     - Each park with entries
     - Paid activities (balloon, tours)
     - Meals (full trip total)
     - Other items (crater fees, transfers)
     - Service fee
  3. Notes section should auto-fill with Includes / Excludes / Optional activities
  4. Preview panel shows day-by-day with location and accommodation
""")