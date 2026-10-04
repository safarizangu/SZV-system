<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'dashboard_sales.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Sales & Pipeline Dashboard';

// ── Pipeline funnel — live counts per stage ────────────────────
$stageLabels = ['lead'=>'Lead','qualified'=>'Qualified','quoted'=>'Quoted','negotiation'=>'Negotiation','booked'=>'Booked','travelled'=>'Travelled','completed'=>'Completed'];
$funnel = array_fill_keys(array_keys($stageLabels), 0);
$lostCount = 0;
try {
    foreach ($db->query("SELECT pipeline_stage, COUNT(*) c FROM leads GROUP BY pipeline_stage")->fetchAll() as $r) {
        if ($r['pipeline_stage'] === 'lost') { $lostCount = (int)$r['c']; continue; }
        if (isset($funnel[$r['pipeline_stage']])) $funnel[$r['pipeline_stage']] = (int)$r['c'];
    }
} catch (\Exception $e) {}

// ── Quotation conversion ────────────────────────────────────────
$quoteStats = ['sent' => 0, 'accepted' => 0, 'converted' => 0, 'declined' => 0, 'expired' => 0];
try {
    foreach ($db->query("SELECT status, COUNT(*) c FROM quotations WHERE status != 'draft' GROUP BY status")->fetchAll() as $r) {
        if (isset($quoteStats[$r['status']])) $quoteStats[$r['status']] = (int)$r['c'];
    }
} catch (\Exception $e) {}
$quotesSentTotal = array_sum($quoteStats);
$quotesWon       = $quoteStats['accepted'] + $quoteStats['converted'];
$winRate         = $quotesSentTotal > 0 ? round($quotesWon / $quotesSentTotal * 100, 1) : 0;

// ── Monthly revenue trend (last 6 months, real bookings only) ──
$monthlyRevenue = [];
try {
    $rows = $db->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') ym, SUM(total_amount) revenue, COUNT(*) bookings
        FROM bookings
        WHERE payment_status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY ym ORDER BY ym
    ")->fetchAll();
    foreach ($rows as $r) $monthlyRevenue[$r['ym']] = ['revenue' => (float)$r['revenue'], 'bookings' => (int)$r['bookings']];
} catch (\Exception $e) {}
// Fill any missing months with zero so the chart always spans a full 6-month window
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    if (!isset($monthlyRevenue[$ym])) $monthlyRevenue[$ym] = ['revenue' => 0, 'bookings' => 0];
}
ksort($monthlyRevenue);

$totalRevenue90d = 0;
try { $totalRevenue90d = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE payment_status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)")->fetchColumn(); }
catch (\Exception $e) {}

ob_start();
?>
<p style="color:var(--gray-500);font-size:.85rem;margin:-.5rem 0 1.25rem">
  Lead → quote → booking pipeline, quotation win rate, and revenue trend — all computed live, nothing cached or manually entered.
</p>

<div class="admin-stats-grid">
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Open Pipeline</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= array_sum($funnel) ?></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Quote Win Rate</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $winRate ?>%</div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Revenue (90 Days)</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem">$<?= number_format($totalRevenue90d,0) ?></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Lost Leads</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem;color:#991b1b"><?= $lostCount ?></div>
  </div></div>
</div>

<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3>Pipeline Funnel</h3><a href="leads.php" class="admin-btn admin-btn--outline" style="font-size:.68rem">View Leads →</a></div>
  <div class="admin-card-body">
    <?php $maxFunnel = max(1, max($funnel)); ?>
    <?php foreach ($stageLabels as $key => $label): $v = $funnel[$key]; $pct = round($v / $maxFunnel * 100); ?>
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem">
      <div style="width:100px;font-size:.78rem;color:var(--gray-500)"><?= $label ?></div>
      <div style="flex:1;background:var(--gray-100);height:22px;position:relative">
        <div style="width:<?= max(2,$pct) ?>%;background:var(--black);height:100%"></div>
      </div>
      <div style="width:40px;text-align:right;font-weight:700;font-size:.85rem"><?= $v ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">
  <div class="admin-card">
    <div class="admin-card-header"><h3>Revenue Trend — Last 6 Months</h3></div>
    <div class="admin-card-body" style="overflow-x:auto">
      <?php $maxRev = max(1, max(array_column($monthlyRevenue, 'revenue'))); ?>
      <div style="display:flex;align-items:flex-end;gap:8px;height:120px;min-width:320px">
        <?php foreach ($monthlyRevenue as $ym => $d): $barH = max(2, round($d['revenue'] / $maxRev * 120)); ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px" title="<?= $ym ?>: $<?= number_format($d['revenue'],0) ?> (<?= $d['bookings'] ?> bookings)">
          <div style="width:100%;height:<?= $barH ?>px;background:var(--black);opacity:.85;min-height:2px"></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:.4rem;font-size:.6rem;color:var(--gray-500)">
        <?php foreach ($monthlyRevenue as $ym => $d): ?><span style="flex:1;text-align:center"><?= date('M', strtotime($ym.'-01')) ?></span><?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Quotation Outcomes</h3><a href="quotations.php" class="admin-btn admin-btn--outline" style="font-size:.68rem">View →</a></div>
    <div class="admin-card-body">
      <?php
      $outcomeLabels = ['accepted'=>['Accepted','#166534'],'converted'=>['Converted to Booking','#0a0a0a'],'declined'=>['Declined','#991b1b'],'expired'=>['Expired','#9ca3af']];
      foreach ($outcomeLabels as $key => [$label,$color]): $v = $quoteStats[$key];
      ?>
      <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--gray-100)">
        <span style="font-size:.85rem"><?= $label ?></span>
        <span style="font-weight:700;color:<?= $color ?>"><?= $v ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
