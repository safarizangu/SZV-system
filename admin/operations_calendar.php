<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();

// ── Assign guide/driver + vehicle to a booking ────────────────
// Conflict-blocking is same-day only (exact travel_date match) — the
// same exact-date model the calendar itself uses, rather than an
// estimated multi-day trip span that could false-block a legitimately
// free person/vehicle.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    \App\Middleware\CsrfMiddleware::validateOrDie();
    $bookingRef = trim($_POST['booking_ref'] ?? '');
    $staffId    = (int)($_POST['staff_id'] ?? 0) ?: null;
    $vehicleId  = (int)($_POST['vehicle_id'] ?? 0) ?: null;
    $backMonth  = trim($_POST['back_month'] ?? date('Y-m'));
    $backDay    = trim($_POST['back_day'] ?? '');
    $conflictMsg = '';

    try {
        $bkStmt = $db->prepare("SELECT travel_date FROM bookings WHERE booking_ref=? LIMIT 1");
        $bkStmt->execute([$bookingRef]);
        $travelDate = $bkStmt->fetchColumn();

        if ($travelDate) {
            if ($staffId) {
                $c = $db->prepare("
                    SELECT ba.booking_ref FROM booking_assignments ba
                    JOIN bookings b ON b.booking_ref = ba.booking_ref
                    WHERE ba.staff_id=? AND b.travel_date=? AND ba.booking_ref != ? LIMIT 1
                ");
                $c->execute([$staffId, $travelDate, $bookingRef]);
                if ($conflict = $c->fetchColumn()) $conflictMsg = "That guide/driver is already assigned to booking {$conflict} on this date.";
            }
            if (!$conflictMsg && $vehicleId) {
                $c = $db->prepare("
                    SELECT ba.booking_ref FROM booking_assignments ba
                    JOIN bookings b ON b.booking_ref = ba.booking_ref
                    WHERE ba.vehicle_id=? AND b.travel_date=? AND ba.booking_ref != ? LIMIT 1
                ");
                $c->execute([$vehicleId, $travelDate, $bookingRef]);
                if ($conflict = $c->fetchColumn()) $conflictMsg = "That vehicle is already assigned to booking {$conflict} on this date.";
            }
        }

        if (!$conflictMsg && $bookingRef) {
            $db->prepare("
                INSERT INTO booking_assignments (booking_ref, staff_id, vehicle_id)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE staff_id=VALUES(staff_id), vehicle_id=VALUES(vehicle_id)
            ")->execute([$bookingRef, $staffId, $vehicleId]);
        }
    } catch (\Exception $e) {
        $conflictMsg = 'Could not save assignment: ' . $e->getMessage();
    }

    header('Location: operations_calendar.php?month=' . urlencode($backMonth) . '&day=' . urlencode($backDay)
        . ($conflictMsg ? '&amsg=' . urlencode($conflictMsg) : ''));
    exit;
}

// ── Month navigation ─────────────────────────────────────────
$monthParam = trim($_GET['month'] ?? '');
$monthTs    = $monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam) ? strtotime($monthParam . '-01') : strtotime(date('Y-m-01'));
$monthStart = date('Y-m-01', $monthTs);
$monthEnd   = date('Y-m-t', $monthTs);
$prevMonth  = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth  = date('Y-m', strtotime($monthStart . ' +1 month'));
$monthLabel = date('F Y', $monthTs);
$today      = date('Y-m-d');

$selectedDay = trim($_GET['day'] ?? '');
if ($selectedDay && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDay)) $selectedDay = '';

// ── Departures: bookings whose travel_date falls in this month ──
// Exact-date grouping only (no estimated trip-span logic) — a
// booking's package/itinerary duration is a planning figure, not a
// confirmed return date, so this calendar shows confirmed departure
// days only rather than guessing at occupied ranges.
$departuresByDate = [];
try {
    $stmt = $db->prepare("
        SELECT b.id, b.booking_ref, b.full_name, b.travelers, b.adults, b.children,
               b.package_name, b.travel_date, b.payment_status, b.accommodation_level,
               COALESCE(p.duration_days, i.duration_days) AS trip_duration,
               ba.staff_id, ba.vehicle_id
        FROM bookings b
        LEFT JOIN packages p ON p.id = b.package_id
        LEFT JOIN itineraries i ON i.id = b.itinerary_id
        LEFT JOIN booking_assignments ba ON ba.booking_ref = b.booking_ref
        WHERE b.payment_status != 'cancelled'
          AND b.travel_date BETWEEN ? AND ?
        ORDER BY b.travel_date, b.full_name
    ");
    $stmt->execute([$monthStart, $monthEnd]);
    foreach ($stmt->fetchAll() as $row) {
        $departuresByDate[$row['travel_date']][] = $row;
    }
} catch (\Exception $e) { error_log('[operations_calendar] departures: ' . $e->getMessage()); }

// ── Reminders due this month (deposit/balance/departure/post-trip) ──
$remindersByDate = [];
try {
    $stmt = $db->prepare("
        SELECT id, booking_ref, type, send_at, status
        FROM booking_reminders
        WHERE status = 'pending' AND DATE(send_at) BETWEEN ? AND ?
        ORDER BY send_at
    ");
    $stmt->execute([$monthStart, $monthEnd]);
    foreach ($stmt->fetchAll() as $row) {
        $remindersByDate[date('Y-m-d', strtotime($row['send_at']))][] = $row;
    }
} catch (\Exception $e) { error_log('[operations_calendar] reminders: ' . $e->getMessage()); }

$reminderLabels = [
    'deposit_due' => 'Deposit Due', 'balance_due' => 'Balance Due',
    'departure'   => 'Departure Reminder', 'post_trip' => 'Post-Trip', 'custom' => 'Reminder',
];

$activeStaff    = [];
$activeVehicles = [];
try { $activeStaff    = $db->query("SELECT id, full_name, role FROM staff WHERE is_active=1 ORDER BY full_name")->fetchAll(); } catch (\Exception $e) {}
try { $activeVehicles = $db->query("SELECT id, name FROM vehicles WHERE is_active=1 ORDER BY name")->fetchAll(); } catch (\Exception $e) {}
$assignConflict = trim($_GET['amsg'] ?? '');

// Same status colors as admin/bookings.php, so a payment_status pill
// reads identically wherever it appears.
$statusColors = [
    'pending'      => ['bg'=>'var(--amber-light)','color'=>'#92400e','label'=>'Pending'],
    'deposit_paid' => ['bg'=>'var(--blue-light)', 'color'=>'#1e40af','label'=>'Deposit Paid'],
    'fully_paid'   => ['bg'=>'var(--black)',       'color'=>'var(--white)','label'=>'Fully Paid'],
    'cancelled'    => ['bg'=>'var(--red-light)',  'color'=>'#991b1b','label'=>'Cancelled'],
];

// ── Selected day detail ──────────────────────────────────────
$dayBookings  = $selectedDay ? ($departuresByDate[$selectedDay] ?? []) : [];
$dayReminders = $selectedDay ? ($remindersByDate[$selectedDay] ?? []) : [];

// ── Month-at-a-glance totals ─────────────────────────────────
$totalDepartures = array_sum(array_map('count', $departuresByDate));
$totalPax        = 0;
foreach ($departuresByDate as $rows) foreach ($rows as $r) $totalPax += (int)$r['travelers'];
$totalRemindersDue = array_sum(array_map('count', $remindersByDate));

$pageTitle = 'Operations Calendar';
ob_start();
?>
<p style="color:var(--gray-500);font-size:.85rem;margin:-.5rem 0 1.25rem">Confirmed departures and reminders due, by day — built from existing booking data, no separate schedule to maintain.</p>

<div class="admin-stats-grid">
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Departures This Month</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $totalDepartures ?></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Travelers Departing</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $totalPax ?></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Reminders Due</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $totalRemindersDue ?></div>
  </div></div>
</div>

<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header">
    <div style="display:flex;align-items:center;gap:.75rem">
      <a href="?month=<?= $prevMonth ?>" class="admin-btn admin-btn--outline" style="font-size:.75rem">&larr;</a>
      <h3 style="min-width:150px;text-align:center"><?= $monthLabel ?></h3>
      <a href="?month=<?= $nextMonth ?>" class="admin-btn admin-btn--outline" style="font-size:.75rem">&rarr;</a>
    </div>
    <a href="?month=<?= date('Y-m') ?>" class="admin-btn admin-btn--outline" style="font-size:.7rem">Today</a>
  </div>
  <div class="admin-card-body" style="overflow-x:auto">
    <div style="display:grid;grid-template-columns:repeat(7,minmax(90px,1fr));gap:1px;background:var(--gray-200);border:1px solid var(--gray-200);min-width:630px">
      <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
      <div style="background:var(--gray-50);padding:.4rem;text-align:center;font-size:.62rem;letter-spacing:.06em;text-transform:uppercase;color:var(--gray-500);font-weight:600"><?= $dow ?></div>
      <?php endforeach; ?>

      <?php
      $firstDow = (int)date('w', $monthTs);
      for ($i = 0; $i < $firstDow; $i++) echo '<div style="background:var(--gray-50)"></div>';

      $daysInMonth = (int)date('t', $monthTs);
      for ($d = 1; $d <= $daysInMonth; $d++):
          $dateStr   = date('Y-m-d', mktime(0, 0, 0, (int)date('n', $monthTs), $d, (int)date('Y', $monthTs)));
          $deps      = $departuresByDate[$dateStr] ?? [];
          $rems      = $remindersByDate[$dateStr]   ?? [];
          $isToday   = $dateStr === $today;
          $isSelected= $dateStr === $selectedDay;
      ?>
      <a href="?month=<?= date('Y-m', $monthTs) ?>&day=<?= $dateStr ?>"
         style="background:<?= $isSelected ? 'var(--gray-100)' : 'var(--white)' ?>;min-height:90px;padding:.4rem;display:block;text-decoration:none;color:inherit;border-left:<?= $isSelected ? '3px solid var(--black)' : '3px solid transparent' ?>">
        <div style="font-size:.75rem;font-weight:<?= $isToday ? '700' : '400' ?>;color:<?= $isToday ? 'var(--black)' : 'var(--gray-500)' ?>">
          <?= $d ?><?= $isToday ? ' <span style="font-size:.6rem">&bull; today</span>' : '' ?>
        </div>
        <?php if ($deps): ?>
        <div style="margin-top:.3rem;background:#0a0a0a;color:#fff;font-size:.62rem;padding:.15rem .35rem;display:inline-block;border-radius:2px">
          <?= count($deps) ?> departure<?= count($deps) !== 1 ? 's' : '' ?>
        </div>
        <?php endif; ?>
        <?php if ($rems): ?>
        <div style="margin-top:.25rem;background:#fef3c7;color:#92400e;font-size:.62rem;padding:.15rem .35rem;display:inline-block;border-radius:2px">
          <?= count($rems) ?> due
        </div>
        <?php endif; ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
</div>

<?php if ($selectedDay): ?>
<div class="admin-card">
  <div class="admin-card-header">
    <h3><?= date('l, j F Y', strtotime($selectedDay)) ?></h3>
    <a href="?month=<?= date('Y-m', $monthTs) ?>" class="admin-btn admin-btn--outline" style="font-size:.7rem">Close</a>
  </div>
  <div class="admin-card-body">

    <h4 style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500);margin-bottom:.6rem">Departures</h4>
    <?php if ($assignConflict): ?>
    <div class="admin-alert admin-alert--error" style="margin-bottom:.85rem"><?= htmlspecialchars($assignConflict) ?></div>
    <?php endif; ?>
    <?php if (!$dayBookings): ?>
    <p style="color:var(--gray-500);font-size:.85rem;margin-bottom:1.25rem">No departures scheduled.</p>
    <?php else: ?>
    <div style="overflow-x:auto;margin-bottom:1.25rem">
    <table style="width:100%;min-width:640px;font-size:.85rem;border-collapse:collapse">
      <thead><tr style="border-bottom:2px solid var(--gray-200)">
        <th style="text-align:left;padding:.4rem 0;font-size:.62rem;text-transform:uppercase;color:var(--gray-500)">Ref</th>
        <th style="text-align:left;padding:.4rem 0;font-size:.62rem;text-transform:uppercase;color:var(--gray-500)">Guest</th>
        <th style="text-align:left;padding:.4rem 0;font-size:.62rem;text-transform:uppercase;color:var(--gray-500)">Pax</th>
        <th style="text-align:left;padding:.4rem 0;font-size:.62rem;text-transform:uppercase;color:var(--gray-500)">Status</th>
        <th style="text-align:left;padding:.4rem 0;font-size:.62rem;text-transform:uppercase;color:var(--gray-500)">Guide/Driver</th>
        <th style="text-align:left;padding:.4rem 0;font-size:.62rem;text-transform:uppercase;color:var(--gray-500)">Vehicle</th>
      </tr></thead>
      <tbody>
      <?php foreach ($dayBookings as $bk):
          $returnEst = $bk['trip_duration'] ? date('j M', strtotime($selectedDay . ' +' . ((int)$bk['trip_duration'] - 1) . ' days')) : null;
      ?>
      <tr style="border-bottom:1px solid var(--gray-100)">
        <td style="padding:.5rem 0"><a href="bookings.php?search=<?= urlencode($bk['booking_ref']) ?>"><?= htmlspecialchars($bk['booking_ref']) ?></a></td>
        <td style="padding:.5rem 0">
          <?= htmlspecialchars($bk['full_name']) ?>
          <div style="font-size:.72rem;color:var(--gray-500)">
            <?= htmlspecialchars($bk['package_name']) ?><?= $returnEst ? ' · back ~' . $returnEst : '' ?>
            · <a href="../api/voucher_pdf.php?ref=<?= urlencode($bk['booking_ref']) ?>" target="_blank">Voucher</a>
          </div>
        </td>
        <td style="padding:.5rem 0"><?= (int)$bk['adults'] ?>A<?= $bk['children'] ? '+' . (int)$bk['children'] . 'C' : '' ?></td>
        <td style="padding:.5rem 0">
          <?php $sc = $statusColors[$bk['payment_status']] ?? ['bg'=>'var(--gray-100)','color'=>'var(--gray-700)','label'=>ucwords(str_replace('_',' ',$bk['payment_status']))]; ?>
          <span style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-size:.68rem;padding:.15rem .5rem;border-radius:2px"><?= $sc['label'] ?></span>
        </td>
        <td style="padding:.5rem 0" colspan="2">
          <form method="POST" style="display:flex;gap:.4rem;align-items:center">
            <?= \App\Middleware\CsrfMiddleware::field() ?>
            <input type="hidden" name="assign" value="1">
            <input type="hidden" name="booking_ref" value="<?= htmlspecialchars($bk['booking_ref']) ?>">
            <input type="hidden" name="back_month" value="<?= date('Y-m', $monthTs) ?>">
            <input type="hidden" name="back_day" value="<?= $selectedDay ?>">
            <select name="staff_id" class="admin-input" style="font-size:.78rem;padding:.3rem" onchange="this.form.submit()">
              <option value="0">— Unassigned —</option>
              <?php foreach ($activeStaff as $st): ?>
              <option value="<?= $st['id'] ?>" <?= (int)($bk['staff_id'] ?? 0) === (int)$st['id'] ? 'selected' : '' ?>><?= htmlspecialchars($st['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="vehicle_id" class="admin-input" style="font-size:.78rem;padding:.3rem" onchange="this.form.submit()">
              <option value="0">— No vehicle —</option>
              <?php foreach ($activeVehicles as $v): ?>
              <option value="<?= $v['id'] ?>" <?= (int)($bk['vehicle_id'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>

    <h4 style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500);margin-bottom:.6rem">Reminders Due</h4>
    <?php if (!$dayReminders): ?>
    <p style="color:var(--gray-500);font-size:.85rem">None due.</p>
    <?php else: ?>
    <ul style="list-style:none;padding:0;margin:0">
      <?php foreach ($dayReminders as $rm): ?>
      <li style="padding:.5rem 0;border-bottom:1px solid var(--gray-100);font-size:.85rem">
        <strong><?= htmlspecialchars($reminderLabels[$rm['type']] ?? $rm['type']) ?></strong>
        — <a href="bookings.php?search=<?= urlencode($rm['booking_ref']) ?>"><?= htmlspecialchars($rm['booking_ref']) ?></a>
        <span style="color:var(--gray-500)">at <?= date('g:i A', strtotime($rm['send_at'])) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
