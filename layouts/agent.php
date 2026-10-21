<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $pageTitle ?? 'Agent Portal' ?> — Safari Zangu Ventures</title>
  <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/images/logo.jpeg">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/admin/css/admin.css?v=<?= @filemtime(__DIR__ . '/../admin/css/admin.css') ?: time() ?>">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/agent-portal/css/agent.css?v=<?= @filemtime(__DIR__ . '/../agent-portal/css/agent.css') ?: time() ?>">
<script>
window.BASE_PATH = "<?= BASE_PATH ?>";
window.CSRF_TOKEN = "<?= \App\Middleware\CsrfMiddleware::token() ?>";
</script>
</head>
<body class="agent-body">

<header class="agent-navbar">
  <div class="agent-navbar-left">
    <a href="index.php" class="agent-brand">
      <span class="agent-brand-mark">SZV</span>
      <span class="agent-brand-sub">Agent Portal</span>
    </a>
    <?php $cur = basename($_SERVER['PHP_SELF']); ?>
    <nav class="agent-nav-links" id="agentNavLinks">
      <a href="index.php" class="agent-nav-link<?= $cur==='index.php'?' active':'' ?>"><?= \App\Helpers\AdminIcon::render('dashboard', 14) ?> Dashboard</a>
      <a href="leads.php" class="agent-nav-link<?= $cur==='leads.php'?' active':'' ?>"><?= \App\Helpers\AdminIcon::render('leads', 14) ?> Leads</a>
      <a href="commissions.php" class="agent-nav-link<?= $cur==='commissions.php'?' active':'' ?>"><?= \App\Helpers\AdminIcon::render('commissions', 14) ?> Commissions</a>
      <a href="profile.php" class="agent-nav-link<?= $cur==='profile.php'?' active':'' ?>"><?= \App\Helpers\AdminIcon::render('key', 14) ?> Profile</a>
    </nav>
  </div>
  <div class="agent-navbar-right">
    <?php
      $agentDisplayName = $_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? 'Agent';
      $agentInitials = '';
      foreach (preg_split('/\s+/', trim($agentDisplayName)) as $part) {
        if ($part !== '') $agentInitials .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($agentInitials) >= 2) break;
      }
    ?>
    <a href="profile.php" class="agent-profile-link" title="<?= htmlspecialchars($agentDisplayName) ?>">
      <span class="agent-profile-avatar"><?= htmlspecialchars($agentInitials ?: 'A') ?></span>
      <span class="agent-profile-name"><?= htmlspecialchars($agentDisplayName) ?></span>
    </a>
    <a href="logout.php" class="agent-signout">Sign Out</a>
    <button class="agent-hamburger" id="agentHamburger" aria-label="Open menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<main>
  <div class="agent-content">
    <?php if (!empty($pageTitle)): ?><h1 class="agent-page-title"><?= htmlspecialchars($pageTitle) ?></h1><?php endif; ?>
    <?= $content ?? '' ?>
  </div>
</main>

<script src="<?= BASE_PATH ?>/admin/js/admin-validator.js"></script>
<script src="<?= BASE_PATH ?>/admin/js/admin.js?v=<?= @filemtime(__DIR__ . '/../admin/js/admin.js') ?: time() ?>"></script>
<script>
(function () {
  var btn   = document.getElementById('agentHamburger');
  var links = document.getElementById('agentNavLinks');
  if (!btn || !links) return;
  btn.addEventListener('click', function () {
    var open = links.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();
</script>
</body>
</html>
