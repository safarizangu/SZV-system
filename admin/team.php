<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Team Members';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_member'])) {
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        'name'        => trim($_POST['name'] ?? ''),
        'role'        => trim($_POST['role'] ?? ''),
        'bio'         => trim($_POST['bio'] ?? ''),
        'photo'       => trim($_POST['photo'] ?? ''),
        'email'       => trim($_POST['email'] ?? ''),
        'phone'       => trim($_POST['phone'] ?? ''),
        'linkedin_url'=> trim($_POST['linkedin_url'] ?? ''),
        'member_type' => trim($_POST['member_type'] ?? 'office'),
        'languages'   => trim($_POST['languages'] ?? ''),
        'specialty'   => trim($_POST['specialty'] ?? ''),
        'years_exp'   => (int)($_POST['years_exp'] ?? 0),
        'sort_order'  => (int)($_POST['sort_order'] ?? 0),
        'is_active'   => isset($_POST['is_active']) ? 1 : 0,
    ];
    try {
        if ($id) {
            $sets=implode(',',array_map(fn($k)=>"`$k`=?",array_keys($data)));
            $db->prepare("UPDATE team_members SET $sets WHERE id=?")->execute([...array_values($data),$id]);
        } else {
            $cols=implode(',',array_map(fn($k)=>"`$k`",array_keys($data)));
            $vals=implode(',',array_fill(0,count($data),'?'));
            $db->prepare("INSERT INTO team_members ($cols) VALUES ($vals)")->execute(array_values($data));
        }
        header('Location: team.php?msg=Saved'); exit;
    } catch(\Exception $e) { $error=$e->getMessage(); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try { $db->prepare("DELETE FROM team_members WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    catch(\Exception $e) {}
    header('Location: team.php?msg=Deleted'); exit;
}

$members = $db->query("SELECT * FROM team_members ORDER BY member_type ASC, sort_order ASC, id ASC")->fetchAll();
$editId  = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId) { $s=$db->prepare("SELECT * FROM team_members WHERE id=?"); $s->execute([$editId]); $editing=$s->fetch(); }

ob_start();
?>
<div class="admin-card-header">
  <h2 style="font-family:var(--font-disp);font-size:1.05rem">Team Members</h2>
  <a href="team.php?edit=new" class="admin-btn admin-btn--primary">+ Add Member</a>
</div>
<?php if(isset($_GET['msg'])): ?><div class="admin-alert admin-alert--success"><?=htmlspecialchars($_GET['msg'])?></div><?php endif; ?>

<?php if($editId || isset($_GET['edit'])): ?>
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header">
    <h3><?=$editing?'Edit: '.htmlspecialchars($editing['name']):'New Member'?></h3>
    <a href="team.php" class="admin-btn admin-btn--outline">← Cancel</a>
  </div>
  <div class="admin-card-body">
  <form method="POST">
    <input type="hidden" name="save_member" value="1">
    <input type="hidden" name="id" value="<?=$editing['id']??0?>">
    <input type="hidden" name="photo" id="photo_url" value="<?=htmlspecialchars($editing['photo']??'')?>">

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
      <div><label class="admin-label">Full Name *</label><input class="admin-input" name="name" required value="<?=htmlspecialchars($editing['name']??'')?>"></div>
      <div><label class="admin-label">Role / Title</label><input class="admin-input" name="role" value="<?=htmlspecialchars($editing['role']??'')?>"></div>
      <div><label class="admin-label">Type</label>
        <select class="admin-input" name="member_type">
          <option value="office" <?=($editing['member_type']??'')==='office'?'selected':''?>>Office Team</option>
          <option value="guide"  <?=($editing['member_type']??'')==='guide'?'selected':''?>>Field Guide</option>
        </select>
      </div>
      <div><label class="admin-label">Email</label><input class="admin-input" name="email" type="email" value="<?=htmlspecialchars($editing['email']??'')?>"></div>
      <div><label class="admin-label">Phone</label><input class="admin-input" name="phone" value="<?=htmlspecialchars($editing['phone']??'')?>"></div>
      <div><label class="admin-label">LinkedIn URL</label><input class="admin-input" name="linkedin_url" value="<?=htmlspecialchars($editing['linkedin_url']??'')?>"></div>
      <div><label class="admin-label">Languages (Guide only)</label><input class="admin-input" name="languages" value="<?=htmlspecialchars($editing['languages']??'')?>"></div>
      <div><label class="admin-label">Specialty (Guide only)</label><input class="admin-input" name="specialty" value="<?=htmlspecialchars($editing['specialty']??'')?>"></div>
      <div><label class="admin-label">Years Experience</label><input class="admin-input" type="number" name="years_exp" value="<?=$editing['years_exp']??0?>"></div>
      <div><label class="admin-label">Sort Order</label><input class="admin-input" type="number" name="sort_order" value="<?=$editing['sort_order']??0?>"></div>
      <div style="display:flex;align-items:center;gap:.5rem;margin-top:1.5rem">
        <input type="checkbox" name="is_active" id="is_active" <?=($editing['is_active']??1)?'checked':''?>>
        <label for="is_active" class="admin-label" style="margin-bottom:0">Active</label>
      </div>
    </div>

    <div style="margin-bottom:1rem"><label class="admin-label">Bio</label><textarea class="admin-input" name="bio" rows="4"><?=htmlspecialchars($editing['bio']??'')?></textarea></div>

    <!-- Photo Upload -->
    <div style="margin-bottom:1.5rem">
      <label class="admin-label">Headshot Photo</label>
      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:.75rem">
        <div id="photo_preview">
          <?php if(!empty($editing['photo'])): ?>
          <img src="<?=htmlspecialchars($editing['photo'])?>" style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:2px solid #e5e7eb">
          <?php else: ?>
          <div style="width:80px;height:80px;border-radius:50%;background:#f3f4f6;border:2px dashed #d1d5db;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#9ca3af">?</div>
          <?php endif; ?>
        </div>
        <div>
          <input type="file" id="photo_file" accept="image/*" style="display:none" onchange="uploadPhoto(this.files[0])">
          <button type="button" onclick="document.getElementById('photo_file').click()" class="admin-btn admin-btn--outline">📷 Upload Photo</button>
          <p style="font-size:.72rem;color:#6b7280;margin-top:.35rem">Square photo works best. JPG, PNG, WebP. Max 5MB.</p>
          <span id="photo_status" style="font-size:.72rem;color:#16a34a"></span>
        </div>
      </div>
    </div>

    <button type="submit" class="admin-btn admin-btn--primary">Save Member</button>
    <a href="team.php" class="admin-btn admin-btn--outline">Cancel</a>
  </form>
  </div>
</div>
<?php endif; ?>

<!-- Members Grid -->
<?php
$office = array_filter($members, fn($m) => $m['member_type'] === 'office');
$guides = array_filter($members, fn($m) => $m['member_type'] === 'guide');
?>

<?php foreach([['Office Team',$office],['Field Guides',$guides]] as [$label,$group]): ?>
<h3 style="font-family:var(--font-disp);font-size:.95rem;margin:1.5rem 0 .75rem"><?=$label?></h3>
<div class="admin-table-wrap" style="margin-bottom:1.5rem">
<table class="admin-table">
<thead><tr><th>Photo</th><th>Name</th><th>Role</th><?=$label==='Field Guides'?'<th>Languages</th><th>Specialty</th>':''?><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($group as $m): ?>
<tr>
  <td><?php if($m['photo']): ?><img src="<?=htmlspecialchars($m['photo'])?>" style="width:44px;height:44px;object-fit:cover;border-radius:50%;border:1px solid #e5e7eb"><?php else: ?><div style="width:44px;height:44px;border-radius:50%;background:#f3f4f6;display:flex;align-items:center;justify-content:center;font-weight:600;color:#6b7280"><?=mb_strtoupper(mb_substr($m['name'],0,1))?></div><?php endif; ?></td>
  <td><strong><?=htmlspecialchars($m['name'])?></strong></td>
  <td><?=htmlspecialchars($m['role']??'—')?></td>
  <?php if($label==='Field Guides'): ?>
  <td><?=htmlspecialchars($m['languages']??'—')?></td>
  <td><?=htmlspecialchars($m['specialty']??'—')?></td>
  <?php endif; ?>
  <td><span class="badge <?=$m['is_active']?'badge-new':'badge-cancelled'?>"><?=$m['is_active']?'Active':'Inactive'?></span></td>
  <td>
    <a href="team.php?edit=<?=$m['id']?>" class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .6rem">Edit</a>
    <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
      <input type="hidden" name="delete_id" value="<?=$m['id']?>">
      <button class="admin-btn admin-btn--danger" style="font-size:.65rem;padding:.3rem .6rem">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
<?php if(empty(iterator_to_array(new ArrayIterator($group ?? [])))): ?>
<tr><td colspan="6" class="admin-empty">No <?=strtolower($label)?> added yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php endforeach; ?>

<script>
var BASE='<?=BASE_PATH?>';
async function uploadPhoto(file){
  if(!file)return;
  var status=document.getElementById('photo_status');
  status.textContent='Uploading...';
  var fd=new FormData();fd.append('image',file);fd.append('type','team');
  try{
    var r=await fetch(BASE+'/api/upload_team_image.php',{method:'POST',body:fd,credentials:'same-origin'});
    var d=await r.json();
    if(d.success){
      document.getElementById('photo_url').value=d.url;
      var prev=document.getElementById('photo_preview');
      prev.innerHTML='<img src="'+d.url+'" style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:2px solid #c8963e">';
      status.textContent='✓ Photo uploaded';
      status.style.color='#16a34a';
    }else{status.textContent='Error: '+d.message;status.style.color='#dc2626';}
  }catch(e){status.textContent='Upload failed';status.style.color='#dc2626';}
}
</script>
<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
