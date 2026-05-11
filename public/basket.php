<?php
session_start();
$base = __DIR__;
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

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
$host = ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Basket &mdash; Cedar Grove</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f5f4;color:#1a1a1a}a{text-decoration:none;color:inherit}
.hdr{background:#fff;border-bottom:1px solid #e5e5e5;padding:12px 20px;display:flex;align-items:center;justify-content:space-between}
.bk{font-size:14px;color:#1D9E75;font-weight:500}.logo{display:flex;align-items:center;gap:10px}
.av{width:36px;height:36px;background:#1D9E75;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px}
.logo strong{font-size:15px}
.wrap{max-width:900px;margin:0 auto;padding:20px}
.empty{text-align:center;padding:60px 20px}
.empty p{font-size:18px;color:#666;margin-bottom:16px}
.layout{display:flex;gap:24px;align-items:flex-start}
.items{flex:1;display:flex;flex-direction:column;gap:12px}
.card{background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:14px 16px}
.card-top{display:flex;justify-content:space-between;gap:12px}
.card-name{font-weight:600;font-size:15px}
.card-size{font-size:13px;color:#777;margin-top:2px}
.card-mod{font-size:12px;color:#888;margin-top:2px}
.card-price{font-weight:700;color:#1D9E75;font-size:15px;white-space:nowrap}
.card-actions{display:flex;align-items:center;gap:8px;margin-top:10px;border-top:1px solid #f0f0f0;padding-top:10px;flex-wrap:wrap}
.qty-btn{width:28px;height:28px;border-radius:50%;border:1px solid #ddd;background:#fff;font-size:16px;cursor:pointer;line-height:1}
.qty-btn:hover{background:#f0f0f0}
.qty-val{font-weight:600;font-size:15px;min-width:20px;text-align:center}
.edit-btn{padding:5px 12px;border-radius:14px;border:1px solid #1D9E75;background:#f0faf6;color:#1D9E75;font-size:12px;cursor:pointer;text-decoration:none;display:inline-block}
.edit-btn:hover{background:#e1f5ee}
.remove-btn{padding:5px 12px;border-radius:14px;border:1px solid #fca5a5;background:#fff5f5;color:#e53e3e;font-size:12px;cursor:pointer;margin-left:auto}
.remove-btn:hover{background:#fee2e2}
.mod-price{color:#1D9E75;margin-left:4px}
.summary{width:260px;flex-shrink:0;background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:20px;position:sticky;top:20px}
.summary h2{font-size:16px;margin-bottom:14px}
.srow{display:flex;justify-content:space-between;gap:10px;font-size:14px;color:#444;margin-top:8px}
.srow-total{font-weight:700;font-size:16px;color:#111;border-top:1px solid #e5e5e5;margin-top:12px;padding-top:12px}
.btn-p{display:block;text-align:center;padding:11px 22px;background:#1D9E75;color:#fff;border-radius:22px;font-size:14px;font-weight:600;margin-top:16px;text-decoration:none}
.btn-p:hover{background:#0F6E56}
.btn-s{display:block;text-align:center;padding:11px 22px;background:#fff;color:#1D9E75;border:1px solid #1D9E75;border-radius:22px;font-size:14px;font-weight:600;margin-top:8px;text-decoration:none}
.btn-s:hover{background:#f0faf6}
@media(max-width:680px){.layout{flex-direction:column}.summary{width:100%;position:static}}
</style>
</head>
<body>
<header class="hdr">
  <a href="<?= $host ?>/index.php" class="bk">&larr; Menu</a>
  <div class="logo"><span class="av">CG</span><strong>Your Basket</strong></div>
  <span></span>
</header>
<div class="wrap">
  <?php if (empty($basket)): ?>
    <div class="empty"><p>Your basket is empty.</p><a href="<?= $host ?>/index.php" class="btn-p">Browse the menu</a></div>
  <?php else: ?>
  <div class="layout">
    <div class="items">
      <?php foreach ($basket as $b): ?>
      <div class="card">
        <div class="card-top">
          <div>
            <p class="card-name"><?= esc($b['item_name']) ?></p>
            <?php if ($b['size_label']): ?><p class="card-size"><?= esc($b['size_label']) ?></p><?php endif; ?>
            <?php foreach ($b['selections'] as $sel): ?>
              <p class="card-mod"><?= esc($sel['group_label']) ?>: <?= esc($sel['choice']) ?>
                <?php if ($sel['price_delta'] > 0): ?><span class="mod-price">+<?= fmt($sel['price_delta']) ?></span><?php endif; ?>
              </p>
            <?php endforeach; ?>
          </div>
          <div class="card-price"><?= fmt($b['total'] * ($b['qty'] ?? 1)) ?></div>
        </div>
        <div class="card-actions">
          <!-- Qty controls -->
          <form method="POST" style="display:inline">
            <input type="hidden" name="bid" value="<?= esc($b['id']) ?>">
            <input type="hidden" name="action" value="dec">
            <button class="qty-btn">&minus;</button>
          </form>
          <span class="qty-val"><?= (int)($b['qty'] ?? 1) ?></span>
          <form method="POST" style="display:inline">
            <input type="hidden" name="bid" value="<?= esc($b['id']) ?>">
            <input type="hidden" name="action" value="inc">
            <button class="qty-btn">+</button>
          </form>
          <!-- Edit button -->
          <a class="edit-btn"
             href="<?= $host ?>/item.php?id=<?= urlencode($b['item_id']) ?>&edit=<?= urlencode($b['id']) ?>">
            &#9998; Edit
          </a>
          <!-- Remove button -->
          <form method="POST" style="display:inline;margin-left:auto">
            <input type="hidden" name="bid" value="<?= esc($b['id']) ?>">
            <input type="hidden" name="action" value="remove">
            <button class="remove-btn">Remove</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="summary">
      <h2>Order summary</h2>
      <div class="srow"><span>Subtotal</span><span><?= fmt($subtotal) ?></span></div>
      <div class="srow"><span>Tax (6.63%)</span><span><?= fmt($tax) ?></span></div>
      <div class="srow srow-total"><span>Total</span><span><?= fmt($total) ?></span></div>
      <a href="<?= $host ?>/checkout.php" class="btn-p">Proceed to checkout</a>
      <a href="<?= $host ?>/index.php" class="btn-s">+ Add more items</a>
    </div>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
