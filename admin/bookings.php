<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Services\OwnershipScope;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

// ── AJAX handlers ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $ref     = trim($_POST['booking_ref'] ?? '');
        $status  = trim($_POST['status'] ?? '');
        $allowed = ['pending','deposit_paid','fully_paid','cancelled'];
        if (OwnershipScope::isScoped() && !OwnershipScope::ownsBooking($db, $ref, OwnershipScope::currentAgentId())) {
            \App\Services\AccessGuard::denyJson('booking', $ref, 'update_status:' . $status);
        }
        // Per-user permission override (admin/users.php) — doesn't apply to
        // agents, who are already scoped by ownership above.
        if (!AuthMiddleware::isAgentRole() && !AuthMiddleware::canChangeStatus()) {
            \App\Services\AccessGuard::denyJson('booking', $ref, 'update_status:' . $status);
        }
        // No payment gateway is wired up — "Deposit Paid" always means an
        // admin is attesting to a bank transfer that already happened off
        // platform, so the actual amount has to be entered by hand rather
        // than assumed to match the suggested deposit_amount a guest can
        // plausibly over- or under-pay in practice.
        $amountPaid = null;
        if ($status === 'deposit_paid') {
            $amountPaid = (float)($_POST['amount_paid'] ?? 0);
            if ($amountPaid <= 0) {
                echo json_encode(['success'=>false,'message'=>'Enter the amount actually received.']);
                exit;
            }
        }

        if ($ref && in_array($status, $allowed, true)) {
            $prevStatus = $db->prepare("SELECT payment_status FROM bookings WHERE booking_ref=? LIMIT 1");
            $prevStatus->execute([$ref]);
            $prevStatus = $prevStatus->fetchColumn();
            $wasFullyPaid    = $prevStatus === 'fully_paid';
            $wasDepositPaid  = $prevStatus === 'deposit_paid';

            if ($status === 'deposit_paid') {
                $db->prepare("UPDATE bookings SET payment_status=?, amount_paid=?, updated_at=NOW() WHERE booking_ref=?")
                   ->execute([$status, $amountPaid, $ref]);
            } elseif ($status === 'fully_paid') {
                // Fully paid is unambiguous — the whole total was received,
                // no manual figure needed regardless of what deposit_paid
                // (if any) was previously recorded at.
                $db->prepare("UPDATE bookings SET payment_status=?, amount_paid=total_amount, updated_at=NOW() WHERE booking_ref=?")
                   ->execute([$status, $ref]);
            } else {
                $db->prepare("UPDATE bookings SET payment_status=?, updated_at=NOW() WHERE booking_ref=?")
                   ->execute([$status, $ref]);
            }
            \App\Services\ActivityLog::record('update_status', 'booking', 0, ['booking_ref'=>$ref,'status'=>$status,'amount_paid'=>$amountPaid]);

            $invoiceEmailSent = false;
            // Fires once per genuine transition INTO fully_paid — not on
            // every save of an already-paid booking — converting the
            // Quotation/Booking PDF into its Invoice form (PdfRenderer's
            // *Invoice() methods re-verify payment_status themselves) and
            // emailing it straight to the client, no admin action needed.
            if ($status === 'fully_paid' && !$wasFullyPaid && AUTO_SEND_PAYMENT_EMAILS) {
                try {
                    $bs = $db->prepare("SELECT * FROM bookings WHERE booking_ref=? LIMIT 1");
                    $bs->execute([$ref]);
                    $bRow = $bs->fetch();
                    if ($bRow) {
                        $invoiceEmailSent = \App\Services\InvoiceMailer::sendForBooking($bRow);
                    }
                } catch (\Throwable $e) {
                    error_log('[SZV Invoice] ' . $e->getMessage());
                }
            }
            // Same idea, once per genuine transition INTO deposit_paid —
            // the Quotation PDF already relabels itself as a partial
            // Invoice from the live payment_status (see api/quotation_pdf.php),
            // this just emails that same document with the real figures.
            if ($status === 'deposit_paid' && !$wasDepositPaid && AUTO_SEND_PAYMENT_EMAILS) {
                try {
                    $bs = $db->prepare("SELECT * FROM bookings WHERE booking_ref=? LIMIT 1");
                    $bs->execute([$ref]);
                    $bRow = $bs->fetch();
                    if ($bRow) {
                        $invoiceEmailSent = \App\Services\InvoiceMailer::sendDepositReceipt($bRow);
                    }
                } catch (\Throwable $e) {
                    error_log('[SZV Invoice] ' . $e->getMessage());
                }
            }

            echo json_encode(['success'=>true, 'invoice_email_sent'=>$invoiceEmailSent]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Invalid data.']);
        }
        exit;
    }
    if ($action === 'update_booking') {
        if (!AuthMiddleware::isAdminRole()) {
            \App\Services\AccessGuard::denyJson('booking', trim($_POST['booking_ref'] ?? ''), 'update_booking');
        }
        $ref = trim($_POST['booking_ref'] ?? '');
        $existing = $db->prepare("SELECT * FROM bookings WHERE booking_ref=? LIMIT 1");
        $existing->execute([$ref]);
        $existing = $existing->fetch();
        if (!$ref || !$existing) {
            echo json_encode(['success'=>false,'message'=>'Booking not found.']);
            exit;
        }

        $fullName  = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $travelDate = trim($_POST['travel_date'] ?? '');
        $adults    = (int)($_POST['adults'] ?? 0);
        $children  = max(0, (int)($_POST['children'] ?? 0));
        $accLevel  = trim($_POST['accommodation_level'] ?? '');
        $specialRequest = trim($_POST['special_request'] ?? '');
        $totalAmount   = (float)($_POST['total_amount'] ?? -1);
        $depositAmount = (float)($_POST['deposit_amount'] ?? -1);
        $packageId = trim($_POST['package_id'] ?? '');
        $itineraryId = trim($_POST['itinerary_id'] ?? '');

        $errors = [];
        if ($fullName === '') $errors[] = 'Guest name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        if ($phone === '') $errors[] = 'Phone is required.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $travelDate) || !strtotime($travelDate)) $errors[] = 'Travel date is invalid.';
        if ($adults < 1) $errors[] = 'At least 1 adult is required.';
        $allowedLevels = ['budget','midrange','highend','luxury','ultra_luxury'];
        if (!in_array($accLevel, $allowedLevels, true)) $errors[] = 'Invalid accommodation level.';
        if ($totalAmount < 0) $errors[] = 'Total amount must be 0 or more.';
        if ($depositAmount < 0) $errors[] = 'Deposit amount must be 0 or more.';
        if ($depositAmount > $totalAmount) $errors[] = 'Deposit cannot exceed the total amount.';

        $packageName = $existing['package_name'];
        $packageIdVal = null;
        if ($packageId !== '') {
            $pkg = $db->prepare("SELECT id, name FROM packages WHERE id=? LIMIT 1");
            $pkg->execute([(int)$packageId]);
            $pkg = $pkg->fetch();
            if (!$pkg) { $errors[] = 'Selected package does not exist.'; }
            else { $packageIdVal = (int)$pkg['id']; $packageName = $pkg['name']; }
        }
        $itineraryIdVal = null;
        if ($itineraryId !== '') {
            $it = $db->prepare("SELECT id FROM itineraries WHERE id=? LIMIT 1");
            $it->execute([(int)$itineraryId]);
            if (!$it->fetch()) { $errors[] = 'Selected itinerary does not exist.'; }
            else { $itineraryIdVal = (int)$itineraryId; }
        }

        if ($errors) {
            echo json_encode(['success'=>false,'message'=>implode(' ', $errors)]);
            exit;
        }

        $db->prepare("UPDATE bookings SET
                full_name=?, email=?, phone=?, travel_date=?, adults=?, children=?,
                accommodation_level=?, special_request=?, total_amount=?, deposit_amount=?,
                package_id=?, package_name=?, itinerary_id=?, updated_at=NOW()
              WHERE booking_ref=?")
           ->execute([
                $fullName, $email, $phone, $travelDate, $adults, $children,
                $accLevel, $specialRequest, $totalAmount, $depositAmount,
                $packageIdVal, $packageName, $itineraryIdVal,
                $ref,
            ]);

        \App\Services\ActivityLog::record('update_booking', 'booking', 0, [
            'booking_ref' => $ref,
            'full_name'   => $fullName,
            'email'       => $email,
            'total_amount'   => $totalAmount,
            'deposit_amount' => $depositAmount,
        ]);

        $s = $db->prepare("SELECT * FROM bookings WHERE booking_ref=?");
        $s->execute([$ref]);
        echo json_encode(['success'=>true, 'booking'=>$s->fetch()]);
        exit;
    }
    if ($action === 'get_booking') {
        $ref = trim($_POST['booking_ref'] ?? '');
        if (OwnershipScope::isScoped() && !OwnershipScope::ownsBooking($db, $ref, OwnershipScope::currentAgentId())) {
            \App\Services\AccessGuard::denyJson('booking', $ref, 'get_booking');
        }
        $s = $db->prepare("SELECT * FROM bookings WHERE booking_ref=?");
        $s->execute([$ref]);
        $b = $s->fetch();
        echo json_encode($b ?: ['error'=>'Not found']);
        exit;
    }
    exit;
}

// ── Filters ───────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$status  = trim($_GET['status'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page-1)*$perPage;

$where = []; $params = [];
if ($search) {
    $where[]  = "(bookings.booking_ref LIKE ? OR bookings.full_name LIKE ? OR bookings.email LIKE ? OR bookings.phone LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params,[$like,$like,$like,$like]);
}
if ($status) { $where[] = "bookings.payment_status=?"; $params[] = $status; }
if (OwnershipScope::isScoped()) {
    [$scopeSql, $scopeParams] = OwnershipScope::bookingsListSql(OwnershipScope::currentAgentId());
    $where[]  = $scopeSql;
    $params   = array_merge($params, $scopeParams);
}
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

try {
    $total = $db->prepare("SELECT COUNT(*) FROM bookings $whereSQL");
    $total->execute($params);
    $totalRows  = (int)$total->fetchColumn();
    $totalPages = (int)ceil($totalRows/$perPage);
    $stmt = $db->prepare("
        SELECT bookings.*, COALESCE(p.duration_days, i.duration_days) AS trip_duration, agent.full_name AS agent_name
        FROM bookings
        LEFT JOIN packages p ON p.id = bookings.package_id
        LEFT JOIN itineraries i ON i.id = bookings.itinerary_id
        LEFT JOIN quotations q ON q.id = bookings.quotation_id
        LEFT JOIN admin_users agent ON agent.id = q.created_by AND agent.role = 'agent'
        $whereSQL ORDER BY bookings.created_at DESC LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch(\Exception $e) {
    error_log($e->getMessage());
    $bookings = []; $totalRows = 0; $totalPages = 0;
}

$viewRef = trim($_GET['view'] ?? '');

$statusCounts = [];
try {
    $countWhere  = '';
    $countParams = [];
    if (OwnershipScope::isScoped()) {
        [$scopeSql, $scopeParams] = OwnershipScope::bookingsListSql(OwnershipScope::currentAgentId());
        $countWhere  = "WHERE $scopeSql";
        $countParams = $scopeParams;
    }
    $cs = $db->prepare("SELECT payment_status, COUNT(*) as c FROM bookings $countWhere GROUP BY payment_status");
    $cs->execute($countParams);
    foreach ($cs->fetchAll() as $r) $statusCounts[$r['payment_status']] = $r['c'];
} catch(\Exception $e) {}

// One status→color config, shared with the JS below via STATUS_META
// (see script block) instead of the PHP map, the status-update JS map,
// and the detail-modal JS map each independently hand-maintaining the
// same four colors.
$statusColors = [
    'pending'      => ['bg'=>'var(--amber-light)','color'=>'#92400e','badge'=>'amber','label'=>'Pending'],
    'deposit_paid' => ['bg'=>'var(--blue-light)', 'color'=>'#1e40af','badge'=>'blue', 'label'=>'Deposit Paid'],
    'fully_paid'   => ['bg'=>'var(--black)',       'color'=>'var(--white)','badge'=>'dark', 'label'=>'Fully Paid'],
    'cancelled'    => ['bg'=>'var(--red-light)',  'color'=>'#991b1b','badge'=>'red',  'label'=>'Cancelled'],
];

// Package/itinerary pickers for the Edit Booking form — admin-role only
// feature, but cheap enough to just always fetch.
$editPackages   = $db->query("SELECT id, name FROM packages WHERE is_active=1 ORDER BY name")->fetchAll();
$editItineraries = $db->query("SELECT id, name FROM itineraries WHERE is_active=1 ORDER BY name")->fetchAll();

$pageTitle = 'Bookings';
ob_start();
?>

<!-- Status filter pills -->
<div class="admin-filter-pills">
  <a href="bookings.php" class="admin-filter-pill<?= !$status?' active':'' ?>">
    All &nbsp;<span style="opacity:.6"><?= number_format($totalRows) ?></span>
  </a>
  <?php foreach ($statusColors as $sv => $sc): ?>
  <a href="?status=<?= $sv ?><?= $search?'&search='.urlencode($search):'' ?>"
     class="admin-filter-pill admin-filter-pill--<?= $sc['badge'] ?><?= $status===$sv?' active':'' ?>">
    <?= $sc['label'] ?> &nbsp;<span style="opacity:.6"><?= $statusCounts[$sv] ?? 0 ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- Search bar -->
<form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>"><?php endif; ?>
  <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
         placeholder="Search ref, name, email, phone…"
         class="admin-input" style="min-width:260px;flex:1">
  <button class="admin-btn admin-btn--primary">Search</button>
  <?php if ($search || $status): ?>
  <a href="bookings.php" class="admin-btn admin-btn--outline">Clear</a>
  <?php endif; ?>
</form>

<!-- Table -->
<div class="admin-table-wrap">
<table class="admin-table">
  <thead>
    <tr>
      <th>Reference</th>
      <th>Client</th>
      <th>Package</th>
      <th style="text-align:center">Guests</th>
      <th>Travel Date</th>
      <th>Trip</th>
      <th>Total / Deposit</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($bookings as $b):
      $sc = $statusColors[$b['payment_status']] ?? $statusColors['pending'];
    ?>
    <tr id="row-<?= htmlspecialchars($b['booking_ref']) ?>"
        class="<?= ($viewRef && $viewRef===$b['booking_ref']) ? 'highlighted-row' : '' ?>">
      <td><code><?= htmlspecialchars($b['booking_ref']) ?></code></td>
      <td>
        <div style="font-weight:600;font-size:.85rem">
          <?= htmlspecialchars($b['full_name']) ?>
          <?php if (!empty($b['agent_name'])): ?>
          <span class="badge-status badge-status--blue" style="font-size:.58rem;margin-left:.3rem;vertical-align:middle" title="Booked by agent <?= htmlspecialchars($b['agent_name']) ?>">Agent: <?= htmlspecialchars($b['agent_name']) ?></span>
          <?php endif; ?>
        </div>
        <div style="font-size:.72rem;color:#9ca3af;margin-top:.1rem">
          <?= htmlspecialchars($b['email']) ?><br>
          <?= htmlspecialchars($b['phone'] ?? '') ?>
        </div>
      </td>
      <td style="font-size:.82rem;max-width:160px">
        <?= htmlspecialchars($b['package_name'] ?? '—') ?>
        <?php if (!empty($b['accommodation_level'])): ?>
        <div style="font-size:.68rem;color:#9ca3af"><?= ucfirst($b['accommodation_level']) ?></div>
        <?php endif; ?>
      </td>
      <td style="text-align:center;font-weight:600"><?= (int)$b['travelers'] ?></td>
      <td style="white-space:nowrap;font-size:.82rem">
        <?= date('d M Y', strtotime($b['travel_date'])) ?>
      </td>
      <td style="white-space:nowrap">
        <?php if ($b['payment_status'] !== 'cancelled'): $ts = \App\Helpers\Formatter::tripStatus($b['travel_date'], $b['trip_duration'] ? (int)$b['trip_duration'] : null); ?>
        <span style="background:<?= $ts['bg'] ?>;color:<?= $ts['color'] ?>;font-size:.68rem;font-weight:600;padding:.15rem .5rem;border-radius:2px"><?= $ts['label'] ?></span>
        <?php else: ?>
        <span style="color:var(--gray-400);font-size:.72rem">—</span>
        <?php endif; ?>
      </td>
      <td style="white-space:nowrap">
        <div style="font-weight:700"><?= $b['total_amount'] ? '$'.number_format($b['total_amount'],0) : '—' ?></div>
        <?php if (in_array($b['payment_status'], ['deposit_paid','fully_paid'], true) && $b['amount_paid']): ?>
        <div style="font-size:.68rem;color:#9ca3af">paid: $<?= number_format($b['amount_paid'],0) ?></div>
        <?php elseif ($b['deposit_amount']): ?>
        <div style="font-size:.68rem;color:#9ca3af">dep: $<?= number_format($b['deposit_amount'],0) ?></div>
        <?php endif; ?>
      </td>
      <td>
        <select onchange="updateStatus('<?= htmlspecialchars($b['booking_ref']) ?>', this.value, this)"
                data-prev="<?= htmlspecialchars($b['payment_status']) ?>"
                class="admin-input" style="padding:.3rem .5rem;font-size:.72rem;cursor:pointer;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-weight:700;max-width:130px">
          <?php foreach ($statusColors as $sv => $sl): ?>
          <option value="<?= $sv ?>" <?= $b['payment_status']===$sv?'selected':'' ?>><?= $sl['label'] ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td style="white-space:nowrap">
        <!-- View detail modal — Quotation/Itinerary downloads, Voucher,
             Quote and Track links all live inside the modal now, so this
             row stays to just the 3 most-used actions. -->
        <button onclick="viewBooking('<?= htmlspecialchars($b['booking_ref']) ?>')"
                class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">
          View
        </button>
        <!-- Email booking confirmation -->
        <a href="../api/booking_email.php?ref=<?= urlencode($b['booking_ref']) ?>"
           onclick="return confirmNavGuard(event, this.href, 'Send booking confirmation email to <?= htmlspecialchars(addslashes($b['email'])) ?>?')"
           class="admin-btn admin-btn--tint-blue" style="font-size:.65rem;padding:.3rem .6rem">
          Email
        </a>
        <!-- WhatsApp quick contact -->
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/','', $b['phone']??'') ?>?text=<?= urlencode('Hi '.$b['full_name'].', this is Safari Zangu regarding your booking '.$b['booking_ref'].'.') ?>"
           target="_blank"
           class="admin-btn admin-btn--success" style="font-size:.65rem;padding:.3rem .6rem">
          WA
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($bookings)): ?>
    <tr><td colspan="9" class="admin-empty">No bookings found.</td></tr>
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
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
       class="page-btn<?= $i===$page?' active':'' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Booking Detail Modal ─────────────────────────────── -->
<div id="bookingModal" class="admin-modal-overlay">
  <div class="admin-modal">
    <div class="admin-modal-header" style="position:sticky;top:0;background:var(--white);z-index:1">
      <span class="admin-modal-title" id="modalTitle">Booking Detail</span>
      <button onclick="closeModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div id="modalBody" style="padding:0 0 1rem"></div>
  </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script>
var BASE = '<?= BASE_PATH ?>';
var STATUS_META = <?= json_encode($statusColors) ?>;
var CAN_EDIT_BOOKING = <?= AuthMiddleware::isAdminRole() ? 'true' : 'false' ?>;
var EDIT_PACKAGES = <?= json_encode($editPackages) ?>;
var EDIT_ITINERARIES = <?= json_encode($editItineraries) ?>;
var ACC_LEVELS = [
  ['budget','Budget-Friendly'],['midrange','Mid-Range'],['highend','High-End'],
  ['luxury','Luxury'],['ultra_luxury','Ultra Luxury']
];
var CURRENT_BOOKING = null;

async function updateStatus(ref, newStatus, sel) {
  var label = sel.options[sel.selectedIndex].text;
  var amountPaid = null;

  if (newStatus === 'deposit_paid') {
    // No payment gateway wired up — this dialog IS the confirmation for
    // the whole status change, not an extra step before one: entering a
    // real amount and saving is what moving to "Deposit Paid" means here.
    amountPaid = await promptDialog(
      'How much did the guest actually transfer for booking ' + ref + '? This is entered by hand — there\'s no bank integration to verify it automatically.',
      { title: 'Amount Received (USD)', inputType: 'number', step: '0.01', min: '0', placeholder: 'e.g. 1200.00', confirmLabel: 'Save & Mark Deposit Paid' }
    );
    if (amountPaid === null) { sel.value = sel.dataset.prev; return; }
    if (!(parseFloat(amountPaid) > 0)) {
      toast('Enter an amount greater than 0', 'err');
      sel.value = sel.dataset.prev;
      return;
    }
  } else {
    if (!(await confirmStatusChange(sel, 'Change booking '+ref+' status to "'+label+'"?', {danger: newStatus==='cancelled'}))) return;
  }

  var fd = new FormData();
  fd.append('action','update_status');
  fd.append('booking_ref',ref);
  fd.append('status',newStatus);
  if (amountPaid !== null) fd.append('amount_paid', amountPaid);
  fd.append('_csrf', window.CSRF_TOKEN || '');
  try {
    var r = await fetch('bookings.php',{method:'POST',body:fd});
    var d = await r.json();
    if (d.success) {
      toast(d.invoice_email_sent ? 'Status updated — Invoice emailed to client' : 'Status updated: '+newStatus,'ok');
      var meta = STATUS_META[newStatus];
      if (meta) { sel.style.background = meta.bg; sel.style.color = meta.color; }
      sel.dataset.prev = newStatus;
    } else {
      sel.value = sel.dataset.prev;
      toast('Failed: '+(d.message||'Error'),'err');
    }
  } catch(e) {
    sel.value = sel.dataset.prev;
    toast('Network error','err');
  }
}

async function viewBooking(ref) {
  var fd = new FormData();
  fd.append('action','get_booking');
  fd.append('booking_ref',ref);
  fd.append('_csrf', window.CSRF_TOKEN || '');
  var r = await fetch('bookings.php',{method:'POST',body:fd});
  var b = await r.json();
  if (b.error) { toast('Not found','err'); return; }
  CURRENT_BOOKING = b;

  document.getElementById('modalTitle').textContent = 'Booking — ' + b.booking_ref;

  var meta = STATUS_META[b.payment_status] || {};

  var travelDate = b.travel_date ? new Date(b.travel_date).toLocaleDateString('en-GB',{day:'2-digit',month:'long',year:'numeric'}) : '—';
  var bookedDate = b.created_at  ? new Date(b.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'long',year:'numeric'}) : '—';

  var rows = [
    ['Reference',      '<code>'+b.booking_ref+'</code>'],
    ['Guest',          '<strong>'+b.full_name+'</strong>'],
    ['Email',          '<a href="mailto:'+b.email+'" style="color:inherit">'+b.email+'</a>'],
    ['Phone',          '<a href="https://wa.me/'+(b.phone||'').replace(/[^0-9]/g,'')+'?text='+encodeURIComponent('Hi '+b.full_name+', Safari Zangu here regarding booking '+b.booking_ref+'.')+'" target="_blank" style="color:inherit">'+b.phone+'</a>'],
    ['Package',        b.package_name||'—'],
    ['Accommodation',  b.accommodation_level ? (b.accommodation_level.charAt(0).toUpperCase()+b.accommodation_level.slice(1).replace(/_/g,' ')) : '—'],
    ['Guests',         (b.adults||b.travelers)+' adult'+(parseInt(b.adults||b.travelers)>1?'s':'')+(b.children>0?' + '+b.children+' child':'')],
    ['Travel Date',    '<strong>'+travelDate+'</strong>'],
    ['Total Amount',   b.total_amount   ? '<strong>USD $'+Number(b.total_amount).toLocaleString('en-US',{minimumFractionDigits:2})+'</strong>' : '—'],
  ];
  // Once a real payment is on file, show what was actually received
  // (amount_paid — entered by hand or via PayPal) instead of the merely
  // suggested deposit_amount, same real-vs-suggested distinction the
  // Quotation/Invoice PDF and Track Booking page make.
  if (b.payment_status === 'deposit_paid' || b.payment_status === 'fully_paid') {
    var realBalance = Math.max(0, (b.total_amount||0) - (b.amount_paid||0));
    rows.push(['Amount Paid', 'USD $'+Number(b.amount_paid||0).toLocaleString('en-US',{minimumFractionDigits:2})]);
    rows.push(['Balance Due', 'USD $'+realBalance.toLocaleString('en-US',{minimumFractionDigits:2})]);
  } else {
    rows.push(['Deposit (25%)', b.deposit_amount ? 'USD $'+Number(b.deposit_amount).toLocaleString('en-US',{minimumFractionDigits:2}) : '—']);
    rows.push(['Balance Due',   (b.total_amount && b.deposit_amount) ? 'USD $'+(b.total_amount - b.deposit_amount).toLocaleString('en-US',{minimumFractionDigits:2}) : '—']);
  }
  rows = rows.concat([
    ['Payment Status', '<span class="badge-status badge-status--'+(meta.badge||'gray')+'">'+(meta.label||b.payment_status)+'</span>'],
    ['Booked On',      bookedDate],
  ]);
  if (b.special_request)  rows.push(['Special Requests', b.special_request]);
  if (b.trip_extension)   rows.push(['Trip Extension', b.trip_extension]);
  if (b.admin_notes)      rows.push(['Admin Notes', b.admin_notes]);

  var html = rows.map(function(r){
    return '<div class="detail-row"><span class="detail-label">'+r[0]+'</span><span>'+r[1]+'</span></div>';
  }).join('');

  // Quotation/Itinerary PDFs — when a quotation is linked, these must be
  // the real itemized quotation_pdf.php / quotation_itinerary_pdf.php
  // (full cost breakdown), not booking_pdf.php's flat total-only summary,
  // so what the admin previews here matches what api/booking_email.php
  // actually attaches when it sends to the client. Once the booking is
  // fully paid, the same documents auto-relabel as an Invoice (the
  // backend re-verifies payment_status itself before honoring invoice=1,
  // so this flag is just a request, not the source of truth).
  var isPaid = b.payment_status === 'fully_paid';
  var quotePdfUrl = b.quotation_id
    ? BASE+'/api/quotation_pdf.php?id='+encodeURIComponent(b.quotation_id)+(isPaid?'&invoice=1':'')
    : BASE+'/api/booking_pdf.php?ref='+encodeURIComponent(b.booking_ref)+(isPaid?'&invoice=1':'');
  var itinPdfUrl = b.quotation_id
    ? BASE+'/api/quotation_itinerary_pdf.php?id='+encodeURIComponent(b.quotation_id)
    : BASE+'/api/booking_itinerary_pdf.php?ref='+encodeURIComponent(b.booking_ref);

  // Action buttons
  html += '<div style="padding:1.25rem 1.5rem;display:flex;gap:.5rem;flex-wrap:wrap">'

    // Quotation PDF (or Invoice PDF once fully paid) — pricing/guest
    // confirmation, full cost breakdown, Print/Save PDF built into the
    // page itself, same as the Voucher
    +'<a href="'+quotePdfUrl+'" '
    +'target="_blank" class="admin-btn admin-btn--outline" style="font-size:.72rem">'+(isPaid?'Invoice PDF':'Quotation PDF')+'</a>'

    // Itinerary — day-by-day companion PDF
    +'<a href="'+itinPdfUrl+'" '
    +'target="_blank" class="admin-btn admin-btn--outline" style="font-size:.72rem">Itinerary PDF</a>'

    + (CAN_EDIT_BOOKING
        ? '<button type="button" onclick="editBooking()" class="admin-btn admin-btn--primary" style="font-size:.72rem">Edit Booking</button>'
        : '')

    +'</div>';

  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('bookingModal').classList.add('open');
}

function editBooking() {
  var b = CURRENT_BOOKING;
  if (!b) return;
  document.getElementById('modalTitle').textContent = 'Edit Booking — ' + b.booking_ref;

  function opt(value, label, selected) {
    return '<option value="'+value+'"'+(selected?' selected':'')+'>'+label+'</option>';
  }
  var pkgOptions = '<option value="">— None —</option>' + EDIT_PACKAGES.map(function(p){
    return opt(p.id, p.name, String(b.package_id)===String(p.id));
  }).join('');
  var itinOptions = '<option value="">— None —</option>' + EDIT_ITINERARIES.map(function(i){
    return opt(i.id, i.name, String(b.itinerary_id)===String(i.id));
  }).join('');
  var accOptions = ACC_LEVELS.map(function(a){
    return opt(a[0], a[1], b.accommodation_level===a[0]);
  }).join('');

  function field(label, inputHtml) {
    return '<div style="margin-bottom:.85rem">'
      + '<label class="admin-label" style="display:block;margin-bottom:.3rem">'+label+'</label>'
      + inputHtml
      + '</div>';
  }
  function esc(v) {
    var d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML;
  }

  var html = '<form id="editBookingForm" style="padding:1.25rem 1.5rem">'
    + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1rem">'
    + field('Full Name', '<input class="admin-input" name="full_name" value="'+esc(b.full_name)+'" required>')
    + field('Email', '<input class="admin-input" type="email" name="email" value="'+esc(b.email)+'" required>')
    + field('Phone', '<input class="admin-input" name="phone" value="'+esc(b.phone)+'" required>')
    + field('Travel Date', '<input class="admin-input" type="date" name="travel_date" value="'+esc(b.travel_date)+'" required>')
    + field('Adults', '<input class="admin-input" type="number" min="1" name="adults" value="'+esc(b.adults||b.travelers||1)+'" required>')
    + field('Children', '<input class="admin-input" type="number" min="0" name="children" value="'+esc(b.children||0)+'">')
    + field('Accommodation Level', '<select class="admin-input" name="accommodation_level">'+accOptions+'</select>')
    + field('Package', '<select class="admin-input" name="package_id">'+pkgOptions+'</select>')
    + field('Itinerary', '<select class="admin-input" name="itinerary_id">'+itinOptions+'</select>')
    + field('Total Amount (USD)', '<input class="admin-input" type="number" min="0" step="0.01" name="total_amount" value="'+esc(b.total_amount)+'" required>')
    + field('Deposit Amount (USD)', '<input class="admin-input" type="number" min="0" step="0.01" name="deposit_amount" value="'+esc(b.deposit_amount)+'" required>')
    + '</div>'
    + field('Special Request', '<textarea class="admin-input" name="special_request" rows="3">'+esc(b.special_request)+'</textarea>')
    + '<div style="display:flex;gap:.5rem;padding-top:.5rem">'
    + '<button type="submit" class="admin-btn admin-btn--primary">Save Changes</button>'
    + '<button type="button" class="admin-btn admin-btn--outline" onclick="viewBooking(\''+b.booking_ref+'\')">Cancel</button>'
    + '</div>'
    + '</form>';

  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('editBookingForm').addEventListener('submit', saveBooking);
}

async function saveBooking(e) {
  e.preventDefault();
  var form = e.target;
  var fd = new FormData(form);
  fd.append('action','update_booking');
  fd.append('booking_ref', CURRENT_BOOKING.booking_ref);
  fd.append('_csrf', window.CSRF_TOKEN || '');
  var btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  try {
    var r = await fetch('bookings.php',{method:'POST',body:fd});
    var d = await r.json();
    if (d.success) {
      toast('Booking updated','ok');
      CURRENT_BOOKING = d.booking;
      viewBooking(d.booking.booking_ref);
      setTimeout(function(){ location.reload(); }, 600);
    } else {
      toast('Failed: '+(d.message||'Error'),'err');
      btn.disabled = false;
    }
  } catch(err) { toast('Network error','err'); btn.disabled = false; }
}

function closeModal() {
  document.getElementById('bookingModal').classList.remove('open');
}
document.getElementById('bookingModal').addEventListener('click', function(e){
  if (e.target === this) closeModal();
});
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') closeModal();
});

function toast(msg, type) {
  var t = document.createElement('div');
  t.className = 'toast' + (type==='ok' ? ' toast--ok' : type==='err' ? ' toast--err' : '');
  t.textContent = msg;
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(function(){ t.remove(); }, 3500);
}

// api/voucher_email.php and api/booking_email.php redirect back here with
// ?msg=... (their send result) — surface it instead of silently dropping
// it, then strip it from the URL so a refresh doesn't re-show it.
(function () {
  var params = new URLSearchParams(window.location.search);
  var msg = params.get('msg');
  if (!msg) return;
  toast(msg, msg.charAt(0) === '✓' ? 'ok' : msg.charAt(0) === '✗' ? 'err' : null);
  params.delete('msg');
  var qs = params.toString();
  history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
})();

<?php if ($viewRef): ?>
window.addEventListener('load', function() {
  viewBooking('<?= htmlspecialchars($viewRef) ?>');
  var row = document.getElementById('row-<?= htmlspecialchars($viewRef) ?>');
  if (row) row.scrollIntoView({behavior:'smooth',block:'center'});
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
?>
