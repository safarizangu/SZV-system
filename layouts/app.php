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
    // Stored value is root-relative (no BASE_PATH) — normalize away any
    // stray prefix from before that fix, then add BASE_PATH back for
    // the current environment. Done only when a value was actually
    // found, so an empty result stays empty rather than becoming a
    // bare, always-truthy BASE_PATH.
    if ($_heroImg !== '') {
        if (BASE_PATH !== '' && str_starts_with($_heroImg, BASE_PATH)) {
            $_heroImg = substr($_heroImg, strlen(BASE_PATH));
        }
        if (str_starts_with($_heroImg, '/safari-zangu')) {
            $_heroImg = substr($_heroImg, strlen('/safari-zangu'));
        }
        $_heroImg = BASE_PATH . $_heroImg;
    }
} catch(\Exception $_e) {}
if ($_heroImg): ?>
<link rel="preload" as="image"
      href="<?= htmlspecialchars($_heroImg) ?>"
      fetchpriority="high">
<?php endif; ?>
</head>

<body<?= !empty($bodyClass) ? ' class="' . htmlspecialchars($bodyClass) . '"' : '' ?>>
<!-- A11Y-13: #c8963e (--gold) background with white text computes to
     2.66:1, failing WCAG AA (needs 4.5:1) — same underlying color as
     A11Y-6/A11Y-7, same fix (the darker, already-vetted #7a5a10, which
     computes to 6.37:1 either direction). Also fixed in passing: the
     onfocus/onblur handlers had stray literal backslashes (this.style.top=\'0\')
     left over from somewhere this was once embedded in a PHP-quoted
     string context it no longer is — plain HTML, so the backslash was
     sent to the browser as a literal character, making `\'0\'` invalid
     JS and silently breaking the show-on-focus behavior entirely. The
     skip link has likely never actually worked until this fix. -->
<a href="#main-content" class="skip-nav" style="position:absolute;top:-100px;left:0;background:#7a5a10;color:#fff;padding:.5rem 1rem;font-size:.8rem;font-weight:700;z-index:9999;text-decoration:none;border-radius:0 0 4px 0;transition:top .2s" onfocus="this.style.top='0'" onblur="this.style.top='-100px'">Skip to main content</a>

<?php include __DIR__ . '/navbar.php'; ?>

<main id="main-content">
<?= $content ?? '' ?>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script src="<?= BASE_PATH ?>/assets/js/country-codes.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/form-validator.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/analytics.js"></script>
<?php if (defined('GA4_ID') && GA4_ID !== ''): ?>
<!-- Google Analytics 4 — only loads when GA4_ID is set in .env. The
     privacy policy's claim of using GA4 previously had nothing behind
     it; this block is what makes that claim actually true. -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars(GA4_ID) ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){ dataLayer.push(arguments); }
  gtag('js', new Date());
  gtag('config', '<?= htmlspecialchars(GA4_ID) ?>', { anonymize_ip: true });
</script>
<?php endif; ?>

</body>
</html>

