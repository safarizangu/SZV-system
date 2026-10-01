<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;

/**
 * Quotation Itinerary PDF — the day-by-day companion to api/quotation_pdf.php.
 *
 * Split out from quotation_pdf.php so the client receives two focused
 * documents (Quotation + Itinerary) instead of one long combined PDF.
 *
 * Auth is skipped only when included internally by trusted server-side
 * code with SZV_PDF_INTERNAL_RENDER already defined — never settable via
 * request input. Every real HTTP request still requires an admin session.
 */
if (!defined('SZV_PDF_INTERNAL_RENDER')) {
    AuthMiddleware::requireAdmin();
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) die('Invalid quotation ID.');

$db = \App\Models\Database::getInstance();
try {
    $s = $db->prepare("SELECT * FROM quotations WHERE id=?");
    $s->execute([$id]);
    $q = $s->fetch();
    if (!$q) die('Quotation not found.');
} catch(Exception $e) { die('DB error: '.$e->getMessage()); }

// Same priority quotation_pdf.php always used for pricing: the
// quotation's own itinerary/includes/excludes snapshot columns win when
// present, because admin/quotation_new.php's Day-by-Day editor stays
// editable even when itinerary_id is set — pricing (and now this
// document) is always built from exactly what's in those columns, never
// re-fetched live from itineraries.days. itinerary_id is only a fallback
// for older quotations saved before this snapshot existed; package_name
// is a last-resort legacy fallback for quotations older still.
$itinDays     = [];
$itinOverview = '';
$itinIncludes = [];
$itinExcludes = [];
try {
    $itinRow = null;
    if (!empty($q['itinerary'])) {
        $itinDays     = json_decode($q['itinerary'], true) ?: [];
        $itinIncludes = json_decode($q['includes']  ?? '[]', true) ?: [];
        $itinExcludes = json_decode($q['excludes']  ?? '[]', true) ?: [];
    } elseif (!empty($q['itinerary_id'])) {
        $is = $db->prepare("SELECT * FROM itineraries WHERE id=? LIMIT 1");
        $is->execute([$q['itinerary_id']]);
        $itinRow = $is->fetch() ?: null;
    }
    if ($itinRow) {
        $itinDays     = json_decode($itinRow['days']     ?? '[]', true) ?: [];
        $itinOverview = $itinRow['overview'] ?? '';
        $itinIncludes = json_decode($itinRow['includes'] ?? '[]', true) ?: [];
        $itinExcludes = json_decode($itinRow['excludes'] ?? '[]', true) ?: [];
    } elseif (empty($itinDays) && !empty($q['package_name'])) {
        $ps = $db->prepare("SELECT i.* FROM packages p
                            JOIN itineraries i ON i.id = p.itinerary_id
                            WHERE p.name = ? AND p.is_active = 1 LIMIT 1");
        $ps->execute([$q['package_name']]);
        $itinRow = $ps->fetch() ?: null;
        if ($itinRow) {
            $itinDays     = json_decode($itinRow['days']     ?? '[]', true) ?: [];
            $itinOverview = $itinRow['overview'] ?? '';
            $itinIncludes = json_decode($itinRow['includes'] ?? '[]', true) ?: [];
            $itinExcludes = json_decode($itinRow['excludes'] ?? '[]', true) ?: [];
        }
    }
} catch(Exception $e) { $itinDays = []; $itinOverview = ''; $itinIncludes = []; $itinExcludes = []; }

$destinations = json_decode($q['destinations'] ?? '[]', true) ?: [];
$destStr      = !empty($destinations) ? implode(' → ', $destinations) : ($q['package_name'] ?? 'Tanzania Safari');
$logoUrl      = (defined('APP_URL') ? APP_URL : 'https://www.safarizanguven.com') . '/assets/images/logo.jpeg';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Itinerary — Quotation <?= htmlspecialchars($q['quote_ref']) ?> — Safari Zangu Ventures</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Jost:wght@300;400;500;600;700&display=swap');
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  /* 'DejaVu Sans' is dompdf's own bundled font, kept as a fallback because
     dompdf can't load the Jost @import above (stripped by
     App\Services\PdfRenderer to avoid a font-cache write failure) — without
     it, glyphs like → and ✦ silently render as "?" instead of erroring. */
  body{font-family:'Jost','DejaVu Sans',Arial,sans-serif;font-size:13px;color:#0a0a0a;background:#fff;line-height:1.5}
  .page{max-width:820px;margin:0 auto;padding:2.5rem}

  .hdr{display:grid;grid-template-columns:1fr auto;gap:2rem;align-items:start;
        padding-bottom:1.5rem;border-bottom:2px solid #0a0a0a;margin-bottom:2rem}
  .logo-img{width:64px;height:64px;border-radius:50%;object-fit:cover;margin-bottom:.4rem;display:block}
  .logo-sub{font-size:.58rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#9ca3af;margin-top:.3rem}
  .qref{text-align:right}
  .qref-num{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:500}
  .qref-date{font-size:.72rem;color:#9ca3af;margin-top:.2rem}

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
    <div class="qref-num"><?= htmlspecialchars($q['quote_ref']) ?></div>
    <div class="qref-date">For Quotation <?= htmlspecialchars($q['quote_ref']) ?></div>
  </div>
</div>

<!-- ── SAFARI SUMMARY ─────────────────────────────────────── -->
<div class="sec">
  <div class="sec-title">Safari Summary</div>
  <div class="four-col">
    <div class="field"><label>Destination</label><span><?= htmlspecialchars($destStr) ?></span></div>
    <div class="field"><label>Duration</label><span><?= (int)$q['duration_days'] ?> nights</span></div>
    <div class="field"><label>Travelers</label>
      <span><?= (int)($q['adults']??2) ?> adult<?= (int)($q['adults']??2)!==1?'s':'' ?>
        <?= (int)($q['children']??0) > 0 ? ', '.(int)$q['children'].' child'.((int)$q['children']!==1?'ren':'') : '' ?>
      </span></div>
    <div class="field"><label>Start Date</label>
      <span><?= $q['start_date'] ? date('d F Y',strtotime($q['start_date'])) : 'Flexible' ?></span></div>
  </div>
  <?php if (!empty($itinOverview)): ?>
  <div class="overview" style="margin-top:1rem"><?= nl2br(htmlspecialchars($itinOverview)) ?></div>
  <?php endif; ?>
</div>

<!-- ── DAY BY DAY ─────────────────────────────────────────── -->
<?php if (!empty($itinDays)): ?>
<div class="sec">
  <div class="sec-title">Day-by-Day Itinerary</div>
  <?= \App\Helpers\ItineraryDayCards::render($itinDays) ?>
</div>
<?php endif; ?>

<!-- ── INCLUDES / EXCLUDES ────────────────────────────────── -->
<?php if (!empty($itinIncludes) || !empty($itinExcludes)): ?>
<div class="sec">
  <div class="two-col-list">
    <?php if (!empty($itinIncludes)): ?>
    <div>
      <div class="list-title inc">What's Included</div>
      <ul class="list-items">
        <?php foreach ($itinIncludes as $inc): ?>
        <li><?= htmlspecialchars($inc) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if (!empty($itinExcludes)): ?>
    <div>
      <div class="list-title exc">What's Not Included</div>
      <ul class="list-items exc">
        <?php foreach ($itinExcludes as $exc): ?>
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
    Pricing and payment details are<br>
    in your separate Quotation PDF.
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
