<?php
$base = dirname(__DIR__);
foreach ([
    $base . '/config/env.php',
    __DIR__ . '/config/env.php',
    __DIR__ . '/../config/env.php',
] as $envPath) {
    if (file_exists($envPath)) { require_once $envPath; break; }
}
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bid    = $_POST['bid']    ?? '';
    if ($action === 'remove') {
        $_SESSION['basket'] = array_values(array_filter($_SESSION['basket'] ?? [], fn($b) => $b['id'] !== $bid));
    } elseif ($action === 'inc' || $action === 'dec') {
        foreach ($_SESSION['basket'] as &$b) {
            if ($b['id'] === $bid) { $b['qty'] = max(1, ($b['qty'] ?? 1) + ($action === 'inc' ? 1 : -1)); break; }
        }
    }
    header('Location: basket.php'); exit;
}

$basket   = $_SESSION['basket'] ?? [];
$subtotal = array_sum(array_map(fn($b) => $b['total'] * ($b['qty'] ?? 1), $basket));
$tax      = $subtotal * 0.0663;
$total    = $subtotal + $tax;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Basket &mdash; Cedar Grove</title>
  <link rel="stylesheet" href="assets/css/app.css" />
</head>
<body>
<header class="site-header"><div class="header-inner">
  <a href="index.php" class="back-btn">&larr; Menu</a>
  <div class="logo"><span class="logo-icon">CG</span><strong>Your Basket</strong></div>
  <span></span>
</div></header>
<main class="container basket-page">
  <?php if (empty($basket)): ?>
    <div class="basket-empty"><p>Your basket is empty.</p><a href="index.php" class="btn-primary">Browse the menu</a></div>
  <?php else: ?>
  <div class="basket-layout">
    <div class="basket-items">
      <?php foreach ($basket as $b): ?>
      <div class="basket-card">
        <div class="basket-card__top">
          <div>
            <p class="basket-card__name"><?= esc($b['item_name']) ?></p>
            <?php if ($b['size_label']): ?><p class="basket-card__size"><?= esc($b['size_label']) ?></p><?php endif; ?>
            <?php foreach ($b['selections'] as $sel): ?>
              <p class="basket-card__mod"><?= esc($sel['group_label']) ?>: <?= esc($sel['choice']) ?>
                <?php if ($sel['price_delta'] > 0): ?><span class="mod-price">+<?= fmt($sel['price_delta']) ?></span><?php endif; ?>
              </p>
            <?php endforeach; ?>
          </div>
          <div class="basket-card__price"><?= fmt($b['total'] * ($b['qty'] ?? 1)) ?></div>
        </div>
        <div class="basket-card__actions">
          <form method="POST" style="display:inline"><input type="hidden" name="bid" value="<?= esc($b['id']) ?>"><input type="hidden" name="action" value="dec"><button class="qty-btn">&minus;</button></form>
          <span class="qty-val"><?= (int)($b['qty'] ?? 1) ?></span>
          <form method="POST" style="display:inline"><input type="hidden" name="bid" value="<?= esc($b['id']) ?>"><input type="hidden" name="action" value="inc"><button class="qty-btn">+</button></form>
          <form method="POST" style="display:inline;margin-left:auto"><input type="hidden" name="bid" value="<?= esc($b['id']) ?>"><input type="hidden" name="action" value="remove"><button class="remove-btn">Remove</button></form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="basket-summary">
      <h2>Order summary</h2>
      <div class="summary-row"><span>Subtotal</span><span><?= fmt($subtotal) ?></span></div>
      <div class="summary-row"><span>Tax (6.63%)</span><span><?= fmt($tax) ?></span></div>
      <div class="summary-row summary-row--total"><span>Total</span><span><?= fmt($total) ?></span></div>
      <a href="checkout.php" class="btn-primary" style="margin-top:16px;display:block;text-align:center">Proceed to checkout</a>
      <a href="index.php" class="btn-secondary" style="margin-top:8px;display:block;text-align:center">+ Add more items</a>
    </div>
  </div>
  <?php endif; ?>
</main>
</body></html>
