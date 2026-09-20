<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
$db = \App\Models\Database::getInstance();
$pageTitle = 'Operations Dashboard';

// ── Next 30 days: departures + assignment gaps ──────────────────
$upcoming = [];
try {
    $upcoming = $db->query("
        SELECT b.booking_ref, b.full_name, b.travel_date, b.adults, b.children,
               ba.staff_id, ba.vehicle_id
        FROM bookings b
        LEFT JOIN booking_assignments ba ON ba.booking_ref = b.booking_ref
        WHERE b.payment_status != 'cancelled'
          AND b.travel_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY b.travel_date
    ")->fetchAll();
} catch (\Exception $e) {}

$unassigned = array_filter($upcoming, fn($b) => empty($b['staff_id']) || empty($b['vehicle_id']));

// ── Overdue reminders ─────────────────────────────────────────
$overdueReminders = 0;
try { $overdueReminders = (int)$db->query("SELECT COUNT(*) FROM booking_reminders WHERE status='pending' AND send_at < NOW()")->fetchColumn(); }
catch (\Exception $e) {}

// ── Active roster snapshot ──────────────────────────────────────
$activeStaffCount    = 0;
$activeVehicleCount  = 0;
try { $activeStaffCount   = (int)$db->query("SELECT COUNT(*) FROM staff WHERE is_active=1")->fetchColumn(); } catch (\Exception $e) {}
try { $activeVehicleCount = (int)$db->query("SELECT COUNT(*) FROM vehicles WHERE is_active=1")->fetchColumn(); } catch (\Exception $e) {}

$openIncidents = 0;
try { $openIncidents = (int)$db->query("SELECT COUNT(*) FROM incidents WHERE status='open'")->fetchColumn(); } catch (\Exception $e) {}

ob_start();
?>
<p style="color:var(--gray-500);font-size:.85rem;margin:-.5rem 0 1.25rem">
  What needs attention on the ground in the next 30 days — unassigned departures, overdue reminders, and roster capacity.
</p>

<div class="admin-stats-grid">
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Departures (30d)</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= count($upcoming) ?></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:<?= count($unassigned) ? '#991b1b' : 'var(--gray-500)' ?>">Unassigned</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem;color:<?= count($unassigned) ? '#991b1b' : 'inherit' ?>"><?= count($unassigned) ?></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:<?= $overdueReminders ? '#991b1b' : 'var(--gray-500)' ?>">Overdue Reminders</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem;color:<?= $overdueReminders ? '#991b1b' : 'inherit' ?>"><?= $overdueReminders ?></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Active Roster</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $activeStaffCount ?> <span style="font-size:1rem;color:var(--gray-500)">guides · <?= $activeVehicleCount ?> vehicles</span></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:<?= $openIncidents ? '#991b1b' : 'var(--gray-500)' ?>">Open Incidents</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem;color:<?= $openIncidents ? '#991b1b' : 'inherit' ?>"><?= $openIncidents ?></div>
  </div></div>
</div>

<?php if ($unassigned): ?>
<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3 style="color:#991b1b">Needs a Guide/Vehicle — Next 30 Days</h3><a href="operations_calendar.php" class="admin-btn admin-btn--outline" style="font-size:.68rem">Open Calendar →</a></div>
  <div class="admin-table-wrap">
  <table class="admin-table">
  <thead><tr><th>Departs</th><th>Booking</th><th>Guest</th><th>Pax</th><th>Missing</th></tr></thead>
  <tbody>
  <?php foreach ($unassigned as $u): ?>
  <tr>
    <td><?= date('d M', strtotime($u['travel_date'])) ?></td>
    <td><a href="operations_calendar.php?month=<?= date('Y-m', strtotime($u['travel_date'])) ?>&day=<?= $u['travel_date'] ?>"><?= htmlspecialchars($u['booking_ref']) ?></a></td>
    <td><?= htmlspecialchars($u['full_name']) ?></td>
    <td><?= (int)$u['adults'] ?>A<?= $u['children'] ? '+'.(int)$u['children'].'C' : '' ?></td>
    <td>
      <?php $missing = []; if (empty($u['staff_id'])) $missing[] = 'Guide'; if (empty($u['vehicle_id'])) $missing[] = 'Vehicle'; ?>
      <span style="color:#991b1b;font-weight:600;font-size:.78rem"><?= implode(' + ', $missing) ?></span>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php else: ?>
<div class="admin-alert admin-alert--success" style="margin-bottom:1.25rem">All departures in the next 30 days have a guide and vehicle assigned.</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
