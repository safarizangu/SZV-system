<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'reviews.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Reviews';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        $db->prepare("UPDATE reviews SET is_published = NOT is_published WHERE id=?")->execute([(int)$_POST['toggle_id']]);
    } catch (\Exception $e) {}
    header('Location: reviews.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try { $db->prepare("DELETE FROM reviews WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch (\Exception $e) {}
    header('Location: reviews.php');
    exit;
}

$filter = trim($_GET['filter'] ?? '');
$where  = '';
if ($filter === 'published') $where = 'WHERE is_published=1';
elseif ($filter === 'unpublished') $where = 'WHERE is_published=0';

$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page-1)*$perPage;
$rows = []; $totalRows = 0; $totalPages = 0;
try {
    $totalRows  = (int)$db->query("SELECT COUNT(*) FROM reviews $where")->fetchColumn();
    $totalPages = (int)ceil($totalRows/$perPage);
    $rows = $db->query("SELECT * FROM reviews $where ORDER BY submitted_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
}
catch (\Exception $e) {}

$summary = ['avg_rating' => 0, 'avg_nps' => 0, 'total' => 0, 'published' => 0];
try {
    $s = $db->query("SELECT COUNT(*) total, AVG(rating) avg_rating, AVG(nps_score) avg_nps, SUM(is_published) published FROM reviews")->fetch();
    if ($s) $summary = [
        'total'      => (int)$s['total'],
        'avg_rating' => $s['avg_rating'] ? round((float)$s['avg_rating'], 1) : 0,
        'avg_nps'    => $s['avg_nps'] ? round((float)$s['avg_nps'], 1) : 0,
        'published'  => (int)($s['published'] ?? 0),
    ];
} catch (\Exception $e) {}

ob_start();
?>

<div class="admin-stats-grid">
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Total Reviews</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $summary['total'] ?></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Average Rating</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $summary['avg_rating'] ?: '—' ?> <span style="font-size:1rem;color:var(--gray-500)">/5</span></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Average NPS</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $summary['avg_nps'] ?: '—' ?> <span style="font-size:1rem;color:var(--gray-500)">/10</span></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Published</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $summary['published'] ?></div>
  </div></div>
</div>

<form method="GET" style="margin-bottom:1.25rem">
  <select name="filter" class="admin-input" onchange="this.form.submit()" style="max-width:170px;font-weight:700">
    <option value=""             <?= !$filter?'selected':'' ?>>All</option>
    <option value="published"    <?= $filter==='published'?'selected':'' ?>>Published</option>
    <option value="unpublished"  <?= $filter==='unpublished'?'selected':'' ?>>Unpublished</option>
  </select>
</form>

<div class="admin-card">
  <div class="admin-table-wrap">
  <table class="admin-table">
  <thead><tr><th>Date</th><th>Guest</th><th>Booking</th><th>Rating</th><th>NPS</th><th>Comment</th><th>Published</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (empty($rows)): ?>
  <tr><td colspan="8" class="admin-empty">No reviews yet.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td style="white-space:nowrap;color:var(--gray-500);font-size:.78rem"><?= date('d M Y', strtotime($r['submitted_at'])) ?></td>
    <td><?= htmlspecialchars($r['guest_name']) ?><?= $r['guest_country'] ? '<div style="font-size:.72rem;color:var(--gray-500)">'.htmlspecialchars($r['guest_country']).'</div>' : '' ?></td>
    <td><a href="bookings.php?search=<?= urlencode($r['booking_ref']) ?>"><?= htmlspecialchars($r['booking_ref']) ?></a></td>
    <td><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></td>
    <td><?= $r['nps_score'] !== null ? (int)$r['nps_score'] . '/10' : '—' ?></td>
    <td style="max-width:280px;font-size:.82rem;color:var(--gray-700)"><?= $r['comment'] ? htmlspecialchars(mb_strimwidth($r['comment'], 0, 140, '…')) : '<span style="color:var(--gray-400)">—</span>' ?></td>
    <td>
      <form method="POST" style="display:inline">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="toggle_id" value="<?= $r['id'] ?>">
        <button class="admin-btn <?= $r['is_published'] ? 'admin-btn--success' : 'admin-btn--muted' ?>" style="font-size:.65rem;padding:.25rem .5rem">
          <?= $r['is_published'] ? 'Published' : 'Unpublished' ?>
        </button>
      </form>
    </td>
    <td>
      <div class="admin-row-actions">
        <button type="button" class="admin-icon-btn admin-row-actions-toggle" aria-label="Actions" aria-haspopup="true" aria-expanded="false"><?= \App\Helpers\AdminIcon::render('dots-vertical', 16) ?></button>
        <div class="admin-row-actions-menu">
          <form method="POST" onsubmit="return confirmSubmitGuard(event, this, 'Delete this review?', {danger:true})">
            <?= \App\Middleware\CsrfMiddleware::field() ?>
            <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
            <button type="submit" class="admin-row-actions-item admin-row-actions-item--danger"><?= \App\Helpers\AdminIcon::render('trash', 14) ?> Delete</button>
          </form>
        </div>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php if ($totalPages > 1): ?>
<div class="pagination" style="border-top:none">
  <span style="font-size:.75rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalRows) ?> total)</span>
  <div class="page-btns">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&filter=<?= urlencode($filter) ?>" class="page-btn<?= $i===$page?' active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
