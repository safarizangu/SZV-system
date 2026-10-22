<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'team_members.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Team Members';
ob_start();
?>

<?php
$msg = '';
// Add / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $role   = trim($_POST['role'] ?? '');
    $bio    = trim($_POST['bio']  ?? '');
    $email  = trim($_POST['email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $li     = trim($_POST['linkedin_url'] ?? '');
    $photo  = trim($_POST['photo_url'] ?? '');
    $sort   = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    try {
        if ($id > 0) {
            $db->prepare("UPDATE team_members SET name=?,role=?,bio=?,email=?,phone=?,linkedin_url=?,photo_url=?,sort_order=?,is_active=? WHERE id=?")
               ->execute([$name,$role,$bio,$email,$phone,$li,$photo,$sort,$active,$id]);
        } else {
            $db->prepare("INSERT INTO team_members(name,role,bio,email,phone,linkedin_url,photo_url,sort_order,is_active) VALUES(?,?,?,?,?,?,?,?,?)")
               ->execute([$name,$role,$bio,$email,$phone,$li,$photo,$sort,$active]);
        }
        $msg = 'Saved successfully.';
    } catch(Exception $e){ $msg = 'Error: '.$e->getMessage(); }
    header('Location: team_members.php?msg='.urlencode($msg)); exit;
}
// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try { $db->prepare("DELETE FROM team_members WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch(Exception $e){ error_log($e->getMessage()); }
    header('Location: team_members.php'); exit;
}
$msg = htmlspecialchars($_GET['msg'] ?? '');
$edit = null;
if (isset($_GET['edit'])) {
    try { $edit = $db->prepare("SELECT * FROM team_members WHERE id=?")->execute([(int)$_GET['edit']]) ? $db->query("SELECT * FROM team_members WHERE id=".(int)$_GET['edit'])->fetch() : null; }
    catch(Exception $e){}
    try { $stmt=$db->prepare("SELECT * FROM team_members WHERE id=?"); $stmt->execute([(int)$_GET['edit']]); $edit=$stmt->fetch(); } catch(Exception $e){}
}
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page-1)*$perPage;
$members = []; $totalRows = 0; $totalPages = 0;
try {
    $totalRows  = (int)$db->query("SELECT COUNT(*) FROM team_members")->fetchColumn();
    $totalPages = (int)ceil($totalRows/$perPage);
    $members = $db->query("SELECT * FROM team_members ORDER BY sort_order ASC, id ASC LIMIT $perPage OFFSET $offset")->fetchAll();
}
catch(Exception $e){ error_log($e->getMessage()); }
?>
<?php if($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>

<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Team Members</h3>
    <a href="team_members.php?new=1" class="admin-btn admin-btn--primary" style="font-size:.68rem">+ New Member</a>
  </div>

<!-- List -->
<div class="admin-table-wrap">
<table class="admin-table">
<thead><tr><th>Order</th><th>Name</th><th>Role</th><th>Email</th><th>Active</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($members as $m): ?>
<tr>
  <td><?= $m['sort_order'] ?></td>
  <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
  <td><?= htmlspecialchars($m['role'] ?? '—') ?></td>
  <td><?= htmlspecialchars($m['email'] ?? '—') ?></td>
  <td><?= $m['is_active'] ? '<span class="badge-status badge-status--green">Yes</span>' : '<span class="badge-status badge-status--gray">No</span>' ?></td>
  <td>
    <div class="admin-row-actions">
      <button type="button" class="admin-icon-btn admin-row-actions-toggle" aria-label="Actions" aria-haspopup="true" aria-expanded="false"><?= \App\Helpers\AdminIcon::render('dots-vertical', 16) ?></button>
      <div class="admin-row-actions-menu">
        <a href="?edit=<?= $m['id'] ?>" class="admin-row-actions-item"><?= \App\Helpers\AdminIcon::render('edit', 14) ?> Edit</a>
        <form method="POST" onsubmit="return confirmSubmitGuard(event, this, 'Delete <?= htmlspecialchars(addslashes($m['name'])) ?>?', {danger:true})">
          <?= \App\Middleware\CsrfMiddleware::field() ?>
          <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
          <button type="submit" class="admin-row-actions-item admin-row-actions-item--danger"><?= \App\Helpers\AdminIcon::render('trash', 14) ?> Delete</button>
        </form>
      </div>
    </div>
  </td>
</tr>
<?php endforeach; ?>
<?php if(empty($members)): ?><tr><td colspan="6" class="admin-empty">No team members yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
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
      <span class="admin-modal-title"><?= $edit ? 'Edit Member' : 'Add Team Member' ?></span>
      <button type="button" onclick="closeFormModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <form method="POST" class="av-form">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div><label class="admin-label">Name *</label>
            <input name="name" data-av="required minlen:2" class="admin-input" required value="<?= htmlspecialchars($edit['name']??'') ?>"></div>
          <div><label class="admin-label">Role / Title</label>
            <input name="role" class="admin-input" value="<?= htmlspecialchars($edit['role']??'') ?>"></div>
          <div><label class="admin-label">Email</label>
            <input name="email" data-av="email" type="email" class="admin-input" value="<?= htmlspecialchars($edit['email']??'') ?>"></div>
          <div><label class="admin-label">Phone</label>
            <input name="phone" class="admin-input" value="<?= htmlspecialchars($edit['phone']??'') ?>"></div>
          <div><label class="admin-label">LinkedIn URL</label>
            <input name="linkedin_url" data-av="url" class="admin-input" value="<?= htmlspecialchars($edit['linkedin_url']??'') ?>"></div>
          <div><label class="admin-label">Sort Order</label>
            <input name="sort_order" data-av="integer min:0" type="number" class="admin-input" value="<?= $edit['sort_order']??0 ?>"></div>
        </div>
        <div style="margin-top:1rem">
          <label class="admin-label">Photo</label>
          <input type="hidden" name="photo_url" id="tm-photo-url" value="<?= htmlspecialchars($edit['photo_url']??'') ?>">
          <div style="display:flex;align-items:center;gap:.85rem">
            <img id="tm-photo-preview" src="<?= htmlspecialchars(($edit['photo_url']??'') ? BASE_PATH.$edit['photo_url'] : '') ?>"
                 style="width:64px;height:64px;object-fit:cover;border:1.5px solid var(--gray-200);<?= ($edit['photo_url']??'') ? '' : 'display:none' ?>">
            <input type="file" id="tm-photo-file" accept="image/jpeg,image/png,image/webp" class="admin-input" style="max-width:280px">
            <span id="tm-photo-status" style="font-size:.75rem;color:var(--gray-500)"></span>
          </div>
        </div>
        <div style="margin-top:1rem"><label class="admin-label">Bio</label>
          <textarea name="bio" class="admin-input" rows="3"><?= htmlspecialchars($edit['bio']??'') ?></textarea></div>
        <div style="margin-top:.75rem">
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" <?= ($edit['is_active']??1) ? 'checked' : '' ?>>
            <span class="admin-label" style="margin:0">Active (shows on About page)</span>
          </label>
        </div>
        <div style="margin-top:1rem;display:flex;gap:.5rem">
          <button class="admin-btn admin-btn--primary">Save Member</button>
          <button type="button" onclick="closeFormModal()" class="admin-btn admin-btn--outline">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('tm-photo-file').addEventListener('change', async function(e) {
  var file = e.target.files[0];
  if (!file) return;
  var status  = document.getElementById('tm-photo-status');
  var preview = document.getElementById('tm-photo-preview');
  status.textContent = 'Uploading…'; status.style.color = 'var(--gray-500)';

  var fd = new FormData();
  fd.append('image', file);
  fd.append('type', 'team');
  fd.append('_csrf', window.CSRF_TOKEN || '');

  try {
    var r = await fetch('<?= BASE_PATH ?>/api/upload_team_image.php', {method:'POST', body:fd, credentials:'same-origin'});
    var d = await r.json();
    if (d.success) {
      document.getElementById('tm-photo-url').value = d.url;
      preview.src = '<?= BASE_PATH ?>' + d.url;
      preview.style.display = '';
      status.textContent = 'Uploaded ✓'; status.style.color = 'var(--green)';
    } else {
      status.textContent = d.message || 'Upload failed'; status.style.color = 'var(--red)';
    }
  } catch (err) {
    status.textContent = 'Network error'; status.style.color = 'var(--red)';
  }
});

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
