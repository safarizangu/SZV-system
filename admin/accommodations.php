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
    $images=trim($_POST['images_json'] ?? '');
    try {
        if ($id > 0) {
            $db->prepare("UPDATE accommodations SET name=?,level=?,location=?,park_key=?,rate_per_room=?,rate_per_person=?,single_supplement=?,child_rate=?,meal_plan=?,is_active=?,notes=?,images_json=? WHERE id=?")
               ->execute([$name,$lev,$loc,$park,$rr,$rp,$ss,$cr,$mp,$act,$notes,$images,$id]);
        } else {
            $db->prepare("INSERT INTO accommodations(name,level,location,park_key,rate_per_room,rate_per_person,single_supplement,child_rate,meal_plan,is_active,notes,images_json) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$name,$lev,$loc,$park,$rr,$rp,$ss,$cr,$mp,$act,$notes,$images]);
        }
        $msg = 'Saved.';
    } catch(Exception $e){ $msg = 'Error: '.$e->getMessage(); }
    header('Location: accommodations.php?msg='.urlencode($msg)); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
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
?>
<?php if($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>
<div class="admin-card" style="margin-bottom:2rem">
  <div class="admin-card-header"><h3><?= $edit ? 'Edit Accommodation' : 'Add Accommodation' ?></h3></div>
  <div class="admin-card-body">
  <form method="POST" class="av-form">
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
    <div style="margin-top:.75rem">
      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
        <input type="checkbox" name="is_active" value="1" <?= ($edit['is_active']??1)?'checked':'' ?>>
        <span class="admin-label" style="margin:0">Active</span>
      </label>
    </div>
    <div style="margin-top:1rem;display:flex;gap:.5rem">
      <button class="admin-btn admin-btn--primary">Save</button>
      <?php if($edit): ?><a href="accommodations.php" class="admin-btn admin-btn--outline">Cancel</a><?php endif; ?>
    </div>
  </form>
  </div>
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
