<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
session_start();
$base = __DIR__;
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

$item_id = $_GET['id']  ?? '';
$edit_id = $_GET['edit'] ?? '';   // basket entry id to replace

if (!$item_id) { header('Location: index.php'); exit; }
$item = fetch_item($item_id);
if (!$item) { header('Location: index.php'); exit; }

$sizes     = fetch_item_sizes($item_id);
$modifiers = fetch_item_modifiers($item_id);

// Pre-populate state if editing an existing basket entry
$edit_entry = null;
if ($edit_id) {
    foreach ($_SESSION['basket'] ?? [] as $b) {
        if ($b['id'] === $edit_id) { $edit_entry = $b; break; }
    }
}

$steps = [];
if (count($sizes) > 1) {
    $steps[] = [
        'key'           => '__size__',
        'label'         => 'What size would you like?',
        'ui_type'       => 'radio',
        'min_select'    => 1,
        'max_select'    => 1,
        'included_free' => 1,
        'options'       => array_map(fn($s) => [
            'id'          => $s['id'],
            'name'        => $s['label'],
            'price_delta' => (float)$s['price'],
            'is_size'     => true,
        ], $sizes),
    ];
}
foreach ($modifiers as $mg) {
    $steps[] = [
        'key'           => $mg['id'],
        'label'         => $mg['name'] . '?',
        'ui_type'       => $mg['ui_type'],
        'min_select'    => (int)$mg['min_select'],
        'max_select'    => (int)$mg['max_select'],
        'included_free' => (int)($mg['included_free'] ?? 1),
        'options'       => array_map(fn($o) => [
            'id'               => $o['id'],
            'name'             => $o['name'],
            'price_delta'      => (float)$o['price_delta'],
            'default_selected' => (bool)$o['default_selected'],
        ], $mg['options']),
    ];
}

$base_price  = count($sizes) === 1 ? (float)$sizes[0]['price'] : 0;
$single_size = count($sizes) === 1 ? $sizes[0]['label'] : null;
$basket_count = array_sum(array_column($_SESSION['basket'] ?? [], 'qty'));
$host = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?= htmlspecialchars($item['name']) ?> &mdash; Cedar Grove</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f5f4;color:#1a1a1a}a{text-decoration:none;color:inherit}
.hdr{background:#fff;border-bottom:1px solid #e5e5e5;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.bk{font-size:14px;color:#1D9E75;font-weight:500}.logo{display:flex;align-items:center;gap:10px}
.av{width:36px;height:36px;background:#1D9E75;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0}
.logo strong{font-size:15px}
.bkt{display:flex;align-items:center;gap:6px;background:#1D9E75;color:#fff;padding:8px 14px;border-radius:20px;font-size:13px;font-weight:600;position:relative}
.bkt svg{width:16px;height:16px}
.bdg{position:absolute;top:-4px;right:-4px;background:#e53e3e;color:#fff;width:18px;height:18px;border-radius:50%;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center}
.wrap{max-width:600px;margin:0 auto;padding:20px}
.edit-banner{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#856404;display:flex;align-items:center;gap:8px}
.cw{background:#fff;border-radius:16px;border:1px solid #e5e5e5;box-shadow:0 2px 20px rgba(0,0,0,.08);overflow:hidden}
.ch{display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid #eee;background:linear-gradient(135deg,#1D9E75,#0F6E56)}
.ca{width:42px;height:42px;border-radius:50%;background:rgba(255,255,255,.2);color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,.3)}
.ch-info strong{font-size:15px;color:#fff;display:block}.ch-info small{font-size:12px;color:rgba(255,255,255,.8)}
.cf{padding:14px;display:flex;flex-direction:column;gap:8px;min-height:260px;max-height:420px;overflow-y:auto}
.msg{display:flex;gap:8px;align-items:flex-end}
.bot{flex-direction:row}.usr{flex-direction:row-reverse}
.av2{width:26px;height:26px;border-radius:50%;background:#1D9E75;color:#fff;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bb{max-width:78%;padding:9px 13px;border-radius:14px;font-size:14px;line-height:1.5}
.bot .bb{background:#f0f0f0;color:#111;border-radius:3px 14px 14px 14px}
.usr .bb{background:#1D9E75;color:#fff;border-radius:14px 3px 14px 14px}
.ca2{padding:0 14px 12px;display:flex;flex-direction:column;gap:6px}
.hint{font-size:11px;color:#888;padding:2px 4px}
.crow{display:flex;flex-wrap:wrap;gap:7px}
.chip{padding:7px 13px;border-radius:20px;border:1px solid #ddd;background:#fff;color:#333;font-size:13px;cursor:pointer;transition:all .12s}
.chip:hover:not(:disabled){background:#f0faf6;border-color:#1D9E75;color:#1D9E75}
.chip.sel{background:#e1f5ee;border-color:#1D9E75;color:#085041}
.chip.sel-paid{background:#fff3cd;border-color:#ffc107;color:#856404}
.chip:disabled{opacity:.5;cursor:default}
.conf{padding:8px 18px;border-radius:20px;border:none;background:#1D9E75;color:#fff;font-size:13px;font-weight:600;cursor:pointer;margin-top:4px}
.conf:disabled{opacity:.5;cursor:default}
.cheese-info{font-size:11px;color:#888;margin-top:4px;font-style:italic}
.rc{margin:8px 14px 14px;background:#f8f8f8;border:1px solid #e5e5e5;border-radius:12px;padding:14px}
.rt{font-weight:600;font-size:14px;margin-bottom:8px}
.rr{display:flex;justify-content:space-between;gap:12px;color:#555;margin-top:4px;font-size:13px}
.rT{font-weight:700;color:#111;border-top:1px solid #e0e0e0;margin-top:8px;padding-top:8px}
.rm{color:#777;font-size:12px;padding-left:10px;margin-top:2px}
.ab{margin-top:10px;padding:12px;border-radius:12px;border:none;background:#1D9E75;color:#fff;font-size:14px;font-weight:600;cursor:pointer;width:100%}
.ab:hover{background:#0F6E56}
.navr{display:flex;gap:8px;margin-top:8px}
.nc{padding:9px 14px;border-radius:20px;font-size:13px;font-weight:500;text-align:center;flex:1;text-decoration:none;border:none;cursor:pointer}
.ncs{border:1px solid #ddd!important;color:#444;background:#fff}
.ncp{background:#1D9E75;color:#fff}
</style>
</head>
<body>
<header class="hdr">
  <a href="<?= $host ?>/index.php" class="bk">&larr; Menu</a>
  <div class="logo"><span class="av">CG</span><strong>Cedar Grove</strong></div>
  <a href="<?= $host ?>/basket.php" class="bkt">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
    Basket<?php if ($basket_count > 0): ?><span class="bdg"><?= $basket_count ?></span><?php endif; ?>
  </a>
</header>

<div class="wrap">
  <?php if ($edit_entry): ?>
  <div class="edit-banner">&#9998; Editing: <strong><?= htmlspecialchars($edit_entry['item_name']) ?></strong> &mdash; your previous choices are pre-selected below.</div>
  <?php endif; ?>
  <div class="cw">
    <div class="ch">
      <div class="ca">CG</div>
      <div class="ch-info">
        <strong><?= htmlspecialchars($item['name']) ?></strong>
        <small><?= $edit_entry ? 'Editing your order' : 'Tap the options to customise' ?></small>
      </div>
    </div>
    <div class="cf" id="F"></div>
    <div id="A"></div>
  </div>
</div>

<script>
var ITEM = <?= json_encode([
    'id'         => $item['id'],
    'name'       => $item['name'],
    'base_price' => $base_price,
    'single_size'=> $single_size,
]) ?>;
var STEPS   = <?= json_encode($steps) ?>;
var HOST    = <?= json_encode($host) ?>;
var EDIT_ID = <?= json_encode($edit_id ?: null) ?>;
var EDIT_ENTRY = <?= json_encode($edit_entry) ?>;

var stepIdx   = 0;
var sizeLabel = ITEM.single_size || '';
var basePrice = ITEM.base_price;
var selections = [];  // [{option_id, group_label, choice, price_delta}]
var multiSel   = {};  // keyed by step key: Set of option ids

var feed = document.getElementById('F');
var area = document.getElementById('A');

function sb() { feed.scrollTop = feed.scrollHeight; }

function addMsg(role, text) {
    var w = document.createElement('div');
    w.className = 'msg ' + (role === 'bot' ? 'bot' : 'usr');
    if (role === 'bot') w.innerHTML = '<div class="av2">CG</div>';
    var b = document.createElement('div');
    b.className = 'bb';
    b.textContent = text;
    w.appendChild(b);
    feed.appendChild(w);
    sb();
}

function lockChips() {
    area.querySelectorAll('.chip,.conf').forEach(function(b) { b.disabled = true; });
}

// Get pre-selected option ids for a step when editing
function getPreselected(step) {
    if (!EDIT_ENTRY) return [];
    var groupLabel = step.label.replace('?','').trim();
    if (step.key === '__size__') {
        // find size by label
        var sl = EDIT_ENTRY.size_label;
        var opt = step.options.find(function(o) { return o.name === sl; });
        return opt ? [opt.id] : [];
    }
    return EDIT_ENTRY.selections
        .filter(function(s) { return s.group_label === groupLabel; })
        .map(function(s) {
            var o = step.options.find(function(o) { return o.name === s.choice; });
            return o ? o.id : null;
        })
        .filter(Boolean);
}

function showChips(step, cb) {
    area.innerHTML = '';
    var multi         = step.ui_type === 'checkbox';
    var includedFree  = step.included_free || 1;
    var preselected   = getPreselected(step);
    var sel           = new Set(preselected);

    var wrap = document.createElement('div');
    wrap.className = 'ca2';

    if (multi) {
        var maxSel = step.max_select || 99;
        var hint = document.createElement('p');
        hint.className = 'hint';
        if (step.key === '00000000-0000-0002-0000-000000000003' || step.label.toLowerCase().indexOf('cheese') !== -1) {
            hint.textContent = 'Pick up to ' + maxSel + ' cheeses. First ' + includedFree + ' free, additional +$' + (step.options[0] ? step.options[0].price_delta.toFixed(2) : '1.00') + ' each.';
        } else {
            hint.textContent = 'Select all that apply then tap Confirm';
        }
        wrap.appendChild(hint);
    }

    var row = document.createElement('div');
    row.className = 'crow';
    var cheeseInfo = null;

    step.options.forEach(function(o) {
        var btn = document.createElement('button');
        btn.className = 'chip' + (sel.has(o.id) ? ' sel' : '');
        btn.dataset.id = o.id;
        // Label — for cheese we show "free" or price
        var lbl = o.name;
        if (multi && step.options[0].price_delta > 0) {
            // price shown dynamically based on how many selected
        }
        btn.textContent = lbl;

        if (multi) {
            btn.onclick = function() {
                if (btn.disabled) return;
                var maxSel = step.max_select || 99;
                if (!sel.has(o.id)) {
                    if (sel.size >= maxSel) {
                        // flash hint
                        if (cheeseInfo) { cheeseInfo.style.color='#e53e3e'; setTimeout(function(){cheeseInfo.style.color='';},800); }
                        return;
                    }
                    sel.add(o.id);
                } else {
                    sel.delete(o.id);
                }
                // Update chip styling based on free vs paid position
                updateCheeseChips(row, step, sel, includedFree);
                if (cheeseInfo) updateCheeseInfo(cheeseInfo, step, sel, includedFree);
            };
        } else {
            btn.onclick = function() {
                if (btn.disabled) return;
                lockChips();
                cb([o]);
            };
        }
        row.appendChild(btn);
    });

    wrap.appendChild(row);

    // Cheese info line
    if (multi && step.options[0] && step.options[0].price_delta > 0) {
        cheeseInfo = document.createElement('p');
        cheeseInfo.className = 'cheese-info';
        updateCheeseInfo(cheeseInfo, step, sel, includedFree);
        wrap.appendChild(cheeseInfo);
    }

    // Apply initial styling for preselected
    updateCheeseChips(row, step, sel, includedFree);

    if (multi) {
        var conf = document.createElement('button');
        conf.className = 'conf';
        conf.textContent = 'Confirm';
        conf.onclick = function() {
            if (conf.disabled) return;
            lockChips();
            var chosen = step.options.filter(function(o) { return sel.has(o.id); });
            cb(chosen);
        };
        wrap.appendChild(conf);
    }

    area.appendChild(wrap);
    sb();
}

function updateCheeseChips(row, step, sel, includedFree) {
    if (step.ui_type !== 'checkbox') return;
    var selArray = Array.from(sel);
    row.querySelectorAll('.chip').forEach(function(btn) {
        var id  = btn.dataset.id;
        var idx = selArray.indexOf(id);
        btn.classList.remove('sel','sel-paid');
        if (idx === -1) return;
        if (idx < includedFree) {
            btn.classList.add('sel');       // green = free
        } else {
            btn.classList.add('sel-paid');  // yellow = extra charge
        }
    });
}

function updateCheeseInfo(el, step, sel, includedFree) {
    var count   = sel.size;
    var extra   = Math.max(0, count - includedFree);
    var perItem = step.options[0] ? step.options[0].price_delta : 1.00;
    var charge  = extra * perItem;
    if (count === 0) {
        el.textContent = 'No cheese selected';
    } else if (extra === 0) {
        el.textContent = count + ' cheese' + (count>1?'s':'') + ' selected — included free';
    } else {
        el.textContent = count + ' cheeses selected — ' + includedFree + ' free + ' + extra + ' extra (+$' + charge.toFixed(2) + ')';
    }
}

function handleChoice(step, chosen) {
    if (step.key === '__size__') {
        sizeLabel = chosen[0].name;
        basePrice = chosen[0].price_delta;
        addMsg('user', chosen[0].name);
        stepIdx++;
        setTimeout(run, 400);
        return;
    }

    var includedFree = step.included_free || 1;
    var display = chosen.length > 0 ? chosen.map(function(o) { return o.name; }).join(', ') : 'None';
    addMsg('user', display);

    chosen.forEach(function(o, idx) {
        var actualPrice = (step.ui_type === 'checkbox' && idx >= includedFree) ? o.price_delta : 0;
        if (o.name !== 'None') {
            selections.push({
                option_id:   o.id,
                group_label: step.label.replace('?','').trim(),
                choice:      o.name,
                price_delta: actualPrice,
            });
        }
    });

    stepIdx++;
    setTimeout(run, 400);
}

function run() {
    area.innerHTML = '';
    if (stepIdx >= STEPS.length) { showReceipt(); return; }
    var step  = STEPS[stepIdx];
    addMsg('bot', step.label);
    showChips(step, function(chosen) { handleChoice(step, chosen); });
}

function showReceipt() {
    area.innerHTML = '';
    var mt  = selections.reduce(function(s, x) { return s + (x.price_delta || 0); }, 0);
    var tot = basePrice + mt;
    addMsg('bot', EDIT_ID ? 'Updated order:' : 'Here is your order:');

    var rc = document.createElement('div');
    rc.className = 'rc';
    var h = '<p class="rt">' + esc(ITEM.name) + '</p>';
    if (sizeLabel) {
        h += '<div class="rr"><span>Size</span><span>' + esc(sizeLabel) + '</span></div>';
        h += '<div class="rr"><span>Base price</span><span>$' + basePrice.toFixed(2) + '</span></div>';
    } else {
        h += '<div class="rr"><span>Price</span><span>$' + basePrice.toFixed(2) + '</span></div>';
    }
    selections.forEach(function(s) {
        h += '<div class="rm">' + esc(s.group_label) + ': ' + esc(s.choice);
        if (s.price_delta > 0) h += ' <span style="color:#1D9E75">+$' + s.price_delta.toFixed(2) + '</span>';
        h += '</div>';
    });
    h += '<div class="rr rT"><span>Total</span><span>$' + tot.toFixed(2) + '</span></div>';

    var btn = document.createElement('button');
    btn.className = 'ab';
    btn.textContent = EDIT_ID ? 'Update basket' : 'Add to basket';
    btn.onclick = function() { saveToBasket(tot, btn); };

    rc.innerHTML = h;
    rc.appendChild(btn);
    area.appendChild(rc);
}

async function saveToBasket(tot, btn) {
    btn.disabled = true;
    btn.textContent = EDIT_ID ? 'Updating…' : 'Adding…';
    try {
        var r = await fetch(HOST + '/add_to_basket.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                item_id:    ITEM.id,
                item_name:  ITEM.name,
                size_label: sizeLabel,
                base_price: basePrice,
                selections: selections,
                total:      tot,
                edit_id:    EDIT_ID,   // null for new, basket entry id for edit
            }),
        });
        var d = await r.json();
        if (d.ok) {
            btn.textContent = EDIT_ID ? '✓ Updated!' : '✓ Added!';
            btn.style.background = '#0F6E56';
            var bdg = document.querySelector('.bdg');
            if (!bdg) { bdg = document.createElement('span'); bdg.className = 'bdg'; document.querySelector('.bkt').appendChild(bdg); }
            bdg.textContent = d.count;
            var nav = document.createElement('div');
            nav.className = 'navr';
            nav.innerHTML = '<a href="' + HOST + '/index.php" class="nc ncs">+ Add another item</a>'
                          + '<a href="' + HOST + '/basket.php" class="nc ncp">View basket &rarr;</a>';
            area.appendChild(nav);
        } else {
            btn.textContent = 'Error — try again';
            btn.disabled = false;
        }
    } catch(e) {
        btn.textContent = 'Error — try again';
        btn.disabled = false;
    }
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

addMsg('bot', 'Hi! Let me help you ' + (EDIT_ID ? 'update your ' : 'order ') + ITEM.name + '.');
setTimeout(run, 300);
</script>
</body>
</html>
