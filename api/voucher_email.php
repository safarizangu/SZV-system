<?php
/**
 * Email the Service Voucher PDF to the guest.
 * URL: api/voucher_email.php?ref=SZVXXX
 * Auth: Admin session required
 */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
if (!defined('SZV_PDF_INTERNAL_RENDER') && \App\Services\OwnershipScope::isScoped()) {
    $__ref = strtoupper(trim($_GET['ref'] ?? $_POST['ref'] ?? ''));
    if (!\App\Services\OwnershipScope::ownsBooking(\App\Models\Database::getInstance(), $__ref, \App\Services\OwnershipScope::currentAgentId())) {
        \App\Services\AccessGuard::denyPlain('booking', $__ref, basename($_SERVER['SCRIPT_NAME']));
    }
}

$ref = strtoupper(trim($_GET['ref'] ?? ''));
if (!$ref) {
    header('Location: ../admin/bookings.php?msg=' . urlencode('No booking reference.'));
    exit;
}

$db = \App\Models\Database::getInstance();
try {
    $s = $db->prepare("SELECT * FROM bookings WHERE booking_ref=? LIMIT 1");
    $s->execute([$ref]);
    $b = $s->fetch();
    if (!$b) {
        header('Location: ../admin/bookings.php?msg=' . urlencode('Booking not found: ' . $ref));
        exit;
    }
} catch (\Exception $e) {
    header('Location: ../admin/bookings.php?msg=' . urlencode('DB error: ' . $e->getMessage()));
    exit;
}

$subject = 'Your Service Voucher — ' . $ref . ' | Safari Zangu Ventures';
$name    = htmlspecialchars($b['full_name'] ?? '');
$refSafe = htmlspecialchars($ref);

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:'Times New Roman',Times,'Liberation Serif',serif">
<div style="max-width:640px;margin:24px auto;background:#fff;border:1px solid #e0e0e0">

  <div style="background:#0a0a0a;padding:24px 32px;text-align:center">
    <div style="font-size:30px;font-weight:700;color:#fff;letter-spacing:.32em">SZV</div>
    <div style="font-size:11px;color:#fff;letter-spacing:.15em;text-transform:uppercase;margin-top:8px">Service Voucher</div>
  </div>

  <div style="padding:32px">
    <p style="font-size:15px;margin-top:0">Dear {$name},</p>
    <p style="font-size:14px;line-height:1.7;color:#0a0a0a">
      Please find attached the Service Voucher for your booking <strong>{$refSafe}</strong> — this confirms the
      services covered under your safari and can be presented at lodges, camps, or park entry points if requested.
    </p>
    <p style="font-size:12px;color:#0a0a0a;margin-top:20px;text-align:center">
      Questions? Reply to this email or WhatsApp us on +255 663 430 934
    </p>
  </div>

  <div style="background:#f7f7f5;padding:16px 32px;border-top:1px solid #e0e0e0;text-align:center">
    <p style="margin:0;font-size:11px;color:#0a0a0a">Safari Zangu Ventures Ltd · Njiro Road, Arusha, Tanzania</p>
    <p style="margin:4px 0 0;font-size:11px;color:#0a0a0a">info@safarizanguven.com · +255 663 430 934 · safarizanguven.com</p>
    <p style="margin:4px 0 0;font-size:10px;color:#0a0a0a">TALA Licence No. 037129 · Reg. No. 179228971</p>
  </div>

</div>
</body>
</html>
HTML;

$sent  = false;
$error = '';
try {
    $attachments = [];
    $rendered = \App\Services\PdfRenderer::renderVoucher($ref, 'Voucher-' . $ref);
    if ($rendered) $attachments[] = $rendered;

    $queuedId = \App\Mail\Mailer::queueRaw(
        $b['email'],
        $b['full_name'],
        $subject,
        $html,
        $attachments ?: null,
        'voucher_sent'
    );
    $sent = $queuedId !== false;
    if (!$sent) $error = 'See error log for detail.';
} catch (\Exception $e) {
    $error = $e->getMessage();
    error_log('[voucher_email] ' . $error);
}

if ($sent) {
    header('Location: ../admin/bookings.php?msg=' . urlencode("✓ Voucher sent to {$b['email']}"));
} else {
    header('Location: ../admin/bookings.php?msg=' . urlencode("✗ Voucher email failed: {$error}"));
}
exit;
