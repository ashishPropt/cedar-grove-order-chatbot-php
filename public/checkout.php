<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
session_start();
$base = __DIR__;
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

$basket = $_SESSION['basket'] ?? [];
if (empty($basket)) { header('Location: index.php'); exit; }

$order_placed = false; $order_id = null; $error = null; $offline_saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_type = $_POST['order_type'] ?? 'dine_in';
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $offline = $_POST['offline'] ?? '0';  // flag set by JS when offline

    $subtotal = array_sum(array_map(fn($b) => $b['total'] * ($b['qty'] ?? 1), $basket));
    $tax      = round($subtotal * 0.0663, 2);
    $total    = round($subtotal + $tax, 2);

    if ($offline === '1') {
        // Order will be handled by JS/IDB - just clear basket
        $order_placed = true;
        $offline_saved = true;
        $_SESSION['basket'] = [];
    } else {
        $customer = null;
        if ($email) {
            $existing = sb_get('customers', ['email' => 'eq.' . $email]);
            $customer = $existing[0] ?? null;
            if (!$customer) { $rows = sb_post('customers', ['name'=>$name,'email'=>$email,'phone'=>$phone]); $customer = $rows[0] ?? null; }
        }
        $first_rows    = sb_get('menu_items', ['id' => 'eq.' . $basket[0]['item_id'], 'select' => 'restaurant_id']);
        $restaurant_id = $first_rows[0]['restaurant_id'] ?? null;
        $order_payload = ['restaurant_id'=>$restaurant_id,'status'=>'pending','order_type'=>$order_type,'subtotal'=>$subtotal,'tax'=>$tax,'total'=>$total,'notes'=>$notes];
        if ($customer) $order_payload['customer_id'] = $customer['id'];
        $order_rows = sb_post('orders', $order_payload);
        $order = $order_rows[0] ?? null;
        if ($order) {
            $order_id = $order['id'];
            foreach ($basket as $b) {
                for ($q = 0; $q < ($b['qty'] ?? 1); $q++) {
                    $oi_rows = sb_post('order_items', ['order_id'=>$order_id,'menu_item_id'=>$b['item_id'],'unit_price'=>$b['base_price'],'line_total'=>$b['total'],'quantity'=>1,'notes'=>$b['size_label']??'']);
                    $oi = $oi_rows[0] ?? null;
                    if ($oi && !empty($b['selections'])) {
                        foreach ($b['selections'] as $sel) {
                            if (!empty($sel['option_id'])) {
                                sb_post('order_item_modifiers', ['order_item_id'=>$oi['id'],'modifier_option_id'=>$sel['option_id'],'price_delta'=>$sel['price_delta']??0]);
                            }
                        }
                    }
                }
            }
            $order_placed = true;
            $_SESSION['basket'] = [];
        } else {
            $error = 'Could not reach server. Use offline mode below.';
        }
    }
}

$subtotal = array_sum(array_map(fn($b) => $b['total'] * ($b['qty'] ?? 1), $basket));
$tax = $subtotal * 0.0663; $total = $subtotal + $tax;
$host = ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Checkout &mdash; Cedar Grove POS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f5f4;color:#1a1a1a}a{text-decoration:none;color:inherit}
.hdr{background:#fff;border-bottom:1px solid #e5e5e5;padding:12px 20px;display:flex;align-items:center;justify-content:space-between}
.bk{font-size:14px;color:#1D9E75;font-weight:500}.logo{display:flex;align-items:center;gap:10px}
.av{width:36px;height:36px;background:#1D9E75;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px}
.logo strong{font-size:15px}
.wrap{max-width:900px;margin:0 auto;padding:20px}
.layout{display:flex;gap:24px;align-items:flex-start}
.form-card{flex:1;background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:24px}
.form-card h2{font-size:16px;margin-bottom:14px}
.form-card label{display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:500;color:#444;margin-bottom:14px}
.form-card input,.form-card textarea,.form-card select{border:1px solid #ddd;border-radius:8px;padding:9px 12px;font-size:14px;width:100%;outline:none;font-family:inherit}
.form-card input:focus,.form-card textarea:focus{border-color:#1D9E75}
.rg{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:8px}
.ro{display:flex;align-items:center;gap:6px;font-size:14px;font-weight:400;cursor:pointer;margin-bottom:0}
.offline-notice{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#856404;display:none}
.offline-notice.show{display:block}
.submit-btn{margin-top:20px;width:100%;padding:13px;border:none;border-radius:12px;background:#1D9E75;color:#fff;font-size:15px;font-weight:600;cursor:pointer}
.submit-btn:hover{background:#0F6E56}
.submit-btn.offline-mode{background:#f59e0b}
.summary{width:280px;flex-shrink:0;background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:20px;position:sticky;top:20px}
.summary h2{font-size:16px;margin-bottom:14px}
.co-item{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid #f0f0f0}
.co-name{font-size:13px;font-weight:600}
.co-mod{font-size:12px;color:#888;margin-top:2px}
.co-price{font-size:13px;font-weight:600;color:#1D9E75;white-space:nowrap}
.srow{display:flex;justify-content:space-between;gap:10px;font-size:14px;color:#444;margin-top:8px}
.srow-total{font-weight:700;font-size:16px;color:#111;border-top:1px solid #e5e5e5;margin-top:12px;padding-top:12px}
.confirmed{text-align:center;padding:60px 20px}
.confirm-icon{width:64px;height:64px;border-radius:50%;background:#1D9E75;color:#fff;font-size:30px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
.order-ref{color:#666;font-size:14px;margin-top:8px}
.offline-confirmed{background:#fff3cd;border:1px solid #ffc107;border-radius:10px;padding:16px;margin-top:12px;font-size:13px;color:#856404}
.btn-p{display:inline-block;padding:11px 24px;background:#1D9E75;color:#fff;border-radius:22px;font-size:14px;font-weight:600;margin-top:20px;text-decoration:none}
.err{color:#e53e3e;font-size:14px;margin-bottom:12px}
@media(max-width:680px){.layout{flex-direction:column}.summary{width:100%;position:static}}
</style>
</head>
<body>
<header class="hdr">
  <a href="<?= $host ?>/basket.php" class="bk">&larr; Basket</a>
  <div class="logo"><span class="av">CG</span><strong>Checkout</strong></div>
  <span></span>
</header>
<div class="wrap">
<?php if ($order_placed): ?>
  <div class="confirmed">
    <div class="confirm-icon">&#10003;</div>
    <h2><?= $offline_saved ? 'Order saved locally!' : 'Order placed!' ?></h2>
    <?php if ($offline_saved): ?>
      <div class="offline-confirmed">
        &#128683; <strong>Offline order saved.</strong><br>
        This order is stored on this device and will automatically sync to the database when internet is restored.
      </div>
    <?php else: ?>
      <p>Order received and saved to database.</p>
      <?php if ($order_id): ?><p class="order-ref">Ref: <strong><?= esc(substr($order_id,0,8)) ?>&hellip;</strong></p><?php endif; ?>
    <?php endif; ?>
    <a href="<?= $host ?>/index.php" class="btn-p">New order</a>
  </div>

<?php elseif ($error): ?>
  <p class="err"><?= esc($error) ?></p>

<?php endif; ?>

<?php if (!$order_placed): ?>
<div class="layout">
  <div class="form-card">
    <div class="offline-notice" id="offlineNotice">
      &#128683; <strong>You are offline.</strong> This order will be saved locally and synced when connection returns.
    </div>
    <form method="POST" id="checkoutForm">
      <input type="hidden" name="offline" id="offlineFlag" value="0"/>
      <h2>Customer details <small style="font-weight:400;color:#999">(optional for POS)</small></h2>
      <label>Name<input type="text" name="name" placeholder="Customer name"/></label>
      <label>Phone<input type="tel" name="phone" placeholder="(555) 000-0000"/></label>
      <label>Email<input type="email" name="email" placeholder="customer@example.com"/></label>
      <h2 style="margin-top:20px">Order type</h2>
      <div class="rg">
        <label class="ro"><input type="radio" name="order_type" value="dine_in" checked/> Dine in</label>
        <label class="ro"><input type="radio" name="order_type" value="pickup"/> Pickup</label>
        <label class="ro"><input type="radio" name="order_type" value="delivery"/> Delivery</label>
      </div>
      <label style="margin-top:16px">Notes<textarea name="notes" rows="2" placeholder="Table #, allergies, special requests…"></textarea></label>
      <button type="submit" class="submit-btn" id="submitBtn">
        Place order &mdash; <?= fmt($total) ?>
      </button>
    </form>
  </div>
  <div class="summary">
    <h2>Order summary</h2>
    <?php foreach ($basket as $b): ?>
      <div class="co-item">
        <div>
          <p class="co-name"><?= esc($b['item_name']) ?><?php if($b['size_label']): ?> <em>(<?= esc($b['size_label']) ?>)</em><?php endif; ?><?php if(($b['qty']??1)>1): ?> &times;<?= (int)$b['qty'] ?><?php endif; ?></p>
          <?php foreach($b['selections'] as $sel): ?>
            <p class="co-mod"><?= esc($sel['group_label']) ?>: <?= esc($sel['choice']) ?><?php if($sel['price_delta']>0): ?> +<?= fmt($sel['price_delta']) ?><?php endif; ?></p>
          <?php endforeach; ?>
        </div>
        <div class="co-price"><?= fmt($b['total']*($b['qty']??1)) ?></div>
      </div>
    <?php endforeach; ?>
    <div class="srow"><span>Subtotal</span><span><?= fmt($subtotal) ?></span></div>
    <div class="srow"><span>Tax (6.63%)</span><span><?= fmt($tax) ?></span></div>
    <div class="srow srow-total"><span>Total</span><span><?= fmt($total) ?></span></div>
  </div>
</div>
<?php endif; ?>
</div>

<script src="/assets/js/idb.js"></script>
<script src="/assets/js/pos.js"></script>
<script>
// Detect offline and switch to offline mode
function updateOfflineUI() {
    var isOffline = !navigator.onLine;
    document.getElementById('offlineNotice').classList.toggle('show', isOffline);
    var btn = document.getElementById('submitBtn');
    if (btn) {
        btn.classList.toggle('offline-mode', isOffline);
        btn.textContent = isOffline
            ? 'Save order offline — <?= fmt($total) ?>'
            : 'Place order — <?= fmt($total) ?>';
    }
    document.getElementById('offlineFlag').value = isOffline ? '1' : '0';
}

window.addEventListener('online',  updateOfflineUI);
window.addEventListener('offline', updateOfflineUI);
updateOfflineUI();

// When offline, intercept form submit and save to IDB
document.getElementById('checkoutForm') && document.getElementById('checkoutForm').addEventListener('submit', async function(e) {
    if (!navigator.onLine) {
        e.preventDefault();
        var basket = <?= json_encode(array_values($basket)) ?>;
        var offlineOrder = {
            offline_id:  'order_' + Date.now() + '_' + Math.random().toString(36).slice(2),
            basket:      basket,
            order_type:  document.querySelector('[name=order_type]:checked').value,
            name:        document.querySelector('[name=name]').value,
            phone:       document.querySelector('[name=phone]').value,
            email:       document.querySelector('[name=email]').value,
            notes:       document.querySelector('[name=notes]').value,
        };
        await window.IDB.saveOrder(offlineOrder);
        // Clear session basket via AJAX
        await fetch('/checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'offline=1&order_type=dine_in',
        });
        window.location.href = '/checkout.php?offline_confirmed=1';
    }
});

// Show offline confirmed message if redirected
if (new URLSearchParams(window.location.search).get('offline_confirmed') === '1') {
    document.querySelector('.form-card') && (document.querySelector('.form-card').style.display = 'none');
    document.querySelector('.summary') && (document.querySelector('.summary').style.display = 'none');
    document.querySelector('.wrap').innerHTML = `
        <div class="confirmed">
            <div class="confirm-icon">&#128683;</div>
            <h2>Order saved locally!</h2>
            <div class="offline-confirmed">
                &#128683; <strong>Offline order saved.</strong><br>
                Stored on this device and will auto-sync when internet returns.
            </div>
            <a href="/index.php" class="btn-p">New order</a>
        </div>`;
    window.POS && window.POS.updatePendingBadge();
}
</script>
</body>
</html>
