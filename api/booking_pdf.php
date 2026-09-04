<?php
/**
 * Booking Confirmation PDF
 * URL: api/booking_pdf.php?ref=SZVXXX
 * Auth: Admin session required
 */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

$ref = strtoupper(trim($_GET['ref'] ?? ''));
if (!$ref) { http_response_code(400); die('Booking reference required.'); }

$db = \App\Models\Database::getInstance();
try {
    $s = $db->prepare("SELECT * FROM bookings WHERE booking_ref=? LIMIT 1");
    $s->execute([$ref]);
    $b = $s->fetch();
    if (!$b) { http_response_code(404); die('Booking not found.'); }
} catch (\Exception $e) { die('DB error: '.$e->getMessage()); }

// Load linked itinerary via package
$itinDays = []; $itinIncludes = []; $itinExcludes = [];
try {
    $ps = $db->prepare("SELECT i.days, i.includes, i.excludes, i.name as itin_name
                        FROM packages p JOIN itineraries i ON i.id=p.itinerary_id
                        WHERE p.name=? AND p.is_active=1 LIMIT 1");
    $ps->execute([$b['package_name']]);
    $pr = $ps->fetch();
    if ($pr) {
        $itinDays     = json_decode($pr['days']     ?? '[]', true) ?: [];
        $itinIncludes = json_decode($pr['includes'] ?? '[]', true) ?: [];
        $itinExcludes = json_decode($pr['excludes'] ?? '[]', true) ?: [];
    }
} catch (\Exception $e) {}

$pax       = (int)($b['travelers'] ?? $b['adults'] ?? 2);
$adults    = (int)($b['adults'] ?? 2);
$children  = (int)($b['children'] ?? 0);
$total     = (float)($b['total_amount'] ?? 0);
$deposit   = (float)($b['deposit_amount'] ?? round($total * 0.25, 2));
$balance   = round($total - $deposit, 2);
$travelDt  = $b['travel_date'] ? date('d F Y', strtotime($b['travel_date'])) : '—';
$bookedDt  = date('d F Y', strtotime($b['created_at']));
$logoPath  = BASE_PATH . '/assets/images/logo.jpeg';
$logoUrl   = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $logoPath;

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking <?= htmlspecialchars($ref) ?> — Safari Zangu Ventures</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:13px;color:#0a0a0a;background:#fff;line-height:1.5}
.page{max-width:820px;margin:0 auto;padding:2.5rem}
.hdr{display:flex;align-items:center;justify-content:space-between;padding-bottom:1.5rem;border-bottom:2px solid #0a0a0a;margin-bottom:2rem}
.logo-img{width:64px;height:64px;border-radius:50%;object-fit:cover}
.bref-num{font-size:1.4rem;font-weight:700;letter-spacing:.04em;text-align:right}
.bref-date{font-size:.72rem;color:#9ca3af;margin-top:.2rem;text-align:right}
.badge{display:inline-block;padding:.2rem .65rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;background:#0a0a0a;color:#fff;margin-top:.4rem}
.sec{margin-bottom:1.75rem}
.sec-title{font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;border-bottom:1px solid #e5e7eb;padding-bottom:.4rem;margin-bottom:.85rem}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
.four-col{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem}
.field label{display:block;font-size:.56rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#9ca3af;margin-bottom:.2rem}
.field span{font-size:.88rem;font-weight:500}
.day-tbl{width:100%;border-collapse:collapse;margin-bottom:.5rem}
.day-tbl th{font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#fff;background:#0a0a0a;padding:5px 8px;text-align:left}
.day-tbl td{padding:6px 8px;border-bottom:1px solid #f3f4f6;font-size:.8rem;vertical-align:top}
.day-tbl tr:nth-child(even) td{background:#f9f9f8}
.day-num{font-weight:700;color:#c8963e;font-size:1rem}
.pay-tbl{width:100%;border-collapse:collapse}
.pay-tbl th{font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#fff;background:#0a0a0a;padding:5px 8px;text-align:left}
.pay-tbl td{padding:6px 8px;border-bottom:1px solid #f3f4f6;font-size:.82rem}
.pay-tbl td.r{text-align:right;font-weight:600}
.total-block{background:#f7f7f5;border:1px solid #e5e7eb;padding:1.25rem;margin-top:1rem}
.grand{font-size:1.8rem;font-weight:700}
.pay-box{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:.75rem}
.pay-method{border:1px solid #e5e7eb;padding:1rem}
.pay-method h4{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;margin-bottom:.65rem;color:#0a0a0a}
.pay-method p{font-size:.8rem;line-height:1.8;color:#374151}
.pay-method strong{color:#0a0a0a}
.incl-list{columns:2;column-gap:1rem;margin:0;padding:0;list-style:none}
.incl-list li{font-size:.8rem;padding:.2rem 0;color:#374151}
.incl-list li::before{content:'✓ ';color:#166534;font-weight:700}
.excl-list{columns:2;column-gap:1rem;margin:0;padding:0;list-style:none}
.excl-list li{font-size:.8rem;padding:.2rem 0;color:#374151}
.excl-list li::before{content:'✗ ';color:#dc2626;font-weight:700}
.notice{background:#fffbeb;border-left:3px solid #c8963e;padding:.85rem 1rem;font-size:.8rem;line-height:1.65;margin-top:1.5rem}
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
      <div class="bref-num"><?= htmlspecialchars($ref) ?></div>
      <div class="bref-date">Booked <?= $bookedDt ?></div>
      <div style="text-align:right">
        <span class="badge"><?= strtoupper(str_replace('_',' ',$b['payment_status'] ?? 'Pending')) ?></span>
      </div>
    </div>
  </div>

  <!-- Guest details -->
  <div class="sec">
    <div class="sec-title">Guest Details</div>
    <div class="two-col">
      <div class="field"><label>Full Name</label><span><?= htmlspecialchars($b['full_name']) ?></span></div>
      <div class="field"><label>Email</label><span><?= htmlspecialchars($b['email']) ?></span></div>
      <div class="field"><label>Phone</label><span><?= htmlspecialchars($b['phone'] ?? '—') ?></span></div>
      <div class="field"><label>Country</label><span><?= htmlspecialchars($b['country'] ?? '—') ?></span></div>
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
  </div>

  <!-- Day-by-day itinerary -->
  <?php if (!empty($itinDays)): ?>
  <div class="sec">
    <div class="sec-title">Day-by-Day Itinerary</div>
    <table class="day-tbl">
      <thead>
        <tr>
          <th style="width:45px">Day</th>
          <th>Programme</th>
          <th>Accommodation</th>
          <th>Park / Location</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($itinDays as $day): ?>
        <tr>
          <td class="day-num"><?= $day['day'] ?? '' ?></td>
          <td>
            <strong><?= htmlspecialchars($day['title'] ?? '') ?></strong>
            <?php if (!empty($day['description'])): ?>
            <br><span style="font-size:.75rem;color:#6b7280"><?= htmlspecialchars(mb_substr($day['description'],0,100)) ?><?= strlen($day['description'])>100?'…':'' ?></span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($day['accommodation_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($day['park_fee_name'] ?? $day['location'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
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

  <!-- Payment summary -->
  <div class="sec">
    <div class="sec-title">Payment Summary</div>
    <table class="pay-tbl">
      <thead><tr><th>Description</th><th class="r">Amount (USD)</th></tr></thead>
      <tbody>
        <tr><td>Total Safari Cost</td><td class="r">$<?= number_format($total,2) ?></td></tr>
        <tr><td>Deposit Due (25%) — payable within 48 hours to secure dates</td><td class="r">$<?= number_format($deposit,2) ?></td></tr>
        <tr><td><strong>Balance Due</strong> — 45 days before departure</td><td class="r"><strong>$<?= number_format($balance,2) ?></strong></td></tr>
      </tbody>
    </table>
    <div class="total-block">
      <div style="font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;margin-bottom:.4rem">Grand Total</div>
      <div class="grand">$<?= number_format($total,2) ?> USD</div>
      <?php if ($pax > 0): ?>
      <div style="font-size:.72rem;color:#6b7280;margin-top:.3rem">$<?= number_format($total/$pax,2) ?> per person · <?= $pax ?> guest<?= $pax!==1?'s':'' ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Payment Details -->
  <div class="sec">
    <div class="sec-title">Payment Details</div>
    <div class="pay-box">
      <!-- Bank Transfer -->
      <div class="pay-method">
        <h4>🏦 Bank Transfer</h4>
        <p>
          <strong>Bank:</strong> <?= defined('BANK_NAME') ? htmlspecialchars(BANK_NAME) : 'ABSA Bank Tanzania' ?><br>
          <strong>Account Name:</strong> <?= defined('BANK_ACCOUNT_NAME') ? htmlspecialchars(BANK_ACCOUNT_NAME) : 'Safari Zangu Ventures Ltd' ?><br>
          <strong>Account No:</strong> <?= defined('BANK_ACCOUNT_NO') ? htmlspecialchars(BANK_ACCOUNT_NO) : '—' ?><br>
          <strong>SWIFT:</strong> <?= defined('BANK_SWIFT') ? htmlspecialchars(BANK_SWIFT) : '—' ?><br>
          <strong>Reference:</strong> <?= htmlspecialchars($ref) ?>
        </p>
      </div>
      <!-- PayPal -->
      <div class="pay-method">
        <h4>💳 PayPal / Card</h4>
        <?php if (defined('PAYPAL_EMAIL') && PAYPAL_EMAIL): ?>
        <p>
          Send payment to:<br>
          <strong><?= htmlspecialchars(PAYPAL_EMAIL) ?></strong><br><br>
          Include reference: <strong><?= htmlspecialchars($ref) ?></strong><br>
          <span style="font-size:.72rem;color:#9ca3af">PayPal Friends &amp; Family or use our payment link</span>
        </p>
        <?php else: ?>
        <p style="color:#9ca3af">Contact us for PayPal payment link:<br>
          <strong>info@safarizanguven.com</strong><br>
          <strong>+255 663 430 934</strong>
        </p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Notice -->
  <div class="notice">
    <strong>Cancellation Policy:</strong> Full refund for cancellations 60+ days before departure.
    50% refund for cancellations 30–59 days before departure.
    Cancellations within 30 days forfeit the deposit.
    All cancellations must be made in writing to info@safarizanguven.com.
  </div>

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
