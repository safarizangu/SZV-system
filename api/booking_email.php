<?php
/**
 * Send booking confirmation email with full itinerary
 * GET: api/booking_email.php?ref=SZVXXX
 * Auth: Admin session required
 */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

$ref = strtoupper(trim($_GET['ref'] ?? ''));
if (!$ref) {
    header('Location: ../admin/bookings.php?msg='.urlencode('No booking reference.'));
    exit;
}

$db = \App\Models\Database::getInstance();

// Load booking
try {
    $s = $db->prepare("SELECT * FROM bookings WHERE booking_ref=? LIMIT 1");
    $s->execute([$ref]);
    $b = $s->fetch();
    if (!$b) {
        header('Location: ../admin/bookings.php?msg='.urlencode('Booking not found: '.$ref));
        exit;
    }
} catch (\Exception $e) {
    header('Location: ../admin/bookings.php?msg='.urlencode('DB error: '.$e->getMessage()));
    exit;
}

// Load the linked itinerary — prefer the booking's own itinerary_id (a
// direct FK) and only fall back to matching by package name (fragile —
// breaks if the package was ever renamed) for older bookings made before
// bookings.itinerary_id existed.
$itinDays = []; $itinIncludes = []; $itinExcludes = [];
try {
    $pr = null;
    if (!empty($b['itinerary_id'])) {
        $is = $db->prepare("SELECT days, includes, excludes, name as itin_name FROM itineraries WHERE id=? LIMIT 1");
        $is->execute([$b['itinerary_id']]);
        $pr = $is->fetch() ?: null;
    }
    if (!$pr) {
        $ps = $db->prepare("SELECT i.days, i.includes, i.excludes, i.name as itin_name
                            FROM packages p
                            JOIN itineraries i ON i.id = p.itinerary_id
                            WHERE p.name = ? AND p.is_active = 1 LIMIT 1");
        $ps->execute([$b['package_name']]);
        $pr = $ps->fetch() ?: null;
    }
    if ($pr) {
        $itinDays     = json_decode($pr['days']     ?? '[]', true) ?: [];
        $itinIncludes = json_decode($pr['includes'] ?? '[]', true) ?: [];
        $itinExcludes = json_decode($pr['excludes'] ?? '[]', true) ?: [];
    }
} catch (\Exception $e) {
    error_log('[booking_email] itinerary load: '.$e->getMessage());
}

// Load quotation line items if linked
$lineItems = [];
try {
    if (!empty($b['quotation_id'])) {
        $qs = $db->prepare("SELECT line_items FROM quotations WHERE id=? LIMIT 1");
        $qs->execute([$b['quotation_id']]);
        $qr = $qs->fetch();
        if ($qr) $lineItems = json_decode($qr['line_items'] ?? '[]', true) ?: [];
    }
} catch (\Exception $e) {}

// ── Build email HTML ──────────────────────────────────────────
$pax       = (int)($b['adults'] ?? $b['travelers'] ?? 2);
$children  = (int)($b['children'] ?? 0);
$total     = (float)($b['total_amount'] ?? 0);
$deposit   = (float)($b['deposit_amount'] ?? round($total * 0.25, 2));
$balance   = $total - $deposit;
$travelDt  = $b['travel_date'] ? date('d F Y', strtotime($b['travel_date'])) : '—';
$trackUrl  = rtrim(APP_URL,'/').'/track.php?ref='.urlencode($ref).'&date='.urlencode($b['travel_date']??'');
$pdfUrl    = rtrim(APP_URL,'/').'/api/booking_pdf.php?ref='.urlencode($ref);

// Day-by-day HTML — same card arrangement as the PDFs and the admin
// itinerary manager, built with bulletproof (table-based) email markup.
$itinHtml = '';
if (!empty($itinDays)) {
    $itinHtml = '<h3 style="color:#000;font-size:15px;margin:28px 0 10px;border-bottom:2px solid #000;padding-bottom:6px">Day-by-Day Itinerary</h3>'
        . \App\Helpers\ItineraryDayCards::renderEmailSafe($itinDays);
}

// Includes / Excludes HTML
$inclExclHtml = '';
if ($itinIncludes || $itinExcludes) {
    $inclExclHtml = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px">';
    if ($itinIncludes) {
        $inclExclHtml .= '<div><h4 style="font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px;color:#166534">What\'s Included</h4><ul style="margin:0;padding:0;list-style:none">';
        foreach ($itinIncludes as $inc)
            $inclExclHtml .= '<li style="font-size:12px;padding:3px 0;color:#374151"><span style="color:#166534;font-weight:700">✓</span> '.htmlspecialchars($inc).'</li>';
        $inclExclHtml .= '</ul></div>';
    }
    if ($itinExcludes) {
        $inclExclHtml .= '<div><h4 style="font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px;color:#991b1b">Not Included</h4><ul style="margin:0;padding:0;list-style:none">';
        foreach ($itinExcludes as $exc)
            $inclExclHtml .= '<li style="font-size:12px;padding:3px 0;color:#374151"><span style="color:#991b1b;font-weight:700">✗</span> '.htmlspecialchars($exc).'</li>';
        $inclExclHtml .= '</ul></div>';
    }
    $inclExclHtml .= '</div>';
}

// Cost breakdown HTML (from quotation line items)
$breakdownHtml = '';
if (!empty($lineItems)) {
    $breakdownHtml = '<h3 style="color:#000;font-size:15px;margin:28px 0 10px;border-bottom:2px solid #000;padding-bottom:6px">Cost Breakdown</h3>
    <table style="width:100%;border-collapse:collapse;font-size:12px">';
    foreach ($lineItems as $item) {
        $breakdownHtml .= '<tr style="border-bottom:1px solid #f3f4f6">
          <td style="padding:6px 0;color:#374151">'.htmlspecialchars($item['description']??$item['name']??'').'</td>
          <td style="padding:6px 0;text-align:right;font-weight:600">$'.number_format((float)($item['amount']??0),2).'</td>
        </tr>';
    }
    $breakdownHtml .= '<tr style="border-top:2px solid #0a0a0a">
      <td style="padding:8px 0;font-weight:700;font-size:14px">Total</td>
      <td style="padding:8px 0;font-weight:700;font-size:14px;text-align:right">$'.number_format($total,2).'</td>
    </tr></table>';
}

// These were previously referenced inside the heredocs below without ever
// being defined — {$pax_s} interpolated to an undefined-variable warning
// and an empty string before the following str_replace() could even see
// it (heredocs interpolate immediately, not lazily), and {$total_fmt} /
// {$deposit_fmt} / {$balance_fmt} were never defined at all — so a
// resent confirmation email showed "Total Amount: $" with no number.
$pax_s       = $pax !== 1 ? 's' : '';
$total_fmt   = number_format($total, 0);
$deposit_fmt = number_format($deposit, 0);
$balance_fmt = number_format($balance, 0);

$bankName    = defined('BANK_NAME') ? BANK_NAME : 'ABSA Bank Tanzania';
$bankAccName = defined('BANK_ACCOUNT_NAME') ? BANK_ACCOUNT_NAME : 'Safari Zangu Ventures Ltd';
$bankAcc     = defined('BANK_ACCOUNT_NO') ? BANK_ACCOUNT_NO : '—';
$bankSwift   = defined('BANK_SWIFT') ? BANK_SWIFT : '—';
$paypalEmail = defined('PAYPAL_EMAIL') ? PAYPAL_EMAIL : 'info@safarizanguven.com';

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif">
<div style="max-width:640px;margin:24px auto;background:#fff;border:1px solid #e0e0e0">

  <!-- Header -->
  <div style="background:#0a0a0a;padding:24px 32px">
    <div style="font-size:22px;font-weight:700;color:#fff;letter-spacing:.04em">Safari Zangu Ventures</div>
    <div style="font-size:11px;color:rgba(255,255,255,.6);letter-spacing:.15em;text-transform:uppercase;margin-top:4px">Booking Confirmation</div>
  </div>

  <!-- Body -->
  <div style="padding:32px">
    <p style="font-size:15px;margin-top:0">Dear {$b['full_name']},</p>
    <p style="font-size:14px;line-height:1.7;color:#444">
      Thank you for booking with Safari Zangu Ventures. Your booking has been received and is confirmed.
      Please find your complete booking details and itinerary below.
    </p>

    <!-- Booking Reference Box -->
    <div style="background:#f7f7f5;border-left:4px solid #c8963e;padding:16px 20px;margin:24px 0">
      <div style="font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:#6b7280;margin-bottom:4px">Booking Reference — Keep This Safe</div>
      <div style="font-size:28px;font-weight:700;letter-spacing:.06em;color:#0a0a0a">{$ref}</div>
      <div style="font-size:11px;color:#6b7280;margin-top:6px">You will need this reference to track your booking online</div>
    </div>

    <!-- Booking Summary -->
    <h3 style="color:#000;font-size:15px;margin:0 0 10px;border-bottom:2px solid #000;padding-bottom:6px">Booking Summary</h3>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <tr><td style="padding:7px 0;border-bottom:1px solid #f3f4f6;color:#6b7280;width:45%">Package</td><td style="font-weight:600">{$b['package_name']}</td></tr>
      <tr><td style="padding:7px 0;border-bottom:1px solid #f3f4f6;color:#6b7280">Travel Date</td><td style="font-weight:600">{$travelDt}</td></tr>
      <tr><td style="padding:7px 0;border-bottom:1px solid #f3f4f6;color:#6b7280">Guests</td><td>{$pax} adult{$pax_s}
HTML;
$html .= ($children > 0 ? " + {$children} child" : '') . '</td></tr>';
$html .= <<<HTML2
      <tr><td style="padding:7px 0;border-bottom:1px solid #f3f4f6;color:#6b7280">Accommodation</td><td>{$b['accommodation_level']}</td></tr>
      <tr><td style="padding:7px 0;border-bottom:1px solid #f3f4f6;color:#6b7280">Total Amount</td><td style="font-weight:700;font-size:15px">\${$total_fmt}</td></tr>
      <tr><td style="padding:7px 0;border-bottom:1px solid #f3f4f6;color:#6b7280">Deposit (25%) — due within 48h</td><td style="font-weight:600;color:#c8963e">\${$deposit_fmt}</td></tr>
      <tr><td style="padding:7px 0;color:#6b7280">Balance — due 45 days before travel</td><td style="font-weight:600">\${$balance_fmt}</td></tr>
    </table>

    {$itinHtml}
    {$inclExclHtml}
    {$breakdownHtml}

    <!-- Payment Instructions -->
    <h3 style="color:#000;font-size:15px;margin:28px 0 10px;border-bottom:2px solid #000;padding-bottom:6px">Payment Instructions</h3>
    <p style="font-size:13px;color:#444;margin-bottom:16px">
      To secure your booking, please transfer the deposit of <strong>\${$deposit_fmt}</strong> using one of the methods below.
      Include your booking reference <strong>{$ref}</strong> in all transfers.
    </p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div style="border:1px solid #e5e7eb;padding:16px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:10px">🏦 Bank Transfer</div>
        <div style="font-size:12px;line-height:2;color:#374151">
          <strong>Bank:</strong> {$bankName}<br>
          <strong>Account Name:</strong> {$bankAccName}<br>
          <strong>Account No:</strong> {$bankAcc}<br>
          <strong>SWIFT:</strong> {$bankSwift}<br>
          <strong>Reference:</strong> {$ref}
        </div>
      </div>
      <div style="border:1px solid #e5e7eb;padding:16px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:10px">💳 PayPal / Wise</div>
        <div style="font-size:12px;line-height:2;color:#374151">
          <strong>Send to:</strong> {$paypalEmail}<br>
          <strong>Reference:</strong> {$ref}<br>
          <span style="font-size:11px;color:#9ca3af">Use Friends &amp; Family to avoid fees</span>
        </div>
      </div>
    </div>

    <!-- Track Button -->
    <div style="margin-top:28px;text-align:center">
      <a href="{$trackUrl}"
         style="display:inline-block;background:#0a0a0a;color:#fff;text-decoration:none;padding:14px 32px;font-size:12px;letter-spacing:.1em;text-transform:uppercase;font-weight:600">
        Track My Booking
      </a>
    </div>

    <p style="font-size:12px;color:#9ca3af;margin-top:20px;text-align:center">
      A printable PDF of this booking is attached to this email.<br>
      Questions? Reply to this email or WhatsApp us on +255 663 430 934
    </p>
  </div>

  <!-- Footer -->
  <div style="background:#f7f7f5;padding:16px 32px;border-top:1px solid #e0e0e0;text-align:center">
    <p style="margin:0;font-size:11px;color:#9ca3af">Safari Zangu Ventures Ltd · Njiro Road, Arusha, Tanzania</p>
    <p style="margin:4px 0 0;font-size:11px;color:#9ca3af">info@safarizanguven.com · +255 663 430 934 · safarizanguven.com</p>
    <p style="margin:4px 0 0;font-size:10px;color:#c0c0b8">TALA Licence No. 037129 · Reg. No. 179228971</p>
  </div>

</div>
</body>
</html>
HTML2;

$subject = 'Booking Confirmation — '.$ref.' | Safari Zangu Ventures';
$toName  = $b['full_name'];
$toEmail = $b['email'];

// ── Preview mode — ?preview=1 outputs HTML without sending ──────
if (isset($_GET['preview'])) {
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

// ── Send via PHPMailer ────────────────────────────────────────
$sent  = false;
$error = '';
try {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
    $localPath  = __DIR__ . '/../php/PHPMailer/src/';
    if (file_exists($vendorPath)) {
        require_once $vendorPath;
    } elseif (file_exists($localPath.'PHPMailer.php')) {
        require_once $localPath.'Exception.php';
        require_once $localPath.'PHPMailer.php';
        require_once $localPath.'SMTP.php';
    } else {
        throw new \Exception('PHPMailer not found');
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE === 'ssl'
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress($toEmail, $toName);
    $mail->addCC(ADMIN_EMAIL, 'Safari Zangu Admin');
    $mail->addReplyTo(SITE_EMAIL, APP_NAME);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body    = $html;
    $mail->AltBody = "SAFARI ZANGU VENTURES — BOOKING CONFIRMATION\n\n"
                   . "Dear {$toName},\n\n"
                   . "Booking Reference: {$ref}\n"
                   . "Package: {$b['package_name']}\n"
                   . "Travel Date: {$travelDt}\n"
                   . "Guests: {$pax} adults\n"
                   . "Total: \${$total_fmt}\n"
                   . "Deposit Due (25%): \${$deposit_fmt}\n"
                   . "Balance Due: \${$balance_fmt}\n\n"
                   . "Track your booking: {$trackUrl}\n\n"
                   . "Payment:\n"
                   . "Bank: {$bankName} | Account: {$bankAcc} | SWIFT: {$bankSwift}\n"
                   . "PayPal: {$paypalEmail}\n"
                   . "Reference: {$ref}\n\n"
                   . "Questions? Reply to this email or WhatsApp +255 663 430 934\n\n"
                   . "Safari Zangu Ventures · Arusha, Tanzania";

    $mail->send();
    $sent = true;

    // Log to email_queue as sent record
    try {
        $db->prepare("INSERT INTO email_queue
            (to_email, to_name, subject, body, template, booking_ref, status, sent_at, created_at)
            VALUES (?,?,?,?,?,?,'sent',NOW(),NOW())")
           ->execute([$toEmail, $toName, $subject, $html, 'booking_confirmation', $ref]);
    } catch(\Exception $e) { /* non-critical */ }

} catch (\Exception $e) {
    $error = $e->getMessage();
    error_log('[booking_email] '.$error);
}

if ($sent) {
    header('Location: ../admin/bookings.php?msg='.urlencode("✓ Booking confirmation sent to {$toEmail}"));
} else {
    header('Location: ../admin/bookings.php?msg='.urlencode("✗ Email failed: {$error}"));
}
exit;
