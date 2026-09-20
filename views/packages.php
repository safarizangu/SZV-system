<?php
try {
    $db = \App\Models\Database::getInstance();
    $packages = $db->query(
        "SELECT * FROM packages WHERE is_active=1 ORDER BY sort_order ASC, id ASC"
    )->fetchAll();
    $pkgMap = [];
    // Full day-by-day plan per package, keyed by package id — pulled from
    // the itineraries table (rich per-day accommodation/vehicle/park data)
    // rather than packages.itinerary (a much thinner title+description-only
    // field), so guests see the same day cards admins build in the
    // Itinerary Manager, not a degraded version of them.
    $pkgItinDays = [];
    foreach ($packages as &$p) {
        $p["gallery"]       = json_decode($p["gallery_images"] ?? "[]", true) ?: [];
        $p["includes_arr"]  = json_decode($p["includes"]       ?? "[]", true) ?: [];
        $p["excludes_arr"]  = json_decode($p["excludes"]       ?? "[]", true) ?: [];
        $pkgMap[$p["slug"]] = $p;

        if (!empty($p['itinerary_id'])) {
            try {
                $is = $db->prepare("SELECT days FROM itineraries WHERE id=? AND is_active=1 LIMIT 1");
                $is->execute([$p['itinerary_id']]);
                $itinRow = $is->fetch();
                if ($itinRow) {
                    $pkgItinDays[$p['id']] = json_decode($itinRow['days'] ?? '[]', true) ?: [];
                }
            } catch (\Exception $e) {}
        }
    }
    unset($p);
} catch (\Exception $e) {
    $packages = []; $pkgMap = []; $pkgItinDays = [];
    error_log("[view] " . $e->getMessage());
}

?>
<style><?= \App\Helpers\ItineraryDayCards::css() ?></style>

<div class="page-hero">
  <div class="page-hero-inner container-sz">
    <div>
      <div class="breadcrumb-sz"><a href="<?= BASE_PATH ?>/">Home</a><span>/</span>Packages</div>
      <h1 class="display-1"><em>Safari</em><br>Packages</h1>
    </div>
    <div>
      <p style="color:var(--black);font-size:.95rem;font-weight:500;line-height:1.8">Six thoughtfully designed Tanzania & Zanzibar experiences, each available in Budget, Mid-Range, or High-End accommodation. All prices are per person in USD.</p>
      <div class="mt-3" style="font-size:.78rem;font-weight:500;color:var(--black);line-height:1.9">
        ✓ &nbsp;Private 4WD vehicles &nbsp;·&nbsp; ✓ &nbsp;Professional guides<br>
        ✓ &nbsp;All park fees included &nbsp;·&nbsp; ✓ &nbsp;Airport transfers
      </div>
    </div>
  </div>
</div>

<div class="section">
  <div class="container-sz">

    <!-- Package Navigation -->
    <div class="pkg-nav">
      <?php foreach ($packages as $i => $pkg): ?>
        <a href="#<?= htmlspecialchars($pkg['slug']) ?>"><?= sprintf('%02d', $i + 1) ?>. <?= htmlspecialchars($pkg['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <?php foreach ($packages as $i => $pkg):
        $num         = sprintf('%02d', $i + 1);
        $includes    = decodeJsonField($pkg['includes']);
        $excludes    = decodeJsonField($pkg['excludes']);
        $itinDays    = $pkgItinDays[$pkg['id']] ?? [];

        // "Starting from" marketing figure — the real total for an actual
        // booking depends on the traveler's exact party size and is
        // computed at quotation/booking time, not read from here.
        $startPrice = (float)($pkg['price_1pax'] ?? 0);
    ?>
    <div class="pkg-section" data-pkg-card="true" id="<?= htmlspecialchars($pkg['slug']) ?>">
      <span class="pkg-section-num"><?= $num ?></span>
      <div class="pkg-detail-grid">
        <div>
          <div class="pkg-section-header" data-pkg-card="true">
            <h2 class="display-2"><?= htmlspecialchars($pkg['name']) ?></h2>
            <p class="eyebrow mt-2"><?= (int)$pkg['duration_days'] ?> Days · <?= (int)$pkg['duration_days'] - 1 ?> Nights · duration: <?= (int)$pkg['duration_days'] - 1 ?> nights</p>
            <div class="divider-line"></div>
            <p style="color:var(--black);font-weight:500;line-height:1.8;max-width:580px"><?= htmlspecialchars($pkg['overview'] ?? $pkg['description'] ?? '') ?></p>
          </div>

          <!-- Mobile-only price teaser — full pricing/booking widget still
               follows at the end of this section for every viewport;
               this just surfaces the price sooner on a stacked mobile
               layout, before the gallery/includes/itinerary content. -->
          <div class="pkg-price-teaser">
            <span>From $<?= number_format($startPrice) ?> <small style="opacity:.65;font-weight:500"><?= approxAll($startPrice) ?></small> <em>per person</em></span>
            <a href="<?= BASE_PATH ?>/book.php?package_id=<?= $pkg['id'] ?>" class="btn-sz-dark">Book Now</a>
          </div>

                    <!-- ── IMAGE GALLERY — from packages.gallery_images (Admin → Packages) ── -->
          <div class="pkg-gallery" >
            <?php if (!empty($pkg['gallery'])): ?>
              <?php foreach ($pkg['gallery'] as $gi => $imgUrl): ?>
            <img loading="lazy" src="<?= htmlspecialchars(BASE_PATH . $imgUrl) ?>" alt="<?= htmlspecialchars($pkg['name']) ?><?= $gi ? ' '.($gi+1) : '' ?>"
                 onerror="this.style.display='none'"
                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;">
              <?php endforeach; ?>
            <?php else: ?>
            <img loading="lazy" src="<?= BASE_PATH ?>/assets/images/logo.jpeg" alt="<?= htmlspecialchars($pkg['name']) ?>"
                 onerror="this.style.display='none'"
                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;">
            <?php endif; ?>
            <button type="button" class="pkg-slider-btn pkg-slider-prev pkg-prev" aria-label="Previous"
              >&#8249;</button>
            <button type="button" class="pkg-slider-btn pkg-slider-next pkg-next" aria-label="Next"
              >&#8250;</button>
          </div>

          <div class="include-exclude mt-4">
            <div>
              <h5 class="font-display mb-3" style="font-size:.9rem;letter-spacing:.05em">Included</h5>
              <ul class="includes-list">
                <?php foreach ($includes as $inc): ?>
                  <li><?= htmlspecialchars($inc) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div>
              <h5 class="font-display mb-3" style="font-size:.9rem;letter-spacing:.05em">Excluded</h5>
              <ul class="excludes-list">
                <?php foreach ($excludes as $exc): ?>
                  <li><?= htmlspecialchars($exc) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>

          <?php
            // Named lodges, deduped in stay order — pulled from the same
            // itinerary day-blocks as the collapsed breakdown below, but
            // surfaced here so a buyer sees exactly where they'll sleep
            // without needing to open "Day-by-Day Itinerary" first.
            $lodgeNames = [];
            $lodgeAlts  = [];
            foreach ($itinDays as $d) {
                $ln = trim($d['accommodation_name'] ?? '');
                if ($ln && !in_array($ln, $lodgeNames, true)) {
                    $lodgeNames[] = $ln;
                    $lodgeAlts[$ln] = array_filter($d['accommodation_alternatives'] ?? []);
                }
            }
          ?>
          <?php if ($lodgeNames): ?>
          <div class="mt-4">
            <h5 class="font-display mb-3" style="font-size:.9rem;letter-spacing:.05em">Where You'll Stay</h5>
            <ul class="includes-list">
              <?php foreach ($lodgeNames as $ln): ?>
                <li><?= htmlspecialchars($ln) ?><?php if (!empty($lodgeAlts[$ln])): ?>
                  <span style="display:block;font-size:.7rem;color:var(--gray-500);font-weight:400">or similar: <?= htmlspecialchars(implode(', ', $lodgeAlts[$ln])) ?></span>
                <?php endif; ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

          <p class="mt-3" style="font-size:.8rem;color:var(--gray-500);font-style:italic">
            <?= htmlspecialchars(\App\Helpers\TripExtensions::lineFor($pkg['slug'])) ?>
          </p>

          <?php if ($itinDays): ?>
          <div class="mt-4">
            <button type="button" onclick="toggleBreakdown(<?= $pkg['id'] ?>)"
                    class="btn-sz-outline" style="font-size:.75rem;display:inline-flex;align-items:center;gap:var(--space-2)">
              View Day-by-Day Itinerary
              <span id="bd-arrow-<?= $pkg['id'] ?>" style="display:inline-block;transition:transform .2s">&#9662;</span>
            </button>
            <div id="bd-panel-<?= $pkg['id'] ?>" style="display:none;margin-top:1.25rem">
              <?= \App\Helpers\ItineraryDayCards::render($itinDays, true) ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Booking Sidebar -->
        <div>
          <div class="booking-widget">
            <p class="eyebrow mb-2">Starting From</p>
            <p class="widget-price" id="price-<?= $pkg['id'] ?>">$<?= number_format($startPrice) ?> <small style="opacity:.6;font-weight:500;font-size:.5em"><?= approxAll($startPrice) ?></small></p>
            <p class="widget-price-note">per person · solo traveler</p>

            <!-- Accommodation Level -->

            <div class="mt-3 mb-4 p-3"
                style="
                border:1px solid var(--gray-2);
                background:#fafafa;
                border-radius:12px;">

              <div style="
                  font-size:.72rem;
                  text-transform:uppercase;
                  letter-spacing:.08em;
                  color:#666;
                  margin-bottom:.35rem;">

                  Accommodation Level

              </div>

              <div style="
                  font-size:1rem;
                  font-weight:600;
                  color:var(--black);">

                  <?= htmlspecialchars($pkg['accommodation_level'] ?? 'Mid-Range') ?>

              </div>

            </div>

            <p style="font-size:.78rem;color:var(--black);line-height:1.7;margin-bottom:var(--space-4);font-weight:500">Final price depends on your travel dates and group size — request a quote for an exact total.</p>

            <p style="font-size:.75rem;color:var(--black);line-height:1.6;margin-bottom:var(--space-5);font-weight:500">50% deposit confirms your booking. Balance due <?= BALANCE_DAYS ?> days before departure.</p>

            <a href="book.php?package_id=<?= $pkg['id'] ?>"
               class="btn-sz-dark w-100 justify-content-center mb-2 book-now-link"
               data-pkg-id="<?= $pkg['id'] ?>">
              Book This Safari
            </a>
            <a href="<?= BASE_PATH ?>/#customize" class="btn-sz-outline w-100 justify-content-center" style="font-size:.65rem">Customize This Package</a>

            <div class="mt-3 pt-3" style="border-top:1px solid var(--gray-2)">
              <p style="font-size:.72rem;color:var(--black);text-align:center;font-weight:500">Questions? <a href="https://wa.me/<?= WHATSAPP_NUMBER ?>" target="_blank" style="color:var(--black);font-weight:500">Chat on WhatsApp</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

  </div>
</div>

<!-- Sticky mobile "Book Now" bar — hidden on desktop via CSS -->
<?php $_wa = defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '255663430934'; ?>
<div class="mobile-book-bar">
  <a href="<?= BASE_PATH ?>/book.php" class="btn-sz-dark">Book Now</a>
  <a href="https://wa.me/<?= $_wa ?>?text=<?= urlencode('Hi Safari Zangu, I\'d like to enquire about a Tanzania safari.') ?>"
     target="_blank" rel="noopener" class="btn-sz-outline">WhatsApp</a>
</div>

<script>
function toggleBreakdown(id) {
  var panel = document.getElementById('bd-panel-'+id);
  var arrow = document.getElementById('bd-arrow-'+id);
  var open  = panel.style.display === 'block';
  panel.style.display = open ? 'none' : 'block';
  if(arrow) arrow.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
}
</script>
<!-- Footer -->