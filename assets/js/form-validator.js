/**
 * Safari Zangu Ventures — Public Form Validator
 * Unified real-time validation for all public forms.
 */
(function () {
  'use strict';

  // ── Validation rules ──────────────────────────────────────
  var RULES = {
    required:  function (v) { return v.trim() !== ''; },
    email:     function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()); },
    phone:     function (v) { return v.trim() === '' || /^[+\d][\d\s\-().]{6,20}$/.test(v.trim()); },
    minlen:    function (v, p) { return v.trim().length >= parseInt(p); },
    maxlen:    function (v, p) { return v.trim().length <= parseInt(p); },
    min:       function (v, p) { return parseFloat(v) >= parseFloat(p); },
    max:       function (v, p) { return parseFloat(v) <= parseFloat(p); },
    future:    function (v) {
      if (!v) return false;
      var d = new Date(v); var t = new Date(); t.setHours(0,0,0,0);
      return d >= t;
    },
    name:      function (v) { return /^[a-zA-ZÀ-ÿ\s'\-]{2,80}$/.test(v.trim()); },
    nonempty:  function (v) { return v !== '' && v !== '0' && v !== undefined; },
    positive:  function (v) { return parseFloat(v) > 0; },
    integer:   function (v) { return /^\d+$/.test(v.trim()); },
  };

  var MESSAGES = {
    required:  'This field is required',
    email:     'Please enter a valid email address',
    phone:     'Please enter a valid phone number',
    minlen:    function (p) { return 'Must be at least ' + p + ' characters'; },
    maxlen:    function (p) { return 'Must be no more than ' + p + ' characters'; },
    min:       function (p) { return 'Minimum value is ' + p; },
    max:       function (p) { return 'Maximum value is ' + p; },
    future:    'Please select a future date',
    name:      'Please enter a valid name (letters only)',
    nonempty:  'Please make a selection',
    positive:  'Please enter a positive number',
    integer:   'Please enter a whole number',
  };

  // ── DOM helpers ────────────────────────────────────────────
  function getWrapper(el) {
    return el.closest('.sz-field') || el.closest('.form-group') || el.parentElement;
  }

  function showError(el, msg) {
    var wrap = getWrapper(el);
    el.classList.add('sz-invalid');
    el.classList.remove('sz-valid');
    el.setAttribute('aria-invalid', 'true');
    var err = wrap.querySelector('.sz-error');
    if (!err) {
      err = document.createElement('span');
      err.className = 'sz-error';
      err.setAttribute('role', 'alert');
      wrap.appendChild(err);
    }
    err.textContent = msg;
    err.style.display = 'block';
  }

  function showSuccess(el) {
    var wrap = getWrapper(el);
    el.classList.remove('sz-invalid');
    el.classList.add('sz-valid');
    el.removeAttribute('aria-invalid');
    var err = wrap.querySelector('.sz-error');
    if (err) err.style.display = 'none';
  }

  function clearState(el) {
    el.classList.remove('sz-invalid', 'sz-valid');
    var wrap = getWrapper(el);
    var err = wrap && wrap.querySelector('.sz-error');
    if (err) err.style.display = 'none';
  }

  // ── Validate a single field ────────────────────────────────
  function validateField(el) {
    var rules  = (el.dataset.validate || '').split(' ').filter(Boolean);
    var val    = el.value;
    var label  = el.dataset.label || el.getAttribute('placeholder') || 'This field';

    // Skip hidden or disabled fields
    if (el.type === 'hidden' || el.disabled) return true;

    for (var i = 0; i < rules.length; i++) {
      var parts  = rules[i].split(':');
      var rule   = parts[0];
      var param  = parts[1];
      var fn     = RULES[rule];
      if (!fn) continue;
      var pass = fn(val, param);
      if (!pass) {
        var msg = MESSAGES[rule];
        if (typeof msg === 'function') msg = msg(param);
        msg = msg.replace('This field', label);
        showError(el, msg);
        return false;
      }
    }
    // All rules passed
    if (rules.length > 0 && val.trim() !== '') showSuccess(el);
    else clearState(el);
    return true;
  }

  // ── Validate entire form ───────────────────────────────────
  function validateForm(form) {
    var fields = form.querySelectorAll('[data-validate]');
    var valid  = true;
    var first  = null;
    fields.forEach(function (el) {
      if (!validateField(el)) {
        valid = false;
        if (!first) first = el;
      }
    });
    if (first) {
      first.focus();
      first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return valid;
  }

  // ── Attach real-time listeners ─────────────────────────────
  function attachListeners(form) {
    var fields = form.querySelectorAll('[data-validate]');
    fields.forEach(function (el) {
      // Validate on blur (after leaving field)
      el.addEventListener('blur', function () {
        if (el.value.trim() !== '' || el.classList.contains('sz-invalid')) {
          validateField(el);
        }
      });
      // Clear error on input (while typing)
      el.addEventListener('input', function () {
        if (el.classList.contains('sz-invalid')) validateField(el);
      });
      // Select/date: validate on change immediately
      if (el.tagName === 'SELECT' || el.type === 'date' || el.type === 'number') {
        el.addEventListener('change', function () { validateField(el); });
      }
    });
  }

  // ── Character counters ─────────────────────────────────────
  function setupCounters(form) {
    form.querySelectorAll('[data-maxchars]').forEach(function (el) {
      var max     = parseInt(el.dataset.maxchars);
      var counter = document.createElement('span');
      counter.className = 'sz-char-count';
      counter.textContent = '0 / ' + max;
      getWrapper(el).appendChild(counter);
      el.addEventListener('input', function () {
        var len = el.value.length;
        counter.textContent = len + ' / ' + max;
        counter.style.color = len > max * 0.9 ? '#dc2626' : '#9ca3af';
      });
    });
  }

  // ── Phone auto-format ──────────────────────────────────────
  function setupPhoneFormat(form) {
    form.querySelectorAll('input[type="tel"]').forEach(function (el) {
      el.addEventListener('input', function () {
        // Allow + at start, digits, spaces, hyphens, parentheses
        var cleaned = el.value.replace(/[^\d+\s\-().]/g, '');
        if (el.value !== cleaned) el.value = cleaned;
      });
    });
  }

  // ── Submit handler ─────────────────────────────────────────
  function setupSubmit(form) {
    form.addEventListener('submit', function (e) {
      if (!validateForm(form)) {
        e.preventDefault();
        e.stopPropagation();
        return false;
      }
    }, true);
  }

  // ── Init ───────────────────────────────────────────────────
  function init() {
    document.querySelectorAll('form[data-validate-form]').forEach(function (form) {
      attachListeners(form);
      setupCounters(form);
      setupPhoneFormat(form);
      setupSubmit(form);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Expose for external use
  window.SZValidator = { validateForm: validateForm, validateField: validateField };
})();
