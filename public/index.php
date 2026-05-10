<?php
session_start();

// ---- bootstrap ----
$base = dirname(__DIR__);

// Try multiple possible locations for env.php
foreach ([
    $base . '/config/env.php',
    __DIR__  . '/config/env.php',
    __DIR__  . '/../config/env.php',
] as $envPath) {
    if (file_exists($envPath)) { require_once $envPath; break; }
}

// Show a clear error if credentials still missing
if (!defined('SUPABASE_URL') || !defined('SUPABASE_ANON_KEY')) {
    die(renderError(
        'Configuration missing',
        'config/env.php not found or missing constants.<br>'
        . 'Create it on the server with SUPABASE_URL and SUPABASE_ANON_KEY defined.<br>'
        . 'Expected path: <code>' . $base . '/config/env.php</code>'
    ));
}

require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

// ---- fetch data ----
$restaurants = fetch_menu();
$basket      = $_SESSION['basket'] ?? [];
$basket_count = array_sum(array_column($basket, 'qty'));

if (empty($restaurants)) {
    // Try to give a useful debug message
    $test = sb_get('restaurants', []);
    $debug = defined('SUPABASE_URL')
        ? 'Supabase URL: ' . SUPABASE_URL . '<br>Got ' . count($test) . ' restaurants. Check RLS policies and anon key.'
        : 'SUPABASE_URL not defined.';
    die(renderError('No restaurant data returned', $debug));
}

function renderError(string $title, string $msg): string {
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <title>Error</title>
    <style>body{font-family:sans-serif;padding:40px;background:#fff5f5}
    h2{color:#c53030}code{background:#f0f0f0;padding:2px 6px;border-radius:4px}</style>
    </head><body><h2>&#9888; $title</h2><p>$msg</p></body></html>";
}
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
