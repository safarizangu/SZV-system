<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

function sv($db,$sql,$p=[]){try{$s=$db->prepare($sql);$s->execute($p);return $s->fetchColumn();}catch(\Exception $e){return 0;}}
function sa($db,$sql,$p=[]){try{$s=$db->prepare($sql);$s->execute($p);return $s->fetchAll();}catch(\Exception $e){return[];}}

$totalBookings  = (int)sv($db,"SELECT COUNT(*) FROM bookings");
$totalRevenue   = (float)sv($db,"SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE payment_status!='cancelled'");
$totalLeads     = (int)sv($db,"SELECT COUNT(*) FROM custom_safari_requests");
$totalQuotes    = (int)sv($db,"SELECT COUNT(*) FROM quotations");
$convRate       = ($totalLeads+$totalBookings)>0 ? round(($totalBookings/($totalLeads+$totalBookings))*100,1) : 0;
$newRequests    = (int)sv($db,"SELECT COUNT(*) FROM custom_safari_requests WHERE status='new'");
$unreadMsgs     = (int)sv($db,"SELECT COUNT(*) FROM contact_messages WHERE is_read=0");
$pendingEmails  = (int)sv($db,"SELECT COUNT(*) FROM email_queue WHERE status='pending'");
$failedEmails   = (int)sv($db,"SELECT COUNT(*) FROM email_queue WHERE status='failed'");
$pendingRem     = (int)sv($db,"SELECT COUNT(*) FROM booking_reminders WHERE status='pending'");
$dueRem         = (int)sv($db,"SELECT COUNT(*) FROM booking_reminders WHERE status='pending' AND send_at<=DATE_ADD(NOW(),INTERVAL 24 HOUR)");

$recentBookings = sa($db,"SELECT * FROM bookings ORDER BY created_at DESC LIMIT 8");
$recentQuotes   = sa($db,"SELECT * FROM quotations ORDER BY created_at DESC LIMIT 5");
$newLeadRows    = sa($db,"SELECT * FROM custom_safari_requests WHERE status='new' ORDER BY created_at DESC LIMIT 5");

// Maps each status to one of admin.css's shared .badge-status--* color
// modifiers, instead of a page-local bg/color hex map — the same
// statuses get re-colored ad-hoc on bookings.php, quotations.php etc.,
// so centralizing the mapping here (and eventually the others) keeps
// "pending" the same shade of amber everywhere it appears.
$statusColors = [
    'pending'      => ['badge'=>'amber','label'=>'Pending'],
    'deposit_paid' => ['badge'=>'blue', 'label'=>'Deposit Paid'],
    'fully_paid'   => ['badge'=>'dark', 'label'=>'Fully Paid'],
    'cancelled'    => ['badge'=>'red',  'label'=>'Cancelled'],
];
$quoteStatusColors = [
    'draft'    => 'gray',
    'sent'     => 'blue',
    'accepted' => 'green',
    'declined' => 'red',
];

$pageTitle = 'Dashboard';
ob_start();
?>

<!-- METRIC CARDS -->
<div class="admin-metrics-grid" style="margin-bottom:1.5rem">
  <div class="metric-card metric-card--dark">
    <span class="metric-label">Total Revenue</span>
    <span class="metric-value">$<?= number_format($totalRevenue,0) ?></span>
    <span class="metric-sub">Confirmed bookings</span>
  </div>
  <a href="bookings.php" style="text-decoration:none">
  <div class="metric-card">
    <span class="metric-label">Bookings</span>
    <span class="metric-value"><?= $totalBookings ?></span>
    <span class="metric-sub">All time</span>
  </div></a>
  <a href="quotations.php" style="text-decoration:none">
  <div class="metric-card">
    <span class="metric-label">Quotations</span>
    <span class="metric-value"><?= $totalQuotes ?></span>
    <span class="metric-sub">Created</span>
  </div></a>
  <a href="custom_requests.php" style="text-decoration:none">
  <div class="metric-card <?= $newRequests>0?'metric-card--amber':'' ?>">
    <span class="metric-label">New Requests</span>
    <span class="metric-value"><?= $newRequests ?></span>
    <span class="metric-sub"><?= $totalLeads ?> total leads</span>
  </div></a>
  <a href="contact_messages.php" style="text-decoration:none">
  <div class="metric-card <?= $unreadMsgs>0?'metric-card--amber':'' ?>">
    <span class="metric-label">Unread Messages</span>
    <span class="metric-value"><?= $unreadMsgs ?></span>
    <span class="metric-sub">Awaiting reply</span>
  </div></a>
  <div class="metric-card">
    <span class="metric-label">Conversion</span>
    <span class="metric-value"><?= $convRate ?>%</span>
    <span class="metric-sub">Requests → bookings</span>
  </div>
</div>

<!-- COMMS HEALTH -->
<div class="admin-stats-grid" style="margin-bottom:1.5rem">
  <a href="email_queue.php" style="text-decoration:none">
  <div class="admin-stat-card <?= $pendingEmails>0?'admin-stat-card--amber':'' ?>">
    <span class="admin-stat-label">Emails Queued</span>
    <span class="admin-stat-value"><?= $pendingEmails ?></span>
    <span class="admin-stat-sub">Pending</span>
  </div></a>
  <a href="email_queue.php" style="text-decoration:none">
  <div class="admin-stat-card <?= $failedEmails>0?'admin-stat-card--red':'' ?>">
    <span class="admin-stat-label">Emails Failed</span>
    <span class="admin-stat-value"><?= $failedEmails ?></span>
    <span class="admin-stat-sub">Need retry</span>
  </div></a>
  <a href="reminders.php" style="text-decoration:none">
  <div class="admin-stat-card">
    <span class="admin-stat-label">Reminders</span>
    <span class="admin-stat-value"><?= $pendingRem ?></span>
    <span class="admin-stat-sub">Scheduled</span>
  </div></a>
  <a href="reminders.php" style="text-decoration:none">
  <div class="admin-stat-card <?= $dueRem>0?'admin-stat-card--blue':'' ?>">
    <span class="admin-stat-label">Due 24h</span>
    <span class="admin-stat-value"><?= $dueRem ?></span>
    <span class="admin-stat-sub">Firing soon</span>
  </div></a>
</div>

<!-- MAIN GRID -->
<div style="display:grid;grid-template-columns:1.3fr 1fr;gap:1.25rem;align-items:start">

<!-- Recent Bookings -->
<div class="admin-card">
  <div class="admin-card-header">
    <h3>Recent Bookings</h3>
    <a href="bookings.php" class="admin-btn admin-btn--outline" style="font-size:.65rem">View All →</a>
  </div>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Ref</th><th>Guest</th><th>Package</th><th>Travel</th><th>Amount</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($recentBookings as $b):
      $sc = $statusColors[$b['payment_status']??'pending'] ?? $statusColors['pending'];
      $travelDate = !empty($b['travel_date']) && $b['travel_date'] !== '0000-00-00'
                    ? date('d M Y',strtotime($b['travel_date'])) : '—';
    ?>
    <tr style="cursor:pointer" onclick="window.location='bookings.php?search=<?= urlencode($b['booking_ref']) ?>'">
      <td><code><?= htmlspecialchars($b['booking_ref']) ?></code></td>
      <td>
        <div style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($b['full_name']??'') ?></div>
        <div style="font-size:.68rem;color:var(--gray-500)"><?= htmlspecialchars($b['email']??'') ?></div>
      </td>
      <td style="font-size:.78rem"><?= htmlspecialchars($b['package_name']??'—') ?></td>
      <td style="font-size:.75rem;white-space:nowrap"><?= $travelDate ?></td>
      <td style="font-weight:700;font-size:.82rem">$<?= number_format((float)($b['total_amount']??0),0) ?></td>
      <td>
        <span class="badge-status badge-status--<?= $sc['badge'] ?>">
          <?= str_replace('_',' ',$b['payment_status']??'pending') ?>
        </span>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($recentBookings)): ?>
    <tr><td colspan="6" class="admin-empty">No bookings yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Right column -->
<div style="display:flex;flex-direction:column;gap:1.25rem">

  <!-- New Requests -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3>New Requests
        <?php if($newRequests>0): ?>
        <span class="nav-badge" style="margin-left:.5rem"><?= $newRequests ?></span>
        <?php endif; ?>
      </h3>
      <a href="custom_requests.php" class="admin-btn admin-btn--outline" style="font-size:.65rem">View All →</a>
    </div>
    <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Name</th><th>Country</th><th>Submitted</th></tr></thead>
      <tbody>
      <?php foreach($newLeadRows as $l): ?>
      <tr onclick="window.location='custom_requests.php'" style="cursor:pointer">
        <td>
          <div style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($l['full_name']??'') ?></div>
          <div style="font-size:.68rem;color:var(--gray-500)"><?= htmlspecialchars($l['package_interest']??'') ?></div>
        </td>
        <td style="font-size:.78rem"><?= htmlspecialchars($l['country']??'—') ?></td>
        <td style="font-size:.72rem;color:var(--gray-500)"><?= date('d M H:i',strtotime($l['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($newLeadRows)): ?><tr><td colspan="3" class="admin-empty">No new requests.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- Recent Quotes -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3>Recent Quotations</h3>
      <div style="display:flex;gap:.4rem">
        <a href="quotation_new.php" class="admin-btn admin-btn--primary" style="font-size:.65rem">+ New</a>
        <a href="quotations.php" class="admin-btn admin-btn--outline" style="font-size:.65rem">All →</a>
      </div>
    </div>
    <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Ref</th><th>Client</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
      <?php
      foreach($recentQuotes as $q):
        $qBadge = $quoteStatusColors[$q['status']??'draft'] ?? 'gray';
      ?>
      <tr onclick="window.location='quotation_new.php?id=<?= $q['id'] ?>'" style="cursor:pointer">
        <td><code><?= htmlspecialchars($q['quote_ref']) ?></code></td>
        <td style="font-size:.82rem;font-weight:600"><?= htmlspecialchars($q['client_name']) ?></td>
        <td style="font-size:.82rem;font-weight:700">$<?= number_format((float)($q['grand_total']??0),0) ?></td>
        <td><span class="badge-status badge-status--<?= $qBadge ?>"><?= $q['status']??'draft' ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($recentQuotes)): ?><tr><td colspan="4" class="admin-empty">No quotations.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="admin-card">
    <div class="admin-card-header"><h3>Quick Actions</h3></div>
    <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem">
      <a href="quotation_new.php" class="admin-btn admin-btn--primary" style="justify-content:center">+ Quotation</a>
      <a href="bookings.php"      class="admin-btn admin-btn--outline" style="justify-content:center">Bookings</a>
      <a href="gallery.php"       class="admin-btn admin-btn--outline" style="justify-content:center">Gallery</a>
      <a href="itineraries.php"   class="admin-btn admin-btn--outline" style="justify-content:center">Itineraries</a>
      <a href="custom_requests.php" class="admin-btn admin-btn--amber" style="justify-content:center">Requests</a>
      <a href="packages.php"      class="admin-btn admin-btn--muted"   style="justify-content:center">Packages</a>
    </div>
  </div>

</div><!-- /right -->
</div><!-- /grid -->

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
