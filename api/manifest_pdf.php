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
} catch (\Exception $e) { die('DB error: ' . $e->getMessage()); }

$totalPax = array_sum(array_map(fn($b) => (int)$b['adults'] + (int)$b['children'], $bookings));
$logoPath = BASE_PATH . '/assets/images/logo.jpeg';
$logoUrl  = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $logoPath;

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Departure Manifest — <?= htmlspecialchars(date('d M Y', strtotime($date))) ?> — Safari Zangu Ventures</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,'DejaVu Sans',sans-serif;font-size:13px;color:#0a0a0a;background:#fff;line-height:1.5}
.page{max-width:1000px;margin:0 auto;padding:2.5rem}
.hdr{display:flex;align-items:center;justify-content:space-between;padding-bottom:1.5rem;border-bottom:2px solid #0a0a0a;margin-bottom:2rem}
.logo-img{width:56px;height:56px;border-radius:50%;object-fit:cover}
.hdr-title{font-size:1.3rem;font-weight:700;letter-spacing:.02em;text-align:right}
.hdr-sub{font-size:.75rem;color:#9ca3af;margin-top:.2rem;text-align:right}
.summary{background:#f7f7f5;border:1px solid #e5e7eb;padding:.85rem 1.25rem;margin-bottom:1.5rem;display:flex;gap:2rem;font-size:.85rem}
.summary strong{font-size:1.1rem;font-family:Georgia,serif}
table{width:100%;border-collapse:collapse}
th{font-size:.6rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#fff;background:#0a0a0a;padding:7px 8px;text-align:left}
td{padding:8px;border-bottom:1px solid #f3f4f6;font-size:.8rem;vertical-align:top}
.pax{white-space:nowrap}
.missing{color:#991b1b;font-weight:600}
.footer{text-align:center;margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid #e5e7eb;font-size:.72rem;color:#9ca3af}
@media print{body{font-size:11pt}@page{margin:1.5cm;size:landscape}.no-print{display:none}}
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
      <div style="width:56px;height:56px;border-radius:50%;background:#0a0a0a;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.65rem">SZV</div>
      <?php endif; ?>
      <div>
        <div style="font-size:1rem;font-weight:700;letter-spacing:.04em">Safari Zangu Ventures</div>
        <div style="font-size:.65rem;color:#9ca3af;letter-spacing:.1em">ARUSHA, TANZANIA</div>
      </div>
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
  <p style="color:#9ca3af;padding:2rem 0;text-align:center">No departures scheduled for this date.</p>
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
      <td<?= $b['staff_name'] ? '' : ' class="missing"' ?>><?= htmlspecialchars($b['staff_name'] ?: 'Not assigned') ?><?= $b['staff_phone'] ? '<br><span style="color:#9ca3af">'.htmlspecialchars($b['staff_phone']).'</span>' : '' ?></td>
      <td<?= $b['vehicle_name'] ? '' : ' class="missing"' ?>><?= htmlspecialchars($b['vehicle_name'] ?: 'Not assigned') ?></td>
      <td><?= htmlspecialchars($b['special_request'] ?: '—') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <div class="footer">
    Safari Zangu Ventures Ltd &middot; Njiro Road, Arusha, Tanzania &middot; +255 663 430 934 &middot; info@safarizanguven.com<br>
    Generated <?= date('d M Y, H:i') ?>
  </div>

</div>
</body>
</html>
