<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'dashboard_fleet.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Suppliers & Fleet Dashboard';

// ── Supplier breakdown ──────────────────────────────────────────
$supplierByCategory = [];
try {
    foreach ($db->query("SELECT category, COUNT(*) c, SUM(is_active) active FROM suppliers GROUP BY category")->fetchAll() as $r) {
        $supplierByCategory[$r['category']] = ['total' => (int)$r['c'], 'active' => (int)$r['active']];
    }
} catch (\Exception $e) {}

// ── Vehicle utilization — assignments this month ────────────────
$vehicleUtil = [];
try {
    $vehicleUtil = $db->query("
        SELECT v.id, v.name, v.capacity, COUNT(ba.id) AS assignments
        FROM vehicles v
        LEFT JOIN booking_assignments ba ON ba.vehicle_id = v.id
            AND ba.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE v.is_active = 1
        GROUP BY v.id ORDER BY assignments DESC, v.name
    ")->fetchAll();
} catch (\Exception $e) {}

// ── Guide/driver utilization — assignments this month ────────────
$staffUtil = [];
try {
    $staffUtil = $db->query("
        SELECT st.id, st.full_name, st.role, COUNT(ba.id) AS assignments
        FROM staff st
        LEFT JOIN booking_assignments ba ON ba.staff_id = st.id
            AND ba.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE st.is_active = 1
        GROUP BY st.id ORDER BY assignments DESC, st.full_name
    ")->fetchAll();
} catch (\Exception $e) {}

$categoryLabels = ['accommodation'=>'Accommodation','park_operator'=>'Park Operator','activity_operator'=>'Activity Operator','transport'=>'Transport','other'=>'Other'];

ob_start();
?>
<p style="color:var(--gray-500);font-size:.85rem;margin:-.5rem 0 1.25rem">
  Supplier directory at a glance, plus guide/vehicle assignment load over the last 30 days — an even spread here is
  healthy; one name doing most of the trips is a bus-factor risk worth knowing about.
</p>

<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3>Suppliers by Category</h3><a href="suppliers.php" class="admin-btn admin-btn--outline" style="font-size:.68rem">Manage →</a></div>
  <div class="admin-card-body">
    <?php if (empty($supplierByCategory)): ?>
    <p style="color:var(--gray-500);font-size:.85rem">No suppliers on file yet.</p>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem">
      <?php foreach ($categoryLabels as $key => $label): $d = $supplierByCategory[$key] ?? ['total'=>0,'active'=>0]; ?>
      <div style="border:1px solid var(--gray-200);padding:.85rem">
        <div style="font-size:.65rem;text-transform:uppercase;color:var(--gray-500)"><?= $label ?></div>
        <div style="font-size:1.4rem;font-weight:700;margin-top:.2rem"><?= $d['total'] ?></div>
        <div style="font-size:.7rem;color:var(--gray-500)"><?= $d['active'] ?> active</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">
  <div class="admin-card">
    <div class="admin-card-header"><h3>Vehicle Utilization <span style="font-weight:600;font-size:.7rem;color:var(--gray-500)">(30 days)</span></h3></div>
    <div class="admin-card-body">
      <?php if (empty($vehicleUtil)): ?>
      <p style="color:var(--gray-500);font-size:.85rem">No active vehicles.</p>
      <?php else: $maxV = max(1, max(array_column($vehicleUtil, 'assignments'))); foreach ($vehicleUtil as $v): $pct = round($v['assignments'] / $maxV * 100); ?>
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem">
        <div style="width:120px;font-size:.78rem;color:var(--gray-700);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($v['name']) ?></div>
        <div style="flex:1;background:var(--gray-100);height:16px"><div style="width:<?= max(2,$pct) ?>%;background:var(--black);height:100%"></div></div>
        <div style="width:25px;text-align:right;font-weight:700;font-size:.8rem"><?= $v['assignments'] ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Guide/Driver Utilization <span style="font-weight:600;font-size:.7rem;color:var(--gray-500)">(30 days)</span></h3></div>
    <div class="admin-card-body">
      <?php if (empty($staffUtil)): ?>
      <p style="color:var(--gray-500);font-size:.85rem">No active guides/drivers. <a href="staff.php">Add one →</a></p>
      <?php else: $maxS = max(1, max(array_column($staffUtil, 'assignments'))); foreach ($staffUtil as $st): $pct = round($st['assignments'] / $maxS * 100); ?>
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem">
        <div style="width:120px;font-size:.78rem;color:var(--gray-700);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($st['full_name']) ?></div>
        <div style="flex:1;background:var(--gray-100);height:16px"><div style="width:<?= max(2,$pct) ?>%;background:var(--black);height:100%"></div></div>
        <div style="width:25px;text-align:right;font-weight:700;font-size:.8rem"><?= $st['assignments'] ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
