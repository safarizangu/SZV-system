<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Customers';

// Handle manual edits (name/phone/country) — the record itself is
// otherwise only ever written by App\Models\Customer::findOrCreateByEmail().
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
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

$search = trim($_GET['q'] ?? '');
$sort   = $_GET['sort'] ?? 'recent';
$msg    = htmlspecialchars($_GET['msg'] ?? '');

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
$customers = [];
$stats     = ['total' => 0, 'total_spent' => 0, 'repeat' => 0];
try {
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
        LIMIT 200
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
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:1.75rem">
  <div style="border:1.5px solid #e5e7eb;padding:.85rem 1rem">
    <div style="font-family:var(--font-disp);font-size:1.5rem;font-weight:500"><?= number_format($stats['total']) ?></div>
    <div style="font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af;margin-top:.2rem">Total Customers</div>
  </div>
  <div style="border:1.5px solid #e5e7eb;padding:.85rem 1rem">
    <div style="font-family:var(--font-disp);font-size:1.5rem;font-weight:500">$<?= number_format($stats['total_spent'],0) ?></div>
    <div style="font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af;margin-top:.2rem">Lifetime Booking Value</div>
  </div>
  <div style="border:1.5px solid #e5e7eb;padding:.85rem 1rem">
    <div style="font-family:var(--font-disp);font-size:1.5rem;font-weight:500"><?= number_format($stats['repeat']) ?></div>
    <div style="font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af;margin-top:.2rem">Repeat Guests (2+ Bookings)</div>
  </div>
</div>

<!-- Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem">
  <form style="display:flex;gap:.5rem;flex-wrap:wrap">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search name, email, phone…"
           style="border:1.5px solid #e5e7eb;padding:.45rem .85rem;font-size:.82rem;outline:none;min-width:240px">
    <select name="sort" onchange="this.form.submit()" style="border:1.5px solid #e5e7eb;padding:.45rem .7rem;font-size:.78rem">
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
    <?php if ((int)$c['booking_count'] > 1): ?>
      <span style="display:inline-block;margin-left:.35rem;font-size:.58rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;background:#f0fdf4;color:#166534;padding:.1rem .4rem">Repeat</span>
    <?php endif; ?>
  </td>
  <td style="font-size:.78rem">
    <a href="mailto:<?= htmlspecialchars($c['email']) ?>" style="color:#6b7280"><?= htmlspecialchars($c['email']) ?></a>
    <?php if ($c['phone']): ?><br><span style="color:#9ca3af"><?= htmlspecialchars($c['phone']) ?></span><?php endif; ?>
  </td>
  <td style="font-size:.8rem;color:#6b7280"><?= htmlspecialchars($c['country'] ?? '—') ?></td>
  <td style="text-align:center;font-weight:700"><?= (int)$c['booking_count'] ?></td>
  <td style="text-align:right;font-weight:700">$<?= number_format((float)$c['total_spent'],0) ?></td>
  <td style="text-align:center;color:#6b7280"><?= (int)$c['quotation_count'] ?></td>
  <td style="text-align:center;color:#6b7280"><?= (int)$c['lead_count'] ?></td>
  <td style="text-align:center;color:#6b7280"><?= (int)$c['request_count'] ?></td>
  <td style="font-size:.72rem;color:#9ca3af"><?= date('d M Y', strtotime($c['first_seen_at'])) ?></td>
  <td>
    <div style="display:flex;gap:.3rem">
      <a href="customer_view.php?id=<?= $c['id'] ?>" class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">View</a>
      <button type="button" class="admin-btn admin-btn--muted" style="font-size:.65rem;padding:.3rem .6rem"
              onclick='openEdit(<?= json_encode($c) ?>)'>Edit</button>
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

<!-- Edit modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:999;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;width:100%;max-width:440px;border-radius:2px;overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.5rem;border-bottom:1px solid #e5e7eb;background:#0a0a0a;color:#fff">
      <span style="font-size:.9rem;font-weight:600">Edit Customer</span>
      <button type="button" onclick="closeEdit()" style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer">✕</button>
    </div>
    <form method="POST" style="padding:1.5rem">
      <input type="hidden" name="update_customer" value="1">
      <input type="hidden" name="id" id="edit_id">
      <div class="admin-form-group" style="margin-bottom:1rem">
        <label class="admin-label">Email (fixed — the dedup key)</label>
        <input class="admin-input" id="edit_email" disabled style="background:#f3f4f6;color:#9ca3af">
      </div>
      <div class="admin-form-group" style="margin-bottom:1rem">
        <label class="admin-label">Full Name</label>
        <input class="admin-input" name="full_name" id="edit_name" required>
      </div>
      <div class="admin-form-group" style="margin-bottom:1rem">
        <label class="admin-label">Phone</label>
        <input class="admin-input" name="phone" id="edit_phone">
      </div>
      <div class="admin-form-group" style="margin-bottom:1.25rem">
        <label class="admin-label">Country</label>
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
  document.getElementById('editModal').style.display = 'flex';
}
function closeEdit() { document.getElementById('editModal').style.display = 'none'; }
document.getElementById('editModal').addEventListener('click', function(e){ if (e.target === this) closeEdit(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeEdit(); });
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
