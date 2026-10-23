<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAgent();
$db   = \App\Models\Database::getInstance();
$myId = (int)($_SESSION['admin_user_id'] ?? 0);

function sv($db,$sql,$p=[]){try{$s=$db->prepare($sql);$s->execute($p);return $s->fetchColumn();}catch(\Exception $e){return 0;}}
function sa($db,$sql,$p=[]){try{$s=$db->prepare($sql);$s->execute($p);return $s->fetchAll();}catch(\Exception $e){return[];}}

$myLeadsTotal   = (int)sv($db,"SELECT COUNT(*) FROM leads WHERE agent_id=?",[$myId]);
$myLeadsWon     = (int)sv($db,"SELECT COUNT(*) FROM leads WHERE agent_id=? AND pipeline_stage IN ('booked','travelled','completed')",[$myId]);

$myCommPending  = (float)sv($db,"SELECT COALESCE(SUM(commission_amount),0) FROM commissions WHERE agent_id=? AND status='pending'",[$myId]);
$myCommApproved = (float)sv($db,"SELECT COALESCE(SUM(commission_amount),0) FROM commissions WHERE agent_id=? AND status='approved'",[$myId]);
$myCommPaid     = (float)sv($db,"SELECT COALESCE(SUM(commission_amount),0) FROM commissions WHERE agent_id=? AND status='paid'",[$myId]);

$myRecentLeads  = sa($db,"SELECT * FROM leads WHERE agent_id=? ORDER BY created_at DESC LIMIT 6",[$myId]);
$myRecentComms  = sa($db,"SELECT * FROM commissions WHERE agent_id=? ORDER BY created_at DESC LIMIT 5",[$myId]);

$commStatusColors = ['pending'=>'amber','approved'=>'blue','paid'=>'green','cancelled'=>'red'];

$pageTitle = 'My Dashboard';
ob_start();
?>

<div class="admin-metrics-grid" style="margin-bottom:1.5rem">
  <a href="leads.php" style="text-decoration:none">
  <div class="metric-card">
    <span class="metric-label">My Leads</span>
    <span class="metric-value"><?= $myLeadsTotal ?></span>
    <span class="metric-sub"><?= $myLeadsWon ?> booked</span>
  </div></a>
  <div class="metric-card metric-card--dark">
    <span class="metric-label">Commission Paid</span>
    <span class="metric-value">$<?= number_format($myCommPaid,0) ?></span>
    <span class="metric-sub">Lifetime</span>
  </div>
  <a href="commissions.php" style="text-decoration:none">
  <div class="metric-card <?= $myCommPending>0?'metric-card--amber':'' ?>">
    <span class="metric-label">Commission Pending</span>
    <span class="metric-value">$<?= number_format($myCommPending,0) ?></span>
    <span class="metric-sub">$<?= number_format($myCommApproved,0) ?> approved</span>
  </div></a>
</div>

<div class="admin-split-layout" style="display:grid;grid-template-columns:1.3fr 1fr;gap:1.25rem;align-items:start">

<!-- My Leads -->
<div class="admin-card">
  <div class="admin-card-header">
    <h3>My Leads</h3>
    <a href="leads.php" class="admin-btn admin-btn--outline" style="font-size:.65rem">View All →</a>
  </div>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Country</th><th>Stage</th><th>Added</th></tr></thead>
    <tbody>
    <?php foreach($myRecentLeads as $l): ?>
    <tr onclick="window.location='leads.php'" style="cursor:pointer">
      <td>
        <div style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($l['full_name']??'') ?></div>
        <div style="font-size:.68rem;color:var(--gray-500)"><?= htmlspecialchars($l['email']??'') ?></div>
      </td>
      <td style="font-size:.78rem"><?= htmlspecialchars($l['country']??'—') ?></td>
      <td style="font-size:.75rem"><?= htmlspecialchars(str_replace('_',' ',$l['pipeline_stage']??'')) ?></td>
      <td style="font-size:.72rem;color:var(--gray-500)"><?= date('d M Y',strtotime($l['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($myRecentLeads)): ?><tr><td colspan="4" class="admin-empty">No leads yet — add your first one.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Right column -->
<div style="display:flex;flex-direction:column;gap:1.25rem">

  <!-- My Commissions -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3>My Commissions</h3>
      <a href="commissions.php" class="admin-btn admin-btn--outline" style="font-size:.65rem">View All →</a>
    </div>
    <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Client</th><th>Amount</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($myRecentComms as $c):
        $cBadge = $commStatusColors[$c['status']??'pending'] ?? 'amber';
      ?>
      <tr onclick="window.location='commissions.php'" style="cursor:pointer">
        <td style="font-size:.82rem;font-weight:600"><?= htmlspecialchars($c['client_name']??'') ?></td>
        <td style="font-size:.82rem;font-weight:700">$<?= number_format((float)($c['commission_amount']??0),0) ?></td>
        <td><span class="badge-status badge-status--<?= $cBadge ?>"><?= $c['status']??'pending' ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($myRecentComms)): ?><tr><td colspan="3" class="admin-empty">No commissions yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="admin-card">
    <div class="admin-card-header"><h3>Quick Actions</h3></div>
    <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem">
      <a href="leads.php"       class="admin-btn admin-btn--primary" style="justify-content:center">+ Add Lead</a>
      <a href="commissions.php" class="admin-btn admin-btn--outline" style="justify-content:center">Commissions</a>
      <a href="profile.php"     class="admin-btn admin-btn--muted"   style="justify-content:center;grid-column:span 2">Edit Profile</a>
    </div>
  </div>

</div><!-- /right -->
</div><!-- /grid -->

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/agent.php';
