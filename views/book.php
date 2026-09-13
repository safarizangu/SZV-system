<?php
// Accommodation levels — was in php/config.php, now defined here
$levels = defined('ACCOMMODATION_LEVELS') ? ACCOMMODATION_LEVELS : [
    'budget'       => 'Budget-Friendly',
    'midrange'     => 'Mid-Range',
    'highend'      => 'High-End / Luxury',
    'ultra_luxury' => 'Ultra Luxury',
];
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

<?php if (!empty($quoteData)): ?>
<div style="background:#0a0a0a;color:#fff;padding:.85rem 1.5rem;font-size:.82rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">
  <span>✓ Pre-filled from Quotation <strong><?= htmlspecialchars($quoteData['quote_ref']) ?></strong>
    — <?= htmlspecialchars($quoteData['client_name']) ?>
    · <?= (int)$quoteData['adults']+(int)$quoteData['children'] ?> traveler(s)
    · USD $<?= number_format($quoteData['grand_total'],0) ?></span>
  <a href="<?= BASE_PATH ?>/admin/quotation_new.php?id=<?= $quoteData['id'] ?>" style="color:#fff;font-size:.72rem;text-decoration:underline">← Back to Quotation</a>
</div>
<?php endif; ?>

<div class="page-hero">
  <div class="page-hero-inner container-sz">
    <div>
      <div class="breadcrumb-sz"><a href="<?= BASE_PATH ?>/">Home</a><span>/</span>Book</div>
      <h1 class="display-1">Book Your<br><em>Safari</em></h1>
    </div>
    <div>
      <p style="color:var(--black);font-size:.95rem;line-height:1.8;font-weight:500">
        Complete the form below to reserve your dates. We'll confirm availability
        within 24 hours and send a secure payment link — card, PayPal, or bank transfer accepted.
      </p>
      <div class="mt-3" style="border:1.5px solid var(--black);padding:1rem 1.25rem;font-size:.82rem;line-height:1.9;color:var(--black);background:#f7f7f5">
        <strong style="font-size:.65rem;letter-spacing:.14em;text-transform:uppercase;font-weight:700;display:block;margin-bottom:.3rem">Booking Policy</strong>
        25–30% deposit secures your dates · Full balance due 45 days before departure<br>
        Confirmation email sent within minutes · 24-hour human response
      </div>
    </div>
  </div>
</div>

<section class="section">
  <div class="container-sz">
    <div class="booking-layout">

      <!-- ── BOOKING FORM ───────────────────────────────── -->
      <div>
        <form id="booking-form" data-action="api/book.php" data-validate-form novalidate>
          <input type="hidden" name="_csrf" value="<?= generateCsrfToken() ?>">
          <input type="hidden" name="travelers" id="travelers-hidden" value="">
          <input type="hidden" name="package_name" id="package-name-hidden">

          <!-- Step 1: Your Details -->
          <div class="sz-form-section">
            <p class="sz-form-section-title">01 — Your Details</p>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="bk-name">
                  Full Name <span class="sz-required">*</span>
                  <span class="sz-hint">As on passport</span>
                </label>
                <input type="text" id="bk-name" name="full_name"
                       class="form-control-sz"
                       placeholder="e.g. John Kamau"
                       data-validate="required name"
                       data-label="Full name"
                       value="<?= htmlspecialchars($quoteData['client_name'] ?? '') ?>"
                       autocomplete="name" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="bk-email">
                  Email Address <span class="sz-required">*</span>
                </label>
                <input type="email" id="bk-email" name="email"
                       class="form-control-sz"
                       placeholder="your@email.com"
                       data-validate="required email"
                       data-label="Email"
                       value="<?= htmlspecialchars($quoteData['client_email'] ?? '') ?>"
                       autocomplete="email" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="bk-phone">
                  Phone / WhatsApp <span class="sz-required">*</span>
                  <span class="sz-hint">Include country code</span>
                </label>
                <input type="tel" id="bk-phone" name="phone"
                       class="form-control-sz"
                       placeholder="+255 700 000 000"
                       data-validate="required phone"
                       data-label="Phone number"
                       value="<?= htmlspecialchars($quoteData['client_phone'] ?? '') ?>"
                       autocomplete="tel" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="bk-nationality">
                  Nationality / Country
                </label>
                <input type="text" id="bk-nationality" name="nationality"
                       class="form-control-sz"
                       placeholder="e.g. United Kingdom"
                       value="<?= htmlspecialchars($quoteData['client_country'] ?? '') ?>"
                       autocomplete="country-name">
              </div>
            </div>
          </div>

          <!-- Step 2: Safari Details -->
          <div class="sz-form-section">
            <p class="sz-form-section-title">02 — Safari Details</p>

            <div class="form-group">
              <label class="form-label" for="package-select">
                Safari Package <span class="sz-required">*</span>
              </label>
              <select name="package_id" id="package-select"
                      class="form-control-sz"
                      data-validate="nonempty"
                      data-label="Safari package" required>
                <option value="">— Choose a safari package —</option>
                <?php foreach ($packages as $pkg): ?>
                <option value="<?= $pkg['id'] ?>"
                  data-name="<?= htmlspecialchars($pkg['name']) ?>"
                  data-days="<?= (int)$pkg['duration_days'] ?>"
                  data-acc="<?= htmlspecialchars(strtolower($pkg['accommodation_level'] ?? 'midrange')) ?>"
                  data-acc-label="<?= htmlspecialchars($pkg['accommodation_level'] ?? 'Mid-Range') ?>"
                  <?= (!empty($quoteData['package_name']) && $pkg['name']===$quoteData['package_name']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($pkg['name']) ?> — <?= (int)$pkg['duration_days'] ?> Days
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="adults">
                  Adults <span class="sz-required">*</span>
                  <span class="sz-hint">Age 12+</span>
                </label>
                <input type="number" id="adults" name="adults"
                       class="form-control-sz"
                       min="1" max="30" value="<?= (int)($quoteData['adults'] ?? 2) ?>"
                       data-validate="required min:1 max:30 integer"
                       data-label="Adults" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="children">
                  Children
                  <span class="sz-hint">Age 3–11 · ~50% rate</span>
                </label>
                <input type="number" id="children" name="children"
                       class="form-control-sz"
                       min="0" max="20" value="<?= (int)($quoteData['children'] ?? 0) ?>"
                       data-validate="min:0 max:20 integer"
                       data-label="Children">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="travel-date">
                  Travel Date <span class="sz-required">*</span>
                  <span class="sz-hint">First day of safari</span>
                </label>
                <input type="date" id="travel-date" name="travel_date"
                       class="form-control-sz"
                       min="<?= date('Y-m-d', strtotime('+2 days')) ?>"
                       data-validate="required future"
                       data-label="Travel date"
                       value="<?= htmlspecialchars($quoteData['start_date'] ?? '') ?>"
                       required>
              </div>
              <div class="form-group">
                <label class="form-label">
                  Accommodation Level
                </label>
                <?php foreach ($levels as $key => $label): ?>
                <input type="radio" name="accommodation_level"
                       id="acc-<?= $key ?>" value="<?= $key ?>"
                       style="display:none"
                       <?= $key === 'midrange' ? 'checked' : '' ?>>
                <?php endforeach; ?>
                <div id="acc-display" style="display:none;border:1.5px solid var(--black);padding:.75rem 1rem;background:#f7f7f5">
                  <span style="font-size:.62rem;letter-spacing:.12em;text-transform:uppercase;font-weight:700;color:#9ca3af;display:block;margin-bottom:.2rem">Package Includes</span>
                  <span id="acc-label" style="font-size:.9rem;font-weight:700;color:var(--black)"></span>
                </div>
                <p id="acc-placeholder" style="font-size:.8rem;color:#9ca3af;font-style:italic;padding:.5rem 0">
                  Select a package above to see accommodation.
                </p>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="bk-special">
                Special Requests
                <span class="sz-hint">Optional</span>
              </label>
              <textarea id="bk-special" name="special_request"
                        class="form-control-sz"
                        style="min-height:90px"
                        placeholder="Dietary requirements, accessibility needs, birthday celebrations, specific wildlife interests…"
                        data-maxchars="500"></textarea>
            </div>
          </div>

          <!-- Step 3: Price Preview -->
          <div class="sz-form-section">
            <p class="sz-form-section-title">03 — Price Preview</p>
            <div class="sz-price-preview" id="price-preview" style="display:none">
              <p style="font-size:.62rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#9ca3af;margin-bottom:.35rem">Estimated Total</p>
              <div class="sz-price-preview-total" id="preview-total">—</div>
              <div class="sz-price-breakdown" id="preview-breakdown"></div>
              <p style="font-size:.7rem;color:#9ca3af;margin-top:.75rem">25–30% deposit confirms your dates. Full balance due 45 days before departure.</p>
            </div>
            <p id="price-placeholder" style="font-size:.85rem;color:#9ca3af;font-style:italic">
              Select a package and enter travelers to see price estimate.
            </p>
          </div>

          <button type="submit" class="btn-sz-dark" id="booking-submit"
                  style="width:100%;justify-content:center;padding:1.1rem;font-size:.78rem;letter-spacing:.1em">
            Request Booking &rarr;
          </button>
          <p style="font-size:.7rem;color:#9ca3af;text-align:center;margin-top:.65rem;line-height:1.6">
            By submitting you agree to our
          <a href="<?= BASE_PATH ?>/terms.php" style="color:#9ca3af;text-decoration:underline">Booking Terms &amp; Conditions</a>
          and <a href="<?= BASE_PATH ?>/privacy-policy.php" style="color:#9ca3af;text-decoration:underline">Privacy Policy</a>
          · No card required to reserve · After confirmation you'll receive a secure payment link (card, PayPal or bank transfer)
          </p>
          <div class="form-message" id="booking-msg"></div>
        </form>
      </div>

      <!-- ── SIDEBAR ────────────────────────────────────── -->
      <div>
        <div class="booking-widget" style="position:sticky;top:100px">
          <p class="eyebrow mb-2">Need Help?</p>
          <a href="https://wa.me/<?= defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '255663430934' ?>?text=Hi!%20I%20need%20help%20booking%20a%20safari."
             target="_blank" class="btn-sz-dark w-100 justify-content-center"
             style="background:#25D366;border-color:#25D366;margin-bottom:.75rem;font-size:.72rem">
            Chat on WhatsApp
          </a>
          <a href="contact.php" class="btn-sz-outline w-100 justify-content-center"
             style="font-size:.72rem;margin-bottom:1.5rem">
            Send Email
          </a>
          <div style="border-top:1px solid var(--gray-2);padding-top:1.25rem">
            <p style="font-size:.62rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#9ca3af;margin-bottom:.75rem">Why Book Direct</p>
            <?php foreach (['Direct-booking rates','Private vehicle included','24h expert support','Free itinerary changes','Instant confirmation'] as $benefit): ?>
            <p style="font-size:.78rem;font-weight:500;color:var(--black);margin-bottom:.4rem;display:flex;align-items:center;gap:.4rem">
              <span style="color:#16a34a;font-size:.8rem">✓</span> <?= $benefit ?>
            </p>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>
