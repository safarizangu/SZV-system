<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$pageTitle = 'Gallery Manager';

// ── Image root ────────────────────────────────────────────────
$imgRoot  = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . BASE_PATH . '/assets/images/';
$imgBase  = BASE_PATH . '/assets/images/';

// ── Get current hero image from settings ──────────────────────
$currentHero = '';
try {
    $hs = \App\Models\Database::getInstance()->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
    $hs->execute(['hero_image']);
    $hr = $hs->fetch();
    $currentHero = $hr ? $hr['value'] : '';
} catch (\Exception $e) {}

// ── Scan folders ──────────────────────────────────────────────
$folders = [];
if (is_dir($imgRoot)) {
    foreach (scandir($imgRoot) as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $imgRoot . $item;
        if (!is_dir($full)) continue;
        $imgs = [];
        foreach (scandir($full) as $img) {
            if (!preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $img)) continue;
            $imgs[] = $img;
        }
        // Also scan one level of subdirectories
        foreach (scandir($full) as $sub) {
            if ($sub === '.' || $sub === '..') continue;
            $subFull = $full . '/' . $sub;
            if (!is_dir($subFull)) continue;
            $subImgs = [];
            foreach (scandir($subFull) as $img) {
                if (!preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $img)) continue;
                $subImgs[] = $img;
            }
            if ($subImgs) $folders[$item . '/' . $sub] = $subImgs;
        }
        if ($imgs) $folders[$item] = $imgs;
    }
}

$activeFolder = $_GET['folder'] ?? (array_key_first($folders) ?: 'downloaded');
$activeFolder = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $activeFolder);
$trashIconJs = json_encode(\App\Helpers\AdminIcon::render('trash', 12));

ob_start();
?>
<style>
.gallery-layout { display:grid; grid-template-columns:220px 1fr; gap:0; min-height:70vh; }
.gallery-sidebar {
  border-right:1px solid var(--gray-200);
  padding:1rem 0;
  background:var(--gray-50);
  overflow-y:auto;
  max-height:80vh;
  position:sticky;
  top:0;
}
.gallery-sidebar a {
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:.55rem 1.1rem;
  font-size:.72rem;
  font-weight:600;
  text-decoration:none;
  color:var(--gray-700);
  border-left:3px solid transparent;
  transition:all .15s;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.gallery-sidebar a:hover { background:var(--gray-100); color:var(--black); }
.gallery-sidebar a.active {
  background:var(--black);
  color:var(--white);
  border-left-color:var(--gold);
}
.gallery-sidebar span.count {
  font-size:.6rem;
  font-weight:400;
  opacity:.6;
  margin-left:.4rem;
  flex-shrink:0;
}
.gallery-main { padding:1.5rem; }
.gallery-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
  gap:1rem;
}
.img-card {
  border:1.5px solid var(--gray-200);
  background:var(--white);
  position:relative;
  overflow:hidden;
  transition:border-color .15s, box-shadow .15s;
}
.img-card:hover { border-color:var(--gold); box-shadow:0 4px 16px rgba(0,0,0,.08); }
.img-card.is-hero { border-color:var(--gold); border-width:2px; }
.img-thumb {
  aspect-ratio:4/3;
  overflow:hidden;
  background:var(--gray-100);
  cursor:pointer;
}
.img-thumb img {
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
  transition:transform .2s;
}
.img-thumb:hover img { transform:scale(1.04); }
.img-actions {
  display:grid;
  grid-template-columns:1fr auto auto;
  gap:4px;
  padding:6px;
  border-top:1px solid var(--gray-100);
}
.btn-preview {
  padding:.35rem .5rem;
  font-size:.6rem;
  font-weight:600;
  letter-spacing:.06em;
  border:1px solid var(--gray-200);
  background:var(--white);
  cursor:pointer;
  text-align:center;
  transition:all .15s;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.btn-preview:hover { background:var(--gray-100); }
.btn-hero {
  padding:.35rem .5rem;
  font-size:.65rem;
  font-weight:700;
  border:1.5px solid #fde68a;
  background:#fffbeb;
  color:#92400e;
  cursor:pointer;
  transition:all .15s;
  white-space:nowrap;
}
.btn-hero:hover { background:var(--gold); border-color:var(--gold); color:var(--white); }
.btn-hero.active-hero { background:var(--gold); border-color:var(--gold); color:var(--white); }
.btn-delete {
  padding:.35rem .5rem;
  font-size:.65rem;
  border:1.5px solid #fee2e2;
  background:var(--red-light);
  color:var(--red);
  cursor:pointer;
  transition:all .15s;
}
.btn-delete:hover { background:var(--red); color:var(--white); }
.hero-badge {
  position:absolute;
  top:6px;
  left:6px;
  background:var(--gold);
  color:var(--white);
  font-size:.5rem;
  font-weight:700;
  letter-spacing:.12em;
  text-transform:uppercase;
  padding:.18rem .45rem;
  z-index:3;
  pointer-events:none;
}
.upload-zone {
  border:2px dashed #d1d5db;
  padding:2rem;
  text-align:center;
  cursor:pointer;
  transition:all .2s;
  background:var(--gray-50);
  margin-bottom:1.25rem;
}
.upload-zone:hover, .upload-zone.drag-over {
  border-color:var(--gold);
  background:#fffbeb;
}
.upload-progress-bar {
  background:var(--gray-100);
  height:4px;
  border-radius:2px;
  margin-top:.75rem;
  overflow:hidden;
  display:none;
}
.upload-progress-fill {
  background:var(--gold);
  height:4px;
  width:0;
  transition:width .3s;
}
#preview-modal.admin-modal-overlay {
  background:rgba(0,0,0,.92);
  z-index:9999;
  padding:2rem;
}
#preview-modal img {
  max-width:90vw;
  max-height:85vh;
  object-fit:contain;
  box-shadow:0 24px 80px rgba(0,0,0,.5);
}
#preview-modal-close {
  position:absolute;
  top:1rem;
  right:1.25rem;
  background:rgba(255,255,255,.15);
  border:none;
  color:var(--white);
  width:36px;
  height:36px;
  border-radius:50%;
  cursor:pointer;
  display:flex;
  align-items:center;
  justify-content:center;
}
.toast-container {
  position:fixed;
  bottom:1.5rem;
  right:1.5rem;
  z-index:10000;
  display:flex;
  flex-direction:column;
  gap:.5rem;
}
.toast {
  padding:.75rem 1.1rem;
  font-size:.78rem;
  font-weight:600;
  color:var(--white);
  border-radius:2px;
  box-shadow:0 4px 16px rgba(0,0,0,.2);
  animation:toast-in .2s ease;
}
@keyframes toast-in { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
.toast.ok  { background:#166534; }
.toast.err { background:var(--red); }
.toast.inf { background:var(--blue); }
.gallery-header {
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin-bottom:1.25rem;
  gap:1rem;
  flex-wrap:wrap;
}
.gallery-count {
  font-size:.72rem;
  color:var(--gray-500);
  font-weight:500;
}
</style>

<div class="gallery-layout">

  <!-- Sidebar: folder tree -->
  <div class="gallery-sidebar">
    <div style="padding:.5rem 1.1rem;font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--gray-500);border-bottom:1px solid var(--gray-200);margin-bottom:.5rem;padding-bottom:.75rem">
      Image Folders
    </div>
    <?php foreach ($folders as $folder => $imgs): ?>
    <a href="?folder=<?= urlencode($folder) ?>"
       class="<?= $folder === $activeFolder ? 'active' : '' ?>">
      <span style="overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($folder) ?></span>
      <span class="count"><?= count($imgs) ?></span>
    </a>
    <?php endforeach; ?>
    <?php if (empty($folders)): ?>
    <p style="padding:1rem;font-size:.72rem;color:var(--gray-500)">No image folders found</p>
    <?php endif; ?>
  </div>

  <!-- Main: image grid -->
  <div class="gallery-main">

    <!-- Upload zone -->
    <div class="upload-zone" id="upload-zone"
         onclick="document.getElementById('file-input').click()">
      <input type="file" id="file-input" multiple accept="image/*"
             style="display:none" onchange="uploadFiles(this.files)">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--gray-500)" stroke-width="1.5" style="margin:0 auto .5rem;display:block">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
      <p style="font-size:.72rem;color:var(--gray-500);margin:0">
        Drop images here or <strong>click to upload</strong> into <em><?= htmlspecialchars($activeFolder) ?>/</em>
      </p>
      <div class="upload-progress-bar" id="upload-bar-wrap">
        <div class="upload-progress-fill" id="upload-bar"></div>
      </div>
    </div>

    <!-- Header -->
    <div class="gallery-header">
      <div>
        <div style="font-size:.6rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--gray-500);margin-bottom:.2rem">
          Current folder
        </div>
        <div style="font-family:var(--font-display,Georgia),serif;font-size:1.1rem">
          <?= htmlspecialchars($activeFolder) ?>/
        </div>
      </div>
      <span class="gallery-count" id="img-count">
        <?= count($folders[$activeFolder] ?? []) ?> images
      </span>
    </div>

    <!-- Image grid -->
    <div class="gallery-grid" id="gallery-grid">
      <?php
      $activeImgs = $folders[$activeFolder] ?? [];
      foreach ($activeImgs as $img):
        $imgUrl  = $imgBase . $activeFolder . '/' . $img;
        $isHero  = ($imgUrl === $currentHero);
      ?>
      <div class="img-card <?= $isHero ? 'is-hero' : '' ?>"
           data-img-url="<?= htmlspecialchars($imgUrl) ?>">

        <?php if ($isHero): ?>
        <div class="hero-badge">⌂ HERO</div>
        <?php endif; ?>

        <div class="img-thumb"
             onclick="previewImage('<?= htmlspecialchars($imgUrl) ?>')">
          <img src="<?= htmlspecialchars($imgUrl) ?>"
               alt="<?= htmlspecialchars($img) ?>"
               loading="lazy">
        </div>

        <div style="padding:5px 6px;font-size:.55rem;color:var(--gray-500);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;border-top:1px solid var(--gray-100)">
          <?= htmlspecialchars($img) ?>
        </div>

        <div class="img-actions">
          <button class="btn-preview"
                  onclick="previewImage('<?= htmlspecialchars($imgUrl) ?>')"
                  title="Preview">
            Preview
          </button>
          <button class="btn-hero <?= $isHero ? 'active-hero' : '' ?>"
                  onclick="setHeroImage('<?= htmlspecialchars($imgUrl) ?>', this)"
                  title="Set as homepage hero image">
            ⌂
          </button>
          <button class="btn-delete"
                  onclick="deleteImage('<?= htmlspecialchars($imgUrl) ?>', this)"
                  title="Delete image">
            <?= \App\Helpers\AdminIcon::render('trash', 12) ?>
          </button>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($activeImgs)): ?>
      <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--gray-500);font-size:.85rem">
        No images in this folder. Upload some above.
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /.gallery-main -->
</div><!-- /.gallery-layout -->

<!-- Preview modal -->
<div id="preview-modal" class="admin-modal-overlay">
  <button id="preview-modal-close" onclick="closePreview()"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
  <img id="preview-img" src="" alt="Preview">
</div>

<!-- Toast container -->
<div class="toast-container" id="toasts"></div>

<script>
var BASE       = '<?= BASE_PATH ?>';
var CURRENT_HERO = '<?= htmlspecialchars($currentHero) ?>';

// ── Toast ─────────────────────────────────────────────────────
function toast(msg, type) {
  type = type || 'inf';
  var t = document.createElement('div');
  t.className = 'toast ' + type;
  t.textContent = msg;
  document.getElementById('toasts').appendChild(t);
  setTimeout(function(){ t.remove(); }, 4000);
}

// ── Preview modal ─────────────────────────────────────────────
function previewImage(url) {
  document.getElementById('preview-img').src = url;
  document.getElementById('preview-modal').classList.add('open');
}
function closePreview() {
  document.getElementById('preview-modal').classList.remove('open');
  document.getElementById('preview-img').src = '';
}
document.getElementById('preview-modal').addEventListener('click', function(e){
  if (e.target === this) closePreview();
});
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') closePreview();
});

// ── Set hero image ────────────────────────────────────────────
function setHeroImage(url, btn) {
  btn.disabled = true;
  btn.textContent = '…';

  var fd = new FormData();
  fd.append('image_url', url);

  fetch(BASE + '/api/set_hero.php', {
    method: 'POST',
    body: fd,
    credentials: 'same-origin'
  })
  .then(function(r){ return r.json(); })
  .then(function(d){
    if (d.success) {
      CURRENT_HERO = url;
      updateHeroBadges(url);
      toast('✓ Hero image set — reload homepage to see it', 'ok');
    } else {
      toast('✗ ' + (d.message || 'Failed'), 'err');
    }
    btn.disabled = false;
    btn.textContent = '⌂';
  })
  .catch(function(e){
    toast('Network error: ' + e, 'err');
    btn.disabled = false;
    btn.textContent = '⌂';
  });
}

function updateHeroBadges(activeUrl) {
  document.querySelectorAll('.img-card').forEach(function(card){
    var url      = card.dataset.imgUrl;
    var badge    = card.querySelector('.hero-badge');
    var heroBtn  = card.querySelector('.btn-hero');
    var isHero   = (url === activeUrl);

    // Remove existing badge
    if (badge) badge.remove();
    // Add new badge if this is the hero
    if (isHero) {
      var b = document.createElement('div');
      b.className = 'hero-badge';
      b.textContent = '⌂ HERO';
      card.appendChild(b);
      card.classList.add('is-hero');
    } else {
      card.classList.remove('is-hero');
    }
    // Update button state
    if (heroBtn) {
      heroBtn.classList.toggle('active-hero', isHero);
    }
  });
}

// ── Delete image ──────────────────────────────────────────────
function deleteImage(url, btn) {
  if (!confirm('Delete this image permanently?')) return;
  btn.disabled = true;

  var fd = new FormData();
  fd.append('url', url);

  fetch(BASE + '/api/gallery/delete.php', {
    method: 'POST',
    body: fd,
    credentials: 'same-origin'
  })
  .then(function(r){ return r.json(); })
  .then(function(d){
    if (d.success) {
      var card = btn.closest('.img-card');
      card.style.opacity = '0';
      card.style.transform = 'scale(.9)';
      card.style.transition = 'all .2s';
      setTimeout(function(){ card.remove(); }, 200);
      toast('Image deleted', 'ok');
    } else {
      toast('Delete failed: ' + (d.message || ''), 'err');
      btn.disabled = false;
    }
  })
  .catch(function(){
    toast('Network error', 'err');
    btn.disabled = false;
  });
}

// ── Upload ────────────────────────────────────────────────────
var uploadZone = document.getElementById('upload-zone');
uploadZone.addEventListener('dragover', function(e){
  e.preventDefault();
  this.classList.add('drag-over');
});
uploadZone.addEventListener('dragleave', function(){
  this.classList.remove('drag-over');
});
uploadZone.addEventListener('drop', function(e){
  e.preventDefault();
  this.classList.remove('drag-over');
  uploadFiles(e.dataTransfer.files);
});

function uploadFiles(files) {
  if (!files || !files.length) return;
  var bar     = document.getElementById('upload-bar');
  var barWrap = document.getElementById('upload-bar-wrap');
  var total   = files.length;
  var done    = 0;
  barWrap.style.display = 'block';

  Array.from(files).forEach(function(file){
    var fd = new FormData();
    fd.append('image', file);
    fd.append('folder', '<?= htmlspecialchars($activeFolder) ?>');

    fetch(BASE + '/api/gallery/upload.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
      done++;
      bar.style.width = (done / total * 100) + '%';
      if (d.success) {
        // Add new card to grid
        addImageCard(d.url, d.filename);
        toast('Uploaded: ' + d.filename, 'ok');
      } else {
        toast('Upload failed: ' + (d.message || ''), 'err');
      }
      if (done === total) {
        setTimeout(function(){
          barWrap.style.display = 'none';
          bar.style.width = '0';
        }, 800);
      }
    })
    .catch(function(){
      done++;
      toast('Upload error', 'err');
    });
  });
}

function addImageCard(url, filename) {
  var grid    = document.getElementById('gallery-grid');
  var isHero  = (url === CURRENT_HERO);
  var div     = document.createElement('div');
  div.className = 'img-card' + (isHero ? ' is-hero' : '');
  div.dataset.imgUrl = url;
  div.innerHTML =
    (isHero ? '<div class="hero-badge">⌂ HERO</div>' : '') +
    '<div class="img-thumb" onclick="previewImage(\''+url+'\')">'+
      '<img src="'+url+'" alt="'+filename+'" loading="lazy">'+
    '</div>'+
    '<div style="padding:5px 6px;font-size:.55rem;color:var(--gray-500);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;border-top:1px solid var(--gray-100)">'+filename+'</div>'+
    '<div class="img-actions">'+
      '<button class="btn-preview" onclick="previewImage(\''+url+'\')">Preview</button>'+
      '<button class="btn-hero'+(isHero?' active-hero':'')+'" onclick="setHeroImage(\''+url+'\',this)" title="Set hero">⌂</button>'+
      '<button class="btn-delete" onclick="deleteImage(\''+url+'\',this)" title="Delete">'+<?= $trashIconJs ?>+'</button>'+
    '</div>';
  grid.appendChild(div);
  // Update count
  var countEl = document.getElementById('img-count');
  if (countEl) countEl.textContent = grid.querySelectorAll('.img-card').length + ' images';
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
?>
