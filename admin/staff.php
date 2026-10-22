<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'staff.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Guides & Drivers';
ob_start();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim($_POST['full_name'] ?? '');
    $role      = in_array($_POST['role'] ?? '', ['guide_driver','guide','driver'], true) ? $_POST['role'] : 'guide_driver';
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $languages = trim($_POST['languages'] ?? '');
    $license   = trim($_POST['license_number'] ?? '');
    $rate      = (float)($_POST['daily_rate'] ?? 0);
    $active    = isset($_POST['is_active']) ? 1 : 0;
    $notes     = trim($_POST['notes'] ?? '');

    if ($name === '') {
        $msg = 'Error: Name is required.';
    } else {
        try {
            if ($id > 0) {
                $db->prepare("UPDATE staff SET full_name=?,role=?,phone=?,email=?,languages=?,license_number=?,daily_rate=?,is_active=?,notes=? WHERE id=?")
                   ->execute([$name,$role,$phone,$email,$languages,$license,$rate,$active,$notes,$id]);
            } else {
                $db->prepare("INSERT INTO staff(full_name,role,phone,email,languages,license_number,daily_rate,is_active,notes) VALUES(?,?,?,?,?,?,?,?,?)")
                   ->execute([$name,$role,$phone,$email,$languages,$license,$rate,$active,$notes]);
            }
            $msg = 'Saved.';
        } catch (\Exception $e) { $msg = 'Error: ' . $e->getMessage(); }
    }
    header('Location: staff.php?msg=' . urlencode($msg));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try { $db->prepare("DELETE FROM staff WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch (\Exception $e) {}
    header('Location: staff.php');
    exit;
}

$msg  = htmlspecialchars($_GET['msg'] ?? '');
$edit = null;
if (isset($_GET['edit'])) {
    try { $s = $db->prepare("SELECT * FROM staff WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }
    catch (\Exception $e) {}
}
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page-1)*$perPage;
$rows = []; $totalRows = 0; $totalPages = 0;
try {
    $totalRows  = (int)$db->query("SELECT COUNT(*) FROM staff")->fetchColumn();
    $totalPages = (int)ceil($totalRows/$perPage);
    $rows = $db->query("SELECT * FROM staff ORDER BY is_active DESC, full_name LIMIT $perPage OFFSET $offset")->fetchAll();
}
catch (\Exception $e) {}

$roleLabels = ['guide_driver' => 'Guide-Driver', 'guide' => 'Guide Only', 'driver' => 'Driver Only'];
?>
<?php if ($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>

<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Guides &amp; Drivers</h3>
    <a href="staff.php?new=1" class="admin-btn admin-btn--primary" style="font-size:.68rem">+ New</a>
  </div>
  <div class="admin-table-wrap">
  <table class="admin-table">
  <thead><tr><th>Name</th><th>Role</th><th>Phone</th><th>Languages</th><th>Daily Rate</th><th>Active</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (empty($rows)): ?>
  <tr><td colspan="7" class="admin-empty">No guides/drivers on file yet.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r['full_name']) ?></td>
    <td><?= $roleLabels[$r['role']] ?? ucfirst($r['role']) ?></td>
    <td><?= htmlspecialchars($r['phone'] ?: '—') ?></td>
    <td><?= htmlspecialchars($r['languages'] ?: '—') ?></td>
    <td>$<?= number_format($r['daily_rate'],0) ?></td>
    <td><?= $r['is_active'] ? '<span class="badge-status badge-status--green">Active</span>' : '<span class="badge-status badge-status--gray">Inactive</span>' ?></td>
    <td>
      <div class="admin-row-actions">
        <button type="button" class="admin-icon-btn admin-row-actions-toggle" aria-label="Actions" aria-haspopup="true" aria-expanded="false"><?= \App\Helpers\AdminIcon::render('dots-vertical', 16) ?></button>
        <div class="admin-row-actions-menu">
          <a href="?edit=<?= $r['id'] ?>" class="admin-row-actions-item"><?= \App\Helpers\AdminIcon::render('edit', 14) ?> Edit</a>
          <form method="POST" onsubmit="return confirmSubmitGuard(event, this, 'Delete this person? Any current booking assignment will be cleared, not the booking itself.', {danger:true})">
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
      <span class="admin-modal-title"><?= $edit ? 'Edit Person' : 'Add Guide/Driver' ?></span>
      <button type="button" onclick="closeFormModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div><label class="admin-label">Full Name</label>
            <input name="full_name" class="admin-input" required value="<?= htmlspecialchars($edit['full_name'] ?? '') ?>"></div>
          <div><label class="admin-label">Role</label>
            <select name="role" class="admin-input">
              <?php foreach ($roleLabels as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($edit['role'] ?? 'guide_driver') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select></div>
          <div><label class="admin-label">Phone</label>
            <input name="phone" class="admin-input" value="<?= htmlspecialchars($edit['phone'] ?? '') ?>"></div>
          <div><label class="admin-label">Email</label>
            <input name="email" type="email" class="admin-input" value="<?= htmlspecialchars($edit['email'] ?? '') ?>"></div>
          <div><label class="admin-label">Languages</label>
            <input name="languages" class="admin-input" placeholder="English, Swahili, French" value="<?= htmlspecialchars($edit['languages'] ?? '') ?>"></div>
          <div><label class="admin-label">License Number</label>
            <input name="license_number" class="admin-input" value="<?= htmlspecialchars($edit['license_number'] ?? '') ?>"></div>
          <div><label class="admin-label">Daily Rate (USD)</label>
            <input name="daily_rate" type="number" step="0.01" min="0" class="admin-input" value="<?= $edit['daily_rate'] ?? 0 ?>"></div>
          <div style="display:flex;align-items:flex-end">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;padding-bottom:.5rem">
              <input type="checkbox" name="is_active" value="1" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>>
              <span class="admin-label" style="margin:0">Active</span>
            </label>
          </div>
        </div>
        <div style="margin-top:1rem"><label class="admin-label">Notes</label>
          <textarea name="notes" class="admin-input" rows="2"><?= htmlspecialchars($edit['notes'] ?? '') ?></textarea></div>
        <div style="margin-top:1rem;display:flex;gap:.5rem">
          <button class="admin-btn admin-btn--primary">Save</button>
          <button type="button" onclick="closeFormModal()" class="admin-btn admin-btn--outline">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

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
