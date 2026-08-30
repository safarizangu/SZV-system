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
  <div class="container-sz track-container">

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
            <label class="form-label">Travel Date <span style="font-weight:400;font-size:.75rem;color:#9ca3af">Enter as DD/MM/YYYY (e.g. 15 Sep 2026 → 15/09/2026)</span></label>
            <input type="text" name="date" id="track-date" class="form-control-sz" required
               placeholder="DD/MM/YYYY or YYYY-MM-DD"
               pattern="^(\d{2}/\d{2}/\d{4}|\d{4}-\d{2}-\d{2})$"
               autocomplete="off"
               style="font-family:monospace;letter-spacing:.05em">
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
// ── Rate limiting (item 31) ────────────────────────────────
// Rate limiting now handled server-side in php/track_lookup.php
// (3 failed attempts → 15 min lockout per IP — cannot be bypassed by new tab/session)

document.addEventListener('DOMContentLoaded', function () {
  // ── Track form submit ─────────────────────────────────────
  var trackForm = document.getElementById('check-booking-form');
  if (trackForm) {
    trackForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      var btn = document.getElementById('track-submit');
      var result = document.getElementById('booking-result');
      btn.disabled = true;
      btn.textContent = 'Searching…';
      result.innerHTML = '';

      var fd = new FormData(trackForm);

      try {
        var resp = await fetch(window.BASE_PATH + '/api/track_lookup.php', {
          method: 'POST', body: fd, credentials: 'same-origin'
        });
        var data = await resp.json();

        if (data.locked) {
          result.innerHTML = '<div style="background:#fef2f2;border:1.5px solid #fca5a5;padding:1.25rem;color:#991b1b;font-size:.85rem;line-height:1.7">' +
            '<strong>Account temporarily locked.</strong><br>' + data.message + '</div>';
        } else if (!data.success) {
          result.innerHTML = '<div style="background:#fef3e2;border:1.5px solid #fcd34d;padding:1.25rem;color:#92400e;font-size:.85rem;line-height:1.7">' +
            data.message + '</div>';
        } else {
          var d = data;
          result.innerHTML =
            '<div style="background:#f0fdf4;border:1.5px solid #86efac;padding:1.75rem;margin-bottom:1rem">' +
              '<div style="font-size:.6rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#166534;margin-bottom:.75rem">Booking Found</div>' +
              '<div style="font-family:var(--font-display,Georgia),serif;font-size:1.4rem;margin-bottom:.25rem">' + d.booking_ref + '</div>' +
              '<div style="font-size:.8rem;color:#166534">Hello, ' + d.client_first + '!</div>' +
            '</div>' +
            '<table style="width:100%;border-collapse:collapse;font-size:.85rem;margin-bottom:1rem">' +
              '<tr style="border-bottom:1px solid #e5e7eb"><td style="padding:.65rem 0;color:#6b7280;width:40%">Package</td><td style="font-weight:600">' + d.package_name + '</td></tr>' +
              '<tr style="border-bottom:1px solid #e5e7eb"><td style="padding:.65rem 0;color:#6b7280">Accommodation</td><td>' + d.accommodation + '</td></tr>' +
              '<tr style="border-bottom:1px solid #e5e7eb"><td style="padding:.65rem 0;color:#6b7280">Travel Date</td><td style="font-weight:600">' + d.travel_date + '</td></tr>' +
              '<tr style="border-bottom:1px solid #e5e7eb"><td style="padding:.65rem 0;color:#6b7280">Travelers</td><td>' + d.travelers + ' guest' + (d.travelers !== 1 ? 's' : '') + (d.children > 0 ? ' (' + d.adults + ' adults, ' + d.children + ' children)' : '') + '</td></tr>' +
              '<tr style="border-bottom:1px solid #e5e7eb"><td style="padding:.65rem 0;color:#6b7280">Booked On</td><td>' + d.booked_on + '</td></tr>' +
              '<tr><td style="padding:.65rem 0;color:#6b7280">Payment Status</td><td><span style="background:' + d.status_color + '20;color:' + d.status_color + ';padding:.15rem .5rem;font-weight:700;font-size:.75rem">' + d.status_label + '</span></td></tr>' +
            '</table>' +
            (d.payment_note ? '<div style="background:#eff6ff;border:1.5px solid #bfdbfe;padding:1rem;font-size:.82rem;color:#1d4ed8;margin-bottom:1rem">' + d.payment_note + '</div>' : '') +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">' +
              '<a href="https://wa.me/' + window.WHATSAPP_NUMBER + '?text=' + encodeURIComponent('Hi Safari Zangu, I am following up on booking ' + d.booking_ref + '.') + '" target="_blank" class="btn-sz-dark" style="font-size:.68rem;padding:.75rem;text-align:center;justify-content:center">WhatsApp Us</a>' +
              '<a href="contact.php" class="btn-sz-outline" style="font-size:.68rem;padding:.75rem;text-align:center;justify-content:center">Send Email</a>' +
            '</div>';
        }
      } catch(err) {
        result.innerHTML = '<div style="background:#fef2f2;border:1.5px solid #fca5a5;padding:1.25rem;color:#991b1b;font-size:.85rem">Network error. Please try again or contact us on WhatsApp.</div>';
      }

      btn.disabled = false;
      btn.textContent = 'Search My Booking';
    });
  }
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
