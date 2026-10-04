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
    // Topbar notification bell — same "new/unactioned" counts as the nav
    // badges above, plus new leads and recent bookings, which don't have
    // their own nav badge. Small preview rows (LIMIT 5 each) so the
    // dropdown shows what's new, not just a number.
    $newLeadsCount    = 0;
    $newBookingsCount = 0;
    $notifMessages    = [];
    $notifRequests    = [];
    $notifLeads       = [];
    $notifBookings    = [];
    try {
      $db_nav       = \App\Models\Database::getInstance();
      $unreadCount  = (int)$db_nav->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
      $queuePending = (int)$db_nav->query("SELECT COUNT(*) FROM email_queue WHERE status='pending'")->fetchColumn();
      $remindersDue = (int)$db_nav->query("SELECT COUNT(*) FROM booking_reminders WHERE status='pending' AND send_at<=DATE_ADD(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
      $navScoped = \App\Services\OwnershipScope::isScoped();
      $navMe     = \App\Services\OwnershipScope::currentAgentId();
      [$reqScope, $reqP] = $navScoped ? \App\Services\OwnershipScope::customRequestsListSql($navMe) : ['1=1', []];
      [$bkScope,  $bkP]  = $navScoped ? \App\Services\OwnershipScope::bookingsListSql($navMe)       : ['1=1', []];
      $leadScope = $navScoped ? 'agent_id = ?' : '1=1';
      $leadP     = $navScoped ? [$navMe] : [];
      $newRequests  = (int)(function() use ($db_nav, $reqScope, $reqP) { $s = $db_nav->prepare("SELECT COUNT(*) FROM custom_safari_requests WHERE status='new' AND $reqScope"); $s->execute($reqP); return $s->fetchColumn(); })();
      $openIncidents = (int)$db_nav->query("SELECT COUNT(*) FROM incidents WHERE status='open'")->fetchColumn();
      $ls = $db_nav->prepare("SELECT COUNT(*) FROM leads WHERE pipeline_stage='lead' AND $leadScope"); $ls->execute($leadP);
      $newLeadsCount = (int)$ls->fetchColumn();
      $bs = $db_nav->prepare("SELECT COUNT(*) FROM bookings WHERE created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR) AND $bkScope"); $bs->execute($bkP);
      $newBookingsCount = (int)$bs->fetchColumn();

      $notifMessages = $db_nav->query("SELECT id, full_name, message, created_at FROM contact_messages WHERE is_read=0 ORDER BY created_at DESC LIMIT 5")->fetchAll();
      $rq = $db_nav->prepare("SELECT id, full_name, created_at FROM custom_safari_requests WHERE status='new' AND $reqScope ORDER BY created_at DESC LIMIT 5"); $rq->execute($reqP);
      $notifRequests = $rq->fetchAll();
      $ll = $db_nav->prepare("SELECT id, full_name, created_at FROM leads WHERE pipeline_stage='lead' AND $leadScope ORDER BY created_at DESC LIMIT 5"); $ll->execute($leadP);
      $notifLeads = $ll->fetchAll();
      $bl = $db_nav->prepare("SELECT booking_ref, full_name, created_at FROM bookings WHERE created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR) AND $bkScope ORDER BY created_at DESC LIMIT 5"); $bl->execute($bkP);
      $notifBookings = $bl->fetchAll();
    } catch(Exception $e) {}
    $notifTotal = $unreadCount + $newRequests + $newLeadsCount + $newBookingsCount;

    // Regrouped per an explicit admin request — Dashboard is now just the
    // one overview page; former Dashboards widgets (Sales & Pipeline,
    // Profitability, Customer Experience, Agent Performance) moved into
    // Analytics/Agent below instead of sharing a group with the overview.
    // analytics.php (Web Analytics), operations_calendar.php (Ops
    // Calendar) and dashboard_operations.php (Operations dashboard
    // widget) and dashboard_fleet.php (Suppliers & Fleet widget) were
    // deliberately left out of the requested structure — the pages still
    // exist and work, they're just not linked from the sidebar anymore.
    // Group membership here is load-bearing for the role filter below —
    // see the comment there before adding/removing anything.
    $groups = [
      'Dashboard' => [
        ['index.php',            'Overview',         'dashboard', ''],
      ],
      'Operations' => [
        ['bookings.php',         'Bookings',         'bookings', ''],
        ['custom_requests.php',  'Requests',         'requests', $newRequests > 0 ? $newRequests : ''],
        ['quotations.php',       'Quotations',       'quotations', ''],
        ['invoices.php',         'Invoices',         'invoices', ''],
        ['customers.php',        'Customers',        'customers', ''],
        ['contact_messages.php', 'Messages',         'messages', $unreadCount > 0 ? $unreadCount : ''],
      ],
      'Communications' => [
        ['email_templates.php', 'Email Templates',   'email-template', ''],
        ['email_queue.php',      'Email Queue',      'email-queue', $queuePending > 0 ? $queuePending : ''],
        ['reminders.php',        'Reminders',        'reminders', $remindersDue > 0 ? $remindersDue : ''],
      ],
      'Catalog' => [
        ['packages.php',         'Packages',         'packages', ''],
        ['itineraries.php',      'Itineraries',      'itineraries', ''],
        ['accommodations.php',   'Accommodations',   'accommodations', ''],
        ['activities.php',       'Activities',       'activities', ''],
        ['vehicles.php',         'Fleet',            'fleet', ''],
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
      'Analytics' => [
        ['dashboard_profitability.php','Profitability',  'trending', ''],
        ['dashboard_customer_experience.php', 'Customer Experience', 'star', ''],
        ['reviews.php',          'Reviews',          'star', ''],
        ['dashboard_sales.php',  'Sales &amp; Pipeline', 'analytics', ''],
      ],
      'Agent' => [
        ['leads.php',            'Leads',            'leads', ''],
        ['dashboard_agents.php', 'Agent Performance','target', ''],
        ['commissions.php',      'Commissions',      'commissions', ''],
      ],
      'Gallery' => [
        ['poster_maker.php',     'Poster Maker',     'poster', ''],
        ['gallery.php',          'Image Gallery',    'gallery', ''],
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
    // Dashboard + Operations + Agent, where several pages additionally
    // scope their data to the signed-in agent's own records (leads.php,
    // quotations.php, commissions.php, invoices.php).
    $myNavRole = $_SESSION['admin_role'] ?? 'agent';
    // Profitability and Agent Performance carry margin/cross-agent data
    // that admin/dashboard_profitability.php and admin/dashboard_agents.php
    // already gate server-side (isAdminRole()) — hidden here too so the
    // nav never shows a link a non-admin/superadmin can't actually open.
    if ($myNavRole !== 'admin' && $myNavRole !== 'superadmin') {
        $groups['Analytics'] = array_values(array_filter($groups['Analytics'], fn($l) => $l[0] !== 'dashboard_profitability.php'));
        $groups['Agent']     = array_values(array_filter($groups['Agent'], fn($l) => $l[0] !== 'dashboard_agents.php'));
    }
    if ($myNavRole === 'agent') {
        $groups = [
            'Dashboard'  => $groups['Dashboard'],
            'Operations' => $groups['Operations'],
            'Agent'      => $groups['Agent'],
        ];
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
      <?php
        $notifGroups = [
          ['label' => 'Unread Messages', 'count' => $unreadCount,    'href' => 'contact_messages.php', 'rows' => $notifMessages, 'nameKey' => 'full_name', 'subKey' => 'message'],
          ['label' => 'New Requests',    'count' => $newRequests,    'href' => 'custom_requests.php',  'rows' => $notifRequests, 'nameKey' => 'full_name', 'subKey' => null],
          ['label' => 'New Leads',       'count' => $newLeadsCount,  'href' => 'leads.php',             'rows' => $notifLeads,    'nameKey' => 'full_name', 'subKey' => null],
          ['label' => 'New Bookings',    'count' => $newBookingsCount,'href' => 'bookings.php',         'rows' => $notifBookings, 'nameKey' => 'full_name', 'subKey' => 'booking_ref'],
        ];
      ?>
      <div class="admin-notif" id="adminNotif">
        <button type="button" class="admin-icon-btn admin-notif-toggle" id="notifToggle" aria-label="Notifications" aria-expanded="false">
          <?= \App\Helpers\AdminIcon::render('bell', 19) ?>
          <?php if ($notifTotal > 0): ?><span class="admin-notif-badge"><?= $notifTotal > 99 ? '99+' : $notifTotal ?></span><?php endif; ?>
        </button>
        <div class="admin-notif-panel" id="notifPanel">
          <div class="admin-notif-panel-header">Notifications</div>
          <?php if ($notifTotal === 0): ?>
          <div class="admin-notif-empty">You're all caught up.</div>
          <?php else: foreach ($notifGroups as $g): if (empty($g['rows'])) continue; ?>
          <div class="admin-notif-group">
            <a href="<?= $g['href'] ?>" class="admin-notif-group-title"><?= htmlspecialchars($g['label']) ?> (<?= $g['count'] ?>)</a>
            <?php foreach ($g['rows'] as $r): ?>
            <a href="<?= $g['href'] ?>" class="admin-notif-item">
              <strong><?= htmlspecialchars($r[$g['nameKey']] ?? '') ?></strong>
              <?php if ($g['subKey'] && !empty($r[$g['subKey']])): ?>
              <span><?= htmlspecialchars(mb_strlen($r[$g['subKey']]) > 60 ? mb_substr($r[$g['subKey']], 0, 60) . '…' : $r[$g['subKey']]) ?></span>
              <?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <?php
        $adminDisplayName = $_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
        $adminInitials    = '';
        foreach (preg_split('/\s+/', trim($adminDisplayName)) as $part) {
          if ($part !== '') $adminInitials .= mb_strtoupper(mb_substr($part, 0, 1));
          if (mb_strlen($adminInitials) >= 2) break;
        }
      ?>
      <a href="profile.php" class="admin-profile-link" title="<?= htmlspecialchars($adminDisplayName) ?>">
        <span class="admin-profile-avatar"><?= htmlspecialchars($adminInitials ?: 'A') ?></span>
      </a>
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

// ── Notification bell dropdown ───────────────────────────────
(function () {
  var wrap  = document.getElementById('adminNotif');
  var toggle = document.getElementById('notifToggle');
  var panel  = document.getElementById('notifPanel');
  if (!wrap || !toggle || !panel) return;

  function open() {
    wrap.classList.add('open');
    toggle.setAttribute('aria-expanded', 'true');
  }
  function close() {
    wrap.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
  }

  toggle.addEventListener('click', function (e) {
    e.stopPropagation();
    wrap.classList.contains('open') ? close() : open();
  });
  document.addEventListener('click', function (e) {
    if (!wrap.contains(e.target)) close();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });
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

