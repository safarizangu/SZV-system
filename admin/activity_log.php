<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Activity Log';

// Only admin/superadmin can see who-did-what across the whole team.
if (!AuthMiddleware::isAdminRole()) {
    header('Location: index.php'); exit;
}

$entityFilter = trim($_GET['entity'] ?? '');
$actorFilter  = (int)($_GET['actor'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 30;
$offset       = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($entityFilter) { $where[] = 'entity_type=?'; $params[] = $entityFilter; }
if ($actorFilter)  { $where[] = 'actor_id=?'; $params[] = $actorFilter; }
$whereSQL = $where ? 'WHERE '.implode(' AND ', $where) : '';

$rows = []; $totalRows = 0; $totalPages = 0;
try {
    $total = $db->prepare("SELECT COUNT(*) FROM activity_log $whereSQL");
    $total->execute($params);
    $totalRows  = (int)$total->fetchColumn();
    $totalPages = (int)ceil($totalRows / $perPage);
    $stmt = $db->prepare("SELECT * FROM activity_log $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch(\Exception $e) {}

$entityTypes = [];
try { $entityTypes = $db->query("SELECT DISTINCT entity_type FROM activity_log ORDER BY entity_type")->fetchAll(\PDO::FETCH_COLUMN); }
catch(\Exception $e) {}

$actors = [];
try { $actors = $db->query("SELECT id, full_name, username FROM admin_users ORDER BY full_name")->fetchAll(); }
catch(\Exception $e) {}

ob_start();
?>

<!-- Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
    <select name="entity" onchange="this.form.submit()" class="admin-input" style="width:auto">
      <option value="">All entity types</option>
      <?php foreach ($entityTypes as $et): ?>
      <option value="<?= htmlspecialchars($et) ?>" <?= $entityFilter===$et?'selected':'' ?>><?= ucfirst(htmlspecialchars($et)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="actor" onchange="this.form.submit()" class="admin-input" style="width:auto">
      <option value="">All team members</option>
      <?php foreach ($actors as $a): ?>
      <option value="<?= $a['id'] ?>" <?= $actorFilter===(int)$a['id']?'selected':'' ?>><?= htmlspecialchars($a['full_name'] ?: $a['username']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($entityFilter || $actorFilter): ?>
    <a href="activity_log.php" class="admin-btn admin-btn--outline">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Table -->
<div class="admin-table-wrap">
<table class="admin-table">
  <thead>
    <tr>
      <th>When</th><th>Who</th><th>Action</th><th>Entity</th><th>Details</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r):
      $details = $r['details'] ? json_decode($r['details'], true) : [];
    ?>
    <tr>
      <td style="white-space:nowrap;font-size:.75rem;color:var(--gray-500)"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
      <td style="font-size:.82rem"><?= htmlspecialchars($r['actor_name']) ?></td>
      <td><span class="badge-status badge-status--gray"><?= htmlspecialchars($r['action']) ?></span></td>
      <td style="font-size:.82rem"><?= htmlspecialchars($r['entity_type']) ?><?= $r['entity_id'] ? ' #'.(int)$r['entity_id'] : '' ?></td>
      <td style="font-size:.72rem;color:var(--gray-500)">
        <?php if ($details): ?>
          <?php foreach ($details as $k=>$v): ?>
            <span><?= htmlspecialchars($k) ?>: <?= htmlspecialchars(is_scalar($v)?(string)$v:json_encode($v)) ?></span><br>
          <?php endforeach; ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
    <tr><td colspan="5" class="admin-empty">No activity recorded yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination" style="border-top:none">
  <span style="font-size:.75rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?></span>
  <div class="page-btns">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&entity=<?= urlencode($entityFilter) ?>&actor=<?= $actorFilter ?>"
       class="page-btn<?= $i===$page?' active':'' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
