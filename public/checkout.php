<?php
session_start();
require_once __DIR__ . '/../src/supabase.php';
require_once __DIR__ . '/../src/helpers.php';

$basket = $_SESSION['basket'] ?? [];
if (empty($basket)) { header('Location: index.php'); exit; }

$order_placed = false;
$order_id     = null;
$error        = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_type = $_POST['order_type'] ?? 'pickup';
    $name       = trim($_POST['name']  ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    $subtotal = array_sum(array_map(fn($b) => $b['total'] * ($b['qty'] ?? 1), $basket));
    $tax      = round($subtotal * 0.0663, 2);
    $total    = round($subtotal + $tax, 2);

    // Save customer
    $customer = null;
    if ($email) {
        $existing = sb_get('customers', ['email' => 'eq.' . $email]);
        $customer = $existing[0] ?? null;
        if (!$customer) {
            $rows     = sb_post('customers', ['name' => $name, 'email' => $email, 'phone' => $phone]);
            $customer = $rows[0] ?? null;
        }
    }

    // Save order
    $order_payload = [
        'restaurant_id' => $basket[0]['item_id'] ? null : null, // placeholder
        'status'        => 'pending',
        'order_type'    => $order_type,
        'subtotal'      => $subtotal,
        'tax'           => $tax,
        'total'         => $total,
        'notes'         => $notes,
    ];
    if ($customer) $order_payload['customer_id'] = $customer['id'];

    // Resolve restaurant id from first basket item's menu_item
    $first_rows = sb_get('menu_items', ['id' => 'eq.' . $basket[0]['item_id'], 'select' => 'restaurant_id']);
    if (!empty($first_rows)) $order_payload['restaurant_id'] = $first_rows[0]['restaurant_id'];

    $order_rows = sb_post('orders', $order_payload);
    $order      = $order_rows[0] ?? null;

    if ($order) {
        $order_id = $order['id'];
        // Save order_items
        foreach ($basket as $b) {
            $qty = $b['qty'] ?? 1;
            for ($q = 0; $q < $qty; $q++) {
                $oi_rows = sb_post('order_items', [
                    'order_id'   => $order_id,
                    'menu_item_id'=> $b['item_id'],
                    'unit_price' => $b['base_price'],
                    'line_total' => $b['total'],
                    'quantity'   => 1,
                    'notes'      => $b['size_label'] ?? '',
                ]);
                $oi = $oi_rows[0] ?? null;
                if ($oi && !empty($b['selections'])) {
                    foreach ($b['selections'] as $sel) {
                        if (!empty($sel['option_id'])) {
                            sb_post('order_item_modifiers', [
                                'order_item_id'     => $oi['id'],
                                'modifier_option_id'=> $sel['option_id'],
                                'price_delta'       => $sel['price_delta'] ?? 0,
                            ]);
                        }
                    }
                }
            }
        }
        $order_placed = true;
        $_SESSION['basket'] = [];
    } else {
        $error = 'Could not place order. Please try again.';
    }
}

$subtotal = array_sum(array_map(fn($b) => $b['total'] * ($b['qty'] ?? 1), $basket));
$tax      = $subtotal * 0.0663;
$total    = $subtotal + $tax;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Checkout — Cedar Grove</title>
  <link rel="stylesheet" href="assets/css/app.css" />
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a href="basket.php" class="back-btn">&larr; Basket</a>
    <div class="logo"><span class="logo-icon">CG</span><strong>Checkout</strong></div>
    <span></span>
  </div>
</header>

<main class="container checkout-page">

  <?php if ($order_placed): ?>
  <div class="order-confirmed">
    <div class="confirm-icon">&#10003;</div>
    <h2>Order placed!</h2>
    <p>Your order has been received. We'll have it ready shortly.</p>
    <?php if ($order_id): ?>
      <p class="order-ref">Reference: <strong><?= esc(substr($order_id,0,8)) ?>…</strong></p>
    <?php endif; ?>
    <a href="index.php" class="btn-primary" style="margin-top:20px">Back to menu</a>
  </div>

  <?php elseif ($error): ?>
    <p class="error-msg"><?= esc($error) ?></p>
    <a href="checkout.php" class="btn-primary">Try again</a>

  <?php else: ?>

  <div class="checkout-layout">

    <form method="POST" class="checkout-form">
      <h2>Your details</h2>

      <label>Name
        <input type="text" name="name" placeholder="Your name" />
      </label>
      <label>Phone
        <input type="tel" name="phone" placeholder="(555) 000-0000" />
      </label>
      <label>Email
        <input type="email" name="email" placeholder="you@example.com" />
      </label>

      <h2 style="margin-top:20px">Order type</h2>
      <div class="radio-group">
        <label class="radio-opt">
          <input type="radio" name="order_type" value="pickup" checked /> Pickup
        </label>
        <label class="radio-opt">
          <input type="radio" name="order_type" value="delivery" /> Delivery
        </label>
        <label class="radio-opt">
          <input type="radio" name="order_type" value="dine_in" /> Dine in
        </label>
      </div>

      <label style="margin-top:16px">Notes
        <textarea name="notes" rows="3" placeholder="Allergies, special requests…"></textarea>
      </label>

      <button type="submit" class="btn-primary" style="margin-top:20px;width:100%">
        Place order — <?= fmt($total) ?>
      </button>
    </form>

    <div class="checkout-summary">
      <h2>Order summary</h2>
      <?php foreach ($basket as $b): ?>
        <div class="co-item">
          <div>
            <p class="co-item__name"><?= esc($b['item_name']) ?>
              <?php if ($b['size_label']): ?><em>(<?= esc($b['size_label']) ?>)</em><?php endif; ?>
              <?php if (($b['qty'] ?? 1) > 1): ?>&times;<?= (int)$b['qty'] ?><?php endif; ?>
            </p>
            <?php foreach ($b['selections'] as $sel): ?>
              <p class="co-item__mod"><?= esc($sel['group_label']) ?>: <?= esc($sel['choice']) ?>
                <?php if ($sel['price_delta'] > 0): ?><span class="mod-price">+<?= fmt($sel['price_delta']) ?></span><?php endif; ?>
              </p>
            <?php endforeach; ?>
          </div>
          <div class="co-item__price"><?= fmt($b['total'] * ($b['qty'] ?? 1)) ?></div>
        </div>
      <?php endforeach; ?>
      <div class="summary-row" style="margin-top:12px"><span>Subtotal</span><span><?= fmt($subtotal) ?></span></div>
      <div class="summary-row"><span>Tax (6.63%)</span><span><?= fmt($tax) ?></span></div>
      <div class="summary-row summary-row--total"><span>Total</span><span><?= fmt($total) ?></span></div>
    </div>

  </div>
  <?php endif; ?>
</main>
</body>
</html>
