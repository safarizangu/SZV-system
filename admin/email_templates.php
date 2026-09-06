<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Mail\EmailTemplate;
AuthMiddleware::requireAdmin();
$db        = \App\Models\Database::getInstance();
$pageTitle = 'Email Templates';

// ── Handle save ───────────────────────────────────────────────
$saveMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $key     = trim($_POST['template_key'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body    = $_POST['body_html'] ?? '';
    if ($key && $subject && $body) {
        try {
            $db->prepare("INSERT INTO email_templates (template_key, subject, body_html, is_custom)
                          VALUES (?,?,?,1)
                          ON DUPLICATE KEY UPDATE subject=VALUES(subject), body_html=VALUES(body_html), is_custom=1")
               ->execute([$key, $subject, $body]);
            $saveMsg = 'Template saved ✓';
        } catch(\Exception $e) { $saveMsg = 'Error: '.$e->getMessage(); }
    }
}

// ── Load custom overrides from DB ─────────────────────────────
$customTemplates = [];
try {
    $rows = $db->query("SELECT * FROM email_templates WHERE is_custom=1")->fetchAll();
    foreach ($rows as $row) $customTemplates[$row['template_key']] = $row;
} catch(\Exception $e) {}

// ── Template definitions ──────────────────────────────────────
$templates = [
    ['key'=>'booking_confirmation',      'label'=>'Booking Confirmation',      'to'=>'Guest',  'trigger'=>'Booking form submitted'],
    ['key'=>'booking_reminder_deposit',  'label'=>'Deposit Reminder',          'to'=>'Guest',  'trigger'=>'48h after booking'],
    ['key'=>'booking_reminder_balance',  'label'=>'Balance Reminder',          'to'=>'Guest',  'trigger'=>'45 days before travel'],
    ['key'=>'booking_reminder_departure','label'=>'Departure Reminder',        'to'=>'Guest',  'trigger'=>'7 days before travel'],
    ['key'=>'booking_reminder_posttrip', 'label'=>'Post-Trip (Review Request)','to'=>'Guest',  'trigger'=>'3 days after travel'],
    ['key'=>'quotation_sent',            'label'=>'Quotation Sent',            'to'=>'Guest',  'trigger'=>'Send Quotation clicked'],
    ['key'=>'quotation_accepted',        'label'=>'Quotation Accepted',        'to'=>'Guest',  'trigger'=>'Status → Accepted'],
    ['key'=>'quotation_expired',         'label'=>'Quotation Expired',         'to'=>'Guest',  'trigger'=>'Status → Expired'],
    ['key'=>'contact_acknowledgement',   'label'=>'Contact Auto-Reply',        'to'=>'Guest',  'trigger'=>'Contact form submitted'],
    ['key'=>'contact_admin_notification','label'=>'Contact Admin Alert',       'to'=>'Admin',  'trigger'=>'Contact form submitted'],
    ['key'=>'custom_request_ack',        'label'=>'Custom Request Auto-Reply', 'to'=>'Guest',  'trigger'=>'Custom Safari form submitted'],
    ['key'=>'custom_request_admin',      'label'=>'Custom Request Admin Alert','to'=>'Admin',  'trigger'=>'Custom Safari form submitted'],
    ['key'=>'lead_converted',            'label'=>'Lead Converted (Agent)',    'to'=>'Agent',  'trigger'=>'Lead moves to Booked'],
    ['key'=>'commission_approved',       'label'=>'Commission Approved',       'to'=>'Agent',  'trigger'=>'Commission approved'],
    ['key'=>'commission_paid',           'label'=>'Commission Paid',           'to'=>'Agent',  'trigger'=>'Commission marked paid'],
];

$editKey  = $_GET['edit'] ?? '';
$editData = $customTemplates[$editKey] ?? null;

ob_start();
?>
<style>
.tpl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;margin-bottom:2rem}
.tpl-card{border:1.5px solid var(--gray-200);background:var(--white);padding:1.1rem;position:relative}
.tpl-card.custom-override{border-color:var(--gold);background:#fffbeb}
.tpl-badge{position:absolute;top:.65rem;right:.65rem;font-size:.55rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.15rem .4rem;background:var(--gold);color:var(--white)}
.tpl-key{font-size:.6rem;font-family:monospace;color:var(--gray-500);margin-bottom:.35rem}
.tpl-label{font-weight:700;font-size:.9rem;margin-bottom:.2rem}
.tpl-meta{font-size:.72rem;color:var(--gray-500);margin-bottom:.75rem}
.tpl-actions{display:flex;gap:.4rem}
#editor-area{background:var(--gray-50);border:1.5px solid var(--gray-200);padding:1.5rem;margin-bottom:1.5rem}
</style>

<?php if ($saveMsg): ?>
<div class="admin-alert <?= str_contains($saveMsg,'✓')?'admin-alert--success':'admin-alert--danger' ?>">
  <?= htmlspecialchars($saveMsg) ?>
</div>
<?php endif; ?>

<?php if ($editKey): ?>
<!-- ── Template Editor ─────────────────────────────────────── -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header">
    <h3 style="font-family:var(--font-disp)">
      Editing: <?= htmlspecialchars($editKey) ?>
      <?php if (isset($customTemplates[$editKey])): ?>
      <span style="font-size:.65rem;font-weight:400;color:var(--gold)"> ★ Custom override active</span>
      <?php endif; ?>
    </h3>
    <a href="email_templates.php" class="admin-btn admin-btn--outline">← Back to Templates</a>
  </div>
  <div class="admin-card-body" id="editor-area">
    <form method="POST">
      <input type="hidden" name="save_template" value="1">
      <input type="hidden" name="template_key" value="<?= htmlspecialchars($editKey) ?>">

      <div style="margin-bottom:1rem">
        <label class="admin-label">Subject Line *</label>
        <input class="admin-input" name="subject" required
               value="<?= htmlspecialchars($editData['subject'] ?? 'Safari Zangu — '.ucwords(str_replace('_',' ',$editKey))) ?>">
        <small style="font-size:.65rem;color:var(--gray-500)">Use {client_name}, {booking_ref}, {package_name} etc as placeholders</small>
      </div>

      <div style="margin-bottom:1rem">
        <label class="admin-label">Email Body (HTML)</label>
        <div style="border:1.5px solid var(--gray-200);border-bottom:none;background:var(--gray-100);padding:.4rem .65rem;font-size:.65rem;color:var(--gray-500);display:flex;gap:.5rem;flex-wrap:wrap">
          <?php foreach(['<b>Bold</b>','<i>Italic</i>','<br>','<p>','</p>','<strong>','</strong>',
                          '{client_name}','{booking_ref}','{package_name}','{travel_date}','{total_amount}',
                          '{deposit_amount}','{agent_name}','{commission_amount}'] as $snip): ?>
          <button type="button" onclick="insertSnippet('<?= htmlspecialchars($snip,ENT_QUOTES) ?>')"
                  style="background:var(--white);border:1px solid var(--gray-200);padding:.15rem .4rem;font-family:monospace;font-size:.62rem;cursor:pointer">
            <?= htmlspecialchars($snip) ?>
          </button>
          <?php endforeach; ?>
        </div>
        <textarea name="body_html" id="body_html" rows="18"
                  style="width:100%;font-family:monospace;font-size:.8rem;line-height:1.6;padding:.75rem;border:1.5px solid var(--gray-200);resize:vertical"
        ><?= htmlspecialchars($editData['body_html'] ?? '<p>Hello {client_name},</p>
<p>Thank you for choosing Safari Zangu Ventures.</p>
<p>Best regards,<br>The Safari Zangu Team</p>') ?></textarea>
      </div>

      <!-- Live Preview -->
      <div style="margin-bottom:1rem">
        <label class="admin-label">Preview</label>
        <div style="display:flex;gap:.5rem;margin-bottom:.5rem">
          <button type="button" onclick="updatePreview()" class="admin-btn admin-btn--outline" style="font-size:.68rem">Refresh Preview</button>
          <label style="display:flex;align-items:center;gap:.35rem;font-size:.72rem">
            <input type="checkbox" id="mobile-preview" onchange="toggleMobile(this.checked)"> Mobile view
          </label>
        </div>
        <div id="preview-wrap" style="border:1.5px solid var(--gray-200);transition:width .3s">
          <iframe id="preview-frame" style="width:100%;height:400px;border:none"></iframe>
        </div>
      </div>

      <div style="display:flex;gap:.5rem;align-items:center">
        <button type="submit" class="admin-btn admin-btn--primary">Save Template Override</button>
        <?php if (isset($customTemplates[$editKey])): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="reset_template" value="<?= htmlspecialchars($editKey) ?>">
          <button type="submit" class="admin-btn admin-btn--danger" onclick="return confirm('Reset to default template?')">Reset to Default</button>
        </form>
        <?php endif; ?>
        <a href="email_templates.php" class="admin-btn admin-btn--outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
function insertSnippet(text) {
  var ta = document.getElementById('body_html');
  var start = ta.selectionStart, end = ta.selectionEnd;
  ta.value = ta.value.substring(0,start) + text + ta.value.substring(end);
  ta.selectionStart = ta.selectionEnd = start + text.length;
  ta.focus();
  updatePreview();
}
function updatePreview() {
  var html = document.getElementById('body_html').value;
  var frame = document.getElementById('preview-frame');
  var preview = '<html><head><style>body{font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#0a0a0a;max-width:600px;margin:20px auto;padding:20px}</style></head><body>'
    + '<div style="background:#0a0a0a;color:#fff;padding:1rem 1.5rem;margin-bottom:1.5rem">'
    + '<div style="font-size:1.1rem;font-weight:500;font-style:italic">Safari Zangu Ventures</div></div>'
    + html.replace(/{(\w+)}/g, '<span style="background:#fef3e2;color:#92400e;padding:.1rem .3rem;border-radius:2px;font-size:.8em">{$1}</span>')
    + '<div style="margin-top:2rem;padding-top:1rem;border-top:1px solid #e5e7eb;font-size:.75rem;color:#9ca3af;text-align:center">Safari Zangu Ventures · Arusha, Tanzania · safarizanguven.com</div>'
    + '</body></html>';
  frame.srcdoc = preview;
}
function toggleMobile(on) {
  var wrap = document.getElementById('preview-wrap');
  wrap.style.maxWidth = on ? '390px' : '100%';
  wrap.style.margin = on ? '0 auto' : '0';
}
document.addEventListener('DOMContentLoaded', updatePreview);
document.getElementById('body_html').addEventListener('input', updatePreview);
</script>

<?php else: ?>
<!-- ── Template Grid ──────────────────────────────────────── -->
<div class="admin-card-header">
  <h2 style="font-family:var(--font-disp);font-size:1.05rem">Email Templates</h2>
  <span style="font-size:.72rem;color:var(--gray-500)"><?= count($customTemplates) ?> custom override<?= count($customTemplates)!==1?'s':'' ?> active</span>
</div>

<div style="background:#fffbeb;border:1.5px solid #fde68a;padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.8rem;color:#92400e">
  <b>How templates work:</b> Default templates are defined in <code>app/Mail/EmailTemplate.php</code>.
  Saving a custom override here stores it in the database and takes priority over the default.
  Click Reset to Default to remove the override and revert to the code template.
</div>

<div class="tpl-grid">
  <?php foreach ($templates as $tpl):
    $isCustom = isset($customTemplates[$tpl['key']]);
    // "Who this email goes to" pill — uses the shared badge-status palette
    // instead of its own hand-rolled Guest/Agent/Admin color map.
    $toBadge  = $tpl['to']==='Guest' ? 'blue' : ($tpl['to']==='Agent' ? 'green' : 'red');
  ?>
  <div class="tpl-card <?= $isCustom ? 'custom-override' : '' ?>">
    <?php if ($isCustom): ?><div class="tpl-badge">★ Custom</div><?php endif; ?>
    <div class="tpl-key"><?= htmlspecialchars($tpl['key']) ?></div>
    <div class="tpl-label"><?= htmlspecialchars($tpl['label']) ?></div>
    <div class="tpl-meta">
      <span class="badge-status badge-status--<?= $toBadge ?>">→ <?= $tpl['to'] ?></span>
      <span style="margin-left:.4rem;font-size:.68rem"><?= htmlspecialchars($tpl['trigger']) ?></span>
    </div>
    <div class="tpl-actions">
      <a href="?edit=<?= urlencode($tpl['key']) ?>" class="admin-btn admin-btn--primary" style="font-size:.65rem;padding:.35rem .7rem">Edit Template</a>
      <?php if ($isCustom): ?>
      <span style="font-size:.65rem;color:var(--gold);display:flex;align-items:center">Custom active</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
?>
