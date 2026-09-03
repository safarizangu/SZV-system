<?php
namespace App\Mail;

/**
 * Safari Zangu Ventures — Email Template Engine
 *
 * Usage:
 *   $html = EmailTemplate::render('booking_confirmation', [
 *       'client_name'  => 'John Smith',
 *       'booking_ref'  => 'SZV-ABC123',
 *       'package_name' => 'Classic Safari',
 *       ...
 *   ]);
 *
 * Templates available:
 *   booking_confirmation       — php/book.php, php/convert_quotation.php
 *   booking_reminder_deposit   — php/process_reminders.php
 *   booking_reminder_balance   — php/process_reminders.php
 *   booking_reminder_departure — php/process_reminders.php
 *   booking_reminder_posttrip  — php/process_reminders.php
 *   quotation_sent             — php/quotation_email.php
 *   quotation_accepted         — admin/quotations.php (on accept)
 *   quotation_expired          — cron/scheduled job
 *   contact_acknowledgement    — php/contact.php (to guest)
 *   contact_admin_notification — php/contact.php (to admin)
 *   custom_request_ack         — php/custom_safari.php (to guest)
 *   custom_request_admin       — php/custom_safari.php (to admin)
 *   lead_converted             — php/leads_api.php (to agent)
 *   commission_approved        — admin/commissions.php (to agent)
 *   commission_paid            — admin/commissions.php (to agent)
 */
class EmailTemplate
{
    // ── Brand constants ───────────────────────────────────────
    private const BRAND_NAME    = 'Safari Zangu Ventures';
    private const BRAND_TAGLINE = 'Private Tanzania Safari Specialists · Arusha';
    private const WA_NUMBER     = '255663430934';
    private const SITE_URL      = 'https://www.safarizanguven.com';
    private const CONTACT_EMAIL = 'info@safarizanguven.com';
    private const CONTACT_PHONE = '+255 663 430 934';

    /**
     * Render a named template with given variables.
     * Returns ['html'=>string, 'text'=>string, 'subject'=>string]
     */
    public static function render(string $template, array $vars = []): array
    {
        // ── Check DB for custom override first ────────────────
        // If admin saved a custom template in email_templates table, use it.
        // Placeholders like {client_name} are replaced with $vars values.
        try {
            $db   = \App\Models\Database::getInstance();
            $stmt = $db->prepare(
                "SELECT subject, body_html FROM email_templates WHERE template_key=? AND is_custom=1 LIMIT 1"
            );
            $stmt->execute([$template]);
            $row = $stmt->fetch();
            if ($row && !empty($row['body_html'])) {
                $subject = $row['subject'];
                $body    = $row['body_html'];
                // Replace {placeholder} with $vars values
                foreach ($vars as $k => $v) {
                    if (is_scalar($v)) {
                        $body    = str_replace('{' . $k . '}', (string)$v, $body);
                        $subject = str_replace('{' . $k . '}', (string)$v, $subject);
                    }
                }
                return [
                    'html'    => self::wrap(ucwords(str_replace('_',' ',$template)), $body, ''),
                    'text'    => strip_tags($body),
                    'subject' => $subject,
                ];
            }
        } catch (\Exception $e) {
            // DB unavailable or table missing — fall through to PHP template
            error_log('[EmailTemplate] DB check failed: ' . $e->getMessage());
        }

        // ── Fall back to PHP class template ───────────────────
        $method = 'tpl_' . $template;
        if (!method_exists(self::class, $method)) {
            throw new \InvalidArgumentException("Unknown email template: {$template}");
        }
        $result = self::$method($vars);
        return [
            'html'    => self::wrap($result['title'], $result['html'], $result['preview'] ?? ''),
            'text'    => $result['text'] ?? strip_tags($result['html']),
            'subject' => $result['subject'],
        ];
    }

    // ══════════════════════════════════════════════════════════
    // MASTER LAYOUT WRAPPER
    // ══════════════════════════════════════════════════════════
    private static function wrap(string $title, string $body, string $preview = ''): string
    {
        $year     = date('Y');
        $brand    = self::BRAND_NAME;
        $tagline  = self::BRAND_TAGLINE;
        $wa       = self::WA_NUMBER;
        $siteUrl  = self::SITE_URL;
        $email    = self::CONTACT_EMAIL;
        $phone    = self::CONTACT_PHONE;

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>{$title}</title>
  <!--[if mso]><style>td,th,div,p,a,h1,h2,h3,h4,h5,h6{font-family:Arial,sans-serif!important}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#f3f3f1;font-family:Arial,Helvetica,sans-serif;color:#0a0a0a;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%">

<!-- Preview text (hidden) -->
<div style="display:none;max-height:0;overflow:hidden;color:#f3f3f1;font-size:1px">{$preview}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
<tr><td align="center" style="padding:24px 16px">
<table width="620" cellpadding="0" cellspacing="0" border="0" style="max-width:620px;width:100%">

  <!-- HEADER -->
  <tr><td style="background:#0a0a0a;padding:28px 40px">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td>
        <div style="font-family:Georgia,'Times New Roman',serif;font-size:22px;font-weight:400;color:#ffffff;letter-spacing:.04em;font-style:italic">{$brand}</div>
        <div style="font-size:10px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-top:4px">{$tagline}</div>
      </td>
      <td align="right" style="vertical-align:middle">
        <a href="{$siteUrl}" style="color:rgba(255,255,255,.35);font-size:11px;text-decoration:none;letter-spacing:.06em">{$siteUrl}</a>
      </td>
    </tr>
    </table>
  </td></tr>

  <!-- BODY -->
  <tr><td style="background:#ffffff;padding:40px">
    {$body}
  </td></tr>

  <!-- FOOTER -->
  <tr><td style="background:#f7f7f5;border-top:1px solid #e5e7eb;padding:24px 40px;text-align:center">
    <p style="margin:0 0 8px;font-size:12px;color:#9ca3af;line-height:1.8">
      {$brand} &middot; Njiro Road, Arusha, Tanzania<br>
      <a href="tel:{$phone}" style="color:#9ca3af;text-decoration:none">{$phone}</a>
      &middot;
      <a href="mailto:{$email}" style="color:#9ca3af;text-decoration:none">{$email}</a>
    </p>
    <p style="margin:0;font-size:11px;color:#d1d5db">
      &copy; {$year} {$brand} Ltd. All rights reserved.
    </p>
    <p style="margin:8px 0 0;font-size:10px;color:#d1d5db">
      You received this email because of a booking or enquiry with Safari Zangu Ventures.
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    // ── Shared sub-components ─────────────────────────────────

    private static function sectionTitle(string $title): string {
        return '<div style="font-size:9px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;border-bottom:1px solid #e5e7eb;padding-bottom:8px;margin:28px 0 16px">'
             . htmlspecialchars($title) . '</div>';
    }

    private static function refBox(string $ref, string $subtitle = ''): string {
        return '<div style="background:#f7f7f5;border-left:4px solid #0a0a0a;padding:16px 20px;margin:20px 0">'
             . '<div style="font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#9ca3af;margin-bottom:6px">Reference</div>'
             . '<div style="font-family:Georgia,serif;font-size:24px;font-weight:500;letter-spacing:.04em">' . htmlspecialchars($ref) . '</div>'
             . ($subtitle ? '<div style="font-size:12px;color:#6b7280;margin-top:4px">' . htmlspecialchars($subtitle) . '</div>' : '')
             . '</div>';
    }

    private static function tableRow(string $label, string $value): string {
        return '<tr>'
             . '<td style="padding:7px 0;color:#6b7280;font-size:13px;width:38%;vertical-align:top">' . htmlspecialchars($label) . '</td>'
             . '<td style="padding:7px 0;font-size:13px;font-weight:600">' . $value . '</td>'
             . '</tr>';
    }

    private static function totalBox(float $grand, float $deposit, float $balance, string $currency = 'USD'): string {
        return '<div style="margin-top:20px">'
             . '<div style="background:#0a0a0a;color:#ffffff;padding:14px 20px;display:flex;justify-content:space-between">'
             . '<span style="font-size:14px;font-weight:600">Grand Total</span>'
             . '<span style="font-family:Georgia,serif;font-size:18px">' . $currency . ' $' . number_format($grand, 0) . '</span>'
             . '</div>'
             . '<div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:14px 20px;margin-top:1px">'
             . '<div style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#166534;margin-bottom:6px">Payment Schedule</div>'
             . '<div style="font-size:13px;color:#166534;margin-bottom:4px"><strong>Deposit (30%): $' . number_format($deposit, 0) . '</strong> — due within 48 hours</div>'
             . '<div style="font-size:13px;color:#166534"><strong>Balance (70%): $' . number_format($balance, 0) . '</strong> — due 45 days before departure</div>'
             . '</div></div>';
    }

    private static function ctaButton(string $text, string $url, string $color = '#0a0a0a'): string {
        return '<a href="' . htmlspecialchars($url) . '" '
             . 'style="display:inline-block;background:' . $color . ';color:#ffffff;padding:13px 28px;'
             . 'font-weight:700;text-decoration:none;font-size:12px;letter-spacing:.1em;text-transform:uppercase;margin:4px 4px 4px 0">'
             . htmlspecialchars($text) . '</a>';
    }

    private static function waButton(string $text = 'Chat on WhatsApp', string $message = ''): string {
        $wa  = self::WA_NUMBER;
        $url = 'https://wa.me/' . $wa . ($message ? '?text=' . rawurlencode($message) : '');
        return self::ctaButton($text, $url, '#25D366');
    }

    private static function divider(): string {
        return '<hr style="border:none;border-top:1px solid #f3f4f6;margin:28px 0">';
    }

    public static function paymentBox(): string {
        $bank = defined('BANK_NAME') ? BANK_NAME : 'Your Bank';
        $acc  = defined('BANK_ACCOUNT_NAME') ? BANK_ACCOUNT_NAME : 'Safari Zangu Ventures';
        $no   = defined('BANK_ACCOUNT_NO')   ? BANK_ACCOUNT_NO   : 'Contact us for details';
        $sw   = defined('BANK_SWIFT')        ? BANK_SWIFT        : '';
        $pp   = defined('PAYPAL_EMAIL')      ? PAYPAL_EMAIL      : 'Contact us';

        return '<div style="margin:20px 0">'
             . '<div style="font-size:9px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;border-bottom:1px solid #e5e7eb;padding-bottom:8px;margin-bottom:14px">Payment Methods</div>'
             . '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr valign="top">'
             . '<td style="width:50%;padding-right:12px">'
             . '<div style="border:1px solid #e5e7eb;padding:14px 16px">'
             . '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px">Bank Transfer</div>'
             . '<div style="font-size:12px;line-height:1.9;color:#374151">'
             . 'Bank: <strong>' . htmlspecialchars($bank) . '</strong><br>'
             . 'Account: <strong>' . htmlspecialchars($acc) . '</strong><br>'
             . 'Number: <strong>' . htmlspecialchars($no) . '</strong>'
             . ($sw ? '<br>SWIFT: <strong>' . htmlspecialchars($sw) . '</strong>' : '')
             . '</div></div></td>'
             . '<td style="width:50%;padding-left:12px">'
             . '<div style="border:1px solid #e5e7eb;padding:14px 16px">'
             . '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px">PayPal</div>'
             . '<div style="font-size:12px;line-height:1.9;color:#374151">PayPal: <strong>' . htmlspecialchars($pp) . '</strong></div>'
             . '</div></td>'
             . '</tr></table>'
             . '<p style="font-size:12px;color:#6b7280;margin:12px 0 0;line-height:1.7">Please include your reference number in the payment description.</p>'
             . '</div>';
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: booking_confirmation
    // Used by: php/book.php, php/convert_quotation.php
    // Sent to: guest
    // ══════════════════════════════════════════════════════════
    private static function tpl_booking_confirmation(array $v): array
    {
        $name       = htmlspecialchars($v['client_name']   ?? $v['full_name']   ?? 'Guest');
        $ref        = $v['booking_ref']  ?? '';
        $package    = htmlspecialchars($v['package_name']  ?? 'Tanzania Safari');
        $date       = !empty($v['travel_date']) ? date('d F Y', strtotime($v['travel_date'])) : 'To be confirmed';
        $travelers  = (int)($v['travelers'] ?? ($v['adults'] ?? 1) + ($v['children'] ?? 0));
        $accLevel   = ucwords(str_replace(['_','-'], ' ', $v['accommodation_level'] ?? 'Mid-Range'));
        $total      = (float)($v['total_amount']   ?? 0);
        $deposit    = (float)($v['deposit_amount'] ?? round($total * 0.3, 2));
        $balance    = round($total - $deposit, 2);
        $trackUrl   = self::SITE_URL . '/track.php?ref=' . urlencode($ref) . '&date=' . urlencode($v['travel_date'] ?? '');
        $waMsg      = 'Hi Safari Zangu, I\'m following up on booking ' . $ref;

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Dear {$name},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          Thank you for booking with <strong>Safari Zangu Ventures</strong>! Your booking has been received and is now pending confirmation. We will be in touch within 24 hours with further details.
        </p>

        " . self::refBox($ref, 'Keep this reference — you\'ll need it to track your booking') . "

        " . self::sectionTitle('Booking Summary') . "
        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='border-collapse:collapse'>
          " . self::tableRow('Package',       '<strong>' . $package . '</strong>') . "
          " . self::tableRow('Accommodation', $accLevel) . "
          " . self::tableRow('Travelers',     $travelers . ' person' . ($travelers !== 1 ? 's' : '')) . "
          " . self::tableRow('Travel Date',   '<strong>' . $date . '</strong>') . "
          " . self::tableRow('Total Amount',  '<strong>USD $' . number_format($total, 0) . '</strong>') . "
        </table>

        " . self::totalBox($total, $deposit, $balance) . "

        " . self::paymentBox() . "

        " . self::divider() . "

        " . self::sectionTitle('Track Your Booking') . "
        <p style='font-size:13px;line-height:1.75;color:#374151;margin:0 0 16px'>
          Your <strong>Booking ID</strong> and <strong>Travel Date</strong> together give you access to your booking status online — no password needed.
        </p>
        <div style='text-align:center;margin:20px 0'>
          " . self::ctaButton('Track My Booking →', $trackUrl) . "
          " . self::waButton('WhatsApp Us', $waMsg) . "
        </div>

        <p style='font-size:12px;color:#9ca3af;line-height:1.7;margin:20px 0 0;border-top:1px solid #f3f4f6;padding-top:16px'>
          If you have any questions, WhatsApp or email us at <a href='mailto:" . self::CONTACT_EMAIL . "' style='color:#0a0a0a'>" . self::CONTACT_EMAIL . "</a>. We respond within 24 hours.
        </p>";

        return [
            'subject' => 'Booking Confirmed — ' . $ref . ' | Safari Zangu Ventures',
            'title'   => 'Booking Confirmation',
            'preview' => 'Your safari booking ' . $ref . ' has been received. Keep this email — your booking reference is inside.',
            'html'    => $html,
            'text'    => "Dear {$name},\n\nThank you for booking with Safari Zangu Ventures!\n\nBooking Reference: {$ref}\nPackage: {$package}\nTravel Date: {$date}\nTotal: USD \$" . number_format($total, 0) . "\nDeposit Due (30%): USD \$" . number_format($deposit, 0) . "\n\nTrack your booking: {$trackUrl}\n\nSafari Zangu Ventures · " . self::CONTACT_PHONE,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: booking_reminder_deposit
    // Used by: php/process_reminders.php (type='deposit_due')
    // Sent to: guest, 48 hours after booking
    // ══════════════════════════════════════════════════════════
    private static function tpl_booking_reminder_deposit(array $v): array
    {
        $name    = htmlspecialchars($v['client_name']  ?? 'Guest');
        $ref     = $v['booking_ref']  ?? '';
        $package = htmlspecialchars($v['package_name'] ?? 'Tanzania Safari');
        $deposit = (float)($v['deposit_amount'] ?? 0);
        $waMsg   = 'Hi Safari Zangu, I\'m contacting you about my deposit for booking ' . $ref;

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Dear {$name},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          This is a friendly reminder that your deposit payment of <strong>USD \$" . number_format($deposit, 0) . "</strong> is due to confirm your safari dates for booking <strong>{$ref}</strong>.
        </p>

        <div style='background:#fef3e2;border:1px solid #fcd34d;padding:18px 20px;margin:20px 0'>
          <div style='font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#92400e;margin-bottom:6px'>Action Required</div>
          <div style='font-size:14px;font-weight:700;color:#92400e'>Deposit of USD \$" . number_format($deposit, 0) . " due now</div>
          <div style='font-size:12px;color:#92400e;margin-top:4px'>Your dates are not confirmed until the deposit is received.</div>
        </div>

        " . self::refBox($ref, $package) . "
        " . self::paymentBox() . "
        " . self::divider() . "
        <div style='text-align:center;margin:20px 0'>
          " . self::waButton('Confirm Payment on WhatsApp', $waMsg) . "
        </div>";

        return [
            'subject' => 'Deposit Due — ' . $ref . ' | Safari Zangu Ventures',
            'title'   => 'Deposit Reminder',
            'preview' => 'Your 30% deposit is due to secure your safari dates.',
            'html'    => $html,
            'text'    => "Dear {$name},\n\nYour deposit of USD \$" . number_format($deposit, 0) . " is due for booking {$ref} ({$package}).\n\nPlease arrange payment to confirm your dates.\n\nWhatsApp: " . self::CONTACT_PHONE . "\nEmail: " . self::CONTACT_EMAIL,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: booking_reminder_balance
    // Used by: php/process_reminders.php (type='balance_due')
    // Sent to: guest, 45 days before travel
    // ══════════════════════════════════════════════════════════
    private static function tpl_booking_reminder_balance(array $v): array
    {
        $name    = htmlspecialchars($v['client_name']  ?? 'Guest');
        $ref     = $v['booking_ref']  ?? '';
        $package = htmlspecialchars($v['package_name'] ?? 'Tanzania Safari');
        $date    = !empty($v['travel_date']) ? date('d F Y', strtotime($v['travel_date'])) : '';
        $balance = (float)($v['balance_due'] ?? 0);
        $waMsg   = 'Hi Safari Zangu, I\'m contacting about my balance payment for booking ' . $ref;

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Dear {$name},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          Your safari is 45 days away — how exciting! This is a reminder that your balance payment is now due to finalise your booking.
        </p>

        <div style='background:#fef3e2;border:1px solid #fcd34d;padding:18px 20px;margin:20px 0'>
          <div style='font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#92400e;margin-bottom:6px'>Balance Due</div>
          <div style='font-size:14px;font-weight:700;color:#92400e'>USD \$" . number_format($balance, 0) . "</div>
          <div style='font-size:12px;color:#92400e;margin-top:4px'>Travel date: <strong>{$date}</strong></div>
        </div>

        " . self::refBox($ref, $package) . "
        " . self::paymentBox() . "
        " . self::divider() . "
        <div style='text-align:center;margin:20px 0'>
          " . self::waButton('Contact Us on WhatsApp', $waMsg) . "
        </div>";

        return [
            'subject' => 'Balance Due — ' . $ref . ' | Safari Zangu Ventures',
            'title'   => 'Balance Payment Reminder',
            'preview' => 'Your safari is 45 days away — your balance payment is now due.',
            'html'    => $html,
            'text'    => "Dear {$name},\n\nYour balance of USD \$" . number_format($balance, 0) . " is due for booking {$ref}.\nTravel date: {$date}\n\nSafari Zangu Ventures · " . self::CONTACT_PHONE,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: booking_reminder_departure
    // Used by: php/process_reminders.php (type='departure')
    // Sent to: guest, 7 days before travel
    // ══════════════════════════════════════════════════════════
    private static function tpl_booking_reminder_departure(array $v): array
    {
        $name    = htmlspecialchars($v['client_name']  ?? 'Guest');
        $ref     = $v['booking_ref']  ?? '';
        $package = htmlspecialchars($v['package_name'] ?? 'Tanzania Safari');
        $date    = !empty($v['travel_date']) ? date('d F Y', strtotime($v['travel_date'])) : '';

        $checklist = [
            'Valid passport (6+ months validity from travel date)',
            'Tanzania entry visa — USD 50, available on arrival or e-visa online',
            'Yellow fever vaccination certificate (required for entry)',
            'Travel insurance with emergency medical evacuation cover',
            'Safari-appropriate clothing (neutral colours — khaki, olive, beige)',
            'Binoculars and camera with spare batteries/memory cards',
            'Insect repellent (DEET 30%+) and SPF 50 sunscreen',
            'Any prescription medications in original packaging',
            'USD cash for tips (guide USD 20–30/day, lodge staff USD 5–10/day)',
        ];

        $checklistHtml = '';
        foreach ($checklist as $item) {
            $checklistHtml .= '<tr><td style="padding:7px 0;font-size:13px;color:#374151;border-bottom:1px solid #f3f4f6">&#10003;&nbsp; ' . htmlspecialchars($item) . '</td></tr>';
        }

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Dear {$name},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          Your Tanzania safari begins in <strong>7 days</strong> — we can't wait to welcome you to Africa! Here's everything you need to know before you travel.
        </p>

        <div style='background:#0a0a0a;color:#ffffff;padding:18px 20px;margin-bottom:20px'>
          <div style='font-size:11px;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:rgba(255,255,255,.5);margin-bottom:4px'>Your departure</div>
          <div style='font-family:Georgia,serif;font-size:20px'>{$date}</div>
          <div style='font-size:12px;color:rgba(255,255,255,.5);margin-top:2px'>{$ref} &middot; {$package}</div>
        </div>

        " . self::sectionTitle('Pre-Departure Checklist') . "
        <table width='100%' cellpadding='0' cellspacing='0' border='0'>
          {$checklistHtml}
        </table>

        " . self::divider() . "

        " . self::sectionTitle('On Arrival in Arusha') . "
        <p style='font-size:13px;line-height:1.75;color:#374151;margin:0 0 12px'>
          Your Safari Zangu guide will meet you at <strong>Kilimanjaro International Airport (JRO)</strong>. Look for a board with your name or our logo. WhatsApp us when you land if you have any trouble.
        </p>
        <div style='text-align:center;margin:20px 0'>
          " . self::waButton('WhatsApp Your Guide', 'Hi Safari Zangu, I\'m arriving soon for booking ' . $ref) . "
        </div>";

        return [
            'subject' => 'Your Safari Departs in 7 Days — ' . $ref . ' | Safari Zangu Ventures',
            'title'   => 'Departure Reminder',
            'preview' => 'Your Tanzania safari is 7 days away. Here\'s your pre-departure checklist.',
            'html'    => $html,
            'text'    => "Dear {$name},\n\nYour safari ({$ref}) departs in 7 days on {$date}.\n\nPre-departure checklist:\n- Valid passport (6+ months)\n- Tanzania visa (USD 50 on arrival)\n- Yellow fever certificate\n- Travel insurance\n- Neutral safari clothing\n- Binoculars, camera\n- Insect repellent + sunscreen\n- Medications\n- USD cash for tips\n\nWhatsApp us: " . self::CONTACT_PHONE,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: booking_reminder_posttrip
    // Used by: php/process_reminders.php (type='post_trip')
    // Sent to: guest, 3 days after return
    // ══════════════════════════════════════════════════════════
    private static function tpl_booking_reminder_posttrip(array $v): array
    {
        $name    = htmlspecialchars($v['client_name']  ?? 'Guest');
        $ref     = $v['booking_ref']  ?? '';
        $package = htmlspecialchars($v['package_name'] ?? 'Tanzania Safari');

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Dear {$name},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          We hope you're home safely and still dreaming of the Serengeti sunsets. It was a privilege to host your Tanzania safari ({$ref}) and we'd love to hear how it went.
        </p>

        " . self::sectionTitle('Share Your Experience') . "
        <p style='font-size:13px;line-height:1.75;color:#374151;margin:0 0 16px'>
          Your feedback helps us improve and lets future travellers know what to expect. Even a few words make a big difference.
        </p>

        <table width='100%' cellpadding='0' cellspacing='0' border='0'><tr valign='top'>
          <td style='width:50%;padding-right:8px'>
            <div style='border:1px solid #e5e7eb;padding:16px;text-align:center'>
              <div style='font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px'>Google Review</div>
              <a href='https://g.page/safari-zangu-ventures/review' style='display:inline-block;background:#4285F4;color:#fff;padding:8px 16px;font-size:11px;font-weight:700;text-decoration:none;letter-spacing:.06em;text-transform:uppercase'>Leave Review →</a>
            </div>
          </td>
          <td style='width:50%;padding-left:8px'>
            <div style='border:1px solid #e5e7eb;padding:16px;text-align:center'>
              <div style='font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px'>TripAdvisor</div>
              <a href='https://www.tripadvisor.com/Attraction_Review-safari-zangu' style='display:inline-block;background:#34E0A1;color:#0a0a0a;padding:8px 16px;font-size:11px;font-weight:700;text-decoration:none;letter-spacing:.06em;text-transform:uppercase'>Leave Review →</a>
            </div>
          </td>
        </tr></table>

        " . self::divider() . "

        <div style='background:#f7f7f5;border:1px solid #e5e7eb;padding:20px;margin-top:8px'>
          <div style='font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;margin-bottom:8px'>Plan Your Next Adventure</div>
          <p style='font-size:13px;line-height:1.75;color:#374151;margin:0 0 12px'>
            Most of our guests return within 3 years — Tanzania only reveals a little of itself on the first visit. Ask us about our loyalty discount for returning clients.
          </p>
          " . self::ctaButton('Explore Our Packages', self::SITE_URL . '/packages.php') . "
          " . self::waButton('Chat About Next Trip', 'Hi Safari Zangu, I\'d like to plan another safari. My last booking was ' . $ref) . "
        </div>";

        return [
            'subject' => 'How Was Your Safari? — ' . $ref . ' | Safari Zangu Ventures',
            'title'   => 'Post-Trip Feedback',
            'preview' => 'We hope you\'re home safely! We\'d love to hear about your Tanzania safari experience.',
            'html'    => $html,
            'text'    => "Dear {$name},\n\nWe hope you're home safely after your Tanzania safari ({$ref})!\n\nWe'd love your feedback. Please leave a Google or TripAdvisor review — it takes 2 minutes and helps future travellers enormously.\n\nThank you for travelling with Safari Zangu Ventures. We hope to see you again.\n\n" . self::CONTACT_PHONE,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: quotation_sent
    // Used by: php/quotation_email.php
    // Sent to: guest (prospective client)
    // ══════════════════════════════════════════════════════════
    private static function tpl_quotation_sent(array $v): array
    {
        $name      = htmlspecialchars($v['client_name']  ?? 'Guest');
        $ref       = $v['quote_ref']     ?? '';
        $package   = htmlspecialchars($v['package_name'] ?? 'Tanzania Safari');
        $grand     = (float)($v['grand_total']   ?? 0);
        $deposit   = (float)($v['deposit_amount']?? round($grand * 0.3, 2));
        $balance   = (float)($v['balance_due']   ?? round($grand - $deposit, 2));
        $perPerson = (float)($v['per_person']    ?? 0);
        $valid     = !empty($v['valid_until']) ? date('d F Y', strtotime($v['valid_until'])) : date('d F Y', strtotime('+14 days'));
        $adults    = (int)($v['adults']    ?? 2);
        $children  = (int)($v['children'] ?? 0);
        $duration  = (int)($v['duration_days'] ?? 5);
        $startDate = !empty($v['start_date']) ? date('d F Y', strtotime($v['start_date'])) : 'Flexible';
        $accLevel  = ucwords(str_replace(['_','-'], ' ', $v['accommodation_level'] ?? 'Mid-Range'));
        $notes     = $v['notes'] ?? '';
        $lineItems = $v['line_items'] ?? [];

        // Build destinations string
        $dests = $v['destinations'] ?? [];
        if (is_string($dests)) $dests = json_decode($dests, true) ?: [$dests];
        $destStr = !empty($dests) ? implode(' → ', $dests) : 'Tanzania';

        // Line items table
        $liHtml = '';
        if (!empty($lineItems)) {
            foreach ($lineItems as $item) {
                $liHtml .= '<tr>'
                  . '<td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;font-size:13px">' . htmlspecialchars($item['description'] ?? $item['desc'] ?? '') . '</td>'
                  . '<td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;font-size:12px;color:#6b7280;text-align:center">' . htmlspecialchars($item['quantity_str'] ?? $item['qty'] ?? '1') . '</td>'
                  . '<td style="padding:8px 12px;border-bottom:1px solid #f3f4f6;font-size:13px;font-weight:600;text-align:right">$' . number_format((float)($item['amount'] ?? 0), 0) . '</td>'
                  . '</tr>';
            }
        }

        $waMsg  = 'Hi Safari Zangu, I\'d like to accept quotation ' . $ref . '.';
        $mailto = self::CONTACT_EMAIL;

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Dear {$name},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          Thank you for your enquiry with Safari Zangu Ventures. Please find your personalised safari quotation below, carefully prepared based on your requirements.
        </p>

        " . self::refBox($ref, 'Valid until ' . $valid) . "

        " . self::sectionTitle('Safari Overview') . "
        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='border-collapse:collapse'>
          " . self::tableRow('Destination',    $destStr) . "
          " . self::tableRow('Duration',       $duration . ' nights') . "
          " . self::tableRow('Start Date',     $startDate) . "
          " . self::tableRow('Travelers',      $adults . ' adult' . ($adults !== 1 ? 's' : '') . ($children > 0 ? ' + ' . $children . ' child' . ($children !== 1 ? 'ren' : '') : '')) . "
          " . self::tableRow('Accommodation',  $accLevel) . "
        </table>

        " . (!empty($liHtml) ? self::sectionTitle('Cost Breakdown') . "
        <table width='100%' cellpadding='0' cellspacing='0' border='0'>
          <thead>
            <tr style='border-bottom:2px solid #0a0a0a'>
              <th style='padding:8px 12px;text-align:left;font-size:9px;letter-spacing:.15em;text-transform:uppercase;color:#6b7280'>Description</th>
              <th style='padding:8px 12px;text-align:center;font-size:9px;letter-spacing:.15em;text-transform:uppercase;color:#6b7280'>Quantity</th>
              <th style='padding:8px 12px;text-align:right;font-size:9px;letter-spacing:.15em;text-transform:uppercase;color:#6b7280'>Amount</th>
            </tr>
          </thead>
          <tbody>{$liHtml}</tbody>
        </table>" : '') . "

        " . self::totalBox($grand, $deposit, $balance) . "

        " . ($perPerson > 0 ? "<p style='font-size:13px;color:#6b7280;text-align:right;margin:4px 0 20px'>USD \$" . number_format($perPerson, 0) . " per person</p>" : '') . "

        " . self::paymentBox() . "

        " . (!empty($notes) ? self::sectionTitle('Notes & Inclusions') . "
        <div style='border:1px solid #e5e7eb;padding:16px;background:#f9f9f8;font-size:13px;line-height:1.8;white-space:pre-wrap'>" . htmlspecialchars($notes) . "</div>" : '') . "

        " . self::divider() . "

        <p style='font-size:13px;line-height:1.75;color:#374151;margin:0 0 16px;text-align:center'>
          <strong>Ready to confirm your safari?</strong> Reply to accept or WhatsApp us directly.
        </p>
        <div style='text-align:center;margin:20px 0'>
          " . self::waButton('Accept on WhatsApp', $waMsg) . "
          " . self::ctaButton('Reply by Email', 'mailto:' . $mailto . '?subject=' . rawurlencode('Re: Quotation ' . $ref)) . "
        </div>

        <p style='font-size:11px;color:#9ca3af;text-align:center;margin:16px 0 0'>
          This quotation is valid until <strong>{$valid}</strong> and subject to availability.
        </p>";

        return [
            'subject' => 'Your Safari Quotation ' . $ref . ' — Safari Zangu Ventures',
            'title'   => 'Safari Quotation',
            'preview' => 'Your personalised Tanzania safari quotation is ready. Grand total: USD $' . number_format($grand, 0) . '.',
            'html'    => $html,
            'text'    => "Dear {$name},\n\nYour safari quotation {$ref} is ready.\n\nPackage: {$package}\nDuration: {$duration} nights\nGrand Total: USD \$" . number_format($grand, 0) . "\nPer Person: USD \$" . number_format($perPerson, 0) . "\nValid Until: {$valid}\n\nWhatsApp us to confirm: " . self::CONTACT_PHONE . "\n\nSafari Zangu Ventures",
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: quotation_accepted
    // Used by: admin/quotations.php when status changed to 'accepted'
    // Sent to: guest
    // ══════════════════════════════════════════════════════════
    private static function tpl_quotation_accepted(array $v): array
    {
        $name    = htmlspecialchars($v['client_name'] ?? 'Guest');
        $ref     = $v['quote_ref'] ?? '';
        $deposit = (float)($v['deposit_amount'] ?? 0);
        $waMsg   = 'Hi Safari Zangu, I\'m ready to pay my deposit for quotation ' . $ref;

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Dear {$name},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          Wonderful news — we've received your acceptance of quotation <strong>{$ref}</strong>. Your Tanzania safari adventure is about to begin!
        </p>

        <div style='background:#f0fdf4;border:1px solid #bbf7d0;padding:18px 20px;margin:20px 0'>
          <div style='font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#166534;margin-bottom:6px'>Next Step</div>
          <div style='font-size:14px;font-weight:700;color:#166534'>Pay deposit of USD \$" . number_format($deposit, 0) . " to confirm your dates</div>
          <div style='font-size:12px;color:#166534;margin-top:4px'>Your dates are held for 48 hours pending receipt of deposit.</div>
        </div>

        " . self::refBox($ref) . "
        " . self::paymentBox() . "
        " . self::divider() . "
        <div style='text-align:center;margin:20px 0'>
          " . self::waButton('Confirm Payment on WhatsApp', $waMsg) . "
        </div>";

        return [
            'subject' => 'Quotation Accepted — Next Steps | Safari Zangu Ventures',
            'title'   => 'Quotation Accepted',
            'preview' => 'Your quotation ' . $ref . ' has been accepted. Pay your deposit to secure your safari dates.',
            'html'    => $html,
            'text'    => "Dear {$name},\n\nYour quotation {$ref} has been accepted. Please pay your deposit of USD \$" . number_format($deposit, 0) . " within 48 hours to confirm your dates.\n\nSafari Zangu Ventures · " . self::CONTACT_PHONE,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: quotation_expired
    // Used by: scheduled cron / admin manual action
    // Sent to: guest
    // ══════════════════════════════════════════════════════════
    private static function tpl_quotation_expired(array $v): array
    {
        $name = htmlspecialchars($v['client_name'] ?? 'Guest');
        $ref  = $v['quote_ref'] ?? '';

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Dear {$name},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          Your safari quotation <strong>{$ref}</strong> has expired. Prices and availability change with the seasons, but we'd love to prepare a fresh proposal for you.
        </p>

        <div style='background:#f7f7f5;border:1px solid #e5e7eb;padding:20px;text-align:center'>
          <p style='font-size:13px;color:#374151;margin:0 0 16px'>Ready to re-enquire? We'll have a new quotation ready within 24 hours.</p>
          " . self::ctaButton('Request New Quotation', self::SITE_URL . '/contact.php') . "
          " . self::waButton('WhatsApp Us Now', 'Hi Safari Zangu, my quotation ' . $ref . ' has expired. Could you prepare a new one?') . "
        </div>";

        return [
            'subject' => 'Your Quotation Has Expired — Safari Zangu Ventures',
            'title'   => 'Quotation Expired',
            'preview' => 'Your quotation ' . $ref . ' has expired. Request a fresh proposal.',
            'html'    => $html,
            'text'    => "Dear {$name},\n\nYour quotation {$ref} has expired. Request a new one at " . self::SITE_URL . "/contact.php or WhatsApp " . self::CONTACT_PHONE,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: contact_acknowledgement
    // Used by: php/contact.php (auto-reply to guest)
    // Sent to: guest
    // ══════════════════════════════════════════════════════════
    private static function tpl_contact_acknowledgement(array $v): array
    {
        $name    = htmlspecialchars($v['client_name'] ?? $v['name'] ?? 'Guest');
        $subject = htmlspecialchars($v['subject'] ?? 'your enquiry');

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Dear {$name},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          Thank you for contacting Safari Zangu Ventures. We've received your message about <em>\"{$subject}\"</em> and will respond within <strong>24 hours</strong>.
        </p>

        <div style='background:#f7f7f5;border:1px solid #e5e7eb;padding:20px;margin:20px 0'>
          <div style='font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;margin-bottom:12px'>While You Wait</div>
          <p style='font-size:13px;line-height:1.75;color:#374151;margin:0 0 12px'>
            For a faster response, WhatsApp us directly — our team is available 7 days a week during East Africa business hours (GMT+3).
          </p>
          " . self::waButton('WhatsApp Us Now', 'Hi Safari Zangu, I sent an enquiry and wanted to follow up.') . "
        </div>

        " . self::divider() . "

        <div style='text-align:center;margin:20px 0'>
          " . self::ctaButton('Browse Our Packages', self::SITE_URL . '/packages.php') . "
        </div>

        <p style='font-size:12px;color:#9ca3af;line-height:1.7;margin:20px 0 0'>
          This is an automated confirmation of your message. Please do not reply to this email — use the WhatsApp link above or email us at <a href='mailto:" . self::CONTACT_EMAIL . "' style='color:#0a0a0a'>" . self::CONTACT_EMAIL . "</a>.
        </p>";

        return [
            'subject' => 'We\'ve Received Your Message — Safari Zangu Ventures',
            'title'   => 'Message Received',
            'preview' => 'Thank you for contacting Safari Zangu Ventures. We\'ll respond within 24 hours.',
            'html'    => $html,
            'text'    => "Dear {$name},\n\nThank you for contacting Safari Zangu Ventures. We've received your message and will respond within 24 hours.\n\nFor faster response, WhatsApp us: " . self::CONTACT_PHONE,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: contact_admin_notification
    // Used by: php/contact.php (notify admin of new message)
    // Sent to: admin
    // ══════════════════════════════════════════════════════════
    private static function tpl_contact_admin_notification(array $v): array
    {
        $name    = htmlspecialchars($v['client_name'] ?? $v['name'] ?? '');
        $email   = htmlspecialchars($v['client_email'] ?? $v['email'] ?? '');
        $phone   = htmlspecialchars($v['phone'] ?? '');
        $subject = htmlspecialchars($v['subject'] ?? 'General Enquiry');
        $message = nl2br(htmlspecialchars($v['message'] ?? ''));
        $adminUrl = (defined('APP_URL') ? APP_URL : 'https://www.safarizanguven.com') . '/admin/contact_messages.php';

        $html = "
        <p style='font-size:14px;margin:0 0 16px;font-weight:600'>New Contact Form Submission</p>

        " . self::sectionTitle('Contact Details') . "
        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='border-collapse:collapse'>
          " . self::tableRow('Name',    $name) . "
          " . self::tableRow('Email',   '<a href=\"mailto:' . $email . '\" style=\"color:#0a0a0a\">' . $email . '</a>') . "
          " . self::tableRow('Phone',   $phone ?: '—') . "
          " . self::tableRow('Subject', $subject) . "
        </table>

        " . self::sectionTitle('Message') . "
        <div style='border:1px solid #e5e7eb;padding:16px;background:#f9f9f8;font-size:13px;line-height:1.8'>{$message}</div>

        " . self::divider() . "
        <div style='text-align:center;margin:16px 0'>
          " . self::ctaButton('View in Admin →', $adminUrl) . "
          <a href='mailto:{$email}?subject=" . rawurlencode('Re: ' . strip_tags($subject)) . "' style='display:inline-block;background:#f3f4f6;color:#0a0a0a;padding:13px 28px;font-weight:700;text-decoration:none;font-size:12px;letter-spacing:.1em;text-transform:uppercase;margin:4px'>Reply by Email</a>
        </div>";

        return [
            'subject' => '[Contact] ' . strip_tags($subject) . ' — ' . strip_tags($name),
            'title'   => 'New Contact Message',
            'preview' => 'New message from ' . strip_tags($name) . ': ' . strip_tags($subject),
            'html'    => $html,
            'text'    => "New contact from {$name} ({$email})\nSubject: {$subject}\n\n" . strip_tags($v['message'] ?? ''),
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: custom_request_ack
    // Used by: php/custom_safari.php (auto-reply to guest)
    // Sent to: guest
    // ══════════════════════════════════════════════════════════
    private static function tpl_custom_request_ack(array $v): array
    {
        $name = htmlspecialchars($v['client_name'] ?? $v['full_name'] ?? 'Guest');

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Dear {$name},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          Thank you for reaching out to Safari Zangu Ventures with your custom safari request. Our specialist team is reviewing your requirements and will prepare a personalised itinerary proposal within <strong>24 hours</strong>.
        </p>

        <div style='background:#0a0a0a;color:#ffffff;padding:24px;margin:20px 0'>
          <div style='font-size:11px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:12px'>What Happens Next</div>
          <table width='100%' cellpadding='0' cellspacing='0' border='0'>
            <tr>
              <td style='width:32px;vertical-align:top;padding-top:2px'>
                <div style='background:rgba(255,255,255,.15);width:24px;height:24px;text-align:center;line-height:24px;font-size:11px;font-weight:700'>1</div>
              </td>
              <td style='padding:0 0 12px 12px;font-size:13px;color:rgba(255,255,255,.75);line-height:1.6'>We review your requirements and check availability with our partner lodges.</td>
            </tr>
            <tr>
              <td style='width:32px;vertical-align:top;padding-top:2px'>
                <div style='background:rgba(255,255,255,.15);width:24px;height:24px;text-align:center;line-height:24px;font-size:11px;font-weight:700'>2</div>
              </td>
              <td style='padding:0 0 12px 12px;font-size:13px;color:rgba(255,255,255,.75);line-height:1.6'>We prepare a detailed day-by-day itinerary tailored to your group.</td>
            </tr>
            <tr>
              <td style='width:32px;vertical-align:top;padding-top:2px'>
                <div style='background:rgba(255,255,255,.15);width:24px;height:24px;text-align:center;line-height:24px;font-size:11px;font-weight:700'>3</div>
              </td>
              <td style='padding:0 0 0 12px;font-size:13px;color:rgba(255,255,255,.75);line-height:1.6'>You receive a full quotation within 24 hours — with options to refine it.</td>
            </tr>
          </table>
        </div>

        " . self::divider() . "
        <p style='font-size:13px;line-height:1.75;color:#374151;margin:0 0 12px;text-align:center'>
          In a hurry? WhatsApp us and we'll respond immediately.
        </p>
        <div style='text-align:center;margin:16px 0'>
          " . self::waButton('WhatsApp Us Now', 'Hi Safari Zangu, I just submitted a custom safari request and wanted to follow up.') . "
        </div>";

        return [
            'subject' => 'Custom Safari Request Received — Safari Zangu Ventures',
            'title'   => 'Custom Request Received',
            'preview' => 'We\'ve received your custom safari request and will send a personalised proposal within 24 hours.',
            'html'    => $html,
            'text'    => "Dear {$name},\n\nThank you for your custom safari request. We'll prepare a personalised proposal within 24 hours.\n\nFor faster response, WhatsApp us: " . self::CONTACT_PHONE,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: custom_request_admin
    // Used by: php/custom_safari.php (notify admin)
    // Sent to: admin
    // ══════════════════════════════════════════════════════════
    private static function tpl_custom_request_admin(array $v): array
    {
        $name      = htmlspecialchars($v['full_name']   ?? '');
        $email     = htmlspecialchars($v['email']        ?? '');
        $phone     = htmlspecialchars($v['phone']        ?? '');
        $country   = htmlspecialchars($v['country']      ?? '');
        $adults    = (int)($v['adults']    ?? 0);
        $children  = (int)($v['children'] ?? 0);
        $duration  = htmlspecialchars($v['duration_days'] ?? '');
        $startDate = !empty($v['start_date']) ? date('d F Y', strtotime($v['start_date'])) : '—';
        $budget    = htmlspecialchars($v['total_budget'] ?? '');
        $dests     = $v['destinations'] ?? '';
        if (is_array($dests)) $dests = implode(', ', $dests);
        $dests     = htmlspecialchars($dests);
        $message   = nl2br(htmlspecialchars($v['message'] ?? ''));
        $adminUrl  = (defined('APP_URL') ? APP_URL : 'https://www.safarizanguven.com') . '/admin/custom_requests.php';
        $quoteUrl  = (defined('APP_URL') ? APP_URL : 'https://www.safarizanguven.com') . '/admin/quotation_new.php';

        $html = "
        <p style='font-size:14px;margin:0 0 16px;font-weight:600'>New Custom Safari Request</p>

        " . self::sectionTitle('Client Details') . "
        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='border-collapse:collapse'>
          " . self::tableRow('Name',       $name) . "
          " . self::tableRow('Email',      '<a href=\"mailto:' . $email . '\" style=\"color:#0a0a0a\">' . $email . '</a>') . "
          " . self::tableRow('Phone',      $phone ?: '—') . "
          " . self::tableRow('Country',    $country ?: '—') . "
        </table>

        " . self::sectionTitle('Safari Requirements') . "
        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='border-collapse:collapse'>
          " . self::tableRow('Destinations', $dests ?: '—') . "
          " . self::tableRow('Group Size',   ($adults ? $adults . ' adults' : '—') . ($children ? ' + ' . $children . ' children' : '')) . "
          " . self::tableRow('Duration',     $duration ? $duration . ' days' : '—') . "
          " . self::tableRow('Start Date',   $startDate) . "
          " . self::tableRow('Budget',       $budget ?: '—') . "
        </table>

        " . (!empty($message) ? self::sectionTitle('Message') . "
        <div style='border:1px solid #e5e7eb;padding:16px;background:#f9f9f8;font-size:13px;line-height:1.8'>{$message}</div>" : '') . "

        " . self::divider() . "
        <div style='text-align:center;margin:16px 0'>
          " . self::ctaButton('View Request →', $adminUrl) . "
          " . self::ctaButton('Create Quotation →', $quoteUrl) . "
        </div>";

        return [
            'subject' => '[Custom Safari] ' . strip_tags($name) . ' — ' . strip_tags($country),
            'title'   => 'New Custom Safari Request',
            'preview' => 'New custom safari request from ' . strip_tags($name) . ' (' . strip_tags($country) . ').',
            'html'    => $html,
            'text'    => "New custom safari request from {$name} ({$email})\nCountry: {$country}\nDestinations: {$dests}\nGroup: " . ($adults ? $adults . ' adults' : '') . "\nBudget: {$budget}\n\n" . strip_tags($v['message'] ?? ''),
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: lead_converted
    // Used by: php/leads_api.php (when lead.pipeline_stage = 'booked')
    // Sent to: agent
    // ══════════════════════════════════════════════════════════
    private static function tpl_lead_converted(array $v): array
    {
        $agentName   = htmlspecialchars($v['agent_name']   ?? 'Agent');
        $clientName  = htmlspecialchars($v['client_name']  ?? '');
        $package     = htmlspecialchars($v['package_name'] ?? 'Tanzania Safari');
        $bookingVal  = (float)($v['booking_value']    ?? 0);
        $commRate    = (float)($v['commission_rate']   ?? 10);
        $commAmount  = (float)($v['commission_amount'] ?? round($bookingVal * $commRate / 100, 2));
        $adminUrl    = (defined('APP_URL') ? APP_URL : 'https://www.safarizanguven.com') . '/admin/leads.php';

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Hi {$agentName},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          Great news — your lead <strong>{$clientName}</strong> has been converted to a confirmed booking!
        </p>

        <div style='background:#0a0a0a;color:#ffffff;padding:24px;margin:20px 0'>
          <div style='font-size:11px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:12px'>Booking Summary</div>
          <table width='100%' cellpadding='0' cellspacing='0' border='0'>
            <tr><td style='color:rgba(255,255,255,.5);font-size:13px;padding:5px 0;width:40%'>Client</td><td style='color:#fff;font-size:13px;font-weight:600'>{$clientName}</td></tr>
            <tr><td style='color:rgba(255,255,255,.5);font-size:13px;padding:5px 0'>Package</td><td style='color:#fff;font-size:13px;font-weight:600'>{$package}</td></tr>
            <tr><td style='color:rgba(255,255,255,.5);font-size:13px;padding:5px 0'>Booking Value</td><td style='color:#fff;font-size:13px;font-weight:600'>USD \$" . number_format($bookingVal, 0) . "</td></tr>
            <tr><td style='color:rgba(255,255,255,.5);font-size:13px;padding:5px 0'>Commission ({$commRate}%)</td><td style='font-family:Georgia,serif;font-size:18px;color:#4ade80;font-weight:500'>USD \$" . number_format($commAmount, 0) . "</td></tr>
          </table>
        </div>

        <p style='font-size:13px;line-height:1.75;color:#374151;margin:0 0 16px'>
          Your commission will be reviewed and approved by the admin team. You'll receive another notification when it's approved and when payment is made.
        </p>
        <div style='text-align:center;margin:16px 0'>
          " . self::ctaButton('View My Leads →', $adminUrl) . "
        </div>";

        return [
            'subject' => 'Lead Converted — Commission USD $' . number_format($commAmount, 0) . ' | Safari Zangu Ventures',
            'title'   => 'Lead Converted to Booking',
            'preview' => "Your lead {$clientName} has been converted. Your commission is USD \$" . number_format($commAmount, 0) . '.',
            'html'    => $html,
            'text'    => "Hi {$agentName},\n\nYour lead {$clientName} has been converted to a booking!\n\nPackage: {$package}\nBooking Value: USD \$" . number_format($bookingVal, 0) . "\nYour Commission ({$commRate}%): USD \$" . number_format($commAmount, 0) . "\n\nLog in to view: " . self::SITE_URL . '/admin/leads.php',
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: commission_approved
    // Used by: admin/commissions.php (when status → approved)
    // Sent to: agent
    // ══════════════════════════════════════════════════════════
    private static function tpl_commission_approved(array $v): array
    {
        $agentName  = htmlspecialchars($v['agent_name']    ?? 'Agent');
        $clientName = htmlspecialchars($v['client_name']   ?? '');
        $amount     = (float)($v['commission_amount'] ?? 0);

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Hi {$agentName},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          Your commission for <strong>{$clientName}</strong> has been approved. Payment will be processed shortly.
        </p>

        <div style='background:#f0fdf4;border:1px solid #bbf7d0;padding:24px;text-align:center;margin:20px 0'>
          <div style='font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#166534;margin-bottom:8px'>Commission Approved</div>
          <div style='font-family:Georgia,serif;font-size:32px;font-weight:500;color:#166534'>USD \$" . number_format($amount, 2) . "</div>
        </div>

        <p style='font-size:13px;color:#374151;line-height:1.75;margin:0'>
          Please ensure your bank details on file are up to date. Contact the admin team if you have any questions about payment timing.
        </p>";

        return [
            'subject' => 'Commission Approved — USD $' . number_format($amount, 2) . ' | Safari Zangu Ventures',
            'title'   => 'Commission Approved',
            'preview' => "Your commission of USD \$" . number_format($amount, 2) . ' has been approved.',
            'html'    => $html,
            'text'    => "Hi {$agentName},\n\nYour commission of USD \$" . number_format($amount, 2) . " for {$clientName} has been approved. Payment will be processed shortly.",
        ];
    }

    // ══════════════════════════════════════════════════════════
    // TEMPLATE: commission_paid
    // Used by: admin/commissions.php (when status → paid)
    // Sent to: agent
    // ══════════════════════════════════════════════════════════
    private static function tpl_commission_paid(array $v): array
    {
        $agentName  = htmlspecialchars($v['agent_name']    ?? 'Agent');
        $clientName = htmlspecialchars($v['client_name']   ?? '');
        $amount     = (float)($v['commission_amount'] ?? 0);
        $paidDate   = date('d F Y');

        $html = "
        <p style='font-size:15px;margin:0 0 8px'>Hi {$agentName},</p>
        <p style='font-size:14px;line-height:1.8;color:#374151;margin:0 0 20px'>
          Your commission payment for <strong>{$clientName}</strong> has been processed. Please allow 1–3 business days for the transfer to appear in your account.
        </p>

        <div style='background:#0a0a0a;color:#ffffff;padding:24px;text-align:center;margin:20px 0'>
          <div style='font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:8px'>Payment Sent</div>
          <div style='font-family:Georgia,serif;font-size:32px;font-weight:500'>USD \$" . number_format($amount, 2) . "</div>
          <div style='font-size:12px;color:rgba(255,255,255,.4);margin-top:6px'>{$paidDate}</div>
        </div>

        <p style='font-size:13px;color:#374151;line-height:1.75;margin:0 0 12px'>
          Thank you for bringing this client to Safari Zangu Ventures. Keep bringing in great leads — we look forward to working with you on many more safaris.
        </p>
        " . self::waButton('Contact the Admin Team', 'Hi Safari Zangu admin team, I have a question about my commission payment.') . "";

        return [
            'subject' => 'Commission Payment Sent — USD $' . number_format($amount, 2) . ' | Safari Zangu Ventures',
            'title'   => 'Commission Paid',
            'preview' => "Your commission of USD \$" . number_format($amount, 2) . ' has been sent. Allow 1-3 business days.',
            'html'    => $html,
            'text'    => "Hi {$agentName},\n\nYour commission of USD \$" . number_format($amount, 2) . " for {$clientName} has been paid ({$paidDate}). Allow 1-3 business days.\n\nThank you for your work!",
        ];
    }
}
