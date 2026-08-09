<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

$id  = (int)($_GET['id'] ?? 0);
$msg = htmlspecialchars($_GET['msg'] ?? '');
$pkg = null;

if ($id > 0) {
    try { $s=$db->prepare("SELECT * FROM packages WHERE id=?"); $s->execute([$id]); $pkg=$s->fetch(); }
    catch(\Exception $e) {}
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pkg'])) {
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['slug'] ?? '')));

    // Build itinerary JSON from day inputs
    $days = $_POST['day_title'] ?? [];
    $descs= $_POST['day_desc']  ?? [];
    $itinerary = [];
    foreach ($days as $i => $title) {
        if (trim($title) === '') continue;
        $itinerary[] = ['day' => $i+1, 'title' => trim($title), 'description' => trim($descs[$i] ?? '')];
    }

    // Build includes/excludes
    $includes = array_values(array_filter(array_map('trim', explode("\n", $_POST['includes'] ?? ''))));
    $excludes = array_values(array_filter(array_map('trim', explode("\n", $_POST['excludes'] ?? ''))));

    $data = [
        'slug'                => $slug,
        'name'                => trim($_POST['name'] ?? ''),
        'duration_days'       => (int)($_POST['duration_days'] ?? 5),
        'short_description'   => trim($_POST['short_description'] ?? ''),
        'overview'            => trim($_POST['overview'] ?? ''),
        'accommodation'       => trim($_POST['accommodation'] ?? ''),
        'accommodation_level' => $_POST['accommodation_level'] ?? 'midrange',
        'price_1pax'          => (float)($_POST['price_1pax'] ?? 0),
        'price_2pax'          => (float)($_POST['price_2pax'] ?? 0),
        'price_3pax'          => (float)($_POST['price_3pax'] ?? 0),
        'is_featured'         => isset($_POST['is_featured']) ? 1 : 0,
        'is_active'           => isset($_POST['is_active'])   ? 1 : 0,
        'sort_order'          => (int)($_POST['sort_order'] ?? 0),
        'itinerary'           => json_encode($itinerary),
        'includes'            => json_encode($includes),
        'excludes'            => json_encode($excludes),
    ];

    try {
        if ($id > 0) {
            $set = implode(', ', array_map(fn($k) => "`$k`=?", array_keys($data)));
            $db->prepare("UPDATE packages SET $set WHERE id=?")
               ->execute(array_merge(array_values($data), [$id]));
            $newId = $id;
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
            $phld = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO packages ($cols) VALUES ($phld)")->execute(array_values($data));
            $newId = $db->lastInsertId();
        }
        header("Location: package_edit.php?id=$newId&msg=".urlencode('Package saved.')); exit;
    } catch(\Exception $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}

// Parse existing JSON fields
$itinerary = $pkg ? (json_decode($pkg['itinerary'] ?? '[]', true) ?: []) : [];
$includes  = $pkg ? implode("\n", json_decode($pkg['includes'] ?? '[]', true) ?: []) : '';
$excludes  = $pkg ? implode("\n", json_decode($pkg['excludes'] ?? '[]', true) ?: []) : '';

// Ensure 10 day slots
while (count($itinerary) < 10) {
    $itinerary[] = ['day' => count($itinerary)+1, 'title' => '', 'description' => ''];
}

$pageTitle = $id ? 'Edit Package' : 'New Package';
ob_start();
?>
<?php if ($msg): ?><div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>"><?= $msg ?></div><?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem">
  <div></div>
  <div style="display:flex;gap:.5rem">
    <?php if ($id): ?>
    <a href="<?= BASE_PATH ?>/packages.php#<?= htmlspecialchars($pkg['slug']??'') ?>" target="_blank"
       class="admin-btn admin-btn--outline">View Live ↗</a>
    <?php endif; ?>
    <a href="packages.php" class="admin-btn admin-btn--muted">← All Packages</a>
  </div>
</div>

<form method="POST" class="av-form">
  <input type="hidden" name="save_pkg" value="1">
  <div style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start">

    <!-- LEFT -->
    <div>
      <!-- Basic Info -->
      <div class="admin-card" style="margin-bottom:1.25rem">
        <div class="admin-card-header"><h3>Basic Information</h3></div>
        <div class="admin-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
            <div><label class="admin-label">Package Name *</label>
              <input name="name" data-av="required minlen:2" class="admin-input" required value="<?= htmlspecialchars($pkg['name']??'') ?>"></div>
            <div><label class="admin-label">URL Slug *</label>
              <input name="slug" data-av="required slug" class="admin-input" required value="<?= htmlspecialchars($pkg['slug']??'') ?>"
                     pattern="[a-z0-9-]+" title="Lowercase letters, numbers and hyphens only"></div>
            <div><label class="admin-label">Duration (nights)</label>
              <input name="duration_days" data-av="required integer min:1 max:30" type="number" min="1" max="30" class="admin-input"
                     value="<?= $pkg['duration_days']??5 ?>"></div>
            <div><label class="admin-label">Sort Order</label>
              <input name="sort_order" type="number" class="admin-input" value="<?= $pkg['sort_order']??0 ?>"></div>
          </div>
          <div style="margin-bottom:1rem">
            <label class="admin-label">Short Description (for cards)</label>
            <textarea name="short_description" class="admin-input" rows="2"><?= htmlspecialchars($pkg['short_description']??'') ?></textarea>
          </div>
          <div><label class="admin-label">Full Overview</label>
            <textarea name="overview" class="admin-input" rows="4"><?= htmlspecialchars($pkg['overview']??'') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Pricing -->
      <div class="admin-card" style="margin-bottom:1.25rem">
        <div class="admin-card-header"><h3>Pricing (USD per person)</h3>
          <span style="font-size:.72rem;color:#9ca3af">1 pax = solo, 2 pax = couple, 3+ pax = group</span>
        </div>
        <div class="admin-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
            <div><label class="admin-label">1 Person</label>
              <input name="price_1pax" data-av="min:0" type="number" step="0.01" class="admin-input"
                     value="<?= $pkg['price_1pax']??0 ?>"></div>
            <div><label class="admin-label">2 People</label>
              <input name="price_2pax" data-av="min:0" type="number" step="0.01" class="admin-input"
                     value="<?= $pkg['price_2pax']??0 ?>"></div>
            <div><label class="admin-label">3+ People</label>
              <input name="price_3pax" data-av="min:0" type="number" step="0.01" class="admin-input"
                     value="<?= $pkg['price_3pax']??0 ?>"></div>
          </div>
        </div>
      </div>

      <!-- Itinerary -->
      <div class="admin-card" style="margin-bottom:1.25rem">
        <div class="admin-card-header"><h3>Day-by-Day Itinerary</h3></div>
        <div class="admin-card-body">
          <?php foreach ($itinerary as $i => $day): ?>
          <div style="display:grid;grid-template-columns:120px 1fr;gap:.75rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid #f3f4f6">
            <div><label class="admin-label">Day <?= $i+1 ?> Title</label>
              <input name="day_title[]" class="admin-input" placeholder="e.g. Serengeti Arrival"
                     value="<?= htmlspecialchars($day['title']??'') ?>"></div>
            <div><label class="admin-label">Description</label>
              <textarea name="day_desc[]" class="admin-input" rows="2"><?= htmlspecialchars($day['description']??'') ?></textarea></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Includes / Excludes -->
      <div class="admin-card" style="margin-bottom:1.25rem">
        <div class="admin-card-header"><h3>Includes &amp; Excludes</h3>
          <span style="font-size:.72rem;color:#9ca3af">One item per line</span>
        </div>
        <div class="admin-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div><label class="admin-label">✓ Included</label>
              <textarea name="includes" class="admin-input" rows="8"><?= htmlspecialchars($includes) ?></textarea></div>
            <div><label class="admin-label">✗ Not Included</label>
              <textarea name="excludes" class="admin-input" rows="8"><?= htmlspecialchars($excludes) ?></textarea></div>
          </div>
        </div>
      </div>

      <!-- Accommodation -->
      <div class="admin-card">
        <div class="admin-card-header"><h3>Accommodation Description</h3></div>
        <div class="admin-card-body">
          <textarea name="accommodation" class="admin-input" rows="3"><?= htmlspecialchars($pkg['accommodation']??'') ?></textarea>
        </div>
      </div>
    </div><!-- /left -->

    <!-- RIGHT sidebar -->
    <div style="position:sticky;top:80px">
      <div class="admin-card" style="margin-bottom:1rem">
        <div class="admin-card-header"><h3>Settings</h3></div>
        <div class="admin-card-body">
          <div style="margin-bottom:.85rem">
            <label class="admin-label">Accommodation Level</label>
            <select name="accommodation_level" class="admin-input">
              <?php foreach(['budget'=>'Budget-Friendly','midrange'=>'Mid-Range','highend'=>'High-End / Luxury','ultra_luxury'=>'Ultra Luxury'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($pkg['accommodation_level']??'midrange')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="display:flex;flex-direction:column;gap:.65rem;margin-top:.75rem">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
              <input type="checkbox" name="is_active" value="1" <?= ($pkg['is_active']??1)?'checked':'' ?>>
              <span class="admin-label" style="margin:0">Active (visible on site)</span>
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
              <input type="checkbox" name="is_featured" value="1" <?= ($pkg['is_featured']??1)?'checked':'' ?>>
              <span class="admin-label" style="margin:0">Featured (show on homepage)</span>
            </label>
          </div>
        </div>
      </div>
      <div class="admin-card">
        <div class="admin-card-body">
          <button type="submit" class="admin-btn admin-btn--primary" style="width:100%;justify-content:center;padding:.85rem;margin-bottom:.5rem">
            💾 Save Package
          </button>
          <?php if ($id): ?>
          <a href="<?= BASE_PATH ?>/packages.php#<?= htmlspecialchars($pkg['slug']??'') ?>" target="_blank"
             class="admin-btn admin-btn--outline" style="width:100%;justify-content:center">
            View on Site ↗
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div><!-- /right -->

  </div>
</form>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
