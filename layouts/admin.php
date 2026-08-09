<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $pageTitle ?? 'Admin — Safari Zangu Ventures' ?></title>
  <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/images/logo.jpeg">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/admin/css/admin.css">
</head>
<body class="admin-body">

<!-- Overlay for mobile sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="admin-sidebar" id="adminSidebar">
  <div class="admin-logo">
    <div class="admin-logo-left">
      <span class="admin-logo-mark">SZV</span>
      <span class="admin-logo-sub">Admin Panel</span>
    </div>
    <button class="sidebar-close" id="sidebarClose" aria-label="Close menu">&#10005;</button>
  </div>

  <nav class="admin-nav">
    <?php
    $cur = basename($_SERVER['PHP_SELF']);

    // Count unread messages for badge
    $unreadCount = 0;
    try {
      $db_nav = \App\Models\Database::getInstance();
      $unreadCount = (int)$db_nav->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
    } catch(Exception $e) {}

    $groups = [
      'Operations' => [
        ['index.php',            'Dashboard',        '◈', ''],
        ['analytics.php',        'Analytics',        '◉', ''],
        ['bookings.php',         'Bookings',         '◉', ''],
        ['custom_requests.php',  'Safari Requests',  '◎', ''],
        ['contact_messages.php', 'Messages',         '✉', $unreadCount > 0 ? $unreadCount : ''],
        ['leads.php',            'Leads',            '◇', ''],
        ['quotations.php',       'Quotations',       '❝', ''],
      ],
      'Content' => [
        ['packages.php',         'Packages',         '▣', ''],
        ['package_edit.php',      'New Package',      '+', ''],
        ['team_members.php',     'Team',             '◑', ''],
        ['accommodations.php',   'Accommodations',   '⌂', ''],
        ['activities.php',       'Activities',       '◆', ''],
        ['vehicles.php',         'Fleet',            '⊕', ''],
      ],
      'Settings' => [
        ['park_fees.php',        'Park Fees',        '♦', ''],
        ['seasonal_rates.php',   'Seasons',          '◐', ''],
      ],
    ];

    foreach ($groups as $groupLabel => $links):
    ?>
    <span class="admin-nav-group"><?= $groupLabel ?></span>
    <?php foreach ($links as [$href, $label, $icon, $badge]):
      $active = ($cur === $href) ? ' active' : '';
    ?>
    <a href="<?= $href ?>" class="admin-nav-link<?= $active ?>">
      <span class="admin-nav-icon"><?= $icon ?></span>
      <?= $label ?>
      <?php if ($badge): ?>
        <span class="nav-badge"><?= $badge ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; endforeach; ?>
  </nav>

  <div class="admin-sidebar-footer">
    <a href="<?= BASE_PATH ?>/" target="_blank" class="admin-nav-link">← View Site</a>
    <a href="logout.php" class="admin-nav-link admin-nav-link--danger">Sign Out</a>
  </div>
</aside>

<main class="admin-main">
  <header class="admin-topbar">
    <div class="admin-topbar-left">
      <button class="admin-hamburger" id="adminHamburger" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <h1 class="admin-page-title"><?= $pageTitle ?? '' ?></h1>
    </div>
    <div class="admin-topbar-right">
      <span class="admin-env-badge"><?= defined('APP_ENV') && APP_ENV === 'local' ? 'LOCAL' : 'LIVE' ?></span>
      <a href="logout.php" class="admin-user" style="text-decoration:none;color:inherit">Admin ↗</a>
    </div>
  </header>

  <div class="admin-content">
    <?= $content ?? '' ?>
  </div>
</main>

<script src="<?= BASE_PATH ?>/admin/js/admin-validator.js"></script>
<script src="<?= BASE_PATH ?>/admin/js/admin.js"></script>
<script>
// ── Sidebar toggle (mobile) ──────────────────────────────────
(function () {
  var sidebar  = document.getElementById('adminSidebar');
  var overlay  = document.getElementById('sidebarOverlay');
  var hamburger= document.getElementById('adminHamburger');
  var closeBtn = document.getElementById('sidebarClose');

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
    hamburger.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    hamburger.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  if (hamburger) hamburger.addEventListener('click', openSidebar);
  if (closeBtn)  closeBtn.addEventListener('click', closeSidebar);
  if (overlay)   overlay.addEventListener('click', closeSidebar);

  // Close on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
  });

  // Close sidebar on nav link click (mobile)
  if (sidebar) {
    sidebar.querySelectorAll('.admin-nav-link').forEach(function(link) {
      link.addEventListener('click', function() {
        if (window.innerWidth <= 768) closeSidebar();
      });
    });
  }
})();
</script>
</body>
</html>
