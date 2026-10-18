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

// Attaching or detaching a package's itinerary template changes what the
// package prices and displays — admin/superadmin only, enforced here on
// the GET path itself, not just hidden in the UI.
if ($id > 0 && (isset($_GET['unlink']) || !empty($_GET['link'])) && !AuthMiddleware::isAdminRole()) {
    \App\Services\AccessGuard::denyRedirect("package_edit.php?id=$id", 'package', (string)$id, isset($_GET['unlink']) ? 'unlink_itinerary' : 'link_itinerary');
}

// ── Unlink — detach this package from its Itinerary Manager template,
// seeding its own now-editable itinerary copy from the template's
// current days first so nothing the admin was looking at disappears.
// A one-shot DB action, not a form submit — the linked template has no
// editable inputs on this page to submit in the first place.
if ($id > 0 && isset($_GET['unlink']) && $pkg && !empty($pkg['itinerary_id'])) {
    try {
        $ls = $db->prepare("SELECT days FROM itineraries WHERE id=? LIMIT 1");
        $ls->execute([(int)$pkg['itinerary_id']]);
        $row  = $ls->fetch();
        $days = $row ? (json_decode($row['days'] ?? '[]', true) ?: []) : [];
        $db->prepare("UPDATE packages SET itinerary_id=NULL, itinerary=? WHERE id=?")
           ->execute([json_encode($days), $id]);
        header("Location: package_edit.php?id=$id&msg=".urlencode('Unlinked — itinerary is now editable here.')); exit;
    } catch (\Exception $e) {}
}

// ── Link — attach this package to an Itinerary Manager template. The
// template becomes authoritative for pricing and display (see
// ItineraryQuoteBuilder usage in the SAVE block below); this package's
// own itinerary copy is simply not read anywhere once linked.
if ($id > 0 && !empty($_GET['link']) && $pkg) {
    $linkId = (int)$_GET['link'];
    try {
        $chk = $db->prepare("SELECT id FROM itineraries WHERE id=? LIMIT 1");
        $chk->execute([$linkId]);
        if ($chk->fetch()) {
            $db->prepare("UPDATE packages SET itinerary_id=? WHERE id=?")->execute([$linkId, $id]);
            header("Location: package_edit.php?id=$id&msg=".urlencode('Linked to itinerary template.')); exit;
        }
    } catch (\Exception $e) {}
}

function fetchAll($db,$sql){try{return $db->query($sql)->fetchAll();}catch(\Exception $e){return[];}}
$accommodations = fetchAll($db,"SELECT id,name,level,rate_per_room,rate_per_person,single_supplement,child_rate FROM accommodations WHERE is_active=1 ORDER BY level,name");
$vehicles       = fetchAll($db,"SELECT id,name,daily_rate,fuel_cost_day,driver_cost_day,capacity FROM vehicles WHERE is_active=1 ORDER BY name");
$parkFees       = fetchAll($db,"SELECT id,name,adult_fee,child_fee,vehicle_fee FROM park_fees WHERE is_active=1 ORDER BY name");
$activities_db  = fetchAll($db,"SELECT id,name,price,price_unit FROM activities WHERE is_active=1 ORDER BY name");

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pkg'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    // Packages are a shared catalog with no per-record owner. Content is a
    // section staff has full access to (per the sidebar role model), so
    // this blocks only the agent role — the one role restricted to its
    // own sales-pipeline records, not general catalog/pricing management.
    if (AuthMiddleware::isAgentRole()) {
        header("Location: package_edit.php?".($id>0?"id=$id&":"")."msg=".urlencode('Permission denied.')); exit;
    }
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
    // of being re-typed into a separate set of rows here — service
    // charge and traveler counts are what's left to enter directly.
    $bd = [
        'service_charge_pax' => (float)($_POST['service_charge_pax'] ?? 0),
        'adults'              => max(1,(int)($_POST['adults'] ?? 1)),
        'children'            => max(0,(int)($_POST['children'] ?? 0)),
        'infants'             => max(0,(int)($_POST['infants'] ?? 0)),
        'notes'               => trim($_POST['breakdown_notes']??''),
    ];

    // The listed price is no longer typed — it's the itinerary total
    // (linked Itinerary Manager template when this package is linked,
    // otherwise its own Day-by-Day Itinerary above) plus the Service
    // Charge, for a single traveler. This is a "starting from" marketing
    // figure only; the real price a guest pays is always recomputed fresh
    // from their actual adults/children count via the same
    // ItineraryQuoteBuilder (admin/quotation_new.php, api/book.php) — so
    // there's no 2pax/3pax tier to keep in sync here, just this one rate.
    $pricingItinId = !empty($_POST['itinerary_id']) ? (int)$_POST['itinerary_id'] : 0;
    $pricingDays   = $itinerary;
    if ($pricingItinId) {
        $ls  = $db->prepare("SELECT days FROM itineraries WHERE id=? LIMIT 1");
        $ls->execute([$pricingItinId]);
        $row = $ls->fetch();
        $pricingDays = $row ? (json_decode($row['days'] ?? '[]', true) ?: []) : [];
    }
    $svcPax = $bd['service_charge_pax'];
    $quote     = \App\Services\ItineraryQuoteBuilder::build(['days' => $pricingDays], 1, 0, false);
    $lineItems = $quote['line_items'];
    if ($svcPax > 0) {
        $lineItems[] = [
            'description' => 'Service Charge', 'unit_price' => $svcPax,
            'quantity_str'=> '1 Guest',
            'amount'      => round($svcPax, 2), 'category' => 'service_charge',
        ];
    }
    $totals = \App\Services\ItineraryQuoteBuilder::totals($lineItems);
    $computedPrice = round($totals['grand_total'], 2);

    // Operational cost is entered by the admin (there's no reliable
    // system-derived source for true internal cost — cost_breakdown is
    // display/notes content, not a cost ledger) and defaults to 0,
    // matching the existing vw_package_profitability convention of
    // never fabricating a cost figure. Margin is computed the same way
    // the view computes it, so a direct SELECT on packages matches the
    // view without needing the join.
    $operationalCost = max(0, (float)($_POST['operational_cost'] ?? 0));
    $sellingPrice     = $computedPrice;
    $profitMargin     = $sellingPrice > 0 ? round((($sellingPrice - $operationalCost) / $sellingPrice) * 100, 2) : 0;
    $validTiers       = ['BUDGET', 'MIDRANGE', 'COMFORT', 'LUXURY', 'ULTRA_LUXURY'];
    $packageTier      = in_array($_POST['package_tier'] ?? '', $validTiers, true) ? $_POST['package_tier'] : null;

    $data = [
        'slug'                => $slug,
        'name'                => trim($_POST['name']??''),
        'duration_days'       => $durationDays,
        'short_description'   => trim($_POST['short_description']??''),
        'overview'            => trim($_POST['overview']??''),
        'accommodation'       => trim($_POST['accommodation']??''),
        'accommodation_level' => $_POST['accommodation_level']??'midrange',
        'price_1pax'          => $computedPrice,
        'is_featured'         => isset($_POST['is_featured'])?1:0,
        'is_active'           => isset($_POST['is_active'])?1:0,
        'sort_order'          => (int)($_POST['sort_order']??0),
        'itinerary'           => json_encode($itinerary),
        'itinerary_id'        => !empty($_POST['itinerary_id']) ? (int)$_POST['itinerary_id'] : null,
        'includes'            => json_encode($includes),
        'excludes'            => json_encode($excludes),
        'cost_breakdown'      => json_encode($bd),
        'gallery_images'      => json_encode($galleryImages),
        'package_tier'        => $packageTier,
        'operational_cost'    => $operationalCost,
        'selling_price'       => $sellingPrice,
        'profit_margin_percent' => $profitMargin,
    ];

    try {
        if ($id > 0) {
            $set=implode(',',array_map(fn($k)=>"`$k`=?",array_keys($data)));
            $db->prepare("UPDATE packages SET $set WHERE id=?")->execute(array_merge(array_values($data),[$id]));
            $newId=$id;
            \App\Services\ActivityLog::record('update', 'package', $newId, ['name'=>$data['name'],'price_1pax'=>$data['price_1pax']]);
        } else {
            $cols=implode(',',array_map(fn($k)=>"`$k`",array_keys($data)));
            $phld=implode(',',array_fill(0,count($data),'?'));
            $db->prepare("INSERT INTO packages ($cols,created_at) VALUES ($phld,NOW())")->execute(array_values($data));
            $newId=$db->lastInsertId();
            \App\Services\ActivityLog::record('create', 'package', (int)$newId, ['name'=>$data['name']]);
        }
        header("Location: package_edit.php?id=$newId&msg=".urlencode('Package saved.')); exit;
    } catch(\Exception $e){ $msg='Error: '.$e->getMessage(); }
}

// Parse existing
$durationDays = (int)($pkg['duration_days'] ?? 5);

// A package linked to an Itinerary Manager template treats that shared
// template as authoritative for pricing and day-by-day display — it's
// what api/book.php actually charges and what the public packages page
// actually shows (see views/packages.php). This package's own itinerary
// section below is only ever editable/used when nothing is linked.
$linkedItin = null;
if ($pkg && !empty($pkg['itinerary_id'])) {
    try {
        $ls = $db->prepare("SELECT * FROM itineraries WHERE id=? LIMIT 1");
        $ls->execute([(int)$pkg['itinerary_id']]);
        $linkedItin = $ls->fetch() ?: null;
    } catch (\Exception $e) {}
}
$isLinked       = $linkedItin !== null;
$linkedDays     = $isLinked ? (json_decode($linkedItin['days']??'[]',true)?:[]) : [];
$linkedIncludes = $isLinked ? (json_decode($linkedItin['includes']??'[]',true)?:[]) : [];
$linkedExcludes = $isLinked ? (json_decode($linkedItin['excludes']??'[]',true)?:[]) : [];

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
// day-blocks (see calcTotals() below); service_charge_pax and Adults/
// Children/Infants are what's left to enter directly. 'service_charge'
// is the old key, kept as a fallback for packages saved before this
// change. Adults/Children/Infants only drive this page's live cost
// preview — the saved price_1pax is always computed for 1 adult, 0 children.
$bdSvcPax    = (float)($bd['service_charge_pax'] ?? $bd['service_charge'] ?? 0);
$bdAdults    = max(1, (int)($bd['adults'] ?? $bd['traveler_count'] ?? 1));
$bdChildren  = max(0, (int)($bd['children'] ?? 0));
$bdInfants   = max(0, (int)($bd['infants'] ?? 0));

$pageTitle = $id ? 'Edit Package' : 'New Package';
ob_start();
?>
<style><?= \App\Helpers\ItineraryDayCards::css() ?></style>
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
<?= \App\Middleware\CsrfMiddleware::field() ?>
<input type="hidden" name="save_pkg" value="1">
    <input type="hidden" name="itinerary_id" id="selected_itinerary_id" value="<?= (int)($pkg['itinerary_id'] ?? 0) ?>">
<div class="admin-split-layout" style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start">

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
        <img src="<?= htmlspecialchars(BASE_PATH . $imgUrl) ?>" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block" loading="lazy">
        <button type="button" onclick="removeSelectedImg(this)"
                style="position:absolute;top:3px;right:3px;background:rgba(220,38,38,.9);border:none;color:#fff;width:20px;height:20px;border-radius:50%;cursor:pointer;font-size:.6rem;display:flex;align-items:center;justify-content:center">✕</button>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <p style="font-size:.68rem;color:#9ca3af;margin-top:.5rem">First image = hero image · remove and re-select in the order you want to reorder</p>
  </div>
</div>

<!-- Listed Price removed — the "starting from" price shown to guests is
     now always the itinerary total (linked template, or this package's
     own Day-by-Day Itinerary below) plus Service Charge, for a single
     traveler. See the Quotation Live Cost Summary further down for the
     live number; price_1pax is recomputed automatically on every save
     so nothing has to be typed or kept in sync by hand. The real price
     a guest pays is always computed fresh from their actual party size
     at quotation/booking time, not read from this field. -->


<?php if (!$isLinked): ?>
<!-- LOAD OR LINK ITINERARY TEMPLATE — only relevant while this package
     has no linked template of its own (see $isLinked branch on the
     Day-by-Day Itinerary card below). -->
<div class="admin-card" style="margin-bottom:1.25rem;border:1.5px solid var(--black)">
  <div class="admin-card-header" style="background:var(--black);color:var(--white)">
    <h3 style="color:var(--white)">Load or Link Itinerary Template</h3>
    <span style="font-size:.72rem;opacity:.7">Copy to customize, or link permanently</span>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr auto<?= $id ? ' auto' : '' ?>;gap:.75rem;align-items:end">
      <div>
        <label class="admin-label">Select Itinerary</label>
        <select id="pkg_itin_select" class="admin-input">
          <option value="">— Select an itinerary template —</option>
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
      <button type="button" onclick="loadPkgItinerary()" class="admin-btn admin-btn--primary" style="padding:.75rem 1.5rem">
        Copy to Customize
      </button>
      <?php if ($id): ?>
      <button type="button" onclick="linkPkgItinerary()" class="admin-btn admin-btn--outline" style="padding:.75rem 1.5rem">
        Link Permanently
      </button>
      <?php endif; ?>
    </div>
    <p style="font-size:.68rem;color:var(--gray-500);margin-top:.4rem"><strong>Copy</strong> fills the day-by-day itinerary below so you can customize it just for this package — pricing then comes from that local copy. <strong>Link</strong> attaches this package to the template directly: pricing and display always follow the Itinerary Manager, and the section below becomes read-only.<?= $id ? '' : ' (Save the package first to enable linking.)' ?></p>
  </div>
</div>
<?php endif; ?>



<?php if ($isLinked): ?>
<!-- Linked to an Itinerary Manager template — that shared template is
     what api/book.php actually charges and what guests see on the
     public packages page, so this package's own itinerary section is
     read-only here; edit day-by-day details in the Itinerary Manager. -->
<div class="admin-card" style="margin-bottom:1.25rem;border:1.5px solid var(--black)">
  <div class="admin-card-header" style="background:var(--gray-50)">
    <h3>Day-by-Day Itinerary <span style="font-weight:600;color:var(--gray-500);font-size:.75rem">— linked to "<?= htmlspecialchars($linkedItin['name']) ?>"</span></h3>
    <div style="display:flex;gap:.5rem">
      <a href="itinerary_edit.php?id=<?= (int)$linkedItin['id'] ?>" target="_blank" class="admin-btn admin-btn--outline" style="font-size:.65rem">Edit in Itinerary Manager <?= \App\Helpers\AdminIcon::render('external-link', 11) ?></a>
      <a href="package_edit.php?id=<?= $id ?>&amp;unlink=1"
         onclick="return confirmNavGuard(event, this.href, 'Unlink and make this package\'s itinerary editable here instead? The template\'s current days will be copied over first as a starting point.')"
         class="admin-btn admin-btn--muted" style="font-size:.65rem">Unlink &amp; Customize Here</a>
    </div>
  </div>
  <div class="admin-card-body">
    <?= \App\Helpers\ItineraryDayCards::render($linkedDays, true) ?>
  </div>
</div>
<?php else: ?>
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
      <span style="font-size:.72rem;color:var(--gray-500)" id="itinerary-count">
        <?= $durationDays ?> day<?= $durationDays!=1?'s':'' ?> · matches duration above
      </span>
      <button type="button" onclick="openAddVehicleModal()" class="admin-btn admin-btn--outline" style="font-size:.7rem;padding:.3rem .6rem">+ New Vehicle</button>
    </div>
  </div>
  <div class="admin-card-body" id="itinerary-rows">
    <?php foreach($itinerary as $i=>$day): ?>
    <div class="day-block itinerary-day" data-day="<?= $i ?>" style="margin-bottom:1rem;border-left:3px solid var(--black)">
      <div class="admin-card-header" style="background:var(--gray-50)">
        <h3 style="font-size:.85rem">
          <span style="font-family:'Times New Roman',Times,serif;color:var(--black);margin-right:.5rem">Day <?= $i+1 ?></span>
          <input name="day_title[]" class="admin-input" style="display:inline;width:auto;min-width:200px;font-size:.85rem;border:none;background:transparent;font-weight:600;padding:.15rem .35rem"
                 placeholder="Day title e.g. Arusha → Tarangire"
                 value="<?= htmlspecialchars($day['title']??'') ?>">
        </h3>
        <div style="display:flex;align-items:center;gap:.5rem">
          <span class="day-cost-badge" style="font-size:.65rem;background:var(--black);color:var(--white);padding:.2rem .6rem;font-weight:600">$0</span>
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
          <div class="itin-panel itin-panel--blue">
            <p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#1d4ed8"><?= \App\Helpers\AdminIcon::render('accommodations', 13) ?> Accommodation</p>
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
            <div class="admin-field-pair" style="display:grid;grid-template-columns:1fr 60px;gap:.35rem">
              <div><label class="admin-label">Rate/Room/Night</label>
                <input name="day_acc_rate[]" type="number" step="1" class="admin-input day-acc-rate"
                       value="<?= (float)($day['accommodation_rate']??0) ?>" oninput="calcItinTotal()"></div>
              <div><label class="admin-label">Rooms</label>
                <input name="day_acc_rooms[]" type="number" min="1" class="admin-input"
                       value="<?= (int)($day['accommodation_rooms']??1) ?>" oninput="calcItinTotal()"></div>
            </div>
          </div>

          <!-- Vehicle -->
          <div class="itin-panel itin-panel--green">
            <p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#166534"><?= \App\Helpers\AdminIcon::render('fleet', 13) ?> Vehicle</p>
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
          <div class="itin-panel itin-panel--amber">
            <p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#92400e"><?= \App\Helpers\AdminIcon::render('park-fees', 13) ?> Park &amp; Meals</p>
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
            <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Activities (Day <?= $i+1 ?>)</p>
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
                      class="admin-icon-btn admin-icon-btn--danger"><?= \App\Helpers\AdminIcon::render('close', 14) ?></button>
            </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="day_activities_json[]" id="acts-json-<?= $i ?>"
                 value="<?= htmlspecialchars(json_encode($day['activities']??[])) ?>">
        </div>

        <!-- Other items for this day -->
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
            <p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Other Costs (Day <?= $i+1 ?>)</p>
            <button type="button" onclick="addDayOther(<?= $i ?>)" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>
          </div>
          <div id="day-other-<?= $i ?>">
            <?php foreach(($day['other_items']??[]) as $oi=>$ot): ?>
            <div class="other-item" style="display:grid;grid-template-columns:2fr 100px auto auto;gap:.35rem;align-items:center;margin-bottom:.4rem">
              <input class="admin-input day-other-name" value="<?= htmlspecialchars($ot['name']??'') ?>" placeholder="Item name" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
              <input type="number" step="0.01" class="admin-input day-other-amount" value="<?= (float)($ot['amount']??0) ?>" placeholder="$" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
              <label style="display:flex;align-items:center;gap:.25rem;font-size:.62rem;color:var(--gray-500);white-space:nowrap" title="Multiply this amount by the number of travelers">
                <input type="checkbox" class="day-other-perpax" <?= !empty($ot['per_pax']) ? 'checked' : '' ?> onchange="saveDayOther(<?= $i ?>)"> /traveler
              </label>
              <button type="button" onclick="this.closest('.other-item').remove();saveDayOther(<?= $i ?>)"
                      class="admin-icon-btn admin-icon-btn--danger"><?= \App\Helpers\AdminIcon::render('close', 14) ?></button>
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
<?php endif; ?>

<!-- INCLUDES / EXCLUDES — auto-fillable from whatever's actually in the
     itinerary (linked template's own curated lists when linked, or
     derived from the local Day-by-Day Itinerary otherwise) via ↓ Sync,
     then still freely editable by hand afterward. -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3>Includes &amp; Excludes</h3>
    <div style="display:flex;align-items:center;gap:.75rem">
      <span style="font-size:.72rem;color:var(--gray-500)">One item per line</span>
      <button type="button" onclick="syncIncludesExcludes()" class="admin-btn admin-btn--muted" style="font-size:.65rem;padding:.25rem .55rem;display:inline-flex;align-items:center;gap:.3rem"><?= \App\Helpers\AdminIcon::render('refresh', 11) ?> Sync from Itinerary</button>
    </div>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
      <div><label class="admin-label" style="display:flex;align-items:center;gap:.3rem;color:#166534"><?= \App\Helpers\AdminIcon::render('check', 11) ?> Included</label>
        <textarea name="includes" id="includes_ta" class="admin-input" rows="8"><?= htmlspecialchars($includes) ?></textarea></div>
      <div><label class="admin-label" style="display:flex;align-items:center;gap:.3rem;color:#991b1b"><?= \App\Helpers\AdminIcon::render('close', 11) ?> Not Included</label>
        <textarea name="excludes" id="excludes_ta" class="admin-input" rows="8"><?= htmlspecialchars($excludes) ?></textarea></div>
    </div>
    <p style="font-size:.68rem;color:var(--gray-500);margin-top:.5rem">Sync pulls the linked itinerary template's own list when linked<?php if(!$isLinked): ?>, or builds a starting Includes list from what's actually in the Day-by-Day Itinerary above — Excludes still needs typing, since things like visas or insurance aren't part of the itinerary<?php endif; ?>. Edit the result freely afterward.</p>
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
    <span style="font-size:.72rem;color:var(--gray-500)">Pulled from the itinerary above · same VAT and deposit math as a real quotation</span>
  </div>
  <div class="admin-card-body">
    <div class="admin-split-summary" style="display:grid;grid-template-columns:1fr 260px;gap:1.5rem;align-items:start">

      <!-- Read-only summary — itemized, matching what ItineraryQuoteBuilder
           actually computes (see calcTotals() below) -->
      <div>
        <table style="width:100%;font-size:.85rem;border-collapse:collapse">
          <thead>
            <tr style="border-bottom:2px solid var(--gray-200)">
              <th style="text-align:left;padding:.4rem 0;font-size:.62rem;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-500);font-weight:600">Item</th>
              <th style="text-align:right;padding:.4rem 0;font-size:.62rem;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-500);font-weight:600">Unit Price</th>
              <th style="text-align:left;padding:.4rem 0 .4rem .75rem;font-size:.62rem;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-500);font-weight:600">Qty</th>
              <th style="text-align:right;padding:.4rem 0;font-size:.62rem;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-500);font-weight:600">Amount</th>
            </tr>
          </thead>
          <tbody id="qs-line-items"></tbody>
          <tbody>
            <tr style="border-top:1px solid var(--gray-200)"><td colspan="3" style="padding:.6rem 0;font-weight:600">Subtotal</td><td style="padding:.6rem 0;text-align:right;font-weight:600" id="qs-subtotal">$0</td></tr>
            <tr style="border-bottom:1px solid var(--gray-100)"><td colspan="3" style="padding:.4rem 0;color:var(--gray-500);font-size:.78rem">VAT (<?= (int) round((defined('VAT_RATE') ? VAT_RATE : 0.18) * 100) ?>%)</td><td style="padding:.4rem 0;text-align:right;font-size:.78rem" id="qs-vat">$0</td></tr>
            <tr style="background:var(--black);color:var(--white)"><td colspan="3" style="padding:.75rem;font-weight:700">TOTAL</td><td style="padding:.75rem;text-align:right;font-family:'Times New Roman',Times,serif;font-size:1.1rem" id="qs-total">$0</td></tr>
            <tr><td colspan="3" style="padding:.5rem 0;font-size:.75rem;color:var(--gray-500)" id="qs-pp-label">Per Person (÷2)</td><td style="padding:.5rem 0;text-align:right;font-size:.9rem;font-weight:700" id="qs-pp">$0</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Aside: the only editable inputs — Adults/Children/Infants
           (shared with the itinerary day badges above), then Service
           Charge. Identical field set/order to admin/quotation_new.php's
           equivalent aside. -->
      <div style="background:var(--gray-50);border:1px solid var(--gray-200);padding:1rem">
        <label class="admin-label">Adults</label>
        <input type="number" name="adults" id="pe_adults" min="1" max="20"
               value="<?= $bdAdults ?>" class="admin-input" style="margin-bottom:.75rem"
               oninput="calcTotals()" title="Used for both the itinerary day badges above and this summary">

        <label class="admin-label">Children</label>
        <input type="number" name="children" id="pe_children" min="0" max="20"
               value="<?= $bdChildren ?>" class="admin-input" style="margin-bottom:.75rem"
               oninput="calcTotals()">

        <label class="admin-label">Infants</label>
        <input type="number" name="infants" id="pe_infants" min="0" max="20"
               value="<?= $bdInfants ?>" class="admin-input" style="margin-bottom:.75rem">

        <label class="admin-label">Service Charge ($ / person)</label>
        <input name="service_charge_pax" type="number" step="0.01" class="admin-input" id="service_charge_pax"
               value="<?= $bdSvcPax ?>" oninput="calcTotals()" placeholder="e.g. 50">
        <p style="font-size:.68rem;color:var(--gray-500);margin-top:.5rem;line-height:1.5">Everything above comes from the Day-by-Day Itinerary.</p>
      </div>

    </div>

    <!-- NOTES -->
    <div style="margin-top:1.25rem"><label class="admin-label">Pricing Notes (shown to guests)</label>
      <textarea name="breakdown_notes" class="admin-input" rows="2" placeholder="e.g. Based on 2 travelers sharing."><?= htmlspecialchars($bd['notes']??'') ?></textarea></div>

  </div>
</div>

<!-- Internal-only: not shown to guests. selling_price/profit_margin_percent
     are recomputed on every save from the total above; operational_cost
     and package_tier are the only two figures entered here, since there's
     no system-derived source for true internal cost. Feeds
     vw_package_profitability directly. -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <h3>Profitability <span style="font-weight:600;color:var(--gray-500);font-size:.75rem">(internal only)</span></h3>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;align-items:end">
      <div>
        <label class="admin-label">Operational Cost ($, per person)</label>
        <input type="number" step="0.01" min="0" name="operational_cost" id="pe_op_cost" class="admin-input"
               value="<?= htmlspecialchars((string)($pkg['operational_cost'] ?? 0)) ?>" oninput="calcMargin()" placeholder="0.00">
      </div>
      <div>
        <label class="admin-label">Package Tier</label>
        <select name="package_tier" class="admin-input">
          <option value="">— Not set —</option>
          <?php foreach(['BUDGET','MIDRANGE','COMFORT','LUXURY','ULTRA_LUXURY'] as $t): ?>
          <option value="<?= $t ?>" <?= ($pkg['package_tier']??'')===$t?'selected':'' ?>><?= ucwords(strtolower(str_replace('_',' ',$t))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="admin-label">Profit Margin</label>
        <div class="admin-input" id="pe_margin" style="background:var(--gray-50);font-weight:600">—</div>
      </div>
    </div>
    <p style="font-size:.68rem;color:var(--gray-500);margin-top:.6rem">Margin = (Total above − Operational Cost) ÷ Total. Leave cost at 0 until a real figure is on file — an unset cost shows as a 100% margin on the profitability report, which is a flag to fill it in, not a claim.</p>
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
      <?php if (!AuthMiddleware::isAgentRole()): ?>
      <button type="submit" class="admin-btn admin-btn--primary" style="width:100%;justify-content:center;padding:.85rem;margin-bottom:.5rem">
        💾 Save Package
      </button>
      <?php else: ?>
      <div class="admin-alert" style="margin-bottom:.5rem">Package pricing is not available to your role — you can view but not edit.</div>
      <?php endif; ?>
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
<div id="addVehicleModal" class="admin-modal-overlay" style="display:none">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <span class="admin-modal-title">Add New Vehicle to Database</span>
      <button onclick="document.getElementById('addVehicleModal').style.display='none'" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <div id="veh-err" class="admin-alert admin-alert--error" style="display:none"></div>
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
<div id="galleryPickerModal" class="admin-modal-overlay" style="display:none">
  <div class="admin-modal admin-modal--wide" style="max-width:900px;display:flex;flex-direction:column">
    <div class="admin-modal-header" style="flex-shrink:0">
      <div>
        <span class="admin-modal-title">Select Gallery Images</span>
        <span id="picker-selected-count" style="font-size:.72rem;color:var(--gray-500);margin-left:.75rem">0 selected</span>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center">
        <button onclick="applyGallerySelection()" class="admin-btn admin-btn--primary" style="font-size:.72rem">Apply Selection</button>
        <button onclick="document.getElementById('galleryPickerModal').style.display='none'" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
      </div>
    </div>

    <!-- Folder tabs -->
    <div style="display:flex;gap:0;border-bottom:1px solid var(--gray-200);overflow-x:auto;flex-shrink:0" id="picker-tabs"></div>

    <!-- Image grid -->
    <div style="flex:1;overflow-y:auto;padding:1rem">
      <input type="text" id="picker-search" placeholder="Filter images…" class="admin-input" style="margin-bottom:1rem"
             oninput="filterPickerImages(this.value)">
      <div id="picker-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.65rem"></div>
    </div>
  </div>
</div>

<script>
var fmt = function(n){ return '$'+Math.round(n).toLocaleString('en-US'); };
// Single source of truth is config/app.php's VAT_RATE constant — mirrored
// here instead of hardcoding 0.18 so this preview can't drift from it.
var VAT_RATE = <?= json_encode(defined('VAT_RATE') ? VAT_RATE : 0.18) ?>;

// Non-null only when this package is linked to an Itinerary Manager
// template — that template's days/includes/excludes are then what
// actually prices and describes the package (see PHP $isLinked), and
// there are no editable .day-block elements in the DOM to read instead.
var LINKED_ITIN_DAYS     = <?= $isLinked ? json_encode($linkedDays) : 'null' ?>;
var LINKED_ITIN_INCLUDES = <?= json_encode($linkedIncludes) ?>;
var LINKED_ITIN_EXCLUDES = <?= json_encode($linkedExcludes) ?>;

// ── Quotation Live Cost Summary ──────────────────────────────
// Aggregates from either the linked template's days (LINKED_ITIN_DAYS)
// or the Day-by-Day Itinerary's own day-blocks — same category grouping
// calcItinTotal() uses for the per-day badges (each accommodation/
// vehicle counted once per unique property across all nights/days, not
// once per day) — then adds VAT, matching how a real quotation is
// actually priced (see App\Services\ItineraryQuoteBuilder::totals() and
// api/quotation_pdf.php). Previously this read a separate set of
// manually-typed rows instead of the itinerary, so the two could
// silently disagree.
function calcTotals() {
  var adults   = +((document.getElementById('pe_adults')||{}).value)||1;
  var children = +((document.getElementById('pe_children')||{}).value)||0;
  var travelers = adults + children;

  var accSeen = {}, vehSeen = {}, parkSeen = {}, otherSeen = {};
  var mealsPP = 0, mealsDays = 0;
  var actSeen = {};

  var dayDescriptors = LINKED_ITIN_DAYS
    ? LINKED_ITIN_DAYS.map(function(day){
        return {
          accName: day.accommodation_name||'', accRate: +day.accommodation_rate||0, accRooms: +day.accommodation_rooms||1,
          vehName: day.vehicle_name||'', vehRate: +day.vehicle_rate||0,
          parkName: day.park_fee_name||'', parkFee: +day.park_adult_fee||0, meals: +day.meals_cost_pp||0,
          activities: (day.activities||[]).map(function(a){ return {name:a.name||'', price:+a.price_pp||0, type:a.type||'included'}; }),
          other: (day.other_items||[]).map(function(o){ return {name:o.name||'', amount:+o.amount||0, per_pax:!!o.per_pax}; })
        };
      })
    : Array.prototype.map.call(document.querySelectorAll('.day-block'), function(block){
        return {
          accName: (block.querySelector('.day-acc-name')||{}).value||'',
          accRate: parseFloat((block.querySelector('.day-acc-rate')||{}).value)||0,
          accRooms: parseInt((block.querySelector('[name="day_acc_rooms[]"]')||{}).value)||1,
          vehName: (block.querySelector('.day-veh-name')||{}).value||'',
          vehRate: parseFloat((block.querySelector('.day-veh-rate')||{}).value)||0,
          parkName: ((block.querySelector('[name="day_park_name[]"]')||{}).value||''),
          parkFee: parseFloat((block.querySelector('.day-park-fee')||{}).value)||0,
          meals: parseFloat((block.querySelector('[name="day_meals_pp[]"]')||{}).value)||0,
          activities: Array.prototype.map.call(block.querySelectorAll('.act-item'), function(row){
            return {
              name: ((row.querySelector('.day-act-name')||{}).value||'').trim(),
              price: parseFloat((row.querySelector('.day-act-price')||{}).value)||0,
              type: (row.querySelector('.day-act-type')||{}).value||'included'
            };
          }),
          other: Array.prototype.map.call(block.querySelectorAll('.other-item'), function(row){
            return {
              name: ((row.querySelector('.day-other-name')||{}).value||'').trim(),
              amount: parseFloat((row.querySelector('.day-other-amount')||{}).value)||0,
              per_pax: (row.querySelector('.day-other-perpax')||{}).checked||false
            };
          })
        };
      });

  dayDescriptors.forEach(function(d){
    if (d.accName && d.accRate > 0) {
      if (!accSeen[d.accName]) accSeen[d.accName] = {rate:d.accRate,rooms:d.accRooms,nights:0};
      accSeen[d.accName].nights++;
    }
    if (d.vehName && d.vehRate > 0) {
      if (!vehSeen[d.vehName]) vehSeen[d.vehName] = {rate:d.vehRate,days:0};
      vehSeen[d.vehName].days++;
    }
    if (d.parkName && d.parkFee > 0) {
      if (!parkSeen[d.parkName]) {
        var pLookup = peParkLookup(d.parkName) || {};
        parkSeen[d.parkName] = {fee:d.parkFee,childFee:pLookup.childFee||0,vehicleFee:pLookup.vehicleFee||0,entries:0};
      }
      parkSeen[d.parkName].entries++;
    }
    if (d.meals > 0) { mealsPP += d.meals; mealsDays++; }

    // Only paid included activities become a priced line item — a free
    // included activity (price 0) has nothing to charge, so it doesn't
    // belong in a cost table (matches ItineraryQuoteBuilder::build()'s
    // actPaid/actFree split server-side).
    d.activities.forEach(function(a){
      if (!a.name || a.type!=='included' || a.price<=0) return;
      if (!actSeen[a.name]) actSeen[a.name] = {price:a.price,count:0};
      actSeen[a.name].count++;
    });

    d.other.forEach(function(o){
      if (!o.name || o.amount <= 0) return;
      if (!otherSeen[o.name]) otherSeen[o.name] = {amount:0,count:0,perPax:o.per_pax};
      otherSeen[o.name].amount += o.amount;
      otherSeen[o.name].count++;
    });
  });

  var lineItems = [];

  // Mirrors App\Services\ItineraryQuoteBuilder::build()'s two accommodation
  // branches exactly — per-person-sharing (real room allocation) when the
  // property has a rate_per_person on file, flat per-room otherwise. The
  // saved price_1pax is always computed at 1 adult / 0 children server-side
  // (see the note above this form), so this only matters when the preview
  // is also set to 1 adult — shown at whatever the preview has regardless,
  // same as every other line here, for a consistent "what would this many
  // travelers pay" view.
  Object.keys(accSeen).forEach(function(name){
    var a = accSeen[name];
    var accExtra = peAccLookup(name);

    if (accExtra && accExtra.ratePerPerson > 0) {
      var personRate = accExtra.ratePerPerson;
      var pairs = Math.floor(adults / 2), soloAdult = adults % 2, roomsUsed = pairs + soloAdult;
      var qty = adults+' Adult'+(adults!==1?'s':'')+' × '+a.nights+' Night'+(a.nights!==1?'s':'')+' ('+roomsUsed+' Room'+(roomsUsed!==1?'s':'')+')';
      lineItems.push({desc:'Accommodation – '+name+' (Per Person Sharing)', unit:personRate, qty:qty, amount:personRate*adults*a.nights});

      if (soloAdult === 1 && accExtra.singleSupp > 0) {
        lineItems.push({desc:'Single Supplement – '+name, unit:accExtra.singleSupp, qty:'1 Guest × '+a.nights+' Night'+(a.nights!==1?'s':''), amount:accExtra.singleSupp*a.nights});
      }
      if (children > 0 && accExtra.childRate > 0) {
        var childQty = children+' Child'+(children!==1?'ren':'')+' × '+a.nights+' Night'+(a.nights!==1?'s':'');
        lineItems.push({desc:'Child Rate – '+name, unit:accExtra.childRate, qty:childQty, amount:accExtra.childRate*children*a.nights});
      }
    } else {
      var amount = a.rate * a.nights * a.rooms;
      var qty2 = a.rooms > 1
        ? a.rooms+' Rooms × '+a.nights+' Night'+(a.nights!==1?'s':'')
        : a.nights+' Night'+(a.nights!==1?'s':'');
      lineItems.push({desc:'Accommodation – '+name, unit:a.rate, qty:qty2, amount:amount});

      if (accExtra) {
        if (adults === 1 && accExtra.singleSupp > 0) {
          lineItems.push({desc:'Single Supplement – '+name, unit:accExtra.singleSupp, qty:qty2, amount:accExtra.singleSupp*a.nights*a.rooms});
        }
        if (children > 0 && accExtra.childRate > 0) {
          var childQty2 = children+' Child'+(children!==1?'ren':'')+' × '+a.nights+' Night'+(a.nights!==1?'s':'');
          lineItems.push({desc:'Child Rate – '+name, unit:accExtra.childRate, qty:childQty2, amount:accExtra.childRate*children*a.nights});
        }
      }
    }
  });

  Object.keys(vehSeen).forEach(function(name){
    var v = vehSeen[name], amount = v.rate * v.days;
    lineItems.push({desc:name+' & Fuel', unit:v.rate, qty:'1 Vehicle × '+v.days+' Day'+(v.days!==1?'s':''), amount:amount});
  });

  Object.keys(parkSeen).forEach(function(name){
    var p = parkSeen[name];
    var amount = (p.fee*adults + p.childFee*children) * p.entries;
    var guestStr = adults+' Adult'+(adults!==1?'s':'')+(children>0?' + '+children+' Child'+(children!==1?'ren':''):'');
    var qty = p.entries > 1 ? (guestStr+' × '+p.entries+' Days') : guestStr;
    lineItems.push({desc:name+' Entry Fees', unit:p.fee, qty:qty, amount:amount});

    if (p.vehicleFee > 0) {
      lineItems.push({desc:name+' Vehicle Entry Fee', unit:p.vehicleFee, qty:p.entries>1?(p.entries+' Days'):'1 Vehicle', amount:p.vehicleFee*p.entries});
    }
  });

  Object.keys(actSeen).forEach(function(name){
    var a = actSeen[name], amount = a.price * travelers * a.count;
    var qty = travelers+' Guest'+(travelers!==1?'s':'')+(a.count>1?' × '+a.count+' Days':'');
    lineItems.push({desc:name, unit:a.price, qty:qty, amount:amount});
  });

  if (mealsPP > 0) {
    var avgMealPP = mealsPP / Math.max(1,mealsDays);
    lineItems.push({desc:'Meals (Full Board — '+mealsDays+' day'+(mealsDays!==1?'s':'')+')', unit:avgMealPP, qty:travelers+' Guests × '+mealsDays+' Days', amount:mealsPP*travelers});
  }

  // Per-traveler is now an explicit checkbox on each Other Cost row
  // (day-other-perpax), not a name-substring guess — matches
  // App\Services\ItineraryQuoteBuilder::build()'s server-side logic
  // exactly, which is what keeps this live preview honest against what's
  // actually saved as the listed price.
  Object.keys(otherSeen).forEach(function(name){
    var o = otherSeen[name];
    var amount = (o.perPax && travelers > 1) ? o.amount * travelers : o.amount;
    var qty = o.perPax && travelers > 1
      ? travelers+' Guests'+(o.count>1?' × '+o.count+' Days':'')
      : (o.count>1 ? o.count+' Times' : '1');
    lineItems.push({desc:name, unit:o.amount, qty:qty, amount:amount});
  });

  var svcPax = +((document.getElementById('service_charge_pax')||{}).value)||0;
  if (svcPax > 0) {
    lineItems.push({desc:'Service Charge', unit:svcPax, qty:travelers+' Guest'+(travelers!==1?'s':''), amount:svcPax*travelers});
  }

  var subtotal = lineItems.reduce(function(sum,li){ return sum+li.amount; }, 0);
  var vat      = subtotal * VAT_RATE;
  var total    = subtotal + vat;
  var pp       = travelers > 0 ? total/travelers : total;

  var tbody = document.getElementById('qs-line-items');
  tbody.innerHTML = lineItems.length ? lineItems.map(function(li){
    return '<tr style="border-bottom:1px solid var(--gray-100)">'
      + '<td style="padding:.45rem 0;color:var(--gray-500)">'+escapeHtml(li.desc)+'</td>'
      + '<td style="padding:.45rem 0;text-align:right">'+fmt(li.unit)+'</td>'
      + '<td style="padding:.45rem 0 .45rem .75rem;font-size:.78rem;color:var(--gray-500)">'+escapeHtml(li.qty)+'</td>'
      + '<td style="padding:.45rem 0;text-align:right;font-weight:600">'+fmt(li.amount)+'</td>'
      + '</tr>';
  }).join('') : '<tr><td colspan="4" style="padding:.75rem 0;color:var(--gray-500);font-size:.8rem">Add accommodation, vehicle, or other costs to the itinerary above to see line items here.</td></tr>';

  document.getElementById('qs-subtotal').textContent = fmt(subtotal);
  document.getElementById('qs-vat').textContent      = fmt(vat);
  document.getElementById('qs-total').textContent    = fmt(total);
  document.getElementById('qs-pp').textContent       = fmt(pp);
  document.getElementById('qs-pp-label').textContent = 'Per Person (÷'+travelers+')';

  window._qsTotal = total;
  calcMargin();
}

function calcMargin() {
  var total = window._qsTotal || 0;
  var cost  = parseFloat(document.getElementById('pe_op_cost').value) || 0;
  var el    = document.getElementById('pe_margin');
  if (total <= 0) { el.textContent = '—'; return; }
  var margin = ((total - cost) / total) * 100;
  el.textContent = margin.toFixed(1) + '%';
  el.style.color = margin < 20 ? '#dc2626' : (margin < 40 ? '#b45309' : '#16a34a');
}

function escapeHtml(s) {
  var d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
}

// Park child fee, looked up from PE_PARKS (same reference data used for
// the datalists) — mirrors ItineraryQuoteBuilder::lookupParkChildFee()
// server-side so this live preview matches the real saved calculation.
function parkChildFee(parkName) {
  for (var i=0; i<PE_PARKS.length; i++) {
    if (PE_PARKS[i].name === parkName) return PE_PARKS[i].childFee||0;
  }
  return 0;
}
function peParkLookup(parkName) {
  for (var i=0; i<PE_PARKS.length; i++) if (PE_PARKS[i].name === parkName) return PE_PARKS[i];
  return null;
}

// ── Includes/Excludes sync ────────────────────────────────────
function syncIncludesExcludes() {
  if (LINKED_ITIN_DAYS) {
    // Linked package — pull the template's own curated lists verbatim.
    document.getElementById('includes_ta').value = LINKED_ITIN_INCLUDES.join('\n');
    document.getElementById('excludes_ta').value = LINKED_ITIN_EXCLUDES.join('\n');
    toast('Includes & Excludes synced from the itinerary template.');
    return;
  }
  // Unlinked — build Includes from what's actually in the day-by-day
  // itinerary below. Excludes has nothing itinerary-side to derive from
  // (visas, insurance, tips aren't part of the itinerary), so it's left
  // for the admin to type.
  var accSeen = {}, vehSeen = {}, parkSeen = {}, hasMeals = false, acts = {};
  document.querySelectorAll('.day-block').forEach(function(block){
    var accName  = ((block.querySelector('.day-acc-name')||{}).value||'').trim();
    var vehName  = ((block.querySelector('.day-veh-name')||{}).value||'').trim();
    var parkName = ((block.querySelector('[name="day_park_name[]"]')||{}).value||'').trim();
    var meals    = parseFloat((block.querySelector('[name="day_meals_pp[]"]')||{}).value)||0;
    if (accName)  accSeen[accName]   = true;
    if (vehName)  vehSeen[vehName]   = true;
    if (parkName) parkSeen[parkName] = true;
    if (meals > 0) hasMeals = true;
    block.querySelectorAll('.act-item').forEach(function(row){
      var name = ((row.querySelector('.day-act-name')||{}).value||'').trim();
      var type = (row.querySelector('.day-act-type')||{}).value||'included';
      if (name && type==='included') acts[name] = true;
    });
  });

  var lines = [];
  if (Object.keys(vehSeen).length) lines.push('Private 4WD safari vehicle (' + Object.keys(vehSeen).join(', ') + ')');
  lines.push('Professional driver-guide');
  Object.keys(parkSeen).forEach(function(p){ lines.push(p + ' entry fees'); });
  Object.keys(accSeen).forEach(function(a){ lines.push('Accommodation: ' + a); });
  if (hasMeals) lines.push('Full-board meals as per itinerary');
  Object.keys(acts).forEach(function(a){ lines.push(a); });

  document.getElementById('includes_ta').value = lines.join('\n');
  toast('Includes built from the itinerary — Excludes still needs typing.');
}

// ── Itinerary sync with duration_days ──────────────────────
// Reference data for building a new day-block's datalists dynamically —
// same accommodations/vehicles/park fees the PHP-rendered day-blocks
// already use, output once here so a freshly-added day (when Nights is
// increased) gets the exact same autocomplete options.
var PE_ACCS  = <?= json_encode(array_map(fn($a) => ['name'=>$a['name'],'id'=>(int)$a['id'],'rate'=>max((float)$a['rate_per_room'],(float)$a['rate_per_person']),'ratePerPerson'=>(float)$a['rate_per_person'],'singleSupp'=>(float)$a['single_supplement'],'childRate'=>(float)$a['child_rate']], $accommodations)) ?>;
function peAccLookup(accName) {
  for (var i=0; i<PE_ACCS.length; i++) if (PE_ACCS[i].name === accName) return PE_ACCS[i];
  return null;
}
var PE_VEHS  = <?= json_encode(array_map(fn($v) => ['name'=>$v['name'],'id'=>(int)$v['id'],'rate'=>(float)$v['daily_rate']+(float)$v['fuel_cost_day']+(float)$v['driver_cost_day']], $vehicles)) ?>;
var PE_PARKS = <?= json_encode(array_map(fn($p) => ['name'=>$p['name'],'id'=>(int)$p['id'],'fee'=>(float)$p['adult_fee'],'childFee'=>(float)$p['child_fee'],'vehicleFee'=>(float)$p['vehicle_fee']], $parkFees)) ?>;

function peDayBlockHtml(i) {
  var accOpts  = PE_ACCS.map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-rate="'+a.rate+'">'; }).join('');
  var vehOpts  = PE_VEHS.map(function(v){ return '<option value="'+v.name+'" data-id="'+v.id+'" data-rate="'+v.rate+'">'; }).join('');
  var parkOpts = PE_PARKS.map(function(p){ return '<option value="'+p.name+'" data-id="'+p.id+'" data-fee="'+p.fee+'">'; }).join('');

  return ''
    +'<div class="admin-card-header" style="background:var(--gray-50)">'
      +'<h3 style="font-size:.85rem">'
        +'<span style="font-family:\'Times New Roman\',Times,serif;color:var(--black);margin-right:.5rem">Day '+(i+1)+'</span>'
        +'<input name="day_title[]" class="admin-input" style="display:inline;width:auto;min-width:200px;font-size:.85rem;border:none;background:transparent;font-weight:600;padding:.15rem .35rem" placeholder="Day title e.g. Arusha → Tarangire">'
      +'</h3>'
      +'<div style="display:flex;align-items:center;gap:.5rem"><span class="day-cost-badge" style="font-size:.65rem;background:var(--black);color:var(--white);padding:.2rem .6rem;font-weight:600">$0</span></div>'
    +'</div>'
    +'<div class="admin-card-body">'
      +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">'
        +'<div><label class="admin-label">Location / Park</label><input name="day_location[]" class="admin-input" placeholder="e.g. Serengeti National Park"></div>'
        +'<div><label class="admin-label">Day Description</label><textarea name="day_desc[]" class="admin-input" rows="2" placeholder="What happens this day…"></textarea></div>'
      +'</div>'
      +'<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">'
        +'<div class="itin-panel itin-panel--blue">'
          +'<p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#1d4ed8"><?= \App\Helpers\AdminIcon::render('accommodations', 13) ?> Accommodation</p>'
          +'<input type="hidden" name="day_acc_id[]" class="day-acc-id" value="0">'
          +'<div style="margin-bottom:.45rem"><label class="admin-label">Property</label>'
            +'<input name="day_acc_name[]" class="admin-input day-acc-name" list="pe-acc-list-'+i+'" oninput="calcItinTotal()" onchange="fillDayAcc(this,'+i+')" placeholder="Select or type">'
            +'<datalist id="pe-acc-list-'+i+'">'+accOpts+'</datalist>'
          +'</div>'
          +'<div class="admin-field-pair" style="display:grid;grid-template-columns:1fr 60px;gap:.35rem">'
            +'<div><label class="admin-label">Rate/Room/Night</label><input name="day_acc_rate[]" type="number" step="1" class="admin-input day-acc-rate" value="0" oninput="calcItinTotal()"></div>'
            +'<div><label class="admin-label">Rooms</label><input name="day_acc_rooms[]" type="number" min="1" class="admin-input" value="1" oninput="calcItinTotal()"></div>'
          +'</div>'
        +'</div>'
        +'<div class="itin-panel itin-panel--green">'
          +'<p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#166534"><?= \App\Helpers\AdminIcon::render('fleet', 13) ?> Vehicle</p>'
          +'<input type="hidden" name="day_veh_id[]" class="day-veh-id" value="0">'
          +'<div style="margin-bottom:.45rem"><label class="admin-label">Vehicle</label>'
            +'<input name="day_veh_name[]" class="admin-input day-veh-name" list="pe-veh-list-'+i+'" oninput="calcItinTotal()" onchange="fillDayVeh(this,'+i+')" placeholder="Select or type">'
            +'<datalist id="pe-veh-list-'+i+'">'+vehOpts+'</datalist>'
          +'</div>'
          +'<div><label class="admin-label">Daily Rate (incl. fuel+driver)</label><input name="day_veh_rate[]" type="number" step="1" class="admin-input day-veh-rate" value="0" oninput="calcItinTotal()"></div>'
        +'</div>'
        +'<div class="itin-panel itin-panel--amber">'
          +'<p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#92400e"><?= \App\Helpers\AdminIcon::render('park-fees', 13) ?> Park &amp; Meals</p>'
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
          +'<p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Activities (Day '+(i+1)+')</p>'
          +'<button type="button" onclick="addDayActivity('+i+')" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>'
        +'</div>'
        +'<div id="day-acts-'+i+'"></div>'
        +'<input type="hidden" name="day_activities_json[]" id="acts-json-'+i+'" value="[]">'
      +'</div>'
      +'<div>'
        +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">'
          +'<p style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Other Costs (Day '+(i+1)+')</p>'
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
    div.style.cssText = 'margin-bottom:1rem;border-left:3px solid var(--black)';
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
  var pax      = (parseInt((document.getElementById('pe_adults')||{}).value)||1)
               + (parseInt((document.getElementById('pe_children')||{}).value)||0);
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
  // touched the summary until the admin also (re-)touched Adults or
  // Children.
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
    +'<button type="button" onclick="this.closest(\'.act-item\').remove();saveDayActs('+dayIdx+')" class="admin-icon-btn admin-icon-btn--danger"><?= \App\Helpers\AdminIcon::render("close", 14) ?></button>';
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
  div.style.cssText='display:grid;grid-template-columns:2fr 100px auto auto;gap:.35rem;align-items:center;margin-bottom:.4rem';
  div.innerHTML='<input class="admin-input day-other-name" placeholder="Item name" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
    +'<input type="number" step="0.01" class="admin-input day-other-amount" value="0" placeholder="$" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
    +'<label style="display:flex;align-items:center;gap:.25rem;font-size:.62rem;color:var(--gray-500);white-space:nowrap" title="Multiply this amount by the number of travelers"><input type="checkbox" class="day-other-perpax" onchange="saveDayOther('+dayIdx+')"> /traveler</label>'
    +'<button type="button" onclick="this.closest(\'.other-item\').remove();saveDayOther('+dayIdx+')" class="admin-icon-btn admin-icon-btn--danger"><?= \App\Helpers\AdminIcon::render("close", 14) ?></button>';
  container.appendChild(div);
}

function saveDayOther(dayIdx) {
  var container=document.getElementById('day-other-'+dayIdx);
  var items=[];
  container.querySelectorAll('.other-item').forEach(function(row){
    var name=row.querySelector('.day-other-name').value.trim();
    if(!name) return;
    items.push({name:name, amount:parseFloat(row.querySelector('.day-other-amount').value)||0, per_pax:row.querySelector('.day-other-perpax').checked});
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
  fd.append('_csrf', window.CSRF_TOKEN || '');

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
    div.innerHTML = '<img src="'+(window.BASE_PATH||'')+img.url+'" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block" loading="lazy">'
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
    div.innerHTML='<img src="'+(window.BASE_PATH||'')+url+'" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block" loading="lazy">'
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

// ── Copy an itinerary template's days into the local, editable
// Day-by-Day Itinerary as a customizable starting point. This does NOT
// link the package (selected_itinerary_id stays whatever it already
// was) — that's what "Link Permanently" / linkPkgItinerary() is for,
// which becomes a real link and switches this section to read-only on
// the next page load instead.
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
        row.querySelector('.day-other-perpax').checked = !!ot.per_pax;
      });
      saveDayOther(i);
    });

    calcItinTotal();
    alert('Itinerary "'+itin.name+'" loaded — '+itin.duration_days+' days filled. Review and save.');
  } catch(e) { alert('Error: '+e.message); }
}

// ── Link this package to an itinerary template permanently. Unlike
// loadPkgItinerary() (a local copy), this is a real link: it navigates
// to the unlink/link GET action in package_edit.php's PHP, which writes
// itinerary_id straight to the DB and reloads the page — at which point
// the Day-by-Day Itinerary section becomes the read-only, linked view.
async function linkPkgItinerary() {
  var id = document.getElementById('pkg_itin_select').value;
  if (!id) { alert('Select an itinerary first.'); return; }
  var ok = await confirmDialog('Link this package to that itinerary template? Its pricing and day-by-day display will then always follow the template — edit the itinerary in the Itinerary Manager instead of here.');
  if (!ok) return;
  window.location = (window.BASE_PATH||'') + '/admin/package_edit.php?id=<?= $id ?>&link=' + id;
}

// calcItinTotal() (defined above, alongside syncItineraryDays) now owns
// the day-cost badges and the itinerary section's grand total/per-person
// figures — it replaced a dead calcDayTotals() that matched field names
// (accommodation_rate[], vehicle_rate[], meals_cost[]) this section no
// longer has after being rebuilt to match the Itinerary Manager's fields.
document.addEventListener('DOMContentLoaded', calcItinTotal);

</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
