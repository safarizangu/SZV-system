<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Mail\EmailTemplate;
AuthMiddleware::requireAdmin();

$pageTitle = 'Email Templates';

// All templates with metadata
$templates = [
    'booking_confirmation'       => ['label'=>'Booking Confirmation',       'group'=>'Bookings',     'to'=>'Guest',  'trigger'=>'php/book.php, php/convert_quotation.php'],
    'booking_reminder_deposit'   => ['label'=>'Deposit Reminder',           'group'=>'Bookings',     'to'=>'Guest',  'trigger'=>'process_reminders.php — 48h after booking'],
    'booking_reminder_balance'   => ['label'=>'Balance Due Reminder',       'group'=>'Bookings',     'to'=>'Guest',  'trigger'=>'process_reminders.php — 45 days before travel'],
    'booking_reminder_departure' => ['label'=>'Departure Reminder',         'group'=>'Bookings',     'to'=>'Guest',  'trigger'=>'process_reminders.php — 7 days before travel'],
    'booking_reminder_posttrip'  => ['label'=>'Post-Trip Feedback',         'group'=>'Bookings',     'to'=>'Guest',  'trigger'=>'process_reminders.php — 3 days after return'],
    'quotation_sent'             => ['label'=>'Quotation Sent',             'group'=>'Quotations',   'to'=>'Guest',  'trigger'=>'php/quotation_email.php'],
    'quotation_accepted'         => ['label'=>'Quotation Accepted',         'group'=>'Quotations',   'to'=>'Guest',  'trigger'=>'admin/quotations.php on status → accepted'],
    'quotation_expired'          => ['label'=>'Quotation Expired',          'group'=>'Quotations',   'to'=>'Guest',  'trigger'=>'Cron / manual admin action'],
    'contact_acknowledgement'    => ['label'=>'Contact Auto-Reply',         'group'=>'Contact',      'to'=>'Guest',  'trigger'=>'php/contact.php — auto-reply to guest'],
    'contact_admin_notification' => ['label'=>'Contact Admin Notification', 'group'=>'Contact',      'to'=>'Admin',  'trigger'=>'php/contact.php — notify admin'],
    'custom_request_ack'         => ['label'=>'Custom Request Auto-Reply',  'group'=>'Requests',     'to'=>'Guest',  'trigger'=>'php/custom_safari.php — auto-reply'],
    'custom_request_admin'       => ['label'=>'Custom Request Notification','group'=>'Requests',     'to'=>'Admin',  'trigger'=>'php/custom_safari.php — notify admin'],
    'lead_converted'             => ['label'=>'Lead Converted',             'group'=>'Agent',        'to'=>'Agent',  'trigger'=>'php/leads_api.php — when stage → Booked'],
    'commission_approved'        => ['label'=>'Commission Approved',        'group'=>'Agent',        'to'=>'Agent',  'trigger'=>'admin/commissions.php on approve'],
    'commission_paid'            => ['label'=>'Commission Paid',            'group'=>'Agent',        'to'=>'Agent',  'trigger'=>'admin/commissions.php on paid'],
];

// Sample data for preview rendering
$sampleData = [
    'booking_confirmation' => [
        'client_name'=>'Sarah Mitchell', 'booking_ref'=>'SZVAB1234',
        'package_name'=>'Classic Safari — 5 Nights', 'travel_date'=>'2026-09-15',
        'travelers'=>2, 'adults'=>2, 'children'=>0,
        'accommodation_level'=>'midrange', 'total_amount'=>3300.00,
        'deposit_amount'=>990.00, 'balance_due'=>2310.00,
    ],
    'booking_reminder_deposit' => [
        'client_name'=>'James Okonkwo', 'booking_ref'=>'SZVC5678',
        'package_name'=>'Luxury Safari — 6 Nights', 'deposit_amount'=>1740.00,
    ],
    'booking_reminder_balance' => [
        'client_name'=>'Emma Schreiber', 'booking_ref'=>'SZVD9012',
        'package_name'=>'Comfort Safari — 5 Nights',
        'travel_date'=>'2026-10-20', 'balance_due'=>2170.00,
    ],
    'booking_reminder_departure' => [
        'client_name'=>'David & Claire Wilson', 'booking_ref'=>'SZVE3456',
        'package_name'=>'Safari + Zanzibar — 7 Nights', 'travel_date'=>'2026-09-22',
    ],
    'booking_reminder_posttrip' => [
        'client_name'=>'Hana Nakamura', 'booking_ref'=>'SZVF7890',
        'package_name'=>'Cultural Safari — 4 Nights',
    ],
    'quotation_sent' => [
        'client_name'=>'Robert & Anne Taylor', 'quote_ref'=>'SZV-Q001',
        'package_name'=>'Classic Safari', 'adults'=>2, 'children'=>0,
        'duration_days'=>5, 'start_date'=>'2026-10-10', 'accommodation_level'=>'midrange',
        'destinations'=>['Tarangire','Serengeti','Ngorongoro'],
        'grand_total'=>3300.00, 'deposit_amount'=>990.00, 'balance_due'=>2310.00,
        'per_person'=>1650.00, 'valid_until'=>date('Y-m-d', strtotime('+14 days')),
        'notes'=>"INCLUDED:\n✓ Private 4WD Safari Vehicle\n✓ Professional Guide\n✓ All park fees\n✓ Full board accommodation\n\nNOT INCLUDED:\n✗ International flights\n✗ Tanzania visa (USD 50)",
        'line_items'=>[
            ['description'=>'Accommodation – Tarangire River Camp', 'unit_price'=>180, 'quantity_str'=>'1 Night', 'amount'=>180],
            ['description'=>'Accommodation – Serengeti Seronera Camp', 'unit_price'=>220, 'quantity_str'=>'2 Nights', 'amount'=>440],
            ['description'=>'Toyota Land Cruiser 4WD & Fuel', 'unit_price'=>350, 'quantity_str'=>'5 Days', 'amount'=>1750],
            ['description'=>'Tarangire National Park Entry Fees', 'unit_price'=>59, 'quantity_str'=>'2 Guests', 'amount'=>118],
            ['description'=>'Serengeti National Park Entry Fees', 'unit_price'=>70, 'quantity_str'=>'2 Guests × 2 Days', 'amount'=>280],
            ['description'=>'Ngorongoro Conservation Area Fees', 'unit_price'=>70, 'quantity_str'=>'2 Guests', 'amount'=>140],
            ['description'=>'Meals (Full Board — 5 days)', 'unit_price'=>20, 'quantity_str'=>'2 Guests × 5 Days', 'amount'=>200],
            ['description'=>'Professional Driver Guide & Service Fee', 'unit_price'=>100, 'quantity_str'=>'2 Guests', 'amount'=>200],
        ],
    ],
    'quotation_accepted' => [
        'client_name'=>'Mark Stevenson', 'quote_ref'=>'SZV-Q002', 'deposit_amount'=>870.00,
    ],
    'quotation_expired' => [
        'client_name'=>'Sophie Laurent', 'quote_ref'=>'SZV-Q003',
    ],
    'contact_acknowledgement' => [
        'client_name'=>'Ben Kamau', 'subject'=>'Safari in July 2026',
    ],
    'contact_admin_notification' => [
        'client_name'=>'Ben Kamau', 'client_email'=>'ben@example.com',
        'phone'=>'+44 7911 123456', 'subject'=>'Safari in July 2026',
        'message'=>'Hi, we are a family of 4 looking for a 7-day safari in July 2026. We are interested in the Serengeti and Ngorongoro. Budget around $8,000 total. Could you send us a quotation?',
    ],
    'custom_request_ack' => [
        'client_name'=>'The Johnson Family', 'full_name'=>'The Johnson Family',
    ],
    'custom_request_admin' => [
        'full_name'=>'The Johnson Family', 'email'=>'johnson@example.com',
        'phone'=>'+1 555 0182', 'country'=>'United States',
        'adults'=>2, 'children'=>2, 'duration_days'=>8, 'start_date'=>'2026-08-01',
        'total_budget'=>'USD 12,000–15,000',
        'destinations'=>['Serengeti','Ngorongoro','Zanzibar'],
        'message'=>'We are a family of 4 (2 adults, 2 kids aged 8 and 11) looking for an 8-day family safari with beach extension in Zanzibar. We love wildlife and want to see the Big Five.',
    ],
    'lead_converted' => [
        'agent_name'=>'Emmanuel Mwangi', 'client_name'=>'Peter & Lisa Hughes',
        'package_name'=>'Luxury Safari — 6 Nights', 'booking_value'=>5800.00,
        'commission_rate'=>10.0, 'commission_amount'=>580.00,
    ],
    'commission_approved' => [
        'agent_name'=>'Emmanuel Mwangi', 'client_name'=>'Peter & Lisa Hughes',
        'commission_amount'=>580.00,
    ],
    'commission_paid' => [
        'agent_name'=>'Emmanuel Mwangi', 'client_name'=>'Peter & Lisa Hughes',
        'commission_amount'=>580.00,
    ],
];

// Handle preview request
$previewTemplate = $_GET['preview'] ?? '';
$previewHtml = '';
$previewSubject = '';
if ($previewTemplate && isset($templates[$previewTemplate])) {
    try {
        $vars    = $sampleData[$previewTemplate] ?? ['client_name'=>'Sample Guest'];
        $result  = EmailTemplate::render($previewTemplate, $vars);
        $previewHtml    = $result['html'];
        $previewSubject = $result['subject'];
    } catch(\Exception $e) {
        $previewHtml = '<p style="color:red;padding:1rem">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

// Group templates
$groups = [];
foreach ($templates as $key => $meta) {
    $groups[$meta['group']][$key] = $meta;
}

ob_start();
?>

<div style="display:grid;grid-template-columns:280px 1fr;gap:1.5rem;align-items:start;min-height:80vh">

<!-- LEFT: template list -->
<div style="position:sticky;top:calc(var(--topbar-h,60px) + 1rem)">
  <div class="admin-card">
    <div class="admin-card-header" style="padding:.85rem 1rem">
      <h3>Email Templates</h3>
      <span style="font-size:.65rem;color:var(--gray-500)"><?= count($templates) ?> templates</span>
    </div>
    <div style="padding:.25rem 0">
      <?php foreach ($groups as $groupName => $groupTemplates): ?>
      <div style="padding:.5rem 1rem .25rem;font-size:.55rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--gray-400)"><?= htmlspecialchars($groupName) ?></div>
      <?php foreach ($groupTemplates as $key => $meta): ?>
      <a href="?preview=<?= urlencode($key) ?>"
         style="display:flex;align-items:center;justify-content:space-between;padding:.55rem 1rem;font-size:.78rem;text-decoration:none;border-left:2px solid <?= $previewTemplate===$key?'var(--black)':'transparent' ?>;background:<?= $previewTemplate===$key?'var(--gray-50)':'transparent' ?>;color:var(--gray-700)"
         onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='<?= $previewTemplate===$key?'var(--gray-50)':'transparent' ?>'">
        <span style="font-weight:<?= $previewTemplate===$key?'700':'400' ?>"><?= htmlspecialchars($meta['label']) ?></span>
        <span style="font-size:.58rem;padding:.1rem .35rem;background:<?= $meta['to']==='Admin'?'var(--gray-100)':($meta['to']==='Agent'?'#eff6ff':'#f0fdf4') ?>;color:<?= $meta['to']==='Admin'?'var(--gray-600)':($meta['to']==='Agent'?'#1d4ed8':'#166534') ?>;font-weight:700;letter-spacing:.06em;text-transform:uppercase">
          <?= $meta['to'] ?>
        </span>
      </a>
      <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Usage guide -->
  <div class="admin-card" style="margin-top:1rem">
    <div class="admin-card-header" style="padding:.85rem 1rem"><h3 style="font-size:.8rem">Integration Guide</h3></div>
    <div class="admin-card-body" style="padding:.85rem 1rem;font-size:.72rem;line-height:1.8;color:var(--gray-600)">
      <code style="display:block;background:var(--gray-50);padding:.5rem .65rem;font-size:.65rem;margin-bottom:.65rem;word-break:break-all">use App\Mail\Mailer;</code>
      <div style="font-weight:700;margin-bottom:.3rem;color:var(--gray-800)">Send to guest:</div>
      <code style="display:block;background:var(--gray-50);padding:.5rem .65rem;font-size:.65rem;margin-bottom:.65rem;word-break:break-all">Mailer::queue('template_name', $vars);</code>
      <div style="font-weight:700;margin-bottom:.3rem;color:var(--gray-800)">Send to admin:</div>
      <code style="display:block;background:var(--gray-50);padding:.5rem .65rem;font-size:.65rem;margin-bottom:.65rem;word-break:break-all">Mailer::queueAdmin('template_name', $vars);</code>
      <div style="font-weight:700;margin-bottom:.3rem;color:var(--gray-800)">Send to both:</div>
      <code style="display:block;background:var(--gray-50);padding:.5rem .65rem;font-size:.65rem;word-break:break-all">Mailer::queueBoth('guest_tpl', $gVars, 'admin_tpl', $aVars);</code>
    </div>
  </div>
</div>

<!-- RIGHT: preview -->
<div>
  <?php if ($previewTemplate && isset($templates[$previewTemplate])): ?>
  <?php $meta = $templates[$previewTemplate]; ?>

  <!-- Template info bar -->
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-body" style="padding:.85rem 1.25rem">
      <div style="display:flex;align-items:start;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
        <div>
          <h2 style="font-family:var(--font-disp);font-size:1.1rem;font-weight:500;margin-bottom:.25rem"><?= htmlspecialchars($meta['label']) ?></h2>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.4rem">
            <span style="font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.15rem .45rem;background:var(--gray-100);color:var(--gray-600)"><?= htmlspecialchars($meta['group']) ?></span>
            <span style="font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.15rem .45rem;background:<?= $meta['to']==='Admin'?'var(--gray-100)':($meta['to']==='Agent'?'#eff6ff':'#f0fdf4') ?>;color:<?= $meta['to']==='Admin'?'var(--gray-600)':($meta['to']==='Agent'?'#1d4ed8':'#166534') ?>">Sent to: <?= $meta['to'] ?></span>
          </div>
          <div style="font-size:.72rem;color:var(--gray-500)">
            <strong>Triggered by:</strong> <?= htmlspecialchars($meta['trigger']) ?>
          </div>
          <div style="font-size:.72rem;color:var(--gray-500);margin-top:.2rem">
            <strong>Subject:</strong> <?= htmlspecialchars($previewSubject) ?>
          </div>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          <button onclick="openFullPreview()" class="admin-btn admin-btn--primary" style="font-size:.65rem">⛶ Full Preview</button>
          <button onclick="copyPhpCode('<?= $previewTemplate ?>')" class="admin-btn admin-btn--outline" style="font-size:.65rem">Copy PHP Code</button>
          <a href="email_queue.php" class="admin-btn admin-btn--muted" style="font-size:.65rem">View Queue →</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Email preview frame -->
  <div class="admin-card" style="overflow:hidden">
    <div style="background:var(--gray-100);padding:.6rem 1rem;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;gap:.75rem">
      <div style="display:flex;gap:.35rem">
        <div style="width:11px;height:11px;border-radius:50%;background:#ff5f56"></div>
        <div style="width:11px;height:11px;border-radius:50%;background:#febc2e"></div>
        <div style="width:11px;height:11px;border-radius:50%;background:#27c93f"></div>
      </div>
      <div style="flex:1;background:white;border:1px solid var(--gray-200);border-radius:3px;padding:.2rem .65rem;font-size:.7rem;color:var(--gray-400)">Email preview — <?= htmlspecialchars($meta['label']) ?></div>
      <button onclick="toggleDevice()" id="device-btn" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .5rem">📱 Mobile</button>
    </div>
    <div id="preview-container" style="background:#f3f3f1;padding:0;overflow:auto;transition:max-width .3s">
      <iframe id="preview-frame" srcdoc="<?= htmlspecialchars($previewHtml) ?>"
              style="width:100%;border:none;display:block;min-height:700px"
              onload="this.style.height=(this.contentWindow.document.body.scrollHeight+40)+'px'">
      </iframe>
    </div>
  </div>

  <?php else: ?>
  <!-- No template selected -->
  <div class="admin-card" style="text-align:center;padding:4rem 2rem">
    <div style="font-size:3rem;margin-bottom:1rem;opacity:.15">✉</div>
    <h2 style="font-family:var(--font-disp);font-size:1.25rem;font-weight:500;margin-bottom:.5rem">Email Template Manager</h2>
    <p style="font-size:.85rem;color:var(--gray-500);max-width:400px;margin:0 auto 1.5rem;line-height:1.75">
      Select a template from the left panel to preview the exact HTML email that will be sent to clients, agents or admin.
    </p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem;text-align:left;max-width:700px;margin:0 auto">
      <?php foreach ($groups as $groupName => $groupTemplates): ?>
      <div style="border:1.5px solid var(--gray-200);padding:1rem">
        <div style="font-size:.6rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--gray-400);margin-bottom:.65rem"><?= htmlspecialchars($groupName) ?></div>
        <?php foreach ($groupTemplates as $key => $meta): ?>
        <a href="?preview=<?= urlencode($key) ?>" style="display:block;font-size:.78rem;color:var(--gray-700);text-decoration:none;padding:.25rem 0;border-bottom:1px solid var(--gray-100)"><?= htmlspecialchars($meta['label']) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

</div><!-- /grid -->

<!-- Full preview modal -->
<div id="fullPreviewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:999;flex-direction:column">
  <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.5rem;background:var(--black);color:var(--white)">
    <span style="font-size:.8rem;font-weight:600"><?= htmlspecialchars($previewSubject ?: 'Email Preview') ?></span>
    <button onclick="document.getElementById('fullPreviewModal').style.display='none'" style="background:none;border:none;color:var(--white);font-size:1.1rem;cursor:pointer">✕ Close</button>
  </div>
  <div style="flex:1;overflow:auto">
    <iframe id="full-preview-frame" style="width:100%;height:100%;border:none;background:#f3f3f1"></iframe>
  </div>
</div>

<!-- PHP code snippets (hidden) -->
<div id="php-codes" style="display:none">
<?php foreach ($templates as $key => $meta):
    $vars = $sampleData[$key] ?? [];
    $varLines = [];
    foreach ($vars as $k => $v) {
        if (is_array($v)) {
            $varLines[] = "    '$k' => " . json_encode($v, JSON_UNESCAPED_UNICODE) . ',';
        } elseif (is_float($v)) {
            $varLines[] = "    '$k' => {$v},";
        } elseif (is_int($v)) {
            $varLines[] = "    '$k' => {$v},";
        } else {
            $varLines[] = "    '$k' => '" . str_replace("'", "\\'", (string)$v) . "',";
        }
    }
    $varStr = implode("\n", $varLines);
    $method = $meta['to'] === 'Admin' ? 'queueAdmin' : 'queue';
?>
<pre id="code-<?= $key ?>" style="font-size:.72rem;line-height:1.8"><?= htmlspecialchars("use App\\Mail\\Mailer;

Mailer::{$method}('{$key}', [
{$varStr}
]);") ?></pre>
<?php endforeach; ?>
</div>

<div id="toast-tpl" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none"></div>

<script>
function openFullPreview() {
  var frame = document.getElementById('preview-frame');
  var fullFrame = document.getElementById('full-preview-frame');
  if (frame && fullFrame) {
    fullFrame.srcdoc = frame.srcdoc || frame.getAttribute('srcdoc');
    document.getElementById('fullPreviewModal').style.display = 'flex';
  }
}

var isMobile = false;
function toggleDevice() {
  var container = document.getElementById('preview-container');
  var frame = document.getElementById('preview-frame');
  var btn = document.getElementById('device-btn');
  isMobile = !isMobile;
  if (isMobile) {
    container.style.maxWidth = '430px';
    container.style.margin = '0 auto';
    frame.style.width = '430px';
    btn.textContent = '🖥 Desktop';
  } else {
    container.style.maxWidth = 'none';
    container.style.margin = '0';
    frame.style.width = '100%';
    btn.textContent = '📱 Mobile';
  }
}

function copyPhpCode(key) {
  var el = document.getElementById('code-' + key);
  if (!el) { toast('Code not found', false); return; }
  navigator.clipboard.writeText(el.textContent).then(function() {
    toast('PHP code copied to clipboard!');
  }).catch(function() {
    toast('Could not copy — try selecting manually', false);
  });
}

function toast(msg, ok) {
  ok = ok === undefined ? true : ok;
  var t = document.createElement('div');
  t.style.cssText = 'background:' + (ok ? '#16a34a' : '#dc2626') + ';color:#fff;padding:.65rem 1.1rem;font-size:.78rem;pointer-events:auto;box-shadow:0 4px 16px rgba(0,0,0,.2)';
  t.textContent = msg;
  document.getElementById('toast-tpl').appendChild(t);
  setTimeout(function() { t.remove(); }, 3000);
}

document.getElementById('fullPreviewModal') && document.getElementById('fullPreviewModal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
?>
