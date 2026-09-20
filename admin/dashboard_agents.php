<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
if (!\App\Middleware\AuthMiddleware::isAdminRole()) {
    header('Location: index.php?msg=' . urlencode('Permission denied.'));
    exit;
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Agent Performance Dashboard';

// admin_users.target_monthly is a real, existing column with no UI
// anywhere to set or use it — the separate agent_targets table would
// need someone to create a new row every month by hand, which nothing
// in this codebase does either. Using the simpler single field here:
// one target per agent, actuals always computed fresh for the current
// month rather than a manually-kept-in-sync counter.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_target'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $agentId = (int)($_POST['agent_id'] ?? 0);
    $target  = max(0, (float)($_POST['target_monthly'] ?? 0));
    try { $db->prepare("UPDATE admin_users SET target_monthly=? WHERE id=?")->execute([$target, $agentId]); }
    catch (\Exception $e) {}
    header('Location: dashboard_agents.php');
    exit;
}

$agents = [];
try { $agents = $db->query("SELECT id, full_name, username, commission_rate, target_monthly FROM admin_users WHERE role='agent' AND is_active=1 ORDER BY full_name")->fetchAll(); }
catch (\Exception $e) {}

$monthStart = date('Y-m-01');
foreach ($agents as &$a) {
    $aid = (int)$a['id'];
    try {
        $s = $db->prepare("SELECT COUNT(*) FROM leads WHERE agent_id=? AND created_at >= ?");
        $s->execute([$aid, $monthStart]);
        $a['leads_mtd'] = (int)$s->fetchColumn();
    } catch (\Exception $e) { $a['leads_mtd'] = 0; }
    try {
        $s = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(booking_value),0) v FROM commissions WHERE agent_id=? AND created_at >= ?");
        $s->execute([$aid, $monthStart]);
        $row = $s->fetch();
        $a['bookings_mtd'] = (int)($row['c'] ?? 0);
        $a['value_mtd']    = (float)($row['v'] ?? 0);
    } catch (\Exception $e) { $a['bookings_mtd'] = 0; $a['value_mtd'] = 0; }
    try {
        $s = $db->prepare("SELECT status, COALESCE(SUM(commission_amount),0) v FROM commissions WHERE agent_id=? GROUP BY status");
        $s->execute([$aid]);
        $a['commission_by_status'] = [];
        foreach ($s->fetchAll() as $r) $a['commission_by_status'][$r['status']] = (float)$r['v'];
    } catch (\Exception $e) { $a['commission_by_status'] = []; }
}
unset($a);

ob_start();
?>
<p style="color:var(--gray-500);font-size:.85rem;margin:-.5rem 0 1.25rem">
  Target is a single monthly figure per agent, editable below. Actuals are always computed live for the current
  month from real leads/commissions data — never a manually-updated counter that can drift out of sync.
</p>

<?php if (empty($agents)): ?>
<div class="admin-card"><div class="admin-card-body">
  <p style="color:var(--gray-500)">No active agent-role users yet. Create one in <a href="users.php">Users</a> with role "Agent" to see performance data here.</p>
</div></div>
<?php else: ?>

<?php foreach ($agents as $a): ?>
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <h3><?= htmlspecialchars($a['full_name'] ?: $a['username']) ?></h3>
    <span style="font-size:.72rem;color:var(--gray-500)">Commission rate: <?= $a['commission_rate'] ? number_format($a['commission_rate'],1).'%' : '—' ?></span>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
      <div><div style="font-size:.65rem;text-transform:uppercase;color:var(--gray-500)">Leads (MTD)</div><div style="font-size:1.3rem;font-weight:700"><?= (int)$a['leads_mtd'] ?></div></div>
      <div><div style="font-size:.65rem;text-transform:uppercase;color:var(--gray-500)">Bookings (MTD)</div><div style="font-size:1.3rem;font-weight:700"><?= (int)$a['bookings_mtd'] ?></div></div>
      <div><div style="font-size:.65rem;text-transform:uppercase;color:var(--gray-500)">Booking Value (MTD)</div><div style="font-size:1.3rem;font-weight:700">$<?= number_format($a['value_mtd'],0) ?></div></div>
      <div>
        <div style="font-size:.65rem;text-transform:uppercase;color:var(--gray-500)">Target (Monthly)</div>
        <form method="POST" style="display:flex;gap:.4rem;align-items:center">
          <?= \App\Middleware\CsrfMiddleware::field() ?>
          <input type="hidden" name="save_target" value="1">
          <input type="hidden" name="agent_id" value="<?= $a['id'] ?>">
          <span>$</span><input type="number" step="1" min="0" name="target_monthly" value="<?= (float)$a['target_monthly'] ?>" class="admin-input" style="width:100px;padding:.3rem;font-size:.85rem">
          <button class="admin-btn admin-btn--outline" style="font-size:.65rem">Save</button>
        </form>
      </div>
    </div>
    <?php if ($a['target_monthly'] > 0): $pct = min(100, round($a['value_mtd'] / $a['target_monthly'] * 100)); ?>
    <div style="background:var(--gray-100);height:10px;border-radius:5px;overflow:hidden">
      <div style="width:<?= $pct ?>%;background:<?= $pct >= 100 ? '#16a34a' : (($pct >= 60) ? 'var(--black)' : '#b45309') ?>;height:100%"></div>
    </div>
    <div style="font-size:.72rem;color:var(--gray-500);margin-top:.3rem"><?= $pct ?>% of monthly target</div>
    <?php endif; ?>
    <div style="margin-top:1rem;display:flex;gap:1.5rem;font-size:.8rem">
      <span>Pending commission: <strong>$<?= number_format($a['commission_by_status']['pending'] ?? 0,0) ?></strong></span>
      <span>Approved: <strong>$<?= number_format($a['commission_by_status']['approved'] ?? 0,0) ?></strong></span>
      <span>Paid: <strong>$<?= number_format($a['commission_by_status']['paid'] ?? 0,0) ?></strong></span>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
