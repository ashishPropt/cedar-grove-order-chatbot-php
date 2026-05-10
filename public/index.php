<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
session_start();
$base = __DIR__;
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

$restaurants  = fetch_menu();
$basket       = $_SESSION['basket'] ?? [];
$basket_count = array_sum(array_column($basket, 'qty'));

// Build absolute asset base URL
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$asset    = $scheme . '://' . $host;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Cedar Grove &amp; Gianni's &mdash; Menu</title>
  <link rel="stylesheet" href="<?= $asset ?>/assets/css/app.css" />
  <style>
    /* Fallback inline critical styles in case CSS file fails */
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f5f4;color:#1a1a1a}
    .site-header{background:#fff;border-bottom:1px solid #e5e5e5;padding:12px 20px;display:flex;align-items:center;justify-content:space-between}
    .logo{display:flex;align-items:center;gap:10px}
    .logo-icon{width:38px;height:38px;background:#1D9E75;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px}
    .logo strong{font-size:15px}
    .logo small{font-size:11px;color:#777}
    .basket-btn{display:flex;align-items:center;gap:6px;background:#1D9E75;color:#fff;padding:8px 14px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;position:relative}
    .basket-btn svg{width:16px;height:16px}
    .basket-badge{position:absolute;top:-4px;right:-4px;background:#e53e3e;color:#fff;width:18px;height:18px;border-radius:50%;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center}
    .container{max-width:1200px;margin:0 auto;padding:20px}
    .tab-bar{display:flex;gap:8px;margin-bottom:24px;border-bottom:2px solid #e5e5e5}
    .tab-btn{padding:10px 20px;border:none;background:none;font-size:15px;font-weight:500;color:#666;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px}
    .tab-btn.active{color:#1D9E75;border-color:#1D9E75}
    .rest-panel{display:none}
    .rest-panel.active{display:block}
    .menu-layout{display:flex;gap:24px}
    .cat-nav{width:200px;flex-shrink:0;background:#fff;border-radius:10px;padding:12px 0;border:1px solid #e5e5e5;position:sticky;top:70px;max-height:calc(100vh - 90px);overflow-y:auto}
    .cat-link{display:block;padding:8px 16px;font-size:13px;color:#444;border-left:3px solid transparent;text-decoration:none}
    .cat-link:hover,.cat-link.active{background:#f0faf6;color:#1D9E75;border-color:#1D9E75}
    .items-area{flex:1}
    .cat-section{margin-bottom:36px;scroll-margin-top:80px}
    .cat-title{font-size:18px;font-weight:700;margin-bottom:14px;padding-bottom:8px;border-bottom:2px solid #e5e5e5}
    .item-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
    .item-card{background:#fff;border:1px solid #e5e5e5;border-radius:10px;display:flex;flex-direction:column;cursor:pointer;transition:box-shadow .15s,transform .15s;text-decoration:none;color:inherit;position:relative;overflow:hidden}
    .item-card:hover{box-shadow:0 4px 18px rgba(0,0,0,.1);transform:translateY(-2px)}
    .item-card.featured{border-color:#1D9E75}
    .featured-badge{position:absolute;top:8px;right:8px;background:#1D9E75;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
    .item-card__body{padding:14px 14px 8px;flex:1}
    .item-card__name{font-size:14px;font-weight:600;color:#111;line-height:1.35}
    .item-card__desc{font-size:12px;color:#777;margin-top:4px;line-height:1.4}
    .item-card__footer{padding:8px 14px 12px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #f0f0f0}
    .item-card__price{font-size:13px;font-weight:600;color:#1D9E75}
    .item-card__cta{font-size:12px;color:#999}
    @media(max-width:768px){.menu-layout{flex-direction:column}.cat-nav{width:100%;position:static;display:flex;flex-wrap:wrap;gap:6px;padding:10px}.cat-link{padding:6px 12px;border:1px solid #ddd;border-radius:16px;border-left:none}.item-grid{grid-template-columns:repeat(auto-fill,minmax(160px,1fr))}}
  </style>
</head>
<body>
<header class="site-header">
  <div class="logo">
    <span class="logo-icon">CG</span>
    <div>
      <strong>Cedar Grove &amp; Gianni's</strong>
      <small>160 Stelton Rd, Piscataway NJ</small>
    </div>
  </div>
  <a href="<?= $asset ?>/basket.php" class="basket-btn">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
    </svg>
    Basket
    <?php if ($basket_count > 0): ?>
      <span class="basket-badge"><?= $basket_count ?></span>
    <?php endif; ?>
  </a>
</header>

<main class="container">
  <div class="tab-bar">
    <?php foreach ($restaurants as $i => $r): ?>
      <button class="tab-btn <?= $i===0?'active':'' ?>" data-target="rest-<?= $i ?>">
        <?= esc($r['name']) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <?php foreach ($restaurants as $i => $r): ?>
  <div class="rest-panel <?= $i===0?'active':'' ?>" id="rest-<?= $i ?>">
    <div class="menu-layout">
      <nav class="cat-nav">
        <?php foreach ($r['categories'] as $j => $cat): ?>
          <a class="cat-link" href="#cat-<?= $i ?>-<?= $j ?>"><?= esc($cat['name']) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="items-area">
        <?php foreach ($r['categories'] as $j => $cat): ?>
          <section class="cat-section" id="cat-<?= $i ?>-<?= $j ?>">
            <h2 class="cat-title"><?= esc($cat['name']) ?></h2>
            <?php if (empty($cat['items'])): ?>
              <p style="color:#999;font-size:14px">No items available.</p>
            <?php else: ?>
            <div class="item-grid">
              <?php foreach ($cat['items'] as $item): ?>
              <a class="item-card <?= $item['featured']?'featured':'' ?>"
                 href="<?= $asset ?>/item.php?id=<?= urlencode($item['id']) ?>">
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
                    <?= $item['min_price']>0 ? 'from '.fmt($item['min_price']) : 'See options' ?>
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

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.rest-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.target).classList.add('active');
  });
});
// Category scroll spy
const sections = document.querySelectorAll('.cat-section');
const catLinks  = document.querySelectorAll('.cat-link');
const observer  = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      catLinks.forEach(l => l.classList.remove('active'));
      const t = document.querySelector(`.cat-link[href="#${e.target.id}"]`);
      if (t) t.classList.add('active');
    }
  });
}, { threshold: 0.3 });
sections.forEach(s => observer.observe(s));
</script>
</body>
</html>
