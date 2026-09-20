<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
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

<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h3>Currency Rates</h3></div>
  <div class="admin-table-wrap">
  <table class="admin-table">
  <thead><tr><th>Code</th><th>Name</th><th>Symbol</th><th>Rate (per $1)</th><th>Active</th><th>Updated</th><th>Actions</th></tr></thead>
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
      <form method="POST" style="display:inline-flex;gap:.4rem;align-items:center">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="save" value="1">
        <input type="hidden" name="code" value="<?= htmlspecialchars($r['code']) ?>">
        <input type="hidden" name="name" value="<?= htmlspecialchars($r['name']) ?>">
        <input type="hidden" name="symbol" value="<?= htmlspecialchars($r['symbol']) ?>">
        <input type="number" step="0.0001" min="0.0001" name="rate_to_usd" value="<?= $r['rate_to_usd'] ?>" class="admin-input" style="width:100px;font-size:.75rem;padding:.3rem">
        <label style="display:flex;align-items:center;gap:.3rem;font-size:.7rem"><input type="checkbox" name="is_active" value="1" <?= $r['is_active']?'checked':'' ?>> Active</label>
        <button class="admin-btn admin-btn--outline" style="font-size:.68rem">Update</button>
      </form>
      <?php if ($r['code'] !== 'USD'): ?>
      <form method="POST" style="display:inline" onsubmit="return confirm('Remove <?= htmlspecialchars($r['code']) ?>?')">
        <?= \App\Middleware\CsrfMiddleware::field() ?>
        <input type="hidden" name="delete_code" value="<?= htmlspecialchars($r['code']) ?>">
        <button class="admin-btn admin-btn--danger" style="font-size:.68rem">Delete</button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>

<div class="admin-card">
  <div class="admin-card-header"><h3>Add Currency</h3></div>
  <div class="admin-card-body">
    <form method="POST" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:1rem;align-items:end">
      <?= \App\Middleware\CsrfMiddleware::field() ?>
      <input type="hidden" name="save" value="1">
      <div><label class="admin-label">Code (ISO 4217)</label><input name="code" maxlength="3" class="admin-input" placeholder="AUD" required></div>
      <div><label class="admin-label">Name</label><input name="name" class="admin-input" placeholder="Australian Dollar" required></div>
      <div><label class="admin-label">Symbol</label><input name="symbol" class="admin-input" placeholder="A$" required></div>
      <div><label class="admin-label">Rate (per $1)</label><input type="number" step="0.0001" min="0.0001" name="rate_to_usd" class="admin-input" required></div>
      <div><button class="admin-btn admin-btn--primary">Add</button></div>
    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
