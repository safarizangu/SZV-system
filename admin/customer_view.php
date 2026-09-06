<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: customers.php'); exit; }

$customer = null;
try {
    $s = $db->prepare("SELECT * FROM customers WHERE id=?");
    $s->execute([$id]);
    $customer = $s->fetch();
} catch (Exception $e) { error_log('[customer_view] ' . $e->getMessage()); }

if (!$customer) { header('Location: customers.php?msg=' . urlencode('Customer not found.')); exit; }

$pageTitle = htmlspecialchars($customer['full_name']) . ' — Customer';

function fetchAllSafe($db, $sql, $params) {
    try { $s = $db->prepare($sql); $s->execute($params); return $s->fetchAll(); }
    catch (Exception $e) { error_log($e->getMessage()); return []; }
}

$bookings = fetchAllSafe($db, "SELECT * FROM bookings WHERE customer_id=? ORDER BY created_at DESC", [$id]);
$quotes   = fetchAllSafe($db, "SELECT * FROM quotations WHERE customer_id=? ORDER BY created_at DESC", [$id]);
$leads    = fetchAllSafe($db, "SELECT * FROM leads WHERE customer_id=? ORDER BY created_at DESC", [$id]);
$requests = fetchAllSafe($db, "SELECT * FROM custom_safari_requests WHERE customer_id=? ORDER BY created_at DESC", [$id]);

$totalSpent = array_sum(array_map(fn($b) => $b['payment_status'] !== 'cancelled' ? (float)$b['total_amount'] : 0, $bookings));

$bookingStatusColors = [
    'pending'      => 'amber',
    'deposit_paid' => 'blue',
    'fully_paid'   => 'dark',
    'cancelled'    => 'red',
];
$quoteStatusColors = [
    'draft' => 'gray', 'sent' => 'blue',
    'accepted' => 'green', 'declined' => 'red',
    'expired' => 'red', 'converted' => 'green',
];

ob_start();
?>

<div style="margin-bottom:1.25rem">
  <a href="customers.php" class="admin-btn admin-btn--outline" style="font-size:.75rem">← All Customers</a>
</div>

<!-- Profile header -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-body" style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:1.5rem">
    <div>
      <h2 style="margin:0 0 .35rem;font-size:1.4rem"><?= htmlspecialchars($customer['full_name']) ?></h2>
      <div style="font-size:.85rem;color:var(--gray-500);line-height:1.9">
        <a href="mailto:<?= htmlspecialchars($customer['email']) ?>" style="color:inherit"><?= htmlspecialchars($customer['email']) ?></a>
        <?php if ($customer['phone']): ?>
          &nbsp;·&nbsp;
          <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$customer['phone']) ?>" target="_blank" style="color:inherit"><?= htmlspecialchars($customer['phone']) ?></a>
        <?php endif; ?>
        <?php if ($customer['country']): ?> &nbsp;·&nbsp; <?= htmlspecialchars($customer['country']) ?><?php endif; ?>
        <br><span style="font-size:.72rem;color:var(--gray-500)">Customer since <?= date('d F Y', strtotime($customer['first_seen_at'])) ?></span>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,auto);gap:1.5rem;text-align:center">
      <div><div style="font-family:var(--font-disp);font-size:1.4rem"><?= count($bookings) ?></div><div style="font-size:.6rem;text-transform:uppercase;letter-spacing:.08em;color:var(--gray-500)">Bookings</div></div>
      <div><div style="font-family:var(--font-disp);font-size:1.4rem">$<?= number_format($totalSpent,0) ?></div><div style="font-size:.6rem;text-transform:uppercase;letter-spacing:.08em;color:var(--gray-500)">Lifetime Value</div></div>
      <div><div style="font-family:var(--font-disp);font-size:1.4rem"><?= count($quotes) ?></div><div style="font-size:.6rem;text-transform:uppercase;letter-spacing:.08em;color:var(--gray-500)">Quotations</div></div>
      <div><div style="font-family:var(--font-disp);font-size:1.4rem"><?= count($leads) + count($requests) ?></div><div style="font-size:.6rem;text-transform:uppercase;letter-spacing:.08em;color:var(--gray-500)">Enquiries</div></div>
    </div>
  </div>
</div>

<!-- Bookings -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Bookings (<?= count($bookings) ?>)</h3></div>
  <?php if ($bookings): ?>
  <div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Ref</th><th>Package</th><th>Travel Date</th><th style="text-align:right">Amount</th><th>Status</th><th>Booked</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($bookings as $b): $badge = $bookingStatusColors[$b['payment_status']] ?? 'gray'; ?>
    <tr>
      <td><code><?= htmlspecialchars($b['booking_ref']) ?></code></td>
      <td style="font-size:.82rem"><?= htmlspecialchars($b['package_name'] ?? '—') ?></td>
      <td style="font-size:.8rem"><?= $b['travel_date'] ? date('d M Y', strtotime($b['travel_date'])) : '—' ?></td>
      <td style="text-align:right;font-weight:700">$<?= number_format((float)$b['total_amount'],0) ?></td>
      <td><span class="badge-status badge-status--<?= $badge ?>"><?= ucfirst(str_replace('_',' ',$b['payment_status'])) ?></span></td>
      <td style="font-size:.72rem;color:var(--gray-500)"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
      <td><a href="bookings.php?view=<?= urlencode($b['booking_ref']) ?>" class="admin-btn admin-btn--outline" style="font-size:.62rem;padding:.25rem .5rem">View →</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
  <div class="admin-empty">No bookings yet.</div>
  <?php endif; ?>
</div>

<!-- Quotations -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Quotations (<?= count($quotes) ?>)</h3></div>
  <?php if ($quotes): ?>
  <div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Ref</th><th>Package</th><th style="text-align:right">Grand Total</th><th>Status</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($quotes as $q): $badge = $quoteStatusColors[$q['status']] ?? 'gray'; ?>
    <tr>
      <td><code><?= htmlspecialchars($q['quote_ref']) ?></code></td>
      <td style="font-size:.82rem"><?= htmlspecialchars($q['package_name'] ?: 'Custom itinerary') ?></td>
      <td style="text-align:right;font-weight:700">$<?= number_format((float)$q['grand_total'],0) ?></td>
      <td><span class="badge-status badge-status--<?= $badge ?>"><?= ucfirst($q['status']) ?></span></td>
      <td style="font-size:.72rem;color:var(--gray-500)"><?= date('d M Y', strtotime($q['created_at'])) ?></td>
      <td><a href="quotation_new.php?id=<?= $q['id'] ?>" class="admin-btn admin-btn--outline" style="font-size:.62rem;padding:.25rem .5rem">View →</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
  <div class="admin-empty">No quotations yet.</div>
  <?php endif; ?>
</div>

<!-- Leads -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Sales Leads (<?= count($leads) ?>)</h3></div>
  <?php if ($leads): ?>
  <div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Stage</th><th>Package Interest</th><th>Budget</th><th>Created</th></tr></thead>
    <tbody>
    <?php foreach ($leads as $l): ?>
    <tr>
      <td style="font-size:.78rem;text-transform:capitalize"><?= htmlspecialchars(str_replace('_',' ',$l['pipeline_stage'])) ?></td>
      <td style="font-size:.82rem"><?= htmlspecialchars($l['package_name'] ?? '—') ?></td>
      <td style="font-size:.8rem;color:var(--gray-500)"><?= htmlspecialchars($l['budget_range'] ?? '—') ?></td>
      <td style="font-size:.72rem;color:var(--gray-500)"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
  <div class="admin-empty">No sales leads yet.</div>
  <?php endif; ?>
</div>

<!-- Custom safari requests -->
<div class="admin-card">
  <div class="admin-card-header"><h3>Custom Safari Enquiries (<?= count($requests) ?>)</h3></div>
  <?php if ($requests): ?>
  <div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Status</th><th>Destinations</th><th>Duration</th><th>Budget</th><th>Enquired</th></tr></thead>
    <tbody>
    <?php foreach ($requests as $r): ?>
    <tr>
      <td style="font-size:.78rem;text-transform:capitalize"><?= htmlspecialchars(str_replace('_',' ',$r['status'])) ?></td>
      <td style="font-size:.82rem"><?= htmlspecialchars($r['destinations'] ?? '—') ?></td>
      <td style="font-size:.8rem;color:var(--gray-500)"><?= $r['duration_days'] ? $r['duration_days'].' days' : '—' ?></td>
      <td style="font-size:.8rem;color:var(--gray-500)"><?= htmlspecialchars($r['total_budget'] ?? '—') ?></td>
      <td style="font-size:.72rem;color:var(--gray-500)"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
  <div class="admin-empty">No custom safari enquiries yet.</div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';