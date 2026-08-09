<?php
require_once __DIR__ . '/../php/config.php';
$_csrf = generateCsrfToken();
?>

<div class="page-hero">
  <div class="page-hero-inner container-sz">
    <div>
      <div class="breadcrumb-sz"><a href="<?= BASE_PATH ?>/">Home</a><span>/</span>Contact</div>
      <h1 class="display-1">Get in<br><em>Touch</em></h1>
    </div>
    <div>
      <p style="color:var(--black);font-size:.95rem;font-weight:400;line-height:1.8">
        Based in Arusha — we respond to all enquiries within 24 hours.
        WhatsApp is the fastest way to reach us.
      </p>
    </div>
  </div>
</div>

<section class="section-lg">
  <div class="container-sz">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6rem;align-items:start" class="contact-grid">

      <!-- ── FORM ───────────────────────────────────────── -->
      <div class="fade-in">
        <h3 class="font-display mb-4" style="font-size:1.3rem">Send a Message</h3>
        <form id="contact-form" data-validate-form novalidate>
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="ct-name">
                Full Name <span class="sz-required">*</span>
              </label>
              <input type="text" id="ct-name" name="full_name"
                     class="form-control-sz"
                     placeholder="Your name"
                     data-validate="required name minlen:2"
                     data-label="Full name"
                     autocomplete="name" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="ct-email">
                Email <span class="sz-required">*</span>
              </label>
              <input type="email" id="ct-email" name="email"
                     class="form-control-sz"
                     placeholder="your@email.com"
                     data-validate="required email"
                     data-label="Email"
                     autocomplete="email" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="ct-phone">
                Phone / WhatsApp
                <span class="sz-hint">Optional</span>
              </label>
              <input type="tel" id="ct-phone" name="phone"
                     class="form-control-sz"
                     placeholder="+255 700 000 000"
                     data-validate="phone"
                     data-label="Phone"
                     autocomplete="tel">
            </div>
            <div class="form-group">
              <label class="form-label" for="ct-subject">Subject</label>
              <select id="ct-subject" name="subject" class="form-control-sz">
                <option value="General Enquiry">General Enquiry</option>
                <option value="Safari Booking">Safari Booking</option>
                <option value="Custom Safari Request">Custom Safari Request</option>
                <option value="Payment Question">Payment Question</option>
                <option value="Existing Booking">Existing Booking</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="ct-message">
              Message <span class="sz-required">*</span>
              <span class="sz-hint">Minimum 20 characters</span>
            </label>
            <textarea id="ct-message" name="message"
                      class="form-control-sz"
                      style="min-height:150px"
                      placeholder="How can we help you? Tell us about your dream safari…"
                      data-validate="required minlen:20"
                      data-label="Message"
                      data-maxchars="1000"
                      required></textarea>
          </div>

          <button type="submit" class="btn-sz-dark" id="contact-submit"
                  style="width:100%;justify-content:center;padding:1rem">
            Send Message
          </button>
          <p style="font-size:.7rem;color:#9ca3af;text-align:center;margin-top:.5rem">
            We respond within 24 hours · Your data is never shared
          </p>
          <div class="form-message" id="contact-msg" style="margin-top:.75rem"></div>
        </form>
      </div>

      <!-- ── CONTACT INFO ───────────────────────────────── -->
      <div class="fade-in fade-in-delay-1">
        <h3 class="font-display mb-4" style="font-size:1.3rem">Contact Details</h3>
        <div style="display:grid;gap:2rem">

          <div style="padding-bottom:2rem;border-bottom:1px solid var(--gray-2)">
            <p style="font-size:.62rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;margin-bottom:.75rem;color:#9ca3af">WhatsApp — Fastest Response</p>
            <a href="https://wa.me/<?= defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '255663430934' ?>?text=Hi!%20I%20have%20a%20question%20about%20a%20Tanzania%20safari."
               target="_blank" class="btn-sz-dark"
               style="font-size:.72rem;padding:.75rem 1.5rem;display:inline-flex;gap:.5rem;background:#25D366;border-color:#25D366;margin-bottom:.6rem">
              <svg style="width:16px;height:16px;fill:white;flex-shrink:0" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
              Chat on WhatsApp
            </a>
            <p style="font-size:.75rem;font-weight:500;color:var(--black)">+255 663 430 934 · Available 7am – 9pm EAT</p>
          </div>

          <div style="padding-bottom:2rem;border-bottom:1px solid var(--gray-2)">
            <p style="font-size:.62rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;margin-bottom:.75rem;color:#9ca3af">Email</p>
            <a href="mailto:info@safarizanguven.com" style="font-family:var(--font-display);font-size:1rem;color:var(--black);text-decoration:none">info@safarizanguven.com</a>
            <p style="font-size:.8rem;color:#6b7280;margin-top:.25rem">Response within 24 hours guaranteed</p>
          </div>

          <div style="padding-bottom:2rem;border-bottom:1px solid var(--gray-2)">
            <p style="font-size:.62rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;margin-bottom:.75rem;color:#9ca3af">Office</p>
            <p style="font-size:.9rem;line-height:1.8;font-weight:500">
              Safari Zangu Ventures<br>
              Njiro Road, Arusha<br>
              Tanzania, East Africa
            </p>
          </div>

          <div>
            <p style="font-size:.62rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;margin-bottom:.75rem;color:#9ca3af">Response Times</p>
            <div style="font-size:.82rem;font-weight:500;line-height:2;color:var(--black)">
              <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--gray-2);padding:.2rem 0">
                <span>WhatsApp</span><span style="color:#16a34a;font-weight:700">Under 1 hour</span>
              </div>
              <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--gray-2);padding:.2rem 0">
                <span>Email / Form</span><span>Within 24 hours</span>
              </div>
              <div style="display:flex;justify-content:space-between;padding:.2rem 0">
                <span>Quote Requests</span><span>Same business day</span>
              </div>
            </div>
          </div>
        </div>

        <div class="map-placeholder">
          <p style="font-size:.62rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#9ca3af">Location</p>
          <p style="font-family:var(--font-display);font-size:1.1rem;font-style:italic">Arusha, Tanzania</p>
          <a href="https://maps.google.com/?q=Arusha,Tanzania" target="_blank"
             class="btn-sz-outline" style="font-size:.65rem;padding:.5rem 1.25rem">
            Open in Google Maps ↗
          </a>
        </div>
      </div>

    </div>
  </div>
</section>
