<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
if (!\App\Middleware\AuthMiddleware::isAdminRole()) {
    header('Location: index.php?msg=' . urlencode('Permission denied.'));
    exit;
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Profitability Dashboard';

$rows = [];
try { $rows = $db->query("SELECT * FROM vw_package_profitability ORDER BY profit_margin_percent ASC")->fetchAll(); }
catch (\Exception $e) {}

$needsCosting = array_filter($rows, fn($r) => (float)$r['operational_cost'] <= 0);
$priced       = array_filter($rows, fn($r) => (float)$r['operational_cost'] > 0);
$avgMargin    = $priced ? round(array_sum(array_column($priced, 'profit_margin_percent')) / count($priced), 1) : null;

// ── Realized profit (last 90 days) — actual booking revenue against
//    the package's operational_cost, not just the catalog margin.
//    operational_cost is a per-1-adult figure (same basis price_1pax is
//    computed on in package_edit.php), scaled by traveler count here —
//    an approximation, same level of accuracy the package-level margin
//    field already carries, not a claim of exact per-booking cost.
$realizedRevenue = 0; $realizedCost = 0; $costedBookings = 0; $uncostedBookings = 0;
try {
    $bk = $db->query("
        SELECT b.total_amount, b.travelers, p.operational_cost
        FROM bookings b
        JOIN packages p ON p.id = b.package_id
        WHERE b.payment_status != 'cancelled' AND b.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    ")->fetchAll();
    foreach ($bk as $b) {
        $realizedRevenue += (float)$b['total_amount'];
        if ((float)$b['operational_cost'] > 0) {
            $realizedCost += (float)$b['operational_cost'] * max(1, (int)$b['travelers']);
            $costedBookings++;
        } else {
            $uncostedBookings++;
        }
    }
} catch (\Exception $e) {}
$realizedProfit = $realizedRevenue - $realizedCost;

ob_start();
?>
<p style="color:var(--gray-500);font-size:.85rem;margin:-.5rem 0 1.25rem">
  Pulled straight from <code>vw_package_profitability</code> — margin only means something once an operational cost is
  on file (set per-package in <a href="package_edit.php">Packages</a>). A package still at $0 cost shows 100% margin,
  which is a flag to price it, not a real figure.
</p>

<div class="admin-stats-grid">
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Packages Costed</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= count($priced) ?> <span style="font-size:1rem;color:var(--gray-500)">/ <?= count($rows) ?></span></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Avg Margin (Costed)</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $avgMargin !== null ? $avgMargin.'%' : '—' ?></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:<?= $needsCosting ? '#991b1b' : 'var(--gray-500)' ?>">Needs Costing</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem;color:<?= $needsCosting ? '#991b1b' : 'inherit' ?>"><?= count($needsCosting) ?></div>
  </div></div>
</div>

<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3>Realized Profit — Last 90 Days</h3></div>
  <div class="admin-card-body">
    <?php if ($uncostedBookings > 0): ?>
    <div class="admin-alert" style="background:#fef3e2;color:#92400e;margin-bottom:1rem">
      <?= $uncostedBookings ?> of <?= $costedBookings + $uncostedBookings ?> bookings in this window are for packages with no operational cost on file — their revenue counts below, but they're excluded from the cost/profit total, so profit is understated, not overstated.
    </div>
    <?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
      <div><div style="font-size:.65rem;text-transform:uppercase;color:var(--gray-500)">Revenue (All Bookings)</div><div style="font-size:1.5rem;font-weight:700">$<?= number_format($realizedRevenue,0) ?></div></div>
      <div><div style="font-size:.65rem;text-transform:uppercase;color:var(--gray-500)">Est. Cost (Costed Bookings Only)</div><div style="font-size:1.5rem;font-weight:700">$<?= number_format($realizedCost,0) ?></div></div>
      <div><div style="font-size:.65rem;text-transform:uppercase;color:var(--gray-500)">Est. Profit (Partial Coverage)</div><div style="font-size:1.5rem;font-weight:700;color:<?= $realizedProfit >= 0 ? '#16a34a' : '#dc2626' ?>">$<?= number_format($realizedProfit,0) ?></div></div>
    </div>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-header"><h3>Package Profitability</h3></div>
  <div class="admin-table-wrap">
  <table class="admin-table">
  <thead><tr><th>Package</th><th>Selling Price</th><th>Operational Cost</th><th>Profit</th><th>Margin</th></tr></thead>
  <tbody>
  <?php if (empty($rows)): ?>
  <tr><td colspan="5" class="admin-empty">No packages found.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r):
      $costed = (float)$r['operational_cost'] > 0;
      $margin = (float)$r['profit_margin_percent'];
      $mColor = !$costed ? '#9ca3af' : ($margin < 20 ? '#dc2626' : ($margin < 40 ? '#b45309' : '#16a34a'));
  ?>
  <tr>
    <td><a href="package_edit.php?id=<?= $r['package_id'] ?>"><?= htmlspecialchars($r['package_name']) ?></a></td>
    <td>$<?= number_format($r['selling_price'],0) ?></td>
    <td><?= $costed ? '$'.number_format($r['operational_cost'],0) : '<span style="color:#991b1b">Not set</span>' ?></td>
    <td>$<?= number_format($r['profit_amount'],0) ?></td>
    <td style="color:<?= $mColor ?>;font-weight:700"><?= $costed ? number_format($margin,1).'%' : '—' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
