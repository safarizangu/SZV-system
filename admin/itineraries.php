<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Itinerary Manager';

$msg = htmlspecialchars($_GET['msg'] ?? '');

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_itin'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $id       = (int)($_POST['id'] ?? 0);
    $dur      = max(1,(int)($_POST['duration_days']??5));
    $slug     = preg_replace('/[^a-z0-9-]/','',strtolower(trim($_POST['slug']??'')));
    $includes = array_values(array_filter(array_map('trim',explode("\n",$_POST['includes']??''))));
    $excludes = array_values(array_filter(array_map('trim',explode("\n",$_POST['excludes']??''))));

    // Build days JSON from posted arrays
    $days = [];
    for ($i=0; $i<$dur; $i++) {
        $days[] = [
            'day'                 => $i+1,
            'title'               => trim(($_POST['day_title'][$i]??'')),
            'description'         => trim(($_POST['day_desc'][$i]??'')),
            'location'            => trim(($_POST['day_location'][$i]??'')),
            'accommodation_id'    => (int)($_POST['day_acc_id'][$i]??0),
            'accommodation_name'  => trim(($_POST['day_acc_name'][$i]??'')),
            'accommodation_rate'  => (float)($_POST['day_acc_rate'][$i]??0),
            'accommodation_rooms' => (int)($_POST['day_acc_rooms'][$i]??1),
            'vehicle_id'          => (int)($_POST['day_veh_id'][$i]??0),
            'vehicle_name'        => trim(($_POST['day_veh_name'][$i]??'')),
            'vehicle_rate'        => (float)($_POST['day_veh_rate'][$i]??0),
            'park_fee_id'         => (int)($_POST['day_park_id'][$i]??0),
            'park_fee_name'       => trim(($_POST['day_park_name'][$i]??'')),
            'park_adult_fee'      => (float)($_POST['day_park_fee'][$i]??0),
            'meals_cost_pp'       => (float)($_POST['day_meals_pp'][$i]??0),
            'activities'          => json_decode($_POST['day_activities_json'][$i]??'[]',true)?:[],
            'other_items'         => json_decode($_POST['day_other_json'][$i]??'[]',true)?:[],
        ];
    }

    // Compute base price (accommodation + vehicle + parks + activities + meals for 2 pax)
    $basePrice = 0;
    $pax = 2;
    $accSeen = []; $vehSeen = [];
    foreach ($days as $day) {
        $accKey = $day['accommodation_name'];
        if ($accKey && !isset($accSeen[$accKey])) $accSeen[$accKey] = 0;
        if ($accKey) { $accSeen[$accKey]++; }
        $vehKey = $day['vehicle_name'];
        if ($vehKey && !isset($vehSeen[$vehKey])) $vehSeen[$vehKey] = 0;
        if ($vehKey) { $vehSeen[$vehKey]++; }
        $basePrice += (float)$day['park_adult_fee'] * $pax;
        $basePrice += (float)$day['meals_cost_pp']  * $pax;
        foreach ($day['activities'] as $act) {
            if (($act['type']??'included')==='included') $basePrice += (float)($act['price_pp']??0) * $pax;
        }
    }
    foreach ($accSeen as $name=>$nights) {
        foreach ($days as $d) { if ($d['accommodation_name']===$name) { $basePrice += $d['accommodation_rate']*$nights*$d['accommodation_rooms']; break; } }
    }
    foreach ($vehSeen as $name=>$dayCount) {
        foreach ($days as $d) { if ($d['vehicle_name']===$name) { $basePrice += $d['vehicle_rate']*$dayCount; break; } }
    }
    $perPerson = $pax > 0 ? round($basePrice/$pax,2) : 0;

    $data = [
        'name'                => trim($_POST['name']??''),
        'slug'                => $slug,
        'duration_days'       => $dur,
        'overview'            => trim($_POST['overview']??''),
        'accommodation_level' => $_POST['accommodation_level']??'midrange',
        'days'                => json_encode($days),
        'includes'            => json_encode($includes),
        'excludes'            => json_encode($excludes),
        'base_price_pax'      => $perPerson,
        'is_active'           => isset($_POST['is_active'])?1:0,
        'sort_order'          => (int)($_POST['sort_order']??0),
    ];

    try {
        if ($id > 0) {
            $set = implode(',',array_map(fn($k)=>"`$k`=?",array_keys($data)));
            $db->prepare("UPDATE itineraries SET $set,updated_at=NOW() WHERE id=?")->execute(array_merge(array_values($data),[$id]));
            $newId = $id;
        } else {
            $cols = implode(',',array_map(fn($k)=>"`$k`",array_keys($data)));
            $phld = implode(',',array_fill(0,count($data),'?'));
            $db->prepare("INSERT INTO itineraries ($cols) VALUES ($phld)")->execute(array_values($data));
            $newId = $db->lastInsertId();
        }
        header("Location: itineraries.php?id=$newId&msg=".urlencode('Itinerary saved.')); exit;
    } catch(\Exception $e) { $msg = 'Error: '.$e->getMessage(); }
}

// DELETE
if (isset($_GET['delete'])) {
    try { $db->prepare("DELETE FROM itineraries WHERE id=?")->execute([(int)$_GET['delete']]); }
    catch(\Exception $e){}
    header('Location: itineraries.php?msg='.urlencode('Deleted.')); exit;
}

// Load reference data
function fetchAll($db,$sql){try{return $db->query($sql)->fetchAll();}catch(\Exception $e){return[];}}
$accommodations = fetchAll($db,"SELECT id,name,level,rate_per_room,rate_per_person FROM accommodations WHERE is_active=1 ORDER BY level,name");
$vehicles       = fetchAll($db,"SELECT id,name,daily_rate,fuel_cost_day,driver_cost_day FROM vehicles WHERE is_active=1 ORDER BY name");
$parkFees       = fetchAll($db,"SELECT id,name,adult_fee,child_fee FROM park_fees WHERE is_active=1 ORDER BY name");
$activitiesDB   = fetchAll($db,"SELECT id,name,price,price_unit FROM activities WHERE is_active=1 ORDER BY name");
$itineraries    = fetchAll($db,"SELECT id,name,duration_days,base_price_pax,is_active FROM itineraries ORDER BY sort_order,name LIMIT 200");

$editId = (int)($_GET['id'] ?? 0);
$itin   = null;
$days   = [];
$includes = $excludes = '';
if ($editId) {
    try { $s=$db->prepare("SELECT * FROM itineraries WHERE id=?"); $s->execute([$editId]); $itin=$s->fetch(); }
    catch(\Exception $e){}
    if ($itin) {
        $days     = json_decode($itin['days']??'[]',true)?:[];
        $includes = implode("\n", json_decode($itin['includes']??'[]',true)?:[]);
        $excludes = implode("\n", json_decode($itin['excludes']??'[]',true)?:[]);
    }
}
$dur = (int)($itin['duration_days']??5);
while (count($days)<$dur) $days[]=['day'=>count($days)+1,'title'=>'','description'=>'','location'=>'','accommodation_id'=>0,'accommodation_name'=>'','accommodation_rate'=>0,'accommodation_rooms'=>1,'vehicle_id'=>0,'vehicle_name'=>'','vehicle_rate'=>0,'park_fee_id'=>0,'park_fee_name'=>'','park_adult_fee'=>0,'meals_cost_pp'=>20,'activities'=>[],'other_items'=>[]];
$days = array_slice($days,0,$dur);
$trashIconJs = json_encode(\App\Helpers\AdminIcon::render('trash', 14));

ob_start();
?>
<?php if($msg): ?>
<div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:260px 1fr;gap:1.5rem;align-items:start">

<!-- LEFT: list -->
<div style="position:sticky;top:calc(var(--topbar-h)+1rem)">
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-header" style="padding:.75rem 1rem">
      <h3>Itineraries <span style="opacity:.5;font-size:.75rem"><?= count($itineraries) ?></span></h3>
      <a href="itineraries.php" class="admin-btn admin-btn--primary" style="font-size:.62rem">+ New</a>
    </div>
    <div style="padding:.25rem 0">
      <?php foreach($itineraries as $it): ?>
      <a href="?id=<?= $it['id'] ?>"
         style="display:block;padding:.6rem 1rem;font-size:.78rem;text-decoration:none;
                border-left:2px solid <?= $it['id']===$editId?'var(--white)':'transparent' ?>;
                background:<?= $it['id']===$editId?'var(--black)':'transparent' ?>;
                color:<?= $it['id']===$editId?'var(--white)':'var(--gray-700)' ?>">
        <div style="font-weight:600"><?= htmlspecialchars($it['name']) ?></div>
        <div style="font-size:.65rem;opacity:.55;margin-top:.1rem">
          <?= $it['duration_days'] ?>d ·
          <?= $it['base_price_pax']>0 ? '$'.number_format($it['base_price_pax'],0).'/pp' : 'no price' ?> ·
          <?= $it['is_active']?'Active':'Draft' ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- JSON preview for API -->
  <?php if($editId && $itin): ?>
  <div class="admin-card">
    <div class="admin-card-header" style="padding:.75rem 1rem"><h3 style="font-size:.78rem">API</h3></div>
    <div class="admin-card-body" style="padding:.75rem;font-size:.65rem;color:var(--gray-500)">
      <div style="margin-bottom:.4rem">Use in quotation or package:</div>
      <code style="display:block;word-break:break-all">
        /api/itinerary.php?action=get&id=<?= $editId ?>
      </code>
      <code style="display:block;word-break:break-all;margin-top:.35rem">
        /api/itinerary.php?action=quote_items&id=<?= $editId ?>&adults=2
      </code>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- RIGHT: editor -->
<div>
<?php if ($editId || !$editId): ?>
<form method="POST" id="itin-form">
<?= \App\Middleware\CsrfMiddleware::field() ?>
<input type="hidden" name="save_itin" value="1">
<input type="hidden" name="id" value="<?= $editId ?>">

<!-- Header -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <h3><?= $editId ? 'Edit: '.htmlspecialchars($itin['name']??'') : 'New Itinerary' ?></h3>
    <?php if($editId): ?>
    <a href="?delete=<?= $editId ?>" class="admin-btn admin-btn--danger" style="font-size:.65rem"
       onclick="return confirm('Delete this itinerary?')">Delete</a>
    <?php endif; ?>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr 80px;gap:1rem;margin-bottom:1rem">
      <div><label class="admin-label">Name *</label>
        <input name="name" class="admin-input" required value="<?= htmlspecialchars($itin['name']??'') ?>" placeholder="e.g. Classic Safari – 5 Days"></div>
      <div><label class="admin-label">Slug *</label>
        <input name="slug" class="admin-input" required pattern="[a-z0-9-]+" value="<?= htmlspecialchars($itin['slug']??'') ?>"></div>
      <div><label class="admin-label">Accommodation Level</label>
        <select name="accommodation_level" class="admin-input">
          <?php foreach(['budget'=>'Budget','midrange'=>'Mid-Range','highend'=>'High-End','ultra_luxury'=>'Ultra Luxury'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($itin['accommodation_level']??'midrange')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label class="admin-label">Sort</label>
        <input name="sort_order" type="number" class="admin-input" value="<?= (int)($itin['sort_order']??0) ?>"></div>
    </div>
    <div style="display:grid;grid-template-columns:60px 1fr;gap:1rem;margin-bottom:1rem;align-items:end">
      <div><label class="admin-label">Nights</label>
        <input name="duration_days" type="number" min="1" max="30" class="admin-input" id="dur_input"
               value="<?= $dur ?>" oninput="syncDayCount(this.value)"></div>
      <div><label class="admin-label">Overview</label>
        <textarea name="overview" class="admin-input" rows="2"><?= htmlspecialchars($itin['overview']??'') ?></textarea></div>
    </div>
    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
      <input type="checkbox" name="is_active" value="1" <?= ($itin['is_active']??1)?'checked':'' ?>>
      <span class="admin-label" style="margin:0">Active (available to load in packages/quotations)</span>
    </label>
  </div>
</div>

<!-- Grand total bar -->
<div style="background:var(--black);color:var(--white);padding:1rem 1.5rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
  <div>
    <div style="font-size:.58rem;letter-spacing:.15em;text-transform:uppercase;opacity:.5">Estimated Base Cost</div>
    <div style="font-family:Georgia,serif;font-size:1.5rem" id="itin-total">$0</div>
  </div>
  <div style="text-align:center">
    <div style="font-size:.58rem;letter-spacing:.12em;text-transform:uppercase;opacity:.5">Travelers (for preview)</div>
    <input type="number" id="preview_pax" value="2" min="1" max="20"
           style="width:56px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:var(--white);text-align:center;padding:.35rem;font-size:1rem;font-family:inherit"
           oninput="calcItinTotal()">
  </div>
  <div style="text-align:right">
    <div style="font-size:.58rem;letter-spacing:.12em;text-transform:uppercase;opacity:.5">Per Person</div>
    <div style="font-family:Georgia,serif;font-size:1.3rem" id="itin-pp">$0</div>
  </div>
</div>

<!-- DAY-BY-DAY BUILDER -->
<div id="days-container">
<?php foreach($days as $i=>$day): ?>
<div class="day-block admin-card" data-day="<?= $i ?>" style="margin-bottom:1rem;border-left:3px solid var(--black)">
  <div class="admin-card-header" style="background:var(--gray-50)">
    <h3 style="font-size:.85rem">
      <span style="font-family:Georgia,serif;color:var(--black);margin-right:.5rem">Day <?= $i+1 ?></span>
      <input name="day_title[]" class="admin-input" style="display:inline;width:auto;min-width:200px;font-size:.85rem;border:none;background:transparent;font-weight:600;padding:.15rem .35rem"
             placeholder="Day title e.g. Arusha → Tarangire"
             value="<?= htmlspecialchars($day['title']) ?>">
    </h3>
    <div style="display:flex;align-items:center;gap:.5rem">
      <span class="day-cost-badge" style="font-size:.65rem;background:var(--black);color:var(--white);padding:.2rem .6rem;font-weight:600">$0</span>
    </div>
  </div>

  <div class="admin-card-body">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
      <!-- Location -->
      <div><label class="admin-label">Location / Park</label>
        <input name="day_location[]" class="admin-input" value="<?= htmlspecialchars($day['location']??'') ?>" placeholder="e.g. Serengeti National Park"></div>
      <!-- Description -->
      <div><label class="admin-label">Day Description</label>
        <textarea name="day_desc[]" class="admin-input" rows="2" placeholder="What happens this day…"><?= htmlspecialchars($day['description']??'') ?></textarea></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">

      <!-- Accommodation -->
      <div style="border:1px solid #e5e7eb;padding:.85rem;background:#eff6ff20">
        <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#1d4ed8;display:flex;align-items:center;gap:.35rem"><?= \App\Helpers\AdminIcon::render('accommodations', 13) ?> Accommodation</p>
        <input type="hidden" name="day_acc_id[]" class="day-acc-id" value="<?= (int)($day['accommodation_id']??0) ?>">
        <div style="margin-bottom:.45rem"><label class="admin-label">Property</label>
          <input name="day_acc_name[]" class="admin-input day-acc-name" list="acc-list-<?= $i ?>"
                 value="<?= htmlspecialchars($day['accommodation_name']??'') ?>"
                 oninput="calcItinTotal()" onchange="fillDayAcc(this,<?= $i ?>)"
                 placeholder="Select or type">
          <datalist id="acc-list-<?= $i ?>">
            <?php foreach($accommodations as $a): $r=max((float)$a['rate_per_room'],(float)$a['rate_per_person']); ?>
            <option value="<?= htmlspecialchars($a['name']) ?>" data-id="<?= $a['id'] ?>" data-rate="<?= $r ?>" data-level="<?= $a['level'] ?>">
            <?php endforeach; ?>
          </datalist>
        </div>
        <div style="display:grid;grid-template-columns:1fr 60px;gap:.35rem">
          <div><label class="admin-label">Rate/Room/Night</label>
            <input name="day_acc_rate[]" type="number" step="1" class="admin-input day-acc-rate"
                   value="<?= (float)($day['accommodation_rate']??0) ?>" oninput="calcItinTotal()"></div>
          <div><label class="admin-label">Rooms</label>
            <input name="day_acc_rooms[]" type="number" min="1" class="admin-input"
                   value="<?= (int)($day['accommodation_rooms']??1) ?>" oninput="calcItinTotal()"></div>
        </div>
      </div>

      <!-- Vehicle -->
      <div style="border:1px solid #e5e7eb;padding:.85rem;background:#f0fdf420">
        <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#166534;display:flex;align-items:center;gap:.35rem"><?= \App\Helpers\AdminIcon::render('fleet', 13) ?> Vehicle</p>
        <input type="hidden" name="day_veh_id[]" class="day-veh-id" value="<?= (int)($day['vehicle_id']??0) ?>">
        <div style="margin-bottom:.45rem"><label class="admin-label">Vehicle</label>
          <input name="day_veh_name[]" class="admin-input day-veh-name" list="veh-list-<?= $i ?>"
                 value="<?= htmlspecialchars($day['vehicle_name']??'') ?>"
                 oninput="calcItinTotal()" onchange="fillDayVeh(this,<?= $i ?>)"
                 placeholder="Select or type">
          <datalist id="veh-list-<?= $i ?>">
            <?php foreach($vehicles as $v): $total=(float)$v['daily_rate']+(float)$v['fuel_cost_day']+(float)$v['driver_cost_day']; ?>
            <option value="<?= htmlspecialchars($v['name']) ?>" data-id="<?= $v['id'] ?>" data-rate="<?= $total ?>">
            <?php endforeach; ?>
          </datalist>
        </div>
        <div><label class="admin-label">Daily Rate (incl. fuel+driver)</label>
          <input name="day_veh_rate[]" type="number" step="1" class="admin-input day-veh-rate"
                 value="<?= (float)($day['vehicle_rate']??0) ?>" oninput="calcItinTotal()"></div>
      </div>

      <!-- Park + Meals -->
      <div style="border:1px solid #e5e7eb;padding:.85rem;background:#fef3e220">
        <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#92400e;display:flex;align-items:center;gap:.35rem"><?= \App\Helpers\AdminIcon::render('park-fees', 13) ?> Park & Meals</p>
        <input type="hidden" name="day_park_id[]" value="<?= (int)($day['park_fee_id']??0) ?>">
        <div style="margin-bottom:.45rem"><label class="admin-label">Park / Area</label>
          <input name="day_park_name[]" class="admin-input" list="park-list-<?= $i ?>"
                 value="<?= htmlspecialchars($day['park_fee_name']??'') ?>"
                 oninput="calcItinTotal()" onchange="fillDayPark(this,<?= $i ?>)"
                 placeholder="Select or leave blank">
          <datalist id="park-list-<?= $i ?>">
            <?php foreach($parkFees as $p): ?>
            <option value="<?= htmlspecialchars($p['name']) ?>" data-id="<?= $p['id'] ?>" data-fee="<?= $p['adult_fee'] ?>">
            <?php endforeach; ?>
          </datalist>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem">
          <div><label class="admin-label">Adult Fee</label>
            <input name="day_park_fee[]" type="number" step="0.01" class="admin-input day-park-fee"
                   value="<?= (float)($day['park_adult_fee']??0) ?>" oninput="calcItinTotal()"></div>
          <div><label class="admin-label">Meals/pp</label>
            <input name="day_meals_pp[]" type="number" step="0.01" class="admin-input"
                   value="<?= (float)($day['meals_cost_pp']??20) ?>" oninput="calcItinTotal()"></div>
        </div>
      </div>

    </div>

    <!-- Activities for this day -->
    <div style="margin-bottom:1rem">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
        <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Activities (Day <?= $i+1 ?>)</p>
        <button type="button" onclick="addDayActivity(<?= $i ?>)" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>
      </div>
      <div id="day-acts-<?= $i ?>">
        <?php foreach($day['activities'] as $ai=>$act): ?>
        <div class="act-item" style="display:grid;grid-template-columns:2fr 80px 90px auto;gap:.35rem;align-items:end;margin-bottom:.4rem">
          <input name="act_name_<?= $i ?>_<?= $ai ?>" class="admin-input day-act-name" list="act-list"
                 value="<?= htmlspecialchars($act['name']??'') ?>" placeholder="Activity name" style="font-size:.78rem" oninput="saveDayActs(<?= $i ?>)">
          <input name="act_price_<?= $i ?>_<?= $ai ?>" type="number" step="0.01" class="admin-input day-act-price"
                 value="<?= (float)($act['price_pp']??0) ?>" placeholder="$/pp" style="font-size:.78rem" oninput="saveDayActs(<?= $i ?>)">
          <select name="act_type_<?= $i ?>_<?= $ai ?>" class="admin-input day-act-type" style="font-size:.72rem" onchange="saveDayActs(<?= $i ?>)">
            <option value="included" <?= ($act['type']??'included')==='included'?'selected':'' ?>>Included</option>
            <option value="optional" <?= ($act['type']??'included')==='optional'?'selected':'' ?>>Optional</option>
          </select>
          <button type="button" onclick="this.closest('.act-item').remove();saveDayActs(<?= $i ?>)"
                  class="admin-icon-btn admin-icon-btn--danger"><?= \App\Helpers\AdminIcon::render('trash', 14) ?></button>
        </div>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="day_activities_json[]" id="acts-json-<?= $i ?>"
             value="<?= htmlspecialchars(json_encode($day['activities'])) ?>">
    </div>

    <!-- Other items for this day -->
    <div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
        <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Other Costs (Day <?= $i+1 ?>)</p>
        <button type="button" onclick="addDayOther(<?= $i ?>)" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>
      </div>
      <div id="day-other-<?= $i ?>">
        <?php foreach($day['other_items'] as $oi=>$ot): ?>
        <div class="other-item" style="display:grid;grid-template-columns:2fr 100px auto;gap:.35rem;align-items:end;margin-bottom:.4rem">
          <input class="admin-input day-other-name" value="<?= htmlspecialchars($ot['name']??'') ?>" placeholder="Item name" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
          <input type="number" step="0.01" class="admin-input day-other-amount" value="<?= (float)($ot['amount']??0) ?>" placeholder="$" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
          <button type="button" onclick="this.closest('.other-item').remove();saveDayOther(<?= $i ?>)"
                  class="admin-icon-btn admin-icon-btn--danger"><?= \App\Helpers\AdminIcon::render('trash', 14) ?></button>
        </div>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="day_other_json[]" id="other-json-<?= $i ?>"
             value="<?= htmlspecialchars(json_encode($day['other_items'])) ?>">
    </div>

  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Includes / Excludes -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3>Includes &amp; Excludes</h3>
    <span style="font-size:.72rem;color:var(--gray-500)">One item per line</span>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
      <div><label class="admin-label" style="display:flex;align-items:center;gap:.3rem"><?= \App\Helpers\AdminIcon::render('check', 12) ?> Included</label>
        <textarea name="includes" class="admin-input" rows="6"><?= htmlspecialchars($includes) ?></textarea></div>
      <div><label class="admin-label" style="display:flex;align-items:center;gap:.3rem"><?= \App\Helpers\AdminIcon::render('close', 12) ?> Not Included</label>
        <textarea name="excludes" class="admin-input" rows="6"><?= htmlspecialchars($excludes) ?></textarea></div>
    </div>
  </div>
</div>

<!-- Save -->
<div style="display:flex;gap:.75rem">
  <button type="submit" class="admin-btn admin-btn--primary" style="padding:.85rem 2.5rem">Save Itinerary</button>
  <a href="itineraries.php" class="admin-btn admin-btn--muted">Cancel</a>
</div>

</form>
<?php endif; ?>
</div><!-- /right -->
</div><!-- /grid -->

<!-- Activities datalist -->
<datalist id="act-list">
  <?php foreach($activitiesDB as $a): ?>
  <option value="<?= htmlspecialchars($a['name']) ?>" data-price="<?= $a['price'] ?>">
  <?php endforeach; ?>
  <option value="Morning Game Drive">
  <option value="Afternoon Game Drive">
  <option value="Full Day Game Drive">
  <option value="Night Game Drive">
  <option value="Bush Walk">
  <option value="Sundowner">
  <option value="Hot Air Balloon Safari">
  <option value="Crater Descent">
  <option value="Village Visit">
  <option value="Dhow Cruise">
</datalist>

<script>
// ── Per-day auto-fill ──────────────────────────────────────
function fillDayAcc(input, dayIdx) {
  var opts=document.querySelectorAll('#acc-list-'+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.rate)  block.querySelector('.day-acc-rate').value=opts[i].dataset.rate;
      if(opts[i].dataset.id)    block.querySelector('.day-acc-id').value=opts[i].dataset.id;
      calcItinTotal(); break;
    }
  }
}
function fillDayVeh(input, dayIdx) {
  var opts=document.querySelectorAll('#veh-list-'+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.rate)  block.querySelector('.day-veh-rate').value=opts[i].dataset.rate;
      if(opts[i].dataset.id)    block.querySelector('.day-veh-id').value=opts[i].dataset.id;
      calcItinTotal(); break;
    }
  }
}
function fillDayPark(input, dayIdx) {
  var opts=document.querySelectorAll('#park-list-'+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.fee)  block.querySelector('.day-park-fee').value=opts[i].dataset.fee;
      calcItinTotal(); break;
    }
  }
}

// ── Calculate total across all days ───────────────────────
function calcItinTotal() {
  var pax      = parseInt(document.getElementById('preview_pax').value)||2;
  var total    = 0;
  var blocks   = document.querySelectorAll('.day-block');
  var accSeen  = {}, vehSeen = {};

  blocks.forEach(function(block) {
    var accName = (block.querySelector('.day-acc-name')||{}).value||'';
    var accRate = parseFloat((block.querySelector('.day-acc-rate')||{}).value)||0;
    var accRooms= parseInt((block.querySelector('[name="day_acc_rooms[]"]')||{}).value)||1;
    var vehName = (block.querySelector('.day-veh-name')||{}).value||'';
    var vehRate = parseFloat((block.querySelector('.day-veh-rate')||{}).value)||0;
    var parkFee = parseFloat((block.querySelector('.day-park-fee')||{}).value)||0;
    var meals   = parseFloat((block.querySelector('[name="day_meals_pp[]"]')||{}).value)||0;

    // Accommodation: only count each property once (not per-day again)
    if (accName && !accSeen[accName]) accSeen[accName] = {rate:accRate,rooms:accRooms,nights:0};
    if (accName) accSeen[accName].nights++;

    // Vehicle: only count each vehicle once
    if (vehName && !vehSeen[vehName]) vehSeen[vehName] = {rate:vehRate,days:0};
    if (vehName) vehSeen[vehName].days++;

    // Park + meals per day
    total += parkFee * pax;
    total += meals * pax;

    // Activities
    block.querySelectorAll('.act-item').forEach(function(row){
      var price = parseFloat((row.querySelector('.day-act-price')||{}).value)||0;
      var type  = (row.querySelector('.day-act-type')||{}).value||'included';
      if(type==='included') total += price * pax;
    });

    // Day cost badge
    var dayCost = parkFee*pax + meals*pax + accRate*accRooms + vehRate;
    var badge = block.querySelector('.day-cost-badge');
    if(badge) badge.textContent='$'+Math.round(dayCost).toLocaleString();
  });

  // Add accommodation totals
  Object.values(accSeen).forEach(function(a){ total += a.rate * a.nights * a.rooms; });
  // Add vehicle totals
  Object.values(vehSeen).forEach(function(v){ total += v.rate * v.days; });

  var pp = pax > 0 ? total/pax : total;
  document.getElementById('itin-total').textContent = '$'+Math.round(total).toLocaleString();
  document.getElementById('itin-pp').textContent    = '$'+Math.round(pp).toLocaleString()+'/pp';
}

// ── Activities per day ────────────────────────────────────
function addDayActivity(dayIdx) {
  var container = document.getElementById('day-acts-'+dayIdx);
  var rowIdx    = container.querySelectorAll('.act-item').length;
  var div = document.createElement('div');
  div.className='act-item';
  div.style.cssText='display:grid;grid-template-columns:2fr 80px 90px auto;gap:.35rem;align-items:end;margin-bottom:.4rem';
  div.innerHTML='<input class="admin-input day-act-name" list="act-list" placeholder="Activity name" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<input type="number" step="0.01" class="admin-input day-act-price" value="0" placeholder="$/pp" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<select class="admin-input day-act-type" style="font-size:.72rem" onchange="saveDayActs('+dayIdx+')"><option value="included">Included</option><option value="optional">Optional</option></select>'
    +'<button type="button" onclick="this.closest(\'.act-item\').remove();saveDayActs('+dayIdx+')" class="admin-icon-btn admin-icon-btn--danger">'+<?= $trashIconJs ?>+'</button>';
  container.appendChild(div);
  div.querySelector('.day-act-name').addEventListener('change', function(){
    var opts=document.querySelectorAll('#act-list option');
    for(var i=0;i<opts.length;i++){
      if(opts[i].value===this.value && opts[i].dataset.price){
        div.querySelector('.day-act-price').value=opts[i].dataset.price;
        saveDayActs(dayIdx); break;
      }
    }
  });
}

function saveDayActs(dayIdx) {
  var container = document.getElementById('day-acts-'+dayIdx);
  var acts=[];
  container.querySelectorAll('.act-item').forEach(function(row){
    var name=row.querySelector('.day-act-name').value.trim();
    if(!name) return;
    acts.push({name:name, price_pp:parseFloat(row.querySelector('.day-act-price').value)||0, type:row.querySelector('.day-act-type').value});
  });
  document.getElementById('acts-json-'+dayIdx).value=JSON.stringify(acts);
  calcItinTotal();
}

// ── Other items per day ───────────────────────────────────
function addDayOther(dayIdx) {
  var container=document.getElementById('day-other-'+dayIdx);
  var div=document.createElement('div');
  div.className='other-item';
  div.style.cssText='display:grid;grid-template-columns:2fr 100px auto;gap:.35rem;align-items:end;margin-bottom:.4rem';
  div.innerHTML='<input class="admin-input day-other-name" placeholder="Item name" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
    +'<input type="number" step="0.01" class="admin-input day-other-amount" value="0" placeholder="$" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
    +'<button type="button" onclick="this.closest(\'.other-item\').remove();saveDayOther('+dayIdx+')" class="admin-icon-btn admin-icon-btn--danger">'+<?= $trashIconJs ?>+'</button>';
  container.appendChild(div);
}

function saveDayOther(dayIdx) {
  var container=document.getElementById('day-other-'+dayIdx);
  var items=[];
  container.querySelectorAll('.other-item').forEach(function(row){
    var name=row.querySelector('.day-other-name').value.trim();
    if(!name) return;
    items.push({name:name, amount:parseFloat(row.querySelector('.day-other-amount').value)||0});
  });
  document.getElementById('other-json-'+dayIdx).value=JSON.stringify(items);
}

// ── Sync day count with duration input ────────────────────
function syncDayCount(n) {
  n = Math.max(1, Math.min(30, parseInt(n)||1));
  var container = document.getElementById('days-container');
  var existing  = container.querySelectorAll('.day-block');
  var cur       = existing.length;
  // Remove extras
  for(var i=cur-1;i>=n;i--) existing[i].remove();
  calcItinTotal();
}

// Init
calcItinTotal();
</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
