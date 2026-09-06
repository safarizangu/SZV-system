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

    // Same per-day shape as the itineraries table's days JSON — this
    // package-level itinerary used to be a much thinner subset (no park
    // fee, no accommodation rooms, no activities/other items), which is
    // why this section couldn't look or behave like the Itinerary
    // Manager no matter how it was styled.
    $itinerary = [];
    foreach ($_POST['day_title']??[] as $i=>$title) {
        $itinerary[] = [
            'day'                 => $i+1,
            'title'               => trim($title),
            'description'         => trim(($_POST['day_desc']??[])[$i]??''),
            'location'            => trim(($_POST['day_location']??[])[$i]??''),
            'accommodation_id'    => (int)(($_POST['day_acc_id']??[])[$i]??0),
            'accommodation_name'  => trim(($_POST['day_acc_name']??[])[$i]??''),
            'accommodation_rate'  => (float)(($_POST['day_acc_rate']??[])[$i]??0),
            'accommodation_rooms' => (int)(($_POST['day_acc_rooms']??[])[$i]??1),
            'vehicle_id'          => (int)(($_POST['day_veh_id']??[])[$i]??0),
            'vehicle_name'        => trim(($_POST['day_veh_name']??[])[$i]??''),
            'vehicle_rate'        => (float)(($_POST['day_veh_rate']??[])[$i]??0),
            'park_fee_id'         => (int)(($_POST['day_park_id']??[])[$i]??0),
            'park_fee_name'       => trim(($_POST['day_park_name']??[])[$i]??''),
            'park_adult_fee'      => (float)(($_POST['day_park_fee']??[])[$i]??0),
            'meals_cost_pp'       => (float)(($_POST['day_meals_pp']??[])[$i]??0),
            'activities'          => json_decode(($_POST['day_activities_json']??[])[$i]??'[]', true) ?: [],
            'other_items'         => json_decode(($_POST['day_other_json']??[])[$i]??'[]', true) ?: [],
        ];
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

    // Accommodation/vehicle/park/activity costs all now come from the
    // Day-by-Day Itinerary above ($itinerary, built further up) instead
    // of being re-typed into a separate set of rows here — the only real
    // input left for the quotation cost summary is the service charge.
    $bd = [
        'service_charge_pax' => (float)($_POST['service_charge_pax'] ?? 0),
        'traveler_count'      => max(1,(int)($_POST['traveler_count'] ?? 2)),
        'notes'               => trim($_POST['breakdown_notes']??''),
    ];

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
        'itinerary_id'        => !empty($_POST['itinerary_id']) ? (int)$_POST['itinerary_id'] : null,
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

// The cost breakdown used to duplicate accommodation/vehicle/park/activity
// data the admin had already entered per day in the Day-by-Day Itinerary
// above — re-typing the same lodge and vehicle rates twice, with no
// guarantee the two ever agreed. It's now computed live from those same
// day-blocks (see calcTotals() below); service_charge_pax is the only
// value that has nowhere else to come from, so it's still stored here.
// 'service_charge' is the old key, kept as a fallback for packages saved
// before this change.
$bdSvcPax    = (float)($bd['service_charge_pax'] ?? $bd['service_charge'] ?? 0);
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
    <input type="hidden" name="itinerary_id" id="selected_itinerary_id" value="<?= (int)($pkg['itinerary_id'] ?? 0) ?>">
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
    <p style="font-size:.68rem;color:#9ca3af;margin-top:.5rem">First image = hero image · remove and re-select in the order you want to reorder</p>
  </div>
</div>

<!-- PRICING -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3>Listed Prices (USD per person)</h3>
    <span style="font-size:.72rem;color:#9ca3af">Selling price — can include margin above cost</span>
  </div>
  <div class="admin-card-body">
    <div style="background:#f7f7f5;border:1px solid #e5e7eb;padding:.65rem .85rem;margin-bottom:1rem;font-size:.75rem;color:#6b7280">
      These are typed independently of the Cost Breakdown Builder below by default — nothing keeps them in sync automatically. Use <strong>↓ Sync</strong> on each tier to pull that tier's live per-person total from the breakdown into the field, so the listed price actually reflects what the breakdown adds up to.
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
      <div>
        <label class="admin-label">1 Person (Solo)</label>
        <input name="price_1pax" id="price_1pax" type="number" step="1" class="admin-input" value="<?= (float)($pkg['price_1pax']??0) ?>">
        <button type="button" onclick="syncPriceFromBreakdown(1)" class="admin-btn admin-btn--muted" style="font-size:.62rem;padding:.25rem .5rem;margin-top:.35rem;width:100%">↓ Sync from breakdown</button>
      </div>
      <div>
        <label class="admin-label">2 People (Couple)</label>
        <input name="price_2pax" id="price_2pax" type="number" step="1" class="admin-input" value="<?= (float)($pkg['price_2pax']??0) ?>">
        <button type="button" onclick="syncPriceFromBreakdown(2)" class="admin-btn admin-btn--muted" style="font-size:.62rem;padding:.25rem .5rem;margin-top:.35rem;width:100%">↓ Sync from breakdown</button>
      </div>
      <div>
        <label class="admin-label">3+ People (Group)</label>
        <input name="price_3pax" id="price_3pax" type="number" step="1" class="admin-input" value="<?= (float)($pkg['price_3pax']??0) ?>">
        <button type="button" onclick="syncPriceFromBreakdown(3)" class="admin-btn admin-btn--muted" style="font-size:.62rem;padding:.25rem .5rem;margin-top:.35rem;width:100%">↓ Sync from breakdown</button>
      </div>
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



<!-- ITINERARY — days sync with duration_days. Same arrangement as
     admin/itineraries.php's day-block: a "Day N" + title header with a
     live cost badge, then one colour-coded box per category
     (Accommodation / Vehicle / Park & Meals), then per-day activities
     and other costs. Previously this section was a much thinner
     4-field grid (title, description, 3 flat rate inputs) with no park
     field, no per-day activities, and a day-total badge wired to the
     wrong calculation function. -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <h3>Day-by-Day Itinerary</h3>
    <div style="display:flex;align-items:center;gap:.85rem">
      <span style="font-size:.72rem;color:#9ca3af" id="itinerary-count">
        <?= $durationDays ?> day<?= $durationDays!=1?'s':'' ?> · matches duration above
      </span>
      <button type="button" onclick="openAddVehicleModal()" class="admin-btn admin-btn--outline" style="font-size:.7rem;padding:.3rem .6rem">+ New Vehicle</button>
    </div>
  </div>
  <div class="admin-card-body" id="itinerary-rows">
    <?php foreach($itinerary as $i=>$day): ?>
    <div class="day-block itinerary-day" data-day="<?= $i ?>" style="margin-bottom:1rem;border-left:3px solid #0a0a0a">
      <div class="admin-card-header" style="background:#f7f7f5">
        <h3 style="font-size:.85rem">
          <span style="font-family:Georgia,serif;color:#0a0a0a;margin-right:.5rem">Day <?= $i+1 ?></span>
          <input name="day_title[]" class="admin-input" style="display:inline;width:auto;min-width:200px;font-size:.85rem;border:none;background:transparent;font-weight:600;padding:.15rem .35rem"
                 placeholder="Day title e.g. Arusha → Tarangire"
                 value="<?= htmlspecialchars($day['title']??'') ?>">
        </h3>
        <div style="display:flex;align-items:center;gap:.5rem">
          <span class="day-cost-badge" style="font-size:.65rem;background:#0a0a0a;color:#fff;padding:.2rem .6rem;font-weight:600">$0</span>
        </div>
      </div>

      <div class="admin-card-body">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div><label class="admin-label">Location / Park</label>
            <input name="day_location[]" class="admin-input" value="<?= htmlspecialchars($day['location']??'') ?>" placeholder="e.g. Serengeti National Park"></div>
          <div><label class="admin-label">Day Description</label>
            <textarea name="day_desc[]" class="admin-input" rows="2" placeholder="What happens this day…"><?= htmlspecialchars($day['description']??'') ?></textarea></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">

          <!-- Accommodation -->
          <div style="border:1px solid #e5e7eb;padding:.85rem;background:#eff6ff20">
            <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#1d4ed8">🏕 Accommodation</p>
            <input type="hidden" name="day_acc_id[]" class="day-acc-id" value="<?= (int)($day['accommodation_id']??0) ?>">
            <div style="margin-bottom:.45rem"><label class="admin-label">Property</label>
              <input name="day_acc_name[]" class="admin-input day-acc-name" list="pe-acc-list-<?= $i ?>"
                     value="<?= htmlspecialchars($day['accommodation_name']??'') ?>"
                     oninput="calcItinTotal()" onchange="fillDayAcc(this,<?= $i ?>)"
                     placeholder="Select or type">
              <datalist id="pe-acc-list-<?= $i ?>">
                <?php foreach($accommodations as $a): $r=max((float)$a['rate_per_room'],(float)$a['rate_per_person']); ?>
                <option value="<?= htmlspecialchars($a['name']) ?>" data-id="<?= $a['id'] ?>" data-rate="<?= $r ?>">
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
            <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#166534">🚙 Vehicle</p>
            <input type="hidden" name="day_veh_id[]" class="day-veh-id" value="<?= (int)($day['vehicle_id']??0) ?>">
            <div style="margin-bottom:.45rem"><label class="admin-label">Vehicle</label>
              <input name="day_veh_name[]" class="admin-input day-veh-name" list="pe-veh-list-<?= $i ?>"
                     value="<?= htmlspecialchars($day['vehicle_name']??'') ?>"
                     oninput="calcItinTotal()" onchange="fillDayVeh(this,<?= $i ?>)"
                     placeholder="Select or type">
              <datalist id="pe-veh-list-<?= $i ?>">
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
            <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#92400e">🏞 Park &amp; Meals</p>
            <input type="hidden" name="day_park_id[]" value="<?= (int)($day['park_fee_id']??0) ?>">
            <div style="margin-bottom:.45rem"><label class="admin-label">Park / Area</label>
              <input name="day_park_name[]" class="admin-input" list="pe-park-list-<?= $i ?>"
                     value="<?= htmlspecialchars($day['park_fee_name']??'') ?>"
                     oninput="calcItinTotal()" onchange="fillDayPark(this,<?= $i ?>)"
                     placeholder="Select or leave blank">
              <datalist id="pe-park-list-<?= $i ?>">
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
            <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#6b7280">Activities (Day <?= $i+1 ?>)</p>
            <button type="button" onclick="addDayActivity(<?= $i ?>)" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>
          </div>
          <div id="day-acts-<?= $i ?>">
            <?php foreach(($day['activities']??[]) as $ai=>$act): ?>
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
                      style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:.2rem">✕</button>
            </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="day_activities_json[]" id="acts-json-<?= $i ?>"
                 value="<?= htmlspecialchars(json_encode($day['activities']??[])) ?>">
        </div>

        <!-- Other items for this day -->
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
            <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#6b7280">Other Costs (Day <?= $i+1 ?>)</p>
            <button type="button" onclick="addDayOther(<?= $i ?>)" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>
          </div>
          <div id="day-other-<?= $i ?>">
            <?php foreach(($day['other_items']??[]) as $oi=>$ot): ?>
            <div class="other-item" style="display:grid;grid-template-columns:2fr 100px auto;gap:.35rem;align-items:end;margin-bottom:.4rem">
              <input class="admin-input day-other-name" value="<?= htmlspecialchars($ot['name']??'') ?>" placeholder="Item name" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
              <input type="number" step="0.01" class="admin-input day-other-amount" value="<?= (float)($ot['amount']??0) ?>" placeholder="$" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
              <button type="button" onclick="this.closest('.other-item').remove();saveDayOther(<?= $i ?>)"
                      style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:.2rem">✕</button>
            </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="day_other_json[]" id="other-json-<?= $i ?>"
                 value="<?= htmlspecialchars(json_encode($day['other_items']??[])) ?>">
        </div>

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

<!-- QUOTATION LIVE COST SUMMARY — auto-calculated from the Day-by-Day
     Itinerary above instead of a separate set of rows that duplicated
     the same accommodation/vehicle/park/activity data entry. Service
     charge is the one figure with nowhere else to come from, so it's
     the only thing still entered here — set aside from the read-only
     summary, in its own box, rather than mixed in as another row. -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <h3>Quotation Live Cost Summary</h3>
    <span style="font-size:.72rem;color:#9ca3af">Pulled from the itinerary above · same VAT and deposit math as a real quotation</span>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr 260px;gap:1.5rem;align-items:start">

      <!-- Read-only summary -->
      <div>
        <table style="width:100%;font-size:.85rem;border-collapse:collapse">
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.5rem 0;color:#6b7280">Accommodation</td><td style="padding:.5rem 0;text-align:right;font-weight:600" id="qs-acc">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.5rem 0;color:#6b7280">Vehicle</td><td style="padding:.5rem 0;text-align:right;font-weight:600" id="qs-veh">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.5rem 0;color:#6b7280">Park Fees</td><td style="padding:.5rem 0;text-align:right;font-weight:600" id="qs-park">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.5rem 0;color:#6b7280">Meals</td><td style="padding:.5rem 0;text-align:right;font-weight:600" id="qs-meals">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.5rem 0;color:#6b7280">Activities (included)</td><td style="padding:.5rem 0;text-align:right;font-weight:600" id="qs-act">$0</td></tr>
          <tr style="border-bottom:1px solid #e5e7eb;background:#f0fdf4"><td style="padding:.5rem 0 .5rem .5rem;color:#166534">Service Charge</td><td style="padding:.5rem .5rem .5rem 0;text-align:right;font-weight:600;color:#166534" id="qs-svc">$0</td></tr>
          <tr style="border-top:1px solid #e5e7eb"><td style="padding:.6rem 0;font-weight:600">Subtotal</td><td style="padding:.6rem 0;text-align:right;font-weight:600" id="qs-subtotal">$0</td></tr>
          <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:.4rem 0;color:#6b7280;font-size:.78rem">VAT (18%)</td><td style="padding:.4rem 0;text-align:right;font-size:.78rem" id="qs-vat">$0</td></tr>
          <tr style="background:#0a0a0a;color:#fff"><td style="padding:.75rem;font-weight:700">TOTAL</td><td style="padding:.75rem;text-align:right;font-family:Georgia,serif;font-size:1.1rem" id="qs-total">$0</td></tr>
          <tr><td style="padding:.5rem 0;font-size:.75rem;color:#9ca3af" id="qs-pp-label">Per Person (÷2)</td><td style="padding:.5rem 0;text-align:right;font-size:.9rem;font-weight:700" id="qs-pp">$0</td></tr>
        </table>
      </div>

      <!-- Aside: the only editable inputs — travelers (shared with the
           itinerary day badges above) and service charge -->
      <div style="background:#f7f7f5;border:1px solid #e5e7eb;padding:1rem">
        <label class="admin-label">Travelers</label>
        <input type="number" name="traveler_count" id="traveler_count" min="1" max="20"
               value="<?= $bdTravelers ?>" class="admin-input" style="margin-bottom:1rem"
               oninput="calcTotals()" title="Used for both the itinerary day badges above and this summary">

        <label class="admin-label">Service Charge ($ / person)</label>
        <input name="service_charge_pax" type="number" step="0.01" class="admin-input" id="service_charge_pax"
               value="<?= $bdSvcPax ?>" oninput="calcTotals()" placeholder="e.g. 50">
        <p style="font-size:.68rem;color:#9ca3af;margin-top:.5rem;line-height:1.5">The only cost entered directly here — everything else above comes from the Day-by-Day Itinerary.</p>
      </div>

    </div>

    <!-- NOTES -->
    <div style="margin-top:1.25rem"><label class="admin-label">Pricing Notes (shown to guests)</label>
      <textarea name="breakdown_notes" class="admin-input" rows="2" placeholder="e.g. Based on 2 travelers sharing."><?= htmlspecialchars($bd['notes']??'') ?></textarea></div>

  </div>
</div>

<!-- Shared by the Day-by-Day Itinerary's per-day activity rows above -->
<datalist id="act-list">
  <?php foreach($activities_db as $a): ?>
  <option value="<?= htmlspecialchars($a['name']) ?>" data-price="<?= $a['price'] ?>">
  <?php endforeach; ?>
</datalist>

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

// ── Quotation Live Cost Summary ──────────────────────────────
// Aggregates straight from the Day-by-Day Itinerary's day-blocks —
// same category grouping calcItinTotal() uses for the per-day badges
// (each accommodation/vehicle counted once per unique property across
// all nights/days, not once per day) — then adds VAT, matching how a
// real quotation is actually priced (see App\Services\ItineraryQuoteBuilder::totals()
// and api/quotation_pdf.php). Previously this read a separate set of
// manually-typed rows instead of the itinerary, so the two could
// silently disagree.
function calcTotals() {
  var travelers = +((document.getElementById('traveler_count')||{}).value)||1;
  var accT=0, vehT=0, parkT=0, mealsT=0, actT=0;
  var accSeen = {}, vehSeen = {};

  document.querySelectorAll('.day-block').forEach(function(block){
    var accName = (block.querySelector('.day-acc-name')||{}).value||'';
    var accRate = parseFloat((block.querySelector('.day-acc-rate')||{}).value)||0;
    var accRooms= parseInt((block.querySelector('[name="day_acc_rooms[]"]')||{}).value)||1;
    var vehName = (block.querySelector('.day-veh-name')||{}).value||'';
    var vehRate = parseFloat((block.querySelector('.day-veh-rate')||{}).value)||0;
    var parkFee = parseFloat((block.querySelector('.day-park-fee')||{}).value)||0;
    var meals   = parseFloat((block.querySelector('[name="day_meals_pp[]"]')||{}).value)||0;

    if (accName && !accSeen[accName]) accSeen[accName] = {rate:accRate,rooms:accRooms,nights:0};
    if (accName) accSeen[accName].nights++;

    if (vehName && !vehSeen[vehName]) vehSeen[vehName] = {rate:vehRate,days:0};
    if (vehName) vehSeen[vehName].days++;

    parkT  += parkFee * travelers;
    mealsT += meals * travelers;

    block.querySelectorAll('.act-item').forEach(function(row){
      var price = parseFloat((row.querySelector('.day-act-price')||{}).value)||0;
      var type  = (row.querySelector('.day-act-type')||{}).value||'included';
      if (type==='included') actT += price * travelers;
    });
  });

  Object.values(accSeen).forEach(function(a){ accT += a.rate * a.nights * a.rooms; });
  Object.values(vehSeen).forEach(function(v){ vehT += v.rate * v.days; });

  var svcPax   = +((document.getElementById('service_charge_pax')||{}).value)||0;
  var svcTotal = svcPax * travelers;

  var subtotal = accT + vehT + parkT + mealsT + actT + svcTotal;
  var vat      = subtotal * 0.18;
  var total    = subtotal + vat;
  var pp       = travelers > 0 ? total/travelers : total;

  document.getElementById('qs-acc').textContent      = fmt(accT);
  document.getElementById('qs-veh').textContent      = fmt(vehT);
  document.getElementById('qs-park').textContent     = fmt(parkT);
  document.getElementById('qs-meals').textContent    = fmt(mealsT);
  document.getElementById('qs-act').textContent      = fmt(actT);
  document.getElementById('qs-svc').textContent      = fmt(svcTotal);
  document.getElementById('qs-subtotal').textContent = fmt(subtotal);
  document.getElementById('qs-vat').textContent      = fmt(vat);
  document.getElementById('qs-total').textContent    = fmt(total);
  document.getElementById('qs-pp').textContent       = fmt(pp);
  document.getElementById('qs-pp-label').textContent = 'Per Person (÷'+travelers+')';

  // Exposed so syncPriceFromBreakdown() (Listed Prices card, above) can
  // read the current per-person total without re-parsing formatted text
  // back out of the DOM.
  window.__breakdownPerPerson = pp;
  window.__breakdownTravelers = travelers;
}

// ── Keep Listed Prices honest against the Cost Breakdown Builder ──
// The two were previously fully independent: an admin could build a
// $2,000/pp breakdown and still have a stale $1,200 typed into Listed
// Prices from months ago, and packages.php would happily publish the
// stale number. This doesn't force them to always match (margin above
// cost is often deliberate) — it just makes copying the current
// breakdown total into a tier one click instead of a manual retype
// (and a manual retype is exactly where staleness creeps in).
function syncPriceFromBreakdown(tier) {
  var pp = window.__breakdownPerPerson;
  if (typeof pp !== 'number') { calcTotals(); pp = window.__breakdownPerPerson; }
  var rounded = Math.round(pp || 0);
  document.getElementById('price_' + tier + 'pax').value = rounded;
  toast('Synced ' + tier + '-pax listed price to $' + rounded.toLocaleString('en-US')
      + ' (breakdown at ' + window.__breakdownTravelers + ' traveler'
      + (window.__breakdownTravelers !== 1 ? 's' : '') + ') — set Travelers above to match this tier before syncing each one.');
}

// ── Itinerary sync with duration_days ──────────────────────
// Reference data for building a new day-block's datalists dynamically —
// same accommodations/vehicles/park fees the PHP-rendered day-blocks
// already use, output once here so a freshly-added day (when Nights is
// increased) gets the exact same autocomplete options.
var PE_ACCS  = <?= json_encode(array_map(fn($a) => ['name'=>$a['name'],'id'=>(int)$a['id'],'rate'=>max((float)$a['rate_per_room'],(float)$a['rate_per_person'])], $accommodations)) ?>;
var PE_VEHS  = <?= json_encode(array_map(fn($v) => ['name'=>$v['name'],'id'=>(int)$v['id'],'rate'=>(float)$v['daily_rate']+(float)$v['fuel_cost_day']+(float)$v['driver_cost_day']], $vehicles)) ?>;
var PE_PARKS = <?= json_encode(array_map(fn($p) => ['name'=>$p['name'],'id'=>(int)$p['id'],'fee'=>(float)$p['adult_fee']], $parkFees)) ?>;

function peDayBlockHtml(i) {
  var accOpts  = PE_ACCS.map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-rate="'+a.rate+'">'; }).join('');
  var vehOpts  = PE_VEHS.map(function(v){ return '<option value="'+v.name+'" data-id="'+v.id+'" data-rate="'+v.rate+'">'; }).join('');
  var parkOpts = PE_PARKS.map(function(p){ return '<option value="'+p.name+'" data-id="'+p.id+'" data-fee="'+p.fee+'">'; }).join('');

  return ''
    +'<div class="admin-card-header" style="background:#f7f7f5">'
      +'<h3 style="font-size:.85rem">'
        +'<span style="font-family:Georgia,serif;color:#0a0a0a;margin-right:.5rem">Day '+(i+1)+'</span>'
        +'<input name="day_title[]" class="admin-input" style="display:inline;width:auto;min-width:200px;font-size:.85rem;border:none;background:transparent;font-weight:600;padding:.15rem .35rem" placeholder="Day title e.g. Arusha → Tarangire">'
      +'</h3>'
      +'<div style="display:flex;align-items:center;gap:.5rem"><span class="day-cost-badge" style="font-size:.65rem;background:#0a0a0a;color:#fff;padding:.2rem .6rem;font-weight:600">$0</span></div>'
    +'</div>'
    +'<div class="admin-card-body">'
      +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">'
        +'<div><label class="admin-label">Location / Park</label><input name="day_location[]" class="admin-input" placeholder="e.g. Serengeti National Park"></div>'
        +'<div><label class="admin-label">Day Description</label><textarea name="day_desc[]" class="admin-input" rows="2" placeholder="What happens this day…"></textarea></div>'
      +'</div>'
      +'<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">'
        +'<div style="border:1px solid #e5e7eb;padding:.85rem;background:#eff6ff20">'
          +'<p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#1d4ed8">🏕 Accommodation</p>'
          +'<input type="hidden" name="day_acc_id[]" class="day-acc-id" value="0">'
          +'<div style="margin-bottom:.45rem"><label class="admin-label">Property</label>'
            +'<input name="day_acc_name[]" class="admin-input day-acc-name" list="pe-acc-list-'+i+'" oninput="calcItinTotal()" onchange="fillDayAcc(this,'+i+')" placeholder="Select or type">'
            +'<datalist id="pe-acc-list-'+i+'">'+accOpts+'</datalist>'
          +'</div>'
          +'<div style="display:grid;grid-template-columns:1fr 60px;gap:.35rem">'
            +'<div><label class="admin-label">Rate/Room/Night</label><input name="day_acc_rate[]" type="number" step="1" class="admin-input day-acc-rate" value="0" oninput="calcItinTotal()"></div>'
            +'<div><label class="admin-label">Rooms</label><input name="day_acc_rooms[]" type="number" min="1" class="admin-input" value="1" oninput="calcItinTotal()"></div>'
          +'</div>'
        +'</div>'
        +'<div style="border:1px solid #e5e7eb;padding:.85rem;background:#f0fdf420">'
          +'<p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#166534">🚙 Vehicle</p>'
          +'<input type="hidden" name="day_veh_id[]" class="day-veh-id" value="0">'
          +'<div style="margin-bottom:.45rem"><label class="admin-label">Vehicle</label>'
            +'<input name="day_veh_name[]" class="admin-input day-veh-name" list="pe-veh-list-'+i+'" oninput="calcItinTotal()" onchange="fillDayVeh(this,'+i+')" placeholder="Select or type">'
            +'<datalist id="pe-veh-list-'+i+'">'+vehOpts+'</datalist>'
          +'</div>'
          +'<div><label class="admin-label">Daily Rate (incl. fuel+driver)</label><input name="day_veh_rate[]" type="number" step="1" class="admin-input day-veh-rate" value="0" oninput="calcItinTotal()"></div>'
        +'</div>'
        +'<div style="border:1px solid #e5e7eb;padding:.85rem;background:#fef3e220">'
          +'<p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#92400e">🏞 Park &amp; Meals</p>'
          +'<input type="hidden" name="day_park_id[]" value="0">'
          +'<div style="margin-bottom:.45rem"><label class="admin-label">Park / Area</label>'
            +'<input name="day_park_name[]" class="admin-input" list="pe-park-list-'+i+'" oninput="calcItinTotal()" onchange="fillDayPark(this,'+i+')" placeholder="Select or leave blank">'
            +'<datalist id="pe-park-list-'+i+'">'+parkOpts+'</datalist>'
          +'</div>'
          +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem">'
            +'<div><label class="admin-label">Adult Fee</label><input name="day_park_fee[]" type="number" step="0.01" class="admin-input day-park-fee" value="0" oninput="calcItinTotal()"></div>'
            +'<div><label class="admin-label">Meals/pp</label><input name="day_meals_pp[]" type="number" step="0.01" class="admin-input" value="20" oninput="calcItinTotal()"></div>'
          +'</div>'
        +'</div>'
      +'</div>'
      +'<div style="margin-bottom:1rem">'
        +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">'
          +'<p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#6b7280">Activities (Day '+(i+1)+')</p>'
          +'<button type="button" onclick="addDayActivity('+i+')" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>'
        +'</div>'
        +'<div id="day-acts-'+i+'"></div>'
        +'<input type="hidden" name="day_activities_json[]" id="acts-json-'+i+'" value="[]">'
      +'</div>'
      +'<div>'
        +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">'
          +'<p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#6b7280">Other Costs (Day '+(i+1)+')</p>'
          +'<button type="button" onclick="addDayOther('+i+')" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>'
        +'</div>'
        +'<div id="day-other-'+i+'"></div>'
        +'<input type="hidden" name="day_other_json[]" id="other-json-'+i+'" value="[]">'
      +'</div>'
    +'</div>';
}

function syncItineraryDays(n) {
  n = Math.max(1, Math.min(60, parseInt(n)||1));
  var container = document.getElementById('itinerary-rows');
  var existing  = container.querySelectorAll('.itinerary-day');
  var current   = existing.length;

  // Add days
  for (var i = current; i < n; i++) {
    var div = document.createElement('div');
    div.className = 'day-block itinerary-day';
    div.dataset.day = i;
    div.style.cssText = 'margin-bottom:1rem;border-left:3px solid #0a0a0a';
    div.innerHTML = peDayBlockHtml(i);
    container.appendChild(div);
  }

  // Remove extra days
  for (var i = current - 1; i >= n; i--) {
    if (existing[i]) existing[i].remove();
  }

  document.getElementById('itinerary-count').textContent = n+' day'+(n!==1?'s':'')+' · matches duration above';
  calcItinTotal();
}

// ── Per-day auto-fill from datalists ───────────────────────
function fillDayAcc(input, dayIdx) {
  var opts=document.querySelectorAll('#pe-acc-list-'+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.rate) block.querySelector('.day-acc-rate').value=opts[i].dataset.rate;
      if(opts[i].dataset.id)   block.querySelector('.day-acc-id').value=opts[i].dataset.id;
      calcItinTotal(); break;
    }
  }
}
function fillDayVeh(input, dayIdx) {
  var opts=document.querySelectorAll('#pe-veh-list-'+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.rate) block.querySelector('.day-veh-rate').value=opts[i].dataset.rate;
      if(opts[i].dataset.id)   block.querySelector('.day-veh-id').value=opts[i].dataset.id;
      calcItinTotal(); break;
    }
  }
}
function fillDayPark(input, dayIdx) {
  var opts=document.querySelectorAll('#pe-park-list-'+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.fee) block.querySelector('.day-park-fee').value=opts[i].dataset.fee;
      calcItinTotal(); break;
    }
  }
}

// ── Calculate itinerary total across all days (mirrors
//    admin/itineraries.php's calcItinTotal — same field roles, same
//    once-per-property accommodation/vehicle counting) ────────────
function calcItinTotal() {
  var pax      = parseInt((document.getElementById('traveler_count')||{}).value)||2;
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

    if (accName && !accSeen[accName]) accSeen[accName] = {rate:accRate,rooms:accRooms,nights:0};
    if (accName) accSeen[accName].nights++;

    if (vehName && !vehSeen[vehName]) vehSeen[vehName] = {rate:vehRate,days:0};
    if (vehName) vehSeen[vehName].days++;

    total += parkFee * pax;
    total += meals * pax;

    block.querySelectorAll('.act-item').forEach(function(row){
      var price = parseFloat((row.querySelector('.day-act-price')||{}).value)||0;
      var type  = (row.querySelector('.day-act-type')||{}).value||'included';
      if(type==='included') total += price * pax;
    });

    var dayCost = parkFee*pax + meals*pax + accRate*accRooms + vehRate;
    var badge = block.querySelector('.day-cost-badge');
    if(badge) badge.textContent='$'+Math.round(dayCost).toLocaleString();
  });

  Object.values(accSeen).forEach(function(a){ total += a.rate * a.nights * a.rooms; });
  Object.values(vehSeen).forEach(function(v){ total += v.rate * v.days; });

  // Keep the Quotation Live Cost Summary in sync with whatever just
  // changed in the itinerary above it — the two used to be
  // independent, so editing a day's accommodation rate here never
  // touched the summary until the admin also (re-)touched Travelers or
  // Service Charge.
  calcTotals();
}

// ── Activities per day ────────────────────────────────────
function addDayActivity(dayIdx) {
  var container = document.getElementById('day-acts-'+dayIdx);
  var div = document.createElement('div');
  div.className='act-item';
  div.style.cssText='display:grid;grid-template-columns:2fr 80px 90px auto;gap:.35rem;align-items:end;margin-bottom:.4rem';
  div.innerHTML='<input class="admin-input day-act-name" list="act-list" placeholder="Activity name" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<input type="number" step="0.01" class="admin-input day-act-price" value="0" placeholder="$/pp" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<select class="admin-input day-act-type" style="font-size:.72rem" onchange="saveDayActs('+dayIdx+')"><option value="included">Included</option><option value="optional">Optional</option></select>'
    +'<button type="button" onclick="this.closest(\'.act-item\').remove();saveDayActs('+dayIdx+')" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:.2rem">✕</button>';
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
    +'<button type="button" onclick="this.closest(\'.other-item\').remove();saveDayOther('+dayIdx+')" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;padding:.2rem">✕</button>';
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
    var r = await fetch('<?= BASE_PATH ?>/api/vehicle_manage.php',{method:'POST',body:fd,credentials:'same-origin'});
    var d = await r.json();
    if (d.success) {
      // Push into PE_VEHS and every per-day vehicle datalist (pe-veh-list-N)
      // — there's no single shared #veh-list any more since each day's
      // vehicle box has its own datalist instance.
      var combinedRate = (parseFloat(rate)||0) + (parseFloat(fuel)||0) + (parseFloat(driver)||0);
      PE_VEHS.push({name:name, id:d.id, rate:combinedRate});
      document.querySelectorAll('[id^="pe-veh-list-"]').forEach(function(list){
        var opt = document.createElement('option');
        opt.value = name;
        opt.dataset.id   = d.id;
        opt.dataset.rate = combinedRate;
        list.appendChild(opt);
      });
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
// Rewritten: the previous version rebuilt the folder tabs from whatever
// subset of images the grid happened to be showing, so each tab's click
// handler closed over that shrinking subset. Click a folder tab once and
// every other folder's tab vanished; click "All" afterwards and it now
// showed only the previously-selected folder, not everything — the
// picker looked increasingly "stuck"/broken the more you clicked around
// in it. Typing in the search box also rebuilt the entire tab bar (and
// re-registered every tab's onclick) on every keystroke, which is pure
// wasted work on top of the bug.
//
// Fixed by keeping one true source of data (allPickerImages, populated
// once and never mutated) plus two small pieces of UI state
// (currentFolder, currentSearch). Tabs are built once per picker open,
// always enumerating folders from allPickerImages; only the image grid
// re-renders on search input or a tab click.
var allPickerImages = [];
var pickerSelected  = new Set(
  document.getElementById('gallery_images_input').value.split(',').filter(Boolean)
);
var currentFolder = 'all';
var currentSearch = '';

async function openGalleryPicker() {
  document.getElementById('galleryPickerModal').style.display='flex';
  if (allPickerImages.length === 0) await loadPickerImages();
  currentFolder = 'all';
  currentSearch = '';
  document.getElementById('picker-search').value = '';
  renderPickerTabs();
  renderPickerGrid();
}

async function loadPickerImages() {
  try {
    var r = await fetch('<?= BASE_PATH ?>/api/gallery/list.php',{credentials:'same-origin'});
    allPickerImages = await r.json();
  } catch(e) { allPickerImages = []; }
}

function renderPickerTabs() {
  var tabs = document.getElementById('picker-tabs');
  var folders = [...new Set(allPickerImages.map(function(img){ return img.folder; }))].sort();
  tabs.innerHTML = '';
  ['all'].concat(folders).forEach(function(f){
    var tab = document.createElement('button');
    tab.type='button';
    tab.textContent = f==='all' ? 'All' : f.replace('packages/','');
    tab.dataset.folder = f;
    var active = f === currentFolder;
    tab.style.cssText='padding:.5rem 1rem;font-size:.7rem;font-weight:600;background:none;border:none;'
      +'border-bottom:2px solid '+(active?'#0a0a0a':'transparent')+';cursor:pointer;white-space:nowrap;'
      +'letter-spacing:.06em;text-transform:uppercase;color:'+(active?'#0a0a0a':'#9ca3af');
    tab.onclick = function(){
      currentFolder = f;
      renderPickerTabs();
      renderPickerGrid();
    };
    tabs.appendChild(tab);
  });
}

function renderPickerGrid() {
  var grid   = document.getElementById('picker-grid');
  var filter = currentSearch.toLowerCase();

  var images = currentFolder === 'all'
    ? allPickerImages
    : allPickerImages.filter(function(img){ return img.folder === currentFolder; });
  var filtered = filter
    ? images.filter(function(img){ return img.url.toLowerCase().includes(filter); })
    : images;

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

function filterPickerImages(q) { currentSearch = q; renderPickerGrid(); }

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
  // Save itinerary_id to hidden field so it gets submitted with the form
  var hiddenField = document.getElementById('selected_itinerary_id');
  if (hiddenField) hiddenField.value = id;
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

    // Fill every field the Itinerary Manager's own day-block has — not
    // just title/description, which is all this used to copy over,
    // silently dropping every accommodation/vehicle/park/activity the
    // template actually defined.
    var days = itin.days || [];
    days.forEach(function(day, i) {
      var block = document.querySelector('.day-block[data-day="'+i+'"]');
      if (!block) return;

      var titleInp = block.querySelector('[name="day_title[]"]');
      var descInp  = block.querySelector('[name="day_desc[]"]');
      var locInp   = block.querySelector('[name="day_location[]"]');
      if (titleInp) titleInp.value = day.title || '';
      if (descInp)  descInp.value  = day.description || '';
      if (locInp)   locInp.value   = day.location || '';

      var accName = block.querySelector('.day-acc-name');
      var accRate = block.querySelector('.day-acc-rate');
      var accRoom = block.querySelector('[name="day_acc_rooms[]"]');
      var accId   = block.querySelector('.day-acc-id');
      if (accName) accName.value = day.accommodation_name || '';
      if (accRate) accRate.value = day.accommodation_rate || 0;
      if (accRoom) accRoom.value = day.accommodation_rooms || 1;
      if (accId)   accId.value   = day.accommodation_id || 0;

      var vehName = block.querySelector('.day-veh-name');
      var vehRate = block.querySelector('.day-veh-rate');
      var vehId   = block.querySelector('.day-veh-id');
      if (vehName) vehName.value = day.vehicle_name || '';
      if (vehRate) vehRate.value = day.vehicle_rate || 0;
      if (vehId)   vehId.value   = day.vehicle_id || 0;

      var parkName = block.querySelector('[name="day_park_name[]"]');
      var parkFee  = block.querySelector('.day-park-fee');
      var parkId   = block.querySelector('[name="day_park_id[]"]');
      var mealsInp = block.querySelector('[name="day_meals_pp[]"]');
      if (parkName) parkName.value = day.park_fee_name || '';
      if (parkFee)  parkFee.value  = day.park_adult_fee || 0;
      if (parkId)   parkId.value   = day.park_fee_id || 0;
      if (mealsInp) mealsInp.value = day.meals_cost_pp || 0;

      (day.activities || []).forEach(function(act) {
        addDayActivity(i);
        var rows = block.querySelectorAll('.act-item');
        var row  = rows[rows.length - 1];
        row.querySelector('.day-act-name').value  = act.name || '';
        row.querySelector('.day-act-price').value = act.price_pp || 0;
        row.querySelector('.day-act-type').value  = act.type || 'included';
      });
      saveDayActs(i);

      (day.other_items || []).forEach(function(ot) {
        addDayOther(i);
        var rows = block.querySelectorAll('.other-item');
        var row  = rows[rows.length - 1];
        row.querySelector('.day-other-name').value   = ot.name || '';
        row.querySelector('.day-other-amount').value = ot.amount || 0;
      });
      saveDayOther(i);
    });

    calcItinTotal();
    alert('Itinerary "'+itin.name+'" loaded — '+itin.duration_days+' days filled. Review and save.');
  } catch(e) { alert('Error: '+e.message); }
}


// calcItinTotal() (defined above, alongside syncItineraryDays) now owns
// the day-cost badges and the itinerary section's grand total/per-person
// figures — it replaced a dead calcDayTotals() that matched field names
// (accommodation_rate[], vehicle_rate[], meals_cost[]) this section no
// longer has after being rebuilt to match the Itinerary Manager's fields.
document.addEventListener('DOMContentLoaded', calcItinTotal);

</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
