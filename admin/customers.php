<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Customers';

// Handle manual edits (name/phone/country) — the record itself is
// otherwise only ever written by App\Models\Customer::findOrCreateByEmail().
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        $db->prepare("UPDATE customers SET full_name=?, phone=?, country=?, updated_at=NOW() WHERE id=?")
           ->execute([
               trim($_POST['full_name'] ?? ''),
               trim($_POST['phone'] ?? '') ?: null,
               trim($_POST['country'] ?? '') ?: null,
               (int)$_POST['id'],
           ]);
    } catch (Exception $e) { error_log('[customers update] ' . $e->getMessage()); }
    header('Location: customers.php?msg=' . urlencode('Customer updated.')); exit;
}

$search  = trim($_GET['q'] ?? '');
$sort    = $_GET['sort'] ?? 'recent';
$msg     = htmlspecialchars($_GET['msg'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = '';
$params = [];
if ($search !== '') {
    $where = "WHERE c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$orderBy = match ($sort) {
    'name'    => 'c.full_name ASC',
    'spend'   => 'total_spent DESC',
    'oldest'  => 'c.first_seen_at ASC',
    default   => 'c.first_seen_at DESC', // 'recent'
};

// One row per customer with counts/sums pulled from every table that
// links back to it — this is the whole point of the customers table:
// a single query now answers "how many times has this person contacted
// us and how much have they spent," which no query could answer before.
// Deliberately not agent-scoped: a customer is a shared contact record —
// several agents may legitimately handle the same repeat guest across
// different bookings, so there is no single "owner" to restrict this to.
$customers = [];
$stats     = ['total' => 0, 'total_spent' => 0, 'repeat' => 0];
$totalRows = 0; $totalPages = 0;
try {
    $total = $db->prepare("SELECT COUNT(*) FROM customers c $where");
    $total->execute($params);
    $totalRows  = (int)$total->fetchColumn();
    $totalPages = (int)ceil($totalRows / $perPage);

    $stmt = $db->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM bookings b WHERE b.customer_id = c.id) AS booking_count,
               (SELECT COALESCE(SUM(b.total_amount),0) FROM bookings b WHERE b.customer_id = c.id AND b.payment_status != 'cancelled') AS total_spent,
               (SELECT COUNT(*) FROM quotations q WHERE q.customer_id = c.id) AS quotation_count,
               (SELECT COUNT(*) FROM leads l WHERE l.customer_id = c.id) AS lead_count,
               (SELECT COUNT(*) FROM custom_safari_requests r WHERE r.customer_id = c.id) AS request_count
        FROM customers c
        $where
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $customers = $stmt->fetchAll();

    $stats['total']       = (int)$db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $stats['total_spent'] = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE customer_id IS NOT NULL AND payment_status != 'cancelled'")->fetchColumn();
    $stats['repeat']      = (int)$db->query("
        SELECT COUNT(*) FROM customers c
        WHERE (SELECT COUNT(*) FROM bookings b WHERE b.customer_id = c.id) > 1
    ")->fetchColumn();
} catch (Exception $e) { error_log('[customers list] ' . $e->getMessage()); }

ob_start();
?>

<?php if ($msg): ?>
<div class="admin-alert admin-alert--success"><?= $msg ?></div>
<?php endif; ?>

<!-- Stats row -->
<div class="admin-stats-grid" style="grid-template-columns:repeat(3,1fr)">
  <div class="admin-stat-card">
    <span class="admin-stat-value"><?= number_format($stats['total']) ?></span>
    <span class="admin-stat-label">Total Customers</span>
  </div>
  <div class="admin-stat-card">
    <span class="admin-stat-value">$<?= number_format($stats['total_spent'],0) ?></span>
    <span class="admin-stat-label">Lifetime Booking Value</span>
  </div>
  <div class="admin-stat-card">
    <span class="admin-stat-value"><?= number_format($stats['repeat']) ?></span>
    <span class="admin-stat-label">Repeat Guests (2+ Bookings)</span>
  </div>
</div>

<!-- Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem">
  <form style="display:flex;gap:.5rem;flex-wrap:wrap">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search name, email, phone…"
           class="admin-input" style="min-width:240px">
    <select name="sort" onchange="this.form.submit()" class="admin-input" style="width:auto">
      <option value="recent" <?= $sort==='recent'?'selected':'' ?>>Most Recent</option>
      <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Oldest First</option>
      <option value="spend"  <?= $sort==='spend' ?'selected':'' ?>>Highest Spend</option>
      <option value="name"   <?= $sort==='name'  ?'selected':'' ?>>Name (A–Z)</option>
    </select>
    <button class="admin-btn admin-btn--primary">Search</button>
    <?php if ($search): ?>
    <a href="customers.php" class="admin-btn admin-btn--outline">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Table -->
<div class="admin-table-wrap">
<table class="admin-table">
<thead>
  <tr>
    <th>Customer</th><th>Contact</th><th>Country</th>
    <th style="text-align:center">Bookings</th>
    <th style="text-align:right">Lifetime Value</th>
    <th style="text-align:center">Quotes</th>
    <th style="text-align:center">Leads</th>
    <th style="text-align:center">Enquiries</th>
    <th>First Seen</th>
    <th style="min-width:140px">Actions</th>
  </tr>
</thead>
<tbody>
<?php foreach ($customers as $c): ?>
<tr>
  <td>
    <strong style="font-size:.85rem"><?= htmlspecialchars($c['full_name']) ?></strong>
    <?php
    // Informational only, not a formal points/discount program — a
    // quick "who's a VIP" signal for sales staff, computed from data
    // already on the row (no new schema). Thresholds are a starting
    // point; adjust here if the business wants different cutoffs.
    $spend = (float)$c['total_spent'];
    $bookings = (int)$c['booking_count'];
    if ($bookings >= 3 || $spend >= 10000) { $tier = ['Gold', '#92400e', '#fef3e2']; }
    elseif ($bookings >= 2 || $spend >= 4000) { $tier = ['Silver', '#374151', '#f3f4f6']; }
    else { $tier = null; }
    if ($tier): ?>
      <span style="background:<?= $tier[2] ?>;color:<?= $tier[1] ?>;font-size:.62rem;font-weight:700;letter-spacing:.04em;padding:.1rem .45rem;margin-left:.35rem;border-radius:2px" title="Informational loyalty tier — based on booking count / total spend"><?= $tier[0] ?></span>
    <?php endif; ?>
  </td>
  <td style="font-size:.78rem">
    <a href="mailto:<?= htmlspecialchars($c['email']) ?>" style="color:var(--gray-500)"><?= htmlspecialchars($c['email']) ?></a>
    <?php if ($c['phone']): ?><br><span style="color:var(--gray-500)"><?= htmlspecialchars($c['phone']) ?></span><?php endif; ?>
  </td>
  <td style="font-size:.8rem;color:var(--gray-500)"><?= htmlspecialchars($c['country'] ?? '—') ?></td>
  <td style="text-align:center;font-weight:700"><?= (int)$c['booking_count'] ?></td>
  <td style="text-align:right;font-weight:700">$<?= number_format((float)$c['total_spent'],0) ?></td>
  <td style="text-align:center;color:var(--gray-500)"><?= (int)$c['quotation_count'] ?></td>
  <td style="text-align:center;color:var(--gray-500)"><?= (int)$c['lead_count'] ?></td>
  <td style="text-align:center;color:var(--gray-500)"><?= (int)$c['request_count'] ?></td>
  <td style="font-size:.72rem;color:var(--gray-500)"><?= date('d M Y', strtotime($c['first_seen_at'])) ?></td>
  <td>
    <div class="admin-row-actions">
      <button type="button" class="admin-icon-btn admin-row-actions-toggle" aria-label="Actions" aria-haspopup="true" aria-expanded="false"><?= \App\Helpers\AdminIcon::render('dots-vertical', 16) ?></button>
      <div class="admin-row-actions-menu">
        <a href="customer_view.php?id=<?= $c['id'] ?>" class="admin-row-actions-item"><?= \App\Helpers\AdminIcon::render('eye', 14) ?> View</a>
        <button type="button" class="admin-row-actions-item" onclick='openEdit(<?= json_encode($c) ?>)'><?= \App\Helpers\AdminIcon::render('edit', 14) ?> Edit</button>
      </div>
    </div>
  </td>
</tr>
<?php endforeach; ?>
<?php if (empty($customers)): ?>
<tr><td colspan="10" class="admin-empty">
  <?= $search ? 'No customers match "' . htmlspecialchars($search) . '".' : 'No customers yet — they\'re created automatically from bookings, quotations, leads, and custom safari requests.' ?>
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination" style="border-top:none">
  <span style="font-size:.75rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?></span>
  <div class="page-btns">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"
       class="page-btn<?= $i===$page?' active':'' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- Edit modal -->
<div id="editModal" class="admin-modal-overlay">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <span class="admin-modal-title">Edit Customer</span>
      <button type="button" onclick="closeEdit()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <form method="POST" class="admin-modal-body">
      <?= \App\Middleware\CsrfMiddleware::field() ?>
      <input type="hidden" name="update_customer" value="1">
      <input type="hidden" name="id" id="edit_id">
      <div class="admin-form-group" style="margin-bottom:1rem">
        <label class="admin-label" for="edit_email">Email (fixed — the dedup key)</label>
        <input class="admin-input" id="edit_email" disabled style="background:var(--gray-100);color:var(--gray-500)">
      </div>
      <div class="admin-form-group" style="margin-bottom:1rem">
        <label class="admin-label" for="edit_name">Full Name</label>
        <input class="admin-input" name="full_name" id="edit_name" required>
      </div>
      <div class="admin-form-group" style="margin-bottom:1rem">
        <label class="admin-label" for="edit_phone">Phone</label>
        <input class="admin-input" name="phone" id="edit_phone">
      </div>
      <div class="admin-form-group" style="margin-bottom:1.25rem">
        <label class="admin-label" for="edit_country">Country</label>
        <input class="admin-input" name="country" id="edit_country">
      </div>
      <div style="display:flex;gap:.75rem;justify-content:flex-end">
        <button type="button" onclick="closeEdit()" class="admin-btn admin-btn--outline">Cancel</button>
        <button type="submit" class="admin-btn admin-btn--primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(c) {
  document.getElementById('edit_id').value      = c.id;
  document.getElementById('edit_email').value   = c.email;
  document.getElementById('edit_name').value    = c.full_name || '';
  document.getElementById('edit_phone').value   = c.phone || '';
  document.getElementById('edit_country').value = c.country || '';
  document.getElementById('editModal').classList.add('open');
}
function closeEdit() { document.getElementById('editModal').classList.remove('open'); }
document.getElementById('editModal').addEventListener('click', function(e){ if (e.target === this) closeEdit(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeEdit(); });
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
