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
      <button onclick="openConvertModal(<?= $q['id'] ?>, '<?= htmlspecialchars($q['quote_ref']) ?>', '<?= htmlspecialchars($q['client_name']) ?>', <?= (float)$q['grand_total'] ?>, '<?= htmlspecialchars($q['package_name']??'') ?>', '<?= htmlspecialchars($q['start_date']??'') ?>')"
              class="admin-btn admin-btn--success" style="font-size:.65rem;padding:.3rem .6rem">
        → Convert to Booking
      </button>
      <?php endif; ?>
      <?php if ($q['status'] === 'converted' && !empty($q['booking_ref'])): ?>
      <a href="bookings.php?view=<?= urlencode($q['booking_ref']) ?>" class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">
        View Booking ↗
      </a>
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


<!-- ── CONVERT TO BOOKING MODAL ─────────────────────────── -->
<div id="convertModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:999;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;width:100%;max-width:520px;border-radius:2px;overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.5rem;border-bottom:1px solid #e5e7eb;background:#0a0a0a;color:#fff">
      <span style="font-size:.9rem;font-weight:600">Convert Quotation to Booking</span>
      <button onclick="closeConvertModal()" style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer">✕</button>
    </div>
    <div style="padding:1.5rem">
      <div id="convert-summary" style="background:#f7f7f5;padding:1rem;margin-bottom:1.25rem;font-size:.85rem;line-height:1.8"></div>
      <form id="convert-form">
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
        <div id="convert-error" style="display:none;background:#fdf2f2;border-left:3px solid #dc2626;color:#7f1d1d;padding:.75rem 1rem;font-size:.82rem;margin-bottom:1rem"></div>
        <div style="display:flex;gap:.75rem;justify-content:flex-end">
          <button type="button" onclick="closeConvertModal()" class="admin-btn admin-btn--outline">Cancel</button>
          <button type="submit" class="admin-btn admin-btn--primary" id="convert-submit">
            ✓ Create Booking
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="toastContainer2" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem"></div>

<script>
function openConvertModal(id, ref, name, total, pkg, startDate) {
  document.getElementById('convert-quote-id').value = id;
  document.getElementById('convert-travel-date').value = startDate || '';
  document.getElementById('convert-summary').innerHTML =
    '<strong>Quotation:</strong> '+ref+'<br>'
    +'<strong>Client:</strong> '+name+'<br>'
    +'<strong>Package:</strong> '+(pkg||'—')+'<br>'
    +'<strong>Grand Total:</strong> USD $'+Math.round(total).toLocaleString('en-US')+'<br>'
    +'<strong>Deposit (50%):</strong> USD $'+Math.round(total*0.5).toLocaleString('en-US');
  document.getElementById('convert-error').style.display='none';
  document.getElementById('convert-modal-ref') && (document.getElementById('convert-modal-ref').textContent=ref);
  document.getElementById('convertModal').style.display='flex';
}
function closeConvertModal() { document.getElementById('convertModal').style.display='none'; }
document.getElementById('convertModal').addEventListener('click',function(e){if(e.target===this)closeConvertModal();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeConvertModal();});

document.getElementById('convert-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  var btn = document.getElementById('convert-submit');
  btn.disabled=true; btn.textContent='Creating…';
  document.getElementById('convert-error').style.display='none';

  var fd = new FormData(this);
  try {
    var r = await fetch('<?= BASE_PATH ?>/php/convert_quotation.php', {method:'POST',body:fd,credentials:'same-origin'});
    var raw = await r.text();
    var d;
    try { d = JSON.parse(raw); } catch(ex) {
      d = {success:false, message:'Server error: '+raw.substring(0,120)};
    }
    if (d.success) {
      closeConvertModal();
      // Show success toast
      var t = document.createElement('div');
      t.style.cssText='background:#16a34a;color:#fff;padding:.85rem 1.25rem;font-size:.82rem;min-width:240px;box-shadow:0 4px 16px rgba(0,0,0,.2)';
      t.innerHTML='✓ Booking <strong>'+d.booking_ref+'</strong> created! <a href="'+d.admin_url+'" style="color:#fff;margin-left:.5rem;text-decoration:underline">View →</a>';
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
  btn.disabled=false; btn.textContent='✓ Create Booking';
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
