<?php $_csrf = generateCsrfToken(); ?>

<div class="page-hero">
  <div class="page-hero-inner container-sz">
    <div>
      <div class="breadcrumb-sz"><a href="<?= BASE_PATH ?>/">Home</a><span>/</span>Share Your Experience</div>
      <h1 class="display-1">How Was<br><em>Your Safari?</em></h1>
    </div>
    <div>
      <p style="color:var(--black);font-size:.95rem;font-weight:400;line-height:1.8">
        Two minutes of your time helps us keep improving — and helps future travellers know what to expect.
      </p>
    </div>
  </div>
</div>

<section class="section-lg">
  <div class="container-sz" style="max-width:640px">

    <?php if (!$ref || !$booking): ?>
    <div class="fade-in" style="text-align:center;padding:3rem 1rem">
      <h3 class="font-display mb-4">We couldn't find that booking</h3>
      <p style="color:#6b7280;margin-bottom:1.5rem">
        This review link should include your booking reference. If you have it, try the link again, or
        <a href="<?= BASE_PATH ?>/contact.php">contact us</a> and we'll help you leave feedback directly.
      </p>
    </div>

    <?php elseif ($alreadyReviewed): ?>
    <div class="fade-in" style="text-align:center;padding:3rem 1rem">
      <h3 class="font-display mb-4">Thank you — we've already received your review</h3>
      <p style="color:#6b7280">We appreciate you taking the time. If you'd like to add anything, just
        <a href="<?= BASE_PATH ?>/contact.php">contact us</a> directly.</p>
    </div>

    <?php else: ?>
    <div class="fade-in">
      <form id="review-form" data-action="api/review_submit.php" data-validate-form novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
        <input type="hidden" name="booking_ref" value="<?= htmlspecialchars($booking['booking_ref']) ?>">
        <div class="hp-field" aria-hidden="true">
          <label for="rv-website">Website</label>
          <input type="text" id="rv-website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="form-group">
          <label class="form-label" for="rv-name">Your Name <span class="sz-required">*</span></label>
          <input type="text" id="rv-name" name="guest_name" class="form-control-sz"
                 value="<?= htmlspecialchars($booking['full_name']) ?>"
                 data-validate="required minlen:2" data-label="Name" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="rv-country">Country <span class="sz-hint">Optional</span></label>
          <input type="text" id="rv-country" name="guest_country" class="form-control-sz" placeholder="United Kingdom">
        </div>

        <div class="form-group">
          <label class="form-label" for="rv-rating">Overall Rating <span class="sz-required">*</span></label>
          <select id="rv-rating" name="rating" class="form-control-sz" data-validate="required" data-label="Rating" required>
            <option value="">Select a rating…</option>
            <option value="5">★★★★★ — Excellent</option>
            <option value="4">★★★★ — Very Good</option>
            <option value="3">★★★ — Good</option>
            <option value="2">★★ — Fair</option>
            <option value="1">★ — Poor</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="rv-nps">
            How likely are you to recommend Safari Zangu to a friend? <span class="sz-hint">0 = not at all, 10 = extremely likely</span>
          </label>
          <input type="range" id="rv-nps" name="nps_score" min="0" max="10" value="9" step="1"
                 oninput="document.getElementById('rv-nps-val').textContent=this.value" style="width:100%">
          <div style="text-align:center;font-weight:700;font-size:1.1rem;margin-top:.25rem" id="rv-nps-val">9</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="rv-comment">Tell Us More <span class="sz-hint">Optional</span></label>
          <textarea id="rv-comment" name="comment" class="form-control-sz" style="min-height:120px"
                    placeholder="What stood out? What could we improve?" data-maxchars="1000"></textarea>
        </div>

        <button type="submit" class="btn-sz-dark" id="review-submit" style="width:100%;justify-content:center;padding:1rem">
          Submit Review
        </button>
        <div class="form-message" id="review-msg" style="margin-top:.75rem"></div>
      </form>
    </div>
    <?php endif; ?>

  </div>
</section>
