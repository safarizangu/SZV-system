<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Packages';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $id  = (int)$_POST['toggle_id'];
    $cur = (int)$_POST['current'];
    try { $db->prepare("UPDATE packages SET is_active=? WHERE id=?")->execute([!$cur, $id]); }
    catch(\Exception $e){ error_log($e->getMessage()); }
    header('Location: packages.php'); exit;
}

$packages = [];
try { $packages = $db->query("SELECT * FROM packages ORDER BY sort_order ASC, id ASC")->fetchAll(); }
catch(\Exception $e){ error_log($e->getMessage()); }
$levelLabels = ['budget'=>'Budget','midrange'=>'Mid-Range','highend'=>'High-End','ultra_luxury'=>'Ultra Luxury'];

ob_start();
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem">
  <p style="font-size:.82rem;color:var(--gray-500)">
    <?= count($packages) ?> packages ·
    <?= count(array_filter($packages,fn($p)=>$p['is_active'])) ?> active ·
    <?= count(array_filter($packages,fn($p)=>$p['is_featured'])) ?> featured
  </p>
  <a href="package_edit.php" class="admin-btn admin-btn--primary">+ New Package</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem">
<?php foreach ($packages as $p):
    $imgs  = json_decode($p['gallery_images']??'[]',true)?:[];
    $hero  = $imgs[0]??null;
    $level = $levelLabels[$p['accommodation_level']??'midrange']??'Mid-Range';
    $borderColor = $p['is_active'] ? 'var(--gray-200)' : 'var(--red-border)';
?>
<div style="background:#fff;border:1.5px solid <?= $borderColor ?>;display:flex;flex-direction:column">

  <!-- Thumbnail -->
  <div style="height:150px;overflow:hidden;background:var(--gray-100);flex-shrink:0;position:relative">
    <?php if($hero): ?>
    <img src="<?= htmlspecialchars($hero) ?>" alt="<?= htmlspecialchars($p['name']) ?>"
         style="width:100%;height:100%;object-fit:cover;display:block" loading="lazy">
    <?php else: ?>
    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--gray-300);font-size:2.5rem">🏕</div>
    <?php endif; ?>
    <span style="position:absolute;top:.5rem;left:.5rem;font-size:.55rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.15rem .45rem;background:<?= $p['is_active']?'#16a34a':'#dc2626' ?>;color:#fff">
      <?= $p['is_active']?'Active':'Inactive' ?>
    </span>
    <?php if($p['is_featured']): ?>
    <span style="position:absolute;top:.5rem;right:.5rem;font-size:.55rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.15rem .45rem;background:#0a0a0a;color:#fff">
      Featured
    </span>
    <?php endif; ?>
  </div>

  <!-- Info -->
  <div style="padding:1rem;flex:1;display:flex;flex-direction:column">
    <div style="display:flex;align-items:start;justify-content:space-between;gap:.5rem;margin-bottom:.5rem">
      <div>
        <h3 style="font-family:var(--font-disp);font-size:.95rem;font-weight:500;line-height:1.2"><?= htmlspecialchars($p['name']) ?></h3>
        <p style="font-size:.65rem;color:var(--gray-500);margin-top:.1rem"><?= htmlspecialchars($p['slug']) ?></p>
      </div>
      <span style="font-size:.58rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;padding:.15rem .4rem;background:var(--gray-100);color:var(--gray-700);white-space:nowrap;flex-shrink:0"><?= $level ?></span>
    </div>
    <p style="font-size:.78rem;color:var(--gray-700);line-height:1.6;margin-bottom:.75rem;flex:1">
      <?= htmlspecialchars(mb_substr($p['short_description']??'',0,100)) ?><?= mb_strlen($p['short_description']??'')>100?'…':'' ?>
    </p>
    <div style="display:flex;gap:.75rem;margin-bottom:.85rem;font-size:.7rem;color:var(--gray-500)">
      <span>⏱ <?= $p['duration_days'] ?> nights</span>
      <span>💰 $<?= number_format((float)$p['price_2pax'],0) ?>/pp</span>
      <span>🖼 <?= count($imgs) ?> images</span>
    </div>
    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
      <a href="package_edit.php?id=<?= $p['id'] ?>" class="admin-btn admin-btn--primary" style="font-size:.62rem">Edit</a>
      <a href="<?= BASE_PATH ?>/packages.php#<?= htmlspecialchars($p['slug']) ?>" target="_blank" class="admin-btn admin-btn--outline" style="font-size:.62rem">View Live ↗</a>
      <form method="POST" style="display:inline">
        <input type="hidden" name="toggle_id" value="<?= $p['id'] ?>">
        <input type="hidden" name="current" value="<?= $p['is_active'] ?>">
        <button class="admin-btn admin-btn--<?= $p['is_active']?'muted':'success' ?>" style="font-size:.62rem">
          <?= $p['is_active']?'Deactivate':'Activate' ?>
        </button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if(empty($packages)): ?>
<div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--gray-300)">
  <div style="font-size:2.5rem;margin-bottom:.75rem;opacity:.3">▣</div>
  <p style="font-size:.85rem;font-style:italic">No packages yet. <a href="package_edit.php" style="color:var(--black)">Create your first package</a>.</p>
</div>
<?php endif; ?>
</div>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
