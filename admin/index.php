<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Helpers\Formatter;
AuthMiddleware::requireAdmin();

$db = \App\Models\Database::getInstance();

$totalBookings  = 0;
$totalRevenue   = 0.0;
$totalLeads     = 0;
$recentBookings = [];
$monthlyRevenue = [];
$popularPkgs    = [];
$newLeads       = [];
$convRate       = 0;

try { $totalBookings = (int)$db->query("SELECT COUNT(*) FROM bookings")->fetchColumn(); }
catch(Exception $e) { error_log('[SZV] '.$e->getMessage()); }

try { $totalRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE payment_status != 'cancelled'")->fetchColumn(); }
catch(Exception $e) { error_log('[SZV] '.$e->getMessage()); }

try { $totalLeads = (int)$db->query("SELECT COUNT(*) FROM custom_safari_requests")->fetchColumn(); }
catch(Exception $e) { error_log('[SZV] '.$e->getMessage()); }

$convRate = ($totalLeads + $totalBookings) > 0
    ? round(($totalBookings / ($totalLeads + $totalBookings)) * 100, 1) : 0;

try {
    $recentBookings = $db->query(
        "SELECT * FROM bookings ORDER BY created_at DESC LIMIT 8"
    )->fetchAll();
} catch(Exception $e) { error_log('[SZV] '.$e->getMessage()); }

try {
    $monthlyRevenue = $db->query("
        SELECT DATE_FORMAT(MIN(created_at),'%b %Y') AS month,
               SUM(total_amount) AS revenue, COUNT(*) AS cnt
        FROM bookings
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at,'%Y-%m')
        ORDER BY DATE_FORMAT(created_at,'%Y-%m') ASC
    ")->fetchAll();
} catch(Exception $e) { error_log('[SZV] monthly: '.$e->getMessage()); }

try {
    $popularPkgs = $db->query("
        SELECT package_name, COUNT(*) AS bookings, SUM(total_amount) AS revenue
        FROM bookings
        WHERE package_name IS NOT NULL
        GROUP BY package_name
        ORDER BY COUNT(*) DESC LIMIT 6
    ")->fetchAll();
} catch(Exception $e) { error_log('[SZV] packages: '.$e->getMessage()); }

try {
    $newLeads = $db->query(
        "SELECT * FROM custom_safari_requests WHERE status='new' ORDER BY created_at DESC LIMIT 5"
    )->fetchAll();
} catch(Exception $e) { error_log('[SZV] '.$e->getMessage()); }

$pageTitle = 'Dashboard — Safari Zangu Admin';
ob_start();
?>
<div class="admin-page">
  <div class="metrics-grid">
    <div class="metric-card">
      <span class="metric-label">Total Bookings</span>
      <span class="metric-value"><?= $totalBookings ?></span>
      <span class="metric-sub">All time</span>
    </div>
    <div class="metric-card metric-card--revenue">
      <span class="metric-label">Total Revenue</span>
      <span class="metric-value">$<?= number_format($totalRevenue,0) ?></span>
      <span class="metric-sub">USD confirmed</span>
    </div>
    <div class="metric-card">
      <span class="metric-label">Safari Requests</span>
      <span class="metric-value"><?= $totalLeads ?></span>
      <span class="metric-sub">Custom enquiries</span>
    </div>
    <div class="metric-card">
      <span class="metric-label">Conversion Rate</span>
      <span class="metric-value"><?= $convRate ?>%</span>
      <span class="metric-sub">Requests to bookings</span>
    </div>
  </div>

  <div class="admin-grid-2">
    <div class="admin-card">
      <div class="admin-card-header">
        <h2>Recent Bookings</h2>
        <a href="bookings.php" class="admin-link">View all &rarr;</a>
      </div>
      <table class="admin-table">
        <thead><tr><th>Ref</th><th>Guest</th><th>Package</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentBookings as $b): ?>
          <tr>
            <td><code><?= htmlspecialchars($b['booking_ref']) ?></code></td>
            <td><?= htmlspecialchars($b['full_name']) ?></td>
            <td><?= htmlspecialchars($b['package_name'] ?? '—') ?></td>
            <td><?= date('d M Y', strtotime($b['travel_date'])) ?></td>
            <td>$<?= number_format((float)$b['total_amount'],0) ?></td>
            <td><?= Formatter::bookingStatusBadge($b['payment_status'] ?? 'pending') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($recentBookings)): ?>
          <tr><td colspan="6" class="empty-state">No bookings yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="admin-card">
      <div class="admin-card-header">
        <h2>New Safari Requests</h2>
        <a href="custom_requests.php" class="admin-link">View all &rarr;</a>
      </div>
      <?php if (!empty($newLeads)): ?>
        <div class="lead-list">
        <?php foreach ($newLeads as $l): ?>
          <div class="lead-item">
            <div class="lead-name"><?= htmlspecialchars($l['full_name']) ?></div>
            <div class="lead-meta"><?= htmlspecialchars($l['destinations'] ?? '') ?></div>
            <div class="lead-date"><?= date('d M, H:i', strtotime($l['created_at'])) ?></div>
            <span class="badge badge-new">New</span>
          </div>
        <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="empty-state">No new requests.</p>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($popularPkgs)): ?>
  <div class="admin-card" style="margin-top:2rem">
    <div class="admin-card-header"><h2>Package Performance</h2></div>
    <table class="admin-table">
      <thead><tr><th>Package</th><th>Bookings</th><th>Revenue</th></tr></thead>
      <tbody>
      <?php foreach ($popularPkgs as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['package_name']) ?></td>
          <td><?= $p['bookings'] ?></td>
          <td>$<?= number_format((float)$p['revenue'],0) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php
// Inject live-refresh script
$liveScript = <<<HTML
<script>
(function() {
  var REFRESH_INTERVAL = 60000; // 60 seconds
  var indicators = {
    totalBookings: document.querySelector('.metric-value'),
  };

  function refreshStats() {
    fetch((window.BASE_PATH||'')+'/php/dashboard_stats.php', { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        // Update any element with data-stat attribute
        document.querySelectorAll('[data-stat]').forEach(function(el) {
          var key = el.dataset.stat;
          if (d[key] !== undefined) {
            el.textContent = typeof d[key] === 'number' && key.includes('revenue')
              ? d.formatted_revenue : d[key];
          }
        });
        // Update last-refreshed indicator
        var ts = document.getElementById('last-refreshed');
        if (ts) ts.textContent = 'Updated ' + d.timestamp;
        // Update sidebar unread badge
        var badge = document.querySelector('.nav-badge');
        if (badge && d.messages_unread > 0) badge.textContent = d.messages_unread;
      })
      .catch(function() {});
  }

  // Auto-refresh every 60s
  setInterval(refreshStats, REFRESH_INTERVAL);

  // Show last-refreshed indicator
  var topbar = document.querySelector('.admin-topbar-right');
  if (topbar) {
    var ts = document.createElement('span');
    ts.id = 'last-refreshed';
    ts.style.cssText = 'font-size:.62rem;color:#9ca3af';
    topbar.prepend(ts);
  }
})();
</script>
HTML;
$content = ob_get_clean();
$content .= $liveScript;
require_once __DIR__ . '/../layouts/admin.php';
