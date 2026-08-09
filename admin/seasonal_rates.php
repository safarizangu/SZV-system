<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Seasonal Rates';
ob_start();
?>

<?php
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id  = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $key  = trim($_POST['season_key'] ?? '');
    $mult = (float)($_POST['multiplier'] ?? 1.0);
    $sm   = (int)($_POST['start_month'] ?? 1);
    $em   = (int)($_POST['end_month'] ?? 12);
    $desc = trim($_POST['description'] ?? '');
    try {
        if ($id > 0) {
            $db->prepare("UPDATE seasonal_rates SET name=?,season_key=?,multiplier=?,start_month=?,end_month=?,description=? WHERE id=?")
               ->execute([$name,$key,$mult,$sm,$em,$desc,$id]);
        } else {
            $db->prepare("INSERT INTO seasonal_rates(name,season_key,multiplier,start_month,end_month,description) VALUES(?,?,?,?,?,?)")
               ->execute([$name,$key,$mult,$sm,$em,$desc]);
        }
        $msg = 'Saved.';
    } catch(Exception $e){ $msg = 'Error: '.$e->getMessage(); }
    header('Location: seasonal_rates.php?msg='.urlencode($msg)); exit;
}
$msg  = htmlspecialchars($_GET['msg'] ?? '');
$edit = null;
if (isset($_GET['edit'])) { try { $s=$db->prepare("SELECT * FROM seasonal_rates WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit=$s->fetch(); } catch(Exception $e){} }
$rows = [];
try { $rows = $db->query("SELECT * FROM seasonal_rates ORDER BY start_month")->fetchAll(); }
catch(Exception $e){}
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<?php if($msg): ?><div class="admin-alert admin-alert--success"><?= $msg ?></div><?php endif; ?>
<div class="admin-card" style="margin-bottom:2rem">
  <div class="admin-card-header"><h3><?= $edit ? 'Edit Season' : 'Add Season' ?></h3></div>
  <div class="admin-card-body">
  <form method="POST" class="av-form">
    <input type="hidden" name="save" value="1">
    <input type="hidden" name="id"   value="<?= $edit['id']??0 ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:1rem">
      <div><label class="admin-label">Season Name</label>
        <input name="name" data-av="required minlen:2" class="admin-input" required value="<?= htmlspecialchars($edit['name']??'') ?>"></div>
      <div><label class="admin-label">Key</label>
        <input name="season_key" data-av="required slug" class="admin-input" required value="<?= htmlspecialchars($edit['season_key']??'') ?>"></div>
      <div><label class="admin-label">Multiplier (1.0=100%)</label>
        <input name="multiplier" data-av="required min:0 max:3" type="number" step="0.01" min="0" max="3" class="admin-input" value="<?= $edit['multiplier']??1.0 ?>"></div>
      <div><label class="admin-label">Start Month</label>
        <select name="start_month" class="admin-input">
          <?php for($i=1;$i<=12;$i++): ?>
          <option value="<?= $i ?>" <?= ($edit['start_month']??1)==$i?'selected':'' ?>><?= $months[$i-1] ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div><label class="admin-label">End Month</label>
        <select name="end_month" class="admin-input">
          <?php for($i=1;$i<=12;$i++): ?>
          <option value="<?= $i ?>" <?= ($edit['end_month']??12)==$i?'selected':'' ?>><?= $months[$i-1] ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
    <div style="margin-top:1rem"><label class="admin-label">Description</label>
      <textarea name="description" class="admin-input" rows="2"><?= htmlspecialchars($edit['description']??'') ?></textarea></div>
    <div style="margin-top:1rem;display:flex;gap:.5rem">
      <button class="admin-btn admin-btn--primary">Save</button>
      <?php if($edit): ?><a href="seasonal_rates.php" class="admin-btn admin-btn--outline">Cancel</a><?php endif; ?>
    </div>
  </form>
  </div>
</div>
<div class="admin-table-wrap">
<table class="admin-table">
<thead><tr><th>Name</th><th>Key</th><th>Multiplier</th><th>% of Base</th><th>Months</th><th>Description</th><th>Edit</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
  <td><code><?= htmlspecialchars($r['season_key']) ?></code></td>
  <td><?= $r['multiplier'] ?>×</td>
  <td><?= round($r['multiplier']*100) ?>%</td>
  <td><?= $months[($r['start_month']??1)-1] ?> – <?= $months[($r['end_month']??12)-1] ?></td>
  <td style="font-size:.8rem;color:#666"><?= htmlspecialchars($r['description']??'') ?></td>
  <td><a href="?edit=<?= $r['id'] ?>" class="admin-btn admin-btn--outline" style="font-size:.7rem">Edit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
