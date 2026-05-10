<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
session_start();
$base = __DIR__;
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

$item_id = $_GET['id'] ?? '';
if (!$item_id) { header('Location: /index.php'); exit; }
$item = fetch_item($item_id);
if (!$item) { header('Location: /index.php'); exit; }
$sizes     = fetch_item_sizes($item_id);
$modifiers = fetch_item_modifiers($item_id);
$steps = [];
if (count($sizes) > 1) {
    $steps[] = ['key'=>'__size__','label'=>'Choose a size','ui_type'=>'radio','min_select'=>1,'max_select'=>1,
        'options'=>array_map(fn($s)=>['id'=>$s['id'],'name'=>$s['label'],'price_delta'=>(float)$s['price'],'is_size'=>true],$sizes)];
}
foreach ($modifiers as $mg) {
    $steps[] = ['key'=>$mg['id'],'label'=>$mg['name'],'ui_type'=>$mg['ui_type'],
        'min_select'=>(int)$mg['min_select'],'max_select'=>(int)$mg['max_select'],
        'options'=>array_map(fn($o)=>['id'=>$o['id'],'name'=>$o['name'],'price_delta'=>(float)$o['price_delta'],'default_selected'=>(bool)$o['default_selected']],$mg['options'])];
}
$base_price  = count($sizes)===1?(float)$sizes[0]['price']:0;
$single_size = count($sizes)===1?$sizes[0]['label']:null;
$basket_count = array_sum(array_column($_SESSION['basket']??[],'qty'));
$host = ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?=htmlspecialchars($item['name'])?> &mdash; Cedar Grove</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f5f4;color:#1a1a1a}
a{text-decoration:none;color:inherit}
.hdr{background:#fff;border-bottom:1px solid #e5e5e5;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.bk{font-size:14px;color:#1D9E75;font-weight:500}
.logo{display:flex;align-items:center;gap:10px}
.av{width:36px;height:36px;background:#1D9E75;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0}
.logo strong{font-size:15px}
.bkt{display:flex;align-items:center;gap:6px;background:#1D9E75;color:#fff;padding:8px 14px;border-radius:20px;font-size:13px;font-weight:600;position:relative}
.bkt svg{width:16px;height:16px}
.bdg{position:absolute;top:-4px;right:-4px;background:#e53e3e;color:#fff;width:18px;height:18px;border-radius:50%;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center}
.wrap{max-width:1200px;margin:0 auto;padding:20px;display:flex;justify-content:center}
.cw{width:100%;max-width:520px;background:#fff;border-radius:16px;border:1px solid #e5e5e5;box-shadow:0 2px 20px rgba(0,0,0,.08);display:flex;flex-direction:column;min-height:560px;overflow:hidden}
.ch{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid #eee;flex-shrink:0}
.ca{width:36px;height:36px;border-radius:50%;background:#1D9E75;color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cn{font-size:15px;font-weight:600;color:#111}
.ch small{font-size:12px;color:#888}
.cf{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:6px}
.ft{padding:10px 14px;border-top:1px solid #eee;flex-shrink:0}
.fi{width:100%;border:1px solid #ddd;border-radius:22px;padding:9px 16px;font-size:13px;background:#f8f8f8;color:#aaa;outline:none;cursor:default}
.msg{display:flex;gap:8px;align-items:flex-end;margin-bottom:4px}
.bot{flex-direction:row}.usr{flex-direction:row-reverse}
.av2{width:28px;height:28px;border-radius:50%;background:#1D9E75;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bb{max-width:76%;padding:10px 14px;border-radius:16px;font-size:14px;line-height:1.5}
.bot .bb{background:#f0f0f0;color:#111;border-radius:4px 16px 16px 16px}
.usr .bb{background:#1D9E75;color:#fff;border-radius:16px 4px 16px 16px}
.cw2{display:flex;flex-direction:column;gap:7px;align-self:flex-start;max-width:92%;margin:4px 0}
.hint{font-size:11px;color:#888}
.cr{display:flex;flex-wrap:wrap;gap:7px}
.chip{padding:7px 13px;border-radius:20px;border:1px solid #ddd;background:#fff;color:#333;font-size:13px;cursor:pointer;transition:background .12s,border-color .12s}
.chip:hover:not(:disabled){background:#f0f0f0;border-color:#bbb}
.sel{background:#e1f5ee;border-color:#1D9E75;color:#085041}
.chip:disabled{opacity:.5;cursor:default}
.conf{align-self:flex-start;padding:8px 18px;border-radius:20px;border:none;background:#1D9E75;color:#fff;font-size:13px;font-weight:600;cursor:pointer;margin-top:2px}
.conf:hover:not(:disabled){background:#0F6E56}
.conf:disabled{opacity:.5;cursor:default}
.rc{background:#f8f8f8;border:1px solid #e5e5e5;border-radius:12px;padding:14px 16px;font-size:13px;align-self:flex-start;max-width:92%}
.rt{font-weight:600;margin-bottom:8px;font-size:14px}
.rr{display:flex;justify-content:space-between;gap:12px;color:#555;margin-top:4px}
.rT{font-weight:600;color:#111;border-top:1px solid #e0e0e0;margin-top:8px;padding-top:8px}
.rm{color:#777;font-size:12px;padding-left:10px}
.ab{margin-top:10px;padding:9px 20px;border-radius:20px;border:none;background:#1D9E75;color:#fff;font-size:13px;font-weight:600;cursor:pointer;width:100%}
.ab:hover{background:#0F6E56}
</style>
</head>
<body>
<header class="hdr">
  <a href="<?=$host?>/index.php" class="bk">&larr; Menu</a>
  <div class="logo"><span class="av">CG</span><strong>Cedar Grove &amp; Gianni's</strong></div>
  <a href="<?=$host?>/basket.php" class="bkt">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
    Basket<?php if($basket_count>0):?><span class="bdg"><?=$basket_count?></span><?php endif;?>
  </a>
</header>
<div class="wrap">
  <div class="cw">
    <div class="ch">
      <div class="ca">CG</div>
      <div><p class="cn"><?=htmlspecialchars($item['name'])?></p><small>I'll walk you through the options</small></div>
    </div>
    <div class="cf" id="F"></div>
    <div class="ft"><input class="fi" placeholder="Use the options above&hellip;" readonly/></div>
  </div>
</div>
<script>
var ITEM={id:<?=json_encode($item['id'])?>,name:<?=json_encode($item['name'])?>,base_price:<?=(float)$base_price?>,single_size:<?=json_encode($single_size)?>};
var STEPS=<?=json_encode($steps)?>;
var HOST=<?=json_encode($host)?>;
var feed=document.getElementById('F');
var stepIdx=0,sizeLabel=ITEM.single_size||'',basePrice=ITEM.base_price,selections=[],multiSel=[];
function sb(){feed.scrollTop=feed.scrollHeight;}
function msg(r,t){
  var w=document.createElement('div');w.className='msg '+(r==='bot'?'bot':'usr');
  if(r==='bot')w.innerHTML='<div class="av2">CG</div>';
  var b=document.createElement('div');b.className='bb';b.textContent=t;
  w.appendChild(b);feed.appendChild(w);sb();
}
function lock(){feed.querySelectorAll('.chip,.conf').forEach(function(b){b.disabled=true;});}
function chips(opts,cb,multi){
  var w=document.createElement('div');w.className='cw2';
  if(multi){var h=document.createElement('p');h.className='hint';h.textContent='Select all that apply, then tap Confirm';w.appendChild(h);}
  var row=document.createElement('div');row.className='cr';
  var sel=new Set();
  opts.forEach(function(o){
    var lbl=o.price_delta>0?o.name+' (+$'+o.price_delta.toFixed(2)+')':o.name;
    var btn=document.createElement('button');btn.className='chip';btn.textContent=lbl;
    if(multi){
      btn.onclick=function(){if(btn.disabled)return;btn.classList.toggle('sel');if(sel.has(o.id))sel.delete(o.id);else sel.add(o.id);multiSel=opts.filter(function(x){return sel.has(x.id);});};
    }else{
      btn.onclick=function(){if(btn.disabled)return;lock();cb([o]);};
    }
    row.appendChild(btn);
  });
  w.appendChild(row);
  if(multi){
    var c=document.createElement('button');c.className='conf';c.textContent='Confirm';
    c.onclick=function(){if(c.disabled)return;lock();cb(multiSel);multiSel=[];};
    w.appendChild(c);
  }
  feed.appendChild(w);sb();
}
function run(){
  if(stepIdx>=STEPS.length){receipt();return;}
  var s=STEPS[stepIdx];
  if(s.key==='__size__'){
    msg('bot','What size would you like?');
    chips(s.options,function(ch){sizeLabel=ch[0].name;basePrice=ch[0].price_delta;msg('usr',ch[0].name);stepIdx++;run();});
    return;
  }
  var multi=s.ui_type==='checkbox';
  msg('bot',s.label+'?');
  chips(s.options,function(ch){
    var d=ch.length>0?ch.map(function(o){return o.name;}).join(', '):'None';
    msg('usr',d);
    ch.filter(function(o){return o.name!=='None';}).forEach(function(o){
      selections.push({option_id:o.id,group_label:s.label,choice:o.name,price_delta:o.price_delta});
    });
    stepIdx++;run();
  },multi);
}
function receipt(){
  var mt=selections.reduce(function(s,x){return s+(x.price_delta||0);},0);
  var tot=basePrice+mt;
  var card=document.createElement('div');card.className='rc';
  var h='<p class="rt">'+esc(ITEM.name)+'</p>';
  if(sizeLabel){h+='<div class="rr"><span>Size</span><span>'+esc(sizeLabel)+'</span></div>';h+='<div class="rr"><span>Base price</span><span>$'+basePrice.toFixed(2)+'</span></div>';}
  else{h+='<div class="rr"><span>Price</span><span>$'+basePrice.toFixed(2)+'</span></div>';}
  selections.forEach(function(s){h+='<div class="rm">'+esc(s.group_label)+': '+esc(s.choice)+(s.price_delta>0?' <span style="color:#1D9E75">+$'+s.price_delta.toFixed(2)+'</span>':'')+'</div>';});
  h+='<div class="rr rT"><span>Item total</span><span>$'+tot.toFixed(2)+'</span></div>';
  var btn=document.createElement('button');btn.className='ab';btn.textContent='Add to basket';
  btn.onclick=function(){addBasket(tot,btn);};
  card.innerHTML=h;card.appendChild(btn);feed.appendChild(card);sb();
}
async function addBasket(tot,btn){
  btn.disabled=true;btn.textContent='Adding…';
  try{
    var r=await fetch(HOST+'/add_to_basket.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({item_id:ITEM.id,item_name:ITEM.name,size_label:sizeLabel,base_price:basePrice,selections:selections,total:tot})});
    var d=await r.json();
    if(d.ok){
      btn.textContent='✓ Added to basket!';btn.style.background='#0F6E56';
      var bdg=document.querySelector('.bdg');
      if(!bdg){bdg=document.createElement('span');bdg.className='bdg';document.querySelector('.bkt').appendChild(bdg);}
      bdg.textContent=d.count;
      setTimeout(function(){
        var n=document.createElement('div');n.className='cw2';
        n.innerHTML='<div class="cr"><a href="'+HOST+'/index.php" class="chip">+ Add another item</a><a href="'+HOST+'/basket.php" class="chip sel">View basket &rarr;</a></div>';
        feed.appendChild(n);sb();
      },400);
    }else{btn.textContent='Error - try again';btn.disabled=false;}
  }catch(e){btn.textContent='Error - try again';btn.disabled=false;}
}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
msg('bot','Let me help you customise your '+ITEM.name+'!');
run();
</script>
</body>
</html>
