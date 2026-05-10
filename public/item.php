<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
session_start();
$base = __DIR__;
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

$item_id = $_GET['id'] ?? '';
if (!$item_id) { header('Location: /index.php'); exit; }
$item = fetch_item($item_id);
if (!$item) { header('Location: /index.php'); exit; }

$sizes     = fetch_item_sizes($item_id);
$modifiers = fetch_item_modifiers($item_id);

$steps = [];
if (count($sizes) > 1) {
    $steps[] = [
        'key'        => '__size__',
        'label'      => 'Choose a size',
        'ui_type'    => 'radio',
        'min_select' => 1,
        'max_select' => 1,
        'options'    => array_map(fn($s) => [
            'id'          => $s['id'],
            'name'        => $s['label'],
            'price_delta' => (float)$s['price'],
            'is_size'     => true,
        ], $sizes),
    ];
}
foreach ($modifiers as $mg) {
    $steps[] = [
        'key'        => $mg['id'],
        'label'      => $mg['name'],
        'ui_type'    => $mg['ui_type'],
        'min_select' => (int)$mg['min_select'],
        'max_select' => (int)$mg['max_select'],
        'options'    => array_map(fn($o) => [
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

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$asset  = $scheme . '://' . $host;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= esc($item['name']) ?> &mdash; Cedar Grove</title>
  <link rel="stylesheet" href="<?= $asset ?>/assets/css/app.css" />
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f5f4;color:#1a1a1a}
    .site-header{background:#fff;border-bottom:1px solid #e5e5e5;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
    .logo{display:flex;align-items:center;gap:10px}
    .logo-icon{width:36px;height:36px;background:#1D9E75;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0}
    .logo strong{font-size:15px}
    .back-btn{font-size:14px;color:#1D9E75;font-weight:500;text-decoration:none;white-space:nowrap}
    .basket-btn{display:flex;align-items:center;gap:6px;background:#1D9E75;color:#fff;padding:8px 14px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;position:relative}
    .basket-btn svg{width:16px;height:16px}
    .basket-badge{position:absolute;top:-4px;right:-4px;background:#e53e3e;color:#fff;width:18px;height:18px;border-radius:50%;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center}
    .container{max-width:1200px;margin:0 auto;padding:20px;display:flex;justify-content:center}
    .chatbot-wrap{width:100%;max-width:520px;background:#fff;border-radius:16px;border:1px solid #e5e5e5;box-shadow:0 2px 20px rgba(0,0,0,.08);display:flex;flex-direction:column;min-height:560px;overflow:hidden}
    .chatbot-header{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid #eee;flex-shrink:0}
    .chatbot-avatar{width:36px;height:36px;border-radius:50%;background:#1D9E75;color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .chatbot-item-name{font-size:15px;font-weight:600;color:#111}
    .chatbot-header small{font-size:12px;color:#888}
    .chat-feed{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:6px}
    .chat-footer{padding:10px 14px;border-top:1px solid #eee;flex-shrink:0}
    .chat-input{width:100%;border:1px solid #ddd;border-radius:22px;padding:9px 16px;font-size:13px;background:#f8f8f8;color:#aaa;outline:none;cursor:default}
    .msg{display:flex;gap:8px;align-items:flex-end;margin-bottom:4px}
    .msg--bot{flex-direction:row}
    .msg--user{flex-direction:row-reverse}
    .msg__av{width:28px;height:28px;border-radius:50%;background:#1D9E75;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .msg__bubble{max-width:76%;padding:10px 14px;border-radius:16px;font-size:14px;line-height:1.5}
    .msg--bot .msg__bubble{background:#f0f0f0;color:#111;border-radius:4px 16px 16px 16px}
    .msg--user .msg__bubble{background:#1D9E75;color:#fff;border-radius:16px 4px 16px 16px}
    .chips-wrap{display:flex;flex-direction:column;gap:7px;align-self:flex-start;max-width:92%;margin:4px 0}
    .chips-hint{font-size:11px;color:#888}
    .chips-row{display:flex;flex-wrap:wrap;gap:7px}
    .chip{padding:7px 13px;border-radius:20px;border:1px solid #ddd;background:#fff;color:#333;font-size:13px;cursor:pointer;transition:background .12s,border-color .12s}
    .chip:hover:not(:disabled){background:#f0f0f0;border-color:#bbb}
    .chip--sel{background:#e1f5ee;border-color:#1D9E75;color:#085041}
    .chip:disabled{opacity:.5;cursor:default}
    .chips-confirm{align-self:flex-start;padding:8px 18px;border-radius:20px;border:none;background:#1D9E75;color:#fff;font-size:13px;font-weight:600;cursor:pointer;margin-top:2px}
    .chips-confirm:hover:not(:disabled){background:#0F6E56}
    .chips-confirm:disabled{opacity:.5;cursor:default}
    .chat-receipt{background:#f8f8f8;border:1px solid #e5e5e5;border-radius:12px;padding:14px 16px;font-size:13px;align-self:flex-start;max-width:92%}
    .chat-receipt__title{font-weight:600;margin-bottom:8px;font-size:14px}
    .chat-receipt__row{display:flex;justify-content:space-between;gap:12px;color:#555;margin-top:4px}
    .chat-receipt__row--total{font-weight:600;color:#111;border-top:1px solid #e0e0e0;margin-top:8px;padding-top:8px}
    .chat-receipt__mod{color:#777;font-size:12px;padding-left:10px}
    .add-basket-btn{margin-top:10px;padding:9px 20px;border-radius:20px;border:none;background:#1D9E75;color:#fff;font-size:13px;font-weight:600;cursor:pointer;width:100%}
    .add-basket-btn:hover{background:#0F6E56}
  </style>
</head>
<body>
<header class="site-header">
  <a href="<?= $asset ?>/index.php" class="back-btn">&larr; Menu</a>
  <div class="logo">
    <span class="logo-icon">CG</span>
    <strong>Cedar Grove &amp; Gianni's</strong>
  </div>
  <a href="<?= $asset ?>/basket.php" class="basket-btn">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
    </svg>
    Basket
    <?php if ($basket_count > 0): ?><span class="basket-badge"><?= $basket_count ?></span><?php endif; ?>
  </a>
</header>

<main class="container">
  <div class="chatbot-wrap">
    <div class="chatbot-header">
      <div class="chatbot-avatar">CG</div>
      <div>
        <p class="chatbot-item-name"><?= esc($item['name']) ?></p>
        <small>I'll walk you through the options</small>
      </div>
    </div>
    <div class="chat-feed" id="chatFeed"></div>
    <div class="chat-footer">
      <input class="chat-input" placeholder="Use the options above&hellip;" readonly />
    </div>
  </div>
</main>

<!-- IMPORTANT: define ITEM and STEPS BEFORE loading chatbot.js -->
<script>
const ITEM = <?= json_encode(['id' => $item['id'], 'name' => $item['name'], 'base_price' => $base_price, 'single_size' => $single_size]) ?>;
const STEPS = <?= json_encode($steps) ?>;
const ASSET_BASE = '<?= $asset ?>';
</script>
<script>
// Inline chatbot.js to avoid path issues
const feed = document.getElementById('chatFeed');
let stepIdx    = 0;
let sizeLabel  = ITEM.single_size || '';
let basePrice  = ITEM.base_price;
let selections = [];
let multiSel   = [];

const scrollBottom = () => (feed.scrollTop = feed.scrollHeight);

function addMsg(role, text) {
  const wrap = document.createElement('div');
  wrap.className = `msg msg--${role}`;
  if (role === 'bot') wrap.innerHTML = `<div class="msg__av">CG</div>`;
  const b = document.createElement('div');
  b.className = 'msg__bubble';
  b.textContent = text;
  wrap.appendChild(b);
  feed.appendChild(wrap);
  scrollBottom();
}

function lockAllChips() {
  feed.querySelectorAll('.chip,.chips-confirm').forEach(b => (b.disabled = true));
}

function addChips(options, onSelect, multi = false) {
  const wrap = document.createElement('div');
  wrap.className = 'chips-wrap';
  if (multi) {
    const h = document.createElement('p');
    h.className = 'chips-hint';
    h.textContent = 'Select all that apply, then tap Confirm';
    wrap.appendChild(h);
  }
  const row = document.createElement('div');
  row.className = 'chips-row';
  const selected = new Set();
  options.forEach(opt => {
    const label = opt.price_delta > 0 ? `${opt.name} (+$${opt.price_delta.toFixed(2)})` : opt.name;
    const btn = document.createElement('button');
    btn.className = 'chip';
    btn.textContent = label;
    if (multi) {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        btn.classList.toggle('chip--sel');
        if (selected.has(opt.id)) selected.delete(opt.id); else selected.add(opt.id);
        multiSel = options.filter(o => selected.has(o.id));
      });
    } else {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        lockAllChips();
        onSelect([opt]);
      });
    }
    row.appendChild(btn);
  });
  wrap.appendChild(row);
  if (multi) {
    const conf = document.createElement('button');
    conf.className = 'chips-confirm';
    conf.textContent = 'Confirm';
    conf.addEventListener('click', () => {
      if (conf.disabled) return;
      lockAllChips();
      onSelect(multiSel);
      multiSel = [];
    });
    wrap.appendChild(conf);
  }
  feed.appendChild(wrap);
  scrollBottom();
}

function runStep() {
  if (stepIdx >= STEPS.length) { showReceipt(); return; }
  const step = STEPS[stepIdx];
  if (step.key === '__size__') {
    addMsg('bot', 'What size would you like?');
    addChips(step.options, chosen => {
      sizeLabel = chosen[0].name;
      basePrice = chosen[0].price_delta;
      addMsg('user', chosen[0].name);
      stepIdx++; runStep();
    });
    return;
  }
  const multi = step.ui_type === 'checkbox';
  addMsg('bot', step.label + '?');
  addChips(step.options, chosen => {
    const display = chosen.length > 0 ? chosen.map(o => o.name).join(', ') : 'None';
    addMsg('user', display);
    chosen.filter(o => o.name !== 'None').forEach(o => {
      selections.push({ option_id: o.id, group_label: step.label, choice: o.name, price_delta: o.price_delta });
    });
    stepIdx++; runStep();
  }, multi);
}

function showReceipt() {
  const modTotal = selections.reduce((s, sel) => s + (sel.price_delta || 0), 0);
  const total    = basePrice + modTotal;
  const card = document.createElement('div');
  card.className = 'chat-receipt';
  let html = `<p class="chat-receipt__title">${escHtml(ITEM.name)}</p>`;
  if (sizeLabel) {
    html += `<div class="chat-receipt__row"><span>Size</span><span>${escHtml(sizeLabel)}</span></div>`;
    html += `<div class="chat-receipt__row"><span>Base price</span><span>$${basePrice.toFixed(2)}</span></div>`;
  } else {
    html += `<div class="chat-receipt__row"><span>Price</span><span>$${basePrice.toFixed(2)}</span></div>`;
  }
  selections.forEach(sel => {
    html += `<div class="chat-receipt__mod">${escHtml(sel.group_label)}: ${escHtml(sel.choice)}${sel.price_delta > 0 ? ` <span style="color:#1D9E75">+$${sel.price_delta.toFixed(2)}</span>` : ''}</div>`;
  });
  html += `<div class="chat-receipt__row chat-receipt__row--total"><span>Item total</span><span>$${total.toFixed(2)}</span></div>`;
  const addBtn = document.createElement('button');
  addBtn.className = 'add-basket-btn';
  addBtn.textContent = 'Add to basket';
  addBtn.addEventListener('click', () => addToBasket(total, addBtn));
  card.innerHTML = html;
  card.appendChild(addBtn);
  feed.appendChild(card);
  scrollBottom();
}

async function addToBasket(total, btn) {
  btn.disabled = true;
  btn.textContent = 'Adding…';
  try {
    const res  = await fetch(ASSET_BASE + '/add_to_basket.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ item_id: ITEM.id, item_name: ITEM.name, size_label: sizeLabel, base_price: basePrice, selections, total }),
    });
    const data = await res.json();
    if (data.ok) {
      btn.textContent = '✓ Added to basket!';
      btn.style.background = '#0F6E56';
      let badge = document.querySelector('.basket-badge');
      const basketBtn = document.querySelector('.basket-btn');
      if (!badge) { badge = document.createElement('span'); badge.className = 'basket-badge'; basketBtn.appendChild(badge); }
      badge.textContent = data.count;
      setTimeout(() => {
        const nav = document.createElement('div');
        nav.className = 'chips-wrap';
        nav.innerHTML = `<div class="chips-row">
          <a href="${ASSET_BASE}/index.php" class="chip">+ Add another item</a>
          <a href="${ASSET_BASE}/basket.php" class="chip chip--sel">View basket &rarr;</a>
        </div>`;
        feed.appendChild(nav);
        scrollBottom();
      }, 400);
    } else {
      btn.textContent = 'Error — try again';
      btn.disabled = false;
    }
  } catch(e) {
    btn.textContent = 'Error — try again';
    btn.disabled = false;
  }
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

addMsg('bot', `Let me help you customise your ${ITEM.name}!`);
runStep();
</script>
</body>
</html>
