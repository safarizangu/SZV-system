<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Mail\Mailer;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$myId = (int)($_SESSION['admin_user_id'] ?? 0);
// Agents see and can view their own commission records (previously
// blocked from this page entirely) but can't change a status or rate —
// approving/marking your own commission "paid" is a self-approval
// conflict, and rate-setting is a compensation decision reserved for
// admin/superadmin specifically, same standard as Settings pages even
// though this page itself lives in Operations.
$isAgentUser = AuthMiddleware::isAgentRole();
$isAdminUser = AuthMiddleware::isAdminRole();
$pageTitle = 'Commission Management';

// Update commission status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_commission'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    if ($isAgentUser) { header('Location: commissions.php?msg='.urlencode('Permission denied.')); exit; }
    // Email wiring for commission status changes
    try {
        $id     = (int)$_POST['commission_id'];
        $status = $_POST['status'];
        $allowed = ['pending','approved','paid','cancelled'];
        if (in_array($status, $allowed, true)) {
            $db->prepare("UPDATE commissions SET status=?, updated_at=NOW() WHERE id=?")->execute([$status,$id]);
            if ($status === 'approved') {
                // ── Email: commission_approved → agent ───────────────────
                try {
                    $cs = $db->prepare("SELECT c.*, u.full_name as agent_name, u.email as agent_email
                        FROM commissions c LEFT JOIN admin_users u ON u.id=c.agent_id WHERE c.id=?");
                    $cs->execute([$id]);
                    $comm = $cs->fetch();
                    if ($comm && $comm['agent_email']) {
                        Mailer::queue('commission_approved', [
                            'agent_name'       => $comm['agent_name'],
                            'client_name'      => $comm['client_name'],
                            'commission_amount'=> (float)$comm['commission_amount'],
                            'to_email'         => $comm['agent_email'],
                            'to_name'          => $comm['agent_name'],
                        ]);
                    }
                } catch(\Exception $e) { error_log('[Commission Approved Email] '.$e->getMessage()); }
            }

            if ($status === 'paid') {
                $db->prepare("UPDATE commissions SET paid_at=NOW() WHERE id=?")->execute([$id]);
                $db->prepare("UPDATE leads SET commission_status='paid' WHERE id=(SELECT lead_id FROM commissions WHERE id=?)")->execute([$id]);
                // ── Email: commission_paid → agent ────────────────────────
                try {
                    $cs = $db->prepare("SELECT c.*, u.full_name as agent_name, u.email as agent_email
                        FROM commissions c LEFT JOIN admin_users u ON u.id=c.agent_id WHERE c.id=?");
                    $cs->execute([$id]);
                    $comm = $cs->fetch();
                    if ($comm && $comm['agent_email']) {
                        Mailer::queue('commission_paid', [
                            'agent_name'       => $comm['agent_name'],
                            'client_name'      => $comm['client_name'],
                            'commission_amount'=> (float)$comm['commission_amount'],
                            'to_email'         => $comm['agent_email'],
                            'to_name'          => $comm['agent_name'],
                        ]);
                    }
                } catch(\Exception $e) { error_log('[Commission Paid Email] '.$e->getMessage()); }
            }
        }
    } catch(\Exception $e){ error_log($e->getMessage()); }
    header('Location: commissions.php?msg='.urlencode('Updated.')); exit;
}

// Update agent commission rate — admin/superadmin only, a compensation
// decision, not something staff's broader Operations access extends to.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_agent_rate'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    if (!$isAdminUser) { header('Location: commissions.php?msg='.urlencode('Permission denied.')); exit; }
    try {
        $db->prepare("UPDATE admin_users SET commission_rate=? WHERE id=?")
           ->execute([(float)$_POST['rate'], (int)$_POST['agent_id']]);
    } catch(\Exception $e){ error_log($e->getMessage()); }
    header('Location: commissions.php?msg='.urlencode('Rate updated.')); exit;
}

$msg = htmlspecialchars($_GET['msg'] ?? '');

// Load commissions — agents see only their own
$comms = [];
try {
    $commsSql = "SELECT c.*, u.full_name as agent_name, u.email as agent_email,
        u.commission_rate as agent_default_rate
        FROM commissions c
        LEFT JOIN admin_users u ON u.id=c.agent_id"
        . ($isAgentUser ? " WHERE c.agent_id=?" : "")
        . " ORDER BY c.created_at DESC LIMIT 200";
    $stmt = $db->prepare($commsSql);
    $stmt->execute($isAgentUser ? [$myId] : []);
    $comms = $stmt->fetchAll();
} catch(\Exception $e){ error_log($e->getMessage()); }

// Agent summary — agents see only their own card
$agentSummary = [];
try {
    $summarySql = "SELECT u.id, u.full_name, u.username, u.commission_rate,
        COUNT(l.id) as total_leads,
        SUM(CASE WHEN l.pipeline_stage IN ('booked','travelled','completed') THEN 1 ELSE 0 END) as converted,
        COALESCE(SUM(c.commission_amount),0) as total_commission,
        COALESCE(SUM(CASE WHEN c.status='paid' THEN c.commission_amount ELSE 0 END),0) as paid_commission,
        COALESCE(SUM(CASE WHEN c.status='pending' THEN c.commission_amount ELSE 0 END),0) as pending_commission
        FROM admin_users u
        LEFT JOIN leads l ON l.agent_id=u.id
        LEFT JOIN commissions c ON c.agent_id=u.id"
        . ($isAgentUser ? " WHERE u.id=?" : "")
        . " GROUP BY u.id ORDER BY total_commission DESC";
    $stmt = $db->prepare($summarySql);
    $stmt->execute($isAgentUser ? [$myId] : []);
    $agentSummary = $stmt->fetchAll();
} catch(\Exception $e){ error_log($e->getMessage()); }

// 'paid' is a final settled state — same semantics as bookings.php's
// "Fully Paid", which is standardized to the dark badge, not green.
$statusColors = [
    'pending'   => ['badge'=>'amber'],
    'approved'  => ['badge'=>'blue'],
    'paid'      => ['badge'=>'dark'],
    'cancelled' => ['badge'=>'red'],
];
ob_start();
?>
<?php if($msg): ?><div class="admin-alert admin-alert--success"><?= $msg ?></div><?php endif; ?>

<!-- Agent summary cards -->
<div style="margin-bottom:2rem">
  <h2 style="font-family:var(--font-disp);font-size:1.1rem;font-weight:500;margin-bottom:1rem"><?= $isAgentUser ? 'My Performance' : 'Agent Performance' ?></h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem">
  <?php foreach($agentSummary as $a): ?>
  <div class="admin-card">
    <div class="admin-card-header">
      <div>
        <h3 style="font-size:.9rem"><?= htmlspecialchars($a['full_name']??$a['username']) ?></h3>
        <span style="font-size:.65rem;color:var(--gray-500)">@<?= htmlspecialchars($a['username']) ?></span>
      </div>
      <?php if ($isAdminUser): ?>
      <form method="POST" style="display:flex;gap:.35rem;align-items:center">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_agent_rate" value="1">
        <input type="hidden" name="agent_id" value="<?= $a['id'] ?>">
        <input type="number" name="rate" value="<?= $a['commission_rate'] ?>" step="0.5" min="0" max="50"
               style="width:60px;border:1.5px solid var(--gray-200);padding:.25rem .35rem;font-size:.75rem;text-align:center">
        <span style="font-size:.72rem;color:var(--gray-500)">%</span>
        <button class="admin-btn admin-btn--outline" style="font-size:.6rem;padding:.2rem .45rem">Set</button>
      </form>
      <?php else: ?>
      <span style="font-size:.78rem;font-weight:700"><?= $a['commission_rate'] ?>%</span>
      <?php endif; ?>
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
  <?php endforeach; ?>
  </div>
</div>

<!-- Commission records -->
<div class="admin-card">
  <div class="admin-card-header">
    <h3><?= $isAgentUser ? 'My Commission Records' : 'All Commission Records' ?></h3>
    <div style="font-size:.72rem;color:var(--gray-500)">
      Total pending: <strong style="color:var(--amber)">$<?= number_format(array_sum(array_column(array_filter($comms,fn($c)=>$c['status']==='pending'),'commission_amount')),0) ?></strong>
      &nbsp;·&nbsp;
      Total paid: <strong style="color:var(--green)">$<?= number_format(array_sum(array_column(array_filter($comms,fn($c)=>$c['status']==='paid'),'commission_amount')),0) ?></strong>
    </div>
  </div>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr>
      <th>Agent</th><th>Client</th><th>Package</th>
      <th>Booking Value</th><th>Rate</th><th>Commission</th>
      <th>Status</th><th>Date</th><th>Action</th>
    </tr></thead>
    <tbody>
    <?php foreach($comms as $c):
      $sc = $statusColors[$c['status']] ?? $statusColors['pending'];
    ?>
    <tr>
      <td style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($c['agent_name']??'—') ?></td>
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
      <td>
        <span class="badge-status badge-status--<?= $sc['badge'] ?>"><?= ucfirst($c['status']) ?></span>
      </td>
      <td style="font-size:.72rem;color:var(--gray-500)"><?= date('d M Y',strtotime($c['created_at'])) ?></td>
      <td>
        <?php if ($isAgentUser): ?>
        <span style="font-size:.68rem;color:var(--gray-500)">View only</span>
        <?php else: ?>
        <form method="POST" style="display:flex;gap:.3rem">
          <?= \App\Middleware\CsrfMiddleware::field() ?>
          <input type="hidden" name="update_commission" value="1">
          <input type="hidden" name="commission_id" value="<?= $c['id'] ?>">
          <select name="status" class="admin-input" style="font-size:.65rem;padding:.22rem .4rem;width:auto">
            <?php foreach(['pending','approved','paid','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $c['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="admin-btn admin-btn--outline" style="font-size:.6rem;padding:.22rem .4rem">Update</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($comms)): ?>
    <tr><td colspan="9" class="admin-empty">No commission records yet. Commissions are created when a lead is moved to "Booked" stage.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
