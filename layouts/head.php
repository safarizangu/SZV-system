<?php
$pageTitle = $pageTitle ?? 'Safari Zangu Ventures';
$pageDescription = $pageDescription ?? 'Private Tanzania Safari Experiences';
$pageCanonical = $pageCanonical ?? '';
// Fallback social-share image: a real, landscape-format safari photo that
// actually exists on disk (the previous fallback, Intro22.webp, did not —
// every page's og:image/twitter:image 404'd until a controller happened
// to set $pageImage explicitly).
$pageImage = $pageImage ?? (BASE_PATH . '/assets/images/packages/luxury-safari/1.jpg');
// og:image must be an absolute URL per spec — WhatsApp/Facebook/Twitter
// crawlers frequently fail to fetch (and so show no preview at all for)
// a relative one, which is what every page served until this point.
if (!preg_match('#^https?://#i', $pageImage)) {
    $_imgScheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $pageImage  = $_imgScheme . '://' . $_SERVER['HTTP_HOST'] . $pageImage;
}
?>

<meta charset="UTF-8">
<meta name="robots" content="index, follow">
<meta name="author" content="Safari Zangu Ventures">
<meta name="keywords" content="Tanzania safari, Serengeti, Ngorongoro, Tarangire, Zanzibar, Africa safari, private safari, Arusha">
<meta name="geo.region" content="TZ">
<meta name="geo.placename" content="Arusha, Tanzania">

<!-- Canonical URL -->
<?php
$_canonical = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http')
    .'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$_canonical = strtok($_canonical,'?'); // remove query string
?>
<link rel="canonical" href="<?= htmlspecialchars($_canonical) ?>">

<!-- Open Graph — complete set -->
<meta property="og:site_name" content="Safari Zangu Ventures">
<meta property="og:locale" content="en_GB">

<link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/images/logo.jpeg">
<meta name="theme-color" content="#0a0a0a">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($pageTitle) ?></title>

<meta name="description"
      content="<?= htmlspecialchars($pageDescription) ?>">

<meta property="og:url" content="<?= htmlspecialchars(!empty($pageCanonical)?$pageCanonical:((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].strtok($_SERVER['REQUEST_URI'],'?'))) ?>">

<meta property="og:type" content="website">

<meta property="og:title"
      content="<?= htmlspecialchars($pageTitle) ?>">

<meta property="og:description"
      content="<?= htmlspecialchars($pageDescription) ?>">

<meta property="og:image"
      content="<?= htmlspecialchars($pageImage) ?>">


<meta name="twitter:card"
      content="summary_large_image">

<meta name="twitter:title"
      content="<?= htmlspecialchars($pageTitle) ?>">

<meta name="twitter:description"
      content="<?= htmlspecialchars($pageDescription) ?>">

<meta name="twitter:image"
      content="<?= htmlspecialchars($pageImage) ?>">


<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect"
      href="https://fonts.gstatic.com"
      crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400;1,500&family=Jost:wght@300;400;500;600;700&display=swap"
      rel="stylesheet">

<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/forms.css">

<?php
// Hero image — loaded from settings table, set via Admin → Gallery.
// PERF-2: $_heroSettingRaw (the unmodified stored value, or '' if unset)
// is also exposed so layouts/app.php's own preload-tag logic further
// down can reuse this one query instead of running an identical second
// one of its own — this used to be queried twice on every page load.
$_heroImg = BASE_PATH . '/assets/images/downloaded/safari_1.jpg';
$_heroSettingRaw = '';
try {
    $_stmt = \App\Models\Database::getInstance()
        ->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
    $_stmt->execute(['hero_image']);
    $_row = $_stmt->fetch();
    $_heroSettingRaw = $_row['value'] ?? '';
    if ($_row && !empty($_row['value'])) {
        // Stored value is root-relative (no BASE_PATH — see
        // api/set_hero.php); add it back for the current environment.
        // Defensively strip a stray BASE_PATH/other-environment prefix
        // first, in case this row predates that fix.
        $_stored = $_row['value'];
        if (BASE_PATH !== '' && str_starts_with($_stored, BASE_PATH)) {
            $_stored = substr($_stored, strlen(BASE_PATH));
        }
        if (str_starts_with($_stored, '/safari-zangu')) {
            $_stored = substr($_stored, strlen('/safari-zangu'));
        }
        $_heroImg = htmlspecialchars(BASE_PATH . $_stored, ENT_QUOTES);
    }
} catch (\Exception $_e) {}
?>
<style>
.hero-bg {
  background-image: url('<?= $_heroImg ?>') !important;
  background-size: cover !important;
  background-position: center center !important;
}
</style>
