<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle       = 'Privacy Policy — Safari Zangu Ventures';
$pageDescription = 'How Safari Zangu Ventures collects, uses and protects your personal data.';
$pageCanonical   = 'https://safarizanguven.com/privacy-policy.php';
ob_start();
?>
<div class="page-hero" style="padding:clamp(5rem,9vw,7rem) var(--pad) 3rem">
  <div class="page-hero-inner container-sz">
    <div>
      <div class="breadcrumb-sz"><a href="<?= BASE_PATH ?>/">Home</a><span>/</span>Privacy Policy</div>
      <h1 class="display-1">Privacy<br><em>Policy</em></h1>
    </div>
    <div>
      <p style="color:var(--black);font-size:.9rem;font-weight:500;line-height:1.8">
        Last updated: August 2026 · Applies to safarizanguven.com and all booking communications.
      </p>
    </div>
  </div>
</div>

<section class="section">
  <div class="container-sz" style="max-width:780px">

    <?php
    $sections = [
      ['Who we are',
       'Safari Zangu Ventures Ltd is a Tanzania-registered tour operator (Reg. 179228971, Licence 037129) based in Arusha, Tanzania. We operate safarizanguven.com and handle all bookings directly. Our contact address is: info@safarizanguven.com · +255 663 430 934.'],

      ['What data we collect',
       '<strong>When you submit an enquiry or booking form</strong> we collect: your name, email address, phone number, nationality, travel dates, traveller numbers, and any special requests you include. <strong>When you visit the website</strong> we collect anonymised analytics data (pages visited, referral source) through our own first-party analytics, and — where enabled — through Google Analytics 4. No identifying data is sent to Google Analytics.'],

      ['Why we collect it',
       'We collect your personal data to: process and confirm safari bookings; send booking confirmations and pre-trip information; respond to enquiries; comply with Tanzania tourism regulations (guest registers are required by law). We do not use your data for advertising or sell it to any third party.'],

      ['How long we keep it',
       'Booking records are kept for 7 years to comply with Tanzania tax law. Enquiries that do not result in a booking are deleted after 12 months. You can request deletion of your data at any time by emailing info@safarizanguven.com — we will action within 30 days.'],

      ['Your rights (UK/EU guests)',
       'Under UK GDPR and EU GDPR you have the right to: access the personal data we hold about you; correct inaccurate data; request deletion ("right to be forgotten"); object to processing; data portability. To exercise any of these rights, email info@safarizanguven.com with the subject line "Data Request". We will respond within 30 days.'],

      ['Cookies',
       'We use one first-party session cookie (required for form security) and, where Google Analytics is enabled, Google Analytics cookies (anonymised). We do not use advertising cookies or tracking pixels. You can disable cookies in your browser settings — this will not affect your ability to use the website.'],

      ['Third parties',
       'We use the following third-party services: <strong>Google Analytics</strong> (anonymised website analytics, where enabled); <strong>PayPal</strong> (optional payment processing — their privacy policy applies to payment data); <strong>Hostinger</strong> (website hosting, servers located in Europe). We do not share your personal data with any other third party without your explicit consent.'],

      ['Data security',
       'All data is transmitted over HTTPS. Form submissions are stored in a password-protected database on Hostinger\'s EU servers. Staff with access to guest data are limited to the core SZV team. In the unlikely event of a data breach we will notify affected guests within 72 hours.'],

      ['Contact & complaints',
       'For any privacy questions, email info@safarizanguven.com. If you are a UK resident and are unhappy with our response, you have the right to complain to the Information Commissioner\'s Office (ICO) at ico.org.uk.'],
    ];
    ?>

    <?php foreach ($sections as $i => [$title, $body]): ?>
    <div style="margin-bottom:2.5rem;padding-bottom:2.5rem;border-bottom:1px solid var(--gray-2)">
      <h2 style="font-family:var(--font-display);font-size:1.15rem;font-weight:500;margin-bottom:.85rem"><?= $i+1 ?>. <?= $title ?></h2>
      <p style="font-size:.9rem;color:var(--black);line-height:1.85;font-weight:400"><?= $body ?></p>
    </div>
    <?php endforeach; ?>

    <p style="font-size:.78rem;color:#9ca3af;margin-top:1rem">
      This policy was last updated August 2026. We may update it periodically — the current version is always at safarizanguven.com/privacy-policy.php.
    </p>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
