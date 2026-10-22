<?php
/**
 * Daily Departure Manifest — every confirmed departure on one date, for
 * the office/ops team to use as a single checklist rather than opening
 * each booking separately.
 * URL: api/manifest_pdf.php?date=YYYY-MM-DD
 * Auth: Admin session required
 */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
if (!defined('SZV_PDF_INTERNAL_RENDER')) {
    AuthMiddleware::requireAdmin();
}

$date = trim($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { http_response_code(400); die('A valid date is required.'); }

$db = \App\Models\Database::getInstance();
$bookings = [];
try {
    $bookings = $db->query("
        SELECT b.booking_ref, b.full_name, b.phone, b.email, b.adults, b.children, b.package_name,
               b.special_request, b.accommodation_level,
               st.full_name AS staff_name, st.phone AS staff_phone, v.name AS vehicle_name
        FROM bookings b
        LEFT JOIN booking_assignments ba ON ba.booking_ref = b.booking_ref
        LEFT JOIN staff st ON st.id = ba.staff_id
        LEFT JOIN vehicles v ON v.id = ba.vehicle_id
        WHERE b.payment_status != 'cancelled' AND b.travel_date = " . $db->quote($date) . "
        ORDER BY b.full_name
    ")->fetchAll();
} catch (\Exception $e) { error_log('[manifest_pdf] ' . $e->getMessage()); die('Could not load this document. Please try again or contact support.'); }

$totalPax = array_sum(array_map(fn($b) => (int)$b['adults'] + (int)$b['children'], $bookings));
$logoPath = BASE_PATH . '/assets/images/logo.jpeg';
$logoUrl  = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $logoPath;
$logoFile = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . BASE_PATH . '/assets/images/logo.jpeg';

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Departure Manifest — <?= htmlspecialchars(date('d M Y', strtotime($date))) ?> — Safari Zangu Ventures</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
*,*::before,*::after{-webkit-print-color-adjust:exact;print-color-adjust:exact;color-adjust:exact}
body{font-family:'Times New Roman',Times,'Liberation Serif',serif;font-size:13.5px;color:#0a0a0a;background:#fff;line-height:1.55}
.page{max-width:1000px;margin:0 auto;padding:2.5rem 2.5rem 4rem}
.brandbar{height:4px;background:#c8963e;margin:-2.5rem -2.5rem 2rem}
.hdr{display:flex;align-items:center;justify-content:space-between;padding-bottom:1.5rem;border-bottom:2px solid #0a0a0a;margin-bottom:2rem}
.company-name{font-size:1.9rem;font-weight:700;letter-spacing:.02em}
.hdr-title{font-size:1.3rem;font-weight:700;letter-spacing:.02em;text-align:right}
.hdr-sub{font-size:.75rem;color:#0a0a0a;margin-top:.2rem;text-align:right}
.summary{background:#f7f7f5;border:1px solid #e5e7eb;border-left:4px solid #c8963e;padding:.85rem 1.25rem;margin-bottom:1.5rem;display:flex;gap:2rem;font-size:.85rem}
.summary strong{font-size:1.1rem}
table{width:100%;border-collapse:collapse;border:1px solid #0a0a0a}
th{font-size:.6rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#fff;background:#0a0a0a;padding:7px 8px;text-align:left;border:1px solid #2d2d2d}
td{padding:8px;border:1px solid #e5e7eb;font-size:.8rem;vertical-align:top}
tbody tr:nth-child(even){background:#f9f9f8}
.pax{white-space:nowrap}
.missing{color:#991b1b;font-weight:600}
.closing-seal{text-align:center;margin:2rem 0}
.closing-seal img{width:170px;height:170px;border-radius:50%;object-fit:cover;display:inline-block}
.footer{text-align:center;margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid #e5e7eb;font-size:.72rem;color:#0a0a0a}
.page-footer{position:fixed;bottom:.7rem;left:2.5rem;right:2.5rem;display:flex;justify-content:space-between;font-size:.62rem;color:#0a0a0a;letter-spacing:.04em;border-top:1px solid #e5e7eb;padding-top:.5rem}
.page-footer .pg:after{content:"Page " counter(page)}
@media print{body{font-size:11pt}@page{margin:1.5cm;size:landscape}.no-print{display:none}.page{padding:1.5rem 1.5rem 3.5rem}.page-footer{left:1.5rem;right:1.5rem}}
</style>
</head>
<body>
<!-- Running footer — a direct child of <body>, not nested inside .page,
     because dompdf only reliably repaints position:fixed elements on
     every generated page when they sit outside any padded/margined
     ancestor block. -->
<div class="page-footer">
  <span>Safari Zangu Ventures · Departure Manifest · <?= htmlspecialchars($date) ?></span>
  <span class="pg"></span>
</div>

<div class="page">
<div class="brandbar"></div>

  <?php if (!defined('SZV_PDF_INTERNAL_RENDER')): ?>
  <div class="no-print" style="text-align:right;margin-bottom:1rem">
    <button onclick="window.print()"
            style="background:#0a0a0a;color:#fff;border:none;padding:.65rem 1.25rem;font-size:.78rem;font-weight:600;letter-spacing:.08em;cursor:pointer">
      ⎙ Print / Save PDF
    </button>
  </div>
  <?php endif; ?>

  <div class="hdr">
    <div>
      <div class="company-name">SZV</div>
      <div style="font-size:.65rem;color:#0a0a0a;letter-spacing:.1em;margin-top:.3rem">ARUSHA, TANZANIA</div>
    </div>
    <div>
      <div class="hdr-title">Departure Manifest</div>
      <div class="hdr-sub"><?= htmlspecialchars(date('l, d F Y', strtotime($date))) ?></div>
    </div>
  </div>

  <div class="summary">
    <div><strong><?= count($bookings) ?></strong> Departure<?= count($bookings) !== 1 ? 's' : '' ?></div>
    <div><strong><?= $totalPax ?></strong> Traveler<?= $totalPax !== 1 ? 's' : '' ?></div>
  </div>

  <?php if (!$bookings): ?>
  <p style="color:#0a0a0a;padding:2rem 0;text-align:center">No departures scheduled for this date.</p>
  <?php else: ?>
  <table>
    <thead><tr>
      <th>Booking</th><th>Guest</th><th>Contact</th><th>Package</th><th>Pax</th><th>Guide/Driver</th><th>Vehicle</th><th>Notes</th>
    </tr></thead>
    <tbody>
    <?php foreach ($bookings as $b): ?>
    <tr>
      <td><?= htmlspecialchars($b['booking_ref']) ?></td>
      <td><?= htmlspecialchars($b['full_name']) ?></td>
      <td><?= htmlspecialchars($b['phone'] ?: $b['email']) ?></td>
      <td><?= htmlspecialchars($b['package_name']) ?></td>
      <td class="pax"><?= (int)$b['adults'] ?>A<?= $b['children'] ? '+' . (int)$b['children'] . 'C' : '' ?></td>
      <td<?= $b['staff_name'] ? '' : ' class="missing"' ?>><?= htmlspecialchars($b['staff_name'] ?: 'Not assigned') ?><?= $b['staff_phone'] ? '<br><span style="color:#0a0a0a">'.htmlspecialchars($b['staff_phone']).'</span>' : '' ?></td>
      <td<?= $b['vehicle_name'] ? '' : ' class="missing"' ?>><?= htmlspecialchars($b['vehicle_name'] ?: 'Not assigned') ?></td>
      <td><?= htmlspecialchars($b['special_request'] ?: '—') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Closing seal — larger, centered, no border ring -->
  <?php if (file_exists($logoFile)): ?>
  <div class="closing-seal">
    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Safari Zangu Ventures">
  </div>
  <?php endif; ?>

  <div class="footer">
    Safari Zangu Ventures Ltd &middot; Njiro Road, Arusha, Tanzania &middot; +255 663 430 934 &middot; info@safarizanguven.com<br>
    Generated <?= date('d M Y, H:i') ?>
  </div>

</div>
</body>
</html>
