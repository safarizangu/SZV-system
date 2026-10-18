<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Leads Pipeline';
$myId     = (int)($_SESSION['admin_user_id'] ?? 0);
// Ownership scoping (own leads only) applies to the agent role alone —
// staff sees/manages every lead, same as admin, just without Settings
// access elsewhere in the panel.
$isAgentUser = AuthMiddleware::isAgentRole();

// ── Handle AJAX JSON responses ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    ob_end_clean();
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $action = $_POST['action'];

    if ($action === 'add_lead') {
        $data = [
            'full_name'     => trim($_POST['full_name']     ?? ''),
            'email'         => trim($_POST['email']         ?? ''),
            'phone'         => trim($_POST['phone']         ?? ''),
            'country'       => trim($_POST['country']       ?? ''),
            'budget_range'  => trim($_POST['budget_range']  ?? ''),
            'source'        => trim($_POST['source']        ?? 'website'),
            'package_name'  => trim($_POST['package_name']  ?? ''),
            'party_size'    => (int)($_POST['adults']       ?? 2),
            'travel_date'   => !empty($_POST['travel_date']) ? $_POST['travel_date'] : null,
            'pipeline_stage'=> 'lead',
            'notes'         => trim($_POST['notes']         ?? ''),
            'assigned_to'   => !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null,
            // An agent can only ever create leads owned by themselves —
            // staff/admin may optionally hand a new lead straight to an agent.
            'agent_id'      => $isAgentUser ? $myId : (!empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null),
        ];
        if (!$data['full_name'] || !$data['email']) {
            echo json_encode(['success'=>false,'message'=>'Name and email required.']);
            exit;
        }
        try {
            $cols = implode(',', array_map(fn($k)=>"`$k`", array_keys($data)));
            $vals = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO leads ($cols,created_at) VALUES ($vals,NOW())")
               ->execute(array_values($data));
            $newId = (int)$db->lastInsertId();
            \App\Services\ActivityLog::record('create', 'lead', $newId, ['full_name'=>$data['full_name']]);
            echo json_encode(['success'=>true,'id'=>$newId]);
        } catch(\Exception $e) {
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update_stage') {
        $allowed = ['lead','qualified','quoted','negotiation','booked','travelled','completed','lost'];
        $stage   = trim($_POST['stage'] ?? '');
        $id      = (int)($_POST['id']   ?? 0);
        if (!in_array($stage, $allowed, true)) {
            echo json_encode(['success'=>false,'message'=>'Invalid stage.']); exit;
        }
        // Per-user permission override — doesn't apply to agents, who are
        // already scoped to their own leads via $isAgentUser below.
        if (!$isAgentUser && !AuthMiddleware::canChangeStatus()) {
            echo json_encode(['success'=>false,'message'=>'Permission denied.']); exit;
        }

        // Commission creation on a genuine transition into 'booked' (and the
        // agent email that goes with it) lives in PipelineSync — shared with
        // the automatic advance api/convert_quotation.php triggers, so that
        // logic exists exactly once. This manual path additionally allows
        // ANY stage (including backward corrections or 'lost'), unlike the
        // automatic advance-only one.
        $result = \App\Services\PipelineSync::setLeadStageManual($db, $id, $stage, $isAgentUser, $myId);
        if (!$result['ok']) {
            echo json_encode(['success'=>false,'message'=>$result['message']]); exit;
        }
        echo json_encode(['success'=>true, 'commission_created'=>$result['commission_created']]);
        exit;
    }

    if ($action === 'delete_lead') {
        // Agent role never — staff/admin/superadmin can delete leads unless
        // the per-user permission override (admin/users.php) says otherwise.
        if ($isAgentUser || !AuthMiddleware::canDeleteRecords()) {
            echo json_encode(['success'=>false,'message'=>'Permission denied.']); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
        \App\Services\ActivityLog::record('delete', 'lead', $id);
        echo json_encode(['success'=>true]);
        exit;
    }
    echo json_encode(['success'=>false,'message'=>'Unknown action']);
    exit;
}

// ── Filters + pagination ─────────────────────────────────────
$stageFilter = trim($_GET['stage'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 20;
$offset      = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($isAgentUser) { $where[] = 'agent_id=?'; $params[] = $myId; }
if ($stageFilter)  { $where[] = 'pipeline_stage=?'; $params[] = $stageFilter; }
$whereSQL = $where ? 'WHERE '.implode(' AND ', $where) : '';

$rows = []; $totalRows = 0; $totalPages = 0;
try {
    $total = $db->prepare("SELECT COUNT(*) FROM leads $whereSQL");
    $total->execute($params);
    $totalRows  = (int)$total->fetchColumn();
    $totalPages = (int)ceil($totalRows / $perPage);
    $stmt = $db->prepare("SELECT * FROM leads $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch(\Exception $e) {}

$stages = ['lead','qualified','quoted','negotiation','booked','travelled','completed','lost'];
// Stage→color map keyed to the shared .badge-status--* palette (same bg/text
// values those classes use) so a pipeline stage reads the same as any other
// status pill in the panel instead of inventing its own hues per page.
$stageColors = [
    'lead'        => ['bg'=>'var(--gray-100)',   'text'=>'var(--gray-700)', 'badge'=>'gray'],
    'qualified'   => ['bg'=>'var(--blue-light)',  'text'=>'#1e40af',        'badge'=>'blue'],
    'quoted'      => ['bg'=>'var(--amber-light)', 'text'=>'#92400e',        'badge'=>'amber'],
    'negotiation' => ['bg'=>'var(--amber-light)', 'text'=>'#92400e',        'badge'=>'amber'],
    'booked'      => ['bg'=>'var(--green-light)', 'text'=>'#166534',        'badge'=>'green'],
    'travelled'   => ['bg'=>'var(--green-light)', 'text'=>'#166534',        'badge'=>'green'],
    'completed'   => ['bg'=>'var(--black)',       'text'=>'var(--white)',   'badge'=>'dark'],
    'lost'        => ['bg'=>'var(--red-light)',   'text'=>'#991b1b',        'badge'=>'red'],
];
$stageCounts = array_fill_keys($stages, 0);
try {
    $countWhereSQL = $isAgentUser ? 'WHERE agent_id=?' : '';
    $countParams   = $isAgentUser ? [$myId] : [];
    $cs = $db->prepare("SELECT pipeline_stage, COUNT(*) c FROM leads $countWhereSQL GROUP BY pipeline_stage");
    $cs->execute($countParams);
    foreach ($cs->fetchAll() as $cr) $stageCounts[$cr['pipeline_stage']] = (int)$cr['c'];
} catch(\Exception $e) {}

// Get admin users for assignment
$admins = [];
try { $admins = $db->query("SELECT id, username, full_name FROM admin_users WHERE is_active=1 ORDER BY full_name")->fetchAll(); }
catch(\Exception $e) {}

$packages = [];
try { $packages = $db->query("SELECT id,name FROM packages WHERE is_active=1 ORDER BY sort_order")->fetchAll(); }
catch(\Exception $e) {}

ob_start();
?>

<!-- Stage pills -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;align-items:center;justify-content:space-between">
  <div class="admin-filter-pills" style="margin-bottom:0">
    <a href="leads.php" class="admin-filter-pill<?= !$stageFilter?' active':'' ?>">
      All &nbsp;<span style="opacity:.6"><?= array_sum($stageCounts) ?></span>
    </a>
    <?php foreach($stages as $s):
      $sc = $stageColors[$s];
      $pillMod = in_array($sc['badge'], ['amber','green','red','blue','dark'], true) ? ' admin-filter-pill--'.$sc['badge'] : '';
    ?>
    <a href="?stage=<?= $s ?>" class="admin-filter-pill<?= $pillMod ?><?= $stageFilter===$s?' active':'' ?>">
      <?= ucfirst($s) ?> &nbsp;<span style="opacity:.6"><?= $stageCounts[$s] ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <button onclick="openAddModal()"
          class="admin-btn admin-btn--primary" style="font-size:.72rem">
    + Add Lead
  </button>
</div>

<!-- Leads Table -->
<div class="admin-table-wrap">
<table class="admin-table">
  <thead>
    <tr>
      <th>Date</th>
      <th>Name</th>
      <th>Email / Phone</th>
      <th>Country</th>
      <th>Package</th>
      <th>Travel Date</th>
      <th>Budget</th>
      <th>Stage</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r):
      $sc = $stageColors[$r['pipeline_stage']] ?? $stageColors['lead'];
    ?>
    <tr id="lead-row-<?= $r['id'] ?>">
      <td style="white-space:nowrap;font-size:.78rem"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
      <td>
        <strong style="font-size:.85rem"><?= htmlspecialchars($r['full_name']) ?></strong>
        <?php if (!empty($r['source'])): ?>
        <div style="font-size:.65rem;color:var(--gray-500)"><?= htmlspecialchars($r['source']) ?></div>
        <?php endif; ?>
      </td>
      <td>
        <a href="mailto:<?= htmlspecialchars($r['email']) ?>" style="color:inherit;font-size:.78rem"><?= htmlspecialchars($r['email']) ?></a>
        <?php if (!empty($r['phone'])): ?>
        <div style="font-size:.72rem"><a href="https://wa.me/<?= preg_replace('/[^0-9]/','',($r['phone'])) ?>" target="_blank" style="color:#166534"><?= htmlspecialchars($r['phone']) ?></a></div>
        <?php endif; ?>
      </td>
      <td style="font-size:.82rem"><?= htmlspecialchars($r['country'] ?? '—') ?></td>
      <td style="font-size:.78rem"><?= htmlspecialchars($r['package_name'] ?: '—') ?></td>
      <td style="white-space:nowrap;font-size:.82rem">
        <?= !empty($r['travel_date']) ? date('d/m/Y', strtotime($r['travel_date'])) : '—' ?>
      </td>
      <td style="font-size:.82rem"><?= htmlspecialchars($r['budget_range'] ?? '—') ?></td>
      <td>
        <select onchange="updateStage(<?= $r['id'] ?>, this.value, this)"
                data-prev="<?= htmlspecialchars($r['pipeline_stage']) ?>"
                style="border:1.5px solid var(--gray-200);padding:.25rem .5rem;font-size:.7rem;background:<?= $sc['bg'] ?>;color:<?= $sc['text'] ?>;font-weight:700;max-width:120px;cursor:pointer">
          <?php foreach ($stages as $s): ?>
          <option value="<?= $s ?>" <?= $r['pipeline_stage']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td style="white-space:nowrap">
        <a href="mailto:<?= htmlspecialchars($r['email']) ?>"
           class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .55rem"><?= \App\Helpers\AdminIcon::render('messages', 12) ?></a>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',($r['phone']??'')) ?>?text=<?= urlencode('Hi '.$r['full_name'].',') ?>"
           target="_blank"
           class="admin-btn admin-btn--success" style="font-size:.65rem;padding:.3rem .55rem">WA</a>
        <?php if (!empty($r['email'])): ?>
        <a href="../admin/quotation_new.php?from_lead=<?= $r['id'] ?>"
           class="admin-btn admin-btn--tint-blue" style="font-size:.65rem;padding:.3rem .55rem">Quote</a>
        <?php endif; ?>
        <?php if (!$isAgentUser): ?>
        <button onclick="deleteLead(<?= $r['id'] ?>)"
                class="admin-btn admin-btn--danger" style="font-size:.65rem;padding:.3rem .55rem"><?= \App\Helpers\AdminIcon::render('trash', 12) ?></button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
    <tr><td colspan="9" class="admin-empty">No leads yet. Click "+ Add Lead" to add your first lead.</td></tr>
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
    <a href="?page=<?= $i ?>&stage=<?= urlencode($stageFilter) ?>"
       class="page-btn<?= $i===$page?' active':'' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Add Lead Modal ──────────────────────────────────────── -->
<div id="addLeadModal" class="admin-modal-overlay">
  <div class="admin-modal">
    <div class="admin-modal-header" style="position:sticky;top:0;background:var(--white);z-index:1">
      <span class="admin-modal-title">Add New Lead</span>
      <button onclick="closeAddModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;margin-bottom:.85rem">
        <div>
          <label class="admin-label">Full Name *</label>
          <input id="lf-name" class="admin-input" placeholder="Jane Smith" required>
        </div>
        <div>
          <label class="admin-label">Email *</label>
          <input id="lf-email" type="email" class="admin-input" placeholder="jane@email.com" required>
        </div>
        <div>
          <label class="admin-label">Phone (WhatsApp)</label>
          <input id="lf-phone" class="admin-input" placeholder="+44 7700 900000">
        </div>
        <div>
          <label class="admin-label">Country</label>
          <input id="lf-country" class="admin-input" placeholder="United Kingdom">
        </div>
        <div>
          <label class="admin-label">Travel Date</label>
          <input id="lf-travel-date" type="date" class="admin-input"
                 min="<?= date('Y-m-d') ?>"
                 style="font-family:monospace">
          <small style="font-size:.65rem;color:var(--gray-500)">dd/mm/yyyy — shown as date to client</small>
        </div>
        <div>
          <label class="admin-label">Party Size (Adults)</label>
          <input id="lf-adults" type="number" min="1" max="20" value="2" class="admin-input">
        </div>
        <div>
          <label class="admin-label">Package Interest</label>
          <select id="lf-package" class="admin-input">
            <option value="">— Select package —</option>
            <?php foreach ($packages as $pkg): ?>
            <option value="<?= htmlspecialchars($pkg['name']) ?>"><?= htmlspecialchars($pkg['name']) ?></option>
            <?php endforeach; ?>
            <option value="Custom Safari">Custom Safari</option>
          </select>
        </div>
        <div>
          <label class="admin-label">Budget Range (USD)</label>
          <select id="lf-budget" class="admin-input">
            <option value="">— Select range —</option>
            <option value="Under $1,000">Under $1,000</option>
            <option value="$1,000 – $2,000">$1,000 – $2,000</option>
            <option value="$2,000 – $3,500">$2,000 – $3,500</option>
            <option value="$3,500 – $5,000">$3,500 – $5,000</option>
            <option value="$5,000 – $8,000">$5,000 – $8,000</option>
            <option value="$8,000+">$8,000+</option>
            <option value="Flexible">Flexible / Unknown</option>
          </select>
        </div>
        <div>
          <label class="admin-label">Lead Source</label>
          <select id="lf-source" class="admin-input">
            <option value="website">Website</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="email">Email Enquiry</option>
            <option value="referral">Referral</option>
            <option value="instagram">Instagram</option>
            <option value="facebook">Facebook</option>
            <option value="safariBookings">SafariBookings.com</option>
            <option value="tripadvisor">TripAdvisor</option>
            <option value="google">Google Search</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div>
          <label class="admin-label">Assign To</label>
          <select id="lf-assigned" class="admin-input">
            <option value="">— Unassigned —</option>
            <?php foreach ($admins as $adm): ?>
            <option value="<?= $adm['id'] ?>"><?= htmlspecialchars($adm['full_name'] ?: $adm['username']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="margin-bottom:1.25rem">
        <label class="admin-label">Notes</label>
        <textarea id="lf-notes" class="admin-input" rows="3"
                  placeholder="Any additional details about this lead..."></textarea>
      </div>
      <div style="display:flex;gap:.5rem">
        <button onclick="submitAddLead()" class="admin-btn admin-btn--primary">Save Lead</button>
        <button onclick="closeAddModal()" class="admin-btn admin-btn--outline">Cancel</button>
        <span id="lf-status" style="font-size:.78rem;color:var(--gray-500);display:flex;align-items:center;margin-left:.5rem"></span>
      </div>
    </div>
  </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script>
var STAGE_COLORS = <?= json_encode($stageColors) ?>;

function openAddModal() {
    document.getElementById('addLeadModal').classList.add('open');
    document.getElementById('lf-name').focus();
}
function closeAddModal() {
    document.getElementById('addLeadModal').classList.remove('open');
    ['lf-name','lf-email','lf-phone','lf-country','lf-notes'].forEach(function(id){
        var el = document.getElementById(id); if(el) el.value='';
    });
    document.getElementById('lf-travel-date').value = '';
    document.getElementById('lf-adults').value = 2;
}
document.getElementById('addLeadModal').addEventListener('click', function(e){
    if(e.target === this) closeAddModal();
});
document.addEventListener('keydown', function(e){
    if(e.key==='Escape') closeAddModal();
});

async function submitAddLead() {
    var status = document.getElementById('lf-status');
    var name   = document.getElementById('lf-name').value.trim();
    var email  = document.getElementById('lf-email').value.trim();
    if (!name || !email) { status.textContent='Name and email required.'; status.style.color='var(--red)'; return; }

    status.textContent = 'Saving…'; status.style.color='var(--gray-500)';
    var fd = new FormData();
    fd.append('action',           'add_lead');
    fd.append('full_name',        name);
    fd.append('email',            email);
    fd.append('phone',            document.getElementById('lf-phone').value);
    fd.append('country',          document.getElementById('lf-country').value);
    fd.append('travel_date',      document.getElementById('lf-travel-date').value);
    fd.append('adults',           document.getElementById('lf-adults').value);
    fd.append('package_name', document.getElementById('lf-package').value);
    fd.append('budget_range',     document.getElementById('lf-budget').value);
    fd.append('source',           document.getElementById('lf-source').value);
    fd.append('assigned_to',      document.getElementById('lf-assigned').value);
    fd.append('notes',            document.getElementById('lf-notes').value);
    fd.append('_csrf',            window.CSRF_TOKEN || '');

    try {
        var r = await fetch('leads.php', {method:'POST', body:fd});
        var d = await r.json();
        if (d.success) {
            toast('Lead added', 'ok');
            closeAddModal();
            setTimeout(function(){ window.location.reload(); }, 800);
        } else {
            status.textContent = d.message || 'Error saving lead.';
            status.style.color = 'var(--red)';
        }
    } catch(e) {
        status.textContent = 'Network error.'; status.style.color='var(--red)';
    }
}

async function updateStage(id, stage, sel) {
    var msg = stage === 'booked'
        ? 'Mark this lead as Booked? This creates a commission record for the agent.'
        : 'Change this lead\'s stage to "'+sel.options[sel.selectedIndex].text+'"?';
    if (!(await confirmStatusChange(sel, msg, {danger: stage==='lost'}))) return;
    var fd = new FormData();
    fd.append('action','update_stage');
    fd.append('id', id);
    fd.append('stage', stage);
    fd.append('_csrf', window.CSRF_TOKEN || '');
    try {
        var r = await fetch('leads.php',{method:'POST',body:fd});
        var d = await r.json();
        if (d.success) {
            var sc = STAGE_COLORS[stage] || {bg:'var(--gray-100)',text:'var(--gray-700)'};
            sel.style.background = sc.bg; sel.style.color = sc.text;
            sel.dataset.prev = stage;
            toast(d.commission_created ? 'Stage → '+stage+' — commission created' : 'Stage → '+stage,'ok');
        } else {
            sel.value = sel.dataset.prev;
            toast(d.message || 'Failed','err');
        }
    } catch(e) { sel.value = sel.dataset.prev; toast('Failed','err'); }
}

async function deleteLead(id) {
    if (!(await confirmDialog('Delete this lead? This cannot be undone.', {danger:true}))) return;
    var fd = new FormData();
    fd.append('action','delete_lead');
    fd.append('id', id);
    fd.append('_csrf', window.CSRF_TOKEN || '');
    try {
        var r = await fetch('leads.php',{method:'POST',body:fd});
        var d = await r.json();
        if (d.success) {
            var row = document.getElementById('lead-row-'+id);
            if (row) row.remove();
            toast('Lead deleted','ok');
        }
    } catch(e) { toast('Failed','err'); }
}

function toast(msg, type) {
    var t = document.createElement('div');
    t.className = 'toast' + (type==='ok' ? ' toast--ok' : type==='err' ? ' toast--err' : '');
    t.textContent = msg;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(function(){ t.remove(); }, 3200);
}
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
?>
