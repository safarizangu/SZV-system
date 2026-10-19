<?php
/**
 * Booking Confirmation PDF
 * URL: api/booking_pdf.php?ref=SZVXXX
 * Auth: Admin session required
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
} catch (\Exception $e) { die('DB error: '.$e->getMessage()); }

$pax        = (int)($b['travelers'] ?? $b['adults'] ?? 2);
$adults     = (int)($b['adults'] ?? 2);
$children   = (int)($b['children'] ?? 0);
$total      = (float)($b['total_amount'] ?? 0);
$deposit    = (float)($b['deposit_amount'] ?? round($total * 0.25, 2));
$balance    = round($total - $deposit, 2);
$amountPaid = (float)($b['amount_paid'] ?? 0);
$realBalance = max(0, round($total - $amountPaid, 2));

// $total is the authoritative, already-charged amount (unchanged below) —
// VAT is only broken back out of it for display, the same reverse-VAT
// calc api/check_booking.php already uses, so the figure a guest is
// actually paying never moves, only how it's itemized.
$vatRate     = defined('VAT_RATE') ? VAT_RATE : 0.18;
$vatPct      = (int) round($vatRate * 100);
$totalExVat  = round($total / (1 + $vatRate), 2);
$vatAmount   = round($total - $totalExVat, 2);

// Document state — self-determined from the booking's own live
// payment_status, same as api/quotation_pdf.php, so this document
// reflects reality on every view/download, not just when a caller
// happens to pass ?invoice=1 (Track Booking's download link never did,
// so a fully-paid booking's "confirmation" PDF never actually became an
// invoice before this):
//   'quote'   — payment_status is 'pending' (or 'cancelled')
//   'partial' — 'deposit_paid' — real amount_paid vs. real balance
//   'full'    — 'fully_paid' — zero balance
$docState  = match ($b['payment_status'] ?? 'pending') {
    'fully_paid'   => 'full',
    'deposit_paid' => 'partial',
    default        => 'quote',
};
$isInvoice = $docState !== 'quote'; // kept for the spots below that only care "is this an invoice at all"

// ── Itemized cost breakdown ─────────────────────────────────
// Most bookings have no linked quotations row (it's only created when
// the booking went through the quotation-aware path in api/book.php and
// that insert succeeded) — those would otherwise show only the flat
// Total/Deposit/Balance summary below, with no line-item detail at all.
// Rebuilding it live from the booking's own resolved itinerary, the same
// way api/book.php originally priced it, gives every booking a real
// breakdown (rooms × nights, vehicle × days, etc.) instead of just the
// ones that happen to have a quotations row.
$lineItems = [];
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
    if ($pr) {
        $breakdownDays = json_decode($pr['days'] ?? '[]', true) ?: [];
        if ($breakdownDays) {
            $built     = \App\Services\ItineraryQuoteBuilder::build(['days' => $breakdownDays], $adults, $children, false, $b['travel_date'] ?: null);
            $lineItems = $built['line_items'] ?? [];
        }
    }
} catch (\Throwable $e) { $lineItems = []; }
$costSubtotal = array_sum(array_column($lineItems, 'amount'));
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
<title><?= $isInvoice ? 'Invoice' : 'Booking' ?> <?= htmlspecialchars($ref) ?> — Safari Zangu Ventures</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
/* Without this, a real browser's print/save-as-PDF engine silently
   lightens every background color to save ink — looks correct on screen
   and in dompdf's own render, but washed-out grey once actually printed.
   dompdf itself ignores the property harmlessly. */
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
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
.four-col{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem}
.field label{display:block;font-size:.56rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#0a0a0a;margin-bottom:.2rem}
.field span{font-size:.88rem;font-weight:500}
.pay-tbl{width:100%;border-collapse:collapse;border:1px solid #0a0a0a}
.pay-tbl th{font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#fff;background:#0a0a0a;padding:5px 8px;text-align:left;border:1px solid #2d2d2d}
.pay-tbl td{padding:6px 8px;border:1px solid #e5e7eb;font-size:.82rem}
.pay-tbl tbody tr:nth-child(even){background:#f9f9f8}
.pay-tbl td.r{text-align:right;font-weight:600;font-variant-numeric:tabular-nums}
/* Forces the element onto a fresh page in dompdf's print renderer, with
   the same top padding page 1 gets from .page's own padding — a div's
   own padding only wraps its content once, not per generated page. */
.page-break{page-break-before:always;padding-top:1.5rem}
.cost-tbl{width:100%;border-collapse:collapse;margin-bottom:.5rem;border:1px solid #0a0a0a}
.cost-tbl th{font-size:.58rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#fff;background:#0a0a0a;padding:.6rem .75rem;text-align:left;border:1px solid #2d2d2d}
.cost-tbl th.r{text-align:right}
.cost-tbl th.c{text-align:center}
.cost-tbl td{padding:.55rem .75rem;border:1px solid #e5e7eb;font-size:.82rem;vertical-align:middle}
.cost-tbl tbody tr:nth-child(even){background:#f9f9f8}
.cost-tbl td.r{text-align:right;font-weight:600;font-variant-numeric:tabular-nums}
.cost-tbl td.c{text-align:center;color:#0a0a0a}
.cost-subtotal{display:flex;justify-content:flex-end;gap:.75rem;padding:.5rem .75rem;font-size:.85rem;font-weight:700;border-top:2px solid #0a0a0a}
.total-block{background:#f7f7f5;border:1px solid #e5e7eb;padding:1.25rem;margin-top:1rem;border-left:4px solid #c8963e}
.grand{font-size:1.8rem;font-weight:700}
.pay-box{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:.75rem}
.pay-method{border:1px solid #e5e7eb;padding:1rem}
.pay-method h4{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;margin-bottom:.65rem;color:#0a0a0a}
.pay-method p{font-size:.8rem;line-height:1.8;color:#0a0a0a}
.pay-method strong{color:#0a0a0a}
.notice{background:#fffbeb;border-left:3px solid #c8963e;padding:.85rem 1rem;font-size:.8rem;line-height:1.65;margin-top:1.5rem;color:#0a0a0a}
.closing-seal{text-align:center;margin:2rem 0}
.closing-seal img{width:170px;height:170px;border-radius:50%;object-fit:cover;display:inline-block}
.footer{text-align:center;margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid #e5e7eb;font-size:.72rem;color:#0a0a0a}
.page-footer{position:fixed;bottom:.7rem;left:2.5rem;right:2.5rem;display:flex;justify-content:space-between;font-size:.62rem;color:#0a0a0a;letter-spacing:.04em;border-top:1px solid #e5e7eb;padding-top:.5rem}
/* counter(pages) (total page count) reliably resolves to 0 in this
   dompdf install regardless of markup, so this shows the current page
   number only, not a "page X of Y" that would always lie about Y. */
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
      <div style="font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#0a0a0a;text-align:right"><?= $isInvoice ? 'INVOICE' : '' ?></div>
      <div class="bref-num"><?= htmlspecialchars($ref) ?></div>
      <div class="bref-date">Booked <?= $bookedDt ?></div>
      <div style="text-align:right">
        <?php if ($docState === 'full'): ?>
        <span class="badge" style="background:#166534">PAID IN FULL</span>
        <?php elseif ($docState === 'partial'): ?>
        <span class="badge" style="background:#1d4ed8">DEPOSIT PAID — BALANCE DUE</span>
        <?php else: ?>
        <span class="badge"><?= strtoupper(str_replace('_',' ',$b['payment_status'] ?? 'Pending')) ?></span>
        <?php endif; ?>
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

  <!-- Cost breakdown -->
  <?php if (!empty($lineItems)): ?>
  <div class="sec page-break">
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
        <?php foreach ($lineItems as $item): ?>
        <tr>
          <td>
            <?= htmlspecialchars($item['description'] ?? '') ?>
          </td>
          <td class="r"><?= number_format((float)($item['unit_price']??0),0) ?></td>
          <td class="c"><?= htmlspecialchars($item['quantity_str'] ?? '') ?></td>
          <td class="r"><?= number_format((float)($item['amount']??0),0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="cost-subtotal">
      <span style="color:#0a0a0a;font-weight:600">SUBTOTAL</span>
      <span>USD <?= number_format($costSubtotal,2) ?></span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Payment summary -->
  <div class="sec">
    <div class="sec-title">Payment Summary</div>
    <table class="pay-tbl">
      <thead><tr><th>Description</th><th class="r">Amount (USD)</th></tr></thead>
      <tbody>
        <tr><td>Safari Cost (excl. VAT)</td><td class="r">$<?= number_format($totalExVat,2) ?></td></tr>
        <tr><td>VAT (<?= $vatPct ?>%)</td><td class="r">$<?= number_format($vatAmount,2) ?></td></tr>
        <tr><td><strong>Total Safari Cost</strong></td><td class="r"><strong>$<?= number_format($total,2) ?></strong></td></tr>
        <?php if ($docState === 'full'): ?>
        <tr><td>Amount Paid</td><td class="r">$<?= number_format($total,2) ?></td></tr>
        <tr><td><strong>Balance Due</strong></td><td class="r"><strong>$0.00</strong></td></tr>
        <?php elseif ($docState === 'partial'): ?>
        <tr><td>Amount Paid</td><td class="r">$<?= number_format($amountPaid,2) ?></td></tr>
        <tr><td><strong>Balance Due</strong> — 45 days before departure</td><td class="r"><strong>$<?= number_format($realBalance,2) ?></strong></td></tr>
        <?php else: ?>
        <tr><td>Deposit Due (25%) — payable within 48 hours to secure dates</td><td class="r">$<?= number_format($deposit,2) ?></td></tr>
        <tr><td><strong>Balance Due</strong> — 45 days before departure</td><td class="r"><strong>$<?= number_format($balance,2) ?></strong></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <div class="total-block">
      <div style="font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#0a0a0a;margin-bottom:.4rem">Grand Total</div>
      <div class="grand">$<?= number_format($total,2) ?> USD</div>
      <?php if ($pax > 0): ?>
      <div style="font-size:.72rem;color:#0a0a0a;margin-top:.3rem">$<?= number_format($total/$pax,2) ?> per person · <?= $pax ?> guest<?= $pax!==1?'s':'' ?></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($docState !== 'full'): ?>
  <!-- Payment Details — still shown for a partial payment, since a
       balance remains to pay; only dropped once fully paid. -->
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
          <span style="font-size:.72rem;color:#0a0a0a">PayPal Friends &amp; Family or use our payment link</span>
        </p>
        <?php else: ?>
        <p style="color:#0a0a0a">Contact us for PayPal payment link:<br>
          <strong>info@safarizanguven.com</strong><br>
          <strong>+255 663 430 934</strong>
        </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Notice -->
  <div class="notice">
    <strong>Your full day-by-day itinerary</strong> is attached as a separate PDF alongside this document.<br><br>
    <?php if ($docState === 'full'): ?>
    <strong>Thank you for your payment.</strong> This invoice confirms your safari is paid in full — we look forward to welcoming you.
    <?php elseif ($docState === 'partial'): ?>
    <strong>Thank you for your deposit.</strong> The remaining balance above is due 45 days before departure — see Payment Details.
    <?php else: ?>
    <strong>Cancellation Policy:</strong> Full refund for cancellations 60+ days before departure.
    50% refund for cancellations 30–59 days before departure.
    Cancellations within 30 days forfeit the deposit.
    All cancellations must be made in writing to info@safarizanguven.com.
    <?php endif; ?>
  </div>

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
