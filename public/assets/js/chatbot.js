/**
 * Cedar Grove & Gianni's Order Chatbot
 * Vanilla JS — calls /api/chat.php for all menu data
 * State is kept in memory (page-scoped); cart persisted to sessionStorage
 */

const API = '../api/chat.php';
const TAX = 0.0663;

// ── State ───────────────────────────────────────────────────────────────────
const state = {
  step: 'restaurant',      // restaurant | category | item | size | modifier | continue | order_type | done
  restaurant: null,
  category:   null,
  item:       null,
  sizeKey:    null,
  basePrice:  0,
  modifiers:  [],          // array of { key, label, type, options }
  modIdx:     0,
  selections: {},          // { modKey: [chosen names] }
  cart:       JSON.parse(sessionStorage.getItem('cg_cart') || '[]'),
  orderType:  null,
};

// ── DOM refs ────────────────────────────────────────────────────────────────
const feed = document.getElementById('messages');

// ── Utility ─────────────────────────────────────────────────────────────────
function saveCart() {
  sessionStorage.setItem('cg_cart', JSON.stringify(state.cart));
}

function scrollBottom() {
  feed.scrollTop = feed.scrollHeight;
}

async function api(action, extra = {}) {
  const res = await fetch(API, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ action, ...extra }),
  });
  return res.json();
}

// ── Render helpers ───────────────────────────────────────────────────────────
function addMsg(role, text) {
  const wrap = document.createElement('div');
  wrap.className = `msg msg--${role}`;
  if (role === 'bot') wrap.innerHTML = `<div class="msg__av">CG</div>`;
  const bubble = document.createElement('div');
  bubble.className = 'msg__bubble';
  bubble.textContent = text;
  wrap.appendChild(bubble);
  feed.appendChild(wrap);
  scrollBottom();
  return wrap;
}

function showTyping() {
  const d = document.createElement('div');
  d.className = 'msg msg--bot';
  d.id = 'typing';
  d.innerHTML = `<div class="msg__av">CG</div><div class="typing"><span></span><span></span><span></span></div>`;
  feed.appendChild(d);
  scrollBottom();
}

function hideTyping() {
  document.getElementById('typing')?.remove();
}

function addChips(options, onSelect, multi = false) {
  const wrap = document.createElement('div');
  wrap.className = 'chips-wrap';
  wrap.id = 'chips-wrap';

  if (multi) {
    const hint = document.createElement('p');
    hint.className = 'chips-hint';
    hint.textContent = 'Select all that apply, then tap Confirm';
    wrap.appendChild(hint);
  }

  const row = document.createElement('div');
  row.className = 'chips';

  const selected = new Set();

  options.forEach(([label, price]) => {
    const display = price > 0 ? `${label} (+$${price.toFixed(2)})` : label;
    const btn = document.createElement('button');
    btn.className = 'chip';
    btn.textContent = display;
    btn.dataset.name = label;

    if (multi) {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        btn.classList.toggle('chip--selected');
        if (selected.has(label)) selected.delete(label);
        else selected.add(label);
      });
    } else {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        lockChips();
        onSelect([label], price);
      });
    }
    row.appendChild(btn);
  });
  wrap.appendChild(row);

  if (multi) {
    const confirm = document.createElement('button');
    confirm.className = 'chips-confirm';
    confirm.textContent = 'Confirm selection';
    confirm.addEventListener('click', () => {
      if (confirm.disabled) return;
      lockChips();
      onSelect([...selected]);
    });
    wrap.appendChild(confirm);
  }

  feed.appendChild(wrap);
  scrollBottom();
}

function lockChips() {
  const wrap = document.getElementById('chips-wrap');
  if (!wrap) return;
  wrap.querySelectorAll('button').forEach(b => (b.disabled = true));
}

function removeChips() {
  document.getElementById('chips-wrap')?.remove();
}

// ── Render order summary receipt ─────────────────────────────────────────────
function renderReceipt() {
  const subtotal = state.cart.reduce((s, i) => s + i.line_total, 0);
  const tax      = subtotal * TAX;
  const total    = subtotal + tax;

  const card = document.createElement('div');
  card.className = 'receipt';

  let html = `<div class="receipt__title">Your order</div>`;

  state.cart.forEach((item, i) => {
    html += `<div class="receipt__item">
      <div class="receipt__item-header"><span>${i + 1}. ${item.name}</span><span>$${item.base_price.toFixed(2)}</span></div>`;
    (item.mod_lines || []).forEach(m => {
      html += `<div class="receipt__mod"><span>${m.label}</span>${m.cost > 0 ? `<span>+$${m.cost.toFixed(2)}</span>` : ''}</div>`;
    });
    html += `<div class="receipt__line-total">Line total: $${item.line_total.toFixed(2)}</div></div>`;
  });

  html += `<hr class="receipt__divider">
    <div class="receipt__row"><span>Subtotal</span><span>$${subtotal.toFixed(2)}</span></div>
    <div class="receipt__row receipt__row--muted"><span>Tax (6.63% NJ)</span><span>$${tax.toFixed(2)}</span></div>
    <div class="receipt__row receipt__row--total"><span>Total</span><span>$${total.toFixed(2)}</span></div>
    <div class="receipt__row receipt__row--muted" style="margin-top:6px;font-size:12px"><span>Order type</span><span>${state.orderType}</span></div>`;

  card.innerHTML = html;

  const addBtn = document.createElement('button');
  addBtn.className = 'receipt__add';
  addBtn.textContent = '+ Add another item';
  addBtn.addEventListener('click', () => { addBtn.disabled = true; startOver(); });
  card.appendChild(addBtn);

  feed.appendChild(card);
  scrollBottom();
}

// ── Step helpers ─────────────────────────────────────────────────────────────
async function botSay(text, delay = 300) {
  showTyping();
  await new Promise(r => setTimeout(r, delay));
  hideTyping();
  addMsg('bot', text);
}

function startOver() {
  state.step       = 'restaurant';
  state.restaurant = null;
  state.category   = null;
  state.item       = null;
  state.sizeKey    = null;
  state.basePrice  = 0;
  state.modifiers  = [];
  state.modIdx     = 0;
  state.selections = {};
  askRestaurant();
}

async function askRestaurant() {
  const data = await api('get_restaurants');
  await botSay('Which restaurant would you like to order from?', 200);
  addChips(data.restaurants.map(r => [r, 0]), async ([r]) => {
    addMsg('user', r);
    state.restaurant = r;
    await askCategory();
  });
}

async function askCategory() {
  const data = await api('get_categories', { restaurant: state.restaurant });
  await botSay('Which category are you interested in?');
  addChips(data.categories.map(c => [c, 0]), async ([cat]) => {
    addMsg('user', cat);
    state.category = cat;
    await askItem();
  });
}

async function askItem() {
  const data = await api('get_items', { category: state.category });
  await botSay('Which item would you like?');
  addChips(data.items.map(i => [i, 0]), async ([itm]) => {
    addMsg('user', itm);
    state.item = itm;
    await askSize();
  });
}

async function askSize() {
  const data = await api('get_sizes', { category: state.category, item: state.item });

  if (data.sizes && data.sizes.length > 0) {
    await botSay('What size would you like?');
    const opts = data.sizes.map(sz => [sz, data.price_map[sz] || 0]);
    addChips(opts, async ([sz, price]) => {
      addMsg('user', sz);
      state.sizeKey   = sz;
      state.basePrice = price;
      await askModifiers();
    });
  } else {
    state.sizeKey   = null;
    state.basePrice = data.price_map['_fixed'] || 0;
    await askModifiers();
  }
}

async function askModifiers() {
  const data = await api('get_modifiers', { category: state.category });
  const mods = data.modifiers || {};

  state.modifiers = Object.entries(mods).map(([key, mod]) => ({ key, ...mod }));
  state.modIdx    = 0;
  state.selections = {};
  await nextModifier();
}

async function nextModifier() {
  if (state.modIdx >= state.modifiers.length) {
    await finalizeItem();
    return;
  }

  const mod   = state.modifiers[state.modIdx];
  const multi = mod.type === 'checkbox';
  await botSay(mod.label + '?');

  addChips(mod.options, async (chosen) => {
    const display = chosen.length > 0 ? chosen.join(', ') : 'None';
    addMsg('user', display);
    state.selections[mod.key] = chosen.filter(c => c !== 'None');
    state.modIdx++;
    await nextModifier();
  }, multi);
}

async function finalizeItem() {
  const data = await api('price_item', {
    category:   state.category,
    item:       state.item,
    size_key:   state.sizeKey,
    selections: state.selections,
  });

  state.cart.push(data);
  saveCart();

  await botSay(`Added: ${data.name} — $${data.line_total.toFixed(2)}. Would you like to add another item?`);

  addChips([['Add another item', 0], ["That's my order", 0]], async ([choice]) => {
    addMsg('user', choice);
    if (choice === 'Add another item') {
      startOver();
    } else {
      await askOrderType();
    }
  });
}

async function askOrderType() {
  await botSay('How would you like your order?');
  addChips([['Pickup', 0], ['Delivery', 0], ['Dine in', 0]], async ([type]) => {
    addMsg('user', type);
    state.orderType = type;
    await botSay("Here's your complete order summary:");
    renderReceipt();
  });
}

// ── Boot ─────────────────────────────────────────────────────────────────────
(async () => {
  await botSay("Welcome to Cedar Grove Cafe & Gianni's Pizzarama! I'll help you build your order.", 400);
  await askRestaurant();
})();
