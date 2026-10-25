<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

$uid  = (int)($_SESSION['admin_user_id'] ?? 0);
$msg  = '';
$error = '';

$user = null;
if ($uid) {
    $s = $db->prepare("SELECT * FROM admin_users WHERE id=?");
    $s->execute([$uid]);
    $user = $s->fetch();
}
if (!$user) {
    header('Location: index.php?msg=' . urlencode('Account not found.'));
    exit;
}

// Self-service only: full_name, email, phone, bio. Username/role/
// commission_rate/target_monthly stay admin-managed via users.php — a
// user editing their own role or commission rate here would be a
// privilege-escalation hole, not a profile feature.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $bio      = trim($_POST['bio'] ?? '');

    if ($fullName === '') {
        $error = 'Full name is required.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email address is required.';
    } else {
        try {
            $db->prepare("UPDATE admin_users SET full_name=?, email=?, phone=?, bio=? WHERE id=?")
               ->execute([$fullName, $email, $phone, $bio, $uid]);
            $_SESSION['admin_full_name'] = $fullName;
            $user['full_name'] = $fullName;
            $user['email']     = $email;
            $user['phone']     = $phone;
            $user['bio']       = $bio;
            $msg = 'Profile updated.';
            \App\Services\ActivityLog::record('update', 'admin_user', $uid, ['field' => 'profile']);
        } catch (\Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$initials = '';
foreach (preg_split('/\s+/', trim($user['full_name'] ?: $user['username'])) as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    if (mb_strlen($initials) >= 2) break;
}
$roleLabels = ['superadmin' => 'Super Admin', 'admin' => 'Admin', 'staff' => 'Staff', 'agent' => 'Agent'];

$pageTitle = 'My Profile';
ob_start();
?>
<?php if ($msg): ?>
<div class="admin-alert admin-alert--success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div style="display:flex;flex-direction:column;gap:1.25rem">

  <div class="admin-card">
    <div class="admin-card-body" style="display:flex;align-items:center;gap:1rem">
      <span style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;background:var(--black);color:var(--white);font-size:1.1rem;font-weight:700;flex-shrink:0">
        <?= htmlspecialchars($initials ?: 'A') ?>
      </span>
      <div>
        <div style="font-family:var(--font-disp);font-size:1.15rem;font-weight:500"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></div>
        <div style="font-size:.78rem;color:var(--gray-500)">
          <span class="badge-status badge-status--gray"><?= htmlspecialchars($roleLabels[$user['role']] ?? $user['role']) ?></span>
          · @<?= htmlspecialchars($user['username']) ?>
        </div>
      </div>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Account Details</h3></div>
    <div class="admin-card-body">
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:1rem">
          <div class="admin-form-group">
            <label class="admin-label" for="full_name">Full Name</label>
            <input id="full_name" name="full_name" class="admin-input" required value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
          </div>
          <div class="admin-form-group">
            <label class="admin-label" for="email">Email</label>
            <input id="email" type="email" name="email" class="admin-input" required value="<?= htmlspecialchars($user['email'] ?? '') ?>">
          </div>
          <div class="admin-form-group">
            <label class="admin-label" for="phone">Phone</label>
            <input id="phone" name="phone" class="admin-input" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
          </div>
        </div>
        <div class="admin-form-group">
          <label class="admin-label" for="bio">Bio</label>
          <textarea id="bio" name="bio" class="admin-input" rows="3" style="max-width:640px"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1.25rem">
          <button type="submit" class="admin-btn admin-btn--primary">Save Changes</button>
          <a href="index.php" class="admin-btn admin-btn--outline">Cancel</a>
        </div>
      </form>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Account Info</h3></div>
    <div class="admin-card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:.75rem 1.5rem;font-size:.82rem">
      <div class="detail-row"><span class="detail-label">Username</span><span>@<?= htmlspecialchars($user['username']) ?></span></div>
      <div class="detail-row"><span class="detail-label">Role</span><span><?= htmlspecialchars($roleLabels[$user['role']] ?? $user['role']) ?></span></div>
      <?php if ($user['role'] === 'agent'): ?>
      <div class="detail-row"><span class="detail-label">Commission Rate</span><span><?= htmlspecialchars((string)$user['commission_rate']) ?>%</span></div>
      <?php if ((float)($user['target_monthly'] ?? 0) > 0): ?>
      <div class="detail-row"><span class="detail-label">Monthly Target</span><span>$<?= number_format((float)$user['target_monthly'], 0) ?></span></div>
      <?php endif; ?>
      <?php endif; ?>
      <div class="detail-row"><span class="detail-label">Member Since</span><span><?= date('d F Y', strtotime($user['created_at'])) ?></span></div>
      <div class="detail-row"><span class="detail-label">Last Login</span><span><?= $user['last_login'] ? date('d F Y, H:i', strtotime($user['last_login'])) : '—' ?></span></div>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
      <div>
        <div style="font-weight:600;font-size:.85rem">Password</div>
        <div style="font-size:.78rem;color:var(--gray-500)">Change your account password</div>
      </div>
      <a href="change_password.php" class="admin-btn admin-btn--outline">Change Password →</a>
    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
