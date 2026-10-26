<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'itinerary_edit.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();

$msg = htmlspecialchars($_GET['msg'] ?? '');

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_itin'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $id       = (int)($_POST['id'] ?? 0);
    $dur      = max(1,(int)($_POST['duration_days']??5));
    $slug     = preg_replace('/[^a-z0-9-]/','',strtolower(trim($_POST['slug']??'')));
    $includes = array_values(array_filter(array_map('trim',explode("\n",$_POST['includes']??''))));
    $excludes = array_values(array_filter(array_map('trim',explode("\n",$_POST['excludes']??''))));

    // Build days JSON from posted arrays. If the first day is flagged as
    // an arrival day (airport pickup + overnight, nothing touring-related
    // happens yet), it's labelled "Day 0" and every day after it shifts
    // down by one so actual touring starts at "Day 1" — see day_is_arrival
    // below. Only index 0's flag is ever read; it's keyed explicitly
    // (day_is_arrival[0]) rather than day_is_arrival[] because unchecked
    // checkboxes don't submit at all, which would otherwise misalign a
    // plain array against day position the moment any other day's (unused)
    // checkbox were checked instead.
    $arrivalDay = !empty($_POST['day_is_arrival'][0]);
    $days = [];
    for ($i=0; $i<$dur; $i++) {
        $days[] = [
            'day'                 => $arrivalDay ? $i : $i+1,
            'is_arrival'          => ($i===0 && $arrivalDay),
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
        foreach ($days as $day) { \App\Services\LocationRegistry::touchLocation($db, $day['location'] ?? ''); }
        header("Location: itinerary_edit.php?id=$newId&msg=".urlencode('Itinerary saved.')); exit;
    } catch(\Exception $e) { $msg = 'Error: '.$e->getMessage(); }
}

// DELETE — POST + CSRF only. A GET-triggered delete could be fired by any
// page the admin visits (cross-site request forgery via a plain link).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try { $db->prepare("DELETE FROM itineraries WHERE id=?")->execute([(int)$_POST['delete']]); }
    catch(\Exception $e){}
    header('Location: itineraries.php?msg='.urlencode('Deleted.')); exit;
}

// Load reference data
$accommodations = fetchAll($db,"SELECT id,name,level,location,rate_per_room,rate_per_person FROM accommodations WHERE is_active=1 ORDER BY level,name");
$vehicles       = fetchAll($db,"SELECT id,name,daily_rate,fuel_cost_day,driver_cost_day FROM vehicles WHERE is_active=1 ORDER BY name");
$parkFees       = fetchAll($db,"SELECT id,name,location,adult_fee,child_fee FROM park_fees WHERE is_active=1 ORDER BY name");
$activitiesDB   = fetchAll($db,"SELECT id,name,price,price_unit,location FROM activities WHERE is_active=1 ORDER BY name");
$allLocations   = \App\Services\LocationRegistry::allLocations($db);
// Generic, location-less activity-type suggestions — kept as free-text
// prompts (no price/id) alongside the real catalog above, shown in every
// day's datalist regardless of location since they're not tied to one.
$genericActivityLabels = ['Morning Game Drive','Afternoon Game Drive','Full Day Game Drive','Night Game Drive','Bush Walk','Sundowner','Hot Air Balloon Safari','Crater Descent','Village Visit','Dhow Cruise'];

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
// Padding for a shorter-than-duration_days load only; whether Day 1 is
// flagged as an arrival day determines whether a freshly-padded day's
// number continues from 0 or from 1 — see the save handler above for the
// authoritative version of this same rule.
$arrivalDayLoaded = !empty($days[0]['is_arrival']);
while (count($days)<$dur) {
    $n = count($days);
    $days[] = ['day'=>$arrivalDayLoaded ? $n : $n+1,'is_arrival'=>false,'title'=>'','description'=>'','location'=>'','accommodation_id'=>0,'accommodation_name'=>'','accommodation_rate'=>0,'accommodation_rooms'=>1,'vehicle_id'=>0,'vehicle_name'=>'','vehicle_rate'=>0,'park_fee_id'=>0,'park_fee_name'=>'','park_adult_fee'=>0,'meals_cost_pp'=>20,'activities'=>[],'other_items'=>[]];
}
$days = array_slice($days,0,$dur);
$trashIconJs = json_encode(\App\Helpers\AdminIcon::render('trash', 14));

$pageTitle = $editId ? 'Edit Itinerary' : 'New Itinerary';
ob_start();
?>
<?php if($msg): ?>
<div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:flex-end;gap:.5rem;margin-bottom:1.25rem">
  <a href="itineraries.php" class="admin-btn admin-btn--muted">← All Itineraries</a>
</div>

<form method="POST" id="itin-form">
<?= \App\Middleware\CsrfMiddleware::field() ?>
<input type="hidden" name="save_itin" value="1">
<input type="hidden" name="id" value="<?= $editId ?>">

<!-- Header -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <h3><?= $editId ? 'Edit: '.htmlspecialchars($itin['name']??'') : 'New Itinerary' ?></h3>
    <?php if($editId): ?>
    <button type="button" class="admin-btn admin-btn--danger" style="font-size:.65rem"
       onclick="deleteItinerary(<?= $editId ?>)">Delete</button>
    <script>
    async function deleteItinerary(id) {
      if (!(await confirmDialog('Delete this itinerary? This cannot be undone.', {danger:true}))) return;
      var f = document.createElement('form');
      f.method = 'POST'; f.action = 'itinerary_edit.php';
      [['delete', id], ['_csrf', window.CSRF_TOKEN || '']].forEach(function (p) {
        var i = document.createElement('input'); i.type = 'hidden'; i.name = p[0]; i.value = p[1]; f.appendChild(i);
      });
      document.body.appendChild(f); f.submit();
    }
    </script>
    <?php endif; ?>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr 80px;gap:1rem;margin-bottom:1rem">
      <div><label class="admin-label" for="name">Name *</label>
        <input id="name" name="name" class="admin-input" required value="<?= htmlspecialchars($itin['name']??'') ?>" placeholder="e.g. Classic Safari – 5 Days"></div>
      <div><label class="admin-label" for="slug">Slug *</label>
        <input id="slug" name="slug" class="admin-input" required pattern="[a-z0-9-]+" value="<?= htmlspecialchars($itin['slug']??'') ?>"></div>
      <div><label class="admin-label" for="accommodation_level">Accommodation Level</label>
        <select id="accommodation_level" name="accommodation_level" class="admin-input" onchange="filterAllDayOptions()">
          <?php foreach(['budget'=>'Budget','midrange'=>'Mid-Range','highend'=>'High-End','luxury'=>'Luxury','ultra_luxury'=>'Ultra Luxury'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($itin['accommodation_level']??'midrange')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label class="admin-label" for="sort_order">Sort</label>
        <input id="sort_order" name="sort_order" type="number" class="admin-input" value="<?= (int)($itin['sort_order']??0) ?>"></div>
    </div>
    <div style="display:grid;grid-template-columns:60px 1fr;gap:1rem;margin-bottom:1rem;align-items:end">
      <div><label class="admin-label" for="dur_input">Days</label>
        <input name="duration_days" type="number" min="1" max="30" class="admin-input" id="dur_input"
               value="<?= $dur ?>" oninput="syncDayCount(this.value)"></div>
      <div><label class="admin-label" for="overview">Overview</label>
        <textarea id="overview" name="overview" class="admin-input" rows="2"><?= htmlspecialchars($itin['overview']??'') ?></textarea></div>
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
    <div style="font-family:'Times New Roman',Times,serif;font-size:1.5rem" id="itin-total">$0</div>
  </div>
  <div style="text-align:center">
    <div style="font-size:.58rem;letter-spacing:.12em;text-transform:uppercase;opacity:.5">Travelers (for preview)</div>
    <input type="number" id="preview_pax" value="2" min="1" max="20"
           style="width:56px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:var(--white);text-align:center;padding:.35rem;font-size:1rem;font-family:inherit"
           oninput="calcItinTotal()">
  </div>
  <div style="text-align:right">
    <div style="font-size:.58rem;letter-spacing:.12em;text-transform:uppercase;opacity:.5">Per Person</div>
    <div style="font-family:'Times New Roman',Times,serif;font-size:1.3rem" id="itin-pp">$0</div>
  </div>
</div>

<!-- DAY-BY-DAY BUILDER -->
<div id="days-container">
<?php foreach($days as $i=>$day): ?>
<div class="day-block admin-card" data-day="<?= $i ?>" style="margin-bottom:1rem;border-left:3px solid var(--black)">
  <div class="admin-card-header" style="background:var(--gray-50)">
    <h3 style="font-size:.85rem">
      <span class="day-num-label" style="font-family:'Times New Roman',Times,serif;color:var(--black);margin-right:.5rem">Day <?= $day['day'] ?></span>
      <input name="day_title[]" class="admin-input" style="display:inline;width:auto;min-width:200px;font-size:.85rem;border:none;background:transparent;font-weight:600;padding:.15rem .35rem"
             placeholder="Day title e.g. Arusha → Tarangire"
             value="<?= htmlspecialchars($day['title']) ?>">
    </h3>
    <div style="display:flex;align-items:center;gap:.5rem">
      <label class="day-arrival-toggle" style="display:<?= $i===0?'inline-flex':'none' ?>;align-items:center;gap:.3rem;font-size:.65rem;color:var(--gray-500);font-weight:600;cursor:pointer;white-space:nowrap">
        <input type="checkbox" name="day_is_arrival[<?= $i ?>]" value="1" <?= !empty($day['is_arrival'])?'checked':'' ?> onchange="renumberDays()">
        Arrival day — pickup &amp; overnight (Day 0)
      </label>
      <span class="day-cost-badge" style="font-size:.65rem;background:var(--black);color:var(--white);padding:.2rem .6rem;font-weight:600">$0</span>
      <button type="button" onclick="deleteDay(this)" class="admin-icon-btn admin-icon-btn--danger" title="Delete this day"><?= \App\Helpers\AdminIcon::render('trash', 14) ?></button>
    </div>
  </div>

  <div class="admin-card-body">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
      <!-- Location -->
      <div><label class="admin-label" for="day_location-<?= $i ?>">Location / Park</label>
        <input id="day_location-<?= $i ?>" name="day_location[]" class="admin-input day-location" list="location-list" data-idx="<?= $i ?>" oninput="filterDayOptions(<?= $i ?>)"
               value="<?= htmlspecialchars($day['location']??'') ?>" placeholder="e.g. Serengeti National Park"></div>
      <!-- Description -->
      <div><label class="admin-label" for="day_desc-<?= $i ?>">Day Description</label>
        <textarea id="day_desc-<?= $i ?>" name="day_desc[]" class="admin-input" rows="2" placeholder="What happens this day…"><?= htmlspecialchars($day['description']??'') ?></textarea></div>
    </div>

    <div class="day-resource-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">

      <!-- Accommodation -->
      <div style="border:1px solid #e5e7eb;padding:.85rem;background:#eff6ff20">
        <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#1d4ed8;display:flex;align-items:center;gap:.35rem"><?= \App\Helpers\AdminIcon::render('accommodations', 13) ?> Accommodation</p>
        <input type="hidden" name="day_acc_id[]" class="day-acc-id" value="<?= (int)($day['accommodation_id']??0) ?>">
        <div style="margin-bottom:.45rem"><label class="admin-label" for="day_acc_name-<?= $i ?>">Property</label>
          <input id="day_acc_name-<?= $i ?>" name="day_acc_name[]" class="admin-input day-acc-name" list="acc-list-<?= $i ?>"
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
          <div><label class="admin-label" for="day_acc_rate-<?= $i ?>">Rate/Room/Night</label>
            <input id="day_acc_rate-<?= $i ?>" name="day_acc_rate[]" type="number" step="1" class="admin-input day-acc-rate"
                   value="<?= (float)($day['accommodation_rate']??0) ?>" oninput="calcItinTotal()"></div>
          <div><label class="admin-label" for="day_acc_rooms-<?= $i ?>">Rooms</label>
            <input id="day_acc_rooms-<?= $i ?>" name="day_acc_rooms[]" type="number" min="1" class="admin-input"
                   value="<?= (int)($day['accommodation_rooms']??1) ?>" oninput="calcItinTotal()"></div>
        </div>
      </div>

      <!-- Vehicle -->
      <div style="border:1px solid #e5e7eb;padding:.85rem;background:#f0fdf420">
        <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#166534;display:flex;align-items:center;gap:.35rem"><?= \App\Helpers\AdminIcon::render('fleet', 13) ?> Vehicle</p>
        <input type="hidden" name="day_veh_id[]" class="day-veh-id" value="<?= (int)($day['vehicle_id']??0) ?>">
        <div style="margin-bottom:.45rem"><label class="admin-label" for="day_veh_name-<?= $i ?>">Vehicle</label>
          <input id="day_veh_name-<?= $i ?>" name="day_veh_name[]" class="admin-input day-veh-name" list="veh-list-<?= $i ?>"
                 value="<?= htmlspecialchars($day['vehicle_name']??'') ?>"
                 oninput="calcItinTotal()" onchange="fillDayVeh(this,<?= $i ?>)"
                 placeholder="Select or type">
          <datalist id="veh-list-<?= $i ?>">
            <?php foreach($vehicles as $v): $total=(float)$v['daily_rate']+(float)$v['fuel_cost_day']+(float)$v['driver_cost_day']; ?>
            <option value="<?= htmlspecialchars($v['name']) ?>" data-id="<?= $v['id'] ?>" data-rate="<?= $total ?>">
            <?php endforeach; ?>
          </datalist>
        </div>
        <div><label class="admin-label" for="day_veh_rate-<?= $i ?>">Daily Rate (incl. fuel+driver)</label>
          <input id="day_veh_rate-<?= $i ?>" name="day_veh_rate[]" type="number" step="1" class="admin-input day-veh-rate"
                 value="<?= (float)($day['vehicle_rate']??0) ?>" oninput="calcItinTotal()"></div>
      </div>

      <!-- Park + Meals -->
      <div style="border:1px solid #e5e7eb;padding:.85rem;background:#fef3e220">
        <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#92400e;display:flex;align-items:center;gap:.35rem"><?= \App\Helpers\AdminIcon::render('park-fees', 13) ?> Park & Meals</p>
        <input type="hidden" name="day_park_id[]" value="<?= (int)($day['park_fee_id']??0) ?>">
        <div style="margin-bottom:.45rem"><label class="admin-label" for="day_park_name-<?= $i ?>">Park / Area</label>
          <input id="day_park_name-<?= $i ?>" name="day_park_name[]" class="admin-input" list="park-list-<?= $i ?>"
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
          <div><label class="admin-label" for="day_park_fee-<?= $i ?>">Adult Fee</label>
            <input id="day_park_fee-<?= $i ?>" name="day_park_fee[]" type="number" step="0.01" class="admin-input day-park-fee"
                   value="<?= (float)($day['park_adult_fee']??0) ?>" oninput="calcItinTotal()"></div>
          <div><label class="admin-label" for="day_meals_pp-<?= $i ?>">Meals/pp</label>
            <input id="day_meals_pp-<?= $i ?>" name="day_meals_pp[]" type="number" step="0.01" class="admin-input"
                   value="<?= (float)($day['meals_cost_pp']??20) ?>" oninput="calcItinTotal()"></div>
        </div>
      </div>

    </div>

    <!-- Activities for this day -->
    <div style="margin-bottom:1rem">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
        <p class="day-acts-label" style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Activities (Day <?= $day['day'] ?>)</p>
        <button type="button" onclick="addDayActivity(<?= $i ?>)" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>
      </div>
      <datalist id="act-list-<?= $i ?>">
        <?php foreach($activitiesDB as $a): ?>
        <option value="<?= htmlspecialchars($a['name']) ?>" data-id="<?= $a['id'] ?>" data-price="<?= $a['price'] ?>">
        <?php endforeach; ?>
        <?php foreach($genericActivityLabels as $__g): ?>
        <option value="<?= htmlspecialchars($__g) ?>">
        <?php endforeach; ?>
      </datalist>
      <div id="day-acts-<?= $i ?>">
        <?php foreach($day['activities'] as $ai=>$act): ?>
        <div class="act-item" style="display:grid;grid-template-columns:2fr 80px 90px auto;gap:.35rem;align-items:end;margin-bottom:.4rem">
          <input name="act_name_<?= $i ?>_<?= $ai ?>" class="admin-input day-act-name" list="act-list-<?= $i ?>"
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
        <p class="day-other-label" style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Other Costs (Day <?= $day['day'] ?>)</p>
        <button type="button" onclick="addDayOther(<?= $i ?>)" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>
      </div>
      <div id="day-other-<?= $i ?>">
        <?php foreach($day['other_items'] as $oi=>$ot): ?>
        <div class="other-item" style="border:1px solid var(--gray-200);border-radius:2px;padding:.5rem;margin-bottom:.5rem">
          <div style="display:flex;gap:.35rem;margin-bottom:.35rem">
            <input class="admin-input day-other-name" value="<?= htmlspecialchars($ot['name']??'') ?>" placeholder="Item name" style="font-size:.78rem;flex:1" oninput="saveDayOther(<?= $i ?>)">
            <button type="button" onclick="this.closest('.other-item').remove();saveDayOther(<?= $i ?>)"
                    class="admin-icon-btn admin-icon-btn--danger"><?= \App\Helpers\AdminIcon::render('trash', 14) ?></button>
          </div>
          <div style="display:grid;grid-template-columns:70px 55px 1fr 150px;gap:.35rem;align-items:center">
            <input type="number" step="0.01" class="admin-input day-other-amount" value="<?= (float)($ot['amount']??0) ?>" placeholder="$/unit" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
            <input type="number" min="1" class="admin-input day-other-qty" title="Number of units" value="<?= (int)($ot['qty']??1) ?>" placeholder="Qty" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
            <input class="admin-input day-other-unit" title="What a unit is called (e.g. Box, Trip, Vehicle) — type your own, independent of traveler count" value="<?= htmlspecialchars($ot['unit']??'') ?>" placeholder="Unit name (optional)" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
            <select class="admin-input day-other-perpax" style="font-size:.72rem" title="Whether this cost multiplies by the number of travelers" onchange="saveDayOther(<?= $i ?>)">
              <option value="0" <?= empty($ot['per_pax'])?'selected':'' ?>>Flat (not per guest)</option>
              <option value="1" <?= !empty($ot['per_pax'])?'selected':'' ?>>× Number of guests</option>
            </select>
          </div>
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
      <div><label class="admin-label" style="display:flex;align-items:center;gap:.3rem" for="includes"><?= \App\Helpers\AdminIcon::render('check', 12) ?> Included</label>
        <textarea id="includes" name="includes" class="admin-input" rows="6"><?= htmlspecialchars($includes) ?></textarea></div>
      <div><label class="admin-label" style="display:flex;align-items:center;gap:.3rem" for="excludes"><?= \App\Helpers\AdminIcon::render('close', 12) ?> Not Included</label>
        <textarea id="excludes" name="excludes" class="admin-input" rows="6"><?= htmlspecialchars($excludes) ?></textarea></div>
    </div>
  </div>
</div>

<!-- Save -->
<div style="display:flex;gap:.75rem">
  <button type="submit" class="admin-btn admin-btn--primary" style="padding:.85rem 2.5rem">Save Itinerary</button>
  <a href="itineraries.php" class="admin-btn admin-btn--muted">Cancel</a>
</div>

</form>

<!-- Location datalist — same master list every location/country field in
     the app autocompletes from (see App\Services\LocationRegistry) -->
<datalist id="location-list">
  <?php foreach ($allLocations as $__l): ?>
  <option value="<?= htmlspecialchars($__l['name']) ?>">
  <?php endforeach; ?>
</datalist>

<script>
// ── Reference data + day-block template, for dynamically adding days
//    when "Days" is increased (syncDayCount below) — mirrors the exact
//    PHP-rendered markup above so a day added via JS behaves identically
//    to one rendered server-side (same class names, same datalist ids). ──
var ITIN_ACCS  = <?= json_encode(array_map(fn($a) => ['name'=>$a['name'],'id'=>(int)$a['id'],'rate'=>max((float)$a['rate_per_room'],(float)$a['rate_per_person']),'level'=>$a['level'],'location'=>$a['location']??''], $accommodations)) ?>;
var ITIN_VEHS  = <?= json_encode(array_map(fn($v) => ['name'=>$v['name'],'id'=>(int)$v['id'],'rate'=>(float)$v['daily_rate']+(float)$v['fuel_cost_day']+(float)$v['driver_cost_day']], $vehicles)) ?>;
var ITIN_PARKS = <?= json_encode(array_map(fn($p) => ['name'=>$p['name'],'id'=>(int)$p['id'],'fee'=>(float)$p['adult_fee'],'location'=>$p['location']??''], $parkFees)) ?>;
var ITIN_ACTS  = <?= json_encode(array_map(fn($a) => ['name'=>$a['name'],'id'=>(int)$a['id'],'price'=>(float)$a['price'],'location'=>$a['location']??''], $activitiesDB)) ?>;
var ITIN_GENERIC_ACTS = <?= json_encode($genericActivityLabels) ?>;
var ITIN_GENERIC_ACT_OPTS = ITIN_GENERIC_ACTS.map(function(g){ return '<option value="'+g+'">'; }).join('');

// Whether the first day-block's "arrival day" checkbox is checked — index
// 0 always already exists in the DOM (an itinerary can never have 0 days),
// so newly-added days just need to read it, not track it themselves.
function isArrivalDayFlagged() {
  var first = document.querySelector('#days-container .day-block');
  var cb = first ? first.querySelector('.day-arrival-toggle input[type=checkbox]') : null;
  return !!(cb && cb.checked);
}

function itinDayBlockHtml(i) {
  var accOpts  = ITIN_ACCS.map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-rate="'+a.rate+'" data-level="'+a.level+'">'; }).join('');
  var vehOpts  = ITIN_VEHS.map(function(v){ return '<option value="'+v.name+'" data-id="'+v.id+'" data-rate="'+v.rate+'">'; }).join('');
  var parkOpts = ITIN_PARKS.map(function(p){ return '<option value="'+p.name+'" data-id="'+p.id+'" data-fee="'+p.fee+'">'; }).join('');
  var actOpts  = ITIN_ACTS.map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-price="'+a.price+'">'; }).join('') + ITIN_GENERIC_ACT_OPTS;
  var dayNum   = isArrivalDayFlagged() ? i : i+1;

  return ''
    +'<div class="admin-card-header" style="background:var(--gray-50)">'
      +'<h3 style="font-size:.85rem">'
        +'<span class="day-num-label" style="font-family:\'Times New Roman\',Times,serif;color:var(--black);margin-right:.5rem">Day '+dayNum+'</span>'
        +'<input name="day_title[]" class="admin-input" style="display:inline;width:auto;min-width:200px;font-size:.85rem;border:none;background:transparent;font-weight:600;padding:.15rem .35rem" placeholder="Day title e.g. Arusha → Tarangire">'
      +'</h3>'
      +'<div style="display:flex;align-items:center;gap:.5rem">'
        +'<label class="day-arrival-toggle" style="display:none;align-items:center;gap:.3rem;font-size:.65rem;color:var(--gray-500);font-weight:600;cursor:pointer;white-space:nowrap">'
          +'<input type="checkbox" name="day_is_arrival['+i+']" value="1" onchange="renumberDays()">'
          +'Arrival day — pickup &amp; overnight (Day 0)'
        +'</label>'
        +'<span class="day-cost-badge" style="font-size:.65rem;background:var(--black);color:var(--white);padding:.2rem .6rem;font-weight:600">$0</span>'
        +'<button type="button" onclick="deleteDay(this)" class="admin-icon-btn admin-icon-btn--danger" title="Delete this day">'+<?= $trashIconJs ?>+'</button></div>'
    +'</div>'
    +'<div class="admin-card-body">'
      +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">'
        +'<div><label class="admin-label" for="day_location-2-'+i+'">Location / Park</label><input id="day_location-2-'+i+'" name="day_location[]" class="admin-input day-location" list="location-list" data-idx="'+i+'" oninput="filterDayOptions('+i+')" placeholder="e.g. Serengeti National Park"></div>'
        +'<div><label class="admin-label" for="day_desc-2-'+i+'">Day Description</label><textarea id="day_desc-2-'+i+'" name="day_desc[]" class="admin-input" rows="2" placeholder="What happens this day…"></textarea></div>'
      +'</div>'
      +'<div class="day-resource-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">'
        +'<div style="border:1px solid #e5e7eb;padding:.85rem;background:#eff6ff20">'
          +'<p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#1d4ed8;display:flex;align-items:center;gap:.35rem"><?= \App\Helpers\AdminIcon::render('accommodations', 13) ?> Accommodation</p>'
          +'<input type="hidden" name="day_acc_id[]" class="day-acc-id" value="0">'
          +'<div style="margin-bottom:.45rem"><label class="admin-label" for="day_acc_name-2-'+i+'">Property</label>'
            +'<input id="day_acc_name-2-'+i+'" name="day_acc_name[]" class="admin-input day-acc-name" list="acc-list-'+i+'" oninput="calcItinTotal()" onchange="fillDayAcc(this,'+i+')" placeholder="Select or type">'
            +'<datalist id="acc-list-'+i+'">'+accOpts+'</datalist>'
          +'</div>'
          +'<div style="display:grid;grid-template-columns:1fr 60px;gap:.35rem">'
            +'<div><label class="admin-label" for="day_acc_rate-2-'+i+'">Rate/Room/Night</label><input id="day_acc_rate-2-'+i+'" name="day_acc_rate[]" type="number" step="1" class="admin-input day-acc-rate" value="0" oninput="calcItinTotal()"></div>'
            +'<div><label class="admin-label" for="day_acc_rooms-2-'+i+'">Rooms</label><input id="day_acc_rooms-2-'+i+'" name="day_acc_rooms[]" type="number" min="1" class="admin-input" value="1" oninput="calcItinTotal()"></div>'
          +'</div>'
        +'</div>'
        +'<div style="border:1px solid #e5e7eb;padding:.85rem;background:#f0fdf420">'
          +'<p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#166534;display:flex;align-items:center;gap:.35rem"><?= \App\Helpers\AdminIcon::render('fleet', 13) ?> Vehicle</p>'
          +'<input type="hidden" name="day_veh_id[]" class="day-veh-id" value="0">'
          +'<div style="margin-bottom:.45rem"><label class="admin-label" for="day_veh_name-2-'+i+'">Vehicle</label>'
            +'<input id="day_veh_name-2-'+i+'" name="day_veh_name[]" class="admin-input day-veh-name" list="veh-list-'+i+'" oninput="calcItinTotal()" onchange="fillDayVeh(this,'+i+')" placeholder="Select or type">'
            +'<datalist id="veh-list-'+i+'">'+vehOpts+'</datalist>'
          +'</div>'
          +'<div><label class="admin-label" for="day_veh_rate-2-'+i+'">Daily Rate (incl. fuel+driver)</label><input id="day_veh_rate-2-'+i+'" name="day_veh_rate[]" type="number" step="1" class="admin-input day-veh-rate" value="0" oninput="calcItinTotal()"></div>'
        +'</div>'
        +'<div style="border:1px solid #e5e7eb;padding:.85rem;background:#fef3e220">'
          +'<p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#92400e;display:flex;align-items:center;gap:.35rem"><?= \App\Helpers\AdminIcon::render('park-fees', 13) ?> Park &amp; Meals</p>'
          +'<input type="hidden" name="day_park_id[]" value="0">'
          +'<div style="margin-bottom:.45rem"><label class="admin-label" for="day_park_name-2-'+i+'">Park / Area</label>'
            +'<input id="day_park_name-2-'+i+'" name="day_park_name[]" class="admin-input" list="park-list-'+i+'" oninput="calcItinTotal()" onchange="fillDayPark(this,'+i+')" placeholder="Select or leave blank">'
            +'<datalist id="park-list-'+i+'">'+parkOpts+'</datalist>'
          +'</div>'
          +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem">'
            +'<div><label class="admin-label" for="day_park_fee-2-'+i+'">Adult Fee</label><input id="day_park_fee-2-'+i+'" name="day_park_fee[]" type="number" step="0.01" class="admin-input day-park-fee" value="0" oninput="calcItinTotal()"></div>'
            +'<div><label class="admin-label" for="day_meals_pp-2-'+i+'">Meals/pp</label><input id="day_meals_pp-2-'+i+'" name="day_meals_pp[]" type="number" step="0.01" class="admin-input" value="20" oninput="calcItinTotal()"></div>'
          +'</div>'
        +'</div>'
      +'</div>'
      +'<div style="margin-bottom:1rem">'
        +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">'
          +'<p class="day-acts-label" style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Activities (Day '+dayNum+')</p>'
          +'<button type="button" onclick="addDayActivity('+i+')" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>'
        +'</div>'
        +'<datalist id="act-list-'+i+'">'+actOpts+'</datalist>'
        +'<div id="day-acts-'+i+'"></div>'
        +'<input type="hidden" name="day_activities_json[]" id="acts-json-'+i+'" value="[]">'
      +'</div>'
      +'<div>'
        +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">'
          +'<p class="day-other-label" style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Other Costs (Day '+dayNum+')</p>'
          +'<button type="button" onclick="addDayOther('+i+')" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>'
        +'</div>'
        +'<div id="day-other-'+i+'"></div>'
        +'<input type="hidden" name="day_other_json[]" id="other-json-'+i+'" value="[]">'
      +'</div>'
    +'</div>';
}

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

// ── Smart location/level filter for Accommodation + Park options ────
// Narrows each day's Accommodation and Park/Area datalist down to the
// entries whose own `location` shares a significant word with what's
// typed in that day's own Location field — not a plain substring check,
// because this app's location strings are sub-area names ("Central
// Serengeti", "Northern Serengeti", "Western Serengeti Corridor") that
// don't contain one another as substrings even though they're all
// "Serengeti"; a pure indexOf() match would silently show nothing for
// most real combinations. Tokenizing both sides and matching on any
// shared 4+ letter word (park/region names) catches all of these.
// Further narrows Accommodation by the itinerary's overall Accommodation
// Level. This only trims the *suggestions* — the field itself stays free
// text via <datalist>, so an admin can always type something not yet
// listed. Looked up by the day's stable build index (data-idx / the N in
// acc-list-N, park-list-N), not DOM position — those ids never change
// even after a day is deleted and the rest renumber (see deleteDay()).
function locMatch(a, b) {
  if (!a || !b) return true;
  var ta = a.toLowerCase().match(/[a-z]{4,}/g) || [];
  var tb = b.toLowerCase().match(/[a-z]{4,}/g) || [];
  for (var i = 0; i < ta.length; i++) { if (tb.indexOf(ta[i]) !== -1) return true; }
  return false;
}

function filterDayOptions(dayIdx) {
  var locInput = document.querySelector('.day-location[data-idx="'+dayIdx+'"]');
  var loc      = locInput ? locInput.value : '';
  var level    = (document.querySelector('[name="accommodation_level"]')||{}).value || '';

  var accList = document.getElementById('acc-list-'+dayIdx);
  if (accList) {
    accList.innerHTML = ITIN_ACCS
      .filter(function(a){ return (!level || a.level===level) && locMatch(loc, a.location); })
      .map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-rate="'+a.rate+'" data-level="'+a.level+'">'; })
      .join('');
  }
  var parkList = document.getElementById('park-list-'+dayIdx);
  if (parkList) {
    parkList.innerHTML = ITIN_PARKS
      .filter(function(p){ return locMatch(loc, p.location); })
      .map(function(p){ return '<option value="'+p.name+'" data-id="'+p.id+'" data-fee="'+p.fee+'">'; })
      .join('');
  }
  var actList = document.getElementById('act-list-'+dayIdx);
  if (actList) {
    actList.innerHTML = ITIN_ACTS
      .filter(function(a){ return locMatch(loc, a.location); })
      .map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-price="'+a.price+'">'; })
      .join('') + ITIN_GENERIC_ACT_OPTS;
  }
}

function filterAllDayOptions() {
  document.querySelectorAll('.day-location').forEach(function(input){
    filterDayOptions(input.dataset.idx);
  });
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
    var actCost = 0;
    block.querySelectorAll('.act-item').forEach(function(row){
      var price = parseFloat((row.querySelector('.day-act-price')||{}).value)||0;
      var type  = (row.querySelector('.day-act-type')||{}).value||'included';
      if(type==='included') { total += price * pax; actCost += price * pax; }
    });

    // Other costs — same Qty/Unit Name/per-guest fields as
    // quotation_new.php and package_edit.php's Other Costs rows.
    var otherCost = 0;
    block.querySelectorAll('.other-item').forEach(function(row){
      var unitPrice = parseFloat((row.querySelector('.day-other-amount')||{}).value)||0;
      var qty       = parseInt((row.querySelector('.day-other-qty')||{}).value)||1;
      var perPax    = (row.querySelector('.day-other-perpax')||{}).value==='1';
      var amt = unitPrice * (perPax ? qty * pax : qty);
      total += amt;
      otherCost += amt;
    });

    // Day cost badge
    var dayCost = parkFee*pax + meals*pax + accRate*accRooms + vehRate + actCost + otherCost;
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
  div.innerHTML='<input class="admin-input day-act-name" list="act-list-'+dayIdx+'" placeholder="Activity name" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<input type="number" step="0.01" class="admin-input day-act-price" value="0" placeholder="$/pp" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<select class="admin-input day-act-type" style="font-size:.72rem" onchange="saveDayActs('+dayIdx+')"><option value="included">Included</option><option value="optional">Optional</option></select>'
    +'<button type="button" onclick="this.closest(\'.act-item\').remove();saveDayActs('+dayIdx+')" class="admin-icon-btn admin-icon-btn--danger">'+<?= $trashIconJs ?>+'</button>';
  container.appendChild(div);
  div.querySelector('.day-act-name').addEventListener('change', function(){
    var opts=document.querySelectorAll('#act-list-'+dayIdx+' option');
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
  div.style.cssText='border:1px solid var(--gray-200);border-radius:2px;padding:.5rem;margin-bottom:.5rem';
  div.innerHTML='<div style="display:flex;gap:.35rem;margin-bottom:.35rem">'
      +'<input class="admin-input day-other-name" placeholder="Item name" style="font-size:.78rem;flex:1" oninput="saveDayOther('+dayIdx+')">'
      +'<button type="button" onclick="this.closest(\'.other-item\').remove();saveDayOther('+dayIdx+')" class="admin-icon-btn admin-icon-btn--danger">'+<?= $trashIconJs ?>+'</button>'
    +'</div>'
    +'<div style="display:grid;grid-template-columns:70px 55px 1fr 150px;gap:.35rem;align-items:center">'
      +'<input type="number" step="0.01" class="admin-input day-other-amount" value="0" placeholder="$/unit" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
      +'<input type="number" min="1" class="admin-input day-other-qty" title="Number of units" value="1" placeholder="Qty" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
      +'<input class="admin-input day-other-unit" title="What a unit is called (e.g. Box, Trip, Vehicle) — type your own, independent of traveler count" placeholder="Unit name (optional)" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
      +'<select class="admin-input day-other-perpax" style="font-size:.72rem" title="Whether this cost multiplies by the number of travelers" onchange="saveDayOther('+dayIdx+')"><option value="0">Flat (not per guest)</option><option value="1">× Number of guests</option></select>'
    +'</div>';
  container.appendChild(div);
}

function saveDayOther(dayIdx) {
  var container=document.getElementById('day-other-'+dayIdx);
  var items=[];
  container.querySelectorAll('.other-item').forEach(function(row){
    var name=row.querySelector('.day-other-name').value.trim();
    if(!name) return;
    items.push({
      name:name,
      amount:parseFloat(row.querySelector('.day-other-amount').value)||0,
      qty:parseInt(row.querySelector('.day-other-qty').value)||1,
      unit:(row.querySelector('.day-other-unit')||{}).value||'',
      per_pax:row.querySelector('.day-other-perpax').value==='1'
    });
  });
  document.getElementById('other-json-'+dayIdx).value=JSON.stringify(items);
  calcItinTotal();
}

// ── Sync day count with duration input ────────────────────
function syncDayCount(n) {
  n = Math.max(1, Math.min(30, parseInt(n)||1));
  var container = document.getElementById('days-container');
  var existing  = container.querySelectorAll('.day-block');
  var cur       = existing.length;

  // Add missing days
  for (var i = cur; i < n; i++) {
    var div = document.createElement('div');
    div.className = 'day-block admin-card';
    div.dataset.day = i;
    div.style.cssText = 'margin-bottom:1rem;border-left:3px solid var(--black)';
    div.innerHTML = itinDayBlockHtml(i);
    container.appendChild(div);
    filterDayOptions(i);
  }
  // Remove extras
  for(var i=cur-1;i>=n;i--) existing[i].remove();
  calcItinTotal();
}

// ── Delete a single day, from anywhere in the itinerary ─────────
// Unlike shortening via the Days field above (which only ever trims
// from the end), this removes one specific day-block and shifts every
// day after it up. Every day_*[] field is an array-append ("[]") name,
// not an explicit day_title[3]-style index, so the form submission
// already reindexes correctly from DOM order alone once the block is
// gone — renumberDays() only has to fix the *visible* "Day N" labels and
// the Days count, which don't update themselves on their own. Each
// day-block's internal ids (acc-list-N, day-acts-N, etc.) and their
// onclick handlers stay as originally built — they only need to stay
// self-consistent within their own moved block, not match the new
// visible day number.
async function deleteDay(btn) {
  var blocks = document.querySelectorAll('.day-block');
  if (blocks.length <= 1) {
    toast('An itinerary needs at least 1 day — edit this one instead of deleting it.', 'err');
    return;
  }
  if (!(await confirmDialog(
    'Delete this day? Its accommodation, vehicle, park, meals, activities and other costs are removed with it — the remaining days shift up and the total recalculates automatically.',
    {danger: true, title: 'Delete Day', confirmLabel: 'Delete Day'}
  ))) return;

  btn.closest('.day-block').remove();
  renumberDays();
  calcItinTotal();
}

function renumberDays() {
  var blocks = document.querySelectorAll('.day-block');
  var arrival = isArrivalDayFlagged();
  blocks.forEach(function (block, idx) {
    // Only the first block's checkbox ever controls anything — show it
    // there, hide it everywhere else (deleting the original Day 1 just
    // makes the next day's own, already-present-but-hidden checkbox
    // reachable in its place, no extra wiring needed).
    var toggle = block.querySelector('.day-arrival-toggle');
    if (toggle) toggle.style.display = (idx === 0) ? 'inline-flex' : 'none';

    var n2 = (idx === 0 && arrival) ? 0 : (arrival ? idx : idx + 1);
    var dayLabel = block.querySelector('.day-num-label');
    if (dayLabel) dayLabel.textContent = 'Day ' + n2;
    var actsLabel = block.querySelector('.day-acts-label');
    if (actsLabel) actsLabel.textContent = 'Activities (Day ' + n2 + ')';
    var otherLabel = block.querySelector('.day-other-label');
    if (otherLabel) otherLabel.textContent = 'Other Costs (Day ' + n2 + ')';
    block.dataset.day = idx;
  });
  var durInput = document.getElementById('dur_input');
  if (durInput) durInput.value = blocks.length;
}

// Init
filterAllDayOptions();
calcItinTotal();
</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
