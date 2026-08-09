<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Leads Pipeline';
ob_start();
?>

<?php
// Update pipeline stage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stage'])) {
    try {
        $db->prepare("UPDATE leads SET pipeline_stage=? WHERE id=?")
           ->execute([$_POST['stage'], (int)$_POST['id']]);
    } catch(Exception $e){ error_log($e->getMessage()); }
    header('Location: leads.php'); exit;
}
$rows = [];
try { $rows = $db->query("SELECT * FROM leads ORDER BY created_at DESC")->fetchAll(); }
catch(Exception $e){ error_log($e->getMessage()); }
$stages = ['lead','qualified','quoted','negotiation','booked','travelled','completed','lost'];
$stageColors = ['lead'=>'#6b7280','qualified'=>'#3b82f6','quoted'=>'#f59e0b','negotiation'=>'#8b5cf6','booked'=>'#10b981','travelled'=>'#06b6d4','completed'=>'#16a34a','lost'=>'#ef4444'];
// Group by stage for kanban count
$stageCounts = array_fill_keys($stages, 0);
foreach($rows as $r) $stageCounts[$r['pipeline_stage']]++;
?>
<div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem">
  <?php foreach($stages as $s): ?>
  <div style="background:#f7f7f5;border:1px solid #e5e7eb;padding:.5rem 1rem;font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em">
    <span style="display:block;font-size:1.1rem;font-weight:700;color:<?= $stageColors[$s] ?>"><?= $stageCounts[$s] ?></span>
    <?= ucfirst($s) ?>
  </div>
  <?php endforeach; ?>
</div>
<div class="admin-table-wrap">
<table class="admin-table">
<thead><tr><th>Date</th><th>Name</th><th>Email</th><th>Country</th><th>Budget</th><th>Source</th><th>Stage</th><th>Update Stage</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td style="white-space:nowrap"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
  <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
  <td><a href="mailto:<?= htmlspecialchars($r['email']) ?>"><?= htmlspecialchars($r['email']) ?></a></td>
  <td><?= htmlspecialchars($r['country'] ?? '—') ?></td>
  <td><?= htmlspecialchars($r['budget_range'] ?? '—') ?></td>
  <td><?= htmlspecialchars($r['source'] ?? '—') ?></td>
  <td><span style="color:<?= $stageColors[$r['pipeline_stage']] ?? '#333' ?>;font-weight:700;font-size:.75rem;text-transform:uppercase"><?= $r['pipeline_stage'] ?></span></td>
  <td>
    <form method="POST" style="display:flex;gap:.25rem">
      <input type="hidden" name="update_stage" value="1">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <select name="stage" class="admin-input" style="padding:.25rem .5rem;font-size:.75rem;height:auto">
        <?php foreach($stages as $s): ?>
        <option value="<?= $s ?>" <?= $r['pipeline_stage']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="admin-btn admin-btn--primary" style="font-size:.7rem;padding:.3rem .65rem">Save</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
<?php if(empty($rows)): ?><tr><td colspan="8" class="admin-empty">No leads yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
