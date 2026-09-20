<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Poster Maker';

$packages = [];
try {
    $rows = $db->query("SELECT p.*, i.days as itin_days, i.includes as itin_inc, i.overview as itin_overview
        FROM packages p LEFT JOIN itineraries i ON i.id=p.itinerary_id
        WHERE p.is_active=1 ORDER BY p.sort_order")->fetchAll();
    foreach ($rows as $r) {
        $r['gallery']      = json_decode($r['gallery_images']??'[]',true)?:[];
        $r['includes_arr'] = json_decode($r['includes']??'[]',true)?:[];
        $r['excludes_arr'] = json_decode($r['excludes']??'[]',true)?:[];
        $r['itin_arr']     = json_decode($r['itinerary']??'[]',true)?:[];
        $r['itin_days_arr']= json_decode($r['itin_days']??'[]',true)?:[];
        $r['itin_inc_arr'] = json_decode($r['itin_inc']??'[]',true)?:[];
        $packages[] = $r;
    }
} catch(\Exception $e){}

$pkgsJson = json_encode($packages, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);
$logoUrl  = BASE_PATH . '/assets/images/logo.jpeg';
ob_start();
?>
<style>
:root {
  --pm-bg: #111;
  --pm-panel: #1a1a1a;
  --pm-border: #2a2a2a;
  --pm-text: #e8e8e8;
  --pm-muted: #666;
  --pm-accent: #d4af7a;
  --pm-active: #d4af7a;
}
.pm-wrap {
  display: grid;
  grid-template-columns: 260px 1fr 280px;
  gap: 0;
  height: calc(100vh - var(--topbar-h, 60px));
  background: var(--pm-bg);
  margin: -1.5rem;
  overflow: hidden;
}
.pm-sidebar {
  background: var(--pm-panel);
  border-right: 1px solid var(--pm-border);
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}
.pm-right {
  background: var(--pm-panel);
  border-left: 1px solid var(--pm-border);
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}
.pm-centre {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  padding: 1.25rem;
  overflow-y: auto;
  background: #0a0a0a;
  gap: .85rem;
}
.pm-section {
  border-bottom: 1px solid var(--pm-border);
  padding: .85rem 1rem;
}
.pm-section-title {
  font-size: .55rem;
  font-weight: 700;
  letter-spacing: .2em;
  text-transform: uppercase;
  color: var(--pm-muted);
  margin-bottom: .65rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.pm-pkg-btn {
  display: block;
  width: 100%;
  text-align: left;
  padding: .6rem .75rem;
  background: none;
  border: 1px solid transparent;
  color: var(--pm-text);
  cursor: pointer;
  margin-bottom: .2rem;
  transition: all .12s;
  border-radius: 2px;
}
.pm-pkg-btn:hover { background: rgba(255,255,255,.05); border-color: var(--pm-border); }
.pm-pkg-btn.active { background: rgba(212,175,122,.12); border-color: var(--pm-accent); }
.pm-pkg-name { font-size: .8rem; font-weight: 600; color: var(--pm-text); }
.pm-pkg-meta { font-size: .62rem; color: var(--pm-muted); margin-top: .15rem; }

.pm-tpl-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: .35rem;
}
.pm-tpl-btn {
  padding: .5rem .3rem;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--pm-border);
  color: var(--pm-muted);
  font-size: .6rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  cursor: pointer;
  text-align: center;
  transition: all .12s;
  border-radius: 2px;
}
.pm-tpl-btn:hover { background: rgba(255,255,255,.08); color: var(--pm-text); }
.pm-tpl-btn.active { background: var(--pm-accent); color: #000; border-color: var(--pm-accent); }

.pm-size-row {
  display: flex;
  gap: .35rem;
}
.pm-size-btn {
  flex: 1;
  padding: .4rem .3rem;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--pm-border);
  color: var(--pm-muted);
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  cursor: pointer;
  text-align: center;
  transition: all .12s;
  border-radius: 2px;
}
.pm-size-btn.active { background: var(--pm-accent); color: #000; border-color: var(--pm-accent); }

.pm-color-row {
  display: flex;
  gap: .4rem;
  flex-wrap: wrap;
}
.pm-swatch {
  width: 26px; height: 26px;
  border-radius: 50%;
  cursor: pointer;
  border: 2px solid transparent;
  transition: transform .12s, border-color .12s;
  position: relative;
}
.pm-swatch.active, .pm-swatch:hover {
  transform: scale(1.2);
  border-color: var(--pm-accent) !important;
}

.pm-label {
  font-size: .6rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--pm-muted);
  display: block;
  margin-bottom: .3rem;
}
.pm-input {
  width: 100%;
  background: rgba(255,255,255,.06);
  border: 1px solid var(--pm-border);
  color: var(--pm-text);
  padding: .45rem .6rem;
  font-size: .78rem;
  font-family: inherit;
  border-radius: 2px;
  outline: none;
  box-sizing: border-box;
}
.pm-input:focus { border-color: var(--pm-accent); }
.pm-textarea {
  width: 100%;
  background: rgba(255,255,255,.06);
  border: 1px solid var(--pm-border);
  color: var(--pm-text);
  padding: .45rem .6rem;
  font-size: .75rem;
  font-family: inherit;
  border-radius: 2px;
  outline: none;
  resize: vertical;
  min-height: 80px;
  box-sizing: border-box;
  line-height: 1.6;
}
.pm-textarea:focus { border-color: var(--pm-accent); }
.pm-range {
  width: 100%;
  accent-color: var(--pm-accent);
  margin: .4rem 0;
}
.pm-toggle {
  display: flex;
  align-items: center;
  gap: .5rem;
  cursor: pointer;
  padding: .25rem 0;
}
.pm-toggle span { font-size: .75rem; color: var(--pm-text); }
.pm-toggle input[type=checkbox] { accent-color: var(--pm-accent); width: 14px; height: 14px; }

.pm-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  padding: .55rem 1rem;
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  border: 1px solid var(--pm-border);
  background: rgba(255,255,255,.06);
  color: var(--pm-text);
  cursor: pointer;
  transition: all .12s;
  border-radius: 2px;
}
.pm-btn:hover { background: rgba(255,255,255,.12); border-color: var(--pm-text); }
.pm-btn--primary { background: var(--pm-accent); color: #000; border-color: var(--pm-accent); }
.pm-btn--primary:hover { background: #c9a265; }
.pm-btn--ai { background: #7c3aed; color: #fff; border-color: #7c3aed; }
.pm-btn--ai:hover { background: #6d28d9; }
.pm-btn--sm { padding: .3rem .6rem; font-size: .6rem; }
.pm-btn-row { display: flex; gap: .4rem; flex-wrap: wrap; margin-top: .75rem; }

.pm-canvas-frame {
  background: #000;
  box-shadow: 0 8px 40px rgba(0,0,0,.6);
  position: relative;
  max-width: 100%;
}
#posterCanvas { display: block; max-width: 100%; }

.pm-tool-row { display: flex; gap: .35rem; flex-wrap: wrap; }

.pm-chip {
  display: inline-block;
  padding: .18rem .45rem;
  background: rgba(255,255,255,.08);
  color: var(--pm-muted);
  font-size: .62rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid var(--pm-border);
  border-radius: 2px;
  transition: all .1s;
  margin: .1rem;
}
.pm-chip:hover, .pm-chip.active {
  background: var(--pm-accent);
  color: #000;
  border-color: var(--pm-accent);
}

.pm-spinner {
  display: inline-block;
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,.2);
  border-top-color: var(--pm-accent);
  border-radius: 50%;
  animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.pm-overlay-preview {
  background: rgba(212,175,122,.1);
  border: 1px solid rgba(212,175,122,.3);
  padding: .5rem .65rem;
  font-size: .72rem;
  color: var(--pm-accent);
  margin-top: .5rem;
  line-height: 1.6;
}

.pm-img-strip {
  display: flex;
  gap: .35rem;
  overflow-x: auto;
  padding: .25rem 0;
}
.pm-img-thumb {
  width: 52px; height: 52px;
  object-fit: cover;
  cursor: pointer;
  border: 2px solid transparent;
  flex-shrink: 0;
  border-radius: 2px;
  transition: border-color .12s;
}
.pm-img-thumb.active { border-color: var(--pm-accent); }
.pm-img-thumb:hover { border-color: rgba(212,175,122,.5); }

.pm-font-row { display: flex; gap: .35rem; flex-wrap: wrap; }
.pm-font-btn {
  padding: .3rem .55rem;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--pm-border);
  color: var(--pm-muted);
  font-size: .68rem;
  cursor: pointer;
  border-radius: 2px;
  transition: all .12s;
}
.pm-font-btn.active { background: var(--pm-accent); color: #000; border-color: var(--pm-accent); }

.pm-stat-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .35rem;
  margin-bottom: .65rem;
}
.pm-stat-cell {
  background: rgba(255,255,255,.04);
  padding: .5rem .65rem;
  border: 1px solid var(--pm-border);
  border-radius: 2px;
}
.pm-stat-label { font-size: .55rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--pm-muted); }
.pm-stat-val { font-size: .88rem; font-weight: 700; color: var(--pm-text); margin-top: .15rem; }

.pm-caption-out {
  background: rgba(255,255,255,.04);
  border: 1px solid var(--pm-border);
  color: var(--pm-text);
  padding: .65rem .75rem;
  font-size: .75rem;
  line-height: 1.7;
  border-radius: 2px;
  min-height: 120px;
  white-space: pre-wrap;
  cursor: text;
}

.pm-version-row {
  display: flex;
  gap: .25rem;
}
.pm-version-btn {
  flex: 1;
  padding: .35rem .4rem;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--pm-border);
  color: var(--pm-muted);
  font-size: .58rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  cursor: pointer;
  text-align: center;
  border-radius: 2px;
  transition: all .12s;
}
.pm-version-btn.active { background: var(--pm-accent); color: #000; border-color: var(--pm-accent); }
</style>

<div class="pm-wrap">

<!-- ════════════════════════════════════════════════════════ -->
<!-- LEFT SIDEBAR — Package + Design Controls                 -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="pm-sidebar">

  <!-- PACKAGE -->
  <div class="pm-section">
    <div class="pm-section-title">Package</div>
    <?php foreach ($packages as $i => $pkg): ?>
    <button class="pm-pkg-btn <?= $i===0?'active':'' ?>" onclick="selectPackage(<?= $i ?>)" id="pkgbtn<?= $i ?>">
      <div class="pm-pkg-name"><?= htmlspecialchars($pkg['name']) ?></div>
      <div class="pm-pkg-meta"><?= $pkg['duration_days'] ?>n · $<?= number_format((float)$pkg['price_1pax'],0) ?>/pp · <?= ucwords(str_replace('_',' ',$pkg['accommodation_level']??'')) ?></div>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- TEMPLATE -->
  <div class="pm-section">
    <div class="pm-section-title">Template</div>
    <div class="pm-tpl-grid">
      <button class="pm-tpl-btn active" onclick="setTpl('hero')"      id="tpl-hero">Hero</button>
      <button class="pm-tpl-btn"        onclick="setTpl('split')"     id="tpl-split">Split</button>
      <button class="pm-tpl-btn"        onclick="setTpl('mosaic')"    id="tpl-mosaic">Mosaic</button>
      <button class="pm-tpl-btn"        onclick="setTpl('minimal')"   id="tpl-minimal">Minimal</button>
      <button class="pm-tpl-btn"        onclick="setTpl('itinerary')" id="tpl-itin">Itinerary</button>
      <button class="pm-tpl-btn"        onclick="setTpl('price')"     id="tpl-price">Pricing</button>
      <button class="pm-tpl-btn"        onclick="setTpl('story')"     id="tpl-story">Story</button>
      <button class="pm-tpl-btn"        onclick="setTpl('feature')"   id="tpl-feature">Feature</button>
      <button class="pm-tpl-btn"        onclick="setTpl('dark')"      id="tpl-dark">Dark Full</button>
    </div>
  </div>

  <!-- SIZE -->
  <div class="pm-section">
    <div class="pm-section-title">Canvas Size</div>
    <div class="pm-size-row" style="margin-bottom:.4rem">
      <button class="pm-size-btn active" onclick="setSize('square')"   id="sz-sq">1:1</button>
      <button class="pm-size-btn"        onclick="setSize('portrait')" id="sz-po">4:5</button>
      <button class="pm-size-btn"        onclick="setSize('story')"    id="sz-st">9:16</button>
      <button class="pm-size-btn"        onclick="setSize('landscape')"id="sz-la">16:9</button>
    </div>
    <div style="font-size:.6rem;color:var(--pm-muted)" id="size-label">1080 × 1080 px</div>
  </div>

  <!-- COLOUR THEME -->
  <div class="pm-section">
    <div class="pm-section-title">Colour Theme</div>
    <div class="pm-color-row" style="margin-bottom:.65rem">
      <div class="pm-swatch active" style="background:#0a0a0a;border-color:#333" onclick="setTheme('midnight')" title="Midnight"></div>
      <div class="pm-swatch" style="background:#1a2e1a" onclick="setTheme('forest')" title="Forest"></div>
      <div class="pm-swatch" style="background:#3d2608" onclick="setTheme('earth')" title="Savannah"></div>
      <div class="pm-swatch" style="background:#0f1a2e" onclick="setTheme('navy')" title="Night Sky"></div>
      <div class="pm-swatch" style="background:#2e0f0f" onclick="setTheme('dust')" title="Dust Red"></div>
      <div class="pm-swatch" style="background:#f5f0e8" onclick="setTheme('cream')" title="Sand"></div>
      <div class="pm-swatch" style="background:#1a1a2e" onclick="setTheme('violet')" title="Violet Night"></div>
      <div class="pm-swatch" style="background:#fff" onclick="setTheme('white')" title="Clean White"></div>
    </div>
    <div>
      <span class="pm-label">Accent Colour</span>
      <div class="pm-color-row">
        <div class="pm-swatch active" style="background:#d4af7a;border-color:#555" onclick="setAccent('#d4af7a')" title="Gold"></div>
        <div class="pm-swatch" style="background:#fff"    onclick="setAccent('#ffffff')" title="White"></div>
        <div class="pm-swatch" style="background:#7ecba1" onclick="setAccent('#7ecba1')" title="Sage"></div>
        <div class="pm-swatch" style="background:#e87a7a" onclick="setAccent('#e87a7a')" title="Coral"></div>
        <div class="pm-swatch" style="background:#7ab4e8" onclick="setAccent('#7ab4e8')" title="Sky"></div>
        <div class="pm-swatch" style="background:#f5c842" onclick="setAccent('#f5c842')" title="Yellow"></div>
        <div class="pm-swatch" style="background:#e8a87a" onclick="setAccent('#e8a87a')" title="Copper"></div>
        <input type="color" id="customAccent" value="#d4af7a"
               onchange="setAccent(this.value)"
               style="width:26px;height:26px;border-radius:50%;border:2px solid #555;cursor:pointer;padding:0;background:none;overflow:hidden">
      </div>
    </div>
  </div>

  <!-- TYPOGRAPHY -->
  <div class="pm-section">
    <div class="pm-section-title">Typography</div>
    <span class="pm-label" style="margin-bottom:.4rem">Headline Font</span>
    <div class="pm-font-row" style="margin-bottom:.65rem">
      <button class="pm-font-btn active" onclick="setFont('Georgia')"     id="fn-georgia">Serif</button>
      <button class="pm-font-btn"        onclick="setFont('Arial')"       id="fn-arial">Sans</button>
      <button class="pm-font-btn"        onclick="setFont('Courier New')" id="fn-courier">Mono</button>
    </div>
    <span class="pm-label">Text Case</span>
    <div class="pm-font-row">
      <button class="pm-font-btn active" onclick="setCase('upper')" id="case-upper">UPPER</button>
      <button class="pm-font-btn"        onclick="setCase('title')" id="case-title">Title</button>
      <button class="pm-font-btn"        onclick="setCase('lower')" id="case-lower">lower</button>
    </div>
  </div>

  <!-- OVERLAY & BLUR -->
  <div class="pm-section">
    <div class="pm-section-title">Image Controls</div>
    <span class="pm-label">Overlay Darkness</span>
    <input type="range" id="rOverlay" class="pm-range" min="0" max="90" value="55" oninput="render()">
    <span class="pm-label">Vignette</span>
    <input type="range" id="rVignette" class="pm-range" min="0" max="100" value="40" oninput="render()">
    <span class="pm-label">Image Position</span>
    <div class="pm-tool-row">
      <button class="pm-btn pm-btn--sm" onclick="setImgPos('top')">Top</button>
      <button class="pm-btn pm-btn--sm active" onclick="setImgPos('center')">Centre</button>
      <button class="pm-btn pm-btn--sm" onclick="setImgPos('bottom')">Bottom</button>
    </div>
  </div>

  <!-- ELEMENTS TOGGLE -->
  <div class="pm-section">
    <div class="pm-section-title">Elements</div>
    <label class="pm-toggle"><input type="checkbox" id="elLogo"       checked onchange="render()"><span>Brand / Logo</span></label>
    <label class="pm-toggle"><input type="checkbox" id="elHeadline"   checked onchange="render()"><span>Headline</span></label>
    <label class="pm-toggle"><input type="checkbox" id="elTagline"    checked onchange="render()"><span>Tagline</span></label>
    <label class="pm-toggle"><input type="checkbox" id="elPrice"      checked onchange="render()"><span>Price</span></label>
    <label class="pm-toggle"><input type="checkbox" id="elHighlights" checked onchange="render()"><span>Highlights strip</span></label>
    <label class="pm-toggle"><input type="checkbox" id="elBadge"      checked onchange="render()"><span>Duration badge</span></label>
    <label class="pm-toggle"><input type="checkbox" id="elCTA"        checked onchange="render()"><span>CTA / Website</span></label>
    <label class="pm-toggle"><input type="checkbox" id="elVigBar"             onchange="render()"><span>Accent bar</span></label>
    <label class="pm-toggle"><input type="checkbox" id="elDivider"    checked onchange="render()"><span>Dividers</span></label>
    <label class="pm-toggle"><input type="checkbox" id="elWatermark"          onchange="render()"><span>Watermark</span></label>
  </div>

  <!-- LAYOUT TWEAKS -->
  <div class="pm-section">
    <div class="pm-section-title">Layout</div>
    <span class="pm-label">Padding</span>
    <input type="range" id="rPad" class="pm-range" min="20" max="120" value="64" oninput="render()">
    <span class="pm-label">Headline Size</span>
    <input type="range" id="rHSize" class="pm-range" min="40" max="120" value="68" oninput="render()">
    <span class="pm-label">Badge Position</span>
    <div class="pm-tool-row">
      <button class="pm-btn pm-btn--sm active" onclick="setBadgePos('tr')">Top Right</button>
      <button class="pm-btn pm-btn--sm" onclick="setBadgePos('tl')">Top Left</button>
      <button class="pm-btn pm-btn--sm" onclick="setBadgePos('br')">Bottom</button>
    </div>
  </div>

</div><!-- /left sidebar -->

<!-- ════════════════════════════════════════════════════════ -->
<!-- CENTRE — Canvas + Toolbar                                -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="pm-centre">

  <!-- Top toolbar -->
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;width:100%;max-width:540px;justify-content:center">
    <button class="pm-btn pm-btn--primary" onclick="downloadPNG()">↓ PNG</button>
    <button class="pm-btn" onclick="downloadJPG()">↓ JPG</button>
    <button class="pm-btn" onclick="copyToClipboard()">⧉ Copy</button>
    <button class="pm-btn pm-btn--ai" onclick="generateCaption()" id="aibtn">✦ AI Caption</button>
    <button class="pm-btn" onclick="cycleImg()">⟳ Next Image</button>
    <button class="pm-btn" onclick="randomise()">⚂ Randomise</button>
  </div>

  <!-- Image strip -->
  <div class="pm-img-strip" id="imgStrip" style="width:100%;max-width:540px"></div>

  <!-- Canvas -->
  <div class="pm-canvas-frame" id="canvasFrame">
    <canvas id="posterCanvas" width="1080" height="1080"></canvas>
  </div>

  <!-- Zoom -->
  <div style="display:flex;align-items:center;gap:.65rem">
    <span style="font-size:.62rem;color:var(--pm-muted)">Zoom</span>
    <input type="range" id="rZoom" class="pm-range" min="20" max="100" value="60"
           style="width:120px" oninput="applyZoom(this.value)">
    <span style="font-size:.62rem;color:var(--pm-muted)" id="zoomLabel">60%</span>
  </div>

</div><!-- /centre -->

<!-- ════════════════════════════════════════════════════════ -->
<!-- RIGHT SIDEBAR — Text & AI                                -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="pm-right">

  <!-- CUSTOM TEXT -->
  <div class="pm-section">
    <div class="pm-section-title">Custom Text</div>
    <div style="margin-bottom:.65rem">
      <span class="pm-label">Headline</span>
      <input id="txHeadline" class="pm-input" placeholder="Leave blank to use package name" oninput="render()">
    </div>
    <div style="margin-bottom:.65rem">
      <span class="pm-label">Tagline</span>
      <input id="txTagline" class="pm-input" placeholder="e.g. From $1,650 per person" oninput="render()">
    </div>
    <div style="margin-bottom:.65rem">
      <span class="pm-label">CTA / Website</span>
      <input id="txCTA" class="pm-input" value="safarizanguven.com" oninput="render()">
    </div>
    <div style="margin-bottom:.65rem">
      <span class="pm-label">Badge Text</span>
      <input id="txBadge" class="pm-input" placeholder="e.g. 5 NIGHTS" oninput="render()">
    </div>
    <div>
      <span class="pm-label">Bottom Note</span>
      <input id="txNote" class="pm-input" placeholder="e.g. Prices from USD per person" oninput="render()">
    </div>
  </div>

  <!-- HIGHLIGHTS EDITOR -->
  <div class="pm-section">
    <div class="pm-section-title">Highlights <button class="pm-btn pm-btn--sm" onclick="resetHighlights()">Reset</button></div>
    <textarea id="txHighlights" class="pm-textarea" rows="5"
              placeholder="One per line — shown as strip on poster" oninput="render()"></textarea>
  </div>

  <!-- AI CAPTION -->
  <div class="pm-section">
    <div class="pm-section-title">
      AI Caption
      <button class="pm-btn pm-btn--sm pm-btn--ai" onclick="generateCaption()">Generate</button>
    </div>
    <div class="pm-version-row" style="margin-bottom:.5rem">
      <button class="pm-version-btn active" onclick="setCaptionTone('premium')"   id="tone-premium">Premium</button>
      <button class="pm-version-btn"        onclick="setCaptionTone('urgent')"    id="tone-urgent">Urgent</button>
      <button class="pm-version-btn"        onclick="setCaptionTone('story')"     id="tone-story">Story</button>
      <button class="pm-version-btn"        onclick="setCaptionTone('minimal')"   id="tone-minimal">Minimal</button>
    </div>
    <div id="captionOut" class="pm-caption-out" contenteditable="true">
      Select a package then click Generate to create an AI caption.
    </div>
    <div class="pm-btn-row">
      <button class="pm-btn pm-btn--sm" onclick="copyCaption()">Copy Caption</button>
      <button class="pm-btn pm-btn--sm" onclick="generateCaption()"><span class="pm-spinner" id="spinner" style="display:none"></span> Regenerate</button>
    </div>
  </div>

  <!-- HASHTAGS -->
  <div class="pm-section">
    <div class="pm-section-title">
      Hashtags
      <button class="pm-btn pm-btn--sm" onclick="refreshHashtags()">Refresh</button>
    </div>
    <div id="hashtagChips" style="margin-bottom:.5rem"></div>
    <textarea id="txHashtags" class="pm-textarea" rows="3" placeholder="Hashtags..."></textarea>
    <button class="pm-btn pm-btn--sm" onclick="copyHashtags()" style="margin-top:.35rem">Copy Hashtags</button>
  </div>

  <!-- PACKAGE STATS -->
  <div class="pm-section">
    <div class="pm-section-title">Package Data</div>
    <div id="statsPanel"></div>
  </div>

</div><!-- /right -->
</div><!-- /pm-wrap -->

<script>
/* ════════════════════════════════════════════════════════════
   POSTER MAKER — Core Engine
   ════════════════════════════════════════════════════════════ */
var PKGS = <?= $pkgsJson ?>;
var LOGO_URL = '<?= $logoUrl ?>';
var BASE = window.BASE_PATH || '';

var S = { // State
  pkg:      0,
  tpl:      'hero',
  size:     'square',
  theme:    'midnight',
  accent:   '#d4af7a',
  font:     'Georgia',
  textCase: 'upper',
  imgIdx:   0,
  imgPos:   'center',
  badgePos: 'tr',
  loadedImgs: {},
  logoImg:    null,
  captionTone: 'premium',
};

var SIZES = {
  square:    {w:1080, h:1080, label:'1080×1080'},
  portrait:  {w:1080, h:1350, label:'1080×1350'},
  story:     {w:1080, h:1920, label:'1080×1920'},
  landscape: {w:1920, h:1080, label:'1920×1080'},
};

var THEMES = {
  midnight:{ bg:'#0a0a0a', bg2:'#111', text:'#fff',   sub:'rgba(255,255,255,.58)', strip:'rgba(0,0,0,.72)' },
  forest:  { bg:'#0d1f0d', bg2:'#1a2e1a', text:'#fff',sub:'rgba(255,255,255,.58)', strip:'rgba(10,20,10,.75)' },
  earth:   { bg:'#1e0f00', bg2:'#3d2608', text:'#fff',sub:'rgba(255,255,255,.58)', strip:'rgba(20,10,0,.75)' },
  navy:    { bg:'#060c18', bg2:'#0f1a2e', text:'#fff',sub:'rgba(255,255,255,.58)', strip:'rgba(5,10,20,.75)' },
  dust:    { bg:'#1a0505', bg2:'#2e0f0f', text:'#fff',sub:'rgba(255,255,255,.58)', strip:'rgba(20,5,5,.75)' },
  cream:   { bg:'#f5f0e8', bg2:'#ede8df', text:'#1a1208', sub:'rgba(26,18,8,.55)', strip:'rgba(245,240,232,.85)' },
  violet:  { bg:'#0a0514', bg2:'#1a0a2e', text:'#fff',sub:'rgba(255,255,255,.58)', strip:'rgba(8,3,18,.75)' },
  white:   { bg:'#fff',    bg2:'#f7f7f5', text:'#0a0a0a', sub:'rgba(0,0,0,.55)', strip:'rgba(255,255,255,.9)' },
};

var canvas  = document.getElementById('posterCanvas');
var ctx     = canvas.getContext('2d');

/* ── Init ─────────────────────────────────────────────────── */
window.addEventListener('load', function() {
  loadLogo();
  selectPackage(0);
  applyZoom(60);
});

function loadLogo() {
  var img = new Image();
  img.crossOrigin = 'anonymous';
  img.onload = function(){ S.logoImg = img; render(); };
  img.onerror = function(){ S.logoImg = null; render(); };
  img.src = LOGO_URL;
}

/* ── Package Selection ────────────────────────────────────── */
function selectPackage(idx) {
  S.pkg = idx;
  S.imgIdx = 0;
  var pkg = PKGS[idx];

  // Update buttons
  PKGS.forEach(function(_, i) {
    var b = document.getElementById('pkgbtn' + i);
    if (b) b.classList.toggle('active', i === idx);
  });

  // Populate highlights
  var incl = pkg.itin_inc_arr.length ? pkg.itin_inc_arr : pkg.includes_arr || [];
  document.getElementById('txHighlights').value = incl.slice(0,5).join('\n');

  // Set default badge
  document.getElementById('txBadge').value = pkg.duration_days + ' NIGHTS';

  // Update image strip
  buildImgStrip(pkg);

  // Stats
  updateStats(pkg);

  // Load images then render
  loadPkgImages(pkg, render);

  // Default hashtags
  refreshHashtags();
}

function buildImgStrip(pkg) {
  var strip = document.getElementById('imgStrip');
  strip.innerHTML = '';
  (pkg.gallery || []).forEach(function(src, i) {
    var img = document.createElement('img');
    img.src = BASE + src; img.className = 'pm-img-thumb' + (i===0?' active':'');
    img.title = 'Image ' + (i+1);
    img.onclick = (function(idx){ return function(){
      S.imgIdx = idx;
      strip.querySelectorAll('.pm-img-thumb').forEach(function(t,j){ t.classList.toggle('active',j===idx); });
      render();
    }; })(i);
    strip.appendChild(img);
  });
}

function loadPkgImages(pkg, cb) {
  var imgs = (pkg.gallery || []).filter(Boolean);
  if (!imgs.length) { cb(); return; }
  var done = 0;
  imgs.forEach(function(src) {
    if (S.loadedImgs[src]) { done++; if (done === imgs.length) cb(); return; }
    var img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload  = function(){ S.loadedImgs[src] = img; done++; if (done === imgs.length) cb(); };
    img.onerror = function(){ done++; if (done === imgs.length) cb(); };
    img.src = BASE + src;
  });
}

/* ── Controls ─────────────────────────────────────────────── */
function setTpl(t) {
  S.tpl = t;
  document.querySelectorAll('[id^="tpl-"]').forEach(function(b){ b.classList.remove('active'); });
  var el = document.getElementById('tpl-' + t) || document.getElementById('tpl-' + t.replace('y','ary'));
  if (el) el.classList.add('active');
  render();
}

function setSize(s) {
  S.size = s;
  document.querySelectorAll('[id^="sz-"]').forEach(function(b){ b.classList.remove('active'); });
  var map = {square:'sq', portrait:'po', story:'st', landscape:'la'};
  document.getElementById('sz-' + (map[s]||s)).classList.add('active');
  var sz = SIZES[s];
  canvas.width = sz.w; canvas.height = sz.h;
  document.getElementById('size-label').textContent = sz.label + ' px';
  applyZoom(parseInt(document.getElementById('rZoom').value));
  render();
}

function setTheme(t) {
  S.theme = t;
  document.querySelectorAll('.pm-swatch').forEach(function(sw){ sw.classList.remove('active'); });
  event.target.classList.add('active');
  render();
}

function setAccent(col) {
  S.accent = col;
  render();
}

function setFont(f) {
  S.font = f;
  document.querySelectorAll('[id^="fn-"]').forEach(function(b){ b.classList.remove('active'); });
  var map = {'Georgia':'georgia','Arial':'arial','Courier New':'courier'};
  var el = document.getElementById('fn-' + (map[f]||f.toLowerCase()));
  if (el) el.classList.add('active');
  render();
}

function setCase(c) {
  S.textCase = c;
  document.querySelectorAll('[id^="case-"]').forEach(function(b){ b.classList.remove('active'); });
  document.getElementById('case-' + c).classList.add('active');
  render();
}

function setImgPos(p) { S.imgPos = p; render(); }
function setBadgePos(p) { S.badgePos = p; render(); }
function setCaptionTone(t) {
  S.captionTone = t;
  document.querySelectorAll('[id^="tone-"]').forEach(function(b){ b.classList.remove('active'); });
  document.getElementById('tone-' + t).classList.add('active');
}

function applyZoom(z) {
  var pct = z / 100;
  var sz = SIZES[S.size];
  canvas.style.width  = Math.round(sz.w * pct) + 'px';
  canvas.style.height = Math.round(sz.h * pct) + 'px';
  document.getElementById('zoomLabel').textContent = z + '%';
}

function cycleImg() {
  var pkg = PKGS[S.pkg];
  var imgs = pkg.gallery || [];
  if (!imgs.length) return;
  S.imgIdx = (S.imgIdx + 1) % imgs.length;
  var strip = document.getElementById('imgStrip');
  strip.querySelectorAll('.pm-img-thumb').forEach(function(t,j){ t.classList.toggle('active',j===S.imgIdx); });
  render();
}

function randomise() {
  var tpls = ['hero','split','mosaic','minimal','itinerary','price','story','feature','dark'];
  var thms = Object.keys(THEMES);
  S.tpl   = tpls[Math.floor(Math.random()*tpls.length)];
  S.theme = thms[Math.floor(Math.random()*thms.length)];
  S.imgIdx= Math.floor(Math.random()*Math.max(1,(PKGS[S.pkg].gallery||[]).length));
  render();
}

function resetHighlights() {
  var pkg = PKGS[S.pkg];
  var incl = pkg.itin_inc_arr.length ? pkg.itin_inc_arr : pkg.includes_arr || [];
  document.getElementById('txHighlights').value = incl.slice(0,5).join('\n');
  render();
}

/* ── MASTER RENDER ───────────────────────────────────────── */
function render() {
  var pkg   = PKGS[S.pkg];
  var theme = THEMES[S.theme];
  var w = canvas.width, h = canvas.height;

  ctx.clearRect(0,0,w,h);
  ctx.fillStyle = theme.bg;
  ctx.fillRect(0,0,w,h);

  var imgSrc = (pkg.gallery||[])[S.imgIdx];
  var img    = imgSrc ? S.loadedImgs[imgSrc] : null;

  var fns = {
    hero:      renderHero,
    split:     renderSplit,
    mosaic:    renderMosaic,
    minimal:   renderMinimal,
    itinerary: renderItinerary,
    price:     renderPrice,
    story:     renderStoryTpl,
    feature:   renderFeature,
    dark:      renderDarkFull,
  };
  (fns[S.tpl] || renderHero)(pkg, theme, img, w, h);
}

/* ── TEXT HELPERS ────────────────────────────────────────── */
function applyCase(str) {
  if (S.textCase === 'upper') return str.toUpperCase();
  if (S.textCase === 'lower') return str.toLowerCase();
  return str.replace(/\b\w/g, function(c){ return c.toUpperCase(); });
}

function wrapText(text, x, y, maxW, size, color, weight, lhMult, maxLines) {
  ctx.font = (weight||'normal') + ' ' + size + 'px ' + S.font;
  ctx.fillStyle = color;
  var lh = size * (lhMult||1.25);
  var words = String(text).split(' ');
  var line = '';
  var lines = [];
  words.forEach(function(w) {
    var test = line + (line?' ':'')+w;
    if (ctx.measureText(test).width > maxW && line) {
      lines.push(line); line = w;
    } else { line = test; }
  });
  if (line) lines.push(line);
  if (maxLines) lines = lines.slice(0, maxLines);
  lines.forEach(function(l, i) {
    ctx.fillText(l, x, y - (lines.length - 1 - i) * lh);
  });
  return lines.length * lh;
}

function textH(text, size, maxW) {
  ctx.font = 'bold ' + size + 'px ' + S.font;
  var words = String(text).split(' '), line = '', n = 1;
  words.forEach(function(w) {
    var t = line+(line?' ':'')+w;
    if (ctx.measureText(t).width > maxW && line) { n++; line = w; } else line = t;
  });
  return n * size * 1.25;
}

function hexRgba(hex, a) {
  hex = hex.replace('#','');
  if (hex.length===3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
  var r=parseInt(hex.slice(0,2),16), g=parseInt(hex.slice(2,4),16), b=parseInt(hex.slice(4,6),16);
  return 'rgba('+r+','+g+','+b+','+a+')';
}

/* ── SHARED DRAW FUNCTIONS ───────────────────────────────── */
function drawImg(img, x, y, w, h) {
  if (!img) return;
  var pos = S.imgPos;
  var scale = Math.max(w/img.width, h/img.height);
  var sw = img.width*scale, sh = img.height*scale;
  var sx = x+(w-sw)/2;
  var sy = pos==='top' ? y : pos==='bottom' ? y+(h-sh) : y+(h-sh)/2;
  ctx.drawImage(img, sx, sy, sw, sh);
}

function clipDraw(img, x, y, w, h) {
  if (!img) return;
  ctx.save(); ctx.beginPath(); ctx.rect(x,y,w,h); ctx.clip();
  drawImg(img, x, y, w, h); ctx.restore();
}

function drawGradOverlay(x, y, w, h, bg, alpha, direction) {
  var grad;
  if (direction === 'lr') {
    grad = ctx.createLinearGradient(x,0,x+w,0);
  } else {
    grad = ctx.createLinearGradient(0,y,0,y+h);
  }
  grad.addColorStop(0, hexRgba(bg, 0));
  grad.addColorStop(0.5, hexRgba(bg, alpha*0.6));
  grad.addColorStop(1, hexRgba(bg, alpha));
  ctx.fillStyle = grad;
  ctx.fillRect(x, y, w, h);
}

function drawVignette(w, h, strength) {
  if (strength < 5) return;
  var s = strength/100;
  var grad = ctx.createRadialGradient(w/2,h/2,h*0.2,w/2,h/2,h*0.75);
  grad.addColorStop(0, 'transparent');
  grad.addColorStop(1, hexRgba('#000', s));
  ctx.fillStyle = grad;
  ctx.fillRect(0,0,w,h);
}

function drawLogo(theme, w, y, x) {
  var pad = x !== undefined ? x : parseInt(document.getElementById('rPad').value)||64;
  if (S.logoImg) {
    var lh = Math.round(w * 0.045);
    var lw = Math.round(lh * (S.logoImg.width/S.logoImg.height));
    // Draw logo image
    ctx.save(); ctx.globalAlpha = 0.9;
    ctx.drawImage(S.logoImg, pad, y - lh + 4, lw, lh);
    ctx.restore();
    // Brand text next to logo
    ctx.font = 'bold ' + Math.round(w*0.018) + 'px Arial';
    ctx.fillStyle = theme.text;
    ctx.fillText('SAFARI ZANGU VENTURES', pad + lw + 10, y);
  } else {
    ctx.font = 'bold ' + Math.round(w*0.02) + 'px Arial';
    ctx.fillStyle = S.accent;
    ctx.fillText('SAFARI ZANGU VENTURES', pad, y);
  }
}

function drawBadge(text, w, h) {
  var bp = S.badgePos;
  var fs = Math.round(w*0.02);
  ctx.font = 'bold ' + fs + 'px Arial';
  var tw = ctx.measureText(text).width;
  var bw = tw + 28, bh = fs + 22;
  var bx, by;
  if (bp === 'tl') { bx = 50; by = 50; }
  else if (bp === 'br') { bx = w - 50 - bw; by = h - 50 - bh; }
  else { bx = w - 50 - bw; by = 50; } // tr default

  ctx.fillStyle = S.accent;
  ctx.fillRect(bx, by, bw, bh);
  ctx.fillStyle = THEMES[S.theme].bg;
  ctx.textAlign = 'center';
  ctx.fillText(text, bx + bw/2, by + bh/2 + fs/3);
  ctx.textAlign = 'left';
}

function drawAccentBar(x, y, w, h) {
  ctx.fillStyle = S.accent;
  ctx.fillRect(x, y, w, h);
}

function drawHighlightsStrip(pkg, theme, w, h, pad) {
  var lines = document.getElementById('txHighlights').value.split('\n').map(function(l){ return l.trim(); }).filter(Boolean);
  if (!lines.length) return;
  var shown = lines.slice(0, 3);
  var sh = Math.round(h * 0.075);
  var sy = Math.round(h * 0.7);
  ctx.fillStyle = theme.strip;
  ctx.fillRect(0, sy, w, sh);
  var cw = (w - pad*2) / shown.length;
  ctx.textAlign = 'center';
  shown.forEach(function(inc, i) {
    var cx = pad + i*cw + cw/2;
    ctx.fillStyle = S.accent;
    ctx.font = 'bold ' + Math.round(w*0.022) + 'px Arial';
    ctx.fillText('✓', cx - Math.round(w*0.04), sy + sh/2 + 8);
    ctx.fillStyle = theme.text;
    ctx.font = Math.round(w*0.022) + 'px Arial';
    var short = inc.length > 28 ? inc.substr(0,26)+'…' : inc;
    ctx.fillText(short, cx + Math.round(w*0.015), sy + sh/2 + 8);
  });
  ctx.textAlign = 'left';
}

/* ════════════════════════════════════════════════════════════
   TEMPLATES
   ════════════════════════════════════════════════════════════ */

function renderHero(pkg, theme, img, w, h) {
  var ov  = parseInt(document.getElementById('rOverlay').value)/100;
  var vig = parseInt(document.getElementById('rVignette').value);
  var pad = parseInt(document.getElementById('rPad').value)||64;
  var hfs = parseInt(document.getElementById('rHSize').value)||68;

  if (img) {
    clipDraw(img, 0, 0, w, h);
    var g = ctx.createLinearGradient(0,0,0,h);
    g.addColorStop(0, hexRgba(theme.bg, 0.08));
    g.addColorStop(0.35, hexRgba(theme.bg, 0.15));
    g.addColorStop(0.65, hexRgba(theme.bg, ov*0.6));
    g.addColorStop(1, hexRgba(theme.bg, Math.min(ov+0.25,1)));
    ctx.fillStyle = g; ctx.fillRect(0,0,w,h);
    drawVignette(w,h,vig);
  }

  // Top fade for logo
  var tg = ctx.createLinearGradient(0,0,0,h*0.22);
  tg.addColorStop(0, hexRgba(theme.bg, 0.65));
  tg.addColorStop(1, 'transparent');
  ctx.fillStyle = tg; ctx.fillRect(0,0,w,h*0.22);

  if (document.getElementById('elLogo').checked) drawLogo(theme, w, 58, pad);
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value || pkg.duration_days+' NIGHTS', w, h);

  var y = h - 56;
  if (document.getElementById('elCTA').checked) {
    var cta = document.getElementById('txCTA').value || 'safarizanguven.com';
    ctx.font = 'bold ' + Math.round(w*0.022) + 'px Arial'; ctx.fillStyle = S.accent;
    ctx.fillText(cta.toUpperCase(), pad, y); y -= 48;
  }
  var note = document.getElementById('txNote').value;
  if (note) {
    ctx.font = Math.round(w*0.02)+'px Arial'; ctx.fillStyle = theme.sub;
    ctx.fillText(note, pad, y); y -= 42;
  }
  if (document.getElementById('elPrice').checked) {
    ctx.font = 'bold '+Math.round(w*0.03)+'px Arial'; ctx.fillStyle = theme.text;
    ctx.fillText('From $'+Number(pkg.price_1pax).toLocaleString()+' per person', pad, y); y -= 56;
  }
  if (document.getElementById('elDivider').checked) {
    drawAccentBar(pad, y-2, 60, 3); y -= 24;
  }
  var headline = applyCase(document.getElementById('txHeadline').value || pkg.name);
  if (document.getElementById('elHeadline').checked) {
    var hsize = Math.round(w * hfs/1000 * 0.8);
    wrapText(headline, pad, y, w-pad*2, hsize, theme.text, 'bold', 1.1, 3);
    y -= textH(headline, hsize, w-pad*2) + 16;
  }
  var tagline = document.getElementById('txTagline').value || pkg.short_description || '';
  if (tagline && document.getElementById('elTagline').checked) {
    wrapText(tagline, pad, y-8, w-pad*2, Math.round(w*0.027), theme.sub, 'normal', 1.6, 2);
  }
  if (document.getElementById('elHighlights').checked) drawHighlightsStrip(pkg, theme, w, h, pad);
  if (document.getElementById('elVigBar').checked) { ctx.fillStyle = S.accent; ctx.fillRect(0,h-5,w,5); }
  if (document.getElementById('elWatermark').checked) {
    ctx.save(); ctx.globalAlpha=0.08;
    ctx.font='bold '+Math.round(w*0.12)+'px '+S.font;
    ctx.fillStyle=theme.text; ctx.textAlign='center';
    ctx.fillText('SZV', w/2, h*0.55);
    ctx.textAlign='left'; ctx.restore();
  }
}

function renderSplit(pkg, theme, img, w, h) {
  var pad = parseInt(document.getElementById('rPad').value)||64;
  var splitX = Math.round(w*0.50);

  if (img) {
    clipDraw(img, 0, 0, splitX, h);
    var g = ctx.createLinearGradient(splitX-160,0,splitX,0);
    g.addColorStop(0,'transparent'); g.addColorStop(1,theme.bg);
    ctx.fillStyle = g; ctx.fillRect(0,0,splitX,h);
  } else { ctx.fillStyle = hexRgba(theme.bg,0.7); ctx.fillRect(0,0,splitX,h); }
  ctx.fillStyle = theme.bg; ctx.fillRect(splitX,0,w-splitX,h);

  var rx = splitX+56, rw = w-splitX-60, ry = 72;
  if (document.getElementById('elLogo').checked) { drawLogo(theme,w,ry,rx); ry+=80; }
  if (document.getElementById('elVigBar').checked) { drawAccentBar(rx, ry, 3, 160); }
  ry += 28;

  var headline = applyCase(document.getElementById('txHeadline').value || pkg.name);
  if (document.getElementById('elHeadline').checked) {
    var hfs2 = Math.round(w*0.044);
    wrapText(headline, rx, ry, rw, hfs2, theme.text, 'bold', 1.15, 3);
    ry += textH(headline, hfs2, rw) + 28;
  }
  var tagline = document.getElementById('txTagline').value || pkg.short_description || '';
  if (tagline && document.getElementById('elTagline').checked) {
    wrapText(tagline, rx, ry, rw, Math.round(w*0.025), theme.sub, 'normal', 1.65, 3);
    ry += textH(tagline, Math.round(w*0.025), rw) + 44;
  }
  if (document.getElementById('elDivider').checked) {
    drawAccentBar(rx, ry, rw*0.6, 1); ry += 28;
  }

  var stats = [
    {l:'DURATION', v: pkg.duration_days+' nights'},
    {l:'FROM',     v: '$'+Number(pkg.price_1pax).toLocaleString()+'/pp'},
    {l:'STYLE',    v: ucw(pkg.accommodation_level)},
  ];
  stats.forEach(function(s) {
    ctx.font='bold '+Math.round(w*0.016)+'px Arial'; ctx.fillStyle=S.accent; ctx.fillText(s.l, rx, ry); ry+=Math.round(w*0.024);
    ctx.font='bold '+Math.round(w*0.032)+'px '+S.font; ctx.fillStyle=theme.text; ctx.fillText(s.v, rx, ry); ry+=Math.round(w*0.052);
  });

  if (document.getElementById('elHighlights').checked) {
    ry += 10;
    ctx.font='bold '+Math.round(w*0.016)+'px Arial'; ctx.fillStyle=S.accent; ctx.fillText('INCLUDES', rx, ry); ry+=32;
    var lines = document.getElementById('txHighlights').value.split('\n').map(function(l){return l.trim();}).filter(Boolean);
    lines.slice(0,5).forEach(function(inc){
      ctx.font=Math.round(w*0.022)+'px Arial'; ctx.fillStyle=theme.sub;
      ctx.fillText('✓  '+inc, rx, ry); ry+=Math.round(w*0.033);
    });
  }
  if (document.getElementById('elCTA').checked) {
    ctx.font='bold '+Math.round(w*0.02)+'px Arial'; ctx.fillStyle=S.accent;
    ctx.fillText((document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase(), rx, h-56);
  }
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||pkg.duration_days+'N', w, h);
}

function renderMosaic(pkg, theme, img, w, h) {
  var pad = parseInt(document.getElementById('rPad').value)||64;
  var ov  = parseInt(document.getElementById('rOverlay').value)/100;
  var imgs = (pkg.gallery||[]).map(function(s){return S.loadedImgs[s];}).filter(Boolean);
  var gap = 6;

  if (imgs.length >= 3) {
    var lw = Math.round(w*0.44), rw2 = w-lw-gap, th = Math.round(h*0.49), bh = h-th-gap;
    clipDraw(imgs[0],0,0,lw,th); clipDraw(imgs[1],0,th+gap,lw,bh); clipDraw(imgs[2],lw+gap,0,rw2,h);
  } else if (imgs.length===2) {
    clipDraw(imgs[0],0,0,Math.round(w*0.5)-gap/2,h); clipDraw(imgs[1],Math.round(w*0.5)+gap/2,0,Math.round(w*0.5)-gap/2,h);
  } else if (img) { clipDraw(img,0,0,w,h); }

  var bg = ctx.createLinearGradient(0,h*0.45,0,h);
  bg.addColorStop(0,'transparent'); bg.addColorStop(1,hexRgba(theme.bg,Math.min(ov+0.2,1)));
  ctx.fillStyle=bg; ctx.fillRect(0,0,w,h);
  var tg2 = ctx.createLinearGradient(0,0,0,h*0.22);
  tg2.addColorStop(0,hexRgba(theme.bg,0.7)); tg2.addColorStop(1,'transparent');
  ctx.fillStyle=tg2; ctx.fillRect(0,0,w,h*0.22);

  if (document.getElementById('elLogo').checked) drawLogo(theme,w,56,pad);
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||pkg.duration_days+' NIGHTS',w,h);

  var y = h-56;
  if (document.getElementById('elCTA').checked) {
    ctx.font='bold '+Math.round(w*0.022)+'px Arial'; ctx.fillStyle=S.accent;
    ctx.fillText((document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase(), pad, y); y-=50;
  }
  if (document.getElementById('elPrice').checked) {
    ctx.font='bold '+Math.round(w*0.03)+'px Arial'; ctx.fillStyle=theme.text;
    ctx.fillText('From $'+Number(pkg.price_1pax).toLocaleString()+'/pp', pad, y); y-=58;
  }
  var headline = applyCase(document.getElementById('txHeadline').value||pkg.name);
  if (document.getElementById('elHeadline').checked) {
    var hfs3 = Math.round(w*0.062);
    wrapText(headline, pad, y, w-pad*2, hfs3, theme.text, 'bold', 1.1, 2);
    y -= textH(headline, hfs3, w-pad*2)+16;
  }
  if (document.getElementById('elHighlights').checked) drawHighlightsStrip(pkg, theme, w, h, pad);
}

function renderMinimal(pkg, theme, img, w, h) {
  var pad = parseInt(document.getElementById('rPad').value)||64;
  ctx.fillStyle = theme.bg; ctx.fillRect(0,0,w,h);

  // Big ghost number
  ctx.save(); ctx.globalAlpha=0.07;
  ctx.font='bold '+Math.round(h*0.3)+'px '+S.font;
  ctx.fillStyle=S.accent; ctx.textAlign='right';
  ctx.fillText(pkg.duration_days, w-pad+20, Math.round(h*0.5));
  ctx.textAlign='left'; ctx.restore();

  if (img) {
    ctx.save(); ctx.beginPath(); roundR(ctx, w-pad-280, pad, 280, 340, 3); ctx.clip();
    drawImg(img, w-pad-280, pad, 280, 340); ctx.restore();
    // Subtle fade edge
    var eg = ctx.createLinearGradient(w-pad-280,0,w-pad-140,0);
    eg.addColorStop(0,hexRgba(theme.bg,0.8)); eg.addColorStop(1,'transparent');
    ctx.fillStyle=eg; ctx.fillRect(w-pad-280, pad, 140, 340);
  }

  var y = Math.round(h*0.14);
  drawAccentBar(pad, y, 80, 4); y+=32;
  ctx.font='bold '+Math.round(w*0.016)+'px Arial'; ctx.fillStyle=S.accent;
  ctx.fillText('SAFARI ZANGU VENTURES', pad, y); y+=60;

  var headline = applyCase(document.getElementById('txHeadline').value||pkg.name);
  if (document.getElementById('elHeadline').checked) {
    wrapText(headline, pad, y, w-pad*2-300, Math.round(w*0.07), theme.text, 'bold', 1.1, 2);
    y += textH(headline, Math.round(w*0.07), w-pad*2-300)+36;
  }
  var tagline = document.getElementById('txTagline').value||pkg.short_description||'';
  if (tagline && document.getElementById('elTagline').checked) {
    wrapText(tagline, pad, y, w-pad*2-100, Math.round(w*0.028), theme.sub, 'normal', 1.65, 2); y+=textH(tagline, Math.round(w*0.028), w-pad*2-100)+50;
  }
  if (document.getElementById('elDivider').checked) {
    ctx.fillStyle=hexRgba(theme.text,0.12); ctx.fillRect(pad,y,w-pad*2,1); y+=36;
  }
  var facts = [{l:'Duration',v:pkg.duration_days+' Nights'},{l:'From',v:'$'+Number(pkg.price_1pax).toLocaleString()+'/pp'},{l:'Accommodation',v:ucw(pkg.accommodation_level)}];
  var cw2=(w-pad*2)/facts.length;
  facts.forEach(function(f,i){
    var cx2=pad+i*cw2;
    ctx.font='bold '+Math.round(w*0.034)+'px '+S.font; ctx.fillStyle=theme.text; ctx.fillText(f.v,cx2,y);
    ctx.font=Math.round(w*0.018)+'px Arial'; ctx.fillStyle=theme.sub; ctx.fillText(f.l,cx2,y+38);
  }); y+=96;

  if (document.getElementById('elHighlights').checked) {
    var lines2 = document.getElementById('txHighlights').value.split('\n').map(function(l){return l.trim();}).filter(Boolean);
    lines2.slice(0,5).forEach(function(inc){
      ctx.font=Math.round(w*0.022)+'px Arial'; ctx.fillStyle=theme.sub;
      ctx.fillText('— '+inc, pad, y); y+=Math.round(w*0.036);
    });
  }
  if (document.getElementById('elCTA').checked) {
    ctx.font='bold '+Math.round(w*0.022)+'px Arial'; ctx.fillStyle=S.accent;
    ctx.fillText((document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase(), pad, h-60);
  }
  ctx.font='bold '+Math.round(w*0.016)+'px Arial'; ctx.fillStyle=hexRgba(theme.text,0.25);
  ctx.textAlign='right'; ctx.fillText('SAFARI ZANGU VENTURES', w-pad, h-60); ctx.textAlign='left';
}

function renderItinerary(pkg, theme, img, w, h) {
  var pad = parseInt(document.getElementById('rPad').value)||64;
  var ov  = parseInt(document.getElementById('rOverlay').value)/100;
  if (img) { clipDraw(img,0,0,w,h); ctx.fillStyle=hexRgba(theme.bg,ov+0.1); ctx.fillRect(0,0,w,h); drawVignette(w,h,parseInt(document.getElementById('rVignette').value)); }
  var y=72;
  if (document.getElementById('elLogo').checked) { drawLogo(theme,w,y,pad); y+=76; }
  var headline = applyCase(document.getElementById('txHeadline').value||pkg.name);
  ctx.font='bold '+Math.round(w*0.052)+'px '+S.font; ctx.fillStyle=theme.text; ctx.fillText(headline,pad,y); y+=58;
  drawAccentBar(pad,y,100,3); y+=32;
  if (document.getElementById('elPrice').checked) {
    ctx.font='bold '+Math.round(w*0.026)+'px Arial'; ctx.fillStyle=S.accent;
    ctx.fillText(pkg.duration_days+' NIGHTS  ·  FROM $'+Number(pkg.price_1pax).toLocaleString()+'/PP', pad, y); y+=64;
  }
  var days = pkg.itin_days_arr.length ? pkg.itin_days_arr : pkg.itin_arr||[];
  var maxD = Math.min(days.length, S.size==='story'?7:5);
  var lh2 = Math.round((h-y-80) / Math.max(maxD,1));
  days.slice(0,maxD).forEach(function(day,i){
    var dy = y + i*lh2 + lh2*0.5;
    ctx.fillStyle = i===0 ? S.accent : hexRgba(S.accent, 0.35);
    ctx.beginPath(); ctx.arc(pad+20, dy, 22, 0, Math.PI*2); ctx.fill();
    ctx.font='bold '+Math.round(w*0.022)+'px Arial'; ctx.fillStyle=theme.bg; ctx.textAlign='center';
    ctx.fillText(String(day.day||i+1), pad+20, dy+8); ctx.textAlign='left';
    if (i<maxD-1) { ctx.fillStyle=hexRgba(S.accent,0.2); ctx.fillRect(pad+18,dy+24,4,lh2-40); }
    ctx.font='bold '+Math.round(w*0.027)+'px Arial'; ctx.fillStyle=theme.text;
    ctx.fillText(String(day.title||'Day '+(i+1)).substr(0,40), pad+58, dy);
    if (day.location||day.description) {
      var sub=(day.location||day.description||'').substr(0,48);
      ctx.font=Math.round(w*0.02)+'px Arial'; ctx.fillStyle=theme.sub; ctx.fillText(sub, pad+58, dy+26);
    }
  });
  if (document.getElementById('elCTA').checked) {
    ctx.font='bold '+Math.round(w*0.022)+'px Arial'; ctx.fillStyle=S.accent;
    ctx.fillText((document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase(), pad, h-52);
  }
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||pkg.duration_days+' NIGHTS',w,h);
}

function renderPrice(pkg, theme, img, w, h) {
  var pad = parseInt(document.getElementById('rPad').value)||64;
  var ov  = parseInt(document.getElementById('rOverlay').value)/100;
  if (img) { clipDraw(img,0,0,w,h); ctx.fillStyle=hexRgba(theme.bg,ov); ctx.fillRect(0,0,w,h); }
  if (document.getElementById('elLogo').checked) drawLogo(theme,w,56,pad);
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||pkg.duration_days+' NIGHTS',w,h);
  var cw3=Math.round(w*0.76), ch=Math.round(h*0.52), cx3=(w-cw3)/2, cy3=(h-ch)/2;
  ctx.fillStyle=hexRgba(theme.bg,0.9); ctx.fillRect(cx3,cy3,cw3,ch);
  drawAccentBar(cx3,cy3,cw3,4);
  var iy=cy3+52; ctx.textAlign='center';
  var headline=applyCase(document.getElementById('txHeadline').value||pkg.name);
  ctx.font='bold '+Math.round(w*0.04)+'px '+S.font; ctx.fillStyle=theme.text; ctx.fillText(headline,w/2,iy); iy+=48;
  var tag=document.getElementById('txTagline').value||pkg.short_description||'';
  if (tag) { ctx.font=Math.round(w*0.022)+'px Arial'; ctx.fillStyle=theme.sub; ctx.fillText(tag.substr(0,55),w/2,iy); iy+=44; }
  ctx.fillStyle=hexRgba(S.accent,0.25); ctx.fillRect(cx3+50,iy,cw3-100,1); iy+=40;
  var tiers=[{l:'DURATION',v:pkg.duration_days+' Nights'},{l:'FROM',v:'$'+Number(pkg.price_1pax).toLocaleString()},{l:'STYLE',v:ucw(pkg.accommodation_level)}];
  var tw2=(cw3-60)/3;
  tiers.forEach(function(t,i){
    var tx2=cx3+30+i*tw2+tw2/2;
    ctx.font='bold '+Math.round(w*0.016)+'px Arial'; ctx.fillStyle=S.accent; ctx.fillText(t.l,tx2,iy);
    ctx.font='bold '+Math.round(w*0.042)+'px '+S.font; ctx.fillStyle=theme.text; ctx.fillText(t.v,tx2,iy+50);
    ctx.font=Math.round(w*0.016)+'px Arial'; ctx.fillStyle=theme.sub; ctx.fillText(i===1?'per person':'',tx2,iy+76);
  }); iy+=118;
  if (document.getElementById('elHighlights').checked) {
    var lines3=document.getElementById('txHighlights').value.split('\n').map(function(l){return l.trim();}).filter(Boolean);
    ctx.font=Math.round(w*0.018)+'px Arial'; ctx.fillStyle=theme.sub;
    ctx.fillText(lines3.slice(0,3).join('  ·  '), w/2, iy);
  }
  ctx.textAlign='left';
  if (document.getElementById('elCTA').checked) {
    ctx.font='bold '+Math.round(w*0.024)+'px Arial'; ctx.fillStyle=S.accent; ctx.textAlign='center';
    ctx.fillText((document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase(), w/2, cy3+ch+52);
    ctx.textAlign='left';
  }
}

function renderStoryTpl(pkg, theme, img, w, h) {
  // Story format: Bold vertical layout
  var pad = parseInt(document.getElementById('rPad').value)||64;
  var ov  = parseInt(document.getElementById('rOverlay').value)/100;
  if (img) { clipDraw(img,0,0,w,h); ctx.fillStyle=hexRgba(theme.bg,ov*0.85); ctx.fillRect(0,0,w,h); drawVignette(w,h,parseInt(document.getElementById('rVignette').value)); }
  else { ctx.fillStyle=theme.bg; ctx.fillRect(0,0,w,h); }

  // Top section
  var y=90;
  drawLogo(theme,w,y,pad); y+=90;

  // Big centred headline
  var headline=applyCase(document.getElementById('txHeadline').value||pkg.name);
  ctx.textAlign='center';
  wrapText(headline, w/2, h*0.35, w-pad*2, Math.round(w*0.09), theme.text, 'bold', 1.1, 3);

  // Accent bar centre
  drawAccentBar(w/2-50,Math.round(h*0.42),100,3);

  // Tagline
  var tag=document.getElementById('txTagline').value||pkg.short_description||'';
  if (tag) { ctx.font=Math.round(w*0.032)+'px Arial'; ctx.fillStyle=theme.sub; ctx.fillText(tag.substr(0,40),w/2,Math.round(h*0.47)); }

  // Price big
  if (document.getElementById('elPrice').checked) {
    ctx.font='bold '+Math.round(w*0.052)+'px '+S.font; ctx.fillStyle=S.accent;
    ctx.fillText('$'+Number(pkg.price_1pax).toLocaleString(),w/2,Math.round(h*0.56));
    ctx.font=Math.round(w*0.024)+'px Arial'; ctx.fillStyle=theme.sub;
    ctx.fillText('per person · '+pkg.duration_days+' nights',w/2,Math.round(h*0.60));
  }

  // Highlights as pills in centre
  if (document.getElementById('elHighlights').checked) {
    var lines4=document.getElementById('txHighlights').value.split('\n').map(function(l){return l.trim();}).filter(Boolean).slice(0,4);
    var py=Math.round(h*0.68);
    lines4.forEach(function(inc){
      var tw3=ctx.measureText('✓ '+inc).width + 48;
      ctx.fillStyle=hexRgba(S.accent,0.15); ctx.fillRect(w/2-tw3/2, py-22, tw3, 34);
      ctx.fillStyle=hexRgba(S.accent,0.5); ctx.fillRect(w/2-tw3/2, py-22, 3, 34);
      ctx.font='bold '+Math.round(w*0.025)+'px Arial'; ctx.fillStyle=theme.text;
      ctx.fillText('✓ '+inc, w/2-tw3/2+14, py); py+=48;
    });
  }

  // Bottom CTA
  if (document.getElementById('elCTA').checked) {
    var cta2=document.getElementById('txCTA').value||'safarizanguven.com';
    ctx.font='bold '+Math.round(w*0.026)+'px Arial'; ctx.fillStyle=S.accent;
    ctx.fillText(cta2.toUpperCase(), w/2, h-70);
  }
  ctx.textAlign='left';
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||pkg.duration_days+'N',w,h);
}

function renderFeature(pkg, theme, img, w, h) {
  // Magazine feature style: top image + text panel below
  var pad = parseInt(document.getElementById('rPad').value)||64;
  var imgH = Math.round(h*0.52);
  if (img) { clipDraw(img,0,0,w,imgH); }
  // Fade at bottom of image
  var fg=ctx.createLinearGradient(0,imgH*0.6,0,imgH);
  fg.addColorStop(0,'transparent'); fg.addColorStop(1,theme.bg);
  ctx.fillStyle=fg; ctx.fillRect(0,0,w,imgH);

  ctx.fillStyle=theme.bg; ctx.fillRect(0,imgH,w,h-imgH);
  drawAccentBar(0,imgH,w,4);

  if (document.getElementById('elLogo').checked) drawLogo(theme,w,48,pad);
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||pkg.duration_days+' NIGHTS',w,h);

  var y2=imgH+60;
  var headline=applyCase(document.getElementById('txHeadline').value||pkg.name);
  if (document.getElementById('elHeadline').checked) {
    wrapText(headline, pad, y2, w-pad*2, Math.round(w*0.062), theme.text, 'bold', 1.1, 2);
    y2+=textH(headline, Math.round(w*0.062), w-pad*2)+28;
  }
  var tag=document.getElementById('txTagline').value||pkg.short_description||'';
  if (tag) { wrapText(tag, pad, y2, w-pad*2, Math.round(w*0.026), theme.sub, 'normal', 1.6, 2); y2+=textH(tag,Math.round(w*0.026),w-pad*2)+32; }
  if (document.getElementById('elDivider').checked) { ctx.fillStyle=hexRgba(S.accent,0.3); ctx.fillRect(pad,y2,w-pad*2,1); y2+=28; }
  var facts2=[pkg.duration_days+' Nights', '$'+Number(pkg.price_1pax).toLocaleString()+'/pp', ucw(pkg.accommodation_level)];
  var fw=(w-pad*2)/facts2.length;
  facts2.forEach(function(f,i){
    ctx.font='bold '+Math.round(w*0.03)+'px '+S.font; ctx.fillStyle=theme.text; ctx.fillText(f, pad+i*fw, y2+28);
    ctx.font=Math.round(w*0.016)+'px Arial'; ctx.fillStyle=theme.sub; ctx.fillText(['Duration','From','Accommodation'][i], pad+i*fw, y2+52);
  }); y2+=80;
  if (document.getElementById('elCTA').checked) {
    ctx.font='bold '+Math.round(w*0.02)+'px Arial'; ctx.fillStyle=S.accent;
    ctx.fillText((document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase(), pad, h-52);
  }
}

function renderDarkFull(pkg, theme, img, w, h) {
  // Pure dark, no image. Typography only.
  var pad = parseInt(document.getElementById('rPad').value)||64;
  ctx.fillStyle=theme.bg; ctx.fillRect(0,0,w,h);
  drawAccentBar(pad,60,4,h-120);
  var y3=80;
  ctx.font='bold '+Math.round(w*0.016)+'px Arial'; ctx.fillStyle=S.accent;
  ctx.fillText('SAFARI ZANGU VENTURES', pad+24, y3); y3+=60;
  var headline=applyCase(document.getElementById('txHeadline').value||pkg.name);
  ctx.font='bold '+Math.round(w*0.075)+'px '+S.font; ctx.fillStyle=theme.text;
  var words5=headline.split(' '); var line5='';
  words5.forEach(function(word){ var t=line5+(line5?' ':'')+word; if(ctx.measureText(t).width>w-pad-24-40 && line5){ctx.fillText(line5,pad+24,y3);y3+=Math.round(w*0.085);line5=word;}else line5=t; });
  if(line5){ctx.fillText(line5,pad+24,y3);y3+=Math.round(w*0.085);}
  y3+=20; drawAccentBar(pad+24,y3,100,3); y3+=40;
  var tag2=document.getElementById('txTagline').value||pkg.short_description||'';
  if (tag2) { ctx.font=Math.round(w*0.03)+'px Arial'; ctx.fillStyle=theme.sub; wrapText(tag2,pad+24,y3,w-pad-48,Math.round(w*0.03),'','normal',1.65,2); y3+=100; }
  // Animated feel — staggered includes
  var lines5=document.getElementById('txHighlights').value.split('\n').map(function(l){return l.trim();}).filter(Boolean);
  lines5.slice(0,5).forEach(function(inc,i){
    var x5=pad+24+(i%2===0?0:30);
    ctx.fillStyle=hexRgba(S.accent,0.5+i*0.08);
    ctx.fillRect(x5,y3-20,2,28);
    ctx.font=Math.round(w*0.025)+'px Arial'; ctx.fillStyle=theme.sub;
    ctx.fillText(inc, x5+16, y3); y3+=Math.round(w*0.04);
  });
  y3+=20;
  if (document.getElementById('elPrice').checked) {
    ctx.font='bold '+Math.round(w*0.05)+'px '+S.font; ctx.fillStyle=S.accent;
    ctx.fillText('From $'+Number(pkg.price_1pax).toLocaleString(), pad+24, h-100);
    ctx.font=Math.round(w*0.02)+'px Arial'; ctx.fillStyle=theme.sub;
    ctx.fillText('USD per person', pad+24, h-70);
  }
  if (document.getElementById('elCTA').checked) {
    ctx.font='bold '+Math.round(w*0.018)+'px Arial'; ctx.fillStyle=hexRgba(theme.text,0.35);
    ctx.textAlign='right'; ctx.fillText((document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase(), w-pad, h-70); ctx.textAlign='left';
  }
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||pkg.duration_days+' NIGHTS',w,h);
}

/* ── Utils ────────────────────────────────────────────────── */
function roundR(ctx, x, y, w, h, r) {
  ctx.beginPath(); ctx.moveTo(x+r,y);
  ctx.lineTo(x+w-r,y); ctx.quadraticCurveTo(x+w,y,x+w,y+r);
  ctx.lineTo(x+w,y+h-r); ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h);
  ctx.lineTo(x+r,y+h); ctx.quadraticCurveTo(x,y+h,x,y+h-r);
  ctx.lineTo(x,y+r); ctx.quadraticCurveTo(x,y,x+r,y); ctx.closePath();
}
function ucw(s){ return String(s||'').replace(/[_-]/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();}); }

/* ── Download & Export ────────────────────────────────────── */
function downloadPNG() {
  var pkg=PKGS[S.pkg];
  var a=document.createElement('a');
  a.download='szv-'+pkg.slug+'-'+S.tpl+'-'+S.size+'.png';
  a.href=canvas.toDataURL('image/png'); a.click(); toast('Downloaded PNG');
}
function downloadJPG() {
  var pkg=PKGS[S.pkg];
  var a=document.createElement('a');
  a.download='szv-'+pkg.slug+'-'+S.tpl+'-'+S.size+'.jpg';
  a.href=canvas.toDataURL('image/jpeg',0.92); a.click(); toast('Downloaded JPG');
}
function copyToClipboard() {
  canvas.toBlob(function(blob){
    try {
      navigator.clipboard.write([new ClipboardItem({'image/png':blob})]).then(function(){ toast('Copied to clipboard'); }).catch(function(){ toast('Copy not supported — use Download'); });
    } catch(e){ toast('Copy not supported — use Download'); }
  });
}

/* ── AI Caption — prompt is built server-side in api/generate_caption.php,
   which also owns the real package data and the API key. ────────────── */
async function generateCaption() {
  var pkg=PKGS[S.pkg], btn=document.getElementById('aibtn'), sp=document.getElementById('spinner');
  btn.disabled=true; sp.style.display='inline-block';
  document.getElementById('captionOut').textContent='Generating...';
  try {
    var r=await fetch((window.BASE_PATH||'')+'/api/generate_caption.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':window.CSRF_TOKEN||''},
      body:new URLSearchParams({package_id:pkg.id, tone:S.captionTone||'premium'})
    });
    var d=await r.json();
    document.getElementById('captionOut').textContent = d.success ? d.caption : ('Error: '+(d.message||'Could not generate caption.'));
    if (d.success) refreshHashtags();
  } catch(e){ document.getElementById('captionOut').textContent='Error: '+e.message; }
  btn.disabled=false; sp.style.display='none';
}

function copyCaption() {
  var t=document.getElementById('captionOut').textContent;
  navigator.clipboard.writeText(t).then(function(){ toast('Caption copied'); });
}

/* ── Hashtags ─────────────────────────────────────────────── */
var ALL_HASHTAGS = {
  base: ['#TanzaniaSafari','#SafariZanguVentures','#AfricaSafari','#Serengeti','#Ngorongoro','#Tanzania','#WildlifePhotography','#SafariLife','#BigFive','#AfricaWildlife','#TravelAfrica','#VisitTanzania','#AfricanSafari','#Arusha','#SafariGuide','#BushLife','#NaturalHabitat','#WildAfrica'],
  'classic-safari':  ['#ClassicSafari','#Tarangire','#SerengetiMigration','#TarangireElephants','#NgorongoroCrater'],
  'luxury-safari':   ['#LuxuryTravel','#BalloonSafari','#ExclusiveLodge','#LuxuryAfrica','#PrivateSafari'],
  'comfort-safari':  ['#ComfortSafari','#LakeManyara','#TreeClimbingLions','#MidRangeSafari','#SerengetiCentral'],
  'safari-zanzibar': ['#Zanzibar','#SafariAndBeach','#ZanzibarIsland','#StoneT own','#IndianOcean'],
  'cultural-safari': ['#CulturalTanzania','#MaasaiCulture','#CoffeeFarm','#EcoTourism','#CommunityTravel'],
};
var activeHashtags = new Set();

function refreshHashtags() {
  var pkg=PKGS[S.pkg];
  var all=[].concat(ALL_HASHTAGS.base, ALL_HASHTAGS[pkg.slug]||[]);
  all.sort(function(){return Math.random()-0.5;});
  all=all.slice(0,28);
  activeHashtags=new Set(all);
  renderHashtagChips(all);
  document.getElementById('txHashtags').value=all.join(' ');
}

function renderHashtagChips(tags) {
  var el=document.getElementById('hashtagChips');
  el.innerHTML=tags.map(function(h){
    return '<span class="pm-chip '+(activeHashtags.has(h)?'active':'')+'" onclick="toggleHashtag(this,\''+h+'\')" >'+h+'</span>';
  }).join('');
}

function toggleHashtag(el, tag) {
  if (activeHashtags.has(tag)) { activeHashtags.delete(tag); el.classList.remove('active'); }
  else { activeHashtags.add(tag); el.classList.add('active'); }
  var active=Array.from(activeHashtags);
  if (!active.length) active=Array.from(document.querySelectorAll('.pm-chip')).map(function(c){return c.textContent;});
  document.getElementById('txHashtags').value=active.join(' ');
}

function copyHashtags() {
  var t=document.getElementById('txHashtags').value;
  navigator.clipboard.writeText(t).then(function(){ toast('Hashtags copied'); });
}

/* ── Stats ────────────────────────────────────────────────── */
function updateStats(pkg) {
  var html='<div class="pm-stat-grid">';
  [{l:'Duration',v:pkg.duration_days+' nights'},{l:'Price',v:'$'+Number(pkg.price_1pax).toLocaleString()+'/pp'}].forEach(function(s){
    html+='<div class="pm-stat-cell"><div class="pm-stat-label">'+s.l+'</div><div class="pm-stat-val">'+s.v+'</div></div>';
  });
  html+='</div>';
  html+='<div style="font-size:.72rem;color:var(--pm-muted);line-height:1.9">';
  html+='<div><strong style="color:var(--pm-text)">Level:</strong> '+ucw(pkg.accommodation_level)+'</div>';
  html+='<div><strong style="color:var(--pm-text)">Images:</strong> '+(pkg.gallery||[]).length+' photos</div>';
  var dcount=(pkg.itin_days_arr||pkg.itin_arr||[]).length;
  html+='<div><strong style="color:var(--pm-text)">Itinerary:</strong> '+dcount+' days</div>';
  html+='<div><strong style="color:var(--pm-text)">Includes:</strong> '+(pkg.includes_arr||[]).length+' items</div>';
  html+='</div>';
  document.getElementById('statsPanel').innerHTML=html;
}

/* ── Toast ────────────────────────────────────────────────── */
function toast(msg) {
  var t=document.createElement('div');
  t.style.cssText='position:fixed;bottom:1.5rem;right:1.5rem;background:var(--pm-accent);color:#000;padding:.65rem 1.25rem;font-size:.75rem;font-weight:700;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.4)';
  t.textContent=msg; document.body.appendChild(t);
  setTimeout(function(){t.style.opacity='0';t.style.transition='opacity .3s';setTimeout(function(){t.remove();},300);},2200);
}

// crop/zoom tool
// blur filter
// text overlay on image
// ── Advanced Tools JS ──────────────────────────────────────
function toggleTools() {
    var body = document.getElementById('advanced-tools-body');
    var icon = document.getElementById('tools-toggle-icon');
    if (body.style.display === 'none') {
        body.style.display = 'block'; icon.textContent = '▲ Collapse';
    } else {
        body.style.display = 'none'; icon.textContent = '▼ Expand';
    }
}

var _imgZoom = 1, _imgPanX = 0, _imgPanY = 0;
var _overlayTexts = [];

function applyFilters() {
    var b = document.getElementById('tool-brightness').value;
    var c = document.getElementById('tool-contrast').value;
    var s = document.getElementById('tool-saturation').value;
    var bl= document.getElementById('tool-blur').value;
    document.getElementById('val-brightness').textContent = b+'%';
    document.getElementById('val-contrast').textContent   = c+'%';
    document.getElementById('val-saturation').textContent = s+'%';
    document.getElementById('val-blur').textContent       = bl+'px';
    var canvas = document.getElementById('poster-canvas') || document.querySelector('canvas');
    if (canvas) {
        canvas.style.filter = 'brightness('+b+'%) contrast('+c+'%) saturate('+s+'%) blur('+bl+'px)';
    }
}

// crop/zoom tool marker
function applyZoom() {
    _imgZoom  = parseInt(document.getElementById('tool-zoom').value) / 100;
    _imgPanX  = parseInt(document.getElementById('tool-pan-x').value);
    _imgPanY  = parseInt(document.getElementById('tool-pan-y').value);
    document.getElementById('val-zoom').textContent = Math.round(_imgZoom*100)+'%';
    var canvas = document.getElementById('poster-canvas') || document.querySelector('canvas');
    if (canvas) {
        canvas.style.transform = 'scale('+_imgZoom+') translate('+_imgPanX+'px,'+_imgPanY+'px)';
        canvas.style.transformOrigin = 'center center';
    }
}

function addTextOverlay() {
    var text  = document.getElementById('overlay-text').value.trim();
    if (!text) return;
    var size  = parseInt(document.getElementById('overlay-size').value) || 32;
    var color = document.getElementById('overlay-color').value;
    var pos   = document.getElementById('overlay-pos').value;
    var style = document.getElementById('overlay-bold').value;

    // Draw on canvas
    var canvas = document.getElementById('poster-canvas') || document.querySelector('canvas');
    if (!canvas) { alert('Canvas not ready — generate a poster first'); return; }
    var ctx = canvas.getContext('2d');
    ctx.save();
    ctx.font = style + ' ' + size + 'px Arial, sans-serif';
    ctx.fillStyle = color;
    ctx.textAlign = 'center';
    ctx.shadowColor = 'rgba(0,0,0,0.7)';
    ctx.shadowBlur = 8;
    var x = canvas.width / 2;
    var y = pos==='top' ? size+20 : pos==='center' ? canvas.height/2 : canvas.height-40;
    ctx.fillText(text, x, y);
    ctx.restore();
    document.getElementById('overlay-text').value = '';
}

function resetTools() {
    ['tool-brightness','tool-contrast','tool-saturation'].forEach(function(id){ document.getElementById(id).value=100; });
    document.getElementById('tool-blur').value = 0;
    document.getElementById('tool-zoom').value = 100;
    document.getElementById('tool-pan-x').value = 0;
    document.getElementById('tool-pan-y').value = 0;
    var canvas = document.getElementById('poster-canvas') || document.querySelector('canvas');
    if (canvas) { canvas.style.filter=''; canvas.style.transform=''; }
    applyFilters(); applyZoom();
}

</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
