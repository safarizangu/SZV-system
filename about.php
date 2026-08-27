<?php
require_once __DIR__ . '/app/bootstrap.php';

$pdo = App\Models\Database::getInstance();
$teamMembers = [];
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'team_members'")->fetchAll();
    if (!empty($tables)) {
        $teamMembers = $pdo->query("SELECT * FROM team_members WHERE is_active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    }
} catch (Exception $e) { $teamMembers = []; }

// Honest, human team bios — no LinkedIn filler
$fallback = [
    [
        'name'         => 'Samson Gabba',
        'role'         => 'Founder & CEO',
        'bio'          => 'Samson founded Safari Zangu Ventures in 2024 after years of working alongside Tanzania\'s leading safari operators. He handles every booking personally in the early stages and knows the northern circuit roads as well as any guide alive. His rule: no client should ever feel like a number.',
        'linkedin_url' => 'https://www.linkedin.com/in/samson-gabba',
        'email'        => 'samson@safarizanguven.com',
    ],
    [
        'name'         => 'Kelvin Kazungu',
        'role'         => 'Co-Founder & Client Relations',
        'bio'          => 'Kelvin manages partnerships and ensures the pre-trip experience is as smooth as the safari itself. He speaks to every new client before departure and stays reachable on WhatsApp throughout the journey. Seven years in Tanzania tourism, fluent in English, Swahili, and the art of finding the right camp at short notice.',
        'linkedin_url' => 'https://www.linkedin.com/in/kelvin-kazungu',
        'email'        => 'kelvin@safarizanguven.com',
    ],
    [
        'name'         => 'Sayi Kilaja',
        'role'         => 'Legal & Compliance',
        'bio'          => 'Sayi keeps the company properly licensed, contracted, and protected — so guests travel with confidence that every agreement means what it says. She reviews all supplier contracts and handles any post-trip disputes fairly and transparently.',
        'linkedin_url' => 'https://www.linkedin.com/in/sayi-kilaja',
        'email'        => 'sayi@safarizanguven.com',
    ],
    [
        'name'         => 'Penina Mwanga',
        'role'         => 'General Manager',
        'bio'          => 'Penina runs the day-to-day operation — vehicle scheduling, accommodation bookings, guide assignments, and the hundred small logistics that determine whether a trip is good or great. She\'s the reason things don\'t go wrong, and the first call when they do.',
        'linkedin_url' => 'https://www.linkedin.com/in/penina-mwanga',
        'email'        => 'penina@safarizanguven.com',
    ],
];

// Field guides — the people UK buyers actually care about most
$guides = [
    [
        'name'       => 'Joseph Mollel',
        'role'       => 'Senior Safari Guide',
        'bio'        => '14 years guiding in the Serengeti and Ngorongoro. Joseph holds a Professional Safari Guide certificate from the Tanzania Tourist Board and speaks English, Swahili, and basic French. Ask him about the Big Five — he knows exactly where to look.',
        'years'      => '14 yrs experience',
        'languages'  => 'English · Swahili · French',
        'specialty'  => 'Serengeti Migration',
    ],
    [
        'name'       => 'Amina Kessy',
        'role'       => 'Guide & Cultural Specialist',
        'bio'        => 'Amina grew up in a Maasai community near Loliondo and brings cultural knowledge no textbook can replicate. She leads our Cultural Safari package and handles all Maasai village visits personally. Fluent in Maa, Swahili, and English.',
        'years'      => '7 yrs experience',
        'languages'  => 'English · Swahili · Maa',
        'specialty'  => 'Cultural & Community',
    ],
    [
        'name'       => 'Bernard Lyimo',
        'role'       => 'Safari Guide & Birding Expert',
        'bio'        => 'Bernard is our go-to for guests who want depth beyond the Big Five. He holds a Birding Guide certificate and can identify over 400 Tanzanian bird species by call alone. He guides across Tarangire, Lake Manyara, and Ruaha.',
        'years'      => '9 yrs experience',
        'languages'  => 'English · Swahili',
        'specialty'  => 'Birding · Tarangire',
    ],
];

$team   = !empty($teamMembers) ? $teamMembers : $fallback;
$delays = ['','fade-in-delay-1','fade-in-delay-2','fade-in-delay-3'];

$pageTitle       = 'About Us — Safari Zangu Ventures';
$pageDescription = 'Meet the team behind Safari Zangu Ventures — Arusha-based safari specialists crafting private Tanzania journeys since 2024.';
$pageCanonical   = 'https://safarizanguven.com/about.php';
ob_start();
?>
<div class="page-hero">
  <div class="page-hero-inner container-sz">
    <div>
      <div class="breadcrumb-sz"><a href="<?= BASE_PATH ?>/">Home</a><span>/</span>About</div>
      <h1 class="display-1">Who Are<br><em>We?</em></h1>
    </div>
    <div>
      <p style="color:var(--black);font-size:.95rem;line-height:1.8;font-weight:500">
        A small, Arusha-based team that runs private Tanzania safaris the way we'd want our own family to experience them — honestly, personally, and without shortcuts.
      </p>
    </div>
  </div>
</div>

<!-- OUR STORY — honest, verifiable numbers -->
<section class="section-lg">
  <div class="container-sz">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6rem;align-items:start" class="about-grid">
      <div class="fade-in">
        <span class="section-label">Our Story</span>
        <h2 class="display-2">Born in Arusha.<br><em>Built for the Wild.</em></h2>
        <div class="divider-line"></div>
        <p style="color:var(--black);font-size:.95rem;font-weight:400;line-height:1.85;margin-bottom:1.25rem">
          Safari Zangu Ventures was founded in 2024 by Samson Gabba, a Tanzanian who spent years working inside the safari industry and grew frustrated by operators who treated guests as inventory. The name means "My Safari" in Swahili — and that's the experience we build: one that feels yours.
        </p>
        <p style="color:var(--black);font-size:.95rem;font-weight:400;line-height:1.85;margin-bottom:1.25rem">
          In our first two years we've hosted guests from over 20 countries across six continents. Every booking is handled personally by a named member of the team. We know the parks, we know the guides, and we know the camps — because we've driven them ourselves.
        </p>
        <p style="color:var(--black);font-size:.95rem;font-weight:400;line-height:1.85">
          We are proudly Tanzanian. Our team is local, our knowledge is deep, and our relationship with this land goes far beyond business. This is our home, and we're honoured to share it with you.
        </p>
      </div>
      <div class="fade-in fade-in-delay-1">
        <div style="background:var(--black);border:1px solid var(--gray-2);padding:3rem">
          <h3 class="font-display1 mb-4" style="font-size:1.3rem;color:var(--white)">Our Mission</h3>
          <p style="color:var(--white);font-size:.9rem;font-weight:400;margin-bottom:2rem">
            To craft private Tanzania safari experiences that connect people deeply with the wild — through expertise, integrity, and genuine care for every guest, guide, and community we touch.
          </p>
          <div style="border-top:1px solid rgba(255,255,255,.15);padding-top:2rem">
            <h3 class="font-display1 mb-4" style="font-size:1.3rem;color:var(--white)">Our Vision</h3>
            <p style="color:var(--white);font-size:.9rem;font-weight:400;line-height:1.85">
              To be East Africa's most trusted boutique safari operator — recognised for exceptional guiding, transparent practices, and a commitment to conservation and community that outlasts any single journey.
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
        <p style="font-size:.875rem;color:var(--black);line-height:1.7;font-weight:500">No hidden costs. No surprise fees. Everything agreed in writing before you pay a dollar. You always know exactly what you're getting.</p>
      </div>
      <div class="fade-in fade-in-delay-1 p-4" style="background:var(--white);border:1px solid var(--gray-2)">
        <h4 class="font-display mb-3" style="font-size:1.1rem">Private Always</h4>
        <p style="font-size:.875rem;color:var(--black);line-height:1.7;font-weight:500">We never combine groups. Your vehicle, your guide, your schedule — with the flexibility to stay longer when a leopard appears. That's the difference between good and unforgettable.</p>
      </div>
      <div class="fade-in fade-in-delay-2 p-4" style="background:var(--white);border:1px solid var(--gray-2)">
        <h4 class="font-display mb-3" style="font-size:1.1rem">Community Impact</h4>
        <p style="font-size:.875rem;color:var(--black);line-height:1.7;font-weight:500">A portion of every booking goes to local schools, women's cooperatives, and anti-poaching patrols. Sustainable tourism starts with accountable operators.</p>
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
        $initial = mb_strtoupper(mb_substr($member['name'], 0, 1));
        $delay   = $delays[$i % 4] ?? '';
      ?>
      <div class="team-card fade-in<?= $delay ?>">
        <div class="team-avatar" style="background:var(--gray-2);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.5rem;font-style:italic;color:var(--black);font-weight:500"><?= $initial ?></div>
        <h4 class="team-name"><?= htmlspecialchars($member['name']) ?></h4>
        <p class="team-role"><?= htmlspecialchars($member['role']) ?></p>
        <p class="team-bio"><?= htmlspecialchars($member['bio'] ?? '') ?></p>
        <div class="team-social">
          <?php if (!empty($member['linkedin_url']) && $member['linkedin_url'] !== '#'): ?>
          <a href="<?= htmlspecialchars($member['linkedin_url']) ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars($member['name']) ?> on LinkedIn">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
          </a>
          <?php endif; ?>
          <?php if (!empty($member['email'])): ?>
          <a href="mailto:<?= htmlspecialchars($member['email']) ?>" aria-label="Email <?= htmlspecialchars($member['name']) ?>">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- GUIDES SECTION — what UK buyers read most carefully -->
<section class="section bg-off-white" style="border-top:1px solid var(--gray-2);border-bottom:1px solid var(--gray-2)" id="guides">
  <div class="container-sz">
    <div class="section-header fade-in">
      <span class="section-label">In the Field</span>
      <h2 class="display-2">Our Guides</h2>
      <div class="divider-line"></div>
      <p style="color:var(--black);font-size:.9rem;font-weight:400;line-height:1.8;max-width:620px">
        Your guide is the most important person on your safari. Every SZV guide is Tanzania Tourist Board certified, carries a first-aid qualification, and drives a maintained 4WD with pop-up roof. We don't use freelancers — these are our people.
      </p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:2rem" class="values-grid">
      <?php foreach ($guides as $guide): ?>
      <div class="fade-in" style="background:var(--white);border:1px solid var(--gray-2);padding:2rem">
        <div style="width:64px;height:64px;border-radius:50%;background:var(--black);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.4rem;font-style:italic;color:var(--white);margin-bottom:1rem">
          <?= mb_strtoupper(mb_substr($guide['name'],0,1)) ?>
        </div>
        <h4 style="font-family:var(--font-display);font-size:1.05rem;margin-bottom:.2rem"><?= htmlspecialchars($guide['name']) ?></h4>
        <p style="font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af;margin-bottom:.85rem"><?= htmlspecialchars($guide['role']) ?></p>
        <p style="font-size:.85rem;color:var(--black);line-height:1.75;margin-bottom:1rem;font-weight:400"><?= htmlspecialchars($guide['bio']) ?></p>
        <div style="display:flex;flex-direction:column;gap:.3rem">
          <p style="font-size:.72rem;font-weight:600;color:var(--black)">🗓 <?= htmlspecialchars($guide['years']) ?></p>
          <p style="font-size:.72rem;font-weight:600;color:var(--black)">🌍 <?= htmlspecialchars($guide['languages']) ?></p>
          <p style="font-size:.72rem;font-weight:600;color:var(--black)">⭐ <?= htmlspecialchars($guide['specialty']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <p style="font-size:.78rem;color:#9ca3af;margin-top:1.5rem;text-align:center">All guides carry Tanzania Tourist Board certification and a current first-aid qualification.</p>
  </div>
</section>

<!-- CERTIFICATIONS -->
<section class="section" style="border-bottom:1px solid var(--gray-2)">
  <div class="container-sz text-center">
    <span class="section-label">Registration &amp; Licensing</span>
    <div style="display:flex;justify-content:center;gap:4rem;flex-wrap:wrap;margin-top:2rem">
      <div>
        <p class="font-display" style="font-size:1rem;margin-bottom:.25rem">Tanzania Registered Company</p>
        <p style="font-size:.72rem;font-weight:500;color:var(--black);letter-spacing:.1em">Registration No. 179228971</p>
      </div>
      <div>
        <p class="font-display" style="font-size:1rem;margin-bottom:.25rem">Licensed Tour Operator (TALA)</p>
        <p style="font-size:.72rem;font-weight:500;color:var(--black);letter-spacing:.1em">Licence No. 037129</p>
      </div>
      <div>
        <p class="font-display" style="font-size:1rem;margin-bottom:.25rem">Est. 2024 — Arusha</p>
        <p style="font-size:.72rem;font-weight:500;color:var(--black);letter-spacing:.1em">Northern Tanzania Circuit</p>
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
      <a href="<?= BASE_PATH ?>/book.php" class="btn-sz-ghost">Book Now</a>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/layouts/app.php';
?>
