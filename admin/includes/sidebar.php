<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">SZV</div>
    <div class="sidebar-logo-sub">Admin Panel</div>
  </div>
  <nav class="sidebar-nav">
    <?php
    $cur   = basename($_SERVER['PHP_SELF']);
    $links = [
        ['index.php',           'Dashboard',       '◈'],
        ['bookings.php',        'Bookings',        '◉'],
        ['custom_requests.php', 'Safari Requests', '◎'],
        ['reminders.php',       'Reminders',       '◷'],
    ];
    foreach ($links as [$href, $label, $icon]):
        $active = ($cur === $href) ? ' active' : '';
    ?>
    <a href="<?= $href ?>" class="sidebar-link<?= $active ?>">
      <span class="sidebar-icon"><?= $icon ?></span>
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="<?= BASE_PATH ?>/" target="_blank" class="sidebar-link">← View Site</a>
    <a href="logout.php" class="sidebar-link sidebar-danger">Sign Out</a>
  </div>
</aside>
