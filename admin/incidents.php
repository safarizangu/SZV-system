<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'incidents.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Incidents';

$typeLabels = [
    'vehicle_breakdown'   => 'Vehicle Breakdown',
    'medical'             => 'Medical',
    'weather_delay'       => 'Weather Delay',
    'accommodation_issue' => 'Accommodation Issue',
    'guide_driver_issue'  => 'Guide/Driver Issue',
    'safety'              => 'Safety',
    'other'               => 'Other',
];
$severityColors = [
    'low'      => ['#374151', '#f3f4f6'],
    'medium'   => ['#92400e', '#fef3e2'],
    'high'     => ['#9a3412', '#ffedd5'],
    'critical' => ['#991b1b', '#fef2f2'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $bookingRef  = trim($_POST['booking_ref'] ?? '') ?: null;
    $type        = array_key_exists($_POST['incident_type'] ?? '', $typeLabels) ? $_POST['incident_type'] : 'other';
    $severity    = array_key_exists($_POST['severity'] ?? '', $severityColors) ? $_POST['severity'] : 'medium';
    $description = trim($_POST['description'] ?? '');
    $occurredAt  = trim($_POST['occurred_at'] ?? '') ?: date('Y-m-d\TH:i');
    $myId        = (int)($_SESSION['admin_user_id'] ?? 0) ?: null;

    if ($description === '') {
        header('Location: incidents.php?msg=' . urlencode('Error: Description is required.'));
        exit;
    }
    try {
        $db->prepare("
            INSERT INTO incidents (booking_ref, incident_type, severity, description, reported_by, occurred_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$bookingRef, $type, $severity, $description, $myId, str_replace('T', ' ', $occurredAt)]);
        $msg = 'Logged.';
    } catch (\Exception $e) { $msg = 'Error: ' . $e->getMessage(); }
    header('Location: incidents.php?msg=' . urlencode($msg));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $notes = trim($_POST['resolution_notes'] ?? '');
    try {
        $db->prepare("UPDATE incidents SET status='resolved', resolved_at=NOW(), resolution_notes=? WHERE id=?")
           ->execute([$notes ?: null, (int)$_POST['resolve_id']]);
    } catch (\Exception $e) {}
    header('Location: incidents.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try { $db->prepare("DELETE FROM incidents WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch (\Exception $e) {}
    header('Location: incidents.php');
    exit;
}

$msg    = htmlspecialchars($_GET['msg'] ?? '');
$filter = trim($_GET['filter'] ?? 'open');
$where  = $filter === 'open' ? "WHERE status='open'" : ($filter === 'resolved' ? "WHERE status='resolved'" : '');

$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page-1)*$perPage;
$rows = []; $totalRows = 0; $totalPages = 0;
try {
    $totalRows  = (int)$db->query("SELECT COUNT(*) FROM incidents i $where")->fetchColumn();
    $totalPages = (int)ceil($totalRows/$perPage);
    $rows = $db->query("
        SELECT i.*, a.full_name AS reporter_name
        FROM incidents i
        LEFT JOIN admin_users a ON a.id = i.reported_by
        $where
        ORDER BY i.severity='critical' DESC, i.severity='high' DESC, i.occurred_at DESC
        LIMIT $perPage OFFSET $offset
    ")->fetchAll();
} catch (\Exception $e) {}

$openCount = 0;
try { $openCount = (int)$db->query("SELECT COUNT(*) FROM incidents WHERE status='open'")->fetchColumn(); } catch (\Exception $e) {}

// Prefill from a booking ref, e.g. linked from operations_calendar.php
$prefillRef = trim($_GET['booking_ref'] ?? '');

ob_start();
?>
<?php if ($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>

<div class="admin-stats-grid" style="grid-template-columns:repeat(2,1fr);max-width:400px">
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:<?= $openCount ? '#991b1b' : 'var(--gray-500)' ?>">Open</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem;color:<?= $openCount ? '#991b1b' : 'inherit' ?>"><?= $openCount ?></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Total Logged</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= (int)$db->query("SELECT COUNT(*) FROM incidents")->fetchColumn() ?></div>
  </div></div>
</div>

<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Incidents</h3>
    <a href="incidents.php?new=1<?= $prefillRef ? '&booking_ref='.urlencode($prefillRef) : '' ?>" class="admin-btn admin-btn--primary" style="font-size:.68rem">+ Log Incident</a>
  </div>
  <form method="GET" style="padding:0 1.25rem 1rem">
    <select name="filter" class="admin-input" onchange="this.form.submit()" style="max-width:170px;font-weight:700">
      <option value="open"     <?= $filter==='open'?'selected':'' ?>>Open</option>
      <option value="resolved" <?= $filter==='resolved'?'selected':'' ?>>Resolved</option>
      <option value="all"      <?= $filter==='all'?'selected':'' ?>>All</option>
    </select>
  </form>
  <div class="admin-table-wrap">
  <table class="admin-table">
  <thead><tr><th>Occurred</th><th>Booking</th><th>Type</th><th>Severity</th><th>Description</th><th>Reported By</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (empty($rows)): ?>
  <tr><td colspan="8" class="admin-empty">No incidents<?= $filter==='open'?' — nothing open right now':'' ?>.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r): [$c,$bg] = $severityColors[$r['severity']]; ?>
  <tr>
    <td style="white-space:nowrap;font-size:.78rem"><?= date('d M Y, H:i', strtotime($r['occurred_at'])) ?></td>
    <td><?= $r['booking_ref'] ? '<a href="bookings.php?search='.urlencode($r['booking_ref']).'">'.htmlspecialchars($r['booking_ref']).'</a>' : '<span style="color:var(--gray-400)">—</span>' ?></td>
    <td><?= $typeLabels[$r['incident_type']] ?? ucfirst($r['incident_type']) ?></td>
    <td><span style="background:<?= $bg ?>;color:<?= $c ?>;font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:2px"><?= ucfirst($r['severity']) ?></span></td>
    <td style="max-width:280px;font-size:.82rem"><?= htmlspecialchars(mb_strimwidth($r['description'], 0, 140, '…')) ?></td>
    <td style="font-size:.8rem;color:var(--gray-500)"><?= htmlspecialchars($r['reporter_name'] ?? '—') ?></td>
    <td><?= $r['status']==='open' ? '<span class="badge-status badge-status--amber">Open</span>' : '<span class="badge-status badge-status--green">Resolved</span>' ?></td>
    <td>
      <div class="admin-row-actions">
        <button type="button" class="admin-icon-btn admin-row-actions-toggle" aria-label="Actions" aria-haspopup="true" aria-expanded="false"><?= \App\Helpers\AdminIcon::render('dots-vertical', 16) ?></button>
        <div class="admin-row-actions-menu">
          <?php if ($r['status']==='open'): ?>
          <button type="button" class="admin-row-actions-item" onclick="openResolveModal(<?= $r['id'] ?>)"><?= \App\Helpers\AdminIcon::render('check', 14) ?> Resolve</button>
          <?php else: ?>
          <span class="admin-row-actions-item" style="color:var(--gray-500);cursor:default" title="<?= htmlspecialchars($r['resolution_notes'] ?? '') ?>">✓ Resolved <?= date('d M', strtotime($r['resolved_at'])) ?></span>
          <?php endif; ?>
          <form method="POST" onsubmit="return confirmSubmitGuard(event, this, 'Delete this incident record?', {danger:true})">
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

<!-- Log Incident modal -->
<div id="formModal" class="admin-modal-overlay">
  <div class="admin-modal admin-modal--wide">
    <div class="admin-modal-header">
      <span class="admin-modal-title">Log Incident</span>
      <button type="button" onclick="closeFormModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="save" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div><label class="admin-label">Booking Ref <span style="font-weight:600;color:var(--gray-500)">(optional)</span></label>
            <input name="booking_ref" class="admin-input" value="<?= htmlspecialchars($prefillRef) ?>" placeholder="SZVXXXXXXXX"></div>
          <div><label class="admin-label">Occurred At</label>
            <input name="occurred_at" type="datetime-local" class="admin-input" value="<?= date('Y-m-d\TH:i') ?>" required></div>
          <div><label class="admin-label">Type</label>
            <select name="incident_type" class="admin-input">
              <?php foreach ($typeLabels as $v => $l): ?>
              <option value="<?= $v ?>"><?= $l ?></option>
              <?php endforeach; ?>
            </select></div>
          <div><label class="admin-label">Severity</label>
            <select name="severity" class="admin-input">
              <?php foreach (array_keys($severityColors) as $sv): ?>
              <option value="<?= $sv ?>" <?= $sv==='medium'?'selected':'' ?>><?= ucfirst($sv) ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
        <div style="margin-top:1rem"><label class="admin-label">Description</label>
          <textarea name="description" class="admin-input" rows="3" required placeholder="What happened, and what's being done about it"></textarea></div>
        <div style="margin-top:1rem;display:flex;gap:.5rem">
          <button class="admin-btn admin-btn--primary">Log Incident</button>
          <button type="button" onclick="closeFormModal()" class="admin-btn admin-btn--outline">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Resolve modal -->
<div id="resolveModal" class="admin-modal-overlay">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <span class="admin-modal-title">Resolve Incident</span>
      <button type="button" onclick="closeResolveModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="resolve_id" id="resolve_id" value="">
        <label class="admin-label">Resolution Notes <span style="font-weight:600;color:var(--gray-500)">(optional)</span></label>
        <textarea name="resolution_notes" class="admin-input" rows="3" placeholder="How was this resolved?"></textarea>
        <div style="margin-top:1rem;display:flex;gap:.5rem">
          <button class="admin-btn admin-btn--primary">Mark Resolved</button>
          <button type="button" onclick="closeResolveModal()" class="admin-btn admin-btn--outline">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openFormModal() { document.getElementById('formModal').classList.add('open'); }
function closeFormModal() { document.getElementById('formModal').classList.remove('open'); }
document.getElementById('formModal').addEventListener('click', function(e){ if (e.target === this) closeFormModal(); });
function openResolveModal(id) { document.getElementById('resolve_id').value = id; document.getElementById('resolveModal').classList.add('open'); }
function closeResolveModal() { document.getElementById('resolveModal').classList.remove('open'); }
document.getElementById('resolveModal').addEventListener('click', function(e){ if (e.target === this) closeResolveModal(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { closeFormModal(); closeResolveModal(); } });
<?php if ((isset($msg) && str_starts_with($msg,'Error')) || isset($_GET['new'])): ?>
document.addEventListener('DOMContentLoaded', openFormModal);
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
