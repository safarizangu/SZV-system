<?php
/**
 * Service Voucher / Exchange Order
 * URL: api/voucher_pdf.php?ref=SZVXXX
 * Auth: Admin session required
 *
 * For handing to (or emailing) the ground suppliers on a confirmed trip —
 * lodges, camps, park-gate staff — as proof that Safari Zangu Ventures has
 * already been paid and which services are covered. Distinct from
 * api/booking_pdf.php, which is the guest-facing confirmation/invoice.
 */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
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
} catch (\Exception $e) { die('DB error: ' . $e->getMessage()); }

// Same itinerary-resolution fallback as api/booking_pdf.php.
$itinDays = [];
try {
    $pr = null;
    if (!empty($b['itinerary_id'])) {
        $is = $db->prepare("SELECT days FROM itineraries WHERE id=? LIMIT 1");
        $is->execute([$b['itinerary_id']]);
        $pr = $is->fetch() ?: null;
    }
    if (!$pr) {
        $ps = $db->prepare("SELECT i.days FROM packages p JOIN itineraries i ON i.id=p.itinerary_id
                            WHERE p.name=? AND p.is_active=1 LIMIT 1");
        $ps->execute([$b['package_name']]);
        $pr = $ps->fetch() ?: null;
    }
    if ($pr) $itinDays = json_decode($pr['days'] ?? '[]', true) ?: [];
} catch (\Exception $e) {}

// Assigned guide/driver + vehicle, if set via admin/operations_calendar.php.
$assignment = null;
try {
    $as = $db->prepare("
        SELECT st.full_name AS staff_name, st.role AS staff_role, st.phone AS staff_phone,
               v.name AS vehicle_name
        FROM booking_assignments ba
        LEFT JOIN staff st ON st.id = ba.staff_id
        LEFT JOIN vehicles v ON v.id = ba.vehicle_id
        WHERE ba.booking_ref = ? LIMIT 1
    ");
    $as->execute([$ref]);
    $assignment = $as->fetch() ?: null;
} catch (\Exception $e) {}

$emergency = [];
try {
    $row = $db->query("SELECT value FROM settings WHERE `key`='emergency_contacts' LIMIT 1")->fetch();
    if ($row) $emergency = json_decode($row['value'], true) ?: [];
} catch (\Exception $e) {}

$pax       = (int)($b['travelers'] ?? $b['adults'] ?? 2);
$adults    = (int)($b['adults'] ?? 2);
$children  = (int)($b['children'] ?? 0);
$travelDt  = $b['travel_date'] ? date('d F Y', strtotime($b['travel_date'])) : '—';
$issuedDt  = date('d F Y');
$logoPath  = BASE_PATH . '/assets/images/logo.jpeg';
$logoUrl   = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $logoPath;

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Voucher <?= htmlspecialchars($ref) ?> — Safari Zangu Ventures</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
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
<?= \App\Helpers\ItineraryDayCards::css() ?>
.notice{background:#fffbeb;border-left:3px solid #c8963e;padding:.85rem 1rem;font-size:.8rem;line-height:1.65;margin-top:1.5rem}
.paid-notice{background:#f0fdf4;border-left:3px solid #166534;padding:.85rem 1rem;font-size:.8rem;line-height:1.65;margin-top:1rem;font-weight:600;color:#166534}
.sig-box{display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-top:2.5rem}
.sig-line{border-top:1px solid #0a0a0a;padding-top:.4rem;font-size:.68rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em}
.footer{text-align:center;margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid #e5e7eb;font-size:.72rem;color:#9ca3af}
@media print{body{font-size:11pt}@page{margin:1.5cm}.no-print{display:none}}
</style>
</head>
<body>
<div class="page">

  <div class="no-print" style="text-align:right;margin-bottom:1rem">
    <button onclick="window.print()"
            style="background:#0a0a0a;color:#fff;border:none;padding:.65rem 1.25rem;font-size:.78rem;font-weight:600;letter-spacing:.08em;cursor:pointer">
      ⎙ Print / Save PDF
    </button>
  </div>

  <div class="hdr">
    <div style="display:flex;align-items:center;gap:1rem">
      <?php $logoFile = rtrim($_SERVER['DOCUMENT_ROOT'],'/').(BASE_PATH).'/assets/images/logo.jpeg'; if (file_exists($logoFile)): ?>
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
      <div class="bref-num"><?= htmlspecialchars($ref) ?></div>
      <div class="bref-date">Issued <?= $issuedDt ?></div>
      <div class="badge">Service Voucher</div>
    </div>
  </div>

  <div class="sec">
    <div class="sec-title">Trip Details</div>
    <div class="four-col">
      <div class="field"><label>Guest</label><span><?= htmlspecialchars($b['full_name']) ?></span></div>
      <div class="field"><label>Package</label><span><?= htmlspecialchars($b['package_name']) ?></span></div>
      <div class="field"><label>Travel Date</label><span><?= $travelDt ?></span></div>
      <div class="field"><label>Party Size</label><span><?= $adults ?> Adult<?= $adults !== 1 ? 's' : '' ?><?= $children ? ' + ' . $children . ' Child' . ($children !== 1 ? 'ren' : '') : '' ?></span></div>
    </div>
  </div>

  <?php if ($assignment && ($assignment['staff_name'] || $assignment['vehicle_name'])): ?>
  <div class="sec">
    <div class="sec-title">Guide &amp; Vehicle</div>
    <div class="four-col">
      <div class="field"><label>Guide/Driver</label><span><?= htmlspecialchars($assignment['staff_name'] ?: '—') ?></span></div>
      <div class="field"><label>Contact</label><span><?= htmlspecialchars($assignment['staff_phone'] ?: '—') ?></span></div>
      <div class="field"><label>Vehicle</label><span><?= htmlspecialchars($assignment['vehicle_name'] ?: '—') ?></span></div>
      <div class="field"><label>Reference</label><span><?= htmlspecialchars($ref) ?></span></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($itinDays): ?>
  <div class="sec">
    <div class="sec-title">Services Covered — Day by Day</div>
    <?= \App\Helpers\ItineraryDayCards::render($itinDays) ?>
  </div>
  <?php endif; ?>

  <div class="paid-notice">
    ✓ This trip has been paid in full to Safari Zangu Ventures. Please do not request payment from the bearer for any service listed above.
  </div>
  <div class="notice">
    To the service provider: kindly confirm the services above were rendered by signing and stamping below. For any discrepancy or additional charge, contact Safari Zangu Ventures directly at +255 663 430 934 before accepting payment from the guest.
  </div>

  <?php if (array_filter($emergency)): ?>
  <div class="sec">
    <div class="sec-title">Emergency Contacts</div>
    <div class="four-col">
      <?php if (!empty($emergency['office_24h'])): ?><div class="field"><label>24h Office</label><span><?= htmlspecialchars($emergency['office_24h']) ?></span></div><?php endif; ?>
      <?php if (!empty($emergency['hospital'])): ?><div class="field"><label>Hospital</label><span><?= htmlspecialchars($emergency['hospital']) ?></span></div><?php endif; ?>
      <?php if (!empty($emergency['police'])): ?><div class="field"><label>Police</label><span><?= htmlspecialchars($emergency['police']) ?></span></div><?php endif; ?>
      <?php if (!empty($emergency['tourist_police'])): ?><div class="field"><label>Tourist Police</label><span><?= htmlspecialchars($emergency['tourist_police']) ?></span></div><?php endif; ?>
      <?php if (!empty($emergency['evacuation_provider'])): ?><div class="field"><label>Medical Evacuation</label><span><?= htmlspecialchars($emergency['evacuation_provider']) ?></span></div><?php endif; ?>
    </div>
    <?php if (!empty($emergency['notes'])): ?><p style="margin-top:.6rem;font-size:.8rem;color:#374151"><?= nl2br(htmlspecialchars($emergency['notes'])) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="sig-box">
    <div><div style="height:2.5rem"></div><div class="sig-line">Supplier Signature &amp; Stamp</div></div>
    <div><div style="height:2.5rem"></div><div class="sig-line">Date</div></div>
  </div>

  <div class="footer">
    Safari Zangu Ventures Ltd &middot; Njiro Road, Arusha, Tanzania &middot; +255 663 430 934 &middot; info@safarizanguven.com<br>
    TALA Licence No. 037129 &middot; Reg. No. 179228971
  </div>

</div>
</body>
</html>
