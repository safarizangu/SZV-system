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
if (!defined('SZV_PDF_INTERNAL_RENDER') && \App\Services\OwnershipScope::isScoped()) {
    $__ref = strtoupper(trim($_GET['ref'] ?? $_POST['ref'] ?? ''));
    if (!\App\Services\OwnershipScope::ownsBooking(\App\Models\Database::getInstance(), $__ref, \App\Services\OwnershipScope::currentAgentId())) {
        \App\Services\AccessGuard::denyPlain('booking', $__ref, basename($_SERVER['SCRIPT_NAME']));
    }
}
}

$ref = strtoupper(trim($_GET['ref'] ?? ''));
if (!$ref) { http_response_code(400); die('Booking reference required.'); }

$db = \App\Models\Database::getInstance();
try {
    $s = $db->prepare("SELECT * FROM bookings WHERE booking_ref=? LIMIT 1");
    $s->execute([$ref]);
    $b = $s->fetch();
    if (!$b) { http_response_code(404); die('Booking not found.'); }
} catch (\Exception $e) { error_log('[booking_itinerary_pdf] '.$e->getMessage()); die('Could not load this document. Please try again or contact support.'); }

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
$logoFile  = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . BASE_PATH . '/assets/images/logo.jpeg';

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Itinerary — Booking <?= htmlspecialchars($ref) ?> — Safari Zangu Ventures</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
*,*::before,*::after{-webkit-print-color-adjust:exact;print-color-adjust:exact;color-adjust:exact}
body{font-family:'Times New Roman',Times,'Liberation Serif',serif;font-size:13.5px;color:#0a0a0a;background:#fff;line-height:1.55}
.page{max-width:820px;margin:0 auto;padding:2.5rem 2.5rem 4rem}
.brandbar{height:4px;background:#c8963e;margin:-2.5rem -2.5rem 2rem}
.hdr{display:flex;align-items:center;justify-content:space-between;padding-bottom:1.5rem;border-bottom:2px solid #0a0a0a;margin-bottom:2rem}
.company-name{font-size:1.9rem;font-weight:700;letter-spacing:.02em}
.bref-num{font-size:1.4rem;font-weight:700;letter-spacing:.04em;text-align:right}
.bref-date{font-size:.72rem;color:#0a0a0a;margin-top:.2rem;text-align:right}
.badge{display:inline-block;padding:.2rem .65rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;background:#0a0a0a;color:#fff;margin-top:.4rem}
.sec{margin-bottom:1.75rem}
.sec-title{display:flex;align-items:center;gap:.5rem;font-size:.64rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#0a0a0a;border-bottom:1px solid #e5e7eb;padding-bottom:.4rem;margin-bottom:.85rem}
.sec-title::before{content:'';width:10px;height:2px;background:#c8963e;flex-shrink:0}
.four-col{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem}
.field label{display:block;font-size:.56rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#0a0a0a;margin-bottom:.2rem}
.field span{font-size:.88rem;font-weight:500}
.overview{font-size:.88rem;line-height:1.8;color:#0a0a0a}
<?= \App\Helpers\ItineraryDayCards::css() ?>
.incl-list{columns:2;column-gap:1rem;margin:0;padding:0;list-style:none}
.incl-list li{font-size:.8rem;padding:.2rem 0;color:#0a0a0a}
.incl-list li::before{content:'✓ ';color:#166534;font-weight:700}
.excl-list{columns:2;column-gap:1rem;margin:0;padding:0;list-style:none}
.excl-list li{font-size:.8rem;padding:.2rem 0;color:#0a0a0a}
.excl-list li::before{content:'✗ ';color:#dc2626;font-weight:700}
.closing-seal{text-align:center;margin:2rem 0}
.closing-seal img{width:170px;height:170px;border-radius:50%;object-fit:cover;display:inline-block}
.footer{text-align:center;margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid #e5e7eb;font-size:.72rem;color:#0a0a0a}
.page-footer{position:fixed;bottom:.7rem;left:2.5rem;right:2.5rem;display:flex;justify-content:space-between;font-size:.62rem;color:#0a0a0a;letter-spacing:.04em;border-top:1px solid #e5e7eb;padding-top:.5rem}
.page-footer .pg:after{content:"Page " counter(page)}
@media print{body{font-size:11pt}@page{margin:1.5cm}.no-print{display:none}.page{padding:1.5rem 1.5rem 3.5rem}.page-footer{left:1.5rem;right:1.5rem}}
</style>
</head>
<body>
<!-- Running footer — a direct child of <body>, not nested inside .page,
     because dompdf only reliably repaints position:fixed elements on
     every generated page when they sit outside any padded/margined
     ancestor block. -->
<div class="page-footer">
  <span>Safari Zangu Ventures · <?= htmlspecialchars($ref) ?></span>
  <span class="pg"></span>
</div>

<div class="page">
<div class="brandbar"></div>

  <?php if (!defined('SZV_PDF_INTERNAL_RENDER')): ?>
  <!-- Print button — only for an admin viewing this page live in a
       browser; omitted when PdfRenderer captures this markup to bake
       into the actual emailed/downloaded PDF. -->
  <div class="no-print" style="text-align:right;margin-bottom:1rem">
    <button onclick="window.print()"
            style="background:#0a0a0a;color:#fff;border:none;padding:.65rem 1.25rem;font-size:.78rem;font-weight:600;letter-spacing:.08em;cursor:pointer">
      ⎙ Print / Save PDF
    </button>
  </div>
  <?php endif; ?>

  <!-- Header -->
  <div class="hdr">
    <div>
      <div class="company-name">SZV</div>
      <div style="font-size:.68rem;color:#0a0a0a;letter-spacing:.1em;margin-top:.3rem">ARUSHA, TANZANIA</div>
    </div>
    <div>
      <div style="font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#0a0a0a;text-align:right">ITINERARY</div>
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

  <!-- Closing seal — larger, centered, no border ring -->
  <?php if (file_exists($logoFile)): ?>
  <div class="closing-seal">
    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Safari Zangu Ventures">
  </div>
  <?php endif; ?>

  <!-- Footer -->
  <div class="footer">
    <p>Safari Zangu Ventures Ltd · Njiro Road, Arusha, Tanzania</p>
    <p>info@safarizanguven.com · +255 663 430 934 · safarizanguven.com</p>
    <p style="margin-top:.5rem">TALA Licence No. 037129 · Reg. No. 179228971</p>
  </div>

</div>
<?php if (!defined('SZV_PDF_INTERNAL_RENDER')): ?>
<script>window.onload = function(){ window.print(); }</script>
<?php endif; ?>
</body>
</html>
