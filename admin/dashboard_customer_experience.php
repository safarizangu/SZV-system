<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'dashboard_customer_experience.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Customer Experience Dashboard';

$summary = ['total' => 0, 'avg_rating' => null, 'avg_nps' => null];
try {
    $s = $db->query("SELECT COUNT(*) total, AVG(rating) avg_rating, AVG(nps_score) avg_nps FROM reviews")->fetch();
    if ($s) $summary = [
        'total'      => (int)$s['total'],
        'avg_rating' => $s['avg_rating'] !== null ? round((float)$s['avg_rating'], 1) : null,
        'avg_nps'    => $s['avg_nps']    !== null ? round((float)$s['avg_nps'], 1)    : null,
    ];
} catch (\Exception $e) {}

// Rating distribution
$distribution = array_fill(1, 5, 0);
try {
    foreach ($db->query("SELECT rating, COUNT(*) c FROM reviews GROUP BY rating")->fetchAll() as $r) {
        $distribution[(int)$r['rating']] = (int)$r['c'];
    }
} catch (\Exception $e) {}

// NPS trend — last 6 months, average per month
$npsTrend = [];
try {
    $rows = $db->query("
        SELECT DATE_FORMAT(submitted_at,'%Y-%m') ym, AVG(nps_score) avg_nps, COUNT(*) c
        FROM reviews WHERE nps_score IS NOT NULL AND submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY ym ORDER BY ym
    ")->fetchAll();
    foreach ($rows as $r) $npsTrend[$r['ym']] = ['avg' => round((float)$r['avg_nps'],1), 'count' => (int)$r['c']];
} catch (\Exception $e) {}
for ($i = 5; $i >= 0; $i--) { $ym = date('Y-m', strtotime("-$i months")); if (!isset($npsTrend[$ym])) $npsTrend[$ym] = ['avg' => 0, 'count' => 0]; }
ksort($npsTrend);

// Repeat-customer rate + loyalty tier breakdown (same thresholds as admin/customers.php)
$repeatRate = 0; $totalCustomers = 0; $repeatCustomers = 0;
$tierCounts = ['Gold' => 0, 'Silver' => 0, 'Standard' => 0];
try {
    $totalCustomers = (int)$db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $cs = $db->query("
        SELECT c.id,
               (SELECT COUNT(*) FROM bookings b WHERE b.customer_id=c.id) AS bookings,
               (SELECT COALESCE(SUM(b.total_amount),0) FROM bookings b WHERE b.customer_id=c.id AND b.payment_status!='cancelled') AS spend
        FROM customers c
    ")->fetchAll();
    foreach ($cs as $c) {
        if ($c['bookings'] > 1) $repeatCustomers++;
        if ($c['bookings'] >= 3 || $c['spend'] >= 10000) $tierCounts['Gold']++;
        elseif ($c['bookings'] >= 2 || $c['spend'] >= 4000) $tierCounts['Silver']++;
        else $tierCounts['Standard']++;
    }
    $repeatRate = $totalCustomers > 0 ? round($repeatCustomers / $totalCustomers * 100, 1) : 0;
} catch (\Exception $e) {}

ob_start();
?>
<p style="color:var(--gray-500);font-size:.85rem;margin:-.5rem 0 1.25rem">
  Review/NPS scores from <a href="reviews.php">guest-submitted reviews</a>, and repeat/loyalty signals computed live from booking history.
</p>

<div class="admin-stats-grid">
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Avg Rating</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $summary['avg_rating'] ?? '—' ?> <span style="font-size:1rem;color:var(--gray-500)">/5</span></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Avg NPS</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $summary['avg_nps'] ?? '—' ?> <span style="font-size:1rem;color:var(--gray-500)">/10</span></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Repeat Rate</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $repeatRate ?>%</div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Reviews Collected</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $summary['total'] ?></div>
  </div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem">
  <div class="admin-card">
    <div class="admin-card-header"><h3>Rating Distribution</h3></div>
    <div class="admin-card-body">
      <?php $maxDist = max(1, max($distribution)); for ($star = 5; $star >= 1; $star--): $v = $distribution[$star]; $pct = round($v / $maxDist * 100); ?>
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem">
        <div style="width:60px;font-size:.78rem;color:var(--gray-500)"><?= str_repeat('★', $star) ?></div>
        <div style="flex:1;background:var(--gray-100);height:18px"><div style="width:<?= max(2,$pct) ?>%;background:var(--black);height:100%"></div></div>
        <div style="width:30px;text-align:right;font-weight:700;font-size:.8rem"><?= $v ?></div>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Loyalty Tiers <span style="font-weight:600;font-size:.7rem;color:var(--gray-500)">(informational)</span></h3></div>
    <div class="admin-card-body">
      <?php $tierColors = ['Gold'=>['#92400e','#fef3e2'],'Silver'=>['#374151','#f3f4f6'],'Standard'=>['#6b7280','#fafaf9']]; ?>
      <?php foreach ($tierCounts as $tier => $count): [$c,$bg] = $tierColors[$tier]; ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-bottom:1px solid var(--gray-100)">
        <span style="background:<?= $bg ?>;color:<?= $c ?>;font-size:.7rem;font-weight:700;padding:.15rem .55rem;border-radius:2px"><?= $tier ?></span>
        <span style="font-weight:700"><?= $count ?> <span style="font-weight:600;color:var(--gray-500);font-size:.78rem">customers</span></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-header"><h3>NPS Trend — Last 6 Months</h3></div>
  <div class="admin-card-body" style="overflow-x:auto">
    <?php $maxNps = 10; ?>
    <div style="display:flex;align-items:flex-end;gap:8px;height:100px;min-width:320px">
      <?php foreach ($npsTrend as $ym => $d): $barH = max(2, round($d['avg'] / $maxNps * 100)); ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px" title="<?= $ym ?>: NPS <?= $d['avg'] ?> (<?= $d['count'] ?> reviews)">
        <div style="width:100%;height:<?= $barH ?>px;background:var(--black);opacity:.85;min-height:2px"></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:space-between;margin-top:.4rem;font-size:.6rem;color:var(--gray-500)">
      <?php foreach ($npsTrend as $ym => $d): ?><span style="flex:1;text-align:center"><?= date('M', strtotime($ym.'-01')) ?></span><?php endforeach; ?>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
