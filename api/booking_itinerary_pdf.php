<?php
/**
 * Booking Itinerary PDF — the day-by-day companion to api/booking_pdf.php.
 * URL: api/booking_itinerary_pdf.php?ref=SZVXXX
 * Auth: Admin session required
 *
 * Split out from api/booking_pdf.php so the client receives two focused
 * documents (Quotation + Itinerary) instead of one long combined PDF.
 * Same itinerary-resolution logic booking_pdf.php always used, kept here
 * so both documents agree on which itinerary a booking actually has.
 */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
// Auth is skipped only when included internally with SZV_PDF_INTERNAL_RENDER
// already defined by trusted server-side code — never settable via request
// input. Every real HTTP request still requires an admin session.
if (!defined('SZV_PDF_INTERNAL_RENDER')) {
    AuthMiddleware::requireAdmin();
}

$ref = strtoupper(trim($_GET['ref'] ?? ''));
if (!$ref) { http_response_code(400); die('Booking reference required.'); }

$db = \App\Models\Database::getInstance();
try {
    $s = $db->prepare("SELECT * FROM bookings WHERE booking_ref=? LIMIT 1");
    $s->execute([$ref]);
    $b = $s->fetch();
    if (!$b) { http_response_code(404); die('Booking not found.'); }
} catch (\Exception $e) { die('DB error: '.$e->getMessage()); }

// Load the linked itinerary — prefer the booking's own itinerary_id (a
// direct FK, reliable) and only fall back to matching by package name
// (fragile — breaks if the package was ever renamed) for bookings made
// before bookings.itinerary_id existed.
$itinDays = []; $itinIncludes = []; $itinExcludes = []; $itinOverview = '';
try {
    $pr = null;
    if (!empty($b['itinerary_id'])) {
        $is = $db->prepare("SELECT * FROM itineraries WHERE id=? LIMIT 1");
        $is->execute([$b['itinerary_id']]);
        $pr = $is->fetch() ?: null;
    }
    if (!$pr) {
        $ps = $db->prepare("SELECT i.* FROM packages p JOIN itineraries i ON i.id=p.itinerary_id
                            WHERE p.name=? AND p.is_active=1 LIMIT 1");
        $ps->execute([$b['package_name']]);
        $pr = $ps->fetch() ?: null;
    }
    if ($pr) {
        $itinDays     = json_decode($pr['days']     ?? '[]', true) ?: [];
        $itinIncludes = json_decode($pr['includes'] ?? '[]', true) ?: [];
        $itinExcludes = json_decode($pr['excludes'] ?? '[]', true) ?: [];
        $itinOverview = $pr['overview'] ?? '';
    }
} catch (\Exception $e) {}

$adults    = (int)($b['adults'] ?? 2);
$children  = (int)($b['children'] ?? 0);
$travelDt  = $b['travel_date'] ? date('d F Y', strtotime($b['travel_date'])) : '—';
$bookedDt  = date('d F Y', strtotime($b['created_at']));
$logoPath  = BASE_PATH . '/assets/images/logo.jpeg';
$logoUrl   = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $logoPath;

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Itinerary — Booking <?= htmlspecialchars($ref) ?> — Safari Zangu Ventures</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
/* 'DejaVu Sans' is dompdf's own bundled font — without it as a fallback,
   dompdf's generic sans-serif silently renders glyphs like → and ✦ as "?"
   instead of erroring, since Arial isn't actually installed on Linux. */
body{font-family:Arial,'DejaVu Sans',sans-serif;font-size:13px;color:#0a0a0a;background:#fff;line-height:1.5}
.page{max-width:820px;margin:0 auto;padding:2.5rem}
.hdr{display:flex;align-items:center;justify-content:space-between;padding-bottom:1.5rem;border-bottom:2px solid #0a0a0a;margin-bottom:2rem}
.logo-img{width:64px;height:64px;border-radius:50%;object-fit:cover}
.bref-num{font-size:1.4rem;font-weight:700;letter-spacing:.04em;text-align:right}
.bref-date{font-size:.72rem;color:#9ca3af;margin-top:.2rem;text-align:right}
.badge{display:inline-block;padding:.2rem .65rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;background:#0a0a0a;color:#fff;margin-top:.4rem}
.sec{margin-bottom:1.75rem}
.sec-title{font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;border-bottom:1px solid #e5e7eb;padding-bottom:.4rem;margin-bottom:.85rem}
.four-col{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem}
.field label{display:block;font-size:.56rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#9ca3af;margin-bottom:.2rem}
.field span{font-size:.88rem;font-weight:500}
.overview{font-size:.88rem;line-height:1.8;color:#374151}
<?= \App\Helpers\ItineraryDayCards::css() ?>
.incl-list{columns:2;column-gap:1rem;margin:0;padding:0;list-style:none}
.incl-list li{font-size:.8rem;padding:.2rem 0;color:#374151}
.incl-list li::before{content:'✓ ';color:#166534;font-weight:700}
.excl-list{columns:2;column-gap:1rem;margin:0;padding:0;list-style:none}
.excl-list li{font-size:.8rem;padding:.2rem 0;color:#374151}
.excl-list li::before{content:'✗ ';color:#dc2626;font-weight:700}
.footer{text-align:center;margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid #e5e7eb;font-size:.72rem;color:#9ca3af}
@media print{body{font-size:11pt}@page{margin:1.5cm}.no-print{display:none}}
</style>
</head>
<body>
<div class="page">

  <!-- Print button -->
  <div class="no-print" style="text-align:right;margin-bottom:1rem">
    <button onclick="window.print()"
            style="background:#0a0a0a;color:#fff;border:none;padding:.65rem 1.25rem;font-size:.78rem;font-weight:600;letter-spacing:.08em;cursor:pointer">
      ⎙ Print / Save PDF
    </button>
  </div>

  <!-- Header -->
  <div class="hdr">
    <div style="display:flex;align-items:center;gap:1rem">
      <?php
      $logoFile = rtrim($_SERVER['DOCUMENT_ROOT'],'/').(BASE_PATH).'/assets/images/logo.jpeg';
      if (file_exists($logoFile)):
      ?>
      <img src="<?= $logoUrl ?>" alt="Safari Zangu" class="logo-img">
      <?php else: ?>
      <div style="width:64px;height:64px;border-radius:50%;background:#0a0a0a;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.7rem;text-align:center;line-height:1.2">SZV</div>
      <?php endif; ?>
      <div>
        <div style="font-size:1.1rem;font-weight:700;letter-spacing:.04em">Safari Zangu Ventures</div>
        <div style="font-size:.68rem;color:#9ca3af;letter-spacing:.1em">ARUSHA, TANZANIA</div>
      </div>
    </div>
    <div>
      <div style="font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;text-align:right">ITINERARY</div>
      <div class="bref-num"><?= htmlspecialchars($ref) ?></div>
      <div class="bref-date">Booked <?= $bookedDt ?></div>
    </div>
  </div>

  <!-- Safari details -->
  <div class="sec">
    <div class="sec-title">Safari Details</div>
    <div class="four-col">
      <div class="field"><label>Package</label><span><?= htmlspecialchars($b['package_name'] ?? '—') ?></span></div>
      <div class="field"><label>Travel Date</label><span><?= $travelDt ?></span></div>
      <div class="field"><label>Guests</label><span><?= $adults ?> adult<?= $adults!==1?'s':'' ?><?= $children?" + $children child":'' ?></span></div>
      <div class="field"><label>Accommodation</label><span><?= ucwords(str_replace(['_','-'],' ',$b['accommodation_level']??'Standard')) ?></span></div>
    </div>
    <?php if (!empty($itinOverview)): ?>
    <div class="overview" style="margin-top:1rem"><?= nl2br(htmlspecialchars($itinOverview)) ?></div>
    <?php endif; ?>
  </div>

  <!-- Day-by-day itinerary -->
  <?php if (!empty($itinDays)): ?>
  <div class="sec">
    <div class="sec-title">Day-by-Day Itinerary</div>
    <?= \App\Helpers\ItineraryDayCards::render($itinDays) ?>
  </div>
  <?php endif; ?>

  <!-- Includes / Excludes -->
  <?php if ($itinIncludes || $itinExcludes): ?>
  <div class="sec">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
      <?php if ($itinIncludes): ?>
      <div>
        <div class="sec-title">What's Included</div>
        <ul class="incl-list">
          <?php foreach ($itinIncludes as $inc): ?>
          <li><?= htmlspecialchars($inc) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ($itinExcludes): ?>
      <div>
        <div class="sec-title">Not Included</div>
        <ul class="excl-list">
          <?php foreach ($itinExcludes as $exc): ?>
          <li><?= htmlspecialchars($exc) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Footer -->
  <div class="footer">
    <p>Safari Zangu Ventures Ltd · Njiro Road, Arusha, Tanzania</p>
    <p>info@safarizanguven.com · +255 663 430 934 · safarizanguven.com</p>
    <p style="margin-top:.5rem">TALA Licence No. 037129 · Reg. No. 179228971</p>
  </div>

</div>
<script>window.onload = function(){ window.print(); }</script>
</body>
</html>
