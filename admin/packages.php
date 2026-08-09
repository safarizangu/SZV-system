<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Packages';
ob_start();
?>

<?php
// Handle toggle active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $id  = (int)$_POST['toggle_id'];
    $cur = (int)$_POST['current'];
    try { $db->prepare("UPDATE packages SET is_active=? WHERE id=?")->execute([!$cur, $id]); }
    catch(Exception $e){ error_log($e->getMessage()); }
    header('Location: packages.php'); exit;
}

$packages = [];
try { $packages = $db->query("SELECT * FROM packages ORDER BY sort_order ASC, id ASC")->fetchAll(); }
catch(Exception $e){ error_log($e->getMessage()); }
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
  <p style="font-size:.8rem;color:#666"><?= count($packages) ?> package(s)</p>
  <a href="package_edit.php" class="admin-btn admin-btn--primary">+ New Package</a>
</div>
<div class="admin-table-wrap">
<table class="admin-table">
<thead><tr>
  <th>#</th><th>Name</th><th>Duration</th><th>1 Pax</th><th>2 Pax</th><th>3 Pax</th>
  <th>Level</th><th>Featured</th><th>Active</th><th>Action</th>
</tr></thead>
<tbody>
<?php foreach ($packages as $p): ?>
<tr>
  <td><?= $p['id'] ?></td>
  <td><strong><?= htmlspecialchars($p['name']) ?></strong><br>
      <small style="color:#888"><?= htmlspecialchars($p['slug']) ?></small></td>
  <td><?= $p['duration_days'] ?> days</td>
  <td>$<?= number_format($p['price_1pax'],0) ?></td>
  <td>$<?= number_format($p['price_2pax'],0) ?></td>
  <td>$<?= number_format($p['price_3pax'],0) ?></td>
  <td><?= htmlspecialchars($p['accommodation_level'] ?? '—') ?></td>
  <td><?= $p['is_featured'] ? '★' : '—' ?></td>
  <td>
    <form method="POST" style="display:inline">
      <input type="hidden" name="toggle_id" value="<?= $p['id'] ?>">
      <input type="hidden" name="current"   value="<?= $p['is_active'] ?>">
      <button class="admin-btn <?= $p['is_active'] ? 'admin-btn--success' : 'admin-btn--muted' ?>">
        <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
      </button>
    </form>
  </td>
  <td>
    <a href="package_edit.php?id=<?= $p['id'] ?>" class="admin-btn admin-btn--primary" style="font-size:.65rem">Edit</a><a href="<?= BASE_PATH ?>/packages.php#<?= $p['slug'] ?>" target="_blank" class="admin-btn admin-btn--outline" style="font-size:.65rem">View</a>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
