<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

$id  = (int)($_GET['id'] ?? 0);
$msg = htmlspecialchars($_GET['msg'] ?? '');
$pkg = null;
if ($id > 0) {
    try { $s=$db->prepare("SELECT * FROM packages WHERE id=?"); $s->execute([$id]); $pkg=$s->fetch(); }
    catch(\Exception $e){}
}

function fetchAll($db,$sql){try{return $db->query($sql)->fetchAll();}catch(\Exception $e){return[];}}
$accommodations = fetchAll($db,"SELECT id,name,level,rate_per_room,rate_per_person FROM accommodations WHERE is_active=1 ORDER BY level,name");
$vehicles       = fetchAll($db,"SELECT id,name,daily_rate,fuel_cost_day,driver_cost_day,capacity FROM vehicles WHERE is_active=1 ORDER BY name");
$parkFees       = fetchAll($db,"SELECT id,name,adult_fee,child_fee FROM park_fees WHERE is_active=1 ORDER BY name");
$activities_db  = fetchAll($db,"SELECT id,name,price,price_unit FROM activities WHERE is_active=1 ORDER BY name");

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pkg'])) {
    $slug = preg_replace('/[^a-z0-9-]/','',strtolower(trim($_POST['slug']??'')));
    $durationDays = max(1,(int)($_POST['duration_days']??5));

    $itinerary = [];
    foreach ($_POST['day_title']??[] as $i=>$title) {
        $itinerary[] = ['day'=>$i+1,'title'=>trim($title),'description'=>trim(($_POST['day_desc']??[])[$i]??'')];
    }
    // Trim to duration
    $itinerary = array_slice($itinerary, 0, $durationDays);

    $includes = array_values(array_filter(array_map('trim',explode("\n",$_POST['includes']??''))));
    $excludes = array_values(array_filter(array_map('trim',explode("\n",$_POST['excludes']??''))));

    // Gallery images from picker
    $galleryImages = [];
    $rawGallery = $_POST['gallery_images'] ?? '';
    if ($rawGallery) {
        foreach (explode(',', $rawGallery) as $imgUrl) {
            $url = trim($imgUrl);
            if ($url) $galleryImages[] = $url;
        }
    }

    $bd = [
        'accommodations'  => [],
        'transport'       => [],
        'park_fees'       => [],
        'activities'      => [],
        'other'           => [],
        'service_charge'  => (float)($_POST['service_charge_pax'] ?? 0),
        'traveler_count'  => max(1,(int)($_POST['traveler_count'] ?? 2)),
        'notes'           => trim($_POST['breakdown_notes']??''),
    ];

    foreach ($_POST['acc_name']??[] as $i=>$name) {
        if(!trim($name)) continue;
        $nights=(int)($_POST['acc_nights'][$i]??1);
        $rate=(float)($_POST['acc_rate'][$i]??0);
        $rooms=(int)($_POST['acc_rooms'][$i]??1);
        $bd['accommodations'][]=['name'=>trim($name),'nights'=>$nights,'rate_per_room'=>$rate,'rooms'=>$rooms,'level'=>trim($_POST['acc_level'][$i]??'midrange'),'subtotal'=>round($nights*$rate*$rooms,2)];
    }

    // Multiple vehicles
    foreach ($_POST['trans_vehicle']??[] as $i=>$vname) {
        if(!trim($vname)) continue;
        $tD=(int)($_POST['trans_days'][$i]??$durationDays);
        $tR=(float)($_POST['trans_rate'][$i]??0);
        $tF=(float)($_POST['trans_fuel'][$i]??0);
        $tDr=(float)($_POST['trans_driver'][$i]??0);
        $bd['transport'][]=['vehicle'=>trim($vname),'days'=>$tD,'daily_rate'=>$tR,'fuel_day'=>$tF,'driver_day'=>$tDr,'subtotal'=>round($tD*($tR+$tF+$tDr),2)];
    }

    foreach ($_POST['park_name']??[] as $i=>$name) {
        if(!trim($name)) continue;
        $af=(float)($_POST['park_adult_fee'][$i]??0);
        $en=(int)($_POST['park_entries'][$i]??1);
        $ad=(int)($_POST['park_adults'][$i]??2);
        $bd['park_fees'][]=['park'=>trim($name),'adult_fee'=>$af,'child_fee'=>(float)($_POST['park_child_fee'][$i]??0),'entries'=>$en,'adults'=>$ad,'subtotal'=>round($af*$ad*$en,2)];
    }

    foreach ($_POST['act_name']??[] as $i=>$name) {
        if(!trim($name)) continue;
        $price=(float)($_POST['act_price'][$i]??0);
        $pax=(int)($_POST['act_pax'][$i]??2);
        $type=$_POST['act_type'][$i]??'included';
        $bd['activities'][]=['name'=>trim($name),'price_pp'=>$price,'type'=>$type,'note'=>trim($_POST['act_note'][$i]??''),'subtotal_2pax'=>round($price*$pax,2)];
    }

    foreach ($_POST['other_name']??[] as $i=>$name) {
        if(!trim($name)) continue;
        $bd['other'][]=['name'=>trim($name),'amount'=>(float)($_POST['other_amount'][$i]??0),'note'=>trim($_POST['other_note'][$i]??'')];
    }

    $data = [
        'slug'                => $slug,
        'name'                => trim($_POST['name']??''),
        'duration_days'       => $durationDays,
        'short_description'   => trim($_POST['short_description']??''),
        'overview'            => trim($_POST['overview']??''),
        'accommodation'       => trim($_POST['accommodation']??''),
        'accommodation_level' => $_POST['accommodation_level']??'midrange',
        'price_1pax'          => (float)($_POST['price_1pax']??0),
        'price_2pax'          => (float)($_POST['price_2pax']??0),
        'price_3pax'          => (float)($_POST['price_3pax']??0),
        'price_notes'         => trim($_POST['price_notes']??''),
        'is_featured'         => isset($_POST['is_featured'])?1:0,
        'is_active'           => isset($_POST['is_active'])?1:0,
        'sort_order'          => (int)($_POST['sort_order']??0),
        'itinerary'           => json_encode($itinerary),
        'includes'            => json_encode($includes),
        'excludes'            => json_encode($excludes),
        'cost_breakdown'      => json_encode($bd),
        'gallery_images'      => json_encode($galleryImages),
    ];

    try {
        if ($id > 0) {
            $set=implode(',',array_map(fn($k)=>"`$k`=?",array_keys($data)));
            $db->prepare("UPDATE packages SET $set WHERE id=?")->execute(array_merge(array_values($data),[$id]));
            $newId=$id;
        } else {
            $cols=implode(',',array_map(fn($k)=>"`$k`",array_keys($data)));
            $phld=implode(',',array_fill(0,count($data),'?'));
            $db->prepare("INSERT INTO packages ($cols,created_at) VALUES ($phld,NOW())")->execute(array_values($data));
            $newId=$db->lastInsertId();
        }
        header("Location: package_edit.php?id=$newId&msg=".urlencode('Package saved.')); exit;
    } catch(\Exception $e){ $msg='Error: '.$e->getMessage(); }
}

// Parse existing
$durationDays = (int)($pkg['duration_days'] ?? 5);
$itinerary  = $pkg ? (json_decode($pkg['itinerary']??'[]',true)?:[]) : [];
$includes   = $pkg ? implode("\n",json_decode($pkg['includes']??'[]',true)?:[]) : '';
$excludes   = $pkg ? implode("\n",json_decode($pkg['excludes']??'[]',true)?:[]) : '';
$bd         = $pkg ? (json_decode($pkg['cost_breakdown']??'{}',true)?:[]) : [];
$galleryImgs= $pkg ? (json_decode($pkg['gallery_images']??'[]',true)?:[]) : [];

// Ensure itinerary has exactly durationDays slots
while(count($itinerary) < $durationDays) $itinerary[]=['day'=>count($itinerary)+1,'title'=>'','description'=>''];
$itinerary = array_slice($itinerary, 0, $durationDays);

$bdAccs  = $bd['accommodations'] ?? [['name'=>'','nights'=>1,'rate_per_room'=>0,'rooms'=>1,'level'=>'midrange','subtotal'=>0]];
$bdTrans = $bd['transport']      ?? [['vehicle'=>'Toyota Land Cruiser 4WD','days'=>$durationDays,'daily_rate'=>250,'fuel_day'=>50,'driver_day'=>50,'subtotal'=>0]];
// Normalise old single-transport format
if (isset($bdTrans['vehicle'])) $bdTrans = [$bdTrans];
$bdParks = $bd['park_fees']      ?? [['park'=>'','adult_fee'=>0,'child_fee'=>0,'entries'=>1,'adults'=>2,'subtotal'=>0]];
$bdActs  = $bd['activities']     ?? [['name'=>'','price_pp'=>0,'type'=>'included','note'=>'']];
$bdOther = $bd['other']          ?? [['name'=>'','amount'=>0,'note'=>'']];
$bdSvcPax = (float)($bd['service_charge'] ?? 0);
$bdTravelers = (int)($bd['traveler_count'] ?? 2);

$pageTitle = $id ? 'Edit Package' : 'New Package';
ob_start();
?>
<?php if($msg): ?>
<div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>">
  <?= $msg ?>
  <?php if($id&&!empty($pkg['slug'])): ?>&nbsp;·&nbsp;<a href="<?= BASE_PATH ?>/packages.php#<?= htmlspecialchars($pkg['slug']) ?>" target="_blank" style="font-weight:700">View Live ↗</a><?php endif; ?>
</div>
<?php endif; ?>

<div style="display:flex;justify-content:flex-end;gap:.5rem;margin-bottom:1.25rem">
  <?php if($id&&!empty($pkg['slug'])): ?>
  <a href="<?= BASE_PATH ?>/packages.php#<?= htmlspecialchars($pkg['slug']) ?>" target="_blank" class="admin-btn admin-btn--outline">View Live ↗</a>
  <?php endif; ?>
  <a href="packages.php" class="admin-btn admin-btn--muted">← All Packages</a>
</div>

<form method="POST" id="pkg-form">
<input type="hidden" name="save_pkg" value="1">
<div style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start">

<!-- ════ LEFT ════ -->
<div>

<!-- Basic Info -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3>Basic Information</h3></div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
      <div><label class="admin-label">Package Name *</label>
        <input name="name" class="admin-input" required value="<?= htmlspecialchars($pkg['name']??'') ?>"></div>
      <div><label class="admin-label">URL Slug *</label>
        <input name="slug" class="admin-input" required pattern="[a-z0-9-]+" value="<?= htmlspecialchars($pkg['slug']??'') ?>"></div>
      <div><label class="admin-label">Duration (nights)</label>
        <input name="duration_days" type="number" min="1" max="60" class="admin-input" id="duration_days"
               value="<?= $durationDays ?>" oninput="syncItineraryDays(this.value)"></div>
      <div><label class="admin-label">Sort Order</label>
        <input name="sort_order" type="number" class="admin-input" value="<?= (int)($pkg['sort_order']??0) ?>"></div>
    </div>
    <div style="margin-bottom:1rem"><label class="admin-label">Short Description (card)</label>
      <textarea name="short_description" class="admin-input" rows="2"><?= htmlspecialchars($pkg['short_description']??'') ?></textarea></div>
    <div><label class="admin-label">Full Overview</label>
      <textarea name="overview" class="admin-input" rows="4"><?= htmlspecialchars($pkg['overview']??'') ?></textarea></div>
  </div>
</div>

<!-- GALLERY IMAGE PICKER -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <h3>Package Gallery Images</h3>
    <div style="display:flex;gap:.5rem">
      <a href="gallery.php" target="_blank" class="admin-btn admin-btn--outline" style="font-size:.65rem">Manage Gallery ↗</a>
      <button type="button" onclick="openGalleryPicker()" class="admin-btn admin-btn--primary" style="font-size:.65rem">+ Select Images</button>
    </div>
  </div>
  <div class="admin-card-body">
    <input type="hidden" name="gallery_images" id="gallery_images_input"
           value="<?= htmlspecialchars(implode(',', $galleryImgs)) ?>">
    <div id="selected-gallery"
         style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.6rem;min-height:80px;padding:.5rem;background:#f9f9f8;border:1.5px dashed #e5e7eb">
      <?php if(empty($galleryImgs)): ?>
      <div id="gallery-placeholder" style="grid-column:1/-1;text-align:center;padding:1.5rem;color:#9ca3af;font-size:.78rem">
        No images selected. Click "Select Images" to pick from gallery.
      </div>
      <?php else: ?>
      <?php foreach($galleryImgs as $imgUrl): ?>
      <div class="selected-img-item" data-url="<?= htmlspecialchars($imgUrl) ?>" style="position:relative;border:1.5px solid #e5e7eb;background:#fff">
        <img src="<?= htmlspecialchars($imgUrl) ?>" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block" loading="lazy">
        <button type="button" onclick="removeSelectedImg(this)"
                style="position:absolute;top:3px;right:3px;background:rgba(220,38,38,.9);border:none;color:#fff;width:20px;height:20px;border-radius:50%;cursor:pointer;font-size:.6rem;display:flex;align-items:center;justify-content:center">✕</button>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <p style="font-size:.68rem;color:#9ca3af;margin-top:.5rem">Drag to reorder · First image = hero image</p>
  </div>
</div>

<!-- COST BREAKDOWN BUILDER -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <h3>Cost Breakdown Builder</h3>
    <span style="font-size:.72rem;color:#9ca3af">Shown publicly as detailed price breakdown</span>
  </div>
  <div class="admin-card-body">

    <!-- Grand total bar -->
    <div style="background:#0a0a0a;color:#fff;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
      <div>
        <div style="font-size:.58rem;letter-spacing:.15em;text-transform:uppercase;opacity:.5;margin-bottom:.2rem">Live Total</div>
        <div style="font-family:Georgia,serif;font-size:1.5rem;font-weight:500" id="gt-total">$0</div>
      </div>
      <div style="text-align:center">
        <div style="font-size:.58rem;letter-spacing:.12em;text-transform:uppercase;opacity:.5;margin-bottom:.2rem">Travelers</div>
        <input type="number" name="traveler_count" id="traveler_count" min="1" max="20"
               value="<?= $bdTravelers ?>"
               style="width:56px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;text-align:center;padding:.35rem;font-size:1rem;font-family:inherit"
               oninput="calcTotals()" title="Number of travelers (for per-person calculation)">
      </div>
      <div style="text-align:right">
        <div style="font-size:.58rem;letter-spacing:.12em;text-transform:uppercase;opacity:.5;margin-bottom:.2rem">Per Person</div>
        <div style="font-family:Georgia,serif;font-size:1.3rem" id="gt-pp">$0</div>
      </div>
    </div>

    <!-- ACCOMMODATIONS -->
    <div style="margin-bottom:2rem">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem">
        <p style="font-size:.62rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase">Accommodations</p>
        <button type="button" onclick="addAccRow()" class="admin-btn admin-btn--outline" style="font-size:.65rem">+ Add Property</button>
      </div>
      <div id="acc-rows">
        <?php foreach($bdAccs as $a): ?>
        <div class="acc-row" style="display:grid;grid-template-columns:2fr 55px 95px 55px 115px 75px auto;gap:.4rem;align-items:end;margin-bottom:.6rem;padding:.75rem;background:#f9f9f8;border:1px solid #e5e7eb">
          <div><label class="admin-label">Property</label>
            <input name="acc_name[]" class="admin-input" list="acc-list" value="<?= htmlspecialchars($a['name']) ?>" oninput="calcTotals()" onchange="fillAccRate(this)"></div>
          <div><label class="admin-label">Nights</label>
            <input name="acc_nights[]" type="number" min="1" class="admin-input" value="<?= (int)($a['nights']??1) ?>" oninput="calcTotals()"></div>
          <div><label class="admin-label">$/Room/Night</label>
            <input name="acc_rate[]" type="number" step="1" class="admin-input" value="<?= (float)($a['rate_per_room']??0) ?>" oninput="calcTotals()"></div>
          <div><label class="admin-label">Rooms</label>
            <input name="acc_rooms[]" type="number" min="1" class="admin-input" value="<?= (int)($a['rooms']??1) ?>" oninput="calcTotals()"></div>
          <div><label class="admin-label">Level</label>
            <select name="acc_level[]" class="admin-input">
              <?php foreach(['budget'=>'Budget','midrange'=>'Mid-Range','highend'=>'High-End','ultra_luxury'=>'Ultra Luxury'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($a['level']??'midrange')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select></div>
          <div><label class="admin-label">Subtotal</label>
            <input class="admin-input acc-sub" readonly style="background:#f3f4f6;font-weight:700" value="$<?= number_format((float)($a['subtotal']??0),0) ?>"></div>
          <div style="padding-top:1.2rem"><button type="button" onclick="this.closest('.acc-row').remove();calcTotals()" class="admin-btn admin-btn--danger" style="padding:.3rem .5rem">✕</button></div>
        </div>
        <?php endforeach; ?>
      </div>
      <datalist id="acc-list">
        <?php foreach($accommodations as $a): ?>
        <option value="<?= htmlspecialchars($a['name']) ?>" data-rate="<?= max((float)$a['rate_per_room'],(float)$a['rate_per_person']) ?>" data-level="<?= $a['level'] ?>">
        <?php endforeach; ?>
      </datalist>
    </div>

    <!-- TRANSPORT — multiple vehicles -->
    <div style="margin-bottom:2rem">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem">
        <p style="font-size:.62rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase">Transport / Vehicles</p>
        <div style="display:flex;gap:.5rem">
          <button type="button" onclick="openAddVehicleModal()" class="admin-btn admin-btn--success" style="font-size:.65rem">+ New Vehicle to DB</button>
          <button type="button" onclick="addTransRow()" class="admin-btn admin-btn--outline" style="font-size:.65rem">+ Add Vehicle Row</button>
        </div>
      </div>
      <div id="trans-rows">
        <?php foreach($bdTrans as $t): ?>
        <div class="trans-row" style="display:grid;grid-template-columns:2fr 55px 90px 75px 75px 90px auto;gap:.4rem;align-items:end;margin-bottom:.6rem;padding:.75rem;background:#f9f9f8;border:1px solid #e5e7eb">
          <div><label class="admin-label">Vehicle</label>
            <input name="trans_vehicle[]" class="admin-input" list="veh-list" oninput="calcTotals()" onchange="fillVehRate(this)"
              value="<?= htmlspecialchars($t['vehicle']??'Toyota Land Cruiser 4WD') ?>">
          </div>
          <div><label class="admin-label">Days</label>
            <input name="trans_days[]" type="number" min="1" class="admin-input" oninput="calcTotals()"
              value="<?= (int)($t['days']??$durationDays) ?>"></div>
          <div><label class="admin-label">Rate/Day</label>
            <input name="trans_rate[]" type="number" step="1" class="admin-input" oninput="calcTotals()"
              value="<?= (float)($t['daily_rate']??250) ?>"></div>
          <div><label class="admin-label">Fuel/Day</label>
            <input name="trans_fuel[]" type="number" step="1" class="admin-input" oninput="calcTotals()"
              value="<?= (float)($t['fuel_day']??50) ?>"></div>
          <div><label class="admin-label">Driver/Day</label>
            <input name="trans_driver[]" type="number" step="1" class="admin-input" oninput="calcTotals()"
              value="<?= (float)($t['driver_day']??50) ?>"></div>
          <div><label class="admin-label">Subtotal</label>
            <input class="admin-input trans-sub" readonly style="background:#f3f4f6;font-weight:700"
              value="$<?= number_format((float)($t['subtotal']??0),0) ?>"></div>
          <div style="padding-top:1.2rem"><button type="button" onclick="this.closest('.trans-row').remove();calcTotals()" class="admin-btn admin-btn--danger" style="padding:.3rem .5rem">✕</button></div>
        </div>
        <?php endforeach; ?>
      </div>
      <datalist id="veh-list">
        <?php foreach($vehicles as $v): ?>
        <option value="<?= htmlspecialchars($v['name']) ?>" data-rate="<?= $v['daily_rate'] ?>" data-fuel="<?= $v['fuel_cost_day'] ?>" data-driver="<?= $v['driver_cost_day'] ?>">
        <?php endforeach; ?>
      </datalist>
    </div>

    <!-- PARK FEES -->
    <div style="margin-bottom:2rem">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem">
        <p style="font-size:.62rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase">Park Entry Fees</p>
        <button type="button" onclick="addParkRow()" class="admin-btn admin-btn--outline" style="font-size:.65rem">+ Add Park</button>
      </div>
      <div id="park-rows">
        <?php foreach($bdParks as $p): ?>
        <div class="park-row" style="display:grid;grid-template-columns:2fr 85px 85px 55px 55px 85px auto;gap:.4rem;align-items:end;margin-bottom:.6rem;padding:.75rem;background:#f9f9f8;border:1px solid #e5e7eb">
          <div><label class="admin-label">Park</label>
            <input name="park_name[]" class="admin-input" list="park-list" value="<?= htmlspecialchars($p['park']??$p['name']??'') ?>" oninput="calcTotals()" onchange="fillParkFee(this)"></div>
          <div><label class="admin-label">Adult Fee</label>
            <input name="park_adult_fee[]" type="number" step="0.01" class="admin-input" value="<?= (float)($p['adult_fee']??0) ?>" oninput="calcTotals()"></div>
          <div><label class="admin-label">Child Fee</label>
            <input name="park_child_fee[]" type="number" step="0.01" class="admin-input" value="<?= (float)($p['child_fee']??0) ?>" oninput="calcTotals()"></div>
          <div><label class="admin-label">Entries</label>
            <input name="park_entries[]" type="number" min="1" class="admin-input" value="<?= (int)($p['entries']??1) ?>" oninput="calcTotals()"></div>
          <div><label class="admin-label">Adults</label>
            <input name="park_adults[]" type="number" min="1" class="admin-input" value="<?= (int)($p['adults']??2) ?>" oninput="calcTotals()"></div>
          <div><label class="admin-label">Subtotal</label>
            <input class="admin-input park-sub" readonly style="background:#f3f4f6;font-weight:700" value="$<?= number_format((float)($p['subtotal']??0),0) ?>"></div>
          <div style="padding-top:1.2rem"><button type="button" onclick="this.closest('.park-row').remove();calcTotals()" class="admin-btn admin-btn--danger" style="padding:.3rem .5rem">✕</button></div>
        </div>
        <?php endforeach; ?>
      </div>
      <datalist id="park-list">
        <?php foreach($parkFees as $p): ?>
        <option value="<?= htmlspecialchars($p['name']) ?>" data-adult="<?= $p['adult_fee'] ?>" data-child="<?= $p['child_fee'] ?>">
        <?php endforeach; ?>
      </datalist>
    </div>

    <!-- ACTIVITIES -->
    <div style="margin-bottom:2rem">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem">
        <p style="font-size:.62rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase">Activities</p>
        <button type="button" onclick="addActRow()" class="admin-btn admin-btn--outline" style="font-size:.65rem">+ Add Activity</button>
      </div>
      <div id="act-rows">
        <?php foreach($bdActs as $a): ?>
        <div class="act-row" style="display:grid;grid-template-columns:2fr 90px 55px 110px 1fr auto;gap:.4rem;align-items:end;margin-bottom:.6rem;padding:.75rem;background:#f9f9f8;border:1px solid #e5e7eb">
          <div><label class="admin-label">Activity</label>
            <input name="act_name[]" class="admin-input" list="act-list" value="<?= htmlspecialchars($a['name']) ?>" oninput="calcTotals()" onchange="fillActPrice(this)"></div>
          <div><label class="admin-label">$/Person</label>
            <input name="act_price[]" type="number" step="0.01" class="admin-input" value="<?= (float)($a['price_pp']??0) ?>" oninput="calcTotals()"></div>
          <div><label class="admin-label">Pax</label>
            <input name="act_pax[]" type="number" min="1" class="admin-input" value="2" oninput="calcTotals()"></div>
          <div><label class="admin-label">Type</label>
            <select name="act_type[]" class="admin-input">
              <option value="included" <?= ($a['type']??'included')==='included'?'selected':'' ?>>Included</option>
              <option value="optional" <?= ($a['type']??'included')==='optional'?'selected':'' ?>>Optional</option>
            </select></div>
          <div><label class="admin-label">Note</label>
            <input name="act_note[]" class="admin-input" value="<?= htmlspecialchars($a['note']??'') ?>"></div>
          <div style="padding-top:1.2rem"><button type="button" onclick="this.closest('.act-row').remove();calcTotals()" class="admin-btn admin-btn--danger" style="padding:.3rem .5rem">✕</button></div>
        </div>
        <?php endforeach; ?>
      </div>
      <datalist id="act-list">
        <?php foreach($activities_db as $a): ?>
        <option value="<?= htmlspecialchars($a['name']) ?>" data-price="<?= $a['price'] ?>">
        <?php endforeach; ?>
      </datalist>
    </div>

    <!-- OTHER + SERVICE CHARGE -->
    <div style="margin-bottom:2rem">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem">
        <p style="font-size:.62rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase">Other Costs</p>
        <button type="button" onclick="addOtherRow()" class="admin-btn admin-btn--outline" style="font-size:.65rem">+ Add Item</button>
      </div>
      <div id="other-rows">
        <?php foreach($bdOther as $o): ?>
        <div class="other-row" style="display:grid;grid-template-columns:2fr 100px 1fr auto;gap:.4rem;align-items:end;margin-bottom:.6rem;padding:.75rem;background:#f9f9f8;border:1px solid #e5e7eb">
          <div><label class="admin-label">Item</label>
            <input name="other_name[]" class="admin-input" value="<?= htmlspecialchars($o['name']) ?>" oninput="calcTotals()"></div>
          <div><label class="admin-label">Amount ($)</label>
            <input name="other_amount[]" type="number" step="1" class="admin-input" value="<?= (float)($o['amount']??0) ?>" oninput="calcTotals()"></div>
          <div><label class="admin-label">Note</label>
            <input name="other_note[]" class="admin-input" value="<?= htmlspecialchars($o['note']??'') ?>"></div>
          <div style="padding-top:1.2rem"><button type="button" onclick="this.closest('.other-row').remove();calcTotals()" class="admin-btn admin-btn--danger" style="padding:.3rem .5rem">✕</button></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- SERVICE CHARGE PER PERSON -->
    <div style="padding:.85rem;background:#f0fdf4;border:1.5px solid #86efac;margin-bottom:1rem">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;align-items:end">
        <div>
          <label class="admin-label">Service Charge ($/person)</label>
          <input name="service_charge_pax" type="number" step="0.01" class="admin-input" id="service_charge_pax"
                 value="<?= $bdSvcPax ?>" oninput="calcTotals()" placeholder="e.g. 50">
        </div>
        <div style="font-size:.8rem;color:#166534;line-height:1.6;padding-top:1.25rem">
          Service charge × travelers = total service fee.<br>
          Shown separately in cost summary.
        </div>
        <div>
          <label class="admin-label">Service Charge Subtotal</label>
          <input id="svc-sub" class="admin-input" readonly style="background:#f3f4f6;font-weight:700" value="$0">
        </div>
      </div>
    </div>

    <!-- NOTES -->
    <div><label class="admin-label">Pricing Notes (shown to guests)</label>
      <textarea name="breakdown_notes" class="admin-input" rows="2" placeholder="e.g. Based on 2 travelers sharing."><?= htmlspecialchars($bd['notes']??'') ?></textarea></div>

  </div>
</div>

<!-- PRICING -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3>Listed Prices (USD per person)</h3>
    <span style="font-size:.72rem;color:#9ca3af">Selling price — can include margin above cost</span>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
      <div><label class="admin-label">1 Person (Solo)</label>
        <input name="price_1pax" type="number" step="1" class="admin-input" value="<?= (float)($pkg['price_1pax']??0) ?>"></div>
      <div><label class="admin-label">2 People (Couple)</label>
        <input name="price_2pax" type="number" step="1" class="admin-input" value="<?= (float)($pkg['price_2pax']??0) ?>"></div>
      <div><label class="admin-label">3+ People (Group)</label>
        <input name="price_3pax" type="number" step="1" class="admin-input" value="<?= (float)($pkg['price_3pax']??0) ?>"></div>
    </div>
    <div><label class="admin-label">Price Notes</label>
      <textarea name="price_notes" class="admin-input" rows="2"><?= htmlspecialchars($pkg['price_notes']??'') ?></textarea></div>
  </div>
</div>


<!-- LOAD FROM ITINERARY TEMPLATE -->
<div class="admin-card" style="margin-bottom:1.25rem;border:1.5px solid #7c3aed">
  <div class="admin-card-header" style="background:#7c3aed;color:#fff">
    <h3 style="color:#fff">Load Itinerary Template</h3>
    <span style="font-size:.72rem;opacity:.7">Fills day-by-day itinerary + auto-sets cost breakdown</span>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:end">
      <div>
        <label class="admin-label">Select Itinerary</label>
        <select id="pkg_itin_select" class="admin-input">
          <option value="">— Load an itinerary template —</option>
          <?php
          $itinList2 = [];
          try { $itinList2 = $db->query("SELECT id,name,duration_days,accommodation_level,base_price_pax FROM itineraries WHERE is_active=1 ORDER BY sort_order,name")->fetchAll(); }
          catch(Exception $e){}
          foreach($itinList2 as $it): ?>
          <option value="<?= $it['id'] ?>" data-dur="<?= $it['duration_days'] ?>" data-level="<?= $it['accommodation_level'] ?>">
            <?= htmlspecialchars($it['name']) ?> — <?= $it['duration_days'] ?>d
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="button" onclick="loadPkgItinerary()" class="admin-btn" style="background:#7c3aed;color:#fff;padding:.75rem 1.5rem">
        ↓ Load Template
      </button>
    </div>
    <p style="font-size:.68rem;color:#9ca3af;margin-top:.4rem">This will overwrite the current day-by-day itinerary below. Cost breakdown stays unchanged.</p>
  </div>
</div>



<!-- ITINERARY — days sync with duration_days -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <h3>Day-by-Day Itinerary</h3>
    <span style="font-size:.72rem;color:#9ca3af" id="itinerary-count">
      <?= $durationDays ?> day<?= $durationDays!=1?'s':'' ?> · matches duration above
    </span>
  </div>
  <div class="admin-card-body" id="itinerary-rows">
    <?php foreach($itinerary as $i=>$day): ?>
    <div class="itinerary-day" style="display:grid;grid-template-columns:130px 1fr;gap:.75rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid #f3f4f6">
      <div>
        <label class="admin-label">Day <?= $i+1 ?> Title</label>
        <input name="day_title[]" class="admin-input" placeholder="e.g. Serengeti Arrival"
               value="<?= htmlspecialchars($day['title']??'') ?>">
      </div>
      <div>
        <label class="admin-label">Description</label>
        <textarea name="day_desc[]" class="admin-input" rows="2"><?= htmlspecialchars($day['description']??'') ?></textarea>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- INCLUDES / EXCLUDES -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3>Includes &amp; Excludes</h3>
    <span style="font-size:.72rem;color:#9ca3af">One item per line</span>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
      <div><label class="admin-label">✓ Included</label>
        <textarea name="includes" class="admin-input" rows="8"><?= htmlspecialchars($includes) ?></textarea></div>
      <div><label class="admin-label">✗ Not Included</label>
        <textarea name="excludes" class="admin-input" rows="8"><?= htmlspecialchars($excludes) ?></textarea></div>
    </div>
  </div>
</div>

<!-- ACCOMMODATION DESC -->
<div class="admin-card">
  <div class="admin-card-header"><h3>Accommodation Description</h3></div>
  <div class="admin-card-body">
    <textarea name="accommodation" class="admin-input" rows="3"><?= htmlspecialchars($pkg['accommodation']??'') ?></textarea>
  </div>
</div>

</div><!-- /left -->

<!-- ════ RIGHT SIDEBAR ════ -->
<div style="position:sticky;top:calc(var(--topbar-h) + 1rem)">

  <!-- Settings -->
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-header"><h3>Settings</h3></div>
    <div class="admin-card-body">
      <div style="margin-bottom:.85rem"><label class="admin-label">Accommodation Level</label>
        <select name="accommodation_level" class="admin-input">
          <?php foreach(['budget'=>'Budget-Friendly','midrange'=>'Mid-Range','highend'=>'High-End / Luxury','ultra_luxury'=>'Ultra Luxury'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($pkg['accommodation_level']??'midrange')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;margin-bottom:.6rem">
        <input type="checkbox" name="is_active" value="1" <?= ($pkg['is_active']??1)?'checked':'' ?>>
        <span class="admin-label" style="margin:0">Active (visible on site)</span>
      </label>
      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
        <input type="checkbox" name="is_featured" value="1" <?= ($pkg['is_featured']??1)?'checked':'' ?>>
        <span class="admin-label" style="margin:0">Featured (homepage)</span>
      </label>
    </div>
  </div>

  <!-- Live Cost Summary -->
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-header"><h3>Live Cost Summary</h3></div>
    <div class="admin-card-body" style="padding:0">
      <table style="width:100%;font-size:.8rem;border-collapse:collapse">
        <tr style="border-bottom:1px solid #f3f4f6">
          <td style="padding:.5rem 1rem">Accommodation</td>
          <td style="padding:.5rem 1rem;text-align:right;font-weight:600" id="s-acc">$0</td>
        </tr>
        <tr style="border-bottom:1px solid #f3f4f6">
          <td style="padding:.5rem 1rem">Transport</td>
          <td style="padding:.5rem 1rem;text-align:right;font-weight:600" id="s-trans">$0</td>
        </tr>
        <tr style="border-bottom:1px solid #f3f4f6">
          <td style="padding:.5rem 1rem">Park Fees</td>
          <td style="padding:.5rem 1rem;text-align:right;font-weight:600" id="s-park">$0</td>
        </tr>
        <tr style="border-bottom:1px solid #f3f4f6">
          <td style="padding:.5rem 1rem">Activities</td>
          <td style="padding:.5rem 1rem;text-align:right;font-weight:600" id="s-act">$0</td>
        </tr>
        <tr style="border-bottom:1px solid #f3f4f6">
          <td style="padding:.5rem 1rem">Other</td>
          <td style="padding:.5rem 1rem;text-align:right;font-weight:600" id="s-other">$0</td>
        </tr>
        <tr style="border-bottom:1px solid #f3f4f6;background:#f0fdf4">
          <td style="padding:.5rem 1rem;color:#166534">Service Charge</td>
          <td style="padding:.5rem 1rem;text-align:right;font-weight:600;color:#166534" id="s-svc">$0</td>
        </tr>
        <tr style="background:#0a0a0a;color:#fff">
          <td style="padding:.75rem 1rem;font-weight:600">Grand Total</td>
          <td style="padding:.75rem 1rem;text-align:right;font-family:Georgia,serif;font-size:1rem" id="s-total">$0</td>
        </tr>
        <tr style="border-bottom:1px solid #f3f4f6">
          <td style="padding:.5rem 1rem;font-size:.75rem;color:#9ca3af" id="s-pp-label">Per Person (÷2)</td>
          <td style="padding:.5rem 1rem;text-align:right;font-size:.85rem;font-weight:700" id="s-pp">$0</td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Save button -->
  <div class="admin-card">
    <div class="admin-card-body">
      <button type="submit" class="admin-btn admin-btn--primary" style="width:100%;justify-content:center;padding:.85rem;margin-bottom:.5rem">
        💾 Save Package
      </button>
      <?php if($id&&!empty($pkg['slug'])): ?>
      <a href="<?= BASE_PATH ?>/packages.php#<?= htmlspecialchars($pkg['slug']) ?>" target="_blank"
         class="admin-btn admin-btn--outline" style="width:100%;justify-content:center">
        View on Site ↗
      </a>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /right -->
</div><!-- /grid -->
</form>

<!-- ════ ADD VEHICLE MODAL ════ -->
<div id="addVehicleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:999;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;width:100%;max-width:480px;border-radius:2px">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid #e5e7eb;background:#0a0a0a;color:#fff">
      <span style="font-size:.9rem;font-weight:600">Add New Vehicle to Database</span>
      <button onclick="document.getElementById('addVehicleModal').style.display='none'" style="background:none;border:none;color:#fff;cursor:pointer;font-size:1.1rem">✕</button>
    </div>
    <div style="padding:1.5rem">
      <div id="veh-err" style="display:none;background:#fef2f2;border-left:3px solid #dc2626;color:#991b1b;padding:.65rem 1rem;font-size:.82rem;margin-bottom:1rem"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
        <div><label class="admin-label">Vehicle Name *</label>
          <input id="veh-name" class="admin-input" placeholder="e.g. Toyota Hilux"></div>
        <div><label class="admin-label">Capacity (seats)</label>
          <input id="veh-cap" type="number" min="1" class="admin-input" value="6"></div>
        <div><label class="admin-label">Daily Rate ($)</label>
          <input id="veh-rate" type="number" step="1" class="admin-input" value="250"></div>
        <div><label class="admin-label">Fuel/Day ($)</label>
          <input id="veh-fuel" type="number" step="1" class="admin-input" value="50"></div>
        <div><label class="admin-label">Driver/Day ($)</label>
          <input id="veh-driver" type="number" step="1" class="admin-input" value="50"></div>
        <div><label class="admin-label">Level</label>
          <select id="veh-level" class="admin-input">
            <option value="standard">Standard</option>
            <option value="luxury">Luxury</option>
          </select></div>
      </div>
      <div style="display:flex;gap:.75rem;justify-content:flex-end">
        <button type="button" onclick="document.getElementById('addVehicleModal').style.display='none'" class="admin-btn admin-btn--outline">Cancel</button>
        <button type="button" onclick="saveNewVehicle()" class="admin-btn admin-btn--primary" id="veh-save-btn">Save Vehicle</button>
      </div>
    </div>
  </div>
</div>

<!-- ════ GALLERY PICKER MODAL ════ -->
<div id="galleryPickerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:999;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;width:100%;max-width:900px;max-height:90vh;display:flex;flex-direction:column;border-radius:2px">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid #e5e7eb;flex-shrink:0">
      <div>
        <span style="font-size:.9rem;font-weight:600">Select Gallery Images</span>
        <span id="picker-selected-count" style="font-size:.72rem;color:#9ca3af;margin-left:.75rem">0 selected</span>
      </div>
      <div style="display:flex;gap:.5rem">
        <button onclick="applyGallerySelection()" class="admin-btn admin-btn--primary" style="font-size:.72rem">Apply Selection</button>
        <button onclick="document.getElementById('galleryPickerModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:1.1rem;color:#9ca3af">✕</button>
      </div>
    </div>

    <!-- Folder tabs -->
    <div style="display:flex;gap:0;border-bottom:1px solid #e5e7eb;overflow-x:auto;flex-shrink:0" id="picker-tabs"></div>

    <!-- Image grid -->
    <div style="flex:1;overflow-y:auto;padding:1rem">
      <input type="text" id="picker-search" placeholder="Filter images…"
             style="border:1.5px solid #e5e7eb;padding:.4rem .75rem;font-size:.78rem;outline:none;width:100%;margin-bottom:1rem"
             oninput="filterPickerImages(this.value)">
      <div id="picker-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.65rem"></div>
    </div>
  </div>
</div>

<script>
var fmt = function(n){ return '$'+Math.round(n).toLocaleString('en-US'); };

// ── Totals ──────────────────────────────────────────────────
function calcTotals() {
  var accT=0, transT=0, parkT=0, actT=0, otherT=0;
  var travelers = +(document.getElementById('traveler_count').value)||1;

  document.querySelectorAll('.acc-row').forEach(function(r){
    var n=+(r.querySelector('[name="acc_nights[]"]').value)||0;
    var rate=+(r.querySelector('[name="acc_rate[]"]').value)||0;
    var rooms=+(r.querySelector('[name="acc_rooms[]"]').value)||1;
    var s=n*rate*rooms; r.querySelector('.acc-sub').value=fmt(s); accT+=s;
  });

  document.querySelectorAll('.trans-row').forEach(function(r){
    var d=+(r.querySelector('[name="trans_days[]"]').value)||0;
    var rate=+(r.querySelector('[name="trans_rate[]"]').value)||0;
    var fuel=+(r.querySelector('[name="trans_fuel[]"]').value)||0;
    var driver=+(r.querySelector('[name="trans_driver[]"]').value)||0;
    var s=d*(rate+fuel+driver); r.querySelector('.trans-sub').value=fmt(s); transT+=s;
  });

  document.querySelectorAll('.park-row').forEach(function(r){
    var af=+(r.querySelector('[name="park_adult_fee[]"]').value)||0;
    var en=+(r.querySelector('[name="park_entries[]"]').value)||1;
    var ad=+(r.querySelector('[name="park_adults[]"]').value)||2;
    var s=af*ad*en; r.querySelector('.park-sub').value=fmt(s); parkT+=s;
  });

  document.querySelectorAll('.act-row').forEach(function(r){
    var price=+(r.querySelector('[name="act_price[]"]').value)||0;
    var pax=+(r.querySelector('[name="act_pax[]"]').value)||2;
    if(r.querySelector('[name="act_type[]"]').value==='included') actT+=price*pax;
  });

  document.querySelectorAll('.other-row').forEach(function(r){
    otherT+=+(r.querySelector('[name="other_amount[]"]').value)||0;
  });

  // Service charge
  var svcPax = +(document.getElementById('service_charge_pax').value)||0;
  var svcTotal = svcPax * travelers;
  document.getElementById('svc-sub').value = fmt(svcTotal);

  var total = accT+transT+parkT+actT+otherT+svcTotal;
  var pp    = travelers > 0 ? total/travelers : total;

  document.getElementById('s-acc').textContent   = fmt(accT);
  document.getElementById('s-trans').textContent  = fmt(transT);
  document.getElementById('s-park').textContent   = fmt(parkT);
  document.getElementById('s-act').textContent    = fmt(actT);
  document.getElementById('s-other').textContent  = fmt(otherT);
  document.getElementById('s-svc').textContent    = fmt(svcTotal);
  document.getElementById('s-total').textContent  = fmt(total);
  document.getElementById('s-pp').textContent     = fmt(pp);
  document.getElementById('s-pp-label').textContent = 'Per Person (÷'+travelers+')';
  document.getElementById('gt-total').textContent = fmt(total);
  document.getElementById('gt-pp').textContent    = fmt(pp)+'/pp';
}

// ── Itinerary sync with duration_days ──────────────────────
function syncItineraryDays(n) {
  n = Math.max(1, Math.min(60, parseInt(n)||1));
  var container = document.getElementById('itinerary-rows');
  var existing  = container.querySelectorAll('.itinerary-day');
  var current   = existing.length;

  // Add days
  for (var i = current; i < n; i++) {
    var div = document.createElement('div');
    div.className = 'itinerary-day';
    div.style.cssText = 'display:grid;grid-template-columns:130px 1fr;gap:.75rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid #f3f4f6';
    div.innerHTML = '<div><label class="admin-label">Day '+(i+1)+' Title</label>'
      +'<input name="day_title[]" class="admin-input" placeholder="e.g. Game Drive"></div>'
      +'<div><label class="admin-label">Description</label>'
      +'<textarea name="day_desc[]" class="admin-input" rows="2"></textarea></div>';
    container.appendChild(div);
  }

  // Remove extra days
  for (var i = current - 1; i >= n; i--) {
    if (existing[i]) existing[i].remove();
  }

  document.getElementById('itinerary-count').textContent = n+' day'+(n!==1?'s':'')+' · matches duration above';
}

// ── Add row helpers ─────────────────────────────────────────
function addAccRow() {
  var d=document.createElement('div'); d.className='acc-row';
  d.style.cssText='display:grid;grid-template-columns:2fr 55px 95px 55px 115px 75px auto;gap:.4rem;align-items:end;margin-bottom:.6rem;padding:.75rem;background:#f9f9f8;border:1px solid #e5e7eb';
  d.innerHTML='<div><label class="admin-label">Property</label><input name="acc_name[]" class="admin-input" list="acc-list" oninput="calcTotals()" onchange="fillAccRate(this)"></div><div><label class="admin-label">Nights</label><input name="acc_nights[]" type="number" min="1" value="1" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">$/Room/Night</label><input name="acc_rate[]" type="number" step="1" value="0" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Rooms</label><input name="acc_rooms[]" type="number" min="1" value="1" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Level</label><select name="acc_level[]" class="admin-input"><option value="budget">Budget</option><option value="midrange" selected>Mid-Range</option><option value="highend">High-End</option><option value="ultra_luxury">Ultra Luxury</option></select></div><div><label class="admin-label">Subtotal</label><input class="admin-input acc-sub" readonly style="background:#f3f4f6;font-weight:700" value="$0"></div><div style="padding-top:1.2rem"><button type="button" onclick="this.closest(\'.acc-row\').remove();calcTotals()" class="admin-btn admin-btn--danger" style="padding:.3rem .5rem">✕</button></div>';
  document.getElementById('acc-rows').appendChild(d); calcTotals();
}

function addTransRow() {
  var days = document.getElementById('duration_days').value || 5;
  var d=document.createElement('div'); d.className='trans-row';
  d.style.cssText='display:grid;grid-template-columns:2fr 55px 90px 75px 75px 90px auto;gap:.4rem;align-items:end;margin-bottom:.6rem;padding:.75rem;background:#f9f9f8;border:1px solid #e5e7eb';
  d.innerHTML='<div><label class="admin-label">Vehicle</label><input name="trans_vehicle[]" class="admin-input" list="veh-list" oninput="calcTotals()" onchange="fillVehRate(this)"></div><div><label class="admin-label">Days</label><input name="trans_days[]" type="number" min="1" value="'+days+'" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Rate/Day</label><input name="trans_rate[]" type="number" step="1" value="250" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Fuel/Day</label><input name="trans_fuel[]" type="number" step="1" value="50" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Driver/Day</label><input name="trans_driver[]" type="number" step="1" value="50" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Subtotal</label><input class="admin-input trans-sub" readonly style="background:#f3f4f6;font-weight:700" value="$0"></div><div style="padding-top:1.2rem"><button type="button" onclick="this.closest(\'.trans-row\').remove();calcTotals()" class="admin-btn admin-btn--danger" style="padding:.3rem .5rem">✕</button></div>';
  document.getElementById('trans-rows').appendChild(d); calcTotals();
}

function addParkRow() {
  var d=document.createElement('div'); d.className='park-row';
  d.style.cssText='display:grid;grid-template-columns:2fr 85px 85px 55px 55px 85px auto;gap:.4rem;align-items:end;margin-bottom:.6rem;padding:.75rem;background:#f9f9f8;border:1px solid #e5e7eb';
  d.innerHTML='<div><label class="admin-label">Park</label><input name="park_name[]" class="admin-input" list="park-list" oninput="calcTotals()" onchange="fillParkFee(this)"></div><div><label class="admin-label">Adult Fee</label><input name="park_adult_fee[]" type="number" step="0.01" value="0" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Child Fee</label><input name="park_child_fee[]" type="number" step="0.01" value="0" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Entries</label><input name="park_entries[]" type="number" min="1" value="1" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Adults</label><input name="park_adults[]" type="number" min="1" value="2" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Subtotal</label><input class="admin-input park-sub" readonly style="background:#f3f4f6;font-weight:700" value="$0"></div><div style="padding-top:1.2rem"><button type="button" onclick="this.closest(\'.park-row\').remove();calcTotals()" class="admin-btn admin-btn--danger" style="padding:.3rem .5rem">✕</button></div>';
  document.getElementById('park-rows').appendChild(d); calcTotals();
}

function addActRow() {
  var d=document.createElement('div'); d.className='act-row';
  d.style.cssText='display:grid;grid-template-columns:2fr 90px 55px 110px 1fr auto;gap:.4rem;align-items:end;margin-bottom:.6rem;padding:.75rem;background:#f9f9f8;border:1px solid #e5e7eb';
  d.innerHTML='<div><label class="admin-label">Activity</label><input name="act_name[]" class="admin-input" list="act-list" oninput="calcTotals()" onchange="fillActPrice(this)"></div><div><label class="admin-label">$/Person</label><input name="act_price[]" type="number" step="0.01" value="0" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Pax</label><input name="act_pax[]" type="number" min="1" value="2" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Type</label><select name="act_type[]" class="admin-input"><option value="included">Included</option><option value="optional">Optional</option></select></div><div><label class="admin-label">Note</label><input name="act_note[]" class="admin-input"></div><div style="padding-top:1.2rem"><button type="button" onclick="this.closest(\'.act-row\').remove();calcTotals()" class="admin-btn admin-btn--danger" style="padding:.3rem .5rem">✕</button></div>';
  document.getElementById('act-rows').appendChild(d); calcTotals();
}

function addOtherRow() {
  var d=document.createElement('div'); d.className='other-row';
  d.style.cssText='display:grid;grid-template-columns:2fr 100px 1fr auto;gap:.4rem;align-items:end;margin-bottom:.6rem;padding:.75rem;background:#f9f9f8;border:1px solid #e5e7eb';
  d.innerHTML='<div><label class="admin-label">Item</label><input name="other_name[]" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Amount ($)</label><input name="other_amount[]" type="number" step="1" value="0" class="admin-input" oninput="calcTotals()"></div><div><label class="admin-label">Note</label><input name="other_note[]" class="admin-input"></div><div style="padding-top:1.2rem"><button type="button" onclick="this.closest(\'.other-row\').remove();calcTotals()" class="admin-btn admin-btn--danger" style="padding:.3rem .5rem">✕</button></div>';
  document.getElementById('other-rows').appendChild(d); calcTotals();
}

// ── Auto-fill from datalists ───────────────────────────────
function fillAccRate(input) {
  var opts=document.querySelectorAll('#acc-list option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var row=input.closest('.acc-row');
      if(opts[i].dataset.rate)  row.querySelector('[name="acc_rate[]"]').value=opts[i].dataset.rate;
      if(opts[i].dataset.level) row.querySelector('[name="acc_level[]"]').value=opts[i].dataset.level;
      calcTotals(); break;
    }
  }
}
function fillVehRate(input) {
  var opts=document.querySelectorAll('#veh-list option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var row=input.closest('.trans-row');
      if(opts[i].dataset.rate)   row.querySelector('[name="trans_rate[]"]').value=opts[i].dataset.rate;
      if(opts[i].dataset.fuel)   row.querySelector('[name="trans_fuel[]"]').value=opts[i].dataset.fuel;
      if(opts[i].dataset.driver) row.querySelector('[name="trans_driver[]"]').value=opts[i].dataset.driver;
      calcTotals(); break;
    }
  }
}
function fillParkFee(input) {
  var opts=document.querySelectorAll('#park-list option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var row=input.closest('.park-row');
      if(opts[i].dataset.adult) row.querySelector('[name="park_adult_fee[]"]').value=opts[i].dataset.adult;
      if(opts[i].dataset.child) row.querySelector('[name="park_child_fee[]"]').value=opts[i].dataset.child;
      calcTotals(); break;
    }
  }
}
function fillActPrice(input) {
  var opts=document.querySelectorAll('#act-list option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var row=input.closest('.act-row');
      if(opts[i].dataset.price) row.querySelector('[name="act_price[]"]').value=opts[i].dataset.price;
      calcTotals(); break;
    }
  }
}

// ── Add Vehicle to DB ──────────────────────────────────────
function openAddVehicleModal() { document.getElementById('addVehicleModal').style.display='flex'; }

async function saveNewVehicle() {
  var name   = document.getElementById('veh-name').value.trim();
  var cap    = document.getElementById('veh-cap').value;
  var rate   = document.getElementById('veh-rate').value;
  var fuel   = document.getElementById('veh-fuel').value;
  var driver = document.getElementById('veh-driver').value;
  var level  = document.getElementById('veh-level').value;
  var errEl  = document.getElementById('veh-err');

  if (!name) { errEl.textContent='Vehicle name is required.'; errEl.style.display='block'; return; }
  errEl.style.display='none';

  var btn = document.getElementById('veh-save-btn');
  btn.disabled=true; btn.textContent='Saving…';

  var fd=new FormData();
  fd.append('action','add_vehicle');
  fd.append('name',name); fd.append('capacity',cap);
  fd.append('daily_rate',rate); fd.append('fuel_cost_day',fuel);
  fd.append('driver_cost_day',driver); fd.append('level',level);

  try {
    var r = await fetch('<?= BASE_PATH ?>/php/vehicle_manage.php',{method:'POST',body:fd,credentials:'same-origin'});
    var d = await r.json();
    if (d.success) {
      // Add to datalist
      var opt = document.createElement('option');
      opt.value = name;
      opt.dataset.rate   = rate;
      opt.dataset.fuel   = fuel;
      opt.dataset.driver = driver;
      document.getElementById('veh-list').appendChild(opt);
      document.getElementById('addVehicleModal').style.display='none';
      // Reset
      document.getElementById('veh-name').value='';
      toast('Vehicle "'+name+'" added to database!');
    } else {
      errEl.textContent = d.message || 'Save failed.'; errEl.style.display='block';
    }
  } catch(e) { errEl.textContent='Network error.'; errEl.style.display='block'; }
  btn.disabled=false; btn.textContent='Save Vehicle';
}

// ── Gallery picker ─────────────────────────────────────────
var allPickerImages = [];
var pickerSelected  = new Set(
  document.getElementById('gallery_images_input').value.split(',').filter(Boolean)
);

async function openGalleryPicker() {
  document.getElementById('galleryPickerModal').style.display='flex';
  if (allPickerImages.length === 0) await loadPickerImages();
  renderPickerGrid(allPickerImages);
}

async function loadPickerImages() {
  try {
    var r = await fetch('<?= BASE_PATH ?>/api/gallery/list.php',{credentials:'same-origin'});
    allPickerImages = await r.json();
  } catch(e) { allPickerImages = []; }
}

function renderPickerGrid(images, filter) {
  var grid  = document.getElementById('picker-grid');
  var tabs  = document.getElementById('picker-tabs');
  filter    = (filter||'').toLowerCase();

  // Build folder tabs
  var folders = [...new Set(images.map(function(img){ return img.folder; }))].sort();
  tabs.innerHTML = '';
  ['all'].concat(folders).forEach(function(f){
    var tab = document.createElement('button');
    tab.type='button';
    tab.textContent = f==='all' ? 'All' : f.replace('packages/','');
    tab.dataset.folder = f;
    tab.style.cssText='padding:.5rem 1rem;font-size:.7rem;font-weight:600;background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;white-space:nowrap;letter-spacing:.06em;text-transform:uppercase;color:#9ca3af';
    tab.onclick = function(){
      document.querySelectorAll('#picker-tabs button').forEach(function(b){ b.style.borderBottomColor='transparent'; b.style.color='#9ca3af'; });
      tab.style.borderBottomColor='#0a0a0a'; tab.style.color='#0a0a0a';
      var filtered = f==='all' ? images : images.filter(function(img){ return img.folder===f; });
      renderPickerGrid(filtered, document.getElementById('picker-search').value);
    };
    tabs.appendChild(tab);
  });
  tabs.children[0].style.borderBottomColor='#0a0a0a';
  tabs.children[0].style.color='#0a0a0a';

  // Render images
  var filtered = filter ? images.filter(function(img){ return img.url.toLowerCase().includes(filter); }) : images;
  grid.innerHTML = '';
  filtered.forEach(function(img) {
    var isSelected = pickerSelected.has(img.url);
    var div = document.createElement('div');
    div.style.cssText='position:relative;cursor:pointer;border:2.5px solid '+(isSelected?'#0a0a0a':'#e5e7eb')+';overflow:hidden;transition:border-color .15s';
    div.dataset.url = img.url;
    div.innerHTML = '<img src="'+img.url+'" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block" loading="lazy">'
      +'<div style="position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;background:'+(isSelected?'#0a0a0a':'rgba(255,255,255,.8)')+';border:2px solid '+(isSelected?'#0a0a0a':'#e5e7eb')+';display:flex;align-items:center;justify-content:center;font-size:.65rem;color:white;transition:all .15s" class="chk">'
      +(isSelected?'✓':'')+'</div>'
      +'<div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.55);color:#fff;font-size:.58rem;padding:.2rem .4rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+img.filename+'</div>';
    div.onclick = function() {
      if (pickerSelected.has(img.url)) {
        pickerSelected.delete(img.url);
        div.style.borderColor='#e5e7eb';
        div.querySelector('.chk').style.background='rgba(255,255,255,.8)';
        div.querySelector('.chk').style.borderColor='#e5e7eb';
        div.querySelector('.chk').textContent='';
      } else {
        pickerSelected.add(img.url);
        div.style.borderColor='#0a0a0a';
        div.querySelector('.chk').style.background='#0a0a0a';
        div.querySelector('.chk').style.borderColor='#0a0a0a';
        div.querySelector('.chk').textContent='✓';
      }
      document.getElementById('picker-selected-count').textContent = pickerSelected.size+' selected';
    };
    grid.appendChild(div);
  });
  document.getElementById('picker-selected-count').textContent = pickerSelected.size+' selected';
}

function filterPickerImages(q) { renderPickerGrid(allPickerImages, q); }

function applyGallerySelection() {
  var urls   = [...pickerSelected];
  var input  = document.getElementById('gallery_images_input');
  input.value = urls.join(',');

  // Update preview
  var container = document.getElementById('selected-gallery');
  var placeholder = document.getElementById('gallery-placeholder');
  if (placeholder) placeholder.remove();
  // Remove old items
  container.querySelectorAll('.selected-img-item').forEach(function(el){ el.remove(); });
  // Add new
  urls.forEach(function(url){
    var div = document.createElement('div');
    div.className='selected-img-item';
    div.dataset.url=url;
    div.style.cssText='position:relative;border:1.5px solid #e5e7eb;background:#fff';
    div.innerHTML='<img src="'+url+'" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block">'
      +'<button type="button" onclick="removeSelectedImg(this)" style="position:absolute;top:3px;right:3px;background:rgba(220,38,38,.9);border:none;color:#fff;width:20px;height:20px;border-radius:50%;cursor:pointer;font-size:.6rem;display:flex;align-items:center;justify-content:center">✕</button>';
    container.appendChild(div);
  });

  if (urls.length === 0) {
    container.innerHTML = '<div id="gallery-placeholder" style="grid-column:1/-1;text-align:center;padding:1.5rem;color:#9ca3af;font-size:.78rem">No images selected.</div>';
  }

  document.getElementById('galleryPickerModal').style.display='none';
}

function removeSelectedImg(btn) {
  var item = btn.closest('.selected-img-item');
  pickerSelected.delete(item.dataset.url);
  item.remove();
  // Update hidden input
  document.getElementById('gallery_images_input').value = [...pickerSelected].join(',');
}

// ── Toast ─────────────────────────────────────────────────
function toast(msg) {
  var t=document.createElement('div');
  t.style.cssText='position:fixed;bottom:1.5rem;right:1.5rem;background:#0a0a0a;color:#fff;padding:.75rem 1.25rem;font-size:.8rem;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.2)';
  t.textContent=msg; document.body.appendChild(t);
  setTimeout(function(){ t.remove(); },3000);
}

// ── Init ──────────────────────────────────────────────────
calcTotals();

// ── Load itinerary template into package editor ────────────
async function loadPkgItinerary() {
  var id = document.getElementById('pkg_itin_select').value;
  if (!id) { alert('Select an itinerary first.'); return; }

  try {
    var r = await fetch((window.BASE_PATH||'')+'/api/itinerary.php?action=get&id='+id, {credentials:'same-origin'});
    var d = await r.json();
    if (!d.success) { alert('Error: '+d.message); return; }

    var itin = d.data;

    // Set duration
    var durInp = document.getElementById('duration_days');
    if (durInp) { durInp.value = itin.duration_days; syncItineraryDays(itin.duration_days); }

    // Set accommodation level
    var lvlSel = document.querySelector('[name="accommodation_level"]');
    if (lvlSel) lvlSel.value = itin.accommodation_level||'midrange';

    // Fill day titles/descriptions
    var days = itin.days || [];
    days.forEach(function(day, i) {
      var titles = document.querySelectorAll('[name="day_title[]"]');
      var descs  = document.querySelectorAll('[name="day_desc[]"]');
      if (titles[i]) titles[i].value = day.title || '';
      if (descs[i])  descs[i].value  = day.description || '';
    });

    alert('Itinerary "'+itin.name+'" loaded — '+itin.duration_days+' days filled. Review and save.');
  } catch(e) { alert('Error: '+e.message); }
}

</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
