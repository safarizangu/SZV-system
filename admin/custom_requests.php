<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Services\OwnershipScope;
use App\Services\AccessGuard;
AuthMiddleware::requireAdmin();

$db = \App\Models\Database::getInstance();

// ── AJAX: update status ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    \App\Middleware\CsrfMiddleware::validateOrDie();
    if ($_POST['action'] === 'update_status') {
        $id     = (int)$_POST['id'];
        $status = trim($_POST['status'] ?? '');
        if (OwnershipScope::isScoped() && !OwnershipScope::ownsCustomRequest($db, $id, OwnershipScope::currentAgentId())) {
            AccessGuard::denyJson('custom_request', (string)$id, 'update_status:' . $status);
        }
        $allowed = ['new','in_review','quoted','converted','closed'];
        if ($id && in_array($status, $allowed, true)) {
            try {
                // first_response_at is set once, the first time a request
                // moves off "new" — the proxy we have for "staff actually
                // acted on this," used to measure the under-1-hour promise.
                if ($status !== 'new') {
                    $db->prepare("UPDATE custom_safari_requests SET status=?, first_response_at=COALESCE(first_response_at, NOW()) WHERE id=?")
                       ->execute([$status, $id]);
                } else {
                    $db->prepare("UPDATE custom_safari_requests SET status=? WHERE id=?")
                       ->execute([$status, $id]);
                }
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false]);
        }
    }

    // Real action replacing the old page-load bulk transition: fired once
    // from viewRequest() the moment staff actually opens a request's detail
    // modal — not when the list merely renders. first_response_at is the
    // dashboard's own proxy for "staff responded," so stamping it on a page
    // load (which could be an automated refresh, a filter click, or nobody
    // actually reading anything) made that metric meaningless.
    if ($_POST['action'] === 'mark_viewed') {
        $id = (int)($_POST['id'] ?? 0);
        if (OwnershipScope::isScoped() && !OwnershipScope::ownsCustomRequest($db, $id, OwnershipScope::currentAgentId())) {
            AccessGuard::denyJson('custom_request', (string)$id, 'mark_viewed');
        }
        if ($id) {
            try {
                $db->prepare("UPDATE custom_safari_requests SET status='in_review', first_response_at=COALESCE(first_response_at, NOW()) WHERE id=? AND status='new'")
                   ->execute([$id]);
            } catch (\Exception $e) {}
        }
        echo json_encode(['success' => true]);
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
    $where[]  = 'custom_safari_requests.status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $where[] = '(custom_safari_requests.full_name LIKE ? OR custom_safari_requests.email LIKE ? OR custom_safari_requests.phone LIKE ?)';
    $like    = "%{$search}%";
    $params  = array_merge($params, [$like, $like, $like]);
}
if (OwnershipScope::isScoped()) {
    [$scopeSql, $scopeParams] = OwnershipScope::customRequestsListSql(OwnershipScope::currentAgentId());
    $where[] = $scopeSql;
    $params  = array_merge($params, $scopeParams);
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM custom_safari_requests $whereSQL");
    $cntStmt->execute($params);
    $totalRows  = (int)$cntStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    $rowStmt = $db->prepare(
        "SELECT custom_safari_requests.*, agent.full_name AS agent_name
         FROM custom_safari_requests
         LEFT JOIN admin_users agent ON agent.id = custom_safari_requests.submitted_by_agent_id
         $whereSQL
         ORDER BY custom_safari_requests.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
    );
    $rowStmt->execute($params);
    $requests = $rowStmt->fetchAll();
} catch (\Exception $e) {
    $requests   = [];
    $totalRows  = 0;
    $totalPages = 1;
    error_log('[SZV CustomReqs] ' . $e->getMessage());
}

// Status helpers — one status→color config (bg/color match the shared
// .badge-status--* modifiers in admin.css) shared with the JS below via
// STATUS_META, same pattern as bookings.php's $statusColors.
$statusColors = [
    'new'       => ['bg'=>'var(--black)',      'color'=>'var(--white)',    'badge'=>'dark',  'label'=>'New'],
    'in_review' => ['bg'=>'var(--blue-light)',  'color'=>'#1e40af',        'badge'=>'blue',  'label'=>'In Review'],
    'quoted'    => ['bg'=>'var(--amber-light)', 'color'=>'#92400e',        'badge'=>'amber', 'label'=>'Quoted'],
    'converted' => ['bg'=>'var(--green-light)', 'color'=>'#166534',        'badge'=>'green', 'label'=>'Converted'],
    'closed'    => ['bg'=>'var(--gray-100)',    'color'=>'var(--gray-700)','badge'=>'gray',  'label'=>'Closed'],
];

// Summary counts
$counts = [];
try {
    $countWhere  = '';
    $countParams = [];
    if (OwnershipScope::isScoped()) {
        [$scopeSql, $scopeParams] = OwnershipScope::customRequestsListSql(OwnershipScope::currentAgentId());
        $countWhere  = "WHERE $scopeSql";
        $countParams = $scopeParams;
    }
    $cs = $db->prepare("SELECT status, COUNT(*) as cnt FROM custom_safari_requests $countWhere GROUP BY status");
    $cs->execute($countParams);
    foreach ($cs->fetchAll() as $r) $counts[$r['status']] = (int)$r['cnt'];
} catch (\Exception $e) {}

$pageTitle = 'Custom Safari Requests';
ob_start();
?>

<!-- ── Status filter pills ─────────────────────────────────── -->
<div class="admin-filter-pills">
  <a href="custom_requests.php" class="admin-filter-pill<?= !$statusFilter ? ' active' : '' ?>">
    All &nbsp;<span style="opacity:.6"><?= array_sum($counts) ?></span>
  </a>
  <?php foreach ($statusColors as $key => $sc):
    $pillMod = $sc['badge'] !== 'gray' ? ' admin-filter-pill--'.$sc['badge'] : '';
  ?>
  <a href="?status=<?= $key ?>" class="admin-filter-pill<?= $pillMod ?><?= $statusFilter===$key?' active':'' ?>">
    <?= $sc['label'] ?> &nbsp;<span style="opacity:.6"><?= $counts[$key] ?? 0 ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ── Toolbar ─────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
    <?php if ($statusFilter): ?>
    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
    <?php endif; ?>
    <input type="text" name="search" class="admin-input" style="min-width:220px;flex:1" placeholder="Search name, email, phone…" value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="admin-btn admin-btn--primary">Search</button>
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
      <strong style="font-size:.85rem"><?= htmlspecialchars($r['full_name']) ?></strong>
      <?php if (!empty($r['agent_name'])): ?>
      <span class="badge-status badge-status--blue" style="font-size:.58rem;margin-left:.3rem;vertical-align:middle" title="Submitted by agent <?= htmlspecialchars($r['agent_name']) ?>">Agent: <?= htmlspecialchars($r['agent_name']) ?></span>
      <?php endif; ?>
      <br>
      <a href="mailto:<?= htmlspecialchars($r['email']) ?>" style="font-size:.72rem;color:var(--gray-500)"><?= htmlspecialchars($r['email']) ?></a><br>
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
        <br><span style="font-size:.65rem;color:var(--gray-500)">Flexible</span>
      <?php endif; ?>
    </td>
    <td style="font-size:.78rem"><?= htmlspecialchars($r['total_budget'] ?? '—') ?: '—' ?></td>
    <td>
      <select id="cr-status-<?= $r['id'] ?>" class="status-select" onchange="updateStatus(<?= $r['id'] ?>, this.value, this)"
              data-prev="<?= htmlspecialchars($r['status']) ?>"
              style="font-size:.72rem;padding:.3rem .5rem;border:1.5px solid var(--gray-200);background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-weight:700;cursor:pointer">
        <?php foreach ($statusColors as $sv => $sl): ?>
        <option value="<?= $sv ?>" <?= $r['status']===$sv?'selected':'' ?>><?= $sl['label'] ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td style="font-size:.72rem;color:var(--gray-500);white-space:nowrap">
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
<div class="pagination" style="border-top:none">
  <span style="font-size:.78rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?></span>
  <div class="page-btns">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>"
       class="page-btn<?= $i===$page?' active':'' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- ══ DETAIL MODAL ════════════════════════════════════════ -->
<div id="crModal" class="admin-modal-overlay">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <span class="admin-modal-title" id="crModalTitle">Request Detail</span>
      <button onclick="closeModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div id="crModalBody" style="overflow-y:auto;max-height:70vh"></div>
    <div class="admin-modal-footer" id="crModalActions"></div>
  </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script>
/* ── Status update ──────────────────────────────────────── */
async function updateStatus(id, newStatus, sel) {
  var label = sel.options[sel.selectedIndex].text;
  if (!(await confirmStatusChange(sel, 'Change this request\'s status to "'+label+'"?'))) return;
  var fd = new FormData();
  fd.append('action', 'update_status');
  fd.append('id', id);
  fd.append('status', newStatus);
  fd.append('_csrf', window.CSRF_TOKEN || '');
  try {
    var res  = await fetch('custom_requests.php', { method:'POST', body:fd });
    var data = await res.json();
    if (data.success) { sel.dataset.prev = newStatus; } else { sel.value = sel.dataset.prev; }
    toast(data.success ? 'Status updated' : 'Update failed', data.success ? 'ok' : 'err');
  } catch(e) { sel.value = sel.dataset.prev; toast('Network error', 'err'); }
}

/* ── View request modal ─────────────────────────────────── */
var STATUS_META = <?= json_encode($statusColors) ?>;

function viewRequest(r) {
  document.getElementById('crModalTitle').textContent = 'Request #' + r.id + ' — ' + r.full_name;

  // Real trigger for new → in_review (replacing the old page-load bulk
  // transition): staff is genuinely looking at this request right now.
  // Fire-and-forget — the modal doesn't wait on it — but update locally
  // so the modal and the row's status dropdown both reflect it immediately
  // instead of showing stale "New" until the next full page load.
  if (r.status === 'new') {
    var fd = new FormData();
    fd.append('action', 'mark_viewed');
    fd.append('id', r.id);
    fd.append('_csrf', window.CSRF_TOKEN || '');
    fetch('custom_requests.php', { method: 'POST', body: fd }).catch(function(){});

    r.status = 'in_review';
    var sel = document.getElementById('cr-status-' + r.id);
    if (sel) {
      sel.value = 'in_review';
      var meta = STATUS_META['in_review'];
      if (meta) { sel.style.background = meta.bg; sel.style.color = meta.color; }
    }
  }

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
    return '<div class="detail-row"><span class="detail-label">' + label + '</span><span>' + val + '</span></div>';
  }

  var adults   = parseInt(r.adults)   || 0;
  var children = parseInt(r.children) || 0;
  var group    = adults ? adults + ' adult' + (adults!==1?'s':'') + (children?' + '+children+' child'+(children!==1?'ren':''):'') : (r.travelers || '—');

  document.getElementById('crModalBody').innerHTML =
    row('Full Name',      '<strong>' + r.full_name + '</strong>') +
    row('Submitted By',   r.agent_name ? 'Agent — ' + r.agent_name : 'Website') +
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
  if (r.phone) actions += ' <a href="https://wa.me/'+r.phone.replace(/[^0-9]/g,'')+'" target="_blank" class="admin-btn admin-btn--success" style="background:#25D366;border-color:#25D366">WhatsApp</a>';
  document.getElementById('crModalActions').innerHTML = actions;

  document.getElementById('crModal').classList.add('open');
}

function closeModal() {
  document.getElementById('crModal').classList.remove('open');
}
document.getElementById('crModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });

/* ── Toast ──────────────────────────────────────────────── */
function toast(msg, type) {
  var t = document.createElement('div');
  t.className = 'toast' + (type==='ok' ? ' toast--ok' : type==='err' ? ' toast--err' : '');
  t.textContent = msg;
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(function(){ t.remove(); }, 3000);
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
