<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

// ── AJAX handlers ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $ref     = trim($_POST['booking_ref'] ?? '');
        $status  = trim($_POST['status'] ?? '');
        $allowed = ['pending','deposit_paid','fully_paid','cancelled'];
        if ($ref && in_array($status, $allowed, true)) {
            $db->prepare("UPDATE bookings SET payment_status=?, updated_at=NOW() WHERE booking_ref=?")
               ->execute([$status, $ref]);
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Invalid data.']);
        }
        exit;
    }
    if ($action === 'get_booking') {
        $ref = trim($_POST['booking_ref'] ?? '');
        $s = $db->prepare("SELECT * FROM bookings WHERE booking_ref=?");
        $s->execute([$ref]);
        $b = $s->fetch();
        // Also check if a quotation exists for this booking
        $hasQuote = false;
        if ($b) {
            try {
                $qs = $db->prepare("SELECT id FROM quotations WHERE booking_ref=? OR id=? LIMIT 1");
                $qs->execute([$ref, $b['quotation_id'] ?? 0]);
                $hasQuote = (bool)$qs->fetch();
            } catch(\Exception $e) {}
        }
        if ($b) $b['has_quote'] = $hasQuote;
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
    $where[]  = "(booking_ref LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params,[$like,$like,$like,$like]);
}
if ($status) { $where[] = "payment_status=?"; $params[] = $status; }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

try {
    $total = $db->prepare("SELECT COUNT(*) FROM bookings $whereSQL");
    $total->execute($params);
    $totalRows  = (int)$total->fetchColumn();
    $totalPages = (int)ceil($totalRows/$perPage);
    $stmt = $db->prepare("SELECT * FROM bookings $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch(\Exception $e) {
    error_log($e->getMessage());
    $bookings = []; $totalRows = 0; $totalPages = 0;
}

$viewRef = trim($_GET['view'] ?? '');

$statusCounts = [];
try {
    $rows = $db->query("SELECT payment_status, COUNT(*) as c FROM bookings GROUP BY payment_status")->fetchAll();
    foreach ($rows as $r) $statusCounts[$r['payment_status']] = $r['c'];
} catch(\Exception $e) {}

$statusColors = [
    'pending'      => ['bg'=>'#fef3e2','color'=>'#92400e','label'=>'Pending'],
    'deposit_paid' => ['bg'=>'#eff6ff','color'=>'#1d4ed8','label'=>'Deposit Paid'],
    'fully_paid'   => ['bg'=>'#f0fdf4','color'=>'#166534','label'=>'Fully Paid'],
    'cancelled'    => ['bg'=>'#fef2f2','color'=>'#991b1b','label'=>'Cancelled'],
];

$pageTitle = 'Bookings';
ob_start();
?>

<!-- Status filter pills -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem">
  <a href="bookings.php" class="admin-filter-pill<?= !$status?' active':'' ?>">
    All &nbsp;<span style="opacity:.6"><?= number_format($totalRows) ?></span>
  </a>
  <?php foreach ($statusColors as $sv => $sc): ?>
  <a href="?status=<?= $sv ?><?= $search?'&search='.urlencode($search):'' ?>"
     style="padding:.35rem .9rem;font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border:1.5px solid <?= $status===$sv?'#0a0a0a':'#e5e7eb' ?>;background:<?= $status===$sv?$sc['bg']:'#fff' ?>;color:<?= $status===$sv?$sc['color']:'#0a0a0a' ?>;text-decoration:none">
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
      <th>Total / Deposit</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($bookings as $b):
      $sc = $statusColors[$b['payment_status']] ?? $statusColors['pending'];
      // Check if quotation exists
      $hasQuote = !empty($b['quotation_id']);
    ?>
    <tr id="row-<?= htmlspecialchars($b['booking_ref']) ?>"
        <?= ($viewRef && $viewRef===$b['booking_ref']) ? 'style="background:#f0f0ee"' : '' ?>>
      <td>
        <code style="font-size:.78rem;background:#f3f4f6;padding:.12rem .4rem;font-family:monospace">
          <?= htmlspecialchars($b['booking_ref']) ?>
        </code>
      </td>
      <td>
        <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($b['full_name']) ?></div>
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
        <div style="font-weight:700"><?= $b['total_amount'] ? '$'.number_format($b['total_amount'],0) : '—' ?></div>
        <?php if ($b['deposit_amount']): ?>
        <div style="font-size:.68rem;color:#9ca3af">dep: $<?= number_format($b['deposit_amount'],0) ?></div>
        <?php endif; ?>
      </td>
      <td>
        <select onchange="updateStatus('<?= htmlspecialchars($b['booking_ref']) ?>', this.value, this)"
                style="border:1.5px solid #e5e7eb;padding:.3rem .5rem;font-size:.72rem;cursor:pointer;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-weight:700;max-width:130px">
          <?php foreach ($statusColors as $sv => $sl): ?>
          <option value="<?= $sv ?>" <?= $b['payment_status']===$sv?'selected':'' ?>><?= $sl['label'] ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td style="white-space:nowrap">
        <!-- View detail modal -->
        <button onclick="viewBooking('<?= htmlspecialchars($b['booking_ref']) ?>')"
                class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">
          View
        </button>
        <!-- PDF — booking confirmation print page -->
        <a href="../api/booking_pdf.php?ref=<?= urlencode($b['booking_ref']) ?>"
           target="_blank"
           class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">
          PDF
        </a>
        <!-- Quotation status: + Quote creates new, ✓ Quote opens existing -->
        <?php if ($hasQuote): ?>
        <a href="quotations.php?search=<?= urlencode($b['booking_ref']) ?>"
           class="admin-btn" style="font-size:.6rem;padding:.3rem .6rem;background:#f0fdf4;border-color:#86efac;color:#166534">
          ✓ Quote
        </a>
        <?php else: ?>
        <a href="quotation_new.php?from_booking=<?= urlencode($b['booking_ref']) ?>"
           class="admin-btn" style="font-size:.6rem;padding:.3rem .6rem;background:#eff6ff;border-color:#93c5fd;color:#1d4ed8">
          + Quote
        </a>
        <?php endif; ?>
        <!-- Email booking confirmation -->
        <a href="../api/booking_email.php?ref=<?= urlencode($b['booking_ref']) ?>"
           onclick="return confirm('Send booking confirmation email to <?= htmlspecialchars(addslashes($b['email'])) ?>?')"
           class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem;background:#f0f9ff;border-color:#7dd3fc;color:#0369a1">
          ✉ Email
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
    <tr><td colspan="8" class="admin-empty">No bookings found.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 0;flex-wrap:wrap;gap:.5rem">
  <span style="font-size:.75rem;color:#9ca3af">Page <?= $page ?> of <?= $totalPages ?></span>
  <div style="display:flex;gap:.25rem;flex-wrap:wrap">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
       style="display:inline-block;padding:.3rem .6rem;font-size:.75rem;border:1.5px solid <?= $i===$page?'#0a0a0a':'#e5e7eb' ?>;background:<?= $i===$page?'#0a0a0a':'#fff' ?>;color:<?= $i===$page?'#fff':'#0a0a0a' ?>;text-decoration:none">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Booking Detail Modal ─────────────────────────────── -->
<div id="bookingModal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:999;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;width:100%;max-width:600px;max-height:92vh;overflow-y:auto;border-radius:2px;box-shadow:0 24px 80px rgba(0,0,0,.25)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.5rem;border-bottom:1px solid #e5e7eb;position:sticky;top:0;background:#fff;z-index:1">
      <span style="font-size:.95rem;font-weight:600" id="modalTitle">Booking Detail</span>
      <button onclick="closeModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#9ca3af;line-height:1">✕</button>
    </div>
    <div id="modalBody" style="padding:0 0 1rem"></div>
  </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem"></div>

<script>
var BASE = '<?= BASE_PATH ?>';

async function updateStatus(ref, newStatus, sel) {
  var fd = new FormData();
  fd.append('action','update_status');
  fd.append('booking_ref',ref);
  fd.append('status',newStatus);
  try {
    var r = await fetch('bookings.php',{method:'POST',body:fd});
    var d = await r.json();
    if (d.success) {
      toast('Status updated: '+newStatus,'ok');
      var colors    = {pending:'#fef3e2',deposit_paid:'#eff6ff',fully_paid:'#f0fdf4',cancelled:'#fef2f2'};
      var textColors= {pending:'#92400e',deposit_paid:'#1d4ed8',fully_paid:'#166534',cancelled:'#991b1b'};
      sel.style.background = colors[newStatus] || '#fff';
      sel.style.color      = textColors[newStatus] || '#0a0a0a';
    } else {
      toast('Failed: '+(d.message||'Error'),'err');
    }
  } catch(e) { toast('Network error','err'); }
}

async function viewBooking(ref) {
  var fd = new FormData();
  fd.append('action','get_booking');
  fd.append('booking_ref',ref);
  var r = await fetch('bookings.php',{method:'POST',body:fd});
  var b = await r.json();
  if (b.error) { toast('Not found','err'); return; }

  document.getElementById('modalTitle').textContent = 'Booking — ' + b.booking_ref;

  var statusLabel = {pending:'Pending',deposit_paid:'Deposit Paid',fully_paid:'Fully Paid',cancelled:'Cancelled'};
  var statusColor = {pending:'#92400e',deposit_paid:'#1d4ed8',fully_paid:'#166534',cancelled:'#991b1b'};
  var statusBg    = {pending:'#fef3e2',deposit_paid:'#eff6ff',fully_paid:'#f0fdf4',cancelled:'#fef2f2'};

  var travelDate = b.travel_date ? new Date(b.travel_date).toLocaleDateString('en-GB',{day:'2-digit',month:'long',year:'numeric'}) : '—';
  var bookedDate = b.created_at  ? new Date(b.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'long',year:'numeric'}) : '—';

  var rows = [
    ['Reference',      '<code style="font-family:monospace;font-size:.9rem;background:#f3f4f6;padding:.1rem .35rem">'+b.booking_ref+'</code>'],
    ['Guest',          '<strong>'+b.full_name+'</strong>'],
    ['Email',          '<a href="mailto:'+b.email+'" style="color:inherit">'+b.email+'</a>'],
    ['Phone',          '<a href="https://wa.me/'+(b.phone||'').replace(/[^0-9]/g,'')+'?text='+encodeURIComponent('Hi '+b.full_name+', Safari Zangu here regarding booking '+b.booking_ref+'.')+'" target="_blank" style="color:inherit">'+b.phone+'</a>'],
    ['Package',        b.package_name||'—'],
    ['Accommodation',  b.accommodation_level ? (b.accommodation_level.charAt(0).toUpperCase()+b.accommodation_level.slice(1).replace(/_/g,' ')) : '—'],
    ['Guests',         (b.adults||b.travelers)+' adult'+(parseInt(b.adults||b.travelers)>1?'s':'')+(b.children>0?' + '+b.children+' child':'')],
    ['Travel Date',    '<strong>'+travelDate+'</strong>'],
    ['Total Amount',   b.total_amount   ? '<strong>USD $'+Number(b.total_amount).toLocaleString('en-US',{minimumFractionDigits:2})+'</strong>' : '—'],
    ['Deposit (25%)',  b.deposit_amount ? 'USD $'+Number(b.deposit_amount).toLocaleString('en-US',{minimumFractionDigits:2}) : '—'],
    ['Balance Due',    (b.total_amount && b.deposit_amount) ? 'USD $'+(b.total_amount - b.deposit_amount).toLocaleString('en-US',{minimumFractionDigits:2}) : '—'],
    ['Payment Status', '<span style="display:inline-block;padding:.2rem .65rem;font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:'+(statusBg[b.payment_status]||'#f3f4f6')+';color:'+(statusColor[b.payment_status]||'#374151')+'">'+(statusLabel[b.payment_status]||b.payment_status)+'</span>'],
    ['Booked On',      bookedDate],
  ];
  if (b.special_requests) rows.push(['Special Requests', b.special_requests]);
  if (b.admin_notes)      rows.push(['Admin Notes', b.admin_notes]);

  var html = rows.map(function(r){
    return '<div style="display:grid;grid-template-columns:130px 1fr;gap:.5rem;padding:.6rem 1.5rem;border-bottom:1px solid #f3f4f6;font-size:.85rem">'
      +'<span style="font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af;padding-top:.15rem">'+r[0]+'</span>'
      +'<span>'+r[1]+'</span></div>';
  }).join('');

  // Action buttons
  html += '<div style="padding:1.25rem 1.5rem;display:flex;gap:.5rem;flex-wrap:wrap">'

    // PDF — booking confirmation
    +'<a href="'+BASE+'/api/booking_pdf.php?ref='+encodeURIComponent(b.booking_ref)+'" '
    +'target="_blank" class="admin-btn admin-btn--outline" style="font-size:.72rem">'
    +'📄 Booking PDF</a>'

    // Create / View Quotation
    +(b.has_quote
      ? '<a href="'+BASE+'/admin/quotations.php?search='+encodeURIComponent(b.booking_ref)+'" '
        +'class="admin-btn" style="font-size:.72rem;background:#f0fdf4;border-color:#86efac;color:#166534">✓ View Quotation</a>'
      : '<a href="'+BASE+'/admin/quotation_new.php?from_booking='+encodeURIComponent(b.booking_ref)+'" '
        +'class="admin-btn" style="font-size:.72rem;background:#eff6ff;border-color:#93c5fd;color:#1d4ed8">+ Create Quotation</a>'
    )

    // Resend confirmation email
    +'<a href="'+BASE+'/api/booking_email.php?ref='+encodeURIComponent(b.booking_ref)+'" '
    +'onclick="return confirm(\'Resend booking confirmation to '+b.email+'?\')" '
    +'class="admin-btn admin-btn--outline" style="font-size:.72rem">📧 Resend Email</a>'

    // Track page
    +'<a href="'+BASE+'/track.php?ref='+encodeURIComponent(b.booking_ref)+'&date='+(b.travel_date||'')+'" '
    +'target="_blank" class="admin-btn admin-btn--outline" style="font-size:.72rem">🔍 Track Page</a>'

    // WhatsApp
    +'<a href="https://wa.me/'+(b.phone||'').replace(/[^0-9]/g,'')+'?text='+encodeURIComponent('Hi '+b.full_name+', this is Safari Zangu Ventures regarding your booking '+b.booking_ref+'.')+'" '
    +'target="_blank" class="admin-btn admin-btn--success" style="font-size:.72rem">💬 WhatsApp</a>'

    +'</div>';

  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('bookingModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('bookingModal').style.display = 'none';
}
document.getElementById('bookingModal').addEventListener('click', function(e){
  if (e.target === this) closeModal();
});
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') closeModal();
});

function toast(msg, type) {
  var t = document.createElement('div');
  var bg = type==='ok' ? '#166534' : type==='err' ? '#991b1b' : '#0a0a0a';
  t.style.cssText = 'background:'+bg+';color:#fff;padding:.65rem 1.1rem;font-size:.8rem;min-width:180px;box-shadow:0 4px 16px rgba(0,0,0,.2);border-radius:2px';
  t.textContent = msg;
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(function(){ t.remove(); }, 3500);
}

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
