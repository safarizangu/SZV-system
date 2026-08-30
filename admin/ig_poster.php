<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

$db = \App\Models\Database::getInstance();
$pageTitle = 'IG Poster Maker';

// Load all active packages with itinerary data
$packages = [];
try {
    $rows = $db->query("
        SELECT p.*, i.days as itin_days, i.includes as itin_includes,
               i.overview as itin_overview, i.base_price_pax,
               i.accommodation_level as itin_level
        FROM packages p
        LEFT JOIN itineraries i ON i.id = p.itinerary_id
        WHERE p.is_active = 1
        ORDER BY p.sort_order
    ")->fetchAll();
    foreach ($rows as $r) {
        $r['gallery']     = json_decode($r['gallery_images'] ?? '[]', true) ?: [];
        $r['includes_arr']= json_decode($r['includes']       ?? '[]', true) ?: [];
        $r['excludes_arr']= json_decode($r['excludes']       ?? '[]', true) ?: [];
        $r['itinerary_arr']= json_decode($r['itinerary']     ?? '[]', true) ?: [];
        $r['itin_days_arr']= json_decode($r['itin_days']     ?? '[]', true) ?: [];
        $r['itin_inc_arr'] = json_decode($r['itin_includes'] ?? '[]', true) ?: [];
        $packages[] = $r;
    }
} catch(\Exception $e) { error_log($e->getMessage()); }

$packagesJson = json_encode($packages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

ob_start();
?>

<style>
/* ── IG Poster Maker Styles ─────────────────────────────── */
.poster-layout {
  display: grid;
  grid-template-columns: 320px 1fr 280px;
  gap: 1.25rem;
  align-items: start;
  min-height: 85vh;
}
.poster-panel { position: sticky; top: calc(var(--topbar-h, 60px) + 1rem); }
.poster-canvas-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}
.poster-canvas-frame {
  position: relative;
  background: #0a0a0a;
  box-shadow: 0 20px 60px rgba(0,0,0,.25);
}
#posterCanvas {
  display: block;
  max-width: 100%;
}
.template-btn {
  padding: .4rem .85rem;
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  border: 1.5px solid var(--gray-200);
  background: #fff;
  cursor: pointer;
  transition: all .15s;
}
.template-btn.active, .template-btn:hover {
  background: var(--black);
  color: var(--white);
  border-color: var(--black);
}
.ai-generating {
  display: none;
  align-items: center;
  gap: .5rem;
  font-size: .75rem;
  color: var(--gray-500);
}
.ai-generating.show { display: flex; }
.ai-spinner {
  width: 16px; height: 16px;
  border: 2px solid var(--gray-200);
  border-top-color: var(--black);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.copy-field {
  width: 100%;
  padding: .6rem .75rem;
  border: 1.5px solid var(--gray-200);
  font-family: inherit;
  font-size: .78rem;
  line-height: 1.6;
  resize: vertical;
  background: var(--gray-50);
  color: var(--gray-800);
}
.copy-field:focus { outline: none; border-color: var(--black); background: #fff; }
.hashtag-chip {
  display: inline-block;
  padding: .2rem .5rem;
  background: var(--gray-100);
  color: var(--gray-700);
  font-size: .65rem;
  font-weight: 600;
  margin: .15rem;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all .12s;
}
.hashtag-chip:hover { background: var(--black); color: var(--white); }
.color-swatch {
  width: 28px; height: 28px;
  border-radius: 50%;
  cursor: pointer;
  border: 2px solid transparent;
  transition: transform .15s;
}
.color-swatch.active, .color-swatch:hover {
  transform: scale(1.2);
  border-color: var(--black);
}
</style>

<div class="poster-layout">

<!-- ════ LEFT: Package Selector & Controls ════ -->
<div class="poster-panel">

  <!-- Package select -->
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-header" style="padding:.85rem 1rem">
      <h3>Select Package</h3>
    </div>
    <div style="padding:.5rem">
      <?php foreach ($packages as $i => $pkg): ?>
      <button onclick="selectPackage(<?= $i ?>)"
              id="pkg-btn-<?= $i ?>"
              class="template-btn <?= $i===0?'active':'' ?>"
              style="display:block;width:100%;text-align:left;margin-bottom:.3rem;padding:.55rem .85rem">
        <div style="font-size:.78rem;font-weight:700"><?= htmlspecialchars($pkg['name']) ?></div>
        <div style="font-size:.6rem;opacity:.5;font-weight:400;margin-top:.1rem">
          <?= $pkg['duration_days'] ?> nights · $<?= number_format((float)$pkg['price_2pax'],0) ?>/pp · <?= ucwords(str_replace('_',' ',$pkg['accommodation_level'])) ?>
        </div>
      </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Template style -->
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-header" style="padding:.85rem 1rem"><h3>Poster Template</h3></div>
    <div class="admin-card-body" style="padding:.75rem">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem;margin-bottom:.75rem">
        <button class="template-btn active" onclick="setTemplate('hero')" id="tpl-hero">Hero</button>
        <button class="template-btn" onclick="setTemplate('split')" id="tpl-split">Split</button>
        <button class="template-btn" onclick="setTemplate('mosaic')" id="tpl-mosaic">Mosaic</button>
        <button class="template-btn" onclick="setTemplate('minimal')" id="tpl-minimal">Minimal</button>
        <button class="template-btn" onclick="setTemplate('itinerary')" id="tpl-itinerary">Itinerary</button>
        <button class="template-btn" onclick="setTemplate('price')" id="tpl-price">Price Card</button>
      </div>

      <!-- Colour themes -->
      <div style="font-size:.58rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--gray-400);margin-bottom:.5rem">Colour Theme</div>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.75rem">
        <div class="color-swatch active" style="background:#0a0a0a" onclick="setTheme('dark')" title="Midnight Black"></div>
        <div class="color-swatch" style="background:#1a3a2a" onclick="setTheme('forest')" title="Forest Green"></div>
        <div class="color-swatch" style="background:#4a2c00" onclick="setTheme('earth')" title="Savannah Earth"></div>
        <div class="color-swatch" style="background:#1a1a3e" onclick="setTheme('navy')" title="Deep Navy"></div>
        <div class="color-swatch" style="background:#5a1a1a" onclick="setTheme('crimson')" title="Dust Crimson"></div>
        <div class="color-swatch" style="background:#f5f0e8" onclick="setTheme('cream')" title="Sand Cream"></div>
      </div>

      <!-- Overlay intensity -->
      <div style="font-size:.58rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--gray-400);margin-bottom:.4rem">Overlay Intensity</div>
      <input type="range" id="overlayRange" min="20" max="85" value="55" oninput="renderPoster()" style="width:100%;margin-bottom:.75rem">

      <!-- Show/hide elements -->
      <div style="font-size:.58rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--gray-400);margin-bottom:.5rem">Show Elements</div>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.75rem;cursor:pointer;margin-bottom:.3rem">
        <input type="checkbox" id="showLogo" checked onchange="renderPoster()"> Logo & Brand
      </label>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.75rem;cursor:pointer;margin-bottom:.3rem">
        <input type="checkbox" id="showPrice" checked onchange="renderPoster()"> Price
      </label>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.75rem;cursor:pointer;margin-bottom:.3rem">
        <input type="checkbox" id="showHighlights" checked onchange="renderPoster()"> Highlights
      </label>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.75rem;cursor:pointer;margin-bottom:.3rem">
        <input type="checkbox" id="showCTA" checked onchange="renderPoster()"> CTA / Website
      </label>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.75rem;cursor:pointer">
        <input type="checkbox" id="showBadge" checked onchange="renderPoster()"> Duration Badge
      </label>
    </div>
  </div>

  <!-- Custom text overrides -->
  <div class="admin-card">
    <div class="admin-card-header" style="padding:.85rem 1rem"><h3>Custom Text</h3></div>
    <div class="admin-card-body" style="padding:.75rem">
      <div style="margin-bottom:.65rem">
        <label style="font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gray-500);display:block;margin-bottom:.3rem">Headline</label>
        <input id="customHeadline" class="copy-field" style="resize:none;height:auto" oninput="renderPoster()" placeholder="Leave blank to use package name">
      </div>
      <div style="margin-bottom:.65rem">
        <label style="font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gray-500);display:block;margin-bottom:.3rem">Tagline</label>
        <input id="customTagline" class="copy-field" style="resize:none;height:auto" oninput="renderPoster()" placeholder="e.g. From $1,650 per person">
      </div>
      <div>
        <label style="font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gray-500);display:block;margin-bottom:.3rem">CTA Text</label>
        <input id="customCTA" class="copy-field" style="resize:none;height:auto" oninput="renderPoster()" placeholder="e.g. safarizanguven.com">
      </div>
    </div>
  </div>

</div>

<!-- ════ CENTRE: Canvas ════ -->
<div class="poster-canvas-wrap">

  <!-- Size toggle -->
  <div style="display:flex;gap:.35rem;margin-bottom:.25rem">
    <button class="template-btn active" onclick="setSize('square')" id="sz-square">1:1 Square</button>
    <button class="template-btn" onclick="setSize('portrait')" id="sz-portrait">4:5 Portrait</button>
    <button class="template-btn" onclick="setSize('story')" id="sz-story">9:16 Story</button>
  </div>

  <div class="poster-canvas-frame" id="canvasFrame">
    <canvas id="posterCanvas" width="1080" height="1080"></canvas>
  </div>

  <!-- Action buttons -->
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;justify-content:center">
    <button onclick="downloadPoster()" class="admin-btn admin-btn--primary" style="padding:.7rem 1.5rem">
      ↓ Download PNG
    </button>
    <button onclick="generateAI()" class="admin-btn admin-btn--amber" style="padding:.7rem 1.5rem" id="ai-btn">
      ✦ Generate AI Caption
    </button>
    <button onclick="cycleImage()" class="admin-btn admin-btn--outline" style="padding:.7rem 1rem">
      🔄 Next Image
    </button>
  </div>

  <div class="ai-generating" id="ai-status">
    <div class="ai-spinner"></div>
    <span id="ai-status-text">Generating caption...</span>
  </div>

</div>

<!-- ════ RIGHT: AI Output & Caption ════ -->
<div class="poster-panel">

  <!-- AI Caption -->
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-header" style="padding:.85rem 1rem">
      <h3>AI Caption</h3>
      <button onclick="generateAI()" class="admin-btn admin-btn--primary" style="font-size:.6rem;padding:.2rem .5rem">Generate</button>
    </div>
    <div class="admin-card-body" style="padding:.75rem">
      <textarea id="captionText" class="copy-field" rows="8"
                placeholder="Click ✦ Generate AI Caption to create an Instagram caption tailored to this package..."></textarea>
      <button onclick="copyField('captionText')" class="admin-btn admin-btn--outline" style="font-size:.62rem;width:100%;justify-content:center;margin-top:.4rem">
        Copy Caption
      </button>
    </div>
  </div>

  <!-- Hashtags -->
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-header" style="padding:.85rem 1rem">
      <h3>Hashtags</h3>
      <button onclick="generateHashtags()" class="admin-btn admin-btn--outline" style="font-size:.6rem;padding:.2rem .5rem">Refresh</button>
    </div>
    <div class="admin-card-body" style="padding:.75rem">
      <div id="hashtagChips" style="margin-bottom:.65rem"></div>
      <textarea id="hashtagText" class="copy-field" rows="4"
                placeholder="Hashtags will appear here..."></textarea>
      <button onclick="copyField('hashtagText')" class="admin-btn admin-btn--outline" style="font-size:.62rem;width:100%;justify-content:center;margin-top:.4rem">
        Copy Hashtags
      </button>
    </div>
  </div>

  <!-- Story copy variants -->
  <div class="admin-card" style="margin-bottom:1rem">
    <div class="admin-card-header" style="padding:.85rem 1rem">
      <h3>Story Variants</h3>
      <button onclick="generateVariants()" class="admin-btn admin-btn--outline" style="font-size:.6rem;padding:.2rem .5rem">Generate</button>
    </div>
    <div class="admin-card-body" style="padding:.75rem">
      <div id="variantsOutput" style="font-size:.75rem;color:var(--gray-500);font-style:italic">
        Click Generate to create 3 different caption styles.
      </div>
    </div>
  </div>

  <!-- Quick stats card -->
  <div class="admin-card">
    <div class="admin-card-header" style="padding:.85rem 1rem"><h3>Package Stats</h3></div>
    <div id="statsPanel" class="admin-card-body" style="padding:.75rem;font-size:.78rem;line-height:2;color:var(--gray-700)">
      Select a package to see stats.
    </div>
  </div>

</div>
</div><!-- /poster-layout -->

<script>
// ── Package data from PHP ────────────────────────────────────
var PACKAGES = <?= $packagesJson ?>;
var BASE = window.BASE_PATH || '';

// ── State ────────────────────────────────────────────────────
var state = {
  pkgIdx:    0,
  template:  'hero',
  theme:     'dark',
  size:      'square',
  imgIdx:    0,
  images:    [],
  loadedImgs:{},
};

var THEMES = {
  dark:    { bg:'#0a0a0a', text:'#ffffff', accent:'#d4af7a', sub:'rgba(255,255,255,.6)' },
  forest:  { bg:'#1a3a2a', text:'#ffffff', accent:'#7ecba1', sub:'rgba(255,255,255,.6)' },
  earth:   { bg:'#4a2c00', text:'#ffffff', accent:'#e8c07a', sub:'rgba(255,255,255,.6)' },
  navy:    { bg:'#1a1a3e', text:'#ffffff', accent:'#7ab4e8', sub:'rgba(255,255,255,.6)' },
  crimson: { bg:'#5a1a1a', text:'#ffffff', accent:'#e87a7a', sub:'rgba(255,255,255,.6)' },
  cream:   { bg:'#f5f0e8', text:'#0a0a0a', accent:'#8b5e3c', sub:'rgba(0,0,0,.5)' },
};

var SIZES = {
  square:   { w:1080, h:1080, label:'1080×1080' },
  portrait: { w:1080, h:1350, label:'1080×1350' },
  story:    { w:1080, h:1920, label:'1080×1920' },
};

var canvas  = document.getElementById('posterCanvas');
var ctx     = canvas.getContext('2d');

// ── Init ─────────────────────────────────────────────────────
window.addEventListener('load', function() {
  selectPackage(0);
});

function selectPackage(idx) {
  state.pkgIdx = idx;
  state.imgIdx = 0;

  // Update button styles
  PACKAGES.forEach(function(_, i) {
    var btn = document.getElementById('pkg-btn-' + i);
    if (btn) {
      btn.classList.toggle('active', i === idx);
    }
  });

  var pkg = PACKAGES[idx];
  state.images = (pkg.gallery || []).filter(Boolean);

  // Update stats panel
  updateStats(pkg);

  // Set default hashtags
  generateHashtags();

  // Load images then render
  loadImages(function() { renderPoster(); });
}

function loadImages(cb) {
  var pkg = PACKAGES[state.pkgIdx];
  var imgs = state.images;
  if (!imgs.length) { cb(); return; }

  var loaded = 0;
  imgs.forEach(function(src) {
    if (state.loadedImgs[src]) { loaded++; if (loaded === imgs.length) cb(); return; }
    var img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload  = function() { state.loadedImgs[src] = img; loaded++; if (loaded === imgs.length) cb(); };
    img.onerror = function() { loaded++; if (loaded === imgs.length) cb(); };
    img.src = src + '?v=' + Date.now();
  });
}

function cycleImage() {
  if (!state.images.length) return;
  state.imgIdx = (state.imgIdx + 1) % state.images.length;
  renderPoster();
}

function setTemplate(tpl) {
  state.template = tpl;
  document.querySelectorAll('[id^="tpl-"]').forEach(function(b) { b.classList.remove('active'); });
  var btn = document.getElementById('tpl-' + tpl);
  if (btn) btn.classList.add('active');
  renderPoster();
}

function setTheme(theme) {
  state.theme = theme;
  document.querySelectorAll('.color-swatch').forEach(function(s) { s.classList.remove('active'); });
  event.target.classList.add('active');
  renderPoster();
}

function setSize(size) {
  state.size = size;
  document.querySelectorAll('[id^="sz-"]').forEach(function(b) { b.classList.remove('active'); });
  document.getElementById('sz-' + size).classList.add('active');
  var s = SIZES[size];
  canvas.width  = s.w;
  canvas.height = s.h;
  renderPoster();
}

// ── MAIN RENDER ───────────────────────────────────────────────
function renderPoster() {
  var pkg   = PACKAGES[state.pkgIdx];
  var theme = THEMES[state.theme];
  var w     = canvas.width;
  var h     = canvas.height;
  var tpl   = state.template;

  ctx.clearRect(0, 0, w, h);

  // Background
  ctx.fillStyle = theme.bg;
  ctx.fillRect(0, 0, w, h);

  // Get current image
  var imgSrc = state.images[state.imgIdx];
  var img    = imgSrc ? state.loadedImgs[imgSrc] : null;

  if (tpl === 'hero')      renderHero(pkg, theme, img, w, h);
  else if (tpl === 'split')     renderSplit(pkg, theme, img, w, h);
  else if (tpl === 'mosaic')    renderMosaic(pkg, theme, img, w, h);
  else if (tpl === 'minimal')   renderMinimal(pkg, theme, img, w, h);
  else if (tpl === 'itinerary') renderItinerary(pkg, theme, img, w, h);
  else if (tpl === 'price')     renderPrice(pkg, theme, img, w, h);
}

// ── TEMPLATE: HERO ────────────────────────────────────────────
function renderHero(pkg, theme, img, w, h) {
  var overlay = parseInt(document.getElementById('overlayRange').value) / 100;

  // Full bleed image
  if (img) {
    drawCoverImage(img, 0, 0, w, h);
  }

  // Gradient overlay (bottom-weighted)
  var grad = ctx.createLinearGradient(0, 0, 0, h);
  grad.addColorStop(0,   hexToRgba(theme.bg, 0.1));
  grad.addColorStop(0.3, hexToRgba(theme.bg, 0.2));
  grad.addColorStop(0.65,hexToRgba(theme.bg, overlay * 0.7));
  grad.addColorStop(1,   hexToRgba(theme.bg, overlay + 0.2));
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, w, h);

  // Top bar (logo area)
  if (document.getElementById('showLogo').checked) {
    drawLogo(theme, w, 52);
  }

  // Duration badge
  if (document.getElementById('showBadge').checked) {
    drawBadge(pkg.duration_days + ' NIGHTS', theme, w - 60, 60);
  }

  // Bottom content area
  var bottomY = h - 60;
  var padding = 64;

  // CTA
  if (document.getElementById('showCTA').checked) {
    var ctaText = document.getElementById('customCTA').value || 'safarizanguven.com';
    ctx.font = 'bold ' + Math.round(w * 0.022) + 'px Arial';
    ctx.fillStyle = theme.accent;
    ctx.letterSpacing = '3px';
    ctx.fillText(ctaText.toUpperCase(), padding, bottomY);
    bottomY -= 48;
  }

  // Price
  if (document.getElementById('showPrice').checked) {
    var priceY = bottomY;
    ctx.font = 'bold ' + Math.round(w * 0.032) + 'px Arial';
    ctx.fillStyle = theme.text;
    var priceStr = 'From $' + Number(pkg.price_2pax).toLocaleString() + ' per person';
    ctx.fillText(priceStr, padding, priceY);
    bottomY -= 56;
  }

  // Main headline
  var headline = document.getElementById('customHeadline').value || pkg.name;
  drawWrappedText(headline.toUpperCase(), padding, bottomY, w - padding * 2,
    Math.round(w * 0.072), theme.text, 'bold', 1.1);
  bottomY -= estimateTextHeight(headline, Math.round(w * 0.072), w - padding * 2);

  // Tagline
  var tagline = document.getElementById('customTagline').value || pkg.short_description || '';
  if (tagline) {
    bottomY -= 20;
    drawWrappedText(tagline, padding, bottomY - 10, w - padding * 2,
      Math.round(w * 0.028), theme.sub, 'normal', 1.5);
  }

  // Highlights strip
  if (document.getElementById('showHighlights').checked) {
    drawHighlightsStrip(pkg, theme, w, h, padding);
  }
}

// ── TEMPLATE: SPLIT ───────────────────────────────────────────
function renderSplit(pkg, theme, img, w, h) {
  var splitX = Math.round(w * 0.52);

  // Image on left
  if (img) {
    ctx.save();
    ctx.beginPath();
    ctx.rect(0, 0, splitX, h);
    ctx.clip();
    drawCoverImage(img, 0, 0, splitX, h);
    ctx.restore();
  }

  // Dark overlay on image
  var grad = ctx.createLinearGradient(splitX - 120, 0, splitX, 0);
  grad.addColorStop(0, 'transparent');
  grad.addColorStop(1, theme.bg);
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, splitX, h);

  // Right panel
  ctx.fillStyle = theme.bg;
  ctx.fillRect(splitX, 0, w - splitX, h);

  var rx = splitX + 60;
  var rw = w - splitX - 60;
  var ry = 80;

  if (document.getElementById('showLogo').checked) {
    drawLogo(theme, w, 52, rx);
    ry = 140;
  }

  // Vertical accent line
  ctx.fillStyle = theme.accent;
  ctx.fillRect(rx - 20, ry, 3, 180);

  // Package name
  var headline = document.getElementById('customHeadline').value || pkg.name;
  ry += 30;
  drawWrappedText(headline.toUpperCase(), rx, ry, rw,
    Math.round(w * 0.046), theme.text, 'bold', 1.15);
  ry += estimateTextHeight(headline, Math.round(w * 0.046), rw) + 30;

  // Short description
  var desc = pkg.short_description || pkg.overview || '';
  if (desc) {
    drawWrappedText(desc, rx, ry, rw, Math.round(w * 0.026), theme.sub, 'normal', 1.6);
    ry += estimateTextHeight(desc, Math.round(w * 0.026), rw) + 50;
  }

  // Key stats
  var stats = [
    ['DURATION',      pkg.duration_days + ' nights'],
    ['ACCOMMODATION', ucwords(pkg.accommodation_level)],
    ['FROM',          '$' + Number(pkg.price_2pax).toLocaleString() + '/pp'],
  ];
  stats.forEach(function(s) {
    ctx.font = 'bold ' + Math.round(w * 0.018) + 'px Arial';
    ctx.fillStyle = theme.accent;
    ctx.fillText(s[0], rx, ry);
    ry += Math.round(w * 0.025);
    ctx.font = 'bold ' + Math.round(w * 0.032) + 'px Arial';
    ctx.fillStyle = theme.text;
    ctx.fillText(s[1], rx, ry);
    ry += Math.round(w * 0.055);
  });

  // Includes (top 4)
  if (document.getElementById('showHighlights').checked) {
    ry += 20;
    ctx.font = 'bold ' + Math.round(w * 0.018) + 'px Arial';
    ctx.fillStyle = theme.accent;
    ctx.fillText('INCLUDES', rx, ry);
    ry += 36;
    (pkg.includes_arr || []).slice(0, 4).forEach(function(inc) {
      ctx.font = Math.round(w * 0.022) + 'px Arial';
      ctx.fillStyle = theme.sub;
      ctx.fillText('✓  ' + inc, rx, ry);
      ry += Math.round(w * 0.034);
    });
  }

  if (document.getElementById('showCTA').checked) {
    var cta = document.getElementById('customCTA').value || 'safarizanguven.com';
    ctx.font = 'bold ' + Math.round(w * 0.02) + 'px Arial';
    ctx.fillStyle = theme.accent;
    ctx.fillText(cta.toUpperCase(), rx, h - 60);
  }

  if (document.getElementById('showBadge').checked) {
    drawBadge(pkg.duration_days + 'N', theme, w - 50, 60);
  }
}

// ── TEMPLATE: MOSAIC ──────────────────────────────────────────
function renderMosaic(pkg, theme, img, w, h) {
  // Grid of images + overlay panel
  var imgs = state.images.map(function(src) { return state.loadedImgs[src]; }).filter(Boolean);

  // Layout: 2 images left column, 1 large right
  if (imgs.length >= 3) {
    var gap = 8;
    var leftW = Math.round(w * 0.42);
    var rightW = w - leftW - gap;
    var topH = Math.round(h * 0.50);
    var botH = h - topH - gap;

    // Top-left
    drawCoverImageClipped(imgs[0], 0, 0, leftW, topH);
    // Bottom-left
    drawCoverImageClipped(imgs[1], 0, topH + gap, leftW, botH);
    // Right (full height)
    drawCoverImageClipped(imgs[2], leftW + gap, 0, rightW, h);
  } else if (img) {
    drawCoverImage(img, 0, 0, w, h);
  }

  // Dark gradient overlay across bottom third
  var grad = ctx.createLinearGradient(0, h * 0.5, 0, h);
  grad.addColorStop(0, 'transparent');
  grad.addColorStop(1, hexToRgba(theme.bg, 0.92));
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, w, h);

  // Top overlay for logo
  var topGrad = ctx.createLinearGradient(0, 0, 0, 180);
  topGrad.addColorStop(0, hexToRgba(theme.bg, 0.7));
  topGrad.addColorStop(1, 'transparent');
  ctx.fillStyle = topGrad;
  ctx.fillRect(0, 0, w, 180);

  if (document.getElementById('showLogo').checked) drawLogo(theme, w, 52);

  var pad = 60;
  var y = h - 56;

  if (document.getElementById('showCTA').checked) {
    var cta = document.getElementById('customCTA').value || 'safarizanguven.com';
    ctx.font = 'bold ' + Math.round(w * 0.022) + 'px Arial';
    ctx.fillStyle = theme.accent;
    ctx.fillText(cta.toUpperCase(), pad, y); y -= 52;
  }
  if (document.getElementById('showPrice').checked) {
    ctx.font = 'bold ' + Math.round(w * 0.028) + 'px Arial';
    ctx.fillStyle = theme.text;
    ctx.fillText('From $' + Number(pkg.price_2pax).toLocaleString() + '/pp', pad, y); y -= 60;
  }
  var headline = document.getElementById('customHeadline').value || pkg.name;
  drawWrappedText(headline.toUpperCase(), pad, y, w - pad * 2, Math.round(w * 0.065), theme.text, 'bold', 1.1);
  y -= estimateTextHeight(headline, Math.round(w * 0.065), w - pad * 2);

  if (document.getElementById('showBadge').checked) {
    drawBadge(pkg.duration_days + ' NIGHTS', theme, w - 60, 60);
  }
}

// ── TEMPLATE: MINIMAL ─────────────────────────────────────────
function renderMinimal(pkg, theme, img, w, h) {
  // Clean typographic layout with small image inset
  ctx.fillStyle = theme.bg;
  ctx.fillRect(0, 0, w, h);

  var pad = 80;

  // Large number accent (days)
  ctx.save();
  ctx.font = 'bold ' + Math.round(h * 0.28) + 'px Arial';
  ctx.fillStyle = hexToRgba(theme.accent, 0.12);
  ctx.textAlign = 'right';
  ctx.fillText(pkg.duration_days, w - pad + 20, Math.round(h * 0.45));
  ctx.textAlign = 'left';
  ctx.restore();

  // Image inset (right side, top area)
  if (img) {
    ctx.save();
    ctx.beginPath();
    roundRect(ctx, w - pad - 260, pad, 260, 320, 4);
    ctx.clip();
    drawCoverImage(img, w - pad - 260, pad, 260, 320);
    ctx.restore();
  }

  // Accent bar
  ctx.fillStyle = theme.accent;
  ctx.fillRect(pad, Math.round(h * 0.12), 60, 4);

  var y = Math.round(h * 0.18);

  // Eyebrow
  ctx.font = 'bold ' + Math.round(w * 0.018) + 'px Arial';
  ctx.fillStyle = theme.accent;
  ctx.fillText('SAFARI ZANGU VENTURES', pad, y); y += 50;

  // Headline
  var headline = document.getElementById('customHeadline').value || pkg.name;
  drawWrappedText(headline.toUpperCase(), pad, y, w - pad * 2 - 280,
    Math.round(w * 0.075), theme.text, 'bold', 1.1);
  y += estimateTextHeight(headline, Math.round(w * 0.075), w - pad * 2 - 280) + 40;

  // Tagline
  var tagline = document.getElementById('customTagline').value || pkg.short_description || '';
  if (tagline) {
    drawWrappedText(tagline, pad, y, w - pad * 2 - 100, Math.round(w * 0.03), theme.sub, 'normal', 1.6);
    y += estimateTextHeight(tagline, Math.round(w * 0.03), w - pad * 2 - 100) + 60;
  }

  // Divider
  ctx.fillStyle = hexToRgba(theme.text, 0.15);
  ctx.fillRect(pad, y, w - pad * 2, 1);
  y += 40;

  // Key facts in a row
  var facts = [
    pkg.duration_days + ' Nights',
    '$' + Number(pkg.price_2pax).toLocaleString() + '/pp',
    ucwords(pkg.accommodation_level),
  ];
  var colW = (w - pad * 2) / facts.length;
  facts.forEach(function(fact, i) {
    var cx = pad + i * colW;
    ctx.font = 'bold ' + Math.round(w * 0.035) + 'px Arial';
    ctx.fillStyle = theme.text;
    ctx.fillText(fact, cx, y);
    ctx.font = Math.round(w * 0.02) + 'px Arial';
    ctx.fillStyle = theme.sub;
    ctx.fillText(['Duration','From','Accommodation'][i], cx, y + 36);
  });

  y += 100;
  // Includes
  if (document.getElementById('showHighlights').checked) {
    (pkg.includes_arr || []).slice(0, 5).forEach(function(inc) {
      ctx.font = Math.round(w * 0.024) + 'px Arial';
      ctx.fillStyle = theme.sub;
      ctx.fillText('— ' + inc, pad, y);
      y += Math.round(w * 0.038);
    });
  }

  // CTA at bottom
  if (document.getElementById('showCTA').checked) {
    var cta = document.getElementById('customCTA').value || 'safarizanguven.com';
    ctx.font = 'bold ' + Math.round(w * 0.022) + 'px Arial';
    ctx.fillStyle = theme.accent;
    ctx.fillText(cta.toUpperCase(), pad, h - 60);
  }

  if (document.getElementById('showLogo').checked) {
    ctx.font = 'bold ' + Math.round(w * 0.018) + 'px Arial';
    ctx.fillStyle = hexToRgba(theme.text, 0.3);
    ctx.textAlign = 'right';
    ctx.fillText('SAFARI ZANGU VENTURES', w - pad, h - 60);
    ctx.textAlign = 'left';
  }
}

// ── TEMPLATE: ITINERARY ───────────────────────────────────────
function renderItinerary(pkg, theme, img, w, h) {
  var overlay = parseInt(document.getElementById('overlayRange').value) / 100;

  if (img) {
    drawCoverImage(img, 0, 0, w, h);
    ctx.fillStyle = hexToRgba(theme.bg, overlay + 0.15);
    ctx.fillRect(0, 0, w, h);
  }

  var pad = 70;
  var y   = 80;

  if (document.getElementById('showLogo').checked) {
    drawLogo(theme, w, y); y += 80;
  }

  // Package name
  var headline = document.getElementById('customHeadline').value || pkg.name;
  ctx.font = 'bold ' + Math.round(w * 0.056) + 'px Arial';
  ctx.fillStyle = theme.text;
  ctx.fillText(headline.toUpperCase(), pad, y); y += 60;

  // Accent line
  ctx.fillStyle = theme.accent;
  ctx.fillRect(pad, y, 80, 3);
  y += 40;

  // Duration + price
  ctx.font = 'bold ' + Math.round(w * 0.028) + 'px Arial';
  ctx.fillStyle = theme.accent;
  ctx.fillText(pkg.duration_days + ' NIGHTS  ·  FROM $' + Number(pkg.price_2pax).toLocaleString() + '/PP', pad, y);
  y += 70;

  // Day-by-day itinerary
  var days = pkg.itin_days_arr.length ? pkg.itin_days_arr : pkg.itinerary_arr;
  var maxDays = Math.min(days.length, state.size === 'story' ? 7 : 5);
  var lineH = Math.round((h - y - 100) / (maxDays + 0.5));

  days.slice(0, maxDays).forEach(function(day, i) {
    var dy = y + i * lineH;

    // Day circle
    ctx.fillStyle = i === 0 ? theme.accent : hexToRgba(theme.accent, 0.4);
    ctx.beginPath();
    ctx.arc(pad + 18, dy - 8, 18, 0, Math.PI * 2);
    ctx.fill();
    ctx.font = 'bold ' + Math.round(w * 0.022) + 'px Arial';
    ctx.fillStyle = theme.bg;
    ctx.textAlign = 'center';
    ctx.fillText(day.day || (i+1), pad + 18, dy - 2);
    ctx.textAlign = 'left';

    // Connector line
    if (i < maxDays - 1) {
      ctx.fillStyle = hexToRgba(theme.accent, 0.25);
      ctx.fillRect(pad + 16, dy + 12, 4, lineH - 28);
    }

    // Title
    ctx.font = 'bold ' + Math.round(w * 0.026) + 'px Arial';
    ctx.fillStyle = theme.text;
    ctx.fillText((day.title || 'Day ' + (i+1)).substring(0, 38), pad + 50, dy - 4);

    // Location/subtitle
    if (day.location || day.description) {
      var sub = (day.location || day.description || '').substring(0, 45);
      ctx.font = Math.round(w * 0.02) + 'px Arial';
      ctx.fillStyle = theme.sub;
      ctx.fillText(sub, pad + 50, dy + 22);
    }
  });

  // CTA bottom
  if (document.getElementById('showCTA').checked) {
    var cta = document.getElementById('customCTA').value || 'safarizanguven.com';
    ctx.font = 'bold ' + Math.round(w * 0.022) + 'px Arial';
    ctx.fillStyle = theme.accent;
    ctx.fillText(cta.toUpperCase(), pad, h - 52);
  }
}

// ── TEMPLATE: PRICE CARD ──────────────────────────────────────
function renderPrice(pkg, theme, img, w, h) {
  var overlay = parseInt(document.getElementById('overlayRange').value) / 100;

  if (img) {
    drawCoverImage(img, 0, 0, w, h);
    ctx.fillStyle = hexToRgba(theme.bg, overlay);
    ctx.fillRect(0, 0, w, h);
  }

  var pad = 70;

  if (document.getElementById('showLogo').checked) drawLogo(theme, w, 52);
  if (document.getElementById('showBadge').checked) drawBadge(pkg.duration_days + ' NIGHTS', theme, w - 60, 60);

  // Central price card
  var cardW = Math.round(w * 0.78);
  var cardH = Math.round(h * 0.52);
  var cardX = (w - cardW) / 2;
  var cardY = (h - cardH) / 2;

  // Card background
  ctx.fillStyle = hexToRgba(theme.bg, 0.88);
  roundRect(ctx, cardX, cardY, cardW, cardH, 4);
  ctx.fill();

  // Card accent top border
  ctx.fillStyle = theme.accent;
  ctx.fillRect(cardX, cardY, cardW, 4);

  var cy = cardY + 60;
  var cx = cardX + 55;

  // Package name
  var headline = document.getElementById('customHeadline').value || pkg.name;
  ctx.font = 'bold ' + Math.round(w * 0.042) + 'px Arial';
  ctx.fillStyle = theme.text;
  ctx.textAlign = 'center';
  ctx.fillText(headline.toUpperCase(), w/2, cy); cy += 55;

  // Short desc
  ctx.font = Math.round(w * 0.024) + 'px Arial';
  ctx.fillStyle = theme.sub;
  var desc = (pkg.short_description || '').substring(0, 60);
  ctx.fillText(desc, w/2, cy); cy += 60;

  // Divider
  ctx.fillStyle = hexToRgba(theme.accent, 0.3);
  ctx.fillRect(cx, cy, cardW - 110, 1); cy += 45;

  // Prices row
  var priceTiers = [
    {label: 'SOLO', price: pkg.price_1pax},
    {label: 'COUPLE', price: pkg.price_2pax},
    {label: 'GROUP 3+', price: pkg.price_3pax},
  ];
  var tierW = (cardW - 80) / 3;
  priceTiers.forEach(function(tier, i) {
    var tx = cardX + 40 + i * tierW + tierW/2;

    ctx.font = 'bold ' + Math.round(w * 0.016) + 'px Arial';
    ctx.fillStyle = theme.accent;
    ctx.fillText(tier.label, tx, cy);

    ctx.font = 'bold ' + Math.round(w * 0.044) + 'px Arial';
    ctx.fillStyle = theme.text;
    ctx.fillText('$' + Number(tier.price).toLocaleString(), tx, cy + 48);

    ctx.font = Math.round(w * 0.018) + 'px Arial';
    ctx.fillStyle = theme.sub;
    ctx.fillText('per person', tx, cy + 76);
  });
  ctx.textAlign = 'left';
  cy += 120;

  // Key includes
  if (document.getElementById('showHighlights').checked) {
    ctx.textAlign = 'center';
    var incl = (pkg.includes_arr || []).slice(0, 3).join('  ·  ');
    ctx.font = Math.round(w * 0.02) + 'px Arial';
    ctx.fillStyle = theme.sub;
    ctx.fillText(incl, w/2, cy);
    ctx.textAlign = 'left';
  }

  // CTA below card
  if (document.getElementById('showCTA').checked) {
    var cta = document.getElementById('customCTA').value || 'safarizanguven.com';
    ctx.font = 'bold ' + Math.round(w * 0.024) + 'px Arial';
    ctx.fillStyle = theme.accent;
    ctx.textAlign = 'center';
    ctx.fillText(cta.toUpperCase(), w/2, cardY + cardH + 56);
    ctx.textAlign = 'left';
  }
}

// ── DRAWING HELPERS ───────────────────────────────────────────
function drawCoverImage(img, x, y, w, h) {
  if (!img) return;
  var scale = Math.max(w / img.width, h / img.height);
  var sw = img.width * scale;
  var sh = img.height * scale;
  var sx = x + (w - sw) / 2;
  var sy = y + (h - sh) / 2;
  ctx.drawImage(img, sx, sy, sw, sh);
}

function drawCoverImageClipped(img, x, y, w, h) {
  if (!img) return;
  ctx.save();
  ctx.beginPath();
  ctx.rect(x, y, w, h);
  ctx.clip();
  drawCoverImage(img, x, y, w, h);
  ctx.restore();
}

function drawLogo(theme, canvasW, y, x) {
  x = x || 60;
  // Brand wordmark
  ctx.font = 'bold ' + Math.round(canvasW * 0.022) + 'px Arial';
  ctx.fillStyle = theme.text;
  ctx.fillText('SAFARI ZANGU VENTURES', x, y);
}

function drawBadge(text, theme, cx, cy) {
  var fs = Math.round(canvas.width * 0.018);
  ctx.font = 'bold ' + fs + 'px Arial';
  var tw = ctx.measureText(text).width;
  var bw = tw + 30;
  var bh = fs + 20;

  ctx.fillStyle = theme.accent;
  roundRect(ctx, cx - bw/2, cy - bh/2, bw, bh, 2);
  ctx.fill();

  ctx.fillStyle = theme.bg;
  ctx.textAlign = 'center';
  ctx.fillText(text, cx, cy + fs/3);
  ctx.textAlign = 'left';
}

function drawHighlightsStrip(pkg, theme, w, h, pad) {
  var incl = (pkg.includes_arr || []).slice(0, 3);
  if (!incl.length) return;

  var stripH = Math.round(h * 0.08);
  var stripY = Math.round(h * 0.68);

  ctx.fillStyle = hexToRgba(theme.bg, 0.7);
  ctx.fillRect(0, stripY, w, stripH);

  var colW = (w - pad * 2) / incl.length;
  var fs = Math.round(w * 0.022);
  ctx.font = fs + 'px Arial';
  ctx.textAlign = 'center';
  incl.forEach(function(inc, i) {
    ctx.fillStyle = theme.accent;
    ctx.fillText('✓', pad + i * colW + 14, stripY + stripH/2 + fs/3);
    ctx.fillStyle = theme.sub;
    ctx.fillText(inc.substring(0, 26), pad + i * colW + colW/2, stripY + stripH/2 + fs/3);
  });
  ctx.textAlign = 'left';
}

function drawWrappedText(text, x, y, maxW, fontSize, color, weight, lineHeightMult) {
  ctx.font = (weight || 'normal') + ' ' + fontSize + 'px Arial';
  ctx.fillStyle = color;
  var lh = fontSize * (lineHeightMult || 1.3);
  var words = text.split(' ');
  var line = '';
  var cy = y;
  words.forEach(function(word) {
    var test = line + (line ? ' ' : '') + word;
    if (ctx.measureText(test).width > maxW && line) {
      ctx.fillText(line, x, cy);
      line = word;
      cy -= lh;
    } else {
      line = test;
    }
  });
  if (line) ctx.fillText(line, x, cy);
}

function estimateTextHeight(text, fontSize, maxW) {
  ctx.font = 'bold ' + fontSize + 'px Arial';
  var words = text.split(' ');
  var line = '';
  var lines = 1;
  words.forEach(function(word) {
    var test = line + (line ? ' ' : '') + word;
    if (ctx.measureText(test).width > maxW && line) {
      lines++;
      line = word;
    } else {
      line = test;
    }
  });
  return lines * fontSize * 1.2;
}

function hexToRgba(hex, alpha) {
  var r = parseInt(hex.slice(1,3),16);
  var g = parseInt(hex.slice(3,5),16);
  var b = parseInt(hex.slice(5,7),16);
  return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
}

function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.lineTo(x + w - r, y);
  ctx.quadraticCurveTo(x + w, y, x + w, y + r);
  ctx.lineTo(x + w, y + h - r);
  ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
  ctx.lineTo(x + r, y + h);
  ctx.quadraticCurveTo(x, y + h, x, y + h - r);
  ctx.lineTo(x, y + r);
  ctx.quadraticCurveTo(x, y, x + r, y);
  ctx.closePath();
}

function ucwords(str) {
  return (str || '').replace(/[_-]/g,' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
}

// ── DOWNLOAD ─────────────────────────────────────────────────
function downloadPoster() {
  var pkg  = PACKAGES[state.pkgIdx];
  var link = document.createElement('a');
  link.download = 'szv-' + (pkg.slug || 'poster') + '-' + state.template + '-' + state.size + '.png';
  link.href = canvas.toDataURL('image/png');
  link.click();
  toast('Poster downloaded!');
}

// ── AI CAPTION GENERATION ────────────────────────────────────
async function generateAI() {
  var pkg = PACKAGES[state.pkgIdx];
  var btn = document.getElementById('ai-btn');
  var status = document.getElementById('ai-status');
  btn.disabled = true;
  status.classList.add('show');
  document.getElementById('ai-status-text').textContent = 'Generating caption...';
  document.getElementById('captionText').value = '';

  var days = (pkg.itin_days_arr || pkg.itinerary_arr || []).map(function(d) {
    return 'Day ' + (d.day||'') + ': ' + (d.title||'') + (d.location ? ' (' + d.location + ')' : '');
  }).join('\n');

  var prompt = "You are a luxury safari travel copywriter for Safari Zangu Ventures, based in Arusha, Tanzania. Write a captivating Instagram caption for this safari package.\n\nPackage: " + pkg.name + "\nDuration: " + pkg.duration_days + " nights\nPrice: From $" + pkg.price_2pax + " per person\nAccommodation: " + ucwords(pkg.accommodation_level) + "\nDescription: " + (pkg.short_description || pkg.overview || '') + "\nItinerary highlights:\n" + days + "\nTop inclusions: " + (pkg.includes_arr || []).slice(0,5).join(', ') + "\n\nWrite an Instagram caption that:\n- Opens with an evocative hook (no generic openers like 'Are you ready')\n- Mentions 2-3 specific wildlife or landscape highlights of this route\n- Includes the price naturally\n- Has a clear call to action with 'safarizanguven.com'\n- Uses 3-5 relevant emojis tastefully integrated\n- Is 150-220 words\n- Feels premium and aspirational, not salesy\n- Ends with a question or invitation that encourages engagement\n\nReturn ONLY the caption, no preamble.";

  try {
    var response = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model: 'claude-sonnet-4-6',
        max_tokens: 1000,
        messages: [{ role: 'user', content: prompt }]
      })
    });
    var data = await response.json();
    var caption = '';
    if (data.content && data.content[0]) {
      caption = data.content[0].text || '';
    }
    document.getElementById('captionText').value = caption;
    generateHashtags();
  } catch(e) {
    document.getElementById('captionText').value = 'Error generating caption. Please check your connection.';
  }

  btn.disabled = false;
  status.classList.remove('show');
}

async function generateVariants() {
  var pkg = PACKAGES[state.pkgIdx];
  var el = document.getElementById('variantsOutput');
  el.innerHTML = '<div style="color:var(--gray-400);font-size:.75rem">Generating 3 variants...</div>';

  var prompt = "Write 3 short Instagram caption variants for the " + pkg.name + " safari package (" + pkg.duration_days + " nights, from $" + pkg.price_2pax + "/pp) by Safari Zangu Ventures, Tanzania. Each variant should have a different tone:\n1. EMOTIONAL — evocative, poetic, focuses on the feeling\n2. INFORMATIVE — highlights key facts, itinerary, inclusions\n3. URGENT — creates FOMO, limited availability angle\n\nEach caption should be 60-80 words max. Format as:\n[EMOTIONAL]\n...\n\n[INFORMATIVE]\n...\n\n[URGENT]\n...";

  try {
    var r = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({model:'claude-sonnet-4-6',max_tokens:1000,messages:[{role:'user',content:prompt}]})
    });
    var d = await r.json();
    var text = d.content && d.content[0] ? d.content[0].text : '';

    // Parse variants
    var variants = text.split(/\[(?:EMOTIONAL|INFORMATIVE|URGENT)\]/g).filter(Boolean).map(function(s){ return s.trim(); });
    var labels   = ['Emotional', 'Informative', 'Urgent'];

    el.innerHTML = variants.map(function(v,i) {
      return '<div style="margin-bottom:1rem;padding:.75rem;background:var(--gray-50);border:1px solid var(--gray-200)">'
           + '<div style="font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--gray-400);margin-bottom:.4rem">' + (labels[i]||'Variant '+(i+1)) + '</div>'
           + '<div style="font-size:.75rem;line-height:1.7;color:var(--gray-700)">' + v.replace(/\n/g,'<br>') + '</div>'
           + '<button onclick="copyText(' + JSON.stringify(v) + ')" style="font-size:.6rem;margin-top:.35rem;background:none;border:1px solid var(--gray-200);padding:.2rem .5rem;cursor:pointer">Copy</button>'
           + '</div>';
    }).join('');
  } catch(e) {
    el.innerHTML = '<div style="color:#dc2626;font-size:.75rem">Error: ' + e.message + '</div>';
  }
}

function generateHashtags() {
  var pkg = PACKAGES[state.pkgIdx];
  var base = [
    '#TanzaniaSafari', '#SafariZangu', '#AfricaSafari',
    '#Serengeti', '#Ngorongoro', '#Tanzania',
    '#WildlifePhotography', '#SafariLife', '#BigFive',
    '#AfricaWildlife', '#TravelAfrica', '#LuxurySafari',
    '#VisitTanzania', '#AfricanSafari', '#Arusha',
    '#SafariGuide', '#KilimanjaroRegion', '#BushLife',
  ];

  var pkgTags = {
    'classic-safari':  ['#ClassicSafari', '#Tarangire', '#SerengetiPlains'],
    'luxury-safari':   ['#LuxuryTravel', '#BalloonsafariAfrica', '#ExclusiveLodge'],
    'comfort-safari':  ['#LakeManyara', '#MidRangeSafari', '#TreeClimbingLions'],
    'safari-zanzibar': ['#Zanzibar', '#SafariAndBeach', '#ZanzibarIsland'],
    'cultural-safari': ['#CulturalTanzania', '#MaasaiCulture', '#CoffeeFarm'],
  };

  var extra = pkgTags[pkg.slug] || [];
  var all   = base.concat(extra);
  // Shuffle and pick 25
  all.sort(function(){ return Math.random() - 0.5; });
  var chosen = all.slice(0, 25);

  // Render chips
  var chipsHtml = chosen.map(function(h) {
    return '<span class="hashtag-chip" onclick="toggleHashtag(this)">' + h + '</span>';
  }).join('');
  document.getElementById('hashtagChips').innerHTML = chipsHtml;

  // Set textarea
  document.getElementById('hashtagText').value = chosen.join(' ');
}

function toggleHashtag(el) {
  el.classList.toggle('active');
  el.style.background = el.classList.contains('active') ? 'var(--black)' : '';
  el.style.color = el.classList.contains('active') ? 'var(--white)' : '';
  // Rebuild textarea from active chips
  var active = [];
  document.querySelectorAll('.hashtag-chip.active').forEach(function(c){ active.push(c.textContent); });
  // If none active, use all
  if (!active.length) {
    var all = [];
    document.querySelectorAll('.hashtag-chip').forEach(function(c){ all.push(c.textContent); });
    document.getElementById('hashtagText').value = all.join(' ');
  } else {
    document.getElementById('hashtagText').value = active.join(' ');
  }
}

function updateStats(pkg) {
  var html = '';
  html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.75rem">';
  [
    ['Duration', pkg.duration_days + ' nights'],
    ['1 Person', '$' + Number(pkg.price_1pax).toLocaleString()],
    ['2 People', '$' + Number(pkg.price_2pax).toLocaleString() + '/pp'],
    ['Group 3+', '$' + Number(pkg.price_3pax).toLocaleString() + '/pp'],
  ].forEach(function(row) {
    html += '<div style="background:var(--gray-50);padding:.5rem .65rem">'
          + '<div style="font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gray-400)">' + row[0] + '</div>'
          + '<div style="font-size:.88rem;font-weight:700;color:var(--black)">' + row[1] + '</div>'
          + '</div>';
  });
  html += '</div>';
  html += '<div style="font-size:.72rem;color:var(--gray-600);margin-bottom:.4rem"><strong>Level:</strong> ' + ucwords(pkg.accommodation_level) + '</div>';
  html += '<div style="font-size:.72rem;color:var(--gray-600);margin-bottom:.4rem"><strong>Images:</strong> ' + (pkg.gallery || []).length + ' photos</div>';
  var days = pkg.itin_days_arr || pkg.itinerary_arr || [];
  html += '<div style="font-size:.72rem;color:var(--gray-600)"><strong>Itinerary:</strong> ' + days.length + ' days mapped</div>';
  document.getElementById('statsPanel').innerHTML = html;
}

// ── UTILITIES ─────────────────────────────────────────────────
function copyField(id) {
  var el = document.getElementById(id);
  el.select();
  navigator.clipboard.writeText(el.value).then(function(){ toast('Copied!'); }).catch(function(){ document.execCommand('copy'); toast('Copied!'); });
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(function(){ toast('Copied!'); });
}

function toast(msg) {
  var t = document.createElement('div');
  t.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;background:#16a34a;color:#fff;padding:.65rem 1.1rem;font-size:.78rem;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.2)';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(function(){ t.remove(); }, 2500);
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
?>
