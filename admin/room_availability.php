<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Services\AccommodationAvailability;
AuthMiddleware::requireAdmin();
$pageTitle = 'Room Availability';

$rangeStart = date('Y-m-d');
$rangeEnd   = date('Y-m-d', strtotime('+60 days'));
$summary    = AccommodationAvailability::utilizationSummary($rangeStart, $rangeEnd);

// Drill-down: day-by-day commitment for one accommodation
$selectedId   = (int)($_GET['acc'] ?? 0);
$selectedRow  = null;
$dayByDay     = [];
foreach ($summary as $s) {
    if ($s['id'] === $selectedId) {
        $selectedRow = $s;
        $dayByDay    = AccommodationAvailability::committedRoomsByDate($s['name'], $rangeStart, $rangeEnd);
        break;
    }
}

$overCapacity = array_filter($summary, fn($s) => $s['over_capacity']);
$tracked      = count($summary);

ob_start();
?>
<p style="color:var(--gray-500);font-size:.85rem;margin:-.5rem 0 1.25rem">
  Computed live from every active booking's actual itinerary — not a separate reservation system. Set "Available
  Rooms" on an accommodation in <a href="accommodations.php">Accommodations</a> to start tracking it here; it's
  optional per property, and nothing is flagged until you do.
</p>

<div class="admin-stats-grid">
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500)">Properties Tracked</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem"><?= $tracked ?></div>
  </div></div>
  <div class="admin-card"><div class="admin-card-body">
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:<?= $overCapacity ? '#991b1b' : 'var(--gray-500)' ?>">Over Capacity (60d)</div>
    <div style="font-family:var(--font-disp);font-size:1.8rem;margin-top:.25rem;color:<?= $overCapacity ? '#991b1b' : 'inherit' ?>"><?= count($overCapacity) ?></div>
  </div></div>
</div>

<?php if (!$tracked): ?>
<div class="admin-card"><div class="admin-card-body">
  <p style="color:var(--gray-500)">No accommodation has an "Available Rooms" figure set yet — add one in <a href="accommodations.php">Accommodations</a> to start seeing utilization here.</p>
</div></div>
<?php else: ?>

<div class="admin-card" style="margin-bottom:1.25rem">
  <div class="admin-card-header"><h3>Property Utilization — Next 60 Days</h3></div>
  <div class="admin-table-wrap">
  <table class="admin-table">
  <thead><tr><th>Property</th><th>Available Rooms</th><th>Peak Committed</th><th>Peak Date</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($summary as $s): ?>
  <tr style="<?= $s['over_capacity'] ? 'background:#fef2f2' : '' ?>">
    <td><?= htmlspecialchars($s['name']) ?></td>
    <td><?= $s['available_rooms'] ?></td>
    <td style="font-weight:700"><?= $s['peak_committed'] ?></td>
    <td><?= $s['peak_date'] ? date('d M Y', strtotime($s['peak_date'])) : '—' ?></td>
    <td>
      <?php if ($s['over_capacity']): ?>
      <span style="background:#fef2f2;color:#991b1b;font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:2px">Over Capacity</span>
      <?php elseif ($s['peak_committed'] > 0): ?>
      <span style="background:#f0fdf4;color:#166534;font-size:.68rem;padding:.15rem .5rem;border-radius:2px">OK</span>
      <?php else: ?>
      <span style="color:var(--gray-400);font-size:.68rem">No bookings</span>
      <?php endif; ?>
    </td>
    <td><a href="?acc=<?= $s['id'] ?>" class="admin-btn admin-btn--outline" style="font-size:.65rem">Day by Day</a></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>

<?php if ($selectedRow): ?>
<div class="admin-card">
  <div class="admin-card-header">
    <h3><?= htmlspecialchars($selectedRow['name']) ?> — Day by Day</h3>
    <a href="room_availability.php" class="admin-btn admin-btn--outline" style="font-size:.7rem">Close</a>
  </div>
  <div class="admin-card-body">
    <?php if (!$dayByDay): ?>
    <p style="color:var(--gray-500);font-size:.85rem">No committed rooms in the next 60 days.</p>
    <?php else: ksort($dayByDay); ?>
    <table style="width:100%;font-size:.85rem;border-collapse:collapse">
      <thead><tr style="border-bottom:2px solid var(--gray-200)">
        <th style="text-align:left;padding:.4rem 0;font-size:.62rem;text-transform:uppercase;color:var(--gray-500)">Date</th>
        <th style="text-align:left;padding:.4rem 0;font-size:.62rem;text-transform:uppercase;color:var(--gray-500)">Committed</th>
        <th style="text-align:left;padding:.4rem 0;font-size:.62rem;text-transform:uppercase;color:var(--gray-500)">Available</th>
        <th style="text-align:left;padding:.4rem 0;font-size:.62rem;text-transform:uppercase;color:var(--gray-500)">Status</th>
      </tr></thead>
      <tbody>
      <?php foreach ($dayByDay as $date => $rooms): $over = $rooms > $selectedRow['available_rooms']; ?>
      <tr style="border-bottom:1px solid var(--gray-100);<?= $over ? 'background:#fef2f2' : '' ?>">
        <td style="padding:.5rem 0"><?= date('l, d M Y', strtotime($date)) ?></td>
        <td style="padding:.5rem 0;font-weight:700"><?= $rooms ?></td>
        <td style="padding:.5rem 0"><?= $selectedRow['available_rooms'] ?></td>
        <td style="padding:.5rem 0"><?= $over ? '<span style="color:#991b1b;font-weight:700">Over by ' . ($rooms - $selectedRow['available_rooms']) . '</span>' : '<span style="color:#166534">OK</span>' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
