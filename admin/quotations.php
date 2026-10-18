<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Mail\Mailer;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$myId        = (int)($_SESSION['admin_user_id'] ?? 0);
// Ownership scoping (own quotations only) applies to the agent role
// alone — staff sees/manages every quotation, same as admin.
$isAgentUser = AuthMiddleware::isAgentRole();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        $newStatus = $_POST['status'];
        $qId       = (int)$_POST['id'];
        if (!in_array($newStatus, ['draft','sent','viewed','negotiating','accepted','declined','expired','converted'], true)) {
            throw new \RuntimeException('Invalid status.');
        }

        if ($isAgentUser) {
            $upd = $db->prepare("UPDATE quotations SET status=?, updated_at=NOW() WHERE id=? AND created_by=?");
            $upd->execute([$newStatus, $qId, $myId]);
        } else {
            $upd = $db->prepare("UPDATE quotations SET status=?, updated_at=NOW() WHERE id=?");
            $upd->execute([$newStatus, $qId]);
        }
        if ($upd->rowCount() > 0) {
            \App\Services\ActivityLog::record('update_status', 'quotation', $qId, ['status'=>$newStatus]);
        }

        // ── Email: quotation_accepted ─────────────────────────────
        if ($newStatus === 'accepted') {
            $db->prepare("UPDATE quotations SET accepted_at=NOW() WHERE id=? AND accepted_at IS NULL")
               ->execute([$qId]);
            try {
                $q = $db->prepare("SELECT * FROM quotations WHERE id=?");
                $q->execute([$qId]);
                $quot = $q->fetch();
                if ($quot && $quot['client_email']) {
                    // They just agreed to specific pricing — send a copy of
                    // exactly what they accepted, not just a notice that it
                    // happened.
                    $attachments = [];
                    $pdf = \App\Services\PdfRenderer::renderQuotation($qId, 'Quotation-' . $quot['quote_ref']);
                    if ($pdf) $attachments[] = $pdf;
                    $itin = \App\Services\PdfRenderer::renderQuotationItinerary($qId, 'Itinerary-' . $quot['quote_ref']);
                    if ($itin) $attachments[] = $itin;

                    Mailer::queue('quotation_accepted', [
                        'client_name'    => $quot['client_name'],
                        'quote_ref'      => $quot['quote_ref'],
                        'deposit_amount' => (float)($quot['deposit_amount'] ?? round($quot['grand_total'] * 0.25, 2)),
                        'to_email'       => $quot['client_email'],
                        'to_name'        => $quot['client_name'],
                    ], null, null, $attachments ?: null);
                }
            } catch(\Exception $e) { error_log('[Quotation Accepted Email] '.$e->getMessage()); }
        }

        // ── Email: quotation_expired ──────────────────────────────
        if ($newStatus === 'expired') {
            try {
                $q = $db->prepare("SELECT * FROM quotations WHERE id=?");
                $q->execute([$qId]);
                $quot = $q->fetch();
                if ($quot && $quot['client_email']) {
                    Mailer::queue('quotation_expired', [
                        'client_name' => $quot['client_name'],
                        'quote_ref'   => $quot['quote_ref'],
                        'to_email'    => $quot['client_email'],
                        'to_name'     => $quot['client_name'],
                    ]);
                }
            } catch(\Exception $e) { error_log('[Quotation Expired Email] '.$e->getMessage()); }
        }
    } catch(Exception $e) { error_log($e->getMessage()); }
    header('Location: quotations.php'); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        $delId = (int)$_POST['delete_id'];
        if ($isAgentUser) {
            $del = $db->prepare("DELETE FROM quotations WHERE id=? AND created_by=?");
            $del->execute([$delId, $myId]);
        } else {
            $del = $db->prepare("DELETE FROM quotations WHERE id=?");
            $del->execute([$delId]);
        }
        if ($del->rowCount() > 0) {
            \App\Services\ActivityLog::record('delete', 'quotation', $delId);
        }
    }
    catch(Exception $e) { error_log($e->getMessage()); }
    header('Location: quotations.php'); exit;
}

// Filters + pagination
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where  = 'WHERE 1=1';
$params = [];
if ($isAgentUser) { $where .= ' AND created_by=?'; $params[] = $myId; }
if ($statusFilter) { $where .= ' AND status=?'; $params[] = $statusFilter; }
if ($search)       { $where .= ' AND (client_name LIKE ? OR client_email LIKE ? OR quote_ref LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }

$quotes = [];
$stats  = [];
$totalRows = 0; $totalPages = 0;
try {
    $total = $db->prepare("SELECT COUNT(*) FROM quotations $where");
    $total->execute($params);
    $totalRows  = (int)$total->fetchColumn();
    $totalPages = (int)ceil($totalRows / $perPage);

    $s = $db->prepare("SELECT * FROM quotations $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $s->execute($params);
    $quotes = $s->fetchAll();

    $statWhere  = $isAgentUser ? 'WHERE created_by=?' : '';
    $statParams = $isAgentUser ? [$myId] : [];
    $statStmt = $db->prepare("SELECT status, COUNT(*) as cnt, SUM(grand_total) as rev FROM quotations $statWhere GROUP BY status");
    $statStmt->execute($statParams);
    foreach ($statStmt->fetchAll() as $r) $stats[$r['status']] = $r;
} catch(Exception $e) { error_log($e->getMessage()); }

// Funnel rates over quotations that were actually sent. Open rate = viewed
// at least once; acceptance = accepted or converted. Scoped the same way
// as the list for agents.
$funnel = ['sent' => 0, 'viewed' => 0, 'won' => 0];
try {
    $fw = $isAgentUser ? ' AND created_by=?' : '';
    $fp = $isAgentUser ? [$myId] : [];
    $fs = $db->prepare("SELECT COUNT(*) sent, SUM(first_viewed_at IS NOT NULL) viewed,
                               SUM(status IN ('accepted','converted')) won
                        FROM quotations WHERE sent_at IS NOT NULL$fw");
    $fs->execute($fp);
    $row = $fs->fetch();
    $funnel = ['sent' => (int)$row['sent'], 'viewed' => (int)$row['viewed'], 'won' => (int)$row['won']];
} catch(Exception $e) { error_log($e->getMessage()); }
$openRate = $funnel['sent'] > 0 ? round($funnel['viewed'] / $funnel['sent'] * 100) : null;
$acceptRate = $funnel['sent'] > 0 ? round($funnel['won'] / $funnel['sent'] * 100) : null;

$statusColors = [
    'draft'       => ['badge'=>'gray',  'label'=>'Draft'],
    'sent'        => ['badge'=>'blue',  'label'=>'Sent'],
    'viewed'      => ['badge'=>'blue',  'label'=>'Viewed'],
    'negotiating' => ['badge'=>'amber', 'label'=>'Negotiating'],
    'accepted'    => ['badge'=>'green', 'label'=>'Accepted'],
    'declined'    => ['badge'=>'red',   'label'=>'Declined / Rejected'],
    'expired'     => ['badge'=>'gray',  'label'=>'Expired'],
    // Set automatically by api/convert_quotation.php, not manually from
    // the status dropdown below — excluded from $manualStatuses, but
    // still needs a color/label here for correct badge display wherever
    // a converted quotation shows up (list badge, stats row).
    'converted'   => ['badge'=>'dark',  'label'=>'Converted'],
];
// Statuses a human can actually choose from the status dropdown —
// everything except 'converted', which only api/convert_quotation.php
// should ever set (manually picking it wouldn't create the booking/
// booking_ref that status implies exists).
$manualStatuses = array_diff_key($statusColors, ['converted' => true]);

$pageTitle = 'Quotations';
ob_start();
?>

<!-- Stats row -->
<div class="admin-metrics-grid">
  <?php foreach ($statusColors as $key => $s): ?>
  <a href="?status=<?= $key ?>" style="text-decoration:none">
    <div class="metric-card<?= $statusFilter===$key ? ' metric-card--'.$s['badge'] : '' ?>">
      <span class="metric-label"><?= $s['label'] ?></span>
      <span class="metric-value"><?= $stats[$key]['cnt'] ?? 0 ?></span>
      <?php if (!empty($stats[$key]['rev'])): ?>
      <span class="metric-sub">$<?= number_format($stats[$key]['rev'],0) ?></span>
      <?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
  <div class="metric-card">
    <span class="metric-label">Open Rate</span>
    <span class="metric-value"><?= $openRate === null ? '—' : $openRate.'%' ?></span>
    <span class="metric-sub"><?= $funnel['viewed'] ?> of <?= $funnel['sent'] ?> sent</span>
  </div>
  <div class="metric-card">
    <span class="metric-label">Acceptance Rate</span>
    <span class="metric-value"><?= $acceptRate === null ? '—' : $acceptRate.'%' ?></span>
    <span class="metric-sub"><?= $funnel['won'] ?> of <?= $funnel['sent'] ?> sent</span>
  </div>
</div>

<!-- Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem">
  <form style="display:flex;gap:.5rem;flex-wrap:wrap">
    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search name, email, ref…"
           class="admin-input" style="min-width:220px">
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
  <td><code><?= htmlspecialchars($q['quote_ref']) ?></code></td>
  <td>
    <strong style="font-size:.85rem"><?= htmlspecialchars($q['client_name']) ?></strong><br>
    <a href="mailto:<?= htmlspecialchars($q['client_email']) ?>" style="font-size:.72rem;color:var(--gray-500)"><?= htmlspecialchars($q['client_email']) ?></a>
    <?php if ($q['client_phone']): ?>
      <br><span style="font-size:.72rem;color:var(--gray-500)"><?= htmlspecialchars($q['client_phone']) ?></span>
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
    <span style="font-size:.68rem;color:var(--gray-500)"><br><?= $q['adults'] ?>A <?= $q['children']>0 ? '+'.$q['children'].'C' : '' ?></span>
  </td>
  <td>
    <strong style="font-size:.9rem">$<?= number_format($q['grand_total'],0) ?></strong>
    <br><span style="font-size:.68rem;color:var(--gray-500)">$<?= number_format($q['per_person'],0) ?>/pp</span>
  </td>
  <td>
    <span class="badge-status badge-status--<?= $sc['badge'] ?>"><?= $sc['label'] ?></span>
    <?php if ($expired): ?>
      <br><span style="font-size:.62rem;color:var(--red)">Expired</span>
    <?php endif; ?>
  </td>
  <td style="font-size:.78rem;<?= $expired ? 'color:var(--red)' : 'color:var(--gray-500)' ?>">
    <?= $q['valid_until'] ? date('d M Y', strtotime($q['valid_until'])) : '—' ?>
  </td>
  <td style="font-size:.72rem;color:var(--gray-500)"><?= date('d M Y', strtotime($q['created_at'])) ?></td>
  <td>
    <div style="display:flex;gap:.3rem;flex-wrap:wrap">
      <!-- Edit -->
      <a href="quotation_new.php?id=<?= $q['id'] ?>" class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">Edit</a>
      <!-- PDF -->
      <a href="../api/quotation_pdf.php?id=<?= $q['id'] ?>" target="_blank" class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">PDF</a>
      <!-- Itinerary — day-by-day companion PDF -->
      <a href="../api/quotation_itinerary_pdf.php?id=<?= $q['id'] ?>" target="_blank" class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">Itinerary</a>
      <!-- Email -->
      <form method="POST" action="../api/quotation_email.php" style="display:inline" target="_blank">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="id" value="<?= $q['id'] ?>">
        <button class="admin-btn admin-btn--primary" style="font-size:.65rem;padding:.3rem .6rem">Email</button>
      </form>
      <!-- Status -->
      <form method="POST" style="display:inline">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_status" value="1">
        <input type="hidden" name="id" value="<?= $q['id'] ?>">
        <select name="status" data-prev="<?= htmlspecialchars($q['status']) ?>"
                onchange="confirmQuoteStatusChange(this, '<?= htmlspecialchars(addslashes($q['quote_ref'])) ?>')"
                class="admin-input"
                style="font-size:.65rem;padding:.3rem .5rem;max-width:100px">
          <?php foreach ($manualStatuses as $sv => $sl): ?>
          <option value="<?= $sv ?>" <?= $q['status']===$sv?'selected':'' ?>><?= $sl['label'] ?></option>
          <?php endforeach; ?>
          <?php if ($q['status']==='converted'): ?>
          <option value="converted" selected disabled>Converted</option>
          <?php endif; ?>
        </select>
      </form>
      <!-- Convert to Booking -->
      <?php if ($q['status'] === 'accepted'): ?>
      <button onclick="openConvertModal(<?= $q['id'] ?>, '<?= htmlspecialchars($q['quote_ref']) ?>', '<?= htmlspecialchars($q['client_name']) ?>', <?= (float)$q['grand_total'] ?>, '<?= htmlspecialchars($q['package_name']??'') ?>', '<?= htmlspecialchars($q['start_date']??'') ?>', <?= (float)($q['deposit_amount']??0) ?>)"
              class="admin-btn admin-btn--success" style="font-size:.65rem;padding:.3rem .6rem">
        Convert to Booking
      </button>
      <?php endif; ?>
      <?php if ($q['status'] === 'converted' && !empty($q['booking_ref'])): ?>
      <a href="bookings.php?view=<?= urlencode($q['booking_ref']) ?>" class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">
        View Booking ↗
      </a>
      <?php endif; ?>
      <!-- Delete -->
      <form method="POST" style="display:inline" onsubmit="return confirmSubmitGuard(event, this, 'Delete quotation <?= htmlspecialchars(addslashes($q['quote_ref'])) ?>?', {danger:true})">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
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

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination" style="border-top:none">
  <span style="font-size:.75rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?></span>
  <div class="page-btns">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&q=<?= urlencode($search) ?>"
       class="page-btn<?= $i===$page?' active':'' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>


<!-- ── CONVERT TO BOOKING MODAL ─────────────────────────── -->
<div id="convertModal" class="admin-modal-overlay">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <span class="admin-modal-title">Convert Quotation to Booking</span>
      <button onclick="closeConvertModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <div id="convert-summary" style="background:var(--gray-50);padding:1rem;margin-bottom:1.25rem;font-size:.85rem;line-height:1.8"></div>
      <form id="convert-form">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" id="convert-quote-id" name="quotation_id">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div>
            <label class="admin-label">Travel Date *</label>
            <input type="date" id="convert-travel-date" name="travel_date" class="admin-input" required
                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
          </div>
          <div>
            <label class="admin-label">Payment Method</label>
            <select name="payment_method" class="admin-input">
              <option value="bank_transfer">Bank Transfer</option>
              <option value="paypal">PayPal</option>
              <option value="wise">Wise</option>
              <option value="cash">Cash</option>
            </select>
          </div>
        </div>
        <div style="margin-bottom:1.25rem">
          <label class="admin-label">Special Requests / Notes</label>
          <textarea name="special_requests" class="admin-input" rows="2" placeholder="Any notes from the client…"></textarea>
        </div>
        <div id="convert-error" class="admin-alert admin-alert--error" style="display:none"></div>
        <div style="display:flex;gap:.75rem;justify-content:flex-end">
          <button type="button" onclick="closeConvertModal()" class="admin-btn admin-btn--outline">Cancel</button>
          <button type="submit" class="admin-btn admin-btn--primary" id="convert-submit">
            Create Booking
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="toastContainer2" class="toast-container"></div>

<script>
async function confirmQuoteStatusChange(sel, ref) {
  var label = sel.options[sel.selectedIndex].text;
  if (!(await confirmStatusChange(sel, 'Change quotation '+ref+' status to "'+label+'"?'))) return;
  sel.form.submit();
}

function openConvertModal(id, ref, name, total, pkg, startDate, deposit) {
  document.getElementById('convert-quote-id').value = id;
  document.getElementById('convert-travel-date').value = startDate || '';
  document.getElementById('convert-summary').innerHTML =
    '<strong>Quotation:</strong> '+ref+'<br>'
    +'<strong>Client:</strong> '+name+'<br>'
    +'<strong>Package:</strong> '+(pkg||'—')+'<br>'
    +'<strong>Grand Total:</strong> USD $'+Math.round(total).toLocaleString('en-US')+'<br>'
    // The actual deposit this quotation was given to the client (not a
    // recomputed percentage) — this is the exact figure
    // api/convert_quotation.php carries into the new booking.
    +'<strong>Deposit:</strong> USD $'+Math.round(deposit||0).toLocaleString('en-US');
  document.getElementById('convert-error').style.display='none';
  document.getElementById('convert-modal-ref') && (document.getElementById('convert-modal-ref').textContent=ref);
  document.getElementById('convertModal').classList.add('open');
}
function closeConvertModal() { document.getElementById('convertModal').classList.remove('open'); }
document.getElementById('convertModal').addEventListener('click',function(e){if(e.target===this)closeConvertModal();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeConvertModal();});

document.getElementById('convert-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  var btn = document.getElementById('convert-submit');
  btn.disabled=true; btn.textContent='Creating…';
  document.getElementById('convert-error').style.display='none';

  var fd = new FormData(this);
  try {
    var r = await fetch('<?= BASE_PATH ?>/api/convert_quotation.php', {method:'POST',body:fd,credentials:'same-origin'});
    var raw = await r.text();
    var d;
    try { d = JSON.parse(raw); } catch(ex) {
      d = {success:false, message:'Server error: '+raw.substring(0,120)};
    }
    if (d.success) {
      closeConvertModal();
      // Show success toast
      var t = document.createElement('div');
      t.className = 'toast toast--ok';
      t.innerHTML='Booking <strong>'+d.booking_ref+'</strong> created! <a href="'+d.admin_url+'" style="color:#fff;margin-left:.5rem;text-decoration:underline">View →</a>';
      document.getElementById('toastContainer2').appendChild(t);
      setTimeout(function(){ t.remove(); window.location.reload(); }, 4000);
    } else {
      document.getElementById('convert-error').textContent = d.message || 'Conversion failed.';
      document.getElementById('convert-error').style.display='block';
    }
  } catch(ex) {
    document.getElementById('convert-error').textContent = 'Network error: '+ex.message;
    document.getElementById('convert-error').style.display='block';
  }
  btn.disabled=false; btn.textContent='Create Booking';
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
