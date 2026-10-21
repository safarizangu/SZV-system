<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Settings page — admin/superadmin only, regardless of what's in the
// sidebar (staff and agent never see the nav link, but that alone isn't
// access control).
if (!AuthMiddleware::isAdminRole()) { header('Location: index.php'); exit; }
$db = \App\Models\Database::getInstance();
$pageTitle = 'Park Fees';
ob_start();
?>

<?php
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $id  = (int)($_POST['id'] ?? 0);
    $fields = ['park_key','name','location','country','adult_fee','child_fee','vehicle_fee','resident_adult','resident_child','fee_unit','notes'];
    $vals = [];
    foreach($fields as $f) $vals[] = trim($_POST[$f] ?? '');
    $vals[] = isset($_POST['is_active']) ? 1 : 0;
    try {
        if ($id > 0) {
            $db->prepare("UPDATE park_fees SET park_key=?,name=?,location=?,country=?,adult_fee=?,child_fee=?,vehicle_fee=?,resident_adult=?,resident_child=?,fee_unit=?,notes=?,is_active=? WHERE id=?")
               ->execute(array_merge($vals,[$id]));
        } else {
            $db->prepare("INSERT INTO park_fees(park_key,name,location,country,adult_fee,child_fee,vehicle_fee,resident_adult,resident_child,fee_unit,notes,is_active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute($vals);
        }
        \App\Services\LocationRegistry::touchLocation($db, $vals[2], $vals[3]);
        $msg = 'Saved.';
    } catch(Exception $e){ $msg = 'Error: '.$e->getMessage(); }
    header('Location: park_fees.php?msg='.urlencode($msg)); exit;
}
$msg  = htmlspecialchars($_GET['msg'] ?? '');
$edit = null;
if (isset($_GET['edit'])) { try { $s=$db->prepare("SELECT * FROM park_fees WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit=$s->fetch(); } catch(Exception $e){} }
$rows = [];
try { $rows = $db->query("SELECT * FROM park_fees ORDER BY name LIMIT 200")->fetchAll(); }
catch(Exception $e){}
$allLocations = \App\Services\LocationRegistry::allLocations($db);
$allCountries = \App\Services\LocationRegistry::allCountries($db);
?>
<?php if($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Park Fees</h3>
    <a href="park_fees.php?new=1" class="admin-btn admin-btn--primary" style="font-size:.68rem">+ New Park</a>
  </div>
<div class="admin-table-wrap">
<table class="admin-table">
<thead><tr><th>Key</th><th>Park</th><th>Location</th><th>Country</th><th>Non-Res Adult</th><th>Non-Res Child</th><th>Vehicle</th><th>Res Adult</th><th>Active</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><code><?= htmlspecialchars($r['park_key']) ?></code></td>
  <td><?= htmlspecialchars($r['name']) ?></td>
  <td><?= htmlspecialchars($r['location']??'—') ?></td>
  <td><?= htmlspecialchars($r['country']) ?></td>
  <td>$<?= number_format($r['adult_fee'],0) ?></td>
  <td>$<?= number_format($r['child_fee'],0) ?></td>
  <td>$<?= number_format($r['vehicle_fee'],0) ?></td>
  <td>$<?= number_format($r['resident_adult'],0) ?></td>
  <td><?= $r['is_active'] ? '<span class="badge-status badge-status--green">Active</span>' : '<span class="badge-status badge-status--gray">Inactive</span>' ?></td>
  <td>
    <div class="admin-row-actions">
      <button type="button" class="admin-icon-btn admin-row-actions-toggle" aria-label="Actions" aria-haspopup="true" aria-expanded="false"><?= \App\Helpers\AdminIcon::render('dots-vertical', 16) ?></button>
      <div class="admin-row-actions-menu">
        <a href="?edit=<?= $r['id'] ?>" class="admin-row-actions-item"><?= \App\Helpers\AdminIcon::render('edit', 14) ?> Edit</a>
      </div>
    </div>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div>

<!-- Add/Edit modal -->
<div id="formModal" class="admin-modal-overlay">
  <div class="admin-modal admin-modal--wide">
    <div class="admin-modal-header">
      <span class="admin-modal-title"><?= $edit ? 'Edit Park Fee' : 'Add Park' ?></span>
      <button type="button" onclick="closeFormModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <form method="POST" class="av-form">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="id"   value="<?= $edit['id']??0 ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
          <div><label class="admin-label">Park Key</label>
            <input name="park_key" data-av="required slug" class="admin-input" required value="<?= htmlspecialchars($edit['park_key']??'') ?>"></div>
          <div><label class="admin-label">Park Name</label>
            <input name="name" data-av="required minlen:2" class="admin-input" required value="<?= htmlspecialchars($edit['name']??'') ?>"></div>
          <div><label class="admin-label">Location / Area</label>
            <input name="location" class="admin-input" list="location-list" value="<?= htmlspecialchars($edit['location']??'') ?>" placeholder="e.g. Serengeti"></div>
          <div><label class="admin-label">Country</label>
            <input name="country" class="admin-input" list="country-list" value="<?= htmlspecialchars($edit['country']??'Tanzania') ?>"></div>
          <div><label class="admin-label">Non-Resident Adult (USD)</label>
            <input name="adult_fee" data-av="required min:0" type="number" step="0.01" class="admin-input" value="<?= $edit['adult_fee']??0 ?>"></div>
          <div><label class="admin-label">Non-Resident Child (USD)</label>
            <input name="child_fee" data-av="min:0" type="number" step="0.01" class="admin-input" value="<?= $edit['child_fee']??0 ?>"></div>
          <div><label class="admin-label">Vehicle Entry Fee (USD)</label>
            <input name="vehicle_fee" data-av="min:0" type="number" step="0.01" class="admin-input" value="<?= $edit['vehicle_fee']??0 ?>"></div>
          <div><label class="admin-label">Resident Adult (USD)</label>
            <input name="resident_adult" type="number" step="0.01" class="admin-input" value="<?= $edit['resident_adult']??0 ?>"></div>
          <div><label class="admin-label">Resident Child (USD)</label>
            <input name="resident_child" type="number" step="0.01" class="admin-input" value="<?= $edit['resident_child']??0 ?>"></div>
          <div><label class="admin-label">Fee Unit</label>
            <input name="fee_unit" class="admin-input" value="<?= htmlspecialchars($edit['fee_unit']??'per_person_per_entry') ?>"></div>
          <div style="display:flex;align-items:flex-end">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;padding-bottom:.5rem">
              <input type="checkbox" name="is_active" value="1" <?= ($edit['is_active']??1)?'checked':'' ?>>
              <span class="admin-label" style="margin:0">Active</span>
            </label>
          </div>
        </div>
        <div style="margin-top:1rem"><label class="admin-label">Notes</label>
          <textarea name="notes" class="admin-input" rows="2"><?= htmlspecialchars($edit['notes']??'') ?></textarea></div>
        <div style="margin-top:1rem;display:flex;gap:.5rem">
          <button class="admin-btn admin-btn--primary">Save</button>
          <button type="button" onclick="closeFormModal()" class="admin-btn admin-btn--outline">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<datalist id="location-list">
  <?php foreach ($allLocations as $__l): ?>
  <option value="<?= htmlspecialchars($__l['name']) ?>">
  <?php endforeach; ?>
</datalist>
<datalist id="country-list">
  <?php foreach ($allCountries as $__c): ?>
  <option value="<?= htmlspecialchars($__c) ?>">
  <?php endforeach; ?>
</datalist>

<script>
function openFormModal() { document.getElementById('formModal').classList.add('open'); }
function closeFormModal() { document.getElementById('formModal').classList.remove('open'); }
document.getElementById('formModal').addEventListener('click', function(e){ if (e.target === this) closeFormModal(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeFormModal(); });
<?php if ($edit || (isset($msg) && str_starts_with($msg,'Error')) || isset($_GET['new'])): ?>
document.addEventListener('DOMContentLoaded', openFormModal);
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
