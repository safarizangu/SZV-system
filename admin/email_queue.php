<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'email_queue.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'retry') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $db->prepare("UPDATE email_queue SET status='pending', attempts=0, last_error=NULL WHERE id=?")
               ->execute([$id]);
        } catch(\Exception $e) {}
        header('Location: email_queue.php?msg=Queued+for+retry'); exit;
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try { $db->prepare("DELETE FROM email_queue WHERE id=?")->execute([$id]); }
        catch(\Exception $e) {}
        header('Location: email_queue.php?msg=Deleted'); exit;
    }
    if ($action === 'retry_all_failed') {
        try {
            $db->exec("UPDATE email_queue SET status='pending', attempts=0, last_error=NULL WHERE status='failed'");
        } catch(\Exception $e) {}
        header('Location: email_queue.php?msg=All+failed+emails+queued+for+retry'); exit;
    }
    if ($action === 'purge_sent') {
        try {
            $db->exec("DELETE FROM email_queue WHERE status='sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        } catch(\Exception $e) {}
        header('Location: email_queue.php?msg=Old+sent+emails+purged'); exit;
    }
}

// Filters
$statusF = trim($_GET['status'] ?? '');
$search  = trim($_GET['search'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page-1)*$perPage;

$where=[]; $params=[];
if ($statusF) { $where[]="status=?"; $params[]=$statusF; }
if ($search)  { $where[]="(to_email LIKE ? OR subject LIKE ?)"; $like="%$search%"; $params=array_merge($params,[$like,$like]); }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

// Stats
$stats = ['pending'=>0,'sent'=>0,'failed'=>0,'total'=>0];
try {
    $rows = $db->query("SELECT status,COUNT(*) c FROM email_queue GROUP BY status")->fetchAll();
    foreach($rows as $r) { $stats[$r['status']]=(int)$r['c']; $stats['total']+=(int)$r['c']; }
} catch(\Exception $e){}

// Data
$emails=[]; $totalRows=0; $totalPages=0;
try {
    $cntStmt=$db->prepare("SELECT COUNT(*) FROM email_queue $whereSQL"); $cntStmt->execute($params);
    $totalRows=(int)$cntStmt->fetchColumn();
    $totalPages=(int)ceil($totalRows/$perPage);
    $stmt=$db->prepare("SELECT * FROM email_queue $whereSQL ORDER BY id DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $emails=$stmt->fetchAll();
} catch(\Exception $e){ error_log($e->getMessage()); }

// Read last 20 lines of email log
$logLines=[];
$logFile='/var/log/szv_email.log';
if(file_exists($logFile)){
    $lines=array_filter(explode("\n",shell_exec("tail -20 ".escapeshellarg($logFile))??""));
    $logLines=array_reverse(array_values($lines));
}

$pageTitle='Email Queue';
$msg = htmlspecialchars($_GET['msg'] ?? '');
ob_start();
?>
<?php if($msg): ?><div class="admin-alert admin-alert--success"><?= $msg ?></div><?php endif; ?>

<!-- Toolbar: status filter + search, one row -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;align-items:center">
  <form method="GET" style="display:flex;gap:.5rem;flex:1;min-width:200px;align-items:center">
    <?php
    $statusOpts = [
      ['',        'Total',   $stats['total'],   ''],
      ['pending', 'Pending', $stats['pending'], 'amber'],
      ['sent',    'Sent',    $stats['sent'],    'green'],
      ['failed',  'Failed',  $stats['failed'],  'red'],
    ];
    $tintColors = ['amber'=>['bg'=>'var(--amber-light)','color'=>'#92400e'], 'green'=>['bg'=>'var(--green-light)','color'=>'#166534'], 'red'=>['bg'=>'var(--red-light)','color'=>'#991b1b']];
    $curTint = null;
    foreach ($statusOpts as $o) { if ($o[0]===$statusF) $curTint = $o[3]; }
    ?>
    <select name="status" class="admin-input" onchange="this.form.submit()"
            style="max-width:160px;font-weight:700;<?= $curTint && isset($tintColors[$curTint]) ? 'background:'.$tintColors[$curTint]['bg'].';color:'.$tintColors[$curTint]['color'].';' : '' ?>">
      <?php foreach ($statusOpts as [$val,$label,$count,$tint]): ?>
      <option value="<?= $val ?>" <?= $statusF===$val?'selected':'' ?>><?= $label ?> (<?= $count ?>)</option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search email or subject…"
           class="admin-input" style="flex:1">
    <button class="admin-btn admin-btn--outline" style="font-size:.72rem">Search</button>
    <?php if($search||$statusF): ?><a href="email_queue.php" class="admin-btn admin-btn--muted" style="font-size:.72rem">Clear</a><?php endif; ?>
  </form>
  <?php if($stats['failed']>0): ?>
  <form method="POST" onsubmit="return confirmSubmitGuard(event, this, 'Retry all <?= $stats['failed'] ?> failed emails?')">
    <?= \App\Middleware\CsrfMiddleware::field() ?>
    <input type="hidden" name="action" value="retry_all_failed">
    <button class="admin-btn admin-btn--primary" style="font-size:.72rem">Retry All Failed (<?= $stats['failed'] ?>)</button>
  </form>
  <?php endif; ?>
  <form method="POST" onsubmit="return confirmSubmitGuard(event, this, 'Delete sent emails older than 30 days?', {danger:true})">
    <?= \App\Middleware\CsrfMiddleware::field() ?>
    <input type="hidden" name="action" value="purge_sent">
    <button class="admin-btn admin-btn--muted" style="font-size:.72rem">Purge Old Sent</button>
  </form>
</div>

<!-- Table -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header">
    <h3>Email Queue <?php if($statusF): ?><span style="font-size:.72rem;color:var(--gray-500)">· <?= ucfirst($statusF) ?></span><?php endif; ?></h3>
    <span style="font-size:.75rem;color:var(--gray-500)"><?= number_format($totalRows) ?> email<?= $totalRows!=1?'s':'' ?></span>
  </div>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr>
      <th>ID</th><th>To</th><th>Subject</th><th>Status</th>
      <th style="text-align:center">Attempts</th><th>Created</th><th>Sent At</th><th>Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach($emails as $e):
      $badge = ['pending'=>'amber','sent'=>'green','failed'=>'red'][$e['status']] ?? 'gray';
    ?>
    <tr>
      <td style="font-size:.75rem;color:var(--gray-500)">#<?= $e['id'] ?></td>
      <td>
        <div style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($e['to_name']??'') ?></div>
        <div style="font-size:.72rem;color:var(--gray-500)"><?= htmlspecialchars($e['to_email']) ?></div>
      </td>
      <td style="font-size:.82rem;max-width:220px">
        <?= htmlspecialchars(mb_substr($e['subject'],0,55)) ?>
        <?php if(!empty($e['last_error'])): ?>
        <div style="font-size:.68rem;color:var(--red);margin-top:.2rem;display:flex;align-items:center;gap:.25rem" title="<?= htmlspecialchars($e['last_error']) ?>">
          <?= \App\Helpers\AdminIcon::render('alert', 12) ?> <?= htmlspecialchars(mb_substr($e['last_error'],0,60)) ?>
        </div>
        <?php endif; ?>
      </td>
      <td><span class="badge-status badge-status--<?= $badge ?>"><?= $e['status'] ?></span></td>
      <td style="text-align:center;font-size:.82rem"><?= $e['attempts'] ?>/3</td>
      <td style="font-size:.72rem;white-space:nowrap;color:var(--gray-500)"><?= date('d M H:i',strtotime($e['created_at'])) ?></td>
      <td style="font-size:.72rem;white-space:nowrap"><?= $e['sent_at'] ? date('d M H:i',strtotime($e['sent_at'])) : '—' ?></td>
      <td style="white-space:nowrap">
        <div class="admin-row-actions">
          <button type="button" class="admin-icon-btn admin-row-actions-toggle" aria-label="Actions" aria-haspopup="true" aria-expanded="false"><?= \App\Helpers\AdminIcon::render('dots-vertical', 16) ?></button>
          <div class="admin-row-actions-menu">
            <button type="button" class="admin-row-actions-item" onclick="viewEmail(<?= $e['id'] ?>, this.closest('tr'))"><?= \App\Helpers\AdminIcon::render('eye', 14) ?> View</button>
            <?php if($e['status']==='failed' || ($e['status']==='pending' && $e['attempts']>0)): ?>
            <form method="POST">
              <?= \App\Middleware\CsrfMiddleware::field() ?>
              <input type="hidden" name="action" value="retry">
              <input type="hidden" name="id" value="<?= $e['id'] ?>">
              <button type="submit" class="admin-row-actions-item"><?= \App\Helpers\AdminIcon::render('refresh', 14) ?> Retry</button>
            </form>
            <?php endif; ?>
            <form method="POST" onsubmit="return confirmSubmitGuard(event, this, 'Delete this email from the queue?', {danger:true})">
              <?= \App\Middleware\CsrfMiddleware::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $e['id'] ?>">
              <button type="submit" class="admin-row-actions-item admin-row-actions-item--danger"><?= \App\Helpers\AdminIcon::render('trash', 14) ?> Delete</button>
            </form>
          </div>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($emails)): ?>
    <tr><td colspan="8" class="admin-empty">No emails found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php if($totalPages>1): ?>
  <div class="pagination">
    <div class="page-btns">
    <?php for($i=1;$i<=$totalPages;$i++): ?>
    <a href="?page=<?= $i ?>&status=<?= urlencode($statusF) ?>&search=<?= urlencode($search) ?>"
       class="page-btn<?= $i===$page?' active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Cron Live Log -->
<div class="admin-card">
  <div class="admin-card-header">
    <h3>Live Email Log <span style="font-size:.72rem;color:var(--gray-500)">— /var/log/szv_email.log (last 20 lines)</span></h3>
    <button onclick="refreshLog()" class="admin-btn admin-btn--outline" style="font-size:.68rem"><?= \App\Helpers\AdminIcon::render('refresh', 12) ?> Refresh</button>
  </div>
  <div class="admin-card-body" style="padding:0">
    <div id="log-output" class="admin-log-terminal">
      <?php if(empty($logLines)): ?>
      <span style="color:var(--gray-500)">No log entries yet. Cron runs every 2 minutes.</span>
      <?php else: ?>
      <?php foreach($logLines as $line): ?>
      <div><?= htmlspecialchars($line) ?></div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Cron status bar -->
<div style="margin-top:1rem;display:flex;align-items:center;gap:1rem;font-size:.75rem;color:var(--gray-500);flex-wrap:wrap">
  <span>Cron: email queue every <strong>2 min</strong> · reminders every <strong>1 hour</strong></span>
  <span>Logs: <code>/var/log/szv_email.log</code> · <code>/var/log/szv_reminders.log</code></span>
  <a href="reminders.php" style="color:var(--black);font-weight:600;font-size:.72rem">View Reminders →</a>
</div>

<!-- Email Preview Modal -->
<div id="emailModal" class="admin-modal-overlay">
  <div class="admin-modal admin-modal--wide" style="display:flex;flex-direction:column;max-height:90vh">
    <div class="admin-modal-header" style="flex-shrink:0">
      <span class="admin-modal-title" id="modal-subject">Email Preview</span>
      <button onclick="closeEmailModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div style="padding:.75rem 1.5rem;background:var(--gray-50);font-size:.78rem;color:var(--gray-500);flex-shrink:0" id="modal-meta"></div>
    <div style="flex:1;overflow-y:auto" id="modal-body"></div>
  </div>
</div>

<script>
// Pre-load email bodies for preview
var emailData = <?= json_encode(array_column($emails, null, 'id')) ?>;

function viewEmail(id, row) {
    var e = emailData[id];
    if (!e) return;
    document.getElementById('modal-subject').textContent = e.subject;
    document.getElementById('modal-meta').innerHTML =
        'To: <strong>'+e.to_name+' &lt;'+e.to_email+'&gt;</strong> · '+
        'Status: <strong>'+e.status+'</strong> · Attempts: '+e.attempts+'/3 · '+
        'Created: '+e.created_at;
    var iframe = document.createElement('iframe');
    iframe.style.cssText = 'width:100%;height:420px;border:none;';
    var modalBody = document.getElementById('modal-body');
    modalBody.innerHTML = '';
    modalBody.appendChild(iframe);
    iframe.srcdoc = e.body || '<p style="padding:2rem;color:#9ca3af">No HTML body.</p>';
    document.getElementById('emailModal').classList.add('open');
}
function closeEmailModal() { document.getElementById('emailModal').classList.remove('open'); }
document.getElementById('emailModal').addEventListener('click', function(ev) {
    if (ev.target === this) closeEmailModal();
});
document.addEventListener('keydown', function(ev) { if (ev.key === 'Escape') closeEmailModal(); });

function refreshLog() {
    fetch(window.location.href + (window.location.href.includes('?') ? '&' : '?') + '_r='+Date.now())
        .then(r => r.text()).then(html => {
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            var newLog = tmp.querySelector('#log-output');
            if (newLog) document.getElementById('log-output').innerHTML = newLog.innerHTML;
        });
}

// Auto-refresh log every 30 seconds
setInterval(refreshLog, 30000);
</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
