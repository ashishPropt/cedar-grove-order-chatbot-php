<?php
session_start();
require_once __DIR__ . '/../src/supabase.php';
require_once __DIR__ . '/../src/helpers.php';

$item_id = $_GET['id'] ?? '';
if (!$item_id) { header('Location: index.php'); exit; }

$item      = fetch_item($item_id);
if (!$item) { header('Location: index.php'); exit; }

$sizes     = fetch_item_sizes($item_id);
$modifiers = fetch_item_modifiers($item_id);

// Build the chatbot steps as JSON for JS
$steps = [];
// Step 0: size (if more than one)
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
} else {
    // single price — no step needed, baked into JS state
}
// Modifier steps
foreach ($modifiers as $mg) {
    $steps[] = [
        'key'        => $mg['id'],
        'label'      => $mg['name'],
        'ui_type'    => $mg['ui_type'],
        'min_select' => (int)$mg['min_select'],
        'max_select' => (int)$mg['max_select'],
        'options'    => array_map(fn($o) => [
            'id'             => $o['id'],
            'name'           => $o['name'],
            'price_delta'    => (float)$o['price_delta'],
            'default_selected' => (bool)$o['default_selected'],
        ], $mg['options']),
    ];
}

$base_price   = count($sizes) === 1 ? (float)$sizes[0]['price'] : 0;
$single_size  = count($sizes) === 1 ? $sizes[0]['label'] : null;
$item_json    = json_encode([
    'id'         => $item['id'],
    'name'       => $item['name'],
    'base_price' => $base_price,
    'single_size'=> $single_size,
]);
$steps_json = json_encode($steps);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= esc($item['name']) ?> — Cedar Grove</title>
  <link rel="stylesheet" href="assets/css/app.css" />
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a href="index.php" class="back-btn">&larr; Menu</a>
    <div class="logo">
      <span class="logo-icon">CG</span>
      <strong>Cedar Grove &amp; Gianni's</strong>
    </div>
    <a href="basket.php" class="basket-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
      </svg>
      Basket
      <?php
        $cnt = array_sum(array_column($_SESSION['basket'] ?? [], 'qty'));
        if ($cnt > 0) echo '<span class="basket-badge">' . $cnt . '</span>';
      ?>
    </a>
  </div>
</header>

<main class="container chatbot-page">
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
      <input class="chat-input" placeholder="Use the options above…" readonly />
    </div>

  </div>
</main>

<script>
const ITEM   = <?= $item_json ?>;
const STEPS  = <?= $steps_json ?>;
</script>
<script src="assets/js/chatbot.js"></script>
</body>
</html>
