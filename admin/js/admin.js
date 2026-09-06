/**
 * Safari Zangu Ventures — Admin Shared Helpers
 *
 * Loaded by layouts/admin.php on every admin page (it was referenced
 * there but never actually existed — a silent 404 on every page load).
 * Holds the small pieces of behavior that individual admin/*.php pages
 * were each re-implementing slightly differently: a toast() notification
 * function, and open/close wiring for the .admin-modal-overlay component
 * in admin/css/admin.css. A page can still define its own toast()/
 * openModal()/closeModal() if it needs custom behavior — a later
 * function declaration on the same page simply shadows these.
 */
(function () {
  'use strict';

  // ── Toast notifications ─────────────────────────────────────
  // Uses .toast-container / .toast / .toast--ok / .toast--err from
  // admin.css. Creates the container on first use if the page didn't
  // already add one (most pages that want toasts include
  // <div class="toast-container"></div> once in their markup).
  function toastContainer() {
    var el = document.querySelector('.toast-container');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast-container';
      document.body.appendChild(el);
    }
    return el;
  }

  window.toast = window.toast || function (msg, type) {
    var t = document.createElement('div');
    t.className = 'toast' + (type === 'ok' ? ' toast--ok' : type === 'err' ? ' toast--err' : '');
    t.textContent = msg;
    toastContainer().appendChild(t);
    setTimeout(function () { t.remove(); }, 3500);
  };

  // ── Modal open/close ─────────────────────────────────────────
  // Toggles the 'open' class on an .admin-modal-overlay by id. Also
  // wires every .admin-modal-overlay already in the page (event
  // delegation, so modals added dynamically later still work) so that
  // clicking the backdrop or pressing Escape closes whichever modal is
  // currently open — a page doesn't have to wire that itself.
  window.openModal = window.openModal || function (id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('open');
  };
  window.closeModal = window.closeModal || function (id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('open');
  };

  document.addEventListener('click', function (e) {
    if (e.target.classList && e.target.classList.contains('admin-modal-overlay') && e.target.classList.contains('open')) {
      e.target.classList.remove('open');
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.admin-modal-overlay.open').forEach(function (el) {
      el.classList.remove('open');
    });
  });
})();
