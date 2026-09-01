<?php
// Detect active page for nav highlighting
$_currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$_currentPath = $_SERVER['REQUEST_URI'] ?? '';
function _isActive(string $page, string $hash = ''): string {
    global $_currentPage, $_currentPath;
    if ($hash && str_contains($_currentPath, $hash)) return ' class="active-link"';
    return $_currentPage === $page ? ' class="active-link"' : '';
}
?>
<nav class="sz-nav" id="sz-nav" role="navigation" aria-label="Main navigation">
  <div class="nav-inner">

    <!-- Logo — full wordmark -->
    <a href="<?= BASE_PATH ?>/" class="sz-logo" aria-label="Safari Zangu Ventures — Home">
      <b>SZV<b>
    </a>

    <!-- Desktop links -->
    <ul class="sz-nav-links" role="list">
      <li><a href="<?= BASE_PATH ?>/packages.php"<?= _isActive('packages.php') ?>>Packages</a></li>
      <li><a href="<?= BASE_PATH ?>/#customize"<?= _isActive('index.php','customize') ?>>Customize</a></li>
      <li><a href="<?= BASE_PATH ?>/about.php"<?= _isActive('about.php') ?>>About</a></li>
      <li><a href="<?= BASE_PATH ?>/contact.php"<?= _isActive('contact.php') ?>>Contact</a></li>
      <li><a href="<?= BASE_PATH ?>/track.php"<?= _isActive('track.php') ?>>Track</a></li>
      <li><a href="<?= BASE_PATH ?>/book.php" class="sz-nav-cta"<?= _isActive('book.php') ?>>Book Now</a></li>
    </ul>

    <!-- Mobile hamburger -->
    <button class="nav-toggle" id="nav-toggle"
            aria-label="Toggle menu" aria-expanded="false"
            aria-controls="mobile-nav">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- Mobile drawer -->
<div class="sz-mobile-nav" id="mobile-nav"
     role="navigation" aria-label="Mobile navigation"
     aria-hidden="true">
  <a href="<?= BASE_PATH ?>/packages.php"<?= _isActive('packages.php') ?>>
    Packages <span aria-hidden="true">→</span>
  </a>
  <a href="<?= BASE_PATH ?>/#customize">Customize Safari <span aria-hidden="true">→</span></a>
  <a href="<?= BASE_PATH ?>/about.php"<?= _isActive('about.php') ?>>
    About Us <span aria-hidden="true">→</span>
  </a>
  <a href="<?= BASE_PATH ?>/contact.php"<?= _isActive('contact.php') ?>>
    Contact <span aria-hidden="true">→</span>
  </a>
  <a href="<?= BASE_PATH ?>/track.php"<?= _isActive('track.php') ?>>
    Track Booking <span aria-hidden="true">→</span>
  </a>
  <a href="<?= BASE_PATH ?>/book.php" style="background:var(--gold,#c8963e);color:#fff">
    Book Now →
  </a>
</div>
