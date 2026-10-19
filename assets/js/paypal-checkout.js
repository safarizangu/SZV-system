/* Safari Zangu Ventures — PayPal checkout button.
 * Shared by views/track.php and views/confirmation.php. The order amount
 * is never decided here — it's computed server-side from the booking
 * record by api/paypal_create_order.php, and the completed payment is
 * independently re-verified against PayPal's own API by
 * api/record_payment.php before anything is credited. */
window.SZVPayPal = (function () {
  'use strict';
  var sdkPromise = null;

  function loadSdk() {
    if (sdkPromise) return sdkPromise;
    sdkPromise = new Promise(function (resolve, reject) {
      if (window.paypal) return resolve(window.paypal);
      if (!window.PAYPAL_CLIENT_ID) return reject(new Error('PayPal not configured'));
      var s = document.createElement('script');
      s.src = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(window.PAYPAL_CLIENT_ID)
            + '&currency=' + encodeURIComponent(window.PAYPAL_CURRENCY || 'USD')
            + '&intent=capture';
      s.onload = function () { window.paypal ? resolve(window.paypal) : reject(new Error('SDK failed to load')); };
      s.onerror = function () { reject(new Error('SDK failed to load')); };
      document.head.appendChild(s);
    });
    return sdkPromise;
  }

  /**
   * opts: { ref, travelDate, password, csrfToken, onSuccess(data), onError(message) }
   * Pass travelDate (confirmation.php, same-session trust) or password
   * (track.php, a separate later visit) — whichever proves ownership here.
   */
  function render(containerId, opts) {
    var el = document.getElementById(containerId);
    if (!el) return;

    if (!window.PAYPAL_CLIENT_ID) {
      el.innerHTML = '<p style="font-size:.78rem;color:#6b7280;line-height:1.6">Online card/PayPal payment isn\'t set up yet — please use bank transfer, or WhatsApp us to pay another way.</p>';
      return;
    }

    el.innerHTML = '<p style="font-size:.78rem;color:#6b7280">Loading payment options…</p>';

    loadSdk().then(function (paypal) {
      el.innerHTML = '';
      paypal.Buttons({
        style: { layout: 'vertical', color: 'black', shape: 'rect', label: 'pay' },

        createOrder: function () {
          var fd = new FormData();
          fd.append('ref', opts.ref);
          if (opts.travelDate) fd.append('travel_date', opts.travelDate);
          if (opts.password)   fd.append('password', opts.password);
          fd.append('_csrf', opts.csrfToken);
          return fetch('api/paypal_create_order.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
              if (!d.success) throw new Error(d.message || 'Could not start checkout.');
              return d.order_id;
            });
        },

        onApprove: function (data, actions) {
          return actions.order.capture().then(function () {
            var fd = new FormData();
            fd.append('ref', opts.ref);
            if (opts.travelDate) fd.append('travel_date', opts.travelDate);
            if (opts.password)   fd.append('password', opts.password);
            fd.append('paypal_order_id', data.orderID);
            fd.append('_csrf', opts.csrfToken);
            return fetch('api/record_payment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
              .then(function (r) { return r.json(); })
              .then(function (d) {
                if (d.success) { if (opts.onSuccess) opts.onSuccess(d); }
                else { if (opts.onError) opts.onError(d.message || 'Payment could not be recorded.'); }
              });
          });
        },

        onError: function (err) {
          if (opts.onError) opts.onError('PayPal error — please try again or contact us.');
        },
      }).render('#' + containerId);
    }).catch(function () {
      el.innerHTML = '<p style="font-size:.78rem;color:#991b1b;line-height:1.6">Payment temporarily unavailable — please use bank transfer, or WhatsApp us to pay another way.</p>';
    });
  }

  return { render: render };
})();
