<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Team Members';
ob_start();
?>

<?php
$msg = '';
// Add / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $role   = trim($_POST['role'] ?? '');
    $bio    = trim($_POST['bio']  ?? '');
    $email  = trim($_POST['email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $li     = trim($_POST['linkedin_url'] ?? '');
    $sort   = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    try {
        if ($id > 0) {
            $db->prepare("UPDATE team_members SET name=?,role=?,bio=?,email=?,phone=?,linkedin_url=?,sort_order=?,is_active=? WHERE id=?")
               ->execute([$name,$role,$bio,$email,$phone,$li,$sort,$active,$id]);
        } else {
            $db->prepare("INSERT INTO team_members(name,role,bio,email,phone,linkedin_url,sort_order,is_active) VALUES(?,?,?,?,?,?,?,?)")
               ->execute([$name,$role,$bio,$email,$phone,$li,$sort,$active]);
        }
        $msg = 'Saved successfully.';
    } catch(Exception $e){ $msg = 'Error: '.$e->getMessage(); }
    header('Location: team_members.php?msg='.urlencode($msg)); exit;
}
// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
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
$members = [];
try { $members = $db->query("SELECT * FROM team_members ORDER BY sort_order ASC, id ASC")->fetchAll(); }
catch(Exception $e){ error_log($e->getMessage()); }
?>
<?php if($msg): ?><div class="admin-alert admin-alert--success"><?= $msg ?></div><?php endif; ?>

<!-- Add/Edit Form -->
<div class="admin-card" style="margin-bottom:2rem">
  <div class="admin-card-header"><h3><?= $edit ? 'Edit Member' : 'Add Team Member' ?></h3></div>
  <div class="admin-card-body">
  <form method="POST" class="av-form">
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
      <?php if($edit): ?><a href="team_members.php" class="admin-btn admin-btn--outline">Cancel</a><?php endif; ?>
    </div>
  </form>
  </div>
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
  <td><?= $m['is_active'] ? '<span style="color:#16a34a;font-weight:700">Yes</span>' : '<span style="color:#9ca3af">No</span>' ?></td>
  <td>
    <a href="?edit=<?= $m['id'] ?>" class="admin-btn admin-btn--outline" style="font-size:.7rem">Edit</a>
    <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?= htmlspecialchars($m['name']) ?>?')">
      <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
      <button class="admin-btn admin-btn--danger" style="font-size:.7rem">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
<?php if(empty($members)): ?><tr><td colspan="6" class="admin-empty">No team members yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
