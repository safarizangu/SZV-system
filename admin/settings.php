<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Settings page — admin/superadmin only, same direct-access guard every
// other page in the Settings nav group uses (staff/agent lose the nav
// link, but that alone isn't enforcement — see layouts/admin.php).
if (!AuthMiddleware::isAdminRole()) { header('Location: index.php'); exit; }
$db   = \App\Models\Database::getInstance();
$myId = (int)($_SESSION['admin_user_id'] ?? 0);
$pageTitle = 'General Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_theme'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $theme = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
    try {
        $db->prepare("UPDATE admin_users SET theme_preference=? WHERE id=?")->execute([$theme, $myId]);
        $_SESSION['theme_preference'] = $theme;
        header('Location: settings.php?msg='.urlencode('Theme updated.')); exit;
    } catch (\Exception $e) {
        header('Location: settings.php?msg='.urlencode('Error: '.$e->getMessage())); exit;
    }
}

$msg = htmlspecialchars($_GET['msg'] ?? '');
$currentTheme = $_SESSION['theme_preference'] ?? 'light';

ob_start();
?>
<?php if ($msg): ?>
<div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div>
<?php endif; ?>

<div style="max-width:560px">
  <div class="admin-card">
    <div class="admin-card-header"><h3>Appearance</h3></div>
    <div class="admin-card-body">
      <p style="font-size:.82rem;color:var(--gray-500);margin-bottom:1.25rem;line-height:1.6">
        Choose how the admin panel looks for your account. This is saved to your profile, so it
        follows you to any device you sign in on.
      </p>
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_theme" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">

          <label class="theme-option<?= $currentTheme==='light'?' theme-option--active':'' ?>">
            <input type="radio" name="theme" value="light" <?= $currentTheme==='light'?'checked':'' ?> onchange="this.form.requestSubmit()">
            <div class="theme-swatch theme-swatch--light">
              <span class="theme-swatch-bar"></span>
              <span class="theme-swatch-card"></span>
            </div>
            <span class="theme-option-label">Light</span>
          </label>

          <label class="theme-option<?= $currentTheme==='dark'?' theme-option--active':'' ?>">
            <input type="radio" name="theme" value="dark" <?= $currentTheme==='dark'?'checked':'' ?> onchange="this.form.requestSubmit()">
            <div class="theme-swatch theme-swatch--dark">
              <span class="theme-swatch-bar"></span>
              <span class="theme-swatch-card"></span>
            </div>
            <span class="theme-option-label">Dark</span>
          </label>

        </div>
        <noscript><button type="submit" class="admin-btn admin-btn--primary">Save</button></noscript>
      </form>
    </div>
  </div>
</div>

<style>
.theme-option { cursor: pointer; display: block; }
.theme-option input { position: absolute; opacity: 0; width: 0; height: 0; }
.theme-swatch {
  border: 1.5px solid var(--gray-200);
  padding: .6rem;
  margin-bottom: .5rem;
  transition: border-color .15s;
}
.theme-option--active .theme-swatch { border-color: var(--black); }
.theme-swatch-bar  { display: block; height: 10px; margin-bottom: .4rem; }
.theme-swatch-card { display: block; height: 28px; }
.theme-swatch--light { background: #f9f9f8; }
.theme-swatch--light .theme-swatch-bar  { background: #0a0a0a; }
.theme-swatch--light .theme-swatch-card { background: #ffffff; border: 1px solid #e6e6e2; }
.theme-swatch--dark  { background: #141414; }
.theme-swatch--dark  .theme-swatch-bar  { background: #f0f0ee; }
.theme-swatch--dark  .theme-swatch-card { background: #1c1c1c; border: 1px solid #383838; }
.theme-option-label { font-size: .78rem; font-weight: 700; }
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
