/**
 * Safari Zangu Ventures — Admin Form Validator
 */
(function () {
  'use strict';

  function getWrap(el) { return el.closest('.admin-field') || el.closest('div') || el.parentElement; }

  function setError(el, msg) {
    el.style.borderColor = '#dc2626';
    el.style.backgroundColor = '#fef2f2';
    var wrap = getWrap(el);
    var err  = wrap.querySelector('.av-error');
    if (!err) {
      err = document.createElement('p');
      err.className = 'av-error';
      err.style.cssText = 'color:#dc2626;font-size:.68rem;font-weight:600;margin-top:.3rem;letter-spacing:.02em';
      wrap.appendChild(err);
    }
    err.textContent = '⚠ ' + msg;
  }

  function setOk(el) {
    el.style.borderColor = '#16a34a';
    el.style.backgroundColor = '#f0fdf4';
    var wrap = getWrap(el);
    var err  = wrap.querySelector('.av-error');
    if (err) err.remove();
  }

  function clearEl(el) {
    el.style.borderColor = '';
    el.style.backgroundColor = '';
    var wrap = getWrap(el);
    var err  = wrap.querySelector('.av-error');
    if (err) err.remove();
  }

  function validateEl(el) {
    var v     = el.value.trim();
    var rules = (el.dataset.av || '').split(' ').filter(Boolean);
    var valid = true;

    for (var i = 0; i < rules.length; i++) {
      var parts = rules[i].split(':');
      var rule  = parts[0];
      var param = parts[1];

      if (rule === 'required' && v === '') { setError(el, 'Required'); valid=false; break; }
      if (rule === 'email'    && v && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) { setError(el, 'Invalid email'); valid=false; break; }
      if (rule === 'min'      && parseFloat(v) < parseFloat(param)) { setError(el, 'Min: '+param); valid=false; break; }
      if (rule === 'max'      && parseFloat(v) > parseFloat(param)) { setError(el, 'Max: '+param); valid=false; break; }
      if (rule === 'minlen'   && v.length < parseInt(param)) { setError(el, 'Min '+param+' chars'); valid=false; break; }
      if (rule === 'positive' && parseFloat(v) <= 0) { setError(el, 'Must be positive'); valid=false; break; }
      if (rule === 'slug'     && v && !/^[a-z0-9-]+$/.test(v)) { setError(el, 'Only lowercase letters, numbers, hyphens'); valid=false; break; }
      if (rule === 'url'      && v && !/^https?:\/\/.+/.test(v)) { setError(el, 'Must start with https://'); valid=false; break; }
      if (rule === 'integer'  && v && !/^\d+$/.test(v)) { setError(el, 'Must be a whole number'); valid=false; break; }
    }
    if (valid && v !== '') setOk(el);
    else if (valid && v === '') clearEl(el);
    return valid;
  }

  function validateForm(form) {
    var fields  = form.querySelectorAll('[data-av]');
    var isValid = true;
    var first   = null;
    fields.forEach(function(el) {
      if (!validateEl(el)) { isValid=false; if(!first)first=el; }
    });
    if (first) { first.focus(); first.scrollIntoView({behavior:'smooth',block:'center'}); }
    return isValid;
  }

  document.addEventListener('DOMContentLoaded', function() {
    // Attach listeners to all fields with data-av
    document.querySelectorAll('[data-av]').forEach(function(el) {
      el.addEventListener('blur',   function() { if(el.value.trim()) validateEl(el); });
      el.addEventListener('input',  function() { if(el.style.borderColor==='rgb(220, 38, 38)') validateEl(el); });
      el.addEventListener('change', function() { validateEl(el); });
    });

    // Block submit on invalid
    document.querySelectorAll('form.av-form').forEach(function(form) {
      form.addEventListener('submit', function(e) {
        if (!validateForm(form)) { e.preventDefault(); }
      });
    });
  });

  window.AVValidator = { validate: validateForm, validateField: validateEl };
})();
