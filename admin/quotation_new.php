<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

$q    = null;
$id   = (int)($_GET['id']           ?? 0);
$msg  = htmlspecialchars($_GET['msg'] ?? '');
$crId = (int)($_GET['from_request'] ?? 0);

// Load existing quote
if ($id > 0) {
    try { $s=$db->prepare("SELECT * FROM quotations WHERE id=?"); $s->execute([$id]); $q=$s->fetch(); }
    catch(Exception $e){}
}

// Pre-fill from custom_safari_request
$cr = null;
if ($crId > 0) {
    try { $s=$db->prepare("SELECT * FROM custom_safari_requests WHERE id=?"); $s->execute([$crId]); $cr=$s->fetch(); }
    catch(Exception $e){}
}

// ── Pre-fill from existing booking ────────────────────────────
$fromBookingRef = trim($_GET['from_booking'] ?? '');
$bkPrefill = null;
if ($fromBookingRef) {
    try {
        $bks = $db->prepare("
            SELECT b.*,
                   i.id as itin_id, i.name as itin_name, i.days as itin_days,
                   p.itinerary_id as pkg_itin_id
            FROM bookings b
            LEFT JOIN packages p ON p.name = b.package_name AND p.is_active=1
            LEFT JOIN itineraries i ON i.id = p.itinerary_id
            WHERE b.booking_ref = ? LIMIT 1
        ");
        $bks->execute([$fromBookingRef]);
        $bkPrefill = $bks->fetch() ?: null;
    } catch(Exception $e) { error_log('[quotation_new] from_booking: '.$e->getMessage()); }
}

// Pre-fill from existing booking
$fromBookingRef = trim($_GET['from_booking'] ?? '');
$bkPrefill = null;
if ($fromBookingRef) {
    try {
        $bks = $db->prepare("SELECT * FROM bookings WHERE booking_ref=? LIMIT 1");
        $bks->execute([$fromBookingRef]);
        $bkPrefill = $bks->fetch() ?: null;
    } catch(Exception $e) { error_log($e->getMessage()); }
}

// Reference data
function fetchAll($db,$sql){try{return $db->query($sql)->fetchAll();}catch(Exception $e){return[];}}
$packages      = fetchAll($db,"SELECT id,name,slug FROM packages WHERE is_active=1 ORDER BY sort_order");
$vehicles      = fetchAll($db,"SELECT id,name,daily_rate,fuel_cost_day,driver_cost_day,capacity FROM vehicles WHERE is_active=1 ORDER BY name");
$accommodations= fetchAll($db,"SELECT id,name,level,rate_per_room,rate_per_person FROM accommodations WHERE is_active=1 ORDER BY level,name");
$parkFees      = fetchAll($db,"SELECT id,name,adult_fee,child_fee,vehicle_fee FROM park_fees WHERE is_active=1 ORDER BY name");
$activitiesDB  = fetchAll($db,"SELECT id,name,price,price_unit FROM activities WHERE is_active=1 ORDER BY name");

// ── SAVE ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quote'])) {

    // Line items from builder (JSON posted by JS)
    $lineItemsJson = $_POST['line_items_json'] ?? '[]';
    $lineItems     = json_decode($lineItemsJson, true) ?: [];

    // Recalculate totals SERVER-SIDE from line items
    $subtotal = 0;
    foreach ($lineItems as $item) {
        $subtotal += (float)($item['amount'] ?? 0);
    }
    $vatRate    = 0.18;
    $vat        = round($subtotal * $vatRate, 2);
    $grandTotal = round($subtotal + $vat, 2);

    $adults    = max(1,(int)($_POST['adults']   ?? 2));
    $children  = max(0,(int)($_POST['children'] ?? 0));
    $totalPax  = $adults + $children;
    $perPerson = $totalPax > 0 ? round($grandTotal / $totalPax, 2) : $grandTotal;
    $deposit   = round($grandTotal * 0.30, 2);
    $balance   = round($grandTotal - $deposit, 2);

    // Categorise totals for DB columns
    $accTotal     = 0; $vehicleTotal = 0; $parkTotal = 0;
    $actTotal     = 0; $flightCost   = 0; $svcFee   = 0;
    foreach ($lineItems as $item) {
        $cat = $item['category'] ?? 'other';
        $amt = (float)($item['amount'] ?? 0);
        match($cat) {
            'accommodation' => $accTotal     += $amt,
            'transport'     => $vehicleTotal += $amt,
            'park_fee'      => $parkTotal    += $amt,
            'activity'      => $actTotal     += $amt,
            'flight'        => $flightCost   += $amt,
            'service_fee'   => $svcFee       += $amt,
            default         => null,
        };
    }

    $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : date('Y-m-d', strtotime('+14 days'));
    $destinations= json_encode(array_values(array_filter(array_map('trim', explode(',', $_POST['destinations']??'')))));

    // Resolve package_id from the typed package name so downstream conversion
    // to a booking doesn't have to re-guess it by name matching.
    $postedPkgName = trim($_POST['package_name'] ?? '');
    $pkgId = null;
    if ($postedPkgName !== '') {
        try {
            $pkgLookup = $db->prepare("SELECT id FROM packages WHERE name=? LIMIT 1");
            $pkgLookup->execute([$postedPkgName]);
            $pkgRow = $pkgLookup->fetch();
            if ($pkgRow) $pkgId = (int)$pkgRow['id'];
        } catch(Exception $e) {}
    }

    // Resolve (or create) the unified customer record for this client.
    $clientName    = trim($_POST['client_name']);
    $clientEmail   = trim($_POST['client_email']);
    $clientPhone   = trim($_POST['client_phone'] ?? '');
    $clientCountry = trim($_POST['client_country'] ?? '');
    $customerId = null;
    try {
        $customerId = (new \App\Models\Customer())->findOrCreateByEmail($clientEmail, $clientName, $clientPhone, $clientCountry);
    } catch (Exception $e) { error_log('[quotation_new Customer] '.$e->getMessage()); }

    $data = [
        'customer_id'         => $customerId,
        'client_name'         => $clientName,
        'client_email'        => $clientEmail,
        'client_phone'        => $clientPhone,
        'client_country'      => $clientCountry,
        'adults'              => $adults,
        'children'            => $children,
        'infants'             => max(0,(int)($_POST['infants']??0)),
        'duration_days'       => max(1,(int)($_POST['duration_days']??5)),
        'start_date'          => $_POST['start_date'] ?: null,
        'destinations'        => $destinations,
        'accommodation_level' => $_POST['accommodation_level'] ?? 'midrange',
        'meal_plan'           => $_POST['meal_plan']           ?? 'fb',
        'guide_language'      => $_POST['guide_language']      ?? 'english',
        'package_id'          => $pkgId,
        'package_name'        => $postedPkgName,
        'notes'               => trim($_POST['notes']          ?? ''),
        'valid_until'         => $validUntil,
        'status'              => $_POST['status']              ?? 'draft',
        'line_items'          => $lineItemsJson,
        'accommodation_total' => $accTotal,
        'vehicle_total'       => $vehicleTotal,
        'park_fees_total'     => $parkTotal,
        'activities_total'    => $actTotal,
        'flight_cost'         => $flightCost,
        'service_charge'      => $svcFee,
        'discount_pct'        => (float)($_POST['discount_pct'] ?? 0),
        'itinerary_id'        => (int)($_POST['itinerary_id'] ?? 0) ?: null,
        'discount_amount'     => 0,
        'subtotal'            => $subtotal,
        'vat'                 => $vat,
        'grand_total'         => $grandTotal,
        'per_person'          => $perPerson,
        'deposit_amount'      => $deposit,
        'balance_due'         => $balance,
    ];

    try {
        if ($id > 0) {
            $set = implode(',', array_map(fn($k)=>"`$k`=?", array_keys($data)));
            $db->prepare("UPDATE quotations SET $set, updated_at=NOW() WHERE id=?")
               ->execute(array_merge(array_values($data), [$id]));
            $newId = $id;
        } else {
            $ref = 'SZV-Q' . strtoupper(substr(md5(uniqid()), 0, 6));
            $data['quote_ref'] = $ref;
            if ($crId) $data['custom_request_id'] = $crId;
            $cols = implode(',', array_map(fn($k)=>"`$k`", array_keys($data)));
            $phld = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO quotations ($cols,created_at) VALUES ($phld,NOW())")->execute(array_values($data));
            $newId = $db->lastInsertId();
        }
        header("Location: quotation_new.php?id=$newId&msg=".urlencode('Quotation saved.')); exit;
    } catch(Exception $e) { $msg = 'Error: '.$e->getMessage(); }
}

// Parse existing line items
$existingItems = $q ? (json_decode($q['line_items']??'[]',true)?:[]) : [];
$qAdults   = (int)($q['adults']   ?? $cr['adults']   ?? 2);
$qChildren = (int)($q['children'] ?? $cr['children'] ?? 0);
$qDuration = (int)($q['duration_days'] ?? $cr['duration_days'] ?? 5);

// Default line items for new quotation
$defaultItems = empty($existingItems) ? [
    ['description'=>'Accommodation – Arusha',         'unit_price'=>65,  'quantity_num'=>1, 'quantity_str'=>'1 Room × 1 Night',  'amount'=>65,   'category'=>'accommodation'],
    ['description'=>'Safari Vehicle & Fuel',           'unit_price'=>280, 'quantity_num'=>$qDuration, 'quantity_str'=>$qDuration.' Days','amount'=>280*$qDuration,'category'=>'transport'],
    ['description'=>'Park Entry Fees',                 'unit_price'=>59,  'quantity_num'=>$qAdults, 'quantity_str'=>$qAdults.' Guests','amount'=>59*$qAdults,'category'=>'park_fee'],
    ['description'=>'Meals',                           'unit_price'=>20,  'quantity_num'=>($qAdults+$qChildren)*$qDuration,'quantity_str'=>($qAdults+$qChildren).' Guests × '.$qDuration.' Days','amount'=>20*($qAdults+$qChildren)*$qDuration,'category'=>'activity'],
    ['description'=>'Airport Transfers',               'unit_price'=>50,  'quantity_num'=>2, 'quantity_str'=>'2 Transfers',         'amount'=>100,  'category'=>'other'],
    ['description'=>'Professional Driver Guide & Service Fee','unit_price'=>100,'quantity_num'=>$qAdults+$qChildren,'quantity_str'=>($qAdults+$qChildren).' Guests','amount'=>100*($qAdults+$qChildren),'category'=>'service_fee'],
] : $existingItems;

// VAT rate
$vatRate   = 18;
$depositPct= 30;

$pageTitle = $id ? 'Edit Quotation' : 'New Quotation';
ob_start();
?>
<?php if($msg): ?>
<div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>">
    <?= $msg ?>
    <?php if($id): ?>
    &nbsp;·&nbsp;
    <a href="quotations.php" style="font-weight:700">← All Quotations</a>
    &nbsp;·&nbsp;
    <a href="../api/quotation_email.php?id=<?= $id ?>" style="font-weight:700">Send Email ✉</a>
    &nbsp;·&nbsp;
    <a href="../api/quotation_pdf.php?id=<?= $id ?>" target="_blank" style="font-weight:700">PDF ↗</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div style="display:flex;justify-content:flex-end;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap">
    <?php if($id): ?>
    <a href="../api/quotation_pdf.php?id=<?= $id ?>" target="_blank" class="admin-btn admin-btn--outline">PDF ↗</a>
    <a href="../api/quotation_email.php?id=<?= $id ?>" class="admin-btn admin-btn--primary">✉ Send Email</a>
    <?php endif; ?>
    <a href="quotations.php" class="admin-btn admin-btn--muted">← All Quotations</a>
</div>

<?php if ($bkPrefill): ?>
<div id="booking-prefill-banner" style="background:#eff6ff;border:1.5px solid #93c5fd;padding:.85rem 1.25rem;margin-bottom:1.25rem;font-size:.82rem;color:#1d4ed8;display:flex;justify-content:space-between;align-items:center;border-radius:2px">
  <span>
    <b>Pre-filling from Booking <?= htmlspecialchars($bkPrefill['booking_ref']) ?></b>
    &nbsp;·&nbsp; <?= htmlspecialchars($bkPrefill['package_name'] ?? '') ?>
    &nbsp;·&nbsp; <?= (int)($bkPrefill['adults'] ?? 2) ?> adults
    &nbsp;·&nbsp; <?= $bkPrefill['travel_date'] ? date('d M Y', strtotime($bkPrefill['travel_date'])) : '' ?>
  </span>
  <button onclick="document.getElementById('booking-prefill-banner').remove()" style="background:none;border:none;cursor:pointer;color:#93c5fd;font-size:1rem">✕</button>
</div>
<?php endif; ?>
<form method="POST" id="quote-form">
<input type="hidden" name="save_quote" value="1">
<input type="hidden" name="line_items_json" id="line_items_json" value="<?= htmlspecialchars(json_encode($defaultItems)) ?>">
<input type="hidden" name="itinerary_id" id="itinerary_id" value="<?= (int)($q['itinerary_id'] ?? 0) ?>">

<div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start">

<!-- ════ LEFT ════ -->
<div>

<!-- CLIENT + TRIP INFO -->
<div class="admin-card" style="margin-bottom:1.25rem">
    <div class="admin-card-header"><h3>Client & Trip Details</h3></div>
    <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
            <div><label class="admin-label">Client Name *</label>
                <input name="client_name" class="admin-input" required
                       value="<?= htmlspecialchars($q['client_name']??$cr['full_name']??'') ?>"></div>
            <div><label class="admin-label">Email *</label>
                <input name="client_email" type="email" class="admin-input" required
                       value="<?= htmlspecialchars($q['client_email']??$cr['email']??'') ?>"></div>
            <div><label class="admin-label">Phone / WhatsApp</label>
                <input name="client_phone" class="admin-input"
                       value="<?= htmlspecialchars($q['client_phone']??$cr['phone']??'') ?>"></div>
            <div><label class="admin-label">Country</label>
                <input name="client_country" class="admin-input"
                       value="<?= htmlspecialchars($q['client_country']??$cr['country']??'') ?>"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
            <div><label class="admin-label">Adults</label>
                <input name="adults" type="number" min="1" class="admin-input" id="inp_adults"
                       value="<?= $qAdults ?>" oninput="updateTotals()"></div>
            <div><label class="admin-label">Children</label>
                <input name="children" type="number" min="0" class="admin-input" id="inp_children"
                       value="<?= $qChildren ?>" oninput="updateTotals()"></div>
            <div><label class="admin-label">Infants</label>
                <input name="infants" type="number" min="0" class="admin-input"
                       value="<?= (int)($q['infants']??0) ?>"></div>
            <div><label class="admin-label">Duration (nights)</label>
                <input name="duration_days" type="number" min="1" class="admin-input" id="inp_duration"
                       value="<?= $qDuration ?>" oninput="updateTotals()"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
            <div><label class="admin-label">Package</label>
                <input name="package_name" class="admin-input" list="pkg-list"
                       value="<?= htmlspecialchars($q['package_name']??$cr['package_interest']??'') ?>">
                <datalist id="pkg-list">
                    <?php foreach($packages as $p): ?>
                    <option value="<?= htmlspecialchars($p['name']) ?>">
                    <?php endforeach; ?>
                </datalist></div>
            <div><label class="admin-label">Start Date</label>
                <input name="start_date" type="date" class="admin-input"
                       value="<?= htmlspecialchars($q['start_date']??$cr['start_date']??'') ?>"></div>
            <div><label class="admin-label">Destinations (comma-sep)</label>
                <input name="destinations" class="admin-input"
                       value="<?= htmlspecialchars(is_string($q['destinations']??null) ? implode(', ', json_decode($q['destinations']??'[]',true)??[]) : '') ?>"></div>
        </div>
    </div>
</div>


<!-- LOAD FROM ITINERARY -->
<div class="admin-card" style="margin-bottom:1.25rem;border:1.5px solid #0a0a0a">
  <div class="admin-card-header" style="background:#0a0a0a;color:#fff">
    <h3 style="color:#fff">Load Itinerary Template</h3>
    <span style="font-size:.72rem;opacity:.6">Auto-fills all line items, prices, includes/excludes</span>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:end">
      <div>
        <label class="admin-label">Select Itinerary</label>
        <select id="itin_select" class="admin-input" onchange="previewItinerary()">
          <option value="">— Choose an itinerary template —</option>
          <?php
          $itinList = [];
          try { $itinList = $db->query("SELECT id,name,duration_days,accommodation_level,base_price_pax FROM itineraries WHERE is_active=1 ORDER BY sort_order,name")->fetchAll(); }
          catch(Exception $e){}
          foreach($itinList as $it): ?>
          <option value="<?= $it['id'] ?>"
                  data-dur="<?= $it['duration_days'] ?>"
                  data-level="<?= $it['accommodation_level'] ?>"
                  data-price="<?= $it['base_price_pax'] ?>">
            <?= htmlspecialchars($it['name']) ?>
            — <?= $it['duration_days'] ?>d · est. $<?= number_format($it['base_price_pax'],0) ?>/pp
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="button" onclick="loadItinerary()" class="admin-btn admin-btn--primary" id="load-itin-btn" style="padding:.75rem 1.5rem">
        ↓ Load Itinerary
      </button>
    </div>
    <div id="itin-preview" style="display:none;margin-top:1rem;padding:.85rem;background:#f7f7f5;border:1px solid #e5e7eb;font-size:.82rem;line-height:1.7"></div>
  </div>
</div>




<!-- COST LINE ITEMS — matches PDF exactly -->
<div class="admin-card" style="margin-bottom:1.25rem">
    <div class="admin-card-header">
        <h3>Cost Breakdown</h3>
        <span style="font-size:.72rem;color:#9ca3af">Matches the SZV Quotation PDF format</span>
    </div>
    <div class="admin-card-body" style="padding:0">

        <!-- Header row -->
        <div style="display:grid;grid-template-columns:2.5fr 110px 170px 100px 40px;gap:.5rem;padding:.6rem 1rem;background:#0a0a0a;color:#fff">
            <div style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase">Description</div>
            <div style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;text-align:right">Unit Price (USD)</div>
            <div style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;text-align:center">Quantity</div>
            <div style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;text-align:right">Amount (USD)</div>
            <div></div>
        </div>

        <!-- Line items -->
        <div id="line-items-body">
            <?php foreach($defaultItems as $i=>$item): ?>
            <div class="line-item" style="display:grid;grid-template-columns:2.5fr 110px 170px 100px 40px;gap:.5rem;align-items:center;padding:.55rem 1rem;border-bottom:1px solid #f3f4f6">
                <div style="display:flex;gap:.4rem;align-items:center">
                    <select class="li-cat" style="font-size:.6rem;border:1px solid #e5e7eb;padding:.2rem .3rem;color:#9ca3af;background:#fff;cursor:pointer;flex-shrink:0;max-width:90px"
                            onchange="updateTotals()" title="Category">
                        <?php foreach(['accommodation'=>'Accommodation','transport'=>'Transport','park_fee'=>'Park Fee','activity'=>'Activity','flight'=>'Flight','service_fee'=>'Service Fee','other'=>'Other'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= ($item['category']??'other')===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="li-desc admin-input" style="font-size:.82rem;padding:.35rem .5rem"
                           placeholder="Description" value="<?= htmlspecialchars($item['description']??'') ?>"
                           list="desc-suggestions">
                </div>
                <div>
                    <input class="li-price admin-input" type="number" step="0.01" min="0"
                           style="font-size:.82rem;padding:.35rem .5rem;text-align:right"
                           value="<?= (float)($item['unit_price']??0) ?>"
                           oninput="recalcRow(this); updateTotals()">
                </div>
                <div>
                    <input class="li-qty-str admin-input" style="font-size:.78rem;padding:.35rem .5rem;text-align:center"
                           placeholder="e.g. 6 Guests × 3 Days"
                           value="<?= htmlspecialchars($item['quantity_str']??'') ?>"
                           oninput="parseQtyAndRecalc(this)">
                </div>
                <div>
                    <input class="li-amount admin-input" type="number" step="0.01"
                           style="font-size:.85rem;padding:.35rem .5rem;text-align:right;font-weight:700;background:#f9f9f8"
                           value="<?= number_format((float)($item['amount']??0),2,'.','') ?>"
                           oninput="updateTotals()">
                </div>
                <div style="text-align:center">
                    <button type="button" onclick="removeRow(this)"
                            style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:.2rem .3rem;line-height:1">✕</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Add row buttons -->
        <div style="padding:.75rem 1rem;display:flex;gap:.5rem;flex-wrap:wrap;border-top:1px solid #f3f4f6">
            <button type="button" onclick="addRow('accommodation','Accommodation – ','midrange')" class="admin-btn admin-btn--muted" style="font-size:.65rem">+ Accommodation</button>
            <button type="button" onclick="addRow('transport','Safari Vehicle & Fuel','')" class="admin-btn admin-btn--muted" style="font-size:.65rem">+ Vehicle</button>
            <button type="button" onclick="addRow('park_fee','National Park Entry Fees','')" class="admin-btn admin-btn--muted" style="font-size:.65rem">+ Park Fee</button>
            <button type="button" onclick="addRow('flight','Internal/Coastal Flight','')" class="admin-btn admin-btn--muted" style="font-size:.65rem">+ Flight</button>
            <button type="button" onclick="addRow('service_fee','Professional Driver Guide & Service Fee','')" class="admin-btn admin-btn--muted" style="font-size:.65rem">+ Service Fee</button>
            <button type="button" onclick="addRow('activity','Meals','')" class="admin-btn admin-btn--muted" style="font-size:.65rem">+ Meals</button>
            <button type="button" onclick="addRow('other','Airport Transfers','')" class="admin-btn admin-btn--muted" style="font-size:.65rem">+ Other</button>
        </div>

        <!-- Totals panel — matches PDF -->
        <div style="border-top:2px solid #0a0a0a;padding:1rem">
            <div style="max-width:380px;margin-left:auto">
                <!-- Subtotal -->
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.88rem;border-bottom:1px solid #f3f4f6">
                    <span style="color:#6b7280">TOTAL</span>
                    <span id="disp-subtotal" style="font-weight:600">$0.00</span>
                </div>
                <!-- Discount -->
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;border-bottom:1px solid #f3f4f6">
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <span style="font-size:.82rem;color:#6b7280">Discount</span>
                        <input name="discount_pct" type="number" min="0" max="50" step="0.5"
                               id="inp_discount"
                               value="<?= (float)($q['discount_pct']??0) ?>"
                               oninput="updateTotals()"
                               style="width:54px;border:1px solid #e5e7eb;padding:.2rem .35rem;font-size:.78rem;text-align:center">
                        <span style="font-size:.75rem;color:#9ca3af">%</span>
                    </div>
                    <span id="disp-discount" style="font-size:.88rem;color:#16a34a">–$0.00</span>
                </div>
                <!-- VAT -->
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.88rem;border-bottom:1px solid #f3f4f6">
                    <span style="color:#6b7280">VAT (18%)</span>
                    <span id="disp-vat" style="font-weight:600">$0.00</span>
                </div>
                <!-- Grand Total -->
                <div style="display:flex;justify-content:space-between;padding:.75rem;background:#0a0a0a;color:#fff;margin:.35rem 0">
                    <span style="font-weight:700;font-size:.95rem">GRAND TOTAL</span>
                    <span id="disp-grand" style="font-family:Georgia,serif;font-size:1.3rem;font-weight:500">USD $0.00</span>
                </div>
                <!-- Per person -->
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.85rem;border-bottom:1px solid #f3f4f6">
                    <span id="per-person-label" style="color:#6b7280">Per Person (2 guests)</span>
                    <span id="disp-pp" style="font-weight:700">$0.00</span>
                </div>
                <!-- Deposit / Balance -->
                <div style="background:#f0fdf4;border:1px solid #86efac;padding:.75rem;margin-top:.5rem">
                    <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:.25rem">
                        <span style="color:#166534">Deposit (30%) — due within 48h</span>
                        <span id="disp-deposit" style="font-weight:700;color:#166534">$0.00</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:.82rem">
                        <span style="color:#166534">Balance (70%) — due 45 days before</span>
                        <span id="disp-balance" style="font-weight:700;color:#166534">$0.00</span>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /card-body -->
</div><!-- /cost card -->

<!-- NOTES + OPTIONS -->
<div class="admin-card">
    <div class="admin-card-header"><h3>Additional Details</h3></div>
    <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
            <div><label class="admin-label">Accommodation Level</label>
                <select name="accommodation_level" class="admin-input">
                    <?php foreach(['budget'=>'Budget-Friendly','midrange'=>'Mid-Range','highend'=>'High-End / Luxury','ultra_luxury'=>'Ultra Luxury'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($q['accommodation_level']??'midrange')===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="admin-label">Meal Plan</label>
                <select name="meal_plan" class="admin-input">
                    <?php foreach(['fb'=>'Full Board (B+L+D)','hb'=>'Half Board','bb'=>'Bed & Breakfast','ai'=>'All Inclusive'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($q['meal_plan']??'fb')===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="admin-label">Guide Language</label>
                <select name="guide_language" class="admin-input">
                    <?php foreach(['english'=>'English','french'=>'French','german'=>'German','italian'=>'Italian','spanish'=>'Spanish','chinese'=>'Mandarin'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($q['guide_language']??'english')===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="admin-label">Valid Until</label>
                <input name="valid_until" type="date" class="admin-input"
                       value="<?= $q['valid_until'] ?? date('Y-m-d', strtotime('+14 days')) ?>"></div>
            <div><label class="admin-label">Status</label>
                <select name="status" class="admin-input">
                    <?php foreach(['draft'=>'Draft','sent'=>'Sent','accepted'=>'Accepted','declined'=>'Declined'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($q['status']??'draft')===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <div><label class="admin-label">Notes / Special Requests</label>
            <textarea name="notes" class="admin-input" rows="3"
                      placeholder="Any special requirements, inclusions, or notes for the client…"><?= htmlspecialchars($q['notes']??$cr['message']??'') ?></textarea></div>
    </div>
</div>

</div><!-- /left -->

<!-- ════ RIGHT SIDEBAR ════ -->
<div style="position:sticky;top:calc(var(--topbar-h) + 1rem)">

    <!-- Quick-add reference data -->
    <div class="admin-card" style="margin-bottom:1rem">
        <div class="admin-card-header"><h3>Quick Add</h3>
            <span style="font-size:.62rem;color:#9ca3af">Click to add as line item</span>
        </div>
        <div class="admin-card-body" style="padding:.5rem">

            <!-- Vehicles -->
            <div style="margin-bottom:.75rem">
                <div style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#9ca3af;padding:.35rem .5rem">Vehicles</div>
                <?php foreach($vehicles as $v):
                    $total = (float)$v['daily_rate'] + (float)$v['fuel_cost_day'] + (float)$v['driver_cost_day'];
                ?>
                <button type="button"
                        onclick="addRow('transport','<?= htmlspecialchars($v['name']) ?>','',<?= $total ?>,1,'<?= $qDuration ?> Days')"
                        style="display:block;width:100%;text-align:left;padding:.4rem .5rem;font-size:.72rem;background:none;border:none;cursor:pointer;border-radius:2px;color:#374151"
                        onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                    <span style="font-weight:600"><?= htmlspecialchars($v['name']) ?></span>
                    <span style="color:#9ca3af;float:right">$<?= number_format($total,0) ?>/day</span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Accommodations -->
            <div style="margin-bottom:.75rem;border-top:1px solid #f3f4f6;padding-top:.75rem">
                <div style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#9ca3af;padding:.35rem .5rem">Accommodations</div>
                <?php foreach(array_slice($accommodations, 0, 8) as $a):
                    $rate = max((float)$a['rate_per_room'], (float)$a['rate_per_person']);
                ?>
                <button type="button"
                        onclick="addRow('accommodation','Accommodation – <?= htmlspecialchars($a['name']) ?>','',<?= $rate ?>,1,'1 Room × 1 Night')"
                        style="display:block;width:100%;text-align:left;padding:.4rem .5rem;font-size:.72rem;background:none;border:none;cursor:pointer;color:#374151"
                        onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                    <span><?= htmlspecialchars($a['name']) ?></span>
                    <span style="color:#9ca3af;float:right">$<?= number_format($rate,0) ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Park Fees -->
            <div style="border-top:1px solid #f3f4f6;padding-top:.75rem">
                <div style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#9ca3af;padding:.35rem .5rem">Park Fees</div>
                <?php foreach($parkFees as $p): ?>
                <button type="button"
                        onclick="addRow('park_fee','<?= htmlspecialchars($p['name']) ?> Entry Fees','',<?= (float)$p['adult_fee'] ?>,<?= $qAdults ?>,'<?= $qAdults ?> Guests')"
                        style="display:block;width:100%;text-align:left;padding:.4rem .5rem;font-size:.72rem;background:none;border:none;cursor:pointer;color:#374151"
                        onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                    <span><?= htmlspecialchars($p['name']) ?></span>
                    <span style="color:#9ca3af;float:right">$<?= number_format((float)$p['adult_fee'],0) ?>/adult</span>
                </button>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <!-- Save -->
    <div class="admin-card">
        <div class="admin-card-body">
            <button type="submit" class="admin-btn admin-btn--primary"
                    style="width:100%;justify-content:center;padding:.85rem;margin-bottom:.5rem">
                💾 Save Quotation
            </button>
            <?php if($id): ?>
            <a href="../api/quotation_pdf.php?id=<?= $id ?>" target="_blank"
               class="admin-btn admin-btn--outline" style="width:100%;justify-content:center;margin-bottom:.5rem">
                PDF Preview ↗
            </a>
            <a href="../api/quotation_email.php?id=<?= $id ?>"
               class="admin-btn admin-btn--success" style="width:100%;justify-content:center">
                ✉ Send to Client
            </a>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /sidebar -->

</div><!-- /grid -->
</form>

<!-- Description suggestions datalist -->
<datalist id="desc-suggestions">
    <option value="Coastal Flight Zanzibar - Arusha">
    <option value="Internal Flight Arusha - Zanzibar">
    <option value="Accommodation – Arusha">
    <option value="Accommodation – Karatu">
    <option value="Accommodation – Serengeti">
    <option value="Accommodation – Ngorongoro Rim">
    <option value="Safari Vehicle & Fuel">
    <option value="Tarangire National Park Fees">
    <option value="Serengeti National Park Fees">
    <option value="Ngorongoro Conservation Entry Fees">
    <option value="Ngorongoro Crater Service Fee">
    <option value="Vehicle Park Fees">
    <option value="Lake Manyara National Park Fees">
    <option value="Meals">
    <option value="Airport Transfers">
    <option value="Professional Driver Guide & Service Fee">
    <option value="Hot Air Balloon Safari">
    <option value="Stone Town Walking Tour">
    <option value="Sunset Dhow Cruise">
    <option value="Maasai Village Visit">
    <option value="Spice Farm Tour">
    <?php foreach($vehicles as $v): ?>
    <option value="<?= htmlspecialchars($v['name']) ?> & Fuel">
    <?php endforeach; ?>
    <?php foreach($parkFees as $p): ?>
    <option value="<?= htmlspecialchars($p['name']) ?> Entry Fees">
    <option value="<?= htmlspecialchars($p['name']) ?> Crater Service Fee">
    <?php endforeach; ?>
    <?php foreach($activitiesDB as $a): ?>
    <option value="<?= htmlspecialchars($a['name']) ?>">
    <?php endforeach; ?>
</datalist>

<script>
var VAT_RATE    = 0.18;
var DEPOSIT_PCT = 0.30;
var fmt = function(n){ return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); };

// ── Update all totals from current rows ──────────────────────
function updateTotals() {
    var rows     = document.querySelectorAll('.line-item');
    var subtotal = 0;

    rows.forEach(function(row) {
        var amount = parseFloat(row.querySelector('.li-amount').value) || 0;
        subtotal += amount;
    });

    var adults    = parseInt(document.getElementById('inp_adults').value)   || 1;
    var children  = parseInt(document.getElementById('inp_children').value) || 0;
    var pax       = adults + children;
    var discPct   = parseFloat(document.getElementById('inp_discount').value) || 0;
    var discAmt   = subtotal * (discPct / 100);
    var afterDisc = subtotal - discAmt;
    var vat       = afterDisc * VAT_RATE;
    var grand     = afterDisc + vat;
    var perPerson = pax > 0 ? grand / pax : grand;
    var deposit   = grand * DEPOSIT_PCT;
    var balance   = grand - deposit;

    document.getElementById('disp-subtotal').textContent = 'USD $' + fmt(subtotal);
    document.getElementById('disp-discount').textContent = discAmt > 0 ? '–$' + fmt(discAmt) : '–$0.00';
    document.getElementById('disp-vat').textContent      = 'USD $' + fmt(vat);
    document.getElementById('disp-grand').textContent    = 'USD $' + fmt(grand);
    document.getElementById('disp-pp').textContent       = 'USD $' + fmt(perPerson);
    document.getElementById('disp-deposit').textContent  = 'USD $' + fmt(deposit);
    document.getElementById('disp-balance').textContent  = 'USD $' + fmt(balance);
    document.getElementById('per-person-label').textContent = 'USD per person (' + pax + ' guest' + (pax!==1?'s':'') + ')';

    // Update hidden JSON for form submission
    var items = [];
    rows.forEach(function(row) {
        items.push({
            description:  row.querySelector('.li-desc').value,
            unit_price:   parseFloat(row.querySelector('.li-price').value) || 0,
            quantity_str: row.querySelector('.li-qty-str').value,
            amount:       parseFloat(row.querySelector('.li-amount').value) || 0,
            category:     row.querySelector('.li-cat').value,
        });
    });
    document.getElementById('line_items_json').value = JSON.stringify(items);
}

// ── Recalculate row amount from unit_price when price changes ─
function recalcRow(priceInput) {
    var row    = priceInput.closest('.line-item');
    var price  = parseFloat(priceInput.value) || 0;
    var qtyStr = row.querySelector('.li-qty-str').value;
    var qty    = parseQtyNum(qtyStr) || 1;
    row.querySelector('.li-amount').value = (price * qty).toFixed(2);
}

// ── Parse quantity string to get numeric value ────────────────
// e.g. "6 Guests" → 6, "3 Rooms × 2 Nights" → 6, "4 Days" → 4
function parseQtyNum(str) {
    if (!str) return 1;
    // Multiply pattern: "3 × 2" or "3 Rooms × 2 Nights"
    var multMatch = str.match(/(\d+(?:\.\d+)?)\s*[×x\*]\s*(\d+(?:\.\d+)?)/);
    if (multMatch) return parseFloat(multMatch[1]) * parseFloat(multMatch[2]);
    // First number
    var numMatch = str.match(/(\d+(?:\.\d+)?)/);
    return numMatch ? parseFloat(numMatch[1]) : 1;
}

// ── When user types in quantity string, auto-recalc amount ───
function parseQtyAndRecalc(qtyInput) {
    var row   = qtyInput.closest('.line-item');
    var price = parseFloat(row.querySelector('.li-price').value) || 0;
    var qty   = parseQtyNum(qtyInput.value) || 1;
    row.querySelector('.li-amount').value = (price * qty).toFixed(2);
    updateTotals();
}

// ── Add a new line item row ───────────────────────────────────
function addRow(cat, desc, level, price, qty, qtyStr) {
    price  = price  || 0;
    qty    = qty    || 1;
    qtyStr = qtyStr || qty + ' unit' + (qty!==1?'s':'');
    var amount = price * (parseQtyNum(qtyStr.toString()) || qty);

    var cats = {
        accommodation: 'Accommodation', transport: 'Transport',
        park_fee: 'Park Fee', activity: 'Activity',
        flight: 'Flight', service_fee: 'Service Fee', other: 'Other'
    };
    var catOptions = Object.keys(cats).map(function(v){
        return '<option value="'+v+'"'+(v===cat?' selected':'')+'>'+cats[v]+'</option>';
    }).join('');

    var div = document.createElement('div');
    div.className = 'line-item';
    div.style.cssText = 'display:grid;grid-template-columns:2.5fr 110px 170px 100px 40px;gap:.5rem;align-items:center;padding:.55rem 1rem;border-bottom:1px solid #f3f4f6';
    div.innerHTML =
      '<div style="display:flex;gap:.4rem;align-items:center">'
        +'<select class="li-cat" style="font-size:.6rem;border:1px solid #e5e7eb;padding:.2rem .3rem;color:#9ca3af;background:#fff;cursor:pointer;flex-shrink:0;max-width:90px" onchange="updateTotals()" title="Category">'+catOptions+'</select>'
        +'<input class="li-desc admin-input" style="font-size:.82rem;padding:.35rem .5rem" placeholder="Description" value="'+desc+'" list="desc-suggestions">'
      +'</div>'
      +'<input class="li-price admin-input" type="number" step="0.01" min="0" style="font-size:.82rem;padding:.35rem .5rem;text-align:right" value="'+price+'" oninput="recalcRow(this);updateTotals()">'
      +'<input class="li-qty-str admin-input" style="font-size:.78rem;padding:.35rem .5rem;text-align:center" placeholder="e.g. 6 Guests" value="'+qtyStr+'" oninput="parseQtyAndRecalc(this)">'
      +'<input class="li-amount admin-input" type="number" step="0.01" style="font-size:.85rem;padding:.35rem .5rem;text-align:right;font-weight:700;background:#f9f9f8" value="'+amount.toFixed(2)+'" oninput="updateTotals()">'
      +'<div style="text-align:center"><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:.2rem .3rem;line-height:1">✕</button></div>';

    document.getElementById('line-items-body').appendChild(div);
    updateTotals();
}

// ── Remove row ────────────────────────────────────────────────
function removeRow(btn) { btn.closest('.line-item').remove(); updateTotals(); }

// ── Init ─────────────────────────────────────────────────────
updateTotals();

// ── Itinerary preview (on select change) ─────────────────────
function previewItinerary() {
  var sel = document.getElementById('itin_select');
  var opt = sel.options[sel.selectedIndex];
  var preview = document.getElementById('itin-preview');
  if (!sel.value) { preview.style.display='none'; return; }
  preview.style.display='block';
  preview.innerHTML = '<strong>'+opt.text+'</strong>';
}

// ── Load itinerary → populate all line items ──────────────────
async function loadItinerary() {
  var id     = document.getElementById('itin_select').value;
  var adults = parseInt(document.getElementById('inp_adults').value)||2;
  document.getElementById('itinerary_id').value = id;
  if (!id) { alert('Please select an itinerary first.'); return; }

  var btn = document.getElementById('load-itin-btn');
  btn.disabled = true; btn.textContent = 'Loading…';

  try {
    var url = (window.BASE_PATH||'')+'/api/itinerary.php?action=quote_items&id='+id+'&adults='+adults;
    var r   = await fetch(url, {credentials:'same-origin'});
    var d   = await r.json();

    if (!d.success) { alert('Error: '+(d.message||'Failed')); btn.disabled=false; btn.textContent='↓ Load Itinerary'; return; }

    var itin      = d.itinerary;
    var items     = d.line_items     || [];
    var dayInfo   = d.day_titles     || [];
    var includes  = d.includes       || [];
    var excludes  = d.excludes       || [];
    var optionals = d.optional_activities || [];

    // Set trip fields
    var el;
    if ((el=document.querySelector('[name="package_name"]')))   el.value = itin.name;
    if ((el=document.querySelector('[name="duration_days"]')))  el.value = itin.duration_days;
    if ((el=document.querySelector('[name="accommodation_level"]'))) el.value = itin.accommodation_level||'midrange';
    if ((el=document.getElementById('inp_adults')))             el.value = adults;

    // Set destinations
    if ((el=document.querySelector('[name="destinations"]'))) {
      var locs = dayInfo.map(function(d){ return d.location||d.title||''; }).filter(Boolean);
      el.value = locs.filter(function(v,i,a){ return a.indexOf(v)===i; }).join(', ');
    }

    // Clear and repopulate line items
    document.getElementById('line-items-body').innerHTML = '';
    var cats = {accommodation:'Accommodation',transport:'Transport',park_fee:'Park Fee',
                activity:'Activity',flight:'Flight',service_fee:'Service Fee',other:'Other'};

    items.forEach(function(item) {
      var opts = Object.keys(cats).map(function(v){
        return '<option value="'+v+'"'+(v===item.category?' selected':'')+'>'+cats[v]+'</option>';
      }).join('');
      var div = document.createElement('div');
      div.className = 'line-item';
      div.style.cssText = 'display:grid;grid-template-columns:2.5fr 110px 170px 100px 40px;gap:.5rem;align-items:center;padding:.55rem 1rem;border-bottom:1px solid #f3f4f6';
      div.innerHTML =
        '<div style="display:flex;gap:.4rem;align-items:center">'
          +'<select class="li-cat" style="font-size:.6rem;border:1px solid #e5e7eb;padding:.2rem .3rem;color:#9ca3af;background:#fff;flex-shrink:0;max-width:90px" onchange="updateTotals()">'+opts+'</select>'
          +'<input class="li-desc admin-input" style="font-size:.82rem;padding:.35rem .5rem" list="desc-suggestions" value="'+hE(item.description)+'">'
        +'</div>'
        +'<input class="li-price admin-input" type="number" step="0.01" style="font-size:.82rem;padding:.35rem .5rem;text-align:right" value="'+Number(item.unit_price).toFixed(2)+'" oninput="recalcRow(this);updateTotals()">'
        +'<input class="li-qty-str admin-input" style="font-size:.78rem;padding:.35rem .5rem;text-align:center" value="'+hE(item.quantity_str)+'" oninput="parseQtyAndRecalc(this)">'
        +'<input class="li-amount admin-input" type="number" step="0.01" style="font-size:.85rem;padding:.35rem .5rem;text-align:right;font-weight:700;background:#f9f9f8" value="'+Number(item.amount).toFixed(2)+'" oninput="updateTotals()">'
        +'<div style="text-align:center"><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:.2rem .3rem">✕</button></div>';
      document.getElementById('line-items-body').appendChild(div);
    });

    updateTotals();

    // Populate notes with includes/excludes/optionals
    if ((el=document.querySelector('[name="notes"]'))) {
      var txt = '';
      if (includes.length) txt += 'INCLUDED:\n'+includes.map(function(i){ return '✓ '+i; }).join('\n');
      if (excludes.length) txt += '\n\nNOT INCLUDED:\n'+excludes.map(function(e){ return '✗ '+e; }).join('\n');
      if (optionals.length) txt += '\n\nOPTIONAL EXTRAS:\n'+optionals.map(function(o){
        return '◦ '+o.name+(o.price>0?' ($'+Number(o.price).toFixed(0)+'/pp)':'');
      }).join('\n');
      el.value = txt.trim();
    }

    // Build preview panel
    var preview = document.getElementById('itin-preview');
    preview.style.display = 'block';
    var total = items.reduce(function(s,i){ return s+Number(i.amount); }, 0);
    var accCount  = items.filter(function(i){ return i.category==='accommodation'; }).length;
    var parkCount = items.filter(function(i){ return i.category==='park_fee'; }).length;
    var actCount  = items.filter(function(i){ return i.category==='activity'; }).length;

    var html = '<div style="font-weight:700;font-size:.88rem;color:#0a0a0a;margin-bottom:.65rem">✓ Loaded: '+hE(itin.name)+'</div>'
      +'<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:.35rem;margin-bottom:.75rem">';
    [
      ['🏕 Accommodation', accCount+' propert'+(accCount===1?'y':'ies')],
      ['🏞 Park Fees',      parkCount+' park'+(parkCount===1?'':'s')],
      ['🎯 Activities',     actCount+' item'+(actCount===1?'':'s')],
      ['💰 Est. Total',    'USD $'+Math.round(total).toLocaleString()],
    ].forEach(function(row){
      html += '<div style="background:#f3f4f6;padding:.4rem .6rem;font-size:.68rem">'
        +'<div style="opacity:.55;margin-bottom:.1rem">'+row[0]+'</div>'
        +'<div style="font-weight:700;color:#0a0a0a">'+row[1]+'</div></div>';
    });
    html += '</div>'
      +'<div style="border-top:1px solid #e5e7eb;padding-top:.6rem;max-height:200px;overflow-y:auto">';
    dayInfo.forEach(function(day){
      html += '<div style="display:grid;grid-template-columns:48px 1fr;gap:.35rem;padding:.28rem 0;border-bottom:1px solid #f5f5f5;font-size:.73rem">'
        +'<span style="font-weight:700;color:#9ca3af">Day '+day.day+'</span>'
        +'<div>'
          +'<div style="font-weight:600;color:#0a0a0a">'+hE(day.title)+'</div>'
          +(day.accommodation ? '<div style="color:#6b7280;font-size:.65rem">🏕 '+hE(day.accommodation)+'</div>' : '')
          +(day.park ? '<div style="color:#6b7280;font-size:.65rem">🏞 '+hE(day.park)+'</div>' : '')
        +'</div></div>';
    });
    if (optionals.length) {
      html += '<div style="margin-top:.5rem;padding:.45rem .6rem;background:#fef3e2;font-size:.7rem;color:#92400e">'
        +'<strong>Optional add-ons:</strong> '+optionals.map(function(o){ return o.name+(o.price>0?' ($'+o.price+'/pp)':''); }).join(' · ')
        +'</div>';
    }
    html += '</div>';
    preview.innerHTML = html;

  } catch(e) { alert('Network error: '+e.message); }
  btn.disabled = false; btn.textContent = '↓ Load Itinerary';
}

function hE(s) { var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }


// ── Auto-load from booking pre-fill ─────────────────────────
<?php if (!empty($bkPrefill) && !empty($bkPrefill['pkg_itin_id'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Fill client fields from booking
    var fields = {
        'client_name'         : <?= json_encode($bkPrefill['full_name'] ?? '') ?>,
        'client_email'        : <?= json_encode($bkPrefill['email'] ?? '') ?>,
        'client_phone'        : <?= json_encode($bkPrefill['phone'] ?? '') ?>,
        'client_country'      : <?= json_encode($bkPrefill['country'] ?? '') ?>,
        'adults'              : '<?= (int)($bkPrefill['adults'] ?? 2) ?>',
        'children'            : '<?= (int)($bkPrefill['children'] ?? 0) ?>',
        'start_date'          : <?= json_encode($bkPrefill['travel_date'] ?? '') ?>,
        'package_name'        : <?= json_encode($bkPrefill['package_name'] ?? '') ?>,
        'accommodation_level' : <?= json_encode($bkPrefill['accommodation_level'] ?? 'midrange') ?>,
    };
    Object.entries(fields).forEach(function([name, val]) {
        var el = document.querySelector('[name="' + name + '"]');
        if (el) el.value = val;
    });

    // Auto-select itinerary linked to this package
    var itinId = '<?= (int)($bkPrefill['pkg_itin_id'] ?? 0) ?>';
    if (itinId && itinId !== '0') {
        var sel = document.getElementById('itin_select');
        if (sel) {
            sel.value = itinId;
            previewItinerary();
            // Auto-load line items after short delay
            setTimeout(function() {
                document.getElementById('inp_adults').value = '<?= (int)($bkPrefill['adults'] ?? 2) ?>';
                loadItinerary();
            }, 800);
        }
    }
});
<?php elseif (!empty($bkPrefill)): ?>
document.addEventListener('DOMContentLoaded', function() {
    // No linked itinerary — just fill client fields
    var fields = {
        'client_name'         : <?= json_encode($bkPrefill['full_name'] ?? '') ?>,
        'client_email'        : <?= json_encode($bkPrefill['email'] ?? '') ?>,
        'client_phone'        : <?= json_encode($bkPrefill['phone'] ?? '') ?>,
        'client_country'      : <?= json_encode($bkPrefill['country'] ?? '') ?>,
        'adults'              : '<?= (int)($bkPrefill['adults'] ?? 2) ?>',
        'children'            : '<?= (int)($bkPrefill['children'] ?? 0) ?>',
        'start_date'          : <?= json_encode($bkPrefill['travel_date'] ?? '') ?>,
        'package_name'        : <?= json_encode($bkPrefill['package_name'] ?? '') ?>,
        'accommodation_level' : <?= json_encode($bkPrefill['accommodation_level'] ?? 'midrange') ?>,
    };
    Object.entries(fields).forEach(function([name, val]) {
        var el = document.querySelector('[name="' + name + '"]');
        if (el) el.value = val;
    });
});
<?php endif; ?>

</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
