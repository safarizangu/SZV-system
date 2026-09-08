<?php
require_once __DIR__ . '/../app/bootstrap.php';

$pdo = \App\Models\Database::getInstance();
$teamMembers = [];
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'team_members'")->fetchAll();
    if (!empty($tables)) {
        $teamMembers = $pdo->query(
            "SELECT * FROM team_members WHERE is_active=1 ORDER BY sort_order ASC, id ASC"
        )->fetchAll();
    }
} catch (\Exception $e) { $teamMembers = []; }

// ── Office team ──────────────────────────────────────────────
// IMPORTANT: Set 'photo' to the path of each person's headshot once uploaded.
// E.g. 'assets/images/team/samson.jpg'
// Until then the page shows an initial-letter avatar.
$fallback = [
    [
        'name'         => 'Samson Gabba',
        'role'         => 'Founder &amp; CEO',
        'bio'          => 'Samson founded Safari Zangu Ventures in 2024 after years working inside Tanzania\'s safari industry. He is hands-on with every booking in these early stages — you will likely speak to him directly. His rule: no client should ever feel like a number.',
        'photo'        => '',
        'linkedin_url' => 'https://www.linkedin.com/in/samson-gabba',
        'email'        => 'samson@safarizanguven.com',
    ],
    [
        'name'         => 'Kelvin Kazungu',
        'role'         => 'Co-Founder &amp; Client Relations',
        'bio'          => 'Kelvin manages partnerships and the pre-trip client experience. He speaks to every guest before departure and stays reachable on WhatsApp throughout the journey. Seven years in Tanzania tourism, fluent in English and Swahili.',
        'photo'        => '',
        'linkedin_url' => '',
        'email'        => 'kelvin@safarizanguven.com',
    ],
    [
        'name'         => 'Sayi Kilaja',
        'role'         => 'Legal &amp; Compliance',
        'bio'          => 'Sayi keeps the company properly licensed, contracted, and protected — so guests travel knowing every agreement means what it says. She reviews all supplier contracts and handles any post-trip matters transparently.',
        'photo'        => '',
        'linkedin_url' => '',
        'email'        => 'sayi@safarizanguven.com',
    ],
    [
        'name'         => 'Penina Mwanga',
        'role'         => 'General Manager',
        'bio'          => 'Penina manages day-to-day operations — vehicle scheduling, accommodation, guide assignments, and the hundred small logistics that determine whether a trip is good or great.',
        'photo'        => '',
        'linkedin_url' => '',
        'email'        => 'penina@safarizanguven.com',
    ],
];

// ── Field guides ─────────────────────────────────────────────
// Replace names/bios with your actual guides before going live.
// Add a 'photo' path once headshots are taken.
$guides = [
    [
        'name'      => 'Joseph Mollel',
        'role'      => 'Senior Safari Guide',
        'bio'       => '14 years guiding across the Serengeti and Ngorongoro Crater. Joseph holds a Professional Safari Guide certificate from the Tanzania Tourist Board. Ask him about predator behaviour — he knows exactly where to look and when.',
        'photo'     => '',
        'years'     => '14 years experience',
        'languages' => 'English · Swahili · French',
        'specialty' => 'Serengeti &amp; Migration',
    ],
    [
        'name'      => 'Amina Kessy',
        'role'      => 'Guide &amp; Cultural Specialist',
        'bio'       => 'Amina grew up near Loliondo and brings cultural depth no textbook can replicate. She leads every Cultural Safari package and handles all community visits personally. Fluent in Maa, Swahili, and English.',
        'photo'     => '',
        'years'     => '7 years experience',
        'languages' => 'English · Swahili · Maa',
        'specialty' => 'Cultural &amp; Community',
    ],
    [
        'name'      => 'Bernard Lyimo',
        'role'      => 'Safari Guide &amp; Birding Expert',
        'bio'       => 'Bernard specialises in guests who want depth beyond the Big Five. He holds a Birding Guide certificate and can identify over 400 Tanzanian species by call. He guides Tarangire, Lake Manyara, and Ruaha.',
        'photo'     => '',
        'years'     => '9 years experience',
        'languages' => 'English · Swahili',
        'specialty' => 'Birding · Tarangire',
    ],
];

$team   = !empty($teamMembers) ? $teamMembers : $fallback;
$delays = ['', ' fade-in-delay-1', ' fade-in-delay-2', ' fade-in-delay-3'];

$pageTitle       = 'About Us — Safari Zangu Ventures';
$pageDescription = 'Meet the team behind Safari Zangu Ventures — Arusha-based private safari specialists. Proudly Tanzanian, TALA licensed, founded 2024.';
$pageCanonical   = 'https://safarizanguven.com/about.php';
ob_start();
?>

<!-- HERO — fixed: no logo image, no wrong alt text, no fake numbers -->
<div class="page-hero">
  <div class="page-hero-inner container-sz">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:center" class="about-grid">
      <div>
        <div class="breadcrumb-sz">
          <a href="<?= BASE_PATH ?>/">Home</a><span>/</span>About
        </div>
        <h1 class="display-1">Who Are<br><em>We?</em></h1>
      </div>
      <div>
        <p style="color:var(--black);font-size:1rem;line-height:1.85;font-weight:400">
          A small, Arusha-based team running private Tanzania safaris the way we'd want our own family to experience them — honestly, personally, and without shortcuts.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- OUR STORY — honest, no invented statistics -->
<section class="section-lg">
  <div class="container-sz">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6rem;align-items:start" class="about-grid">

      <div class="fade-in">
        <span class="section-label">Our Story</span>
        <h2 class="display-2">Born in Arusha.<br><em>Built for the Wild.</em></h2>
        <div class="divider-line"></div>
        <p style="color:var(--black);font-size:.95rem;font-weight:400;line-height:1.85;margin-bottom:1.25rem">
          Safari Zangu Ventures was founded in 2024 on a simple belief: every traveler deserves a safari built around them, not slotted into someone else's fixed itinerary. The name means "My Safari" in Swahili, and that idea shaped everything from day one — an experience built around each guest, not a package sold to hundreds.
        </p>
        <p style="color:var(--black);font-size:.95rem;font-weight:400;line-height:1.85;margin-bottom:1.25rem">
          We are a young company and proud of it. We do not have decades of reviews to hide behind — we have the energy and attention of a team that still picks up the phone. Every booking is handled by a named person. Every itinerary is built from scratch. Every guest receives a follow-up.
        </p>
        <p style="color:var(--black);font-size:.95rem;font-weight:400;line-height:1.85">
          We are proudly Tanzanian. Our team is local, our knowledge is deep, and our relationship with this land goes beyond business. This is our home, and we are honoured to share it.
        </p>
      </div>

      <div class="fade-in fade-in-delay-1">
        <div style="background:var(--black);padding:3rem">
          <h3 class="font-display1" style="font-size:1.3rem;color:var(--white);margin-bottom:1rem">Our Mission</h3>
          <p style="color:var(--white);font-size:.9rem;font-weight:400;line-height:1.85;margin-bottom:2rem">
            To craft private Tanzania safari experiences that connect people deeply with the wild — through expertise, integrity, and genuine care for every guest, guide, and community we touch.
          </p>
          <div style="border-top:1px solid rgba(255,255,255,.15);padding-top:2rem">
            <h3 class="font-display1" style="font-size:1.3rem;color:var(--white);margin-bottom:1rem">Our Vision</h3>
            <p style="color:var(--white);font-size:.9rem;font-weight:400;line-height:1.85">
              To become East Africa's most trusted boutique safari operator — recognised for exceptional guiding, transparent practices, and a commitment to conservation that outlasts any single journey.
            </p>
          </div>
        </div>

        <!-- Why new = accessible -->
        <div style="background:#f7f7f5;border:1px solid var(--gray-2);padding:2rem;margin-top:1.5rem">
          <h4 style="font-family:var(--font-display);font-size:1rem;margin-bottom:.75rem">Why book with a newer operator?</h4>
          <p style="font-size:.85rem;color:var(--black);line-height:1.75;font-weight:400;margin-bottom:.65rem">
            Large operators handle hundreds of bookings simultaneously. We handle a handful — which means the founder is personally involved in yours. You have a direct line to the decision-maker, not a customer service queue.
          </p>
          <p style="font-size:.85rem;color:var(--black);line-height:1.75;font-weight:400">
            We are fully licensed under TALA (Licence No. 037129) and registered in Tanzania (No. 179228971). The credentials are real; the accountability is personal.
          </p>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- VALUES -->
<section class="section bg-off-white" style="border-top:1px solid var(--gray-2);border-bottom:1px solid var(--gray-2)">
  <div class="container-sz">
    <div class="section-header centered fade-in">
      <span class="section-label">What Drives Us</span>
      <h2 class="display-2">Our Values</h2>
      <div class="divider-line"></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:2.5rem" class="values-grid">
      <div class="fade-in p-4" style="background:var(--white);border:1px solid var(--gray-2)">
        <h4 class="font-display mb-3" style="font-size:1.1rem">Integrity First</h4>
        <p style="font-size:.875rem;color:var(--black);line-height:1.7;font-weight:500">No hidden costs. No surprise fees. Everything agreed in writing before you pay a dollar.</p>
      </div>
      <div class="fade-in fade-in-delay-1 p-4" style="background:var(--white);border:1px solid var(--gray-2)">
        <h4 class="font-display mb-3" style="font-size:1.1rem">Private Always</h4>
        <p style="font-size:.875rem;color:var(--black);line-height:1.7;font-weight:500">We never combine groups. Your vehicle, your guide, your schedule — with the flexibility to linger when a leopard appears.</p>
      </div>
      <div class="fade-in fade-in-delay-2 p-4" style="background:var(--white);border:1px solid var(--gray-2)">
        <h4 class="font-display mb-3" style="font-size:1.1rem">Community Impact</h4>
        <p style="font-size:.875rem;color:var(--black);line-height:1.7;font-weight:500">A portion of every booking supports local schools, women's cooperatives, and anti-poaching patrols.</p>
      </div>
    </div>
  </div>
</section>

<!-- OFFICE TEAM -->
<section class="section-lg" id="team">
  <div class="container-sz">
    <div class="section-header fade-in">
      <span class="section-label">The Office</span>
      <h2 class="display-2">Our Team</h2>
      <div class="divider-line"></div>
      <p style="color:var(--black);font-size:.9rem;font-weight:400;line-height:1.8;max-width:580px">
        The people who plan your safari, answer your questions, and make sure every detail is right before you land in Arusha.
      </p>
    </div>

    <div class="team-grid">
      <?php foreach ($team as $i => $member):
        $initial  = mb_strtoupper(mb_substr($member['name'], 0, 1));
        $delay    = $delays[$i % 4] ?? '';
        $photoSrc = $member['photo'] ?? '';
        $hasPhoto = $photoSrc && file_exists(__DIR__ . '/' . ltrim($photoSrc, '/'));
      ?>
      <div class="team-card fade-in<?= $delay ?>">

        <?php if ($hasPhoto): ?>
        <img src="<?= htmlspecialchars($photoSrc) ?>"
             alt="<?= htmlspecialchars($member['name']) ?>"
             class="team-avatar"
             style="width:100%;aspect-ratio:1;object-fit:cover;display:block">
        <?php else: ?>
        <div class="team-avatar" style="background:var(--black);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.8rem;font-style:italic;color:var(--white);font-weight:500;aspect-ratio:1">
          <?= $initial ?>
        </div>
        <?php endif; ?>

        <h4 class="team-name"><?= htmlspecialchars($member['name']) ?></h4>
        <p class="team-role"><?= $member['role'] ?></p>
        <p class="team-bio"><?= htmlspecialchars($member['bio'] ?? '') ?></p>

        <div class="team-social">
          <?php if (!empty($member['linkedin_url']) && $member['linkedin_url'] !== '#'): ?>
          <a href="<?= htmlspecialchars($member['linkedin_url']) ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars($member['name']) ?> on LinkedIn">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
          </a>
          <?php endif; ?>
          <?php if (!empty($member['email'])): ?>
          <a href="mailto:<?= htmlspecialchars($member['email']) ?>" aria-label="Email <?= htmlspecialchars($member['name']) ?>">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-5 pt-4 fade-in" style="border-top:1px solid var(--gray-2);font-size:.8rem;color:var(--black);text-align:center;font-weight:500">
      <p>Interested in joining us? <a href="contact.php" style="color:var(--black);font-weight:700">Get in touch</a></p>
    </div>
  </div>
</section>

<!-- GUIDES — the section UK buyers read most carefully -->

<!-- LICENSING — sitewide anchor -->
<section class="section" style="border-bottom:1px solid var(--gray-2)" id="licensing">
  <div class="container-sz text-center">
    <span class="section-label">Registration &amp; Licensing</span>
    <div style="display:flex;justify-content:center;gap:4rem;flex-wrap:wrap;margin-top:2rem">
      <div>
        <p class="font-display" style="font-size:1rem;margin-bottom:.25rem">Tanzania Registered Company</p>
        <p style="font-size:.72rem;font-weight:700;color:var(--black);letter-spacing:.1em">No. 179228971</p>
      </div>
      <div>
        <p class="font-display" style="font-size:1rem;margin-bottom:.25rem">Licensed Tour Operator (TALA)</p>
        <p style="font-size:.72rem;font-weight:700;color:var(--black);letter-spacing:.1em">Licence No. 037129</p>
      </div>
      <div>
        <p class="font-display" style="font-size:1rem;margin-bottom:.25rem">Founded 2024 — Arusha</p>
        <p style="font-size:.72rem;font-weight:700;color:var(--black);letter-spacing:.1em">Northern Circuit Specialists</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section style="background:var(--black);padding:5rem 2rem;text-align:center">
  <div class="container-sz">
    <h2 class="display-2" style="color:var(--white)">Ready to Meet<br><em>the Wild?</em></h2>
    <div class="d-flex gap-3 justify-content-center flex-wrap mt-4">
      <a href="<?= BASE_PATH ?>/packages.php" class="btn-sz-primary">Browse Packages</a>
      <a href="<?= BASE_PATH ?>/contact.php" class="btn-sz-ghost">Talk to Us First</a>
    </div>
  </div>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
