<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Contact Messages';
ob_start();
?>

<?php
// Mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $id = (int)$_POST['mark_read'];
    try { $db->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$id]); }
    catch(Exception $e){ error_log($e->getMessage()); }
    header('Location: contact_messages.php'); exit;
}
// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    try { $db->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]); }
    catch(Exception $e){ error_log($e->getMessage()); }
    header('Location: contact_messages.php'); exit;
}

$msgs = [];
$unread = 0;
try {
    $msgs   = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
    $unread = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
} catch(Exception $e){ error_log($e->getMessage()); }
?>
<div style="margin-bottom:1.25rem">
  <?php if($unread > 0): ?>
  <span style="background:#dc2626;color:#fff;padding:.2rem .7rem;font-size:.7rem;font-weight:700;border-radius:2px"><?= $unread ?> Unread</span>
  <?php endif; ?>
</div>
<div class="admin-table-wrap">
<table class="admin-table">
<thead><tr><th>Date</th><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($msgs as $m): ?>
<tr style="<?= !$m['is_read'] ? 'background:#fffbeb' : '' ?>">
  <td style="white-space:nowrap"><?= date('d M Y', strtotime($m['created_at'])) ?></td>
  <td><strong><?= htmlspecialchars($m['full_name']) ?></strong></td>
  <td><a href="mailto:<?= htmlspecialchars($m['email']) ?>"><?= htmlspecialchars($m['email']) ?></a></td>
  <td><?= htmlspecialchars($m['subject'] ?? '—') ?></td>
  <td style="max-width:260px;white-space:pre-wrap;font-size:.78rem"><?= htmlspecialchars(substr($m['message'],0,120)) ?><?= strlen($m['message'])>120?'…':'' ?></td>
  <td><?= $m['is_read'] ? '<span style="color:#16a34a;font-weight:600;font-size:.75rem">Read</span>' : '<span style="color:#dc2626;font-weight:700;font-size:.75rem">Unread</span>' ?></td>
  <td style="white-space:nowrap">
    <?php if(!$m['is_read']): ?>
    <form method="POST" style="display:inline">
      <input type="hidden" name="mark_read" value="<?= $m['id'] ?>">
      <button class="admin-btn admin-btn--outline" style="font-size:.7rem">Mark Read</button>
    </form>
    <?php endif; ?>
    <a href="mailto:<?= htmlspecialchars($m['email']) ?>?subject=Re: <?= urlencode($m['subject']??'') ?>" class="admin-btn admin-btn--primary" style="font-size:.7rem">Reply</a>
    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this message?')">
      <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
      <button class="admin-btn admin-btn--danger" style="font-size:.7rem">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
<?php if(empty($msgs)): ?><tr><td colspan="7" class="admin-empty">No messages yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
