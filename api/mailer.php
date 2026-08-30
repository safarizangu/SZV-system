<?php
// ============================================================
// Safari Zangu Ventures — Mailer Helper
// Wraps PHPMailer to send the booking confirmation email
// containing the Booking ID + Travel Date (used as the
// tracking credentials on the Track Booking page).
// ============================================================

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send the booking confirmation email.
 *
 * @param array $booking Associative array with keys:
 *   booking_ref, full_name, email, package_name, accommodation_level,
 *   travelers, travel_date, total_amount, deposit_amount
 * @return array ['success' => bool, 'message' => string]
 */
function sendBookingConfirmationEmail(array $booking) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = (SMTP_SECURE === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($booking['email'], $booking['full_name']);
        $mail->addCC('admin@safarizanguven.com', 'Safari Admin');
        $mail->addReplyTo(SITE_EMAIL, SITE_NAME);

        $mail->Subject = 'Your Safari Zangu Booking Confirmation — ' . $booking['booking_ref'];

        $travelDateFormatted = date('d F Y', strtotime($booking['travel_date']));
        $accLabel = accommodationLabel($booking['accommodation_level'] ?? 'midrange');
        $total    = !empty($booking['total_amount']) ? formatCurrency($booking['total_amount']) : 'To be confirmed';
        $deposit  = !empty($booking['deposit_amount']) ? formatCurrency($booking['deposit_amount']) : null;
        $trackUrl = SITE_URL . '/track.php?ref=' . urlencode($booking['booking_ref']) . '&date=' . urlencode($booking['travel_date']);

        $mail->Body = "
        <div style='font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#111;'>
          <div style='background:#000;color:#fff;padding:24px 32px;'>
            <h1 style='margin:0;font-size:22px;letter-spacing:.05em;'>Safari Zangu Ventures</h1>
          </div>
          <div style='padding:32px;border:1px solid #eee;'>
            <p style='font-size:15px;'>Dear " . htmlspecialchars($booking['full_name']) . ",</p>
            <p style='font-size:15px;line-height:1.7;'>Thank you for booking with Safari Zangu Ventures! Your safari has been received and is now pending confirmation.</p>

            <div style='background:#f7f7f7;border:1px solid #eee;padding:20px;margin:24px 0;'>
              <p style='margin:0 0 6px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#000000;'>Booking ID — Keep This Safe</p>
              <p style='margin:0;font-size:26px;font-weight:700;letter-spacing:.05em;'>" . htmlspecialchars($booking['booking_ref']) . "</p>
            </div>

            <table style='width:100%;font-size:14px;line-height:2;border-collapse:collapse;'>
              <tr><td style='color:#000000;width:40%;'>Package</td><td><strong>" . htmlspecialchars($booking['package_name']) . "</strong></td></tr>
              <tr><td style='color:#000000;'>Accommodation Level</td><td>" . htmlspecialchars($accLabel) . "</td></tr>
              <tr><td style='color:#000000;'>Travelers</td><td>" . (int)$booking['travelers'] . "</td></tr>
              <tr><td style='color:#000000;'>Travel Date</td><td><strong>" . $travelDateFormatted . "</strong></td></tr>
              <tr><td style='color:#000000;'>Total Amount</td><td>" . $total . "</td></tr>
              " . ($deposit ? "<tr><td style='color:#000000;'>Deposit (50%)</td><td>" . $deposit . "</td></tr>" : "") . "
            </table>

            <p style='font-size:13px;color:#000000;margin-top:24px;line-height:1.7;'>
              <strong>Your Booking ID and Travel Date together act as your secure access code</strong> to track this booking online at any time — no password required.
            </p>

            <a href='" . htmlspecialchars($trackUrl) . "' style='display:inline-block;background:#000;color:#fff;text-decoration:none;padding:12px 24px;font-size:13px;letter-spacing:.05em;margin-top:12px;'>Track My Booking</a>

            <h2 style='margin-top:32px;margin-bottom:16px;color:#000;font-size:18px;'>
Payment Instructions
</h2>

<div style='background:#f8f8f8;border:1px solid #ddd;padding:20px;margin-bottom:20px;'>

    <p style='margin:0 0 10px 0;font-size:15px;font-weight:bold;'>
        Direct Bank Transfer
    </p>

    <p style='margin:0;line-height:1.8;font-size:14px;'>
        Bank: <strong>" . BANK_NAME . "</strong><br>
        Account Name: <strong>" . BANK_ACCOUNT_NAME . "</strong><br>
        Account Number: <strong>" . BANK_ACCOUNT_NO . "</strong><br>
        SWIFT/BIC: <strong>" . BANK_SWIFT . "</strong>
    </p>

</div>

<div style='background:#f8f8f8;border:1px solid #ddd;padding:20px;margin-bottom:20px;'>

    <p style='margin:0 0 10px 0;font-size:15px;font-weight:bold;'>
        PayPal Payment
    </p>

    <p style='margin:0;line-height:1.8;font-size:14px;'>
        PayPal Account: <strong>" . PAYPAL_EMAIL . "</strong>
    </p>

</div>

<p style='font-size:13px;color:#000000;line-height:1.7;'>
    A <strong>50% deposit</strong> is required within 48 hours to confirm your safari dates.
    The remaining balance is due <strong>" . BALANCE_DAYS . " days</strong> before departure.
</p>

<p style='font-size:13px;color:#000000;line-height:1.7;'>
    When making payment, please include your booking reference
    <strong>" . htmlspecialchars($booking['booking_ref']) . "</strong>
    so we can match the payment to your booking.
</p>

            <p style='font-size:13px;color:#000000;margin-top:32px;border-top:1px solid #eee;padding-top:16px;'>
              A 50% deposit is required within 48 hours to confirm your dates. The remaining balance is due " . BALANCE_DAYS . " days before departure.
            </p>
          </div>
          <div style='padding:16px 32px;font-size:12px;color:#000000;'>
            &copy; " . date('Y') . " Safari Zangu Ventures Ltd. &middot; " . SITE_EMAIL . "
          </div>
        </div>";

        $mail->AltBody = "Booking Confirmation\n\n"
            . "Booking ID: {$booking['booking_ref']}\n"
            . "Package: {$booking['package_name']} ({$accLabel})\n"
            . "Travel Date: {$travelDateFormatted}\n"
            . "Total: {$total}\n\n"
            . "Use your Booking ID + Travel Date to track your booking at: {$trackUrl}";

        $mail->send();
        return ['success' => true, 'message' => 'Confirmation email sent.'];

    } catch (PHPMailerException $e) {
        error_log('PHPMailer error: ' . $mail->ErrorInfo);
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}

function sendCustomSafariAck($toEmail, $toName) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = (SMTP_SECURE === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(SITE_EMAIL, SITE_NAME);

        $mail->Subject = 'Your custom safari request — Safari Zangu Ventures';
        $mail->isHTML(true);

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:580px;margin:0 auto;color:#111;'>
              <div style='background:#000;padding:22px 28px;'>
                <h1 style='margin:0;color:#fff;font-size:20px;font-weight:400;letter-spacing:.04em;'>SAFARI ZANGU VENTURES</h1>
              </div>
              <div style='padding:28px;border:1px solid #eee;'>
                <p>Dear " . htmlspecialchars($toName) . ",</p>
                <p>Thank you for reaching out to Safari Zangu Ventures. We have received your custom safari enquiry and our team will prepare a personalised proposal within <strong>24 hours</strong>.</p>
                <p style='margin-top:20px;font-size:13px;color:#555;'>For faster assistance, WhatsApp us at <strong>+255 663 430 934</strong>.</p>
              </div>
              <div style='padding:14px 28px;background:#f7f7f7;border-top:1px solid #eee;text-align:center;font-size:11px;color:#888;'>
                Safari Zangu Ventures &middot; Arusha, Tanzania &middot; " . SITE_EMAIL . "
              </div>
            </div>";

        $mail->AltBody = "Dear {$toName},\n\nThank you for your custom safari request. We will respond within 24 hours.\n\nWhatsApp: +255 663 430 934\n\nSafari Zangu Ventures";

        return $mail->send();

    } catch (PHPMailerException $e) {
        error_log('[SZV Mailer] sendCustomSafariAck error: ' . $e->getMessage());
        return false;
    }
}
