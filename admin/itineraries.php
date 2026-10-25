<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'itineraries.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Itinerary Manager';

$msg = htmlspecialchars($_GET['msg'] ?? '');

function fetchAll($db,$sql){try{return $db->query($sql)->fetchAll();}catch(\Exception $e){return[];}}
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page-1)*$perPage;
$totalRows  = 0;
try { $totalRows = (int)$db->query("SELECT COUNT(*) FROM itineraries")->fetchColumn(); } catch(\Exception $e){}
$totalPages = (int)ceil($totalRows/$perPage);
$itineraries = fetchAll($db,"SELECT id,name,duration_days,base_price_pax,is_active,overview,includes,days FROM itineraries ORDER BY sort_order,name LIMIT $perPage OFFSET $offset");

// Build a "Lake Manyara · Ngorongoro · Serengeti" route line per card from
// each day's own location field — consecutive repeats collapsed (a 2-night
// stay shows the place once, not twice), capped so a long safari's route
// line doesn't wrap into its own paragraph.
foreach ($itineraries as &$it) {
    $days = json_decode($it['days'] ?? '[]', true) ?: [];
    $it['day_count'] = count($days);
    $route = [];
    foreach ($days as $d) {
        $loc = trim($d['location'] ?? '');
        if ($loc !== '' && end($route) !== $loc) $route[] = $loc;
    }
    $it['route'] = array_slice($route, 0, 4);
    $it['route_more'] = max(0, count($route) - 4);
    $it['includes_count'] = count(json_decode($it['includes'] ?? '[]', true) ?: []);
}
unset($it);

ob_start();
?>
<?php if($msg): ?>
<div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div>
<?php endif; ?>

<style>
.itin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,420px));
           gap:1.5rem;justify-content:center}
.itin-card{background:var(--white);border:1.5px solid var(--gray-200);display:flex;flex-direction:column;
           transition:border-color .18s,box-shadow .18s}
.itin-card:hover{border-color:var(--black);box-shadow:0 4px 16px rgba(0,0,0,.06)}
.itin-card-top{padding:1.25rem 1.25rem 1rem;border-bottom:1px solid var(--gray-100)}
.itin-card-name{font-family:var(--font-disp);font-size:1.1rem;font-weight:500;line-height:1.25;margin-bottom:.4rem}
.itin-card-route{font-size:.76rem;color:var(--gray-500);line-height:1.5}
.itin-card-body{padding:1rem 1.25rem;flex:1;display:flex;flex-direction:column}
.itin-card-overview{font-size:.78rem;color:var(--gray-700);line-height:1.65;margin-bottom:1rem;flex:1}
.itin-meta-row{display:flex;flex-wrap:wrap;gap:.5rem 1rem;margin-bottom:1rem;font-size:.7rem;color:var(--gray-500)}
.itin-meta-row strong{color:var(--black);font-weight:600}
.itin-card-footer{padding:0 1.25rem 1.25rem;display:flex;gap:.5rem;align-items:center}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem">
  <p style="font-size:.82rem;color:var(--gray-500)">
    <?= count($itineraries) ?> itineraries ·
    <?= count(array_filter($itineraries,fn($i)=>$i['is_active'])) ?> active
  </p>
  <a href="itinerary_edit.php" class="admin-btn admin-btn--primary">+ New Itinerary</a>
</div>

<?php if(empty($itineraries)): ?>
<div style="text-align:center;padding:4rem 1rem;color:var(--gray-300)">
  <div style="font-size:2.5rem;margin-bottom:.75rem;opacity:.3">▣</div>
  <p style="font-size:.85rem;font-style:italic;color:var(--gray-500)">No itineraries yet. <a href="itinerary_edit.php" style="color:var(--black)">Create your first one</a>.</p>
</div>
<?php else: ?>
<div class="itin-grid">
<?php foreach($itineraries as $it): ?>
<a href="itinerary_edit.php?id=<?= $it['id'] ?>" class="itin-card" style="text-decoration:none;color:inherit">
  <div class="itin-card-top">
    <div style="display:flex;align-items:start;justify-content:space-between;gap:.5rem;margin-bottom:.5rem">
      <div class="itin-card-name"><?= htmlspecialchars($it['name']) ?></div>
      <span class="badge-status badge-status--<?= $it['is_active']?'green':'gray' ?>" style="white-space:nowrap;flex-shrink:0">
        <?= $it['is_active']?'Active':'Draft' ?>
      </span>
    </div>
    <?php if($it['route']): ?>
    <div class="itin-card-route">
      <?= implode(' <span style="opacity:.4">·</span> ', array_map('htmlspecialchars', $it['route'])) ?><?= $it['route_more'] ? ' <span style="opacity:.5">+'.$it['route_more'].' more</span>' : '' ?>
    </div>
    <?php else: ?>
    <div class="itin-card-route" style="font-style:italic;opacity:.6">No day-by-day route set yet</div>
    <?php endif; ?>
  </div>

  <div class="itin-card-body">
    <?php if(!empty($it['overview'])): ?>
    <p class="itin-card-overview"><?= htmlspecialchars(mb_substr($it['overview'],0,140)) ?><?= mb_strlen($it['overview'])>140?'…':'' ?></p>
    <?php else: ?>
    <p class="itin-card-overview" style="font-style:italic;opacity:.5">No overview written yet.</p>
    <?php endif; ?>

    <div class="itin-meta-row">
      <span><?= \App\Helpers\AdminIcon::render('clock', 12) ?> <strong><?= $it['duration_days'] ?></strong> day<?= $it['duration_days']!=1?'s':'' ?> / <strong><?= max(0,$it['duration_days']-1) ?></strong> night<?= max(0,$it['duration_days']-1)!=1?'s':'' ?></span>
      <span><strong><?= $it['base_price_pax']>0 ? '$'.number_format($it['base_price_pax'],0) : '—' ?></strong> /pp</span>
      <span><strong><?= $it['day_count'] ?></strong> day<?= $it['day_count']!=1?'s':'' ?> configured</span>
      <span><strong><?= $it['includes_count'] ?></strong> includes</span>
    </div>
  </div>

  <div class="itin-card-footer">
    <span class="admin-btn admin-btn--primary" style="font-size:.68rem">Edit</span>
  </div>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($totalPages > 1): ?>
<div class="pagination" style="border-top:none">
  <span style="font-size:.75rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalRows) ?> total)</span>
  <div class="page-btns">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
    <a href="?page=<?= $i ?>" class="page-btn<?= $i===$page?' active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
