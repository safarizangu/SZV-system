<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

$db = \App\Models\Database::getInstance();

// ── AJAX: update status ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    if ($_POST['action'] === 'update_status') {
        $id     = (int)$_POST['id'];
        $status = trim($_POST['status'] ?? '');
        $allowed = ['new','in_review','quoted','converted','closed'];
        if ($id && in_array($status, $allowed, true)) {
            try {
                $db->prepare("UPDATE custom_safari_requests SET status=? WHERE id=?")
                   ->execute([$status, $id]);
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false]);
        }
    }
    exit;
}

// ── Filters + pagination ────────────────────────────────────
$statusFilter = trim($_GET['status'] ?? '');
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($statusFilter) {
    $where[]  = 'status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like    = "%{$search}%";
    $params  = array_merge($params, [$like, $like, $like]);
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM custom_safari_requests $whereSQL");
    $cntStmt->execute($params);
    $totalRows  = (int)$cntStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    $rowStmt = $db->prepare(
        "SELECT * FROM custom_safari_requests $whereSQL
         ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
    );
    $rowStmt->execute($params);
    $requests = $rowStmt->fetchAll();
} catch (\Exception $e) {
    $requests   = [];
    $totalRows  = 0;
    $totalPages = 1;
    error_log('[SZV CustomReqs] ' . $e->getMessage());
}

// Auto-mark new → in_review when listing all
if (!$search && !$statusFilter) {
    try {
        $db->exec("UPDATE custom_safari_requests SET status='in_review' WHERE status='new'");
    } catch (\Exception $e) {}
}

// Status helpers
$statusColors = [
    'new'       => ['bg'=>'#0a0a0a','color'=>'#fff',    'label'=>'New'],
    'in_review' => ['bg'=>'#eff6ff','color'=>'#1d4ed8', 'label'=>'In Review'],
    'quoted'    => ['bg'=>'#fef3e2','color'=>'#92400e', 'label'=>'Quoted'],
    'converted' => ['bg'=>'#f0fdf4','color'=>'#166534', 'label'=>'Converted'],
    'closed'    => ['bg'=>'#f3f4f6','color'=>'#6b7280', 'label'=>'Closed'],
];

// Summary counts
$counts = [];
try {
    $rows = $db->query("SELECT status, COUNT(*) as cnt FROM custom_safari_requests GROUP BY status")->fetchAll();
    foreach ($rows as $r) $counts[$r['status']] = (int)$r['cnt'];
} catch (\Exception $e) {}

$pageTitle = 'Custom Safari Requests';
ob_start();
?>

<!-- ── Summary cards ──────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.75rem;margin-bottom:1.75rem">
  <?php foreach ($statusColors as $key => $sc): ?>
  <a href="?status=<?= $key ?>" style="text-decoration:none;display:block;border:1.5px solid <?= $statusFilter===$key?'#0a0a0a':'#e5e7eb' ?>;background:<?= $sc['bg'] ?>;padding:.85rem 1rem">
    <div style="font-family:var(--font-disp);font-size:1.5rem;font-weight:500;color:<?= $sc['color'] ?>"><?= $counts[$key] ?? 0 ?></div>
    <div style="font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:<?= $sc['color'] ?>;margin-top:.2rem"><?= $sc['label'] ?></div>
  </a>
  <?php endforeach; ?>
</div>

<!-- ── Toolbar ─────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
    <?php if ($statusFilter): ?>
    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
    <?php endif; ?>
    <div class="search-bar">
      <input type="text" name="search" placeholder="Search name, email, phone…"
             value="<?= htmlspecialchars($search) ?>">
      <button type="submit">Search</button>
    </div>
    <?php if ($search || $statusFilter): ?>
    <a href="custom_requests.php" class="admin-btn admin-btn--outline">Clear</a>
    <?php endif; ?>
  </form>
  <span style="font-size:.78rem;color:var(--gray-500)"><?= number_format($totalRows) ?> request<?= $totalRows!==1?'s':'' ?></span>
</div>

<!-- ── Table ───────────────────────────────────────────────── -->
<div class="admin-table-wrap">
<table class="admin-table">
  <thead>
    <tr>
      <th>#</th>
      <th>Client</th>
      <th>Destinations</th>
      <th>Group</th>
      <th>Duration</th>
      <th>Travel Date</th>
      <th>Budget</th>
      <th>Status</th>
      <th>Submitted</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($requests)): ?>
    <tr><td colspan="10" class="admin-empty">No custom safari requests found.</td></tr>
  <?php else: ?>
  <?php foreach ($requests as $r):
    $dests = $r['destinations'] ?? '';
    // Handle JSON array or plain string
    $destsArr = json_decode($dests, true);
    if (is_array($destsArr)) {
        $destsDisplay = implode(', ', array_map('htmlspecialchars', $destsArr));
    } else {
        $destsDisplay = htmlspecialchars($dests ?: '—');
    }
    $exps = $r['experiences'] ?? '';
    $expsArr = json_decode($exps, true);
    if (!is_array($expsArr)) $expsArr = array_filter(explode(',', $exps));
    $sc = $statusColors[$r['status']] ?? $statusColors['in_review'];
    $adults   = (int)($r['adults']   ?? 0);
    $children = (int)($r['children'] ?? 0);
    $groupStr = $adults ? $adults . ' adult' . ($adults!==1?'s':'') . ($children ? ' + '.$children.' child'.($children!==1?'ren':'') : '') : ($r['travelers'] ?? '—');
  ?>
  <tr>
    <td style="color:var(--gray-500);font-size:.75rem"><?= $r['id'] ?></td>
    <td>
      <strong style="font-size:.85rem"><?= htmlspecialchars($r['full_name']) ?></strong><br>
      <a href="mailto:<?= htmlspecialchars($r['email']) ?>" style="font-size:.72rem;color:#6b7280"><?= htmlspecialchars($r['email']) ?></a><br>
      <?php if ($r['phone']): ?>
      <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$r['phone']) ?>" target="_blank" style="font-size:.7rem;color:#25D366;font-weight:600"><?= htmlspecialchars($r['phone']) ?></a>
      <?php endif; ?>
    </td>
    <td style="font-size:.78rem;max-width:160px">
      <?php if ($destsDisplay && $destsDisplay !== '—'): ?>
        <span title="<?= $destsDisplay ?>"><?= mb_strlen($destsDisplay) > 40 ? mb_substr($destsDisplay,0,38).'…' : $destsDisplay ?></span>
      <?php else: ?>—<?php endif; ?>
    </td>
    <td style="font-size:.78rem;white-space:nowrap"><?= htmlspecialchars($groupStr) ?></td>
    <td style="font-size:.78rem"><?= $r['duration_days'] ? $r['duration_days'].' days' : '—' ?></td>
    <td style="font-size:.78rem;white-space:nowrap">
      <?= $r['start_date'] ? date('d M Y', strtotime($r['start_date'])) : '—' ?>
      <?php if (!empty($r['flexible_dates']) && $r['flexible_dates'] !== 'No'): ?>
        <br><span style="font-size:.65rem;color:#9ca3af">Flexible</span>
      <?php endif; ?>
    </td>
    <td style="font-size:.78rem"><?= htmlspecialchars($r['total_budget'] ?? '—') ?: '—' ?></td>
    <td>
      <select class="status-select" onchange="updateStatus(<?= $r['id'] ?>, this.value)"
              style="font-size:.72rem;padding:.3rem .5rem;border:1.5px solid #e5e7eb;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-weight:700;cursor:pointer">
        <?php foreach ($statusColors as $sv => $sl): ?>
        <option value="<?= $sv ?>" <?= $r['status']===$sv?'selected':'' ?>><?= $sl['label'] ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td style="font-size:.72rem;color:#9ca3af;white-space:nowrap">
      <?= date('d M Y', strtotime($r['created_at'])) ?><br>
      <?= date('H:i', strtotime($r['created_at'])) ?>
    </td>
    <td>
      <div style="display:flex;gap:.3rem;flex-wrap:wrap">
        <button class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem"
                onclick='viewRequest(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'>
          View
        </button>
        <a href="quotation_new.php?from_request=<?= $r['id'] ?>"
           class="admin-btn admin-btn--primary" style="font-size:.65rem;padding:.3rem .6rem">
          + Quote
        </a>
        <?php if ($r['email']): ?>
        <a href="mailto:<?= htmlspecialchars($r['email']) ?>?subject=Your+Custom+Safari+—+Safari+Zangu+Ventures"
           class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">
          Email
        </a>
        <?php endif; ?>
        <?php if ($r['phone']): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$r['phone']) ?>?text=Dear+<?= urlencode($r['full_name']) ?>%2C+thank+you+for+your+custom+safari+enquiry.+We+have+reviewed+your+request+and+would+love+to+help+plan+your+perfect+safari."
           target="_blank" class="admin-btn admin-btn--success" style="font-size:.65rem;padding:.3rem .6rem;background:#25D366;border-color:#25D366">
          WA
        </a>
        <?php endif; ?>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
</table>
</div>

<!-- ── Pagination ──────────────────────────────────────────── -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <span style="font-size:.78rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?></span>
  <div class="page-btns">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">
      <button class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></button>
    </a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- ══ DETAIL MODAL ════════════════════════════════════════ -->
<div class="modal-overlay" id="crModal" onclick="if(event.target===this)closeModal()">
  <div class="modal-box" style="max-width:640px">
    <div class="modal-header">
      <span class="modal-title" id="crModalTitle">Request Detail</span>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div id="crModalBody" style="overflow-y:auto;max-height:70vh"></div>
    <div style="padding:1rem 1.5rem;border-top:1px solid #e5e7eb;display:flex;gap:.5rem;flex-wrap:wrap" id="crModalActions"></div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
/* ── Status update ──────────────────────────────────────── */
async function updateStatus(id, newStatus) {
  var fd = new FormData();
  fd.append('action', 'update_status');
  fd.append('id', id);
  fd.append('status', newStatus);
  try {
    var res  = await fetch('custom_requests.php', { method:'POST', body:fd });
    var data = await res.json();
    toast(data.success ? 'Status updated ✓' : 'Update failed', data.success);
  } catch(e) { toast('Network error', false); }
}

/* ── View request modal ─────────────────────────────────── */
function viewRequest(r) {
  document.getElementById('crModalTitle').textContent = 'Request #' + r.id + ' — ' + r.full_name;

  // Parse JSON arrays
  function parseList(val) {
    try {
      var arr = JSON.parse(val);
      if (Array.isArray(arr)) return arr.join(', ');
    } catch(e) {}
    return val || '—';
  }

  var dests = parseList(r.destinations);
  var exps  = parseList(r.experiences);

  function row(label, val) {
    if (!val || val === 'null' || val === '—' || val === '') return '';
    return '<div class="detail-row"><div class="detail-label">' + label + '</div><div>' + val + '</div></div>';
  }

  var adults   = parseInt(r.adults)   || 0;
  var children = parseInt(r.children) || 0;
  var group    = adults ? adults + ' adult' + (adults!==1?'s':'') + (children?' + '+children+' child'+(children!==1?'ren':''):'') : (r.travelers || '—');

  document.getElementById('crModalBody').innerHTML =
    row('Full Name',      '<strong>' + r.full_name + '</strong>') +
    row('Email',          r.email ? '<a href="mailto:'+r.email+'" style="text-decoration:underline">'+r.email+'</a>' : '—') +
    row('Phone / WA',     r.phone ? '<a href="https://wa.me/'+r.phone.replace(/[^0-9]/g,'')+'" target="_blank" style="color:#25D366;font-weight:600">'+r.phone+'</a>' : '—') +
    row('Country',        r.country) +
    row('Group',          group) +
    row('Duration',       r.duration_days ? r.duration_days + ' days' : '') +
    row('Start Date',     r.start_date ? new Date(r.start_date).toLocaleDateString('en-GB',{day:'2-digit',month:'long',year:'numeric'}) : '') +
    row('Flexible Dates', r.flexible_dates) +
    row('Destinations',   dests) +
    row('Experiences',    exps) +
    row('Accommodation',  r.accommodation_standard) +
    row('Room Layout',    r.room_layout) +
    row('Meal Plan',      r.meal_plan) +
    row('Guide Language', r.guide_language) +
    row('Budget',         r.total_budget) +
    row('Message',        r.message ? '<div style="line-height:1.7;white-space:pre-wrap">' + r.message + '</div>' : '') +
    row('Status',         '<span style="font-weight:700;text-transform:uppercase;font-size:.75rem">' + r.status + '</span>') +
    row('Submitted',      new Date(r.created_at).toLocaleString('en-GB'));

  // Action buttons
  var actions = '<a href="quotation_new.php?from_request=' + r.id + '" class="admin-btn admin-btn--primary">+ Create Quotation</a>';
  if (r.email) actions += ' <a href="mailto:'+r.email+'?subject=Your+Custom+Safari+—+Safari+Zangu+Ventures" class="admin-btn admin-btn--outline">Email Client</a>';
  if (r.phone) actions += ' <a href="https://wa.me/'+r.phone.replace(/[^0-9]/g,'')+'" target="_blank" class="admin-btn" style="background:#25D366;color:#fff;border-color:#25D366">WhatsApp</a>';
  document.getElementById('crModalActions').innerHTML = actions;

  document.getElementById('crModal').classList.add('open');
}

function closeModal() {
  document.getElementById('crModal').classList.remove('open');
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });

/* ── Toast ──────────────────────────────────────────────── */
function toast(msg, ok) {
  ok = ok === undefined ? true : ok;
  var t = document.createElement('div');
  t.className = 'toast';
  t.textContent = msg;
  if (!ok) t.style.background = '#dc2626';
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(function(){ t.remove(); }, 3000);
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
