<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAgent();
$db   = \App\Models\Database::getInstance();
$myId = (int)($_SESSION['admin_user_id'] ?? 0);

$msg = ''; $error = '';
$pwMsg = ''; $pwError = '';

$stmt = $db->prepare("SELECT * FROM admin_users WHERE id=?");
$stmt->execute([$myId]);
$me = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $fullName = trim($_POST['full_name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $bio      = trim($_POST['bio'] ?? '');

    if (!$fullName) {
        $error = 'Full name is required.';
    } else {
        try {
            $db->prepare("UPDATE admin_users SET full_name=?, phone=?, bio=? WHERE id=?")
               ->execute([$fullName, $phone, $bio, $myId]);
            $_SESSION['admin_full_name'] = $fullName;
            $msg = 'Profile updated.';
            $me['full_name'] = $fullName; $me['phone'] = $phone; $me['bio'] = $bio;
        } catch (\Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $pwError = 'All password fields are required.';
    } elseif (strlen($new) < 10) {
        $pwError = 'New password must be at least 10 characters.';
    } elseif ($new !== $confirm) {
        $pwError = 'New passwords do not match.';
    } elseif (!password_verify($current, $me['password_hash'])) {
        $pwError = 'Current password is incorrect.';
    } else {
        try {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
            $db->prepare("UPDATE admin_users SET password_hash=? WHERE id=?")->execute([$hash, $myId]);
            $pwMsg = 'Password updated successfully.';
        } catch (\Exception $e) {
            $pwError = 'Error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'My Profile';
ob_start();
?>
<div style="display:flex;flex-direction:column;gap:1.25rem">

  <div class="admin-card">
    <div class="admin-card-header"><h3>Profile Details</h3></div>
    <div class="admin-card-body">
      <?php if ($msg): ?><div class="admin-alert admin-alert--success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_profile" value="1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:1rem;margin-bottom:1rem">
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Username</label>
            <input class="admin-input" value="<?= htmlspecialchars($me['username']) ?>" disabled style="opacity:.6">
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Email</label>
            <input class="admin-input" value="<?= htmlspecialchars($me['email'] ?? '') ?>" disabled style="opacity:.6">
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Full Name *</label>
            <input name="full_name" class="admin-input" required value="<?= htmlspecialchars($me['full_name'] ?? '') ?>">
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Phone (WhatsApp)</label>
            <input name="phone" class="admin-input" value="<?= htmlspecialchars($me['phone'] ?? '') ?>" placeholder="+255 7XX XXX XXX">
          </div>
        </div>
        <div class="admin-form-group" style="margin-bottom:1.25rem">
          <label class="admin-label">Bio</label>
          <textarea name="bio" class="admin-input" rows="3" placeholder="A short note about yourself (optional)" style="max-width:640px"><?= htmlspecialchars($me['bio'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Save Changes</button>
      </form>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Change Password</h3></div>
    <div class="admin-card-body">
      <?php if ($pwMsg): ?><div class="admin-alert admin-alert--success"><?= htmlspecialchars($pwMsg) ?></div><?php endif; ?>
      <?php if ($pwError): ?><div class="admin-alert admin-alert--error"><?= htmlspecialchars($pwError) ?></div><?php endif; ?>
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_password" value="1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:1rem">
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Current Password</label>
            <input type="password" name="current_password" class="admin-input" required autocomplete="current-password">
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">New Password <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(min 10 characters)</span></label>
            <input type="password" name="new_password" class="admin-input" required autocomplete="new-password" minlength="10">
          </div>
          <div class="admin-form-group" style="margin-bottom:0">
            <label class="admin-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="admin-input" required autocomplete="new-password" minlength="10">
          </div>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary" style="margin-top:1.25rem">Update Password</button>
      </form>
    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/agent.php';
