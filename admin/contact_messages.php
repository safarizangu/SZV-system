<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Contact Messages';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        try { $db->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([(int)$_POST['id']]); }
        catch(Exception $e){}
        header('Location: contact_messages.php'); exit;
    }
    if ($action === 'mark_unread') {
        try { $db->prepare("UPDATE contact_messages SET is_read=0 WHERE id=?")->execute([(int)$_POST['id']]); }
        catch(Exception $e){}
        header('Location: contact_messages.php'); exit;
    }
    if ($action === 'delete') {
        try { $db->prepare("DELETE FROM contact_messages WHERE id=?")->execute([(int)$_POST['id']]); }
        catch(Exception $e){}
        header('Location: contact_messages.php'); exit;
    }
    if ($action === 'mark_all_read') {
        try { $db->exec("UPDATE contact_messages SET is_read=1 WHERE is_read=0"); }
        catch(Exception $e){}
        header('Location: contact_messages.php'); exit;
    }
}

$filter = $_GET['filter'] ?? 'all';
$msgs = []; $unread = 0;
try {
    $where = $filter === 'unread' ? 'WHERE is_read=0' : ($filter === 'read' ? 'WHERE is_read=1' : '');
    $msgs   = $db->query("SELECT * FROM contact_messages $where ORDER BY created_at DESC")->fetchAll();
    $unread = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
} catch(Exception $e){ error_log($e->getMessage()); }

ob_start();
?>

<!-- Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem">
  <div style="display:flex;gap:.4rem;flex-wrap:wrap">
    <a href="?filter=all"    class="admin-filter-pill <?= $filter==='all'?'active':'' ?>">All <span style="opacity:.55"><?= count($msgs) ?></span></a>
    <a href="?filter=unread" class="admin-filter-pill <?= $filter==='unread'?'active':'' ?>">
      Unread <span style="opacity:.55"><?= $unread ?></span>
    </a>
    <a href="?filter=read"   class="admin-filter-pill <?= $filter==='read'?'active':'' ?>">Read</a>
  </div>
  <?php if($unread > 0): ?>
  <form method="POST" style="display:inline">
    <input type="hidden" name="action" value="mark_all_read">
    <button class="admin-btn admin-btn--outline" style="font-size:.72rem">✓ Mark All Read</button>
  </form>
  <?php endif; ?>
</div>

<!-- Messages list -->
<div class="admin-cards-stack">
<?php foreach($msgs as $m): ?>
<div style="border:1.5px solid <?= !$m['is_read']?'#fcd34d':'#e5e7eb' ?>;background:<?= !$m['is_read']?'#fffbeb':'#fff' ?>;padding:1.25rem 1.5rem">
  <div style="display:flex;align-items:start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:.35rem">
        <?php if(!$m['is_read']): ?>
        <span style="font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:#dc2626;color:#fff;padding:.15rem .45rem">UNREAD</span>
        <?php else: ?>
        <span style="font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af">Read</span>
        <?php endif; ?>
        <span style="font-size:.72rem;color:#9ca3af"><?= date('d M Y H:i', strtotime($m['created_at'])) ?></span>
      </div>
      <!-- Name + contact -->
      <div style="font-weight:700;font-size:.95rem;margin-bottom:.2rem"><?= htmlspecialchars($m['full_name']) ?></div>
      <div style="font-size:.8rem;color:#6b7280;margin-bottom:.5rem">
        <a href="mailto:<?= htmlspecialchars($m['email']) ?>" style="color:inherit"><?= htmlspecialchars($m['email']) ?></a>
        <?php if(!empty($m['phone'])): ?> · <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$m['phone']) ?>" target="_blank" style="color:inherit"><?= htmlspecialchars($m['phone']) ?></a><?php endif; ?>
        <?php if(!empty($m['country'])): ?> · <?= htmlspecialchars($m['country']) ?><?php endif; ?>
      </div>
      <!-- Subject -->
      <?php if(!empty($m['subject'])): ?>
      <div style="font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.5rem">Re: <?= htmlspecialchars($m['subject']) ?></div>
      <?php endif; ?>
      <!-- Full message -->
      <div class="msg-body" style="font-size:.85rem;line-height:1.8;color:#0a0a0a;white-space:pre-wrap;background:#f9f9f8;padding:1rem 1.25rem;border-left:3px solid <?= !$m['is_read']?'#c8963e':'#e5e7eb' ?>;max-height:200px;overflow:hidden;transition:max-height .3s ease;position:relative">
        <?= nl2br(htmlspecialchars($m['message'])) ?>
        <div style="position:absolute;bottom:0;left:0;right:0;height:40px;background:linear-gradient(transparent,#f9f9f8);pointer-events:none;transition:opacity .3s" class="msg-fade"></div>
      </div>
      <button onclick="var b=this.previousElementSibling;var f=b.querySelector('.msg-fade');if(b.style.maxHeight==='none'){b.style.maxHeight='200px';f.style.opacity='1';this.textContent='▼ Read more';}else{b.style.maxHeight='none';f.style.opacity='0';this.textContent='▲ Show less';}"
              style="font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:none;border:none;color:#c8963e;cursor:pointer;padding:.25rem 0;margin-bottom:.5rem">
        ▼ Read more
      </button>
    </div>
    <!-- Actions -->
    <div style="display:flex;flex-direction:column;gap:.4rem;flex-shrink:0">
      <a href="mailto:<?= htmlspecialchars($m['email']) ?>?subject=Re: <?= urlencode($m['subject']??'Your enquiry') ?>"
         class="admin-btn admin-btn--primary" style="font-size:.65rem;white-space:nowrap">Reply by Email</a>
      <?php if(!empty($m['phone'])): ?>
      <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$m['phone']) ?>?text=<?= urlencode('Hi '.$m['full_name'].', thank you for your message. ') ?>"
         target="_blank" class="admin-btn admin-btn--success" style="font-size:.65rem">WhatsApp</a>
      <?php endif; ?>
      <?php if(!$m['is_read']): ?>
      <form method="POST" style="display:contents">
        <input type="hidden" name="action" value="mark_read">
        <input type="hidden" name="id" value="<?= $m['id'] ?>">
        <button class="admin-btn admin-btn--outline" style="font-size:.65rem">✓ Mark Read</button>
      </form>
      <?php else: ?>
      <form method="POST" style="display:contents">
        <input type="hidden" name="action" value="mark_unread">
        <input type="hidden" name="id" value="<?= $m['id'] ?>">
        <button class="admin-btn admin-btn--muted" style="font-size:.65rem">Mark Unread</button>
      </form>
      <?php endif; ?>
      <form method="POST" style="display:contents" onsubmit="return confirm('Delete this message?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $m['id'] ?>">
        <button class="admin-btn admin-btn--danger" style="font-size:.65rem">Delete</button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if(empty($msgs)): ?>
<div style="text-align:center;padding:3rem;color:#9ca3af">
  <div style="font-size:2rem;margin-bottom:.5rem;opacity:.3">✉</div>
  <p style="font-size:.85rem">No <?= $filter !== 'all' ? $filter : '' ?> messages.</p>
</div>
<?php endif; ?>
</div>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
