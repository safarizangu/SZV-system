<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $db->prepare("UPDATE quotations SET status=?, updated_at=NOW() WHERE id=?")
           ->execute([$_POST['status'], (int)$_POST['id']]);
        if ($_POST['status'] === 'accepted') {
            $db->prepare("UPDATE quotations SET accepted_at=NOW() WHERE id=? AND accepted_at IS NULL")
               ->execute([(int)$_POST['id']]);
        }
    } catch(Exception $e) { error_log($e->getMessage()); }
    header('Location: quotations.php'); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try { $db->prepare("DELETE FROM quotations WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch(Exception $e) { error_log($e->getMessage()); }
    header('Location: quotations.php'); exit;
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$where  = 'WHERE 1=1';
$params = [];
if ($statusFilter) { $where .= ' AND status=?'; $params[] = $statusFilter; }
if ($search)       { $where .= ' AND (client_name LIKE ? OR client_email LIKE ? OR quote_ref LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }

$quotes = [];
$stats  = [];
try {
    $s = $db->prepare("SELECT * FROM quotations $where ORDER BY created_at DESC LIMIT 100");
    $s->execute($params);
    $quotes = $s->fetchAll();
    $statRows = $db->query("SELECT status, COUNT(*) as cnt, SUM(grand_total) as rev FROM quotations GROUP BY status")->fetchAll();
    foreach ($statRows as $r) $stats[$r['status']] = $r;
} catch(Exception $e) { error_log($e->getMessage()); }

$statusColors = [
    'draft'    => ['bg'=>'#f3f4f6','color'=>'#374151','label'=>'Draft'],
    'sent'     => ['bg'=>'#eff6ff','color'=>'#1d4ed8','label'=>'Sent'],
    'accepted' => ['bg'=>'#f0fdf4','color'=>'#166534','label'=>'Accepted'],
    'declined' => ['bg'=>'#fef2f2','color'=>'#991b1b','label'=>'Declined'],
    'expired'  => ['bg'=>'#fef3e2','color'=>'#92400e','label'=>'Expired'],
    'converted'=> ['bg'=>'#0a0a0a','color'=>'#ffffff','label'=>'Converted → Booking'],
];

$pageTitle = 'Quotations';
ob_start();
?>

<!-- Stats row -->
<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:.75rem;margin-bottom:1.75rem">
  <?php foreach ($statusColors as $key => $s): ?>
  <a href="?status=<?= $key ?>" style="text-decoration:none;border:1.5px solid <?= $statusFilter===$key ? '#0a0a0a' : '#e5e7eb' ?>;background:<?= $s['bg'] ?>;padding:.85rem 1rem;display:block">
    <div style="font-family:var(--font-disp);font-size:1.5rem;font-weight:500;color:<?= $s['color'] ?>"><?= $stats[$key]['cnt'] ?? 0 ?></div>
    <div style="font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:<?= $s['color'] ?>;margin-top:.2rem"><?= $s['label'] ?></div>
    <?php if (!empty($stats[$key]['rev'])): ?>
    <div style="font-size:.7rem;color:<?= $s['color'] ?>;margin-top:.15rem;opacity:.7">$<?= number_format($stats[$key]['rev'],0) ?></div>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem">
  <form style="display:flex;gap:.5rem;flex-wrap:wrap">
    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search name, email, ref…"
           style="border:1.5px solid #e5e7eb;padding:.45rem .85rem;font-size:.82rem;outline:none;min-width:220px">
    <button class="admin-btn admin-btn--primary">Search</button>
    <?php if ($search || $statusFilter): ?>
    <a href="quotations.php" class="admin-btn admin-btn--outline">Clear</a>
    <?php endif; ?>
  </form>
  <a href="quotation_new.php" class="admin-btn admin-btn--primary" style="font-size:.75rem">+ New Quotation</a>
</div>

<!-- Table -->
<div class="admin-table-wrap">
<table class="admin-table">
<thead>
  <tr>
    <th>Ref</th><th>Client</th><th>Safari Details</th>
    <th>Travelers</th><th>Total</th><th>Status</th>
    <th>Valid Until</th><th>Created</th><th style="min-width:220px">Actions</th>
  </tr>
</thead>
<tbody>
<?php foreach ($quotes as $q):
  $sc = $statusColors[$q['status']] ?? $statusColors['draft'];
  $expired = $q['valid_until'] && strtotime($q['valid_until']) < time() && $q['status'] === 'sent';
?>
<tr <?= $expired ? 'style="opacity:.6"' : '' ?>>
  <td>
    <code style="font-size:.78rem;background:#f3f4f6;padding:.15rem .4rem"><?= htmlspecialchars($q['quote_ref']) ?></code>
    <?php if ($q['version'] > 1): ?>
      <span style="font-size:.6rem;color:#9ca3af"> v<?= $q['version'] ?></span>
    <?php endif; ?>
  </td>
  <td>
    <strong style="font-size:.85rem"><?= htmlspecialchars($q['client_name']) ?></strong><br>
    <a href="mailto:<?= htmlspecialchars($q['client_email']) ?>" style="font-size:.72rem;color:#6b7280"><?= htmlspecialchars($q['client_email']) ?></a>
    <?php if ($q['client_phone']): ?>
      <br><span style="font-size:.72rem;color:#9ca3af"><?= htmlspecialchars($q['client_phone']) ?></span>
    <?php endif; ?>
  </td>
  <td style="font-size:.78rem">
    <?php if (!empty($q['destinations'])): ?>
      <?php $dests = json_decode($q['destinations'], true) ?: [$q['destinations']]; ?>
      <strong><?= htmlspecialchars(implode(', ', array_slice($dests, 0, 2))) ?></strong><br>
    <?php endif; ?>
    <?= $q['duration_days'] ?> days ·
    <?= ucfirst($q['accommodation_level'] ?? 'midrange') ?> ·
    <?= strtoupper($q['meal_plan'] ?? 'FB') ?>
  </td>
  <td style="text-align:center">
    <span style="font-size:.85rem;font-weight:700"><?= ((int)$q['adults']) + ((int)$q['children']) ?></span>
    <span style="font-size:.68rem;color:#9ca3af"><br><?= $q['adults'] ?>A <?= $q['children']>0 ? '+'.$q['children'].'C' : '' ?></span>
  </td>
  <td>
    <strong style="font-size:.9rem">$<?= number_format($q['grand_total'],0) ?></strong>
    <br><span style="font-size:.68rem;color:#9ca3af">$<?= number_format($q['per_person'],0) ?>/pp</span>
  </td>
  <td>
    <span style="display:inline-block;padding:.2rem .6rem;font-size:.62rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>">
      <?= $sc['label'] ?>
    </span>
    <?php if ($expired): ?>
      <br><span style="font-size:.62rem;color:#dc2626">Expired</span>
    <?php endif; ?>
  </td>
  <td style="font-size:.78rem;<?= $expired ? 'color:#dc2626' : 'color:#6b7280' ?>">
    <?= $q['valid_until'] ? date('d M Y', strtotime($q['valid_until'])) : '—' ?>
  </td>
  <td style="font-size:.72rem;color:#9ca3af"><?= date('d M Y', strtotime($q['created_at'])) ?></td>
  <td>
    <div style="display:flex;gap:.3rem;flex-wrap:wrap">
      <!-- Edit -->
      <a href="quotation_new.php?id=<?= $q['id'] ?>" class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">Edit</a>
      <!-- PDF -->
      <a href="../php/quotation_pdf.php?id=<?= $q['id'] ?>" target="_blank" class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">PDF</a>
      <!-- Email -->
      <form method="POST" action="../php/quotation_email.php" style="display:inline" target="_blank">
        <input type="hidden" name="id" value="<?= $q['id'] ?>">
        <button class="admin-btn admin-btn--primary" style="font-size:.65rem;padding:.3rem .6rem">Email</button>
      </form>
      <!-- Status -->
      <form method="POST" style="display:inline">
        <input type="hidden" name="update_status" value="1">
        <input type="hidden" name="id" value="<?= $q['id'] ?>">
        <select name="status" onchange="this.form.submit()"
                style="border:1.5px solid #e5e7eb;font-size:.65rem;padding:.3rem .5rem;cursor:pointer;max-width:100px">
          <?php foreach ($statusColors as $sv => $sl): ?>
          <option value="<?= $sv ?>" <?= $q['status']===$sv?'selected':'' ?>><?= $sl['label'] ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <!-- Convert to Booking -->
      <?php if ($q['status'] === 'accepted'): ?>
      <a href="../book.php?quote_id=<?= $q['id'] ?>" class="admin-btn admin-btn--success" style="font-size:.65rem;padding:.3rem .6rem">→ Book</a>
      <?php endif; ?>
      <!-- Delete -->
      <form method="POST" style="display:inline" onsubmit="return confirm('Delete quotation <?= htmlspecialchars($q['quote_ref']) ?>?')">
        <input type="hidden" name="delete_id" value="<?= $q['id'] ?>">
        <button class="admin-btn admin-btn--danger" style="font-size:.65rem;padding:.3rem .6rem">Del</button>
      </form>
    </div>
  </td>
</tr>
<?php endforeach; ?>
<?php if (empty($quotes)): ?><tr><td colspan="9" class="admin-empty">No quotations found.</td></tr><?php endif; ?>
</tbody>
</table>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
