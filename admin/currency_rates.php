<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Services\AccessGuard;
AuthMiddleware::requireAdmin();
if (!AuthMiddleware::isAdminRole()) {
    AccessGuard::denyRedirect('index.php', 'currency_rates', 'currency_rates.php', $_SERVER['REQUEST_METHOD']);
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Currency Rates';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $code   = strtoupper(trim($_POST['code'] ?? ''));
    $name   = trim($_POST['name'] ?? '');
    $symbol = trim($_POST['symbol'] ?? '');
    $rate   = (float)($_POST['rate_to_usd'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;

    if (!preg_match('/^[A-Z]{3}$/', $code) || $name === '' || $symbol === '' || $rate <= 0) {
        $msg = 'Error: Code must be 3 letters, name/symbol required, rate must be positive.';
    } else {
        try {
            $db->prepare("
                INSERT INTO currency_rates (code, name, symbol, rate_to_usd, is_active)
                VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE name=VALUES(name), symbol=VALUES(symbol), rate_to_usd=VALUES(rate_to_usd), is_active=VALUES(is_active)
            ")->execute([$code, $name, $symbol, $rate, $active]);
            $msg = 'Saved.';
        } catch (\Exception $e) { $msg = 'Error: ' . $e->getMessage(); }
    }
    header('Location: currency_rates.php?msg=' . urlencode($msg));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_code'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $code = strtoupper(trim($_POST['delete_code']));
    if ($code !== 'USD') { // USD is the ledger currency — never removable
        try { $db->prepare("DELETE FROM currency_rates WHERE code=?")->execute([$code]); }
        catch (\Exception $e) {}
    }
    header('Location: currency_rates.php');
    exit;
}

$msg  = htmlspecialchars($_GET['msg'] ?? '');
$rows = [];
try { $rows = $db->query("SELECT * FROM currency_rates ORDER BY code='USD' DESC, code LIMIT 200")->fetchAll(); }
catch (\Exception $e) {}

ob_start();
?>
<?php if ($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>

<p style="color:var(--gray-500);font-size:.85rem;margin:-.5rem 0 1.25rem">
  Display-only — packages and the homepage show an approximate price in each active currency alongside the real USD
  price. Bookings, quotations and payments are still always recorded and charged in USD; nothing here changes what
  a guest is actually billed. These are manually-maintained rates, not a live feed — check them periodically.
</p>

<div class="admin-card">
  <div class="admin-card-header">
    <h3>Currency Rates</h3>
    <button type="button" onclick="openModal('addCurrencyModal')" class="admin-btn admin-btn--primary" style="font-size:.68rem">+ Add Currency</button>
  </div>
  <div class="admin-table-wrap">
  <table class="admin-table">
  <thead><tr><th>Code</th><th>Name</th><th>Symbol</th><th>Rate (per $1)</th><th>Active</th><th>Updated</th><th style="min-width:260px">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><code><?= htmlspecialchars($r['code']) ?></code></td>
    <td><?= htmlspecialchars($r['name']) ?></td>
    <td><?= htmlspecialchars($r['symbol']) ?></td>
    <td><?= number_format($r['rate_to_usd'], $r['rate_to_usd'] < 10 ? 4 : 2) ?></td>
    <td><?= $r['is_active'] ? '<span class="badge-status badge-status--green">Active</span>' : '<span class="badge-status badge-status--gray">Inactive</span>' ?></td>
    <td style="color:var(--gray-500);font-size:.78rem"><?= date('d M Y', strtotime($r['updated_at'])) ?></td>
    <td>
      <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
        <form method="POST" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
          <?= \App\Middleware\CsrfMiddleware::field() ?>
          <input type="hidden" name="save" value="1">
          <input type="hidden" name="code" value="<?= htmlspecialchars($r['code']) ?>">
          <input type="hidden" name="name" value="<?= htmlspecialchars($r['name']) ?>">
          <input type="hidden" name="symbol" value="<?= htmlspecialchars($r['symbol']) ?>">
          <input type="number" step="0.0001" min="0.0001" name="rate_to_usd" value="<?= $r['rate_to_usd'] ?>" class="admin-input" style="width:90px;font-size:.75rem;padding:.35rem .5rem">
          <label style="display:flex;align-items:center;gap:.3rem;font-size:.68rem;color:var(--gray-700);white-space:nowrap;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" <?= $r['is_active']?'checked':'' ?>> Active
          </label>
          <button class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.35rem .6rem">Update</button>
        </form>
        <?php if ($r['code'] !== 'USD'): ?>
        <div class="admin-row-actions">
          <button type="button" class="admin-icon-btn admin-row-actions-toggle" aria-label="Actions" aria-haspopup="true" aria-expanded="false"><?= \App\Helpers\AdminIcon::render('dots-vertical', 16) ?></button>
          <div class="admin-row-actions-menu">
            <form method="POST" onsubmit="return confirmSubmitGuard(event, this, 'Remove <?= htmlspecialchars(addslashes($r['code'])) ?> from currencies?', {danger:true})">
              <?= \App\Middleware\CsrfMiddleware::field() ?>
              <input type="hidden" name="delete_code" value="<?= htmlspecialchars($r['code']) ?>">
              <button type="submit" class="admin-row-actions-item admin-row-actions-item--danger"><?= \App\Helpers\AdminIcon::render('trash', 14) ?> Delete</button>
            </form>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>

<!-- Add Currency modal -->
<div id="addCurrencyModal" class="admin-modal-overlay">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <span class="admin-modal-title">Add Currency</span>
      <button type="button" onclick="closeModal('addCurrencyModal')" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">
      <p style="font-size:.78rem;color:var(--gray-500);margin-bottom:1.25rem;line-height:1.6">
        Display-only, same as every row in the table — actual charging always stays in USD.
      </p>
      <form method="POST">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="save" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">
          <div>
            <label class="admin-label">Code <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(ISO 4217)</span></label>
            <input name="code" maxlength="3" class="admin-input" placeholder="AUD" required style="text-transform:uppercase">
          </div>
          <div>
            <label class="admin-label">Rate <span style="color:var(--gray-500);font-weight:600;text-transform:none;letter-spacing:0">(per $1)</span></label>
            <input type="number" step="0.0001" min="0.0001" name="rate_to_usd" class="admin-input" placeholder="1.50" required>
          </div>
          <div>
            <label class="admin-label">Name</label>
            <input name="name" class="admin-input" placeholder="Australian Dollar" required>
          </div>
          <div>
            <label class="admin-label">Symbol</label>
            <input name="symbol" class="admin-input" placeholder="A$" required>
          </div>
        </div>
        <div style="display:flex;gap:.5rem">
          <button class="admin-btn admin-btn--primary">+ Add Currency</button>
          <button type="button" onclick="closeModal('addCurrencyModal')" class="admin-btn admin-btn--outline">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($msg && str_starts_with($msg, 'Error')): ?>
<script>document.addEventListener('DOMContentLoaded', function(){ openModal('addCurrencyModal'); });</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
