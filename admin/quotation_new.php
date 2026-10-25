<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$myId        = (int)($_SESSION['admin_user_id'] ?? 0);
// Ownership scoping (own quotations only) applies to the agent role
// alone — staff can open/save any quotation, same as admin.
$isAgentUser = AuthMiddleware::isAgentRole();

$q    = null;
$id   = (int)($_GET['id']           ?? 0);
$msg  = htmlspecialchars($_GET['msg'] ?? '');
$crId = (int)($_GET['from_request'] ?? 0);

// Load existing quote
if ($id > 0) {
    try { $s=$db->prepare("SELECT * FROM quotations WHERE id=?"); $s->execute([$id]); $q=$s->fetch(); }
    catch(Exception $e){}
    // Agents may only open quotations they created themselves.
    if ($q && $isAgentUser && (int)($q['created_by'] ?? 0) !== $myId) {
        header('Location: quotations.php?msg='.urlencode('Permission denied.')); exit;
    }
}

// Pre-fill from custom_safari_request
$cr = null;
if ($crId > 0) {
    try { $s=$db->prepare("SELECT * FROM custom_safari_requests WHERE id=?"); $s->execute([$crId]); $cr=$s->fetch(); }
    catch(Exception $e){}
}

// Pre-fill from a lead (leads.php's "Quote" button)
$leadId = (int)($_GET['from_lead'] ?? 0);
$lead   = null;
if ($leadId > 0) {
    try { $s=$db->prepare("SELECT * FROM leads WHERE id=?"); $s->execute([$leadId]); $lead=$s->fetch(); }
    catch(Exception $e){}
}

// ── Pre-fill from existing booking ────────────────────────────
$fromBookingRef = trim($_GET['from_booking'] ?? '');
$bkPrefill = null;
if ($fromBookingRef) {
    try {
        $bks = $db->prepare("
            SELECT b.*,
                   i.id as itin_id, i.name as itin_name, i.days as itin_days,
                   p.itinerary_id as pkg_itin_id,
                   oq.client_country as country
            FROM bookings b
            LEFT JOIN packages p ON p.name = b.package_name AND p.is_active=1
            LEFT JOIN itineraries i ON i.id = p.itinerary_id
            LEFT JOIN quotations oq ON oq.id = b.quotation_id
            WHERE b.booking_ref = ? LIMIT 1
        ");
        $bks->execute([$fromBookingRef]);
        $bkPrefill = $bks->fetch() ?: null;
    } catch(Exception $e) { error_log('[quotation_new] from_booking: '.$e->getMessage()); }
}

// Reference data
function fetchAll($db,$sql){try{return $db->query($sql)->fetchAll();}catch(Exception $e){return[];}}
$packages      = fetchAll($db,"SELECT id,name,slug FROM packages WHERE is_active=1 ORDER BY sort_order");
$vehicles      = fetchAll($db,"SELECT id,name,daily_rate,fuel_cost_day,driver_cost_day,capacity FROM vehicles WHERE is_active=1 ORDER BY name");
$accommodations= fetchAll($db,"SELECT id,name,level,location,rate_per_room,rate_per_person,single_supplement,child_rate FROM accommodations WHERE is_active=1 ORDER BY level,name");
$parkFees      = fetchAll($db,"SELECT id,name,location,adult_fee,child_fee,vehicle_fee,resident_adult,resident_child FROM park_fees WHERE is_active=1 ORDER BY name");
$allLocations  = \App\Services\LocationRegistry::allLocations($db);
$allCountries  = \App\Services\LocationRegistry::allCountries($db);
$activitiesDB  = fetchAll($db,"SELECT id,name,price,price_unit,location FROM activities WHERE is_active=1 ORDER BY name");

// ── SAVE ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quote'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();

    // Agents may only save changes to quotations they created themselves.
    if ($id > 0 && $isAgentUser) {
        $ownerCheck = $db->prepare("SELECT created_by FROM quotations WHERE id=?");
        $ownerCheck->execute([$id]);
        $ownerRow = $ownerCheck->fetch();
        if (!$ownerRow || (int)($ownerRow['created_by'] ?? 0) !== $myId) {
            header("Location: quotation_new.php?id=$id&msg=".urlencode('Permission denied.')); exit;
        }
    }

    $adults       = max(1,(int)($_POST['adults']   ?? 2));
    $children     = max(0,(int)($_POST['children'] ?? 0));
    $totalPax     = $adults + $children;
    $durationDays = max(1,(int)($_POST['duration_days']??5));

    // Day-by-day itinerary built from the Day-by-Day Itinerary section
    // below — same per-day shape as packages.itinerary / itineraries.days
    // (see admin/package_edit.php, built the same way). This is what's
    // actually priced; the old flat "type a description, price, and
    // quantity string by hand" line-item builder is gone.
    // If the first day is flagged as an arrival day (airport pickup +
    // overnight), it's labelled "Day 0" and every day after it shifts
    // down by one so touring starts at "Day 1" — see admin/itinerary_edit.php
    // for the identical rule and why day_is_arrival[0] is explicitly keyed
    // rather than day_is_arrival[] (unchecked checkboxes don't submit).
    $arrivalDay = !empty($_POST['day_is_arrival'][0]);
    $itinerary = [];
    foreach ($_POST['day_title']??[] as $i=>$title) {
        $itinerary[] = [
            'day'                 => $arrivalDay ? $i : $i+1,
            'is_arrival'          => ($i===0 && $arrivalDay),
            'title'               => trim($title),
            'description'         => trim(($_POST['day_desc']??[])[$i]??''),
            'location'            => trim(($_POST['day_location']??[])[$i]??''),
            'accommodation_id'    => (int)(($_POST['day_acc_id']??[])[$i]??0),
            'accommodation_name'  => trim(($_POST['day_acc_name']??[])[$i]??''),
            'accommodation_rate'  => (float)(($_POST['day_acc_rate']??[])[$i]??0),
            'accommodation_rooms' => (int)(($_POST['day_acc_rooms']??[])[$i]??1),
            'vehicle_id'          => (int)(($_POST['day_veh_id']??[])[$i]??0),
            'vehicle_name'        => trim(($_POST['day_veh_name']??[])[$i]??''),
            'vehicle_rate'        => (float)(($_POST['day_veh_rate']??[])[$i]??0),
            'park_fee_id'         => (int)(($_POST['day_park_id']??[])[$i]??0),
            'park_fee_name'       => trim(($_POST['day_park_name']??[])[$i]??''),
            'park_adult_fee'      => (float)(($_POST['day_park_fee']??[])[$i]??0),
            'meals_cost_pp'       => (float)(($_POST['day_meals_pp']??[])[$i]??0),
            'activities'          => json_decode(($_POST['day_activities_json']??[])[$i]??'[]', true) ?: [],
            'other_items'         => json_decode(($_POST['day_other_json']??[])[$i]??'[]', true) ?: [],
        ];
    }
    $itinerary = array_slice($itinerary, 0, $durationDays);

    $itinIdPosted = !empty($_POST['itinerary_id']) ? (int)$_POST['itinerary_id'] : null;

    // Always price off the Day-by-Day Itinerary as actually submitted —
    // unlike admin/package_edit.php, a quotation's itinerary section
    // stays editable even when itinerary_id is set (it's a one-off,
    // client-specific snapshot, not a permanent catalog listing kept in
    // sync with a shared template). Re-fetching itineraries.days here
    // and pricing off that instead would silently discard whatever the
    // admin just customized in the form. itinerary_id is kept only for
    // traceability and as a display fallback for older quotations that
    // predate this snapshot (see the "Parse existing" section below).
    $pricingDays = $itinerary;

    $serviceChargeAdultPax = (float)($_POST['service_charge_adult'] ?? 0);
    $serviceChargeChildPax = (float)($_POST['service_charge_child'] ?? 0);
    $discountPct      = (float)($_POST['discount_pct'] ?? 0);
    $markupPct        = max(0, (float)($_POST['markup_pct'] ?? 0));

    // Skip ItineraryQuoteBuilder's own hardcoded $100/pax default service
    // fee — Service Charge below is the real one, entered directly.
    // Infants are never charged, same as everywhere else in the app.
    $built     = \App\Services\ItineraryQuoteBuilder::build(['days' => $pricingDays], $adults, $children, false, $_POST['start_date'] ?: null, trim($_POST['client_country'] ?? '') ?: null);
    $lineItems = $built['line_items'];
    if ($serviceChargeAdultPax > 0 && $adults > 0) {
        $lineItems[] = [
            'description'  => 'Service Charge (Adults)',
            'unit_price'   => $serviceChargeAdultPax,
            'quantity_str' => $adults . ' Adult' . ($adults !== 1 ? 's' : ''),
            'amount'       => round($serviceChargeAdultPax * $adults, 2),
            'category'     => 'service_fee',
        ];
    }
    if ($serviceChargeChildPax > 0 && $children > 0) {
        $lineItems[] = [
            'description'  => 'Service Charge (Children)',
            'unit_price'   => $serviceChargeChildPax,
            'quantity_str' => $children . ' Child' . ($children !== 1 ? 'ren' : ''),
            'amount'       => round($serviceChargeChildPax * $children, 2),
            'category'     => 'service_fee',
        ];
    }

    $totals     = \App\Services\ItineraryQuoteBuilder::totals($lineItems, $discountPct, $markupPct);
    $subtotal   = $totals['subtotal'];
    $vat        = $totals['vat'];
    $grandTotal = $totals['grand_total'];
    $deposit    = $totals['deposit'];
    $balance    = $totals['balance'];
    $perPerson  = $totalPax > 0 ? round($grandTotal / $totalPax, 2) : $grandTotal;

    // Categorise totals for the DB's own summary columns (unchanged
    // downstream contract — api/quotation_pdf.php, reports, etc. still
    // read these).
    $accTotal     = 0; $vehicleTotal = 0; $parkTotal = 0;
    $actTotal     = 0; $flightCost   = 0; $svcFee   = 0;
    foreach ($lineItems as $item) {
        $cat = $item['category'] ?? 'other';
        $amt = (float)($item['amount'] ?? 0);
        match($cat) {
            'accommodation' => $accTotal     += $amt,
            'transport'     => $vehicleTotal += $amt,
            'park_fee'      => $parkTotal    += $amt,
            'activity'      => $actTotal     += $amt,
            'flight'        => $flightCost   += $amt,
            'service_fee'   => $svcFee       += $amt,
            default         => null,
        };
    }

    $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : date('Y-m-d', strtotime('+' . QUOTATION_VALIDITY_DAYS . ' days'));
    $destinations= json_encode(array_values(array_filter(array_map('trim', explode(',', $_POST['destinations']??'')))));
    $includes   = array_values(array_filter(array_map('trim',explode("\n",$_POST['includes']??''))));
    $excludes   = array_values(array_filter(array_map('trim',explode("\n",$_POST['excludes']??''))));

    // Resolve package_id from the typed package name so downstream conversion
    // to a booking doesn't have to re-guess it by name matching.
    $postedPkgName = trim($_POST['package_name'] ?? '');
    $pkgId = null;
    if ($postedPkgName !== '') {
        try {
            $pkgLookup = $db->prepare("SELECT id FROM packages WHERE name=? LIMIT 1");
            $pkgLookup->execute([$postedPkgName]);
            $pkgRow = $pkgLookup->fetch();
            if ($pkgRow) $pkgId = (int)$pkgRow['id'];
        } catch(Exception $e) {}
    }

    // Resolve (or create) the unified customer record for this client.
    $clientName    = trim($_POST['client_name']);
    $clientEmail   = trim($_POST['client_email']);
    $clientPhone   = trim($_POST['client_phone'] ?? '');
    $clientCountry = trim($_POST['client_country'] ?? '');
    $customerId = null;
    try {
        $customerId = (new \App\Models\Customer())->findOrCreateByEmail($clientEmail, $clientName, $clientPhone, $clientCountry);
    } catch (Exception $e) { error_log('[quotation_new Customer] '.$e->getMessage()); }

    $data = [
        'customer_id'         => $customerId,
        'client_name'         => $clientName,
        'client_email'        => $clientEmail,
        'client_phone'        => $clientPhone,
        'client_country'      => $clientCountry,
        'adults'              => $adults,
        'children'            => $children,
        'infants'             => max(0,(int)($_POST['infants']??0)),
        'duration_days'       => $durationDays,
        'start_date'          => $_POST['start_date'] ?: null,
        'destinations'        => $destinations,
        'accommodation_level' => $_POST['accommodation_level'] ?? 'midrange',
        'meal_plan'           => $_POST['meal_plan']           ?? 'fb',
        'guide_language'      => $_POST['guide_language']      ?? 'english',
        'package_id'          => $pkgId,
        'package_name'        => $postedPkgName,
        'notes'               => trim($_POST['notes']          ?? ''),
        'valid_until'         => $validUntil,
        'status'              => $_POST['status']              ?? 'draft',
        'line_items'          => json_encode($lineItems),
        'itinerary'           => json_encode($itinerary),
        'includes'            => json_encode($includes),
        'excludes'            => json_encode($excludes),
        'accommodation_total' => $accTotal,
        'vehicle_total'       => $vehicleTotal,
        'park_fees_total'     => $parkTotal,
        'activities_total'    => $actTotal,
        'flight_cost'         => $flightCost,
        'service_charge'      => $svcFee,
        'discount_pct'        => $discountPct,
        'markup_pct'          => $markupPct,
        'itinerary_id'        => $itinIdPosted,
        'discount_amount'     => $totals['discount'],
        'subtotal'            => $subtotal,
        'vat'                 => $vat,
        'grand_total'         => $grandTotal,
        'per_person'          => $perPerson,
        'deposit_amount'      => $deposit,
        'balance_due'         => $balance,
    ];

    try {
        if ($id > 0) {
            $set = implode(',', array_map(fn($k)=>"`$k`=?", array_keys($data)));
            $db->prepare("UPDATE quotations SET $set, updated_at=NOW() WHERE id=?")
               ->execute(array_merge(array_values($data), [$id]));
            $newId = $id;
        } else {
            $ref = 'SZV-Q' . strtoupper(substr(md5(uniqid()), 0, 6));
            $data['quote_ref'] = $ref;
            if ($crId) $data['custom_request_id'] = $crId;
            if ($leadId) $data['lead_id'] = $leadId;
            $data['created_by'] = $myId ?: null;
            $cols = implode(',', array_map(fn($k)=>"`$k`", array_keys($data)));
            $phld = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO quotations ($cols,created_at) VALUES ($phld,NOW())")->execute(array_values($data));
            $newId = $db->lastInsertId();
            \App\Services\ActivityLog::record('create', 'quotation', (int)$newId, ['quote_ref'=>$ref]);
            if ($leadId) \App\Services\PipelineSync::advanceLead($db, $leadId, 'quoted');
            if ($crId)   \App\Services\PipelineSync::advanceCustomRequest($db, $crId, 'quoted');
        }
        foreach ($itinerary as $day) { \App\Services\LocationRegistry::touchLocation($db, $day['location'] ?? ''); }
        \App\Services\LocationRegistry::touchCountry($db, $clientCountry);

        // Keep an already-converted Booking in step with this quotation —
        // the reverse of the sync admin/bookings.php already does when a
        // booking is edited (which pushes client/trip details back onto
        // the quotation). Without this, editing the quotation after
        // conversion — a corrected travel date, a re-priced itinerary, a
        // client detail fix — would silently leave the booking (and the
        // Invoice/Track page, which read the booking's own total_amount/
        // deposit_amount as the amount actually owed) showing stale data.
        // travel_date uses COALESCE so clearing Start Date on the
        // quotation can't null out the booking's required travel_date.
        try {
            $bk = $db->prepare("SELECT booking_ref FROM bookings WHERE quotation_id=? LIMIT 1");
            $bk->execute([$newId]);
            $bkRow = $bk->fetch();
            if ($bkRow) {
                $db->prepare("UPDATE bookings SET
                        full_name=?, email=?, phone=?, country=?, travel_date=COALESCE(?, travel_date),
                        adults=?, children=?, accommodation_level=?, special_request=?,
                        total_amount=?, deposit_amount=?, package_id=?, package_name=?, itinerary_id=?,
                        updated_at=NOW()
                      WHERE quotation_id=?")
                   ->execute([
                        $data['client_name'], $data['client_email'], $data['client_phone'], $data['client_country'],
                        $data['start_date'],
                        $data['adults'], $data['children'], $data['accommodation_level'], $data['notes'],
                        $data['grand_total'], $data['deposit_amount'], $data['package_id'], $data['package_name'], $data['itinerary_id'],
                        $newId,
                    ]);

                // Rewrite the Quotation + Itinerary PDF files on disk so
                // they reflect this edit immediately — same filenames
                // every booking-creation path writes to.
                \App\Services\PdfRenderer::renderQuotation($newId, 'Quotation-' . $bkRow['booking_ref']);
                \App\Services\PdfRenderer::renderQuotationItinerary($newId, 'Itinerary-' . $bkRow['booking_ref']);
            }
        } catch (\Throwable $e) {
            error_log('[SZV Quotation Edit Booking Sync] ' . $e->getMessage());
        }

        header("Location: quotation_new.php?id=$newId&msg=".urlencode('Quotation saved.')); exit;
    } catch(Exception $e) { $msg = 'Error: '.$e->getMessage(); }
}

// Parse existing
$qAdults   = (int)($q['adults']   ?? $cr['adults']   ?? 2);
$qChildren = (int)($q['children'] ?? $cr['children'] ?? 0);
$qDuration = (int)($q['duration_days'] ?? $cr['duration_days'] ?? 5);
$qTotalPax = max(1, $qAdults + $qChildren);

// A quotation linked to an Itinerary Manager template shows that
// template's days read-only-in-spirit as a starting point — same as
// admin/package_edit.php — but because a quotation is a one-off,
// client-specific snapshot rather than a permanent catalog listing, the
// Day-by-Day Itinerary section here stays editable either way; loading
// a template just copies its days in as a customizable starting point
// (see loadItinerary() below). itinerary_id is kept for traceability
// and so the PDF/email day-by-day breakdown can prefer the live
// template if nothing was customized.
$itinerary = $q ? (json_decode($q['itinerary'] ?? '[]', true) ?: []) : [];

// Quotations linked to a template before this local-snapshot column
// existed (or linked without ever pressing "Load Itinerary" again)
// have itinerary_id set but nothing of their own here yet — without
// this, the Day-by-Day Itinerary section would render as blank day
// cards despite the quotation clearly having a real itinerary attached.
// Seed it from the linked template so what's shown always matches what
// was actually priced.
if (empty($itinerary) && !empty($q['itinerary_id'])) {
    try {
        $ls = $db->prepare("SELECT days FROM itineraries WHERE id=? LIMIT 1");
        $ls->execute([(int)$q['itinerary_id']]);
        $linkRow = $ls->fetch();
        $itinerary = $linkRow ? (json_decode($linkRow['days'] ?? '[]', true) ?: []) : [];
    } catch (\Exception $e) {}
}

// Whether Day 1 is flagged as an arrival day determines whether a
// freshly-padded day's number continues from 0 or from 1 — see the save
// handler above for the authoritative version of this same rule.
$arrivalDayLoaded = !empty($itinerary[0]['is_arrival']);
while (count($itinerary) < $qDuration) {
    $n = count($itinerary);
    $itinerary[] = ['day'=>$arrivalDayLoaded ? $n : $n+1,'is_arrival'=>false,'title'=>'','description'=>''];
}
$itinerary = array_slice($itinerary, 0, $qDuration);

// service_charge stores only the combined total — the real per-type
// rates the two Service Charge inputs edit live in line_items instead,
// tagged category='service_fee'. A legacy quotation saved before the
// Adults/Children split (a single "Professional Driver Guide & Service
// Fee" line covering everyone) prefills both inputs with that same flat
// rate, matching what it actually charged.
$qLineItemsForPrefill = $q ? (json_decode($q['line_items'] ?? '[]', true) ?: []) : [];
$qSvcAdultPax = 0.0; $qSvcChildPax = 0.0;
foreach ($qLineItemsForPrefill as $li) {
    if (($li['category'] ?? '') !== 'service_fee') continue;
    $desc = $li['description'] ?? '';
    $unit = (float)($li['unit_price'] ?? 0);
    if (stripos($desc, 'child') !== false) {
        $qSvcChildPax = $unit;
    } elseif (stripos($desc, 'adult') !== false) {
        $qSvcAdultPax = $unit;
    } else {
        $qSvcAdultPax = $unit;
        $qSvcChildPax = $unit;
    }
}
$qDiscountPct = (float)($q['discount_pct'] ?? 0);
$qMarkupPct   = (float)($q['markup_pct'] ?? 0);
$qIncludesArr = $q ? (json_decode($q['includes']??'[]',true)?:[]) : [];
$qExcludesArr = $q ? (json_decode($q['excludes']??'[]',true)?:[]) : [];
if (empty($qIncludesArr) && empty($qExcludesArr) && !empty($q['itinerary_id'])) {
    try {
        $ls = $db->prepare("SELECT includes, excludes FROM itineraries WHERE id=? LIMIT 1");
        $ls->execute([(int)$q['itinerary_id']]);
        $linkRow = $ls->fetch();
        if ($linkRow) {
            $qIncludesArr = json_decode($linkRow['includes'] ?? '[]', true) ?: [];
            $qExcludesArr = json_decode($linkRow['excludes'] ?? '[]', true) ?: [];
        }
    } catch (\Exception $e) {}
}
$qIncludes = implode("\n", $qIncludesArr);
$qExcludes = implode("\n", $qExcludesArr);

// VAT rate — single source of truth is config/app.php's VAT_RATE constant.
$vatPct    = (int) round((defined('VAT_RATE') ? VAT_RATE : 0.18) * 100);
$depositPct= defined('DEPOSIT_PERCENT') ? DEPOSIT_PERCENT : 25;

$pageTitle = $id ? 'Edit Quotation' : 'New Quotation';
ob_start();
?>
<?php if($msg): ?>
<div class="admin-alert admin-alert--<?= str_starts_with($msg,'Error')?'error':'success' ?>">
    <?= $msg ?>
    <?php if($id): ?>
    &nbsp;·&nbsp;
    <a href="quotations.php" style="font-weight:700">← All Quotations</a>
    &nbsp;·&nbsp;
    <form method="POST" action="../api/quotation_email.php" style="display:inline">
      <?= \App\Middleware\CsrfMiddleware::field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <button type="submit" style="font-weight:700;background:none;border:none;color:inherit;text-decoration:underline;cursor:pointer;font-family:inherit;font-size:inherit;padding:0">Send Email</button>
    </form>
    &nbsp;·&nbsp;
    <a href="../api/quotation_pdf.php?id=<?= $id ?>" target="_blank" style="font-weight:700">PDF <?= \App\Helpers\AdminIcon::render('external-link', 12) ?></a>
    &nbsp;·&nbsp;
    <a href="../api/quotation_itinerary_pdf.php?id=<?= $id ?>" target="_blank" style="font-weight:700">Itinerary PDF <?= \App\Helpers\AdminIcon::render('external-link', 12) ?></a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($id && ($q['status'] ?? '') === 'converted'): ?>
<div class="admin-alert admin-alert--info">
    <b>Linked to Booking <?= htmlspecialchars($q['booking_ref'] ?? '') ?>.</b>
    Changes here update that booking immediately, including its total and deposit amounts.
    <?php if ($q['booking_ref']): ?>
    <a href="bookings.php?view=<?= urlencode($q['booking_ref']) ?>" style="font-weight:700;margin-left:.4rem">View Booking →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div style="display:flex;justify-content:flex-end;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap">
    <?php if($id): ?>
    <a href="../api/quotation_pdf.php?id=<?= $id ?>" target="_blank" class="admin-btn admin-btn--outline">PDF <?= \App\Helpers\AdminIcon::render('external-link', 13) ?></a>
    <a href="../api/quotation_itinerary_pdf.php?id=<?= $id ?>" target="_blank" class="admin-btn admin-btn--outline">Itinerary PDF <?= \App\Helpers\AdminIcon::render('external-link', 13) ?></a>
    <form method="POST" action="../api/quotation_email.php" style="display:inline">
      <?= \App\Middleware\CsrfMiddleware::field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <button type="submit" class="admin-btn admin-btn--primary">Send Email</button>
    </form>
    <?php endif; ?>
    <a href="quotations.php" class="admin-btn admin-btn--muted">← All Quotations</a>
</div>

<?php if ($bkPrefill): ?>
<div id="booking-prefill-banner" style="background:var(--blue-light);border:1.5px solid var(--blue-border);padding:.85rem 1.25rem;margin-bottom:1.25rem;font-size:.82rem;color:var(--blue);display:flex;justify-content:space-between;align-items:center;border-radius:2px">
  <span>
    <b>Pre-filling from Booking <?= htmlspecialchars($bkPrefill['booking_ref']) ?></b>
    &nbsp;·&nbsp; <?= htmlspecialchars($bkPrefill['package_name'] ?? '') ?>
    &nbsp;·&nbsp; <?= (int)($bkPrefill['adults'] ?? 2) ?> adults
    &nbsp;·&nbsp; <?= $bkPrefill['travel_date'] ? date('d M Y', strtotime($bkPrefill['travel_date'])) : '' ?>
  </span>
  <button onclick="document.getElementById('booking-prefill-banner').remove()" style="background:none;border:none;cursor:pointer;color:var(--blue-border);font-size:1rem">✕</button>
</div>
<?php endif; ?>
<form method="POST" id="quote-form">
<?= \App\Middleware\CsrfMiddleware::field() ?>
<input type="hidden" name="save_quote" value="1">
<input type="hidden" name="itinerary_id" id="itinerary_id" value="<?= (int)($q['itinerary_id'] ?? 0) ?>">

<div class="admin-split-layout" style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start">

<!-- ════ LEFT ════ -->
<div>

<!-- CLIENT + TRIP INFO -->
<div class="admin-card" style="margin-bottom:1.25rem">
    <div class="admin-card-header"><h3>Client & Trip Details</h3></div>
    <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
            <div><label class="admin-label">Client Name *</label>
                <input name="client_name" class="admin-input" required
                       value="<?= htmlspecialchars($q['client_name']??$cr['full_name']??$lead['full_name']??'') ?>"></div>
            <div><label class="admin-label">Email *</label>
                <input name="client_email" type="email" class="admin-input" required
                       value="<?= htmlspecialchars($q['client_email']??$cr['email']??$lead['email']??'') ?>"></div>
            <div><label class="admin-label">Phone / WhatsApp</label>
                <input name="client_phone" class="admin-input"
                       value="<?= htmlspecialchars($q['client_phone']??$cr['phone']??$lead['phone']??'') ?>"></div>
            <div><label class="admin-label">Country</label>
                <input name="client_country" class="admin-input" list="country-list" oninput="calcTotals()"
                       value="<?= htmlspecialchars($q['client_country']??$cr['country']??$lead['country']??'') ?>"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem">
            <div><label class="admin-label">Package</label>
                <input name="package_name" class="admin-input" list="pkg-list"
                       value="<?= htmlspecialchars($q['package_name']??$lead['package_name']??'') ?>">
                <datalist id="pkg-list">
                    <?php foreach($packages as $p): ?>
                    <option value="<?= htmlspecialchars($p['name']) ?>">
                    <?php endforeach; ?>
                </datalist></div>
            <div><label class="admin-label">Start Date</label>
                <input name="start_date" type="date" class="admin-input" onchange="calcTotals()"
                       value="<?= htmlspecialchars($q['start_date']??$cr['start_date']??$lead['travel_date']??'') ?>"></div>
            <div><label class="admin-label">Destinations (comma-sep)</label>
                <input name="destinations" class="admin-input"
                       value="<?= htmlspecialchars(is_string($q['destinations']??null) ? implode(', ', json_decode($q['destinations']??'[]',true)??[]) : '') ?>"></div>
            <div><label class="admin-label">Duration (days)</label>
                <input name="duration_days" type="number" min="1" class="admin-input" id="inp_duration"
                       value="<?= $qDuration ?>" oninput="syncItineraryDays(this.value)"></div>
        </div>
    </div>
</div>

<!-- NOTES + OPTIONS -->
<div class="admin-card" style="margin-bottom:1.25rem">
    <div class="admin-card-header"><h3>Additional Details</h3></div>
    <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
            <div><label class="admin-label">Accommodation Level</label>
                <select name="accommodation_level" class="admin-input" onchange="filterAllDayOptions()">
                    <?php foreach(['budget'=>'Budget-Friendly','midrange'=>'Mid-Range','highend'=>'High-End','luxury'=>'Luxury','ultra_luxury'=>'Ultra Luxury'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($q['accommodation_level']??'midrange')===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="admin-label">Meal Plan</label>
                <select name="meal_plan" class="admin-input">
                    <?php foreach(['fb'=>'Full Board (B+L+D)','hb'=>'Half Board','bb'=>'Bed & Breakfast','ai'=>'All Inclusive'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($q['meal_plan']??'fb')===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="admin-label">Guide Language</label>
                <select name="guide_language" class="admin-input">
                    <?php foreach(['english'=>'English','french'=>'French','german'=>'German','italian'=>'Italian','spanish'=>'Spanish','chinese'=>'Mandarin'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($q['guide_language']??'english')===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="admin-label">Valid Until</label>
                <input name="valid_until" type="date" class="admin-input"
                       value="<?= $q['valid_until'] ?? date('Y-m-d', strtotime('+' . QUOTATION_VALIDITY_DAYS . ' days')) ?>"></div>
            <div><label class="admin-label">Status</label>
                <select name="status" class="admin-input">
                    <?php foreach(['draft'=>'Draft','sent'=>'Sent','viewed'=>'Viewed','negotiating'=>'Negotiating','accepted'=>'Accepted','declined'=>'Declined / Rejected'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($q['status']??'draft')===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <div><label class="admin-label">Notes / Special Requests</label>
            <textarea name="notes" class="admin-input" rows="3"
                      placeholder="Any special requirements, inclusions, or notes for the client…"><?= htmlspecialchars($q['notes']??$cr['message']??$lead['notes']??'') ?></textarea></div>
    </div>
</div>

<!-- LOAD FROM ITINERARY -->
<div class="admin-card" style="margin-bottom:1.25rem;border:1.5px solid var(--black)">
  <div class="admin-card-header" style="background:var(--black);color:var(--white)">
    <h3 style="color:var(--white)">Load Itinerary Template</h3>
    <span style="font-size:.72rem;opacity:.6">Copies days into the Day-by-Day Itinerary below to customize</span>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:end">
      <div>
        <label class="admin-label">Select Itinerary</label>
        <select id="itin_select" class="admin-input">
          <option value="">— Choose an itinerary template —</option>
          <?php
          $itinList = [];
          try { $itinList = $db->query("SELECT id,name,duration_days,accommodation_level,base_price_pax FROM itineraries WHERE is_active=1 ORDER BY sort_order,name")->fetchAll(); }
          catch(Exception $e){}
          foreach($itinList as $it): ?>
          <option value="<?= $it['id'] ?>"
                  data-dur="<?= $it['duration_days'] ?>"
                  data-level="<?= $it['accommodation_level'] ?>"
                  data-price="<?= $it['base_price_pax'] ?>">
            <?= htmlspecialchars($it['name']) ?>
            — <?= $it['duration_days'] ?>d · est. $<?= number_format($it['base_price_pax'],0) ?>/pp
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="button" onclick="loadItinerary()" class="admin-btn admin-btn--primary" id="load-itin-btn" style="padding:.75rem 1.5rem">
        Load Itinerary
      </button>
    </div>
    <p style="font-size:.68rem;color:var(--gray-500);margin-top:.4rem">This overwrites the current Day-by-Day Itinerary below with the template's days — review and customize per-day rates for this specific client before saving.</p>
  </div>
</div>

<!-- DAY-BY-DAY ITINERARY — same arrangement as admin/package_edit.php's
     day-block: a "Day N" + title header with a live cost badge, then one
     colour-coded box per category (Accommodation / Vehicle / Park &
     Meals), then per-day activities and other costs. Replaces the old
     flat "type a description, unit price, and quantity string by hand"
     line-item builder — the Quotation Live Cost Summary below computes
     line items FROM this itinerary instead of the admin re-typing them. -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <h3>Day-by-Day Itinerary</h3>
    <span style="font-size:.72rem;color:var(--gray-500)" id="itinerary-count">
      <?= $qDuration ?> day<?= $qDuration!=1?'s':'' ?> · matches duration above
    </span>
  </div>
  <div class="admin-card-body" id="itinerary-rows">
    <?php foreach($itinerary as $i=>$day): ?>
    <div class="day-block itinerary-day" data-day="<?= $i ?>" style="margin-bottom:1rem;border-left:3px solid var(--black)">
      <div class="admin-card-header" style="background:var(--gray-50)">
        <h3 style="font-size:.85rem">
          <span class="day-num-label" style="font-family:'Times New Roman',Times,serif;color:var(--black);margin-right:.5rem">Day <?= $day['day'] ?? $i+1 ?></span>
          <input name="day_title[]" class="admin-input" style="display:inline;width:auto;min-width:200px;font-size:.85rem;border:none;background:transparent;font-weight:600;padding:.15rem .35rem"
                 placeholder="Day title e.g. Arusha → Tarangire"
                 value="<?= htmlspecialchars($day['title']??'') ?>">
        </h3>
        <div style="display:flex;align-items:center;gap:.5rem">
          <label class="day-arrival-toggle" style="display:<?= $i===0?'inline-flex':'none' ?>;align-items:center;gap:.3rem;font-size:.65rem;color:var(--gray-500);font-weight:600;cursor:pointer;white-space:nowrap">
            <input type="checkbox" name="day_is_arrival[<?= $i ?>]" value="1" <?= !empty($day['is_arrival'])?'checked':'' ?> onchange="renumberDays()">
            Arrival day — pickup &amp; overnight (Day 0)
          </label>
          <span class="day-cost-badge" style="font-size:.65rem;background:var(--black);color:var(--white);padding:.2rem .6rem;font-weight:600">$0</span>
          <button type="button" onclick="deleteDay(this)" class="admin-icon-btn admin-icon-btn--danger" title="Delete this day"><?= \App\Helpers\AdminIcon::render('trash', 14) ?></button>
        </div>
      </div>

      <div class="admin-card-body">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div><label class="admin-label">Location / Park</label>
            <input name="day_location[]" class="admin-input day-location" list="location-list" data-idx="<?= $i ?>" oninput="scheduleFilterDayOptions(<?= $i ?>); syncDestinationsFromDays()"
                   value="<?= htmlspecialchars($day['location']??'') ?>" placeholder="e.g. Serengeti National Park"></div>
          <div><label class="admin-label">Day Description</label>
            <textarea name="day_desc[]" class="admin-input" rows="2" placeholder="What happens this day…"><?= htmlspecialchars($day['description']??'') ?></textarea></div>
        </div>

        <div class="day-resource-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">

          <!-- Accommodation -->
          <div style="border:1px solid var(--gray-200);padding:.85rem;background:#eff6ff20">
            <p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#1d4ed8"><?= \App\Helpers\AdminIcon::render('accommodations', 13) ?> Accommodation</p>
            <input type="hidden" name="day_acc_id[]" class="day-acc-id" value="<?= (int)($day['accommodation_id']??0) ?>">
            <div style="margin-bottom:.45rem"><label class="admin-label">Property</label>
              <input name="day_acc_name[]" class="admin-input day-acc-name" list="qn-acc-list-<?= $i ?>"
                     value="<?= htmlspecialchars($day['accommodation_name']??'') ?>"
                     oninput="calcItinTotal()" onchange="fillDayAcc(this,<?= $i ?>)"
                     placeholder="Select or type">
              <datalist id="qn-acc-list-<?= $i ?>">
                <?php foreach($accommodations as $a): $r=max((float)$a['rate_per_room'],(float)$a['rate_per_person']); ?>
                <option value="<?= htmlspecialchars($a['name']) ?>" data-id="<?= $a['id'] ?>" data-rate="<?= $r ?>">
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="admin-field-pair" style="display:grid;grid-template-columns:1fr 60px;gap:.35rem">
              <div><label class="admin-label">Rate/Room/Night</label>
                <input name="day_acc_rate[]" type="number" step="1" class="admin-input day-acc-rate"
                       value="<?= (float)($day['accommodation_rate']??0) ?>" oninput="calcItinTotal()"></div>
              <div><label class="admin-label">Rooms</label>
                <input name="day_acc_rooms[]" type="number" min="1" class="admin-input"
                       value="<?= (int)($day['accommodation_rooms']??1) ?>" oninput="calcItinTotal()"></div>
            </div>
          </div>

          <!-- Vehicle -->
          <div style="border:1px solid var(--gray-200);padding:.85rem;background:#f0fdf420">
            <p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#166534"><?= \App\Helpers\AdminIcon::render('fleet', 13) ?> Vehicle</p>
            <input type="hidden" name="day_veh_id[]" class="day-veh-id" value="<?= (int)($day['vehicle_id']??0) ?>">
            <div style="margin-bottom:.45rem"><label class="admin-label">Vehicle</label>
              <input name="day_veh_name[]" class="admin-input day-veh-name" list="qn-veh-list-<?= $i ?>"
                     value="<?= htmlspecialchars($day['vehicle_name']??'') ?>"
                     oninput="calcItinTotal()" onchange="fillDayVeh(this,<?= $i ?>)"
                     placeholder="Select or type">
              <datalist id="qn-veh-list-<?= $i ?>">
                <?php foreach($vehicles as $v): $total=(float)$v['daily_rate']+(float)$v['fuel_cost_day']+(float)$v['driver_cost_day']; ?>
                <option value="<?= htmlspecialchars($v['name']) ?>" data-id="<?= $v['id'] ?>" data-rate="<?= $total ?>">
                <?php endforeach; ?>
              </datalist>
            </div>
            <div><label class="admin-label">Daily Rate (incl. fuel+driver)</label>
              <input name="day_veh_rate[]" type="number" step="1" class="admin-input day-veh-rate"
                     value="<?= (float)($day['vehicle_rate']??0) ?>" oninput="calcItinTotal()"></div>
          </div>

          <!-- Park + Meals -->
          <div style="border:1px solid var(--gray-200);padding:.85rem;background:#fef3e220">
            <p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#92400e"><?= \App\Helpers\AdminIcon::render('park-fees', 13) ?> Park &amp; Meals</p>
            <input type="hidden" name="day_park_id[]" value="<?= (int)($day['park_fee_id']??0) ?>">
            <div style="margin-bottom:.45rem"><label class="admin-label">Park / Area</label>
              <input name="day_park_name[]" class="admin-input" list="qn-park-list-<?= $i ?>"
                     value="<?= htmlspecialchars($day['park_fee_name']??'') ?>"
                     oninput="calcItinTotal()" onchange="fillDayPark(this,<?= $i ?>)"
                     placeholder="Select or leave blank">
              <datalist id="qn-park-list-<?= $i ?>">
                <?php foreach($parkFees as $p): ?>
                <option value="<?= htmlspecialchars($p['name']) ?>" data-id="<?= $p['id'] ?>" data-fee="<?= $p['adult_fee'] ?>">
                <?php endforeach; ?>
              </datalist>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem">
              <div><label class="admin-label">Adult Fee</label>
                <input name="day_park_fee[]" type="number" step="0.01" class="admin-input day-park-fee"
                       value="<?= (float)($day['park_adult_fee']??0) ?>" oninput="calcItinTotal()"></div>
              <div><label class="admin-label">Meals/pp</label>
                <input name="day_meals_pp[]" type="number" step="0.01" class="admin-input"
                       value="<?= (float)($day['meals_cost_pp']??20) ?>" oninput="calcItinTotal()"></div>
            </div>
          </div>

        </div>

        <!-- Activities for this day -->
        <div style="margin-bottom:1rem">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
            <p class="day-acts-label" style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Activities (Day <?= $day['day'] ?? $i+1 ?>)</p>
            <button type="button" onclick="addDayActivity(<?= $i ?>)" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>
          </div>
          <datalist id="qn-act-list-<?= $i ?>">
            <?php foreach($activitiesDB as $a): ?>
            <option value="<?= htmlspecialchars($a['name']) ?>" data-id="<?= $a['id'] ?>" data-price="<?= $a['price'] ?>">
            <?php endforeach; ?>
          </datalist>
          <div id="day-acts-<?= $i ?>">
            <?php foreach(($day['activities']??[]) as $ai=>$act): ?>
            <div class="act-item" style="display:grid;grid-template-columns:2fr 80px 60px 90px auto;gap:.35rem;align-items:end;margin-bottom:.4rem">
              <input name="act_name_<?= $i ?>_<?= $ai ?>" class="admin-input day-act-name" list="qn-act-list-<?= $i ?>"
                     value="<?= htmlspecialchars($act['name']??'') ?>" placeholder="Activity name" style="font-size:.78rem" oninput="saveDayActs(<?= $i ?>)">
              <input name="act_price_<?= $i ?>_<?= $ai ?>" type="number" step="0.01" class="admin-input day-act-price"
                     value="<?= (float)($act['price_pp']??0) ?>" placeholder="$/pp" style="font-size:.78rem" oninput="saveDayActs(<?= $i ?>)">
              <input name="act_qty_<?= $i ?>_<?= $ai ?>" type="number" min="1" class="admin-input day-act-qty" title="How many travelers this activity covers"
                     value="<?= (int)($act['qty']??$qTotalPax) ?>" placeholder="Qty" style="font-size:.78rem" oninput="saveDayActs(<?= $i ?>)">
              <select name="act_type_<?= $i ?>_<?= $ai ?>" class="admin-input day-act-type" style="font-size:.72rem" onchange="saveDayActs(<?= $i ?>)">
                <option value="included" <?= ($act['type']??'included')==='included'?'selected':'' ?>>Included</option>
                <option value="optional" <?= ($act['type']??'included')==='optional'?'selected':'' ?>>Optional</option>
              </select>
              <button type="button" onclick="this.closest('.act-item').remove();saveDayActs(<?= $i ?>)"
                      class="admin-icon-btn admin-icon-btn--danger"><?= \App\Helpers\AdminIcon::render('close', 14) ?></button>
            </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="day_activities_json[]" id="acts-json-<?= $i ?>"
                 value="<?= htmlspecialchars(json_encode($day['activities']??[])) ?>">
        </div>

        <!-- Other items for this day -->
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
            <p class="day-other-label" style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Other Costs (Day <?= $day['day'] ?? $i+1 ?>)</p>
            <button type="button" onclick="addDayOther(<?= $i ?>)" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>
          </div>
          <div id="day-other-<?= $i ?>">
            <?php foreach(($day['other_items']??[]) as $oi=>$ot): ?>
            <div class="other-item" style="border:1px solid var(--gray-200);border-radius:2px;padding:.5rem;margin-bottom:.5rem">
              <div style="display:flex;gap:.35rem;margin-bottom:.35rem">
                <input class="admin-input day-other-name" value="<?= htmlspecialchars($ot['name']??'') ?>" placeholder="Item name" style="font-size:.78rem;flex:1" oninput="saveDayOther(<?= $i ?>)">
                <button type="button" onclick="this.closest('.other-item').remove();saveDayOther(<?= $i ?>)"
                        class="admin-icon-btn admin-icon-btn--danger"><?= \App\Helpers\AdminIcon::render('close', 14) ?></button>
              </div>
              <div style="display:grid;grid-template-columns:70px 55px 1fr 150px;gap:.35rem;align-items:center">
                <input type="number" step="0.01" class="admin-input day-other-amount" value="<?= (float)($ot['amount']??0) ?>" placeholder="$/unit" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
                <input type="number" min="1" class="admin-input day-other-qty" title="Number of units" value="<?= (int)($ot['qty']??1) ?>" placeholder="Qty" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
                <input class="admin-input day-other-unit" title="What a unit is called (e.g. Box, Trip, Vehicle) — type your own, independent of traveler count" value="<?= htmlspecialchars($ot['unit']??'') ?>" placeholder="Unit name (optional)" style="font-size:.78rem" oninput="saveDayOther(<?= $i ?>)">
                <select class="admin-input day-other-perpax" style="font-size:.72rem" title="Whether this cost multiplies by the number of travelers" onchange="saveDayOther(<?= $i ?>)">
                  <option value="0" <?= empty($ot['per_pax'])?'selected':'' ?>>Flat (not per guest)</option>
                  <option value="1" <?= !empty($ot['per_pax'])?'selected':'' ?>>× Number of guests</option>
                </select>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="day_other_json[]" id="other-json-<?= $i ?>"
                 value="<?= htmlspecialchars(json_encode($day['other_items']??[])) ?>">
        </div>

      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- INCLUDES / EXCLUDES — auto-fillable from whatever's actually in the
     itinerary (linked template's own curated lists when linked, or
     derived from the Day-by-Day Itinerary otherwise) via ↓ Sync, same
     as admin/package_edit.php, then still freely editable by hand. -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3>Includes &amp; Excludes</h3>
    <div style="display:flex;align-items:center;gap:.75rem">
      <span style="font-size:.72rem;color:var(--gray-500)">One item per line</span>
      <button type="button" onclick="syncIncludesExcludes()" class="admin-btn admin-btn--muted" style="font-size:.65rem;padding:.25rem .55rem;display:inline-flex;align-items:center;gap:.3rem"><?= \App\Helpers\AdminIcon::render('refresh', 11) ?> Sync from Itinerary</button>
    </div>
  </div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
      <div><label class="admin-label" style="display:flex;align-items:center;gap:.3rem;color:#166534"><?= \App\Helpers\AdminIcon::render('check', 11) ?> Included</label>
        <textarea name="includes" id="includes_ta" class="admin-input" rows="8"><?= htmlspecialchars($qIncludes) ?></textarea></div>
      <div><label class="admin-label" style="display:flex;align-items:center;gap:.3rem;color:#991b1b"><?= \App\Helpers\AdminIcon::render('close', 11) ?> Not Included</label>
        <textarea name="excludes" id="excludes_ta" class="admin-input" rows="8"><?= htmlspecialchars($qExcludes) ?></textarea></div>
    </div>
    <p style="font-size:.68rem;color:var(--gray-500);margin-top:.5rem">Sync pulls the linked itinerary template's own list when linked, or builds a starting Includes list from what's actually in the Day-by-Day Itinerary above — Excludes still needs typing, since things like visas or insurance aren't part of the itinerary. Edit the result freely afterward.</p>
  </div>
</div>

<!-- QUOTATION LIVE COST SUMMARY — auto-calculated from the Day-by-Day
     Itinerary above, same as admin/package_edit.php. Discount% and
     Service Charge are the only editable inputs, set aside from the
     read-only summary. -->
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <h3>Quotation Live Cost Summary</h3>
    <span style="font-size:.72rem;color:var(--gray-500)">Pulled from the itinerary above · matches the PDF/email totals</span>
  </div>
  <div class="admin-card-body">
    <div class="admin-split-summary" style="display:grid;grid-template-columns:1fr 260px;gap:1.5rem;align-items:start">

      <!-- Read-only summary — itemized, matching what ItineraryQuoteBuilder
           actually computes (see calcTotals() below) -->
      <div>
        <table style="width:100%;font-size:.85rem;border-collapse:collapse">
          <thead>
            <tr style="border-bottom:2px solid var(--gray-200)">
              <th style="text-align:left;padding:.4rem 0;font-size:.62rem;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-500);font-weight:600">Item</th>
              <th style="text-align:right;padding:.4rem 0;font-size:.62rem;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-500);font-weight:600">Unit Price</th>
              <th style="text-align:left;padding:.4rem 0 .4rem .75rem;font-size:.62rem;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-500);font-weight:600">Qty</th>
              <th style="text-align:right;padding:.4rem 0;font-size:.62rem;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-500);font-weight:600">Amount</th>
            </tr>
          </thead>
          <tbody id="qs-line-items"></tbody>
          <tbody>
            <tr style="border-top:1px solid var(--gray-200)"><td colspan="3" style="padding:.6rem 0;font-weight:600">Subtotal</td><td style="padding:.6rem 0;text-align:right;font-weight:600" id="qs-subtotal">$0</td></tr>
            <tr style="border-bottom:1px solid var(--gray-100)"><td colspan="3" style="padding:.4rem 0;color:var(--gray-500);font-size:.78rem">Markup</td><td style="padding:.4rem 0;text-align:right;font-size:.78rem" id="qs-markup">+$0</td></tr>
            <tr style="border-bottom:1px solid var(--gray-100)"><td colspan="3" style="padding:.4rem 0;color:var(--gray-500);font-size:.78rem">Discount</td><td style="padding:.4rem 0;text-align:right;font-size:.78rem;color:var(--green)" id="qs-discount">–$0</td></tr>
            <tr style="border-bottom:1px solid var(--gray-100)"><td colspan="3" style="padding:.4rem 0;color:var(--gray-500);font-size:.78rem">VAT (<?= $vatPct ?>%)</td><td style="padding:.4rem 0;text-align:right;font-size:.78rem" id="qs-vat">$0</td></tr>
            <tr style="background:var(--black);color:var(--white)"><td colspan="3" style="padding:.75rem;font-weight:700">GRAND TOTAL</td><td style="padding:.75rem;text-align:right;font-family:'Times New Roman',Times,serif;font-size:1.1rem" id="qs-total">$0</td></tr>
            <tr><td colspan="3" style="padding:.5rem 0;font-size:.75rem;color:var(--gray-500)" id="qs-pp-label">Per Person (÷2)</td><td style="padding:.5rem 0;text-align:right;font-size:.9rem;font-weight:700" id="qs-pp">$0</td></tr>
            <tr><td colspan="4" style="padding:.6rem 0 0">
              <div style="background:var(--green-light);border:1px solid var(--green-border);padding:.75rem">
                <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:.25rem">
                  <span style="color:#166534">Deposit (<?= (int)$depositPct ?>%) — due within 48h</span>
                  <span id="qs-deposit" style="font-weight:700;color:#166534">$0</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.82rem">
                  <span style="color:#166534">Balance (<?= 100 - (int)$depositPct ?>%) — due 45 days before</span>
                  <span id="qs-balance" style="font-weight:700;color:#166534">$0</span>
                </div>
              </div>
            </td></tr>
          </tbody>
        </table>
      </div>

      <!-- Aside: Adults/Children/Infants, then Service Charge — identical
           field set/order to admin/package_edit.php's equivalent aside.
           Discount is the one addition specific to a real quotation. -->
      <div style="background:var(--gray-50);border:1px solid var(--gray-200);padding:1rem">
        <label class="admin-label">Adults</label>
        <input name="adults" type="number" min="1" class="admin-input" id="inp_adults"
               value="<?= $qAdults ?>" style="margin-bottom:.75rem" oninput="calcTotals()">

        <label class="admin-label">Children</label>
        <input name="children" type="number" min="0" class="admin-input" id="inp_children"
               value="<?= $qChildren ?>" style="margin-bottom:.75rem" oninput="calcTotals()">

        <label class="admin-label">Infants <span style="font-weight:600;color:var(--gray-500)">— always free, no service charge</span></label>
        <input name="infants" type="number" min="0" class="admin-input"
               value="<?= (int)($q['infants']??0) ?>" style="margin-bottom:.75rem">

        <label class="admin-label">Service Charge — Adults ($ / person)</label>
        <input name="service_charge_adult" type="number" step="0.01" class="admin-input" id="service_charge_adult"
               value="<?= $qSvcAdultPax ?>" oninput="calcTotals()" placeholder="e.g. 100" style="margin-bottom:.75rem">

        <label class="admin-label">Service Charge — Children ($ / person)</label>
        <input name="service_charge_child" type="number" step="0.01" class="admin-input" id="service_charge_child"
               value="<?= $qSvcChildPax ?>" oninput="calcTotals()" placeholder="e.g. 100" style="margin-bottom:.75rem">

        <label class="admin-label">Markup (%) <span style="font-weight:600;color:var(--gray-500)">— applied before discount/VAT</span></label>
        <input name="markup_pct" type="number" min="0" max="100" step="0.5" class="admin-input" id="inp_markup"
               value="<?= $qMarkupPct ?>" oninput="calcTotals()" style="margin-bottom:.75rem">

        <label class="admin-label">Discount (%)</label>
        <input name="discount_pct" type="number" min="0" max="50" step="0.5" class="admin-input" id="inp_discount"
               value="<?= $qDiscountPct ?>" oninput="calcTotals()" style="margin-bottom:1rem">
        <p style="font-size:.68rem;color:var(--gray-500);line-height:1.5">Everything above comes from the Day-by-Day Itinerary.</p>
      </div>

    </div>
  </div>
</div>

<!-- Location/country autocomplete — same master lists every such field
     in the app draws from (see App\Services\LocationRegistry) -->
<datalist id="location-list">
  <?php foreach ($allLocations as $__l): ?>
  <option value="<?= htmlspecialchars($__l['name']) ?>">
  <?php endforeach; ?>
</datalist>
<datalist id="country-list">
  <?php foreach ($allCountries as $__c): ?>
  <option value="<?= htmlspecialchars($__c) ?>">
  <?php endforeach; ?>
</datalist>

</div><!-- /left -->

<!-- ════ RIGHT SIDEBAR ════ -->
<div style="position:sticky;top:calc(var(--topbar-h) + 1rem)">

    <!-- Save -->
    <div class="admin-card">
        <div class="admin-card-body">
            <button type="submit" class="admin-btn admin-btn--primary"
                    style="width:100%;justify-content:center;padding:.85rem;margin-bottom:.5rem">
                Save Quotation
            </button>
            <?php if($id): ?>
            <a href="../api/quotation_pdf.php?id=<?= $id ?>" target="_blank"
               class="admin-btn admin-btn--outline" style="width:100%;justify-content:center;margin-bottom:.5rem">
                PDF Preview <?= \App\Helpers\AdminIcon::render('external-link', 13) ?>
            </a>
            <a href="../api/quotation_itinerary_pdf.php?id=<?= $id ?>" target="_blank"
               class="admin-btn admin-btn--outline" style="width:100%;justify-content:center;margin-bottom:.5rem">
                Itinerary PDF Preview <?= \App\Helpers\AdminIcon::render('external-link', 13) ?>
            </a>
            <button type="submit" form="send-quote-email-form" class="admin-btn admin-btn--success" style="width:100%;justify-content:center">
                Send to Client
            </button>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /sidebar -->

</div><!-- /grid -->
</form>

<!-- A real <form>, kept outside #quote-form since forms can't nest — the
     "Send to Client" button above targets this one via its form="..."
     attribute, so it still gets a full browser navigation (matching the
     Location redirect api/quotation_email.php sends back) rather than a
     fetch() call that couldn't follow it the same way. -->
<?php if ($id): ?>
<form method="POST" action="../api/quotation_email.php" id="send-quote-email-form" style="display:none">
  <?= \App\Middleware\CsrfMiddleware::field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
</form>
<?php endif; ?>

<script>
var fmt = function(n){ return '$'+Math.round(n).toLocaleString('en-US'); };
// Single source of truth is config/app.php's VAT_RATE constant — mirrored
// here instead of hardcoding 0.18 so this preview can't drift from it.
var VAT_RATE = <?= json_encode(defined('VAT_RATE') ? VAT_RATE : 0.18) ?>;
var PE_ACCS  = <?= json_encode(array_map(fn($a) => ['name'=>$a['name'],'id'=>(int)$a['id'],'rate'=>max((float)$a['rate_per_room'],(float)$a['rate_per_person']),'ratePerPerson'=>(float)$a['rate_per_person'],'singleSupp'=>(float)$a['single_supplement'],'childRate'=>(float)$a['child_rate'],'level'=>$a['level'],'location'=>$a['location']??''], $accommodations)) ?>;
var PE_VEHS  = <?= json_encode(array_map(fn($v) => ['name'=>$v['name'],'id'=>(int)$v['id'],'rate'=>(float)$v['daily_rate']+(float)$v['fuel_cost_day']+(float)$v['driver_cost_day']], $vehicles)) ?>;
var PE_PARKS = <?= json_encode(array_map(fn($p) => ['name'=>$p['name'],'id'=>(int)$p['id'],'fee'=>(float)$p['adult_fee'],'childFee'=>(float)$p['child_fee'],'residentAdult'=>(float)$p['resident_adult'],'residentChild'=>(float)$p['resident_child'],'location'=>$p['location']??''], $parkFees)) ?>;
var PE_ACTS  = <?= json_encode(array_map(fn($a) => ['name'=>$a['name'],'id'=>(int)$a['id'],'price'=>(float)$a['price'],'location'=>$a['location']??''], $activitiesDB)) ?>;
// Same 5 EAC members as App\Services\ItineraryQuoteBuilder::EAC_RESIDENT_COUNTRIES.
var PE_EAC_COUNTRIES = ['tanzania','kenya','uganda','rwanda','burundi'];

// Mirrors App\Services\ItineraryQuoteBuilder::detectSeason() exactly —
// pure date math, same peak/shoulder/low rules. No longer used to
// adjust accommodation/vehicle pricing (a manually entered or selected
// rate is charged exactly as given — see calcTotals() below); kept so
// tests/suites/JsPricingConsistencyTest.php can still verify this JS
// copy hasn't drifted from the PHP original, in case season-aware
// pricing is reintroduced elsewhere later.
function peDetectSeason(dateStr) {
  if (!dateStr) return 'peak';
  var d = new Date(dateStr + 'T00:00:00');
  var month = d.getMonth() + 1, day = d.getDate();
  if ([6,7,8,9,10].indexOf(month) !== -1) return 'peak';
  if (month === 12 && day >= 20) return 'peak';
  if (month === 1 && day <= 5) return 'peak';
  if ([3,4,5].indexOf(month) !== -1) return 'shoulder';
  return 'low';
}

// ── Quotation Live Cost Summary ───────────────────────────────
// Aggregates from the Day-by-Day Itinerary's day-blocks — same category
// grouping calcItinTotal() uses for the per-day badges (each
// accommodation/vehicle counted once per unique property across all
// nights/days, not once per day) — then Discount, then VAT, matching
// App\Services\ItineraryQuoteBuilder::totals() and api/quotation_pdf.php.
function calcTotals() {
  var adults   = parseInt((document.getElementById('inp_adults')||{}).value)   || 1;
  var children = parseInt((document.getElementById('inp_children')||{}).value) || 0;
  var pax      = adults + children;

  // Same resident input the server reads — client_country — so this
  // preview matches what actually gets saved. Accommodation/vehicle
  // rates are never auto-adjusted by season: whatever the admin typed
  // or picked for a day is the exact rate charged, so Unit Price × Qty
  // always equals Amount on screen and on the PDF.
  var country    = ((document.querySelector('[name="client_country"]')||{}).value || '').trim().toLowerCase();
  var isResident = country && PE_EAC_COUNTRIES.indexOf(country) !== -1;

  var accSeen = {}, vehSeen = {}, parkSeen = {}, otherSeen = {}, actSeen = {};
  var mealsPP = 0, mealsDays = 0;

  var dayDescriptors = Array.prototype.map.call(document.querySelectorAll('.day-block'), function(block){
    return {
      accName: (block.querySelector('.day-acc-name')||{}).value||'',
      accRate: parseFloat((block.querySelector('.day-acc-rate')||{}).value)||0,
      accRooms: parseInt((block.querySelector('[name="day_acc_rooms[]"]')||{}).value)||1,
      vehName: (block.querySelector('.day-veh-name')||{}).value||'',
      vehRate: parseFloat((block.querySelector('.day-veh-rate')||{}).value)||0,
      parkName: ((block.querySelector('[name="day_park_name[]"]')||{}).value||''),
      parkFee: parseFloat((block.querySelector('.day-park-fee')||{}).value)||0,
      meals: parseFloat((block.querySelector('[name="day_meals_pp[]"]')||{}).value)||0,
      activities: Array.prototype.map.call(block.querySelectorAll('.act-item'), function(row){
        return {
          name: ((row.querySelector('.day-act-name')||{}).value||'').trim(),
          price: parseFloat((row.querySelector('.day-act-price')||{}).value)||0,
          type: (row.querySelector('.day-act-type')||{}).value||'included',
          qty: parseInt((row.querySelector('.day-act-qty')||{}).value)||1
        };
      }),
      other: Array.prototype.map.call(block.querySelectorAll('.other-item'), function(row){
        return {
          name: ((row.querySelector('.day-other-name')||{}).value||'').trim(),
          amount: parseFloat((row.querySelector('.day-other-amount')||{}).value)||0,
          qty: parseInt((row.querySelector('.day-other-qty')||{}).value)||1,
          unit: ((row.querySelector('.day-other-unit')||{}).value||'').trim(),
          perPax: (row.querySelector('.day-other-perpax')||{}).value==='1'
        };
      })
    };
  });

  dayDescriptors.forEach(function(d){
    if (d.accName && d.accRate > 0) {
      if (!accSeen[d.accName]) accSeen[d.accName] = {rate:d.accRate,rooms:d.accRooms,nights:0};
      accSeen[d.accName].nights++;
    }
    if (d.vehName && d.vehRate > 0) {
      if (!vehSeen[d.vehName]) vehSeen[d.vehName] = {rate:d.vehRate,days:0};
      vehSeen[d.vehName].days++;
    }
    if (d.parkName && d.parkFee > 0) {
      if (!parkSeen[d.parkName]) {
        var pLookup = peParkLookup(d.parkName) || {};
        var residentApplied = isResident && (pLookup.residentAdult||0) > 0;
        parkSeen[d.parkName] = {
          fee: residentApplied ? pLookup.residentAdult : d.parkFee,
          childFee: residentApplied && (pLookup.residentChild||0) > 0 ? pLookup.residentChild : (pLookup.childFee||0),
          vehicleFee: pLookup.vehicleFee||0,
          entries: 0, resident: residentApplied
        };
      }
      parkSeen[d.parkName].entries++;
    }
    if (d.meals > 0) { mealsPP += d.meals; mealsDays++; }

    // Only paid included activities become a priced line item — a free
    // included activity (price 0) has nothing to charge, so it doesn't
    // belong in a cost table (matches ItineraryQuoteBuilder::build()'s
    // actPaid/actFree split server-side). Qty is how many travelers this
    // day's booking covers — lets a party where only some travelers join
    // an activity avoid being charged for everyone.
    d.activities.forEach(function(a){
      if (!a.name || a.type!=='included' || a.price<=0) return;
      if (!actSeen[a.name]) actSeen[a.name] = {price:a.price,totalQty:0,days:0};
      actSeen[a.name].totalQty += a.qty;
      actSeen[a.name].days++;
    });

    d.other.forEach(function(o){
      if (!o.name || o.amount <= 0) return;
      if (!otherSeen[o.name]) otherSeen[o.name] = {unitPrice:o.amount,qty:0,unitName:o.unit,perPax:o.perPax};
      otherSeen[o.name].qty += o.qty;
    });
  });

  var lineItems = [];

  // Mirrors App\Services\ItineraryQuoteBuilder::build()'s two accommodation
  // branches exactly — per-person-sharing (real room allocation) when the
  // property has a rate_per_person on file, flat per-room otherwise.
  Object.keys(accSeen).forEach(function(name){
    var a = accSeen[name];
    var accExtra = peAccLookup(name);

    if (accExtra && accExtra.ratePerPerson > 0) {
      // unit is exactly the rate on file/entered for the day — no hidden
      // seasonal adjustment. Whatever the admin typed or picked is the
      // final charged rate, so Unit × Qty always equals Amount (mirrors
      // App\Services\ItineraryQuoteBuilder::build() exactly).
      var personRate = accExtra.ratePerPerson;
      var pairs = Math.floor(adults / 2), soloAdult = adults % 2, roomsUsed = pairs + soloAdult;
      var baseAmt = personRate * adults * a.nights;
      var qty = adults+' Adult'+(adults!==1?'s':'')+' × '+a.nights+' Night'+(a.nights!==1?'s':'')+' ('+roomsUsed+' Room'+(roomsUsed!==1?'s':'')+')';
      lineItems.push({desc:'Accommodation – '+name+' (Per Person Sharing)', unit:personRate, qty:qty, amount:baseAmt});

      if (soloAdult === 1 && accExtra.singleSupp > 0) {
        var suppAmt = accExtra.singleSupp * a.nights;
        lineItems.push({desc:'Single Supplement – '+name, unit:accExtra.singleSupp, qty:'1 Guest × '+a.nights+' Night'+(a.nights!==1?'s':''), amount:suppAmt});
      }
      if (children > 0 && accExtra.childRate > 0) {
        var childQty = children+' Child'+(children!==1?'ren':'')+' × '+a.nights+' Night'+(a.nights!==1?'s':'');
        lineItems.push({desc:'Child Rate – '+name, unit:accExtra.childRate, qty:childQty, amount:accExtra.childRate*children*a.nights});
      }
    } else {
      var amount = a.rate * a.nights * a.rooms;
      var qty2 = a.rooms > 1
        ? a.rooms+' Rooms × '+a.nights+' Night'+(a.nights!==1?'s':'')
        : a.nights+' Night'+(a.nights!==1?'s':'');
      lineItems.push({desc:'Accommodation – '+name, unit:a.rate, qty:qty2, amount:amount});

      if (accExtra) {
        if (adults === 1 && accExtra.singleSupp > 0) {
          var suppAmt2 = accExtra.singleSupp * a.nights * a.rooms;
          lineItems.push({desc:'Single Supplement – '+name, unit:accExtra.singleSupp, qty:qty2, amount:suppAmt2});
        }
        if (children > 0 && accExtra.childRate > 0) {
          var childQty2 = children+' Child'+(children!==1?'ren':'')+' × '+a.nights+' Night'+(a.nights!==1?'s':'');
          lineItems.push({desc:'Child Rate – '+name, unit:accExtra.childRate, qty:childQty2, amount:accExtra.childRate*children*a.nights});
        }
      }
    }
  });

  Object.keys(vehSeen).forEach(function(name){
    var v = vehSeen[name], amount = v.rate * v.days;
    lineItems.push({desc:name+' & Fuel', unit:v.rate, qty:'1 Vehicle × '+v.days+' Day'+(v.days!==1?'s':''), amount:amount});
  });

  Object.keys(parkSeen).forEach(function(name){
    var p = parkSeen[name];
    var amount = (p.fee*adults + p.childFee*children) * p.entries;
    var guestStr = adults+' Adult'+(adults!==1?'s':'')+(children>0?' + '+children+' Child'+(children!==1?'ren':''):'');
    var qty = p.entries > 1 ? (guestStr+' × '+p.entries+' Days') : guestStr;
    lineItems.push({desc:name+' Entry Fees'+(p.resident?' (Resident Rate)':''), unit:p.fee, qty:qty, amount:amount});

    if (p.vehicleFee > 0) {
      var vehFeeAmt = p.vehicleFee * p.entries;
      lineItems.push({desc:name+' Vehicle Entry Fee', unit:p.vehicleFee, qty:p.entries>1?(p.entries+' Days'):'1 Vehicle', amount:vehFeeAmt});
    }
  });

  Object.keys(actSeen).forEach(function(name){
    var a = actSeen[name], amount = a.price * a.totalQty;
    var qty = a.totalQty+' Guest'+(a.totalQty!==1?'s':'')+(a.days>1?' × '+a.days+' Days':'');
    lineItems.push({desc:name, unit:a.price, qty:qty, amount:amount});
  });

  if (mealsPP > 0) {
    var avgMealPP = mealsPP / Math.max(1,mealsDays);
    lineItems.push({desc:'Meals (Full Board — '+mealsDays+' day'+(mealsDays!==1?'s':'')+')', unit:avgMealPP, qty:pax+' Guests × '+mealsDays+' Days', amount:mealsPP*pax});
  }

  // Per-traveler is an explicit checkbox on each Other Cost row
  // (day-other-perpax), not a name-substring guess, and Qty is an
  // explicit per-row unit count (e.g. 3 firewood bundles at $5 each) —
  // matches App\Services\ItineraryQuoteBuilder::build()'s server-side
  // logic exactly, which is what keeps this live preview honest against
  // what's actually saved as the listed price. unitName is whatever the
  // admin typed into "Unit name" (e.g. "Box", "Trip", "Vehicle") — it's
  // just a label, never a multiplier, and falls back to the generic
  // "Unit(s)" when left blank.
  Object.keys(otherSeen).forEach(function(name){
    var o = otherSeen[name];
    var unitLabel = o.unitName || ('Unit'+(o.qty!==1?'s':''));
    var totalQty = o.perPax ? o.qty * pax : o.qty;
    var amount = o.unitPrice * totalQty;
    var qty = o.perPax
      ? pax+' Guest'+(pax!==1?'s':'')+(o.qty>1?' × '+o.qty+' '+unitLabel:'')
      : o.qty+' '+unitLabel;
    lineItems.push({desc:name, unit:o.unitPrice, qty:qty, amount:amount});
  });

  var svcAdultPax = +((document.getElementById('service_charge_adult')||{}).value)||0;
  var svcChildPax = +((document.getElementById('service_charge_child')||{}).value)||0;
  if (svcAdultPax > 0 && adults > 0) {
    lineItems.push({desc:'Service Charge (Adults)', unit:svcAdultPax, qty:adults+' Adult'+(adults!==1?'s':''), amount:svcAdultPax*adults});
  }
  if (svcChildPax > 0 && children > 0) {
    lineItems.push({desc:'Service Charge (Children)', unit:svcChildPax, qty:children+' Child'+(children!==1?'ren':''), amount:svcChildPax*children});
  }

  var subtotal  = lineItems.reduce(function(sum,li){ return sum+li.amount; }, 0);
  var markupPct = parseFloat((document.getElementById('inp_markup')||{}).value) || 0;
  var markupAmt = subtotal * (markupPct / 100);
  var marked    = subtotal + markupAmt;
  var discPct   = parseFloat((document.getElementById('inp_discount')||{}).value) || 0;
  var discAmt   = marked * (discPct / 100);
  var afterDisc = marked - discAmt;
  var vat       = afterDisc * VAT_RATE;
  var total     = afterDisc + vat;
  var pp        = pax > 0 ? total / pax : total;
  var deposit   = total * (<?= (float)$depositPct ?> / 100);
  var balance   = total - deposit;

  var tbody = document.getElementById('qs-line-items');
  tbody.innerHTML = lineItems.length ? lineItems.map(function(li){
    return '<tr style="border-bottom:1px solid var(--gray-100)">'
      + '<td style="padding:.45rem 0;color:var(--gray-500)">'+escapeHtml(li.desc)+'</td>'
      + '<td style="padding:.45rem 0;text-align:right">'+fmt(li.unit)+'</td>'
      + '<td style="padding:.45rem 0 .45rem .75rem;font-size:.78rem;color:var(--gray-500)">'+escapeHtml(li.qty)+'</td>'
      + '<td style="padding:.45rem 0;text-align:right;font-weight:600">'+fmt(li.amount)+'</td>'
      + '</tr>';
  }).join('') : '<tr><td colspan="4" style="padding:.75rem 0;color:var(--gray-500);font-size:.8rem">Add accommodation, vehicle, or other costs to the itinerary above to see line items here.</td></tr>';

  document.getElementById('qs-subtotal').textContent = fmt(subtotal);
  document.getElementById('qs-markup').textContent   = markupAmt > 0 ? '+'+fmt(markupAmt) : fmt(0);
  document.getElementById('qs-discount').textContent = discAmt > 0 ? '–'+fmt(discAmt) : fmt(0);
  document.getElementById('qs-vat').textContent      = fmt(vat);
  document.getElementById('qs-total').textContent    = fmt(total);
  document.getElementById('qs-pp').textContent       = fmt(pp);
  document.getElementById('qs-pp-label').textContent = 'Per Person (÷'+pax+')';
  document.getElementById('qs-deposit').textContent  = fmt(deposit);
  document.getElementById('qs-balance').textContent  = fmt(balance);
}

function escapeHtml(s) {
  var d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
}

// Park child fee, looked up from PE_PARKS (same reference data used for
// the datalists) — mirrors ItineraryQuoteBuilder::lookupParkChildFee()
// server-side so this live preview matches the real saved calculation.
function parkChildFee(parkName) {
  for (var i=0; i<PE_PARKS.length; i++) {
    if (PE_PARKS[i].name === parkName) return PE_PARKS[i].childFee||0;
  }
  return 0;
}
function peParkLookup(parkName) {
  for (var i=0; i<PE_PARKS.length; i++) if (PE_PARKS[i].name === parkName) return PE_PARKS[i];
  return null;
}
function peAccLookup(accName) {
  for (var i=0; i<PE_ACCS.length; i++) if (PE_ACCS[i].name === accName) return PE_ACCS[i];
  return null;
}

// ── Per-day cost badge + keep the summary in sync ─────────────
function calcItinTotal() {
  var pax = (parseInt((document.getElementById('inp_adults')||{}).value)||1)
          + (parseInt((document.getElementById('inp_children')||{}).value)||0);
  var accSeen = {}, vehSeen = {};

  document.querySelectorAll('.day-block').forEach(function(block) {
    var accName = (block.querySelector('.day-acc-name')||{}).value||'';
    var accRate = parseFloat((block.querySelector('.day-acc-rate')||{}).value)||0;
    var accRooms= parseInt((block.querySelector('[name="day_acc_rooms[]"]')||{}).value)||1;
    var vehName = (block.querySelector('.day-veh-name')||{}).value||'';
    var vehRate = parseFloat((block.querySelector('.day-veh-rate')||{}).value)||0;
    var parkFee = parseFloat((block.querySelector('.day-park-fee')||{}).value)||0;
    var meals   = parseFloat((block.querySelector('[name="day_meals_pp[]"]')||{}).value)||0;

    if (accName && !accSeen[accName]) accSeen[accName] = {rate:accRate,rooms:accRooms,nights:0};
    if (accName) accSeen[accName].nights++;
    if (vehName && !vehSeen[vehName]) vehSeen[vehName] = {rate:vehRate,days:0};
    if (vehName) vehSeen[vehName].days++;

    var dayCost = parkFee*pax + meals*pax + accRate*accRooms + vehRate;
    var badge = block.querySelector('.day-cost-badge');
    if (badge) badge.textContent = '$'+Math.round(dayCost).toLocaleString();
  });

  calcTotals();
  scheduleAutoSync();
}

// ── Datalist auto-fill (per-day accommodation/vehicle/park) ───
function fillDayAcc(input, dayIdx) {
  var opts=document.querySelectorAll('#qn-acc-list-'+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.rate) block.querySelector('.day-acc-rate').value=opts[i].dataset.rate;
      if(opts[i].dataset.id)   block.querySelector('.day-acc-id').value=opts[i].dataset.id;
      calcItinTotal(); break;
    }
  }
}
function fillDayVeh(input, dayIdx) {
  var opts=document.querySelectorAll('#qn-veh-list-'+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.rate) block.querySelector('.day-veh-rate').value=opts[i].dataset.rate;
      if(opts[i].dataset.id)   block.querySelector('.day-veh-id').value=opts[i].dataset.id;
      calcItinTotal(); break;
    }
  }
}
function fillDayPark(input, dayIdx) {
  var opts=document.querySelectorAll('#qn-park-list-'+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.fee) block.querySelector('.day-park-fee').value=opts[i].dataset.fee;
      if(opts[i].dataset.id)  block.querySelector('[name="day_park_id[]"]').value=opts[i].dataset.id;
      calcItinTotal(); break;
    }
  }
}

// ── Smart location/level filter for Accommodation + Park options ────
// Narrows each day's Accommodation and Park/Area datalist down to the
// entries whose own `location` shares a significant word with what's
// typed in that day's own Location field — not a plain substring check,
// because this app's location strings are sub-area names ("Central
// Serengeti", "Northern Serengeti", "Western Serengeti Corridor") that
// don't contain one another as substrings even though they're all
// "Serengeti"; a pure indexOf() match would silently show nothing for
// most real combinations. Tokenizing both sides and matching on any
// shared 4+ letter word (park/region names) catches all of these.
// Further narrows Accommodation by the quotation's overall Accommodation
// Level. Only trims the *suggestions* — the field stays free text via
// <datalist>. Looked up by the day's stable build index (data-idx / the
// N in qn-acc-list-N, qn-park-list-N), not DOM position — those ids
// never change even after a day is deleted and the rest renumber.
function locMatch(a, b) {
  if (!a || !b) return true;
  var ta = a.toLowerCase().match(/[a-z]{4,}/g) || [];
  var tb = b.toLowerCase().match(/[a-z]{4,}/g) || [];
  for (var i = 0; i < ta.length; i++) { if (tb.indexOf(ta[i]) !== -1) return true; }
  return false;
}

// Debounced per-day — the location field rebuilds 3 datalists from the
// full in-memory accommodation/park/activity arrays on every call, so an
// un-debounced oninput firing on every keystroke gets more expensive as
// the catalog grows. Keyed by dayIdx so typing in one day's field can't
// cancel or delay another day's pending rebuild.
var _filterDayOptionsTimers = {};
function scheduleFilterDayOptions(dayIdx) {
  if (_filterDayOptionsTimers[dayIdx]) clearTimeout(_filterDayOptionsTimers[dayIdx]);
  _filterDayOptionsTimers[dayIdx] = setTimeout(function(){ filterDayOptions(dayIdx); }, 150);
}

function filterDayOptions(dayIdx) {
  var locInput = document.querySelector('.day-location[data-idx="'+dayIdx+'"]');
  var loc      = locInput ? locInput.value : '';
  var level    = (document.querySelector('[name="accommodation_level"]')||{}).value || '';

  var accList = document.getElementById('qn-acc-list-'+dayIdx);
  if (accList) {
    accList.innerHTML = PE_ACCS
      .filter(function(a){ return (!level || a.level===level) && locMatch(loc, a.location); })
      .map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-rate="'+a.rate+'">'; })
      .join('');
  }
  var parkList = document.getElementById('qn-park-list-'+dayIdx);
  if (parkList) {
    parkList.innerHTML = PE_PARKS
      .filter(function(p){ return locMatch(loc, p.location); })
      .map(function(p){ return '<option value="'+p.name+'" data-id="'+p.id+'" data-fee="'+p.fee+'">'; })
      .join('');
  }
  var actList = document.getElementById('qn-act-list-'+dayIdx);
  if (actList) {
    actList.innerHTML = PE_ACTS
      .filter(function(a){ return locMatch(loc, a.location); })
      .map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-price="'+a.price+'">'; })
      .join('');
  }
}

function filterAllDayOptions() {
  document.querySelectorAll('.day-location').forEach(function(input){
    filterDayOptions(input.dataset.idx);
  });
}

// ── Keep "Destinations" in sync with the day locations above ───────
// Destinations used to only get filled in when a saved itinerary
// template was loaded (see loadItinerary() below) — typing a day's own
// Location field directly left it stale. Recomputed from scratch on
// every call so it never drifts from what the days actually say.
function syncDestinationsFromDays() {
  var el = document.querySelector('[name="destinations"]');
  if (!el) return;
  var locs = Array.prototype.map.call(document.querySelectorAll('.day-location'), function(i){ return i.value.trim(); }).filter(Boolean);
  el.value = locs.filter(function(v,i,a){ return a.indexOf(v)===i; }).join(', ');
}

// ── Activities per day ─────────────────────────────────────────
function addDayActivity(dayIdx) {
  var container = document.getElementById('day-acts-'+dayIdx);
  var defaultQty = (parseInt((document.getElementById('inp_adults')||{}).value)||1) + (parseInt((document.getElementById('inp_children')||{}).value)||0);
  var div = document.createElement('div');
  div.className='act-item';
  div.style.cssText='display:grid;grid-template-columns:2fr 80px 60px 90px auto;gap:.35rem;align-items:end;margin-bottom:.4rem';
  div.innerHTML='<input class="admin-input day-act-name" list="qn-act-list-'+dayIdx+'" placeholder="Activity name" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<input type="number" step="0.01" class="admin-input day-act-price" value="0" placeholder="$/pp" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<input type="number" min="1" class="admin-input day-act-qty" title="How many travelers this activity covers" value="'+defaultQty+'" placeholder="Qty" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<select class="admin-input day-act-type" style="font-size:.72rem" onchange="saveDayActs('+dayIdx+')"><option value="included">Included</option><option value="optional">Optional</option></select>'
    +'<button type="button" onclick="this.closest(\'.act-item\').remove();saveDayActs('+dayIdx+')" class="admin-icon-btn admin-icon-btn--danger"><?= \App\Helpers\AdminIcon::render("close", 14) ?></button>';
  container.appendChild(div);
  div.querySelector('.day-act-name').addEventListener('change', function(){
    var opts=document.querySelectorAll('#qn-act-list-'+dayIdx+' option');
    for(var i=0;i<opts.length;i++){
      if(opts[i].value===this.value && opts[i].dataset.price){
        div.querySelector('.day-act-price').value=opts[i].dataset.price;
        saveDayActs(dayIdx); break;
      }
    }
  });
}
function saveDayActs(dayIdx) {
  var container = document.getElementById('day-acts-'+dayIdx);
  var acts=[];
  container.querySelectorAll('.act-item').forEach(function(row){
    var name=row.querySelector('.day-act-name').value.trim();
    if(!name) return;
    acts.push({name:name, price_pp:parseFloat(row.querySelector('.day-act-price').value)||0, qty:parseInt(row.querySelector('.day-act-qty').value)||1, type:row.querySelector('.day-act-type').value});
  });
  document.getElementById('acts-json-'+dayIdx).value=JSON.stringify(acts);
  calcItinTotal();
}

// ── Other items per day ─────────────────────────────────────────
function addDayOther(dayIdx) {
  var container=document.getElementById('day-other-'+dayIdx);
  var div=document.createElement('div');
  div.className='other-item';
  div.style.cssText='border:1px solid var(--gray-200);border-radius:2px;padding:.5rem;margin-bottom:.5rem';
  div.innerHTML='<div style="display:flex;gap:.35rem;margin-bottom:.35rem">'
      +'<input class="admin-input day-other-name" placeholder="Item name" style="font-size:.78rem;flex:1" oninput="saveDayOther('+dayIdx+')">'
      +'<button type="button" onclick="this.closest(\'.other-item\').remove();saveDayOther('+dayIdx+')" class="admin-icon-btn admin-icon-btn--danger"><?= \App\Helpers\AdminIcon::render("close", 14) ?></button>'
    +'</div>'
    +'<div style="display:grid;grid-template-columns:70px 55px 1fr 150px;gap:.35rem;align-items:center">'
      +'<input type="number" step="0.01" class="admin-input day-other-amount" value="0" placeholder="$/unit" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
      +'<input type="number" min="1" class="admin-input day-other-qty" title="Number of units" value="1" placeholder="Qty" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
      +'<input class="admin-input day-other-unit" title="What a unit is called (e.g. Box, Trip, Vehicle) — type your own, independent of traveler count" placeholder="Unit name (optional)" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
      +'<select class="admin-input day-other-perpax" style="font-size:.72rem" title="Whether this cost multiplies by the number of travelers" onchange="saveDayOther('+dayIdx+')"><option value="0">Flat (not per guest)</option><option value="1">× Number of guests</option></select>'
    +'</div>';
  container.appendChild(div);
}
function saveDayOther(dayIdx) {
  var container=document.getElementById('day-other-'+dayIdx);
  var items=[];
  container.querySelectorAll('.other-item').forEach(function(row){
    var name=row.querySelector('.day-other-name').value.trim();
    if(!name) return;
    items.push({name:name, amount:parseFloat(row.querySelector('.day-other-amount').value)||0, qty:parseInt(row.querySelector('.day-other-qty').value)||1, unit:(row.querySelector('.day-other-unit')||{}).value||'', per_pax:row.querySelector('.day-other-perpax').value==='1'});
  });
  document.getElementById('other-json-'+dayIdx).value=JSON.stringify(items);
  calcItinTotal();
}

// ── Sync day-block count with Duration (days) ────────────────
// Whether the first day-block's "arrival day" checkbox is checked — index
// 0 always already exists in the DOM (a quotation itinerary can never
// have 0 days), so newly-added days just need to read it, not track it.
function isArrivalDayFlagged() {
  var first = document.querySelector('#itinerary-rows .day-block');
  var cb = first ? first.querySelector('.day-arrival-toggle input[type=checkbox]') : null;
  return !!(cb && cb.checked);
}

function peDayBlockHtml(i) {
  var accOpts  = PE_ACCS.map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-rate="'+a.rate+'">'; }).join('');
  var vehOpts  = PE_VEHS.map(function(v){ return '<option value="'+v.name+'" data-id="'+v.id+'" data-rate="'+v.rate+'">'; }).join('');
  var parkOpts = PE_PARKS.map(function(p){ return '<option value="'+p.name+'" data-id="'+p.id+'" data-fee="'+p.fee+'">'; }).join('');
  var actOpts  = PE_ACTS.map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-price="'+a.price+'">'; }).join('');
  var dayNum   = isArrivalDayFlagged() ? i : i+1;

  return ''
    +'<div class="admin-card-header" style="background:var(--gray-50)">'
      +'<h3 style="font-size:.85rem">'
        +'<span class="day-num-label" style="font-family:\'Times New Roman\',Times,serif;color:var(--black);margin-right:.5rem">Day '+dayNum+'</span>'
        +'<input name="day_title[]" class="admin-input" style="display:inline;width:auto;min-width:200px;font-size:.85rem;border:none;background:transparent;font-weight:600;padding:.15rem .35rem" placeholder="Day title e.g. Arusha → Tarangire">'
      +'</h3>'
      +'<div style="display:flex;align-items:center;gap:.5rem">'
        +'<label class="day-arrival-toggle" style="display:none;align-items:center;gap:.3rem;font-size:.65rem;color:var(--gray-500);font-weight:600;cursor:pointer;white-space:nowrap">'
          +'<input type="checkbox" name="day_is_arrival['+i+']" value="1" onchange="renumberDays()">'
          +'Arrival day — pickup &amp; overnight (Day 0)'
        +'</label>'
        +'<span class="day-cost-badge" style="font-size:.65rem;background:var(--black);color:var(--white);padding:.2rem .6rem;font-weight:600">$0</span>'
        +'<button type="button" onclick="deleteDay(this)" class="admin-icon-btn admin-icon-btn--danger" title="Delete this day"><?= \App\Helpers\AdminIcon::render('trash', 14) ?></button></div>'
    +'</div>'
    +'<div class="admin-card-body">'
      +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">'
        +'<div><label class="admin-label">Location / Park</label><input name="day_location[]" class="admin-input day-location" list="location-list" data-idx="'+i+'" oninput="scheduleFilterDayOptions('+i+'); syncDestinationsFromDays()" placeholder="e.g. Serengeti National Park"></div>'
        +'<div><label class="admin-label">Day Description</label><textarea name="day_desc[]" class="admin-input" rows="2" placeholder="What happens this day…"></textarea></div>'
      +'</div>'
      +'<div class="day-resource-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">'
        +'<div style="border:1px solid var(--gray-200);padding:.85rem;background:#eff6ff20">'
          +'<p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#1d4ed8"><?= \App\Helpers\AdminIcon::render('accommodations', 13) ?> Accommodation</p>'
          +'<input type="hidden" name="day_acc_id[]" class="day-acc-id" value="0">'
          +'<div style="margin-bottom:.45rem"><label class="admin-label">Property</label>'
            +'<input name="day_acc_name[]" class="admin-input day-acc-name" list="qn-acc-list-'+i+'" oninput="calcItinTotal()" onchange="fillDayAcc(this,'+i+')" placeholder="Select or type">'
            +'<datalist id="qn-acc-list-'+i+'">'+accOpts+'</datalist>'
          +'</div>'
          +'<div class="admin-field-pair" style="display:grid;grid-template-columns:1fr 60px;gap:.35rem">'
            +'<div><label class="admin-label">Rate/Room/Night</label><input name="day_acc_rate[]" type="number" step="1" class="admin-input day-acc-rate" value="0" oninput="calcItinTotal()"></div>'
            +'<div><label class="admin-label">Rooms</label><input name="day_acc_rooms[]" type="number" min="1" class="admin-input" value="1" oninput="calcItinTotal()"></div>'
          +'</div>'
        +'</div>'
        +'<div style="border:1px solid var(--gray-200);padding:.85rem;background:#f0fdf420">'
          +'<p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#166534"><?= \App\Helpers\AdminIcon::render('fleet', 13) ?> Vehicle</p>'
          +'<input type="hidden" name="day_veh_id[]" class="day-veh-id" value="0">'
          +'<div style="margin-bottom:.45rem"><label class="admin-label">Vehicle</label>'
            +'<input name="day_veh_name[]" class="admin-input day-veh-name" list="qn-veh-list-'+i+'" oninput="calcItinTotal()" onchange="fillDayVeh(this,'+i+')" placeholder="Select or type">'
            +'<datalist id="qn-veh-list-'+i+'">'+vehOpts+'</datalist>'
          +'</div>'
          +'<div><label class="admin-label">Daily Rate (incl. fuel+driver)</label><input name="day_veh_rate[]" type="number" step="1" class="admin-input day-veh-rate" value="0" oninput="calcItinTotal()"></div>'
        +'</div>'
        +'<div style="border:1px solid var(--gray-200);padding:.85rem;background:#fef3e220">'
          +'<p style="display:flex;align-items:center;gap:.4rem;font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.65rem;color:#92400e"><?= \App\Helpers\AdminIcon::render('park-fees', 13) ?> Park &amp; Meals</p>'
          +'<input type="hidden" name="day_park_id[]" value="0">'
          +'<div style="margin-bottom:.45rem"><label class="admin-label">Park / Area</label>'
            +'<input name="day_park_name[]" class="admin-input" list="qn-park-list-'+i+'" oninput="calcItinTotal()" onchange="fillDayPark(this,'+i+')" placeholder="Select or leave blank">'
            +'<datalist id="qn-park-list-'+i+'">'+parkOpts+'</datalist>'
          +'</div>'
          +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem">'
            +'<div><label class="admin-label">Adult Fee</label><input name="day_park_fee[]" type="number" step="0.01" class="admin-input day-park-fee" value="0" oninput="calcItinTotal()"></div>'
            +'<div><label class="admin-label">Meals/pp</label><input name="day_meals_pp[]" type="number" step="0.01" class="admin-input" value="20" oninput="calcItinTotal()"></div>'
          +'</div>'
        +'</div>'
      +'</div>'
      +'<div style="margin-bottom:1rem">'
        +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">'
          +'<p class="day-acts-label" style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Activities (Day '+dayNum+')</p>'
          +'<button type="button" onclick="addDayActivity('+i+')" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>'
        +'</div>'
        +'<datalist id="qn-act-list-'+i+'">'+actOpts+'</datalist>'
        +'<div id="day-acts-'+i+'"></div>'
        +'<input type="hidden" name="day_activities_json[]" id="acts-json-'+i+'" value="[]">'
      +'</div>'
      +'<div>'
        +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">'
          +'<p class="day-other-label" style="font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500)">Other Costs (Day '+dayNum+')</p>'
          +'<button type="button" onclick="addDayOther('+i+')" class="admin-btn admin-btn--muted" style="font-size:.6rem;padding:.2rem .55rem">+ Add</button>'
        +'</div>'
        +'<div id="day-other-'+i+'"></div>'
        +'<input type="hidden" name="day_other_json[]" id="other-json-'+i+'" value="[]">'
      +'</div>'
    +'</div>';
}

function syncItineraryDays(newCount) {
  newCount = Math.max(1, parseInt(newCount) || 1);
  var container = document.getElementById('itinerary-rows');
  var blocks = container.querySelectorAll('.day-block');

  if (newCount > blocks.length) {
    for (var i = blocks.length; i < newCount; i++) {
      var div = document.createElement('div');
      div.className = 'day-block itinerary-day';
      div.dataset.day = i;
      div.style.cssText = 'margin-bottom:1rem;border-left:3px solid var(--black)';
      div.innerHTML = peDayBlockHtml(i);
      container.appendChild(div);
      filterDayOptions(i);
    }
  } else if (newCount < blocks.length) {
    for (var j = blocks.length - 1; j >= newCount; j--) blocks[j].remove();
  }

  document.getElementById('itinerary-count').textContent = newCount + ' day' + (newCount!==1?'s':'') + ' · matches duration above';
  calcItinTotal();
}

// ── Delete a single day, from anywhere in the itinerary ─────────
// Unlike shortening via the Duration field above (which only ever trims
// from the end), this removes one specific day-block and shifts every
// day after it up — Day 3 of 7 deleted leaves a renumbered Day 1-6, not
// a gap. Every day_*[] field is an array-append ("[]") name, not an
// explicit day_title[3]-style index, so the browser's own form
// submission already reindexes correctly from DOM order alone the
// moment the block is gone; renumberDays() below only has to fix the
// *visible* "Day N" labels and the Duration count, which don't update
// themselves just because an element left the DOM.
async function deleteDay(btn) {
  var blocks = document.querySelectorAll('.day-block');
  if (blocks.length <= 1) {
    toast('A quotation needs at least 1 day — edit this one instead of deleting it.', 'err');
    return;
  }
  if (!(await confirmDialog(
    'Delete this day? Its accommodation, vehicle, park, meals, activities and other costs are removed with it — the remaining days shift up and the total recalculates automatically.',
    {danger: true, title: 'Delete Day', confirmLabel: 'Delete Day'}
  ))) return;

  btn.closest('.day-block').remove();
  renumberDays();
  calcItinTotal();
}

function renumberDays() {
  var blocks = document.querySelectorAll('.day-block');
  var arrival = isArrivalDayFlagged();
  blocks.forEach(function (block, idx) {
    var toggle = block.querySelector('.day-arrival-toggle');
    if (toggle) toggle.style.display = (idx === 0) ? 'inline-flex' : 'none';

    var n = (idx === 0 && arrival) ? 0 : (arrival ? idx : idx + 1);
    var dayLabel = block.querySelector('.day-num-label');
    if (dayLabel) dayLabel.textContent = 'Day ' + n;
    var actsLabel = block.querySelector('.day-acts-label');
    if (actsLabel) actsLabel.textContent = 'Activities (Day ' + n + ')';
    var otherLabel = block.querySelector('.day-other-label');
    if (otherLabel) otherLabel.textContent = 'Other Costs (Day ' + n + ')';
    block.dataset.day = idx;
  });
  var count = blocks.length;
  var durInput = document.getElementById('inp_duration');
  if (durInput) durInput.value = count;
  document.getElementById('itinerary-count').textContent = count + ' day' + (count!==1?'s':'') + ' · matches duration above';
}

// ── Init ─────────────────────────────────────────────────────
filterAllDayOptions();
calcTotals();

// ── Load itinerary template → fill Day-by-Day Itinerary ────────
async function loadItinerary() {
  var id     = document.getElementById('itin_select').value;
  var adults = parseInt(document.getElementById('inp_adults').value)||2;
  document.getElementById('itinerary_id').value = id;
  if (!id) { toast('Please select an itinerary first.','err'); return; }

  var btn = document.getElementById('load-itin-btn');
  btn.disabled = true; btn.textContent = 'Loading…';

  try {
    var url = (window.BASE_PATH||'')+'/api/itinerary.php?action=get&id='+id;
    var r   = await fetch(url, {credentials:'same-origin'});
    var d   = await r.json();
    if (!d.success) { toast('Error: '+(d.message||'Failed'),'err'); btn.disabled=false; btn.textContent='Load Itinerary'; return; }

    var itin = d.data;
    var days = itin.days || [];

    // Set trip fields
    var el;
    if ((el=document.querySelector('[name="package_name"]')))   el.value = itin.name;
    if ((el=document.querySelector('[name="duration_days"]')))  el.value = itin.duration_days;
    if ((el=document.querySelector('[name="accommodation_level"]'))) el.value = itin.accommodation_level||'midrange';
    syncItineraryDays(itin.duration_days);

    // Set destinations from day locations
    if ((el=document.querySelector('[name="destinations"]'))) {
      var locs = days.map(function(dd){ return dd.location||dd.title||''; }).filter(Boolean);
      el.value = locs.filter(function(v,i,a){ return a.indexOf(v)===i; }).join(', ');
    }

    // Fill every day-block field from the template
    days.forEach(function(day, i) {
      var block = document.querySelector('.day-block[data-day="'+i+'"]');
      if (!block) return;

      var titleInp = block.querySelector('[name="day_title[]"]');
      var descInp  = block.querySelector('[name="day_desc[]"]');
      var locInp   = block.querySelector('[name="day_location[]"]');
      if (titleInp) titleInp.value = day.title || '';
      if (descInp)  descInp.value  = day.description || '';
      if (locInp)   locInp.value   = day.location || '';

      var accName = block.querySelector('.day-acc-name');
      var accRate = block.querySelector('.day-acc-rate');
      var accRoom = block.querySelector('[name="day_acc_rooms[]"]');
      var accId   = block.querySelector('.day-acc-id');
      if (accName) accName.value = day.accommodation_name || '';
      if (accRate) accRate.value = day.accommodation_rate || 0;
      if (accRoom) accRoom.value = day.accommodation_rooms || 1;
      if (accId)   accId.value   = day.accommodation_id || 0;

      var vehName = block.querySelector('.day-veh-name');
      var vehRate = block.querySelector('.day-veh-rate');
      var vehId   = block.querySelector('.day-veh-id');
      if (vehName) vehName.value = day.vehicle_name || '';
      if (vehRate) vehRate.value = day.vehicle_rate || 0;
      if (vehId)   vehId.value   = day.vehicle_id || 0;

      var parkName = block.querySelector('[name="day_park_name[]"]');
      var parkFee  = block.querySelector('.day-park-fee');
      var parkId   = block.querySelector('[name="day_park_id[]"]');
      var mealsInp = block.querySelector('[name="day_meals_pp[]"]');
      if (parkName) parkName.value = day.park_fee_name || '';
      if (parkFee)  parkFee.value  = day.park_adult_fee || 0;
      if (parkId)   parkId.value   = day.park_fee_id || 0;
      if (mealsInp) mealsInp.value = day.meals_cost_pp || 0;

      (day.activities || []).forEach(function(act) {
        addDayActivity(i);
        var rows = block.querySelectorAll('.act-item');
        var row  = rows[rows.length - 1];
        row.querySelector('.day-act-name').value  = act.name || '';
        row.querySelector('.day-act-price').value = act.price_pp || 0;
        if (act.qty) row.querySelector('.day-act-qty').value = act.qty;
        row.querySelector('.day-act-type').value  = act.type || 'included';
      });
      saveDayActs(i);

      (day.other_items || []).forEach(function(ot) {
        addDayOther(i);
        var rows = block.querySelectorAll('.other-item');
        var row  = rows[rows.length - 1];
        row.querySelector('.day-other-name').value   = ot.name || '';
        row.querySelector('.day-other-amount').value = ot.amount || 0;
        if (ot.qty) row.querySelector('.day-other-qty').value = ot.qty;
        if (ot.unit) row.querySelector('.day-other-unit').value = ot.unit;
        row.querySelector('.day-other-perpax').value = ot.per_pax ? '1' : '0';
      });
      saveDayOther(i);
      filterDayOptions(i);
    });

    calcItinTotal();

    // Populate the real Includes/Excludes fields straight from the
    // template's own curated lists (same as syncIncludesExcludes()'s
    // linked branch below — loading a template does the sync for you).
    document.getElementById('includes_ta').value = (itin.includes||[]).join('\n');
    document.getElementById('excludes_ta').value = (itin.excludes||[]).join('\n');
    includesDirty = false; excludesDirty = false;

    toast('Itinerary "'+itin.name+'" loaded — '+itin.duration_days+' days filled. Review and save.','ok');
  } catch(e) { toast('Network error: '+e.message,'err'); }
  btn.disabled = false; btn.textContent = 'Load Itinerary';
}

// ── Includes/Excludes sync ────────────────────────────────────
// Same behavior as admin/package_edit.php's version, except "linked"
// here is whatever the itinerary_id hidden field currently holds — it
// can change mid-session (Load Itinerary Template sets it), so this
// re-fetches the template fresh rather than trusting page-load data.
//
// Responsive sync: every itinerary-affecting change (accommodation,
// vehicle, park, meals, activities — anywhere calcItinTotal() runs)
// schedules a debounced re-sync automatically, instead of requiring
// the "Sync from Itinerary" button to be clicked by hand each time.
// It never overwrites a field the admin has typed into directly —
// includesDirty/excludesDirty latch true on the first manual keystroke
// in that textarea and only clear again when the button is clicked
// (an explicit "yes, regenerate this" override).
// Starts already "dirty" when the textarea loads with existing content
// (editing a saved quotation) so the very first auto-sync tick doesn't
// silently blow away curated text the admin never asked to regenerate —
// only a genuinely empty field, or an explicit Sync-from-Itinerary click,
// clears the flag and lets auto-sync write to it.
var includesDirty = <?= json_encode($qIncludes !== '') ?>, excludesDirty = <?= json_encode($qExcludes !== '') ?>;
var autoSyncTimer = null, autoSyncInProgress = false;

function scheduleAutoSync() {
  if (autoSyncTimer) clearTimeout(autoSyncTimer);
  autoSyncTimer = setTimeout(function(){ syncIncludesExcludesCore(false); }, 600);
}

(function(){
  var incEl = document.getElementById('includes_ta'), excEl = document.getElementById('excludes_ta');
  if (incEl) incEl.addEventListener('input', function(){ if (!autoSyncInProgress) includesDirty = true; });
  if (excEl) excEl.addEventListener('input', function(){ if (!autoSyncInProgress) excludesDirty = true; });
})();

function syncIncludesExcludes() { return syncIncludesExcludesCore(true); }

async function syncIncludesExcludesCore(force) {
  autoSyncInProgress = true;
  try {
    var itinId = document.getElementById('itinerary_id').value;
    if (itinId) {
      try {
        var r = await fetch((window.BASE_PATH||'')+'/api/itinerary.php?action=get&id='+itinId, {credentials:'same-origin'});
        var d = await r.json();
        if (d.success) {
          if (force || !includesDirty) document.getElementById('includes_ta').value = (d.data.includes||[]).join('\n');
          if (force || !excludesDirty) document.getElementById('excludes_ta').value = (d.data.excludes||[]).join('\n');
          if (force) { includesDirty = false; excludesDirty = false; toast('Includes & Excludes synced from the itinerary template.'); }
          return;
        }
      } catch(e) { /* fall through to the unlinked derivation below */ }
    }

    // Unlinked (or the fetch failed) — build BOTH Includes and Excludes
    // from what's actually in the Day-by-Day Itinerary, so the two stay
    // a complete, accurate record of what the quoted price does and
    // doesn't cover rather than Excludes being left perpetually blank:
    // every accommodation/vehicle/park/meal/other-cost line and every
    // "Included"-type activity goes into Includes; every "Optional"-type
    // activity (priced as an add-on the client pays separately for, per
    // calcTotals()'s actSeen/actPaid split) goes into Excludes instead of
    // being silently dropped.
    if (!force && includesDirty && excludesDirty) return;
    var accSeen = {}, vehSeen = {}, parkSeen = {}, hasMeals = false, acts = {}, optionalActs = {}, otherItems = {};
    document.querySelectorAll('.day-block').forEach(function(block){
      var accName  = ((block.querySelector('.day-acc-name')||{}).value||'').trim();
      var vehName  = ((block.querySelector('.day-veh-name')||{}).value||'').trim();
      var parkName = ((block.querySelector('[name="day_park_name[]"]')||{}).value||'').trim();
      var meals    = parseFloat((block.querySelector('[name="day_meals_pp[]"]')||{}).value)||0;
      if (accName)  accSeen[accName]   = true;
      if (vehName)  vehSeen[vehName]   = true;
      if (parkName) parkSeen[parkName] = true;
      if (meals > 0) hasMeals = true;
      block.querySelectorAll('.act-item').forEach(function(row){
        var name = ((row.querySelector('.day-act-name')||{}).value||'').trim();
        var type = (row.querySelector('.day-act-type')||{}).value||'included';
        if (!name) return;
        if (type==='included') acts[name] = true; else optionalActs[name] = true;
      });
      block.querySelectorAll('.other-item').forEach(function(row){
        var name = ((row.querySelector('.day-other-name')||{}).value||'').trim();
        var amt  = parseFloat((row.querySelector('.day-other-amount')||{}).value)||0;
        if (name && amt > 0) otherItems[name] = true;
      });
    });

    if (force || !includesDirty) {
      var lines = [];
      if (Object.keys(vehSeen).length) {
        lines.push('Private 4WD safari vehicle (' + Object.keys(vehSeen).join(', ') + ')');
        lines.push('Professional driver-guide');
      }
      Object.keys(parkSeen).forEach(function(p){ lines.push(p + ' entry fees'); });
      Object.keys(accSeen).forEach(function(a){ lines.push('Accommodation: ' + a); });
      if (hasMeals) lines.push('Full-board meals as per itinerary');
      Object.keys(acts).forEach(function(a){ lines.push(a); });
      Object.keys(otherItems).forEach(function(o){ lines.push(o); });
      document.getElementById('includes_ta').value = lines.join('\n');
      if (force) includesDirty = false;
    }

    if (force || !excludesDirty) {
      var excLines = Object.keys(optionalActs).map(function(a){ return a + ' (optional — available at extra cost, not included in this price)'; });
      document.getElementById('excludes_ta').value = excLines.join('\n');
      if (force) excludesDirty = false;
    }

    if (force) toast('Includes & Excludes synced from the Day-by-Day Itinerary.');
  } finally {
    autoSyncInProgress = false;
  }
}

function hE(s) { var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }
document.addEventListener('DOMContentLoaded', calcItinTotal);


// ── Auto-load from booking pre-fill ─────────────────────────
<?php if (!empty($bkPrefill) && !empty($bkPrefill['pkg_itin_id'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Fill client fields from booking
    var fields = {
        'client_name'         : <?= json_encode($bkPrefill['full_name'] ?? '') ?>,
        'client_email'        : <?= json_encode($bkPrefill['email'] ?? '') ?>,
        'client_phone'        : <?= json_encode($bkPrefill['phone'] ?? '') ?>,
        'client_country'      : <?= json_encode($bkPrefill['country'] ?? '') ?>,
        'adults'              : '<?= (int)($bkPrefill['adults'] ?? 2) ?>',
        'children'            : '<?= (int)($bkPrefill['children'] ?? 0) ?>',
        'start_date'          : <?= json_encode($bkPrefill['travel_date'] ?? '') ?>,
        'package_name'        : <?= json_encode($bkPrefill['package_name'] ?? '') ?>,
        'accommodation_level' : <?= json_encode($bkPrefill['accommodation_level'] ?? 'midrange') ?>,
    };
    Object.entries(fields).forEach(function([name, val]) {
        var el = document.querySelector('[name="' + name + '"]');
        if (el) el.value = val;
    });

    // Auto-select itinerary linked to this package
    var itinId = '<?= (int)($bkPrefill['pkg_itin_id'] ?? 0) ?>';
    if (itinId && itinId !== '0') {
        var sel = document.getElementById('itin_select');
        if (sel) {
            sel.value = itinId;
            // Auto-load the day-by-day itinerary after a short delay
            setTimeout(function() {
                document.getElementById('inp_adults').value = '<?= (int)($bkPrefill['adults'] ?? 2) ?>';
                loadItinerary();
            }, 800);
        }
    }
});
<?php elseif (!empty($bkPrefill)): ?>
document.addEventListener('DOMContentLoaded', function() {
    // No linked itinerary — just fill client fields
    var fields = {
        'client_name'         : <?= json_encode($bkPrefill['full_name'] ?? '') ?>,
        'client_email'        : <?= json_encode($bkPrefill['email'] ?? '') ?>,
        'client_phone'        : <?= json_encode($bkPrefill['phone'] ?? '') ?>,
        'client_country'      : <?= json_encode($bkPrefill['country'] ?? '') ?>,
        'adults'              : '<?= (int)($bkPrefill['adults'] ?? 2) ?>',
        'children'            : '<?= (int)($bkPrefill['children'] ?? 0) ?>',
        'start_date'          : <?= json_encode($bkPrefill['travel_date'] ?? '') ?>,
        'package_name'        : <?= json_encode($bkPrefill['package_name'] ?? '') ?>,
        'accommodation_level' : <?= json_encode($bkPrefill['accommodation_level'] ?? 'midrange') ?>,
    };
    Object.entries(fields).forEach(function([name, val]) {
        var el = document.querySelector('[name="' + name + '"]');
        if (el) el.value = val;
    });
});
<?php endif; ?>

</script>

<?php $content=ob_get_clean(); require_once __DIR__.'/../layouts/admin.php'; ?>
