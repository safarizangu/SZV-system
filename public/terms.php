<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle       = 'Booking Terms & Conditions — Safari Zangu Ventures';
$pageDescription = 'Safari Zangu Ventures booking terms, cancellation policy, deposits, and guest responsibilities.';
$pageCanonical   = 'https://safarizanguven.com/terms.php';
ob_start();
?>
<div class="page-hero" style="padding:clamp(5rem,9vw,7rem) var(--pad) 3rem">
  <div class="page-hero-inner container-sz">
    <div>
      <div class="breadcrumb-sz"><a href="<?= BASE_PATH ?>/">Home</a><span>/</span>Terms &amp; Conditions</div>
      <h1 class="display-1">Booking<br><em>Terms</em></h1>
    </div>
    <div>
      <p style="color:var(--black);font-size:.9rem;font-weight:500;line-height:1.8">
        Last updated: August 2026 · These terms apply to all bookings made through safarizanguven.com or directly with Safari Zangu Ventures.
      </p>
    </div>
  </div>
</div>

<section class="section">
  <div class="container-sz" style="max-width:780px">
    <?php
    $terms = [
      ['The Booking',
       'A booking is confirmed when Safari Zangu Ventures issues a written confirmation email and a deposit has been received. Verbal or online-form submissions are provisional reservations only until confirmed in writing by SZV.'],

      ['Deposit & Payment',
       'A non-refundable deposit of 30% of the total package price is required within 48 hours of receiving your confirmation email to secure your dates. The balance is due 45 days before your departure date. For bookings made within 45 days of departure, the full amount is due immediately. Payment is accepted by bank transfer, PayPal, or card via our secure payment link.'],

      ['Cancellation by Guest',
       'Cancellations must be notified in writing to info@safarizanguven.com. The following charges apply: 60+ days before departure — deposit forfeited; 30–59 days before departure — 50% of total price; 15–29 days before departure — 75% of total price; fewer than 15 days before departure — 100% of total price. We strongly recommend purchasing comprehensive travel insurance covering trip cancellation.'],

      ['Cancellation by Safari Zangu Ventures',
       'In the unlikely event SZV cancels your safari due to circumstances within our control, we will offer a full refund or an alternative date at the same price. For cancellations due to force majeure (natural disaster, government travel restriction, park closure, outbreak of disease), SZV will make reasonable efforts to rebook at a future date. Refund terms in force majeure situations will be communicated in writing at the time.'],

      ['Force Majeure',
       'Safari Zangu Ventures is not liable for losses, costs or expenses arising from events beyond our reasonable control, including but not limited to: acts of God, war, civil unrest, pandemic, park closures, transport strikes, or government travel advisories. Comprehensive travel insurance covering these events is strongly recommended.'],

      ['Travel Insurance',
       'All guests are strongly advised to purchase travel insurance before departure, covering: trip cancellation, medical evacuation, personal accident, and loss of personal belongings. Tanzania requires no specific insurance to enter, but medical evacuation from a remote national park can cost US$10,000–50,000 without coverage.'],

      ['Passports, Visas & Health',
       'Guests are solely responsible for obtaining a valid passport, appropriate Tanzania entry visa (available online or on arrival for most nationalities), and any required health documentation (e.g. yellow fever certificate for certain nationalities). SZV takes no responsibility for costs arising from incorrect documentation.'],

      ['Guest Conduct',
       'Guests must follow the instructions of their guide at all times in national parks and conservation areas. SZV reserves the right to terminate a safari without refund if a guest\'s conduct endangers wildlife, other guests, or SZV staff.'],

      ['Pricing',
       'All prices are quoted in US Dollars (USD) and are valid at the time of confirmation. Prices include Tanzania national park fees and VAT at the rates applicable at time of booking. SZV reserves the right to adjust prices in the event of significant changes in park fees, fuel costs or exchange rates, with written notice to the guest before the balance due date.'],

      ['Complaints',
       'Any complaint during the safari should be raised immediately with your guide or by contacting Penina Mwanga (penina@safarizanguven.com) so we have the opportunity to resolve it. Post-trip complaints should be submitted in writing within 14 days of return.'],

      ['Governing Law',
       'These terms are governed by the laws of the United Republic of Tanzania. Any dispute shall first be addressed through good-faith negotiation. Unresolved disputes may be referred to the Tourism Conciliation Board of Tanzania.'],
    ];
    ?>
    <?php foreach ($terms as $i => [$title, $body]): ?>
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
?>
