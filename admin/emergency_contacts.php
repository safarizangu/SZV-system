<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'emergency_contacts.php', 'agent_access');
}
$db = \App\Models\Database::getInstance();
$pageTitle = 'Emergency Contacts';

// Stored under settings.key='emergency_contacts' — reference content
// that changes rarely, same pattern already used for hero_image rather
// than a dedicated table for a handful of fields.
$fields = [
    'office_24h'          => '24-Hour Office Line',
    'hospital'             => 'Recommended Hospital (Arusha)',
    'police'               => 'Police Emergency',
    'tourist_police'       => 'Tourist Police',
    'evacuation_provider'  => 'Medical Evacuation Provider',
    'notes'                => 'Additional Notes',
];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $data = [];
    foreach (array_keys($fields) as $k) $data[$k] = trim($_POST[$k] ?? '');
    try {
        $db->prepare("INSERT INTO settings (`key`, value, updated_at) VALUES ('emergency_contacts', ?, NOW())
                      ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=NOW()")
           ->execute([json_encode($data)]);
        $msg = 'Saved.';
    } catch (\Exception $e) { $msg = 'Error: ' . $e->getMessage(); }
    header('Location: emergency_contacts.php?msg=' . urlencode($msg));
    exit;
}

$msg = htmlspecialchars($_GET['msg'] ?? '');
$current = [];
try {
    $row = $db->query("SELECT value FROM settings WHERE `key`='emergency_contacts' LIMIT 1")->fetch();
    if ($row) $current = json_decode($row['value'], true) ?: [];
} catch (\Exception $e) {}

ob_start();
?>
<?php if ($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>

<p style="color:var(--gray-500);font-size:.85rem;margin:-.5rem 0 1.25rem">
  Shown on every Service Voucher (api/voucher_pdf.php) so whoever is holding it in the field always has current
  emergency numbers — no need to reprint older vouchers when a contact changes.
</p>

<div class="admin-card">
  <div class="admin-card-header"><h3>Emergency Contacts</h3></div>
  <div class="admin-card-body">
    <form method="POST">
      <?= \App\Middleware\CsrfMiddleware::field() ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <?php foreach ($fields as $key => $label): ?>
        <div<?= $key === 'notes' ? ' style="grid-column:1/-1"' : '' ?>>
          <label class="admin-label"><?= $label ?></label>
          <?php if ($key === 'notes'): ?>
          <textarea name="<?= $key ?>" class="admin-input" rows="3"><?= htmlspecialchars($current[$key] ?? '') ?></textarea>
          <?php else: ?>
          <input name="<?= $key ?>" class="admin-input" value="<?= htmlspecialchars($current[$key] ?? '') ?>">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:1.25rem">
        <button class="admin-btn admin-btn--primary">Save</button>
      </div>
    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
