<?php
namespace App\Services;

/**
 * Sends the Invoice + Itinerary PDFs to a client the moment a booking's
 * payment_status actually transitions to fully_paid — triggered from
 * admin/bookings.php's update_status handler, never called with a booking
 * that isn't genuinely fully paid (PdfRenderer's *Invoice() methods also
 * re-verify this themselves before honoring the invoice=1 flag).
 */
class InvoiceMailer
{
    public static function sendForBooking(array $b): bool
    {
        $ref = $b['booking_ref'] ?? '';
        if (!$ref || empty($b['email'])) return false;

        $attachments = [];
        if (!empty($b['quotation_id'])) {
            $pdf = PdfRenderer::renderQuotationInvoice((int)$b['quotation_id'], 'Invoice-' . $ref);
            if ($pdf) $attachments[] = $pdf;
            $itin = PdfRenderer::renderQuotationItinerary((int)$b['quotation_id'], 'Itinerary-' . $ref);
            if ($itin) $attachments[] = $itin;
        } else {
            $pdf = PdfRenderer::renderBookingInvoice($ref, 'Invoice-' . $ref);
            if ($pdf) $attachments[] = $pdf;
            $itin = PdfRenderer::renderBookingItinerary($ref, 'Itinerary-' . $ref);
            if ($itin) $attachments[] = $itin;
        }
        if (!$attachments) return false;

        $html    = self::buildEmailHtml($b);
        $subject = 'Invoice — ' . $ref . ' | Safari Zangu Ventures (Paid in Full)';
        $id = \App\Mail\Mailer::queueRaw($b['email'], $b['full_name'] ?? '', $subject, $html, $attachments, 'invoice_paid');
        return $id !== false;
    }

    private static function buildEmailHtml(array $b): string
    {
        $ref      = htmlspecialchars($b['booking_ref']);
        $name     = htmlspecialchars($b['full_name'] ?? '');
        $total    = number_format((float)($b['total_amount'] ?? 0), 2);
        $trackUrl = rtrim(APP_URL, '/') . '/track.php?ref=' . urlencode($b['booking_ref']) . '&date=' . urlencode($b['travel_date'] ?? '');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:'Times New Roman',Times,'Liberation Serif',serif">
<div style="max-width:640px;margin:24px auto;background:#fff;border:1px solid #e0e0e0">

  <div style="background:#166534;padding:24px 32px;text-align:center">
    <div style="font-size:30px;font-weight:700;color:#fff;letter-spacing:.32em">SZV</div>
    <div style="font-size:11px;color:#fff;letter-spacing:.15em;text-transform:uppercase;margin-top:8px">Payment Received — Invoice Attached</div>
  </div>

  <div style="padding:32px">
    <p style="font-size:15px;margin-top:0">Dear {$name},</p>
    <p style="font-size:14px;line-height:1.7;color:#0a0a0a">
      We've received your payment in full for booking <strong>{$ref}</strong>. Thank you for choosing Safari Zangu Ventures —
      your Invoice and Itinerary PDFs are attached to this email for your records.
    </p>

    <div style="background:#f0fdf4;border-left:4px solid #166534;padding:16px 20px;margin:24px 0">
      <div style="font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:#0a0a0a;margin-bottom:4px">Total Paid</div>
      <div style="font-size:28px;font-weight:700;letter-spacing:.06em;color:#0a0a0a">USD \${$total}</div>
      <div style="font-size:11px;color:#0a0a0a;margin-top:6px">Balance Due: \$0.00</div>
    </div>

    <div style="margin-top:28px;text-align:center">
      <a href="{$trackUrl}"
         style="display:inline-block;background:#0a0a0a;color:#fff;text-decoration:none;padding:14px 32px;font-size:12px;letter-spacing:.1em;text-transform:uppercase;font-weight:600">
        Track My Booking
      </a>
    </div>

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
    }
}
