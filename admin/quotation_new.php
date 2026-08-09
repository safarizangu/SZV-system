<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

$q    = null;
$id   = (int)($_GET['id'] ?? 0);
$msg  = htmlspecialchars($_GET['msg'] ?? '');
$crId = (int)($_GET['from_request'] ?? 0);  // pre-fill from custom request

// Load existing quote for edit
if ($id > 0) {
    try { $s=$db->prepare("SELECT * FROM quotations WHERE id=?"); $s->execute([$id]); $q=$s->fetch(); }
    catch(Exception $e) {}
}

// Pre-fill from custom_safari_request
$cr = null;
if ($crId > 0) {
    try { $s=$db->prepare("SELECT * FROM custom_safari_requests WHERE id=?"); $s->execute([$crId]); $cr=$s->fetch(); }
    catch(Exception $e) {}
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quote'])) {
    $adults        = max(1,(int)$_POST['adults']);
    $children      = max(0,(int)$_POST['children']);
    $infants       = max(0,(int)$_POST['infants']);
    $duration      = max(1,(int)$_POST['duration_days']);
    $accTotal      = (float)$_POST['accommodation_total'];
    $vehicleTotal  = (float)$_POST['vehicle_total'];
    $parkTotal     = (float)$_POST['park_fees_total'];
    $actTotal      = (float)$_POST['activities_total'];
    $flightCost    = (float)$_POST['flight_cost'];
    $discount      = (float)$_POST['discount_pct'];
    $subtotal      = (float)$_POST['subtotal'];
    $svcCharge     = (float)$_POST['service_charge'];
    $vat           = (float)$_POST['vat'];
    $grandTotal    = (float)$_POST['grand_total'];
    $perPerson     = (float)$_POST['per_person'];
    $deposit       = $grandTotal * 0.5;
    $balance       = $grandTotal - $deposit;
    $discountAmt   = $subtotal * ($discount / 100);
    $lineItems     = $_POST['line_items_json'] ?? '[]';
    $destinations  = json_encode(array_filter(explode(',', $_POST['destinations'] ?? '')));
    $activities    = json_encode(array_filter(explode(',', $_POST['activities_list'] ?? '')));
    $validUntil    = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    $status        = $_POST['status'] ?? 'draft';

    $data = [
        'client_name'          => trim($_POST['client_name']),
        'client_email'         => trim($_POST['client_email']),
        'client_phone'         => trim($_POST['client_phone'] ?? ''),
        'client_country'       => trim($_POST['client_country'] ?? ''),
        'adults'               => $adults,
        'children'             => $children,
        'infants'              => $infants,
        'duration_days'        => $duration,
        'start_date'           => $_POST['start_date'] ?? null,
        'destinations'         => $destinations,
        'accommodation_level'  => $_POST['accommodation_level'] ?? 'midrange',
        'meal_plan'            => $_POST['meal_plan'] ?? 'fb',
        'guide_language'       => $_POST['guide_language'] ?? 'english',
        'activities'           => $activities,
        'internal_flight'      => isset($_POST['internal_flight']) ? 1 : 0,
        'season'               => $_POST['season'] ?? 'peak',
        'accommodation_total'  => $accTotal,
        'vehicle_total'        => $vehicleTotal,
        'park_fees_total'      => $parkTotal,
        'activities_total'     => $actTotal,
        'flight_cost'          => $flightCost,
        'discount_pct'         => $discount,
        'discount_amount'      => $discountAmt,
        'line_items'           => $lineItems,
        'subtotal'             => $subtotal,
        'service_charge'       => $svcCharge,
        'vat'                  => $vat,
        'grand_total'          => $grandTotal,
        'per_person'           => $perPerson,
        'deposit_amount'       => $deposit,
        'balance_due'          => $balance,
        'currency'             => 'USD',
        'status'               => $status,
        'valid_until'          => $validUntil,
        'notes'                => trim($_POST['notes'] ?? ''),
        'admin_notes'          => trim($_POST['admin_notes'] ?? ''),
        'custom_request_id'    => (int)($_POST['custom_request_id'] ?? 0) ?: null,
        'package_name'         => trim($_POST['package_name'] ?? ''),
        'vehicle_name'         => trim($_POST['vehicle_name'] ?? ''),
    ];

    try {
        if ($id > 0) {
            // Update
            $set = implode(', ', array_map(fn($k) => "`$k`=?", array_keys($data)));
            $db->prepare("UPDATE quotations SET $set, updated_at=NOW() WHERE id=?")
               ->execute(array_merge(array_values($data), [$id]));
            if ($status === 'sent') {
                $db->prepare("UPDATE quotations SET sent_at=NOW() WHERE id=? AND sent_at IS NULL")->execute([$id]);
            }
            $newId = $id;
        } else {
            // Create ref
            $ref = 'QUO' . date('Y') . strtoupper(substr(md5(uniqid(mt_rand(),true)),0,5));
            $data['quote_ref'] = $ref;
            $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
            $phld = implode(', ', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO quotations ($cols, created_at) VALUES ($phld, NOW())")
               ->execute(array_values($data));
            $newId = $db->lastInsertId();
            // Mark custom request as quoted
            if (!empty($data['custom_request_id'])) {
                $db->prepare("UPDATE custom_safari_requests SET status='quoted' WHERE id=?")
                   ->execute([$data['custom_request_id']]);
            }
        }
        header("Location: quotation_new.php?id=$newId&msg=".urlencode('Quotation saved.'));
        exit;
    } catch(Exception $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}

// Load reference data
function fetchAll($db, $sql) { try { return $db->query($sql)->fetchAll(); } catch(Exception $e) { return []; } }
$accommodations = fetchAll($db, "SELECT * FROM accommodations WHERE is_active=1 ORDER BY level,name");
$vehicles       = fetchAll($db, "SELECT * FROM vehicles       WHERE is_active=1 ORDER BY level,name");
$parkFees       = fetchAll($db, "SELECT * FROM park_fees      WHERE is_active=1 ORDER BY name");
$activities     = fetchAll($db, "SELECT * FROM activities     WHERE is_active=1 ORDER BY category,name");
$seasons        = fetchAll($db, "SELECT * FROM seasonal_rates ORDER BY start_month");
$packages       = fetchAll($db, "SELECT id,slug,name,price_1pax,price_2pax,price_3pax,duration_days FROM packages WHERE is_active=1 ORDER BY name");

$pageTitle = $id ? 'Edit Quotation' : 'New Quotation';
ob_start();
?>

<?php if ($msg): ?>
<div class="admin-alert admin-alert--success" style="margin-bottom:1.25rem">
  <?= $msg ?>
  <?php if ($id): ?>
    &nbsp;·&nbsp; <a href="../php/quotation_pdf.php?id=<?= $id ?>" target="_blank" style="font-weight:700">View PDF</a>
    &nbsp;·&nbsp;
    <form method="POST" action="../php/quotation_email.php" style="display:inline">
      <input type="hidden" name="id" value="<?= $id ?>">
      <button style="background:none;border:none;font-weight:700;cursor:pointer;font-size:inherit;color:inherit;text-decoration:underline">Send Email</button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST" id="quote-form">
<input type="hidden" name="save_quote" value="1">
<input type="hidden" name="custom_request_id" value="<?= $cr['id'] ?? ($q['custom_request_id'] ?? '') ?>">
<input type="hidden" name="line_items_json" id="line_items_json" value="<?= htmlspecialchars($q['line_items'] ?? '[]') ?>">

<div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start">

  <!-- ── LEFT: MAIN FORM ──────────────────────────────────── -->
  <div>

    <!-- Client -->
    <div class="admin-card" style="margin-bottom:1.25rem">
      <div class="admin-card-header"><h3>Client Details</h3></div>
      <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div><label class="admin-label">Full Name *</label>
            <input name="client_name" class="admin-input" required
                   value="<?= htmlspecialchars($q['client_name'] ?? $cr['full_name'] ?? '') ?>"></div>
          <div><label class="admin-label">Email *</label>
            <input name="client_email" type="email" class="admin-input" required
                   value="<?= htmlspecialchars($q['client_email'] ?? $cr['email'] ?? '') ?>"></div>
          <div><label class="admin-label">Phone / WhatsApp</label>
            <input name="client_phone" class="admin-input"
                   value="<?= htmlspecialchars($q['client_phone'] ?? $cr['phone'] ?? '') ?>"></div>
          <div><label class="admin-label">Country</label>
            <input name="client_country" class="admin-input"
                   value="<?= htmlspecialchars($q['client_country'] ?? $cr['country'] ?? '') ?>"></div>
        </div>
      </div>
    </div>

    <!-- Safari Details -->
    <div class="admin-card" style="margin-bottom:1.25rem">
      <div class="admin-card-header"><h3>Safari Details</h3>
        <span style="font-size:.72rem;color:#9ca3af">All fields feed into the price calculator</span>
      </div>
      <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div><label class="admin-label">Adults *</label>
            <input name="adults" id="q_adults" type="number" min="1" max="30" class="admin-input" required
                   value="<?= $q['adults'] ?? $cr['adults'] ?? 2 ?>" oninput="recalc()"></div>
          <div><label class="admin-label">Children</label>
            <input name="children" id="q_children" type="number" min="0" max="20" class="admin-input"
                   value="<?= $q['children'] ?? $cr['children'] ?? 0 ?>" oninput="recalc()"></div>
          <div><label class="admin-label">Infants (0–2)</label>
            <input name="infants" type="number" min="0" max="10" class="admin-input"
                   value="<?= $q['infants'] ?? $cr['infants'] ?? 0 ?>"></div>
          <div><label class="admin-label">Duration (nights) *</label>
            <input name="duration_days" id="q_duration" type="number" min="1" max="60" class="admin-input" required
                   value="<?= $q['duration_days'] ?? $cr['duration_days'] ?? 5 ?>" oninput="recalc()"></div>
          <div><label class="admin-label">Start Date</label>
            <input name="start_date" type="date" class="admin-input"
                   value="<?= $q['start_date'] ?? $cr['start_date'] ?? '' ?>"></div>
          <div><label class="admin-label">Season</label>
            <select name="season" id="q_season" class="admin-input" onchange="recalc()">
              <?php foreach ($seasons as $s): ?>
              <option value="<?= $s['season_key'] ?>" <?= ($q['season']??'peak')===$s['season_key']?'selected':'' ?>
                      data-mult="<?= $s['multiplier'] ?>">
                <?= $s['name'] ?> (×<?= $s['multiplier'] ?>)
              </option>
              <?php endforeach; ?>
              <?php if (empty($seasons)): ?>
              <option value="peak" data-mult="1.00" selected>Peak Season (×1.00)</option>
              <option value="shoulder" data-mult="0.85">Shoulder (×0.85)</option>
              <option value="low" data-mult="0.75">Low Season (×0.75)</option>
              <?php endif; ?>
            </select>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div><label class="admin-label">Destinations (comma-separated)</label>
            <input name="destinations" class="admin-input"
                   value="<?= htmlspecialchars(implode(', ', json_decode($q['destinations']??'[]',true) ?: [])) ?>"></div>
          <div><label class="admin-label">Guide Language</label>
            <select name="guide_language" class="admin-input">
              <?php foreach (['english'=>'English','french'=>'French','german'=>'German','italian'=>'Italian','spanish'=>'Spanish','chinese'=>'Mandarin Chinese','swahili'=>'Swahili','other'=>'Other'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($q['guide_language']??'english')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label class="admin-label">Accommodation Level</label>
            <select name="accommodation_level" id="q_acc_level" class="admin-input" onchange="recalc()">
              <?php foreach (['budget'=>'Budget','midrange'=>'Mid-Range','highend'=>'High-End','ultra_luxury'=>'Ultra Luxury'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($q['accommodation_level']??'midrange')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label class="admin-label">Meal Plan</label>
            <select name="meal_plan" class="admin-input">
              <?php foreach (['fb'=>'Full Board (B+L+D)','hb'=>'Half Board (B+D)','bb'=>'Bed & Breakfast','ai'=>'All Inclusive'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($q['meal_plan']??'fb')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div><label class="admin-label">Pre-selected Package (optional)</label>
          <select name="package_name" id="q_package" class="admin-input" onchange="applyPackage(this)">
            <option value="">— None / Custom —</option>
            <?php foreach ($packages as $p): ?>
            <option value="<?= htmlspecialchars($p['name']) ?>"
              <?= ($q['package_name']??'')===$p['name']?'selected':'' ?>
              data-p1="<?= $p['price_1pax'] ?>" data-p2="<?= $p['price_2pax'] ?>"
              data-p3="<?= $p['price_3pax'] ?>" data-days="<?= $p['duration_days'] ?>">
              <?= htmlspecialchars($p['name']) ?> (<?= $p['duration_days'] ?>d)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Line Items Builder -->
    <div class="admin-card" style="margin-bottom:1.25rem">
      <div class="admin-card-header">
        <h3>Cost Line Items</h3>
        <span style="font-size:.72rem;color:#9ca3af">Build the quote from components below</span>
      </div>
      <div class="admin-card-body">

        <!-- Accommodation picker -->
        <div style="margin-bottom:1.25rem">
          <label class="admin-label">Add Accommodation</label>
          <div style="display:grid;grid-template-columns:1fr auto;gap:.5rem">
            <select id="acc_picker" class="admin-input">
              <option value="">— Select Accommodation —</option>
              <?php foreach ($accommodations as $a): ?>
              <option value="<?= $a['id'] ?>"
                data-name="<?= htmlspecialchars($a['name']) ?>"
                data-level="<?= $a['level'] ?>"
                data-rate-room="<?= $a['rate_per_room'] ?>"
                data-rate-pp="<?= $a['rate_per_person'] ?>">
                <?= htmlspecialchars($a['name']) ?> (<?= ucfirst($a['level']) ?>) — $<?= number_format($a['rate_per_room'],0) ?>/room or $<?= number_format($a['rate_per_person'],0) ?>/pp
              </option>
              <?php endforeach; ?>
            </select>
            <button type="button" onclick="addAccItem()" class="admin-btn admin-btn--primary">Add</button>
          </div>
        </div>

        <!-- Vehicle picker -->
        <div style="margin-bottom:1.25rem">
          <label class="admin-label">Add Vehicle</label>
          <div style="display:grid;grid-template-columns:1fr auto;gap:.5rem">
            <select id="veh_picker" class="admin-input">
              <option value="">— Select Vehicle —</option>
              <?php foreach ($vehicles as $v): ?>
              <option value="<?= $v['id'] ?>"
                data-name="<?= htmlspecialchars($v['name']) ?>"
                data-rate="<?= $v['daily_rate'] ?>"
                data-fuel="<?= $v['fuel_cost_day'] ?>"
                data-driver="<?= $v['driver_cost_day'] ?>">
                <?= htmlspecialchars($v['name']) ?> — $<?= number_format($v['daily_rate'],0) ?>/day + $<?= number_format($v['fuel_cost_day']+$v['driver_cost_day'],0) ?>/day ops
              </option>
              <?php endforeach; ?>
            </select>
            <button type="button" onclick="addVehItem()" class="admin-btn admin-btn--primary">Add</button>
          </div>
        </div>

        <!-- Park fees -->
        <div style="margin-bottom:1.25rem">
          <label class="admin-label">Add Park Fees</label>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <?php foreach ($parkFees as $pf): ?>
            <label style="display:flex;align-items:center;gap:.3rem;font-size:.78rem;cursor:pointer;border:1.5px solid #e5e7eb;padding:.3rem .6rem"
                   data-park-id="<?= $pf['id'] ?>"
                   data-park-name="<?= htmlspecialchars($pf['name']) ?>"
                   data-adult-fee="<?= $pf['adult_fee'] ?>"
                   data-child-fee="<?= $pf['child_fee'] ?>">
              <input type="checkbox" class="park-check" value="<?= $pf['id'] ?>">
              <?= htmlspecialchars($pf['name']) ?> ($<?= $pf['adult_fee'] ?>A/$<?= $pf['child_fee'] ?>C)
            </label>
            <?php endforeach; ?>
            <button type="button" onclick="addParkFees()" class="admin-btn admin-btn--primary">Add Selected Parks</button>
          </div>
        </div>

        <!-- Activities -->
        <div style="margin-bottom:1.25rem">
          <label class="admin-label">Add Activities</label>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <?php foreach ($activities as $act): ?>
            <label style="display:flex;align-items:center;gap:.3rem;font-size:.78rem;cursor:pointer;border:1.5px solid #e5e7eb;padding:.3rem .6rem"
                   data-act-name="<?= htmlspecialchars($act['name']) ?>"
                   data-act-price="<?= $act['price'] ?>"
                   data-act-unit="<?= $act['price_unit'] ?>">
              <input type="checkbox" class="act-check" value="<?= $act['id'] ?>">
              <?= htmlspecialchars($act['name']) ?> ($<?= $act['price'] ?>/<?= $act['price_unit'] ?>)
            </label>
            <?php endforeach; ?>
            <button type="button" onclick="addActivities()" class="admin-btn admin-btn--primary">Add Selected</button>
          </div>
          <input name="activities_list" type="hidden" id="activities_list_hidden" value="<?= htmlspecialchars(implode(',', json_decode($q['activities']??'[]',true) ?: [])) ?>">
        </div>

        <!-- Manual line item -->
        <div style="margin-bottom:1.25rem;padding:1rem;background:#f7f7f5;border:1px solid #e5e7eb">
          <label class="admin-label">Add Custom Line Item</label>
          <div style="display:grid;grid-template-columns:2fr 1fr auto;gap:.5rem">
            <input type="text" id="custom_desc" class="admin-input" placeholder="Description…">
            <input type="number" id="custom_amount" class="admin-input" placeholder="Amount (USD)" step="0.01">
            <button type="button" onclick="addCustomItem()" class="admin-btn admin-btn--outline">Add</button>
          </div>
        </div>

        <!-- Internal flight toggle -->
        <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;margin-bottom:1rem">
          <input type="checkbox" name="internal_flight" id="q_flight" value="1"
                 <?= ($q['internal_flight']??0)?'checked':'' ?> onchange="recalc()">
          <span class="admin-label" style="margin:0">Include Internal Flight (+$250/person estimate)</span>
        </label>

        <!-- LINE ITEMS TABLE -->
        <table class="admin-table" id="line-items-table">
          <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Amount</th><th></th></tr></thead>
          <tbody id="line-items-body">
            <!-- Populated by JS -->
          </tbody>
          <tfoot>
            <tr style="background:#f7f7f5">
              <td colspan="3" style="font-weight:600;padding:.6rem .75rem">Subtotal</td>
              <td style="font-weight:700;padding:.6rem .75rem" id="ft-subtotal">—</td><td></td>
            </tr>
          </tfoot>
        </table>

        <!-- Hidden totals for saving -->
        <input type="hidden" name="accommodation_total"   id="h_acc_total"  value="<?= $q['accommodation_total']  ?? 0 ?>">
        <input type="hidden" name="vehicle_total"         id="h_veh_total"  value="<?= $q['vehicle_total']        ?? 0 ?>">
        <input type="hidden" name="park_fees_total"       id="h_park_total" value="<?= $q['park_fees_total']      ?? 0 ?>">
        <input type="hidden" name="activities_total"      id="h_act_total"  value="<?= $q['activities_total']     ?? 0 ?>">
        <input type="hidden" name="flight_cost"           id="h_flight"     value="<?= $q['flight_cost']          ?? 0 ?>">
        <input type="hidden" name="subtotal"              id="h_subtotal"   value="<?= $q['subtotal']             ?? 0 ?>">
        <input type="hidden" name="service_charge"        id="h_svc"        value="<?= $q['service_charge']       ?? 0 ?>">
        <input type="hidden" name="vat"                   id="h_vat"        value="<?= $q['vat']                  ?? 0 ?>">
        <input type="hidden" name="grand_total"           id="h_grand"      value="<?= $q['grand_total']          ?? 0 ?>">
        <input type="hidden" name="per_person"            id="h_pp"         value="<?= $q['per_person']           ?? 0 ?>">
        <input type="hidden" name="vehicle_name"          id="h_veh_name"   value="<?= htmlspecialchars($q['vehicle_name'] ?? '') ?>">
      </div>
    </div>

    <!-- Discount -->
    <div class="admin-card" style="margin-bottom:1.25rem">
      <div class="admin-card-header"><h3>Discount</h3></div>
      <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div><label class="admin-label">Discount %</label>
            <input name="discount_pct" id="q_discount" type="number" min="0" max="100" step="0.5"
                   class="admin-input" value="<?= $q['discount_pct'] ?? 0 ?>" oninput="recalc()"></div>
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div class="admin-card" style="margin-bottom:1.25rem">
      <div class="admin-card-header"><h3>Notes</h3></div>
      <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div><label class="admin-label">Client-Visible Notes</label>
            <textarea name="notes" class="admin-input" rows="4"><?= htmlspecialchars($q['notes'] ?? '') ?></textarea></div>
          <div><label class="admin-label">Internal Admin Notes</label>
            <textarea name="admin_notes" class="admin-input" rows="4"><?= htmlspecialchars($q['admin_notes'] ?? '') ?></textarea></div>
        </div>
      </div>
    </div>

  </div><!-- /left -->

  <!-- ── RIGHT: SUMMARY + ACTIONS ─────────────────────────── -->
  <div style="position:sticky;top:80px">

    <!-- Price Summary -->
    <div class="admin-card" style="margin-bottom:1rem">
      <div class="admin-card-header"><h3>Price Summary</h3></div>
      <div class="admin-card-body" style="padding:0">
        <table style="width:100%;font-size:.82rem;border-collapse:collapse">
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.55rem 1rem">Accommodation</td><td style="padding:.55rem 1rem;text-align:right;font-weight:600" id="s_acc">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.55rem 1rem">Vehicle & Driver</td><td style="padding:.55rem 1rem;text-align:right;font-weight:600" id="s_veh">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.55rem 1rem">Park Fees</td><td style="padding:.55rem 1rem;text-align:right;font-weight:600" id="s_park">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.55rem 1rem">Activities</td><td style="padding:.55rem 1rem;text-align:right;font-weight:600" id="s_act">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.55rem 1rem">Flights</td><td style="padding:.55rem 1rem;text-align:right;font-weight:600" id="s_flight">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.55rem 1rem">Discount</td><td style="padding:.55rem 1rem;text-align:right;color:#16a34a;font-weight:600" id="s_discount">—</td></tr>
          <tr style="border-bottom:2px solid #0a0a0a"><td style="padding:.6rem 1rem;font-weight:600">Subtotal</td><td style="padding:.6rem 1rem;text-align:right;font-weight:700" id="s_sub">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.55rem 1rem">Service Charge (5%)</td><td style="padding:.55rem 1rem;text-align:right" id="s_svc">$0</td></tr>
          <tr style="border-bottom:2px solid #0a0a0a"><td style="padding:.55rem 1rem">VAT (18%)</td><td style="padding:.55rem 1rem;text-align:right" id="s_vat">$0</td></tr>
          <tr style="background:#0a0a0a;color:#fff"><td style="padding:.9rem 1rem;font-family:var(--font-disp);font-size:1rem">Grand Total</td><td style="padding:.9rem 1rem;text-align:right;font-family:var(--font-disp);font-size:1.1rem;font-weight:700" id="s_grand">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6;background:#f7f7f5"><td style="padding:.55rem 1rem;font-size:.78rem">Per Person</td><td style="padding:.55rem 1rem;text-align:right;font-weight:700;font-size:.9rem" id="s_pp">—</td></tr>
          <tr style="background:#f0fdf4"><td style="padding:.55rem 1rem;font-size:.78rem">Deposit (50%)</td><td style="padding:.55rem 1rem;text-align:right;font-weight:700;color:#166534" id="s_dep">$0</td></tr>
          <tr><td style="padding:.55rem 1rem;font-size:.78rem">Balance Due</td><td style="padding:.55rem 1rem;text-align:right;font-weight:600" id="s_bal">$0</td></tr>
        </table>
      </div>
    </div>

    <!-- Status + Validity -->
    <div class="admin-card" style="margin-bottom:1rem">
      <div class="admin-card-header"><h3>Status &amp; Validity</h3></div>
      <div class="admin-card-body">
        <div style="display:grid;gap:.75rem">
          <div><label class="admin-label">Quote Status</label>
            <select name="status" class="admin-input">
              <?php foreach (['draft'=>'Draft','sent'=>'Sent','accepted'=>'Accepted','declined'=>'Declined','expired'=>'Expired','converted'=>'Converted'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($q['status']??'draft')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label class="admin-label">Valid Until</label>
            <input name="valid_until" type="date" class="admin-input"
                   value="<?= $q['valid_until'] ?? date('Y-m-d', strtotime('+14 days')) ?>"></div>
        </div>
      </div>
    </div>

    <!-- Save Actions -->
    <div class="admin-card">
      <div class="admin-card-body">
        <div style="display:flex;flex-direction:column;gap:.5rem">
          <button type="submit" class="admin-btn admin-btn--primary" style="justify-content:center;padding:.75rem">
            💾 Save Quotation
          </button>
          <?php if ($id): ?>
          <a href="../php/quotation_pdf.php?id=<?= $id ?>" target="_blank"
             class="admin-btn admin-btn--outline" style="justify-content:center;padding:.75rem">
            🖨 Print / Save PDF
          </a>
          <form method="POST" action="../php/quotation_email.php">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="admin-btn admin-btn--outline" style="width:100%;justify-content:center;padding:.75rem">
              ✉ Email to Client
            </button>
          </form>
          <a href="quotation_new.php?from_request=<?= $q['custom_request_id'] ?? 0 ?>" class="admin-btn admin-btn--outline" style="justify-content:center;padding:.75rem">
            + New Version
          </a>
          <a href="../php/quotation_pdf.php?id=<?= $id ?>&download=1" target="_blank"
             class="admin-btn admin-btn--outline" style="justify-content:center;padding:.75rem">
            ↓ Download PDF
          </a>
          <?php endif; ?>
          <a href="quotations.php" class="admin-btn admin-btn--muted" style="justify-content:center;padding:.65rem">Cancel</a>
        </div>
      </div>
    </div>

  </div><!-- /right -->
</div><!-- /grid -->
</form>

<script src="<?= BASE_PATH ?>/admin/js/quotation.js"></script>
<script>
// Bootstrap existing line items from PHP
var existingItems = <?= $q ? ($q['line_items'] ?? '[]') : '[]' ?>;
var VAT_RATE = 0.18;
var SVC_RATE = 0.05;
document.addEventListener('DOMContentLoaded', function() {
    if (Array.isArray(existingItems) && existingItems.length) {
        existingItems.forEach(addLineItem);
    }
    recalc();
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
