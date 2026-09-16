/* Safari Zangu Ventures — Admin gallery image picker.
 * Shared by accommodations.php, vehicles.php and activities.php — the
 * same "pick from the uploaded gallery" widget package_edit.php already
 * uses, extracted here instead of copy-pasted three more times.
 *
 * Usage: one call per page, right after the elements it references exist
 * in the DOM (the form's hidden input, preview grid, and picker modal —
 * see any of the three admin pages above for the expected markup shape).
 *
 *   var picker = initAdminGalleryPicker({
 *     inputId:  'images_json_input',
 *     previewId: 'selected-gallery',
 *     modalId:  'galleryPickerModal',
 *     tabsId:   'picker-tabs',
 *     gridId:   'picker-grid',
 *     searchId: 'picker-search',
 *     countId:  'picker-selected-count',
 *     basePath: window.BASE_PATH || '',
 *   });
 *   // then: picker.open(), picker.filter(value), picker.apply(), picker.removeItem(btn)
 */
function initAdminGalleryPicker(opts) {
  var inputEl    = document.getElementById(opts.inputId);
  var basePath   = opts.basePath || '';
  var allImages  = [];
  var selected   = new Set((inputEl.value || '').split(',').filter(Boolean));
  var curFolder  = 'all';
  var curSearch  = '';

  function el(id) { return document.getElementById(id); }

  async function loadImages() {
    try {
      var r = await fetch(basePath + '/api/gallery/list.php', { credentials: 'same-origin' });
      allImages = await r.json();
    } catch (e) { allImages = []; }
  }

  async function open() {
    el(opts.modalId).style.display = 'flex';
    if (allImages.length === 0) await loadImages();
    curFolder = 'all';
    curSearch = '';
    el(opts.searchId).value = '';
    renderTabs();
    renderGrid();
  }

  function close() { el(opts.modalId).style.display = 'none'; }

  function renderTabs() {
    var tabs = el(opts.tabsId);
    var folders = [...new Set(allImages.map(function (img) { return img.folder; }))].sort();
    tabs.innerHTML = '';
    ['all'].concat(folders).forEach(function (f) {
      var tab = document.createElement('button');
      tab.type = 'button';
      tab.textContent = f === 'all' ? 'All' : f;
      var active = f === curFolder;
      tab.style.cssText = 'padding:.5rem 1rem;font-size:.7rem;font-weight:600;background:none;border:none;'
        + 'border-bottom:2px solid ' + (active ? '#0a0a0a' : 'transparent') + ';cursor:pointer;white-space:nowrap;'
        + 'letter-spacing:.06em;text-transform:uppercase;color:' + (active ? '#0a0a0a' : '#9ca3af');
      tab.onclick = function () { curFolder = f; renderTabs(); renderGrid(); };
      tabs.appendChild(tab);
    });
  }

  function renderGrid() {
    var grid   = el(opts.gridId);
    var filter = curSearch.toLowerCase();
    var images = curFolder === 'all' ? allImages : allImages.filter(function (img) { return img.folder === curFolder; });
    var filtered = filter ? images.filter(function (img) { return img.url.toLowerCase().includes(filter); }) : images;

    grid.innerHTML = '';
    filtered.forEach(function (img) {
      var isSelected = selected.has(img.url);
      var div = document.createElement('div');
      div.style.cssText = 'position:relative;cursor:pointer;border:2.5px solid ' + (isSelected ? '#0a0a0a' : '#e5e7eb') + ';overflow:hidden;transition:border-color .15s';
      div.dataset.url = img.url;
      div.innerHTML = '<img src="' + basePath + img.url + '" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block" loading="lazy">'
        + '<div style="position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;background:' + (isSelected ? '#0a0a0a' : 'rgba(255,255,255,.8)') + ';border:2px solid ' + (isSelected ? '#0a0a0a' : '#e5e7eb') + ';display:flex;align-items:center;justify-content:center;font-size:.65rem;color:white;transition:all .15s" class="chk">'
        + (isSelected ? '✓' : '') + '</div>'
        + '<div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.55);color:#fff;font-size:.58rem;padding:.2rem .4rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + img.filename + '</div>';
      div.onclick = function () {
        if (selected.has(img.url)) {
          selected.delete(img.url);
          div.style.borderColor = '#e5e7eb';
          div.querySelector('.chk').style.background = 'rgba(255,255,255,.8)';
          div.querySelector('.chk').style.borderColor = '#e5e7eb';
          div.querySelector('.chk').textContent = '';
        } else {
          selected.add(img.url);
          div.style.borderColor = '#0a0a0a';
          div.querySelector('.chk').style.background = '#0a0a0a';
          div.querySelector('.chk').style.borderColor = '#0a0a0a';
          div.querySelector('.chk').textContent = '✓';
        }
        el(opts.countId).textContent = selected.size + ' selected';
      };
      grid.appendChild(div);
    });
    el(opts.countId).textContent = selected.size + ' selected';
  }

  function filter(q) { curSearch = q; renderGrid(); }

  function renderPreview() {
    var urls = [...selected];
    var container = el(opts.previewId);
    container.innerHTML = '';
    if (urls.length === 0) {
      container.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:1.5rem;color:#9ca3af;font-size:.78rem">No images selected.</div>';
      return;
    }
    urls.forEach(function (url) {
      var div = document.createElement('div');
      div.className = 'selected-img-item';
      div.dataset.url = url;
      div.style.cssText = 'position:relative;border:1.5px solid #e5e7eb;background:#fff';
      div.innerHTML = '<img src="' + basePath + url + '" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block" loading="lazy">'
        + '<button type="button" style="position:absolute;top:3px;right:3px;background:rgba(220,38,38,.9);border:none;color:#fff;width:20px;height:20px;border-radius:50%;cursor:pointer;font-size:.6rem;display:flex;align-items:center;justify-content:center">✕</button>';
      div.querySelector('button').onclick = function () { removeItem(this); };
      container.appendChild(div);
    });
  }

  function apply() {
    inputEl.value = [...selected].join(',');
    renderPreview();
    close();
  }

  function removeItem(btn) {
    var item = btn.closest('.selected-img-item');
    selected.delete(item.dataset.url);
    item.remove();
    inputEl.value = [...selected].join(',');
    if (selected.size === 0) renderPreview();
  }

  // Initial render of whatever's already selected (edit mode).
  renderPreview();

  return { open: open, close: close, filter: filter, apply: apply, removeItem: removeItem };
}
