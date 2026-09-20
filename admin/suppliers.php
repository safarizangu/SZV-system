<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Suppliers';

$categoryLabels = [
    'accommodation'     => 'Accommodation',
    'park_operator'     => 'Park Operator',
    'activity_operator' => 'Activity Operator',
    'transport'         => 'Transport',
    'other'             => 'Other',
];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $category = array_key_exists($_POST['category'] ?? '', $categoryLabels) ? $_POST['category'] : 'accommodation';
    $contact  = trim($_POST['contact_person'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $terms    = trim($_POST['payment_terms'] ?? '');
    $active   = isset($_POST['is_active']) ? 1 : 0;
    $notes    = trim($_POST['notes'] ?? '');

    if ($name === '') {
        $msg = 'Error: Name is required.';
    } else {
        try {
            if ($id > 0) {
                $db->prepare("UPDATE suppliers SET name=?,category=?,contact_person=?,phone=?,email=?,payment_terms=?,is_active=?,notes=? WHERE id=?")
                   ->execute([$name,$category,$contact,$phone,$email,$terms,$active,$notes,$id]);
            } else {
                $db->prepare("INSERT INTO suppliers(name,category,contact_person,phone,email,payment_terms,is_active,notes) VALUES(?,?,?,?,?,?,?,?)")
                   ->execute([$name,$category,$contact,$phone,$email,$terms,$active,$notes]);
            }
            $msg = 'Saved.';
        } catch (\Exception $e) { $msg = 'Error: ' . $e->getMessage(); }
    }
    header('Location: suppliers.php?msg=' . urlencode($msg));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try { $db->prepare("DELETE FROM suppliers WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch (\Exception $e) {}
    header('Location: suppliers.php');
    exit;
}

$msg  = htmlspecialchars($_GET['msg'] ?? '');
$edit = null;
if (isset($_GET['edit'])) {
    try { $s = $db->prepare("SELECT * FROM suppliers WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }
    catch (\Exception $e) {}
}
$rows = [];
try {
    $rows = $db->query("
        SELECT s.*, (SELECT COUNT(*) FROM accommodations a WHERE a.supplier_id = s.id) AS linked_accommodations
        FROM suppliers s ORDER BY s.is_active DESC, s.name LIMIT 200
    ")->fetchAll();
} catch (\Exception $e) {}

ob_start();
?>
<?php if ($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>

<p style="color:var(--gray-500);font-size:.85rem;margin:-.5rem 0 1.25rem">
  Link a supplier to properties in <a href="accommodations.php">Accommodations</a> to track which lodge/camp belongs to which contracted partner.
</p>

<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Suppliers</h3>
    <a href="suppliers.php?new=1" class="admin-btn admin-btn--primary" style="font-size:.68rem">+ New Supplier</a>
  </div>
  <div class="admin-table-wrap">
  <table class="admin-table">
  <thead><tr><th>Name</th><th>Category</th><th>Contact</th><th>Payment Terms</th><th>Properties</th><th>Active</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (empty($rows)): ?>
  <tr><td colspan="7" class="admin-empty">No suppliers on file yet.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r['name']) ?></td>
    <td><?= $categoryLabels[$r['category']] ?? ucfirst($r['category']) ?></td>
    <td style="font-size:.78rem">
      <?= htmlspecialchars($r['contact_person'] ?: '—') ?>
      <?php if ($r['phone']): ?><br><span style="color:var(--gray-500)"><?= htmlspecialchars($r['phone']) ?></span><?php endif; ?>
    </td>
    <td style="font-size:.78rem;color:var(--gray-500)"><?= htmlspecialchars($r['payment_terms'] ?: '—') ?></td>
    <td style="text-align:center"><?= (int)$r['linked_accommodations'] ?></td>
    <td><?= $r['is_active'] ? '<span class="badge-status badge-status--green">Active</span>' : '<span class="badge-status badge-status--gray">Inactive</span>' ?></td>
    <td>
      <a href="?edit=<?= $r['id'] ?>" class="admin-btn admin-btn--outline" style="font-size:.7rem">Edit</a>
      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this supplier? Linked accommodations will just lose the link, not be deleted.')">
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
      <span class="admin-modal-title"><?= $edit ? 'Edit Supplier' : 'Add Supplier' ?></span>
      <button type="button" onclick="closeFormModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div><label class="admin-label">Name</label>
            <input name="name" class="admin-input" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>"></div>
          <div><label class="admin-label">Category</label>
            <select name="category" class="admin-input">
              <?php foreach ($categoryLabels as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($edit['category'] ?? 'accommodation') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select></div>
          <div><label class="admin-label">Contact Person</label>
            <input name="contact_person" class="admin-input" value="<?= htmlspecialchars($edit['contact_person'] ?? '') ?>"></div>
          <div><label class="admin-label">Phone</label>
            <input name="phone" class="admin-input" value="<?= htmlspecialchars($edit['phone'] ?? '') ?>"></div>
          <div><label class="admin-label">Email</label>
            <input name="email" type="email" class="admin-input" value="<?= htmlspecialchars($edit['email'] ?? '') ?>"></div>
          <div><label class="admin-label">Payment Terms</label>
            <input name="payment_terms" class="admin-input" placeholder="e.g. Net 30, 50% deposit" value="<?= htmlspecialchars($edit['payment_terms'] ?? '') ?>"></div>
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
