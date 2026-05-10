<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
session_start();
$base = __DIR__;
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';
$restaurants  = fetch_menu();
$basket_count = array_sum(array_column($_SESSION['basket'] ?? [], 'qty'));
$host = ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Cedar Grove &amp; Gianni's</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f5f4;color:#1a1a1a}a{text-decoration:none;color:inherit}
.hdr{background:#fff;border-bottom:1px solid #e5e5e5;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.logo{display:flex;align-items:center;gap:10px}.av{width:38px;height:38px;background:#1D9E75;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0}
.logo strong{font-size:15px;display:block}.logo small{font-size:11px;color:#777}
.bkt{display:flex;align-items:center;gap:6px;background:#1D9E75;color:#fff;padding:8px 14px;border-radius:20px;font-size:13px;font-weight:600;position:relative}
.bkt svg{width:16px;height:16px}.bdg{position:absolute;top:-4px;right:-4px;background:#e53e3e;color:#fff;width:18px;height:18px;border-radius:50%;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center}
.wrap{max-width:1200px;margin:0 auto;padding:20px}
.tabs{display:flex;gap:8px;margin-bottom:24px;border-bottom:2px solid #e5e5e5}
.tab{padding:10px 20px;border:none;background:none;font-size:15px;font-weight:500;color:#666;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px}
.tab.on{color:#1D9E75;border-color:#1D9E75}
.pnl{display:none}.pnl.on{display:block}
.ml{display:flex;gap:24px;align-items:flex-start}
.cnav{width:200px;flex-shrink:0;background:#fff;border-radius:10px;padding:12px 0;border:1px solid #e5e5e5;position:sticky;top:70px;max-height:calc(100vh - 90px);overflow-y:auto}
.cl{display:block;padding:8px 16px;font-size:13px;color:#444;border-left:3px solid transparent}
.cl:hover,.cl.on{background:#f0faf6;color:#1D9E75;border-color:#1D9E75}
.ia{flex:1}.cs{margin-bottom:36px;scroll-margin-top:80px}
.ct{font-size:18px;font-weight:700;margin-bottom:14px;padding-bottom:8px;border-bottom:2px solid #e5e5e5}
.ig{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.ic{background:#fff;border:1px solid #e5e5e5;border-radius:10px;display:flex;flex-direction:column;cursor:pointer;transition:box-shadow .15s,transform .15s;position:relative;overflow:hidden}
.ic:hover{box-shadow:0 4px 18px rgba(0,0,0,.1);transform:translateY(-2px)}.ic.ft{border-color:#1D9E75}
.fb{position:absolute;top:8px;right:8px;background:#1D9E75;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
.ib{padding:14px 14px 8px;flex:1}.in{font-size:14px;font-weight:600;color:#111;line-height:1.35}
.id{font-size:12px;color:#777;margin-top:4px;line-height:1.4}
.if{padding:8px 14px 12px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #f0f0f0}
.ip{font-size:13px;font-weight:600;color:#1D9E75}.io{font-size:12px;color:#999}
@media(max-width:768px){.ml{flex-direction:column}.cnav{width:100%;position:static;max-height:none;display:flex;flex-wrap:wrap;gap:6px;padding:10px}.cl{padding:6px 12px;border:1px solid #ddd;border-radius:16px;border-left:none}.ig{grid-template-columns:repeat(auto-fill,minmax(160px,1fr))}}
</style>
</head>
<body>
<header class="hdr">
  <div class="logo">
    <span class="av">CG</span>
    <div><strong>Cedar Grove &amp; Gianni's</strong><small>160 Stelton Rd, Piscataway NJ &middot; 732-752-6900</small></div>
  </div>
  <a href="<?=$host?>/basket.php" class="bkt">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
    Basket<?php if($basket_count>0):?><span class="bdg"><?=$basket_count?></span><?php endif;?>
  </a>
</header>
<div class="wrap">
<?php if(empty($restaurants)):?>
  <div style="padding:40px;text-align:center;background:#fff;border-radius:10px;margin-top:20px">
    <h2 style="color:#e53e3e;margin-bottom:12px">&#9888; No menu data returned</h2>
    <p style="color:#666;margin-bottom:8px">Supabase URL: <?=defined('SUPABASE_URL')?SUPABASE_URL:'NOT DEFINED'?></p>
    <p style="color:#666">Check that RLS policies allow anon reads on restaurants, categories, menu_items, item_sizes</p>
  </div>
<?php else:?>
  <div class="tabs">
    <?php foreach($restaurants as $i=>$r):?>
      <button class="tab <?=$i===0?'on':''?>" onclick="showTab(<?=$i?>)"><?=htmlspecialchars($r['name'])?></button>
    <?php endforeach;?>
  </div>
  <?php foreach($restaurants as $i=>$r):?>
  <div class="pnl <?=$i===0?'on':''?>" id="pnl<?=$i?>">
    <div class="ml">
      <nav class="cnav">
        <?php foreach($r['categories'] as $j=>$c):?>
          <a class="cl" href="#s<?=$i?>-<?=$j?>"><?=htmlspecialchars($c['name'])?></a>
        <?php endforeach;?>
      </nav>
      <div class="ia">
        <?php foreach($r['categories'] as $j=>$c):?>
          <section class="cs" id="s<?=$i?>-<?=$j?>">
            <h2 class="ct"><?=htmlspecialchars($c['name'])?></h2>
            <?php if(empty($c['items'])):?>
              <p style="color:#999;font-size:14px;padding:10px 0">No items in this category.</p>
            <?php else:?>
            <div class="ig">
              <?php foreach($c['items'] as $item):?>
              <a class="ic <?=$item['featured']?'ft':''?>" href="<?=$host?>/item.php?id=<?=urlencode($item['id'])?>">
                <?php if($item['featured']):?><span class="fb">Featured</span><?php endif;?>
                <div class="ib">
                  <p class="in"><?=htmlspecialchars($item['name'])?></p>
                  <?php if($item['description']):?><p class="id"><?=htmlspecialchars($item['description'])?></p><?php endif;?>
                </div>
                <div class="if">
                  <span class="ip"><?=$item['min_price']>0?'from $'.number_format($item['min_price'],2):'See options'?></span>
                  <span class="io">Order &rarr;</span>
                </div>
              </a>
              <?php endforeach;?>
            </div>
            <?php endif;?>
          </section>
        <?php endforeach;?>
      </div>
    </div>
  </div>
  <?php endforeach;?>
<?php endif;?>
</div>
<script>
function showTab(i){document.querySelectorAll('.tab').forEach(function(t,idx){t.classList.toggle('on',idx===i);});document.querySelectorAll('.pnl').forEach(function(p,idx){p.classList.toggle('on',idx===i);});}
var obs=new IntersectionObserver(function(ee){ee.forEach(function(e){if(e.isIntersecting){document.querySelectorAll('.cl').forEach(function(l){l.classList.remove('on');});var t=document.querySelector('.cl[href="#'+e.target.id+'"]');if(t)t.classList.add('on');}});},{threshold:0.3});
document.querySelectorAll('.cs').forEach(function(s){obs.observe(s);});
</script>
</body>
</html>
