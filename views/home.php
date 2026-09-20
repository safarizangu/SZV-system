<?php
// Load packages directly — php/config.php no longer exists after restructure
try {
    $db = \App\Models\Database::getInstance();
    $packages = $db->query(
        "SELECT * FROM packages WHERE is_active = 1 ORDER BY sort_order ASC, id ASC"
    )->fetchAll();
    foreach ($packages as $p) {
        $p['gallery']      = json_decode($p['gallery_images'] ?? '[]', true) ?: [];
        $p['includes_arr'] = json_decode($p['includes']       ?? '[]', true) ?: [];
        $p['excludes_arr'] = json_decode($p['excludes']       ?? '[]', true) ?: [];
        $p['itinerary_arr']= json_decode($p['itinerary']      ?? '[]', true) ?: [];
        $pkgMap[$p['slug']] = $p;
    }
} catch (\Exception $e) {
    $packages = [];
    $pkgMap   = [];
    error_log('[home.php] DB error: ' . $e->getMessage());
}
?>
<!-- cSpell:disable -->

<!-- ── HERO ───────────────────────────────────────────────── -->
<section class="hero" aria-label="Hero">
  <div class="hero-bg" role="img" aria-label="Safari landscape in Tanzania"></div>
  <div class="hero-overlay" aria-hidden="true"></div>
  <div class="hero-content">
    <span class="hero-tag">Arusha, Tanzania · Est. 2024</span>
    <h1 class="hero-title">Private Tanzania<br><em>Safari Experiences</em></h1>
    <p class="hero-sub">Serengeti &nbsp;·&nbsp; Ngorongoro &nbsp;·&nbsp; Zanzibar</p>
    <p class="hero-hook">Locally owned. Personally guided. Every safari planned by the team you'll actually meet.</p>
    <div class="hero-actions">
      <a href="<?= BASE_PATH ?>/packages.php" class="btn-sz-primary">Explore Packages</a>
      <a href="<?= BASE_PATH ?>/#customize" class="btn-sz-ghost">Design Your Own</a>
    </div>
  </div>
  <div class="hero-scroll" aria-hidden="true">
    <div class="scroll-line"></div>
    Scroll
  </div>
</section>

<!-- ── STATS ──────────────────────────────────────────────── -->
<!-- <section class="stats-bar" aria-label="Key statistics">
  <div class="stats-grid container-sz">
    <div class="fade-in">
      <span class="stat-number">1+</span>
      <span class="stat-label">Years Operating</span>
    </div>
    <div class="fade-in fade-in-delay-1">
      <span class="stat-number">2+</span>
      <span class="stat-label">Guests Hosted</span>
    </div>
    <div class="fade-in fade-in-delay-2">
      <span class="stat-number">6</span>
      <span class="stat-label">Signature Packages</span>
    </div>
    <div class="fade-in fade-in-delay-3">
      <span class="stat-number">100%</span>
      <span class="stat-label">Private Vehicles</span>
    </div>
  </div>
</section> -->

<!-- Trust Strip -->
<!-- <div class="trust-strip fade-in">
  <div class="container-sz">
    <div class="trust-strip-inner">
      <div class="trust-item">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
        <span class="trust-label"><strong>TALA Licensed</strong> Tour Operator — No. 037129</span>
      </div>
      <div class="trust-divider"></div>
      <div class="trust-item">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span class="trust-label"><strong>100% Private</strong> Vehicles — Never Shared</span>
      </div>
      <div class="trust-divider"></div>
      <div class="trust-item">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        <span class="trust-label"><strong>24h</strong> Response Guarantee</span>
      </div>
      <div class="trust-divider"></div>
      <div class="trust-item">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span class="trust-label"><strong>Arusha-Based</strong> — Proudly Tanzanian</span>
      </div>
    </div>
  </div>
</div> -->

<!-- ── PACKAGES ───────────────────────────────────────────── -->
<section class="section-lg" id="packages">
  <div class="container-sz">
    <div class="section-header fade-in">
      <span class="section-label">Our Packages</span>
      <h2 class="display-2">Curated <em>Safari</em><br>Experiences</h2>
      <div class="divider-line"></div>
      <p style="max-width:520px;color:var(--black);font-size:var(--text-base);line-height:1.8;font-weight:500">
        Each journey is designed to put you at the heart of Tanzania's extraordinary landscapes — Big Five, Great Migration, and Indian Ocean included.
      </p>
    </div>

    <div class="packages-grid">
      <div class="pkg-card fade-in">
        <div class="pkg-number">01</div>
        





<div class="home-slider" data-package="classic-safari">
<?php $__g = $pkgMap['classic-safari']['gallery'] ?? []; if ($__g): foreach ($__g as $__gi => $__url): ?>
    <img loading="lazy" src="<?= htmlspecialchars(BASE_PATH . $__url) ?>" alt="Classic Safari<?= $__gi ? ' '.($__gi+1) : '' ?>"<?= $__gi===0 ? ' class="active"' : '' ?>>
<?php endforeach; else: ?>
    <img loading="lazy" src="<?= BASE_PATH ?>/assets/images/logo.jpeg" alt="Classic Safari" class="active">
<?php endif; ?>
    <button type="button" class="home-prev">‹</button>
    <button type="button" class="home-next">›</button>

</div>





<h3 class="pkg-title">Classic Safari</h3>

        <p class="pkg-duration">5 Days · 4 Nights</p>
        <p class="pkg-desc">Serengeti plains, Ngorongoro Crater, Tarangire. The quintessential Tanzania experience — and the Big Five await.</p>
        <p class="pkg-price"><?php $__p=$pkgMap['classic-safari'] ?? null; $__price=$__p?(float)$__p['price_1pax']:1650; echo 'From $'.number_format($__price,0).' <small style="opacity:.6;font-weight:500">'.gbpApprox($__price).'</small>'; ?></p>
        <p class="pkg-price-label">per person · solo traveler</p>
        <a href="packages.php#classic-safari" class="btn-sz-outline" style="font-size:.65rem;padding:.6rem 1.25rem;min-height:40px">View & Book</a>
      </div>

      <div class="pkg-card fade-in fade-in-delay-1">
        <div class="pkg-number">02</div>
        





<div class="home-slider" data-package="luxury-safari">
<?php $__g = $pkgMap['luxury-safari']['gallery'] ?? []; if ($__g): foreach ($__g as $__gi => $__url): ?>
    <img loading="lazy" src="<?= htmlspecialchars(BASE_PATH . $__url) ?>" alt="Luxury Safari<?= $__gi ? ' '.($__gi+1) : '' ?>"<?= $__gi===0 ? ' class="active"' : '' ?>>
<?php endforeach; else: ?>
    <img loading="lazy" src="<?= BASE_PATH ?>/assets/images/logo.jpeg" alt="Luxury Safari" class="active">
<?php endif; ?>
    <button type="button" class="home-prev">‹</button>
    <button type="button" class="home-next">›</button>

</div>





<h3 class="pkg-title">Luxury Safari</h3>

        <p class="pkg-duration">6 Days · 5 Nights</p>
        <p class="pkg-desc">Private game drives, exclusive lodges, and a sunrise balloon flight over the Serengeti that you will never forget.</p>
        <p class="pkg-price"><?php $__p=$pkgMap['luxury-safari'] ?? null; $__price=$__p?(float)$__p['price_1pax']:2900; echo 'From $'.number_format($__price,0).' <small style="opacity:.6;font-weight:500">'.gbpApprox($__price).'</small>'; ?></p>
        <p class="pkg-price-label">per person · solo traveler</p>
        <a href="packages.php#luxury-safari" class="btn-sz-outline" style="font-size:.65rem;padding:.6rem 1.25rem;min-height:40px">View & Book</a>
      </div>

      <div class="pkg-card fade-in fade-in-delay-2">
        <div class="pkg-number">03</div>
        





<div class="home-slider" data-package="comfort-safari">
<?php $__g = $pkgMap['comfort-safari']['gallery'] ?? []; if ($__g): foreach ($__g as $__gi => $__url): ?>
    <img loading="lazy" src="<?= htmlspecialchars(BASE_PATH . $__url) ?>" alt="Comfort Safari<?= $__gi ? ' '.($__gi+1) : '' ?>"<?= $__gi===0 ? ' class="active"' : '' ?>>
<?php endforeach; else: ?>
    <img loading="lazy" src="<?= BASE_PATH ?>/assets/images/logo.jpeg" alt="Comfort Safari" class="active">
<?php endif; ?>
    <button type="button" class="home-prev">‹</button>
    <button type="button" class="home-next">›</button>

</div>





<h3 class="pkg-title">Comfort Safari</h3>

        <p class="pkg-duration">5 Days · 4 Nights</p>
        <p class="pkg-desc">A refined mid-range circuit through Manyara, Serengeti and Ngorongoro — comfortable private lodges, experienced guide, zero compromise on wildlife time.</p>
        <p class="pkg-price"><?php $__p=$pkgMap['comfort-safari'] ?? null; $__price=$__p?(float)$__p['price_1pax']:750; echo 'From $'.number_format($__price,0).' <small style="opacity:.6;font-weight:500">'.gbpApprox($__price).'</small>'; ?></p>
        <p class="pkg-price-label">per person · solo traveler</p>
        <a href="packages.php#comfort-safari" class="btn-sz-outline" style="font-size:.65rem;padding:.6rem 1.25rem;min-height:40px">View & Book</a>
      </div>

      <div class="pkg-card fade-in">
        <div class="pkg-number">04</div>
        





<div class="home-slider" data-package="safari-zanzibar">
<?php $__g = $pkgMap['safari-zanzibar']['gallery'] ?? []; if ($__g): foreach ($__g as $__gi => $__url): ?>
    <img loading="lazy" src="<?= htmlspecialchars(BASE_PATH . $__url) ?>" alt="Safari + Zanzibar<?= $__gi ? ' '.($__gi+1) : '' ?>"<?= $__gi===0 ? ' class="active"' : '' ?>>
<?php endforeach; else: ?>
    <img loading="lazy" src="<?= BASE_PATH ?>/assets/images/logo.jpeg" alt="Safari + Zanzibar" class="active">
<?php endif; ?>
    <button type="button" class="home-prev">‹</button>
    <button type="button" class="home-next">›</button>

</div>





<h3 class="pkg-title">Safari + Zanzibar</h3>

        <p class="pkg-duration">7 Days · 6 Nights</p>
        <p class="pkg-desc">Three days of wildlife majesty on the mainland, then four days of Indian Ocean paradise. The definitive Tanzania journey.</p>
        <p class="pkg-price"><?php $__p=$pkgMap['safari-zanzibar'] ?? null; $__price=$__p?(float)$__p['price_1pax']:2200; echo 'From $'.number_format($__price,0).' <small style="opacity:.6;font-weight:500">'.gbpApprox($__price).'</small>'; ?></p>
        <p class="pkg-price-label">per person · solo traveler</p>
        <a href="packages.php#safari-zanzibar" class="btn-sz-outline" style="font-size:.65rem;padding:.6rem 1.25rem;min-height:40px">View & Book</a>
      </div>

      <div class="pkg-card fade-in fade-in-delay-1">
        <div class="pkg-number">05</div>
        





<div class="home-slider" data-package="cultural-safari">
<?php $__g = $pkgMap['cultural-safari']['gallery'] ?? []; if ($__g): foreach ($__g as $__gi => $__url): ?>
    <img loading="lazy" src="<?= htmlspecialchars(BASE_PATH . $__url) ?>" alt="Cultural Safari<?= $__gi ? ' '.($__gi+1) : '' ?>"<?= $__gi===0 ? ' class="active"' : '' ?>>
<?php endforeach; else: ?>
    <img loading="lazy" src="<?= BASE_PATH ?>/assets/images/logo.jpeg" alt="Cultural Safari" class="active">
<?php endif; ?>
    <button type="button" class="home-prev">‹</button>
    <button type="button" class="home-next">›</button>

</div>





<h3 class="pkg-title">Cultural Safari</h3>

        <p class="pkg-duration">4 Days · 3 Nights</p>
        <p class="pkg-desc">Maasai villages, coffee farms, local markets — an authentic connection to Tanzania's living cultures and ancient ways.</p>
        <p class="pkg-price"><?php $__p=$pkgMap['cultural-safari'] ?? null; $__price=$__p?(float)$__p['price_1pax']:1100; echo 'From $'.number_format($__price,0).' <small style="opacity:.6;font-weight:500">'.gbpApprox($__price).'</small>'; ?></p>
        <p class="pkg-price-label">per person · solo traveler</p>
        <a href="packages.php#cultural-safari" class="btn-sz-outline" style="font-size:.65rem;padding:.6rem 1.25rem;min-height:40px">View & Book</a>
      </div>

      <div class="pkg-card fade-in fade-in-delay-2">
        <div class="pkg-number">06</div>
        





<div class="home-slider" data-package="short-safari">
<?php $__g = $pkgMap['short-safari']['gallery'] ?? []; if ($__g): foreach ($__g as $__gi => $__url): ?>
    <img loading="lazy" src="<?= htmlspecialchars(BASE_PATH . $__url) ?>" alt="Short Safari<?= $__gi ? ' '.($__gi+1) : '' ?>"<?= $__gi===0 ? ' class="active"' : '' ?>>
<?php endforeach; else: ?>
    <img loading="lazy" src="<?= BASE_PATH ?>/assets/images/logo.jpeg" alt="Short Safari" class="active">
<?php endif; ?>
    <button type="button" class="home-prev">‹</button>
    <button type="button" class="home-next">›</button>

</div>





<h3 class="pkg-title">Short Safari</h3>

        <p class="pkg-duration">3 Days · 2 Nights</p>
        <p class="pkg-desc">A compact, high-impact introduction to Tanzania's wildlife. Perfect for stopovers, first-timers, and tight itineraries.</p>
        <p class="pkg-price"><?php $__p=$pkgMap['short-safari'] ?? null; $__price=$__p?(float)$__p['price_1pax']:850; echo 'From $'.number_format($__price,0).' <small style="opacity:.6;font-weight:500">'.gbpApprox($__price).'</small>'; ?></p>
        <p class="pkg-price-label">per person · solo traveler</p>
        <a href="packages.php#short-safari" class="btn-sz-outline" style="font-size:.65rem;padding:.6rem 1.25rem;min-height:40px">View & Book</a>
      </div>
    </div>

    <div class="text-center mt-5 fade-in">
      <a href="<?= BASE_PATH ?>/packages.php" class="btn-sz-dark">View All Packages & Full Itineraries</a>
    </div>
  </div>
</section>

<!-- ── WHY SAFARI ZANGU ───────────────────────────────────── -->
<section class="section bg-off-white border-bottom-thin">
  <div class="container-sz">
    <div class="section-header fade-in">
      <span class="section-label">Why Choose Us</span>
      <h2 class="display-2">What Sets<br><em>Us Apart</em></h2>
      <div class="divider-line"></div>
    </div>
    <div class="why-grid">
      <div class="why-item fade-in">
        <div class="why-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M8 1.5C5 1.5 2.5 4 2.5 7s4 7.5 5.5 7.5S13.5 10 13.5 7 11 1.5 8 1.5z"/>
            <circle cx="8" cy="7" r="1.5" fill="currentColor" stroke="none"/>
          </svg>
        </div>
        <h4 class="why-title">Locally Rooted</h4>
        <p class="why-text">Born and based in Arusha, we know Tanzania intimately. Every route, every season, every hidden viewpoint — this is our home.</p>
      </div>
      <div class="why-item fade-in fade-in-delay-1">
        <div class="why-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="2" y="6" width="12" height="7" rx="1"/>
            <path d="M5 6V4.5a3 3 0 016 0V6"/>
          </svg>
        </div>
        <h4 class="why-title">Private Vehicles</h4>
        <p class="why-text">All our standard packages use private 4WD vehicles. Your guide, your schedule — with the flexibility to stay wherever wildlife demands it.</p>
      </div>
      <div class="why-item fade-in fade-in-delay-2">
        <div class="why-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M8 2l1.5 3.5L13 6l-2.5 2.5.6 3.5L8 10.5 4.9 12l.6-3.5L3 6l3.5-.5L8 2z"/>
          </svg>
        </div>
        <h4 class="why-title">Expert Naturalists</h4>
        <p class="why-text">Our guides are certified, experienced, and deeply passionate. They don't just spot animals — they read the wild for you.</p>
      </div>
      <div class="why-item fade-in fade-in-delay-3">
        <div class="why-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M3 8.5l3 3 7-7"/>
          </svg>
        </div>
        <h4 class="why-title">Full Transparency</h4>
        <p class="why-text">Clear pricing, nothing hidden. Everything in writing. You'll know exactly what is included before you pay a single dollar.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── CUSTOMIZE YOUR SAFARI ──────────────────────────────── -->
<section class="section custom-safari" id="customize">
  <div class="container-sz">
    <div class="responsive-two-col">

      <!-- ── Left: pitch ───────────────────────────────── -->
      <div class="fade-in">
        <span class="section-label">Bespoke Travel</span>
        <h2 class="display-2">Design<br><em>Your Safari</em></h2>
        <div class="divider-line"></div>
        <p style="color:var(--black);font-size:var(--text-base);line-height:1.85;margin-bottom:var(--space-5);font-weight:500">
          No two travelers are the same. Tell us your vision — budget, dates,
          destinations, travel style — and we'll craft a private safari
          built exclusively for you.
        </p>
        <p style="color:var(--black);font-size:var(--text-sm);line-height:1.8;font-weight:500">
          We respond within 24 hours with a fully tailored proposal: day-by-day
          itinerary, accommodation options, and transparent pricing.
        </p>
        <div style="margin-top:var(--space-6);padding-top:var(--space-6);border-top:1px solid var(--gray-2)">
          <p style="font-size:var(--text-xs);font-weight:700;letter-spacing:.18em;text-transform:uppercase;margin-bottom:.85rem">Included in every proposal</p>
          <ul style="list-style:none;display:flex;flex-direction:column;gap:.55rem">
            <?php foreach(['Full day-by-day itinerary','Accommodation options at every tier','Fully transparent pricing — no hidden fees','Unlimited free revisions'] as $b): ?>
            <li style="font-size:var(--text-sm);color:var(--black);display:flex;gap:.65rem;align-items:flex-start;font-weight:500">
              <span style="font-weight:700;flex-shrink:0">✓</span><?= $b ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div style="margin-top:var(--space-6);padding:.85rem var(--space-4);background:#f7f7f5;border-left:3px solid var(--black);font-size:.78rem;font-weight:500;line-height:1.8">
          <strong style="display:block;font-size:.62rem;letter-spacing:.15em;text-transform:uppercase;margin-bottom:.35rem">Average response time</strong>
          WhatsApp: under 1 hour &nbsp;·&nbsp; Email: within 24 hours
        </div>
      </div>

      <!-- ── Right: form ────────────────────────────────── -->
      <div class="fade-in fade-in-delay-1">
        <form id="custom-safari-form" data-validate-form novalidate>
          <input type="hidden" name="_csrf" value="<?= generateCsrfToken() ?>">
          <div class="hp-field" aria-hidden="true">
            <label for="cs-website">Website</label>
            <input type="text" id="cs-website" name="website" tabindex="-1" autocomplete="off">
          </div>

          <!-- 01 About You -->
          <div class="sz-form-section">
            <p class="sz-form-section-title">01 — About You</p>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="cs-name">Full Name <span class="sz-required">*</span></label>
                <input type="text" id="cs-name" name="full_name" class="form-control-sz"
                       placeholder="Your full name"
                       data-validate="required name" data-label="Full name"
                       autocomplete="name" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="cs-email">Email <span class="sz-required">*</span></label>
                <input type="email" id="cs-email" name="email" class="form-control-sz"
                       placeholder="your@email.com"
                       data-validate="required email" data-label="Email"
                       autocomplete="email" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="cs-phone">Phone / WhatsApp <span class="sz-hint">Optional</span></label>
                <input type="tel" id="cs-phone" name="phone" class="form-control-sz"
                       placeholder="+255 700 000 000"
                       data-validate="phone" data-label="Phone"
                       autocomplete="tel">
              </div>
              <div class="form-group">
                <label class="form-label" for="cs-country">Country</label>
                <div class="autocomplete-wrap">
                  <input type="text" id="cs-country" name="country" class="form-control-sz"
                         placeholder="Start typing — e.g. United Kingdom"
                         autocomplete="off" role="combobox" aria-expanded="false"
                         aria-autocomplete="list" aria-controls="cs-country-suggestions">
                  <div id="cs-country-suggestions" class="autocomplete-list" role="listbox" hidden></div>
                </div>
              </div>
            </div>
          </div>

          <!-- 02 Your Group -->
          <div class="sz-form-section">
            <p class="sz-form-section-title">02 — Your Group</p>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="cs-adults">Adults <span class="sz-required">*</span> <span class="sz-hint">Age 12+</span></label>
                <input type="number" id="cs-adults" name="adults" class="form-control-sz"
                       min="1" max="30" value="2"
                       data-validate="required min:1 max:30 integer" data-label="Adults" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="cs-children">Children <span class="sz-hint">Age 3–11</span></label>
                <input type="number" id="cs-children" name="children" class="form-control-sz"
                       min="0" max="20" value="0"
                       data-validate="min:0 max:20 integer" data-label="Children">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="cs-rooms">Room Configuration</label>
              <select id="cs-rooms" name="room_layout" class="form-control-sz">
                <option value="">— Select —</option>
                <option value="double">Double rooms (couples sharing)</option>
                <option value="twin">Twin rooms (separate beds)</option>
                <option value="mix">Mix of double &amp; twin</option>
                <option value="family">Family rooms</option>
                <option value="single">Single occupancy</option>
              </select>
            </div>
          </div>

          <!-- 03 Dates -->
          <div class="sz-form-section">
            <p class="sz-form-section-title">03 — Dates &amp; Duration</p>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="cs-date">Preferred Start Date</label>
                <input type="date" id="cs-date" name="start_date" class="form-control-sz"
                       min="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                       data-validate="future" data-label="Start date">
              </div>
              <div class="form-group">
                <label class="form-label" for="cs-dur">Duration (days) <span class="sz-required">*</span></label>
                <input type="number" id="cs-dur" name="duration_days" class="form-control-sz"
                       min="1" max="60" value="7" placeholder="e.g. 7"
                       data-validate="required min:1 max:60 integer" data-label="Duration" required>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="cs-flex">Date Flexibility</label>
              <select id="cs-flex" name="flexible_dates" class="form-control-sz">
                <option value="Yes">Yes — flexible by ±2 weeks</option>
                <option value="Somewhat">Somewhat — flexible by a few days</option>
                <option value="No">No — specific dates only</option>
              </select>
            </div>
          </div>

                    <!-- 04 Destinations -->
          <div class="sz-form-section">
            <p class="sz-form-section-title">04 — Destinations <span class="sz-required">*</span></p>

            <!-- Trigger button -->
            <button type="button" id="cs-dest-trigger" onclick="openDestModal()"
                    style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.1rem;border:1.5px solid var(--gray-2);background:#fff;cursor:pointer;font-family:inherit;font-size:.85rem;transition:border-color .15s">
              <span style="display:flex;align-items:center;gap:.6rem;color:#9ca3af" id="cs-trigger-label">
                <svg style="width:18px;height:18px;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35" stroke-linecap="round"/>
                </svg>
                <span id="cs-trigger-text">Choose destinations…</span>
              </span>
              <span id="cs-trigger-badge" style="display:none;background:#0a0a0a;color:#fff;font-size:.65rem;font-weight:700;padding:.2rem .6rem;letter-spacing:.08em;border-radius:99px">0 selected</span>
              <svg style="width:16px;height:16px;color:#9ca3af;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 9l6 6 6-6"/></svg>
            </button>

            <!-- Selected chips preview -->
            <div id="cs-chips" style="display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.6rem"></div>

            <!-- Error -->
            <div id="cs-dest-error" style="display:none;color:#dc2626;font-size:.7rem;font-weight:600;margin-top:.4rem">
              ⚠ Please select at least one destination
            </div>

            <!-- Hidden inputs populated by JS -->
            <div id="cs-dest-inputs"></div>
          </div>

<!-- 05 Experiences -->
          <div class="sz-form-section">
            <p class="sz-form-section-title">05 — Experiences Wanted <span class="sz-hint" style="text-transform:none;letter-spacing:0;font-size:.68rem;font-weight:400">Optional</span></p>
            <div class="cs-exp-grid">
              <?php
              $exps = [
                ['Big Five Game Drives','🎯'],['Great Migration','🐃'],
                ['Hot Air Balloon','🎈'],['Walking Safari','👣'],
                ['Night Safari','🌙'],['Cultural Village','🏡'],
                ['Bird Watching','🔭'],['Photography Safari','📷'],
                ['Boat Safari','⛵'],['Honeymoon Special','💍'],
                ['Mountain Trekking','🥾'],['Snorkeling / Diving','🤿'],
              ];
              foreach ($exps as [$exp, $ico]): ?>
              <label class="cs-exp-tag">
                <input type="checkbox" name="experiences[]" value="<?= $exp ?>" style="display:none">
                <?= $exp ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- 06 Style & Budget -->
          <div class="sz-form-section">
            <p class="sz-form-section-title">06 — Style &amp; Budget</p>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="cs-acc">Accommodation Style</label>
                <select id="cs-acc" name="accommodation_standard" class="form-control-sz">
                  <option value="">— Select —</option>
                  <option value="budget">Budget-Friendly (shared facilities)</option>
                  <option value="midrange">Mid-Range (en-suite, comfortable)</option>
                  <option value="highend">High-End (private lodge / tented camp)</option>
                  <option value="ultra_luxury">Ultra Luxury (exclusive bush camp)</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="cs-meal">Meal Plan</label>
                <select id="cs-meal" name="meal_plan" class="form-control-sz">
                  <option value="fb">Full Board (breakfast, lunch &amp; dinner)</option>
                  <option value="hb">Half Board (breakfast &amp; dinner)</option>
                  <option value="bb">Bed &amp; Breakfast</option>
                  <option value="ai">All Inclusive</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="cs-lang">Guide Language</label>
                <select id="cs-lang" name="guide_language" class="form-control-sz">
                  <option value="english">English</option>
                  <option value="french">French</option>
                  <option value="german">German</option>
                  <option value="italian">Italian</option>
                  <option value="spanish">Spanish</option>
                  <option value="chinese">Mandarin Chinese</option>
                  <option value="swahili">Swahili</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="cs-budget">Total Budget (USD)</label>
                <select id="cs-budget" name="total_budget" class="form-control-sz">
                  <option value="">— Prefer not to say —</option>
                  <option value="Under $1,500/pp">Under $1,500 (<?= gbpApprox(1500) ?>) per person</option>
                  <option value="$1,500-$2,500/pp">$1,500 – $2,500 (<?= gbpApprox(1500) ?>–<?= gbpApprox(2500) ?>) per person</option>
                  <option value="$2,500-$4,000/pp">$2,500 – $4,000 (<?= gbpApprox(2500) ?>–<?= gbpApprox(4000) ?>) per person</option>
                  <option value="$4,000-$7,000/pp">$4,000 – $7,000 (<?= gbpApprox(4000) ?>–<?= gbpApprox(7000) ?>) per person</option>
                  <option value="$7,000+/pp">$7,000+ (<?= gbpApprox(7000) ?>+) per person (ultra luxury)</option>
                  <option value="Flexible">Flexible — show me options</option>
                </select>
              </div>
            </div>
          </div>

          <!-- 07 Message -->
          <div class="sz-form-section">
            <p class="sz-form-section-title">07 — Anything Else?</p>
            <div class="form-group">
              <label class="form-label" for="cs-msg-text">
                Additional Notes <span class="sz-hint">Dietary needs, celebrations, mobility…</span>
              </label>
              <textarea id="cs-msg-text" name="message" class="form-control-sz" rows="3"
                        placeholder="Tell us anything else that would help us design your perfect safari…"
                        data-maxchars="800"></textarea>
            </div>
          </div>

          <!-- Submit -->
          <button type="submit" id="cs-submit" class="btn-sz-dark"
                  style="width:100%;justify-content:center;padding:1.1rem;font-size:.78rem;letter-spacing:.1em">
            Send My Safari Request &rarr;
          </button>
          <p style="font-size:.7rem;color:#9ca3af;text-align:center;margin-top:.6rem;line-height:1.6">
            No payment required &nbsp;·&nbsp; Free personalised proposal within 24 hours
          </p>
          <div class="form-message mt-3" id="cs-form-msg"></div>
        </form>
      </div>

    </div>
  </div>
</section>

<!-- ══ DESTINATION PICKER MODAL ════════════════════════════ -->
<div id="dest-modal-overlay" role="dialog" aria-modal="true" aria-label="Choose Destinations"
     class="dest-modal-hidden" style="position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:flex-end;justify-content:center"
     onclick="if(event.target===this)closeDestModal()">

  <div id="dest-modal-box"
       style="background:#fff;width:100%;max-width:720px;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 -4px 40px rgba(0,0,0,.2);transform:translateY(100%);transition:transform .32s cubic-bezier(.4,0,.2,1);will-change:transform">

    <!-- Modal header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.5rem;border-bottom:1px solid #e5e7eb;flex-shrink:0">
      <div>
        <h3 style="font-family:var(--font-display,Georgia,serif);font-size:1.05rem;font-weight:500">Choose Your Destinations</h3>
        <p style="font-size:.72rem;color:#9ca3af;margin-top:.1rem">Select all parks &amp; islands you'd like to visit</p>
      </div>
      <button onclick="closeDestModal()" aria-label="Close"
              style="width:34px;height:34px;border:1.5px solid #e5e7eb;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#6b7280;flex-shrink:0">
        ✕
      </button>
    </div>

    <!-- Search bar -->
    <div style="padding:.85rem 1.5rem;border-bottom:1px solid #f3f4f6;flex-shrink:0">
      <div style="position:relative">
        <svg style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#9ca3af" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35" stroke-linecap="round"/>
        </svg>
        <input type="text" id="dest-search" placeholder="Search destinations…"
               oninput="filterDests(this.value)"
               style="width:100%;padding:.6rem .75rem .6rem 2.25rem;border:1.5px solid #e5e7eb;font-family:inherit;font-size:.85rem;outline:none">
      </div>
    </div>

    <!-- Destination list (scrollable) -->
    <div id="dest-modal-list" style="overflow-y:auto;flex:1;padding:var(--space-4) var(--space-5)">
      <!-- Populated from PHP/DB below -->
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

      <?php foreach ($__grouped as $__country => $__countryParks): ?>
      <div class="dest-group" style="margin-bottom:var(--space-5)">
        <p style="font-size:.58rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;margin-bottom:.65rem;padding-bottom:.4rem;border-bottom:1px solid #f3f4f6">
          <?= htmlspecialchars($__country) ?> &nbsp;·&nbsp; <?= count($__countryParks) ?> destination<?= count($__countryParks)!==1?'s':'' ?>
        </p>
        <div class="dest-card-grid">
          <?php foreach ($__countryParks as $__park):
            $__key  = $__park['park_key'];
            $__icon = $__icons[$__key] ?? $__icons['__default__'];
            $__sub  = $__subs[$__key]  ?? htmlspecialchars($__park['notes'] ?? 'Tanzania Wildlife');
          ?>
          <label class="dest-modal-card" data-name="<?= htmlspecialchars($__park['name']) ?>"
                 data-key="<?= htmlspecialchars($__key) ?>">
            <input type="checkbox" value="<?= htmlspecialchars($__park['name']) ?>" style="display:none">
            <div class="dest-modal-icon">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <?= $__icon ?>
              </svg>
            </div>
            <div style="flex:1;min-width:0">
              <span class="dest-modal-name"><?= htmlspecialchars($__park['name']) ?></span>
              <span class="dest-modal-sub"><?= $__sub ?></span>
            </div>
            <div class="dest-modal-tick">
              <svg viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($__parks)): ?>
      <!-- Static fallback if DB empty -->
      <div class="dest-card-grid">
        <?php
        $__static = [
          ['serengeti','Serengeti National Park','Great Migration · Big Five'],
          ['ngorongoro','Ngorongoro Conservation Area','Big Five · UNESCO Heritage'],
          ['tarangire','Tarangire National Park','Largest Elephant Herds'],
          ['manyara','Lake Manyara National Park','Tree-Climbing Lions'],
          ['arusha','Arusha National Park','Day Trips'],
          ['kilimanjaro','Kilimanjaro',"Africa's Roof · 5,895m"],
          ['zanzibar','Zanzibar Island','Beaches · Culture'],
          ['nyerere','Nyerere National Park','Boat Safaris · Wild Dogs'],
        ];
        foreach ($__static as [$__k,$__n,$__s]):
          $__icon = $__icons[$__k] ?? $__icons['__default__'];
        ?>
        <label class="dest-modal-card" data-name="<?= htmlspecialchars($__n) ?>" data-key="<?= $__k ?>">
          <input type="checkbox" value="<?= htmlspecialchars($__n) ?>" style="display:none">
          <div class="dest-modal-icon">
            <svg viewBox="0 0 24 24" fill="none"><?= $__icon ?></svg>
          </div>
          <div style="flex:1;min-width:0">
            <span class="dest-modal-name"><?= htmlspecialchars($__n) ?></span>
            <span class="dest-modal-sub"><?= $__s ?></span>
          </div>
          <div class="dest-modal-tick">
            <svg viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- No results -->
      <p id="dest-no-results" style="display:none;text-align:center;color:#9ca3af;font-size:.85rem;padding:var(--space-6)">
        No destinations match your search.
      </p>
    </div>

    <!-- Modal footer -->
    <div style="padding:var(--space-4) var(--space-5);border-top:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:#fafaf9">
      <p style="font-size:.78rem;font-weight:600;color:#6b7280">
        <span id="dest-selected-count">0</span> destination(s) selected
      </p>
      <div style="display:flex;gap:var(--space-2)">
        <button type="button" onclick="clearAllDests()"
                style="padding:.55rem 1.1rem;border:1.5px solid #e5e7eb;background:#fff;font-family:inherit;font-size:.72rem;font-weight:600;cursor:pointer;color:#6b7280">
          Clear All
        </button>
        <button type="button" onclick="confirmDests()"
                style="padding:.55rem 1.4rem;border:1.5px solid #0a0a0a;background:#0a0a0a;color:#fff;font-family:inherit;font-size:.72rem;font-weight:700;letter-spacing:.06em;cursor:pointer">
          Confirm &rarr;
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══ DESTINATION MODAL STYLES ════════════════════════════ -->
<style>
/* Modal overlay open state */
/* Modal hidden/visible states — class-based to beat inline specificity */
.dest-modal-hidden {
  display: none !important;
}
.dest-modal-visible {
  display: flex !important;
  align-items: flex-end !important;
  justify-content: center !important;
}
#dest-modal-overlay.dest-modal-visible #dest-modal-box {
  transform: translateY(0) !important;
}

/* Card grid inside modal */
.dest-card-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2);
}
@media (min-width: 540px) { .dest-card-grid { grid-template-columns: 1fr 1fr 1fr; } }

/* Each card in modal */
.dest-modal-card {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) .85rem;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  cursor: pointer;
  transition: border-color .12s, background .12s;
  position: relative;
  user-select: none;
}
.dest-modal-card:hover { border-color: #9ca3af; background: #f9f9f8; }
.dest-modal-card.selected {
  border-color: #0a0a0a;
  background: #0a0a0a;
  color: #fff;
}
.dest-modal-card.hidden { display: none; }

/* Icon */
.dest-modal-icon {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  color: #0a0a0a;
  transition: color .12s;
}
.dest-modal-card.selected .dest-modal-icon { color: #fff; }
.dest-modal-icon svg { width: 100%; height: 100%; }

/* Text */
.dest-modal-name {
  display: block;
  font-size: .78rem;
  font-weight: 700;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.dest-modal-sub {
  display: block;
  font-size: .62rem;
  color: #9ca3af;
  font-weight: 500;
  line-height: 1.3;
  margin-top: .1rem;
  transition: color .12s;
}
.dest-modal-card.selected .dest-modal-sub { color: rgba(255,255,255,.55); }

/* Tick (top-right) */
.dest-modal-tick {
  position: absolute;
  top: .4rem;
  right: .4rem;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: rgba(255,255,255,.15);
  border: 1.5px solid rgba(255,255,255,.3);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity .12s;
}
.dest-modal-tick svg { width: 10px; height: 10px; stroke: #0a0a0a; }
.dest-modal-card.selected .dest-modal-tick {
  opacity: 1;
  background: #fff;
  border-color: #fff;
}

/* Trigger button hover */
#cs-dest-trigger:hover { border-color: #0a0a0a; }
#cs-dest-trigger:focus { outline: 2px solid #0a0a0a; outline-offset: 2px; }

/* Selected chips */
.cs-chip {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: var(--space-1) .6rem;
  background: #0a0a0a;
  color: #fff;
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .04em;
}
.cs-chip-remove {
  cursor: pointer;
  opacity: .6;
  font-size: .75rem;
  line-height: 1;
  background: none;
  border: none;
  color: inherit;
  padding: 0;
}
.cs-chip-remove:hover { opacity: 1; }

/* Dest search focus */
#dest-search:focus { border-color: #0a0a0a; }

/* Responsive: stack modal full screen on small devices */
@media (max-width: 480px) {
  #dest-modal-box { max-height: 95vh; }
  .dest-card-grid { grid-template-columns: 1fr 1fr; }
  .dest-modal-name { font-size: .72rem; }
}
</style>

<!-- ══ DESTINATION MODAL JS ════════════════════════════════ -->
<script>
(function () {

  // Move modal to body immediately to escape any overflow:hidden parent
  var overlay = document.getElementById('dest-modal-overlay');
  if (overlay && overlay.parentElement !== document.body) {
    document.body.appendChild(overlay);
  }

  var box         = document.getElementById('dest-modal-box');
  var trigger     = document.getElementById('cs-dest-trigger');
  var trigText    = document.getElementById('cs-trigger-text');
  var trigBadge   = document.getElementById('cs-trigger-badge');
  var chipsEl     = document.getElementById('cs-chips');
  var inputsEl    = document.getElementById('cs-dest-inputs');
  var countEl     = document.getElementById('dest-selected-count');
  var errorEl     = document.getElementById('cs-dest-error');
  var noResults   = document.getElementById('dest-no-results');

  var selected = {}; // { name: true }

  // Bridge for the form-submit handler in the other <script> block below
  // (separate scope — this modal owns the real selection state; the old
  // pre-modal .cs-dest-card/.cs-dest-grid markup these used to read from
  // no longer exists).
  window._csGetSelectedCount = function () { return Object.keys(selected).length; };
  window._csResetSelection = function () {
    selected = {};
    document.querySelectorAll('.dest-modal-card').forEach(function (c) { c.classList.remove('selected'); });
    renderHiddenInputs();
    updateTrigger();
    updateCount();
  };

  // ── Open ─────────────────────────────────────────────────
  window.openDestModal = function () {
    if (!overlay) return;
    overlay.classList.remove('dest-modal-hidden');
    overlay.classList.add('dest-modal-visible');
    document.body.style.overflow = 'hidden';
    // Restore previously selected states on cards
    document.querySelectorAll('.dest-modal-card').forEach(function (card) {
      card.classList.toggle('selected', !!selected[card.dataset.name]);
    });
    updateCount();
    var search = document.getElementById('dest-search');
    if (search) { search.value = ''; filterDests(''); setTimeout(function () { search.focus(); }, 80); }
  };

  // ── Close ─────────────────────────────────────────────────
  window.closeDestModal = function () {
    if (!overlay) return;
    overlay.classList.add('dest-modal-hidden');
    overlay.classList.remove('dest-modal-visible');
    document.body.style.overflow = '';
  };

  // Click backdrop to close
  if (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeDestModal();
    });
  }

  // Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDestModal();
  });

  // ── Card toggle ───────────────────────────────────────────
  document.querySelectorAll('.dest-modal-card').forEach(function (card) {
    card.addEventListener('click', function (e) {
      e.preventDefault();
      var name = card.dataset.name;
      selected[name] = !selected[name];
      if (!selected[name]) delete selected[name];
      card.classList.toggle('selected', !!selected[name]);
      updateCount();
    });
  });

  // ── Confirm ───────────────────────────────────────────────
  window.confirmDests = function () {
    renderChips();
    renderHiddenInputs();
    updateTrigger();
    if (errorEl) errorEl.style.display = 'none';
    closeDestModal();
  };

  // ── Clear all ─────────────────────────────────────────────
  window.clearAllDests = function () {
    selected = {};
    document.querySelectorAll('.dest-modal-card').forEach(function (c) {
      c.classList.remove('selected');
    });
    updateCount();
  };

  // ── Search ────────────────────────────────────────────────
  window.filterDests = function (q) {
    var term    = (q || '').toLowerCase().trim();
    var visible = 0;
    document.querySelectorAll('.dest-modal-card').forEach(function (card) {
      var match = !term || (card.dataset.name || '').toLowerCase().includes(term);
      card.classList.toggle('hidden', !match);
      if (match) visible++;
    });
    document.querySelectorAll('.dest-group').forEach(function (g) {
      var any = Array.from(g.querySelectorAll('.dest-modal-card')).some(function (c) {
        return !c.classList.contains('hidden');
      });
      g.style.display = any ? '' : 'none';
    });
    if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
  };

  // ── Remove single chip ────────────────────────────────────
  function removeChip(name) {
    delete selected[name];
    document.querySelectorAll('.dest-modal-card').forEach(function (c) {
      if (c.dataset.name === name) c.classList.remove('selected');
    });
    renderChips();
    renderHiddenInputs();
    updateTrigger();
    updateCount();
  }

  // ── Render chips ──────────────────────────────────────────
  function renderChips() {
    if (!chipsEl) return;
    chipsEl.innerHTML = '';
    Object.keys(selected).forEach(function (name) {
      var short = name.replace(' National Park','').replace(' Conservation Area','').replace(' Island','');
      var chip  = document.createElement('span');
      chip.className = 'cs-chip';
      chip.innerHTML = '<span>' + short + '</span>'
        + '<button type="button" class="cs-chip-remove" aria-label="Remove">×</button>';
      chip.querySelector('.cs-chip-remove').addEventListener('click', function () {
        removeChip(name);
      });
      chipsEl.appendChild(chip);
    });
  }

  // ── Render hidden inputs ──────────────────────────────────
  function renderHiddenInputs() {
    if (!inputsEl) return;
    inputsEl.innerHTML = '';
    Object.keys(selected).forEach(function (name) {
      var inp = document.createElement('input');
      inp.type  = 'hidden';
      inp.name  = 'destinations[]';
      inp.value = name;
      inputsEl.appendChild(inp);
    });
  }

  // ── Update trigger button ─────────────────────────────────
  function updateTrigger() {
    var n = Object.keys(selected).length;
    if (!trigText) return;
    if (n === 0) {
      trigText.style.color = '#9ca3af';
      trigText.textContent = 'Choose destinations…';
      if (trigBadge) trigBadge.style.display = 'none';
      if (trigger)   trigger.style.borderColor = '';
    } else {
      trigText.style.color = '#0a0a0a';
      trigText.textContent = n === 1 ? '1 destination selected' : n + ' destinations selected';
      if (trigBadge) {
        trigBadge.textContent  = n + ' selected';
        trigBadge.style.display = 'inline-block';
      }
      if (trigger) trigger.style.borderColor = '#0a0a0a';
    }
  }

  // ── Update footer count ───────────────────────────────────
  function updateCount() {
    var n = Object.keys(selected).length;
    if (countEl) countEl.textContent = n;
  }

  // ── Form submit validation ────────────────────────────────
  var csForm = document.getElementById('custom-safari-form');
  if (csForm) {
    csForm.addEventListener('submit', function (e) {
      renderHiddenInputs();
      if (Object.keys(selected).length === 0) {
        e.preventDefault();
        if (errorEl) errorEl.style.display = 'block';
        if (trigger)  trigger.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }, true);
  }

})();
</script>

<!-- ── Custom Safari CSS ──────────────────────────────────── -->
<style>
/* .cs-dest-grid/.cs-dest-card/etc. (an earlier pre-modal destination
   picker UI) removed — the current destination picker is the
   .dest-modal-card grid inside #dest-modal-overlay, styled further
   down this file. */

/* Experience tags */
.cs-exp-grid {
  display: flex;
  flex-wrap: wrap;
  gap: .4rem;
}
.cs-exp-tag {
  display: inline-flex;
  align-items: center;
  padding: .38rem .8rem;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  font-size: .72rem;
  font-weight: 600;
  cursor: pointer;
  transition: border-color .15s, background .15s, color .15s;
  user-select: none;
  letter-spacing: .02em;
}
.cs-exp-tag:hover { border-color: #9ca3af; background: #f9f9f8; }
.cs-exp-tag.selected {
  border-color: #0a0a0a;
  background: #0a0a0a;
  color: #fff;
}
</style>

<!-- ── Custom Safari JS ───────────────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function () {

  /* Destination selection/validation lives in the destination-picker
     modal's own <script> block above — bridged via window._csGetSelectedCount
     / window._csResetSelection. The old .cs-dest-card/.cs-dest-grid markup
     this used to read directly no longer exists. */

  /* ── Experience tags ───────────────────────────────────── */
  document.querySelectorAll('.cs-exp-tag').forEach(function (tag) {
    tag.addEventListener('click', function (e) {
      e.preventDefault();
      var cb = tag.querySelector('input[type=checkbox]');
      cb.checked = !cb.checked;
      tag.classList.toggle('selected', cb.checked);
    });
  });

  /* ── Form submit ───────────────────────────────────────── */
  var form    = document.getElementById('custom-safari-form');
  var btn     = document.getElementById('cs-submit');
  var msgDiv  = document.getElementById('cs-form-msg');

  if (!form) return;

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    window._csSubmitted = true;

    /* Client-side field validation */
    if (window.SZValidator && !SZValidator.validateForm(form)) return;

    /* Destination required — checked via the destination-picker modal's
       own selection state (the capture-phase listener above already
       blocks this if empty, but the form can still reach here via a
       non-click submit, e.g. pressing Enter in a text field) */
    if (window._csGetSelectedCount && window._csGetSelectedCount() === 0) {
      var trigEl = document.getElementById('cs-dest-trigger');
      if (trigEl) trigEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.classList.add('is-loading');
    btn.innerHTML = '';

    try {
      var resp = await fetch('api/custom_safari.php', {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
      });
      var raw = await resp.text();
      var data;
      try { data = JSON.parse(raw); }
      catch (pe) {
        console.error('[SZV Custom] Bad JSON:', raw.substring(0, 200));
        showMsg('Server error. Please WhatsApp us at +255 663 430 934.', 'error');
        reset(); return;
      }

      if (data.success) {
        showMsg(data.message, 'success');
        form.reset();
        document.querySelectorAll('.cs-exp-tag').forEach(function(el){ el.classList.remove('selected'); });
        document.querySelectorAll('.cs-exp-tag input').forEach(function(cb){ cb.checked=false; });
        if (window._csResetSelection) window._csResetSelection();
        window._csSubmitted = false;
        if (typeof trackEvent === 'function') trackEvent('custom_safari_submission');
        btn.disabled = false;
        btn.classList.remove('is-loading');
        btn.innerHTML = '✓ Request Sent';
        btn.style.cssText = 'width:100%;justify-content:center;padding:1.1rem;font-size:.78rem;letter-spacing:.1em;background:#16a34a;border-color:#16a34a;color:#fff';
      } else {
        showMsg(data.message || 'Something went wrong.', 'error');
        reset();
      }
    } catch (err) {
      showMsg('Network error. Please check your connection.', 'error');
      reset();
    }

    function reset() {
      btn.disabled = false;
      btn.classList.remove('is-loading');
      btn.innerHTML = orig;
    }
  });

  function showMsg(msg, type) {
    if (!msgDiv) return;
    msgDiv.textContent = msg;
    msgDiv.className = 'form-message mt-3 ' + type;
    msgDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
});
</script>


<!-- ── TESTIMONIALS ───────────────────────────────────────── -->
<!-- <section class="section-lg">
  <div class="container-sz">
    <div class="section-header centered fade-in">
      <span class="section-label">Guest Experiences</span>
      <h2 class="display-2">What Our<br><em>Guests Say</em></h2>
      <div class="divider-line"></div>
    </div>
    <div class="testimonial-grid">
      <div class="testimonial fade-in">
        <div class="testimonial-quote-mark" aria-hidden="true">&ldquo;</div>
        <p class="testimonial-text">The most extraordinary week of our lives. Our guide Samuel knew exactly when and where to be for the Great Migration. Every sunrise was more breathtaking than the last.</p>
        <p class="testimonial-author">Rebecca & James T.</p>
        <p class="testimonial-origin">United Kingdom · Luxury Safari, 2024</p>
      </div>
      <div class="testimonial fade-in fade-in-delay-1">
        <div class="testimonial-quote-mark" aria-hidden="true">&ldquo;</div>
        <p class="testimonial-text">Safari Zangu organized everything seamlessly. The transition from Serengeti to Zanzibar was flawless — we didn't worry about a single detail. Worth every dollar.</p>
        <p class="testimonial-author">Marco & Sofia B.</p>
        <p class="testimonial-origin">Italy · Safari + Zanzibar, 2024</p>
      </div>
      <div class="testimonial fade-in fade-in-delay-2">
        <div class="testimonial-quote-mark" aria-hidden="true">&ldquo;</div>
        <p class="testimonial-text">As a solo traveler I was nervous, but Amina's communication was impeccable. I felt safe, informed, and cared for throughout. Will return without hesitation.</p>
        <p class="testimonial-author">Christine L.</p>
        <p class="testimonial-origin">Canada · Cultural Safari, 2025</p>
      </div>
    </div>
  </div>
</section> -->

<!-- ── CTA BANNER ─────────────────────────────────────────── -->
<section class="cta-banner">
  <div class="container-sz">
    <img src="<?= BASE_PATH ?>/assets/images/logo.jpeg" alt="Safari Zangu Ventures" style="display:block;width:220px;height:220px;object-fit:contain;margin:0 auto 2rem" loading="lazy">
    <span class="section-label">Begin Your Journey</span>
    <h2 class="display-2 cta-banner-title">Tanzania Awaits.<br><em>Will You Answer?</em></h2>
    <div class="cta-banner-actions">
      <a href="<?= BASE_PATH ?>/packages.php" class="btn-sz-dark">Explore Packages</a>
      <a href="<?= BASE_PATH ?>/book.php" class="btn-sz-outline">Book Now</a>
    </div>
  </div>
</section>

<!-- ── FOOTER ─────────────────────────────────────────────── -->