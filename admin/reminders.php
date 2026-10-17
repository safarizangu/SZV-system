<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'reminders.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();

// Cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try { $db->prepare("UPDATE booking_reminders SET status='cancelled' WHERE id=?")->execute([(int)$_POST['cancel_id']]); }
    catch(\Exception $e) {}
    header('Location: reminders.php?msg=Reminder+cancelled'); exit;
}
// Add custom
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reminder'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        $db->prepare("INSERT INTO booking_reminders (booking_ref,type,send_at,subject,body) VALUES (?,?,?,?,?)")
           ->execute([
               strtoupper(trim($_POST['booking_ref'])),
               $_POST['type'] ?? 'custom',
               $_POST['send_at'],
               trim($_POST['subject'] ?? ''),
               trim($_POST['body'] ?? ''),
           ]);
    } catch(\Exception $e) { error_log($e->getMessage()); }
    header('Location: reminders.php?msg=Reminder+scheduled'); exit;
}
// Re-send failed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_id'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try { $db->prepare("UPDATE booking_reminders SET status='pending', sent_at=NULL WHERE id=?")->execute([(int)$_POST['resend_id']]); }
    catch(\Exception $e) {}
    header('Location: reminders.php?msg=Queued+for+resend'); exit;
}

// Data
$pending=[]; $sent=[]; $stats=[];
try {
    $pending = $db->query(
        "SELECT r.*, b.full_name, b.email, b.travel_date
         FROM booking_reminders r
         LEFT JOIN bookings b ON b.booking_ref = r.booking_ref COLLATE utf8mb4_unicode_ci
         WHERE r.status='pending'
         ORDER BY r.send_at ASC LIMIT 50"
    )->fetchAll();

    $sent = $db->query(
        "SELECT r.*, b.full_name
         FROM booking_reminders r
         LEFT JOIN bookings b ON b.booking_ref = r.booking_ref COLLATE utf8mb4_unicode_ci
         WHERE r.status IN ('sent','failed','cancelled')
         ORDER BY COALESCE(r.sent_at, r.created_at) DESC LIMIT 30"
    )->fetchAll();

    $statRows = $db->query("SELECT status,COUNT(*) c FROM booking_reminders GROUP BY status")->fetchAll();
    foreach($statRows as $r) $stats[$r['status']]=(int)$r['c'];
} catch(\Exception $e){ error_log($e->getMessage()); }

// Due in next 24h
$due24=0;
try {
    $due24 = (int)$db->query(
        "SELECT COUNT(*) FROM booking_reminders WHERE status='pending' AND send_at<=DATE_ADD(NOW(),INTERVAL 24 HOUR)"
    )->fetchColumn();
} catch(\Exception $e){}

// Read reminders log
$logLines=[];
$logFile='/var/log/szv_reminders.log';
if(file_exists($logFile)){
    $l=array_filter(explode("\n",shell_exec("tail -15 ".escapeshellarg($logFile))??""));
    $logLines=array_reverse(array_values($l));
}

// Cron status
$cronActive = false;
$cronOutput = shell_exec('crontab -u www-data -l 2>/dev/null') ?? '';
$cronActive = str_contains($cronOutput, 'process_reminders.php');

$typeLabels = [
    'deposit_due' => 'Deposit Due',
    'balance_due' => 'Balance Due',
    'departure'   => 'Departure',
    'post_trip'   => 'Post-Trip',
    'custom'      => 'Custom',
];
$typeColors = [
    'deposit_due' => 'amber',
    'balance_due' => 'blue',
    'departure'   => 'green',
    'post_trip'   => 'purple',
    'custom'      => 'gray',
];

$pageTitle = 'Reminders';
$msg = htmlspecialchars($_GET['msg'] ?? '');
ob_start();
?>
<?php if($msg): ?><div class="admin-alert admin-alert--success"><?= $msg ?></div><?php endif; ?>

<!-- Stats + Cron Status -->
<div class="admin-split-layout" style="display:grid;grid-template-columns:repeat(5,1fr) auto;gap:1rem;margin-bottom:1.5rem;align-items:stretch">
  <?php foreach([
    ['Pending',   $stats['pending']??0,   'amber'],
    ['Sent',      $stats['sent']??0,      'green'],
    ['Failed',    $stats['failed']??0,    'red'],
    ['Cancelled', $stats['cancelled']??0, ''],
    ['Due 24h',   $due24,                 'blue'],
  ] as [$lbl,$val,$tint]): ?>
  <div class="metric-card<?= $tint ? ' metric-card--'.$tint : '' ?>">
    <span class="metric-label"><?= $lbl ?></span>
    <span class="metric-value"><?= $val ?></span>
  </div>
  <?php endforeach; ?>
  <div class="cron-pill <?= $cronActive?'cron-pill--active':'cron-pill--inactive' ?>" style="flex-direction:column;align-items:flex-start;gap:.3rem;height:auto">
    <span>Cron</span>
    <span style="font-size:.8rem"><?= $cronActive ? 'Active' : 'Not Set' ?></span>
  </div>
</div>

<div class="admin-split-layout" style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start">
<div>

<!-- PENDING TABLE -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header">
    <h3>Pending <span style="font-size:.72rem;color:var(--gray-500)">· <?= count($pending) ?> scheduled</span></h3>
    <div style="display:flex;gap:.5rem">
      <button type="button" onclick="openFormModal()" class="admin-btn admin-btn--primary" style="font-size:.68rem">+ Schedule Reminder</button>
      <a href="email_queue.php" class="admin-btn admin-btn--outline" style="font-size:.68rem">View Email Queue →</a>
    </div>
  </div>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr>
      <th>Ref</th><th>Guest</th><th>Travel Date</th>
      <th>Type</th><th>Send At</th><th>Subject</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach($pending as $r):
      $badge = $typeColors[$r['type']] ?? 'gray';
      $isDue = strtotime($r['send_at']) <= time();
    ?>
    <tr class="<?= $isDue?'highlighted-row':'' ?>">
      <td><code><?= htmlspecialchars($r['booking_ref']) ?></code></td>
      <td>
        <div style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($r['full_name']??'—') ?></div>
        <div style="font-size:.68rem;color:var(--gray-500)"><?= htmlspecialchars($r['email']??'') ?></div>
      </td>
      <td style="font-size:.78rem;white-space:nowrap"><?= $r['travel_date'] ? date('d M Y',strtotime($r['travel_date'])) : '—' ?></td>
      <td><span class="badge-status badge-status--<?= $badge ?>"><?= $typeLabels[$r['type']]??$r['type'] ?></span>
        <?php if($isDue): ?><span style="font-size:.62rem;color:var(--red);font-weight:700;margin-left:.25rem">DUE</span><?php endif; ?>
      </td>
      <td style="white-space:nowrap;font-size:.78rem"><?= date('d M Y H:i',strtotime($r['send_at'])) ?></td>
      <td style="font-size:.75rem;color:var(--gray-500);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['subject']??'Auto') ?></td>
      <td>
        <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this reminder?')">
          <?= \App\Middleware\CsrfMiddleware::field() ?>
          <input type="hidden" name="cancel_id" value="<?= $r['id'] ?>">
          <button class="admin-btn admin-btn--danger" style="font-size:.62rem;padding:.25rem .45rem">Cancel</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($pending)): ?>
    <tr><td colspan="7" class="admin-empty">No pending reminders.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- HISTORY TABLE -->
<div class="admin-card">
  <div class="admin-card-header"><h3>History <span style="font-size:.72rem;color:var(--gray-500)">· last 30</span></h3></div>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Ref</th><th>Guest</th><th>Type</th><th>Sent At</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php
    $historyStatusBadge = ['sent'=>'green','failed'=>'red','cancelled'=>'gray'];
    foreach($sent as $r):
      $badge = $typeColors[$r['type']] ?? 'gray';
    ?>
    <tr>
      <td><code><?= htmlspecialchars($r['booking_ref']) ?></code></td>
      <td style="font-size:.82rem;font-weight:600"><?= htmlspecialchars($r['full_name']??'—') ?></td>
      <td><span class="badge-status badge-status--<?= $badge ?>"><?= $typeLabels[$r['type']]??$r['type'] ?></span></td>
      <td style="white-space:nowrap;font-size:.75rem"><?= $r['sent_at'] ? date('d M H:i',strtotime($r['sent_at'])) : '—' ?></td>
      <td><span class="badge-status badge-status--<?= $historyStatusBadge[$r['status']]??'gray' ?>"><?= $r['status'] ?></span></td>
      <td>
        <?php if($r['status']==='failed'): ?>
        <form method="POST" style="display:inline">
          <?= \App\Middleware\CsrfMiddleware::field() ?>
          <input type="hidden" name="resend_id" value="<?= $r['id'] ?>">
          <button class="admin-btn admin-btn--outline" style="font-size:.62rem;padding:.25rem .45rem">Retry</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($sent)): ?><tr><td colspan="6" class="admin-empty">No history yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

</div><!-- /left -->

<!-- RIGHT: cron info + log -->
<div style="position:sticky;top:80px">

  <!-- Cron job info -->
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-header"><h3>Cron Jobs</h3></div>
    <div class="admin-card-body" style="padding:.75rem 1rem">
      <div style="margin-bottom:1rem">
        <div style="font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--gray-500);margin-bottom:.4rem">Email Queue</div>
        <code style="font-size:.68rem;display:block;padding:.5rem .65rem;word-break:break-all">*/2 * * * * /usr/bin/php8.3 <?= BASE_PATH ?>/api/cron/process_email_queue.php</code>
        <div style="font-size:.68rem;color:var(--gray-500);margin-top:.2rem">Runs every 2 minutes</div>
      </div>
      <div>
        <div style="font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--gray-500);margin-bottom:.4rem">Reminders</div>
        <code style="font-size:.68rem;display:block;padding:.5rem .65rem;word-break:break-all">0 * * * * /usr/bin/php8.3 <?= BASE_PATH ?>/api/cron/process_reminders.php</code>
        <div style="font-size:.68rem;color:var(--gray-500);margin-top:.2rem">Runs every hour</div>
      </div>
      <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--gray-100)">
        <div style="font-size:.68rem;color:var(--gray-500)">Logs:</div>
        <div style="font-size:.68rem;margin-top:.25rem"><code>/var/log/szv_email.log</code></div>
        <div style="font-size:.68rem"><code>/var/log/szv_reminders.log</code></div>
      </div>
      <a href="email_queue.php" class="admin-btn admin-btn--outline" style="width:100%;justify-content:center;margin-top:.85rem;font-size:.72rem">
        View Email Queue →
      </a>
    </div>
  </div>

  <!-- Reminder type reference -->
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-header"><h3>Reminder Types</h3></div>
    <div class="admin-card-body" style="padding:0">
      <?php foreach([
        ['deposit_due','Deposit Due','48h after booking'],
        ['balance_due','Balance Due','45 days before travel'],
        ['departure',  'Departure', '7 days before travel'],
        ['post_trip',  'Post-Trip', '3 days after return'],
        ['custom',     'Custom',    'Any time you set'],
      ] as [$key,$lbl,$when]):
        $badge = $typeColors[$key];
      ?>
      <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem 1rem;border-bottom:1px solid var(--gray-100)">
        <span class="badge-status badge-status--<?= $badge ?>" style="white-space:nowrap"><?= $lbl ?></span>
        <span style="font-size:.72rem;color:var(--gray-500)"><?= $when ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Live log -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3>Reminders Log</h3>
      <button onclick="refreshReminderLog()" class="admin-btn admin-btn--outline" style="font-size:.62rem;padding:.3rem .5rem"><?= \App\Helpers\AdminIcon::render('refresh', 12) ?></button>
    </div>
    <div id="rem-log" class="admin-log-terminal" style="font-size:.68rem;padding:.85rem;max-height:240px">
      <?php if(empty($logLines)): ?>
      <span style="color:var(--gray-500)">No log yet. Runs every hour.</span>
      <?php else: ?>
      <?php foreach($logLines as $line): ?>
      <div><?= htmlspecialchars($line) ?></div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /right -->
</div><!-- /grid -->

<!-- Schedule Reminder modal -->
<div id="formModal" class="admin-modal-overlay">
  <div class="admin-modal admin-modal--wide">
    <div class="admin-modal-header">
      <span class="admin-modal-title">Schedule a Reminder</span>
      <button type="button" onclick="closeFormModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="add_reminder" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div>
            <label class="admin-label">Booking Ref *</label>
            <input name="booking_ref" class="admin-input" placeholder="SZV2026…" required>
          </div>
          <div>
            <label class="admin-label">Type</label>
            <select name="type" class="admin-input">
              <?php foreach($typeLabels as $v=>$l): ?>
              <option value="<?= $v ?>"><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="admin-label">Send At *</label>
            <input name="send_at" type="datetime-local" class="admin-input" required
                   min="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div>
            <label class="admin-label">Subject</label>
            <input name="subject" class="admin-input" placeholder="Leave blank for default…">
          </div>
        </div>
        <div style="margin-bottom:.85rem">
          <label class="admin-label">Custom Body (optional — leave blank for template)</label>
          <textarea name="body" class="admin-input" rows="3" placeholder="Custom email body…"></textarea>
        </div>
        <div style="display:flex;gap:.5rem">
          <button type="submit" class="admin-btn admin-btn--primary">Schedule Reminder</button>
          <button type="button" onclick="closeFormModal()" class="admin-btn admin-btn--outline">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function refreshReminderLog() {
    fetch(window.location.href+'?_r='+Date.now())
        .then(r=>r.text()).then(html=>{
            var tmp=document.createElement('div'); tmp.innerHTML=html;
            var nl=tmp.querySelector('#rem-log');
            if(nl) document.getElementById('rem-log').innerHTML=nl.innerHTML;
        });
}
setInterval(refreshReminderLog, 60000);

function openFormModal() { document.getElementById('formModal').classList.add('open'); }
function closeFormModal() { document.getElementById('formModal').classList.remove('open'); }
document.getElementById('formModal').addEventListener('click', function(e){ if (e.target === this) closeFormModal(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeFormModal(); });
<?php if (isset($_GET['new'])): ?>
document.addEventListener('DOMContentLoaded', openFormModal);
<?php endif; ?>
</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
