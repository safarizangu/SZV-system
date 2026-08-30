<?php
require_once __DIR__ . '/../app/bootstrap.php';
http_response_code(500);
$pageTitle = 'Server Error — Safari Zangu Ventures';
ob_start();
?>
<div style="min-height:70vh;display:flex;align-items:center;justify-content:center;padding:4rem var(--pad)">
  <div style="text-align:center;max-width:520px">
    <div style="font-family:var(--font-display);font-size:6rem;font-weight:500;line-height:1;color:#e5e7eb;margin-bottom:1.5rem">500</div>
    <h1 style="font-family:var(--font-display);font-size:1.75rem;font-weight:500;margin-bottom:1rem">Something Went Wrong</h1>
    <p style="font-size:.9rem;color:#6b7280;line-height:1.8;margin-bottom:2rem">
      Our team has been notified. Please try again in a moment.
    </p>
    <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
      <a href="<?= BASE_PATH ?>/" class="btn-sz-primary">← Back to Home</a>
      <a href="https://wa.me/255663430934" target="_blank" class="btn-sz-outline">WhatsApp Us</a>
    </div>
    <p style="margin-top:2rem;font-size:.78rem;color:#9ca3af">
      <a href="mailto:info@safarizanguven.com" style="color:var(--black)">info@safarizanguven.com</a>
      &nbsp;·&nbsp;
      <a href="tel:+255663430934" style="color:var(--black)">+255 663 430 934</a>
    </p>
  </div>
</div>
<?php $content=ob_get_clean(); include __DIR__.'/layouts/app.php'; ?>
