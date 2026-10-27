<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
// Agents have no access to this section (see the sidebar role filter in
// layouts/admin.php); the nav hiding alone isn't enforcement, so block here too.
if (\App\Middleware\AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyRedirect('index.php', 'page', 'poster_maker.php', 'agent_access');
}
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
/* This is a canvas-based design tool — composing a poster meaningfully
   needs real screen width, so this isn't rebuilt as a true mobile UI.
   What it does get is the baseline every page here needs: no forced
   horizontal scroll of the whole admin shell. Sidebars stack above/below
   the canvas and scroll with the page instead of three fixed columns
   fighting for a 320-414px viewport. */
@media (max-width: 1024px) {
  .pm-wrap {
    grid-template-columns: 1fr;
    height: auto;
    overflow: visible;
  }
  /* Grid items default to min-width:auto, which lets a child's own
     content (button labels, swatch rows) stay wider than its 1fr track
     and force the whole grid wider than the viewport again even after
     collapsing to one column — min-width:0 is what actually makes the
     column respect the track width. */
  .pm-sidebar, .pm-right, .pm-centre { min-width: 0; max-height: 50vh; }
  /* .pm-wrap's -1.5rem margin bleeds it edge-to-edge against
     .admin-content's desktop padding (1.5rem at that breakpoint) — but
     .admin-content's own padding shrinks well below 1.5rem at mobile/
     tablet widths, so that same fixed negative margin overshoots the
     real edge and was the actual source of every pm-wrap/pm-sidebar/
     pm-section width mismatch found above (not a flex/grid sizing
     issue at all, despite how it looked at first). */
  .pm-wrap { margin: 0; }
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
  min-width: 0;
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
  min-width: 0;
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
.pm-btn.active { background: var(--pm-accent); color: #000; border-color: var(--pm-accent); }
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

/* Direct-manipulation editing — click any element on the poster to
   select it, drag to move it. #posterCanvas itself shows a grab/grabbing
   cursor via JS (set on hover/drag), not CSS, since it depends on
   hit-testing the current layout. */
.pm-selected-box {
  background: rgba(79,156,255,.08);
  border: 1px solid rgba(79,156,255,.4);
  padding: .65rem .75rem;
  border-radius: 2px;
  margin-bottom: .5rem;
}
.pm-hint {
  font-size: .62rem;
  color: var(--pm-muted);
  font-style: italic;
  line-height: 1.6;
}
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
      <button class="pm-tpl-btn"        onclick="setTpl('inclusions')" id="tpl-inclusions">Inclusions</button>
      <button class="pm-tpl-btn"        onclick="setTpl('gallery')"   id="tpl-gallery">Gallery</button>
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
    <label class="pm-toggle"><input type="checkbox" id="elLogo"       checked onchange="render()"><span>Brand Mark (SZV)</span></label>
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

  <!-- SELECTED ELEMENT / DRAG EDITING -->
  <div class="pm-section">
    <div class="pm-section-title">Edit on Canvas</div>
    <div class="pm-tool-row" style="margin-bottom:.65rem">
      <button class="pm-btn pm-btn--sm" onclick="addCustomText()">+ Add Text</button>
      <button class="pm-btn pm-btn--sm" onclick="addCustomBox()">+ Add Box</button>
    </div>
    <div id="selectedPanel" class="pm-selected-box">
      <span class="pm-hint">Click any element directly on the poster to select it, then drag to move it, or edit its font, size and colour below.</span>
    </div>
    <button class="pm-btn pm-btn--sm" onclick="resetElement()">Reset All Positions</button>
  </div>

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
  captionTone: 'premium',
  offsets:  {},   // per-element drag offsets for the ACTIVE template/size — {key: {dx,dy}}
  selected: null, // key of the element currently selected on the canvas
  styles:   {},   // per-element style overrides for BUILT-IN elements — {key: {fontFamily,fontSize,fontWeight,fontStyle,color,align}}, any field absent = inherit the template's own default
  lastStyle:{},   // the fully-resolved (default+override merged) style each element actually drew with on the last render pass — what the Edit panel reads to show true current state
  customEls:   [], // user-added elements, drawn on top of the template — [{id,type:'text'|'box',x,y,w,h,text,fontFamily,fontSize,fontWeight,fontStyle,color,align}]
  nextCustomId: 1,
};

// Built-in element keys that are pure shapes with no text of their own —
// the Edit panel shows just a colour swatch for these, no font controls.
var SHAPE_ONLY_KEYS = { divider:1, card:1, accentMark:1, cardBar:1, edgeBar:1 };
// Built-in keys with no editable style at all (position only) — 'photo' is
// just an image crop frame, nothing to recolour or re-font.
var POSITION_ONLY_KEYS = { photo:1 };
var FONT_FAMILIES = ['Georgia','Arial','Helvetica','Times New Roman','Courier New','Verdana','Trebuchet MS','Palatino Linotype','Garamond','Impact'];

// Human-readable names for the "Selected: …" panel — anything drawn
// through pos()/hit() below uses one of these keys.
var ELEMENT_LABELS = {
  brand: 'Brand Mark', brandFooter: 'Brand Mark (footer)', headline: 'Headline',
  tagline: 'Tagline', price: 'Price', badge: 'Duration Badge', cta: 'CTA / Website',
  note: 'Bottom Note', highlights: 'Highlights', divider: 'Divider', stats: 'Stats Row',
  days: 'Itinerary Days', watermark: 'Watermark', card: 'Price Card',
  ghostNum: 'Ghost Number', photo: 'Photo', accentMark: 'Accent Mark',
  cardBar: 'Card Top Bar', edgeBar: 'Edge Bar',
  includesList: 'Included Items', excludesList: 'Excluded Items',
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
  selectPackage(0);
  applyZoom(60);
});

/* ════════════════════════════════════════════════════════════
   DIRECT-MANIPULATION EDITING — click an element on the poster to
   select it, drag to move it. Every positioned element on the canvas
   is drawn through pos() (adds its current drag offset to the
   template's own "natural" x/y) and hit() (records a clickable box for
   it on this render pass) — see the TEMPLATES below for how each
   element uses them. Offsets are template+size-specific and reset
   automatically on either changing (see setTpl()/setSize()): a
   headline moved 200px right in "Hero" has no sensible equivalent
   position in "Story", a completely different layout.
   ════════════════════════════════════════════════════════════ */
var HIT = [];    // hit-testable boxes from the render pass just completed: [{key,x,y,w,h}]
var DRAG = null; // {key, startX, startY, startDx, startDy} while a drag is in progress

/** Adds this element's current drag offset to its natural (x,y). Call once per element per render, then draw at the returned point. */
function pos(key, x, y) {
  var o = S.offsets[key] || (S.offsets[key] = {dx:0, dy:0});
  return { x: x + o.dx, y: y + o.dy };
}

/** Records a clickable box for `key`, in canvas pixel space, (x,y) = top-left. Call right after drawing with the same box the draw call actually used. */
function hit(key, x, y, w, h) {
  HIT.push({ key:key, x:x, y:y, w:Math.max(w,24), h:Math.max(h,24) });
}

/** hit() for a text draw — handles the 3 ctx.textAlign values so the box lands under the text regardless of anchor. fs = font size actually used. */
function textHit(key, x, y, text, fs, totalH) {
  var tw = ctx.measureText(text).width;
  var align = ctx.textAlign || 'left';
  var bx = align === 'center' ? x - tw/2 : align === 'right' ? x - tw : x;
  hit(key, bx, y - fs*1.05, tw, totalH || fs*1.4);
}

function canvasPoint(evt) {
  var rect = canvas.getBoundingClientRect();
  return {
    x: (evt.clientX - rect.left) * (canvas.width / rect.width),
    y: (evt.clientY - rect.top)  * (canvas.height / rect.height),
  };
}

function hitTestAt(pt) {
  for (var i = HIT.length - 1; i >= 0; i--) { // topmost-drawn wins
    var r = HIT[i];
    if (pt.x >= r.x && pt.x <= r.x + r.w && pt.y >= r.y && pt.y <= r.y + r.h) return r.key;
  }
  return null;
}

function isCustomKey(key) { return !!key && key.indexOf('custom:') === 0; }
function findCustomEl(key) {
  if (!isCustomKey(key)) return null;
  var id = key.slice(7);
  for (var i = 0; i < S.customEls.length; i++) if (S.customEls[i].id === id) return S.customEls[i];
  return null;
}

function inHandle(pt) {
  if (!RESIZE_HANDLE) return false;
  var pad4 = 4; // a little slop around the small handle so it's easy to grab
  return pt.x >= RESIZE_HANDLE.x - pad4 && pt.x <= RESIZE_HANDLE.x + RESIZE_HANDLE.w + pad4
      && pt.y >= RESIZE_HANDLE.y - pad4 && pt.y <= RESIZE_HANDLE.y + RESIZE_HANDLE.h + pad4;
}

/** Starts a resize drag on whatever is currently selected. Custom boxes resize width+height from the drag delta directly; text (built-in or custom) resizes its font size from the average of dx/dy, so dragging diagonally — the natural "grow the corner" motion — scales it predictably either direction. */
function startResize(pt) {
  var key = S.selected;
  var isCustom = isCustomKey(key);
  var el = isCustom ? findCustomEl(key) : null;
  var isBox = !!(el && el.type === 'box');
  DRAG = {
    key: key, mode: 'resize', startX: pt.x, startY: pt.y, isCustom: isCustom, isBox: isBox,
    baseFontSize: isBox ? null : (isCustom ? el.fontSize : (S.lastStyle[key] ? S.lastStyle[key].fontSize : 32)),
    baseW: isBox ? el.w : null, baseH: isBox ? el.h : null,
  };
  canvas.style.cursor = 'nwse-resize';
}

canvas.addEventListener('mousedown', function(e) {
  var pt = canvasPoint(e);
  if (S.selected && inHandle(pt)) {
    startResize(pt);
    e.preventDefault();
    return;
  }
  var key = hitTestAt(pt);
  selectElement(key);
  if (!key) return;
  if (isCustomKey(key)) {
    var el = findCustomEl(key);
    DRAG = { key:key, mode:'move', startX:pt.x, startY:pt.y, startDx:el.x, startDy:el.y, custom:true };
  } else {
    var o = S.offsets[key] || (S.offsets[key] = {dx:0, dy:0});
    DRAG = { key:key, mode:'move', startX:pt.x, startY:pt.y, startDx:o.dx, startDy:o.dy };
  }
  canvas.style.cursor = 'grabbing';
  e.preventDefault();
});
window.addEventListener('mousemove', function(e) {
  if (!DRAG) return;
  var pt = canvasPoint(e);
  if (DRAG.mode === 'resize') {
    var ddx = pt.x - DRAG.startX, ddy = pt.y - DRAG.startY;
    if (DRAG.isBox) {
      var elB = findCustomEl(DRAG.key);
      if (elB) { elB.w = Math.max(20, Math.round(DRAG.baseW + ddx)); elB.h = Math.max(20, Math.round(DRAG.baseH + ddy)); }
    } else {
      var newSize = Math.max(10, Math.min(240, Math.round(DRAG.baseFontSize + (ddx + ddy) / 2)));
      if (DRAG.isCustom) {
        var elT = findCustomEl(DRAG.key);
        if (elT) elT.fontSize = newSize;
      } else {
        if (!S.styles[DRAG.key]) S.styles[DRAG.key] = {};
        S.styles[DRAG.key].fontSize = newSize;
      }
    }
    render();
    return;
  }
  if (DRAG.custom) {
    var el = findCustomEl(DRAG.key);
    if (el) { el.x = DRAG.startDx + (pt.x - DRAG.startX); el.y = DRAG.startDy + (pt.y - DRAG.startY); }
  } else {
    var o = S.offsets[DRAG.key];
    o.dx = DRAG.startDx + (pt.x - DRAG.startX);
    o.dy = DRAG.startDy + (pt.y - DRAG.startY);
  }
  render();
});
window.addEventListener('mouseup', function() {
  if (!DRAG) return;
  var wasResize = DRAG.mode === 'resize';
  DRAG = null;
  canvas.style.cursor = 'default';
  if (wasResize) renderEditPanel(); // sync the size slider/value to where the drag landed
});
canvas.addEventListener('mousemove', function(e) {
  if (DRAG) return;
  var pt = canvasPoint(e);
  canvas.style.cursor = (S.selected && inHandle(pt)) ? 'nwse-resize' : (hitTestAt(pt) ? 'grab' : 'default');
});
canvas.addEventListener('mouseleave', function() {
  if (!DRAG) canvas.style.cursor = 'default';
});

function selectElement(key) {
  S.selected = key;
  render();          // populates S.lastStyle for this key from the actual draw pass
  renderEditPanel();  // ...so the panel below can show the real resolved style, not a guess
}

function resetElement(key) {
  if (key) delete S.offsets[key];
  else S.offsets = {};
  render();
}

function resetElStyle() {
  if (S.selected && !isCustomKey(S.selected)) delete S.styles[S.selected];
  render();
  renderEditPanel();
}

function setElStyle(field, value) {
  var key = S.selected;
  if (!key) return;
  if (isCustomKey(key)) {
    var el = findCustomEl(key);
    if (el) el[field] = value;
  } else {
    if (!S.styles[key]) S.styles[key] = {};
    S.styles[key][field] = value;
  }
  render();
  renderEditPanel();
}

function toggleElStyle(field, onVal, offVal) {
  var key = S.selected;
  if (!key) return;
  var cur = isCustomKey(key) ? findCustomEl(key)[field] : (S.lastStyle[key] || {})[field];
  setElStyle(field, cur === onVal ? offVal : onVal);
}

function updateCustomText(value) {
  var el = findCustomEl(S.selected);
  if (el) { el.text = value; render(); }
}

function escAttr(s) { return String(s==null?'':s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

function addCustomText() {
  var id = 'c' + (S.nextCustomId++);
  S.customEls.push({
    id: id, type: 'text', text: 'New text', x: canvas.width/2, y: canvas.height/2,
    fontFamily: S.font, fontSize: Math.round(canvas.width*0.04), fontWeight: 'bold',
    fontStyle: 'normal', color: S.accent, align: 'center',
  });
  selectElement('custom:' + id);
}

function addCustomBox() {
  var id = 'c' + (S.nextCustomId++);
  var w0 = Math.round(canvas.width*0.2), h0 = Math.round(canvas.width*0.12);
  S.customEls.push({
    id: id, type: 'box', x: (canvas.width-w0)/2, y: (canvas.height-h0)/2,
    w: w0, h: h0, color: S.accent,
  });
  selectElement('custom:' + id);
}

function deleteCustomEl(key) {
  var el = findCustomEl(key);
  if (!el) return;
  S.customEls = S.customEls.filter(function(e){ return e.id !== el.id; });
  selectElement(null);
}

/* ── Edit Selected panel — shows the resolved (default+override) style
   for whatever is currently selected and lets every field be overridden.
   Reads from S.lastStyle (populated by T()/TW() during the render() call
   that selectElement() just ran) so the controls always reflect what's
   really on the canvas, not a guess at the template's hardcoded default. */
function renderEditPanel() {
  var panel = document.getElementById('selectedPanel');
  if (!panel) return;
  var key = S.selected;
  if (!key) {
    panel.innerHTML = '<span class="pm-hint">Click any element directly on the poster to select it, then drag to move it, or edit its font, size and colour below.</span>';
    return;
  }

  var isCustom  = isCustomKey(key);
  var custEl    = isCustom ? findCustomEl(key) : null;
  if (isCustom && !custEl) { S.selected = null; panel.innerHTML = ''; return; }
  var label     = isCustom ? (custEl.type === 'text' ? 'Custom Text' : 'Custom Box') : (ELEMENT_LABELS[key] || key);
  var posOnly   = !isCustom && POSITION_ONLY_KEYS[key];
  var shapeOnly = (isCustom && custEl.type === 'box') || (!isCustom && SHAPE_ONLY_KEYS[key]);
  var live      = isCustom ? custEl : (S.lastStyle[key] || {});

  var html = '<div style="font-size:.78rem;color:var(--pm-text);font-weight:700;margin-bottom:.65rem">' + label + '</div>';

  if (isCustom && custEl.type === 'text') {
    html += '<span class="pm-label">Text</span>'
      + '<input class="pm-input" style="margin-bottom:.6rem" value="' + escAttr(custEl.text) + '" oninput="updateCustomText(this.value)">';
  }

  if (!posOnly && !shapeOnly) {
    var curFam = live.fontFamily || S.font;
    html += '<span class="pm-label">Font</span><select class="pm-input" style="margin-bottom:.6rem" onchange="setElStyle(\'fontFamily\', this.value)">';
    FONT_FAMILIES.forEach(function(f) {
      html += '<option value="' + f + '"' + (curFam === f ? ' selected' : '') + '>' + f + '</option>';
    });
    html += '</select>';

    var curSize = live.fontSize || 32;
    html += '<span class="pm-label">Size — <span id="fsizeVal">' + curSize + 'px</span></span>'
      + '<input type="range" class="pm-range" min="10" max="240" value="' + curSize + '" style="margin-bottom:.6rem" '
      + 'oninput="document.getElementById(\'fsizeVal\').textContent=this.value+\'px\'; setElStyle(\'fontSize\', parseInt(this.value,10))">';

    var curWeight = live.fontWeight || 'normal', curStyle = live.fontStyle || 'normal', curAlign = live.align || 'left';
    function btn(active, onclick, label2) {
      return '<button class="pm-btn pm-btn--sm' + (active ? ' active' : '') + '" onclick="' + onclick + '">' + label2 + '</button>';
    }
    html += '<div class="pm-tool-row" style="margin-bottom:.75rem">'
      + btn(curWeight === 'bold', "toggleElStyle('fontWeight','bold','normal')", '<b>Bold</b>')
      + btn(curStyle === 'italic', "toggleElStyle('fontStyle','italic','normal')", '<i>Italic</i>')
      + btn(curAlign === 'left', "setElStyle('align','left')", 'Left')
      + btn(curAlign === 'center', "setElStyle('align','center')", 'Centre')
      + btn(curAlign === 'right', "setElStyle('align','right')", 'Right')
      + '</div>';
  }

  if (!posOnly) {
    var curColor = live.color || S.accent;
    html += '<span class="pm-label">' + (shapeOnly || key === 'badge' ? 'Fill Colour' : 'Colour') + '</span>'
      + '<input type="color" value="' + curColor + '" style="width:100%;height:32px;border:1px solid var(--pm-border);border-radius:2px;cursor:pointer;margin-bottom:.75rem;background:none;padding:2px" '
      + 'onchange="setElStyle(\'color\', this.value)">';
  }

  html += '<div class="pm-btn-row" style="margin-top:0">'
    + '<button class="pm-btn pm-btn--sm" onclick="resetElement(S.selected)">Reset Position</button>';
  if (!posOnly && !isCustom) html += '<button class="pm-btn pm-btn--sm" onclick="resetElStyle()">Reset Style</button>';
  if (isCustom) html += '<button class="pm-btn pm-btn--sm" style="border-color:#e87a7a;color:#e87a7a" onclick="deleteCustomEl(S.selected)">Delete</button>';
  html += '</div>';

  panel.innerHTML = html;
}

/** Elements whose size can be dragged via the resize handle — everything with text (font size) plus every custom element (custom text font size, custom box width/height). Pure decoration (dividers, the price card panel, the photo crop frame) is excluded — their "size" isn't a single meaningful number. */
function isResizable(key) {
  if (isCustomKey(key)) return true;
  return !POSITION_ONLY_KEYS[key] && !SHAPE_ONLY_KEYS[key];
}

var RESIZE_HANDLE = null; // {x,y,w,h} in canvas px for the currently selected element's resize grip, or null

function drawSelectionOutline() {
  RESIZE_HANDLE = null;
  if (!S.selected) return;
  for (var i = 0; i < HIT.length; i++) {
    if (HIT[i].key !== S.selected) continue;
    var r = HIT[i];
    ctx.save();
    ctx.strokeStyle = '#4f9cff'; ctx.lineWidth = 2; ctx.setLineDash([7,5]);
    ctx.strokeRect(r.x - 8, r.y - 8, r.w + 16, r.h + 16);
    ctx.restore();
    if (isResizable(S.selected)) {
      var hs = 16;
      var hx = r.x + r.w + 8 - hs/2, hy = r.y + r.h + 8 - hs/2;
      ctx.save();
      ctx.fillStyle = '#4f9cff'; ctx.fillRect(hx, hy, hs, hs);
      ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.strokeRect(hx, hy, hs, hs);
      ctx.restore();
      RESIZE_HANDLE = { x:hx, y:hy, w:hs, h:hs };
    }
    return;
  }
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
  document.getElementById('txBadge').value = (pkg.duration_days-1) + ' NIGHTS';

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
  // A dragged position is relative to the OLD template's completely
  // different layout — carrying it over would often land an element
  // off-canvas or on top of something else in the new one.
  S.offsets = {};
  selectElement(null); // also re-renders
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
  // Same reasoning as setTpl() — a story (1080×1920) canvas's natural
  // positions are nothing like a square (1080×1080) one's.
  S.offsets = {};
  selectElement(null); // also re-renders
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

  HIT.length = 0; // every element the template below draws re-registers its hit box this pass

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
    inclusions: renderInclusions,
    gallery:    renderGallery,
  };
  (fns[S.tpl] || renderHero)(pkg, theme, img, w, h);

  drawCustomElements();
  drawSelectionOutline();
}

function drawCustomElements() {
  S.customEls.forEach(function(el) {
    var key = 'custom:' + el.id;
    if (el.type === 'box') {
      ctx.fillStyle = el.color;
      ctx.fillRect(el.x, el.y, el.w, el.h);
      hit(key, el.x, el.y, el.w, el.h);
    } else {
      applyStyle(el);
      ctx.fillText(el.text, el.x, el.y);
      textHit(key, el.x, el.y, el.text, el.fontSize);
      ctx.textAlign = 'left';
    }
  });
}

/* ── PER-ELEMENT STYLE SYSTEM ────────────────────────────────
   Every built-in element draws through T() (single line) or TW() (word-
   wrapped, multi-line), which both resolve S.styles[key] (a user override,
   set via the Edit Selected panel) over the template's own hardcoded
   default for that draw call. Nothing changes visually until a user
   actually overrides an element — defaults ARE the override object's
   fallback values, so this is purely additive over the original templates.
   ──────────────────────────────────────────────────────────── */
function styleOf(key, d) {
  var o = S.styles[key] || {};
  return {
    fontFamily: o.fontFamily || d.fontFamily || S.font,
    fontSize:   o.fontSize   != null ? o.fontSize : d.fontSize,
    fontWeight: o.fontWeight || d.fontWeight || 'normal',
    fontStyle:  o.fontStyle  || d.fontStyle  || 'normal',
    color:      o.color      || d.color,
    align:      o.align      || d.align || 'left',
  };
}

function applyStyle(st) {
  var tokens = [];
  if (st.fontStyle === 'italic') tokens.push('italic');
  if (st.fontWeight === 'bold') tokens.push('bold');
  tokens.push(st.fontSize + 'px');
  tokens.push(st.fontFamily);
  ctx.font = tokens.join(' ');
  ctx.fillStyle = st.color;
  ctx.textAlign = st.align || 'left';
}

/** Draws one line of styled, selectable, draggable text. d = {fontFamily,fontSize,fontWeight,fontStyle,color,align} defaults. Returns the resolved font size (for layout chaining). */
function T(key, text, x, y, d) {
  var st = styleOf(key, d);
  S.lastStyle[key] = st;
  var p = pos(key, x, y);
  applyStyle(st);
  ctx.fillText(text, p.x, p.y);
  textHit(key, p.x, p.y, text, st.fontSize);
  ctx.textAlign = 'left';
  return st.fontSize;
}

/** Word-wrapped, styled, selectable, draggable text block. Returns total block height (for layout chaining) — replaces the old wrapText()+textH()+textHit() trio in one call. */
function TW(key, text, x, y, maxW, d, lhMult, maxLines) {
  var st = styleOf(key, d);
  S.lastStyle[key] = st;
  var p = pos(key, x, y);
  var hgt = wrapText(text, p.x, p.y, maxW, st.fontSize, st.color, st.fontWeight, lhMult, maxLines, st.fontFamily, st.fontStyle);
  textHit(key, p.x, p.y, text, st.fontSize, hgt);
  return hgt;
}

/** Resolved fill colour for a shape/object element (dividers, card panels, badge pill, etc) — same override store as text, just its .color field. */
function fillOf(key, defaultColor) {
  var o = S.styles[key];
  var c = (o && o.color) || defaultColor;
  if (!S.lastStyle[key]) S.lastStyle[key] = {};
  S.lastStyle[key].color = c;
  return c;
}

/* ── TEXT HELPERS ────────────────────────────────────────── */
function applyCase(str) {
  if (S.textCase === 'upper') return str.toUpperCase();
  if (S.textCase === 'lower') return str.toLowerCase();
  return str.replace(/\b\w/g, function(c){ return c.toUpperCase(); });
}

function wrapText(text, x, y, maxW, size, color, weight, lhMult, maxLines, family, fstyle) {
  var tokens = [];
  if (fstyle === 'italic') tokens.push('italic');
  if (weight === 'bold') tokens.push('bold');
  tokens.push(size + 'px');
  tokens.push(family || S.font);
  ctx.font = tokens.join(' ');
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
  T('brand', 'SZV', pad, y, {fontSize:Math.round(w*0.02), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
}

function drawBadge(text, w, h) {
  var bp = S.badgePos;
  var st = styleOf('badge', {fontSize:Math.round(w*0.02), fontWeight:'bold', fontFamily:'Arial', color:THEMES[S.theme].bg});
  S.lastStyle.badge = st;
  var fillColor = fillOf('badge', S.accent);
  applyStyle(st);
  var tw = ctx.measureText(text).width;
  var bw = tw + 28, bh = st.fontSize + 22;
  var bx, by;
  if (bp === 'tl') { bx = 50; by = 50; }
  else if (bp === 'br') { bx = w - 50 - bw; by = h - 50 - bh; }
  else { bx = w - 50 - bw; by = 50; } // tr default

  var p = pos('badge', bx, by);
  hit('badge', p.x, p.y, bw, bh);
  ctx.fillStyle = fillColor;
  ctx.fillRect(p.x, p.y, bw, bh);
  ctx.fillStyle = st.color;
  ctx.textAlign = 'center';
  ctx.fillText(text, p.x + bw/2, p.y + bh/2 + st.fontSize/3);
  ctx.textAlign = 'left';
}

/** key is optional — pass it to make this bar individually selectable and recolourable; omit for a purely decorative accent mark tied to the global accent colour. */
function drawAccentBar(x, y, w, h, key) {
  ctx.fillStyle = key ? fillOf(key, S.accent) : S.accent;
  ctx.fillRect(x, y, w, h);
  if (key) hit(key, x, y, w, h);
}

function drawHighlightsStrip(pkg, theme, w, h, pad) {
  var lines = document.getElementById('txHighlights').value.split('\n').map(function(l){ return l.trim(); }).filter(Boolean);
  if (!lines.length) return;
  var shown = lines.slice(0, 3);
  var sh = Math.round(h * 0.075);
  var p  = pos('highlights', 0, Math.round(h * 0.7));
  var sy = p.y;
  hit('highlights', p.x, sy, w, sh);
  ctx.fillStyle = theme.strip;
  ctx.fillRect(p.x, sy, w, sh);
  var st = styleOf('highlights', {fontSize:Math.round(w*0.022), fontFamily:'Arial', color:theme.text});
  S.lastStyle.highlights = st;
  var cw = (w - pad*2) / shown.length;
  ctx.textAlign = 'center';
  shown.forEach(function(inc, i) {
    var cx = p.x + pad + i*cw + cw/2;
    ctx.fillStyle = S.accent;
    ctx.font = 'bold ' + Math.round(w*0.022) + 'px Arial';
    ctx.fillText('✓', cx - Math.round(w*0.04), sy + sh/2 + 8);
    applyStyle(st); ctx.textAlign = 'center';
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
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value || (pkg.duration_days-1)+' NIGHTS', w, h);

  var y = h - 56;
  if (document.getElementById('elCTA').checked) {
    var cta = document.getElementById('txCTA').value || 'safarizanguven.com';
    T('cta', cta.toUpperCase(), pad, y, {fontSize:Math.round(w*0.022), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
    y -= 48;
  }
  var note = document.getElementById('txNote').value;
  if (note) {
    T('note', note, pad, y, {fontSize:Math.round(w*0.02), fontFamily:'Arial', color:theme.sub});
    y -= 42;
  }
  if (document.getElementById('elPrice').checked) {
    var priceTxt = 'From $'+Number(pkg.price_1pax).toLocaleString()+' per person';
    T('price', priceTxt, pad, y, {fontSize:Math.round(w*0.03), fontWeight:'bold', fontFamily:'Arial', color:theme.text});
    y -= 56;
  }
  if (document.getElementById('elDivider').checked) {
    var pdv = pos('divider', pad, y-2);
    drawAccentBar(pdv.x, pdv.y, 60, 3, 'divider'); y -= 24;
  }
  var headline = applyCase(document.getElementById('txHeadline').value || pkg.name);
  if (document.getElementById('elHeadline').checked) {
    var hsize = Math.round(w * hfs/1000 * 0.8);
    var hH = TW('headline', headline, pad, y, w-pad*2, {fontSize:hsize, fontWeight:'bold', color:theme.text}, 1.1, 3);
    y -= hH + 16;
  }
  var tagline = document.getElementById('txTagline').value || pkg.short_description || '';
  if (tagline && document.getElementById('elTagline').checked) {
    TW('tagline', tagline, pad, y-8, w-pad*2, {fontSize:Math.round(w*0.027), color:theme.sub}, 1.6, 2);
  }
  if (document.getElementById('elHighlights').checked) drawHighlightsStrip(pkg, theme, w, h, pad);
  if (document.getElementById('elVigBar').checked) { ctx.fillStyle = S.accent; ctx.fillRect(0,h-5,w,5); }
  if (document.getElementById('elWatermark').checked) {
    ctx.save(); ctx.globalAlpha=0.08;
    T('watermark', 'SZV', w/2, h*0.55, {fontSize:Math.round(w*0.12), fontWeight:'bold', color:theme.text, align:'center'});
    ctx.restore();
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
    var hH = TW('headline', headline, rx, ry, rw, {fontSize:Math.round(w*0.044), fontWeight:'bold', color:theme.text}, 1.15, 3);
    ry += hH + 28;
  }
  var tagline = document.getElementById('txTagline').value || pkg.short_description || '';
  if (tagline && document.getElementById('elTagline').checked) {
    var tH = TW('tagline', tagline, rx, ry, rw, {fontSize:Math.round(w*0.025), color:theme.sub}, 1.65, 3);
    ry += tH + 44;
  }
  if (document.getElementById('elDivider').checked) {
    var pdv2 = pos('divider', rx, ry);
    drawAccentBar(pdv2.x, pdv2.y, rw*0.6, 1, 'divider'); ry += 28;
  }

  var stats = [
    {l:'DURATION', v: (pkg.duration_days-1)+' nights'},
    {l:'FROM',     v: '$'+Number(pkg.price_1pax).toLocaleString()+'/pp'},
    {l:'STYLE',    v: ucw(pkg.accommodation_level)},
  ];
  var pst = pos('stats', rx, ry);
  var sst = styleOf('stats', {fontSize:Math.round(w*0.032), fontWeight:'bold', color:theme.text});
  S.lastStyle.stats = sst;
  var sy = pst.y, statsTop = sy, statsMaxW = 0;
  stats.forEach(function(s) {
    ctx.font='bold '+Math.round(w*0.016)+'px Arial'; ctx.fillStyle=S.accent; ctx.fillText(s.l, pst.x, sy);
    statsMaxW = Math.max(statsMaxW, ctx.measureText(s.l).width); sy+=Math.round(w*0.024);
    applyStyle(sst); ctx.fillText(s.v, pst.x, sy);
    statsMaxW = Math.max(statsMaxW, ctx.measureText(s.v).width); sy+=Math.round(w*0.052);
  });
  hit('stats', pst.x, statsTop - Math.round(w*0.016), statsMaxW, sy - statsTop + 10);
  ry += stats.length * (Math.round(w*0.024)+Math.round(w*0.052));

  if (document.getElementById('elHighlights').checked) {
    ry += 10;
    var phl = pos('highlights', rx, ry);
    var hst = styleOf('highlights', {fontSize:Math.round(w*0.022), fontFamily:'Arial', color:theme.sub});
    S.lastStyle.highlights = hst;
    var hy = phl.y, hlTop = hy;
    ctx.font='bold '+Math.round(w*0.016)+'px Arial'; ctx.fillStyle=S.accent; ctx.fillText('INCLUDES', phl.x, hy); hy+=32;
    var lines = document.getElementById('txHighlights').value.split('\n').map(function(l){return l.trim();}).filter(Boolean);
    lines.slice(0,5).forEach(function(inc){
      applyStyle(hst);
      ctx.fillText('✓  '+inc, phl.x, hy); hy+=Math.round(w*0.033);
    });
    hit('highlights', phl.x, hlTop-Math.round(w*0.016), rw, hy-hlTop+10);
  }
  if (document.getElementById('elCTA').checked) {
    var ctaTxt2 = (document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase();
    T('cta', ctaTxt2, rx, h-56, {fontSize:Math.round(w*0.02), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
  }
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||(pkg.duration_days-1)+'N', w, h);
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
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||(pkg.duration_days-1)+' NIGHTS',w,h);

  var y = h-56;
  if (document.getElementById('elCTA').checked) {
    var ctaTxt3 = (document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase();
    T('cta', ctaTxt3, pad, y, {fontSize:Math.round(w*0.022), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
    y-=50;
  }
  if (document.getElementById('elPrice').checked) {
    var priceTxt3 = 'From $'+Number(pkg.price_1pax).toLocaleString()+'/pp';
    T('price', priceTxt3, pad, y, {fontSize:Math.round(w*0.03), fontWeight:'bold', fontFamily:'Arial', color:theme.text});
    y-=58;
  }
  var headline = applyCase(document.getElementById('txHeadline').value||pkg.name);
  if (document.getElementById('elHeadline').checked) {
    var hH = TW('headline', headline, pad, y, w-pad*2, {fontSize:Math.round(w*0.062), fontWeight:'bold', color:theme.text}, 1.1, 2);
    y -= hH+16;
  }
  if (document.getElementById('elHighlights').checked) drawHighlightsStrip(pkg, theme, w, h, pad);
}

function renderMinimal(pkg, theme, img, w, h) {
  var pad = parseInt(document.getElementById('rPad').value)||64;
  ctx.fillStyle = theme.bg; ctx.fillRect(0,0,w,h);

  // Big ghost number
  ctx.save(); ctx.globalAlpha=0.07;
  T('ghostNum', pkg.duration_days, w-pad+20, Math.round(h*0.5), {fontSize:Math.round(h*0.3), fontWeight:'bold', color:S.accent, align:'right'});
  ctx.restore();

  if (img) {
    var pph = pos('photo', w-pad-280, pad);
    ctx.save(); ctx.beginPath(); roundR(ctx, pph.x, pph.y, 280, 340, 3); ctx.clip();
    drawImg(img, pph.x, pph.y, 280, 340); ctx.restore();
    hit('photo', pph.x, pph.y, 280, 340);
    // Subtle fade edge
    var eg = ctx.createLinearGradient(pph.x,0,pph.x+140,0);
    eg.addColorStop(0,hexRgba(theme.bg,0.8)); eg.addColorStop(1,'transparent');
    ctx.fillStyle=eg; ctx.fillRect(pph.x, pph.y, 140, 340);
  }

  var y = Math.round(h*0.14);
  drawAccentBar(pad, y, 80, 4, 'accentMark'); y+=32;
  T('brand', 'SZV', pad, y, {fontSize:Math.round(w*0.016), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
  y+=60;

  var headline = applyCase(document.getElementById('txHeadline').value||pkg.name);
  if (document.getElementById('elHeadline').checked) {
    var hH = TW('headline', headline, pad, y, w-pad*2-300, {fontSize:Math.round(w*0.07), fontWeight:'bold', color:theme.text}, 1.1, 2);
    y += hH+36;
  }
  var tagline = document.getElementById('txTagline').value||pkg.short_description||'';
  if (tagline && document.getElementById('elTagline').checked) {
    var tH4 = TW('tagline', tagline, pad, y, w-pad*2-100, {fontSize:Math.round(w*0.028), color:theme.sub}, 1.65, 2);
    y+=tH4+50;
  }
  if (document.getElementById('elDivider').checked) {
    var pdv4 = pos('divider', pad, y);
    ctx.fillStyle = fillOf('divider', hexRgba(theme.text,0.12));
    ctx.fillRect(pdv4.x,pdv4.y,w-pad*2,1);
    hit('divider', pdv4.x, pdv4.y, w-pad*2, 1); y+=36;
  }
  var facts = [{l:'Duration',v:(pkg.duration_days-1)+' Nights'},{l:'From',v:'$'+Number(pkg.price_1pax).toLocaleString()+'/pp'},{l:'Accommodation',v:ucw(pkg.accommodation_level)}];
  var cw2=(w-pad*2)/facts.length;
  var pfa = pos('stats', pad, y);
  var sst4 = styleOf('stats', {fontSize:Math.round(w*0.034), fontWeight:'bold', color:theme.text});
  S.lastStyle.stats = sst4;
  facts.forEach(function(f,i){
    var cx2=pfa.x+i*cw2;
    applyStyle(sst4); ctx.fillText(f.v,cx2,pfa.y);
    ctx.font=Math.round(w*0.018)+'px Arial'; ctx.fillStyle=theme.sub; ctx.fillText(f.l,cx2,pfa.y+38);
  });
  hit('stats', pfa.x, pfa.y-Math.round(w*0.034), w-pad*2, 70);
  y+=96;

  if (document.getElementById('elHighlights').checked) {
    var phl2 = pos('highlights', pad, y);
    var hst2 = styleOf('highlights', {fontSize:Math.round(w*0.022), fontFamily:'Arial', color:theme.sub});
    S.lastStyle.highlights = hst2;
    var hy2 = phl2.y, hlTop2 = hy2;
    var lines2 = document.getElementById('txHighlights').value.split('\n').map(function(l){return l.trim();}).filter(Boolean);
    lines2.slice(0,5).forEach(function(inc){
      applyStyle(hst2);
      ctx.fillText('— '+inc, phl2.x, hy2); hy2+=Math.round(w*0.036);
    });
    hit('highlights', phl2.x, hlTop2-Math.round(w*0.022), w-pad*2, hy2-hlTop2+10);
  }
  if (document.getElementById('elCTA').checked) {
    var ctaTxt4 = (document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase();
    T('cta', ctaTxt4, pad, h-60, {fontSize:Math.round(w*0.022), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
  }
  T('brandFooter', 'SZV', w-pad, h-60, {fontSize:Math.round(w*0.016), fontWeight:'bold', fontFamily:'Arial', color:hexRgba(theme.text,0.25), align:'right'});
}

function renderItinerary(pkg, theme, img, w, h) {
  var pad = parseInt(document.getElementById('rPad').value)||64;
  var ov  = parseInt(document.getElementById('rOverlay').value)/100;
  if (img) { clipDraw(img,0,0,w,h); ctx.fillStyle=hexRgba(theme.bg,ov+0.1); ctx.fillRect(0,0,w,h); drawVignette(w,h,parseInt(document.getElementById('rVignette').value)); }
  var y=72;
  if (document.getElementById('elLogo').checked) { drawLogo(theme,w,y,pad); y+=76; }
  var headline = applyCase(document.getElementById('txHeadline').value||pkg.name);
  T('headline', headline, pad, y, {fontSize:Math.round(w*0.052), fontWeight:'bold', color:theme.text});
  y+=58;
  drawAccentBar(pad,y,100,3,'accentMark'); y+=32;
  if (document.getElementById('elPrice').checked) {
    var priceTxt5 = (pkg.duration_days-1)+' NIGHTS  ·  FROM $'+Number(pkg.price_1pax).toLocaleString()+'/PP';
    T('price', priceTxt5, pad, y, {fontSize:Math.round(w*0.026), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
    y+=64;
  }
  var days = pkg.itin_days_arr.length ? pkg.itin_days_arr : pkg.itin_arr||[];
  var maxD = Math.min(days.length, S.size==='story'?7:5);
  var lh2 = Math.round((h-y-80) / Math.max(maxD,1));
  var pdy = pos('days', pad, y);
  var dst = styleOf('days', {fontSize:Math.round(w*0.027), fontWeight:'bold', fontFamily:'Arial', color:theme.text});
  S.lastStyle.days = dst;
  var daysDx = pdy.x - pad, daysTop = pdy.y;
  days.slice(0,maxD).forEach(function(day,i){
    var dy = daysTop + i*lh2 + lh2*0.5;
    var dx = pad + daysDx;
    ctx.fillStyle = i===0 ? S.accent : hexRgba(S.accent, 0.35);
    ctx.beginPath(); ctx.arc(dx+20, dy, 22, 0, Math.PI*2); ctx.fill();
    ctx.font='bold '+Math.round(w*0.022)+'px Arial'; ctx.fillStyle=theme.bg; ctx.textAlign='center';
    ctx.fillText(String(day.day||i+1), dx+20, dy+8); ctx.textAlign='left';
    if (i<maxD-1) { ctx.fillStyle=hexRgba(S.accent,0.2); ctx.fillRect(dx+18,dy+24,4,lh2-40); }
    applyStyle(dst); ctx.fillText(String(day.title||'Day '+(i+1)).substr(0,40), dx+58, dy);
    if (day.location||day.description) {
      var sub=(day.location||day.description||'').substr(0,48);
      ctx.font=Math.round(w*0.02)+'px Arial'; ctx.fillStyle=theme.sub; ctx.fillText(sub, dx+58, dy+26);
    }
  });
  hit('days', pad+daysDx, daysTop, w-pad*2, lh2*maxD);
  if (document.getElementById('elCTA').checked) {
    var ctaTxt5 = (document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase();
    T('cta', ctaTxt5, pad, h-52, {fontSize:Math.round(w*0.022), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
  }
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||(pkg.duration_days-1)+' NIGHTS',w,h);
}

function renderPrice(pkg, theme, img, w, h) {
  var pad = parseInt(document.getElementById('rPad').value)||64;
  var ov  = parseInt(document.getElementById('rOverlay').value)/100;
  if (img) { clipDraw(img,0,0,w,h); ctx.fillStyle=hexRgba(theme.bg,ov); ctx.fillRect(0,0,w,h); }
  if (document.getElementById('elLogo').checked) drawLogo(theme,w,56,pad);
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||(pkg.duration_days-1)+' NIGHTS',w,h);
  // The whole card (headline, tagline, tiers, CTA) is one unit here —
  // they're drawn relative to each other inside a visibly framed panel,
  // so dragging the card moves all of them together rather than letting
  // each wander independently out of its frame.
  var cw3=Math.round(w*0.76), ch=Math.round(h*0.52);
  var pcard = pos('card', (w-cw3)/2, (h-ch)/2);
  var cx3=pcard.x, cy3=pcard.y;
  var cardCx = cx3 + cw3/2; // card's own horizontal centre — centred text inside it must track the card, not the canvas, once dragged
  hit('card', cx3, cy3, cw3, ch);
  ctx.fillStyle = fillOf('card', hexRgba(theme.bg,0.9)); ctx.fillRect(cx3,cy3,cw3,ch);
  drawAccentBar(cx3,cy3,cw3,4,'cardBar');
  var iy=cy3+52;
  var headline=applyCase(document.getElementById('txHeadline').value||pkg.name);
  T('headline', headline, cardCx, iy, {fontSize:Math.round(w*0.04), fontWeight:'bold', color:theme.text, align:'center'});
  iy+=48;
  var tag=document.getElementById('txTagline').value||pkg.short_description||'';
  if (tag) { T('tagline', tag.substr(0,55), cardCx, iy, {fontSize:Math.round(w*0.022), fontFamily:'Arial', color:theme.sub, align:'center'}); iy+=44; }
  ctx.fillStyle=hexRgba(S.accent,0.25); ctx.fillRect(cx3+50,iy,cw3-100,1); iy+=40;
  var tiers=[{l:'DURATION',v:(pkg.duration_days-1)+' Nights'},{l:'FROM',v:'$'+Number(pkg.price_1pax).toLocaleString()},{l:'STYLE',v:ucw(pkg.accommodation_level)}];
  var tw2=(cw3-60)/3;
  ctx.textAlign='center';
  var tst = styleOf('stats', {fontSize:Math.round(w*0.042), fontWeight:'bold', color:theme.text});
  S.lastStyle.stats = tst;
  tiers.forEach(function(t,i){
    var tx2=cx3+30+i*tw2+tw2/2;
    ctx.font='bold '+Math.round(w*0.016)+'px Arial'; ctx.fillStyle=S.accent; ctx.fillText(t.l,tx2,iy);
    applyStyle(tst); ctx.textAlign='center'; ctx.fillText(t.v,tx2,iy+50);
    ctx.font=Math.round(w*0.016)+'px Arial'; ctx.fillStyle=theme.sub; ctx.fillText(i===1?'per person':'',tx2,iy+76);
  }); iy+=118;
  if (document.getElementById('elHighlights').checked) {
    var lines3=document.getElementById('txHighlights').value.split('\n').map(function(l){return l.trim();}).filter(Boolean);
    T('highlights', lines3.slice(0,3).join('  ·  '), cardCx, iy, {fontSize:Math.round(w*0.018), fontFamily:'Arial', color:theme.sub, align:'center'});
  }
  ctx.textAlign='left';
  if (document.getElementById('elCTA').checked) {
    T('cta', (document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase(), cardCx, cy3+ch+52, {fontSize:Math.round(w*0.024), fontWeight:'bold', fontFamily:'Arial', color:S.accent, align:'center'});
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
  TW('headline', headline, w/2, h*0.35, w-pad*2, {fontSize:Math.round(w*0.09), fontWeight:'bold', color:theme.text, align:'center'}, 1.1, 3);

  // Accent bar centre
  drawAccentBar(w/2-50,Math.round(h*0.42),100,3,'accentMark');

  // Tagline
  var tag=document.getElementById('txTagline').value||pkg.short_description||'';
  if (tag) {
    T('tagline', tag.substr(0,40), w/2, Math.round(h*0.47), {fontSize:Math.round(w*0.032), fontFamily:'Arial', color:theme.sub, align:'center'});
  }

  // Price big
  if (document.getElementById('elPrice').checked) {
    var priceTxt6 = '$'+Number(pkg.price_1pax).toLocaleString();
    var sub6 = 'per person · '+(pkg.duration_days-1)+' nights';
    var pfs6 = T('price', priceTxt6, w/2, Math.round(h*0.56), {fontSize:Math.round(w*0.052), fontWeight:'bold', color:S.accent, align:'center'});
    var pp6sub = pos('price', w/2, Math.round(h*0.56));
    ctx.font=Math.round(w*0.024)+'px Arial'; ctx.fillStyle=theme.sub; ctx.textAlign='center';
    ctx.fillText(sub6, pp6sub.x, pp6sub.y+Math.round(h*0.04));
    ctx.textAlign='left';
  }

  // Highlights as pills in centre
  if (document.getElementById('elHighlights').checked) {
    var lines4=document.getElementById('txHighlights').value.split('\n').map(function(l){return l.trim();}).filter(Boolean).slice(0,4);
    var phl3 = pos('highlights', w/2, Math.round(h*0.68));
    var hst3 = styleOf('highlights', {fontSize:Math.round(w*0.025), fontWeight:'bold', fontFamily:'Arial', color:theme.text});
    S.lastStyle.highlights = hst3;
    var py=phl3.y, pyTop=py;
    lines4.forEach(function(inc){
      ctx.font = (hst3.fontWeight==='bold'?'bold ':'') + hst3.fontSize + 'px ' + hst3.fontFamily;
      var tw3=ctx.measureText('✓ '+inc).width + 48;
      ctx.fillStyle=hexRgba(S.accent,0.15); ctx.fillRect(phl3.x-tw3/2, py-22, tw3, 34);
      ctx.fillStyle=hexRgba(S.accent,0.5); ctx.fillRect(phl3.x-tw3/2, py-22, 3, 34);
      applyStyle(hst3);
      ctx.fillText('✓ '+inc, phl3.x-tw3/2+14, py); py+=48;
    });
    hit('highlights', phl3.x-Math.round(w*0.4), pyTop-30, Math.round(w*0.8), py-pyTop+20);
  }

  // Bottom CTA
  if (document.getElementById('elCTA').checked) {
    var cta2=(document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase();
    T('cta', cta2, w/2, h-70, {fontSize:Math.round(w*0.026), fontWeight:'bold', fontFamily:'Arial', color:S.accent, align:'center'});
  }
  ctx.textAlign='left';
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||(pkg.duration_days-1)+'N',w,h);
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
  drawAccentBar(0,imgH,w,4,'accentMark');

  if (document.getElementById('elLogo').checked) drawLogo(theme,w,48,pad);
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||(pkg.duration_days-1)+' NIGHTS',w,h);

  var y2=imgH+60;
  var headline=applyCase(document.getElementById('txHeadline').value||pkg.name);
  if (document.getElementById('elHeadline').checked) {
    var hH7 = TW('headline', headline, pad, y2, w-pad*2, {fontSize:Math.round(w*0.062), fontWeight:'bold', color:theme.text}, 1.1, 2);
    y2+=hH7+28;
  }
  var tag=document.getElementById('txTagline').value||pkg.short_description||'';
  if (tag) {
    var tH7 = TW('tagline', tag, pad, y2, w-pad*2, {fontSize:Math.round(w*0.026), color:theme.sub}, 1.6, 2);
    y2+=tH7+32;
  }
  if (document.getElementById('elDivider').checked) {
    var pdv7 = pos('divider', pad, y2);
    ctx.fillStyle = fillOf('divider', hexRgba(S.accent,0.3));
    ctx.fillRect(pdv7.x,pdv7.y,w-pad*2,1);
    hit('divider', pdv7.x, pdv7.y, w-pad*2, 1); y2+=28;
  }
  var facts2=[(pkg.duration_days-1)+' Nights', '$'+Number(pkg.price_1pax).toLocaleString()+'/pp', ucw(pkg.accommodation_level)];
  var fw=(w-pad*2)/facts2.length;
  var pfa2 = pos('stats', pad, y2);
  var sst7 = styleOf('stats', {fontSize:Math.round(w*0.03), fontWeight:'bold', color:theme.text});
  S.lastStyle.stats = sst7;
  facts2.forEach(function(f,i){
    applyStyle(sst7); ctx.fillText(f, pfa2.x+i*fw, pfa2.y+28);
    ctx.font=Math.round(w*0.016)+'px Arial'; ctx.fillStyle=theme.sub; ctx.fillText(['Duration','From','Accommodation'][i], pfa2.x+i*fw, pfa2.y+52);
  });
  hit('stats', pfa2.x, pfa2.y, w-pad*2, 60);
  y2+=80;
  if (document.getElementById('elCTA').checked) {
    var ctaTxt7 = (document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase();
    T('cta', ctaTxt7, pad, h-52, {fontSize:Math.round(w*0.02), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
  }
}

function renderDarkFull(pkg, theme, img, w, h) {
  // Pure dark, no image. Typography only.
  var pad = parseInt(document.getElementById('rPad').value)||64;
  ctx.fillStyle=theme.bg; ctx.fillRect(0,0,w,h);
  drawAccentBar(pad,60,4,h-120,'edgeBar');
  var y3=80;
  T('brand', 'SZV', pad+24, y3, {fontSize:Math.round(w*0.016), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
  y3+=60;
  var headline=applyCase(document.getElementById('txHeadline').value||pkg.name);
  var hst8 = styleOf('headline', {fontSize:Math.round(w*0.075), fontWeight:'bold', color:theme.text});
  S.lastStyle.headline = hst8;
  applyStyle(hst8);
  var ph8 = pos('headline', pad+24, y3);
  var hx8 = ph8.x, hyStart8 = ph8.y, hLineCount = 0;
  var words5=headline.split(' '); var line5='';
  words5.forEach(function(word){ var t=line5+(line5?' ':'')+word; if(ctx.measureText(t).width>w-pad-24-40 && line5){ctx.fillText(line5,hx8,ph8.y);ph8.y+=Math.round(w*0.085);hLineCount++;line5=word;}else line5=t; });
  if(line5){ctx.fillText(line5,hx8,ph8.y);ph8.y+=Math.round(w*0.085);hLineCount++;}
  hit('headline', hx8, hyStart8-hst8.fontSize, Math.max(w-pad-24-40,200), hLineCount*Math.round(w*0.085));
  y3 += hLineCount*Math.round(w*0.085);
  y3+=20; drawAccentBar(pad+24,y3,100,3,'accentMark'); y3+=40;
  var tag2=document.getElementById('txTagline').value||pkg.short_description||'';
  if (tag2) {
    var tH8 = TW('tagline', tag2, pad+24, y3, w-pad-48, {fontSize:Math.round(w*0.03), fontFamily:'Arial', color:theme.sub}, 1.65, 2);
    y3+=100;
  }
  // Animated feel — staggered includes
  var lines5=document.getElementById('txHighlights').value.split('\n').map(function(l){return l.trim();}).filter(Boolean);
  var phl4 = pos('highlights', pad+24, y3);
  var hst4b = styleOf('highlights', {fontSize:Math.round(w*0.025), fontFamily:'Arial', color:theme.sub});
  S.lastStyle.highlights = hst4b;
  var y3b = phl4.y, hlTop4 = y3b;
  lines5.slice(0,5).forEach(function(inc,i){
    var x5=phl4.x+(i%2===0?0:30);
    ctx.fillStyle=hexRgba(S.accent,0.5+i*0.08);
    ctx.fillRect(x5,y3b-20,2,28);
    applyStyle(hst4b);
    ctx.fillText(inc, x5+16, y3b); y3b+=Math.round(w*0.04);
  });
  hit('highlights', phl4.x, hlTop4-24, w-pad-24-40, y3b-hlTop4+20);
  y3 += (y3b - phl4.y) + 20;
  if (document.getElementById('elPrice').checked) {
    var priceTxt8 = 'From $'+Number(pkg.price_1pax).toLocaleString();
    var pfs8 = T('price', priceTxt8, pad+24, h-100, {fontSize:Math.round(w*0.05), fontWeight:'bold', color:S.accent});
    var pp8sub = pos('price', pad+24, h-100);
    ctx.font=Math.round(w*0.02)+'px Arial'; ctx.fillStyle=theme.sub;
    ctx.fillText('USD per person', pp8sub.x, pp8sub.y+30);
  }
  if (document.getElementById('elCTA').checked) {
    var ctaTxt8 = (document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase();
    T('cta', ctaTxt8, w-pad, h-70, {fontSize:Math.round(w*0.018), fontWeight:'bold', fontFamily:'Arial', color:hexRgba(theme.text,0.35), align:'right'});
  }
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||(pkg.duration_days-1)+' NIGHTS',w,h);
}

function renderInclusions(pkg, theme, img, w, h) {
  // What's in / what's not — the one question every guest asks before
  // booking, answered in one glance: two columns, no image competing
  // for attention.
  var pad = parseInt(document.getElementById('rPad').value)||64;
  ctx.fillStyle = theme.bg; ctx.fillRect(0,0,w,h);

  if (document.getElementById('elLogo').checked) drawLogo(theme,w,56,pad);
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||(pkg.duration_days-1)+' NIGHTS', w, h);

  var y = 150;
  var headline = applyCase(document.getElementById('txHeadline').value||pkg.name);
  if (document.getElementById('elHeadline').checked) {
    var hH = TW('headline', headline, pad, y, w-pad*2, {fontSize:Math.round(w*0.052), fontWeight:'bold', color:theme.text}, 1.1, 2);
    y += hH + 16;
  }
  var tagline = document.getElementById('txTagline').value || pkg.short_description || '';
  if (tagline && document.getElementById('elTagline').checked) {
    var tH = TW('tagline', tagline, pad, y, w-pad*2, {fontSize:Math.round(w*0.024), color:theme.sub}, 1.5, 2);
    y += tH + 20;
  }
  if (document.getElementById('elDivider').checked) {
    var pdvI = pos('divider', pad, y);
    drawAccentBar(pdvI.x, pdvI.y, 80, 3, 'divider'); y += 20;
  }
  y += 28;

  var colGap = Math.round(w*0.045);
  var colW = (w - pad*2 - colGap) / 2;
  var leftX = pad, rightX = pad + colW + colGap;
  var incSrc = (pkg.itin_inc_arr && pkg.itin_inc_arr.length) ? pkg.itin_inc_arr : (pkg.includes_arr || []);
  var incItems = incSrc.slice(0, 8);
  var excItems = (pkg.excludes_arr || []).slice(0, 8);
  var rowH = Math.round(w*0.036);
  var itemFs = Math.round(w*0.021);

  var pInc = pos('includesList', leftX, y);
  var incSt = styleOf('includesList', {fontSize:itemFs, fontFamily:'Arial', color:theme.text});
  S.lastStyle.includesList = incSt;
  ctx.font = 'bold ' + Math.round(w*0.019) + 'px Arial'; ctx.fillStyle = '#7ecba1';
  ctx.fillText('✓  INCLUDED', pInc.x, pInc.y);
  var iy = pInc.y + rowH;
  incItems.forEach(function(item){
    var short = item.length > 42 ? item.substr(0,40)+'…' : item;
    ctx.font = itemFs+'px Arial'; ctx.fillStyle = '#7ecba1'; ctx.fillText('✓', pInc.x, iy);
    applyStyle(incSt); ctx.fillText(short, pInc.x+30, iy);
    iy += rowH;
  });
  hit('includesList', pInc.x, pInc.y - Math.round(w*0.019), colW, iy - pInc.y + 16);

  var pExc = pos('excludesList', rightX, y);
  var excSt = styleOf('excludesList', {fontSize:itemFs, fontFamily:'Arial', color:theme.sub});
  S.lastStyle.excludesList = excSt;
  ctx.font = 'bold ' + Math.round(w*0.019) + 'px Arial'; ctx.fillStyle = '#e87a7a';
  ctx.fillText('✕  NOT INCLUDED', pExc.x, pExc.y);
  var ey = pExc.y + rowH;
  excItems.forEach(function(item){
    var short2 = item.length > 42 ? item.substr(0,40)+'…' : item;
    ctx.font = itemFs+'px Arial'; ctx.fillStyle = '#e87a7a'; ctx.fillText('✕', pExc.x, ey);
    applyStyle(excSt); ctx.fillText(short2, pExc.x+30, ey);
    ey += rowH;
  });
  hit('excludesList', pExc.x, pExc.y - Math.round(w*0.019), colW, ey - pExc.y + 16);

  if (document.getElementById('elPrice').checked) {
    var priceTxt = 'From $'+Number(pkg.price_1pax).toLocaleString()+' per person';
    T('price', priceTxt, pad, h-110, {fontSize:Math.round(w*0.028), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
  }
  if (document.getElementById('elCTA').checked) {
    var ctaTxt = (document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase();
    T('cta', ctaTxt, pad, h-56, {fontSize:Math.round(w*0.02), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
  }
}

function renderGallery(pkg, theme, img, w, h) {
  // A clean photo-grid summary with a caption strip — the carousel-cover
  // shot, distinct from Mosaic's single hero-with-inset arrangement.
  var pad = parseInt(document.getElementById('rPad').value)||64;
  var imgs = (pkg.gallery||[]).map(function(s){return S.loadedImgs[s];}).filter(Boolean);
  var gap = 6;
  var capH = Math.round(h*0.22);
  var gridH = h - capH;

  if (imgs.length >= 4) {
    var gw = Math.round((w-gap)/2), gh2 = Math.round((gridH-gap)/2);
    clipDraw(imgs[0],0,0,gw,gh2);
    clipDraw(imgs[1],gw+gap,0,w-gw-gap,gh2);
    clipDraw(imgs[2],0,gh2+gap,gw,gridH-gh2-gap);
    clipDraw(imgs[3],gw+gap,gh2+gap,w-gw-gap,gridH-gh2-gap);
  } else if (imgs.length === 3) {
    var lw2 = Math.round(w*0.5);
    clipDraw(imgs[0],0,0,lw2-gap/2,gridH);
    clipDraw(imgs[1],lw2+gap/2,0,w-lw2-gap/2,Math.round(gridH/2)-gap/2);
    clipDraw(imgs[2],lw2+gap/2,Math.round(gridH/2)+gap/2,w-lw2-gap/2,Math.round(gridH/2)-gap/2);
  } else if (imgs.length === 2) {
    clipDraw(imgs[0],0,0,Math.round(w/2)-gap/2,gridH);
    clipDraw(imgs[1],Math.round(w/2)+gap/2,0,Math.round(w/2)-gap/2,gridH);
  } else if (img) {
    clipDraw(img,0,0,w,gridH);
  } else {
    ctx.fillStyle = theme.bg2; ctx.fillRect(0,0,w,gridH);
  }

  ctx.fillStyle = theme.bg; ctx.fillRect(0, gridH, w, capH);
  if (document.getElementById('elDivider').checked) drawAccentBar(0, gridH, w, 3, 'divider');

  var capPad = Math.round(w*0.045);
  var cy = gridH + Math.round(capH*0.4);
  var headline = applyCase(document.getElementById('txHeadline').value||pkg.name);
  if (document.getElementById('elHeadline').checked) {
    T('headline', headline, capPad, cy, {fontSize:Math.round(w*0.042), fontWeight:'bold', color:theme.text});
  }
  if (document.getElementById('elPrice').checked) {
    var priceTxt2 = 'From $'+Number(pkg.price_1pax).toLocaleString();
    T('price', priceTxt2, capPad, cy+Math.round(capH*0.36), {fontSize:Math.round(w*0.026), fontWeight:'bold', fontFamily:'Arial', color:S.accent});
  }
  if (document.getElementById('elCTA').checked) {
    var ctaTxt2 = (document.getElementById('txCTA').value||'safarizanguven.com').toUpperCase();
    T('cta', ctaTxt2, w-capPad, gridH+Math.round(capH*0.68), {fontSize:Math.round(w*0.02), fontWeight:'bold', fontFamily:'Arial', color:S.accent, align:'right'});
  }
  if (document.getElementById('elLogo').checked) drawLogo(theme, w, 48, pad);
  if (document.getElementById('elBadge').checked) drawBadge(document.getElementById('txBadge').value||(pkg.duration_days-1)+' NIGHTS', w, h);
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

/* ── Download & Export ────────────────────────────────────────
   The selection outline (blue dashed box) is only ever meant for the
   editor, never the exported file — so every export re-renders with
   S.selected temporarily cleared, then restores it and re-renders again
   for the on-screen view once the export has read the clean frame. */
function withoutSelection(fn) {
  var had = S.selected;
  if (had) { S.selected = null; render(); }
  fn();
  if (had) { S.selected = had; render(); }
}
function downloadPNG() {
  var pkg=PKGS[S.pkg];
  withoutSelection(function(){
    var a=document.createElement('a');
    a.download='szv-'+pkg.slug+'-'+S.tpl+'-'+S.size+'.png';
    a.href=canvas.toDataURL('image/png'); a.click(); toast('Downloaded PNG');
  });
}
function downloadJPG() {
  var pkg=PKGS[S.pkg];
  withoutSelection(function(){
    var a=document.createElement('a');
    a.download='szv-'+pkg.slug+'-'+S.tpl+'-'+S.size+'.jpg';
    a.href=canvas.toDataURL('image/jpeg',0.92); a.click(); toast('Downloaded JPG');
  });
}
function copyToClipboard() {
  withoutSelection(function(){
    canvas.toBlob(function(blob){
      try {
        navigator.clipboard.write([new ClipboardItem({'image/png':blob})]).then(function(){ toast('Copied to clipboard'); }).catch(function(){ toast('Copy not supported — use Download'); });
      } catch(e){ toast('Copy not supported — use Download'); }
    });
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
  [{l:'Duration',v:(pkg.duration_days-1)+' nights'},{l:'Price',v:'$'+Number(pkg.price_1pax).toLocaleString()+'/pp'}].forEach(function(s){
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

</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
