<?php
/**
 * php/quotation_email.php
 * Send a quotation by email to the client via PHPMailer.
 * Called from admin/quotations.php (POST form) or admin/quotation_new.php.
 */
ob_start();
require_once __DIR__ . '/../app/bootstrap.php';

use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

// Only the POST form path (admin/quotations.php) carries a CSRF token —
// the existing GET "Send Email" links (admin/quotation_new.php) predate
// this check and stay as they were.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Middleware\CsrfMiddleware::validateOrDie();
}

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

// ── Build line items HTML — grouped by category ────────────────
// NOTE: the previous version read $item['desc'] / $item['qty'], but the
// data actually stored (see api/itinerary.php's quote_items action) uses
// 'description' / 'quantity_str' — those fields were always empty before
// this fix; only the dollar amount was ever rendering.
$categoryLabels = [
    'accommodation' => 'Accommodation',
    'transport'     => 'Transport',
    'park_fee'      => 'Park Fees',
    'activity'      => 'Activities & Meals',
    'other'         => 'Other Items',
    'service_fee'   => 'Service & Guide Fees',
];

$grouped = [];
foreach ($lineItems as $item) {
    $cat = $item['category'] ?? 'other';
    $grouped[$cat][] = $item;
}

$renderItemRow = function (array $item): string {
    return '<tr>
        <td style="padding:.5rem .75rem;border-bottom:1px solid #f3f4f6;font-size:.85rem">'
            . htmlspecialchars($item['description'] ?? '') . '</td>
        <td style="padding:.5rem .75rem;border-bottom:1px solid #f3f4f6;text-align:center;font-size:.82rem;color:#6b7280">'
            . htmlspecialchars((string)($item['quantity_str'] ?? '')) . '</td>
        <td style="padding:.5rem .75rem;border-bottom:1px solid #f3f4f6;text-align:right;font-weight:600;font-size:.85rem">$'
            . number_format((float)($item['amount'] ?? 0), 0) . '</td>
    </tr>';
};

$lineItemsHtml = '';
foreach ($categoryLabels as $catKey => $catLabel) {
    if (empty($grouped[$catKey])) continue;
    $lineItemsHtml .= '<tr><td colspan="3" style="padding:.6rem .75rem .3rem;font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af">' . $catLabel . '</td></tr>';
    foreach ($grouped[$catKey] as $item) {
        $lineItemsHtml .= $renderItemRow($item);
    }
    unset($grouped[$catKey]);
}
// Safety net: any category not in the known list above still renders,
// ungrouped, rather than silently disappearing from the email.
foreach ($grouped as $items) {
    foreach ($items as $item) {
        $lineItemsHtml .= $renderItemRow($item);
    }
}

// ── Day-by-day itinerary breakdown (from the quotation's own
//    Day-by-Day Itinerary, or a linked template as a fallback) ──
// The quotation's own itinerary column always wins when present — it's
// what the admin actually edited and what pricing was computed from
// (see admin/quotation_new.php's save handler, which never re-fetches
// itineraries.days live). itinerary_id only matters for older
// quotations saved before that column existed; for those, this IS
// computed live from itineraries.days, so editing the template after
// such a quotation was sent will change what a re-send shows.
$dayByDayHtml = '';
if (!empty($q['itinerary']) || !empty($q['itinerary_id'])) {
    if (!empty($q['itinerary'])) {
        $days = json_decode($q['itinerary'], true) ?: [];
    } else {
        try {
            $is = $db->prepare("SELECT days FROM itineraries WHERE id=?");
            $is->execute([(int)$q['itinerary_id']]);
            $itinRow = $is->fetch();
            $days = $itinRow ? (json_decode($itinRow['days'] ?? '[]', true) ?: []) : [];
        } catch (Exception $e) {
            $days = [];
        }
    }

    $adultsQ   = (int)($q['adults']   ?? 0);
    $childrenQ = (int)($q['children'] ?? 0);
    $paxQ      = max(1, $adultsQ + $childrenQ);

    foreach ($days as $day) {
        $dayNum   = $day['day']      ?? '?';
        $title    = $day['title']    ?? '';
        $location = $day['location'] ?? '';
        $rows     = '';
        $dayTotal = 0;

        $accName = trim($day['accommodation_name'] ?? '');
        $accRate = (float)($day['accommodation_rate'] ?? 0);
        $accRooms= (int)($day['accommodation_rooms'] ?? 1) ?: 1;
        if ($accName && $accRate > 0) {
            $amt = $accRate * $accRooms;
            $rows .= '<tr><td style="padding:.3rem .6rem;font-size:.78rem;color:#374151">Accommodation – ' . htmlspecialchars($accName) . '</td><td style="padding:.3rem .6rem;font-size:.78rem;text-align:right">$' . number_format($amt, 0) . '</td></tr>';
            $dayTotal += $amt;
        }

        $vehName = trim($day['vehicle_name'] ?? '');
        $vehRate = (float)($day['vehicle_rate'] ?? 0);
        if ($vehName && $vehRate > 0) {
            $rows .= '<tr><td style="padding:.3rem .6rem;font-size:.78rem;color:#374151">' . htmlspecialchars($vehName) . '</td><td style="padding:.3rem .6rem;font-size:.78rem;text-align:right">$' . number_format($vehRate, 0) . '</td></tr>';
            $dayTotal += $vehRate;
        }

        $parkName = trim($day['park_fee_name'] ?? '');
        $parkFee  = (float)($day['park_adult_fee'] ?? 0);
        if ($parkName && $parkFee > 0) {
            $amt = $parkFee * max(1, $adultsQ);
            $rows .= '<tr><td style="padding:.3rem .6rem;font-size:.78rem;color:#374151">' . htmlspecialchars($parkName) . ' Entry Fees</td><td style="padding:.3rem .6rem;font-size:.78rem;text-align:right">$' . number_format($amt, 0) . '</td></tr>';
            $dayTotal += $amt;
        }

        foreach ($day['activities'] ?? [] as $act) {
            $actName = trim($act['name'] ?? '');
            $price   = (float)($act['price_pp'] ?? 0);
            $type    = $act['type'] ?? 'included';
            if (!$actName || $type === 'optional' || $price <= 0) continue;
            $amt = $price * $paxQ;
            $rows .= '<tr><td style="padding:.3rem .6rem;font-size:.78rem;color:#374151">' . htmlspecialchars($actName) . '</td><td style="padding:.3rem .6rem;font-size:.78rem;text-align:right">$' . number_format($amt, 0) . '</td></tr>';
            $dayTotal += $amt;
        }

        $meals = (float)($day['meals_cost_pp'] ?? 0);
        if ($meals > 0) {
            $amt = $meals * $paxQ;
            $rows .= '<tr><td style="padding:.3rem .6rem;font-size:.78rem;color:#374151">Meals</td><td style="padding:.3rem .6rem;font-size:.78rem;text-align:right">$' . number_format($amt, 0) . '</td></tr>';
            $dayTotal += $amt;
        }

        foreach ($day['other_items'] ?? [] as $ot) {
            $otName = trim($ot['name']   ?? '');
            $otAmt  = (float)($ot['amount'] ?? 0);
            if (!$otName || $otAmt <= 0) continue;
            $isPerPax = stripos($otName, 'transfer') !== false
                     || stripos($otName, 'water') !== false
                     || stripos($otName, 'contribution') !== false;
            $amt = $isPerPax ? $otAmt * $paxQ : $otAmt;
            $rows .= '<tr><td style="padding:.3rem .6rem;font-size:.78rem;color:#374151">' . htmlspecialchars($otName) . '</td><td style="padding:.3rem .6rem;font-size:.78rem;text-align:right">$' . number_format($amt, 0) . '</td></tr>';
            $dayTotal += $amt;
        }

        if ($rows === '') continue;

        $dayByDayHtml .= '
        <div style="border:1px solid #e5e7eb;margin-bottom:.75rem">
          <div style="background:#f7f7f5;padding:.5rem .75rem;display:flex;justify-content:space-between;align-items:center">
            <div><strong style="font-size:.82rem">Day ' . htmlspecialchars((string)$dayNum) . '</strong><span style="font-size:.78rem;color:#6b7280;margin-left:.5rem">' . htmlspecialchars($title) . ($location ? ' · ' . htmlspecialchars($location) : '') . '</span></div>
            <div style="font-weight:700;font-size:.82rem">$' . number_format($dayTotal, 0) . '</div>
          </div>
          <table width="100%" cellpadding="0" cellspacing="0" border="0">' . $rows . '</table>
        </div>';
    }
}

// ── Email body ────────────────────────────────────────────────
$wa      = defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '255663430934';
$waLink  = 'https://wa.me/' . $wa . '?text=' . urlencode('Hi, I would like to accept quotation ' . $q['quote_ref'] . '.');
$replyTo = defined('SITE_EMAIL') ? SITE_EMAIL : 'info@safarizanguven.com';
$logoUrl = (defined('APP_URL') ? APP_URL : 'https://www.safarizanguven.com') . '/assets/images/logo.jpeg';

$destStr2 = $destStr;
$mealPlanLabels = ['fb' => 'Full Board (B+L+D)', 'hb' => 'Half Board (B+D)', 'bb' => 'Bed & Breakfast', 'ai' => 'All Inclusive'];
$mealPlanStr = $mealPlanLabels[$q['meal_plan'] ?? 'fb'] ?? strtoupper($q['meal_plan'] ?? 'FB');
$travelersStr = (int)$q['adults'] . ' adult' . ((int)$q['adults'] !== 1 ? 's' : '')
              . ((int)$q['children'] > 0 ? ', ' . (int)$q['children'] . ' child' . ((int)$q['children'] !== 1 ? 'ren' : '') : '');
$startDateStr = $q['start_date'] ? date('d F Y', strtotime($q['start_date'])) : 'Flexible';

// Same config constants paymentBox() reads, kept in sync manually since
// this is plain text (can't call the HTML-returning method here).
$bankName    = defined('BANK_NAME')         ? BANK_NAME         : 'Your Bank';
$bankAcc     = defined('BANK_ACCOUNT_NAME') ? BANK_ACCOUNT_NAME : 'Safari Zangu Ventures';
$bankNo      = defined('BANK_ACCOUNT_NO')   ? BANK_ACCOUNT_NO   : 'Contact us for details';
$bankSwift   = defined('BANK_SWIFT')        ? BANK_SWIFT        : '';
$paypalEmail = defined('PAYPAL_EMAIL')      ? PAYPAL_EMAIL      : 'Contact us';

$emailBody = "Dear {$q['client_name']},\n\n"
    . "Thank you for considering Safari Zangu Ventures for your Tanzania safari. "
    . "Please find your personalised quotation below.\n\n"
    . "QUOTATION REFERENCE: {$q['quote_ref']}\n"
    . "Valid until: {$validStr}\n\n"
    . "SAFARI SUMMARY\n"
    . "Destination: {$destStr2}\n"
    . "Duration: " . (int)$q['duration_days'] . " nights\n"
    . "Travelers: {$travelersStr}\n"
    . "Start Date: {$startDateStr}\n"
    . "Accommodation: " . ucfirst(str_replace('_', ' ', $q['accommodation_level'] ?? 'Mid-Range')) . "\n"
    . "Meal Plan: {$mealPlanStr}\n"
    . (!empty($q['vehicle_name']) ? "Vehicle: {$q['vehicle_name']}\n" : '')
    . "\n"
    . "TOTAL: USD $" . number_format((float)$q['grand_total'], 0) . "\n"
    . "Deposit (due within 48 hours): USD $" . number_format((float)$q['deposit_amount'], 0) . "\n"
    . "Balance (due 14 days before your safari): USD $" . number_format((float)$q['balance_due'], 0) . "\n\n"
    . "PAYMENT DETAILS\n"
    . "Bank: {$bankName}\n"
    . "Account Name: {$bankAcc}\n"
    . "Account Number: {$bankNo}\n"
    . ($bankSwift ? "SWIFT: {$bankSwift}\n" : '')
    . "PayPal: {$paypalEmail}\n"
    . "Please include your reference number ({$q['quote_ref']}) in the payment description.\n\n"
    . (!empty($q['notes']) ? "NOTES\n{$q['notes']}\n\n" : '')
    . "To accept this quotation:\n"
    . "WhatsApp us: {$waLink}\n"
    . "Or reply to this email: {$replyTo}\n\n"
    . "This quotation is valid until {$validStr} and subject to availability.\n\n"
    . "Safari Zangu Ventures\n"
    . "Njiro Road, Arusha, Tanzania\n"
    . "+255 663 430 934 · {$replyTo} · www.safarizanguven.com";

// ── Render the quotation PDF and queue the email ────────────────
// Previously this sent synchronously via PHPMailer inside the request —
// no retry on failure, no record in admin/email_queue.php, and the
// admin's browser blocked on however long SMTP took. It now renders
// the PDF once (reusing the exact same capture-and-strip approach that
// already worked), saves it to storage/uploads/, and queues the email
// like every other email in the system — the cron sends it within 2
// minutes with 3 retries.
//
// The itinerary is deliberately NOT attached as a second PDF here:
// api/quotation_pdf.php already merges the linked itinerary's days
// into the same document when itinerary_id is set, so a separate
// Itinerary.pdf attachment was duplicate content in every email sent.
$queued        = false;
$error         = '';
$pdfAttachNote = '';
$attachments   = [];

$rendered = \App\Services\PdfRenderer::renderQuotation($id, 'Quotation-' . $q['quote_ref']);
if ($rendered) {
    $attachments[] = $rendered;
} else {
    $pdfAttachNote = ' (PDF attachment failed — check the error log)';
}

try {
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Safari Zangu Ventures';
    $db->prepare("
        INSERT INTO email_queue (to_email, to_name, subject, template, body, is_plain_text, attachments, status, created_at)
        VALUES (?, ?, ?, 'quotation_sent', ?, 1, ?, 'pending', NOW())
    ")->execute([
        $q['client_email'],
        $q['client_name'],
        'Your Safari Quotation ' . $q['quote_ref'] . ' — Safari Zangu Ventures',
        $emailBody,
        $attachments ? json_encode($attachments) : null,
    ]);

    // No separate admin-copy row needed — api/cron/process_email_queue.php
    // already CCs ADMIN_EMAIL on every queued send.
    $queued = true;

    // Mark as sent — queuing is the point past which the admin considers
    // it dispatched; the cron's retry logic covers actual delivery.
    $db->prepare("UPDATE quotations SET status='sent', sent_at=NOW(), updated_at=NOW() WHERE id=? AND status='draft'")
       ->execute([$id]);

} catch (Exception $e) {
    $error = $e->getMessage();
    error_log('[SZV QuoteEmail] ' . $error);
}

ob_end_clean();

$msg = $queued
    ? "Quotation {$q['quote_ref']} queued for sending to {$q['client_email']} — it will go out within 2 minutes.{$pdfAttachNote}"
    : "Could not queue the email: {$error}";

header('Location: ' . BASE_PATH . '/admin/quotations.php?msg=' . urlencode($msg));
exit;