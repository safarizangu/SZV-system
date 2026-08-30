<?php
$pageTitle = $pageTitle ?? 'Safari Zangu Ventures';
$pageDescription = $pageDescription ?? 'Private Tanzania Safari Experiences';
$pageCanonical = $pageCanonical ?? '';
$pageImage = $pageImage ?? (BASE_PATH . '/assets/images/Intro22.webp');
?>

<meta charset="UTF-8">
<link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/images/logo.jpeg">
<meta name="theme-color" content="#0a0a0a">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($pageTitle) ?></title>

<meta name="description"
      content="<?= htmlspecialchars($pageDescription) ?>">

<?php if (!empty($pageCanonical)): ?>
<meta property="og:url"
      content="<?= htmlspecialchars($pageCanonical) ?>">
<?php endif; ?>

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
