<?php
require_once __DIR__ . '/../app/bootstrap.php';
http_response_code(404);
$pageTitle = 'Page Not Found — Safari Zangu Ventures';
ob_start();
?>
<div style="min-height:70vh;display:flex;align-items:center;justify-content:center;padding:4rem var(--pad)">
  <div style="text-align:center;max-width:520px">
    <div style="font-family:var(--font-display);font-size:6rem;font-weight:500;line-height:1;color:#e5e7eb;margin-bottom:1.5rem">404</div>
    <h1 style="font-family:var(--font-display);font-size:1.75rem;font-weight:500;margin-bottom:1rem">Page Not Found</h1>
    <p style="font-size:.9rem;color:#6b7280;line-height:1.8;margin-bottom:2rem">
      The page you're looking for has moved or doesn't exist.<br>Let us help you find what you need.
    </p>
    <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
      <a href="<?= BASE_PATH ?>/" class="btn-sz-primary">← Back to Home</a>
      <a href="<?= BASE_PATH ?>/packages.php" class="btn-sz-outline">View Packages</a>
      <a href="<?= BASE_PATH ?>/contact.php" class="btn-sz-outline">Contact Us</a>
    </div>
    <p style="margin-top:2.5rem;font-size:.78rem;color:#9ca3af">
      Looking for your booking? <a href="<?= BASE_PATH ?>/track.php" style="color:var(--black);font-weight:600">Track it here →</a>
    </p>
  </div>
</div>
<?php $content=ob_get_clean(); include __DIR__.'/../layouts/app.php'; ?>
