<?php
/**
 * php/quotation_email.php
 * Send a quotation by email to the client via PHPMailer.
 * Called from admin/quotations.php (POST form) or admin/quotation_new.php.
 */
ob_start();
require_once __DIR__ . '/config.php';

use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_PATH . '/admin/quotations.php?msg=' . urlencode('No quotation ID.'));
    exit;
}

$db = \App\Models\Database::getInstance();
try {
    $s = $db->prepare("SELECT * FROM quotations WHERE id=?");
    $s->execute([$id]);
    $q = $s->fetch();
    if (!$q) {
        header('Location: ' . BASE_PATH . '/admin/quotations.php?msg=' . urlencode('Quotation not found.'));
        exit;
    }
} catch (Exception $e) {
    header('Location: ' . BASE_PATH . '/admin/quotations.php?msg=' . urlencode('DB error: ' . $e->getMessage()));
    exit;
}

$lineItems    = json_decode($q['line_items']   ?? '[]', true) ?: [];
$destinations = json_decode($q['destinations'] ?? '[]', true) ?: [];
$destStr      = !empty($destinations) ? implode(' → ', $destinations) : 'Tanzania Safari';
$validStr     = $q['valid_until']
    ? date('d F Y', strtotime($q['valid_until']))
    : date('d F Y', strtotime('+14 days'));

// ── Build line items HTML ─────────────────────────────────────
$lineItemsHtml = '';
foreach ($lineItems as $item) {
    $lineItemsHtml .= '<tr>
        <td style="padding:.5rem .75rem;border-bottom:1px solid #f3f4f6;font-size:.85rem">'
            . htmlspecialchars($item['desc'] ?? '') . '</td>
        <td style="padding:.5rem .75rem;border-bottom:1px solid #f3f4f6;text-align:center;font-size:.82rem">'
            . htmlspecialchars((string)($item['qty'] ?? '')) . '</td>
        <td style="padding:.5rem .75rem;border-bottom:1px solid #f3f4f6;text-align:right;font-weight:600;font-size:.85rem">$'
            . number_format((float)($item['amount'] ?? 0), 0) . '</td>
    </tr>';
}

// ── Email body ────────────────────────────────────────────────
$wa      = defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '255663430934';
$waLink  = 'https://wa.me/' . $wa . '?text=' . urlencode('Hi, I would like to accept quotation ' . $q['quote_ref'] . '.');
$replyTo = defined('SITE_EMAIL') ? SITE_EMAIL : 'info@safarizanguven.com';

$emailBody = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#0a0a0a;margin:0;padding:0;background:#f3f3f1">
<div style="max-width:620px;margin:2rem auto;background:#fff">

  <!-- Header -->
  <div style="background:#0a0a0a;padding:2rem 2.5rem">
    <div style="font-family:Georgia,serif;font-size:1.4rem;font-weight:400;color:#fff;letter-spacing:.04em">SAFARI ZANGU VENTURES</div>
    <div style="font-size:.65rem;font-weight:600;letter-spacing:.22em;text-transform:uppercase;color:rgba(255,255,255,.45);margin-top:.3rem">Private Tanzania Safari Specialists · Arusha</div>
  </div>

  <!-- Body -->
  <div style="padding:2rem 2.5rem">

    <p style="font-size:1rem;margin:0 0 1rem">Dear ' . htmlspecialchars($q['client_name']) . ',</p>
    <p style="line-height:1.75;color:#333;margin:0 0 1.75rem">
      Thank you for considering Safari Zangu Ventures for your Tanzania safari.
      Please find your personalised quotation below. We have carefully crafted
      this proposal based on your requirements.
    </p>

    <!-- Reference box -->
    <div style="background:#f7f7f5;border-left:4px solid #0a0a0a;padding:1rem 1.25rem;margin-bottom:1.75rem">
      <div style="font-size:.58rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#9ca3af;margin-bottom:.35rem">Quotation Reference</div>
      <div style="font-family:Georgia,serif;font-size:1.5rem;font-weight:500">' . htmlspecialchars($q['quote_ref']) . '</div>
      <div style="font-size:.78rem;color:#6b7280;margin-top:.3rem">Valid until: <strong>' . $validStr . '</strong></div>
    </div>

    <!-- Safari summary -->
    <div style="font-size:.6rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem;margin-bottom:.85rem">Safari Summary</div>
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;margin-bottom:1.75rem">
      <tr><td style="padding:.4rem 0;color:#6b7280;width:38%">Destination</td><td style="font-weight:600">' . htmlspecialchars($destStr) . '</td></tr>
      <tr><td style="padding:.4rem 0;color:#6b7280">Duration</td><td style="font-weight:600">' . (int)$q['duration_days'] . ' nights</td></tr>
      <tr><td style="padding:.4rem 0;color:#6b7280">Travelers</td><td style="font-weight:600">' . (int)$q['adults'] . ' adult' . ((int)$q['adults'] !== 1 ? 's' : '') . ((int)$q['children'] > 0 ? ', ' . (int)$q['children'] . ' child' . ((int)$q['children'] !== 1 ? 'ren' : '') : '') . '</td></tr>
      <tr><td style="padding:.4rem 0;color:#6b7280">Start Date</td><td style="font-weight:600">' . ($q['start_date'] ? date('d F Y', strtotime($q['start_date'])) : 'Flexible') . '</td></tr>
      <tr><td style="padding:.4rem 0;color:#6b7280">Accommodation</td><td style="font-weight:600">' . ucfirst(str_replace('_', ' ', $q['accommodation_level'] ?? 'Mid-Range')) . '</td></tr>
      <tr><td style="padding:.4rem 0;color:#6b7280">Meal Plan</td><td style="font-weight:600">' . (['fb'=>'Full Board (B+L+D)','hb'=>'Half Board (B+D)','bb'=>'Bed & Breakfast','ai'=>'All Inclusive'][$q['meal_plan'] ?? 'fb'] ?? strtoupper($q['meal_plan'] ?? 'FB')) . '</td></tr>
      ' . (!empty($q['vehicle_name']) ? '<tr><td style="padding:.4rem 0;color:#6b7280">Vehicle</td><td style="font-weight:600">' . htmlspecialchars($q['vehicle_name']) . '</td></tr>' : '') . '
    </table>

    <!-- Line items -->
    ' . (!empty($lineItemsHtml) ? '
    <div style="font-size:.6rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem;margin-bottom:.85rem">Cost Breakdown</div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:1.75rem">
      <thead>
        <tr style="border-bottom:2px solid #0a0a0a">
          <th style="padding:.5rem .75rem;text-align:left;font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:#6b7280">Description</th>
          <th style="padding:.5rem .75rem;text-align:center;font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:#6b7280">Qty</th>
          <th style="padding:.5rem .75rem;text-align:right;font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:#6b7280">Amount</th>
        </tr>
      </thead>
      <tbody>' . $lineItemsHtml . '</tbody>
    </table>' : '') . '

    <!-- Totals -->
    <div style="margin-left:auto;max-width:320px;margin-bottom:1.75rem">
      ' . ((float)($q['discount_pct'] ?? 0) > 0 ? '<div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid #f3f4f6;font-size:.82rem"><span>Discount (' . $q['discount_pct'] . '%)</span><span style="color:#16a34a">-$' . number_format((float)$q['subtotal'] * ((float)$q['discount_pct'] / 100), 0) . '</span></div>' : '') . '
      <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid #f3f4f6;font-size:.82rem"><span>Subtotal</span><span>$' . number_format((float)$q['subtotal'], 0) . '</span></div>
      <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid #f3f4f6;font-size:.82rem"><span>Service Charge (5%) + VAT (18%)</span><span>$' . number_format((float)$q['service_charge'] + (float)$q['vat'], 0) . '</span></div>
      <div style="background:#0a0a0a;color:#fff;padding:.85rem 1rem;display:flex;justify-content:space-between;align-items:center;margin-top:.35rem">
        <span style="font-size:.95rem;font-weight:600">Grand Total</span>
        <span style="font-family:Georgia,serif;font-size:1.25rem">USD $' . number_format((float)$q['grand_total'], 0) . '</span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:.35rem 0;font-size:.78rem;color:#6b7280"><span>Per Person</span><span>$' . number_format((float)$q['per_person'], 0) . '/pp</span></div>
    </div>

    <!-- Deposit -->
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:1rem 1.25rem;margin-bottom:1.75rem">
      <div style="font-size:.6rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#166534;margin-bottom:.35rem">To Confirm Your Booking</div>
      <div style="font-size:1rem;font-weight:700;color:#166534">Pay deposit of $' . number_format((float)$q['deposit_amount'], 0) . ' within 48 hours</div>
      <div style="font-size:.78rem;color:#166534;margin-top:.2rem">Balance of $' . number_format((float)$q['balance_due'], 0) . ' due 14 days before your safari</div>
    </div>

    ' . (!empty($q['notes']) ? '<div style="border:1px solid #e5e7eb;padding:1rem;background:#f9f9f8;font-size:.82rem;line-height:1.75;margin-bottom:1.75rem;white-space:pre-wrap">' . htmlspecialchars($q['notes']) . '</div>' : '') . '

    <!-- CTA buttons -->
    <div style="text-align:center;margin:2rem 0">
      <a href="' . $waLink . '" style="display:inline-block;background:#25D366;color:#fff;padding:.85rem 2rem;font-weight:700;text-decoration:none;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;margin:.25rem">Accept on WhatsApp</a>
      <a href="mailto:' . $replyTo . '?subject=' . rawurlencode('Re: Quotation ' . $q['quote_ref']) . '" style="display:inline-block;background:#0a0a0a;color:#fff;padding:.85rem 2rem;font-weight:700;text-decoration:none;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;margin:.25rem">Reply by Email</a>
    </div>

  </div>

  <!-- Footer -->
  <div style="background:#f7f7f5;border-top:1px solid #e5e7eb;padding:1.25rem 2.5rem;text-align:center;font-size:.72rem;color:#9ca3af;line-height:1.8">
    Safari Zangu Ventures · Njiro Road, Arusha, Tanzania<br>
    +255 663 430 934 · ' . $replyTo . ' · www.safarizanguven.com<br>
    Quotation valid until ' . $validStr . '. All prices in USD and subject to availability.
  </div>

</div>
</body></html>';

// ── Send via PHPMailer ────────────────────────────────────────
$sent  = false;
$error = '';

try {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = defined('SMTP_HOST')     ? SMTP_HOST     : 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : 'admin@safarizanguven.com';
    $mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = defined('SMTP_PORT')     ? (int)SMTP_PORT : 465;
    $mail->CharSet    = 'UTF-8';

    $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'admin@safarizanguven.com';
    $fromName  = defined('MAIL_FROM_NAME')  ? MAIL_FROM_NAME  : 'Safari Zangu Ventures';
    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($q['client_email'], $q['client_name']);
    $mail->addReplyTo($replyTo, $fromName);

    $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : $fromEmail;
    $mail->addCC($adminEmail, 'Safari Zangu Admin');

    $mail->Subject = 'Your Safari Quotation ' . $q['quote_ref'] . ' — Safari Zangu Ventures';
    $mail->isHTML(true);
    $mail->Body    = $emailBody;
    $mail->AltBody = "Dear {$q['client_name']},\n\nYour quotation {$q['quote_ref']} is ready.\n\nGrand Total: USD \$"
                   . number_format((float)$q['grand_total'], 0)
                   . "\nValid Until: {$validStr}\n\nWhatsApp us to confirm: +255 663 430 934\n\nSafari Zangu Ventures · Arusha, Tanzania";
    $mail->send();
    $sent = true;

    // Mark as sent in DB
    $db->prepare("UPDATE quotations SET status='sent', sent_at=NOW(), updated_at=NOW() WHERE id=? AND status='draft'")
       ->execute([$id]);

} catch (Exception $e) {
    $error = $e->getMessage();
    error_log('[SZV QuoteEmail] ' . $error);
}

ob_end_clean();

$msg = $sent
    ? "Quotation {$q['quote_ref']} emailed successfully to {$q['client_email']}."
    : "Email failed: {$error} — check SMTP settings in config/app.php.";

header('Location: ' . BASE_PATH . '/admin/quotations.php?msg=' . urlencode($msg));
exit;