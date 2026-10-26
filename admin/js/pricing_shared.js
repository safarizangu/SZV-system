// ARCH-1: shared between admin/package_edit.php and admin/quotation_new.php.
// These two pages' pricing/itinerary-builder JS had drifted into ~20
// independently-maintained near-copies of the same functions — the real
// risk wasn't any one bug, it was that a fix applied to one copy could
// silently never reach the other (see fillDayPark below, which actually
// happened: package_edit.php's copy never persisted the selected park's
// id, only its fee, because the fix quotation_new.php got for that was
// never backported).
//
// Only functions that were either byte-identical already, or differed
// *only* in which DOM-id convention each page uses (pe-*/pe_adults vs
// qn-*/inp_adults), are here. Each page sets window.PE_IDS to its own
// convention before this script's functions are ever called. Functions
// with genuine behavioral differences between the two pages — calcTotals,
// calcItinTotal, peDayBlockHtml, syncItineraryDays,
// syncIncludesExcludesCore — stay page-specific; merging those would mean
// either bolting resident-pricing/template-linked-data logic onto a page
// that has no use for it, or losing it from the page that does.
//
// Loaded via <script src="js/pricing_shared.js"> before each page's own
// inline <script> block, so these are plain function declarations (hoisted,
// available immediately) that read PE_IDS/PE_ACCS/PE_PARKS/PE_ACTS/
// autoSyncTimer/syncIncludesExcludesCore/calcItinTotal — all of which each
// page's own inline script defines under those exact names.

function escapeHtml(s) {
  var d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
}

// Park child fee, looked up from PE_PARKS (same reference data used for
// the datalists) — mirrors ItineraryQuoteBuilder::lookupParkChildFee()
// server-side so this live preview matches the real saved calculation.
function parkChildFee(parkName) {
  for (var i=0; i<PE_PARKS.length; i++) {
    if (PE_PARKS[i].name === parkName) return PE_PARKS[i].childFee||0;
  }
  return 0;
}

function peParkLookup(parkName) {
  for (var i=0; i<PE_PARKS.length; i++) if (PE_PARKS[i].name === parkName) return PE_PARKS[i];
  return null;
}

function peAccLookup(accName) {
  for (var i=0; i<PE_ACCS.length; i++) if (PE_ACCS[i].name === accName) return PE_ACCS[i];
  return null;
}

// Because this app's location strings are sub-area names ("Central
// Serengeti", "Northern Serengeti", "Western Serengeti Corridor") that
// don't contain one another as substrings even though they're all
// "Serengeti", a pure indexOf() match would silently show nothing for
// most real combinations — tokenizing both sides and matching on any
// shared 4+ letter word (park/region names) catches all of these.
function locMatch(a, b) {
  if (!a || !b) return true;
  var ta = a.toLowerCase().match(/[a-z]{4,}/g) || [];
  var tb = b.toLowerCase().match(/[a-z]{4,}/g) || [];
  for (var i = 0; i < ta.length; i++) { if (tb.indexOf(ta[i]) !== -1) return true; }
  return false;
}

// Whether the first day-block's "arrival day" checkbox is checked — index
// 0 always already exists in the DOM (an itinerary can never have 0 days),
// so newly-added days just need to read it, not track it.
function isArrivalDayFlagged() {
  var first = document.querySelector('#itinerary-rows .day-block');
  var cb = first ? first.querySelector('.day-arrival-toggle input[type=checkbox]') : null;
  return !!(cb && cb.checked);
}

function scheduleAutoSync() {
  if (autoSyncTimer) clearTimeout(autoSyncTimer);
  autoSyncTimer = setTimeout(function(){ syncIncludesExcludesCore(false); }, 600);
}

function syncIncludesExcludes() { return syncIncludesExcludesCore(true); }

function saveDayActs(dayIdx) {
  var container = document.getElementById('day-acts-'+dayIdx);
  var acts=[];
  container.querySelectorAll('.act-item').forEach(function(row){
    var name=row.querySelector('.day-act-name').value.trim();
    if(!name) return;
    acts.push({name:name, price_pp:parseFloat(row.querySelector('.day-act-price').value)||0, qty:parseInt(row.querySelector('.day-act-qty').value)||1, type:row.querySelector('.day-act-type').value});
  });
  document.getElementById('acts-json-'+dayIdx).value=JSON.stringify(acts);
  calcItinTotal();
}

function addDayOther(dayIdx) {
  var container=document.getElementById('day-other-'+dayIdx);
  var div=document.createElement('div');
  div.className='other-item';
  div.style.cssText='border:1px solid var(--gray-200);border-radius:2px;padding:.5rem;margin-bottom:.5rem';
  div.innerHTML='<div style="display:flex;gap:.35rem;margin-bottom:.35rem">'
      +'<input class="admin-input day-other-name" placeholder="Item name" style="font-size:.78rem;flex:1" oninput="saveDayOther('+dayIdx+')">'
      +'<button type="button" onclick="this.closest(\'.other-item\').remove();saveDayOther('+dayIdx+')" class="admin-icon-btn admin-icon-btn--danger">'+PE_CLOSE_ICON_HTML+'</button>'
    +'</div>'
    +'<div style="display:grid;grid-template-columns:70px 55px 1fr 150px;gap:.35rem;align-items:center">'
      +'<input type="number" step="0.01" class="admin-input day-other-amount" value="0" placeholder="$/unit" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
      +'<input type="number" min="1" class="admin-input day-other-qty" title="Number of units" value="1" placeholder="Qty" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
      +'<input class="admin-input day-other-unit" title="What a unit is called (e.g. Box, Trip, Vehicle) — type your own, independent of traveler count" placeholder="Unit name (optional)" style="font-size:.78rem" oninput="saveDayOther('+dayIdx+')">'
      +'<select class="admin-input day-other-perpax" style="font-size:.72rem" title="Whether this cost multiplies by the number of travelers" onchange="saveDayOther('+dayIdx+')"><option value="0">Flat (not per guest)</option><option value="1">× Number of guests</option></select>'
    +'</div>';
  container.appendChild(div);
}

function saveDayOther(dayIdx) {
  var container=document.getElementById('day-other-'+dayIdx);
  var items=[];
  container.querySelectorAll('.other-item').forEach(function(row){
    var name=row.querySelector('.day-other-name').value.trim();
    if(!name) return;
    items.push({name:name, amount:parseFloat(row.querySelector('.day-other-amount').value)||0, qty:parseInt(row.querySelector('.day-other-qty').value)||1, unit:(row.querySelector('.day-other-unit')||{}).value||'', per_pax:row.querySelector('.day-other-perpax').value==='1'});
  });
  document.getElementById('other-json-'+dayIdx).value=JSON.stringify(items);
  calcItinTotal();
}

function filterAllDayOptions() {
  document.querySelectorAll('.day-location').forEach(function(input){
    filterDayOptions(input.dataset.idx);
  });
}

// ── Below: identical logic, differing only by which DOM-id convention
// each page uses — unified via window.PE_IDS, set per-page before this
// script's functions run. ──────────────────────────────────────────

function fillDayAcc(input, dayIdx) {
  var opts=document.querySelectorAll('#'+PE_IDS.accList+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.rate) block.querySelector('.day-acc-rate').value=opts[i].dataset.rate;
      if(opts[i].dataset.id)   block.querySelector('.day-acc-id').value=opts[i].dataset.id;
      calcItinTotal(); break;
    }
  }
}

function fillDayVeh(input, dayIdx) {
  var opts=document.querySelectorAll('#'+PE_IDS.vehList+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.rate) block.querySelector('.day-veh-rate').value=opts[i].dataset.rate;
      if(opts[i].dataset.id)   block.querySelector('.day-veh-id').value=opts[i].dataset.id;
      calcItinTotal(); break;
    }
  }
}

// ARCH-1 note: package_edit.php's copy of this never set day_park_id[],
// only the fee — quotation_new.php's copy already did. Unifying on the
// more complete version fixes that gap for package_edit.php too.
function fillDayPark(input, dayIdx) {
  var opts=document.querySelectorAll('#'+PE_IDS.parkList+dayIdx+' option');
  for(var i=0;i<opts.length;i++){
    if(opts[i].value===input.value){
      var block=input.closest('.day-block');
      if(opts[i].dataset.fee) block.querySelector('.day-park-fee').value=opts[i].dataset.fee;
      if(opts[i].dataset.id)  block.querySelector('[name="day_park_id[]"]').value=opts[i].dataset.id;
      calcItinTotal(); break;
    }
  }
}

// Narrows each day's Accommodation/Park/Activity datalist down to the
// entries whose own `location` shares a significant word with what's
// typed in that day's own Location field (see locMatch above), and
// Accommodation further by the page's overall Accommodation Level. Only
// trims the *suggestions* — the field stays free text via <datalist>.
// Looked up by the day's stable build index (data-idx), not DOM position
// — those ids never change even after a day is deleted and the rest
// renumber.
function filterDayOptions(dayIdx) {
  var locInput = document.querySelector('.day-location[data-idx="'+dayIdx+'"]');
  var loc      = locInput ? locInput.value : '';
  var level    = (document.querySelector('[name="accommodation_level"]')||{}).value || '';

  var accList = document.getElementById(PE_IDS.accList+dayIdx);
  if (accList) {
    accList.innerHTML = PE_ACCS
      .filter(function(a){ return (!level || a.level===level) && locMatch(loc, a.location); })
      .map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-rate="'+a.rate+'">'; })
      .join('');
  }
  var parkList = document.getElementById(PE_IDS.parkList+dayIdx);
  if (parkList) {
    parkList.innerHTML = PE_PARKS
      .filter(function(p){ return locMatch(loc, p.location); })
      .map(function(p){ return '<option value="'+p.name+'" data-id="'+p.id+'" data-fee="'+p.fee+'">'; })
      .join('');
  }
  var actList = document.getElementById(PE_IDS.actList+dayIdx);
  if (actList) {
    actList.innerHTML = PE_ACTS
      .filter(function(a){ return locMatch(loc, a.location); })
      .map(function(a){ return '<option value="'+a.name+'" data-id="'+a.id+'" data-price="'+a.price+'">'; })
      .join('');
  }
}

function addDayActivity(dayIdx) {
  var container = document.getElementById('day-acts-'+dayIdx);
  var defaultQty = (parseInt((document.getElementById(PE_IDS.adults)||{}).value)||1) + (parseInt((document.getElementById(PE_IDS.children)||{}).value)||0);
  var div = document.createElement('div');
  div.className='act-item';
  div.style.cssText='display:grid;grid-template-columns:2fr 80px 60px 90px auto;gap:.35rem;align-items:end;margin-bottom:.4rem';
  div.innerHTML='<input class="admin-input day-act-name" list="'+PE_IDS.actList+dayIdx+'" placeholder="Activity name" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<input type="number" step="0.01" class="admin-input day-act-price" value="0" placeholder="$/pp" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<input type="number" min="1" class="admin-input day-act-qty" title="How many travelers this activity covers" value="'+defaultQty+'" placeholder="Qty" style="font-size:.78rem" oninput="saveDayActs('+dayIdx+')">'
    +'<select class="admin-input day-act-type" style="font-size:.72rem" onchange="saveDayActs('+dayIdx+')"><option value="included">Included</option><option value="optional">Optional</option></select>'
    +'<button type="button" onclick="this.closest(\'.act-item\').remove();saveDayActs('+dayIdx+')" class="admin-icon-btn admin-icon-btn--danger">'+PE_CLOSE_ICON_HTML+'</button>';
  container.appendChild(div);
  div.querySelector('.day-act-name').addEventListener('change', function(){
    var opts=document.querySelectorAll('#'+PE_IDS.actList+dayIdx+' option');
    for(var i=0;i<opts.length;i++){
      if(opts[i].value===this.value && opts[i].dataset.price){
        div.querySelector('.day-act-price').value=opts[i].dataset.price;
        saveDayActs(dayIdx); break;
      }
    }
  });
}

function renumberDays() {
  var blocks = document.querySelectorAll('.day-block');
  var arrival = isArrivalDayFlagged();
  blocks.forEach(function (block, idx) {
    var toggle = block.querySelector('.day-arrival-toggle');
    if (toggle) toggle.style.display = (idx === 0) ? 'inline-flex' : 'none';

    var n = (idx === 0 && arrival) ? 0 : (arrival ? idx : idx + 1);
    var dayLabel = block.querySelector('.day-num-label');
    if (dayLabel) dayLabel.textContent = 'Day ' + n;
    var actsLabel = block.querySelector('.day-acts-label');
    if (actsLabel) actsLabel.textContent = 'Activities (Day ' + n + ')';
    var otherLabel = block.querySelector('.day-other-label');
    if (otherLabel) otherLabel.textContent = 'Other Costs (Day ' + n + ')';
    block.dataset.day = idx;
  });
  var count = blocks.length;
  var durInput = document.getElementById(PE_IDS.duration);
  if (durInput) durInput.value = count;
  document.getElementById('itinerary-count').textContent = count + ' day' + (count!==1?'s':'') + ' · matches duration above';
}
