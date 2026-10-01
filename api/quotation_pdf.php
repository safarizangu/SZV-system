<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;

/**
 * Quotation PDF — matches SZV Quotation PDF format exactly:
 * Description | Unit Price (USD) | Quantity | Amount (USD)
 * TOTAL → VAT(18%) → GRAND TOTAL → Per Person
 *
 * Auth is skipped only when included internally by trusted server-side
 * code with SZV_PDF_INTERNAL_RENDER already defined (see
 * App\Services\QuotationPdfRenderer) — never settable via request input.
 * Every real HTTP request still requires an admin session.
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

$lineItems    = json_decode($q['line_items']    ?? '[]', true) ?: [];
$destinations = json_decode($q['destinations']  ?? '[]', true) ?: [];
$isDownload   = isset($_GET['download']);

// Invoice mode — same document, relabeled once the linked booking is
// actually fully paid. The ?invoice=1 flag only ever REQUESTS this; the
// real payment status is looked up fresh so the URL itself can't be used
// to misrepresent a booking as paid when it isn't.
$isInvoice = false;
if (isset($_GET['invoice']) && $_GET['invoice'] == '1' && !empty($q['booking_ref'])) {
    $bps = $db->prepare("SELECT payment_status FROM bookings WHERE booking_ref=? LIMIT 1");
    $bps->execute([$q['booking_ref']]);
    $isInvoice = $bps->fetchColumn() === 'fully_paid';
}

// Recalculate from line items (server authoritative)
$subtotal  = array_sum(array_column($lineItems, 'amount'));
$discount  = (float)($q['discount_pct'] ?? 0);
$discAmt   = $subtotal * ($discount / 100);
$afterDisc = $subtotal - $discAmt;
$vat       = round($afterDisc * 0.18, 2);
$grand     = round($afterDisc + $vat, 2);
$pax       = max(1, (int)($q['adults'] ?? 2) + (int)($q['children'] ?? 0));
$perPerson = round($grand / $pax, 2);
$deposit   = round($grand * 0.30, 2);
$balance   = round($grand - $deposit, 2);
$validStr  = $q['valid_until'] ? date('d F Y', strtotime($q['valid_until'])) : date('d F Y', strtotime('+14 days'));
$destStr   = !empty($destinations) ? implode(' → ', $destinations) : ($q['package_name'] ?? 'Tanzania Safari');
$logoUrl = (defined('APP_URL') ? APP_URL : 'https://www.safarizanguven.com') . '/assets/images/logo.jpeg';
$bankName    = defined('BANK_NAME')         ? BANK_NAME         : 'Your Bank';
$bankAcc     = defined('BANK_ACCOUNT_NAME') ? BANK_ACCOUNT_NAME : 'Safari Zangu Ventures';
$bankNo      = defined('BANK_ACCOUNT_NO')   ? BANK_ACCOUNT_NO   : 'Contact us for details';
$bankSwift   = defined('BANK_SWIFT')        ? BANK_SWIFT        : '';
$paypalEmail = defined('PAYPAL_EMAIL')      ? PAYPAL_EMAIL      : 'Contact us';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $isInvoice ? 'Invoice' : 'Quotation' ?> <?= htmlspecialchars($q['quote_ref']) ?> — Safari Zangu Ventures</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Jost:wght@300;400;500;600;700&display=swap');
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  /* 'DejaVu Sans' is dompdf's own bundled font, kept as a fallback because
     dompdf can't load the Jost @import above (stripped by
     App\Services\PdfRenderer to avoid a font-cache write failure) — without
     it, glyphs like → and ✦ silently render as "?" instead of erroring. */
  body{font-family:'Jost','DejaVu Sans',Arial,sans-serif;font-size:13px;color:#0a0a0a;background:#fff;line-height:1.5}
  .page{max-width:820px;margin:0 auto;padding:2.5rem}

  /* Header */
  .hdr{display:grid;grid-template-columns:1fr auto;gap:2rem;align-items:start;
        padding-bottom:1.5rem;border-bottom:2px solid #0a0a0a;margin-bottom:2rem}
  .logo{font-family:'Playfair Display',Georgia,serif;font-size:1.8rem;font-weight:500;letter-spacing:.04em}
  .logo-img{width:64px;height:64px;border-radius:50%;object-fit:cover;margin-bottom:.4rem;display:block}
  .logo-sub{font-size:.58rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#9ca3af;margin-top:.3rem}
  .qref{text-align:right}
  .qref-num{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:500}
  .qref-date{font-size:.72rem;color:#9ca3af;margin-top:.2rem}
  .badge{display:inline-block;padding:.2rem .65rem;font-size:.58rem;font-weight:700;
         letter-spacing:.14em;text-transform:uppercase;background:#0a0a0a;color:#fff;margin-top:.4rem}

  /* Section */
  .sec{margin-bottom:1.75rem}
  .sec-title{font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;
             color:#9ca3af;border-bottom:1px solid #e5e7eb;padding-bottom:.4rem;margin-bottom:.85rem}

  /* Client / safari grid */
  .two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
  .four-col{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem}
  .field label{display:block;font-size:.56rem;font-weight:700;letter-spacing:.15em;
               text-transform:uppercase;color:#9ca3af;margin-bottom:.2rem}
  .field span{font-size:.88rem;font-weight:500}

  /* Cost table — matches PDF */
  .cost-tbl{width:100%;border-collapse:collapse;margin-bottom:1rem}
  .cost-tbl th{font-size:.58rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
               color:#fff;background:#0a0a0a;padding:.6rem .75rem;text-align:left}
  .cost-tbl th.r{text-align:right}
  .cost-tbl th.c{text-align:center}
  .cost-tbl td{padding:.55rem .75rem;border-bottom:1px solid #f3f4f6;font-size:.82rem;vertical-align:middle}
  .cost-tbl td.r{text-align:right;font-weight:600}
  .cost-tbl td.c{text-align:center;color:#6b7280}
  .cost-tbl tr:last-child td{border-bottom:none}
  .cost-tbl .cat-badge{font-size:.56rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
                        padding:.12rem .35rem;margin-right:.4rem;opacity:.7}

  /* Totals */
  .totals{margin-left:auto;width:340px}
  .tot-row{display:flex;justify-content:space-between;padding:.45rem 0;
           border-bottom:1px solid #f3f4f6;font-size:.85rem}
  .tot-row .lbl{color:#6b7280}
  .tot-row.disc .val{color:#16a34a;font-weight:700}
  .tot-row.vat-row{border-bottom:2px solid #0a0a0a}
  .grand-row{display:flex;justify-content:space-between;align-items:center;
             background:#0a0a0a;color:#fff;padding:.85rem 1rem;margin:.4rem 0}
  .grand-row .lbl{font-weight:700;font-size:.95rem}
  .grand-row .val{font-family:'Playfair Display',serif;font-size:1.25rem}
  .pp-row{display:flex;justify-content:space-between;padding:.4rem 0;
          font-size:.85rem;border-bottom:1px solid #f3f4f6}
  .pp-row .val{font-weight:700;font-size:.95rem}
  .deposit-box{background:#f0fdf4;border:1px solid #86efac;padding:.85rem 1rem;margin-top:.75rem}
  .deposit-box .title{font-size:.58rem;font-weight:700;letter-spacing:.14em;
                       text-transform:uppercase;color:#166534;margin-bottom:.4rem}
  .dep-row{display:flex;justify-content:space-between;font-size:.82rem;color:#166534;margin-bottom:.2rem}
  .dep-row .val{font-weight:700}
  .payment-box{border:1px solid #e5e7eb;padding:.85rem 1rem;margin-top:.75rem;background:#f9f9f8}
  .payment-box .title{font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#6b7280;margin-bottom:.5rem}
  .payment-row{font-size:.82rem;color:#374151;line-height:1.8}
  .payment-row strong{color:#0a0a0a}

  /* Notes */
  .notes-box{border:1px solid #e5e7eb;padding:1rem;background:#f9f9f8;
             font-size:.82rem;line-height:1.75;white-space:pre-wrap}

  /* Footer */
  .footer{margin-top:2.5rem;padding-top:1.25rem;border-top:2px solid #0a0a0a;
          display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;font-size:.72rem;color:#6b7280}
  .footer strong{display:block;color:#0a0a0a;font-weight:600;margin-bottom:.15rem}

  /* Print */
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
    <div style="font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af"><?= $isInvoice ? 'INVOICE' : 'QUOTATION' ?></div>
    <div class="qref-num"><?= htmlspecialchars($q['quote_ref']) ?></div>
    <?php if ($isInvoice): ?>
    <div class="qref-date">Issued: <?= date('d F Y') ?></div>
    <div class="badge" style="background:#166534">PAID IN FULL</div>
    <?php else: ?>
    <div class="qref-date">Issued: <?= date('d F Y') ?></div>
    <div class="qref-date">Valid until: <strong><?= $validStr ?></strong></div>
    <div class="badge"><?= strtoupper($q['status'] ?? 'draft') ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- ── CLIENT ─────────────────────────────────────────────── -->
<div class="sec">
  <div class="sec-title">Client Details</div>
  <div class="two-col">
    <div class="field"><label>Name</label><span><?= htmlspecialchars($q['client_name']) ?></span></div>
    <div class="field"><label>Email</label><span><?= htmlspecialchars($q['client_email']) ?></span></div>
    <div class="field"><label>Phone / WhatsApp</label><span><?= htmlspecialchars($q['client_phone'] ?: '—') ?></span></div>
    <div class="field"><label>Country</label><span><?= htmlspecialchars($q['client_country'] ?: '—') ?></span></div>
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
    <div class="field"><label>Accommodation</label>
      <span><?= ['budget'=>'Budget-Friendly','midrange'=>'Mid-Range','highend'=>'High-End / Luxury','ultra_luxury'=>'Ultra Luxury'][$q['accommodation_level']??'midrange'] ?? 'Mid-Range' ?></span></div>
    <div class="field"><label>Meal Plan</label>
      <span><?= ['fb'=>'Full Board','hb'=>'Half Board','bb'=>'Bed & Breakfast','ai'=>'All Inclusive'][$q['meal_plan']??'fb'] ?? 'Full Board' ?></span></div>
    <div class="field"><label>Guide Language</label>
      <span><?= ucfirst($q['guide_language'] ?? 'English') ?></span></div>
    <div class="field"><label>Package</label>
      <span><?= htmlspecialchars($q['package_name'] ?: '—') ?></span></div>
  </div>
</div>

<!-- ── COST BREAKDOWN TABLE — matches PDF exactly ────────── -->
<div class="sec">
  <div class="sec-title">Cost Breakdown</div>
  <table class="cost-tbl">
    <thead>
      <tr>
        <th>Description</th>
        <th class="r">Unit Price (USD)</th>
        <th class="c">Quantity</th>
        <th class="r">Amount (USD)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($lineItems as $item):
        $catColors = [
          'accommodation'=>['#eff6ff','#1d4ed8'],
          'transport'    =>['#f0fdf4','#166534'],
          'park_fee'     =>['#fef3e2','#92400e'],
          'activity'     =>['#fdf4ff','#7e22ce'],
          'flight'       =>['#f0f9ff','#0369a1'],
          'service_fee'  =>['#f9fafb','#374151'],
          'other'        =>['#f9fafb','#6b7280'],
        ];
        $catBg  = $catColors[$item['category']??'other'][0] ?? '#f9fafb';
        $catCol = $catColors[$item['category']??'other'][1] ?? '#6b7280';
        $catLabel = ['accommodation'=>'Acc','transport'=>'Veh','park_fee'=>'Park',
                     'activity'=>'Act','flight'=>'Flight','service_fee'=>'Svc','other'=>''][$item['category']??'other'] ?? '';
        $amount = (float)($item['amount'] ?? 0);
      ?>
      <tr>
        <td>
          <?php if($catLabel): ?>
          <span class="cat-badge" style="background:<?= $catBg ?>;color:<?= $catCol ?>"><?= $catLabel ?></span>
          <?php endif; ?>
          <?= htmlspecialchars($item['description'] ?? '') ?>
        </td>
        <td class="r">
          <?= number_format((float)($item['unit_price']??0),0) ?>
        </td>
        <td class="c">
          <?= htmlspecialchars($item['quantity_str'] ?? '') ?>
        </td>
        <td class="r"><?= number_format($amount,0) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totals — matches PDF layout -->
  <div class="totals">
    <div class="tot-row">
      <span class="lbl">TOTAL</span>
      <span style="font-weight:600">USD <?= number_format($subtotal,2) ?></span>
    </div>
    <?php if($discount > 0): ?>
    <div class="tot-row disc">
      <span class="lbl">Discount (<?= $discount ?>%)</span>
      <span class="val">–<?= number_format($discAmt,2) ?></span>
    </div>
    <?php endif; ?>
    <div class="tot-row vat-row">
      <span class="lbl">VAT (18%)</span>
      <span style="font-weight:600"><?= number_format($vat,2) ?></span>
    </div>
    <div class="grand-row">
      <span class="lbl">GRAND TOTAL</span>
      <span class="val">USD <?= number_format($grand,2) ?></span>
    </div>
    <div class="pp-row">
      <span style="color:#6b7280">USD per person (<?= $pax ?> guest<?= $pax!==1?'s':'' ?>)</span>
      <span class="val">USD <?= number_format($perPerson,2) ?></span>
    </div>
    <?php if ($isInvoice): ?>
    <div class="deposit-box">
      <div class="title">Payment Status</div>
      <div class="dep-row"><span>Amount Paid</span><span class="val">USD <?= number_format($grand,2) ?></span></div>
      <div class="dep-row"><span>Balance Due</span><span class="val">USD 0.00</span></div>
    </div>
    <?php else: ?>
    <div class="deposit-box">
      <div class="title">To Confirm Your Booking</div>
      <div class="dep-row"><span>Deposit (30%) — due within 48 hours</span><span class="val">USD <?= number_format($deposit,2) ?></span></div>
      <div class="dep-row"><span>Balance (70%) — due 45 days before safari</span><span class="val">USD <?= number_format($balance,2) ?></span></div>
    </div>
    <div class="payment-box">
      <div class="title">Payment Details</div>
      <div class="payment-row">Bank: <strong><?= htmlspecialchars($bankName) ?></strong></div>
      <div class="payment-row">Account Name: <strong><?= htmlspecialchars($bankAcc) ?></strong></div>
      <div class="payment-row">Account Number: <strong><?= htmlspecialchars($bankNo) ?></strong></div>
      <?php if ($bankSwift): ?><div class="payment-row">SWIFT: <strong><?= htmlspecialchars($bankSwift) ?></strong></div><?php endif; ?>
      <div class="payment-row">PayPal: <strong><?= htmlspecialchars($paypalEmail) ?></strong></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="sec" style="font-size:.8rem;color:#6b7280;background:#f9f9f8;border:1px solid #e5e7eb;padding:.85rem 1rem">
  Your full day-by-day itinerary is attached as a separate PDF alongside this quotation.
</div>

<?php if(!empty($q['notes'])): ?>
<!-- ── NOTES ─────────────────────────────────────────────── -->
<div class="sec">
  <div class="sec-title">Notes</div>
  <div class="notes-box"><?= htmlspecialchars($q['notes']) ?></div>
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
    <strong>Payment</strong>
    <?php if ($isInvoice): ?>
    Paid in full — thank you for<br>travelling with Safari Zangu Ventures.
    <?php else: ?>
    Bank transfer or PayPal — see Payment Details above.<br>
    This quotation is valid until <?= $validStr ?>
    <?php endif; ?>
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
