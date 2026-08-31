<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

// Scan all images
$imgRoot = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/assets/images/';
$allowed = ['jpg','jpeg','png','webp','gif'];

function scanFolder(string $dir, string $relBase): array {
    global $allowed;
    $images = [];
    if (!is_dir($dir)) return $images;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $dir . $item;
        if (is_dir($full)) continue;
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;
        $images[] = [
            'url'      => $relBase . $item,
            'filename' => $item,
            'size'     => filesize($full),
            'modified' => filemtime($full),
        ];
    }
    usort($images, fn($a,$b) => $b['modified'] - $a['modified']);
    return $images;
}

// Get all folders
$folders = [];
$di = new DirectoryIterator($imgRoot);
foreach ($di as $item) {
    if ($item->isDot() || !$item->isDir()) continue;
    $name     = $item->getFilename();
    $relBase  = BASE_PATH . '/assets/images/' . $name . '/';
    $subDir   = $imgRoot . $name . '/';
    $images   = scanFolder($subDir, $relBase);

    // Also scan one level of subdirs (e.g. packages/classic-safari/)
    if (is_dir($subDir)) {
        $subDi = new DirectoryIterator($subDir);
        foreach ($subDi as $sub) {
            if ($sub->isDot() || !$sub->isDir()) continue;
            $subName  = $sub->getFilename();
            $subRel   = BASE_PATH . '/assets/images/' . $name . '/' . $subName . '/';
            $subImgs  = scanFolder($subDir.$subName.'/', $subRel);
            if ($subImgs) {
                $folders[$name.'/'.$subName] = $subImgs;
            }
        }
    }

    // Root images directly in this folder
    if ($images) $folders[$name] = $images;
}
ksort($folders);

// All images flat (for counts)
$totalImages = array_sum(array_map('count', $folders));
$activeFolder = $_GET['folder'] ?? array_key_first($folders) ?? '';

$pageTitle = 'Image Gallery';
ob_start();
?>

<div style="display:grid;grid-template-columns:220px 1fr;gap:1.5rem;align-items:start">

<!-- LEFT: folder tree -->
<div style="position:sticky;top:calc(var(--topbar-h) + 1rem)">
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-header" style="padding:.85rem 1rem">
      <h3 style="font-size:.82rem">Folders</h3>
      <span style="font-size:.68rem;color:#9ca3af"><?= $totalImages ?> images</span>
    </div>
    <div style="padding:.25rem 0">
      <?php foreach ($folders as $folder => $imgs): ?>
      <a href="?folder=<?= urlencode($folder) ?>"
         style="display:flex;align-items:center;justify-content:space-between;padding:.55rem 1rem;font-size:.75rem;font-weight:600;text-decoration:none;color:<?= $folder===$activeFolder?'#fff':'#374151' ?>;background:<?= $folder===$activeFolder?'#0a0a0a':'transparent' ?>;border-left:2px solid <?= $folder===$activeFolder?'#fff':'transparent' ?>">
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">📁 <?= htmlspecialchars(str_replace('packages/','',$folder)) ?></span>
        <span style="font-size:.65rem;opacity:.6;flex-shrink:0;margin-left:.35rem"><?= count($imgs) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Upload form -->
  <div class="admin-card">
    <div class="admin-card-header" style="padding:.85rem 1rem"><h3 style="font-size:.82rem">Upload Image</h3></div>
    <div class="admin-card-body" style="padding:.85rem 1rem">
      <div style="margin-bottom:.65rem">
        <label class="admin-label">Upload to folder</label>
        <select id="upload-folder" class="admin-input" style="font-size:.78rem">
          <?php foreach (array_keys($folders) as $f): ?>
          <option value="<?= htmlspecialchars($f) ?>" <?= $f===$activeFolder?'selected':'' ?>><?= htmlspecialchars($f) ?></option>
          <?php endforeach; ?>
          <option value="general">general (new)</option>
        </select>
      </div>
      <label id="upload-area"
             style="display:block;border:2px dashed #e5e7eb;padding:1.5rem;text-align:center;cursor:pointer;transition:border-color .15s;background:#fafafa">
        <input type="file" id="upload-input" accept="image/*" multiple style="display:none">
        <div id="upload-icon" style="font-size:1.5rem;margin-bottom:.35rem">📷</div>
        <div style="font-size:.72rem;color:#9ca3af;line-height:1.5">Click or drag images<br><span style="font-size:.65rem">JPG, PNG, WebP · max 8MB</span></div>
      </label>
      <div id="upload-progress" style="display:none;margin-top:.65rem">
        <div style="background:#f3f4f6;height:4px;border-radius:2px">
          <div id="upload-bar" style="background:#0a0a0a;height:4px;width:0;border-radius:2px;transition:width .3s"></div>
        </div>
        <div id="upload-status" style="font-size:.68rem;color:#9ca3af;margin-top:.3rem;text-align:center"></div>
      </div>
    </div>
  </div>
</div><!-- /left -->

<!-- RIGHT: image grid -->
<div>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
    <div>
      <h2 style="font-family:var(--font-disp);font-size:1.05rem;font-weight:500">
        <?= htmlspecialchars($activeFolder ?: 'All Images') ?>
      </h2>
      <p style="font-size:.72rem;color:#9ca3af;margin-top:.1rem">
        <?= isset($folders[$activeFolder]) ? count($folders[$activeFolder]) : 0 ?> images
      </p>
    </div>
    <div style="display:flex;gap:.5rem">
      <input type="text" id="img-search" placeholder="Filter images…"
             style="border:1.5px solid #e5e7eb;padding:.35rem .75rem;font-size:.78rem;outline:none;min-width:180px"
             oninput="filterImages(this.value)">
    </div>
  </div>

  <div id="image-grid"
       style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.85rem">
    <?php $activeImages = $folders[$activeFolder] ?? []; ?>
    <?php if (empty($activeImages)): ?>
    <div style="grid-column:1/-1;text-align:center;padding:3rem;color:#9ca3af">
      <div style="font-size:2rem;margin-bottom:.5rem;opacity:.3">🖼</div>
      <p style="font-size:.82rem">No images in this folder yet. Upload one above.</p>
    </div>
    <?php endif; ?>
    <?php foreach ($activeImages as $img): ?>
    <div class="gallery-item" data-url="<?= htmlspecialchars($img['url']) ?>"
         data-img-url="<?= htmlspecialchars($imgUrl) ?>" style="border:1.5px solid #e5e7eb;background:#fff;position:relative;overflow:hidden">
      <div style="aspect-ratio:4/3;overflow:hidden;background:#f3f4f6">
        <img src="<?= htmlspecialchars($img['url']) ?>"
             alt="<?= htmlspecialchars($img['filename']) ?>"
             style="width:100%;height:100%;object-fit:cover;display:block;transition:transform .2s"
             loading="lazy"
             onmouseover="this.style.transform='scale(1.05)'"
             onmouseout="this.style.transform='scale(1)'">
      </div>
      <div style="padding:.5rem .6rem">
        <div style="font-size:.65rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#374151"
             title="<?= htmlspecialchars($img['filename']) ?>">
          <?= htmlspecialchars($img['filename']) ?>
        </div>
        <div style="font-size:.6rem;color:#9ca3af;margin-top:.1rem">
          <?= round($img['size']/1024) ?>KB · <?= date('d M Y', $img['modified']) ?>
        </div>
      </div>
      <div style="display:flex;gap:.35rem;padding:.4rem .6rem .6rem">
        <button onclick="copyUrl('<?= htmlspecialchars($img['url']) ?>')"
                class="admin-btn admin-btn--outline" style="flex:1;font-size:.58rem;padding:.25rem .3rem;justify-content:center">
          Copy URL
        </button>
        <button onclick="deleteImage('<?= htmlspecialchars($img['url']) ?>', this)"
                class="admin-btn admin-btn--danger" style="font-size:.58rem;padding:.25rem .4rem">
          ✕
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div><!-- /right -->
</div><!-- /grid -->

<!-- Full-size preview modal -->
<style>
.hero-indicator {
  position:absolute;top:6px;left:6px;
  background:#c8963e;color:#fff;
  font-size:.55rem;font-weight:700;letter-spacing:.1em;
  text-transform:uppercase;padding:.18rem .45rem;z-index:2;
}
</style>

<div id="previewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:999;align-items:center;justify-content:center;padding:2rem"
     onclick="if(event.target===this)this.style.display='none'">
  <div style="max-width:90vw;max-height:90vh;position:relative">
    <img id="previewImg" src="" alt="" style="max-width:100%;max-height:85vh;display:block;object-fit:contain">
    <div id="previewInfo" style="color:#fff;font-size:.78rem;margin-top:.75rem;text-align:center;opacity:.7"></div>
    <button onclick="document.getElementById('previewModal').style.display='none'"
            style="position:absolute;top:-1.25rem;right:-1.25rem;background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1rem">✕</button>
  </div>
</div>

<div id="toastG" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none"></div>

<script>
// ── Upload ────────────────────────────────────────────────
var uploadArea  = document.getElementById('upload-area');
var uploadInput = document.getElementById('upload-input');

uploadArea.addEventListener('dragover', function(e){ e.preventDefault(); this.style.borderColor='#0a0a0a'; });
uploadArea.addEventListener('dragleave', function(){ this.style.borderColor='#e5e7eb'; });
uploadArea.addEventListener('drop', function(e){
    e.preventDefault(); this.style.borderColor='#e5e7eb';
    uploadFiles(e.dataTransfer.files);
});
uploadInput.addEventListener('change', function(){ uploadFiles(this.files); });

async function uploadFiles(files) {
    if (!files.length) return;
    var folder = document.getElementById('upload-folder').value;
    var prog   = document.getElementById('upload-progress');
    var bar    = document.getElementById('upload-bar');
    var status = document.getElementById('upload-status');
    prog.style.display = 'block';
    var done = 0;
    for (var i = 0; i < files.length; i++) {
        status.textContent = 'Uploading ' + (i+1) + ' of ' + files.length + '…';
        bar.style.width = Math.round((i/files.length)*100) + '%';
        var fd = new FormData();
        fd.append('image', files[i]);
        fd.append('folder', folder);
        try {
            var r = await fetch('<?= BASE_PATH ?>/php/gallery_upload.php', {method:'POST',body:fd,credentials:'same-origin'});
            var d = await r.json();
            if (d.success) { done++; addToGrid(d); toast('Uploaded: '+d.filename, 'ok'); }
            else           { toast('Failed: '+d.message, 'err'); }
        } catch(e) { toast('Network error', 'err'); }
    }
    bar.style.width = '100%';
    status.textContent = done + ' of ' + files.length + ' uploaded.';
    setTimeout(function(){ prog.style.display='none'; bar.style.width='0'; }, 2500);
}

function addToGrid(d) {
    var grid = document.getElementById('image-grid');
    var div  = document.createElement('div');
    div.className = 'gallery-item';
    div.dataset.url = d.url;
    div.style.cssText = 'border:1.5px solid #0a0a0a;background:#fff;position:relative;overflow:hidden';
    div.innerHTML = '<div style="aspect-ratio:4/3;overflow:hidden;background:#f3f4f6"><img src="'+d.url+'" style="width:100%;height:100%;object-fit:cover;display:block"></div>'
        +'<div style="padding:.5rem .6rem"><div style="font-size:.65rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+d.filename+'</div></div>'
        +'<div style="display:flex;gap:.35rem;padding:.4rem .6rem .6rem">'
        +'<button onclick="copyUrl(\''+d.url+'\')" class="admin-btn admin-btn--outline" style="flex:1;font-size:.58rem;padding:.25rem .3rem;justify-content:center">Copy URL</button>'
        +'<button onclick="deleteImage(\''+d.url+'\',this)" class="admin-btn admin-btn--danger" style="font-size:.58rem;padding:.25rem .4rem">✕</button>'
        +'</div>';
    grid.prepend(div);
    setTimeout(function(){ div.style.border='1.5px solid #e5e7eb'; }, 1500);
}

// ── Delete ────────────────────────────────────────────────
async function deleteImage(url, btn) {
    if (!confirm('Delete this image? This cannot be undone.')) return;
    btn.disabled = true; btn.textContent = '…';
    var fd = new FormData(); fd.append('path', url);
    try {
        var r = await fetch('<?= BASE_PATH ?>/php/gallery_delete.php', {method:'POST',body:fd,credentials:'same-origin'});
        var d = await r.json();
        if (d.success) { btn.closest('.gallery-item').remove(); toast('Deleted', 'ok'); }
        else           { toast('Error: '+d.message, 'err'); btn.disabled=false; btn.textContent='✕'; }
    } catch(e) { toast('Network error', 'err'); }
}

// ── Copy URL ──────────────────────────────────────────────
function copyUrl(url) {
    navigator.clipboard.writeText(url).then(function(){
        toast('URL copied!', 'ok');
    }).catch(function(){
        prompt('Copy this URL:', url);
    });
}

// ── Filter ────────────────────────────────────────────────
function filterImages(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.gallery-item').forEach(function(el){
        var name = el.dataset.url.toLowerCase();
        el.style.display = name.includes(q) ? '' : 'none';
    });
}

// ── Preview on image click ────────────────────────────────
document.getElementById('image-grid').addEventListener('click', function(e) {
    var img = e.target.closest('img');
    if (!img) return;
    document.getElementById('previewImg').src = img.src;
    document.getElementById('previewInfo').textContent = img.alt;
    document.getElementById('previewModal').style.display = 'flex';
});

// ── Toast ─────────────────────────────────────────────────
function toast(msg, type) {
    var t = document.createElement('div');
    t.style.cssText = 'background:'+(type==='ok'?'#16a34a':'#dc2626')+';color:#fff;padding:.65rem 1.1rem;font-size:.78rem;box-shadow:0 4px 16px rgba(0,0,0,.15);pointer-events:auto';
    t.textContent = msg;
    document.getElementById('toastG').appendChild(t);
    setTimeout(function(){ t.remove(); }, 3000);
}

// ── Hero image management ─────────────────────────────────
var currentHeroUrl = '';

// Load current hero on page load
fetch(BASE + '/api/get_setting.php?key=hero_image', {credentials:'same-origin'})
  .then(function(r){return r.json();})
  .then(function(d){
    if (d.success && d.value) {
      currentHeroUrl = d.value;
      markCurrentHero(d.value);
    }
  }).catch(function(){});

function markCurrentHero(url) {
  document.querySelectorAll('.hero-indicator').forEach(function(el){
    el.remove();
  });
  document.querySelectorAll('[data-img-url]').forEach(function(card){
    if (card.dataset.imgUrl === url) {
      var badge = document.createElement('div');
      badge.className = 'hero-indicator';
      badge.style.cssText = 'position:absolute;top:6px;left:6px;background:#c8963e;color:#fff;font-size:.55rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.18rem .45rem;z-index:2';
      badge.textContent = '⌂ HERO';
      card.style.position = 'relative';
      card.appendChild(badge);
    }
  });
}

async function setHeroImage(url, btn) {
  var orig = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Setting…';

  var fd = new FormData();
  fd.append('image_url', url);

  try {
    var r = await fetch(BASE + '/api/set_hero.php', {
      method:'POST', body:fd, credentials:'same-origin'
    });
    var d = await r.json();

    if (d.success) {
      currentHeroUrl = url;
      markCurrentHero(url);
      toast('✓ Hero image updated — refresh the homepage to see it', 'ok');
    } else {
      toast(d.message || 'Failed to set hero', 'err');
    }
  } catch(e) {
    toast('Network error', 'err');
  }

  btn.disabled = false;
  btn.textContent = orig;
}

</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
