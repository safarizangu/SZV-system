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

$pendingEmails  = 0; $failedEmails = 0;
$pendingReminders = 0; $dueReminders = 0;
try { $pendingEmails  = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status='pending'")->fetchColumn(); } catch(\Exception $e) {}
try { $failedEmails   = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status='failed'")->fetchColumn(); }  catch(\Exception $e) {}
try { $pendingReminders=(int)$db->query("SELECT COUNT(*) FROM booking_reminders WHERE status='pending'")->fetchColumn(); } catch(\Exception $e) {}
try { $dueReminders   = (int)$db->query("SELECT COUNT(*) FROM booking_reminders WHERE status='pending' AND send_at<=DATE_ADD(NOW(),INTERVAL 24 HOUR)")->fetchColumn(); } catch(\Exception $e) {}

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

  <!-- Comms Health Row -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem">
    <a href="email_queue.php?status=pending" style="text-decoration:none;display:block;padding:1rem 1.25rem;border:1.5px solid <?= $pendingEmails>0?'#92400e':'#e5e7eb' ?>;background:<?= $pendingEmails>0?'#fef3e2':'#fff' ?>">
      <div style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#9ca3af">Emails Queued</div>
      <div style="font-family:Georgia,serif;font-size:1.5rem;font-weight:500;color:<?= $pendingEmails>0?'#92400e':'#0a0a0a' ?>;margin-top:.25rem"><?= $pendingEmails ?></div>
      <div style="font-size:.68rem;color:#9ca3af;margin-top:.1rem">Pending send</div>
    </a>
    <a href="email_queue.php?status=failed" style="text-decoration:none;display:block;padding:1rem 1.25rem;border:1.5px solid <?= $failedEmails>0?'#dc2626':'#e5e7eb' ?>;background:<?= $failedEmails>0?'#fef2f2':'#fff' ?>">
      <div style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#9ca3af">Emails Failed</div>
      <div style="font-family:Georgia,serif;font-size:1.5rem;font-weight:500;color:<?= $failedEmails>0?'#dc2626':'#0a0a0a' ?>;margin-top:.25rem"><?= $failedEmails ?></div>
      <div style="font-size:.68rem;color:#9ca3af;margin-top:.1rem">Need retry</div>
    </a>
    <a href="reminders.php" style="text-decoration:none;display:block;padding:1rem 1.25rem;border:1.5px solid #e5e7eb;background:#fff">
      <div style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#9ca3af">Reminders</div>
      <div style="font-family:Georgia,serif;font-size:1.5rem;font-weight:500;color:#0a0a0a;margin-top:.25rem"><?= $pendingReminders ?></div>
      <div style="font-size:.68rem;color:#9ca3af;margin-top:.1rem">Scheduled</div>
    </a>
    <a href="reminders.php" style="text-decoration:none;display:block;padding:1rem 1.25rem;border:1.5px solid <?= $dueReminders>0?'#1d4ed8':'#e5e7eb' ?>;background:<?= $dueReminders>0?'#eff6ff':'#fff' ?>">
      <div style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#9ca3af">Due Next 24h</div>
      <div style="font-family:Georgia,serif;font-size:1.5rem;font-weight:500;color:<?= $dueReminders>0?'#1d4ed8':'#0a0a0a' ?>;margin-top:.25rem"><?= $dueReminders ?></div>
      <div style="font-size:.68rem;color:#9ca3af;margin-top:.1rem">Reminders</div>
    </a>
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
