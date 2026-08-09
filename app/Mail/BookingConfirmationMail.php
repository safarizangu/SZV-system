<?php
namespace App\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use App\Helpers\Formatter;

/**
 * Booking confirmation email with full itemised breakdown.
 */
class BookingConfirmationMail
{
    private PHPMailer $mailer;

    public function __construct()
    {
        require_once __DIR__ . '/../../php/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/../../php/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../../php/PHPMailer/src/SMTP.php';

        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host       = SMTP_HOST;
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = SMTP_USERNAME;
        $this->mailer->Password   = SMTP_PASSWORD;
        $this->mailer->SMTPSecure = (SMTP_SECURE === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port       = SMTP_PORT;
        $this->mailer->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $this->mailer->addReplyTo(SITE_EMAIL, APP_NAME);
    }

    public function send(array $booking, array $quote = []): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($booking['email'], $booking['full_name']);
            $this->mailer->addCC(ADMIN_EMAIL, 'Safari Zangu Admin');
            $this->mailer->Subject = 'Booking Confirmation — ' . $booking['booking_ref'] . ' | Safari Zangu Ventures';
            $this->mailer->isHTML(true);
            $this->mailer->Body    = $this->buildHtml($booking, $quote);
            $this->mailer->AltBody = $this->buildPlainText($booking, $quote);
            return $this->mailer->send();
        } catch (PHPMailerException $e) {
            error_log('[SZV Mail] ' . $e->getMessage());
            return false;
        }
    }

    private function buildHtml(array $b, array $q): string
    {
        $travelDate = Formatter::date($b['travel_date'] ?? '');
        $trackUrl   = APP_URL . '/track.php?ref=' . urlencode($b['booking_ref']) . '&date=' . urlencode($b['travel_date'] ?? '');
        $total      = Formatter::currency((float)($b['total_amount'] ?? 0));
        $deposit    = Formatter::currency((float)($b['deposit_amount'] ?? 0));

        $lineItemsHtml = '';
        foreach ($q['line_items'] ?? [] as $item) {
            $lineItemsHtml .= sprintf(
                '<tr><td style="padding:6px 0;border-bottom:1px solid #f0f0f0;color:#444;font-size:13px;">%s</td><td style="padding:6px 0;border-bottom:1px solid #f0f0f0;text-align:right;font-weight:600;font-size:13px;">%s</td></tr>',
                htmlspecialchars($item['name']),
                Formatter::currency($item['amount'])
            );
        }

        $breakdownSection = !empty($lineItemsHtml) ? "
            <h3 style='color:#000;font-size:16px;margin:28px 0 12px;'>Price Breakdown</h3>
            <table style='width:100%;border-collapse:collapse;'>
                {$lineItemsHtml}
                <tr><td colspan='2' style='padding:12px 0;border-top:2px solid #000;'></td></tr>
                <tr>
                    <td style='font-size:16px;font-weight:700;'>Total</td>
                    <td style='font-size:16px;font-weight:700;text-align:right;'>{$total}</td>
                </tr>
            </table>" : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">
  <div style="max-width:600px;margin:24px auto;background:#fff;border:1px solid #e0e0e0;">

    <!-- Header -->
    <div style="background:#000;padding:24px 32px;">
      <h1 style="margin:0;color:#fff;font-size:22px;letter-spacing:.04em;font-weight:400;">SAFARI ZANGU VENTURES</h1>
      <p style="margin:4px 0 0;color:rgba(255,255,255,.6);font-size:11px;letter-spacing:.12em;text-transform:uppercase;">Booking Confirmation</p>
    </div>

    <!-- Body -->
    <div style="padding:32px;">
      <p style="font-size:15px;margin-top:0;">Dear {$b['full_name']},</p>
      <p style="font-size:14px;line-height:1.7;color:#444;">
        Thank you for booking with Safari Zangu Ventures. Your booking has been received and is now pending confirmation. Our team will be in touch within 24 hours.
      </p>

      <!-- Booking ID Box -->
      <div style="background:#f7f7f7;border-left:4px solid #000;padding:16px 20px;margin:24px 0;">
        <p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#666;">Your Booking Reference</p>
        <p style="margin:0;font-size:26px;font-weight:700;letter-spacing:.06em;color:#000;">{$b['booking_ref']}</p>
        <p style="margin:6px 0 0;font-size:11px;color:#666;">Keep this reference number safe — you will need it to track your booking.</p>
      </div>

      <!-- Summary Table -->
      <h3 style="color:#000;font-size:16px;margin:0 0 12px;">Booking Summary</h3>
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <tr><td style="padding:7px 0;border-bottom:1px solid #f0f0f0;color:#666;width:45%;">Package</td><td style="font-weight:600;">{$b['package_name']}</td></tr>
        <tr><td style="padding:7px 0;border-bottom:1px solid #f0f0f0;color:#666;">Accommodation</td><td>{$b['accommodation_level']}</td></tr>
        <tr><td style="padding:7px 0;border-bottom:1px solid #f0f0f0;color:#666;">Travelers</td><td>{$b['travelers']}</td></tr>
        <tr><td style="padding:7px 0;border-bottom:1px solid #f0f0f0;color:#666;">Travel Date</td><td style="font-weight:600;">{$travelDate}</td></tr>
        <tr><td style="padding:7px 0;border-bottom:1px solid #f0f0f0;color:#666;">Total Amount</td><td style="font-weight:600;">{$total}</td></tr>
        <tr><td style="padding:7px 0;color:#666;">Deposit Required (50%)</td><td style="font-weight:600;color:#000;">{$deposit}</td></tr>
      </table>

      {$breakdownSection}

      <!-- Payment Instructions -->
      <div style="margin-top:28px;padding:20px;border:1px solid #e0e0e0;">
        <h3 style="margin-top:0;font-size:15px;">Payment Instructions</h3>
        <p style="font-size:13px;color:#444;margin-bottom:12px;">To confirm your booking, please transfer the deposit of <strong>{$deposit}</strong> via one of the following:</p>
        <p style="font-size:13px;margin:4px 0;"><strong>Bank Transfer:</strong> ABSA Bank Tanzania · Account: 1234567890 · SWIFT: BARCTZTZXXX</p>
        <p style="font-size:13px;margin:4px 0;"><strong>PayPal / Wise:</strong> payments@safarizanguven.com</p>
        <p style="font-size:13px;margin:8px 0 0;color:#666;">Please use your booking reference <strong>{$b['booking_ref']}</strong> as the payment reference.</p>
      </div>

      <!-- Track Button -->
      <div style="margin-top:24px;text-align:center;">
        <a href="{$trackUrl}" style="display:inline-block;background:#000;color:#fff;text-decoration:none;padding:14px 32px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;font-weight:600;">Track My Booking</a>
      </div>
    </div>

    <!-- Footer -->
    <div style="background:#f7f7f7;padding:16px 32px;border-top:1px solid #e0e0e0;text-align:center;">
      <p style="margin:0;font-size:11px;color:#888;">Safari Zangu Ventures · Arusha, Tanzania · info@safarizanguven.com · +255 663 430 934</p>
      <p style="margin:4px 0 0;font-size:11px;color:#aaa;">© 2026 Safari Zangu Ventures Ltd. All rights reserved.</p>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function buildPlainText(array $b, array $q): string
    {
        $total   = Formatter::currency((float)($b['total_amount'] ?? 0));
        $deposit = Formatter::currency((float)($b['deposit_amount'] ?? 0));
        return "SAFARI ZANGU VENTURES — BOOKING CONFIRMATION\n\n"
             . "Dear {$b['full_name']},\n\n"
             . "Booking Reference: {$b['booking_ref']}\n"
             . "Package: {$b['package_name']}\n"
             . "Travel Date: " . Formatter::date($b['travel_date'] ?? '') . "\n"
             . "Travelers: {$b['travelers']}\n"
             . "Total: {$total}\n"
             . "Deposit (50%): {$deposit}\n\n"
             . "Track your booking at: " . APP_URL . "/track.php?ref={$b['booking_ref']}&date={$b['travel_date']}\n\n"
             . "Safari Zangu Ventures · Arusha, Tanzania\n"
             . "info@safarizanguven.com · +255 663 430 934";
    }
}
