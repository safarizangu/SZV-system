<?php if (!isset($team)) { $team = []; } if (!isset($delays)) { $delays = []; } ?>

<div class="page-hero">
  <div class="page-hero-inner container-sz">
    <div style="display:grid;grid-template-columns:1fr auto;gap:3rem;align-items:end">
    <div>
      <div class="breadcrumb-sz"><a href="index.php">Home</a><span>/</span>About</div>
      <h1 class="display-1">Who Are<br><em>We?</em></h1>
    </div>
    <div>
      <img loading="lazy" src="assets/images/logo.jpeg" alt="Safari Zangu Ventures" class="page-hero-img" width="350" height="350">
    </div>
    </div>
  </div>
</div>

<!-- COMPANY OVERVIEW -->
<section class="section-lg">
  <div class="container-sz">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6rem;align-items:start" class="about-grid">
      <div class="fade-in">
        <span class="section-label">Our Story</span>
        <h2 class="display-2">Born in Arusha.<br><em>Built for the Wild.</em></h2>
        <div class="divider-line"></div>
        <p style="color:var(--black);font-size:.95rem;font-weight:400;line-height:1.85;margin-bottom:1.25rem">
          Founded in 2024, Safari Zangu Ventures began with one guide, one vehicle, and an unwavering commitment to creating experiences that felt personal, honest, and transformative.
        </p>
        <p style="color:var(--black);font-size:.95rem;font-weight:400;line-height:1.85;margin-bottom:1.25rem">
          Over a decade later, we've hosted over 2,400 guests from 50+ countries. We've refined every detail — the routes, the camps, the guides, the communication — to deliver what we promise: a safari that exceeds every expectation.
        </p>
        <p style="color:var(--black);font-size:.95rem;font-weight:400;line-height:1.85">
          We are proudly Tanzanian. Our team is local, our knowledge is deep, and our relationship with this land goes far beyond business. This is our home, and we're honoured to share it with you.
        </p>
      </div>
      <div class="fade-in fade-in-delay-1">
        <div style="background:var(--black);border:1px solid var(--gray-2);padding:3rem">
          <h3 class="font-display1 mb-4" style="font-size:1.3rem">Our Mission</h3>
          <p style="color:var(--white);font-size:.9rem;font-weight:400;margin-bottom:2rem">
            To craft private Tanzania safari experiences that connect people deeply with the wild — through expertise, integrity, and genuine care for every guest, guide, and community we touch.
          </p>
          <div style="border-top:1px solid var(--gray-2);padding-top:2rem">
            <h3 class="font-display1 mb-4" style="font-size:1.3rem">Our Vision</h3>
            <p style="color:var(--white);font-size:.9rem;font-weight:400;line-height:1.85">
              To be East Africa's most trusted boutique safari operator — recognized for exceptional guiding, transparent practices, and a commitment to conservation and community that outlasts any single journey.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- VALUES -->
<section class="section bg-off-white border-bottom-thin" style="border-top:1px solid var(--gray-2)">
  <div class="container-sz">
    <div class="section-header centered fade-in">
      <span class="section-label">What Drives Us</span>
      <h2 class="display-2">Our Values</h2>
      <div class="divider-line"></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:2.5rem" class="values-grid">
      <div class="fade-in p-4" style="background:var(--white);border:1px solid var(--gray-2)">
        <h4 class="font-display mb-3" style="font-size:1.1rem">Integrity First</h4>
        <p style="font-size:.875rem;color:var(--black);line-height:1.7;font-weight:500">No hidden costs. No surprise fees. Everything agreed in writing before you pay a dollar. You always know what you're getting.</p>
      </div>
      <div class="fade-in fade-in-delay-1 p-4" style="background:var(--white);border:1px solid var(--gray-2)">
        <h4 class="font-display mb-3" style="font-size:1.1rem">Private Always</h4>
        <p style="font-size:.875rem;color:var(--black);line-height:1.7;font-weight:500">We never combine groups. Your vehicle, your guide, your schedule. The flexibility to stay longer when a leopard appears means the difference between good and unforgettable.</p>
      </div>
      <div class="fade-in fade-in-delay-2 p-4" style="background:var(--white);border:1px solid var(--gray-2)">
        <h4 class="font-display mb-3" style="font-size:1.1rem">Community Impact</h4>
        <p style="font-size:.875rem;color:var(--black);line-height:1.7;font-weight:500">A portion of every booking supports local schools, women's cooperatives, and anti-poaching patrols. We believe sustainable tourism begins with accountable operators.</p>
      </div>
    </div>
  </div>
</section>

<!-- TEAM -->
<section class="section-lg" id="team">
  <div class="container-sz">
    <div class="section-header fade-in">
      <span class="section-label">The People Behind Your Safari</span>
      <h2 class="display-2">Our Team</h2>
      <div class="divider-line"></div>
    </div>

        <div class="team-grid">

<?php foreach ($team as $i => $member):
    $initial  = mb_strtoupper(mb_substr($member['name'], 0, 1));
    $delay    = $delays[$i % 4] ?? '';
    $linkedin = htmlspecialchars($member['linkedin_url'] ?? '#');
    $email    = htmlspecialchars($member['email'] ?? '');
    $name     = htmlspecialchars($member['name']);
    $role     = $member['role']; // may contain &amp; so don't double-escape
    $bio      = htmlspecialchars($member['bio'] ?? '');
?>
      <div class="team-card fade-in<?= $delay ?>">
        <div class="team-avatar" style="background:var(--gray-2);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.5rem;font-style:italic;color:var(--black);font-weight:500"><?= $initial ?></div>
        <h4 class="team-name"><?= $name ?></h4>
        <p class="team-role"><?= $role ?></p>
        <p class="team-bio"><?= $bio ?></p>
        <div class="team-social">
          <?php if (!empty($member['linkedin_url']) && $member['linkedin_url'] !== '#'): ?>
          <a href="<?= $linkedin ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $name ?> on LinkedIn"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
          <?php endif; ?>
          <?php if (!empty($member['email'])): ?>
          <a href="mailto:<?= $email ?>" aria-label="Email <?= $name ?>"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg></a>
          <?php endif; ?>
        </div>
      </div>

<?php endforeach; ?>

    </div>

    <div class="mt-5 pt-4 fade-in" style="border-top:1px solid var(--gray-2);font-size:.8rem;color:var(--black);text-align:center;font-weight:500">
      <p style="color:var(--black);font-weight:500">Interested in joining our team? <a href="contact.php" style="color:var(--black);font-weight:500">Get in touch</a></p>
    </div>
  </div>
</section>

<!-- CERTIFICATIONS -->
<section class="section bg-off-white" style="border-top:1px solid var(--gray-2);border-bottom:1px solid var(--gray-2)">
  <div class="container-sz text-center">
    <span class="section-label">Recognition &amp; Compliance</span>
    <div style="display:flex;justify-content:center;gap:4rem;flex-wrap:wrap;margin-top:2rem">
      <div>
        <p class="font-display" style="font-size:1rem;margin-bottom:.25rem">Tanzania Registered Company</p>
        <p style="font-size:.72rem;font-weight:500;color:var(--black);letter-spacing:.1em">Registration No. 179228971</p>
      </div>
      <div>
        <p class="font-display" style="font-size:1rem;margin-bottom:.25rem">Licensed Tour Operator</p>
        <p style="font-size:.72rem;font-weight:500;color:var(--black);letter-spacing:.1em">License No. 037129</p>
      </div>
      
      <div>
        <p class="font-display" style="font-size:1rem;margin-bottom:.25rem">1+ Years</p>
        <p style="font-size:.72rem;font-weight:500;color:var(--black);letter-spacing:.1em">Est. 2024 — Arusha, Tanzania</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section style="background:var(--white);padding:5rem 2rem;text-align:center">
  <div class="container-sz">
    <img src="assets/images/logo.jpeg" alt="Safari Zangu Ventures" style="display:block;width:220px;height:220px;object-fit:contain;margin:0 auto 2rem" loading="lazy">
    <h2 class="display-2" style="color:var(--black)">Ready to Meet<br><em>the Wild?</em></h2>
    <div class="d-flex gap-3 justify-content-center flex-wrap mt-4">
      <a href="packages.php" class="btn-sz-dark">Browse Packages</a>
      <a href="book.php" class="btn-sz-outline">Book Now</a>
    </div>
  </div>
</section>