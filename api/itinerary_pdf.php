<?php
// SZV_ITINERARY_PDF_v1 — marker for fix_quotation_email.py, don't remove.
require_once __DIR__ . '/../app/bootstrap.php';
if (empty($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(403);
    die('Access denied.');
}

/**
 * Itinerary PDF — printable day-by-day itinerary document.
 * Uses the same browser-print approach as api/quotation_pdf.php — this
 * project has no server-side PDF library (checked composer.json), so
 * "PDF" here means a printable HTML page saved via the browser's own
 * Print > Save as PDF, same as the quotation document.
 */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) die('Invalid itinerary ID.');

$db = \App\Models\Database::getInstance();
try {
    $s = $db->prepare("SELECT * FROM itineraries WHERE id=?");
    $s->execute([$id]);
    $itin = $s->fetch();
    if (!$itin) die('Itinerary not found.');
} catch (Exception $e) { die('DB error: ' . $e->getMessage()); }

$days     = json_decode($itin['days']     ?? '[]', true) ?: [];
$includes = json_decode($itin['includes'] ?? '[]', true) ?: [];
$excludes = json_decode($itin['excludes'] ?? '[]', true) ?: [];

$logoUrl = (defined('APP_URL') ? APP_URL : 'https://www.safarizanguven.com') . '/assets/images/logo.jpeg';
$accLevelLabels = ['budget' => 'Budget-Friendly', 'midrange' => 'Mid-Range', 'highend' => 'High-End / Luxury', 'ultra_luxury' => 'Ultra Luxury'];
$accLevelStr = $accLevelLabels[$itin['accommodation_level'] ?? 'midrange'] ?? 'Mid-Range';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($itin['name']) ?> — Safari Zangu Ventures</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Jost:wght@300;400;500;600;700&display=swap');
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  /* 'DejaVu Sans' is dompdf's own bundled font, not a web font — kept as a
     fallback because dompdf can't load the Jost @import (stripped by
     App\Services\PdfRenderer to avoid a font-cache write failure), and its
     default fallback silently drops glyphs like the arrow/star below to
     "?" instead of erroring. A real browser still uses Jost; dompdf gets
     full Unicode coverage from DejaVu Sans instead of a broken glyph. */
  body{font-family:'Jost','DejaVu Sans',Arial,sans-serif;font-size:13px;color:#0a0a0a;background:#fff;line-height:1.5}
  .page{max-width:820px;margin:0 auto;padding:2.5rem}

  .hdr{display:grid;grid-template-columns:1fr auto;gap:2rem;align-items:start;
        padding-bottom:1.5rem;border-bottom:2px solid #0a0a0a;margin-bottom:2rem}
  .logo-img{width:64px;height:64px;border-radius:50%;object-fit:cover;margin-bottom:.4rem;display:block}
  .logo-sub{font-size:.58rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#9ca3af;margin-top:.3rem}
  .qref{text-align:right}
  .qref-num{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:500}
  .qref-date{font-size:.72rem;color:#9ca3af;margin-top:.2rem}
  .badge{display:inline-block;padding:.2rem .65rem;font-size:.58rem;font-weight:700;
         letter-spacing:.14em;text-transform:uppercase;background:#0a0a0a;color:#fff;margin-top:.4rem}

  .sec{margin-bottom:1.75rem}
  .sec-title{font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;
             color:#9ca3af;border-bottom:1px solid #e5e7eb;padding-bottom:.4rem;margin-bottom:.85rem}

  .four-col{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem}
  .field label{display:block;font-size:.56rem;font-weight:700;letter-spacing:.15em;
               text-transform:uppercase;color:#9ca3af;margin-bottom:.2rem}
  .field span{font-size:.88rem;font-weight:500}

  .overview{font-size:.88rem;line-height:1.8;color:#374151}

  <?= \App\Helpers\ItineraryDayCards::css() ?>

  .two-col-list{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem}
  .list-title{font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
              margin-bottom:.5rem}
  .list-title.inc{color:#166534}
  .list-title.exc{color:#991b1b}
  .list-items{list-style:none;font-size:.82rem;line-height:1.9;color:#374151}
  .list-items li::before{content:'\2713 ';color:#16a34a;font-weight:700;margin-right:.2rem}
  .list-items.exc li::before{content:'\2717 ';color:#dc2626}

  .footer{margin-top:2.5rem;padding-top:1.25rem;border-top:2px solid #0a0a0a;
          display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;font-size:.72rem;color:#6b7280}
  .footer strong{display:block;color:#0a0a0a;font-weight:600;margin-bottom:.15rem}

  @media print {
    body{background:#fff}
    .no-print{display:none}
    .page{padding:1.5rem}
  }
</style>
</head>
<body>
<div class="page">

<!-- ── HEADER ─────────────────────────────────────────────── -->
<div class="hdr">
  <div>
    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Safari Zangu Ventures" class="logo-img">
    <div class="logo-sub">Private Tanzania Safari Specialists · Arusha</div>
    <div style="margin-top:.5rem;font-size:.72rem;color:#9ca3af">TALA Licence No. 037129 · Reg. No. 179228971</div>
  </div>
  <div class="qref">
    <div style="font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af">ITINERARY</div>
    <div class="qref-num"><?= htmlspecialchars($itin['name']) ?></div>
    <div class="qref-date"><?= (int)$itin['duration_days'] ?> Days</div>
    <div class="badge"><?= htmlspecialchars($accLevelStr) ?></div>
  </div>
</div>

<!-- ── OVERVIEW ───────────────────────────────────────────── -->
<div class="sec">
  <div class="sec-title">Overview</div>
  <div class="four-col" style="margin-bottom:1rem">
    <div class="field"><label>Duration</label><span><?= (int)$itin['duration_days'] ?> days</span></div>
    <div class="field"><label>Accommodation</label><span><?= htmlspecialchars($accLevelStr) ?></span></div>
    <div class="field"><label>Est. Price</label><span>USD <?= number_format((float)($itin['base_price_pax'] ?? 0), 0) ?>/pp</span></div>
  </div>
  <?php if (!empty($itin['overview'])): ?>
  <div class="overview"><?= nl2br(htmlspecialchars($itin['overview'])) ?></div>
  <?php endif; ?>
</div>

<!-- ── DAY BY DAY ─────────────────────────────────────────── -->
<div class="sec">
  <div class="sec-title">Day-by-Day Itinerary</div>
  <?= \App\Helpers\ItineraryDayCards::render($days) ?>
</div>

<!-- ── INCLUDES / EXCLUDES ────────────────────────────────── -->
<?php if (!empty($includes) || !empty($excludes)): ?>
<div class="sec">
  <div class="two-col-list">
    <?php if (!empty($includes)): ?>
    <div>
      <div class="list-title inc">What's Included</div>
      <ul class="list-items">
        <?php foreach ($includes as $inc): ?>
        <li><?= htmlspecialchars($inc) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if (!empty($excludes)): ?>
    <div>
      <div class="list-title exc">What's Not Included</div>
      <ul class="list-items exc">
        <?php foreach ($excludes as $exc): ?>
        <li><?= htmlspecialchars($exc) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── FOOTER ─────────────────────────────────────────────── -->
<div class="footer">
  <div>
    <strong>Safari Zangu Ventures</strong>
    Njiro Road, Arusha, Tanzania<br>
    TALA Licence No. 037129<br>
    Reg. No. 179228971
  </div>
  <div>
    <strong>Contact</strong>
    +255 663 430 934<br>
    info@safarizanguven.com<br>
    safarizanguven.com
  </div>
  <div>
    <strong>Note</strong>
    This is an indicative itinerary.<br>
    Final costs are confirmed in your<br>
    personalised quotation.
  </div>
</div>

<!-- Print button -->
<div class="no-print" style="margin-top:1.5rem;text-align:center">
  <button onclick="window.print()"
          style="padding:.65rem 2rem;background:#0a0a0a;color:#fff;border:none;
                 font-family:inherit;font-size:.78rem;font-weight:700;letter-spacing:.1em;
                 text-transform:uppercase;cursor:pointer;margin-right:.5rem">
    🖨 Print / Save PDF
  </button>
  <a href="javascript:history.back()"
     style="padding:.65rem 1.5rem;border:1.5px solid #e5e7eb;font-size:.78rem;
            font-weight:700;letter-spacing:.1em;text-transform:uppercase;
            text-decoration:none;color:#0a0a0a">
    ← Back
  </a>
</div>

</div><!-- /page -->
</body></html>
