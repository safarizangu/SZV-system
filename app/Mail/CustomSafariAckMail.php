<?php
namespace App\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class CustomSafariAckMail
{
    public function send(array $data): bool
    {
        require_once __DIR__ . '/../../php/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/../../php/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../../php/PHPMailer/src/SMTP.php';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($data['email'], $data['full_name']);
            $mail->addCC(ADMIN_EMAIL, 'Safari Zangu Admin');
            $mail->Subject = 'Your Custom Safari Request — Safari Zangu Ventures';
            $mail->isHTML(true);
            $mail->Body = $this->html($data);
            $mail->AltBody = "Dear {$data['full_name']}, thank you for your custom safari request. We will respond within 24 hours. WhatsApp: +255 663 430 934";
            return $mail->send();
        } catch (PHPMailerException $e) {
            error_log('[SZV Mail CustomAck] ' . $e->getMessage());
            return false;
        }
    }

    private function html(array $d): string
    {
        $destinations = is_array($d['destinations'] ?? '') ? implode(', ', $d['destinations']) : ($d['destinations'] ?? '');
        return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:580px;margin:0 auto;color:#111;">
  <div style="background:#000;padding:22px 28px;">
    <h1 style="margin:0;color:#fff;font-size:20px;font-weight:400;letter-spacing:.04em;">SAFARI ZANGU VENTURES</h1>
  </div>
  <div style="padding:28px;border:1px solid #eee;">
    <p>Dear {$d['full_name']},</p>
    <p>Thank you for reaching out to Safari Zangu Ventures. We've received your custom safari request and our team will prepare a personalised proposal within <strong>24 hours</strong>.</p>
    <h3 style="font-size:14px;margin-top:20px;">Your Request Summary</h3>
    <table style="width:100%;font-size:13px;border-collapse:collapse;">
      <tr><td style="padding:5px 0;color:#666;width:40%;">Destinations</td><td>{$destinations}</td></tr>
      <tr><td style="padding:5px 0;color:#666;">Duration</td><td>{$d['duration_days']} days</td></tr>
      <tr><td style="padding:5px 0;color:#666;">Travelers</td><td>{$d['adults']} adults, {$d['children']} children</td></tr>
      <tr><td style="padding:5px 0;color:#666;">Accommodation</td><td>{$d['accommodation_standard']}</td></tr>
    </table>
    <p style="margin-top:20px;font-size:13px;color:#555;">For faster assistance, WhatsApp us at <strong>+255 663 430 934</strong>.</p>
  </div>
  <div style="padding:14px 28px;background:#f7f7f7;border-top:1px solid #eee;text-align:center;font-size:11px;color:#888;">
    Safari Zangu Ventures · Arusha, Tanzania · info@safarizanguven.com
  </div>
</div>
HTML;
    }
}
