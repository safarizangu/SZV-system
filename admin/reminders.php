<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

// Cancel a reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    try { $db->prepare("UPDATE booking_reminders SET status='cancelled' WHERE id=?")->execute([(int)$_POST['cancel_id']]); }
    catch(\Exception $e) {}
    header('Location: reminders.php'); exit;
}

// Add custom reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reminder'])) {
    try {
        $db->prepare("INSERT INTO booking_reminders (booking_ref, type, send_at, subject, body) VALUES (?,?,?,?,?)")
           ->execute([
               strtoupper(trim($_POST['booking_ref'])),
               $_POST['type'] ?? 'custom',
               $_POST['send_at'],
               trim($_POST['subject']),
               trim($_POST['body']),
           ]);
    } catch(\Exception $e) { error_log($e->getMessage()); }
    header('Location: reminders.php'); exit;
}

$pending  = [];
$sent     = [];
try {
    $pending = $db->query("SELECT r.*, b.full_name, b.email FROM booking_reminders r LEFT JOIN bookings b ON b.booking_ref=r.booking_ref WHERE r.status='pending' ORDER BY r.send_at ASC LIMIT 50")->fetchAll();
    $sent    = $db->query("SELECT r.*, b.full_name FROM booking_reminders r LEFT JOIN bookings b ON b.booking_ref=r.booking_ref WHERE r.status IN ('sent','failed') ORDER BY r.sent_at DESC LIMIT 20")->fetchAll();
} catch(\Exception $e) { error_log($e->getMessage()); }

$pageTitle = 'Reminders';
ob_start();
?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
  <div class="metric-card"><span class="metric-label">Pending</span>
    <span class="metric-value"><?= count($pending) ?></span></div>
  <div class="metric-card"><span class="metric-label">Sent (last 20)</span>
    <span class="metric-value"><?= count(array_filter($sent,fn($r)=>$r['status']==='sent')) ?></span></div>
  <div class="metric-card"><span class="metric-label">Failed</span>
    <span class="metric-value" style="color:#dc2626"><?= count(array_filter($sent,fn($r)=>$r['status']==='failed')) ?></span></div>
</div>

<!-- Cron setup info -->
<div class="admin-alert admin-alert--info" style="margin-bottom:1.5rem;font-size:.82rem">
  <strong>Setup Cron Jobs (run once on server):</strong><br>
  <code>crontab -e</code><br>
  <code>*/2 * * * * /usr/bin/php /var/www/html/safari-zangu/php/process_email_queue.php >> /var/log/szv_email.log 2>&1</code><br>
  <code>0 * * * * /usr/bin/php /var/www/html/safari-zangu/php/process_reminders.php >> /var/log/szv_reminders.log 2>&1</code>
</div>

<!-- Add custom reminder -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Add Custom Reminder</h3></div>
  <div class="admin-card-body">
  <form method="POST">
    <input type="hidden" name="add_reminder" value="1">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
      <div><label class="admin-label">Booking Ref</label>
        <input name="booking_ref" class="admin-input" placeholder="SZV2026..." required></div>
      <div><label class="admin-label">Type</label>
        <select name="type" class="admin-input">
          <?php foreach(['deposit_due'=>'Deposit Due','balance_due'=>'Balance Due','departure'=>'Departure (7 days)','post_trip'=>'Post-Trip Feedback','custom'=>'Custom'] as $v=>$l): ?>
          <option value="<?= $v ?>"><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="admin-label">Send At</label>
        <input name="send_at" type="datetime-local" class="admin-input" required></div>
      <div><label class="admin-label">Subject</label>
        <input name="subject" class="admin-input" placeholder="Reminder subject…"></div>
    </div>
    <div style="margin-bottom:.75rem"><label class="admin-label">Body (optional — auto-generated if blank)</label>
      <textarea name="body" class="admin-input" rows="3" placeholder="Leave blank to use default template…"></textarea></div>
    <button type="submit" class="admin-btn admin-btn--primary">Schedule Reminder</button>
  </form>
  </div>
</div>

<!-- Pending -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Pending Reminders</h3></div>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Ref</th><th>Guest</th><th>Type</th><th>Send At</th><th>Subject</th><th>Cancel</th></tr></thead>
    <tbody>
    <?php foreach ($pending as $r): ?>
    <tr>
      <td><code><?= htmlspecialchars($r['booking_ref']) ?></code></td>
      <td><?= htmlspecialchars($r['full_name']??'—') ?></td>
      <td><span style="font-size:.7rem;font-weight:600;text-transform:uppercase"><?= $r['type'] ?></span></td>
      <td style="white-space:nowrap"><?= date('d M Y H:i', strtotime($r['send_at'])) ?></td>
      <td style="font-size:.78rem;color:#6b7280"><?= htmlspecialchars(substr($r['subject']??'',0,50)) ?></td>
      <td>
        <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this reminder?')">
          <input type="hidden" name="cancel_id" value="<?= $r['id'] ?>">
          <button class="admin-btn admin-btn--danger" style="font-size:.65rem;padding:.25rem .5rem">Cancel</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($pending)): ?><tr><td colspan="6" class="admin-empty">No pending reminders.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Sent history -->
<div class="admin-card">
  <div class="admin-card-header"><h3>Recent History</h3></div>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Ref</th><th>Guest</th><th>Type</th><th>Sent At</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($sent as $r): ?>
    <tr>
      <td><code><?= htmlspecialchars($r['booking_ref']) ?></code></td>
      <td><?= htmlspecialchars($r['full_name']??'—') ?></td>
      <td style="font-size:.7rem;text-transform:uppercase"><?= $r['type'] ?></td>
      <td style="white-space:nowrap;font-size:.78rem"><?= $r['sent_at'] ? date('d M H:i',strtotime($r['sent_at'])) : '—' ?></td>
      <td><span style="color:<?= $r['status']==='sent'?'#16a34a':'#dc2626' ?>;font-weight:700;font-size:.72rem;text-transform:uppercase"><?= $r['status'] ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($sent)): ?><tr><td colspan="5" class="admin-empty">No history yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php $content = ob_get_clean(); require_once __DIR__ . '/../layouts/admin.php'; ?>
