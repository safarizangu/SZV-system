<?php
require_once __DIR__ . '/../app/bootstrap.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) die('Invalid quotation ID.');

$db = \App\Models\Database::getInstance();
try {
    $s = $db->prepare("SELECT * FROM quotations WHERE id=?");
    $s->execute([$id]);
    $q = $s->fetch();
    if (!$q) die('Quotation not found.');
} catch(Exception $e) { die('DB error: '.$e->getMessage()); }

$lineItems   = json_decode($q['line_items'] ?? '[]', true) ?: [];
$destinations= json_decode($q['destinations'] ?? '[]', true) ?: [];
$activities  = json_decode($q['activities']   ?? '[]', true) ?: [];
$isDownload  = isset($_GET['download']);

if ($isDownload) {
    header('Content-Type: text/html; charset=UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quotation <?= htmlspecialchars($q['quote_ref']) ?> — Safari Zangu Ventures</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400&family=Jost:wght@300;400;500;600;700&display=swap');
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Jost',sans-serif;font-size:13px;color:#0a0a0a;background:#fff;line-height:1.5}
  .page{max-width:820px;margin:0 auto;padding:2.5rem}
  .header{display:grid;grid-template-columns:1fr auto;gap:2rem;align-items:start;padding-bottom:1.5rem;border-bottom:2px solid #0a0a0a;margin-bottom:2rem}
  .logo{font-family:'Playfair Display',Georgia,serif;font-size:1.75rem;font-weight:500;letter-spacing:.04em}
  .logo-sub{font-size:.6rem;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:#6b7280;margin-top:.2rem}
  .quote-meta{text-align:right}
  .quote-ref{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:500}
  .quote-date,.quote-valid{font-size:.75rem;color:#6b7280;margin-top:.2rem}
  .status-badge{display:inline-block;padding:.2rem .7rem;font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;background:#0a0a0a;color:#fff;margin-top:.4rem}
  .section{margin-bottom:1.75rem}
  .section-title{font-size:.6rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;padding-bottom:.4rem;margin-bottom:.85rem}
  .client-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
  .client-field label{display:block;font-size:.58rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#9ca3af;margin-bottom:.2rem}
  .client-field span{font-size:.88rem;font-weight:500}
  .safari-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem}
  .sf-item label{display:block;font-size:.58rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#9ca3af;margin-bottom:.2rem}
  .sf-item span{font-size:.88rem;font-weight:600}
  .items-table{width:100%;border-collapse:collapse;margin-bottom:1rem}
  .items-table th{font-size:.6rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;padding:.5rem .75rem;border-bottom:1.5px solid #0a0a0a;text-align:left}
  .items-table td{padding:.6rem .75rem;border-bottom:1px solid #e5e7eb;font-size:.82rem;vertical-align:middle}
  .items-table .amount{text-align:right;font-weight:600}
  .totals{margin-left:auto;width:320px}
  .total-row{display:flex;justify-content:space-between;align-items:center;padding:.45rem 0;border-bottom:1px solid #f3f4f6;font-size:.82rem}
  .total-row.grand{font-family:'Playfair Display',serif;font-size:1.1rem;background:#0a0a0a;color:#fff;padding:.75rem 1rem;margin-top:.5rem;border:none}
  .total-row.deposit-row{color:#166534;font-weight:700;background:#f0fdf4;padding:.45rem 1rem}
  .notes-box{border:1px solid #e5e7eb;padding:1rem;background:#f9f9f8;font-size:.82rem;line-height:1.7;white-space:pre-wrap}
  .footer-strip{margin-top:2.5rem;padding-top:1.25rem;border-top:2px solid #0a0a0a;display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;font-size:.72rem;color:#6b7280}
  .footer-strip strong{display:block;color:#0a0a0a;font-weight:600;margin-bottom:.2rem}
  .print-bar{position:fixed;bottom:0;left:0;right:0;background:#0a0a0a;color:#fff;padding:.75rem 2rem;display:flex;align-items:center;justify-content:space-between;z-index:999;font-size:.82rem}
  .print-bar button,.print-bar a{background:#fff;color:#0a0a0a;border:none;padding:.45rem 1.25rem;font-weight:700;cursor:pointer;font-size:.78rem;text-decoration:none;letter-spacing:.06em}
  @media print{
    .print-bar{display:none}
    body{background:#fff}
    .page{padding:1.5rem;max-width:100%}
    @page{size:A4;margin:15mm}
  }
</style>
</head>
<body>

<div class="page">

  <!-- Header -->
  <div class="header">
    <div>
      <div class="logo">SAFARI ZANGU VENTURES</div>
      <div class="logo-sub">Private Tanzania Safari Specialists</div>
      <div style="margin-top:.85rem;font-size:.75rem;color:#6b7280;line-height:1.8">
        Arusha, Tanzania &nbsp;·&nbsp; +255 663 430 934<br>
        info@safarizanguven.com &nbsp;·&nbsp; www.safarizanguven.com
      </div>
    </div>
    <div class="quote-meta">
      <div class="quote-ref"><?= htmlspecialchars($q['quote_ref']) ?></div>
      <div class="quote-date">Issued: <?= date('d F Y', strtotime($q['created_at'])) ?></div>
      <?php if ($q['valid_until']): ?>
      <div class="quote-valid">Valid until: <strong><?= date('d F Y', strtotime($q['valid_until'])) ?></strong></div>
      <?php endif; ?>
      <div class="status-badge"><?= strtoupper($q['status']) ?></div>
    </div>
  </div>

  <!-- Client -->
  <div class="section">
    <div class="section-title">Prepared For</div>
    <div class="client-grid">
      <div class="client-field"><label>Name</label><span><?= htmlspecialchars($q['client_name']) ?></span></div>
      <div class="client-field"><label>Email</label><span><?= htmlspecialchars($q['client_email']) ?></span></div>
      <?php if ($q['client_phone']): ?>
      <div class="client-field"><label>Phone / WhatsApp</label><span><?= htmlspecialchars($q['client_phone']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($q['client_country'])): ?>
      <div class="client-field"><label>Country</label><span><?= htmlspecialchars($q['client_country']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Safari Details -->
  <div class="section">
    <div class="section-title">Safari Details</div>
    <div class="safari-grid">
      <div class="sf-item"><label>Travelers</label>
        <span><?= $q['adults'] ?>A<?= $q['children']>0?'+'.$q['children'].'C':'' ?><?= $q['infants']>0?'+'.$q['infants'].'I':'' ?></span></div>
      <div class="sf-item"><label>Duration</label><span><?= $q['duration_days'] ?> nights</span></div>
      <div class="sf-item"><label>Start Date</label><span><?= $q['start_date'] ? date('d M Y',strtotime($q['start_date'])) : 'Flexible' ?></span></div>
      <div class="sf-item"><label>Season</label><span><?= ucfirst($q['season'] ?? 'Peak') ?></span></div>
      <?php if (!empty($destinations)): ?>
      <div class="sf-item" style="grid-column:span 2"><label>Destinations</label><span><?= htmlspecialchars(implode(' → ', $destinations)) ?></span></div>
      <?php endif; ?>
      <div class="sf-item"><label>Accommodation</label><span><?= ucfirst($q['accommodation_level'] ?? 'Mid-Range') ?></span></div>
      <div class="sf-item"><label>Meal Plan</label>
        <span><?= ['fb'=>'Full Board','hb'=>'Half Board','bb'=>'Bed & Breakfast','ai'=>'All Inclusive'][$q['meal_plan']] ?? strtoupper($q['meal_plan']) ?></span></div>
      <?php if ($q['guide_language']): ?>
      <div class="sf-item"><label>Guide Language</label><span><?= ucfirst($q['guide_language']) ?></span></div>
      <?php endif; ?>
      <?php if ($q['internal_flight']): ?>
      <div class="sf-item"><label>Internal Flight</label><span>Included</span></div>
      <?php endif; ?>
      <?php if (!empty($q['package_name'])): ?>
      <div class="sf-item" style="grid-column:span 2"><label>Package</label><span><?= htmlspecialchars($q['package_name']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($q['vehicle_name'])): ?>
      <div class="sf-item" style="grid-column:span 2"><label>Vehicle</label><span><?= htmlspecialchars($q['vehicle_name']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Line Items -->
  <?php if (!empty($lineItems)): ?>
  <div class="section">
    <div class="section-title">Cost Breakdown</div>
    <table class="items-table">
      <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th class="amount">Amount (USD)</th></tr></thead>
      <tbody>
      <?php foreach ($lineItems as $item): ?>
      <tr>
        <td><?= htmlspecialchars($item['desc'] ?? '') ?></td>
        <td><?= htmlspecialchars((string)($item['qty'] ?? 1)) ?></td>
        <td style="color:#9ca3af"><?= htmlspecialchars($item['unit'] ?? '') ?></td>
        <td class="amount">$<?= number_format((float)($item['amount'] ?? 0), 0) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
      <?php if ((float)$q['discount_pct'] > 0): ?>
      <div class="total-row"><span>Discount (<?= $q['discount_pct'] ?>%)</span><span style="color:#16a34a">-$<?= number_format($q['subtotal']*($q['discount_pct']/100),0) ?></span></div>
      <?php endif; ?>
      <div class="total-row"><span>Subtotal</span><span>$<?= number_format($q['subtotal'],0) ?></span></div>
      <div class="total-row"><span>Service Charge (5%)</span><span>$<?= number_format($q['service_charge'],0) ?></span></div>
      <div class="total-row"><span>VAT (18% Tanzania)</span><span>$<?= number_format($q['vat'],0) ?></span></div>
      <div class="total-row grand"><span>Grand Total</span><span>USD $<?= number_format($q['grand_total'],0) ?></span></div>
      <div class="total-row" style="font-size:.75rem;color:#6b7280;padding:.35rem 0">
        <span>Per Person</span><span>$<?= number_format($q['per_person'],0) ?>/pp</span>
      </div>
      <div class="total-row deposit-row"><span>Deposit Required (50%)</span><span>$<?= number_format($q['deposit_amount'],0) ?></span></div>
      <div class="total-row"><span>Balance Due</span><span>$<?= number_format($q['balance_due'],0) ?></span></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Notes -->
  <?php if (!empty($q['notes'])): ?>
  <div class="section">
    <div class="section-title">Notes &amp; Inclusions</div>
    <div class="notes-box"><?= htmlspecialchars($q['notes']) ?></div>
  </div>
  <?php endif; ?>

  <!-- Standard inclusions -->
  <div class="section">
    <div class="section-title">What's Included</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;font-size:.8rem;line-height:1.8">
      <div>✓ Airport transfers (if selected)</div>
      <div>✗ International flights</div>
      <div>✓ All listed park entry fees</div>
      <div>✗ Travel insurance</div>
      <div>✓ Professional safari guide</div>
      <div>✗ Personal expenses & tips</div>
      <div>✓ 4×4 safari vehicle with pop-up roof</div>
      <div>✗ Visa fees</div>
      <div>✓ <?= ['fb'=>'Full board meals (B+L+D)','hb'=>'Half board (B+D)','bb'=>'Bed & Breakfast','ai'=>'All inclusive meals'][$q['meal_plan']] ?? 'Meals as per meal plan' ?></div>
      <div>✗ Optional activities not listed above</div>
    </div>
  </div>

  <!-- Payment -->
  <div class="section">
    <div class="section-title">Payment Instructions</div>
    <div style="background:#f7f7f5;border-left:3px solid #0a0a0a;padding:1rem;font-size:.82rem;line-height:2">
      <strong>Bank Transfer:</strong> ABSA Bank Tanzania · <?= defined('BANK_ACCOUNT_NO') ? BANK_ACCOUNT_NO : '1234567890' ?> · SWIFT: <?= defined('BANK_SWIFT') ? BANK_SWIFT : 'BARCTZTZXXX' ?><br>
      <strong>Reference:</strong> <?= htmlspecialchars($q['quote_ref']) ?><br>
      <strong>PayPal / Wise:</strong> <?= defined('WISE_EMAIL') ? WISE_EMAIL : 'payments@safarizanguven.com' ?><br>
      <strong>Deposit Due:</strong> $<?= number_format($q['deposit_amount'],0) ?> within 48 hours of accepting this quotation.
    </div>
  </div>

  <!-- Footer -->
  <div class="footer-strip">
    <div><strong>Safari Zangu Ventures</strong>Njiro Road, Arusha<br>Tanzania, East Africa</div>
    <div><strong>Contact</strong>+255 663 430 934<br>info@safarizanguven.com</div>
    <div><strong>This Quotation</strong><?= htmlspecialchars($q['quote_ref']) ?><br>
    Valid: <?= $q['valid_until'] ? date('d M Y',strtotime($q['valid_until'])) : '14 days' ?><br>
    Prices in USD</div>
  </div>

</div><!-- /page -->

<!-- Print bar -->
<div class="print-bar">
  <span>Quotation <?= htmlspecialchars($q['quote_ref']) ?> · <?= htmlspecialchars($q['client_name']) ?> · $<?= number_format($q['grand_total'],0) ?></span>
  <div style="display:flex;gap:.5rem">
    <button onclick="window.print()">🖨 Print / Save PDF</button>
    <a href="javascript:history.back()">← Back to Admin</a>
  </div>
</div>

<script>
// Auto-trigger print if ?download=1
<?php if ($isDownload): ?>window.addEventListener('load', function(){ setTimeout(window.print, 500); });<?php endif; ?>
</script>
</body>
</html>
