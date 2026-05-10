<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
session_start();
$base = __DIR__;
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

$item_id = $_GET['id'] ?? '';
if (!$item_id) { header('Location: index.php'); exit; }
$item = fetch_item($item_id);
if (!$item) { header('Location: index.php'); exit; }
$sizes     = fetch_item_sizes($item_id);
$modifiers = fetch_item_modifiers($item_id);

$steps = [];
if (count($sizes) > 1) {
    $steps[] = ['key'=>'__size__','label'=>'What size would you like?','ui_type'=>'radio','min_select'=>1,'max_select'=>1,
        'options'=>array_map(fn($s)=>['id'=>$s['id'],'name'=>$s['label'],'price_delta'=>(float)$s['price'],'is_size'=>true],$sizes)];
}
foreach ($modifiers as $mg) {
    $steps[] = ['key'=>$mg['id'],'label'=>$mg['name'].'?','ui_type'=>$mg['ui_type'],
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
*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f5f4;color:#1a1a1a}a{text-decoration:none;color:inherit}
.hdr{background:#fff;border-bottom:1px solid #e5e5e5;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.bk{font-size:14px;color:#1D9E75;font-weight:500}
.logo{display:flex;align-items:center;gap:10px}
.av{width:36px;height:36px;background:#1D9E75;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0}
.logo strong{font-size:15px}
.bkt{display:flex;align-items:center;gap:6px;background:#1D9E75;color:#fff;padding:8px 14px;border-radius:20px;font-size:13px;font-weight:600;position:relative}
.bkt svg{width:16px;height:16px}
.bdg{position:absolute;top:-4px;right:-4px;background:#e53e3e;color:#fff;width:18px;height:18px;border-radius:50%;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center}
.wrap{max-width:600px;margin:0 auto;padding:20px}
.cw{background:#fff;border-radius:16px;border:1px solid #e5e5e5;box-shadow:0 2px 20px rgba(0,0,0,.08);overflow:hidden}

/* Start screen */
.start-screen{padding:40px 30px;text-align:center}
.start-icon{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1D9E75,#0F6E56);color:#fff;font-size:32px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px}
.start-screen h2{font-size:20px;color:#111;margin-bottom:8px}
.start-screen p{font-size:14px;color:#666;margin-bottom:24px;line-height:1.6}
.start-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.btn-voice{padding:12px 24px;border-radius:24px;border:none;background:linear-gradient(135deg,#1D9E75,#0F6E56);color:#fff;font-size:15px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;transition:transform .15s,box-shadow .15s}
.btn-voice:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(29,158,117,.4)}
.btn-tap{padding:12px 24px;border-radius:24px;border:1px solid #ddd;background:#fff;color:#555;font-size:15px;font-weight:500;cursor:pointer;transition:background .15s}
.btn-tap:hover{background:#f5f5f5}

/* Chat UI */
.chat-ui{display:none}
.ch{display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid #eee;background:linear-gradient(135deg,#1D9E75,#0F6E56)}
.ca{width:42px;height:42px;border-radius:50%;background:rgba(255,255,255,.2);color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid rgba(255,255,255,.3)}
.ch-info strong{font-size:15px;color:#fff;display:block}
.ch-info small{font-size:12px;color:rgba(255,255,255,.8)}

/* Voice bar */
.vbar{display:flex;align-items:center;gap:10px;padding:10px 16px;background:#f0fdf8;border-bottom:1px solid #d1fae5}
.mic{width:42px;height:42px;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;transition:all .2s}
.mic.idle{background:#e5e5e5;color:#555}
.mic.listening{background:#e53e3e;color:#fff;animation:pulse 1s infinite}
.mic.speaking{background:#1D9E75;color:#fff}
@keyframes pulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(229,62,62,.4)}50%{transform:scale(1.05);box-shadow:0 0 0 8px rgba(229,62,62,0)}}
.vstatus{font-size:12px;color:#065f46;flex:1;min-width:0}
.vstatus strong{color:#065f46}
.waves{display:none;align-items:center;gap:2px;height:16px;flex-shrink:0}
.waves.show{display:flex}
.waves span{width:3px;background:#1D9E75;border-radius:3px;animation:wave 1s ease-in-out infinite}
.waves span:nth-child(2){animation-delay:.1s}.waves span:nth-child(3){animation-delay:.2s}.waves span:nth-child(4){animation-delay:.3s}.waves span:nth-child(5){animation-delay:.4s}
@keyframes wave{0%,100%{height:3px}50%{height:14px}}

/* Feed */
.cf{padding:14px;display:flex;flex-direction:column;gap:8px;min-height:260px;max-height:360px;overflow-y:auto}
.msg{display:flex;gap:8px;align-items:flex-end}
.bot{flex-direction:row}.usr{flex-direction:row-reverse}
.av2{width:26px;height:26px;border-radius:50%;background:#1D9E75;color:#fff;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bb{max-width:78%;padding:9px 13px;border-radius:14px;font-size:14px;line-height:1.5}
.bot .bb{background:#f0f0f0;color:#111;border-radius:3px 14px 14px 14px}
.usr .bb{background:#1D9E75;color:#fff;border-radius:14px 3px 14px 14px}

/* Chips */
.ca2{padding:0 14px 12px;display:flex;flex-direction:column;gap:6px}
.hint{font-size:11px;color:#888}
.crow{display:flex;flex-wrap:wrap;gap:7px}
.chip{padding:7px 13px;border-radius:20px;border:1px solid #ddd;background:#fff;color:#333;font-size:13px;cursor:pointer;transition:all .12s}
.chip:hover:not(:disabled){background:#f0faf6;border-color:#1D9E75;color:#1D9E75}
.chip.sel{background:#e1f5ee;border-color:#1D9E75;color:#085041}
.chip:disabled{opacity:.5;cursor:default}
.conf{padding:8px 18px;border-radius:20px;border:none;background:#1D9E75;color:#fff;font-size:13px;font-weight:600;cursor:pointer}
.conf:disabled{opacity:.5;cursor:default}

/* Receipt */
.rc{margin:8px 14px 14px;background:#f8f8f8;border:1px solid #e5e5e5;border-radius:12px;padding:14px}
.rt{font-weight:600;font-size:14px;margin-bottom:8px}
.rr{display:flex;justify-content:space-between;gap:12px;color:#555;margin-top:4px;font-size:13px}
.rT{font-weight:700;color:#111;border-top:1px solid #e0e0e0;margin-top:8px;padding-top:8px}
.rm{color:#777;font-size:12px;padding-left:10px;margin-top:2px}
.ab{margin-top:10px;padding:12px;border-radius:12px;border:none;background:#1D9E75;color:#fff;font-size:14px;font-weight:600;cursor:pointer;width:100%}
.ab:hover{background:#0F6E56}
.nav-row{display:flex;gap:8px;margin-top:8px}
.nc{padding:9px 14px;border-radius:20px;font-size:13px;font-weight:500;text-align:center;flex:1;text-decoration:none}
.nc-s{border:1px solid #ddd;color:#444;background:#fff}
.nc-p{background:#1D9E75;color:#fff}
</style>
</head>
<body>
<header class="hdr">
  <a href="<?=$host?>/index.php" class="bk">&larr; Menu</a>
  <div class="logo"><span class="av">CG</span><strong>Cedar Grove</strong></div>
  <a href="<?=$host?>/basket.php" class="bkt">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
    Basket<?php if($basket_count>0):?><span class="bdg"><?=$basket_count?></span><?php endif;?>
  </a>
</header>

<div class="wrap">
  <div class="cw">

    <!-- START SCREEN -->
    <div class="start-screen" id="startScreen">
      <div class="start-icon">&#127908;</div>
      <h2><?=htmlspecialchars($item['name'])?></h2>
      <p>How would you like to order?<br>
         <strong>Voice</strong> &mdash; I'll read the questions and listen to your answers.<br>
         <strong>Tap</strong> &mdash; just tap the options on screen.</p>
      <div class="start-btns">
        <button class="btn-voice" id="btnVoice">
          &#127908; Start voice order
        </button>
        <button class="btn-tap" id="btnTap">
          &#128393; Tap to order
        </button>
      </div>
    </div>

    <!-- CHAT UI (hidden until mode chosen) -->
    <div class="chat-ui" id="chatUI">
      <div class="ch">
        <div class="ca">CG</div>
        <div class="ch-info">
          <strong><?=htmlspecialchars($item['name'])?></strong>
          <small id="modeLabel">Order assistant</small>
        </div>
      </div>

      <!-- Voice bar (only shown in voice mode) -->
      <div class="vbar" id="vbar" style="display:none">
        <button class="mic idle" id="micBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
            <line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>
          </svg>
        </button>
        <div class="vstatus" id="vstatus">Listening for your answer&hellip;</div>
        <div class="waves" id="waves"><span></span><span></span><span></span><span></span><span></span></div>
      </div>

      <div class="cf" id="F"></div>
      <div id="A"></div>
    </div>

  </div>
</div>

<script>
var ITEM  = <?=json_encode(['id'=>$item['id'],'name'=>$item['name'],'base_price'=>$base_price,'single_size'=>$single_size])?>;
var STEPS = <?=json_encode($steps)?>;
var HOST  = <?=json_encode($host)?>;

var stepIdx=0, sizeLabel=ITEM.single_size||'', basePrice=ITEM.base_price;
var selections=[], multiSel=[];
var voiceMode=false, isSpeaking=false, isListening=false;

var feed=document.getElementById('F');
var area=document.getElementById('A');
var vbar=document.getElementById('vbar');
var micBtn=document.getElementById('micBtn');
var vstatus=document.getElementById('vstatus');
var waves=document.getElementById('waves');

// ---- Mode selection (user gesture required) ----
document.getElementById('btnVoice').onclick = function(){
    voiceMode = true;
    document.getElementById('startScreen').style.display='none';
    document.getElementById('chatUI').style.display='block';
    document.getElementById('modeLabel').textContent='Voice order — speak or tap';
    vbar.style.display='flex';
    initVoice();
    startOrder();
};
document.getElementById('btnTap').onclick = function(){
    voiceMode = false;
    document.getElementById('startScreen').style.display='none';
    document.getElementById('chatUI').style.display='block';
    document.getElementById('modeLabel').textContent='Tap to order';
    startOrder();
};

// ---- Speech Synthesis ----
var synth = window.speechSynthesis;
var voice = null;
function initVoice(){
    var vs = synth.getVoices();
    voice = vs.find(function(v){return v.lang.startsWith('en')&&/samantha|zira|karen|victoria|moira|tessa|fiona/i.test(v.name);})
           || vs.find(function(v){return v.lang.startsWith('en');})
           || vs[0];
}
if(synth.onvoiceschanged!==undefined) synth.onvoiceschanged=initVoice;

function speak(text, cb){
    if(!voiceMode){if(cb)cb();return;}
    synth.cancel();
    var u=new SpeechSynthesisUtterance(text);
    u.voice=voice; u.rate=0.93; u.pitch=1.05; u.volume=1;
    isSpeaking=true;
    micBtn.className='mic speaking';
    vstatus.innerHTML='<strong>Speaking&hellip;</strong>';
    waves.className='waves show';
    u.onend=u.onerror=function(){
        isSpeaking=false;
        micBtn.className='mic idle';
        waves.className='waves';
        vstatus.textContent='Tap mic or speak your answer';
        if(cb)cb();
    };
    synth.speak(u);
}

// ---- Speech Recognition ----
var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
var rec=SR?new SR():null;
if(rec){rec.continuous=false;rec.interimResults=true;rec.lang='en-US';}

micBtn.onclick=function(){
    if(isSpeaking){synth.cancel();return;}
    if(isListening){rec&&rec.stop();return;}
    listen(null);
};

function listen(cb){
    if(!rec||isListening)return;
    isListening=true;
    micBtn.className='mic listening';
    vstatus.innerHTML='<strong>Listening&hellip;</strong>';
    waves.className='waves show';
    rec.onresult=function(e){
        var t='';
        for(var i=e.resultIndex;i<e.results.length;i++) t+=e.results[i][0].transcript;
        vstatus.textContent='Heard: "'+t+'"';
        if(e.results[e.results.length-1].isFinal){
            rec.stop();
            if(cb) cb(t.trim().toLowerCase());
            else matchVoice(t.trim().toLowerCase());
        }
    };
    rec.onerror=rec.onend=function(){
        isListening=false;
        micBtn.className='mic idle';
        waves.className='waves';
        vstatus.textContent='Tap mic to speak';
    };
    rec.start();
}

function matchVoice(t){
    if(stepIdx>=STEPS.length)return;
    var step=STEPS[stepIdx], opts=step.options, multi=step.ui_type==='checkbox';
    if(multi){
        var matched=opts.filter(function(o){return t.indexOf(o.name.toLowerCase())!==-1;});
        if(matched.length){lockChips();handleChoice(matched);}
        else speak('Sorry, please say option names or tap them. Say confirm when done.');
    } else {
        var best=null;
        opts.forEach(function(o){if(t.indexOf(o.name.toLowerCase())!==-1)best=o;});
        if(!best) opts.forEach(function(o){if(t.indexOf(o.name.split(' ')[0].toLowerCase())!==-1)best=o;});
        if(best){lockChips();handleChoice([best]);}
        else speak('Sorry I didn\'t catch that. Options are: '+opts.map(function(o){return o.name;}).join(', '));
    }
}

// ---- Chat helpers ----
function sb(){feed.scrollTop=feed.scrollHeight;}
function addMsg(role,text){
    var w=document.createElement('div');w.className='msg '+(role==='bot'?'bot':'usr');
    if(role==='bot')w.innerHTML='<div class="av2">CG</div>';
    var b=document.createElement('div');b.className='bb';b.textContent=text;
    w.appendChild(b);feed.appendChild(w);sb();
}
function lockChips(){area.querySelectorAll('.chip,.conf').forEach(function(b){b.disabled=true;});}

function showChips(opts,cb,multi){
    area.innerHTML='';
    var wrap=document.createElement('div');wrap.className='ca2';
    if(multi){var h=document.createElement('p');h.className='hint';h.textContent='Select all that apply, then tap Confirm';wrap.appendChild(h);}
    var row=document.createElement('div');row.className='crow';
    var sel=new Set();
    opts.forEach(function(o){
        var lbl=o.price_delta>0?o.name+' (+$'+o.price_delta.toFixed(2)+')':o.name;
        var btn=document.createElement('button');btn.className='chip';btn.textContent=lbl;
        if(multi){
            btn.onclick=function(){if(btn.disabled)return;btn.classList.toggle('sel');if(sel.has(o.id))sel.delete(o.id);else sel.add(o.id);multiSel=opts.filter(function(x){return sel.has(x.id);});};
        } else {
            btn.onclick=function(){if(btn.disabled)return;lockChips();cb([o]);};
        }
        row.appendChild(btn);
    });
    wrap.appendChild(row);
    if(multi){
        var conf=document.createElement('button');conf.className='conf';conf.textContent='Confirm';
        conf.onclick=function(){if(conf.disabled)return;lockChips();cb(multiSel);multiSel=[];};
        wrap.appendChild(conf);
    }
    area.appendChild(wrap);
}

// ---- Step runner ----
function handleChoice(chosen){
    var step=STEPS[stepIdx];
    if(step.key==='__size__'){
        sizeLabel=chosen[0].name; basePrice=chosen[0].price_delta;
        addMsg('user',chosen[0].name); stepIdx++; setTimeout(run,400); return;
    }
    var d=chosen.length>0?chosen.map(function(o){return o.name;}).join(', '):'None';
    addMsg('user',d);
    chosen.filter(function(o){return o.name!=='None';}).forEach(function(o){
        selections.push({option_id:o.id,group_label:step.label.replace('?','').trim(),choice:o.name,price_delta:o.price_delta});
    });
    stepIdx++; setTimeout(run,400);
}

function startOrder(){
    addMsg('bot','Hi! Let me help you order '+ITEM.name+'.');
    speak('Hi! I\'m your Cedar Grove order assistant. Let me help you order '+ITEM.name+'.', function(){
        setTimeout(run,300);
    });
    if(!voiceMode) setTimeout(run,400);
}

function run(){
    area.innerHTML='';
    if(stepIdx>=STEPS.length){showReceipt();return;}
    var step=STEPS[stepIdx], multi=step.ui_type==='checkbox';
    var prompt=step.label+' Options are: '+step.options.map(function(o){return o.name;}).join(', ')+(multi?'. Tap confirm when done.':'.');
    addMsg('bot',step.label);
    showChips(step.options,handleChoice,multi);
    speak(prompt,function(){
        if(!multi) setTimeout(function(){listen(null);},300);
    });
}

// ---- Receipt ----
function showReceipt(){
    area.innerHTML='';
    var mt=selections.reduce(function(s,x){return s+(x.price_delta||0);},0), tot=basePrice+mt;
    var summary='Great! Here is your order. '+ITEM.name;
    if(sizeLabel) summary+=', '+sizeLabel+' size';
    if(selections.length) summary+=', with '+selections.map(function(s){return s.choice;}).join(', ');
    summary+='. Total is $'+tot.toFixed(2)+'. Shall I add this to your basket? Say yes to confirm.';
    addMsg('bot','Here is your order summary:');
    speak(summary);

    var rc=document.createElement('div');rc.className='rc';
    var h='<p class="rt">'+esc(ITEM.name)+'</p>';
    if(sizeLabel){h+='<div class="rr"><span>Size</span><span>'+esc(sizeLabel)+'</span></div>';h+='<div class="rr"><span>Base</span><span>$'+basePrice.toFixed(2)+'</span></div>';}
    else h+='<div class="rr"><span>Price</span><span>$'+basePrice.toFixed(2)+'</span></div>';
    selections.forEach(function(s){h+='<div class="rm">'+esc(s.group_label)+': '+esc(s.choice)+(s.price_delta>0?' <span style="color:#1D9E75">+$'+s.price_delta.toFixed(2)+'</span>':'')+'</div>';});
    h+='<div class="rr rT"><span>Total</span><span>$'+tot.toFixed(2)+'</span></div>';
    var btn=document.createElement('button');btn.className='ab';btn.textContent='Add to basket';
    btn.onclick=function(){addBasket(tot,btn);};
    rc.innerHTML=h;rc.appendChild(btn);area.appendChild(rc);

    // Listen for yes/no
    if(voiceMode) setTimeout(function(){
        listen(function(t){
            if(/yes|add|confirm|ok|sure/.test(t)) addBasket(tot,btn);
            else if(/no|cancel/.test(t)) speak('No problem, tap back to return to the menu.');
            else speak('Please say yes to add to your basket, or tap the button.');
        });
    },4000);
}

async function addBasket(tot,btn){
    btn.disabled=true;btn.textContent='Adding…';
    speak('Adding to your basket!');
    try{
        var r=await fetch(HOST+'/add_to_basket.php',{method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({item_id:ITEM.id,item_name:ITEM.name,size_label:sizeLabel,base_price:basePrice,selections:selections,total:tot})});
        var d=await r.json();
        if(d.ok){
            btn.textContent='✓ Added!';btn.style.background='#0F6E56';
            var bdg=document.querySelector('.bdg');
            if(!bdg){bdg=document.createElement('span');bdg.className='bdg';document.querySelector('.bkt').appendChild(bdg);}
            bdg.textContent=d.count;
            var nav=document.createElement('div');nav.className='nav-row';
            nav.innerHTML='<a href="'+HOST+'/index.php" class="nc nc-s">+ Add another item</a><a href="'+HOST+'/basket.php" class="nc nc-p">View basket &rarr;</a>';
            area.appendChild(nav);
            speak('Done! Would you like to add anything else?');
        } else {btn.textContent='Error — try again';btn.disabled=false;}
    }catch(e){btn.textContent='Error — try again';btn.disabled=false;}
}

function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
</script>
</body>
</html>
