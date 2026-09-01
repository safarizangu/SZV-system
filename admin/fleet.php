<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Fleet Management';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vehicle'])) {
    $id       = (int)($_POST['id'] ?? 0);
    $images   = json_decode($_POST['images_json'] ?? '[]', true) ?: [];
    $data = [
        'name'        => trim($_POST['name'] ?? ''),
        'type'        => trim($_POST['type'] ?? 'land_cruiser'),
        'capacity'    => (int)($_POST['capacity'] ?? 6),
        'daily_rate'  => (float)($_POST['daily_rate'] ?? 0),
        'plate_no'    => trim($_POST['plate_no'] ?? ''),
        'year'        => (int)($_POST['year'] ?? date('Y')),
        'fuel_type'   => trim($_POST['fuel_type'] ?? 'diesel'),
        'description' => trim($_POST['description'] ?? ''),
        'images'      => json_encode($images),
        'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        'sort_order'  => (int)($_POST['sort_order'] ?? 0),
    ];
    try {
        if ($id) {
            $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
            $db->prepare("UPDATE fleet SET $sets WHERE id=?")->execute([...array_values($data), $id]);
        } else {
            $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
            $vals = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO fleet ($cols) VALUES ($vals)")->execute(array_values($data));
        }
        header('Location: fleet.php?msg=Saved'); exit;
    } catch(\Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try { $db->prepare("DELETE FROM fleet WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch(\Exception $e) {}
    header('Location: fleet.php?msg=Deleted'); exit;
}

$vehicles = $db->query("SELECT * FROM fleet ORDER BY sort_order ASC, id ASC")->fetchAll();
$editId   = (int)($_GET['edit'] ?? 0);
$editing  = null;
if ($editId) {
    $s = $db->prepare("SELECT * FROM fleet WHERE id=?");
    $s->execute([$editId]);
    $editing = $s->fetch();
}

$vehicleTypes = ['land_cruiser'=>'Toyota Land Cruiser','extended_cruiser'=>'Extended Land Cruiser','luxury_cruiser'=>'Luxury Land Cruiser','minivan'=>'Safari Minivan','4x4_pickup'=>'4×4 Pickup'];

ob_start();
?>
<div class="admin-card-header">
  <h2 style="font-family:var(--font-disp);font-size:1.05rem">Fleet Management</h2>
  <a href="fleet.php?edit=new" class="admin-btn admin-btn--primary">+ Add Vehicle</a>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="admin-alert admin-alert--success"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<?php if ($editId || isset($_GET['edit'])): ?>
<!-- ── Vehicle Form ──────────────────────────────────────────── -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header">
    <h3><?= $editing ? 'Edit: '.htmlspecialchars($editing['name']) : 'New Vehicle' ?></h3>
    <a href="fleet.php" class="admin-btn admin-btn--outline">← Cancel</a>
  </div>
  <div class="admin-card-body">
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="save_vehicle" value="1">
    <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
    <input type="hidden" name="images_json" id="images_json" value="<?= htmlspecialchars($editing['images'] ?? '[]') ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
      <div>
        <label class="admin-label">Vehicle Name *</label>
        <input class="admin-input" name="name" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
      </div>
      <div>
        <label class="admin-label">Type</label>
        <select class="admin-input" name="type">
          <?php foreach($vehicleTypes as $k=>$v): ?>
          <option value="<?= $k ?>" <?= ($editing['type']??'')===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="admin-label">Capacity (persons)</label>
        <input class="admin-input" type="number" name="capacity" min="1" max="20" value="<?= $editing['capacity'] ?? 6 ?>">
      </div>
      <div>
        <label class="admin-label">Daily Rate (USD)</label>
        <input class="admin-input" type="number" step="0.01" name="daily_rate" value="<?= $editing['daily_rate'] ?? 0 ?>">
      </div>
      <div>
        <label class="admin-label">Plate Number</label>
        <input class="admin-input" name="plate_no" value="<?= htmlspecialchars($editing['plate_no'] ?? '') ?>">
      </div>
      <div>
        <label class="admin-label">Year</label>
        <input class="admin-input" type="number" name="year" min="2000" max="2030" value="<?= $editing['year'] ?? date('Y') ?>">
      </div>
      <div>
        <label class="admin-label">Fuel Type</label>
        <select class="admin-input" name="fuel_type">
          <option value="diesel" <?= ($editing['fuel_type']??'')==='diesel'?'selected':'' ?>>Diesel</option>
          <option value="petrol" <?= ($editing['fuel_type']??'')==='petrol'?'selected':'' ?>>Petrol</option>
        </select>
      </div>
      <div>
        <label class="admin-label">Sort Order</label>
        <input class="admin-input" type="number" name="sort_order" value="<?= $editing['sort_order'] ?? 0 ?>">
      </div>
      <div style="display:flex;align-items:center;gap:.5rem;margin-top:1.5rem">
        <input type="checkbox" name="is_active" id="is_active" <?= ($editing['is_active']??1)?'checked':'' ?>>
        <label for="is_active" class="admin-label" style="margin-bottom:0">Active</label>
      </div>
    </div>

    <div style="margin-bottom:1rem">
      <label class="admin-label">Description</label>
      <textarea class="admin-input" name="description" rows="3"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
    </div>

    <!-- Image Upload -->
    <div style="margin-bottom:1.5rem">
      <label class="admin-label">Vehicle Photos</label>
      <div id="image-preview" style="display:flex;gap:.65rem;flex-wrap:wrap;margin-bottom:.75rem">
        <?php
        $imgs = json_decode($editing['images'] ?? '[]', true) ?: [];
        foreach($imgs as $img): ?>
        <div class="img-thumb-wrap" style="position:relative">
          <img src="<?= htmlspecialchars($img) ?>" style="width:90px;height:68px;object-fit:cover;border:1px solid #e5e7eb">
          <button type="button" onclick="removeImg(this,'<?= htmlspecialchars($img) ?>')"
            style="position:absolute;top:2px;right:2px;background:#dc2626;color:#fff;border:none;width:18px;height:18px;font-size:.7rem;cursor:pointer;display:flex;align-items:center;justify-content:center">×</button>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center">
        <input type="file" id="img_upload" accept="image/*" multiple style="display:none" onchange="uploadImgs(this.files,'fleet')">
        <button type="button" onclick="document.getElementById('img_upload').click()"
          class="admin-btn admin-btn--outline">📷 Upload Photos</button>
        <span id="upload_status" style="font-size:.72rem;color:#6b7280"></span>
      </div>
    </div>

    <div style="display:flex;gap:.5rem">
      <button type="submit" class="admin-btn admin-btn--primary">Save Vehicle</button>
      <a href="fleet.php" class="admin-btn admin-btn--outline">Cancel</a>
    </div>
  </form>
  </div>
</div>
<?php endif; ?>

<!-- ── Vehicle Grid ──────────────────────────────────────────── -->
<div class="admin-table-wrap">
<table class="admin-table">
<thead><tr><th>Photo</th><th>Name</th><th>Type</th><th>Capacity</th><th>Rate/Day</th><th>Plate</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($vehicles as $v):
  $imgs = json_decode($v['images'] ?? '[]', true) ?: [];
?>
<tr>
  <td><img src="<?= $imgs[0] ?? '' ?>" style="width:64px;height:46px;object-fit:cover;border:1px solid #e5e7eb;<?= empty($imgs)?'display:none':'' ?>"></td>
  <td><strong><?= htmlspecialchars($v['name']) ?></strong></td>
  <td><?= $vehicleTypes[$v['type']] ?? $v['type'] ?></td>
  <td><?= $v['capacity'] ?> persons</td>
  <td>$<?= number_format($v['daily_rate'],0) ?></td>
  <td><?= htmlspecialchars($v['plate_no'] ?? '—') ?></td>
  <td><span class="badge <?= $v['is_active']?'badge-new':'badge-cancelled' ?>"><?= $v['is_active']?'Active':'Inactive' ?></span></td>
  <td>
    <a href="fleet.php?edit=<?= $v['id'] ?>" class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">Edit</a>
    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this vehicle?')">
      <input type="hidden" name="delete_id" value="<?= $v['id'] ?>">
      <button class="admin-btn admin-btn--danger" style="font-size:.65rem;padding:.3rem .6rem">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
<?php if(empty($vehicles)): ?>
<tr><td colspan="8" class="admin-empty">No vehicles added yet. Click "+ Add Vehicle" to start.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<script>
var BASE = '<?= BASE_PATH ?>';
var currentImages = <?= $editing['images'] ?? '[]' ?>;
if (!Array.isArray(currentImages)) currentImages = [];

async function uploadImgs(files, type) {
  var status = document.getElementById('upload_status');
  status.textContent = 'Uploading...';
  for (var i = 0; i < files.length; i++) {
    var fd = new FormData();
    fd.append('image', files[i]);
    fd.append('type', type);
    try {
      var r = await fetch(BASE + '/api/upload_team_image.php', {method:'POST',body:fd,credentials:'same-origin'});
      var d = await r.json();
      if (d.success) {
        currentImages.push(d.url);
        document.getElementById('images_json').value = JSON.stringify(currentImages);
        var wrap = document.getElementById('image-preview');
        var div = document.createElement('div');
        div.className = 'img-thumb-wrap';
        div.style.position = 'relative';
        div.innerHTML = '<img src="'+d.url+'" style="width:90px;height:68px;object-fit:cover;border:1px solid #e5e7eb">'
          + '<button type="button" onclick="removeImg(this,\''+d.url+'\')" style="position:absolute;top:2px;right:2px;background:#dc2626;color:#fff;border:none;width:18px;height:18px;font-size:.7rem;cursor:pointer">×</button>';
        wrap.appendChild(div);
        status.textContent = 'Uploaded ✓';
      } else {
        status.textContent = 'Error: ' + d.message;
      }
    } catch(e) { status.textContent = 'Upload failed'; }
  }
}

function removeImg(btn, url) {
  currentImages = currentImages.filter(function(u){ return u !== url; });
  document.getElementById('images_json').value = JSON.stringify(currentImages);
  btn.parentElement.remove();
}
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
?>
