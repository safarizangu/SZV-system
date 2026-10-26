<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'suppliers.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Suppliers';

$categoryLabels = [
    'accommodation'     => 'Accommodation',
    'park_operator'     => 'Park Operator',
    'activity_operator' => 'Activity Operator',
    'transport'         => 'Transport',
    'other'             => 'Other',
];

// Contracts live outside the public web root — already blocked from
// direct access by .htaccess (RewriteRule ^storage/ - [F,L]), served
// only through api/supplier_contract.php's own admin-auth check.
$contractDir = __DIR__ . '/../storage/uploads/contracts';

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
        // Existing contract, if any — needed either way: to keep it when
        // no new file is uploaded, or to delete the old one when replaced.
        $existingContract = null;
        if ($id > 0) {
            $ec = $db->prepare("SELECT contract_filename FROM suppliers WHERE id=?");
            $ec->execute([$id]);
            $existingContract = $ec->fetchColumn() ?: null;
        }

        $contractFilename     = $existingContract;
        $contractOriginalName = null;
        $contractUploadedAt   = null;
        $removeContract       = isset($_POST['remove_contract']);

        $file = $_FILES['contract'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] > SUPPLIER_CONTRACT_MAX_MB * 1024 * 1024) {
                $msg = 'Error: Contract PDF must be under ' . SUPPLIER_CONTRACT_MAX_MB . 'MB.';
            } else {
                // Real content sniffing, not the filename/extension —
                // same standard as api/upload_team_image.php.
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($file['tmp_name']);
                if ($mime !== 'application/pdf') {
                    $msg = 'Error: Contract must be a PDF file.';
                } else {
                    if (!is_dir($contractDir)) @mkdir($contractDir, 0775, true);
                    $newFilename = uniqid('contract_', true) . '.pdf';
                    if (move_uploaded_file($file['tmp_name'], $contractDir . '/' . $newFilename)) {
                        // Replacing an existing file — remove the old one
                        // now that the new one is safely saved.
                        if ($existingContract) {
                            @unlink($contractDir . '/' . $existingContract);
                        }
                        $contractFilename     = $newFilename;
                        $contractOriginalName = basename($file['name']);
                        $contractUploadedAt   = date('Y-m-d H:i:s');
                    } else {
                        $msg = 'Error: Could not save the uploaded contract.';
                    }
                }
            }
        } elseif ($removeContract && $existingContract) {
            @unlink($contractDir . '/' . $existingContract);
            $contractFilename     = null;
            $contractOriginalName = null;
            $contractUploadedAt   = null;
        }

        if ($msg === '') {
            try {
                if ($id > 0) {
                    if ($contractFilename !== $existingContract || $removeContract) {
                        // Contract changed (uploaded, replaced, or removed) —
                        // also write original_name/uploaded_at so they never
                        // drift out of sync with contract_filename.
                        $db->prepare("UPDATE suppliers SET name=?,category=?,contact_person=?,phone=?,email=?,payment_terms=?,is_active=?,notes=?,
                                      contract_filename=?,contract_original_name=?,contract_uploaded_at=? WHERE id=?")
                           ->execute([$name,$category,$contact,$phone,$email,$terms,$active,$notes,
                                      $contractFilename,$contractOriginalName,$contractUploadedAt,$id]);
                    } else {
                        $db->prepare("UPDATE suppliers SET name=?,category=?,contact_person=?,phone=?,email=?,payment_terms=?,is_active=?,notes=? WHERE id=?")
                           ->execute([$name,$category,$contact,$phone,$email,$terms,$active,$notes,$id]);
                    }
                } else {
                    $db->prepare("INSERT INTO suppliers(name,category,contact_person,phone,email,payment_terms,is_active,notes,
                                  contract_filename,contract_original_name,contract_uploaded_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
                       ->execute([$name,$category,$contact,$phone,$email,$terms,$active,$notes,
                                  $contractFilename,$contractOriginalName,$contractUploadedAt]);
                }
                $msg = 'Saved.';
            } catch (\Exception $e) { $msg = 'Error: ' . $e->getMessage(); }
        }
    }
    header('Location: suppliers.php?msg=' . urlencode($msg));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        $delId = (int)$_POST['delete_id'];
        $cf = $db->prepare("SELECT contract_filename FROM suppliers WHERE id=?");
        $cf->execute([$delId]);
        $contractToDelete = $cf->fetchColumn();
        $db->prepare("DELETE FROM suppliers WHERE id=?")->execute([$delId]);
        if ($contractToDelete) @unlink($contractDir . '/' . $contractToDelete);
    }
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
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page-1)*$perPage;
$rows = []; $totalRows = 0; $totalPages = 0;
try {
    $totalRows  = (int)$db->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    $totalPages = (int)ceil($totalRows/$perPage);
    $rows = $db->query("
        SELECT s.*, (SELECT COUNT(*) FROM accommodations a WHERE a.supplier_id = s.id) AS linked_accommodations
        FROM suppliers s ORDER BY s.is_active DESC, s.name LIMIT $perPage OFFSET $offset
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
  <thead><tr><th>Name</th><th>Category</th><th>Contact</th><th>Payment Terms</th><th>Contract</th><th>Properties</th><th>Active</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (empty($rows)): ?>
  <tr><td colspan="8" class="admin-empty">No suppliers on file yet.</td></tr>
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
    <td style="font-size:.72rem">
      <?php if (!empty($r['contract_filename'])): ?>
      <a href="../api/supplier_contract.php?id=<?= $r['id'] ?>" target="_blank" class="admin-btn admin-btn--tint-blue" style="font-size:.65rem;padding:.3rem .55rem"><?= \App\Helpers\AdminIcon::render('invoices', 12) ?> PDF</a>
      <?php if ($r['contract_uploaded_at']): ?><div style="color:var(--gray-500);margin-top:.25rem"><?= date('d M Y', strtotime($r['contract_uploaded_at'])) ?></div><?php endif; ?>
      <?php else: ?>
      <span style="color:var(--gray-500)">—</span>
      <?php endif; ?>
    </td>
    <td style="text-align:center"><?= (int)$r['linked_accommodations'] ?></td>
    <td><?= $r['is_active'] ? '<span class="badge-status badge-status--green">Active</span>' : '<span class="badge-status badge-status--gray">Inactive</span>' ?></td>
    <td>
      <div class="admin-row-actions">
        <button type="button" class="admin-icon-btn admin-row-actions-toggle" aria-label="Actions" aria-haspopup="true" aria-expanded="false"><?= \App\Helpers\AdminIcon::render('dots-vertical', 16) ?></button>
        <div class="admin-row-actions-menu">
          <a href="?edit=<?= $r['id'] ?>" class="admin-row-actions-item"><?= \App\Helpers\AdminIcon::render('edit', 14) ?> Edit</a>
          <form method="POST" onsubmit="return confirmSubmitGuard(event, this, 'Delete this supplier? Linked accommodations will just lose the link, not be deleted. Any uploaded contract PDF is deleted too.', {danger:true})">
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
      <span class="admin-modal-title"><?= $edit ? 'Edit Supplier' : 'Add Supplier' ?></span>
      <button type="button" onclick="closeFormModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <form method="POST" enctype="multipart/form-data">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div><label class="admin-label" for="name">Name</label>
            <input id="name" name="name" class="admin-input" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>"></div>
          <div><label class="admin-label" for="category">Category</label>
            <select id="category" name="category" class="admin-input">
              <?php foreach ($categoryLabels as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($edit['category'] ?? 'accommodation') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select></div>
          <div><label class="admin-label" for="contact_person">Contact Person</label>
            <input id="contact_person" name="contact_person" class="admin-input" value="<?= htmlspecialchars($edit['contact_person'] ?? '') ?>"></div>
          <div><label class="admin-label" for="phone">Phone</label>
            <input id="phone" name="phone" class="admin-input" value="<?= htmlspecialchars($edit['phone'] ?? '') ?>"></div>
          <div><label class="admin-label" for="email">Email</label>
            <input id="email" name="email" type="email" class="admin-input" value="<?= htmlspecialchars($edit['email'] ?? '') ?>"></div>
          <div><label class="admin-label" for="payment_terms">Payment Terms</label>
            <input id="payment_terms" name="payment_terms" class="admin-input" placeholder="e.g. Net 30, 50% deposit" value="<?= htmlspecialchars($edit['payment_terms'] ?? '') ?>"></div>
          <div>
            <label class="admin-label" for="contract">Contract <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(PDF, max <?= SUPPLIER_CONTRACT_MAX_MB ?>MB)</span></label>
            <input id="contract" type="file" name="contract" accept="application/pdf" class="admin-input">
            <?php if (!empty($edit['contract_filename'])): ?>
            <div style="display:flex;align-items:center;gap:.6rem;margin-top:.5rem;font-size:.75rem">
              <a href="../api/supplier_contract.php?id=<?= $edit['id'] ?>" target="_blank" style="color:var(--gold)">
                <?= htmlspecialchars($edit['contract_original_name'] ?: 'Current contract') ?>
              </a>
              <label style="display:flex;align-items:center;gap:.35rem;cursor:pointer;color:var(--red)">
                <input type="checkbox" name="remove_contract" value="1"> Remove
              </label>
            </div>
            <?php endif; ?>
          </div>
          <div style="display:flex;align-items:flex-end">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;padding-bottom:.5rem">
              <input type="checkbox" name="is_active" value="1" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>>
              <span class="admin-label" style="margin:0">Active</span>
            </label>
          </div>
        </div>
        <div style="margin-top:1rem"><label class="admin-label" for="notes">Notes</label>
          <textarea id="notes" name="notes" class="admin-input" rows="2"><?= htmlspecialchars($edit['notes'] ?? '') ?></textarea></div>
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
