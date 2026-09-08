/* Safari Zangu Ventures — Analytics Tracker */
(function () {
  'use strict';

  function trackEvent(eventName, packageSlug) {
    packageSlug = packageSlug || '';
    var body = 'event='    + encodeURIComponent(eventName)
             + '&page='   + encodeURIComponent(window.location.pathname)
             + '&package='+ encodeURIComponent(packageSlug)
             + '&ref='    + encodeURIComponent(document.referrer || '');

    // Use sendBeacon for page-unload events (reliable), fetch for others
    if (navigator.sendBeacon) {
      var blob = new Blob([body], { type: 'application/x-www-form-urlencoded' });
      navigator.sendBeacon('api/analytics.php', blob);
    } else {
      fetch('api/analytics.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body,
        keepalive: true,
      }).catch(function () {});
    }
  }

  document.addEventListener('DOMContentLoaded', function () {

    // ── Page view ────────────────────────────────────────────
    var path   = window.location.pathname;
    var isHome = path === '/' || path.endsWith('index.php') || path.endsWith((window.BASE_PATH||'')+'/');
    var evtName = isHome                    ? 'home_page_view'
                : path.includes('package') ? 'packages_page_view'
                : path.includes('book')    ? 'book_page_view'
                : path.includes('track')   ? 'track_page_view'
                : path.includes('about')   ? 'about_page_view'
                : path.includes('contact') ? 'contact_page_view'
                : 'page_view';
    trackEvent(evtName);

    // ── Package page: individual package views ─────────────────
    if (path.includes('packages')) {
      document.querySelectorAll('.pkg-card[data-slug], [data-slug]').forEach(function (el) {
        var observer = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              trackEvent('package_view', el.dataset.slug);
              observer.unobserve(el);
            }
          });
        }, { threshold: 0.5 });
        observer.observe(el);
      });
    }

    // ── Book Now buttons ─────────────────────────────────────
    document.querySelectorAll('.sz-nav-cta, [href*="book.php"], .btn-book').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var slug = btn.dataset.slug || btn.dataset.package || '';
        trackEvent('book_now_click', slug);
      });
    });

    // ── WhatsApp clicks ──────────────────────────────────────
    document.querySelectorAll('a[href*="wa.me"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var slug = btn.dataset.slug || '';
        trackEvent('whatsapp_click', slug);
      });
    });

    // ── Custom safari form ───────────────────────────────────
    var customForm = document.getElementById('custom-safari-form');
    if (customForm) {
      customForm.addEventListener('submit', function () {
        trackEvent('custom_safari_submission');
      });
    }

    // ── Contact form ─────────────────────────────────────────
    var contactForm = document.getElementById('contact-form');
    if (contactForm) {
      contactForm.addEventListener('submit', function () {
        trackEvent('contact_form_submission');
      });
    }

    // ── Booking form ─────────────────────────────────────────
    var bookForm = document.getElementById('booking-form');
    if (bookForm) {
      bookForm.addEventListener('submit', function () {
        var pkgSelect = document.getElementById('package-select');
        var slug = pkgSelect ? pkgSelect.options[pkgSelect.selectedIndex].dataset.slug || '' : '';
        trackEvent('booking_form_submit', slug);
      });
    }

    // ── Scroll depth ─────────────────────────────────────────
    var scrollDepths = [25, 50, 75, 100];
    var tracked = {};
    window.addEventListener('scroll', function () {
      var scrolled = Math.round(
        ((window.scrollY + window.innerHeight) / document.documentElement.scrollHeight) * 100
      );
      scrollDepths.forEach(function (depth) {
        if (scrolled >= depth && !tracked[depth]) {
          tracked[depth] = true;
          trackEvent('scroll_' + depth + '_pct');
        }
      });
    }, { passive: true });

    // ── Time on page ─────────────────────────────────────────
    var start    = Date.now();
    var timeFired= {};
    var timeMark = [30, 60, 120]; // seconds
    setInterval(function () {
      var secs = Math.floor((Date.now() - start) / 1000);
      timeMark.forEach(function (t) {
        if (secs >= t && !timeFired[t]) {
          timeFired[t] = true;
          trackEvent('time_on_page_' + t + 's');
        }
      });
    }, 5000);

    // ── Outbound links ───────────────────────────────────────
    document.querySelectorAll('a[href]').forEach(function (a) {
      if (a.hostname && a.hostname !== window.location.hostname) {
        a.addEventListener('click', function () {
          trackEvent('outbound_click');
        });
      }
    });

  });
})();
