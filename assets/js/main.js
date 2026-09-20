/* ============================================================
   SAFARI ZANGU VENTURES — Main JavaScript
   ============================================================ */

'use strict';

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
    document.body.classList.toggle('nav-open');
  };
  navToggle.addEventListener('click', openNav);

  const closeNav = () => {
    mobileNav.classList.remove('open');
    navToggle.classList.remove('is-open');
    navToggle.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('nav-open');
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
      const res  = await fetch(bookingForm.dataset.action || 'api/book.php', {
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
      const res  = await fetch(contactForm.dataset.action || 'api/contact.php', { method: 'POST', body: new FormData(contactForm) });
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

  /* ── home-slider: manual navigation + swipe (no auto-advance) ── */
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

/* ── Country autocomplete (type-ahead suggestions) ───────────
   A free-text field with live suggestions rather than a long native
   <select> a UK/US/AU/etc. visitor has to scroll through. Reuses the
   COUNTRY_DIAL_CODES list already loaded for the phone code picker —
   one country dataset for the whole site, not a second copy. */
(function () {
  'use strict';

  function initCountryAutocomplete(inputId, listId) {
    var input = document.getElementById(inputId);
    var list  = document.getElementById(listId);
    if (!input || !list || typeof COUNTRY_DIAL_CODES === 'undefined') return;

    var names = COUNTRY_DIAL_CODES.map(function (c) { return c.name; });
    var activeIndex = -1;
    var currentMatches = [];

    function close() {
      list.hidden = true;
      list.innerHTML = '';
      input.setAttribute('aria-expanded', 'false');
      activeIndex = -1;
      currentMatches = [];
    }

    function highlight(name, query) {
      var i = name.toLowerCase().indexOf(query.toLowerCase());
      if (i === -1) return name;
      return name.slice(0, i) + '<mark>' + name.slice(i, i + query.length) + '</mark>' + name.slice(i + query.length);
    }

    function render(matches, query) {
      currentMatches = matches;
      activeIndex = -1;
      if (!matches.length) { close(); return; }
      list.innerHTML = matches.map(function (name, i) {
        return '<div class="autocomplete-item" role="option" id="' + inputId + '-opt-' + i + '" data-value="' + name.replace(/"/g, '&quot;') + '">' + highlight(name, query) + '</div>';
      }).join('');
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }

    function updateActive() {
      var items = list.querySelectorAll('.autocomplete-item');
      items.forEach(function (el, i) { el.classList.toggle('active', i === activeIndex); });
      if (activeIndex >= 0 && items[activeIndex]) {
        items[activeIndex].scrollIntoView({ block: 'nearest' });
        input.setAttribute('aria-activedescendant', items[activeIndex].id);
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    }

    input.addEventListener('input', function () {
      var q = input.value.trim();
      if (!q) { close(); return; }
      var starts = names.filter(function (n) { return n.toLowerCase().indexOf(q.toLowerCase()) === 0; });
      var contains = names.filter(function (n) {
        return n.toLowerCase().indexOf(q.toLowerCase()) > 0;
      });
      render(starts.concat(contains).slice(0, 8), q);
    });

    input.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, currentMatches.length - 1);
        updateActive();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
        updateActive();
      } else if (e.key === 'Enter') {
        if (activeIndex >= 0) {
          e.preventDefault();
          input.value = currentMatches[activeIndex];
          close();
        }
      } else if (e.key === 'Escape') {
        close();
      }
    });

    list.addEventListener('mousedown', function (e) {
      var item = e.target.closest('.autocomplete-item');
      if (!item) return;
      e.preventDefault(); // keep focus in the input, fires before blur
      input.value = item.getAttribute('data-value');
      close();
    });

    input.addEventListener('blur', function () {
      setTimeout(close, 120); // let a pending mousedown selection land first
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initCountryAutocomplete('cs-country', 'cs-country-suggestions');
  });
}());