<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAgent();
$db   = \App\Models\Database::getInstance();
$myId = (int)($_SESSION['admin_user_id'] ?? 0);

// Own commission records only
$comms = [];
try {
    $stmt = $db->prepare("SELECT c.*, u.full_name as agent_name, u.email as agent_email,
        u.commission_rate as agent_default_rate
        FROM commissions c
        LEFT JOIN admin_users u ON u.id=c.agent_id
        WHERE c.agent_id=?
        ORDER BY c.created_at DESC LIMIT 200");
    $stmt->execute([$myId]);
    $comms = $stmt->fetchAll();
} catch(\Exception $e){ error_log($e->getMessage()); }

// Own summary
$agentSummary = null;
try {
    $stmt = $db->prepare("SELECT u.id, u.full_name, u.username, u.commission_rate,
        COUNT(l.id) as total_leads,
        SUM(CASE WHEN l.pipeline_stage IN ('booked','travelled','completed') THEN 1 ELSE 0 END) as converted,
        COALESCE(SUM(c.commission_amount),0) as total_commission,
        COALESCE(SUM(CASE WHEN c.status='paid' THEN c.commission_amount ELSE 0 END),0) as paid_commission,
        COALESCE(SUM(CASE WHEN c.status='pending' THEN c.commission_amount ELSE 0 END),0) as pending_commission
        FROM admin_users u
        LEFT JOIN leads l ON l.agent_id=u.id
        LEFT JOIN commissions c ON c.agent_id=u.id
        WHERE u.id=?
        GROUP BY u.id");
    $stmt->execute([$myId]);
    $agentSummary = $stmt->fetch();
} catch(\Exception $e){ error_log($e->getMessage()); }

$statusColors = [
    'pending'   => ['badge'=>'amber'],
    'approved'  => ['badge'=>'blue'],
    'paid'      => ['badge'=>'dark'],
    'cancelled' => ['badge'=>'red'],
];

$pageTitle = 'My Commissions';
ob_start();
?>

<?php if ($agentSummary): $a = $agentSummary; ?>
<div class="admin-card" style="margin-bottom:1.5rem;max-width:420px">
  <div class="admin-card-header">
    <div>
      <h3 style="font-size:.9rem"><?= htmlspecialchars($a['full_name']??$a['username']) ?></h3>
      <span style="font-size:.65rem;color:var(--gray-500)">@<?= htmlspecialchars($a['username']) ?></span>
    </div>
    <span style="font-size:.78rem;font-weight:700"><?= $a['commission_rate'] ?>%</span>
  </div>
  <div class="admin-card-body" style="padding:.75rem 1rem">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;text-align:center">
      <div style="background:var(--gray-50);padding:.5rem">
        <div style="font-size:1.1rem;font-weight:700;font-family:var(--font-disp)"><?= $a['total_leads'] ?></div>
        <div style="font-size:.58rem;color:var(--gray-500);text-transform:uppercase;letter-spacing:.08em">Leads</div>
      </div>
      <div style="background:var(--green-light);padding:.5rem">
        <div style="font-size:1.1rem;font-weight:700;font-family:var(--font-disp);color:var(--green)"><?= $a['converted'] ?></div>
        <div style="font-size:.58rem;color:var(--green);text-transform:uppercase;letter-spacing:.08em">Booked</div>
      </div>
      <div style="background:var(--amber-light);padding:.5rem">
        <div style="font-size:1rem;font-weight:700;font-family:var(--font-disp);color:var(--amber)">
          $<?= number_format($a['pending_commission'],0) ?>
        </div>
        <div style="font-size:.58rem;color:var(--amber);text-transform:uppercase;letter-spacing:.08em">Due</div>
      </div>
    </div>
    <div style="margin-top:.6rem;padding-top:.6rem;border-top:1px solid var(--gray-100);display:flex;justify-content:space-between;font-size:.75rem">
      <span style="color:var(--gray-500)">Total earned</span>
      <span style="font-weight:700;color:var(--green)">$<?= number_format($a['total_commission'],0) ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.75rem;margin-top:.25rem">
      <span style="color:var(--gray-500)">Paid out</span>
      <span style="font-weight:600">$<?= number_format($a['paid_commission'],0) ?></span>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Commission records -->
<div class="admin-card">
  <div class="admin-card-header">
    <h3>My Commission Records</h3>
    <div style="font-size:.72rem;color:var(--gray-500)">
      Total pending: <strong style="color:var(--amber)">$<?= number_format(array_sum(array_column(array_filter($comms,fn($c)=>$c['status']==='pending'),'commission_amount')),0) ?></strong>
      &nbsp;·&nbsp;
      Total paid: <strong style="color:var(--green)">$<?= number_format(array_sum(array_column(array_filter($comms,fn($c)=>$c['status']==='paid'),'commission_amount')),0) ?></strong>
    </div>
  </div>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr>
      <th>Client</th><th>Package</th><th>Booking Value</th><th>Rate</th><th>Commission</th><th>Status</th><th>Date</th>
    </tr></thead>
    <tbody>
    <?php foreach($comms as $c):
      $sc = $statusColors[$c['status']] ?? $statusColors['pending'];
    ?>
    <tr>
      <td style="font-size:.82rem">
        <?= htmlspecialchars($c['client_name']??'—') ?>
        <?php if($c['booking_ref']): ?>
        <div style="font-size:.65rem;color:var(--gray-500)"><?= htmlspecialchars($c['booking_ref']) ?></div>
        <?php endif; ?>
      </td>
      <td style="font-size:.78rem"><?= htmlspecialchars($c['package_name']??'—') ?></td>
      <td style="font-weight:600;font-size:.82rem">$<?= number_format((float)$c['booking_value'],0) ?></td>
      <td style="font-size:.78rem"><?= $c['commission_rate'] ?>%</td>
      <td style="font-weight:700;font-size:.9rem;color:<?= (float)$c['commission_amount']>0?'var(--green)':'var(--gray-500)' ?>">
        $<?= number_format((float)$c['commission_amount'],2) ?>
      </td>
      <td><span class="badge-status badge-status--<?= $sc['badge'] ?>"><?= ucfirst($c['status']) ?></span></td>
      <td style="font-size:.72rem;color:var(--gray-500)"><?= date('d M Y',strtotime($c['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($comms)): ?>
    <tr><td colspan="7" class="admin-empty">No commission records yet. Commissions are created when a lead is moved to "Booked" stage.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/agent.php';
