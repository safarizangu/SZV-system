<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
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
    $park = trim($_POST['park_key'] ?? '');
    $rr   = (float)($_POST['rate_per_room'] ?? 0);
    $rp   = (float)($_POST['rate_per_person'] ?? 0);
    $ss   = (float)($_POST['single_supplement'] ?? 0);
    $cr   = (float)($_POST['child_rate'] ?? 0);
    $mp   = trim($_POST['meal_plan'] ?? 'fb');
    $act  = isset($_POST['is_active']) ? 1 : 0;
    $notes= trim($_POST['notes'] ?? '');
    $imgUrls = array_values(array_filter(array_map('trim', explode(',', $_POST['images_json'] ?? ''))));
    $images  = $imgUrls ? json_encode($imgUrls) : null;
    try {
        if ($id > 0) {
            $db->prepare("UPDATE accommodations SET name=?,level=?,location=?,park_key=?,rate_per_room=?,rate_per_person=?,single_supplement=?,child_rate=?,meal_plan=?,is_active=?,notes=?,images_json=? WHERE id=?")
               ->execute([$name,$lev,$loc,$park,$rr,$rp,$ss,$cr,$mp,$act,$notes,$images,$id]);
        } else {
            $db->prepare("INSERT INTO accommodations(name,level,location,park_key,rate_per_room,rate_per_person,single_supplement,child_rate,meal_plan,is_active,notes,images_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$name,$lev,$loc,$park,$rr,$rp,$ss,$cr,$mp,$act,$notes,$images]);
        }
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
$rows = [];
try { $rows = $db->query("SELECT * FROM accommodations ORDER BY level, name")->fetchAll(); }
catch(Exception $e){}
$galleryImgs = $edit ? (json_decode($edit['images_json'] ?? '[]', true) ?: []) : [];
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
    <a href="?edit=<?= $r['id'] ?>" class="admin-btn admin-btn--outline" style="font-size:.7rem">Edit</a>
    <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
      <?= \App\Middleware\CsrfMiddleware::field() ?>
      <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
      <button class="admin-btn admin-btn--danger" style="font-size:.7rem">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
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
          <div><label class="admin-label">Name *</label>
            <input name="name" data-av="required minlen:2" class="admin-input" required value="<?= htmlspecialchars($edit['name']??'') ?>"></div>
          <div><label class="admin-label">Level</label>
            <select name="level" class="admin-input">
              <?php foreach(['budget'=>'Budget','midrange'=>'Mid-Range','highend'=>'High-End','ultra_luxury'=>'Ultra Luxury'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($edit['level']??'midrange')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label class="admin-label">Location</label>
            <input name="location" class="admin-input" value="<?= htmlspecialchars($edit['location']??'') ?>"></div>
          <div><label class="admin-label">Park Key</label>
            <input name="park_key" class="admin-input" value="<?= htmlspecialchars($edit['park_key']??'') ?>"></div>
          <div><label class="admin-label">Rate/Room (USD)</label>
            <input name="rate_per_room" data-av="min:0" type="number" step="0.01" class="admin-input" value="<?= $edit['rate_per_room']??0 ?>"></div>
          <div><label class="admin-label">Rate/Person (USD)</label>
            <input name="rate_per_person" data-av="min:0" type="number" step="0.01" class="admin-input" value="<?= $edit['rate_per_person']??0 ?>"></div>
          <div><label class="admin-label">Single Supplement</label>
            <input name="single_supplement" type="number" step="0.01" class="admin-input" value="<?= $edit['single_supplement']??0 ?>"></div>
          <div><label class="admin-label">Child Rate</label>
            <input name="child_rate" type="number" step="0.01" class="admin-input" value="<?= $edit['child_rate']??0 ?>"></div>
          <div><label class="admin-label">Meal Plan</label>
            <select name="meal_plan" class="admin-input">
              <?php foreach(['fb'=>'Full Board','hb'=>'Half Board','bb'=>'B&B','ai'=>'All Inclusive'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($edit['meal_plan']??'fb')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="margin-top:1rem"><label class="admin-label">Notes</label>
          <textarea name="notes" class="admin-input" rows="2"><?= htmlspecialchars($edit['notes']??'') ?></textarea></div>

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
