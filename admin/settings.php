<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Settings page — admin/superadmin only, same direct-access guard every
// other page in the Settings nav group uses (staff/agent lose the nav
// link, but that alone isn't enforcement — see layouts/admin.php).
if (!AuthMiddleware::isAdminRole()) { header('Location: index.php'); exit; }
$db   = \App\Models\Database::getInstance();
$myId = (int)($_SESSION['admin_user_id'] ?? 0);
$pageTitle = 'General Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_theme'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $theme = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
    try {
        $db->prepare("UPDATE admin_users SET theme_preference=? WHERE id=?")->execute([$theme, $myId]);
        $_SESSION['theme_preference'] = $theme;
        header('Location: settings.php?msg='.urlencode('Theme updated.')); exit;
    } catch (\Exception $e) {
        header('Location: settings.php?msg='.urlencode('Error: '.$e->getMessage())); exit;
    }
}

// ── Business rules / contact / bank — stored in the shared `settings`
// key/value table (sql/create_settings.sql) and read back by every
// consuming page via config/app.php's _setting() override, so a save
// here takes effect site-wide on the very next request, no deploy. ──
function saveSettings(\PDO $db, array $data): void {
    $sql = "INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)";
    $stmt = $db->prepare($sql);
    foreach ($data as $k => $v) $stmt->execute([$k, $v]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_business_rules'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        saveSettings($db, [
            'deposit_percent'     => max(0, min(100, (int)($_POST['deposit_percent'] ?? 25))),
            'balance_days'        => max(0, (int)($_POST['balance_days'] ?? 45)),
            'vat_rate'            => max(0, min(100, (float)($_POST['vat_rate'] ?? 18))) / 100,
            'service_charge_rate' => max(0, min(100, (float)($_POST['service_charge_rate'] ?? 5))) / 100,
            'gbp_rate'            => max(0, (float)($_POST['gbp_rate'] ?? 0.75)),
        ]);
        header('Location: settings.php?msg='.urlencode('Business rules updated.')); exit;
    } catch (\Exception $e) {
        header('Location: settings.php?msg='.urlencode('Error: '.$e->getMessage())); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        saveSettings($db, [
            'site_email'      => trim($_POST['site_email'] ?? ''),
            'admin_email'     => trim($_POST['admin_email'] ?? ''),
            'whatsapp_number' => preg_replace('/[^0-9]/', '', $_POST['whatsapp_number'] ?? ''),
        ]);
        header('Location: settings.php?msg='.urlencode('Contact details updated.')); exit;
    } catch (\Exception $e) {
        header('Location: settings.php?msg='.urlencode('Error: '.$e->getMessage())); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_bank'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        saveSettings($db, [
            'bank_name'         => trim($_POST['bank_name'] ?? ''),
            'bank_account_name' => trim($_POST['bank_account_name'] ?? ''),
            'bank_account_no'   => trim($_POST['bank_account_no'] ?? ''),
            'bank_swift'        => trim($_POST['bank_swift'] ?? ''),
            'wise_email'        => trim($_POST['wise_email'] ?? ''),
        ]);
        header('Location: settings.php?msg='.urlencode('Bank details updated.')); exit;
    } catch (\Exception $e) {
        header('Location: settings.php?msg='.urlencode('Error: '.$e->getMessage())); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lead_rules'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        saveSettings($db, [
            'default_commission_rate' => max(0, min(100, (float)($_POST['default_commission_rate'] ?? 10))),
            'quotation_validity_days' => max(1, (int)($_POST['quotation_validity_days'] ?? 14)),
            'max_party_size'          => max(1, min(200, (int)($_POST['max_party_size'] ?? 60))),
        ]);
        header('Location: settings.php?msg='.urlencode('Lead & quotation rules updated.')); exit;
    } catch (\Exception $e) {
        header('Location: settings.php?msg='.urlencode('Error: '.$e->getMessage())); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_platform_controls'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        saveSettings($db, [
            'maintenance_mode'              => isset($_POST['maintenance_mode']) ? '1' : '0',
            'agent_direct_booking_enabled'  => isset($_POST['agent_direct_booking_enabled']) ? '1' : '0',
        ]);
        header('Location: settings.php?msg='.urlencode('Platform controls updated.')); exit;
    } catch (\Exception $e) {
        header('Location: settings.php?msg='.urlencode('Error: '.$e->getMessage())); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_security'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    try {
        saveSettings($db, [
            'session_timeout_minutes' => max(5, min(480, (int)($_POST['session_timeout_minutes'] ?? 30))),
        ]);
        header('Location: settings.php?msg='.urlencode('Security settings updated.')); exit;
    } catch (\Exception $e) {
        header('Location: settings.php?msg='.urlencode('Error: '.$e->getMessage())); exit;
    }
}

$msg = htmlspecialchars($_GET['msg'] ?? '');
$currentTheme = $_SESSION['theme_preference'] ?? 'light';

ob_start();
?>
<?php if ($msg): ?>
<div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div>
<?php endif; ?>

<div style="display:flex;flex-direction:column;gap:1.25rem">
  <div class="admin-card">
    <div class="admin-card-header"><h3>Appearance</h3></div>
    <div class="admin-card-body">
      <p style="font-size:.82rem;color:var(--gray-500);margin-bottom:1.25rem;line-height:1.6;max-width:640px">
        Choose how the admin panel looks for your account. This is saved to your profile, so it
        follows you to any device you sign in on.
      </p>
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_theme" value="1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:1rem;margin-bottom:1.25rem;max-width:560px">

          <label class="theme-option<?= $currentTheme==='light'?' theme-option--active':'' ?>">
            <input type="radio" name="theme" value="light" <?= $currentTheme==='light'?'checked':'' ?> onchange="this.form.requestSubmit()">
            <div class="theme-swatch theme-swatch--light">
              <span class="theme-swatch-bar"></span>
              <span class="theme-swatch-card"></span>
            </div>
            <span class="theme-option-label">Light</span>
          </label>

          <label class="theme-option<?= $currentTheme==='dark'?' theme-option--active':'' ?>">
            <input type="radio" name="theme" value="dark" <?= $currentTheme==='dark'?'checked':'' ?> onchange="this.form.requestSubmit()">
            <div class="theme-swatch theme-swatch--dark">
              <span class="theme-swatch-bar"></span>
              <span class="theme-swatch-card"></span>
            </div>
            <span class="theme-option-label">Dark</span>
          </label>

        </div>
        <noscript><button type="submit" class="admin-btn admin-btn--primary">Save</button></noscript>
      </form>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Business Rules</h3></div>
    <div class="admin-card-body">
      <p style="font-size:.82rem;color:var(--gray-500);margin-bottom:1.25rem;line-height:1.6;max-width:640px">
        Used everywhere a quotation, booking, invoice or PDF computes a total — changing one of
        these applies to every new calculation immediately, site-wide.
      </p>
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_business_rules" value="1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(190px,100%),1fr));gap:1rem;margin-bottom:1rem">
          <div>
            <label class="admin-label">Deposit (%)</label>
            <input type="number" name="deposit_percent" class="admin-input" min="0" max="100" step="1"
                   value="<?= (int)DEPOSIT_PERCENT ?>">
          </div>
          <div>
            <label class="admin-label">Balance Due <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(days before travel)</span></label>
            <input type="number" name="balance_days" class="admin-input" min="0" step="1"
                   value="<?= (int)BALANCE_DAYS ?>">
          </div>
          <div>
            <label class="admin-label">VAT Rate (%)</label>
            <input type="number" name="vat_rate" class="admin-input" min="0" max="100" step="0.1"
                   value="<?= rtrim(rtrim(number_format(VAT_RATE*100,2,'.',''),'0'),'.') ?>">
          </div>
          <div>
            <label class="admin-label">Service Charge (%)</label>
            <input type="number" name="service_charge_rate" class="admin-input" min="0" max="100" step="0.1"
                   value="<?= rtrim(rtrim(number_format(SERVICE_CHARGE_RATE*100,2,'.',''),'0'),'.') ?>">
          </div>
          <div>
            <label class="admin-label">Display USD→GBP Rate <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(shown only, never charged)</span></label>
            <input type="number" name="gbp_rate" class="admin-input" min="0" step="0.001"
                   value="<?= rtrim(rtrim(number_format(GBP_RATE,4,'.',''),'0'),'.') ?>">
          </div>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Save Business Rules</button>
      </form>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Contact &amp; Notifications</h3></div>
    <div class="admin-card-body">
      <p style="font-size:.82rem;color:var(--gray-500);margin-bottom:1.25rem;line-height:1.6;max-width:640px">
        Where client-facing emails are sent from, and the WhatsApp number shown across the site
        and in client messages.
      </p>
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_contact" value="1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:1rem;margin-bottom:1.25rem">
          <div>
            <label class="admin-label">Site Email <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(general contact)</span></label>
            <input type="email" name="site_email" class="admin-input" value="<?= htmlspecialchars(SITE_EMAIL) ?>">
          </div>
          <div>
            <label class="admin-label">Admin Email <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(internal notifications)</span></label>
            <input type="email" name="admin_email" class="admin-input" value="<?= htmlspecialchars(ADMIN_EMAIL) ?>">
          </div>
          <div>
            <label class="admin-label">WhatsApp Number <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(digits only, country code first)</span></label>
            <input type="text" name="whatsapp_number" class="admin-input" value="<?= htmlspecialchars(WHATSAPP_NUMBER) ?>">
          </div>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Save Contact Details</button>
      </form>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Bank &amp; Payment Details</h3></div>
    <div class="admin-card-body">
      <p style="font-size:.82rem;color:var(--gray-500);margin-bottom:1.25rem;line-height:1.6;max-width:640px">
        Shown on quotation/booking PDFs and payment-instruction emails for clients paying by bank
        transfer.
      </p>
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_bank" value="1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:1rem;margin-bottom:1.25rem">
          <div>
            <label class="admin-label">Bank Name</label>
            <input type="text" name="bank_name" class="admin-input" value="<?= htmlspecialchars(BANK_NAME) ?>">
          </div>
          <div>
            <label class="admin-label">Account Name</label>
            <input type="text" name="bank_account_name" class="admin-input" value="<?= htmlspecialchars(BANK_ACCOUNT_NAME) ?>">
          </div>
          <div>
            <label class="admin-label">Account Number</label>
            <input type="text" name="bank_account_no" class="admin-input" value="<?= htmlspecialchars(BANK_ACCOUNT_NO) ?>">
          </div>
          <div>
            <label class="admin-label">SWIFT Code</label>
            <input type="text" name="bank_swift" class="admin-input" value="<?= htmlspecialchars(BANK_SWIFT) ?>">
          </div>
          <div>
            <label class="admin-label">Wise Email <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(for Wise transfers)</span></label>
            <input type="email" name="wise_email" class="admin-input" value="<?= htmlspecialchars(WISE_EMAIL) ?>">
          </div>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Save Bank Details</button>
      </form>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Lead &amp; Quotation Rules</h3></div>
    <div class="admin-card-body">
      <p style="font-size:.82rem;color:var(--gray-500);margin-bottom:1.25rem;line-height:1.6;max-width:640px">
        Fallback values the system reaches for when a more specific one isn't set — an agent with
        no commission rate of their own, a quotation with no "valid until" date typed in, the
        largest party size the public booking form will accept.
      </p>
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_lead_rules" value="1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(230px,100%),1fr));gap:1rem;margin-bottom:1rem">
          <div>
            <label class="admin-label">Default Commission Rate (%) <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(no rate set on the agent)</span></label>
            <input type="number" name="default_commission_rate" class="admin-input" min="0" max="100" step="0.5"
                   value="<?= rtrim(rtrim(number_format(DEFAULT_COMMISSION_RATE,2,'.',''),'0'),'.') ?>">
          </div>
          <div>
            <label class="admin-label">Quotation Validity <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(days from today)</span></label>
            <input type="number" name="quotation_validity_days" class="admin-input" min="1" step="1"
                   value="<?= (int)QUOTATION_VALIDITY_DAYS ?>">
          </div>
          <div>
            <label class="admin-label">Max Party Size <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(adults + children, public booking)</span></label>
            <input type="number" name="max_party_size" class="admin-input" min="1" max="200" step="1"
                   value="<?= (int)MAX_PARTY_SIZE ?>">
          </div>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Save Lead &amp; Quotation Rules</button>
      </form>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Platform Controls</h3></div>
    <div class="admin-card-body">
      <p style="font-size:.82rem;color:var(--gray-500);margin-bottom:1.25rem;line-height:1.6;max-width:640px">
        Switches that change what the public site and agent portal will accept right now — useful
        during a migration, a pricing review, or anything else that needs the front door closed
        for a while without touching code.
      </p>
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_platform_controls" value="1">
        <div style="display:flex;flex-direction:column;gap:.9rem;margin-bottom:1.25rem;max-width:560px">
          <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer">
            <input type="checkbox" name="maintenance_mode" value="1" style="margin-top:.2rem" <?= MAINTENANCE_MODE?'checked':'' ?>>
            <span>
              <strong style="display:block;font-size:.85rem">Maintenance Mode</strong>
              <span style="font-size:.76rem;color:var(--gray-500);line-height:1.5">
                Public booking and custom-safari-request submissions are declined with a polite
                message pointing guests to WhatsApp instead. Browsing and everything in the admin
                panel keep working normally.
              </span>
            </span>
          </label>
          <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer">
            <input type="checkbox" name="agent_direct_booking_enabled" value="1" style="margin-top:.2rem" <?= AGENT_DIRECT_BOOKING_ENABLED?'checked':'' ?>>
            <span>
              <strong style="display:block;font-size:.85rem">Agent Direct Booking</strong>
              <span style="font-size:.76rem;color:var(--gray-500);line-height:1.5">
                Lets agents book an existing package straight from the agent portal's "New Client"
                flow. Turning this off still leaves Custom Request available — agents just send
                every client to your team to quote instead.
              </span>
            </span>
          </label>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Save Platform Controls</button>
      </form>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card-header"><h3>Security</h3></div>
    <div class="admin-card-body">
      <p style="font-size:.82rem;color:var(--gray-500);margin-bottom:1.25rem;line-height:1.6;max-width:640px">
        How long an admin, staff or agent session can sit idle before it's signed out automatically.
        Lower is safer on a shared computer; higher is more convenient for a long working session.
      </p>
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="update_security" value="1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(230px,100%),1fr));gap:1rem;margin-bottom:1rem">
          <div>
            <label class="admin-label">Session Timeout <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(minutes of inactivity, 5–480)</span></label>
            <input type="number" name="session_timeout_minutes" class="admin-input" min="5" max="480" step="5"
                   value="<?= (int)SESSION_TIMEOUT_MINUTES ?>">
          </div>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Save Security Settings</button>
      </form>
    </div>
  </div>
</div>

<style>
.theme-option { cursor: pointer; display: block; }
.theme-option input { position: absolute; opacity: 0; width: 0; height: 0; }
.theme-swatch {
  border: 1.5px solid var(--gray-200);
  padding: .6rem;
  margin-bottom: .5rem;
  transition: border-color .15s;
}
.theme-option--active .theme-swatch { border-color: var(--black); }
.theme-swatch-bar  { display: block; height: 10px; margin-bottom: .4rem; }
.theme-swatch-card { display: block; height: 28px; }
.theme-swatch--light { background: #f9f9f8; }
.theme-swatch--light .theme-swatch-bar  { background: #0a0a0a; }
.theme-swatch--light .theme-swatch-card { background: #ffffff; border: 1px solid #e6e6e2; }
.theme-swatch--dark  { background: #141414; }
.theme-swatch--dark  .theme-swatch-bar  { background: #f0f0ee; }
.theme-swatch--dark  .theme-swatch-card { background: #1c1c1c; border: 1px solid #383838; }
.theme-option-label { font-size: .78rem; font-weight: 700; }
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
