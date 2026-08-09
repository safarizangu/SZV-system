<?php
require_once __DIR__ . '/app/bootstrap.php';
http_response_code(404);
$pageTitle       = '404 — Page Not Found';
$pageDescription = 'The page you were looking for could not be found.';
ob_start();
?>
<div class="page-hero" style="min-height:60vh;display:flex;align-items:center">
  <div class="page-hero-inner container-sz">
    <div>
      <div class="breadcrumb-sz"><a href="<?= BASE_PATH ?>/">Home</a><span>/</span>404</div>
      <h1 class="display-1" style="font-size:clamp(3rem,10vw,7rem)">404</h1>
      <h2 class="font-display" style="font-size:1.5rem;font-weight:400;margin-bottom:1rem">Page Not Found</h2>
      <p style="font-size:.95rem;font-weight:500;color:var(--black);margin-bottom:2rem;line-height:1.7">
        The page you were looking for has moved or doesn't exist.<br>
        Let's get you back on safari.
      </p>
      <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <a href="<?= BASE_PATH ?>/" class="btn-sz-dark">Back to Home</a>
        <a href="<?= BASE_PATH ?>/packages.php" class="btn-sz-outline">View Packages</a>
        <a href="https://wa.me/<?= defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '255663430934' ?>"
           target="_blank" class="btn-sz-outline">WhatsApp Us</a>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layouts/app.php';
