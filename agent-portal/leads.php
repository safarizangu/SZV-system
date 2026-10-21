<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAgent();
$db   = \App\Models\Database::getInstance();
$myId = (int)($_SESSION['admin_user_id'] ?? 0);

// ── Handle AJAX JSON responses ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    ob_end_clean();
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $action = $_POST['action'];

    // Agent is booking an existing package directly for their client.
    // Creates a real `bookings` row (visible to admin for confirmation/
    // payment processing) plus a linked `quotations` row stamped
    // created_by=$myId so OwnershipScope can attribute the booking back to
    // this agent, plus a `leads` row carried straight to 'booked' through
    // PipelineSync so the agent's commission is created the exact same way
    // a manual stage change would (see PipelineSync::writeLeadStage).
    if ($action === 'add_direct_booking') {
        if (!AGENT_DIRECT_BOOKING_ENABLED) {
            echo json_encode(['success'=>false,'message'=>'Direct booking is currently disabled — please submit a custom request instead.']);
            exit;
        }
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email']     ?? '');
        $phone    = trim($_POST['phone']     ?? '');
        $country  = trim($_POST['country']   ?? '');
        $packageId  = (int)($_POST['package_id'] ?? 0);
        $travelDate = trim($_POST['travel_date'] ?? '');
        $adults     = max(1, (int)($_POST['adults']   ?? 1));
        $children   = max(0, (int)($_POST['children'] ?? 0));
        $accLevel   = trim($_POST['accommodation_level'] ?? 'midrange');
        $notes      = trim($_POST['notes'] ?? '');

        if (!$fullName || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$packageId || !$travelDate) {
            echo json_encode(['success'=>false,'message'=>'Client name, email, package and travel date are required.']);
            exit;
        }

        $package = (new \App\Models\Package())->findById($packageId);
        if (!$package || !$package['is_active']) {
            echo json_encode(['success'=>false,'message'=>'Selected package is not available.']); exit;
        }

        $travelers = $adults + $children;
        $builtQuote = null; $quoteTotals = null; $itinerary = null;
        if (!empty($package['itinerary_id'])) {
            try {
                $itinStmt = $db->prepare("SELECT * FROM itineraries WHERE id=? AND is_active=1 LIMIT 1");
                $itinStmt->execute([(int)$package['itinerary_id']]);
                $itinerary = $itinStmt->fetch() ?: null;
                if ($itinerary) {
                    // This package's own Adults/Children Service Charge
                    // rates (admin/package_edit.php) — same fallback as
                    // api/book.php for a package saved before that split.
                    $pkgCostBreakdown = json_decode($package['cost_breakdown'] ?? '{}', true) ?: [];
                    $svcFeeRates = [
                        'adult' => (float)($pkgCostBreakdown['service_charge_adult'] ?? $pkgCostBreakdown['service_charge_pax'] ?? 100),
                        'child' => (float)($pkgCostBreakdown['service_charge_child'] ?? $pkgCostBreakdown['service_charge_pax'] ?? 100),
                    ];
                    $builtQuote  = \App\Services\ItineraryQuoteBuilder::build($itinerary, $adults, $children, true, $travelDate, $country ?: null, $svcFeeRates);
                    $quoteTotals = \App\Services\ItineraryQuoteBuilder::totals($builtQuote['line_items']);
                }
            } catch (\Exception $e) { $itinerary = null; }
        }
        if ($quoteTotals) {
            $total   = $quoteTotals['grand_total'];
            $deposit = $quoteTotals['deposit'];
        } else {
            $price   = (float)($package['price_1pax'] ?? 0);
            $total   = round($price * $travelers, 2);
            $deposit = round($total * (DEPOSIT_PERCENT / 100), 2);
        }

        try {
            $db->beginTransaction();

            $customerId = null;
            try { $customerId = (new \App\Models\Customer())->findOrCreateByEmail($email, $fullName, $phone, $country ?: null); }
            catch (\Exception $e) {}

            $ref = generateBookingRef();
            $db->prepare("
                INSERT INTO bookings
                    (booking_ref, customer_id, full_name, email, phone, country, travelers, adults, children,
                     package_id, package_name, accommodation_level,
                     travel_date, special_request, total_amount, deposit_amount,
                     payment_status, payment_method, created_at)
                VALUES
                    (?,?,?,?,?,?,?,?,?, ?,?,?, ?,?,?,?, 'pending','agent_booking', NOW())
            ")->execute([
                $ref, $customerId, $fullName, $email, $phone, $country, $travelers, $adults, $children,
                $packageId, $package['name'], $accLevel,
                $travelDate, $notes, $total, $deposit,
            ]);
            $bookingId = (int)$db->lastInsertId();

            // Linked quotation — itemised when the package has a real
            // itinerary (same builder api/book.php uses for public
            // bookings), otherwise a single flat line so the agent's
            // created_by still gives OwnershipScope something to derive
            // ownership from regardless of which pricing path ran.
            $quoteRef = 'SZV-Q' . strtoupper(bin2hex(random_bytes(4)));
            $lineItems = $builtQuote['line_items'] ?? [[
                'label' => $package['name'], 'qty' => $travelers, 'unit_price' => round($total / max(1,$travelers), 2), 'total' => $total,
            ]];
            $destinations = $builtQuote
                ? array_values(array_unique(array_filter(array_map(fn($d) => $d['location'] ?? '', $builtQuote['day_titles']))))
                : [];
            $db->prepare("
                INSERT INTO quotations
                    (quote_ref, customer_id, package_id, package_name, itinerary_id, created_by,
                     client_name, client_email, client_phone, client_country,
                     adults, children, duration_days, start_date, destinations,
                     accommodation_level, line_items, subtotal, grand_total,
                     per_person, deposit_amount, balance_due, status, booking_ref,
                     valid_until, sent_at, accepted_at, created_at)
                VALUES
                    (?,?,?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?,?, ?,?,?, 'converted', ?,
                     CURDATE(), NOW(), NOW(), NOW())
            ")->execute([
                $quoteRef, $customerId, $packageId, $package['name'], $package['itinerary_id'] ?? null, $myId,
                $fullName, $email, $phone, $country,
                $adults, $children, (int)($package['duration_days'] ?? 5), $travelDate, json_encode($destinations),
                $accLevel, json_encode($lineItems), $quoteTotals['subtotal'] ?? $total, $total,
                round($total / max(1,$travelers), 2), $deposit, $total - $deposit, $ref,
            ]);
            $quotationId = (int)$db->lastInsertId();
            $db->prepare("UPDATE bookings SET quotation_id=? WHERE booking_ref=?")->execute([$quotationId, $ref]);

            // Lead row, carried straight to 'booked' so the shared
            // PipelineSync commission logic fires exactly as it would from
            // a manual stage change in admin/leads.php.
            $db->prepare("
                INSERT INTO leads (full_name, email, phone, country, package_name, party_size,
                                    travel_date, booking_value, booking_ref, pipeline_stage,
                                    source, notes, agent_id, created_at)
                VALUES (?,?,?,?,?,?, ?,?,?, 'lead', 'agent_direct_booking', ?, ?, NOW())
            ")->execute([$fullName, $email, $phone, $country, $package['name'], $travelers,
                         $travelDate, $total, $ref, $notes, $myId]);
            $leadId = (int)$db->lastInsertId();

            // Track Booking password — same issuance every booking-creation
            // path uses, issued inside the transaction so a failure here
            // rolls back the whole booking rather than leaving one without
            // a way for the client to ever look it up again.
            $trackingPassword = \App\Services\BookingTracking::issue($db, $ref);

            $db->commit();

            $result = \App\Services\PipelineSync::setLeadStageManual($db, $leadId, 'booked', true, $myId);
            \App\Services\ActivityLog::record('create', 'booking', $bookingId, ['booking_ref'=>$ref, 'via'=>'agent_portal', 'agent_id'=>$myId]);
            \App\Services\LocationRegistry::touchCountry($db, $country);

            // Client confirmation — same branded template every other
            // booking-creation path sends; previously skipped on this path,
            // but the client needs their tracking password regardless of
            // who created the booking.
            try {
                \App\Mail\Mailer::queue('booking_confirmation', [
                    'to_email'            => $email,
                    'to_name'             => $fullName,
                    'client_name'         => $fullName,
                    'booking_ref'         => $ref,
                    'tracking_password'   => $trackingPassword,
                    'package_name'        => $package['name'],
                    'travel_date'         => $travelDate,
                    'travelers'           => $travelers,
                    'accommodation_level' => $accLevel,
                    'total_amount'        => $total,
                    'deposit_amount'      => $deposit,
                ]);
                $db->prepare("UPDATE bookings SET confirmation_email_sent=1 WHERE booking_ref=?")->execute([$ref]);
            } catch (\Exception $e) {}

            echo json_encode(['success'=>true, 'booking_ref'=>$ref, 'tracking_password'=>$trackingPassword, 'commission_created'=>$result['commission_created'] ?? false]);
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
        }
        exit;
    }

    // Agent's client wants something bespoke — no pricing decided yet, so
    // this lands in the same custom_safari_requests queue the public
    // enquiry form feeds, tagged with submitted_by_agent_id (the one
    // stored exception in OwnershipScope — see its class doc) so the agent
    // keeps visibility of it even once an admin builds the quotation.
    if ($action === 'add_custom_request') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email']     ?? '');
        if (!$fullName || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success'=>false,'message'=>'Client name and a valid email are required.']); exit;
        }
        $destinations = array_filter(array_map('trim', explode(',', trim($_POST['destinations'] ?? ''))));

        $data = [
            'full_name'              => $fullName,
            'email'                  => $email,
            'phone'                  => trim($_POST['phone'] ?? ''),
            'country'                => trim($_POST['country'] ?? ''),
            'adults'                 => max(1, (int)($_POST['adults']   ?? 2)),
            'children'               => max(0, (int)($_POST['children'] ?? 0)),
            'start_date'             => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'duration_days'          => max(1, (int)($_POST['duration_days'] ?? 7)),
            'flexible_dates'         => !empty($_POST['flexible_dates']) ? 'Yes' : 'No',
            'destinations'           => json_encode($destinations),
            'total_budget'           => trim($_POST['total_budget'] ?? ''),
            'message'                => trim($_POST['notes'] ?? ''),
            'submitted_by_agent_id'  => $myId,
        ];
        try {
            $newId = (new \App\Models\CustomSafariRequest())->create($data);
            \App\Services\ActivityLog::record('create', 'custom_request', $newId, ['full_name'=>$fullName, 'via'=>'agent_portal', 'agent_id'=>$myId]);
            try {
                \App\Mail\Mailer::queue('custom_request_ack', array_merge($data, ['to_email'=>$email, 'to_name'=>$fullName]));
                \App\Mail\Mailer::queueAdmin('custom_request_admin', $data);
            } catch (\Exception $e) {}
            echo json_encode(['success'=>true,'id'=>$newId]);
        } catch (\Exception $e) {
            echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update_stage') {
        $allowed = ['lead','qualified','quoted','negotiation','booked','travelled','completed','lost'];
        $stage   = trim($_POST['stage'] ?? '');
        $id      = (int)($_POST['id']   ?? 0);
        if (!in_array($stage, $allowed, true)) {
            echo json_encode(['success'=>false,'message'=>'Invalid stage.']); exit;
        }
        $result = \App\Services\PipelineSync::setLeadStageManual($db, $id, $stage, true, $myId);
        if (!$result['ok']) {
            echo json_encode(['success'=>false,'message'=>$result['message']]); exit;
        }
        echo json_encode(['success'=>true, 'commission_created'=>$result['commission_created']]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action']);
    exit;
}

// ── Filters + pagination ─────────────────────────────────────
$stageFilter = trim($_GET['stage'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 20;
$offset      = ($page - 1) * $perPage;

$where  = ['agent_id=?'];
$params = [$myId];
if ($stageFilter) { $where[] = 'pipeline_stage=?'; $params[] = $stageFilter; }
$whereSQL = 'WHERE '.implode(' AND ', $where);

$rows = []; $totalRows = 0; $totalPages = 0;
try {
    $total = $db->prepare("SELECT COUNT(*) FROM leads $whereSQL");
    $total->execute($params);
    $totalRows  = (int)$total->fetchColumn();
    $totalPages = (int)ceil($totalRows / $perPage);
    $stmt = $db->prepare("SELECT * FROM leads $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch(\Exception $e) {}

$stages = ['lead','qualified','quoted','negotiation','booked','travelled','completed','lost'];
$stageColors = [
    'lead'        => ['bg'=>'var(--gray-100)',   'text'=>'var(--gray-700)', 'badge'=>'gray'],
    'qualified'   => ['bg'=>'var(--blue-light)',  'text'=>'#1e40af',        'badge'=>'blue'],
    'quoted'      => ['bg'=>'var(--amber-light)', 'text'=>'#92400e',        'badge'=>'amber'],
    'negotiation' => ['bg'=>'var(--amber-light)', 'text'=>'#92400e',        'badge'=>'amber'],
    'booked'      => ['bg'=>'var(--green-light)', 'text'=>'#166534',        'badge'=>'green'],
    'travelled'   => ['bg'=>'var(--green-light)', 'text'=>'#166534',        'badge'=>'green'],
    'completed'   => ['bg'=>'var(--black)',       'text'=>'var(--white)',   'badge'=>'dark'],
    'lost'        => ['bg'=>'var(--red-light)',   'text'=>'#991b1b',        'badge'=>'red'],
];
$stageCounts = array_fill_keys($stages, 0);
try {
    $cs = $db->prepare("SELECT pipeline_stage, COUNT(*) c FROM leads WHERE agent_id=? GROUP BY pipeline_stage");
    $cs->execute([$myId]);
    foreach ($cs->fetchAll() as $cr) $stageCounts[$cr['pipeline_stage']] = (int)$cr['c'];
} catch(\Exception $e) {}

$packages = [];
try { $packages = $db->query("SELECT id,name,price_1pax,duration_days FROM packages WHERE is_active=1 ORDER BY sort_order")->fetchAll(); }
catch(\Exception $e) {}
$allCountries = \App\Services\LocationRegistry::allCountries($db);

$pageTitle = 'My Leads';
ob_start();
?>

<datalist id="country-list">
  <?php foreach ($allCountries as $__c): ?>
  <option value="<?= htmlspecialchars($__c) ?>">
  <?php endforeach; ?>
</datalist>

<!-- Stage pills -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;align-items:center;justify-content:space-between">
  <div class="admin-filter-pills" style="margin-bottom:0">
    <a href="leads.php" class="admin-filter-pill<?= !$stageFilter?' active':'' ?>">
      All &nbsp;<span style="opacity:.6"><?= array_sum($stageCounts) ?></span>
    </a>
    <?php foreach($stages as $s):
      $sc = $stageColors[$s];
      $pillMod = in_array($sc['badge'], ['amber','green','red','blue','dark'], true) ? ' admin-filter-pill--'.$sc['badge'] : '';
    ?>
    <a href="?stage=<?= $s ?>" class="admin-filter-pill<?= $pillMod ?><?= $stageFilter===$s?' active':'' ?>">
      <?= ucfirst($s) ?> &nbsp;<span style="opacity:.6"><?= $stageCounts[$s] ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <button onclick="openAddModal()" class="admin-btn admin-btn--primary" style="font-size:.72rem">+ New Client</button>
</div>

<!-- Leads Table -->
<div class="admin-table-wrap">
<table class="admin-table">
  <thead>
    <tr>
      <th>Date</th><th>Name</th><th>Email / Phone</th><th>Country</th>
      <th>Package</th><th>Travel Date</th><th>Budget</th><th>Stage</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r):
      $sc = $stageColors[$r['pipeline_stage']] ?? $stageColors['lead'];
    ?>
    <tr id="lead-row-<?= $r['id'] ?>">
      <td style="white-space:nowrap;font-size:.78rem"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
      <td>
        <strong style="font-size:.85rem"><?= htmlspecialchars($r['full_name']) ?></strong>
        <?php if (!empty($r['source'])): ?>
        <div style="font-size:.65rem;color:var(--gray-500)"><?= htmlspecialchars($r['source']) ?></div>
        <?php endif; ?>
      </td>
      <td>
        <a href="mailto:<?= htmlspecialchars($r['email']) ?>" style="color:inherit;font-size:.78rem"><?= htmlspecialchars($r['email']) ?></a>
        <?php if (!empty($r['phone'])): ?>
        <div style="font-size:.72rem"><a href="https://wa.me/<?= preg_replace('/[^0-9]/','',($r['phone'])) ?>" target="_blank" style="color:#166534"><?= htmlspecialchars($r['phone']) ?></a></div>
        <?php endif; ?>
      </td>
      <td style="font-size:.82rem"><?= htmlspecialchars($r['country'] ?? '—') ?></td>
      <td style="font-size:.78rem"><?= htmlspecialchars($r['package_name'] ?: '—') ?></td>
      <td style="white-space:nowrap;font-size:.82rem">
        <?= !empty($r['travel_date']) ? date('d/m/Y', strtotime($r['travel_date'])) : '—' ?>
      </td>
      <td style="font-size:.82rem"><?= htmlspecialchars($r['budget_range'] ?? '—') ?></td>
      <td>
        <select onchange="updateStage(<?= $r['id'] ?>, this.value, this)"
                data-prev="<?= htmlspecialchars($r['pipeline_stage']) ?>"
                style="border:1.5px solid var(--gray-200);padding:.25rem .5rem;font-size:.7rem;background:<?= $sc['bg'] ?>;color:<?= $sc['text'] ?>;font-weight:700;max-width:120px;cursor:pointer">
          <?php foreach ($stages as $s): ?>
          <option value="<?= $s ?>" <?= $r['pipeline_stage']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td style="white-space:nowrap">
        <a href="mailto:<?= htmlspecialchars($r['email']) ?>"
           class="admin-btn admin-btn--outline" style="font-size:.65rem;padding:.3rem .55rem"><?= \App\Helpers\AdminIcon::render('messages', 12) ?></a>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',($r['phone']??'')) ?>?text=<?= urlencode('Hi '.$r['full_name'].',') ?>"
           target="_blank"
           class="admin-btn admin-btn--success" style="font-size:.65rem;padding:.3rem .55rem">WA</a>
        <?php if (!empty($r['email'])): ?>
        <a href="../admin/quotation_new.php?from_lead=<?= $r['id'] ?>" target="_blank"
           class="admin-btn admin-btn--tint-blue" style="font-size:.65rem;padding:.3rem .55rem">Quote ↗</a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
    <tr><td colspan="9" class="admin-empty">No leads yet. Click "+ New Client" to book a package or submit a custom request.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination" style="border-top:none">
  <span style="font-size:.75rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?></span>
  <div class="page-btns">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&stage=<?= urlencode($stageFilter) ?>"
       class="page-btn<?= $i===$page?' active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── New Client Modal — choose a path, then fill its form ─── -->
<div id="addLeadModal" class="admin-modal-overlay">
  <div class="admin-modal" style="max-width:640px">
    <div class="admin-modal-header" style="position:sticky;top:0;background:var(--white);z-index:1">
      <span class="admin-modal-title" id="nc-title">New Client</span>
      <button onclick="closeAddModal()" class="admin-icon-btn admin-modal-close"><?= \App\Helpers\AdminIcon::render('close', 18) ?></button>
    </div>
    <div class="admin-modal-body">

      <!-- Screen 0 — choose the path -->
      <div id="nc-screen-choice">
        <p style="font-size:.82rem;color:var(--gray-500);margin:-.25rem 0 1.25rem;line-height:1.6">
          What is this client looking for?
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:.85rem">
          <?php if (AGENT_DIRECT_BOOKING_ENABLED): ?>
          <button type="button" onclick="ncGoto('booking')" class="nc-choice-card">
            <?= \App\Helpers\AdminIcon::render('bookings', 26) ?>
            <strong>Direct Booking</strong>
            <span>They already know which package, dates and party size — book it now.</span>
          </button>
          <?php endif; ?>
          <button type="button" onclick="ncGoto('request')" class="nc-choice-card">
            <?= \App\Helpers\AdminIcon::render('requests', 26) ?>
            <strong>Custom Request</strong>
            <span>They want something bespoke — send it to our team to quote.</span>
          </button>
        </div>
        <?php if (!AGENT_DIRECT_BOOKING_ENABLED): ?>
        <p style="font-size:.74rem;color:var(--gray-500);margin-top:.85rem">Direct booking is temporarily disabled — all clients go through Custom Request for now.</p>
        <?php endif; ?>
      </div>

      <!-- Screen 1 — direct booking of an existing package -->
      <div id="nc-screen-booking" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;margin-bottom:.85rem">
          <div>
            <label class="admin-label">Client Full Name *</label>
            <input id="db-name" class="admin-input" placeholder="Jane Smith" required>
          </div>
          <div>
            <label class="admin-label">Email *</label>
            <input id="db-email" type="email" class="admin-input" placeholder="jane@email.com" required>
          </div>
          <div>
            <label class="admin-label">Phone (WhatsApp)</label>
            <input id="db-phone" class="admin-input" placeholder="+44 7700 900000">
          </div>
          <div>
            <label class="admin-label">Country</label>
            <input id="db-country" class="admin-input" list="country-list" placeholder="United Kingdom">
          </div>
          <div>
            <label class="admin-label">Package *</label>
            <select id="db-package" class="admin-input" required onchange="ncUpdateEstimate()">
              <option value="">— Select package —</option>
              <?php foreach ($packages as $pkg): ?>
              <option value="<?= (int)$pkg['id'] ?>" data-price="<?= $pkg['price_1pax'] ?>"><?= htmlspecialchars($pkg['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="admin-label">Travel Date *</label>
            <input id="db-travel-date" type="date" class="admin-input" min="<?= date('Y-m-d') ?>" style="font-family:monospace" required>
          </div>
          <div>
            <label class="admin-label">Adults</label>
            <input id="db-adults" type="number" min="1" max="20" value="2" class="admin-input" onchange="ncUpdateEstimate()">
          </div>
          <div>
            <label class="admin-label">Children</label>
            <input id="db-children" type="number" min="0" max="20" value="0" class="admin-input" onchange="ncUpdateEstimate()">
          </div>
          <div>
            <label class="admin-label">Accommodation Level</label>
            <select id="db-acc-level" class="admin-input">
              <option value="budget">Budget</option>
              <option value="midrange" selected>Mid-range</option>
              <option value="highend">High-end</option>
            </select>
          </div>
        </div>
        <div id="db-estimate" style="font-size:.78rem;color:var(--gray-500);margin-bottom:1rem;display:none"></div>
        <div style="margin-bottom:1.25rem">
          <label class="admin-label">Special Requests</label>
          <textarea id="db-notes" class="admin-input" rows="3" placeholder="Anything our team should know before confirming..."></textarea>
        </div>
        <div style="display:flex;gap:.5rem">
          <button onclick="ncBack()" class="admin-btn admin-btn--outline">← Back</button>
          <button onclick="submitDirectBooking()" class="admin-btn admin-btn--primary">Submit Booking</button>
          <span id="db-status" style="font-size:.78rem;color:var(--gray-500);display:flex;align-items:center;margin-left:.5rem"></span>
        </div>
      </div>

      <!-- Screen 1b — booking submitted: Track Booking credentials, shown
           once, same reasoning as views/confirmation.php's own flash —
           the toast this used to rely on was wiped by the page reload
           before an agent could read it, let alone relay it to a client. -->
      <div id="nc-screen-booking-done" style="display:none">
        <div style="background:var(--green-light);border:1.5px solid var(--green-border);padding:1.25rem;margin-bottom:1.25rem">
          <p style="font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--green);margin-bottom:.6rem">Booking Submitted</p>
          <p style="font-size:.82rem;color:var(--gray-700);line-height:1.6">Sent to admin for processing. A confirmation email with these same details has also gone to the client.</p>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;margin-bottom:1.25rem">
          <div style="background:var(--gray-50);border-left:4px solid var(--black);padding:1rem">
            <div style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500);margin-bottom:.35rem">Booking ID</div>
            <div id="db-done-ref" style="font-family:monospace;font-size:1.1rem;font-weight:700"></div>
          </div>
          <div style="background:var(--gray-50);border-left:4px solid var(--black);padding:1rem">
            <div style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gray-500);margin-bottom:.35rem">Track Booking Password</div>
            <div id="db-done-pwd" style="font-family:monospace;font-size:1.1rem;font-weight:700"></div>
          </div>
        </div>
        <p style="font-size:.76rem;color:var(--gray-500);margin-bottom:1.25rem;line-height:1.6">Shown once here — if the client can't find their email, this is the only other place to get it. Both are needed together at <code>/track.php</code>.</p>
        <button onclick="ncFinishBooking()" class="admin-btn admin-btn--primary">Done</button>
      </div>

      <!-- Screen 2 — custom safari request -->
      <div id="nc-screen-request" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;margin-bottom:.85rem">
          <div>
            <label class="admin-label">Client Full Name *</label>
            <input id="cr-name" class="admin-input" placeholder="Jane Smith" required>
          </div>
          <div>
            <label class="admin-label">Email *</label>
            <input id="cr-email" type="email" class="admin-input" placeholder="jane@email.com" required>
          </div>
          <div>
            <label class="admin-label">Phone (WhatsApp)</label>
            <input id="cr-phone" class="admin-input" placeholder="+44 7700 900000">
          </div>
          <div>
            <label class="admin-label">Country</label>
            <input id="cr-country" class="admin-input" list="country-list" placeholder="United Kingdom">
          </div>
          <div>
            <label class="admin-label">Destinations</label>
            <input id="cr-destinations" class="admin-input" placeholder="Serengeti, Zanzibar, Ngorongoro">
          </div>
          <div>
            <label class="admin-label">Preferred Start Date</label>
            <input id="cr-start-date" type="date" class="admin-input" min="<?= date('Y-m-d') ?>" style="font-family:monospace">
          </div>
          <div>
            <label class="admin-label">Duration (days)</label>
            <input id="cr-duration" type="number" min="1" max="60" value="7" class="admin-input">
          </div>
          <div>
            <label class="admin-label">Adults</label>
            <input id="cr-adults" type="number" min="1" max="20" value="2" class="admin-input">
          </div>
          <div>
            <label class="admin-label">Children</label>
            <input id="cr-children" type="number" min="0" max="20" value="0" class="admin-input">
          </div>
          <div>
            <label class="admin-label">Budget Range (USD)</label>
            <select id="cr-budget" class="admin-input">
              <option value="">— Select range —</option>
              <option value="Under $1,000">Under $1,000</option>
              <option value="$1,000 – $2,000">$1,000 – $2,000</option>
              <option value="$2,000 – $3,500">$2,000 – $3,500</option>
              <option value="$3,500 – $5,000">$3,500 – $5,000</option>
              <option value="$5,000 – $8,000">$5,000 – $8,000</option>
              <option value="$8,000+">$8,000+</option>
              <option value="Flexible">Flexible / Unknown</option>
            </select>
          </div>
          <div style="display:flex;align-items:center;gap:.4rem;margin-top:1.3rem">
            <input id="cr-flexible" type="checkbox">
            <label for="cr-flexible" style="font-size:.78rem;color:var(--gray-700);cursor:pointer">Dates are flexible</label>
          </div>
        </div>
        <div style="margin-bottom:1.25rem">
          <label class="admin-label">What are they looking for?</label>
          <textarea id="cr-notes" class="admin-input" rows="3" placeholder="Describe what the client wants — experiences, pace, special occasions..."></textarea>
        </div>
        <div style="display:flex;gap:.5rem">
          <button onclick="ncBack()" class="admin-btn admin-btn--outline">← Back</button>
          <button onclick="submitCustomRequest()" class="admin-btn admin-btn--primary">Send Request</button>
          <span id="cr-status" style="font-size:.78rem;color:var(--gray-500);display:flex;align-items:center;margin-left:.5rem"></span>
        </div>
      </div>

    </div>
  </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<style>
.nc-choice-card {
  display:flex; flex-direction:column; align-items:flex-start; gap:.5rem;
  text-align:left; padding:1.1rem; border:1.5px solid var(--gray-200);
  background:var(--gray-50); cursor:pointer; border-radius:var(--radius);
  font-family:inherit; transition:border-color .15s,background .15s;
}
.nc-choice-card:hover { border-color:var(--gold); background:var(--gray-100); }
.nc-choice-card strong { font-size:.88rem; color:var(--black); }
.nc-choice-card span { font-size:.74rem; color:var(--gray-500); line-height:1.5; }
</style>

<script>
var STAGE_COLORS = <?= json_encode($stageColors) ?>;

var NC_SCREENS = ['choice', 'booking', 'booking-done', 'request'];
var NC_TITLES  = {choice: 'New Client', booking: 'Direct Booking', 'booking-done': 'Booking Submitted', request: 'Custom Request'};

function openAddModal() {
    document.getElementById('addLeadModal').classList.add('open');
    ncGoto('choice');
}
function closeAddModal() {
    document.getElementById('addLeadModal').classList.remove('open');
    ['db-name','db-email','db-phone','db-country','db-package','db-notes',
     'cr-name','cr-email','cr-phone','cr-country','cr-destinations','cr-notes','cr-budget'].forEach(function(id){
        var el = document.getElementById(id); if (el) el.value = '';
    });
    ['db-travel-date','cr-start-date'].forEach(function(id){ document.getElementById(id).value = ''; });
    document.getElementById('db-adults').value   = 2;
    document.getElementById('db-children').value = 0;
    document.getElementById('cr-adults').value   = 2;
    document.getElementById('cr-children').value = 0;
    document.getElementById('cr-duration').value = 7;
    document.getElementById('cr-flexible').checked = false;
    document.getElementById('db-estimate').style.display = 'none';
}
function ncGoto(screen) {
    NC_SCREENS.forEach(function(s){ document.getElementById('nc-screen-' + s).style.display = (s===screen) ? '' : 'none'; });
    document.getElementById('nc-title').textContent = NC_TITLES[screen];
}
function ncBack() { ncGoto('choice'); }
document.getElementById('addLeadModal').addEventListener('click', function(e){
    if(e.target === this) closeAddModal();
});
document.addEventListener('keydown', function(e){
    if(e.key==='Escape') closeAddModal();
});

function ncUpdateEstimate() {
    var sel  = document.getElementById('db-package');
    var opt  = sel.options[sel.selectedIndex];
    var box  = document.getElementById('db-estimate');
    var price = opt ? parseFloat(opt.dataset.price || '0') : 0;
    if (!price) { box.style.display = 'none'; return; }
    var travelers = (parseInt(document.getElementById('db-adults').value)||0) + (parseInt(document.getElementById('db-children').value)||0);
    box.style.display = '';
    box.textContent = 'Estimated from $' + price.toFixed(2) + ' per person — exact total is calculated on submit.';
}

async function submitDirectBooking() {
    var status = document.getElementById('db-status');
    var name    = document.getElementById('db-name').value.trim();
    var email   = document.getElementById('db-email').value.trim();
    var pkg     = document.getElementById('db-package').value;
    var date    = document.getElementById('db-travel-date').value;
    if (!name || !email || !pkg || !date) {
        status.textContent = 'Name, email, package and travel date are required.'; status.style.color = 'var(--red)'; return;
    }
    status.textContent = 'Submitting…'; status.style.color = 'var(--gray-500)';
    var fd = new FormData();
    fd.append('action',               'add_direct_booking');
    fd.append('full_name',            name);
    fd.append('email',                email);
    fd.append('phone',                document.getElementById('db-phone').value);
    fd.append('country',              document.getElementById('db-country').value);
    fd.append('package_id',           pkg);
    fd.append('travel_date',          date);
    fd.append('adults',               document.getElementById('db-adults').value);
    fd.append('children',             document.getElementById('db-children').value);
    fd.append('accommodation_level',  document.getElementById('db-acc-level').value);
    fd.append('notes',                document.getElementById('db-notes').value);
    fd.append('_csrf',                window.CSRF_TOKEN || '');

    try {
        var r = await fetch('leads.php', {method:'POST', body:fd});
        var d = await r.json();
        if (d.success) {
            document.getElementById('db-done-ref').textContent = d.booking_ref;
            document.getElementById('db-done-pwd').textContent = d.tracking_password || '—';
            ncGoto('booking-done');
            if (d.commission_created) toast('Commission created', 'ok');
        } else {
            status.textContent = d.message || 'Error submitting booking.'; status.style.color = 'var(--red)';
        }
    } catch(e) {
        status.textContent = 'Network error.'; status.style.color = 'var(--red)';
    }
}

function ncFinishBooking() {
    closeAddModal();
    window.location.reload();
}

async function submitCustomRequest() {
    var status = document.getElementById('cr-status');
    var name   = document.getElementById('cr-name').value.trim();
    var email  = document.getElementById('cr-email').value.trim();
    if (!name || !email) { status.textContent = 'Name and email are required.'; status.style.color = 'var(--red)'; return; }

    status.textContent = 'Sending…'; status.style.color = 'var(--gray-500)';
    var fd = new FormData();
    fd.append('action',           'add_custom_request');
    fd.append('full_name',        name);
    fd.append('email',            email);
    fd.append('phone',            document.getElementById('cr-phone').value);
    fd.append('country',          document.getElementById('cr-country').value);
    fd.append('destinations',     document.getElementById('cr-destinations').value);
    fd.append('start_date',       document.getElementById('cr-start-date').value);
    fd.append('duration_days',    document.getElementById('cr-duration').value);
    fd.append('adults',           document.getElementById('cr-adults').value);
    fd.append('children',         document.getElementById('cr-children').value);
    fd.append('flexible_dates',   document.getElementById('cr-flexible').checked ? '1' : '');
    fd.append('total_budget',     document.getElementById('cr-budget').value);
    fd.append('notes',            document.getElementById('cr-notes').value);
    fd.append('_csrf',            window.CSRF_TOKEN || '');

    try {
        var r = await fetch('leads.php', {method:'POST', body:fd});
        var d = await r.json();
        if (d.success) {
            toast('Custom request sent to admin for a proposal', 'ok');
            closeAddModal();
            setTimeout(function(){ window.location.reload(); }, 900);
        } else {
            status.textContent = d.message || 'Error sending request.'; status.style.color = 'var(--red)';
        }
    } catch(e) {
        status.textContent = 'Network error.'; status.style.color = 'var(--red)';
    }
}

async function updateStage(id, stage, sel) {
    var msg = stage === 'booked'
        ? 'Mark this lead as Booked? This creates your commission record for it.'
        : 'Change this lead\'s stage to "'+sel.options[sel.selectedIndex].text+'"?';
    if (!(await confirmStatusChange(sel, msg, {danger: stage==='lost'}))) return;
    var fd = new FormData();
    fd.append('action','update_stage');
    fd.append('id', id);
    fd.append('stage', stage);
    fd.append('_csrf', window.CSRF_TOKEN || '');
    try {
        var r = await fetch('leads.php',{method:'POST',body:fd});
        var d = await r.json();
        if (d.success) {
            var sc = STAGE_COLORS[stage] || {bg:'var(--gray-100)',text:'var(--gray-700)'};
            sel.style.background = sc.bg; sel.style.color = sc.text;
            sel.dataset.prev = stage;
            toast(d.commission_created ? 'Stage → '+stage+' — commission created' : 'Stage → '+stage,'ok');
        } else {
            sel.value = sel.dataset.prev;
            toast(d.message || 'Failed','err');
        }
    } catch(e) { sel.value = sel.dataset.prev; toast('Failed','err'); }
}

function toast(msg, type) {
    var t = document.createElement('div');
    t.className = 'toast' + (type==='ok' ? ' toast--ok' : type==='err' ? ' toast--err' : '');
    t.textContent = msg;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(function(){ t.remove(); }, 3200);
}
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/agent.php';
