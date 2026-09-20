<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle       = 'Sustainability — Safari Zangu Ventures';
$pageDescription = 'How Safari Zangu Ventures operates responsibly in Tanzania — local ownership, private vehicles, and conservation fees.';
$pageCanonical   = 'https://safarizanguven.com/sustainability.php';
ob_start();
?>
<div class="page-hero" style="padding:clamp(5rem,9vw,7rem) var(--pad) 3rem">
  <div class="page-hero-inner container-sz">
    <div>
      <div class="breadcrumb-sz"><a href="<?= BASE_PATH ?>/">Home</a><span>/</span>Sustainability</div>
      <h1 class="display-1">Traveling<br><em>Responsibly</em></h1>
    </div>
    <div>
      <p style="color:var(--black);font-size:.9rem;font-weight:500;line-height:1.8">
        We're a small, young operator — here's what that actually means for how we run trips, honestly and without overstating it.
      </p>
    </div>
  </div>
</div>

<section class="section">
  <div class="container-sz" style="max-width:780px">
    <?php
    $sections = [
      ['Tanzanian-owned, Tanzanian-run',
       'Safari Zangu Ventures is registered and based in Arusha (Reg. 179228971, TALA Licence 037129). Every guide, driver and office team member is Tanzanian. Money spent on a trip with us stays largely in the local economy — wages, vehicle maintenance, lodge bookings, park fees — rather than flowing to an overseas head office.'],

      ['Private vehicles, not shared trucks',
       'Every safari runs in your own private vehicle — we never combine unrelated groups to fill a truck. That means fewer vehicles competing for the same game-viewing spot, and a lighter footprint per guest than the shared-group model most large operators use.'],

      ['Park and conservation fees, on every booking',
       'Every itinerary includes Tanzania\'s official national park entry and conservation fees — the direct funding mechanism for park management, anti-poaching patrols and habitat protection in Serengeti, Ngorongoro and the other parks we visit. These aren\'t optional add-ons; they\'re built into the price of every package.'],

      ['Where we are today',
       'We\'re a young company, and we\'d rather say that plainly than dress up a small operation with figures we can\'t back up. As we grow, we intend to formalise direct partnerships with specific community organisations and publish real numbers here — not before we can name exactly who benefits and by how much.'],
    ];
    ?>
    <?php foreach ($sections as $i => [$title, $body]): ?>
    <div style="margin-bottom:2.5rem;padding-bottom:2.5rem;border-bottom:1px solid var(--gray-2)">
      <h2 style="font-family:var(--font-display);font-size:1.15rem;font-weight:500;margin-bottom:.85rem"><?= $i+1 ?>. <?= $title ?></h2>
      <p style="font-size:.9rem;color:var(--black);line-height:1.85;font-weight:400"><?= $body ?></p>
    </div>
    <?php endforeach; ?>
    <p style="font-size:.78rem;color:#9ca3af">
      Safari Zangu Ventures Ltd · Registration No. 179228971 · TALA Licence No. 037129 · Arusha, Tanzania
    </p>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
