<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Invoices';
$myId        = (int)($_SESSION['admin_user_id'] ?? 0);
$isAgentUser = AuthMiddleware::isAgentRole();

// Invoices aren't a separate record — every confirmed booking already is
// one (a real charge, a client, a due/paid amount). This page is a
// dedicated financial view over bookings.* instead of a second, parallel
// "invoices" table that could drift from what bookings.php actually
// charges and collects. Each row prints via the existing booking PDF
// (api/booking_pdf.php), which already carries everything an invoice
// needs — reference number, amounts, deposit/balance, dates.

// Ownership, for the agent role only: a booking has no agent/owner column
// of its own, so "my invoices" is derived the same way commission
// attribution already works — a booking counts as an agent's own if it
// came from their own quotation (quotations.created_by) or is linked to
// their own commission record (i.e. it originated from their own lead
// converting). staff/admin/superadmin see every invoice, unrestricted.
$ownerJoin  = $isAgentUser
    ? "LEFT JOIN quotations oq ON oq.id = b.quotation_id
       LEFT JOIN commissions oc ON oc.booking_ref = b.booking_ref"
    : "";
$ownerWhere = $isAgentUser ? "(oq.created_by = ? OR oc.agent_id = ?)" : "";
$ownerParams = $isAgentUser ? [$myId, $myId] : [];

// ── Filters + pagination ────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$status  = trim($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($ownerWhere) { $where[] = $ownerWhere; $params = array_merge($params, $ownerParams); }
if ($search) {
    $where[]  = "(b.booking_ref LIKE ? OR b.full_name LIKE ? OR b.email LIKE ? OR b.package_name LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like, $like]);
}
if ($status) { $where[] = "b.payment_status=?"; $params[] = $status; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$invoices = []; $totalRows = 0; $totalPages = 0;
try {
    $total = $db->prepare("SELECT COUNT(DISTINCT b.id) FROM bookings b $ownerJoin $whereSQL");
    $total->execute($params);
    $totalRows  = (int)$total->fetchColumn();
    $totalPages = (int)ceil($totalRows / $perPage);
    $stmt = $db->prepare("SELECT DISTINCT b.* FROM bookings b $ownerJoin $whereSQL ORDER BY b.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
} catch (\Exception $e) {
    error_log($e->getMessage());
}

// Status pill counts — unrestricted by the current filter (but still
// ownership-scoped for an agent), same pattern as bookings.php's
// $statusCounts.
$statusCounts = [];
try {
    $countWhereSQL = $ownerWhere ? "WHERE $ownerWhere" : '';
    $stmt = $db->prepare("SELECT b.payment_status, COUNT(DISTINCT b.id) as c FROM bookings b $ownerJoin $countWhereSQL GROUP BY b.payment_status");
    $stmt->execute($ownerParams);
    foreach ($stmt->fetchAll() as $r) $statusCounts[$r['payment_status']] = (int)$r['c'];
} catch (\Exception $e) {}

$statusColors = [
    'pending'      => ['bg' => 'var(--amber-light)', 'color' => '#92400e', 'badge' => 'amber', 'label' => 'Pending'],
    'deposit_paid' => ['bg' => 'var(--blue-light)',  'color' => '#1e40af', 'badge' => 'blue',  'label' => 'Deposit Paid'],
    'fully_paid'   => ['bg' => 'var(--black)',       'color' => 'var(--white)', 'badge' => 'dark', 'label' => 'Fully Paid'],
    'cancelled'    => ['bg' => 'var(--red-light)',   'color' => '#991b1b', 'badge' => 'red',   'label' => 'Cancelled'],
];

// Portfolio totals — invoiced (every non-cancelled charge), collected
// (what's actually been paid per status), outstanding (the gap between
// them). Cancelled bookings are excluded from all three: nothing is
// truly owed or collectible against a cancelled invoice.
$totals = ['invoiced' => 0.0, 'collected' => 0.0];
try {
    $totalsWhere = "WHERE b.payment_status != 'cancelled'" . ($ownerWhere ? " AND $ownerWhere" : '');
    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(b.total_amount),0) AS invoiced,
            COALESCE(SUM(CASE
                WHEN b.payment_status='fully_paid' THEN b.total_amount
                WHEN b.payment_status='deposit_paid' THEN b.deposit_amount
                ELSE 0
            END),0) AS collected
        FROM bookings b $ownerJoin $totalsWhere
    ");
    $stmt->execute($ownerParams);
    $r = $stmt->fetch();
    $totals['invoiced']  = (float)($r['invoiced'] ?? 0);
    $totals['collected'] = (float)($r['collected'] ?? 0);
} catch (\Exception $e) {}
$totals['outstanding'] = max(0, $totals['invoiced'] - $totals['collected']);

ob_start();
?>

<!-- Portfolio stats -->
<div class="admin-metrics-grid" style="margin-bottom:1.5rem">
  <div class="metric-card">
    <span class="metric-label">Total Invoiced</span>
    <span class="metric-value">$<?= number_format($totals['invoiced'], 0) ?></span>
  </div>
  <div class="metric-card metric-card--green">
    <span class="metric-label">Collected</span>
    <span class="metric-value">$<?= number_format($totals['collected'], 0) ?></span>
  </div>
  <div class="metric-card metric-card--amber">
    <span class="metric-label">Outstanding</span>
    <span class="metric-value">$<?= number_format($totals['outstanding'], 0) ?></span>
  </div>
  <div class="metric-card">
    <span class="metric-label">Invoices</span>
    <span class="metric-value"><?= number_format($totalRows) ?></span>
  </div>
</div>

<!-- Status filter pills -->
<div class="admin-filter-pills">
  <a href="invoices.php" class="admin-filter-pill<?= !$status ? ' active' : '' ?>">
    All &nbsp;<span style="opacity:.6"><?= number_format(array_sum($statusCounts)) ?></span>
  </a>
  <?php foreach ($statusColors as $sv => $sc): ?>
  <a href="?status=<?= $sv ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
     class="admin-filter-pill admin-filter-pill--<?= $sc['badge'] ?><?= $status === $sv ? ' active' : '' ?>">
    <?= $sc['label'] ?> &nbsp;<span style="opacity:.6"><?= $statusCounts[$sv] ?? 0 ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- Search bar -->
<form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem">
  <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
  <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
         placeholder="Search invoice #, client, package…" class="admin-input" style="min-width:260px">
  <button class="admin-btn admin-btn--primary">Search</button>
  <?php if ($search || $status): ?>
  <a href="invoices.php" class="admin-btn admin-btn--outline">Clear</a>
  <?php endif; ?>
</form>

<!-- Table -->
<div class="admin-table-wrap">
<table class="admin-table">
  <thead><tr>
    <th>Invoice #</th><th>Client</th><th>Package</th><th>Travel Date</th>
    <th style="text-align:right">Total</th><th style="text-align:right">Paid</th><th style="text-align:right">Balance</th>
    <th>Status</th><th>Date</th><th>Actions</th>
  </tr></thead>
  <tbody>
  <?php foreach ($invoices as $inv):
    $sc      = $statusColors[$inv['payment_status']] ?? $statusColors['pending'];
    $total   = (float)$inv['total_amount'];
    $deposit = (float)$inv['deposit_amount'];
    $paid    = match ($inv['payment_status']) {
        'fully_paid'   => $total,
        'deposit_paid' => $deposit,
        default        => 0.0,
    };
    $balance = $inv['payment_status'] === 'cancelled' ? null : max(0, $total - $paid);
  ?>
  <tr>
    <td><code><?= htmlspecialchars($inv['booking_ref']) ?></code></td>
    <td>
      <strong style="font-size:.85rem"><?= htmlspecialchars($inv['full_name']) ?></strong>
      <div style="font-size:.7rem;color:var(--gray-500)"><?= htmlspecialchars($inv['email']) ?></div>
    </td>
    <td style="font-size:.8rem"><?= htmlspecialchars($inv['package_name'] ?? '—') ?></td>
    <td style="font-size:.78rem;white-space:nowrap"><?= $inv['travel_date'] ? date('d M Y', strtotime($inv['travel_date'])) : '—' ?></td>
    <td style="text-align:right;font-weight:600;font-size:.85rem">$<?= number_format($total, 0) ?></td>
    <td style="text-align:right;font-size:.82rem;color:var(--green)">$<?= number_format($paid, 0) ?></td>
    <td style="text-align:right;font-size:.82rem;<?= $balance ? 'color:var(--red);font-weight:600' : 'color:var(--gray-500)' ?>">
      <?= $balance === null ? '—' : '$' . number_format($balance, 0) ?>
    </td>
    <td><span class="badge-status badge-status--<?= $sc['badge'] ?>"><?= $sc['label'] ?></span></td>
    <td style="font-size:.72rem;color:var(--gray-500);white-space:nowrap"><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
    <td style="white-space:nowrap">
      <a href="../api/booking_pdf.php?ref=<?= urlencode($inv['booking_ref']) ?>" target="_blank"
         class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .55rem">View</a>
      <a href="bookings.php?view=<?= urlencode($inv['booking_ref']) ?>"
         class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .55rem">Booking ↗</a>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (empty($invoices)): ?>
  <tr><td colspan="10" class="admin-empty">No invoices found.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination" style="border-top:none">
  <span style="font-size:.75rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?></span>
  <div class="page-btns">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
       class="page-btn<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
