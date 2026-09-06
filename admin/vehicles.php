<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Fleet & Vehicles';
ob_start();
?>

<?php
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id   = (int)($_POST['id'] ?? 0);
    $key  = trim($_POST['vehicle_key'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $cap  = (int)($_POST['capacity'] ?? 6);
    $dr   = (float)($_POST['daily_rate'] ?? 0);
    $fc   = (float)($_POST['fuel_cost_day'] ?? 0);
    $drc  = (float)($_POST['driver_cost_day'] ?? 0);
    $lev  = $_POST['level'] ?? 'standard';
    $act  = isset($_POST['is_active']) ? 1 : 0;
    $images=trim($_POST['images_json']??'');
    try {
        if ($id > 0) {
            $db->prepare("UPDATE vehicles SET vehicle_key=?,name=?,capacity=?,daily_rate=?,fuel_cost_day=?,driver_cost_day=?,level=?,is_active=?,images_json=? WHERE id=?")
               ->execute([$key,$name,$cap,$dr,$fc,$drc,$lev,$act,$images,$id]);
        } else {
            $db->prepare("INSERT INTO vehicles(vehicle_key,name,capacity,daily_rate,fuel_cost_day,driver_cost_day,level,is_active,images_json) VALUES(?,?,?,?,?,?,?,?)")
               ->execute([$key,$name,$cap,$dr,$fc,$drc,$lev,$act,$images]);
        }
        $msg = 'Saved.';
    } catch(Exception $e){ $msg = 'Error: '.$e->getMessage(); }
    header('Location: vehicles.php?msg='.urlencode($msg)); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try { $db->prepare("DELETE FROM vehicles WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch(Exception $e){}
    header('Location: vehicles.php'); exit;
}
$msg  = htmlspecialchars($_GET['msg'] ?? '');
$edit = null;
if (isset($_GET['edit'])) { try { $s=$db->prepare("SELECT * FROM vehicles WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit=$s->fetch(); } catch(Exception $e){} }
$rows = [];
try { $rows = $db->query("SELECT * FROM vehicles ORDER BY level, name")->fetchAll(); }
catch(Exception $e){}
?>
<?php if($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>
<div class="admin-card" style="margin-bottom:2rem">
  <div class="admin-card-header"><h3><?= $edit ? 'Edit Vehicle' : 'Add Vehicle' ?></h3></div>
  <div class="admin-card-body">
  <form method="POST" class="av-form">
    <input type="hidden" name="save" value="1">
    <input type="hidden" name="id"   value="<?= $edit['id']??0 ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
      <div><label class="admin-label">Key</label>
        <input name="vehicle_key" data-av="required slug" class="admin-input" required value="<?= htmlspecialchars($edit['vehicle_key']??'') ?>"></div>
      <div><label class="admin-label">Name</label>
        <input name="name" data-av="required minlen:2" class="admin-input" required value="<?= htmlspecialchars($edit['name']??'') ?>"></div>
      <div><label class="admin-label">Capacity (pax)</label>
        <input name="capacity" data-av="required integer min:1" type="number" class="admin-input" value="<?= $edit['capacity']??6 ?>"></div>
      <div><label class="admin-label">Daily Rate (USD)</label>
        <input name="daily_rate" data-av="required min:0" type="number" step="0.01" class="admin-input" value="<?= $edit['daily_rate']??0 ?>"></div>
      <div><label class="admin-label">Fuel/Day (USD)</label>
        <input name="fuel_cost_day" type="number" step="0.01" class="admin-input" value="<?= $edit['fuel_cost_day']??0 ?>"></div>
      <div><label class="admin-label">Driver/Day (USD)</label>
        <input name="driver_cost_day" type="number" step="0.01" class="admin-input" value="<?= $edit['driver_cost_day']??0 ?>"></div>
      <div><label class="admin-label">Level</label>
        <select name="level" class="admin-input">
          <?php foreach(['standard'=>'Standard','comfort'=>'Comfort','luxury'=>'Luxury'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($edit['level']??'standard')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;align-items:flex-end">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;padding-bottom:.5rem">
          <input type="checkbox" name="is_active" value="1" <?= ($edit['is_active']??1)?'checked':'' ?>>
          <span class="admin-label" style="margin:0">Active</span>
        </label>
      </div>
    </div>
    <div style="margin-top:1rem;display:flex;gap:.5rem">
      <button class="admin-btn admin-btn--primary">Save</button>
      <?php if($edit): ?><a href="vehicles.php" class="admin-btn admin-btn--outline">Cancel</a><?php endif; ?>
    </div>
  </form>
  </div>
</div>
<div class="admin-table-wrap">
<table class="admin-table">
<thead><tr><th>Key</th><th>Name</th><th>Capacity</th><th>Daily Rate</th><th>Fuel/Day</th><th>Driver/Day</th><th>Level</th><th>Active</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><code><?= htmlspecialchars($r['vehicle_key']) ?></code></td>
  <td><?= htmlspecialchars($r['name']) ?></td>
  <td><?= $r['capacity'] ?> pax</td>
  <td>$<?= number_format($r['daily_rate'],0) ?></td>
  <td>$<?= number_format($r['fuel_cost_day'],0) ?></td>
  <td>$<?= number_format($r['driver_cost_day'],0) ?></td>
  <td><?= ucfirst($r['level']) ?></td>
  <td><?= $r['is_active'] ? '<span class="badge-status badge-status--green">Active</span>' : '<span class="badge-status badge-status--gray">Inactive</span>' ?></td>
  <td>
    <a href="?edit=<?= $r['id'] ?>" class="admin-btn admin-btn--outline" style="font-size:.7rem">Edit</a>
    <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
      <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
      <button class="admin-btn admin-btn--danger" style="font-size:.7rem">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
