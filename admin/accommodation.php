<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Accommodations';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_acc'])) {
    $id     = (int)($_POST['id'] ?? 0);
    $images = json_decode($_POST['images_json'] ?? '[]', true) ?: [];
    $data = [
        'name'=>trim($_POST['name']??''),
        'type'=>trim($_POST['type']??'lodge'),
        'location'=>trim($_POST['location']??''),
        'park_area'=>trim($_POST['park_area']??''),
        'category'=>trim($_POST['category']??'midrange'),
        'rate_low'=>(float)($_POST['rate_low']??0),
        'rate_high'=>(float)($_POST['rate_high']??0),
        'rate_peak'=>(float)($_POST['rate_peak']??0),
        'contact_name'=>trim($_POST['contact_name']??''),
        'contact_email'=>trim($_POST['contact_email']??''),
        'contact_phone'=>trim($_POST['contact_phone']??''),
        'description'=>trim($_POST['description']??''),
        'images'=>json_encode($images),
        'website'=>trim($_POST['website']??''),
        'is_active'=>isset($_POST['is_active'])?1:0,
        'sort_order'=>(int)($_POST['sort_order']??0),
    ];
    try {
        if ($id) {
            $sets = implode(',',array_map(fn($k)=>"`$k`=?",array_keys($data)));
            $db->prepare("UPDATE accommodations SET $sets WHERE id=?")->execute([...array_values($data),$id]);
        } else {
            $cols=implode(',',array_map(fn($k)=>"`$k`",array_keys($data)));
            $vals=implode(',',array_fill(0,count($data),'?'));
            $db->prepare("INSERT INTO accommodations ($cols) VALUES ($vals)")->execute(array_values($data));
        }
        header('Location: accommodation.php?msg=Saved'); exit;
    } catch(\Exception $e) { $error=$e->getMessage(); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try { $db->prepare("DELETE FROM accommodations WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch(\Exception $e) {}
    header('Location: accommodation.php?msg=Deleted'); exit;
}

$accs   = $db->query("SELECT * FROM accommodations ORDER BY sort_order ASC, name ASC")->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId) { $s=$db->prepare("SELECT * FROM accommodations WHERE id=?"); $s->execute([$editId]); $editing=$s->fetch(); }

$categories = ['budget'=>'Budget-Friendly','midrange'=>'Mid-Range','highend'=>'High-End / Luxury','ultra_luxury'=>'Ultra Luxury'];
$parks = ['Serengeti','Ngorongoro','Tarangire','Lake Manyara','Arusha NP','Zanzibar','Ruaha','Nyerere','Kilimanjaro','Other'];

ob_start();
?>
<div class="admin-card-header">
  <h2 style="font-family:var(--font-disp);font-size:1.05rem">Accommodations</h2>
  <a href="accommodation.php?edit=new" class="admin-btn admin-btn--primary">+ Add Accommodation</a>
</div>
<?php if(isset($_GET['msg'])): ?><div class="admin-alert admin-alert--success"><?=htmlspecialchars($_GET['msg'])?></div><?php endif; ?>

<?php if($editId || isset($_GET['edit'])): ?>
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header">
    <h3><?=$editing?'Edit: '.htmlspecialchars($editing['name']):'New Accommodation'?></h3>
    <a href="accommodation.php" class="admin-btn admin-btn--outline">← Cancel</a>
  </div>
  <div class="admin-card-body">
  <form method="POST">
    <input type="hidden" name="save_acc" value="1">
    <input type="hidden" name="id" value="<?=$editing['id']??0?>">
    <input type="hidden" name="images_json" id="images_json" value="<?=htmlspecialchars($editing['images']??'[]')?>">

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
      <div><label class="admin-label">Name *</label><input class="admin-input" name="name" required value="<?=htmlspecialchars($editing['name']??'')?>"></div>
      <div><label class="admin-label">Type</label>
        <select class="admin-input" name="type">
          <?php foreach(['lodge','camp','tented_camp','hotel','guesthouse','beach_resort'] as $t): ?>
          <option value="<?=$t?>" <?=($editing['type']??'')===$t?'selected':''?>><?=ucwords(str_replace('_',' ',$t))?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="admin-label">Category</label>
        <select class="admin-input" name="category">
          <?php foreach($categories as $k=>$v): ?>
          <option value="<?=$k?>" <?=($editing['category']??'')===$k?'selected':''?>><?=$v?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="admin-label">Location</label><input class="admin-input" name="location" value="<?=htmlspecialchars($editing['location']??'')?>"></div>
      <div><label class="admin-label">Park / Area</label>
        <select class="admin-input" name="park_area">
          <?php foreach($parks as $p): ?>
          <option value="<?=$p?>" <?=($editing['park_area']??'')===$p?'selected':''?>><?=$p?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="admin-label">Website</label><input class="admin-input" name="website" type="url" value="<?=htmlspecialchars($editing['website']??'')?>"></div>
      <div><label class="admin-label">Rate Low Season (USD/night)</label><input class="admin-input" type="number" step="0.01" name="rate_low" value="<?=$editing['rate_low']??0?>"></div>
      <div><label class="admin-label">Rate High Season</label><input class="admin-input" type="number" step="0.01" name="rate_high" value="<?=$editing['rate_high']??0?>"></div>
      <div><label class="admin-label">Rate Peak Season</label><input class="admin-input" type="number" step="0.01" name="rate_peak" value="<?=$editing['rate_peak']??0?>"></div>
      <div><label class="admin-label">Contact Name</label><input class="admin-input" name="contact_name" value="<?=htmlspecialchars($editing['contact_name']??'')?>"></div>
      <div><label class="admin-label">Contact Email</label><input class="admin-input" name="contact_email" value="<?=htmlspecialchars($editing['contact_email']??'')?>"></div>
      <div><label class="admin-label">Contact Phone</label><input class="admin-input" name="contact_phone" value="<?=htmlspecialchars($editing['contact_phone']??'')?>"></div>
    </div>
    <div style="margin-bottom:1rem"><label class="admin-label">Description</label><textarea class="admin-input" name="description" rows="3"><?=htmlspecialchars($editing['description']??'')?></textarea></div>

    <!-- Image upload -->
    <div style="margin-bottom:1.5rem">
      <label class="admin-label">Photos</label>
      <div id="image-preview" style="display:flex;gap:.65rem;flex-wrap:wrap;margin-bottom:.75rem">
        <?php $imgs=json_decode($editing['images']??'[]',true)?:[];
        foreach($imgs as $img): ?>
        <div style="position:relative">
          <img src="<?=htmlspecialchars($img)?>" style="width:90px;height:68px;object-fit:cover;border:1px solid #e5e7eb">
          <button type="button" onclick="removeImg(this,'<?=htmlspecialchars($img)?>')" style="position:absolute;top:2px;right:2px;background:#dc2626;color:#fff;border:none;width:18px;height:18px;font-size:.7rem;cursor:pointer">×</button>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center">
        <input type="file" id="img_upload" accept="image/*" multiple style="display:none" onchange="uploadImgs(this.files,'accommodation')">
        <button type="button" onclick="document.getElementById('img_upload').click()" class="admin-btn admin-btn--outline">📷 Upload Photos</button>
        <span id="upload_status" style="font-size:.72rem;color:#6b7280"></span>
      </div>
    </div>

    <button type="submit" class="admin-btn admin-btn--primary">Save</button>
    <a href="accommodation.php" class="admin-btn admin-btn--outline">Cancel</a>
  </form>
  </div>
</div>
<?php endif; ?>

<div class="admin-table-wrap">
<table class="admin-table">
<thead><tr><th>Photo</th><th>Name</th><th>Type</th><th>Park</th><th>Category</th><th>Low/High/Peak</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($accs as $a):
  $imgs=json_decode($a['images']??'[]',true)?:[];
?>
<tr>
  <td><?php if($imgs[0]??null): ?><img src="<?=htmlspecialchars($imgs[0])?>" style="width:64px;height:46px;object-fit:cover;border:1px solid #e5e7eb"><?php endif; ?></td>
  <td><strong><?=htmlspecialchars($a['name'])?></strong><br><span style="font-size:.72rem;color:#6b7280"><?=htmlspecialchars($a['location']??'')?></span></td>
  <td><?=ucwords(str_replace('_',' ',$a['type']))?></td>
  <td><?=htmlspecialchars($a['park_area']??'—')?></td>
  <td><span class="badge badge-pending"><?=$categories[$a['category']]??$a['category']?></span></td>
  <td style="font-size:.78rem">$<?=number_format($a['rate_low'],0)?> / $<?=number_format($a['rate_high'],0)?> / $<?=number_format($a['rate_peak'],0)?></td>
  <td><span class="badge <?=$a['is_active']?'badge-new':'badge-cancelled'?>"><?=$a['is_active']?'Active':'Inactive'?></span></td>
  <td>
    <a href="accommodation.php?edit=<?=$a['id']?>" class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">Edit</a>
    <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
      <input type="hidden" name="delete_id" value="<?=$a['id']?>">
      <button class="admin-btn admin-btn--danger" style="font-size:.65rem;padding:.3rem .6rem">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
<?php if(empty($accs)): ?><tr><td colspan="8" class="admin-empty">No accommodations yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>

<script>
var BASE='<?=BASE_PATH?>';
var currentImages=<?=$editing['images']??'[]'?>;
if(!Array.isArray(currentImages))currentImages=[];
async function uploadImgs(files,type){
  var status=document.getElementById('upload_status');
  for(var i=0;i<files.length;i++){
    var fd=new FormData();fd.append('image',files[i]);fd.append('type',type);
    try{var r=await fetch(BASE+'/api/upload_team_image.php',{method:'POST',body:fd,credentials:'same-origin'});
    var d=await r.json();
    if(d.success){currentImages.push(d.url);document.getElementById('images_json').value=JSON.stringify(currentImages);
    var wrap=document.getElementById('image-preview');var div=document.createElement('div');div.style.position='relative';
    div.innerHTML='<img src="'+d.url+'" style="width:90px;height:68px;object-fit:cover;border:1px solid #e5e7eb">'
      +'<button type="button" onclick="removeImg(this,\''+d.url+'\')" style="position:absolute;top:2px;right:2px;background:#dc2626;color:#fff;border:none;width:18px;height:18px;font-size:.7rem;cursor:pointer">×</button>';
    wrap.appendChild(div);status.textContent='✓ Uploaded';}else{status.textContent='Error: '+d.message;}
    }catch(e){status.textContent='Upload failed';}
  }
}
function removeImg(btn,url){currentImages=currentImages.filter(function(u){return u!==url;});document.getElementById('images_json').value=JSON.stringify(currentImages);btn.parentElement.remove();}
</script>
<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
