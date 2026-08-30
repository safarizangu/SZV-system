<?php
try {
    $db = \App\Models\Database::getInstance();
    $packages = $db->query(
        "SELECT * FROM packages WHERE is_active=1 ORDER BY sort_order ASC, id ASC"
    )->fetchAll();
    $pkgMap = [];
    foreach ($packages as &$p) {
        $p["gallery"]       = json_decode($p["gallery_images"] ?? "[]", true) ?: [];
        $p["includes_arr"]  = json_decode($p["includes"]       ?? "[]", true) ?: [];
        $p["excludes_arr"]  = json_decode($p["excludes"]       ?? "[]", true) ?: [];
        $p["itinerary_arr"] = json_decode($p["itinerary"]      ?? "[]", true) ?: [];
        $pkgMap[$p["slug"]] = $p;
    }
    unset($p);
} catch (\Exception $e) {
    $packages = []; $pkgMap = [];
    error_log("[view] " . $e->getMessage());
}
?>

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
        $itinerary   = decodeJsonField($pkg['itinerary']);
        $includes    = decodeJsonField($pkg['includes']);
        $excludes    = decodeJsonField($pkg['excludes']);
        $gallery     = []; // using local image files instead of DB

        // Default starting price shown on widget = mid-range, 2pax
        $startPrice = (float)($pkg['price_2pax'] ?? 0);

        // Placeholder images (real photos can be swapped in later —
        // using a neutral placeholder service so nothing 404s)
        $placeholderSeed = urlencode($pkg['slug']);
    ?>
    <div class="pkg-section" id="<?= htmlspecialchars($pkg['slug']) ?>">
      <span class="pkg-section-num"><?= $num ?></span>
      <div class="pkg-detail-grid">
        <div>
          <div class="pkg-section-header">
            <h2 class="display-2"><?= htmlspecialchars($pkg['name']) ?></h2>
            <p class="eyebrow mt-2"><?= (int)$pkg['duration_days'] ?> Days · <?= (int)$pkg['duration_days'] - 1 ?> Nights</p>
            <div class="divider-line"></div>
            <p style="color:var(--black);font-weight:500;line-height:1.8;max-width:580px"><?= htmlspecialchars($pkg['overview'] ?? $pkg['description'] ?? '') ?></p>
          </div>

                    <!-- ── IMAGE GALLERY — local files ── -->
          <?php
            $imgBase = BASE_PATH . '/assets/images/packages/' . $pkg['slug'];
          ?>
          <div class="pkg-gallery" >
            <img src="<?= $imgBase ?>/1.jpg" alt="<?= htmlspecialchars($pkg['name']) ?>"
                 loading="lazy" onerror="this.style.display='none'"
                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;">
            <img src="<?= $imgBase ?>/2.jpg" alt="<?= htmlspecialchars($pkg['name']) ?> 2"
                 loading="lazy" onerror="this.style.display='none'"
                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;">
            <img src="<?= $imgBase ?>/3.jpg" alt="<?= htmlspecialchars($pkg['name']) ?> 3"
                 loading="lazy" onerror="this.style.display='none'"
                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;">
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
        </div>

        <!-- Booking Sidebar -->
        <div>
          <div class="booking-widget">
            <p class="eyebrow mb-2">Starting From</p>
            <p class="widget-price" id="price-<?= $pkg['id'] ?>">$<?= number_format($startPrice) ?></p>
            <p class="widget-price-note">per person · 2 travelers</p>

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

            <h5 class="font-display mb-3" style="font-size:.9rem">Pricing Per Person</h5>
            <div class="pricing-table mb-4">
              <div class="pricing-row header"><span style="font-size:.65rem;letter-spacing:.1em">TRAVELERS</span><span style="font-size:.65rem;letter-spacing:.1em">PRICE / PERSON</span></div>
              <div class="pricing-row">
                <span style="font-size:.85rem">1 Person</span>
                <span class="price-cell" data-pax="1" style="font-family:var(--font-display);font-size:1.1rem">$<?= number_format((float)($pkg['price_1pax'] ?? 0)) ?></span>
              </div>
              <div class="pricing-row">
                <span style="font-size:.85rem">2 People</span>
                <span class="price-cell" data-pax="2" style="font-family:var(--font-display);font-size:1.1rem">$<?= number_format((float)($pkg['price_2pax'] ?? 0)) ?></span>
              </div>
              <div class="pricing-row">
                <span style="font-size:.85rem">3+ People</span>
                <span class="price-cell" data-pax="3" style="font-family:var(--font-display);font-size:1.1rem">$<?= number_format((float)($pkg['price_3pax'] ?? 0)) ?></span>
              </div>
            </div>

            <p style="font-size:.75rem;color:var(--black);line-height:1.6;margin-bottom:1.5rem;font-weight:500">50% deposit confirms your booking. Balance due <?= BALANCE_DAYS ?> days before departure.</p>

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