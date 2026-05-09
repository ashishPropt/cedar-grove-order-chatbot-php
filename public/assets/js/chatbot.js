/**
 * Item chatbot — drives a step-by-step modifier selection
 * then POSTs to add_to_basket.php
 *
 * Globals injected by item.php:
 *   ITEM  = { id, name, base_price, single_size }
 *   STEPS = [ { key, label, ui_type, min_select, max_select, options[] } ]
 */

const feed = document.getElementById('chatFeed');

// ── State ───────────────────────────────────────────────
let stepIdx     = 0;
let sizeLabel   = ITEM.single_size || '';
let basePrice   = ITEM.base_price;
let selections  = [];   // [{ option_id, group_label, choice, price_delta }]
let multiSel    = [];   // temp for checkbox steps

// ── Helpers ─────────────────────────────────────────────
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

function addNode(el) {
  feed.appendChild(el);
  scrollBottom();
}

function lockAllChips() {
  feed.querySelectorAll('.chip, .chips-confirm').forEach(b => (b.disabled = true));
}

function addChips(options, onSelect, multi = false, hint = '') {
  const wrap = document.createElement('div');
  wrap.className = 'chips-wrap';

  if (multi && hint) {
    const h = document.createElement('p');
    h.className = 'chips-hint';
    h.textContent = hint || 'Select all that apply, then tap Confirm';
    wrap.appendChild(h);
  }

  const row = document.createElement('div');
  row.className = 'chips-row';
  const selected = new Set();

  options.forEach(opt => {
    const label = opt.price_delta > 0
      ? `${opt.name} (+$${opt.price_delta.toFixed(2)})`
      : opt.name;
    const btn = document.createElement('button');
    btn.className = 'chip';
    btn.textContent = label;
    btn.dataset.optId   = opt.id || '';
    btn.dataset.optName = opt.name;
    btn.dataset.optPrice = opt.price_delta;
    btn.dataset.isSize  = opt.is_size ? '1' : '';

    if (multi) {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        btn.classList.toggle('chip--sel');
        if (selected.has(opt.id)) selected.delete(opt.id);
        else selected.add(opt.id);
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

// ── Step runner ─────────────────────────────────────────
function runStep() {
  if (stepIdx >= STEPS.length) {
    showReceipt();
    return;
  }
  const step = STEPS[stepIdx];

  // Special: size step
  if (step.key === '__size__') {
    addMsg('bot', 'What size would you like?');
    addChips(step.options, chosen => {
      const opt = chosen[0];
      sizeLabel = opt.name;
      basePrice = opt.price_delta; // price_delta holds the full size price
      addMsg('user', opt.name);
      stepIdx++;
      runStep();
    });
    return;
  }

  // Modifier step
  const multi = step.ui_type === 'checkbox';
  addMsg('bot', step.label + '?');
  addChips(
    step.options,
    chosen => {
      const display = chosen.length > 0
        ? chosen.map(o => o.name).join(', ')
        : 'None';
      addMsg('user', display);

      chosen.filter(o => o.name !== 'None').forEach(o => {
        selections.push({
          option_id:   o.id,
          group_label: step.label,
          choice:      o.name,
          price_delta: o.price_delta,
        });
      });
      stepIdx++;
      runStep();
    },
    multi,
    multi ? 'Select all that apply, then tap Confirm' : ''
  );
}

// ── Receipt ─────────────────────────────────────────────
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
    html += `<div class="chat-receipt__mod">
      ${escHtml(sel.group_label)}: ${escHtml(sel.choice)}
      ${sel.price_delta > 0 ? `<span style="color:#1D9E75">+$${sel.price_delta.toFixed(2)}</span>` : ''}
    </div>`;
  });

  html += `<div class="chat-receipt__row chat-receipt__row--total">
    <span>Item total</span><span>$${total.toFixed(2)}</span></div>`;

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

  const payload = {
    item_id:    ITEM.id,
    item_name:  ITEM.name,
    size_label: sizeLabel,
    base_price: basePrice,
    selections: selections,
    total:      total,
  };

  try {
    const res  = await fetch('add_to_basket.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.ok) {
      btn.textContent = '✓ Added to basket!';
      btn.style.background = '#0F6E56';
      // update badge
      let badge = document.querySelector('.basket-badge');
      const basketBtn = document.querySelector('.basket-btn');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'basket-badge';
        basketBtn.appendChild(badge);
      }
      badge.textContent = data.count;

      // offer nav buttons
      setTimeout(() => {
        const nav = document.createElement('div');
        nav.className = 'chips-wrap';
        nav.innerHTML = `
          <div class="chips-row">
            <a href="index.php" class="chip">+ Add another item</a>
            <a href="basket.php" class="chip chip--sel">View basket &rarr;</a>
          </div>`;
        feed.appendChild(nav);
        scrollBottom();
      }, 400);
    } else {
      btn.textContent = 'Error — try again';
      btn.disabled = false;
    }
  } catch (e) {
    btn.textContent = 'Error — try again';
    btn.disabled = false;
  }
}

function escHtml(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Boot ────────────────────────────────────────────────
addMsg('bot', `Let me help you customise your ${ITEM.name}!`);
runStep();
