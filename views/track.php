<?php
require_once __DIR__ . '/../php/config.php';
$_wa = defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '255663430934';
?>

<div class="page-hero">
  <div class="page-hero-inner container-sz">
    <div>
      <div class="breadcrumb-sz"><a href="<?= BASE_PATH ?>/">Home</a><span>/</span>Track Booking</div>
      <h1 class="display-1">Track<br><em>Your Booking</em></h1>
    </div>
    <div>
      <p style="color:var(--black);font-size:.95rem;line-height:1.8;font-weight:500">
        Enter your Booking ID and Travel Date to view your full booking
        details, payment status, cost breakdown, and payment instructions.
      </p>
    </div>
  </div>
</div>

<section class="section">
  <div class="container-sz" style="max-width:800px">

    <!-- Search Form -->
    <div style="border:1.5px solid var(--gray-2);padding:2rem;margin-bottom:2rem;background:var(--off-white)">
      <p style="font-size:.65rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;margin-bottom:1.25rem;color:var(--black)">Find Your Booking</p>
      <form id="check-booking-form">
        <div class="form-row" style="margin-bottom:1rem">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Booking ID *</label>
            <input type="text" name="ref" class="form-control-sz"
                   placeholder="e.g. SZV2026AB1234"
                   style="text-transform:uppercase;letter-spacing:.08em;font-size:1rem;font-family:monospace"
                   required autocomplete="off">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Travel Date *</label>
            <input type="date" name="date" class="form-control-sz" required>
          </div>
        </div>
        <button type="submit" class="btn-sz-dark w-100 justify-content-center"
                style="padding:.9rem;font-size:.72rem;letter-spacing:.14em" id="track-submit">
          Search My Booking
        </button>
        <p style="font-size:.7rem;color:#9ca3af;margin-top:.65rem;text-align:center;line-height:1.6">
          Both fields were emailed when you booked · Used together as your secure access code
        </p>
      </form>
    </div>

    <!-- Result Container -->
    <div id="booking-result"></div>

    <!-- Help -->
    <div class="mt-5 pt-4" style="border-top:1px solid var(--gray-2)">
      <p class="eyebrow mb-3">Can't Find Your Booking?</p>
      <p style="font-size:.82rem;font-weight:500;color:var(--black);margin-bottom:1.25rem;line-height:1.7">
        Check your email for subject <em>"Booking Confirmed — Safari Zangu Ventures"</em>.
        Your Booking ID starts with <strong>SZV</strong>.
      </p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <a href="https://wa.me/<?= $_wa ?>?text=Hi!%20I%20need%20help%20finding%20my%20booking%20reference."
           target="_blank" class="btn-sz-dark justify-content-center"
           style="font-size:.68rem;padding:.75rem">Chat on WhatsApp</a>
        <a href="contact.php" class="btn-sz-outline justify-content-center"
           style="font-size:.68rem;padding:.75rem">Send Email</a>
      </div>
    </div>

  </div>
</section>

<script>
window.PAYPAL_CLIENT_ID  = "<?= htmlspecialchars(PAYPAL_CLIENT_ID) ?>";
window.PAYPAL_CURRENCY   = "<?= htmlspecialchars(PAYPAL_CURRENCY) ?>";
window.WHATSAPP_NUMBER   = "<?= htmlspecialchars($_wa) ?>";
window.WHATSAPP_BASE_URL = "https://wa.me/<?= htmlspecialchars($_wa) ?>";
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var params = new URLSearchParams(window.location.search);
  var urlRef  = params.get('ref');
  var urlDate = params.get('date');
  if (urlRef && urlDate) {
    var refEl  = document.querySelector('[name="ref"]');
    var dateEl = document.querySelector('[name="date"]');
    if (refEl)  refEl.value  = urlRef;
    if (dateEl) dateEl.value = urlDate;
    var form = document.getElementById('check-booking-form');
    if (form) form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
  }
});
</script>
