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
if (!defined('SZV_PDF_INTERNAL_RENDER') && \App\Services\OwnershipScope::isScoped()) {
    $__qid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!\App\Services\OwnershipScope::ownsQuotation(\App\Models\Database::getInstance(), $__qid, \App\Services\OwnershipScope::currentAgentId())) {
        \App\Services\AccessGuard::denyPlain('quotation', (string)$__qid, basename($_SERVER['SCRIPT_NAME']));
    }
}
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

// Document state — this same document relabels itself the moment its
// linked booking has a real payment against it, read fresh from the DB
// every time (never trusted from the URL) so it always reflects what's
// actually been paid, not a snapshot from whenever it was last opened:
//   'quote'   — no booking yet, or booking still 'pending'
//   'partial' — booking is 'deposit_paid' — an invoice showing the real,
//               admin-entered amount_paid and what's still due
//   'full'    — booking is 'fully_paid' — invoice, zero balance
$docState   = 'quote';
$amountPaid = 0.0;
if (!empty($q['booking_ref'])) {
    $bps = $db->prepare("SELECT payment_status, amount_paid FROM bookings WHERE booking_ref=? LIMIT 1");
    $bps->execute([$q['booking_ref']]);
    $bRow = $bps->fetch();
    if ($bRow) {
        $amountPaid = (float)($bRow['amount_paid'] ?? 0);
        if ($bRow['payment_status'] === 'fully_paid') $docState = 'full';
        elseif ($bRow['payment_status'] === 'deposit_paid') $docState = 'partial';
    }
}
$isInvoice = $docState !== 'quote'; // kept for the handful of spots below that only care "is this an invoice at all"

// Recalculate from line items (server authoritative)
$subtotal  = array_sum(array_column($lineItems, 'amount'));
$discount  = (float)($q['discount_pct'] ?? 0);
$discAmt   = $subtotal * ($discount / 100);
$afterDisc = $subtotal - $discAmt;
$vatRate   = defined('VAT_RATE') ? VAT_RATE : 0.18;
$vatPct    = (int) round($vatRate * 100);
$vat       = round($afterDisc * $vatRate, 2);
$grand     = round($afterDisc + $vat, 2);
$pax       = max(1, (int)($q['adults'] ?? 2) + (int)($q['children'] ?? 0));
$perPerson = round($grand / $pax, 2);
$deposit   = round($grand * 0.30, 2);
$balance   = round($grand - $deposit, 2);
$realBalance = max(0, round($grand - $amountPaid, 2));
$validStr  = $q['valid_until'] ? date('d F Y', strtotime($q['valid_until'])) : date('d F Y', strtotime('+' . QUOTATION_VALIDITY_DAYS . ' days'));
// A right-arrow glyph here previously rendered as "?" under dompdf's font
// substitution — "·" is the proven-safe choice, used throughout.
$destStr   = !empty($destinations) ? implode(' · ', $destinations) : ($q['package_name'] ?? 'Tanzania Safari');
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
<title><?= $isInvoice ? 'Invoice' : 'Quotation' ?> <?= htmlspecialchars($q['quote_ref']) ?> — SZV</title>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  /* Without this, a real browser's print/save-as-PDF engine silently
     lightens every background color (the black header bar, the gold
     brandbar, the green deposit box) to save ink — the page looks
     correct on screen and in dompdf's own render, but washed-out grey
     once actually printed. This forces backgrounds to print at their
     true color. dompdf itself ignores this property harmlessly (it
     already renders backgrounds at full color with no adjustment). */
  *,*::before,*::after{-webkit-print-color-adjust:exact;print-color-adjust:exact;color-adjust:exact}
  /* One serif face throughout, per request — Times New Roman everywhere,
     'Times'/'Liberation Serif' as same-metric fallbacks if the exact
     name isn't resolved, 'serif' last so there's always a serif result
     rather than silently falling back to a sans-serif default. */
  body{font-family:'Times New Roman',Times,'Liberation Serif',serif;font-size:13.5px;color:#0a0a0a;background:#fff;line-height:1.55}
  /* CSS @page{margin:...} was tried here to give every generated page the
     same top/bottom inset, not just the first — confirmed via direct
     testing that dompdf silently ignores it in this document (page
     content rendered flush to the physical edge regardless), so this
     reverts to .page's own padding, which is proven reliable, plus
     explicit top padding on each page-break section below. */
  .page{max-width:820px;margin:0 auto;padding:2.5rem 2.5rem 4rem}

  /* Letterhead accent — a single gold rule, the one recurring brand mark
     carried through the document */
  .brandbar{height:4px;background:#c8963e;margin:-2.5rem -2.5rem 2rem}

  /* Header */
  .hdr{display:grid;grid-template-columns:1fr auto;gap:2rem;align-items:start;
        padding-bottom:1.5rem;border-bottom:2px solid #0a0a0a;margin-bottom:2rem}
  /* The closing seal on the last page — larger still, for visibility,
     centered as a closing mark. */
  .closing-seal{text-align:center;margin:2rem 0}
  .closing-seal img{width:170px;height:170px;border-radius:50%;object-fit:cover;display:inline-block}
  .company-name{font-size:1.9rem;font-weight:700;letter-spacing:.02em}
  .logo-sub{font-size:.6rem;font-weight:600;letter-spacing:.18em;text-transform:uppercase;color:#0a0a0a;margin-top:.3rem}
  .logo-meta{margin-top:.6rem;font-size:.68rem;color:#0a0a0a;letter-spacing:.02em}
  .qref{text-align:right}
  .qref-eyebrow{font-size:.6rem;font-weight:700;letter-spacing:.24em;text-transform:uppercase;color:#0a0a0a}
  .qref-num{font-size:1.5rem;font-weight:700;margin-top:.15rem}
  .qref-date{font-size:.74rem;color:#0a0a0a;margin-top:.3rem}
  .qref-date strong{color:#0a0a0a}
  .badge{display:inline-block;padding:.25rem .75rem;font-size:.6rem;font-weight:700;
         letter-spacing:.16em;text-transform:uppercase;background:#0a0a0a;color:#fff;margin-top:.55rem}

  /* Section */
  .sec{margin-bottom:1.85rem}
  .sec-title{display:flex;align-items:center;gap:.5rem;font-size:.64rem;font-weight:700;letter-spacing:.2em;
             text-transform:uppercase;color:#0a0a0a;border-bottom:1px solid #e5e7eb;
             padding-bottom:.5rem;margin-bottom:1rem}
  .sec-title::before{content:'';width:10px;height:2px;background:#c8963e;flex-shrink:0}

  /* Client / safari grid — soft card framing instead of bare label/value
     pairs, so each group of facts reads as one object */
  .info-card{border:1px solid #e5e7eb;background:#f9f9f8;padding:1.1rem 1.25rem;page-break-inside:avoid}
  .two-col{display:grid;grid-template-columns:1fr 1fr;gap:1rem 1.5rem}
  .four-col{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem 1.25rem}
  .field label{display:block;font-size:.58rem;font-weight:700;letter-spacing:.14em;
               text-transform:uppercase;color:#0a0a0a;margin-bottom:.25rem}
  .field span{font-size:.9rem;font-weight:500;color:#0a0a0a}

  /* Cost table — zebra striping replaces per-category color chips; the
     category text itself (Acc/Veh/Park…) already carries the distinction,
     so the color was decoration without information */
  .cost-tbl{width:100%;border-collapse:collapse;margin-bottom:1.1rem;border:1px solid #0a0a0a}
  .cost-tbl th{font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
               color:#fff;background:#0a0a0a;padding:.7rem .8rem;text-align:left;border:1px solid #2d2d2d}
  .cost-tbl th.r{text-align:right}
  .cost-tbl th.c{text-align:center}
  .cost-tbl td{padding:.6rem .8rem;border:1px solid #e5e7eb;font-size:.84rem;vertical-align:middle}
  .cost-tbl tbody tr:nth-child(even){background:#f9f9f8}
  .cost-tbl td.r{text-align:right;font-weight:600;font-variant-numeric:tabular-nums}
  .cost-tbl td.c{text-align:center;color:#0a0a0a}

  /* Forces the element onto a fresh page in dompdf's print renderer */
  /* Forces a fresh page AND gives that page's content the same breathing
     room page 1 gets from .page's own top padding — without this, a
     page-break-before section starts flush against the physical page
     edge, since .page's padding only wraps the document once, not per
     generated page. */
  .page-break{page-break-before:always;padding-top:1.5rem}

  /* The last page's own content column, tall enough to span the page,
     so .footer (position:absolute below) anchors flush against the
     bottom of the page instead of sitting wherever the content above it
     happens to end, with empty space beneath it. flexbox's margin-top:auto
     was tried first for this and confirmed (by direct render) to be
     silently ignored by dompdf's flex implementation — absolute
     positioning is the technique already proven reliable in this same
     document for .page-footer and .brandbar. */
  .last-page{position:relative;min-height:950px}

  /* Totals */
  .totals{margin-left:auto;width:340px}
  .tot-row{display:flex;justify-content:space-between;padding:.5rem 0;
           border-bottom:1px solid #f3f4f6;font-size:.86rem;font-variant-numeric:tabular-nums}
  .tot-row .lbl{color:#0a0a0a}
  .tot-row.disc .val{color:#0a0a0a;font-weight:700}
  .tot-row.vat-row{border-bottom:2px solid #0a0a0a}
  .grand-row{display:flex;justify-content:space-between;align-items:center;
             background:#0a0a0a;color:#fff;padding:.95rem 1.1rem;margin:.5rem 0;
             border-left:4px solid #c8963e}
  .grand-row .lbl{font-weight:700;font-size:.78rem;letter-spacing:.1em;text-transform:uppercase}
  .grand-row .val{font-size:1.35rem;font-weight:700;font-variant-numeric:tabular-nums}
  .pp-row{display:flex;justify-content:space-between;padding:.45rem 0;
          font-size:.86rem;border-bottom:1px solid #f3f4f6;font-variant-numeric:tabular-nums}
  .pp-row .val{font-weight:700;font-size:.96rem}
  .deposit-box{background:#f0fdf4;border:1px solid #86efac;padding:.9rem 1.1rem;margin-top:.85rem}
  .deposit-box .title{font-size:.6rem;font-weight:700;letter-spacing:.14em;
                       text-transform:uppercase;color:#0a0a0a;margin-bottom:.5rem}
  .dep-row{display:flex;justify-content:space-between;gap:.5rem;font-size:.8rem;color:#0a0a0a;margin-bottom:.3rem;
            font-variant-numeric:tabular-nums}
  .dep-row .val{font-weight:700;white-space:nowrap;margin-left:.75rem}
  .payment-box{border:1px solid #e5e7eb;padding:1rem 1.1rem;background:#f9f9f8;
               border-left:3px solid #c8963e}
  .payment-row{display:flex;justify-content:space-between;font-size:.86rem;color:#0a0a0a;
               padding:.4rem 0;border-bottom:1px solid #e5e7eb}
  .payment-row:last-child{border-bottom:none}
  .payment-row strong{color:#0a0a0a;font-weight:600;margin-left:.75rem;white-space:nowrap}

  /* Notes */
  .notes-box{border:1px solid #e5e7eb;padding:1.1rem 1.25rem;background:#f9f9f8;
             font-size:.84rem;line-height:1.8;white-space:pre-wrap}

  /* Footer — normal in-flow block by default (the Invoice-mode path never
     wraps this in .last-page, so it must still lay out correctly on its
     own); anchored to the bottom of .last-page specifically when present. */
  .footer{margin-top:2.5rem;padding-top:1.35rem;border-top:2px solid #0a0a0a;
          display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;font-size:.74rem;color:#0a0a0a}
  .last-page .footer{position:absolute;left:0;right:0;bottom:0;margin-top:0}
  .footer strong{display:block;color:#0a0a0a;font-weight:600;margin-bottom:.2rem;
                 font-size:.6rem;letter-spacing:.1em;text-transform:uppercase}

  /* Running page-number strip — repeats on every page via dompdf's
     position:fixed support, so a multi-page document never loses its
     place once printed loose */
  .page-footer{position:fixed;bottom:.7rem;left:2.5rem;right:2.5rem;
               display:flex;justify-content:space-between;font-size:.62rem;
               color:#0a0a0a;letter-spacing:.04em;border-top:1px solid #e5e7eb;padding-top:.5rem}
  /* counter(pages) (the document's total page count) reliably resolves
     to 0 in this dompdf install regardless of markup — confirmed by
     direct test — so this shows the current page number only, not a
     "page X of Y" that would always lie about Y. */
  .page-footer .pg:after{content:"Page " counter(page)}

  /* Print */
  @media print {
    body{background:#fff}
    .no-print{display:none}
    .page{padding:1.5rem 1.5rem 3.5rem}
    .page-footer{left:1.5rem;right:1.5rem}
  }
</style>
</head>
<body>
<!-- Running footer — a direct child of <body>, not nested inside .page,
     because dompdf only reliably repaints position:fixed elements on
     every generated page when they sit outside any padded/margined
     ancestor block; nested one page inside .page, it silently stopped
     repeating past page 1. -->
<div class="page-footer">
  <span>Safari Zangu Ventures · <?= htmlspecialchars($q['quote_ref']) ?></span>
  <span class="pg"></span>
</div>

<div class="page">
<div class="brandbar"></div>

<!-- ── HEADER ─────────────────────────────────────────────── -->
<div class="hdr">
  <div>
    <div class="company-name">SZV</div>
    <div class="logo-sub">Private Tanzania Safari Specialists · Arusha</div>
    <div class="logo-meta">TALA Licence No. 037129 · Reg. No. 179228971</div>
  </div>
  <div class="qref">
    <div class="qref-eyebrow"><?= $isInvoice ? 'Invoice' : 'Quotation' ?></div>
    <div class="qref-num"><?= htmlspecialchars($q['quote_ref']) ?></div>
    <?php if ($docState === 'full'): ?>
    <div class="qref-date">Issued <strong><?= date('d F Y') ?></strong></div>
    <div class="badge" style="background:#166534">Paid in Full</div>
    <?php elseif ($docState === 'partial'): ?>
    <div class="qref-date">Issued <strong><?= date('d F Y') ?></strong></div>
    <div class="badge" style="background:#1d4ed8">Deposit Paid — Balance Due</div>
    <?php else: ?>
    <div class="qref-date">Issued <strong><?= date('d F Y') ?></strong></div>
    <div class="qref-date">Valid until <strong><?= $validStr ?></strong></div>
    <div class="badge"><?= strtoupper($q['status'] ?? 'draft') ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- ── CLIENT ─────────────────────────────────────────────── -->
<div class="sec">
  <div class="sec-title">Client Details</div>
  <div class="info-card two-col">
    <div class="field"><label>Name</label><span><?= htmlspecialchars($q['client_name']) ?></span></div>
    <div class="field"><label>Email</label><span><?= htmlspecialchars($q['client_email']) ?></span></div>
    <div class="field"><label>Phone / WhatsApp</label><span><?= htmlspecialchars($q['client_phone'] ?: '—') ?></span></div>
    <div class="field"><label>Country</label><span><?= htmlspecialchars($q['client_country'] ?: '—') ?></span></div>
  </div>
</div>

<!-- ── SAFARI SUMMARY ─────────────────────────────────────── -->
<div class="sec">
  <div class="sec-title">Safari Summary</div>
  <div class="info-card four-col">
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
      <?php foreach($lineItems as $item):
        $amount = (float)($item['amount'] ?? 0);
      ?>
      <tr>
        <td>
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
      <span class="lbl">VAT (<?= $vatPct ?>%)</span>
      <span style="font-weight:600"><?= number_format($vat,2) ?></span>
    </div>
    <div class="grand-row">
      <span class="lbl">GRAND TOTAL</span>
      <span class="val">USD <?= number_format($grand,2) ?></span>
    </div>
    <div class="pp-row">
      <span style="color:#0a0a0a">USD per person (<?= $pax ?> guest<?= $pax!==1?'s':'' ?>)</span>
      <span class="val">USD <?= number_format($perPerson,2) ?></span>
    </div>
    <?php if ($docState === 'full'): ?>
    <div class="deposit-box">
      <div class="title">Payment Status</div>
      <div class="dep-row"><span>Amount Paid</span><span class="val">USD <?= number_format($grand,2) ?></span></div>
      <div class="dep-row"><span>Balance Due</span><span class="val">USD 0.00</span></div>
    </div>
    <?php elseif ($docState === 'partial'): ?>
    <div class="deposit-box" style="background:#eff6ff;border-color:#bfdbfe">
      <div class="title">Payment Status</div>
      <div class="dep-row"><span>Amount Paid</span><span class="val">USD <?= number_format($amountPaid,2) ?></span></div>
      <div class="dep-row"><span>Balance Due</span><span class="val">USD <?= number_format($realBalance,2) ?></span></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($docState !== 'full'): ?>
<!-- ── PAYMENT DETAILS — always its own final page, and the whole
     page is a flex column so .footer is pushed down to sit flush
     against the bottom of the page rather than floating wherever the
     content above it happens to end. ─────────────────────────────── -->
<div class="last-page page-break">
<div class="sec">
  <!-- "To Confirm Your Booking" sits directly above Payment Details —
       the deposit amount and the bank details needed to actually pay it
       now read as one combined instruction, instead of being split
       across two different pages. For a partial payment, this becomes
       "Balance Still Due" against the real amount already received,
       instead of restating a deposit that's already been paid. -->
  <div class="deposit-box" style="margin-top:0;margin-bottom:1.5rem">
    <?php if ($docState === 'partial'): ?>
    <div class="title">Balance Still Due</div>
    <div class="dep-row"><span>Received so far</span><span class="val">USD <?= number_format($amountPaid,2) ?></span></div>
    <div class="dep-row"><span>Balance — due 45 days before safari</span><span class="val">USD <?= number_format($realBalance,2) ?></span></div>
    <?php else: ?>
    <div class="title">To Confirm Your Booking</div>
    <div class="dep-row"><span>Deposit (30%) — due within 48 hours</span><span class="val">USD <?= number_format($deposit,2) ?></span></div>
    <div class="dep-row"><span>Balance (70%) — due 45 days before safari</span><span class="val">USD <?= number_format($balance,2) ?></span></div>
    <?php endif; ?>
  </div>
  <div class="sec-title">Payment Details</div>
  <div class="payment-box" style="margin-top:0">
    <div class="payment-row"><span>Bank</span><strong><?= htmlspecialchars($bankName) ?></strong></div>
    <div class="payment-row"><span>Account Name</span><strong><?= htmlspecialchars($bankAcc) ?></strong></div>
    <div class="payment-row"><span>Account Number</span><strong><?= htmlspecialchars($bankNo) ?></strong></div>
    <?php if ($bankSwift): ?><div class="payment-row"><span>SWIFT</span><strong><?= htmlspecialchars($bankSwift) ?></strong></div><?php endif; ?>
    <div class="payment-row"><span>PayPal</span><strong><?= htmlspecialchars($paypalEmail) ?></strong></div>
  </div>
</div>
<?php endif; ?>

<div class="sec" style="font-size:.8rem;color:#0a0a0a;background:#f9f9f8;border:1px solid #e5e7eb;padding:.85rem 1rem">
  Your full day-by-day itinerary is attached as a separate PDF alongside this quotation.
</div>

<!-- Closing seal — the logo moved here from the header (shortened to
     "SZV" there instead), larger and centered as a closing mark rather
     than a small corner icon. -->
<div class="closing-seal">
  <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Safari Zangu Ventures">
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
    <?php if ($docState === 'full'): ?>
    Paid in full — thank you for<br>travelling with Safari Zangu Ventures.
    <?php elseif ($docState === 'partial'): ?>
    Balance due — see Payment Details above.<br>
    Thank you for your deposit.
    <?php else: ?>
    Bank transfer or PayPal — see Payment Details above.<br>
    This quotation is valid until <?= $validStr ?>
    <?php endif; ?>
  </div>
</div>
<?php if ($docState !== 'full'): ?></div><!-- /last-page --><?php endif; ?>

<?php if (!defined('SZV_PDF_INTERNAL_RENDER')): ?>
<!-- Print button — only for an admin viewing this page live in a browser.
     Omitted when PdfRenderer captures this same markup to bake into the
     actual PDF file that gets emailed/downloaded, where an on-page
     "Print"/"Back" button is meaningless and was previously shipping to
     every client inside the real document. -->
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
<?php endif; ?>

</div><!-- /page -->
</body></html>
