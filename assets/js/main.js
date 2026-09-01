/* ============================================================
   SAFARI ZANGU VENTURES — Main JavaScript
   ============================================================ */

'use strict';

// ── CUSTOM SAFARI FORM ──────────────────────────────────
const customForm = document.querySelector('#custom-safari-form');
if (customForm) {
  customForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn   = customForm.querySelector('[type="submit"]');
    const msgEl = customForm.querySelector('.form-message');
    const orig  = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Sending…';

    try {
      const fd = new FormData(customForm);

      // Attach the selected country's full name alongside the dial code
      const countrySelect = customForm.querySelector('#phone-country-select');
      if (countrySelect) {
        const selectedOpt = countrySelect.options[countrySelect.selectedIndex];
        fd.append('phone_country_name', selectedOpt?.dataset.countryName || '');
      }

      const res  = await fetch('php/custom_safari.php', { method: 'POST', body: fd });
      const data = await res.json();
      showMsg(msgEl, data.message, data.success ? 'success' : 'error');
      if (data.success) customForm.reset();
    } catch {
      showMsg(msgEl, 'Network error. Please try again.', 'error');
    }
    btn.disabled = false;
    btn.innerHTML = orig;
  });
}

// ── NAV SCROLL BEHAVIOUR ─────────────────────────────────
const nav = document.querySelector('.sz-nav');
if (nav) {
  // Only apply over-hero if a .hero element exists on this page
  const hasHero = document.querySelector('.hero');

  const onScroll = () => {
    const scrolled = window.scrollY > 60;
    nav.classList.toggle('scrolled', scrolled);
    // over-hero: only on pages with a hero AND not yet scrolled
    if (hasHero) {
      nav.classList.toggle('over-hero', !scrolled);
    }
    // No hero = always black nav, never add over-hero
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll(); // run immediately on page load
}

// ── MOBILE NAV ───────────────────────────────────────────
const navToggle = document.querySelector('.nav-toggle');
const mobileNav = document.querySelector('.sz-mobile-nav');
if (navToggle && mobileNav) {
  const openNav = () => {
    const isOpen = mobileNav.classList.contains('open');
    mobileNav.classList.toggle('open');
    mobileNav.setAttribute('aria-hidden', mobileNav.classList.contains('open') ? 'false' : 'true');
    navToggle.classList.toggle('is-open');
    navToggle.setAttribute('aria-expanded', String(!isOpen));
  };
  navToggle.addEventListener('click', openNav);

  const closeNav = () => {
    mobileNav.classList.remove('open');
    navToggle.classList.remove('is-open');
    navToggle.setAttribute('aria-expanded', 'false');
  };

  mobileNav.querySelectorAll('a').forEach(a => a.addEventListener('click', closeNav));

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!navToggle.contains(e.target) && !mobileNav.contains(e.target)) closeNav();
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mobileNav.classList.contains('open')) closeNav();
  });
}

// ── HERO IMAGE PARALLAX / LOAD ───────────────────────────
const heroBg = document.querySelector('.hero-bg');
if (heroBg) {
  heroBg.addEventListener('animationend', () => heroBg.classList.add('loaded'));
  setTimeout(() => heroBg.classList.add('loaded'), 100);
}


// ── COUNTRY DIAL CODE DROPDOWN (Custom Safari form) ─────
const phoneCountrySelect = document.getElementById('phone-country-select');
if (phoneCountrySelect && typeof COUNTRY_DIAL_CODES !== 'undefined') {
  COUNTRY_DIAL_CODES.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.code;
    opt.textContent = `${c.name} (${c.code})`;
    opt.dataset.countryName = c.name;
    if (c.iso === 'TZ') opt.selected = true;
    phoneCountrySelect.appendChild(opt);
  });
}

// ── TRAVELERS COUNT DROPDOWN (Custom Safari form) ────────
const travelersCountSelect = document.getElementById('travelers-count-select');
if (travelersCountSelect) {
  for (let i = 1; i <= 14; i++) {
    const opt = document.createElement('option');
    opt.value = i;
    opt.textContent = i === 1 ? '1 person' : `${i} people`;
    travelersCountSelect.appendChild(opt);
  }
  const opt15 = document.createElement('option');
  opt15.value = '15+';
  opt15.textContent = '15+ people';
  travelersCountSelect.appendChild(opt15);
}


// ── SET MIN DATES on date pickers to tomorrow ────────────
const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
const minDateStr = tomorrow.toISOString().split('T')[0];
document.querySelectorAll('input[type="date"]').forEach(input => {
  if (!input.min) input.min = minDateStr;
});

// ── INTERSECTION OBSERVER — FADE IN ─────────────────────
const fadeEls = document.querySelectorAll('.fade-in');
if (fadeEls.length && 'IntersectionObserver' in window) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

  fadeEls.forEach(el => observer.observe(el));

  // Immediately trigger elements already in viewport on page load
  // (no scroll needed for above-fold content)
  setTimeout(() => {
    fadeEls.forEach(el => {
      const rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight && rect.bottom > 0) {
        el.classList.add('visible');
      }
    });
  }, 50);
}

// ── BOOKING FORM ─────────────────────────────────────────
const bookingForm = document.querySelector('#booking-form');
if (bookingForm) {
  bookingForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn    = bookingForm.querySelector('[type="submit"]');
    const msgEl  = bookingForm.querySelector('.form-message');
    const orig   = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Processing…';

    try {
      const res  = await fetch(bookingForm.dataset.action || 'php/book.php', {
        method: 'POST',
        body: new FormData(bookingForm),
      });
      const data = await res.json();

      if (data.success && data.redirect) {
        window.location.href = data.redirect;
      } else {
        showMsg(msgEl, data.message, data.success ? 'success' : 'error');
        btn.disabled = false;
        btn.innerHTML = orig;
      }
    } catch {
      showMsg(msgEl, 'Network error. Please try again.', 'error');
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  });
}


// ── CONTACT FORM ─────────────────────────────────────────
const contactForm = document.querySelector('#contact-form');
if (contactForm) {
  contactForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn   = contactForm.querySelector('[type="submit"]');
    const msgEl = contactForm.querySelector('.form-message');
    const orig  = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Sending…';

    try {
      const res  = await fetch('php/contact.php', { method: 'POST', body: new FormData(contactForm) });
      const raw  = await res.text();
      let data;
      try {
        data = JSON.parse(raw);
      } catch (pe) {
        console.error('[SZV Contact] Bad JSON:', raw.substring(0, 300));
        showMsg(msgEl, res.ok
          ? 'Message sent! We\'ll be in touch within 24 hours.'
          : 'Server error. Please email us at info@safarizanguven.com.', res.ok ? 'success' : 'error');
        if (res.ok) contactForm.reset();
        btn.disabled = false; btn.innerHTML = orig;
        return;
      }
      showMsg(msgEl, data.message, data.success ? 'success' : 'error');
      if (data.success) contactForm.reset();
    } catch (netErr) {
      console.error('[SZV Contact] Network error:', netErr);
      showMsg(msgEl, 'Network error. Please check your connection and try again.', 'error');
    }
    btn.disabled = false;
    btn.innerHTML = orig;
  });
}

// ── BOOKING CHECK ─────────────────────────────────────────
const checkForm = document.querySelector('#check-booking-form');
const checkResult = document.querySelector('#booking-result');
if (checkForm) {
  checkForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn  = checkForm.querySelector('[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Searching…';
    if (checkResult) checkResult.innerHTML = '';

    try {
      const refInput  = checkForm.querySelector('[name="ref"]');
      const dateInput = checkForm.querySelector('[name="date"]');
      const params = new URLSearchParams({
        ref: refInput ? refInput.value : '',
        date: dateInput ? dateInput.value : '',
      });
      const res = await fetch('php/check_booking.php?' + params);
      const raw = await res.text();
      let data;
      try {
        data = JSON.parse(raw);
      } catch (pe) {
        console.error('[SZV Track] Bad JSON:', raw.substring(0, 400));
        if (checkResult) checkResult.innerHTML =
          '<div class="alert-sz error">Server error. Please try again or ' +
          '<a href="' + (window.WHATSAPP_BASE_URL || 'https://wa.me/255663430934') +
          '?text=Hi%2C%20I%20need%20help%20tracking%20my%20booking" ' +
          'style="color:inherit;font-weight:700">WhatsApp us</a>.</div>';
        btn.disabled = false; btn.innerHTML = orig; return;
      }

      if (data.success) {
        renderBookingResult(data);
      } else {
        if (checkResult) {
          checkResult.innerHTML = `<div class="alert-sz error">${data.message}</div>`;
        }
      }
    } catch (netErr) {
      console.error('[SZV Track] Network error:', netErr);
      if (checkResult) checkResult.innerHTML = '<div class="alert-sz error">Network error. Please check your connection and try again.</div>';
    }
    btn.disabled = false;
    btn.innerHTML = orig;
  });
}

// ── RENDER BOOKING RESULT ────────────────────────────────
function renderBookingResult(data) {
  if (!checkResult) return;
  const b = data.booking;
  const statusMap = {
    pending:       ['status-pending', 'Pending Payment'],
    deposit_paid:  ['status-deposit', 'Deposit Paid'],
    fully_paid:    ['status-paid',    'Fully Paid ✓'],
    cancelled:     ['status-cancelled','Cancelled'],
  };
  const [cls, label] = statusMap[b.status] || ['status-pending', b.status];

  const rawTotal = parseFloat((b.total || '0').replace(/[^0-9.]/g, '')) || 0;
  const rawDeposit = parseFloat((b.deposit || '0').replace(/[^0-9.]/g, '')) || 0;
  const amountDue = (b.status === 'pending') ? rawDeposit : (rawTotal - rawDeposit);

  let paymentHtml = '';
  if (amountDue > 0 && b.status !== 'cancelled' && b.status !== 'fully_paid') {
    paymentHtml = `
      <div class="mt-3">
        <p style="margin:0 0 .5rem">Amount due: <strong>${b.balance_due || ('From ' + (window.CURRENCY_SYMBOL || '$') + amountDue.toFixed(2))}</strong></p>
        <div id="track-paypal-container"></div>
        <div id="track-paypal-status" style="margin-top:.5rem;color:#333;font-size:.9rem"></div>
      </div>`;
    // Load PayPal buttons if configured
    if (window.PAYPAL_CLIENT_ID) {
      // render after insertion into DOM
      setTimeout(() => loadTrackPayPal(amountDue, b.ref, b.travel_date), 50);
    }
  }


  // Status colors (inline — no CSS class dependency)
  const sColor = {pending:'#92400e',deposit_paid:'#075985',fully_paid:'#166534',cancelled:'#991b1b'};
  const sBg    = {pending:'#fef3e2',deposit_paid:'#e0f2fe',fully_paid:'#f0fdf4',cancelled:'#fef2f2'};
  const sc = sColor[b.status] || '#333';
  const sb = sBg[b.status]    || '#f5f5f5';

  const breakdownRows = b.base_amount ? `
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:.4rem 0;font-weight:500;color:#111;width:55%">Safari Package Cost</td>
      <td style="padding:.4rem 0;text-align:right">${b.base_amount}</td>
    </tr>
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:.4rem 0;font-weight:500;color:#111">Service Charge (5%)</td>
      <td style="padding:.4rem 0;text-align:right">${b.service_charge}</td>
    </tr>
    <tr style="border-bottom:2px solid #111">
      <td style="padding:.5rem 0;font-weight:600">Subtotal</td>
      <td style="padding:.5rem 0;text-align:right;font-weight:600">${b.subtotal}</td>
    </tr>
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:.4rem 0;font-weight:500;color:#111">VAT (${b.vat_rate||'18%'} Tanzania)</td>
      <td style="padding:.4rem 0;text-align:right">${b.vat_amount}</td>
    </tr>
    <tr style="background:#f7f7f7;border-bottom:2px solid #111">
      <td style="padding:.6rem .4rem;font-weight:700;font-size:1rem">Grand Total</td>
      <td style="padding:.6rem .4rem;text-align:right;font-weight:700;font-size:1rem">${b.total}</td>
    </tr>
    <tr><td colspan="2" style="padding:.25rem 0"></td></tr>
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:.4rem 0;font-weight:500">Deposit Required (50%)</td>
      <td style="padding:.4rem 0;text-align:right;font-weight:700">${b.deposit}</td>
    </tr>
    <tr>
      <td style="padding:.4rem 0;font-weight:500">Balance Due</td>
      <td style="padding:.4rem 0;text-align:right">${b.balance_due}</td>
    </tr>` : `
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:.4rem 0;font-weight:500;width:55%">Total Amount</td>
      <td style="padding:.4rem 0;text-align:right;font-weight:600">${b.total||data.total_amount||'—'}</td>
    </tr>
    <tr>
      <td style="padding:.4rem 0;font-weight:500">Deposit (50%)</td>
      <td style="padding:.4rem 0;text-align:right;font-weight:600">${b.deposit||data.deposit_amount||'—'}</td>
    </tr>`;

  checkResult.innerHTML = `
    <div style="border:1.5px solid #0a0a0a;padding:2rem">
      <p style="font-size:.6rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;margin-bottom:.65rem">Booking Found</p>
      <div style="font-family:Georgia,serif;font-size:1.9rem;font-weight:500;letter-spacing:.04em;color:#0a0a0a;margin-bottom:.5rem">${b.ref}</div>
      <div style="display:inline-block;padding:.2rem .65rem;font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:${sb};color:${sc};border:1px solid ${sc};margin-bottom:1.25rem">${label}</div>

      <p style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin:.75rem 0 .6rem">Summary</p>
      <table style="width:100%;font-size:.85rem;border-collapse:collapse;margin-bottom:1.25rem">
        <tr style="border-bottom:1px solid #eee"><td style="padding:.4rem 0;font-weight:500;color:#111;width:40%">Guest</td><td style="font-weight:600">${b.name}</td></tr>
        <tr style="border-bottom:1px solid #eee"><td style="padding:.4rem 0;font-weight:500;color:#111">Package</td><td>${b.package}</td></tr>
        <tr style="border-bottom:1px solid #eee"><td style="padding:.4rem 0;font-weight:500;color:#111">Accommodation</td><td>${b.accommodation_level||'—'}</td></tr>
        <tr style="border-bottom:1px solid #eee"><td style="padding:.4rem 0;font-weight:500;color:#111">Travelers</td><td>${b.travelers}</td></tr>
        <tr style="border-bottom:1px solid #eee"><td style="padding:.4rem 0;font-weight:500;color:#111">Travel Date</td><td style="font-weight:600">${b.travel_date}</td></tr>
        <tr><td style="padding:.4rem 0;font-weight:500;color:#111">Booked On</td><td>${b.created_at}</td></tr>
      </table>

      <p style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin:.75rem 0 .6rem">Cost Breakdown</p>
      <table style="width:100%;font-size:.85rem;border-collapse:collapse;margin-bottom:1.25rem">
        ${breakdownRows}
      </table>

      ${paymentHtml}

      <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.5rem">
        <a href="${data.whatsapp}" target="_blank" style="display:inline-block;padding:.65rem 1.4rem;background:#0a0a0a;color:#fff;font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;text-decoration:none">WhatsApp Us</a>
        <button onclick="window.print()" style="padding:.65rem 1.4rem;background:#fff;color:#0a0a0a;border:1.5px solid #0a0a0a;font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;cursor:pointer">Print / Save</button>
      </div>
    </div>`;


}

// ── HELPER ───────────────────────────────────────────────
function showMsg(el, text, type) {
  if (!el) return;
  el.innerHTML = `<div class="alert-sz ${type}">${text}</div>`;
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── SMOOTH ANCHOR LINKS ──────────────────────────────────
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', (e) => {
    const href = a.getAttribute('href');
    if (!href || href === '#') return;
    try {
      const target = document.querySelector(href);
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    } catch(err) { /* invalid selector, skip */ }
  });
});

function loadTrackPayPal(amount, bookingRef, bookingDate) {
  if (!window.PAYPAL_CLIENT_ID) return;
  const script = document.createElement('script');
  script.src = `https://www.paypal.com/sdk/js?client-id=${window.PAYPAL_CLIENT_ID}&currency=${window.PAYPAL_CURRENCY}&intent=capture`;
  script.onload = () => {
    paypal.Buttons({
      style: { layout: 'vertical', color: 'black', shape: 'rect', label: 'paypal' },
      createOrder: (data, actions) => actions.order.create({
        purchase_units: [{
          amount: { value: amount.toFixed(2), currency_code: window.PAYPAL_CURRENCY },
          description: 'Safari Zangu Ventures — Booking ' + bookingRef
        }]
      }),
      onApprove: (data, actions) => actions.order.capture().then(details => {
        const statusEl = document.getElementById('track-paypal-status');
        statusEl.textContent = 'Payment received — updating your booking…';
        statusEl.style.color = '#1a6b1a';

        const fd = new FormData();
        fd.append('ref', bookingRef);
        fd.append('travel_date', bookingDate);
        fd.append('paypal_order_id', details.id);
        fd.append('amount_paid', amount.toFixed(2));

        fetch('php/record_payment.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(res => {
            statusEl.textContent = res.success
              ? 'Payment confirmed! Refresh to see your updated status.'
              : 'Payment captured, but updating your booking failed — please contact us with your reference.';
          });
      }),
      onError: (err) => {
        document.getElementById('track-paypal-status').textContent = 'PayPal payment failed. Please try again.';
        document.getElementById('track-paypal-status').style.color = '#cc2222';
        console.error('PayPal error:', err);
      }
    }).render('#track-paypal-container');
  };
  document.body.appendChild(script);
}


// ── CURRENCY SWITCHER ──────────────────────────────────────
(function() {
  const RATES = { USD: 1, EUR: 0.92, GBP: 0.79 };
  const SYMBOLS = { USD: '$', EUR: '€', GBP: '£' };
  let current = localStorage.getItem('szv_currency') || 'USD';

  function convertPrices(currency) {
    const rate = RATES[currency] || 1;
    const sym  = SYMBOLS[currency];
    document.querySelectorAll('[data-price-usd]').forEach(el => {
      const usd = parseFloat(el.dataset.priceUsd);
      el.textContent = 'From ' + sym + Math.round(usd * rate).toLocaleString();
    });
    document.querySelectorAll('.currency-switcher button').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.currency === currency);
    });
    localStorage.setItem('szv_currency', currency);
    current = currency;
  }

  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.currency-switcher button').forEach(btn => {
      btn.addEventListener('click', function() { convertPrices(this.dataset.currency); });
    });
    convertPrices(current);
  });
})();


/* ── PACKAGE GALLERY SLIDER (desktop grid / mobile slider) ── */
document.addEventListener('DOMContentLoaded', function () {

  /* ── pkg-gallery slider ─────────────────────────────────── */
  document.querySelectorAll('.pkg-gallery').forEach(function (gallery) {
    var imgs    = Array.from(gallery.querySelectorAll('img'));
    var prevBtn = gallery.querySelector('.pkg-prev, .pkg-slider-prev');
    var nextBtn = gallery.querySelector('.pkg-next, .pkg-slider-next');
    if (!imgs.length) return;

    var current = 0;

    function show(i) {
      imgs.forEach(function (img) {
        img.classList.remove('active');
        img.style.opacity = '0';
      });
      imgs[i].classList.add('active');
      imgs[i].style.opacity = '1';
      current = i;
    }

    show(0);
    if (imgs.length < 2) return;

    if (prevBtn) prevBtn.addEventListener('click', function (e) {
      e.preventDefault();
      show((current - 1 + imgs.length) % imgs.length);
    });
    if (nextBtn) nextBtn.addEventListener('click', function (e) {
      e.preventDefault();
      show((current + 1) % imgs.length);
    });

    // Touch swipe
    var startX = 0;
    gallery.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    gallery.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 50) show(dx < 0 ? (current+1) % imgs.length : (current-1+imgs.length) % imgs.length);
    });
  });

  /* ── home-slider: auto-advance + swipe ─────────────────── */
  document.querySelectorAll('.home-slider').forEach(function (slider) {
    var imgs    = Array.from(slider.querySelectorAll('img'));
    var prevBtn = slider.querySelector('.home-prev');
    var nextBtn = slider.querySelector('.home-next');
    if (!imgs.length) return;

    var current = 0;

    function show(i) {
      imgs.forEach(function (img) {
        img.classList.remove('active');
        img.style.opacity = '0';
      });
      imgs[i].classList.add('active');
      imgs[i].style.opacity = '1';
      current = i;
    }

    show(0);
    if (imgs.length < 2) return;

    var timer = setInterval(function () { show((current+1) % imgs.length); }, 4000);
    slider.addEventListener('mouseenter', function () { clearInterval(timer); });
    slider.addEventListener('mouseleave', function () {
      timer = setInterval(function () { show((current+1) % imgs.length); }, 4000);
    });

    if (prevBtn) prevBtn.addEventListener('click', function () { show((current-1+imgs.length)%imgs.length); });
    if (nextBtn) nextBtn.addEventListener('click', function () { show((current+1)%imgs.length); });

    var startX = 0;
    slider.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, {passive:true});
    slider.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 50) show(dx < 0 ? (current+1)%imgs.length : (current-1+imgs.length)%imgs.length);
    });
  });

});

/* ── Form double-submit prevention ─────────────────────────── */
(function(){
  document.addEventListener('submit',function(e){
    var form=e.target;
    if(form.id==='booking-form'||form.id==='contact-form'||
       form.id==='custom-safari-form'||form.id==='check-booking-form')return;
    if(form.dataset.submitting){e.preventDefault();return;}
    var btn=form.querySelector('[type="submit"]');
    if(btn&&!btn.dataset.noLock){
      form.dataset.submitting='1';
      var orig=btn.innerHTML;
      btn.disabled=true;
      btn.innerHTML='<span style="opacity:.7">Sending\u2026</span>';
      setTimeout(function(){
        delete form.dataset.submitting;
        btn.disabled=false;btn.innerHTML=orig;
      },15000);
    }
  });
}());

/* ── WhatsApp float button ──────────────────────────────────── */
(function(){
  var btn=document.getElementById('wa-float');
  if(!btn)return;
  setTimeout(function(){btn.classList.add('wa-float--visible');},3000);
  window.addEventListener('scroll',function(){
    if(window.scrollY>300)btn.classList.add('wa-float--visible');
    else btn.classList.remove('wa-float--visible');
  },{passive:true});
}());


/* ── Form double-submit prevention ─────────────────────────── */
(function(){
  'use strict';
  document.addEventListener('submit',function(e){
    var form=e.target;
    // Skip forms that handle their own async submission
    if(form.id==='booking-form'||form.id==='contact-form'||
       form.id==='custom-safari-form'||form.id==='check-booking-form')return;
    if(form.dataset.submitting){e.preventDefault();return;}
    var btn=form.querySelector('[type="submit"]');
    if(btn&&!btn.dataset.noLock){
      form.dataset.submitting='1';
      var orig=btn.innerHTML;
      btn.disabled=true;
      btn.innerHTML='<span style="opacity:.7">Sending…</span>';
      setTimeout(function(){
        delete form.dataset.submitting;
        btn.disabled=false;
        btn.innerHTML=orig;
      },15000);
    }
  });
}());
/* ── Form double-submit prevention ─────────────────────────── */
(function(){
  'use strict';
  document.addEventListener('submit',function(e){
    var form=e.target;
    if(form.id==='booking-form'||form.id==='contact-form'||
       form.id==='custom-safari-form'||form.id==='check-booking-form')return;
    if(form.dataset.submitting){e.preventDefault();return;}
    var btn=form.querySelector('[type="submit"]');
    if(btn&&!btn.dataset.noLock){
      form.dataset.submitting='1';
      var orig=btn.innerHTML;
      btn.disabled=true;
      btn.innerHTML='Sending\u2026';
      setTimeout(function(){
        delete form.dataset.submitting;
        btn.disabled=false;
        btn.innerHTML=orig;
      },15000);
    }
  });
}());

/* ── Form double-submit prevention ─────────────────────────── */
(function () {
  'use strict';
  document.addEventListener('submit', function (e) {
    var form = e.target;
    // Skip forms with their own async handlers
    var skipIds = ['booking-form','contact-form','custom-safari-form','check-booking-form'];
    if (skipIds.indexOf(form.id) !== -1) return;
    if (form.dataset.submitting) { e.preventDefault(); return; }
    var btn = form.querySelector('[type="submit"]');
    if (btn && !btn.dataset.noLock) {
      form.dataset.submitting = '1';
      var orig = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = 'Sending…';
      setTimeout(function () {
        delete form.dataset.submitting;
        btn.disabled = false;
        btn.innerHTML = orig;
      }, 15000);
    }
  });
}());