<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $totalRows=(int)$db->prepare("SELECT COUNT(*) FROM email_queue $whereSQL")->execute($params) ? $db->prepare("SELECT COUNT(*) FROM email_queue $whereSQL")->execute($params) : 0;
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

<!-- Stats bar -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
  <?php foreach([
    ['Total',   $stats['total'],   '#0a0a0a','#fff'],
    ['Pending', $stats['pending'], '#fef3e2','#92400e'],
    ['Sent',    $stats['sent'],    '#f0fdf4','#166534'],
    ['Failed',  $stats['failed'],  '#fef2f2','#991b1b'],
  ] as [$label,$val,$bg,$color]): ?>
  <a href="?status=<?= strtolower($label)==='total'?'':strtolower($label) ?>"
     style="background:<?= $bg ?>;color:<?= $color ?>;padding:1rem 1.25rem;text-decoration:none;display:block;border:1.5px solid <?= $label==='Total'?'#0a0a0a':'#e5e7eb' ?>">
    <div style="font-size:.62rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;opacity:.7"><?= $label ?></div>
    <div style="font-family:Georgia,serif;font-size:1.6rem;font-weight:500;margin-top:.2rem"><?= $val ?></div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Toolbar -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;align-items:center">
  <form method="GET" style="display:flex;gap:.5rem;flex:1;min-width:200px">
    <?php if($statusF): ?><input type="hidden" name="status" value="<?= htmlspecialchars($statusF) ?>"><?php endif; ?>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search email or subject…"
           style="border:1.5px solid #e5e7eb;padding:.4rem .8rem;font-size:.82rem;outline:none;flex:1">
    <button class="admin-btn admin-btn--outline" style="font-size:.72rem">Search</button>
    <?php if($search||$statusF): ?><a href="email_queue.php" class="admin-btn admin-btn--muted" style="font-size:.72rem">Clear</a><?php endif; ?>
  </form>
  <?php if($stats['failed']>0): ?>
  <form method="POST" onsubmit="return confirm('Retry all <?= $stats['failed'] ?> failed emails?')">
    <input type="hidden" name="action" value="retry_all_failed">
    <button class="admin-btn admin-btn--primary" style="font-size:.72rem">↺ Retry All Failed (<?= $stats['failed'] ?>)</button>
  </form>
  <?php endif; ?>
  <form method="POST" onsubmit="return confirm('Delete sent emails older than 30 days?')">
    <input type="hidden" name="action" value="purge_sent">
    <button class="admin-btn admin-btn--muted" style="font-size:.72rem">🗑 Purge Old Sent</button>
  </form>
</div>

<!-- Table -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header">
    <h3>Email Queue <?php if($statusF): ?><span style="font-size:.72rem;color:#9ca3af">· <?= ucfirst($statusF) ?></span><?php endif; ?></h3>
    <span style="font-size:.75rem;color:#9ca3af"><?= number_format($totalRows) ?> email<?= $totalRows!=1?'s':'' ?></span>
  </div>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr>
      <th>ID</th><th>To</th><th>Subject</th><th>Status</th>
      <th style="text-align:center">Attempts</th><th>Created</th><th>Sent At</th><th>Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach($emails as $e):
      $statusColor=['pending'=>['#fef3e2','#92400e'],'sent'=>['#f0fdf4','#166534'],'failed'=>['#fef2f2','#991b1b']][$e['status']] ?? ['#f3f4f6','#374151'];
    ?>
    <tr>
      <td style="font-size:.75rem;color:#9ca3af">#<?= $e['id'] ?></td>
      <td>
        <div style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($e['to_name']??'') ?></div>
        <div style="font-size:.72rem;color:#9ca3af"><?= htmlspecialchars($e['to_email']) ?></div>
      </td>
      <td style="font-size:.82rem;max-width:220px">
        <?= htmlspecialchars(mb_substr($e['subject'],0,55)) ?>
        <?php if(!empty($e['last_error'])): ?>
        <div style="font-size:.68rem;color:#dc2626;margin-top:.2rem" title="<?= htmlspecialchars($e['last_error']) ?>">
          ⚠ <?= htmlspecialchars(mb_substr($e['last_error'],0,60)) ?>
        </div>
        <?php endif; ?>
      </td>
      <td><span style="font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:.2rem .5rem;background:<?= $statusColor[0] ?>;color:<?= $statusColor[1] ?>"><?= $e['status'] ?></span></td>
      <td style="text-align:center;font-size:.82rem"><?= $e['attempts'] ?>/3</td>
      <td style="font-size:.72rem;white-space:nowrap;color:#9ca3af"><?= date('d M H:i',strtotime($e['created_at'])) ?></td>
      <td style="font-size:.72rem;white-space:nowrap"><?= $e['sent_at'] ? date('d M H:i',strtotime($e['sent_at'])) : '—' ?></td>
      <td style="white-space:nowrap">
        <button onclick="viewEmail(<?= $e['id'] ?>, this.closest('tr'))"
                class="admin-btn admin-btn--outline" style="font-size:.62rem;padding:.25rem .5rem">View</button>
        <?php if($e['status']==='failed' || ($e['status']==='pending' && $e['attempts']>0)): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="retry">
          <input type="hidden" name="id" value="<?= $e['id'] ?>">
          <button class="admin-btn admin-btn--primary" style="font-size:.62rem;padding:.25rem .5rem">↺ Retry</button>
        </form>
        <?php endif; ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this email?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $e['id'] ?>">
          <button class="admin-btn admin-btn--danger" style="font-size:.62rem;padding:.25rem .5rem">✕</button>
        </form>
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
  <div style="display:flex;gap:.25rem;padding:1rem;flex-wrap:wrap">
    <?php for($i=1;$i<=$totalPages;$i++): ?>
    <a href="?page=<?= $i ?>&status=<?= urlencode($statusF) ?>&search=<?= urlencode($search) ?>"
       style="display:inline-block;padding:.3rem .65rem;font-size:.75rem;border:1.5px solid <?= $i===$page?'#0a0a0a':'#e5e7eb' ?>;background:<?= $i===$page?'#0a0a0a':'#fff' ?>;color:<?= $i===$page?'#fff':'#0a0a0a' ?>;text-decoration:none"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Cron Live Log -->
<div class="admin-card">
  <div class="admin-card-header">
    <h3>Live Email Log <span style="font-size:.72rem;color:#9ca3af">— /var/log/szv_email.log (last 20 lines)</span></h3>
    <button onclick="refreshLog()" class="admin-btn admin-btn--outline" style="font-size:.68rem">↺ Refresh</button>
  </div>
  <div class="admin-card-body" style="padding:0">
    <div id="log-output"
         style="background:#0a0a0a;color:#a3e635;font-family:monospace;font-size:.75rem;padding:1rem 1.25rem;max-height:320px;overflow-y:auto;line-height:1.7">
      <?php if(empty($logLines)): ?>
      <span style="color:#6b7280">No log entries yet. Cron runs every 2 minutes.</span>
      <?php else: ?>
      <?php foreach($logLines as $line): ?>
      <div><?= htmlspecialchars($line) ?></div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Cron status bar -->
<div style="margin-top:1rem;display:flex;align-items:center;gap:1rem;font-size:.75rem;color:#9ca3af;flex-wrap:wrap">
  <span>📡 Cron: email queue every <strong>2 min</strong> · reminders every <strong>1 hour</strong></span>
  <span>📋 Logs: <code>/var/log/szv_email.log</code> · <code>/var/log/szv_reminders.log</code></span>
  <a href="reminders.php" style="color:#0a0a0a;font-weight:600;font-size:.72rem">View Reminders →</a>
</div>

<!-- Email Preview Modal -->
<div id="emailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;width:100%;max-width:680px;max-height:90vh;display:flex;flex-direction:column;border-radius:2px">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid #e5e7eb;flex-shrink:0">
      <span style="font-size:.9rem;font-weight:600" id="modal-subject">Email Preview</span>
      <button onclick="document.getElementById('emailModal').style.display='none'"
              style="background:none;border:none;font-size:1.1rem;cursor:pointer;color:#9ca3af">✕</button>
    </div>
    <div style="padding:.75rem 1.5rem;background:#f7f7f5;font-size:.78rem;color:#6b7280;flex-shrink:0" id="modal-meta"></div>
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
    document.getElementById('emailModal').style.display = 'flex';
}
document.getElementById('emailModal').addEventListener('click', function(ev) {
    if (ev.target === this) this.style.display = 'none';
});

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
