<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $pageTitle ?? 'Admin — Safari Zangu Ventures' ?></title>
  <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/images/logo.jpeg">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/admin/css/admin.css?v=<?= @filemtime(__DIR__ . '/../admin/css/admin.css') ?: time() ?>">
<script>
window.BASE_PATH = "<?= BASE_PATH ?>";
window.CSRF_TOKEN = "<?= \App\Middleware\CsrfMiddleware::token() ?>";
</script>
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
    $unreadCount  = 0;
    $queuePending = 0;
    $remindersDue = 0;
    $newRequests  = 0;
    $openIncidents = 0;
    try {
      $db_nav       = \App\Models\Database::getInstance();
      $unreadCount  = (int)$db_nav->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
      $queuePending = (int)$db_nav->query("SELECT COUNT(*) FROM email_queue WHERE status='pending'")->fetchColumn();
      $remindersDue = (int)$db_nav->query("SELECT COUNT(*) FROM booking_reminders WHERE status='pending' AND send_at<=DATE_ADD(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
      $newRequests  = (int)$db_nav->query("SELECT COUNT(*) FROM custom_safari_requests WHERE status='new'")->fetchColumn();
      $openIncidents = (int)$db_nav->query("SELECT COUNT(*) FROM incidents WHERE status='open'")->fetchColumn();
    } catch(Exception $e) {}

    // Grouped by how often you'd actually reach for each page, not by
    // data type — Operations is everything touched daily; Catalog,
    // Field Ops, People & Partners and Settings are the lower-frequency
    // reference/configuration pages, split by subject instead of dumped
    // into one long list. Operations' exact membership (and Settings') is
    // load-bearing for the role filter below — see the comment there
    // before adding/removing anything from either.
    $groups = [
      'Dashboards' => [
        ['index.php',            'Overview',         'dashboard', ''],
        ['dashboard_sales.php',       'Sales &amp; Pipeline', 'analytics', ''],
        ['dashboard_operations.php',  'Operations',       'calendar', ''],
        ['dashboard_profitability.php','Profitability',  'trending', ''],
        ['dashboard_customer_experience.php', 'Customer Experience', 'star', ''],
        ['dashboard_agents.php',      'Agent Performance','target', ''],
        ['dashboard_fleet.php',       'Suppliers &amp; Fleet', 'suppliers', ''],
      ],
      'Operations' => [
        ['analytics.php',        'Web Analytics',    'analytics', ''],
        ['operations_calendar.php', 'Ops Calendar',  'calendar', ''],
        ['bookings.php',         'Bookings',         'bookings', ''],
        ['invoices.php',         'Invoices',         'invoices', ''],
        ['customers.php',        'Customers',        'customers', ''],
        ['custom_requests.php',  'Requests',         'requests', $newRequests > 0 ? $newRequests : ''],
        ['contact_messages.php', 'Messages',         'messages', $unreadCount > 0 ? $unreadCount : ''],
        ['leads.php',            'Leads',            'leads', ''],
        ['commissions.php',    'Commissions',      'commissions', ''],
        ['quotations.php',       'Quotations',       'quotations', ''],
        ['reviews.php',          'Reviews',          'star', ''],
      ],
      'Communications' => [
        ['email_templates.php', 'Email Templates',   'email-template', ''],
        ['email_queue.php',      'Email Queue',      'email-queue', $queuePending > 0 ? $queuePending : ''],
        ['reminders.php',        'Reminders',        'reminders', $remindersDue > 0 ? $remindersDue : ''],
      ],
      'Catalog' => [
        ['packages.php',         'Packages',         'packages', ''],
        ['package_edit.php',      'New Package',      'plus', ''],
        ['itineraries.php',      'Itineraries',      'itineraries', ''],
        ['accommodations.php',   'Accommodations',   'accommodations', ''],
        ['activities.php',       'Activities',       'activities', ''],
        ['vehicles.php',         'Fleet',            'fleet', ''],
        ['poster_maker.php',       'Poster Maker',        'poster', ''],
        ['gallery.php',          'Image Gallery',    'gallery', ''],
      ],
      'Field Operations' => [
        ['room_availability.php', 'Room Availability', 'inventory', ''],
        ['incidents.php',         'Incidents',        'incident', $openIncidents > 0 ? $openIncidents : ''],
        ['emergency_contacts.php', 'Emergency Contacts', 'alert', ''],
      ],
      'People &amp; Partners' => [
        ['team_members.php',     'Team',             'team', ''],
        ['suppliers.php',        'Suppliers',        'suppliers', ''],
        ['staff.php',            'Guides &amp; Drivers', 'guide', ''],
      ],
      'Settings' => [
        ['park_fees.php',        'Park Fees',        'park-fees', ''],
        ['seasonal_rates.php',   'Seasons',          'seasons', ''],
        ['currency_rates.php',   'Currencies',       'currency', ''],
        ['users.php',             'Users',            'users', ''],
        ['activity_log.php',      'Activity Log',     'clock', ''],
      ],
    ];

    // Role-based sidebar: superadmin/admin see every group unchanged.
    // staff loses Settings (still full access within what's left — the
    // pages themselves aren't restricted for staff, only the nav and the
    // Settings pages' own direct-access guard are). agent is narrowed to
    // Operations only, where several pages additionally scope their data
    // to the signed-in agent's own records (leads.php, quotations.php,
    // commissions.php, invoices.php).
    $myNavRole = $_SESSION['admin_role'] ?? 'agent';
    // Profitability and Agent Performance carry margin/cross-agent data
    // that admin/dashboard_profitability.php and admin/dashboard_agents.php
    // already gate server-side (isAdminRole()) — hidden here too so the
    // nav never shows a link a non-admin/superadmin can't actually open.
    if ($myNavRole !== 'admin' && $myNavRole !== 'superadmin') {
        $groups['Dashboards'] = array_values(array_filter($groups['Dashboards'], fn($l) => !in_array($l[0], ['dashboard_profitability.php', 'dashboard_agents.php'], true)));
    }
    if ($myNavRole === 'agent') {
        $groups = ['Dashboards' => [$groups['Dashboards'][0]], 'Operations' => $groups['Operations']];
    } elseif ($myNavRole === 'staff') {
        unset($groups['Settings']);
    }

    foreach ($groups as $groupLabel => $links):
      $groupKey = preg_replace('/[^a-z0-9]+/', '-', strtolower(strip_tags($groupLabel)));
      $groupHasActive = false;
      foreach ($links as [$href]) { if ($cur === $href) { $groupHasActive = true; break; } }
    ?>
    <button type="button" class="admin-nav-group" data-group="<?= $groupKey ?>" aria-expanded="true">
      <span><?= $groupLabel ?></span>
      <span class="admin-nav-group-chevron"><?= \App\Helpers\AdminIcon::render('chevron-down', 11) ?></span>
    </button>
    <div class="admin-nav-items" data-group-items="<?= $groupKey ?>"<?= $groupHasActive ? ' data-has-active="1"' : '' ?>>
    <?php foreach ($links as [$href, $label, $icon, $badge]):
      $active = ($cur === $href) ? ' active' : '';
    ?>
    <a href="<?= $href ?>" class="admin-nav-link<?= $active ?>">
      <span class="admin-nav-icon"><?= \App\Helpers\AdminIcon::render($icon, 15) ?></span>
      <?= $label ?>
      <?php if ($badge): ?>
        <span class="nav-badge"><?= $badge ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>

  <div class="admin-sidebar-footer">
    <a href="<?= BASE_PATH ?>/" target="_blank" class="admin-nav-link"><span class="admin-nav-icon"><?= \App\Helpers\AdminIcon::render('external-link', 15) ?></span> View Site</a>
    <a href="change_password.php" class="admin-nav-link"><span class="admin-nav-icon"><?= \App\Helpers\AdminIcon::render('key', 15) ?></span> Change Password</a>
    <a href="logout.php" class="admin-nav-link admin-nav-link--danger"><span class="admin-nav-icon"><?= \App\Helpers\AdminIcon::render('logout', 15) ?></span> Sign Out</a>
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
      <span class="admin-user">
        <?= htmlspecialchars($_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? 'Admin') ?>
      </span>
      <a href="logout.php" class="admin-btn admin-btn--muted" style="font-size:.62rem;padding:.28rem .65rem">Sign Out</a>
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

// ── Collapsible nav groups ───────────────────────────────────
// Per-group state remembered across page loads (this is a classic
// multi-page app, not an SPA, so without this every navigation would
// reset every group back open). The group containing the current page
// is always forced open regardless of saved state — collapsing the
// section you're currently in would hide where you are.
//
// Default (no saved preference yet) is COLLAPSED, not expanded: with
// 7 groups and dozens of links, having them all open at once is taller
// than the viewport, so the lower groups end up scrolled out of view
// with no visible scrollbar hint — they look missing, not just hidden
// below the fold. Starting collapsed means everything on screen is
// fully visible; the user opens only the section(s) they need.
(function () {
  var STORE_KEY = 'szv_admin_nav_collapsed';

  function readState() {
    try { return JSON.parse(localStorage.getItem(STORE_KEY) || '{}'); }
    catch (e) { return {}; }
  }
  function writeState(state) {
    try { localStorage.setItem(STORE_KEY, JSON.stringify(state)); }
    catch (e) { /* private-window / storage blocked — collapsing just won't persist */ }
  }

  var state = readState();

  document.querySelectorAll('.admin-nav-group').forEach(function (btn) {
    var key   = btn.getAttribute('data-group');
    var items = document.querySelector('.admin-nav-items[data-group-items="' + key + '"]');
    if (!items) return;

    var hasActive = items.getAttribute('data-has-active') === '1';
    var saved     = Object.prototype.hasOwnProperty.call(state, key) ? state[key] : true;
    var collapsed = !hasActive && saved === true;

    if (collapsed) {
      items.classList.add('collapsed');
      btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function () {
      if (hasActive) return; // never let the current page's own group be hidden
      var nowCollapsed = items.classList.toggle('collapsed');
      btn.setAttribute('aria-expanded', nowCollapsed ? 'false' : 'true');
      state[key] = nowCollapsed;
      writeState(state);
    });
  });
})();
</script>
</body>
</html>

