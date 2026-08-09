<?php
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();

$db = \App\Models\Database::getInstance();

// ── Date range filter ──────────────────────────────────────────
$range  = $_GET['range'] ?? '30';  // days
$ranges = ['7'=>'Last 7 days','30'=>'Last 30 days','90'=>'Last 90 days','365'=>'Last 12 months'];
if (!isset($ranges[$range])) $range = '30';
$since = "DATE_SUB(NOW(), INTERVAL {$range} DAY)";

// ── Helper: safe query ──────────────────────────────────────────
function aq($db, $sql, $params = []) {
    try {
        $s = $db->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    } catch (Exception $e) {
        error_log('[SZV Analytics] ' . $e->getMessage());
        return [];
    }
}
function aqv($db, $sql, $params = []) {
    try {
        $s = $db->prepare($sql);
        $s->execute($params);
        return $s->fetchColumn();
    } catch (Exception $e) { return 0; }
}

// ── 1. Total events in range ────────────────────────────────────
$totalEvents  = aqv($db, "SELECT COUNT(*) FROM analytics_events WHERE created_at >= $since");
$uniqueIPs    = aqv($db, "SELECT COUNT(DISTINCT ip_address) FROM analytics_events WHERE created_at >= $since");
$pageViews    = aqv($db, "SELECT COUNT(*) FROM analytics_events WHERE event_name LIKE '%page_view%' AND created_at >= $since");
$interactions = aqv($db, "SELECT COUNT(*) FROM analytics_events WHERE event_name NOT LIKE '%page_view%' AND created_at >= $since");

// ── 2. Event breakdown ──────────────────────────────────────────
$eventBreakdown = aq($db, "
    SELECT event_name, COUNT(*) as cnt,
           COUNT(DISTINCT ip_address) as unique_ips
    FROM analytics_events
    WHERE created_at >= $since
    GROUP BY event_name
    ORDER BY cnt DESC
    LIMIT 20
");

// ── 3. Top pages ────────────────────────────────────────────────
$topPages = aq($db, "
    SELECT page_url, COUNT(*) as views,
           COUNT(DISTINCT ip_address) as unique_visitors
    FROM analytics_events
    WHERE event_name LIKE '%page_view%' AND created_at >= $since
    GROUP BY page_url
    ORDER BY views DESC
    LIMIT 10
");

// ── 4. Top packages ─────────────────────────────────────────────
$topPackages = aq($db, "
    SELECT package_slug, COUNT(*) as views,
           SUM(CASE WHEN event_name='package_view' THEN 1 ELSE 0 END) as pkg_views,
           SUM(CASE WHEN event_name='book_now_click' THEN 1 ELSE 0 END) as book_clicks,
           SUM(CASE WHEN event_name='whatsapp_click' THEN 1 ELSE 0 END) as wa_clicks
    FROM analytics_events
    WHERE package_slug != '' AND package_slug IS NOT NULL AND created_at >= $since
    GROUP BY package_slug
    ORDER BY views DESC
    LIMIT 10
");

// ── 5. Traffic sources (referrers) ──────────────────────────────
$referrers = aq($db, "
    SELECT
        CASE
            WHEN referrer IS NULL OR referrer = '' THEN 'Direct / None'
            WHEN referrer LIKE '%google%' THEN 'Google'
            WHEN referrer LIKE '%facebook%' OR referrer LIKE '%fb.com%' THEN 'Facebook'
            WHEN referrer LIKE '%instagram%' THEN 'Instagram'
            WHEN referrer LIKE '%twitter%' OR referrer LIKE '%t.co%' THEN 'Twitter/X'
            WHEN referrer LIKE '%tiktok%' THEN 'TikTok'
            WHEN referrer LIKE '%whatsapp%' THEN 'WhatsApp'
            WHEN referrer LIKE '%safari-zangu%' OR referrer LIKE '%safarizanguven%' THEN 'Internal'
            ELSE 'Other'
        END as source,
        COUNT(*) as visits,
        COUNT(DISTINCT ip_address) as unique_visitors
    FROM analytics_events
    WHERE created_at >= $since
    GROUP BY source
    ORDER BY visits DESC
");

// ── 6. Daily trend (last N days) ───────────────────────────────
$dailyTrend = aq($db, "
    SELECT DATE(created_at) as day, COUNT(*) as events,
           COUNT(DISTINCT ip_address) as visitors
    FROM analytics_events
    WHERE created_at >= $since
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");

// ── 7. Hourly distribution ──────────────────────────────────────
$hourlyDist = aq($db, "
    SELECT HOUR(created_at) as hr, COUNT(*) as events
    FROM analytics_events
    WHERE created_at >= $since
    GROUP BY HOUR(created_at)
    ORDER BY hr ASC
");

// ── 8. Conversion funnel ────────────────────────────────────────
$funnel = [
    'page_views'          => aqv($db, "SELECT COUNT(DISTINCT ip_address) FROM analytics_events WHERE event_name LIKE '%page_view%' AND created_at >= $since"),
    'package_views'       => aqv($db, "SELECT COUNT(DISTINCT ip_address) FROM analytics_events WHERE event_name='package_view' AND created_at >= $since"),
    'book_now_clicks'     => aqv($db, "SELECT COUNT(DISTINCT ip_address) FROM analytics_events WHERE event_name='book_now_click' AND created_at >= $since"),
    'whatsapp_clicks'     => aqv($db, "SELECT COUNT(DISTINCT ip_address) FROM analytics_events WHERE event_name='whatsapp_click' AND created_at >= $since"),
    'custom_submissions'  => aqv($db, "SELECT COUNT(DISTINCT ip_address) FROM analytics_events WHERE event_name='custom_safari_submission' AND created_at >= $since"),
    'contact_submissions' => aqv($db, "SELECT COUNT(DISTINCT ip_address) FROM analytics_events WHERE event_name='contact_form_submission' AND created_at >= $since"),
    'bookings_completed'  => aqv($db, "SELECT COUNT(*) FROM bookings WHERE created_at >= $since"),
];

// ── 9. Device detection (simple UA parse) ───────────────────────
$devices = aq($db, "
    SELECT
        CASE
            WHEN user_agent LIKE '%Mobile%' OR user_agent LIKE '%Android%' THEN 'Mobile'
            WHEN user_agent LIKE '%iPad%' OR user_agent LIKE '%Tablet%' THEN 'Tablet'
            WHEN user_agent LIKE '%bot%' OR user_agent LIKE '%crawler%' OR user_agent LIKE '%spider%' THEN 'Bot'
            ELSE 'Desktop'
        END as device,
        COUNT(*) as cnt
    FROM analytics_events
    WHERE created_at >= $since
    GROUP BY device
    ORDER BY cnt DESC
");

// ── 10. Peak days ────────────────────────────────────────────────
$peakDays = aq($db, "
    SELECT DAYNAME(created_at) as day_name,
           DAYOFWEEK(created_at) as day_num,
           COUNT(*) as events
    FROM analytics_events
    WHERE created_at >= $since
    GROUP BY day_name, day_num
    ORDER BY day_num ASC
");

// ── 11. Recent raw events ────────────────────────────────────────
$recentEvents = aq($db, "
    SELECT * FROM analytics_events
    ORDER BY created_at DESC LIMIT 50
");

$pageTitle = 'Analytics';
ob_start();
?>

<!-- ── HEADER: Range Filter ───────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.75rem">
  <div>
    <p style="font-size:.65rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--gray-500);margin-bottom:.2rem">Period</p>
    <p style="font-family:var(--font-disp);font-size:1.1rem"><?= $ranges[$range] ?></p>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <?php foreach ($ranges as $v => $l): ?>
    <a href="?range=<?= $v ?>"
       style="padding:.4rem .9rem;font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;border:1.5px solid <?= $v===$range ? '#0a0a0a' : '#e5e7eb' ?>;background:<?= $v===$range ? '#0a0a0a' : '#fff' ?>;color:<?= $v===$range ? '#fff' : '#0a0a0a' ?>">
      <?= $l ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── KPI CARDS ─────────────────────────────────────────────── -->
<div class="metrics-grid" style="margin-bottom:1.75rem">
  <div class="metric-card">
    <span class="metric-label">Total Events</span>
    <span class="metric-value"><?= number_format($totalEvents) ?></span>
    <span class="metric-sub">All tracked interactions</span>
  </div>
  <div class="metric-card metric-card--revenue">
    <span class="metric-label">Unique Visitors</span>
    <span class="metric-value"><?= number_format($uniqueIPs) ?></span>
    <span class="metric-sub">By IP address</span>
  </div>
  <div class="metric-card">
    <span class="metric-label">Page Views</span>
    <span class="metric-value"><?= number_format($pageViews) ?></span>
    <span class="metric-sub">View events only</span>
  </div>
  <div class="metric-card">
    <span class="metric-label">Interactions</span>
    <span class="metric-value"><?= number_format($interactions) ?></span>
    <span class="metric-sub">Clicks, forms, WhatsApp</span>
  </div>
</div>

<!-- ── CONVERSION FUNNEL ─────────────────────────────────────── -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h2>Conversion Funnel</h2></div>
  <div class="admin-card-body">
    <?php
    $funnelSteps = [
        ['Unique Visitors',       $funnel['page_views'],          '#6b7280'],
        ['Viewed a Package',      $funnel['package_views'],        '#3b82f6'],
        ['Clicked Book Now',      $funnel['book_now_clicks'],      '#8b5cf6'],
        ['WhatsApp Enquiry',      $funnel['whatsapp_clicks'],      '#25d366'],
        ['Custom Safari Request', $funnel['custom_submissions'],   '#f59e0b'],
        ['Contact Form Sent',     $funnel['contact_submissions'],  '#06b6d4'],
        ['Booking Completed',     $funnel['bookings_completed'],   '#16a34a'],
    ];
    $maxVal = max(1, $funnelSteps[0][1]);
    foreach ($funnelSteps as $i => [$label, $val, $color]):
        $pct    = $maxVal > 0 ? round(($val / $maxVal) * 100) : 0;
        $convPct= $i > 0 && $funnelSteps[$i-1][1] > 0
                  ? round(($val / $funnelSteps[$i-1][1]) * 100) : 100;
    ?>
    <div style="display:grid;grid-template-columns:180px 1fr 80px 70px;align-items:center;gap:.75rem;margin-bottom:.6rem">
      <span style="font-size:.78rem;font-weight:500"><?= $label ?></span>
      <div style="background:#f3f4f6;height:10px;border-radius:5px;overflow:hidden">
        <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:5px;transition:width .5s ease"></div>
      </div>
      <span style="font-size:.85rem;font-weight:700;text-align:right"><?= number_format($val) ?></span>
      <span style="font-size:.72rem;color:<?= $color ?>;font-weight:700;text-align:right"><?= $pct ?>%</span>
    </div>
    <?php endforeach; ?>

    <?php
    $visitorsToBkd = $funnel['page_views'] > 0
        ? round(($funnel['bookings_completed'] / $funnel['page_views']) * 100, 2) : 0;
    ?>
    <div style="margin-top:1.25rem;padding:.85rem 1rem;background:#f7f7f5;border-left:3px solid #0a0a0a">
      <span style="font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#6b7280">Overall Conversion Rate</span>
      <span style="font-family:var(--font-disp);font-size:1.5rem;font-weight:500;margin-left:1rem"><?= $visitorsToBkd ?>%</span>
      <span style="font-size:.75rem;color:#6b7280;margin-left:.5rem">visitors → bookings</span>
    </div>
  </div>
</div>

<!-- ── DAILY TREND CHART ──────────────────────────────────────── -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header">
    <h2>Daily Traffic Trend</h2>
    <span style="font-size:.7rem;color:var(--gray-500)"><?= $ranges[$range] ?></span>
  </div>
  <div class="admin-card-body" style="overflow-x:auto">
    <?php if (!empty($dailyTrend)): ?>
    <div style="min-width:400px">
      <?php
      $maxDay = max(1, max(array_column($dailyTrend, 'events')));
      $chartH = 120;
      ?>
      <div style="display:flex;align-items:flex-end;gap:3px;height:<?= $chartH ?>px;padding-bottom:0">
        <?php foreach ($dailyTrend as $day):
          $barH = max(2, round(($day['events'] / $maxDay) * $chartH));
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;min-width:8px" title="<?= $day['day'] ?>: <?= $day['events'] ?> events, <?= $day['visitors'] ?> visitors">
          <div style="width:100%;height:<?= $barH ?>px;background:#0a0a0a;border-radius:2px 2px 0 0;opacity:.85;min-height:2px"></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:.35rem;font-size:.62rem;color:#9ca3af">
        <span><?= date('d M', strtotime($dailyTrend[0]['day'])) ?></span>
        <span><?= date('d M', strtotime(end($dailyTrend)['day'])) ?></span>
      </div>
    </div>
    <?php else: ?>
    <p style="color:#9ca3af;font-size:.85rem;text-align:center;padding:2rem">No trend data yet.</p>
    <?php endif; ?>
  </div>
</div>

<!-- ── 3-COL: Events / Pages / Packages ──────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.25rem;margin-bottom:1.5rem">

  <!-- Event Breakdown -->
  <div class="admin-card">
    <div class="admin-card-header"><h2>Event Types</h2></div>
    <div class="admin-card-body" style="padding:0">
      <table class="admin-table" style="min-width:0">
        <thead><tr><th>Event</th><th>Count</th><th>Unique</th></tr></thead>
        <tbody>
        <?php foreach ($eventBreakdown as $e): ?>
        <tr>
          <td style="font-size:.75rem">
            <?php
            $icon = match(true) {
                str_contains($e['event_name'],'page_view')   => '👁',
                str_contains($e['event_name'],'book')        => '📋',
                str_contains($e['event_name'],'whatsapp')    => '💬',
                str_contains($e['event_name'],'contact')     => '✉',
                str_contains($e['event_name'],'custom')      => '🦁',
                str_contains($e['event_name'],'package')     => '🗺',
                default                                       => '•',
            };
            ?>
            <?= $icon ?> <?= htmlspecialchars(str_replace('_',' ', $e['event_name'])) ?>
          </td>
          <td><strong><?= number_format($e['cnt']) ?></strong></td>
          <td style="color:#6b7280"><?= number_format($e['unique_ips']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($eventBreakdown)): ?><tr><td colspan="3" class="admin-empty">No events.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top Pages -->
  <div class="admin-card">
    <div class="admin-card-header"><h2>Top Pages</h2></div>
    <div class="admin-card-body" style="padding:0">
      <table class="admin-table" style="min-width:0">
        <thead><tr><th>Page</th><th>Views</th><th>Uniq</th></tr></thead>
        <tbody>
        <?php foreach ($topPages as $p): ?>
        <tr>
          <td style="font-size:.72rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
              title="<?= htmlspecialchars($p['page_url']) ?>">
            <?= htmlspecialchars(str_replace('/safari-zangu','',$p['page_url'])) ?: '/' ?>
          </td>
          <td><strong><?= number_format($p['views']) ?></strong></td>
          <td style="color:#6b7280"><?= number_format($p['unique_visitors']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($topPages)): ?><tr><td colspan="3" class="admin-empty">No page views.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top Packages -->
  <div class="admin-card">
    <div class="admin-card-header"><h2>Package Interest</h2></div>
    <div class="admin-card-body" style="padding:0">
      <table class="admin-table" style="min-width:0">
        <thead><tr><th>Package</th><th>Views</th><th>Books</th></tr></thead>
        <tbody>
        <?php foreach ($topPackages as $p): ?>
        <tr>
          <td style="font-size:.72rem"><?= htmlspecialchars(str_replace('-',' ', $p['package_slug'])) ?></td>
          <td><strong><?= number_format($p['views']) ?></strong></td>
          <td style="color:#16a34a;font-weight:700"><?= number_format($p['book_clicks']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($topPackages)): ?><tr><td colspan="3" class="admin-empty">No package data.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ── 2-COL: Sources + Devices ──────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem">

  <!-- Traffic Sources -->
  <div class="admin-card">
    <div class="admin-card-header"><h2>Traffic Sources</h2></div>
    <div class="admin-card-body">
      <?php
      $totalVisits = array_sum(array_column($referrers, 'visits')) ?: 1;
      $srcColors   = ['Direct / None'=>'#6b7280','Google'=>'#4285f4','Facebook'=>'#1877f2','Instagram'=>'#e1306c','Twitter/X'=>'#1da1f2','WhatsApp'=>'#25d366','Internal'=>'#0a0a0a','Other'=>'#9ca3af','TikTok'=>'#010101'];
      foreach ($referrers as $r):
        $pct = round(($r['visits'] / $totalVisits) * 100);
        $col = $srcColors[$r['source']] ?? '#9ca3af';
      ?>
      <div style="margin-bottom:.75rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.25rem">
          <span style="font-size:.8rem;font-weight:500"><?= htmlspecialchars($r['source']) ?></span>
          <span style="font-size:.8rem;font-weight:700"><?= number_format($r['visits']) ?> <span style="font-weight:400;color:#9ca3af">(<?= $pct ?>%)</span></span>
        </div>
        <div style="background:#f3f4f6;height:6px;border-radius:3px">
          <div style="width:<?= $pct ?>%;height:100%;background:<?= $col ?>;border-radius:3px"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(empty($referrers)): ?><p class="admin-empty">No source data.</p><?php endif; ?>
    </div>
  </div>

  <!-- Devices + Peak Hours -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Devices -->
    <div class="admin-card">
      <div class="admin-card-header"><h2>Devices</h2></div>
      <div class="admin-card-body" style="display:flex;gap:1.5rem;flex-wrap:wrap">
        <?php
        $totalDev = array_sum(array_column($devices,'cnt')) ?: 1;
        $devIcons = ['Desktop'=>'🖥','Mobile'=>'📱','Tablet'=>'📟','Bot'=>'🤖'];
        $devColors= ['Desktop'=>'#0a0a0a','Mobile'=>'#3b82f6','Tablet'=>'#8b5cf6','Bot'=>'#9ca3af'];
        foreach ($devices as $d):
          $pct = round(($d['cnt'] / $totalDev) * 100);
          $col = $devColors[$d['device']] ?? '#9ca3af';
        ?>
        <div style="text-align:center;flex:1;min-width:70px">
          <div style="font-size:1.5rem;margin-bottom:.3rem"><?= $devIcons[$d['device']] ?? '?' ?></div>
          <div style="font-size:1.2rem;font-weight:700;color:<?= $col ?>"><?= $pct ?>%</div>
          <div style="font-size:.7rem;color:#6b7280;font-weight:500"><?= $d['device'] ?></div>
          <div style="font-size:.68rem;color:#9ca3af"><?= number_format($d['cnt']) ?></div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($devices)): ?><p class="admin-empty">No device data.</p><?php endif; ?>
      </div>
    </div>

    <!-- Peak Hours -->
    <div class="admin-card" style="flex:1">
      <div class="admin-card-header"><h2>Peak Hours (EAT)</h2></div>
      <div class="admin-card-body">
        <?php
        // Build 24h lookup
        $hrLookup = [];
        foreach ($hourlyDist as $h) $hrLookup[(int)$h['hr']] = (int)$h['events'];
        $maxHr = max(1, max($hrLookup ?: [1]));
        // Show condensed bar chart
        $slots = [[0,6,'Night'],[6,12,'Morning'],[12,18,'Afternoon'],[18,24,'Evening']];
        foreach ($slots as [$from, $to, $label]):
          $slotTotal = 0;
          for($i=$from;$i<$to;$i++) $slotTotal += $hrLookup[$i] ?? 0;
          $pct = round(($slotTotal / max(1, array_sum($hrLookup))) * 100);
          $col = $pct > 30 ? '#0a0a0a' : ($pct > 15 ? '#3b82f6' : '#e5e7eb');
        ?>
        <div style="display:grid;grid-template-columns:80px 1fr 50px;align-items:center;gap:.5rem;margin-bottom:.5rem">
          <span style="font-size:.75rem;font-weight:500"><?= $label ?></span>
          <div style="background:#f3f4f6;height:8px;border-radius:4px">
            <div style="width:<?= $pct ?>%;height:100%;background:<?= $col ?>;border-radius:4px"></div>
          </div>
          <span style="font-size:.72rem;font-weight:700;text-align:right"><?= $slotTotal ?></span>
        </div>
        <?php endforeach; ?>
        <?php
        // Best single hour
        if ($hrLookup) {
          $peakHr = array_keys($hrLookup, max($hrLookup))[0];
          echo '<p style="margin-top:.75rem;font-size:.75rem;color:#6b7280">Peak hour: <strong>' . sprintf('%02d:00', $peakHr) . '–' . sprintf('%02d:00', $peakHr+1) . ' EAT</strong> (' . $hrLookup[$peakHr] . ' events)</p>';
        }
        ?>
      </div>
    </div>

  </div>

</div>

<!-- ── PEAK DAYS ───────────────────────────────────────────────── -->
<?php if (!empty($peakDays)): ?>
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h2>Activity by Day of Week</h2></div>
  <div class="admin-card-body">
    <div style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
      <?php
      $maxPeakDay = max(1, max(array_column($peakDays, 'events')));
      foreach ($peakDays as $pd):
        $h = max(20, round(($pd['events'] / $maxPeakDay) * 100));
        $isTop = $pd['events'] === $maxPeakDay;
      ?>
      <div style="text-align:center;flex:1;min-width:50px">
        <div style="font-size:.72rem;font-weight:700;margin-bottom:.25rem"><?= $pd['events'] ?></div>
        <div style="height:<?= $h ?>px;background:<?= $isTop ? '#0a0a0a' : '#e5e7eb' ?>;border-radius:3px 3px 0 0;min-height:4px"></div>
        <div style="font-size:.65rem;font-weight:600;margin-top:.3rem;color:<?= $isTop ? '#0a0a0a' : '#9ca3af' ?>"><?= substr($pd['day_name'],0,3) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── INSIGHTS & RECOMMENDATIONS ─────────────────────────────── -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <div class="admin-card-header"><h2>💡 Insights &amp; Recommendations</h2></div>
  <div class="admin-card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
      <?php
      // Auto-generate insights from data
      $insights = [];

      // Conversion insight
      $conv = $funnel['page_views'] > 0 ? round(($funnel['bookings_completed']/$funnel['page_views'])*100,1) : 0;
      if ($conv < 1) $insights[] = ['⚠', 'Low conversion rate ('.$conv.'%). Add stronger CTAs and pricing transparency to package pages.', '#fef3e2','#92400e'];
      elseif ($conv < 3) $insights[] = ['📈', 'Conversion rate is '.$conv.'% — industry average is 2–4%. Test adding urgency (limited availability) to booking page.', '#eff6ff','#1d4ed8'];
      else $insights[] = ['🎉', 'Strong conversion rate of '.$conv.'%. Focus on driving more top-of-funnel traffic.', '#f0fdf4','#166534'];

      // WhatsApp insight
      $waRatio = $funnel['page_views'] > 0 ? round(($funnel['whatsapp_clicks']/$funnel['page_views'])*100,1) : 0;
      if ($waRatio > 5) $insights[] = ['💬', 'High WhatsApp engagement ('.$waRatio.'%). Respond quickly — these are warm leads. Consider WhatsApp Business automation.', '#f0fdf4','#166534'];
      else $insights[] = ['💬', 'WhatsApp engagement is '.$waRatio.'%. Make the WhatsApp button more prominent on mobile — it\'s your fastest conversion path.', '#fef3e2','#92400e'];

      // Device insight
      $mobileEvents = 0; $totalDevE = 0;
      foreach ($devices as $d) { $totalDevE += $d['cnt']; if ($d['device']==='Mobile') $mobileEvents = $d['cnt']; }
      $mobilePct = $totalDevE > 0 ? round($mobileEvents/$totalDevE*100) : 0;
      if ($mobilePct > 60) $insights[] = ['📱', $mobilePct.'% of visitors are on mobile. Prioritize mobile UX — test the booking form on a phone monthly.', '#eff6ff','#1d4ed8'];

      // Top package insight
      if (!empty($topPackages)) {
        $top = $topPackages[0];
        $insights[] = ['🦁', '"'.ucwords(str_replace('-',' ',$top['package_slug'])).'" gets the most interest ('.number_format($top['views']).' views). Make sure its images, pricing, and itinerary are fully up to date.', '#f0fdf4','#166534'];
      }

      // Peak day insight
      if (!empty($peakDays)) {
        usort($peakDays, fn($a,$b) => $b['events'] <=> $a['events']);
        $insights[] = ['📅', 'Most activity happens on '.$peakDays[0]['day_name'].'s. Schedule social posts and email campaigns for '.($peakDays[0]['day_name'] === 'Sunday' || $peakDays[0]['day_name'] === 'Saturday' ? 'Friday afternoons' : 'the day before').'.', '#faf5ff','#6d28d9'];
      }

      // Traffic source insight
      $directPct = 0;
      foreach ($referrers as $r) { if ($r['source']==='Direct / None') $directPct = round($r['visits']/max(1,array_sum(array_column($referrers,'visits')))*100); }
      if ($directPct > 70) $insights[] = ['🔍', $directPct.'% of traffic is direct. Invest in SEO and social media to diversify traffic sources and reduce dependency on word-of-mouth.', '#fef3e2','#92400e'];

      foreach ($insights as [$icon, $text, $bg, $color]):
      ?>
      <div style="background:<?= $bg ?>;border-left:3px solid <?= $color ?>;padding:.85rem 1rem">
        <p style="font-size:.82rem;font-weight:500;color:<?= $color ?>;line-height:1.6"><?= $icon ?> <?= $text ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── RECENT EVENTS LOG ──────────────────────────────────────── -->
<div class="admin-card">
  <div class="admin-card-header">
    <h2>Recent Events Log</h2>
    <span style="font-size:.72rem;color:var(--gray-500)">Last 50 events</span>
  </div>
  <div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Time</th><th>Event</th><th>Page</th><th>Package</th><th>IP</th><th>Source</th></tr></thead>
    <tbody>
    <?php foreach ($recentEvents as $e): ?>
    <tr>
      <td style="white-space:nowrap;font-size:.72rem"><?= date('d M H:i', strtotime($e['created_at'])) ?></td>
      <td>
        <span style="font-size:.72rem;font-weight:600;background:#f3f4f6;padding:.15rem .4rem">
          <?= htmlspecialchars($e['event_name']) ?>
        </span>
      </td>
      <td style="font-size:.72rem;color:#6b7280;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
        <?= htmlspecialchars(str_replace('/safari-zangu','',$e['page_url'] ?? '')) ?>
      </td>
      <td style="font-size:.72rem"><?= htmlspecialchars($e['package_slug'] ?? '—') ?></td>
      <td style="font-size:.7rem;color:#9ca3af"><?= htmlspecialchars($e['ip_address'] ?? '') ?></td>
      <td style="font-size:.7rem;color:#9ca3af;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
        <?php
        $ref = $e['referrer'] ?? '';
        echo $ref ? htmlspecialchars(parse_url($ref, PHP_URL_HOST) ?: $ref) : '—';
        ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($recentEvents)): ?><tr><td colspan="6" class="admin-empty">No events recorded yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/admin.php';
