<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password']     ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $error = 'All fields are required.';
    } elseif (strlen($new) < 10) {
        $error = 'New password must be at least 10 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        try {
            $uid  = $_SESSION['admin_user_id'] ?? null;
            $user = null;
            if ($uid) {
                $s = $db->prepare("SELECT * FROM admin_users WHERE id=?");
                $s->execute([$uid]);
                $user = $s->fetch();
            }
            if (!$user) {
                // Fallback: match by username
                $uname = $_SESSION['admin_username'] ?? 'admin';
                $s = $db->prepare("SELECT * FROM admin_users WHERE username=?");
                $s->execute([$uname]);
                $user = $s->fetch();
            }
            if (!$user) {
                $error = 'User not found.';
            } elseif (!password_verify($current, $user['password_hash'])) {
                $error = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
                $db->prepare("UPDATE admin_users SET password_hash=? WHERE id=?")
                   ->execute([$hash, $user['id']]);
                $msg = 'Password updated successfully.';
            }
        } catch (\Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Change Password';
ob_start();
?>
<div style="max-width:480px">
  <?php if ($msg): ?>
  <div class="admin-alert admin-alert--success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Change Admin Password</h3></div>
    <div class="admin-card-body">
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <div class="admin-form-group">
          <label class="admin-label">Current Password</label>
          <input type="password" name="current_password" class="admin-input" required autocomplete="current-password">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">New Password <span style="color:var(--gray-500);font-weight:400;text-transform:none;letter-spacing:0">(min 10 characters)</span></label>
          <input type="password" name="new_password" class="admin-input" required autocomplete="new-password" minlength="10">
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Confirm New Password</label>
          <input type="password" name="confirm_password" class="admin-input" required autocomplete="new-password" minlength="10">
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1.25rem">
          <button type="submit" class="admin-btn admin-btn--primary">Update Password</button>
          <a href="index.php" class="admin-btn admin-btn--outline">Cancel</a>
        </div>
      </form>
    </div>
  </div>

  <div class="admin-card" style="margin-top:1.25rem">
    <div class="admin-card-body" style="font-size:.82rem;color:var(--gray-500);line-height:1.7">
      <strong style="color:var(--black)">Password requirements:</strong><br>
      · Minimum 10 characters<br>
      · Use a mix of letters, numbers and symbols<br>
      · Never reuse your previous password<br>
      · Do not share this password with anyone
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
?>
