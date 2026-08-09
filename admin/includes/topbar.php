<header class="topbar">
  <div class="topbar-left">
    <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">&#9776;</button>
    <h1 class="topbar-title"><?= $pageTitle ?? 'Admin' ?></h1>
  </div>
  <div class="topbar-right">
    <span class="env-badge"><?= defined('APP_ENV') && APP_ENV === 'local' ? 'LOCAL' : 'LIVE' ?></span>
    <span class="admin-user">Admin</span>
  </div>
</header>
