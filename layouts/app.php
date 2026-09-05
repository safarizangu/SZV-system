<?php
// cspell:ignore Zangu zangu
$pageTitle = $pageTitle ?? 'Safari Zangu Ventures';
?>

<!DOCTYPE html>
<html lang="en">

<head>

<?php include __DIR__ . '/head.php'; ?>


<!-- Structured Data — Local Business -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "TouristInformationCenter",
  "name": "Safari Zangu Ventures",
  "description": "Private Tanzania safari operator based in Arusha",
  "url": "https://safarizanguven.com",
  "telephone": "+255663430934",
  "email": "info@safarizanguven.com",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Njiro Road",
    "addressLocality": "Arusha",
    "addressCountry": "TZ"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": -3.3869,
    "longitude": 36.6828
  },
  "openingHours": "Mo-Su 07:00-21:00",
  "currenciesAccepted": "USD",
  "priceRange": "$$$$",
  "areaServed": "Tanzania"
}
</script>

<?php
// Preload the hero image for LCP performance
$_heroImg = '';
try {
    $_db = \App\Models\Database::getInstance();
    $_hs = $_db->prepare("SELECT value FROM settings WHERE `key`='hero_image' LIMIT 1");
    $_hs->execute();
    $_heroImg = $_hs->fetchColumn() ?: '';
} catch(\Exception $_e) {}
if ($_heroImg): ?>
<link rel="preload" as="image"
      href="<?= htmlspecialchars($_heroImg) ?>"
      fetchpriority="high">
<?php endif; ?>
</head>

<body>
<a href="#main-content" class="skip-nav" style="position:absolute;top:-100px;left:0;background:#c8963e;color:#fff;padding:.5rem 1rem;font-size:.8rem;font-weight:700;z-index:9999;text-decoration:none;border-radius:0 0 4px 0;transition:top .2s" onfocus="this.style.top=\'0\'" onblur="this.style.top=\'-100px\'">Skip to main content</a>

<?php include __DIR__ . '/navbar.php'; ?>

<main id="main-content">
<?= $content ?? '' ?>
</main>

<?php include __DIR__ . '/footer.php'; ?>
<!-- <a href="https://wa.me/255663430934?text=<?= urlencode('Hi Safari Zangu, I\'d like to enquire about a Tanzania safari.') ?>"
   id="wa-float" class="wa-float" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="width:22px;height:22px;fill:#fff;flex-shrink:0">
    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
    <path d="M11.999 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2.22 21.5l4.432-1.162A9.945 9.945 0 0011.999 22c5.523 0 10-4.477 10-10S17.522 2 11.999 2z"/>
  </svg>
  <span class="wa-float-label">Chat with us</span>
</a> -->

<script src="<?= BASE_PATH ?>/assets/js/country-codes.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/form-validator.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/analytics.js"></script>

</body>
</html>

