<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Settings page — admin/superadmin only, regardless of what's in the
// sidebar (staff and agent never see the nav link, but that alone isn't
// access control). Managing accounts/roles is exactly the kind of action
// that shouldn't be reachable by anyone but admin/superadmin.
if (!AuthMiddleware::isAdminRole()) { header('Location: index.php'); exit; }
$db = \App\Models\Database::getInstance();
$pageTitle = 'User Management';

$msg   = htmlspecialchars($_GET['msg'] ?? '');
$error = '';

// Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $id       = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'admin';
    $newPw    = trim($_POST['new_password'] ?? '');
    $active   = isset($_POST['is_active']) ? 1 : 0;
    $canChangeStatus  = isset($_POST['can_change_status'])  ? 1 : 0;
    $canDeleteRecords = isset($_POST['can_delete_records']) ? 1 : 0;

    // SEC-3: a plain 'admin' account had no privilege-boundary check at
    // all here — it could set role=superadmin on itself or any other
    // account, bypassing every per-account can_change_status/
    // can_delete_records restriction (AuthMiddleware::canChangeStatus()/
    // canDeleteRecords() both special-case superadmin to always pass).
    // Only an acting superadmin may grant the superadmin role, or modify
    // an account that already holds it.
    $actingIsSuperadmin = ($_SESSION['admin_role'] ?? '') === 'superadmin';
    $targetWasSuperadmin = false;
    if ($id > 0) {
        $ts = $db->prepare("SELECT role FROM admin_users WHERE id=?");
        $ts->execute([$id]);
        $targetWasSuperadmin = ($ts->fetchColumn() === 'superadmin');
    }
    if (!$actingIsSuperadmin && ($role === 'superadmin' || $targetWasSuperadmin)) {
        $error = 'Only a superadmin can grant the superadmin role or modify a superadmin account.';
    } elseif (!$username) {
        $error = 'Username is required.';
    } elseif ($newPw) {
        if (strlen($newPw) < 10) {
            $error = 'Password must be at least 10 characters.';
        } elseif (!preg_match('/[A-Z]/', $newPw)) {
            $error = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $newPw)) {
            $error = 'Password must contain at least one number.';
        }
    } if ($error) {
    } else {
        try {
            if ($id > 0) {
                // Update — only change password if new one provided
                if ($newPw) {
                    $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost'=>12]);
                    $db->prepare("UPDATE admin_users SET username=?,full_name=?,email=?,role=?,is_active=?,can_change_status=?,can_delete_records=?,password_hash=? WHERE id=?")
                       ->execute([$username,$fullName,$email,$role,$active,$canChangeStatus,$canDeleteRecords,$hash,$id]);
                } else {
                    $db->prepare("UPDATE admin_users SET username=?,full_name=?,email=?,role=?,is_active=?,can_change_status=?,can_delete_records=? WHERE id=?")
                       ->execute([$username,$fullName,$email,$role,$active,$canChangeStatus,$canDeleteRecords,$id]);
                }
                header('Location: users.php?msg='.urlencode('User updated.')); exit;
            } else {
                if (!$newPw) { $error = 'Password is required for new users.'; }
                else {
                    $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost'=>12]);
                    $db->prepare("INSERT INTO admin_users(username,full_name,email,role,is_active,can_change_status,can_delete_records,password_hash,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())")
                       ->execute([$username,$fullName,$email,$role,$active,$canChangeStatus,$canDeleteRecords,$hash]);
                    header('Location: users.php?msg='.urlencode('User created.')); exit;
                }
            }
        } catch(Exception $e) { $error = 'Error: '.$e->getMessage(); }
    }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $delId = (int)$_POST['delete_id'];
    $myId  = $_SESSION['admin_user_id'] ?? 0;
    if ($delId === $myId) { $error = 'You cannot delete your own account.'; }
    else {
        // SEC-3: same privilege boundary as the save handler above — a
        // plain admin could otherwise delete a superadmin account outright.
        $ds = $db->prepare("SELECT role FROM admin_users WHERE id=?");
        $ds->execute([$delId]);
        $targetRole = $ds->fetchColumn();
        if ($targetRole === 'superadmin' && ($_SESSION['admin_role'] ?? '') !== 'superadmin') {
            $error = 'Only a superadmin can delete a superadmin account.';
        }
    }
    if (!$error && $delId !== $myId) {
        try { $db->prepare("DELETE FROM admin_users WHERE id=?")->execute([$delId]); }
        catch(Exception $e){ $error = $e->getMessage(); }
        if (!$error) { header('Location: users.php?msg='.urlencode('User deleted.')); exit; }
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editUser = null;
if ($editId) {
    try { $s=$db->prepare("SELECT * FROM admin_users WHERE id=?"); $s->execute([$editId]); $editUser=$s->fetch(); }
    catch(Exception $e){}
}

$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page-1)*$perPage;
$users = []; $totalRows = 0; $totalPages = 0;
try {
    $totalRows  = (int)$db->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    $totalPages = (int)ceil($totalRows/$perPage);
    $users = $db->query("SELECT * FROM admin_users ORDER BY id ASC LIMIT $perPage OFFSET $offset")->fetchAll();
}
catch(Exception $e){ $error = $e->getMessage(); }

ob_start();
?>
<?php if($msg): ?><div class="admin-alert admin-alert--success"><?= $msg ?></div><?php endif; ?>
<?php if($error): ?><div class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header"><h3>Admin Users</h3>
      <a href="users.php?new=1" class="admin-btn admin-btn--primary" style="font-size:.68rem">+ New User</a>
    </div>
    <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr>
        <th>User</th><th>Email</th><th>Role</th><th>Permissions</th><th>Last Login</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach($users as $u): $isMe = ($u['id'] == ($_SESSION['admin_user_id']??0)); ?>
      <tr style="<?= $isMe?'background:var(--green-light)':'' ?>">
        <td>
          <div style="font-weight:700;font-size:.88rem">
            <?= htmlspecialchars($u['full_name']?:$u['username']) ?>
            <?php if($isMe): ?><span class="badge-status badge-status--dark" style="margin-left:.35rem">YOU</span><?php endif; ?>
          </div>
          <div style="font-size:.72rem;color:var(--gray-500)">@<?= htmlspecialchars($u['username']) ?></div>
        </td>
        <td style="font-size:.8rem"><?= htmlspecialchars($u['email']??'—') ?></td>
        <td>
          <span class="badge-status badge-status--<?= $u['role']==='superadmin'?'dark':'gray' ?>">
            <?= htmlspecialchars($u['role']??'admin') ?>
          </span>
        </td>
        <td style="font-size:.68rem;color:var(--gray-500);white-space:nowrap">
          <?php if ($u['role']==='superadmin'): ?>
          <span style="color:var(--black);font-weight:600">Full access</span>
          <?php else: ?>
          <?= ($u['can_change_status']??1) ? '✓ Status' : '<span style="color:var(--red)">✗ Status</span>' ?><br>
          <?= ($u['can_delete_records']??1) ? '✓ Delete' : '<span style="color:var(--red)">✗ Delete</span>' ?>
          <?php endif; ?>
        </td>
        <td style="font-size:.75rem;color:var(--gray-500)">
          <?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Never' ?>
        </td>
        <td>
          <?php if($u['is_active']??1): ?>
          <span class="status-dot status-dot--green"></span><span style="font-size:.72rem;font-weight:600;color:var(--green)">Active</span>
          <?php else: ?>
          <span class="status-dot status-dot--gray"></span><span style="font-size:.72rem;color:var(--gray-500)">Inactive</span>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <div class="admin-row-actions">
            <button type="button" class="admin-icon-btn admin-row-actions-toggle" aria-label="Actions" aria-haspopup="true" aria-expanded="false"><?= \App\Helpers\AdminIcon::render('dots-vertical', 16) ?></button>
            <div class="admin-row-actions-menu">
              <a href="?edit=<?= $u['id'] ?>" class="admin-row-actions-item"><?= \App\Helpers\AdminIcon::render('edit', 14) ?> Edit</a>
              <?php if(!$isMe): ?>
              <form method="POST" onsubmit="return confirmSubmitGuard(event, this, 'Delete user <?= htmlspecialchars(addslashes($u['username'])) ?>? This cannot be undone.', {danger:true})">
                <?= \App\Middleware\CsrfMiddleware::field() ?>
                <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                <button type="submit" class="admin-row-actions-item admin-row-actions-item--danger"><?= \App\Helpers\AdminIcon::render('trash', 14) ?> Delete</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
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

<div class="admin-card" style="margin-top:1rem">
  <div class="admin-card-body" style="font-size:.78rem;color:var(--gray-500);line-height:1.75">
    <strong style="color:var(--black);display:block;margin-bottom:.25rem">Security Notes</strong>
    · Passwords hashed with bcrypt (cost 12)<br>
    · You cannot delete your own account<br>
    · Superadmin has full access; Staff may have limited access
  </div>
</div>

<!-- Add/Edit modal -->
<div id="userModal" class="admin-modal-overlay">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <span class="admin-modal-title"><?= $editUser ? 'Edit User: '.htmlspecialchars($editUser['username']) : 'Add New User' ?></span>
      <button type="button" onclick="closeUserModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="save_user" value="1">
        <input type="hidden" name="id" value="<?= $editUser ? $editUser['id'] : 0 ?>">
        <div class="admin-form-group">
          <label class="admin-label" for="username">Username *</label>
          <input id="username" name="username" class="admin-input" required value="<?= htmlspecialchars($editUser['username']??'') ?>" placeholder="e.g. samson">
        </div>
        <div class="admin-form-group">
          <label class="admin-label" for="full_name">Full Name</label>
          <input id="full_name" name="full_name" class="admin-input" value="<?= htmlspecialchars($editUser['full_name']??'') ?>" placeholder="e.g. Samson Gabba">
        </div>
        <div class="admin-form-group">
          <label class="admin-label" for="email">Email</label>
          <input id="email" name="email" type="email" class="admin-input" value="<?= htmlspecialchars($editUser['email']??'') ?>">
        </div>
        <div class="admin-form-group">
          <label class="admin-label" for="role">Role</label>
          <select id="role" name="role" class="admin-input">
            <?php foreach(['superadmin'=>'Super Admin','admin'=>'Admin','agent'=>'Agent','staff'=>'Staff'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($editUser['role']??'admin')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-form-group">
          <label class="admin-label" for="new_password">
            <?= $editUser ? 'New Password <span style="font-weight:600;opacity:.6">(leave blank to keep current)</span>' : 'Password *' ?>
          </label>
          <input id="new_password" name="new_password" type="password" class="admin-input" <?= $editUser?'':'required' ?> minlength="8" placeholder="Min 8 characters">
        </div>
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;margin-bottom:.75rem">
          <input type="checkbox" name="is_active" value="1" <?= ($editUser['is_active']??1)?'checked':'' ?>>
          <span class="admin-label" style="margin:0">Active (can log in)</span>
        </label>

        <div style="border-top:1px solid var(--gray-100);margin:.5rem 0 .85rem;padding-top:.85rem">
          <label class="admin-label" style="display:block;margin-bottom:.5rem" for="can_change_status">Access Permissions</label>
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;margin-bottom:.5rem">
            <input id="can_change_status" type="checkbox" name="can_change_status" value="1" <?= ($editUser['can_change_status']??1)?'checked':'' ?>>
            <span style="font-size:.78rem">Can change status <span style="color:var(--gray-500)">(bookings, leads, commissions)</span></span>
          </label>
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;margin-bottom:0">
            <input type="checkbox" name="can_delete_records" value="1" <?= ($editUser['can_delete_records']??1)?'checked':'' ?>>
            <span style="font-size:.78rem">Can delete records</span>
          </label>
          <p style="font-size:.68rem;color:var(--gray-500);margin-top:.5rem;line-height:1.5">
            Superadmin always has both, regardless of these checkboxes. For agents, these have no extra effect beyond what their role already restricts.
          </p>
        </div>

        <div style="display:flex;gap:.5rem;margin-top:1rem">
          <button type="submit" class="admin-btn admin-btn--primary" style="flex:1;justify-content:center">
            <?= $editUser ? 'Update User' : '+ Create User' ?>
          </button>
          <button type="button" onclick="closeUserModal()" class="admin-btn admin-btn--outline">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openUserModal() { document.getElementById('userModal').classList.add('open'); }
function closeUserModal() { document.getElementById('userModal').classList.remove('open'); }
document.getElementById('userModal').addEventListener('click', function(e){ if (e.target === this) closeUserModal(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeUserModal(); });
<?php if ($editUser || $error || isset($_GET['new'])): ?>
document.addEventListener('DOMContentLoaded', openUserModal);
<?php endif; ?>
</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
