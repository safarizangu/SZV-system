<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Activities & Add-ons';
ob_start();
?>

<?php
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $id    = (int)($_POST['id'] ?? 0);
    $key   = trim($_POST['activity_key'] ?? '');
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $unit  = $_POST['price_unit'] ?? 'per_person';
    $cat   = trim($_POST['category'] ?? '');
    $act   = isset($_POST['is_active']) ? 1 : 0;
    try {
        if ($id > 0) {
            $db->prepare("UPDATE activities SET activity_key=?,name=?,description=?,price=?,price_unit=?,category=?,is_active=? WHERE id=?")
               ->execute([$key,$name,$desc,$price,$unit,$cat,$act,$id]);
        } else {
            $db->prepare("INSERT INTO activities(activity_key,name,description,price,price_unit,category,is_active) VALUES(?,?,?,?,?,?,?)")
               ->execute([$key,$name,$desc,$price,$unit,$cat,$act]);
        }
        $msg = 'Saved.';
    } catch(Exception $e){ $msg = 'Error: '.$e->getMessage(); }
    header('Location: activities.php?msg='.urlencode($msg)); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try { $db->prepare("DELETE FROM activities WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch(Exception $e){}
    header('Location: activities.php'); exit;
}
$msg  = htmlspecialchars($_GET['msg'] ?? '');
$edit = null;
if (isset($_GET['edit'])) { try { $s=$db->prepare("SELECT * FROM activities WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit=$s->fetch(); } catch(Exception $e){} }
$rows = [];
try { $rows = $db->query("SELECT * FROM activities ORDER BY category, name")->fetchAll(); }
catch(Exception $e){}
?>
<?php if($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>
<div class="admin-card" style="margin-bottom:2rem">
  <div class="admin-card-header"><h3><?= $edit ? 'Edit Activity' : 'Add Activity' ?></h3></div>
  <div class="admin-card-body">
  <form method="POST" class="av-form">
    <?= \App\Middleware\CsrfMiddleware::field() ?>
    <input type="hidden" name="save" value="1">
    <input type="hidden" name="id"   value="<?= $edit['id']??0 ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
      <div><label class="admin-label">Key (unique slug)</label>
        <input name="activity_key" data-av="required slug" class="admin-input" required value="<?= htmlspecialchars($edit['activity_key']??'') ?>"></div>
      <div><label class="admin-label">Name</label>
        <input name="name" data-av="required minlen:2" class="admin-input" required value="<?= htmlspecialchars($edit['name']??'') ?>"></div>
      <div><label class="admin-label">Category</label>
        <input name="category" class="admin-input" value="<?= htmlspecialchars($edit['category']??'') ?>"></div>
      <div><label class="admin-label">Price (USD)</label>
        <input name="price" data-av="required min:0" type="number" step="0.01" class="admin-input" value="<?= $edit['price']??0 ?>"></div>
      <div><label class="admin-label">Price Unit</label>
        <select name="price_unit" class="admin-input">
          <?php foreach(['per_person'=>'Per Person','per_group'=>'Per Group','flat'=>'Flat Fee'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($edit['price_unit']??'per_person')===$v?'selected':'' ?>><?= $l ?></option>
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
    <div style="margin-top:1rem"><label class="admin-label">Description</label>
      <textarea name="description" class="admin-input" rows="2"><?= htmlspecialchars($edit['description']??'') ?></textarea></div>
    <div style="margin-top:1rem;display:flex;gap:.5rem">
      <button class="admin-btn admin-btn--primary">Save</button>
      <?php if($edit): ?><a href="activities.php" class="admin-btn admin-btn--outline">Cancel</a><?php endif; ?>
    </div>
  </form>
  </div>
</div>
<div class="admin-table-wrap">
<table class="admin-table">
<thead><tr><th>Key</th><th>Name</th><th>Category</th><th>Price</th><th>Unit</th><th>Active</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><code><?= htmlspecialchars($r['activity_key']) ?></code></td>
  <td><?= htmlspecialchars($r['name']) ?></td>
  <td><?= htmlspecialchars($r['category']??'—') ?></td>
  <td>$<?= number_format($r['price'],2) ?></td>
  <td><?= htmlspecialchars($r['price_unit']) ?></td>
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
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
