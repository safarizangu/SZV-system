<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'accommodations.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Accommodations';
ob_start();
?>

<?php
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $lev  = $_POST['level'] ?? 'midrange';
    $loc  = trim($_POST['location'] ?? '');
    // DB-12: NULL, not '' — a blank field must mean "no park", and ''
    // doesn't match anything in park_fees.park_key (the column's new FK
    // target), so saving '' here would throw on every save with no park.
    $park = trim($_POST['park_key'] ?? '') ?: null;
    $supp = (int)($_POST['supplier_id'] ?? 0) ?: null;
    $rr   = (float)($_POST['rate_per_room'] ?? 0);
    $rp   = (float)($_POST['rate_per_person'] ?? 0);
    $ss   = (float)($_POST['single_supplement'] ?? 0);
    $cr   = (float)($_POST['child_rate'] ?? 0);
    $avr  = trim($_POST['available_rooms'] ?? '') === '' ? null : max(0, (int)$_POST['available_rooms']);
    $mp   = trim($_POST['meal_plan'] ?? 'fb');
    $act  = isset($_POST['is_active']) ? 1 : 0;
    $notes= trim($_POST['notes'] ?? '');
    $imgUrls = array_values(array_filter(array_map('trim', explode(',', $_POST['images_json'] ?? ''))));
    $images  = $imgUrls ? json_encode($imgUrls) : null;
    try {
        if ($id > 0) {
            $db->prepare("UPDATE accommodations SET name=?,level=?,location=?,park_key=?,supplier_id=?,rate_per_room=?,rate_per_person=?,single_supplement=?,child_rate=?,available_rooms=?,meal_plan=?,is_active=?,notes=?,images_json=? WHERE id=?")
               ->execute([$name,$lev,$loc,$park,$supp,$rr,$rp,$ss,$cr,$avr,$mp,$act,$notes,$images,$id]);
        } else {
            $db->prepare("INSERT INTO accommodations(name,level,location,park_key,supplier_id,rate_per_room,rate_per_person,single_supplement,child_rate,available_rooms,meal_plan,is_active,notes,images_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$name,$lev,$loc,$park,$supp,$rr,$rp,$ss,$cr,$avr,$mp,$act,$notes,$images]);
        }
        \App\Services\LocationRegistry::touchLocation($db, $loc);
        $msg = 'Saved.';
    } catch(Exception $e){ $msg = 'Error: '.$e->getMessage(); }
    header('Location: accommodations.php?msg='.urlencode($msg)); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try { $db->prepare("DELETE FROM accommodations WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch(Exception $e){}
    header('Location: accommodations.php'); exit;
}
$msg  = htmlspecialchars($_GET['msg'] ?? '');
$edit = null;
if (isset($_GET['edit'])) { try { $s=$db->prepare("SELECT * FROM accommodations WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit=$s->fetch(); } catch(Exception $e){} }
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page-1)*$perPage;
$rows = []; $totalRows = 0; $totalPages = 0;
try {
    $totalRows  = (int)$db->query("SELECT COUNT(*) FROM accommodations")->fetchColumn();
    $totalPages = (int)ceil($totalRows/$perPage);
    $rows = $db->query("SELECT * FROM accommodations ORDER BY level, name LIMIT $perPage OFFSET $offset")->fetchAll();
}
catch(Exception $e){}
$galleryImgs = $edit ? (json_decode($edit['images_json'] ?? '[]', true) ?: []) : [];
$allLocations = \App\Services\LocationRegistry::allLocations($db);

// Usage count for the delete-confirm warning — day-by-day itineraries
// snapshot an accommodation's name/rate rather than holding a live FK
// (deliberately, so an old quotation never changes retroactively), so
// there's no DB constraint to stop a delete. This is a soft warning, not
// a block: three JSON blobs fetched once (not per row) and matched by
// name in PHP, cheap at today's data volume and avoids an N+1 query per
// row in the table below.
$accUsageBlobs = [];
try {
    $accUsageBlobs = array_merge(
        $db->query("SELECT days AS j FROM itineraries")->fetchAll(\PDO::FETCH_COLUMN),
        $db->query("SELECT itinerary AS j FROM quotations WHERE itinerary IS NOT NULL")->fetchAll(\PDO::FETCH_COLUMN),
        $db->query("SELECT itinerary AS j FROM packages WHERE itinerary IS NOT NULL")->fetchAll(\PDO::FETCH_COLUMN)
    );
} catch (\Exception $e) {}
function accUsageCount(array $blobs, string $name): int {
    if ($name === '') return 0;
    // MySQL's JSON column type re-serializes with a space after the
    // colon ("key": "value"), not the compact form a hand-written
    // json_encode() would use — match that, not the compact form.
    $needle = '"accommodation_name": "' . addcslashes($name, '"\\') . '"';
    $count = 0;
    foreach ($blobs as $b) { if ($b && str_contains($b, $needle)) $count++; }
    return $count;
}
?>
<?php if($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Accommodations</h3>
    <a href="accommodations.php?new=1" class="admin-btn admin-btn--primary" style="font-size:.68rem">+ New Accommodation</a>
  </div>
<div class="admin-table-wrap">
<table class="admin-table">
<thead><tr><th>Name</th><th>Level</th><th>Location</th><th>Park</th><th>Rate/Room</th><th>Rate/Person</th><th>Active</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
  <td><?= ucfirst($r['level']) ?></td>
  <td><?= htmlspecialchars($r['location']??'—') ?></td>
  <td><?= htmlspecialchars($r['park_key']??'—') ?></td>
  <td>$<?= number_format($r['rate_per_room'],0) ?></td>
  <td>$<?= number_format($r['rate_per_person'],0) ?></td>
  <td><?= $r['is_active'] ? '<span class="badge-status badge-status--green">Active</span>' : '<span class="badge-status badge-status--gray">Inactive</span>' ?></td>
  <td>
    <div class="admin-row-actions">
      <button type="button" class="admin-icon-btn admin-row-actions-toggle" aria-label="Actions" aria-haspopup="true" aria-expanded="false"><?= \App\Helpers\AdminIcon::render('dots-vertical', 16) ?></button>
      <div class="admin-row-actions-menu">
        <a href="?edit=<?= $r['id'] ?>" class="admin-row-actions-item"><?= \App\Helpers\AdminIcon::render('edit', 14) ?> Edit</a>
        <?php
        $accUsed = accUsageCount($accUsageBlobs, $r['name']);
        $accDeleteMsg = $accUsed > 0
            ? "Delete this accommodation? It appears in {$accUsed} saved itinerary/quotation/package day plan" . ($accUsed!==1?'s':'') . " — those keep their own saved rate and won't break, but it will no longer be offered for new ones."
            : 'Delete this accommodation?';
        ?>
        <form method="POST" onsubmit="return confirmSubmitGuard(event, this, '<?= htmlspecialchars(addslashes($accDeleteMsg)) ?>', {danger:true})">
          <?= \App\Middleware\CsrfMiddleware::field() ?>
          <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
          <button type="submit" class="admin-row-actions-item admin-row-actions-item--danger"><?= \App\Helpers\AdminIcon::render('trash', 14) ?> Delete</button>
        </form>
      </div>
    </div>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($totalPages > 1): ?>
<div class="pagination" style="border-top:none">
  <span style="font-size:.75rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalRows) ?> total)</span>
  <div class="page-btns">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
    <a href="?page=<?= $i ?>" class="page-btn<?= $i===$page?' active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>
</div>

<!-- Add/Edit modal -->
<div id="formModal" class="admin-modal-overlay">
  <div class="admin-modal admin-modal--wide">
    <div class="admin-modal-header">
      <span class="admin-modal-title"><?= $edit ? 'Edit Accommodation' : 'Add Accommodation' ?></span>
      <button type="button" onclick="closeFormModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <form method="POST" class="av-form">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="id"   value="<?= $edit['id']??0 ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
          <div><label class="admin-label" for="name">Name *</label>
            <input id="name" name="name" data-av="required minlen:2" class="admin-input" required value="<?= htmlspecialchars($edit['name']??'') ?>"></div>
          <div><label class="admin-label" for="level">Level</label>
            <select id="level" name="level" class="admin-input">
              <?php foreach(['budget'=>'Budget','midrange'=>'Mid-Range','highend'=>'High-End','luxury'=>'Luxury','ultra_luxury'=>'Ultra Luxury'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($edit['level']??'midrange')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label class="admin-label" for="location">Location</label>
            <input id="location" name="location" class="admin-input" list="location-list" value="<?= htmlspecialchars($edit['location']??'') ?>" placeholder="e.g. Serengeti National Park"></div>
          <div><label class="admin-label" for="park_key">Park Key</label>
            <input id="park_key" name="park_key" class="admin-input" value="<?= htmlspecialchars($edit['park_key']??'') ?>"></div>
          <div><label class="admin-label" for="supplier_id">Supplier</label>
            <select id="supplier_id" name="supplier_id" class="admin-input">
              <option value="0">— None linked —</option>
              <?php
              try { $__suppliers = $db->query("SELECT id, name FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll(); }
              catch (\Exception $e) { $__suppliers = []; }
              foreach ($__suppliers as $__sup): ?>
              <option value="<?= $__sup['id'] ?>" <?= (int)($edit['supplier_id']??0)===(int)$__sup['id']?'selected':'' ?>><?= htmlspecialchars($__sup['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <p style="font-size:.65rem;color:var(--gray-500);margin-top:.25rem">Manage suppliers on the <a href="suppliers.php" target="_blank">Suppliers</a> page.</p>
          </div>
          <div><label class="admin-label" for="rate_per_room">Rate/Room (USD)</label>
            <input id="rate_per_room" name="rate_per_room" data-av="min:0" type="number" step="0.01" class="admin-input" value="<?= $edit['rate_per_room']??0 ?>"></div>
          <div><label class="admin-label" for="rate_per_person">Rate/Person (USD)</label>
            <input id="rate_per_person" name="rate_per_person" data-av="min:0" type="number" step="0.01" class="admin-input" value="<?= $edit['rate_per_person']??0 ?>"></div>
          <div><label class="admin-label" for="single_supplement">Single Supplement</label>
            <input id="single_supplement" name="single_supplement" type="number" step="0.01" class="admin-input" value="<?= $edit['single_supplement']??0 ?>"></div>
          <div><label class="admin-label" for="child_rate">Child Rate</label>
            <input id="child_rate" name="child_rate" type="number" step="0.01" class="admin-input" value="<?= $edit['child_rate']??0 ?>"></div>
          <div><label class="admin-label" for="available_rooms">Available Rooms <span style="font-weight:600;color:var(--gray-500)">(allotment, optional)</span></label>
            <input id="available_rooms" name="available_rooms" type="number" min="0" step="1" class="admin-input" placeholder="Leave blank if not tracked" value="<?= $edit['available_rooms'] ?? '' ?>"></div>
          <div><label class="admin-label" for="meal_plan">Meal Plan</label>
            <select id="meal_plan" name="meal_plan" class="admin-input">
              <?php foreach(['fb'=>'Full Board','hb'=>'Half Board','bb'=>'B&B','ai'=>'All Inclusive'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($edit['meal_plan']??'fb')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="margin-top:1rem"><label class="admin-label" for="notes">Notes</label>
          <textarea id="notes" name="notes" class="admin-input" rows="2"><?= htmlspecialchars($edit['notes']??'') ?></textarea></div>

        <!-- Images -->
        <div style="margin-top:1rem">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
            <label class="admin-label" style="margin:0">Images</label>
            <div style="display:flex;gap:.5rem">
              <a href="gallery.php?folder=accommodation" target="_blank" class="admin-btn admin-btn--outline" style="font-size:.62rem;padding:.25rem .5rem">Manage Gallery ↗</a>
              <button type="button" onclick="accGalleryPicker.open()" class="admin-btn admin-btn--primary" style="font-size:.62rem;padding:.25rem .5rem">+ Select Images</button>
            </div>
          </div>
          <input type="hidden" name="images_json" id="images_json_input" value="<?= htmlspecialchars(implode(',', $galleryImgs)) ?>">
          <div id="selected-gallery" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:.5rem;min-height:70px;padding:.5rem;background:#f9f9f8;border:1.5px dashed #e5e7eb"></div>
        </div>

        <div style="margin-top:.75rem">
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" <?= ($edit['is_active']??1)?'checked':'' ?>>
            <span class="admin-label" style="margin:0">Active</span>
          </label>
        </div>
        <div style="margin-top:1rem;display:flex;gap:.5rem">
          <button class="admin-btn admin-btn--primary">Save</button>
          <button type="button" onclick="closeFormModal()" class="admin-btn admin-btn--outline">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Gallery picker modal -->
<div id="galleryPickerModal" class="admin-modal-overlay" style="display:none">
  <div class="admin-modal admin-modal--wide" style="max-width:900px;display:flex;flex-direction:column">
    <div class="admin-modal-header" style="flex-shrink:0">
      <div>
        <span class="admin-modal-title">Select Images</span>
        <span id="picker-selected-count" style="font-size:.72rem;color:var(--gray-500);margin-left:.75rem">0 selected</span>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center">
        <button type="button" onclick="accGalleryPicker.apply()" class="admin-btn admin-btn--primary" style="font-size:.72rem">Apply Selection</button>
        <button type="button" onclick="accGalleryPicker.close()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
      </div>
    </div>
    <div style="display:flex;gap:0;border-bottom:1px solid var(--gray-200);overflow-x:auto;flex-shrink:0" id="picker-tabs"></div>
    <div style="flex:1;overflow-y:auto;padding:1rem">
      <input type="text" id="picker-search" placeholder="Filter images…" class="admin-input" style="margin-bottom:1rem" oninput="accGalleryPicker.filter(this.value)">
      <div id="picker-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.65rem"></div>
    </div>
  </div>
</div>

<datalist id="location-list">
  <?php foreach ($allLocations as $__l): ?>
  <option value="<?= htmlspecialchars($__l['name']) ?>">
  <?php endforeach; ?>
</datalist>

<script src="<?= BASE_PATH ?>/assets/js/admin-gallery-picker.js"></script>
<script>
function openFormModal() { document.getElementById('formModal').classList.add('open'); }
function closeFormModal() { document.getElementById('formModal').classList.remove('open'); }
document.getElementById('formModal').addEventListener('click', function(e){ if (e.target === this) closeFormModal(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeFormModal(); });
<?php if ($edit || (isset($msg) && str_starts_with($msg,'Error')) || isset($_GET['new'])): ?>
document.addEventListener('DOMContentLoaded', openFormModal);
<?php endif; ?>

var accGalleryPicker = initAdminGalleryPicker({
  inputId: 'images_json_input',
  previewId: 'selected-gallery',
  modalId: 'galleryPickerModal',
  tabsId: 'picker-tabs',
  gridId: 'picker-grid',
  searchId: 'picker-search',
  countId: 'picker-selected-count',
  basePath: '<?= BASE_PATH ?>',
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
