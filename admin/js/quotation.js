/* Quotation Builder — Live Calculator */
var lineItems = [];
var VAT_RATE  = VAT_RATE || 0.18;
var SVC_RATE  = SVC_RATE || 0.05;

function fmt(n) { return '$' + Number(n).toLocaleString('en-US', {minimumFractionDigits:0,maximumFractionDigits:0}); }

function addLineItem(item) {
    lineItems.push(item);
    renderItems();
    recalc();
}

function removeLineItem(idx) {
    lineItems.splice(idx, 1);
    renderItems();
    recalc();
}

function renderItems() {
    var tbody = document.getElementById('line-items-body');
    if (!tbody) return;
    tbody.innerHTML = '';
    lineItems.forEach(function(item, i) {
        var tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #f3f4f6';
        tr.innerHTML = '<td style="padding:.5rem .75rem;font-size:.8rem">' + item.desc + '</td>'
            + '<td style="padding:.5rem .75rem;font-size:.8rem;text-align:center">' + (item.qty||1) + '</td>'
            + '<td style="padding:.5rem .75rem;font-size:.8rem;color:#9ca3af">' + (item.unit||'') + '</td>'
            + '<td style="padding:.5rem .75rem;font-weight:600">' + fmt(item.amount) + '</td>'
            + '<td style="padding:.5rem .75rem"><button type="button" onclick="removeLineItem(' + i + ')" '
            + 'style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem">×</button></td>';
        tbody.appendChild(tr);
    });
    document.getElementById('line_items_json').value = JSON.stringify(lineItems);
}

function recalc() {
    var adults    = parseInt(document.getElementById('q_adults').value)   || 0;
    var children  = parseInt(document.getElementById('q_children').value) || 0;
    var travelers = adults + children;
    var duration  = parseInt(document.getElementById('q_duration').value) || 1;
    var discount  = parseFloat(document.getElementById('q_discount').value) || 0;
    var flight    = document.getElementById('q_flight') && document.getElementById('q_flight').checked;

    // Sum by category
    var accTotal=0, vehTotal=0, parkTotal=0, actTotal=0;
    lineItems.forEach(function(item) {
        if      (item.category === 'accommodation') accTotal  += item.amount;
        else if (item.category === 'vehicle')       vehTotal  += item.amount;
        else if (item.category === 'park_fee')      parkTotal += item.amount;
        else if (item.category === 'activity')      actTotal  += item.amount;
        else                                         accTotal  += item.amount; // custom → general
    });

    var flightCost = flight ? (adults * 250 + children * 125) : 0;

    var raw       = accTotal + vehTotal + parkTotal + actTotal + flightCost;
    var discAmt   = raw * (discount / 100);
    var afterDisc = raw - discAmt;
    var svcCharge = afterDisc * SVC_RATE;
    var preVat    = afterDisc + svcCharge;
    var vat       = preVat * VAT_RATE;
    var grand     = preVat + vat;
    var pp        = travelers > 0 ? grand / travelers : grand;
    var deposit   = grand * 0.5;
    var balance   = grand - deposit;

    // Update sidebar
    function setSel(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }
    setSel('s_acc',      fmt(accTotal));
    setSel('s_veh',      fmt(vehTotal));
    setSel('s_park',     fmt(parkTotal));
    setSel('s_act',      fmt(actTotal));
    setSel('s_flight',   fmt(flightCost));
    setSel('s_discount', discount > 0 ? '-' + fmt(discAmt) + ' (' + discount + '%)' : '—');
    setSel('s_sub',      fmt(afterDisc));
    setSel('s_svc',      fmt(svcCharge));
    setSel('s_vat',      fmt(vat));
    setSel('s_grand',    fmt(grand));
    setSel('s_pp',       fmt(pp) + '/pp');
    setSel('s_dep',      fmt(deposit));
    setSel('s_bal',      fmt(balance));
    setSel('ft-subtotal',fmt(raw));

    // Update hidden fields
    function setHid(id, val) { var el = document.getElementById(id); if (el) el.value = val.toFixed(2); }
    setHid('h_acc_total',  accTotal);
    setHid('h_veh_total',  vehTotal);
    setHid('h_park_total', parkTotal);
    setHid('h_act_total',  actTotal);
    setHid('h_flight',     flightCost);
    setHid('h_subtotal',   afterDisc);
    setHid('h_svc',        svcCharge);
    setHid('h_vat',        vat);
    setHid('h_grand',      grand);
    setHid('h_pp',         pp);
}

function addAccItem() {
    var sel = document.getElementById('acc_picker');
    var opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    var adults   = parseInt(document.getElementById('q_adults').value)   || 1;
    var children = parseInt(document.getElementById('q_children').value) || 0;
    var duration = parseInt(document.getElementById('q_duration').value) || 1;
    var rateRoom = parseFloat(opt.dataset.rateRoom) || 0;
    var ratePp   = parseFloat(opt.dataset.ratePp)   || 0;
    var amount   = rateRoom > 0
        ? rateRoom * Math.ceil(adults / 2) * duration
        : ratePp * (adults + children) * duration;
    addLineItem({
        desc:     opt.dataset.name + ' (' + duration + ' nights)',
        qty:      duration,
        unit:     'nights',
        amount:   amount,
        category: 'accommodation'
    });
    sel.selectedIndex = 0;
}

function addVehItem() {
    var sel = document.getElementById('veh_picker');
    var opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    var duration = parseInt(document.getElementById('q_duration').value) || 1;
    var daily    = parseFloat(opt.dataset.rate)   + parseFloat(opt.dataset.fuel) + parseFloat(opt.dataset.driver);
    var amount   = daily * duration;
    document.getElementById('h_veh_name').value = opt.dataset.name;
    addLineItem({
        desc:     opt.dataset.name + ' (' + duration + ' days incl. fuel & driver)',
        qty:      duration,
        unit:     'days',
        amount:   amount,
        category: 'vehicle'
    });
    sel.selectedIndex = 0;
}

function addParkFees() {
    var adults   = parseInt(document.getElementById('q_adults').value)   || 1;
    var children = parseInt(document.getElementById('q_children').value) || 0;
    var checked  = document.querySelectorAll('.park-check:checked');
    checked.forEach(function(cb) {
        var lbl      = cb.closest('label');
        var name     = lbl.dataset.parkName;
        var adultFee = parseFloat(lbl.dataset.adultFee)  || 0;
        var childFee = parseFloat(lbl.dataset.childFee)  || 0;
        var amount   = (adultFee * adults) + (childFee * children);
        addLineItem({ desc: name + ' Entry Fees', qty: adults+(children>0?'+'+children:''), unit:'persons', amount:amount, category:'park_fee' });
        cb.checked = false;
    });
}

function addActivities() {
    var adults   = parseInt(document.getElementById('q_adults').value)   || 1;
    var children = parseInt(document.getElementById('q_children').value) || 0;
    var checked  = document.querySelectorAll('.act-check:checked');
    var names    = [];
    checked.forEach(function(cb) {
        var lbl   = cb.closest('label');
        var name  = lbl.dataset.actName;
        var price = parseFloat(lbl.dataset.actPrice) || 0;
        var unit  = lbl.dataset.actUnit;
        var qty   = unit === 'per_group' ? 1 : (adults + children);
        addLineItem({ desc:name, qty:qty, unit:unit, amount:price*qty, category:'activity' });
        names.push(name);
        cb.checked = false;
    });
    var hidAct = document.getElementById('activities_list_hidden');
    if (hidAct) {
        var cur = hidAct.value ? hidAct.value.split(',') : [];
        hidAct.value = cur.concat(names).join(',');
    }
}

function addCustomItem() {
    var desc   = document.getElementById('custom_desc').value.trim();
    var amount = parseFloat(document.getElementById('custom_amount').value) || 0;
    if (!desc || !amount) return;
    addLineItem({ desc:desc, qty:1, unit:'custom', amount:amount, category:'custom' });
    document.getElementById('custom_desc').value   = '';
    document.getElementById('custom_amount').value = '';
}

function applyPackage(sel) {
    var opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    var adults   = parseInt(document.getElementById('q_adults').value)   || 1;
    var duration = parseInt(document.getElementById('q_duration').value) || parseInt(opt.dataset.days) || 5;
    // Apply package duration
    document.getElementById('q_duration').value = opt.dataset.days || duration;
    // Price tier
    var tier   = adults === 1 ? 'p1' : adults === 2 ? 'p2' : 'p3';
    var ppRate = parseFloat(opt.dataset[tier]) || 0;
    if (ppRate > 0) {
        addLineItem({ desc: opt.textContent.trim() + ' (Package Base)', qty: adults, unit:'persons', amount: ppRate * adults, category:'accommodation' });
    }
}
