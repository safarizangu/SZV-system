<?php
/**
 * Booking PDF — generates a booking confirmation document
 * URL: api/booking_pdf.php?ref=SZVABC123
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

$pax      = (int)($b['travelers'] ?? $b['adults'] ?? 2);
$total    = (float)($b['total_amount'] ?? 0);
$deposit  = (float)($b['deposit_amount'] ?? round($total * 0.25, 2));
$balance  = round($total - $deposit, 2);
$travelDt = $b['travel_date'] ? date('d F Y', strtotime($b['travel_date'])) : '—';
$bookedDt = date('d F Y', strtotime($b['created_at']));

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking <?= htmlspecialchars($ref) ?> — Safari Zangu Ventures</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Jost:wght@300;400;500;600;700&display=swap');
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Jost',Arial,sans-serif;font-size:13px;color:#0a0a0a;background:#fff;line-height:1.5}
.page{max-width:820px;margin:0 auto;padding:2.5rem}
.hdr{display:grid;grid-template-columns:1fr auto;gap:2rem;align-items:start;padding-bottom:1.5rem;border-bottom:2px solid #0a0a0a;margin-bottom:2rem}
.logo{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:500;letter-spacing:.04em}
.logo-sub{font-size:.58rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#9ca3af;margin-top:.3rem}
.bref{text-align:right}
.bref-num{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:500}
.bref-date{font-size:.72rem;color:#9ca3af;margin-top:.2rem}
.badge{display:inline-block;padding:.2rem .65rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;background:#0a0a0a;color:#fff;margin-top:.4rem}
.sec{margin-bottom:1.75rem}
.sec-title{font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;border-bottom:1px solid #e5e7eb;padding-bottom:.4rem;margin-bottom:.85rem}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
.four-col{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem}
.field label{display:block;font-size:.56rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#9ca3af;margin-bottom:.2rem}
.field span{font-size:.88rem;font-weight:500}
.pay-tbl{width:100%;border-collapse:collapse;margin-bottom:1rem}
.pay-tbl th{font-size:.58rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#fff;background:#0a0a0a;padding:.6rem .75rem;text-align:left}
.pay-tbl td{padding:.55rem .75rem;border-bottom:1px solid #f3f4f6;font-size:.82rem}
.pay-tbl td.r{text-align:right;font-weight:600}
.total-block{background:#f7f7f5;border:1px solid #e5e7eb;padding:1.25rem;margin-top:1rem}
.grand{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:500}
.notice{background:#fffbeb;border-left:3px solid #c8963e;padding:.85rem 1rem;font-size:.8rem;line-height:1.65;margin-top:1.5rem}
.footer{text-align:center;margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid #e5e7eb;font-size:.72rem;color:#9ca3af}
@media print{body{font-size:11pt}@page{margin:1.5cm}}
</style>
</head>
<body>
<div class="page">
  <!-- Header -->
  <div class="hdr">
    <div>
      <div class="logo"><em>Safari Zangu</em> Ventures</div>
      <div class="logo-sub">Arusha, Tanzania · Est. 2024</div>
    </div>
    <div class="bref">
      <div class="bref-num"><?= htmlspecialchars($ref) ?></div>
      <div class="bref-date">Booked <?= $bookedDt ?></div>
      <div class="badge"><?= strtoupper($b['payment_status'] ?? 'Pending') ?></div>
    </div>
  </div>

  <!-- Client details -->
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
      <div class="field"><label>Travelers</label><span><?= $pax ?> guest<?= $pax!==1?'s':'' ?></span></div>
      <div class="field"><label>Accommodation</label><span><?= ucwords(str_replace(['_','-'],' ',$b['accommodation_level']??'Standard')) ?></span></div>
    </div>
  </div>

  <!-- Payment summary -->
  <div class="sec">
    <div class="sec-title">Payment Summary</div>
    <table class="pay-tbl">
      <thead><tr><th>Description</th><th class="r">Amount (USD)</th></tr></thead>
      <tbody>
        <tr><td>Total Safari Cost</td><td class="r">$<?= number_format($total,2) ?></td></tr>
        <tr><td>Deposit Paid (25%)</td><td class="r">$<?= number_format($deposit,2) ?></td></tr>
        <tr><td><strong>Balance Due</strong> (45 days before departure)</td><td class="r"><strong>$<?= number_format($balance,2) ?></strong></td></tr>
      </tbody>
    </table>
    <div class="total-block">
      <div style="font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;margin-bottom:.4rem">Grand Total</div>
      <div class="grand">$<?= number_format($total,2) ?> USD</div>
      <div style="font-size:.72rem;color:#6b7280;margin-top:.3rem">$<?= number_format($total/$pax,2) ?> per person</div>
    </div>
  </div>

  <!-- Notice -->
  <div class="notice">
    <strong>Payment Terms:</strong> A 25% deposit secures your dates. The remaining balance is due 45 days before departure. 
    Cancellations within 30 days of departure forfeit the deposit. Full refund for cancellations 60+ days prior.
  </div>

  <!-- Footer -->
  <div class="footer">
    <p>Safari Zangu Ventures Ltd · Njiro Road, Arusha, Tanzania</p>
    <p>info@safarizanguven.com · +255 663 430 934 · safarizanguven.com</p>
    <p style="margin-top:.5rem">TALA Licence No. 037129 · Reg. No. 179228971</p>
  </div>
</div>
<script>window.onload=function(){window.print()}</script>
</body>
</html>
