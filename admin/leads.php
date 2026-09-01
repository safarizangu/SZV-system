<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

$myId   = (int)($_SESSION['admin_user_id'] ?? 0);
$myRole = $_SESSION['admin_role'] ?? 'agent';
$isAdmin = in_array($myRole, ['admin','superadmin'], true);
$pageTitle = $isAdmin ? 'Leads Pipeline' : 'My Leads';

// Load agents list (for admin to assign)
$agents = [];
try {
    $agents = $db->query("SELECT id, full_name, username, commission_rate FROM admin_users WHERE is_active=1 ORDER BY full_name")->fetchAll();
} catch(\Exception $e) {}

// Load packages for datalist
$packages = [];
try {
    $packages = $db->query("SELECT name FROM packages WHERE is_active=1 ORDER BY sort_order")->fetchAll();
} catch(\Exception $e) {}

// Filter
$stageFilter = $_GET['stage'] ?? '';
$search      = trim($_GET['q'] ?? '');
$view        = $_GET['view'] ?? 'kanban'; // kanban | list

// Build query
$where  = $isAdmin ? 'WHERE 1=1' : 'WHERE l.agent_id='.$myId;
$params = [];
if ($stageFilter) { $where .= ' AND l.pipeline_stage=?'; $params[] = $stageFilter; }
if ($search)      { $where .= ' AND (l.full_name LIKE ? OR l.email LIKE ? OR l.phone LIKE ?)'; $like="%$search%"; $params=array_merge($params,[$like,$like,$like]); }

$rows = [];
try {
    $rows = $db->prepare("SELECT l.*, u.full_name as agent_name, u.commission_rate as agent_rate
        FROM leads l
        LEFT JOIN admin_users u ON u.id=l.agent_id
        $where ORDER BY l.created_at DESC LIMIT 500");
    $rows->execute($params);
    $rows = $rows->fetchAll();
} catch(\Exception $e) { error_log($e->getMessage()); }

$stages = ['lead','qualified','quoted','negotiation','booked','travelled','completed','lost'];
$stageColors = [
    'lead'        => ['color'=>'#6b7280','bg'=>'#f3f4f6','label'=>'Lead'],
    'qualified'   => ['color'=>'#1d4ed8','bg'=>'#eff6ff','label'=>'Qualified'],
    'quoted'      => ['color'=>'#d97706','bg'=>'#fef3e2','label'=>'Quoted'],
    'negotiation' => ['color'=>'#7c3aed','bg'=>'#f5f3ff','label'=>'Negotiation'],
    'booked'      => ['color'=>'#059669','bg'=>'#ecfdf5','label'=>'Booked'],
    'travelled'   => ['color'=>'#0891b2','bg'=>'#ecfeff','label'=>'Travelled'],
    'completed'   => ['color'=>'#16a34a','bg'=>'#f0fdf4','label'=>'Completed'],
    'lost'        => ['color'=>'#dc2626','bg'=>'#fef2f2','label'=>'Lost'],
];

// Stage groups for kanban
$kanban = array_fill_keys($stages, []);
foreach ($rows as $r) {
    $s = $r['pipeline_stage'] ?? 'lead';
    if (isset($kanban[$s])) $kanban[$s][] = $r;
}

// Summary stats
$totalLeads     = count($rows);
$bookedLeads    = count(array_filter($rows, fn($r)=>in_array($r['pipeline_stage'],['booked','travelled','completed'])));
$totalCommission= array_sum(array_column(array_filter($rows, fn($r)=>$r['pipeline_stage']==='booked'),'commission_amount'));
$pendingFollowUp= count(array_filter($rows, fn($r)=>!empty($r['next_follow_up']) && $r['next_follow_up'] <= date('Y-m-d') && !in_array($r['pipeline_stage'],['booked','completed','lost'])));

ob_start();
?>

<!-- Stats row -->
<div class="admin-stats-grid" style="margin-bottom:1.5rem">
  <div class="admin-stat-card">
    <span class="admin-stat-label">Total Leads</span>
    <span class="admin-stat-value"><?= $totalLeads ?></span>
    <span class="admin-stat-sub"><?= $isAdmin ? 'All agents' : 'My leads' ?></span>
  </div>
  <div class="admin-stat-card admin-stat-card--green">
    <span class="admin-stat-label">Converted</span>
    <span class="admin-stat-value"><?= $bookedLeads ?></span>
    <span class="admin-stat-sub">Booked + completed</span>
  </div>
  <div class="admin-stat-card admin-stat-card--amber">
    <span class="admin-stat-label">Follow-ups Due</span>
    <span class="admin-stat-value"><?= $pendingFollowUp ?></span>
    <span class="admin-stat-sub">Overdue today</span>
  </div>
  <div class="admin-stat-card admin-stat-card--dark">
    <span class="admin-stat-label">Commission Earned</span>
    <span class="admin-stat-value">$<?= number_format($totalCommission, 0) ?></span>
    <span class="admin-stat-sub">Booked leads</span>
  </div>
  <?php if($isAdmin): ?>
  <a href="commissions.php" style="text-decoration:none">
  <div class="admin-stat-card" style="cursor:pointer">
    <span class="admin-stat-label">Commissions</span>
    <span class="admin-stat-value">→</span>
    <span class="admin-stat-sub">Manage payouts</span>
  </div></a>
  <?php endif; ?>
</div>

<!-- Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem">
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
    <!-- Search -->
    <form method="GET" style="display:flex;gap:.35rem">
      <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
             placeholder="Search name, email, phone…"
             class="admin-input" style="min-width:200px">
      <button class="admin-btn admin-btn--outline">Search</button>
      <?php if($search): ?><a href="leads.php?view=<?= $view ?>" class="admin-btn admin-btn--muted">Clear</a><?php endif; ?>
    </form>
    <!-- View toggle -->
    <div style="display:flex;border:1.5px solid var(--gray-200);overflow:hidden">
      <a href="?view=kanban&q=<?= urlencode($search) ?>"
         style="padding:.4rem .85rem;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;text-decoration:none;background:<?= $view==='kanban'?'#0a0a0a':'#fff' ?>;color:<?= $view==='kanban'?'#fff':'#374151' ?>">
        Kanban
      </a>
      <a href="?view=list&q=<?= urlencode($search) ?>"
         style="padding:.4rem .85rem;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;text-decoration:none;background:<?= $view==='list'?'#0a0a0a':'#fff' ?>;color:<?= $view==='list'?'#fff':'#374151' ?>">
        List
      </a>
    </div>
  </div>
  <button onclick="openAddLead()" class="admin-btn admin-btn--primary">+ Add Lead</button>
</div>

<?php if ($view === 'kanban'): ?>
<!-- ══ KANBAN VIEW ══════════════════════════════════════════ -->
<div style="overflow-x:auto;padding-bottom:1rem">
<div style="display:flex;gap:.85rem;min-width:max-content">
  <?php foreach ($stages as $stage):
    $sc = $stageColors[$stage];
    $stageRows = $kanban[$stage];
  ?>
  <div style="width:230px;flex-shrink:0;display:flex;flex-direction:column">
    <!-- Column header -->
    <div style="padding:.6rem .85rem;background:<?= $sc['bg'] ?>;border:1.5px solid <?= $sc['color'] ?>20;margin-bottom:.5rem;display:flex;align-items:center;justify-content:space-between">
      <span style="font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:<?= $sc['color'] ?>"><?= $sc['label'] ?></span>
      <span style="font-size:.75rem;font-weight:700;color:<?= $sc['color'] ?>"><?= count($stageRows) ?></span>
    </div>
    <!-- Cards -->
    <div style="display:flex;flex-direction:column;gap:.5rem;min-height:120px">
      <?php foreach ($stageRows as $r): ?>
      <div onclick="openEditLead(<?= $r['id'] ?>)"
           style="background:#fff;border:1.5px solid var(--gray-200);padding:.75rem .85rem;cursor:pointer;transition:border-color .15s"
           onmouseover="this.style.borderColor='#0a0a0a'" onmouseout="this.style.borderColor='var(--gray-200)'">
        <div style="font-weight:700;font-size:.82rem;margin-bottom:.2rem"><?= htmlspecialchars($r['full_name']) ?></div>
        <div style="font-size:.68rem;color:var(--gray-500);margin-bottom:.35rem"><?= htmlspecialchars($r['country']??'') ?> <?= $r['party_size']?'· '.$r['party_size'].'pax':'' ?></div>
        <?php if($r['package_name']): ?>
        <div style="font-size:.68rem;font-weight:600;color:#374151;margin-bottom:.25rem">📦 <?= htmlspecialchars($r['package_name']) ?></div>
        <?php endif; ?>
        <?php if($r['budget_range']): ?>
        <div style="font-size:.68rem;color:var(--green);font-weight:600">💰 <?= htmlspecialchars($r['budget_range']) ?></div>
        <?php endif; ?>
        <?php if($isAdmin && $r['agent_name']): ?>
        <div style="font-size:.62rem;color:var(--gray-500);margin-top:.3rem;padding-top:.3rem;border-top:1px solid var(--gray-100)">👤 <?= htmlspecialchars($r['agent_name']) ?></div>
        <?php endif; ?>
        <?php if(!empty($r['next_follow_up']) && $r['next_follow_up'] <= date('Y-m-d')): ?>
        <div style="font-size:.6rem;background:#fef2f2;color:#dc2626;padding:.1rem .35rem;margin-top:.3rem;font-weight:700">⚠ Follow-up overdue</div>
        <?php endif; ?>
        <!-- Quick stage move -->
        <div style="margin-top:.5rem;display:flex;gap:.25rem;flex-wrap:wrap" onclick="event.stopPropagation()">
          <?php
          $stageIdx = array_search($stage, $stages);
          $prevStage = $stageIdx > 0 ? $stages[$stageIdx-1] : null;
          $nextStage = $stageIdx < count($stages)-1 ? $stages[$stageIdx+1] : null;
          if ($nextStage && $nextStage !== 'lost'):
          ?>
          <button onclick="moveStage(<?= $r['id'] ?>,'<?= $nextStage ?>')"
                  style="background:<?= $stageColors[$nextStage]['bg'] ?>;color:<?= $stageColors[$nextStage]['color'] ?>;border:1px solid <?= $stageColors[$nextStage]['color'] ?>30;padding:.15rem .45rem;font-size:.58rem;font-weight:700;cursor:pointer">
            → <?= ucfirst($nextStage) ?>
          </button>
          <?php endif; ?>
          <button onclick="moveStage(<?= $r['id'] ?>,'lost')"
                  style="background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;padding:.15rem .4rem;font-size:.58rem;cursor:pointer">
            Lost
          </button>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(empty($stageRows)): ?>
      <div style="text-align:center;padding:1.5rem .5rem;color:var(--gray-300);font-size:.72rem;font-style:italic;border:1.5px dashed var(--gray-200)">Empty</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
</div>

<?php else: ?>
<!-- ══ LIST VIEW ═══════════════════════════════════════════ -->
<!-- Stage filter pills -->
<div class="admin-filter-pills" style="margin-bottom:1rem">
  <a href="?view=list&q=<?= urlencode($search) ?>" class="admin-filter-pill <?= !$stageFilter?'active':'' ?>">All <?= count($rows) ?></a>
  <?php foreach($stages as $s):
    $cnt = count(array_filter($rows, fn($r)=>$r['pipeline_stage']===$s));
  ?>
  <a href="?view=list&stage=<?= $s ?>&q=<?= urlencode($search) ?>"
     class="admin-filter-pill <?= $stageFilter===$s?'active':'' ?>"
     style="<?= $stageFilter===$s?'background:'.$stageColors[$s]['bg'].';color:'.$stageColors[$s]['color'].';border-color:'.$stageColors[$s]['color'].'40':'' ?>">
    <?= $stageColors[$s]['label'] ?> <span style="opacity:.55"><?= $cnt ?></span>
  </a>
  <?php endforeach; ?>
</div>

<div class="admin-card">
<div class="admin-table-wrap">
<table class="admin-table">
  <thead><tr>
    <th>Lead</th><th>Contact</th><th>Package</th>
    <th>Budget</th><th>Travel</th>
    <?php if($isAdmin): ?><th>Agent</th><th>Commission</th><?php endif; ?>
    <th>Stage</th><th>Follow-up</th><th>Actions</th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
    $sc = $stageColors[$r['pipeline_stage']] ?? $stageColors['lead'];
    $overdue = !empty($r['next_follow_up']) && $r['next_follow_up'] <= date('Y-m-d')
               && !in_array($r['pipeline_stage'],['booked','completed','lost']);
    $commRate = (float)($r['commission_rate'] ?? $r['agent_rate'] ?? 10);
    $commAmt  = (float)($r['commission_amount'] ?? 0);
  ?>
  <tr style="<?= $overdue?'background:#fff8f8':'' ?>">
    <td>
      <div style="font-weight:700;font-size:.85rem"><?= htmlspecialchars($r['full_name']) ?></div>
      <div style="font-size:.68rem;color:var(--gray-500)"><?= htmlspecialchars($r['country']??'') ?> <?= $r['party_size']?'· '.$r['party_size'].'pax':'' ?></div>
      <?php if(!empty($r['booking_ref'])): ?>
      <div style="font-size:.65rem;color:var(--green);font-weight:600">Booking: <?= htmlspecialchars($r['booking_ref']) ?></div>
      <?php endif; ?>
    </td>
    <td style="font-size:.78rem">
      <a href="mailto:<?= htmlspecialchars($r['email']) ?>" style="color:inherit;display:block"><?= htmlspecialchars($r['email']) ?></a>
      <?php if($r['phone']): ?>
      <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',($r['phone']??'')) ?>" target="_blank" style="color:#16a34a;font-size:.72rem;font-weight:600"><?= htmlspecialchars($r['phone']) ?></a>
      <?php endif; ?>
    </td>
    <td style="font-size:.78rem"><?= htmlspecialchars($r['package_name']??'—') ?></td>
    <td style="font-size:.78rem">
      <?= htmlspecialchars($r['budget_range']??'—') ?>
      <?php if($r['booking_value']>0): ?>
      <div style="font-size:.68rem;color:var(--green);font-weight:600">$<?= number_format($r['booking_value'],0) ?> actual</div>
      <?php endif; ?>
    </td>
    <td style="font-size:.75rem"><?= htmlspecialchars($r['travel_month']??'—') ?></td>
    <?php if($isAdmin): ?>
    <td style="font-size:.75rem"><?= htmlspecialchars($r['agent_name']??'Unassigned') ?></td>
    <td style="font-size:.78rem">
      <div style="font-weight:600;color:<?= $commAmt>0?'var(--green)':'var(--gray-500)' ?>">
        $<?= number_format($commAmt,0) ?>
      </div>
      <div style="font-size:.65rem;color:var(--gray-500)"><?= $commRate ?>%</div>
    </td>
    <?php endif; ?>
    <td>
      <select onchange="moveStage(<?= $r['id'] ?>,this.value)"
              style="font-size:.65rem;padding:.25rem .4rem;border:1.5px solid <?= $sc['color'] ?>40;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-weight:700;cursor:pointer">
        <?php foreach($stages as $s): ?>
        <option value="<?= $s ?>" <?= $r['pipeline_stage']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td style="font-size:.75rem;<?= $overdue?'color:#dc2626;font-weight:700':'' ?>">
      <?= $overdue ? '⚠ ' : '' ?><?= $r['next_follow_up'] ? date('d M', strtotime($r['next_follow_up'])) : '—' ?>
    </td>
    <td style="white-space:nowrap">
      <button onclick="openEditLead(<?= $r['id'] ?>)" class="admin-btn admin-btn--outline" style="font-size:.6rem;padding:.22rem .45rem">Edit</button>
      <a href="mailto:<?= htmlspecialchars($r['email']) ?>" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.22rem .45rem">Email</a>
      <?php if($r['phone']): ?>
      <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',($r['phone']??'')) ?>?text=<?= urlencode('Hi '.$r['full_name'].', following up on your safari enquiry with Safari Zangu.') ?>"
         target="_blank" class="admin-btn admin-btn--success" style="font-size:.6rem;padding:.22rem .45rem">WA</a>
      <?php endif; ?>
      <?php if($isAdmin): ?>
      <button onclick="setBookingValue(<?= $r['id'] ?>,'<?= htmlspecialchars($r['full_name']) ?>',<?= $r['booking_value']??0 ?>)"
              class="admin-btn admin-btn--amber" style="font-size:.6rem;padding:.22rem .45rem">$ Set Value</button>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($rows)): ?>
  <tr><td colspan="<?= $isAdmin?10:8 ?>" class="admin-empty">
    <?= $isAdmin ? 'No leads yet.' : 'You have no leads yet. Add your first lead above.' ?>
  </td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?php endif; ?>

<!-- ══ ADD / EDIT LEAD MODAL ════════════════════════════════ -->
<div id="leadModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:999;align-items:flex-start;justify-content:center;padding:1.5rem;overflow-y:auto">
  <div style="background:#fff;width:100%;max-width:620px;margin:0 auto">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid #e5e7eb;background:#0a0a0a;color:#fff">
      <span id="leadModalTitle" style="font-size:.9rem;font-weight:600">Add Lead</span>
      <button onclick="closeLeadModal()" style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer">✕</button>
    </div>
    <form id="leadForm" style="padding:1.5rem">
      <input type="hidden" id="lead_id" value="0">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
        <div><label class="admin-label">Full Name *</label>
          <input id="f_name" class="admin-input" placeholder="Client full name" required></div>
        <div><label class="admin-label">Email *</label>
          <input id="f_email" type="email" class="admin-input" placeholder="client@email.com" required></div>
        <div><label class="admin-label">Phone / WhatsApp</label>
          <input id="f_phone" class="admin-input" placeholder="+44 7911 123456"></div>
        <div><label class="admin-label">Country</label>
          <input id="f_country" class="admin-input" placeholder="United Kingdom"></div>
        <div><label class="admin-label">Package Interest</label>
          <input id="f_package" class="admin-input" list="pkg-list-lead" placeholder="Classic Safari…"></div>
        <div><label class="admin-label">Budget Range</label>
          <input id="f_budget" class="admin-input" placeholder="$2,000–$3,000"></div>
        <div><label class="admin-label">Travel Month</label>
          <input id="f_travel" class="admin-input" placeholder="July 2026"></div>
        <div><label class="admin-label">Party Size</label>
          <input id="f_party" type="number" min="1" class="admin-input" value="2"></div>
        <div><label class="admin-label">Source</label>
          <select id="f_source" class="admin-input">
            <option value="referral">Referral</option>
            <option value="social_media">Social Media</option>
            <option value="website">Website</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="email">Email</option>
            <option value="event">Event / Exhibition</option>
            <option value="repeat">Repeat Client</option>
            <option value="other">Other</option>
          </select></div>
        <div><label class="admin-label">Pipeline Stage</label>
          <select id="f_stage" class="admin-input">
            <?php foreach($stages as $s): ?>
            <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select></div>
        <?php if($isAdmin): ?>
        <div><label class="admin-label">Assign to Agent</label>
          <select id="f_agent" class="admin-input">
            <option value="">— Unassigned —</option>
            <?php foreach($agents as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['full_name']?:$a['username']) ?> (<?= $a['commission_rate'] ?>%)</option>
            <?php endforeach; ?>
          </select></div>
        <?php endif; ?>
        <div><label class="admin-label">Next Follow-up</label>
          <input id="f_followup" type="date" class="admin-input"></div>
      </div>
      <div style="margin-bottom:1rem"><label class="admin-label">Destinations</label>
        <input id="f_destinations" class="admin-input" placeholder="Serengeti, Ngorongoro, Zanzibar"></div>
      <div style="margin-bottom:1.25rem"><label class="admin-label">Notes</label>
        <textarea id="f_notes" class="admin-input" rows="3" placeholder="Key details, requirements, follow-up notes…"></textarea></div>

      <div id="lead-error" style="display:none;background:#fef2f2;border-left:3px solid #dc2626;color:#991b1b;padding:.75rem 1rem;font-size:.82rem;margin-bottom:1rem"></div>

      <div style="display:flex;gap:.75rem;justify-content:flex-end">
        <button type="button" onclick="closeLeadModal()" class="admin-btn admin-btn--muted">Cancel</button>
        <?php if($isAdmin): ?>
        <button type="button" id="deleteLeadBtn" onclick="deleteLead()" class="admin-btn admin-btn--danger" style="display:none">Delete Lead</button>
        <?php endif; ?>
        <button type="submit" id="saveLeadBtn" class="admin-btn admin-btn--primary">Save Lead</button>
      </div>
    </form>
  </div>
</div>

<!-- Set booking value modal (admin only) -->
<?php if($isAdmin): ?>
<div id="valueModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:999;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;width:100%;max-width:400px;border-radius:2px">
    <div style="padding:1rem 1.5rem;border-bottom:1px solid #e5e7eb;font-weight:600" id="valueModalTitle">Set Booking Value</div>
    <div style="padding:1.5rem">
      <input type="hidden" id="value_lead_id">
      <div style="margin-bottom:1rem">
        <label class="admin-label">Actual Booking Value (USD)</label>
        <input id="value_amount" type="number" step="1" class="admin-input" placeholder="e.g. 3500">
      </div>
      <div style="margin-bottom:1.25rem">
        <label class="admin-label">Booking Reference (optional)</label>
        <input id="value_ref" class="admin-input" placeholder="SZV-B...">
      </div>
      <div id="value_preview" style="background:#f0fdf4;border:1px solid #86efac;padding:.75rem;font-size:.82rem;margin-bottom:1rem;display:none">
        Commission: <strong id="value_comm_display">$0</strong>
      </div>
      <div style="display:flex;gap:.75rem;justify-content:flex-end">
        <button onclick="document.getElementById('valueModal').style.display='none'" class="admin-btn admin-btn--muted">Cancel</button>
        <button onclick="saveBookingValue()" class="admin-btn admin-btn--primary">Save Value</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Packages datalist -->
<datalist id="pkg-list-lead">
  <?php foreach($packages as $p): ?>
  <option value="<?= htmlspecialchars($p['name']) ?>">
  <?php endforeach; ?>
</datalist>

<div id="toast-leads" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none"></div>

<script>
var BASE = window.BASE_PATH || '';
var isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

// ── Toast ──────────────────────────────────────────────────
function toast(msg, ok) {
  var t=document.createElement('div');
  t.style.cssText='background:'+(ok===false?'#dc2626':'#16a34a')+';color:#fff;padding:.65rem 1.1rem;font-size:.78rem;min-width:200px;pointer-events:auto;box-shadow:0 4px 16px rgba(0,0,0,.2)';
  t.textContent=msg;
  document.getElementById('toast-leads').appendChild(t);
  setTimeout(function(){t.remove();},3000);
}

// ── Move stage ─────────────────────────────────────────────
async function moveStage(id, stage) {
  var fd=new FormData();
  fd.append('action','update_stage'); fd.append('id',id); fd.append('stage',stage);
  try {
    var r=await fetch(BASE+'/api/leads.php',{method:'POST',body:fd,credentials:'same-origin'});
    var d=await r.json();
    if(d.success){ toast('Moved to '+stage); setTimeout(function(){window.location.reload();},800); }
    else toast(d.message||'Failed',false);
  } catch(e){ toast('Network error',false); }
}

// ── Add lead modal ─────────────────────────────────────────
function openAddLead() {
  document.getElementById('leadModalTitle').textContent='Add New Lead';
  document.getElementById('lead_id').value='0';
  document.getElementById('leadForm').reset();
  if(document.getElementById('deleteLeadBtn')) document.getElementById('deleteLeadBtn').style.display='none';
  document.getElementById('lead-error').style.display='none';
  document.getElementById('leadModal').style.display='flex';
}

// ── Edit lead modal ─────────────────────────────────────────
async function openEditLead(id) {
  document.getElementById('lead-error').style.display='none';
  try {
    var r=await fetch(BASE+'/api/leads.php?action=get_lead&id='+id,{credentials:'same-origin'});
    var d=await r.json();
    if(!d.success){toast(d.message||'Error',false);return;}
    var l=d.data;
    document.getElementById('leadModalTitle').textContent='Edit Lead — '+l.full_name;
    document.getElementById('lead_id').value=l.id;
    document.getElementById('f_name').value=l.full_name||'';
    document.getElementById('f_email').value=l.email||'';
    document.getElementById('f_phone').value=l.phone||'';
    document.getElementById('f_country').value=l.country||'';
    document.getElementById('f_package').value=l.package_name||'';
    document.getElementById('f_budget').value=l.budget_range||'';
    document.getElementById('f_travel').value=l.travel_month||'';
    document.getElementById('f_party').value=l.party_size||2;
    document.getElementById('f_source').value=l.source||'referral';
    document.getElementById('f_stage').value=l.pipeline_stage||'lead';
    document.getElementById('f_followup').value=l.next_follow_up||'';
    document.getElementById('f_destinations').value=l.destinations||'';
    document.getElementById('f_notes').value=l.notes||'';
    if(isAdmin){
      var agentSel=document.getElementById('f_agent');
      if(agentSel) agentSel.value=l.agent_id||'';
      var delBtn=document.getElementById('deleteLeadBtn');
      if(delBtn) delBtn.style.display='inline-flex';
    }
    document.getElementById('leadModal').style.display='flex';
  } catch(e){toast('Error loading lead',false);}
}

function closeLeadModal() { document.getElementById('leadModal').style.display='none'; }
document.getElementById('leadModal').addEventListener('click',function(e){if(e.target===this)closeLeadModal();});

// ── Save lead ──────────────────────────────────────────────
document.getElementById('leadForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  var errEl=document.getElementById('lead-error');
  errEl.style.display='none';
  var btn=document.getElementById('saveLeadBtn');
  btn.disabled=true; btn.textContent='Saving…';

  var fd=new FormData();
  fd.append('action','save_lead');
  fd.append('id',document.getElementById('lead_id').value);
  fd.append('full_name',document.getElementById('f_name').value);
  fd.append('email',document.getElementById('f_email').value);
  fd.append('phone',document.getElementById('f_phone').value);
  fd.append('country',document.getElementById('f_country').value);
  fd.append('package_name',document.getElementById('f_package').value);
  fd.append('budget_range',document.getElementById('f_budget').value);
  fd.append('travel_month',document.getElementById('f_travel').value);
  fd.append('party_size',document.getElementById('f_party').value);
  fd.append('source',document.getElementById('f_source').value);
  fd.append('pipeline_stage',document.getElementById('f_stage').value);
  fd.append('next_follow_up',document.getElementById('f_followup').value);
  fd.append('destinations',document.getElementById('f_destinations').value);
  fd.append('notes',document.getElementById('f_notes').value);
  if(isAdmin && document.getElementById('f_agent')) fd.append('agent_id',document.getElementById('f_agent').value);

  try {
    var r=await fetch(BASE+'/api/leads.php',{method:'POST',body:fd,credentials:'same-origin'});
    var d=await r.json();
    if(d.success){ closeLeadModal(); toast(d.message); setTimeout(function(){window.location.reload();},800); }
    else { errEl.textContent=d.message||'Save failed'; errEl.style.display='block'; }
  } catch(err){ errEl.textContent='Network error: '+err.message; errEl.style.display='block'; }
  btn.disabled=false; btn.textContent='Save Lead';
});

// ── Delete lead ────────────────────────────────────────────
async function deleteLead() {
  if(!confirm('Delete this lead? This cannot be undone.')) return;
  var id=document.getElementById('lead_id').value;
  var fd=new FormData(); fd.append('action','delete_lead'); fd.append('id',id);
  var r=await fetch(BASE+'/api/leads.php',{method:'POST',body:fd,credentials:'same-origin'});
  var d=await r.json();
  if(d.success){ closeLeadModal(); toast('Lead deleted'); setTimeout(function(){window.location.reload();},800); }
  else toast(d.message||'Delete failed',false);
}

<?php if($isAdmin): ?>
// ── Set booking value ──────────────────────────────────────
function setBookingValue(id, name, currentVal) {
  document.getElementById('value_lead_id').value=id;
  document.getElementById('valueModalTitle').textContent='Set Booking Value — '+name;
  document.getElementById('value_amount').value=currentVal||'';
  document.getElementById('value_ref').value='';
  document.getElementById('value_preview').style.display='none';
  document.getElementById('valueModal').style.display='flex';
}
document.getElementById('value_amount').addEventListener('input', function() {
  var val=parseFloat(this.value)||0;
  if(val>0){
    var comm=Math.round(val*0.10);
    document.getElementById('value_comm_display').textContent='$'+comm.toLocaleString()+' (10% est.)';
    document.getElementById('value_preview').style.display='block';
  }
});
async function saveBookingValue() {
  var fd=new FormData();
  fd.append('action','set_booking_value');
  fd.append('id',document.getElementById('value_lead_id').value);
  fd.append('value',document.getElementById('value_amount').value);
  fd.append('booking_ref',document.getElementById('value_ref').value);
  var r=await fetch(BASE+'/api/leads.php',{method:'POST',body:fd,credentials:'same-origin'});
  var d=await r.json();
  if(d.success){
    document.getElementById('valueModal').style.display='none';
    toast('Booking value set. Commission: $'+Math.round(d.commission).toLocaleString());
    setTimeout(function(){window.location.reload();},1000);
  } else toast(d.message||'Failed',false);
}
document.getElementById('valueModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none';});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
