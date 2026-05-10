<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

$base = dirname(__DIR__);

// Try every possible path for env.php
$envLoaded = false;
foreach ([
    $base . '/config/env.php',
    __DIR__  . '/config/env.php',
    __DIR__  . '/../config/env.php',
    '/home/' . get_current_user() . '/config/env.php',
] as $envPath) {
    if (file_exists($envPath)) {
        require_once $envPath;
        $envLoaded = true;
        break;
    }
}

if (!$envLoaded || !defined('SUPABASE_URL')) {
    die('<b>ERROR:</b> config/env.php not found.<br>'
      . 'Searched:<br><pre>'
      . implode("\n", [
            $base . '/config/env.php',
            __DIR__  . '/config/env.php',
            __DIR__  . '/../config/env.php',
        ])
      . '</pre>'
      . '__DIR__ = ' . __DIR__ . '<br>'
      . 'dirname(__DIR__) = ' . dirname(__DIR__)
    );
}

// Test if src files exist
foreach ([$base.'/src/supabase.php', $base.'/src/helpers.php'] as $f) {
    if (!file_exists($f)) die('<b>ERROR:</b> Missing file: ' . $f
        . '<br>__DIR__=' . __DIR__
        . '<br>base=' . $base);
}

require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

// Quick Supabase connectivity test
$ping = sb_get('restaurants', ['select' => 'id,name', 'limit' => '1']);
if (empty($ping)) {
    // Show detailed curl error
    $ch = curl_init(SUPABASE_URL . '/rest/v1/restaurants?select=name&limit=1');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_VERBOSE        => false,
        CURLOPT_HTTPHEADER => [
            'apikey: '        . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        ],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    die('<b>ERROR:</b> Supabase returned no data.<br>'
      . 'HTTP status: ' . $code . '<br>'
      . 'cURL error: ' . ($err ?: 'none') . '<br>'
      . 'Response: <pre>' . htmlspecialchars($res) . '</pre>'
      . 'URL tried: ' . SUPABASE_URL . '/rest/v1/restaurants'
    );
}

$restaurants  = fetch_menu();
$basket       = $_SESSION['basket'] ?? [];
$basket_count = array_sum(array_column($basket, 'qty'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Cedar Grove &amp; Gianni's &mdash; Menu</title>
  <link rel="stylesheet" href="assets/css/app.css" />
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <div class="logo">
      <span class="logo-icon">CG</span>
      <div>
        <strong>Cedar Grove &amp; Gianni's</strong>
        <small>160 Stelton Rd, Piscataway NJ &middot; 732-752-6900</small>
      </div>
    </div>
    <a href="basket.php" class="basket-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
      </svg>
      Basket
      <?php if ($basket_count > 0): ?>
        <span class="basket-badge"><?= $basket_count ?></span>
      <?php endif; ?>
    </a>
  </div>
</header>

<main class="container">

  <div class="tab-bar">
    <?php foreach ($restaurants as $i => $r): ?>
      <button class="tab-btn <?= $i === 0 ? 'active' : '' ?>"
              data-target="rest-<?= $i ?>">
        <?= esc($r['name']) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <?php foreach ($restaurants as $i => $r): ?>
  <div class="rest-panel <?= $i === 0 ? 'active' : '' ?>" id="rest-<?= $i ?>">
    <div class="menu-layout">

      <nav class="cat-nav">
        <?php foreach ($r['categories'] as $j => $cat): ?>
          <a class="cat-link" href="#cat-<?= $i ?>-<?= $j ?>">
            <?= esc($cat['name']) ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="items-area">
        <?php foreach ($r['categories'] as $j => $cat): ?>
          <section class="cat-section" id="cat-<?= $i ?>-<?= $j ?>">
            <h2 class="cat-title"><?= esc($cat['name']) ?></h2>
            <?php if (empty($cat['items'])): ?>
              <p class="empty">No items available.</p>
            <?php else: ?>
            <div class="item-grid">
              <?php foreach ($cat['items'] as $item): ?>
              <a class="item-card <?= $item['featured'] ? 'featured' : '' ?>"
                 href="item.php?id=<?= urlencode($item['id']) ?>">
                <?php if ($item['featured']): ?>
                  <span class="featured-badge">Featured</span>
                <?php endif; ?>
                <div class="item-card__body">
                  <p class="item-card__name"><?= esc($item['name']) ?></p>
                  <?php if ($item['description']): ?>
                    <p class="item-card__desc"><?= esc($item['description']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="item-card__footer">
                  <span class="item-card__price">
                    <?= $item['min_price'] > 0 ? 'from ' . fmt($item['min_price']) : 'See options' ?>
                  </span>
                  <span class="item-card__cta">Order &rarr;</span>
                </div>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      </div>

    </div>
  </div>
  <?php endforeach; ?>

</main>

<script src="assets/js/menu.js"></script>
</body>
</html>
